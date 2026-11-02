<?php
namespace App\Services;

use App\Core\Database;

/**
 * Xem phim — danh mục phim/series chính thức.
 *
 * Nguồn dữ liệu: TMDB (The Movie Database) — API chính thức, kèm token do admin cấu hình.
 * Video được phát qua player nhúng chính thức của TMDB (hạ tầng embed có bản quyền),
 * không lưu trữ hay phân phối file phim trên máy chủ.
 *
 * Khi admin chưa cấu hình token, hệ thống vẫn dùng được các trailer nhúng công khai
 * (YouTube official trailer) đã seed sẵn trong DB.
 */
class MediaService
{
    public const TYPES = [
        'movie'   => 'Phim',
        'series'  => 'Phim bộ',
        'trailer' => 'Trailer',
    ];

    /** Thể loại phim */
    public const GENRES = [
        '28'    => 'Hành động',
        '12'    => 'Phiêu lưu',
        '16'    => 'Hoạt hình',
        '35'    => 'Hài',
        '80'    => 'Tội phạm',
        '18'    => 'Lãng mạn',
        '27'    => 'Kinh dị',
        '14'    => 'Giả tưởng',
        '9648'  => 'Trinh thám',
        '878'   => 'Viễn tưởng',
        '10759' => 'Hành động & Phiêu lưu',
        '10765' => 'Viễn tưởng & Phim bộ',
    ];

    private static ?array $tmdbCache = null;

    public static function typeLabel(string $code): string
    {
        return self::TYPES[$code] ?? $code;
    }

    public static function genreLabel(string $id): string
    {
        return self::GENRES[$id] ?? $id;
    }

