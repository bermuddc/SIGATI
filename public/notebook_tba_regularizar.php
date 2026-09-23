<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_role('Administrador TI');

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

$id_notebook = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_notebook || $id_notebook <= 0) {
    header('Location: notebooks.php');
    exit;
}

$errores = [];

try {
    $consulta = $pdo->prepare("
        SELECT n.id_notebook, n.numero_serie, n.modelo,
               n.nombre_equipo_actual, e.nombre_estado,
               (SELECT COUNT(*) FROM asignacion a
                WHERE a.id_notebook = n.id_notebook) AS total_asignaciones
        FROM notebook n
        INNER JOIN estado_notebook e ON e.id_estado = n.id_estado
        WHERE n.id_notebook = :id_notebook
    ");
    $consulta->execute([':id_notebook' => $id_notebook]);
    $notebook = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notebook = false;
    $errores[] = 'No fue posible consultar el notebook.';
}

if (!$notebook && !$errores) {
    header('Location: notebooks.php');
    exit;
}

if ($notebook && (
    $notebook['nombre_estado'] !== 'TBA'
    || (int) $notebook['total_asignaciones'] !== 0
)) {
    $errores[] = 'Este equipo no cumple las condiciones para habilitar su primera asignación.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errores) {
    validate_csrf();

    try {
        $pdo->beginTransaction();

        $bloqueo = $pdo->prepare("
            SELECT n.id_estado, n.nombre_equipo_actual, e.nombre_estado
            FROM notebook n
            INNER JOIN estado_notebook e ON e.id_estado = n.id_estado
            WHERE n.id_notebook = :id_notebook
            FOR UPDATE
        ");
        $bloqueo->execute([':id_notebook' => $id_notebook]);
        $actual = $bloqueo->fetch(PDO::FETCH_ASSOC);

        if (!$actual || $actual['nombre_estado'] !== 'TBA') {
            throw new RuntimeException('El notebook ya no está en TBA. Actualiza la lista.');
        }
        if (trim((string) $actual['nombre_equipo_actual']) === '') {
            throw new RuntimeException('El notebook necesita un nombre de equipo antes de asignarse.');
        }

        $historico = $pdo->prepare('SELECT COUNT(*) FROM asignacion WHERE id_notebook = :id_notebook');
        $historico->execute([':id_notebook' => $id_notebook]);
        if ((int) $historico->fetchColumn() !== 0) {
            throw new RuntimeException('Este notebook ya tiene historial de asignaciones y debe seguir el flujo de reasignación.');
        }

        $estado = $pdo->query("SELECT id_estado FROM estado_notebook WHERE nombre_estado = 'Disponible' LIMIT 1");
        $id_disponible = (int) $estado->fetchColumn();
        $tipo = $pdo->query("SELECT id_tipo_movimiento FROM tipo_movimiento WHERE nombre_tipo = 'Regularización a Disponible' LIMIT 1");
        $id_tipo = (int) $tipo->fetchColumn();
        $id_usuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($id_disponible <= 0 || $id_tipo <= 0 || $id_usuario <= 0) {
            throw new RuntimeException('Falta configurar el tipo de movimiento o el usuario responsable.');
        }

        $actualizar = $pdo->prepare('UPDATE notebook SET id_estado = :estado WHERE id_notebook = :id_notebook');
        $actualizar->execute([':estado' => $id_disponible, ':id_notebook' => $id_notebook]);

        $movimiento = $pdo->prepare("
            INSERT INTO movimiento (
                id_notebook, id_tipo_movimiento, id_usuario_sistema,
                id_estado_anterior, id_estado_nuevo, observacion
            ) VALUES (
                :id_notebook, :tipo, :usuario, :anterior, :nuevo, :observacion
            )
        ");
        $movimiento->execute([
            ':id_notebook' => $id_notebook,
            ':tipo' => $id_tipo,
            ':usuario' => $id_usuario,
            ':anterior' => (int) $actual['id_estado'],
            ':nuevo' => $id_disponible,
            ':observacion' => 'Equipo TBA sin asignaciones anteriores habilitado para su primera asignación.'
        ]);

        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: notebooks.php?' . http_build_query([
            'regularizacion' => 'ok',
            'buscar' => $notebook['numero_serie']
        ]));
        exit;
    } catch (RuntimeException | PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errores[] = $e instanceof PDOException
            ? 'No se pudo guardar el cambio. Revisa la conexión y la configuración de la base de datos.'
            : $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Habilitar notebook TBA - SIGATI</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f5f7fa; margin: 0; }
        main { max-width: 680px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 10px; }
        .error { background: #fee2e2; padding: 1rem; border-radius: 6px; }
        .aviso { background: #e0f2fe; padding: 1rem; border-radius: 6px; line-height: 1.5; }
        button, .enlace { display: inline-block; padding: .8rem 1rem; border: 0; border-radius: 5px; background: #1f2937; color: white; text-decoration: none; cursor: pointer; }
        .enlace { background: #64748b; }
        .acciones { display: flex; gap: .7rem; margin-top: 1.5rem; flex-wrap: wrap; }
    </style>
</head>
<body>
<main>
    <h1>Habilitar notebook para su primera asignación</h1>
    <?php foreach ($errores as $error): ?>
        <p class="error" role="alert"><?= e($error); ?></p>
    <?php endforeach; ?>
    <?php if ($notebook): ?>
        <p><strong>Serie:</strong> <?= e($notebook['numero_serie']); ?></p>
        <p><strong>Modelo:</strong> <?= e($notebook['modelo']); ?></p>
        <p><strong>Estado actual:</strong> <?= e($notebook['nombre_estado']); ?></p>
        <div class="aviso">
            Este equipo está en TBA, pero nunca se asignó a un colaborador.
            Se cambiará a Disponible y se registrará el cambio en su historial.
            Después podrás crear su primera asignación desde «Nueva asignación».
        </div>
    <?php endif; ?>
    <div class="acciones">
        <?php if ($notebook && !$errores): ?>
            <form method="post">
                <?= csrf_field(); ?>
                <button type="submit">Confirmar cambio a Disponible</button>
            </form>
        <?php endif; ?>
        <a class="enlace" href="notebooks.php">Volver a Gestión de Notebooks</a>
    </div>
</main>
</body>
</html>
