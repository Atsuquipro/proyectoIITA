<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$user = current_user();
$priority = user_priority();
$cursoId = $user['curso_id'];

// Solamente usuarios con curso (o profesores) pueden ver minigrupos. 
// Para simplicidad, haremos que el alumno vea los grupos de su curso.
if (!$cursoId && $priority < 3) {
    set_flash('error', 'Debes estar asignado a un curso para ver los grupos de trabajo.');
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_grupo'])) {
        $nombreGrupo = trim($_POST['nombre_grupo']);
        $integrantes = $_POST['integrantes'] ?? []; // array de IDs de usuarios
        
        if (empty($nombreGrupo)) {
            $error = 'El nombre del grupo es obligatorio.';
        } elseif (empty($integrantes)) {
            $error = 'Debes seleccionar al menos un integrante (tú mismo).';
        } else {
            // Asegurar que el creador esté incluido
            if (!in_array($user['id'], $integrantes)) {
                $integrantes[] = $user['id'];
            }
            
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO minigrupos (curso_id, nombre) VALUES (?, ?)");
                $ins->execute([$cursoId, $nombreGrupo]);
                $grupoId = $pdo->lastInsertId();
                
                $insRel = $pdo->prepare("INSERT INTO minigrupos_usuarios (minigrupo_id, usuario_id) VALUES (?, ?)");
                foreach ($integrantes as $uid) {
                    $insRel->execute([$grupoId, $uid]);
                }
                
                $pdo->commit();
                set_flash('success', 'Grupo creado exitosamente.');
                header('Location: minigrupos.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error al crear el grupo: ' . $e->getMessage();
            }
        }
    }
}

// Obtener compañeros de curso
$stmtC = $pdo->prepare("SELECT id, nombre, apellido, username FROM usuarios WHERE curso_id = ? AND id != ? ORDER BY nombre");
$stmtC->execute([$cursoId, $user['id']]);
$compañeros = $stmtC->fetchAll();

// Obtener grupos del curso
$stmtG = $pdo->prepare("SELECT * FROM minigrupos WHERE curso_id = ? ORDER BY nombre");
$stmtG->execute([$cursoId]);
$grupos = $stmtG->fetchAll();

$pageTitle = 'Grupos de Trabajo';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">👥 Grupos de Trabajo</h1>
    <p style="color:var(--text-muted);">Crea grupos con tus compañeros para entregar tareas de forma conjunta.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div class="grid-cards">
    <!-- Crear nuevo grupo -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom:1rem;">Crear Nuevo Grupo</h3>
        <form method="POST" action="minigrupos.php">
            <input type="hidden" name="crear_grupo" value="1">
            <div class="form-group">
                <label class="form-label" for="nombre_grupo">Nombre del Grupo</label>
                <input type="text" id="nombre_grupo" name="nombre_grupo" class="form-control" required placeholder="Ej: Los Pumas, Grupo A...">
            </div>
            
            <div class="form-group">
                <label class="form-label">Selecciona a tus compañeros:</label>
                <div style="max-height: 160px; overflow-y:auto; background:var(--bg-card-alt); padding:0.5rem; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                    <?php if (empty($compañeros)): ?>
                        <p style="color:var(--text-muted); font-size:0.85rem; padding:0.5rem;">No hay otros alumnos en este curso.</p>
                    <?php else: ?>
                        <?php foreach ($compañeros as $comp): ?>
                            <label class="choice-option" for="comp_<?= $comp['id'] ?>" style="margin-bottom:0.35rem; padding:0.5rem 0.75rem;">
                                <input type="checkbox" id="comp_<?= $comp['id'] ?>" name="integrantes[]" value="<?= $comp['id'] ?>">
                                <span style="font-size:0.875rem; font-weight:500; color:var(--text-main);">
                                    <?= htmlspecialchars($comp['nombre'] . ' ' . $comp['apellido']) ?>
                                    <small style="color:var(--text-muted);">(<?= htmlspecialchars($comp['username']) ?>)</small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Crear Grupo</button>
        </form>
    </div>

    <!-- Grupos existentes -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom:1rem;">Grupos en el Curso</h3>
        <?php if (empty($grupos)): ?>
            <p style="color:var(--text-muted);">No hay grupos creados todavía.</p>
        <?php else: ?>
            <?php foreach ($grupos as $g): 
                $stmtM = $pdo->prepare("SELECT u.nombre, u.apellido FROM usuarios u JOIN minigrupos_usuarios mu ON u.id = mu.usuario_id WHERE mu.minigrupo_id = ?");
                $stmtM->execute([$g['id']]);
                $miembros = $stmtM->fetchAll();
            ?>
                <div style="background:var(--bg-hover); padding:1rem; border-radius:8px; margin-bottom:1rem; border:1px solid var(--border);">
                    <h4 style="color:var(--primary); margin-bottom:0.5rem; font-size:1.1rem;"><?= htmlspecialchars($g['nombre']) ?></h4>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.25rem;">Integrantes:</p>
                    <ul style="font-size:0.9rem; margin-left:1.5rem;">
                        <?php foreach ($miembros as $m): ?>
                            <li><?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
