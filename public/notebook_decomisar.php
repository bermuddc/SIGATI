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
| Obtener notebook
|--------------------------------------------------------------------------
*/

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

    $notebook =
        $stmtNotebook->fetch(PDO::FETCH_ASSOC);

    if (!$notebook) {
        header('Location: notebooks.php');
        exit;
    }

} catch (PDOException $e) {

    header('Location: notebooks.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Solo se puede decomisar desde Desactivado
|--------------------------------------------------------------------------
*/

if ($notebook['nombre_estado'] !== 'Desactivado') {
    header('Location: notebooks.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Motivos válidos para Decomiso
|--------------------------------------------------------------------------
*/

try {

    $sqlMotivos = "
        SELECT
            id_motivo,
            nombre_motivo
        FROM motivo_movimiento
        WHERE id_motivo IN (3, 7, 8, 9)
        ORDER BY id_motivo
    ";

    $stmtMotivos = $pdo->prepare($sqlMotivos);
    $stmtMotivos->execute();

    $motivos =
        $stmtMotivos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $motivos = [];
}


$errores = [];

$id_motivo = '';
$observacion = '';


/*
|--------------------------------------------------------------------------
| Procesar decomiso
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validar CSRF
    |--------------------------------------------------------------------------
    */

    validate_csrf();


    $id_motivo =
        trim($_POST['id_motivo'] ?? '');

    $observacion =
        trim($_POST['observacion'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validar motivo
    |--------------------------------------------------------------------------
    */

    $motivosPermitidos = [3, 7, 8, 9];

    if (
        $id_motivo === ''
        || !ctype_digit($id_motivo)
        || !in_array(
            (int) $id_motivo,
            $motivosPermitidos,
            true
        )
    ) {

        $errores[] =
            'Debes seleccionar un motivo válido para el decomiso.';
    }


    /*
    |--------------------------------------------------------------------------
    | Observación obligatoria
    |--------------------------------------------------------------------------
    */

    if ($observacion === '') {

        $errores[] =
            'Debes ingresar una observación para el decomiso.';

    } elseif (mb_strlen($observacion) > 500) {

        $errores[] =
            'La observación no puede superar los 500 caracteres.';
    }


    /*
    |--------------------------------------------------------------------------
    | Usuario responsable
    |--------------------------------------------------------------------------
    */

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
    | Ejecutar transacción
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
                !== 'Desactivado'
            ) {

                throw new RuntimeException(
                    'El notebook ya no se encuentra en estado Desactivado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verificar que no tenga asignación activa
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
                    'No es posible decomisar un notebook con asignación activa.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validar motivo nuevamente en BD
            |--------------------------------------------------------------------------
            */

            $sqlMotivo = "
                SELECT COUNT(*)
                FROM motivo_movimiento
                WHERE id_motivo = :id_motivo
                  AND id_motivo IN (3, 7, 8, 9)
            ";

            $stmtMotivo =
                $pdo->prepare($sqlMotivo);

            $stmtMotivo->execute([
                ':id_motivo' => (int) $id_motivo
            ]);


            if (
                (int) $stmtMotivo->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'El motivo seleccionado no es válido para decomiso.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener estado Decomisado
            |--------------------------------------------------------------------------
            */

            $sqlEstado = "
                SELECT id_estado
                FROM estado_notebook
                WHERE nombre_estado = 'Decomisado'
                LIMIT 1
            ";

            $stmtEstado =
                $pdo->prepare($sqlEstado);

            $stmtEstado->execute();

            $id_estado_decomisado =
                (int) $stmtEstado->fetchColumn();


            if ($id_estado_decomisado <= 0) {

                throw new RuntimeException(
                    'No se encontró el estado Decomisado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener tipo movimiento Decomiso
            |--------------------------------------------------------------------------
            */

            $sqlTipo = "
                SELECT id_tipo_movimiento
                FROM tipo_movimiento
                WHERE nombre_tipo = 'Decomiso'
                LIMIT 1
            ";

            $stmtTipo =
                $pdo->prepare($sqlTipo);

            $stmtTipo->execute();

            $id_tipo_movimiento =
                (int) $stmtTipo->fetchColumn();


            if ($id_tipo_movimiento <= 0) {

                throw new RuntimeException(
                    'No se encontró el tipo de movimiento Decomiso.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Actualizar notebook
            |--------------------------------------------------------------------------
            */

            $sqlDecomisar = "
                UPDATE notebook
                SET
                    id_estado = :id_estado,
                    nombre_equipo_actual = NULL
                WHERE id_notebook = :id_notebook
            ";

            $stmtDecomisar =
                $pdo->prepare($sqlDecomisar);

            $stmtDecomisar->execute([
                ':id_estado' =>
                    $id_estado_decomisado,

                ':id_notebook' =>
                    $id_notebook
            ]);


            /*
            |--------------------------------------------------------------------------
            | Registrar movimiento
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
                    NULL,
                    NULL,
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

                ':id_estado_anterior' =>
                    (int) $notebookBloqueado['id_estado'],

                ':id_estado_nuevo' =>
                    $id_estado_decomisado,

                ':observacion' =>
                    $observacion
            ]);


            /*
            |--------------------------------------------------------------------------
            | Confirmar transacción
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
                'Location: notebooks.php?decomiso=ok'
            );

            exit;


        } catch (
            RuntimeException | PDOException $e
        ) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errores[] =
                $e->getMessage();
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

    <title>Decomisar notebook | SIGATI</title>

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
            max-width: 850px;
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
                0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .informacion {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
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

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 12px;
            font-weight: bold;
        }

        .aviso {
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 7px;
            background: #111827;
            color: #ffffff;
            line-height: 1.5;
            font-size: 14px;
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
            min-height: 110px;
            resize: vertical;
        }

        .grupo select:focus,
        .grupo textarea:focus {
            outline: none;
            border-color: #111827;
            box-shadow:
                0 0 0 2px rgba(17, 24, 39, 0.10);
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
            background: #111827;
            color: #ffffff;
        }

        .boton-principal:hover {
            background: #000000;
        }

        .boton-secundario {
            background: #e5e7eb;
            color: #1f2937;
        }

        @media (max-width: 600px) {

            .informacion {
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

        <h2>Decomisar notebook</h2>

        <p>
            Registra la baja definitiva del activo manteniendo su historial.
        </p>

    </section>


    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    No fue posible decomisar el notebook:
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
                    <?= e($notebook['numero_serie']); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Notebook</strong>

                <span>
                    <?= e(
                        $notebook['marca']
                        . ' '
                        . $notebook['modelo']
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Estado actual</strong>

                <span class="estado">
                    <?= e(
                        $notebook['nombre_estado']
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Nombre actual</strong>

                <span>
                    <?= e(
                        $notebook['nombre_equipo_actual']
                        ?? 'Sin nombre'
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong>Procesador</strong>

                <span>
                    <?= e($notebook['procesador']); ?>
                </span>

            </div>


            <div class="dato">

                <strong>RAM / Disco</strong>

                <span>
                    <?= (int) $notebook['ram_gb']; ?> GB RAM
                    /
                    <?= (int) $notebook['capacidad_disco_gb']; ?> GB
                </span>

            </div>

        </div>


        <div class="aviso">

            <strong>Atención:</strong>

            esta operación representa la
            <strong>baja definitiva</strong>
            del notebook dentro de SIGATI.

            El equipo cambiará de
            <strong>Desactivado</strong>
            a
            <strong>Decomisado</strong>.

            El activo no será eliminado de la base de datos y
            su Hoja de Vida Digital permanecerá disponible.

        </div>


        <form method="POST" action="">

            <?= csrf_field() ?>


            <div class="grupo">

                <label for="id_motivo">
                    Motivo de decomiso
                </label>

                <select
                    id="id_motivo"
                    name="id_motivo"
                    required
                >

                    <option value="">
                        Selecciona el motivo
                    </option>

                    <?php foreach ($motivos as $motivo): ?>

                        <option
                            value="<?= (int) $motivo['id_motivo']; ?>"
                            <?= (
                                (string) $id_motivo
                                ===
                                (string) $motivo['id_motivo']
                            ) ? 'selected' : ''; ?>
                        >

                            <?= e(
                                $motivo['nombre_motivo']
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <span class="ayuda">
                    Solo se permiten Antigüedad, Daño,
                    No cumple estándar u Otro.
                </span>

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
                    placeholder="Describe la razón y antecedentes del decomiso."
                ><?= e($observacion); ?></textarea>

                <span class="ayuda">
                    Obligatoria. Máximo 500 caracteres.
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
                    Confirmar decomiso
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>