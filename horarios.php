<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();
$priority = user_priority();

// Procesar nuevo bloque de horario (Profesores / Directivos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_horario']) && $priority >= 3) {
    $cId = (int)$_POST['curso_id'];
    $mId = (int)$_POST['materia_id'];
    $dia = $_POST['dia'];
    $hInicio = $_POST['hora_inicio'];
    $hFin = $_POST['hora_fin'];

    if ($cId && $mId && $dia && $hInicio && $hFin) {
        $ins = $pdo->prepare("INSERT INTO horarios (escuela_id, curso_id, materia_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$schoolId, $cId, $mId, $dia, $hInicio, $hFin]);
        set_flash('success', 'Materia añadida al horario correctamente.');
    } else {
        set_flash('error', 'Completa todos los campos del horario.');
    }
    header("Location: horarios.php?curso_id=" . $cId);
    exit;
}

// Eliminar bloque de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_horario']) && $priority >= 3) {
    $hId = (int)$_POST['horario_id'];
    $cId = (int)$_POST['curso_id'];
    $pdo->prepare("DELETE FROM horarios WHERE id = ? AND escuela_id = ?")->execute([$hId, $schoolId]);
    set_flash('success', 'Bloque de horario eliminado.');
    header("Location: horarios.php?curso_id=" . $cId);
    exit;
}

// Cursos disponibles
$stmtCursos = $pdo->prepare("SELECT * FROM cursos WHERE escuela_id = ? ORDER BY nombre");
$stmtCursos->execute([$schoolId]);
$cursos = $stmtCursos->fetchAll();

// Determinar curso seleccionado
$cursoSeleccionadoId = null;
if ($priority >= 3) {
    $cursoSeleccionadoId = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : ($cursos[0]['id'] ?? null);
} else {
    $cursoSeleccionadoId = $user['curso_id'];
}

// Obtener datos del curso seleccionado
$cursoData = null;
$materiasCurso = [];
$horariosPorDia = [
    'Lunes' => [],
    'Martes' => [],
    'Miércoles' => [],
    'Jueves' => [],
    'Viernes' => []
];

if ($cursoSeleccionadoId) {
    $stmtCur = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND escuela_id = ?");
    $stmtCur->execute([$cursoSeleccionadoId, $schoolId]);
    $cursoData = $stmtCur->fetch();

    if ($cursoData) {
        // Materias de este curso
        $stmtMat = $pdo->prepare("SELECT * FROM materias WHERE curso_id = ? ORDER BY nombre");
        $stmtMat->execute([$cursoSeleccionadoId]);
        $materiasCurso = $stmtMat->fetchAll();

        // Horarios de este curso
        $stmtH = $pdo->prepare("SELECT h.*, m.nombre as materia_nombre FROM horarios h JOIN materias m ON h.materia_id = m.id WHERE h.curso_id = ? ORDER BY h.hora_inicio ASC");
        $stmtH->execute([$cursoSeleccionadoId]);
        $todosHorarios = $stmtH->fetchAll();

        foreach ($todosHorarios as $h) {
            if (isset($horariosPorDia[$h['dia']])) {
                $horariosPorDia[$h['dia']][] = $h;
            }
        }
    }
}

$pageTitle = 'Horarios de Clases';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size: 1.6rem;">📅 Cronograma y Horarios de Clase</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Consulta la distribución semanal de materias por curso.</p>
    </div>
</div>

<?php if ($priority >= 3 && !empty($cursos)): ?>
    <!-- Selector de Curso para Docentes -->
    <div class="card" style="margin-bottom:1.5rem;">
        <form method="GET" action="horarios.php" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:240px;">
                <label class="form-label">Seleccionar Curso / División:</label>
                <select name="curso_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cursoSeleccionadoId == $c['id'] ? 'selected' : '' ?>>
                            Curso: <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Ver Horarios</button>
        </form>
    </div>
<?php endif; ?>

<?php if (!$cursoData): ?>
    <div class="card">
        <p style="color:var(--text-muted);">No estás asignado a ningún curso y no hay horarios para mostrar.</p>
    </div>
<?php else: ?>

    <!-- Cuadrícula Semanal de Horarios -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem;">
        <?php foreach ($horariosPorDia as $diaNombre => $bloques): ?>
            <div class="card" style="padding:1.15rem; margin-bottom:0; display:flex; flex-direction:column;">
                <div style="border-bottom:2px solid var(--primary); padding-bottom:0.5rem; margin-bottom:0.75rem; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:1.1rem; color:var(--text-main); font-weight:700;"><?= $diaNombre ?></h3>
                    <span class="badge" style="background:var(--primary-subtle); color:var(--primary); font-size:0.75rem;"><?= count($bloques) ?> clases</span>
                </div>

                <?php if (empty($bloques)): ?>
                    <p style="color:var(--text-muted); font-size:0.85rem; font-style:italic; margin-top:0.5rem;">Sin clases programadas</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:0.6rem;">
                        <?php foreach ($bloques as $b): ?>
                            <div style="background:var(--bg-card-alt); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:0.65rem 0.85rem;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <strong style="color:var(--primary); font-size:0.95rem;"><?= htmlspecialchars($b['materia_nombre']) ?></strong>
                                    <?php if ($priority >= 3): ?>
                                        <form method="POST" action="horarios.php" style="margin:0;">
                                            <input type="hidden" name="eliminar_horario" value="1">
                                            <input type="hidden" name="horario_id" value="<?= $b['id'] ?>">
                                            <input type="hidden" name="curso_id" value="<?= $cursoSeleccionadoId ?>">
                                            <button type="submit" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.8rem; padding:0;" onclick="return confirm('¿Eliminar este bloque horario?');">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                    🕒 <?= date('H:i', strtotime($b['hora_inicio'])) ?> - <?= date('H:i', strtotime($b['hora_fin'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Formulario para Añadir Horarios (Docentes y Directivos) -->
    <?php if ($priority >= 3 && !empty($materiasCurso)): ?>
        <div class="card">
            <h3 class="card-title" style="margin-bottom:1rem;">➕ Añadir Materia al Horario de <?= htmlspecialchars($cursoData['nombre']) ?></h3>
            <form method="POST" action="horarios.php">
                <input type="hidden" name="crear_horario" value="1">
                <input type="hidden" name="curso_id" value="<?= $cursoSeleccionadoId ?>">

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Materia:</label>
                        <select name="materia_id" class="form-control" required>
                            <?php foreach ($materiasCurso as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Día de la Semana:</label>
                        <select name="dia" class="form-control" required>
                            <option value="Lunes">Lunes</option>
                            <option value="Martes">Martes</option>
                            <option value="Miércoles">Miércoles</option>
                            <option value="Jueves">Jueves</option>
                            <option value="Viernes">Viernes</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hora Inicio:</label>
                        <input type="time" name="hora_inicio" class="form-control" required value="08:00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hora Fin:</label>
                        <input type="time" name="hora_fin" class="form-control" required value="09:20">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Guardar Bloque Horario</button>
            </form>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
