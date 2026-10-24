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
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Penyimpanan Saya - Cloud Guru</title><style>
body{font:16px system-ui,sans-serif;background:#f4f7fb;color:#172033;margin:0}.wrap{max-width:1100px;margin:30px auto;padding:0 16px}.card{background:white;border:1px solid #e0e6ef;border-radius:14px;padding:20px;margin:14px 0}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.btn,button{display:inline-block;background:#155eef;color:white;border:0;border-radius:8px;padding:10px 15px;text-decoration:none;cursor:pointer;font:inherit}.muted{color:#657087}.error{color:#a12622;background:#fff1f0;padding:12px;border-radius:8px}.ok{color:#176b3a;background:#eaf8ef;padding:12px;border-radius:8px}select,input{font:inherit;padding:9px;border:1px solid #cbd5e1;border-radius:8px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #e8edf4;overflow-wrap:anywhere}th{background:#f8fafc}@media(max-width:600px){table{font-size:13px}.wrap{margin:14px auto}}
</style></head><body><main class="wrap"><p style="margin:0 0 14px"><a href="/dashboard/index.php" style="display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid #cbd5e1;border-radius:9px;padding:9px 13px;color:#155eef;text-decoration:none;font-weight:650">← Kembali ke Beranda Akun</a></p><h1>Penyimpanan Saya</h1><p class="muted">Cloud Guru · SDN Ciburayut 01</p>
<section class="card"><div class="row"><strong>Status Google Drive</strong><?php if($connection && !empty($connection['refresh_token_enc'])): ?><span class="ok">Terhubung<?= !empty($connection['google_email'])?' · '.h($connection['google_email']):'' ?></span><?php else: ?><span class="error">Belum terhubung</span><?php endif; ?><a class="btn" href="/gdrive/connect.php">Hubungkan Google Drive</a></div>
<?php if(isset($_GET['gdrive'])&&$_GET['gdrive']==='connected'): ?><p class="ok">Google Drive berhasil dihubungkan.</p><?php endif; ?>
<?php if($error!==''): ?><p class="error"><?=h($error)?></p><?php endif; ?>
</section>
<section class="card"><div class="row"><form method="get"><label for="folder">Folder: </label><select id="folder" name="folder" onchange="this.form.submit()"><?php foreach($folders as $key=>$label): ?><option value="<?=h($key)?>" <?=$selected===$key?'selected':''?>><?=h($label)?></option><?php endforeach; ?></select></form>
<form action="/storage/upload.php" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=h(cloudguru_csrf_token())?>"><input type="hidden" name="folder" value="<?=h($selected)?>"><input type="file" name="file" required><button type="submit">Upload File</button></form></div>
<?php if($connection && !empty($connection['refresh_token_enc'])): ?><p class="muted">File disimpan di Google Drive sekolah. Ukuran upload maksimum mengikuti konfigurasi server.</p><table><thead><tr><th>Nama file</th><th>Jenis</th><th>Ukuran</th><th>Aksi</th></tr></thead><tbody><?php if(!$files): ?><tr><td colspan="4">Belum ada file di folder ini.</td></tr><?php endif; foreach($files as $file): ?><tr><td><?=h($file['name']??'File')?></td><td><?=h($file['mimeType']??'')?></td><td><?=isset($file['size'])?number_format((int)$file['size']/1024,1).' KB':'—'?></td><td><a href="/storage/download.php?id=<?=rawurlencode((string)$file['id'])?>">Unduh</a> · <form style="display:inline" method="post" action="/storage/delete.php" onsubmit="return confirm('Hapus file ini dari Google Drive?')"><input type="hidden" name="csrf_token" value="<?=h(cloudguru_csrf_token())?>"><input type="hidden" name="id" value="<?=h($file['id'])?>"><input type="hidden" name="folder" value="<?=h($selected)?>"><button type="submit">Hapus</button></form><?php if(!empty($file['webViewLink'])): ?> · <a target="_blank" rel="noopener" href="<?=h($file['webViewLink'])?>">Buka Drive</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><p>Hubungkan akun Google Drive untuk mulai menyimpan file.</p><?php endif; ?></section></main></body></html>