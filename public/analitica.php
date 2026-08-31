<?php

require_once __DIR__ . '/../src/auth.php';

require_login();

$baseResultados = __DIR__ . '/../analytics/resultados/';

function leerCsv(string $archivo): array
{
    if (!file_exists($archivo)) {
        return [];
    }

    $filas = [];

    if (($handle = fopen($archivo, 'r')) !== false) {

        $cabeceras = fgetcsv($handle);

        if ($cabeceras === false) {
            fclose($handle);
            return [];
        }

        // Eliminar posible BOM UTF-8 de la primera cabecera
        $cabeceras[0] = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $cabeceras[0]
        );

        while (($datos = fgetcsv($handle)) !== false) {

            if (count($cabeceras) === count($datos)) {
                $filas[] = array_combine(
                    $cabeceras,
                    $datos
                );
            }
        }

        fclose($handle);
    }

    return $filas;
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

$indicadores = leerCsv(
    $baseResultados . 'indicadores_generales.csv'
);

$porEstado = leerCsv(
    $baseResultados . 'notebooks_por_estado.csv'
);

$porMarca = leerCsv(
    $baseResultados . 'notebooks_por_marca.csv'
);

$porRam = leerCsv(
    $baseResultados . 'notebooks_por_ram.csv'
);

$porDisco = leerCsv(
    $baseResultados . 'notebooks_por_disco.csv'
);

$indicadoresMapa = [];

foreach ($indicadores as $fila) {
    if (
        isset($fila['indicador']) &&
        isset($fila['valor'])
    ) {
        $indicadoresMapa[
            $fila['indicador']
        ] = $fila['valor'];
    }
}

$totalNotebooks = $indicadoresMapa[
    'Total de notebooks'
] ?? '0';

$totalRam = $indicadoresMapa[
    'Memoria RAM total GB'
] ?? '0';

$promedioRam = $indicadoresMapa[
    'Promedio memoria RAM GB'
] ?? '0';

$totalDisco = $indicadoresMapa[
    'Capacidad total almacenamiento GB'
] ?? '0';

