<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;

/** Envio de e-mail por SMTP (PHPMailer). Sem MAIL_HOST configurado, nada é enviado. */
final class Mailer
{
    public static function configurado(): bool
    {
        return (string) Config::get('mail.host', '') !== '' && (string) Config::get('mail.from_address', '') !== '';
    }

    public static function enviar(string $para, string $nome, string $assunto, string $texto): bool
    {
        if (!self::configurado() || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host = (string) Config::get('mail.host');
            $m->Port = (int) Config::get('mail.port', 587);
            $enc = (string) Config::get('mail.encryption', 'tls');
            if ($enc === 'ssl') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $m->SMTPSecure = '';
                $m->SMTPAutoTLS = false;
            }
            $user = (string) Config::get('mail.username', '');
            if ($user !== '') {
                $m->SMTPAuth = true;
                $m->Username = $user;
                $m->Password = (string) Config::get('mail.password', '');
            }
            $m->CharSet = 'UTF-8';
            $m->setFrom((string) Config::get('mail.from_address'), (string) Config::get('mail.from_name', 'CRM Grupo Mirante'));
            $m->addAddress($para, $nome);
            $m->Subject = $assunto;
            $m->Body = $texto;
            $m->isHTML(false);
            $m->send();
            return true;
        } catch (\Throwable $e) {
            Logger::warning('Falha ao enviar e-mail: ' . $e->getMessage(), ['para' => $para, 'assunto' => $assunto]);
            return false;
        }
    }
}
