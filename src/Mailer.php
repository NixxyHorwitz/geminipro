<?php
declare(strict_types=1);

namespace App;

/**
 * Mailer — Google SMTP email sender using PHPMailer via stream/cURL
 * No composer required. Uses PHP's built-in stream sockets.
 *
 * Supports Gmail SMTP via TLS (port 587) or SSL (port 465).
 * Config keys stored in DB: smtp_host, smtp_port, smtp_user, smtp_pass,
 *                            smtp_from, smtp_from_name, smtp_secure
 */
class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;
    private string $secure; // 'tls' or 'ssl'

    /** Last error message */
    public string $lastError = '';

    public function __construct(
        string $host     = '',
        int    $port     = 587,
        string $user     = '',
        string $pass     = '',
        string $from     = '',
        string $fromName = 'Google AI Pro',
        string $secure   = 'tls'
    ) {
        $this->host     = $host;
        $this->port     = $port;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->from     = $from ?: $user;
        $this->fromName = $fromName;
        $this->secure   = strtolower($secure);
    }

    /** Build from DB config */
    public static function fromConfig(): self
    {
        return new self(
            Config::get('smtp_host',      'smtp.gmail.com'),
            (int) Config::get('smtp_port', '587'),
            Config::get('smtp_user',      ''),
            Config::get('smtp_pass',      ''),
            Config::get('smtp_from',      ''),
            Config::get('smtp_from_name', 'Google AI Pro'),
            Config::get('smtp_secure',    'tls'),
        );
    }

    /**
     * Send email via SMTP with socket (no external library needed)
     *
     * @param string|array $to  email or ['email'=>'name',...] or 'email1,email2'
     * @param string $subject
     * @param string $htmlBody  HTML content
     * @param string $textBody  Plain-text fallback (auto-generated if empty)
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        if (!$this->host || !$this->user || !$this->pass) {
            $this->lastError = 'SMTP belum dikonfigurasi (host/user/pass kosong).';
            return false;
        }

        // Normalize $to
        $recipients = [];
        if (is_string($to)) {
            foreach (explode(',', $to) as $t) {
                $t = trim($t);
                if ($t) $recipients[] = ['email' => $t, 'name' => ''];
            }
        } else {
            foreach ($to as $email => $name) {
                if (is_int($email)) {
                    $recipients[] = ['email' => $name, 'name' => ''];
                } else {
                    $recipients[] = ['email' => $email, 'name' => $name];
                }
            }
        }

        if (empty($recipients)) {
            $this->lastError = 'Tidak ada penerima email.';
            return false;
        }

        if (!$textBody) {
            $textBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
        }

        // Build MIME message
        $boundary = '=_Part_' . md5(uniqid('', true));
        $msgId    = '<' . uniqid('', true) . '@googlaipro>';
        $date     = date('r');

        $toHeader = implode(', ', array_map(
            fn($r) => $r['name'] ? '"' . $r['name'] . '" <' . $r['email'] . '>' : $r['email'],
            $recipients
        ));

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$this->fromName} <{$this->from}>\r\n";
        $headers .= "To: {$toHeader}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: GoogleAIPro-Mailer/1.0\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $rawMessage = $headers . "\r\n" . $body;

        // Connect via socket
        try {
            return $this->smtpSend($recipients, $rawMessage);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private function smtpSend(array $recipients, string $rawMessage): bool
    {
        $timeout = 15;

        // Choose transport
        if ($this->secure === 'ssl') {
            $transport = "ssl://{$this->host}";
        } else {
            $transport = $this->host; // TLS: STARTTLS upgrade after plain connect
        }

        $errno = 0; $errstr = '';
        $sock = @fsockopen($transport, $this->port, $errno, $errstr, $timeout);

        if (!$sock) {
            // Try with SSL wrapper as fallback
            $sock = @fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, $timeout);
            if (!$sock) {
                throw new \RuntimeException("Gagal konek ke SMTP {$this->host}:{$this->port} — {$errstr} ({$errno})");
            }
        }

        stream_set_timeout($sock, $timeout);

        $read = function () use ($sock): string {
            $buf = '';
            while (!feof($sock)) {
                $line = fgets($sock, 512);
                $buf .= $line;
                if ($line !== false && strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $buf;
        };

        $cmd = function (string $c) use ($sock, $read): string {
            fwrite($sock, $c . "\r\n");
            return $read();
        };

        // server greeting
        $resp = $read();
        if (!str_starts_with(trim($resp), '2')) {
            fclose($sock);
            throw new \RuntimeException("SMTP greeting error: {$resp}");
        }

        // EHLO
        $resp = $cmd("EHLO googlaipro.local");

        // STARTTLS if TLS mode
        if ($this->secure === 'tls') {
            $resp = $cmd("STARTTLS");
            if (!str_starts_with($resp, '220')) {
                fclose($sock);
                throw new \RuntimeException("STARTTLS not accepted: {$resp}");
            }
            // Upgrade socket
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                throw new \RuntimeException("TLS handshake gagal.");
            }
            $resp = $cmd("EHLO googlaipro.local");
        }

        // AUTH LOGIN
        $resp = $cmd("AUTH LOGIN");
        $resp = $cmd(base64_encode($this->user));
        $resp = $cmd(base64_encode($this->pass));
        if (!str_starts_with($resp, '235')) {
            fclose($sock);
            // Strip base64 pass from error
            throw new \RuntimeException("Auth gagal — cek username/password SMTP. ({$resp})");
        }

        // MAIL FROM
        $resp = $cmd("MAIL FROM:<{$this->from}>");
        if (!str_starts_with($resp, '250')) {
            fclose($sock);
            throw new \RuntimeException("MAIL FROM ditolak: {$resp}");
        }

        // RCPT TO
        foreach ($recipients as $r) {
            $resp = $cmd("RCPT TO:<{$r['email']}>");
            if (!str_starts_with($resp, '250') && !str_starts_with($resp, '251')) {
                fclose($sock);
                throw new \RuntimeException("RCPT TO {$r['email']} ditolak: {$resp}");
            }
        }

        // DATA
        $cmd("DATA");
        fwrite($sock, $rawMessage . "\r\n.\r\n");
        $resp = $read();
        $cmd("QUIT");
        fclose($sock);

        if (!str_starts_with($resp, '250')) {
            throw new \RuntimeException("DATA ditolak server: {$resp}");
        }

        return true;
    }

    /**
     * Build Google-style activation email HTML
     */
    public static function buildActivationEmail(
        string $toName,
        string $activationLink,
        string $productName = 'Google AI Pro',
        string $duration    = '12 bulan'
    ): string {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f9fa;font-family:'Google Sans',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);max-width:calc(100vw - 32px)">
      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#1a73e8,#4285f4);padding:32px;text-align:center">
        <div style="font-size:24px;font-weight:700;color:#fff;letter-spacing:-.5px">{$productName}</div>
        <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:4px">Link Aktivasi Langganan</div>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:36px 40px">
        <p style="font-size:15px;color:#202124;margin:0 0 8px">Halo, <strong>{$toName}</strong></p>
        <p style="font-size:14px;color:#5f6368;line-height:1.6;margin:0 0 24px">
          Terima kasih telah berlangganan <strong>{$productName}</strong> selama <strong>{$duration}</strong>.<br>
          Klik tombol di bawah untuk mengaktifkan akun Google AI Pro Anda:
        </p>
        <div style="text-align:center;margin:28px 0">
          <a href="{$activationLink}" style="display:inline-block;background:#1a73e8;color:#fff;text-decoration:none;padding:14px 36px;border-radius:100px;font-size:15px;font-weight:600;letter-spacing:.01em">
            Aktifkan Sekarang
          </a>
        </div>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px 20px;margin:24px 0">
          <div style="font-size:12px;color:#80868b;margin-bottom:4px">Atau copy link di bawah ke browser Anda:</div>
          <div style="font-size:12px;color:#1a73e8;word-break:break-all;font-family:monospace">{$activationLink}</div>
        </div>
        <p style="font-size:12px;color:#9aa0a6;line-height:1.6;margin:0">
          Link ini akan kadaluarsa dalam <strong>24 jam</strong>. Jika Anda tidak meminta aktivasi ini, abaikan email ini.
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #e8eaed">
        <p style="font-size:12px;color:#9aa0a6;margin:0">&copy; {$year} {$productName} Reseller. Bukan afiliasi resmi Google LLC.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Build simple custom HTML email
     */
    public static function buildCustomEmail(string $toName, string $subject, string $content, string $productName = 'Google AI Pro'): string
    {
        $year = date('Y');
        $contentHtml = nl2br(htmlspecialchars($content));
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f8f9fa;font-family:'Google Sans',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);max-width:calc(100vw - 32px)">
      <tr><td style="background:linear-gradient(135deg,#1a73e8,#4285f4);padding:28px 32px;text-align:center">
        <div style="font-size:20px;font-weight:700;color:#fff">{$productName}</div>
      </td></tr>
      <tr><td style="padding:32px 40px">
        <h2 style="margin:0 0 16px;font-size:18px;color:#202124">{$subject}</h2>
        <div style="font-size:14px;color:#5f6368;line-height:1.7">{$contentHtml}</div>
      </td></tr>
      <tr><td style="background:#f8f9fa;padding:16px 32px;text-align:center;border-top:1px solid #e8eaed">
        <p style="font-size:12px;color:#9aa0a6;margin:0">&copy; {$year} {$productName} Reseller</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
