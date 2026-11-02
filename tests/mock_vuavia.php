<?php
/**
 * Mock server API Vuavia — dùng để test luồng mua hàng mà không cần key thật.
 * Chạy: php tests/mock_vuavia.php
 *   Balance : GET  /api/v2/wallet/balance
 *   Products: GET  /api/v2/products
 *   Order   : POST /api/v2/orders
 */
$port = (int)(getenv('VUAVIA_MOCK_PORT') ?: 8921);
$root = dirname(__DIR__);

$router = function (string $method, string $path): array {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($key === '' || !str_starts_with($key, 'vav_')) {
        return [401, ['success' => false, 'message' => 'Unauthorized', 'data' => null, 'error' => ['code' => 'UNAUTHORIZED']]];
    }

    if (str_ends_with($path, '/wallet/balance')) {
        return [200, ['success' => true, 'message' => 'Thành công', 'data' => ['balance' => 150000], 'error' => null]];
    }

    if (str_ends_with($path, '/products')) {
        return [200, ['success' => true, 'message' => 'Thành công', 'error' => null, 'data' => [
            'content' => [
                ['id' => 8, 'name' => 'Acc Việt Nữ 50-1000 Bạn Bè Trên 18 Tuổi', 'price' => 160000, 'stockCount' => 4, 'deliveryType' => 'AUTO', 'categoryId' => 5, 'categoryName' => 'Via'],
                ['id' => 9, 'name' => 'Facebook Clone VN', 'price' => 30000, 'stockCount' => 0, 'deliveryType' => 'MANUAL', 'categoryId' => 6, 'categoryName' => 'Facebook'],
                ['id' => 10, 'name' => 'TikTok Việt Nam', 'price' => 50000, 'stockCount' => 5, 'deliveryType' => 'AUTO', 'categoryId' => 7, 'categoryName' => 'TikTok'],
            ],
            'totalElements' => 3, 'totalPages' => 1, 'number' => 0, 'size' => 20,
        ]]];
    }

    if (str_ends_with($path, '/orders') && $method === 'POST') {
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true) ?: [];
        $pid = (int)($in['productId'] ?? 0);
        $qty = (int)($in['quantity'] ?? 1);

        if ($pid <= 0) {
            return [400, ['success' => false, 'message' => 'productId is required', 'data' => null, 'error' => ['code' => 'BAD_REQUEST']]];
        }
        if ($pid === 9) {
            return [409, ['success' => false, 'message' => 'Out of stock', 'data' => null, 'error' => ['code' => 'OUT_OF_STOCK']]];
        }
        if ($pid === 10) {
            return [400, ['success' => false, 'message' => 'Số dư ví không đủ', 'data' => null, 'error' => ['code' => 'INSUFFICIENT_BALANCE']]];
        }

        return [200, ['success' => true, 'message' => 'Đặt hàng thành công', 'error' => null, 'data' => [
            'id' => 101,
            'status' => 'DELIVERED',
            'totalAmount' => 160000 * $qty,
            'quantity' => $qty,
            'deliveredItems' => ['acc' . $pid . '_1|pass' . $pid . '_1', 'acc' . $pid . '_2|pass' . $pid . '_2'],
            'createdAt' => date('c'),
        ]]];
    }

    return [404, ['success' => false, 'message' => 'Not found', 'data' => null, 'error' => ['code' => 'NOT_FOUND']]];
};

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    [$code, $body] = $router($_SERVER['REQUEST_METHOD'] ?? 'GET', (string)$path);
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
[$code, $body] = $router($_SERVER['REQUEST_METHOD'] ?? 'GET', (string)$path);
http_response_code($code);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
