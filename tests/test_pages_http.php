<?php
/**
 * Test HTTP: đăng nhập rồi tải các trang chính, kiểm tra render không lỗi.
 * Chạy: php tests/test_pages_http.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\Core\Database;

$base  = 'http://127.0.0.1:8899';
$email = 'uitest_' . time() . '@local.test';
$pass  = 'UiTest@2026';

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['uitest' . time(), $email, password_hash($pass, PASSWORD_DEFAULT), 'user', 'active', 1000000]);
$userId = (int)$db->lastInsertId();
echo "User test ID: {$userId} ({$email})\n";

// Lấy CSRF token từ form login
$jar = tempnam(sys_get_temp_dir(), 'ck');
$ch = curl_init($base . '/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
]);
$html = (string)curl_exec($ch);
curl_close($ch);

if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) {
    echo "  [FAIL] Không tìm thấy CSRF token ở form login\n";
    exit(1);
}
$token = $m[1];
echo "CSRF token: " . substr($token, 0, 12) . "...\n";

// Đăng nhập
$ch = curl_init($base . '/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['_csrf_token' => $token, 'login' => $email, 'password' => $pass]),
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "Đăng nhập: HTTP {$info['http_code']}\n";
if ((int)$info['http_code'] !== 302) {
    echo "  [FAIL] Đăng nhập không thành công\n";
    exit(1);
}

$pages = [
    '/'                       => 'Trang chủ',
    '/services/otp-rental'    => 'Thuê SIM',
    '/services/ai-accounts'   => 'Tài khoản AI',
    '/services/virtual-bank'  => 'Bank ảo',
    '/services/bank-lookup'   => 'Tra cứu ngân hàng',
    '/services/sale'          => 'Dịch vụ Sale',
    '/services/ui-scraper'    => 'Đục UI/UX',
    '/services/smm'           => 'SMM',
    '/services/social'        => 'Mạng xã hội',
    '/services/telegram'      => 'Telegram',
    '/vip'                    => 'VIP',
    '/child-sites'            => 'Website con',
    '/api/docs'               => 'Tài liệu API',
    '/lobbies'                => 'Sảnh',
    '/games'                  => 'Game',
    '/security/2fa'           => 'Bảo mật 2FA',
    '/wallet/deposit'         => 'Nạp tiền',
    '/wallet/history'         => 'Lịch sử nạp',
];

$pass = 0;
$fail = 0;
$fatals = [];

foreach ($pages as $path => $label) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isError = preg_match('/(Fatal error|Parse error|Uncaught|Deprecated:|Warning:)/i', $body, $em);
    if ($code !== 200) {
        echo "  [FAIL] {$label} ({$path}) → HTTP {$code}\n";
        $fail++;
        continue;
    }
    if ($isError) {
        echo "  [FAIL] {$label} ({$path}) → PHP error: " . trim(substr($em[0], 0, 60)) . "\n";
        $fail++;
        continue;
    }
    if (str_contains($body, 'đăng nhập') && strlen($body) < 3000 && !str_contains($body, 'Dịch vụ')) {
        // có thể bị redirect về login
    }
    echo "  [OK]   {$label} ({$path}) → " . strlen($body) . " bytes\n";
    $pass++;
}

// Kiểm tra input không bị trắng trên các trang có form
echo "\n== Kiểm tra input trên các trang ==\n";
$inputPages = ['/services/sale', '/services/bank-lookup', '/services/virtual-bank', '/services/otp-rental'];
foreach ($inputPages as $path) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $body = (string)curl_exec($ch);
    curl_close($ch);
    $nInput = preg_match_all('/<input(?![^>]*type=hidden)[^>]*>/i', $body);
    $nSelect = preg_match_all('/<select/i', $body);
    echo "  {$path}: {$nInput} input, {$nSelect} select\n";
}

// Cleanup
Database::query('DELETE FROM users WHERE id = ?', [$userId]);
@unlink($jar);

echo "\n=== Kết quả: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
