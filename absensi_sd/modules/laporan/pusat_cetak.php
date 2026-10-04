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
    ['key'=>'kartu_absensi','icon'=>'🎫','title'=>'Kartu Absensi Siswa','desc'=>'Cetak kartu absensi siswa per kelas atau berdasarkan pencarian.','url'=>'../absensi/cetak_kartu.php','roles'=>['admin','operator','guru']],
    ['key'=>'kartu_absensi','icon'=>'📋','title'=>'Absensi Siswa per Kelas','desc'=>'Cetak daftar hadir bulanan lengkap berdasarkan kelas/rombel.','url'=>'cetak_absen_kelas.php','roles'=>['admin','operator','guru']],
    ['key'=>'laporan','icon'=>'📊','title'=>'Laporan / Rapor Nilai','desc'=>'Lihat, pilih siswa, lalu cetak rapor atau rekap nilai.','url'=>'index.php','roles'=>['admin','operator','guru']],
    ['key'=>'laporan','icon'=>'🖨️','title'=>'Cetak Semua Rapor','desc'=>'Cetak rapor secara massal berdasarkan tahun ajaran dan kelas.','url'=>'cetak_masal.php','roles'=>['admin','operator','guru']],
    ['key'=>'buku_induk_masal','icon'=>'📖','title'=>'Cetak Buku Induk','desc'=>'Cetak Buku Induk siswa secara massal berdasarkan kelas.','url'=>'../buku_induk/cetak_masal.php','roles'=>['admin','operator']],
    ['key'=>'kartu_absensi','icon'=>'👨‍🏫','title'=>'Kartu Guru / Pegawai','desc'=>'Cetak kartu Guru/Pegawai dengan data dari master Guru.','url'=>'../guru/cetak_kartu.php','roles'=>['admin','operator']],
    ['key'=>'pengguna','icon'=>'🔐','title'=>'Cetak Akun Guru','desc'=>'Cetak daftar akun Guru yang telah dibuat pada menu Pengguna.','url'=>'cetak_akun_guru.php','roles'=>['admin','operator']],
    ['key'=>'pengguna','icon'=>'👨‍🎓','title'=>'Cetak Akun Siswa','desc'=>'Cetak akun siswa berdasarkan kelas.','url'=>'cetak_akun_siswa.php','roles'=>['admin','operator']],
    ['key'=>'ekstrakurikuler','icon'=>'🏆','title'=>'Laporan Ekstrakurikuler','desc'=>'Cetak laporan kegiatan dan rekap kehadiran ekstrakurikuler.','url'=>'../ekstrakurikuler/laporan.php','roles'=>['admin','operator','guru']],
];

$visible = array_values(array_filter($items, static function(array $item) use ($can, $role): bool {
    return in_array($role, $item['roles'], true) && $can($item['key']);
}));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.lpc{max-width:1250px;margin:0 auto}.lpc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px}.lpc-head h1{margin:0 0 7px;font-size:30px;color:#14243d}.lpc-head p{margin:0;color:#64748b}.lpc-badge{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border-radius:999px;background:#eaf3ff;color:#176fe5;font-size:13px;font-weight:700;white-space:nowrap}.lpc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.lpc-card{background:#fff;border:1px solid #dce6f0;border-radius:18px;padding:22px;box-shadow:0 8px 25px rgba(15,40,70,.06);display:flex;flex-direction:column;min-height:220px}.lpc-icon{width:52px;height:52px;border-radius:15px;background:#edf5ff;display:flex;align-items:center;justify-content:center;font-size:27px;margin-bottom:16px}.lpc-card h2{margin:0 0 9px;font-size:18px;color:#172b49}.lpc-card p{margin:0;color:#64748b;line-height:1.55;font-size:13px;flex:1}.lpc-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;margin-top:18px;padding:11px 15px;border-radius:10px;background:#176fe5;color:#fff;text-decoration:none;font-weight:700;font-size:13px}.lpc-btn:hover{background:#0d5dcc}.lpc-note{margin-top:22px;padding:16px 18px;border-radius:14px;background:#f8fafc;border:1px solid #e4ebf3;color:#64748b;font-size:13px;line-height:1.55}@media(max-width:1050px){.lpc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.lpc-head{display:block}.lpc-badge{margin-top:14px}.lpc-grid{grid-template-columns:1fr}.lpc-card{min-height:190px}}@media print{.sidebar,.no-print{display:none!important}.main-content{margin:0!important;padding:0!important}.lpc{max-width:none}.lpc-grid{grid-template-columns:repeat(2,1fr)}.lpc-card{box-shadow:none;break-inside:avoid}}
</style>
<main class="main-content">
<div class="lpc">
<div class="lpc-head"><div><h1>🖨️ Laporan &amp; Cetak</h1><p>Semua laporan dan fasilitas cetak dikumpulkan di satu tempat.</p></div><div class="lpc-badge">SDN CIBURAYUT 01</div></div>
<div class="lpc-grid">
<?php foreach ($visible as $item): ?>
<div class="lpc-card"><div class="lpc-icon"><?=h($item['icon'])?></div><h2><?=h($item['title'])?></h2><p><?=h($item['desc'])?></p><a class="lpc-btn" href="<?=h($item['url'])?>">Buka &amp; Cetak →</a></div>
<?php endforeach; ?>
</div>
<div class="lpc-note"><strong>Catatan:</strong> tombol di atas membuka modul cetak yang sudah ada. Pusat ini hanya menjadi pintu masuk untuk seluruh laporan dan cetak.</div>
</div>
</main>
</body>
</html>