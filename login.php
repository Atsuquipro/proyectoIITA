<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$successSchool = null;
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
    $username      = trim($_POST['username'] ?? '');
    $password      = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '¡Faltan datos! Completa usuario y contraseña.';
    } else {
        if ($codigoEscuela === '') {
            $targetSchool = get_school_by_id(1);
        } else {
            $targetSchool = get_school_by_code($codigoEscuela);
        }

        if (!$targetSchool) {
            $error = 'La escuela indicada no fue encontrada. Verifica el código.';
        } else {
            $pdo = get_db();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE escuela_id = ? AND username = ?");
            $stmt->execute([$targetSchool['id'], $username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = '¡Los datos no coinciden o el usuario no existe en esta escuela!';
            } else {
                if (verify_user_password($password, $user['password'])) {
                    // Update hash if needed
                    if (!password_get_info($user['password'])['algo']) {
                        $newHash = hash_user_password($password);
                        $upd = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                        $upd->execute([$newHash, $user['id']]);
                    }

                    login_user_by_id($user['id']);
                    
                    $schoolArr = [
                        'id'     => (int)$targetSchool['id'],
                        'nombre' => $targetSchool['nombre'],
                        'codigo' => $targetSchool['codigo']
                    ];
                    $successSchool = $schoolArr;
                    set_flash('success', '¡Bienvenido de nuevo, ' . htmlspecialchars($user['nombre']) . '!');
                } else {
                    $error = '¡Contraseña o usuario incorrectos!';
                }
            }
        }
    }
}

