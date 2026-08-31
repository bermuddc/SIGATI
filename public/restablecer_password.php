<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';


$mensaje_error = '';

$token =
    trim(
        $_GET['token']
        ?? $_POST['token']
        ?? ''
    );


$token_valido = false;

$recuperacion = null;


/*
|--------------------------------------------------------------------------
| VALIDAR TOKEN RECIBIDO
|--------------------------------------------------------------------------
*/

if ($token !== '') {

    $token_hash =
        hash(
            'sha256',
            $token
        );


    $sql = "
        SELECT
            rp.id_recuperacion,
            rp.id_usuario,
            rp.fecha_expiracion,
            rp.utilizado,
            u.nombre_usuario,
            u.activo
        FROM recuperacion_password rp
        INNER JOIN usuario_sistema u
            ON rp.id_usuario = u.id_usuario
        WHERE rp.token_hash = :token_hash
          AND rp.utilizado = 0
          AND rp.fecha_expiracion >= NOW()
          AND u.activo = 1
        LIMIT 1
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([
        ':token_hash' =>
            $token_hash
    ]);


    $recuperacion =
        $stmt->fetch();


    if ($recuperacion) {
        $token_valido = true;
    }
}


/*
|--------------------------------------------------------------------------
| CAMBIAR CONTRASEÑA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $token_valido
) {

    validate_csrf();


    $password =
        $_POST['password']
        ?? '';


    $password_confirmacion =
        $_POST['password_confirmacion']
        ?? '';


    if (
        strlen($password) < 10
    ) {

        $mensaje_error =
            'La nueva contraseña debe tener al menos 10 caracteres.';

    } elseif (
        $password
        !==
        $password_confirmacion
    ) {

        $mensaje_error =
            'Las contraseñas ingresadas no coinciden.';

    } else {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | GENERAR HASH DE CONTRASEÑA
            |--------------------------------------------------------------------------
            */

            $nuevo_hash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR CONTRASEÑA
            |--------------------------------------------------------------------------
            */

            $sql_password = "
                UPDATE usuario_sistema
                SET password_hash = :password_hash
                WHERE id_usuario = :id_usuario
                  AND activo = 1
            ";


            $stmt_password =
                $pdo->prepare(
                    $sql_password
                );


            $stmt_password->execute([

                ':password_hash' =>
                    $nuevo_hash,

                ':id_usuario' =>
                    $recuperacion['id_usuario']

            ]);


            /*
            |--------------------------------------------------------------------------
            | INVALIDAR TODOS LOS TOKENS DEL USUARIO
            |--------------------------------------------------------------------------
            */

            $sql_tokens = "
                UPDATE recuperacion_password
                SET utilizado = 1
                WHERE id_usuario = :id_usuario
                  AND utilizado = 0
            ";


            $stmt_tokens =
                $pdo->prepare(
                    $sql_tokens
                );


            $stmt_tokens->execute([

                ':id_usuario' =>
                    $recuperacion['id_usuario']

            ]);


            $pdo->commit();


            unset(
                $_SESSION['csrf_token']
            );


            header(
                'Location: login.php?password=restablecida'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            $mensaje_error =
                'No fue posible actualizar la contraseña.';
        }
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
        SIGATI - Restablecer contraseña
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
            justify-content: center;
            align-items: center;

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

            margin-bottom: 30px;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;

            margin-bottom: 7px;

            color: #374151;
            font-weight: bold;
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

        .error {
            padding: 13px;

            background-color: #fee2e2;
            color: #991b1b;

            border-radius: 6px;

            margin-bottom: 20px;

            line-height: 1.5;
        }

        .volver {
            text-align: center;
            margin-top: 22px;
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
        Restablecer contraseña
    </p>


    <?php if (!$token_valido): ?>

        <div class="error">

            El enlace de recuperación no es válido,
            ya fue utilizado o ha expirado.

        </div>


        <div class="volver">

            <a href="recuperar_password.php">
                Solicitar un nuevo enlace
            </a>

        </div>


    <?php else: ?>


        <?php if ($mensaje_error !== ''): ?>

            <div class="error">

                <?= htmlspecialchars(
                    $mensaje_error,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <?= csrf_field(); ?>


            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars(
                    $token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >


            <div class="campo">

                <label for="password">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="10"
                    required
                >

            </div>


            <div class="campo">

                <label for="password_confirmacion">
                    Confirmar nueva contraseña
                </label>

                <input
                    type="password"
                    id="password_confirmacion"
                    name="password_confirmacion"
                    autocomplete="new-password"
                    minlength="10"
                    required
                >

            </div>


            <button
                class="boton"
                type="submit"
            >
                Cambiar contraseña
            </button>

        </form>


    <?php endif; ?>

</div>

</body>

</html>