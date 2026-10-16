<?php
declare(strict_types=1);

/* Cloud Guru Google Drive OAuth start endpoint. Keep credentials in server-only config. */
require_once __DIR__ . '/../includes/auth.php';
wajib_login();
require_once __DIR__ . '/../config/google_drive_lib.php';

function gdrive_connect_b64url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

try {
    $userId = gdrive_user_id();
    if ($userId <= 0) {
        throw new RuntimeException('Akun Cloud Guru tidak ditemukan. Silakan login ulang.');
    }
    if (!defined('GOOGLE_DRIVE_CLIENT_ID') || !defined('GOOGLE_DRIVE_REDIRECT_URI') ||
        !defined('GOOGLE_DRIVE_TOKEN_KEY') || GOOGLE_DRIVE_CLIENT_ID === '' ||
        GOOGLE_DRIVE_TOKEN_KEY === '' || GOOGLE_DRIVE_TOKEN_KEY === 'GENERATE_RANDOM_KEY_ON_SERVER_AND_KEEP_STABLE') {
        throw new RuntimeException('Konfigurasi Google Drive belum lengkap. Periksa config/google_drive.php di server.');
    }

    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS google_drive_oauth_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        verifier_enc TEXT NOT NULL,
        state_value VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX idx_gdrive_oauth_user_exp (user_id, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $verifier = gdrive_connect_b64url(random_bytes(48));
    $challenge = gdrive_connect_b64url(hash('sha256', $verifier, true));
    $now = time();
    $payload = gdrive_connect_b64url(json_encode([
        'uid' => $userId,
        'iat' => $now,
        'exp' => $now + 600,
        'nonce' => bin2hex(random_bytes(12))
    ], JSON_THROW_ON_ERROR));
    $state = $payload . '.' . hash_hmac('sha256', $payload, GOOGLE_DRIVE_TOKEN_KEY);

    $pdo->prepare('DELETE FROM google_drive_oauth_transactions WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('INSERT INTO google_drive_oauth_transactions (user_id, verifier_enc, state_value, created_at, expires_at) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))')
        ->execute([$userId, gdrive_encrypt($verifier), $state]);

    $params = [
        'client_id' => GOOGLE_DRIVE_CLIENT_ID,
        'redirect_uri' => GOOGLE_DRIVE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => defined('GOOGLE_DRIVE_SCOPE') ? GOOGLE_DRIVE_SCOPE : 'https://www.googleapis.com/auth/drive.file',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'include_granted_scopes' => 'true',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256'
    ];
    header('Cache-Control: no-store');
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params), true, 302);
    exit;
} catch (Throwable $e) {
    error_log('Cloud Guru Google Drive connect: ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hubungkan Google Drive</title><body style="font-family:Arial,sans-serif;max-width:700px;margin:50px auto;padding:20px"><h2>Google Drive belum bisa dihubungkan</h2><p>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</p><p>Periksa konfigurasi Google Drive pada server lalu coba lagi.</p><p><a href="/storage/index.php">Kembali ke Penyimpanan Saya</a></p></body></html>';
}
