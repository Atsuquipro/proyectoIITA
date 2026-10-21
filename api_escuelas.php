<?php
/**
 * API ligera para búsqueda predictiva y validación de escuelas públicas y privadas
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim($_GET['action'] ?? 'search');

if ($action === 'check') {
    $code = trim($_GET['code'] ?? '');
    if ($code === '') {
        echo json_encode(['success' => false, 'error' => 'Código no proporcionado']);
        exit;
    }

    $school = get_school_by_code($code);
    if ($school) {
        echo json_encode([
            'success' => true,
            'school' => [
                'id'         => (int)$school['id'],
                'nombre'     => $school['nombre'],
                'codigo'     => $school['codigo'],
                'es_publica' => (bool)$school['es_publica']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'Escuela no encontrada']);
    }
    exit;
}

// Búsqueda de escuelas públicas
$q = trim($_GET['q'] ?? '');
$schools = get_public_schools($q);

$result = [];
foreach ($schools as $s) {
    $result[] = [
        'id'     => (int)$s['id'],
        'nombre' => $s['nombre'],
        'codigo' => $s['codigo']
    ];
}

echo json_encode([
    'success' => true,
    'total'   => count($result),
    'schools' => $result
], JSON_UNESCAPED_UNICODE);
