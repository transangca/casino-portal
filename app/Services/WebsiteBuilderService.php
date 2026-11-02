<?php
namespace App\Services;

use App\Core\Database;

/**
 * Website Builder — khách hàng đăng ký nhu cầu thiết kế website.
 *
 * Quy trình:
 *  1. Khách nhập thông tin dự án (nền tảng, domain, email, liên hệ).
 *  2. Hệ thống tính giá theo bảng giá trong settings.
 *  3. Khách thanh toán (bắt buộc nạp trước) → dự án chuyển sang "demo".
 *  4. Khách xem demo → popup iframe dự án (không rời trang).
 *  5. Thông báo tự động gửi về Telegram bot của admin.
 */
class WebsiteBuilderService
{
    public const PLATFORMS = [
        'laravel'    => 'Laravel',
        'wordpress'  => 'WordPress',
        'shopify'    => 'Shopify',
        'react'      => 'React / Next.js',
        'vue'        => 'Vue / Nuxt',
        'nodejs'     => 'Node.js / Express',
        'php'        => 'PHP thuần',
        'landing'    => 'Landing page',
        'pos'        => 'Phần mềm quản lý',
        'other'      => 'Khác',
    ];

    public const STATUS_LABELS = [
        'draft'    => 'Chờ thanh toán',
        'demo'     => 'Đã có demo',
        'review'   => 'Đang xem xét',
        'active'   => 'Đang triển khai',
        'rejected' => 'Từ chối',
    ];

    public static function platformLabel(string $code): string
    {
        return self::PLATFORMS[$code] ?? $code;
    }

    public static function statusLabel(string $code): string
    {
        return self::STATUS_LABELS[$code] ?? $code;
    }

    /**
     * Bảng giá theo nền tảng (admin chỉnh trong settings).
     */
    public static function priceTable(): array
    {
        $raw = (string)setting('website_builder_prices', '');
        $table = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $table = $decoded;
            }
        }
        if (!$table) {
            $table = [
                'landing' => 2500000,
                'php'     => 5000000,
                'laravel' => 8000000,
                'wordpress' => 6000000,
                'react'   => 12000000,
                'vue'     => 11000000,
                'nodejs'  => 10000000,
                'shopify' => 9000000,
                'pos'     => 15000000,
                'other'   => 7000000,
            ];
        }
        return $table;
    }

    public static function priceFor(string $platform): float
    {
        $table = self::priceTable();
        return (float)($table[$platform] ?? $table['other'] ?? 0);
    }

    /**
     * Tạo dự án mới (chưa thanh toán).
     */
    public static function create(int $userId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên dự án.'];
        }

        $platform = (string)($data['platform'] ?? 'laravel');
        if (!isset(self::PLATFORMS[$platform])) {
            $platform = 'other';
        }

        $price = self::priceFor($platform);

        $id = Database::insert('website_projects', [
            'user_id'      => $userId,
            'name'         => mb_substr($name, 0, 150),
            'platform'     => $platform,
            'domain'       => trim((string)($data['domain'] ?? '')) ?: null,
            'email'        => trim((string)($data['email'] ?? '')) ?: null,
            'telegram'     => trim((string)($data['telegram'] ?? '')) ?: null,
            'contact_info' => json_encode([
                'phone'    => trim((string)($data['phone'] ?? '')),
                'facebook' => trim((string)($data['facebook'] ?? '')),
                'zalo'     => trim((string)($data['zalo'] ?? '')),
                'address'  => trim((string)($data['address'] ?? '')),
            ], JSON_UNESCAPED_UNICODE),
            'images'       => json_encode($data['images'] ?? [], JSON_UNESCAPED_UNICODE),
            'description'  => trim((string)($data['description'] ?? '')) ?: null,
            'price'        => $price,
            'status'       => 'draft',
        ]);

        return ['ok' => true, 'message' => 'Đã tạo dự án. Vui lòng thanh toán để tiếp tục.', 'id' => $id, 'price' => $price];
    }

    /**
     * Thanh toán dự án → chuyển sang trạng thái demo, báo admin.
     */
    public static function pay(int $userId, int $projectId): array
    {
        $p = Database::fetch('SELECT * FROM website_projects WHERE id = ? AND user_id = ?', [$projectId, $userId]);
        if (!$p) {
            return ['ok' => false, 'message' => 'Không tìm thấy dự án.'];
        }
        if ($p['status'] !== 'draft') {
            return ['ok' => false, 'message' => 'Dự án này đã được thanh toán.'];
        }

        $price = (float)$p['price'];
        try {
            Database::transaction(function () use ($userId, $projectId, $price, $p) {
                \App\Core\Wallet::apply(
                    $userId,
                    'adjustment',
                    -$price,
                    'Thanh toán dự án website: ' . $p['name'],
                    'website_project',
                    (string)$projectId
                );

                Database::update('website_projects', [
                    'status'   => 'demo',
                    'demo_url' => $p['demo_url'] ?: (string)setting('website_builder_demo_url', ''),
                ], 'id = :id', ['id' => $projectId]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        // Báo telegram cho admin
        TelegramService::notifyWebsiteProject($p);
        Database::update('website_projects', ['notified' => 1], 'id = :id', ['id' => $projectId]);

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'website.project_paid', $userId, (string)$projectId, [
            'price' => $price,
        ]);

        return ['ok' => true, 'message' => 'Thanh toán thành công. Dự án demo sẽ hiển thị ngay.'];
    }

    public static function myProjects(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM website_projects WHERE user_id = ? ORDER BY id DESC LIMIT 100',
            [$userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM website_projects WHERE id = ?', [$id]);
    }
}
