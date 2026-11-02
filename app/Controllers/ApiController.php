<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Wallet;
use App\Services\ApiDocService;
use App\Services\ApiKeyService;
use App\Services\GameSessionService;
use App\Services\PackageService;
use App\Services\PhoneCheckService;
use App\Services\ServicePriceService;
use App\Services\VuaviaService;

/**
 * Public API cho đối tác / dev đấu game vào website.
 *
 * Xác thực: header `X-API-Key: pk_live_xxx_yyy`
 * Response chuẩn:
 *   { "success": true, "data": {...}, "meta": {...} }
 *   { "success": false, "error": { "code": "...", "message": "..." } }
 */
class ApiController
{
    /** @var array|null Client đã xác thực */
    private ?array $client = null;

    private float $startedAt = 0.0;
    private string $endpoint = '';

    // ------------------------------------------------------------------
    // Router gọi vào đây
    // ------------------------------------------------------------------

    /**
     * Điểm vào duy nhất — /api/{action}
     */
    public function handle(string $action): void
    {
        $this->startedAt = microtime(true);
        $this->endpoint = '/api/' . $action;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Endpoint công khai: không cần API key
        if ($action === 'docs') {
            $this->docsJson();
            return;
        }
        if ($action === 'spec') {
            $this->publicSpec();
            return;
        }

        // Xác thực API key
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? null);
        $auth = ApiKeyService::authenticate(is_string($apiKey) ? $apiKey : null);

        if (!$auth['success']) {
            $this->fail($auth['message'], 'AUTH_INVALID', 401);
            return;
        }

        $this->client = $auth['client'];

