<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

try {

    $sql = "
        SELECT
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            n.procesador,
            n.ram_gb,
            n.capacidad_disco_gb,
            n.nombre_equipo_actual,
            e.nombre_estado,
            n.fecha_registro
        FROM notebook n
        INNER JOIN estado_notebook e
            ON n.id_estado = e.id_estado
        ORDER BY n.id_notebook DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $notebooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notebooks = [];
    $error = 'No fue posible obtener los notebooks registrados.';
}


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

    <title>SIGATI - Notebooks</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
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
        }

        .barra-superior h1 {
            font-size: 24px;
        }

        .usuario {
            display: flex;
            align-items: center;
            gap: 12px;

            font-size: 14px;
        }

        .rol {
            display: inline-block;

            padding: 6px 10px;

            background: #ffffff;
            color: #1f2937;

            border-radius: 20px;

            font-size: 12px;
            font-weight: bold;
        }

        .contenedor {
            width: 100%;
            max-width: 1500px;

            margin: 35px auto;

            padding: 0 20px;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            margin-bottom: 25px;
        }

        .encabezado h2 {
            margin-bottom: 6px;
        }

        .encabezado p {
            color: #6b7280;
        }

        .acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .boton {
            display: inline-block;

            padding: 10px 15px;

            border-radius: 6px;

            text-decoration: none;

            background-color: #1f2937;
            color: #ffffff;

            font-size: 14px;

            white-space: nowrap;
        }

        .boton:hover {
            background-color: #111827;
        }

        .boton-secundario {
            background-color: #6b7280;
        }

        .boton-editar {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #f59e0b;
        }

        .boton-editar:hover {
            background-color: #d97706;
        }

        .boton-preparar {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #2563eb;
        }

        .boton-preparar:hover {
            background-color: #1d4ed8;
        }

        .boton-tba {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #dc2626;
        }

        .boton-tba:hover {
            background-color: #b91c1c;
        }

        .boton-reasignar {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #7c3aed;
        }

        .boton-reasignar:hover {
            background-color: #6d28d9;
        }

        .boton-desactivar {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #475569;
        }

        .boton-desactivar:hover {
            background-color: #334155;
        }

        .boton-decomisar {
            padding: 7px 12px;

            font-size: 13px;

            background-color: #111827;
        }

        .boton-decomisar:hover {
            background-color: #000000;
        }

        .acciones-fila {
            display: flex;

            gap: 7px;

            align-items: center;

            flex-wrap: wrap;
        }

        .mensaje {
            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 7px;

            font-size: 14px;
        }

        .mensaje-exito {
            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;
        }

        .mensaje-error {
            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }

        .tabla-contenedor {
            background-color: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 4px 16px rgba(0, 0, 0, 0.08);

            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1450px;
        }

        th,
        td {
            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 14px;

            vertical-align: middle;
        }

        th {
            background-color: #f9fafb;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        .estado {
            display: inline-block;

            padding: 6px 9px;

            border-radius: 15px;

            background-color: #e5e7eb;

            font-size: 12px;
            font-weight: bold;

            white-space: nowrap;
        }

        .estado-ingresado {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .estado-preparacion {
            background-color: #fef3c7;
            color: #92400e;
        }

        .estado-asignado {
            background-color: #dcfce7;
            color: #166534;
        }

        .estado-tba {
            background-color: #e5e7eb;
            color: #374151;
        }

        .estado-desactivado {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .estado-decomisado {
            background-color: #111827;
            color: #ffffff;
        }

        .sin-registros {
            padding: 30px;

            text-align: center;

            color: #6b7280;
        }

        @media (max-width: 800px) {

            .barra-superior {
                flex-direction: column;
                align-items: flex-start;
            }

            .usuario {
                align-items: flex-start;
                flex-direction: column;
            }

            .encabezado {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

<header class="barra-superior">

    <h1>SIGATI</h1>

    <div class="usuario">

        <span>
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

            <h2>Gestión de Notebooks</h2>

            <p>
                Equipos tecnológicos registrados en SIGATI.
            </p>

        </div>

        <div class="acciones">

            <a
                class="boton boton-secundario"
                href="dashboard.php"
            >
                Volver
            </a>

            <?php if (is_admin()): ?>

                <a
                    class="boton"
                    href="notebook_crear.php"
                >
                    Registrar notebook
                </a>

            <?php endif; ?>

        </div>

    </section>


    <?php if (
        isset($_GET['preparacion'])
        && $_GET['preparacion'] === 'ok'
    ): ?>

        <div class="mensaje mensaje-exito">
            Notebook enviado a preparación correctamente.
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['tba'])
        && $_GET['tba'] === 'ok'
    ): ?>

        <div class="mensaje mensaje-exito">
            Notebook cambiado a TBA correctamente.
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['desactivacion'])
        && $_GET['desactivacion'] === 'ok'
    ): ?>

        <div class="mensaje mensaje-exito">
            Notebook desactivado correctamente.
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['decomiso'])
        && $_GET['decomiso'] === 'ok'
    ): ?>

        <div class="mensaje mensaje-exito">
            Notebook decomisado correctamente.
        </div>

    <?php endif; ?>


    <?php if (isset($error)): ?>

        <div class="mensaje mensaje-error">
            <?= e($error); ?>
        </div>

    <?php endif; ?>


    <section class="tabla-contenedor">

        <?php if (count($notebooks) > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Número de serie</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Procesador</th>
                        <th>RAM</th>
                        <th>Disco</th>
                        <th>Nombre equipo</th>
                        <th>Estado</th>
                        <th>Fecha registro</th>

                        <?php if (is_admin()): ?>

                            <th>Acciones</th>

                        <?php endif; ?>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($notebooks as $notebook): ?>

                    <tr>

                        <td>
                            <?= (int) $notebook['id_notebook']; ?>
                        </td>

                        <td>
                            <?= e($notebook['numero_serie']); ?>
                        </td>

                        <td>
                            <?= e($notebook['marca']); ?>
                        </td>

                        <td>
                            <?= e($notebook['modelo']); ?>
                        </td>

                        <td>
                            <?= e($notebook['procesador']); ?>
                        </td>

                        <td>
                            <?= (int) $notebook['ram_gb']; ?> GB
                        </td>

                        <td>

                            <?php

                            $disco =
                                (int) $notebook['capacidad_disco_gb'];

                            if ($disco === 1024) {

                                echo '1 TB';

                            } elseif ($disco === 2048) {

                                echo '2 TB';

                            } else {

                                echo e(
                                    (string) $disco
                                ) . ' GB';
                            }

                            ?>

                        </td>

                        <td>

                            <?= e(
                                $notebook['nombre_equipo_actual']
                                ?? '-'
                            ); ?>

                        </td>

                        <td>

                            <?php

                            $claseEstado = 'estado';

                            if (
                                $notebook['nombre_estado']
                                === 'Ingresado'
                            ) {

                                $claseEstado .=
                                    ' estado-ingresado';

                            } elseif (
                                $notebook['nombre_estado']
                                === 'En preparación'
                            ) {

                                $claseEstado .=
                                    ' estado-preparacion';

                            } elseif (
                                $notebook['nombre_estado']
                                === 'Asignado'
                            ) {

                                $claseEstado .=
                                    ' estado-asignado';

                            } elseif (
                                $notebook['nombre_estado']
                                === 'TBA'
                            ) {

                                $claseEstado .=
                                    ' estado-tba';

                            } elseif (
                                $notebook['nombre_estado']
                                === 'Desactivado'
                            ) {

                                $claseEstado .=
                                    ' estado-desactivado';

                            } elseif (
                                $notebook['nombre_estado']
                                === 'Decomisado'
                            ) {

                                $claseEstado .=
                                    ' estado-decomisado';
                            }

                            ?>

                            <span class="<?= e($claseEstado); ?>">

                                <?= e(
                                    $notebook['nombre_estado']
                                ); ?>

                            </span>

                        </td>

                        <td>
                            <?= e(
                                $notebook['fecha_registro']
                            ); ?>
                        </td>


                        <?php if (is_admin()): ?>

                            <td>

                                <div class="acciones-fila">

                                    <a
                                        class="boton boton-editar"
                                        href="notebook_editar.php?id=<?= urlencode(
                                            (string) $notebook['id_notebook']
                                        ); ?>"
                                    >
                                        Editar
                                    </a>


                                    <?php if (
                                        $notebook['nombre_estado']
                                        === 'Ingresado'
                                    ): ?>

                                        <a
                                            class="boton boton-preparar"
                                            href="notebook_preparar.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Preparar
                                        </a>


                                    <?php elseif (
                                        $notebook['nombre_estado']
                                        === 'Asignado'
                                    ): ?>

                                        <a
                                            class="boton boton-tba"
                                            href="notebook_tba.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Cambiar a TBA
                                        </a>

                                        <a
                                            class="boton boton-desactivar"
                                            href="notebook_desactivar.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Desactivar
                                        </a>


                                    <?php elseif (
                                        $notebook['nombre_estado']
                                        === 'TBA'
                                    ): ?>

                                        <a
                                            class="boton boton-reasignar"
                                            href="notebook_reasignar.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Reasignar
                                        </a>

                                        <a
                                            class="boton boton-desactivar"
                                            href="notebook_desactivar.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Desactivar
                                        </a>


                                    <?php elseif (
                                        $notebook['nombre_estado']
                                        === 'Desactivado'
                                    ): ?>

                                        <a
                                            class="boton boton-decomisar"
                                            href="notebook_decomisar.php?id=<?= urlencode(
                                                (string) $notebook['id_notebook']
                                            ); ?>"
                                        >
                                            Decomisar
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="sin-registros">
                No existen notebooks registrados.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>

</html>