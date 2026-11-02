<?php
/**
 * Test render giao diện dịch vụ VPS StoreVPS (khách + quản trị).
 * Chạy: php tests/test_vps_vz_views.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\VirtualizorService;

$pass = 0;
$fail = 0;

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  [PASS] $name\n";
    } else {
        $fail++;
        echo "  [FAIL] $name" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

function section(string $t): void
{
    echo "\n== $t ==\n";
}

/**
 * Render 1 view với dữ liệu giả, bắt lỗi PHP nếu có.
 */
function renderView(string $template, array $data): array
{
    $file = BASE_PATH . '/app/Views/' . $template . '.php';
    if (!is_file($file)) {
        return ['ok' => false, 'html' => '', 'error' => 'View không tồn tại: ' . $template];
    }

    $data['__flash'] = [];
    extract($data, EXTR_SKIP);

    ob_start();
    try {
        include $file;
    } catch (\Throwable $e) {
        ob_end_clean();
        return ['ok' => false, 'html' => '', 'error' => get_class($e) . ': ' . $e->getMessage()];
    }
    $html = (string)ob_get_clean();

    return ['ok' => true, 'html' => $html, 'error' => ''];
}

echo "=== TEST GIAO DIỆN DỊCH VỤ VPS STOREVPS ===\n";

setting_set('vps_vz_markup', '10', 'XVPS Virtualizor');

// ------------------------------------------------------------------
section('1. View danh mục (khách)');
// ------------------------------------------------------------------
$product = [
    'id' => 1, 'product_id' => 'vz-101', 'group_name' => 'VPS StoreVPS Basic',
    'name' => 'STARTER (1-2-25)', 'display_name' => 'STARTER',
    'description' => 'Gói khởi đầu', 'type_vps' => 'kvm',
    'cpu' => 1, 'ram' => 2048, 'disk' => 25,
    'bandwidth' => 1000, 'network_speed' => 1000, 'upload_speed' => 1000,
    'ipv4' => 1, 'ipv6' => 1, 'ipv6_subnet' => 64,
    'type_addon' => '', 'unit_value' => 1, 'is_free' => 0, 'is_addon' => 0,
    'price_monthly' => 120000.0, 'price_quarterly' => 340000.0,
    'price_semi_annually' => 640000.0, 'price_annually' => 1200000.0,
    'pricing_json' => '{}',
    'locations_json' => json_encode([
        ['location_id' => 'loc-hcm', 'code' => 'HCM', 'name' => 'Việt Nam - Hồ Chí Minh', 'surcharge' => 0],
        ['location_id' => 'loc-sg', 'code' => 'SG', 'name' => 'Singapore', 'surcharge' => 20000],
    ]),
    'is_active' => 1, 'sort_order' => 0,
];

$addon = [
    'id' => 2, 'product_id' => 'vz-ad-cores', 'group_name' => 'Addon VPS StoreVPS',
    'name' => 'Addon CPU Core', 'display_name' => 'Addon CPU Core', 'description' => '',
    'type_vps' => '', 'cpu' => 1, 'ram' => 0, 'disk' => 0,
    'bandwidth' => 0, 'network_speed' => 0, 'upload_speed' => -1,
    'ipv4' => 0, 'ipv6' => 0, 'ipv6_subnet' => 0,
    'type_addon' => 'cores', 'unit_value' => 1, 'is_free' => 0, 'is_addon' => 1,
    'price_monthly' => 60000.0, 'price_quarterly' => 0.0,
    'price_semi_annually' => 0.0, 'price_annually' => 0.0,
    'pricing_json' => '{}', 'locations_json' => '[]',
    'is_active' => 1, 'sort_order' => 1,
];

$osList = [
    ['id' => 1, 'os_id' => '1001', 'os_name' => 'Ubuntu 22.04 LTS', 'distro' => 'ubuntu', 'is_active' => 1],
    ['id' => 2, 'os_id' => '1002', 'os_name' => 'Debian 12', 'distro' => 'debian', 'is_active' => 1],
];

