<?php
/**
 * HTTP client tối giảu mô phỏng trình duyệt: tự theo dõi cookie từ header
 * Set-Cookie (PHP session cookie không có Expires nên curl cookie jar hay bị rơi).
 */
class HttpClient
{
    public string $base;
    /** @var array<string,string> */
    private array $cookies = [];

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    /** @return array{code:int, body:string, headers:string} */
    private function request(string $method, string $path, ?array $post = null): array
    {
        $ch = curl_init($this->base . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($post !== null) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        if ($this->cookies) {
            $parts = [];
            foreach ($this->cookies as $k => $v) {
                $parts[] = $k . '=' . $v;
            }
            $opts[CURLOPT_COOKIE] = implode('; ', $parts);
        }
        curl_setopt_array($ch, $opts);
        $raw = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $sep = strpos($raw, "\r\n\r\n");
        $head = $sep === false ? $raw : substr($raw, 0, $sep);
        $body = $sep === false ? '' : substr($raw, $sep + 4);

        if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $head, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                $name = trim($c[1]);
                $val  = trim($c[2]);
                if ($val === '' || $val === 'deleted') {
                    unset($this->cookies[$name]);
                } else {
                    $this->cookies[$name] = $val;
                }
            }
        }

        return ['code' => $code, 'body' => $body, 'headers' => $head];
    }

    public function get(string $path): string
    {
        $r = $this->request('GET', $path);
        if ($r['code'] !== 200) {
            echo "   [HTTP] {$path} → HTTP {$r['code']} loc=" . $this->loc($r['headers']) . " (" . strlen($r['body']) . " bytes)\n";
        }
        return $r['body'];
    }

    public function post(string $path, array $post): string
    {
        $r = $this->request('POST', $path, $post);
        if ($r['code'] !== 302 && $r['code'] !== 200) {
            echo "   [HTTP] POST {$path} → HTTP {$r['code']}\n";
        }
        return $r['body'];
    }

    public function code(string $path): int
    {
        return $this->request('GET', $path)['code'];
    }

    private function loc(string $head): string
    {
        return preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
    }

    /** @return array<string,string> */
    public function cookies(): array
    {
        return $this->cookies;
    }
}
