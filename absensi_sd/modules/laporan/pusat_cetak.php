<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
wajib_login();
wajib_role('Admin', 'Operator', 'Guru');

$menu_aktif = 'laporan';
$page_title = 'Laporan & Cetak - SDN Ciburayut 01';

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$role = strtolower((string)($_SESSION['role'] ?? ''));

$can = static function(string $menu): bool {
    return function_exists('izin_menu') ? izin_menu($menu) : true;
};

$items = [
    [
        'key'   => 'kartu_absensi',
        'icon'  => '🎫',
        'title' => 'Kartu Absensi Siswa',
        'desc'  => 'Cetak kartu identitas dan absensi siswa lengkap dengan foto dan QR Code.',
        'url'   => '../absensi/cetak_kartu.php',
        'roles' => ['admin', 'operator', 'guru'],
    ],
    [
        'key'   => 'kartu_absensi',
        'icon'  => '📋',
        'title' => 'Daftar Absensi Kelas',
        'desc'  => 'Cetak daftar hadir siswa berdasarkan kelas, rombel, dan periode.',
        'url'   => 'cetak_absen_kelas.php',
        'roles' => ['admin', 'operator', 'guru'],
    ],
    [
        'key'   => 'laporan',
        'icon'  => '📊',
        'title' => 'Laporan Nilai / Rapor',
        'desc'  => 'Lihat rekap nilai siswa dan buka rapor berdasarkan tahun ajaran.',
        'url'   => 'index.php',
        'roles' => ['admin', 'operator', 'guru'],
    ],
    [
        'key'   => 'laporan',
        'icon'  => '🖨️',
        'title' => 'Cetak Rapor Massal',
        'desc'  => 'Cetak rapor beberapa siswa sekaligus berdasarkan kelas dan periode.',
        'url'   => 'cetak_masal.php',
        'roles' => ['admin', 'operator', 'guru'],
    ],
    [
        'key'   => 'buku_induk_masal',
        'icon'  => '📖',
        'title' => 'Cetak Buku Induk',
        'desc'  => 'Cetak Buku Induk siswa secara massal berdasarkan kelas.',
        'url'   => '../buku_induk/cetak_masal.php',
        'roles' => ['admin', 'operator'],
    ],
    [
        'key'   => 'kartu_absensi',
        'icon'  => '👨‍🏫',
        'title' => 'Kartu Guru / Pegawai',
        'desc'  => 'Cetak kartu Guru/Pegawai menggunakan data master Guru.',
        'url'   => '../guru/cetak_kartu.php',
        'roles' => ['admin', 'operator'],
    ],
    [
        'key'   => 'pengguna',
        'icon'  => '🔐',
        'title' => 'Cetak Akun Guru',
        'desc'  => 'Cetak informasi akun Guru yang tersedia pada menu Pengguna.',
        'url'   => 'cetak_akun_guru.php',
        'roles' => ['admin', 'operator'],
    ],
    [
        'key'   => 'pengguna',
        'icon'  => '👨‍🎓',
        'title' => 'Cetak Akun Siswa',
        'desc'  => 'Cetak informasi akun siswa berdasarkan kelas.',
        'url'   => 'cetak_akun_siswa.php',
        'roles' => ['admin', 'operator'],
    ],
    [
        'key'   => 'ekstrakurikuler',
        'icon'  => '🏆',
        'title' => 'Laporan Ekstrakurikuler',
        'desc'  => 'Cetak laporan kegiatan dan rekap kehadiran ekstrakurikuler.',
        'url'   => '../ekstrakurikuler/laporan.php',
        'roles' => ['admin', 'operator', 'guru'],
    ],
];

$visible = array_values(array_filter(
    $items,
    static function(array $item) use ($can, $role): bool {
        return in_array($role, $item['roles'], true) && $can($item['key']);
    }
));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.lpc{max-width:1280px;margin:0 auto}
.lpc-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}
.lpc-title h1{margin:0 0 6px;font-size:30px;line-height:1.2;color:#14243d}
.lpc-title p{margin:0;color:#64748b;font-size:14px}
.lpc-badge{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border:1px solid #d8e7fb;border-radius:999px;background:#f0f7ff;color:#176fe5;font-size:12px;font-weight:800;white-space:nowrap}
.lpc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.lpc-card{display:flex;flex-direction:column;min-height:235px;padding:22px;background:#fff;border:1px solid #dce6f0;border-radius:18px;box-shadow:0 8px 24px rgba(15,40,70,.06);transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.lpc-card:hover{transform:translateY(-2px);border-color:#c8dcf4;box-shadow:0 12px 30px rgba(15,40,70,.10)}
.lpc-icon{display:flex;align-items:center;justify-content:center;width:52px;height:52px;margin-bottom:16px;border-radius:15px;background:#edf5ff;font-size:26px}
.lpc-card h2{margin:0 0 8px;color:#172b49;font-size:18px;line-height:1.3}
.lpc-card p{flex:1;margin:0;color:#64748b;font-size:13px;line-height:1.6}
.lpc-btn{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:18px;padding:11px 15px;border-radius:10px;background:linear-gradient(135deg,#2388f5,#1265dc);color:#fff;text-decoration:none;font-size:13px;font-weight:800;box-shadow:0 7px 16px rgba(18,101,220,.15)}
.lpc-btn:hover{background:linear-gradient(135deg,#1979e5,#0d5dcc)}
.lpc-note{margin-top:20px;padding:15px 17px;border:1px solid #e2eaf3;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.55}
.lpc-note strong{color:#334155}
@media(max-width:1050px){.lpc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){
 .lpc-head{display:block}
 .lpc-badge{margin-top:14px}
 .lpc-grid{grid-template-columns:1fr}
 .lpc-card{min-height:205px}
 .lpc-title h1{font-size:25px}
}
@media print{
 @page{size:A4 portrait;margin:12mm}
 .sidebar,.no-print{display:none!important}
 .main-content{margin:0!important;padding:0!important}
 .lpc{max-width:none}
 .lpc-head{margin-bottom:18px}
 .lpc-grid{grid-template-columns:repeat(2,1fr)}
 .lpc-card{box-shadow:none;break-inside:avoid}
 .lpc-btn{background:#fff;color:#111;border:1px solid #ddd;box-shadow:none}
}
</style>

<main class="main-content">
<div class="lpc">

    <div class="lpc-head">
        <div class="lpc-title">
            <h1>🖨️ Laporan &amp; Cetak</h1>
            <p>Semua laporan dan fasilitas cetak SDN Ciburayut 01 tersedia di satu tempat.</p>
        </div>
        <div class="lpc-badge">SDN CIBURAYUT 01</div>
    </div>

    <div class="lpc-grid">
        <?php foreach ($visible as $item): ?>
            <section class="lpc-card">
                <div class="lpc-icon"><?=h($item['icon'])?></div>
                <h2><?=h($item['title'])?></h2>
                <p><?=h($item['desc'])?></p>
                <a class="lpc-btn" href="<?=h($item['url'])?>">
                    Buka &amp; Cetak <span aria-hidden="true">→</span>
                </a>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="lpc-note">
        <strong>Info:</strong> halaman ini hanya menjadi pusat akses. Proses pencetakan tetap menggunakan modul asli masing-masing sehingga data, filter, dan format cetak tidak berubah.
    </div>

</div>
</main>

</body>
</html>
