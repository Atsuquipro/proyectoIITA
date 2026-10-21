<?php
require_once __DIR__ . '/includes/auth.php';
require_priority(3); // Profesores, Vice-directores y Directores

$pdo = get_db();
$priority = user_priority();
$schoolId = current_school_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Añadir Curso (Director)
    if ($action === 'crear_curso' && $priority >= 5) {
        $nombreCurso = ucfirst(strtolower(trim($_POST['nombre_curso'] ?? '')));
        if ($nombreCurso !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO cursos (escuela_id, nombre) VALUES (?, ?)");
                $stmt->execute([$schoolId, $nombreCurso]);
                set_flash('success', "Curso '{$nombreCurso}' creado correctamente.");
            } catch (Exception $e) {
                set_flash('error', "Error al crear curso o ya existe.");
            }
        }
        header('Location: cursos.php'); exit;
    }

    // 2. Eliminar Curso (Director)
    if ($action === 'eliminar_curso' && $priority >= 5) {
        $idCurso = (int)($_POST['id_curso'] ?? 0);
        $pdo->prepare("DELETE FROM cursos WHERE id = ? AND escuela_id = ?")->execute([$idCurso, $schoolId]);
        set_flash('success', "Curso eliminado.");
        header('Location: cursos.php'); exit;
    }

    // 3. Añadir Materia a Curso (Profesor+)
    if ($action === 'añadir_materia' && $priority >= 3) {
        $idCurso = (int)($_POST['curso'] ?? 0);
        $materia = ucfirst(strtolower(trim($_POST['materia'] ?? '')));
        if ($idCurso && $materia) {
            try {
                $stmt = $pdo->prepare("INSERT INTO materias (curso_id, nombre) VALUES (?, ?)");
                $stmt->execute([$idCurso, $materia]);
                set_flash('success', "Materia '{$materia}' agregada.");
            } catch(Exception $e) { set_flash('error', "Error al añadir materia."); }
        }
        header('Location: cursos.php'); exit;
    }

    // 4. Eliminar Materia (Profesor+)
    if ($action === 'eliminar_materia' && $priority >= 3) {
        $idMat = (int)($_POST['materia'] ?? 0);
        $pdo->prepare("DELETE FROM materias WHERE id = ?")->execute([$idMat]);
        set_flash('success', "Materia eliminada.");
        header('Location: cursos.php'); exit;
    }

    // 5. Inscribir Alumno (Vice-director+)
    if ($action === 'inscribir_alumno' && $priority >= 4) {
        $idCurso = (int)($_POST['curso'] ?? 0);
        $idAlumno = (int)($_POST['alumno'] ?? 0);
        if ($idCurso && $idAlumno) {
            $pdo->prepare("UPDATE usuarios SET curso_id = ? WHERE id = ? AND escuela_id = ?")->execute([$idCurso, $idAlumno, $schoolId]);
            set_flash('success', "Alumno inscrito correctamente.");
        }
        header('Location: cursos.php'); exit;
    }

    // 6. Remover Alumno (Vice-director+)
    if ($action === 'remover_alumno' && $priority >= 4) {
        $idAlumno = (int)($_POST['alumno'] ?? 0);
        $pdo->prepare("UPDATE usuarios SET curso_id = NULL WHERE id = ? AND escuela_id = ?")->execute([$idAlumno, $schoolId]);
        set_flash('success', "Alumno removido del curso.");
        header('Location: cursos.php'); exit;
    }

    // 7. Transferir Alumno (Vice-director+)
    if ($action === 'transferir_alumno' && $priority >= 4) {
        $idAlumno = (int)($_POST['alumno'] ?? 0);
        $idCurso = (int)($_POST['nuevo_curso'] ?? 0);
        if ($idCurso && $idAlumno) {
            $pdo->prepare("UPDATE usuarios SET curso_id = ? WHERE id = ? AND escuela_id = ?")->execute([$idCurso, $idAlumno, $schoolId]);
            set_flash('success', "Alumno transferido.");
        }
        header('Location: cursos.php'); exit;
    }
}

// Data fetching
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE escuela_id = ? ORDER BY nombre");
$stmt->execute([$schoolId]);
$cursosRaw = $stmt->fetchAll();

$cursos = [];
foreach ($cursosRaw as $c) {
    $cId = $c['id'];
    $cursos[$cId] = $c;
    $cursos[$cId]['materias'] = [];
    $cursos[$cId]['miembros'] = [];
}

$stmtM = $pdo->prepare("SELECT * FROM materias WHERE curso_id IN (SELECT id FROM cursos WHERE escuela_id = ?) ORDER BY nombre");
$stmtM->execute([$schoolId]);
foreach ($stmtM->fetchAll() as $m) {
    $cursos[$m['curso_id']]['materias'][] = $m;
}

