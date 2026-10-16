<?php
declare(strict_types=1);
/* Cloud Guru Google Drive OAuth callback. Secrets remain in config/google_drive.php. */
require_once __DIR__ . '/../includes/auth.php';
wajib_login();
require_once __DIR__ . '/../config/google_drive_lib.php';

function gdrive_cb_b64url_decode(string $value) {
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    return $decoded === false ? false : $decoded;
}
function gdrive_cb_table(): void {
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
}
$error = '';
try {
    $userId = gdrive_user_id();
    if ($userId <= 0) throw new RuntimeException('Identitas akun Cloud Guru tidak ditemukan.');
    gdrive_cb_table();
    if (!empty($_GET['error'])) throw new RuntimeException('Google membatalkan koneksi: ' . preg_replace('/[^a-zA-Z0-9_ -]/', '', (string)$_GET['error']));
    $code = trim((string)($_GET['code'] ?? ''));
    $state = trim((string)($_GET['state'] ?? ''));
    if ($code === '' || $state === '') throw new RuntimeException('Kode atau state OAuth tidak diterima. Mulai lagi dari tombol Hubungkan Google Drive.');
    $parts = explode('.', $state, 2);
    if (count($parts) !== 2) throw new RuntimeException('State OAuth tidak valid.');
    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, GOOGLE_DRIVE_TOKEN_KEY);
    if (!hash_equals($expected, $signature)) throw new RuntimeException('Tanda tangan state OAuth tidak valid.');
    $payloadJson = gdrive_cb_b64url_decode($encoded);
    $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;
    if (!is_array($payload) || (int)($payload['uid'] ?? 0) !== $userId || (int)($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('State OAuth kedaluwarsa atau bukan milik akun yang sedang login. Hubungkan ulang dari akun ini.');
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, verifier_enc FROM google_drive_oauth_transactions WHERE user_id = ? AND state_value = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $state]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaction) throw new RuntimeException('Transaksi OAuth tidak ditemukan untuk akun ini. Klik Hubungkan Google Drive lagi.');
    $verifier = gdrive_decrypt((string)$transaction['verifier_enc']);
    if (!is_string($verifier) || $verifier === '') throw new RuntimeException('Verifikasi OAuth tidak dapat dibaca. Hubungkan ulang.');
    $body = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_DRIVE_CLIENT_ID,
        'client_secret' => GOOGLE_DRIVE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_DRIVE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'code_verifier' => $verifier
    ]);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $curlError !== '') throw new RuntimeException('Gagal menghubungi Google OAuth: ' . $curlError);
    $tokens = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($tokens) || empty($tokens['access_token'])) {
        throw new RuntimeException('Google menolak pertukaran token (HTTP ' . $httpCode . '). Periksa redirect URI dan Client ID di konfigurasi server.');
    }
    $old = gdrive_connection($userId);
    $refreshToken = (string)($tokens['refresh_token'] ?? '');
    if ($refreshToken === '' && is_array($old)) $refreshToken = (string)($old['refresh_token_enc'] ?? '');
    if ($refreshToken === '') throw new RuntimeException('Google tidak mengirim refresh token. Cabut akses aplikasi Cloud Guru dari akun Google lalu hubungkan kembali.');
    $googleEmail = '';
    $googleName = '';
    try {
        $userInfo = gdrive_api($userId, 'https://www.googleapis.com/oauth2/v2/userinfo');
        $googleEmail = (string)($userInfo['email'] ?? '');
        $googleName = (string)($userInfo['name'] ?? '');
    } catch (Throwable $ignored) {}
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, (int)($tokens['expires_in'] ?? 3600)));
    $pdo->prepare('INSERT INTO google_drive_connections (user_id, google_email, google_name, refresh_token_enc, access_token_enc, token_expires_at, root_folder_id, folder_admin_id, folder_planning_id, folder_assessment_id) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL) ON DUPLICATE KEY UPDATE google_email=VALUES(google_email), google_name=VALUES(google_name), refresh_token_enc=VALUES(refresh_token_enc), access_token_enc=VALUES(access_token_enc), token_expires_at=VALUES(token_expires_at)')
        ->execute([$userId, $googleEmail, $googleName, gdrive_encrypt($refreshToken), gdrive_encrypt((string)$tokens['access_token']), $expiresAt]);
    $pdo->prepare('DELETE FROM google_drive_oauth_transactions WHERE user_id = ?')->execute([$userId]);
    header('Location: /storage/index.php?gdrive=connected', true, 302);
    exit;
} catch (Throwable $e) {
    error_log('Cloud Guru Google Drive callback: ' . $e->getMessage());
    $error = $e->getMessage();
}
http_response_code(400);
?><!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Koneksi Google Drive</title><body style="font-family:Arial,sans-serif;max-width:700px;margin:40px auto;padding:20px"><h2>Koneksi Google Drive belum selesai</h2><p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><p><a href="/gdrive/connect.php">Coba hubungkan lagi</a> · <a href="/storage/index.php">Kembali ke Penyimpanan Saya</a></p></body></html>
