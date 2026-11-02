<?php
namespace App\Services;

use App\Core\Database;

/**
 * DỊCH VỤ ĐỤC UI/UX WEBSITE (bản chạy thật)
 * ------------------------------------------------------------------
 * Dán link 1 website (casino / game) → hệ thống:
 *   1. Tải HTML trang
 *   2. Phân tích: CSS, ảnh, sprite, font, script, background-image, lazy-load
 *   3. TẢI THẬT toàn bộ tài nguyên về đĩa (không chỉ liệt kê URL)
 *   4. Viết lại liên kết trong HTML + CSS trỏ về file cục bộ
 *   5. Đóng gói thành 1 file ZIP hoàn chỉnh để tải xuống
 *
 * QUAN TRỌNG: ZIP chỉ được tạo SAU KHI quét xong. Trước đó form hiển thị
 * tiến trình theo thời gian thực (AJAX polling) — không "nhảy zip lên" ngay.
 */
class UiScraperService
{
    /** Thư mục lưu job */
    public const STORAGE = 'ui-scraper';

    /**
     * Chuẩn hoá URL.
     */
    public static function normalizeUrl(string $raw): string
    {
        return SiteCrawlerService::normalizeUrl($raw);
    }

    /**
     * Kiểm tra URL hợp lệ & an toàn.
     *
     * @return array{ok:bool,message:string,host:string}
     */
    public static function validateUrl(string $raw): array
    {
        return SiteCrawlerService::validateUrl($raw);
    }

    // =================================================================
    //  Job
    // =================================================================

    /**
     * Tạo job quét mới (chạy nền qua cron, hoặc chạy đồng bộ).
     */
    public static function createJob(int $userId, string $url, array $opts = []): int
    {
        $check = self::validateUrl($url);
        $url   = self::normalizeUrl($url);

        return Database::insert('scraper_jobs', [
            'user_id'      => $userId,
            'url'          => mb_substr($url, 0, 500),
            'host'         => mb_substr($check['host'], 0, 120),
            'status'       => 'queued',
            'step'         => 'Đang chờ xử lý',
            'progress'     => 0,
            'total_assets' => 0,
            'done_assets'  => 0,
            'counts'       => null,
            'result_json'  => null,
        ]);
    }

    /**
     * Lấy job theo id.
     */
    public static function job(int $id): ?array
    {
        return Database::fetch('SELECT * FROM scraper_jobs WHERE id = ?', [$id]);
    }

    /**
     * Lấy job mới nhất của user.
     */
    public static function lastJob(int $userId): ?array
    {
        return Database::fetch(
            'SELECT * FROM scraper_jobs WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [$userId]
        );
    }

    /**
     * Danh sách job gần đây của user.
     */
    public static function jobs(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM scraper_jobs WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }

    /**
     * Cập nhật tiến trình.
     */
    private static function progress(int $jobId, string $step, int $percent, int $done = 0, int $total = 0): void
    {
        Database::update('scraper_jobs', [
            'step'         => mb_substr($step, 0, 120),
            'progress'     => max(0, min(100, $percent)),
            'done_assets'  => $done,
            'total_assets' => $total,
        ], 'id = :id', ['id' => $jobId]);
    }

    // =================================================================
    //  Quét thật
    // =================================================================

    /**
     * Chạy quét cho 1 job (đồng bộ — dùng bởi cron hoặc khi user bấm "quét ngay").
     *
     * @return array{ok:bool,message:string,job:array}
     */
    public static function runJob(int $jobId): array
    {
        $job = self::job($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => 'Không tìm thấy job.', 'job' => []];
        }
        if ($job['status'] === 'done' || $job['status'] === 'running') {
            return ['ok' => false, 'message' => 'Job đã chạy hoặc đang chạy.', 'job' => $job];
        }

        $url = (string)$job['url'];

        Database::update('scraper_jobs', [
            'status'  => 'running',
            'step'    => 'Đang tải trang...',
            'progress'=> 2,
        ], 'id = :id', ['id' => $jobId]);

        // ---- 1. Tải HTML ----
        $page = SiteCrawlerService::fetchPage($url, 30);
        if (!$page['ok']) {
            return self::failJob($jobId, $page['message']);
        }

        $html = $page['html'];
        $base = SiteCrawlerService::baseUrl($page['final_url'] ?: $url);

        $res = SiteCrawlerService::parseHtml($html, $base);

        // ---- 2. Chuẩn bị thư mục ----
        $slug   = preg_replace('/[^a-z0-9]+/i', '-', (string)($job['host'] ?? 'site')) ?: 'site';
        $folder = 'ui-' . trim($slug, '-') . '-' . date('YmdHis', (int)strtotime((string)$job['created_at']));
        $workDir = BASE_PATH . '/storage/' . self::STORAGE . '/job-' . $jobId . '/' . $folder;

        if (!is_dir($workDir) && !@mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            return self::failJob($jobId, 'Không tạo được thư mục lưu trữ.');
        }

        $assetMap = [];       // url => đường dẫn tương đối trong ZIP
        $downloaded = [];     // ['url','file','size','mime']
        $errors     = [];

