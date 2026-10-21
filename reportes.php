<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();
$priority = user_priority();
$username = current_username();

// Buscar mi reporte actual
$stmtMi = $pdo->prepare("SELECT * FROM reportes WHERE autor_id = ? AND escuela_id = ? LIMIT 1");
$stmtMi->execute([$user['id'], $schoolId]);
$miReporteObj = $stmtMi->fetch();
$miReporte = $miReporteObj ? ($miReporteObj['tipo'] === 'urgente' ? '⚠️ ' : '') . $miReporteObj['descripcion'] : null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear_reporte') {
        if ($miReporteObj) {
            set_flash('error', 'Ya tienes un reporte activo. Elimina el anterior para enviar uno nuevo.');
        } else {
            $texto = trim($_POST['reporte'] ?? '');
            $urgente = !empty($_POST['urgente']) && $priority >= 2;

            if ($texto === '') {
                set_flash('error', '¡Escribe un mensaje o reporte para continuar!');
            } else {
                $tipo = $urgente ? 'urgente' : 'normal';
                $ins = $pdo->prepare("INSERT INTO reportes (escuela_id, titulo, descripcion, autor_id, tipo) VALUES (?, 'Reporte', ?, ?, ?)");
                $ins->execute([$schoolId, $texto, $user['id'], $tipo]);
                set_flash('success', '¡El reporte se envió de manera correcta!');
            }
        }
        header('Location: reportes.php');
        exit;
    }

    if ($action === 'eliminar_reporte') {
        if (!$miReporteObj) {
            set_flash('error', 'No tienes reportes activos para eliminar.');
        } else {
            $pdo->prepare("DELETE FROM reportes WHERE autor_id = ? AND escuela_id = ?")->execute([$user['id'], $schoolId]);
            set_flash('success', '¡El reporte se eliminó correctamente!');
        }
        header('Location: reportes.php');
        exit;
    }

    if ($action === 'marcar_leido' && $priority >= 3) {
        $reporteId = (int)$_POST['reporte_id'];
        $pdo->prepare("UPDATE reportes SET leido = 1 WHERE id = ? AND escuela_id = ?")->execute([$reporteId, $schoolId]);
        set_flash('success', 'Reporte marcado como leído.');
        header('Location: reportes.php');
        exit;
    }
}

// Obtener todos los reportes para profes/directivos
$todosLosReportes = [];
if ($priority >= 3) {
    $stmtT = $pdo->prepare("SELECT r.*, u.username, u.nombre, u.apellido, u.rol_prioridad FROM reportes r JOIN usuarios u ON r.autor_id = u.id WHERE r.escuela_id = ? ORDER BY r.fecha DESC");
    $stmtT->execute([$schoolId]);
    $todosLosReportes = $stmtT->fetchAll();
}

$pageTitle = 'Reportes y Mensajes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">📢 Buzón de Reportes y Mensajes</h2>
    </div>

    <?php if ($miReporte): ?>
        <div class="card" style="background: var(--bg-card-alt); border-left: 4px solid var(--primary);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <h3 style="font-size: 1rem; margin:0; color: var(--text-main);">Tu reporte actual:</h3>
                <?php if ($miReporteObj['leido']): ?>
                    <span class="badge" style="background:var(--success); color:white;">✅ Visto por un directivo</span>
                <?php else: ?>
                    <span class="badge" style="background:var(--warning); color:black;">⏳ Pendiente de lectura</span>
                <?php endif; ?>
            </div>
            
            <div style="max-height: 200px; overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1rem; font-size: 1.05rem; white-space: pre-wrap; word-break: break-word;">
                <?= htmlspecialchars($miReporte) ?>
            </div>
            
            <form method="POST" action="reportes.php" style="display:inline;">
                <input type="hidden" name="action" value="eliminar_reporte">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar tu reporte?');">
                    🗑️ Eliminar Mi Reporte
                </button>
            </form>
        </div>
    <?php else: ?>
        <form method="POST" action="reportes.php">
            <input type="hidden" name="action" value="crear_reporte">
            
            <div class="form-group">
                <label class="form-label" for="reporte">Escribe tu mensaje o reporte institucional:</label>
                <textarea id="reporte" name="reporte" class="form-control" placeholder="Escribe aquí los detalles del mensaje o solicitud..." required rows="4"></textarea>
            </div>

            <?php if ($priority >= 2): ?>
                <div class="form-group">
                    <label class="choice-option" for="urgente" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2);">
                        <input type="checkbox" id="urgente" name="urgente" value="1" style="accent-color: var(--danger);">
                        <span style="font-weight: 600; color: var(--danger); font-size: 0.9rem;">
                            ⚠️ Marcar como reporte urgente
                        </span>
                    </label>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">
                Enviar Reporte
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Listado global de reportes para Profesores, Vice-directores y Directores -->
<?php if ($priority >= 3): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📋 Todos los Reportes Recibidos</h2>
        <span class="badge badge-profesor">Total: <?= count($todosLosReportes) ?></span>
    </div>

    <?php if (empty($todosLosReportes)): ?>
        <p style="color: var(--text-muted);">No hay ningún reporte registrado en el sistema en este momento.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="table-layout: fixed; width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 200px;">Usuario Emisor</th>
                        <th style="width: 100px;">Tipo</th>
                        <th>Contenido del Reporte</th>
                        <th style="width: 130px; text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todosLosReportes as $r): 
                        $esUrgente = ($r['tipo'] === 'urgente');
                        $textoReal = ($esUrgente ? '⚠️ ' : '') . $r['descripcion'];
                        $rolAutor = get_rol_name($r['rol_prioridad']);
                    ?>
                        <tr style="<?= $esUrgente ? 'background-color: rgba(239,68,68,0.1);' : '' ?>">
                            <td style="vertical-align: top;">
                                <strong><?= htmlspecialchars($r['username']) ?></strong>
                                <br><small style="color: var(--text-muted);"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?> (<?= htmlspecialchars($rolAutor) ?>)</small>
                            </td>
                            <td style="vertical-align: top;">
                                <?php if ($esUrgente): ?>
                                    <span class="badge" style="background: var(--danger); color: white; display:inline-block; margin-bottom:0.25rem;">⚠️ URGENTE</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="background: var(--border-color); color: var(--text-main); display:inline-block; margin-bottom:0.25rem;">Mensaje</span>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: top;">
                                <div style="max-height: 120px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; padding-right: 0.5rem; color:var(--text-main);">
                                    <?= htmlspecialchars($textoReal) ?>
                                </div>
                            </td>
                            <td style="vertical-align: top; text-align: center;">
                                <?php if ($r['leido']): ?>
                                    <span class="badge" style="background:var(--success); color:white; display:inline-block; width:100%; text-align:center; padding:0.4rem;">✅ Leído</span>
                                <?php else: ?>
                                    <form method="POST" action="reportes.php" style="margin:0;">
                                        <input type="hidden" name="action" value="marcar_leido">
                                        <input type="hidden" name="reporte_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; font-size:0.75rem; padding:0.4rem;">Marcar Leído</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
