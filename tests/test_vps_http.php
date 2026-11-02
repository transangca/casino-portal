<?php
/**
 * Test HTTP thực tế — gọi các route của dịch vụ VPS qua HTTP thật.
 *
 * Yêu cầu: web server đang chạy ở http://127.0.0.1:8000
 * Chạy: php tests/test_vps_http.php
 */

require __DIR__ . '/bootstrap.php';

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
 * Gọi HTTP thật bằng curl (giữ cookie jar).
 */
function http(string $url, string $method = 'GET', ?array $post = null, array $headers = [], ?string $jar = null): array
{
    $jar ??= sys_get_temp_dir() . '/vps_test_cookies.txt';

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

echo "=== TEST HTTP DỊCH VỤ VPS ===\n";

$BASE = getenv('APP_BASE_URL') ?: 'http://127.0.0.1:8000';
$JAR  = sys_get_temp_dir() . '/vps_test_cookies.txt';
@unlink($JAR);

// Xóa rate limit để test chạy độc lập, không dính 429
reset_rate_limits();

// ------------------------------------------------------------------
section('1. Route cần đăng nhập → chuyển hướng login');
// ------------------------------------------------------------------
$r = http($BASE . '/services/vps');
ok('GET /services/vps trả 302 khi chưa đăng nhập', $r['status'] === 302, 'status=' . $r['status']);

$r = http($BASE . '/admin/vps');
ok('GET /admin/vps yêu cầu đăng nhập', $r['status'] === 302 || $r['status'] === 403, 'status=' . $r['status']);

// ------------------------------------------------------------------
section('2. Trang đăng nhập');
// ------------------------------------------------------------------
$r = http($BASE . '/login');
ok('GET /login trả 200', $r['status'] === 200, 'status=' . $r['status']);
ok('login có trường password', str_contains($r['body'], 'name="password"'));

// Lấy CSRF token từ trang đăng nhập
$token = '';
if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $token = $m[1];
}
ok('lấy được CSRF token', $token !== '', 'token=' . ($token === '' ? 'không tìm thấy' : substr($token, 0, 8)));

// ------------------------------------------------------------------
section('3. Đăng nhập');
// ------------------------------------------------------------------
$user = App\Core\Database::fetch(
    'SELECT * FROM users WHERE role IN ("admin","operator") AND status = "active" ORDER BY id LIMIT 1'
);
if (!$user) {
    $user = App\Core\Database::fetch('SELECT * FROM users WHERE status = "active" ORDER BY id LIMIT 1');
}
ok('có user trong DB', $user !== null);

