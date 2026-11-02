<?php
namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

/**
 * Quản lý API key cho khách hàng (dev) đấu vào game.
 *
 * Luồng:
 *  1. User mua gói API  → tạo api_clients + cấp key (hiển thị 1 lần)
 *  2. Dev gửi header `X-API-Key: pk_live_xxx_yyy`
 *  3. Mỗi request: xác thực → kiểm tra hạn dùng + quota + rate limit
 *  4. Hết hạn / hết quota → trả lỗi chuẩn
 */
class ApiKeyService
{
    /** Số request tối đa trong 1 phút theo IP (chống lạm dụng) */
    private const IP_RATE_WINDOW = 60;

    /**
     * Tạo client + key mới.
     *
     * @return array{success:bool, message:string, key?:string, client?:array}
     */
    public static function create(int $userId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập tên ứng dụng.'];
        }

        $packageId = (int)($data['package_id'] ?? 0);
        $package   = $packageId > 0 ? Database::fetch('SELECT * FROM api_packages WHERE id = ? AND is_active = 1', [$packageId]) : null;

        $quota    = $package ? (int)$package['daily_quota'] : 10000;
        $rate     = $package ? (int)$package['rate_limit'] : 60;
        $duration = $package ? (int)$package['duration_days'] : 30;

        $gen = Crypto::generateApiKey();

        $expiresAt = date('Y-m-d H:i:s', time() + $duration * 86400);

        $clientId = Database::insert('api_clients', [
            'user_id'       => $userId,
            'package_id'    => $packageId ?: null,
            'name'          => mb_substr($name, 0, 150),
            'email'         => trim((string)($data['email'] ?? '')) ?: null,
            'website'       => trim((string)($data['website'] ?? '')) ?: null,
            'key_hash'      => $gen['hash'],
            'key_prefix'    => $gen['prefix'],
            'key_encrypted' => $gen['encrypted'],
            'status'        => 'active',
            'expires_at'    => $expiresAt,
            'quota_limit'   => $quota,
            'rate_limit'    => $rate,
        ]);

        audit('api_client.create', 'api_clients', (string)$clientId, ['name' => $name]);

