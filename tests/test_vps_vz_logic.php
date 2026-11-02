<?php
/**
 * Test logic dịch vụ VPS StoreVPS (Virtualizor API).
 * Chạy: php tests/test_vps_vz_logic.php
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

echo "=== TEST LOGIC DỊCH VỤ VPS STOREVPS (Virtualizor API) ===\n";

// ------------------------------------------------------------------
section('1. Cấu hình & bật/tắt dịch vụ');
// ------------------------------------------------------------------
setting_set('vps_vz_service_on', '1', 'XVPS Virtualizor');
ok('enabled() đúng khi bật', VirtualizorService::enabled() === true);

setting_set('vps_vz_service_on', '0', 'XVPS Virtualizor');
ok('enabled() đúng khi tắt', VirtualizorService::enabled() === false);
setting_set('vps_vz_service_on', '1', 'XVPS Virtualizor');

setting_set('vps_vz_base_url', '', 'XVPS Virtualizor');
ok('base_url mặc định là api.xvps.vn', str_contains(VirtualizorService::baseUrl(), 'api.xvps.vn'), VirtualizorService::baseUrl());

setting_set('vps_vz_base_url', 'http://127.0.0.1:8789/', 'XVPS Virtualizor');
ok('base_url bỏ dấu / cuối', VirtualizorService::baseUrl() === 'http://127.0.0.1:8789', VirtualizorService::baseUrl());

setting_set('vps_vz_sandbox', '1', 'XVPS Virtualizor');
ok('sandbox() đúng khi bật', VirtualizorService::sandbox() === true);
setting_set('vps_vz_sandbox', '0', 'XVPS Virtualizor');

// ------------------------------------------------------------------
section('2. Mã hoá / giải mã api-secret');
// ------------------------------------------------------------------
VirtualizorService::saveCredentials('vz_user', 'vz_app', 'vz_secret_123');
ok('api-username lưu đúng', VirtualizorService::apiUsername() === 'vz_user', VirtualizorService::apiUsername());
ok('api-app lưu đúng', VirtualizorService::apiApp() === 'vz_app', VirtualizorService::apiApp());
ok('api-secret giải mã đúng', VirtualizorService::apiSecret() === 'vz_secret_123', VirtualizorService::apiSecret());

$rawSecret = (string)setting('vps_vz_api_secret', '');
ok('api-secret được mã hoá trong DB', $rawSecret !== 'vz_secret_123' && $rawSecret !== '', 'giá trị thô: ' . $rawSecret);
ok('configured() true khi đủ 3 thông tin', VirtualizorService::configured() === true);

VirtualizorService::saveCredentials('', '', '');
ok('configured() false khi thiếu thông tin', VirtualizorService::configured() === false);

VirtualizorService::saveCredentials('vz_user', 'vz_app', 'vz_secret_123');

// ------------------------------------------------------------------
section('3. Biên lợi nhuận & tính giá');
// ------------------------------------------------------------------
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
ok('markup 0 → giá nguyên bản', VirtualizorService::markup() === 0.0);
ok('sellPrice 100000 không markup = 100000', VirtualizorService::sellPrice(100000.0) === 100000.0);

setting_set('vps_vz_markup', '10', 'XVPS Virtualizor');
ok('markup 10 được đọc', VirtualizorService::markup() === 10.0);
ok('sellPrice 100000 + 10% = 110000', VirtualizorService::sellPrice(100000.0) === 110000.0, (string)VirtualizorService::sellPrice(100000.0));
ok('sellPrice làm tròn ceil', VirtualizorService::sellPrice(1001.0) === 1102.0, (string)VirtualizorService::sellPrice(1001.0));

setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
ok('VAT luôn bằng 0', VirtualizorService::vatPercent() === 0.0);

$product = [
    'price_monthly'       => 120000.0,
    'price_quarterly'     => 340000.0,
    'price_semi_annually' => 640000.0,
    'price_annually'      => 1200000.0,
    'pricing_json'        => json_encode([
        'cores' => ['monthly' => ['amount' => 60000]],
        'ram'   => ['monthly' => ['amount' => 45000]],
        'disk'  => ['monthly' => ['amount' => 30000]],
    ]),
];

ok('cyclePrice monthly', VirtualizorService::cyclePrice($product, 'monthly') === 120000.0);
ok('cyclePrice quarterly', VirtualizorService::cyclePrice($product, 'quarterly') === 340000.0);
ok('cyclePrice semi_annually', VirtualizorService::cyclePrice($product, 'semi_annually') === 640000.0);
ok('cyclePrice annually', VirtualizorService::cyclePrice($product, 'annually') === 1200000.0);
ok('cyclePrice yearly → annually', VirtualizorService::cyclePrice($product, 'yearly') === 1200000.0);

$calc = VirtualizorService::calcTotal($product, 'monthly', 1);
ok('calcTotal 1 máy tháng = 120000', $calc['total'] === 120000.0, (string)$calc['total']);
ok('calcTotal VAT = 0', $calc['vat'] === 0.0);
ok('calcTotal item = 120000', $calc['item'] === 120000.0);

$calc = VirtualizorService::calcTotal($product, 'monthly', 3);
ok('calcTotal 3 máy = 360000', $calc['total'] === 360000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 1, ['cores' => 1]);
ok('calcTotal +1 core (60000) = 180000', $calc['total'] === 180000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 1, ['ram' => 2, 'disk' => 1]);
ok('calcTotal +2RAM +1disk = 120000+90000+30000 = 240000', $calc['total'] === 240000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 1, [], 20000);
ok('calcTotal +phí DC 20000 = 140000', $calc['total'] === 140000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 2, ['cores' => 1], 20000);
ok('calcTotal 2 máy +1core +DC = 2*(120000+60000+20000) = 400000', $calc['total'] === 400000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'quarterly', 1);
ok('calcTotal quý = 340000', $calc['total'] === 340000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 0);
ok('quantity tối thiểu = 1', $calc['quantity'] === 1, (string)$calc['quantity']);

// ------------------------------------------------------------------
section('4. Quy đổi trạng thái');
// ------------------------------------------------------------------
ok('mapStatus running → completed', VirtualizorService::mapStatus('running') === VirtualizorService::STATUS_COMPLETED);
ok('mapStatus stopped → completed', VirtualizorService::mapStatus('stopped') === VirtualizorService::STATUS_COMPLETED);
ok('mapStatus creating → processing', VirtualizorService::mapStatus('creating') === VirtualizorService::STATUS_PROCESSING);
ok('mapStatus rebuilding → processing', VirtualizorService::mapStatus('rebuilding') === VirtualizorService::STATUS_PROCESSING);
ok('mapStatus expired → failed', VirtualizorService::mapStatus('expired') === VirtualizorService::STATUS_FAILED);
ok('mapStatus suspended → failed', VirtualizorService::mapStatus('suspended') === VirtualizorService::STATUS_FAILED);
ok('mapStatus cancelled → failed', VirtualizorService::mapStatus('cancelled') === VirtualizorService::STATUS_FAILED);

ok('isOperable running = true', VirtualizorService::isOperable('running') === true);
ok('isOperable creating = false', VirtualizorService::isOperable('creating') === false);
ok('isOperable expired = false', VirtualizorService::isOperable('expired') === false);

ok('statusLabel pending', VirtualizorService::statusLabel(VirtualizorService::STATUS_PENDING) === 'Đang chờ');
ok('statusBadge completed', VirtualizorService::statusBadge(VirtualizorService::STATUS_COMPLETED) === 'success');
ok('vpsStatusLabel running', VirtualizorService::vpsStatusLabel('running') === 'Đang chạy');
ok('vpsStatusLabel stopped', VirtualizorService::vpsStatusLabel('stopped') === 'Đã tắt');
ok('vpsStatusBadge expired', VirtualizorService::vpsStatusBadge('expired') === 'danger');

// ------------------------------------------------------------------
section('5. Chu kỳ & hành động hợp lệ');
// ------------------------------------------------------------------
ok('có 4 chu kỳ', count(VirtualizorService::CYCLES) === 4);
ok('cycleKey monthly', VirtualizorService::cycleKey('monthly') === 'monthly');
ok('cycleKey semi_annually', VirtualizorService::cycleKey('semi_annually') === 'semi_annually');
ok('cycleKey không xác định → monthly', VirtualizorService::cycleKey('xxx') === 'monthly');

ok('có 4 hành động nguồn', count(VirtualizorService::POWER_ACTIONS) === 4);
ok('action start hợp lệ', isset(VirtualizorService::POWER_ACTIONS['start']));
ok('action poweroff hợp lệ', isset(VirtualizorService::POWER_ACTIONS['poweroff']));
ok('action delete không hợp lệ', !isset(VirtualizorService::POWER_ACTIONS['delete']));

ok('có 6 loại addon', count(VirtualizorService::ADDON_TYPES) === 6);
ok('addon cores → cpu', VirtualizorService::ADDON_REQUEST_KEY['cores'] === 'cpu');
ok('addon ipv4 → ip', VirtualizorService::ADDON_REQUEST_KEY['ipv4'] === 'ip');
ok('addon ipv6 → ipv6', VirtualizorService::ADDON_REQUEST_KEY['ipv6'] === 'ipv6');

// ------------------------------------------------------------------
section('6. Validate hostname & password');
// ------------------------------------------------------------------
$ctrl = new ReflectionClass(App\Controllers\VpsStorevpsController::class);

ok('hostname hợp lệ: demo-host', App\Controllers\VpsStorevpsController::isValidHostname('demo-host'));
ok('hostname hợp lệ: my.server_01', App\Controllers\VpsStorevpsController::isValidHostname('my.server_01'));
ok('hostname quá ngắn bị từ chối', !App\Controllers\VpsStorevpsController::isValidHostname('ab'));
ok('hostname có chữ hoa bị từ chối', !App\Controllers\VpsStorevpsController::isValidHostname('DemoHost'));
ok('hostname có khoảng trắng bị từ chối', !App\Controllers\VpsStorevpsController::isValidHostname('demo host'));
ok('hostname bắt đầu bằng dấu gạch bị từ chối', !App\Controllers\VpsStorevpsController::isValidHostname('-demo'));
ok('hostname dài quá 63 bị từ chối', !App\Controllers\VpsStorevpsController::isValidHostname(str_repeat('a', 64)));

ok('password hợp lệ: Abcdef12345', App\Controllers\VpsStorevpsController::isValidPassword('Abcdef12345'));
ok('password ngắn bị từ chối', !App\Controllers\VpsStorevpsController::isValidPassword('Abc12'));
ok('password chỉ 1 nhóm bị từ chối', !App\Controllers\VpsStorevpsController::isValidPassword('abcdefghij'));
ok('password có khoảng trắng bị từ chối', !App\Controllers\VpsStorevpsController::isValidPassword('Abc def 123'));
ok('password có ký tự nguy hiểm bị từ chối', !App\Controllers\VpsStorevpsController::isValidPassword('Abcdef12345<>'));

// ------------------------------------------------------------------
section('7. Chuẩn hoá product từ API');
// ------------------------------------------------------------------
$raw = [
    'product_id'  => 'vz-101',
    'name'        => 'STARTER (1-2-25)',
    'display_name'=> 'STARTER',
    'description' => 'Gói khởi đầu',
    'type_vps'    => 'kvm',
    'cpu'         => 1,
    'ram'         => 2048,
    'disk'        => 25,
    'bandwidth'   => 1000,
    'network_speed' => 1000,
    'upload_speed' => 1000,
    'ipv4'        => 1,
    'ipv6'        => 1,
    'ipv6_subnet' => 64,
    'pricing'     => [
        'monthly'  => ['amount' => 120000],
        'annually' => ['amount' => 1200000],
    ],
    'locations'   => [
        ['location_id' => 'loc-hcm', 'code' => 'HCM', 'name' => 'Hồ Chí Minh', 'surcharge' => 0],
        ['location_id' => 'loc-sg',  'code' => 'SG',  'name' => 'Singapore',    'surcharge' => 20000],
    ],
];

$norm = VirtualizorService::normalizeProduct($raw, 'VPS StoreVPS Basic', false);
ok('product_id giữ dạng chuỗi', $norm['product_id'] === 'vz-101');
ok('group_name lấy đúng', $norm['group_name'] === 'VPS StoreVPS Basic');
ok('name lấy đúng', $norm['name'] === 'STARTER (1-2-25)');
ok('cpu ép về int', $norm['cpu'] === 1);
ok('ram ép về int', $norm['ram'] === 2048);
ok('price_monthly đúng', $norm['price_monthly'] === 120000.0);
ok('price_annually đúng', $norm['price_annually'] === 1200000.0);
ok('price_quarterly mặc định 0', $norm['price_quarterly'] === 0.0);
ok('is_addon = false', $norm['is_addon'] === false);
ok('locations_json là JSON hợp lệ', is_array(json_decode($norm['locations_json'], true)));
ok('locations_json có 2 location', count(json_decode($norm['locations_json'], true)) === 2);

// ------------------------------------------------------------------
section('8. Vận hành khi chưa cấu hình API');
// ------------------------------------------------------------------
VirtualizorService::saveCredentials('', '', '');
$res = VirtualizorService::request('GET', '/api/virt/get-info');
ok('request khi chưa cấu hình → ok=false', $res['ok'] === false);
ok('thông báo hướng dẫn cấu hình', str_contains($res['message'], 'cấu hình'), $res['message']);

ok('info() khi chưa cấu hình → ok=false', VirtualizorService::info()['ok'] === false);
ok('products() khi chưa cấu hình → rỗng', VirtualizorService::products() === ['vps' => [], 'addons' => []]);
ok('operatingSystems() khi chưa cấu hình → rỗng', VirtualizorService::operatingSystems() === []);

VirtualizorService::saveCredentials('vz_user', 'vz_app', 'vz_secret_123');

// ------------------------------------------------------------------
section('9. Idempotency tạo đơn');
// ------------------------------------------------------------------
$uid = (int)Database::fetchColumn('SELECT id FROM users ORDER BY id LIMIT 1');
$key = 'VZ-LOGIC-' . uniqid();

$id1 = VirtualizorService::createOrderRow($uid, [
    'product_id'      => 'vz-idem-test',
    'product_name'    => 'Idem Test',
    'billing_cycle'   => 'monthly',
    'os_id'           => '1001',
    'os_name'         => 'Ubuntu 22.04 LTS',
    'quantity'        => 1,
    'price'           => 120000,
    'amount'          => 120000,
    'status'          => VirtualizorService::STATUS_PENDING,
    'idempotency_key' => $key,
]);
ok('tạo đơn lần 1', $id1 > 0);

$id2 = VirtualizorService::createOrderRow($uid, [
    'product_id'      => 'vz-idem-test',
    'product_name'    => 'Idem Test',
    'billing_cycle'   => 'monthly',
    'os_id'           => '1001',
    'quantity'        => 1,
    'price'           => 120000,
    'amount'          => 120000,
    'status'          => VirtualizorService::STATUS_PENDING,
    'idempotency_key' => $key,
]);
ok('tạo đơn lần 2 trả về cùng id', $id1 === $id2, "$id1 vs $id2");
ok('chỉ có 1 bản ghi đơn', (int)Database::fetchColumn(
    'SELECT COUNT(*) FROM vps_vz_orders WHERE idempotency_key = ?',
    [$key]
) === 1);

$order = VirtualizorService::findOrder($uid, $id1);
ok('findOrder trả về đơn', $order !== null);
ok('findOrder của user khác → null', VirtualizorService::findOrder($uid + 999999, $id1) === null);

Database::query('DELETE FROM vps_vz_orders WHERE idempotency_key = ?', [$key]);

// ------------------------------------------------------------------
section('10. Log thao tác & lịch sử');
// ------------------------------------------------------------------
VirtualizorService::logAction($uid, 'VZ-LOGIC-1', 'start', 'success', 'VPS đã bật', ['x' => 1]);
$hist = VirtualizorService::actionHistory('VZ-LOGIC-1');
ok('ghi và đọc lịch sử thao tác', count($hist) === 1, (string)count($hist));
ok('lịch sử lưu đúng action', $hist[0]['action'] === 'start');
ok('lịch sử lưu đúng status', $hist[0]['status'] === 'success');
ok('lịch sử lưu meta JSON', json_decode((string)$hist[0]['meta'], true)['x'] === 1);

Database::query("DELETE FROM vps_vz_actions WHERE vps_id = 'VZ-LOGIC-1'");

// ------------------------------------------------------------------
section('11. Bảng DB & ENUM ví');
// ------------------------------------------------------------------
foreach ([
    'vps_vz_products',
    'vps_vz_operating_systems',
    'vps_vz_locations',
    'vps_vz_orders',
    'vps_vz_instances',
    'vps_vz_actions',
] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    ok("bảng $t tồn tại", $n === 1);
}

$enum = (string)Database::fetchColumn(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallet_transactions' AND COLUMN_NAME = 'type'"
);
foreach (['vps_vz_order', 'vps_vz_refund', 'vps_vz_renew', 'vps_vz_addon'] as $t) {
    ok("ENUM ví chứa $t", str_contains($enum, "'$t'"), $enum);
}

// Không đụng bảng của module VMware (V12)
foreach (['vps_products', 'vps_orders', 'vps_instances'] as $t) {
    $n = (int)Database::fetchColumn(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$t]
    );
    ok("bảng VMware $t vẫn còn nguyên", $n === 1);
}

// ------------------------------------------------------------------
section('12. Dọn dẹp');
// ------------------------------------------------------------------
VirtualizorService::saveCredentials('', '', '');
VirtualizorService::clearToken();
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
setting_set('vps_vz_base_url', 'https://api.xvps.vn', 'XVPS Virtualizor');
setting_set('vps_vz_service_on', '1', 'XVPS Virtualizor');
ok('dọn dẹp hoàn tất', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
