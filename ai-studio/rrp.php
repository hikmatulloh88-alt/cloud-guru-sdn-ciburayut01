<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) || !empty($_SESSION['user']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) { $next = rawurlencode('/ai-studio/rrp.php'); header('Location: /auth/login.php?next=' . $next); exit; }
require_once dirname(__DIR__) . '/includes/sidebar.php';
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>AI Studio — Pembuat RRP</title><link rel="stylesheet" href="/assets/css/glass-ui.css?v=4"><style>:root{--ink:#0f172a;--muted:#64748b;--blue:#1d4ed8;}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f0f5fc;color:var(--ink);font:15px/1.55 system-ui,sans-serif}.cg-main-content{min-height:100vh;padding:30px 38px 64px}.studio-shell{max-width:1120px;margin:0 auto}.studio-hero{padding:30px 36px;border-radius:24px;background:linear-gradient(120deg,#091e42 0%,#143d82 54%,#2563eb 100%);color:#fff;margin-bottom:22px}.studio-badge{display:inline-flex;gap:6px;padding:5px 11px;margin-bottom:10px;border-radius:999px;background:rgba(255,255,255,.15);font-size:11px;font-weight:800;color:#e0e7ff}.studio-hero h1{margin:0;font-size:clamp(25px,3.6vw,36px);font-weight:800}.studio-hero p{margin:8px 0 0;color:#dbeafe;font-size:14px;max-width:580px}.studio-grid{display:grid;grid-template-columns:1fr 1.15fr;gap:20px;align-items:start}.card{border:1px solid #e2e8f0;border-radius:22px;padding:24px;background:#fff;box-shadow:0 14px 36px rgba(15,35,68,.06)}.card h2{margin:0 0 4px;font-size:17.5px;font-weight:800}.hint{color:var(--muted);font-size:12.5px;margin:0}.field{display:flex;flex-direction:column;gap:7px;margin-top:14px}label{font-size:13px;font-weight:700;color:#1e293b}input,select,textarea{width:100%;background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:11px 13px;font:inherit;font-size:13.5px}input:focus,select:focus,textarea:focus{outline:none;border-color:#2563eb}textarea{min-height:110px;resize:vertical}.presets{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}.preset-btn{border:1px solid #dbeafe;background:#eff6ff;color:#1e40af;border-radius:999px;padding:6px 11px;font-size:11.5px;font-weight:700;cursor:pointer}.preset-btn:hover{background:#dbeafe}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.primary-btn,.btn{border:0;border-radius:13px;background:linear-gradient(115deg,#1d4ed8,#2563eb);color:#fff;padding:12px 20px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}.primary-btn:hover,.btn:hover{transform:translateY(-1px)}.primary-btn:disabled{opacity:.6;cursor:wait}.outline-btn{border:1px solid #cbd5e1;background:#fff;color:#1e293b}.outline-btn:hover{border-color:#2563eb}.rrp-preview{border:1px solid #e2e8f0;border-radius:16px;background:#fff;padding:24px;min-height:420px;overflow:auto;font-size:13.5px;line-height:1.6}.rrp-preview h2{font-size:16px;border-bottom:2px solid #1e3a8a;padding-bottom:6px;margin-top:18px;color:#1e3a8a}.rrp-preview h3{font-size:14px;margin-top:14px;color:#334155}.rrp-preview table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12.5px}.rrp-preview th,.rrp-preview td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left}.rrp-preview th{background:#f1f5f9;color:#475569;font-weight:700}.rrp-preview ul{margin:6px 0 6px 18px;padding:0}.rrp-preview li{margin:3px 0}.status-panel{margin-top:14px;padding:14px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}.status-text{font-size:13px;color:#334155;font-weight:600}.result-area{margin-top:14px;display:none}.empty-state{padding:28px;text-align:center;color:#94a3b8}.empty-state b{display:block;color:#e2e8f0;font-size:14px;margin-top:8px}@media(max-width:900px){.cg-main-content{padding:22px 18px 56px}.studio-grid{grid-template-columns:1fr}}</style></head><body class="ai-studio-page"><main class="cg-main-content"><div class="studio-shell"><header class="studio-hero"><div class="studio-badge">✦ AI STUDIO — RPP / RRP</div><h1>Pembuat Rencana Pelaksanaan Pembelajaran</h1><p>Hasilkan RRP lengkap untuk kurikulum sekolah dasar dalam hitungan detik. Hasil dapat diunduh dan dicetak langsung.</p></header><div class="studio-grid"><section class="card"><h2>1. Isi Data RPP</h2><p class="hint">Semua field wajib diisi kecuali yang ditandai opsional.</p><form id="rrpForm" novalidate><div class="field"><label for="sekolah">Nama Sekolah</label><input id="sekolah" name="sekolah" value="SDN Ciburayut 01" required></div><div class="field"><label for="nama_guru">Nama Guru</label><input id="nama_guru" name="nama_guru" placeholder="Nama lengkap guru"></div><div class="field"><label for="nip">NIP</label><input id="nip" name="nip" placeholder="Nomor Induk Pegawai"></div><div class="field"><label for="mata_pelajaran">Mata Pelajaran</label><input id="mata_pelajaran" name="mata_pelajaran" placeholder="Contoh: IPA, Matematika, Bahasa Indonesia" required></div><div class="field"><label for="kelas">Kelas</label><select id="kelas" name="kelas" required><option value="">Pilih kelas</option><option>Kelas 1</option><option>Kelas 2</option><option>Kelas 3</option><option>Kelas 4</option><option>Kelas 5</option><option>Kelas 6</option></select></div><div class="field"><label for="semester">Semester</label><select id="semester" name="semester" required><option value="">Pilih semester</option><option>Ganjil</option><option>Genap</option></select></div><div class="field"><label for="tema">Tema</label><input id="tema" name="tema" placeholder="Contoh: Hidup Berbhineka" required></div><div class="field"><label for="sub_tema">Sub Tema (opsional)</label><input id="sub_tema" name="sub_tema" placeholder="Contoh: Keindahan Budaya di Daerah Saya"></div><div class="field"><label for="materi">Materi Pokok</label><textarea id="materi" name="materi" placeholder="Contoh: Mengenal suku bangsa dan budaya di Indonesia" required></textarea></div><div class="field"><label for="alokasi_waktu">Alokasi Waktu</label><input id="alokasi_waktu" name="alokasi_waktu" value="2 x 40 menit" placeholder="Contoh: 2 x 40 menit"></div><div class="field"><label for="metode">Metode Pembelajaran (opsional)</label><input id="metode" name="metode" placeholder="Contoh: Diskusi, Tanya Jawab, Demonstrasi"><div class="presets"><button type="button" class="preset-btn" data-val="Diskusi dan Tanya Jawab">💬 Diskusi</button><button type="button" class="preset-btn" data-val="Demonstrasi dan Praktik">🎯 Demonstrasi</button><button type="button" class="preset-btn" data-val="Pembelajaran Berbasis Proyek">🛠️ PBL</button></div></div><div class="field"><label for="model_pembelajaran">Model Pembelajaran (opsional)</label><input id="model_pembelajaran" name="model_pembelajaran" placeholder="Contoh: Problem Based Learning"><div class="presets"><button type="button" class="preset-btn" data-val="Problem Based Learning">🧩 PBL</button><button type="button" class="preset-btn" data-val="Discovery Learning">🔍 Discovery</button><button type="button" class="preset-btn" data-val="Cooperative Learning">🤝 Kooperatif</button></div></div><div class="field"><label for="sumber_belajar">Sumber Belajar (opsional)</label><input id="sumber_belajar" name="sumber_belajar" placeholder="Contoh: Buku paket, LKS, internet"></div><div class="actions"><button id="generateRrp" class="primary-btn" type="button">✦ Generate RRP</button><button type="reset" class="btn outline-btn">🔄 Reset</button></div></form><div id="rrpStatus" class="status-panel" style="margin-top:14px;"><div id="rrpStatusText" class="status-text">Siap digunakan. Isi data di atas lalu klik Generate RRP.</div></div></section>
    <section class="card">
      <h2>2. Hasil RPP</h2>
      <p class="hint">Pratinjau RPP akan muncul di sini setelah proses generate selesai.</p>

      <div id="rrpEmpty" class="empty-state">
        <span style="font-size:34px" aria-hidden="true">📄</span>
        <b>Belum ada RPP yang dibuat</b>
        <small>Isi formulir sebelah kiri dan tekan Generate RRP.</small>
      </div>

      <div id="rrpResult" class="result-area rrp-preview"></div>

      <div id="rrpActions" class="actions hidden" style="margin-top:14px;">
        <button id="downloadRrp" class="btn">⬇ Unduh .DOC</button>
        <button id="printRrp" class="btn outline-btn">🖨 Cetak / Simpan PDF</button>
        <button id="newRrp" class="btn outline-btn">📝 Buat Lagi</button>
      </div>
    </section>
  </div>
</div>
</main>

<script>
const form = document.getElementById('rrpForm');
const generateBtn = document.getElementById('generateRrp');
const statusText = document.getElementById('rrpStatusText');
const rrpEmpty = document.getElementById('rrpEmpty');
const rrpResult = document.getElementById('rrpResult');
const rrpActions = document.getElementById('rrpActions');
const downloadBtn = document.getElementById('downloadRrp');
const printBtn = document.getElementById('printRrp');
const newRrpBtn = document.getElementById('newRrp');

let currentRrpHtml = '';

document.querySelectorAll('.preset-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.closest('.field').querySelector('input, textarea');
    if (target) target.value = btn.getAttribute('data-val') || '';
  });
});

