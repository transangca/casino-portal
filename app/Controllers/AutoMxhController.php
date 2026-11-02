<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\AutoMxhService;

/**
 * Dịch vụ Auto MXH — mở khóa & bảo vệ tài khoản (API đối tác TrungTamMMO).
 *
 * Khách hàng:
 *  - /services/automxh                 → danh mục gói, đặt đơn
 *  - /services/automxh/order/{id}       → chi tiết đơn + tiến độ
 *  - /services/automxh/apikey          → quản lý apikey của tôi
 *
 * Quản trị:
 *  - /admin/automxh                    → cấu hình, đồng bộ, đơn, thống kê
 */
class AutoMxhController
{
    // ================= Khách hàng =================

    /**
     * Trang danh mục + đặt đơn.
     */
    public function index(): void
    {
        $userId = (int)Auth::id();

        // Đồng bộ danh mục tự động (cache 5 phút)
        if (
            AutoMxhService::configured()
            && setting('automxh_auto_sync', '1') === '1'
            && AutoMxhService::shouldSync()
        ) {
            AutoMxhService::sync();
            AutoMxhService::markSynced();
        }

        $keyword = trim((string)($_GET['q'] ?? ''));
        $tree    = AutoMxhService::catalogTree($keyword);
        $orders  = AutoMxhService::myOrders($userId, 10);

        view('services/automxh', [
            'title'      => 'Auto MXH — Mở khóa tài khoản',
            'tree'       => $tree,
            'orders'     => $orders,
            'keyword'    => $keyword,
            'configured' => AutoMxhService::configured(),
            'enabled'    => AutoMxhService::enabled(),
            'hasKey'     => AutoMxhService::userKey($userId) !== null,
        ]);
    }

    /**
     * Chi tiết đơn (kèm poll trạng thái).
     */
    public function order(int $id): void
    {
        $userId = (int)Auth::id();
        $order  = AutoMxhService::findOrder($userId, $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        // Làm mới trạng thái nếu đơn còn đang chạy
        if (in_array((string)$order['status'], ['pending', 'processing'], true) && (int)$order['remote_order'] > 0) {
            $st = AutoMxhService::status((int)$order['remote_order']);
            if ($st['ok']) {
                AutoMxhService::updateOrderStatus(
                    $id,
                    $st['status'],
                    $st['progress_status'],
                    $st['progress_remains']
                );
                $order = AutoMxhService::findOrder($userId, $id);
            }
        }

        view('services/automxh-order', [
            'title' => 'Đơn Auto MXH #' . ($order['remote_order'] ?: $order['id']),
            'order' => $order,
        ]);
    }

    /**
     * Đặt đơn.
     *
     * Tiền trừ theo giá bán của site (giá đối tác + markup) qua Wallet::apply.
     * Nếu API đối tác lỗi → hoàn tiền ngay.
     */
    public function submit(): void
    {
        CSRF::verifyOrFail();

        if (!AutoMxhService::enabled()) {
            flash('error', 'Dịch vụ Auto MXH hiện đang tạm dừng.');
            redirect(url('/services/automxh'));
        }

        $userId    = (int)Auth::id();
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $link      = trim((string)($_POST['link'] ?? ''));
        $buyerInfo = trim((string)($_POST['buyer_info'] ?? ''));
        $customVal = trim((string)($_POST['custom_value'] ?? ''));

        if ($variantId <= 0) {
            flash('error', 'Vui lòng chọn gói dịch vụ.');
            redirect(url('/services/automxh'));
        }
        if ($link === '') {
            flash('error', 'Vui lòng nhập link cần xử lý.');
            redirect(url('/services/automxh'));
        }
        if (mb_strlen($link) > 1000) {
            flash('error', 'Link quá dài (tối đa 1000 ký tự).');
            redirect(url('/services/automxh'));
        }

        // Tìm gói trong DB (đã đồng bộ)
        $variant = AutoMxhService::findVariant($variantId);
        if (!$variant) {
            flash('error', 'Không tìm thấy gói dịch vụ. Vui lòng tải lại trang.');
            redirect(url('/services/automxh'));
        }

        $cost     = (float)$variant['price'];
        $sellPrice = AutoMxhService::sellPrice($cost);
        $quantity  = max(1, (int)$variant['quantity']);
        $total     = $sellPrice * $quantity;

        $balance = Wallet::balance($userId);
        if ($balance < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money($balance) . '.');
            redirect(url('/services/automxh'));
        }

        // Idempotency: khách bấm lại không trừ tiền 2 lần
        $idemKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($idemKey === '') {
            $idemKey = 'amx-' . $userId . '-' . $variantId . '-' . bin2hex(random_bytes(6));
        }

        $existing = Database::fetch(
            'SELECT id FROM automxh_orders WHERE idempotency_key = ? AND user_id = ?',
            [$idemKey, $userId]
        );
        if ($existing) {
            flash('success', 'Đơn này đã được tạo trước đó, đang mở lại đơn cũ (không trừ tiền lần hai).');
            redirect(url('/services/automxh/order/' . (int)$existing['id']));
        }

        // Trừ tiền ví nội bộ trước
        Wallet::apply(
            $userId,
            'adjustment',
            -$total,
            'Auto MXH: ' . (string)$variant['name'],
            'automxh',
            (string)$variantId
        );

        $res = AutoMxhService::createOrder(
            $variantId,
            $link,
            (int)$variant['product_id'],
            $buyerInfo,
            $customVal
        );

        if (!$res['ok']) {
            // Hoàn tiền khi API lỗi
            Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền đặt đơn Auto MXH thất bại',
                'automxh_refund',
                (string)$variantId
            );

            $orderId = AutoMxhService::createOrderRow($userId, [
                'variant_id'       => $variantId,
                'product_id'       => (int)$variant['product_id'],
                'category_slug'    => (string)$variant['category_slug'],
                'variant_name'     => (string)$variant['name'],
                'product_name'     => (string)$variant['product_name'],
                'link'             => $link,
                'buyer_info'       => $buyerInfo,
                'custom_value'     => $customVal,
                'quantity'         => $quantity,
                'price'            => $cost,
                'amount'           => $total,
                'charge'           => 0,
                'status'           => AutoMxhService::STATUS_FAILED,
                'idempotency_key'  => $idemKey,
                'message'          => $res['message'],
            ]);

            flash('error', $res['message']);
            redirect(url('/services/automxh/order/' . $orderId));
        }

