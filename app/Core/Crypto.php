<?php
namespace App\Core;

/**
 * Mã hóa đối xứng (AES-256-GCM) dùng cho các giá trị nhạy cảm
 * cần giữ lại nhưng không muốn lộ bản rõ (API key, số tài khoản...).
 *
 * - Khóa lấy từ APP_KEY (base64) trong config, sinh thêm nếu thiếu.
 * - GCM kèm IV ngẫu nhiên + tag xác thực → chống sửa đổi ciphertext.
 * - Chuỗi lưu trong DB: base64(iv . tag . ciphertext)
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    /** @var string|null */
    private static ?string $key = null;

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $raw = (string)config('app.key', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 6)) ?: '';
        }
        if ($raw === '' || strlen($raw) < 32) {
            // Sinh khóa tạm cho phiên (không tồn tại sau restart) — chỉ để dev
            $raw = hash('sha256', 'dev-key-' . gethostname() . PHP_VERSION, true);
        }

        return self::$key = hash('sha256', $raw, true);
    }

    /**
     * Mã hóa chuỗi.
     */
    public static function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ct === false) {
            throw new \RuntimeException('Mã hóa thất bại');
        }

        return base64_encode($iv . $tag . $ct);
    }

    /**
     * Giải mã chuỗi đã mã hóa. Trả về null nếu sai khóa / dữ liệu hỏng.
     */
    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);

        $plain = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /**
     * Hash chuẩn dùng để tra cứu nhanh (không thể đảo ngược).
     */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Sinh API key dạng: pk_live_<prefix>_<secret>
     * - prefix: 12 ký tự random, dùng để tra cứu nhanh trong DB
     * - secret: 48 ký tự random, chỉ hiển thị 1 lần
     */
    public static function generateApiKey(string $prefix = 'pk_live'): array
    {
        $short = substr(bin2hex(random_bytes(6)), 0, 12);   // 12 hex
        $secret = bin2hex(random_bytes(24));                // 48 hex
        $key = "{$prefix}_{$short}_{$secret}";

        return [
            'key'       => $key,
            'prefix'    => $short,
            'hash'      => self::hash($key),
            'encrypted' => self::encrypt($key),
        ];
    }

    /**
     * Tạo token phiên ngẫu nhiên (hex).
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * HMAC chữ ký request (webhook).
     */
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * So sánh thời gian cố định.
     */
    public static function equals(?string $a, ?string $b): bool
    {
        return is_string($a) && is_string($b) && hash_equals($a, $b);
    }
}
