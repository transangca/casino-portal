<?php
namespace App\Services;

/**
 * Đặc tả API (machine-readable) + tài liệu HTML cho dev.
 */
class ApiDocService
{
    /**
     * Trả về đặc tả OpenAPI-lite.
     */
    public static function spec(): array
    {
        return [
            'name'        => 'PGSoft Casino Gateway API',
            'version'     => '1.0.0',
            'base_url'    => url('/api'),
            'auth'        => [
                'type'   => 'header',
                'header' => 'X-API-Key',
                'format' => 'pk_live_<prefix>_<secret>',
                'note'   => 'Key được cấp sau khi mua gói API. Gửi kèm mọi request.',
            ],
            'response'    => [
                'success' => ['success' => true, 'data' => 'object', 'meta' => 'object'],
                'error'   => ['success' => false, 'error' => ['code' => 'string', 'message' => 'string']],
            ],
            'endpoints'   => [
                [
                    'method'      => 'GET',
                    'path'        => '/api/ping',
                    'description' => 'Kiểm tra kết nối + thông tin quota',
                    'params'      => [],
                    'response'    => ['pong' => 'boolean', 'server' => 'string', 'quota' => 'object'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/games',
                    'description' => 'Danh sách game (lọc + phân trang)',
                    'params'      => [
                        'provider'  => ['type' => 'string', 'desc' => 'pgsoft | internal'],
                        'type'      => ['type' => 'string', 'desc' => 'slot | live | card | table'],
                        'search'    => ['type' => 'string', 'desc' => 'Tìm theo tên/slug'],
                        'featured'  => ['type' => 'bool'],
                        'hot'       => ['type' => 'bool'],
                        'new'       => ['type' => 'bool'],
                        'limit'     => ['type' => 'int', 'default' => 50, 'max' => 200],
                        'page'      => ['type' => 'int', 'default' => 1],
                    ],
                    'response'    => ['games' => 'array'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/game',
                    'description' => 'Chi tiết 1 game',
                    'params'      => ['slug' => ['type' => 'string', 'required' => true]],
                    'response'    => ['game' => 'object'],
                ],
                [
                    'method'      => 'POST',
                    'path'        => '/api/launch',
                    'description' => 'Khởi tạo phiên chơi, trả launch URL + operator token',
                    'body'        => ['slug' => ['type' => 'string', 'required' => true]],
                    'response'    => [
                        'session_id'     => 'string',
                        'session_token'  => 'string',
                        'operator_token' => 'string',
                        'launch_url'     => 'string',
                        'balance'        => 'number',
                        'expires_in'     => 'int (giây)',
                    ],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/wallet',
                    'description' => 'Số dư hiện tại',
                    'response'    => ['balance' => 'number', 'currency' => 'string'],
                ],
                [
                    'method'      => 'POST',
                    'path'        => '/api/wallet/change',
                    'description' => 'Cập nhật số dư (bet / win / refund) — dùng cho game H5 tự host',
                    'body'        => [
                        'operator_token' => ['type' => 'string', 'required' => true],
                        'type'           => ['type' => 'string', 'desc' => 'bet | win | refund'],
                        'amount'         => ['type' => 'number', 'desc' => 'Số tiền (luôn dương)'],
                        'roundId'        => ['type' => 'string', 'optional' => true],
                    ],
                    'response'    => ['balance' => 'number'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/packages',
                    'description' => 'Danh sách gói chơi',
                    'response'    => ['packages' => 'array'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/history',
                    'description' => 'Lịch sử cược + giao dịch',
                    'params'      => ['limit' => ['type' => 'int', 'default' => 50]],
                    'response'    => ['rounds' => 'array', 'transactions' => 'array'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/user',
                    'description' => 'Thông tin tài khoản',
                    'response'    => ['user' => 'object'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/phone/check',
                    'description' => 'Tra cứu thông tin 1 số điện thoại VN (tên, CCCD, địa chỉ, nhà mạng, link Facebook)',
                    'params'      => ['phone' => ['type' => 'string', 'required' => true, 'desc' => 'SĐT 10 số, VD: 0912345678']],
                    'response'    => [
                        'phone'      => 'string',
                        'carrier'    => 'string',
                        'full_name'  => 'string',
                        'id_card'    => 'string',
                        'address'    => 'string',
                        'fb_url'     => 'string',
                        'google_url' => 'string',
                        'status'     => 'string (found | partial)',
                        'cost'       => 'number',
                        'balance'    => 'number',
                    ],
                ],
                [
                    'method'      => 'POST',
                    'path'        => '/api/phone/batch',
                    'description' => 'Tra cứu hàng loạt tối đa 100 số điện thoại',
                    'body'        => ['phones' => ['type' => 'array', 'required' => true, 'desc' => 'Mảng số điện thoại']],
                    'response'    => [
                        'results' => 'array',
                        'count'   => 'int',
                        'cost'    => 'number',
                        'balance' => 'number',
                    ],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/phone/history',
                    'description' => 'Lịch sử tra cứu của API key này',
                    'params'      => ['limit' => ['type' => 'int', 'default' => 50]],
                    'response'    => ['history' => 'array', 'count' => 'int'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/accounts/products',
                    'description' => 'Danh sách sản phẩm tài khoản & nick (lọc + phân trang)',
                    'params'      => [
                        'q'          => ['type' => 'string', 'desc' => 'Tìm theo tên sản phẩm'],
                        'categoryId' => ['type' => 'int', 'desc' => 'Lọc theo ID danh mục'],
                        'page'       => ['type' => 'int', 'default' => 0],
                        'size'       => ['type' => 'int', 'default' => 50, 'max' => 100],
                        'sort'       => ['type' => 'string', 'default' => 'newest', 'desc' => 'newest | price_asc | price_desc'],
                    ],
                    'response'    => ['products' => 'array', 'count' => 'int'],
                ],
                [
                    'method'      => 'POST',
                    'path'        => '/api/accounts/order',
                    'description' => 'Mua tài khoản & nick — trừ ví và giao hàng (AUTO) hoặc chờ admin giao (MANUAL)',
                    'body'        => [
                        'productId' => ['type' => 'int', 'required' => true],
                        'quantity'  => ['type' => 'int', 'default' => 1, 'min' => 1],
                    ],
                    'response'    => [
                        'id'             => 'int',
                        'status'         => 'string (DELIVERED | PENDING_DELIVERY)',
                        'productName'    => 'string',
                        'totalAmount'    => 'number',
                        'quantity'       => 'int',
                        'deliveredItems' => 'array',
                        'balance'        => 'number',
                    ],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/accounts/orders',
                    'description' => 'Lịch sử đơn tài khoản & nick',
                    'params'      => ['limit' => ['type' => 'int', 'default' => 20]],
                    'response'    => ['orders' => 'array', 'count' => 'int'],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/accounts/detail',
                    'description' => 'Chi tiết 1 đơn (kèm hàng đã giao)',
                    'params'      => ['id' => ['type' => 'int', 'required' => true]],
                    'response'    => [
                        'id'             => 'int',
                        'productName'    => 'string',
                        'status'         => 'string',
                        'totalAmount'    => 'number',
                        'quantity'       => 'int',
                        'deliveredItems' => 'array',
                        'deliveryNote'   => 'string | null',
                        'createdAt'      => 'string',
                    ],
                ],
                [
                    'method'      => 'GET',
                    'path'        => '/api/docs',
                    'description' => 'Đặc tả API (JSON)',
                    'response'    => ['docs' => 'object'],
                ],
            ],
            'errors'       => [
                'AUTH_INVALID' => 'API key thiếu / sai / bị khóa / hết hạn',
                'NO_PACKAGE'   => 'Tài khoản chưa có gói chơi',
                'WALLET_ERROR' => 'Số dư không đủ hoặc lỗi ledger',
                'INSUFFICIENT_BALANCE' => 'Số dư không đủ để thực hiện dịch vụ',
                'INVALID_PARAM' => 'Tham số không hợp lệ',
                'GAME_NOT_FOUND' => 'Không tìm thấy game',
                'PRODUCT_NOT_FOUND' => 'Không tìm thấy sản phẩm',
                'OUT_OF_STOCK'  => 'Sản phẩm tạm hết hàng',
                'NOT_FOUND'    => 'Endpoint không tồn tại',
            ],
            'examples'     => [
                'curl' => "curl -X GET '".url('/api/games')."?limit=5' \\\n  -H 'X-API-Key: pk_live_xxx_yyy'",
                'php'  => "\$ch = curl_init('".url('/api/launch')."');\n"
                    . "curl_setopt(\$ch, CURLOPT_POST, true);\n"
                    . "curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(['slug' => 'mahjong-ways']));\n"
                    . "curl_setopt(\$ch, CURLOPT_HTTPHEADER, ['X-API-Key: pk_live_xxx_yyy', 'Content-Type: application/json']);",
            ],
        ];
    }
}
