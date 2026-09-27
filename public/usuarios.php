<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';
require_role('Administrador TI');

function h(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

$errores = [];
$roles = $pdo->query("SELECT id_rol, nombre_rol FROM rol WHERE nombre_rol IN ('Administrador TI', 'Consulta') ORDER BY id_rol")
    ->fetchAll(PDO::FETCH_ASSOC);
$rolesValidos = array_map(static fn(array $r): int => (int) $r['id_rol'], $roles);
$modo = (string) ($_GET['modo'] ?? '');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$datos = ['nombre_completo' => '', 'nombre_usuario' => '', 'correo' => '', 'id_rol' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $idPost = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT) ?: 0;

    if ($accion === 'guardar') {
        $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
        $usuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $idRol = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT) ?: 0;
        $clave = (string) ($_POST['password'] ?? '');
        $datos = ['nombre_completo' => $nombre, 'nombre_usuario' => $usuario,
            'correo' => $correo, 'id_rol' => (string) $idRol];
        $modo = $idPost > 0 ? 'editar' : 'crear';
        $id = $idPost;

        if ($nombre === '' || mb_strlen($nombre) > 150) $errores[] = 'Ingresa un nombre de hasta 150 caracteres.';
        if ($usuario === '' || mb_strlen($usuario) > 100) $errores[] = 'Ingresa un usuario de hasta 100 caracteres.';
        if ($correo !== '' && (mb_strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)))
            $errores[] = 'Ingresa un correo válido de hasta 150 caracteres.';
        if (!in_array($idRol, $rolesValidos, true)) $errores[] = 'Selecciona un rol válido.';
        if (($idPost === 0 && $clave === '') || ($clave !== '' && strlen($clave) < 10))
            $errores[] = 'La contraseña debe tener al menos 10 caracteres.';

        if (!$errores) {
            try {
                $pdo->beginTransaction();
                if ($idPost > 0) {
                    $stmt = $pdo->prepare('SELECT id_rol, activo FROM usuario_sistema WHERE id_usuario = :id FOR UPDATE');
                    $stmt->execute([':id' => $idPost]);
                    $anterior = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$anterior) throw new RuntimeException('El usuario no existe.');
                    if ((int) $anterior['activo'] !== 1)
                        throw new RuntimeException('Reactiva la cuenta antes de editarla.');
                    if ($idPost === (int) $_SESSION['usuario_id'] && $idRol !== (int) $anterior['id_rol'])
                        throw new RuntimeException('No puedes cambiar tu propio rol.');
                    if ($idRol !== (int) $anterior['id_rol'] && (int) $anterior['activo'] === 1) {
                        $stmt = $pdo->prepare('SELECT nombre_rol FROM rol WHERE id_rol = :id');
                        $stmt->execute([':id' => (int) $anterior['id_rol']]);
                        if ($stmt->fetchColumn() === 'Administrador TI') {
                            $total = (int) $pdo->query("SELECT COUNT(*) FROM usuario_sistema u JOIN rol r ON u.id_rol = r.id_rol WHERE u.activo = 1 AND r.nombre_rol = 'Administrador TI'")->fetchColumn();
                            if ($total <= 1) throw new RuntimeException('Debe permanecer al menos un Administrador TI activo.');
                        }
                    }
                    $sql = 'UPDATE usuario_sistema SET nombre_completo = :nombre, nombre_usuario = :usuario,
                        correo = :correo, id_rol = :rol' . ($clave !== '' ? ', password_hash = :hash' : '') . '
                        WHERE id_usuario = :id';
                    $params = [':nombre' => $nombre, ':usuario' => $usuario, ':correo' => $correo ?: null,
                        ':rol' => $idRol, ':id' => $idPost];
                    if ($clave !== '') $params[':hash'] = password_hash($clave, PASSWORD_DEFAULT);
                    $pdo->prepare($sql)->execute($params);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO usuario_sistema
                        (nombre_completo, nombre_usuario, correo, password_hash, id_rol, activo)
                        VALUES (:nombre, :usuario, :correo, :hash, :rol, 1)');
                    $stmt->execute([':nombre' => $nombre, ':usuario' => $usuario, ':correo' => $correo ?: null,
                        ':hash' => password_hash($clave, PASSWORD_DEFAULT), ':rol' => $idRol]);
                }
                $pdo->commit();
                header('Location: usuarios.php?' . http_build_query([
                    'resultado' => $idPost > 0 ? 'editado' : 'creado',
                    'buscar' => $usuario,
                ]));
                exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errores[] = $ex instanceof PDOException && $ex->getCode() === '23000'
                    ? 'El nombre de usuario o correo ya existe.'
                    : ($ex instanceof RuntimeException ? $ex->getMessage() : 'No fue posible guardar el usuario.');
            }
        }
    } elseif (in_array($accion, ['baja', 'reactivar'], true) && $idPost > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT u.activo, u.nombre_usuario, r.nombre_rol FROM usuario_sistema u
                JOIN rol r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id FOR UPDATE');
            $stmt->execute([':id' => $idPost]);
            $objetivo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$objetivo) throw new RuntimeException('El usuario no existe.');
            if ($accion === 'baja' && $idPost === (int) $_SESSION['usuario_id'])
                throw new RuntimeException('No puedes dar de baja tu propia cuenta.');
            if ($accion === 'baja' && (int) $objetivo['activo'] === 1 && $objetivo['nombre_rol'] === 'Administrador TI') {
                $total = (int) $pdo->query("SELECT COUNT(*) FROM usuario_sistema u JOIN rol r ON u.id_rol = r.id_rol WHERE u.activo = 1 AND r.nombre_rol = 'Administrador TI'")->fetchColumn();
                if ($total <= 1) throw new RuntimeException('Debe permanecer al menos un Administrador TI activo.');
            }
            $pdo->prepare('UPDATE usuario_sistema SET activo = :activo WHERE id_usuario = :id')
                ->execute([':activo' => $accion === 'reactivar' ? 1 : 0, ':id' => $idPost]);
            $pdo->commit();
            header('Location: usuarios.php?' . http_build_query([
                'resultado' => $accion === 'baja' ? 'baja' : 'reactivado',
                'buscar' => $objetivo['nombre_usuario'],
            ]));
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'No fue posible actualizar el estado.';
        }
    } else {
        $errores[] = 'Operación inválida.';
    }
}

