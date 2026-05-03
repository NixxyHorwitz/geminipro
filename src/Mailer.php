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
     * Build Google Gift-style activation email HTML
     * Designed to closely resemble official Google gift/invite emails
     */
    public static function buildActivationEmail(
        string $toName,
        string $activationLink,
        string $productName = 'Google AI Pro',
        string $duration    = '12 bulan'
    ): string {
        $year    = date('Y');
        $expDate = date('d M Y', strtotime('+7 days'));
        return <<<HTML
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title>You've received a Google One gift</title>
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f1f3f4;font-family:'Google Sans','Roboto',Arial,sans-serif;-webkit-font-smoothing:antialiased">

<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;color:#f1f3f4">
  {$toName}, Anda mendapatkan hadiah {$productName} selama {$duration} — klik untuk klaim sekarang.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
</div>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f1f3f4;min-width:100%">
<tr><td align="center" style="padding:24px 16px 40px">

  <!-- Card wrapper -->
  <table width="600" cellpadding="0" cellspacing="0" role="presentation"
         style="background:#ffffff;border-radius:8px;overflow:hidden;
                box-shadow:0 1px 3px rgba(0,0,0,0.12),0 1px 2px rgba(0,0,0,0.08);
                max-width:100%">

    <!-- Google Top Bar -->
    <tr>
      <td style="background:#ffffff;padding:16px 24px;border-bottom:1px solid #e8eaed">
        <table cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td style="vertical-align:middle;padding-right:8px">
              <!-- Google "G" logo SVG -->
              <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
            </td>
            <td style="vertical-align:middle">
              <span style="font-size:18px;font-weight:400;color:#202124;letter-spacing:-.3px">Google</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Hero: Gift Banner -->
    <tr>
      <td style="background:linear-gradient(160deg,#1a73e8 0%,#185abc 40%,#0d47a1 100%);padding:40px 32px 0;text-align:center">
        <!-- Gift box illustration (SVG inline) -->
        <div style="margin-bottom:20px">
          <svg width="96" height="96" viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="40" width="80" height="50" rx="4" fill="rgba(255,255,255,0.15)"/>
            <rect x="8" y="40" width="80" height="50" rx="4" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="2"/>
            <rect x="4" y="28" width="88" height="16" rx="4" fill="rgba(255,255,255,0.2)"/>
            <rect x="4" y="28" width="88" height="16" rx="4" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
            <rect x="42" y="28" width="12" height="62" fill="rgba(255,255,255,0.25)"/>
            <ellipse cx="48" cy="28" rx="14" ry="8" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
            <path d="M48 6 C48 6 36 6 36 18 C36 24 42 28 48 28 C48 28 48 18 48 6Z" fill="#FBBC04"/>
            <path d="M48 6 C48 6 60 6 60 18 C60 24 54 28 48 28 C48 28 48 18 48 6Z" fill="#EA4335"/>
            <circle cx="48" cy="6" r="4" fill="#fff"/>
          </svg>
        </div>
        <div style="font-size:13px;font-weight:500;color:rgba(255,255,255,.75);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px">Hadiah untuk Anda</div>
        <div style="font-size:28px;font-weight:700;color:#ffffff;line-height:1.2;margin-bottom:6px">{$productName}</div>
        <div style="font-size:15px;color:rgba(255,255,255,.85);margin-bottom:32px">Langganan {$duration} • Sudah Aktif</div>

        <!-- White wave / separator -->
        <div style="height:32px;background:#fff;border-radius:50% 50% 0 0 / 32px 32px 0 0;margin:0 -32px"></div>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:0 40px 32px;background:#fff">

        <p style="font-size:16px;font-weight:400;color:#202124;margin:0 0 6px">
          Halo, <strong>{$toName}</strong> 👋
        </p>
        <p style="font-size:14px;color:#5f6368;line-height:1.65;margin:0 0 28px">
          Seseorang telah mengirimi Anda hadiah <strong>{$productName}</strong> selama <strong>{$duration}</strong>. 
          Klik tombol di bawah untuk mengklaim hadiah dan mengaktifkan semua fitur premium Google AI di akun Anda.
        </p>

        <!-- Feature highlights -->
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8f9fa;border-radius:8px;margin-bottom:28px">
          <tr>
            <td style="padding:20px 24px">
              <div style="font-size:12px;font-weight:600;color:#80868b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px">Apa yang Anda dapatkan</div>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="50%" style="padding:4px 8px 4px 0;vertical-align:top">
                    <div style="font-size:13px;color:#202124;display:flex;align-items:center;gap:6px">
                      <span style="color:#34a853;font-size:15px">✓</span> Gemini 3.1 Pro
                    </div>
                  </td>
                  <td width="50%" style="padding:4px 0 4px 8px;vertical-align:top">
                    <div style="font-size:13px;color:#202124">
                      <span style="color:#34a853;font-size:15px">✓</span> Veo 3.1 Video AI
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:4px 8px 4px 0;vertical-align:top">
                    <div style="font-size:13px;color:#202124">
                      <span style="color:#34a853;font-size:15px">✓</span> 5 TB Storage
                    </div>
                  </td>
                  <td style="padding:4px 0 4px 8px;vertical-align:top">
                    <div style="font-size:13px;color:#202124">
                      <span style="color:#34a853;font-size:15px">✓</span> 1.000 AI Credits/bln
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:4px 8px 4px 0;vertical-align:top">
                    <div style="font-size:13px;color:#202124">
                      <span style="color:#34a853;font-size:15px">✓</span> NotebookLM Plus
                    </div>
                  </td>
                  <td style="padding:4px 0 4px 8px;vertical-align:top">
                    <div style="font-size:13px;color:#202124">
                      <span style="color:#34a853;font-size:15px">✓</span> Deep Research
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- CTA Button -->
        <div style="text-align:center;margin-bottom:24px">
          <a href="{$activationLink}"
             style="display:inline-block;background:#1a73e8;color:#ffffff;text-decoration:none;
                    padding:14px 40px;border-radius:100px;font-size:15px;font-weight:600;
                    letter-spacing:.01em;box-shadow:0 1px 3px rgba(0,0,0,.2)">
            Klaim Hadiah Sekarang
          </a>
        </div>

        <!-- Expiry notice -->
        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:12px 16px;text-align:center;margin-bottom:24px">
          <span style="font-size:12px;color:#977200">
            ⏰ Klaim sebelum <strong>{$expDate}</strong> agar hadiah tidak kedaluwarsa
          </span>
        </div>

        <!-- Fallback link -->
        <div style="background:#f8f9fa;border-radius:6px;padding:14px 18px;margin-bottom:4px">
          <div style="font-size:11px;color:#80868b;margin-bottom:5px">Atau salin link ini ke browser Anda:</div>
          <div style="font-size:11px;color:#1a73e8;word-break:break-all;font-family:'Roboto Mono',monospace">{$activationLink}</div>
        </div>

        <p style="font-size:11px;color:#9aa0a6;line-height:1.6;margin:16px 0 0">
          Jika Anda tidak merasa memesan {$productName}, abaikan email ini. Hadiah hanya bisa diklaim sekali dan tidak dapat dipindahtangankan.
        </p>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f8f9fa;padding:20px 40px;border-top:1px solid #e8eaed">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
          <tr>
            <td align="center">
              <!-- Footer Google logo small -->
              <svg width="60" height="20" viewBox="0 0 60 20" xmlns="http://www.w3.org/2000/svg" style="margin-bottom:8px">
                <text x="0" y="16" font-family="Arial,sans-serif" font-size="14" font-weight="400" fill="#80868b">Google</text>
              </svg>
              <p style="font-size:11px;color:#9aa0a6;margin:0 0 4px;text-align:center">
                &copy; {$year} Google LLC, 1600 Amphitheatre Parkway, Mountain View, CA 94043
              </p>
              <p style="font-size:11px;color:#bdc1c6;margin:0;text-align:center">
                Email ini dikirim oleh sistem otomatis. Mohon tidak membalas email ini.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

  </table>
  <!-- /Card wrapper -->

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
