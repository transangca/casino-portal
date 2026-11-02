<?php
/**
 * Test HTTP dịch vụ Tài khoản & Nick (Vuavia) + các dịch vụ V16 mới.
 * Chạy: php tests/test_new_services_http.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\Core\Database;

$base  = 'http://127.0.0.1:8912';
$email = 'newsvc_' . time() . '@local.test';
$pass  = 'NewSvc@2026';

// Xóa bộ đếm rate limit để test chạy độc lập
reset_rate_limits();

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['newsvc' . time(), $email, password_hash($pass, PASSWORD_DEFAULT), 'user', 'active', 5000000]);
$userId = (int)$db->lastInsertId();
echo "User test ID: {$userId}\n";

$db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['admin', $userId]);

require_once __DIR__ . '/http_client.php';

$client = new HttpClient($base);

/** Lấy CSRF token từ form login */
$html = $client->get('/login');
if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) {
    echo "  [FAIL] Không tìm thấy CSRF token\n";
    exit(1);
}
$token = $m[1];

/** Đăng nhập */
$client->post('/login', ['_csrf_token' => $token, 'login' => $email, 'password' => $pass]);
$who = $client->get('/');
echo "Đăng nhập: " . (str_contains($who, 'Đăng nhập') ? '[FAIL] chưa đăng nhập' : '[OK] đã đăng nhập') . "\n";

$pages = [
    '/services/ui-scraper'            => 'Đục UI/UX web',
    '/services/phone-check'          => 'Check số điện thoại',
    '/services/phone-check/history'  => 'Lịch sử check số',
    '/services/site-clone'            => 'Copy 1-1 website',
    '/services/site-auto'             => 'Tạo website A-Z',
    '/services/sms'                  => 'Gửi tin nhắn SMS',
    '/services/vuavia-accounts'      => 'Tài khoản & Nick (Vuavia)',
    '/admin/vuavia-accounts'         => 'Admin: Tài khoản & Nick',
    '/admin/service-prices'          => 'Admin: giá dịch vụ',
    '/admin/phone-checks'            => 'Admin: log check số',
    '/admin/site-jobs'               => 'Admin: job website',
    '/admin/sms-campaigns'           => 'Admin: chiến dịch SMS',
    '/admin/service-overview'        => 'Admin: tổng quan dịch vụ',
    '/api/docs'                      => 'Tài liệu API',
    '/lobbies'                        => 'Sảnh game',
    '/games'                          => 'Kho game',
    '/security/2fa'                   => 'Bảo mật 2FA',
    '/tools'                          => 'Tiện ích',
    '/'                               => 'Trang chủ',
];

$pass = 0;
$fail = 0;
foreach ($pages as $path => $label) {
    $body = $client->get($path);
    if (preg_match('/(Fatal error|Parse error|Uncaught |Warning:|Notice:|Deprecated:)/i', $body, $em)) {
        echo "  [FAIL] {$label} ({$path}) → PHP error: " . trim($em[0]) . "\n";
        $fail++;
        continue;
    }
    if (str_contains($body, 'Bạn cần đăng nhập') || str_contains($body, 'Vui lòng đăng nhập')) {
        echo "  [FAIL] {$label} ({$path}) → bị chặn đăng nhập\n";
        $fail++;
        continue;
    }
    echo "  [OK]   {$label} ({$path}) → " . strlen($body) . " bytes\n";
    $pass++;
}

// Kiểm tra trang chủ có hiển thị dịch vụ mới
echo "\n== Kiểm tra menu trang chủ ==\n";
$home = $client->get('/');
foreach (['phone-check', 'site-clone', 'site-auto', '/services/sms', 'vuavia-accounts'] as $needle) {
    $ok = str_contains($home, $needle);
    echo "  " . ($ok ? "[OK]  " : "[FAIL]") . " trang chủ có '{$needle}'\n";
    $ok ? $pass++ : $fail++;
}

// Kiểm tra layout có link tới dịch vụ mới
echo "\n== Kiểm tra link trong layout ==\n";
$lobby = $client->get('/lobbies');
foreach (['/services/phone-check', '/services/site-clone', '/services/site-auto', '/services/sms', '/services/vuavia-accounts'] as $needle) {
    $ok = str_contains($lobby, $needle);
    echo "  " . ($ok ? "[OK]  " : "[FAIL]") . " layout có '{$needle}'\n";
    $ok ? $pass++ : $fail++;
}

// Kiểm tra API docs có endpoint mới
echo "\n== Kiểm tra API docs ==\n";
$spec = $client->get('/api/spec');
foreach (['/api/accounts/products', '/api/accounts/order', '/api/phone/check'] as $needle) {
    $ok = str_contains($spec, $needle);
    echo "  " . ($ok ? "[OK]  " : "[FAIL]") . " spec có '{$needle}'\n";
    $ok ? $pass++ : $fail++;
}

// Kiểm tra trang Vuavia không lộ thông tin đối tác
echo "\n== Kiểm tra không lộ đối tác (phía khách) ==\n";
$vu = $client->get('/services/vuavia-accounts');
$leaks = ['vuavia.io', 'vav_', 'X-Api-Key', 'api.vuavia'];
foreach ($leaks as $needle) {
    $ok = !str_contains($vu, $needle);
    echo "  " . ($ok ? "[OK]  " : "[FAIL]") . " trang khách không chứa '{$needle}'\n";
    $ok ? $pass++ : $fail++;
}

// Kiểm tra admin CÓ thấy thông tin đối tác (nên có)
$adm = $client->get('/admin/vuavia-accounts');
$ok = str_contains($adm, 'vuavia.io');
echo "  " . ($ok ? "[OK]  " : "[FAIL]") . " trang admin có cấu hình đối tác\n";
$ok ? $pass++ : $fail++;

// Cleanup
Database::query('DELETE FROM users WHERE id = ?', [$userId]);

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
