<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';


function enviar_correo_recuperacion(
    string $correo_destino,
    string $nombre_destino,
    string $enlace_recuperacion
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

    $mail->isHTML(true);

    $mail->Subject =
        'Recuperación de contraseña - SIGATI';


    $enlace_seguro =
        htmlspecialchars(
            $enlace_recuperacion,
            ENT_QUOTES,
            'UTF-8'
        );


    $nombre_seguro =
        htmlspecialchars(
            $nombre_destino,
            ENT_QUOTES,
            'UTF-8'
        );


    $mail->Body = '

        <h2>SIGATI</h2>

        <p>
            Hola ' . $nombre_seguro . ':
        </p>

        <p>
            Se recibió una solicitud para restablecer
            la contraseña de tu cuenta en SIGATI.
        </p>

        <p>
            Para crear una nueva contraseña,
            utiliza el siguiente enlace:
        </p>

        <p>
            <a href="' . $enlace_seguro . '">
                Restablecer contraseña
            </a>
        </p>

        <p>
            Este enlace tiene una vigencia de
            <strong>30 minutos</strong>
            y solamente puede utilizarse una vez.
        </p>

        <p>
            Si no solicitaste este cambio,
            puedes ignorar este mensaje.
        </p>

        <p>
            <strong>
                SIGATI - Sistema de Gestión y
                Trazabilidad de Activos Tecnológicos
            </strong>
        </p>
    ';


    $mail->AltBody =
        "SIGATI\n\n"
        . "Se recibió una solicitud para restablecer "
        . "tu contraseña.\n\n"
        . "Utiliza este enlace:\n"
        . $enlace_recuperacion
        . "\n\n"
        . "El enlace tiene una vigencia de 30 minutos "
        . "y solamente puede utilizarse una vez.";


    /*
    |--------------------------------------------------------------------------
    | ENVÍO
    |--------------------------------------------------------------------------
    */

    $mail->send();
}