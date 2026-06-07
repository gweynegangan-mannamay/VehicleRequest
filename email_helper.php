<?php
// ============================================================
//  VehiQuest Gmail Mailer
//  Sends email via Gmail SMTP (free, no library needed).
//  Uses your Gmail + App Password from config/mailer_config.php
//
//  Gmail App Password setup:
//  1. Enable 2-Step Verification on your Google account
//  2. Go to: https://myaccount.google.com/apppasswords
//  3. Create an app password and paste it in mailer_config.php
// ============================================================

require_once __DIR__ . '/../config/mailer_config.php';

/**
 * Core SMTP sender — connects to Gmail and delivers one email.
 *
 * @param string $toEmail   Recipient address
 * @param string $toName    Recipient display name
 * @param string $subject   Subject line
 * @param string $htmlBody  HTML body
 * @param string $textBody  Plain-text fallback (auto-stripped from HTML if empty)
 * @return bool
 */
function sendGmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {

    $host     = 'smtp.gmail.com';
    $port     = 587;
    $username = MAIL_USERNAME;
    $password = MAIL_PASSWORD;
    $from     = MAIL_FROM_EMAIL;
    $fromName = MAIL_FROM_NAME;

    if (empty($textBody)) {
        $textBody = wordwrap(strip_tags($htmlBody), 75, "\r\n");
    }

    // ── 1. Open plain TCP socket ─────────────────────────────
    $errno  = 0;
    $errstr = '';
    $sock   = fsockopen("tcp://{$host}", $port, $errno, $errstr, 15);

    if (!$sock) {
        error_log("[VehiQuest Mail] Cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($sock, 15);

    // Helper: write a line and return the server response
    $cmd = function (string $line) use ($sock): string {
        fwrite($sock, $line . "\r\n");
        $resp = '';
        while (!feof($sock)) {
            $chunk = fgets($sock, 512);
            $resp .= $chunk;
            // A response line with a space at position 3 is the last line
            if (isset($chunk[3]) && $chunk[3] === ' ') break;
        }
        return trim($resp);
    };

    // Helper: read server greeting without sending anything
    $read = function () use ($sock): string {
        $resp = '';
        while (!feof($sock)) {
            $chunk = fgets($sock, 512);
            $resp .= $chunk;
            if (isset($chunk[3]) && $chunk[3] === ' ') break;
        }
        return trim($resp);
    };

    try {
        // ── 2. SMTP handshake ────────────────────────────────
        $read();                            // 220 smtp.gmail.com ready
        $cmd("EHLO localhost");             // list capabilities
        $r = $cmd("STARTTLS");             // request TLS upgrade

        if (strpos($r, '220') === false) {
            error_log("[VehiQuest Mail] STARTTLS rejected: {$r}");
            fclose($sock);
            return false;
        }

        // ── 3. Upgrade to TLS ────────────────────────────────
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            // Fallback to any TLS version
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        // ── 4. Re-introduce after TLS ────────────────────────
        $cmd("EHLO localhost");

        // ── 5. Authenticate ──────────────────────────────────
        $cmd("AUTH LOGIN");
        $cmd(base64_encode($username));
        $r = $cmd(base64_encode($password));

        if (strpos($r, '235') === false) {
            error_log("[VehiQuest Mail] AUTH failed ({$username}): {$r}");
            fclose($sock);
            return false;
        }

        // ── 6. Envelope ──────────────────────────────────────
        $cmd("MAIL FROM:<{$from}>");
        $cmd("RCPT TO:<{$toEmail}>");
        $cmd("DATA");

        // ── 7. Build MIME message ────────────────────────────
        $boundary = 'vq_' . bin2hex(random_bytes(8));
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromEncoded    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $toEncoded      = '=?UTF-8?B?' . base64_encode($toName)   . '?=';

        $headers  = "Date: " . date('r') . "\r\n";
        $headers .= "From: {$fromEncoded} <{$from}>\r\n";
        $headers .= "To: {$toEncoded} <{$toEmail}>\r\n";
        $headers .= "Subject: {$subjectEncoded}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: VehiQuest/1.0\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // Send headers + body, then the DATA terminator on its own line
        fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");

        // Read the 250 OK response
        $r = $read();

        $cmd("QUIT");
        fclose($sock);

        if (strpos($r, '250') === false) {
            error_log("[VehiQuest Mail] Message rejected by server: {$r}");
            return false;
        }

        return true;

    } catch (\Throwable $e) {
        error_log("[VehiQuest Mail] Exception: " . $e->getMessage());
        @fclose($sock);
        return false;
    }
}

// ── Public API ───────────────────────────────────────────────

/**
 * Send the "trip ticket ready" notification email to a user.
 */
function sendTicketReadyEmail(
    string $toEmail,
    string $toName,
    string $destination,
    string $departureDate,
    string $driverName,
    string $vehicleName,
    string $plateNumber,
    int    $tripId = 0
): bool {
    $subject = "Your Trip Ticket is Ready – {$destination}";
    $html    = _ticketEmailHtml($toName, $destination, $departureDate, $driverName, $vehicleName, $plateNumber, $tripId);
    $plain   = _ticketEmailText($toName, $destination, $departureDate, $driverName, $vehicleName, $plateNumber, $tripId);

    return sendGmail($toEmail, $toName, $subject, $html, $plain);
}

// ── Email templates ──────────────────────────────────────────

function _ticketEmailHtml(
    string $name, string $destination, string $departureDate,
    string $driverName, string $vehicleName, string $plateNumber,
    int $tripId = 0
): string {
    $n   = htmlspecialchars($name);
    $d   = htmlspecialchars($destination);
    $dt  = htmlspecialchars($departureDate);
    $dr  = htmlspecialchars($driverName);
    $v   = htmlspecialchars($vehicleName);
    $p   = htmlspecialchars($plateNumber);
    $dashboardUrl = BASE_URL . '/user/index.php';
    $ticketUrl    = $tripId > 0 ? BASE_URL . '/user/ticket.php?trip_id=' . $tripId : $dashboardUrl;

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Segoe UI,Arial,sans-serif;">
<div style="max-width:600px;margin:30px auto;background:#fff;border-radius:10px;
            overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1);">

  <!-- Header -->
  <div style="background:linear-gradient(135deg,#1e7e34,#f39c12);padding:28px 32px;">
    <h1 style="color:#fff;margin:0;font-size:22px;">🚗 VehiQuest</h1>
    <p style="color:rgba(255,255,255,.85);margin:4px 0 0;font-size:13px;">
      Isabela State University — Ilagan Campus
    </p>
  </div>

  <!-- Body -->
  <div style="padding:32px;">
    <h2 style="color:#1e7e34;margin-top:0;">Your Trip Ticket is Ready!</h2>
    <p style="font-size:15px;color:#333;">Hi <strong>{$n}</strong>,</p>
    <p style="font-size:14px;color:#555;">
      Great news! Your trip request has been <strong>approved</strong> and your ticket is now ready.
      Here are your trip details:
    </p>

    <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;
                  border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
      <tr style="background:#f0f9f2;">
        <td style="padding:12px 16px;font-weight:600;color:#444;width:42%;
                   border-bottom:1px solid #e0e0e0;">Destination</td>
        <td style="padding:12px 16px;color:#222;border-bottom:1px solid #e0e0e0;">{$d}</td>
      </tr>
      <tr>
        <td style="padding:12px 16px;font-weight:600;color:#444;border-bottom:1px solid #e0e0e0;">
          Departure Date</td>
        <td style="padding:12px 16px;color:#222;border-bottom:1px solid #e0e0e0;">{$dt}</td>
      </tr>
      <tr style="background:#f0f9f2;">
        <td style="padding:12px 16px;font-weight:600;color:#444;border-bottom:1px solid #e0e0e0;">
          Driver</td>
        <td style="padding:12px 16px;color:#222;border-bottom:1px solid #e0e0e0;">{$dr}</td>
      </tr>
      <tr>
        <td style="padding:12px 16px;font-weight:600;color:#444;border-bottom:1px solid #e0e0e0;">
          Vehicle</td>
        <td style="padding:12px 16px;color:#222;border-bottom:1px solid #e0e0e0;">{$v}</td>
      </tr>
      <tr style="background:#f0f9f2;">
        <td style="padding:12px 16px;font-weight:600;color:#444;">Plate Number</td>
        <td style="padding:12px 16px;color:#222;">{$p}</td>
      </tr>
    </table>

    <p style="font-size:14px;color:#555;">
      Please coordinate with the assigned driver for final arrangements.
    </p>

    <div style="text-align:center;margin:28px 0;">
      <a href="{$ticketUrl}"
         style="background:linear-gradient(135deg,#1e7e34,#f39c12);color:#fff;
                padding:13px 32px;border-radius:25px;text-decoration:none;
                font-weight:bold;font-size:14px;display:inline-block;margin:0 6px;">
        📄 View &amp; Save Ticket
      </a>
      <a href="{$dashboardUrl}"
         style="background:#6c757d;color:#fff;
                padding:13px 32px;border-radius:25px;text-decoration:none;
                font-weight:bold;font-size:14px;display:inline-block;margin:0 6px;">
        My Requests
      </a>
    </div>

    <p style="font-size:14px;color:#333;">
      Safe travels! 🚗<br><strong>VehiQuest Team</strong>
    </p>
  </div>

  <!-- Footer -->
  <div style="background:#f8f9fa;padding:16px 32px;text-align:center;
              font-size:12px;color:#999;border-top:1px solid #e0e0e0;">
    This is an automated message — please do not reply.<br>
    <strong>Isabela State University — Ilagan Campus</strong>
  </div>

</div>
</body>
</html>
HTML;
}

function _ticketEmailText(
    string $name, string $destination, string $departureDate,
    string $driverName, string $vehicleName, string $plateNumber,
    int $tripId = 0
): string {
    $ticketUrl = $tripId > 0
        ? BASE_URL . '/user/ticket.php?trip_id=' . $tripId
        : BASE_URL . '/user/index.php';

    return "Hi {$name},\n\n"
         . "Your trip ticket is ready!\n\n"
         . "Destination  : {$destination}\n"
         . "Departure    : {$departureDate}\n"
         . "Driver       : {$driverName}\n"
         . "Vehicle      : {$vehicleName}\n"
         . "Plate Number : {$plateNumber}\n\n"
         . "View and save your ticket as PDF: {$ticketUrl}\n\n"
         . "Safe travels!\n"
         . "— VehiQuest Team, ISU Ilagan";
}
