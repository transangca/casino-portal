<?php
/**
 * Kiểm tra nội dung giao diện: logo thương hiệu, badge VIP 3D, carousel, tab dịch vụ,
 * nút sao chép API, ô nhập mã 2FA, thẻ website con, logo ngân hàng.
 * Chạy: php tests/test_ui_content.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';

use App\Core\Database;

const BASE = 'http://127.0.0.1:8899';

$email    = 'uicontent_' . time() . '@local.test';
$userPass = 'UiContent@2026';

reset_rate_limits();

$db = Database::conn();
$st = $db->prepare('INSERT INTO users (username, email, password_hash, role, status, balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute(['uicont' . time(), $email, password_hash($userPass, PASSWORD_DEFAULT), 'user', 'active', 1000000]);
$userId = (int)$db->lastInsertId();

$http = new HttpClient(BASE);

// ---- Đăng nhập ----
$loginPage = $http->get('/login');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
$token = $m[1] ?? '';
if ($token === '') {
    echo "  [FAIL] Không lấy được CSRF token\n";
    exit(1);
}
$http->post('/login', [
    '_csrf_token' => $token,
    'login'       => $email,
    'password'    => $userPass,
]);

// Xác nhận đã đăng nhập: trang chủ phải không chuyển hướng về /login
$homeCode = $http->code('/');
$probe    = $http->get('/services/otp-rental');
if (str_contains($probe, 'Vui lòng đăng nhập để tiếp tục')) {
    echo "  [FAIL] Đăng nhập không có hiệu lực (phiên không giao nhớ cookie)\n";
    exit(1);
}

$passCount = 0;
$failCount = 0;
function check(string $name, bool $ok, string $msg = ''): void
{
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "  [OK]   {$name}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$name}" . ($msg !== '' ? " -> {$msg}" : '') . "\n";
    }
}

echo "== Trang chủ ==\n";
$home = $http->get('/');
check('có carousel banner quảng cáo', str_contains($home, 'promo-carousel'));
check('có slide trong carousel', str_contains($home, 'promo-slide'));
check('có tab dịch vụ theo danh mục', str_contains($home, 'svc-tabs') && str_contains($home, 'svc-card'));
check('có lưới nhà phát hành (brand-grid)', str_contains($home, 'brand-grid'));
check('có logo nhà phát hành', preg_match('/brand-icon[^>]*>\s*<img/', $home) === 1);
check('không có lỗi PHP', !preg_match('/(Fatal error|Parse error|Uncaught)/i', $home));

echo "\n== Thuê SIM ==\n";
$otp = $http->get('/services/otp-rental');
check('có danh mục ứng dụng (brand-grid)', str_contains($otp, 'brand-grid'));
check('có logo Telegram', str_contains($otp, 'brands/sim/telegram'));
check('có logo Zalo', str_contains($otp, 'brands/sim/zalo'));
check('có logo WhatsApp', str_contains($otp, 'brands/sim/whatsapp'));
check('có logo TikTok hoặc Shopee', str_contains($otp, 'brands/sim/tiktok') || str_contains($otp, 'brands/sim/shopee'));

echo "\n== Tài khoản AI ==\n";
$ai = $http->get('/services/ai-accounts');
check('có danh mục AI', str_contains($ai, 'brand-grid'));
check('có logo Cursor', str_contains($ai, 'brands/ai/cursor'));
check('có logo ChatGPT', str_contains($ai, 'brands/ai/chatgpt'));
check('có logo Claude', str_contains($ai, 'brands/ai/claude'));
check('có logo Gemini', str_contains($ai, 'brands/ai/gemini'));

echo "\n== Bank ảo ==\n";
$vb = $http->get('/services/virtual-bank');
check('tiêu đề "Bank ảo" (không phải HeyBank)', str_contains($vb, 'Bank ảo') && !str_contains($vb, 'HeyBank'));
check('có logo ngân hàng', preg_match('#img/brands/bank/#', $vb) === 1);
check('có mục tải xuống', stripos($vb, 'tải xuống') !== false || stripos($vb, 'download') !== false);

echo "\n== VIP 1-60 (badge 3D) ==\n";
$vip = $http->get('/vip');
check('có badge 3D', str_contains($vip, 'vip-badge-3d'));
check('có SVG badge inline', preg_match('/<svg[^>]*viewBox/i', $vip) === 1);
preg_match_all('/data-level="(\d+)"/', $vip, $lm);
$levels = array_unique($lm[1]);
check('có nhiều cấp VIP khác nhau', count($levels) >= 3, 'số cấp: ' . count($levels));
check('có nhiều class tier khác nhau', preg_match_all('/vip-tier-/', $vip) >= 3);

echo "\n== Website con ==\n";
$cs = $http->get('/child-sites');
check('có thẻ website con (child-site-card)', str_contains($cs, 'child-site-card'));
check('không có thẻ rỗng', !str_contains($cs, '<div class="child-site-card"></div>'));

echo "\n== 2FA ==\n";
$tfa = $http->get('/security/2fa');
check('có lưới ô mã (otp-inputs)', str_contains($tfa, 'otp-inputs'));
check('có 6 ô nhập mã', preg_match_all('/<input[^>]*class="[^"]*otp-input/', $tfa) >= 6, 'số ô: ' . preg_match_all('/<input[^>]*class="[^"]*otp-input/', $tfa));
check('có ô hiển thị secret key', stripos($tfa, 'secret') !== false);

echo "\n== Nạp tiền / Card ==\n";
$dep = $http->get('/wallet/deposit');
check('có bộ chọn mệnh giá thẻ cào', str_contains($dep, 'denom'));
check('có mã QR', stripos($dep, 'qr') !== false);

echo "\n== Tài liệu API ==\n";
$api = $http->get('/api/docs');
check('có nút sao chép toàn bộ API cho AI Agent', stripos($api, 'AI Agent') !== false);
check('có nút sao chép từng endpoint', str_contains($api, 'data-copy'));
check('có thanh điều hướng', str_contains($api, 'api-nav') || str_contains($api, 'api-docs-nav'));

echo "\n== Sảnh game (logo + thu gọn/mở rộng) ==\n";
$lob = $http->get('/lobbies');
check('có logo nhà phát hành', preg_match('#img/providers/#', $lob) === 1 || str_contains($lob, 'brand-icon'));
check('có nút thu gọn / mở rộng', str_contains($lob, 'toggle') || str_contains($lob, 'expand') || str_contains($lob, 'rail'));

echo "\n== Đục UI/UX ==\n";
$scr = $http->get('/services/ui-scraper');
check('có form nhập URL', str_contains($scr, 'name="url"'));
check('có nút tải ZIP', stripos($scr, 'zip') !== false);

echo "\n== Dịch vụ Sale ==\n";
$sale = $http->get('/services/sale');
check('có import file .txt/.csv', str_contains($sale, 'data_file') && str_contains($sale, '.csv'));
check('có ô nhập mã xác minh', str_contains($sale, 'sale-code-input') || str_contains($sale, 'name="code"'));
check('có logo nền tảng', preg_match('#img/brands/sim/#', $sale) === 1);

Database::query('DELETE FROM users WHERE id = ?', [$userId]);

echo "\n=== Kết quả: {$passCount} pass, {$failCount} fail ===\n";
exit($failCount > 0 ? 1 : 0);
