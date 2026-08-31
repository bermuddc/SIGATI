<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();


function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatear_fecha(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '-';
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return e($fecha);
    }

    return date('d-m-Y H:i', $timestamp);
}


$id_notebook = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Cargar notebooks
|--------------------------------------------------------------------------
*/

try {

    $sqlNotebooks = "
        SELECT
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            n.nombre_equipo_actual,
            e.nombre_estado
        FROM notebook n
        INNER JOIN estado_notebook e
            ON n.id_estado = e.id_estado
        ORDER BY n.numero_serie
    ";

    $stmtNotebooks = $pdo->prepare($sqlNotebooks);
    $stmtNotebooks->execute();

    $notebooks = $stmtNotebooks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notebooks = [];

    $error =
        'No fue posible cargar los notebooks registrados.';
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$notebookSeleccionado = null;
$movimientos = [];


/*
|--------------------------------------------------------------------------
| Si hay notebook seleccionado, cargar datos
|--------------------------------------------------------------------------
*/

if ($id_notebook && $id_notebook > 0) {

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
                n.fecha_registro,
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

        $notebookSeleccionado =
            $stmtNotebook->fetch(PDO::FETCH_ASSOC);


        if ($notebookSeleccionado) {

            $sqlMovimientos = "
                SELECT
                    m.id_movimiento,
                    m.fecha_movimiento,
                    m.observacion,
                    m.anulado,
                    m.fecha_anulacion,
                    m.motivo_anulacion,

                    tm.nombre_tipo AS tipo_movimiento,

                    mm.nombre_motivo AS motivo_movimiento,

                    ea.nombre_estado AS estado_anterior,

                    en.nombre_estado AS estado_nuevo,

                    us.nombre_completo AS usuario_responsable,

                    uan.nombre_completo AS usuario_anulacion,

                    ao.id_asignacion AS id_asignacion_origen,

                    ad.id_asignacion AS id_asignacion_destino,

                    co.nombre_completo AS colaborador_origen,

                    cd.nombre_completo AS colaborador_destino,

                    ao.nombre_equipo AS nombre_equipo_origen,

                    ad.nombre_equipo AS nombre_equipo_destino,

                    ao.piso AS piso_origen,

                    ad.piso AS piso_destino,

                    ao.asiento AS asiento_origen,

                    ad.asiento AS asiento_destino,

                    aro.nombre_area AS area_origen,

                    ard.nombre_area AS area_destino

                FROM movimiento m

                INNER JOIN tipo_movimiento tm
                    ON m.id_tipo_movimiento = tm.id_tipo_movimiento

                LEFT JOIN motivo_movimiento mm
                    ON m.id_motivo = mm.id_motivo

                LEFT JOIN estado_notebook ea
                    ON m.id_estado_anterior = ea.id_estado

                INNER JOIN estado_notebook en
                    ON m.id_estado_nuevo = en.id_estado

                INNER JOIN usuario_sistema us
                    ON m.id_usuario_sistema = us.id_usuario

                LEFT JOIN usuario_sistema uan
                    ON m.id_usuario_anulacion = uan.id_usuario

                LEFT JOIN asignacion ao
                    ON m.id_asignacion_origen = ao.id_asignacion

                LEFT JOIN colaborador co
                    ON ao.id_colaborador = co.id_colaborador

                LEFT JOIN area aro
                    ON ao.id_area = aro.id_area

                LEFT JOIN asignacion ad
                    ON m.id_asignacion_destino = ad.id_asignacion

                LEFT JOIN colaborador cd
                    ON ad.id_colaborador = cd.id_colaborador

                LEFT JOIN area ard
                    ON ad.id_area = ard.id_area

                WHERE m.id_notebook = :id_notebook

                ORDER BY
                    m.fecha_movimiento ASC,
                    m.id_movimiento ASC
            ";

            $stmtMovimientos = $pdo->prepare(
                $sqlMovimientos
            );

            $stmtMovimientos->execute([
                ':id_notebook' => $id_notebook
            ]);

            $movimientos =
                $stmtMovimientos->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    } catch (PDOException $e) {

        $error =
            'No fue posible obtener la Hoja de Vida Digital del notebook.';
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

    <title>Hoja de Vida Digital | SIGATI</title>

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
            max-width: 1300px;

            margin: 30px auto;

            padding: 0 20px;
        }

        .encabezado {
            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

            flex-wrap: wrap;
        }

        .encabezado h2 {
            font-size: 26px;

            margin-bottom: 7px;
        }

        .encabezado p {
            color: #6b7280;

            font-size: 14px;
        }

        .boton {
            display: inline-block;

            padding: 11px 17px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;
        }

        .boton-secundario {
            background: #e5e7eb;

            color: #1f2937;
        }

        .boton-secundario:hover {
            background: #d1d5db;
        }

        .panel {
            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.07);

            padding: 25px;

            margin-bottom: 25px;
        }

        .selector {
            display: grid;

            grid-template-columns: 1fr auto;

            gap: 12px;

            align-items: end;
        }

        .grupo label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;

            font-weight: bold;

            color: #374151;
        }

        .grupo select {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            background: #ffffff;

            color: #1f2937;

            font-size: 15px;
        }

        .grupo select:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px rgba(37, 99, 235, 0.10);
        }

        .boton-consultar {
            padding: 12px 18px;

            border: none;

            border-radius: 7px;

            background: #2563eb;

            color: #ffffff;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }

        .boton-consultar:hover {
            background: #1d4ed8;
        }

        .mensaje-error {
            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 7px;

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }

        .resumen {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }

        .dato {
            padding: 15px;

            border-radius: 8px;

            background: #f9fafb;

            border: 1px solid #e5e7eb;
        }

        .dato strong {
            display: block;

            margin-bottom: 5px;

            color: #6b7280;

            font-size: 12px;
        }

        .dato span {
            font-size: 14px;

            font-weight: bold;
        }

        .estado-actual {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #dcfce7;

            color: #166534;

            font-size: 12px;

            font-weight: bold;
        }

        .titulo-historial {
            font-size: 20px;

            margin-bottom: 20px;
        }

        .timeline {
            position: relative;

            margin-left: 15px;

            padding-left: 35px;
        }

        .timeline::before {
            content: '';

            position: absolute;

            left: 8px;

            top: 5px;

            bottom: 5px;

            width: 3px;

            background: #d1d5db;

            border-radius: 5px;
        }

        .movimiento {
            position: relative;

            margin-bottom: 28px;

            padding: 20px;

            background: #ffffff;

            border-radius: 9px;

            border: 1px solid #e5e7eb;

            box-shadow:
                0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .movimiento::before {
            content: '';

            position: absolute;

            left: -35px;

            top: 22px;

            width: 17px;

            height: 17px;

            border-radius: 50%;

            background: #2563eb;

            border: 4px solid #eff6ff;
        }

        .movimiento-anulado {
            opacity: 0.65;

            border-color: #fecaca;
        }

        .movimiento-anulado::before {
            background: #dc2626;
        }

        .movimiento-header {
            display: flex;

            justify-content: space-between;

            gap: 15px;

            align-items: center;

            margin-bottom: 13px;

            flex-wrap: wrap;
        }

        .tipo {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            background: #e0e7ff;

            color: #3730a3;

            font-size: 13px;

            font-weight: bold;
        }

        .fecha {
            color: #6b7280;

            font-size: 13px;
        }

        .estados {
            display: flex;

            align-items: center;

            gap: 8px;

            margin-bottom: 14px;

            flex-wrap: wrap;
        }

        .estado {
            padding: 5px 9px;

            border-radius: 20px;

            background: #f3f4f6;

            color: #374151;

            font-size: 12px;
        }

        .flecha {
            font-weight: bold;

            color: #6b7280;
        }

        .detalle-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 12px;

            margin-top: 15px;
        }

        .detalle {
            padding: 12px;

            background: #f9fafb;

            border-radius: 7px;
        }

        .detalle strong {
            display: block;

            margin-bottom: 4px;

            color: #6b7280;

            font-size: 12px;
        }

        .detalle span {
            font-size: 14px;
        }

        .observacion {
            margin-top: 14px;

            padding: 13px;

            background: #eff6ff;

            border-radius: 7px;

            color: #1e3a8a;

            font-size: 13px;

            line-height: 1.5;
        }

        .anulacion {
            margin-top: 14px;

            padding: 13px;

            background: #fee2e2;

            border-radius: 7px;

            color: #991b1b;

            font-size: 13px;

            line-height: 1.5;
        }

        .sin-historial {
            padding: 35px 20px;

            text-align: center;

            color: #6b7280;
        }

        @media (max-width: 850px) {

            .resumen {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .detalle-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .topbar {
                padding: 15px 20px;
            }

            .contenedor {
                margin-top: 20px;

                padding: 0 12px;
            }

            .selector {
                grid-template-columns: 1fr;
            }

            .boton-consultar {
                width: 100%;
            }

            .resumen {
                grid-template-columns: 1fr;
            }

            .timeline {
                margin-left: 5px;

                padding-left: 30px;
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

        <div>

            <h2>Hoja de Vida Digital</h2>

            <p>
                Consulta cronológica del ciclo de vida de cada notebook registrado en SIGATI.
            </p>

        </div>

        <a
            href="movimientos.php"
            class="boton boton-secundario"
        >
            Volver a movimientos
        </a>

    </section>


    <?php if (isset($error)): ?>

        <div class="mensaje-error">
            <?= e($error); ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <form
            method="GET"
            action=""
            class="selector"
        >

            <div class="grupo">

                <label for="id">
                    Seleccionar notebook
                </label>

                <select
                    name="id"
                    id="id"
                    required
                >

                    <option value="">
                        Selecciona un notebook
                    </option>

                    <?php foreach ($notebooks as $notebook): ?>

                        <option
                            value="<?= (int) $notebook['id_notebook']; ?>"
                            <?= (
                                (int) $id_notebook
                                ===
                                (int) $notebook['id_notebook']
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $notebook['numero_serie']
                                . ' | '
                                . $notebook['marca']
                                . ' '
                                . $notebook['modelo']
                                . ' | '
                                . (
                                    $notebook['nombre_equipo_actual']
                                    ?? 'Sin nombre'
                                )
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button
                type="submit"
                class="boton-consultar"
            >
                Consultar Hoja de Vida
            </button>

        </form>

    </section>


    <?php if ($notebookSeleccionado): ?>

        <section class="panel">

            <div class="resumen">

                <div class="dato">

                    <strong>
                        Número de serie
                    </strong>

                    <span>
                        <?= e(
                            $notebookSeleccionado['numero_serie']
                        ); ?>
                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Equipo
                    </strong>

                    <span>

                        <?= e(
                            $notebookSeleccionado['marca']
                            . ' '
                            . $notebookSeleccionado['modelo']
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Nombre actual
                    </strong>

                    <span>

                        <?= e(
                            $notebookSeleccionado['nombre_equipo_actual']
                            ?? '-'
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Estado actual
                    </strong>

                    <span class="estado-actual">

                        <?= e(
                            $notebookSeleccionado['nombre_estado']
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Procesador
                    </strong>

                    <span>

                        <?= e(
                            $notebookSeleccionado['procesador']
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>
                        RAM
                    </strong>

                    <span>
                        <?= (int) $notebookSeleccionado['ram_gb']; ?> GB
                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Disco
                    </strong>

                    <span>

                        <?php

                        $disco =
                            (int) $notebookSeleccionado[
                                'capacidad_disco_gb'
                            ];

                        if ($disco === 1024) {

                            echo '1 TB';

                        } elseif ($disco === 2048) {

                            echo '2 TB';

                        } else {

                            echo $disco . ' GB';
                        }

                        ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>
                        Fecha de registro
                    </strong>

                    <span>

                        <?= formatear_fecha(
                            $notebookSeleccionado['fecha_registro']
                        ); ?>

                    </span>

                </div>

            </div>

        </section>


        <section class="panel">

            <h3 class="titulo-historial">
                Historial del activo
            </h3>


            <?php if (count($movimientos) > 0): ?>

                <div class="timeline">


                    <?php foreach ($movimientos as $movimiento): ?>

                        <article
                            class="movimiento
                            <?= (
                                (int) $movimiento['anulado'] === 1
                            ) ? 'movimiento-anulado' : ''; ?>"
                        >

                            <div class="movimiento-header">

                                <span class="tipo">

                                    <?= e(
                                        $movimiento['tipo_movimiento']
                                    ); ?>

                                </span>

                                <span class="fecha">

                                    <?= formatear_fecha(
                                        $movimiento['fecha_movimiento']
                                    ); ?>

                                </span>

                            </div>


                            <div class="estados">

                                <span class="estado">

                                    <?= e(
                                        $movimiento['estado_anterior']
                                        ?? 'Sin estado anterior'
                                    ); ?>

                                </span>

                                <span class="flecha">
                                    →
                                </span>

                                <span class="estado">

                                    <?= e(
                                        $movimiento['estado_nuevo']
                                    ); ?>

                                </span>

                            </div>


                            <div class="detalle-grid">


                                <?php if (
                                    $movimiento['colaborador_origen']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Colaborador origen
                                        </strong>

                                        <span>

                                            <?= e(
                                                $movimiento['colaborador_origen']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['colaborador_destino']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Colaborador destino
                                        </strong>

                                        <span>

                                            <?= e(
                                                $movimiento['colaborador_destino']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['area_origen']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Área origen
                                        </strong>

                                        <span>

                                            <?= e(
                                                $movimiento['area_origen']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['area_destino']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Área destino
                                        </strong>

                                        <span>

                                            <?= e(
                                                $movimiento['area_destino']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['piso_origen']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Ubicación origen
                                        </strong>

                                        <span>

                                            Piso
                                            <?= (int) $movimiento['piso_origen']; ?>

                                            /
                                            Asiento

                                            <?= e(
                                                $movimiento['asiento_origen']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['piso_destino']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Ubicación destino
                                        </strong>

                                        <span>

                                            Piso
                                            <?= (int) $movimiento['piso_destino']; ?>

                                            /
                                            Asiento

                                            <?= e(
                                                $movimiento['asiento_destino']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $movimiento['motivo_movimiento']
                                    !== null
                                ): ?>

                                    <div class="detalle">

                                        <strong>
                                            Motivo
                                        </strong>

                                        <span>

                                            <?= e(
                                                $movimiento['motivo_movimiento']
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>


                                <div class="detalle">

                                    <strong>
                                        Responsable
                                    </strong>

                                    <span>

                                        <?= e(
                                            $movimiento['usuario_responsable']
                                        ); ?>

                                    </span>

                                </div>

                            </div>


                            <?php if (
                                $movimiento['observacion']
                                !== null
                                &&
                                $movimiento['observacion']
                                !== ''
                            ): ?>

                                <div class="observacion">

                                    <strong>
                                        Observación:
                                    </strong>

                                    <?= e(
                                        $movimiento['observacion']
                                    ); ?>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                (int) $movimiento['anulado'] === 1
                            ): ?>

                                <div class="anulacion">

                                    <strong>
                                        Movimiento anulado.
                                    </strong>

                                    <br>

                                    Fecha:
                                    <?= formatear_fecha(
                                        $movimiento['fecha_anulacion']
                                    ); ?>

                                    <br>

                                    Responsable:
                                    <?= e(
                                        $movimiento['usuario_anulacion']
                                        ?? '-'
                                    ); ?>

                                    <br>

                                    Motivo:
                                    <?= e(
                                        $movimiento['motivo_anulacion']
                                        ?? '-'
                                    ); ?>

                                </div>

                            <?php endif; ?>


                        </article>

                    <?php endforeach; ?>


                </div>

            <?php else: ?>

                <div class="sin-historial">

                    El notebook seleccionado todavía no posee movimientos registrados.

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

</body>

</html>