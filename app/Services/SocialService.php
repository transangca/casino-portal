<?php
namespace App\Services;

use App\Core\Database;

/**
 * Dịch vụ mạng xã hội & buff tương tác.
 *
 * Danh mục dịch vụ lấy từ bảng `social_services` (admin tự cấu hình giá).
 * Đơn hàng được trừ tiền từ ví khi đặt, hoàn tiền nếu thất bại.
 */
class SocialService
{
    public const PLATFORMS = [
        'facebook'  => 'Facebook',
        'tiktok'    => 'TikTok',
        'instagram' => 'Instagram',
        'youtube'   => 'YouTube',
        'web'       => 'Website',
        'google'    => 'Google',
    ];

    public static function platformLabel(string $code): string
    {
        return self::PLATFORMS[$code] ?? ucfirst($code);
    }

    public static function services(?string $platform = null): array
    {
        $where  = ['is_active = 1'];
        $params = [];
        if ($platform !== null && $platform !== '') {
            $where[]  = 'platform = ?';
            $params[] = $platform;
        }
        return Database::fetchAll(
            'SELECT * FROM social_services WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order, name',
            $params
        );
    }

    public static function platformsUsed(): array
    {
        return Database::fetchAll(
            'SELECT DISTINCT platform FROM social_services WHERE is_active = 1 ORDER BY platform'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM social_services WHERE id = ?', [$id]);
    }

    /**
     * Đặt dịch vụ buff — trừ tiền ví, tạo đơn.
     */
    public static function order(int $userId, array $data): array
    {
        $serviceId = (int)($data['service_id'] ?? 0);
        $svc = self::find($serviceId);
        if (!$svc) {
            return ['ok' => false, 'message' => 'Dịch vụ không tồn tại.'];
        }

        $url = trim((string)($data['target_url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'message' => 'Vui lòng nhập URL hợp lệ (bắt đầu bằng http:// hoặc https://).'];
        }

        $qty = max(1, (int)($data['quantity'] ?? 0));
        if ($qty < (int)$svc['min_qty']) {
            return ['ok' => false, 'message' => 'Số lượng tối thiểu là ' . number_format((int)$svc['min_qty']) . '.'];
        }
        if ($qty > (int)$svc['max_qty']) {
            return ['ok' => false, 'message' => 'Số lượng tối đa là ' . number_format((int)$svc['max_qty']) . '.'];
        }

        $amount = round((float)$svc['unit_price'] * $qty, 2);

        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
        if ($balance < $amount) {
            return ['ok' => false, 'message' => 'Số dư không đủ. Cần ' . money($amount) . ', hiện có ' . money($balance) . '.'];
        }

        \App\Core\Wallet::apply($userId, 'adjustment', -$amount, 'Buff ' . $svc['name'], 'social_order', (string)$serviceId);

        $id = Database::insert('social_orders', [
            'user_id'    => $userId,
            'service_id' => $serviceId,
            'target_url' => $url,
            'quantity'   => $qty,
            'amount'     => $amount,
            'status'     => 'pending',
            'note'       => trim((string)($data['note'] ?? '')) ?: null,
        ]);

        return ['ok' => true, 'message' => 'Đã đặt ' . number_format($qty) . ' ' . $svc['name'] . '. Đơn #' . $id, 'id' => $id];
    }

    public static function myOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT o.*, s.name AS service_name, s.platform
             FROM social_orders o LEFT JOIN social_services s ON s.id = o.service_id
             WHERE o.user_id = ? ORDER BY o.id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }
}
