<?php
declare(strict_types=1);

function sh_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $local = __DIR__ . '/config.local.php';
    $example = __DIR__ . '/config.example.php';

    if (is_file($local)) {
        $config = require $local;
    } else {
        $config = require $example;
    }

    return $config;
}

function sh_runtime_dir(): string
{
    $dir = (string) sh_config()['runtime_dir'];
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function sh_log(string $event, array $context = []): void
{
    $record = [
        'time' => date('c'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'context' => $context,
    ];

    $file = sh_runtime_dir() . '/self-healing.log';
    @file_put_contents(
        $file,
        json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function sh_install_error_handler(): void
{
    set_error_handler(static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        sh_log('php_error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);

        return false;
    });
}

function sh_install_shutdown_handler(): void
{
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($error['type'], $fatalTypes, true)) {
            sh_log('fatal_error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        }
    });
}
