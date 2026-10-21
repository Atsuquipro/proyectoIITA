<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
require_login();

$pdo = get_db();
$user = current_user();
$priority = user_priority();
$tareaId = $_GET['id'] ?? null;

if (!$tareaId) {
    header('Location: classroom.php');
    exit;
}

$stmtT = $pdo->prepare("SELECT t.*, m.nombre as materia_nombre, c.nombre as curso_nombre FROM tareas t JOIN materias m ON t.materia_id = m.id JOIN cursos c ON m.curso_id = c.id WHERE t.id = ?");
$stmtT->execute([$tareaId]);
$tarea = $stmtT->fetch();

if (!$tarea) {
    set_flash('error', 'Tarea no encontrada.');
    header('Location: classroom.php');
    exit;
}

// Lógica de entrega
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entregar'])) {
    $contenido = trim($_POST['contenido']);
    
    $archivo_ruta = procesar_subida_archivo('archivo_entrega');
    if ($archivo_ruta === false) {
        header("Location: tarea.php?id=" . $tareaId);
        exit;
    }

    if ($tarea['tipo_entrega'] === 'grupal') {
        $mgId = $_POST['minigrupo_id'];
        $ins = $pdo->prepare("INSERT INTO entregas (tarea_id, minigrupo_id, contenido, archivo) VALUES (?, ?, ?, ?)");
        $ins->execute([$tareaId, $mgId, $contenido, $archivo_ruta]);
    } else {
        $ins = $pdo->prepare("INSERT INTO entregas (tarea_id, alumno_id, contenido, archivo) VALUES (?, ?, ?, ?)");
        $ins->execute([$tareaId, $user['id'], $contenido, $archivo_ruta]);
    }
    set_flash('success', 'Entrega enviada correctamente.');
    header("Location: tarea.php?id=" . $tareaId);
    exit;
}

// Lógica de calificación y devolución (feedback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar']) && $priority >= 3) {
    $entregaId = (int)$_POST['entrega_id'];
    $nota = (float)$_POST['nota'];
    $feedback = trim($_POST['feedback'] ?? '');
    
    $upd = $pdo->prepare("UPDATE entregas SET calificacion = ?, feedback = ? WHERE id = ?");
    $upd->execute([$nota, $feedback, $entregaId]);
    set_flash('success', 'Calificación y devolución guardadas.');
    header("Location: tarea.php?id=" . $tareaId);
    exit;
}

// Lógica de comentarios / dudas de la tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publicar_comentario'])) {
    $comentarioTexto = trim($_POST['comentario'] ?? '');
    if (!empty($comentarioTexto)) {
        $stmtC = $pdo->prepare("INSERT INTO comentarios_tarea (tarea_id, usuario_id, contenido) VALUES (?, ?, ?)");
        $stmtC->execute([$tareaId, $user['id'], $comentarioTexto]);
        set_flash('success', 'Pregunta o comentario publicado.');
        header("Location: tarea.php?id=" . $tareaId . "#comentarios");
        exit;
    }
}

$pageTitle = 'Tarea: ' . htmlspecialchars($tarea['titulo']);
require_once __DIR__ . '/includes/header.php';

// Entregas existentes
$entregas = [];
if ($priority >= 3) {
    $stmtE = $pdo->prepare("SELECT e.*, u.nombre, u.apellido, mg.nombre as grupo_nombre FROM entregas e LEFT JOIN usuarios u ON e.alumno_id = u.id LEFT JOIN minigrupos mg ON e.minigrupo_id = mg.id WHERE e.tarea_id = ? ORDER BY e.fecha_entrega DESC");
    $stmtE->execute([$tareaId]);
    $entregas = $stmtE->fetchAll();
} else {
    if ($tarea['tipo_entrega'] === 'individual') {
        $stmtE = $pdo->prepare("SELECT e.* FROM entregas e WHERE e.tarea_id = ? AND e.alumno_id = ?");
        $stmtE->execute([$tareaId, $user['id']]);
    } else {
        $stmtE = $pdo->prepare("SELECT e.*, mg.nombre as grupo_nombre FROM entregas e JOIN minigrupos_usuarios mu ON e.minigrupo_id = mu.minigrupo_id JOIN minigrupos mg ON e.minigrupo_id = mg.id WHERE e.tarea_id = ? AND mu.usuario_id = ?");
        $stmtE->execute([$tareaId, $user['id']]);
    }
    $entregas = $stmtE->fetchAll();
}

// Cargar comentarios / dudas
$stmtCom = $pdo->prepare("SELECT c.*, u.nombre, u.apellido, u.rol_prioridad FROM comentarios_tarea c JOIN usuarios u ON c.usuario_id = u.id WHERE c.tarea_id = ? ORDER BY c.fecha ASC");
$stmtCom->execute([$tareaId]);
$comentarios = $stmtCom->fetchAll();
?>

