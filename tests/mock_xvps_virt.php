<?php
/**
 * Mock API StoreVPS / Virtualizor (XVPS) — dùng cho test.
 *
 * Chạy: php -S 127.0.0.1:8789 tests/mock_xvps_virt.php
 *
 * Mô phỏng đúng contract của https://api.xvps.vn/api/virt/* :
 *   - mọi request cần 3 header: api-username, api-app, api-secret
 *   - mọi request (trừ get-token) cần header auth-token
 *   - thành công: error = 0 ; lỗi: error != 0 kèm message
 */

const VIRT_TOKEN = 'MOCK.VIRT.JWT.TOKEN';

$body = file_get_contents('php://input');
$json = json_decode((string)$body, true);
if (!is_array($json)) {
    $json = [];
}

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('Content-Type: application/json');

function vresp(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function verr(string $msg, int $code = 1, int $status = 400): void
{
    vresp(['error' => $code, 'message' => $msg], $status);
}

// ---------- Kiểm tra headers xác thực ----------
foreach (['api-username', 'api-app', 'api-secret'] as $h) {
    if (($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $h))] ?? '') === '') {
        verr('Headers api-username, api-app, api-secret are required', 1, 400);
    }
}

if (
    ($_SERVER['HTTP_API_USERNAME'] ?? '') !== 'mock_user'
    || ($_SERVER['HTTP_API_APP'] ?? '') !== 'mock_app'
    || ($_SERVER['HTTP_API_SECRET'] ?? '') !== 'mock_secret'
) {
    verr('Invalid credentials', 1, 401);
}

// ---------- get-token (không cần auth-token) ----------
if ($path === '/api/virt/get-token') {
    if ($method !== 'POST') {
        verr('Method not allowed. Use POST.', 1, 405);
    }
    vresp(['error' => 0, 'auth-token' => VIRT_TOKEN]);
}

// ---------- Các endpoint còn lại cần auth-token ----------
if (($_SERVER['HTTP_AUTH_TOKEN'] ?? '') === '') {
    verr('Auth Token không tồn tại.', 9999, 401);
}

if (($_SERVER['HTTP_AUTH_TOKEN'] ?? '') !== VIRT_TOKEN) {
    verr('Auth Token không hợp lệ.', 9999, 401);
}

// ---------- Helpers ----------
function virt_vps(string $id = 'MOCK-VZ-1001'): array
{
    return [
        'vps-id'         => $id,
        'order-code'     => 'VZ-ORD-' . str_replace('MOCK-', '', $id),
        'product-id'     => 'vz-101',
        'hostname'       => 'demo-vz-host',
        'description'    => 'Gói VPS StoreVPS 1 vCPU / 2GB RAM / 25GB SSD',
        'ip'             => '103.56.200.11',
        'ipv6'           => '2001:db8::11',
        'username'       => 'root',
        'password'       => 'MockPass@2026',
        'os-name'        => 'Ubuntu 22.04 LTS',
        'cpu'            => 1,
        'ram'            => 2048,
        'disk'           => 25,
        'text-config'    => '1 vCPU / 2048 MB RAM / 25 GB SSD',
        'type-vps'       => 'kvm',
        'billing-cycle'  => 'monthly',
        'day-left'       => 25,
        'vps-status'     => 'running',
        'auto-renew'     => 0,
        'amount'         => 120000,
        'date_create'    => '2026-11-01T00:00:00.000Z',
        'next_due_date'  => '2026-12-01T00:00:00.000Z',
        'ipv4List'       => ['103.56.200.11'],
        'ipv6List'       => ['2001:db8::11'],
    ];
}

