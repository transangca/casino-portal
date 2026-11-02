<?php
namespace App\Services\Provider;

/**
 * Driver sảnh KingMidas.
 *
 * Dùng mẫu launch URL dạng:
 *   https://{host}/{game_id}/index.html?ot={token}&l=vi&btt=2
 * Icon: https://publiccdn.kingmidasgames.net/500x500images/KingMidas/{slug}_500x500_en.jpg
 */
class KingMidasProvider implements ProviderInterface
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [
            'game_url'  => 'https://m.kingmidasgames.net/{game_id}/index.html',
            'icon_base' => 'https://publiccdn.kingmidasgames.net/500x500images/KingMidas/{slug}_500x500_en.jpg',
        ];
    }

    public function code(): string
    {
        return 'king-midas-games';
    }

    public function name(): string
    {
        return 'KingMidas';
    }

    public function fetchGames(): array
    {
        // Danh mục KingMidas được seed sẵn trong DB (bảng games).
        return [];
    }

    public function buildLaunchUrl(array $game, array $user, array $context = []): ?array
    {
        $gameId = $game['provider_game_id'] ?? null;
        if (!$gameId) {
            return null;
        }

        $sessionToken = bin2hex(random_bytes(32));
        $url = str_replace('{game_id}', (string)$gameId, $this->config['game_url']);

        $url .= '?' . http_build_query([
            'ot'  => $context['operator_token'] ?? $sessionToken,
            'l'   => $context['lang'] ?? 'vi',
            'btt' => $context['bet_type'] ?? '2',
        ]);

        return ['url' => $url, 'token' => $sessionToken];
    }

    public function pushBalance(int $userId, float $amount): bool
    {
        return true;
    }

    public function fetchBalance(int $userId): ?float
    {
        return null;
    }

    public function iconFor(string $slug): string
    {
        return str_replace('{slug}', $slug, $this->config['icon_base']);
    }
}
