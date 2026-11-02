<?php
namespace App\Services;

use App\Services\Ws\WsClient;

/**
 * Deno Casino (CQ9 Live / MT Studio) — nhà phát hành casino trực tiếp.
 *
 * Kiến trúc:
 *   1. Lobby là React SPA tại gcdemo-lobby.cqgame.me
 *   2. Client gọi POST /api/lobby/auth (form-data) với token lobby
 *      → nhận về session token
 *   3. Client mở WebSocket /api/lobby/ws?token=... để nhận danh sách bàn
 *      (Categories, Tables, Banner, Marquee, ...)
 *   4. Bàn chơi mở bằng deep-link: /{category}/?token=..&tableId=XXX
 *
 * Service này dùng chung cho:
 *   - Đồng bộ danh sách bàn (games) vào DB
 *   - Sinh URL launch cho từng bàn
 */
class DenoCasinoService
{
    public const HOST     = 'gcdemo-lobby.cqgame.me';
    public const BASE_URL = 'https://gcdemo-lobby.cqgame.me';

    /**
     * Token lobby mặc định (demo). Có thể ghi đè trong config/app.php hoặc .env.
     */
    public static function lobbyToken(): string
    {
        $cfg = config('providers.deno-casino', []);
        $tok = (string)($cfg['lobby_token'] ?? getenv('DENO_LOBBY_TOKEN') ?? '');
        if ($tok === '') {
            $tok = 'eyJUeXAiOiJKV1QiLCJBbGciOiJIUzI1NiIsIkN0eSI6IiJ9.eyJSb2xlIjoiNmFlODBkZTk5ZjhiNTAxMDY1NDhlNWZhIiwiZXhwIjoxNzkzNTkyODY5LCJpYXQiOjE3OTM1OTI4MDl9.cxyo3Fm9aiq9i0uh6JoljzeK7_ApepLwLa8f15LLkVk';
        }
        return $tok;
    }

    /**
     * Đổi lobby token → session token (gọi được nhiều lần).
     */
    public static function auth(): ?string
    {
        $token = self::lobbyToken();

        $ch = curl_init(self::BASE_URL . '/api/lobby/auth');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POSTFIELDS     => http_build_query([
                'token'      => $token,
                'language'   => 'en',
                'dollarsign' => 'Y',
                'app'        => 'N',
                'detect'     => 'N',
            ]),
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            logger('deno auth failed: ' . $err, 'error');
            return null;
        }

        $json = json_decode((string)$raw, true);
        if (!is_array($json) || empty($json['data']['token'])) {
            logger('deno auth bad payload: ' . substr((string)$raw, 0, 200), 'error');
            return null;
        }

        return (string)$json['data']['token'];
    }

    /**
     * Mở kết nối WS lobby và trả về client (đã sẵn sàng nhận message).
     */
    public static function connectWs(string $sessionToken, int $timeout = 20): ?WsClient
    {
        $ws = new WsClient(self::HOST, 443, '/api/lobby/ws?token=' . urlencode($sessionToken) . '&language=en&nolobby=', true);
        $ws->setTimeout($timeout);

        try {
            $ws->connect();
        } catch (\Throwable $e) {
            logger('deno ws connect failed: ' . $e->getMessage(), 'error');
            return null;
        }

        return $ws;
    }

    /**
     * Lấy toàn bộ dữ liệu lobby (bàn, banner, marquee, logo) qua WebSocket.
     *
     * @return array{banners:array,marquee:array,tables:array,categories:array}
     */
    public static function fetchLobby(int $maxMessages = 80, int $maxSeconds = 25): array
    {
        $result = [
            'banners'    => [],
            'marquee'    => [],
            'tables'     => [],
            'categories' => [],
            'meta'       => [],
        ];

        $session = self::auth();
        if ($session === null) {
            return $result;
        }

        $ws = self::connectWs($session, 10);
        if ($ws === null) {
            return $result;
        }

        $seen = 0;
        $stop = microtime(true) + $maxSeconds;

        try {
            while ($seen < $maxMessages && microtime(true) < $stop) {
                $raw = $ws->read();
                if ($raw === null) {
                    break;
                }
                $seen++;

                $msg = json_decode($raw, true);
                if (!is_array($msg)) {
                    continue;
                }

                switch ((string)($msg['action'] ?? '')) {
                    case 'Categories':
                        $result['categories'] = (array)($msg['data']['categories'] ?? []);
                        break;

                    case 'Banner':
                        $result['banners'] = (array)($msg['data'] ?? []);
                        break;

                    case 'Marquee':
                        $result['marquee'] = (array)($msg['data'] ?? []);
                        break;

                    case 'GlobalSetting':
                        $result['meta']['global'] = (array)($msg['data'] ?? []);
                        break;

                    case 'UpdateBalance':
                        $result['meta']['balance'] = (array)($msg['data'] ?? []);
                        break;
                }
            }
        } finally {
            $ws->close();
        }

        return $result;
    }

    /**
     * Chuẩn hóa danh sách bàn từ payload "Categories".
     *
     * Mỗi category có ribbon.tables[] với tableId.
     * Trả về: [['table_id'=>..., 'category'=>..., 'label'=>...], ...]
     */
    public static function normalizeTables(array $categories): array
    {
        $labels = [
            'baccarat' => 'Baccarat',
            'dt'       => 'Dragon Tiger',
            'sicbo'    => 'Sicbo',
            'marble'   => 'Marble / PK10',
        ];

        $out = [];
        foreach ($categories as $cat) {
            $cid  = (string)($cat['categoryId'] ?? '');
            if ($cid === '') {
                continue;
            }
            $tables = (array)($cat['ribbon']['tables'] ?? []);
            foreach ($tables as $t) {
                $tid = (string)($t['tableId'] ?? '');
                if ($tid === '') {
                    continue;
                }
                $out[] = [
                    'table_id'  => $tid,
                    'category'  => $cid,
                    'label'     => $labels[$cid] ?? ucfirst($cid),
                ];
            }
        }

        return $out;
    }

    /**
     * URL mở bàn chơi trực tiếp (deep-link).
     */
    public static function tableUrl(string $category, string $tableId, string $lang = 'en'): string
    {
        $token = self::lobbyToken();

        return self::BASE_URL . '/' . rawurlencode($category) . '/'
             . '?token=' . urlencode($token)
             . '&tableId=' . urlencode($tableId)
             . '&language=' . urlencode($lang)
             . '&nolobby=';
    }

    /**
     * URL lobby chính (dùng nhúng iframe).
     */
    public static function lobbyUrl(string $lang = 'en'): string
    {
        $token = self::lobbyToken();

        return self::BASE_URL . '/lobby/'
             . '?token=' . urlencode($token)
             . '&language=' . urlencode($lang)
             . '&dollarsign=Y&app=N&detect=N';
    }

    /**
     * Logo nhà phát hành (lấy từ API auth).
     */
    public static function logoUrl(): string
    {
        $token = self::lobbyToken();

        $ch = curl_init(self::BASE_URL . '/api/lobby/auth');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POSTFIELDS     => http_build_query([
                'token'      => $token,
                'language'   => 'en',
                'dollarsign' => 'Y',
                'app'        => 'N',
                'detect'     => 'N',
            ]),
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $json = json_decode((string)$raw, true);
        $logo = (string)($json['data']['logo']['logoH'] ?? '');
        return $logo !== '' ? $logo : 'https://imgur.cq9site.com/ginkgo/mt/logo-h.svg';
    }
}
