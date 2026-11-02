<?php
namespace App\Services\Provider;

/**
 * Driver PGSoft (Pocket Games Soft).
 *
 * Dựa trên tài liệu Integration Document chính thức (Transfer/Seamless mode)
 * và bản demo công khai của PGSoft:
 *
 *  1. LAUNCH — URL Scheme (HTML scheme):
 *     {PublicDomain}/{gameId}/index.html?ot={operator_token}&ops={session}&btt={mode}&l={lang}
 *     Bản HTML scheme mới gọi API:
 *     POST {PgSoftAPIDomain}/external-game-launcher/api/v1/GetLaunchURLHTML
 *     body: operator_token, path, extra_args (btt, ops, l), url_type, client_ip
 *
 *  2. SEAMLESS — Operator API do PGSoft gọi lại:
 *     POST {OperatorAPIDomain}/VerifySession  → player_name, nickname, currency
 *     POST {OperatorAPIDomain}/Cash/Get       → balance_amount, currency_code, updated_time
 *     POST {OperatorAPIDomain}/Cash/Change    → bet/payout, idempotent theo transaction_id
 *
 * Nếu hệ thống chưa được PGSoft cấp operator_token/secret_key thì chỉ chơi
 * được chế độ demo (btt=2) — vẫn chạy game thật của nhà phát hành.
 */
class PgSoftProvider implements ProviderInterface
{
    private array $config;

    private static ?array $manifest = null;
    private static array $nameCache = [];

