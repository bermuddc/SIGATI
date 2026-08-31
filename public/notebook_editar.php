<?php

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role('Administrador TI');

$mensaje_error = '';

$ram_permitida = [8, 16, 32, 64, 128];
$discos_permitidos = [256, 512, 1024, 2048];

$id_notebook = (int)($_GET['id'] ?? 0);

if ($id_notebook <= 0) {
    die('Notebook no válido.');
}

/*
 * Obtenemos los datos actuales del notebook.
 */
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

$notebook = $stmtNotebook->fetch();

if (!$notebook) {
    die('Notebook no encontrado.');
}

/*
 * Verificamos si el notebook ya posee historial.
 */
$stmtAsignaciones = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM asignacion
    WHERE id_notebook = :id_notebook
");

$stmtAsignaciones->execute([
    ':id_notebook' => $id_notebook
]);

$total_asignaciones = (int)$stmtAsignaciones->fetch()['total'];

$stmtMovimientos = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM movimiento
    WHERE id_notebook = :id_notebook
");

$stmtMovimientos->execute([
    ':id_notebook' => $id_notebook
]);

$total_movimientos = (int)$stmtMovimientos->fetch()['total'];

/*
 * Regla de negocio:
 * El número de serie solo puede modificarse cuando:
 * 1. El notebook está en estado "Ingresado".
 * 2. No posee asignaciones.
 * 3. No posee movimientos.
 */
