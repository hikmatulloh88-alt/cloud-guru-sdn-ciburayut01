<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
wajib_login();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/sidebar.php';

$name = trim((string)($_SESSION['user_name'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna')) ?: 'Pengguna';
$role = (string)($_SESSION['role'] ?? 'Guru');
$isAdmin = in_array(strtolower(trim($role)), ['admin', 'administrator'], true);
$userId = (int)($_SESSION['user_id'] ?? 0);

// Greeting based on WIB server/local hour
$hour = (int)date('G');
if ($hour >= 4 && $hour < 11) {
    $greeting = 'Selamat Pagi';
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = 'Selamat Siang';
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = 'Selamat Sore';
} else {
    $greeting = 'Selamat Malam';
}

$hariMap = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanMap = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalIndo = $hariMap[(int)date('w')] . ', ' . date('j') . ' ' . $bulanMap[(int)date('n')] . ' ' . date('Y');

// Safe read-only check of Google Drive connection status
$driveConnected = false;
$driveEmail = '';
try {
    if ($userId > 0 && isset($pdo) && $pdo instanceof PDO) {
        $st = $pdo->prepare('SELECT google_email, refresh_token_enc FROM google_drive_connections WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['refresh_token_enc'])) {
            $driveConnected = true;
            $driveEmail = (string)($row['google_email'] ?? '');
        }
    }
} catch (Throwable $e) {
    // Keep dashboard resilient if table has not been initialized yet.
}

function dash_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Cloud Guru</title>
  <link rel="stylesheet" href="/assets/css/glass-ui.css?v=4">
  <style>
    :root{
      --dash-bg:#f0f5fc;
      --dash-ink:#0f172a;
      --dash-muted:#64748b;
      --dash-line:#e2e8f0;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      background:
        radial-gradient(ellipse at 12% 0%,rgba(56,189,248,.14),transparent 38%),
        radial-gradient(ellipse at 92% 15%,rgba(37,99,235,.1),transparent 35%),
        var(--dash-bg);
      color:var(--dash-ink);
      font-family:var(--cg-font,'Plus Jakarta Sans',system-ui,-apple-system,sans-serif);
    }
    .cg-main-content{min-height:100vh;padding:34px 40px 64px}
    .dashboard-shell{max-width:1160px;margin:0 auto}

    /* Hero Banner */
    .dashboard-hero{
      position:relative;overflow:hidden;padding:36px 40px;
      border:1px solid rgba(255,255,255,.35);border-radius:26px;
      background:
        radial-gradient(circle at 88% 22%,rgba(56,189,248,.36),transparent 36%),
        linear-gradient(125deg,#09244b 0%,#0f4c9c 55%,#1475c4 100%);
      color:#fff;box-shadow:0 22px 50px rgba(11,45,92,.18);
      display:flex;align-items:center;justify-content:space-between;gap:28px;
    }
    .dashboard-hero:after{
      content:"";position:absolute;width:300px;height:300px;right:-70px;bottom:-150px;
      border-radius:50%;border:1px solid rgba(255,255,255,.16);
      box-shadow:0 0 0 36px rgba(255,255,255,.04);pointer-events:none;
    }
    .hero-copy{position:relative;z-index:1;max-width:650px}
    .hero-meta{
      display:inline-flex;align-items:center;gap:8px;padding:6px 12px;
      border-radius:999px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);
      font-size:12px;font-weight:700;color:#e0f2fe;margin-bottom:14px;
    }
    .dashboard-hero h1{margin:0 0 10px;font-size:clamp(26px,4vw,40px);font-weight:800;letter-spacing:-.03em;line-height:1.15}
    .dashboard-hero p{margin:0;color:#dbeafe;font-size:15px;line-height:1.65;max-width:560px}
    .hero-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}
    .hero-btn{
      display:inline-flex;align-items:center;gap:9px;min-height:44px;padding:10px 18px;
      border-radius:13px;font-size:13.5px;font-weight:700;text-decoration:none;transition:all .18s ease;
    }
    .hero-btn svg{width:17px;height:17px}
    .hero-btn-primary{background:#fff;color:#0f4c9c;box-shadow:0 10px 24px rgba(4,18,44,.2)}
    .hero-btn-primary:hover{transform:translateY(-2px);background:#f0f9ff;box-shadow:0 14px 28px rgba(4,18,44,.25)}
    .hero-btn-secondary{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28)}
    .hero-btn-secondary:hover{background:rgba(255,255,255,.22);transform:translateY(-2px)}

    /* Status Strip */
    .drive-strip{
      display:flex;align-items:center;justify-content:space-between;gap:16px;
      margin-top:20px;padding:16px 22px;border-radius:18px;
      border:1px solid rgba(255,255,255,.85);background:rgba(255,255,255,.78);
      box-shadow:0 10px 28px rgba(15,23,42,.05);backdrop-filter:blur(14px);
    }
    .drive-strip-info{display:flex;align-items:center;gap:14px;min-width:0}
    .drive-strip-icon{
      width:44px;height:44px;border-radius:13px;display:grid;place-items:center;flex:none;
      background:#eff6ff;color:#1d4ed8;
    }
    .drive-strip-icon svg{width:22px;height:22px}
    .drive-strip-title{font-size:14px;font-weight:700;color:#0f172a}
    .drive-strip-sub{font-size:12.5px;color:var(--dash-muted);margin-top:2px;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
    .status-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
    .status-dot.ok{background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.18)}
    .status-dot.warn{background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.18)}
    .strip-btn{
      display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:11px;
      font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;transition:.18s ease;
    }
    .strip-btn.connected{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
    .strip-btn.connected:hover{background:#dbeafe}
    .strip-btn.connect{background:linear-gradient(110deg,#0f4c9c,#1d70c9);color:#fff;box-shadow:0 6px 16px rgba(15,76,156,.22)}
    .strip-btn.connect:hover{transform:translateY(-1px);filter:brightness(1.05)}

    /* Section Headings */
    .section-head{display:flex;align-items:baseline;justify-content:space-between;margin:28px 2px 14px}
    .section-head h2{margin:0;font-size:17px;font-weight:800;letter-spacing:-.02em;color:#0f172a}
    .section-head span{font-size:12.5px;color:var(--dash-muted)}

    /* Workspace Cards */
    .dashboard-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
    .dashboard-card{
      position:relative;display:flex;flex-direction:column;justify-content:space-between;
      min-height:164px;padding:22px;border:1px solid rgba(255,255,255,.9);border-radius:20px;
      background:rgba(255,255,255,.84);box-shadow:0 12px 30px rgba(15,35,68,.06);
      color:inherit;text-decoration:none;transition:all .2s cubic-bezier(.4,0,.2,1);
      backdrop-filter:blur(12px);
    }
    .dashboard-card:hover{
      transform:translateY(-4px);border-color:#93c5fd;
      background:#fff;box-shadow:0 20px 40px rgba(15,76,156,.12);
    }
    .card-top{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .card-icon{
      display:grid;width:46px;height:46px;place-items:center;border-radius:14px;
      transition:transform .2s ease;
    }
    .dashboard-card:hover .card-icon{transform:scale(1.06)}
    .card-icon svg{width:22px;height:22px}
    .ic-blue{background:#eff6ff;color:#1d4ed8}
    .ic-sky{background:#f0f9ff;color:#0284c7}
    .ic-emerald{background:#ecfdf5;color:#059669}
    .ic-indigo{background:#eef2ff;color:#4338ca}
    .ic-violet{background:#f5f3ff;color:#6d28d9}
    .ic-amber{background:#fffbeb;color:#d97706}
    .card-arrow{
      width:30px;height:30px;border-radius:999px;display:grid;place-items:center;
      color:#94a3b8;background:#f8fafc;font-size:14px;transition:all .2s ease;
    }
    .dashboard-card:hover .card-arrow{background:#eff6ff;color:#1d4ed8;transform:translateX(2px)}
    .dashboard-card h3{margin:16px 0 6px;font-size:16.5px;font-weight:700;color:#0f172a;letter-spacing:-.01em}
    .dashboard-card p{margin:0;color:var(--dash-muted);font-size:13px;line-height:1.55}

    @media(max-width:960px){
      .cg-main-content{padding:24px 20px 56px}
      .dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:640px){
      .dashboard-grid{grid-template-columns:1fr}
      .dashboard-hero{padding:26px 22px;border-radius:22px}
      .drive-strip{flex-direction:column;align-items:flex-start}
      .strip-btn{width:100%;justify-content:center}
      .hero-actions{flex-direction:column}
      .hero-btn{width:100%;justify-content:center}
    }
  </style>
</head>
<body>
<main class="cg-main-content">
  <div class="dashboard-shell">
    <section class="dashboard-hero">
      <div class="hero-copy">
        <div class="hero-meta">
          <span><?= dash_e($isAdmin ? 'Administrator' : 'Ruang Kerja Guru') ?></span>
          <span>•</span>
          <span><?= dash_e($tanggalIndo) ?></span>
        </div>
        <h1><?= dash_e($greeting) ?>, <?= dash_e($name) ?></h1>
        <p>Kelola administrasi pembelajaran, simpan dokumen ke Google Drive sekolah, dan buat media pembelajaran digital dalam satu tempat.</p>
        <div class="hero-actions">
          <a class="hero-btn hero-btn-primary" href="/storage/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span>Unggah &amp; Kelola File</span>
          </a>
          <a class="hero-btn hero-btn-secondary" href="/ai-studio/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
            <span>Buka AI Studio</span>
          </a>
        </div>
      </div>
    </section>

    <section class="drive-strip" aria-label="Status koneksi Google Drive">
      <div class="drive-strip-info">
        <div class="drive-strip-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
        </div>
        <div>
          <div class="drive-strip-title">Integrasi Google Drive Guru</div>
          <div class="drive-strip-sub">
            <?php if ($driveConnected): ?>
              <span class="status-dot ok"></span>
              <span>Terhubung<?= $driveEmail !== '' ? ' sebagai <strong>' . dash_e($driveEmail) . '</strong>' : '' ?> — Siap menyimpan dokumen</span>
            <?php else: ?>
              <span class="status-dot warn"></span>
              <span>Belum terhubung — Hubungkan akun Google Drive Anda untuk mulai menyimpan dokumen</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($driveConnected): ?>
        <a class="strip-btn connected" href="/storage/index.php">Buka Penyimpanan →</a>
      <?php else: ?>
        <a class="strip-btn connect" href="/gdrive/connect.php">Hubungkan Google Drive</a>
      <?php endif; ?>
    </section>

    <div class="section-head">
      <h2>Ruang Kerja Utama</h2>
      <span>Akses cepat folder &amp; alat bantu guru</span>
    </div>

    <section class="dashboard-grid" aria-label="Menu ruang kerja">
      <a class="dashboard-card" href="/storage/index.php">
        <div class="card-top">
          <div class="card-icon ic-blue"><?= cg_sidebar_svg('folder') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Semua Penyimpanan</h3>
          <p>Lihat seluruh berkas dan folder pribadi yang tersinkronisasi dengan Google Drive Anda.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/storage/index.php?folder=administrasi_folder_id">
        <div class="card-top">
          <div class="card-icon ic-sky"><?= cg_sidebar_svg('briefcase') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Administrasi Guru</h3>
          <p>Simpan perangkat ajar, daftar hadir, jurnal mengajar, dan kelengkapan administrasi kelas.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/storage/index.php?folder=perencanaan_folder_id">
        <div class="card-top">
          <div class="card-icon ic-emerald"><?= cg_sidebar_svg('calendar') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Perencanaan</h3>
          <p>Kelola Program Tahunan (Prota), Program Semester (Promes), Modul Ajar, dan ATP.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/storage/index.php?folder=asesmen_folder_id">
        <div class="card-top">
          <div class="card-icon ic-indigo"><?= cg_sidebar_svg('check-square') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Asesmen &amp; Evaluasi</h3>
          <p>Arsipkan kisi-kisi soal, instrumen penilaian formatif/sumatif, dan rekap hasil belajar.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/ai-studio/index.php">
        <div class="card-top">
          <div class="card-icon ic-violet"><?= cg_sidebar_svg('sparkles') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>AI Studio</h3>
          <p>Ubah foto kegiatan atau ilustrasi pembelajaran menjadi video pendek realistis berbasis AI.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/ai-studio/rrp.php">
        <div class="card-top">
          <div class="card-icon ic-indigo"><?= cg_sidebar_svg('layout') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Buat RPP</h3>
          <p>Hasilkan Rencana Pelaksanaan Pembelajaran lengkap, lalu unduh dalam format .DOC.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/guru/">
        <div class="card-top">
          <div class="card-icon ic-amber"><?= cg_sidebar_svg('user') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3><?= $isAdmin ? 'Profil Guru' : 'Profil Saya' ?></h3>
          <p>Periksa biodata pendidik, jabatan, dan informasi akun yang terhubung dengan sekolah.</p>
        </div>
      </a>
    </section>

    <?php if ($isAdmin): ?>
    <div class="section-head">
      <h2>Administrasi Sekolah</h2>
      <span>Menu khusus pengelola sistem &amp; website</span>
    </div>

    <section class="dashboard-grid" aria-label="Menu administrator">
      <a class="dashboard-card" href="/builder/">
        <div class="card-top">
          <div class="card-icon ic-blue"><?= cg_sidebar_svg('layout') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Editor Visual Website</h3>
          <p>Ubah tampilan beranda, berita, galeri foto, dan halaman informasi publik sekolah.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/absensi_sd/modules/pengguna/index.php">
        <div class="card-top">
          <div class="card-icon ic-emerald"><?= cg_sidebar_svg('key') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Akun Guru &amp; Absensi</h3>
          <p>Kelola akun login guru yang digunakan bersama antara Aplikasi Absensi dan Cloud Guru.</p>
        </div>
      </a>

      <a class="dashboard-card" href="/self-healing/admin.php">
        <div class="card-top">
          <div class="card-icon ic-amber"><?= cg_sidebar_svg('shield') ?></div>
          <span class="card-arrow" aria-hidden="true">→</span>
        </div>
        <div>
          <h3>Self-Healing &amp; Keamanan</h3>
          <p>Pantau kesehatan sistem, log error server, dan cadangan file konfigurasi secara aman.</p>
        </div>
      </a>
    </section>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
