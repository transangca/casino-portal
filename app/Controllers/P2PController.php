<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\P2PService;

/**
 * Sàn P2P — mua bán trao đổi trực tiếp giữa các thành viên.
 *
 * Route:
 *   /p2p                     → sàn giao dịch
 *   /p2p/sell                → đăng tin bán
 *   /p2p/my                  → tin của tôi + giao dịch
 *   /p2p/order/{id}          → chi tiết tin
 *   /p2p/trade/{id}          → phòng giao dịch (nhắn tin + xác nhận)
 */
class P2PController
{
    public function index(): void
    {
        $category = (string)($_GET['category'] ?? '');
        $q        = trim((string)($_GET['q'] ?? ''));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = 20;
        $offset   = ($page - 1) * $limit;

        $filters = ['category' => $category, 'q' => $q];

        $orders = P2PService::listOrders($filters, $limit, $offset);
        $total  = P2PService::countOrders($filters);
        $pages  = (int)ceil($total / $limit);

        $reserve = P2PService::reserveAccount();

        view('p2p/index', [
            'title'   => 'Sàn P2P',
            'orders'  => $orders,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'q'       => $q,
            'category'=> $category,
            'reserve' => $reserve,
            'categories' => P2PService::CATEGORIES,
        ]);
    }

    public function sellForm(): void
    {
        view('p2p/sell', [
            'title'      => 'Đăng tin bán',
            'categories' => P2PService::CATEGORIES,
        ]);
    }

    public function create(): void
    {
        CSRF::verifyOrFail();

        // Quản trị viên cũng được đăng tin bán trên sàn P2P
        $sellerId = (int)Auth::id();

        $res = P2PService::createOrder($sellerId, [
            'title'          => $_POST['title'] ?? '',
            'description'    => $_POST['description'] ?? '',
            'category'       => $_POST['category'] ?? 'account',
            'price'          => $_POST['price'] ?? 0,
            'quantity'       => $_POST['quantity'] ?? 1,
            'payment_method' => $_POST['payment_method'] ?? '',
            'min_amount'     => $_POST['min_amount'] ?? 0,
            'max_amount'     => $_POST['max_amount'] ?? 0,
            'terms'          => $_POST['terms'] ?? '',
        ]);

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect($res['ok'] ? url('/p2p/my') : url('/p2p/sell'));
    }

    public function my(): void
    {
        $userId = (int)Auth::id();
        view('p2p/my', [
            'title'  => 'P2P của tôi',
            'orders' => P2PService::myOrders($userId),
            'trades' => P2PService::myTrades($userId),
        ]);
    }

    public function show(int $id): void
    {
        $order = P2PService::findOrder($id);
        if (!$order) {
            abort(404, 'Không tìm thấy tin bán');
        }

        view('p2p/order', [
            'title'  => $order['title'],
            'order'  => $order,
            'categories' => P2PService::CATEGORIES,
        ]);
    }

    /**
     * Đặt mua → tạo trade, tiền giữ ở ví dự phòng.
     */
    public function buy(): void
    {
        CSRF::verifyOrFail();

        $orderId = (int)($_POST['order_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0);

        $res = P2PService::createTrade((int)Auth::id(), $orderId, $amount);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect($res['ok'] && !empty($res['trade_no'])
            ? url('/p2p/trade/' . $res['trade_no'])
            : url('/p2p/order/' . $orderId));
    }

    /**
     * Phòng giao dịch.
     */
    public function trade(string $no): void
    {
        $trade = P2PService::findTradeByNo($no);
        if (!$trade) {
            abort(404, 'Không tìm thấy giao dịch');
        }

        $userId = (int)Auth::id();
        if ((int)$trade['buyer_id'] !== $userId && (int)$trade['seller_id'] !== $userId && !Auth::isAdmin()) {
            abort(403, 'Bạn không có quyền xem giao dịch này');
        }

        view('p2p/trade', [
            'title'   => 'Giao dịch ' . $trade['trade_no'],
            'trade'   => $trade,
            'messages'=> P2PService::messages((int)$trade['id']),
            'events'  => P2PService::events((int)$trade['id']),
            'isBuyer' => (int)$trade['buyer_id'] === $userId,
        ]);
    }

    /**
     * Thao tác trạng thái giao dịch.
     */
    public function action(): void
    {
        CSRF::verifyOrFail();

        $tradeId = (int)($_POST['trade_id'] ?? 0);
        $action  = (string)($_POST['action'] ?? '');
        $userId  = (int)Auth::id();

        $res = match ($action) {
            'paid'     => P2PService::markPaid($tradeId, $userId),
            'delivered'=> P2PService::markDelivered($tradeId, $userId),
            'buyer_confirm'  => P2PService::markBuyerConfirmed($tradeId, $userId),
            'seller_confirm' => P2PService::markSellerConfirmed($tradeId, $userId),
            default    => ['ok' => false, 'message' => 'Hành động không hợp lệ.'],
        };

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect($_SERVER['HTTP_REFERER'] ?? url('/p2p/my'));
    }

    /**
     * Gửi tin nhắn trong phòng giao dịch.
     */
    public function message(): void
    {
        CSRF::verifyOrFail();

        $tradeId = (int)($_POST['trade_id'] ?? 0);
        $body    = trim((string)($_POST['body'] ?? ''));

        $res = P2PService::message($tradeId, (int)Auth::id(), $body);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/p2p/trade/' . (P2PService::findTrade($tradeId)['trade_no'] ?? '')));
    }

    /**
     * Mở tranh chấp.
     */
    public function dispute(): void
    {
        CSRF::verifyOrFail();

        $tradeId = (int)($_POST['trade_id'] ?? 0);
        $reason  = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            flash('error', 'Vui lòng nêu lý do tranh chấp.');
            redirect($_SERVER['HTTP_REFERER'] ?? url('/p2p/my'));
        }

        $res = P2PService::openDispute($tradeId, (int)Auth::id(), $reason);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect($_SERVER['HTTP_REFERER'] ?? url('/p2p/my'));
    }
}
