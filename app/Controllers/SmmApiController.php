<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Wallet;
use App\Services\SmmService;

/**
 * Proxy API SMM — đối tác dùng apikey riêng của user để đấu vào hệ thống.
 *
 * Base: /api/external/smm
 *
 * Xác thực (theo thứ tự ưu tiên):
 *   header  x-api-key
 *   header  Authorization: Bearer {apikey}
 *   query   ?apikey= / ?api_key= / ?key=
 *   body    apikey / api_key / key
 *
 * Endpoint (giống hệt tài liệu SMM):
 *   GET  /services?platform=&category=&search=&page=&per_page=
 *   GET  /categories?platform=&search=
 *   GET  /category/{slug}
 *   POST /quote
 *   POST /order  (alias /add)
 *   GET  /status?order=        |  /status?orders=1024,1025  (tối đa 100 mã)
 *   GET  /orders?service_ids=&limit=
 *   GET  /balance
 *
 * Nguyên tắc nghiệp vụ:
 *  - Giá trả về là GIÁ BÁN đang hiển thị trên web (đã gồm chi phí + biên lợi nhuận),
 *    lấy từ cache dịch vụ SMM trong DB.
 *  - Tạo đơn → trừ ví chính users.balance của user gắn với apikey, KHÔNG cộng VAT.
 *  - User thường chỉ xem/kiểm tra được đơn SMM của chính mình; admin được kiểm tra nhiều đơn.
 *  - Dịch vụ comment tự tính quantity theo số dòng comment hợp lệ.
 */
class SmmApiController
{
    /** Apikey của request hiện tại (dùng để áp giảm giá đại lý) */
    private string $currentKey = '';

    public function handle(string $path = ''): void
    {
        $path   = trim($path, '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $apikey = $this->extractApiKey();
        if ($apikey === '') {
            $this->fail('Thiếu API key. Gửi header x-api-key hoặc Authorization: Bearer.', 401);
            return;
        }

        // Tra cứu user gắn với apikey
        $user = Database::fetch(
            'SELECT u.id, u.username, u.balance, u.status, u.role
             FROM ttmmo_api_keys k
             JOIN users u ON u.id = k.user_id
             WHERE k.apikey = ? AND k.is_active = 1
             LIMIT 1',
            [$apikey]
        );
        if (!$user) {
            $this->fail('apikey không hợp lệ', 401);
            return;
        }
        if ((string)$user['status'] !== 'active') {
            $this->fail('Tài khoản đang bị khoá. Vui lòng liên hệ admin.', 403);
            return;
        }

        // Admin được kiểm tra nhiều đơn để vận hành
        $isAdmin = in_array((string)$user['role'], ['admin', 'operator'], true);
        $this->currentKey = $apikey;

        // Endpoint tổng dùng ?action=
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));
        if ($path === '') {
            $path = $action;
        }

        // Chuẩn hoá alias
        $path = match ($path) {
            'add'          => 'order',
            'services.php' => 'services',
            'categories.php' => 'categories',
            'quote.php'    => 'quote',
            'order.php'    => 'order',
            'status.php'   => 'status',
            'orders.php'   => 'orders',
            'balance.php'  => 'balance',
            default        => $path,
        };

        // Tách category/{slug}
        $slug = '';
        if (str_starts_with($path, 'category/')) {
            $slug = trim(substr($path, 9), '/');
            $path = 'category';
        }

        $body = $this->jsonBody();

