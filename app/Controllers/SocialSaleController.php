<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\ProxyService;
use App\Services\SaleService;
use App\Services\SocialService;
use App\Services\TelegramService;

/**
 * Dịch vụ Telegram Mini App / Bot, dịch vụ social buff, dịch vụ sale tin nhắn.
 */
class SocialSaleController
{
    // ================= TELEGRAM MINI APP =================

    public function telegram(): void
    {
        $bots = Database::fetchAll('SELECT id, label, chat_id, is_default, is_active FROM telegram_bots ORDER BY is_default DESC, id');
        $configured = TelegramService::configured();

        view('services/telegram', [
            'title'      => 'Telegram Mini App & Bot',
            'bots'       => $bots,
            'configured' => $configured,
            'token'      => (string)setting('telegram_bot_token', ''),
            'chatId'     => (string)setting('telegram_admin_chat_id', ''),
        ]);
    }

    public function telegramSave(): void
    {
        CSRF::verifyOrFail();

        $token  = trim((string)($_POST['bot_token'] ?? ''));
        $chatId = trim((string)($_POST['chat_id'] ?? ''));
        $label  = trim((string)($_POST['label'] ?? 'Bot chính')) ?: 'Bot chính';

        if ($token === '' || $chatId === '') {
            flash('error', 'Vui lòng nhập đầy đủ Bot Token và Chat ID.');
            redirect(url('/services/telegram'));
        }

        $exists = Database::fetch('SELECT id FROM telegram_bots WHERE bot_token = ?', [$token]);
        if ($exists) {
            Database::update('telegram_bots', [
                'label'    => $label,
                'chat_id'  => $chatId,
                'is_active'=> 1,
            ], 'id = :id', ['id' => (int)$exists['id']]);
        } else {
            Database::insert('telegram_bots', [
                'label'      => $label,
                'bot_token'  => $token,
                'chat_id'    => $chatId,
                'is_default' => 0,
                'is_active'  => 1,
            ]);
        }

        flash('success', 'Đã lưu cấu hình Telegram bot.');
        redirect(url('/services/telegram'));
    }

    public function telegramTest(): void
    {
        CSRF::verifyOrFail();

        $ok = TelegramService::send("<b>🔔 Test thông báo</b>\nHệ thống đang hoạt động bình thường.");
        flash($ok ? 'success' : 'error', $ok ? 'Đã gửi tin nhắn test thành công.' : 'Gửi thất bại, kiểm tra lại Bot Token / Chat ID.');
        redirect(url('/services/telegram'));
    }

    // ================= SOCIAL / BUFF =================

    public function social(): void
    {
        $platform = (string)($_GET['platform'] ?? '');

        view('services/social', [
            'title'       => 'Dịch vụ mạng xã hội & Buff',
            'platforms'   => SocialService::PLATFORMS,
            'services'    => SocialService::services($platform !== '' ? $platform : null),
            'orders'      => SocialService::myOrders((int)Auth::id(), 30),
            'currentPlatform' => $platform,
        ]);
    }

