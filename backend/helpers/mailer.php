<?php
// backend/helpers/mailer.php

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'noreply@nikahin.app');
define('APP_URL',   getenv('APP_URL')   ?: 'http://localhost');

/**
 * Send invitation email via SMTP (uses PHPMailer if available, falls back to mail())
 */
function send_invitation_email(string $to, string $name, string $link, string $groomBride): bool {
    $subject = "Undangan Pernikahan {$groomBride}";
    $html = invitation_email_template($name, $link, $groomBride);

    // Try PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host       = SMTP_HOST;
            $m->SMTPAuth   = true;
            $m->Username   = SMTP_USER;
            $m->Password   = SMTP_PASS;
            $m->SMTPSecure = 'tls';
            $m->Port       = SMTP_PORT;
            $m->setFrom(SMTP_FROM, 'nikahin');
            $m->addAddress($to, $name);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body    = $html;
            $m->send();
            return true;
        } catch (\Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: PHP mail()
    $headers = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=UTF-8",
        "From: nikahin <" . SMTP_FROM . ">",
    ]);
    return mail($to, $subject, $html, $headers);
}

function invitation_email_template(string $name, string $link, string $groomBride): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#0d0a14;font-family:Georgia,serif;">
<div style="max-width:520px;margin:40px auto;background:#1a1225;border-radius:16px;overflow:hidden;border:1px solid #4a1d7a;">
  <div style="background:linear-gradient(135deg,#2d1b4e,#1a0d2e);padding:48px 32px;text-align:center;">
    <p style="color:#d4b896;font-size:13px;letter-spacing:4px;margin:0 0 16px;">nikahin</p>
    <h1 style="color:#f5e6d3;font-size:28px;margin:0;font-weight:400;">Anda Diundang</h1>
  </div>
  <div style="padding:40px 32px;text-align:center;">
    <p style="color:#c8a882;font-size:16px;line-height:1.7;margin:0 0 8px;">Kepada Yth.</p>
    <p style="color:#f5e6d3;font-size:20px;margin:0 0 32px;">{$name}</p>
    <p style="color:#a89080;font-size:15px;line-height:1.8;margin:0 0 32px;">
      Dengan penuh kebahagiaan, kami mengundang Anda untuk turut merayakan<br>
      momen sakral pernikahan kami
    </p>
    <p style="color:#d4b896;font-size:22px;margin:0 0 40px;font-style:italic;">{$groomBride}</p>
    <a href="{$link}" style="display:inline-block;background:linear-gradient(135deg,#6d28d9,#4c1d95);color:#fff;text-decoration:none;padding:16px 40px;border-radius:50px;font-size:15px;letter-spacing:1px;">
      Buka Undangan
    </a>
    <p style="color:#6b5a72;font-size:12px;margin:40px 0 0;">{$link}</p>
  </div>
</div>
</body>
</html>
HTML;
}

/**
 * Build WhatsApp deep-link blast URL
 */
function whatsapp_link(string $phone, string $name, string $link, string $groomBride): string {
    $msg = "Hai {$name},\nKami dengan penuh kebahagiaan mengundang Anda ke pernikahan kami:\n*{$groomBride}*\n\nBuka undangan di sini:\n{$link}";
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
}
