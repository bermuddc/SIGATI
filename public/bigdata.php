<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

require_login();

$nombre_completo = $_SESSION['nombre_completo'] ?? '';
$nombre_usuario = $_SESSION['nombre_usuario'] ?? '';
$rol = $_SESSION['rol'] ?? '';

function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function limpiar_bom(string $valor): string
{
    return preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $valor
    ) ?? $valor;
}

function leer_csv(string $ruta): array
{
    if (
        !is_file($ruta)
        || !is_readable($ruta)
    ) {
        return [
            'ok' => false,
            'filas' => [],
            'error' =>
                'No se pudo leer '
                . basename($ruta)
        ];
    }

    $archivo = fopen(
        $ruta,
        'rb'
    );

    if ($archivo === false) {
        return [
            'ok' => false,
            'filas' => [],
            'error' =>
                'No se pudo abrir '
                . basename($ruta)
        ];
    }

    $cabeceras =
        fgetcsv($archivo);

    if ($cabeceras === false) {

        fclose($archivo);

        return [
            'ok' => false,
            'filas' => [],
            'error' =>
                'Archivo vacío: '
                . basename($ruta)
        ];
    }

    $cabeceras = array_map(
        static function ($valor): string {

            return limpiar_bom(
                trim((string)$valor)
            );
        },
        $cabeceras
    );

    $filas = [];

    while (
        ($datos = fgetcsv($archivo))
        !== false
    ) {

        if (
            count($datos)
            !== count($cabeceras)
        ) {
            continue;
        }

        $fila = [];

        foreach (
            $cabeceras
            as $indice => $cabecera
        ) {

            $fila[$cabecera] =
                trim(
                    (string)$datos[$indice]
                );
        }

        $filas[] = $fila;
    }

    fclose($archivo);

    return [
        'ok' => true,
        'filas' => $filas,
        'error' => null
    ];
}

function leer_metricas(
    string $ruta
): array {

    $resultado =
        leer_csv($ruta);

    if (!$resultado['ok']) {

        return [
            'ok' => false,
            'datos' => [],
            'error' =>
                $resultado['error']
        ];
    }

    $datos = [];

    foreach (
        $resultado['filas']
        as $fila
    ) {

        $metrica =
            $fila['metrica'] ?? '';

        $valor =
            $fila['valor'] ?? '';

        if ($metrica !== '') {

            $datos[$metrica] =
                $valor;
        }
    }

    return [
        'ok' => true,
        'datos' => $datos,
        'error' => null
    ];
}

function numero_formateado(
    string|int|float|null $valor
): string {

    if (
        $valor === null
        || $valor === ''
        || !is_numeric((string)$valor)
    ) {
        return '-';
    }

    return number_format(
        (float)$valor,
        0,
        ',',
        '.'
    );
}

$ruta_resultados =
    realpath(
        __DIR__
        . '/../analytics/'
        . 'resultados_bigdata'
    );

$error_general = null;

$metricas = [];
$notebooks_estado = [];
$notebooks_marca = [];
$asignaciones_piso = [];
$colaboradores_tipo = [];
$movimientos_tipo = [];

if ($ruta_resultados === false) {

    $error_general =
        'No se encontró la carpeta '
        . 'de resultados de Big Data.';

} else {

    $resultado_metricas =
        leer_metricas(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'metricas_ejecucion.csv'
        );

    $resultado_estados =
        leer_csv(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'notebooks_por_estado.csv'
        );

    $resultado_marcas =
        leer_csv(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'notebooks_por_marca.csv'
        );

    $resultado_pisos =
        leer_csv(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'asignaciones_por_piso.csv'
        );

    $resultado_colaboradores =
        leer_csv(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'colaboradores_por_tipo.csv'
        );

    $resultado_movimientos =
        leer_csv(
            $ruta_resultados
            . DIRECTORY_SEPARATOR
            . 'movimientos_por_tipo.csv'
        );

    $errores = [];

    foreach (
        [
            $resultado_metricas,
            $resultado_estados,
            $resultado_marcas,
            $resultado_pisos,
            $resultado_colaboradores,
            $resultado_movimientos
        ] as $resultado
    ) {

        if (!$resultado['ok']) {

            $errores[] =
                $resultado['error'];
        }
    }

    if ($errores !== []) {

        $error_general =
            'No fue posible cargar '
            . 'todos los resultados. '
            . implode(
                ' ',
                array_unique($errores)
            );

    } else {

        $metricas =
            $resultado_metricas[
                'datos'
            ];

        $notebooks_estado =
            $resultado_estados[
                'filas'
            ];

        $notebooks_marca =
            $resultado_marcas[
                'filas'
            ];

        $asignaciones_piso =
            $resultado_pisos[
                'filas'
            ];

        $colaboradores_tipo =
            $resultado_colaboradores[
                'filas'
            ];

        $movimientos_tipo =
            $resultado_movimientos[
                'filas'
            ];
    }
}

