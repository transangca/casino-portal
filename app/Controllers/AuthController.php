<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;

/**
 * Đăng ký / đăng nhập / đăng xuất.
 */
class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(url('/'));
        }
        view('auth/login', ['title' => 'Đăng nhập']);
    }

    public function login(): void
    {
        CSRF::verifyOrFail();

        $login    = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            flash('error', 'Vui lòng nhập đầy đủ thông tin.');
            keep_old(['login' => $login]);
            redirect(url('/login'));
        }

        // Đã gỡ cơ chế khóa tài khoản sau nhiều lần đăng nhập sai.
        // Vẫn ghi nhận số lần sai để thống kê nhưng không chặn.
        if (self::tooManyAttempts($login)) {
            logger('Login attempts exceeded for ' . $login . ' (not blocking)', 'warning');
        }

        if (!Auth::attempt($login, $password)) {
            self::registerFailedAttempt($login);
            flash('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
            keep_old(['login' => $login]);
            redirect(url('/login'));
        }

        self::clearAttempts($login);
        CSRF::rotate();
        flash('success', 'Đăng nhập thành công!');

        $ref = $_SESSION['intended'] ?? url('/');
        unset($_SESSION['intended']);
        redirect($ref);
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect(url('/'));
        }

        // Mã giới thiệu đã lưu từ link giới thiệu (session/cookie)
        $refCode = strtoupper(trim((string)($_SESSION['ref_code'] ?? $_COOKIE['ref_code'] ?? '')));

        view('auth/register', [
            'title'   => 'Đăng ký',
            'refCode' => $refCode,
        ]);
    }

    public function register(): void
    {
        CSRF::verifyOrFail();

        $fullName  = trim((string)($_POST['full_name'] ?? ''));
        $username  = trim((string)($_POST['username'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $confirm   = (string)($_POST['password_confirm'] ?? '');
        $refCode   = trim((string)($_POST['ref_code'] ?? ''));
        if ($refCode === '') {
            $refCode = strtoupper(trim((string)($_SESSION['ref_code'] ?? $_COOKIE['ref_code'] ?? '')));
        }
        $refCode   = strtoupper($refCode);

        $errors = [];
        if ($fullName === '')  { $errors[] = 'Vui lòng nhập họ tên.'; }
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username 3-50 ký tự, chỉ gồm chữ, số và dấu gạch dưới.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }
        if (strlen($password) < (int)config('security.password_min', 8)) {
            $errors[] = 'Mật khẩu phải có ít nhất ' . config('security.password_min', 8) . ' ký tự.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Xác nhận mật khẩu không khớp.';
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE username = ?', [$username])) {
            $errors[] = 'Username đã tồn tại.';
        }
        if (Database::fetchColumn('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Email đã được sử dụng.';
        }

        if ($errors) {
            keep_old(['full_name' => $fullName, 'username' => $username, 'email' => $email]);
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect(url('/register'));
        }

        $userId = Database::insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name'     => $fullName,
            'role'          => 'user',
            'status'        => 'active',
            'balance'       => 0,
        ]);

        if ($refCode !== '') {
            if (!\App\Services\CsoService::applyReferral($userId, $refCode)) {
                flash('warning', 'Mã giới thiệu không hợp lệ nên chưa được ghi nhận. Bạn vẫn đăng ký thành công.');
            }
        }
        unset($_SESSION['ref_code']);

        // Thưởng gói trải nghiệm cho tài khoản mới
        $trialPkg = Database::fetch(
            'SELECT * FROM packages WHERE is_free_trial = 1 AND is_active = 1 ORDER BY sort_order LIMIT 1'
        );
        if ($trialPkg) {
            \App\Services\PackageService::purchase($userId, (int)$trialPkg['id']);
        }

        Auth::loginById($userId);
        CSRF::rotate();
        audit('user.register', 'users', (string)$userId, ['username' => $username]);

        flash('success', 'Chào mừng bạn đến với ' . setting('site_name', 'Casino Portal') . '!');
        redirect(url('/'));
    }

    public function logout(): void
    {
        audit('user.logout');
        Auth::logout();
        flash('success', 'Đã đăng xuất.');
        redirect(url('/'));
    }

    // ---------------- Login attempts ----------------

    private static function attemptsFile(): string
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/login_attempts.json';
    }

    private static function tooManyAttempts(string $login): bool
    {
        $data = self::readAttempts();
        $key  = md5(strtolower($login));
        $rec  = $data[$key] ?? ['count' => 0, 'last' => 0];

        $window = (int)config('security.lockout_minutes', 15) * 60;
        if (time() - $rec['last'] > $window) {
            $rec['count'] = 0;
        }

        return $rec['count'] >= (int)config('security.max_login_attempts', 5);
    }

    private static function registerFailedAttempt(string $login): void
    {
        $data = self::readAttempts();
        $key  = md5(strtolower($login));
        $data[$key] = ['count' => ($data[$key]['count'] ?? 0) + 1, 'last' => time()];
        self::writeAttempts($data);
    }

    private static function clearAttempts(string $login): void
    {
        $data = self::readAttempts();
        unset($data[md5(strtolower($login))]);
        self::writeAttempts($data);
    }

    private static function readAttempts(): array
    {
        $f = self::attemptsFile();
        if (!is_file($f)) {
            return [];
        }
        return json_decode((string)file_get_contents($f), true) ?: [];
    }

    private static function writeAttempts(array $data): void
    {
        @file_put_contents(self::attemptsFile(), json_encode($data), LOCK_EX);
    }
}