        $orderId = AutoMxhService::createOrderRow($userId, [
            'remote_order'     => (int)$res['order_id'],
            'variant_id'       => $variantId,
            'product_id'       => (int)$variant['product_id'],
            'category_slug'    => (string)$variant['category_slug'],
            'variant_name'     => (string)$variant['name'],
            'product_name'     => (string)$variant['product_name'],
            'link'             => $link,
            'buyer_info'       => $buyerInfo,
            'custom_value'     => $customVal,
            'quantity'         => $quantity,
            'price'            => $cost,
            'amount'           => $total,
            'charge'           => (float)$res['charge'],
            'status'           => (string)$res['status'],
            'balance_after'    => (float)$res['balance'],
            'idempotency_key'  => $idemKey,
            'message'          => (string)$res['message'],
            'raw'              => $res['raw'],
        ]);

        flash('success', 'Đã tạo đơn Auto MXH thành công. Mã đơn: ' . (int)$res['order_id']);
        redirect(url('/services/automxh/order/' . $orderId));
    }

    /**
     * AJAX: lấy trạng thái đơn.
     */
    public function status(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_error('Yêu cầu không hợp lệ', 400);
        }

        $userId = (int)Auth::id();
        $id     = (int)($_GET['id'] ?? 0);
        $order  = AutoMxhService::findOrder($userId, $id);
        if (!$order) {
            json_error('Không tìm thấy đơn hàng', 404);
        }

        if ((int)$order['remote_order'] > 0 && in_array((string)$order['status'], ['pending', 'processing'], true)) {
            $st = AutoMxhService::status((int)$order['remote_order']);
            if ($st['ok']) {
                AutoMxhService::updateOrderStatus($id, $st['status'], $st['progress_status'], $st['progress_remains']);
                $order = AutoMxhService::findOrder($userId, $id);
            }
        }

        json_success([
            'status'     => (string)$order['status'],
            'statusText' => AutoMxhService::statusLabel((string)$order['status']),
            'badge'      => AutoMxhService::statusBadge((string)$order['status']),
            'progress'   => (string)$order['progress_status'],
            'remains'    => (string)$order['progress_remains'],
            'finished'   => !in_array((string)$order['status'], ['pending', 'processing'], true),
        ]);
    }

    /**
     * AJAX: tính giá trước khi đặt (đối chiếu với quote của đối tác).
     */
    public function quote(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_error('Yêu cầu không hợp lệ', 400);
        }

        $variantId = (int)($_GET['variant_id'] ?? 0);
        if ($variantId <= 0) {
            json_error('Thiếu variant_id', 400);
        }

        $variant = AutoMxhService::findVariant($variantId);
        if (!$variant) {
            json_error('Không tìm thấy gói dịch vụ', 404);
        }

        $res = AutoMxhService::quote($variantId, (int)$variant['product_id']);

        json_success([
            'ok'          => $res['ok'],
            'message'     => (string)($res['message'] ?? ''),
            'cost'        => (float)$variant['price'],
            'sell'        => AutoMxhService::sellPrice((float)$variant['price']),
            'sellText'    => money(AutoMxhService::sellPrice((float)$variant['price'])),
            'costText'    => money((float)$variant['price']),
            'checkout'    => $res['checkout'] ?? [],
            'discount'    => $res['discount'] ?? [],
        ]);
    }

    // ================= API key của khách =================

    /**
     * Trang quản lý apikey của khách.
     */
    public function apikey(): void
    {
        $userId = (int)Auth::id();

        view('services/automxh-apikey', [
            'title' => 'API key Auto MXH của tôi',
            'keys'  => AutoMxhService::userKeys($userId),
            'primary' => AutoMxhService::userKey($userId),
        ]);
    }

    /**
     * Lưu apikey mới.
     */
    public function saveApikey(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $apikey = trim((string)($_POST['apikey'] ?? ''));
        $label  = trim((string)($_POST['label'] ?? ''));

        if ($apikey === '') {
            flash('error', 'Vui lòng nhập API key.');
            redirect(url('/services/automxh/apikey'));
        }
        if (mb_strlen($apikey) > 180) {
            flash('error', 'API key quá dài.');
            redirect(url('/services/automxh/apikey'));
        }

        AutoMxhService::saveUserKey($userId, $apikey, $label);

        flash('success', 'Đã lưu API key. Hệ thống sẽ dùng key này cho các đơn của bạn.');
        redirect(url('/services/automxh/apikey'));
    }

    public function deleteApikey(): void
    {
        CSRF::verifyOrFail();

        $ok = AutoMxhService::deleteUserKey((int)Auth::id(), (int)($_POST['key_id'] ?? 0));

        flash($ok ? 'success' : 'error', $ok ? 'Đã xoá API key.' : 'Không tìm thấy API key.');
        redirect(url('/services/automxh/apikey'));
    }

    public function setPrimaryApikey(): void
    {
        CSRF::verifyOrFail();

        $ok = AutoMxhService::setPrimaryKey((int)Auth::id(), (int)($_POST['key_id'] ?? 0));

        flash($ok ? 'success' : 'error', $ok ? 'Đã đặt API key làm key chính.' : 'Không tìm thấy API key.');
        redirect(url('/services/automxh/apikey'));
    }

    // ================= Quản trị =================

    /**
     * Trang quản trị.
     */
    public function admin(): void
    {
        $orders   = AutoMxhService::allOrders(100);
        $stats    = AutoMxhService::stats();
        $balance  = AutoMxhService::balance();
        $pending  = AutoMxhService::pendingOrders(50);

        view('admin/automxh', [
            'title'     => 'Dịch vụ Auto MXH (mở khóa tài khoản)',
            'orders'    => $orders,
            'pending'   => $pending,
            'stats'     => $stats,
            'balance'   => $balance,
            'categories'=> AutoMxhService::localCategories(),
            'variants'  => AutoMxhService::localVariants(),
            'baseUrl'   => AutoMxhService::baseUrl(),
            'hasKey'    => AutoMxhService::configured(),
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
            flash('error', 'Base URL phải dùng HTTPS. Cấu hình khác vẫn đã được lưu.');
        } elseif ($baseUrl !== '') {
            setting_set('automxh_base_url', rtrim($baseUrl, '/'), 'Auto MXH');
        }

        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($key !== '') {
            AutoMxhService::saveApiKey($key);
        }

        setting_set('automxh_service_on', isset($_POST['service_on']) ? '1' : '0', 'Auto MXH');
        setting_set('automxh_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'Auto MXH');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('automxh_markup', (string)(int)$markup, 'Auto MXH');

        flash('success', 'Đã lưu cấu hình dịch vụ Auto MXH.');
        redirect(url('/admin/automxh'));
    }

    /**
     * Đồng bộ danh mục.
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = AutoMxhService::sync();
        if ($res['ok']) {
            AutoMxhService::markSynced();
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/automxh'));
    }

    /**
     * Làm mới trạng thái đơn.
     */
    public function adminRefresh(): void
    {
        CSRF::verifyOrFail();

        $res = AutoMxhService::refreshStatuses(50);

        flash('success', 'Đã kiểm tra ' . (int)$res['checked'] . ' đơn, cập nhật ' . (int)$res['updated'] . ' đơn.');
        redirect(url('/admin/automxh'));
    }

    /**
     * Đổi trạng thái đơn thủ công (admin xử lý tay các gói không tự động được).
     */
    public function adminUpdateOrder(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['order_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));

        $allowed = [
            AutoMxhService::STATUS_PENDING,
            AutoMxhService::STATUS_PROCESSING,
            AutoMxhService::STATUS_COMPLETED,
            AutoMxhService::STATUS_FAILED,
        ];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Trạng thái không hợp lệ.');
            redirect(url('/admin/automxh'));
        }

        $order = Database::fetch('SELECT * FROM automxh_orders WHERE id = ?', [$id]);
        if (!$order) {
            flash('error', 'Không tìm thấy đơn.');
            redirect(url('/admin/automxh'));
        }

        AutoMxhService::updateOrderStatus(
            $id,
            $status,
            (string)($_POST['progress_status'] ?? ''),
            (string)($_POST['progress_remains'] ?? '')
        );

        flash('success', 'Đã cập nhật trạng thái đơn #' . $id . '.');
        redirect(url('/admin/automxh'));
    }

    /**
     * AJAX: số dư ví đối tác.
     */
    public function partnerBalance(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            redirect(url('/admin/automxh'));
        }

        $res = AutoMxhService::balance();
        if (!$res['ok']) {
            json_error((string)$res['message']);
        }

        json_success([
            'balance'  => (float)$res['balance'],
            'text'     => money((float)$res['balance']),
            'currency' => (string)$res['currency'],
            'user'     => $res['user'],
        ]);
    }
}
