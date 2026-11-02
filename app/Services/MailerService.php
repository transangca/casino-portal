<?php
namespace App\Services;

/**
 * Gửi email qua SMTP (không cần thư viện ngoài).
 *
 * Hỗ trợ: STARTTLS, AUTH LOGIN/PLAIN, email HTML + text thay thế.
 * Cấu hình lấy từ bảng `settings` (admin tự chỉnh trong giao diện).
 */
class MailerService
{
    private static ?string $lastError = null;

    public static function config(): array
    {
        return [
            'host'      => (string)setting('smtp_host', ''),
            'port'      => (int)setting('smtp_port', 587),
            'user'      => (string)setting('smtp_username', ''),
            'pass'      => (string)setting('smtp_password', ''),
            'encryption'=> (string)setting('smtp_encryption', 'tls'),   // none|tls|ssl
            'from_email'=> (string)setting('mail_from_email', ''),
            'from_name' => (string)setting('mail_from_name', ''),
            'host_mail' => (string)setting('mail_host', ''),
        ];
    }

    public static function isConfigured(): bool
    {
        $c = self::config();
        return $c['host'] !== '' && $c['from_email'] !== '';
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Gửi 1 email. Trả về true nếu thành công.
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): bool
    {
        $cfg = self::config();
        if ($cfg['host'] === '' || $cfg['from_email'] === '') {
            self::$lastError = 'SMTP chưa được cấu hình';
            self::log($to, $subject, 'failed', self::$lastError);
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Email người nhận không hợp lệ';
            self::log($to, $subject, 'failed', self::$lastError);
            return false;
        }

        if ($textBody === '') {
            $textBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)) ?? '');
        }

        $boundary = 'b' . bin2hex(random_bytes(8));
        $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : 'Casino Portal';

        $headers = [
            'From' => sprintf('%s <%s>', $fromName, $cfg['from_email']),
            'Reply-To' => $replyTo ?: $cfg['from_email'],
            'X-Mailer' => 'CasinoPortal/1.0',
            'MIME-Version' => '1.0',
            'Content-Type' => sprintf('multipart/alternative; boundary="%s"', $boundary),
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($textBody))
            . "\r\n--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($htmlBody))
            . "\r\n--{$boundary}--";

        try {
            $sent = self::smtpSend($cfg, $to, $subject, $headers, $body);
            self::log($to, $subject, $sent ? 'sent' : 'failed', $sent ? null : self::$lastError);
            return $sent;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            self::log($to, $subject, 'failed', self::$lastError);
            logger('mail failed to ' . $to . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    private static function smtpSend(array $cfg, string $to, string $subject, array $headers, string $body): bool
    {
        $host = $cfg['host'];
        $port = $cfg['port'];
        $scheme = $cfg['encryption'] === 'ssl' ? 'ssl://' : '';

        $errno = 0;
        $errstr = '';
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $fp = @stream_socket_client(
            $scheme . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$fp) {
            throw new \RuntimeException("Không kết nối được SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $expect = static function (string $prefix) use ($read): string {
            $resp = $read();
            if (strncmp($resp, $prefix, strlen($prefix)) !== 0) {
                throw new \RuntimeException('SMTP lỗi: ' . trim($resp));
            }
            return $resp;
        };
        $cmd = static function (string $line) use ($fp): void {
            fwrite($fp, $line . "\r\n");
        };

        try {
            $expect('220');
            $cmd('EHLO ' . ($cfg['host_mail'] ?: 'localhost'));
            $read();

            if ($cfg['encryption'] === 'tls') {
                $cmd('STARTTLS');
                $resp = $read();
                if (strncmp($resp, '220', 3) !== 0) {
                    throw new \RuntimeException('STARTTLS thất bại: ' . trim($resp));
                }
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Không bật được TLS');
                }
                $cmd('EHLO ' . ($cfg['host_mail'] ?: 'localhost'));
                $read();
            }

            if ($cfg['user'] !== '') {
                $cmd('AUTH LOGIN');
                $expect('334');
                $cmd(base64_encode($cfg['user']));
                $expect('334');
                $cmd(base64_encode($cfg['pass']));
                $expect('235');
            }

            $cmd('MAIL FROM:<' . $cfg['from_email'] . '>');
            $expect('250');
            $cmd('RCPT TO:<' . $to . '>');
            $expect('250');
            $cmd('DATA');
            $expect('354');

            $headerLines = '';
            foreach ($headers as $k => $v) {
                $headerLines .= $k . ': ' . $v . "\r\n";
            }
            $message = $headerLines . "\r\n" . $body . "\r\n.";
            fwrite($fp, $message);
            $expect('250');

            $cmd('QUIT');
            fclose($fp);
            return true;
        } catch (\Throwable $e) {
            @fclose($fp);
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    public static function template(string $name, array $vars = []): string
    {
        $siteName = (string)setting('site_name', 'Casino Portal');
        $vars += [
            'site_name'  => $siteName,
            'site_url'   => url('/'),
            'support'    => (string)setting('support_telegram', ''),
            'hotline'    => (string)setting('support_hotline', ''),
            'year'       => date('Y'),
        ];

        $html = self::renderTemplate($name, $vars);
        return self::wrap($html, $vars);
    }

    private static function renderTemplate(string $name, array $vars): string
    {
        $file = BASE_PATH . '/app/Views/emails/' . $name . '.php';
        if (is_file($file)) {
            extract($vars, EXTR_SKIP);
            ob_start();
            include $file;
            return (string)ob_get_clean();
        }
        return '<p>' . e((string)($vars['message'] ?? $name)) . '</p>';
    }

    private static function wrap(string $content, array $vars): string
    {
        $siteName = (string)($vars['site_name'] ?? 'Casino Portal');
        $year     = (string)($vars['year'] ?? date('Y'));
        $heading  = (string)($vars['heading'] ?? 'Thông báo');
        $subhead  = (string)($vars['subheading'] ?? 'Số chào thân bạn đã đăng ký');
        return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;padding:24px;background:#f4f5f9;font-family:-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,sans-serif;color:#1c1f2b}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.07)}
.hd{background:linear-gradient(135deg,#1b1035,#3a1d6b);padding:26px 28px;color:#fff}
.hd h1{margin:0;font-size:20px;font-weight:700;letter-spacing:.3px}
.hd p{margin:6px 0 0;font-size:13px;opacity:.8}
.bd{padding:26px 28px;font-size:14px;line-height:1.65}
.ft{padding:16px 28px;background:#f7f8fc;font-size:12px;color:#7b8194;text-align:center}
.btn{display:inline-block;margin:18px 0 6px;padding:12px 26px;background:linear-gradient(135deg,#f6b93b,#f08a4b);color:#1b1035;font-weight:700;border-radius:10px;text-decoration:none}
.hr{height:1px;background:#eceef5;margin:18px 0}
@media(max-width:600px){.bd,.hd,.ft{padding-left:18px;padding-right:18px}}
</style></head><body>
<div class="wrap">
  <div class="hd"><h1>{$heading}</h1><p>{$subhead}</p></div>
  <div class="bd">{$content}</div>
  <div class="ft">&copy; {$year} {$siteName}. Trang được gửi tự động, vui lòng không trả lời lại email này.</div>
</div></body></html>
HTML;
    }

    private static function log(string $to, string $subject, string $status, ?string $error = null): void
    {
        try {
            \App\Core\Database::insert('mail_logs', [
                'to_email' => $to,
                'subject'  => mb_substr($subject, 0, 250),
                'status'   => $status,
                'error'    => $error,
            ]);
        } catch (\Throwable $e) {
            logger('mail log failed: ' . $e->getMessage(), 'error');
        }
    }

    // ------------------------------------------------------------------
    // Gửi nhanh theo sự kiện
    // ------------------------------------------------------------------

    public static function notifyUser(int $userId, string $template, array $vars = []): bool
    {
        $user = \App\Core\Database::fetch('SELECT id,email,username FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return false;
        }
        $vars += [
            'username' => (string)$user['username'],
            'email'    => (string)$user['email'],
            'user_id'  => (int)$user['id'],
        ];
        $subject = $vars['subject'] ?? self::subjectFor($template);
        $html    = self::template($template, $vars);
        $ok = self::send((string)$user['email'], $subject, $html);
        if ($ok) {
            \App\Core\Database::update(
                'mail_logs',
                ['to_user_id' => $userId],
                'to_email = ? AND subject = ? AND to_user_id IS NULL',
                [(string)$user['email'], mb_substr($subject, 0, 250)]
            );
        }
        return $ok;
    }

    private static function subjectFor(string $template): string
    {
        $site = (string)setting('site_name', 'Casino Portal');
        return match ($template) {
            'register'  => "Chào mừng bạn đến với {$site}",
            'login'     => "Đăng nhập mới vào {$site}",
            'deposit'   => "Yêu cầu nạp tiền đã được ghi nhận - {$site}",
            'deposit_success' => "Nạp tiền thành công - {$site}",
            'withdraw'  => "Yêu cầu rút tiền đã được ghi nhận - {$site}",
            'withdraw_success' => "Rút tiền thành công - {$site}",
            'transfer'  => "Chuyển tiền thành công - {$site}",
            'twofa'     => "Bảo mật 2FA đã được bật - {$site}",
            'password'  => "Đặt lại mật khẩu - {$site}",
            default     => "Thông báo từ {$site}",
        };
    }
}
