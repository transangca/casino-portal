<?php
namespace App\Services;

use App\Core\Database;

/**
 * Quản lý website con (whitelabel) cho VIP từ mốc 5 trở lên.
 *
 * - Mỗi website con có slug riêng, logo, banner, theme, % ăn chia.
 * - % ăn chia lấy theo cấp VIP, admin có thể đặt trần trong settings.
 * - Điều kiện nạp tiền: require_deposit + min_deposit do admin cấu hình.
 */
class ChildSiteService
{
    public static function minVipLevel(): int
    {
        return (int)setting('child_site_min_vip', 5);
    }

    public static function maxRevShare(): float
    {
        return (float)setting('child_site_max_rev_share', 0.30);
    }

    public static function listByUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM child_sites WHERE user_id = ? ORDER BY id DESC',
            [$userId]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM child_sites WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM child_sites WHERE id = ?', [$id]);
    }

    /**
     * Tạo website con mới. Trả về ['ok'=>bool,'message'=>string,'id'=>int].
     */
    public static function create(int $userId, array $data): array
    {
        $vipLevel = VipService::levelOf($userId);
        $minVip   = self::minVipLevel();

        if ($vipLevel < $minVip) {
            return [
                'ok' => false,
                'message' => "Bạn cần đạt VIP {$minVip} để tạo website con (hiện tại VIP {$vipLevel}).",
            ];
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên website.'];
        }

        $slug = slugify((string)($data['slug'] ?? $name));
        if ($slug === '' || $slug === 'item') {
            $slug = 'site-' . bin2hex(random_bytes(3));
        }
        if (Database::fetchColumn('SELECT id FROM child_sites WHERE slug = ?', [$slug])) {
            return ['ok' => false, 'message' => 'Mã định danh đã tồn tại, vui lòng chọn mã khác.'];
        }

        // % ăn chia không vượt quá trần cấu hình và không vượt trần của cấp VIP
        $tier = VipService::find($vipLevel);
        $tierMax = (float)($tier['rev_share_max'] ?? 0);
        $requested = (float)($data['rev_share'] ?? 0);
        $revShare = max(0.0, min($requested, self::maxRevShare(), $tierMax ?: self::maxRevShare()));

        $requireDeposit = !empty($data['require_deposit']) ? 1 : 0;
        $minDeposit = max(0.0, (float)($data['min_deposit'] ?? 0));

        $id = Database::insert('child_sites', [
            'user_id'         => $userId,
            'name'            => mb_substr($name, 0, 150),
            'slug'            => mb_substr($slug, 0, 100),
            'domain'          => trim((string)($data['domain'] ?? '')) ?: null,
            'logo'            => trim((string)($data['logo'] ?? '')) ?: null,
            'banner'          => trim((string)($data['banner'] ?? '')) ?: null,
            'theme'           => in_array(($data['theme'] ?? ''), ['dark', 'light', 'system'], true) ? $data['theme'] : 'dark',
            'rev_share'       => $revShare,
            'require_deposit' => $requireDeposit,
            'min_deposit'     => $minDeposit,
            'status'          => 'pending',
        ]);

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'child_site.create', $userId, $slug, [
            'rev_share' => $revShare,
            'vip'       => $vipLevel,
        ]);

        return ['ok' => true, 'message' => 'Đã tạo website con. Vui lòng chờ admin duyệt.', 'id' => $id];
    }

    /** Cập nhật website con (chủ site tự đổi tên/logo/banner) */
    public static function update(int $id, int $userId, array $data): bool
    {
        $site = self::find($id);
        if (!$site || (int)$site['user_id'] !== $userId) {
            return false;
        }

        $payload = [];
        foreach (['name', 'domain', 'logo', 'banner'] as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = trim((string)$data[$k]) ?: null;
            }
        }
        if (isset($data['theme']) && in_array($data['theme'], ['dark', 'light', 'system'], true)) {
            $payload['theme'] = $data['theme'];
        }

        if (!$payload) {
            return false;
        }

        Database::update('child_sites', $payload, 'id = :id', ['id' => $id]);
        return true;
    }

    /** Ghi nhận 1 lượt truy cập website con */
    public static function trackVisit(int $childId, ?int $userId = null): void
    {
        try {
            Database::insert('child_site_visits', [
                'child_id' => $childId,
                'user_id'  => $userId,
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
                'referrer'  => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
            ]);
            Database::query('UPDATE child_sites SET hits = hits + 1 WHERE id = ?', [$childId]);
        } catch (\Throwable $e) {
            logger('child site visit failed: ' . $e->getMessage(), 'error');
        }
    }
}
