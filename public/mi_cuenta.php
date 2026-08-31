<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();


$id_usuario =
    (int) ($_SESSION['usuario_id'] ?? 0);

$mensaje_exito = '';
$mensaje_error = '';


/*
|--------------------------------------------------------------------------
| CARGAR DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

function obtener_usuario_cuenta(
    PDO $pdo,
    int $id_usuario
): array|false {

    $sql = "
        SELECT
            u.id_usuario,
            u.nombre_completo,
            u.nombre_usuario,
            u.correo,
            u.password_hash,
            u.activo,
            r.nombre_rol
        FROM usuario_sistema u
        INNER JOIN rol r
            ON u.id_rol = r.id_rol
        WHERE u.id_usuario = :id_usuario
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id_usuario' => $id_usuario
    ]);

    return $stmt->fetch();
}


$usuario =
    obtener_usuario_cuenta(
        $pdo,
        $id_usuario
    );


if (!$usuario) {

    http_response_code(404);

    exit(
        'Usuario no encontrado.'
    );
}


/*
|--------------------------------------------------------------------------
| PROCESAR FORMULARIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_csrf();

    $accion =
        $_POST['accion']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR PERFIL
    |--------------------------------------------------------------------------
    */

    if ($accion === 'actualizar_perfil') {

        $nombre_completo =
            trim(
                $_POST['nombre_completo']
                ?? ''
            );

        $correo =
            trim(
                $_POST['correo']
                ?? ''
            );


        if ($nombre_completo === '') {

            $mensaje_error =
                'El nombre completo es obligatorio.';

        } elseif (
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
            | COMPROBAR CORREO DUPLICADO
            |--------------------------------------------------------------------------
            */

            $sql_correo = "
                SELECT
                    id_usuario
                FROM usuario_sistema
                WHERE correo = :correo
                  AND id_usuario <> :id_usuario
                LIMIT 1
            ";

            $stmt_correo =
                $pdo->prepare(
                    $sql_correo
                );

            $stmt_correo->execute([

                ':correo' =>
                    $correo,

                ':id_usuario' =>
                    $id_usuario

            ]);


            if ($stmt_correo->fetch()) {

                $mensaje_error =
                    'El correo electrónico ya está asociado a otra cuenta.';

            } else {

                $sql_actualizar = "
                    UPDATE usuario_sistema
                    SET
                        nombre_completo = :nombre_completo,
                        correo = :correo
                    WHERE id_usuario = :id_usuario
                ";

                $stmt_actualizar =
                    $pdo->prepare(
                        $sql_actualizar
                    );

                $stmt_actualizar->execute([

                    ':nombre_completo' =>
                        $nombre_completo,

                    ':correo' =>
                        $correo,

                    ':id_usuario' =>
                        $id_usuario

                ]);


                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR DATOS DE SESIÓN
                |--------------------------------------------------------------------------
                */

                $_SESSION['nombre_completo'] =
                    $nombre_completo;


                $mensaje_exito =
                    'Perfil actualizado correctamente.';


                unset(
                    $_SESSION['csrf_token']
                );


                $usuario =
                    obtener_usuario_cuenta(
                        $pdo,
                        $id_usuario
                    );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CAMBIAR CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    elseif ($accion === 'cambiar_password') {

        $password_actual =
            $_POST['password_actual']
            ?? '';

        $password_nueva =
            $_POST['password_nueva']
            ?? '';

        $password_confirmacion =
            $_POST['password_confirmacion']
            ?? '';


        if (
            !password_verify(
                $password_actual,
                $usuario['password_hash']
            )
        ) {

            $mensaje_error =
                'La contraseña actual no es correcta.';

        } elseif (
            strlen($password_nueva) < 10
        ) {

            $mensaje_error =
                'La nueva contraseña debe tener al menos 10 caracteres.';

        } elseif (
            $password_nueva
            !==
            $password_confirmacion
        ) {

            $mensaje_error =
                'La nueva contraseña y su confirmación no coinciden.';

        } elseif (
            password_verify(
                $password_nueva,
                $usuario['password_hash']
            )
        ) {

            $mensaje_error =
                'La nueva contraseña debe ser diferente de la contraseña actual.';

        } else {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | GENERAR NUEVO HASH
                |--------------------------------------------------------------------------
                */

                $nuevo_hash =
                    password_hash(
                        $password_nueva,
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
                        $id_usuario

                ]);


                /*
                |--------------------------------------------------------------------------
                | INVALIDAR TOKENS DE RECUPERACIÓN
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
                        $id_usuario
                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | RENOVAR IDENTIFICADOR DE SESIÓN
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                unset(
                    $_SESSION['csrf_token']
                );


                $mensaje_exito =
                    'Contraseña actualizada correctamente.';


                $usuario =
                    obtener_usuario_cuenta(
                        $pdo,
                        $id_usuario
                    );

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }


                $mensaje_error =
                    'No fue posible actualizar la contraseña.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ACCIÓN NO VÁLIDA
    |--------------------------------------------------------------------------
    */

    else {

        $mensaje_error =
            'La operación solicitada no es válida.';
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPAR SALIDA HTML
|--------------------------------------------------------------------------
*/

function e_cuenta(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
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
        SIGATI - Mi cuenta
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #1f2937;
        }

        .barra-superior {
            background-color: #1f2937;
            color: #ffffff;

            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
            flex-wrap: wrap;
        }

        .barra-superior h1 {
            font-size: 24px;
        }

        .barra-superior a {
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .contenedor {
            width: 100%;
            max-width: 1000px;

            margin: 40px auto;

            padding: 0 20px;
        }

        .encabezado {
            margin-bottom: 25px;
        }

        .encabezado h2 {
            margin-bottom: 8px;
        }

        .encabezado p {
            color: #6b7280;
        }

        .mensaje-exito {
            margin-bottom: 25px;

            padding: 14px;

            background-color: #dcfce7;
            color: #166534;

            border-radius: 7px;
        }

        .mensaje-error {
            margin-bottom: 25px;

            padding: 14px;

            background-color: #fee2e2;
            color: #991b1b;

            border-radius: 7px;
        }

        .grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 25px;
        }

        .tarjeta {
            background-color: #ffffff;

            padding: 28px;

            border-radius: 10px;

            box-shadow:
                0 4px 16px
                rgba(0, 0, 0, 0.08);
        }

        .tarjeta h3 {
            margin-bottom: 20px;
        }

        .campo {
            margin-bottom: 18px;
        }

        .campo label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;
            color: #374151;
        }

        .campo input {
            width: 100%;

            padding: 11px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 15px;
        }

        .campo input[readonly] {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .ayuda {
            margin-top: 5px;

            color: #6b7280;

            font-size: 13px;
            line-height: 1.4;
        }

        .boton {
            width: 100%;

            padding: 12px;

            border: none;
            border-radius: 6px;

            background-color: #1f2937;
            color: #ffffff;

            font-size: 15px;
            font-weight: bold;

            cursor: pointer;
        }

        .boton:hover {
            background-color: #111827;
        }

        .volver {
            display: inline-block;

            margin-top: 30px;

            padding: 11px 16px;

            background-color: #1f2937;
            color: #ffffff;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;
            font-size: 14px;
        }

        @media (max-width: 800px) {

            .grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<header class="barra-superior">

    <h1>SIGATI</h1>

    <a href="dashboard.php">
        Panel principal
    </a>

</header>


<main class="contenedor">

    <div class="encabezado">

        <h2>Mi cuenta</h2>

        <p>
            Consulta y administra los datos asociados
            a tu cuenta de acceso a SIGATI.
        </p>

    </div>


    <?php if ($mensaje_exito !== ''): ?>

        <div class="mensaje-exito">

            <?= e_cuenta(
                $mensaje_exito
            ); ?>

        </div>

    <?php endif; ?>


    <?php if ($mensaje_error !== ''): ?>

        <div class="mensaje-error">

            <?= e_cuenta(
                $mensaje_error
            ); ?>

        </div>

    <?php endif; ?>


    <div class="grid">


        <!-- PERFIL -->

        <section class="tarjeta">

            <h3>Datos de perfil</h3>


            <form method="POST" action="">

                <?= csrf_field(); ?>


                <input
                    type="hidden"
                    name="accion"
                    value="actualizar_perfil"
                >


                <div class="campo">

                    <label for="nombre_usuario">
                        Usuario
                    </label>

                    <input
                        type="text"
                        id="nombre_usuario"
                        value="<?= e_cuenta(
                            $usuario['nombre_usuario']
                        ); ?>"
                        readonly
                    >

                    <div class="ayuda">
                        El nombre de usuario no puede
                        modificarse desde este formulario.
                    </div>

                </div>


                <div class="campo">

                    <label for="rol">
                        Perfil de acceso
                    </label>

                    <input
                        type="text"
                        id="rol"
                        value="<?= e_cuenta(
                            $usuario['nombre_rol']
                        ); ?>"
                        readonly
                    >

                </div>


                <div class="campo">

                    <label for="nombre_completo">
                        Nombre completo
                    </label>

                    <input
                        type="text"
                        id="nombre_completo"
                        name="nombre_completo"
                        maxlength="150"
                        value="<?= e_cuenta(
                            $usuario['nombre_completo']
                        ); ?>"
                        required
                    >

                </div>


                <div class="campo">

                    <label for="correo">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        maxlength="150"
                        value="<?= e_cuenta(
                            $usuario['correo']
                        ); ?>"
                        required
                    >

                    <div class="ayuda">
                        Este correo también se utiliza
                        para recuperar la contraseña.
                    </div>

                </div>


                <button
                    class="boton"
                    type="submit"
                >
                    Actualizar perfil
                </button>

            </form>

        </section>


        <!-- CONTRASEÑA -->

        <section class="tarjeta">

            <h3>Seguridad de la cuenta</h3>


            <form method="POST" action="">

                <?= csrf_field(); ?>


                <input
                    type="hidden"
                    name="accion"
                    value="cambiar_password"
                >


                <div class="campo">

                    <label for="password_actual">
                        Contraseña actual
                    </label>

                    <input
                        type="password"
                        id="password_actual"
                        name="password_actual"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="campo">

                    <label for="password_nueva">
                        Nueva contraseña
                    </label>

                    <input
                        type="password"
                        id="password_nueva"
                        name="password_nueva"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >

                    <div class="ayuda">
                        Debe contener al menos
                        10 caracteres.
                    </div>

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

        </section>

    </div>


    <a
        class="volver"
        href="dashboard.php"
    >
        Volver al panel principal
    </a>

</main>

</body>

</html>