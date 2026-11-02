<?php
namespace App\Controllers;

use App\Services\AdService;
use App\Services\NewsService;

/**
 * Tin tức / sự kiện.
 */
class NewsController
{
    public function index(): void
    {
        $type  = (string)($_GET['type'] ?? '');
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;

        $posts = NewsService::all($type, $limit, ($page - 1) * $limit);
        $total = NewsService::count($type);
        $pages = (int)ceil($total / $limit);

        $pinned = NewsService::latest(3, 'promotion');
        $ads    = AdService::forSlot('home_mid', 3);

        view('news/index', [
            'title'   => 'Tin tức & Sự kiện',
            'posts'   => $posts,
            'pinned'  => $pinned,
            'type'    => $type,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'types'   => NewsService::TYPES,
            'ads'     => $ads,
        ]);
    }

    public function show(string $slug): void
    {
        $post = NewsService::findBySlug($slug);
        if (!$post) {
            abort(404, 'Không tìm thấy bài viết');
        }

        $related = NewsService::latest(5);

        view('news/show', [
            'title'   => $post['title'],
            'post'    => $post,
            'related' => $related,
        ]);
    }
}
