<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function download_error(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) ||
            !empty($_SESSION['user']) || !empty($_SESSION['username']) ||
            !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) download_error(401, 'Silakan login ulang ke Cloud Guru.');
$url = (string)($_SESSION['ai_studio_video_url'] ?? '');
$expires = (int)($_SESSION['ai_studio_video_expires'] ?? 0);
if ($url === '' || $expires < time()) {
    unset($_SESSION['ai_studio_video_url'], $_SESSION['ai_studio_video_expires']);
    download_error(410, 'Video sementara sudah kedaluwarsa. Silakan generate ulang.');
}
$parts = parse_url($url);
if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' ||
    !in_array(strtolower((string)($parts['host'] ?? '')), ['fal.media', 'storage.googleapis.com'], true)) {
    download_error(400, 'Alamat video tidak valid.');
}
if (!extension_loaded('curl')) download_error(500, 'Ekstensi PHP cURL belum aktif.');

$tmp = tempnam(sys_get_temp_dir(), 'cg_ai_');
if ($tmp === false) download_error(500, 'Tidak dapat membuat file sementara.');
$fp = fopen($tmp, 'wb');
if ($fp === false) { @unlink($tmp); download_error(500, 'Tidak dapat menyiapkan file sementara.'); }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_FILE => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_FAILONERROR => true,
]);
$ok = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
fclose($fp);
if ($ok === false || $code < 200 || $code >= 300 || filesize($tmp) < 1) {
    @unlink($tmp);
    download_error(502, 'Pengambilan video gagal dari penyedia AI. ' . $error);
}

header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="cloud-guru-ai-video.mp4"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp);
@unlink($tmp);
// Remove the session reference after the download; the temporary server file is deleted above.
unset($_SESSION['ai_studio_video_url'], $_SESSION['ai_studio_video_expires']);
exit;