<div class="card" style="margin-top:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2 style="color:var(--primary); margin-bottom:0.25rem;"><?= htmlspecialchars($tarea['titulo']) ?></h2>
            <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:0.5rem;">
                <?= htmlspecialchars($tarea['curso_nombre']) ?> &bull; <strong><?= htmlspecialchars($tarea['materia_nombre']) ?></strong> &bull;
                Tipo: <span class="badge" style="background:var(--primary-subtle); color:var(--primary);"><?= ucfirst($tarea['tipo_entrega']) ?></span>
            </div>
        </div>
        <?php if ($tarea['fecha_limite']): ?>
            <div class="badge" style="background:var(--warning-light); color:var(--warning-hover); font-size:0.85rem; padding:0.4rem 0.8rem;">
                📅 Fecha Límite: <?= date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="padding:1.25rem; background:var(--bg-card-alt); border-radius:var(--radius-sm); border:1px solid var(--border-color); margin:1rem 0;">
        <?= nl2br(htmlspecialchars($tarea['descripcion'])) ?>
    </div>
    
    <?= mostrar_archivo($tarea['archivo'] ?? null) ?>
    
    <hr style="border-color:var(--border-color); margin:2rem 0;">
    
    <!-- Sección de Entregas -->
    <?php if ($priority >= 3): ?>
        <h3 style="margin-bottom:1rem;">📋 Entregas Recibidas (<?= count($entregas) ?>)</h3>
        <?php if (empty($entregas)): ?>
            <p style="color:var(--text-muted);">Aún no hay entregas para esta tarea.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <?php foreach ($entregas as $e): 
                    $esTarde = false;
                    if ($tarea['fecha_limite'] && $e['fecha_entrega']) {
                        $esTarde = (strtotime($e['fecha_entrega']) > strtotime($tarea['fecha_limite']));
                    }
                ?>
                    <div style="padding:1.25rem; border:1px solid var(--border-color); border-radius:var(--radius-sm); background:var(--bg-card);">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:0.75rem;">
                            <strong><?= $e['grupo_nombre'] ? '👥 Grupo: '.htmlspecialchars($e['grupo_nombre']) : '👤 Alumno: '.htmlspecialchars($e['nombre'].' '.$e['apellido']) ?></strong>
                            <div style="display:flex; gap:0.5rem; align-items:center;">
                                <span style="font-size:0.8rem; color:var(--text-muted);"><?= date('d/m/Y H:i', strtotime($e['fecha_entrega'])) ?></span>
                                <?php if ($tarea['fecha_limite']): ?>
                                    <?php if ($esTarde): ?>
                                        <span class="badge" style="background:var(--danger-light); color:var(--danger);">⏰ Con retraso</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-light); color:var(--success);">⏱️ A tiempo</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p style="margin:0.5rem 0; font-size:0.95rem;"><?= nl2br(htmlspecialchars($e['contenido'])) ?></p>
                        
                        <?= mostrar_archivo($e['archivo'] ?? null) ?>
                        
                        <!-- Formulario de Calificación y Feedback -->
                        <form method="POST" style="margin-top:1rem; padding-top:1rem; border-top:1px dashed var(--border-color);">
                            <input type="hidden" name="calificar" value="1">
                            <input type="hidden" name="entrega_id" value="<?= $e['id'] ?>">
                            
                            <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-start;">
                                <div style="width:120px;">
                                    <label class="form-label">Nota (1 a 10):</label>
                                    <input type="number" step="0.1" min="1" max="10" name="nota" class="form-control" value="<?= htmlspecialchars($e['calificacion'] ?? '') ?>" required placeholder="Ej: 8.5">
                                </div>
                                <div style="flex:1; min-width:240px;">
                                    <label class="form-label">Devolución / Observaciones:</label>
                                    <textarea name="feedback" class="form-control" rows="2" placeholder="Escribe un comentario u orientación para el estudiante..."><?= htmlspecialchars($e['feedback'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">Guardar Calificación y Devolución</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if (!empty($entregas)): ?>
            <h3>Tu Entrega</h3>
            <?php 
                $miEntrega = $entregas[0]; 
                $esTarde = false;
                if ($tarea['fecha_limite'] && $miEntrega['fecha_entrega']) {
                    $esTarde = (strtotime($miEntrega['fecha_entrega']) > strtotime($tarea['fecha_limite']));
                }
            ?>
            <div style="margin-top:1rem; padding:1.25rem; border:1px solid var(--success); border-radius:var(--radius-sm); background:rgba(16,185,129,0.06);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <strong>Trabajo entregado el <?= date('d/m/Y H:i', strtotime($miEntrega['fecha_entrega'])) ?></strong>
                    <?php if ($tarea['fecha_limite']): ?>
                        <?php if ($esTarde): ?>
                            <span class="badge" style="background:var(--danger-light); color:var(--danger);">⏰ Entregada con retraso</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--success-light); color:var(--success);">⏱️ Entregada a tiempo</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <p style="margin-bottom:0.75rem;"><?= nl2br(htmlspecialchars($miEntrega['contenido'])) ?></p>
                <?= mostrar_archivo($miEntrega['archivo'] ?? null) ?>
                
                <?php if ($miEntrega['calificacion']): ?>
                    <div style="margin-top:1rem; padding:0.75rem 1rem; border-radius:var(--radius-sm); background:var(--bg-card); border:1px solid var(--border-color);">
                        <div style="font-weight:700; font-size:1.1rem; color:var(--success);">
                            Calificación: <?= htmlspecialchars($miEntrega['calificacion']) ?> / 10
                        </div>
                        <?php if (!empty($miEntrega['feedback'])): ?>
                            <div style="margin-top:0.5rem; font-size:0.92rem; color:var(--text-main);">
                                <strong>💬 Devolución del docente:</strong><br>
                                <?= nl2br(htmlspecialchars($miEntrega['feedback'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-top:1rem; font-size:0.85rem; color:var(--text-muted);">
                        ⏳ Pendiente de revisión por parte del profesor.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h3>Subir Entrega</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="entregar" value="1">
                
                <?php if ($tarea['tipo_entrega'] === 'grupal'): 
                    $sg = $pdo->prepare("SELECT mg.id, mg.nombre FROM minigrupos mg JOIN minigrupos_usuarios mu ON mg.id = mu.minigrupo_id WHERE mu.usuario_id = ? AND mg.curso_id = ?");
                    $sg->execute([$user['id'], $tarea['curso_id']]);
                    $misGrupos = $sg->fetchAll();
                ?>
                    <div class="form-group">
                        <label class="form-label">Selecciona tu grupo:</label>
                        <select name="minigrupo_id" class="form-control" required>
                            <?php foreach ($misGrupos as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Mensaje / Desarrollo del trabajo:</label>
                    <textarea name="contenido" class="form-control" rows="4" required placeholder="Escribe aquí tu respuesta, explicación o detalles de la entrega..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Adjuntar Archivo (PDF, Word, TXT, Imágenes, etc.):</label>
                    <input type="file" name="archivo_entrega" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Entregar Tarea</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Sección de Dudas y Preguntas Públicas -->
    <hr id="comentarios" style="border-color:var(--border-color); margin:2.5rem 0 1.5rem 0;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1.15rem;">💬 Dudas y Consultas de la Tarea</h3>
        <span class="badge" style="background:var(--primary-subtle); color:var(--primary);"><?= count($comentarios) ?> comentarios</span>
    </div>

    <!-- Hilo de comentarios -->
    <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
        <?php if (empty($comentarios)): ?>
            <p style="color:var(--text-muted); font-size:0.9rem;">No hay dudas registradas. ¡Si tienes preguntas sobre la consigna, escribe abajo!</p>
        <?php else: ?>
            <?php foreach ($comentarios as $c): 
                $esDocente = ($c['rol_prioridad'] >= 3);
            ?>
                <div style="padding:0.85rem 1rem; border-radius:var(--radius-sm); background:<?= $esDocente ? 'var(--primary-subtle)' : 'var(--bg-card-alt)' ?>; border:1px solid <?= $esDocente ? 'var(--primary-light)' : 'var(--border-color)' ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                        <span style="font-size:0.85rem; font-weight:700; color:<?= $esDocente ? 'var(--primary)' : 'var(--text-main)' ?>;">
                            <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?>
                            <span class="badge" style="font-size:0.7rem; margin-left:0.3rem;"><?= get_rol_name($c['rol_prioridad']) ?></span>
                        </span>
                        <span style="font-size:0.75rem; color:var(--text-muted);"><?= date('d/m/Y H:i', strtotime($c['fecha'])) ?></span>
                    </div>
                    <div style="font-size:0.9rem; line-height:1.4;"><?= nl2br(htmlspecialchars($c['contenido'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Formulario para publicar duda -->
    <form method="POST" action="tarea.php?id=<?= $tareaId ?>">
        <input type="hidden" name="publicar_comentario" value="1">
        <div class="form-group" style="display:flex; gap:0.5rem; align-items:flex-start;">
            <input type="text" name="comentario" class="form-control" placeholder="Escribe aquí tu duda o respuesta sobre esta tarea..." required>
            <button type="submit" class="btn btn-secondary">Publicar</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