$total_registros =
    $metricas['Total registros']
    ?? null;

$total_notebooks =
    $metricas['Notebooks']
    ?? null;

$total_colaboradores =
    $metricas['Colaboradores']
    ?? null;

$total_asignaciones =
    $metricas['Asignaciones']
    ?? null;

$total_movimientos =
    $metricas['Movimientos']
    ?? null;

$version_spark =
    $metricas['Version Spark']
    ?? '-';

$particiones =
    $metricas['Particiones']
    ?? '-';

$tiempo_total =
    $metricas[
        'Tiempo total segundos'
    ]
    ?? '-';

$fecha_ejecucion =
    $metricas['Fecha ejecucion']
    ?? '-';

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,
        initial-scale=1.0"
    >

    <title>
        SIGATI - Analítica Big Data
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background-color:
                #f4f6f8;

            color:
                #1f2937;
        }

        .barra-superior {
            background-color:
                #1f2937;

            color:
                #ffffff;

            padding:
                18px 30px;

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;

            flex-wrap:
                wrap;
        }

        .marca h1 {
            font-size:
                24px;
        }

        .usuario {
            text-align:
                right;

            font-size:
                14px;
        }

        .usuario strong {
            display:
                block;

            margin-bottom:
                4px;
        }

        .usuario a {
            display:
                inline-block;

            margin-top:
                6px;

            color:
                #ffffff;

            font-weight:
                bold;

            text-decoration:
                none;
        }

        .contenedor {
            width:
                100%;

            max-width:
                1250px;

            margin:
                40px auto;

            padding:
                0 20px 50px;
        }

        .cabecera-modulo,
        .panel,
        .indicador {

            background-color:
                #ffffff;

            border-radius:
                10px;

            box-shadow:
                0 4px 16px
                rgba(
                    0,
                    0,
                    0,
                    0.08
                );
        }

        .cabecera-modulo {
            padding:
                30px;

            margin-bottom:
                25px;
        }

        .cabecera-modulo h2 {
            margin-bottom:
                10px;
        }

        .cabecera-modulo p {
            color:
                #6b7280;

            line-height:
                1.6;
        }

        .aviso {
            margin-top:
                18px;

            padding:
                14px 16px;

            border-radius:
                8px;

            background-color:
                #eff6ff;

            border:
                1px solid
                #bfdbfe;

            color:
                #1e40af;

            line-height:
                1.5;

            font-size:
                14px;
        }

        .mensaje-error {
            padding:
                14px 18px;

            margin-bottom:
                25px;

            border-radius:
                7px;

            background:
                #fee2e2;

            color:
                #991b1b;

            border:
                1px solid
                #fecaca;
        }

        .indicadores {
            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                18px;

            margin-bottom:
                30px;
        }

        .indicador {
            padding:
                22px;

            border-top:
                4px solid
                #2563eb;
        }

        .indicador h3 {
            color:
                #6b7280;

            font-size:
                14px;

            font-weight:
                normal;

            margin-bottom:
                10px;
        }

        .numero {
            display:
                block;

            font-size:
                30px;

            font-weight:
                bold;

            color:
                #111827;
        }

        .indicador small {
            display:
                block;

            margin-top:
                7px;

            color:
                #6b7280;
        }

        .seccion {
            margin-top:
                32px;
        }

        .titulo-seccion {
            margin-bottom:
                16px;
        }

        .titulo-seccion h2 {
            margin-bottom:
                5px;
        }

        .titulo-seccion p {
            color:
                #6b7280;

            font-size:
                14px;
        }

        .grid-paneles {
            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap:
                20px;
        }

        .panel {
            padding:
                24px;
        }

        .panel h3 {
            margin-bottom:
                8px;
        }

        .panel-descripcion {
            color:
                #6b7280;

            font-size:
                13px;

            line-height:
                1.5;

            margin-bottom:
                18px;
        }

        .fila-dato {
            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

            padding:
                10px 0;

            border-bottom:
                1px solid
                #e5e7eb;
        }

        .fila-dato:last-child {
            border-bottom:
                none;
        }

        .etiqueta {
            color:
                #374151;

            font-size:
                14px;
        }

        .valor {
            min-width:
                70px;

            padding:
                5px 9px;

            border-radius:
                20px;

            background-color:
                #e5e7eb;

            text-align:
                center;

            font-size:
                13px;

            font-weight:
                bold;
        }

        .tabla-contenedor {
            overflow-x:
                auto;
        }

        table {
            width:
                100%;

            border-collapse:
                collapse;

            min-width:
                620px;
        }

        th,
        td {
            padding:
                11px 12px;

            text-align:
                left;

            border-bottom:
                1px solid
                #e5e7eb;
        }

        th {
            background:
                #f9fafb;

            color:
                #374151;

            font-size:
                13px;
        }

        td {
            font-size:
                14px;
        }

        .acciones {
            margin-top:
                30px;

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                12px;
        }

        .boton {
            display:
                inline-block;

            padding:
                11px 17px;

            border-radius:
                6px;

            background-color:
                #1f2937;

            color:
                #ffffff;

            text-decoration:
                none;

            font-weight:
                bold;

            font-size:
                14px;
        }

        @media (
            max-width: 950px
        ) {

            .indicadores {
                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }

            .grid-paneles {
                grid-template-columns:
                    1fr;
            }
        }

        @media (
            max-width: 650px
        ) {

            .indicadores {
                grid-template-columns:
                    1fr;
            }

            .barra-superior {
                flex-direction:
                    column;

                align-items:
                    flex-start;
            }

            .usuario {
                text-align:
                    left;
            }
        }

    </style>

