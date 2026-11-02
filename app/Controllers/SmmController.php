<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\SmmService;

/**
 * Dịch vụ SMM — mua like / view / comment / share / follow (API đối tác TrungTamMMO).
 *
 * Khách hàng:
 *  - /services/smm                → danh mục dịch vụ, đặt đơn
 *  - /services/smm/order/{id}      → chi tiết đơn + tiến độ
 *  - /services/smm/quote           → AJAX tính giá
 *  - /services/smm/submit         → đặt đơn
 *  - /services/smm/apikey         → API key của tôi (dùng cho proxy đối tác)
 *
 * Quản trị:
 *  - /admin/smm                   → cấu hình, danh mục, đơn, thống kê
 *  - /admin/smm/save|sync|refresh|order/update
 */
class SmmController
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
            SmmService::configured()
            && setting('smm_auto_sync', '1') === '1'
            && SmmService::shouldSync()
        ) {
            SmmService::sync();
            SmmService::markSynced();
        }

        $platform = trim((string)($_GET['platform'] ?? ''));
        $keyword  = trim((string)($_GET['q'] ?? ''));

        view('services/smm', [
            'title'      => 'SMM — Like, View, Comment, Follow',
            'tree'       => SmmService::catalogTree($platform, $keyword),
            'platforms'  => SmmService::platforms(),
            'orders'     => SmmService::myOrders($userId, 10),
            'platform'   => $platform,
            'keyword'    => $keyword,
            'configured' => SmmService::configured(),
            'enabled'    => SmmService::enabled(),
            'hasKey'     => SmmService::userKey($userId) !== null,
            'markup'     => SmmService::markup(),
        ]);
    }

    /**
     * Chi tiết đơn (có poll trạng thái).
     */
    public function order(int $id): void
    {
        $userId = (int)Auth::id();
        $order  = SmmService::findOrder($userId, $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        if (
            in_array((string)$order['status'], [SmmService::STATUS_PENDING, SmmService::STATUS_PROCESSING], true)
            && (string)$order['remote_order'] !== ''
        ) {
            $st = SmmService::status((int)$order['remote_order']);
            if ($st['ok']) {
                SmmService::updateOrderStatus(
                    $id,
                    $st['status'],
                    (string)($st['data']['start_count'] ?? ''),
                    (string)($st['data']['remains'] ?? '')
                );
                $order = SmmService::findOrder($userId, $id);
            }
        }

        view('services/smm-order', [
            'title' => 'Đơn SMM #' . ($order['remote_order'] ?: $order['id']),
            'order' => $order,
        ]);
    }

    /**
     * AJAX: tính giá trước khi đặt.
     */
    public function quote(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_error('Yêu cầu không hợp lệ', 400);
        }

        $serviceId = (int)($_GET['service_id'] ?? 0);
        if ($serviceId <= 0) {
            json_error('Thiếu service_id', 400);
        }

        $service = SmmService::findService($serviceId);
        if (!$service) {
            json_error('Không tìm thấy dịch vụ', 404);
        }

        $isComment = (int)$service['is_comment_service'] === 1;
        $comments  = (string)($_GET['comments'] ?? '');
        $quantity  = (int)($_GET['quantity'] ?? 0);

        if ($isComment) {
            $quantity = SmmService::countCommentLines($comments);
        }
        if ($quantity <= 0) {
            json_error($isComment
                ? 'Vui lòng nhập ít nhất 1 dòng comment.'
                : 'Vui lòng nhập số lượng.', 400);
        }

        $min = (int)$service['min'];
        $max = (int)$service['max'];
        if ($min > 0 && $quantity < $min) {
            json_error("Số lượng không hợp lệ. Min $min - Max $max", 400);
        }
        if ($max > 0 && $quantity > $max) {
            json_error("Số lượng không hợp lệ. Min $min - Max $max", 400);
        }

        $cost    = (float)$service['price_per_1k_vnd'];
        $sell1k  = SmmService::sellPricePer1k($cost);
        $total   = SmmService::calcTotal($sell1k, $quantity);
        $upTotal = SmmService::calcTotal($cost, $quantity);

        json_success([
            'service_id'    => (int)$service['service_id'],
            'name'          => (string)$service['name'],
            'quantity'      => $quantity,
            'is_comment'    => $isComment,
            'min'           => $min,
            'max'           => $max,
            'price_per_1k'  => $sell1k,
            'cost_per_1k'   => $cost,
            'price_per_unit'=> SmmService::pricePerUnit($sell1k),
            'total'         => $total,
            'totalText'     => money($total),
            'cost_total'    => $upTotal,
            'formula'       => 'ceil(quantity / 1000 * price_per_1k_vnd)',
            'vat'           => 0,
        ]);
    }

    /**
     * Đặt đơn SMM.
     *
     * Tiền trừ theo công thức ceil(quantity / 1000 * price_per_1k_vnd) — không VAT.
     * Dịch vụ comment tự tính quantity theo số dòng comment hợp lệ.
     * Nếu API đối tác lỗi → hoàn tiền ngay.
     */
    public function submit(): void
    {
        CSRF::verifyOrFail();

        if (!SmmService::enabled()) {
            flash('error', 'Dịch vụ SMM hiện đang tạm dừng.');
            redirect(url('/services/smm'));
        }

        $userId    = (int)Auth::id();
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $link      = trim((string)($_POST['link'] ?? ''));
        $comments  = SmmService::normalizeComments((string)($_POST['comments'] ?? ''));
        $reaction  = trim((string)($_POST['reaction'] ?? ''));

        if ($serviceId <= 0) {
            flash('error', 'Vui lòng chọn dịch vụ.');
            redirect(url('/services/smm'));
        }
        if ($link === '') {
            flash('error', 'Vui lòng nhập link cần xử lý.');
            redirect(url('/services/smm'));
        }
        if (mb_strlen($link) > 1000) {
            flash('error', 'Link quá dài (tối đa 1000 ký tự).');
            redirect(url('/services/smm'));
        }

        $service = SmmService::findService($serviceId);
        if (!$service) {
            flash('error', 'Không tìm thấy dịch vụ. Vui lòng tải lại trang.');
            redirect(url('/services/smm'));
        }

        // Dịch vụ comment: quantity = số dòng comment hợp lệ
        $isComment = (int)$service['is_comment_service'] === 1;
        if ($isComment) {
            $quantity = SmmService::countCommentLines($comments);
            if ($quantity <= 0) {
                flash('error', 'Vui lòng nhập ít nhất 1 dòng comment cho dịch vụ này.');
                redirect(url('/services/smm'));
            }
        } else {
            $quantity = (int)($_POST['quantity'] ?? 0);
            if ($quantity <= 0) {
                flash('error', 'Vui lòng nhập số lượng.');
                redirect(url('/services/smm'));
            }
        }

        $min = (int)$service['min'];
        $max = (int)$service['max'];
        if (($min > 0 && $quantity < $min) || ($max > 0 && $quantity > $max)) {
            flash('error', "Số lượng không hợp lệ. Min $min - Max $max");
            redirect(url('/services/smm'));
        }

        $cost   = (float)$service['price_per_1k_vnd'];
        $sell1k = SmmService::sellPricePer1k($cost);
        $total  = SmmService::calcTotal($sell1k, $quantity);

        $balance = Wallet::balance($userId);
        if ($balance < $total) {
            flash('error', 'Số dư ví chính không đủ. Cần ' . money($total) . ', hiện có ' . money($balance) . '.');
            redirect(url('/services/smm'));
        }

        // Idempotency: khách bấm lại không trừ tiền 2 lần
        $idemKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($idemKey === '') {
            $idemKey = 'smm-' . $userId . '-' . $serviceId . '-' . bin2hex(random_bytes(6));
        }

        $existing = Database::fetch(
            'SELECT id FROM smm_orders WHERE idempotency_key = ? AND user_id = ?',
            [$idemKey, $userId]
        );
        if ($existing) {
            flash('success', 'Đơn này đã được tạo trước đó, đang mở lại đơn cũ (không trừ tiền lần hai).');
            redirect(url('/services/smm/order/' . (int)$existing['id']));
        }

        // Trừ tiền ví nội bộ trước
        Wallet::apply(
            $userId,
            'adjustment',
            -$total,
            'SMM: ' . (string)$service['name'],
            'smm',
            (string)$serviceId
        );

        $res = SmmService::createOrder(
            $serviceId,
            $link,
            $isComment ? 0 : $quantity,
            $isComment ? $comments : '',
            $reaction
        );

        if (!$res['ok']) {
            // Hoàn tiền khi API đối tác lỗi
            Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền đặt đơn SMM thất bại',
                'smm_refund',
                (string)$serviceId
            );

            $orderId = SmmService::createOrderRow($userId, [
                'service_id'    => $serviceId,
                'service_name'  => (string)$service['name'],
                'category_slug' => (string)$service['category_slug'],
                'platform'      => (string)$service['platform'],
                'link'          => $link,
                'quantity'      => $quantity,
                'comments'      => $comments !== '' ? $comments : null,
                'reaction'      => $reaction,
                'price'         => $cost,
                'amount'        => $total,
                'charge'        => 0,
                'status'        => SmmService::STATUS_FAILED,
                'idempotency_key'=> $idemKey,
                'message'       => $res['message'],
            ]);

            flash('error', $res['message']);
            redirect(url('/services/smm/order/' . $orderId));
        }

        $orderId = SmmService::createOrderRow($userId, [
            'remote_order'   => (string)$res['order_id'],
            'service_id'     => $serviceId,
            'service_name'   => (string)$service['name'],
            'category_slug'  => (string)$service['category_slug'],
            'platform'       => (string)$service['platform'],
            'link'           => $link,
            'quantity'       => $quantity,
            'comments'       => $comments !== '' ? $comments : null,
            'reaction'       => $reaction,
            'price'          => $cost,
            'amount'         => $total,
            'charge'         => (float)$res['charge'],
            'status'         => (string)$res['status'],
            'idempotency_key'=> $idemKey,
            'message'        => (string)$res['message'],
            'raw'            => $res['raw'],
        ]);

        flash('success', 'Đã tạo đơn SMM thành công. Mã đơn: ' . (int)$res['order_id']);
        redirect(url('/services/smm/order/' . $orderId));
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
        $order  = SmmService::findOrder($userId, $id);
        if (!$order) {
            json_error('Không tìm thấy đơn hàng', 404);
        }

        if (
            in_array((string)$order['status'], [SmmService::STATUS_PENDING, SmmService::STATUS_PROCESSING], true)
            && (string)$order['remote_order'] !== ''
        ) {
            $st = SmmService::status((int)$order['remote_order']);
            if ($st['ok']) {
                SmmService::updateOrderStatus(
                    $id,
                    $st['status'],
                    (string)($st['data']['start_count'] ?? ''),
                    (string)($st['data']['remains'] ?? '')
                );
                $order = SmmService::findOrder($userId, $id);
            }
        }

        json_success([
            'status'     => (string)$order['status'],
            'statusText' => SmmService::statusLabel((string)$order['status']),
            'badge'      => SmmService::statusBadge((string)$order['status']),
            'start_count'=> (string)$order['start_count'],
            'remains'    => (string)$order['remains'],
            'finished'   => !in_array((string)$order['status'], [SmmService::STATUS_PENDING, SmmService::STATUS_PROCESSING], true),
        ]);
    }

    // ================= API key của khách =================

    /**
     * Trang quản lý API key của khách.
     */
    public function apikey(): void
    {
        $userId = (int)Auth::id();

        view('services/smm-apikey', [
            'title'   => 'API key SMM của tôi',
            'keys'    => SmmService::userKeys($userId),
            'primary' => SmmService::userKey($userId),
        ]);
    }

    /**
     * Tạo API key mới cho khách.
     */
    public function saveApikey(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $label  = trim((string)($_POST['label'] ?? ''));
        $apikey = trim((string)($_POST['apikey'] ?? ''));

        // Cho phép dán key sẵn có, nếu không thì tự sinh
        if ($apikey === '') {
            $apikey = 'ttmmo_apikey_' . bin2hex(random_bytes(16));
        }
        if (mb_strlen($apikey) > 180) {
            flash('error', 'API key quá dài.');
            redirect(url('/services/smm/apikey'));
        }

        SmmService::saveUserKey($userId, $apikey, $label);

        flash('success', 'Đã lưu API key. Dùng key này để đối tác gọi API SMM thay bạn.');
        redirect(url('/services/smm/apikey'));
    }

    public function deleteApikey(): void
    {
        CSRF::verifyOrFail();

        $ok = SmmService::deleteUserKey((int)Auth::id(), (int)($_POST['key_id'] ?? 0));

        flash($ok ? 'success' : 'error', $ok ? 'Đã xoá API key.' : 'Không tìm thấy API key.');
        redirect(url('/services/smm/apikey'));
    }

    public function setPrimaryApikey(): void
    {
        CSRF::verifyOrFail();

        $ok = SmmService::setPrimaryKey((int)Auth::id(), (int)($_POST['key_id'] ?? 0));

        flash($ok ? 'success' : 'error', $ok ? 'Đã đặt API key làm key chính.' : 'Không tìm thấy API key.');
        redirect(url('/services/smm/apikey'));
    }

    // ================= Quản trị =================

    /**
     * Trang quản trị.
     */
    public function admin(): void
    {
        view('admin/smm', [
            'title'      => 'Dịch vụ SMM (like, view, comment)',
            'orders'     => SmmService::allOrders(100),
            'pending'    => SmmService::pendingOrders(50),
            'stats'      => SmmService::stats(),
            'balance'    => SmmService::balance(),
            'categories' => SmmService::localCategories(),
            'services'   => SmmService::localServices(),
            'platforms'  => SmmService::platforms(),
            'baseUrl'    => SmmService::baseUrl(),
            'hasKey'     => SmmService::configured(),
            'keys'       => SmmService::allKeys(200),
        ], 'layouts/admin');
    }

    /**
     * Admin đặt % giảm đại lý cho 1 apikey.
     */
    public function adminKeyDiscount(): void
    {
        CSRF::verifyOrFail();

        $keyId = (int)($_POST['key_id'] ?? 0);
        $pct   = (float)($_POST['discount'] ?? 0);

        if ($keyId <= 0) {
            flash('error', 'Thiếu API key cần cập nhật.');
            redirect(url('/admin/smm'));
            return;
        }

        SmmService::setKeyDiscount($keyId, $pct);

        flash('success', 'Đã cập nhật giảm giá đại lý cho API key.');
        redirect(url('/admin/smm'));
    }

    /**
     * Lưu cấu hình.
     */
    public function adminSave(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !self::isSafeBaseUrl($baseUrl)) {
            flash('error', 'Base URL phải dùng HTTPS (hoặc http://localhost khi phát triển). Cấu hình khác vẫn đã được lưu.');
        } elseif ($baseUrl !== '') {
            setting_set('smm_base_url', rtrim($baseUrl, '/'), 'SMM');
        }

        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($key !== '') {
            SmmService::saveApiKey($key);
        }

        setting_set('smm_service_on', isset($_POST['service_on']) ? '1' : '0', 'SMM');
        setting_set('smm_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'SMM');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('smm_markup', (string)(int)$markup, 'SMM');

        flash('success', 'Đã lưu cấu hình dịch vụ SMM.');
        redirect(url('/admin/smm'));
    }

    /**
     * Base URL an toàn: bắt buộc HTTPS, cho phép http://localhost/127.0.0.1 khi phát triển.
     */
    private static function isSafeBaseUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Đồng bộ danh mục.
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = SmmService::sync();
        if ($res['ok']) {
            SmmService::markSynced();
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/smm'));
    }

    /**
     * Làm mới trạng thái đơn (batch tối đa 100 mã/lần).
     */
    public function adminRefresh(): void
    {
        CSRF::verifyOrFail();

        $res = SmmService::refreshStatuses(100);

        flash('success', 'Đã kiểm tra ' . (int)$res['checked'] . ' đơn, cập nhật ' . (int)$res['updated'] . ' đơn.');
        redirect(url('/admin/smm'));
    }

    /**
     * Đổi trạng thái đơn thủ công.
     */
    public function adminUpdateOrder(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['order_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));

        $allowed = [
            SmmService::STATUS_PENDING,
            SmmService::STATUS_PROCESSING,
            SmmService::STATUS_COMPLETED,
            SmmService::STATUS_FAILED,
        ];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Trạng thái không hợp lệ.');
            redirect(url('/admin/smm'));
        }

        $order = Database::fetch('SELECT * FROM smm_orders WHERE id = ?', [$id]);
        if (!$order) {
            flash('error', 'Không tìm thấy đơn.');
            redirect(url('/admin/smm'));
        }

        SmmService::updateOrderStatus(
            $id,
            $status,
            (string)($_POST['start_count'] ?? $order['start_count'] ?? ''),
            (string)($_POST['remains'] ?? $order['remains'] ?? '')
        );

        flash('success', 'Đã cập nhật trạng thái đơn #' . $id . '.');
        redirect(url('/admin/smm'));
    }

    /**
     * AJAX: số dư ví đối tác.
     */
    public function partnerBalance(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            redirect(url('/admin/smm'));
        }

        $res = SmmService::balance();
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
