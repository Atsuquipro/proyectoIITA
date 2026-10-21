<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreEscuela = trim($_POST['nombre_escuela'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $apellido      = trim($_POST['apellido'] ?? '');
    $username      = ucfirst(strtolower(trim($_POST['username'] ?? '')));
    $pass1         = trim($_POST['password'] ?? '');
    $pass2         = trim($_POST['password2'] ?? '');

    if ($nombreEscuela === '' || $nombre === '' || $apellido === '' || $username === '' || $pass1 === '' || $pass2 === '') {
        $error = 'Por favor, completa todos los campos del formulario.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($nombreEscuela) < 3) {
        $error = 'El nombre de la escuela debe tener al menos 3 caracteres.';
    } else {
        $directorData = [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'password' => $pass1,
            'curso'    => null,
            'rol'      => ROLES[4] // Director/a (prioridad 5)
        ];

        $newSchool = create_school($nombreEscuela, $username, $directorData);
        if ($newSchool) {
            login_user($username, $directorData, $newSchool);
            $successData = [
                'id'     => $newSchool['id'],
                'nombre' => $newSchool['nombre'],
                'codigo' => $newSchool['codigo']
            ];
            set_flash('success', "¡Escuela '{$newSchool['nombre']}' creada con éxito! Tu código es: {$newSchool['codigo']}. Compártelo con tus profesores y alumnos.");
        } else {
            $error = 'Ocurrió un error al intentar crear la escuela. Inténtalo nuevamente.';
        }
    }
}

$pageTitle = 'Registrar Nueva Escuela';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-sm" style="margin-top: 2rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏫 Registrar Nueva Escuela</h2>
        </div>
        <p style="color:var(--text-muted); margin-bottom: 1.5rem; font-size:0.95rem;">
            Crea el espacio digital de tu institución. El usuario que registres aquí asumirá el rol de <strong>Director/a</strong> con control total.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successData): ?>
            <div class="alert alert-success">
                <span>¡Escuela creada correctamente! Redirigiendo al panel...</span>
            </div>
            <script>
                // Guardar la nueva escuela en el localStorage del navegador
                try {
                    let misEscuelas = JSON.parse(localStorage.getItem('mis_escuelas') || '[]');
                    const nueva = {
                        id: <?= json_encode($successData['id']) ?>,
                        nombre: <?= json_encode($successData['nombre']) ?>,
                        codigo: <?= json_encode($successData['codigo']) ?>
                    };
                    // Evitar duplicados
                    misEscuelas = misEscuelas.filter(e => e.id !== nueva.id && e.codigo !== nueva.codigo);
                    misEscuelas.unshift(nueva);
                    localStorage.setItem('mis_escuelas', JSON.stringify(misEscuelas));
                    localStorage.setItem('ultima_escuela', JSON.stringify(nueva));
                } catch(e) {}
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1200);
            </script>
        <?php else: ?>
            <form method="POST" action="crear_escuela.php">
                <div style="background: var(--bg-hover, rgba(255,255,255,0.03)); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                    <h3 style="margin-top:0; font-size:1.05rem; margin-bottom: 0.75rem; color:var(--primary);">📌 Datos del Establecimiento</h3>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="nombre_escuela">Nombre de la Escuela o Institución</label>
                        <input type="text" id="nombre_escuela" name="nombre_escuela" required placeholder="Ej: Escuela Técnica N° 1 'Libertador'" value="<?= htmlspecialchars($_POST['nombre_escuela'] ?? '') ?>">
                        <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                            ℹ️ Toda escuela comienza como privada. Podrás publicarla en el buscador general cuando sumes al menos 5 alumnos.
                        </small>
                    </div>
                </div>

                <div style="background: var(--bg-hover, rgba(255,255,255,0.03)); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                    <h3 style="margin-top:0; font-size:1.05rem; margin-bottom: 0.75rem; color:var(--primary);">👑 Cuenta del Director/a</h3>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Tu nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="apellido">Apellido</label>
                        <input type="text" id="apellido" name="apellido" required placeholder="Tu apellido" value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="username">Nombre de Usuario (para iniciar sesión)</label>
                        <input type="text" id="username" name="username" required placeholder="Ej: JuanPerez" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required placeholder="Mínimo 4 caracteres">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="password2">Confirmar Contraseña</label>
                        <input type="password" id="password2" name="password2" required placeholder="Repite tu contraseña">
                    </div>
                </div>

                <div style="display:flex; gap:1rem; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">🚀 Crear y Fundar Escuela</button>
                    <a href="login.php" class="btn btn-secondary">Volver al Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
