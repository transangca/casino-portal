<?php
/**
 * Helper functions toàn cục.
 */

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;

// Đặt timezone một lần duy nhất ở đây để mọi entry point
// (web, CLI, cron, seed) đều dùng chung một múi giờ với MySQL.
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// ---------- Nạp .env (không cần thư viện ngoài) ----------
if (!getenv('APP_ENV')) {
    $envFile = BASE_PATH . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if ($k === '' || getenv($k) !== false) {
                continue;
            }
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

date_default_timezone_set((function (): string {
    static $tz = null;
    if ($tz === null) {
        $cfg = require BASE_PATH . '/config/app.php';
        $tz  = (string)($cfg['app']['timezone'] ?? 'Asia/Ho_Chi_Minh');
    }
    return $tz;
})());

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            $cache = require BASE_PATH . '/config/app.php';
        }
        if ($key === null) {
            return $cache;
        }
        $value = $cache;
        foreach (explode('.', $key) as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }
}

if (!function_exists('e')) {
    /** Escape HTML */
    function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        $base = rtrim(config('app.url'), '/');
        return $path === '/' ? $base . '/' : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }
}

if (!function_exists('abort')) {
    function abort(int $code = 404, string $message = ''): never
    {
        http_response_code($code);
        $view = BASE_PATH . '/app/Views/error.php';
        if (is_file($view)) {
            $errorCode = $code;
            $errorMessage = $message;
            extract(['errorCode' => $errorCode, 'errorMessage' => $errorMessage], EXTR_SKIP);
            include $view;
        } else {
            echo $message ?: 'Error ' . $code;
        }
        exit;
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        // Layout mặc định theo prefix thư mục view
        if ($layout === 'layouts/main' && str_starts_with($template, 'admin/')) {
            $layout = 'layouts/admin';
        }
        if ($layout === 'layouts/main' && str_starts_with($template, 'games/play')) {
            $layout = null;
        }

        $data['__flash'] = flash_messages();
        extract($data, EXTR_SKIP);

        ob_start();
        include BASE_PATH . '/app/Views/' . $template . '.php';
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }
        include BASE_PATH . '/app/Views/' . $layout . '.php';
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_error')) {
    function json_error(string $message, int $code = 400, array $extra = []): never
    {
        json_response(array_merge(['success' => false, 'message' => $message], $extra), $code);
    }
}

