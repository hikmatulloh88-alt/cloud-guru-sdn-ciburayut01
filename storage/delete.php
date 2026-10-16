<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
wajib_login();
require_once __DIR__.'/../config/google_drive_lib.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method tidak diizinkan');}
try{
 $uid=gdrive_user_id();if($uid<1)throw new RuntimeException('Akun tidak terbaca. Login ulang.');
 $id=trim((string)($_POST['id']??''));if($id===''||!preg_match('/^[A-Za-z0-9_-]+$/',$id))throw new RuntimeException('ID file tidak valid.');
 gdrive_api($uid,'DELETE','https://www.googleapis.com/drive/v3/files/'.rawurlencode($id));
 $folder=(string)($_POST['folder']??'root_folder_id');if(!in_array($folder,['root_folder_id','administrasi_folder_id','perencanaan_folder_id','asesmen_folder_id'],true))$folder='root_folder_id';
 header('Location: /storage/index.php?folder='.rawurlencode($folder).'&deleted=1',true,303);exit;
}catch(Throwable $e){error_log('Cloud Guru Drive delete: '.$e->getMessage());http_response_code(400);echo 'Hapus file gagal: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').' <a href="/storage/index.php">Kembali</a>';}