$pageTitle = 'Iniciar Sesión';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-sm" style="margin-top: 2rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔐 Iniciar Sesión</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successSchool): ?>
            <div class="alert alert-success">
                <span>¡Ingreso exitoso! Redirigiendo a tu escuela...</span>
            </div>
            <script>
                try {
                    let misEscuelas = JSON.parse(localStorage.getItem('mis_escuelas') || '[]');
                    const nueva = {
                        id: <?= json_encode($successSchool['id']) ?>,
                        nombre: <?= json_encode($successSchool['nombre']) ?>,
                        codigo: <?= json_encode($successSchool['codigo']) ?>
                    };
                    misEscuelas = misEscuelas.filter(e => e.id !== nueva.id && e.codigo !== nueva.codigo);
                    misEscuelas.unshift(nueva);
                    localStorage.setItem('mis_escuelas', JSON.stringify(misEscuelas));
                    localStorage.setItem('ultima_escuela', JSON.stringify(nueva));
                } catch(e) {}
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 800);
            </script>
        <?php else: ?>
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="codigo_escuela">
                        🏫 Código de Escuela
                    </label>
                    <input type="text" id="codigo_escuela" name="codigo_escuela" class="form-control" 
                           placeholder="Ej: ESC-101" 
                           value="<?= htmlspecialchars($schoolInfo['codigo'] ?? $preselectedCode) ?>" 
                           style="text-transform: uppercase; font-weight:600; letter-spacing:1px;">
                    <div id="schoolNameNotice" style="<?= $schoolInfo ? '' : 'display:none;' ?> font-size:0.85rem; color:var(--primary); margin-top:0.35rem; font-weight:600;">
                        📍 Conectando a: <span id="schoolNameText"><?= htmlspecialchars($schoolInfo['nombre'] ?? '') ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Usuario</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus placeholder="Nombre de usuario" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Tu contraseña">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                    Ingresar al Establecimiento
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
                ¿No tienes una cuenta? <a href="register.php" style="color: var(--primary); font-weight: 600;">Regístrate aquí</a>
                <br><br>
                ¿Quieres crear una nueva escuela? <a href="crear_escuela.php" style="color: var(--primary); font-weight: 600;">Crear escuela aquí</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN INTERACTIVA: MIS ESCUELAS (RECORDADAS EN CACHÉ) -->
    <div class="card" id="misEscuelasCard" style="margin-top: 1.5rem; display: none;">
        <div class="card-header">
            <h3 class="card-title" style="font-size: 1.1rem;">🏫 Tus Escuelas Recientes</h3>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">
            Haz clic en cualquiera de tus escuelas guardadas en este equipo para seleccionarla rápidamente:
        </p>

        <div id="escuelasGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem;">
            <!-- Renderizado dinámico desde localStorage -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('codigo_escuela');
    const notice = document.getElementById('schoolNameNotice');
    const nameText = document.getElementById('schoolNameText');
    const escuelasCard = document.getElementById('misEscuelasCard');
    const grid = document.getElementById('escuelasGrid');

    // Cargar escuelas guardadas en localStorage
    let misEscuelas = [];
    try {
        misEscuelas = JSON.parse(localStorage.getItem('mis_escuelas') || '[]');
    } catch(e) {}

    // Si el campo de código está vacío, usar la última escuela
    if (codeInput && !codeInput.value && misEscuelas.length > 0) {
        const ultima = misEscuelas[0];
        codeInput.value = ultima.codigo;
        if (nameText && notice) {
            nameText.textContent = ultima.nombre;
            notice.style.display = 'block';
        }
    }

    // Renderizar tarjetas de escuelas
    if (misEscuelas.length > 0 && grid && escuelasCard) {
        escuelasCard.style.display = 'block';
        grid.innerHTML = '';

        misEscuelas.forEach(esc => {
            const card = document.createElement('div');
            card.style.cssText = 'padding: 0.85rem; background: var(--bg-hover, rgba(255,255,255,0.03)); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; transition: 0.2s; display:flex; flex-direction:column; justify-content:space-between; gap:0.5rem;';
            
            const isSelected = codeInput.value.toUpperCase() === esc.codigo.toUpperCase();
            if (isSelected) {
                card.style.borderColor = 'var(--primary)';
                card.style.background = 'var(--primary-light, rgba(59, 130, 246, 0.1))';
            }

            card.innerHTML = `
                <div style="font-weight: 600; font-size: 0.95rem; line-height: 1.3;">${escapeHtml(esc.nombre)}</div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="badge" style="font-size:0.75rem;">${escapeHtml(esc.codigo)}</span>
                    <span style="font-size:0.8rem; color:var(--primary); font-weight:600;">Elegir →</span>
                </div>
            `;

            card.addEventListener('mouseenter', () => {
                if (codeInput.value.toUpperCase() !== esc.codigo.toUpperCase()) {
                    card.style.borderColor = 'var(--primary)';
                }
            });
            card.addEventListener('mouseleave', () => {
                if (codeInput.value.toUpperCase() !== esc.codigo.toUpperCase()) {
                    card.style.borderColor = 'var(--border)';
                }
            });

            card.addEventListener('click', () => {
                codeInput.value = esc.codigo;
                if (nameText && notice) {
                    nameText.textContent = esc.nombre;
                    notice.style.display = 'block';
                }
                // Actualizar estilo activo
                Array.from(grid.children).forEach(c => {
                    c.style.borderColor = 'var(--border)';
                    c.style.background = 'var(--bg-hover, rgba(255,255,255,0.03))';
                });
                card.style.borderColor = 'var(--primary)';
                card.style.background = 'var(--primary-light, rgba(59, 130, 246, 0.1))';
                document.getElementById('username')?.focus();
            });

            grid.appendChild(card);
        });
    }

    // Al tipear código manualmente, buscar si coincide
    let codeTimeout = null;
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            clearTimeout(codeTimeout);
            codeTimeout = setTimeout(() => {
                const val = codeInput.value.trim();
                if (val.length >= 3) {
                    fetch('api_escuelas.php?action=check&code=' + encodeURIComponent(val))
                        .then(r => r.json())
                        .then(d => {
                            if (d.success && d.school) {
                                nameText.textContent = d.school.nombre;
                                notice.style.display = 'block';
                            } else {
                                notice.style.display = 'none';
                            }
                        })
                        .catch(() => {});
                } else {
                    notice.style.display = 'none';
                }
            }, 300);
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