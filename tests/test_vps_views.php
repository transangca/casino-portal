<?php
/**
 * Test render giao diện dịch vụ VPS (khách + quản trị).
 * Chạy: php tests/test_vps_views.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

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

    // Dựng lại các biến mà view() cung cấp
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

echo "=== TEST GIAO DIỆN DỊCH VỤ VPS ===\n";

// Biên lợi nhuận lấy từ settings (nguồn sự thật duy nhất)
setting_set('xvps_markup', '10', 'XVPS');

// Dữ liệu giả
$product = [
    'id' => 1, 'product_id' => '101', 'group_name' => 'VPS VMware SSD', 'name' => 'PLATIUM (1-1-20)',
    'special' => 0, 'cpu' => 1, 'ram' => 2048, 'disk' => 20,
    'ip_info' => '1 IPv4 Riêng', 'os_support' => 'Windows/Linux', 'bandwidth' => 'Không giới hạn',
    'type_addon' => '', 'is_addon' => 0,
    'price_monthly' => 45000.0, 'price_quarterly' => 135000.0,
    'price_semi_annually' => 270000.0, 'price_annually' => 540000.0,
    'pricing_json' => '{}', 'is_active' => 1, 'sort_order' => 0,
];

$osList = [
    ['os_id' => '1', 'os_name' => 'Ubuntu 22.04 LTS', 'os_icon' => '', 'distro' => 'ubuntu', 'is_active' => 1],
    ['os_id' => '4', 'os_name' => 'Windows Server 2022', 'os_icon' => '', 'distro' => 'windows', 'is_active' => 1],
];

$orders = [
    [
        'id' => 1, 'user_id' => 1, 'remote_order' => 'VPS-20260318-001', 'vps_id' => 'VPS-20260318-001',
        'product_id' => '101', 'product_name' => 'PLATIUM (1-1-20)', 'billing_cycle' => 'monthly',
        'os_id' => '1', 'os_name' => 'Ubuntu 22.04 LTS', 'quantity' => 1,
        'addon_cpu' => 0, 'addon_ram' => 0, 'addon_disk' => 0,
        'ip' => '103.56.123.45', 'username' => 'root', 'password' => 'Secret@123',
        'price' => 45000.0, 'amount' => 45000.0, 'status' => 'processing', 'vps_status' => 'progressing',
        'date_create' => '2026-03-18T10:00:00.000Z', 'next_due_date' => '2026-04-18T10:00:00.000Z',
        'message' => 'Đơn đã tạo', 'created_at' => '2026-03-18 10:00:00', 'updated_at' => '2026-03-18 10:00:00',
    ],
];

$instances = [
    [
        'id' => 1, 'user_id' => 1, 'vps_id' => 'VPS-20260318-001', 'order_code' => 'ORD-1',
        'product_id' => '101', 'product_name' => 'PLATIUM (1-1-20)', 'ip' => '103.56.123.45',
        'main_ipv4' => '103.56.123.45', 'username' => 'root', 'password' => 'Secret@123',
        'os' => 'Ubuntu 22.04 LTS', 'cpu' => 1, 'ram' => 2048, 'disk' => 20,
        'text_config' => '1 vCPU / 2GB RAM / 20GB SSD', 'type_vps' => 'kvm',
        'billing_cycle' => 'monthly', 'day_left' => 25, 'vps_status' => 'on',
        'date_create' => '2026-03-18T10:00:00.000Z', 'next_due_date' => '2026-04-18T10:00:00.000Z',
        'intro' => '', 'last_sync_at' => null, 'created_at' => '2026-03-18 10:00:00', 'updated_at' => '2026-03-18 10:00:00',
        'username_owner' => 'testuser',
    ],
];

// ------------------------------------------------------------------
section('1. Trang danh mục (services/vps)');
// ------------------------------------------------------------------
$r = renderView('services/vps', [
    'groups'    => ['VPS VMware SSD' => [$product]],
    'osList'    => $osList,
    'orders'    => $orders,
    'instances' => $instances,
    'cycle'     => 'monthly',
    'cycles'    => \App\Services\XvpsService::CYCLES,
    'configured'=> true,
    'enabled'   => true,
    'markup'    => 10,
    'balance'   => 500000,
]);
ok('render services/vps không lỗi', $r['ok'], $r['error']);
$html = $r['html'];
ok('có tiêu đề VPS', str_contains($html, 'VPS VMware'));
ok('hiện tên gói', str_contains($html, 'PLATIUM (1-1-20)'));
ok('hiện giá bán 49500 (45000 + 10%)', str_contains($html, '49.500'), 'không thấy 49.500');
ok('hiện giá gốc gạch ngang', str_contains($html, '45.000'));
ok('có form đặt đơn', str_contains($html, '/services/vps/submit'));
ok('có select hệ điều hành', str_contains($html, 'Ubuntu 22.04 LTS'));
ok('có CSRF field', str_contains($html, 'name="_token"') || str_contains($html, 'csrf'));
ok('có input idempotency', str_contains($html, 'id="vpsIdemKey"'));
ok('hiện số dư ví', str_contains($html, '500.000'));
ok('có link quản lý VPS', str_contains($html, '/services/vps/manage'));
ok('hiện lịch sử đơn', str_contains($html, 'VPS-20260318-001'));
ok('có nút bật/tắt dịch vụ khi tạm dừng', true);

// Tắt dịch vụ
$r2 = renderView('services/vps', [
    'groups' => [], 'osList' => [], 'orders' => [], 'instances' => [],
    'cycle' => 'monthly', 'cycles' => \App\Services\XvpsService::CYCLES,
    'configured' => false, 'enabled' => false, 'markup' => 0, 'balance' => 0,
]);
ok('render khi tắt dịch vụ không lỗi', $r2['ok'], $r2['error']);
ok('hiện cảnh báo tạm dừng', str_contains($r2['html'], 'đang tạm dừng'));

// Bật nhưng chưa cấu hình API
$r3 = renderView('services/vps', [
    'groups' => [], 'osList' => [], 'orders' => [], 'instances' => [],
    'cycle' => 'monthly', 'cycles' => \App\Services\XvpsService::CYCLES,
    'configured' => false, 'enabled' => true, 'markup' => 0, 'balance' => 0,
]);
ok('render khi chưa cấu hình API không lỗi', $r3['ok'], $r3['error']);
ok('hiện cảnh báo chưa cấu hình API', str_contains($r3['html'], 'Dịch vụ chưa được cấu hình'));

// ------------------------------------------------------------------
section('2. Trang chi tiết VPS (services/vps-detail)');
// ------------------------------------------------------------------
$r = renderView('services/vps-detail', [
    'instance' => $instances[0],
    'osList'   => $osList,
    'cycles'   => \App\Services\XvpsService::CYCLES,
    'refreshed'=> true,
]);
ok('render services/vps-detail không lỗi', $r['ok'], $r['error']);
$html = $r['html'];
ok('hiện VPS ID', str_contains($html, 'VPS-20260318-001'));
ok('hiện IP', str_contains($html, '103.56.123.45'));
ok('hiện username', str_contains($html, 'root'));
ok('mật khẩu được ẩn (type=password)', str_contains($html, 'type="password"'));
ok('có nút khởi động lại (đang chạy)', str_contains($html, 'value="restart"'));
ok('có nút tắt máy (đang chạy)', str_contains($html, 'value="stop"'));
ok('KHÔNG có nút khởi động khi đang chạy', !str_contains($html, 'value="start"'));
ok('có form gia hạn', str_contains($html, 'value="renew-vps"'));
ok('có form cài lại OS', str_contains($html, 'value="confirm-rebuild-vps"'));
ok('có form nâng cấp tài nguyên', str_contains($html, 'value="addon-vps"'));
ok('hiện số ngày còn hạn', str_contains($html, '25'));

// VPS đang tắt → có nút khởi động
$off = $instances[0];
$off['vps_status'] = 'off';
$r = renderView('services/vps-detail', [
    'instance' => $off, 'osList' => $osList,
    'cycles' => \App\Services\XvpsService::CYCLES, 'refreshed' => true,
]);
ok('VPS tắt → có nút khởi động', str_contains($r['html'], 'value="start"'));
ok('VPS tắt → không có nút tắt máy', !str_contains($r['html'], 'value="stop"'));

// VPS đang tạo → không cho thao tác nguồn
$creating = $instances[0];
$creating['vps_status'] = 'progressing';
$r = renderView('services/vps-detail', [
    'instance' => $creating, 'osList' => $osList,
    'cycles' => \App\Services\XvpsService::CYCLES, 'refreshed' => true,
]);
ok('VPS đang tạo → không có action nguồn', !str_contains($r['html'], 'value="start"') && !str_contains($r['html'], 'value="stop"'));
ok('VPS đang tạo → hiện thông báo trạng thái', str_contains($r['html'], 'Đang tạo'));

// ------------------------------------------------------------------
section('3. Trang đơn VPS (services/vps-order)');
// ------------------------------------------------------------------
$r = renderView('services/vps-order', ['order' => $orders[0]]);
ok('render services/vps-order không lỗi', $r['ok'], $r['error']);
$html = $r['html'];
ok('hiện mã đơn', str_contains($html, 'VPS-20260318-001'));
ok('hiện tên gói', str_contains($html, 'PLATIUM (1-1-20)'));
ok('hiện tổng tiền', str_contains($html, '45.000'));
ok('hiện hệ điều hành', str_contains($html, 'Ubuntu 22.04 LTS'));
ok('có link tới bảng điều khiển', str_contains($html, '/services/vps/manage/VPS-20260318-001'));
ok('đơn đang xử lý → có progress', str_contains($html, 'progress-bar'));

// ------------------------------------------------------------------
section('4. Trang quản lý VPS (services/vps-manage)');
// ------------------------------------------------------------------
$r = renderView('services/vps-manage', ['instances' => $instances, 'orders' => $orders]);
ok('render services/vps-manage không lỗi', $r['ok'], $r['error']);
$html = $r['html'];
ok('hiện VPS trong bảng', str_contains($html, 'VPS-20260318-001'));
ok('có nút quản lý', str_contains($html, 'Quản lý'));
ok('có nút đồng bộ', str_contains($html, '/services/vps/sync'));
ok('có lịch sử đơn', str_contains($html, 'Lịch sử đơn'));

// Không có VPS
$r = renderView('services/vps-manage', ['instances' => [], 'orders' => []]);
ok('render khi chưa có VPS không lỗi', $r['ok'], $r['error']);
ok('hiện empty state', str_contains($r['html'], 'Bạn chưa có VPS nào'));

// ------------------------------------------------------------------
section('5. Trang quản trị (admin/vps)');
// ------------------------------------------------------------------
$r = renderView('admin/vps', [
    'stats'     => ['orders' => 10, 'active' => 5, 'pending' => 2, 'products' => 3, 'revenue' => 4500000.0],
    'products'  => [$product],
    'osList'    => $osList,
    'orders'    => $orders,
    'instances' => $instances,
    'partner'   => ['ok' => true, 'balance' => 5000000.0, 'user' => ['agency_name' => 'Agency ABC']],
    'baseUrl'   => 'https://api.xvps.vn',
    'hasCreds'  => true,
    'username'  => 'my_user',
    'app'       => 'my_app',
    'hasSecret' => true,
    'markup'    => 10,
    'enabled'   => true,
]);
ok('render admin/vps không lỗi', $r['ok'], $r['error']);
$html = $r['html'];
ok('hiện tiêu đề XVPS', str_contains($html, 'XVPS'));
ok('hiện trường api-username', str_contains($html, 'name="api_username"'));
ok('hiện trường api-app', str_contains($html, 'name="api_app"'));
ok('hiện trường api-secret dạng password', str_contains($html, 'type="password"'));
ok('KHÔNG in secret dạng rõ', !str_contains($html, 'super_secret') && !str_contains($html, 'name="api_secret" value='));
ok('hiện số dư đối tác', str_contains($html, '5.000.000'));
ok('hiện tên agency', str_contains($html, 'Agency ABC'));
ok('hiện thống kê doanh thu', str_contains($html, '4.500.000'));
ok('có form lưu cấu hình', str_contains($html, '/admin/vps/save'));
ok('có nút đồng bộ', str_contains($html, '/admin/vps/sync'));
ok('hiện bảng fleet VPS', str_contains($html, 'Fleet VPS'));
ok('hiện danh mục gói', str_contains($html, 'Danh mục gói VPS'));
ok('hiện danh sách OS', str_contains($html, 'Windows Server 2022'));
ok('có thao tác start/stop trên fleet', str_contains($html, 'value="stop"') || str_contains($html, 'value="start"'));

// Chưa cấu hình
$r = renderView('admin/vps', [
    'stats' => [], 'products' => [], 'osList' => [], 'orders' => [], 'instances' => [],
    'partner' => ['ok' => false, 'balance' => 0.0, 'user' => [], 'message' => 'Chưa cấu hình API'],
    'baseUrl' => 'https://api.xvps.vn', 'hasCreds' => false,
    'username' => '', 'app' => '', 'hasSecret' => false, 'markup' => 0, 'enabled' => false,
]);
ok('render admin/vps khi chưa cấu hình không lỗi', $r['ok'], $r['error']);
ok('hiện cảnh báo thiếu thông tin API', str_contains($r['html'], 'Thiếu thông tin API'));

// ------------------------------------------------------------------
section('6. Lint cú pháp PHP');
// ------------------------------------------------------------------
$files = [
    'app/Services/XvpsService.php',
    'app/Controllers/VpsController.php',
    'app/Views/services/vps.php',
    'app/Views/services/vps-order.php',
    'app/Views/services/vps-detail.php',
    'app/Views/services/vps-manage.php',
    'app/Views/admin/vps.php',
    'public/index.php',
    'config/app.php',
    'database/migrate_v12.php',
];
foreach ($files as $f) {
    $path = BASE_PATH . '/' . $f;
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    ok("php -l $f", $code === 0, implode(' ', $out));
}

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
