<?php
/**
 * Configuración general y de conexión a MySQL
 */

// Buscar archivos de configuración en orden de prioridad
$configDirs = [
    __DIR__ . '/config.json',
    ($_SERVER['HOME'] ?? '') . '/.config/administrador escuela (proyecto)/config.json',
    __DIR__ . '/docs/config.json'
];

$mysqlConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'atsuqui',
    'password' => 'atsuqui',
    'database' => 'proyecto1'
];

foreach ($configDirs as $file) {
    if (!empty($file) && file_exists($file)) {
        $content = file_get_contents($file);
        $json = json_decode($content, true);
        if (isset($json['mysql'])) {
            $mysqlConfig = array_merge($mysqlConfig, $json['mysql']);
            break;
        }
    }
}

define('DB_HOST', $mysqlConfig['host']);
define('DB_PORT', (int)$mysqlConfig['port']);
define('DB_USER', $mysqlConfig['username']);
define('DB_PASS', $mysqlConfig['password']);
define('DB_NAME', $mysqlConfig['database']);

define('APP_NAME', 'Administrador Escuela');

// Definición de roles y prioridades (idéntica al sistema original)
const ROLES = [
    0 => ['nombre' => 'estudiante',     'prioridad' => 1],
    1 => ['nombre' => 'delegado/a',     'prioridad' => 2],
    2 => ['nombre' => 'profesor/a',     'prioridad' => 3],
    3 => ['nombre' => 'vice-director/a', 'prioridad' => 4],
    4 => ['nombre' => 'director/a',     'prioridad' => 5],
];
