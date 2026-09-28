<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$mensajeBaja = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('Administrador TI');
    validate_csrf();
    $idBaja = filter_input(INPUT_POST, 'id_colaborador', FILTER_VALIDATE_INT);
    if (!$idBaja || $idBaja < 1) {
        $mensajeBaja = 'Selecciona un colaborador válido.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT activo, usuario_dominio FROM colaborador WHERE id_colaborador = :id FOR UPDATE');
            $stmt->execute([':id' => $idBaja]);
            $colaboradorBaja = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$colaboradorBaja || (int) $colaboradorBaja['activo'] !== 1) {
                $mensajeBaja = 'El colaborador no existe o ya está dado de baja.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM asignacion WHERE id_colaborador = :id AND fecha_fin IS NULL');
                $stmt->execute([':id' => $idBaja]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $mensajeBaja = 'Finaliza la asignación activa antes de dar de baja al colaborador.';
                } else {
                    $stmt = $pdo->prepare('UPDATE colaborador SET activo = 0 WHERE id_colaborador = :id AND activo = 1');
                    $stmt->execute([':id' => $idBaja]);
                }
            }
            $pdo->commit();
            if ($mensajeBaja === null) {
                header('Location: colaboradores.php?' . http_build_query([
                    'baja' => 'ok',
                    'buscar' => $colaboradorBaja['usuario_dominio'],
                    'localizar' => $idBaja,
                ]));
                exit;
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensajeBaja = 'No fue posible dar de baja al colaborador.';
        }
    }
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$localizar = filter_input(INPUT_GET, 'localizar', FILTER_VALIDATE_INT);
$confirmarBaja = filter_input(INPUT_GET, 'confirmar_baja', FILTER_VALIDATE_INT);
$colaboradorAConfirmar = null;
if ($confirmarBaja && is_admin()) {
    $stmtConfirmar = $pdo->prepare('SELECT id_colaborador, nombre_completo, rut FROM colaborador WHERE id_colaborador = :id AND activo = 1');
    $stmtConfirmar->execute([':id' => $confirmarBaja]);
    $colaboradorAConfirmar = $stmtConfirmar->fetch(PDO::FETCH_ASSOC) ?: null;
}
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 50;
$totalColaboradores = 0;
$totalPaginas = 1;
$colaboradores = [];

