<?php
declare(strict_types=1);

/*
 * Cloud Guru Self-Healing Patch Engine V1
 *
 * Safety model:
 * - Never executes AI-generated PHP.
 * - Requires an exact old-text match and an explicit approval phrase.
 * - Creates a backup before changing a file.
 * - Validates PHP syntax after the change when PHP CLI is available.
 * - Automatically restores the backup if syntax validation fails.
 * - Blocks secret/runtime paths and the Self-Healing runtime itself.
 */

require_once __DIR__ . '/backup.php';

header('Content-Type: application/json; charset=utf-8');

function sh_patch_json(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sh_patch_token_ok(): bool
{
    $expected = (string) (sh_config()['access_token'] ?? '');
    if ($expected === '' || str_starts_with($expected, 'CHANGE_THIS_')) {
        return false;
    }

    $provided = (string) (
        $_SERVER['HTTP_X_SELF_HEALING_TOKEN']
        ?? $_POST['token']
        ?? $_GET['token']
        ?? ''
    );

    return $provided !== '' && hash_equals($expected, $provided);
}

function sh_patch_blocked(string $path): bool
{
    $real = sh_normalize_path($path);
    $blocked = [
        realpath(__DIR__ . '/runtime'),
        realpath(__DIR__ . '/backups'),
        realpath(__DIR__ . '/config.local.php'),
    ];

    foreach ($blocked as $item) {
        if ($item !== false && ($real === $item || str_starts_with($real, rtrim($item, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
            return true;
        }
    }

    return false;
}

function sh_patch_php_lint(string $file): array
{
    $php = PHP_BINARY;
    if (!is_file($php) || !is_executable($php)) {
        return [
            'available' => false,
            'ok' => true,
            'message' => 'PHP CLI tidak tersedia; syntax check dilewati.',
        ];
    }

    $command = escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [
        'available' => true,
        'ok' => $exitCode === 0,
        'message' => implode(PHP_EOL, $output),
    ];
}

function sh_patch_read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $raw = json_encode($_POST);
    }

    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

if (!sh_patch_token_ok()) {
    sh_patch_json(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

if (!sh_config()['enabled']) {
    sh_patch_json(403, ['ok' => false, 'error' => 'Self-Healing disabled.']);
}

$data = sh_patch_read_json();
$action = (string) ($data['action'] ?? 'dry_run');

if ($action === 'apply') {
    $approval = (string) ($data['approval'] ?? '');
    if ($approval !== 'I_UNDERSTAND_THIS_PATCH') {
        sh_patch_json(400, [
            'ok' => false,
            'error' => 'Explicit approval is required.',
            'required_approval' => 'I_UNDERSTAND_THIS_PATCH',
        ]);
    }
}

if (!in_array($action, ['dry_run', 'apply', 'rollback'], true)) {
    sh_patch_json(400, ['ok' => false, 'error' => 'Unknown action.']);
}

try {
    if ($action === 'rollback') {
        $backup = (string) ($data['backup'] ?? '');
        $target = (string) ($data['target'] ?? '');

        if ($backup === '' || $target === '') {
            throw new RuntimeException('backup and target are required.');
        }

        sh_restore_file($backup, $target);
        sh_log('patch_rollback_completed', ['backup' => $backup, 'target' => $target]);

        sh_patch_json(200, [
            'ok' => true,
            'action' => 'rollback',
            'backup' => $backup,
            'target' => $target,
        ]);
    }

    $file = (string) ($data['file'] ?? '');
    $old = (string) ($data['old'] ?? '');
    $new = (string) ($data['new'] ?? '');

    if ($file === '' || $old === '' || $new === '') {
        throw new RuntimeException('file, old and new are required.');
    }

    $real = sh_normalize_path($file);

    if (!sh_is_allowed_path($real)) {
        throw new RuntimeException('File is outside the allowed roots.');
    }

    if (sh_patch_blocked($real)) {
        throw new RuntimeException('Protected Self-Healing path.');
    }

    if (!is_file($real) || !is_readable($real) || !is_writable($real)) {
        throw new RuntimeException('Target file must be readable and writable.');
    }

    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'phtml', 'inc'], true)) {
        throw new RuntimeException('Only PHP source files are supported in V1.');
    }

    $source = file_get_contents($real);
    if ($source === false) {
        throw new RuntimeException('Unable to read target file.');
    }

    $count = substr_count($source, $old);
    if ($count !== 1) {
        throw new RuntimeException('Exact old text must occur exactly once; found ' . $count . ' occurrences.');
    }

    $candidate = str_replace($old, $new, $source, $replaced);
    if ($replaced !== 1) {
        throw new RuntimeException('Patch replacement failed.');
    }

    $result = [
        'ok' => true,
        'action' => $action,
        'file' => $real,
        'replacement_count' => $replaced,
        'syntax_check' => null,
    ];

    if ($action === 'dry_run') {
        $result['message'] = 'Dry-run only. No file was changed.';
        sh_log('patch_dry_run', [
            'file' => $real,
            'replacement_count' => $replaced,
        ]);
        sh_patch_json(200, $result);
    }

    $backup = sh_backup_file($real);

    $tmp = $real . '.self-healing-' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $candidate, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write temporary patch file.');
    }
    @chmod($tmp, fileperms($real) & 0777);

    if (!rename($tmp, $real)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to atomically replace target file.');
    }

    $lint = sh_patch_php_lint($real);
    $result['backup'] = $backup;
    $result['syntax_check'] = $lint;

    if (!$lint['ok']) {
        sh_restore_file($backup, $real);
        sh_log('patch_auto_rollback', [
            'file' => $real,
            'backup' => $backup,
            'reason' => 'PHP syntax check failed',
        ]);

        sh_patch_json(422, [
            'ok' => false,
            'action' => 'apply',
            'file' => $real,
            'backup' => $backup,
            'syntax_check' => $lint,
            'rolled_back' => true,
        ]);
    }

    sh_log('patch_applied', [
        'file' => $real,
        'backup' => $backup,
        'replacement_count' => $replaced,
        'syntax_check' => $lint,
    ]);

    sh_patch_json(200, $result);
} catch (Throwable $e) {
    sh_log('patch_failed', [
        'action' => $action,
        'error' => $e->getMessage(),
    ]);

    sh_patch_json(400, [
        'ok' => false,
        'action' => $action,
        'error' => $e->getMessage(),
    ]);
}