generateBtn.addEventListener('click', async () => {
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());

  if (!data.mata_pelajaran || !data.kelas || !data.semester || !data.tema || !data.materi) {
    statusText.textContent = '⚠ Mata Pelajaran, Kelas, Semester, Tema, dan Materi wajib diisi.';
    return;
  }

  generateBtn.disabled = true;
  generateBtn.textContent = '⏳ Sedang memproses...';
  statusText.textContent = 'Sedang menyusun RRP...';
  rrpActions.classList.add('hidden');

  try {
    const r = await fetch('generate_rrp.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(data)
    });
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || ('HTTP ' + r.status));
    if (!d.html) throw new Error('Hasil RRP tidak diterima dari server.');

    currentRrpHtml = d.html;
    rrpResult.innerHTML = d.html;
    rrpEmpty.style.display = 'none';
    rrpResult.style.display = 'block';
    rrpActions.classList.remove('hidden');
    statusText.textContent = '✓ RRP berhasil dibuat. Silakan unduh atau cetak.';
  } catch (e) {
    statusText.textContent = '⚠ Gagal: ' + e.message;
  } finally {
    generateBtn.disabled = false;
    generateBtn.textContent = '✦ Generate RRP';
  }
});

downloadBtn.addEventListener('click', () => {
  if (!currentRrpHtml) return;
  const blob = new Blob([currentRrpHtml], {type: 'application/msword'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'RPP_' + (document.getElementById('mata_pelajaran').value || 'MataPelajaran') + '_' + (document.getElementById('kelas').value || 'Kelas') + '.doc';
  a.click();
  URL.revokeObjectURL(url);
});

printBtn.addEventListener('click', () => {
  if (!currentRrpHtml) return;
  const win = window.open('', '_blank');
  win.document.write('<html><head><title>Cetak RPP</title><style>body{font-family:sans-serif;font-size:12px;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #000;padding:4px 6px;text-align:left;}h2{border-bottom:2px solid #000;padding-bottom:4px;}</style></head><body>' + currentRrpHtml + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); }, 500);
});

newRrpBtn.addEventListener('click', () => {
  form.reset();
  document.getElementById('sekolah').value = 'SDN Ciburayut 01';
  rrpResult.style.display = 'none';
  rrpEmpty.style.display = 'block';
  rrpActions.classList.add('hidden');
  statusText.textContent = 'Siap digunakan. Isi data di atas lalu klik Generate RRP.';
  currentRrpHtml = '';
});
</script>
</body></html>
