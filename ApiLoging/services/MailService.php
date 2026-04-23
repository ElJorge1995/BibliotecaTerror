<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public static function sendVerificationEmail(string $email, string $name, string $verificationUrl): bool
    {
        $subject = 'Confirma tu dirección de correo — Grupo Reglado';
        $message = self::buildEmailLayout(
            $name,
            'Activa tu cuenta',
            'Gracias por registrarte en Grupo Reglado. Para completar el proceso y activar tu cuenta, confirma tu dirección de correo electrónico pulsando el botón:',
            $verificationUrl,
            'Confirmar mi correo',
            'Este enlace es válido durante 24 horas. Si no solicitaste este registro, puedes ignorar este mensaje de forma segura.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    public static function sendEmailChangeConfirmation(string $email, string $name, string $confirmationUrl): bool
    {
        $subject = 'Confirma el cambio de correo — Grupo Reglado';
        $message = self::buildEmailLayout(
            $name,
            'Confirma tu nuevo correo',
            'Has solicitado cambiar la dirección de correo asociada a tu cuenta en Grupo Reglado. Para completar el cambio, confirma la nueva dirección pulsando el botón:',
            $confirmationUrl,
            'Confirmar nueva dirección',
            'Si no has realizado esta solicitud, ignora este mensaje. Tu correo actual no se modificará.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    public static function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool
    {
        $subject = 'Restablece tu contraseña — Grupo Reglado';
        $message = self::buildEmailLayout(
            $name,
            'Restablecer contraseña',
            'Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en Grupo Reglado. Pulsa el botón para crear una nueva contraseña:',
            $resetUrl,
            'Restablecer contraseña',
            'Este enlace es válido durante 1 hora. Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual no se verá afectada.'
        );

        return self::sendHtml($email, $subject, $message);
    }

    public static function sendLoginAlert(
        array $user,
        ?string $countryName,
        string $ip,
        string $yesUrl,
        string $noUrl
    ): bool {
        // Subject informativo y no alarmista, ayuda a que Outlook/Gmail no lo
        // marquen como "security alert phishing" en el primer envío.
        $subject = 'Actividad reciente en tu cuenta Librum Tenebris';
        $message = self::buildLoginAlertLayout(
            (string) ($user['name'] ?? ''),
            $countryName ?? 'desconocido',
            $ip,
            $yesUrl,
            $noUrl
        );
        return self::sendHtml((string) $user['email'], $subject, $message);
    }

    private static function buildLoginAlertLayout(
        string $name,
        string $country,
        string $ip,
        string $yesUrl,
        string $noUrl
    ): string {
        $safeName    = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
        $safeCountry = htmlspecialchars($country, ENT_QUOTES, 'UTF-8');
        $safeIp      = htmlspecialchars($ip,      ENT_QUOTES, 'UTF-8');
        $safeYesUrl  = htmlspecialchars($yesUrl,  ENT_QUOTES, 'UTF-8');
        $safeNoUrl   = htmlspecialchars($noUrl,   ENT_QUOTES, 'UTF-8');
        $when        = htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
        $year        = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nueva ubicación detectada</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f1f5f9;mso-hide:all;">
    Inicio de sesión desde {$safeCountry}. Si has sido tú, no tienes que hacer nada.
  </div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td style="padding:32px 16px;">
        <table role="presentation" align="center" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.08);overflow:hidden;">
          <tr>
            <td style="padding:32px 40px 8px;">
              <h1 style="margin:0 0 16px;font-size:22px;color:#0f172a;">Actividad reciente en tu cuenta</h1>
              <p style="margin:0 0 16px;line-height:1.55;">Hola, {$safeName}:</p>
              <p style="margin:0 0 16px;line-height:1.55;">Hemos registrado un inicio de sesión en tu cuenta Librum Tenebris desde un país distinto al habitual:</p>
              <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:16px 0;font-size:14px;">
                <tr><td style="padding:4px 12px 4px 0;color:#64748b;">País:</td><td style="padding:4px 0;font-weight:600;">{$safeCountry}</td></tr>
                <tr><td style="padding:4px 12px 4px 0;color:#64748b;">IP:</td><td style="padding:4px 0;font-family:monospace;">{$safeIp}</td></tr>
                <tr><td style="padding:4px 12px 4px 0;color:#64748b;">Fecha:</td><td style="padding:4px 0;">{$when}</td></tr>
              </table>
              <p style="margin:24px 0 16px;line-height:1.55;font-weight:600;">¿Has sido tú?</p>
              <table role="presentation" cellspacing="0" cellpadding="0" style="margin:16px 0;">
                <tr>
                  <td style="padding-right:12px;">
                    <a href="{$safeYesUrl}" style="display:inline-block;padding:12px 24px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Sí, he sido yo</a>
                  </td>
                  <td>
                    <a href="{$safeNoUrl}" style="display:inline-block;padding:12px 24px;background:#dc2626;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">No, no he sido yo</a>
                  </td>
                </tr>
              </table>
              <p style="margin:24px 0 8px;line-height:1.55;font-size:13px;color:#475569;">Si no fuiste tú, pulsa "No, no he sido yo" y cerraremos esa sesión. La próxima vez que accedas te pediremos cambiar la contraseña por seguridad.</p>
              <p style="margin:8px 0 0;line-height:1.55;font-size:13px;color:#475569;">Si sí fuiste tú, no hace falta que hagas nada — este aviso es solo informativo.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 40px 32px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;">
              Este aviso se envía automáticamente cuando detectamos logins desde un país nuevo.<br>
              &copy; {$year} Librum Tenebris
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private static function buildEmailLayout(
        string $name,
        string $title,
        string $intro,
        string $actionUrl,
        string $actionLabel,
        string $closing
    ): string {
        $safeName    = htmlspecialchars($name,        ENT_QUOTES, 'UTF-8');
        $safeTitle   = htmlspecialchars($title,       ENT_QUOTES, 'UTF-8');
        $safeIntro   = htmlspecialchars($intro,       ENT_QUOTES, 'UTF-8');
        $safeUrl     = htmlspecialchars($actionUrl,   ENT_QUOTES, 'UTF-8');
        $safeLabel   = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
        $safeClosing = htmlspecialchars($closing,     ENT_QUOTES, 'UTF-8');
        $year        = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td style="padding:32px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.06);">

          <!-- Cabecera -->
          <tr>
            <td style="padding:28px 36px;background:#1f3553;">
              <p style="margin:0;font-size:13px;letter-spacing:0.1em;text-transform:uppercase;color:#93c5fd;font-weight:600;">Grupo Reglado</p>
              <h1 style="margin:8px 0 0;font-size:24px;font-weight:700;color:#ffffff;line-height:1.3;">{$safeTitle}</h1>
            </td>
          </tr>

          <!-- Cuerpo -->
          <tr>
            <td style="padding:36px;">
              <p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:#0f172a;">Hola <strong>{$safeName}</strong>,</p>
              <p style="margin:0 0 28px;font-size:15px;line-height:1.7;color:#334155;">{$safeIntro}</p>

              <!-- Botón -->
              <table role="presentation" cellspacing="0" cellpadding="0">
                <tr>
                  <td style="border-radius:8px;background:#1f3553;">
                    <a href="{$safeUrl}" style="display:inline-block;padding:14px 28px;border-radius:8px;background:#1f3553;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;line-height:1;">{$safeLabel}</a>
                  </td>
                </tr>
              </table>

              <!-- Enlace de respaldo -->
              <p style="margin:28px 0 8px;font-size:13px;line-height:1.5;color:#64748b;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
              <p style="margin:0 0 28px;font-size:12px;line-height:1.6;word-break:break-all;color:#64748b;">
                <a href="{$safeUrl}" style="color:#2563eb;text-decoration:none;">{$safeUrl}</a>
              </p>

              <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 24px;">
              <p style="margin:0;font-size:13px;line-height:1.6;color:#94a3b8;">{$safeClosing}</p>
            </td>
          </tr>

          <!-- Pie -->
          <tr>
            <td style="padding:20px 36px;background:#f8fafc;border-top:1px solid #e2e8f0;">
              <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5;">
                Este mensaje fue enviado por Grupo Reglado &mdash; regladogroup.com<br>
                &copy; {$year} Reglado Consultores. Todos los derechos reservados.
              </p>
            </td>
          </tr>

        </table>
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

        error_log('[MailService] Driver "' . $driver . '" no reconocido o mail() nativo. Intentando mail() para: ' . $email);
        $from = getenv('MAIL_FROM') ?: 'no-reply@reglado.local';
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
        ];

        $result = mail($email, $subject, $message, implode("\r\n", $headers));
        if (!$result) {
            error_log('[MailService] mail() nativo falló para: ' . $email);
        }
        return $result;
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
            error_log('[MailService] SMTP config incompleta: host=' . $host . ' user=' . $username . ' from=' . $fromEmail);
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
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->XMailer  = ' ';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = self::buildAltBody($htmlBody);

            return $mail->send();
        } catch (Exception $e) {
            error_log('[MailService] SMTP error al enviar a ' . $toEmail . ': ' . $e->getMessage());
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
