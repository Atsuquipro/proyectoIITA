<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/upload.php';
require_priority(2); // Al menos delegado

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();
$priority = user_priority();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cursoId = $_POST['curso_id'] ?? null;
    $opciones = array_filter(array_map('trim', $_POST['opciones'] ?? []));

    if ($cursoId === 'global') $cursoId = null;

    if (empty($titulo)) {
        $error = "El título es obligatorio.";
    } elseif (count($opciones) < 2) {
        $error = "Debes proporcionar al menos 2 opciones.";
    } else {
        // Validación de permisos
        if ($priority == 2) {
            // Delegado: Solo su curso
            if (!$user['curso_id'] || $cursoId != $user['curso_id']) {
                $error = "Como delegado, solo puedes crear encuestas para tu propio curso.";
            }
        } elseif ($priority == 3) {
            // Profesor: Un curso a la vez, no puede ser global
            if ($cursoId === null) {
                $error = "Los profesores no pueden crear encuestas globales.";
            } else {
                // Verificar si ya tiene una encuesta activa en OTRO curso
                $chk = $pdo->prepare("SELECT COUNT(*) FROM encuestas WHERE creador_id = ? AND estado = 'abierta' AND escuela_id = ?");
                $chk->execute([$user['id'], $schoolId]);
                if ($chk->fetchColumn() > 0) {
                    $error = "Ya tienes una encuesta activa. Debes finalizarla o eliminarla antes de crear otra.";
                }
            }
        } elseif ($priority >= 4) {
            // Vicedirector/Director: Puede crear globales o para un curso
            // Todo OK
        }

        if (!$error) {
            $archivo_ruta = procesar_subida_archivo('archivo_encuesta');
            if ($archivo_ruta === false) {
                header('Location: crear_voto.php');
                exit;
            }

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO encuestas (escuela_id, curso_id, titulo, descripcion, creador_id, archivo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$schoolId, $cursoId, $titulo, $descripcion, $user['id'], $archivo_ruta]);
                $encuestaId = $pdo->lastInsertId();

                $optStmt = $pdo->prepare("INSERT INTO opciones (encuesta_id, texto) VALUES (?, ?)");
                foreach ($opciones as $op) {
                    $optStmt->execute([$encuestaId, $op]);
                }
                $pdo->commit();
                
                set_flash('success', 'Encuesta creada correctamente.');
                header('Location: votos.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al crear la encuesta: " . $e->getMessage();
            }
        }
    }
}

// Obtener cursos para el select
$cursos = [];
if ($priority >= 3) {
    $stmtC = $pdo->prepare("SELECT * FROM cursos WHERE escuela_id = ? ORDER BY nombre");
    $stmtC->execute([$schoolId]);
    $cursos = $stmtC->fetchAll();
} else if ($priority == 2 && $user['curso_id']) {
    $stmtC = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmtC->execute([$user['curso_id']]);
    $cursos = $stmtC->fetchAll();
}

$pageTitle = 'Crear Encuesta';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-sm" style="margin-top: 2rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">➕ Crear Nueva Encuesta</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="crear_voto.php" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label" for="titulo">Título de la encuesta</label>
                <input type="text" id="titulo" name="titulo" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="descripcion">Descripción (opcional)</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Adjuntar Archivo o Foto (Opcional)</label>
                <input type="file" name="archivo_encuesta" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label" for="curso_id">Público / Visibilidad</label>
                <select id="curso_id" name="curso_id" class="form-control" required>
                    <?php if ($priority >= 4): ?>
                        <option value="global">Todos los cursos (Global)</option>
                    <?php endif; ?>
                    
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['id'] ?>">Solo curso: <?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($priority == 3): ?>
                    <small style="color:var(--text-muted);">Como profesor, solo puedes tener una encuesta activa a la vez.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Opciones (Mínimo 2)</label>
                <div id="opciones-container">
                    <input type="text" name="opciones[]" class="form-control" style="margin-bottom:0.5rem;" placeholder="Opción 1" required>
                    <input type="text" name="opciones[]" class="form-control" style="margin-bottom:0.5rem;" placeholder="Opción 2" required>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addOpcion()" style="margin-top:0.5rem;">+ Añadir opción</button>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Publicar Encuesta</button>
        </form>
    </div>
</div>

<script>
let optCount = 2;
function addOpcion() {
    optCount++;
    const container = document.getElementById('opciones-container');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'opciones[]';
    input.className = 'form-control';
    input.style.marginBottom = '0.5rem';
    input.placeholder = 'Opción ' + optCount;
    container.appendChild(input);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
