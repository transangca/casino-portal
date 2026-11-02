<?php
namespace App\Services;

use App\Core\Database;

/**
 * Bộ công cụ tiện ích: hash, JSON, mật khẩu, đổi đơn vị, UUID, IP, WHOIS, SEO, speed test.
 *
 * Mỗi tool có 1 hàm xử lý riêng, giao diện dùng chung 1 trang /tools.
 */
class ToolsService
{
    /** Danh sách tool đăng ký trong DB */
    public static function registry(): array
    {
        return Database::fetchAll(
            'SELECT * FROM tools_registry WHERE is_active = 1 ORDER BY group_name, sort_order, id'
        );
    }

    public static function groups(): array
    {
        $out = [];
        foreach (self::registry() as $t) {
            $out[(string)$t['group_name']][] = $t;
        }
        return $out;
    }

    // ================= HASH =================

    public static function hash(string $input): array
    {
        return [
            'MD5'    => md5($input),
            'SHA1'   => sha1($input),
            'SHA256' => hash('sha256', $input),
            'SHA512' => hash('sha512', $input),
            'CRC32'  => sprintf('%u', crc32($input)),
            'Bcrypt' => password_hash($input, PASSWORD_BCRYPT),
        ];
    }

    // ================= JSON =================

    public static function formatJson(string $input): array
    {
        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'message' => 'JSON không hợp lệ: ' . json_last_error_msg()];
        }
        return [
            'ok'      => true,
            'pretty'  => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'minify'  => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'size'    => strlen($input),
        ];
    }

    // ================= MẬT KHẨU =================

    public static function generatePassword(int $length = 16, bool $symbols = true, bool $numbers = true): string
    {
        $length = max(8, min(64, $length));
        $sets = ['abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        if ($numbers) { $sets[] = '0123456789'; }
        if ($symbols) { $sets[] = '!@#$%^&*()-_=+[]{}<>?'; }

        $all = implode('', $sets);
        $pw = '';
        foreach ($sets as $set) {
            $pw .= $set[random_int(0, strlen($set) - 1)];
        }
        for ($i = strlen($pw); $i < $length; $i++) {
            $pw .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pw);
    }

    // ================= ĐỔI ĐƠN VỊ =================

    public const UNITS = [
        'length' => [
            'mm' => ['label' => 'Milimét', 'factor' => 0.001],
            'cm' => ['label' => 'Centimét', 'factor' => 0.01],
            'm'  => ['label' => 'Mét', 'factor' => 1],
            'km' => ['label' => 'Ki lô mét', 'factor' => 1000],
            'in' => ['label' => 'Inch', 'factor' => 0.0254],
            'ft' => ['label' => 'Feet', 'factor' => 0.3048],
            'yd' => ['label' => 'Yard', 'factor' => 0.9144],
        ],
        'weight' => [
            'kg'  => ['label' => 'Kilogram', 'factor' => 1],
            'g'   => ['label' => 'Gram', 'factor' => 0.001],
            'mg'  => ['label' => 'Milligram', 'factor' => 0.000001],
            't'   => ['label' => 'Tấn', 'factor' => 1000],
            'lb'  => ['label' => 'Pound', 'factor' => 0.45359237],
            'oz'  => ['label' => 'Ounce', 'factor' => 0.02834952],
        ],
        'area' => [
            'm2'  => ['label' => 'Mét vuông', 'factor' => 1],
            'cm2' => ['label' => 'Centimét vuông', 'factor' => 0.0001],
            'ha'  => ['label' => 'Hectare', 'factor' => 10000],
            'ft2' => ['label' => 'Feet vuông', 'factor' => 0.09290304],
        ],
        'speed' => [
            'kmh' => ['label' => 'km/h', 'factor' => 1],
            'ms'  => ['label' => 'm/s', 'factor' => 3.6],
            'mph' => ['label' => 'mph', 'factor' => 1.609344],
            'knot'=> ['label' => 'Hải lý', 'factor' => 1.852],
        ],
        'storage' => [
            'kb' => ['label' => 'KB', 'factor' => 1],
            'mb' => ['label' => 'MB', 'factor' => 1024],
            'gb' => ['label' => 'GB', 'factor' => 1048576],
            'tb' => ['label' => 'TB', 'factor' => 1073741824],
        ],
    ];

    public static function convert(string $group, string $from, string $to, float $value): ?float
    {
        $units = self::UNITS[$group] ?? null;
        if (!$units || !isset($units[$from], $units[$to])) {
            return null;
        }
        return round($value * $units[$from]['factor'] / $units[$to]['factor'], 8);
    }

    // ================= UUID =================

    public static function uuid(int $count = 5): array
    {
        $out = [];
        for ($i = 0; $i < max(1, min(50, $count)); $i++) {
            $d = random_bytes(16);
            $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
            $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
            $h = bin2hex($d);
            $out[] = sprintf('%s-%s-%s-%s-%s',
                substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4),
                substr($h, 16, 4), substr($h, 20, 12));
        }
        return $out;
    }

    // ================= BASE64 =================

    public static function base64(string $input, string $mode): array
    {
        if ($mode === 'encode') {
            return ['ok' => true, 'output' => base64_encode($input)];
        }
        $decoded = base64_decode($input, true);
        if ($decoded === false) {
            return ['ok' => false, 'message' => 'Chuỗi Base64 không hợp lệ.'];
        }
        return ['ok' => true, 'output' => $decoded];
    }

    // ================= IP =================

    public static function ipInfo(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'Địa chỉ IP không hợp lệ.'];
        }

        $info = [
            'ip'      => $ip,
            'version' => str_contains($ip, ':') ? 6 : 4,
            'private' => !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE),
            'reverse' => null,
        ];

        $rev = @gethostbyaddr($ip);
        if ($rev && $rev !== $ip) {
            $info['reverse'] = $rev;
        }

        // Tra cứu geo công khai (miễn phí, không bắt buộc)
        try {
            $res = HttpClient::getJson('http://ip-api.com/json/' . urlencode($ip) . '?lang=vi');
            if (is_array($res) && ($res['status'] ?? '') === 'success') {
                $info['geo'] = [
                    'country' => (string)($res['country'] ?? ''),
                    'region'  => (string)($res['regionName'] ?? ''),
                    'city'    => (string)($res['city'] ?? ''),
                    'isp'     => (string)($res['isp'] ?? ''),
                    'org'     => (string)($res['org'] ?? ''),
                    'as'      => (string)($res['as'] ?? ''),
                    'lat'     => (float)($res['lat'] ?? 0),
                    'lon'     => (float)($res['lon'] ?? 0),
                    'tz'      => (string)($res['timezone'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // bỏ qua lỗi mạng
        }

        return ['ok' => true, 'data' => $info];
    }

    // ================= WHOIS =================

    public static function whois(string $domain): array
    {
        $domain = trim(strtolower($domain));
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return ['ok' => false, 'message' => 'Tên miền không hợp lệ.'];
        }
        if (!function_exists('exec')) {
            return ['ok' => false, 'message' => 'Máy chủ không cho phép truy vấn WHOIS.'];
        }
        $safe = escapeshellarg($domain);
        $raw  = @shell_exec('whois ' . $safe . ' 2>/dev/null');
        if (!is_string($raw) || trim($raw) === '') {
            return ['ok' => false, 'message' => 'Không lấy được dữ liệu WHOIS (máy chủ chưa cài whois client).'];
        }
        $raw = mb_substr($raw, 0, 60000);
        $parse = static function (string $label) use ($raw): ?string {
            if (preg_match('/' . $label . '\s*:\s*(.+)/i', $raw, $m)) {
                return trim($m[1]);
            }
            return null;
        };
        return [
            'ok'   => true,
            'data' => [
                'domain'     => $domain,
                'registrar'  => $parse('Registrar'),
                'created'    => $parse('Creation Date|Created On|Registered On'),
                'expires'    => $parse('Registry Expiry Date|Expiry Date|Expires On'),
                'updated'    => $parse('Updated Date|Last Modified'),
                'nameserver' => $parse('Name Server'),
                'status'     => $parse('Domain Status'),
                'raw'        => $raw,
            ],
        ];
    }

    // ================= SEO =================

    public static function seoCheck(string $url): array
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'message' => 'URL phải bắt đầu bằng http:// hoặc https://.'];
        }

        $html = HttpClient::get((string)$url);
        if (!is_string($html) || $html === '') {
            return ['ok' => false, 'message' => 'Không tải được nội dung trang.'];
        }
        $html = mb_substr($html, 0, 400000);

        $get = static function (string $pattern) use ($html): ?string {
            if (preg_match($pattern, $html, $m)) {
                return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }
            return null;
        };

        $title    = $get('/<title[^>]*>(.*?)<\/title>/is');
        $desc     = $get('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is')
                 ?: $get('/<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']description["\']/is');
        $keywords = $get('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\'](.*?)["\']/is');
        $ogImage  = $get('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']/is');
        $robots   = $get('/<meta[^>]+name=["\']robots["\'][^>]+content=["\'](.*?)["\']/is');

        $h1 = [];
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            foreach ($m[1] as $t) {
                $h1[] = trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8'));
            }
        }
        $h2Count = preg_match_all('/<h2[\s>]/i', $html);
        $imgCount = preg_match_all('/<img[\s>]/i', $html);
        $imgNoAlt = preg_match_all('/<img(?![^>]*\balt=)[^>]*>/i', $html);
        $links    = preg_match_all('/<a[\s>]/i', $html);
        $canonical = $get('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\'](.*?)["\']/is');
        $schema   = preg_match_all('/application\/ld\+json/i', $html);

        $checks = [
            ['name' => 'Thẻ <title>', 'ok' => $title !== null && $title !== '', 'detail' => $title ?: 'Thiếu thẻ title'],
            ['name' => 'Meta description', 'ok' => $desc !== null && mb_strlen($desc) > 50, 'detail' => $desc ? mb_strlen($desc) . ' ký tự' : 'Thiếu hoặc quá ngắn (<50)'],
            ['name' => 'Meta keywords', 'ok' => $keywords !== null, 'detail' => $keywords ?: 'Thiếu'],
            ['name' => 'Canonical URL', 'ok' => $canonical !== null, 'detail' => $canonical ?: 'Thiếu'],
            ['name' => 'Open Graph image', 'ok' => $ogImage !== null, 'detail' => $ogImage ?: 'Thiếu'],
            ['name' => 'Robots meta', 'ok' => $robots !== null, 'detail' => $robots ?: 'Mặc định index,follow'],
            ['name' => 'Chỉ 1 thẻ H1', 'ok' => count($h1) === 1, 'detail' => count($h1) . ' thẻ H1'],
            ['name' => 'Có thẻ H2', 'ok' => $h2Count > 0, 'detail' => $h2Count . ' thẻ H2'],
            ['name' => 'Ảnh có alt', 'ok' => $imgNoAlt === 0, 'detail' => $imgCount . ' ảnh, ' . $imgNoAlt . ' thiếu alt'],
            ['name' => 'Schema.org JSON-LD', 'ok' => $schema > 0, 'detail' => $schema . ' khối'],
            ['name' => 'Số liên kết nội bộ', 'ok' => $links > 10, 'detail' => $links . ' liên kết'],
            ['name' => 'HTTPS', 'ok' => str_starts_with(strtolower($url), 'https://'), 'detail' => str_starts_with(strtolower($url), 'https://') ? 'Đã bật HTTPS' : 'Đang dùng HTTP'],
        ];

        $score = 0;
        foreach ($checks as $c) {
            if ($c['ok']) { $score++; }
        }
        $score = (int)round($score / count($checks) * 100);

        return [
            'ok'   => true,
            'data' => [
                'url'     => $url,
                'score'   => $score,
                'checks'  => $checks,
                'h1'      => $h1,
                'title'   => $title,
                'desc'    => $desc,
                'keywords'=> $keywords,
                'canonical'=> $canonical,
                'og_image'=> $ogImage,
            ],
        ];
    }

    // ================= SPEED TEST =================

    public static function speedTest(string $url): array
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'message' => 'URL không hợp lệ.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'ToolducSpeed/1.0',
        ]);
        $start = microtime(true);
        $raw   = curl_exec($ch);
        $total = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size  = (int)curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $dns   = (float)curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
        $conn  = (float)curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $tls   = (float)curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'message' => 'Không kết nối được tới website.'];
        }

        return [
            'ok'   => true,
            'data' => [
                'url'         => $url,
                'status'      => $code,
                'total_ms'    => (int)round($total * 1000),
                'dns_ms'      => (int)round($dns * 1000),
                'connect_ms'  => (int)round(max(0.0, $conn - $dns) * 1000),
                'ttfb_ms'     => (int)round($total * 1000),
                'size_bytes'  => $size,
                'size_kb'     => (int)round($size / 1024),
                'tls_ok'      => $tls === 0.0,
            ],
        ];
    }
}
