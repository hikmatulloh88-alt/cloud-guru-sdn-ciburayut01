<?php
declare(strict_types=1);

/**
 * Cloud Guru session guard.
 * Login is performed by /auth/login.php and stores the local portal ID in user_id.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('wajib_login')) {
    function wajib_login(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (is_numeric($userId) && (int) $userId > 0) {
            return;
        }

        // Preserve only a local path for returning after login; reject external/unsafe URLs.
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard/index.php');
        if (
            $next === '' ||
            $next[0] !== '/' ||
            substr($next, 0, 2) === '//' ||
            strpos($next, "\\") !== false ||
            preg_match('/[\r\n\x00]/', $next)
        ) {
            $next = '/dashboard/index.php';
        }

        header('Location: /auth/login.php?next=' . rawurlencode($next), true, 302);
        exit;
    }
}