if ($user) {
    $oldHash = (string)$user['password_hash'];
    $testPass = 'VpsTest@2026';
    App\Core\Database::update('users', ['password_hash' => password_hash($testPass, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int)$user['id']]);

    $login = http($BASE . '/login', 'POST', [
        '_csrf_token' => $token,
        'login'       => (string)$user['username'],
        'password'    => $testPass,
    ]);
    ok('POST /login trả 302', $login['status'] === 302, 'status=' . $login['status']);

    // Xác nhận session đã đăng nhập
    $r = http($BASE . '/');
    ok('session đã đăng nhập (không còn 302 tới login)', $r['status'] === 200, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('4. Trang khách');
    // ------------------------------------------------------------------
    $r = http($BASE . '/services/vps');
    ok('GET /services/vps trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang có tiêu đề VPS VMware', str_contains($r['body'], 'VPS VMware'), 'thiếu tiêu đề');
    ok('trang có số dư ví', str_contains($r['body'], 'Số dư ví chính'));

    $r = http($BASE . '/services/vps/manage');
    ok('GET /services/vps/manage trả 200', $r['status'] === 200, 'status=' . $r['status']);
    ok('trang quản lý có bảng', str_contains($r['body'], 'Danh sách máy chủ'));

    $r = http($BASE . '/services/vps/manage/999-NOT-EXIST');
    ok('GET /services/vps/manage/{id} không tồn tại → 404', $r['status'] === 404, 'status=' . $r['status']);

    $r = http($BASE . '/services/vps/order/999999');
    ok('GET /services/vps/order/{id} không tồn tại → 404', $r['status'] === 404, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('5. AJAX tính giá');
    // ------------------------------------------------------------------
    App\Core\Database::query(
        "INSERT INTO vps_products (product_id, group_name, name, cpu, ram, disk, price_monthly, is_active, sort_order)
         VALUES ('HTTP-TEST-1', 'TEST', 'HTTP TEST PLAN', 1, 2048, 20, 45000, 1, 0)
         ON DUPLICATE KEY UPDATE price_monthly = 45000"
    );

    // Tạm đặt markup = 0 để kiểm tra giá gốc, khôi phục lại sau
    $oldMarkup = (string)App\Core\Database::fetchColumn(
        "SELECT value FROM settings WHERE name = 'xvps_markup' LIMIT 1"
    );
    App\Core\Database::query(
        "INSERT INTO settings (name, value) VALUES ('xvps_markup', '0')
         ON DUPLICATE KEY UPDATE value = '0'"
    );

    $r = http($BASE . '/services/vps/quote?product_id=HTTP-TEST-1&cycle=monthly&quantity=2', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept'           => 'application/json',
    ]);
    ok('GET /services/vps/quote trả 200', $r['status'] === 200, 'status=' . $r['status']);
    $j = json_decode($r['body'], true);
    ok('quote trả JSON hợp lệ', is_array($j), substr($r['body'], 0, 200));
    ok('quote success = true', ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
    ok('quote total = 90000', (float)($j['data']['total'] ?? 0) === 90000.0, json_encode($j['data'] ?? []));
    ok('quote có formatted.total', isset($j['data']['formatted']['total']));

    // Với markup 10% → giá bán = 49500/đơn vị
    App\Core\Database::query(
        "INSERT INTO settings (name, value) VALUES ('xvps_markup', '10')
         ON DUPLICATE KEY UPDATE value = '10'"
    );
    $r = http($BASE . '/services/vps/quote?product_id=HTTP-TEST-1&cycle=monthly&quantity=1', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('markup 10% → giá = 49500', (float)($j['data']['total'] ?? 0) === 49500.0, json_encode($j['data'] ?? []));

    $r = http($BASE . '/services/vps/quote?product_id=HTTP-TEST-1&cycle=xxx', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('quote chu kỳ sai → success=false', ($j['success'] ?? true) === false, substr($r['body'], 0, 200));

    $r = http($BASE . '/services/vps/quote?product_id=NOT-EXIST', 'GET', null, [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $j = json_decode($r['body'], true);
    ok('quote gói không tồn tại → success=false', ($j['success'] ?? true) === false);

    // ------------------------------------------------------------------
    section('6. Chống CSRF');
    // ------------------------------------------------------------------
    $r = http($BASE . '/services/vps/submit', 'POST', [
        'product_id' => 'HTTP-TEST-1',
        'os_id'      => '1',
        'cycle'      => 'monthly',
    ]);
    ok('POST /services/vps/submit thiếu CSRF bị chặn (không tạo đơn)', $r['status'] !== 200, 'status=' . $r['status']);
    ok('POST thiếu CSRF không tạo đơn VPS', (int)App\Core\Database::fetchColumn(
        "SELECT COUNT(*) FROM vps_orders WHERE product_id = 'HTTP-TEST-1'"
    ) === 0);

    $r = http($BASE . '/services/vps/action', 'POST', [
        'vps_id' => 'X',
        'action' => 'start',
    ]);
    ok('POST /services/vps/action thiếu CSRF bị chặn', $r['status'] !== 200, 'status=' . $r['status']);

    // ------------------------------------------------------------------
    section('7. Trang quản trị VPS');
    // ------------------------------------------------------------------
    $r = http($BASE . '/admin/vps');
    ok('GET /admin/vps trả 200', $r['status'] === 200, 'status=' . $r['status']);
    if ($r['status'] === 200) {
        ok('admin có tiêu đề XVPS', str_contains($r['body'], 'XVPS'));
        ok('admin có trường api-username', str_contains($r['body'], 'name="api_username"'));
        ok('admin có trường api-secret dạng password', str_contains($r['body'], 'type="password"'));
        ok('admin có bảng fleet', str_contains($r['body'], 'Fleet VPS'));
    }

    // ------------------------------------------------------------------
    section('8. Dọn dẹp');
    // ------------------------------------------------------------------
    App\Core\Database::query("DELETE FROM vps_products WHERE product_id = 'HTTP-TEST-1'");
    App\Core\Database::query(
        "INSERT INTO settings (name, value) VALUES ('xvps_markup', '" . addslashes($oldMarkup) . "')
         ON DUPLICATE KEY UPDATE value = '" . addslashes($oldMarkup) . "'"
    );
    App\Core\Database::update('users', ['password_hash' => $oldHash], 'id = :id', ['id' => (int)$user['id']]);
    ok('khôi phục mật khẩu user', true);
}

@unlink($JAR);

echo "\n================================\n";
echo "KẾT QUẢ: $pass PASS, $fail FAIL\n";
echo "================================\n";
exit($fail > 0 ? 1 : 0);
