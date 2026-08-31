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


/*
|--------------------------------------------------------------------------
| Obtener movimientos
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            m.id_movimiento,
            m.fecha_movimiento,
            m.observacion,
            m.anulado,
            m.fecha_anulacion,
            m.motivo_anulacion,

            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            n.nombre_equipo_actual,

            tm.nombre_tipo AS tipo_movimiento,

            mm.nombre_motivo AS motivo_movimiento,

            ea.nombre_estado AS estado_anterior,
            en.nombre_estado AS estado_nuevo,

            us.nombre_completo AS usuario_responsable,

            uan.nombre_completo AS usuario_anulacion,

            ao.id_asignacion AS asignacion_origen_id,
            ad.id_asignacion AS asignacion_destino_id,

            co.nombre_completo AS colaborador_origen,
            cd.nombre_completo AS colaborador_destino

        FROM movimiento m

        INNER JOIN notebook n
            ON m.id_notebook = n.id_notebook

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

        LEFT JOIN asignacion ad
            ON m.id_asignacion_destino = ad.id_asignacion

        LEFT JOIN colaborador cd
            ON ad.id_colaborador = cd.id_colaborador

        ORDER BY
            m.fecha_movimiento DESC,
            m.id_movimiento DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $movimientos = [];

    $error =
        'No fue posible obtener el historial de movimientos.';
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

    <title>Movimientos | SIGATI</title>

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
            max-width: 1700px;

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

        .encabezado-texto h2 {
            font-size: 26px;
            margin-bottom: 7px;
        }

        .encabezado-texto p {
            color: #6b7280;
            font-size: 14px;
        }

        .acciones-superiores {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .boton {
            display: inline-block;

            padding: 11px 17px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 14px;
            font-weight: bold;

            cursor: pointer;
        }

        .boton-secundario {
            background: #e5e7eb;
            color: #1f2937;
        }

        .boton-secundario:hover {
            background: #d1d5db;
        }

        .boton-hoja {
            background: #2563eb;
            color: #ffffff;
        }

        .boton-hoja:hover {
            background: #1d4ed8;
        }

        .mensaje {
            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 7px;

            font-size: 14px;
        }

        .mensaje-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .contador {
            margin-bottom: 15px;

            color: #4b5563;

            font-size: 14px;
        }

        .panel {
            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.07);

            overflow: hidden;
        }

        .tabla-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;

            min-width: 1750px;

            border-collapse: collapse;
        }

        thead {
            background: #eef2f7;
        }

        th,
        td {
            padding: 12px 13px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 13px;

            vertical-align: middle;
        }

        th {
            color: #374151;

            font-weight: bold;

            white-space: nowrap;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .serial {
            font-weight: bold;
            color: #1d4ed8;
        }

        .tipo {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #e0e7ff;

            color: #3730a3;

            font-size: 12px;
            font-weight: bold;

            white-space: nowrap;
        }

        .estado {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #f3f4f6;

            color: #374151;

            font-size: 12px;

            white-space: nowrap;
        }

        .vigente {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #dcfce7;

            color: #166534;

            font-size: 12px;
            font-weight: bold;
        }

        .anulado {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #fee2e2;

            color: #991b1b;

            font-size: 12px;
            font-weight: bold;
        }

        .observacion {
            max-width: 300px;

            white-space: normal;

            line-height: 1.4;
        }

        .sin-registros {
            padding: 45px 20px;

            text-align: center;

            color: #6b7280;
        }

        @media (max-width: 768px) {

            .topbar {
                padding: 15px 20px;
            }

            .contenedor {
                margin-top: 20px;
                padding: 0 12px;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }

            .acciones-superiores {
                width: 100%;
            }

            .acciones-superiores .boton {
                flex: 1;
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

        <div class="encabezado-texto">

            <h2>Movimientos</h2>

            <p>
                Registro histórico de cambios asociados al ciclo de vida de los notebooks.
            </p>

        </div>

        <div class="acciones-superiores">

            <a
                href="dashboard.php"
                class="boton boton-secundario"
            >
                Volver al dashboard
            </a>

            <a
                href="hoja_vida.php"
                class="boton boton-hoja"
            >
                Hoja de Vida Digital
            </a>

        </div>

    </section>


    <?php if (isset($error)): ?>

        <div class="mensaje mensaje-error">
            <?= e($error); ?>
        </div>

    <?php endif; ?>


    <div class="contador">

        Total de movimientos registrados:

        <strong>
            <?= count($movimientos); ?>
        </strong>

    </div>


    <section class="panel">

        <?php if (count($movimientos) > 0): ?>

            <div class="tabla-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Serie</th>
                            <th>Equipo</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Estado anterior</th>
                            <th>Estado nuevo</th>
                            <th>Colaborador origen</th>
                            <th>Colaborador destino</th>
                            <th>Responsable</th>
                            <th>Observación</th>
                            <th>Estado registro</th>
                            <th>Anulado por</th>
                            <th>Motivo anulación</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($movimientos as $movimiento): ?>

                        <tr>

                            <td>
                                <?= (int) $movimiento['id_movimiento']; ?>
                            </td>


                            <td>
                                <?= formatear_fecha(
                                    $movimiento['fecha_movimiento']
                                ); ?>
                            </td>


                            <td class="serial">

                                <?= e(
                                    $movimiento['numero_serie']
                                ); ?>

                            </td>


                            <td>

                                <?= e(
                                    (
                                        $movimiento['nombre_equipo_actual']
                                        ?? '-'
                                    )
                                ); ?>

                            </td>


                            <td>

                                <span class="tipo">

                                    <?= e(
                                        $movimiento['tipo_movimiento']
                                    ); ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $movimiento['motivo_movimiento']
                                    ?? '-'
                                ); ?>

                            </td>


                            <td>

                                <span class="estado">

                                    <?= e(
                                        $movimiento['estado_anterior']
                                        ?? '-'
                                    ); ?>

                                </span>

                            </td>


                            <td>

                                <span class="estado">

                                    <?= e(
                                        $movimiento['estado_nuevo']
                                    ); ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $movimiento['colaborador_origen']
                                    ?? '-'
                                ); ?>

                            </td>


                            <td>

                                <?= e(
                                    $movimiento['colaborador_destino']
                                    ?? '-'
                                ); ?>

                            </td>


                            <td>

                                <?= e(
                                    $movimiento['usuario_responsable']
                                ); ?>

                            </td>


                            <td class="observacion">

                                <?= e(
                                    $movimiento['observacion']
                                    ?? '-'
                                ); ?>

                            </td>


                            <td>

                                <?php if (
                                    (int) $movimiento['anulado'] === 1
                                ): ?>

                                    <span class="anulado">
                                        Anulado
                                    </span>

                                <?php else: ?>

                                    <span class="vigente">
                                        Vigente
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    $movimiento['usuario_anulacion']
                                    ?? '-'
                                ); ?>

                            </td>


                            <td class="observacion">

                                <?= e(
                                    $movimiento['motivo_anulacion']
                                    ?? '-'
                                ); ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="sin-registros">

                No existen movimientos registrados actualmente.

            </div>

        <?php endif; ?>

    </section>

</main>

</body>

</html>