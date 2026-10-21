<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$user = current_user();
$schoolId = current_school_id();
$priority = user_priority();

$tipoChat = $_GET['tipo'] ?? 'directo'; // 'directo', 'curso', 'minigrupo'
$chatId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fallback antiguo: ?chat_con=X
if (isset($_GET['chat_con'])) {
    $tipoChat = 'directo';
    $chatId = (int)$_GET['chat_con'];
}

// Enviar mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensaje'])) {
    $contenido = trim($_POST['contenido'] ?? '');
    $postTipo = $_POST['tipo_chat'] ?? 'directo';
    $postTarget = (int)($_POST['target_id'] ?? 0);
    
    if (!empty($contenido) && $postTarget) {
        if ($postTipo === 'curso') {
            $stmt = $pdo->prepare("INSERT INTO mensajes (escuela_id, remitente_id, destinatario_id, contenido, tipo_chat, grupo_id) VALUES (?, ?, NULL, ?, 'curso', ?)");
            $stmt->execute([$schoolId, $user['id'], $contenido, $postTarget]);
            header("Location: mensajes.php?tipo=curso&id=" . $postTarget);
            exit;
        } elseif ($postTipo === 'minigrupo') {
            $stmt = $pdo->prepare("INSERT INTO mensajes (escuela_id, remitente_id, destinatario_id, contenido, tipo_chat, grupo_id) VALUES (?, ?, NULL, ?, 'minigrupo', ?)");
            $stmt->execute([$schoolId, $user['id'], $contenido, $postTarget]);
            header("Location: mensajes.php?tipo=minigrupo&id=" . $postTarget);
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO mensajes (escuela_id, remitente_id, destinatario_id, contenido, tipo_chat, grupo_id) VALUES (?, ?, ?, ?, 'directo', NULL)");
            $stmt->execute([$schoolId, $user['id'], $postTarget, $contenido]);
            header("Location: mensajes.php?tipo=directo&id=" . $postTarget);
            exit;
        }
    }
}

// 1. Obtener curso del usuario
$miCurso = null;
if ($user['curso_id']) {
    $stmtC = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmtC->execute([$user['curso_id']]);
    $miCurso = $stmtC->fetch();
}

// 2. Obtener minigrupos del usuario
$misGrupos = [];
$stmtMG = $pdo->prepare("SELECT mg.* FROM minigrupos mg JOIN minigrupos_usuarios mu ON mg.id = mu.minigrupo_id WHERE mu.usuario_id = ?");
$stmtMG->execute([$user['id']]);
$misGrupos = $stmtMG->fetchAll();

// 3. Obtener lista de usuarios para mensajes directos
$stmtU = $pdo->prepare("SELECT id, nombre, apellido, username, rol_prioridad FROM usuarios WHERE escuela_id = ? AND id != ? ORDER BY nombre");
$stmtU->execute([$schoolId, $user['id']]);
$usuarios = $stmtU->fetchAll();

// 4. Cargar mensajes del chat activo
$mensajes = [];
$tituloChat = null;
$subtituloChat = null;

if ($chatId) {
    if ($tipoChat === 'curso' && $miCurso && $chatId == $miCurso['id']) {
        $tituloChat = "👥 Curso: " . $miCurso['nombre'];
        $subtituloChat = "Chat grupal con todos los integrantes de tu división";
        
        $stmtM = $pdo->prepare("SELECT m.*, u.nombre, u.apellido, u.rol_prioridad FROM mensajes m JOIN usuarios u ON m.remitente_id = u.id WHERE m.escuela_id = ? AND m.tipo_chat = 'curso' AND m.grupo_id = ? ORDER BY m.fecha_envio ASC");
        $stmtM->execute([$schoolId, $chatId]);
        $mensajes = $stmtM->fetchAll();

    } elseif ($tipoChat === 'minigrupo') {
        // Verificar membresía
        $stmtChk = $pdo->prepare("SELECT mg.nombre FROM minigrupos mg JOIN minigrupos_usuarios mu ON mg.id = mu.minigrupo_id WHERE mg.id = ? AND mu.usuario_id = ?");
        $stmtChk->execute([$chatId, $user['id']]);
        $mgInfo = $stmtChk->fetch();

        if ($mgInfo) {
            $tituloChat = "👥 Minigrupo: " . $mgInfo['nombre'];
            $subtituloChat = "Espacio privado para coordinar trabajos en equipo";
            
            $stmtM = $pdo->prepare("SELECT m.*, u.nombre, u.apellido, u.rol_prioridad FROM mensajes m JOIN usuarios u ON m.remitente_id = u.id WHERE m.escuela_id = ? AND m.tipo_chat = 'minigrupo' AND m.grupo_id = ? ORDER BY m.fecha_envio ASC");
            $stmtM->execute([$schoolId, $chatId]);
            $mensajes = $stmtM->fetchAll();
        }
    } else {
        // Chat Directo
        $stmtDest = $pdo->prepare("SELECT nombre, apellido, rol_prioridad FROM usuarios WHERE id = ? AND escuela_id = ?");
        $stmtDest->execute([$chatId, $schoolId]);
        $destinatarioInfo = $stmtDest->fetch();

        if ($destinatarioInfo) {
            $tituloChat = "💬 " . $destinatarioInfo['nombre'] . ' ' . $destinatarioInfo['apellido'];
            $subtituloChat = "Mensaje directo &bull; " . get_rol_name($destinatarioInfo['rol_prioridad']);

            // Marcar como leídos
            $upd = $pdo->prepare("UPDATE mensajes SET leido = 1 WHERE destinatario_id = ? AND remitente_id = ? AND tipo_chat = 'directo'");
            $upd->execute([$user['id'], $chatId]);

            // Cargar historial
            $stmtM = $pdo->prepare("SELECT m.*, u.nombre, u.apellido, u.rol_prioridad FROM mensajes m JOIN usuarios u ON m.remitente_id = u.id WHERE m.escuela_id = ? AND m.tipo_chat = 'directo' AND ((m.remitente_id = ? AND m.destinatario_id = ?) OR (m.remitente_id = ? AND m.destinatario_id = ?)) ORDER BY m.fecha_envio ASC");
            $stmtM->execute([$schoolId, $user['id'], $chatId, $chatId, $user['id']]);
            $mensajes = $stmtM->fetchAll();
        }
    }
}

