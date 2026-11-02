<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\ActivityLogService;
use App\Services\MailerService;
use App\Services\TwoFactorService;

/**
 * Bảo mật tài khoản: 2FA (TOTP + QR), avatar, ngôn ngữ, theme.
 */
class SecurityController
{
    /**
     * Trang cài đặt 2FA.
     */
    public function index(): void
    {
        $userId = (int)Auth::id();
        $status = TwoFactorService::status($userId);

        $secret = $status['secret'];
        if ($secret === '' || $status['enabled']) {
            $secret = TwoFactorService::generateSecret();
        }

        $uri = TwoFactorService::otpauthUri($secret, (string)(Auth::user()['username'] ?? 'user'), (string)setting('site_name', 'Casino Portal'));

        // Mã khôi phục chỉ hiện 1 lần sau khi bật / tạo lại
        $sessionRecovery = $_SESSION['_recovery_codes'] ?? [];
        unset($_SESSION['_recovery_codes']);

        view('security/2fa', [
            'title'          => 'Bảo mật 2FA',
            'status'         => $status,
            'secret'         => $secret,
            'otpauthUri'     => $uri,
            'qrSvg'          => TwoFactorService::qrSvg($uri, 6, 4),
            'sessionRecovery'=> $sessionRecovery,
        ]);
    }

    /**
     * Bật 2FA sau khi người dùng nhập mã 6 số.
     */
    public function enable(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $code   = preg_replace('/\D/', '', (string)($_POST['code'] ?? '')) ?? '';
        $secret = (string)($_POST['secret'] ?? '');

        if ($secret === '' || strlen($code) !== 6) {
            flash('error', 'Vui lòng nhập mã 6 số hiển thị trong ứng dụng.');
            redirect(url('/security/2fa'));
        }

        if (!TwoFactorService::verify($secret, $code)) {
            flash('error', 'Mã xác thực không đúng. Vui lòng thử lại.');
            redirect(url('/security/2fa'));
        }

        $recovery = TwoFactorService::generateRecoveryCodes(8);
        TwoFactorService::enable($userId, $secret, $recovery);

        // Lưu mã dự phòng vào session để hiển thị 1 lần
        $_SESSION['_recovery_codes'] = $recovery;

        ActivityLogService::log(ActivityLogService::SCOPE_AUTH, 'twofa.enable', $userId);
        audit('security.2fa.enable', 'users', (string)$userId);
        MailerService::notifyUser($userId, 'twofa');

        flash('success', 'Đã bật bảo mật 2FA thành công.');
        redirect(url('/security/2fa'));
    }

    /**
     * Tắt 2FA (yêu cầu mật khẩu hoặc mã 2FA).
     */
    public function disable(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $status = TwoFactorService::status($userId);

        if (!$status['enabled']) {
            flash('error', '2FA đang không bật.');
            redirect(url('/security/2fa'));
        }

        $password = (string)($_POST['password'] ?? '');
        $code     = preg_replace('/\D/', '', (string)($_POST['code'] ?? '')) ?? '';

        $user = Auth::user();
        $okPass = $password !== '' && $user && password_verify($password, (string)$user['password_hash']);
        $okCode = $code !== '' && TwoFactorService::verify($status['secret'], $code);

        if (!$okPass && !$okCode) {
            flash('error', 'Vui lòng nhập đúng mật khẩu hoặc mã 2FA để tắt bảo mật.');
            redirect(url('/security/2fa'));
        }

        TwoFactorService::disable($userId);

        ActivityLogService::log(ActivityLogService::SCOPE_AUTH, 'twofa.disable', $userId);
        audit('security.2fa.disable', 'users', (string)$userId);

        flash('success', 'Đã tắt bảo mật 2FA.');
        redirect(url('/security/2fa'));
    }

    /**
     * Tải lại mã dự phòng mới.
     */
    public function regenerateRecovery(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $status = TwoFactorService::status($userId);

        if (!$status['enabled']) {
            flash('error', '2FA đang không bật.');
            redirect(url('/security/2fa'));
        }

        $recovery = TwoFactorService::generateRecoveryCodes(8);
        Database::update(
            'user_twofa',
            ['recovery_codes' => json_encode($recovery)],
            'user_id = ?',
            [$userId]
        );

        $_SESSION['_recovery_codes'] = $recovery;
        flash('success', 'Đã tạo lại mã khôi phục.');
        redirect(url('/security/2fa'));
    }

