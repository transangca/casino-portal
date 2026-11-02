<?php
namespace App\Services;

use App\Core\Database;

/**
 * Forum cộng đồng — thành viên chia sẻ bài viết, hỏi đáp.
 */
class ForumService
{
    public const CATEGORIES = [
        'general'   => 'Thảo luận chung',
        'game'      => 'Game & sảnh',
        'promo'     => 'Khuyến mãi',
        'support'   => 'Hỗ trợ',
        'tech'      => 'Kỹ thuật - API',
        'feedback'  => 'Góp ý',
    ];

    public static function categoryLabel(string $code): string
    {
        return self::CATEGORIES[$code] ?? $code;
    }

    public static function topics(?string $category = null, int $limit = 30, int $offset = 0): array
    {
        $where  = [];
        $params = [];
        if ($category !== null && $category !== '') {
            $where[]  = 't.category = ?';
            $params[] = $category;
        }
        $w = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        return Database::fetchAll(
            'SELECT t.*, u.username, u.is_verified, u.avatar,
                    (SELECT COUNT(*) FROM forum_posts p WHERE p.topic_id = t.id) AS post_count
             FROM forum_topics t JOIN users u ON u.id = t.user_id' . $w . '
             ORDER BY t.is_pinned DESC, t.last_post_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            $params
        );
    }

    public static function countTopics(?string $category = null): int
    {
        if ($category !== null && $category !== '') {
            return (int)Database::fetchColumn('SELECT COUNT(*) FROM forum_topics WHERE category = ?', [$category]);
        }
        return (int)Database::fetchColumn('SELECT COUNT(*) FROM forum_topics');
    }

    public static function findTopic(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, u.username, u.is_verified, u.avatar FROM forum_topics t
             JOIN users u ON u.id = t.user_id WHERE t.id = ?',
            [$id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        $id = Database::fetchColumn('SELECT id FROM forum_topics WHERE slug = ?', [$slug]);
        return $id ? self::findTopic((int)$id) : null;
    }

    public static function createTopic(int $userId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $body  = trim((string)($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tiêu đề và nội dung.'];
        }

        $category = (string)($data['category'] ?? 'general');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'general';
        }

        $slug = slugify($title);
        $i = 1;
        $base = $slug;
        while (Database::fetchColumn('SELECT id FROM forum_topics WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i++;
            if ($i > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        $id = Database::insert('forum_topics', [
            'user_id'   => $userId,
            'category'  => $category,
            'title'     => mb_substr($title, 0, 250),
            'slug'      => $slug,
            'body'      => $body,
            'last_post_at' => now(),
        ]);

        Database::insert('forum_posts', [
            'topic_id' => $id,
            'user_id'  => $userId,
            'body'     => $body,
        ]);

        return ['ok' => true, 'message' => 'Đã đăng bài.', 'id' => $id, 'slug' => $slug];
    }

    public static function posts(int $topicId): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.username, u.is_verified, u.avatar, u.vip_level FROM forum_posts p
             JOIN users u ON u.id = p.user_id WHERE p.topic_id = ? ORDER BY p.id ASC',
            [$topicId]
        );
    }

    public static function reply(int $topicId, int $userId, string $body): array
    {
        $topic = self::findTopic($topicId);
        if (!$topic) {
            return ['ok' => false, 'message' => 'Chủ đề không tồn tại.'];
        }
        if ($topic['is_locked']) {
            return ['ok' => false, 'message' => 'Chủ đề đã khoá.'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'message' => 'Nội dung trống.'];
        }

        Database::insert('forum_posts', [
            'topic_id' => $topicId,
            'user_id'  => $userId,
            'body'     => $body,
        ]);

        Database::query(
            'UPDATE forum_topics SET reply_count = reply_count + 1, last_post_at = NOW() WHERE id = ?',
            [$topicId]
        );

        return ['ok' => true, 'message' => 'Đã trả lời.'];
    }

    public static function markSolution(int $postId, int $userId): bool
    {
        $post = Database::fetch('SELECT * FROM forum_posts WHERE id = ?', [$postId]);
        if (!$post) {
            return false;
        }
        $topic = self::findTopic((int)$post['topic_id']);
        // Chủ topic hoặc admin mới đánh dấu được
        if (!$topic || ((int)$topic['user_id'] !== $userId && !\App\Core\Auth::isAdmin())) {
            return false;
        }
        Database::query('UPDATE forum_posts SET is_solution = 0 WHERE topic_id = ?', [(int)$post['topic_id']]);
        Database::update('forum_posts', ['is_solution' => 1], 'id = :id', ['id' => $postId]);
        return true;
    }

    public static function recentTopics(int $limit = 5): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.username FROM forum_topics t JOIN users u ON u.id = t.user_id
             ORDER BY t.last_post_at DESC LIMIT ' . (int)$limit
        );
    }
}
