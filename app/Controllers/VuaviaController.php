<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\VuaviaService;

/**
 * Dịch vụ bán tài khoản & nick — API đối tác Vuavia.
 *
 * Người dùng:
 *  - /services/vuavia-accounts            → danh sách sản phẩm + mua hàng
 *  - /services/vuavia-accounts/order/{id} → chi tiết đơn & nhận hàng
 *
 * Admin:
 *  - /admin/vuavia-accounts               → cấu hình API key, số dư, thống kê
 *
 * Lưu ý: toàn bộ thông tin đối tác (tên API, base URL, key) chỉ hiển thị
 * trong khu vực quản trị — phía khách chỉ thấy tên dịch vụ.
 */
class VuaviaController
{
    // ================= Người dùng =================

    public function index(): void
    {
        $userId = (int)Auth::id();

        $q          = trim((string)($_GET['q'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $page       = max(0, (int)($_GET['page'] ?? 0));

        // Đồng bộ sản phẩm tự động (cache 5 phút)
        if (
            VuaviaService::configured()
            && setting('vuavia_auto_sync', '1') === '1'
            && VuaviaService::shouldSync()
        ) {
            VuaviaService::syncProducts();
            VuaviaService::markSynced();
        }

        $products = VuaviaService::products([
            'q'           => $q,
            'category_id' => $categoryId,
            'page'        => $page,
            'size'        => 60,
        ]);

        if (!$products) {
            $products = VuaviaService::localProducts([
                'q'           => $q,
                'category_id' => $categoryId,
            ]);
        }

        view('services/vuavia-accounts', [
            'title'      => 'Tài khoản & Nick',
            'products'   => $products,
            'categories' => VuaviaService::categories(),
            'orders'     => VuaviaService::myOrders($userId),
            'configured' => VuaviaService::configured(),
            'enabled'    => VuaviaService::enabled(),
            'markup'     => VuaviaService::markup(),
            'q'          => $q,
            'categoryId' => $categoryId,
            'page'       => $page,
        ]);
    }

    /**
     * Đồng bộ danh mục sản phẩm.
     */
    public function sync(): void
    {
        $res = VuaviaService::syncProducts();
        if ($res['ok']) {
            VuaviaService::markSynced();
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/vuavia-accounts'));
    }

    /**
     * Mua hàng.
     *
     * Tiền được trừ từ ví nội bộ theo giá bán (giá đối tác + markup),
     * sau đó gọi API đặt hàng. Nếu API lỗi → hoàn tiền.
     */
    public function purchase(): void
    {
        CSRF::verifyOrFail();

        if (!VuaviaService::enabled()) {
            flash('error', 'Dịch vụ tài khoản & nick hiện đang tạm dừng.');
            redirect(url('/services/vuavia-accounts'));
        }

        $productId = trim((string)($_POST['product_id'] ?? ''));
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));

        $product = VuaviaService::findProduct($productId);
        if (!$product) {
            flash('error', 'Không tìm thấy sản phẩm. Vui lòng tải lại trang.');
            redirect(url('/services/vuavia-accounts'));
        }

        if ((int)$product['stock_count'] <= 0) {
            flash('error', 'Sản phẩm "' . $product['name'] . '" hiện tạm hết hàng.');
            redirect(url('/services/vuavia-accounts'));
        }

        $markup    = VuaviaService::markup();
        $unitCost  = (float)$product['price'];
        $sellPrice = $markup > 0 ? (int)ceil($unitCost * (1 + $markup / 100)) : (int)$unitCost;
        $total     = $sellPrice * $quantity;

        $userId = (int)Auth::id();
        $balance = Wallet::balance($userId);
        if ($balance < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money($balance) . '.');
            redirect(url('/services/vuavia-accounts'));
        }

        // Trừ tiền ví trước
        try {
            Wallet::apply(
                $userId,
                'adjustment',
                -$total,
                'Mua ' . $product['name'] . ' ×' . $quantity,
                'vuavia_order',
                $productId
            );
        } catch (\Throwable $e) {
            flash('error', 'Lỗi trừ phí: ' . $e->getMessage());
            redirect(url('/services/vuavia-accounts'));
        }

        $res = VuaviaService::createOrder($userId, $productId, $quantity);

        if (!$res['ok']) {
            // Hoàn tiền khi API lỗi
            Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền mua tài khoản thất bại',
                'vuavia_refund',
                $productId
            );

            $orderId = VuaviaService::saveOrder($userId, [
                'product_id'   => (int)$productId,
                'product_name' => (string)$product['name'],
                'quantity'     => $quantity,
                'total_amount' => $total,
                'unit_price'   => $sellPrice,
                'status'       => 'FAILED',
                'error_msg'    => $res['message'],
            ]);

            flash('error', '[' . $res['errorCode'] . '] ' . $res['message']);
            redirect(url('/services/vuavia-accounts'));
        }

        $status = (string)($res['status'] ?? 'PENDING_DELIVERY');

        $orderId = VuaviaService::saveOrder($userId, [
            'product_id'       => (int)$productId,
            'product_name'     => (string)$product['name'],
            'quantity'         => $quantity,
            'total_amount'     => $total,
            'unit_price'       => $sellPrice,
            'status'           => $status,
            'partner_order_id' => (int)($res['partner_order_id'] ?? 0),
        ]);

        VuaviaService::saveDeliveries($orderId, (array)$res['deliveredItems']);

        // AUTO → đã giao ngay. MANUAL → chờ đối tác giao, admin sẽ cập nhật.
        flash(
            'success',
            $status === 'DELIVERED'
                ? 'Đã mua thành công ' . $quantity . ' tài khoản. Mã đơn: #' . $orderId
                : 'Đã đặt hàng thành công. Mã đơn: #' . $orderId . ' — hàng sẽ được giao sau.'
        );

        redirect(url('/services/vuavia-accounts/order/' . $orderId));
    }

