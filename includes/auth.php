<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_username(): ?string {
    return $_SESSION['username'] ?? null;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_priority(): int {
    return (int)($_SESSION['user']['rol_prioridad'] ?? 0);
}

function has_role_priority(int $minPriority): bool {
    return user_priority() >= $minPriority;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    refresh_session_user();
}

function require_priority(int $minPriority): void {
    require_login();
    if (!has_role_priority($minPriority)) {
        set_flash('error', 'Acceso denegado: No tienes los permisos necesarios.');
        header('Location: index.php');
        exit;
    }
}

function refresh_session_user(): void {
    $uid = current_user_id();
    if (!$uid) return;

    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT u.*, e.nombre as escuela_nombre, e.codigo as escuela_codigo FROM usuarios u JOIN escuelas e ON u.escuela_id = e.id WHERE u.id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;
        $_SESSION['school_id'] = $user['escuela_id'];
        $_SESSION['school_name'] = $user['escuela_nombre'];
        $_SESSION['school_code'] = $user['escuela_codigo'];
    } else {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

function login_user_by_id(int $userId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT u.*, e.nombre as escuela_nombre, e.codigo as escuela_codigo FROM usuarios u JOIN escuelas e ON u.escuela_id = e.id WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user'] = $user;
        $_SESSION['school_id'] = $user['escuela_id'];
        $_SESSION['school_name'] = $user['escuela_nombre'];
        $_SESSION['school_code'] = $user['escuela_codigo'];
    }
}

function current_school_id(): int {
    return (int)($_SESSION['school_id'] ?? 1);
}

function current_school_name(): string {
    return $_SESSION['school_name'] ?? 'Escuela Principal';
}

function current_school_code(): string {
    return $_SESSION['school_code'] ?? 'ESC-101';
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function verify_user_password(string $inputPassword, string $storedPassword): bool {
    if ($inputPassword == $storedPassword) return true;
    return password_verify($inputPassword, $storedPassword) || hash_equals($storedPassword, $inputPassword);
}

function hash_user_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
