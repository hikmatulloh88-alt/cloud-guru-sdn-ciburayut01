<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
wajib_login();
require_once __DIR__ . '/../config/google_drive_lib.php';

try {
    $uid = gdrive_user_id();
    $id = trim((string)($_GET['id'] ?? ''));
    if ($uid < 1 || $id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        http_response_code(404);
        exit;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $connection = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$connection || empty($connection['refresh_token_enc'])) {
        http_response_code(404);
        exit;
    }

    // Only proxy thumbnails for files belonging to this user's Cloud Guru Drive folders.
    $meta = gdrive_api(
        $uid,
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?fields=id,mimeType,thumbnailLink,parents'
    );
    if (!is_array($meta) || ($meta['id'] ?? '') !== $id || strpos((string)($meta['mimeType'] ?? ''), 'image/') !== 0) {
        http_response_code(404);
        exit;
    }

    $allowedParents = array_filter([
        (string)($connection['root_folder_id'] ?? ''),
        (string)($connection['administrasi_folder_id'] ?? ''),
        (string)($connection['perencanaan_folder_id'] ?? ''),
        (string)($connection['asesmen_folder_id'] ?? ''),
    ]);
    if (!array_intersect((array)($meta['parents'] ?? []), $allowedParents)) {
        http_response_code(404);
        exit;
    }

    $thumbnailUrl = (string)($meta['thumbnailLink'] ?? '');
    if ($thumbnailUrl === '' || !preg_match('#^https://#i', $thumbnailUrl)) {
        http_response_code(404);
        exit;
    }

    $bytes = gdrive_api($uid, 'GET', $thumbnailUrl);
    if (!is_string($bytes) || $bytes === '') {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=900');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (Throwable $e) {
    error_log('Cloud Guru Drive thumbnail: ' . $e->getMessage());
    http_response_code(404);
    exit;
}