if (!function_exists('json_success')) {
    /**
     * Trả JSON thành công.
     *
     * @param mixed $data Dữ liệu trả về (đặt trong key "data")
     */
    function json_success(mixed $data = null, string $message = '', array $extra = []): never
    {
        $payload = ['success' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        json_response(array_merge($payload, $extra), 200);
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_messages')) {
    function flash_messages(): array
    {
        $m = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $m;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('keep_old')) {
    function keep_old(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
}

if (!function_exists('clear_old')) {
    function clear_old(): void
    {
        unset($_SESSION['_old']);
    }
}

if (!function_exists('logger')) {
    function logger(string $message, string $level = 'info'): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            PHP_EOL
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}

if (!function_exists('audit')) {
    function audit(string $action, ?string $entity = null, ?string $entityId = null, array $detail = []): void
    {
        try {
            Database::insert('audit_logs', [
                'user_id'   => Auth::id(),
                'action'    => $action,
                'entity'    => $entity,
                'entity_id' => $entityId,
                'detail'    => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            logger('audit failed: ' . $e->getMessage(), 'error');
        }
    }
}

if (!function_exists('setting')) {
    /**
     * Đọc 1 setting từ bảng `settings` (có cache trong request).
     */
    function setting(string $name, mixed $default = null): mixed
    {
        static $cache = [];

        // Giá trị vừa được ghi đè trong chính request này → ưu tiên giá trị mới
        if (isset($GLOBALS['__setting_fresh'][$name])) {
            return $GLOBALS['__setting_fresh'][$name];
        }

        if (!array_key_exists($name, $cache)) {
            $v = Database::fetchColumn('SELECT value FROM settings WHERE name = ?', [$name]);
            $cache[$name] = $v === null ? $default : $v;
        }
        return $cache[$name] ?? $default;
    }
}

if (!function_exists('setting_set')) {
    /**
     * Ghi 1 setting và cập nhật cache trong request hiện tại.
     */
    function setting_set(string $name, string $value, string $group = 'general', string $inputType = 'text'): void
    {
        $exists = Database::fetchColumn('SELECT name FROM settings WHERE name = ?', [$name]);
        if ($exists) {
            Database::update('settings', ['value' => $value], 'name = :n', ['n' => $name]);
        } else {
            Database::insert('settings', [
                'name'       => $name,
                'value'      => $value,
                'group_name' => $group,
                'input_type' => $inputType,
            ]);
        }
        $GLOBALS['__setting_fresh'][$name] = $value;
    }
}

if (!function_exists('money')) {
    function money(float $amount): string
    {
        $sym = setting('currency_symbol', 'đ');
        return number_format($amount, 0, ',', '.') . ' ' . $sym;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return CSRF::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return CSRF::token();
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text, 'UTF-8'));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        return trim($text, '-') ?: 'item';
    }
}

if (!function_exists('page_url')) {
    /** Giữ nguyên query string khi đổi tham số */
    function page_url(array $overrides = []): string
    {
        $query = array_merge($_GET, $overrides);
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $qs   = http_build_query($query);
        return $path . ($qs ? '?' . $qs : '');
    }
}

// ---------- Đa ngôn ngữ ----------
if (!function_exists('t')) {
    /** Dịch key i18n: t('nav.wallet') */
    function t(string $key, array $replace = []): string
    {
        return \App\Services\I18n::t($key, $replace);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return \App\Services\I18n::locale();
    }
}

if (!function_exists('available_locales')) {
    function available_locales(): array
    {
        return \App\Services\I18n::available();
    }
}

// ---------- Theme ----------
if (!function_exists('current_theme')) {
    /**
     * Theme hiện tại: dark | light | system
     * Ưu tiên lựa chọn của user → cookie → cài đặt admin.
     */
    function current_theme(): string
    {
        static $theme = null;
        if ($theme !== null) {
            return $theme;
        }

        $user = \App\Core\Auth::user();
        if ($user && !empty($user['theme'])) {
            return $theme = (string)$user['theme'];
        }
        if (!empty($_COOKIE['theme'])) {
            return $theme = (string)$_COOKIE['theme'];
        }
        return $theme = (string)setting('theme_style', 'system');
    }
}

// ---------- Avatar / verified ----------
if (!function_exists('user_avatar')) {
    function user_avatar(?array $user, string $class = 'avatar'): string
    {
        $src = (string)($user['avatar'] ?? '');
        if ($src === '') {
            $name = (string)($user['username'] ?? '?');
            $initials = mb_strtoupper(mb_substr($name, 0, 1));
            return '<span class="' . e($class) . ' avatar-fallback">' . e($initials) . '</span>';
        }
        return '<img src="' . e($src) . '" alt="' . e((string)($user['username'] ?? '')) . '" class="' . e($class) . '">';
    }
}

if (!function_exists('verified_tick')) {
    /** Tick xanh cho tài khoản đã xác minh (admin cấp) */
    function verified_tick(?array $user, bool $large = false): string
    {
        if (!$user || empty($user['is_verified'])) {
            return '';
        }
        $cls = $large ? 'verified-tick lg' : 'verified-tick';
        return '<span class="' . $cls . '" title="Tài khoản đã xác minh"><i class="fas fa-check"></i></span>';
    }
}

// ---------- Provider helpers ----------
if (!function_exists('provider_categories')) {
    /** Danh mục kèm số lượng sảnh/game */
    function provider_categories(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $rows = \App\Core\Database::fetchAll(
            'SELECT c.code, c.name, c.name_en, c.icon, c.banner,
                    (SELECT COUNT(*) FROM providers p WHERE p.category = c.code AND p.status = "active") AS provider_count,
                    (SELECT COUNT(*) FROM games g JOIN providers p2 ON p2.id = g.provider_id
                       WHERE p2.category = c.code AND g.status = "active") AS game_count
             FROM game_categories c WHERE c.is_active = 1 ORDER BY c.sort_order'
        );
        return $cache = $rows;
    }
}

if (!function_exists('game_placeholder')) {
    function game_placeholder(): string
    {
        return asset('img/placeholder.svg');
    }
}

// ---------- Provider helpers ----------
if (!function_exists('media_url')) {
    /**
     * Chuẩn hoá URL media (ảnh game, ảnh sảnh, banner) về đường dẫn dùng được.
     * - http(s)://…  → giữ nguyên
     * - //…          → https:…
     * - /assets/…    → giữ nguyên (đường dẫn tuyệt đối trong public/)
     * - assets/…     → /assets/… (asset() tự thêm 'assets/')
     * - img/…        → /assets/img/…
     */
    function media_url(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        if (str_starts_with($src, '//')) {
            return 'https:' . $src;
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        if (str_starts_with($src, '/assets/')) {
            return url(ltrim($src, '/'));
        }
        if (str_starts_with($src, 'assets/')) {
            $src = substr($src, 7);
        } elseif (!str_starts_with($src, 'img/')) {
            $src = 'img/' . ltrim($src, '/');
        }
        return asset($src);
    }
}

if (!function_exists('provider_logo_url')) {
    /**
     * URL logo của sảnh (chuẩn hoá về đường dẫn dùng được trong thuộc tính src).
     * Logo trong DB có thể là URL tuyệt đối hoặc đường dẫn nội bộ.
     * Nếu sảnh chưa có logo → tra trong brand registry (logo nhà phát hành chính thức).
     */
    function provider_logo_url(?array $provider): string
    {
        $src = trim((string)($provider['logo'] ?? ''));
        if ($src !== '') {
            if (str_starts_with($src, '//')) {
                return 'https:' . $src;
            }
            if (preg_match('#^https?://#i', $src)) {
                return $src;
            }
            return media_url($src);
        }

        // Fallback: logo thương hiệu của nhà phát hành
        $name = (string)($provider['name'] ?? '');
        $code = (string)($provider['code'] ?? '');
        foreach ([$code, $name] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $url = brand_logo_url($candidate, 'game');
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }
}

if (!function_exists('provider_logo')) {
    /**
     * Thẻ <img> logo của sảnh.
     */
    function provider_logo(?array $provider, string $class = 'provider-logo'): string
    {
        $src = provider_logo_url($provider);
        if ($src === '') {
            return '';
        }
        return '<img src="' . e($src) . '" alt="' . e((string)($provider['name'] ?? '')) . '" class="' . e($class) . '" loading="lazy" decoding="async" onerror="this.remove()">';
    }
}

if (!function_exists('provider_map')) {
    /**
     * Bản đồ id => provider (dùng để gắn logo thương hiệu cho game card).
     * Cache tĩnh trong 1 request để không query lặp.
     */
    function provider_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $rows = \App\Core\Database::fetchAll('SELECT id, code, name, logo, category FROM providers');
        $map  = [];
        foreach ($rows as $r) {
            $map[(int)$r['id']] = $r;
        }
        return $map;
    }
}

if (!function_exists('provider_promo')) {
    /** Ảnh nền hero của sảnh (absolute URL hoặc rỗng) */
    function provider_promo(?array $provider): string
    {
        return media_url((string)($provider['promo_image'] ?? ''));
    }
}

/* =====================================================================
   BRAND LOGOS — thương hiệu ứng dụng / AI / ngân hàng / sảnh game
   ===================================================================== */
if (!function_exists('brand_manifest')) {
    /**
     * Bảng ánh xạ slug ứng dụng → file logo trong /assets/img/brands.
     * Mỗi mục: slug => [nhóm, tên hiển thị, tên file (bỏ .svg/.png)]
     */
    function brand_manifest(): array
    {
        static $m = null;
        if ($m !== null) {
            return $m;
        }

        $raw = [
            // ---- Nền tảng AI ----
            'cursor'       => ['ai', 'Cursor', 'cursor'],
            'chatgpt'      => ['ai', 'ChatGPT', 'chatgpt'],
            'openai'       => ['ai', 'OpenAI', 'openai'],
            'claude'       => ['ai', 'Claude', 'claude'],
            'anthropic'    => ['ai', 'Anthropic', 'anthropic'],
            'gemini'       => ['ai', 'Gemini', 'gemini'],
            'midjourney'   => ['ai', 'Midjourney', 'midjourney'],
            'copilot'      => ['ai', 'GitHub Copilot', 'github'],
            'perplexity'   => ['ai', 'Perplexity', 'perplexity'],
            'canva'        => ['ai', 'Canva', 'canva'],
            'jetbrains'    => ['ai', 'JetBrains', 'jetbrains'],
            'poe'          => ['ai', 'Poe', 'poe'],
            'huggingface'  => ['ai', 'Hugging Face', 'huggingface'],
            'notion'       => ['ai', 'Notion AI', 'notion'],
            'xai'          => ['ai', 'Grok / xAI', 'xai'],
            'grok'         => ['ai', 'Grok', 'xai'],
            'windsurf'     => ['ai', 'Windsurf', 'cursor'],
            'aider'        => ['ai', 'Aider', 'cursor'],
            'sora'         => ['ai', 'Sora', 'openai'],
            'stability'    => ['ai', 'Stable Diffusion', 'stability'],
            'elevenlabs'   => ['ai', 'ElevenLabs', 'elevenlabs'],
            'runway'       => ['ai', 'Runway', 'runway'],
            'synthesia'    => ['ai', 'Synthesia', 'synthesia'],
            'deepseek'     => ['ai', 'DeepSeek', 'deepseek'],
            'ollama'       => ['ai', 'Ollama', 'ollama'],
            'langchain'    => ['ai', 'LangChain', 'langchain'],
            'replit'       => ['ai', 'Replit', 'replit'],
            'google'       => ['ai', 'Google', 'google'],
            'microsoft'    => ['ai', 'Microsoft', 'microsoft'],
            'discord'      => ['ai,sim', 'Discord', 'discord'],
            'mistral'      => ['ai', 'Mistral', 'mistral'],
            'replicate'    => ['ai', 'Replicate', 'replicate'],
            'suno'         => ['ai', 'Suno', 'suno'],

            // ---- Ứng dụng thuê SIM / mạng xã hội ----
            'telegram'     => ['sim', 'Telegram', 'telegram'],
            'zalo'         => ['sim', 'Zalo', 'zalo'],
            'whatsapp'     => ['sim', 'WhatsApp', 'whatsapp'],
            'facebook'     => ['sim', 'Facebook', 'facebook'],
            'instagram'    => ['sim', 'Instagram', 'instagram'],
            'tiktok'       => ['sim', 'TikTok', 'tiktok'],
            'shopee'       => ['sim', 'Shopee', 'shopee'],
            'lazada'       => ['sim', 'Lazada', 'lazada'],
            'twitter'      => ['sim', 'X (Twitter)', 'twitter'],
            'linkedin'     => ['sim', 'LinkedIn', 'linkedin'],
            'messenger'    => ['sim', 'Messenger', 'messenger'],
            'viber'        => ['sim', 'Viber', 'viber'],
            'signal'       => ['sim', 'Signal', 'signal'],
            'youtube'      => ['sim', 'YouTube', 'youtube'],
            'spotify'      => ['sim', 'Spotify', 'spotify'],
            'threads'      => ['sim', 'Threads', 'threads'],
            'pinterest'    => ['sim', 'Pinterest', 'pinterest'],
            'snapchat'     => ['sim', 'Snapchat', 'snapchat'],
            'line'         => ['sim', 'LINE', 'line'],
            'twilio'       => ['sim', 'Twilio', 'twilio'],
            'momo'         => ['bank,sim', 'MoMo', 'momo'],
            'vnpay'        => ['sim', 'VNPay', 'vnpay'],
            'epay'         => ['sim', 'ePay', 'epay'],
            'x'            => ['sim', 'X (Twitter)', 'x'],
            'tiktokads'    => ['sim', 'TikTok Ads', 'tiktokads'],
            'discord'      => ['ai,sim', 'Discord', 'discord'],

            // ---- Nhà mạng & thẻ cào ----
            'viettel'      => ['sim', 'Viettel', 'viettel'],
            'mobifone'     => ['sim', 'Mobifone', 'mobifone'],
            'vinaphone'    => ['sim', 'Vinaphone', 'vinaphone'],
            'gamecard'     => ['sim', 'Thẻ Game', 'gamecard'],
            'giftcard'     => ['sim', 'Giftcode', 'giftcard'],

            // ---- Ngân hàng Việt Nam (file theo mã VietQR) ----
            'vietcombank'  => ['bank', 'Vietcombank', 'vcb'],
            'techcombank'  => ['bank', 'Techcombank', 'tcb'],
            'mbbank'       => ['bank', 'MB Bank', 'mb'],
            'acb'          => ['bank', 'ACB', 'acb'],
            'vpbank'       => ['bank', 'VPBank', 'vpb'],
            'tpbank'       => ['bank', 'TPBank', 'tpb'],
            'bidv'         => ['bank', 'BIDV', 'bidv'],
            'agribank'     => ['bank', 'Agribank', 'agribank'],
            'vib'          => ['bank', 'VIB', 'vib'],
            'seabank'      => ['bank', 'SeABank', 'seab'],
            'scb'          => ['bank', 'SCB', 'scb'],
            'pvbank'       => ['bank', 'PVBank', 'pvdb'],
            'abbank'       => ['bank', 'ABBank', 'abbank'],
            'lpbank'       => ['bank', 'LPBank', 'lpbank'],
            'vietbank'     => ['bank', 'Vietbank', 'vietbank'],
            'vccorp'       => ['bank', 'VCCorp', 'vccorp'],
            'shb'          => ['bank', 'SHB', 'shb'],
            'msb'          => ['bank', 'MSB', 'msb'],
            'ocb'          => ['bank', 'OCB', 'ocb'],
            'bvbank'       => ['bank', 'BVBank', 'bvb'],
            'mbv'          => ['bank', 'MBV (Cống Quỹ Đầu Tư)', 'mbv'],
            'vrb'          => ['bank', 'VRBank', 'vrb'],
            'coopbank'     => ['bank', 'Nông hạn và Xây dựng Việt Nam (NHB)', 'coopbank'],
            'stb'          => ['bank', 'STBank', 'stb'],
            'timo'         => ['bank', 'Timo', 'timo'],
            'cake'         => ['bank', 'Cake', 'cake'],
            'pbank'        => ['bank', 'PBank', 'pbvn'],
            'ubank'        => ['bank', 'Ubank', 'ubank'],
            'momo'         => ['bank,sim', 'MoMo', 'momo'],
            'zalopay'      => ['bank', 'ZaloPay', 'zalopay'],
            'vnptmoney'    => ['bank', 'VNPT Money', 'vnptmoney'],
            'vtlmoney'     => ['bank', 'Viettel Money', 'vtlmoney'],
            'bac'          => ['bank', 'BAC', 'bab'],
            'vab'          => ['bank', 'VAB', 'vab'],
            'vbsp'         => ['bank', 'VBSP', 'vbsp'],
            'citibank'     => ['bank', 'Citibank', 'citibank'],
            'hsbc'         => ['bank', 'HSBC', 'hsbc'],
            'cimb'         => ['bank', 'CIMB', 'cimb'],
            'icbc'         => ['bank', 'ICBC', 'icbc'],
            'dbs'          => ['bank', 'DBS', 'dbs'],
            'uob'          => ['bank', 'UOB', 'uob'],
            'kbank'        => ['bank', 'KBank', 'kbank'],
            'baib'         => ['bank', 'BIB', 'bab'],

            // ---- Nhà phát hành game ----
            'pgsoft'        => ['game', 'PG Soft', 'pgsoft'],
            'pgs'           => ['game', 'PGS', 'pgs'],
            'pragmaticplay' => ['game', 'Pragmatic Play', 'pragmaticplay'],
            'jili'          => ['game', 'JILI', 'jili'],
            'spadegaming'   => ['game', 'Spade Gaming', 'spadegaming'],
            'fastspin'      => ['game', 'FastSpin', 'infinitygaming'],
            'cq9'           => ['game', 'CQ9', 'jdb'],
            'playtech'      => ['game', 'Playtech', 'playtech'],
            'netent'        => ['game', 'NetEnt', 'netent'],
            'microgaming'   => ['game', 'Microgaming', 'microgaming'],
            'pushgaming'    => ['game', 'Push Gaming', 'pushgaming'],
            'nolimitcity'   => ['game', 'Nolimit City', 'nolimitcity'],
            'hacksawgaming' => ['game', 'Hacksaw Gaming', 'hacksawgaming'],
            'evolution'     => ['game', 'Evolution', 'evolution'],
            'ezugi'         => ['game', 'Ezugi', 'funky'],
            'habanero'      => ['game', 'Habanero', 'habanero'],
            'yggdrasil'     => ['game', 'Yggdrasil', 'yggdrasil'],
            'playson'       => ['game', 'Playson', 'playson'],
            'betsoft'       => ['game', 'BetSoft', 'betsoft'],
            'joker'         => ['game', 'Joker', 'joker'],
            'onehack'       => ['game', 'OneHack', 'sbo'],
            'bng'           => ['game', 'BNG', 'supergame'],
            'supergame'     => ['game', 'Super Game', 'supergame'],
            'iwin'          => ['game', 'iWin', 'iwin'],
            'infinitygaming'=> ['game', 'Infinity Gaming', 'infinitygaming'],
            // ---- Bổ sung: logo lấy từ /img/providers ----
            'sexy'          => ['game', 'SEXY Gaming', 'sexy'],
            'tg'            => ['game', 'TG (Tiền Giàu)', 'tg'],
            'dg'            => ['game', 'DG (Đại Chiến)', 'dg'],
            'wm'            => ['game', 'WM Casino', 'wm'],
            'choice'        => ['game', 'Choice Games', 'choice'],
            'bg'            => ['game', 'B Bingo', 'bg'],
            'bbin'          => ['game', 'BBIN', 'bbin'],
            'mglive'        => ['game', 'MG Live', 'mg-live'],
            'hrg'           => ['game', 'H.R.G', 'hrg'],
            'sa'            => ['game', 'SA Gaming', 'sa'],
            'fc'            => ['game', 'FC (Fug Func)', 'fc'],
            'vg'            => ['game', 'VGSlots', 'vg'],
            'game168'       => ['game', '168 GAME', '168game'],
            'mw'            => ['game', 'Microgaming Slots', 'mw'],
            'wg'            => ['game', 'WG (WGWIN)', 'wg'],
            'tp'            => ['game', 'TP (Triple Play)', 'tp'],
            'va'            => ['game', 'VA Slot', 'va'],
            'nextspin'      => ['game', 'Next Spin', 'nextspin'],
            'asgaming'      => ['game', 'AS Gaming', 'as-gaming'],
            'mgaming'       => ['game', 'MGaming', 'mg'],
            'vp'            => ['game', 'VP (Vortex)', 'vp'],
            'bgaming'       => ['game', 'BGaming', 'bgaming'],
            'g759'          => ['game', '759 Gaming', '759gaming'],
            'spribe'        => ['game', 'Spribe', 'spribe'],
            'xgame'         => ['game', 'X Game (Crash)', 'x-game'],
            'saba'          => ['game', 'SABA Sports', 'saba'],
            'bti'           => ['game', 'BTI Sport', 'bti'],
            'cmd'           => ['game', 'CMD368', 'cmd'],
            'imsport'       => ['game', 'IM Sport', 'im'],
            'poly'          => ['game', 'Poly Sport', 'poly'],
            'crownsport'    => ['game', 'Crown Sport', 'crown-sport'],
            'g3sing'        => ['game', '3 Sing', '3sing'],
            'v8'            => ['game', 'V8 Card', 'v8'],
            'king'          => ['game', 'KingMidas', 'king'],
            'funky'         => ['game', 'Funky Game', 'funky'],
            'tcg'           => ['game', 'TCG', 'tcg'],
            'db'            => ['game', 'DB (Đá Bạch)', 'db'],
        ];

        $m = [];
        foreach ($raw as $slug => [$group, $label, $file]) {
            $groups = array_values(array_filter(array_map('trim', explode(',', (string)$group))));
            $primary = $groups[0] ?? 'sim';
            $m[$slug] = [
                'group'  => $primary,
                'groups' => $groups,
                'label'  => $label,
                'file'   => $file,
                'path'   => "img/brands/{$primary}/{$file}",
            ];
        }
        return $m;
    }
}

if (!function_exists('brand_slug')) {
    /**
     * Chuẩn hoá tên dịch vụ / sản phẩm về slug brand để tra logo.
     * Nhận diện theo từ khoá trong tên, ví dụ "Facebook Ads" → "facebook".
     */
    function brand_slug(string $name): ?string
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }
        $manifest = brand_manifest();

        // Khớp chính xác
        if (isset($manifest[$name])) {
            return $name;
        }

        // Chuẩn hoá ký tự đặc biệt + bỏ dấu
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($translit === false || $translit === '') {
            $translit = $name;
        }
        $plain = preg_replace('/[^a-z0-9]+/', '', $translit);

        foreach ($manifest as $slug => $meta) {
            $s = preg_replace('/[^a-z0-9]+/', '', $slug);
            if ($plain === $s) {
                return $slug;
            }
        }

        // Khớp theo từ khoá chứa trong tên (sắp xếp theo độ dài giảm dần)
        $slugs = array_keys($manifest);
        usort($slugs, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($slugs as $slug) {
            if (preg_match('/\b' . preg_quote($slug, '/') . '\b/', $name)) {
                return $slug;
            }
        }
        return null;
    }
}

if (!function_exists('brand_logo_url')) {
    /**
     * URL logo thương hiệu.
     * Thứ tự ưu tiên: svg → png trong /img/brands/{group}/, sau đó /img/providers/,
     * cuối cùng là placeholder.svg.
     */
    function brand_logo_url(string $name, string $group = ''): string
    {
        $slug = brand_slug($name);
        if ($slug === null) {
            return '';
        }
        $meta = brand_manifest()[$slug];
        if ($group !== '') {
            $groups = $meta['groups'] ?? [$meta['group']];
            if (!in_array($group, $groups, true)) {
                return '';
            }
        }

        // Thử lần lượt từng nhóm (một brand có thể nằm ở nhiều nhóm)
        $groups = $meta['groups'] ?? [$meta['group']];
        $rel = '';
        foreach ($groups as $g) {
            $rel = 'img/brands/' . $g . '/' . $meta['file'];
            foreach (['svg', 'png'] as $ext) {
                if (is_file(BASE_PATH . '/public/assets/' . $rel . '.' . $ext)) {
                    return asset($rel . '.' . $ext);
                }
            }
        }

        // Fallback: thư mục providers (nhiều nhà phát hành chỉ có logo ở đây)
        $file = strtolower((string)$meta['file']);
        $altNames = [$file];
        if ($file === 'pragmaticplay') {
            $altNames[] = 'pragmatic-play';
        }
        if ($file === 'pgsoft') {
            $altNames[] = 'pg';
        }
        foreach ($altNames as $alt) {
            foreach (['svg', 'png', 'jpg', 'jpeg', 'webp'] as $ext) {
                if (is_file(BASE_PATH . '/public/assets/img/providers/' . $alt . '.' . $ext)) {
                    return asset('img/providers/' . $alt . '.' . $ext);
                }
            }
        }

        // Placeholder chung
        if (is_file(BASE_PATH . '/public/assets/img/placeholder.svg')) {
            return asset('img/placeholder.svg');
        }
        return '';
    }
}

if (!function_exists('brand_logo')) {
    /**
     * Thẻ <img> logo thương hiệu, tự ẩn nếu không tìm thấy.
     * $name: tên dịch vụ/sản phẩm ("Cursor Pro", "Facebook", ...)
     */
    function brand_logo(string $name, string $group = '', string $class = 'brand-logo'): string
    {
        $url = brand_logo_url($name, $group);
        if ($url === '') {
            return '';
        }
        $label = $name;
        $slug  = brand_slug($name);
        if ($slug !== null) {
            $label = brand_manifest()[$slug]['label'];
        }
        return '<img src="' . e($url) . '" alt="' . e($label) . '" class="' . e($class) . '" '
            . 'loading="lazy" decoding="async" onerror="this.remove()">';
    }
}

if (!function_exists('brand_by_group')) {
    /**
     * Danh sách brand theo nhóm, dùng cho lưới danh mục có logo.
     */
    function brand_by_group(string $group): array
    {
        $out = [];
        foreach (brand_manifest() as $slug => $meta) {
            $groups = $meta['groups'] ?? [$meta['group']];
            if (!in_array($group, $groups, true)) {
                continue;
            }
            $out[] = $slug;
        }
        return $out;
    }
}

if (!function_exists('brand_grid_items')) {
    /**
     * Lưới danh mục thương hiệu đầy đủ theo nhóm.
     *
     * Trả về mảng ['slug','label','url','count'] — luôn gồm đủ brand của nhóm
     * (kể cả khi chưa có dịch vụ nào trong DB) để trang hiển thị lưới logo
     * đầy đủ, không bị trống.
     *
     * @param array<string,int> $counts  đếm số dịch vụ theo label
     */
    function brand_grid_items(string $group, array $counts = []): array
    {
        $out = [];
        foreach (brand_by_group($group) as $slug) {
            $meta = brand_manifest()[$slug];
            $out[] = [
                'slug'  => $slug,
                'label' => $meta['label'],
                'url'   => brand_logo_url($slug, $group),
                'count' => (int)($counts[$meta['label']] ?? $counts[$slug] ?? 0),
            ];
        }
        return $out;
    }
}

if (!function_exists('bank_code_slug')) {
    /**
     * Ánh xạ mã ngân hàng (MB, VCB, TCB...) → file logo trong /brands/bank.
     */
    function bank_code_slug(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        // Bỏ hậu tố loại tài khoản: MB-PRO, MB-EVENT, VCB-PRO...
        $base = preg_split('/[-_ ]/', $code)[0];

        static $map = [
            'MB' => 'mb', 'MBB' => 'mb', 'MBV' => 'mbv',
            'VCB' => 'vcb', 'VCBCB' => 'vccb', 'VCCB' => 'vccb',
            'TCB' => 'tcb', 'TECHCOMBANK' => 'tcb',
            'TPB' => 'tpb', 'TPBANK' => 'tpbank',
            'ICB' => 'icb', 'VIETINBANK' => 'icb',
            'BIDV' => 'bidv', 'BID' => 'bidv',
            'ACB' => 'acb',
            'AGRI' => 'agribank', 'AGRIBANK' => 'agribank',
            'VIB' => 'vib',
            'SEAB' => 'seab', 'SEABANK' => 'seabank',
            'SCB' => 'scb', 'SCVN' => 'scvn',
            'PVDB' => 'pvdb', 'PVBANK' => 'pvdb',
            'ABB' => 'abbank', 'AB' => 'abbank',
            'LPB' => 'lpbank', 'LPBANK' => 'lpbank',
            'VBB' => 'vietbank', 'VIETBANK' => 'vietbank',
            'VCC' => 'vccorp', 'VCCORP' => 'vccorp',
            'SHB' => 'shb', 'SHBV' => 'shbvn',
            'MSB' => 'msb',
            'OCB' => 'ocb',
            'BVB' => 'bvb', 'BV' => 'bvb',
            'VRB' => 'vrb',
            'STB' => 'stb',
            'IVB' => 'ivb',
            'NCB' => 'ncb',
            'HDB' => 'hdb',
            'EIB' => 'eib',
            'BSP' => 'bsp',
            'GPB' => 'gpb',
            'CIV' => 'cimb', 'CIMB' => 'cimb',
            'CITI' => 'citibank', 'CTB' => 'citibank',
            'HSBC' => 'hsbc',
            'DBS' => 'dbs',
            'UOB' => 'uob',
            'KB' => 'kbank', 'KBANK' => 'kbank',
            'CAKE' => 'cake', 'CA' => 'cake',
            'PB' => 'pbank', 'P' => 'pbank',
            'UB' => 'ubank', 'U' => 'ubank',
            'MOMO' => 'momo', 'M' => 'momo',
            'ZALO' => 'zalopay',
            'VNPT' => 'vnptmoney',
            'VIETTEL' => 'vtlmoney', 'VTL' => 'vtlmoney',
            'BAC' => 'bab', 'BAB' => 'bab',
            'VAB' => 'vab', 'VBA' => 'vba',
            'VBSP' => 'vbsp',
            'MVA' => 'mvas', 'MVAS' => 'mvas',
            'KL' => 'klb', 'KLB' => 'klb',
            'MAFC' => 'mafc',
            'TIMO' => 'timo',
            'TNEX' => 'tnex',
            'G' => 'gpb',
            // Bổ sung các mã còn thiếu trong bộ logo
            'PB' => 'pbvn', 'P' => 'pbvn', 'PBANK' => 'pbvn',
            'SCVN' => 'scvn', 'SHBV' => 'shbvn',
            'TECHCOMBANK' => 'tcb', 'TECHCOM' => 'tcb',
            'VIETINBANK' => 'icb', 'VIC' => 'icb',
            'AGRI' => 'agribank',
            'PVBANK' => 'pvdb',
            'LPBANK' => 'lpbank',
            'VIETBANK' => 'vietbank',
            'VCCORP' => 'vccorp',
            'BV' => 'bvb', 'BVBank' => 'bvb',
            'STBANK' => 'stb',
            'HDBANK' => 'hdb', 'HDB' => 'hdb',
            'EXIM' => 'eib', 'EIB' => 'eib',
            'BSP' => 'bsp',
            'GPBANK' => 'gpb',
            'KEBHANAHN' => 'kebhanahn', 'KEBHANAHCM' => 'kebhanahcm',
            'KBHCM' => 'kbhcm', 'KBHN' => 'kbhn',
            'NHB' => 'nhb hn', 'NHBH' => 'nhb hn',
            'WON' => 'wvn',
            'BICB' => 'bidc',
            'CUB' => 'cubhcm',
            'ESUN' => 'esun',
            'BOCHK' => 'bochk',
            'BNP' => 'bnp hcm',
            'HLBVN' => 'hlbvn',
            'IBK' => 'ibk - hcm',
            'SGICB' => 'sgicb',
            'VIKKI' => 'vikki',
            'PGB' => 'pgb',
            'PVCB' => 'pvcb',
            'CBB' => 'cbb',
            'D' => 'pvdb',
            'PVB' => 'pvdb',
        ];

        if (isset($map[$code])) {
            return $map[$code];
        }
        if (isset($map[$base])) {
            return $map[$base];
        }
        return null;
    }
}

if (!function_exists('bank_logo')) {
    /**
     * Logo ngân hàng theo mã (MB, VCB, TCB...). Trả về '' nếu chưa có file.
     */
    function bank_logo(string $code, string $class = 'brand-logo'): string
    {
        $slug = bank_code_slug($code);
        if ($slug === null) {
            return '';
        }
        $svgAbs = BASE_PATH . '/public/assets/img/brands/bank/' . $slug . '.svg';
        $pngAbs = BASE_PATH . '/public/assets/img/brands/bank/' . $slug . '.png';
        if (is_file($svgAbs)) {
            $url = asset('img/brands/bank/' . $slug . '.svg');
        } elseif (is_file($pngAbs)) {
            $url = asset('img/brands/bank/' . $slug . '.png');
        } else {
            return '';
        }
        return '<img src="' . e($url) . '" alt="' . e(strtoupper($code)) . '" class="' . e($class) . '" '
            . 'loading="lazy" decoding="async" onerror="this.remove()">';
    }
}

if (!function_exists('bank_display_name')) {
    /** Tên đầy đủ của ngân hàng theo mã. */
    function bank_display_name(string $code, string $fallback = ''): string
    {
        static $names = [
            'MB' => 'MB Bank', 'VCB' => 'Vietcombank', 'TCB' => 'Techcombank',
            'TPB' => 'TPBank', 'ICB' => 'Vietinbank', 'BIDV' => 'BIDV',
            'ACB' => 'ACB', 'AGRI' => 'Agribank', 'VIB' => 'VIB',
            'SEAB' => 'SeABank', 'SCB' => 'SCB', 'PVDB' => 'PVBank',
            'ABB' => 'ABBank', 'LPB' => 'LPBank', 'VBB' => 'Vietbank',
            'VCC' => 'VCCorp', 'SHB' => 'SHB', 'MSB' => 'MSB',
            'OCB' => 'OCB', 'BVB' => 'BVBank', 'VRB' => 'VRBank',
            'STB' => 'STBank', 'IVB' => 'IVB', 'NCB' => 'NCB',
            'HDB' => 'HDBank', 'EIB' => 'Eximbank', 'BSP' => 'BSP',
            'GPB' => 'GPBank', 'MOMO' => 'MoMo', 'CAKE' => 'Cake',
        ];
        $code = strtoupper(trim($code));
        $base = preg_split('/[-_ ]/', $code)[0];
        if (isset($names[$code])) {
            return $names[$code];
        }
        if (isset($names[$base])) {
            return $names[$base];
        }
        return $fallback !== '' ? $fallback : $code;
    }
}
