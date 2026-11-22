<?php
declare(strict_types=1);
try {
    require_once __DIR__ . '/../includes/auth.php';
    if (!function_exists('wajib_login')) {
        throw new RuntimeException('Cloud Guru login helper is missing.');
    }
    wajib_login();
    require_once __DIR__ . '/../includes/csrf.php';
    require_once __DIR__ . '/../config/google_drive_lib.php';
} catch (Throwable $bootstrapError) {
    error_log('Cloud Guru Storage bootstrap: ' . $bootstrapError->getMessage());
    http_response_code(503);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Penyimpanan sedang diperbaiki</title><style>body{font:16px system-ui,sans-serif;background:#f4f7fb;color:#172033;margin:0;min-height:100vh;display:grid;place-items:center}.card{max-width:560px;margin:20px;background:#fff;border:1px solid #e0e6ef;border-radius:16px;padding:28px;box-shadow:0 10px 30px #17203312}a{color:#155eef;font-weight:700}</style></head><body><main class="card"><h1>Penyimpanan sedang tidak tersedia</h1><p>Komponen login, database, atau Google Drive belum dapat dimuat. Rincian sudah dicatat di log PHP server.</p><p><a href="/auth/login.php">Kembali ke Login</a></p></main></body></html><?php
    exit;
}

require_once __DIR__ . '/../includes/sidebar.php';

$error = '';
$schemaReady = true;
// Repair legacy Google Drive connection schemas before any page query uses folder columns.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS google_drive_connections (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL UNIQUE,
        google_email VARCHAR(255) DEFAULT NULL,
        google_name VARCHAR(255) DEFAULT NULL,
        refresh_token_enc MEDIUMTEXT DEFAULT NULL,
        access_token_enc MEDIUMTEXT DEFAULT NULL,
        access_token_expires_at DATETIME DEFAULT NULL,
        token_expires_at DATETIME DEFAULT NULL,
        root_folder_id VARCHAR(255) DEFAULT NULL,
        administrasi_folder_id VARCHAR(255) DEFAULT NULL,
        perencanaan_folder_id VARCHAR(255) DEFAULT NULL,
        asesmen_folder_id VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gdrive_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $driveColumns = $pdo->query("SHOW COLUMNS FROM google_drive_connections")->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'root_folder_id' => 'VARCHAR(255) DEFAULT NULL',
        'administrasi_folder_id' => 'VARCHAR(255) DEFAULT NULL',
        'perencanaan_folder_id' => 'VARCHAR(255) DEFAULT NULL',
        'asesmen_folder_id' => 'VARCHAR(255) DEFAULT NULL',
    ] as $driveColumn => $driveDefinition) {
        if (!in_array($driveColumn, $driveColumns, true)) {
            $pdo->exec("ALTER TABLE google_drive_connections ADD COLUMN `$driveColumn` $driveDefinition");
        }
    }
} catch (Throwable $schemaError) {
    error_log('Cloud Guru Drive schema repair: ' . $schemaError->getMessage());
}
try {
    gdrive_require_tables();
} catch (Throwable $schemaError) {
    $schemaReady = false;
    $error = 'Database Google Drive belum siap. Periksa konfigurasi database dan hak akses tabel.';
    error_log('Cloud Guru Drive table initialization: ' . $schemaError->getMessage());
}

$userId = gdrive_user_id();
$folders = [
    'root_folder_id' => 'Semua File',
    'administrasi_folder_id' => 'Administrasi Guru',
    'perencanaan_folder_id' => 'Perencanaan',
    'asesmen_folder_id' => 'Asesmen',
];
$selected = (string)($_GET['folder'] ?? 'root_folder_id');
if (!isset($folders[$selected])) $selected = 'root_folder_id';

