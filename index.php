<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$username = current_username();
$priority = user_priority();
$schoolId = current_school_id();
$pdo = get_db();

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE escuela_id = ?");
$stmtC->execute([$schoolId]);
$cursosCount = $stmtC->fetchColumn();

$stmtU = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE escuela_id = ?");
$stmtU->execute([$schoolId]);
$usuariosCount = $stmtU->fetchColumn();

// Fetch curso nombre if assigned
$cursoNombre = 'Sin asignar';
if ($user['curso_id']) {
    $stmtCur = $pdo->prepare("SELECT nombre FROM cursos WHERE id = ?");
    $stmtCur->execute([$user['curso_id']]);
    $c = $stmtCur->fetch();
    if ($c) $cursoNombre = $c['nombre'];
}

$pageTitle = 'Inicio';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner de Bienvenida Amigable -->
<div class="card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-alt) 100%); border-left: 5px solid var(--primary); margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em;">Portal Institucional</span>
            <h1 style="font-size: 1.75rem; margin: 0.2rem 0 0.4rem 0; letter-spacing: -0.02em;">
                👋 ¡Hola, <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?>!
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">
                Tu rol: 
                <span class="badge badge-<?= strtolower(str_replace(['/', ' '], ['-', ''], $rolNombre)) ?>">
                    <?= htmlspecialchars($rolNombre) ?>
                </span>
                <?php if ($user['curso_id']): ?>
                    &bull; Curso asignado: <strong style="color: var(--text-main);"><?= htmlspecialchars($cursoNombre) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="perfil.php" class="btn btn-secondary">👤 Mi Perfil</a>
            <a href="mensajes.php" class="btn btn-primary">💬 Abrir Chat</a>
        </div>
    </div>

    <?php if ($tareasPendientesCount > 0): ?>
        <div style="margin-top:1.25rem; padding:0.75rem 1rem; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
            <span style="font-size:0.9rem; font-weight:600; color:var(--danger);">
                ⚠️ Tienes <strong><?= $tareasPendientesCount ?></strong> tarea(s) pendiente(s) por entregar en Classroom.
            </span>
            <a href="classroom.php" class="btn btn-danger btn-sm">Ver Tareas</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($priority >= 3): ?>
<!-- Resumen Rápido para Docentes y Directivos -->
<div class="grid-cards" style="margin-bottom: 2rem;">
    <div class="card" style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: var(--primary-subtle); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
            📚
        </div>
        <div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1;"><?= $cursosCount ?></div>
            <div style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-top: 4px;">Cursos Registrados</div>
        </div>
    </div>
    <div class="card" style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
            👥
        </div>
        <div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1;"><?= $usuariosCount ?></div>
            <div style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-top: 4px;">Usuarios en el Sistema</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cuadrícula de Accesos Rápidos Intuitivos -->
<div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
    <h2 style="font-size: 1.3rem; font-weight: 700; letter-spacing: -0.01em;">⚡ ¿Qué deseas hacer hoy?</h2>
    <span style="font-size: 0.85rem; color: var(--text-muted);">Accesos directos</span>
</div>

