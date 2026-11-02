<?php
namespace App\Services\Provider;

use App\Services\DenoCasinoService;

/**
 * Driver cho Deno Casino (CQ9 Live / MT Studio).
 *
 * Khác biệt với generic-lobby:
 *  - Không có URL game dạng {host}/{gameId}/index.html
 *  - Mỗi bàn là 1 "game" với mã bàn riêng (ABT41, DT01, SI01, MC01...)
 *  - URL mở bàn là deep-link: /{category}/?token=..&tableId=XXX&language=en
 *  - Lobby là React SPA, dữ liệu bàn đến qua WebSocket
 */
class DenoCasinoProvider implements ProviderInterface
{
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    public function code(): string
    {
        return (string)($this->config['code'] ?? 'deno-casino');
    }

    public function name(): string
    {
        return (string)($this->config['name'] ?? 'Deno Casino');
    }

    public function gameHost(): string
    {
        return DenoCasinoService::BASE_URL;
    }

    public function defaultBetType(): ?int
    {
        // Demo lobby: luôn tiền ảo (btt = 2)
        return (int)($this->config['bet_type'] ?? 2);
    }

    /**
     * Đồng bộ danh sách bàn từ lobby WebSocket.
     */
    public function fetchGames(): array
    {
        $lobby = DenoCasinoService::fetchLobby();
        $tables = DenoCasinoService::normalizeTables((array)($lobby['categories'] ?? []));

        $out = [];
        foreach ($tables as $t) {
            $tableId = (string)$t['table_id'];
            $cat     = (string)$t['category'];

            $out[] = [
                'game_id'    => $tableId,
                'name'       => $t['label'] . ' ' . $tableId,
                'slug'       => 'deno-' . strtolower($cat) . '-' . strtolower($tableId),
                'icon'       => '',
                'embed_url'  => DenoCasinoService::tableUrl($cat, $tableId),
                'demo_url'   => DenoCasinoService::tableUrl($cat, $tableId),
                'game_type'  => 'live',
                'provider'   => $this->code(),
                'raw'        => $t,
            ];
        }

        return $out;
    }

    /**
     * URL mở bàn trực tiếp.
     */
    public function buildLaunchUrl(array $game, array $user, array $context = []): ?array
    {
        $gameId = (string)($game['provider_game_id'] ?? $game['game_id'] ?? '');
        if ($gameId === '') {
            return null;
        }

        $category = (string)($game['game_config']['category'] ?? '');
        if ($category === '') {
            $category = (string)($game['category'] ?? 'baccarat');
        }

        $lang = (string)($context['lang'] ?? 'en');

        // URL đã lưu sẵn trong DB (deep-link chuẩn) → dùng luôn
        $path = (string)($game['embed_url'] ?? '');
        if ($path !== '' && str_contains($path, '{table_id}')) {
            $path = str_replace('{table_id}', rawurlencode($gameId), $path);
        }

        if ($path === '') {
            $path = DenoCasinoService::tableUrl($category, $gameId, $lang);
        }

        $sessionToken = (string)($context['session_token'] ?? bin2hex(random_bytes(32)));

        return [
            'url'      => $path,
            'token'    => $sessionToken,
            'bet_type' => 2,
            'mode'     => 'demo',
        ];
    }

    public function pushBalance(int $userId, float $amount): bool
    {
        // Lobby demo chỉ chơi bằng tiền ảo → không có chiều đẩy ví.
        return false;
    }

    public function fetchBalance(int $userId): ?float
    {
        // Số dư nằm trong lobby demo, không phải hệ thống ta.
        return null;
    }
}
