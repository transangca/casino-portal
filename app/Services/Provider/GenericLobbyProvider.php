<?php
namespace App\Services\Provider;

use App\Core\Database;
use App\Services\LobbyService;

/**
 * Driver dùng chung cho các sảnh phát hành theo URL Scheme chuẩn.
 *
 * Mô hình URL phổ biến của các sảnh slot (KingMidas, JILI, CQ9, ...):
 *   {host}/{gameId}/index.html?ot={operator_token}&l={lang}&btt={mode}
 *
 * Driver này giữ đúng contract của ProviderInterface, không tự tạo game,
 * chỉ trỏ tới game thật của nhà phát hành.
 */
class GenericLobbyProvider implements ProviderInterface
{
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    public function code(): string
    {
        return (string)($this->config['code'] ?? 'generic');
    }

    public function name(): string
    {
        return (string)($this->config['name'] ?? 'Sảnh game');
    }

    public function gameHost(): string
    {
        return rtrim((string)($this->config['game_host'] ?? ''), '/');
    }

    public function defaultBetType(): ?int
    {
        $bt = $this->config['bet_type'] ?? 2;
        return $bt === null ? null : (int)$bt;
    }

    public function fetchGames(): array
    {
        // Danh mục được seed sẵn vào DB từ nguồn chính thức của nhà phát hành.
        return [];
    }

    public function buildLaunchUrl(array $game, array $user, array $context = []): ?array
    {
        $gameId = $game['provider_game_id'] ?? $game['game_id'] ?? null;
        if (!$gameId || $this->gameHost() === '') {
            return null;
        }

        $sessionToken = (string)($context['session_token'] ?? bin2hex(random_bytes(32)));
        $betType      = (int)($context['bet_type'] ?? ($this->defaultBetType() ?? 2));

        // Chuẩn seamless của các nhà phát hành:
        //   ot  = operator token
        //   ops = token phiên (nhà phát hành dùng nó để gọi operator API)
        //   btt = 1 credit (tiền thật) | 2 demo
        //   l   = ngôn ngữ
        $params = [
            'ot'  => (string)($context['operator_token'] ?? ''),
            'ops' => $sessionToken,
            'l'   => (string)($context['lang'] ?? 'vi'),
            'btt' => $betType,
        ];

        // Tham số riêng của sảnh (nếu có)
        foreach ((array)($this->config['extra_params'] ?? []) as $k => $v) {
            $params[$k] = $v;
        }

        $params = array_filter($params, static fn($v) => $v !== '');

        // Ưu tiên URL launch thật do nhà phát hành công bố (lưu trong DB),
        // sau đó mới dựng từ template của sảnh.
        $path = (string)($game['embed_url'] ?? '');
        $tpl  = (string)($this->config['game_url'] ?? $this->gameHost() . '/{game_id}/index.html');

        // URL trong DB nhiều khi là trang marketing của nhà phát hành
        // (vd. 168game.net/game-slot, vpslot.net/games.php). Nhúng URL đó sẽ
        // hiện nguyên website của họ → bỏ qua, dùng template game thật.
        if ($path !== '' && LobbyService::isMarketingPage($path)) {
            $path = '';
        }

        if ($path === '') {
            $path = str_replace(
                ['{game_id}', '{game_slug}'],
                [(string)$gameId, (string)($game['slug'] ?? $gameId)],
                $tpl
            );
        } elseif (str_contains($path, '{')) {
            $path = str_replace(
                ['{game_id}', '{game_slug}'],
                [(string)$gameId, (string)($game['slug'] ?? $gameId)],
                $path
            );
        }

        // URL lưu trong DB có thể đã kèm query string (vd. Pragmatic openGame.do?gameSymbol=..)
        // → ghép tham số bằng & để không sinh "??".
        $separator = str_contains($path, '?') ? '&' : '?';
        if (substr($path, -1) === '?' || substr($path, -1) === '&') {
            $separator = '';
        }

        return [
            'url'      => $path . $separator . http_build_query($params),
            'token'    => $sessionToken,
            'bet_type' => $betType,
            'mode'     => $betType === 1 ? 'credit' : 'demo',
        ];
    }

    public function pushBalance(int $userId, float $amount): bool
    {
        // Operator giữ số dư tại hệ thống ta.
        return true;
    }

    public function fetchBalance(int $userId): ?float
    {
        return (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Dựng URL icon từ mẫu của sảnh (nếu có).
     */
    public function iconFor(string $slug): string
    {
        $tpl = (string)($this->config['icon_base'] ?? '');
        if ($tpl === '') {
            return '';
        }
        return str_replace(['{slug}', '{game_id}'], $slug, $tpl);
    }
}
