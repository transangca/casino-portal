<?php
namespace App\Services;

use App\Core\Database;

/**
 * Chợ tài nguyên — bán/mua tài khoản, tài nguyên, dịch vụ.
 */
class MarketService
{
    public const CATEGORIES = [
        'account'  => 'Tài khoản',
        'tool'     => 'Tool / Script',
        'source'   => 'Source code',
        'service'  => 'Dịch vụ',
        'resource' => 'Tài nguyên khác',
    ];

    public static function categoryLabel(string $code): string
    {
        return self::CATEGORIES[$code] ?? $code;
    }

    public static function all(?string $category = null, string $q = '', int $limit = 40, int $offset = 0): array
    {
        $where  = ['mp.is_active = 1'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'mp.category = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $where[] = '(mp.title LIKE ? OR mp.description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql = 'SELECT mp.*, u.username AS seller_name, u.is_verified AS seller_verified
                FROM market_products mp
                JOIN users u ON u.id = mp.seller_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY mp.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        return Database::fetchAll($sql, $params);
    }

    public static function count(?string $category = null, string $q = ''): int
    {
        $where  = ['mp.is_active = 1'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'mp.category = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $where[] = '(mp.title LIKE ? OR mp.description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM market_products mp WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT mp.*, u.username AS seller_name, u.is_verified AS seller_verified
             FROM market_products mp JOIN users u ON u.id = mp.seller_id
             WHERE mp.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function bySlug(string $slug): ?array
    {
        return Database::fetch(
            'SELECT mp.*, u.username AS seller_name, u.is_verified AS seller_verified
             FROM market_products mp JOIN users u ON u.id = mp.seller_id
             WHERE mp.slug = ? LIMIT 1',
            [$slug]
        );
    }

    public static function myItems(int $sellerId): array
    {
        return Database::fetchAll(
            'SELECT mp.*, (SELECT COUNT(*) FROM market_orders mo WHERE mo.product_id = mp.id) AS order_count
             FROM market_products mp WHERE mp.seller_id = ? ORDER BY mp.id DESC',
            [$sellerId]
        );
    }

    public static function create(int $sellerId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên sản phẩm.'];
        }

        $price = (float)($data['price'] ?? 0);
        if ($price < 0) {
            return ['ok' => false, 'message' => 'Giá không hợp lệ.'];
        }

        $category = (string)($data['category'] ?? 'account');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'account';
        }

        $slug = slugify($title);
        $i = 1;
        $base = $slug;
        while (Database::fetchColumn('SELECT id FROM market_products WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        $id = Database::insert('market_products', [
            'seller_id'   => $sellerId,
            'title'       => mb_substr($title, 0, 200),
            'slug'        => $slug,
            'category'    => $category,
            'description' => trim((string)($data['description'] ?? '')),
            'price'       => $price,
            'stock'       => (int)($data['stock'] ?? -1),
            'image'       => trim((string)($data['image'] ?? '')) ?: null,
            'contact'     => trim((string)($data['contact'] ?? '')) ?: null,
            'is_active'   => 1,
        ]);

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'market.list_create', $sellerId, $title, [
            'price' => $price,
        ]);

        return ['ok' => true, 'message' => 'Đã đăng bán tài nguyên.', 'id' => $id, 'slug' => $slug];
    }

    /**
     * Đặt mua — tiền chuyển từ ví người mua sang ví người bán.
     */
    public static function buy(int $buyerId, int $productId, string $note = ''): array
    {
        $product = self::find($productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Sản phẩm không tồn tại.'];
        }
        if ((int)$product['seller_id'] === $buyerId) {
            return ['ok' => false, 'message' => 'Bạn không thể mua sản phẩm của chính mình.'];
        }
        if ((int)$product['stock'] === 0) {
            return ['ok' => false, 'message' => 'Sản phẩm đã hết hàng.'];
        }

        $amount = (float)$product['price'];
        $sellerId = (int)$product['seller_id'];

        try {
            Database::transaction(function () use ($buyerId, $sellerId, $productId, $amount, $note, $product) {
                \App\Core\Wallet::apply(
                    $buyerId,
                    'adjustment',
                    -$amount,
                    'Mua tài nguyên: ' . $product['title'],
                    'market_order',
                    (string)$productId
                );

                \App\Core\Wallet::apply(
                    $sellerId,
                    'adjustment',
                    $amount,
                    'Bán tài nguyên: ' . $product['title'],
                    'market_order',
                    (string)$productId
                );

                Database::insert('market_orders', [
                    'product_id' => $productId,
                    'buyer_id'   => $buyerId,
                    'seller_id'  => $sellerId,
                    'amount'     => $amount,
                    'status'     => 'paid',
                    'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
                ]);

                if ((int)$product['stock'] > 0) {
                    Database::query(
                        'UPDATE market_products SET stock = stock - 1, sold_count = sold_count + 1 WHERE id = ?',
                        [$productId]
                    );
                } else {
                    Database::query(
                        'UPDATE market_products SET sold_count = sold_count + 1 WHERE id = ?',
                        [$productId]
                    );
                }
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        ActivityLogService::log(ActivityLogService::SCOPE_SYSTEM, 'market.order', $buyerId, (string)$productId, [
            'amount' => $amount,
        ]);

        return ['ok' => true, 'message' => 'Mua thành công. Liên hệ người bán để nhận tài nguyên.'];
    }

    public static function myOrders(int $buyerId): array
    {
        return Database::fetchAll(
            'SELECT mo.*, mp.title, mp.contact, mp.image, u.username AS seller_name
             FROM market_orders mo
             JOIN market_products mp ON mp.id = mo.product_id
             JOIN users u ON u.id = mo.seller_id
             WHERE mo.buyer_id = ? ORDER BY mo.id DESC',
            [$buyerId]
        );
    }
}