        // ---- 3. Tải CSS ----
        $cssFiles   = array_slice($res['stylesheets'], 0, SiteCrawlerService::MAX_CSS_FILES);
        $cssContent = [];
        $totalSteps = max(1, count($cssFiles) + count(array_slice($res['images'], 0, SiteCrawlerService::MAX_IMAGES))
            + count(array_slice($res['scripts'], 0, SiteCrawlerService::MAX_JS_FILES)));
        $stepIndex  = 0;

        foreach ($cssFiles as $i => $cssUrl) {
            $stepIndex++;
            self::progress($jobId, 'Đang tải CSS ' . $stepIndex . '/' . count($cssFiles) . '...',
                5 + (int)round(45 * $stepIndex / $totalSteps));

            $content = SiteCrawlerService::fetchCss($cssUrl);
            if ($content === null) {
                $errors[] = 'CSS: ' . $cssUrl;
                continue;
            }
            $rel = 'assets/css/' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '-'
                . SiteCrawlerService::safeName($cssUrl, 'css');
            $cssContent[$cssUrl] = $content;
            $assetMap[$cssUrl]    = $rel;

            // Asset nằm trong CSS (ảnh, font)
            self::collectCssAssets($content, $cssUrl, $workDir, $assetMap, $downloaded, $errors);
        }

        // ---- 4. Tải ảnh / sprite ----
        $images = array_slice($res['images'], 0, SiteCrawlerService::MAX_IMAGES);
        foreach ($images as $i => $imgUrl) {
            $stepIndex++;
            self::progress($jobId, 'Đang tải ảnh ' . ($i + 1) . '/' . count($images) . '...',
                5 + (int)round(45 * $stepIndex / $totalSteps));

            $ext  = SiteCrawlerService::imageExt($imgUrl);
            $name = SiteCrawlerService::safeName($imgUrl, $ext);
            $rel  = 'assets/img/' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT) . '-' . $name;

            $d = SiteCrawlerService::downloadAsset($imgUrl, $workDir, $rel);
            if (!$d['ok']) {
                $errors[] = 'IMG: ' . $imgUrl . ' (' . $d['error'] . ')';
                continue;
            }
            $assetMap[$imgUrl] = $rel;
            $downloaded[] = ['url' => $imgUrl, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
        }

