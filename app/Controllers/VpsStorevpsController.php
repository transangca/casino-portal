<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\VirtualizorService;

/**
 * Dịch vụ VPS StoreVPS / Virtualizor — tích hợp XVPS API (https://api.xvps.vn/api/virt/*).
 *
 * Khách hàng:
 *  - /services/vps-storevps                  → danh mục gói, chọn OS/location, đặt đơn
 *  - /services/vps-storevps/order/{id}        → chi tiết đơn
 *  - /services/vps-storevps/manage            → danh sách VPS của tôi
 *  - /services/vps-storevps/manage/{vpsId}    → bảng điều khiển VPS
 *  - /services/vps-storevps/quote             → AJAX tính giá
 *  - /services/vps-storevps/submit            → đặt đơn (trừ ví chính)
 *  - /services/vps-storevps/action            → start/stop/restart/poweroff
 *  - /services/vps-storevps/rebuild           → cài lại OS
 *  - /services/vps-storevps/password          → đổi root password
 *  - /services/vps-storevps/hostname          → đổi hostname
 *  - /services/vps-storevps/renew             → gia hạn (trừ ví chính)
 *  - /services/vps-storevps/addon             → nâng cấp addon
 *  - /services/vps-storevps/auto-renew        → bật/tắt tự động gia hạn
 *  - /services/vps-storevps/cancel            → hủy dịch vụ
 *  - /services/vps-storevps/status            → lấy trạng thái nhiều VPS (batch)
 *  - /services/vps-storevps/sync              → đồng bộ danh sách VPS từ API
 *
 * Quản trị:
 *  - /admin/vps-storevps                          → cấu hình API, danh mục, đơn, fleet
 *  - /admin/vps-storevps/save|sync|balance        → lưu / đồng bộ / xem số dư
 *  - /admin/vps-storevps/instance/action           → thao tác trên VPS của khách
 */