    private const MANIFEST_URL  = 'https://public.pg-demo.com/pages/hash-manifest.json';
    private const MANIFEST_FILE = 'pgsoft_manifest.json';
    private const NAMES_FILE    = 'pgsoft_names.json';
    private const LIVE_FILE     = 'pgsoft_live.json';

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('providers.pgsoft');
    }

    public function code(): string
    {
        return 'pgsoft';
    }

    public function name(): string
    {
        return 'PGSoft';
    }

    public function gameHost(): string
    {
        return rtrim((string)($this->config['game_host'] ?? 'https://m.pg-demo.com'), '/');
    }

    public function defaultBetType(): ?int
    {
        return 2; // 1 = credit (cần operator token), 2 = demo/free
    }

    // ------------------------------------------------------------------
    // Danh sách game
    // ------------------------------------------------------------------

    public function fetchGames(): array
    {
        $manifest = $this->manifest();
        if (!$manifest) {
            return [];
        }

        $games = [];

        // Icon mỗi game nằm ở image/en/Small_Icon/{gameId}/xxx.png
        foreach ($manifest as $original => $hashed) {
            if (!preg_match('#^image/en/Small_Icon/(\d+)/[^/]+\.png$#', (string)$original, $m)) {
                continue;
            }
            $gameId = $m[1];

            if (isset($games[$gameId])) {
                continue;
            }

            $games[$gameId] = [
                'game_id'   => $gameId,
                'name'      => '',
                'icon'      => 'https://public.pg-demo.com/pages/static/' . $hashed,
                'embed_url' => $this->gameUrl($gameId),
                'demo_url'  => $this->gameUrl($gameId),
                'game_type' => 'slot',
            ];
        }

        // Lấy tên game thật từ PGSoft (có cache)
        $realNames = $this->fetchRealNames(array_keys($games));
        foreach ($games as $gid => &$g) {
            $g['name'] = ($realNames[$gid] ?? '') !== ''
                ? $realNames[$gid]
                : $this->displayName($gid);
        }
        unset($g);

        // Chỉ giữ game còn live trên lobby PGSoft
        $probe = $this->probeLive(array_keys($games));
        foreach ($probe['dead'] as $gid) {
            unset($games[$gid]);
        }

        return array_values($games);
    }

    /**
     * Kiểm tra game shell nào còn live.
     *
     * @param string[] $gameIds
     * @return array{gameIds:string[], dead:string[]}
     */
    public function probeLive(array $gameIds): array
    {
        $cacheFile = BASE_PATH . '/storage/' . self::LIVE_FILE;

        $known = [];
        if (is_file($cacheFile)) {
            $decoded = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($decoded)) {
                $known = $decoded;
            }
        }

        $needCheck = array_values(array_filter($gameIds, static fn($id) => !isset($known[$id])));

        if ($needCheck) {
            $mh      = curl_multi_init();
            $handles = [];
            foreach ($needCheck as $id) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $this->gameUrl((string)$id),
                    CURLOPT_NOBODY         => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0',
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[(string)$id] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh, 0.5);
                }
            } while ($running > 0);

            foreach ($handles as $id => $ch) {
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $known[$id] = ($code >= 200 && $code < 400);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);

            @file_put_contents($cacheFile, json_encode($known, JSON_PRETTY_PRINT));
        }

        $live = [];
        $dead = [];
        foreach ($gameIds as $id) {
            if ($known[$id] ?? false) {
                $live[] = $id;
            } else {
                $dead[] = $id;
            }
        }

        return ['gameIds' => $live, 'dead' => $dead];
    }

    /**
     * Lấy tên game thật từ trang game của PGSoft (`<title>`), có cache.
     *
     * @param string[] $gameIds
     * @return array<string,string>
     */
    public function fetchRealNames(array $gameIds): array
    {
        $cacheFile = BASE_PATH . '/storage/' . self::NAMES_FILE;
        $cache     = self::$nameCache;
        if (!$cache && is_file($cacheFile)) {
            $decoded = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($decoded)) {
                $cache = self::$nameCache = $decoded;
            }
        }

        $missing = array_values(array_filter($gameIds, static fn($id) => !isset($cache[$id])));
        if ($missing) {
            foreach ($this->curlMultiTitles($missing) as $id => $title) {
                $cache[$id] = $title;
            }
            self::$nameCache = $cache;
            @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $out = [];
        foreach ($gameIds as $id) {
            if (isset($cache[$id])) {
                $out[$id] = $cache[$id];
            }
        }
        return $out;
    }

    /**
     * @param string[] $gameIds
     * @return array<string,string>
     */
    private function curlMultiTitles(array $gameIds): array
    {
        if (!function_exists('curl_multi_init')) {
            return [];
        }

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($gameIds as $id) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->gameUrl((string)$id),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[(string)$id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0);

        $titles = [];
        foreach ($handles as $id => $ch) {
            $body = (string)curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body !== '' && $code >= 200 && $code < 400) {
                if (preg_match('#<title>([^<]{1,120})</title>#i', $body, $m)) {
                    $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $title = trim(str_replace(["\n", "\r", '"'], ' ', $title));
                    $title = preg_replace('#\s+#u', ' ', $title) ?? $title;
                    if ($title !== '') {
                        $titles[$id] = $title;
                    }
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $titles;
    }

    private function displayName(string $gameId): string
    {
        $name = \App\Core\Database::fetchColumn(
            'SELECT name FROM games WHERE provider_id = (SELECT id FROM providers WHERE code = "pgsoft") AND provider_game_id = ?',
            [$gameId]
        );
        if ($name && $name !== '' && !preg_match('#^PG \d+$#', (string)$name)) {
            return (string)$name;
        }

        $cached = $this->fetchRealNames([$gameId]);
        if (isset($cached[$gameId])) {
            return $cached[$gameId];
        }

        return $name ?: 'PG ' . $gameId;
    }

    public function gameUrl(string $gameId): string
    {
        return $this->gameHost() . '/' . $gameId . '/index.html';
    }

    // ------------------------------------------------------------------
    // Launch
    // ------------------------------------------------------------------

    /**
     * Tạo URL launch theo URL Scheme chuẩn của PGSoft.
     *
     * - ot  : operator token (mã định danh operator của ta)
     * - ops : token phiên do hệ thống ta sinh — PGSoft dùng nó để gọi
     *        VerifySession để xác thực người chơi
     * - btt : 1 = credit (chơi bằng tiền thật), 2 = demo
     * - l   : ngôn ngữ hiển thị
     */
    public function buildLaunchUrl(array $game, array $user, array $context = []): ?array
    {
        $gameId = $game['provider_game_id'] ?? $game['game_id'] ?? null;
        if (!$gameId) {
            return null;
        }

        $sessionToken = (string)($context['session_token'] ?? bin2hex(random_bytes(32)));

        // Chế độ credit chỉ dùng được khi PGSoft đã cấp operator token
        // và ta đã đăng ký operator API. Nếu không → chơi demo.
        $betType = (int)($context['bet_type'] ?? $this->defaultBetType());
        if (!$this->hasOperatorCredential()) {
            $betType = 2;
        }

        $params = [
            'ot'  => (string)($context['operator_token'] ?? ''),
            'ops' => $sessionToken,
            'btt' => $betType,
            'l'   => (string)($context['lang'] ?? 'vi'),
            'f'   => (string)($context['exit_url'] ?? ''),
        ];

        $params = array_filter($params, static fn($v) => $v !== '');

        // Ưu tiên URL launch thật lưu trong DB, fallback về template của sảnh.
        $path = (string)($game['embed_url'] ?? '');
        if ($path === '') {
            $path = $this->gameUrl((string)$gameId);
        }

        return [
            'url'      => $path . '?' . http_build_query($params),
            'token'    => $sessionToken,
            'bet_type' => $betType,
            'mode'     => $betType === 1 ? 'credit' : 'demo',
        ];
    }

    /**
     * PGSoft có cấp operator_token + secret_key cho ta chưa.
     */
    public function hasOperatorCredential(): bool
    {
        return (string)($this->config['operator_token'] ?? '') !== ''
            && (string)($this->config['secret_key'] ?? '') !== '';
    }

    // ------------------------------------------------------------------
    // Đồng bộ ví
    // ------------------------------------------------------------------

    /**
     * Operator giữ số dư tại hệ thống ta → không cần push từ xa.
     * Số dư được cập nhật khi game gọi endpoint operator của ta.
     */
    public function pushBalance(int $userId, float $amount): bool
    {
        return true;
    }

    public function fetchBalance(int $userId): ?float
    {
        return (float)\App\Core\Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
    }

    // ------------------------------------------------------------------
    // Manifest
    // ------------------------------------------------------------------

    private function manifest(): ?array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $cacheFile = BASE_PATH . '/storage/' . self::MANIFEST_FILE;

        // Cache 12 giờ
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 43200) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && $cached) {
                return self::$manifest = $cached;
            }
        }

        $raw = \App\Services\HttpClient::get(self::MANIFEST_URL, [], ['timeout' => 30]);
        if (!$raw) {
            if (is_file($cacheFile)) {
                $cached = json_decode((string)file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return self::$manifest = $cached;
                }
            }
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        @file_put_contents($cacheFile, $raw);
        return self::$manifest = $decoded;
    }

    public static function clearManifestCache(): void
    {
        $cacheFile = BASE_PATH . '/storage/' . self::MANIFEST_FILE;
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
        self::$manifest = null;
    }
}
