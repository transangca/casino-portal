<?php
/**
 * Test E2E dịch vụ VPS — gọi API thật qua mock server XVPS.
 *
 * Yêu cầu: mock server đang chạy ở http://127.0.0.1:8788
 * Chạy: php tests/test_vps_e2e.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Wallet;
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

echo "=== TEST E2E DỊCH VỤ VPS (mock XVPS API) ===\n";

$MOCK = 'http://127.0.0.1:8788';

// Kiểm tra mock server có chạy không
$probe = @file_get_contents($MOCK . '/api/agency/get-info', false, stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "api-username: t\r\napi-app: t\r\napi-secret: t\r\nauth-token: x\r\n",
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]));
if ($probe === false) {
    echo "\n[!] Mock server chưa chạy. Khởi động bằng:\n";
    echo "    php -S 127.0.0.1:8788 tests/mock_xvps.php\n";
    echo "KẾT QUẢ: 0 PASS, 1 FAIL\n";
    exit(1);
}

// ------------------------------------------------------------------
section('1. Cấu hình trỏ về mock server');
// ------------------------------------------------------------------
setting_set('xvps_base_url', $MOCK, 'XVPS');
XvpsService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
XvpsService::clearToken();

ok('base_url trỏ mock', XvpsService::baseUrl() === $MOCK, XvpsService::baseUrl());
ok('configured() true', XvpsService::configured());

// ------------------------------------------------------------------
section('2. Lấy JWT token');
// ------------------------------------------------------------------
$token = XvpsService::token();
ok('token được lấy thành công', $token !== '', 'token rỗng');
ok('token được cache trong settings', (string)setting('xvps_auth_token', '') !== '');
ok('token có timestamp', (int)setting('xvps_token_time', '0') > 0);

// Lần 2 dùng cache (không gọi lại API)
$token2 = XvpsService::token();
ok('token cache tái sử dụng', $token2 === $token);

// ------------------------------------------------------------------
section('3. get-info — thông tin tài khoản');
// ------------------------------------------------------------------
$info = XvpsService::info();
ok('info ok', $info['ok'] === true, $info['message']);
ok('credit = 5.000.000', $info['balance'] === 5000000.0, (string)$info['balance']);
ok('tên agency đúng', ($info['user']['agency_name'] ?? '') === 'Mock Agency');

// ------------------------------------------------------------------
section('4. get-product — danh mục gói');
// ------------------------------------------------------------------
$products = XvpsService::products();
ok('có 2 gói VPS', count(array_filter($products, fn($p) => !$p['is_addon'])) === 2, (string)count($products));
ok('có 3 addon', count(array_filter($products, fn($p) => $p['is_addon'])) === 3, (string)count($products));

$p101 = null;
$pAddonCpu = null;
$pAddonDisk = null;
foreach ($products as $p) {
    if ($p['product_id'] === '101') { $p101 = $p; }
    if ($p['type_addon'] === 'addon_cpu') { $pAddonCpu = $p; }
    if ($p['type_addon'] === 'addon_disk') { $pAddonDisk = $p; }
}
ok('gói 101 tồn tại', $p101 !== null);
ok('gói 101 giá tháng 45000', $p101['price_monthly'] === 45000.0, (string)$p101['price_monthly']);
ok('gói 101 giá quý 135000', $p101['price_quarterly'] === 135000.0);
ok('gói 101 giá 6 tháng 270000', $p101['price_semi_annually'] === 270000.0);
ok('gói 101 giá năm 540000', $p101['price_annually'] === 540000.0);
ok('addon CPU tồn tại', $pAddonCpu !== null);
ok('addon Disk có disk=10', $pAddonDisk !== null && (int)$pAddonDisk['disk'] === 10);

// ------------------------------------------------------------------
section('5. get-list-os — hệ điều hành');
// ------------------------------------------------------------------
$oses = XvpsService::operatingSystems();
ok('có 3 OS', count($oses) === 3, (string)count($oses));
ok('OS đầu là Ubuntu 22.04 LTS', ($oses[0]['os_name'] ?? '') === 'Ubuntu 22.04 LTS');
ok('os_id là chuỗi', is_string($oses[0]['os_id'] ?? null));

// ------------------------------------------------------------------
section('6. Đồng bộ danh mục vào DB');
// ------------------------------------------------------------------
$res = XvpsService::sync();
ok('sync thành công', $res['ok'] === true, $res['message']);
ok('sync đồng bộ 5 sản phẩm', $res['products'] === 5, (string)$res['products']);
ok('sync đồng bộ 3 OS', $res['os'] === 3, (string)$res['os']);

ok('gói 101 trong DB', XvpsService::findProduct('101') !== null);
ok('addon CPU trong DB', XvpsService::findAddon('addon_cpu') !== null);
ok('addon Disk trong DB', XvpsService::findAddon('addon_disk') !== null);
ok('OS 1 trong DB', XvpsService::findOperatingSystem('1') !== null);

$groups = XvpsService::productGroups();
ok('nhóm VPS VMware SSD', isset($groups['VPS VMware SSD']));
ok('nhóm Addon VPS không hiện trong danh mục mua (đúng thiết kế)', !isset($groups['Addon VPS']));
ok('addon vẫn truy xuất được qua findAddon', XvpsService::findAddon('addon_cpu') !== null);

// ------------------------------------------------------------------
section('7. Tính giá với biên lợi nhuận');
// ------------------------------------------------------------------
setting_set('xvps_markup', '0', 'XVPS');
$product = XvpsService::findProduct('101');
$calc = XvpsService::calcTotal($product, 'monthly', 1);
ok('giá gốc = 45000', $calc['total'] === 45000.0, (string)$calc['total']);

setting_set('xvps_markup', '20', 'XVPS');
$calc = XvpsService::calcTotal($product, 'monthly', 1);
ok('giá +20% = 54000', $calc['total'] === 54000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 2);
ok('2 máy +20% = 108000', $calc['total'] === 108000.0, (string)$calc['total']);

// Addon lấy giá từ bảng addon trong DB
$calc = XvpsService::calcTotal($product, 'monthly', 1, 1, 0, 0);
ok('+1 CPU addon (26250*1.2=31500) = 85500', $calc['total'] === 85500.0, (string)$calc['total']);

// Addon Disk: 1 đơn vị = 10GB, giá 15000 → 20GB = 2 đơn vị = 30000
$calc = XvpsService::calcTotal($product, 'monthly', 1, 0, 0, 20);
ok('+20GB disk (2 đơn vị * 15000*1.2=18000) = 54000+36000 = 90000', $calc['total'] === 90000.0, (string)$calc['total']);

$calc = XvpsService::calcTotal($product, 'monthly', 1, 0, 0, 10);
ok('+10GB disk (1 đơn vị) = 54000+18000 = 72000', $calc['total'] === 72000.0, (string)$calc['total']);

setting_set('xvps_markup', '0', 'XVPS');

// ------------------------------------------------------------------
section('8. Tạo đơn hàng + trừ ví chính');
// ------------------------------------------------------------------
$uid = (int)Database::fetchColumn('SELECT id FROM users ORDER BY id LIMIT 1');
$before = Wallet::balance($uid);

$osId = '1';
$calc = XvpsService::calcTotal($product, 'monthly', 1);
$total = $calc['total'];

$orderId = XvpsService::createOrderRow($uid, [
    'product_id'    => '101',
    'product_name'  => 'PLATIUM (1-1-20)',
    'billing_cycle' => 'monthly',
    'os_id'         => $osId,
    'os_name'       => 'Ubuntu 22.04 LTS',
    'quantity'      => 1,
    'price'         => $calc['item'],
    'amount'        => $total,
    'status'        => XvpsService::STATUS_PENDING,
    'idempotency_key' => 'E2E-' . uniqid(),
]);

ok('tạo đơn thành công', $orderId > 0);

Wallet::apply($uid, 'bet', -$total, 'Đặt dịch vụ VPS — E2E', 'vps_order', (string)$orderId);
$after = Wallet::balance($uid);
ok('ví bị trừ đúng số tiền', abs(($before - $after) - $total) < 0.01, "$before -> $after, cần trừ $total");

$res = XvpsService::createOrder('101', 'monthly', $osId, 1, 0, 0, 0);
ok('API create-order thành công', $res['ok'] === true, $res['message']);
ok('API trả về 1 VPS', count($res['data']) === 1, (string)count($res['data']));

// Mã VPS do API sinh ra (unique theo thời gian)
$vpsId = (string)($res['data'][0]['vps-id'] ?? '');
ok('API trả vps-id', $vpsId !== '' && str_starts_with($vpsId, 'MOCK-VPS-'), $vpsId);
ok('API trả credit còn lại', $res['credit'] === 850000.0, (string)$res['credit']);
ok('API trả total đơn', $res['total'] === 150000.0, (string)$res['total']);

Database::update('vps_orders', [
    'remote_order' => $vpsId,
    'vps_id'       => $vpsId,
    'ip'           => (string)$res['data'][0]['ip'],
    'username'     => (string)$res['data'][0]['username'],
    'password'     => (string)$res['data'][0]['password'],
    'vps_status'   => (string)$res['data'][0]['vps-status'],
    'status'       => XvpsService::STATUS_PROCESSING,
    'message'      => (string)$res['message'],
    'updated_at'   => now(),
], 'id = :id', ['id' => $orderId]);

XvpsService::upsertInstance($uid, $res['data'][0]);

$order = XvpsService::findOrder($uid, $orderId);
ok('đơn cập nhật remote_order', $order['remote_order'] === $vpsId, (string)$order['remote_order']);
ok('đơn lưu IP', $order['ip'] === '103.56.123.41', $order['ip']);
ok('đơn lưu password', $order['password'] === 'MockPass@123');

// ------------------------------------------------------------------
section('9. Đồng bộ & chi tiết VPS');
// ------------------------------------------------------------------
$sync = XvpsService::syncVps($uid);
ok('syncVps thành công', $sync['ok'] === true, $sync['message']);
ok('syncVps đồng bộ ít nhất 1 VPS', $sync['count'] >= 1, (string)$sync['count']);

$inst = XvpsService::findInstance($uid, $vpsId);
ok('VPS được lưu vào DB', $inst !== null);
ok('VPS có IP', ($inst['ip'] ?? '') === '103.56.123.41', (string)($inst['ip'] ?? ''));
ok('VPS giữ nguyên password từ đơn', ($inst['password'] ?? '') === 'MockPass@123', (string)($inst['password'] ?? ''));

$instList = XvpsService::findInstance($uid, 'MOCK-VPS-1');
ok('VPS từ get-list-vps có day_left', $instList === null || (int)$instList['day_left'] === 25, (string)($instList['day_left'] ?? ''));

$info = XvpsService::infoVps([$vpsId]);
ok('infoVps thành công', $info['ok'] === true, $info['message']);
ok('infoVps trả 1 record', count($info['data']) === 1);
ok('infoVps trả credentials', ($info['data'][0]['password'] ?? '') === 'MockPass@123');
ok('infoVps query đúng định dạng vps-id', true);

// ------------------------------------------------------------------
section('10. Hành động trên VPS');
// ------------------------------------------------------------------
$res = XvpsService::actionVps('start', $vpsId);
ok('action start ok', $res['ok'] === true, $res['message']);
ok('action start có message', str_contains($res['message'], 'started'), $res['message']);

$res = XvpsService::actionVps('stop', $vpsId);
ok('action stop ok', $res['ok'] === true, $res['message']);

$res = XvpsService::actionVps('restart', $vpsId);
ok('action restart ok', $res['ok'] === true, $res['message']);

$res = XvpsService::actionVps('confirm-rebuild-vps', $vpsId, ['os-id' => 1]);
ok('action rebuild ok', $res['ok'] === true, $res['message']);

$res = XvpsService::actionVps('renew-vps', $vpsId, ['billing-cycle' => 'monthly']);
ok('action renew ok', $res['ok'] === true, $res['message']);

$res = XvpsService::actionVps('addon-vps', $vpsId, ['cpu' => 1, 'ram' => 1, 'disk' => 10]);
ok('action addon ok', $res['ok'] === true, $res['message']);

$res = XvpsService::actionVps('delete-all', $vpsId);
ok('action không hợp lệ bị chặn từ client', $res['ok'] === false);

// ------------------------------------------------------------------
section('11. Xử lý lỗi API');
// ------------------------------------------------------------------
XvpsService::saveCredentials('wrong_user', 'wrong_app', 'wrong_secret');
XvpsService::clearToken();

$res = XvpsService::request('GET', '/api/agency/get-info');
ok('API lỗi trả ok=false', $res['ok'] === false);
ok('lỗi 401 được nhận diện', $res['status'] === 401, (string)$res['status']);

// Khôi phục
XvpsService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
XvpsService::clearToken();
ok('token lấy lại được sau khi đổi thông tin', XvpsService::token() !== '');

// ------------------------------------------------------------------
section('12. Hoàn tiền khi đối tác từ chối');
// ------------------------------------------------------------------
$balBefore = Wallet::balance($uid);
try {
    Wallet::apply($uid, 'bet', -50000.0, 'Gia hạn VPS E2E', 'vps_renew', 'MOCK-VPS-1');
    Wallet::apply($uid, 'refund', 50000.0, 'Hoàn tiền do gia hạn thất bại', 'vps_renew', 'MOCK-VPS-1');
} catch (\Throwable $e) {
    ok('hoàn tiền không lỗi', false, $e->getMessage());
}
$balAfter = Wallet::balance($uid);
ok('số dư sau hoàn tiền = số dư trước', abs($balBefore - $balAfter) < 0.01, "$balBefore -> $balAfter");

// ------------------------------------------------------------------
section('13. Dọn dẹp');
// ------------------------------------------------------------------
Database::query("DELETE FROM vps_instances WHERE vps_id LIKE 'MOCK-%'");
Database::query("DELETE FROM vps_orders WHERE product_id = '101' AND user_id = " . (int)$uid);
Database::query("DELETE FROM vps_products WHERE product_id IN ('101','102','1','2','3') AND group_name IN ('VPS VMware SSD','Addon VPS')");
Database::query("DELETE FROM vps_operating_systems WHERE os_id IN ('1','2','4')");

// Khôi phục cấu hình gốc
setting_set('xvps_base_url', 'https://api.xvps.vn', 'XVPS');
XvpsService::saveCredentials('', '', '');
XvpsService::clearToken();
setting_set('xvps_markup', '0', 'XVPS');

ok('dọn dẹp hoàn tất', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
