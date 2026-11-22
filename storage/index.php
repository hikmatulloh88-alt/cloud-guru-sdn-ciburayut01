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
$userId=gdrive_user_id();
$folders=['root_folder_id'=>'Semua File','administrasi_folder_id'=>'Administrasi Guru','perencanaan_folder_id'=>'Perencanaan','asesmen_folder_id'=>'Asesmen'];
$selected=(string)($_GET['folder']??'root_folder_id');
if(!isset($folders[$selected])) $selected='root_folder_id';
$connection=null; $files=[];
try {
 if(!$schemaReady) throw new RuntimeException('Inisialisasi tabel Google Drive gagal.');
 if($userId<1) throw new RuntimeException('ID akun tidak terbaca. Keluar lalu masuk kembali.');
 $q=$pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=? LIMIT 1'); $q->execute([$userId]); $connection=$q->fetch(PDO::FETCH_ASSOC) ?: null;
 if($connection && !empty($connection['refresh_token_enc'])) {
   gdrive_ensure_structure($userId);
   $q=$pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=? LIMIT 1'); $q->execute([$userId]); $connection=$q->fetch(PDO::FETCH_ASSOC) ?: $connection;
   $parent=$connection[$selected]??'';
   if($parent) {
     $query="'".str_replace("'","\\'",$parent)."' in parents and trashed=false";
     $result=gdrive_api($userId,'GET','https://www.googleapis.com/drive/v3/files?q='.rawurlencode($query).'&pageSize=100&orderBy=folder,name&fields=files(id,name,mimeType,size,modifiedTime,webViewLink)');
     $files=$result['files']??[];
   }
 }
} catch(Throwable $e) { if($error==='') $error=$e->getMessage(); error_log('Cloud Guru storage: '.$e->getMessage()); }
function h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function gdrive_file_size($value): string {
    if ($value === null || $value === '' || !is_numeric($value)) return '—';
    $size = max(0, (float)$value);
    if ($size < 1024) return number_format($size, 0) . ' B';
    $units = ['KB','MB','GB','TB'];
    $size /= 1024;
    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'TB') return number_format($size, 1) . ' ' . $unit;
        $size /= 1024;
    }
    return '—';
}
function gdrive_file_type_label(string $mime, string $name): string {
    if ($mime === 'application/vnd.google-apps.folder') return 'Folder';
    if (strpos($mime, 'image/') === 0) return 'Gambar';
    if (strpos($mime, 'video/') === 0) return 'Video';
    if (strpos($mime, 'audio/') === 0) return 'Audio';
    $labels = [
        'application/pdf' => 'PDF',
        'application/vnd.google-apps.document' => 'Dokumen Google',
        'application/vnd.google-apps.spreadsheet' => 'Spreadsheet Google',
        'application/vnd.google-apps.presentation' => 'Presentasi Google',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
        'application/msword' => 'Word',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
        'application/vnd.ms-excel' => 'Excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
        'text/plain' => 'Dokumen teks',
        'application/zip' => 'Arsip ZIP',
        'application/x-rar-compressed' => 'Arsip RAR',
    ];
    if (isset($labels[$mime])) return $labels[$mime];
    $ext = strtoupper((string)pathinfo($name, PATHINFO_EXTENSION));
    return $ext !== '' ? $ext . ' File' : 'File';
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
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Penyimpanan Saya - Cloud Guru</title>
<style>
:root{color-scheme:light;--ink:#17243a;--muted:#718096;--line:#e6ebf2;--blue:#3858d6;--purple:#7654d6;--surface:#fff}
*{box-sizing:border-box}
body{min-height:100vh;margin:0;background:radial-gradient(ellipse at 8% 0%,#e9edff 0,transparent 34%),#f5f7fb;color:var(--ink);font:15px/1.55 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
a{color:inherit}
button,input{font:inherit}
.wrap{width:min(1180px,100% - 40px);margin:0 auto;padding:24px 0 54px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.brand{display:flex;align-items:center;gap:11px;text-decoration:none;font-weight:800;letter-spacing:-.02em}
.brand-mark{display:grid;width:42px;height:42px;place-items:center;border-radius:14px;background:linear-gradient(145deg,#526ee7,#7958d7);color:white;font-size:21px;box-shadow:0 7px 18px #526ee744}
.brand small{display:block;color:var(--muted);font-size:11px;font-weight:600;letter-spacing:.02em}
.back-link{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #dfe5ef;border-radius:12px;background:#ffffffb8;color:#46536a;text-decoration:none;font-size:13px;font-weight:700;transition:.18s ease}
.back-link:hover{border-color:#c3cef5;background:#fff;color:var(--blue);transform:translateY(-1px)}
.hero{position:relative;display:flex;align-items:center;justify-content:space-between;min-height:205px;overflow:hidden;padding:32px 38px;border-radius:25px;background:linear-gradient(112deg,#344fc0 0%,#5366d8 54%,#7960d8 100%);color:#fff;box-shadow:0 18px 45px #4056bf2a}
.hero:before,.hero:after{position:absolute;content:"";border:1px solid #ffffff20;border-radius:50%;pointer-events:none}
.hero:before{width:310px;height:310px;right:4%;top:-180px}
.hero:after{width:230px;height:230px;right:10%;bottom:-185px}
.hero-copy{position:relative;z-index:1;max-width:670px}
.eyebrow{margin-bottom:10px;color:#dce4ff;font-size:11px;font-weight:800;letter-spacing:.15em}
.hero h1{max-width:610px;margin:0;font-size:clamp(28px,4vw,42px);line-height:1.12;letter-spacing:-.045em}
.hero p{max-width:560px;margin:12px 0 0;color:#e4e9ff;font-size:14px}
.hero-art{position:relative;z-index:1;display:grid;width:116px;height:116px;flex:none;place-items:center;margin:0 5% 0 24px;border:1px solid #ffffff40;border-radius:32px;background:#ffffff1a;box-shadow:inset 0 1px #ffffff35;transform:rotate(7deg)}
.hero-art span{font-size:54px;filter:drop-shadow(0 8px 12px #17276455);transform:rotate(-7deg)}
.status-card,.panel{border:1px solid var(--line);border-radius:19px;background:var(--surface);box-shadow:0 8px 26px #263b6510}
.status-card{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:18px 0;padding:17px 20px}
.status-copy{display:flex;align-items:center;gap:13px;min-width:0}
.status-icon{display:grid;width:44px;height:44px;flex:none;place-items:center;border-radius:14px;background:#edf2ff;color:#4563dc;font-size:20px}
.status-copy strong{display:block;font-size:14px}
.status-copy p{margin:2px 0 0;color:var(--muted);font-size:12px;overflow-wrap:anywhere}
.status-pill{display:inline-flex;align-items:center;gap:7px;margin-top:4px;color:#178459;font-size:12px;font-weight:750}
.status-pill:before{width:7px;height:7px;border-radius:50%;background:#20b37a;content:""}
.status-pill.disconnected{color:#c05b52}
.status-pill.disconnected:before{background:#df7069}
.btn,button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 16px;border:0;border-radius:12px;background:linear-gradient(110deg,#3b5bd4,#6858d5);color:white;text-decoration:none;font-size:13px;font-weight:750;white-space:nowrap;cursor:pointer;box-shadow:0 6px 15px #4e5fd42b;transition:transform .18s ease,box-shadow .18s ease,filter .18s ease}
.btn:hover,button:hover{filter:brightness(1.05);transform:translateY(-1px);box-shadow:0 9px 20px #4e5fd43b}
.btn:focus-visible,button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #9cadff;outline-offset:3px}
.alerts{display:grid;gap:9px;margin:14px 0}
.notice{margin:0;padding:11px 14px;border:1px solid #ccebdc;border-radius:12px;background:#effaf4;color:#16734c;font-size:13px;font-weight:650}
.notice.error{border-color:#f1d2d0;background:#fff4f2;color:#a93832}
.panel{margin-top:17px;padding:22px}
.panel-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}
.panel-heading h2{margin:0;font-size:18px;letter-spacing:-.025em}
.panel-heading p{margin:4px 0 0;color:var(--muted);font-size:13px}
.upload-form{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px;border:1px dashed #cbd5ed;border-radius:15px;background:linear-gradient(110deg,#f8faff,#fbf9ff)}
.upload-form input[type=file]{min-width:0;flex:1;padding:7px;border:1px solid #e2e7f0;border-radius:10px;background:#fff;color:#566176;font-size:12px}
.upload-form input[type=file]::file-selector-button{margin-right:11px;padding:8px 11px;border:0;border-radius:8px;background:#edf1ff;color:#415bd0;font:inherit;font-size:12px;font-weight:700;cursor:pointer}
.helper{display:flex;align-items:center;gap:7px;margin:9px 2px 0;color:var(--muted);font-size:11px}
.folder-nav{display:flex;gap:8px;overflow-x:auto;padding:1px 2px 8px}
.folder-nav form{flex:none;margin:0}
.folder-tab{min-height:38px;padding:8px 13px;border:1px solid #e5e9f1;border-radius:11px;background:#fff;color:#66738a;font-size:12px;font-weight:700;box-shadow:none}
.folder-tab:hover{border-color:#cbd5ff;background:#f6f7ff;color:#405bd0;box-shadow:none}
.folder-tab.active{border-color:#dbe2ff;background:#edf1ff;color:#405bd0;box-shadow:inset 0 0 0 1px #e1e6ff}
.files-heading{align-items:center;margin:13px 0 0}
.file-count{display:inline-flex;align-items:center;min-height:28px;padding:4px 10px;border-radius:99px;background:#f0f3f8;color:#637087;font-size:11px;font-weight:750;white-space:nowrap}
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(205px,1fr));gap:15px;margin-top:17px}
.drive-file{position:relative;min-width:0;overflow:visible;border:1px solid #e8ecf3;border-radius:15px;background:#fff;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.drive-file:hover{z-index:2;border-color:#d1d9f6;transform:translateY(-3px);box-shadow:0 13px 25px #273c6618}
.drive-preview{position:relative;height:150px;overflow:hidden;border-radius:14px 14px 0 0;background:#f2f4fa}
.drive-preview img{display:block;width:100%;height:100%;object-fit:cover}
.drive-icon{display:grid;width:100%;height:100%;place-items:center;background:radial-gradient(circle at 50% 35%,#fff 0,#f2f4ff 75%);font-size:47px}
.drive-preview-link{position:absolute;inset:0;z-index:1;display:block}
.drive-file-info{min-height:80px;padding:12px 44px 14px 14px;border-top:1px solid #f0f2f6}
.drive-file-name{display:-webkit-box;overflow:hidden;color:#27334a;font-size:13px;font-weight:750;line-height:1.45;text-decoration:none;overflow-wrap:anywhere;-webkit-box-orient:vertical;-webkit-line-clamp:2}
.drive-file-name:hover{color:var(--blue)}
.drive-meta{margin-top:7px;color:#8792a4;font-size:11px;overflow-wrap:anywhere}
.drive-actions{position:absolute;right:8px;bottom:13px;z-index:5}
.drive-actions summary{display:grid;width:31px;height:31px;place-items:center;border-radius:10px;color:#64718a;font-size:21px;line-height:1;list-style:none;cursor:pointer}
.drive-actions summary::-webkit-details-marker{display:none}
.drive-actions summary:hover,.drive-actions[open] summary{background:#f0f3fb;color:#405bd0}
.drive-menu{position:absolute;right:0;bottom:37px;display:flex;min-width:155px;flex-direction:column;gap:3px;padding:6px;border:1px solid #e4e8f0;border-radius:12px;background:#fff;box-shadow:0 12px 30px #17243a22}
.drive-menu a,.drive-menu button{display:block;width:100%;min-height:0;padding:9px 10px;border:0;border-radius:8px;background:transparent;color:#3f4b60;font-size:12px;font-weight:650;text-align:left;text-decoration:none;box-shadow:none}
.drive-menu a:hover,.drive-menu button:hover{background:#f3f5fa;color:#293752;transform:none;box-shadow:none}
.drive-menu form{margin:0}
.drive-menu .danger{color:#bb4543}
.drive-menu .danger:hover{background:#fff1f0;color:#a83230}
.empty-state{display:grid;justify-items:center;gap:7px;padding:38px 16px;border:1px dashed #e2e7f0;border-radius:15px;background:#fafbfe;color:#718096;text-align:center}
.empty-icon{display:grid;width:58px;height:58px;place-items:center;margin-bottom:5px;border-radius:20px;background:#eff2ff;font-size:30px}
.empty-state strong{color:#35425b;font-size:14px}
.empty-state p{margin:0;font-size:12px}
@media(max-width:700px){.wrap{width:min(100% - 28px,600px);padding-top:14px}.topbar{margin-bottom:16px}.brand-mark{width:38px;height:38px;border-radius:12px}.back-link{padding:8px 10px;font-size:12px}.hero{min-height:190px;padding:25px 22px;border-radius:21px}.hero-art{width:80px;height:80px;margin-left:12px;border-radius:24px}.hero-art span{font-size:39px}.hero p{font-size:13px}.status-card{padding:14px}.panel{padding:17px}.drive-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.drive-preview{height:125px}.drive-file-info{min-height:76px;padding:10px 36px 12px 11px}.drive-actions{right:5px;bottom:8px}}
@media(max-width:430px){.wrap{width:calc(100% - 22px)}.brand{gap:8px}.brand small{font-size:10px}.back-link{gap:5px}.back-link .back-label{display:none}.hero{min-height:0;padding:23px 18px}.hero-art{width:56px;height:56px;margin-left:8px;border-radius:17px}.hero-art span{font-size:28px}.hero h1{font-size:28px}.hero p{font-size:12px}.status-card{align-items:flex-start;flex-wrap:wrap}.status-card>.btn{width:100%}.panel{padding:15px}.panel-heading h2{font-size:16px}.upload-form{align-items:stretch;padding:11px}.upload-form input[type=file]{flex-basis:100%}.upload-form button{width:100%}.drive-preview{height:105px}.drive-file-name{font-size:12px}.drive-meta{font-size:10px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}
</style>
<link rel="stylesheet" href="/assets/css/glass-ui.css?v=2">
</head>
<body class="storage-page">
<main class="wrap">
  <header class="topbar">
    <a class="brand" href="/dashboard/index.php"><span class="brand-mark" aria-hidden="true">☁</span><span>Cloud Guru<small>SDN Ciburayut 01</small></span></a>
    <a class="back-link" href="/dashboard/index.php"><span aria-hidden="true">←</span><span class="back-label">Kembali ke Dashboard</span></a>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <div class="eyebrow">RUANG FILE DIGITAL</div>
      <h1>Penyimpanan Saya</h1>
      <p>Simpan dan kelola dokumen sekolah dengan praktis. Semua file tersimpan aman di Google Drive.</p>
    </div>
    <div class="hero-art" aria-hidden="true"><span>☁️</span></div>
  </section>

  <section class="status-card" aria-label="Status Google Drive">
    <div class="status-copy">
      <span class="status-icon" aria-hidden="true">☁</span>
      <div>
        <strong>Google Drive</strong>
        <?php if($connection && !empty($connection['refresh_token_enc'])): ?>
          <span class="status-pill">Terhubung<?= !empty($connection['google_email'])?' · '.h($connection['google_email']):'' ?></span>
        <?php else: ?>
          <span class="status-pill disconnected">Belum terhubung</span>
        <?php endif; ?>
      </div>
    </div>
    <a class="btn" href="/gdrive/connect.php"><?= $connection && !empty($connection['refresh_token_enc']) ? 'Hubungkan ulang' : 'Hubungkan Google Drive' ?></a>
  </section>

  <div class="alerts" aria-live="polite">
    <?php if(isset($_GET['gdrive'])&&$_GET['gdrive']==='connected'): ?><p class="notice">Google Drive berhasil dihubungkan.</p><?php endif; ?>
    <?php if(isset($_GET['uploaded'])&&$_GET['uploaded']==='1'): ?><p class="notice">File berhasil diunggah.</p><?php endif; ?>
    <?php if(isset($_GET['deleted'])&&$_GET['deleted']==='1'): ?><p class="notice">File berhasil dihapus.</p><?php endif; ?>
    <?php if($error!==''): ?><p class="notice error"><?=h($error)?></p><?php endif; ?>
  </div>

  <section class="panel">
    <div class="panel-heading">
      <div><h2>Unggah file</h2><p>Pilih file yang ingin disimpan ke folder sekolah.</p></div>
    </div>
    <form class="upload-form" action="/storage/upload.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?=h(cloudguru_csrf_token())?>">
      <input type="hidden" name="folder" value="<?=h($selected)?>">
      <input type="file" name="file" required aria-label="Pilih file untuk diunggah">
      <button type="submit"><span aria-hidden="true">↑</span> Upload File</button>
    </form>
    <div class="helper"><span aria-hidden="true">🔒</span> File hanya dapat diakses melalui akun Google Drive yang terhubung.</div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div><h2>Jelajahi folder</h2><p>Pilih folder untuk melihat dan mengelola file.</p></div>
    </div>
    <nav class="folder-nav" aria-label="Pilih folder">
      <?php foreach($folders as $key=>$label): ?>
        <form method="get"><button class="folder-tab <?=$selected===$key?'active':''?>" type="submit" name="folder" value="<?=h($key)?>" <?=$selected===$key?'aria-current="page"':''?>><?=h($label)?></button></form>
      <?php endforeach; ?>
    </nav>
    <div class="panel-heading files-heading">
      <div><h2><?=h($folders[$selected])?></h2><p>File yang ada di folder ini.</p></div>
      <?php if($connection && !empty($connection['refresh_token_enc'])): ?><span class="file-count"><?=count($files)?> item</span><?php endif; ?>
    </div>
    <?php if($connection && !empty($connection['refresh_token_enc'])): ?>
      <?php if(!$files): ?>
        <div class="empty-state"><div class="empty-icon" aria-hidden="true">📂</div><strong>Folder ini masih kosong</strong><p>File yang kamu unggah akan muncul di sini.</p></div>
      <?php else: ?>
        <div class="drive-grid">
        <?php foreach($files as $file):
            $fileId = (string)($file['id'] ?? '');
            $fileName = (string)($file['name'] ?? 'File');
            $fileMime = (string)($file['mimeType'] ?? '');
            $isFolder = $fileMime === 'application/vnd.google-apps.folder';
            $isImage = strpos($fileMime, 'image/') === 0;
            $fileSize = gdrive_file_size($file['size'] ?? null);
            $openUrl = (string)($file['webViewLink'] ?? '');
            $fallbackUrl = '/storage/download.php?id=' . rawurlencode($fileId);
            $targetUrl = $openUrl !== '' ? $openUrl : $fallbackUrl;
        ?>
          <article class="drive-file">
            <div class="drive-preview">
              <?php if($isImage): ?><img loading="lazy" src="/storage/thumbnail.php?id=<?=rawurlencode($fileId)?>" alt="<?=h($fileName)?>" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'"><?php endif; ?>
              <div class="drive-icon" <?=$isImage?'style="display:none"':''?>><?=h(gdrive_file_icon($fileMime, $isFolder))?></div>
              <a class="drive-preview-link" href="<?=h($targetUrl)?>" target="_blank" rel="noopener" aria-label="Buka <?=h($fileName)?>"></a>
            </div>
            <div class="drive-file-info">
              <a class="drive-file-name" href="<?=h($targetUrl)?>" target="_blank" rel="noopener" title="<?=h($fileName)?>"><?=h($fileName)?></a>
              <div class="drive-meta"><?=h(gdrive_file_type_label($fileMime, $fileName))?><?php if(!$isFolder && $fileSize !== '—'): ?> · <?=h($fileSize)?><?php endif; ?></div>
            </div>
            <details class="drive-actions">
              <summary aria-label="Aksi file">⋮</summary>
              <div class="drive-menu">
                <a href="<?=h($fallbackUrl)?>">Unduh</a>
                <?php if($openUrl !== ''): ?><a href="<?=h($openUrl)?>" target="_blank" rel="noopener">Buka di Drive</a><?php endif; ?>
                <form method="post" action="/storage/delete.php" onsubmit="return confirm('Hapus file ini dari Google Drive?')">
                  <input type="hidden" name="csrf_token" value="<?=h(cloudguru_csrf_token())?>">
                  <input type="hidden" name="id" value="<?=h($fileId)?>">
                  <input type="hidden" name="folder" value="<?=h($selected)?>">
                  <button class="danger" type="submit">Hapus</button>
                </form>
              </div>
            </details>
          </article>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon" aria-hidden="true">☁️</div><strong>Hubungkan Google Drive untuk mulai</strong><p>Setelah terhubung, file dan foldermu akan tampil di sini.</p></div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>