        try {
            $this->route($action, $method);
        } catch (\Throwable $e) {
            logger('API error: ' . $e->getMessage(), 'error');
            $this->fail('Lỗi hệ thống: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    private function route(string $action, string $method): void
    {
        match ($action) {
            'ping'              => $this->ping(),
            'games'             => $this->listGames(),
            'game'              => $this->gameDetail(),
            'launch'            => $this->launch(),
            'wallet'            => $this->wallet(),
            'wallet/change'     => $this->walletChange(),
            'packages'          => $this->packages(),
            'history'           => $this->history(),
            'user'              => $this->userInfo(),
            'phone/check'       => $this->phoneCheck(),
            'phone/batch'       => $this->phoneBatch(),
            'phone/history'     => $this->phoneHistory(),
            'accounts/products' => $this->accountProducts(),
            'accounts/order'    => $this->accountOrder(),
            'accounts/orders'   => $this->accountOrders(),
            'accounts/detail'   => $this->accountDetail(),
            default             => $this->fail('Endpoint không tồn tại: /api/' . $action, 'NOT_FOUND', 404),
        };
    }

    // ------------------------------------------------------------------
    // Endpoints
    // ------------------------------------------------------------------

    private function ping(): void
    {
        $this->ok([
            'pong'     => true,
            'server'   => 'PGSoft Casino Gateway',
            'version'  => '1.0.0',
            'time'     => date('c'),
            'client'   => $this->client['name'],
            'expires'  => $this->client['expires_at'],
            'quota'    => [
                'limit' => (int)$this->client['quota_limit'],
                'used'  => ApiKeyService::requestsToday((int)$this->client['id']),
            ],
        ]);
    }

    /**
     * Danh sách game.
     * GET /api/games?provider=pgsoft&type=slot&search=mahjong&limit=50&page=1
     */
    private function listGames(): void
    {
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $where  = ["g.status = 'active'"];
        $params = [];

        if (!empty($_GET['provider'])) {
            $where[] = 'p.code = :provider';
            $params['provider'] = (string)$_GET['provider'];
        }
        if (!empty($_GET['type'])) {
            $where[] = 'g.game_type = :type';
            $params['type'] = (string)$_GET['type'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(g.name LIKE :search OR g.slug LIKE :search)';
            $params['search'] = '%' . (string)$_GET['search'] . '%';
        }
        if (isset($_GET['featured'])) {
            $where[] = 'g.is_featured = 1';
        }
        if (isset($_GET['hot'])) {
            $where[] = 'g.is_hot = 1';
        }
        if (isset($_GET['new'])) {
            $where[] = 'g.is_new = 1';
        }

        $sqlWhere = implode(' AND ', $where);

        $total = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM games g JOIN providers p ON p.id = g.provider_id WHERE $sqlWhere",
            $params
        );

        $rows = Database::fetchAll(
            "SELECT g.id, g.slug, g.name, g.game_type, g.icon, g.rtp, g.min_bet, g.max_bet,
                    g.is_featured, g.is_hot, g.is_new, g.is_free, p.code AS provider, p.name AS provider_name
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE $sqlWhere
             ORDER BY g.sort_order DESC, g.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $this->ok([
            'games' => array_map([$this, 'formatGame'], $rows),
        ], [
            'total'    => $total,
            'page'     => $page,
            'limit'    => $limit,
            'pages'    => (int)ceil($total / $limit),
            'has_more' => $offset + count($rows) < $total,
        ]);
    }

    /**
     * Chi tiết 1 game.
     * GET /api/game?slug=mahjong-ways
     */
    private function gameDetail(): void
    {
        $slug = (string)($_GET['slug'] ?? '');
        if ($slug === '') {
            $this->fail('Thiếu tham số slug', 'INVALID_PARAM', 422);
            return;
        }

        $game = Database::fetch(
            'SELECT g.*, p.code AS provider, p.name AS provider_name
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.slug = ? AND g.status = "active"',
            [$slug]
        );

        if (!$game) {
            $this->fail('Không tìm thấy game', 'GAME_NOT_FOUND', 404);
            return;
        }

        $this->ok(['game' => $this->formatGame($game)]);
    }

    /**
     * Khởi tạo phiên chơi → trả URL launch + token operator.
     * POST /api/launch { slug }
     */
    private function launch(): void
    {
        $slug = (string)($this->input('slug') ?? '');
        if ($slug === '') {
            $this->fail('Thiếu tham số slug', 'INVALID_PARAM', 422);
            return;
        }

        $game = Database::fetch(
            'SELECT g.*, p.code AS provider_code, p.name AS provider_name
             FROM games g JOIN providers p ON p.id = g.provider_id
             WHERE g.slug = ? AND g.status = "active"',
            [$slug]
        );

        if (!$game) {
            $this->fail('Không tìm thấy game', 'GAME_NOT_FOUND', 404);
            return;
        }

        // Client API đại diện cho user cụ thể (nếu không thì dùng user sở hữu key)
        $userId = (int)$this->client['user_id'];

        $check = PackageService::canPlay($userId, (string)$game['provider_code'], (bool)$game['is_free']);
        if (!$check['allowed']) {
            $this->fail('Tài khoản chưa có gói chơi hợp lệ. Vui lòng mua gói trước.', 'NO_PACKAGE', 403);
            return;
        }

        try {
            $session = GameSessionService::start($userId, (int)$game['id']);
        } catch (\Throwable $e) {
            $this->fail('Không khởi tạo được phiên: ' . $e->getMessage(), 'SESSION_FAILED', 500);
            return;
        }

        $this->ok([
            'session_id'      => (string)$session['session_id'],
            'session_token'   => $session['session_token'],
            'operator_token'  => $session['operator_token'],
            'launch_url'      => $session['launch_url'],
            'balance'         => $session['balance'],
            'expires_in'      => PackageService::remainingSeconds($userId),
        ]);
    }

    /**
     * Số dư hiện tại.
     * GET /api/wallet
     */
    private function wallet(): void
    {
        $userId = (int)$this->client['user_id'];
        $this->ok([
            'balance'  => Wallet::balance($userId),
            'currency' => 'VND',
            'user_id'  => (string)$userId,
        ]);
    }

    /**
     * Trừ/cộng số dư (dùng cho game h5 tự host).
     * POST /api/wallet/change { type: bet|win|refund, amount, roundId }
     */
    private function walletChange(): void
    {
        $operatorToken = (string)($this->input('operator_token') ?? '');
        if ($operatorToken === '') {
            $this->fail('Thiếu operator_token', 'INVALID_PARAM', 422);
            return;
        }

        $session = GameSessionService::findByOperatorToken($operatorToken);
        if (!$session || (int)$session['user_id'] !== (int)$this->client['user_id']) {
            $this->fail('Phiên chơi không hợp lệ', 'SESSION_INVALID', 401);
            return;
        }

        $type   = (string)($this->input('type') ?? 'adjustment');
        $amount = (float)($this->input('amount') ?? 0);

        $result = GameSessionService::applyWalletChange($operatorToken, $type, $amount, $this->body());

        if (!$result['success']) {
            $this->fail($result['message'] ?? 'Cập nhật số dư thất bại', 'WALLET_ERROR', 400);
            return;
        }

        $this->ok(['balance' => $result['balance']]);
    }

    /**
     * Danh sách gói chơi.
     */
    private function packages(): void
    {
        $rows = Database::fetchAll(
            'SELECT id, name, slug, description, duration_minutes, price, original_price,
                    provider_code, is_free_trial FROM packages WHERE is_active = 1 ORDER BY sort_order'
        );
        $this->ok(['packages' => $rows]);
    }

    /**
     * Lịch sử cược.
     * GET /api/history?limit=50
     */
    private function history(): void
    {
        $userId = (int)$this->client['user_id'];
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $rounds = Database::fetchAll(
            "SELECT gr.round_id, gr.bet, gr.win, gr.balance_after, gr.created_at, g.name AS game, g.slug
             FROM game_rounds gr
             JOIN game_sessions gs ON gs.id = gr.session_id
             JOIN games g ON g.id = gs.game_id
             WHERE gs.user_id = ? ORDER BY gr.id DESC LIMIT $limit",
            [$userId]
        );

        $tx = Database::fetchAll(
            'SELECT id, type, amount, balance_after, description, created_at
             FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );

        $this->ok(['rounds' => $rounds, 'transactions' => $tx]);
    }

    /**
     * Thông tin tài khoản.
     */
    private function userInfo(): void
    {
        $userId = (int)$this->client['user_id'];
        $user = Database::fetch(
            'SELECT id, username, email, full_name, role, balance, vip_level, created_at FROM users WHERE id = ?',
            [$userId]
        );
        if (!$user) {
            $this->fail('Không tìm thấy người dùng', 'USER_NOT_FOUND', 404);
            return;
        }
        $user['balance'] = Wallet::balance($userId);
        unset($user['password_hash']);
        $this->ok(['user' => $user]);
    }

    /**
     * Danh sách sản phẩm tài khoản & nick.
     * GET /api/accounts/products?q=&categoryId=&page=&size=
     */
    private function accountProducts(): void
    {
        $products = VuaviaService::products([
            'q'           => trim((string)($_GET['q'] ?? '')),
            'category_id' => (int)($_GET['categoryId'] ?? 0),
            'page'        => max(0, (int)($_GET['page'] ?? 0)),
            'size'        => max(1, min(100, (int)($_GET['size'] ?? 50))),
            'sort'        => (string)($_GET['sort'] ?? 'newest'),
        ]);

        if (!$products) {
            $products = VuaviaService::localProducts();
        }

        $markup = VuaviaService::markup();

        $out = [];
        foreach ($products as $p) {
            $out[] = [
                'product_id'    => $p['product_id'],
                'name'          => $p['name'],
                'price'         => $p['price'],
                'sell_price'    => $markup > 0 ? (int)ceil($p['price'] * (1 + $markup / 100)) : (int)$p['price'],
                'stock_count'   => $p['stock_count'],
                'delivery_type' => $p['delivery_type'],
                'category_id'   => $p['category_id'],
                'category_name' => $p['category_name'],
            ];
        }

        $this->ok(['products' => $out, 'count' => count($out)]);
    }

    /**
     * Mua tài khoản & nick.
     * POST /api/accounts/order { productId, quantity }
     */
    private function accountOrder(): void
    {
        $userId = (int)$this->client['user_id'];

        $productId = trim((string)($this->input('productId') ?? $this->input('product_id') ?? ''));
        $quantity  = max(1, (int)($this->input('quantity') ?? 1));

        if ($productId === '') {
            $this->fail('Thiếu tham số productId', 'INVALID_PARAM', 422);
            return;
        }

        $product = VuaviaService::findProduct($productId);
        if (!$product) {
            $this->fail('Không tìm thấy sản phẩm', 'PRODUCT_NOT_FOUND', 404);
            return;
        }

        if ((int)$product['stock_count'] <= 0) {
            $this->fail('Sản phẩm tạm hết hàng', 'OUT_OF_STOCK', 409);
            return;
        }

        $markup    = VuaviaService::markup();
        $unitCost  = (float)$product['price'];
        $sellPrice = $markup > 0 ? (int)ceil($unitCost * (1 + $markup / 100)) : (int)$unitCost;
        $total     = $sellPrice * $quantity;

        if (Wallet::balance($userId) < $total) {
            $this->fail('Số dư không đủ. Cần ' . money($total) . ' để mua.', 'INSUFFICIENT_BALANCE', 402);
            return;
        }

        try {
            Wallet::apply(
                $userId,
                'adjustment',
                -$total,
                'API mua tài khoản ' . $product['name'] . ' ×' . $quantity,
                'vuavia_order_api',
                $productId
            );
        } catch (\Throwable $e) {
            $this->fail('Lỗi trừ phí: ' . $e->getMessage(), 'WALLET_ERROR', 400);
            return;
        }

        $res = VuaviaService::createOrder($userId, $productId, $quantity);

        if (!$res['ok']) {
            Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền mua tài khoản thất bại',
                'vuavia_refund',
                $productId
            );

            $this->fail($res['message'], $res['errorCode'], 400);
            return;
        }

        $orderId = VuaviaService::saveOrder($userId, [
            'product_id'       => (int)$productId,
            'product_name'     => (string)$product['name'],
            'quantity'         => $quantity,
            'total_amount'     => $total,
            'unit_price'       => $sellPrice,
            'status'           => (string)$res['status'],
            'partner_order_id' => (int)($res['partner_order_id'] ?? 0),
        ]);

        VuaviaService::saveDeliveries($orderId, (array)$res['deliveredItems']);

        $this->ok([
            'id'              => $orderId,
            'status'          => $res['status'],
            'productName'     => $product['name'],
            'totalAmount'     => $total,
            'quantity'        => $quantity,
            'deliveredItems'  => $res['deliveredItems'],
            'balance'         => Wallet::balance($userId),
        ]);
    }

    /**
     * Lịch sử đơn tài khoản & nick.
     * GET /api/accounts/orders?limit=20
     */
    private function accountOrders(): void
    {
        $userId = (int)$this->client['user_id'];
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 20)));

