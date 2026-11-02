<?php
namespace App\Services;

use App\Core\Database;

/**
 * Quảng cáo / banner.
 *
 * Các vị trí (slot) dùng trong giao diện:
 *   home_top      : banner lớn đầu trang chủ
 *   home_mid      : banner giữa trang chủ
 *   lobby_top     : banner đầu trang sảnh
 *   sidebar       : banner cột bên
 *   login         : banner trang đăng nhập
 *   footer        : banner cuối trang
 *
 * Admin có thể thêm/sửa/xoá trong /admin/ads.
 */
class AdService
{
    public const SLOTS = [
        'home_top'  => 'Trang chủ - đầu trang',
        'home_mid'  => 'Trang chủ - giữa trang',
        'lobby_top' => 'Trang sảnh - đầu trang',
        'sidebar'   => 'Cột bên',
        'login'     => 'Trang đăng nhập',
        'footer'    => 'Cuối trang',
    ];

    public static function slotLabel(string $slot): string
    {
        return self::SLOTS[$slot] ?? $slot;
    }

    /**
     * Lấy các banner đang hiệu lực cho 1 slot.
     */
    public static function forSlot(string $slot, int $limit = 5): array
    {
        return Database::fetchAll(
            'SELECT * FROM ads_banners
             WHERE slot = ? AND is_active = 1
               AND (start_at IS NULL OR start_at <= NOW())
               AND (end_at IS NULL OR end_at >= NOW())
             ORDER BY sort_order, id DESC LIMIT ' . (int)$limit,
            [$slot]
        );
    }

    /** Banner đầu tiên của slot (dùng cho dải slider) */
    public static function first(string $slot): ?array
    {
        $rows = self::forSlot($slot, 1);
        return $rows[0] ?? null;
    }

    /** Tăng lượt hiển thị */
    public static function impression(int $id): void
    {
        Database::query('UPDATE ads_banners SET impressions = impressions + 1 WHERE id = ?', [$id]);
    }

    /** Tăng lượt click */
    public static function click(int $id): void
    {
        Database::query('UPDATE ads_banners SET clicks = clicks + 1 WHERE id = ?', [$id]);
    }

    /**
     * Render 1 banner ra HTML (dùng chung cho mọi slot).
     */
    public static function render(array $ad, bool $carousel = false): string
    {
        $img = media_url((string)($ad['image_url'] ?? ''));
        $vid = media_url((string)($ad['video_url'] ?? ''));
        $link = (string)($ad['link_url'] ?? '');
        $title = (string)($ad['title'] ?? '');
        $desc = (string)($ad['description'] ?? '');

        $media = '';
        if ($vid !== '') {
            $poster = media_url((string)($ad['position'] ?? ''));
            $media = '<video src="' . e($vid) . '" autoplay muted loop playsinline'
                . ($poster !== '' ? ' poster="' . e($poster) . '"' : '')
                . '></video>';
        } elseif ($img !== '') {
            $media = '<img src="' . e($img) . '" alt="' . e($title) . '" loading="lazy" decoding="async">';
        } else {
            return '';
        }

        $caption = '';
        if ($title !== '' || $desc !== '') {
            $caption = '<div class="ad-caption">'
                . ($title !== '' ? '<h4>' . e($title) . '</h4>' : '')
                . ($desc !== '' ? '<p>' . e($desc) . '</p>' : '')
                . '</div>';
        }

        $attrs = 'data-ad-id="' . (int)$ad['id'] . '" class="ad-banner"';
        if ($link !== '') {
            $attrs .= ' href="' . e($link) . '" target="_blank" rel="noopener sponsored"';
        } else {
            $attrs .= ' href="#"';
        }

        return '<a ' . $attrs . '><span class="ad-label">Quảng cáo</span>'
            . $media . $caption . '</a>';
    }
}
