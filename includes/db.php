<?php
require_once __DIR__ . '/../config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }

    return $pdo;
}

function get_active_school_id(): int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return (int)($_SESSION['school_id'] ?? 1);
}

function get_school_by_code(string $code): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM escuelas WHERE UPPER(codigo) = ? OR id = ? LIMIT 1");
    $idNum = is_numeric($code) ? (int)$code : 0;
    $stmt->execute([strtoupper(trim($code)), $idNum]);
    return $stmt->fetch() ?: null;
}

function get_school_by_id(int $id): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM escuelas WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_public_schools(?string $query = null): array {
    $pdo = get_db();
    if ($query !== null && trim($query) !== '') {
        $stmt = $pdo->prepare("SELECT * FROM escuelas WHERE es_publica = 1 AND nombre LIKE ? ORDER BY nombre ASC LIMIT 30");
        $stmt->execute(['%' . trim($query) . '%']);
    } else {
        $stmt = $pdo->query("SELECT * FROM escuelas WHERE es_publica = 1 ORDER BY nombre ASC LIMIT 30");
    }
    return $stmt->fetchAll();
}

function create_school(string $nombre, string $directorUsername, array $directorData): ?array {
    $pdo = get_db();
    $codigo = 'ESC-' . time() . rand(10,99);

    try {
        $pdo->beginTransaction();
        
        $ins = $pdo->prepare("INSERT INTO escuelas (nombre, codigo, es_publica) VALUES (?, ?, 0)");
        $ins->execute([trim($nombre), $codigo]);
        $newId = (int)$pdo->lastInsertId();

        $insU = $pdo->prepare("INSERT INTO usuarios (escuela_id, username, nombre, apellido, password, rol_prioridad) VALUES (?, ?, ?, ?, ?, 5)");
        $insU->execute([$newId, $directorUsername, $directorData['nombre'], $directorData['apellido'], $directorData['password']]);

        $pdo->commit();
        
        return [
            'id' => $newId,
            'nombre' => trim($nombre),
            'codigo' => $codigo
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return null;
    }
}