$locations = [
    ['id' => 1, 'location_id' => 'loc-hcm', 'code' => 'HCM', 'name' => 'Việt Nam - Hồ Chí Minh', 'surcharge' => 0, 'product_id' => 'vz-101', 'is_active' => 1],
    ['id' => 2, 'location_id' => 'loc-sg', 'code' => 'SG', 'name' => 'Singapore', 'surcharge' => 20000, 'product_id' => 'vz-101', 'is_active' => 1],
];

$orders = [
    ['id' => 10, 'user_id' => 1, 'order_code' => 'VZ-ORD-1', 'remote_order' => 'VZ-ORD-1',
     'instance_id' => 'MOCK-VZ-1', 'product_id' => 'vz-101', 'product_name' => 'STARTER (1-2-25)',
     'billing_cycle' => 'monthly', 'os_id' => '1001', 'os_name' => 'Ubuntu 22.04 LTS',
     'location_id' => 'loc-hcm', 'location_name' => 'Hồ Chí Minh', 'hostname' => 'demo-vz',
     'quantity' => 1, 'addons_json' => null, 'price' => 120000.0, 'amount' => 132000.0,
     'status' => 'completed', 'vps_status' => 'running', 'ip' => '103.56.200.11',
     'username' => 'root', 'password' => 'MockPass@2026', 'date_create' => null,
     'next_due_date' => '2026-12-01', 'idempotency_key' => null, 'message' => 'OK',
     'raw' => null, 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00'],
];

$instances = [
    ['id' => 1, 'user_id' => 1, 'vps_id' => 'MOCK-VZ-1', 'hostname' => 'demo-vz',
     'order_code' => 'VZ-ORD-1', 'product_id' => 'vz-101', 'product_name' => 'STARTER (1-2-25)',
     'ip' => '103.56.200.11', 'ipv6' => '2001:db8::11', 'username' => 'root', 'password' => 'MockPass@2026',
     'os_name' => 'Ubuntu 22.04 LTS', 'cpu' => 1, 'ram' => 2048, 'disk' => 25,
     'text_config' => '1 vCPU / 2048 MB RAM / 25 GB SSD', 'type_vps' => 'kvm',
     'billing_cycle' => 'monthly', 'day_left' => '25', 'vps_status' => 'running',
     'amount' => 132000.0, 'auto_renew' => 1, 'description' => 'Gói VPS StoreVPS',
     'date_create' => '2026-11-01', 'next_due_date' => '2026-12-01', 'last_sync_at' => null,
     'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00'],
];

$r = renderView('services/vps-storevps', [
    'groups'     => ['VPS StoreVPS Basic' => [$product]],
    'osList'     => $osList,
    'locations'  => $locations,
    'addons'     => [$addon],
    'orders'     => $orders,
    'instances'  => $instances,
    'cycle'      => 'monthly',
    'cycles'     => VirtualizorService::CYCLES,
    'configured' => true,
    'enabled'    => true,
    'markup'     => 10.0,
    'balance'    => 5000000.0,
]);

ok('render danh mục không lỗi', $r['ok'], $r['error']);
ok('có tiêu đề StoreVPS', str_contains($r['html'], 'VPS StoreVPS'));
ok('hiện tên gói', str_contains($r['html'], 'STARTER (1-2-25)'));
ok('hiện thông số vCPU', str_contains($r['html'], '1 vCPU'));
ok('hiện giá bán 132000', str_contains($r['html'], '132.000') || str_contains($r['html'], '132000'));
ok('hiện giá gốc gạch ngang', str_contains($r['html'], '<s>'));
ok('có modal đặt đơn', str_contains($r['html'], 'vzOrderModal'));
ok('có trường OS', str_contains($r['html'], 'name="os_id"'));
ok('có trường hostname', str_contains($r['html'], 'name="hostname"'));
ok('có trường location', str_contains($r['html'], 'name="location_id"'));
ok('có trường quantity', str_contains($r['html'], 'name="quantity"'));
ok('có trường idempotency_key', str_contains($r['html'], 'name="idempotency_key"'));
ok('có ô addon CPU', str_contains($r['html'], 'name="addon_cores"'));
ok('có bảng giá addon', str_contains($r['html'], 'Addon CPU Core'));
ok('hiện số dư ví', str_contains($r['html'], '5.000.000') || str_contains($r['html'], '5000000'));
ok('hiện danh sách đơn', str_contains($r['html'], 'VZ-ORD-1'));
ok('hiện danh sách VPS của tôi', str_contains($r['html'], 'MOCK-VZ-1') || str_contains($r['html'], 'demo-vz'));
ok('hiện badge auto-renew', str_contains($r['html'], 'Auto'));
ok('không có lỗi PHP', !str_contains($r['html'], 'Fatal error') && !str_contains($r['html'], 'Warning:'));

// Dịch vụ tắt
$r = renderView('services/vps-storevps', [
    'groups' => [], 'osList' => [], 'locations' => [], 'addons' => [], 'orders' => [], 'instances' => [],
    'cycle' => 'monthly', 'cycles' => VirtualizorService::CYCLES,
    'configured' => false, 'enabled' => false, 'markup' => 0, 'balance' => 0,
]);
ok('render khi dịch vụ tắt không lỗi', $r['ok'], $r['error']);
ok('hiện thông báo tạm dừng', str_contains($r['html'], 'tạm dừng'));
ok('hiện cảnh báo chưa cấu hình', str_contains($r['html'], 'chưa được cấu hình'));

// ------------------------------------------------------------------
section('2. View chi tiết đơn');
// ------------------------------------------------------------------
$r = renderView('services/vps-storevps-order', ['order' => $orders[0]]);
ok('render chi tiết đơn không lỗi', $r['ok'], $r['error']);
ok('hiện mã đơn', str_contains($r['html'], 'VZ-ORD-1'));
ok('hiện hostname', str_contains($r['html'], 'demo-vz'));
ok('hiện datacenter', str_contains($r['html'], 'Hồ Chí Minh'));
ok('hiện tổng tiền', str_contains($r['html'], '132.000') || str_contains($r['html'], '132000'));
ok('hiện VAT = 0', str_contains($r['html'], '0 đ'));
ok('hiện thông tin kết nối', str_contains($r['html'], '103.56.200.11'));
ok('có link tới bảng điều khiển', str_contains($r['html'], '/services/vps-storevps/manage/MOCK-VZ-1'));

// ------------------------------------------------------------------
section('3. View danh sách VPS');
// ------------------------------------------------------------------
$r = renderView('services/vps-storevps-manage', ['instances' => $instances, 'orders' => $orders]);
ok('render danh sách VPS không lỗi', $r['ok'], $r['error']);
ok('có bảng máy chủ', str_contains($r['html'], 'Danh sách máy chủ'));
ok('hiện hostname', str_contains($r['html'], 'demo-vz'));
ok('hiện IP', str_contains($r['html'], '103.56.200.11'));
ok('hiện cấu hình', str_contains($r['html'], '1C / 2048M / 25G'));
ok('hiện badge trạng thái', str_contains($r['html'], 'Đang chạy'));
ok('có nút quản lý', str_contains($r['html'], '/services/vps-storevps/manage/MOCK-VZ-1'));

// Không có VPS
$r = renderView('services/vps-storevps-manage', ['instances' => [], 'orders' => []]);
ok('render danh sách rỗng không lỗi', $r['ok'], $r['error']);
ok('hiện empty state', str_contains($r['html'], 'Bạn chưa có VPS StoreVPS nào'));

// ------------------------------------------------------------------
section('4. View bảng điều khiển VPS');
// ------------------------------------------------------------------
$history = [
    ['id' => 1, 'user_id' => 1, 'vps_id' => 'MOCK-VZ-1', 'action' => 'start', 'status' => 'success',
     'message' => 'VPS đã khởi động thành công.', 'meta' => null, 'created_at' => '2026-11-01 11:00:00'],
    ['id' => 2, 'user_id' => 1, 'vps_id' => 'MOCK-VZ-1', 'action' => 'rebuild', 'status' => 'error',
     'message' => 'Thất bại', 'meta' => null, 'created_at' => '2026-11-01 12:00:00'],
];

$r = renderView('services/vps-storevps-detail', [
    'instance'  => $instances[0],
    'osList'    => $osList,
    'cycles'    => VirtualizorService::CYCLES,
    'addons'    => [$addon],
    'history'   => $history,
    'refreshed' => true,
]);

ok('render bảng điều khiển không lỗi', $r['ok'], $r['error']);
ok('hiện VPS ID', str_contains($r['html'], 'MOCK-VZ-1'));
ok('hiện IPv4', str_contains($r['html'], '103.56.200.11'));
ok('hiện IPv6', str_contains($r['html'], '2001:db8::11'));
ok('hiện mật khẩu (dạng password)', str_contains($r['html'], 'js-toggle-pw'));
ok('có nút khởi động lại', str_contains($r['html'], 'value="restart"'));
ok('có nút tắt máy', str_contains($r['html'], 'value="stop"'));
ok('có nút tắt cứng', str_contains($r['html'], 'value="poweroff"'));
ok('có form gia hạn', str_contains($r['html'], '/services/vps-storevps/renew'));
ok('có form đổi mật khẩu', str_contains($r['html'], '/services/vps-storevps/password'));
ok('có form đổi hostname', str_contains($r['html'], '/services/vps-storevps/hostname'));
ok('có form rebuild', str_contains($r['html'], '/services/vps-storevps/rebuild'));
ok('có form addon', str_contains($r['html'], '/services/vps-storevps/addon'));
ok('có form auto-renew', str_contains($r['html'], '/services/vps-storevps/auto-renew'));
ok('có form hủy VPS', str_contains($r['html'], '/services/vps-storevps/cancel'));
ok('có ô addon RAM', str_contains($r['html'], 'name="addon_ram"'));
ok('có ô addon disk', str_contains($r['html'], 'name="addon_disk"'));
ok('auto-renew được check', str_contains($r['html'], 'id="autoRenew" checked'));
ok('hiện lịch sử thao tác', str_contains($r['html'], 'Lịch sử thao tác'));
ok('hiện action start trong lịch sử', str_contains($r['html'], 'start'));
ok('không có lỗi PHP', !str_contains($r['html'], 'Fatal error') && !str_contains($r['html'], 'Warning:'));

// VPS đang chạy → không hiện nút start
$off = $instances[0];
$off['vps_status'] = 'stopped';
$r = renderView('services/vps-storevps-detail', [
    'instance' => $off, 'osList' => $osList, 'cycles' => VirtualizorService::CYCLES,
    'addons' => [$addon], 'history' => [], 'refreshed' => true,
]);
ok('VPS đã tắt → có nút khởi động', str_contains($r['html'], 'value="start"'));
ok('VPS đã tắt → không có nút restart', !str_contains($r['html'], 'value="restart"'));

// VPS đang tạo → khóa thao tác
$creating = $instances[0];
$creating['vps_status'] = 'creating';
$r = renderView('services/vps-storevps-detail', [
    'instance' => $creating, 'osList' => $osList, 'cycles' => VirtualizorService::CYCLES,
    'addons' => [$addon], 'history' => [], 'refreshed' => false,
]);
ok('VPS đang tạo → không có nút nguồn', !str_contains($r['html'], 'value="start"') && !str_contains($r['html'], 'value="restart"'));
ok('VPS đang tạo → hiện cảnh báo', str_contains($r['html'], 'không thể thực hiện thao tác nguồn'));
ok('refreshed=false → hiện cảnh báo API', str_contains($r['html'], 'Không làm mới được'));

// ------------------------------------------------------------------
section('5. View quản trị');
// ------------------------------------------------------------------
$adminOrders = array_map(static function ($o) {
    $o['username'] = 'tester';
    return $o;
}, $orders);

$adminInstances = array_map(static function ($v) {
    $v['username'] = 'tester';
    return $v;
}, $instances);

$r = renderView('admin/vps-storevps', [
    'stats'      => ['orders' => 5, 'active' => 3, 'pending' => 1, 'products' => 8, 'revenue' => 1500000],
    'products'   => array_merge([$product], [$addon]),
    'osList'     => $osList,
    'locations'  => $locations,
    'orders'     => $adminOrders,
    'instances'  => $adminInstances,
    'partner'    => ['ok' => true, 'balance' => 25000000.0, 'currency' => 'VND', 'message' => '', 'user' => ['name' => 'Mock StoreVPS Agency'], 'stats' => [], 'scopes' => []],
    'baseUrl'    => 'https://api.xvps.vn',
    'hasCreds'   => true,
    'username'   => 'vz_user',
    'app'        => 'vz_app',
    'hasSecret'  => true,
    'markup'     => 10.0,
    'enabled'    => true,
    'sandbox'    => false,
    'addonTypes' => VirtualizorService::ADDON_TYPES,
]);

ok('render admin không lỗi', $r['ok'], $r['error']);
ok('có tiêu đề Virtualizor', str_contains($r['html'], 'Virtualizor'));
ok('có ô api-username', str_contains($r['html'], 'name="api_username"'));
ok('có ô api-app', str_contains($r['html'], 'name="api_app"'));
ok('có ô api-secret password', str_contains($r['html'], 'name="api_secret"'));
ok('có ô base_url', str_contains($r['html'], 'name="base_url"'));
ok('có ô markup', str_contains($r['html'], 'name="markup"'));
ok('có toggle sandbox', str_contains($r['html'], 'name="sandbox"'));
ok('có toggle auto_sync', str_contains($r['html'], 'name="auto_sync"'));
ok('hiện số dư đối tác', str_contains($r['html'], '25.000.000') || str_contains($r['html'], '25000000'));
ok('hiện bảng fleet', str_contains($r['html'], 'Fleet VPS'));
ok('hiện bảng đơn hàng', str_contains($r['html'], 'Đơn hàng gần đây'));
ok('hiện danh mục gói', str_contains($r['html'], 'Danh mục gói VPS'));
ok('hiện bảng datacenter', str_contains($r['html'], 'Datacenter'));
ok('hiện danh sách OS', str_contains($r['html'], 'Hệ điều hành'));
ok('hiện thống kê doanh thu', str_contains($r['html'], '1.500.000') || str_contains($r['html'], '1500000'));
ok('không có lỗi PHP', !str_contains($r['html'], 'Fatal error') && !str_contains($r['html'], 'Warning:'));

// ------------------------------------------------------------------
section('6. XSS escaping');
// ------------------------------------------------------------------
$xss = $orders[0];
$xss['product_name'] = '<script>alert(1)</script>';
$r = renderView('services/vps-storevps-order', ['order' => $xss]);
ok('render đơn với XSS không lỗi', $r['ok'], $r['error']);
ok('XSS được escape', !str_contains($r['html'], '<script>alert(1)</script>'), 'còn script thô trong HTML');
ok('XSS thành entity', str_contains($r['html'], '&lt;script&gt;'));

// ------------------------------------------------------------------
section('7. Dọn dẹp');
// ------------------------------------------------------------------
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
ok('dọn dẹp hoàn tất', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
