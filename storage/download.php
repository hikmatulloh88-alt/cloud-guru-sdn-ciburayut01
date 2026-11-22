<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
wajib_login();
require_once __DIR__.'/../config/google_drive_lib.php';
try{
 $uid=gdrive_user_id();if($uid<1)throw new RuntimeException('Akun tidak terbaca. Login ulang.');
 $id=trim((string)($_GET['id']??''));if($id===''||!preg_match('/^[A-Za-z0-9_-]+$/',$id))throw new RuntimeException('ID file tidak valid.');
 if(!gdrive_file_belongs_to_user($uid,$id))throw new RuntimeException('File tidak ditemukan atau bukan milik akun Anda.');
 $meta=gdrive_api($uid,'GET','https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?fields=id,name,mimeType');
 $name=basename((string)($meta['name']??'download'));$mime=(string)($meta['mimeType']??'application/octet-stream');
 $bytes=gdrive_api($uid,'GET','https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?alt=media');
 if(!is_string($bytes))throw new RuntimeException('Google Drive tidak mengembalikan isi file.');
 header('Content-Type: '.$mime);header('Content-Length: '.strlen($bytes));header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],['_','',''],$name).'"');header('X-Content-Type-Options: nosniff');echo $bytes;exit;
}catch(Throwable $e){error_log('Cloud Guru Drive download: '.$e->getMessage());http_response_code(400);echo 'Unduh file gagal: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').' <a href="/storage/index.php">Kembali</a>';}
