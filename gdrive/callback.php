<?php
declare(strict_types=1);

/* OAuth callback Cloud Guru. Rahasia tetap di config/google_drive.php di server. */
require_once __DIR__ . '/../includes/auth.php';
wajib_login();
require_once __DIR__ . '/../config/google_drive_lib.php';

function gdrive_callback_b64url_decode(string $value) {
    $raw = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    return $raw === false ? false : $raw;
}
function gdrive_callback_ensure_table(): void {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS google_drive_oauth_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        verifier_enc TEXT NOT NULL,
        state_value VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX idx_gdrive_oauth_user_exp (user_id, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$error = '';
try {
    $userId = (int) gdrive_user_id();
    if ($userId < 1) throw new RuntimeException('Identitas akun tidak ditemukan. Login ulang lalu hubungkan Google Drive.');
    gdrive_callback_ensure_table();
    if (!empty($_GET['error'])) throw new RuntimeException('Google membatalkan koneksi: ' . preg_replace('/[^a-zA-Z0-9_ -]/', '', (string) $_GET['error']));
    $code = trim((string) ($_GET['code'] ?? ''));
    $state = trim((string) ($_GET['state'] ?? ''));
    if ($code === '' || $state === '') throw new RuntimeException('Kode atau state OAuth tidak diterima. Mulai kembali dari tombol Hubungkan Google Drive.');

    $parts = explode('.', $state, 2);
    if (count($parts) !== 2) throw new RuntimeException('State OAuth tidak valid.');
    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, GOOGLE_DRIVE_TOKEN_KEY);
    if (!hash_equals($expected, $signature)) throw new RuntimeException('Tanda tangan state OAuth tidak valid.');
    $payloadJson = gdrive_callback_b64url_decode($encoded);
    $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;
    if (!is_array($payload) || (int)($payload['uid'] ?? 0) !== $userId || (int)($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('State OAuth kedaluwarsa atau tidak sesuai akun. Hubungkan ulang dari akun ini.');
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT id, verifier_enc FROM google_drive_oauth_transactions WHERE user_id = ? AND state_value = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $state]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) throw new RuntimeException('Transaksi OAuth tidak ditemukan. Klik Hubungkan Google Drive lagi.');
    $verifier = gdrive_decrypt((string) $transaction['verifier_enc']);
    if (!is_string($verifier) || $verifier === '') throw new RuntimeException('Verifikasi OAuth tidak dapat dibaca. Hubungkan ulang.');

    $body = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_DRIVE_CLIENT_ID,
        'client_secret' => GOOGLE_DRIVE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_DRIVE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'code_verifier' => $verifier,
    ]);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $curlError !== '') throw new RuntimeException('Gagal menghubungi Google OAuth: ' . $curlError);
    $tokens = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($tokens) || empty($tokens['access_token'])) {
        $googleError = is_array($tokens) ? (string)($tokens['error_description'] ?? $tokens['error'] ?? '') : '';
        throw new RuntimeException('Google menolak pertukaran token (HTTP ' . $httpCode . '). ' . substr($googleError, 0, 240) . ' Periksa Client ID dan redirect URI di konfigurasi server.');
    }

    gdrive_store_tokens($userId, $tokens);
    $about = gdrive_about($userId);
    $googleEmail = (string)($about['user']['emailAddress'] ?? '');
    $googleName = (string)($about['user']['displayName'] ?? '');
    $pdo->prepare('UPDATE google_drive_connections SET google_email = ?, google_name = ? WHERE user_id = ?')->execute([$googleEmail, $googleName, $userId]);
    gdrive_ensure_structure($userId);
    $pdo->prepare('DELETE FROM google_drive_oauth_transactions WHERE id = ? AND user_id = ?')->execute([(int)$transaction['id'], $userId]);
    header('Location: /storage/index.php?gdrive=connected', true, 302);
    exit;
} catch (Throwable $e) {
    error_log('Cloud Guru Google Drive callback: ' . $e->getMessage());
    $error = $e->getMessage();
}
http_response_code(400);
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Koneksi Google Drive</title></head><body style="font-family:system-ui,sans-serif;max-width:720px;margin:48px auto;padding:0 18px"><h2>Koneksi Google Drive belum berhasil</h2><p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><p><a href="/storage/index.php">Kembali ke Penyimpanan Saya</a></p></body></html>