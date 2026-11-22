<?php
declare(strict_types=1);

/*
 * Database Absensi SDN Ciburayut 01
 * Real credentials must be provided through server environment variables.
 */

$host = getenv('ABSENSI_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$user = getenv('ABSENSI_DB_USER') ?: getenv('DB_USER') ?: '';
$password = getenv('ABSENSI_DB_PASS') ?: getenv('DB_PASS') ?: '';
$database = getenv('ABSENSI_DB_NAME') ?: getenv('DB_NAME') ?: '';

if ($user === '' || $database === '') {
    http_response_code(500);
    exit('Konfigurasi database Absensi belum disetel pada server.');
}

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    error_log('Cloud Guru Absensi database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Layanan database sementara tidak tersedia.');
}

mysqli_set_charset($koneksi, 'utf8mb4');

// schema_bootstrap.php is intentionally NOT loaded here.
// Database schema changes must be run explicitly, not on every page request.
