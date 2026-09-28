<?php

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role('Administrador TI');

$mensaje_error = '';

/*
 * Regla de negocio:
 * Todo equipo nuevo debe ingresar al sistema
 * con el estado "Ingresado".
 */
$stmtEstado = $pdo->prepare("
    SELECT id_estado
    FROM estado_notebook
    WHERE nombre_estado = :nombre_estado
    LIMIT 1
");

$stmtEstado->execute([
    ':nombre_estado' => 'Ingresado'
]);

$estadoIngresado = $stmtEstado->fetch();

if (!$estadoIngresado) {
    die('No se encuentra configurado el estado Ingresado.');
}

$id_estado_ingresado = (int)$estadoIngresado['id_estado'];

/*
 * Valores permitidos.
 * Se validan tanto en frontend como en backend.
 */
$ram_permitida = [8, 16, 32, 64, 128];
$discos_permitidos = [256, 512, 1024, 2048];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Seguridad CSRF:
     * Antes de procesar cualquier dato recibido por POST,
     * SIGATI comprueba que el formulario pertenece a la
     * sesión actual del usuario.
     */
    validate_csrf();

    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $tipo_equipo = trim((string) ($_POST['tipo_equipo'] ?? ''));
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram_gb = (int)($_POST['ram_gb'] ?? 0);
    $capacidad_disco_gb = (int)($_POST['capacidad_disco_gb'] ?? 0);
    $fecha_adquisicion = trim($_POST['fecha_adquisicion'] ?? '');

    if (
        $numero_serie === '' ||
        !in_array($tipo_equipo, ['Notebook', 'Escritorio'], true) ||
        $marca === '' ||
        $modelo === '' ||
        $procesador === '' ||
        $fecha_adquisicion === ''
    ) {

        $mensaje_error = 'Debes completar todos los campos obligatorios y seleccionar un tipo de equipo válido.';

    } elseif (strcasecmp($modelo, 'Dell Pro Max Tower T2') === 0 && $tipo_equipo !== 'Escritorio') {

        $mensaje_error = 'Dell Pro Max Tower T2 debe registrarse como equipo de escritorio.';

    } elseif (!in_array($ram_gb, $ram_permitida, true)) {

        $mensaje_error = 'La capacidad de RAM seleccionada no es válida.';

    } elseif (!in_array($capacidad_disco_gb, $discos_permitidos, true)) {

        $mensaje_error = 'La capacidad de disco seleccionada no es válida.';

    } else {

        /*
         * Validación de la fecha de adquisición.
         * Debe tener formato AAAA-MM-DD y no puede ser futura.
         */
        $fecha_objeto = DateTime::createFromFormat(
            'Y-m-d',
            $fecha_adquisicion
        );

        $fecha_valida =
            $fecha_objeto !== false &&
            $fecha_objeto->format('Y-m-d') === $fecha_adquisicion;

        if (!$fecha_valida) {

            $mensaje_error = 'La fecha de adquisición no es válida.';

        } elseif ($fecha_adquisicion > date('Y-m-d')) {

            $mensaje_error = 'La fecha de adquisición no puede ser futura.';

        } else {

            try {

                $id_usuario_sistema = (int) ($_SESSION['usuario_id'] ?? 0);
                if ($id_usuario_sistema <= 0) {
                    throw new RuntimeException('No se identificó al usuario responsable.');
                }

                $stmtTipo = $pdo->prepare(
                    'SELECT id_tipo_movimiento FROM tipo_movimiento WHERE nombre_tipo = :nombre LIMIT 1'
                );
                $stmtTipo->execute([':nombre' => 'Ingreso']);
                $id_tipo_ingreso = (int) $stmtTipo->fetchColumn();
                if ($id_tipo_ingreso <= 0) {
                    throw new RuntimeException('No está configurado el movimiento Ingreso.');
                }

                $pdo->beginTransaction();

                $sql = "
                    INSERT INTO notebook
                    (
                        numero_serie,
                        tipo_equipo,
                        marca,
                        modelo,
                        procesador,
                        ram_gb,
                        capacidad_disco_gb,
                        fecha_adquisicion,
                        nombre_equipo_actual,
                        id_estado
                    )
                    VALUES
                    (
                        :numero_serie,
                        :tipo_equipo,
                        :marca,
                        :modelo,
                        :procesador,
                        :ram_gb,
                        :capacidad_disco_gb,
                        :fecha_adquisicion,
                        NULL,
                        :id_estado
                    )
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':numero_serie' => $numero_serie,
                    ':tipo_equipo' => $tipo_equipo,
                    ':marca' => $marca,
                    ':modelo' => $modelo,
                    ':procesador' => $procesador,
                    ':ram_gb' => $ram_gb,
                    ':capacidad_disco_gb' => $capacidad_disco_gb,
                    ':fecha_adquisicion' => $fecha_adquisicion,
                    ':id_estado' => $id_estado_ingresado
                ]);

                $id_notebook_nuevo = (int) $pdo->lastInsertId();
                $stmtMovimiento = $pdo->prepare(
                    'INSERT INTO movimiento
                     (id_notebook, id_tipo_movimiento, id_usuario_sistema,
                      id_estado_anterior, id_estado_nuevo, observacion)
                     VALUES (:notebook, :tipo, :usuario, NULL, :estado, :observacion)'
                );
                $stmtMovimiento->execute([
                    ':notebook' => $id_notebook_nuevo,
                    ':tipo' => $id_tipo_ingreso,
                    ':usuario' => $id_usuario_sistema,
                    ':estado' => $id_estado_ingresado,
                    ':observacion' => 'Equipo ingresado al inventario mediante SIGATI.'
                ]);

                $pdo->commit();
                header('Location: notebooks.php?' . http_build_query([
                    'registro' => 'ok',
                    'buscar' => $numero_serie
                ]));
                exit;

            } catch (PDOException | RuntimeException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    $mensaje_error = 'El número de serie ya se encuentra registrado.';
                } else {
                    $mensaje_error = 'No fue posible registrar el equipo.';
                }
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

    <title>SIGATI - Registrar equipo</title>

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
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
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

        .estado-inicial {
            padding: 11px;
            background-color: #e5e7eb;
            border-radius: 6px;
            font-weight: bold;
        }

        .ayuda {
            margin-top: 6px;
            color: #6b7280;
            font-size: 13px;
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

        <h2>Registrar equipo</h2>

        <p class="descripcion">
            Ingresa los datos técnicos del nuevo equipo.
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
                    <label for="tipo_equipo">Tipo de equipo *</label>
                    <select id="tipo_equipo" name="tipo_equipo" required>
                        <option value="">Selecciona un tipo</option>
                        <?php foreach (['Notebook', 'Escritorio'] as $tipo): ?>
                            <option value="<?= $tipo ?>" <?= ($_POST['tipo_equipo'] ?? '') === $tipo ? 'selected' : '' ?>>
                                <?= $tipo ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">

                    <label for="numero_serie">
                        Número de serie *
                    </label>

                    <input
                        type="text"
                        id="numero_serie"
                        name="numero_serie"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['numero_serie'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

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
                            $_POST['marca'] ?? '',
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
                            $_POST['modelo'] ?? '',
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
                            $_POST['procesador'] ?? '',
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

                        <option value="">
                            Selecciona una capacidad
                        </option>

                        <?php foreach ($ram_permitida as $ram): ?>

                            <option
                                value="<?= $ram ?>"
                                <?= (
                                    (string)($_POST['ram_gb'] ?? '') ===
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

                        <option value="">
                            Selecciona una capacidad
                        </option>

                        <?php foreach ($discos_permitidos as $disco): ?>

                            <option
                                value="<?= $disco ?>"
                                <?= (
                                    (string)(
                                        $_POST[
                                            'capacidad_disco_gb'
                                        ] ?? ''
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

                    <label for="fecha_adquisicion">
                        Fecha de adquisición *
                    </label>

                    <input
                        type="date"
                        id="fecha_adquisicion"
                        name="fecha_adquisicion"
                        max="<?= date('Y-m-d') ?>"
                        required
                        value="<?= htmlspecialchars(
                            $_POST['fecha_adquisicion'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <span class="ayuda">
                        Fecha en que el equipo fue adquirido.
                        Se utiliza para calcular su antigüedad.
                    </span>

                </div>

                <div class="campo">

                    <label>
                        Estado inicial
                    </label>

                    <div class="estado-inicial">
                        Ingresado
                    </div>

                    <span class="ayuda">
                        El estado inicial es asignado
                        automáticamente por SIGATI.
                    </span>

                </div>

                <div class="campo">

                    <label>
                        Nombre de equipo
                    </label>

                    <div class="estado-inicial">
                        Pendiente de preparación
                    </div>

                    <span class="ayuda">
                        El nombre de equipo se asignará
                        durante la preparación.
                    </span>

                </div>

            </div>

            <div class="acciones">

                <button
                    class="boton"
                    type="submit"
                >
                    Registrar equipo
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
