<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ai_reply(int $status, array $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) ||
            !empty($_SESSION['user']) || !empty($_SESSION['username']) ||
            !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) ai_reply(401, ['error' => 'Sesi login tidak ditemukan. Silakan login ulang ke Cloud Guru.']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') ai_reply(405, ['error' => 'Gunakan GET.']);

$requestId = (string)($_SESSION['ai_studio_request_id'] ?? '');
if ($requestId === '' || !preg_match('/^[a-zA-Z0-9-]{8,100}$/', $requestId)) {
    ai_reply(404, ['error' => 'Tidak ada proses video aktif di sesi ini.']);
}
$apiKey = getenv('FAL_KEY') ?: '';
if ($apiKey === '') {
    $privateConfig = dirname(__DIR__) . '/config/ai_video.php';
    if (is_file($privateConfig)) {
        $cfg = require $privateConfig;
        if (is_array($cfg)) $apiKey = (string)($cfg['fal_key'] ?? '');
    }
}
if ($apiKey === '') ai_reply(503, ['error' => 'API key belum dikonfigurasi di server.']);

$base = 'https://queue.fal.run/fal-ai/kling-video/v2.6/pro/image-to-video/requests/' . rawurlencode($requestId);
function fal_get(string $url, string $key): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Key ' . $key, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 25
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) ai_reply(502, ['error' => 'Koneksi status AI gagal: ' . $error]);
    $data = json_decode($response, true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        $detail = is_array($data) ? (string)($data['detail'] ?? $data['error'] ?? 'Permintaan status ditolak.') : 'Respons status tidak valid.';
        ai_reply(502, ['error' => 'Layanan AI (' . $code . '): ' . substr($detail, 0, 250)]);
    }
    return $data;
}
try {
    $state = fal_get($base . '/status', $apiKey);
    $status = strtoupper((string)($state['status'] ?? 'UNKNOWN'));
    if ($status === 'COMPLETED') {
        $result = fal_get($base, $apiKey);
        $videoUrl = $result['video']['url'] ?? '';
        if (!is_string($videoUrl) || !preg_match('~^https://~i', $videoUrl)) {
            ai_reply(502, ['error' => 'Proses selesai tetapi URL video tidak tersedia.']);
        }
        unset($_SESSION['ai_studio_request_id']);
        ai_reply(200, ['status' => 'completed', 'video_url' => $videoUrl]);
    }
    if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
        unset($_SESSION['ai_studio_request_id']);
        ai_reply(200, ['status' => strtolower($status), 'error' => 'Penyedia AI tidak berhasil menyelesaikan video.']);
    }
    ai_reply(200, ['status' => strtolower($status), 'queue_position' => $state['queue_position'] ?? null]);
} catch (Throwable $e) {
    ai_reply(502, ['error' => 'Tidak dapat memeriksa proses video.']);
}
