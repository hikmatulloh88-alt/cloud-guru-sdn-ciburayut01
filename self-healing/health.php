<?php
declare(strict_types=1);

require_once __DIR__ . '/logger.php';

$config = sh_config();

if (empty($config['enabled'])) {
    http_response_code(503);
    exit('Self-Healing disabled');
}

$provided = (string) ($_SERVER['HTTP_X_SELF_HEALING_TOKEN'] ?? $_GET['token'] ?? '');
$expected = (string) ($config['access_token'] ?? '');

if ($expected === '' || $expected === 'CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN' ||
    !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: application/json; charset=utf-8');

$checks = [];

$checks['php'] = [
    'ok' => PHP_VERSION_ID >= 80000,
    'version' => PHP_VERSION,
];

$runtime = sh_runtime_dir();
$checks['runtime'] = [
    'ok' => is_dir($runtime) && is_writable($runtime),
    'path' => $runtime,
];

$backup = (string) ($config['backup_dir'] ?? '');
if ($backup !== '' && !is_dir($backup)) {
    @mkdir($backup, 0700, true);
}

$checks['backup'] = [
    'ok' => $backup !== '' && is_dir($backup) && is_writable($backup),
    'path' => $backup,
];

$overall = true;
foreach ($checks as $check) {
    if (empty($check['ok'])) {
        $overall = false;
        break;
    }
}

sh_log('health_check', ['ok' => $overall]);

echo json_encode([
    'service' => 'cloud-guru-self-healing',
    'version' => '1.0.0',
    'status' => $overall ? 'healthy' : 'attention',
    'checks' => $checks,
    'time' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
