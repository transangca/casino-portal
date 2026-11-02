<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Chế độ bảo trì toàn hệ thống.
 *
 * Admin bật trong /admin/settings → mọi khách truy cập (trừ admin) sẽ thấy
 * trang bảo trì thay vì trang chủ. Các API vẫn hoạt động để game server
 * không bị gián đoạn.
 */
class MaintenanceService
{
    public static function enabled(): bool
    {
        if (setting('maintenance_mode', '0') !== '1') {
            return false;
        }
        // Admin vẫn được vào để cấu hình
        if (setting('maintenance_allow_admin', '1') === '1' && Auth::isAdmin()) {
            return false;
        }
        return true;
    }

    public static function message(): string
    {
        return (string)setting('maintenance_message', 'Hệ thống đang bảo trì. Vui lòng quay lại sau ít phút.');
    }

    /**
     * Hiển thị trang bảo trì và dừng xử lý.
     */
    public static function render(): never
    {
        http_response_code(503);
        header('Retry-After: 900');

        $title = 'Đang bảo trì';
        $message = self::message();
        $contact = (string)setting('support_telegram', '');

        include BASE_PATH . '/app/Views/maintenance.php';
        exit;
    }

    /**
     * Chỉ bảo trì 1 nhóm dịch vụ (menu con trong trang quản trị).
     */
    public static function serviceInMaintenance(string $code): bool
    {
        $row = Database::fetch(
            'SELECT is_maintenance FROM service_catalog WHERE code = ?',
            [$code]
        );
        return (bool)($row['is_maintenance'] ?? false);
    }
}