        $rows = Database::fetchAll(
            'SELECT id, product_name, product_id, quantity, total_amount, status, created_at
             FROM vuavia_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );

        $this->ok(['orders' => $rows, 'count' => count($rows)]);
    }

    /**
     * Chi tiết đơn tài khoản & nick.
     * GET /api/accounts/detail?id=123
     */
    private function accountDetail(): void
    {
        $userId = (int)$this->client['user_id'];
        $id     = (int)($_GET['id'] ?? 0);

        $order = VuaviaService::findOrder($userId, $id);
        if (!$order) {
            $this->fail('Không tìm thấy đơn hàng', 'NOT_FOUND', 404);
            return;
        }

        $items = [];
        foreach ((array)($order['deliveries'] ?? []) as $d) {
            $items[] = [
                'account'  => (string)($d['account'] ?? ''),
                'password' => VuaviaService::revealPassword($d['password_enc']),
            ];
        }

        $this->ok([
            'id'             => (int)$order['id'],
            'productName'    => (string)($order['product_name'] ?? ''),
            'status'         => (string)$order['status'],
            'totalAmount'    => (float)$order['total_amount'],
            'quantity'       => (int)$order['quantity'],
            'deliveredItems' => $items,
            'deliveryNote'   => $order['delivery_note'] ?? null,
            'createdAt'      => (string)$order['created_at'],
        ]);
    }

    /**
     * Tài liệu API (machine-readable).
     */
    private function docsJson(): void
    {
        $this->ok(['docs' => ApiDocService::spec()]);
    }    /**
     * Đặc tả OpenAPI công khai (không cần key).
     */
    private function publicSpec(): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(ApiDocService::spec(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // ------------------------------------------------------------------
    // Dịch vụ check số điện thoại (V16)
    // ------------------------------------------------------------------

    /**
     * Tra cứu 1 số điện thoại.
     * GET /api/phone/check?phone=0912345678
     */
    private function phoneCheck(): void
    {
        $userId = (int)$this->client['user_id'];
        $phone  = trim((string)($_GET['phone'] ?? $this->input('phone') ?? ''));

        if ($phone === '') {
            $this->fail('Thiếu tham số phone', 'INVALID_PARAM', 422);
            return;
        }

        $price = ServicePriceService::price('phone-check', 3000.0);
        if (Wallet::balance($userId) < $price) {
            $this->fail('Số dư không đủ. Cần ' . money($price) . ' để tra cứu 1 số.', 'INSUFFICIENT_BALANCE', 402);
            return;
        }

        $res = PhoneCheckService::lookup($phone);
        if (!$res['ok']) {
            $this->fail($res['message'], 'INVALID_PARAM', 422);
            return;
        }

        $data = $res['data'];

        try {
            $after = Wallet::apply(
                $userId,
                'adjustment',
                -$price,
                'API tra cứu số điện thoại ' . $data['phone'],
                'phone_check_api',
                $data['phone'],
                ['carrier' => $data['carrier']]
            );
        } catch (\Throwable $e) {
            $this->fail('Lỗi trừ phí: ' . $e->getMessage(), 'WALLET_ERROR', 400);
            return;
        }

        PhoneCheckService::log($userId, $data['phone'], $data, $price, $after);

        $this->ok([
            'phone'     => $data['phone'],
            'carrier'   => $data['carrier'],
            'full_name' => $data['full_name'],
            'id_card'   => $data['id_card'],
            'address'   => $data['address'],
            'fb_url'    => $data['fb_url'],
            'google_url'=> $data['google_url'],
            'status'    => $data['status'],
            'cost'      => $price,
            'balance'   => Wallet::balance($userId),
        ]);
    }

    /**
     * Tra cứu hàng loạt (tối đa 100 số).
     * POST /api/phone/batch { phones: ["0912...", "0987..."] }
     */
    private function phoneBatch(): void
    {
        $userId = (int)$this->client['user_id'];

        $raw = $this->input('phones');
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            $this->fail('Thiếu tham số phones (mảng số điện thoại)', 'INVALID_PARAM', 422);
            return;
        }

        $phones = [];
        foreach ($raw as $chunk) {
            $chunk = trim((string)$chunk);
            if ($chunk === '') {
                continue;
            }
            $p = PhoneCheckService::normalize($chunk);
            if ($p !== '') {
                $phones[] = $p;
            }
        }
        $phones = array_slice(array_values(array_unique($phones)), 0, 100);

        if (!$phones) {
            $this->fail('Không có số điện thoại hợp lệ', 'INVALID_PARAM', 422);
            return;
        }

        $price = ServicePriceService::price('phone-check-batch', 2500.0);
        $total = $price * count($phones);

        if (Wallet::balance($userId) < $total) {
            $this->fail('Số dư không đủ. Cần ' . money($total) . ' cho ' . count($phones) . ' số.', 'INSUFFICIENT_BALANCE', 402);
            return;
        }

        $rows = [];
        foreach ($phones as $p) {
            $res = PhoneCheckService::lookup($p);
            if (!$res['ok']) {
                $rows[] = ['phone' => $p, 'status' => 'error', 'message' => $res['message']];
                continue;
            }
            $d = $res['data'];
            $rows[] = [
                'phone'     => $d['phone'],
                'status'    => $d['status'],
                'carrier'   => $d['carrier'],
                'full_name' => $d['full_name'],
                'id_card'   => $d['id_card'],
                'address'   => $d['address'],
                'fb_url'    => $d['fb_url'],
            ];
        }

        try {
            $after = Wallet::apply(
                $userId,
                'adjustment',
                -$total,
                'API tra cứu hàng loạt ' . count($phones) . ' số điện thoại',
                'phone_check_batch_api',
                null,
                ['count' => count($phones)]
            );
        } catch (\Throwable $e) {
            $this->fail('Lỗi trừ phí: ' . $e->getMessage(), 'WALLET_ERROR', 400);
            return;
        }

        foreach ($rows as $r) {
            if ($r['status'] === 'error') {
                continue;
            }
            PhoneCheckService::log($userId, $r['phone'], [
                'phone'     => $r['phone'],
                'carrier'   => $r['carrier'],
                'full_name' => $r['full_name'],
                'id_card'   => $r['id_card'],
                'address'   => $r['address'],
                'fb_url'    => $r['fb_url'],
                'status'    => $r['status'],
                'found'     => $r['status'] === 'found',
            ], $price, $after);
        }

        $this->ok([
            'results' => $rows,
            'count'   => count($rows),
            'cost'    => $total,
            'balance' => Wallet::balance($userId),
        ]);
    }

    /**
     * Lịch sử tra cứu của client.
     * GET /api/phone/history?limit=50
     */
    private function phoneHistory(): void
    {
        $userId = (int)$this->client['user_id'];
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $rows = Database::fetchAll(
            'SELECT phone, carrier, full_name, id_card, address, fb_url, status, cost, created_at
             FROM phone_checks WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );

        $this->ok(['history' => $rows, 'count' => count($rows)]);
    }

    /**
     * Tài liệu API (trang HTML, xem được không cần đăng nhập).
     */
    public function docsPage(): void
    {
        $spec = ApiDocService::spec();
        view('api/docs', compact('spec'));
    }

    /**
     * Đặc tả OpenAPI công khai (JSON, không cần key).
     */
    public function specPage(): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(ApiDocService::spec(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function formatGame(array $g): array
    {
        return [
            'id'         => (string)$g['id'],
            'slug'       => $g['slug'],
            'name'       => $g['name'],
            'type'       => $g['game_type'],
            'icon'       => media_url((string)($g['icon'] ?? '')),
            'rtp'        => $g['rtp'] !== null ? (float)$g['rtp'] : null,
            'min_bet'    => (float)$g['min_bet'],
            'max_bet'    => (float)$g['max_bet'],
            'is_free'    => (bool)$g['is_free'],
            'is_featured'=> (bool)$g['is_featured'],
            'is_hot'     => (bool)$g['is_hot'],
            'is_new'     => (bool)$g['is_new'],
            'provider'   => $g['provider'] ?? null,
            'launch'     => '/api/launch',   // hướng dẫn dùng
            'launch_url' => url('/play/' . $g['slug']),
        ];
    }

    private function body(): array
    {
        $raw = (string)file_get_contents('php://input');
        if ($raw === '') {
            return $_POST ?: [];
        }
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        parse_str($raw, $form);
        return $form ?: [];
    }

    private function input(string $key): mixed
    {
        $body = $this->body();
        return $body[$key] ?? null;
    }

    private function ok(array $data, array $meta = []): never
    {
        $duration = (int)round((microtime(true) - $this->startedAt) * 1000);

        if ($this->client) {
            ApiKeyService::track((int)$this->client['id'], $this->endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', 200, $duration);
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-RateLimit-Limit: ' . (int)($this->client['rate_limit'] ?? 60));
        header('X-Response-Time: ' . $duration . 'ms');

        echo json_encode([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'timestamp' => time(),
                'duration'  => $duration,
            ], $meta),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function fail(string $message, string $code = 'ERROR', int $status = 400): never
    {
        $duration = (int)round((microtime(true) - $this->startedAt) * 1000);

        if ($this->client) {
            ApiKeyService::track((int)$this->client['id'], $this->endpoint, $_SERVER['REQUEST_METHOD'] ?? 'GET', $status, $duration);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
            'meta'    => [
                'timestamp' => time(),
                'duration'  => $duration,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
