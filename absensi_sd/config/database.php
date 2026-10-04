<?php
declare(strict_types=1);

/*
 * Database Absensi SDN Ciburayut 01
 * IMPORTANT:
 * - Do not commit real hosting credentials to GitHub.
 * - Fill the values below only on the production server.
 */

$host = 'localhost';
$user = 'YOUR_DB_USER';
$password = 'YOUR_DB_PASSWORD';
$database = 'YOUR_DB_NAME';

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($koneksi, 'utf8mb4');

// schema_bootstrap.php is intentionally NOT loaded here.
// Database schema changes must be run explicitly, not on every page request.
