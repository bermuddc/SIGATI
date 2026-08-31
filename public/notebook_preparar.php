<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_role('Administrador TI');

/*
|--------------------------------------------------------------------------
| Función para escapar HTML
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

/*
|--------------------------------------------------------------------------
| Validar ID del notebook
|--------------------------------------------------------------------------
*/

$id_notebook = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id_notebook || $id_notebook <= 0) {
    header('Location: notebooks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Obtener notebook
|--------------------------------------------------------------------------
*/

try {

    $sqlNotebook = "
        SELECT
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            n.procesador,
            n.ram_gb,
            n.capacidad_disco_gb,
            n.nombre_equipo_actual,
            n.id_estado,
            e.nombre_estado
        FROM notebook n
        INNER JOIN estado_notebook e
            ON n.id_estado = e.id_estado
        WHERE n.id_notebook = :id_notebook
        LIMIT 1
    ";

    $stmtNotebook = $pdo->prepare($sqlNotebook);

    $stmtNotebook->execute([
        ':id_notebook' => $id_notebook
    ]);

    $notebook = $stmtNotebook->fetch(PDO::FETCH_ASSOC);

    if (!$notebook) {
        header('Location: notebooks.php');
        exit;
    }

} catch (PDOException $e) {

    header('Location: notebooks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Solo notebooks Ingresados pueden prepararse
|--------------------------------------------------------------------------
*/

if ($notebook['nombre_estado'] !== 'Ingresado') {
    header('Location: notebooks.php');
    exit;
}

$errores = [];

$nombre_equipo = trim(
    (string) (
        $notebook['nombre_equipo_actual']
        ?? ''
    )
);

/*
|--------------------------------------------------------------------------
| Procesar formulario
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validar CSRF
    |--------------------------------------------------------------------------
    |
    | Si el token no existe o no corresponde a la sesión actual,
    | validate_csrf() detiene inmediatamente la solicitud con HTTP 403.
    |
    */

    validate_csrf();

    $nombre_equipo = strtoupper(
        trim($_POST['nombre_equipo'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Validar nombre de equipo
    |--------------------------------------------------------------------------
    */

    if ($nombre_equipo === '') {

        $errores[] =
            'Debes ingresar el nombre que será asignado al equipo.';

    } elseif (
        mb_strlen($nombre_equipo) > 100
    ) {

        $errores[] =
            'El nombre del equipo no puede superar los 100 caracteres.';

    } elseif (
        !preg_match(
            '/^[A-Z0-9\-]+$/',
            $nombre_equipo
        )
    ) {

        $errores[] =
            'El nombre del equipo solo puede contener letras, números y guiones.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validar usuario de sesión
    |--------------------------------------------------------------------------
    */

    $id_usuario_sistema =
        (int) (
            $_SESSION['usuario_id']
            ?? 0
        );

    if ($id_usuario_sistema <= 0) {

        $errores[] =
            'No fue posible identificar al usuario responsable.';
    }

    /*
    |--------------------------------------------------------------------------
    | Procesar preparación mediante transacción
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Bloquear notebook y volver a comprobar estado
            |--------------------------------------------------------------------------
            */

            $sqlBloqueo = "
                SELECT
                    n.id_notebook,
                    n.id_estado,
                    e.nombre_estado
                FROM notebook n
                INNER JOIN estado_notebook e
                    ON n.id_estado = e.id_estado
                WHERE n.id_notebook = :id_notebook
                FOR UPDATE
            ";

            $stmtBloqueo =
                $pdo->prepare($sqlBloqueo);

            $stmtBloqueo->execute([
                ':id_notebook' =>
                    $id_notebook
            ]);

            $notebookBloqueado =
                $stmtBloqueo->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$notebookBloqueado) {

                throw new RuntimeException(
                    'El notebook no existe.'
                );
            }

            if (
                $notebookBloqueado['nombre_estado']
                !== 'Ingresado'
            ) {

                throw new RuntimeException(
                    'El notebook ya no se encuentra en estado Ingresado.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verificar que el nombre no esté activo en otro notebook
            |--------------------------------------------------------------------------
            */

            $sqlNombre = "
                SELECT COUNT(*)
                FROM notebook n
                INNER JOIN estado_notebook e
                    ON n.id_estado = e.id_estado
                WHERE n.nombre_equipo_actual = :nombre_equipo
                  AND n.id_notebook <> :id_notebook
                  AND e.nombre_estado NOT IN (
                      'Desactivado',
                      'Decomisado'
                  )
            ";

            $stmtNombre =
                $pdo->prepare($sqlNombre);

            $stmtNombre->execute([
                ':nombre_equipo' =>
                    $nombre_equipo,

                ':id_notebook' =>
                    $id_notebook
            ]);

            if (
                (int) $stmtNombre->fetchColumn()
                > 0
            ) {

                throw new RuntimeException(
                    'El nombre de equipo ya se encuentra utilizado por otro notebook activo.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Obtener estado "En preparación"
            |--------------------------------------------------------------------------
            */

            $sqlEstadoNuevo = "
                SELECT id_estado
                FROM estado_notebook
                WHERE nombre_estado = 'En preparación'
                LIMIT 1
            ";

            $stmtEstadoNuevo =
                $pdo->prepare($sqlEstadoNuevo);

            $stmtEstadoNuevo->execute();

            $id_estado_nuevo =
                (int) $stmtEstadoNuevo->fetchColumn();

            if ($id_estado_nuevo <= 0) {

                throw new RuntimeException(
                    'No se encontró el estado En preparación.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Obtener tipo de movimiento "Preparación"
            |--------------------------------------------------------------------------
            */

            $sqlTipoMovimiento = "
                SELECT id_tipo_movimiento
                FROM tipo_movimiento
                WHERE nombre_tipo = 'Preparación'
                LIMIT 1
            ";

            $stmtTipoMovimiento =
                $pdo->prepare(
                    $sqlTipoMovimiento
                );

            $stmtTipoMovimiento->execute();

            $id_tipo_movimiento =
                (int) $stmtTipoMovimiento
                    ->fetchColumn();

            if ($id_tipo_movimiento <= 0) {

                throw new RuntimeException(
                    'No se encontró el tipo de movimiento Preparación.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar notebook
            |--------------------------------------------------------------------------
            */

            $sqlUpdateNotebook = "
                UPDATE notebook
                SET
                    nombre_equipo_actual = :nombre_equipo,
                    id_estado = :id_estado_nuevo
                WHERE id_notebook = :id_notebook
            ";

            $stmtUpdateNotebook =
                $pdo->prepare(
                    $sqlUpdateNotebook
                );

            $stmtUpdateNotebook->execute([
                ':nombre_equipo' =>
                    $nombre_equipo,

                ':id_estado_nuevo' =>
                    $id_estado_nuevo,

                ':id_notebook' =>
                    $id_notebook
            ]);

            /*
            |--------------------------------------------------------------------------
            | Registrar movimiento
            |--------------------------------------------------------------------------
            */

            $sqlMovimiento = "
                INSERT INTO movimiento (
                    id_notebook,
                    id_tipo_movimiento,
                    id_motivo,
                    id_usuario_sistema,
                    id_asignacion_origen,
                    id_asignacion_destino,
                    id_estado_anterior,
                    id_estado_nuevo,
                    observacion
                )
                VALUES (
                    :id_notebook,
                    :id_tipo_movimiento,
                    NULL,
                    :id_usuario_sistema,
                    NULL,
                    NULL,
                    :id_estado_anterior,
                    :id_estado_nuevo,
                    :observacion
                )
            ";

            $stmtMovimiento =
                $pdo->prepare(
                    $sqlMovimiento
                );

            $stmtMovimiento->execute([
                ':id_notebook' =>
                    $id_notebook,

                ':id_tipo_movimiento' =>
                    $id_tipo_movimiento,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':id_estado_anterior' =>
                    (int) $notebookBloqueado[
                        'id_estado'
                    ],

                ':id_estado_nuevo' =>
                    $id_estado_nuevo,

                ':observacion' =>
                    'Notebook enviado a preparación. ' .
                    'Nombre de equipo asignado: ' .
                    $nombre_equipo
            ]);

            /*
            |--------------------------------------------------------------------------
            | Confirmar transacción
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | Renovar token CSRF
            |--------------------------------------------------------------------------
            |
            | Después de una operación exitosa reemplazamos el token
            | anterior por uno nuevo.
            |
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );

            header(
                'Location: notebooks.php?preparacion=ok'
            );

            exit;

        } catch (
            RuntimeException | PDOException $e
        ) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errores[] = $e->getMessage();
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

    <title>Preparar notebook | SIGATI</title>

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
            padding: 30px;
            border-radius: 10px;
            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .informacion {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            background: #f9fafb;
            border:
                1px solid #e5e7eb;
        }

        .dato strong {
            display: block;
            margin-bottom: 4px;
            color: #374151;
            font-size: 13px;
        }

        .dato span {
            font-size: 14px;
        }

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: bold;
        }

        .mensaje-error {
            padding: 14px 18px;
            margin-bottom: 22px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
            border:
                1px solid #fecaca;
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

        .grupo input {
            width: 100%;
            padding: 12px;
            border:
                1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
            text-transform: uppercase;
        }

        .grupo input:focus {
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

        .aviso {
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 7px;
            background: #fef3c7;
            color: #92400e;
            border:
                1px solid #fde68a;
            font-size: 14px;
            line-height: 1.5;
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

        .boton-principal {
            background: #2563eb;
            color: #ffffff;
        }

        .boton-principal:hover {
            background: #1d4ed8;
        }

        .boton-secundario {
            background: #e5e7eb;
            color: #1f2937;
        }

        .boton-secundario:hover {
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

            .informacion {
                grid-template-columns: 1fr;
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

        <h2>Preparar notebook</h2>

        <p>
            Asigna el nombre del equipo e inicia su proceso
            de preparación.
        </p>

    </section>

    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    No fue posible completar la preparación:
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

        <div class="informacion">

            <div class="dato">

                <strong>
                    Número de serie
                </strong>

                <span>
                    <?= e(
                        $notebook['numero_serie']
                    ); ?>
                </span>

            </div>

            <div class="dato">

                <strong>
                    Marca / modelo
                </strong>

                <span>

                    <?= e(
                        $notebook['marca']
                        . ' '
                        . $notebook['modelo']
                    ); ?>

                </span>

            </div>

            <div class="dato">

                <strong>
                    Procesador
                </strong>

                <span>
                    <?= e(
                        $notebook['procesador']
                    ); ?>
                </span>

            </div>

            <div class="dato">

                <strong>
                    Estado actual
                </strong>

                <span class="estado">
                    <?= e(
                        $notebook['nombre_estado']
                    ); ?>
                </span>

            </div>

        </div>

        <div class="aviso">

            Al confirmar, SIGATI cambiará el notebook desde
            <strong>Ingresado</strong>
            a
            <strong>En preparación</strong>
            y registrará automáticamente el movimiento
            en su historial.

        </div>

        <form method="POST" action="">

            <?= csrf_field() ?>

            <div class="grupo">

                <label for="nombre_equipo">
                    Nombre del equipo
                </label>

                <input
                    type="text"
                    id="nombre_equipo"
                    name="nombre_equipo"
                    maxlength="100"
                    placeholder="Ejemplo: CL-NB-0002"
                    value="<?= e(
                        $nombre_equipo
                    ); ?>"
                    required
                    autocomplete="off"
                >

                <span class="ayuda">

                    Corresponde al nombre asignado al
                    notebook durante su preparación.

                </span>

            </div>

            <div class="acciones">

                <a
                    href="notebooks.php"
                    class="boton boton-secundario"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="boton boton-principal"
                >
                    Iniciar preparación
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>