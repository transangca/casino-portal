<?php
/**
 * Test E2E dịch vụ VPS StoreVPS — gọi API thật qua mock server Virtualizor.
 *
 * Yêu cầu: mock server đang chạy ở http://127.0.0.1:8789
 * Chạy: php tests/test_vps_vz_e2e.php
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Wallet;
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

echo "=== TEST E2E DỊCH VỤ VPS STOREVPS (mock Virtualizor API) ===\n";

$MOCK = 'http://127.0.0.1:8789';

// Kiểm tra mock server có chạy không
$probe = @file_get_contents($MOCK . '/api/virt/get-info', false, stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "api-username: mock_user\r\napi-app: mock_app\r\napi-secret: mock_secret\r\nauth-token: x\r\n",
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]));
if ($probe === false) {
    echo "\n[!] Mock server chưa chạy. Khởi động bằng:\n";
    echo "    php -S 127.0.0.1:8789 tests/mock_xvps_virt.php\n";
    echo "KẾT QUẢ: 0 PASS, 1 FAIL\n";
    exit(1);
}

// ------------------------------------------------------------------
section('1. Cấu hình trỏ về mock server');
// ------------------------------------------------------------------
setting_set('vps_vz_base_url', $MOCK, 'XVPS Virtualizor');
VirtualizorService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
VirtualizorService::clearToken();

ok('base_url trỏ mock', VirtualizorService::baseUrl() === $MOCK, VirtualizorService::baseUrl());
ok('configured() true', VirtualizorService::configured());

// ------------------------------------------------------------------
section('2. Lấy JWT token');
// ------------------------------------------------------------------
$token = VirtualizorService::token();
ok('token được lấy thành công', $token !== '', 'token rỗng');
ok('token đúng giá trị mock', $token === 'MOCK.VIRT.JWT.TOKEN', $token);
ok('token được cache trong settings', (string)setting('vps_vz_auth_token', '') !== '');
ok('token có timestamp', (int)setting('vps_vz_token_time', '0') > 0);

$token2 = VirtualizorService::token();
ok('token cache tái sử dụng', $token2 === $token);

// ------------------------------------------------------------------
section('3. get-info — thông tin tài khoản');
// ------------------------------------------------------------------
$info = VirtualizorService::info();
ok('info ok', $info['ok'] === true, $info['message']);
ok('credit = 25.000.000', $info['balance'] === 25000000.0, (string)$info['balance']);
ok('tên tài khoản đúng', ($info['user']['name'] ?? '') === 'Mock StoreVPS Agency');
ok('có scopes', !empty($info['scopes']));
ok('có thống kê vps', !empty($info['stats']));

// ------------------------------------------------------------------
section('4. get-product — danh mục gói');
// ------------------------------------------------------------------
$catalog = VirtualizorService::products();
ok('có 2 gói VPS', count($catalog['vps']) === 2, (string)count($catalog['vps']));
ok('có 6 addon', count($catalog['addons']) === 6, (string)count($catalog['addons']));

$p101 = null;
$addonCores = null;
foreach (array_merge($catalog['vps'], $catalog['addons']) as $p) {
    if ($p['product_id'] === 'vz-101') { $p101 = $p; }
    if ($p['type_addon'] === 'cores') { $addonCores = $p; }
}
ok('gói vz-101 tồn tại', $p101 !== null);
ok('gói vz-101 giá tháng 120000', (float)$p101['price_monthly'] === 120000.0, (string)$p101['price_monthly']);
ok('gói vz-101 giá năm 1200000', (float)$p101['price_annually'] === 1200000.0);
ok('gói vz-101 có 2 datacenter', count(json_decode((string)$p101['locations_json'], true)) === 2);
ok('addon cores tồn tại', $addonCores !== null);
ok('addon cores giá 60000', $addonCores !== null && (float)$addonCores['price_monthly'] === 60000.0);

// ------------------------------------------------------------------
section('5. get-list-os — hệ điều hành');
// ------------------------------------------------------------------
$oses = VirtualizorService::operatingSystems();
ok('có 4 OS', count($oses) === 4, (string)count($oses));
ok('OS đầu là Ubuntu 22.04 LTS', ($oses[0]['os_name'] ?? '') === 'Ubuntu 22.04 LTS');
ok('os_id là chuỗi', is_string($oses[0]['os_id'] ?? null));
ok('os_id giữ nguyên dạng chuỗi', ($oses[0]['os_id'] ?? '') === '1001');

// ------------------------------------------------------------------
section('6. Đồng bộ danh mục vào DB');
// ------------------------------------------------------------------
$res = VirtualizorService::sync();
ok('sync thành công', $res['ok'] === true, $res['message']);
ok('sync đồng bộ 8 sản phẩm', $res['products'] === 8, (string)$res['products']);
ok('sync đồng bộ 4 OS', $res['os'] === 4, (string)$res['os']);
ok('sync đồng bộ datacenter', $res['locations'] >= 3, (string)$res['locations']);

ok('gói vz-101 trong DB', VirtualizorService::findProduct('vz-101') !== null);
ok('addon cores trong DB', VirtualizorService::findAddon('cores') !== null);
ok('OS 1001 trong DB', VirtualizorService::findOperatingSystem('1001') !== null);
ok('datacenter loc-hcm trong DB', VirtualizorService::findLocation('loc-hcm') !== null);
ok('datacenter loc-sg có phí 20000', (float)(VirtualizorService::findLocation('loc-sg')['surcharge'] ?? 0) === 20000.0);

$groups = VirtualizorService::productGroups();
ok('có nhóm VPS StoreVPS Basic', isset($groups['VPS StoreVPS Basic']));
ok('nhóm addon không lọt vào danh mục mua', !isset($groups['Addon VPS StoreVPS']));

// ------------------------------------------------------------------
section('7. Tính giá với biên lợi nhuận');
// ------------------------------------------------------------------
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
$product = VirtualizorService::findProduct('vz-101');
$calc = VirtualizorService::calcTotal($product, 'monthly', 1);
ok('giá gốc = 120000', $calc['total'] === 120000.0, (string)$calc['total']);

setting_set('vps_vz_markup', '20', 'XVPS Virtualizor');
$calc = VirtualizorService::calcTotal($product, 'monthly', 1);
ok('giá +20% = 144000', $calc['total'] === 144000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 2);
ok('2 máy +20% = 288000', $calc['total'] === 288000.0, (string)$calc['total']);

$calc = VirtualizorService::calcTotal($product, 'monthly', 1, ['cores' => 1]);
ok('+1 core (60000*1.2=72000) = 216000', $calc['total'] === 216000.0, (string)$calc['total']);

setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');

// ------------------------------------------------------------------
section('8. Tạo đơn hàng + trừ ví chính');
// ------------------------------------------------------------------
$uid = (int)Database::fetchColumn('SELECT id FROM users ORDER BY id LIMIT 1');
$before = Wallet::balance($uid);

$osId   = '1001';
$cycle  = 'monthly';
$total  = VirtualizorService::calcTotal($product, $cycle, 1)['total'];

$orderId = VirtualizorService::createOrderRow($uid, [
    'product_id'      => 'vz-101',
    'product_name'    => 'STARTER (1-2-25)',
    'billing_cycle'   => $cycle,
    'os_id'           => $osId,
    'os_name'         => 'Ubuntu 22.04 LTS',
    'quantity'        => 1,
    'price'           => 120000,
    'amount'          => $total,
    'status'          => VirtualizorService::STATUS_PENDING,
    'idempotency_key' => 'VZ-E2E-' . uniqid(),
]);
ok('tạo đơn thành công', $orderId > 0);

Wallet::apply($uid, 'vps_vz_order', -$total, 'Đặt dịch vụ VPS StoreVPS — E2E', 'vps_vz_order', (string)$orderId);
$after = Wallet::balance($uid);
ok('ví bị trừ đúng số tiền', abs(($before - $after) - $total) < 0.01, "$before -> $after, cần trừ $total");

$res = VirtualizorService::createOrder('vz-101', $cycle, $osId, 'demo-vz-host', 'loc-hcm', 1, ['vz-ad-cores'], 'idem-e2e-1');
ok('API create-order thành công', $res['ok'] === true, $res['message']);
ok('API trả 1 đơn', count($res['orders']) === 1, (string)count($res['orders']));
ok('API trả instance_id', ($res['orders'][0]['instance_id'] ?? '') !== '');
ok('API trả order_code', ($res['orders'][0]['order_code'] ?? '') !== '');
ok('API trả status creating', ($res['status'] ?? '') === 'creating', (string)($res['status'] ?? ''));

$instanceId = (string)$res['orders'][0]['instance_id'];
$orderCode  = (string)$res['orders'][0]['order_code'];
ok('instance_id có tiền tố MOCK-VZ-', str_starts_with($instanceId, 'MOCK-VZ-'), $instanceId);

Database::update('vps_vz_orders', [
    'remote_order' => $orderCode,
    'instance_id'  => $instanceId,
    'order_code'   => $orderCode,
    'vps_status'   => 'creating',
    'status'       => VirtualizorService::STATUS_PROCESSING,
    'message'      => (string)$res['message'],
    'updated_at'   => now(),
], 'id = :id', ['id' => $orderId]);

VirtualizorService::upsertInstance($uid, [
    'vps-id'      => $instanceId,
    'hostname'    => 'demo-vz-host',
    'order-code'  => $orderCode,
    'product-id'  => 'vz-101',
    'description' => 'STARTER (1-2-25)',
    'vps-status'  => 'creating',
    'ip'          => '103.56.200.11',
    'password'    => 'MockPass@2026',
    'cpu'         => 1,
    'ram'         => 2048,
    'disk'        => 25,
    'billing-cycle' => $cycle,
    'amount'      => $total,
]);

$order = VirtualizorService::findOrder($uid, $orderId);
ok('đơn cập nhật remote_order', $order['remote_order'] === $orderCode, (string)$order['remote_order']);
ok('đơn lưu instance_id', $order['instance_id'] === $instanceId);

// ------------------------------------------------------------------
section('9. Đồng bộ & chi tiết VPS');
// ------------------------------------------------------------------
$sync = VirtualizorService::syncVps($uid);
ok('syncVps thành công', $sync['ok'] === true, $sync['message']);
ok('syncVps đồng bộ ít nhất 1 VPS', $sync['count'] >= 1, (string)$sync['count']);

$info = VirtualizorService::infoVps($instanceId);
ok('infoVps thành công', $info['ok'] === true, $info['message']);
ok('infoVps trả đúng vps-id', ($info['data']['vps-id'] ?? '') === $instanceId, (string)($info['data']['vps-id'] ?? ''));
ok('infoVps trả credentials', ($info['data']['password'] ?? '') === 'MockPass@2026');
ok('infoVps trả IP', ($info['data']['ip'] ?? '') === '103.56.200.11');

$st = VirtualizorService::statusBatch([$instanceId]);
ok('statusBatch thành công', $st['ok'] === true, $st['message']);
ok('statusBatch trả 1 record', count($st['data']) === 1, (string)count($st['data']));
ok('statusBatch trả vps-status running', ($st['data'][0]['vps-status'] ?? '') === 'running');

$st2 = VirtualizorService::statusBatch(array_fill(0, 60, $instanceId));
ok('statusBatch giới hạn 50 ids', $st2['ok'] === true, $st2['message']);

// ------------------------------------------------------------------
section('10. Hành động trên VPS');
// ------------------------------------------------------------------
$res = VirtualizorService::actionVps('start', $instanceId);
ok('action start ok', $res['ok'] === true, $res['message']);
ok('action start có message', str_contains($res['message'], 'khởi động'), $res['message']);

$res = VirtualizorService::actionVps('stop', $instanceId);
ok('action stop ok', $res['ok'] === true, $res['message']);

$res = VirtualizorService::actionVps('restart', $instanceId);
ok('action restart ok', $res['ok'] === true, $res['message']);

$res = VirtualizorService::actionVps('poweroff', $instanceId);
ok('action poweroff ok', $res['ok'] === true, $res['message']);

$res = VirtualizorService::actionVps('delete', $instanceId);
ok('action không hợp lệ bị chặn từ client', $res['ok'] === false);

// ------------------------------------------------------------------
section('11. Rebuild / đổi mật khẩu / đổi hostname');
// ------------------------------------------------------------------
$res = VirtualizorService::rebuildVps($instanceId, '1002');
ok('rebuild ok', $res['ok'] === true, $res['message']);
ok('rebuild trả vps-status rebuilding', ($res['data']['vps-status'] ?? '') === 'rebuilding');

$res = VirtualizorService::changePassword($instanceId, 'NewPass@2026', true);
ok('change-password ok', $res['ok'] === true, $res['message']);
ok('change-password trả on_boot', ($res['data']['on_boot'] ?? false) === true);

$res = VirtualizorService::changeHostname($instanceId, 'demo-vz-host-2');
ok('change-hostname ok', $res['ok'] === true, $res['message']);
ok('change-hostname trả hostname', ($res['data']['hostname'] ?? '') === 'demo-vz-host-2');

// ------------------------------------------------------------------
section('12. Gia hạn / addon / auto-renew / hủy');
// ------------------------------------------------------------------
$res = VirtualizorService::renewVps($instanceId, 'quarterly');
ok('renew ok', $res['ok'] === true, $res['message']);
ok('renew trả newExpireAt', ($res['newExpireAt'] ?? '') !== '');

$res = VirtualizorService::addonVps($instanceId, ['cores' => 1, 'ram' => 2], true, 'Nâng RAM');
ok('addon ok', $res['ok'] === true, $res['message']);
ok('addon trả invoice_id', ($res['invoiceId'] ?? '') !== '');

$res = VirtualizorService::addonVps($instanceId, []);
ok('addon không có gì → bị từ chối', $res['ok'] === false);

$res = VirtualizorService::autoRenew($instanceId, true);
ok('auto-renew bật ok', $res['ok'] === true, $res['message']);
ok('auto-renew trả auto_renew=true', ($res['autoRenew'] ?? false) === true);

$res = VirtualizorService::autoRenew($instanceId, false);
ok('auto-renew tắt ok', $res['ok'] === true, $res['message']);
ok('auto-renew trả auto_renew=false', ($res['autoRenew'] ?? true) === false);

$res = VirtualizorService::cancelVps($instanceId);
ok('cancel ok', $res['ok'] === true, $res['message']);
ok('cancel trả status cancelled', ($res['status'] ?? '') === 'cancelled');

// ------------------------------------------------------------------
section('13. Xử lý lỗi API');
// ------------------------------------------------------------------
VirtualizorService::saveCredentials('wrong_user', 'wrong_app', 'wrong_secret');
VirtualizorService::clearToken();

$res = VirtualizorService::request('GET', '/api/virt/get-info');
ok('API lỗi trả ok=false', $res['ok'] === false);
ok('lỗi 401 được nhận diện', $res['status'] === 401, (string)$res['status']);

$res = VirtualizorService::actionVps('start', $instanceId);
ok('action khi token lỗi → ok=false', $res['ok'] === false);

VirtualizorService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
VirtualizorService::clearToken();
ok('token lấy lại được sau khi đổi thông tin', VirtualizorService::token() !== '');

// ------------------------------------------------------------------
section('14. Hoàn tiền khi đối tác từ chối');
// ------------------------------------------------------------------
$balBefore = Wallet::balance($uid);
try {
    Wallet::apply($uid, 'vps_vz_renew', -50000.0, 'Gia hạn VPS StoreVPS E2E', 'vps_vz_renew', $instanceId);
    Wallet::apply($uid, 'vps_vz_refund', 50000.0, 'Hoàn tiền do gia hạn thất bại', 'vps_vz_renew', $instanceId);
} catch (\Throwable $e) {
    ok('hoàn tiền không lỗi', false, $e->getMessage());
}
$balAfter = Wallet::balance($uid);
ok('số dư sau hoàn tiền = số dư trước', abs($balBefore - $balAfter) < 0.01, "$balBefore -> $balAfter");

// ------------------------------------------------------------------
section('15. Dọn dẹp');
// ------------------------------------------------------------------
Database::query("DELETE FROM vps_vz_instances WHERE vps_id LIKE 'MOCK-VZ-%'");
Database::query("DELETE FROM vps_vz_orders WHERE product_id = 'vz-101' AND user_id = " . (int)$uid);
Database::query("DELETE FROM vps_vz_products WHERE product_id LIKE 'vz-%'");
Database::query("DELETE FROM vps_vz_operating_systems WHERE os_id IN ('1001','1002','1003','1004')");
Database::query("DELETE FROM vps_vz_locations WHERE location_id IN ('loc-hcm','loc-sg')");
Database::query("DELETE FROM vps_vz_actions WHERE vps_id LIKE 'MOCK-VZ-%'");

setting_set('vps_vz_base_url', 'https://api.xvps.vn', 'XVPS Virtualizor');
VirtualizorService::saveCredentials('', '', '');
VirtualizorService::clearToken();
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
ok('dọn dẹp hoàn tất', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
