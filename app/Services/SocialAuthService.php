<?php
namespace App\Services;

use App\Core\Database;

/**
 * Đăng nhập/đăng ký qua mạng xã hội: Google, Facebook, Telegram.
 *
 * - Google   : OAuth2 Authorization Code (Web Application)
 * - Facebook : OAuth2 Authorization Code
 * - Telegram : Login Widget (verify hash HMAC-SHA256 theo data_check_string)
 *
 * Cấu hình (client_id / client_secret / bot_token) do Admin nhập trong
 * giao diện quản trị, lưu ở bảng `settings`.
 */
class SocialAuthService
{
    public const PROVIDERS = ['google', 'facebook', 'telegram'];

    public static function isEnabled(string $provider): bool
    {
        return setting('social_' . $provider . '_enabled', '0') === '1'
            && self::clientId($provider) !== '';
    }

    public static function clientId(string $provider): string
    {
        return (string)setting('social_' . $provider . '_client_id', '');
    }

    public static function clientSecret(string $provider): string
    {
        return (string)setting('social_' . $provider . '_client_secret', '');
    }

    public static function redirectUri(string $provider): string
    {
        return url('/auth/social/' . $provider . '/callback');
    }

    // ------------------------------------------------------------------
    // Google
    // ------------------------------------------------------------------

