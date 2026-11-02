<?php
namespace App\Services;

use App\Core\Database;

/**
 * Gửi thông báo qua Telegram Bot API.
 *
 * Dùng cho:
 *  - Thông báo admin khi có dự án website mới / giao dịch P2P / đăng ký mới.
 *  - Tính năng bán hàng: đăng ký tài khoản Telegram của khách để nhận thông báo.
 */
class TelegramService
{
    private static ?array $bot = null;

    /**
     * Lấy cấu hình bot đang dùng (bot mặc định, hoặc theo tên trong settings).
     */
    public static function bot(): ?array
    {
        if (self::$bot !== null) {
            return self::$bot ?: null;
        }

        $row = Database::fetch('SELECT * FROM telegram_bots WHERE is_active = 1 ORDER BY is_default DESC, id LIMIT 1');
        if (!$row) {
            // Fallback sang settings
            $token = (string)setting('telegram_bot_token', '');
            $chat  = (string)setting('telegram_admin_chat_id', '');
            if ($token === '' || $chat === '') {
                self::$bot = [];
                return null;
            }
            self::$bot = ['bot_token' => $token, 'chat_id' => $chat];
            return self::$bot;
        }

        self::$bot = $row;
        return $row;
    }

    public static function configured(): bool
    {
        $bot = self::bot();
        return !empty($bot['bot_token']) && !empty($bot['chat_id']);
    }

    /**
     * Gửi tin nhắn text.
     */
    public static function send(string $text, ?string $chatId = null): bool
    {
        $bot = self::bot();
        if (!$bot || empty($bot['bot_token'])) {
            logger('Telegram chưa cấu hình, bỏ qua gửi: ' . mb_substr($text, 0, 100), 'warning');
            return false;
        }

        $chat = $chatId ?: (string)$bot['chat_id'];
        if ($chat === '') {
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $bot['bot_token'] . '/sendMessage';
        $res = HttpClient::postJson($url, [
            'chat_id'    => $chat,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        return $res !== null;
    }

    /**
     * Gửi tin nhắn dài (tự chia nhỏ 4096 ký tự của Telegram).
     */
    public static function sendLong(string $text, ?string $chatId = null): bool
    {
        $chunks = str_split($text, 4000);
        $ok = true;
        foreach ($chunks as $chunk) {
            if (!self::send($chunk, $chatId)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Thông báo cho admin về dự án website mới.
     */
    public static function notifyWebsiteProject(array $project): bool
    {
        $lines = [
            '<b>🌐 Dự án website mới</b>',
            'Tên: ' . ($project['name'] ?? '-'),
            'Nền tảng: ' . ($project['platform'] ?? '-'),
            'Domain: ' . ($project['domain'] ?? '-'),
            'Email: ' . ($project['email'] ?? '-'),
            'Telegram: ' . ($project['telegram'] ?? '-'),
            'Giá: ' . money((float)($project['price'] ?? 0)),
            'Mã: <code>#' . (int)$project['id'] . '</code>',
        ];
        return self::sendLong(implode("\n", $lines));
    }

    /**
     * Thông báo cho admin về giao dịch P2P mới.
     */
    public static function notifyP2PTrade(array $trade): bool
    {
        $lines = [
            '<b>🔄 Giao dịch P2P mới</b>',
            'Mã: <code>' . ($trade['trade_no'] ?? '-') . '</code>',
            'Người mua: ' . ($trade['buyer_name'] ?? '-'),
            'Người bán: ' . ($trade['seller_name'] ?? '-'),
            'Số tiền: ' . money((float)($trade['amount'] ?? 0)),
            'Trạng thái: ' . ($trade['status'] ?? '-'),
        ];
        return self::sendLong(implode("\n", $lines));
    }

    /**
     * Gửi mã 2FA / OTP tới Telegram của chính user (nếu user đã liên kết).
     */
    public static function sendToUser(int $userId, string $text): bool
    {
        $chatId = Database::fetchColumn(
            'SELECT provider_id FROM social_accounts WHERE user_id = ? AND provider = "telegram" LIMIT 1',
            [$userId]
        );
        if (!$chatId) {
            return false;
        }
        return self::send($text, (string)$chatId);
    }
}
