<?php
namespace App\Services;

use App\Core\Database;

/**
 * Phần mềm quản lý bán hàng (POS) — mô hình KiotViet cho nhiều ngành nghề.
 *
 * Khi người dùng bấm vào menu, toàn bộ website được thay thế bằng giao diện
 * POS (fullscreen). Thoát bằng nút "Thoát" hoặc phím ESC → quay lại website.
 */
class PosService
{
    public const CATEGORIES = [
        'food'      => 'Nhà hàng - Ăn uống',
        'coffee'    => 'Quán cà phê - Trà sữa',
        'retail'    => 'Cửa hàng - Bán lẻ',
        'student'   => 'Quản lý sinh viên',
        'beauty'    => 'Làm đẹp - Spa',
        'fashion'   => 'Thời trang',
        'pharmacy'  => 'Nhà thuốc',
        'hardware'  => 'Phần cứng - Xây dựng',
        'service'   => 'Dịch vụ',
        'other'     => 'Ngành nghề khác',
    ];

    public const PAYMENT_METHODS = [
        'cash'    => 'Tiền mặt',
        'card'    => 'Thẻ ngân hàng',
        'transfer'=> 'Chuyển khoản',
        'momo'    => 'MoMo',
        'cod'     => 'COD',
    ];

    /**
     * Danh mục POS theo ngành nghề.
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function products(?string $category = null, string $q = '', int $limit = 200): array
    {
        $where  = ['is_active = 1'];
        $params = [];
        if ($category !== null && $category !== '') {
            $where[]  = 'category = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $where[]  = '(name LIKE ? OR code LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        return Database::fetchAll(
            'SELECT * FROM pos_products WHERE ' . implode(' AND ', $where) .
            ' ORDER BY category, name LIMIT ' . (int)$limit,
            $params
        );
    }

    public static function customers(string $q = '', int $limit = 100): array
    {
        if ($q !== '') {
            return Database::fetchAll(
                'SELECT * FROM pos_customers WHERE name LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT ' . (int)$limit,
                ['%' . $q . '%', '%' . $q . '%']
            );
        }
        return Database::fetchAll('SELECT * FROM pos_customers ORDER BY id DESC LIMIT ' . (int)$limit);
    }

    public static function orders(int $limit = 50, string $status = ''): array
    {
        if ($status !== '') {
            return Database::fetchAll(
                'SELECT * FROM pos_orders WHERE status = ? ORDER BY id DESC LIMIT ' . (int)$limit,
                [$status]
            );
        }
        return Database::fetchAll('SELECT * FROM pos_orders ORDER BY id DESC LIMIT ' . (int)$limit);
    }

    /**
     * Tạo đơn hàng.
     */
    public static function createOrder(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || !$items) {
            return ['ok' => false, 'message' => 'Chưa có sản phẩm nào trong đơn.'];
        }

        $subtotal = 0.0;
        $clean = [];
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = max(1, (int)($it['qty'] ?? 1));
            $p = Database::fetch('SELECT * FROM pos_products WHERE id = ? AND is_active = 1', [$pid]);
            if (!$p) {
                continue;
            }
            $line = (float)$p['price'] * $qty;
            $subtotal += $line;
            $clean[] = [
                'product_id' => $pid,
                'name'       => $p['name'],
                'price'      => (float)$p['price'],
                'qty'        => $qty,
                'line_total' => $line,
            ];
        }

        if (!$clean) {
            return ['ok' => false, 'message' => 'Không tìm thấy sản phẩm hợp lệ.'];
        }

        $discount = (float)($data['discount'] ?? 0);
        $total    = max(0, $subtotal - $discount);
        $orderNo  = 'HD' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));

        $id = Database::insert('pos_orders', [
            'order_no'        => $orderNo,
            'user_id'         => \App\Core\Auth::id(),
            'customer_name'   => trim((string)($data['customer_name'] ?? '')) ?: null,
            'customer_phone'  => trim((string)($data['customer_phone'] ?? '')) ?: null,
            'items'           => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'total'           => $total,
            'payment_method'  => (string)($data['payment_method'] ?? 'cash'),
            'status'          => (string)($data['status'] ?? 'paid'),
            'note'            => trim((string)($data['note'] ?? '')) ?: null,
        ]);

        // Tăng doanh thu cho khách hàng
        if (!empty($data['customer_id'])) {
            Database::query(
                'UPDATE pos_customers SET total_spent = total_spent + ? WHERE id = ?',
                [$total, (int)$data['customer_id']]
            );
        }

        return ['ok' => true, 'message' => 'Đã tạo đơn ' . $orderNo, 'id' => $id, 'total' => $total];
    }

    /**
     * Thống kê nhanh cho dashboard POS.
     */
    public static function stats(): array
    {
        $today = (string)Database::fetchColumn(
            'SELECT COALESCE(SUM(total),0) FROM pos_orders WHERE DATE(created_at) = CURDATE() AND status = "paid"'
        );
        $month = (string)Database::fetchColumn(
            'SELECT COALESCE(SUM(total),0) FROM pos_orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND status = "paid"'
        );
        return [
            'today'      => (float)$today,
            'month'      => (float)$month,
            'orders_today' => (int)Database::fetchColumn(
                'SELECT COUNT(*) FROM pos_orders WHERE DATE(created_at) = CURDATE()'
            ),
            'products'   => (int)Database::fetchColumn('SELECT COUNT(*) FROM pos_products WHERE is_active = 1'),
            'customers'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM pos_customers'),
        ];
    }
}