    public static function googleAuthUrl(): string
    {
        $state = self::stateToken();
        $_SESSION['social_state'] = $state;
        $params = [
            'client_id'     => self::clientId('google'),
            'redirect_uri'  => self::redirectUri('google'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $state,
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public static function googleToken(string $code): ?string
    {
        $resp = HttpClient::postJsonArray('https://oauth2.googleapis.com/token', [
            'client_id'     => self::clientId('google'),
            'client_secret' => self::clientSecret('google'),
            'redirect_uri'  => self::redirectUri('google'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        ]);
        return $resp['access_token'] ?? null;
    }

    public static function googleUser(string $accessToken): ?array
    {
        $resp = HttpClient::getJson('https://www.googleapis.com/oauth2/v3/userinfo', [], [
            'Authorization: Bearer ' . $accessToken,
        ]);
        if (!$resp || empty($resp['sub'])) {
            return null;
        }
        return [
            'provider'    => 'google',
            'provider_id' => (string)$resp['sub'],
            'email'       => (string)($resp['email'] ?? ''),
            'name'        => (string)($resp['name'] ?? ''),
            'avatar'      => (string)($resp['picture'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------
    // Facebook
    // ------------------------------------------------------------------

    public static function facebookAuthUrl(): string
    {
        $state = self::stateToken();
        $_SESSION['social_state'] = $state;
        $params = [
            'client_id'     => self::clientId('facebook'),
            'redirect_uri'  => self::redirectUri('facebook'),
            'response_type' => 'code',
            'scope'         => 'email,public_profile',
            'state'         => $state,
        ];
        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    public static function facebookToken(string $code): ?string
    {
        $resp = HttpClient::getJson('https://graph.facebook.com/v18.0/oauth/access_token', [
            'client_id'     => self::clientId('facebook'),
            'client_secret' => self::clientSecret('facebook'),
            'redirect_uri'  => self::redirectUri('facebook'),
            'code'          => $code,
        ]);
        return $resp['access_token'] ?? null;
    }

    public static function facebookUser(string $accessToken): ?array
    {
        $resp = HttpClient::getJson('https://graph.facebook.com/v18.0/me', [
            'fields'       => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);
        if (!$resp || empty($resp['id'])) {
            return null;
        }
        return [
            'provider'    => 'facebook',
            'provider_id' => (string)$resp['id'],
            'email'       => (string)($resp['email'] ?? ''),
            'name'        => (string)($resp['name'] ?? ''),
            'avatar'      => (string)($resp['picture']['data']['url'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------
    // Telegram Login Widget
    // ------------------------------------------------------------------

    public static function telegramBotToken(): string
    {
        return (string)setting('social_telegram_bot_token', '');
    }

    /**
     * Kiểm tra dữ liệu Telegram Login Widget.
     *
     * @param array $data Tham số do widget gửi lên
     */
    public static function verifyTelegram(array $data): ?array
    {
        $hash = (string)($data['hash'] ?? '');
        if ($hash === '') {
            return null;
        }
        unset($data['hash']);

        $botToken = self::telegramBotToken();
        if ($botToken === '') {
            return null;
        }

        ksort($data);
        $check = '';
        foreach ($data as $k => $v) {
            if ($v === null || is_array($v)) {
                continue;
            }
            $check .= $k . '=' . $v . "\n";
        }
        $check .= 'hash=' . $hash;

        $secret = hash_hmac('sha256', $check, $botToken, true);
        $calc   = hash_hmac('sha256', $check, $botToken);

        if (!hash_equals($calc, $hash)) {
            return null;
        }

        $authDate = (int)($data['auth_date'] ?? 0);
        if ($authDate > 0 && (time() - $authDate) > 86400) {
            return null; // quá 24h
        }

        return [
            'provider'    => 'telegram',
            'provider_id' => (string)($data['id'] ?? ''),
            'email'       => (string)($data['email'] ?? ''),
            'name'        => trim((string)($data['first_name'] ?? '') . ' ' . (string)($data['last_name'] ?? '')),
            'avatar'      => '',
            'username'    => (string)($data['username'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------
    // Chung
    // ------------------------------------------------------------------

    private static function stateToken(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['social_state'] = $state;
        return $state;
    }

    public static function checkState(string $state): bool
    {
        $saved = (string)($_SESSION['social_state'] ?? '');
        unset($_SESSION['social_state']);
        return $saved !== '' && hash_equals($saved, $state);
    }

    /**
     * Tìm hoặc tạo user từ profile mạng xã hội.
     */
    public static function resolveUser(array $profile): array
    {
        $provider = (string)$profile['provider'];
        $pid      = (string)$profile['provider_id'];

        $link = Database::fetch(
            'SELECT * FROM social_accounts WHERE provider = ? AND provider_id = ? LIMIT 1',
            [$provider, $pid]
        );

        if ($link) {
            $user = Database::fetch('SELECT * FROM users WHERE id = ?', [(int)$link['user_id']]);
            if ($user && $user['status'] === 'active') {
                Database::update('social_accounts', [
                    'email'  => $profile['email'] ?: null,
                    'name'   => $profile['name'] ?: null,
                    'avatar' => $profile['avatar'] ?: null,
                ], 'id = :id', ['id' => (int)$link['id']]);
                return ['user' => $user, 'created' => false];
            }
        }

        // Liên kết theo email
        $email = trim((string)($profile['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
            if ($user) {
                Database::insert('social_accounts', [
                    'user_id'     => (int)$user['id'],
                    'provider'    => $provider,
                    'provider_id' => $pid,
                    'email'       => $email ?: null,
                    'name'        => $profile['name'] ?: null,
                    'avatar'      => $profile['avatar'] ?: null,
                ]);
                return ['user' => $user, 'created' => false];
            }
        }

        // Tạo mới
        $username = self::uniqueUsername($profile['name'] ?: $provider, $pid);
        $userId = Database::insert('users', [
            'username'      => $username,
            'email'         => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? $email
                : $username . '@social.local',
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'full_name'     => $profile['name'] ?: $username,
            'role'          => 'user',
            'status'        => 'active',
            'balance'       => 0,
            'avatar'        => $profile['avatar'] ?: null,
            'language'      => (string)setting('default_language', 'vi'),
            'theme'         => 'system',
        ]);

        Database::insert('social_accounts', [
            'user_id'     => $userId,
            'provider'    => $provider,
            'provider_id' => $pid,
            'email'       => $email ?: null,
            'name'        => $profile['name'] ?: null,
            'avatar'      => $profile['avatar'] ?: null,
        ]);

        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        return ['user' => $user, 'created' => true];
    }

    private static function uniqueUsername(string $base, string $suffix): string
    {
        $base = slugify($base) ?: 'user';
        $base = preg_replace('/\d+/', '', $base) ?: 'user';
        $name = mb_substr($base, 0, 30);
        $candidate = $name;
        $i = 1;
        while (Database::fetchColumn('SELECT id FROM users WHERE username = ?', [$candidate])) {
            $candidate = $name . $i;
            $i++;
            if ($i > 999) {
                $candidate = $name . substr(bin2hex(random_bytes(3)), 0, 6);
                break;
            }
        }
        return $candidate;
    }
}
