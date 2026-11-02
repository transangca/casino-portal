<?php
/**
 * Test HTTP thực tế — gọi các route của dịch vụ VPS StoreVPS qua HTTP thật.
 *
 * Yêu cầu: web server đang chạy ở http://127.0.0.1:8000
 * Chạy: php tests/test_vps_vz_http.php
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

function http(string $url, string $method = 'GET', ?array $post = null, array $headers = [], ?string $jar = null): array
{
    $jar ??= sys_get_temp_dir() . '/vps_vz_test_cookies.txt';

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
    curl_close($ch);

    return ['status' => $status, 'body' => $body];
}

echo "=== TEST HTTP DỊCH VỤ VPS STOREVPS ===\n";

$BASE = getenv('APP_BASE_URL') ?: 'http://127.0.0.1:8000';
$JAR  = sys_get_temp_dir() . '/vps_vz_test_cookies.txt';
@unlink($JAR);

reset_rate_limits();

// ------------------------------------------------------------------
section('1. Route cần đăng nhập → chuyển hướng login');
// ------------------------------------------------------------------
$r = http($BASE . '/services/vps-storevps');
ok('GET /services/vps-storevps trả 302 khi chưa đăng nhập', $r['status'] === 302, 'status=' . $r['status']);

$r = http($BASE . '/admin/vps-storevps');
ok('GET /admin/vps-storevps yêu cầu đăng nhập', $r['status'] === 302 || $r['status'] === 403, 'status=' . $r['status']);

// ------------------------------------------------------------------
section('2. Trang đăng nhập');
// ------------------------------------------------------------------
$r = http($BASE . '/login');
ok('GET /login trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('login có trường password', str_contains($r['body'], 'name="password"'));

$token = '';
if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $token = $m[1];
}
ok('lấy được CSRF token', $token !== '', 'token=' . ($token === '' ? 'không tìm thấy' : substr($token, 0, 8)));

// ------------------------------------------------------------------
section('3. Đăng nhập');
// ------------------------------------------------------------------
$user = Database::fetch(
    'SELECT * FROM users WHERE role IN ("admin","operator") AND status = "active" ORDER BY id LIMIT 1'
);
if (!$user) {
    $user = Database::fetch('SELECT * FROM users WHERE status = "active" ORDER BY id LIMIT 1');
}
ok('có user trong DB', $user !== null);

if ($user) {
    $oldHash  = (string)$user['password_hash'];
    $testPass = 'VzTest@2026';
    Database::update('users', ['password_hash' => password_hash($testPass, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int)$user['id']]);

    $login = http($BASE . '/login', 'POST', [
        '_csrf_token' => $token,
        'login'       => (string)$user['username'],
        'password'    => $testPass,
    ]);
    ok('POST /login trả 302', $login['status'] === 302, 'status=' . $login['status']);

    $r = http($BASE . '/');
    ok('session đã đăng nhập', $r['status'] === 200, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('4. Trang khách hàng');
    // ------------------------------------------------------------------
    $r = http($BASE . '/services/vps-storevps');
    ok('GET /services/vps-storevps trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang có tiêu đề StoreVPS', str_contains($r['body'], 'VPS StoreVPS'), 'thiếu tiêu đề');
    ok('trang có số dư ví', str_contains($r['body'], 'Số dư ví chính'));
    ok('trang có bộ chọn chu kỳ', str_contains($r['body'], 'Chu kỳ thanh toán'));
    ok('trang có modal đặt đơn', str_contains($r['body'], 'vzOrderModal'));
    ok('trang không có lỗi PHP', !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:'), 'có lỗi PHP');

    $r = http($BASE . '/services/vps-storevps?cycle=quarterly');
    ok('GET kèm cycle=quarterly trả 200', $r['status'] === 200);
    ok('chu kỳ quarterly được chọn', str_contains($r['body'], '3 Tháng'));

    $r = http($BASE . '/services/vps-storevps/manage');
    ok('GET /services/vps-storevps/manage trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang quản lý có bảng', str_contains($r['body'], 'Danh sách máy chủ'));

    $r = http($BASE . '/services/vps-storevps/manage/999-NOT-EXIST');
    ok('GET /manage/{id} không tồn tại → 404', $r['status'] === 404, 'status=' . $r['status']);

    $r = http($BASE . '/services/vps-storevps/order/999999');
    ok('GET /order/{id} không tồn tại → 404', $r['status'] === 404, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('5. AJAX tính giá');
    // ------------------------------------------------------------------
    Database::query(
        "INSERT INTO vps_vz_products (product_id, group_name, name, cpu, ram, disk, price_monthly, is_active, sort_order)
         VALUES ('VZHTTPTEST-1', 'TEST', 'VZ HTTP TEST PLAN', 1, 2048, 25, 120000, 1, 0)
         ON DUPLICATE KEY UPDATE price_monthly = 120000"
    );

    $oldMarkup = (string)Database::fetchColumn("SELECT value FROM settings WHERE name = 'vps_vz_markup' LIMIT 1");
    Database::query("INSERT INTO settings (name, value) VALUES ('vps_vz_markup', '0') ON DUPLICATE KEY UPDATE value = '0'");

    $r = http($BASE . '/services/vps-storevps/quote?product_id=VZHTTPTEST-1&cycle=monthly&quantity=2', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept'           => 'application/json',
    ]);
    ok('GET /quote trả 200', $r['status'] === 200, 'status=' . $r['status']);
    $j = json_decode($r['body'], true);
    ok('quote trả JSON hợp lệ', is_array($j), substr($r['body'], 0, 200));
    ok('quote success = true', ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
    ok('quote total = 240000', (float)($j['data']['total'] ?? 0) === 240000.0, json_encode($j['data'] ?? []));
    ok('quote có formatted.total', isset($j['data']['formatted']['total']));
    ok('quote VAT = 0', (float)($j['data']['vat'] ?? -1) === 0.0);

    Database::query("INSERT INTO settings (name, value) VALUES ('vps_vz_markup', '10') ON DUPLICATE KEY UPDATE value = '10'");
    $r = http($BASE . '/services/vps-storevps/quote?product_id=VZHTTPTEST-1&cycle=monthly&quantity=1', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('markup 10% → giá = 132000', (float)($j['data']['total'] ?? 0) === 132000.0, json_encode($j['data'] ?? []));

    $r = http($BASE . '/services/vps-storevps/quote?product_id=VZHTTPTEST-1&cycle=xxx', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('quote chu kỳ sai → success=false', ($j['success'] ?? true) === false, substr($r['body'], 0, 200));

    $r = http($BASE . '/services/vps-storevps/quote?product_id=NOT-EXIST', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('quote gói không tồn tại → success=false', ($j['success'] ?? true) === false);

    // ------------------------------------------------------------------
    section('6. Chống CSRF');
    // ------------------------------------------------------------------
    $r = http($BASE . '/services/vps-storevps/submit', 'POST', [
        'product_id' => 'VZHTTPTEST-1',
        'os_id'      => '1001',
        'cycle'      => 'monthly',
        'hostname'   => 'vz-http-test',
    ]);
    ok('POST /submit thiếu CSRF bị chặn', $r['status'] !== 200, 'status=' . $r['status']);
    ok('POST thiếu CSRF không tạo đơn', (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM vps_vz_orders WHERE product_id = 'VZHTTPTEST-1'"
    ) === 0);

    $r = http($BASE . '/services/vps-storevps/action', 'POST', [
        'vps_id' => 'X',
        'action' => 'start',
    ]);
    ok('POST /action thiếu CSRF bị chặn', $r['status'] !== 200, 'status=' . $r['status']);

    $r = http($BASE . '/services/vps-storevps/renew', 'POST', [
        'vps_id' => 'X',
        'cycle'  => 'monthly',
    ]);
    ok('POST /renew thiếu CSRF bị chặn', $r['status'] !== 200, 'status=' . $r['status']);

    $r = http($BASE . '/services/vps-storevps/cancel', 'POST', ['vps_id' => 'X']);
    ok('POST /cancel thiếu CSRF bị chặn', $r['status'] !== 200, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('7. Trang quản trị');
    // ------------------------------------------------------------------
    $r = http($BASE . '/admin/vps-storevps');
    ok('GET /admin/vps-storevps trả 200', $r['status'] === 200, 'status=' . $r['status']);
    if ($r['status'] === 200) {
        ok('admin có tiêu đề Virtualizor', str_contains($r['body'], 'Virtualizor'));
        ok('admin có trường api-username', str_contains($r['body'], 'name="api_username"'));
        ok('admin có trường api-app', str_contains($r['body'], 'name="api_app"'));
        ok('admin có trường api-secret dạng password', str_contains($r['body'], 'name="api_secret"'));
        ok('admin có trường markup', str_contains($r['body'], 'name="markup"'));
        ok('admin có toggle sandbox', str_contains($r['body'], 'name="sandbox"'));
        ok('admin có bảng fleet', str_contains($r['body'], 'Fleet VPS'));
        ok('admin không có lỗi PHP', !str_contains($r['body'], 'Fatal error'), 'có lỗi PHP');
    }

    // ------------------------------------------------------------------
    section('8. Menu điều hướng');
    // ------------------------------------------------------------------
    $r = http($BASE . '/services/vps-storevps');
    ok('menu khách có link StoreVPS', str_contains($r['body'], '/services/vps-storevps'));

    // ------------------------------------------------------------------
    section('9. Dọn dẹp');
    // ------------------------------------------------------------------
    Database::query("DELETE FROM vps_vz_products WHERE product_id = 'VZHTTPTEST-1'");
    Database::query(
        "INSERT INTO settings (name, value) VALUES ('vps_vz_markup', '" . addslashes($oldMarkup) . "')
         ON DUPLICATE KEY UPDATE value = '" . addslashes($oldMarkup) . "'"
    );
    Database::update('users', ['password_hash' => $oldHash], 'id = :id', ['id' => (int)$user['id']]);
    ok('khôi phục mật khẩu user', true);
}

@unlink($JAR);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
