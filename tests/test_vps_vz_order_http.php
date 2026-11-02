<?php
/**
 * Test E2E qua HTTP: đặt đơn VPS StoreVPS thật qua form khách hàng.
 *
 * Yêu cầu:
 *   - web server: http://127.0.0.1:8000
 *   - mock API:   php -S 127.0.0.1:8789 tests/mock_xvps_virt.php
 *
 * Chạy: php tests/test_vps_vz_order_http.php
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

function http(string $url, string $method = 'GET', ?array $post = null, array $headers = [], string $jar = ''): array
{
    $ch = curl_init($url);
    $hdrList = [];
    foreach ($headers as $k => $v) {
        $hdrList[] = "$k: $v";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    if ($hdrList) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrList);
    }
    $body   = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc    = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'location' => $loc];
}

echo "=== E2E ĐẶT ĐƠN VPS STOREVPS QUA HTTP (giao diện khách) ===\n";

$BASE = getenv('APP_BASE_URL') ?: 'http://127.0.0.1:8000';
$MOCK = 'http://127.0.0.1:8789';
$JAR  = sys_get_temp_dir() . '/vps_vz_order_cookies.txt';
@unlink($JAR);

reset_rate_limits();

// Mock API phải chạy
$probe = @file_get_contents($MOCK . '/api/virt/get-info', false, stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "api-username: mock_user\r\napi-app: mock_app\r\napi-secret: mock_secret\r\nauth-token: x\r\n",
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]));
if ($probe === false) {
    echo "\n[!] Mock server chưa chạy:\n    php -S 127.0.0.1:8789 tests/mock_xvps_virt.php\n";
    echo "KẾT QUẢ: 0 PASS, 1 FAIL\n";
    exit(1);
}

// Chuẩn bị môi trường
VirtualizorService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
setting_set('vps_vz_base_url', $MOCK, 'XVPS Virtualizor');
setting_set('vps_vz_markup', '0', 'XVPS Virtualizor');
setting_set('vps_vz_service_on', '1', 'XVPS Virtualizor');
VirtualizorService::clearToken();
VirtualizorService::sync();

$user = Database::fetch("SELECT * FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
if (!$user) {
    $user = Database::fetch("SELECT * FROM users WHERE status = 'active' ORDER BY id LIMIT 1");
}
$uid      = (int)$user['id'];
$oldHash  = (string)$user['password_hash'];
$oldBal   = (float)$user['balance'];
$oldMarkup = (string)setting('vps_vz_markup', '0');

Database::update('users', [
    'password_hash' => password_hash('VzOrder@2026', PASSWORD_DEFAULT),
    'balance'       => 50000000,
], 'id = :id', ['id' => $uid]);

echo "\n-- 1. Đăng nhập --\n";
$r = http($BASE . '/login', 'GET', null, [], $JAR);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m);
$token = $m[1] ?? '';
ok('lấy được CSRF token', $token !== '');

$r = http($BASE . '/login', 'POST', [
    '_csrf_token' => $token,
    'login'       => (string)$user['username'],
    'password'    => 'VzOrder@2026',
], [], $JAR);
ok('đăng nhập thành công (302)', $r['status'] === 302, 'status=' . $r['status']);

echo "\n-- 2. Trang catalog có gói --\n";
$r = http($BASE . '/services/vps-storevps', 'GET', null, [], $JAR);
ok('trang catalog trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('có gói STARTER', str_contains($r['body'], 'STARTER'));
ok('có gói PRO', str_contains($r['body'], 'PRO'));

echo "\n-- 3. Tính giá AJAX --\n";
$r = http($BASE . '/services/vps-storevps/quote?product_id=vz-101&cycle=monthly&quantity=2&addon_cores=1&location_id=loc-sg',
    'GET', null, ['X-Requested-With' => 'XMLHttpRequest'], $JAR);
$j = json_decode($r['body'], true);
ok('quote success', ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
// 2×(120000 + 60000 + 20000) = 400000
ok('quote total = 400000', (float)($j['data']['total'] ?? 0) === 400000.0, json_encode($j['data'] ?? []));

echo "\n-- 4. Lấy CSRF token từ trang catalog --\n";
$r = http($BASE . '/services/vps-storevps', 'GET', null, [], $JAR);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m3);
$formToken = $m3[1] ?? '';
ok('có CSRF token trên form', $formToken !== '');

echo "\n-- 5. Đặt đơn --\n";
$idem = 'vz-http-' . uniqid();
$before = Wallet::balance($uid);

$r = http($BASE . '/services/vps-storevps/submit', 'POST', [
    '_csrf_token'    => $formToken,
    'product_id'     => 'vz-101',
    'os_id'          => '1001',
    'cycle'          => 'monthly',
    'hostname'       => 'vz-http-test',
    'location_id'    => 'loc-sg',
    'quantity'       => '2',
    'addon_cores'    => '1',
    'idempotency_key'=> $idem,
], [], $JAR);
ok('POST submit trả 302', $r['status'] === 302, 'status=' . $r['status'] . ' body=' . substr($r['body'], 0, 150));

$order = Database::fetch('SELECT * FROM vps_vz_orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
ok('đã tạo đơn trong DB', $order !== null);
if ($order) {
    echo "        order #{$order['id']} status={$order['status']} amount={$order['amount']}\n";
    ok('đơn có số tiền 400000', abs((float)$order['amount'] - 400000.0) < 0.01, (string)$order['amount']);
    ok('đơn có instance_id từ API', ($order['instance_id'] ?? '') !== '', (string)$order['instance_id']);
    ok('đơn không phải failed', $order['status'] !== 'failed', (string)$order['status']);
    ok('đơn lưu idempotency_key', ($order['idempotency_key'] ?? '') === $idem);
    ok('đơn lưu hostname', ($order['hostname'] ?? '') === 'vz-http-test');
    ok('đơn lưu datacenter', ($order['location_name'] ?? '') !== '', (string)$order['location_name']);
}

$after = Wallet::balance($uid);
ok('ví đã trừ 400000', abs(($before - $after) - 400000.0) < 0.01, 'trừ ' . ($before - $after));

$txn = Database::fetch(
    "SELECT * FROM wallet_transactions WHERE user_id = ? AND type = 'vps_vz_order' ORDER BY id DESC LIMIT 1",
    [$uid]
);
ok('có giao dịch ví vps_vz_order', $txn !== null);
ok('giao dịch ví âm 400000', abs((float)($txn['amount'] ?? 0) + 400000.0) < 0.01, (string)($txn['amount'] ?? ''));

echo "\n-- 6. Idempotency: bấm lại cùng key --\n";
$countBefore = (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_vz_orders WHERE user_id = ?', [$uid]);
$r = http($BASE . '/services/vps-storevps/submit', 'POST', [
    '_csrf_token'    => $formToken,
    'product_id'     => 'vz-101',
    'os_id'          => '1001',
    'cycle'          => 'monthly',
    'hostname'       => 'vz-http-test',
    'location_id'    => 'loc-sg',
    'quantity'       => '2',
    'addon_cores'    => '1',
    'idempotency_key'=> $idem,
], [], $JAR);
ok('submit lại trả 302', $r['status'] === 302, 'status=' . $r['status']);
$countAfter = (int)Database::fetchColumn('SELECT COUNT(*) FROM vps_vz_orders WHERE user_id = ?', [$uid]);
ok('không tạo đơn trùng', $countBefore === $countAfter, "$countBefore -> $countAfter");

echo "\n-- 7. Trang đơn hàng --\n";
if ($order) {
    $r = http($BASE . '/services/vps-storevps/order/' . $order['id'], 'GET', null, [], $JAR);
    ok('trang đơn trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang đơn có tên gói', str_contains($r['body'], 'STARTER'));
    ok('trang đơn có hostname', str_contains($r['body'], 'vz-http-test'));
    ok('trang đơn có tổng tiền', str_contains($r['body'], '400.000') || str_contains($r['body'], '400000'));
}

echo "\n-- 8. Trang quản lý VPS của tôi --\n";
reset_rate_limits();
$r = http($BASE . '/services/vps-storevps/manage', 'GET', null, [], $JAR);
ok('trang quản lý trả 200', $r['status'] === 200, 'status=' . $r['status']);

$inst = Database::fetch('SELECT * FROM vps_vz_instances WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
ok('có VPS instance được ghi nhận', $inst !== null);
if ($inst) {
    ok('instance có vps_id', ($inst['vps_id'] ?? '') !== '', (string)$inst['vps_id']);
    ok('instance có hostname', ($inst['hostname'] ?? '') === 'vz-http-test', (string)$inst['hostname']);

    echo "\n-- 9. Action stop / start trên VPS --\n";
    $r = http($BASE . '/services/vps-storevps/manage', 'GET', null, [], $JAR);
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m4);
    $t = $m4[1] ?? $formToken;

    $r = http($BASE . '/services/vps-storevps/action', 'POST', [
        '_csrf_token' => $t,
        'vps_id'      => (string)$inst['vps_id'],
        'action'      => 'stop',
    ], [], $JAR);
    ok('action stop trả 302', $r['status'] === 302, 'status=' . $r['status']);

    $log = Database::fetch(
        'SELECT * FROM vps_vz_actions WHERE vps_id = ? AND action = ? ORDER BY id DESC LIMIT 1',
        [(string)$inst['vps_id'], 'stop']
    );
    ok('ghi log thao tác stop', $log !== null);
    ok('log thao tác thành công', ($log['status'] ?? '') === 'success', (string)($log['status'] ?? ''));

    // Trang chi tiết
    $r = http($BASE . '/services/vps-storevps/manage/' . rawurlencode((string)$inst['vps_id']), 'GET', null, [], $JAR);
    ok('trang chi tiết VPS trả 200', $r['status'] === 200, 'status=' . $r['status']);
    // Trang chi tiết làm mới từ API → hostname theo dữ liệu đối tác
    ok('trang chi tiết có hostname từ API', str_contains($r['body'], 'demo-vz-host'), 'thiếu hostname');
    ok('trang chi tiết có nút điều khiển nguồn', str_contains($r['body'], '/services/vps-storevps/action'));

    echo "\n-- 10. Gia hạn (trừ ví) --\n";
    $balBeforeRenew = Wallet::balance($uid);
    $r = http($BASE . '/services/vps-storevps/renew', 'POST', [
        '_csrf_token' => $t,
        'vps_id'      => (string)$inst['vps_id'],
        'cycle'       => 'monthly',
    ], [], $JAR);
    ok('renew trả 302', $r['status'] === 302, 'status=' . $r['status']);

    $balAfterRenew = Wallet::balance($uid);
    ok('renew trừ đúng 1 chu kỳ (120000)', abs(($balBeforeRenew - $balAfterRenew) - 120000.0) < 0.01,
        'trừ ' . ($balBeforeRenew - $balAfterRenew));

    $renewLog = Database::fetch(
        'SELECT * FROM vps_vz_actions WHERE vps_id = ? AND action = ? ORDER BY id DESC LIMIT 1',
        [(string)$inst['vps_id'], 'renew']
    );
    ok('ghi log thao tác renew', $renewLog !== null);
}

echo "\n-- 11. Batch status --\n";
$r = http($BASE . '/services/vps-storevps/status?ids=' . rawurlencode((string)($inst['vps_id'] ?? 'none')), 'GET', null, [
    'X-Requested-With' => 'XMLHttpRequest',
], $JAR);
$j = json_decode($r['body'], true);
ok('status trả JSON hợp lệ', is_array($j) && ($j['success'] ?? false) === true, substr($r['body'], 0, 200));

echo "\n-- 12. Admin --\n";
$r = http($BASE . '/admin/vps-storevps', 'GET', null, [], $JAR);
ok('admin trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('admin có bảng fleet', str_contains($r['body'], 'Fleet VPS'));
ok('admin có bảng đơn', str_contains($r['body'], 'Đơn hàng gần đây'));

$r = http($BASE . '/admin/vps-storevps/balance', 'GET', null, ['X-Requested-With' => 'XMLHttpRequest'], $JAR);
$j = json_decode($r['body'], true);
ok('admin balance trả JSON', is_array($j) && ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
ok('số dư đối tác = 25.000.000', (float)($j['data']['balance'] ?? 0) === 25000000.0, substr($r['body'], 0, 200));

echo "\n-- 13. Hoàn tiền khi đối tác từ chối --\n";
// Trỏ base_url sai → create-order lỗi → hệ thống hoàn tiền
setting_set('vps_vz_base_url', $MOCK . '/wrong-path', 'XVPS Virtualizor');
VirtualizorService::clearToken();

$balBeforeFail = Wallet::balance($uid);
$r = http($BASE . '/services/vps-storevps', 'GET', null, [], $JAR);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m5);
$t2 = $m5[1] ?? $formToken;

$r = http($BASE . '/services/vps-storevps/submit', 'POST', [
    '_csrf_token'    => $t2,
    'product_id'     => 'vz-101',
    'os_id'          => '1001',
    'cycle'          => 'monthly',
    'hostname'       => 'vz-http-fail',
    'quantity'       => '1',
    'idempotency_key'=> 'vz-http-fail-' . uniqid(),
], [], $JAR);
ok('submit lỗi API trả 302', $r['status'] === 302, 'status=' . $r['status']);

$balAfterFail = Wallet::balance($uid);
ok('ví được hoàn lại đúng số tiền', abs($balBeforeFail - $balAfterFail) < 0.01,
    "trước=$balBeforeFail sau=$balAfterFail");

$failOrder = Database::fetch(
    "SELECT * FROM vps_vz_orders WHERE user_id = ? AND hostname = 'vz-http-fail' ORDER BY id DESC LIMIT 1",
    [$uid]
);
ok('đơn lỗi được ghi nhận', $failOrder !== null);
ok('đơn có trạng thái failed', ($failOrder['status'] ?? '') === 'failed', (string)($failOrder['status'] ?? ''));

$refund = Database::fetch(
    "SELECT * FROM wallet_transactions WHERE user_id = ? AND type = 'vps_vz_refund' ORDER BY id DESC LIMIT 1",
    [$uid]
);
ok('có giao dịch hoàn tiền vps_vz_refund', $refund !== null);
ok('hoàn tiền đúng 120000', abs((float)($refund['amount'] ?? 0) - 120000.0) < 0.01, (string)($refund['amount'] ?? ''));

setting_set('vps_vz_base_url', $MOCK, 'XVPS Virtualizor');
VirtualizorService::clearToken();

echo "\n-- 14. Dọn dẹp --\n";
Database::query("DELETE FROM vps_vz_instances WHERE user_id = " . $uid);
Database::query("DELETE FROM vps_vz_orders WHERE user_id = " . $uid);
Database::query("DELETE FROM vps_vz_actions WHERE vps_id LIKE 'MOCK-VZ-%'");
Database::query("DELETE FROM wallet_transactions WHERE user_id = " . $uid . " AND type IN ('vps_vz_order','vps_vz_refund','vps_vz_renew','vps_vz_addon')");
Database::query("DELETE FROM vps_vz_products WHERE product_id LIKE 'vz-%'");
Database::query("DELETE FROM vps_vz_operating_systems WHERE os_id IN ('1001','1002','1003','1004')");
Database::query("DELETE FROM vps_vz_locations WHERE location_id IN ('loc-hcm','loc-sg')");

Database::update('users', ['password_hash' => $oldHash, 'balance' => $oldBal], 'id = :id', ['id' => $uid]);
setting_set('vps_vz_base_url', 'https://api.xvps.vn', 'XVPS Virtualizor');
setting_set('vps_vz_markup', $oldMarkup, 'XVPS Virtualizor');
VirtualizorService::saveCredentials('', '', '');
VirtualizorService::clearToken();
@unlink($JAR);
ok('dọn dẹp hoàn tất', true);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
