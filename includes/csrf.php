<?php
declare(strict_types=1);

/* CSRF protection for state-changing Cloud Guru storage requests. */
function cloudguru_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['cloudguru_csrf_token']) || !is_string($_SESSION['cloudguru_csrf_token'])) {
        $_SESSION['cloudguru_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cloudguru_csrf_token'];
}

function cloudguru_csrf_require_valid(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $expected = (string) ($_SESSION['cloudguru_csrf_token'] ?? '');
    $provided = (string) ($_POST['csrf_token'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit('Permintaan ditolak: token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}
