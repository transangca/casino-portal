<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Lớp phòng thủ hệ thống — chạy ở mỗi request.
 *
 * 1. Kiểm tra chữ ký tấn công (SQLi/XSS/LFI/RCE) trên input → chặn hoặc ghi log.
 * 2. Rate limit theo IP + theo user + theo route nhạy cảm.
 * 3. Blacklist IP.
 * 4. Phát hiện gian lận: đăng nhập bất thường, đổi IP, rút tiền bất thường,
 *    tấn công API, hành vi bất thường.
 *
 * Mọi cấu hình đều nằm trong bảng `settings` để admin chỉnh không cần sửa code.
 */
class SecurityGuard
{
    /** Input hay bị tấn công */
    private const DANGEROUS_KEYS = [
        'q', 'search', 'keyword', 'name', 'username', 'email', 'title', 'description',
        'note', 'message', 'body', 'comment', 'content', 'url', 'link', 'redirect',
        'callback', 'return_url', 'domain', 'phone', 'full_name', 'code', 'slug',
        'category', 'payment_method', 'terms', 'address', 'summary', 'prompt',
    ];

    /** Route nhạy cảm cần giới hạn tần suất mạnh */
    private const STRICT_PATHS = [
        '/login'         => ['limit' => 10, 'window' => 300],
        '/register'      => ['limit' => 8,  'window' => 3600],
        '/wallet/deposit'=> ['limit' => 20, 'window' => 3600],
        '/wallet/withdraw' => ['limit' => 10, 'window' => 3600],
        '/wallet/transfer' => ['limit' => 20, 'window' => 3600],
        '/security/2fa/enable' => ['limit' => 5, 'window' => 3600],
        '/api'           => ['limit' => 120, 'window' => 60],
    ];

    private static bool $checked = false;

