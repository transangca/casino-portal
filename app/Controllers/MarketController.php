<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Services\MarketService;

/**
 * Chợ tài nguyên: bán / mua tài khoản, tool, dịch vụ.
 */
class MarketController
{
    public function index(): void
    {
        $category = (string)($_GET['category'] ?? '');
        $q        = trim((string)($_GET['q'] ?? ''));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = 24;

        $items = MarketService::all($category !== '' ? $category : null, $q, $limit, ($page - 1) * $limit);
        $total = MarketService::count($category !== '' ? $category : null, $q);
        $pages = (int)ceil($total / $limit);

        view('market/index', [
            'title'    => 'Chợ tài nguyên',
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'category' => $category,
            'q'        => $q,
            'cats'     => MarketService::CATEGORIES,
        ]);
    }

    public function show(string $slug): void
    {
        $item = MarketService::bySlug($slug);
        if (!$item) {
            abort(404, 'Không tìm thấy sản phẩm');
        }

        view('market/show', [
            'title' => $item['title'],
            'item'  => $item,
            'cats'  => MarketService::CATEGORIES,
        ]);
    }

    /**
     * Trang đăng bán tài nguyên.
     */
    public function sellForm(): void
    {
        view('market/sell', [
            'title' => 'Đăng bán tài nguyên',
            'cats'  => MarketService::CATEGORIES,
        ]);
    }

    /**
     * Đăng bán tài nguyên.
     */
    public function create(): void
    {
        CSRF::verifyOrFail();

        $result = MarketService::create((int)Auth::id(), [
            'title'       => $_POST['title'] ?? '',
            'category'    => $_POST['category'] ?? 'account',
            'description' => $_POST['description'] ?? '',
            'price'       => $_POST['price'] ?? 0,
            'stock'       => $_POST['stock'] ?? -1,
            'image'       => $_POST['image'] ?? '',
            'contact'     => $_POST['contact'] ?? '',
        ]);

        flash($result['ok'] ? 'success' : 'error', $result['message']);

        if ($result['ok']) {
            redirect(url('/market/item/' . $result['slug']));
        }
        redirect(url('/market/sell'));
    }

    /**
     * Mua tài nguyên.
     */
    public function buy(): void
    {
        CSRF::verifyOrFail();

        $id   = (int)($_POST['id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        $result = MarketService::buy((int)Auth::id(), $id, $note);
        flash($result['ok'] ? 'success' : 'error', $result['message']);

        redirect(url('/market/my'));
    }

    /**
     * Trang quản lý sàn của tôi: tin đăng + đơn hàng.
     */
    public function my(): void
    {
        $userId = (int)Auth::id();

        view('market/my', [
            'title'  => 'Sàn của tôi',
            'items'  => MarketService::myItems($userId),
            'orders' => MarketService::myOrders($userId),
            'cats'   => MarketService::CATEGORIES,
        ]);
    }
}
