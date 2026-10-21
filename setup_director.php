<?php
require_once __DIR__ . '/includes/auth.php';

if (director_exists()) {
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = ucfirst(strtolower(trim($_POST['username'] ?? '')));
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $pass1    = trim($_POST['password'] ?? '');
    $pass2    = trim($_POST['password2'] ?? '');

    if ($username === '' || $nombre === '' || $apellido === '' || $pass1 === '' || $pass2 === '') {
        $error = 'Por favor, completa todos los campos.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $sys = get_system_data();
        if (isset($sys['userData'][$username])) {
            $error = 'Ese nombre de usuario ya existe, elige uno diferente.';
        } else {
            $sys['userData'][$username] = [
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'password' => $pass1,
                'curso'    => null,
                'rol'      => ROLES[4] // director/a
            ];
            $sys['data']['directorExist'] = true;
            save_system_data($sys['data'], $sys['userData']);

            login_user($username, $sys['userData'][$username]);
            set_flash('success', '¡Cuenta de Director configurada correctamente!');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Configuración Inicial - Director';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-sm" style="margin-top: 2rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">👑 Configuración del Director</h2>
        </div>
        <p style="color:var(--text-muted); margin-bottom: 1.25rem;">
            El sistema necesita registrar al Director inicial antes de poder ser utilizado.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="setup_director.php">
            <div class="form-group">
                <label class="form-label" for="username">Nombre de usuario (Login)</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Ej: Admin o Director">
            </div>

            <div class="form-group">
                <label class="form-label" for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required placeholder="Nombre">
            </div>

            <div class="form-group">
                <label class="form-label" for="apellido">Apellido</label>
                <input type="text" id="apellido" name="apellido" class="form-control" required placeholder="Apellido">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="form-label" for="password2">Repetir Contraseña</label>
                <input type="password" id="password2" name="password2" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Crear Cuenta de Director
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
