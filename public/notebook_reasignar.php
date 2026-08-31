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


/*
|--------------------------------------------------------------------------
| Validar notebook
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
| Obtener notebook TBA y última asignación cerrada
|--------------------------------------------------------------------------
*/

try {

    $sqlNotebook = "
        SELECT
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
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

    if ($notebook['nombre_estado'] !== 'TBA') {
        header('Location: notebooks.php');
        exit;
    }


    $sqlAnterior = "
        SELECT
            a.id_asignacion,
            a.id_colaborador,
            a.id_area,
            a.nombre_equipo,
            a.piso,
            a.asiento,
            a.fecha_inicio,
            a.fecha_fin,

            c.nombre_completo AS colaborador_anterior,
            c.usuario_dominio AS usuario_anterior,

            ar.nombre_area AS area_anterior

        FROM asignacion a

        INNER JOIN colaborador c
            ON a.id_colaborador = c.id_colaborador

        INNER JOIN area ar
            ON a.id_area = ar.id_area

        WHERE a.id_notebook = :id_notebook
          AND a.fecha_fin IS NOT NULL

        ORDER BY
            a.fecha_fin DESC,
            a.id_asignacion DESC

        LIMIT 1
    ";

    $stmtAnterior = $pdo->prepare($sqlAnterior);

    $stmtAnterior->execute([
        ':id_notebook' => $id_notebook
    ]);

    $asignacionAnterior =
        $stmtAnterior->fetch(PDO::FETCH_ASSOC);

    if (!$asignacionAnterior) {
        header('Location: notebooks.php');
        exit;
    }

} catch (PDOException $e) {

    header('Location: notebooks.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Colaboradores
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
}


/*
|--------------------------------------------------------------------------
| Áreas
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

    $stmtAreas = $pdo->prepare($sqlAreas);
    $stmtAreas->execute();

    $areas = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $areas = [];
}


/*
|--------------------------------------------------------------------------
| Motivos
|--------------------------------------------------------------------------
*/

try {

    $sqlMotivos = "
        SELECT
            id_motivo,
            nombre_motivo
        FROM motivo_movimiento
        ORDER BY nombre_motivo
    ";

    $stmtMotivos = $pdo->prepare($sqlMotivos);
    $stmtMotivos->execute();

    $motivos = $stmtMotivos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $motivos = [];
}


$errores = [];

$id_colaborador = '';
$id_area = '';
$piso = '';
$asiento = '';
$id_motivo = '';
$observacion = '';


/*
|--------------------------------------------------------------------------
| Procesar reasignación
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validar CSRF
    |--------------------------------------------------------------------------
    */

    validate_csrf();


    $id_colaborador =
        trim($_POST['id_colaborador'] ?? '');

    $id_area =
        trim($_POST['id_area'] ?? '');

    $piso =
        trim($_POST['piso'] ?? '');

    $asiento =
        trim($_POST['asiento'] ?? '');

    $id_motivo =
        trim($_POST['id_motivo'] ?? '');

    $observacion =
        trim($_POST['observacion'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validaciones
    |--------------------------------------------------------------------------
    */

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
            'Debes ingresar el asiento.';

    } elseif (mb_strlen($asiento) > 30) {

        $errores[] =
            'El asiento no puede superar los 30 caracteres.';
    }


    if (
        $id_motivo === ''
        || !ctype_digit($id_motivo)
    ) {

        $errores[] =
            'Debes seleccionar un motivo.';
    }


    /*
    |--------------------------------------------------------------------------
    | Reasignación exige observación
    |--------------------------------------------------------------------------
    */

    if ($observacion === '') {

        $errores[] =
            'La observación es obligatoria para una reasignación.';

    } elseif (mb_strlen($observacion) > 500) {

        $errores[] =
            'La observación no puede superar los 500 caracteres.';
    }


    if (
        ctype_digit($id_colaborador)
        &&
        (int) $id_colaborador
        ===
        (int) $asignacionAnterior['id_colaborador']
    ) {

        $errores[] =
            'La reasignación debe realizarse a un colaborador diferente al anterior.';
    }


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
    | Transacción
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

            $sqlBloqueo = "
                SELECT
                    n.id_notebook,
                    n.id_estado,
                    n.nombre_equipo_actual,
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
                ':id_notebook' => $id_notebook
            ]);

            $notebookBloqueado =
                $stmtBloqueo->fetch(PDO::FETCH_ASSOC);


            if (!$notebookBloqueado) {

                throw new RuntimeException(
                    'El notebook no existe.'
                );
            }


            if (
                $notebookBloqueado['nombre_estado']
                !== 'TBA'
            ) {

                throw new RuntimeException(
                    'El notebook ya no se encuentra en estado TBA.'
                );
            }


            if (
                empty(
                    $notebookBloqueado['nombre_equipo_actual']
                )
            ) {

                throw new RuntimeException(
                    'El notebook no posee nombre de equipo.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | No debe existir asignación activa
            |--------------------------------------------------------------------------
            */

            $sqlActiva = "
                SELECT COUNT(*)
                FROM asignacion
                WHERE id_notebook = :id_notebook
                  AND fecha_fin IS NULL
            ";

            $stmtActiva =
                $pdo->prepare($sqlActiva);

            $stmtActiva->execute([
                ':id_notebook' => $id_notebook
            ]);


            if (
                (int) $stmtActiva->fetchColumn()
                > 0
            ) {

                throw new RuntimeException(
                    'El notebook ya posee una asignación activa.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener asignación origen nuevamente
            |--------------------------------------------------------------------------
            */

            $sqlOrigen = "
                SELECT
                    id_asignacion,
                    id_colaborador
                FROM asignacion
                WHERE id_notebook = :id_notebook
                  AND fecha_fin IS NOT NULL
                ORDER BY
                    fecha_fin DESC,
                    id_asignacion DESC
                LIMIT 1
                FOR UPDATE
            ";

            $stmtOrigen =
                $pdo->prepare($sqlOrigen);

            $stmtOrigen->execute([
                ':id_notebook' => $id_notebook
            ]);

            $origen =
                $stmtOrigen->fetch(PDO::FETCH_ASSOC);


            if (!$origen) {

                throw new RuntimeException(
                    'No se encontró la asignación anterior.'
                );
            }


            if (
                (int) $origen['id_colaborador']
                ===
                (int) $id_colaborador
            ) {

                throw new RuntimeException(
                    'El nuevo colaborador debe ser diferente al anterior.'
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
                ':id_area' => (int) $id_area
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
            | Validar motivo
            |--------------------------------------------------------------------------
            */

            $sqlMotivo = "
                SELECT COUNT(*)
                FROM motivo_movimiento
                WHERE id_motivo = :id_motivo
            ";

            $stmtMotivo =
                $pdo->prepare($sqlMotivo);

            $stmtMotivo->execute([
                ':id_motivo' =>
                    (int) $id_motivo
            ]);


            if (
                (int) $stmtMotivo->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'El motivo seleccionado no existe.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener estado Asignado
            |--------------------------------------------------------------------------
            */

            $sqlEstado = "
                SELECT id_estado
                FROM estado_notebook
                WHERE nombre_estado = 'Asignado'
                LIMIT 1
            ";

            $stmtEstado =
                $pdo->prepare($sqlEstado);

            $stmtEstado->execute();

            $id_estado_asignado =
                (int) $stmtEstado->fetchColumn();


            if ($id_estado_asignado <= 0) {

                throw new RuntimeException(
                    'No se encontró el estado Asignado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener tipo Reasignación
            |--------------------------------------------------------------------------
            */

            $sqlTipo = "
                SELECT id_tipo_movimiento
                FROM tipo_movimiento
                WHERE nombre_tipo = 'Reasignación'
                LIMIT 1
            ";

            $stmtTipo =
                $pdo->prepare($sqlTipo);

            $stmtTipo->execute();

            $id_tipo_movimiento =
                (int) $stmtTipo->fetchColumn();


            if ($id_tipo_movimiento <= 0) {

                throw new RuntimeException(
                    'No se encontró el movimiento Reasignación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Crear nueva asignación
            |--------------------------------------------------------------------------
            */

            $sqlNuevaAsignacion = "
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

            $stmtNuevaAsignacion =
                $pdo->prepare($sqlNuevaAsignacion);

            $stmtNuevaAsignacion->execute([
                ':id_notebook' =>
                    $id_notebook,

                ':id_colaborador' =>
                    (int) $id_colaborador,

                ':id_area' =>
                    (int) $id_area,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':nombre_equipo' =>
                    $notebookBloqueado[
                        'nombre_equipo_actual'
                    ],

                ':piso' =>
                    (int) $piso,

                ':asiento' =>
                    $asiento
            ]);


            $id_asignacion_destino =
                (int) $pdo->lastInsertId();


            if ($id_asignacion_destino <= 0) {

                throw new RuntimeException(
                    'No fue posible crear la nueva asignación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Notebook vuelve a Asignado
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
                    $id_notebook
            ]);


            /*
            |--------------------------------------------------------------------------
            | Registrar movimiento Reasignación
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
                    :id_motivo,
                    :id_usuario_sistema,
                    :id_asignacion_origen,
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
                    $id_notebook,

                ':id_tipo_movimiento' =>
                    $id_tipo_movimiento,

                ':id_motivo' =>
                    (int) $id_motivo,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':id_asignacion_origen' =>
                    (int) $origen['id_asignacion'],

                ':id_asignacion_destino' =>
                    $id_asignacion_destino,

                ':id_estado_anterior' =>
                    (int) $notebookBloqueado[
                        'id_estado'
                    ],

                ':id_estado_nuevo' =>
                    $id_estado_asignado,

                ':observacion' =>
                    $observacion
            ]);


            /*
            |--------------------------------------------------------------------------
            | Confirmar
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
                'Location: asignaciones.php?reasignacion=ok'
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

    <title>Reasignar notebook | SIGATI</title>

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
                0 2px 8px rgba(0,0,0,0.07);
        }

        .informacion {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
            margin-bottom: 25px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .dato strong {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 12px;
        }

        .dato span {
            font-size: 14px;
            font-weight: bold;
        }

        .tba {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #e5e7eb;
            font-size: 12px;
            font-weight: bold;
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

        .grupo {
            margin-bottom: 20px;
        }

        .grupo label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        .grupo select,
        .grupo input,
        .grupo textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
        }

        .grupo textarea {
            min-height: 100px;
            resize: vertical;
        }

        .grupo select:focus,
        .grupo input:focus,
        .grupo textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 2px
                rgba(37,99,235,0.10);
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

        @media (max-width: 650px) {

            .informacion,
            .fila {
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

        <h2>Reasignar notebook</h2>

        <p>
            Asigna un notebook en TBA a un nuevo colaborador.
        </p>

    </section>


    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    No fue posible realizar la reasignación:
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

                <strong>Número de serie</strong>

                <span>
                    <?= e(
                        $notebook['numero_serie']
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Nombre equipo</strong>

                <span>
                    <?= e(
                        $notebook['nombre_equipo_actual']
                        ?? '-'
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Estado actual</strong>

                <span class="tba">
                    TBA
                </span>

            </div>


            <div class="dato">

                <strong>Colaborador anterior</strong>

                <span>
                    <?= e(
                        $asignacionAnterior[
                            'colaborador_anterior'
                        ]
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Área anterior</strong>

                <span>
                    <?= e(
                        $asignacionAnterior[
                            'area_anterior'
                        ]
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Ubicación anterior</strong>

                <span>
                    Piso
                    <?= (int) $asignacionAnterior['piso']; ?>

                    /

                    Asiento
                    <?= e(
                        $asignacionAnterior['asiento']
                    ); ?>
                </span>

            </div>

        </div>


        <div class="aviso">

            La nueva asignación conservará el nombre de equipo
            <strong>
                <?= e(
                    $notebook['nombre_equipo_actual']
                ); ?>
            </strong>
            y quedará vinculada con la asignación anterior
            para mantener la trazabilidad completa.

        </div>


        <form method="POST" action="">

            <?= csrf_field() ?>


            <div class="grupo">

                <label for="id_colaborador">
                    Nuevo colaborador
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

                        <?php if (
                            (int) $colaborador['id_colaborador']
                            ===
                            (int) $asignacionAnterior['id_colaborador']
                        ) {
                            continue;
                        } ?>

                        <option
                            value="<?=
                                (int) $colaborador[
                                    'id_colaborador'
                                ];
                            ?>"
                            <?= (
                                (string) $id_colaborador
                                ===
                                (string) $colaborador[
                                    'id_colaborador'
                                ]
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
                            value="<?=
                                (int) $area['id_area'];
                            ?>"
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
                            $numero = 1;
                            $numero <= 4;
                            $numero++
                        ): ?>

                            <option
                                value="<?= $numero; ?>"
                                <?= (
                                    (string) $piso
                                    ===
                                    (string) $numero
                                ) ? 'selected' : ''; ?>
                            >

                                Piso <?= $numero; ?>

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
                        required
                        autocomplete="off"
                    >

                </div>

            </div>


            <div class="grupo">

                <label for="id_motivo">
                    Motivo
                </label>

                <select
                    id="id_motivo"
                    name="id_motivo"
                    required
                >

                    <option value="">
                        Selecciona un motivo
                    </option>

                    <?php foreach ($motivos as $motivo): ?>

                        <option
                            value="<?=
                                (int) $motivo[
                                    'id_motivo'
                                ];
                            ?>"
                            <?= (
                                (string) $id_motivo
                                ===
                                (string) $motivo[
                                    'id_motivo'
                                ]
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $motivo[
                                    'nombre_motivo'
                                ]
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="grupo">

                <label for="observacion">
                    Observación
                </label>

                <textarea
                    id="observacion"
                    name="observacion"
                    maxlength="500"
                    required
                    placeholder="Describe brevemente la reasignación realizada."
                ><?= e($observacion); ?></textarea>

                <span class="ayuda">
                    La observación es obligatoria en las
                    reasignaciones.
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
                    Confirmar reasignación
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>