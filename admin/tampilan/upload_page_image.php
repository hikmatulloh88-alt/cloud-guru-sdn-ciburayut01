<?php
declare(strict_types=1);

/*
 * Upload a photo directly from the page editor.
 * The response URL is placed into the existing "Gambar URL" field.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function cg_page_image_json(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

wajib_login();

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['admin', 'administrator'], true)) {
    cg_page_image_json(403, ['ok' => false, 'message' => 'Hanya Administrator yang boleh mengunggah gambar halaman.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['csrf'])) {
    cg_page_image_json(200, ['ok' => true, 'csrf_token' => cloudguru_csrf_token()]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: GET, POST');
    cg_page_image_json(405, ['ok' => false, 'message' => 'Metode permintaan tidak didukung.']);
}

cloudguru_csrf_require_valid();

$file = $_FILES['image'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    cg_page_image_json(400, ['ok' => false, 'message' => 'Pilih file gambar terlebih dahulu.']);
}

$maxBytes = 8 * 1024 * 1024;
if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > $maxBytes) {
    cg_page_image_json(413, ['ok' => false, 'message' => 'Ukuran gambar maksimal 8 MB.']);
}

$tmp = (string)($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    cg_page_image_json(400, ['ok' => false, 'message' => 'File upload tidak valid.']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmp);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
if (!isset($allowed[$mime])) {
    cg_page_image_json(415, ['ok' => false, 'message' => 'Format yang didukung: JPG, PNG, WEBP, atau GIF.']);
}

$imageInfo = @getimagesize($tmp);
if (!$imageInfo || (int)($imageInfo[0] ?? 0) < 1 || (int)($imageInfo[1] ?? 0) < 1) {
    cg_page_image_json(415, ['ok' => false, 'message' => 'File tidak terbaca sebagai gambar yang valid.']);
}
$width = (int)$imageInfo[0];
$height = (int)$imageInfo[1];
if ($width > 10000 || $height > 10000 || ($width * $height) > 40000000) {
    cg_page_image_json(413, ['ok' => false, 'message' => 'Resolusi gambar terlalu besar. Gunakan gambar maksimal 10.000 × 10.000 piksel dan 40 megapiksel.']);
}

$uploadDir = dirname(__DIR__, 2) . '/uploads/builder/pages';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    error_log('Cloud Guru page image upload: could not create uploads/builder/pages');
    cg_page_image_json(500, ['ok' => false, 'message' => 'Folder upload tidak dapat dibuat. Periksa izin folder uploads di hosting.']);
}

/* Prevent script execution and directory listing inside this image-only folder. */
$htaccess = $uploadDir . '/.htaccess';
if (!is_file($htaccess)) {
    $rules = "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9]*)$\">\n  Require all denied\n</FilesMatch>\n";
    @file_put_contents($htaccess, $rules, LOCK_EX);
}

try {
    $filename = 'page_' . date('Ymd_His') . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
} catch (Throwable $e) {
    cg_page_image_json(500, ['ok' => false, 'message' => 'Nama file tidak dapat dibuat. Coba lagi.']);
}

$destination = $uploadDir . '/' . $filename;
if (!move_uploaded_file($tmp, $destination)) {
    error_log('Cloud Guru page image upload: move_uploaded_file failed');
    cg_page_image_json(500, ['ok' => false, 'message' => 'Gambar gagal disimpan. Periksa izin folder uploads/builder/pages.']);
}
@chmod($destination, 0644);

$url = '/uploads/builder/pages/' . $filename;
cg_page_image_json(200, [
    'ok' => true,
    'url' => $url,
    'name' => $filename,
    'mime' => $mime,
    'size' => (int)$file['size'],
    'message' => 'Gambar berhasil diunggah dan URL sudah diisi otomatis.',
]);
