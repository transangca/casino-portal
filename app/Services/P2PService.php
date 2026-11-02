<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Wallet;

/**
 * Sàn P2P — mô hình Binance P2P / OTC.
 *
 * Vòng đời giao dịch:
 *   created → paid → seller_delivered → buyer_confirmed → seller_confirmed → completed
 *
 * Tiền KHÔNG chuyển thẳng từ người mua sang người bán.
 * Tiền của người mua được giữ ở ví dự phòng (reserve_accounts) do quản trị quản lý.
 * Khi cả hai bên xác nhận "đã nhận tiền / đã hoàn tất" → hệ thống mở khoá
 * và chuyển tiền từ ví dự phòng sang ví người bán.
 *
 * Nếu báo cáo đã nhận tiền tự động (auto release) được bật, hệ thống tự phát hiện
 * giao dịch đã hoàn tất và tự mở khoá sau 1 khoảng thời gian chờ.
 */
class P2PService
{
    public const FEE_PERCENT_DEFAULT = 1.0;

    // ---------------- Danh mục ----------------

    public const CATEGORIES = [
        'account'  => 'Tài khoản',
        'game'     => 'Tài khoản game',
        'tool'     => 'Tool / Script',
        'source'   => 'Source code',
        'service'  => 'Dịch vụ',
        'resource' => 'Tài nguyên khác',
        'vip'      => 'VIP / Cấp bậc',
        'other'    => 'Khác',
    ];

    public static function categoryLabel(string $code): string
    {
        return self::CATEGORIES[$code] ?? $code;
    }

    // ---------------- Tài khoản dự phòng ----------------

    /**
     * Lấy (hoặc tạo) ví dự phòng dùng cho escrow P2P.
     */
    public static function reserveAccount(): array
    {
        $row = Database::fetch(
            'SELECT * FROM reserve_accounts WHERE type = "p2p_escrow" AND is_active = 1 ORDER BY id LIMIT 1'
        );
        if ($row) {
            return $row;
        }

        $id = Database::insert('reserve_accounts', [
            'owner_user_id' => null,
            'label'         => 'Ví dự phòng P2P (Quản trị quản lý)',
            'type'          => 'p2p_escrow',
            'balance'       => 0,
            'currency'      => 'VND',
            'is_active'     => 1,
            'note'          => 'Tiền P2P được giữ ở đây cho tới khi cả 2 bên xác nhận.',
        ]);

        return Database::fetch('SELECT * FROM reserve_accounts WHERE id = ?', [$id]);
    }

    private static function reserveBalance(int $reserveId): float
    {
        return (float)Database::fetchColumn('SELECT balance FROM reserve_accounts WHERE id = ?', [$reserveId]);
    }

    private static function reserveAdjust(int $reserveId, float $delta, string $note): void
    {
        Database::transaction(function () use ($reserveId, $delta, $note) {
            $row = Database::fetch('SELECT balance FROM reserve_accounts WHERE id = ? FOR UPDATE', [$reserveId]);
            if (!$row) {
                throw new \RuntimeException('Không tìm thấy ví dự phòng.');
            }
            $before = (float)$row['balance'];
            $after  = $before + $delta;
            if ($after < 0) {
                throw new \RuntimeException('Số dư ví dự phòng không đủ.');
            }
            Database::update('reserve_accounts', ['balance' => $after], 'id = :id', ['id' => $reserveId]);
            Database::insert('wallet_transactions', [
                'user_id'       => 0,
                'type'          => 'adjustment',
                'amount'        => $delta,
                'balance_after' => $after,
                'bonus_after'   => 0,
                'ref_type'      => 'p2p_reserve',
                'ref_id'        => (string)$reserveId,
                'description'   => $note,
            ]);
        });
    }

    // ---------------- Đăng tin bán ----------------

