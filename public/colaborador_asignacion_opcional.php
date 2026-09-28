<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';
require_role('Administrador TI');

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$idColaborador = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idColaborador || $idColaborador <= 0) {
    header('Location: colaboradores.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT c.id_colaborador, c.nombre_completo, c.rut, c.id_area, ar.nombre_area
         FROM colaborador c
         INNER JOIN area ar ON ar.id_area = c.id_area
         WHERE c.id_colaborador = :id AND c.activo = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $idColaborador]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colaborador) {
        header('Location: colaboradores.php');
        exit;
    }

    // La última asignación cerrada identifica el área a la que pertenece el TBA.
    $sql = "
        SELECT n.id_notebook, n.numero_serie, n.marca, n.modelo,
               n.nombre_equipo_actual
        FROM notebook n
        INNER JOIN estado_notebook en ON en.id_estado = n.id_estado
        INNER JOIN asignacion ultima ON ultima.id_asignacion = (
            SELECT a.id_asignacion
            FROM asignacion a
            WHERE a.id_notebook = n.id_notebook
              AND a.fecha_fin IS NOT NULL
            ORDER BY a.fecha_fin DESC, a.id_asignacion DESC
            LIMIT 1
        )
        WHERE en.nombre_estado = 'TBA'
          AND ultima.id_area = :id_area
          AND ultima.id_colaborador <> :id_colaborador
          AND n.nombre_equipo_actual IS NOT NULL
          AND n.nombre_equipo_actual <> ''
          AND NOT EXISTS (
              SELECT 1 FROM asignacion activa
              WHERE activa.id_notebook = n.id_notebook
                AND activa.fecha_fin IS NULL
          )
        ORDER BY n.modelo, n.numero_serie
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_area' => (int) $colaborador['id_area'],
        ':id_colaborador' => (int) $colaborador['id_colaborador'],
    ]);
    $notebooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare(
        "SELECT n.id_notebook, n.numero_serie, n.marca, n.modelo,
                n.tipo_equipo, n.nombre_equipo_actual
         FROM notebook n
         INNER JOIN estado_notebook en ON en.id_estado = n.id_estado
         WHERE en.nombre_estado = 'Disponible'
           AND n.nombre_equipo_actual IS NOT NULL
           AND n.nombre_equipo_actual <> ''
           AND (n.tipo_equipo <> 'Escritorio' OR :area_trading = 1)
           AND NOT EXISTS (
               SELECT 1 FROM asignacion activa
               WHERE activa.id_notebook = n.id_notebook AND activa.fecha_fin IS NULL
           )
         ORDER BY n.modelo, n.numero_serie
         LIMIT 50"
    );
    $stmt->execute([':area_trading' => strcasecmp(trim((string) $colaborador['nombre_area']), 'Trading') === 0 ? 1 : 0]);
    $disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $error = null;
} catch (PDOException $e) {
    $colaborador = null;
    $notebooks = [];
    $disponibles = [];
    $error = 'No fue posible cargar los equipos disponibles o en TBA. Vuelve a Colaboradores e intenta nuevamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asignación opcional | SIGATI</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f8; color: #1f2937; }
        main { max-width: 920px; margin: 38px auto; padding: 0 18px; }
        .panel { background: white; padding: 26px; border-radius: 10px; box-shadow: 0 3px 12px #1f293714; }
        h1 { font-size: 23px; margin: 0 0 18px; }
        h2 { font-size: 18px; margin: 26px 0 12px; }
        p { line-height: 1.55; }
        .info { background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px; border-radius: 6px; }
        .error { background: #fee2e2; padding: 12px; border-radius: 6px; }
        .equipo { border: 1px solid #dbe2eb; border-radius: 7px; margin: 9px 0; padding: 12px;
                  display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
        .detalle { color: #475569; font-size: 14px; padding-top: 5px; }
        a.boton { text-decoration: none; display: inline-block; border-radius: 6px; padding: 10px 14px;
                  background: #2563eb; color: white; font-size: 14px; font-weight: bold; }
        a.secundario { background: #475569; }
        .acciones { margin-top: 25px; }
    </style>
</head>
<body>
<main><section class="panel">
    <h1>Colaborador registrado correctamente</h1>
    <?php if ($error !== null): ?>
        <p class="error"><?= e($error); ?></p>
    <?php elseif ($colaborador): ?>
        <p><strong><?= e($colaborador['nombre_completo']); ?></strong>
           · RUT <?= e($colaborador['rut']); ?>
           · Área <?= e($colaborador['nombre_area']); ?></p>
        <p class="info">Puedes asignarle un equipo Disponible o reasignarle uno en TBA de esta misma área, o terminar
           el registro sin equipo. Si el equipo aún no llega, termina sin asignación;
           podrás asignarlo cuando esté registrado y preparado.</p>
        <h2>Equipos Disponibles</h2>
        <?php if (!$disponibles): ?>
            <p>No hay equipos Disponibles aptos en este momento.</p>
        <?php else: ?>
            <?php foreach ($disponibles as $equipo): ?>
                <div class="equipo">
                    <div><strong><?= e(trim($equipo['marca'] . ' ' . $equipo['modelo'])); ?></strong>
                        <div class="detalle">Tipo: <?= e($equipo['tipo_equipo']); ?>
                            · Serie: <?= e($equipo['numero_serie']); ?>
                            · Equipo: <?= e($equipo['nombre_equipo_actual']); ?></div>
                    </div>
                    <a class="boton" href="asignacion_crear.php?<?= e(http_build_query([
                        'colaborador' => (int) $colaborador['id_colaborador'],
                        'equipo' => (int) $equipo['id_notebook'],
                    ])); ?>">Asignar a este colaborador</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <p><a href="asignacion_crear.php?<?= e(http_build_query([
            'colaborador' => (int) $colaborador['id_colaborador'],
        ])); ?>">Buscar otro equipo Disponible por serie o modelo</a></p>
        <h2>Notebooks TBA aptos del área</h2>
        <?php if (!$notebooks): ?>
            <p>No hay notebooks TBA aptos para esta área.</p>
        <?php else: ?>
            <?php foreach ($notebooks as $equipo): ?>
                <div class="equipo">
                    <div><strong><?= e($equipo['marca'] . ' ' . $equipo['modelo']); ?></strong>
                        <div class="detalle">Serie: <?= e($equipo['numero_serie']); ?>
                            · Equipo: <?= e($equipo['nombre_equipo_actual']); ?></div>
                    </div>
                    <a class="boton" href="notebook_reasignar.php?<?= e(http_build_query([
                        'id' => (int) $equipo['id_notebook'],
                        'colaborador' => (int) $colaborador['id_colaborador'],
                    ])); ?>">Asignar a este colaborador</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    <div class="acciones"><a class="boton secundario" href="colaboradores.php?registro=ok">
        Terminar sin asignar notebook</a></div>
</section></main>
</body>
</html>
