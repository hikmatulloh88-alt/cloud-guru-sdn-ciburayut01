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
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Penyimpanan Saya - Cloud Guru</title><style>
:root{color-scheme:light}*{box-sizing:border-box}
body{font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafd;color:#202124;margin:0}
.wrap{max-width:1160px;margin:28px auto;padding:0 22px 36px}
.card{background:#fff;border:1px solid #e0e3e7;border-radius:16px;padding:18px 20px;margin:16px 0}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.btn,button{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#0b57d0;color:#fff;border:0;border-radius:9px;padding:10px 15px;text-decoration:none;cursor:pointer;font:500 14px system-ui,sans-serif;white-space:nowrap}
.btn:hover,button:hover{background:#0842a0}
.muted{color:#5f6368}.error{color:#b3261e;background:#fceeee;padding:12px;border-radius:9px}.ok{color:#137333;background:#e6f4ea;padding:10px 12px;border-radius:9px}
select,input{font:14px system-ui,sans-serif;padding:10px;border:1px solid #dadce0;border-radius:9px;background:#fff;color:#202124;max-width:100%}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.upload-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.upload-form input[type=file]{max-width:min(100%,360px);padding:6px}
.drive-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(205px,1fr));gap:14px;margin-top:17px}
.drive-file{position:relative;min-width:0;overflow:visible;background:#fff;border:1px solid #dadce0;border-radius:12px;transition:border-color .15s,box-shadow .15s}
.drive-file:hover{border-color:#b6c3d5;box-shadow:0 2px 7px rgba(60,64,67,.14)}
.drive-preview{position:relative;height:150px;overflow:hidden;border-radius:11px 11px 0 0;background:#f1f3f4;border-bottom:1px solid #edf0f2}
.drive-preview img{display:block;width:100%;height:100%;object-fit:cover}
.drive-icon{height:100%;width:100%;display:grid;place-items:center;font-size:48px;background:linear-gradient(145deg,#f8fafd,#eef3f8)}
.drive-preview-link{position:absolute;inset:0;z-index:1;display:block}
.drive-file-info{padding:12px 43px 14px 13px;min-height:76px}
.drive-file-name{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;overflow-wrap:anywhere;color:#202124;text-decoration:none;font-weight:500;font-size:14px;line-height:1.4}
.drive-file-name:hover{color:#0b57d0;text-decoration:underline}
.drive-meta{margin-top:7px;color:#5f6368;font-size:12px;overflow-wrap:anywhere}
.drive-actions{position:absolute;right:8px;bottom:12px;z-index:5}
.drive-actions summary{list-style:none;cursor:pointer;display:grid;place-items:center;width:30px;height:30px;border-radius:50%;font-size:21px;line-height:1;color:#5f6368}
.drive-actions summary::-webkit-details-marker{display:none}
.drive-actions summary:hover,.drive-actions[open] summary{background:#f1f3f4}
.drive-menu{position:absolute;right:0;bottom:34px;min-width:145px;padding:6px;background:#fff;border:1px solid #dadce0;border-radius:10px;box-shadow:0 4px 16px rgba(60,64,67,.22);display:flex;flex-direction:column;gap:2px}
.drive-menu a,.drive-menu button{display:block;width:100%;text-align:left;background:transparent;color:#3c4043;border:0;border-radius:6px;padding:9px 10px;font:14px system-ui,sans-serif;text-decoration:none;white-space:normal}
.drive-menu a:hover,.drive-menu button:hover{background:#f1f3f4;color:#202124}
.drive-menu form{margin:0}
.drive-menu .danger{color:#b3261e}
.file-count{color:#5f6368;font-size:13px;margin:12px 0 0}
.empty-state{text-align:center;padding:36px 16px;color:#5f6368}
.empty-icon{font-size:38px;margin-bottom:8px}
@media(max-width:640px){.wrap{margin:15px auto;padding:0 12px 28px}.card{padding:15px;margin:12px 0}.drive-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.drive-preview{height:118px}.drive-file-info{padding:10px 35px 12px 10px;min-height:75px}.drive-file-name{font-size:13px}.drive-actions{right:5px;bottom:9px}.upload-form input[type=file]{width:100%}}
@media(max-width:370px){.drive-grid{grid-template-columns:1fr}.drive-preview{height:160px}}
</style></head><body><main class="wrap"><p style="margin:0 0 14px"><a href="/dashboard/index.php" style="display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid #cbd5e1;border-radius:9px;padding:9px 13px;color:#155eef;text-decoration:none;font-weight:650">← Kembali ke Beranda Akun</a></p><h1>Penyimpanan Saya</h1><p class="muted">Cloud Guru · SDN Ciburayut 01</p>
<section class="card"><div class="row"><strong>Status Google Drive</strong><?php if($connection && !empty($connection['refresh_token_enc'])): ?><span class="ok">Terhubung<?= !empty($connection['google_email'])?' · '.h($connection['google_email']):'' ?></span><?php else: ?><span class="error">Belum terhubung</span><?php endif; ?><a class="btn" href="/gdrive/connect.php">Hubungkan Google Drive</a></div>
<?php if(isset($_GET['gdrive'])&&$_GET['gdrive']==='connected'): ?><p class="ok">Google Drive berhasil dihubungkan.</p><?php endif; ?>
<?php if(isset($_GET['uploaded'])&&$_GET['uploaded']==='1'): ?><p class="ok">File berhasil diunggah.</p><?php endif; ?>
<?php if(isset($_GET['deleted'])&&$_GET['deleted']==='1'): ?><p class="ok">File berhasil dihapus.</p><?php endif; ?>
<?php if($error!==''): ?><p class="error"><?=h($error)?></p><?php endif; ?>
</section>
<section class="card"><div class="row"><form method="get"><label for="folder">Folder: </label><select id="folder" name="folder" onchange="this.form.submit()"><?php foreach($folders as $key=>$label): ?><option value="<?=h($key)?>" <?=$selected===$key?'selected':''?>><?=h($label)?></option><?php endforeach; ?></select></form>
<form action="/storage/upload.php" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=h(cloudguru_csrf_token())?>"><input type="hidden" name="folder" value="<?=h($selected)?>"><input type="file" name="file" required><button type="submit">Upload File</button></form></div>
<?php if($connection && !empty($connection['refresh_token_enc'])): ?>
<p class="muted">File tersimpan di Google Drive sekolah.</p>
<?php if(!$files): ?>
<div class="empty-state"><div class="empty-icon">📂</div><strong>Folder ini masih kosong</strong><div>Upload file untuk mulai menyimpan di sini.</div></div>
<?php else: ?>
<p class="file-count"><?=count($files)?> item</p>
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
<?php else: ?><p>Hubungkan akun Google Drive untuk mulai menyimpan file.</p><?php endif; ?></section></main></body></html>