        return [
            'success' => true,
            'message' => 'Đã tạo API key. Hãy lưu lại ngay, key chỉ hiển thị 1 lần.',
            'key'     => $gen['key'],
            'client'  => Database::fetch('SELECT * FROM api_clients WHERE id = ?', [$clientId]),
        ];
    }

    /**
     * Xác thực API key từ header.
     *
     * @return array{success:bool, message:string, client?:array}
     */
    public static function authenticate(?string $apiKey): array
    {
        if ($apiKey === null || trim($apiKey) === '') {
            return ['success' => false, 'message' => 'Thiếu API key. Gửi header X-API-Key.'];
        }

        $apiKey = trim($apiKey);

        // Tra cứu nhanh bằng prefix rồi so khớp hash
        $prefix = self::extractPrefix($apiKey);
        $candidates = Database::fetchAll(
            'SELECT * FROM api_clients WHERE key_prefix = ? LIMIT 5',
            [$prefix]
        );

        $client = null;
        foreach ($candidates as $c) {
            if (Crypto::equals($c['key_hash'], Crypto::hash($apiKey))) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            return ['success' => false, 'message' => 'API key không hợp lệ.'];
        }

        // Trạng thái
        if ($client['status'] !== 'active') {
            return ['success' => false, 'message' => 'API key đã bị ' . ($client['status'] === 'suspended' ? 'tạm khóa' : 'hết hạn') . '.'];
        }
        if ($client['expires_at'] && strtotime($client['expires_at']) < time()) {
            Database::update('api_clients', ['status' => 'expired'], 'id = :id', ['id' => $client['id']]);
            return ['success' => false, 'message' => 'API key đã hết hạn. Vui lòng gia hạn gói.'];
        }

        // Quota ngày
        $usedToday = self::requestsToday((int)$client['id']);
        if ($usedToday >= (int)$client['quota_limit']) {
            return [
                'success' => false,
                'message' => 'Đã hết quota ngày hôm nay (' . number_format((int)$client['quota_limit']) . ' request).',
            ];
        }

        // Rate limit theo IP (sliding window 1 phút)
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $ipCount = (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM api_client_logs WHERE ip = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
            [$ip, self::IP_RATE_WINDOW]
        );
        if ($ipCount >= 600) {
            return ['success' => false, 'message' => 'Quá nhiều request từ IP này. Vui lòng thử lại sau.'];
        }

        return ['success' => true, 'message' => 'OK', 'client' => $client];
    }

    /**
     * Ghi log + cập nhật counter sau khi xử lý request.
     */
    public static function track(int $clientId, string $endpoint, string $method, int $status, int $durationMs): void
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        Database::query(
            'UPDATE api_clients
             SET total_requests = total_requests + 1,
                 quota_used = quota_used + 1,
                 last_used_at = NOW(),
                 last_ip = ?
             WHERE id = ?',
            [$ip, $clientId]
        );

        Database::insert('api_client_logs', [
            'client_id'   => $clientId,
            'endpoint'    => mb_substr($endpoint, 0, 120),
            'method'      => mb_substr($method, 0, 10),
            'status_code' => $status,
            'ip'          => $ip,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Số request hôm nay của client.
     */
    public static function requestsToday(int $clientId): int
    {
        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM api_client_logs WHERE client_id = ? AND created_at >= CURDATE()',
            [$clientId]
        );
    }

    /**
     * Lấy lại API key gốc (giải mã) — chỉ chủ sở hữu client mới xem được.
     */
    public static function revealKey(int $clientId, int $userId): ?string
    {
        $row = Database::fetch(
            'SELECT key_encrypted FROM api_clients WHERE id = ? AND user_id = ?',
            [$clientId, $userId]
        );
        if (!$row) {
            return null;
        }
        return Crypto::decrypt($row['key_encrypted']);
    }

    /**
     * Gia hạn thêm thời gian cho client.
     */
    public static function renew(int $clientId, int $userId, int $extraDays): array
    {
        $client = Database::fetch(
            'SELECT * FROM api_clients WHERE id = ? AND user_id = ?',
            [$clientId, $userId]
        );
        if (!$client) {
            return ['success' => false, 'message' => 'Không tìm thấy API key.'];
        }

        $base = $client['expires_at'] && strtotime($client['expires_at']) > time()
            ? strtotime($client['expires_at'])
            : time();

        $newExpiry = date('Y-m-d H:i:s', $base + $extraDays * 86400);

        Database::update(
            'api_clients',
            [
                'expires_at' => $newExpiry,
                'status'     => 'active',
                'quota_limit'=> (int)$client['quota_limit'],
            ],
            'id = :id',
            ['id' => $clientId]
        );

        audit('api_client.renew', 'api_clients', (string)$clientId, ['days' => $extraDays]);

        return [
            'success' => true,
            'message' => "Đã gia hạn thêm {$extraDays} ngày. Hết hạn: " . $newExpiry,
            'expires_at' => $newExpiry,
        ];
    }

    /**
     * Tạm khóa / mở khóa.
     */
    public static function setStatus(int $clientId, int $userId, string $status): bool
    {
        $affected = Database::update(
            'api_clients',
            ['status' => $status],
            'id = :id AND user_id = :u',
            ['id' => $clientId, 'u' => $userId]
        );
        return $affected > 0;
    }

    /**
     * Xóa client.
     */
    public static function delete(int $clientId, int $userId): bool
    {
        return Database::delete('api_clients', 'id = :id AND user_id = :u', ['id' => $clientId, 'u' => $userId]) > 0;
    }

    /**
     * Trích prefix từ API key: pk_live_abc123def456_secret → abc123def456
     */
    private static function extractPrefix(string $key): string
    {
        $parts = explode('_', $key);
        if (count($parts) >= 3) {
            return $parts[count($parts) - 2];
        }
        return substr($key, 0, 12);
    }
}
