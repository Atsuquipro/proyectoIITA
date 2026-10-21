<?php
require_once __DIR__ . '/includes/auth.php';
require_priority(3); // Profesores, Vice-directores y Directores

$pdo = get_db();
$schoolId = current_school_id();
$priority = user_priority();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'añadir_nota') {
        $alumno = (int)($_POST['alumno_id'] ?? 0);
        $materia = (int)($_POST['materia_id'] ?? 0);
        $notaRaw = trim($_POST['nota'] ?? '');

        if (!$alumno || !$materia || $notaRaw === '') {
            set_flash('error', 'Completa todos los campos para registrar la nota.');
        } elseif (!is_numeric($notaRaw)) {
            set_flash('error', 'La calificación debe ser un valor numérico.');
        } else {
            $nota = (int)$notaRaw;
            if ($nota < 1 || $nota > 10) {
                set_flash('error', 'La nota debe estar comprendida entre 1 y 10.');
            } else {
                $ins = $pdo->prepare("INSERT INTO notas (materia_id, alumno_id, nota) VALUES (?, ?, ?)");
                $ins->execute([$materia, $alumno, $nota]);
                set_flash('success', "Nota ({$nota}) añadida correctamente.");
            }
        }
        header("Location: notas.php?alumno=" . urlencode($_POST['username']));
        exit;
    }

    if ($action === 'modificar_nota') {
        $notaId = (int)($_POST['nota_id'] ?? 0);
        $notaRaw = trim($_POST['nueva_nota'] ?? '');

        if (!$notaId || $notaRaw === '') {
            set_flash('error', 'Completa todos los campos requeridos.');
        } elseif (!is_numeric($notaRaw)) {
            set_flash('error', 'Ingresa una calificación válida.');
        } else {
            $nuevaNota = (int)$notaRaw;
            if ($nuevaNota < 1 || $nuevaNota > 10) {
                set_flash('error', 'La nueva nota debe estar entre 1 y 10.');
            } else {
                $upd = $pdo->prepare("UPDATE notas SET nota = ? WHERE id = ?");
                $upd->execute([$nuevaNota, $notaId]);
                set_flash('success', "Nota actualizada exitosamente a {$nuevaNota}.");
            }
        }
        header("Location: notas.php?alumno=" . urlencode($_POST['username']));
        exit;
    }

    if ($action === 'eliminar_nota') {
        $notaId = (int)($_POST['nota_id'] ?? 0);
        if ($notaId) {
            $pdo->prepare("DELETE FROM notas WHERE id = ?")->execute([$notaId]);
            set_flash('success', 'Nota eliminada correctamente.');
        }
        header("Location: notas.php?alumno=" . urlencode($_POST['username']));
        exit;
    }
}

// Alumnos con curso para el selector
$stmtU = $pdo->prepare("SELECT u.*, c.nombre as curso_nombre FROM usuarios u JOIN cursos c ON u.curso_id = c.id WHERE u.escuela_id = ? AND u.rol_prioridad <= 2 ORDER BY u.username");
$stmtU->execute([$schoolId]);
$alumnosConCurso = [];
foreach ($stmtU->fetchAll() as $u) {
    $alumnosConCurso[$u['username']] = $u;
}

$alumnoSeleccionado = $_GET['alumno'] ?? (array_key_first($alumnosConCurso) ?? '');
$alumnoData = $alumnosConCurso[$alumnoSeleccionado] ?? null;

$materiasAlumno = [];
$notasPorMateria = [];
if ($alumnoData && !empty($alumnoData['curso_id'])) {
    // Obtener materias del curso
    $stmtM = $pdo->prepare("SELECT * FROM materias WHERE curso_id = ? ORDER BY nombre");
    $stmtM->execute([$alumnoData['curso_id']]);
    foreach ($stmtM->fetchAll() as $m) {
        $materiasAlumno[$m['id']] = $m['nombre'];
        $notasPorMateria[$m['id']] = []; // Initialize empty array of notes
    }

    // Obtener las notas del alumno
    if (!empty($materiasAlumno)) {
        $stmtN = $pdo->prepare("SELECT * FROM notas WHERE alumno_id = ? ORDER BY id ASC");
        $stmtN->execute([$alumnoData['id']]);
        foreach ($stmtN->fetchAll() as $n) {
            if (isset($notasPorMateria[$n['materia_id']])) {
                $notasPorMateria[$n['materia_id']][] = $n;
            }
        }
    }
}

$pageTitle = 'Gestión de Calificaciones';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">📝 Calificaciones Escolares</h1>
    <span class="badge badge-profesor">Módulo de Profesores y Directivos</span>
</div>

<?php if (empty($alumnosConCurso)): ?>
    <div class="card">
        <p style="color:var(--text-muted);">
            No hay alumnos inscritos en ningún curso actualmente. Primero crea un curso e inscribe alumnos en el módulo de <a href="cursos.php" style="color:var(--primary);">Cursos</a>.
        </p>
    </div>
<?php else: ?>

