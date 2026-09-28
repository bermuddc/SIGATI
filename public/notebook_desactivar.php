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
            n.tipo_equipo,
            n.marca,
            n.modelo,
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
| Solo se puede desactivar desde Asignado o TBA
|--------------------------------------------------------------------------
*/

if (
    $notebook['nombre_estado'] !== 'Asignado'
    &&
    $notebook['nombre_estado'] !== 'TBA'
) {
    header('Location: notebooks.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Obtener asignación activa si existe
|--------------------------------------------------------------------------
*/

try {

    $sqlAsignacion = "
        SELECT
            a.id_asignacion,
            a.piso,
            a.asiento,
            a.nombre_equipo,

            c.nombre_completo AS colaborador,
            c.usuario_dominio,

            ar.nombre_area

        FROM asignacion a

        INNER JOIN colaborador c
            ON a.id_colaborador = c.id_colaborador

        INNER JOIN area ar
            ON a.id_area = ar.id_area

        WHERE a.id_notebook = :id_notebook
          AND a.fecha_fin IS NULL

        ORDER BY a.id_asignacion DESC

        LIMIT 1
    ";

    $stmtAsignacion =
        $pdo->prepare($sqlAsignacion);

    $stmtAsignacion->execute([
        ':id_notebook' => $id_notebook
    ]);

    $asignacionActiva =
        $stmtAsignacion->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $asignacionActiva = false;
}


/* Motivos válidos para la desactivación del equipo. */
try {
    $stmtMotivos = $pdo->query("
        SELECT id_motivo, nombre_motivo
        FROM motivo_movimiento
        WHERE nombre_motivo IN ('Falla', 'Antigüedad', 'Renovación')
        ORDER BY FIELD(nombre_motivo, 'Falla', 'Antigüedad', 'Renovación')
    ");
    $motivos = $stmtMotivos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $motivos = [];
}

$errores = [];
$id_motivo = '';
$observacion = '';


/*
|--------------------------------------------------------------------------
| Procesar desactivación
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validar CSRF
    |--------------------------------------------------------------------------
    */

    validate_csrf();


    $id_motivo = is_string($_POST['id_motivo'] ?? null)
        ? trim($_POST['id_motivo'])
        : '';

    $observacion = trim(
        $_POST['observacion'] ?? ''
    );

    if ($id_motivo === '' || !ctype_digit($id_motivo)) {
        $errores[] = 'Debes seleccionar el motivo de desactivación.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validar observación
    |--------------------------------------------------------------------------
    */

    if (mb_strlen($observacion) > 500) {

        $errores[] =
            'La observación no puede superar los 500 caracteres.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validar usuario responsable
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
    | Transacción
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
                $notebookBloqueado['nombre_estado'] !== 'Asignado'
                &&
                $notebookBloqueado['nombre_estado'] !== 'TBA'
            ) {

                throw new RuntimeException(
                    'El notebook ya no se encuentra en un estado válido para desactivación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Buscar asignación activa
            |--------------------------------------------------------------------------
            */

            $sqlActiva = "
                SELECT id_asignacion
                FROM asignacion
                WHERE id_notebook = :id_notebook
                  AND fecha_fin IS NULL
                ORDER BY id_asignacion DESC
                LIMIT 1
                FOR UPDATE
            ";

            $stmtActiva =
                $pdo->prepare($sqlActiva);

            $stmtActiva->execute([
                ':id_notebook' => $id_notebook
            ]);

            $id_asignacion_origen =
                (int) (
                    $stmtActiva->fetchColumn()
                    ?: 0
                );


            /*
            |--------------------------------------------------------------------------
            | Si está Asignado debe existir asignación activa
            |--------------------------------------------------------------------------
            */

            if (
                $notebookBloqueado['nombre_estado'] === 'Asignado'
                &&
                $id_asignacion_origen <= 0
            ) {

                throw new RuntimeException(
                    'El notebook figura como Asignado, pero no posee una asignación activa.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Si está TBA no debe existir asignación activa
            |--------------------------------------------------------------------------
            */

            if (
                $notebookBloqueado['nombre_estado'] === 'TBA'
                &&
                $id_asignacion_origen > 0
            ) {

                throw new RuntimeException(
                    'El notebook está en TBA pero todavía posee una asignación activa.'
                );
            }


            /* Validar el motivo también en el servidor. */
            $stmtMotivo = $pdo->prepare("
                SELECT COUNT(*)
                FROM motivo_movimiento
                WHERE id_motivo = :id_motivo
                  AND nombre_motivo IN ('Falla', 'Antigüedad', 'Renovación')
            ");
            $stmtMotivo->execute([':id_motivo' => (int)$id_motivo]);
            if ((int)$stmtMotivo->fetchColumn() !== 1) {
                throw new RuntimeException('El motivo seleccionado no es válido para desactivación.');
            }

            /*
            |--------------------------------------------------------------------------
            | Obtener estado Desactivado
            |--------------------------------------------------------------------------
            */

            $sqlEstado = "
                SELECT id_estado
                FROM estado_notebook
                WHERE nombre_estado = 'Desactivado'
                LIMIT 1
            ";

            $stmtEstado =
                $pdo->prepare($sqlEstado);

            $stmtEstado->execute();

            $id_estado_desactivado =
                (int) $stmtEstado->fetchColumn();


            if ($id_estado_desactivado <= 0) {

                throw new RuntimeException(
                    'No se encontró el estado Desactivado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener tipo de movimiento Desactivación
            |--------------------------------------------------------------------------
            */

            $sqlTipo = "
                SELECT id_tipo_movimiento
                FROM tipo_movimiento
                WHERE nombre_tipo = 'Desactivación'
                LIMIT 1
            ";

            $stmtTipo =
                $pdo->prepare($sqlTipo);

            $stmtTipo->execute();

            $id_tipo_movimiento =
                (int) $stmtTipo->fetchColumn();


            if ($id_tipo_movimiento <= 0) {

                throw new RuntimeException(
                    'No se encontró el tipo de movimiento Desactivación.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Cerrar asignación activa si existe
            |--------------------------------------------------------------------------
            */

            if ($id_asignacion_origen > 0) {

                $sqlCerrar = "
                    UPDATE asignacion
                    SET fecha_fin = CURRENT_TIMESTAMP
                    WHERE id_asignacion = :id_asignacion
                      AND fecha_fin IS NULL
                ";

                $stmtCerrar =
                    $pdo->prepare($sqlCerrar);

                $stmtCerrar->execute([
                    ':id_asignacion' =>
                        $id_asignacion_origen
                ]);


                if ($stmtCerrar->rowCount() !== 1) {

                    throw new RuntimeException(
                        'No fue posible cerrar la asignación activa.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Desactivar notebook
            |--------------------------------------------------------------------------
            |
            | Al registrar la desactivación, el nombre actual deja de estar
            | vigente en SIGATI. El nombre histórico permanece en
            | asignacion.nombre_equipo. Este archivo no modifica AD.
            |--------------------------------------------------------------------------
            */

            $sqlDesactivar = "
                UPDATE notebook
                SET
                    id_estado = :id_estado,
                    nombre_equipo_actual = NULL
                WHERE id_notebook = :id_notebook
            ";

            $stmtDesactivar =
                $pdo->prepare($sqlDesactivar);

            $stmtDesactivar->execute([
                ':id_estado' =>
                    $id_estado_desactivado,

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
                    :id_asignacion_origen,
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
                    (int)$id_motivo,

                ':id_usuario_sistema' =>
                    $id_usuario_sistema,

                ':id_asignacion_origen' =>
                    $id_asignacion_origen > 0
                        ? $id_asignacion_origen
                        : null,

                ':id_estado_anterior' =>
                    (int) $notebookBloqueado['id_estado'],

                ':id_estado_nuevo' =>
                    $id_estado_desactivado,

                ':observacion' =>
                    $observacion !== ''
                        ? $observacion
                        : 'Desactivación del equipo registrada en SIGATI.'
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


            $filtro_equipo = http_build_query([
                'desactivacion' => 'ok',
                'buscar' => $notebook['numero_serie']
            ]);

            header('Location: notebooks.php?' . $filtro_equipo);

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

    <title>Registrar desactivación | SIGATI</title>

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
            border:
                1px solid #e5e7eb;
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
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: bold;
        }

        .aviso {
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
            border:
                1px solid #fecaca;
            line-height: 1.5;
            font-size: 14px;
        }

        .mensaje-error {
            padding: 14px 18px;
            margin-bottom: 22px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
            border:
                1px solid #fecaca;
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
            border:
                1px solid #d1d5db;
            border-radius: 6px;
            font-family:
                Arial, Helvetica, sans-serif;
            font-size: 15px;
        }

        .grupo textarea {
            min-height: 110px;
            resize: vertical;
        }

        .grupo select:focus,
        .grupo textarea:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow:
                0 0 0 2px
                rgba(220, 38, 38, 0.10);
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
            background: #dc2626;
            color: #ffffff;
        }

        .boton-principal:hover {
            background: #b91c1c;
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

        <h2>Registrar desactivación de equipo</h2>

        <p>
            Registra en SIGATI la desactivación realizada por Soporte TI
            en Active Directory y conserva el historial del equipo.
        </p>

    </section>


    <section class="panel">

        <?php if (!empty($errores)): ?>

            <div class="mensaje-error">

                <strong>
                    No fue posible desactivar el equipo:
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
                    <?= e(
                        $notebook['numero_serie']
                    ); ?>
                </span>

            </div>


            <div class="dato">

                <strong><?= e($notebook['tipo_equipo']); ?></strong>

                <span>

                    <?= e(
                        $notebook['marca']
                        . ' '
                        . $notebook['modelo']
                    ); ?>

                </span>

            </div>


            <div class="dato">

                <strong>Nombre actual</strong>

                <span>

                    <?= e(
                        $notebook['nombre_equipo_actual']
                        ?? '-'
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


            <?php if ($asignacionActiva): ?>

                <div class="dato">

                    <strong>Colaborador actual</strong>

                    <span>

                        <?= e(
                            $asignacionActiva['colaborador']
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>Área</strong>

                    <span>

                        <?= e(
                            $asignacionActiva['nombre_area']
                        ); ?>

                    </span>

                </div>


                <div class="dato">

                    <strong>Ubicación</strong>

                    <span>

                        Piso
                        <?= (int) $asignacionActiva['piso']; ?>

                        /

                        Asiento
                        <?= e(
                            $asignacionActiva['asiento']
                        ); ?>

                    </span>

                </div>

            <?php endif; ?>

        </div>


        <div class="aviso">

            <strong>Importante:</strong>

            confirma esta acción después de desactivar el equipo en
            Active Directory. SIGATI registra el cambio, pero no lo
            ejecuta en AD. Al confirmar, el equipo pasará a
            <strong>Desactivado</strong>.

            <?php if ($asignacionActiva): ?>

                La asignación activa de
                <strong>
                    <?= e(
                        $asignacionActiva['colaborador']
                    ); ?>
                </strong>
                será finalizada.

            <?php endif; ?>

            El nombre
            <strong>
                <?= e(
                    $notebook['nombre_equipo_actual']
                    ?? '-'
                ); ?>
            </strong>
            dejará de figurar como nombre actual en SIGATI y
            permanecerá en el historial de asignaciones.

        </div>


        <form method="POST" action="">

            <?= csrf_field() ?>


            <div class="grupo">

                <label for="id_motivo">Motivo *</label>

                <select id="id_motivo" name="id_motivo" required>
                    <option value="">Selecciona el motivo</option>
                    <?php foreach ($motivos as $motivo): ?>
                        <option
                            value="<?= (int)$motivo['id_motivo']; ?>"
                            <?= $id_motivo === (string)$motivo['id_motivo'] ? 'selected' : ''; ?>
                        ><?= e($motivo['nombre_motivo']); ?></option>
                    <?php endforeach; ?>
                </select>

            </div>

            <div class="grupo">

                <label for="observacion">
                    Observación
                </label>

                <textarea
                    id="observacion"
                    name="observacion"
                    maxlength="500"
                    placeholder="Ejemplo: Desactivación en AD realizada por falla."
                ><?= e($observacion); ?></textarea>

                <span class="ayuda">
                    Campo opcional. Máximo 500 caracteres.
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
                    Confirmar desactivación
                </button>

            </div>

        </form>

    </section>

</main>

</body>

</html>