$puede_editar_serie =
    $notebook['nombre_estado'] === 'Ingresado' &&
    $total_asignaciones === 0 &&
    $total_movimientos === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Protección CSRF:
     * antes de aceptar cualquier modificación,
     * se valida que la solicitud provenga de un
     * formulario legítimo de la sesión actual.
     */
    validate_csrf();

    $numero_serie = trim(
        $_POST['numero_serie'] ?? $notebook['numero_serie']
    );

    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram_gb = (int)($_POST['ram_gb'] ?? 0);
    $capacidad_disco_gb = (int)(
        $_POST['capacidad_disco_gb'] ?? 0
    );

    /*
     * Si el serial ya está protegido,
     * ignoramos cualquier intento de modificación.
     */
    if (!$puede_editar_serie) {
        $numero_serie = $notebook['numero_serie'];
    }

    if (
        $numero_serie === '' ||
        $marca === '' ||
        $modelo === '' ||
        $procesador === ''
    ) {

        $mensaje_error =
            'Debes completar todos los campos obligatorios.';

    } elseif (
        !in_array($ram_gb, $ram_permitida, true)
    ) {

        $mensaje_error =
            'La capacidad de RAM seleccionada no es válida.';

    } elseif (
        !in_array(
            $capacidad_disco_gb,
            $discos_permitidos,
            true
        )
    ) {

        $mensaje_error =
            'La capacidad de disco seleccionada no es válida.';

    } else {

        try {

            /*
             * Validamos que el nuevo número de serie
             * no pertenezca a otro notebook.
             */
            if ($puede_editar_serie) {

                $stmtSerie = $pdo->prepare("
                    SELECT id_notebook
                    FROM notebook
                    WHERE numero_serie = :numero_serie
                      AND id_notebook <> :id_notebook
                    LIMIT 1
                ");

                $stmtSerie->execute([
                    ':numero_serie' => $numero_serie,
                    ':id_notebook' => $id_notebook
                ]);

                if ($stmtSerie->fetch()) {

                    $mensaje_error =
                        'El número de serie ya se encuentra ' .
                        'registrado en otro notebook.';
                }
            }

            if ($mensaje_error === '') {

                $sqlUpdate = "
                    UPDATE notebook
                    SET
                        numero_serie = :numero_serie,
                        marca = :marca,
                        modelo = :modelo,
                        procesador = :procesador,
                        ram_gb = :ram_gb,
                        capacidad_disco_gb =
                            :capacidad_disco_gb
                    WHERE id_notebook = :id_notebook
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    ':numero_serie' => $numero_serie,
                    ':marca' => $marca,
                    ':modelo' => $modelo,
                    ':procesador' => $procesador,
                    ':ram_gb' => $ram_gb,
                    ':capacidad_disco_gb' =>
                        $capacidad_disco_gb,
                    ':id_notebook' => $id_notebook
                ]);

                header(
                    'Location: notebooks.php?actualizacion=ok'
                );
                exit;
            }

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $mensaje_error =
                    'El número de serie ya se encuentra registrado.';

            } else {

                $mensaje_error =
                    'No fue posible actualizar el notebook.';
            }
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

    <title>SIGATI - Editar Notebook</title>

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
            text-align: right;
            font-size: 14px;
        }

        .contenedor {
            width: 100%;
            max-width: 900px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .formulario {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow:
                0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .formulario h2 {
            margin-bottom: 8px;
        }

        .descripcion {
            color: #6b7280;
            margin-bottom: 25px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .campo {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
            background-color: #ffffff;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #374151;
        }

        .campo-bloqueado {
            padding: 11px;
            background-color: #e5e7eb;
            border-radius: 6px;
            font-weight: bold;
        }

        .ayuda {
            margin-top: 6px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.4;
        }

        .mensaje-error {
            margin-bottom: 20px;
            padding: 12px;
            background-color: #fee2e2;
            color: #991b1b;
            border-radius: 6px;
        }

        .acciones {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .boton {
            border: none;
            padding: 11px 18px;
            border-radius: 6px;
            background-color: #1f2937;
            color: #ffffff;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .boton:hover {
            background-color: #111827;
        }

        .boton-secundario {
            background-color: #6b7280;
        }

        @media (max-width: 700px) {

            .grid {
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

    <h1>SIGATI</h1>

    <div class="usuario">

        <?= htmlspecialchars(
            $_SESSION['nombre_usuario'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        |

        <?= htmlspecialchars(
            $_SESSION['rol'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

</header>

<main class="contenedor">

    <section class="formulario">

        <h2>Editar Notebook</h2>

        <p class="descripcion">
            Modifica los datos técnicos del equipo seleccionado.
        </p>

        <?php if ($mensaje_error !== ''): ?>

            <div class="mensaje-error">

                <?= htmlspecialchars(
                    $mensaje_error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <?= csrf_field() ?>

            <div class="grid">

                <div class="campo">

                    <label for="numero_serie">
                        Número de serie
                    </label>

                    <?php if ($puede_editar_serie): ?>

                        <input
                            type="text"
                            id="numero_serie"
                            name="numero_serie"
                            maxlength="100"
                            required
                            value="<?= htmlspecialchars(
                                $_POST['numero_serie'] ??
                                $notebook['numero_serie'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <span class="ayuda">
                            Puede corregirse mientras el notebook
                            permanezca en estado Ingresado y no
                            posea historial.
                        </span>

                    <?php else: ?>

                        <div class="campo-bloqueado">

                            <?= htmlspecialchars(
                                $notebook['numero_serie'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                        <span class="ayuda">
                            El número de serie está protegido porque
                            el equipo ya posee historial o avanzó en
                            su ciclo de vida.
                        </span>

                    <?php endif; ?>

                </div>

                <div class="campo">

                    <label for="marca">
                        Marca *
                    </label>

                    <input
                        type="text"
                        id="marca"
                        name="marca"
                        maxlength="50"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['marca'] ??
                            $notebook['marca'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="modelo">
                        Modelo *
                    </label>

                    <input
                        type="text"
                        id="modelo"
                        name="modelo"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['modelo'] ??
                            $notebook['modelo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="procesador">
                        Procesador *
                    </label>

                    <input
                        type="text"
                        id="procesador"
                        name="procesador"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['procesador'] ??
                            $notebook['procesador'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="ram_gb">
                        RAM *
                    </label>

                    <select
                        id="ram_gb"
                        name="ram_gb"
                        required
                    >

                        <?php foreach (
                            $ram_permitida as $ram
                        ): ?>

                            <option
                                value="<?= $ram ?>"
                                <?= (
                                    (string)(
                                        $_POST['ram_gb'] ??
                                        $notebook['ram_gb']
                                    ) ===
                                    (string)$ram
                                ) ? 'selected' : '' ?>
                            >
                                <?= $ram ?> GB
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label for="capacidad_disco_gb">
                        Capacidad de disco *
                    </label>

                    <select
                        id="capacidad_disco_gb"
                        name="capacidad_disco_gb"
                        required
                    >

                        <?php foreach (
                            $discos_permitidos as $disco
                        ): ?>

                            <option
                                value="<?= $disco ?>"
                                <?= (
                                    (string)(
                                        $_POST[
                                            'capacidad_disco_gb'
                                        ] ??
                                        $notebook[
                                            'capacidad_disco_gb'
                                        ]
                                    ) ===
                                    (string)$disco
                                ) ? 'selected' : '' ?>
                            >

                                <?php
                                if ($disco === 1024) {
                                    echo '1 TB (1024 GB)';
                                } elseif ($disco === 2048) {
                                    echo '2 TB (2048 GB)';
                                } else {
                                    echo $disco . ' GB';
                                }
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label>
                        Estado actual
                    </label>

                    <div class="campo-bloqueado">

                        <?= htmlspecialchars(
                            $notebook['nombre_estado'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                    <span class="ayuda">
                        El estado se administra mediante el flujo
                        de movimientos.
                    </span>

                </div>

                <div class="campo">

                    <label>
                        Nombre de equipo actual
                    </label>

                    <div class="campo-bloqueado">

                        <?= htmlspecialchars(
                            $notebook[
                                'nombre_equipo_actual'
                            ] ??
                            'Pendiente de preparación',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                    <span class="ayuda">
                        El nombre se administra durante la
                        preparación o asignación.
                    </span>

                </div>

            </div>

            <div class="acciones">

                <button
                    class="boton"
                    type="submit"
                >
                    Guardar cambios
                </button>

                <a
                    class="boton boton-secundario"
                    href="notebooks.php"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

</body>

</html>