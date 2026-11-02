<?php
/**
 * Router cho PHP built-in server (php -S).
 * Chỉ dùng khi phát triển; production dùng Apache/Nginx + .htaccess.
 */
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// File tĩnh tồn tại → phục vụ trực tiếp
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Chặn truy cập trực tiếp vào thư mục nhạy cảm
if (preg_match('#^/(app|config|database|storage)/#', $uri)) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/index.php';
