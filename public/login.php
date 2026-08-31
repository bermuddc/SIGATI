<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';


if (isset($_SESSION['usuario_id'])) {

    header(
        'Location: dashboard.php'
    );

    exit;
}


$mensaje_error = '';

$mensaje_exito = '';


if (
    isset($_GET['password'])
    &&
    $_GET['password'] === 'restablecida'
) {

    $mensaje_exito =
        'Contraseña actualizada correctamente. '
        . 'Ya puedes iniciar sesión.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_csrf();


    $nombre_usuario =
        trim(
            $_POST['nombre_usuario']
            ?? ''
        );


    $password =
        $_POST['password']
        ?? '';


    if (
        $nombre_usuario === ''
        ||
        $password === ''
    ) {

        $mensaje_error =
            'Debes ingresar usuario y contraseña.';

    } else {

        $sql = "
            SELECT
                u.id_usuario,
                u.nombre_completo,
                u.nombre_usuario,
                u.password_hash,
                u.activo,
                r.nombre_rol
            FROM usuario_sistema u
            INNER JOIN rol r
                ON u.id_rol = r.id_rol
            WHERE u.nombre_usuario = :nombre_usuario
            LIMIT 1
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute([
            ':nombre_usuario' =>
                $nombre_usuario
        ]);


        $usuario =
            $stmt->fetch();


        if (
            $usuario
            &&
            (int) $usuario['activo'] === 1
            &&
            password_verify(
                $password,
                $usuario['password_hash']
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | RENOVAR ID DE SESIÓN
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            $_SESSION['usuario_id'] =
                $usuario['id_usuario'];

            $_SESSION['nombre_completo'] =
                $usuario['nombre_completo'];

            $_SESSION['nombre_usuario'] =
                $usuario['nombre_usuario'];

            $_SESSION['rol'] =
                $usuario['nombre_rol'];


            /*
            |--------------------------------------------------------------------------
            | RENOVAR TOKEN CSRF
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['csrf_token']
            );


            header(
                'Location: dashboard.php'
            );

            exit;

        } else {

            $mensaje_error =
                'Usuario o contraseña incorrectos.';
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
        SIGATI - Iniciar sesión
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

        .login-container {
            width: 100%;
            max-width: 420px;

            background-color: #ffffff;

            border-radius: 10px;

            padding: 35px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.12);
        }

        .login-container h1 {
            text-align: center;

            margin-bottom: 10px;

            color: #1f2937;
        }

        .subtitulo {
            text-align: center;

            color: #6b7280;

            margin-bottom: 30px;

            line-height: 1.5;
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

        .campo input:focus {
            outline: none;

            border-color: #374151;
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

        .mensaje-error {
            margin-bottom: 20px;

            padding: 12px;

            background-color: #fee2e2;
            color: #991b1b;

            border-radius: 6px;

            text-align: center;
        }

        .mensaje-exito {
            margin-bottom: 20px;

            padding: 12px;

            background-color: #dcfce7;
            color: #166534;

            border-radius: 6px;

            text-align: center;
        }

        .recuperar {
            text-align: center;

            margin-top: 18px;
        }

        .recuperar a {
            color: #1f2937;

            font-weight: bold;

            text-decoration: none;
        }

        .recuperar a:hover {
            text-decoration: underline;
        }

        .pie {
            margin-top: 25px;

            text-align: center;

            color: #9ca3af;

            font-size: 13px;
        }

        @media (max-width: 480px) {

            .login-container {
                padding: 25px;
            }
        }

    </style>

</head>

<body>

<div class="login-container">

    <h1>SIGATI</h1>

    <p class="subtitulo">

        Sistema de Gestión y Trazabilidad
        de Activos Tecnológicos

    </p>


    <?php if ($mensaje_error !== ''): ?>

        <div class="mensaje-error">

            <?= htmlspecialchars(
                $mensaje_error,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <?php if ($mensaje_exito !== ''): ?>

        <div class="mensaje-exito">

            <?= htmlspecialchars(
                $mensaje_exito,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <form method="POST" action="">

        <?= csrf_field(); ?>


        <div class="campo">

            <label for="nombre_usuario">
                Usuario
            </label>

            <input
                type="text"
                id="nombre_usuario"
                name="nombre_usuario"
                autocomplete="username"
                required
            >

        </div>


        <div class="campo">

            <label for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

        </div>


        <button
            class="boton"
            type="submit"
        >
            Iniciar sesión
        </button>

    </form>


    <div class="recuperar">

        <a href="recuperar_password.php">
            ¿Olvidaste tu contraseña?
        </a>

    </div>


    <div class="pie">
        Acceso restringido a usuarios autorizados.
    </div>

</div>

</body>

</html>