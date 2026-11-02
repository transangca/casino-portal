<?php
namespace App\Services;

use App\Core\Database;

/**
 * Tin tức / sự kiện / khuyến mãi.
 */
class NewsService
{
    public const TYPES = [
        'news'      => 'Tin tức',
        'event'     => 'Sự kiện',
        'promotion' => 'Khuyến mãi',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function latest(int $limit = 6, ?string $type = null): array
    {
        if ($type) {
            return Database::fetchAll(
                'SELECT * FROM news_posts WHERE is_active = 1 AND type = ?
                 ORDER BY is_pinned DESC, published_at DESC LIMIT ' . (int)$limit,
                [$type]
            );
        }

        return Database::fetchAll(
            'SELECT * FROM news_posts WHERE is_active = 1
             ORDER BY is_pinned DESC, published_at DESC LIMIT ' . (int)$limit
        );
    }

    public static function all(string $type = '', int $limit = 20, int $offset = 0): array
    {
        if ($type !== '' && isset(self::TYPES[$type])) {
            return Database::fetchAll(
                'SELECT * FROM news_posts WHERE is_active = 1 AND type = ?
                 ORDER BY is_pinned DESC, published_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
                [$type]
            );
        }

        return Database::fetchAll(
            'SELECT * FROM news_posts WHERE is_active = 1
             ORDER BY is_pinned DESC, published_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        );
    }

    public static function count(string $type = ''): int
    {
        if ($type !== '' && isset(self::TYPES[$type])) {
            return (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM news_posts WHERE is_active = 1 AND type = ?',
                [$type]
            );
        }
        return (int)Database::fetchColumn('SELECT COUNT(*) FROM news_posts WHERE is_active = 1');
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM news_posts WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    }
}