    public static function createOrder(int $sellerId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tiêu đề tin bán.'];
        }

        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            return ['ok' => false, 'message' => 'Giá phải lớn hơn 0.'];
        }

        $category = (string)($data['category'] ?? 'account');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'account';
        }

        $quantity = max(1, (int)($data['quantity'] ?? 1));

        // Mã đơn tự sinh, dễ tra cứu
        $orderNo = 'P' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));

        $id = Database::insert('p2p_orders', [
            'order_no'      => $orderNo,
            'seller_id'     => $sellerId,
            'title'         => mb_substr($title, 0, 200),
            'description'   => trim((string)($data['description'] ?? '')),
            'category'      => $category,
            'price'         => $price,
            'quantity'      => $quantity,
            'payment_method'=> trim((string)($data['payment_method'] ?? 'Chuyển khoản ngân hàng')),
            'min_amount'    => (float)($data['min_amount'] ?? 0),
            'max_amount'    => (float)($data['max_amount'] ?? 0),
            'terms'         => trim((string)($data['terms'] ?? '')),
            'status'        => 'open',
            'is_active'     => 1,
        ]);

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'p2p.order_create', $sellerId, $orderNo, [
            'price' => $price,
        ]);

        return ['ok' => true, 'message' => 'Đã đăng tin bán thành công.', 'id' => $id, 'order_no' => $orderNo];
    }

    // ---------------- Danh sách ----------------

    public static function listOrders(array $filters = [], int $limit = 40, int $offset = 0): array
    {
        $where  = ['po.is_active = 1', 'po.status IN ("open","reserved")'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[]  = 'po.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(po.title LIKE ? OR po.description LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['seller_id'])) {
            $where[]  = 'po.seller_id = ?';
            $params[] = (int)$filters['seller_id'];
        }
        if (!empty($filters['min_price'])) {
            $where[]  = 'po.price >= ?';
            $params[] = (float)$filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[]  = 'po.price <= ?';
            $params[] = (float)$filters['max_price'];
        }

        $sql = 'SELECT po.*, u.username AS seller_name, u.is_verified AS seller_verified,
                       (po.quantity - po.sold_count) AS remain
                FROM p2p_orders po JOIN users u ON u.id = po.seller_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY po.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        return Database::fetchAll($sql, $params);
    }

    public static function countOrders(array $filters = []): int
    {
        $where  = ['po.is_active = 1', 'po.status IN ("open","reserved")'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[]  = 'po.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(po.title LIKE ? OR po.description LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM p2p_orders po WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public static function findOrder(int $id): ?array
    {
        return Database::fetch(
            'SELECT po.*, u.username AS seller_name, u.is_verified AS seller_verified
             FROM p2p_orders po JOIN users u ON u.id = po.seller_id
             WHERE po.id = ? LIMIT 1',
            [$id]
        );
    }

    // ---------------- Đặt mua → tạo trade ----------------

    /**
     * Người mua đặt mua → tiền bị giữ ở ví dự phòng.
     */
    public static function createTrade(int $buyerId, int $orderId, float $amount): array
    {
        $order = self::findOrder($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Tin bán không tồn tại.'];
        }
        if ((int)$order['seller_id'] === $buyerId) {
            return ['ok' => false, 'message' => 'Bạn không thể mua tin của chính mình.'];
        }
        if ($order['status'] !== 'open') {
            return ['ok' => false, 'message' => 'Tin này không còn nhận đơn.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Số tiền giao dịch không hợp lệ.'];
        }

        $min = (float)($order['min_amount'] ?? 0);
        $max = (float)($order['max_amount'] ?? 0);
        if ($min > 0 && $amount < $min) {
            return ['ok' => false, 'message' => 'Số tiền tối thiểu là ' . money($min) . '.'];
        }
        if ($max > 0 && $amount > $max) {
            return ['ok' => false, 'message' => 'Số tiền tối đa là ' . money($max) . '.'];
        }

        $reserve = self::reserveAccount();
        $reserveId = (int)$reserve['id'];

        $feePercent = (float)setting('p2p_fee_percent', self::FEE_PERCENT_DEFAULT);
        $fee = round($amount * $feePercent / 100, 2);
        $total = $amount + $fee;

        $tradeNo = 'T' . date('ymd') . strtoupper(bin2hex(random_bytes(5)));

        try {
            Database::transaction(function () use ($buyerId, $orderId, $order, $amount, $fee, $total, $reserveId, $tradeNo) {
                // Khoá order để tránh 2 người mua cùng lúc
                $locked = Database::fetch('SELECT * FROM p2p_orders WHERE id = ? FOR UPDATE', [$orderId]);
                if (!$locked || $locked['status'] !== 'open') {
                    throw new \RuntimeException('Tin này vừa có người mua khác.');
                }

                // Trừ tiền người mua → ví dự phòng
                Wallet::apply($buyerId, 'adjustment', -$total, 'Đặt mua P2P: ' . $order['title'], 'p2p_trade', $tradeNo);

                $tradeId = Database::insert('p2p_trades', [
                    'trade_no'           => $tradeNo,
                    'order_id'           => $orderId,
                    'buyer_id'           => $buyerId,
                    'seller_id'          => (int)$order['seller_id'],
                    'amount'             => $amount,
                    'fee'                => $fee,
                    'reserve_account_id' => $reserveId,
                    'status'             => 'created',
                ]);

                self::reserveAdjust($reserveId, $total, 'Giữ tiền P2P #' . $tradeNo);

                Database::update('p2p_orders', ['status' => 'reserved'], 'id = :id', ['id' => $orderId]);

                self::logEvent($tradeId, $buyerId, 'created', null, 'created', [
                    'amount' => $amount,
                    'fee'    => $fee,
                ]);

                self::systemMessage($tradeId, 'Đơn #' . $tradeNo . ' đã được tạo. Số tiền ' . money($total)
                    . ' đang được giữ ở ví dự phòng của hệ thống. Người bán vui lòng chuyển khoản.');
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'p2p.trade_create', $buyerId, $tradeNo, [
            'amount' => $amount,
        ]);

        return ['ok' => true, 'message' => 'Đặt mua thành công. Tiền đang được giữ an toàn trong ví dự phòng.', 'trade_no' => $tradeNo];
    }

    // ---------------- Tiến trình giao dịch ----------------

    public static function findTrade(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, po.title, po.order_no, po.category, po.payment_method, po.terms, po.description,
                    bu.username AS buyer_name, bu.is_verified AS buyer_verified,
                    se.username AS seller_name, se.is_verified AS seller_verified
             FROM p2p_trades t
             JOIN p2p_orders po ON po.id = t.order_id
             JOIN users bu ON bu.id = t.buyer_id
             JOIN users se ON se.id = t.seller_id
             WHERE t.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findTradeByNo(string $no): ?array
    {
        $id = Database::fetchColumn('SELECT id FROM p2p_trades WHERE trade_no = ?', [$no]);
        return $id ? self::findTrade((int)$id) : null;
    }

    /**
     * Người mua báo "đã chuyển khoản".
     */
    public static function markPaid(int $tradeId, int $actorId): array
    {
        return self::transition($tradeId, $actorId, 'paid', ['paid', 'created']);
    }

    /**
     * Người bán báo "đã giao hàng / đã gửi tài nguyên".
     */
    public static function markDelivered(int $tradeId, int $actorId): array
    {
        return self::transition($tradeId, $actorId, 'seller_delivered', ['seller_delivered', 'paid']);
    }

    /**
     * Người mua xác nhận "đã nhận tiền / đã hoàn tất".
     */
    public static function markBuyerConfirmed(int $tradeId, int $actorId): array
    {
        $res = self::transition($tradeId, $actorId, 'buyer_confirmed', ['buyer_confirmed', 'seller_delivered']);
        if (!$res['ok']) {
            return $res;
        }

        // Người bán cũng đã xác nhận → hoàn tất ngay
        $trade = self::findTrade($tradeId);
        if ($trade && $trade['status'] === 'seller_confirmed') {
            return self::complete($tradeId);
        }
        return $res;
    }

    /**
     * Người bán xác nhận "đã nhận tiền" → mở khoá tiền cho người bán.
     */
    public static function markSellerConfirmed(int $tradeId, int $actorId): array
    {
        $res = self::transition($tradeId, $actorId, 'seller_confirmed', ['seller_confirmed', 'seller_delivered', 'buyer_confirmed']);
        if (!$res['ok']) {
            return $res;
        }

        $trade = self::findTrade($tradeId);
        if ($trade && $trade['status'] === 'buyer_confirmed') {
            return self::complete($tradeId);
        }
        return $res;
    }

    /**
     * Hoàn tất giao dịch — chuyển tiền từ ví dự phòng sang ví người bán.
     */
    public static function complete(int $tradeId): array
    {
        try {
            Database::transaction(function () use ($tradeId) {
                $t = Database::fetch('SELECT * FROM p2p_trades WHERE id = ? FOR UPDATE', [$tradeId]);
                if (!$t) {
                    throw new \RuntimeException('Không tìm thấy giao dịch.');
                }
                if (in_array($t['status'], ['completed', 'refunded', 'cancelled'], true)) {
                    throw new \RuntimeException('Giao dịch đã kết thúc.');
                }

                $reserveId = (int)$t['reserve_account_id'];
                $total     = (float)$t['amount'] + (float)$t['fee'];

                // Trừ ví dự phòng → cộng ví người bán
                self::reserveAdjust($reserveId, -$total, 'Giải ngân P2P #' . $t['trade_no']);

                Wallet::apply(
                    (int)$t['seller_id'],
                    'adjustment',
                    (float)$t['amount'],
                    'Bán hàng P2P #' . $t['trade_no'],
                    'p2p_trade',
                    (string)$tradeId
                );

                Database::update('p2p_trades', [
                    'status'       => 'completed',
                    'completed_at' => now(),
                ], 'id = :id', ['id' => $tradeId]);

                Database::query(
                    'UPDATE p2p_orders SET sold_count = sold_count + 1, status = IF(sold_count + 1 >= quantity, "open", "reserved") WHERE id = ?',
                    [(int)$t['order_id']]
                );

                self::logEvent($tradeId, null, 'completed', $t['status'], 'completed', [
                    'released' => $total,
                ]);

                self::systemMessage($tradeId, 'Giao dịch #' . $t['trade_no']
                    . ' đã hoàn tất. ' . money((float)$t['amount']) . ' đã được chuyển tới ví người bán.');
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'p2p.trade_complete', null, (string)$tradeId);
        return ['ok' => true, 'message' => 'Giao dịch hoàn tất. Tiền đã được chuyển tới người bán.'];
    }

    /**
     * Chuyển trạng thái giao dịch, kiểm tra quyền tác nhân.
     */
    private static function transition(int $tradeId, int $actorId, string $to, array $allowedFrom): array
    {
        $trade = self::findTrade($tradeId);
        if (!$trade) {
            return ['ok' => false, 'message' => 'Không tìm thấy giao dịch.'];
        }

        $isBuyer  = (int)$trade['buyer_id'] === $actorId;
        $isSeller = (int)$trade['seller_id'] === $actorId;
        if (!$isBuyer && !$isSeller) {
            return ['ok' => false, 'message' => 'Bạn không có quyền thao tác giao dịch này.'];
        }

        if (!in_array($trade['status'], $allowedFrom, true)) {
            return ['ok' => false, 'message' => 'Trạng thái hiện tại không cho phép thao tác này.'];
        }

        $data = ['status' => $to];
        if ($to === 'paid') {
            $data['buyer_paid_at'] = now();
        } elseif ($to === 'seller_delivered') {
            $data['seller_delivered_at'] = now();
        } elseif ($to === 'buyer_confirmed') {
            $data['buyer_confirmed_at'] = now();
        } elseif ($to === 'seller_confirmed') {
            $data['seller_confirmed_at'] = now();
        }

        Database::update('p2p_trades', $data, 'id = :id', ['id' => $tradeId]);
        self::logEvent($tradeId, $actorId, $to, $trade['status'], $to);

        return ['ok' => true, 'message' => self::statusLabel($to), 'status' => $to];
    }

    /**
     * Tự động mở khoá khi hệ thống phát hiện giao dịch đã "nhận tiền".
     *
     * Quy tắc:
     *   - Người mua đã báo chuyển khoản (paid)
     *   - Người bán đã giao hàng (seller_delivered)
     *   - Một trong hai bên đã báo "đã nhận tiền"
     *   - Quá thời gian chờ (mặc định 24h) mà vẫn chưa có khiếu nại → tự hoàn tất
     */
    public static function autoReleaseStale(?int $hours = null): int
    {
        if (setting('p2p_auto_release', '1') !== '1') {
            return 0;
        }

        $hours = $hours ?? (int)setting('p2p_auto_release_hours', 24);
        if ($hours <= 0) {
            return 0;
        }

        $rows = Database::fetchAll(
            'SELECT id FROM p2p_trades
             WHERE status IN ("paid","seller_delivered","buyer_confirmed","seller_confirmed")
               AND (buyer_paid_at IS NOT NULL AND seller_delivered_at IS NOT NULL)
               AND updated_at < (NOW() - INTERVAL ? HOUR)',
            [$hours]
        );

        $count = 0;
        foreach ($rows as $r) {
            $res = self::complete((int)$r['id']);
            if ($res['ok']) {
                Database::update('p2p_trades', ['auto_released' => 1], 'id = :id', ['id' => (int)$r['id']]);
                $count++;
            }
        }
        return $count;
    }

    // ---------------- Tranh chấp ----------------

    public static function openDispute(int $tradeId, int $actorId, string $reason): array
    {
        $trade = self::findTrade($tradeId);
        if (!$trade) {
            return ['ok' => false, 'message' => 'Không tìm thấy giao dịch.'];
        }
        if ((int)$trade['buyer_id'] !== $actorId && (int)$trade['seller_id'] !== $actorId) {
            return ['ok' => false, 'message' => 'Bạn không có quyền.'];
        }
        if (in_array($trade['status'], ['completed', 'cancelled', 'refunded'], true)) {
            return ['ok' => false, 'message' => 'Giao dịch đã kết thúc.'];
        }

        Database::insert('p2p_disputes', [
            'trade_id'  => $tradeId,
            'opened_by' => $actorId,
            'reason'    => mb_substr($reason, 0, 255),
            'status'    => 'open',
        ]);

        Database::update('p2p_trades', ['status' => 'disputed'], 'id = :id', ['id' => $tradeId]);
        self::logEvent($tradeId, $actorId, 'disputed', $trade['status'], 'disputed', ['reason' => $reason]);
        self::systemMessage($tradeId, 'Giao dịch đang trong trạng thái tranh chấp. Quản trị sẽ kiểm tra và xử lý.');

        return ['ok' => true, 'message' => 'Đã gửi yêu cầu tranh chấp. Quản trị sẽ xử lý.'];
    }

    // ---------------- Tin nhắn ----------------

    public static function message(int $tradeId, int $senderId, string $body): array
    {
        $trade = self::findTrade($tradeId);
        if (!$trade) {
            return ['ok' => false, 'message' => 'Không tìm thấy giao dịch.'];
        }
        if ((int)$trade['buyer_id'] !== $senderId && (int)$trade['seller_id'] !== $senderId) {
            return ['ok' => false, 'message' => 'Bạn không có quyền.'];
        }

        Database::insert('p2p_messages', [
            'trade_id'  => $tradeId,
            'sender_id' => $senderId,
            'body'      => mb_substr($body, 0, 4000),
        ]);

        return ['ok' => true, 'message' => 'Đã gửi.'];
    }

    public static function messages(int $tradeId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.username FROM p2p_messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.trade_id = ? ORDER BY m.id ASC LIMIT 300',
            [$tradeId]
        );
    }

    private static function systemMessage(int $tradeId, string $body): void
    {
        $adminId = (int)Database::fetchColumn('SELECT id FROM users WHERE role = "admin" ORDER BY id LIMIT 1');
        Database::insert('p2p_messages', [
            'trade_id'  => $tradeId,
            'sender_id' => $adminId ?: 0,
            'body'      => $body,
            'is_system' => 1,
        ]);
    }

    public static function events(int $tradeId): array
    {
        return Database::fetchAll(
            'SELECT e.*, u.username FROM p2p_events e
             LEFT JOIN users u ON u.id = e.actor_id
             WHERE e.trade_id = ? ORDER BY e.id ASC LIMIT 200',
            [$tradeId]
        );
    }

    private static function logEvent(int $tradeId, ?int $actorId, string $event, ?string $from, ?string $to, array $detail = []): void
    {
        Database::insert('p2p_events', [
            'trade_id'    => $tradeId,
            'actor_id'    => $actorId,
            'event'       => $event,
            'from_status' => $from,
            'to_status'   => $to,
            'detail'      => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    // ---------------- Danh sách của tôi ----------------

    public static function myOrders(int $userId): array
    {
        return Database::fetchAll(
            'SELECT po.*, (po.quantity - po.sold_count) AS remain,
                    (SELECT COUNT(*) FROM p2p_trades t WHERE t.order_id = po.id AND t.buyer_id = po.seller_id) AS x
             FROM p2p_orders po WHERE po.seller_id = ? ORDER BY po.id DESC LIMIT 100',
            [$userId]
        );
    }

    public static function myTrades(int $userId): array
    {
        return Database::fetchAll(
            'SELECT t.*, po.title, po.order_no, bu.username AS buyer_name, se.username AS seller_name
             FROM p2p_trades t
             JOIN p2p_orders po ON po.id = t.order_id
             JOIN users bu ON bu.id = t.buyer_id
             JOIN users se ON se.id = t.seller_id
             WHERE t.buyer_id = ? OR t.seller_id = ?
             ORDER BY t.id DESC LIMIT 100',
            [$userId, $userId]
        );
    }

    // ---------------- Nhãn trạng thái ----------------

    public const STATUS_LABELS = [
        'open'            => 'Đang mở',
        'reserved'        => 'Đã có người mua',
        'paid'            => 'Người mua đã chuyển khoản',
        'seller_delivered'=> 'Người bán đã giao',
        'buyer_confirmed' => 'Người mua đã xác nhận',
        'seller_confirmed'=> 'Người bán đã xác nhận',
        'completed'       => 'Hoàn tất',
        'disputed'        => 'Tranh chấp',
        'cancelled'       => 'Đã huỷ',
        'refunded'        => 'Đã hoàn tiền',
        'created'         => 'Vừa tạo',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }
}
