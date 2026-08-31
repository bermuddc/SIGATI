<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_role('Administrador TI');


function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


$errores = [];

$id_notebook = '';
$id_colaborador = '';
$id_area = '';
$piso = '';
$asiento = '';


/*
|--------------------------------------------------------------------------
| Cargar notebooks disponibles para asignación
|--------------------------------------------------------------------------
|
| Solamente se muestran notebooks que:
| - Están en estado "En preparación"
| - No tienen una asignación activa
|
*/

try {

    $sqlNotebooks = "
        SELECT
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            n.nombre_equipo_actual
        FROM notebook n
        INNER JOIN estado_notebook e
            ON n.id_estado = e.id_estado
        WHERE e.nombre_estado = 'En preparación'
          AND NOT EXISTS (
                SELECT 1
                FROM asignacion a
                WHERE a.id_notebook = n.id_notebook
                  AND a.fecha_fin IS NULL
          )
        ORDER BY n.numero_serie
    ";

    $stmtNotebooks =
        $pdo->prepare($sqlNotebooks);

    $stmtNotebooks->execute();

    $notebooks =
        $stmtNotebooks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notebooks = [];

    $errores[] =
        'No fue posible cargar los notebooks disponibles.';
}


/*
|--------------------------------------------------------------------------
| Cargar colaboradores
|--------------------------------------------------------------------------
*/

try {

    $sqlColaboradores = "
        SELECT
            id_colaborador,
            nombre_completo,
            usuario_dominio
        FROM colaborador
        ORDER BY nombre_completo
    ";

    $stmtColaboradores =
        $pdo->prepare($sqlColaboradores);

    $stmtColaboradores->execute();

    $colaboradores =
        $stmtColaboradores->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $colaboradores = [];

    $errores[] =
        'No fue posible cargar los colaboradores.';
}


/*
|--------------------------------------------------------------------------
| Cargar áreas
|--------------------------------------------------------------------------
*/

