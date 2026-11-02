<?php
namespace App\Services;

/**
 * Chạy 1 tác vụ PHP ở chế độ nền (detached).
 *
 * Dùng cho:
 *  - Job quét UI/UX (bấm "Quét" → trang hiện tiến trình → AJAX polling)
 *  - Job copy 1-1 trang web
 *  - Job tạo website A-Z
 *
 * Trên Windows dùng `start /B` của cmd, trên Linux dùng `nohup ... &`.
 * Nếu không spawn được (hosting cấm exec), trả về false để controller
 * chuyển sang chạy đồng bộ.
 */
class BackgroundJob
{
    /**
     * Spawn 1 script CLI với tham số.
     *
     * @param string $script Đường dẫn tuyệt đối tới file PHP
     * @param array  $args   Danh sách tham số
     * @return bool true nếu đã khởi chạy nền
     */
    public static function spawn(string $script, array $args = []): bool
    {
        if (!is_file($script)) {
            return false;
        }

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $parts = [escapeshellarg($php), escapeshellarg($script)];
        foreach ($args as $a) {
            $parts[] = escapeshellarg((string)$a);
        }
        $cmd = implode(' ', $parts);

        $isWindows = PHP_OS_FAMILY === 'Windows';

        // Ghi log ra file để debug
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/background.log';

        $descriptors = [1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];

        if ($isWindows) {
            // start /B chạy ẩn cửa sổ, tách phiên khỏi PHP
            $full = 'start /B "" ' . $cmd;
        } else {
            $full = 'nohup ' . $cmd . ' &';
        }

        $proc = @proc_open($full, $descriptors, $pipes, BASE_PATH);
        if (!is_resource($proc)) {
            return false;
        }

        return true;
    }
}