switch ($path) {
    // --------------------------------------------------
    case '/api/virt/get-info':
        vresp([
            'error' => 0,
            'data'  => [
                'credit' => 25000000,
                'name'   => 'Mock StoreVPS Agency',
                'scopes' => ['virt.read', 'virt.write'],
                'vps'    => [
                    'total'    => 12,
                    'active'   => 9,
                    'suspended' => 1,
                ],
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/get-product':
        vresp([
            'error'   => 0,
            'message' => '',
            'products' => [
                'vps' => [
                    [
                        'group_product_name' => 'VPS StoreVPS Basic',
                        'product' => [
                            [
                                'product_id'   => 'vz-101',
                                'name'         => 'STARTER (1-2-25)',
                                'display_name' => 'STARTER',
                                'description'  => 'Gói khởi đầu, 1 vCPU / 2GB RAM / 25GB SSD',
                                'type_vps'     => 'kvm',
                                'cpu'          => 1,
                                'ram'          => 2048,
                                'disk'         => 25,
                                'bandwidth'    => 1000,
                                'network_speed' => 1000,
                                'upload_speed' => 1000,
                                'ipv4'         => 1,
                                'ipv6'         => 1,
                                'ipv6_subnet'  => 64,
                                'pricing'      => [
                                    'monthly'       => ['amount' => 120000, 'billing_cycle' => '1 Tháng'],
                                    'quarterly'     => ['amount' => 340000, 'billing_cycle' => '3 Tháng'],
                                    'semi_annually' => ['amount' => 640000, 'billing_cycle' => '6 Tháng'],
                                    'annually'      => ['amount' => 1200000, 'billing_cycle' => '12 Tháng'],
                                ],
                                'locations' => [
                                    ['location_id' => 'loc-hcm', 'code' => 'HCM', 'name' => 'Việt Nam - Hồ Chí Minh', 'surcharge' => 0],
                                    ['location_id' => 'loc-sg',  'code' => 'SG',  'name' => 'Singapore',             'surcharge' => 20000],
                                ],
                            ],
                            [
                                'product_id'   => 'vz-102',
                                'name'         => 'PRO (2-4-60)',
                                'display_name' => 'PRO',
                                'description'  => 'Gói cao cấp, 2 vCPU / 4GB RAM / 60GB SSD',
                                'type_vps'     => 'kvm',
                                'cpu'          => 2,
                                'ram'          => 4096,
                                'disk'         => 60,
                                'bandwidth'    => 2000,
                                'network_speed' => 2000,
                                'upload_speed' => 2000,
                                'ipv4'         => 2,
                                'ipv6'         => 1,
                                'ipv6_subnet'  => 64,
                                'pricing'      => [
                                    'monthly'   => ['amount' => 220000, 'billing_cycle' => '1 Tháng'],
                                    'annually'  => ['amount' => 2200000, 'billing_cycle' => '12 Tháng'],
                                ],
                                'locations' => [
                                    ['location_id' => 'loc-hcm', 'code' => 'HCM', 'name' => 'Việt Nam - Hồ Chí Minh', 'surcharge' => 0],
                                ],
                            ],
                        ],
                    ],
                ],
                'addon_vps' => [
                    [
                        'group_product_name' => 'Addon VPS StoreVPS',
                        'product' => [
                            [
                                'product_id'  => 'vz-ad-cores',
                                'name'        => 'Addon CPU Core',
                                'type_addon'  => 'cores',
                                'unit_value'  => 1,
                                'cpu'         => 1, 'ram' => 0, 'disk' => 0,
                                'pricing'     => ['monthly' => ['amount' => 60000, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id'  => 'vz-ad-ram',
                                'name'        => 'Addon RAM',
                                'type_addon'  => 'ram',
                                'unit_value'  => 1024,
                                'cpu'         => 0, 'ram' => 1024, 'disk' => 0,
                                'pricing'     => ['monthly' => ['amount' => 45000, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id'  => 'vz-ad-disk',
                                'name'        => 'Addon Disk',
                                'type_addon'  => 'disk',
                                'unit_value'  => 10,
                                'cpu'         => 0, 'ram' => 0, 'disk' => 10,
                                'pricing'     => ['monthly' => ['amount' => 30000, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id'  => 'vz-ad-ipv4',
                                'name'        => 'Addon IPv4',
                                'type_addon'  => 'ipv4',
                                'unit_value'  => 1,
                                'cpu'         => 0, 'ram' => 0, 'disk' => 0,
                                'pricing'     => ['monthly' => ['amount' => 50000, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id'  => 'vz-ad-ipv6',
                                'name'        => 'Addon IPv6',
                                'type_addon'  => 'ipv6',
                                'unit_value'  => 1,
                                'cpu'         => 0, 'ram' => 0, 'disk' => 0,
                                'pricing'     => ['monthly' => ['amount' => 25000, 'billing_cycle' => '1 Tháng']],
                            ],
                            [
                                'product_id'  => 'vz-ad-bandwidth',
                                'name'        => 'Addon Bandwidth',
                                'type_addon'  => 'bandwidth',
                                'unit_value'  => 100,
                                'cpu'         => 0, 'ram' => 0, 'disk' => 0,
                                'pricing'     => ['monthly' => ['amount' => 40000, 'billing_cycle' => '1 Tháng']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/get-list-os':
        vresp([
            'error'  => 0,
            'os-vps' => [
                ['os_id' => '1001', 'os_name' => 'Ubuntu 22.04 LTS', 'distro' => 'ubuntu'],
                ['os_id' => '1002', 'os_name' => 'Ubuntu 24.04 LTS', 'distro' => 'ubuntu'],
                ['os_id' => '1003', 'os_name' => 'Debian 12',         'distro' => 'debian'],
                ['os_id' => '1004', 'os_name' => 'CentOS Stream 9',   'distro' => 'centos'],
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/order/create-order':
        if (trim((string)($json['plan_id'] ?? '')) === '') {
            verr('plan_id is required', 1, 400);
        }
        if (trim((string)($json['os_id'] ?? '')) === '') {
            verr('os_id is required', 1, 400);
        }

        $hostname = trim((string)($json['hostname'] ?? ''));
        if ($hostname !== '' && !preg_match('/^[a-z0-9][a-z0-9._\-]{1,61}[a-z0-9]$/', $hostname)) {
            verr('Hostname không hợp lệ', 1, 400);
        }

        $qty = max(1, min(10, (int)($json['quantity'] ?? 1)));
        $seq = (int)round(microtime(true) * 1000) % 100000;

        $orders = [];
        for ($i = 1; $i <= $qty; $i++) {
            $orders[] = [
                'instance_id' => 'MOCK-VZ-' . $seq . '-' . $i,
                'order_code'  => 'VZ-ORD-' . $seq . '-' . $i,
                'status'      => 'creating',
                'amount'      => 120000,
                'hostname'    => $hostname,
            ];
        }

        vresp([
            'error'   => 0,
            'message' => 'Đơn VPS đã được tạo, đang khởi tạo.',
            'data'    => [
                'status'  => 'creating',
                'total'   => 120000 * $qty,
                'balance' => 24880000,
                'orders'  => $orders,
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/get-list-vps':
        vresp([
            'error'      => 0,
            'data'       => [virt_vps()],
            'pagination' => [
                'total' => 1,
                'page'  => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 20),
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/get-info-vps':
        $id = trim((string)($_GET['id'] ?? $_GET['vps_id'] ?? ''));
        if ($id === '') {
            verr('id is required', 1, 400);
        }
        vresp(['error' => 0, 'data' => virt_vps($id)]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/get-status-batch':
        $ids = trim((string)($_GET['ids'] ?? ''));
        if ($ids === '') {
            verr('ids is required', 1, 400);
        }
        $list = array_filter(array_map('trim', explode(',', $ids)));
        if (count($list) > 50) {
            verr('Tối đa 50 ids mỗi lần', 1, 400);
        }

        $out = [];
        foreach ($list as $id) {
            $out[] = [
                'vps-id'     => $id,
                'vps-status' => 'running',
                'ip'         => '103.56.200.11',
            ];
        }
        vresp(['error' => 0, 'data' => $out]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/action-vps':
        $action = trim((string)($json['action'] ?? ''));
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        if (!in_array($action, ['start', 'stop', 'restart', 'poweroff'], true)) {
            verr('Unknown action: ' . $action, 1, 400);
        }
        $messages = [
            'start'    => 'VPS đã khởi động thành công.',
            'stop'     => 'VPS đã tắt thành công.',
            'restart'  => 'VPS đã khởi động lại thành công.',
            'poweroff' => 'VPS đã tắt cứng thành công.',
        ];
        vresp(['error' => 0, 'message' => $messages[$action], 'data' => ['vps-status' => $action === 'stop' || $action === 'poweroff' ? 'stopped' : 'running']]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/rebuild-vps':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        if (trim((string)($json['os_id'] ?? '')) === '') {
            verr('os_id is required', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Đã gửi lệnh cài lại hệ điều hành.',
            'data'    => ['vps-status' => 'rebuilding'],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/change-password':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        $pass = (string)($json['new_password'] ?? '');
        if (strlen($pass) < 10 || strlen($pass) > 64) {
            verr('new_password phải dài 10-64 ký tự.', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Đổi mật khẩu root thành công.',
            'data'    => ['on_boot' => (bool)($json['on_boot'] ?? false)],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/change-hostname':
        $host = trim((string)($json['new_hostname'] ?? ''));
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._\-]{1,61}[a-z0-9]$/', $host)) {
            verr('Hostname không hợp lệ.', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Đổi hostname thành công.',
            'data'    => ['hostname' => $host],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/renew-vps':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        $cycle = trim((string)($json['cycle_id'] ?? ''));
        if (!in_array($cycle, ['monthly', 'quarterly', 'semi_annually', 'annually'], true)) {
            verr('cycle_id không hợp lệ.', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Gia hạn VPS thành công.',
            'data'    => [
                'amount'       => 120000,
                'newExpireAt'  => '2026-12-01T00:00:00.000Z',
                'currentCycle' => $cycle,
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/addon-vps':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        $total = 0;
        foreach (['cpu', 'ram', 'disk', 'ip', 'ipv6', 'bandwidth'] as $k) {
            $total += max(0, (int)($json[$k] ?? 0));
        }
        if ($total <= 0) {
            verr('Cần ít nhất một addon > 0.', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Đã tạo hóa đơn nâng cấp thành công.',
            'data'    => [
                'invoice_id' => 'VZ-INV-' . (int)round(microtime(true) * 1000) % 100000,
                'amount'     => 60000,
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/auto-renew':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        $enabled = (bool)($json['enabled'] ?? false);
        vresp([
            'error'   => 0,
            'message' => $enabled ? 'Đã bật tự động gia hạn.' : 'Đã tắt tự động gia hạn.',
            'data'    => [
                'auto_renew'   => $enabled,
                'current_cycle' => 'monthly',
                'next_bill_at'  => '2026-12-01T00:00:00.000Z',
            ],
        ]);
        break;

    // --------------------------------------------------
    case '/api/virt/vps/cancel-vps':
        if (trim((string)($json['vps_id'] ?? '')) === '') {
            verr('vps_id is required', 1, 400);
        }
        vresp([
            'error'   => 0,
            'message' => 'Đã hủy dịch vụ VPS thành công.',
            'data'    => [
                'status'       => 'cancelled',
                'cancelled_at' => '2026-11-01T00:00:00.000Z',
            ],
        ]);
        break;
}

verr('Endpoint not found: ' . $path, 404, 404);
