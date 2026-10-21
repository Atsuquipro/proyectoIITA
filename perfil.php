<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
require_login();

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $actual = trim($_POST['password_actual'] ?? '');
    $nueva = trim($_POST['password_nueva'] ?? '');
    $confirmar = trim($_POST['password_confirmar'] ?? '');

    if ($actual === '' || $nueva === '' || $confirmar === '') {
        set_flash('error', 'Completa todos los campos para cambiar tu contraseña.');
    } elseif (!verify_user_password($actual, $user['password'])) {
        set_flash('error', 'La contraseña actual ingresada es incorrecta.');
    } elseif (strlen($nueva) < 4) {
        set_flash('error', 'La nueva contraseña debe tener al menos 4 caracteres.');
    } elseif ($nueva !== $confirmar) {
        set_flash('error', 'La confirmación de la contraseña no coincide.');
    } else {
        $nuevoHash = hash_user_password($nueva);
        $upd = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $upd->execute([$nuevoHash, $user['id']]);
        set_flash('success', '¡Contraseña actualizada correctamente!');
        header('Location: perfil.php');
        exit;
    }
}

// Procesar foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_avatar'])) {
    $ruta = procesar_subida_archivo('foto_avatar');
    if ($ruta) {
        $pdo->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?")->execute([$ruta, $user['id']]);
        set_flash('success', 'Foto de perfil actualizada.');
        header('Location: perfil.php');
        exit;
    }
}

// Recargar datos actualizados del usuario
$stmtRefresh = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmtRefresh->execute([$user['id']]);
$user = $stmtRefresh->fetch();

$stmtC = $pdo->prepare("SELECT nombre FROM cursos WHERE id = ?");
$stmtC->execute([$user['curso_id']]);
$cursoNombre = $stmtC->fetchColumn() ?: 'Sin asignar';

$notas = [];
$promedio = 0;
if ($user['curso_id']) {
    $stmtN = $pdo->prepare("SELECT m.nombre as materia, n.nota FROM materias m LEFT JOIN notas n ON m.id = n.materia_id AND n.alumno_id = ? WHERE m.curso_id = ?");
    $stmtN->execute([$user['id'], $user['curso_id']]);
    $notasRaw = $stmtN->fetchAll();
    
    $suma = 0;
    $count = 0;
    foreach ($notasRaw as $nr) {
        $notas[] = $nr;
        if ($nr['nota'] !== null) {
            $suma += (float)$nr['nota'];
            $count++;
        }
    }
    if ($count > 0) $promedio = round($suma / $count, 2);
}

$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card-header" style="border:none; padding:0 0 1rem 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <h1 style="font-size: 1.6rem;">👤 Mi Perfil</h1>
    <?php if($user['rol_prioridad'] <= 2 && $user['curso_id']): ?>
        <a href="boletin.php" target="_blank" class="btn btn-secondary btn-sm">🖨️ Imprimir Boletín Oficial</a>
    <?php endif; ?>
</div>

<div class="grid-cards" style="margin-bottom:1.5rem;">
    <!-- Tarjeta de Datos Personales con Avatar -->
    <div class="card" style="margin-bottom:0;">
        <h2 style="font-size:1.2rem; margin-bottom:1rem;">Datos Personales</h2>
        
        <div style="display:flex; align-items:center; gap:1.25rem; margin-bottom:1.25rem;">
            <div style="width:72px; height:72px; border-radius:50%; background:var(--primary-subtle); display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px solid var(--border-color); flex-shrink:0;">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <span style="font-size:2rem;">👤</span>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="font-size:1.15rem; margin-bottom:0.2rem;"><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></h3>
                <span class="badge badge-<?= strtolower(str_replace(['/', ' '], ['-', ''], get_rol_name($user['rol_prioridad']))) ?>">
                    <?= htmlspecialchars(get_rol_name($user['rol_prioridad'])) ?>
                </span>
            </div>
        </div>

        <p style="margin-bottom:0.4rem;"><strong>Usuario:</strong> <?= htmlspecialchars($user['username']) ?></p>
        <?php if($user['rol_prioridad'] <= 2): ?>
            <p style="margin-bottom:0.4rem;"><strong>Curso:</strong> <?= htmlspecialchars($cursoNombre) ?></p>
        <?php endif; ?>

        <!-- Formulario para subir/cambiar foto -->
        <form method="POST" enctype="multipart/form-data" style="margin-top:1rem; padding-top:1rem; border-top:1px dashed var(--border-color);">
            <input type="hidden" name="subir_avatar" value="1">
            <label class="form-label" style="font-size:0.82rem;">Cambiar foto de perfil:</label>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <input type="file" name="foto_avatar" class="form-control" accept="image/*" required style="font-size:0.8rem; padding:0.4rem;">
                <button type="submit" class="btn btn-secondary btn-sm" style="white-space:nowrap;">Subir</button>
            </div>
        </form>
    </div>

    <!-- Tarjeta de Seguridad (Cambiar Contraseña) -->
    <div class="card" style="margin-bottom:0;">
        <h2 style="font-size:1.2rem; margin-bottom:1rem;">🔐 Seguridad de la Cuenta</h2>
        <form method="POST" action="perfil.php">
            <input type="hidden" name="cambiar_password" value="1">
            <div class="form-group">
                <label class="form-label">Contraseña Actual:</label>
                <input type="password" name="password_actual" class="form-control" required placeholder="Tu contraseña actual">
            </div>
            <div class="form-group">
                <label class="form-label">Nueva Contraseña:</label>
                <input type="password" name="password_nueva" class="form-control" required placeholder="Mínimo 4 caracteres">
            </div>
            <div class="form-group">
                <label class="form-label">Confirmar Nueva Contraseña:</label>
                <input type="password" name="password_confirmar" class="form-control" required placeholder="Repite la nueva contraseña">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Actualizar Contraseña</button>
        </form>
    </div>
</div>

<?php if($user['rol_prioridad'] <= 2): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Mis Calificaciones</h2>
            <a href="boletin.php" target="_blank" class="btn btn-secondary btn-sm">🖨️ Ver Boletín Oficial</a>
        </div>
        <?php if (empty($notas)): ?>
            <p style="color:var(--text-muted);">No tienes materias o no estás asignado a un curso todavía.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="width:100%;">
                    <thead><tr><th style="text-align:left;">Materia</th><th style="text-align:left;">Nota</th></tr></thead>
                    <tbody>
                        <?php foreach ($notas as $n): ?>
                            <tr>
                                <td><?= htmlspecialchars($n['materia']) ?></td>
                                <td><?= $n['nota'] !== null ? htmlspecialchars($n['nota']) : '<span style="color:var(--text-muted);">Sin calificar</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h3 style="margin-top:1.25rem; text-align:right; font-size:1.1rem; color:var(--primary);">
                Promedio General: <strong><?= $promedio > 0 ? $promedio : 'N/A' ?></strong>
            </h3>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>