<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "ABSENSI SD - HEALTH CHECK\n";
echo "=========================\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'OK' : 'MISSING') . "\n";
echo "mysqlnd: " . (function_exists('mysqli_stmt_get_result') ? 'OK' : 'MISSING') . "\n";

$config = __DIR__ . '/config/database.php';
echo "database.php: " . (is_file($config) ? 'OK' : 'MISSING') . "\n";

if (!is_file($config)) exit;

try {
    require $config;
} catch (Throwable $e) {
    echo "LOAD CONFIG ERROR: " . $e->getMessage() . "\n";
    exit;
}

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    echo "DB CONNECTION: FAILED\n";
    echo "Reason: variable $koneksi bukan koneksi mysqli.\n";
    exit;
}

if (mysqli_connect_errno()) {
    echo "DB CONNECTION: FAILED\n";
    echo "MySQL error: " . mysqli_connect_error() . "\n";
    exit;
}

echo "DB CONNECTION: OK\n";

$tables = ['users','siswa','guru','kelas','tahun_ajaran','absensi_siswa'];
foreach ($tables as $table) {
    $safe = mysqli_real_escape_string($koneksi, $table);
    $q = mysqli_query($koneksi, "SHOW TABLES LIKE '{$safe}'");
    echo "TABLE {$table}: " . (($q && mysqli_num_rows($q) > 0) ? 'OK' : 'MISSING') . "\n";
}

echo "HEALTH CHECK SELESAI\n";
