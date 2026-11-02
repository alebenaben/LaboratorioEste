<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envío de mails desde sistema@integraldeleste.com.ar.
 * MAIL_MODO = smtp | archivo (guarda .eml en storage/mails, para desarrollo y pruebas) | desactivado
 */
final class Mailer
{
    public static function modo(): string
    {
        return Env::get('MAIL_MODO', 'archivo');
    }

    /**
     * @param array{nombre:string,contenido:string,tipo:string}|null $adjunto
     * @throws MailRebote si el servidor rechaza la dirección (rebote inmediato)
     * @throws \RuntimeException si falla el envío (se reintenta)
     */
    public static function enviar(string $para, string $asunto, string $cuerpo, ?array $adjunto = null): void
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            throw new MailRebote("La dirección $para no es válida.");
        }
        $m = new PHPMailer(true);
        $m->CharSet = 'UTF-8';
        $m->setFrom(Env::get('MAIL_FROM', 'sistema@integraldeleste.com.ar'), Env::get('MAIL_FROM_NAME', 'Integral del Este'));
        if ($rt = Env::get('MAIL_REPLY_TO')) {
            $m->addReplyTo($rt);
        }
        $m->addAddress($para);
        $m->Subject = $asunto;
        $m->Body = $cuerpo;
        $m->isHTML(false);
        if ($adjunto) {
            $m->addStringAttachment($adjunto['contenido'], $adjunto['nombre'], PHPMailer::ENCODING_BASE64, $adjunto['tipo']);
        }
        $modo = self::modo();
        if ($modo === 'desactivado') {
            return;
        }
        if ($modo === 'archivo') {
            $m->preSend();
            $dir = dirname(__DIR__, 2) . '/storage/mails';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml', $m->getSentMIMEMessage());
            return;
        }
        $m->isSMTP();
        $m->Host = (string)Env::get('SMTP_HOST', 'localhost');
        $m->Port = Env::int('SMTP_PORT', 587);
        $m->SMTPAuth = Env::get('SMTP_USER') !== null;
        $m->Username = (string)Env::get('SMTP_USER', '');
        $m->Password = (string)Env::get('SMTP_PASSWORD', '');
        $m->SMTPSecure = Env::int('SMTP_PORT', 587) === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $m->Timeout = 30;
        try {
            $m->send();
        } catch (MailException $e) {
            // 5xx sobre el destinatario = rebote permanente; el resto se reintenta.
            if (preg_match('/\b55[0-4]\b|recipient|mailbox unavailable|user unknown/i', $m->ErrorInfo)) {
                throw new MailRebote($m->ErrorInfo);
            }
            throw new \RuntimeException('SMTP: ' . $m->ErrorInfo);
        }
    }
}

final class MailRebote extends \RuntimeException
{
}