    /**
     * Hàm chính — gọi 1 lần ở đầu mỗi request.
     */
    public static function boot(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            self::scanForAttackPatterns();
            self::enforceIpBlacklist();
            self::enforceRateLimit();
        } catch (\Throwable $e) {
            logger('SecurityGuard error: ' . $e->getMessage(), 'error');
        }
    }

    // ================= 1. Chữ ký tấn công =================

    private static function scanForAttackPatterns(): void
    {
        if (setting('ddos_protection', '1') !== '1') {
            return;
        }

        $patterns = Database::fetchAll(
            'SELECT pattern, type, action FROM attack_signatures WHERE is_active = 1'
        );
        if (!$patterns) {
            return;
        }

        $haystacks = [];
        foreach (self::DANGEROUS_KEYS as $k) {
            foreach ($_GET as $v) {
                if (is_string($v)) {
                    $haystacks[] = $v;
                }
            }
            if (isset($_POST[$k]) && is_string($_POST[$k])) {
                $haystacks[] = $_POST[$k];
            }
        }
        // Toàn bộ POST body dạng chuỗi (giới hạn để không tốn CPU)
        $count = 0;
        foreach ($_POST as $v) {
            if (is_string($v) && $count++ < 40) {
                $haystacks[] = $v;
            }
        }

        if (!$haystacks) {
            return;
        }

        $blob = mb_strtolower(implode("\n", $haystacks));

        foreach ($patterns as $sig) {
            $needle = mb_strtolower((string)$sig['pattern']);
            if ($needle === '' || !str_contains($blob, $needle)) {
                continue;
            }

            $action = (string)$sig['action'];
            self::recordEvent([
                'event_type' => 'attack_' . $sig['type'],
                'severity'   => $action === 'block' ? 'critical' : 'medium',
                'score'      => $action === 'block' ? 90 : 40,
                'detail'     => ['pattern' => $sig['pattern'], 'type' => $sig['type'], 'action' => $action],
            ]);

            Database::query('UPDATE attack_signatures SET hits = hits + 1 WHERE pattern = ?', [$sig['pattern']]);

            if ($action === 'block') {
                self::block('Yêu cầu bị chặn do phát hiện dấu hiệu tấn công.');
            }
        }
    }

    // ================= 2. Blacklist IP =================

    private static function enforceIpBlacklist(): void
    {
        $ip = self::clientIp();
        if ($ip === '') {
            return;
        }

        $row = Database::fetch(
            'SELECT * FROM ip_blacklist WHERE ip = ? AND is_active = 1 LIMIT 1',
            [$ip]
        );
        if (!$row) {
            return;
        }

        // Hết hạn → tự gỡ
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            Database::update('ip_blacklist', ['is_active' => 0], 'id = :id', ['id' => (int)$row['id']]);
            return;
        }

        Database::query('UPDATE ip_blacklist SET hits = hits + 1 WHERE id = ?', [(int)$row['id']]);

        self::recordEvent([
            'event_type' => 'ip_blacklisted',
            'severity'   => 'high',
            'score'      => 70,
            'detail'     => ['reason' => $row['reason']],
        ]);

        self::block('IP của bạn đã bị chặn. Vui lòng liên hệ quản trị.');
    }

    // ================= 3. Rate limit =================

    private static function enforceRateLimit(): void
    {
        if (setting('rate_limit_enabled', '1') !== '1') {
            return;
        }

        $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Bucket theo route
        $bucket = 'route:' . $method . ':' . $path;
        $limit  = 120;
        $window = 60;

        foreach (self::STRICT_PATHS as $strict => $conf) {
            if ($path === $strict || str_starts_with($path, $strict . '/')) {
                $bucket = 'strict:' . $strict;
                $limit  = (int)$conf['limit'];
                $window = (int)$conf['window'];
                break;
            }
        }

        $ip   = self::clientIp();
        $user = Auth::id();

        // Admin luôn được miễn nhiễm — không bao giờ bị chặn.
        if ($user && Auth::isAdmin()) {
            return;
        }

        // Chỉ giới hạn theo IP cho request chưa đăng nhập
        $ident = $user ? ('u' . $user) : ('ip:' . $ip);
        if (!$user) {
            $bucket .= ':ip';
        }

        if (!self::hit($bucket, $ident, $limit, $window)) {
            self::recordEvent([
                'event_type' => 'rate_limit_exceeded',
                'severity'   => 'medium',
                'score'      => 35,
                'detail'     => ['bucket' => $bucket, 'limit' => $limit, 'window' => $window],
            ]);
            // Chỉ ghi nhận sự kiện, KHÔNG chặn người dùng.
        }
    }

    /**
     * Ghi 1 lần truy cập vào cửa sổ đếm. Trả về false nếu đã vượt giới hạn.
     */
    public static function hit(string $bucket, string $ident, int $limit, int $window): bool
    {
        $key = substr($bucket . '|' . $ident, 0, 190);

        $row = Database::fetch('SELECT * FROM rate_limits WHERE bucket = ? AND ident = ?', [$bucket, $ident]);
        $now = time();

        if (!$row) {
            Database::insert('rate_limits', [
                'bucket'       => $bucket,
                'ident'        => $ident,
                'hits'         => 1,
                'window_start' => now(),
            ]);
            return true;
        }

        if (!empty($row['blocked_until']) && strtotime((string)$row['blocked_until']) > $now) {
            return false;
        }

        $start = strtotime((string)$row['window_start']);
        if ($now - $start > $window) {
            Database::update('rate_limits', [
                'hits'         => 1,
                'window_start' => now(),
                'blocked_until' => null,
            ], 'id = :id', ['id' => (int)$row['id']]);
            return true;
        }

        $hits = (int)$row['hits'] + 1;
        if ($hits > $limit) {
            Database::update('rate_limits', [
                'hits'          => $hits,
                'blocked_until' => date('Y-m-d H:i:s', $now + $window),
            ], 'id = :id', ['id' => (int)$row['id']]);
            return false;
        }

        Database::update('rate_limits', ['hits' => $hits], 'id = :id', ['id' => (int)$row['id']]);
        return true;
    }

    // ================= 4. Phát hiện gian lận =================

    /**
     * Ghi nhận 1 sự kiện bảo mật.
     */
    public static function recordEvent(array $data): void
    {
        try {
            Database::insert('security_events', [
                'user_id'    => $data['user_id']    ?? Auth::id(),
                'event_type' => mb_substr((string)($data['event_type'] ?? 'unknown'), 0, 60),
                'severity'   => (string)($data['severity'] ?? 'low'),
                'score'      => (int)($data['score'] ?? 0),
                'ip'         => self::clientIp(),
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'path'       => mb_substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                'detail'     => !empty($data['detail']) ? json_encode($data['detail'], JSON_UNESCAPED_UNICODE) : null,
                'status'     => 'open',
            ]);
        } catch (\Throwable $e) {
            logger('security event failed: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Kiểm tra hành vi bất thường của 1 user (dùng sau login, sau rút tiền, v.v.)
     *
     * @return array danh sách cảnh báo
     */
    public static function analyzeUser(int $userId): array
    {
        if (setting('fraud_detection', '1') !== '1') {
            return [];
        }

        $alerts = [];
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return $alerts;
        }

        // 1. Nhiều đăng nhập từ nhiều IP trong 24h
        $ips = (int)Database::fetchColumn(
            'SELECT COUNT(DISTINCT ip) FROM activity_logs
             WHERE user_id = ? AND scope = "auth" AND created_at > (NOW() - INTERVAL 24 HOUR)',
            [$userId]
        );
        if ($ips >= 5) {
            $alerts[] = ['type' => 'multi_ip_login', 'severity' => 'high', 'detail' => "Đăng nhập từ {$ips} IP khác nhau trong 24h."];
        }

        // 2. Tài khoản mới nhưng rút tiền lớn ngay
        $ageHours = (time() - strtotime((string)$user['created_at'])) / 3600;
        if ($ageHours < 24 && (float)$user['total_withdraw'] > 0) {
            $alerts[] = ['type' => 'new_account_withdraw', 'severity' => 'high', 'detail' => 'Tài khoản mới tạo đã rút tiền.'];
        }

        // 3. Nhiều lần đăng nhập sai
        $fails = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM security_events WHERE user_id = ? AND event_type = "failed_login" AND created_at > (NOW() - INTERVAL 1 HOUR)',
            [$userId]
        );
        if ($fails >= 10) {
            $alerts[] = ['type' => 'brute_force', 'severity' => 'critical', 'detail' => "{$fails} lần đăng nhập sai trong 1 giờ."];
        }

        // 4. Rút tiền vượt tổng nạp
        $dep = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type IN ("deposit","giftcode","win")',
            [$userId]
        );
        $wd = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type = "withdraw"',
            [$userId]
        );
        if ($wd > $dep * 1.05 && $wd > 500000) {
            $alerts[] = ['type' => 'withdraw_exceeds_deposit', 'severity' => 'critical', 'detail' => 'Rút tiền vượt tổng tiền đã nạp.'];
        }

        // 5. Nhiều tài khoản chung 1 thiết bị/IP (đăng ký hàng loạt)
        $sameIp = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM users WHERE ip_register = ? AND ip_register IS NOT NULL',
            [self::clientIp()]
        );
        if ($sameIp >= 10) {
            $alerts[] = ['type' => 'mass_registration', 'severity' => 'medium', 'detail' => "{$sameIp} tài khoản đăng ký từ cùng IP."];
        }

        // 6. Chuyển tiền qua lại giữa 2 tài khoản (dòng tiền rò)
        $loop = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM transfers t1
             JOIN transfers t2 ON t1.from_user_id = t2.to_user_id AND t2.from_user_id = t1.to_user_id
             WHERE t1.from_user_id = ? AND t1.created_at > (NOW() - INTERVAL 24 HOUR)',
            [$userId]
        );
        if ($loop >= 5) {
            $alerts[] = ['type' => 'transfer_loop', 'severity' => 'high', 'detail' => "Chuyển tiền qua lại {$loop} lần trong 24h."];
        }

        foreach ($alerts as $a) {
            self::recordEvent([
                'user_id'    => $userId,
                'event_type' => 'fraud_' . $a['type'],
                'severity'   => $a['severity'],
                'score'      => $a['severity'] === 'critical' ? 80 : 45,
                'detail'     => ['message' => $a['detail']],
            ]);
        }

        return $alerts;
    }

    /**
     * Tổng điểm rủi ro của 1 user (dùng để cảnh báo admin).
     */
    public static function riskScore(int $userId): int
    {
        return (int)Database::fetchColumn(
            'SELECT COALESCE(SUM(score),0) FROM security_events
             WHERE user_id = ? AND status IN ("open","reviewing")',
            [$userId]
        );
    }

    // ================= Tiện ích =================

    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $v = $_SERVER[$key] ?? '';
            if ($v === '') {
                continue;
            }
            $ip = trim(explode(',', (string)$v)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }

    private static function block(string $message, int $code = 403): never
    {
        if (is_ajax()) {
            json_error($message, $code);
        }
        http_response_code($code);
        echo '<!doctype html><meta charset="utf-8"><title>403</title>';
        echo '<div style="font-family:system-ui;padding:80px;text-align:center;background:#0b0b12;color:#eee">';
        echo '<h1 style="font-size:3rem;margin:0">403</h1>';
        echo '<p>' . e($message) . '</p>';
        echo '<a href="/" style="color:#f5c542">Về trang chủ</a></div>';
        exit;
    }
}
