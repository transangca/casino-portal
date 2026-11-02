<?php
namespace App\Services;

use App\Core\Database;

/**
 * DỊCH VỤ COPY 1-1 TRANG WEB
 * ------------------------------------------------------------------
 * Dán link website → hệ thống:
 *   1. Tải trang chính + các trang con cùng domain
 *   2. Tải TOÀN BỘ asset (CSS, ảnh, JS, font) về đĩa
 *   3. Viết lại mọi liên kết trong HTML/CSS trỏ về file cục bộ
 *   4. Lưu thành nhiều file index.html theo cấu trúc thư mục
 *   5. Đóng gói ZIP — mở ra là 1 website hoàn chỉnh chạy offline
 */
class SiteCloneService
{
    public const STORAGE = 'site-clone';

    /** Giới hạn */
    public const MAX_PAGES   = 40;
    public const MAX_DEPTH   = 2;
    public const MAX_ASSETS  = 600;

    // =================================================================
    //  Tạo job
    // =================================================================

    /**
     * Tạo dự án clone mới.
     */
    public static function create(int $userId, string $rawUrl, array $opts = []): array
    {
        $check = SiteCrawlerService::validateUrl($rawUrl);
        if (!$check['ok']) {
            return ['ok' => false, 'message' => $check['message'], 'id' => 0];
        }

        $url = SiteCrawlerService::normalizeUrl($rawUrl);

        $id = Database::insert('site_clones', [
            'user_id'     => $userId,
            'source_url'  => mb_substr($url, 0, 500),
            'host'        => mb_substr($check['host'], 0, 120),
            'title'       => null,
            'status'      => 'pending',
            'pages_total' => 0,
            'pages_done'  => 0,
            'assets_total'=> 0,
            'price'       => 0,
        ]);

        return ['ok' => true, 'message' => 'Đã tạo dự án. Hệ thống bắt đầu sao chép.', 'id' => $id];
    }