try {

    $sqlAreas = "
        SELECT
            id_area,
            nombre_area
        FROM area
        ORDER BY nombre_area
    ";

    $stmtAreas =
        $pdo->prepare($sqlAreas);

    $stmtAreas->execute();

    $areas =
        $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $areas = [];

    $errores[] =
        'No fue posible cargar las áreas.';
}


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
    */

    validate_csrf();


    $id_notebook =
        trim($_POST['id_notebook'] ?? '');

    $id_colaborador =
        trim($_POST['id_colaborador'] ?? '');

    $id_area =
        trim($_POST['id_area'] ?? '');

    $piso =
        trim($_POST['piso'] ?? '');

    $asiento =
        trim($_POST['asiento'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validaciones generales
    |--------------------------------------------------------------------------
    */

    if (
        $id_notebook === ''
        || !ctype_digit($id_notebook)
    ) {

        $errores[] =
            'Debes seleccionar un notebook válido.';
    }


    if (
        $id_colaborador === ''
        || !ctype_digit($id_colaborador)
    ) {

        $errores[] =
            'Debes seleccionar un colaborador válido.';
    }


    if (
        $id_area === ''
        || !ctype_digit($id_area)
    ) {

        $errores[] =
            'Debes seleccionar un área válida.';
    }


    if (
        $piso === ''
        || !ctype_digit($piso)
        || (int) $piso < 1
        || (int) $piso > 4
    ) {

        $errores[] =
            'El piso debe estar entre 1 y 4.';
    }


    if ($asiento === '') {

        $errores[] =
            'Debes ingresar el número o identificador del asiento.';

    } elseif (
        mb_strlen($asiento) > 30
    ) {

        $errores[] =
            'El asiento no puede superar los 30 caracteres.';
    }


    /*
    |--------------------------------------------------------------------------
    | Identificar usuario responsable
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
    | Crear asignación mediante transacción
    |--------------------------------------------------------------------------
    */

    if (empty($errores)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Bloquear notebook
            |--------------------------------------------------------------------------
            */

            $sqlNotebook = "
                SELECT
                    n.id_notebook,
                    n.numero_serie,
                    n.nombre_equipo_actual,
                    n.id_estado,
                    e.nombre_estado
                FROM notebook n
                INNER JOIN estado_notebook e
                    ON n.id_estado = e.id_estado
                WHERE n.id_notebook = :id_notebook
                FOR UPDATE
            ";

            $stmtNotebook =
                $pdo->prepare($sqlNotebook);

            $stmtNotebook->execute([
                ':id_notebook' =>
                    (int) $id_notebook
            ]);

            $notebook =
                $stmtNotebook->fetch(PDO::FETCH_ASSOC);


            if (!$notebook) {

                throw new RuntimeException(
                    'El notebook seleccionado no existe.'
                );
            }


            if (
                $notebook['nombre_estado']
                !== 'En preparación'
            ) {

                throw new RuntimeException(
                    'El notebook ya no se encuentra disponible para asignación.'
                );
            }


            if (
                empty(
                    $notebook['nombre_equipo_actual']
                )
            ) {

                throw new RuntimeException(
                    'El notebook no posee un nombre de equipo asignado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verificar que no exista asignación activa
            |--------------------------------------------------------------------------
            */

            $sqlAsignacionActiva = "
                SELECT COUNT(*)
                FROM asignacion
                WHERE id_notebook = :id_notebook
                  AND fecha_fin IS NULL
            ";

            $stmtAsignacionActiva =
                $pdo->prepare($sqlAsignacionActiva);

            $stmtAsignacionActiva->execute([
                ':id_notebook' =>
                    (int) $id_notebook
            ]);

            if (
                (int) $stmtAsignacionActiva->fetchColumn()
                > 0
            ) {

                throw new RuntimeException(
                    'El notebook ya posee una asignación activa.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validar colaborador
            |--------------------------------------------------------------------------
            */

            $sqlColaborador = "
                SELECT COUNT(*)
                FROM colaborador
                WHERE id_colaborador = :id_colaborador
            ";

            $stmtColaborador =
                $pdo->prepare($sqlColaborador);

            $stmtColaborador->execute([
                ':id_colaborador' =>
                    (int) $id_colaborador
            ]);

            if (
                (int) $stmtColaborador->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'El colaborador seleccionado no existe.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validar área
            |--------------------------------------------------------------------------
            */

            $sqlArea = "
                SELECT COUNT(*)
                FROM area
                WHERE id_area = :id_area
            ";

            $stmtArea =
                $pdo->prepare($sqlArea);

            $stmtArea->execute([
                ':id_area' =>
                    (int) $id_area
            ]);

            if (
                (int) $stmtArea->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'El área seleccionada no existe.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener estado "Asignado"
            |--------------------------------------------------------------------------
            */

            $sqlEstadoAsignado = "
                SELECT id_estado
                FROM estado_notebook
                WHERE nombre_estado = 'Asignado'
                LIMIT 1
            ";

            $stmtEstadoAsignado =
                $pdo->prepare($sqlEstadoAsignado);

            $stmtEstadoAsignado->execute();

            $id_estado_asignado =
                (int) $stmtEstadoAsignado->fetchColumn();


            if ($id_estado_asignado <= 0) {

                throw new RuntimeException(
                    'No se encontró el estado Asignado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener movimiento "Asignación"
            |--------------------------------------------------------------------------
            */

            $sqlTipoMovimiento = "
                SELECT id_tipo_movimiento
                FROM tipo_movimiento
                WHERE nombre_tipo = 'Asignación'
                LIMIT 1
            ";

            $stmtTipoMovimiento =
                $pdo->prepare($sqlTipoMovimiento);

            $stmtTipoMovimiento->execute();

            $id_tipo_movimiento =
                (int) $stmtTipoMovimiento->fetchColumn();


            if ($id_tipo_movimiento <= 0) {

                throw new RuntimeException(
                    'No se encontró el tipo de movimiento Asignación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Insertar asignación
            |--------------------------------------------------------------------------
            */

            $sqlInsertAsignacion = "
                INSERT INTO asignacion (
                    id_notebook,
                    id_colaborador,
                    id_area,
                    id_usuario_sistema,
                    nombre_equipo,
                    piso,
                    asiento
                )
                VALUES (
                    :id_notebook,
                    :id_colaborador,
                    :id_area,
                    :id_usuario_sistema,
                    :nombre_equipo,
                    :piso,
                    :asiento
                )
            ";

            $stmtInsertAsignacion =
                $pdo->prepare($sqlInsertAsignacion);

            $stmtInsertAsignacion->execute([
                ':id_notebook' =>
                    (int) $id_notebook,

                ':id_colaborador' =>
                    (int) $id_colaborador,

                ':id_area' =>
                    (int) $id_area,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':nombre_equipo' =>
                    $notebook['nombre_equipo_actual'],

                ':piso' =>
                    (int) $piso,

                ':asiento' =>
                    $asiento
            ]);


            $id_asignacion =
                (int) $pdo->lastInsertId();


            if ($id_asignacion <= 0) {

                throw new RuntimeException(
                    'No fue posible obtener la nueva asignación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Cambiar notebook a Asignado
            |--------------------------------------------------------------------------
            */

            $sqlUpdateNotebook = "
                UPDATE notebook
                SET id_estado = :id_estado
                WHERE id_notebook = :id_notebook
            ";

            $stmtUpdateNotebook =
                $pdo->prepare($sqlUpdateNotebook);

            $stmtUpdateNotebook->execute([
                ':id_estado' =>
                    $id_estado_asignado,

                ':id_notebook' =>
                    (int) $id_notebook
            ]);


            /*
            |--------------------------------------------------------------------------
            | Registrar movimiento de asignación
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
                    :id_asignacion_destino,
                    :id_estado_anterior,
                    :id_estado_nuevo,
                    :observacion
                )
            ";

            $stmtMovimiento =
                $pdo->prepare($sqlMovimiento);

            $stmtMovimiento->execute([
                ':id_notebook' =>
                    (int) $id_notebook,

                ':id_tipo_movimiento' =>
                    $id_tipo_movimiento,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':id_asignacion_destino' =>
                    $id_asignacion,

                ':id_estado_anterior' =>
                    (int) $notebook['id_estado'],

                ':id_estado_nuevo' =>
                    $id_estado_asignado,

                ':observacion' =>
                    'Notebook asignado al colaborador mediante SIGATI.'
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
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );


            header(
                'Location: asignaciones.php?registro=ok'
            );

            exit;


        } catch (
            RuntimeException | PDOException $e
        ) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }

            $errores[] =
                $e->getMessage();
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

    <title>Nueva asignación | SIGATI</title>

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
            max-width: 900px;

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

        .aviso {
            padding: 14px;

            margin-bottom: 22px;

            border-radius: 7px;

            background: #eff6ff;

            color: #1e40af;

            border: 1px solid #bfdbfe;

            font-size: 14px;

            line-height: 1.5;
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

            background: #ffffff;

            color: #1f2937;

            font-size: 15px;
        }

        .grupo input:focus,
        .grupo select:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.10);
        }

        .fila {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;
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

        @media (max-width: 650px) {

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

            .fila {
                grid-template-columns: 1fr;
                gap: 0;
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

        <h2>Nueva asignación</h2>

        <p>
            Asigna un notebook preparado a un colaborador.
        </p>

    </section>


    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    No fue posible registrar la asignación:
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


        <div class="aviso">

            Solo aparecen notebooks que se encuentran en estado
            <strong>En preparación</strong>
            y que no poseen una asignación activa.

        </div>


        <form method="POST" action="">

            <?= csrf_field() ?>


            <div class="grupo">

                <label for="id_notebook">
                    Notebook
                </label>

                <select
                    id="id_notebook"
                    name="id_notebook"
                    required
                >

                    <option value="">
                        Selecciona un notebook
                    </option>

                    <?php foreach ($notebooks as $item): ?>

                        <option
                            value="<?= (int) $item['id_notebook']; ?>"
                            <?= (
                                (string) $id_notebook
                                ===
                                (string) $item['id_notebook']
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $item['numero_serie']
                                . ' | '
                                . $item['marca']
                                . ' '
                                . $item['modelo']
                                . ' | '
                                . $item['nombre_equipo_actual']
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="grupo">

                <label for="id_colaborador">
                    Colaborador
                </label>

                <select
                    id="id_colaborador"
                    name="id_colaborador"
                    required
                >

                    <option value="">
                        Selecciona un colaborador
                    </option>

                    <?php foreach (
                        $colaboradores as $colaborador
                    ): ?>

                        <option
                            value="<?= (int) $colaborador['id_colaborador']; ?>"
                            <?= (
                                (string) $id_colaborador
                                ===
                                (string) $colaborador['id_colaborador']
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $colaborador['nombre_completo']
                                . ' | '
                                . $colaborador['usuario_dominio']
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="grupo">

                <label for="id_area">
                    Área
                </label>

                <select
                    id="id_area"
                    name="id_area"
                    required
                >

                    <option value="">
                        Selecciona un área
                    </option>

                    <?php foreach ($areas as $area): ?>

                        <option
                            value="<?= (int) $area['id_area']; ?>"
                            <?= (
                                (string) $id_area
                                ===
                                (string) $area['id_area']
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $area['nombre_area']
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="fila">

                <div class="grupo">

                    <label for="piso">
                        Piso
                    </label>

                    <select
                        id="piso"
                        name="piso"
                        required
                    >

                        <option value="">
                            Selecciona
                        </option>

                        <?php for (
                            $numeroPiso = 1;
                            $numeroPiso <= 4;
                            $numeroPiso++
                        ): ?>

                            <option
                                value="<?= $numeroPiso; ?>"
                                <?= (
                                    (string) $piso
                                    ===
                                    (string) $numeroPiso
                                ) ? 'selected' : ''; ?>
                            >

                                Piso <?= $numeroPiso; ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="grupo">

                    <label for="asiento">
                        Asiento
                    </label>

                    <input
                        type="text"
                        id="asiento"
                        name="asiento"
                        maxlength="30"
                        value="<?= e($asiento); ?>"
                        placeholder="Ejemplo: 215"
                        required
                        autocomplete="off"
                    >

                    <span class="ayuda">
                        Número o identificador del puesto físico.
                    </span>

                </div>

            </div>


            <div class="acciones">

                <a
                    href="asignaciones.php"
                    class="boton boton-secundario"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="boton boton-principal"
                >
                    Registrar asignación
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>