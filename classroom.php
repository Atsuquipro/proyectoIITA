<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
require_login();

$pdo = get_db();
$user = current_user();
$priority = user_priority();
$cursoId = $user['curso_id'];

$error = '';

if ($priority >= 3) {
    $stmtM = $pdo->prepare("SELECT m.*, c.nombre as curso_nombre FROM materias m JOIN cursos c ON m.curso_id = c.id WHERE c.escuela_id = ? ORDER BY c.nombre, m.nombre");
    $stmtM->execute([current_school_id()]);
} else if ($cursoId) {
    $stmtM = $pdo->prepare("SELECT m.*, c.nombre as curso_nombre FROM materias m JOIN cursos c ON m.curso_id = c.id WHERE c.id = ? ORDER BY m.nombre");
    $stmtM->execute([$cursoId]);
} else {
    set_flash('error', 'Debes tener un curso asignado para ver el Classroom.');
    header('Location: index.php');
    exit;
}
$materias = $stmtM->fetchAll();

$materiaIds = array_column($materias, 'id');
$tareas = [];
if (!empty($materiaIds)) {
    $in = str_repeat('?,', count($materiaIds) - 1) . '?';
    $stmtT = $pdo->prepare("SELECT t.*, m.nombre as materia_nombre, m.curso_id, c.nombre as curso_nombre, u.nombre as creador_nombre, u.apellido as creador_apellido, u.rol_prioridad as creador_prioridad FROM tareas t JOIN materias m ON t.materia_id = m.id JOIN cursos c ON m.curso_id = c.id JOIN usuarios u ON t.creador_id = u.id WHERE t.materia_id IN ($in) ORDER BY t.fecha_creacion DESC");
    $stmtT->execute($materiaIds);
    $tareas = $stmtT->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_tarea']) && $priority >= 3) {
        $titulo = trim($_POST['titulo']);
        $desc = trim($_POST['descripcion']);
        $matId = (int)$_POST['materia_id'];
        $tipo = $_POST['tipo_entrega'];
        $fechaLimit = $_POST['fecha_limite'] ?: null;
        
        if (empty($titulo) || !$matId) {
            $error = "Título y Materia son obligatorios.";
        } else {
            $archivo_ruta = procesar_subida_archivo('archivo_adjunto');
            if ($archivo_ruta === false) {
                // Hubo un error de validación de extensión, procesar_subida_archivo ya hizo set_flash
                header('Location: classroom.php');
                exit;
            }

            $ins = $pdo->prepare("INSERT INTO tareas (materia_id, creador_id, titulo, descripcion, fecha_limite, tipo_entrega, archivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$matId, $user['id'], $titulo, $desc, $fechaLimit, $tipo, $archivo_ruta]);
            set_flash('success', 'Tarea creada correctamente.');
            header('Location: classroom.php');
            exit;
        }
    }
    
    if (isset($_POST['eliminar_tarea'])) {
        $tareaId = (int)$_POST['tarea_id'];
        
        $stmtC = $pdo->prepare("SELECT t.creador_id, u.rol_prioridad as creador_prioridad FROM tareas t JOIN usuarios u ON t.creador_id = u.id WHERE t.id = ?");
        $stmtC->execute([$tareaId]);
        $creador = $stmtC->fetch();
        
        if ($creador) {
            if ($user['id'] == $creador['creador_id'] || $priority > $creador['creador_prioridad']) {
                $pdo->prepare("DELETE FROM tareas WHERE id = ?")->execute([$tareaId]);
                set_flash('success', 'Tarea eliminada correctamente.');
            } else {
                set_flash('error', 'No tienes permisos para eliminar esta tarea.');
            }
        }
        header('Location: classroom.php');
        exit;
    }
}