$archivosDisponibles =
    !empty($indicadores) &&
    !empty($porEstado) &&
    !empty($porMarca);

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
        Analítica SIGATI
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .header {
            background: #1f2937;
            color: white;
            padding: 18px 30px;
        }

        .header-contenido {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #d1d5db;
        }

        .btn-volver {
            display: inline-block;
            padding: 10px 16px;
            background: white;
            color: #1f2937;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
        }

        .contenedor {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px 40px 20px;
        }

        .titulo-seccion {
            margin-bottom: 20px;
        }

        .titulo-seccion h2 {
            margin-bottom: 5px;
        }

        .titulo-seccion p {
            margin-top: 0;
            color: #6b7280;
        }

        .estado-spark {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .estado-spark strong {
            display: block;
            margin-bottom: 5px;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #991b1b;
        }

        .tarjetas {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(210px, 1fr)
                );
            gap: 18px;
            margin-bottom: 35px;
        }

        .tarjeta {
            background: white;
            padding: 22px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            box-shadow:
                0 2px 5px
                rgba(0, 0, 0, 0.05);
        }

        .tarjeta .titulo {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .tarjeta .valor {
            font-size: 30px;
            font-weight: bold;
            color: #111827;
        }

        .tarjeta .unidad {
            font-size: 14px;
            color: #6b7280;
        }

        .grid-tablas {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );
            gap: 25px;
        }

        .panel {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow:
                0 2px 5px
                rgba(0, 0, 0, 0.05);
        }

        .panel h3 {
            margin: 0;
            padding: 18px 20px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-size: 17px;
        }

        .tabla-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 13px 20px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            color: #374151;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .pie {
            margin-top: 30px;
            padding: 18px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (
            max-width: 800px
        ) {
            .grid-tablas {
                grid-template-columns: 1fr;
            }

            .header-contenido {
                align-items: flex-start;
            }

            .tarjeta .valor {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<header class="header">

    <div class="header-contenido">

        <div>
            <h1>
                SIGATI - Analítica de Activos
            </h1>

            <p>
                Resultados procesados mediante Apache Spark
            </p>
        </div>

        <a
            class="btn-volver"
            href="dashboard.php"
        >
            Volver al Dashboard
        </a>

    </div>

</header>

<main class="contenedor">

    <section class="titulo-seccion">

        <h2>
            Panel Analítico
        </h2>

        <p>
            Indicadores obtenidos a partir de los
            datos operacionales de SIGATI.
        </p>

    </section>

    <?php if ($archivosDisponibles): ?>

        <div class="estado-spark">

            <strong>
                Componente analítico disponible
            </strong>

            Los datos mostrados en este panel
            corresponden a resultados generados
            mediante procesamiento con
            Apache Spark.

        </div>

        <section class="tarjetas">

            <article class="tarjeta">

                <div class="titulo">
                    Total de notebooks
                </div>

                <div class="valor">
                    <?= escapar($totalNotebooks) ?>
                </div>

                <div class="unidad">
                    activos analizados
                </div>

            </article>

            <article class="tarjeta">

                <div class="titulo">
                    Memoria RAM total
                </div>

                <div class="valor">
                    <?= escapar($totalRam) ?>
                </div>

                <div class="unidad">
                    GB
                </div>

            </article>

            <article class="tarjeta">

                <div class="titulo">
                    Promedio de RAM
                </div>

                <div class="valor">
                    <?= escapar($promedioRam) ?>
                </div>

                <div class="unidad">
                    GB por notebook
                </div>

            </article>

            <article class="tarjeta">

                <div class="titulo">
                    Almacenamiento total
                </div>

                <div class="valor">
                    <?= escapar($totalDisco) ?>
                </div>

                <div class="unidad">
                    GB
                </div>

            </article>

        </section>

        <section class="grid-tablas">

            <article class="panel">

                <h3>
                    Notebooks por estado
                </h3>

                <div class="tabla-responsive">

                    <table>

                        <thead>
                        <tr>
                            <th>
                                Estado
                            </th>

                            <th>
                                Cantidad
                            </th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($porEstado as $fila): ?>

                            <tr>
                                <td>
                                    <?= escapar(
                                        $fila['estado'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        $fila['cantidad'] ?? ''
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </article>

            <article class="panel">

                <h3>
                    Notebooks por marca
                </h3>

                <div class="tabla-responsive">

                    <table>

                        <thead>
                        <tr>
                            <th>
                                Marca
                            </th>

                            <th>
                                Cantidad
                            </th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($porMarca as $fila): ?>

                            <tr>
                                <td>
                                    <?= escapar(
                                        $fila['marca'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        $fila['cantidad'] ?? ''
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </article>

            <article class="panel">

                <h3>
                    Distribución por memoria RAM
                </h3>

                <div class="tabla-responsive">

                    <table>

                        <thead>
                        <tr>
                            <th>
                                RAM
                            </th>

                            <th>
                                Cantidad
                            </th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($porRam as $fila): ?>

                            <tr>
                                <td>
                                    <?= escapar(
                                        $fila['ram_gb'] ?? ''
                                    ) ?>
                                    GB
                                </td>

                                <td>
                                    <?= escapar(
                                        $fila['cantidad'] ?? ''
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </article>

            <article class="panel">

                <h3>
                    Distribución por almacenamiento
                </h3>

                <div class="tabla-responsive">

                    <table>

                        <thead>
                        <tr>
                            <th>
                                Capacidad
                            </th>

                            <th>
                                Cantidad
                            </th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($porDisco as $fila): ?>

                            <tr>
                                <td>
                                    <?= escapar(
                                        $fila[
                                            'capacidad_disco_gb'
                                        ] ?? ''
                                    ) ?>
                                    GB
                                </td>

                                <td>
                                    <?= escapar(
                                        $fila['cantidad'] ?? ''
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </article>

        </section>

        <div class="pie">

            <strong>
                Flujo analítico implementado:
            </strong>

            MySQL SIGATI →
            exportación de datos →
            Apache Spark/PySpark →
            procesamiento distribuido mediante
            DataFrames →
            generación de indicadores →
            visualización web en SIGATI.

        </div>

    <?php else: ?>

        <div class="error">

            No se encontraron los resultados
            analíticos generados por Apache Spark.

            Ejecuta nuevamente
            <strong>analisis_sigati.py</strong>
            antes de abrir esta página.

        </div>

    <?php endif; ?>

</main>

</body>

</html>