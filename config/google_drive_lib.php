<?php
declare(strict_types=1);

/* Google Drive integration library. Keep google_drive.php server-only. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google_drive.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Koneksi database PDO ($pdo) tidak tersedia dari config/config.php.');
}

function gdrive_user_id(): int {
    foreach (['user_id','id_user','guru_id','id_guru','id'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) return (int)$_SESSION[$key];
    }
    foreach (['user','guru','pengguna'] as $key) {
        if (isset($_SESSION[$key]) && is_array($_SESSION[$key])) {
            foreach (['id','user_id','id_user','guru_id'] as $sub) {
                if (isset($_SESSION[$key][$sub]) && is_numeric($_SESSION[$key][$sub])) return (int)$_SESSION[$key][$sub];
            }
        }
    }
    return 0;
}
function gdrive_encrypt(string $plain): string {
    $key = hash('sha256', (string)GOOGLE_DRIVE_TOKEN_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Enkripsi token Google Drive gagal.');
    return base64_encode($iv . $tag . $cipher);
}
function gdrive_decrypt(string $encoded): string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Data token Google Drive tidak valid.');
    $key = hash('sha256', (string)GOOGLE_DRIVE_TOKEN_KEY, true);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if (!is_string($plain)) throw new RuntimeException('Token Google Drive tidak dapat dibuka.');
    return $plain;
}
function gdrive_require_tables(): void {
    global $pdo;
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
    // Migrate older installations safely: some earlier builds used token_expires_at.
    $columns = $pdo->query("SHOW COLUMNS FROM google_drive_connections")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('access_token_expires_at', $columns, true)) {
        $pdo->exec("ALTER TABLE google_drive_connections ADD COLUMN access_token_expires_at DATETIME DEFAULT NULL");
    }
    if (!in_array('token_expires_at', $columns, true)) {
        $pdo->exec("ALTER TABLE google_drive_connections ADD COLUMN token_expires_at DATETIME DEFAULT NULL");
    }
    $pdo->exec("UPDATE google_drive_connections SET access_token_expires_at=COALESCE(access_token_expires_at,token_expires_at), token_expires_at=COALESCE(token_expires_at,access_token_expires_at)");
}
function gdrive_store_tokens(int $userId, array $tokens): void {
    global $pdo;
    gdrive_require_tables();
    $expires = date('Y-m-d H:i:s', time() + max(60, (int)($tokens['expires_in'] ?? 3600) - 60));
    $stmt = $pdo->prepare('SELECT refresh_token_enc FROM google_drive_connections WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    $refresh = !empty($tokens['refresh_token']) ? gdrive_encrypt((string)$tokens['refresh_token']) : ($old['refresh_token_enc'] ?? null);
    if (!$refresh) throw new RuntimeException('Google tidak mengirim refresh token. Cabut akses aplikasi dari akun Google lalu hubungkan ulang.');
    $access = gdrive_encrypt((string)$tokens['access_token']);
    $stmt = $pdo->prepare("INSERT INTO google_drive_connections (user_id,refresh_token_enc,access_token_enc,access_token_expires_at,token_expires_at)
      VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE refresh_token_enc=VALUES(refresh_token_enc),access_token_enc=VALUES(access_token_enc),access_token_expires_at=VALUES(access_token_expires_at),token_expires_at=VALUES(token_expires_at)");
    $stmt->execute([$userId,$refresh,$access,$expires,$expires]);
}
function gdrive_access_token(int $userId): string {
    global $pdo;
    gdrive_require_tables();
    $s=$pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=? LIMIT 1'); $s->execute([$userId]); $row=$s->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['refresh_token_enc'])) throw new RuntimeException('Akun Google Drive belum terhubung.');
    if (!empty($row['access_token_enc']) && !empty($row['access_token_expires_at']) && strtotime((string)$row['access_token_expires_at']) > time()+60) return gdrive_decrypt((string)$row['access_token_enc']);
    $post=http_build_query(['client_id'=>GOOGLE_DRIVE_CLIENT_ID,'client_secret'=>GOOGLE_DRIVE_CLIENT_SECRET,'refresh_token'=>gdrive_decrypt((string)$row['refresh_token_enc']),'grant_type'=>'refresh_token']);
    $ch=curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $data=is_string($raw)?json_decode($raw,true):null;
    if ($err || $status<200 || $status>=300 || !is_array($data) || empty($data['access_token'])) throw new RuntimeException('Pembaruan token Google gagal. Periksa izin Drive dan konfigurasi OAuth.');
    $access=gdrive_encrypt((string)$data['access_token']); $expiry=date('Y-m-d H:i:s',time()+max(60,(int)($data['expires_in']??3600)-60));
    $pdo->prepare('UPDATE google_drive_connections SET access_token_enc=?,access_token_expires_at=?,token_expires_at=? WHERE user_id=?')->execute([$access,$expiry,$expiry,$userId]);
    return (string)$data['access_token'];
}
function gdrive_api(int $userId, string $method, string $url, ?array $json=null, ?string $uploadPath=null, ?string $mime=null) {
    $token=gdrive_access_token($userId); $ch=curl_init($url);
    $headers=['Authorization: Bearer '.$token,'Accept: application/json'];
    $opts=[CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120];
    if ($uploadPath !== null) {
        $boundary='gdrive'.bin2hex(random_bytes(12));
        $meta=json_encode($json ?? [],JSON_UNESCAPED_SLASHES);
        $file=file_get_contents($uploadPath);
        if ($file===false) throw new RuntimeException('File upload tidak dapat dibaca.');
        $body="--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: ".($mime ?: 'application/octet-stream')."\r\n\r\n".$file."\r\n--$boundary--";
        $headers[]='Content-Type: multipart/related; boundary='.$boundary; $opts[CURLOPT_POSTFIELDS]=$body;
    } elseif ($json !== null) { $headers[]='Content-Type: application/json'; $opts[CURLOPT_POSTFIELDS]=json_encode($json,JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch,$opts); curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if (!is_string($raw) || $err) throw new RuntimeException('Koneksi Google Drive gagal.');
    if ($status<200 || $status>=300) { $d=json_decode($raw,true); throw new RuntimeException('Google Drive API HTTP '.$status.': '.substr((string)($d['error']['message']??$raw),0,240)); }
    $decoded=json_decode($raw,true); return is_array($decoded)?$decoded:$raw;
}
function gdrive_about(int $userId): array {
    $r=gdrive_api($userId,'GET','https://www.googleapis.com/drive/v3/about?fields=user(displayName,emailAddress)');
    return is_array($r)?$r:[];
}
function gdrive_ensure_structure(int $userId): void {
    global $pdo;
    gdrive_require_tables();
    $s=$pdo->prepare('SELECT * FROM google_drive_connections WHERE user_id=?'); $s->execute([$userId]); $row=$s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    $root=$row['root_folder_id'] ?? null;
    if (!$root) {
        $name=defined('GOOGLE_DRIVE_ROOT_FOLDER')?GOOGLE_DRIVE_ROOT_FOLDER:'Cloud Guru - SDN Ciburayut 01';
        $found=gdrive_api($userId,'GET','https://www.googleapis.com/drive/v3/files?q='.rawurlencode("name='".str_replace("'","\\'",$name)."' and mimeType='application/vnd.google-apps.folder' and trashed=false").'&fields=files(id,name)&pageSize=10');
        if (empty($found['files'][0]['id'])) $created=gdrive_api($userId,'POST','https://www.googleapis.com/drive/v3/files',['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder']);
        else $created=$found['files'][0];
        $root=(string)$created['id'];
        $pdo->prepare('UPDATE google_drive_connections SET root_folder_id=? WHERE user_id=?')->execute([$root,$userId]);
    }
    foreach (['administrasi_folder_id'=>'Administrasi Guru','perencanaan_folder_id'=>'Perencanaan','asesmen_folder_id'=>'Asesmen'] as $column=>$name) {
        if (!empty($row[$column])) continue;
        $q="name='".str_replace("'","\\'",$name)."' and mimeType='application/vnd.google-apps.folder' and '".$root."' in parents and trashed=false";
        $found=gdrive_api($userId,'GET','https://www.googleapis.com/drive/v3/files?q='.rawurlencode($q).'&fields=files(id,name)&pageSize=10');
        $folder=$found['files'][0]??null;
        if (!$folder) $folder=gdrive_api($userId,'POST','https://www.googleapis.com/drive/v3/files',['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder','parents'=>[$root]]);
        $pdo->prepare("UPDATE google_drive_connections SET $column=? WHERE user_id=?")->execute([(string)$folder['id'],$userId]);
    }
}
