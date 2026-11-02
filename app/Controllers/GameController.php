<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Router;
use App\Services\AdService;

/**
 * Kho game + chi tiết game.
 */
class GameController
{
    public function index(): void
    {
        $where  = ['g.status = "active"'];
        $params = [];

        // Lọc theo nhà cung cấp
        if (!empty($_GET['provider'])) {
            $where[] = 'p.code = ?';
            $params[] = $_GET['provider'];
        }

        // Lọc theo loại game
        if (!empty($_GET['type'])) {
            $allowed = ['slot', 'live', 'card', 'table', 'crash', 'jackpot', 'other'];
            if (in_array($_GET['type'], $allowed, true)) {
                $where[] = 'g.game_type = ?';
                $params[] = $_GET['type'];
            }
        }

        // Lọc theo cờ (free / featured / hot / new)
        $flagMap = [
            'free'     => 'g.is_free',
            'featured' => 'g.is_featured',
            'hot'      => 'g.is_hot',
            'new'      => 'g.is_new',
        ];
        foreach ($flagMap as $key => $col) {
            if (!empty($_GET[$key])) {
                $where[] = $col . ' = 1';
            }
        }

        // Tìm kiếm
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(g.name LIKE ? OR g.slug LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        // Phân trang
        $perPage = 24;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $total = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM games g JOIN providers p ON p.id = g.provider_id WHERE $whereSql",
            $params
        );

        $games = Database::fetchAll(
            "SELECT g.*, p.name AS provider_name, p.code AS provider_code
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE $whereSql
             ORDER BY g.sort_order DESC, g.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $providers = Database::fetchAll(
            'SELECT p.*, COUNT(g.id) AS game_count
             FROM providers p LEFT JOIN games g ON g.provider_id = p.id AND g.status = "active"
             WHERE p.status = "active" GROUP BY p.id ORDER BY p.sort_order, p.name'
        );

        $types = Database::fetchAll(
            'SELECT game_type, COUNT(*) AS c FROM games WHERE status = "active" GROUP BY game_type'
        );

        $providerFilter = (string)($_GET['provider'] ?? '');
        $typeFilter     = (string)($_GET['type'] ?? '');

        $adsSidebar = AdService::forSlot('sidebar', 3);

        view('games/index', [
            'games'          => $games,
            'providers'      => $providers,
            'types'          => $types,
            'total'          => $total,
            'page'           => $page,
            'perPage'        => $perPage,
            'pages'          => (int)ceil($total / $perPage),
            'q'              => $q,
            'providerFilter' => $providerFilter,
            'typeFilter'     => $typeFilter,
            'adsSidebar'     => $adsSidebar,
        ]);
    }

    public function show(string $slug): void
    {
        $game = Database::fetch(
            'SELECT g.*, p.name AS provider_name, p.code AS provider_code, p.id AS pid
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.slug = ? AND g.status = "active" LIMIT 1',
            [$slug]
        );

        if (!$game) {
            abort(404, 'Không tìm thấy game');
        }

        $related = Database::fetchAll(
            'SELECT g.*, p.name AS provider_name FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.provider_id = ? AND g.id != ? AND g.status = "active" LIMIT 8',
            [$game['provider_id'], $game['id']]
        );

        view('games/show', compact('game', 'related'));
    }
}
