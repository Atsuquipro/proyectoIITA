<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$successData = null;
$preselectedCode = trim($_GET['escuela'] ?? ($_POST['codigo_escuela'] ?? ''));
$schoolInfo = null;

if ($preselectedCode !== '') {
    $found = get_school_by_code($preselectedCode);
    if ($found) {
        $schoolInfo = [
            'id'     => (int)$found['id'],
            'nombre' => $found['nombre'],
            'codigo' => $found['codigo']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoEscuela = trim($_POST['codigo_escuela'] ?? '');
    $username      = ucfirst(strtolower(trim($_POST['username'] ?? '')));
    $nombre        = trim($_POST['nombre'] ?? '');
    $apellido      = trim($_POST['apellido'] ?? '');
    $pass1         = trim($_POST['password'] ?? '');
    $pass2         = trim($_POST['password2'] ?? '');

    if ($codigoEscuela === '' || $username === '' || $nombre === '' || $apellido === '' || $pass1 === '' || $pass2 === '') {
        $error = 'Por favor, completa todos los campos del formulario.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $targetSchool = get_school_by_code($codigoEscuela);
        if (!$targetSchool) {
            $error = 'La escuela indicada no existe. Verifica el código o utiliza el buscador.';
        } else {
            $sys = get_system_data((int)$targetSchool['id']);
            if (isset($sys['userData'][$username])) {
                $error = 'Ese nombre de usuario ya está en uso en esta escuela. Por favor, elige otro.';
            } else {
                $sys['userData'][$username] = [
                    'nombre'   => $nombre,
                    'apellido' => $apellido,
                    'password' => $pass1,
                    'curso'    => null,
                    'rol'      => ROLES[0] // Estudiante por defecto
                ];
                save_system_data($sys['data'], $sys['userData'], (int)$targetSchool['id']);

                $schoolArr = [
                    'id'     => (int)$targetSchool['id'],
                    'nombre' => $targetSchool['nombre'],
                    'codigo' => $targetSchool['codigo']
                ];
                login_user($username, $sys['userData'][$username], $schoolArr);

                $successData = $schoolArr;
                set_flash('success', "¡Cuenta creada con éxito en '{$targetSchool['nombre']}'! Bienvenido al sistema.");
            }
        }
    }
}

$pageTitle = 'Crear Cuenta';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-sm" style="margin-top: 2rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📝 Registro de Usuario</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successData): ?>
            <div class="alert alert-success">
                <span>¡Registro completado! Redirigiendo al panel...</span>
            </div>
            <script>
                try {
                    let misEscuelas = JSON.parse(localStorage.getItem('mis_escuelas') || '[]');
                    const nueva = {
                        id: <?= json_encode($successData['id']) ?>,
                        nombre: <?= json_encode($successData['nombre']) ?>,
                        codigo: <?= json_encode($successData['codigo']) ?>
                    };
                    misEscuelas = misEscuelas.filter(e => e.id !== nueva.id && e.codigo !== nueva.codigo);
                    misEscuelas.unshift(nueva);
                    localStorage.setItem('mis_escuelas', JSON.stringify(misEscuelas));
                    localStorage.setItem('ultima_escuela', JSON.stringify(nueva));
                } catch(e) {}
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1000);
            </script>
        <?php else: ?>
            <form method="POST" action="register.php" id="registerForm">
                <!-- SELECCIÓN DE ESCUELA -->
                <div style="background: var(--bg-hover, rgba(255,255,255,0.03)); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                        <label style="font-weight: 600; font-size:0.95rem; margin:0;">🏫 Escuela a la que perteneces</label>
                        <button type="button" id="toggleSchoolSearchBtn" class="btn btn-secondary btn-sm" style="font-size:0.8rem;">
                            🔍 Buscar pública
                        </button>
                    </div>

                    <!-- Entrada directa por código -->
                    <div id="schoolCodeSection">
                        <div class="form-group" style="margin-bottom:0.5rem;">
                            <input type="text" id="codigo_escuela" name="codigo_escuela" class="form-control" 
                                   required placeholder="Ej: ESC-101" 
                                   value="<?= htmlspecialchars($schoolInfo['codigo'] ?? $preselectedCode) ?>" 
                                   style="text-transform: uppercase; font-weight:600; letter-spacing:1px;">
                        </div>
                        <div id="schoolValidatedBadge" style="<?= $schoolInfo ? '' : 'display:none;' ?> font-size:0.85rem; color:var(--success); font-weight:600;">
                            ✓ Escuela seleccionada: <span id="schoolValidatedName"><?= htmlspecialchars($schoolInfo['nombre'] ?? '') ?></span>
                        </div>
                        <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                            Ingresa el código que te proporcionó tu preceptor/director o busca en las escuelas públicas.
                        </small>
                    </div>

                    <!-- Buscador predictivo en vivo de escuelas públicas -->
                    <div id="schoolSearchSection" style="display: none; margin-top: 0.75rem;">
                        <input type="text" id="schoolSearchInput" class="form-control" 
                               placeholder="Escribe el nombre de tu escuela (ej: técnica, 3036, sarmiento)...">
                        
                        <div id="schoolSearchResults" style="margin-top: 0.6rem; max-height: 180px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.4rem;">
                            <div style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding: 0.5rem;">
                                Escribe para buscar escuelas públicas disponibles...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DATOS DEL USUARIO -->
                <div class="form-group">
                    <label class="form-label" for="username">Nombre de usuario</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Nombre de usuario deseado" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required placeholder="Tu nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" class="form-control" required placeholder="Tu apellido" value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password2">Repetir Contraseña</label>
                    <input type="password" id="password2" name="password2" class="form-control" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                    Completar Registro
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                ¿Ya tienes una cuenta? <a href="login.php" style="color: var(--primary); font-weight: 600;">Inicia sesión</a>
                <br><br>
                ¿Quieres registrar una nueva escuela? <a href="crear_escuela.php" style="color: var(--primary); font-weight: 600;">Crear escuela aquí</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleSchoolSearchBtn');
    const searchSection = document.getElementById('schoolSearchSection');
    const searchInput = document.getElementById('schoolSearchInput');
    const resultsContainer = document.getElementById('schoolSearchResults');
    const codeInput = document.getElementById('codigo_escuela');
    const badge = document.getElementById('schoolValidatedBadge');
    const badgeName = document.getElementById('schoolValidatedName');

    // Cargar última escuela de localStorage si el campo está vacío
    if (codeInput && !codeInput.value) {
        try {
            const ultima = JSON.parse(localStorage.getItem('ultima_escuela') || 'null');
            if (ultima && ultima.codigo) {
                codeInput.value = ultima.codigo;
                if (badge && badgeName) {
                    badgeName.textContent = ultima.nombre;
                    badge.style.display = 'block';
                }
            }
        } catch(e) {}
    }

    if (toggleBtn && searchSection) {
        toggleBtn.addEventListener('click', function() {
            const isVisible = searchSection.style.display !== 'none';
            searchSection.style.display = isVisible ? 'none' : 'block';
            toggleBtn.textContent = isVisible ? '🔍 Buscar pública' : '✕ Cerrar buscador';
            if (!isVisible) {
                searchInput.focus();
                searchSchools('');
            }
        });
    }

    let searchTimeout = null;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchSchools(searchInput.value.trim());
            }, 250);
        });
    }

    function searchSchools(query) {
        fetch('api_escuelas.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                resultsContainer.innerHTML = '';
                if (!data.schools || data.schools.length === 0) {
                    resultsContainer.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem; padding:0.5rem; text-align:center;">No se encontraron escuelas públicas coincidentes.</div>';
                    return;
                }

                data.schools.forEach(school => {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 0.6rem 0.8rem; background: var(--card-bg, #1e293b); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.2s;';
                    item.innerHTML = `<div><strong>${escapeHtml(school.nombre)}</strong></div><span class="badge" style="font-size:0.75rem;">${escapeHtml(school.codigo)}</span>`;
                    
                    item.addEventListener('mouseenter', () => item.style.borderColor = 'var(--primary)');
                    item.addEventListener('mouseleave', () => item.style.borderColor = 'var(--border)');

                    item.addEventListener('click', function() {
                        codeInput.value = school.codigo;
                        badgeName.textContent = school.nombre;
                        badge.style.display = 'block';
                        searchSection.style.display = 'none';
                        toggleBtn.textContent = '🔍 Buscar pública';
                    });

                    resultsContainer.appendChild(item);
                });
            })
            .catch(() => {
                resultsContainer.innerHTML = '<div style="color:var(--danger); font-size:0.85rem; padding:0.5rem; text-align:center;">Error al conectar con el buscador.</div>';
            });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

