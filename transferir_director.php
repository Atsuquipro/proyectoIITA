<?php
require_once __DIR__ . '/includes/auth.php';
require_priority(5); // Solo Director

$pdo = get_db();
$schoolId = current_school_id();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transferir'])) {
    $uid = (int)$_POST['nuevo_director_id'];
    $pass = $_POST['password'];

    if (verify_user_password($pass, $user['password'])) {
        try {
            $pdo->beginTransaction();
            // Demote actual director a profesor (3)
            $pdo->prepare("UPDATE usuarios SET rol_prioridad = 3 WHERE id = ?")->execute([$user['id']]);
            // Promote nuevo director (5)
            $pdo->prepare("UPDATE usuarios SET rol_prioridad = 5 WHERE id = ?")->execute([$uid]);
            $pdo->commit();
            
            set_flash('success', 'Dirección transferida correctamente. Ahora eres profesor.');
            // Actualizar sesión actual a profesor
            $_SESSION['user']['rol_prioridad'] = 3;
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Error al transferir la dirección.');
        }
    } else {
        set_flash('error', 'Contraseña incorrecta.');
    }
}

$stmtU = $pdo->prepare("SELECT id, username, nombre, apellido FROM usuarios WHERE escuela_id = ? AND id != ? ORDER BY nombre");
$stmtU->execute([$schoolId, $user['id']]);
$usuarios = $stmtU->fetchAll();

$pageTitle = 'Transferir Dirección';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">🏛️ Transferir Dirección</h1>
</div>

<div class="card">
    <p style="color:var(--text-muted); margin-bottom:1rem;">Atención: Al transferir la dirección, perderás tus privilegios de Director y pasarás a ser Profesor. Esta acción es irreversible a menos que el nuevo director te la devuelva.</p>
    
    <form method="POST">
        <input type="hidden" name="transferir" value="1">
        <div class="form-group">
            <label>Seleccionar nuevo director:</label>
            <select name="nuevo_director_id" class="form-control" required>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre'].' '.$u['apellido'].' ('.$u['username'].')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tu contraseña actual (por seguridad):</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-danger">Confirmar Transferencia</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
