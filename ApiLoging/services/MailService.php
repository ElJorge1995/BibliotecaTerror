<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public static function sendVerificationEmail(string $email, string $name, string $verificationUrl): bool
    {
        $subject = 'Confirma tu correo electronico';
        $message = self::buildEmailLayout(
            $name,
            'Confirma tu cuenta',
            'Para activar tu cuenta, confirma tu correo con el siguiente enlace:',
            $verificationUrl,
            'Si no solicitaste este registro, puedes ignorar este mensaje.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    public static function sendEmailChangeConfirmation(string $email, string $name, string $confirmationUrl): bool
    {
        $subject = 'Confirma el cambio de correo';
        $message = self::buildEmailLayout(
            $name,
            'Confirma tu nuevo correo',
            'Has solicitado cambiar el correo de tu cuenta. Confirma el cambio con el siguiente enlace:',
            $confirmationUrl,
            'Si no has sido tu, puedes ignorar este mensaje.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    public static function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool
    {
        $subject = 'Recupera tu contrasena';
        $message = self::buildEmailLayout(
            $name,
            'Recupera tu contrasena',
            'Has solicitado restablecer tu contrasena. Usa este enlace para establecer una nueva:',
            $resetUrl,
            'Si no solicitaste este cambio, puedes ignorar este mensaje.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    private static function buildEmailLayout(
        string $name,
        string $title,
        string $intro,
        string $actionUrl,
        string $closing
    ): string {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeClosing = htmlspecialchars($closing, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<html>
  <body style="margin:0;padding:24px;background:#05060a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e3e5eb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#0f121a;border-radius:12px;overflow:hidden;border:1px solid #33394b;">
      <tr>
        <td style="padding:32px 32px 24px;background:linear-gradient(160deg,#121521,#0d1017);color:#f5f5f4;border-bottom:1px solid #2d3348;">
          <div style="font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:#ed4d4d;font-weight:700;margin-bottom:8px;">Librum Tenebris</div>
          <h1 style="margin:0;font-size:26px;line-height:1.2;">{$safeTitle}</h1>
        </td>
      </tr>
      <tr>
        <td style="padding:32px;">
          <p style="margin:0 0 16px;font-size:16px;color:#d2d7e4;">Hola {$safeName},</p>
          <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#bbc2d2;">{$safeIntro}</p>
          <p style="margin:0 0 24px;text-align:center;">
            <a href="{$safeUrl}" style="display:inline-block;padding:14px 28px;border-radius:8px;background:#ed4d4d;color:#191315;text-decoration:none;font-weight:bold;font-size:15px;">
              Abrir enlace
            </a>
          </p>
          <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#7a839e;border-top:1px solid #2d3348;padding-top:24px;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
          <p style="margin:0 0 24px;font-size:14px;line-height:1.6;word-break:break-word;">
            <a href="{$safeUrl}" style="color:#f26a6a;text-decoration:underline;">{$safeUrl}</a>
          </p>
          <p style="margin:0;font-size:14px;line-height:1.6;color:#7a839e;">{$safeClosing}</p>
        </td>
      </tr>
      <tr>
        <td style="padding:20px 32px;font-size:12px;color:#5c6480;background:#0a0c12;text-align:center;border-top:1px solid #1a1e2b;">
          Este mensaje ha sido enviado por la Librum Tenebris. El archivo nocturno definitivo.
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
    }

    private static function sendHtml(string $email, string $subject, string $message): bool
    {
        $driver = strtolower((string) (getenv('MAIL_DRIVER') ?: 'mail'));
        if ($driver === 'log') {
            return self::logEmail($email, $subject, $message);
        }

        if ($driver === 'smtp') {
            return self::sendWithSmtp($email, $subject, $message);
        }

        $from = getenv('MAIL_FROM') ?: 'no-reply@reglado.local';
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
        ];

        return mail($email, $subject, $message, implode("\r\n", $headers));
    }

    private static function sendWithSmtp(string $toEmail, string $subject, string $htmlBody): bool
    {
        $host = getenv('MAIL_HOST') ?: '';
        $port = (int) (getenv('MAIL_PORT') ?: 587);
        $username = getenv('MAIL_USERNAME') ?: '';
        $password = str_replace(' ', '', trim((string) (getenv('MAIL_PASSWORD') ?: '')));
        $fromEmail = getenv('MAIL_FROM') ?: $username;
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Reglado';
        $secure = strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls'));

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            return false;
        }

        $smtpSecure = '';
        if ($secure === 'tls') {
            $smtpSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            if ($smtpSecure !== '') {
                $mail->SMTPSecure = $smtpSecure;
            }
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = self::buildAltBody($htmlBody);

            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }

    private static function buildAltBody(string $htmlBody): string
    {
        $plain = preg_replace('/<br\\s*\\/?>/i', "\n", $htmlBody);
        $plain = preg_replace('/<\\/p>/i', "\n\n", (string) $plain);
        $plain = strip_tags((string) $plain);

        return trim(html_entity_decode($plain, ENT_QUOTES, 'UTF-8'));
    }

    private static function logEmail(string $email, string $subject, string $message): bool
    {
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        $entry = sprintf(
            "[%s] TO: %s\nSUBJECT: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $email,
            $subject,
            strip_tags($message)
        );

        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND) !== false;
    }
}
