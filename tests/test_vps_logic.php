<?php
/**
 * Test logic dịch vụ VPS (XVPS Agency API).
 * Chạy: php tests/test_vps_logic.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\XvpsService;

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

echo "=== TEST LOGIC DỊCH VỤ VPS (XVPS Agency API) ===\n";

// ------------------------------------------------------------------
section('1. Cấu hình & bật/tắt dịch vụ');
// ------------------------------------------------------------------
setting_set('xvps_service_on', '1', 'XVPS');
ok('enabled() đúng khi bật', XvpsService::enabled() === true);

setting_set('xvps_service_on', '0', 'XVPS');
ok('enabled() đúng khi tắt', XvpsService::enabled() === false);
setting_set('xvps_service_on', '1', 'XVPS');

// base_url: đọc từ settings, fallback về config (mặc định api.xvps.vn)
setting_set('xvps_base_url', '', 'XVPS');
ok('base_url mặc định là api.xvps.vn', str_contains(XvpsService::baseUrl(), 'api.xvps.vn'), XvpsService::baseUrl());
setting_set('xvps_base_url', 'http://127.0.0.1:8788/', 'XVPS');
ok('base_url lấy từ settings và bỏ dấu / cuối', XvpsService::baseUrl() === 'http://127.0.0.1:8788', XvpsService::baseUrl());
setting_set('xvps_base_url', 'https://api.xvps.vn', 'XVPS');

// ------------------------------------------------------------------
section('2. Mã hoá / giải mã api-secret');
// ------------------------------------------------------------------
XvpsService::saveCredentials('test_user', 'test_app', 'super_secret_123');
ok('api-username lưu đúng', XvpsService::apiUsername() === 'test_user', XvpsService::apiUsername());
ok('api-app lưu đúng', XvpsService::apiApp() === 'test_app', XvpsService::apiApp());
ok('api-secret giải mã đúng', XvpsService::apiSecret() === 'super_secret_123', XvpsService::apiSecret());

$rawSecret = (string)setting('xvps_api_secret', '');
ok('api-secret được mã hoá trong DB', $rawSecret !== 'super_secret_123' && $rawSecret !== '', 'giá trị thô: ' . $rawSecret);

ok('configured() true khi đủ 3 thông tin', XvpsService::configured() === true);

XvpsService::saveCredentials('', '', '');
ok('configured() false khi thiếu thông tin', XvpsService::configured() === false);

// Khôi phục
XvpsService::saveCredentials('test_user', 'test_app', 'super_secret_123');

// ------------------------------------------------------------------
section('3. Biên lợi nhuận & tính giá');
// ------------------------------------------------------------------
setting_set('xvps_markup', '0', 'XVPS');
ok('markup 0 → giá nguyên bản', XvpsService::markup() === 0.0);

setting_set('xvps_markup', '10', 'XVPS');
ok('markup 10 được đọc', XvpsService::markup() === 10.0);
ok('sellPrice 100000 + 10% = 110000', XvpsService::sellPrice(100000.0) === 110000.0, (string)XvpsService::sellPrice(100000.0));
ok('sellPrice làm tròn ceil', XvpsService::sellPrice(1001.0) === 1102.0, (string)XvpsService::sellPrice(1001.0));

setting_set('xvps_markup', '0', 'XVPS');

$product = [
    'price_monthly'         => 45000.0,
    'price_quarterly'       => 135000.0,
    'price_semi_annually'   => 270000.0,
    'price_annually'        => 540000.0,
    'pricing_json'          => json_encode([
        'addon_cpu' => ['monthly' => ['amount' => 26250]],
        'addon_ram' => ['monthly' => ['amount' => 26250]],
        'addon_disk' => ['monthly' => ['amount' => 15000]],
    ]),
];

ok('cyclePrice monthly', XvpsService::cyclePrice($product, 'monthly') === 45000.0);
ok('cyclePrice quarterly', XvpsService::cyclePrice($product, 'quarterly') === 135000.0);
ok('cyclePrice annually', XvpsService::cyclePrice($product, 'annually') === 540000.0);
ok('cyclePrice yearly → annually', XvpsService::cyclePrice($product, 'yearly') === 540000.0);

$calc = XvpsService::calcTotal($product, 'monthly', 1);
ok('calcTotal 1 máy tháng = 45000', $calc['total'] === 45000.0, (string)$calc['total']);
ok('calcTotal VAT = 0', $calc['vat'] === 0.0);

$calc = XvpsService::calcTotal($product, 'monthly', 3);
ok('calcTotal 3 máy = 135000', $calc['total'] === 135000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 1, 1, 0, 0);
ok('calcTotal +1 CPU addon (26250) = 71250', $calc['total'] === 71250.0, (string)$calc['total']);

// Addon disk bán theo khối 10GB: 10GB = 1 đơn vị = 15000
$calc = XvpsService::calcTotal($product, 'monthly', 1, 0, 2, 10);
ok('calcTotal +2RAM +10GB disk = 45000+52500+15000 = 112500', $calc['total'] === 112500.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 1, 0, 0, 30);
ok('calcTotal +30GB disk = 3 đơn vị = 45000+45000 = 90000', $calc['total'] === 90000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 1, 0, 0, 5);
ok('calcTotal +5GB disk (< 1 đơn vị) không tính tiền = 45000', $calc['total'] === 45000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'quarterly', 1);
ok('calcTotal quý = 135000', $calc['total'] === 135000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 0);
ok('quantity tối thiểu = 1', $calc['quantity'] === 1, (string)$calc['quantity']);

// ------------------------------------------------------------------
section('4. Quy đổi trạng thái');
// ------------------------------------------------------------------
ok('mapStatus on → completed', XvpsService::mapStatus('on') === XvpsService::STATUS_COMPLETED);
ok('mapStatus off → completed', XvpsService::mapStatus('off') === XvpsService::STATUS_COMPLETED);
ok('mapStatus pending → pending', XvpsService::mapStatus('pending') === XvpsService::STATUS_PENDING);
ok('mapStatus progressing → processing', XvpsService::mapStatus('progressing') === XvpsService::STATUS_PROCESSING);
ok('mapStatus expire → failed', XvpsService::mapStatus('expire') === XvpsService::STATUS_FAILED);
ok('mapStatus suspend → failed', XvpsService::mapStatus('suspend') === XvpsService::STATUS_FAILED);

ok('statusLabel pending', XvpsService::statusLabel(XvpsService::STATUS_PENDING) === 'Đang chờ');
ok('statusBadge completed', XvpsService::statusBadge(XvpsService::STATUS_COMPLETED) === 'success');
ok('vpsStatusLabel on', XvpsService::vpsStatusLabel('on') === 'Đang chạy');
ok('vpsStatusBadge expire', XvpsService::vpsStatusBadge('expire') === 'danger');

// ------------------------------------------------------------------
section('5. Chuẩn hoá product từ API');
// ------------------------------------------------------------------
$raw = [
    'product_id' => 101,
    'name'        => 'PLATIUM (1-1-20)',
    'special'     => 0,
    'cpu'         => 1,
    'ram'         => 1,
    'disk'        => 20,
    'ip'          => '1 IPv4 Riêng',
    'os'          => 'Windows/Linux',
    'bandwidth'   => 'Không giới hạn',
    'pricing'     => [
        'monthly'      => ['amount' => 45000, 'billing_cycle' => '1 Tháng'],
        'quarterly'    => ['amount' => 135000, 'billing_cycle' => '3 Tháng'],
        'semi_annually'=> ['amount' => 270000, 'billing_cycle' => '6 Tháng'],
        'annually'     => ['amount' => 540000, 'billing_cycle' => '12 Tháng'],
    ],
];
$p = XvpsService::normalizeProduct($raw, 'VPS VMware', false);
ok('normalize product_id giữ dạng chuỗi', $p['product_id'] === '101', $p['product_id']);
ok('normalize tên gói', $p['name'] === 'PLATIUM (1-1-20)');
ok('normalize giá tháng', $p['price_monthly'] === 45000.0);
ok('normalize giá quý', $p['price_quarterly'] === 135000.0);
ok('normalize giá 6 tháng', $p['price_semi_annually'] === 270000.0);
ok('normalize giá năm', $p['price_annually'] === 540000.0);
ok('normalize group_name', $p['group_name'] === 'VPS VMware');
ok('normalize is_addon false', $p['is_addon'] === false);
ok('normalize pricing_json không rỗng', $p['pricing_json'] !== '' && $p['pricing_json'] !== '{}');

// Addon
$addon = XvpsService::normalizeProduct([
    'product_id' => 1,
    'name'       => 'Addon CPU',
    'type_addon' => 'addon_cpu',
    'cpu'        => 1,
    'pricing'    => ['monthly' => ['amount' => 26250]],
], 'Addon VPS', true);
ok('normalize addon is_addon true', $addon['is_addon'] === true);
ok('normalize addon type_addon', $addon['type_addon'] === 'addon_cpu');

// ------------------------------------------------------------------
section('6. Action hợp lệ');
// ------------------------------------------------------------------
ok('action start hợp lệ', isset(XvpsService::ACTIONS['start']));
ok('action stop hợp lệ', isset(XvpsService::ACTIONS['stop']));
ok('action restart hợp lệ', isset(XvpsService::ACTIONS['restart']));
ok('action confirm-rebuild-vps hợp lệ', isset(XvpsService::ACTIONS['confirm-rebuild-vps']));
ok('action renew-vps hợp lệ', isset(XvpsService::ACTIONS['renew-vps']));
ok('action addon-vps hợp lệ', isset(XvpsService::ACTIONS['addon-vps']));
ok('action lạ bị từ chối', !isset(XvpsService::ACTIONS['hack']));

$res = XvpsService::actionVps('hack', '123');
ok('actionVps từ chối action lạ', $res['ok'] === false, json_encode($res, JSON_UNESCAPED_UNICODE));

// ------------------------------------------------------------------
section('7. Chu kỳ hỗ trợ');
// ------------------------------------------------------------------
ok('có monthly', isset(XvpsService::CYCLES['monthly']));
ok('có quarterly', isset(XvpsService::CYCLES['quarterly']));
ok('có semi-annually', isset(XvpsService::CYCLES['semi-annually']));
ok('có annually', isset(XvpsService::CYCLES['annually']));
ok('có yearly', isset(XvpsService::CYCLES['yearly']));

// ------------------------------------------------------------------
section('8. Đồng bộ danh mục vào DB');
// ------------------------------------------------------------------
// Dọn sạch dữ liệu test
Database::query("DELETE FROM vps_products WHERE product_id LIKE 'TEST-%'");
Database::query("DELETE FROM vps_operating_systems WHERE os_id LIKE 'TEST-%'");

$fakeProducts = [
    XvpsService::normalizeProduct([
        'product_id' => 'TEST-101',
        'name'       => 'TEST PLATIUM',
        'cpu'        => 1, 'ram' => 2048, 'disk' => 20,
        'pricing'    => ['monthly' => ['amount' => 45000], 'quarterly' => ['amount' => 135000]],
    ], 'TEST Group', false),
    XvpsService::normalizeProduct([
        'product_id' => 'TEST-A1',
        'name'       => 'TEST Addon CPU',
        'type_addon' => 'addon_cpu',
        'pricing'    => ['monthly' => ['amount' => 26250]],
    ], 'TEST Group', true),
];

foreach ($fakeProducts as $p) {
    $exists = Database::fetchColumn('SELECT id FROM vps_products WHERE product_id = ?', [$p['product_id']]);
    $row = [
        'product_id' => $p['product_id'],
        'group_name' => $p['group_name'],
        'name' => $p['name'],
        'special' => $p['special'],
        'cpu' => $p['cpu'], 'ram' => $p['ram'], 'disk' => $p['disk'],
        'ip_info' => $p['ip_info'], 'os_support' => $p['os_support'], 'bandwidth' => $p['bandwidth'],
        'type_addon' => $p['type_addon'], 'is_addon' => (int)$p['is_addon'],
        'price_monthly' => $p['price_monthly'], 'price_quarterly' => $p['price_quarterly'],
        'price_semi_annually' => $p['price_semi_annually'], 'price_annually' => $p['price_annually'],
        'pricing_json' => $p['pricing_json'], 'is_active' => 1, 'sort_order' => 0,
        'updated_at' => now(),
    ];
    if ($exists) {
        Database::update('vps_products', $row, 'id = :id', ['id' => (int)$exists]);
    } else {
        Database::insert('vps_products', $row);
    }
}

ok('gói VPS được lưu vào DB', XvpsService::findProduct('TEST-101') !== null);
ok('localProducts chỉ trả gói (không addon)', count(array_filter(XvpsService::localProducts(false), fn($x) => $x['product_id'] === 'TEST-101')) === 1);
ok('localAddons trả addon', count(array_filter(XvpsService::localAddons(), fn($x) => $x['product_id'] === 'TEST-A1')) === 1);
ok('productGroups gom theo nhóm', isset(XvpsService::productGroups()['TEST Group']));

// Addon giá lấy từ pricing_json
$addonRow = XvpsService::findProduct('TEST-A1');
$unit = XvpsService::addonUnitPrice($addonRow, 'addon_cpu', 'monthly');
ok('addonUnitPrice lấy đúng giá 26250', $unit === 26250.0, (string)$unit);

// OS
Database::insert('vps_operating_systems', [
    'os_id' => 'TEST-1', 'os_name' => 'TEST Ubuntu 22.04', 'is_active' => 1, 'updated_at' => now(),
]);
ok('OS được lưu', XvpsService::findOperatingSystem('TEST-1') !== null);
ok('localOperatingSystems trả OS', count(array_filter(XvpsService::localOperatingSystems(), fn($x) => $x['os_id'] === 'TEST-1')) === 1);

// ------------------------------------------------------------------
section('9. Đơn hàng');
// ------------------------------------------------------------------
$uid = (int)Database::fetchColumn('SELECT id FROM users ORDER BY id LIMIT 1');
ok('có user để test', $uid > 0);

$orderId = XvpsService::createOrderRow($uid, [
    'product_id' => 'TEST-101',
    'product_name' => 'TEST PLATIUM',
    'billing_cycle' => 'monthly',
    'os_id' => 'TEST-1',
    'os_name' => 'TEST Ubuntu 22.04',
    'quantity' => 1,
    'price' => 45000,
    'amount' => 45000,
    'status' => XvpsService::STATUS_PENDING,
    'idempotency_key' => 'TEST-IDEM-1',
]);
ok('tạo đơn thành công', $orderId > 0);

$order = XvpsService::findOrder($uid, $orderId);
ok('tìm thấy đơn của user', $order !== null);
ok('đơn lưu đúng amount', (float)$order['amount'] === 45000.0);
ok('đơn lưu đúng product', $order['product_id'] === 'TEST-101');

// Idempotency: key trùng → trả về đơn cũ
$orderId2 = XvpsService::createOrderRow($uid, [
    'product_id' => 'TEST-101', 'amount' => 45000, 'idempotency_key' => 'TEST-IDEM-1',
]);
ok('idempotency trả về cùng đơn', $orderId2 === $orderId, "$orderId vs $orderId2");

// User khác không thấy đơn
ok('đơn thuộc đúng user', XvpsService::findOrder($uid + 99999, $orderId) === null);

// ------------------------------------------------------------------
section('10. VPS instance');
// ------------------------------------------------------------------
XvpsService::upsertInstance($uid, [
    'vps-id'       => 'TEST-VPS-1',
    'order-code'   => 'TEST-ORD-1',
    'product-id'   => 'TEST-101',
    'ip'           => '103.56.123.45',
    'main-ipv4'    => '103.56.123.45',
    'username'     => 'root',
    'password'     => 'Pass@123',
    'os'           => 'TEST Ubuntu 22.04',
    'cpu'          => 1, 'ram' => 2048, 'disk' => 20,
    'vps-status'   => 'on',
    'billing-cycle'=> 'monthly',
    'day_left'     => 25,
    'next_due_date'=> '2026-12-01T00:00:00.000Z',
]);
ok('upsert instance tạo mới', XvpsService::findInstance($uid, 'TEST-VPS-1') !== null);

XvpsService::upsertInstance($uid, [
    'vps-id'     => 'TEST-VPS-1',
    'vps-status' => 'off',
    'ip'         => '103.56.123.45',
]);
$inst = XvpsService::findInstance($uid, 'TEST-VPS-1');
ok('upsert lần 2 update không tạo trùng', $inst['vps_status'] === 'off', $inst['vps_status']);
ok('update giữ nguyên username', $inst['username'] === 'root');
ok('update giữ nguyên password', $inst['password'] === 'Pass@123');
ok('userInstances chứa VPS', count(array_filter(XvpsService::userInstances($uid), fn($x) => $x['vps_id'] === 'TEST-VPS-1')) === 1);
ok('findInstance của user khác → null', XvpsService::findInstance($uid + 99999, 'TEST-VPS-1') === null);

// ------------------------------------------------------------------
section('11. Thống kê');
// ------------------------------------------------------------------
$stats = XvpsService::stats();
ok('stats có đủ khoá', isset($stats['orders'], $stats['active'], $stats['pending'], $stats['products'], $stats['revenue']));
ok('stats orders là số', is_int($stats['orders']));
ok('stats revenue là số', is_float($stats['revenue']) || is_int($stats['revenue']));

// ------------------------------------------------------------------
section('12. Bảo mật — không lộ secret ra view');
// ------------------------------------------------------------------
$viewAdmin = file_get_contents(BASE_PATH . '/app/Views/admin/vps.php');
ok('view admin không in secret dạng rõ', !str_contains($viewAdmin, "<?= XvpsService::apiSecret()"), 'có thể lộ secret');
ok('view admin chỉ hiện trạng thái có secret', str_contains($viewAdmin, 'hasSecret'));

// ------------------------------------------------------------------
section('13. Route & controller tồn tại');
// ------------------------------------------------------------------
$routes = file_get_contents(BASE_PATH . '/public/index.php');
foreach ([
    "'/vps',",
    "'VpsController@index'",
    "/vps/order/{id}",
    "/vps/manage",
    "/vps/manage/{vpsId}",
    "/vps/quote",
    "/vps/submit",
    "/vps/action",
    "'VpsController@admin'",
    "'VpsController@adminSave'",
    "'VpsController@adminSync'",
    "'VpsController@adminBalance'",
    "'VpsController@adminInstanceAction'",
] as $needle) {
    ok("route chứa $needle", str_contains($routes, $needle));
}

foreach (['index', 'order', 'manage', 'control', 'quote', 'submit', 'action', 'sync',
          'admin', 'adminSave', 'adminSync', 'adminBalance', 'adminInstanceAction'] as $m) {
    ok("VpsController::$m() tồn tại", method_exists('App\Controllers\VpsController', $m));
}

foreach (['services/vps', 'services/vps-order', 'services/vps-detail', 'services/vps-manage', 'admin/vps'] as $v) {
    ok("view $v tồn tại", is_file(BASE_PATH . '/app/Views/' . $v . '.php'));
}

ok('admin nav có mục VPS', str_contains(
    file_get_contents(BASE_PATH . '/app/Views/layouts/admin.php'),
    '/admin/vps'
));

// ------------------------------------------------------------------
section('14. Dọn dẹp dữ liệu test');
// ------------------------------------------------------------------
Database::query("DELETE FROM vps_products WHERE product_id LIKE 'TEST-%'");
Database::query("DELETE FROM vps_operating_systems WHERE os_id LIKE 'TEST-%'");
Database::query("DELETE FROM vps_orders WHERE idempotency_key LIKE 'TEST-%'");
Database::query("DELETE FROM vps_instances WHERE vps_id LIKE 'TEST-%'");
ok('dữ liệu test đã được xoá', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