<div class="grid-cards">
    <!-- 1. Classroom -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(79, 70, 229, 0.12); color: var(--primary);">🎓</div>
            <h3 class="hub-card-title">Classroom</h3>
            <p class="hub-card-desc">
                Revisa tareas, consulta dudas con profes, descarga adjuntos y sube tus trabajos.
            </p>
        </div>
        <a href="classroom.php" class="btn btn-primary btn-sm" style="width: 100%;">
            Entrar a Classroom <?= $tareasPendientesCount > 0 ? "({$tareasPendientesCount})" : '' ?>
        </a>
    </div>

    <!-- 2. Horarios de Clases -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(14, 165, 233, 0.12); color: #0284c7;">📅</div>
            <h3 class="hub-card-title">Horarios de Clases</h3>
            <p class="hub-card-desc">
                Consulta los días de la semana, materias y módulos horarios de cada clase.
            </p>
        </div>
        <a href="horarios.php" class="btn btn-secondary btn-sm" style="width: 100%;">Ver Horarios</a>
    </div>

    <!-- 3. Votaciones -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(245, 158, 11, 0.12); color: var(--warning);">🗳️</div>
            <h3 class="hub-card-title">Votaciones y Encuestas</h3>
            <p class="hub-card-desc">
                Participa en las decisiones y encuestas escolares o consulta los votos en tiempo real.
            </p>
        </div>
        <a href="votos.php" class="btn btn-secondary btn-sm" style="width: 100%;">Ver Votaciones</a>
    </div>

    <!-- 4. Minigrupos -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--success);">👥</div>
            <h3 class="hub-card-title">Grupos de Trabajo</h3>
            <p class="hub-card-desc">
                Arma minigrupos con compañeros de tu clase para resolver y entregar tareas conjuntas.
            </p>
        </div>
        <a href="minigrupos.php" class="btn btn-secondary btn-sm" style="width: 100%;">Gestionar Grupos</a>
    </div>

    <!-- 5. Chat y Mensajería -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(139, 92, 246, 0.12); color: #7c3aed;">💬</div>
            <h3 class="hub-card-title">Chat y Mensajes</h3>
            <p class="hub-card-desc">
                Salas grupales de tu curso, minigrupos de trabajo y chats directos personales.
            </p>
        </div>
        <a href="mensajes.php" class="btn btn-secondary btn-sm" style="width: 100%;">
            Abrir Mensajes <?= $mensajesNoLeidosCount > 0 ? "({$mensajesNoLeidosCount})" : '' ?>
        </a>
    </div>

    <!-- 6. Buzón de Reportes -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(239, 68, 68, 0.12); color: var(--danger);">📢</div>
            <h3 class="hub-card-title">Buzón de Reportes</h3>
            <p class="hub-card-desc">
                Envía mensajes directos o avisos urgentes a las autoridades y revisa si ya fueron leídos.
            </p>
        </div>
        <a href="reportes.php" class="btn btn-secondary btn-sm" style="width: 100%;">Ir a Reportes</a>
    </div>

    <!-- 7. Información Personal / Calificaciones -->
    <div class="hub-card">
        <div>
            <div class="hub-card-icon" style="background: rgba(236, 72, 153, 0.12); color: #db2777;">👤</div>
            <h3 class="hub-card-title">Mi Perfil y Boletín</h3>
            <p class="hub-card-desc">
                Datos de usuario, cambio de contraseña, libreta de notas y boletín oficial para imprimir.
            </p>
        </div>
        <a href="perfil.php" class="btn btn-secondary btn-sm" style="width: 100%;">Ver Mi Perfil</a>
    </div>

    <!-- Módulos de Gestión (Docentes / Directivos) -->
    <?php if ($priority >= 3): ?>
    <div class="hub-card" style="border: 2px dashed var(--primary-light);">
        <div>
            <div class="hub-card-icon" style="background: var(--primary-subtle); color: var(--primary);">📝</div>
            <h3 class="hub-card-title">Gestión de Notas</h3>
            <p class="hub-card-desc">
                Carga y modifica calificaciones, consulta libretas e imprime boletines oficiales.
            </p>
        </div>
        <a href="notas.php" class="btn btn-primary btn-sm" style="width: 100%;">Cargar Calificaciones</a>
    </div>

    <div class="hub-card" style="border: 2px dashed var(--primary-light);">
        <div>
            <div class="hub-card-icon" style="background: var(--primary-subtle); color: var(--primary);">📚</div>
            <h3 class="hub-card-title">Gestión de Cursos</h3>
            <p class="hub-card-desc">
                Crea nuevos cursos, asigna materias e inscribe alumnos para estructurar las clases.
            </p>
        </div>
        <a href="cursos.php" class="btn btn-secondary btn-sm" style="width: 100%;">Administrar Cursos</a>
    </div>
    <?php endif; ?>

    <?php if ($priority >= 4): ?>
    <div class="hub-card" style="border: 2px dashed var(--primary-light);">
        <div>
            <div class="hub-card-icon" style="background: rgba(220, 38, 38, 0.1); color: var(--danger);">🛡️</div>
            <h3 class="hub-card-title">Permisos y Roles</h3>
            <p class="hub-card-desc">
                Asigna o modifica los rangos de profesores, delegados y directivos de la escuela.
            </p>
        </div>
        <a href="roles.php" class="btn btn-secondary btn-sm" style="width: 100%;">Gestionar Roles</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
