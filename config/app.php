<?php
/**
 * Cấu hình ứng dụng.
 * Đọc từ biến môi trường, có fallback để chạy local nhanh.
 */

return [
    'app' => [
        'name'      => getenv('APP_NAME') ?: 'Casino Portal',
        'env'       => getenv('APP_ENV') ?: 'production',
        'debug'     => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
        'url'       => rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'),
        'timezone'  => 'Asia/Ho_Chi_Minh',
        'key'       => getenv('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)),
    ],

    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME') ?: 'casino_platform',
        'user'     => getenv('DB_USER') ?: 'root',
        'pass'     => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'CASINO_SESSID',
        'lifetime' => 60 * 60 * 24 * 7,
    ],

    'wallet' => [
        // Số tiền tối thiểu cho 1 phiên chơi (đồng)
        'min_play_amount'   => 0,
        // Thời gian heartbeat từ client (giây)
        'heartbeat_interval'=> 30,
        // Thời gian chờ bị kick khỏi game (giây) nếu không có heartbeat
        'session_timeout'    => 120,
        // Tỷ lệ khuyến mãi nạp tiền
        'deposit_bonus_rate' => 0.10,
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
        'csrf_lifetime'      => 7200,
        'password_min'       => 8,
    ],

    /**
    50|     * Dịch vụ Bank ảo — API HeyBank v2 (https://heybank.pro/api/v2).
     *
     * ACCESS_KEY lấy từ bot @WsVenderBot bằng lệnh /mykey.
     * Có thể cấu hình qua biến môi trường hoặc trong Admin → Bank ảo
    55|     * (bản ghi trong bảng `settings` được mã hóa AES-256-GCM).
     */
    'heybank' => [
        'base_url'   => getenv('HEYBANK_BASE_URL') ?: 'https://heybank.pro/api/v2',
        'access_key' => getenv('HEYBANK_ACCESS_KEY') ?: '',
        'service_on' => filter_var(getenv('HEYBANK_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ bán tài khoản AI — API đối tác GenzShop (https://genzshop.vn/api/partner/v1).
     *
     *  - GET  /products.php  → danh sách sản phẩm + giá đại lý + tồn kho
     *  - GET  /balance.php   → số dư ví của tài khoản gắn với API key
     *  - POST /purchase.php  → mua hàng, trả về deliveredAccounts
     *
     * Lưu ý: endpoint BẮT BUỘC kèm đuôi .php và phải gọi qua HTTPS.
     * API key có thể cấu hình trong Admin → Tài khoản AI (được mã hoá AES-256-GCM).
     */
    'genzshop' => [
        'base_url'   => getenv('GENZSHOP_BASE_URL') ?: 'https://genzshop.vn/api/partner/v1',
        'api_key'    => getenv('GENZSHOP_API_KEY') ?: '',
        'service_on' => filter_var(getenv('GENZSHOP_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ bán tài khoản & nick — API đối tác Vuavia (https://api.vuavia.io/api/v2).
     *
     *  - GET  /wallet/balance  → số dư ví đối tác
     *  - GET  /products        → danh sách sản phẩm (lọc q/categoryId, phân trang)
     *  - POST /orders          → đặt hàng (AUTO giao ngay / MANUAL chờ admin giao)
     *  - GET  /orders          → lịch sử đơn hàng
     *  - GET  /orders/{id}     → chi tiết đơn + hàng đã giao
     *
     * Xác thực: header `X-Api-Key: vav_xxx` (36 ký tự).
     * API key có thể cấu hình trong Admin → Tài khoản & Nick (được mã hoá AES-256-GCM).
     */
    'vuavia' => [
        'base_url'   => getenv('VUAVIA_BASE_URL') ?: 'https://api.vuavia.io/api/v2',
        'api_key'    => getenv('VUAVIA_API_KEY') ?: '',
        'service_on' => filter_var(getenv('VUAVIA_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ thuê số nhận OTP — API ViOTP v1 (https://api.viotp.net).
     *
     *  - GET  /v1/balance           → số dư tài khoản (VND)
     *  - GET  /v1/carriers          → danh sách nhà mạng
     *  - GET  /v1/services          → danh sách dịch vụ (id, name, price)
     *  - POST /v1/rentals           → thuê số mới
     *  - POST /v1/rentals/rerent    → thuê lại số cũ
     *  - GET  /v1/rentals/{id}      → trạng thái & mã OTP (poll mỗi 5s)
     *
     * Xác thực: header `Authorization: Bearer {token}`.
     * Token có thể cấu hình trong Admin → Thuê số OTP (được mã hoá AES-256-GCM).
     */
    'viotp' => [
        'base_url'   => getenv('VIOTP_BASE_URL') ?: 'https://api.viotp.net',
        'token'      => getenv('VIOTP_TOKEN') ?: '',
        'service_on' => filter_var(getenv('VIOTP_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ Auto Bank — cổng thanh toán tự động (https://api.sieuthicode.net/).
     *
     *  - V1: dữ liệu gốc từ ngân hàng (chỉ cần Token)
     *  - V3: dữ liệu chuẩn hoá (cần PASSWORD / STK / Token)
     *
     * Token / mật khẩu ngân hàng mã hoá AES-256-GCM trước khi lưu vào bảng `settings`
     * hoặc bảng `auto_bank_accounts`.
     */
    'autobank' => [
        'base_url'   => getenv('AUTOBANK_BASE_URL') ?: 'https://api.sieuthicode.net',
        'service_on' => filter_var(getenv('AUTOBANK_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
        'cron_token' => getenv('AUTOBANK_CRON_TOKEN') ?: '',
    ],

    /**
     * Dịch vụ Auto MXH — API mở khóa tài khoản (https://trungtammmo.vn/api/external/automxh).
     *
     * apikey là key riêng của tài khoản đối tác, dùng chung hệ key với SMM.
     * Có thể cấu hình qua biến môi trường hoặc trong Admin → Auto MXH
     * (bản ghi trong bảng `settings` được mã hoá AES-256-GCM).
     */
    'automxh' => [
        'base_url'   => getenv('AUTOMXH_BASE_URL') ?: 'https://trungtammmo.vn/api/external/automxh',
        'api_key'    => getenv('AUTOMXH_API_KEY') ?: '',
        'service_on' => filter_var(getenv('AUTOMXH_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ SMM — mua like / view / comment / share / follow
     * (https://trungtammmo.vn/api/external/smm).
     *
     *  - GET  /services   → dịch vụ + giá bán trên web
     *  - GET  /categories → card nhóm dịch vụ
     *  - POST /quote      → tính giá (không tạo đơn)
     *  - POST /order      → tạo đơn + trừ ví chính
     *  - GET  /status     → trạng thái 1 hoặc nhiều đơn
     *
     * apikey là key riêng của tài khoản đối tác, dùng chung hệ key với Auto MXH.
     * Có thể cấu hình qua biến môi trường hoặc trong Admin → SMM
     * (bản ghi trong bảng `settings` được mã hoá AES-256-GCM).
     */
    'smm' => [
        'base_url'   => getenv('SMM_BASE_URL') ?: 'https://trungtammmo.vn/api/external/smm',
        'api_key'    => getenv('SMM_API_KEY') ?: '',
        'service_on' => filter_var(getenv('SMM_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Dịch vụ VPS VMware — XVPS Agency API.
     *
     * Mọi request gửi 3 header bắt buộc: api-username, api-app, api-secret.
     * Ngoài endpoint `get-token`, mọi endpoint cần thêm header `auth-token` (JWT, TTL 8h).
     * Có thể cấu hình qua biến môi trường hoặc trong Admin → VPS
     * (bản ghi trong bảng `settings` được mã hoá AES-256-GCM).
     */
    'xvps' => [
        'base_url'     => getenv('XVPS_BASE_URL') ?: 'https://api.xvps.vn',
        'api_username' => getenv('XVPS_API_USERNAME') ?: '',
        'api_app'      => getenv('XVPS_API_APP') ?: '',
        'api_secret'   => getenv('XVPS_API_SECRET') ?: '',
        'service_on'   => filter_var(getenv('XVPS_SERVICE_ON') ?: '1', FILTER_VALIDATE_BOOL),
    ],

    /**
     * Cấu hình các sảnh game.
     *
     * Mỗi sảnh dùng chuẩn Seamless Wallet (Transfer Wallet):
     *  - game_host : host chứa game shell của nhà phát hành
     *  - operator_token / secret_key : do nhà phát hành cấp khi đăng ký
     *    → khi có, hệ thống bật chế độ chơi bằng tiền thật (btt=1)
     *    → khi chưa có, hệ thống chạy chế độ demo của nhà phát hành (btt=2)
     *
     * Giá trị này có thể override trong bảng `providers` (cột `config`).
     */
    'providers' => [

        // ---------- PGSoft (Pocket Games Soft) ----------
        'pgsoft' => [
            'name'           => 'PGSoft',
            'game_host'      => getenv('PGSOFT_GAME_HOST') ?: 'https://m.pg-demo.com',
            'game_url'       => getenv('PGSOFT_GAME_URL') ?: 'https://m.pg-demo.com/{game_id}/index.html',
            'api_base'       => getenv('PGSOFT_API_BASE') ?: 'https://public-api.pg-demo.com',
            'icon_base'      => 'https://public.pg-demo.com/pages/static/image/en/Small_Icon/{game_id}/SI-{hash}.png',
            'operator_token' => getenv('PGSOFT_OPERATOR_TOKEN') ?: '',
            'secret_key'     => getenv('PGSOFT_SECRET_KEY') ?: '',
            'bet_type'       => 2,
        ],

        // ---------- KingMidas Games ----------
        'king-midas-games' => [
            'name'           => 'KingMidas',
            'game_host'      => getenv('KM_GAME_HOST') ?: 'https://m.kingmidasgames.net',
            'game_url'       => getenv('KM_GAME_URL') ?: 'https://m.kingmidasgames.net/{game_id}/index.html',
            'api_base'       => getenv('KM_API_BASE') ?: '',
            'icon_base'      => 'https://publiccdn.kingmidasgames.net/500x500images/KingMidas/{slug}_500x500_en.jpg',
            'operator_token' => getenv('KM_OPERATOR_TOKEN') ?: '',
            'secret_key'     => getenv('KM_SECRET_KEY') ?: '',
            'bet_type'       => 2,
        ],

        // ---------- JILI ----------
        'jili' => [
            'name'           => 'JILI',
            'game_host'      => getenv('JILI_GAME_HOST') ?: 'https://m.jili.com',
            'game_url'       => getenv('JILI_GAME_URL') ?: 'https://m.jili.com/{game_id}/index.html',
            'api_base'       => getenv('JILI_API_BASE') ?: '',
            'icon_base'      => 'https://img.jili.com/{slug}.png',
            'operator_token' => getenv('JILI_OPERATOR_TOKEN') ?: '',
            'secret_key'     => getenv('JILI_SECRET_KEY') ?: '',
            'bet_type'       => 2,
        ],

        // ---------- Pragmatic Play ----------
        'pragmatic-play' => [
            'name'           => 'Pragmatic Play',
            'game_host'      => getenv('PP_GAME_HOST') ?: 'https://demoportal.pragmaticplay.com',
            'game_url'       => getenv('PP_GAME_URL') ?: 'https://demoportal.pragmaticplay.com/{game_id}/index.html',
            'api_base'       => getenv('PP_API_BASE') ?: '',
            'icon_base'      => 'https://www.pragmaticplay.com/content/Assets/images/{slug}.png',
            'operator_token' => getenv('PP_OPERATOR_TOKEN') ?: '',
            'secret_key'     => getenv('PP_SECRET_KEY') ?: '',
            'bet_type'       => 2,
        ],

        // ---------- CQ9 ----------
        'cq9' => [
            'name'           => 'CQ9',
            'game_host'      => getenv('CQ9_GAME_HOST') ?: 'https://game.cq9.games',
            'game_url'       => getenv('CQ9_GAME_URL') ?: 'https://game.cq9.games/{game_id}/index.html',
            'api_base'       => getenv('CQ9_API_BASE') ?: '',
            'icon_base'      => 'https://game.cq9.games/image/{slug}.png',
            'operator_token' => getenv('CQ9_OPERATOR_TOKEN') ?: '',
            'secret_key'     => getenv('CQ9_SECRET_KEY') ?: '',
            'bet_type'       => 2,
        ],

        // ---------- Deno Casino (CQ9 Live / MT Studio) ----------
        // Lobby là React SPA, danh sách bàn đến qua WebSocket /api/lobby/ws.
        // Mỗi bàn mở bằng deep-link /{category}/?token=..&tableId=XXX
        'deno-casino' => [
            'name'        => 'Deno Casino',
            'game_host'   => 'https://gcdemo-lobby.cqgame.me',
            'lobby_url'   => 'https://gcdemo-lobby.cqgame.me/lobby/?token=eyJUeXAiOiJKV1QiLCJBbGciOiJIUzI1NiIsIkN0eSI6IiJ9.eyJSb2xlIjoiNmFlODBkZTk5ZjhiNTAxMDY1NDhlNWZhIiwiZXhwIjoxNzkzNTkyODY5LCJpYXQiOjE3OTM1OTI4MDl9.cxyo3Fm9aiq9i0uh6JoljzeK7_ApepLwLa8f15LLkVk&language=en&dollarsign=Y&app=N&detect=N',
            'lobby_token' => getenv('DENO_LOBBY_TOKEN') ?: 'eyJUeXAiOiJKV1QiLCJBbGciOiJIUzI1NiIsIkN0eSI6IiJ9.eyJSb2xlIjoiNmFlODBkZTk5ZjhiNTAxMDY1NDhlNWZhIiwiZXhwIjoxNzkzNTkyODY5LCJpYXQiOjE3OTM1OTI4MDl9.cxyo3Fm9aiq9i0uh6JoljzeK7_ApepLwLa8f15LLkVk',
            'api_base'    => 'https://gcdemo-lobby.cqgame.me',
            'operator_token' => '',
            'secret_key'  => '',
            'bet_type'    => 2,
        ],
    ],
];
