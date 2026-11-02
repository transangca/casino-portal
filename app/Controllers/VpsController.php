<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\XvpsService;

/**
 * Dịch vụ VPS VMware — tích hợp XVPS Agency API (https://api.xvps.vn).
 *
 * Khách hàng:
 *  - /services/vps                    → danh mục gói, chọn OS, đặt đơn
 *  - /services/vps/order/{id}          → chi tiết đơn
 *  - /services/vps/manage              → danh sách VPS của tôi
 *  - /services/vps/manage/{vpsId}      → bảng điều khiển VPS
 *  - /services/vps/quote               → AJAX tính giá
 *  - /services/vps/submit              → đặt đơn (trừ ví chính)
 *  - /services/vps/action              → start / stop / restart / rebuild / renew / addon
 *  - /services/vps/sync                → đồng bộ danh sách VPS từ API
 *
 * Quản trị:
 *  - /admin/vps                        → cấu hình API, danh mục, đơn, fleet
 *  - /admin/vps/save|sync|balance|instance/action
 */
class VpsController
{
    // ================= Khách hàng =================

    /**
     * Trang danh mục + đặt VPS.
     */
    public function index(): void
    {
        $userId = (int)Auth::id();

        // Đồng bộ danh mục tự động (cache 10 phút)
        if (
            XvpsService::configured()
            && setting('xvps_auto_sync', '1') === '1'
            && self::shouldSync()
        ) {
            XvpsService::sync();
            self::markSynced();
        }

        $cycle = trim((string)($_GET['cycle'] ?? 'monthly'));
        if (!isset(XvpsService::CYCLES[$cycle])) {
            $cycle = 'monthly';
        }

        view('services/vps', [
            'title'       => 'VPS VMware — Thuê máy chủ ảo',
            'groups'      => XvpsService::productGroups(),
            'osList'      => XvpsService::localOperatingSystems(),
            'orders'      => XvpsService::userOrders($userId, 10),
            'instances'   => XvpsService::userInstances($userId),
            'cycle'       => $cycle,
            'cycles'      => XvpsService::CYCLES,
            'configured'  => XvpsService::configured(),
            'enabled'     => XvpsService::enabled(),
            'markup'      => XvpsService::markup(),
            'balance'     => Wallet::balance($userId),
        ]);
    }

