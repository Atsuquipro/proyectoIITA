<?php
require_once __DIR__ . '/includes/auth.php';
require_priority(4); // Vicedirector o Director

$pdo = get_db();
$schoolId = current_school_id();
$priority = user_priority();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $uid = (int)$_POST['usuario_id'];
    $nuevoRol = (int)$_POST['nuevo_rol'];

    // Validaciones de jerarquía
    $stmtU = $pdo->prepare("SELECT rol_prioridad FROM usuarios WHERE id = ? AND escuela_id = ?");
    $stmtU->execute([$uid, $schoolId]);
    $targetUser = $stmtU->fetch();

    if ($targetUser) {
        if ($uid == $user['id']) {
            set_flash('error', 'No puedes cambiar tu propio rol.');
        } elseif ($priority < 5 && $targetUser['rol_prioridad'] >= 4) {
            set_flash('error', 'No puedes cambiar el rol de otro Vicedirector o Director.');
        } elseif ($priority < 5 && $nuevoRol >= 4) {
            set_flash('error', 'No puedes asignar roles de Vicedirector o Director.');
        } else {
            $pdo->prepare("UPDATE usuarios SET rol_prioridad = ? WHERE id = ?")->execute([$nuevoRol, $uid]);
            set_flash('success', 'Rol actualizado correctamente.');
        }
    }
    header("Location: roles.php");
    exit;
}

$stmtUsers = $pdo->prepare("SELECT id, username, nombre, apellido, rol_prioridad FROM usuarios WHERE escuela_id = ? ORDER BY rol_prioridad DESC, apellido ASC");
$stmtUsers->execute([$schoolId]);
$usuarios = $stmtUsers->fetchAll();

$pageTitle = 'Gestión de Roles';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">🛡️ Gestión de Roles</h1>
</div>

<div class="card">
    <table class="table" style="width:100%;">
        <thead>
            <tr>
                <th style="text-align:left;">Usuario</th>
                <th style="text-align:left;">Nombre</th>
                <th style="text-align:left;">Rol Actual</th>
                <th style="text-align:left;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                    <td><span class="badge"><?= get_rol_name($u['rol_prioridad']) ?></span></td>
                    <td>
                        <?php if ($u['id'] != $user['id'] && ($priority == 5 || ($priority == 4 && $u['rol_prioridad'] < 4))): ?>
                            <form method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                                <input type="hidden" name="cambiar_rol" value="1">
                                <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                <select name="nuevo_rol" class="form-control">
                                    <option value="1" <?= $u['rol_prioridad']==1?'selected':'' ?>>Estudiante</option>
                                    <option value="2" <?= $u['rol_prioridad']==2?'selected':'' ?>>Delegado</option>
                                    <option value="3" <?= $u['rol_prioridad']==3?'selected':'' ?>>Profesor</option>
                                    <?php if ($priority >= 5): ?>
                                        <option value="4" <?= $u['rol_prioridad']==4?'selected':'' ?>>Vice-director</option>
                                    <?php endif; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
