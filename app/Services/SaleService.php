<?php
namespace App\Services;

use App\Core\Database;

/**
 * Dịch vụ sale / tin nhắn hàng loạt.
 *
 * Người dùng thêm tài khoản (Telegram, Facebook Zalo...) + proxy, tạo chiến dịch
 * gửi tin nhắn hàng loạt. Hệ thống chạy nền qua cron, giới hạn theo
 * daily_limit để tránh bị khóa tài khoản.
 */
class SaleService
{
    public const PLATFORMS = [
        'telegram' => 'Telegram',
        'facebook' => 'Facebook Messenger',
        'zalo'     => 'Zalo',
        'sms'      => 'SMS',
        'email'    => 'Email',
    ];

    /** Nhóm thương hiệu để lấy logo ứng dụng */
    public const PLATFORM_BRANDS = [
        'telegram' => 'telegram',
        'facebook' => 'facebook',
        'zalo'     => 'zalo',
        'sms'      => 'twilio',
        'email'    => 'microsoft',
    ];
    public const STATUSES = [
        'draft'   => 'Nháp',
        'running' => 'Đang chạy',
        'paused'  => 'Tạm dừng',
        'done'    => 'Hoàn tất',
        'stopped' => 'Đã dừng',
    ];

    /** Trạng thái xác minh tài khoản */
    public const ACCOUNT_STATUSES = [
        'pending'  => 'Chờ xác minh',
        'verified' => 'Đã xác minh',
        'rejected' => 'Từ chối',
    ];

    /** Thời hạn mã xác minh (giây) */
    public const VERIFY_TTL = 900;

    /** Số lần nhập sai tối đa */
    public const VERIFY_MAX_ATTEMPTS = 5;

    public static function accounts(int $userId): array
    {
        return Database::fetchAll(
            'SELECT a.*, p.title AS proxy_title, p.endpoint AS proxy_endpoint
             FROM sale_accounts a LEFT JOIN proxy_listings p ON p.id = a.proxy_id
             WHERE a.user_id = ? ORDER BY a.id DESC',
            [$userId]
        );
    }

    public static function createAccount(int $userId, array $data): array
    {
        $platform = (string)($data['platform'] ?? 'telegram');
        if (!isset(self::PLATFORMS[$platform])) {
            $platform = 'telegram';
        }
        $account = trim((string)($data['account'] ?? ''));
        if ($account === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tài khoản.'];
        }
        $secret = trim((string)($data['secret'] ?? ''));
        if ($secret === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập mật khẩu / token / app password.'];
        }

        $id = Database::insert('sale_accounts', [
            'user_id'     => $userId,
            'platform'    => $platform,
            'account'     => $account,
            'secret'      => \App\Core\Crypto::encrypt($secret),
            'proxy_id'    => (int)($data['proxy_id'] ?? 0) ?: null,
            'daily_limit' => max(1, (int)($data['daily_limit'] ?? 500)),
            'is_active'   => 1,
            'status'      => 'pending',
        ]);

        return ['ok' => true, 'message' => 'Đã thêm tài khoản.', 'id' => $id];
    }

    /* ============================================================
     * XÁC MINH TÀI KHOẢN (anti-spam)
     * ============================================================ */

    /**
     * Tạo mã xác minh (6 số) và trả về để hiển thị / gửi tới ứng dụng.
     */
    public static function createVerification(int $userId, int $accountId): array
    {
        $acc = Database::fetch('SELECT * FROM sale_accounts WHERE id = ? AND user_id = ?', [$accountId, $userId]);
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::VERIFY_TTL);

        // Vô hiệu hoá các mã cũ đang chờ
        Database::query(
            'UPDATE sale_account_verifications SET expires_at = NOW() WHERE user_id = ? AND account_id = ? AND verified_at IS NULL',
            [$userId, $accountId]
        );

