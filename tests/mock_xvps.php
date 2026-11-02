<?php
/**
 * Mock API XVPS — dùng cho test E2E.
 *
 * Chạy: php -S 127.0.0.1:8788 tests/mock_xvps.php
 */

$token = 'MOCK.JWT.TOKEN';

// Đọc body JSON
$body = file_get_contents('php://input');
$json = json_decode($body, true) ?: [];

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('Content-Type: application/json');

function resp(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra 3 headers bắt buộc
$required = ['api-username', 'api-app', 'api-secret'];
foreach ($required as $h) {
    if (($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $h))] ?? '') === '') {
        resp(['error' => 1, 'message' => 'Headers api-username, api-app, api-secret are required'], 400);
    }
}

// Xác thực credentials (trong test E2E dùng mock_user / mock_app / mock_secret)
if (
    ($_SERVER['HTTP_API_USERNAME'] ?? '') !== 'mock_user'
    || ($_SERVER['HTTP_API_APP'] ?? '') !== 'mock_app'
    || ($_SERVER['HTTP_API_SECRET'] ?? '') !== 'mock_secret'
) {
    resp(['error' => 1, 'message' => 'Invalid credentials'], 401);
}

// get-token không cần auth-token
if ($path === '/api/agency/get-token') {
    if ($method !== 'POST') {
        resp(['error' => 1, 'message' => 'Method not allowed. Use POST.'], 405);
    }
    resp(['error' => 0, 'auth-token' => $GLOBALS['token'] ?? 'MOCK.JWT.TOKEN']);
}

// Các endpoint còn lại cần auth-token
if (($_SERVER['HTTP_AUTH_TOKEN'] ?? '') === '') {
    resp(['error' => 9999, 'message' => 'Auth Token không tồn tại.'], 401);
}

