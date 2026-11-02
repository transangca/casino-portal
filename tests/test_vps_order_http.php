<?php
/**
 * Test E2E qua HTTP: đặt đơn VPS thật qua form khách hàng.
 * Chạy: php tests/test_vps_order_http.php
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

echo "=== E2E ĐẶT ĐƠN VPS QUA HTTP (giao diện khách) ===\n";

$BASE = getenv('APP_BASE_URL') ?: 'http://127.0.0.1:8000';
$JAR  = sys_get_temp_dir() . '/vps_order_cookies.txt';
@unlink($JAR);

// Xóa rate limit để test chạy độc lập, không dính 429
reset_rate_limits();

// Chuẩn bị môi trường: mock API + gói hợp lệ + user có tiền
XvpsService::saveCredentials('mock_user', 'mock_app', 'mock_secret');
Database::query(
    "INSERT INTO settings (name, value) VALUES ('xvps_base_url', 'http://127.0.0.1:8788')
     ON DUPLICATE KEY UPDATE value = 'http://127.0.0.1:8788'"
);
Database::query(
    "INSERT INTO settings (name, value) VALUES ('xvps_markup', '10')
     ON DUPLICATE KEY UPDATE value = '10'"
);
Database::query(
    "INSERT INTO settings (name, value) VALUES ('xvps_service_on', '1')
     ON DUPLICATE KEY UPDATE value = '1'"
);
XvpsService::sync();

$user = Database::fetch("SELECT * FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
$uid  = (int)$user['id'];
$oldHash = (string)$user['password_hash'];
$oldBal  = (float)$user['balance'];

Database::update('users', [
    'password_hash' => password_hash('VpsTest@2026', PASSWORD_DEFAULT),
    'balance'       => 10000000,
], 'id = :id', ['id' => $uid]);

echo "\n-- 1. Đăng nhập --\n";
$r = http($BASE . '/login', 'GET', null, [], $JAR);
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m);
$token = $m[1] ?? '';
ok('lấy được CSRF token', $token !== '');

$r = http($BASE . '/login', 'POST', [
    '_csrf_token' => $token,
    'login'       => (string)$user['username'],
    'password'    => 'VpsTest@2026',
], [], $JAR);
ok('đăng nhập thành công (302)', $r['status'] === 302, 'status=' . $r['status']);

echo "\n-- 2. Trang catalog có gói --\n";
$r = http($BASE . '/services/vps', 'GET', null, [], $JAR);
ok('trang catalog trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('có gói PLATIUM', str_contains($r['body'], 'PLATIUM'));
ok('có gói GOLD', str_contains($r['body'], 'GOLD'));

echo "\n-- 3. Tính giá AJAX --\n";
$r = http($BASE . '/services/vps/quote?product_id=101&cycle=monthly&quantity=2&addon_cpu=1&addon_ram=2&addon_disk=20',
    'GET', null, ['X-Requested-With' => 'XMLHttpRequest'], $JAR);
$j = json_decode($r['body'], true);
ok('quote success', ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
// 2×49500 + 1×28875 + 2×28875 + 2×16500 = 99000 + 28875 + 57750 + 33000 = 218625
ok('quote total = 218625', (float)($j['data']['total'] ?? 0) === 218625.0, json_encode($j['data'] ?? []));

echo "\n-- 4. Lấy CSRF token từ trang catalog --\n";
$r = http($BASE . '/services/vps', 'GET', null, [], $JAR);
preg_match('/name="csrf-token"\s+content="([^"]+)"/', $r['body'], $m2);
$metaToken = $m2[1] ?? '';
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m3);
$formToken = $m3[1] ?? $metaToken;
ok('có CSRF token trên form', $formToken !== '');

echo "\n-- 5. Đặt đơn --\n";
$before = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
$r = http($BASE . '/services/vps/submit', 'POST', [
    '_csrf_token' => $formToken,
    'product_id'  => '101',
    'os_id'       => '1',
    'cycle'       => 'monthly',
    'quantity'    => '2',
    'addon_cpu'   => '1',
    'addon_ram'   => '2',
    'addon_disk'  => '20',
], [], $JAR);
ok('POST submit trả 302', $r['status'] === 302, 'status=' . $r['status'] . ' body=' . substr($r['body'], 0, 150));

// Lấy đơn mới nhất
$order = Database::fetch('SELECT * FROM vps_orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
ok('đã tạo đơn trong DB', $order !== null);
if ($order) {
    echo "        order #{$order['id']} status={$order['status']} amount={$order['amount']}\n";
    ok('đơn có số tiền 218625', abs((float)$order['amount'] - 218625.0) < 0.01, (string)$order['amount']);
    ok('đơn có vps_id từ API', ($order['vps_id'] ?? '') !== '', (string)$order['vps_id']);
    ok('đơn không phải failed', $order['status'] !== 'failed', (string)$order['status']);
}

$after = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$uid]);
ok('ví đã trừ 218625', abs(($before - $after) - 218625.0) < 0.01, "trừ " . ($before - $after));

echo "\n-- 6. Trang đơn hàng --\n";
if ($order) {
    $r = http($BASE . '/services/vps/order/' . $order['id'], 'GET', null, [], $JAR);
    ok('trang đơn trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang đơn có thông tin đơn', str_contains($r['body'], 'PLATIM') || str_contains($r['body'], '101'));
}

echo "\n-- 7. Trang quản lý VPS của tôi --\n";
reset_rate_limits();
$r = http($BASE . '/services/vps/manage', 'GET', null, [], $JAR);
ok('trang quản lý trả 200', $r['status'] === 200, 'status=' . $r['status']);

$inst = Database::fetch('SELECT * FROM vps_instances WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
ok('có VPS instance được ghi nhận', $inst !== null);
if ($inst) {
    ok('instance có IP', ($inst['ip'] ?? '') !== '', (string)$inst['ip']);
    ok('instance có username', ($inst['username'] ?? '') !== '', (string)$inst['username']);

    echo "\n-- 8. Action start/stop trên VPS --\n";
    $r = http($BASE . '/services/vps', 'GET', null, [], $JAR);
    preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m4);
    $t = $m4[1] ?? $formToken;

    $r = http($BASE . '/services/vps/action', 'POST', [
        '_csrf_token' => $t,
        'vps_id'      => (string)$inst['vps_id'],
        'action'      => 'stop',
    ], [], $JAR);
    ok('action stop trả 302', $r['status'] === 302, 'status=' . $r['status']);

    // Trang chi tiết
    $r = http($BASE . '/services/vps/manage/' . rawurlencode((string)$inst['vps_id']), 'GET', null, [], $JAR);
    ok('trang chi tiết VPS trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang chi tiết có IP', str_contains($r['body'], (string)$inst['ip']));
}

echo "\n-- 9. Đồng bộ lại danh mục (client sync) --\n";
$r = http($BASE . '/services/vps/sync', 'POST', [
    '_csrf_token' => $formToken,
], ['X-Requested-With' => 'XMLHttpRequest'], $JAR);
$j = json_decode($r['body'], true);
ok('sync trả JSON hợp lệ', is_array($j) && ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
ok('sync có số VPS đồng bộ', isset($j['data']['count']), substr($r['body'], 0, 200));

echo "\n-- 10. Trang quản trị --\n";
$r = http($BASE . '/admin/vps', 'GET', null, [], $JAR);
ok('admin trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('admin có bảng fleet', str_contains($r['body'], 'Fleet VPS'));

$r = http($BASE . '/admin/vps/balance', 'GET', null, ['X-Requested-With' => 'XMLHttpRequest'], $JAR);
$j = json_decode($r['body'], true);
ok('admin balance trả JSON', is_array($j) && ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
ok('số dư đối tác = 5.000.000', (float)($j['data']['balance'] ?? 0) === 5000000.0, substr($r['body'], 0, 200));

// Dọn dẹp
Database::update('users', ['password_hash' => $oldHash, 'balance' => $oldBal], 'id = :id', ['id' => $uid]);
@unlink($JAR);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
