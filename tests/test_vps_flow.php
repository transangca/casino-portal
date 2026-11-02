<?php
/**
 * Kiểm thử luồng đầy đủ: đặt đơn VPS qua API XVPS mock.
 * Chạy: php tests/test_vps_flow.php
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

echo "=== LUỒNG ĐẶT ĐƠN VPS QUA API XVPS ===\n";

// Chuẩn bị: trỏ API về mock, credentials mock
XvpsService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
Database::query(
    "INSERT INTO settings (name, value) VALUES ('xvps_base_url', 'http://127.0.0.1:8788')
     ON DUPLICATE KEY UPDATE value = 'http://127.0.0.1:8788'"
);
Database::query(
    "INSERT INTO settings (name, value) VALUES ('xvps_markup', '10')
     ON DUPLICATE KEY UPDATE value = '10'"
);

echo "\n-- 1. Đồng bộ danh mục --\n";
$r = XvpsService::sync();
ok('sync() thành công', $r['ok'] === true, json_encode($r, JSON_UNESCAPED_UNICODE));
ok('có gói VPS trong DB', (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_products WHERE is_addon = 0') >= 2);
ok('có addon trong DB', (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_products WHERE is_addon = 1') >= 3);
ok('có OS trong DB', (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_operating_systems') >= 3);

echo "\n-- 2. Thông tin tài khoản đối tác --\n";
$info = XvpsService::info();
ok('info() thành công', $info['ok'] === true, json_encode($info, JSON_UNESCAPED_UNICODE));
ok('số dư đối tác = 5.000.000', (float)$info['balance'] === 5000000.0, (string)$info['balance']);

echo "\n-- 3. Token được cache --\n";
$t1 = XvpsService::token();
$len = strlen($t1);
ok('token có giá trị', $t1 !== '', 'rỗng');
ok('token được cache (gọi lại trả cùng token)', XvpsService::token() === $t1);

echo "\n-- 4. Tính giá --\n";
$product = XvpsService::findProduct('101');
ok('tìm thấy gói 101', $product !== null);
$q = XvpsService::calcTotal($product, 'monthly', 2);
ok('giá gốc 45000 → bán 49500', (float)$q['item'] === 49500.0, json_encode($q));
ok('tổng 2 máy = 99000', (float)$q['total'] === 99000.0, json_encode($q));
ok('VAT = 0', (float)$q['vat'] === 0.0);

$q4 = XvpsService::calcTotal($product, 'quarterly', 1);
ok('giá quý = 148500', (float)$q4['item'] === 148500.0, json_encode($q4));

// Addon: +1 CPU, +2GB RAM, +20GB Disk (disk chia 10 → 2 đơn vị)
// CPU 26250→28875, RAM 26250→28875, Disk 15000→16500
// = 28875 + 2×28875 + 2×16500 = 119625
$qAddon = XvpsService::calcTotal($product, 'monthly', 1, 1, 2, 20);
$addonCost = $qAddon['total'] - $qAddon['item'];
ok('addon CPU 28875 + RAM 2×28875 + Disk 2×16500 = 119625', abs($addonCost - 119625.0) < 0.01, (string)$addonCost);

echo "\n-- 5. Tạo đơn hàng (trừ ví + gọi API) --\n";
$user = Database::fetch("SELECT * FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
$balBefore = (float)$user['balance'];
Database::update('users', ['balance' => 10000000], 'id = :id', ['id' => (int)$user['id']]);
$balBefore = 10000000.0;

$res = XvpsService::createOrder('101', 'monthly', '1', 2);
ok('createOrder() thành công', $res['ok'] === true, json_encode($res, JSON_UNESCAPED_UNICODE));
ok('API trả 2 VPS', count($res['data']) === 2, json_encode($res['data']));

echo "\n-- 6. Lưu đơn + trừ ví + hoàn tiền khi lỗi --\n";
$orderId = XvpsService::createOrderRow((int)$user['id'], [
    'product_id'   => '101',
    'product_name' => 'PLATIUM',
    'billing_cycle'=> 'monthly',
    'os_id'        => '1',
    'quantity'     => 2,
    'price'        => 99000,
    'amount'       => 99000,
    'status'       => XvpsService::STATUS_PROCESSING,
    'raw'          => json_encode($res['data'], JSON_UNESCAPED_UNICODE),
]);
ok('tạo order row id=' . $orderId, $orderId > 0);

$newBal = Wallet::apply((int)$user['id'], 'vps_order', -99000, 'Đặt VPS PLATIUM x2', 'vps_order', (string)$orderId);
ok('ví trừ đúng 99000', abs((10000000.0 - $newBal) - 99000.0) < 0.01, (string)$newBal);

// Idempotency
$orderId2 = XvpsService::createOrderRow((int)$user['id'], [
    'product_id'     => '101',
    'amount'         => 99000,
    'idempotency_key'=> 'idem-test-1',
    'status'         => XvpsService::STATUS_PENDING,
]);$orderId3 = XvpsService::createOrderRow((int)$user['id'], [
    'product_id'     => '101',
    'amount'         => 99000,
    'idempotency_key'=> 'idem-test-1',
    'status'         => XvpsService::STATUS_PENDING,
]);
ok('idempotency: cùng key trả cùng order id', $orderId2 === $orderId3, "$orderId2 vs $orderId3");

// Hoàn tiền → trả về đúng số dư ban đầu (10.000.000)
$refund = Wallet::apply((int)$user['id'], 'vps_refund', 99000, 'Hoàn tiền VPS lỗi', 'vps_order', (string)$orderId);
ok('hoàn tiền trả lại đủ số dư ban đầu', abs($refund - 10000000.0) < 0.01, "$refund");

echo "\n-- 7. Đồng bộ VPS instance (giữ credentials) --\n";
$r = XvpsService::syncVps((int)$user['id']);
ok('syncVps() thành công', $r['ok'] === true, json_encode($r, JSON_UNESCAPED_UNICODE));
$inst = XvpsService::findInstance((int)$user['id'], 'MOCK-VPS-1');
ok('có instance MOCK-VPS-1', $inst !== null);
ok('instance có IP', ($inst['ip'] ?? '') === '103.56.123.41', (string)($inst['ip'] ?? ''));
ok('instance có day_left', (int)($inst['day_left'] ?? 0) === 25);

echo "\n-- 8. Lấy chi tiết VPS (có credentials) --\n";
$info2 = XvpsService::infoVps(['MOCK-VPS-1']);
ok('infoVps() thành công', $info2['ok'] === true, json_encode($info2, JSON_UNESCAPED_UNICODE));
ok('có username', ($info2['data'][0]['username'] ?? '') === 'root');
ok('có password', ($info2['data'][0]['password'] ?? '') === 'MockPass@123');

echo "\n-- 9. Action trên VPS --\n";
foreach (['start', 'stop', 'restart'] as $act) {
    $a = XvpsService::actionVps($act, 'MOCK-VPS-1');
    ok("action $act thành công", $a['ok'] === true, json_encode($a, JSON_UNESCAPED_UNICODE));
}
$a = XvpsService::actionVps('confirm-rebuild-vps', 'MOCK-VPS-1', ['os' => 4]);
ok('action confirm-rebuild-vps thành công', $a['ok'] === true, json_encode($a, JSON_UNESCAPED_UNICODE));
$a = XvpsService::actionVps('renew-vps', 'MOCK-VPS-1', ['billing-cycle' => 'monthly']);
ok('action renew-vps thành công', $a['ok'] === true, json_encode($a, JSON_UNESCAPED_UNICODE));
$a = XvpsService::actionVps('addon-vps', 'MOCK-VPS-1', ['addon-cpu' => 1]);
ok('action addon-vps thành công', $a['ok'] === true, json_encode($a, JSON_UNESCAPED_UNICODE));
$a = XvpsService::actionVps('hack-action', 'MOCK-VPS-1');
ok('action không hợp lệ bị chặn', $a['ok'] === false);

echo "\n-- 10. Xử lý lỗi API --\n";
// Sai credentials → token fail
XvpsService::saveCredentials('bad_user', 'bad_app', 'bad_secret');
XvpsService::clearToken();
$t = XvpsService::token();
ok('credentials sai → không lấy được token', $t === '', "token=$t");
$bad = XvpsService::createOrder('101', 'monthly', '1', 1);
ok('credentials sai → createOrder thất bại', $bad['ok'] === false, json_encode($bad, JSON_UNESCAPED_UNICODE));

// Khôi phục
XvpsService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
XvpsService::clearToken();
ok('khôi phục credentials → token OK', XvpsService::token() !== '');

echo "\n-- 11. Thống kê --\n";
$stats = XvpsService::stats();
ok('stats có keys', isset($stats['orders'], $stats['active'], $stats['products']), json_encode($stats));
ok('có ít nhất 1 đơn', $stats['orders'] >= 1, json_encode($stats));

// Dọn dẹp
Database::query("DELETE FROM vps_orders WHERE idempotency_key = 'idem-test-1'");
Database::update('users', ['balance' => $balBefore], 'id = :id', ['id' => (int)$user['id']]);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
