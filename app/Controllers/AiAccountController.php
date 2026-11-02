<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\AiAccountService;

/**
 * Dịch vụ bán tài khoản AI — tích hợp API đối tác GenzShop.
 *
 * Người dùng:
 *  - /services/ai-accounts            → danh sách sản phẩm + mua hàng
 *  - /services/ai-accounts/order/{id} → chi tiết đơn & nhận tài khoản
 *
 * Admin:
 *  - /admin/ai-accounts               → cấu hình API key, số dư ví, thống kê
 */
class AiAccountController
{
    // ================= Người dùng =================

    public function index(): void
    {
        $userId = (int)Auth::id();

        // Đồng bộ sản phẩm tự động (cache 5 phút)
        if (
            AiAccountService::configured()
            && setting('genzshop_auto_sync', '1') === '1'
            && AiAccountService::shouldSync()
        ) {
            AiAccountService::syncProducts();
            AiAccountService::markSynced();
        }

        $products = AiAccountService::products();
        if (!$products) {
            $products = AiAccountService::localProducts();
        }

        // Lọc theo nền tảng (brand) khi người dùng bấm vào lưới danh mục AI
        $brand = trim((string)($_GET['brand'] ?? ''));
        if ($brand !== '') {
            $products = array_values(array_filter($products, static function ($p) use ($brand) {
                $slug = brand_slug((string)($p['type'] ?? ''));
                if ($slug === null) {
                    $slug = brand_slug((string)($p['type_label'] ?? ''));
                }
                if ($slug === null) {
                    $slug = brand_slug((string)($p['name'] ?? ''));
                }
                return $slug === $brand;
            }));
        }

        $orders = AiAccountService::myOrders($userId);

        view('services/ai-accounts', [
            'title'      => 'Tài khoản AI',
            'products'   => $products,
            'orders'     => $orders,
            'configured' => AiAccountService::configured(),
            'enabled'    => AiAccountService::enabled(),
        ]);
    }

    /**
     * Đồng bộ sản phẩm thủ công.
     */
    public function sync(): void
    {
        $res = AiAccountService::syncProducts();
        if ($res['ok']) {
            AiAccountService::markSynced();
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/ai-accounts'));
    }

    /**
     * Mua tài khoản.
     *
     * Tiền được trừ từ ví nội bộ theo giá bán (giá đại lý + markup),
     * sau đó gọi purchase.php. Nếu API lỗi → hoàn tiền.
     */
    public function purchase(): void
    {
        CSRF::verifyOrFail();

        if (!AiAccountService::enabled()) {
            flash('error', 'Dịch vụ tài khoản AI hiện đang tạm dừng.');
            redirect(url('/services/ai-accounts'));
        }

        $productId = trim((string)($_POST['product_id'] ?? ''));
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));

        // Tìm giá bán (đồng bộ từ API trước nếu có)
        $product = null;
        foreach (AiAccountService::products(true) as $p) {
            if ($p['product_id'] === $productId) {
                $product = $p;
                break;
            }
        }
        if (!$product) {
            foreach (AiAccountService::localProducts() as $p) {
                if ($p['product_id'] === $productId) {
                    $product = $p;
                    break;
                }
            }
        }

        if (!$product) {
            flash('error', 'Không tìm thấy sản phẩm. Vui lòng tải lại trang.');
            redirect(url('/services/ai-accounts'));
        }

        if ((int)$product['available'] <= 0) {
            flash('error', 'Sản phẩm ' . $product['name'] . ' hiện tạm hết hàng.');
            redirect(url('/services/ai-accounts'));
        }

        // Giá bán cho khách = giá đại lý + markup %
        $markup     = (float)setting('genzshop_markup', '0');
        $unitCost   = (float)$product['price'];
        $sellPrice  = $markup > 0 ? (int)ceil($unitCost * (1 + $markup / 100)) : (int)$unitCost;
        $total      = $sellPrice * $quantity;