$connection = null;
$files = [];
try {
    if (!$schemaReady) throw new RuntimeException('Inisialisasi tabel Google Drive gagal.');
    if ($userId < 1) throw new RuntimeException('ID akun tidak terbaca. Keluar lalu masuk kembali.');
    $q = $pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=? LIMIT 1');
    $q->execute([$userId]);
    $connection = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($connection && !empty($connection['refresh_token_enc'])) {
        gdrive_ensure_structure($userId);
        $q = $pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=? LIMIT 1');
        $q->execute([$userId]);
        $connection = $q->fetch(PDO::FETCH_ASSOC) ?: $connection;
        $parent = $connection[$selected] ?? '';
        if ($parent) {
            $query = "'" . str_replace("'", "\\'", $parent) . "' in parents and trashed=false";
            $result = gdrive_api($userId, 'GET', 'https://www.googleapis.com/drive/v3/files?q=' . rawurlencode($query) . '&pageSize=100&orderBy=folder,name&fields=files(id,name,mimeType,size,modifiedTime,webViewLink)');
            $files = $result['files'] ?? [];
        }
    }
} catch (Throwable $e) {
    if ($error === '') $error = $e->getMessage();
    error_log('Cloud Guru storage: ' . $e->getMessage());
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function gdrive_file_size($value): string {
    if ($value === null || $value === '' || !is_numeric($value)) return '—';
    $size = max(0, (float)$value);
    if ($size < 1024) return number_format($size, 0) . ' B';
    $units = ['KB', 'MB', 'GB', 'TB'];
    $size /= 1024;
    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'TB') return number_format($size, 1) . ' ' . $unit;
        $size /= 1024;
    }
    return '—';
}
function gdrive_file_category(string $mime): string {
    if (strpos($mime, 'image/') === 0) return 'image';
    if (
        $mime === 'application/pdf' ||
        strpos($mime, 'word') !== false ||
        strpos($mime, 'document') !== false ||
        strpos($mime, 'text/') === 0
    ) return 'doc';
    if (
        strpos($mime, 'spreadsheet') !== false ||
        strpos($mime, 'excel') !== false ||
        strpos($mime, 'presentation') !== false ||
        strpos($mime, 'powerpoint') !== false
    ) return 'sheet';
    return 'other';
}
function gdrive_file_type_label(string $mime, string $name): string {
    if ($mime === 'application/vnd.google-apps.folder') return 'Folder';
    if (strpos($mime, 'image/') === 0) return 'Gambar';
    if (strpos($mime, 'video/') === 0) return 'Video';
    if (strpos($mime, 'audio/') === 0) return 'Audio';
    $labels = [
        'application/pdf' => 'PDF',
        'application/vnd.google-apps.document' => 'Google Docs',
        'application/vnd.google-apps.spreadsheet' => 'Google Sheets',
        'application/vnd.google-apps.presentation' => 'Google Slides',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
        'application/msword' => 'Word',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
        'application/vnd.ms-excel' => 'Excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
        'text/plain' => 'Teks',
        'application/zip' => 'ZIP',
        'application/x-rar-compressed' => 'RAR',
    ];
    if (isset($labels[$mime])) return $labels[$mime];
    $ext = strtoupper((string)pathinfo($name, PATHINFO_EXTENSION));
    return $ext !== '' ? $ext : 'File';
}
function gdrive_file_icon(string $mime, bool $isFolder = false): string {
    if ($isFolder || $mime === 'application/vnd.google-apps.folder') return '📁';
    if (strpos($mime, 'image/') === 0) return '🖼️';
    if ($mime === 'application/pdf') return '📕';
    if (strpos($mime, 'spreadsheet') !== false || strpos($mime, 'excel') !== false) return '📊';
    if (strpos($mime, 'presentation') !== false || strpos($mime, 'powerpoint') !== false) return '📽️';
    if (strpos($mime, 'word') !== false || strpos($mime, 'document') !== false || strpos($mime, 'text/') === 0) return '📝';
    if (strpos($mime, 'video/') === 0) return '🎬';
    if (strpos($mime, 'audio/') === 0) return '🎵';
    if (strpos($mime, 'zip') !== false || strpos($mime, 'rar') !== false) return '🗜️';
    return '📄';
}
$isConnected = ($connection && !empty($connection['refresh_token_enc']));
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($folders[$selected]) ?> — Penyimpanan Cloud Guru</title>
<link rel="stylesheet" href="/assets/css/glass-ui.css?v=4">
<style>
:root{
  color-scheme:light;
  --ink:#0f172a;
  --muted:#64748b;
  --line:#e2e8f0;
  --blue:#1d4ed8;
  --sky:#0284c7;
  --surface:rgba(255,255,255,.86);
}
*{box-sizing:border-box}
body{
  min-height:100vh;margin:0;
  background:
    radial-gradient(ellipse at 10% 0%,rgba(56,189,248,.14),transparent 36%),
    radial-gradient(ellipse at 92% 80%,rgba(37,99,235,.09),transparent 36%),
    #f0f5fc;
  color:var(--ink);
  font:15px/1.55 var(--cg-font,'Plus Jakarta Sans',system-ui,-apple-system,sans-serif);
}
a{color:inherit}
button,input{font:inherit}
.cg-main-content{min-height:100vh;padding:30px 38px 64px}
.wrap{max-width:1160px;margin:0 auto}

