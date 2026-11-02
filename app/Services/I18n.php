<?php
namespace App\Services;

/**
 * Đa ngôn ngữ. Mặc định: tiếng Việt (vi).
 *
 * Dictionary nằm ở app/Views/langs/{code}.php — dạng mảng key => chuỗi.
 * Key dạng "dot.notation" được dịch bằng dấu chấm.
 */
class I18n
{
    private static array $dict = [];
    private static string $locale = 'vi';

    public static function available(): array
    {
        return [
            'vi' => ['name' => 'Tiếng Việt',  'flag' => '🇻🇳', 'locale' => 'vi_VN'],
            'en' => ['name' => 'English',     'flag' => '🇬🇧', 'locale' => 'en_US'],
            'zh' => ['name' => '中文',         'flag' => '🇨🇳', 'locale' => 'zh_CN'],
            'ko' => ['name' => '한국어',        'flag' => '🇰🇷', 'locale' => 'ko_KR'],
            'ja' => ['name' => '日本語',        'flag' => '🇯🇵', 'locale' => 'ja_JP'],
            'th' => ['name' => 'ไทย',          'flag' => '🇹🇭', 'locale' => 'th_TH'],
            'id' => ['name' => 'Indonesia',   'flag' => '🇮🇩', 'locale' => 'id_ID'],
            'es' => ['name' => 'Español',     'flag' => '🇪🇸', 'locale' => 'es_ES'],
            'ru' => ['name' => 'Русский',      'flag' => '🇷🇺', 'locale' => 'ru_RU'],
            'pt' => ['name' => 'Português',   'flag' => '🇧🇷', 'locale' => 'pt_BR'],
        ];
    }

    public static function locales(): array
    {
        return array_keys(self::available());
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function setLocale(string $code): void
    {
        if (!isset(self::available()[$code])) {
            $code = 'vi';
        }
        self::$locale = $code;
        self::$dict = [];
    }

    /** Khởi tạo theo user đã đăng nhập / cookie / cài đặt */
    public static function boot(): void
    {
        $code = (string)setting('default_language', 'vi');

        $user = \App\Core\Auth::user();
        if ($user && !empty($user['language'])) {
            $code = (string)$user['language'];
        } elseif (!empty($_COOKIE['lang'])) {
            $code = (string)$_COOKIE['lang'];
        }

        self::setLocale($code);
    }

    private static function load(): array
    {
        if (self::$dict !== []) {
            return self::$dict;
        }
        $file = BASE_PATH . '/app/Views/langs/' . self::$locale . '.php';
        if (is_file($file)) {
            $data = include $file;
            if (is_array($data)) {
                self::$dict = $data;
            }
        }
        if (self::$dict === []) {
            $vi = BASE_PATH . '/app/Views/langs/vi.php';
            if (is_file($vi)) {
                $data = include $vi;
                self::$dict = is_array($data) ? $data : [];
            }
        }
        return self::$dict;
    }

    /**
     * Dịch một key. Nếu không tồn tại → trả về chính key.
     */
    public static function t(string $key, array $replace = []): string
    {
        $dict = self::load();
        $value = $dict[$key] ?? null;

        if ($value === null) {
            // thử key lồng nhau
            $cur = $dict;
            foreach (explode('.', $key) as $seg) {
                if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                    $value = null;
                    break;
                }
                $cur = $cur[$seg];
            }
            if ($cur !== null && !is_array($cur)) {
                $value = $cur;
            }
        }

        if ($value === null || is_array($value)) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, (string)$v, $value);
        }
        return (string)$value;
    }

    /** Bản rút gọn để dùng trong view */
    public static function __callStatic(string $method, array $args): mixed
    {
        if ($method === 't' || $method === 'translate') {
            return self::t((string)($args[0] ?? ''), (array)($args[1] ?? []));
        }
        return null;
    }
}
