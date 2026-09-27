<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';


function enviar_correo_recuperacion(
    string $correo_destino,
    string $nombre_destino,
    string $codigo_recuperacion
): void {

    $config = require __DIR__ . '/../config/mail_config.php';

    $mail = new PHPMailer(true);

    $mail->isSMTP();

    $mail->Host =
        $config['smtp_host'];

    $mail->SMTPAuth = true;

    $mail->Username =
        $config['smtp_usuario'];

    $mail->Password =
        $config['smtp_password'];

    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port =
        (int) $config['smtp_port'];

    $mail->CharSet = 'UTF-8';


    /*
    |--------------------------------------------------------------------------
    | REMITENTE
    |--------------------------------------------------------------------------
    */

    $mail->setFrom(
        $config['smtp_usuario'],
        $config['nombre_remitente']
    );


    /*
    |--------------------------------------------------------------------------
    | DESTINATARIO
    |--------------------------------------------------------------------------
    */

    $mail->addAddress(
        $correo_destino,
        $nombre_destino
    );


    /*
    |--------------------------------------------------------------------------
    | CONTENIDO
    |--------------------------------------------------------------------------
    */

    $mail->isHTML(false);

    $mail->Subject =
        'Código de recuperación - SIGATI';

    // Se omite la URL del hosting: Gmail rechazó los mensajes de prueba que la contenían.
    $mail->Body =
        "SIGATI\n\n"
        . 'Hola ' . $nombre_destino . ":\n\n"
        . "Solicitaste recuperar el acceso a tu cuenta.\n"
        . "Abre SIGATI como lo haces habitualmente, selecciona "
        . "'¿Olvidaste tu contraseña?' y después 'Ya tengo un código'.\n\n"
        . "Código de un solo uso:\n"
        . $codigo_recuperacion . "\n\n"
        . "Vence en 30 minutos. Si no lo solicitaste, ignora este mensaje.";


    /*
    |--------------------------------------------------------------------------
    | ENVÍO
    |--------------------------------------------------------------------------
    */

    $mail->send();
}
