<?php
declare(strict_types=1);

/**
 * Cloud Guru database configuration.
 *
 * Set the CLOUD_DB_* variables on the server. For hosts that expose one
 * database to both applications, DB_* variables are also supported.
 */

$host = getenv('CLOUD_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$database = getenv('CLOUD_DB_NAME') ?: getenv('DB_NAME') ?: '';
$user = getenv('CLOUD_DB_USER') ?: getenv('DB_USER') ?: '';
$password = getenv('CLOUD_DB_PASS') ?: getenv('DB_PASS') ?: '';

if ($database === '' || $user === '') {
    throw new RuntimeException('Konfigurasi database Cloud Guru belum disetel pada server.');
}

define('DB_HOST', $host);
define('DB_NAME', $database);
define('DB_USER', $user);
define('DB_PASS', $password);

if (getenv('ABSENSI_DB_HOST') || getenv('DB_HOST')) {
    define('ABSENSI_DB_HOST', getenv('ABSENSI_DB_HOST') ?: $host);
}
if (getenv('ABSENSI_DB_NAME') || getenv('DB_NAME')) {
    define('ABSENSI_DB_NAME', getenv('ABSENSI_DB_NAME') ?: $database);
}
if (getenv('ABSENSI_DB_USER') || getenv('DB_USER')) {
    define('ABSENSI_DB_USER', getenv('ABSENSI_DB_USER') ?: $user);
}
if (getenv('ABSENSI_DB_PASS') !== false || getenv('DB_PASS') !== false) {
    define('ABSENSI_DB_PASS', getenv('ABSENSI_DB_PASS') !== false
        ? getenv('ABSENSI_DB_PASS')
        : $password);
}

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4',
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Cloud Guru database connection failed: ' . $e->getMessage());
    http_response_code(500);
    throw new RuntimeException('Layanan database sementara tidak tersedia.', 0, $e);
}
