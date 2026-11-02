<?php
namespace App\Services;

use App\Core\Database;

/**
 * Bán proxy & tài khoản dịch vụ.
 */
class ProxyService
{
    public const KINDS = [
        'http'        => 'HTTP/HTTPS',
        'socks5'      => 'SOCKS5',
        'residential' => 'Residential (ISP)',
        'mobile'      => 'Mobile 4G/5G',
        'mail'        => 'Mail / SMTP',
    ];

    public static function kindLabel(string $code): string
    {
        return self::KINDS[$code] ?? $code;
    }

    public static function list(?string $kind = null, string $q = '', int $limit = 30, int $offset = 0): array
    {
        $where  = ['pl.is_active = 1'];
        $params = [];
        if ($kind !== null && $kind !== '') {
            $where[]  = 'pl.kind = ?';
            $params[] = $kind;
        }
        if ($q !== '') {
            $where[]  = '(pl.title LIKE ? OR pl.country LIKE ? OR pl.city LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        return Database::fetchAll(
            'SELECT pl.*, u.username AS seller_name, u.is_verified AS seller_verified
             FROM proxy_listings pl JOIN users u ON u.id = pl.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY pl.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            $params
        );
    }

    public static function count(?string $kind = null, string $q = ''): int
    {
        $where  = ['pl.is_active = 1'];
        $params = [];
        if ($kind !== null && $kind !== '') {
            $where[]  = 'pl.kind = ?';
            $params[] = $kind;
        }
        if ($q !== '') {
            $where[]  = '(pl.title LIKE ? OR pl.country LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        return (int)Database::fetchColumn(
            'SELECT COUNT(*) FROM proxy_listings pl WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public static function create(int $sellerId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên listing.'];
        }
        $kind = (string)($data['kind'] ?? 'http');
        if (!isset(self::KINDS[$kind])) {
            $kind = 'http';
        }
        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            return ['ok' => false, 'message' => 'Giá phải lớn hơn 0.'];
        }

        $id = Database::insert('proxy_listings', [
            'user_id'       => $sellerId,
            'title'         => mb_substr($title, 0, 200),
            'kind'          => $kind,
            'country'       => trim((string)($data['country'] ?? '')) ?: null,
            'city'          => trim((string)($data['city'] ?? '')) ?: null,
            'isp'           => trim((string)($data['isp'] ?? '')) ?: null,
            'duration_days' => max(1, (int)($data['duration_days'] ?? 30)),
            'threads'       => max(1, (int)($data['threads'] ?? 1)),
            'price'         => $price,
            'stock'         => (int)($data['stock'] ?? -1),
            'endpoint'      => trim((string)($data['endpoint'] ?? '')) ?: null,
            'is_active'     => 1,
        ]);

        return ['ok' => true, 'message' => 'Đã đăng listing proxy.', 'id' => $id];
    }

    // ================= Mua proxy =================

    public static function findListing(int $id): ?array
    {
        return Database::fetch(
            'SELECT pl.*, u.username AS seller_name, u.is_verified AS seller_verified
             FROM proxy_listings pl JOIN users u ON u.id = pl.user_id
             WHERE pl.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Mua proxy: trừ ví người mua, tạo đơn, giao endpoint cho ngay.
     */
    public static function purchase(int $buyerId, int $listingId, int $quantity = 1, string $voucher = ''): array
    {
        $listing = self::findListing($listingId);
        if (!$listing) {
            return ['ok' => false, 'message' => 'Listing không tồn tại hoặc đã ngừng bán.'];
        }
        if ((int)$listing['user_id'] === $buyerId) {
            return ['ok' => false, 'message' => 'Bạn không thể mua listing của chính mình.'];
        }
        if ((int)$listing['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'Listing này đã ngừng bán.'];
        }

        $stock = (int)($listing['stock'] ?? -1);
        $quantity = max(1, $quantity);
        if ($stock >= 0 && $quantity > $stock) {
            return ['ok' => false, 'message' => 'Chỉ còn ' . $stock . ' gói khả dụng.'];
        }

        $unitPrice = (float)$listing['price'];
        $total = $unitPrice * $quantity;

        // Áp dụng mã ưu đãi
        $discount = 0;
        $voucherRow = null;
        $voucher = strtoupper(trim($voucher));
        if ($voucher !== '') {
            $voucherRow = Database::fetch(
                'SELECT * FROM proxy_vouchers WHERE code = ? AND is_active = 1 LIMIT 1',
                [$voucher]
            );
            if ($voucherRow && (int)$voucherRow['max_uses'] > (int)$voucherRow['used_count']) {
                $discount = round($total * (float)$voucherRow['discount'] / 100, 2);
            } else {
                $voucherRow = null;
            }
        }
        $total = max(0, $total - $discount);

        $balance = (float)Database::fetchColumn('SELECT balance FROM users WHERE id = ?', [$buyerId]);
        if ($balance < $total) {
            return [
                'ok' => false,
                'message' => 'Số dư không đủ. Cần ' . money($total) . ', hiện có ' . money($balance) . '.',
            ];
        }

        try {
            Database::transaction(function () use ($buyerId, $listing, $listingId, $quantity, $unitPrice, $total, $discount, $voucherRow) {
                \App\Core\Wallet::apply(
                    $buyerId,
                    'adjustment',
                    -$total,
                    'Mua proxy: ' . $listing['title'] . ' ×' . $quantity,
                    'proxy_order',
                    (string)$listingId
                );

                if ($voucherRow) {
                    Database::query(
                        'UPDATE proxy_vouchers SET used_count = used_count + 1 WHERE id = ?',
                        [(int)$voucherRow['id']]
                    );
                }

                // Trừ kho listing (nếu có giới hạn)
                if ((int)($listing['stock'] ?? -1) >= 0) {
                    Database::query(
                        'UPDATE proxy_listings SET stock = GREATEST(0, stock - ?) WHERE id = ?',
                        [$quantity, $listingId]
                    );
                }

                Database::insert('proxy_orders', [
                    'user_id'      => $buyerId,
                    'listing_id'   => $listingId,
                    'title'        => mb_substr((string)$listing['title'], 0, 200),
                    'kind'         => (string)$listing['kind'],
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'total_amount' => $total,
                    'endpoint'     => $listing['endpoint'] ?? null,
                    'status'       => 'paid',
                    'note'         => $discount > 0 ? ('Đã áp dụng ưu đãi -' . money($discount)) : null,
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Không thể hoàn tất giao dịch. Vui lòng thử lại.'];
        }

        return [
            'ok' => true,
            'message' => 'Đã mua thành công ' . $quantity . ' gói proxy.',
            'total'   => $total,
        ];
    }

    /** Đơn mua của tôi */
    public static function myOrders(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM proxy_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit,
            [$userId]
        );
    }
}