switch ($path) {
    case '/api/agency/get-info':
        resp([
            'error' => 0,
            'data'  => ['credit' => 5000000, 'agency_name' => 'Mock Agency'],
        ]);
        break;

    case '/api/agency/get-product':
        resp([
            'error' => 0,
            'products' => [
                'vps' => [
                    [
                        'group_product_name' => 'VPS VMware SSD',
                        'product' => [
                            [
                                'product_id' => 101,
                                'name'       => 'PLATIUM (1-1-20)',
                                'special'    => 0,
                                'cpu'        => 1,
                                'ram'        => 2048,
                                'disk'       => 20,
                                'ip'         => '1 IPv4 Riêng',
                                'os'         => 'Windows/Linux',
                                'bandwidth'  => 'Không giới hạn',
                                'pricing'    => [
                                    'monthly'       => ['amount' => 45000, 'billing_cycle' => '1 Tháng'],
                                    'quarterly'     => ['amount' => 135000, 'billing_cycle' => '3 Tháng'],
                                    'semi_annually' => ['amount' => 270000, 'billing_cycle' => '6 Tháng'],
                                    'annually'      => ['amount' => 540000, 'billing_cycle' => '12 Tháng'],
                                ],
                            ],
                            [
                                'product_id' => 102,
                                'name'       => 'GOLD (2-2-40)',
                                'special'    => 0,
                                'cpu'        => 2,
                                'ram'        => 4096,
                                'disk'       => 40,
                                'ip'         => '1 IPv4 Riêng',
                                'os'         => 'Windows/Linux',
                                'bandwidth'  => 'Không giới hạn',
                                'pricing'    => [
                                    'monthly'   => ['amount' => 90000, 'billing_cycle' => '1 Tháng'],
                                    'quarterly' => ['amount' => 270000, 'billing_cycle' => '3 Tháng'],
                                ],
                            ],
                        ],
                    ],
                ],
                'addon_vps' => [
                    [
                        'group_product_name' => 'Addon VPS',
                        'product' => [
                            [
                                'product_id' => 1, 'name' => 'Addon CPU', 'cpu' => 1, 'ram' => 0, 'disk' => 0,
                                'type_addon' => 'addon_cpu',
                                'pricing' => ['monthly' => ['amount' => 26250, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id' => 2, 'name' => 'Addon RAM', 'cpu' => 0, 'ram' => 1, 'disk' => 0,
                                'type_addon' => 'addon_ram',
                                'pricing' => ['monthly' => ['amount' => 26250, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id' => 3, 'name' => 'Addon Disk', 'cpu' => 0, 'ram' => 0, 'disk' => 10,
                                'type_addon' => 'addon_disk',
                                'pricing' => ['monthly' => ['amount' => 15000, 'billing_cycle' => '1 Tháng']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        break;

    case '/api/agency/get-list-os':
        resp([
            'error'  => 0,
            'os-vps' => [
                ['os-id' => 1, 'os-name' => 'Ubuntu 22.04 LTS', 'os-icon' => ''],
                ['os-id' => 2, 'os-name' => 'Ubuntu 24.04 LTS', 'os-icon' => ''],
                ['os-id' => 4, 'os-name' => 'Windows Server 2022', 'os-icon' => ''],
            ],
        ]);
        break;

    case '/api/agency/order/create-order':
        if (empty($json['product-id'])) {
            resp(['error' => 1, 'message' => 'product-id is required'], 400);
        }
        $qty = max(1, (int)($json['quantity'] ?? 1));
        $data = [];
        // Sinh mã VPS duy nhất theo thời gian để tránh trùng UNIQUE khi test lặp lại
        $seq = (int)round(microtime(true) * 1000) % 100000;
        for ($i = 1; $i <= $qty; $i++) {
            $data[] = [
                'vps-id'        => 'MOCK-VPS-' . $seq . '-' . $i,
                'date_create'   => '2026-03-18T10:00:00.000Z',
                'next_due_date' => '2026-04-18T10:00:00.000Z',
                'vps-status'    => 'progressing',
                'is-special'    => 0,
                'ip'            => '103.56.123.' . (40 + $i),
                'username'      => 'root',
                'password'      => 'MockPass@123',
            ];
        }
        resp([
            'error'   => 0,
            'message' => '',
            'credit'  => 850000,
            'total'   => 150000,
            'data'    => $data,
        ]);
        break;

    case '/api/agency/vps/get-list-vps':
        resp([
            'error' => 0,
            'data'  => [
                [
                    'vps-id' => 'MOCK-VPS-1', 'order-code' => 'MOCK-ORD-1', 'product-id' => '101',
                    'ip' => '103.56.123.41', 'vps-status' => 'on', 'cpu' => 1, 'ram' => 2048, 'disk' => 20,
                    'billing-cycle' => 'monthly', 'day_left' => 25,
                    'date_create' => '2026-03-01T00:00:00.000Z', 'next_due_date' => '2026-04-01T00:00:00.000Z',
                    'intro' => 'VPS VMware SSD',
                ],
            ],
            'total' => 1,
            'page'  => 1,
        ]);
        break;

    case '/api/agency/vps/get-info-vps':
        $ids = $_GET['vps-id'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        if (!$ids) {
            resp(['error' => 1, 'message' => 'vps-id is required'], 400);
        }
        $out = [];
        foreach ($ids as $id) {
            $out[] = [
                'vps-id' => (string)$id, 'order-code' => 'MOCK-ORD-1', 'product-id' => '101',
                'main-ipv4' => '103.56.123.41', 'ip' => '103.56.123.41',
                'username' => 'root', 'password' => 'MockPass@123',
                'vps-status' => 'on', 'os' => 'Ubuntu 22.04 LTS',
                'cpu' => 1, 'ram' => 2048, 'disk' => 20,
                'text-config' => '1 vCPU / 2GB RAM / 20GB SSD', 'type-vps' => 'kvm',
                'billing-cycle' => 'monthly', 'day_left' => 25,
                'date_create' => '2026-03-01T00:00:00.000Z', 'next_due_date' => '2026-04-01T00:00:00.000Z',
                'intro' => '',
            ];
        }
        resp(['error' => 0, 'data' => $out]);
        break;

    case '/api/agency/vps/action-vps':
        $action = (string)($json['action'] ?? '');
        if (!in_array($action, ['start', 'stop', 'restart', 'confirm-rebuild-vps', 'renew-vps', 'addon-vps'], true)) {
            resp(['error' => 1, 'message' => 'Unknown action: ' . $action], 400);
        }
        $messages = [
            'start'              => 'VPS started successfully',
            'stop'               => 'VPS stopped successfully',
            'restart'            => 'VPS restarted successfully',
            'confirm-rebuild-vps' => 'VPS rebuild started',
            'renew-vps'          => 'VPS renewed successfully',
            'addon-vps'          => 'VPS upgraded successfully',
        ];
        resp(['error' => 0, 'message' => $messages[$action]]);
        break;
}

resp(['error' => 1, 'message' => 'Endpoint not found: ' . $path], 404);