        $id = Database::insert('sale_account_verifications', [
            'user_id'      => $userId,
            'account_id'   => $accountId,
            'platform'     => $acc['platform'],
            'account'      => $acc['account'],
            'code_hash'    => \App\Core\Crypto::encrypt($code),
            'attempts'     => 0,
            'max_attempts' => self::VERIFY_MAX_ATTEMPTS,
            'expires_at'   => $expiresAt,
        ]);

        return [
            'ok'         => true,
            'message'    => 'Đã tạo mã xác minh.',
            'id'         => $id,
            'code'       => $code,
            'expires_at' => $expiresAt,
            'ttl'        => self::VERIFY_TTL,
            'account'    => $acc['account'],
            'platform'   => $acc['platform'],
        ];
    }

    /**
     * Người dùng nhập mã 6 số để xác minh tài khoản.
     */
    public static function verifyAccount(int $userId, int $accountId, string $code): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Mã xác minh phải gồm 6 chữ số.'];
        }

        $row = Database::fetch(
            'SELECT * FROM sale_account_verifications
             WHERE user_id = ? AND account_id = ? AND verified_at IS NULL
             ORDER BY id DESC LIMIT 1',
            [$userId, $accountId]
        );
        if (!$row) {
            return ['ok' => false, 'message' => 'Chưa có yêu cầu xác minh. Vui lòng bấm "Gửi mã".'];
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'Mã xác minh đã hết hạn. Vui lòng gửi mã mới.'];
        }
        if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
            Database::update('sale_accounts', ['status' => 'rejected', 'last_error' => 'Quá số lần nhập mã'], 'id = :id', ['id' => $accountId]);
            return ['ok' => false, 'message' => 'Bạn đã nhập sai quá nhiều lần. Tài khoản bị tạm khoá.'];
        }

        $expected = \App\Core\Crypto::decrypt((string)$row['code_hash']);
        if (!hash_equals((string)$expected, $code)) {
            Database::query(
                'UPDATE sale_account_verifications SET attempts = attempts + 1 WHERE id = ?',
                [$row['id']]
            );
            $left = max(0, (int)$row['max_attempts'] - ((int)$row['attempts'] + 1));
            return ['ok' => false, 'message' => 'Mã xác minh không đúng. Còn ' . $left . ' lần thử.'];
        }

        Database::update('sale_account_verifications', ['verified_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        Database::update(
            'sale_accounts',
            ['status' => 'verified', 'verified_at' => date('Y-m-d H:i:s'), 'last_error' => null],
            'id = :id',
            ['id' => $accountId]
        );

        return ['ok' => true, 'message' => 'Xác minh thành công. Tài khoản đã sẵn sàng sử dụng.'];
    }

    /**
     * Xoá mã xác minh (dùng khi người dùng muốn đổi tài khoản).
     */
    public static function resetVerification(int $userId, int $accountId): array
    {
        Database::query(
            'UPDATE sale_account_verifications SET expires_at = NOW() WHERE user_id = ? AND account_id = ? AND verified_at IS NULL',
            [$userId, $accountId]
        );
        Database::update(
            'sale_accounts',
            ['status' => 'pending', 'verified_at' => null],
            'id = :id AND user_id = :uid',
            ['id' => $accountId, 'uid' => $userId]
        );
        return ['ok' => true, 'message' => 'Đã đặt lại trạng thái xác minh.'];
    }

    /**
     * Chỉ cho phép chạy chiến dịch khi tài khoản đã xác minh (nếu bật cơ chế).
     */
    public static function isVerificationRequired(): bool
    {
        try {
            $v = Database::fetchColumn("SELECT `value` FROM `settings` WHERE `name` = 'sale_verify_required'");
        } catch (\Throwable $e) {
            return true;
        }
        return $v === null ? true : (string)$v === '1';
    }

    /* ============================================================
     * IMPORT FILE .TXT / .CSV
     * ============================================================ */

    /**
     * Đọc & phân tích file upload (.txt/.csv) — mỗi dòng: tài khoản|mật khẩu|proxy
     * hoặc tài khoản,mật khẩu,proxy
     */
    public static function parseImportFile(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'csv'], true)) {
            return ['ok' => false, 'message' => 'Chỉ hỗ trợ file .txt hoặc .csv.'];
        }

        $maxSize = 5 * 1024 * 1024;
        try {
            $cfg = Database::fetchColumn("SELECT `value` FROM `settings` WHERE `name` = 'sale_import_max_size'");
            if ($cfg) {
                $maxSize = (int)$cfg;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        if (filesize($path) > $maxSize) {
            return ['ok' => false, 'message' => 'File quá lớn (tối đa ' . round($maxSize / 1048576, 1) . 'MB).'];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['ok' => false, 'message' => 'File rỗng hoặc không đọc được.'];
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $rows = [];
        if ($ext === 'csv') {
            $handle = fopen($path, 'r');
            while (($line = fgetcsv($handle)) !== false) {
                if (is_array($line) && count(array_filter($line, static fn($v) => trim((string)$v) !== '')) > 0) {
                    $rows[] = $line;
                }
            }
            fclose($handle);
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $rows[] = preg_split('/\t|\s*[,;|]\s*/', $line) ?: [];
                }
            }
        }

        $maxRows = 5000;
        try {
            $cfg = Database::fetchColumn("SELECT `value` FROM `settings` WHERE `name` = 'sale_import_max_rows'");
            if ($cfg) {
                $maxRows = (int)$cfg;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $valid   = [];
        $invalid = [];
        $seen    = [];
        $total   = 0;

        foreach ($rows as $row) {
            $total++;
            if ($total > $maxRows) {
                break;
            }
            $row = array_values(array_map(static fn($v) => trim((string)$v), (array)$row));
            $account = $row[0] ?? '';
            $secret  = $row[1] ?? '';
            $proxy   = $row[2] ?? '';

            if ($account === '') {
                $invalid[] = ['line' => $total, 'reason' => 'Thiếu tài khoản'];
                continue;
            }
            if ($secret === '') {
                $invalid[] = ['line' => $total, 'reason' => 'Thiếu mật khẩu/token'];
                continue;
            }
            if (mb_strlen($account) > 150 || mb_strlen($secret) > 255) {
                $invalid[] = ['line' => $total, 'reason' => 'Dữ liệu quá dài'];
                continue;
            }
            $key = mb_strtolower($account);
            if (isset($seen[$key])) {
                $invalid[] = ['line' => $total, 'reason' => 'Trùng tài khoản'];
                continue;
            }
            $seen[$key] = true;
            $valid[] = ['account' => $account, 'secret' => $secret, 'proxy' => $proxy];
        }

        return [
            'ok'      => true,
            'total'   => $total,
            'valid'   => $valid,
            'invalid' => $invalid,
        ];
    }

    /**
     * Lưu các dòng hợp lệ vào sale_accounts (chưa xác minh).
     */
    public static function importAccounts(int $userId, string $platform, array $rows, string $filename): array
    {
        if (!isset(self::PLATFORMS[$platform])) {
            $platform = 'telegram';
        }
        $existing = Database::fetchAll(
            'SELECT account FROM sale_accounts WHERE user_id = ? AND platform = ?',
            [$userId, $platform]
        );
        $map = [];
        foreach ($existing as $e) {
            $map[mb_strtolower((string)$e['account'])] = true;
        }

        $added = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $key = mb_strtolower((string)$r['account']);
            if (isset($map[$key])) {
                $skipped++;
                continue;
            }
            $map[$key] = true;
            Database::insert('sale_accounts', [
                'user_id'     => $userId,
                'platform'    => $platform,
                'account'     => (string)$r['account'],
                'secret'      => \App\Core\Crypto::encrypt((string)$r['secret']),
                'proxy_id'    => null,
                'daily_limit' => 500,
                'is_active'   => 1,
                'status'      => 'pending',
            ]);
            $added++;
        }

        try {
            Database::insert('sale_imports', [
                'user_id'      => $userId,
                'filename'     => $filename,
                'platform'     => $platform,
                'total_rows'   => count($rows) + $skipped,
                'valid_rows'   => $added,
                'invalid_rows' => $skipped,
                'status'       => 'done',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'ok'      => true,
            'message' => 'Đã import ' . $added . ' tài khoản' . ($skipped ? ', bỏ qua ' . $skipped . ' tài khoản trùng' : '') . '.',
            'added'   => $added,
            'skipped' => $skipped,
        ];
    }

    public static function imports(int $userId, int $limit = 20): array
    {
        try {
            return Database::fetchAll(
                'SELECT * FROM sale_imports WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
                [$userId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function deleteAccount(int $userId, int $id): array
    {
        Database::query(
            'DELETE FROM sale_accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        return ['ok' => true, 'message' => 'Đã xoá tài khoản.'];
    }

    /**
     * Tạo chiến dịch gửi tin.
     */
    public static function createCampaign(int $userId, array $data): array
    {
        $msg = trim((string)($data['message'] ?? ''));
        if ($msg === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập nội dung tin nhắn.'];
        }

        $targets = trim((string)($data['target_list'] ?? ''));
        $list    = array_values(array_filter(array_map(
            static fn($s) => trim($s),
            preg_split('/[\r\n,;]+/', $targets) ?: []
        )));

        if (!$list) {
            return ['ok' => false, 'message' => 'Vui lòng nhập danh sách người nhận (mỗi dòng 1 người).'];
        }

        $accountId = (int)($data['account_id'] ?? 0);
        $account = $accountId
            ? Database::fetch('SELECT * FROM sale_accounts WHERE id = ? AND user_id = ?', [$accountId, $userId])
            : null;
        if (!$account) {
            return ['ok' => false, 'message' => 'Vui lòng chọn tài khoản gửi đã được thêm.'];
        }

        $id = Database::insert('sale_campaigns', [
            'user_id'     => $userId,
            'account_id'  => $accountId,
            'message'     => $msg,
            'target_list' => json_encode($list, JSON_UNESCAPED_UNICODE),
            'total'       => count($list),
            'status'      => 'draft',
        ]);

        return ['ok' => true, 'message' => 'Đã tạo chiến dịch với ' . count($list) . ' người nhận.', 'id' => $id];
    }

    public static function campaigns(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT c.*, a.account, a.platform FROM sale_campaigns c
             LEFT JOIN sale_accounts a ON a.id = c.account_id
             WHERE c.user_id = ? ORDER BY c.id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Bắt đầu chiến dịch — job nền sẽ gửi dần theo daily_limit.
     */
    public static function startCampaign(int $userId, int $id): array
    {
        $c = Database::fetch('SELECT * FROM sale_campaigns WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$c) {
            return ['ok' => false, 'message' => 'Không tìm thấy chiến dịch.'];
        }
        if ($c['status'] === 'running') {
            return ['ok' => false, 'message' => 'Chiến dịch đang chạy.'];
        }
        if (self::isVerificationRequired()) {
            $acc = Database::fetch(
                'SELECT account, status FROM sale_accounts WHERE id = ? AND user_id = ?',
                [(int)$c['account_id'], $userId]
            );
            if ($acc && (string)($acc['status'] ?? '') !== 'verified') {
                return ['ok' => false, 'message' => 'Tài khoản "' . (string)$acc['account'] . '" chưa được xác minh. Vui lòng xác minh trước khi chạy.'];
            }
        }
        Database::update('sale_campaigns', ['status' => 'running'], 'id = :id', ['id' => $id]);
        return ['ok' => true, 'message' => 'Đã bắt đầu gửi. Hệ thống sẽ gửi dần theo giới hạn hằng ngày.'];
    }

    public static function stopCampaign(int $userId, int $id): array
    {
        Database::update(
            'sale_campaigns',
            ['status' => 'stopped'],
            'id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
        return ['ok' => true, 'message' => 'Đã dừng chiến dịch.'];
    }
}
