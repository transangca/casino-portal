<?php
/**
 * Quản lý tài khoản quản trị viên qua CLI.
 *
 *   php database/admin_account.php create <username> <email> <password>
 *   php database/admin_account.php reset  <username> <password>
 *   php database/admin_account.php list
 *   php database/admin_account.php grant  <username|email>          # nâng lên admin
 *   php database/admin_account.php revoke <username|email>          # hạ về user
 *   php database/admin_account.php delete <username|email>
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';

use App\Core\Database;

$args = array_slice($argv, 1);
$cmd  = strtolower((string) ($args[0] ?? 'help'));

function out(string $s): void
{
    echo $s . "\n";
}

function fail(string $s): void
{
    fwrite(STDERR, "LỖI: " . $s . "\n");
    exit(1);
}

/** Tìm user theo username hoặc email */
function findUser(string $key): ?array
{
    return Database::fetch(
        'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
        [$key, $key]
    );
}

switch ($cmd) {
    case 'create':
        $username = trim((string) ($args[1] ?? ''));
        $email    = trim((string) ($args[2] ?? ''));
        $password = (string) ($args[3] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            fail('Username 3-50 ký tự, chỉ chữ, số và dấu gạch dưới.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Email không hợp lệ.');
        }
        if (strlen($password) < 8) {
            fail('Mật khẩu phải có ít nhất 8 ký tự.');
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE username = ?', [$username])) {
            fail("Username '{$username}' đã tồn tại. Dùng lệnh 'reset' để đổi mật khẩu.");
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE email = ?', [$email])) {
            fail("Email '{$email}' đã được dùng.");
        }

        $id = Database::insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name'     => 'Quản trị hệ thống',
            'role'          => 'admin',
            'status'        => 'active',
            'balance'       => 0,
            'vip_level'     => 60,
        ]);
        out("Đã tạo tài khoản admin: {$username} / {$email} (id={$id})");
        break;

    case 'reset':
        $username = trim((string) ($args[1] ?? ''));
        $password = (string) ($args[2] ?? '');

        if ($username === '' || strlen($password) < 8) {
            fail('Cần: <username> <password> (mật khẩu tối thiểu 8 ký tự).');
        }
        $user = findUser($username);
        if (!$user) {
            fail("Không tìm thấy tài khoản '{$username}'.");
        }

        Database::update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status'        => 'active',
        ], 'id = :id', ['id' => (int) $user['id']]);

        out("Đã đặt lại mật khẩu cho '{$user['username']}' ({$user['email']}).");
        break;

    case 'grant':
        $key = trim((string) ($args[1] ?? ''));
        if ($key === '') {
            fail('Cần: <username|email>');
        }
        $user = findUser($key);
        if (!$user) {
            fail("Không tìm thấy tài khoản '{$key}'.");
        }
        Database::update('users', [
            'role'      => 'admin',
            'status'    => 'active',
            'vip_level' => 60,
        ], 'id = :id', ['id' => (int) $user['id']]);
        out("Đã nâng quyền '{$user['username']}' thành admin.");
        break;

    case 'revoke':
        $key = trim((string) ($args[1] ?? ''));
        if ($key === '') {
            fail('Cần: <username|email>');
        }
        $user = findUser($key);
        if (!$user) {
            fail("Không tìm thấy tài khoản '{$key}'.");
        }
        if ((string) $user['role'] === 'admin'
            && (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE role = "admin"') <= 1
        ) {
            fail('Không thể hạ quyền tài khoản admin cuối cùng.');
        }
        Database::update('users', ['role' => 'user'], 'id = :id', ['id' => (int) $user['id']]);
        out("Đã hạ quyền '{$user['username']}' về user.");
        break;

    case 'delete':
        $key = trim((string) ($args[1] ?? ''));
        if ($key === '') {
            fail('Cần: <username|email>');
        }
        $user = findUser($key);
        if (!$user) {
            fail("Không tìm thấy tài khoản '{$key}'.");
        }
        Database::delete('users', 'id = ?', [(int) $user['id']]);
        out("Đã xoá tài khoản '{$user['username']}'.");
        break;

    case 'list':
        $rows = Database::fetchAll(
            'SELECT id, username, email, role, status, created_at FROM users
             WHERE role IN ("admin","operator") ORDER BY id'
        );
        if (!$rows) {
            out('Chưa có tài khoản admin nào.');
            break;
        }
        out('Danh sách tài khoản quản trị:');
        foreach ($rows as $r) {
            printf(
                "  #%-5s %-20s %-30s role=%-9s status=%s\n",
                $r['id'],
                $r['username'],
                $r['email'],
                $r['role'],
                $r['status']
            );
        }
        break;

    default:
        out('Cách dùng:');
        out('  php database/admin_account.php list');
        out('  php database/admin_account.php create <username> <email> <password>');
        out('  php database/admin_account.php reset  <username> <password>');
        out('  php database/admin_account.php grant  <username|email>');
        out('  php database/admin_account.php revoke <username|email>');
        out('  php database/admin_account.php delete <username|email>');
        break;
}