        switch ($path) {
            case 'services':
                $this->services();
                return;

            case 'categories':
                $this->categories();
                return;

            case 'category':
                if ($slug === '') {
                    $this->fail('Thiếu slug category.', 400);
                    return;
                }
                $this->category($slug);
                return;

            case 'quote':
                $this->quote($body);
                return;

            case 'order':
                $this->order($apikey, $user, $body);
                return;

            case 'status':
                $this->status($apikey, $user, $isAdmin);
                return;

            case 'orders':
                $this->orders($apikey, $user, $isAdmin);
                return;

            case 'balance':
                $this->balance($user);
                return;

            default:
                $this->fail('Endpoint không tồn tại: /' . $path, 404);
        }
    }

    // ================= Endpoint handlers =================

    /**
     * GET /services — toàn bộ dịch vụ đang active kèm giá bán trên web.
     */
    private function services(): void
    {
        $platform = trim((string)($_GET['platform'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $search   = trim((string)($_GET['search'] ?? ''));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = max(1, min(500, (int)($_GET['per_page'] ?? 100)));

        $rows = SmmService::localServices(
            $platform !== '' ? $platform : null,
            $category !== '' ? $category : null,
            $search
        );

        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $data = [];
        foreach ($items as $r) {
            $data[] = $this->publicService($r);
        }

        $this->ok([
            'meta' => [
                'vat_percent' => 0,
                'currency'     => 'VND',
            ],
            'summary' => [
                'total_services'  => $total,
                'total_platforms' => (int)Database::fetchColumn('SELECT COUNT(DISTINCT platform) FROM smm_services WHERE is_active = 1 AND platform <> ""'),
                'total_categories'=> (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_categories'),
                'page'            => $page,
                'per_page'        => $perPage,
            ],
            'data' => $data,
        ]);
    }

    /**
     * GET /categories — card category giống màn hình web SMM.
     */
    private function categories(): void
    {
        $platform = trim((string)($_GET['platform'] ?? ''));
        $search   = trim((string)($_GET['search'] ?? ''));

        $rows = SmmService::localCategories();
        $data = [];

        foreach ($rows as $c) {
            if ($platform !== '' && strcasecmp((string)$c['platform'], $platform) !== 0) {
                continue;
            }
            if ($search !== '') {
                $hay = mb_strtolower((string)$c['category'] . ' ' . (string)$c['clean_category']);
                if (!str_contains($hay, mb_strtolower($search))) {
                    continue;
                }
            }

            $data[] = [
                'category'       => (string)$c['category'],
                'clean_category' => (string)$c['clean_category'],
                'category_slug'  => (string)$c['category_slug'],
                'platform'       => (string)$c['platform'],
                'service_count'  => (int)$c['service_count'],
                'min'            => (int)$c['min'],
                'max'            => (int)$c['max'],
                'price_range'    => [
                    'min_per_unit_vnd' => (float)$c['price_min_per_unit'],
                    'max_per_unit_vnd' => (float)$c['price_max_per_unit'],
                    'display_per_unit' => (string)$c['display_per_unit'],
                ],
            ];
        }

        $this->ok([
            'summary' => [
                'total_categories' => count($data),
                'total_services'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM smm_services WHERE is_active = 1'),
            ],
            'data' => $data,
        ]);
    }

    /**
     * GET /category/{slug}
     */
    private function category(string $slug): void
    {
        $cat = Database::fetch('SELECT * FROM smm_categories WHERE category_slug = ?', [$slug]);
        if (!$cat) {
            $this->fail('Không tìm thấy nhóm dịch vụ SMM', 404);
            return;
        }

        $rows = SmmService::localServices(null, $slug);
        if (!$rows) {
            $this->fail('Không tìm thấy nhóm dịch vụ SMM', 404);
            return;
        }

        $data = [];
        foreach ($rows as $r) {
            $data[] = $this->publicService($r);
        }

        $this->ok([
            'category'       => (string)$cat['category'],
            'clean_category' => (string)$cat['clean_category'],
            'platform'       => (string)$cat['platform'],
            'vat_percent'    => 0,
            'data'           => $data,
        ]);
    }

    /**
     * POST /quote — chỉ tính giá, không tạo đơn.
     */
    private function quote(array $body): void
    {
        $serviceId = (int)($body['service_ref'] ?? $body['service_id'] ?? $body['service'] ?? 0);
        if ($serviceId <= 0) {
            $this->fail('Thiếu service_ref', 400);
            return;
        }

        $service = SmmService::findService($serviceId);
        if (!$service) {
            $this->fail('Không tìm thấy dịch vụ SMM', 404);
            return;
        }

        $isComment = (int)$service['is_comment_service'] === 1;
        $comments  = SmmService::normalizeComments((string)($body['comments'] ?? ''));
        $quantity  = (int)($body['quantity'] ?? 0);

        if ($isComment) {
            $quantity = SmmService::countCommentLines($comments);
        }
        if ($quantity <= 0) {
            $this->fail($isComment
                ? 'Dịch vụ comment cần ít nhất 1 dòng comment hợp lệ.'
                : 'Thiếu quantity', 400);
        }

        $min = (int)$service['min'];
        $max = (int)$service['max'];
        if (($min > 0 && $quantity < $min) || ($max > 0 && $quantity > $max)) {
            $this->fail("Số lượng không hợp lệ. Min $min - Max $max", 400);
            return;
        }

        $pr     = SmmService::priceForKey((float)$service['price_per_1k_vnd'], $this->currentKey);
        $sell1k = $pr['price'];
        $total  = SmmService::calcTotal($sell1k, $quantity);

        $this->ok([
            'service'  => $this->publicService($service),
            'checkout' => [
                'subtotal'     => $total,
                'vat_amount'   => 0,
                'vat_percent'  => 0,
                'total_to_pay' => $total,
                'quantity'     => $quantity,
                'formula'      => 'ceil(quantity / 1000 * price_per_1k_vnd)',
            ],
            'agent_discount' => [
                'list_price'     => $pr['list_price'],
                'discount_percent' => $pr['discount'],
                'discount_amount' => $pr['discount_amount'],
            ],
        ]);
    }

    /**
     * POST /order — tạo đơn thật + trừ ví chính của user gắn với apikey.
     */
    private function order(string $apikey, array $user, array $body): void
    {
        $serviceId = (int)($body['service_ref'] ?? $body['service_id'] ?? $body['service'] ?? 0);
        $link      = trim((string)($body['link'] ?? ''));
        $comments  = SmmService::normalizeComments((string)($body['comments'] ?? ''));
        $reaction  = trim((string)($body['reaction'] ?? ''));
        $userId    = (int)$user['id'];

        if ($serviceId <= 0) {
            $this->fail('Thiếu service_ref', 400);
            return;
        }
        if ($link === '') {
            $this->fail('Thiếu link', 400);
            return;
        }

        $service = SmmService::findService($serviceId);
        if (!$service) {
            $this->fail('Không tìm thấy dịch vụ SMM', 404);
            return;
        }

        $isComment = (int)$service['is_comment_service'] === 1;
        $quantity  = (int)($body['quantity'] ?? 0);
        if ($isComment) {
            $quantity = SmmService::countCommentLines($comments);
            if ($quantity <= 0) {
                $this->fail('Dịch vụ comment cần ít nhất 1 dòng comment hợp lệ.', 400);
                return;
            }
        }
        if ($quantity <= 0) {
            $this->fail('Thiếu quantity', 400);
            return;
        }

        $min = (int)$service['min'];
        $max = (int)$service['max'];
        if (($min > 0 && $quantity < $min) || ($max > 0 && $quantity > $max)) {
            $this->fail("Số lượng không hợp lệ. Min $min - Max $max", 400);
            return;
        }

        $cost   = (float)$service['price_per_1k_vnd'];
        $pr     = SmmService::priceForKey($cost, $this->currentKey);
        $sell1k = $pr['price'];
        $total  = SmmService::calcTotal($sell1k, $quantity);

        // Idempotency: cùng idempotency_key không trừ tiền 2 lần
        $idemKey = trim((string)($body['idempotency_key'] ?? ''));
        if ($idemKey !== '') {
            $existing = Database::fetch(
                'SELECT * FROM smm_orders WHERE idempotency_key = ? AND user_id = ?',
                [$idemKey, $userId]
            );
            if ($existing) {
                $this->ok([
                    'order'    => (string)($existing['remote_order'] ?? $existing['id']),
                    'charge'   => (float)$existing['amount'],
                    'currency' => 'VND',
                    'balance'  => Wallet::balance($userId),
                    'message'  => 'Đơn SMM đã được tạo và trừ tiền ví chính',
                    'data'     => $this->orderPayload($existing),
                ], 200);
                return;
            }
        } else {
            $idemKey = 'smm-api-' . $userId . '-' . bin2hex(random_bytes(6));
        }

        $balance = Wallet::balance($userId);
        if ($balance < $total) {
            $this->fail('Số dư ví chính không đủ. Vui lòng nạp thêm tiền vào web', 400);
            return;
        }

        // Trừ ví chính trước
        Wallet::apply(
            $userId,
            'adjustment',
            -$total,
            'SMM (API): ' . (string)$service['name'],
            'smm',
            (string)$serviceId
        );

        $res = SmmService::createOrder(
            $serviceId,
            $link,
            $isComment ? 0 : $quantity,
            $isComment ? $comments : '',
            $reaction
        );

        if (!$res['ok']) {
            // Hoàn tiền tức thì khi API đối tác lỗi
            Wallet::apply(
                $userId,
                'adjustment',
                $total,
                'Hoàn tiền đặt đơn SMM (API) thất bại',
                'smm_refund',
                (string)$serviceId
            );

            $orderId = SmmService::createOrderRow($userId, [
                'service_id'     => $serviceId,
                'service_name'   => (string)$service['name'],
                'category_slug'  => (string)$service['category_slug'],
                'platform'       => (string)$service['platform'],
                'link'           => $link,
                'quantity'       => $quantity,
                'comments'       => $comments !== '' ? $comments : null,
                'reaction'       => $reaction,
                'price'          => $cost,
                'amount'         => $total,
                'charge'         => 0,
                'status'         => SmmService::STATUS_FAILED,
                'idempotency_key'=> $idemKey,
                'message'        => $res['message'],
            ]);

            $this->fail($res['message'], 400);
            return;
        }

        $row = [
            'remote_order'   => (string)$res['order_id'],
            'service_id'     => $serviceId,
            'service_name'   => (string)$service['name'],
            'category_slug'  => (string)$service['category_slug'],
            'platform'       => (string)$service['platform'],
            'link'           => $link,
            'quantity'       => $quantity,
            'comments'       => $comments !== '' ? $comments : null,
            'reaction'       => $reaction,
            'price'          => $cost,
            'amount'         => $total,
            'charge'         => (float)$res['charge'],
            'status'         => (string)$res['status'],
            'idempotency_key'=> $idemKey,
            'message'        => (string)$res['message'],
            'raw'            => $res['raw'],
        ];
        $orderId = SmmService::createOrderRow($userId, $row);
        $row['id'] = $orderId;

        $this->ok([
            'order'    => (string)$res['order_id'],
            'charge'   => $total,
            'currency' => 'VND',
            'balance'  => Wallet::balance($userId),
            'message'  => 'Đơn SMM đã được tạo và trừ tiền ví chính',
            'data'     => $this->orderPayload($row),
            'agent_discount' => [
                'list_price'     => $pr['list_price'],
                'discount_percent' => $pr['discount'],
                'discount_amount' => $pr['discount_amount'],
            ],
        ]);
    }

    /**
     * GET /status?order=... hoặc /status?orders=...
     */
    private function status(string $apikey, array $user, bool $isAdmin): void
    {
        $orders = trim((string)($_GET['orders'] ?? ''));
        $order  = trim((string)($_GET['order'] ?? $_GET['id'] ?? $_POST['order'] ?? ''));

        $ids = [];
        if ($orders !== '') {
            foreach (explode(',', $orders) as $o) {
                $o = trim($o);
                if ($o !== '') {
                    $ids[] = $o;
                }
            }
            if (count($ids) > 100) {
                $this->fail('Chỉ cho phép tối đa 100 order mỗi lần', 400);
                return;
            }
        } elseif ($order !== '') {
            $ids[] = $order;
        } else {
            $this->fail('Thiếu order', 400);
            return;
        }

        // User thường chỉ check được đơn của chính mình
        if (!$isAdmin) {
            foreach ($ids as $oid) {
                $owned = Database::fetchColumn(
                    'SELECT id FROM smm_orders WHERE remote_order = ? AND user_id = ? LIMIT 1',
                    [$oid, (int)$user['id']]
                );
                if (!$owned) {
                    $this->fail('Bạn chỉ được kiểm tra trạng thái các đơn SMM của chính mình', 403);
                    return;
                }
            }
        }

        // Đồng bộ trạng thái mới nhất từ đối tác
        $this->refreshLocalStatuses($ids, $isAdmin ? null : (int)$user['id']);

        $data = [];
        foreach ($ids as $oid) {
            $row = $isAdmin
                ? Database::fetch('SELECT * FROM smm_orders WHERE remote_order = ? LIMIT 1', [$oid])
                : Database::fetch('SELECT * FROM smm_orders WHERE remote_order = ? AND user_id = ? LIMIT 1', [$oid, (int)$user['id']]);

            $data[$oid] = [
                'status'      => $row ? SmmService::mapStatus((string)$row['status']) : SmmService::STATUS_PENDING,
                'start_count' => $row ? (string)$row['start_count'] : '0',
                'remains'     => $row ? (string)$row['remains'] : '0',
                'charge'      => $row ? (float)$row['amount'] : 0,
                'currency'    => 'VND',
            ];
        }

        $this->ok(['data' => $data]);
    }

    /**
     * GET /orders
     */
    private function orders(string $apikey, array $user, bool $isAdmin): void
    {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $serviceIds = trim((string)($_GET['service_ids'] ?? ''));

        $sql = 'SELECT * FROM smm_orders WHERE 1 = 1';
        $params = [];
        if (!$isAdmin) {
            $sql .= ' AND user_id = ?';
            $params[] = (int)$user['id'];
        }
        if ($serviceIds !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $serviceIds))));
            if ($ids) {
                $sql .= ' AND service_id IN (' . implode(',', $ids) . ')';
            }
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $rows = $params ? Database::fetchAll($sql, $params) : Database::fetchAll($sql);

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'           => (int)$r['id'],
                'order'        => (string)($r['remote_order'] ?: $r['id']),
                'service_id'   => (int)$r['service_id'],
                'service_name' => (string)$r['service_name'],
                'quantity'     => (int)$r['quantity'],
                'price'        => (float)$r['amount'],
                'status'       => SmmService::mapStatus((string)$r['status']),
                'start_count'  => (int)$r['start_count'],
                'remains'      => (int)$r['remains'],
                'created_at'   => (string)$r['created_at'],
                'updated_at'   => (string)$r['updated_at'],
            ];
        }

        $this->ok(['data' => $data]);
    }

    /**
     * GET /balance — số dư ví chính của user gắn với apikey.
     */
    private function balance(array $user): void
    {
        $balance = Wallet::balance((int)$user['id']);

        $this->ok([
            'balance'  => $balance,
            'currency' => 'VND',
            'data'     => [
                'user_id'    => (int)$user['id'],
                'username'   => (string)$user['username'],
                'balance'    => $balance,
                'api_status' => 'active',
            ],
        ]);
    }

    // ================= Helpers =================

    /**
     * Chuẩn hoá 1 dòng service trong DB → payload public (giá bán trên web).
     */
    private function publicService(array $r): array
    {
        $cost   = (float)$r['price_per_1k_vnd'];
        $pr     = SmmService::priceForKey($cost, $this->currentKey);
        $sell1k = $pr['price'];
        $list   = $pr['list_price'];
        $disc   = $pr['discount'];

        $out = [
            'id'                 => (int)$r['service_id'],
            'service'            => (int)$r['service_id'],
            'name'               => (string)$r['name'],
            'type'               => (string)$r['type'],
            'category'           => (string)$r['category'],
            'platform'           => (string)$r['platform'],
            'min'                => (int)$r['min'],
            'max'                => (int)$r['max'],
            'refill'             => (bool)$r['refill'],
            'price_per_1k_vnd'   => $sell1k,
            'price_per_unit_vnd' => SmmService::pricePerUnit($sell1k),
            'is_comment_service' => (bool)$r['is_comment_service'],
        ];

        if ($list !== null) {
            $out['list_price_per_1k_vnd'] = $list;
        }
        if ($disc > 0) {
            $out['agent_discount_percent'] = $disc;
        }

        return $out;
    }

    /**
     * Payload đơn hàng trả về cho đối tác.
     */
    private function orderPayload(array $r): array
    {
        return [
            'id'           => (int)($r['id'] ?? 0),
            'order'        => (string)($r['remote_order'] ?? $r['id'] ?? ''),
            'service'      => (int)($r['service_id'] ?? 0),
            'service_name' => (string)($r['service_name'] ?? ''),
            'quantity'     => (int)($r['quantity'] ?? 0),
            'status'       => SmmService::mapStatus((string)($r['status'] ?? '')),
            'subtotal'     => (float)($r['amount'] ?? 0),
            'vat_amount'   => 0,
            'total_to_pay' => (float)($r['amount'] ?? 0),
            'balance_after'=> Wallet::balance((int)($r['user_id'] ?? 0)),
        ];
    }

    /**
     * Đồng bộ trạng thái các đơn local từ API đối tác (batch tối đa 100 mã).
     */
    private function refreshLocalStatuses(array $orderIds, ?int $userId): void
    {
        $ids = array_values(array_filter(array_map('intval', $orderIds)));
        if (!$ids) {
            return;
        }

        $in = implode(',', $ids);
        $sql = 'SELECT id, user_id, remote_order FROM smm_orders
                WHERE remote_order IN (' . $in . ')
                  AND status IN ("pending","processing")';
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $rows = $params ? Database::fetchAll($sql, $params) : Database::fetchAll($sql);
        if (!$rows) {
            return;
        }

        // Gom theo user để gọi đúng key
        $byUser = [];
        foreach ($rows as $r) {
            $byUser[(int)$r['user_id']][(string)$r['remote_order']] = (int)$r['id'];
        }

        foreach ($byUser as $uid => $map) {
            $key = SmmService::userKey((int)$uid) ?? SmmService::apiKey();
            $res = SmmService::statusBatch(array_keys($map), $key);
            if (!$res['ok']) {
                continue;
            }
            foreach ($res['data'] as $oid => $d) {
                if (!is_array($d)) {
                    continue;
                }
                $localId = $map[(string)$oid] ?? null;
                if ($localId === null) {
                    continue;
                }
                SmmService::updateOrderStatus(
                    (int)$localId,
                    SmmService::mapStatus((string)($d['status'] ?? '')),
                    (string)($d['start_count'] ?? ''),
                    (string)($d['remains'] ?? '')
                );
            }
        }
    }

    /**
     * Trích apikey từ header / query / body.
     */
    private function extractApiKey(): string
    {
        $key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($auth !== '' && stripos($auth, 'bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($_GET[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        $body = $this->jsonBody();
        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($body[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        foreach (['apikey', 'api_key', 'key'] as $f) {
            $v = trim((string)($_POST[$f] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Đọc JSON body.
     */
    private function jsonBody(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return $cache = [];
        }

        $decoded = json_decode($raw, true);
        return $cache = is_array($decoded) ? $decoded : [];
    }

    /**
     * Trả JSON thành công (kèm success=true).
     */
    private function ok(array $payload, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Trả lỗi chuẩn JSON.
     */
    private function fail(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
