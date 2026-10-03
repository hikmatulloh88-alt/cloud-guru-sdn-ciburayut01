<?php
declare(strict_types=1);

/*
 * Copy this file to config.local.php on the server.
 * NEVER commit config.local.php.
 */

return [
    'enabled' => true,

    // Use a long random token. Keep this file outside Git.
    'access_token' => 'CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN',

    // Runtime paths are intentionally outside the Git-tracked code.
    'runtime_dir' => __DIR__ . '/runtime',
    'backup_dir' => __DIR__ . '/backups',

    // Files must be explicitly allow-listed before backup/restore.
    'allowed_roots' => [
        dirname(__DIR__),
    ],

    // Keep the first version conservative.
    'max_backup_bytes' => 10 * 1024 * 1024,
];