        $userId = (int)Auth::id();
        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money($balance) . '.');
            redirect(url('/services/ai-accounts'));
        }

        // Idempotency key: dùng lại nếu khách bấm lại (chống trừ tiền 2 lần)
        $idemKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($idemKey === '') {
            $idemKey = AiAccountService::makeIdempotencyKey($userId, $productId);
        }

        // Đơn đã tồn tại với key này → không trừ tiền lần hai, chỉ mở lại đơn cũ
        $existing = Database::fetch(
            'SELECT id FROM ai_account_orders WHERE idempotency_key = ? AND user_id = ?',
            [$idemKey, $userId]
        );
        if ($existing) {
            flash('success', 'Đơn này đã được tạo trước đó, đang mở lại đơn cũ (không trừ tiền lần hai).');
            redirect(url('/services/ai-accounts/order/' . (int)$existing['id']));
        }

        // Trừ tiền ví trước
        \App\Core\Wallet::apply(
            $userId,
            'adjustment',
            -$total,
            'Mua ' . $product['name'] . ' ×' . $quantity,
            'ai_account',
            $productId
        );

        $email = trim((string)($_POST['customer_email'] ?? ''));

        $res = AiAccountService::purchase($userId, $productId, $quantity, $idemKey, $email);

        if (!$res['ok']) {
            // Hoàn tiền khi API lỗi
            \App\Core\Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền mua tài khoản AI thất bại',
                'ai_account_refund',
                $productId
            );

            $orderId = AiAccountService::createOrder($userId, [
                'product_id'      => $productId,
                'product_name'    => (string)$product['name'],
                'type'            => (string)$product['type'],
                'quantity'        => $quantity,
                'amount'          => $total,
                'idempotency_key' => $idemKey,
                'status'          => 'failed',
                'message'         => $res['message'],
            ]);

            flash('error', $res['message']);
            redirect(url('/services/ai-accounts'));
        }

        $orderId = AiAccountService::createOrder($userId, [
            'product_id'      => $productId,
            'product_name'    => (string)$product['name'],
            'type'            => (string)$product['type'],
            'quantity'        => $quantity,
            'amount'          => $total,
            'order_code'      => (string)$res['orderCode'],
            'idempotency_key' => $idemKey,
            'status'          => 'success',
            'replay'          => $res['replay'],
            'message'         => $res['message'],
        ]);

        AiAccountService::saveDeliveries($orderId, $res['accounts']);

        // Nếu là replay → không trừ tiền lần 2, hoàn lại tiền đã trừ
        if ($res['replay']) {
            \App\Core\Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền đơn trùng idempotency key',
                'ai_account_refund',
                $productId
            );
        }

        flash(
            'success',
            $res['replay']
                ? 'Đơn trùng — đã trả lại đơn cũ, không trừ tiền lần hai. Mã: ' . $res['orderCode']
                : 'Đã mua thành công ' . $res['quantity'] . ' tài khoản. Mã đơn: ' . $res['orderCode']
        );

        redirect(url('/services/ai-accounts/order/' . $orderId));
    }

    /**
     * Chi tiết đơn & nhận tài khoản.
     */
    public function order(int $id): void
    {
        $order = AiAccountService::findOrder((int)Auth::id(), $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        view('services/ai-account-order', [
            'title' => 'Chi tiết đơn ' . ($order['order_code'] ?: ('#' . $order['id'])),
            'order' => $order,
        ]);
    }

    /**
     * Đánh dấu đã nhận tài khoản.
     */
    public function redeem(): void
    {
        CSRF::verifyOrFail();

        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        $ok = AiAccountService::markRedeemed((int)Auth::id(), $deliveryId);

        flash($ok ? 'success' : 'error', $ok ? 'Đã đánh dấu đã nhận.' : 'Không tìm thấy tài khoản.');

        redirect(url('/services/ai-accounts/order/' . (int)($_POST['order_id'] ?? 0)));
    }

    /**
     * Xem nhanh số dư ví GenzShop.
     */
    public function partnerBalance(): void
    {
        $wantsJson = is_ajax()
            || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if (!$wantsJson) {
            redirect(url('/services/ai-accounts'));
        }

        $res = AiAccountService::balance();
        if (!$res['ok']) {
            json_error($res['message']);
        }

        json_success([
            'balance'     => $res['balance'],
            'balanceText' => $res['balanceText'] ?: money($res['balance']),
            'currency'    => $res['currency'],
        ]);
    }

    // ================= Admin =================

    /**
     * Trang quản trị.
     */
    public function admin(): void
    {
        $products = AiAccountService::localProducts();
        $orders   = AiAccountService::allOrders();
        $stats    = AiAccountService::stats();
        $balance  = AiAccountService::balance();

        view('admin/ai-accounts', [
            'title'     => 'Dịch vụ tài khoản AI',
            'products'  => $products,
            'orders'    => $orders,
            'stats'     => $stats,
            'balance'   => $balance,
            'baseUrl'   => AiAccountService::baseUrl(),
            'hasKey'    => AiAccountService::configured(),
        ], 'layouts/admin');
    }

    /**
     * Lưu cấu hình.
     */
    public function adminSave(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https://#i', $baseUrl)) {
            // Vẫn lưu các trường khác, chỉ báo lỗi base URL
            flash('error', 'Base URL phải dùng HTTPS (API chỉ chấp nhận kết nối mã hoá). Cấu hình khác vẫn đã được lưu.');
        } else {
            if ($baseUrl !== '') {
                setting_set('genzshop_base_url', rtrim($baseUrl, '/'), 'Tài khoản AI');
            }
        }

        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($key !== '') {
            AiAccountService::saveApiKey($key);
        }

        setting_set('genzshop_service_on', isset($_POST['service_on']) ? '1' : '0', 'Tài khoản AI');
        setting_set('genzshop_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'Tài khoản AI');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('genzshop_markup', (string)(int)$markup, 'Tài khoản AI');

        flash('success', 'Đã lưu cấu hình dịch vụ tài khoản AI.');
        redirect(url('/admin/ai-accounts'));
    }

    /**
     * Đồng bộ sản phẩm (admin).
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = AiAccountService::syncProducts();
        if ($res['ok']) {
            AiAccountService::markSynced();
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/admin/ai-accounts'));
    }
}
