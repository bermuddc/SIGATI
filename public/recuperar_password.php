<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/mailer.php';


$mensaje = '';
$mensaje_error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_csrf();

    $correo =
        trim(
            $_POST['correo']
            ?? ''
        );


    if (
        $correo === ''
        ||
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $mensaje_error =
            'Debes ingresar un correo electrónico válido.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | BUSCAR USUARIO
        |--------------------------------------------------------------------------
        |
        | La respuesta que verá el usuario será genérica,
        | exista o no el correo.
        |
        */

        $sql = "
            SELECT
                id_usuario,
                nombre_completo,
                correo
            FROM usuario_sistema
            WHERE correo = :correo
              AND activo = 1
            LIMIT 1
        ";

        $stmt =
            $pdo->prepare($sql);

        $stmt->execute([
            ':correo' => $correo
        ]);

        $usuario =
            $stmt->fetch();


        if ($usuario) {

            try {

                /*
                |--------------------------------------------------------------------------
                | TOKEN ALEATORIO
                |--------------------------------------------------------------------------
                */

                $token =
                    bin2hex(
                        random_bytes(32)
                    );


                /*
                |--------------------------------------------------------------------------
                | GUARDAR SOLO EL HASH
                |--------------------------------------------------------------------------
                */

                $token_hash =
                    hash(
                        'sha256',
                        $token
                    );


                /*
                |--------------------------------------------------------------------------
                | EXPIRACIÓN: 30 MINUTOS
                |--------------------------------------------------------------------------
                */

                $fecha_expiracion =
                    date(
                        'Y-m-d H:i:s',
                        time() + 1800
                    );


                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | INVALIDAR TOKENS ANTERIORES
                |--------------------------------------------------------------------------
                */

                $sql_invalidar = "
                    UPDATE recuperacion_password
                    SET utilizado = 1
                    WHERE id_usuario = :id_usuario
                      AND utilizado = 0
                ";

                $stmt_invalidar =
                    $pdo->prepare(
                        $sql_invalidar
                    );

                $stmt_invalidar->execute([
                    ':id_usuario' =>
                        $usuario['id_usuario']
                ]);


                /*
                |--------------------------------------------------------------------------
                | CREAR NUEVO TOKEN
                |--------------------------------------------------------------------------
                */

                $sql_insertar = "
                    INSERT INTO recuperacion_password (
                        id_usuario,
                        token_hash,
                        fecha_expiracion,
                        utilizado
                    )
                    VALUES (
                        :id_usuario,
                        :token_hash,
                        :fecha_expiracion,
                        0
                    )
                ";

                $stmt_insertar =
                    $pdo->prepare(
                        $sql_insertar
                    );

                $stmt_insertar->execute([

                    ':id_usuario' =>
                        $usuario['id_usuario'],

                    ':token_hash' =>
                        $token_hash,

                    ':fecha_expiracion' =>
                        $fecha_expiracion

                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | CONSTRUIR ENLACE
                |--------------------------------------------------------------------------
                */

                $usa_https =
                    !empty($_SERVER['HTTPS'])
                    &&
                    strtolower(
                        (string) $_SERVER['HTTPS']
                    ) !== 'off';


                $protocolo =
                    $usa_https
                    ? 'https'
                    : 'http';


                $host =
                    $_SERVER['HTTP_HOST']
                    ?? 'localhost';


                $enlace =
                    $protocolo
                    . '://'
                    . $host
                    . '/sigati/public/restablecer_password.php?token='
                    . urlencode($token);


                /*
                |--------------------------------------------------------------------------
                | ENVIAR CORREO
                |--------------------------------------------------------------------------
                */

                enviar_correo_recuperacion(
                    $usuario['correo'],
                    $usuario['nombre_completo'],
                    $enlace
                );

            } catch (Throwable $e) {

                /*
                |--------------------------------------------------------------------------
                | NO MOSTRAR DETALLES TÉCNICOS
                |--------------------------------------------------------------------------
                |
                | Evitamos mostrar información sensible al usuario.
                |
                */

            }
        }


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA GENÉRICA
        |--------------------------------------------------------------------------
        |
        | No revelamos si el correo existe o no.
        |
        */

        $mensaje =
            'Si el correo ingresado pertenece a una cuenta activa, '
            . 'recibirás un enlace para restablecer la contraseña.';


        /*
        |--------------------------------------------------------------------------
        | RENOVAR TOKEN CSRF
        |--------------------------------------------------------------------------
        */

        unset(
            $_SESSION['csrf_token']
        );
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        SIGATI - Recuperar contraseña
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;
        }

        .contenedor {
            width: 100%;
            max-width: 440px;

            background-color: #ffffff;

            padding: 35px;

            border-radius: 10px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.12);
        }

        h1 {
            text-align: center;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .subtitulo {
            text-align: center;
            color: #6b7280;

            line-height: 1.5;

            margin-bottom: 30px;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;
            color: #374151;
        }

        .campo input {
            width: 100%;

            padding: 12px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 16px;
        }

        .boton {
            width: 100%;

            padding: 13px;

            border: none;
            border-radius: 6px;

            background-color: #1f2937;
            color: #ffffff;

            font-size: 16px;

            cursor: pointer;
        }

        .boton:hover {
            background-color: #111827;
        }

        .mensaje {
            margin-bottom: 20px;

            padding: 13px;

            background-color: #dcfce7;
            color: #166534;

            border-radius: 6px;

            line-height: 1.5;
        }

        .mensaje-error {
            margin-bottom: 20px;

            padding: 13px;

            background-color: #fee2e2;
            color: #991b1b;

            border-radius: 6px;
        }

        .volver {
            margin-top: 22px;
            text-align: center;
        }

        .volver a {
            color: #1f2937;
            text-decoration: none;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <h1>SIGATI</h1>

    <p class="subtitulo">
        Recuperación de contraseña
    </p>


    <?php if ($mensaje !== ''): ?>

        <div class="mensaje">

            <?= htmlspecialchars(
                $mensaje,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <?php if ($mensaje_error !== ''): ?>

        <div class="mensaje-error">

            <?= htmlspecialchars(
                $mensaje_error,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <form method="POST" action="">

        <?= csrf_field(); ?>

        <div class="campo">

            <label for="correo">
                Correo electrónico
            </label>

            <input
                type="email"
                id="correo"
                name="correo"
                autocomplete="email"
                required
            >

        </div>


        <button
            class="boton"
            type="submit"
        >
            Enviar enlace de recuperación
        </button>

    </form>


    <div class="volver">

        <a href="login.php">
            Volver a iniciar sesión
        </a>

    </div>

</div>

</body>

</html>