$stmtU = $pdo->prepare("SELECT * FROM usuarios WHERE escuela_id = ? AND rol_prioridad <= 2");
$stmtU->execute([$schoolId]);
$todosAlumnos = $stmtU->fetchAll();

$alumnosSinCurso = [];
$todosLosAlumnosConCurso = [];
foreach ($todosAlumnos as $u) {
    if ($u['curso_id']) {
        if (isset($cursos[$u['curso_id']])) {
            $cursos[$u['curso_id']]['miembros'][] = $u;
            $todosLosAlumnosConCurso[] = $u;
        }
    } else {
        $alumnosSinCurso[] = $u;
    }
}

$pageTitle = 'Gestión de Cursos y Materias';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">📚 Cursos y Materias</h1>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Divisiones Registradas</h2>
    </div>

    <?php if (empty($cursos)): ?>
        <p style="color:var(--text-muted);">No hay ningún curso registrado todavía.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($cursos as $cId => $cData): ?>
                <div class="card" style="margin-bottom:0; background:var(--bg-card-alt);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
                        <h3 style="font-size:1.15rem; color:var(--primary);"><?= htmlspecialchars($cData['nombre']) ?></h3>
                        <?php if ($priority >= 5): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="eliminar_curso">
                                <input type="hidden" name="id_curso" value="<?= $cId ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <strong>Materias (<?= count($cData['materias']) ?>):</strong>
                        <div style="display:flex; gap:0.35rem; flex-wrap:wrap; margin-top:0.3rem;">
                            <?php foreach ($cData['materias'] as $m): ?>
                                <span class="badge" style="background:var(--border-color);">
                                    <?= htmlspecialchars($m['nombre']) ?>
                                    <?php if ($priority >= 3): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="eliminar_materia">
                                            <input type="hidden" name="materia" value="<?= $m['id'] ?>">
                                            <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <strong>Alumnos (<?= count($cData['miembros']) ?>):</strong>
                        <div style="display:flex; gap:0.35rem; flex-wrap:wrap; margin-top:0.3rem;">
                            <?php foreach ($cData['miembros'] as $alum): ?>
                                <span class="badge badge-estudiante">
                                    <?= htmlspecialchars($alum['username']) ?>
                                    <?php if ($priority >= 4): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="remover_alumno">
                                            <input type="hidden" name="alumno" value="<?= $alum['id'] ?>">
                                            <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid-cards">
    <?php if ($priority >= 5): ?>
    <div class="card">
        <h3>➕ Crear Nuevo Curso</h3>
        <form method="POST">
            <input type="hidden" name="action" value="crear_curso">
            <input type="text" name="nombre_curso" class="form-control" required placeholder="Ej: 1°A">
            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem; width:100%;">Crear</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($priority >= 3 && !empty($cursos)): ?>
    <div class="card">
        <h3>📖 Añadir Materia</h3>
        <form method="POST">
            <input type="hidden" name="action" value="añadir_materia">
            <select name="curso" class="form-control" style="margin-bottom:0.5rem;">
                <?php foreach ($cursos as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['nombre'])."</option>"; ?>
            </select>
            <input type="text" name="materia" class="form-control" required placeholder="Materia">
            <button type="submit" class="btn btn-primary" style="margin-top:0.5rem; width:100%;">Añadir</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($priority >= 4 && !empty($cursos)): ?>
    <div class="card">
        <h3>🧑‍🎓 Inscribir Alumno</h3>
        <form method="POST">
            <input type="hidden" name="action" value="inscribir_alumno">
            <select name="curso" class="form-control" style="margin-bottom:0.5rem;">
                <?php foreach ($cursos as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['nombre'])."</option>"; ?>
            </select>
            <select name="alumno" class="form-control" style="margin-bottom:0.5rem;">
                <?php foreach ($alumnosSinCurso as $a) echo "<option value='{$a['id']}'>".htmlspecialchars($a['username'])."</option>"; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="width:100%;">Inscribir</button>
        </form>
    </div>

    <div class="card">
        <h3>🔄 Transferir Alumno</h3>
        <form method="POST">
            <input type="hidden" name="action" value="transferir_alumno">
            <select name="alumno" class="form-control" style="margin-bottom:0.5rem;">
                <?php foreach ($todosLosAlumnosConCurso as $a) echo "<option value='{$a['id']}'>".htmlspecialchars($a['username'])."</option>"; ?>
            </select>
            <select name="nuevo_curso" class="form-control" style="margin-bottom:0.5rem;">
                <?php foreach ($cursos as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['nombre'])."</option>"; ?>
            </select>
            <button type="submit" class="btn btn-success" style="width:100%;">Transferir</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
