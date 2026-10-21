<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
require_login();

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();
$priority = user_priority();
$userCursoId = $user['curso_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'votar') {
        $encuestaId = (int)$_POST['encuesta_id'];
        $opcionId = (int)$_POST['opcion_id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO votos (encuesta_id, opcion_id, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$encuestaId, $opcionId, $user['id']]);
            set_flash('success', 'Tu voto ha sido registrado.');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                set_flash('error', 'Ya has votado en esta encuesta.');
            } else {
                set_flash('error', 'Ocurrió un error al registrar tu voto.');
            }
        }
        header('Location: votos.php');
        exit;
    }

    if ($_POST['action'] === 'eliminar_encuesta') {
        $encuestaId = (int)$_POST['encuesta_id'];
        $stmtC = $pdo->prepare("SELECT e.creador_id, u.rol_prioridad as creador_prioridad FROM encuestas e JOIN usuarios u ON e.creador_id = u.id WHERE e.id = ?");
        $stmtC->execute([$encuestaId]);
        $creador = $stmtC->fetch();
        
        if ($creador) {
            if ($user['id'] == $creador['creador_id'] || $priority > $creador['creador_prioridad']) {
                $pdo->prepare("DELETE FROM encuestas WHERE id = ?")->execute([$encuestaId]);
                set_flash('success', 'Encuesta eliminada correctamente.');
            } else {
                set_flash('error', 'No tienes permisos para eliminar esta encuesta.');
            }
        }
        header('Location: votos.php');
        exit;
    }
}

// Fetch polls
if ($priority >= 3) {
    // Teachers+ can see all polls in the school
    $stmt = $pdo->prepare("SELECT e.*, c.nombre as curso_nombre, u.nombre as creador_nombre, u.rol_prioridad as creador_prioridad FROM encuestas e LEFT JOIN cursos c ON e.curso_id = c.id JOIN usuarios u ON e.creador_id = u.id WHERE e.escuela_id = ? ORDER BY e.fecha_creacion DESC");
    $stmt->execute([$schoolId]);
} else {
    // Students/delegados see global polls (curso_id IS NULL) and polls for their course
    $stmt = $pdo->prepare("SELECT e.*, c.nombre as curso_nombre, u.nombre as creador_nombre, u.rol_prioridad as creador_prioridad FROM encuestas e LEFT JOIN cursos c ON e.curso_id = c.id JOIN usuarios u ON e.creador_id = u.id WHERE e.escuela_id = ? AND (e.curso_id IS NULL OR e.curso_id = ?) ORDER BY e.fecha_creacion DESC");
    $stmt->execute([$schoolId, $userCursoId]);
}
$encuestas = $stmt->fetchAll();

$pageTitle = 'Votaciones';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0; display:flex; justify-content:space-between; align-items:center;">
    <h1 style="font-size: 1.6rem;">🗳️ Encuestas y Votaciones</h1>
    <?php if ($priority >= 2): // Delegados en adelante pueden crear ?>
        <a href="crear_voto.php" class="btn btn-primary">➕ Crear Encuesta</a>
    <?php endif; ?>
</div>

<div class="grid-cards">
    <?php if (empty($encuestas)): ?>
        <p style="color:var(--text-muted);">No hay encuestas disponibles en este momento.</p>
    <?php else: ?>
        <?php foreach ($encuestas as $enc): 
            // Check if user voted
            $vStmt = $pdo->prepare("SELECT opcion_id FROM votos WHERE encuesta_id = ? AND usuario_id = ?");
            $vStmt->execute([$enc['id'], $user['id']]);
            $userVoto = $vStmt->fetchColumn();
            
            // Get options and results
            $oStmt = $pdo->prepare("SELECT o.*, (SELECT COUNT(*) FROM votos v WHERE v.opcion_id = o.id) as votos_count FROM opciones o WHERE o.encuesta_id = ?");
            $oStmt->execute([$enc['id']]);
            $opciones = $oStmt->fetchAll();
            
            $totalVotos = array_sum(array_column($opciones, 'votos_count'));
            
            $puedeEliminar = ($user['id'] == $enc['creador_id'] || $priority > $enc['creador_prioridad']);
        ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <h3 style="font-size: 1.25rem; color: var(--primary);"><?= htmlspecialchars($enc['titulo']) ?></h3>
                    <div>
                        <?php if ($enc['curso_id']): ?>
                            <span class="badge" style="background:var(--primary); color:white;">Curso: <?= htmlspecialchars($enc['curso_nombre']) ?></span>
                        <?php else: ?>
                            <span class="badge badge-director">Global</span>
                        <?php endif; ?>
                        
                        <?php if ($puedeEliminar): ?>
                            <form method="POST" action="votos.php" style="display:inline; margin-left:0.5rem;">
                                <input type="hidden" name="action" value="eliminar_encuesta">
                                <input type="hidden" name="encuesta_id" value="<?= $enc['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta encuesta?');">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">Creada por: <?= htmlspecialchars($enc['creador_nombre']) ?></p>
                
                <p style="margin-bottom: 1rem;"><?= nl2br(htmlspecialchars($enc['descripcion'] ?? '')) ?></p>
                
                <?= mostrar_archivo($enc['archivo'] ?? null) ?>
                
                <?php if ($userVoto || $enc['estado'] === 'cerrada' || $priority >= 3): ?>
                    <!-- Show results -->
                    <div style="margin-bottom: 1rem;">
                        <h4 style="font-size:0.95rem; margin-bottom:0.5rem;">Resultados (Total: <?= $totalVotos ?> votos)</h4>
                        <?php foreach ($opciones as $op): 
                            $porcentaje = $totalVotos > 0 ? round(($op['votos_count'] / $totalVotos) * 100) : 0;
                        ?>
                            <div style="margin-bottom:0.5rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.25rem;">
                                    <span><?= htmlspecialchars($op['texto']) ?> <?= $userVoto == $op['id'] ? '(Tu voto)' : '' ?></span>
                                    <span><?= $op['votos_count'] ?> (<?= $porcentaje ?>%)</span>
                                </div>
                                <div style="width:100%; background:var(--bg-hover); border-radius:4px; height:8px; overflow:hidden;">
                                    <div style="width:<?= $porcentaje ?>%; background:var(--primary); height:100%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Show voting form -->
                    <form method="POST" action="votos.php">
                        <input type="hidden" name="action" value="votar">
                        <input type="hidden" name="encuesta_id" value="<?= $enc['id'] ?>">
                        <?php foreach ($opciones as $op): ?>
                            <label class="choice-option" for="op_<?= $op['id'] ?>">
                                <input type="radio" id="op_<?= $op['id'] ?>" name="opcion_id" value="<?= $op['id'] ?>" required>
                                <span style="font-size:0.925rem; font-weight:500; color:var(--text-main);"><?= htmlspecialchars($op['texto']) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.75rem; width:100%;">Votar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
