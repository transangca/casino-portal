<?php
namespace App\Services;

/**
 * Bộ thu thập website dùng chung.
 * ------------------------------------------------------------------
 * - Tải HTML
 * - Phân tích & tải VỀ ĐĨA toàn bộ tài nguyên: CSS, ảnh, JS, font, sprite
 * - Viết lại liên kết trong HTML trỏ tới file cục bộ → website mở offline
 * - Dùng chung cho: Đục UI/UX, Copy 1-1 trang web
 */
class SiteCrawlerService
{
    /** Giới hạn an toàn */
    public const MAX_HTML_BYTES   = 6_000_000;
    public const MAX_CSS_BYTES    = 2_000_000;
    public const MAX_ASSET_BYTES  = 12_000_000;
    public const MAX_CSS_FILES    = 60;
    public const MAX_IMAGES       = 300;
    public const MAX_JS_FILES     = 60;
    public const MAX_FONTS        = 40;
    public const MAX_PAGES        = 60;

    /** Đuôi file asset được tải */
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'ico', 'bmp'];

    // =================================================================
    //  URL helpers
    // =================================================================

    public static function normalizeUrl(string $raw): string
    {
        $url = trim($raw);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return preg_replace('#^\s+|\s+$#', '', $url) ?? $url;
    }

    /**
     * Kiểm tra URL hợp lệ & an toàn để quét (chặn SSRF).
     *
     * @return array{ok:bool,message:string,host:string}
     */
    public static function validateUrl(string $raw): array
    {
        $url = self::normalizeUrl($raw);
        if ($url === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập đường dẫn website.', 'host' => ''];
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return ['ok' => false, 'message' => 'Đường dẫn không hợp lệ.', 'host' => ''];
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'message' => 'Chỉ hỗ trợ đường dẫn http/https.', 'host' => ''];
        }

        $host = strtolower((string)$parts['host']);
        if (self::isInternalHost($host)) {
            return ['ok' => false, 'message' => 'Không thể quét địa chỉ nội bộ.', 'host' => $host];
        }

        return ['ok' => true, 'message' => '', 'host' => $host];
    }

    public static function isInternalHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return false;
    }

    /**
     * Trỏ URL tương đối → URL tuyệt đối.
     */
    public static function absolute(string $ref, string $base): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }
        if (str_starts_with($ref, 'data:') || str_starts_with($ref, '#')
            || str_starts_with($ref, 'javascript:') || str_starts_with($ref, 'mailto:')
            || str_starts_with($ref, 'tel:')) {
            return '';
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        if (str_starts_with($ref, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $ref;
        }
        if (str_starts_with($ref, '/')) {
            $p = parse_url($base);
            $scheme = (string)($p['scheme'] ?? 'https');
            $host   = (string)($p['host'] ?? '');
            $port   = isset($p['port']) ? ':' . $p['port'] : '';
            return $scheme . '://' . $host . $port . $ref;
        }
        return rtrim($base, '/') . '/' . ltrim($ref, '/');
    }

    /**
     * Base URL (scheme + host + thư mục) để resolve đường dẫn tương đối.
     */
    public static function baseUrl(string $url): string
    {
        $p = parse_url($url);
        if ($p === false || empty($p['host'])) {
            return $url;
        }
        $scheme = (string)($p['scheme'] ?? 'https');
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $path   = (string)($p['path'] ?? '/');
        $dir    = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $scheme . '://' . $p['host'] . $port . ($dir !== '' ? $dir . '/' : '/');
    }

    // =================================================================
    //  Tải trang
    // =================================================================

    /**
     * Tải 1 trang HTML.
     *
     * @return array{ok:bool,message:string,html:string,final_url:string,status:int}
     */
    public static function fetchPage(string $url, int $timeout = 25): array
    {
        $res = HttpClient::requestFull('GET', $url, null, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ], [
            'timeout'         => $timeout,
            'connect_timeout' => 10,
            'retries'         => 1,
        ]);

        if (!$res['ok'] || $res['body'] === null || trim($res['body']) === '') {
            return [
                'ok'        => false,
                'message'   => 'Không đọc được nội dung trang (HTTP ' . $res['status'] . ').',
                'html'      => '',
                'final_url' => $url,
                'status'    => $res['status'],
            ];
        }

        $html = $res['body'];
        if (strlen($html) > self::MAX_HTML_BYTES) {
            $html = substr($html, 0, self::MAX_HTML_BYTES);
        }

        // Charset
        if (preg_match('#charset=["\']?([a-z0-9\-_]+)#i', substr($html, 0, 3000), $m)) {
            $cs = strtoupper($m[1]);
            if ($cs !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($html, 'UTF-8', $cs);
                if (is_string($converted) && $converted !== '') {
                    $html = $converted;
                }
            }
        }

        $final = $url;
        if (preg_match('#^location:\s*(.+)$#im', (string)($res['headers']['location'] ?? ''), $m)) {
            $final = self::absolute(trim($m[1]), $url);
        }

        return [
            'ok'        => true,
            'message'   => '',
            'html'      => $html,
            'final_url' => $final,
            'status'    => $res['status'],
        ];
    }

    // =================================================================
    //  Phân tích HTML
    // =================================================================

    /**
     * Trích toàn bộ tài nguyên + liên kết trong 1 trang HTML.
     */
    public static function parseHtml(string $html, string $base): array
    {
        $out = [
            'title' => '', 'description' => '', 'favicon' => '', 'theme_color' => '',
            'stylesheets' => [], 'inline_css' => [], 'css' => [],
            'images' => [], 'scripts' => [], 'fonts' => [], 'links' => [],
            'inline_scripts' => [],
        ];

        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $out['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)#i', $html, $m)) {
            $out['description'] = trim($m[1]);
        }
        if (preg_match('#<meta[^>]+name=["\']theme-color["\'][^>]+content=["\']([^"\']*)#i', $html, $m)) {
            $out['theme_color'] = trim($m[1]);
        }
        if (preg_match('#<link[^>]+rel=["\'][^"\']*icon[^"\']*["\'][^>]*>#i', $html, $m)) {
            if (preg_match('#href=["\']([^"\']+)#i', $m[0], $h)) {
                $out['favicon'] = self::absolute(html_entity_decode($h[1]), $base);
            }
        }

        // --- Stylesheets ---
        if (preg_match_all('#<link[^>]+>#i', $html, $links)) {
            foreach ($links[0] as $tag) {
                if (preg_match('#rel=["\']([^"\']+)#i', $tag, $r)
                    && stripos($r[1], 'stylesheet') !== false
                    && preg_match('#href=["\']([^"\']+)#i', $tag, $h)) {
                    $u = self::absolute(html_entity_decode($h[1]), $base);
                    if ($u !== '') {
                        $out['stylesheets'][] = $u;
                    }
                }
            }
        }

        // --- Inline <style> ---
        if (preg_match_all('#<style[^>]*>(.*?)</style>#is', $html, $styles)) {
            foreach ($styles[1] as $css) {
                $css = trim($css);
                if ($css !== '') {
                    $out['inline_css'][] = $css;
                }
            }
        }

        // --- Scripts ---
        if (preg_match_all('#<script[^>]+src=["\']([^"\']+)#i', $html, $scripts)) {
            foreach ($scripts[1] as $src) {
                $u = self::absolute(html_entity_decode($src), $base);
                if ($u !== '') {
                    $out['scripts'][] = $u;
                }
            }
        }
        if (preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#is', $html, $inline)) {
            foreach ($inline[1] as $js) {
                $js = trim($js);
                if ($js !== '') {
                    $out['inline_scripts'][] = $js;
                }
            }
        }

        // --- Preload fonts + font link ---
        if (preg_match_all('#<link[^>]+>#i', $html, $preloads)) {
            foreach ($preloads[0] as $tag) {
                $isFont = (preg_match('#as=["\']font["\']#i', $tag)
                    && preg_match('#href=["\']([^"\']+)#i', $tag, $h));
                if ($isFont) {
                    $u = self::absolute(html_entity_decode($h[1]), $base);
                    if ($u !== '') {
                        $out['fonts'][] = $u;
                    }
                }
            }
        }

        // --- Images ---
        if (preg_match_all('#<img[^>]+>#i', $html, $imgs)) {
            foreach ($imgs[0] as $tag) {
                if (preg_match('#\bsrc=["\']([^"\']+)#i', $tag, $s)) {
                    $u = self::absolute(html_entity_decode($s[1]), $base);
                    if ($u !== '') {
                        $out['images'][] = $u;
                    }
                }
                if (preg_match_all('#\bsrcset=["\']([^"\']+)#i', $tag, $sets)) {
                    foreach ($sets[1] as $set) {
                        foreach (explode(',', $set) as $cand) {
                            $one = trim(explode(' ', trim($cand))[0]);
                            $u = self::absolute(html_entity_decode($one), $base);
                            if ($u !== '') {
                                $out['images'][] = $u;
                            }
                        }
                    }
                }
            }
        }
        // Lazy-load attributes
        if (preg_match_all('#<(?:img|source|div|span)[^>]+(?:data-src|data-lazy|data-original|data-bg)=["\']([^"\']+)#i', $html, $lazy)) {
            foreach ($lazy[1] as $u0) {
                $u = self::absolute(html_entity_decode(trim($u0)), $base);
                if ($u !== '') {
                    $out['images'][] = $u;
                }
            }
        }

        // --- background-image trong inline style / <style> ---
        $allCss = implode("\n", $out['inline_css']);
        if (preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $allCss, $bgs)) {
            foreach ($bgs[1] as $u0) {
                $u = self::absolute(html_entity_decode(trim($u0)), $base);
                if ($u !== '' && !self::isFont($u)) {
                    $out['images'][] = $u;
                }
            }
        }

        // --- Liên kết nội bộ (dùng cho copy 1-1) ---
        if (preg_match_all('#<a[^>]+href=["\']([^"\']+)#i', $html, $a)) {
            foreach ($a[1] as $href) {
                $u = self::absolute(html_entity_decode($href), $base);
                if ($u !== '' && preg_match('#^https?://#i', $u)) {
                    $out['links'][] = $u;
                }
            }
        }

        $out['images']      = array_values(array_unique($out['images']));
        $out['stylesheets'] = array_values(array_unique($out['stylesheets']));
        $out['scripts']     = array_values(array_unique($out['scripts']));
        $out['fonts']       = array_values(array_unique($out['fonts']));
        $out['links']       = array_values(array_unique($out['links']));

        return $out;
    }

    public static function isFont(string $url): bool
    {
        $p = strtolower((string)parse_url($url, PHP_URL_PATH));
        return (bool)preg_match('/\.(woff2?|ttf|otf|eot)(\?|$)/', $p);
    }

    public static function isImage(string $url): bool
    {
        $p = strtolower((string)parse_url($url, PHP_URL_PATH));
        return in_array(pathinfo($p, PATHINFO_EXTENSION), self::IMAGE_EXT, true);
    }

    // =================================================================
    //  Tải tài nguyên về đĩa
    // =================================================================

    /**
     * Tải 1 asset về đĩa.
     *
     * @return array{ok:bool,file:string,size:int,mime:string,error:string}
     */
    public static function downloadAsset(string $url, string $targetDir, string $relativePath): array
    {
        $fail = ['ok' => false, 'file' => '', 'size' => 0, 'mime' => '', 'error' => 'Không tải được.'];

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '' || self::isInternalHost($host)) {
            return ['ok' => false, 'file' => '', 'size' => 0, 'mime' => '', 'error' => 'Host không hợp lệ.'];
        }

        $res = HttpClient::requestFull('GET', $url, null, ['Accept: */*'], [
            'timeout'         => 18,
            'connect_timeout' => 8,
            'retries'         => 0,
        ]);

        if (!$res['ok'] || $res['body'] === null || $res['body'] === '') {
            return $fail;
        }

        $body = $res['body'];
        if (strlen($body) > self::MAX_ASSET_BYTES) {
            return ['ok' => false, 'file' => '', 'size' => 0, 'mime' => '', 'error' => 'File quá lớn.'];
        }

        $mime = (string)($res['headers']['content-type'] ?? '');
        $mime = trim(explode(';', $mime)[0]);

        $fullPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir      = dirname($fullPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'file' => '', 'size' => 0, 'mime' => '', 'error' => 'Không tạo được thư mục.'];
        }

        if (@file_put_contents($fullPath, $body) === false) {
            return $fail;
        }

        return [
            'ok'   => true,
            'file' => $relativePath,
            'size' => strlen($body),
            'mime' => $mime,
            'error' => '',
        ];
    }

    /**
     * Tải nội dung CSS (dùng để tìm asset bên trong).
     */
    public static function fetchCss(string $url): ?string
    {
        $css = HttpClient::get($url, ['Accept: text/css,*/*;q=0.1'], ['timeout' => 15, 'retries' => 0]);
        if ($css === null) {
            return null;
        }
        if (strlen($css) > self::MAX_CSS_BYTES) {
            $css = substr($css, 0, self::MAX_CSS_BYTES);
        }
        return $css;
    }

    /**
     * Tên file an toàn từ URL + đuôi.
     */
    public static function safeName(string $url, string $ext = ''): string
    {
        $p = (string)parse_url($url, PHP_URL_PATH);
        $base = basename($p);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'file';
        if ($ext !== '') {
            $base = preg_replace('/\.[A-Za-z0-9]+$/', '', $base) . '.' . $ext;
        }
        return substr($base, -120);
    }

    /**
     * Đuôi file ảnh.
     */
    public static function imageExt(string $url, string $mime = ''): string
    {
        if (preg_match('#image/(png|jpe?g|gif|webp|svg\+xml|avif|ico|bmp)#i', $mime, $m)) {
            return strtolower(str_replace('+xml', '', $m[1]));
        }
        $p = strtolower((string)parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(png|jpe?g|gif|webp|svg|avif|ico|bmp)$/', $p, $m)) {
            return strtolower($m[1]);
        }
        return 'png';
    }

    // =================================================================
    //  Viết lại HTML
    // =================================================================

    /**
     * Viết lại đường dẫn trong HTML trỏ về file cục bộ.
     *
     * @param array<string,string> $map url => đường dẫn tương đối cục bộ
     */
    public static function rewriteHtml(string $html, array $map, string $base = ''): string
    {
        if (!$map) {
            return $html;
        }

        // Sắp xếp theo độ dài giảm dần để thay thế URL dài trước
        $urls = array_keys($map);
        usort($urls, static fn($a, $b) => strlen($b) <=> strlen($a));

        // url(...) trong style
        $html = preg_replace_callback(
            '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i',
            static function (array $m) use ($map) {
                $u = trim($m[1]);
                if ($u !== '' && isset($map[$u])) {
                    return 'url("' . $map[$u] . '")';
                }
                return $m[0];
            },
            $html
        );

        // src / href / data-src
        $html = preg_replace_callback(
            '#(\b(?:src|href|data-src|data-lazy|data-original|data-bg|poster)\s*=\s*["\'])([^"\']+)(["\'])#i',
            static function (array $m) use ($map) {
                $u = trim($m[2]);
                if ($u !== '' && isset($map[$u])) {
                    return $m[1] . $map[$u] . $m[3];
                }
                return $m[0];
            },
            $html
        );

        // srcset
        $html = preg_replace_callback(
            '#(\bsrcset\s*=\s*["\'])([^"\']+)(["\'])#i',
            static function (array $m) use ($map) {
                $parts = array_map(static function (string $c) use ($map): string {
                    $bits = preg_split('/\s+/', trim($c));
                    $u    = trim((string)($bits[0] ?? ''));
                    if ($u !== '' && isset($map[$u])) {
                        $bits[0] = $map[$u];
                    }
                    return implode(' ', $bits);
                }, explode(',', $m[2]));
                return $m[1] . implode(', ', $parts) . $m[3];
            },
            $html
        );

        return $html;
    }

    /**
     * Viết lại CSS: thay url(...) bằng file cục bộ.
     */
    public static function rewriteCss(string $css, array $map): string
    {
        if (!$map) {
            return $css;
        }
        return preg_replace_callback(
            '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i',
            static function (array $m) use ($map) {
                $u = trim($m[1]);
                if ($u !== '' && isset($map[$u])) {
                    return 'url("' . $map[$u] . '")';
                }
                return $m[0];
            },
            $css
        );
    }
}