    /**
     * Chi tiết đơn & nhận hàng.
     */
    public function order(int $id): void
    {
        $order = VuaviaService::findOrder((int)Auth::id(), $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        view('services/vuavia-order', [
            'title' => 'Chi tiết đơn #' . (int)$order['id'],
            'order' => $order,
        ]);
    }

    /**
     * Đánh dấu đã nhận hàng.
     */
    public function redeem(): void
    {
        CSRF::verifyOrFail();

        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        $ok = VuaviaService::markRedeemed((int)Auth::id(), $deliveryId);

        flash($ok ? 'success' : 'error', $ok ? 'Đã đánh dấu đã nhận.' : 'Không tìm thấy hàng.');

        redirect(url('/services/vuavia-accounts/order/' . (int)($_POST['order_id'] ?? 0)));
    }

    /**
     * Xem nhanh số dư ví đối tác (chỉ admin).
     */
    public function partnerBalance(): void
    {
        if (!Auth::isAdmin()) {
            json_error('Chỉ quản trị viên được xem.', 403);
        }

        $res = VuaviaService::balance();
        if (!$res['ok']) {
            json_error($res['message']);
        }

        json_success([
            'balance' => $res['balance'],
            'text'    => money((float)$res['balance']),
        ]);
    }

    // ================= Admin =================

    /**
     * Trang quản trị.
     */
    public function admin(): void
    {
        view('admin/vuavia-accounts', [
            'title'     => 'Dịch vụ Tài khoản & Nick',
            'products'  => VuaviaService::localProducts(),
            'orders'    => VuaviaService::allOrders(),
            'stats'     => VuaviaService::stats(),
            'balance'   => VuaviaService::balance(),
            'baseUrl'   => VuaviaService::baseUrl(),
            'hasKey'    => VuaviaService::configured(),
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
            flash('error', 'Base URL phải dùng HTTPS (API chỉ chấp nhận kết nối mã hoá). Cấu hình khác vẫn đã được lưu.');
        } elseif ($baseUrl !== '') {
            setting_set('vuavia_base_url', rtrim($baseUrl, '/'), 'Tài khoản & Nick');
        }

        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($key !== '') {
            VuaviaService::saveApiKey($key);
        }

        setting_set('vuavia_service_on', isset($_POST['service_on']) ? '1' : '0', 'Tài khoản & Nick');
        setting_set('vuavia_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'Tài khoản & Nick');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('vuavia_markup', (string)(int)$markup, 'Tài khoản & Nick');

        flash('success', 'Đã lưu cấu hình dịch vụ tài khoản & nick.');
        redirect(url('/admin/vuavia-accounts'));
    }

    /**
     * Đồng bộ sản phẩm (admin).
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = VuaviaService::syncProducts();
        if ($res['ok']) {
            VuaviaService::markSynced();
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/admin/vuavia-accounts'));
    }

    /**
     * Admin giao hàng cho đơn MANUAL (chờ đối tác giao).
     */
    public function adminDeliver(): void
    {
        CSRF::verifyOrFail();

        $orderId = (int)($_POST['order_id'] ?? 0);
        $items   = trim((string)($_POST['items'] ?? ''));

        $order = Database::fetch('SELECT * FROM vuavia_orders WHERE id = ?', [$orderId]);
        if (!$order) {
            flash('error', 'Không tìm thấy đơn hàng.');
            redirect(url('/admin/vuavia-accounts'));
        }

        $list = [];
        foreach (preg_split('/[\r\n]+/', $items) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $list[] = $line;
            }
        }

        if ($list) {
            VuaviaService::saveDeliveries($orderId, $list);
            Database::update('vuavia_orders', [
                'status'        => 'DELIVERED',
                'delivery_note' => 'Admin giao hàng thủ công.',
            ], 'id = :id', ['id' => $orderId]);
            flash('success', 'Đã giao ' . count($list) . ' hàng cho đơn #' . $orderId . '.');
        } else {
            flash('error', 'Chưa nhập hàng nào để giao.');
        }

        redirect(url('/admin/vuavia-accounts'));
    }
}