/* Hero */
.hero{
  position:relative;display:flex;align-items:center;justify-content:space-between;
  overflow:hidden;padding:30px 36px;border-radius:24px;
  background:
    radial-gradient(circle at 86% 25%,rgba(56,189,248,.35),transparent 35%),
    linear-gradient(120deg,#09244b 0%,#0f4c9c 56%,#1677c5 100%);
  color:#fff;box-shadow:0 20px 46px rgba(11,45,92,.17);
}
.hero:after{
  position:absolute;content:"";width:260px;height:260px;right:-60px;bottom:-140px;
  border:1px solid rgba(255,255,255,.18);border-radius:50%;pointer-events:none;
}
.hero-copy{position:relative;z-index:1;max-width:650px}
.eyebrow{
  display:inline-flex;align-items:center;gap:6px;padding:5px 11px;margin-bottom:10px;
  border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  color:#e0f2fe;font-size:11px;font-weight:800;letter-spacing:.08em;
}
.hero h1{margin:0;font-size:clamp(25px,3.6vw,36px);font-weight:800;line-height:1.15;letter-spacing:-.03em}
.hero p{margin:8px 0 0;color:#dbeafe;font-size:14px;max-width:540px}
.hero-art{
  position:relative;z-index:1;display:grid;width:88px;height:88px;flex:none;place-items:center;
  border:1px solid rgba(255,255,255,.32);border-radius:24px;background:rgba(255,255,255,.13);
  backdrop-filter:blur(10px);color:#fff;
}
.hero-art svg{width:42px;height:42px}

/* Status Card & Panels */
.status-card,.panel{
  border:1px solid rgba(255,255,255,.9);border-radius:20px;background:var(--surface);
  box-shadow:0 12px 32px rgba(15,35,68,.06);backdrop-filter:blur(16px);
}
.status-card{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:18px 0;padding:16px 22px}
.status-copy{display:flex;align-items:center;gap:14px;min-width:0}
.status-icon{display:grid;width:44px;height:44px;flex:none;place-items:center;border-radius:13px;background:#eff6ff;color:#1d4ed8}
.status-icon svg{width:22px;height:22px}
.status-copy strong{display:block;font-size:14.5px;font-weight:700}
.status-pill{display:inline-flex;align-items:center;gap:7px;margin-top:3px;color:#059669;font-size:12.5px;font-weight:700}
.status-pill:before{width:8px;height:8px;border-radius:50%;background:#10b981;content:""}
.status-pill.disconnected{color:#d97706}
.status-pill.disconnected:before{background:#f59e0b}

.btn,button{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 18px;
  border:0;border-radius:12px;background:linear-gradient(115deg,#0f4c9c,#1d70c9);color:#fff;
  text-decoration:none;font-size:13.5px;font-weight:700;white-space:nowrap;cursor:pointer;
  box-shadow:0 8px 20px rgba(15,76,156,.2);transition:all .18s ease;
}
.btn:hover,button:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(15,76,156,.26);filter:brightness(1.04)}
button:disabled{opacity:.6;cursor:wait;transform:none}

.alerts{display:grid;gap:10px;margin:14px 0}
.notice{margin:0;padding:12px 16px;border:1px solid #a7f3d0;border-radius:13px;background:#ecfdf5;color:#065f46;font-size:13.5px;font-weight:600}
.notice.error{border-color:#fecaca;background:#fef2f2;color:#991b1b}

.panel{margin-top:18px;padding:24px}
.panel-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.panel-heading h2{margin:0;font-size:18px;font-weight:800;letter-spacing:-.02em}
.panel-heading p{margin:3px 0 0;color:var(--muted);font-size:13px}

/* Drag & Drop Upload */
.dropzone{
  position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:26px 20px;border:2px dashed #93c5fd;border-radius:18px;
  background:linear-gradient(145deg,#f8fbff,#eff6ff);cursor:pointer;transition:all .2s ease;
}
.dropzone:hover,.dropzone.is-dragover{
  border-color:#1d4ed8;background:#e0f2fe;transform:translateY(-1px);
  box-shadow:0 10px 25px rgba(29,78,216,.1);
}
.dropzone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.dz-icon{
  width:48px;height:48px;border-radius:15px;background:#fff;color:#1d4ed8;
  display:grid;place-items:center;margin-bottom:10px;box-shadow:0 6px 16px rgba(29,78,216,.12);
}
.dz-icon svg{width:24px;height:24px}
.dz-title{font-size:14.5px;font-weight:700;color:#0f172a}
.dz-sub{font-size:12.5px;color:var(--muted);margin-top:3px}
.upload-bar{
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  margin-top:14px;padding:12px 16px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;
}
.file-chip{display:flex;align-items:center;gap:10px;min-width:0;flex:1}
.file-chip-badge{
  padding:5px 9px;border-radius:8px;background:#dbeafe;color:#1e40af;font-size:11px;font-weight:800;flex:none;
}
.file-chip-name{font-size:13px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.file-chip-size{font-size:12px;color:var(--muted);flex:none}
.helper{display:flex;align-items:center;gap:7px;margin:10px 2px 0;color:var(--muted);font-size:12px}

/* Folder Tabs & Search/Filter Toolbar */
.folder-nav{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 10px}
.folder-nav form{flex:none;margin:0}
.folder-tab{
  min-height:40px;padding:8px 15px;border:1px solid #e2e8f0;border-radius:12px;
  background:#fff;color:#475569;font-size:13px;font-weight:700;box-shadow:none;
}
.folder-tab:hover{border-color:#93c5fd;background:#f0f9ff;color:#1d4ed8;box-shadow:none;transform:none}
.folder-tab.active{
  border-color:#1d4ed8;background:#eff6ff;color:#1d4ed8;
  box-shadow:inset 0 0 0 1px #bfdbfe;
}

.toolbar{
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  margin-top:14px;padding-top:14px;border-top:1px solid #eef2f6;
}
.search-box{position:relative;flex:1;min-width:220px;max-width:360px}
.search-box input{
  width:100%;min-height:40px;padding:8px 34px 8px 36px;border:1px solid #cbd5e1;
  border-radius:11px;background:#fff;color:var(--ink);font-size:13px;transition:border-color .18s ease;
}
.search-box input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.search-box svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;pointer-events:none}
.search-clear{
  position:absolute;right:8px;top:50%;transform:translateY(-50%);min-height:0;padding:2px 6px;
  background:transparent;color:#94a3b8;box-shadow:none;font-size:15px;display:none;
}
.search-clear:hover{background:#f1f5f9;color:#0f172a;box-shadow:none;transform:translateY(-50%)}

.filter-pills{display:flex;gap:6px;flex-wrap:wrap}
.filter-pill{
  min-height:34px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:999px;
  background:#fff;color:#64748b;font-size:12px;font-weight:700;box-shadow:none;
}
.filter-pill:hover{border-color:#cbd5e1;background:#f8fafc;color:#0f172a;box-shadow:none;transform:none}
.filter-pill.is-active{background:#0f4c9c;border-color:#0f4c9c;color:#fff}

.file-count{
  display:inline-flex;align-items:center;min-height:30px;padding:4px 12px;
  border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700;white-space:nowrap;
}

/* File Grid */
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:16px;margin-top:18px}
.drive-file{
  position:relative;min-width:0;overflow:visible;border:1px solid #e2e8f0;border-radius:16px;
  background:#fff;transition:all .2s ease;
}
.drive-file:hover{z-index:2;border-color:#93c5fd;transform:translateY(-3px);box-shadow:0 14px 28px rgba(15,76,156,.1)}
.drive-preview{position:relative;height:146px;overflow:hidden;border-radius:15px 15px 0 0;background:#f1f5f9}
.drive-preview img{display:block;width:100%;height:100%;object-fit:cover}
.drive-icon{display:grid;width:100%;height:100%;place-items:center;background:radial-gradient(circle at 50% 35%,#fff 0,#eff6ff 80%);font-size:46px}
.drive-preview-link{position:absolute;inset:0;z-index:1;display:block}
.drive-file-info{min-height:82px;padding:12px 44px 14px 14px;border-top:1px solid #f1f5f9}
.drive-file-name{
  display:-webkit-box;overflow:hidden;color:#0f172a;font-size:13.5px;font-weight:700;line-height:1.4;
  text-decoration:none;overflow-wrap:anywhere;-webkit-box-orient:vertical;-webkit-line-clamp:2;
}
.drive-file-name:hover{color:var(--blue)}
.drive-meta{margin-top:6px;color:#64748b;font-size:11.5px;font-weight:600;overflow-wrap:anywhere}

.drive-actions{position:absolute;right:8px;bottom:14px;z-index:5}
.drive-actions summary{
  display:grid;width:32px;height:32px;place-items:center;border-radius:10px;color:#64748b;
  font-size:20px;line-height:1;list-style:none;cursor:pointer;
}
.drive-actions summary::-webkit-details-marker{display:none}
.drive-actions summary:hover,.drive-actions[open] summary{background:#eff6ff;color:#1d4ed8}
.drive-menu{
  position:absolute;right:0;bottom:38px;display:flex;min-width:165px;flex-direction:column;gap:3px;
  padding:6px;border:1px solid #e2e8f0;border-radius:13px;background:#fff;
  box-shadow:0 14px 32px rgba(15,23,42,.15);
}
.drive-menu a,.drive-menu button{
  display:block;width:100%;min-height:0;padding:9px 11px;border:0;border-radius:9px;
  background:transparent;color:#334155;font-size:12.5px;font-weight:600;text-align:left;
  text-decoration:none;box-shadow:none;
}
.drive-menu a:hover,.drive-menu button:hover{background:#f1f5f9;color:#0f172a;transform:none;box-shadow:none}
.drive-menu form{margin:0}
.drive-menu .danger{color:#dc2626}
.drive-menu .danger:hover{background:#fef2f2;color:#b91c1c}

.empty-state{
  display:grid;justify-items:center;gap:8px;padding:42px 18px;border:1.5px dashed #cbd5e1;
  border-radius:16px;background:#f8fafc;color:#64748b;text-align:center;margin-top:16px;
}
.empty-icon{display:grid;width:58px;height:58px;place-items:center;margin-bottom:4px;border-radius:18px;background:#eff6ff;font-size:28px}
.empty-state strong{color:#0f172a;font-size:15px}
.empty-state p{margin:0;font-size:13px}

@media(max-width:900px){
  .cg-main-content{padding:22px 18px 56px}
  .hero{padding:24px 22px}
  .hero-art{display:none}
  .status-card{flex-direction:column;align-items:flex-start}
  .status-card>.btn{width:100%}
  .drive-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
}
@media(max-width:480px){
  .panel{padding:18px}
  .upload-bar{flex-direction:column;align-items:stretch}
  .upload-bar button{width:100%}
  .drive-preview{height:115px}
}
</style>
</head>
<body class="storage-page">
<main class="cg-main-content">
<div class="wrap">
  <section class="hero">
    <div class="hero-copy">
      <div class="eyebrow">GOOGLE DRIVE TERINTEGRASI</div>
      <h1><?= h($folders[$selected]) ?></h1>
      <p>Simpan, cari, dan kelola dokumen sekolah dengan praktis. Semua berkas tersimpan aman di akun Google Drive Anda.</p>
    </div>
    <div class="hero-art" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
    </div>
  </section>

  <section class="status-card" aria-label="Status Google Drive">
    <div class="status-copy">
      <span class="status-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
      </span>
      <div>
        <strong>Koneksi Google Drive</strong>
        <?php if ($isConnected): ?>
          <span class="status-pill">Terhubung<?= !empty($connection['google_email']) ? ' · ' . h($connection['google_email']) : '' ?></span>
        <?php else: ?>
          <span class="status-pill disconnected">Belum terhubung — Hubungkan untuk mulai mengunggah</span>
        <?php endif; ?>
      </div>
    </div>
    <a class="btn" href="/gdrive/connect.php"><?= $isConnected ? 'Hubungkan Ulang' : 'Hubungkan Google Drive' ?></a>
  </section>

  <div class="alerts" aria-live="polite">
    <?php if (isset($_GET['gdrive']) && $_GET['gdrive'] === 'connected'): ?><p class="notice">✓ Google Drive berhasil dihubungkan.</p><?php endif; ?>
    <?php if (isset($_GET['uploaded']) && $_GET['uploaded'] === '1'): ?><p class="notice">✓ File berhasil diunggah ke folder <?= h($folders[$selected]) ?>.</p><?php endif; ?>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?><p class="notice">✓ File berhasil dihapus dari Google Drive.</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice error">⚠ <?= h($error) ?></p><?php endif; ?>
  </div>

  <?php if ($isConnected): ?>
  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Unggah Dokumen Baru</h2>
        <p>Seret file ke kotak di bawah atau klik untuk memilih file dari perangkat Anda.</p>
      </div>
      <span class="file-count">Folder tujuan: <?= h($folders[$selected]) ?></span>
    </div>
    <form id="uploadForm" action="/storage/upload.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h(cloudguru_csrf_token()) ?>">
      <input type="hidden" name="folder" value="<?= h($selected) ?>">
      <div class="dropzone" id="dropZone">
        <input id="fileInput" type="file" name="file" required aria-label="Pilih file untuk diunggah">
        <div class="dz-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <div class="dz-title">Seret &amp; lepas file di sini, atau klik untuk memilih</div>
        <div class="dz-sub">Mendukung PDF, Word, Excel, PowerPoint, Gambar, dan Arsip ZIP</div>
      </div>
      <div class="upload-bar" id="uploadBar" style="display:none">
        <div class="file-chip">
          <span class="file-chip-badge" id="fileExtBadge">FILE</span>
          <span class="file-chip-name" id="fileNameLabel">-</span>
          <span class="file-chip-size" id="fileSizeLabel"></span>
        </div>
        <button type="submit" id="uploadSubmitBtn">↑ Unggah Sekarang</button>
      </div>
    </form>
    <div class="helper">🔒 File disimpan langsung ke folder pribadi Google Drive Anda dan hanya dapat diakses melalui akun Anda.</div>
  </section>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Jelajahi Folder</h2>
        <p>Pindah antarfolder kerja atau cari dokumen secara instan.</p>
      </div>
      <?php if ($isConnected): ?>
        <span class="file-count" id="visibleCount"><?= count($files) ?> item</span>
      <?php endif; ?>
    </div>

    <nav class="folder-nav" aria-label="Pilih folder">
      <?php foreach ($folders as $key => $label): ?>
        <form method="get">
          <button class="folder-tab <?= $selected === $key ? 'active' : '' ?>" type="submit" name="folder" value="<?= h($key) ?>" <?= $selected === $key ? 'aria-current="page"' : '' ?>><?= h($label) ?></button>
        </form>
      <?php endforeach; ?>
    </nav>

    <?php if ($isConnected): ?>
      <?php if ($files): ?>
      <div class="toolbar">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="fileSearch" type="search" placeholder="Cari nama file di folder ini..." aria-label="Cari nama file">
          <button type="button" class="search-clear" id="searchClear" aria-label="Hapus pencarian">×</button>
        </div>
        <div class="filter-pills" role="group" aria-label="Filter jenis file">
          <button type="button" class="filter-pill is-active" data-filter="all">Semua</button>
          <button type="button" class="filter-pill" data-filter="doc">Dokumen &amp; PDF</button>
          <button type="button" class="filter-pill" data-filter="sheet">Excel &amp; Slide</button>
          <button type="button" class="filter-pill" data-filter="image">Gambar</button>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$files): ?>
        <div class="empty-state">
          <div class="empty-icon" aria-hidden="true">📂</div>
          <strong>Folder <?= h($folders[$selected]) ?> masih kosong</strong>
          <p>Unggah file pertama Anda menggunakan kotak unggah di atas.</p>
        </div>
      <?php else: ?>
        <div class="drive-grid" id="driveGrid">
        <?php foreach ($files as $file):
            $fileId = (string)($file['id'] ?? '');
            $fileName = (string)($file['name'] ?? 'File');
            $fileMime = (string)($file['mimeType'] ?? '');
            $isFolder = $fileMime === 'application/vnd.google-apps.folder';
            $isImage = strpos($fileMime, 'image/') === 0;
            $fileCat = gdrive_file_category($fileMime);
            $fileSize = gdrive_file_size($file['size'] ?? null);
            $openUrl = (string)($file['webViewLink'] ?? '');
            $fallbackUrl = '/storage/download.php?id=' . rawurlencode($fileId);
            $targetUrl = $openUrl !== '' ? $openUrl : $fallbackUrl;
        ?>
          <article class="drive-file" data-name="<?= h(strtolower($fileName)) ?>" data-cat="<?= h($fileCat) ?>">
            <div class="drive-preview">
              <?php if ($isImage): ?>
                <img loading="lazy" src="/storage/thumbnail.php?id=<?= rawurlencode($fileId) ?>" alt="<?= h($fileName) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
              <?php endif; ?>
              <div class="drive-icon" <?= $isImage ? 'style="display:none"' : '' ?>><?= h(gdrive_file_icon($fileMime, $isFolder)) ?></div>
              <a class="drive-preview-link" href="<?= h($targetUrl) ?>" target="_blank" rel="noopener" aria-label="Buka <?= h($fileName) ?>"></a>
            </div>
            <div class="drive-file-info">
              <a class="drive-file-name" href="<?= h($targetUrl) ?>" target="_blank" rel="noopener" title="<?= h($fileName) ?>"><?= h($fileName) ?></a>
              <div class="drive-meta"><?= h(gdrive_file_type_label($fileMime, $fileName)) ?><?php if (!$isFolder && $fileSize !== '—'): ?> · <?= h($fileSize) ?><?php endif; ?></div>
            </div>
            <details class="drive-actions">
              <summary aria-label="Opsi file">⋮</summary>
              <div class="drive-menu">
                <a href="<?= h($fallbackUrl) ?>">⬇ Unduh File</a>
                <?php if ($openUrl !== ''): ?><a href="<?= h($openUrl) ?>" target="_blank" rel="noopener">↗ Buka di Drive</a><?php endif; ?>
                <form method="post" action="/storage/delete.php" onsubmit="return confirm('Hapus file ini secara permanen dari Google Drive?')">
                  <input type="hidden" name="csrf_token" value="<?= h(cloudguru_csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= h($fileId) ?>">
                  <input type="hidden" name="folder" value="<?= h($selected) ?>">
                  <button class="danger" type="submit">🗑 Hapus File</button>
                </form>
              </div>
            </details>
          </article>
        <?php endforeach; ?>
        </div>
        <div class="empty-state" id="noFilterMatch" style="display:none">
          <div class="empty-icon" aria-hidden="true">🔍</div>
          <strong>Tidak ada file yang cocok</strong>
          <p>Coba ubah kata kunci pencarian atau pilih filter "Semua".</p>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon" aria-hidden="true">☁️</div>
        <strong>Hubungkan Google Drive untuk mulai</strong>
        <p>Klik tombol "Hubungkan Google Drive" di atas agar folder Administrasi, Perencanaan, dan Asesmen siap digunakan.</p>
      </div>
    <?php endif; ?>
  </section>
</div>
</main>

<script>
(function(){
  // Drag & drop upload preview
  var dropZone = document.getElementById('dropZone');
  var fileInput = document.getElementById('fileInput');
  var uploadBar = document.getElementById('uploadBar');
  var fileNameLabel = document.getElementById('fileNameLabel');
  var fileSizeLabel = document.getElementById('fileSizeLabel');
  var fileExtBadge = document.getElementById('fileExtBadge');
  var uploadForm = document.getElementById('uploadForm');
  var uploadSubmitBtn = document.getElementById('uploadSubmitBtn');

  function formatBytes(bytes){
    if (!bytes) return '0 B';
    var units = ['B','KB','MB','GB'];
    var i = 0;
    while (bytes >= 1024 && i < units.length - 1){ bytes /= 1024; i++; }
    return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
  }

  function updateSelectedFile(){
    if (!fileInput || !fileInput.files || !fileInput.files.length){
      if (uploadBar) uploadBar.style.display = 'none';
      return;
    }
    var f = fileInput.files[0];
    var parts = f.name.split('.');
    var ext = parts.length > 1 ? parts.pop().toUpperCase().slice(0, 5) : 'FILE';
    fileExtBadge.textContent = ext;
    fileNameLabel.textContent = f.name;
    fileSizeLabel.textContent = formatBytes(f.size);
    uploadBar.style.display = 'flex';
  }

  if (fileInput && dropZone){
    fileInput.addEventListener('change', updateSelectedFile);
    ['dragenter','dragover'].forEach(function(evt){
      dropZone.addEventListener(evt, function(e){ e.preventDefault(); dropZone.classList.add('is-dragover'); });
    });
    ['dragleave','drop'].forEach(function(evt){
      dropZone.addEventListener(evt, function(){ dropZone.classList.remove('is-dragover'); });
    });
  }

  if (uploadForm && uploadSubmitBtn){
    uploadForm.addEventListener('submit', function(){
      uploadSubmitBtn.disabled = true;
      uploadSubmitBtn.textContent = 'Mengunggah ke Drive...';
    });
  }

  // Instant search & filter
  var searchInput = document.getElementById('fileSearch');
  var searchClear = document.getElementById('searchClear');
  var filterPills = document.querySelectorAll('.filter-pill');
  var grid = document.getElementById('driveGrid');
  var visibleCount = document.getElementById('visibleCount');
  var noMatch = document.getElementById('noFilterMatch');
  var activeFilter = 'all';

  function applyFileFilter(){
    if (!grid) return;
    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
    if (searchClear) searchClear.style.display = q ? 'inline-flex' : 'none';
    var items = grid.querySelectorAll('.drive-file');
    var shown = 0;
    items.forEach(function(card){
      var name = card.getAttribute('data-name') || '';
      var cat = card.getAttribute('data-cat') || 'other';
      var matchText = !q || name.indexOf(q) !== -1;
      var matchCat = activeFilter === 'all' || cat === activeFilter;
      var ok = matchText && matchCat;
      card.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    if (visibleCount) visibleCount.textContent = shown + ' item';
    if (noMatch) noMatch.style.display = shown === 0 ? 'grid' : 'none';
  }

  if (searchInput){
    searchInput.addEventListener('input', applyFileFilter);
  }
  if (searchClear && searchInput){
    searchClear.addEventListener('click', function(){
      searchInput.value = '';
      applyFileFilter();
      searchInput.focus();
    });
  }
  filterPills.forEach(function(btn){
    btn.addEventListener('click', function(){
      filterPills.forEach(function(p){ p.classList.remove('is-active'); });
      btn.classList.add('is-active');
      activeFilter = btn.getAttribute('data-filter') || 'all';
      applyFileFilter();
    });
  });

  // Close open file action dropdowns when clicking outside
  document.addEventListener('click', function(e){
    document.querySelectorAll('details.drive-actions[open]').forEach(function(det){
      if (!det.contains(e.target)) det.removeAttribute('open');
    });
  });
})();
</script>
</body>
</html>