</head>

<body>

<header class="barra-superior">

    <div class="marca">

        <h1>SIGATI</h1>

    </div>

    <div class="usuario">

        <strong>
            <?= e(
                $nombre_completo
            ); ?>
        </strong>

        <?= e(
            $nombre_usuario
        ); ?>

        |

        <?= e($rol); ?>

        <br>

        <a href="dashboard.php">
            Volver al dashboard
        </a>

    </div>

</header>


<main class="contenedor">

    <section
        class="cabecera-modulo"
    >

        <h2>
            Analítica Big Data
        </h2>

        <p>
            Resultados analíticos
            generados mediante
            Apache Spark y PySpark
            a partir de un escenario
            académico con datos
            sintéticos de gran volumen.
        </p>

        <div class="aviso">

            Los datos presentados
            en este módulo son
            <strong>sintéticos</strong>
            y fueron generados
            exclusivamente para
            demostrar procesamiento
            masivo.

            No corresponden a
            información productiva
            ni a datos reales de
            colaboradores.

        </div>

    </section>


    <?php if (
        $error_general !== null
    ): ?>

        <div class="mensaje-error">

            <?= e(
                $error_general
            ); ?>

        </div>

    <?php else: ?>


        <section>

            <div
                class="titulo-seccion"
            >

                <h2>
                    Resumen de
                    procesamiento
                </h2>

                <p>
                    Métricas de la
                    última ejecución
                    registrada de
                    PySpark.
                </p>

            </div>


            <div
                class="indicadores"
            >

                <div
                    class="indicador"
                >

                    <h3>
                        Total procesado
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= numero_formateado(
                            $total_registros
                        ); ?>
                    </span>

                    <small>
                        registros sintéticos
                    </small>

                </div>


                <div
                    class="indicador"
                >

                    <h3>
                        Notebooks
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= numero_formateado(
                            $total_notebooks
                        ); ?>
                    </span>

                </div>


                <div
                    class="indicador"
                >

                    <h3>
                        Colaboradores
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= numero_formateado(
                            $total_colaboradores
                        ); ?>
                    </span>

                </div>


                <div
                    class="indicador"
                >

                    <h3>
                        Asignaciones
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= numero_formateado(
                            $total_asignaciones
                        ); ?>
                    </span>

                </div>


                <div
                    class="indicador"
                >

                    <h3>
                        Movimientos
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= numero_formateado(
                            $total_movimientos
                        ); ?>
                    </span>

                </div>


                <div
                    class="indicador"
                >

                    <h3>
                        Tiempo total
                    </h3>

                    <span
                        class="numero"
                    >
                        <?= e(
                            (string)
                            $tiempo_total
                        ); ?>
                    </span>

                    <small>
                        segundos
                    </small>

                </div>

            </div>

        </section>


        <section
            class="seccion"
        >

            <div
                class="titulo-seccion"
            >

                <h2>
                    Información
                    técnica de Spark
                </h2>

            </div>


            <div class="panel">

                <div
                    class="fila-dato"
                >

                    <span
                        class="etiqueta"
                    >
                        Versión de
                        Apache Spark
                    </span>

                    <span
                        class="valor"
                    >
                        <?= e(
                            (string)
                            $version_spark
                        ); ?>
                    </span>

                </div>


                <div
                    class="fila-dato"
                >

                    <span
                        class="etiqueta"
                    >
                        Particiones
                        utilizadas
                    </span>

                    <span
                        class="valor"
                    >
                        <?= e(
                            (string)
                            $particiones
                        ); ?>
                    </span>

                </div>


                <div
                    class="fila-dato"
                >

                    <span
                        class="etiqueta"
                    >
                        Última ejecución
                    </span>

                    <span
                        class="valor"
                    >
                        <?= e(
                            (string)
                            $fecha_ejecucion
                        ); ?>
                    </span>

                </div>

            </div>

        </section>


        <section
            class="seccion"
        >

            <div
                class="titulo-seccion"
            >

                <h2>
                    Resultados
                    analíticos
                </h2>

                <p>
                    Agregaciones
                    calculadas por
                    PySpark y consumidas
                    desde SIGATI Web.
                </p>

            </div>


            <div
                class="grid-paneles"
            >


                <article
                    class="panel"
                >

                    <h3>
                        Notebooks
                        por estado
                    </h3>

                    <p
                        class="
                        panel-descripcion
                        "
                    >
                        Distribución
                        de 100.000
                        notebooks.
                    </p>

                    <?php foreach (
                        $notebooks_estado
                        as $fila
                    ): ?>

                        <div
                            class="
                            fila-dato
                            "
                        >

                            <span
                                class="
                                etiqueta
                                "
                            >
                                <?= e(
                                    $fila[
                                        'estado'
                                    ]
                                    ?? ''
                                ); ?>
                            </span>

                            <span
                                class="
                                valor
                                "
                            >
                                <?= numero_formateado(
                                    $fila[
                                        'cantidad'
                                    ]
                                    ?? null
                                ); ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </article>


                <article
                    class="panel"
                >

                    <h3>
                        Notebooks
                        por marca
                    </h3>

                    <?php foreach (
                        $notebooks_marca
                        as $fila
                    ): ?>

                        <div
                            class="
                            fila-dato
                            "
                        >

                            <span
                                class="
                                etiqueta
                                "
                            >
                                <?= e(
                                    $fila[
                                        'marca'
                                    ]
                                    ?? ''
                                ); ?>
                            </span>

                            <span
                                class="
                                valor
                                "
                            >
                                <?= numero_formateado(
                                    $fila[
                                        'cantidad'
                                    ]
                                    ?? null
                                ); ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </article>


                <article
                    class="panel"
                >

                    <h3>
                        Colaboradores
                        por tipo
                    </h3>

                    <?php foreach (
                        $colaboradores_tipo
                        as $fila
                    ): ?>

                        <div
                            class="
                            fila-dato
                            "
                        >

                            <span
                                class="
                                etiqueta
                                "
                            >
                                <?= e(
                                    $fila[
                                        'tipo_colaborador'
                                    ]
                                    ?? ''
                                ); ?>
                            </span>

                            <span
                                class="
                                valor
                                "
                            >
                                <?= numero_formateado(
                                    $fila[
                                        'cantidad'
                                    ]
                                    ?? null
                                ); ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </article>


                <article
                    class="panel"
                >

                    <h3>
                        Movimientos
                        por tipo
                    </h3>

                    <?php foreach (
                        $movimientos_tipo
                        as $fila
                    ): ?>

                        <div
                            class="
                            fila-dato
                            "
                        >

                            <span
                                class="
                                etiqueta
                                "
                            >
                                <?= e(
                                    $fila[
                                        'tipo_movimiento'
                                    ]
                                    ?? ''
                                ); ?>
                            </span>

                            <span
                                class="
                                valor
                                "
                            >
                                <?= numero_formateado(
                                    $fila[
                                        'cantidad'
                                    ]
                                    ?? null
                                ); ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </article>

            </div>

        </section>


        <section
            class="seccion"
        >

            <div
                class="titulo-seccion"
            >

                <h2>
                    Asignaciones
                    sintéticas por piso
                </h2>

                <p>
                    Agregación de
                    1.500.000
                    asignaciones
                    procesadas mediante
                    PySpark.
                </p>

            </div>


            <div
                class="
                panel
                tabla-contenedor
                "
            >

                <table>

                    <thead>

                    <tr>

                        <th>
                            Piso
                        </th>

                        <th>
                            Cantidad
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $asignaciones_piso
                        as $fila
                    ): ?>

                        <tr>

                            <td>
                                Piso
                                <?= e(
                                    $fila[
                                        'piso'
                                    ]
                                    ?? ''
                                ); ?>
                            </td>

                            <td>
                                <?= numero_formateado(
                                    $fila[
                                        'cantidad'
                                    ]
                                    ?? null
                                ); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>


    <?php endif; ?>


    <div class="acciones">

        <a
            class="boton"
            href="dashboard.php"
        >
            Volver al dashboard
        </a>

        <a
            class="boton"
            href="logout.php"
        >
            Cerrar sesión
        </a>

    </div>

</main>

</body>

</html>