    /**
     * Chi tiết đơn.
     */
    public function order(int $id): void
    {
        $userId = (int)Auth::id();
        $order  = XvpsService::findOrder($userId, $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        view('services/vps-order', [
            'title' => 'Đơn VPS #' . ($order['remote_order'] ?: $order['id']),
            'order' => $order,
        ]);
    }

    /**
     * Danh sách VPS của tôi.
     */
    public function manage(): void
    {
        $userId = (int)Auth::id();

        view('services/vps-manage', [
            'title'     => 'VPS của tôi',
            'instances' => XvpsService::userInstances($userId),
            'orders'    => XvpsService::userOrders($userId, 20),
        ]);
    }

    /**
     * Bảng điều khiển 1 VPS.
     */
    public function control(string $vpsId): void
    {
        $userId   = (int)Auth::id();
        $instance = XvpsService::findInstance($userId, $vpsId);
        if (!$instance) {
            abort(404, 'Không tìm thấy VPS');
        }

        // Làm mới thông tin (IP, credentials, trạng thái) từ API
        $refreshed = false;
        if (XvpsService::configured()) {
            $info = XvpsService::infoVps([$vpsId]);
            if ($info['ok'] && !empty($info['data'])) {
                XvpsService::upsertInstance($userId, $info['data'][0]);
                $instance = XvpsService::findInstance($userId, $vpsId) ?? $instance;
                $refreshed = true;
            }
        }

        view('services/vps-detail', [
            'title'      => 'VPS ' . $vpsId,
            'instance'   => $instance,
            'osList'     => XvpsService::localOperatingSystems(),
            'cycles'     => XvpsService::CYCLES,
            'refreshed'  => $refreshed,
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

        $productId = trim((string)($_GET['product_id'] ?? ''));
        $cycle     = trim((string)($_GET['cycle'] ?? 'monthly'));
        $qty       = max(1, (int)($_GET['quantity'] ?? 1));
        $addonCpu  = max(0, (int)($_GET['addon_cpu'] ?? 0));
        $addonRam  = max(0, (int)($_GET['addon_ram'] ?? 0));
        $addonDisk = max(0, (int)($_GET['addon_disk'] ?? 0));

        if ($productId === '') {
            json_error('Thiếu product_id', 400);
        }
        if (!isset(XvpsService::CYCLES[$cycle])) {
            json_error('Chu kỳ thanh toán không hợp lệ', 400);
        }

        $product = XvpsService::findProduct($productId);
        if (!$product || (int)$product['is_active'] !== 1) {
            json_error('Không tìm thấy gói VPS', 404);
        }

        $calc = XvpsService::calcTotal($product, $cycle, $qty, $addonCpu, $addonRam, $addonDisk);

        json_success([
            'item'     => $calc['item'],
            'subtotal' => $calc['subtotal'],
            'vat'      => $calc['vat'],
            'total'    => $calc['total'],
            'cycle'    => $cycle,
            'quantity' => $qty,
            'formatted' => [
                'item'     => money($calc['item']),
                'subtotal' => money($calc['subtotal']),
                'vat'      => money($calc['vat']),
                'total'    => money($calc['total']),
            ],
        ]);
    }

    /**
     * Đặt đơn VPS — trừ ví chính rồi gọi API đối tác.
     */
    public function submit(): void
    {
        CSRF::verifyOrFail();

        if (!XvpsService::enabled()) {
            flash('error', 'Dịch vụ VPS hiện đang tạm dừng.');
            redirect(url('/services/vps'));
            return;
        }
        if (!XvpsService::configured()) {
            flash('error', 'Dịch vụ VPS chưa được cấu hình. Vui lòng liên hệ quản trị viên.');
            redirect(url('/services/vps'));
            return;
        }

        $userId    = (int)Auth::id();
        $productId = trim((string)($_POST['product_id'] ?? ''));
        $cycle     = trim((string)($_POST['cycle'] ?? 'monthly'));
        $osId      = trim((string)($_POST['os_id'] ?? ''));
        $qty       = max(1, min(10, (int)($_POST['quantity'] ?? 1)));
        $addonCpu  = max(0, (int)($_POST['addon_cpu'] ?? 0));
        $addonRam  = max(0, (int)($_POST['addon_ram'] ?? 0));
        $addonDisk = max(0, (int)($_POST['addon_disk'] ?? 0));
        $idemKey   = trim((string)($_POST['idempotency_key'] ?? ''));

        if ($productId === '' || $osId === '') {
            flash('error', 'Vui lòng chọn gói VPS và hệ điều hành.');
            redirect(url('/services/vps'));
            return;
        }
        if (!isset(XvpsService::CYCLES[$cycle])) {
            flash('error', 'Chu kỳ thanh toán không hợp lệ.');
            redirect(url('/services/vps'));
            return;
        }

        $product = XvpsService::findProduct($productId);
        if (!$product || (int)$product['is_active'] !== 1) {
            flash('error', 'Gói VPS không còn khả dụng.');
            redirect(url('/services/vps'));
            return;
        }

        $os = XvpsService::findOperatingSystem($osId);
        if (!$os) {
            flash('error', 'Hệ điều hành không hợp lệ.');
            redirect(url('/services/vps'));
            return;
        }

        $calc = XvpsService::calcTotal($product, $cycle, $qty, $addonCpu, $addonRam, $addonDisk);
        $total = (float)$calc['total'];

        if ($total <= 0) {
            flash('error', 'Không xác định được giá của gói VPS này.');
            redirect(url('/services/vps'));
            return;
        }

        // Kiểm tra số dư trước
        if (Wallet::balance($userId) < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money(Wallet::balance($userId)) . '.');
            redirect(url('/services/vps'));
            return;
        }

        // Idempotency: chống tạo đơn trùng khi bấm nút 2 lần
        if ($idemKey !== '') {
            $dup = Database::fetch(
                'SELECT id FROM vps_orders WHERE idempotency_key = ? AND user_id = ?',
                [$idemKey, $userId]
            );
            if ($dup) {
                redirect(url('/services/vps/order/' . (int)$dup['id']));
                return;
            }
        }

        // Tạo đơn ở trạng thái pending (trước khi trừ tiền)
        $orderId = XvpsService::createOrderRow($userId, [
            'product_id'      => $productId,
            'product_name'    => (string)$product['name'],
            'billing_cycle'   => $cycle,
            'os_id'           => $osId,
            'os_name'         => (string)$os['os_name'],
            'quantity'        => $qty,
            'addon_cpu'       => $addonCpu,
            'addon_ram'       => $addonRam,
            'addon_disk'      => $addonDisk,
            'price'           => (float)$calc['item'],
            'amount'          => $total,
            'status'          => XvpsService::STATUS_PENDING,
            'idempotency_key' => $idemKey !== '' ? $idemKey : null,
        ]);

        // Trừ ví chính
        try {
            Wallet::apply(
                $userId,
                'vps_order',
                -$total,
                'Đặt dịch vụ VPS — ' . (string)$product['name'],
                'vps_order',
                (string)$orderId,
                ['product_id' => $productId, 'cycle' => $cycle, 'quantity' => $qty]
            );
        } catch (\Throwable $e) {
            Database::update('vps_orders', [
                'status'  => XvpsService::STATUS_FAILED,
                'message' => $e->getMessage(),
                'updated_at' => now(),
            ], 'id = :id', ['id' => $orderId]);

            flash('error', $e->getMessage());
            redirect(url('/services/vps'));
            return;
        }

        // Gọi API đối tác
        $res = XvpsService::createOrder($productId, $cycle, $osId, $qty, $addonCpu, $addonRam, $addonDisk);

        if (!$res['ok']) {
            // Hoàn tiền khi tạo đơn thất bại
            Wallet::apply(
                $userId,
                'vps_refund',
                $total,
                'Hoàn tiền do tạo đơn VPS thất bại',
                'vps_order',
                (string)$orderId,
                ['reason' => $res['message']]
            );

            Database::update('vps_orders', [
                'status'      => XvpsService::STATUS_FAILED,
                'message'     => $res['message'],
                'updated_at'  => now(),
            ], 'id = :id', ['id' => $orderId]);

            flash('error', 'Đặt VPS thất bại: ' . $res['message']);
            redirect(url('/services/vps/order/' . $orderId));
            return;
        }

        // Lưu kết quả
        $first = $res['data'][0] ?? [];
        $remoteOrder = (string)($first['vps-id'] ?? '');

        try {
            Database::update('vps_orders', [
                'remote_order'  => $remoteOrder !== '' ? $remoteOrder : null,
                'vps_id'        => $remoteOrder !== '' ? $remoteOrder : null,
                'ip'            => (string)($first['ip'] ?? ''),
                'username'      => (string)($first['username'] ?? ''),
                'password'      => (string)($first['password'] ?? ''),
                'vps_status'    => (string)($first['vps-status'] ?? ''),
                'date_create'   => (string)($first['date_create'] ?? ''),
                'next_due_date' => (string)($first['next_due_date'] ?? ''),
                'status'        => XvpsService::STATUS_PROCESSING,
                'message'       => (string)$res['message'],
                'raw'           => json_encode($res['raw'], JSON_UNESCAPED_UNICODE),
                'updated_at'    => now(),
            ], 'id = :id', ['id' => $orderId]);
        } catch (\Throwable $e) {
            // remote_order có UNIQUE — đối tác trả về mã trùng (đơn đã ghi nhận trước đó)
            Database::update('vps_orders', [
                'status'     => XvpsService::STATUS_PROCESSING,
                'message'    => (string)$res['message'],
                'updated_at' => now(),
            ], 'id = :id', ['id' => $orderId]);
        }

        // Ghi nhận VPS vào danh sách quản lý
        if ($remoteOrder !== '') {
            try {
                XvpsService::upsertInstance($userId, array_merge($first, ['vps-id' => $remoteOrder]));
            } catch (\Throwable $e) {
                // VPS đã tồn tại trong hệ thống (đồng bộ trùng) — bỏ qua, không rollback đơn
            }
        }

        flash('success', 'Đặt VPS thành công! Hệ thống đang khởi tạo, bạn có thể theo dõi tại đơn hàng.');
        redirect(url('/services/vps/order/' . $orderId));
    }

    /**
     * Thực hiện hành động trên VPS.
     */
    public function action(): void
    {
        CSRF::verifyOrFail();

        $userId   = (int)Auth::id();
        $vpsId    = trim((string)($_POST['vps_id'] ?? ''));
        $action   = trim((string)($_POST['action'] ?? ''));

        if ($vpsId === '') {
            flash('error', 'Thiếu VPS cần thao tác.');
            redirect(url('/services/vps/manage'));
            return;
        }

        $instance = XvpsService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps/manage'));
            return;
        }

        if (!isset(XvpsService::ACTIONS[$action])) {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $extra = [];

        switch ($action) {
            case 'confirm-rebuild-vps':
                $osId = trim((string)($_POST['os_id'] ?? ''));
                if ($osId === '') {
                    flash('error', 'Vui lòng chọn hệ điều hành để cài lại.');
                    redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                    return;
                }
                $extra['os-id'] = (int)$osId;
                break;

            case 'renew-vps':
                $cycle = trim((string)($_POST['cycle'] ?? 'monthly'));
                if (!isset(XvpsService::CYCLES[$cycle])) {
                    $cycle = 'monthly';
                }
                $extra['billing-cycle'] = $cycle;

                // Gia hạn: trừ ví chính theo giá gói hiện tại
                $product = XvpsService::findProduct((string)$instance['product_id']);
                if ($product) {
                    $calc = XvpsService::calcTotal($product, $cycle, 1);
                    $total = (float)$calc['total'];
                    if ($total > 0) {
                        if (Wallet::balance($userId) < $total) {
                            flash('error', 'Số dư không đủ để gia hạn. Cần ' . money($total) . '.');
                            redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                            return;
                        }
                        try {
                            Wallet::apply(
                                $userId,
                                'vps_renew',
                                -$total,
                                'Gia hạn VPS ' . $vpsId,
                                'vps_renew',
                                $vpsId,
                                ['cycle' => $cycle, 'product_id' => (string)$instance['product_id']]
                            );
                        } catch (\Throwable $e) {
                            flash('error', $e->getMessage());
                            redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                            return;
                        }

                        $res = XvpsService::actionVps($action, $vpsId, $extra);
                        if (!$res['ok']) {
                            Wallet::apply(
                                $userId,
                                'vps_refund',
                                $total,
                                'Hoàn tiền do gia hạn VPS thất bại',
                                'vps_renew',
                                $vpsId,
                                ['reason' => $res['message']]
                            );
                            flash('error', 'Gia hạn thất bại: ' . $res['message']);
                            redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                            return;
                        }

                        flash('success', 'Gia hạn VPS thành công. Đã trừ ' . money($total) . '.');
                        redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                        return;
                    }
                }
                break;

            case 'addon-vps':
                $cpu  = max(0, (int)($_POST['cpu'] ?? 0));
                $ram  = max(0, (int)($_POST['ram'] ?? 0));
                $disk = max(0, (int)($_POST['disk'] ?? 0));
                if ($cpu === 0 && $ram === 0 && $disk === 0) {
                    flash('error', 'Vui lòng chọn ít nhất một tài nguyên cần nâng cấp.');
                    redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
                    return;
                }
                $extra = ['cpu' => $cpu, 'ram' => $ram, 'disk' => $disk];
                break;
        }

        $res = XvpsService::actionVps($action, $vpsId, $extra);
        if (!$res['ok']) {
            flash('error', 'Thao tác thất bại: ' . $res['message']);
            redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
            return;
        }

        // Cập nhật trạng thái cục bộ
        $statusMap = ['start' => 'on', 'stop' => 'off', 'restart' => 'on'];
        if (isset($statusMap[$action])) {
            Database::update('vps_instances', [
                'vps_status' => $statusMap[$action],
                'updated_at' => now(),
            ], 'id = :id', ['id' => (int)$instance['id']]);
        }

        flash('success', $res['message'] !== '' ? $res['message'] : 'Thao tác thành công.');
        redirect(url('/services/vps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Đồng bộ danh sách VPS từ API.
     */
    public function sync(): void
    {
        $userId = (int)Auth::id();
        $res = XvpsService::syncVps($userId);

        if (is_ajax()) {
            json_success([
                'count'   => $res['count'],
                'message' => $res['message'],
            ]);
            return;
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/vps/manage'));
    }

    // ================= Quản trị =================

    /**
     * Trang quản trị dịch vụ VPS.
     */
    public function admin(): void
    {
        $info = ['ok' => false, 'balance' => 0.0, 'user' => ['agency_name' => ''], 'message' => ''];
        if (XvpsService::configured()) {
            $info = XvpsService::info();
        }

        view('admin/vps', [
            'title'     => 'Dịch vụ VPS (XVPS Agency API)',
            'stats'     => XvpsService::stats(),
            'products'  => XvpsService::localProducts(true),
            'osList'    => XvpsService::localOperatingSystems(),
            'orders'    => XvpsService::allOrders(100),
            'instances' => XvpsService::allInstances(200),
            'partner'   => $info,
            'baseUrl'   => XvpsService::baseUrl(),
            'hasCreds'  => XvpsService::configured(),
            'username'  => XvpsService::apiUsername(),
            'app'       => XvpsService::apiApp(),
            'hasSecret' => XvpsService::apiSecret() !== '',
            'markup'    => XvpsService::markup(),
            'enabled'   => XvpsService::enabled(),
        ], 'layouts/admin');
    }

    /**
     * Lưu cấu hình API.
     */
    public function adminSave(): void
    {
        CSRF::verifyOrFail();

        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($baseUrl !== '' && !self::isSafeBaseUrl($baseUrl)) {
            flash('error', 'Base URL phải dùng HTTPS. Cấu hình khác vẫn đã được lưu.');
        } elseif ($baseUrl !== '') {
            setting_set('xvps_base_url', rtrim($baseUrl, '/'), 'XVPS');
        }

        $username = trim((string)($_POST['api_username'] ?? ''));
        $app      = trim((string)($_POST['api_app'] ?? ''));
        $secret   = trim((string)($_POST['api_secret'] ?? ''));

        if ($username !== '' || $app !== '' || $secret !== '') {
            $curUser  = XvpsService::apiUsername();
            $curApp   = XvpsService::apiApp();
            $curSec   = XvpsService::apiSecret();
            XvpsService::saveCredentials(
                $username !== '' ? $username : $curUser,
                $app      !== '' ? $app      : $curApp,
                $secret   !== '' ? $secret   : $curSec
            );
            XvpsService::clearToken();
        }

        setting_set('xvps_service_on', isset($_POST['service_on']) ? '1' : '0', 'XVPS');
        setting_set('xvps_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'XVPS');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('xvps_markup', (string)(int)$markup, 'XVPS');

        flash('success', 'Đã lưu cấu hình dịch vụ VPS.');
        redirect(url('/admin/vps'));
    }

    /**
     * Đồng bộ danh mục gói + OS.
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = XvpsService::sync();
        if ($res['ok']) {
            self::markSynced();
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/vps'));
    }

    /**
     * Xem số dư tài khoản đối tác.
     */
    public function adminBalance(): void
    {
        $info = XvpsService::info();

        if (isset($_GET['json']) || is_ajax()) {
            json_success($info);
            return;
        }

        flash($info['ok'] ? 'success' : 'error', $info['ok']
            ? 'Số dư đối tác: ' . money($info['balance']) . ($info['user']['agency_name'] !== '' ? ' — ' . $info['user']['agency_name'] : '')
            : $info['message']);
        redirect(url('/admin/vps'));
    }

    /**
     * Admin thực hiện hành động trên VPS của khách.
     */
    public function adminInstanceAction(): void
    {
        CSRF::verifyOrFail();

        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));
        $action = trim((string)($_POST['action'] ?? ''));

        if ($vpsId === '' || !isset(XvpsService::ACTIONS[$action])) {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/admin/vps'));
            return;
        }

        $extra = [];
        if ($action === 'confirm-rebuild-vps') {
            $extra['os-id'] = (int)($_POST['os_id'] ?? 0);
        } elseif ($action === 'renew-vps') {
            $extra['billing-cycle'] = (string)($_POST['cycle'] ?? 'monthly');
        } elseif ($action === 'addon-vps') {
            $extra = [
                'cpu'  => max(0, (int)($_POST['cpu'] ?? 0)),
                'ram'  => max(0, (int)($_POST['ram'] ?? 0)),
                'disk' => max(0, (int)($_POST['disk'] ?? 0)),
            ];
        }

        $res = XvpsService::actionVps($action, $vpsId, $extra);

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/vps'));
    }

    // ================= Helper =================

    /**
     * Base URL an toàn (HTTPS, hoặc HTTP localhost khi phát triển).
     */
    private static function isSafeBaseUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string)parse_url($url, PHP_URL_HOST));

        if ($scheme === 'https') {
            return true;
        }

        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $env = strtolower((string)config('app.env', 'production'));
            return $env !== 'production';
        }

        return false;
    }

    /**
     * Đồng bộ danh mục tối đa 1 lần / 10 phút.
     */
    private static function shouldSync(): bool
    {
        $last = (int)setting('xvps_last_sync', '0');
        return (time() - $last) > 600;
    }

    private static function markSynced(): void
    {
        setting_set('xvps_last_sync', (string)time(), 'XVPS');
    }
}
