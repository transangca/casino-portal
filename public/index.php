<?php
/**
 * Entry point chính của ứng dụng.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ---------- Autoload ----------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';

// ---------- Config ----------
$appConfig = config('app');
date_default_timezone_set($appConfig['timezone']);
mb_internal_encoding('UTF-8');

// ---------- Error handling ----------
error_reporting(E_ALL);
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e) {
    logger('UNCAUGHT: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'error');
    if (config('app.debug')) {
        http_response_code(500);
        echo '<pre style="background:#1a1a1a;color:#f66;padding:20px;font-size:13px">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } else {
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Lỗi</title>';
        echo '<div style="font-family:system-ui;padding:60px;text-align:center;background:#0b0b12;color:#eee">';
        echo '<h1 style="font-size:3rem;margin:0">500</h1><p>Đã xảy ra lỗi. Vui lòng thử lại sau.</p>';
        echo '</div>';
    }
});

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(config('session.name'));
    session_start();
}

// ---------- Đa ngôn ngữ ----------
App\Services\I18n::boot();

// ---------- Mã giới thiệu (CSO) ----------
// Bắt mọi mã giới thiệu từ link (ref / r / ref_code / cso) và lưu lại
// để tự động điền vào form đăng ký kể cả khi người dùng mở link ở thiết bị/tab khác.
$refCode = strtoupper(trim((string)($_GET['ref'] ?? $_GET['r'] ?? $_GET['ref_code'] ?? $_GET['cso'] ?? '')));
if ($refCode !== '' && preg_match('/^[A-Z0-9]{4,16}$/', $refCode)) {
    $_SESSION['ref_code'] = $refCode;
    setcookie('ref_code', $refCode, [
        'expires'  => time() + 30 * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
} elseif (!isset($_SESSION['ref_code']) && !empty($_COOKIE['ref_code'])) {
    $cookieRef = strtoupper(trim((string)$_COOKIE['ref_code']));
    if (preg_match('/^[A-Z0-9]{4,16}$/', $cookieRef)) {
        $_SESSION['ref_code'] = $cookieRef;
    }
}

// ---------- Bảo mật (chống DDoS / hacker / gian lận) ----------
App\Services\SecurityGuard::boot();

// ---------- Chế độ bảo trì ----------
if (!\App\Services\MaintenanceService::enabled()) {
    // thực thi bình thường
} else {
    \App\Services\MaintenanceService::render();
}

// ---------- Headers ----------
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

// ---------- Routes ----------
$router = new App\Core\Router();

// --- Public ---
$router->get('/',             'HomeController@index');
$router->get('/games',        'GameController@index');
$router->get('/games/{slug}', 'GameController@show');
$router->get('/search',       'GameController@index');

// --- Sảnh & danh mục ---
$router->get('/lobbies',            'LobbyController@index');
$router->get('/lobbies/{code}',     'LobbyController@show');
$router->get('/category/{code}',    'LobbyController@category');

// --- Tin tức ---
$router->get('/news',           'NewsController@index');
$router->get('/news/{slug}',     'NewsController@show');
$router->get('/events',         'NewsController@index');

// --- Theme & ngôn ngữ (đổi qua GET, lưu cookie + tài khoản) ---
$router->get('/set-theme',   'SettingController@setTheme');
$router->get('/set-language','SettingController@setLanguage');

// --- Auth ---
$router->get('/login',      'AuthController@showLogin');
$router->post('/login',     'AuthController@login');
$router->get('/register',   'AuthController@showRegister');
$router->post('/register',  'AuthController@register');
$router->get('/logout',     'AuthController@logout');

// --- Đăng nhập mạng xã hội ---
$router->get('/auth/social/{provider}',          'SocialAuthController@redirect');
$router->get('/auth/social/{provider}/callback', 'SocialAuthController@callback');
$router->post('/auth/social/telegram',           'SocialAuthController@telegram');

// --- Play (cần đăng nhập) ---
$router->group('/play', ['auth'], function (App\Core\Router $r) {
    $r->get('/embed/{token}', 'PlayController@embed');
});
$router->get('/play/{slug}', 'PlayController@show');
$router->post('/play/resolve',   'PlayController@resolve');
$router->post('/play/heartbeat', 'PlayController@heartbeat');
$router->post('/play/close',     'PlayController@close');

// --- Wallet (cần đăng nhập) ---
$router->group('', ['auth'], function (App\Core\Router $r) {
    $r->get('/wallet',              'WalletController@index');
    $r->get('/wallet/deposit',      'WalletController@showDeposit');
    $r->post('/wallet/deposit',     'WalletController@submitDeposit');
    $r->post('/wallet/deposit/qr',  'WalletController@createQrCode');
    $r->post('/wallet/deposit/card', 'WalletController@redeemCard');
    $r->get('/wallet/withdraw',     'WalletController@showWithdraw');
    $r->post('/wallet/withdraw',    'WalletController@submitWithdraw');
    $r->post('/wallet/giftcode',    'WalletController@redeemGiftcode');
    $r->get('/wallet/history',      'WalletController@history');
    $r->get('/wallet/transfer',     'TransferController@index');
    $r->post('/wallet/transfer',    'TransferController@send');
    $r->get('/wallet/transfer/lookup', 'TransferController@lookup');
    $r->get('/tickets',            'TicketController@index');
    $r->post('/tickets',           'TicketController@store');
    $r->get('/tickets/{code}',     'TicketController@show');
    $r->post('/tickets/{code}/reply', 'TicketController@reply');

    // --- Bảo mật & hồ sơ ---
    $r->get('/security/2fa',              'SecurityController@index');
    $r->post('/security/2fa/enable',       'SecurityController@enable');
    $r->post('/security/2fa/disable',      'SecurityController@disable');
    $r->post('/security/2fa/recovery',     'SecurityController@regenerateRecovery');
    $r->get('/security/profile',           'SecurityController@profile');
    $r->post('/security/profile',          'SecurityController@updateProfile');
    $r->post('/security/password',         'SecurityController@changePassword');
    $r->get('/security/activity',          'SecurityController@activity');
    $r->post('/security/social/{provider}/disconnect', 'SocialAuthController@disconnect');

    // --- VIP & website con ---
    $r->get('/vip',            'VipController@index');
    $r->get('/child-sites',    'VipController@childSites');
    $r->post('/child-sites',   'VipController@createChildSite');
    $r->post('/child-sites/update', 'VipController@updateChildSite');
});

// --- Chợ tài nguyên ---
$router->get('/market',          'MarketController@index');
$router->get('/market/item/{slug}', 'MarketController@show');
$router->group('', ['auth'], function (App\Core\Router $r) {
    $r->get('/market/sell',  'MarketController@sellForm');
    $r->post('/market/sell', 'MarketController@create');
    $r->post('/market/buy',  'MarketController@buy');
    $r->get('/market/my',    'MarketController@my');
});

// --- Shop ---
$router->get('/packages',        'ShopController@index');
$router->group('', ['auth'], function (App\Core\Router $r) {
    $r->post('/packages/purchase', 'ShopController@purchase');
    $r->get('/my-packages',       'ShopController@myPackages');
});

// --- CSO ---
$router->group('/cso', ['auth'], function (App\Core\Router $r) {
    $r->get('',              'CsoController@index');
    $r->post('/create',      'CsoController@create');
    $r->get('/referrals',    'CsoController@referrals');
    $r->post('/withdraw',    'CsoController@withdrawCommission');
});

// ================= P2P — sàn mua bán như Binance P2P =================
$router->get('/p2p', 'P2PController@index');
$router->get('/p2p/order/{id}', 'P2PController@show');
$router->group('/p2p', ['auth'], function (App\Core\Router $r) {
    $r->get('/sell',      'P2PController@sellForm');
    $r->post('/create',   'P2PController@create');
    $r->get('/my',        'P2PController@my');
    $r->post('/buy',      'P2PController@buy');
    $r->get('/trade/{no}','P2PController@trade');
    $r->post('/action',   'P2PController@action');
    $r->post('/message',  'P2PController@message');
    $r->post('/dispute',  'P2PController@dispute');
});

// ================= NHÓM DỊCH VỤ MỞ RỘNG =================
$router->group('/services', [], function (App\Core\Router $r) {
    // AI
    $r->get('/ai',        'ServicesController@ai');
    $r->post('/ai/chat',  'ServicesController@aiChat');

    // Diễn đàn
    $r->get('/forum',             'ServicesController@forum');
    $r->post('/forum/create',     'ServicesController@forumCreate');
    $r->post('/forum/reply',      'ServicesController@forumReply');
    $r->get('/forum/topic/{slug}','ServicesController@forumTopic');

    // CV
    $r->get('/cv',             'ServicesController@cv');
    $r->post('/cv/save',       'ServicesController@cvSave');
    $r->get('/cv/view/{id}',   'ServicesController@cvView');

    // Xem phim
    $r->get('/media',              'ServicesController@media');
    $r->get('/media/sync',         'ServicesController@mediaSync');
    $r->get('/media/watch/{slug}', 'ServicesController@mediaWatch');

    // Thiết kế ảnh
    $r->get('/design',                 'ServicesController@design');
    $r->post('/design/create',         'ServicesController@designCreate');
    $r->get('/design/preview/{id}',    'ServicesController@designPreview');

    // Domain / Hosting / VPS
    $r->get('/infra',          'ServicesController@infra');
    $r->post('/infra/check',   'ServicesController@infraCheck');
    $r->post('/infra/order',   'ServicesController@infraOrder');

    // Tra cứu ngân hàng
    $r->get('/bank-lookup', 'ServicesController@bankLookup');

    // Proxy
    $r->get('/proxy',          'ServicesController@proxy');
    $r->post('/proxy/create',  'ServicesController@proxyCreate');
    $r->post('/proxy/purchase','ServicesController@proxyPurchase');
    $r->get('/proxy/orders',   'ServicesController@proxyOrders');

    // Thiết kế website
    $r->get('/website-builder',              'ServicesController@websiteBuilder');
    $r->post('/website-builder/create',      'ServicesController@websiteBuilderCreate');
    $r->get('/website-builder/project/{id}', 'ServicesController@websiteBuilderProject');
    $r->post('/website-builder/pay',         'ServicesController@websiteBuilderPay');

    // POS / Kế toán (fullscreen)
    $r->get('/pos',           'ServicesController@pos');
    $r->post('/pos/order',    'ServicesController@posOrder');

    // Kế toán (fullscreen, MISA-style) — yêu cầu đăng nhập
    $r->get('/payment-api',         'ServicesController@paymentApi');
});

// ================= DỊCH VỤ CẦN ĐĂNG NHẬP =================
$router->group('/services', ['auth'], function (App\Core\Router $r) {
    // Check thông tin qua số điện thoại (tên, CCCD, địa chỉ, Facebook)
    $r->get('/phone-check',           'PhoneCheckController@index');
    $r->post('/phone-check/check',    'PhoneCheckController@check');
    $r->post('/phone-check/batch',    'PhoneCheckController@batch');
    $r->get('/phone-check/history',   'PhoneCheckController@history');

    // Kế toán (fullscreen, MISA-style)
    $r->get('/accounting',              'AccountingController@index');
    $r->get('/accounting/documents',    'AccountingController@documents');
    $r->get('/accounting/create',       'AccountingController@create');
    $r->post('/accounting/store',       'AccountingController@store');
    $r->get('/accounting/ledger',       'AccountingController@ledger');
    $r->get('/accounting/reports',      'AccountingController@reports');
    $r->get('/accounting/backup',       'AccountingController@backup');
    $r->get('/accounting/backup/search','AccountingController@searchBackup');
    $r->post('/accounting/backup/run',  'AccountingController@runBackup');

    // Telegram Mini App / Bot
    $r->get('/telegram',           'SocialSaleController@telegram');
    $r->post('/telegram/save',     'SocialSaleController@telegramSave');
    $r->post('/telegram/test',     'SocialSaleController@telegramTest');

    // Mạng xã hội / buff
    $r->get('/social',           'SocialSaleController@social');
    $r->post('/social/order',    'SocialSaleController@socialOrder');

    // Sale / tin nhắn hàng loạt
    $r->get('/sale',                     'SocialSaleController@sale');
    $r->post('/sale/account',            'SocialSaleController@saleAccount');
    $r->post('/sale/account/delete',     'SocialSaleController@saleAccountDelete');
    $r->post('/sale/import',             'SocialSaleController@saleImport');
    $r->post('/sale/verify/send',        'SocialSaleController@saleVerifySend');
    $r->post('/sale/verify/submit',      'SocialSaleController@saleVerifySubmit');
    $r->post('/sale/verify/reset',       'SocialSaleController@saleVerifyReset');
    $r->post('/sale/campaign',           'SocialSaleController@saleCampaign');
    $r->post('/sale/campaign/start',     'SocialSaleController@saleCampaignStart');
    $r->post('/sale/campaign/stop',      'SocialSaleController@saleCampaignStop');

    // Bank ảo
    $r->get('/virtual-bank',                    'VirtualBankController@index');
    $r->get('/virtual-bank/sync',               'VirtualBankController@sync');
    $r->get('/virtual-bank/sync-transactions',  'VirtualBankController@syncTransactions');
    $r->get('/virtual-bank/account/{id}',       'VirtualBankController@detail');
    $r->post('/virtual-bank/create',            'VirtualBankController@create');
    $r->post('/virtual-bank/extend',            'VirtualBankController@extend');
    $r->post('/virtual-bank/update',            'VirtualBankController@update');
    $r->post('/virtual-bank/notify',            'VirtualBankController@notify');
    $r->post('/virtual-bank/delete',            'VirtualBankController@delete');
    $r->post('/virtual-bank/upload-avatar',     'VirtualBankController@uploadAvatar');
    $r->post('/virtual-bank/remove-avatar',     'VirtualBankController@removeAvatar');

    // Đục UI/UX web (casino / game) — quét thật + tải asset về ZIP
    $r->get('/ui-scraper',                 'UiScraperController@index');
    $r->post('/ui-scraper/scan',           'UiScraperController@scan');
    $r->get('/ui-scraper/job/{id}',        'UiScraperController@job');
    $r->get('/ui-scraper/status/{id}',     'UiScraperController@status');
    $r->post('/ui-scraper/run/{id}',       'UiScraperController@run');
    $r->post('/ui-scraper/start/{id}',     'UiScraperController@start');
    $r->post('/ui-scraper/download/{id}',  'UiScraperController@download');
    $r->post('/ui-scraper/delete/{id}',    'UiScraperController@delete');
    $r->post('/ui-scraper/clear',          'UiScraperController@clear');

    // Copy 1-1 trang web + tạo website tự động A-Z
    $r->get('/site-clone',                     'SiteToolController@cloneIndex');
    $r->post('/site-clone/create',             'SiteToolController@cloneCreate');
    $r->get('/site-clone/project/{id}',        'SiteToolController@cloneProject');
    $r->get('/site-clone/download/{id}',       'SiteToolController@cloneDownload');
    $r->post('/site-clone/delete/{id}',        'SiteToolController@cloneDelete');
    $r->get('/site-auto',                     'SiteToolController@autoIndex');
    $r->post('/site-auto/create',             'SiteToolController@autoCreate');
    $r->get('/site-auto/project/{id}',        'SiteToolController@autoProject');
    $r->get('/site-auto/download/{id}',       'SiteToolController@autoDownload');
    $r->post('/site-auto/delete/{id}',        'SiteToolController@autoDelete');

    // Gửi tin nhắn SMS / spam số điện thoại VN (cron xử lý nền)
    $r->get('/sms',                   'SmsController@index');
    $r->post('/sms/create',           'SmsController@create');
    $r->post('/sms/action',           'SmsController@action');
    $r->post('/sms/delete',           'SmsController@delete');
    $r->get('/sms/detail/{id}',       'SmsController@detail');

    // Tài khoản AI (API đối tác GenzShop)
    $r->get('/ai-accounts',                 'AiAccountController@index');
    $r->get('/ai-accounts/sync',             'AiAccountController@sync');
    $r->get('/ai-accounts/partner-balance',  'AiAccountController@partnerBalance');
    $r->get('/ai-accounts/order/{id}',       'AiAccountController@order');
    $r->post('/ai-accounts/purchase',        'AiAccountController@purchase');
    $r->post('/ai-accounts/redeem',          'AiAccountController@redeem');

    // Tài khoản & Nick (API đối tác Vuavia)
    $r->get('/vuavia-accounts',                'VuaviaController@index');
    $r->get('/vuavia-accounts/sync',            'VuaviaController@sync');
    $r->get('/vuavia-accounts/order/{id}',       'VuaviaController@order');
    $r->post('/vuavia-accounts/purchase',        'VuaviaController@purchase');
    $r->post('/vuavia-accounts/redeem',          'VuaviaController@redeem');

    // Thuê số nhận OTP (API ViOTP v1)
    $r->get('/otp-rental',                'OtpRentalController@index');
    $r->get('/otp-rental/partner-balance', 'OtpRentalController@partnerBalance');
    $r->get('/otp-rental/poll',            'OtpRentalController@poll');
    $r->get('/otp-rental/rental/{id}',     'OtpRentalController@rental');
    $r->post('/otp-rental/rent',           'OtpRentalController@rent');
    $r->post('/otp-rental/rerent',         'OtpRentalController@rerent');

    // Auto MXH — mở khóa tài khoản (API đối tác TrungTamMMO)
    $r->get('/automxh',                'AutoMxhController@index');
    $r->get('/automxh/order/{id}',      'AutoMxhController@order');
    $r->get('/automxh/status',          'AutoMxhController@status');
    $r->get('/automxh/quote',           'AutoMxhController@quote');
    $r->post('/automxh/submit',         'AutoMxhController@submit');
    $r->get('/automxh/apikey',          'AutoMxhController@apikey');
    $r->post('/automxh/apikey/save',    'AutoMxhController@saveApikey');
    $r->post('/automxh/apikey/delete',  'AutoMxhController@deleteApikey');
    $r->post('/automxh/apikey/primary', 'AutoMxhController@setPrimaryApikey');

    // SMM — like / view / comment / share / follow (API đối tác TrungTamMMO)
    $r->get('/smm',                 'SmmController@index');
    $r->get('/smm/order/{id}',      'SmmController@order');
    $r->get('/smm/status',          'SmmController@status');
    $r->get('/smm/quote',           'SmmController@quote');
    $r->post('/smm/submit',         'SmmController@submit');
    $r->get('/smm/apikey',          'SmmController@apikey');
    $r->post('/smm/apikey/save',    'SmmController@saveApikey');
    $r->post('/smm/apikey/delete',  'SmmController@deleteApikey');
    $r->post('/smm/apikey/primary', 'SmmController@setPrimaryApikey');

    // VPS VMware — thuê máy chủ ảo (XVPS Agency API)
    $r->get('/vps',                    'VpsController@index');
    $r->get('/vps/order/{id}',         'VpsController@order');
    $r->get('/vps/manage',             'VpsController@manage');
    $r->get('/vps/manage/{vpsId}',     'VpsController@control');
    $r->get('/vps/quote',              'VpsController@quote');
    $r->post('/vps/submit',            'VpsController@submit');
    $r->post('/vps/action',            'VpsController@action');
    $r->post('/vps/sync',              'VpsController@sync');

    // VPS StoreVPS / Virtualizor (XVPS api.xvps.vn/api/virt/*)
    $r->get('/vps-storevps',                 'VpsStorevpsController@index');
    $r->get('/vps-storevps/order/{id}',      'VpsStorevpsController@order');
    $r->get('/vps-storevps/manage',          'VpsStorevpsController@manage');
    $r->get('/vps-storevps/manage/{vpsId}',  'VpsStorevpsController@control');
    $r->get('/vps-storevps/quote',           'VpsStorevpsController@quote');
    $r->post('/vps-storevps/submit',         'VpsStorevpsController@submit');
    $r->post('/vps-storevps/action',         'VpsStorevpsController@action');
    $r->post('/vps-storevps/rebuild',        'VpsStorevpsController@rebuild');
    $r->post('/vps-storevps/password',       'VpsStorevpsController@password');
    $r->post('/vps-storevps/hostname',       'VpsStorevpsController@hostname');
    $r->post('/vps-storevps/renew',          'VpsStorevpsController@renew');
    $r->post('/vps-storevps/addon',          'VpsStorevpsController@addon');
    $r->post('/vps-storevps/auto-renew',     'VpsStorevpsController@autoRenew');
    $r->post('/vps-storevps/cancel',         'VpsStorevpsController@cancel');
    $r->get('/vps-storevps/status',          'VpsStorevpsController@status');
    $r->post('/vps-storevps/sync',           'VpsStorevpsController@sync');
});

// ================= CÔNG CỤ TIỆN ÍCH =================
$router->group('/tools', [], function (App\Core\Router $r) {
    $r->get('',           'ToolsController@index');
    $r->get('/hash',      'ToolsController@hash');
    $r->post('/hash',     'ToolsController@hash');
    $r->get('/json',      'ToolsController@json');
    $r->post('/json',     'ToolsController@json');
    $r->get('/password',  'ToolsController@password');
    $r->post('/password', 'ToolsController@password');
    $r->get('/convert',   'ToolsController@convert');
    $r->post('/convert',  'ToolsController@convert');
    $r->get('/uuid',      'ToolsController@uuid');
    $r->post('/uuid',     'ToolsController@uuid');
    $r->get('/base64',    'ToolsController@base64');
    $r->post('/base64',   'ToolsController@base64');
    $r->get('/ip',        'ToolsController@ip');
    $r->post('/ip',       'ToolsController@ip');
    $r->get('/whois',     'ToolsController@whois');
    $r->post('/whois',    'ToolsController@whois');
    $r->get('/seo',       'ToolsController@seo');
    $r->post('/seo',      'ToolsController@seo');
    $r->get('/speed',     'ToolsController@speed');
    $r->post('/speed',    'ToolsController@speed');
});

// --- API cho dev / đối tác ---
$router->group('/api-keys', ['auth'], function (App\Core\Router $r) {
    $r->get('',            'ApiKeyController@index');
    $r->post('/purchase',  'ApiKeyController@purchase');
    $r->post('/create',    'ApiKeyController@create');
    $r->post('/renew',     'ApiKeyController@renew');
    $r->post('/toggle',    'ApiKeyController@toggle');
    $r->post('/delete',    'ApiKeyController@delete');
    $r->get('/reveal',     'ApiKeyController@reveal');
});

// --- Operator API (game server gọi) ---
// Đường dẫn chuẩn theo tài liệu Seamless Wallet của các nhà phát hành.
$router->post('/operator/api/verifySession', 'OperatorController@verifySession');
$router->post('/operator/api/wallet/get',    'OperatorController@walletGet');
$router->post('/operator/api/wallet/change', 'OperatorController@walletChange');
$router->post('/operator/api/game/info',     'OperatorController@gameInfo');
$router->get('/operator/api/game/list',      'OperatorController@gameList');

// Alias chuẩn PGSoft (Cash/Get, Cash/Change) và alias cho các sảnh khác
$router->post('/operator/api/VerifySession',  'OperatorController@verifySession');
$router->post('/operator/api/Cash/Get',       'OperatorController@walletGet');
$router->post('/operator/api/Cash/Change',    'OperatorController@walletChange');
$router->post('/operator/api/wallet/balance', 'OperatorController@walletGet');
$router->post('/operator/api/balance',        'OperatorController@walletGet');
$router->post('/operator/api/transaction',    'OperatorController@walletChange');
$router->post('/operator/api/bet',            'OperatorController@walletChange');
$router->post('/operator/api/win',            'OperatorController@walletChange');

// --- Cron Auto Bank (quét tự động, xác thực bằng token) ---
$router->get('/api/auto-bank/cron', 'AutoBankController@cron');

// --- Proxy API Auto MXH (đối tác dùng apikey riêng của user) ---
// Phải đăng ký TRƯỚC route /api/{action...} để không bị route tổng nuốt mất.
$router->get('/api/external/automxh',            'AutoMxhApiController@handle');
$router->post('/api/external/automxh',           'AutoMxhApiController@handle');
$router->get('/api/external/automxh/{path...}',  'AutoMxhApiController@handle');
$router->post('/api/external/automxh/{path...}', 'AutoMxhApiController@handle');

// --- Proxy API SMM (đối tác dùng apikey riêng của user) ---
// Phải đăng ký TRƯỚC route /api/{action...} để không bị route tổng nuốt mất.
$router->get('/api/external/smm',            'SmmApiController@handle');
$router->post('/api/external/smm',           'SmmApiController@handle');
$router->get('/api/external/smm/{path...}',  'SmmApiController@handle');
$router->post('/api/external/smm/{path...}', 'SmmApiController@handle');

// --- Public API cho đối tác / dev (X-API-Key) ---
$router->get('/api/docs',  'ApiController@docsPage');
$router->get('/api/spec',  'ApiController@specPage');
$router->get('/api/{action...}', 'ApiController@handle');
$router->post('/api/{action...}', 'ApiController@handle');

// --- Admin ---
$router->group('/admin', ['auth', 'admin'], function (App\Core\Router $r) {
    $r->get('',                  'AdminController@dashboard');
    $r->get('/deposits',         'AdminController@deposits');
    $r->post('/deposits/approve','AdminController@approveDeposit');
    $r->post('/deposits/reject', 'AdminController@rejectDeposit');
    $r->get('/withdrawals',      'AdminController@withdrawals');
    $r->post('/withdrawals/approve','AdminController@approveWithdrawal');
    $r->post('/withdrawals/reject','AdminController@rejectWithdrawal');
    $r->get('/games',            'AdminController@games');
    $r->post('/games/sync',      'AdminController@syncGames');
    $r->post('/games/toggle',    'AdminController@toggleGame');
    $r->get('/packages',         'AdminController@packages');
    $r->post('/packages/save',   'AdminController@savePackage');
    $r->get('/users',            'AdminController@users');
    $r->post('/users/create',    'AdminController@createUser');
    $r->post('/users/update',    'AdminController@updateUser');
    $r->post('/users/balance',   'AdminController@adjustBalance');
    $r->get('/giftcodes',        'AdminController@giftcodes');
    $r->post('/giftcodes/create','AdminController@createGiftcode');
    $r->get('/settings',         'AdminController@settings');
    $r->post('/settings/save',   'AdminController@saveSettings');
    $r->get('/cso',              'AdminController@csoList');
    $r->post('/cso/pay',         'AdminController@payCommission');
    $r->get('/tickets',          'TicketController@adminIndex');
    $r->get('/tickets/{code}',   'TicketController@adminShow');
    $r->post('/tickets/{code}/reply', 'TicketController@adminReply');

    // --- Quảng cáo / banner ---
    $r->get('/ads',              'AdminController@ads');
    $r->post('/ads/save',        'AdminController@saveAd');
    $r->post('/ads/delete',      'AdminController@deleteAd');

    // --- Website con ---
    $r->get('/child-sites',      'AdminController@childSites');
    $r->post('/child-sites/update', 'AdminController@updateChildSite');

    // --- Tin tức / sự kiện ---
    $r->get('/news',             'AdminController@news');
    $r->post('/news/save',       'AdminController@saveNews');
    $r->post('/news/delete',     'AdminController@deleteNews');

    // --- Chiến dịch email ---
    $r->get('/campaigns',        'AdminController@campaigns');
    $r->post('/campaigns/save',  'AdminController@saveCampaign');
    $r->post('/campaigns/send',  'AdminController@sendCampaign');
    $r->post('/campaigns/delete','AdminController@deleteCampaign');

    // --- Ngân hàng nhận tiền (VietQR) ---
    $r->get('/banks',            'AdminController@banks');
    $r->post('/banks/save',      'AdminController@saveBank');
    $r->post('/banks/delete',    'AdminController@deleteBank');

    // --- Chợ tài nguyên ---
    $r->get('/market',           'AdminController@market');
    $r->post('/market/toggle',   'AdminController@toggleMarketProduct');

    // --- Bảo mật & tick xanh ---
    $r->post('/users/verify',    'AdminController@toggleVerified');
    $r->get('/security',         'AdminController@security');

    // --- Bank ảo (HeyBank API v2) ---
    $r->get('/virtual-bank',         'VirtualBankController@admin');
    $r->post('/virtual-bank/save',   'VirtualBankController@adminSave');
    $r->post('/virtual-bank/save-apps',   'VirtualBankController@adminSaveApp');
    $r->post('/virtual-bank/delete-app',  'VirtualBankController@adminDeleteApp');

    // --- Tài khoản AI (GenzShop Partner API) ---
    $r->get('/ai-accounts',         'AiAccountController@admin');
    $r->post('/ai-accounts/save',   'AiAccountController@adminSave');
    $r->post('/ai-accounts/sync',   'AiAccountController@adminSync');

    // --- Tài khoản & Nick (API đối tác Vuavia) ---
    $r->get('/vuavia-accounts',        'VuaviaController@admin');
    $r->post('/vuavia-accounts/save',  'VuaviaController@adminSave');
    $r->post('/vuavia-accounts/sync',  'VuaviaController@adminSync');
    $r->post('/vuavia-accounts/deliver','VuaviaController@adminDeliver');
    $r->get('/vuavia-accounts/partner-balance', 'VuaviaController@partnerBalance');

    // --- Thuê số OTP (ViOTP API v1) ---
    $r->get('/otp-rental',         'OtpRentalController@admin');
    $r->post('/otp-rental/save',   'OtpRentalController@adminSave');
    $r->post('/otp-rental/sync',   'OtpRentalController@adminSync');

    // --- Auto Bank (cổng thanh toán tự động api.sieuthicode.net) ---
    $r->get('/auto-bank',              'AutoBankController@index');
    $r->get('/auto-bank/test',         'AutoBankController@test');
    $r->post('/auto-bank/save',        'AutoBankController@save');
    $r->post('/auto-bank/delete',      'AutoBankController@delete');
    $r->post('/auto-bank/sync',        'AutoBankController@sync');
    $r->post('/auto-bank/sync-all',    'AutoBankController@syncAll');
    $r->post('/auto-bank/reconcile',   'AutoBankController@reconcile');
    $r->post('/auto-bank/save-settings','AutoBankController@saveSettings');
    $r->post('/auto-bank/approve-deposit','AutoBankController@approveDeposit');

    // --- Auto MXH (mở khóa tài khoản — API trungtammmo.vn) ---
    $r->get('/automxh',               'AutoMxhController@admin');
    $r->post('/automxh/save',         'AutoMxhController@adminSave');
    $r->post('/automxh/sync',         'AutoMxhController@adminSync');
    $r->post('/automxh/refresh',      'AutoMxhController@adminRefresh');
    $r->post('/automxh/order/update', 'AutoMxhController@adminUpdateOrder');
    $r->get('/automxh/partner-balance','AutoMxhController@partnerBalance');

    // --- SMM (like, view, comment — API trungtammmo.vn) ---
    $r->get('/smm',               'SmmController@admin');
    $r->post('/smm/save',         'SmmController@adminSave');
    $r->post('/smm/sync',         'SmmController@adminSync');
    $r->post('/smm/refresh',      'SmmController@adminRefresh');
    $r->post('/smm/order/update', 'SmmController@adminUpdateOrder');
    $r->post('/smm/key/discount', 'SmmController@adminKeyDiscount');
    $r->get('/smm/partner-balance','SmmController@partnerBalance');

    // --- Dịch vụ mới (V16): bảng giá, check SĐT, website, SMS ---
    $r->get('/service-prices',       'ServiceAdminController@prices');
    $r->post('/service-prices/save', 'ServiceAdminController@savePrice');
    $r->post('/service-prices/delete','ServiceAdminController@deletePrice');
    $r->get('/phone-checks',         'ServiceAdminController@phoneChecks');
    $r->get('/site-jobs',            'ServiceAdminController@sites');
    $r->get('/sms-campaigns',        'ServiceAdminController@smsCampaigns');
    $r->post('/sms-campaigns/action','ServiceAdminController@smsAction');
    $r->get('/service-overview',     'ServiceAdminController@overview');

    // --- VPS VMware (XVPS Agency API — api.xvps.vn) ---
    $r->get('/vps',                 'VpsController@admin');
    $r->post('/vps/save',           'VpsController@adminSave');
    $r->post('/vps/sync',           'VpsController@adminSync');
    $r->get('/vps/balance',         'VpsController@adminBalance');
    $r->post('/vps/instance/action','VpsController@adminInstanceAction');

    // --- VPS StoreVPS / Virtualizor (XVPS api.xvps.vn/api/virt/*) ---
    $r->get('/vps-storevps',                  'VpsStorevpsController@admin');
    $r->post('/vps-storevps/save',            'VpsStorevpsController@adminSave');
    $r->post('/vps-storevps/sync',            'VpsStorevpsController@adminSync');
    $r->get('/vps-storevps/balance',          'VpsStorevpsController@adminBalance');
    $r->post('/vps-storevps/instance/action', 'VpsStorevpsController@adminInstanceAction');
});

// ---------- Dispatch ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
