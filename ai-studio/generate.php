<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) ||
            !empty($_SESSION['user']) || !empty($_SESSION['username']) ||
            !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) reply(401, ['error' => 'Sesi login tidak ditemukan. Silakan login ulang ke Cloud Guru.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['error' => 'Gunakan POST.']);
if (!extension_loaded('curl') || !extension_loaded('fileinfo')) reply(500, ['error' => 'Ekstensi PHP cURL dan fileinfo harus aktif.']);
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) reply(400, ['error' => 'Foto wajib dipilih.']);

$duration = (string)($_POST['duration'] ?? '5');
if (!in_array($duration, ['5', '10'], true)) reply(422, ['error' => 'Durasi harus 5 atau 10 detik.']);
$motion = trim((string)($_POST['motion'] ?? 'Gerakan natural, realistis, sinematik lembut'));
$prompt = trim((string)($_POST['prompt'] ?? ''));
$prompt = trim($motion . '. ' . $prompt);
if ($prompt === '' || mb_strlen($prompt) > 2000) reply(422, ['error' => 'Prompt wajib diisi dan maksimal 2000 karakter.']);

$file = $_FILES['image'];
if ((int)$file['size'] < 1 || (int)$file['size'] > 12 * 1024 * 1024) reply(413, ['error' => 'Ukuran foto harus di bawah 12 MB.']);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) reply(415, ['error' => 'Format foto harus JPG, PNG, atau WebP.']);

$apiKey = getenv('FAL_KEY') ?: '';
if ($apiKey === '') {
    $privateConfig = dirname(__DIR__) . '/config/ai_video.php';
    if (is_file($privateConfig)) {
        $cfg = require $privateConfig;
        if (is_array($cfg)) $apiKey = (string)($cfg['fal_key'] ?? '');
    }
}
if ($apiKey === '') reply(503, ['error' => 'API key belum disetel. Atur FAL_KEY di environment server atau config/ai_video.php (di luar Git).']);

$binary = file_get_contents($file['tmp_name']);
if ($binary === false) reply(500, ['error' => 'Foto tidak dapat dibaca.']);
$imageData = 'data:' . $mime . ';base64,' . base64_encode($binary);
$payload = json_encode([
    'prompt' => $prompt,
    'start_image_url' => $imageData,
    'duration' => $duration,
    'negative_prompt' => 'blur, distort, flicker, deformed face, extra fingers, low quality',
    'generate_audio' => false,
    'enable_safety_checker' => true
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false) reply(500, ['error' => 'Gagal menyiapkan data foto.']);

$ch = curl_init('https://queue.fal.run/fal-ai/kling-video/v2.6/pro/image-to-video');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Key ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 45
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($response === false) reply(502, ['error' => 'Koneksi ke antrean AI gagal: ' . $curlError]);
$data = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
    $detail = is_array($data) ? (string)($data['detail'] ?? $data['error'] ?? 'Penyedia AI menolak permintaan.') : 'Respons penyedia AI tidak valid.';
    reply(502, ['error' => 'Layanan AI (' . $httpCode . '): ' . mb_substr($detail, 0, 350)]);
}
$requestId = (string)($data['request_id'] ?? '');
if ($requestId === '' || !preg_match('/^[a-zA-Z0-9-]{8,100}$/', $requestId)) {
    reply(502, ['error' => 'Layanan AI tidak mengembalikan ID proses yang valid.']);
}
$_SESSION['ai_studio_request_id'] = $requestId;
reply(202, ['status' => 'queued', 'request_id' => $requestId, 'message' => 'Video masuk antrean AI.']);