class VpsStorevpsController
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
            VirtualizorService::configured()
            && setting('vps_vz_auto_sync', '1') === '1'
            && self::shouldSync()
        ) {
            VirtualizorService::sync();
            self::markSynced();
        }

        $cycle = trim((string)($_GET['cycle'] ?? 'monthly'));
        if (!isset(VirtualizorService::CYCLES[$cycle])) {
            $cycle = 'monthly';
        }

        view('services/vps-storevps', [
            'title'      => 'VPS StoreVPS (Virtualizor)',
            'groups'     => VirtualizorService::productGroups(),
            'osList'     => VirtualizorService::localOperatingSystems(),
            'locations'  => VirtualizorService::localLocations(),
            'addons'     => VirtualizorService::localAddons(),
            'orders'     => VirtualizorService::userOrders($userId, 10),
            'instances'  => VirtualizorService::userInstances($userId),
            'cycle'      => $cycle,
            'cycles'     => VirtualizorService::CYCLES,
            'configured' => VirtualizorService::configured(),
            'enabled'    => VirtualizorService::enabled(),
            'markup'     => VirtualizorService::markup(),
            'balance'    => Wallet::balance($userId),
        ]);
    }

    /**
     * Chi tiết đơn.
     */
    public function order(int $id): void
    {
        $userId = (int)Auth::id();
        $order  = VirtualizorService::findOrder($userId, $id);
        if (!$order) {
            abort(404, 'Không tìm thấy đơn hàng');
        }

        view('services/vps-storevps-order', [
            'title' => 'Đơn VPS #' . ($order['order_code'] ?: $order['id']),
            'order' => $order,
        ]);
    }

    /**
     * Danh sách VPS của tôi.
     */
    public function manage(): void
    {
        $userId = (int)Auth::id();

        view('services/vps-storevps-manage', [
            'title'     => 'VPS StoreVPS của tôi',
            'instances' => VirtualizorService::userInstances($userId),
            'orders'    => VirtualizorService::userOrders($userId, 20),
        ]);
    }

    /**
     * Bảng điều khiển 1 VPS.
     */
    public function control(string $vpsId): void
    {
        $userId   = (int)Auth::id();
        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            abort(404, 'Không tìm thấy VPS');
        }

        // Làm mới thông tin (IP, credentials, trạng thái) từ API
        $refreshed = false;
        if (VirtualizorService::configured()) {
            $info = VirtualizorService::infoVps($vpsId);
            if ($info['ok'] && !empty($info['data'])) {
                VirtualizorService::upsertInstance($userId, $info['data']);
                $instance = VirtualizorService::findInstance($userId, $vpsId) ?? $instance;
                $refreshed = true;
            }
        }

        view('services/vps-storevps-detail', [
            'title'      => 'VPS ' . $vpsId,
            'instance'   => $instance,
            'osList'     => VirtualizorService::localOperatingSystems(),
            'cycles'     => VirtualizorService::CYCLES,
            'addons'     => VirtualizorService::localAddons(),
            'history'    => VirtualizorService::actionHistory($vpsId),
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

        $productId  = trim((string)($_GET['product_id'] ?? ''));
        $cycle      = trim((string)($_GET['cycle'] ?? 'monthly'));
        $qty        = max(1, (int)($_GET['quantity'] ?? 1));
        $locationId = trim((string)($_GET['location_id'] ?? ''));

        $addons = [];
        foreach (array_keys(VirtualizorService::ADDON_TYPES) as $type) {
            $addons[$type] = max(0, (int)($_GET['addon_' . $type] ?? 0));
        }

        if ($productId === '') {
            json_error('Thiếu product_id', 400);
        }
        if (!isset(VirtualizorService::CYCLES[$cycle])) {
            json_error('Chu kỳ thanh toán không hợp lệ', 400);
        }

        $product = VirtualizorService::findProduct($productId);
        if (!$product || (int)$product['is_active'] !== 1) {
            json_error('Không tìm thấy gói VPS', 404);
        }

        $surcharge = 0.0;
        $locationName = '';
        if ($locationId !== '') {
            $loc = VirtualizorService::findLocation($locationId);
            if ($loc) {
                $surcharge   = (float)$loc['surcharge'];
                $locationName = (string)$loc['name'];
            }
        }

        $calc = VirtualizorService::calcTotal($product, $cycle, $qty, $addons, $surcharge);

        json_success([
            'item'      => $calc['item'],
            'addon'     => $calc['addon'],
            'surcharge' => $calc['surcharge'],
            'subtotal'  => $calc['subtotal'],
            'vat'       => $calc['vat'],
            'total'     => $calc['total'],
            'cycle'     => $cycle,
            'quantity'  => $qty,
            'location'  => $locationName,
            'formatted' => [
                'item'      => money($calc['item']),
                'addon'     => money($calc['addon']),
                'surcharge' => money($calc['surcharge']),
                'subtotal'  => money($calc['subtotal']),
                'vat'       => money($calc['vat']),
                'total'     => money($calc['total']),
            ],
        ]);
    }

    /**
     * Đặt đơn VPS — trừ ví chính rồi gọi API đối tác.
     */
    public function submit(): void
    {
        CSRF::verifyOrFail();

        if (!VirtualizorService::enabled()) {
            flash('error', 'Dịch vụ VPS StoreVPS hiện đang tạm dừng.');
            redirect(url('/services/vps-storevps'));
            return;
        }
        if (!VirtualizorService::configured()) {
            flash('error', 'Dịch vụ VPS StoreVPS chưa được cấu hình. Vui lòng liên hệ quản trị viên.');
            redirect(url('/services/vps-storevps'));
            return;
        }

        $userId    = (int)Auth::id();
        $productId = trim((string)($_POST['product_id'] ?? ''));
        $cycle     = trim((string)($_POST['cycle'] ?? 'monthly'));
        $osId      = trim((string)($_POST['os_id'] ?? ''));
        $hostname  = trim((string)($_POST['hostname'] ?? ''));
        $locationId = trim((string)($_POST['location_id'] ?? ''));
        $qty       = max(1, min(10, (int)($_POST['quantity'] ?? 1)));
        $idemKey   = trim((string)($_POST['idempotency_key'] ?? ''));

        if ($productId === '' || $osId === '') {
            flash('error', 'Vui lòng chọn gói VPS và hệ điều hành.');
            redirect(url('/services/vps-storevps'));
            return;
        }
        if (!isset(VirtualizorService::CYCLES[$cycle])) {
            flash('error', 'Chu kỳ thanh toán không hợp lệ.');
            redirect(url('/services/vps-storevps'));
            return;
        }
        if ($hostname === '' || !self::isValidHostname($hostname)) {
            flash('error', 'Hostname không hợp lệ (3–63 ký tự, chữ thường a-z 0-9 . _ -).');
            redirect(url('/services/vps-storevps'));
            return;
        }

        $product = VirtualizorService::findProduct($productId);
        if (!$product || (int)$product['is_active'] !== 1) {
            flash('error', 'Gói VPS không còn khả dụng.');
            redirect(url('/services/vps-storevps'));
            return;
        }

        $os = VirtualizorService::findOperatingSystem($osId);
        if (!$os) {
            flash('error', 'Hệ điều hành không hợp lệ.');
            redirect(url('/services/vps-storevps'));
            return;
        }

        // Addon: đọc số đơn vị + product_id của addon tương ứng
        $addons     = [];
        $addonIds   = [];
        $addonTable = [];
        foreach (VirtualizorService::ADDON_TYPES as $type => $label) {
            $n = max(0, (int)($_POST['addon_' . $type] ?? 0));
            if ($n <= 0) {
                continue;
            }
            $addons[$type] = $n;

            $addonRow = VirtualizorService::findAddon($type);
            if ($addonRow) {
                $addonIds[] = (string)$addonRow['product_id'];
            }
        }

        // Location
        $surcharge    = 0.0;
        $locationName = '';
        if ($locationId !== '') {
            $loc = VirtualizorService::findLocation($locationId);
            if ($loc) {
                $surcharge    = (float)$loc['surcharge'];
                $locationName = (string)$loc['name'];
            }
        }

        $calc  = VirtualizorService::calcTotal($product, $cycle, $qty, $addons, $surcharge);
        $total = (float)$calc['total'];

        if ($total <= 0) {
            flash('error', 'Không xác định được giá của gói VPS này.');
            redirect(url('/services/vps-storevps'));
            return;
        }

        // Kiểm tra số dư trước
        if (Wallet::balance($userId) < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money(Wallet::balance($userId)) . '.');
            redirect(url('/services/vps-storevps'));
            return;
        }

        // Idempotency: chống tạo đơn trùng khi bấm nút 2 lần
        if ($idemKey !== '') {
            $dup = Database::fetch(
                'SELECT id FROM vps_vz_orders WHERE idempotency_key = ? AND user_id = ?',
                [$idemKey, $userId]
            );
            if ($dup) {
                redirect(url('/services/vps-storevps/order/' . (int)$dup['id']));
                return;
            }
        }

        // Tạo đơn ở trạng thái pending (trước khi trừ tiền)
        $orderId = VirtualizorService::createOrderRow($userId, [
            'product_id'     => $productId,
            'product_name'   => (string)$product['name'],
            'billing_cycle'  => $cycle,
            'os_id'          => $osId,
            'os_name'        => (string)$os['os_name'],
            'location_id'    => $locationId !== '' ? $locationId : null,
            'location_name'  => $locationName !== '' ? $locationName : null,
            'hostname'       => $hostname,
            'quantity'       => $qty,
            'addons_json'    => $addons ? json_encode($addons, JSON_UNESCAPED_UNICODE) : null,
            'price'          => (float)$calc['item'],
            'amount'         => $total,
            'status'         => VirtualizorService::STATUS_PENDING,
            'idempotency_key'=> $idemKey !== '' ? $idemKey : null,
        ]);

        // Trừ ví chính
        try {
            Wallet::apply(
                $userId,
                'vps_vz_order',
                -$total,
                'Đặt dịch vụ VPS StoreVPS — ' . (string)$product['name'],
                'vps_vz_order',
                (string)$orderId,
                ['product_id' => $productId, 'cycle' => $cycle, 'quantity' => $qty]
            );
        } catch (\Throwable $e) {
            Database::update('vps_vz_orders', [
                'status'     => VirtualizorService::STATUS_FAILED,
                'message'    => $e->getMessage(),
                'updated_at' => now(),
            ], 'id = :id', ['id' => $orderId]);

            flash('error', $e->getMessage());
            redirect(url('/services/vps-storevps'));
            return;
        }

        // Gọi API đối tác
        $res = VirtualizorService::createOrder(
            $productId,
            $cycle,
            $osId,
            $hostname,
            $locationId,
            $qty,
            $addonIds,
            $idemKey
        );

        if (!$res['ok']) {
            // Hoàn tiền khi tạo đơn thất bại
            Wallet::apply(
                $userId,
                'vps_vz_refund',
                $total,
                'Hoàn tiền do tạo đơn VPS StoreVPS thất bại',
                'vps_vz_order',
                (string)$orderId,
                ['reason' => $res['message']]
            );

            Database::update('vps_vz_orders', [
                'status'     => VirtualizorService::STATUS_FAILED,
                'message'    => $res['message'],
                'updated_at' => now(),
            ], 'id = :id', ['id' => $orderId]);

            flash('error', 'Đặt VPS thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/order/' . $orderId));
            return;
        }

        // Lưu kết quả — API trả về data.orders[] với instance_id / order_code / status
        $orders = (array)($res['orders'] ?? []);
        $first  = $orders[0] ?? [];
        $instanceId = (string)($first['instance_id'] ?? '');
        $orderCode  = (string)($first['order_code'] ?? '');

        $update = [
            'remote_order'  => $orderCode !== '' ? $orderCode : ($instanceId !== '' ? $instanceId : null),
            'instance_id'   => $instanceId !== '' ? $instanceId : null,
            'order_code'    => $orderCode !== '' ? $orderCode : null,
            'vps_status'    => (string)($first['status'] ?? $res['status']),
            'status'        => VirtualizorService::STATUS_PROCESSING,
            'message'       => (string)$res['message'],
            'raw'           => json_encode($res['raw'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ];

        try {
            Database::update('vps_vz_orders', $update, 'id = :id', ['id' => $orderId]);
        } catch (\Throwable $e) {
            // remote_order có UNIQUE — đối tác trả về mã trùng (đơn đã ghi nhận trước đó)
            Database::update('vps_vz_orders', [
                'status'     => VirtualizorService::STATUS_PROCESSING,
                'message'    => (string)$res['message'],
                'updated_at' => now(),
            ], 'id = :id', ['id' => $orderId]);
        }

        // Ghi nhận VPS vào danh sách quản lý
        if ($instanceId !== '') {
            try {
                VirtualizorService::upsertInstance($userId, [
                    'vps-id'      => $instanceId,
                    'hostname'    => $hostname,
                    'order-code'  => $orderCode,
                    'product-id'  => $productId,
                    'description' => (string)$product['name'],
                    'vps-status'  => (string)($first['status'] ?? $res['status']),
                    'ip'          => '',
                    'password'    => '',
                    'cpu'         => (int)$product['cpu'],
                    'ram'         => (int)$product['ram'],
                    'disk'        => (int)$product['disk'],
                    'billing-cycle' => $cycle,
                    'amount'      => $total,
                ]);
            } catch (\Throwable $e) {
                // VPS đã tồn tại trong hệ thống (đồng bộ trùng) — bỏ qua, không rollback đơn
            }
        }

        flash('success', 'Đặt VPS thành công! Hệ thống đang khởi tạo, bạn có thể theo dõi tại đơn hàng.');
        redirect(url('/services/vps-storevps/order/' . $orderId));
    }

    /**
     * Thực hiện hành động nguồn trên VPS.
     */
    public function action(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));
        $action = trim((string)($_POST['action'] ?? ''));

        if ($vpsId === '') {
            flash('error', 'Thiếu VPS cần thao tác.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }

        if (!isset(VirtualizorService::POWER_ACTIONS[$action])) {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $res = VirtualizorService::actionVps($action, $vpsId);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            $action,
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );

        if (!$res['ok']) {
            flash('error', 'Thao tác thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        // Cập nhật trạng thái cục bộ
        $statusMap = ['start' => 'running', 'stop' => 'stopped', 'restart' => 'running', 'poweroff' => 'stopped'];
        if (isset($statusMap[$action])) {
            Database::update('vps_vz_instances', [
                'vps_status' => $statusMap[$action],
                'updated_at' => now(),
            ], 'id = :id', ['id' => (int)$instance['id']]);
        }

        flash('success', $res['message'] !== '' ? $res['message'] : 'Thao tác thành công.');
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Cài lại hệ điều hành.
     */
    public function rebuild(): void
    {
        CSRF::verifyOrFail();

        $userId  = (int)Auth::id();
        $vpsId   = trim((string)($_POST['vps_id'] ?? ''));
        $osId    = trim((string)($_POST['os_id'] ?? ''));
        $newPass = (string)($_POST['new_password'] ?? '');

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }
        if ($osId === '' || !VirtualizorService::findOperatingSystem($osId)) {
            flash('error', 'Vui lòng chọn hệ điều hành hợp lệ.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }
        if ($newPass !== '' && !self::isValidPassword($newPass)) {
            flash('error', 'Mật khẩu phải dài 10–64 ký tự, có ít nhất 2 trong 3 nhóm (chữ thường / chữ HOA / chữ số), không chứa khoảng trắng hoặc ký tự nguy hiểm.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $res = VirtualizorService::rebuildVps($vpsId, $osId, $newPass);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'rebuild',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['os_id' => $osId]
        );

        if (!$res['ok']) {
            flash('error', 'Cài lại OS thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        Database::update('vps_vz_instances', [
            'vps_status' => 'rebuilding',
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int)$instance['id']]);

        flash('success', $res['message']);
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Đổi root password.
     */
    public function password(): void
    {
        CSRF::verifyOrFail();

        $userId  = (int)Auth::id();
        $vpsId   = trim((string)($_POST['vps_id'] ?? ''));
        $newPass = (string)($_POST['new_password'] ?? '');
        $onBoot  = isset($_POST['on_boot']);

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }
        if (!self::isValidPassword($newPass)) {
            flash('error', 'Mật khẩu phải dài 10–64 ký tự, có ít nhất 2 trong 3 nhóm (chữ thường / chữ HOA / chữ số), không chứa khoảng trắng hoặc ký tự nguy hiểm.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $res = VirtualizorService::changePassword($vpsId, $newPass, $onBoot);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'change-password',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['on_boot' => $onBoot]
        );

        if (!$res['ok']) {
            flash('error', 'Đổi mật khẩu thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        // Lưu lại mật khẩu mới để khách xem trong bảng điều khiển
        Database::update('vps_vz_instances', [
            'password'   => $newPass,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int)$instance['id']]);

        flash('success', $res['message']);
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Đổi hostname.
     */
    public function hostname(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));
        $newHost = trim((string)($_POST['new_hostname'] ?? ''));

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }
        if (!self::isValidHostname($newHost)) {
            flash('error', 'Hostname không hợp lệ (3–63 ký tự, chữ thường a-z 0-9 . _ -).');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }
        if ($newHost === (string)$instance['hostname']) {
            flash('error', 'Hostname mới trùng với hostname hiện tại.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $res = VirtualizorService::changeHostname($vpsId, $newHost);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'change-hostname',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['new_hostname' => $newHost]
        );

        if (!$res['ok']) {
            flash('error', 'Đổi hostname thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        Database::update('vps_vz_instances', [
            'hostname'   => $newHost,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int)$instance['id']]);

        flash('success', $res['message']);
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Gia hạn — trừ ví chính rồi gọi renew-vps.
     */
    public function renew(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));
        $cycle  = trim((string)($_POST['cycle'] ?? 'monthly'));

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }
        if (!isset(VirtualizorService::CYCLES[$cycle])) {
            flash('error', 'Chu kỳ thanh toán không hợp lệ.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $product = VirtualizorService::findProduct((string)$instance['product_id']);
        if (!$product) {
            flash('error', 'Không tìm thấy cấu hình giá của gói VPS này. Vui lòng liên hệ quản trị viên.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $calc  = VirtualizorService::calcTotal($product, $cycle, 1);
        $total = (float)$calc['total'];

        if ($total <= 0) {
            flash('error', 'Không xác định được giá gia hạn của gói này.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }
        if (Wallet::balance($userId) < $total) {
            flash('error', 'Số dư không đủ để gia hạn. Cần ' . money($total) . '.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        try {
            Wallet::apply(
                $userId,
                'vps_vz_renew',
                -$total,
                'Gia hạn VPS StoreVPS ' . $vpsId,
                'vps_vz_renew',
                $vpsId,
                ['cycle' => $cycle, 'product_id' => (string)$instance['product_id']]
            );
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $res = VirtualizorService::renewVps($vpsId, $cycle);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'renew',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['cycle' => $cycle, 'amount' => $total]
        );

        if (!$res['ok']) {
            // Hoàn tiền khi gia hạn thất bại
            Wallet::apply(
                $userId,
                'vps_vz_refund',
                $total,
                'Hoàn tiền do gia hạn VPS StoreVPS thất bại',
                'vps_vz_renew',
                $vpsId,
                ['reason' => $res['message']]
            );

            flash('error', 'Gia hạn thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        // Cập nhật hạn mới
        $update = ['updated_at' => now()];
        if (!empty($res['newExpireAt'])) {
            $update['next_due_date'] = $res['newExpireAt'];
        }
        Database::update('vps_vz_instances', $update, 'id = :id', ['id' => (int)$instance['id']]);

        flash('success', 'Gia hạn VPS thành công. Đã trừ ' . money($total) . '.');
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Nâng cấp addon.
     */
    public function addon(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }

        $addons = [];
        $total  = 0;
        foreach (array_keys(VirtualizorService::ADDON_TYPES) as $type) {
            $n = max(0, min(64, (int)($_POST['addon_' . $type] ?? 0)));
            if ($n > 0) {
                $addons[$type] = $n;
                $total += $n;
            }
        }

        if ($total <= 0) {
            flash('error', 'Vui lòng chọn ít nhất một tài nguyên cần nâng cấp.');
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        $reboot = isset($_POST['reboot_after_fulfillment']);
        $note   = trim((string)($_POST['note'] ?? ''));

        $res = VirtualizorService::addonVps($vpsId, $addons, $reboot, $note);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'addon',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['addons' => $addons]
        );

        if (!$res['ok']) {
            flash('error', 'Nâng cấp thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        flash('success', $res['message'] !== '' ? $res['message'] : 'Đã tạo hóa đơn nâng cấp.');
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Bật / tắt tự động gia hạn.
     */
    public function autoRenew(): void
    {
        CSRF::verifyOrFail();

        $userId  = (int)Auth::id();
        $vpsId   = trim((string)($_POST['vps_id'] ?? ''));
        $enabled = isset($_POST['enabled']);

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }

        $res = VirtualizorService::autoRenew($vpsId, $enabled);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'auto-renew',
            $res['ok'] ? 'success' : 'error',
            $res['message'],
            ['enabled' => $enabled]
        );

        if (!$res['ok']) {
            flash('error', 'Thao tác thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        Database::update('vps_vz_instances', [
            'auto_renew' => $enabled ? 1 : 0,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int)$instance['id']]);

        flash('success', $res['message']);
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Hủy dịch vụ VPS.
     */
    public function cancel(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));

        $instance = VirtualizorService::findInstance($userId, $vpsId);
        if (!$instance) {
            flash('error', 'Không tìm thấy VPS hoặc bạn không sở hữu VPS này.');
            redirect(url('/services/vps-storevps/manage'));
            return;
        }

        $res = VirtualizorService::cancelVps($vpsId);
        VirtualizorService::logAction(
            $userId,
            $vpsId,
            'cancel',
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );

        if (!$res['ok']) {
            flash('error', 'Hủy dịch vụ thất bại: ' . $res['message']);
            redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
            return;
        }

        Database::update('vps_vz_instances', [
            'vps_status' => 'cancelled',
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int)$instance['id']]);

        $msg = $res['message'];
        if (!empty($res['suspendWarning'])) {
            $msg .= ' Lưu ý: ' . $res['suspendWarning'];
        }

        flash('success', $msg);
        redirect(url('/services/vps-storevps/manage/' . rawurlencode($vpsId)));
    }

    /**
     * Đồng bộ danh sách VPS từ API.
     */
    public function sync(): void
    {
        $userId = (int)Auth::id();
        $res    = VirtualizorService::syncVps($userId);

        if (is_ajax()) {
            json_success([
                'count'   => $res['count'],
                'message' => $res['message'],
            ]);
            return;
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/vps-storevps/manage'));
    }

    /**
     * AJAX: lấy trạng thái nhiều VPS (get-status-batch, tối đa 50).
     */
    public function status(): void
    {
        if (!is_ajax() && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_error('Yêu cầu không hợp lệ', 400);
        }

        $userId = (int)Auth::id();
        $ids    = trim((string)($_GET['ids'] ?? ''));
        if ($ids === '') {
            json_error('Thiếu danh sách ids', 400);
        }

        $all = VirtualizorService::userInstances($userId);
        $map = [];
        foreach ($all as $v) {
            $map[(string)$v['vps_id']] = $v;
        }

        $wanted = array_slice(array_filter(array_map('trim', explode(',', $ids))), 0, 50);
        $owned  = [];
        foreach ($wanted as $id) {
            if (isset($map[$id])) {
                $owned[] = $id;
            }
        }

        if (!$owned) {
            json_success(['data' => [], 'message' => 'Không có VPS nào hợp lệ.']);
            return;
        }

        $res = VirtualizorService::statusBatch($owned);
        if (!$res['ok']) {
            json_error($res['message'], 400);
            return;
        }

        // Cập nhật trạng thái cục bộ
        foreach ($res['data'] as $v) {
            $id = (string)($v['vps-id'] ?? $v['vps_id'] ?? '');
            if ($id === '' || !isset($map[$id])) {
                continue;
            }
            Database::update('vps_vz_instances', [
                'vps_status' => (string)($v['vps-status'] ?? $v['vps_status'] ?? ''),
                'updated_at' => now(),
            ], 'id = :id', ['id' => (int)$map[$id]['id']]);
        }

        json_success(['data' => $res['data']]);
    }

    // ================= Quản trị =================

    /**
     * Trang quản trị dịch vụ.
     */
    public function admin(): void
    {
        $info = ['ok' => false, 'balance' => 0.0, 'user' => ['name' => ''], 'message' => '', 'stats' => []];
        if (VirtualizorService::configured()) {
            $info = VirtualizorService::info();
        }

        view('admin/vps-storevps', [
            'title'      => 'Dịch vụ VPS StoreVPS (Virtualizor)',
            'stats'      => VirtualizorService::stats(),
            'products'   => VirtualizorService::localProducts(true),
            'osList'     => VirtualizorService::localOperatingSystems(),
            'locations'  => VirtualizorService::localLocations(),
            'orders'     => VirtualizorService::allOrders(100),
            'instances'  => VirtualizorService::allInstances(200),
            'partner'    => $info,
            'baseUrl'    => VirtualizorService::baseUrl(),
            'hasCreds'   => VirtualizorService::configured(),
            'username'   => VirtualizorService::apiUsername(),
            'app'        => VirtualizorService::apiApp(),
            'hasSecret'  => VirtualizorService::apiSecret() !== '',
            'markup'     => VirtualizorService::markup(),
            'enabled'    => VirtualizorService::enabled(),
            'sandbox'    => VirtualizorService::sandbox(),
            'addonTypes' => VirtualizorService::ADDON_TYPES,
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
            setting_set('vps_vz_base_url', rtrim($baseUrl, '/'), 'XVPS Virtualizor');
        }

        $username = trim((string)($_POST['api_username'] ?? ''));
        $app      = trim((string)($_POST['api_app'] ?? ''));
        $secret   = trim((string)($_POST['api_secret'] ?? ''));

        if ($username !== '' || $app !== '' || $secret !== '') {
            $curUser = VirtualizorService::apiUsername();
            $curApp  = VirtualizorService::apiApp();
            $curSec  = VirtualizorService::apiSecret();
            VirtualizorService::saveCredentials(
                $username !== '' ? $username : $curUser,
                $app      !== '' ? $app      : $curApp,
                $secret   !== '' ? $secret   : $curSec
            );
            VirtualizorService::clearToken();
        }

        setting_set('vps_vz_service_on', isset($_POST['service_on']) ? '1' : '0', 'XVPS Virtualizor');
        setting_set('vps_vz_auto_sync', isset($_POST['auto_sync']) ? '1' : '0', 'XVPS Virtualizor');
        setting_set('vps_vz_sandbox', isset($_POST['sandbox']) ? '1' : '0', 'XVPS Virtualizor');

        $markup = (float)($_POST['markup'] ?? 0);
        if ($markup < 0 || $markup > 500) {
            $markup = 0;
        }
        setting_set('vps_vz_markup', (string)(int)$markup, 'XVPS Virtualizor');

        flash('success', 'Đã lưu cấu hình dịch vụ VPS StoreVPS.');
        redirect(url('/admin/vps-storevps'));
    }

    /**
     * Đồng bộ danh mục gói + OS + datacenter.
     */
    public function adminSync(): void
    {
        CSRF::verifyOrFail();

        $res = VirtualizorService::sync();
        if ($res['ok']) {
            self::markSynced();
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/vps-storevps'));
    }

    /**
     * Xem số dư tài khoản đối tác.
     */
    public function adminBalance(): void
    {
        $info = VirtualizorService::info();

        if (isset($_GET['json']) || is_ajax()) {
            json_success($info);
            return;
        }

        flash($info['ok'] ? 'success' : 'error', $info['ok']
            ? 'Số dư đối tác: ' . money($info['balance']) . ($info['user']['name'] !== '' ? ' — ' . $info['user']['name'] : '')
            : $info['message']);
        redirect(url('/admin/vps-storevps'));
    }

    /**
     * Admin thực hiện hành động trên VPS của khách.
     */
    public function adminInstanceAction(): void
    {
        CSRF::verifyOrFail();

        $vpsId  = trim((string)($_POST['vps_id'] ?? ''));
        $action = trim((string)($_POST['action'] ?? ''));

        if ($vpsId === '' || !isset(VirtualizorService::POWER_ACTIONS[$action])) {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/admin/vps-storevps'));
            return;
        }

        $res = VirtualizorService::actionVps($action, $vpsId);
        VirtualizorService::logAction(null, $vpsId, 'admin-' . $action, $res['ok'] ? 'success' : 'error', $res['message']);

        if ($res['ok']) {
            $statusMap = ['start' => 'running', 'stop' => 'stopped', 'restart' => 'running', 'poweroff' => 'stopped'];
            if (isset($statusMap[$action])) {
                Database::update('vps_vz_instances', [
                    'vps_status' => $statusMap[$action],
                    'updated_at' => now(),
                ], 'vps_id = :v', ['v' => $vpsId]);
            }
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/vps-storevps'));
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

    /** Hostname hợp lệ theo API: lowercase, a-z 0-9 . _ -, 3–63 ký tự */
    public static function isValidHostname(string $host): bool
    {
        $len = strlen($host);
        if ($len < 3 || $len > 63) {
            return false;
        }
        if ($host !== strtolower($host)) {
            return false;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/', $host)) {
            return false;
        }

        return true;
    }

    /** Mật khẩu hợp lệ theo API: 10–64 ký tự, ≥2/3 nhóm, không khoảng trắng/ký tự nguy hiểm */
    public static function isValidPassword(string $pass): bool
    {
        $len = strlen($pass);
        if ($len < 10 || $len > 64) {
            return false;
        }
        if (preg_match('/\s/', $pass)) {
            return false;
        }
        // Ký tự nguy hiểm
        if (preg_match('/[<>"\'`\\\\\/;|&$%^*#()\[\]{}?=~+,]/', $pass)) {
            return false;
        }

        $groups = 0;
        if (preg_match('/[a-z]/', $pass)) { $groups++; }
        if (preg_match('/[A-Z]/', $pass)) { $groups++; }
        if (preg_match('/[0-9]/', $pass)) { $groups++; }

        return $groups >= 2;
    }

    /**
     * Đồng bộ danh mục tối đa 1 lần / 10 phút.
     */
    private static function shouldSync(): bool
    {
        $last = (int)setting('vps_vz_last_sync', '0');
        return (time() - $last) > 600;
    }

    private static function markSynced(): void
    {
        setting_set('vps_vz_last_sync', (string)time(), 'XVPS Virtualizor');
    }
}
