<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$nombre_completo =
    $_SESSION['nombre_completo']
    ?? '';

$nombre_usuario =
    $_SESSION['nombre_usuario']
    ?? '';

$rol =
    $_SESSION['rol']
    ?? '';


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
| Indicadores generales
|--------------------------------------------------------------------------
|
| Los indicadores se calculan directamente desde la base de datos.
|
| 1. Total de notebooks
| 2. Notebooks asignados
| 3. Notebooks disponibles
| 4. Notebooks en preparación
| 5. Asignaciones activas
| 6. Antigüedad promedio de los notebooks con fecha conocida
|
*/

$total_notebooks = 0;
$total_asignados = 0;
$total_disponibles = 0;
$total_preparacion = 0;
$total_asignaciones_activas = 0;
$antiguedad_promedio = null;

$estados = [];
$pisos = [
    1 => 0,
    2 => 0,
    3 => 0,
    4 => 0
];

$antiguedad = [
    'Menos de 2 años' => 0,
    'Entre 2 y 4 años' => 0,
    '5 años o más' => 0,
    'Sin fecha registrada' => 0
];

$error_indicadores = null;

try {

    /*
    |--------------------------------------------------------------------------
    | Indicadores generales de notebooks
    |--------------------------------------------------------------------------
    */

    $sqlIndicadores = "
        SELECT
            COUNT(n.id_notebook) AS total_notebooks,

            SUM(
                CASE
                    WHEN e.nombre_estado = 'Asignado'
                    THEN 1
                    ELSE 0
                END
            ) AS total_asignados,

            SUM(
                CASE
                    WHEN e.nombre_estado = 'Disponible'
                    THEN 1
                    ELSE 0
                END
            ) AS total_disponibles,

            SUM(
                CASE
                    WHEN e.nombre_estado = 'En preparación'
                    THEN 1
                    ELSE 0
                END
            ) AS total_preparacion

        FROM notebook n

        INNER JOIN estado_notebook e
            ON n.id_estado = e.id_estado
    ";

    $stmtIndicadores =
        $pdo->prepare($sqlIndicadores);

    $stmtIndicadores->execute();

    $indicadores =
        $stmtIndicadores->fetch(PDO::FETCH_ASSOC);

    if ($indicadores) {

        $total_notebooks =
            (int)($indicadores['total_notebooks'] ?? 0);

        $total_asignados =
            (int)($indicadores['total_asignados'] ?? 0);

        $total_disponibles =
            (int)($indicadores['total_disponibles'] ?? 0);

        $total_preparacion =
            (int)($indicadores['total_preparacion'] ?? 0);
    }


    /*
    |--------------------------------------------------------------------------
    | Asignaciones activas
    |--------------------------------------------------------------------------
    |
    | Una asignación se considera activa cuando fecha_fin es NULL.
    |
    */

    $stmtAsignacionesActivas = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM asignacion
        WHERE fecha_fin IS NULL
    ");

    $stmtAsignacionesActivas->execute();

    $resultadoAsignaciones =
        $stmtAsignacionesActivas->fetch(PDO::FETCH_ASSOC);

    if ($resultadoAsignaciones) {

        $total_asignaciones_activas =
            (int)($resultadoAsignaciones['total'] ?? 0);
    }


    /*
    |--------------------------------------------------------------------------
    | Antigüedad promedio
    |--------------------------------------------------------------------------
    |
    | Solo se consideran notebooks que poseen fecha de adquisición.
    | No se utiliza fecha_registro porque esa fecha corresponde al momento
    | en que el equipo fue incorporado a SIGATI, no a su antigüedad física.
    |
    */

    $stmtAntiguedadPromedio = $pdo->prepare("
        SELECT
            AVG(
                TIMESTAMPDIFF(
                    YEAR,
                    fecha_adquisicion,
                    CURDATE()
                )
            ) AS promedio
        FROM notebook
        WHERE fecha_adquisicion IS NOT NULL
    ");

    $stmtAntiguedadPromedio->execute();

    $resultadoPromedio =
        $stmtAntiguedadPromedio->fetch(PDO::FETCH_ASSOC);

    if (
        $resultadoPromedio &&
        $resultadoPromedio['promedio'] !== null
    ) {

        $antiguedad_promedio =
            round(
                (float)$resultadoPromedio['promedio'],
                1
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Distribución de notebooks por estado
    |--------------------------------------------------------------------------
    */

    $stmtEstados = $pdo->prepare("
        SELECT
            e.nombre_estado,
            COUNT(n.id_notebook) AS total
        FROM estado_notebook e
        LEFT JOIN notebook n
            ON n.id_estado = e.id_estado
        GROUP BY
            e.id_estado,
            e.nombre_estado
        ORDER BY
            e.id_estado
    ");

    $stmtEstados->execute();

    $estados =
        $stmtEstados->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Distribución de asignaciones activas por piso
    |--------------------------------------------------------------------------
    |
    | Solo se consideran asignaciones actualmente vigentes.
    |
    */

    $stmtPisos = $pdo->prepare("
        SELECT
            piso,
            COUNT(*) AS total
        FROM asignacion
        WHERE fecha_fin IS NULL
        GROUP BY piso
        ORDER BY piso
    ");

    $stmtPisos->execute();

    $resultadoPisos =
        $stmtPisos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultadoPisos as $fila) {

        $piso = (int)$fila['piso'];

        if (array_key_exists($piso, $pisos)) {

            $pisos[$piso] =
                (int)$fila['total'];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Distribución por antigüedad
    |--------------------------------------------------------------------------
    |
    | Se utilizan tres rangos de antigüedad más un grupo para aquellos
    | notebooks históricos cuya fecha de adquisición aún no está disponible.
    |
    */

    $stmtAntiguedad = $pdo->prepare("
        SELECT

            SUM(
                CASE
                    WHEN fecha_adquisicion IS NOT NULL
                     AND TIMESTAMPDIFF(
                            YEAR,
                            fecha_adquisicion,
                            CURDATE()
                         ) < 2
                    THEN 1
                    ELSE 0
                END
            ) AS menos_2,

            SUM(
                CASE
                    WHEN fecha_adquisicion IS NOT NULL
                     AND TIMESTAMPDIFF(
                            YEAR,
                            fecha_adquisicion,
                            CURDATE()
                         ) BETWEEN 2 AND 4
                    THEN 1
                    ELSE 0
                END
            ) AS entre_2_4,

            SUM(
                CASE
                    WHEN fecha_adquisicion IS NOT NULL
                     AND TIMESTAMPDIFF(
                            YEAR,
                            fecha_adquisicion,
                            CURDATE()
                         ) >= 5
                    THEN 1
                    ELSE 0
                END
            ) AS cinco_mas,

            SUM(
                CASE
                    WHEN fecha_adquisicion IS NULL
                    THEN 1
                    ELSE 0
                END
            ) AS sin_fecha

        FROM notebook
    ");

    $stmtAntiguedad->execute();

    $resultadoAntiguedad =
        $stmtAntiguedad->fetch(PDO::FETCH_ASSOC);

    if ($resultadoAntiguedad) {

        $antiguedad['Menos de 2 años'] =
            (int)($resultadoAntiguedad['menos_2'] ?? 0);

        $antiguedad['Entre 2 y 4 años'] =
            (int)($resultadoAntiguedad['entre_2_4'] ?? 0);

        $antiguedad['5 años o más'] =
            (int)($resultadoAntiguedad['cinco_mas'] ?? 0);

        $antiguedad['Sin fecha registrada'] =
            (int)($resultadoAntiguedad['sin_fecha'] ?? 0);
    }

} catch (PDOException $e) {

    $error_indicadores =
        'No fue posible cargar los indicadores del sistema.';
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

    <title>SIGATI - Panel principal</title>

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

        .marca h1 {
            font-size: 24px;
        }

        .usuario {
            text-align: right;
            font-size: 14px;
        }

        .usuario strong {
            display: block;
            margin-bottom: 4px;
        }

        .usuario a {
            display: inline-block;
            margin-top: 6px;
            color: #ffffff;
            font-weight: bold;
            text-decoration: none;
        }

        .usuario a:hover {
            text-decoration: underline;
        }

        .contenedor {
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .bienvenida {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;

            box-shadow:
                0 4px 16px
                rgba(0, 0, 0, 0.08);

            margin-bottom: 30px;
        }

        .bienvenida h2 {
            margin-bottom: 12px;
        }

        .bienvenida p {
            color: #6b7280;
            line-height: 1.6;
        }

        .perfil {
            margin-top: 15px;
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background-color: #e5e7eb;
            font-size: 14px;
            font-weight: bold;
        }

        .titulo-seccion {
            margin-bottom: 18px;
        }

        .titulo-seccion h2 {
            margin-bottom: 5px;
        }

        .titulo-seccion p {
            color: #6b7280;
            font-size: 14px;
        }

        .indicadores {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 35px;
        }

        .indicador {
            background-color: #ffffff;
            padding: 24px;
            border-radius: 10px;

            box-shadow:
                0 4px 16px
                rgba(0, 0, 0, 0.08);

            border-top: 4px solid #1f2937;
        }

        .indicador h3 {
            color: #6b7280;
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 12px;
        }

        .indicador .numero {
            display: block;
            font-size: 34px;
            font-weight: bold;
            color: #111827;
        }

        .indicador-total {
            border-top-color: #2563eb;
        }

        .indicador-asignado {
            border-top-color: #16a34a;
        }

        .indicador-disponible {
            border-top-color: #059669;
        }

        .indicador-preparacion {
            border-top-color: #d97706;
        }

        .indicador-asignaciones {
            border-top-color: #7c3aed;
        }

        .indicador-antiguedad {
            border-top-color: #dc2626;
        }

        .mensaje-error {
            padding: 14px 18px;
            margin-bottom: 25px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .paneles-gestion {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .panel-gestion {
            background-color: #ffffff;
            padding: 24px;
            border-radius: 10px;

            box-shadow:
                0 4px 16px
                rgba(0, 0, 0, 0.08);
        }

        .panel-gestion h3 {
            margin-bottom: 7px;
        }

        .panel-descripcion {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .fila-dato {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 15px;

            padding: 11px 0;

            border-bottom:
                1px solid #e5e7eb;
        }

        .fila-dato:last-child {
            border-bottom: none;
        }

        .fila-dato .etiqueta {
            color: #374151;
            font-size: 14px;
        }

        .fila-dato .valor {
            min-width: 38px;

            padding: 5px 9px;

            border-radius: 20px;

            background-color: #e5e7eb;

            text-align: center;

            font-size: 13px;
            font-weight: bold;
        }

        .tarjetas {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 20px;
        }

        .tarjeta {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 10px;

            box-shadow:
                0 4px 16px
                rgba(0, 0, 0, 0.08);
        }

        .tarjeta h3 {
            margin-bottom: 10px;
        }

        .tarjeta p {
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .accion {
            display: inline-block;
            padding: 9px 14px;
            background-color: #1f2937;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .accion:hover {
            background-color: #111827;
        }

        .acciones-inferiores {
            margin-top: 30px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .boton-inferior {
            display: inline-block;
            padding: 12px 18px;
            background-color: #1f2937;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .boton-inferior:hover {
            background-color: #111827;
        }

        @media (max-width: 1000px) {

            .paneles-gestion {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {

            .indicadores {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {

            .tarjetas {
                grid-template-columns: 1fr;
            }

            .barra-superior {
                flex-direction: column;
                align-items: flex-start;
            }

            .usuario {
                text-align: left;
            }
        }

        @media (max-width: 550px) {

            .indicadores {
                grid-template-columns: 1fr;
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
            <?= e($nombre_completo); ?>
        </strong>

        <?= e($nombre_usuario); ?>

        |

        <?= e($rol); ?>

        <br>

        <a href="mi_cuenta.php">
            Mi cuenta
        </a>

    </div>

</header>


<main class="contenedor">

    <section class="bienvenida">

        <h2>
            Bienvenido, <?= e($nombre_completo); ?>
        </h2>

        <p>
            Has iniciado sesión correctamente en el
            Sistema de Gestión y Trazabilidad de
            Activos Tecnológicos.
        </p>

        <span class="perfil">
            Perfil: <?= e($rol); ?>
        </span>

    </section>


    <section>

        <div class="titulo-seccion">

            <h2>
                Indicadores de gestión
            </h2>

            <p>
                Resumen calculado a partir de la información
                registrada actualmente en SIGATI.
            </p>

        </div>


        <?php if ($error_indicadores !== null): ?>

            <div class="mensaje-error">

                <?= e($error_indicadores); ?>

            </div>

        <?php endif; ?>


        <div class="indicadores">

            <div class="indicador indicador-total">

                <h3>
                    Total de notebooks
                </h3>

                <span class="numero">
                    <?= $total_notebooks; ?>
                </span>

            </div>


            <div class="indicador indicador-asignado">

                <h3>
                    Notebooks asignados
                </h3>

                <span class="numero">
                    <?= $total_asignados; ?>
                </span>

            </div>


            <div class="indicador indicador-disponible">

                <h3>
                    Notebooks disponibles
                </h3>

                <span class="numero">
                    <?= $total_disponibles; ?>
                </span>

            </div>


            <div class="indicador indicador-preparacion">

                <h3>
                    Notebooks en preparación
                </h3>

                <span class="numero">
                    <?= $total_preparacion; ?>
                </span>

            </div>


            <div class="indicador indicador-asignaciones">

                <h3>
                    Asignaciones activas
                </h3>

                <span class="numero">
                    <?= $total_asignaciones_activas; ?>
                </span>

            </div>


            <div class="indicador indicador-antiguedad">

                <h3>
                    Antigüedad promedio
                </h3>

                <span class="numero">

                    <?php if ($antiguedad_promedio !== null): ?>

                        <?= number_format(
                            $antiguedad_promedio,
                            1,
                            ',',
                            '.'
                        ); ?>

                        <small
                            style="
                                font-size: 16px;
                                font-weight: normal;
                            "
                        >
                            años
                        </small>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </span>

            </div>

        </div>

    </section>


    <section>

        <div class="titulo-seccion">

            <h2>
                Distribución de activos
            </h2>

            <p>
                Indicadores de gestión por estado, ubicación
                y antigüedad.
            </p>

        </div>


        <div class="paneles-gestion">


            <!-- ESTADOS -->

            <article class="panel-gestion">

                <h3>
                    Notebooks por estado
                </h3>

                <p class="panel-descripcion">
                    Cantidad de equipos registrada en cada
                    etapa del ciclo de vida.
                </p>

                <?php foreach ($estados as $estado): ?>

                    <div class="fila-dato">

                        <span class="etiqueta">
                            <?= e(
                                (string)$estado[
                                    'nombre_estado'
                                ]
                            ); ?>
                        </span>

                        <span class="valor">
                            <?= (int)$estado['total']; ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </article>


            <!-- PISOS -->

            <article class="panel-gestion">

                <h3>
                    Asignaciones activas por piso
                </h3>

                <p class="panel-descripcion">
                    Distribución actual de notebooks asignados
                    entre los cuatro pisos considerados
                    por SIGATI.
                </p>

                <?php foreach ($pisos as $piso => $total): ?>

                    <div class="fila-dato">

                        <span class="etiqueta">
                            Piso <?= $piso; ?>
                        </span>

                        <span class="valor">
                            <?= $total; ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </article>


            <!-- ANTIGÜEDAD -->

            <article class="panel-gestion">

                <h3>
                    Notebooks por antigüedad
                </h3>

                <p class="panel-descripcion">
                    Clasificación calculada utilizando la
                    fecha de adquisición de cada equipo.
                </p>

                <?php foreach (
                    $antiguedad as $rango => $total
                ): ?>

                    <div class="fila-dato">

                        <span class="etiqueta">
                            <?= e($rango); ?>
                        </span>

                        <span class="valor">
                            <?= $total; ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </article>

        </div>

    </section>


    <section>

        <div class="titulo-seccion">

            <h2>
                Módulos del sistema
            </h2>

            <p>
                Acceso a las principales funcionalidades de SIGATI.
            </p>

        </div>


        <div class="tarjetas">


            <!-- NOTEBOOKS -->

            <div class="tarjeta">

                <h3>Notebooks</h3>

                <?php if (is_admin()): ?>

                    <p>
                        Registra, consulta y administra
                        los equipos tecnológicos.
                    </p>

                    <a
                        class="accion"
                        href="notebooks.php"
                    >
                        Gestionar notebooks
                    </a>

                <?php else: ?>

                    <p>
                        Consulta los equipos tecnológicos
                        registrados en SIGATI.
                    </p>

                    <a
                        class="accion"
                        href="notebooks.php"
                    >
                        Consultar notebooks
                    </a>

                <?php endif; ?>

            </div>


            <!-- COLABORADORES -->

            <div class="tarjeta">

                <h3>Colaboradores</h3>

                <?php if (is_admin()): ?>

                    <p>
                        Registra, consulta y administra
                        los colaboradores.
                    </p>

                    <a
                        class="accion"
                        href="colaboradores.php"
                    >
                        Gestionar colaboradores
                    </a>

                <?php else: ?>

                    <p>
                        Consulta la información de los
                        colaboradores registrados.
                    </p>

                    <a
                        class="accion"
                        href="colaboradores.php"
                    >
                        Consultar colaboradores
                    </a>

                <?php endif; ?>

            </div>


            <!-- ASIGNACIONES -->

            <div class="tarjeta">

                <h3>Asignaciones</h3>

                <?php if (is_admin()): ?>

                    <p>
                        Asigna notebooks disponibles a
                        colaboradores y consulta el
                        historial de asignaciones.
                    </p>

                    <a
                        class="accion"
                        href="asignaciones.php"
                    >
                        Gestionar asignaciones
                    </a>

                <?php else: ?>

                    <p>
                        Consulta el historial de
                        asignaciones de notebooks.
                    </p>

                    <a
                        class="accion"
                        href="asignaciones.php"
                    >
                        Consultar asignaciones
                    </a>

                <?php endif; ?>

            </div>


            <!-- MOVIMIENTOS -->

            <div class="tarjeta">

                <h3>Movimientos</h3>

                <p>
                    Consulta la trazabilidad,
                    estados y movimientos históricos
                    de los notebooks.
                </p>

                <a
                    class="accion"
                    href="movimientos.php"
                >
                    Consultar movimientos
                </a>

            </div>

        </div>

    </section>


    <div class="acciones-inferiores">

        <a
            class="boton-inferior"
            href="mi_cuenta.php"
        >
            Mi cuenta
        </a>

        <a
            class="boton-inferior"
            href="logout.php"
        >
            Cerrar sesión
        </a>

    </div>

</main>

</body>

</html>