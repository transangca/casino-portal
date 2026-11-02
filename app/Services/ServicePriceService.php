<?php
namespace App\Services;

use App\Core\Database;

/**
 * Bảng giá dịch vụ — admin quản lý trong Quản trị → Bảng giá dịch vụ.
 *
 * Giá niêm yết là giá bán ra cho khách. Admin có thể đặt "giá vốn"
 * (chi phí gọi API đối tác) để hệ thống tự tính biên lợi nhuận.
 */
class ServicePriceService
{
    /** @var array|null cache trong request */
    private static ?array $cache = null;

    /**
     * Danh sách giá (group => items).
     */
    public static function groups(): array
    {
        $out = [];
        foreach (self::all() as $p) {
            $out[(string)$p['group_name']][] = $p;
        }
        return $out;
    }

    /**
     * Toàn bộ bảng giá.
     */
    public static function all(bool $onlyActive = true): array
    {
        if (!$onlyActive && self::$cache !== null) {
            return self::$cache;
        }
        $sql = 'SELECT * FROM service_prices'
            . ($onlyActive ? ' WHERE is_active = 1' : '')
            . ' ORDER BY group_name, sort_order, id';
        $rows = Database::fetchAll($sql);
        if (!$onlyActive) {
            self::$cache = $rows;
        }
        return $rows;
    }

    /**
     * Giá của 1 mã dịch vụ.
     */
    public static function price(string $code, float $default = 0.0): float
    {
        $v = Database::fetchColumn(
            'SELECT price FROM service_prices WHERE code = ? AND is_active = 1',
            [$code]
        );
        return $v === null ? $default : (float)$v;
    }

    /**
     * Lấy nguyên bản ghi giá.
     */
    public static function find(string $code): ?array
    {
        return Database::fetch('SELECT * FROM service_prices WHERE code = ?', [$code]);
    }

    /**
     * Thêm / sửa giá.
     */
    public static function save(array $data): array
    {
        $code = trim((string)($data['code'] ?? ''));
        if (!preg_match('/^[a-z0-9_\-]+$/i', $code)) {
            return ['ok' => false, 'message' => 'Mã dịch vụ không hợp lệ (chỉ chữ, số, gạch dưới, gạch ngang).'];
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập tên dịch vụ.'];
        }

        $price = (float)($data['price'] ?? 0);
        if ($price < 0) {
            return ['ok' => false, 'message' => 'Giá không hợp lệ.'];
        }

        $row = [
            'name'        => mb_substr($name, 0, 150),
            'group_name'  => mb_substr(trim((string)($data['group_name'] ?? 'Dịch vụ')), 0, 50) ?: 'Dịch vụ',
            'price'       => $price,
            'unit'        => mb_substr(trim((string)($data['unit'] ?? 'lần')), 0, 40) ?: 'lần',
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 255) ?: null,
            'is_active'   => isset($data['is_active']) ? 1 : 0,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
        ];

        $exists = Database::fetchColumn('SELECT id FROM service_prices WHERE code = ?', [$code]);
        if ($exists) {
            Database::update('service_prices', $row, 'id = :id', ['id' => (int)$exists]);
        } else {
            $row['code'] = $code;
            Database::insert('service_prices', $row);
        }

        self::$cache = null;
        return ['ok' => true, 'message' => 'Đã lưu giá dịch vụ "' . $name . '".'];
    }

    /**
     * Xoá giá.
     */
    public static function delete(int $id): array
    {
        $row = Database::fetch('SELECT * FROM service_prices WHERE id = ?', [$id]);
        if (!$row) {
            return ['ok' => false, 'message' => 'Không tìm thấy bản ghi.'];
        }
        Database::delete('service_prices', 'id = ?', [$id]);
        self::$cache = null;
        return ['ok' => true, 'message' => 'Đã xoá giá dịch vụ.'];
    }

    /**
     * Bảng giá dạng key => price để các service khác dùng nhanh.
     */
    public static function map(): array
    {
        $out = [];
        foreach (self::all() as $p) {
            $out[(string)$p['code']] = (float)$p['price'];
        }
        return $out;
    }
}