<!-- Selector de Alumno -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" action="notas.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom:0; flex:1; min-width: 250px;">
            <label class="form-label" for="select_alumno">Seleccionar Alumno a Calificar / Evaluar:</label>
            <select id="select_alumno" name="alumno" class="form-control" onchange="this.form.submit()">
                <?php foreach ($alumnosConCurso as $uname => $udata): ?>
                    <option value="<?= htmlspecialchars($uname) ?>" <?= $alumnoSeleccionado === $uname ? 'selected' : '' ?>>
                        <?= htmlspecialchars($uname) ?> &mdash; <?= htmlspecialchars($udata['nombre'] . ' ' . $udata['apellido']) ?> (<?= htmlspecialchars($udata['curso_nombre']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Cargar Alumno</button>
    </form>
</div>

<?php if ($alumnoData): ?>
    <!-- Visualización de Notas y Promedios -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Libreta de: <?= htmlspecialchars($alumnoData['nombre'] . ' ' . $alumnoData['apellido']) ?></h2>
                <span style="font-size: 0.9rem; color:var(--text-muted);">Usuario: <?= htmlspecialchars($alumnoSeleccionado) ?> | Curso: <?= htmlspecialchars($alumnoData['curso_nombre']) ?></span>
            </div>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <span class="badge badge-estudiante"><?= htmlspecialchars($alumnoData['curso_nombre']) ?></span>
                <a href="boletin.php?alumno_id=<?= $alumnoData['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">🖨️ Imprimir Boletín</a>
            </div>
        </div>

        <?php if (empty($materiasAlumno)): ?>
            <p style="color:var(--text-muted);">El curso <?= htmlspecialchars($alumnoData['curso_nombre']) ?> no tiene materias asignadas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Materia</th>
                            <th>Notas Cargadas (con posición)</th>
                            <th style="width: 140px; text-align: center;">Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiasAlumno as $matId => $matNom): 
                            $notas = $notasPorMateria[$matId];
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($matNom) ?></strong></td>
                                <td>
                                    <?php if (empty($notas)): ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Sin notas registradas</span>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                            <?php foreach ($notas as $pos => $n): ?>
                                                <?php
                                                $nVal = (int)$n['nota'];
                                                $nClass = ($nVal >= 7) ? 'nota-alta' : (($nVal >= 4) ? 'nota-media' : 'nota-baja');
                                                ?>
                                                <div style="display:inline-flex; align-items:center; background:var(--bg-card); border:1px solid var(--border-color); border-radius:6px; padding:0.15rem 0.4rem; gap:0.35rem;">
                                                    <small style="color:var(--text-muted); font-size:0.75rem;">#<?= ($pos + 1) ?>:</small>
                                                    <span class="badge-nota <?= $nClass ?>" style="padding:0.1rem 0.35rem;"><?= $nVal ?></span>
                                                    <form method="POST" action="notas.php" style="display:inline; margin:0;">
                                                        <input type="hidden" name="action" value="eliminar_nota">
                                                        <input type="hidden" name="username" value="<?= htmlspecialchars($alumnoSeleccionado) ?>">
                                                        <input type="hidden" name="nota_id" value="<?= $n['id'] ?>">
                                                        <button type="submit" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.75rem; padding:0;" title="Eliminar esta nota" onclick="return confirm('¿Eliminar la nota <?= $nVal ?> de la posición #<?= ($pos + 1) ?>?');">✕</button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if (!empty($notas)): ?>
                                        <?php
                                        $prom = array_sum(array_column($notas, 'nota')) / count($notas);
                                        $pClass = ($prom >= 7) ? 'nota-alta' : (($prom >= 4) ? 'nota-media' : 'nota-baja');
                                        ?>
                                        <span class="badge-nota <?= $pClass ?>" style="font-size: 1rem;">
                                            <?= number_format($prom, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Formularios de Operaciones -->
    <?php if (!empty($materiasAlumno)): ?>
    <div class="grid-cards">
        <!-- 1. Añadir Nota -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">➕ Añadir Nueva Nota</h3>
            </div>
            <form method="POST" action="notas.php">
                <input type="hidden" name="action" value="añadir_nota">
                <input type="hidden" name="username" value="<?= htmlspecialchars($alumnoSeleccionado) ?>">
                <input type="hidden" name="alumno_id" value="<?= $alumnoData['id'] ?>">

                <div class="form-group">
                    <label class="form-label">Materia:</label>
                    <select name="materia_id" class="form-control" required>
                        <?php foreach ($materiasAlumno as $mId => $mNom): ?>
                            <option value="<?= $mId ?>"><?= htmlspecialchars($mNom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nota (1 a 10):</label>
                    <input type="number" name="nota" class="form-control" min="1" max="10" required placeholder="Ej: 8">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Guardar Nota</button>
            </form>
        </div>

        <!-- 2. Modificar Nota Existente -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">✏️ Modificar Nota</h3>
            </div>
            <form method="POST" action="notas.php">
                <input type="hidden" name="action" value="modificar_nota">
                <input type="hidden" name="username" value="<?= htmlspecialchars($alumnoSeleccionado) ?>">

                <div class="form-group">
                    <label class="form-label">Seleccionar Nota a Modificar:</label>
                    <select name="nota_id" class="form-control" required>
                        <?php foreach ($materiasAlumno as $mId => $mNom): 
                            foreach ($notasPorMateria[$mId] as $pos => $n):
                        ?>
                            <option value="<?= $n['id'] ?>"><?= htmlspecialchars($mNom) ?> - Nota #<?= $pos+1 ?> (Valor actual: <?= $n['nota'] ?>)</option>
                        <?php 
                            endforeach;
                        endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nueva Nota (1 a 10):</label>
                    <input type="number" name="nueva_nota" class="form-control" min="1" max="10" required placeholder="Ej: 9">
                </div>

                <button type="submit" class="btn btn-warning btn-sm" style="color:#000;">Actualizar Nota</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
