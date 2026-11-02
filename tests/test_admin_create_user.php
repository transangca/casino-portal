<?php
/**
 * Test tạo tài khoản mới từ trang quản trị /admin/users.
 * Chạy: php tests/test_admin_create_user.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';

use App\Core\Database;

$base = 'http://127.0.0.1:8912';
reset_rate_limits();

$pass = 0;
$fail = 0;
function chk(string $label, bool $cond, string $extra = ''): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  [OK]   {$label}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$label}" . ($extra !== '' ? " — {$extra}" : '') . "\n";
        $fail++;
    }
}

// --- Đăng nhập bằng tài khoản admin ---
$c = new HttpClient($base);
$html = $c->get('/login');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m);
$c->post('/login', ['_csrf_token' => $m[1] ?? '', 'login' => 'admin', 'password' => 'admin123']);

echo "\n== Truy cập trang quản trị ==\n";
chk('đăng nhập admin thành công', $c->code('/admin') === 200);

$users = $c->get('/admin/users');
chk('mở được /admin/users', str_contains($users, 'Quản lý người dùng'));
chk('có nút tạo tài khoản mới', str_contains($users, 'Tạo tài khoản mới'));
chk('có form tạo (username)', str_contains($users, 'name="username"'));
chk('có form tạo (email)', str_contains($users, 'name="email"'));
chk('có form tạo (password)', str_contains($users, 'name="password"'));
chk('có form tạo (role)', str_contains($users, 'name="role"'));
chk('form post đúng route', str_contains($users, '/admin/users/create'));

echo "\n== Tạo tài khoản mới ==\n";
$stamp = time();
$newUser = 'admtest' . $stamp;
$newMail = $newUser . '@local.test';
$newPass = 'Test@2026x';

preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $users, $m);
$c->post('/admin/users/create', [
    '_csrf_token'     => $m[1] ?? '',
    'username'        => $newUser,
    'email'           => $newMail,
    'full_name'       => 'Tai Khoan Test',
    'role'            => 'admin',
    'vip_level'       => '60',
    'balance'         => '50000',
    'password'        => $newPass,
    'password_confirm'=> $newPass,
]);

$db = Database::conn();
$st = $db->prepare('SELECT * FROM users WHERE username = ?');
$st->execute([$newUser]);
$row = $st->fetch();

chk('tài khoản đã được tạo trong DB', (bool) $row);
chk('role = admin', ($row['role'] ?? '') === 'admin', (string)($row['role'] ?? ''));
chk('vip_level = 60', (int)($row['vip_level'] ?? 0) === 60);
chk('số dư ban đầu = 50000', abs((float)($row['balance'] ?? 0) - 50000) < 0.01, (string)($row['balance'] ?? ''));
chk('mật khẩu hash đúng', password_verify($newPass, (string)($row['password_hash'] ?? '')));
chk('trạng thái active', ($row['status'] ?? '') === 'active');

echo "\n== Đăng nhập bằng tài khoản vừa tạo ==\n";
$c2 = new HttpClient($base);
$l2 = $c2->get('/login');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $l2, $m2);
$c2->post('/login', ['_csrf_token' => $m2[1] ?? '', 'login' => $newUser, 'password' => $newPass]);
chk('tài khoản mới đăng nhập được', $c2->code('/admin') === 200);

echo "\n== Chặn trùng lặp ==\n";
$u2 = $c->get('/admin/users');
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $u2, $m3);
$c->post('/admin/users/create', [
    '_csrf_token'     => $m3[1] ?? '',
    'username'        => $newUser,
    'email'           => $newMail,
    'role'            => 'user',
    'password'        => $newPass,
    'password_confirm'=> $newPass,
]);
$st2 = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$st2->execute([$newUser]);
chk('không tạo trùng username', (int)$st2->fetchColumn() === 1);

echo "\n== Dọn dẹp ==\n";
$db->prepare('DELETE FROM users WHERE username = ?')->execute([$newUser]);
chk('đã xoá tài khoản test', (int)$db->query('SELECT COUNT(*) FROM users WHERE username = "' . $newUser . '"')->fetchColumn() === 0);

echo "\n== Kết quả: {$pass} pass, {$fail} fail ==\n";
exit($fail > 0 ? 1 : 0);
