<?php
namespace App\Services;

use App\Core\Database;

/**
 * Nhật ký hoạt động: ghi lại thuê API key, vào game, đổi IP...
 * Dùng cho mục "Lịch sử hoạt động" trong tài khoản và trong Admin.
 */
class ActivityLogService
{
    public const SCOPE_SYSTEM  = 'system';
    public const SCOPE_API     = 'api';
    public const SCOPE_GAME    = 'game';
    public const SCOPE_WALLET  = 'wallet';
    public const SCOPE_AUTH    = 'auth';
    public const SCOPE_ADMIN   = 'admin';
    public const SCOPE_KEY     = 'apikey';

    public static function log(
        string $scope,
        string $action,
        ?int $userId = null,
        ?string $subject = null,
        array $detail = []
    ): void {
        try {
            Database::insert('activity_logs', [
                'user_id'    => $userId ?? \App\Core\Auth::id(),
                'scope'      => $scope,
                'action'     => mb_substr($action, 0, 120),
                'subject'    => $subject ? mb_substr($subject, 0, 255) : null,
                'detail'     => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            logger('activity log failed: ' . $e->getMessage(), 'error');
        }
    }

    /** Danh sách cho user hiện tại */
    public static function forUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        return Database::fetchAll(
            'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            [$userId]
        );
    }

    /** Danh sách theo scope (Admin) */
    public static function byScope(string $scope, int $limit = 100, int $offset = 0): array
    {
        return Database::fetchAll(
            'SELECT a.*, u.username FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.scope = ? ORDER BY a.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            [$scope]
        );
    }

    public static function recent(int $limit = 100, string $scope = ''): array
    {
        if ($scope !== '') {
            return self::byScope($scope, $limit);
        }
        return Database::fetchAll(
            'SELECT a.*, u.username FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . (int)$limit
        );
    }

    /** Ghi log khi khách hàng thuê key API */
    public static function logApiKeyRental(int $userId, int $clientId, string $name, string $status): void
    {
        self::log(self::SCOPE_KEY, 'api_key_rental', $userId, $name, [
            'client_id' => $clientId,
            'status'    => $status,
        ]);
    }
}
