<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_role('Administrador TI');

$errores = [];


/*
|--------------------------------------------------------------------------
| Validar ID recibido
|--------------------------------------------------------------------------
*/

$id_colaborador = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id_colaborador || $id_colaborador <= 0) {

    header('Location: colaboradores.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Obtener catálogo de tipos de colaborador
|--------------------------------------------------------------------------
*/

try {

    $sqlTipos = "
        SELECT
            id_tipo_colaborador,
            nombre_tipo
        FROM tipo_colaborador
        ORDER BY id_tipo_colaborador
    ";

    $stmtTipos = $pdo->prepare($sqlTipos);
    $stmtTipos->execute();

    $tipos_colaborador =
        $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $tipos_colaborador = [];

    $errores[] =
        'No fue posible cargar los tipos de colaborador.';
}


/*
|--------------------------------------------------------------------------
| Obtener colaborador actual
|--------------------------------------------------------------------------
*/

try {

    $sqlColaborador = "
        SELECT
            id_colaborador,
            nombre_completo,
            usuario_dominio,
            correo_corporativo,
            id_tipo_colaborador
        FROM colaborador
        WHERE id_colaborador = :id_colaborador
        LIMIT 1
    ";

    $stmtColaborador =
        $pdo->prepare($sqlColaborador);

    $stmtColaborador->execute([
        ':id_colaborador' =>
            $id_colaborador
    ]);

    $colaborador =
        $stmtColaborador->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {

        header('Location: colaboradores.php');
        exit;
    }

} catch (PDOException $e) {

    header('Location: colaboradores.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Valores iniciales
|--------------------------------------------------------------------------
*/

$nombre_completo =
    $colaborador['nombre_completo'];

$usuario_dominio =
    $colaborador['usuario_dominio'];

$correo_corporativo =
    $colaborador['correo_corporativo'];

$id_tipo_colaborador =
    (string) $colaborador['id_tipo_colaborador'];


/*
|--------------------------------------------------------------------------
| Procesar actualización
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validar CSRF
    |--------------------------------------------------------------------------
    */

    validate_csrf();


    $nombre_completo =
        trim($_POST['nombre_completo'] ?? '');

    $usuario_dominio =
        trim($_POST['usuario_dominio'] ?? '');

    $correo_corporativo =
        trim($_POST['correo_corporativo'] ?? '');

    $id_tipo_colaborador =
        trim($_POST['id_tipo_colaborador'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validaciones generales
    |--------------------------------------------------------------------------
    */

    if ($nombre_completo === '') {

        $errores[] =
            'Debes ingresar el nombre completo del colaborador.';
    }

    if (mb_strlen($nombre_completo) > 150) {

        $errores[] =
            'El nombre completo no puede superar los 150 caracteres.';
    }


    if ($usuario_dominio === '') {

        $errores[] =
            'Debes ingresar el usuario de dominio.';
    }

    if (mb_strlen($usuario_dominio) > 100) {

        $errores[] =
            'El usuario de dominio no puede superar los 100 caracteres.';
    }


    if ($correo_corporativo === '') {

        $errores[] =
            'Debes ingresar el correo corporativo.';

    } elseif (
        !filter_var(
            $correo_corporativo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errores[] =
            'El correo corporativo ingresado no tiene un formato válido.';
    }

    if (mb_strlen($correo_corporativo) > 150) {

        $errores[] =
            'El correo corporativo no puede superar los 150 caracteres.';
    }


    if (
        $id_tipo_colaborador === ''
        || !ctype_digit($id_tipo_colaborador)
    ) {

        $errores[] =
            'Debes seleccionar un tipo de colaborador válido.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validar que el tipo exista
    |--------------------------------------------------------------------------
    */

    if (
        empty($errores)
        && ctype_digit($id_tipo_colaborador)
    ) {

        try {

            $sqlTipo = "
                SELECT COUNT(*)
                FROM tipo_colaborador
                WHERE id_tipo_colaborador = :id_tipo_colaborador
            ";

            $stmtTipo =
                $pdo->prepare($sqlTipo);

            $stmtTipo->execute([
                ':id_tipo_colaborador' =>
                    (int) $id_tipo_colaborador
            ]);

            if (
                (int) $stmtTipo->fetchColumn()
                !== 1
            ) {

                $errores[] =
                    'El tipo de colaborador seleccionado no es válido.';
            }

        } catch (PDOException $e) {

            $errores[] =
                'No fue posible validar el tipo de colaborador.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validar usuario de dominio duplicado
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $sqlUsuario = "
                SELECT COUNT(*)
                FROM colaborador
                WHERE usuario_dominio = :usuario_dominio
                  AND id_colaborador <> :id_colaborador
            ";

            $stmtUsuario =
                $pdo->prepare($sqlUsuario);

            $stmtUsuario->execute([
                ':usuario_dominio' =>
                    $usuario_dominio,

                ':id_colaborador' =>
                    $id_colaborador
            ]);

            if (
                (int) $stmtUsuario->fetchColumn()
                > 0
            ) {

                $errores[] =
                    'El usuario de dominio ya se encuentra registrado en otro colaborador.';
            }

        } catch (PDOException $e) {

            $errores[] =
                'No fue posible validar el usuario de dominio.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validar correo duplicado
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $sqlCorreo = "
                SELECT COUNT(*)
                FROM colaborador
                WHERE correo_corporativo = :correo_corporativo
                  AND id_colaborador <> :id_colaborador
            ";

            $stmtCorreo =
                $pdo->prepare($sqlCorreo);

            $stmtCorreo->execute([
                ':correo_corporativo' =>
                    $correo_corporativo,

                ':id_colaborador' =>
                    $id_colaborador
            ]);

            if (
                (int) $stmtCorreo->fetchColumn()
                > 0
            ) {

                $errores[] =
                    'El correo corporativo ya se encuentra registrado en otro colaborador.';
            }

        } catch (PDOException $e) {

            $errores[] =
                'No fue posible validar el correo corporativo.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar colaborador
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $sqlUpdate = "
                UPDATE colaborador
                SET
                    nombre_completo = :nombre_completo,
                    usuario_dominio = :usuario_dominio,
                    correo_corporativo = :correo_corporativo,
                    id_tipo_colaborador = :id_tipo_colaborador
                WHERE id_colaborador = :id_colaborador
            ";

            $stmtUpdate =
                $pdo->prepare($sqlUpdate);

            $stmtUpdate->execute([
                ':nombre_completo' =>
                    $nombre_completo,

                ':usuario_dominio' =>
                    $usuario_dominio,

                ':correo_corporativo' =>
                    $correo_corporativo,

                ':id_tipo_colaborador' =>
                    (int) $id_tipo_colaborador,

                ':id_colaborador' =>
                    $id_colaborador
            ]);


            /*
            |--------------------------------------------------------------------------
            | Renovar token CSRF
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );


            header(
                'Location: colaboradores.php?actualizacion=ok'
            );

            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $errores[] =
                    'No fue posible actualizar porque el usuario de dominio o el correo corporativo ya existe.';

            } else {

                $errores[] =
                    'Ocurrió un error al actualizar el colaborador.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Escapar salida HTML
|--------------------------------------------------------------------------
*/

function e(?string $valor): string
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

    <title>Editar colaborador | SIGATI</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            min-height: 100vh;
        }

        .topbar {
            background: #172033;
            color: #ffffff;
            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            font-size: 22px;
        }

        .topbar-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .usuario {
            font-size: 14px;
            color: #d1d5db;
        }

        .rol {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            background: #ffffff;
            color: #172033;

            font-size: 12px;
            font-weight: bold;
        }

        .contenedor {
            max-width: 850px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .encabezado {
            margin-bottom: 25px;
        }

        .encabezado h2 {
            font-size: 26px;
            margin-bottom: 7px;
        }

        .encabezado p {
            color: #6b7280;
            font-size: 14px;
        }

        .panel {
            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.07);

            padding: 30px;
        }

        .mensaje-error {
            padding: 14px 18px;

            margin-bottom: 22px;

            border-radius: 7px;

            background: #fee2e2;
            color: #991b1b;

            border: 1px solid #fecaca;
        }

        .mensaje-error ul {
            padding-left: 20px;
        }

        .mensaje-error li + li {
            margin-top: 5px;
        }

        .grupo {
            margin-bottom: 20px;
        }

        .grupo label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: bold;

            color: #374151;
        }

        .grupo input,
        .grupo select {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-size: 15px;

            background: #ffffff;

            color: #1f2937;
        }

        .grupo input:focus,
        .grupo select:focus {
            outline: none;
            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.10);
        }

        .ayuda {
            display: block;

            margin-top: 6px;

            color: #6b7280;

            font-size: 12px;
        }

        .acciones {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 28px;

            flex-wrap: wrap;
        }

        .boton {
            display: inline-block;

            padding: 11px 18px;

            border: none;

            border-radius: 7px;

            text-decoration: none;

            font-size: 14px;
            font-weight: bold;

            cursor: pointer;
        }

        .boton-guardar {
            background: #2563eb;
            color: #ffffff;
        }

        .boton-guardar:hover {
            background: #1d4ed8;
        }

        .boton-cancelar {
            background: #e5e7eb;
            color: #1f2937;
        }

        .boton-cancelar:hover {
            background: #d1d5db;
        }

        @media (max-width: 600px) {

            .topbar {
                padding: 15px 20px;
            }

            .contenedor {
                margin-top: 20px;
                padding: 0 12px;
            }

            .panel {
                padding: 20px;
            }

            .acciones {
                flex-direction: column-reverse;
            }

            .boton {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>

<header class="topbar">

    <h1>SIGATI</h1>

    <div class="topbar-info">

        <span class="usuario">

            <?= e(
                $_SESSION['nombre_completo']
                ?? $_SESSION['nombre_usuario']
                ?? 'Usuario'
            ); ?>

        </span>

        <span class="rol">

            <?= e(
                $_SESSION['rol']
                ?? 'Sin rol'
            ); ?>

        </span>

    </div>

</header>


<main class="contenedor">

    <section class="encabezado">

        <h2>Editar colaborador</h2>

        <p>
            Actualiza la información del colaborador seleccionado.
        </p>

    </section>


    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    Revisa la información ingresada:
                </strong>

                <ul>

                    <?php foreach ($errores as $error): ?>

                        <li>
                            <?= e($error); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <?= csrf_field() ?>


            <div class="grupo">

                <label for="nombre_completo">
                    Nombre completo
                </label>

                <input
                    type="text"
                    id="nombre_completo"
                    name="nombre_completo"
                    maxlength="150"
                    value="<?= e($nombre_completo); ?>"
                    required
                    autocomplete="name"
                >

            </div>


            <div class="grupo">

                <label for="usuario_dominio">
                    Usuario de dominio
                </label>

                <input
                    type="text"
                    id="usuario_dominio"
                    name="usuario_dominio"
                    maxlength="100"
                    value="<?= e($usuario_dominio); ?>"
                    required
                    autocomplete="off"
                >

                <span class="ayuda">
                    Debe ser único dentro de SIGATI.
                </span>

            </div>


            <div class="grupo">

                <label for="correo_corporativo">
                    Correo corporativo
                </label>

                <input
                    type="email"
                    id="correo_corporativo"
                    name="correo_corporativo"
                    maxlength="150"
                    value="<?= e($correo_corporativo); ?>"
                    required
                    autocomplete="email"
                >

                <span class="ayuda">
                    Debe ser único dentro de SIGATI.
                </span>

            </div>


            <div class="grupo">

                <label for="id_tipo_colaborador">
                    Tipo de colaborador
                </label>

                <select
                    id="id_tipo_colaborador"
                    name="id_tipo_colaborador"
                    required
                >

                    <option value="">
                        Selecciona una opción
                    </option>

                    <?php foreach (
                        $tipos_colaborador as $tipo
                    ): ?>

                        <option
                            value="<?= (int) $tipo['id_tipo_colaborador']; ?>"
                            <?= (
                                (string) $id_tipo_colaborador
                                ===
                                (string) $tipo['id_tipo_colaborador']
                            ) ? 'selected' : ''; ?>
                        >
                            <?= e($tipo['nombre_tipo']); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="acciones">

                <a
                    href="colaboradores.php"
                    class="boton boton-cancelar"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="boton boton-guardar"
                >
                    Guardar cambios
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>