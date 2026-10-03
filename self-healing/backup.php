<?php
declare(strict_types=1);

require_once __DIR__ . '/logger.php';

function sh_normalize_path(string $path): string
{
    $real = realpath($path);
    if ($real === false) {
        throw new RuntimeException('Path does not exist.');
    }
    return $real;
}

function sh_is_allowed_path(string $path): bool
{
    $real = sh_normalize_path($path);

    foreach ((array) (sh_config()['allowed_roots'] ?? []) as $root) {
        $rootReal = realpath((string) $root);
        if ($rootReal === false) {
            continue;
        }

        $prefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($real === $rootReal || str_starts_with($real, $prefix)) {
            return true;
        }
    }

    return false;
}

function sh_backup_file(string $source): string
{
    $source = sh_normalize_path($source);

    if (!sh_is_allowed_path($source)) {
        throw new RuntimeException('File is outside the allowed roots.');
    }

    if (!is_file($source) || !is_readable($source)) {
        throw new RuntimeException('Source is not a readable file.');
    }

    $max = (int) (sh_config()['max_backup_bytes'] ?? 10485760);
    $size = filesize($source);
    if ($size === false || $size > $max) {
        throw new RuntimeException('File exceeds the backup size limit.');
    }

    $backupDir = (string) sh_config()['backup_dir'];
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
        throw new RuntimeException('Unable to create backup directory.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($source));
    $target = $backupDir . '/' . date('Ymd_His') . '__' . $safeName;

    if (!copy($source, $target)) {
        throw new RuntimeException('Backup failed.');
    }

    @chmod($target, 0600);

    sh_log('backup_created', [
        'source' => $source,
        'backup' => $target,
        'bytes' => $size,
    ]);

    return $target;
}

function sh_restore_file(string $backup, string $target): void
{
    $backup = sh_normalize_path($backup);
    $target = sh_normalize_path($target);

    $backupDir = realpath((string) sh_config()['backup_dir']);
    if ($backupDir === false) {
        throw new RuntimeException('Backup directory does not exist.');
    }

    $backupPrefix = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!($backup === $backupDir || str_starts_with($backup, $backupPrefix))) {
        throw new RuntimeException('Backup file is outside the backup directory.');
    }

    if (!sh_is_allowed_path($target)) {
        throw new RuntimeException('Target is outside the allowed roots.');
    }

    if (!is_file($backup)) {
        throw new RuntimeException('Backup file does not exist.');
    }

    if (!copy($backup, $target)) {
        throw new RuntimeException('Restore failed.');
    }

    sh_log('file_restored', [
        'backup' => $backup,
        'target' => $target,
    ]);
}