$pageTitle = 'Chat y Mensajes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="border:none; padding:0 0 1rem 0;">
    <h1 style="font-size: 1.6rem;">💬 Centro de Mensajería Escolar</h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">Comunícate por salas grupales o mediante mensajes directos.</p>
</div>

<div style="display:flex; gap:1.25rem; flex-wrap:wrap;">
    <!-- Lista de Conversaciones y Canales -->
    <div class="card" style="flex:1; min-width:280px; max-width:360px; padding:1.25rem; margin-bottom:0;">
        <h3 class="card-title" style="margin-bottom:0.75rem; font-size:1.1rem;">Canales y Chats</h3>
        
        <div style="max-height:65vh; overflow-y:auto; padding-right:0.35rem;">
            <!-- Salas Grupales -->
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin: 0.5rem 0 0.4rem 0.2rem;">
                Grupos y Cursos
            </div>

            <?php if ($miCurso): 
                $isActCurso = ($tipoChat === 'curso' && $chatId == $miCurso['id']);
            ?>
                <a href="mensajes.php?tipo=curso&id=<?= $miCurso['id'] ?>" style="display:flex; align-items:center; gap:0.6rem; padding:0.65rem 0.85rem; border-radius:var(--radius-sm); margin-bottom:0.35rem; text-decoration:none; background:<?= $isActCurso ? 'var(--primary)' : 'var(--bg-card-alt)' ?>; color:<?= $isActCurso ? '#fff' : 'var(--text-main)' ?>; border:1px solid <?= $isActCurso ? 'var(--primary)' : 'var(--border-color)' ?>;">
                    <span style="font-size:1.2rem;">🏫</span>
                    <div>
                        <div style="font-weight:700; font-size:0.9rem;">Curso <?= htmlspecialchars($miCurso['nombre']) ?></div>
                        <div style="font-size:0.75rem; opacity:0.8;">Chat de toda la división</div>
                    </div>
                </a>
            <?php endif; ?>

            <?php foreach ($misGrupos as $g): 
                $isActG = ($tipoChat === 'minigrupo' && $chatId == $g['id']);
            ?>
                <a href="mensajes.php?tipo=minigrupo&id=<?= $g['id'] ?>" style="display:flex; align-items:center; gap:0.6rem; padding:0.65rem 0.85rem; border-radius:var(--radius-sm); margin-bottom:0.35rem; text-decoration:none; background:<?= $isActG ? 'var(--primary)' : 'var(--bg-card-alt)' ?>; color:<?= $isActG ? '#fff' : 'var(--text-main)' ?>; border:1px solid <?= $isActG ? 'var(--primary)' : 'var(--border-color)' ?>;">
                    <span style="font-size:1.2rem;">👥</span>
                    <div>
                        <div style="font-weight:700; font-size:0.9rem;"><?= htmlspecialchars($g['nombre']) ?></div>
                        <div style="font-size:0.75rem; opacity:0.8;">Minigrupo de trabajo</div>
                    </div>
                </a>
            <?php endforeach; ?>

            <!-- Mensajes Directos -->
            <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin: 1rem 0 0.4rem 0.2rem;">
                Mensajes Directos
            </div>

            <?php foreach ($usuarios as $u): 
                $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM mensajes WHERE remitente_id = ? AND destinatario_id = ? AND leido = 0 AND tipo_chat = 'directo'");
                $stmtUnread->execute([$u['id'], $user['id']]);
                $unread = $stmtUnread->fetchColumn();
                $isAct = ($tipoChat === 'directo' && $chatId == $u['id']);
            ?>
                <a href="mensajes.php?tipo=directo&id=<?= $u['id'] ?>" style="display:flex; justify-content:space-between; align-items:center; padding:0.65rem 0.85rem; border-radius:var(--radius-sm); margin-bottom:0.35rem; text-decoration:none; background:<?= $isAct ? 'var(--primary)' : 'var(--bg-card-alt)' ?>; color:<?= $isAct ? '#fff' : 'var(--text-main)' ?>; border:1px solid <?= $isAct ? 'var(--primary)' : 'var(--border-color)' ?>;">
                    <div style="overflow:hidden;">
                        <div style="font-weight:600; font-size:0.9rem; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                            <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                        </div>
                        <div style="font-size:0.75rem; opacity:0.8;"><?= get_rol_name($u['rol_prioridad']) ?></div>
                    </div>
                    <?php if ($unread > 0): ?>
                        <span class="badge" style="background:var(--danger); color:white; font-size:0.7rem; padding:0.15rem 0.45rem; border-radius:999px;"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Panel de Conversación -->
    <div class="card" style="flex:2; min-width:320px; display:flex; flex-direction:column; height:65vh; padding:1.25rem; margin-bottom:0;">
        <?php if ($tituloChat): ?>
            <div style="border-bottom:1px solid var(--border-color); padding-bottom:0.75rem; margin-bottom:1rem;">
                <h3 class="card-title" style="font-size:1.15rem;"><?= htmlspecialchars($tituloChat) ?></h3>
                <div style="font-size:0.8rem; color:var(--text-muted);"><?= $subtituloChat ?></div>
            </div>
            
            <div style="flex:1; overflow-y:auto; padding-right:0.5rem; display:flex; flex-direction:column; gap:0.75rem;" id="chatBox">
                <?php if (empty($mensajes)): ?>
                    <p style="text-align:center; color:var(--text-muted); margin-top:3rem;">No hay mensajes aún en esta sala. ¡Comienza la conversación!</p>
                <?php else: ?>
                    <?php foreach ($mensajes as $msg): 
                        $soyYo = ($msg['remitente_id'] == $user['id']);
                    ?>
                        <div style="max-width:78%; padding:0.65rem 0.95rem; border-radius:12px; align-self:<?= $soyYo ? 'flex-end' : 'flex-start' ?>; background:<?= $soyYo ? 'var(--primary)' : 'var(--bg-card-alt)' ?>; color:<?= $soyYo ? '#fff' : 'var(--text-main)' ?>; border:1px solid <?= $soyYo ? 'transparent' : 'var(--border-color)' ?>; box-shadow:var(--shadow-sm);">
                            <?php if (!$soyYo && $tipoChat !== 'directo'): ?>
                                <div style="font-size:0.75rem; font-weight:700; color:var(--primary); margin-bottom:0.2rem;">
                                    <?= htmlspecialchars($msg['nombre'] . ' ' . $msg['apellido']) ?>
                                    <small style="opacity:0.7;">(<?= get_rol_name($msg['rol_prioridad']) ?>)</small>
                                </div>
                            <?php endif; ?>
                            
                            <div style="font-size:0.925rem; line-height:1.45; word-wrap:break-word;"><?= nl2br(htmlspecialchars($msg['contenido'])) ?></div>
                            
                            <div style="font-size:0.68rem; margin-top:0.3rem; opacity:0.75; text-align:right;">
                                <?= date('d/m H:i', strtotime($msg['fecha_envio'])) ?>
                                <?php if ($soyYo && $tipoChat === 'directo'): ?>
                                    <?= $msg['leido'] ? '👁️' : '✓' ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="mensajes.php" style="margin-top:1rem; display:flex; gap:0.5rem;">
                <input type="hidden" name="enviar_mensaje" value="1">
                <input type="hidden" name="tipo_chat" value="<?= htmlspecialchars($tipoChat) ?>">
                <input type="hidden" name="target_id" value="<?= $chatId ?>">
                <input type="text" name="contenido" class="form-control" placeholder="Escribe un mensaje..." required autofocus autocomplete="off">
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.25rem;">Enviar</button>
            </form>
            
            <script>
                const chatBox = document.getElementById('chatBox');
                if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
            </script>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; color:var(--text-muted); text-align:center;">
                <span style="font-size:3rem; margin-bottom:0.5rem;">💬</span>
                <h3 style="font-size:1.15rem; color:var(--text-main);">Centro de Mensajes</h3>
                <p style="font-size:0.875rem; max-width:280px; margin-top:0.25rem;">Elige una sala grupal de tu curso o selecciona un contacto para chatear.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
