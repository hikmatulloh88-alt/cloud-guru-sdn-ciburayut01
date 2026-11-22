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

require_once __DIR__ . '/../config/database.php'; // $pdo = DB Cloud Guru
require_once __DIR__ . '/../absensi_sd/config/database.php'; // $koneksi = DB Absensi
require_once __DIR__ . '/../includes/csrf.php';

function login_safe_next(): string
{
    $next = isset($_GET['next']) && is_string($_GET['next']) ? $_GET['next'] : '';
    if (
        $next === '' ||
        $next[0] !== '/' ||
        substr($next, 0, 2) === '//' ||
        strpos($next, "\\") !== false ||
        preg_match('/[\r\n\x00]/', $next)
    ) {
        return '/dashboard/index.php';
    }
    return $next;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . login_safe_next(), true, 302);
    exit;
}

$loginCsrf = cloudguru_csrf_token();

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
    try {
        cloudguru_csrf_require_valid();
    } catch (RuntimeException $e) {
        $error = 'Sesi login kedaluwarsa. Muat ulang halaman lalu coba kembali.';
        $loginCsrf = cloudguru_csrf_token();
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($error !== '') {
        // CSRF rejection already set a safe, user-facing error.
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            // 1) Akun utama selalu diperiksa di database Absensi.
            $columns = [];
            $res = mysqli_query($koneksi, "SHOW COLUMNS FROM users");
            if ($res) while ($c = mysqli_fetch_assoc($res)) $columns[$c['Field']] = true;

            $userCol = ($columns['username'] ?? false) ? 'username' : (($columns['user'] ?? false) ? 'user' : null);
            $passCol = ($columns['password'] ?? false) ? 'password' : (($columns['pass'] ?? false) ? 'pass' : null);

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
                    $idCol = ($columns['id_user'] ?? false) ? 'id_user' : 'id';
                    $up = mysqli_prepare($koneksi, "UPDATE users SET `$passCol`=? WHERE `$idCol`=? LIMIT 1");
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

                    header('Location: ' . login_safe_next(), true, 303);
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
<title>Masuk — Cloud Guru SDN Ciburayut 01</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/auth/login.css?v=2">
</head>
<body class="login-page">
<main class="login-stage">
  <section class="login-aside" aria-label="Informasi portal sekolah">
    <div class="login-brand">
      <img src="/assets/images/logo-sekolah-ciburayut.svg" alt="Logo SDN Ciburayut 01">
      <div>
        <span>SDN CIBURAYUT 01</span>
        <small>KABUPATEN BOGOR</small>
      </div>
    </div>
    <div class="login-aside-copy">
      <span class="login-kicker">PORTAL SEKOLAH DIGITAL</span>
      <h2>Ruang kerja sekolah, dalam satu akun.</h2>
      <p>Kelola administrasi pembelajaran, arsip Google Drive, dan layanan digital pendidikan dengan akun yang sama seperti Aplikasi Absensi.</p>
      <div class="login-visual" aria-hidden="true">
        <span class="login-visual-orbit"></span>
        <span class="login-visual-card login-visual-card-main"><b>01</b><span>Cloud Guru<small>Terintegrasi Absensi</small></span></span>
        <span class="login-visual-card login-visual-card-small">✓ Single Sign-On</span>
      </div>
    </div>
    <div class="login-aside-foot">Akses khusus pendidik &amp; tenaga kependidikan</div>
  </section>

  <section class="login-panel">
    <div class="login-top-nav">
      <a class="login-back-portal" href="/?page=home">← Kembali ke Portal Sekolah</a>
    </div>
    <div class="login-card">
      <div class="login-heading">
        <span class="login-mobile-brand">Cloud Guru · SDN Ciburayut 01</span>
        <h1>Selamat Datang Kembali</h1>
        <p>Masukkan username dan password yang sama dengan Aplikasi Absensi.</p>
      </div>
      <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form id="loginForm" method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e($loginCsrf) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" placeholder="Masukkan username Anda" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <div class="password-wrap">
          <input id="password" type="password" name="password" placeholder="Masukkan password Anda" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan password" title="Tampilkan / Sembunyikan Password">
            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eyeClosed" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
          </button>
        </div>

        <button id="loginSubmit" class="btn primary full" type="submit">Masuk ke Cloud Guru</button>
      </form>
    </div>
    <p class="login-caption">Koneksi terenkripsi · Portal Administrasi Guru SDN Ciburayut 01</p>
  </section>
</main>
<script>
(function(){
  var pass = document.getElementById('password');
  var toggle = document.getElementById('togglePass');
  var eyeOpen = document.getElementById('eyeOpen');
  var eyeClosed = document.getElementById('eyeClosed');
  if (pass && toggle){
    toggle.addEventListener('click', function(){
      var show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      eyeOpen.style.display = show ? 'none' : 'block';
      eyeClosed.style.display = show ? 'block' : 'none';
      toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    });
  }
  var form = document.getElementById('loginForm');
  var btn = document.getElementById('loginSubmit');
  if (form && btn){
    form.addEventListener('submit', function(){
      btn.disabled = true;
      btn.textContent = 'Memverifikasi akun...';
    });
  }
})();
</script>
</body>
</html>
