<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function formatear_fecha(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '-';
    }

    $timestamp = strtotime($fecha);
    return $timestamp === false ? e($fecha) : date('d-m-Y H:i', $timestamp);
}

function url_pagina(int $numero, string $buscar): string
{
    return '?' . http_build_query(['buscar' => $buscar, 'pagina' => $numero]);
}

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 50;
$totalAsignaciones = 0;
$totalPaginas = 1;
$asignaciones = [];

try {
    $where = '';
    $parametros = [];

    if ($buscar !== '') {
        $where = "WHERE (
            n.numero_serie LIKE :buscar_serie
            OR a.nombre_equipo LIKE :buscar_equipo
            OR c.nombre_completo LIKE :buscar_colaborador
            OR c.usuario_dominio LIKE :buscar_usuario
            OR ar.nombre_area LIKE :buscar_area
            OR a.asiento LIKE :buscar_asiento
            OR u.nombre_completo LIKE :buscar_responsable
            OR (CASE WHEN a.fecha_fin IS NULL THEN 'Activa' ELSE 'Finalizada' END) LIKE :buscar_estado
        )";

        $termino = '%' . $buscar . '%';
        $parametros = [
            ':buscar_serie' => $termino,
            ':buscar_equipo' => $termino,
            ':buscar_colaborador' => $termino,
            ':buscar_usuario' => $termino,
            ':buscar_area' => $termino,
            ':buscar_asiento' => $termino,
            ':buscar_responsable' => $termino,
            ':buscar_estado' => $termino,
        ];
    }

    $joins = "
        FROM asignacion a
        INNER JOIN notebook n ON a.id_notebook = n.id_notebook
        INNER JOIN colaborador c ON a.id_colaborador = c.id_colaborador
        INNER JOIN area ar ON a.id_area = ar.id_area
        INNER JOIN usuario_sistema u ON a.id_usuario_sistema = u.id_usuario
    ";

    $stmtConteo = $pdo->prepare("SELECT COUNT(*) $joins $where");
    $stmtConteo->execute($parametros);
    $totalAsignaciones = (int) $stmtConteo->fetchColumn();

    $totalPaginas = max(1, (int) ceil($totalAsignaciones / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $sql = "
        SELECT
            a.id_asignacion,
            a.nombre_equipo,
            a.piso,
            a.asiento,
            a.fecha_inicio,
            a.fecha_fin,
            n.id_notebook,
            n.numero_serie,
            n.marca,
            n.modelo,
            c.id_colaborador,
            c.nombre_completo,
            c.usuario_dominio,
            ar.nombre_area,
            u.nombre_completo AS usuario_responsable
        $joins
        $where
        ORDER BY a.fecha_inicio DESC, a.id_asignacion DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($parametros as $nombre => $valor) {
        $stmt->bindValue($nombre, $valor, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'No fue posible obtener las asignaciones registradas.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignaciones | SIGATI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; color: #1f2937; min-height: 100vh; }
        .topbar { background: #172033; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .topbar h1 { font-size: 22px; }
        .topbar-info { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .usuario { font-size: 14px; color: #d1d5db; }
        .rol { display: inline-block; padding: 6px 10px; border-radius: 20px; background: #fff; color: #172033; font-size: 12px; font-weight: bold; }
        .contenedor { max-width: 1500px; margin: 30px auto; padding: 0 20px; }
        .encabezado { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .encabezado-texto h2 { font-size: 26px; margin-bottom: 7px; }
        .encabezado-texto p { color: #6b7280; font-size: 14px; }
        .acciones-superiores { display: flex; gap: 10px; flex-wrap: wrap; }
        .boton { display: inline-block; text-decoration: none; padding: 11px 17px; border-radius: 7px; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .boton-principal { background: #2563eb; color: #fff; }
        .boton-principal:hover { background: #1d4ed8; }
        .boton-secundario { background: #e5e7eb; color: #1f2937; }
        .boton-secundario:hover { background: #d1d5db; }
        .mensaje { padding: 13px 15px; margin-bottom: 20px; border-radius: 7px; font-size: 14px; }
        .mensaje-exito { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .mensaje-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .panel-busqueda { background: #fff; padding: 14px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 14px; }
        .form-busqueda { display: flex; gap: 9px; }
        .form-busqueda input { flex: 1; min-width: 0; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 14px; }
        .contador { margin-bottom: 15px; color: #4b5563; font-size: 14px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.07); overflow: hidden; }
        .tabla-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1350px; }
        thead { background: #eef2f7; }
        th, td { padding: 13px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 13px; vertical-align: middle; }
        th { color: #374151; font-weight: 700; white-space: nowrap; }
        tbody tr:hover { background: #f9fafb; }
        .serial { font-weight: bold; color: #1d4ed8; }
        .estado-activa, .estado-finalizada { display: inline-block; padding: 5px 9px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .estado-activa { background: #dcfce7; color: #166534; }
        .estado-finalizada { background: #e5e7eb; color: #4b5563; }
        .sin-registros { text-align: center; padding: 45px 20px; color: #6b7280; }
        .paginacion { display: flex; justify-content: center; align-items: center; gap: 10px; padding: 18px; flex-wrap: wrap; }
        .pagina-actual { color: #4b5563; font-size: 14px; }
        .deshabilitado { opacity: .5; pointer-events: none; }
        @media (max-width: 768px) {
            .topbar { padding: 15px 20px; }
            .contenedor { margin-top: 20px; padding: 0 12px; }
            .encabezado { flex-direction: column; align-items: flex-start; }
            .acciones-superiores { width: 100%; }
            .acciones-superiores .boton { flex: 1; text-align: center; }
            .form-busqueda { flex-direction: column; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <h1>SIGATI</h1>
    <div class="topbar-info">
        <span class="usuario"><?= e($_SESSION['nombre_completo'] ?? $_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
        <span class="rol"><?= e($_SESSION['rol'] ?? 'Sin rol'); ?></span>
    </div>
</header>

<main class="contenedor">
    <section class="encabezado">
        <div class="encabezado-texto">
            <h2>Asignaciones</h2>
            <p>Historial de notebooks asignados a colaboradores registrados en SIGATI.</p>
        </div>
        <div class="acciones-superiores">
            <a href="dashboard.php" class="boton boton-secundario">Volver al dashboard</a>
            <?php if (is_admin()): ?>
                <a href="asignacion_crear.php" class="boton boton-principal">+ Nueva asignación</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (isset($_GET['registro']) && $_GET['registro'] === 'ok'): ?>
        <div class="mensaje mensaje-exito">Asignación registrada correctamente.</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="mensaje mensaje-error"><?= e($error); ?></div>
    <?php endif; ?>

    <section class="panel-busqueda">
        <form method="get" action="asignaciones.php" class="form-busqueda">
            <input type="text" name="buscar" value="<?= e($buscar); ?>"
                   placeholder="Buscar por serie, equipo, colaborador, usuario, área, asiento, responsable o estado">
            <button type="submit" class="boton boton-principal">Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="asignaciones.php" class="boton boton-secundario">Limpiar</a>
            <?php endif; ?>
        </form>
    </section>

    <div class="contador">
        Asignaciones encontradas: <strong><?= $totalAsignaciones; ?></strong>
        <?php if ($buscar !== ''): ?>| Búsqueda: <strong>“<?= e($buscar); ?>”</strong><?php endif; ?>
        | Página <strong><?= $pagina; ?></strong> de <strong><?= $totalPaginas; ?></strong>
    </div>

    <section class="panel">
        <?php if (count($asignaciones) > 0): ?>
            <div class="tabla-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th><th>Notebook</th><th>Serie</th><th>Nombre equipo</th><th>Colaborador</th>
                        <th>Usuario dominio</th><th>Área</th><th>Piso</th><th>Asiento</th><th>Inicio</th>
                        <th>Fin</th><th>Estado</th><th>Registrado por</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($asignaciones as $asignacion): ?>
                        <tr>
                            <td><?= (int) $asignacion['id_asignacion']; ?></td>
                            <td><?= e($asignacion['marca'] . ' ' . $asignacion['modelo']); ?></td>
                            <td class="serial"><?= e($asignacion['numero_serie']); ?></td>
                            <td><?= e($asignacion['nombre_equipo']); ?></td>
                            <td><?= e($asignacion['nombre_completo']); ?></td>
                            <td><?= e($asignacion['usuario_dominio']); ?></td>
                            <td><?= e($asignacion['nombre_area']); ?></td>
                            <td><?= (int) $asignacion['piso']; ?></td>
                            <td><?= e($asignacion['asiento']); ?></td>
                            <td><?= formatear_fecha($asignacion['fecha_inicio']); ?></td>
                            <td><?= formatear_fecha($asignacion['fecha_fin']); ?></td>
                            <td>
                                <?php if ($asignacion['fecha_fin'] === null): ?>
                                    <span class="estado-activa">Activa</span>
                                <?php else: ?>
                                    <span class="estado-finalizada">Finalizada</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($asignacion['usuario_responsable']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="paginacion" aria-label="Paginación de asignaciones">
                <a class="boton boton-secundario <?= $pagina <= 1 ? 'deshabilitado' : ''; ?>"
                   href="<?= e(url_pagina(max(1, $pagina - 1), $buscar)); ?>">Anterior</a>
                <span class="pagina-actual">Página <?= $pagina; ?> de <?= $totalPaginas; ?></span>
                <a class="boton boton-secundario <?= $pagina >= $totalPaginas ? 'deshabilitado' : ''; ?>"
                   href="<?= e(url_pagina(min($totalPaginas, $pagina + 1), $buscar)); ?>">Siguiente</a>
            </nav>
        <?php else: ?>
            <div class="sin-registros">
                <?= $buscar !== '' ? 'No se encontraron asignaciones para la búsqueda indicada.' : 'No existen asignaciones registradas actualmente.'; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
