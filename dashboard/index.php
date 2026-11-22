<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
wajib_login();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/sidebar.php';

$name = (string)($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Pengguna');
$role = (string)($_SESSION['role'] ?? 'Guru');
$isAdmin = in_array(strtolower($role), ['admin', 'administrator'], true);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard - Cloud Guru</title>
  <link rel="stylesheet" href="/assets/css/glass-ui.css?v=3">
  <style>
    body{margin:0;background:#eef4fb;color:#102a43;font-family:Arial,Helvetica,sans-serif}
    .cg-main-content{min-height:100vh;padding:40px 44px 70px;box-sizing:border-box}
    .dashboard-shell{max-width:1120px;margin:0 auto}
    .dashboard-hero{position:relative;overflow:hidden;padding:38px;border:1px solid rgba(255,255,255,.72);border-radius:24px;background:linear-gradient(135deg,#0b4f9c,#1981c5);color:#fff;box-shadow:0 22px 55px rgba(15,79,156,.16)}
    .dashboard-hero:after{content:"";position:absolute;width:260px;height:260px;right:-80px;bottom:-130px;border-radius:50%;background:rgba(255,255,255,.12)}
    .dashboard-hero h1{position:relative;z-index:1;margin:8px 0 10px;font-size:clamp(30px,5vw,48px)}
    .dashboard-hero p{position:relative;z-index:1;margin:0;color:#eaf5ff;line-height:1.7}
    .dashboard-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:22px}
    .dashboard-card{display:block;min-height:150px;padding:22px;border:1px solid #dde7f2;border-radius:18px;background:rgba(255,255,255,.86);box-shadow:0 12px 30px rgba(24,62,109,.08);color:inherit;text-decoration:none;transition:.2s ease}
    .dashboard-card:hover{transform:translateY(-3px);border-color:#a9c7e8;box-shadow:0 18px 38px rgba(24,62,109,.12)}
    .dashboard-card span{display:grid;width:44px;height:44px;place-items:center;border-radius:13px;background:#edf5ff;font-size:22px}
    .dashboard-card h2{margin:16px 0 7px;font-size:18px}
    .dashboard-card p{margin:0;color:#64748b;font-size:13px;line-height:1.6}
    @media(max-width:900px){.cg-main-content{padding:24px 18px 55px}.dashboard-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:620px){.dashboard-grid{grid-template-columns:1fr}.dashboard-hero{padding:28px 22px}.dashboard-card{min-height:0}}
  </style>
</head>
<body>
<main class="cg-main-content">
  <div class="dashboard-shell">
    <section class="dashboard-hero">
      <small><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></small>
      <h1>Halo, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
      <p>Pusat aktivitas Cloud Guru untuk administrasi, penyimpanan, dan pengelolaan portal sekolah.</p>
    </section>
    <section class="dashboard-grid" aria-label="Menu dashboard">
      <a class="dashboard-card" href="/storage/index.php">
        <span aria-hidden="true">▣</span><h2>Penyimpanan Saya</h2>
        <p>Kelola administrasi, perencanaan, asesmen, dan berkas guru.</p>
      </a>
      <?php if ($isAdmin): ?>
      <a class="dashboard-card" href="/builder/">
        <span aria-hidden="true">▦</span><h2>Editor Visual</h2>
        <p>Atur halaman, tampilan, informasi, dan konten portal sekolah.</p>
      </a>
      <a class="dashboard-card" href="/absensi_sd/modules/pengguna/index.php">
        <span aria-hidden="true">⚿</span><h2>Akun Guru</h2>
        <p>Buat dan kelola akun yang digunakan untuk masuk ke Cloud Guru.</p>
      </a>
      <?php else: ?>
      <a class="dashboard-card" href="/storage/index.php?folder=administrasi_folder_id">
        <span aria-hidden="true">▤</span><h2>Administrasi Guru</h2>
        <p>Buka dokumen dan administrasi yang tersedia untuk Anda.</p>
      </a>
      <a class="dashboard-card" href="/?page=home">
        <span aria-hidden="true">☁</span><h2>Portal Sekolah</h2>
        <p>Lihat tampilan publik Cloud Guru SDN Ciburayut 01.</p>
      </a>
      <?php endif; ?>
    </section>
  </div>
</main>
</body>
</html>