    /**
     * Lấy dự án.
     */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM site_clones WHERE id = ?', [$id]);
    }

    /**
     * Danh sách dự án của user.
     */
    public static function myProjects(int $userId, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT * FROM site_clones WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Tất cả dự án (admin).
     */
    public static function allProjects(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT sc.*, u.username FROM site_clones sc
             LEFT JOIN users u ON u.id = sc.user_id
             ORDER BY sc.id DESC LIMIT ' . (int)$limit
        );
    }

    // =================================================================
    //  Chạy clone
    // =================================================================

    /**
     * Chạy sao chép cho 1 dự án.
     */
    public static function run(int $id): array
    {
        $project = self::find($id);
        if (!$project) {
            return ['ok' => false, 'message' => 'Không tìm thấy dự án.'];
        }
        if ($project['status'] === 'done') {
            return ['ok' => false, 'message' => 'Dự án đã hoàn thành.'];
        }
        if ($project['status'] === 'scanning') {
            return ['ok' => false, 'message' => 'Dự án đang được sao chép.'];
        }

        Database::update('site_clones', [
            'status'     => 'scanning',
            'error_msg'  => null,
            'pages_done' => 0,
        ], 'id = :id', ['id' => $id]);

        $sourceUrl = (string)$project['source_url'];
        $rootUrl   = SiteCrawlerService::normalizeUrl($sourceUrl);
        $rootHost  = strtolower((string)parse_url($rootUrl, PHP_URL_HOST) ?: '');

        // ---- 1. Tải trang chính ----
        $page = SiteCrawlerService::fetchPage($rootUrl, 30);
        if (!$page['ok']) {
            return self::fail($id, $page['message']);
        }

        $base = SiteCrawlerService::baseUrl($page['final_url'] ?: $rootUrl);
        $res  = SiteCrawlerService::parseHtml($page['html'], $base);

        // ---- 2. Thu thập danh sách trang cần clone ----
        $pages = [self::pageKey($page['final_url'] ?: $rootUrl) => $page['final_url'] ?: $rootUrl];
        $queue = [];

        foreach (array_slice($res['links'], 0, self::MAX_PAGES * 3) as $link) {
            $host = strtolower((string)parse_url($link, PHP_URL_HOST) ?: '');
            if ($host !== $rootHost) {
                continue;
            }
            $key = self::pageKey($link);
            if (isset($pages[$key])) {
                continue;
            }
            if (count($pages) + count($queue) >= self::MAX_PAGES) {
                break;
            }
            $queue[] = $link;
        }

        $pages = array_merge($pages, array_map(
            static fn($u) => self::pageKey($u),
            $queue
        ));
        $pages = array_slice($pages, 0, self::MAX_PAGES, true);

        Database::update('site_clones', [
            'pages_total' => count($pages),
            'assets_total'=> 0,
        ], 'id = :id', ['id' => $id]);

        // ---- 3. Chuẩn bị thư mục ----
        $slug   = preg_replace('/[^a-z0-9]+/i', '-', (string)($project['host'] ?? 'site')) ?: 'site';
        $folder = 'clone-' . trim($slug, '-') . '-' . date('YmdHis');
        $workDir = BASE_PATH . '/storage/' . self::STORAGE . '/job-' . $id . '/' . $folder;

        if (!is_dir($workDir) && !@mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            return self::fail($id, 'Không tạo được thư mục lưu trữ.');
        }

        $assetMap   = [];   // url => path cục bộ
        $downloaded = [];
        $pageFiles  = [];
        $donePages  = 0;
        $seenAssets = [];

        // ---- 4. Tải asset từ trang chính ----
        self::downloadAssets($res, $base, $workDir, $assetMap, $downloaded, $seenAssets);

        // ---- 5. Clone từng trang ----
        foreach ($pages as $key => $pageUrl) {
            if ($key === self::pageKey($page['final_url'] ?: $rootUrl)) {
                $html = $page['html'];
                $thisBase = $base;
            } else {
                $p = SiteCrawlerService::fetchPage($pageUrl, 20);
                if (!$p['ok']) {
                    continue;
                }
                $html     = $p['html'];
                $thisBase = SiteCrawlerService::baseUrl($p['final_url'] ?: $pageUrl);
                $pr       = SiteCrawlerService::parseHtml($html, $thisBase);
                self::downloadAssets($pr, $thisBase, $workDir, $assetMap, $downloaded, $seenAssets);
            }

            $donePages++;
            Database::update('site_clones', [
                'pages_done' => $donePages,
                'assets_total' => count($downloaded),
            ], 'id = :id', ['id' => $id]);

            // Ghi file index.html theo cấu trúc đường dẫn
            $relPath = self::relativePath($key, $rootUrl);
            $full    = $workDir . '/' . $relPath;
            $dir     = dirname($full);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $rewritten = SiteCrawlerService::rewriteHtml($html, $assetMap, $thisBase);
            @file_put_contents($full, $rewritten);
            $pageFiles[] = ['path' => $relPath, 'url' => $pageUrl, 'size' => strlen($rewritten)];
        }

        // ---- 6. Ghi lại CSS đã tải (rewrite url) ----
        foreach ($assetMap as $url => $rel) {
            if (!str_ends_with($rel, '.css')) {
                continue;
            }
            $full = $workDir . '/' . $rel;
            if (!is_file($full)) {
                continue;
            }
            $css = (string)@file_get_contents($full);
            @file_put_contents($full, SiteCrawlerService::rewriteCss($css, $assetMap));
        }

        // ---- 7. Manifest ----
        $manifest = [
            'source'     => $rootUrl,
            'host'       => $project['host'],
            'title'      => $res['title'],
            'cloned_at'  => date('Y-m-d H:i:s'),
            'folder'     => $folder,
            'pages'      => $pageFiles,
            'counts'     => [
                'pages'     => count($pageFiles),
                'assets'    => count($downloaded),
                'css'       => count($res['stylesheets']),
                'images'    => count($res['images']),
                'scripts'   => count($res['scripts']),
            ],
            'assets'     => $downloaded,
        ];
        @file_put_contents($workDir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // ---- 8. Đóng gói ----
        $zipPath = $workDir . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return self::fail($id, 'Không tạo được file ZIP.');
        }
        UiScraperService::addDirToZip($zip, $workDir, 'clone-' . trim($slug, '-'));
        $zip->close();

        if (!is_file($zipPath)) {
            return self::fail($id, 'Không tạo được file ZIP.');
        }

        UiScraperService::rmDir($workDir);

        // ---- 9. Hoàn tất ----
        Database::update('site_clones', [
            'status'      => 'done',
            'title'       => $res['title'] ?: $project['title'],
            'pages_total' => count($pageFiles),
            'pages_done'  => count($pageFiles),
            'assets_total'=> count($downloaded),
            'zip_size'    => (int)filesize($zipPath),
            'result_json' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $id]);

        return ['ok' => true, 'message' => 'Sao chép xong ' . count($pageFiles) . ' trang.'];
    }

    /**
     * Tải asset của 1 trang.
     */
    private static function downloadAssets(
        array $res,
        string $base,
        string $workDir,
        array &$assetMap,
        array &$downloaded,
        array &$seenAssets
    ): void {
        // CSS
        foreach (array_slice($res['stylesheets'], 0, SiteCrawlerService::MAX_CSS_FILES) as $cssUrl) {
            if (isset($seenAssets[$cssUrl])) {
                continue;
            }
            $seenAssets[$cssUrl] = true;

            $content = SiteCrawlerService::fetchCss($cssUrl);
            if ($content === null) {
                continue;
            }
            $rel = 'assets/css/' . SiteCrawlerService::safeName($cssUrl, 'css');
            $full = $workDir . '/' . $rel;
            if (!is_dir(dirname($full))) {
                @mkdir(dirname($full), 0775, true);
            }
            @file_put_contents($full, $content);
            $assetMap[$cssUrl] = $rel;
            $downloaded[] = ['url' => $cssUrl, 'file' => $rel, 'size' => strlen($content), 'mime' => 'text/css'];

            // Asset trong CSS
            if (preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $content, $m)) {
                $cssBase = SiteCrawlerService::baseUrl($cssUrl);
                foreach ($m[1] as $raw) {
                    $raw = trim($raw);
                    if ($raw === '' || str_starts_with($raw, 'data:')) {
                        continue;
                    }
                    $abs = SiteCrawlerService::absolute($raw, $cssBase);
                    if ($abs === '' || isset($seenAssets[$abs])) {
                        continue;
                    }
                    $seenAssets[$abs] = true;

                    $isFont = SiteCrawlerService::isFont($abs);
                    $ext    = $isFont ? 'woff2' : SiteCrawlerService::imageExt($abs);
                    $dir    = $isFont ? 'fonts' : 'img';
                    $r      = 'assets/' . $dir . '/' . SiteCrawlerService::safeName($abs, $ext);

                    $d = SiteCrawlerService::downloadAsset($abs, $workDir, $r);
                    if ($d['ok']) {
                        $assetMap[$abs] = $r;
                        $downloaded[] = ['url' => $abs, 'file' => $r, 'size' => $d['size'], 'mime' => $d['mime']];
                    }
                }
            }
        }

        // Ảnh
        foreach (array_slice($res['images'], 0, SiteCrawlerService::MAX_IMAGES) as $imgUrl) {
            if (isset($seenAssets[$imgUrl]) || count($downloaded) >= self::MAX_ASSETS) {
                continue;
            }
            $seenAssets[$imgUrl] = true;

            $ext = SiteCrawlerService::imageExt($imgUrl);
            $rel = 'assets/img/' . SiteCrawlerService::safeName($imgUrl, $ext);
            $d   = SiteCrawlerService::downloadAsset($imgUrl, $workDir, $rel);
            if ($d['ok']) {
                $assetMap[$imgUrl] = $rel;
                $downloaded[] = ['url' => $imgUrl, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
            }
        }

        // Script
        foreach (array_slice($res['scripts'], 0, SiteCrawlerService::MAX_JS_FILES) as $jsUrl) {
            if (isset($seenAssets[$jsUrl]) || count($downloaded) >= self::MAX_ASSETS) {
                continue;
            }
            $seenAssets[$jsUrl] = true;

            $rel = 'assets/js/' . SiteCrawlerService::safeName($jsUrl, 'js');
            $d   = SiteCrawlerService::downloadAsset($jsUrl, $workDir, $rel);
            if ($d['ok']) {
                $assetMap[$jsUrl] = $rel;
                $downloaded[] = ['url' => $jsUrl, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
            }
        }
    }

    /**
     * Khoá trang thành key (path + query).
     */
    private static function pageKey(string $url): string
    {
        $p = parse_url($url);
        if ($p === false) {
            return $url;
        }
        $path  = (string)($p['path'] ?? '/');
        $path  = '/' . trim($path, '/');
        $query = (string)($p['query'] ?? '');
        return $path . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Đường dẫn tương đối (index.html) cho 1 trang.
     */
    private static function relativePath(string $key, string $rootUrl): string
    {
        $path = explode('?', $key)[0];
        $path = trim($path, '/');
        if ($path === '') {
            return 'index.html';
        }
        $path = preg_replace('/[^A-Za-z0-9_\-\/\.]/', '-', $path) ?? $path;
        if (str_ends_with(strtolower($path), '.html') || str_ends_with(strtolower($path), '.htm')) {
            return $path;
        }
        return $path . '/index.html';
    }

    private static function fail(int $id, string $message): array
    {
        Database::update('site_clones', [
            'status'    => 'failed',
            'error_msg' => mb_substr($message, 0, 255),
        ], 'id = :id', ['id' => $id]);
        return ['ok' => false, 'message' => $message];
    }

    /**
     * Đường dẫn ZIP của dự án.
     */
    public static function zipOf(int $id): ?string
    {
        $project = self::find($id);
        if (!$project || $project['status'] !== 'done') {
            return null;
        }
        // Đường dẫn ZIP lưu trong result_json
        $meta = json_decode((string)($project['result_json'] ?? ''), true);
        $folder = (string)($meta['folder'] ?? '');
        if ($folder === '') {
            return null;
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string)($project['host'] ?? 'site')) ?: 'site';
        $path = BASE_PATH . '/storage/' . self::STORAGE . '/job-' . $id . '/' . $folder . '.zip';
        return is_file($path) ? $path : null;
    }

    /**
     * Xoá dự án + ZIP.
     */
    public static function delete(int $id, int $userId): void
    {
        $project = self::find($id);
        if (!$project || (int)$project['user_id'] !== $userId) {
            return;
        }
        $zip = self::zipOf($id);
        if ($zip !== null) {
            @unlink($zip);
        }
        $dir = BASE_PATH . '/storage/' . self::STORAGE . '/job-' . $id;
        UiScraperService::rmDir($dir);
        Database::delete('site_clones', 'id = ? AND user_id = ?', [$id, $userId]);
    }
}
