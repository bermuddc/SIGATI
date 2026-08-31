<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

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


    <section class="tarjetas">


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
                    Asigna notebooks preparados a
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