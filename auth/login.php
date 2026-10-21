<?php
declare(strict_types=1);

/*
 * Cloud Guru - Single Account Login
 * Sumber akun utama: /absensi_sd (database absensi).
 * Setelah autentikasi berhasil, akun disinkronkan ke database Cloud Guru
 * agar modul portal tetap memakai user_id lokalnya sendiri.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ((isset($_GET['next']) && is_string($_GET['next']) && strpos($_GET['next'], '/') === 0 && strpos($_GET['next'], '//') !== 0 && strpos($_GET['next'], "\\") === false) ? $_GET['next'] : '../dashboard/index.php'));
    exit;
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $st->execute([$table]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function table_columns(PDO $pdo, string $table): array {
    $cols = [];
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `$table`");
        foreach ($st ?: [] as $r) {
            $cols[(string)$r['Field']] = true;
        }
    } catch (Throwable $e) {}
    return $cols;
}

function ensure_portal_users(PDO $pdo): array {
    if (!table_exists($pdo, 'users')) {
        $pdo->exec("
            CREATE TABLE users (
                id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                nama VARCHAR(150) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'Guru',
                id_guru INT NULL,
                id_siswa INT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'Aktif',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_users_guru (id_guru)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    return table_columns($pdo, 'users');
}

function portal_role(string $role): string {
    $role = strtolower(trim($role));
    if ($role === 'admin' || $role === 'administrator') return 'Admin';
    if ($role === 'operator') return 'Operator';
    if ($role === 'siswa' || $role === 'student' || $role === 'murid') return 'Siswa';
    return 'Guru';
}

function sync_portal_user(PDO $pdo, array $absUser): array {
    $cols = ensure_portal_users($pdo);

    $username = (string)$absUser['username'];
    $name = (string)($absUser['nama'] ?? $username);
    $role = portal_role((string)($absUser['role'] ?? 'Guru'));
    $status = (string)($absUser['status'] ?? 'Aktif');
    $passwordHash = (string)($absUser['password'] ?? '');
    $idGuru = isset($absUser['id_guru']) && $absUser['id_guru'] !== null
        ? (int)$absUser['id_guru'] : null;
    $idSiswa = isset($absUser['id_siswa']) && $absUser['id_siswa'] !== null
        ? (int)$absUser['id_siswa'] : null;

    $st = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $local = $st->fetch(PDO::FETCH_ASSOC);

    if ($local) {
        $sets = [];
        $params = [];

        if (isset($cols['password']) && $passwordHash !== '') {
            $sets[] = 'password = ?'; $params[] = $passwordHash;
        }
        if (isset($cols['nama'])) {
            $sets[] = 'nama = ?'; $params[] = $name;
        } elseif (isset($cols['name'])) {
            $sets[] = 'name = ?'; $params[] = $name;
        }
        if (isset($cols['role'])) { $sets[] = 'role = ?'; $params[] = $role; }
        if (isset($cols['status'])) { $sets[] = 'status = ?'; $params[] = $status; }
        if (isset($cols['id_guru'])) { $sets[] = 'id_guru = ?'; $params[] = $idGuru; }
        if (isset($cols['id_siswa'])) { $sets[] = 'id_siswa = ?'; $params[] = $idSiswa; }

        $idCol = isset($cols['id_user']) ? 'id_user' : (isset($cols['id']) ? 'id' : null);
        if ($sets && $idCol) {
            $params[] = (int)$local[$idCol];
            $pdo->prepare("UPDATE users SET ".implode(', ', $sets)." WHERE `$idCol` = ? LIMIT 1")
                ->execute($params);
        }
        $localId = (int)$local[$idCol];
    } else {
        $fields = [];
        $marks = [];
        $params = [];

        if (isset($cols['username'])) { $fields[]='username'; $marks[]='?'; $params[]=$username; }
        if (isset($cols['password'])) { $fields[]='password'; $marks[]='?'; $params[]=$passwordHash; }
        if (isset($cols['nama'])) { $fields[]='nama'; $marks[]='?'; $params[]=$name; }
        elseif (isset($cols['name'])) { $fields[]='name'; $marks[]='?'; $params[]=$name; }
        if (isset($cols['role'])) { $fields[]='role'; $marks[]='?'; $params[]=$role; }
        if (isset($cols['status'])) { $fields[]='status'; $marks[]='?'; $params[]=$status; }
        if (isset($cols['id_guru'])) { $fields[]='id_guru'; $marks[]='?'; $params[]=$idGuru; }
        if (isset($cols['id_siswa'])) { $fields[]='id_siswa'; $marks[]='?'; $params[]=$idSiswa; }

        if (!$fields) {
            throw new RuntimeException('Struktur tabel users Cloud Guru tidak dapat digunakan.');
        }

        $pdo->prepare(
            "INSERT INTO users (`".implode('`,`', $fields)."`) VALUES (".implode(',', $marks).")"
        )->execute($params);

        $localId = (int)$pdo->lastInsertId();
    }

    return ['id' => $localId, 'name' => $name, 'role' => $role, 'id_guru' => $idGuru, 'id_siswa' => $idSiswa];
}

function sync_teacher_profile(PDO $pdo, int $portalUserId, ?int $idGuru, string $name): void {
    if (!$idGuru || !table_exists($pdo, 'teachers')) return;

    $cols = table_columns($pdo, 'teachers');
    if (!isset($cols['user_id'])) return;

    // Ambil data guru dari database absensi untuk menyamakan profil portal.
    global $koneksi;
    $guru = null;
    $st = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_guru = ? LIMIT 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $idGuru);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $guru = $rs ? mysqli_fetch_assoc($rs) : null;
        mysqli_stmt_close($st);
    }
    if (!$guru) return;

    $existing = null;
    $st = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
    $st->execute([$portalUserId]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // Jika belum tertaut, cari berdasarkan NIP atau nama.
        if (isset($cols['nip']) && !empty($guru['nip'])) {
            $st = $pdo->prepare("SELECT * FROM teachers WHERE nip = ? LIMIT 1");
            $st->execute([(string)$guru['nip']]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
        }
        if (!$existing) {
            $st = $pdo->prepare("SELECT * FROM teachers WHERE name = ? LIMIT 1");
            $st->execute([$name]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
        }
    }

    $data = [];
    if (isset($cols['user_id'])) $data['user_id'] = $portalUserId;
    if (isset($cols['name'])) $data['name'] = (string)($guru['nama'] ?? $name);
    if (isset($cols['nip'])) $data['nip'] = (string)($guru['nip'] ?? '');
    if (isset($cols['nuptk'])) $data['nuptk'] = (string)($guru['nuptk'] ?? '');
    if (isset($cols['position'])) $data['position'] = (string)($guru['jabatan'] ?? 'Guru');
    if (isset($cols['subject'])) $data['subject'] = (string)($guru['mapel'] ?? $guru['mata_pelajaran'] ?? '');
    if (isset($cols['email']) && isset($guru['email'])) $data['email'] = (string)$guru['email'];
    if (isset($cols['phone'])) {
        $phone = $guru['hp'] ?? $guru['telepon'] ?? $guru['no_hp'] ?? '';
        $data['phone'] = (string)$phone;
    }

    if ($existing && isset($existing['id'])) {
        $sets=[]; $params=[];
        foreach ($data as $k=>$v) { $sets[]="`$k`=?"; $params[]=$v; }
        if ($sets) {
            $params[]=(int)$existing['id'];
            $pdo->prepare("UPDATE teachers SET ".implode(',', $sets)." WHERE id=? LIMIT 1")->execute($params);
        }
    } else {
        if (!$data) return;
        $fields=array_keys($data);
        $pdo->prepare(
            "INSERT INTO teachers (`".implode('`,`',$fields)."`) VALUES (".implode(',',array_fill(0,count($fields),'?')).")"
        )->execute(array_values($data));
    }
}

function ensure_personal_folders(PDO $pdo, int $portalUserId): void {
    if (!table_exists($pdo, 'storage_folders')) return;

    $cols = table_columns($pdo, 'storage_folders');
    if (!isset($cols['user_id']) || !isset($cols['folder_name'])) return;

    $required = ['Administrasi Guru', 'Perencanaan', 'Asesmen'];
    foreach ($required as $i => $folderName) {
        $st = $pdo->prepare("SELECT id FROM storage_folders WHERE user_id=? AND folder_name=? LIMIT 1");
        $st->execute([$portalUserId, $folderName]);
        if (!$st->fetchColumn()) {
            $fields=['user_id','folder_name'];
            $values=[$portalUserId,$folderName];
            if (isset($cols['parent_id'])) { $fields[]='parent_id'; $values[]=null; }
            if (isset($cols['sort_order'])) { $fields[]='sort_order'; $values[]=$i+1; }
            $pdo->prepare(
                "INSERT INTO storage_folders (`".implode('`,`',$fields)."`) VALUES (".implode(',',array_fill(0,count($fields),'?')).")"
            )->execute($values);
        }
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            // Muat koneksi hanya saat form dikirim agar halaman login tidak ikut 500 jika konfigurasi DB bermasalah.
            require_once __DIR__ . '/../config/database.php'; // $pdo = DB Cloud Guru
            require_once __DIR__ . '/../absensi_sd/config/database.php'; // $koneksi = DB Absensi

            // 1) Akun utama selalu diperiksa di database Absensi.
            $columns = [];
            $res = mysqli_query($koneksi, "SHOW COLUMNS FROM users");
            if ($res) while ($c = mysqli_fetch_assoc($res)) $columns[$c['Field']] = true;

            $userCol = !empty($columns['username']) ? 'username' : (!empty($columns['user']) ? 'user' : null);
            $passCol = !empty($columns['password']) ? 'password' : (!empty($columns['pass']) ? 'pass' : null);

            if (!$userCol || !$passCol) {
                throw new RuntimeException('Struktur akun Absensi tidak sesuai.');
            }

            $st = mysqli_prepare($koneksi, "SELECT * FROM users WHERE `$userCol`=? LIMIT 1");
            mysqli_stmt_bind_param($st, 's', $username);
            mysqli_stmt_execute($st);
            $rs = mysqli_stmt_get_result($st);
            $absUser = $rs ? mysqli_fetch_assoc($rs) : null;
            mysqli_stmt_close($st);

            if (!$absUser) {
                $error = 'Akun belum terdaftar di Aplikasi Absensi. Buat akun guru dari menu Akun Guru terlebih dahulu.';
            } else {
                $status = strtolower(trim((string)($absUser['status'] ?? 'aktif')));
                $active = in_array($status, ['', 'aktif', 'active', '1', 'ya'], true);

                $stored = (string)($absUser[$passCol] ?? '');
                $ok = $stored !== '' && password_verify($password, $stored);

                if (!$ok && $stored !== '' && hash_equals($stored, $password)) {
                    $ok = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $idCol = isset($columns['id_user']) ? 'id_user' : (isset($columns['id']) ? 'id' : null);
                    $up = $idCol !== null ? mysqli_prepare($koneksi, "UPDATE users SET `$passCol`=? WHERE `$idCol`=? LIMIT 1") : false;
                    if ($up) {
                        $uid=(int)$absUser[$idCol];
                        mysqli_stmt_bind_param($up,'si',$newHash,$uid);
                        mysqli_stmt_execute($up);
                        mysqli_stmt_close($up);
                        $absUser[$passCol]=$newHash;
                    }
                }

                if (!$active) {
                    $error = 'Akun sedang tidak aktif.';
                } elseif (!$ok) {
                    $error = 'Username atau password salah.';
                } else {
                    $portal = sync_portal_user($pdo, $absUser);
                    sync_teacher_profile($pdo, (int)$portal['id'], $portal['id_guru'], (string)$portal['name']);
                    ensure_personal_folders($pdo, (int)$portal['id']);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$portal['id'];
                    $_SESSION['user_name'] = (string)$portal['name'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = (string)$portal['role'];
                    $_SESSION['id_guru'] = $portal['id_guru'];
                    $_SESSION['id_siswa'] = $portal['id_siswa'];
                    $_SESSION['absensi_user_id'] = isset($absUser['id_user']) ? (int)$absUser['id_user'] : null;
                    $_SESSION['login_user'] = true;

                    header('Location: ../dashboard/index.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('Cloud Guru single-account login: '.$e->getMessage());
            $error = 'Login tidak dapat diproses. Pastikan akun guru sudah dibuat di Aplikasi Absensi.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Cloud Guru</title>
<link rel="stylesheet" href="/assets/css/main.css">
<style>
:root{color-scheme:light;--blue:#175cd3;--ink:#172033;--muted:#667085;--line:#d0d5dd}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 12% 12%,#dbeafe 0,transparent 34%),linear-gradient(135deg,#f8fafc,#eef2ff);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.login-wrap{width:min(100%,440px);background:#fff;border:1px solid #e4e7ec;border-radius:22px;padding:34px;box-shadow:0 24px 70px rgba(16,24,40,.12)}
.login-mark{width:52px;height:52px;display:grid;place-items:center;border-radius:16px;background:#eaf2ff;color:var(--blue);font-size:26px;margin-bottom:20px}
h1{font-size:26px;line-height:1.2;letter-spacing:-.6px;margin:0 0 10px}p{color:var(--muted);margin:0 0 24px}
form{display:grid;gap:9px}label{font-size:14px;font-weight:650;margin-top:7px}input{width:100%;height:46px;border:1px solid var(--line);border-radius:10px;padding:0 13px;font:inherit;outline:none}input:focus{border-color:var(--blue);box-shadow:0 0 0 3px #175cd322}
button{height:47px;margin-top:12px;border:0;border-radius:10px;background:var(--blue);color:#fff;font:inherit;font-weight:700;cursor:pointer}button:hover{background:#1849a9}.alert{background:#fef3f2;color:#b42318;border:1px solid #fecdca;border-radius:10px;padding:11px 13px;margin:0 0 16px;font-size:14px}
.login-foot{font-size:12px;text-align:center;margin-top:22px;color:#98a2b3}
@media(max-width:480px){body{padding:14px}.login-wrap{padding:25px 20px;border-radius:18px}h1{font-size:24px}}
</style>
</head>
<body class="login-page">
<div class="login-wrap">
<div class="logo">☁️</div>
<h1>Cloud Administrasi Guru</h1>
<p>Gunakan username dan password yang sama dengan Aplikasi Absensi.</p>
<?php if ($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" autocomplete="on">
<label>Username</label>
<input name="username" autocomplete="username" required autofocus>
<label>Password</label>
<input type="password" name="password" autocomplete="current-password" required>
<button class="btn primary full" type="submit">Masuk</button>
</form>
<div class="login-foot">SDN Ciburayut 01 · Portal Administrasi Guru</div>
</div>
</body>
</html>
