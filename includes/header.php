<?php
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$username = current_username();
$priority = user_priority();
$flash = get_flash();
$currentPage = basename($_SERVER['PHP_SELF']);

$schoolName = current_school_name();
$schoolCode = current_school_code();

function get_rol_name($prio) {
    switch ($prio) {
        case 5: return 'director/a';
        case 4: return 'vice-director/a';
        case 3: return 'profesor/a';
        case 2: return 'delegado/a';
        default: return 'estudiante';
    }
}
$rolNombre = $currentUser ? get_rol_name($priority) : '';

// Calcular alertas y pendientes
$tareasPendientesCount = 0;
$mensajesNoLeidosCount = 0;
if ($currentUser) {
    $pdo = get_db();
    
    // Mensajes directos no leídos
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ? AND leido = 0 AND tipo_chat = 'directo'");
    $stmtUnread->execute([$currentUser['id']]);
    $mensajesNoLeidosCount = (int)$stmtUnread->fetchColumn();

    // Tareas pendientes para el alumno
    if ($priority <= 2 && $currentUser['curso_id']) {
        $stmtPend = $pdo->prepare("SELECT COUNT(*) FROM tareas t JOIN materias m ON t.materia_id = m.id WHERE m.curso_id = ? AND t.id NOT IN (
            SELECT tarea_id FROM entregas WHERE alumno_id = ? OR minigrupo_id IN (
                SELECT minigrupo_id FROM minigrupos_usuarios WHERE usuario_id = ?
            )
        )");
        $stmtPend->execute([$currentUser['curso_id'], $currentUser['id'], $currentUser['id']]);
        $tareasPendientesCount = (int)$stmtPend->fetchColumn();
    }
}
$totalAlertas = $tareasPendientesCount + $mensajesNoLeidosCount;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> - <?= htmlspecialchars($schoolName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        
        <?php if ($currentUser): ?>
        <div class="hamburger-menu" id="hamburgerMenu">
            <button class="hamburger-btn" type="button" aria-label="Menú principal" style="position:relative;">
                <span>☰</span>
                <span style="font-size:0.85rem; font-weight:600;">Menú</span>
                <?php if ($totalAlertas > 0): ?>
                    <span style="position:absolute; top:-3px; right:-3px; width:9px; height:9px; background:var(--danger); border-radius:50%;"></span>
                <?php endif; ?>
            </button>
            <div class="hamburger-content">
                <a href="index.php" class="hamburger-item">
                    <div class="hamburger-icon">🏠</div>
                    <div>
                        <div class="hamburger-text-title">Inicio</div>
                        <div class="hamburger-text-desc">Panel general y accesos</div>
                    </div>
                </a>
                <a href="classroom.php" class="hamburger-item" style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="hamburger-icon">🎓</div>
                        <div>
                            <div class="hamburger-text-title">Classroom</div>
                            <div class="hamburger-text-desc">Tareas, entregas y archivos</div>
                        </div>
                    </div>
                    <?php if ($tareasPendientesCount > 0): ?>
                        <span class="badge" style="background:var(--danger); color:white; font-size:0.7rem; padding:0.15rem 0.45rem; border-radius:999px;"><?= $tareasPendientesCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="horarios.php" class="hamburger-item">
                    <div class="hamburger-icon">📅</div>
                    <div>
                        <div class="hamburger-text-title">Horarios de Clases</div>
                        <div class="hamburger-text-desc">Cronograma semanal por curso</div>
                    </div>
                </a>
                <a href="votos.php" class="hamburger-item">
                    <div class="hamburger-icon">🗳️</div>
                    <div>
                        <div class="hamburger-text-title">Votaciones</div>
                        <div class="hamburger-text-desc">Encuestas de cursos y escuela</div>
                    </div>
                </a>
                <a href="minigrupos.php" class="hamburger-item">
                    <div class="hamburger-icon">👥</div>
                    <div>
                        <div class="hamburger-text-title">Grupos de Trabajo</div>
                        <div class="hamburger-text-desc">Minigrupos para proyectos</div>
                    </div>
                </a>
                <a href="mensajes.php" class="hamburger-item" style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="hamburger-icon">💬</div>
                        <div>
                            <div class="hamburger-text-title">Chat y Mensajes</div>
                            <div class="hamburger-text-desc">Comunícate con otros miembros</div>
                        </div>
                    </div>
                    <?php if ($mensajesNoLeidosCount > 0): ?>
                        <span class="badge" style="background:var(--danger); color:white; font-size:0.7rem; padding:0.15rem 0.45rem; border-radius:999px;"><?= $mensajesNoLeidosCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <a href="index.php" class="nav-brand" title="Código: <?= htmlspecialchars($schoolCode) ?>" style="margin-left: <?= $currentUser ? '0.75rem' : '0' ?>;">
            <span>🏫</span>
            <span><?= htmlspecialchars($schoolName) ?></span>
            <span class="badge" style="font-size:0.7rem; margin-left: 0.35rem; background: var(--primary-subtle); color: var(--primary); border: 1px solid var(--border-color);">
                <?= htmlspecialchars($schoolCode) ?>
            </span>
        </a>

        <?php if ($currentUser): ?>
            <ul class="nav-links">
                <li><a href="perfil.php" class="<?= $currentPage === 'perfil.php' ? 'active' : '' ?>">👤 Perfil</a></li>
                <li><a href="classroom.php" class="<?= $currentPage === 'classroom.php' ? 'active' : '' ?>">
                    🎓 Classroom
                    <?php if ($tareasPendientesCount > 0): ?>
                        <span class="badge" style="background:var(--danger); color:white; font-size:0.68rem; padding:0.1rem 0.4rem; border-radius:999px;"><?= $tareasPendientesCount ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="horarios.php" class="<?= $currentPage === 'horarios.php' ? 'active' : '' ?>">📅 Horarios</a></li>
                <li><a href="mensajes.php" class="<?= $currentPage === 'mensajes.php' ? 'active' : '' ?>">
                    💬 Chat
                    <?php if ($mensajesNoLeidosCount > 0): ?>
                        <span class="badge" style="background:var(--danger); color:white; font-size:0.68rem; padding:0.1rem 0.4rem; border-radius:999px;"><?= $mensajesNoLeidosCount ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="reportes.php" class="<?= $currentPage === 'reportes.php' ? 'active' : '' ?>">📢 Reportes</a></li>

                <?php if ($priority >= 3): ?>
                    <li><a href="cursos.php" class="<?= $currentPage === 'cursos.php' ? 'active' : '' ?>">📚 Cursos</a></li>
                    <li><a href="notas.php" class="<?= $currentPage === 'notas.php' ? 'active' : '' ?>">📝 Notas</a></li>
                <?php endif; ?>

                <?php if ($priority >= 4): ?>
                    <li><a href="roles.php" class="<?= $currentPage === 'roles.php' ? 'active' : '' ?>">🛡️ Roles</a></li>
                <?php endif; ?>

                <?php if ($priority >= 5): ?>
                    <li><a href="transferir_director.php" class="<?= $currentPage === 'transferir_director.php' ? 'active' : '' ?>">👑 Dirección</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <?php if (!empty($currentUser['avatar'])): ?>
                    <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1px solid var(--border-color);" alt="Avatar">
                <?php endif; ?>
                <span class="badge badge-<?= strtolower(str_replace(['/', ' '], ['-', ''], $rolNombre)) ?>">
                    <?= htmlspecialchars($rolNombre) ?>
                </span>
                <span style="font-size:0.875rem; font-weight:600;"><?= htmlspecialchars($username) ?></span>
                <button type="button" id="themeToggle" class="btn-theme" title="Alternar tema">🌙</button>
                <a href="logout.php" class="btn btn-secondary btn-sm">Salir</a>
            </div>
        <?php else: ?>
            <div class="nav-actions">
                <button type="button" id="themeToggle" class="btn-theme" title="Alternar tema">🌙</button>
                <a href="login.php" class="btn btn-secondary btn-sm">Iniciar Sesión</a>
                <a href="register.php" class="btn btn-primary btn-sm">Registrarse</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
    <?php endif; ?>

<script>
    const hamBtn = document.querySelector('.hamburger-btn');
    const hamMenu = document.getElementById('hamburgerMenu');
    if (hamBtn && hamMenu) {
        hamBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            hamMenu.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!hamMenu.contains(e.target)) {
                hamMenu.classList.remove('active');
            }
        });
    }
</script>