$seleccionado = null;
if (in_array($modo, ['editar', 'baja', 'reactivar'], true) && $id > 0) {
    $stmt = $pdo->prepare('SELECT u.id_usuario, u.nombre_completo, u.nombre_usuario,
        u.correo, u.id_rol, u.activo, r.nombre_rol FROM usuario_sistema u
        JOIN rol r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id');
    $stmt->execute([':id' => $id]);
    $seleccionado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$seleccionado) $errores[] = 'El usuario solicitado no existe.';
    if ($modo === 'editar' && $seleccionado && (int) $seleccionado['activo'] !== 1) {
        $errores[] = 'Reactiva la cuenta antes de editarla.';
        $seleccionado = null;
    }
    if ($modo === 'editar' && $seleccionado && $_SERVER['REQUEST_METHOD'] !== 'POST') $datos = $seleccionado;
}
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$stmt = $pdo->prepare('SELECT u.id_usuario, u.nombre_completo, u.nombre_usuario, u.correo,
    u.activo, u.fecha_creacion, r.nombre_rol FROM usuario_sistema u
    JOIN rol r ON u.id_rol = r.id_rol
    WHERE u.nombre_completo LIKE :nombre OR u.nombre_usuario LIKE :usuario OR u.correo LIKE :correo
    ORDER BY u.id_usuario DESC');
$termino = '%' . $buscar . '%';
$stmt->execute([':nombre' => $termino, ':usuario' => $termino, ':correo' => $termino]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios del sistema | SIGATI</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f4f6f8;color:#1f2937;margin:0}
main{max-width:1200px;margin:30px auto;padding:0 18px}h1{font-size:26px}.panel{background:#fff;border-radius:10px;padding:22px;margin:18px 0;box-shadow:0 2px 8px #0001}
.acciones{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.boton{display:inline-block;border:0;border-radius:6px;padding:9px 13px;text-decoration:none;font-size:14px;font-weight:bold;cursor:pointer;background:#e5e7eb;color:#1f2937}
.principal{background:#2563eb;color:white}.peligro{background:#b91c1c;color:white}.aviso{background:#fff7ed;border:1px solid #fdba74}.error{background:#fee2e2;color:#991b1b}.exito{background:#dcfce7;color:#166534}
.mensaje{padding:13px 15px;border-radius:7px;margin:14px 0}.campo{margin:14px 0}.campo label{display:block;font-weight:bold;margin-bottom:6px}input,select{padding:10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit}input:not([type=hidden]),select{max-width:480px;width:100%}
.tabla{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:850px}th,td{text-align:left;border-bottom:1px solid #e5e7eb;padding:11px;font-size:14px}th{background:#eef2f7}.inactivo{color:#991b1b;font-weight:bold}
</style>
</head>
<body><main>
<div class="acciones"><h1>Usuarios del sistema</h1><a class="boton" href="dashboard.php">Volver al dashboard</a><a class="boton principal" href="usuarios.php?modo=crear">+ Crear usuario</a></div>
<p>Administración de cuentas. La baja conserva las asignaciones y movimientos históricos.</p>
<?php if (isset($_GET['resultado'])): ?>
<div class="mensaje exito"><?= h(['creado'=>'Usuario creado.','editado'=>'Usuario actualizado.','baja'=>'Usuario dado de baja.','reactivado'=>'Usuario reactivado.'][(string)$_GET['resultado']] ?? 'Operación completada.'); ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?><div class="mensaje error"><?= h($error); ?></div><?php endforeach; ?>

<?php if ($modo === 'crear' || ($modo === 'editar' && $seleccionado)): ?>
<section class="panel"><h2><?= $modo === 'crear' ? 'Crear usuario' : 'Editar usuario'; ?></h2>
<form method="post" action="usuarios.php">
<?= csrf_field(); ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id_usuario" value="<?= $modo === 'editar' ? (int)$id : 0; ?>">
<div class="campo"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre_completo" maxlength="150" required value="<?= h((string)$datos['nombre_completo']); ?>"></div>
<div class="campo"><label for="usuario">Nombre de usuario</label><input id="usuario" name="nombre_usuario" maxlength="100" required value="<?= h((string)$datos['nombre_usuario']); ?>"></div>
<div class="campo"><label for="correo">Correo (opcional)</label><input id="correo" name="correo" type="email" maxlength="150" value="<?= h((string)($datos['correo'] ?? '')); ?>"></div>
<div class="campo"><label for="rol">Rol</label><select id="rol" name="id_rol" required><option value="">Selecciona un rol</option>
<?php foreach ($roles as $r): ?><option value="<?= (int)$r['id_rol']; ?>" <?= (string)$datos['id_rol'] === (string)$r['id_rol'] ? 'selected' : ''; ?>><?= h($r['nombre_rol']); ?></option><?php endforeach; ?></select></div>
<div class="campo"><label for="clave"><?= $modo === 'crear' ? 'Contraseña' : 'Nueva contraseña (opcional)'; ?></label><input id="clave" name="password" type="password" minlength="10" <?= $modo === 'crear' ? 'required' : ''; ?> autocomplete="new-password"><small>Al menos 10 caracteres. En edición, dejar vacío para conservar la contraseña.</small></div>
<div class="acciones"><button class="boton principal" type="submit">Guardar usuario</button><a class="boton" href="usuarios.php">Cancelar</a></div>
</form></section>
<?php elseif (($modo === 'baja' || $modo === 'reactivar') && $seleccionado): ?>
<section class="panel aviso"><h2><?= $modo === 'baja' ? 'Confirmar baja' : 'Confirmar reactivación'; ?></h2>
<p><?= $modo === 'baja' ? 'Se impedirá que esta cuenta inicie una sesión nueva. Su historial se conservará.' : 'La cuenta podrá iniciar sesión nuevamente.'; ?></p>
<p><strong><?= h($seleccionado['nombre_completo']); ?></strong> (<?= h($seleccionado['nombre_usuario']); ?>) — <?= h($seleccionado['nombre_rol'] ?? ''); ?></p>
<form method="post" action="usuarios.php"><?= csrf_field(); ?><input type="hidden" name="accion" value="<?= h($modo); ?>"><input type="hidden" name="id_usuario" value="<?= (int)$id; ?>">
<button class="boton <?= $modo === 'baja' ? 'peligro' : 'principal'; ?>" type="submit">Confirmar <?= $modo === 'baja' ? 'baja' : 'reactivación'; ?></button><a class="boton" href="usuarios.php">Cancelar</a></form></section>
<?php endif; ?>

<section class="panel"><form method="get" action="usuarios.php" class="acciones"><input name="buscar" aria-label="Buscar usuario" placeholder="Buscar por nombre, usuario o correo" value="<?= h($buscar); ?>"><button class="boton principal">Buscar</button><?php if ($buscar !== ''): ?><a class="boton" href="usuarios.php">Limpiar</a><?php endif; ?></form></section>
<section class="panel tabla"><table><thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr><td><?= (int)$u['id_usuario']; ?></td><td><?= h($u['nombre_completo']); ?></td><td><?= h($u['nombre_usuario']); ?></td><td><?= h($u['correo'] ?: '—'); ?></td><td><?= h($u['nombre_rol']); ?></td><td class="<?= (int)$u['activo'] === 1 ? '' : 'inactivo'; ?>"><?= (int)$u['activo'] === 1 ? 'Activo' : 'Inactivo'; ?></td><td><?= h($u['fecha_creacion']); ?></td><td><div class="acciones"><?php if ((int)$u['activo'] === 1): ?><a class="boton" href="usuarios.php?modo=editar&amp;id=<?= (int)$u['id_usuario']; ?>">Editar</a><?php endif; ?>
<?php if ((int)$u['activo'] === 1 && (int)$u['id_usuario'] !== (int)$_SESSION['usuario_id']): ?><a class="boton peligro" href="usuarios.php?modo=baja&amp;id=<?= (int)$u['id_usuario']; ?>">Dar de baja</a><?php elseif ((int)$u['activo'] === 0): ?><a class="boton" href="usuarios.php?modo=reactivar&amp;id=<?= (int)$u['id_usuario']; ?>">Reactivar</a><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$usuarios): ?><tr><td colspan="8">No se encontraron usuarios.</td></tr><?php endif; ?>
</tbody></table></section>
</main></body></html>