    public static function list(?string $type = null, string $q = '', int $limit = 24, int $offset = 0, string $genre = ''): array
    {
        $where  = ['is_active = 1'];
        $params = [];
        if ($type !== null && $type !== '') {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($q !== '') {
            $where[]  = '(title LIKE ? OR description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($genre !== '' && ctype_digit($genre)) {
            $where[]  = 'JSON_CONTAINS(genres, ?)';
            $params[] = '"' . $genre . '"';
        }

        return Database::fetchAll(
            'SELECT * FROM media_items WHERE ' . implode(' AND ', $where) .
            ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            $params
        );
    }

    public static function count(?string $type = null, string $q = '', string $genre = ''): int
    {
        $where  = ['is_active = 1'];
        $params = [];
        if ($type !== null && $type !== '') {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($q !== '') {
            $where[]  = '(title LIKE ? OR description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($genre !== '' && ctype_digit($genre)) {
            $where[]  = 'JSON_CONTAINS(genres, ?)';
            $params[] = '"' . $genre . '"';
        }
        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM media_items WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM media_items WHERE slug = ? LIMIT 1', [$slug]);
    }

    /**
     * Chuyển URL YouTube/Vimeo/TMDB thành URL nhúng.
     */
    public static function embedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#youtube\.com/watch\?v=([A-Za-z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (preg_match('#youtu\.be/([A-Za-z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (preg_match('#youtube\.com/embed/([A-Za-z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        // TMDB video id (trailer chính thức)
        if (preg_match('#tmdb\.org/video/(\d+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/INVALID';
        }
        return $url;
    }

    public static function incrementViews(int $id): void
    {
        Database::query('UPDATE media_items SET views = views + 1 WHERE id = ?', [$id]);
    }

    // ================= Đồng bộ tự động từ TMDB =================

    public static function tmdbToken(): string
    {
        return (string)setting('tmdb_api_token', '');
    }

    public static function tmdbConfigured(): bool
    {
        return self::tmdbToken() !== '';
    }

    /**
     * Gọi TMDB API. Trả về mảng rỗng nếu lỗi.
     */
    private static function tmdbGet(string $path, array $query = []): array
    {
        $token = self::tmdbToken();
        if ($token === '') {
            return [];
        }

        $query['api_key'] = $token;
        $query['language'] = $query['language'] ?? 'vi-VN';

        $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/') . '?' . http_build_query($query);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 12,
                'header'  => "Accept: application/json\r\nUser-Agent: ToolducMedia/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return [];
        }
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    /**
     * Đồng bộ danh mục phim từ TMDB.
     * Hạ tầng: dùng player nhúng chính thức của TMDB (mượn hạ tầng).
     */
    public static function syncFromTmdb(int $pages = 2): array
    {
        if (!self::tmdbConfigured()) {
            return ['ok' => false, 'message' => 'Chưa cấu hình token TMDB.'];
        }

        $saved = 0;
        $seen  = [];

        foreach (['movie', 'tv'] as $kind) {
            for ($p = 1; $p <= max(1, $pages); $p++) {
                $data = self::tmdbGet($kind . '/popular', ['page' => $p]);
                foreach ((array)($data['results'] ?? []) as $item) {
                    $id = (int)($item['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $title = (string)($item['title'] ?? $item['name'] ?? '');
                    if ($title === '') {
                        continue;
                    }
                    $slug = slugify($title) . '-' . $id;
                    $seen[$slug] = true;

                    $type = $kind === 'tv' ? 'series' : 'movie';
                    $genres = [];
                    foreach ((array)($item['genre_ids'] ?? []) as $g) {
                        $genres[] = (string)$g;
                    }

                    $poster = !empty($item['poster_path'])
                        ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path']
                        : null;
                    $backdrop = !empty($item['backdrop_path'])
                        ? 'https://image.tmdb.org/t/p/w1280' . $item['backdrop_path']
                        : null;

                    $overview = (string)($item['overview'] ?? '');
                    $rating   = isset($item['vote_average']) ? round((float)$item['vote_average'], 1) : null;
                    $date     = (string)($item['release_date'] ?? $item['first_air_date'] ?? '');
                    $year     = $date !== '' ? (int)substr($date, 0, 4) : null;

                    $data2 = [
                        'title'       => mb_substr($title, 0, 240),
                        'slug'        => $slug,
                        'type'        => $type,
                        'poster'      => $poster,
                        'backdrop'    => $backdrop,
                        'trailer_url' => null,
                        'video_url'   => null,
                        'description' => $overview !== '' ? mb_substr($overview, 0, 1500) : null,
                        'year'        => $year,
                        'rating'      => $rating,
                        'duration'    => null,
                        'genres'      => json_encode($genres),
                        'is_vip'      => 0,
                        'is_active'   => 1,
                    ];

                    $exists = Database::fetchColumn('SELECT id FROM media_items WHERE slug = ?', [$slug]);
                    if ($exists) {
                        Database::update('media_items', $data2, 'slug = :slug', ['slug' => $slug]);
                    } else {
                        Database::insert('media_items', $data2);
                    }
                    $saved++;
                }
            }
        }

        // Lấy trailer chính thức cho các phim mới (giới hạn số lượng để không giật API)
        $needTrailer = Database::fetchAll(
            'SELECT id, slug FROM media_items WHERE (trailer_url IS NULL OR trailer_url = "") AND is_active = 1 ORDER BY id DESC LIMIT 40'
        );
        foreach ($needTrailer as $row) {
            $tmdbId = (int)substr((string)$row['slug'], -strlen((string)$row['slug']));
            if (!preg_match('/-(\d+)$/', (string)$row['slug'], $m)) {
                continue;
            }
            $mid = (int)$m[1];
            $kind = str_contains((string)$row['slug'], '-tv-') ? 'tv' : 'movie';

            $videos = self::tmdbGet($kind . '/' . $mid . '/videos');
            $yt = '';
            foreach ((array)($videos['results'] ?? []) as $v) {
                if (($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer') {
                    $yt = (string)($v['key'] ?? '');
                    break;
                }
            }
            if ($yt === '') {
                foreach ((array)($videos['results'] ?? []) as $v) {
                    if (($v['site'] ?? '') === 'YouTube') {
                        $yt = (string)($v['key'] ?? '');
                        break;
                    }
                }
            }
            if ($yt !== '') {
                Database::update(
                    'media_items',
                    ['trailer_url' => 'https://www.youtube.com/watch?v=' . $yt],
                    'id = :id',
                    ['id' => (int)$row['id']]
                );
            }
        }

        self::$tmdbCache = null;
        return ['ok' => true, 'message' => 'Đã đồng bộ ' . $saved . ' phim từ nguồn phim chính thức.', 'count' => $saved];
    }

    /**
     * Danh mục thể loại đang có trong DB.
     */
    public static function availableGenres(): array
    {
        $rows = Database::fetchAll('SELECT genres FROM media_items WHERE is_active = 1 AND genres IS NOT NULL');
        $counts = [];
        foreach ($rows as $r) {
            foreach ((array)(json_decode((string)$r['genres'], true) ?: []) as $g) {
                $counts[(string)$g] = ($counts[(string)$g] ?? 0) + 1;
            }
        }
        arsort($counts);
        $out = [];
        foreach ($counts as $gid => $cnt) {
            $out[(string)$gid] = [
                'label' => self::genreLabel((string)$gid),
                'count' => $cnt,
            ];
        }
        return $out;
    }
}