    public function socialOrder(): void
    {
        CSRF::verifyOrFail();

        $res = SocialService::order((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/social?platform=' . urlencode((string)($_POST['platform'] ?? ''))));
    }

    // ================= SALE / SPAM =================

    public function sale(): void
    {
        view('services/sale', [
            'title'    => 'Dịch vụ sale & tin nhắn hàng loạt',
            'platforms'=> SaleService::PLATFORMS,
            'statuses' => SaleService::STATUSES,
            'accStatuses' => SaleService::ACCOUNT_STATUSES,
            'accounts' => SaleService::accounts((int)Auth::id()),
            'campaigns'=> SaleService::campaigns((int)Auth::id(), 30),
            'proxies'  => ProxyService::list(null, '', 50, 0),
            'imports'  => SaleService::imports((int)Auth::id(), 10),
            'verifyRequired' => SaleService::isVerificationRequired(),
        ]);
    }

    public function saleAccount(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::createAccount((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    /** Gửi mã xác minh 6 số cho tài khoản */
    public function saleVerifySend(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::createVerification((int)Auth::id(), (int)($_POST['id'] ?? 0));
        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/sale'));
        }

        // Gửi mã tới ứng dụng thật (Telegram / SMS / Email) — bỏ qua lỗi kết nối
        $sent = self::dispatchVerifyCode($res);

        flash('success', $res['message'] . ($sent ? ' Đã gửi mã tới ' . $res['account'] . '.' : ' Mã: ' . $res['code']));
        redirect(url('/services/sale'));
    }

    /** Người dùng nhập mã 6 số */
    public function saleVerifySubmit(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::verifyAccount((int)Auth::id(), (int)($_POST['id'] ?? 0), (string)($_POST['code'] ?? ''));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    public function saleVerifyReset(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::resetVerification((int)Auth::id(), (int)($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    /**
     * Gửi mã xác minh tới ứng dụng (Telegram bot / SMS / Email).
     */
    private static function dispatchVerifyCode(array $res): bool
    {
        $platform = (string)($res['platform'] ?? 'telegram');
        $account  = (string)($res['account'] ?? '');
        $code     = (string)($res['code'] ?? '');

        try {
            if ($platform === 'telegram') {
                $sent = TelegramService::send("🔐 Mã xác minh Toolduc: *{$code}*\nHết hạn sau 15 phút.");
                return (bool)$sent;
            }
            if ($platform === 'email') {
                $sent = self::sendEmail($account, 'Mã xác minh Toolduc', "Mã xác minh của bạn là: {$code}");
                return (bool)$sent;
            }
            if ($platform === 'sms') {
                $sent = self::sendSms($account, "Mã xác minh Toolduc: {$code}");
                return (bool)$sent;
            }
        } catch (\Throwable $e) {
            // ignore — hiển thị mã fallback
        }
        return false;
    }

    private static function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $from = (string)setting('sale_email_from', 'no-reply@toolduc.vn');
            $host = (string)setting('sale_email_host', 'smtp.gmail.com');
            $port = (int)setting('sale_email_port', 587);
            $user = (string)setting('sale_email_user', '');
            $pass = (string)setting('sale_email_pass', '');
            if ($user === '') {
                return false;
            }
            if (!function_exists('mail')) {
                return false;
            }
            $headers = "From: {$from}\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
            return (bool)@mail($to, $subject, $body, $headers);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function sendSms(string $phone, string $body): bool
    {
        try {
            $apiKey = (string)setting('sale_sms_api_key', '');
            $url    = (string)setting('sale_sms_api_url', '');
            if ($apiKey === '' || $url === '') {
                return false;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['phone' => $phone, 'message' => $body]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Upload file .txt/.csv để import hàng loạt tài khoản */
    public function saleImport(): void
    {
        CSRF::verifyOrFail();

        $platform = (string)($_POST['platform'] ?? 'telegram');
        $file     = $_FILES['data_file'] ?? null;

        if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Vui lòng chọn file .txt hoặc .csv để import.');
            redirect(url('/services/sale'));
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'csv'], true)) {
            flash('error', 'Chỉ hỗ trợ file .txt hoặc .csv.');
            redirect(url('/services/sale'));
        }
        if ((int)$file['size'] > 5 * 1024 * 1024) {
            flash('error', 'File quá lớn (tối đa 5MB).');
            redirect(url('/services/sale'));
        }

        $res = SaleService::parseImportFile($file['tmp_name'], (string)$file['name']);
        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/sale'));
        }

        $out = SaleService::importAccounts((int)Auth::id(), $platform, $res['valid'], (string)$file['name']);
        flash($out['ok'] ? 'success' : 'error', $out['message']);

        redirect(url('/services/sale'));
    }

    public function saleAccountDelete(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::deleteAccount((int)Auth::id(), (int)($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    public function saleCampaign(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::createCampaign((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    public function saleCampaignStart(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::startCampaign((int)Auth::id(), (int)($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }

    public function saleCampaignStop(): void
    {
        CSRF::verifyOrFail();

        $res = SaleService::stopCampaign((int)Auth::id(), (int)($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/sale'));
    }
}