try {
    $condiciones = [];
    $parametros = [];

    if ($buscar !== '') {
        $condiciones[] = "(
            c.nombre_completo LIKE :buscar_nombre
            OR c.rut LIKE :buscar_rut
            OR c.cargo LIKE :buscar_cargo
            OR ar.nombre_area LIKE :buscar_area
            OR c.usuario_dominio LIKE :buscar_usuario
            OR c.correo_corporativo LIKE :buscar_correo
            OR tc.nombre_tipo LIKE :buscar_tipo
        )";

        $termino = '%' . $buscar . '%';
        $parametros = [
            ':buscar_nombre' => $termino,
            ':buscar_rut' => '%' . preg_replace('/[.\s]/u', '', strtoupper($buscar)) . '%',
            ':buscar_cargo' => $termino,
            ':buscar_area' => $termino,
            ':buscar_usuario' => $termino,
            ':buscar_correo' => $termino,
            ':buscar_tipo' => $termino,
        ];
    }

    if ($localizar && $localizar > 0) {
        $condiciones[] = 'c.id_colaborador = :localizar';
        $parametros[':localizar'] = $localizar;
    }

    $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

    $sqlConteo = "
        SELECT COUNT(*)
        FROM colaborador c
        INNER JOIN tipo_colaborador tc
            ON c.id_tipo_colaborador = tc.id_tipo_colaborador
        LEFT JOIN area ar ON c.id_area = ar.id_area
        $where
    ";

    $stmtConteo = $pdo->prepare($sqlConteo);
    $stmtConteo->execute($parametros);
    $totalColaboradores = (int) $stmtConteo->fetchColumn();

    $totalPaginas = max(1, (int) ceil($totalColaboradores / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $sql = "
        SELECT
            c.id_colaborador,
            c.nombre_completo,
            c.usuario_dominio,
            c.correo_corporativo,
            c.rut, c.cargo, c.activo, ar.nombre_area,
            tc.nombre_tipo,
            c.fecha_registro
        FROM colaborador c
        INNER JOIN tipo_colaborador tc
            ON c.id_tipo_colaborador = tc.id_tipo_colaborador
        LEFT JOIN area ar ON c.id_area = ar.id_area
        $where
        ORDER BY c.id_colaborador DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($parametros as $nombre => $valor) {
        $stmt->bindValue($nombre, $valor, $nombre === ':localizar' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'No fue posible obtener los colaboradores registrados.';
}

function url_pagina(int $numero, string $buscar): string
{
    return '?' . http_build_query([
        'buscar' => $buscar,
        'pagina' => $numero,
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboradores | SIGATI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; color: #1f2937; min-height: 100vh; }
        .topbar { background: #172033; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .topbar h1 { font-size: 22px; }
        .topbar-info { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .usuario { font-size: 14px; color: #d1d5db; }
        .rol { display: inline-block; padding: 6px 10px; border-radius: 20px; background: #fff; color: #172033; font-size: 12px; font-weight: bold; }
        .contenedor { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .encabezado { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .encabezado-texto h2 { font-size: 26px; margin-bottom: 7px; }
        .encabezado-texto p { color: #6b7280; font-size: 14px; }
        .acciones-superiores { display: flex; gap: 10px; flex-wrap: wrap; }
        .boton { display: inline-block; text-decoration: none; padding: 11px 17px; border-radius: 7px; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .boton-principal { background: #2563eb; color: #fff; }
        .boton-principal:hover { background: #1d4ed8; }
        .boton-secundario { background: #e5e7eb; color: #1f2937; }
        .boton-secundario:hover { background: #d1d5db; }
        .boton-editar { padding: 7px 12px; background: #f59e0b; color: #fff; font-size: 13px; }
        .boton-editar:hover { background: #d97706; }
        .boton-baja { padding: 7px 12px; background: #b91c1c; color: #fff; font-size: 13px; }
        .acciones-fila { display: flex; gap: 6px; align-items: center; }
        .estado-inactivo { color: #991b1b; font-weight: bold; }
        .mensaje { padding: 13px 15px; margin-bottom: 20px; border-radius: 7px; font-size: 14px; }
        .mensaje-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .mensaje-exito { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .confirmacion { background: #fff7ed; border: 1px solid #fdba74; padding: 18px; border-radius: 8px; margin-bottom: 20px; }
        .confirmacion p { margin: 10px 0; }
        .confirmacion form { display: inline-block; }
        .panel-busqueda { background: #fff; padding: 14px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 14px; }
        .form-busqueda { display: flex; gap: 9px; }
        .form-busqueda input { flex: 1; min-width: 0; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 14px; }
        .contador { margin: 0 0 15px; color: #4b5563; font-size: 14px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.07); overflow: hidden; }
        .tabla-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 950px; }
        thead { background: #eef2f7; }
        th, td { padding: 13px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; vertical-align: middle; }
        th { color: #374151; font-weight: 700; white-space: nowrap; }
        tbody tr:hover { background: #f9fafb; }
        .tipo { display: inline-block; padding: 5px 9px; border-radius: 20px; background: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: bold; white-space: nowrap; }
        .solo-lectura { display: inline-block; padding: 7px 10px; border-radius: 6px; background: #f3f4f6; color: #6b7280; font-size: 12px; font-weight: bold; }
        .sin-registros { text-align: center; padding: 40px 20px; color: #6b7280; }
        .paginacion { display: flex; justify-content: center; align-items: center; gap: 10px; padding: 18px; flex-wrap: wrap; }
        .pagina-actual { color: #4b5563; font-size: 14px; }
        .deshabilitado { opacity: .5; pointer-events: none; }
        @media (max-width: 768px) {
            .topbar { padding: 15px 20px; }
            .contenedor { margin-top: 20px; padding: 0 12px; }
            .encabezado { align-items: flex-start; flex-direction: column; }
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
            <h2>Colaboradores</h2>
            <p>Gestión de colaboradores asociados a los notebooks registrados en SIGATI.</p>
        </div>
        <div class="acciones-superiores">
            <a href="dashboard.php" class="boton boton-secundario">Volver al dashboard</a>
            <?php if (is_admin()): ?>
                <a href="colaborador_crear.php" class="boton boton-principal">+ Registrar colaborador</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (isset($_GET['registro']) && $_GET['registro'] === 'ok'): ?>
        <div class="mensaje mensaje-exito">Colaborador registrado correctamente.</div>
    <?php endif; ?>

    <?php if (isset($_GET['actualizacion']) && $_GET['actualizacion'] === 'ok'): ?>
        <div class="mensaje mensaje-exito">Colaborador actualizado correctamente.</div>
    <?php endif; ?>

    <?php if (isset($_GET['baja']) && $_GET['baja'] === 'ok'): ?>
        <div class="mensaje mensaje-exito">Colaborador dado de baja. Su historial se conserva.</div>
    <?php endif; ?>

    <?php if ($mensajeBaja !== null): ?>
        <div class="mensaje mensaje-error"><?= e($mensajeBaja); ?></div>
    <?php endif; ?>

    <?php if ($colaboradorAConfirmar !== null): ?>
        <section class="confirmacion" aria-labelledby="titulo-confirmacion">
            <h3 id="titulo-confirmacion">Confirmar baja del colaborador</h3>
            <p>Vas a dar de baja a <strong><?= e($colaboradorAConfirmar['nombre_completo']); ?></strong>
               (RUT: <?= e($colaboradorAConfirmar['rut'] ?: 'Pendiente'); ?>).
               El colaborador quedará inactivo y se conservará su historial.</p>
            <form method="post" action="colaboradores.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="id_colaborador" value="<?= (int) $colaboradorAConfirmar['id_colaborador']; ?>">
                <button type="submit" class="boton boton-baja">Confirmar baja</button>
            </form>
            <a href="<?= e('colaboradores.php?' . http_build_query(['buscar' => $buscar])); ?>" class="boton boton-secundario">Cancelar</a>
        </section>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mensaje mensaje-error"><?= e($error); ?></div>
    <?php endif; ?>

    <section class="panel-busqueda">
        <form method="get" action="colaboradores.php" class="form-busqueda">
            <input type="text" name="buscar" value="<?= e($buscar); ?>" placeholder="Buscar por nombre, RUT, cargo, área, usuario o correo">
            <button type="submit" class="boton boton-principal">Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="colaboradores.php" class="boton boton-secundario">Limpiar</a>
            <?php endif; ?>
        </form>
    </section>

    <div class="contador">
        Colaboradores encontrados: <strong><?= $totalColaboradores; ?></strong>
        <?php if ($buscar !== ''): ?>
            | Búsqueda: <strong>“<?= e($buscar); ?>”</strong>
        <?php endif; ?>
        | Página <strong><?= $pagina; ?></strong> de <strong><?= $totalPaginas; ?></strong>
    </div>

    <section class="panel">
        <?php if (count($colaboradores) > 0): ?>
            <div class="tabla-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th><th>Nombre completo</th><th>Usuario dominio</th><th>Correo corporativo</th>
                        <th>RUT</th><th>Cargo</th><th>Área</th><th>Tipo colaborador</th><th>Estado</th><th>Fecha registro</th><th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($colaboradores as $colaborador): ?>
                        <tr>
                            <td><?= (int) $colaborador['id_colaborador']; ?></td>
                            <td><?= e($colaborador['nombre_completo']); ?></td>
                            <td><?= e($colaborador['usuario_dominio']); ?></td>
                            <td><?= e($colaborador['correo_corporativo']); ?></td>
                            <td><?= e($colaborador['rut'] ?? 'Pendiente'); ?></td>
                            <td><?= e($colaborador['cargo'] ?? 'Pendiente'); ?></td>
                            <td><?= e($colaborador['nombre_area'] ?? 'Pendiente'); ?></td>
                            <td><span class="tipo"><?= e($colaborador['nombre_tipo']); ?></span></td>
                            <td class="<?= (int) $colaborador['activo'] === 1 ? '' : 'estado-inactivo'; ?>"><?= (int) $colaborador['activo'] === 1 ? 'Activo' : 'Inactivo'; ?></td>
                            <td><?= e($colaborador['fecha_registro']); ?></td>
                            <td>
                                <?php if (is_admin()): ?>
                                    <div class="acciones-fila">
                                        <a href="colaborador_editar.php?id=<?= (int) $colaborador['id_colaborador']; ?>" class="boton boton-editar">Editar</a>
                                        <?php if ((int) $colaborador['activo'] === 1): ?>
                                            <a href="colaboradores.php?confirmar_baja=<?= (int) $colaborador['id_colaborador']; ?>" class="boton boton-baja">Dar de baja</a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="solo-lectura">Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="paginacion" aria-label="Paginación de colaboradores">
                <a class="boton boton-secundario <?= $pagina <= 1 ? 'deshabilitado' : ''; ?>"
                   href="<?= e(url_pagina(max(1, $pagina - 1), $buscar)); ?>">Anterior</a>
                <span class="pagina-actual">Página <?= $pagina; ?> de <?= $totalPaginas; ?></span>
                <a class="boton boton-secundario <?= $pagina >= $totalPaginas ? 'deshabilitado' : ''; ?>"
                   href="<?= e(url_pagina(min($totalPaginas, $pagina + 1), $buscar)); ?>">Siguiente</a>
            </nav>
        <?php else: ?>
            <div class="sin-registros">
                <?= $buscar !== '' ? 'No se encontraron colaboradores para la búsqueda indicada.' : 'No existen colaboradores registrados actualmente.'; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
