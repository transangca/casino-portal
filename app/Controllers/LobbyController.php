<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AdService;
use App\Services\LobbyService;

/**
 * Trang sảnh riêng theo từng nhà phát hành + danh mục.
 *
 * Route:
 *   /lobbies               → danh sách tất cả sảnh
 *   /lobbies/{code}        → trang sảnh (có tab Demo / Đồng bộ ví)
 *   /category/{code}       → trang danh mục (Casino, Slot, Bắn Cá, ...)
 */
class LobbyController
{
    /**
     * Danh sách tất cả sảnh.
     */
    public function index(): void
    {
        $category = (string)($_GET['category'] ?? '');
        $q        = trim((string)($_GET['q'] ?? ''));

        $where  = [];
        $params = [];

        if ($category !== '') {
            $where[] = 'p.category = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql = 'SELECT p.*, c.name AS category_name, c.name_en AS category_name_en,
                       (SELECT COUNT(*) FROM games g WHERE g.provider_id = p.id AND g.status = "active") AS game_count
                FROM providers p LEFT JOIN game_categories c ON c.code = p.category
                WHERE p.status = "active"';

        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.sort_order, p.name';

        $providers = Database::fetchAll($sql, $params);
        $categories = LobbyService::categories();

        view('lobbies/index', compact('providers', 'categories', 'category', 'q'));
    }

    /**
     * Trang sảnh chi tiết — có 2 tab: Chơi thử (demo) & Đồng bộ ví.
     */
    public function show(string $code): void
    {
        $provider = LobbyService::find($code);
        if (!$provider) {
            abort(404, 'Không tìm thấy sảnh game');
        }

        $mode = (string)($_GET['mode'] ?? 'demo');
        if (!in_array($mode, ['demo', 'real'], true)) {
            $mode = 'demo';
        }

        $supportsWallet = LobbyService::supportsWallet($provider);

        // Sảnh chưa có token đồng bộ → chỉ cho chế độ demo
        if (!$supportsWallet) {
            $mode = 'demo';
        }

        $q     = trim((string)($_GET['q'] ?? ''));
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 48;
        $offset = ($page - 1) * $limit;

        $games = LobbyService::games($provider, $mode, $limit, $offset, $q);
        $total = LobbyService::countGames($provider, $mode, $q);
        $pages = (int)ceil($total / $limit);

        // Danh sách sảnh cùng danh mục (để đổi nhanh)
        $siblings = LobbyService::providers((string)($provider['category'] ?? ''));

        $ads = AdService::forSlot('lobby_top', 3);

        // Studio không có API danh mục → nhúng trực tiếp lobby chính thức
        $embedLobby = LobbyService::shouldEmbedLobby($provider);
        $embedUrl   = $embedLobby ? LobbyService::embedUrl($provider) : '';

        view('lobbies/show', compact(
            'provider', 'mode', 'supportsWallet', 'games', 'total',
            'page', 'pages', 'siblings', 'q', 'ads', 'embedLobby', 'embedUrl'
        ));
    }

    /**
     * Trang danh mục: Casino / Slot / Bắn Cá / ...
     */
    public function category(string $code): void
    {
        $cat = LobbyService::category($code);
        if (!$cat) {
            abort(404, 'Không tìm thấy danh mục');
        }

        $providers = LobbyService::providers($code);
        $ads       = AdService::forSlot('home_top', 3);

        // Game nổi bật của danh mục
        $games = Database::fetchAll(
            'SELECT g.*, p.name AS provider_name, p.code AS provider_code
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.status = "active" AND p.category = ?
             ORDER BY g.is_featured DESC, g.is_hot DESC, g.sort_order DESC LIMIT 24',
            [$code]
        );

        view('lobbies/category', compact('cat', 'providers', 'games', 'ads'));
    }
}