        // ---- 5. Tải script ----
        $scripts = array_slice($res['scripts'], 0, SiteCrawlerService::MAX_JS_FILES);
        foreach ($scripts as $i => $jsUrl) {
            $stepIndex++;
            self::progress($jobId, 'Đang tải script ' . ($i + 1) . '/' . count($scripts) . '...',
                5 + (int)round(45 * $stepIndex / $totalSteps));

            $rel = 'assets/js/' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '-'
                . SiteCrawlerService::safeName($jsUrl, 'js');
            $d = SiteCrawlerService::downloadAsset($jsUrl, $workDir, $rel);
            if (!$d['ok']) {
                $errors[] = 'JS: ' . $jsUrl;
                continue;
            }
            $assetMap[$jsUrl] = $rel;
            $downloaded[] = ['url' => $jsUrl, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
        }

        // ---- 6. Tải font ----
        $fonts = array_slice($res['fonts'], 0, SiteCrawlerService::MAX_FONTS);
        foreach ($fonts as $i => $fUrl) {
            $stepIndex++;
            $rel = 'assets/fonts/' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '-'
                . SiteCrawlerService::safeName($fUrl);
            $d = SiteCrawlerService::downloadAsset($fUrl, $workDir, $rel);
            if ($d['ok']) {
                $assetMap[$fUrl] = $rel;
                $downloaded[] = ['url' => $fUrl, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
            }
        }

        // ---- 7. Viết file ra đĩa ----
        self::progress($jobId, 'Đang ghi lại CSS & HTML...', 60);

        // CSS đã tải về → ghi lại với url() trỏ file cục bộ
        foreach ($cssContent as $cssUrl => $content) {
            $rel = $assetMap[$cssUrl] ?? null;
            if ($rel === null) {
                continue;
            }
            $full = $workDir . '/' . $rel;
            $dir  = dirname($full);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($full, SiteCrawlerService::rewriteCss($content, $assetMap));
        }

        // HTML đã viết lại
        $finalHtml = SiteCrawlerService::rewriteHtml($html, $assetMap, $base);
        @file_put_contents($workDir . '/index.html', $finalHtml);

        // Inline CSS gộp
        $inlineBundle = '';
        foreach ($res['inline_css'] as $i => $css) {
            $inlineBundle .= "\n/* ==== inline style #" . ($i + 1) . " ==== */\n" . $css . "\n";
        }
        @file_put_contents($workDir . '/assets/css/inline.css', SiteCrawlerService::rewriteCss($inlineBundle, $assetMap));

        // ---- 8. Manifest ----
        self::progress($jobId, 'Đang tạo manifest...', 80);

        $manifest = [
            'source'       => $url,
            'final_url'    => $page['final_url'] ?: $url,
            'host'         => (string)($job['host'] ?? ''),
            'title'        => $res['title'],
            'description'  => $res['description'],
            'favicon'      => $res['favicon'],
            'theme_color'  => $res['theme_color'],
            'scanned_at'   => date('Y-m-d H:i:s'),
            'folder'       => $folder,
            'counts'       => [
                'css'     => count($cssContent),
                'inline'  => count($res['inline_css']),
                'images'  => count($images),
                'scripts' => count($scripts),
                'fonts'   => count($fonts),
                'downloaded' => count($downloaded),
                'errors'  => count($errors),
            ],
            'stylesheets' => array_keys($assetMap),
            'assets'      => $downloaded,
            'errors'      => array_slice($errors, 0, 50),
        ];
        @file_put_contents($workDir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // ---- 9. Đóng gói ZIP ----
        self::progress($jobId, 'Đang đóng gói ZIP...', 90);

        $zipPath = $workDir . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return self::failJob($jobId, 'Không tạo được file ZIP.');
        }

        $zipRoot = 'ui-' . trim($slug, '-');
        self::addDirToZip($zip, $workDir, $zipRoot);
        $zip->close();

        if (!is_file($zipPath)) {
            return self::failJob($jobId, 'Không tạo được file ZIP.');
        }

        // Dọn thư mục tạm
        self::rmDir($workDir);

        // ---- 10. Hoàn tất ----
        Database::update('scraper_jobs', [
            'status'      => 'done',
            'step'        => 'Hoàn tất — sẵn sàng tải ZIP',
            'progress'    => 100,
            'counts'      => json_encode($manifest['counts'], JSON_UNESCAPED_UNICODE),
            'zip_path'    => $zipPath,
            'result_json' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $jobId]);

        return ['ok' => true, 'message' => 'Quét xong.', 'job' => self::job($jobId)];
    }

    /**
     * Thu thập asset nằm trong CSS (url(...)).
     */
    private static function collectCssAssets(
        string $css,
        string $cssUrl,
        string $workDir,
        array &$assetMap,
        array &$downloaded,
        array &$errors
    ): void {
        if (!preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $css, $m)) {
            return;
        }
        $base = SiteCrawlerService::baseUrl($cssUrl);

        foreach ($m[1] as $raw) {
            $raw = trim($raw);
            if ($raw === '' || str_starts_with($raw, 'data:')) {
                continue;
            }
            $abs = SiteCrawlerService::absolute($raw, $base);
            if ($abs === '' || isset($assetMap[$abs])) {
                continue;
            }

            $isFont = SiteCrawlerService::isFont($abs);
            $ext    = $isFont ? 'woff2' : SiteCrawlerService::imageExt($abs);
            $dir    = $isFont ? 'fonts' : 'img';
            $rel    = 'assets/' . $dir . '/css-' . SiteCrawlerService::safeName($abs, $ext);

            $d = SiteCrawlerService::downloadAsset($abs, $workDir, $rel);
            if (!$d['ok']) {
                $errors[] = 'CSS-ASSET: ' . $abs;
                continue;
            }
            $assetMap[$abs] = $rel;
            $downloaded[] = ['url' => $abs, 'file' => $rel, 'size' => $d['size'], 'mime' => $d['mime']];
        }
    }

    private static function failJob(int $jobId, string $message): array
    {
        Database::update('scraper_jobs', [
            'status'    => 'failed',
            'step'      => 'Thất bại',
            'progress'  => 0,
            'error_msg' => mb_substr($message, 0, 255),
        ], 'id = :id', ['id' => $jobId]);

        return ['ok' => false, 'message' => $message, 'job' => self::job($jobId)];
    }

    // =================================================================
    //  ZIP
    // =================================================================

    /**
     * Đóng gói toàn bộ thư mục vào ZIP.
     */
    public static function addDirToZip(\ZipArchive $zip, string $dir, string $root = ''): void
    {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $path = $file->getPathname();
            $rel  = ltrim(str_replace('\\', '/', substr($path, strlen($dir))), '/');
            $name = $root === '' ? $rel : $root . '/' . $rel;
            if ($file->isDir()) {
                $zip->addEmptyDir($name);
            } else {
                $zip->addFile($path, $name);
            }
        }
    }

    /**
     * Xoá thư mục đệ quy.
     */
    public static function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    /**
     * Lấy đường dẫn ZIP của 1 job.
     */
    public static function zipOf(int $jobId): ?string
    {
        $job = self::job($jobId);
        if (!$job || $job['status'] !== 'done') {
            return null;
        }
        $path = (string)($job['zip_path'] ?? '');
        return ($path !== '' && is_file($path)) ? $path : null;
    }

    /**
     * Xoá job + file ZIP.
     */
    public static function deleteJob(int $jobId, int $userId): void
    {
        $job = self::job($jobId);
        if (!$job || (int)$job['user_id'] !== $userId) {
            return;
        }
        $zip = (string)($job['zip_path'] ?? '');
        if ($zip !== '' && is_file($zip)) {
            @unlink($zip);
        }
        Database::delete('scraper_jobs', 'id = ? AND user_id = ?', [$jobId, $userId]);
    }
}
