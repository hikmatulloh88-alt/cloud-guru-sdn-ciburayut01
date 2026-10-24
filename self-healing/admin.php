<?php
declare(strict_types=1);

/* Admin-only bridge from the authenticated Cloud Guru portal to Self-Healing. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('Akses Self-Healing hanya untuk Administrator.');
}

$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Self-Healing belum dikonfigurasi pada server.');
}

$config = require $configFile;
if (!is_array($config)) {
    http_response_code(503);
    exit('Konfigurasi Self-Healing tidak valid.');
}

$token = (string)($config['access_token'] ?? '');
if ($token === '' || $token === 'CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN') {
    http_response_code(503);
    exit('Token Self-Healing belum dikonfigurasi.');
}

/* Keep the secret token server-side; never put it in a URL. */
$_SERVER['HTTP_X_SELF_HEALING_TOKEN'] = $token;
require __DIR__ . '/dashboard.php';
