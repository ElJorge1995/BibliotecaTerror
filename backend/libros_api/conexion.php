<?php
/**
 * Conexión PDO a la base de datos del catálogo (`librum-tenebris`).
 *
 * Lee la configuración de las variables LIBROS_DB_* del `.env` central de
 * ApiLoging (mismo `.env` que usa el resto del proyecto). Si no están
 * definidas, cae en los valores por defecto de XAMPP (root sin password,
 * BD `librum-tenebris`) para que el proyecto funcione out-of-the-box en un
 * entorno de desarrollo recién clonado.
 *
 * Para personalizar credenciales en local o producción, copiar
 * `ApiLoging/.env.example` → `ApiLoging/.env` y ajustar los valores.
 */

// Cargar el .env central si aún no se ha cargado. La función Env::load es
// idempotente: la segunda llamada no reescribe variables ya definidas.
$auth_root = is_dir(__DIR__ . '/../auth')
    ? __DIR__ . '/../auth'
    : __DIR__ . '/../../ApiLoging';

if (!class_exists('Env')) {
    require_once $auth_root . '/config/Env.php';
}
Env::load($auth_root . '/.env');

$host    = getenv('LIBROS_DB_HOST') ?: 'localhost';
$port    = getenv('LIBROS_DB_PORT') ?: '3306';
$db      = getenv('LIBROS_DB_NAME') ?: 'librum-tenebris';
$user    = getenv('LIBROS_DB_USER') ?: 'root';
$pass    = getenv('LIBROS_DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}