$pageTitle = 'Classroom';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0; display:flex; justify-content:space-between; align-items:center;">
    <h1 style="font-size: 1.6rem;">🎓 Classroom</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div class="grid-cards">
    <?php if ($priority >= 3): ?>
    <div class="card">
        <h3 class="card-title">➕ Crear Nueva Tarea</h3>
        <form method="POST" action="classroom.php" enctype="multipart/form-data">
            <input type="hidden" name="crear_tarea" value="1">
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Materia</label>
                <select name="materia_id" class="form-control" required>
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['curso_nombre'] . ' - ' . $m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tipo de Entrega</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                    <label class="choice-option" for="entrega_ind" style="margin-bottom:0; justify-content:center;">
                        <input type="radio" id="entrega_ind" name="tipo_entrega" value="individual" checked>
                        <span style="font-size:0.875rem; font-weight:600;">👤 Individual</span>
                    </label>
                    <label class="choice-option" for="entrega_grup" style="margin-bottom:0; justify-content:center;">
                        <input type="radio" id="entrega_grup" name="tipo_entrega" value="grupal">
                        <span style="font-size:0.875rem; font-weight:600;">👥 Grupal (Minigrupos)</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha Límite (Opcional)</label>
                <input type="datetime-local" name="fecha_limite" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Adjuntar Archivo/Imagen (Opcional)</label>
                <input type="file" name="archivo_adjunto" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Publicar Tarea</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card" style="grid-column: span <?= $priority >= 3 ? '1' : '2' ?>;">
        <h3 class="card-title" style="margin-bottom:1rem;">Tablón de Tareas</h3>
        <?php if (empty($tareas)): ?>
            <p style="color:var(--text-muted);">No hay tareas asignadas.</p>
        <?php else: ?>
            <?php foreach ($tareas as $t): 
                // Verificar si entregué
                $entregada = false;
                if ($priority < 3) {
                    if ($t['tipo_entrega'] === 'individual') {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM entregas WHERE tarea_id = ? AND alumno_id = ?");
                        $chk->execute([$t['id'], $user['id']]);
                        $entregada = $chk->fetchColumn() > 0;
                    } else {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM entregas e JOIN minigrupos_usuarios mu ON e.minigrupo_id = mu.minigrupo_id WHERE e.tarea_id = ? AND mu.usuario_id = ?");
                        $chk->execute([$t['id'], $user['id']]);
                        $entregada = $chk->fetchColumn() > 0;
                    }
                }
                
                $puedeEliminar = ($user['id'] == $t['creador_id'] || $priority > $t['creador_prioridad']);
            ?>
                <div style="background:var(--bg-hover); padding:1rem; border-radius:8px; margin-bottom:1rem; border:1px solid var(--border); border-left:4px solid <?= $entregada ? 'var(--success)' : 'var(--primary)' ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <h4 style="color:var(--primary); font-size:1.15rem; margin-bottom:0.25rem;">
                                <?= htmlspecialchars($t['titulo']) ?>
                            </h4>
                            <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.5rem;">
                                <?= htmlspecialchars($t['curso_nombre']) ?> - <strong><?= htmlspecialchars($t['materia_nombre']) ?></strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <?php if ($priority < 3): ?>
                                <span class="badge" style="background:<?= $entregada ? 'var(--success)' : 'var(--warning)' ?>; color:<?= $entregada ? '#fff' : '#000' ?>;">
                                    <?= $entregada ? 'Entregada' : 'Pendiente' ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($puedeEliminar): ?>
                                <form method="POST" action="classroom.php" style="margin:0;">
                                    <input type="hidden" name="eliminar_tarea" value="1">
                                    <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta tarea y todas sus entregas?');">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p style="font-size:0.9rem; margin-bottom:1rem; line-height:1.4;"><?= nl2br(htmlspecialchars($t['descripcion'])) ?></p>
                    
                    <?= mostrar_archivo($t['archivo'] ?? null) ?>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; color:var(--text-muted);">
                        <span>👤 <?= htmlspecialchars($t['creador_nombre'] . ' ' . $t['creador_apellido']) ?> | 📅 <?= date('d/m Y', strtotime($t['fecha_creacion'])) ?></span>
                        <a href="tarea.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Ver Tarea</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
