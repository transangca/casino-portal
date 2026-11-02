<?php
namespace App\Services\Ws;

/**
 * WebSocket client tối giải (RFC 6455) — không cần thư viện ngoài.
 *
 * Dùng để đọc dữ liệu lobby của nhà phát hành (Deno Casino / CQ9 live)
 * để đồng bộ danh sách bàn về hệ thống.
 *
 * Hỗ trợ: client-side masking (bắt buộc theo spec), text frame,
 * fragmentation, ping/pong, close.
 *
 * Socket chạy non-blocking + stream_select để không bao giờ treo.
 */
class WsClient
{
    /** @var resource|null */
    private $sock = null;

    private string $host;
    private int    $port;
    private string $path;
    private bool   $secure;

    /** Timeout cho từng lần đọc (giây) */
    private int $timeout = 15;

    /** Hạn chót tổng thời gian sống của kết nối (timestamp) */
    private float $deadline = 0.0;

    public function __construct(string $host, int $port, string $path = '/', bool $secure = true)
    {
        $this->host   = $host;
        $this->port   = $port;
        $this->path   = $path === '' ? '/' : $path;
        $this->secure = $secure;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * Mở kết nối + bắt tay (handshake).
     */
    public function connect(): bool
    {
        $host = $this->host;
        $port = $this->port;
        $ssl  = $this->secure ? 'ssl://' : 'tcp://';

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true,
                'peer_name'         => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            $ssl . $host . ':' . $port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$sock) {
            throw new \RuntimeException('WS connect failed: ' . $errstr . ' (' . $errno . ')');
        }

        $this->sock = $sock;
        $this->deadline = microtime(true) + $this->timeout * 3;

        // ---------- Handshake ----------
        $key = base64_encode(random_bytes(16));
        $req = "GET " . $this->path . " HTTP/1.1\r\n"
             . "Host: " . $host . ":" . $port . "\r\n"
             . "Upgrade: websocket\r\n"
             . "Connection: Upgrade\r\n"
             . "Sec-WebSocket-Key: " . $key . "\r\n"
             . "Sec-WebSocket-Version: 13\r\n"
             . "User-Agent: Mozilla/5.0\r\n"
             . "\r\n";

        fwrite($sock, $req);

        $header = '';
        while (!str_contains($header, "\r\n\r\n")) {
            if (microtime(true) > $this->deadline) {
                $this->close();
                throw new \RuntimeException('WS handshake timeout');
            }
            $chunk = fread($sock, 1024);
            if ($chunk === '' || $chunk === false) {
                $this->close();
                throw new \RuntimeException('WS handshake closed');
            }
            $header .= $chunk;
        }

        if (!preg_match('#HTTP/1\.1 101#i', $header)) {
            $this->close();
            throw new \RuntimeException('WS handshake rejected: ' . substr($header, 0, 160));
        }

        // Non-blocking sau handshake để read() có thể dùng select()
        stream_set_blocking($sock, false);

        return true;
    }

    /**
     * Gửi text frame (server→client không mask, client→server phải mask).
     */
    public function send(string $payload): bool
    {
        if (!$this->sock) {
            return false;
        }
        return $this->writeFrame(0x1, $payload);
    }

    private function writeFrame(int $opcode, string $payload): bool
    {
        $len  = strlen($payload);
        $head = chr(0x80 | $opcode);

        if ($len < 126) {
            $head .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $head .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $head .= chr(0x80 | 127) . pack('J', $len);
        }

        // Client bắt buộc mask frame
        $mask = random_bytes(4);
        $head .= $mask;

        $masked = '';
        for ($i = 0, $n = $len; $i < $n; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        $written = 0;
        $out = $head . $masked;
        $total = strlen($out);

        while ($written < $total) {
            $n = @fwrite($this->sock, substr($out, $written));
            if ($n === false || $n === 0) {
                // Chờ socket sẵn sàng rồi thử lại
                $w = [$this->sock];
                $r = $e = null;
                if (@stream_select($r, $w, $e, 0, 200000) === false) {
                    return false;
                }
                continue;
            }
            $written += $n;
        }

        return true;
    }

    /**
     * Đọc 1 frame đầy đủ. Trả null nếu đóng kết nối hoặc hết thời gian.
     */
    public function read(): ?string
    {
        if (!$this->sock) {
            return null;
        }

        $opcode = null;
        $buffer = '';

        while (true) {
            $h = $this->readExact(2);
            if ($h === null) {
                return null;
            }

            $b0 = ord($h[0]);
            $b1 = ord($h[1]);

            $fin    = ($b0 & 0x80) !== 0;
            $op     = $b0 & 0x0F;
            $masked = ($b1 & 0x80) !== 0;
            $len    = $b1 & 0x7F;

            if ($len === 126) {
                $ext = $this->readExact(2);
                if ($ext === null) {
                    return null;
                }
                $len = unpack('n', $ext)[1];
            } elseif ($len === 127) {
                $ext = $this->readExact(8);
                if ($ext === null) {
                    return null;
                }
                $len = unpack('J', $ext)[1];
            }

            $payload = '';
            if ($len > 0) {
                $payload = $this->readExact($len);
                if ($payload === null) {
                    return null;
                }
            }

            if ($masked) {
                $mk = $this->readExact(4);
                if ($mk === null) {
                    return null;
                }
                $out = '';
                for ($i = 0; $i < $len; $i++) {
                    $out .= $payload[$i] ^ $mk[$i % 4];
                }
                $payload = $out;
            }

            if ($op === 0x8) {           // close
                $this->close();
                return null;
            }
            if ($op === 0x9) {           // ping → pong
                $this->writeFrame(0xA, $payload);
                continue;
            }
            if ($op === 0xA) {           // pong
                continue;
            }

            if ($opcode === null) {
                $opcode = $op;
            }
            $buffer .= $payload;

            if ($fin) {
                return $buffer;
            }
        }
    }

    /**
     * Đọc chính xác N byte (non-blocking + select).
     */
    private function readExact(int $length): ?string
    {
        $out = '';
        $limit = microtime(true) + $this->timeout;

        while (strlen($out) < $length) {
            if (microtime(true) > $limit) {
                return null;
            }

            $r = [$this->sock];
            $w = $e = null;

            $ready = @stream_select($r, $w, $e, 0, 200000);
            if ($ready === false) {
                return null;
            }
            if ($ready === 0) {
                continue;
            }

            $chunk = @fread($this->sock, $length - strlen($out));
            if ($chunk === false || $chunk === '') {
                // Không còn dữ liệu → đóng kết nối
                if (feof($this->sock)) {
                    return null;
                }
                continue;
            }
            $out .= $chunk;
        }

        return $out;
    }

    public function close(): void
    {
        if ($this->sock) {
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