    /**
     * Trang hồ sơ cá nhân + đổi avatar, tên, ngôn ngữ, theme.
     */
    public function profile(): void
    {
        $user = Auth::user();
        $socials = Database::fetchAll(
            'SELECT * FROM social_accounts WHERE user_id = ?',
            [(int)Auth::id()]
        );

        view('security/profile', [
            'title'   => 'Hồ sơ cá nhân',
            'socials' => $socials,
            'user'    => $user,
        ]);
    }

    /**
     * Lưu hồ sơ + upload avatar.
     */
    public function updateProfile(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $user   = Auth::user();
        if (!$user) {
            redirect(url('/login'));
        }

        $data = [];

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        if ($fullName !== '') {
            $data['full_name'] = mb_substr($fullName, 0, 150);
        }

        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone !== '') {
            $data['phone'] = mb_substr($phone, 0, 30);
        }

        $lang = (string)($_POST['language'] ?? '');
        if (in_array($lang, \App\Services\I18n::locales(), true)) {
            $data['language'] = $lang;
        }

        $theme = (string)($_POST['theme'] ?? '');
        if (in_array($theme, ['dark', 'light', 'system'], true)) {
            $data['theme'] = $theme;
        }

        // Upload avatar
        if (!empty($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $avatar = self::saveAvatar($_FILES['avatar'], $userId);
            if ($avatar) {
                $data['avatar'] = $avatar;
            }
        }

        if ($data) {
            Database::update('users', $data, 'id = :id', ['id' => $userId]);
            audit('user.profile.update', 'users', (string)$userId, array_keys($data));
            flash('success', 'Đã cập nhật hồ sơ.');
        }

        redirect(url('/security/profile'));
    }

    /**
     * Lưu avatar: ảnh local trong /public/uploads/avatars.
     */
    private static function saveAvatar(array $file, int $userId): ?string
    {
        $maxSize = 2 * 1024 * 1024; // 2MB
        if ((int)($file['size'] ?? 0) > $maxSize) {
            flash('error', 'Ảnh đại diện không được vượt quá 2MB.');
            return null;
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            flash('error', 'Chỉ hỗ trợ ảnh JPG, PNG, GIF hoặc WEBP.');
            return null;
        }

        $mime = (string)(@mime_content_type($file['tmp_name']) ?: '');
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            flash('error', 'Tệp tải lên không phải hình ảnh hợp lệ.');
            return null;
        }

        $dir = BASE_PATH . '/public/uploads/avatars';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'av_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $target = $dir . '/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            flash('error', 'Không lưu được ảnh đại diện.');
            return null;
        }

        // Xoá ảnh cũ
        $old = (string)($user['avatar'] ?? '');
        if ($old !== '' && str_starts_with($old, '/uploads/avatars/')) {
            @unlink(BASE_PATH . '/public' . $old);
        }

        return '/uploads/avatars/' . $filename;
    }

    /**
     * Đổi mật khẩu.
     */
    public function changePassword(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $user   = Auth::user();

        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        if (!$user || !password_verify($current, (string)$user['password_hash'])) {
            flash('error', 'Mật khẩu hiện tại không đúng.');
            redirect(url('/security/profile'));
        }
        if (strlen($new) < (int)config('security.password_min', 8)) {
            flash('error', 'Mật khẩu mới phải có ít nhất ' . config('security.password_min', 8) . ' ký tự.');
            redirect(url('/security/profile'));
        }
        if ($new !== $confirm) {
            flash('error', 'Xác nhận mật khẩu mới không khớp.');
            redirect(url('/security/profile'));
        }

        Database::update('users', [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
        ], 'id = :id', ['id' => $userId]);

        ActivityLogService::log(ActivityLogService::SCOPE_AUTH, 'password.change', $userId);
        flash('success', 'Đã đổi mật khẩu thành công.');
        redirect(url('/security/profile'));
    }

    /**
     * Lịch sử hoạt động của tài khoản.
     */
    public function activity(): void
    {
        $userId = (int)Auth::id();
        $logs   = ActivityLogService::forUser($userId, 100);
        $keys   = Database::fetchAll(
            'SELECT id, name, key_prefix, status, expires_at, total_requests, quota_limit, quota_used, last_used_at, created_at
             FROM api_clients WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [$userId]
        );

        view('security/activity', [
            'title' => 'Lịch sử hoạt động',
            'logs'  => $logs,
            'keys'  => $keys,
        ]);
    }
}
