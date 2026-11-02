<?php
namespace App\Services;

use App\Core\Database;

/**
 * HTTP client đơn giản dùng cURL, có timeout + retry.
 */
class HttpClient
{
    private static array $options = [
        'timeout'   => 20,
        'connect_timeout' => 10,
        'retries'   => 2,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
    ];

    public static function get(string $url, array $headers = [], array $opts = []): ?string
    {
        return self::request('GET', $url, null, $headers, $opts);
    }

    public static function postJson(string $url, array $payload, array $headers = [], array $opts = []): ?string
    {
        $headers[] = 'Content-Type: application/json';
        return self::request('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $opts);
    }

    public static function postForm(string $url, array $payload, array $headers = [], array $opts = []): ?string
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return self::request('POST', $url, http_build_query($payload), $headers, $opts);
    }

    /** GET trả về mảng JSON (null nếu lỗi hoặc không phải JSON) */
    public static function getJson(string $url, array $query = [], array $headers = [], array $opts = []): ?array
    {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $body = self::request('GET', $url, null, $headers, $opts);
        if ($body === null) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** POST JSON trả về mảng JSON */
    public static function postJsonArray(string $url, array $payload, array $headers = [], array $opts = []): ?array
    {
        $body = self::postJson($url, $payload, $headers, $opts);
        if ($body === null) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Gọi HTTP và trả về body + status + headers (cần Retry-After, RateLimit...).
     *
     * @return array ['body'=>string|null,'status'=>int,'headers'=>array<string,string>,'ok'=>bool]
     */
    public static function requestFull(string $method, string $url, ?array $payload = null, array $headers = [], array $opts = []): array
    {
        $timeout = $opts['timeout'] ?? self::$options['timeout'];
        $ua      = $opts['user_agent'] ?? self::$options['user_agent'];

        $ch = curl_init();
        $hdr = array_merge(['Accept: application/json', 'Accept-Language: vi,en-US;q=0.9'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $opts['connect_timeout'] ?? self::$options['connect_timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => $ua,
        ]);

        if ($method === 'POST') {
            $hdr[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        if ($hdr) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
        }

        $raw    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err    = curl_error($ch);
        curl_close($ch);

        $body = '';
        $respHeaders = [];
        if (is_string($raw) && $raw !== '') {
            $head = substr($raw, 0, $hsize);
            $body = substr($raw, $hsize);
            foreach (preg_split('/\r?\n/', $head) as $line) {
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
            }
        }

        if ($raw === false) {
            logger("HTTP {$method} {$url} failed: {$err}", 'warning');
        }

        return [
            'body'    => is_string($raw) ? $body : null,
            'status'  => $status,
            'headers' => $respHeaders,
            'ok'      => $raw !== false && $status >= 200 && $status < 300,
        ];
    }

    /** PUT JSON (dùng cho cập nhật tài nguyên theo id) */
    public static function putJson(string $url, array $payload, array $headers = [], array $opts = []): ?string
    {
        $headers[] = 'Content-Type: application/json';
        return self::request('PUT', $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $opts);
    }

    /** DELETE kèm body JSON */
    public static function deleteJson(string $url, array $payload = [], array $headers = [], array $opts = []): ?string
    {
        $headers[] = 'Content-Type: application/json';
        return self::request('DELETE', $url, $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null, $headers, $opts);
    }

    private static function request(string $method, string $url, ?string $body, array $headers, array $opts): ?string
    {
        $timeout   = $opts['timeout'] ?? self::$options['timeout'];
        $retries   = $opts['retries'] ?? self::$options['retries'];
        $ua        = $opts['user_agent'] ?? self::$options['user_agent'];

        $attempt = 0;
        $lastErr = null;

        while ($attempt <= $retries) {
            $ch = curl_init();
            $hdr = array_merge(['Accept: */*', 'Accept-Language: vi,en-US;q=0.9'], $headers);

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $opts['connect_timeout'] ?? self::$options['connect_timeout'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => $ua,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } elseif ($method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if ($body !== null && $body !== '') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }
            if ($hdr) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
            }

            $result = curl_exec($ch);
            $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);

            if ($result !== false && $code >= 200 && $code < 400) {
                return (string)$result;
            }

            // Cho phép trả về body của lỗi 4xx (vd 401 Unauthorized) để caller đọc message
            if (!empty($opts['allow_error']) && $result !== false && $code >= 400 && $code < 600) {
                return (string)$result;
            }

            $lastErr = "HTTP {$code} " . ($err ?: 'unknown');
            $attempt++;
            if ($attempt <= $retries) {
                usleep(300000 * $attempt);
            }
        }

        logger("HTTP {$method} {$url} failed: {$lastErr}", 'warning');
        return null;
    }

    /** HEAD/GET để kiểm tra tồn tại, trả về status code */
    public static function status(string $url, array $opts = []): int
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $opts['timeout'] ?? 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => self::$options['user_agent'],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
}
