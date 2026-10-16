<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
wajib_login();
require_once __DIR__.'/../config/google_drive_lib.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: /storage/index.php');exit;}
try{
 $uid=gdrive_user_id(); if($uid<1)throw new RuntimeException('Akun tidak terbaca. Login ulang.');
 // Repair the live table before calling shared Drive helpers; do not rely on stale migration state.
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
 gdrive_require_tables();
 // Last-resort schema repair before folder setup or upload touches folder columns.
 $requiredFolderColumns = [
   'root_folder_id' => 'VARCHAR(255) DEFAULT NULL',
   'administrasi_folder_id' => 'VARCHAR(255) DEFAULT NULL',
   'perencanaan_folder_id' => 'VARCHAR(255) DEFAULT NULL',
   'asesmen_folder_id' => 'VARCHAR(255) DEFAULT NULL',
 ];
 $actualColumns = $pdo->query("SHOW COLUMNS FROM google_drive_connections")->fetchAll(PDO::FETCH_COLUMN);
 foreach ($requiredFolderColumns as $column => $definition) {
   if (!in_array($column, $actualColumns, true)) {
     $pdo->exec("ALTER TABLE google_drive_connections ADD COLUMN `$column` $definition");
   }
 }
 gdrive_ensure_structure($uid);
 $folderKey=(string)($_POST['folder']??'root_folder_id');
 $allowed=['root_folder_id','administrasi_folder_id','perencanaan_folder_id','asesmen_folder_id'];
 if(!in_array($folderKey,$allowed,true))$folderKey='root_folder_id';
 if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('File belum dipilih atau upload PHP gagal.');
 $f=$_FILES['file']; $max=defined('GOOGLE_DRIVE_MAX_UPLOAD')?(int)GOOGLE_DRIVE_MAX_UPLOAD:26214400;
 if((int)$f['size']<1||(int)$f['size']>$max)throw new RuntimeException('Ukuran file kosong atau melebihi batas upload server.');
 $q=$pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=?');$q->execute([$uid]);$conn=$q->fetch(PDO::FETCH_ASSOC);
 $parent=$conn[$folderKey]??'';if(!$parent)throw new RuntimeException('Folder Google Drive belum siap. Hubungkan ulang akun Google Drive.');
 $name=basename((string)$f['name']);$name=preg_replace('/[\x00-\x1F\x7F]/u','',$name);if($name==='')$name='upload-'.date('YmdHis');
 $mime=function_exists('mime_content_type')?(mime_content_type($f['tmp_name'])?:'application/octet-stream'):'application/octet-stream';
 gdrive_api($uid,'POST','https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,size,webViewLink',['name'=>$name,'mimeType'=>$mime,'parents'=>[$parent]],(string)$f['tmp_name'],$mime);
 header('Location: /storage/index.php?folder='.rawurlencode($folderKey).'&uploaded=1',true,303);exit;
}catch(Throwable $e){error_log('Cloud Guru Drive upload: '.$e->getMessage());http_response_code(400);?><!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upload gagal</title><body style="font:16px system-ui;max-width:700px;margin:40px auto;padding:0 16px"><h2>Upload Google Drive gagal</h2><p><?=htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')?></p><p><a href="/storage/index.php">Kembali ke Penyimpanan Saya</a></p></body></html><?php }
