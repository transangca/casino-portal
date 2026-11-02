<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\ActivityLogService;
use App\Services\MailerService;
use App\Services\SocialAuthService;

/**
 * Đăng nhập / đăng ký bằng Google, Facebook, Telegram.
 */
class SocialAuthController
{
    /**
     * Chuyển hướng tới trang xác thực của nhà cung cấp.
     */
    public function redirect(string $provider): void
    {
        if (!in_array($provider, SocialAuthService::PROVIDERS, true)) {
            abort(404, 'Phương thức đăng nhập không hợp lệ');
        }

        if (!SocialAuthService::isEnabled($provider)) {
            flash('error', 'Phương thức đăng nhập này chưa được bật. Vui lòng liên hệ admin.');
            redirect(url('/login'));
        }

        $url = match ($provider) {
            'google'   => SocialAuthService::googleAuthUrl(),
            'facebook' => SocialAuthService::facebookAuthUrl(),
            default    => url('/login'),
        };

        redirect($url);
    }

    /**
     * Callback sau khi nhà cung cấp chuyển hồi.
     */
    public function callback(string $provider): void
    {
        if (!in_array($provider, SocialAuthService::PROVIDERS, true)) {
            abort(404, 'Phương thức đăng nhập không hợp lệ');
        }

        if (!SocialAuthService::isEnabled($provider)) {
            flash('error', 'Phương thức đăng nhập này chưa được bật.');
            redirect(url('/login'));
        }

        if ($provider === 'telegram') {
            $this->handleTelegram();
            return;
        }

        $state = (string)($_GET['state'] ?? '');
        if ($state !== '' && !SocialAuthService::checkState($state)) {
            flash('error', 'Phiên xác thực không hợp lệ. Vui lòng thử lại.');
            redirect(url('/login'));
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            flash('error', 'Không nhận được mã xác thực từ nhà cung cấp.');
            redirect(url('/login'));
        }

        $profile = null;
        if ($provider === 'google') {
            $token = SocialAuthService::googleToken($code);
            if ($token) {
                $profile = SocialAuthService::googleUser($token);
            }
        } elseif ($provider === 'facebook') {
            $token = SocialAuthService::facebookToken($code);
            if ($token) {
                $profile = SocialAuthService::facebookUser($token);
            }
        }

        if (!$profile) {
            flash('error', 'Không đăng nhập được bằng ' . ucfirst($provider) . '. Vui lòng thử lại.');
            redirect(url('/login'));
        }

        $this->finishLogin($profile, $provider);
    }

    /**
     * Telegram Login Widget — POST từ widget.
     */
    public function telegram(): void
    {
        if (!SocialAuthService::isEnabled('telegram')) {
            flash('error', 'Đăng nhập Telegram chưa được bật.');
            redirect(url('/login'));
        }

        $this->handleTelegram();
    }

    private function handleTelegram(): void
    {
        $profile = SocialAuthService::verifyTelegram($_POST);
        if (!$profile) {
            flash('error', 'Xác thực Telegram không hợp lệ. Vui lòng thử lại.');
            redirect(url('/login'));
        }

        $this->finishLogin($profile, 'telegram');
    }

    /**
     * Tìm/tạo user và đăng nhập.
     */
    private function finishLogin(array $profile, string $provider): void
    {
        $result = SocialAuthService::resolveUser($profile);
        $user   = $result['user'];

        if (!$user) {
            flash('error', 'Không tạo được tài khoản từ dữ liệu ' . ucfirst($provider) . '.');
            redirect(url('/login'));
        }

        Auth::loginById((int)$user['id']);
        CSRF::rotate();

        ActivityLogService::log(ActivityLogService::SCOPE_AUTH, 'social.login', (int)$user['id'], $provider);
        audit('user.social_login', 'users', (string)$user['id'], ['provider' => $provider]);

        if ($result['created']) {
            MailerService::notifyUser((int)$user['id'], 'register');
            flash('success', 'Đăng ký thành công qua ' . ucfirst($provider) . '. Chào mừng bạn!');
        } else {
            flash('success', 'Đăng nhập thành công với ' . ucfirst($provider) . '.');
        }

        $ref = $_SESSION['intended'] ?? url('/');
        unset($_SESSION['intended']);
        redirect($ref);
    }

    /**
     * Liên kết / gỡ kết nối mạng xã hội trong hồ sơ.
     */
    public function disconnect(string $provider): void
    {
        CSRF::verifyOrFail();

        if (!in_array($provider, SocialAuthService::PROVIDERS, true)) {
            abort(404);
        }

        Database::delete(
            'social_accounts',
            'user_id = ? AND provider = ?',
            [(int)Auth::id(), $provider]
        );

        flash('success', 'Đã gỡ liên kết ' . ucfirst($provider) . '.');
        redirect(url('/security/profile'));
    }
}
