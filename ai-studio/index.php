<?php
declare(strict_types=1);
/**
 * Cloud Guru AI Studio - isolated module.
 * Login guard accepts common Cloud Guru session keys; align to the deployed login if its key differs.
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) ||
            !empty($_SESSION['user']) || !empty($_SESSION['username']) ||
            !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) {
    $next = rawurlencode('/ai-studio/');
    header('Location: /auth/login.php?next=' . $next);
    exit;
}

require_once dirname(__DIR__) . '/includes/sidebar.php';
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Studio — Cloud Guru</title>
<link rel="stylesheet" href="/assets/css/glass-ui.css?v=4">
<style>
:root{
  color-scheme:light;
  --ink:#0f172a;
  --muted:#64748b;
  --line:#e2e8f0;
  --blue:#1d4ed8;
}
*{box-sizing:border-box}
body{
  margin:0;min-height:100vh;
  background:
    radial-gradient(ellipse at 12% 0%,rgba(56,189,248,.14),transparent 38%),
    radial-gradient(ellipse at 90% 80%,rgba(99,102,241,.1),transparent 36%),
    #f0f5fc;
  color:var(--ink);
  font:15px/1.55 var(--cg-font,'Plus Jakarta Sans',system-ui,-apple-system,sans-serif);
}
.cg-main-content{min-height:100vh;padding:30px 38px 64px}
.studio-shell{max-width:1120px;margin:0 auto}

.studio-hero{
  position:relative;overflow:hidden;padding:30px 36px;border-radius:24px;
  background:
    radial-gradient(circle at 88% 20%,rgba(129,140,248,.38),transparent 38%),
    linear-gradient(120deg,#091e42 0%,#143d82 54%,#2563eb 100%);
  color:#fff;box-shadow:0 20px 46px rgba(15,35,80,.18);margin-bottom:22px;
}
.studio-badge{
  display:inline-flex;align-items:center;gap:6px;padding:5px 11px;margin-bottom:10px;
  border-radius:999px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.24);
  font-size:11px;font-weight:800;letter-spacing:.08em;color:#e0e7ff;
}
.studio-hero h1{margin:0;font-size:clamp(25px,3.6vw,36px);font-weight:800;letter-spacing:-.03em}
.studio-hero p{margin:8px 0 0;color:#dbeafe;font-size:14px;max-width:580px}

.studio-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:20px;align-items:start}
.card{
  border:1px solid rgba(255,255,255,.92);border-radius:22px;padding:24px;
  background:rgba(255,255,255,.88);box-shadow:0 14px 36px rgba(15,35,68,.07);
  backdrop-filter:blur(16px);
}
.card h2{margin:0 0 4px;font-size:17.5px;font-weight:800;letter-spacing:-.02em}
.hint{color:var(--muted);font-size:12.5px;margin:0}

/* Photo Dropzone */
.photo-drop{
  position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;margin-top:14px;padding:24px 16px;border:2px dashed #93c5fd;border-radius:16px;
  background:linear-gradient(145deg,#f8fbff,#eff6ff);cursor:pointer;transition:all .2s ease;
}
.photo-drop:hover,.photo-drop.is-dragover{border-color:#2563eb;background:#e0f2fe}
.photo-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.photo-drop strong{font-size:14px;color:#0f172a;margin-top:8px}
.photo-drop span{font-size:12px;color:var(--muted);margin-top:2px}

.field{display:flex;flex-direction:column;gap:7px;margin-top:16px}
label{font-size:13px;font-weight:700;color:#1e293b}
textarea,select{
  width:100%;background:#fff;color:var(--ink);border:1px solid #cbd5e1;
  border-radius:12px;padding:11px 13px;font:inherit;font-size:13.5px;transition:border-color .18s ease;
}
textarea:focus,select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
textarea{min-height:104px;resize:vertical;line-height:1.55}

/* Prompt Presets */
.presets{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.preset-btn{
  border:1px solid #dbeafe;background:#eff6ff;color:#1e40af;border-radius:999px;
  padding:6px 11px;font:inherit;font-size:11.5px;font-weight:700;cursor:pointer;transition:.16s ease;
}
.preset-btn:hover{background:#dbeafe;border-color:#93c5fd}

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.primary-btn,.btn{
  border:0;border-radius:13px;background:linear-gradient(115deg,#1d4ed8,#2563eb);
  color:#fff;padding:12px 20px;font-weight:700;font:inherit;font-size:14px;cursor:pointer;
  text-decoration:none;display:inline-flex;justify-content:center;align-items:center;gap:8px;
  box-shadow:0 10px 24px rgba(29,78,216,.24);transition:all .18s ease;
}
.primary-btn:hover,.btn:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(29,78,216,.3)}
.primary-btn:disabled{opacity:.6;cursor:wait;transform:none}

/* Media Stage */
.stage-box{
  margin-top:14px;border-radius:16px;background:#0b132b;min-height:280px;
  display:grid;place-items:center;overflow:hidden;position:relative;border:1px solid #1e293b;
}
.stage-placeholder{padding:28px;text-align:center;color:#94a3b8}
.stage-placeholder b{display:block;color:#e2e8f0;font-size:14px;margin-top:8px}
.stage-placeholder small{display:block;font-size:12px;margin-top:4px}
.preview{width:100%;max-height:420px;object-fit:contain;display:block}
.hidden{display:none!important}

/* Status & Progress */
.status-panel{margin-top:14px;padding:14px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}
.status-text{font-size:13px;color:#334155;font-weight:600;overflow-wrap:anywhere}
.progress-track{height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:10px;display:none}
.progress-fill{height:100%;width:15%;background:linear-gradient(90deg,#2563eb,#38bdf8);border-radius:999px;transition:width .4s ease}

@media(max-width:900px){
  .cg-main-content{padding:22px 18px 56px}
  .studio-grid{grid-template-columns:1fr}
  .studio-hero{padding:24px 22px}
}
</style>
</head>
<body class="ai-studio-page">
<main class="cg-main-content">
<div class="studio-shell">
  <header class="studio-hero">
    <div class="studio-badge">✦ STUDIO MEDIA AI</div>
    <h1>AI Studio — Foto ke Video</h1>
    <p>Hidupkan foto kegiatan sekolah atau ilustrasi materi ajar menjadi video realistis berdurasi 5 hingga 10 detik secara otomatis.</p>
  </header>

  <div class="studio-grid">
    <section class="card">
      <h2>1. Pengaturan Video</h2>
      <p class="hint">Unggah foto JPG, PNG, atau WebP (maksimal 12 MB) lalu pilih gaya gerakan.</p>

      <div class="photo-drop" id="photoDrop">
        <input id="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Pilih foto">
        <span style="font-size:28px" aria-hidden="true">🖼️</span>
        <strong id="photoLabel">Seret foto ke sini atau klik untuk memilih</strong>
        <span>Resolusi disarankan minimal 720p agar hasil video tajam</span>
      </div>

      <div class="field">
        <label for="duration">Durasi Video</label>
        <select id="duration">
          <option value="5">5 detik (Cepat &amp; hemat kredit)</option>
          <option value="10">10 detik (Gerakan lebih panjang)</option>
        </select>
      </div>

      <div class="field">
        <label for="prompt">Arahan Gerakan &amp; Suasana</label>
        <div class="presets" aria-label="Contoh instruksi cepat">
          <button type="button" class="preset-btn" data-prompt="Pertahankan identitas dan bentuk wajah, senyum ramah dan sapaan hangat yang natural, gerakan halus, latar tetap konsisten.">😊 Sapaan Ramah</button>
          <button type="button" class="preset-btn" data-prompt="Suasana kegiatan belajar yang hidup, gerakan alami dan halus, pencahayaan cerah ruang kelas, kamera bergerak perlahan mendekat.">🏫 Kegiatan Kelas</button>
          <button type="button" class="preset-btn" data-prompt="Gerakan sinematik yang sangat halus dan stabil, hembusan angin ringan pada latar, pertahankan detail objek utama tanpa distorsi.">🎥 Kamera Sinematik</button>
        </div>
        <textarea id="prompt" maxlength="2000" placeholder="Tulis bebas gerakan, ekspresi, suasana, atau arahan kamera yang Anda inginkan...">Pertahankan identitas dan bentuk wajah, gerakan realistis dan halus, latar tetap konsisten, hindari distorsi dan perubahan karakter.</textarea>
      </div>

      <div class="actions">
        <button id="make" class="primary-btn" type="button">✦ Buat Video AI</button>
      </div>
    </section>

    <section class="card">
      <h2>2. Pratinjau &amp; Hasil Video</h2>
      <p class="hint">Foto yang dipilih dan hasil render video MP4 akan tampil di layar ini.</p>

      <div class="stage-box">
        <div id="stageEmpty" class="stage-placeholder">
          <span style="font-size:34px" aria-hidden="true">🎬</span>
          <b>Belum ada foto dipilih</b>
          <small>Pilih foto di panel sebelah kiri untuk melihat pratinjau</small>
        </div>
        <img id="preview" class="preview hidden" alt="Pratinjau foto">
        <video id="result" class="preview hidden" controls playsinline></video>
      </div>

      <div class="status-panel">
        <div id="status" class="status-text" role="status">Siap digunakan. Pilih foto lalu klik "Buat Video AI".</div>
        <div id="progressTrack" class="progress-track"><div id="progressFill" class="progress-fill"></div></div>
      </div>

      <div id="resultActions" class="actions hidden">
        <a id="download" class="btn" download="cloud-guru-ai-video.mp4">⬇ Simpan Video MP4</a>
      </div>
    </section>
  </div>
</div>
</main>

<script>
const photo = document.getElementById('photo'),
      photoDrop = document.getElementById('photoDrop'),
      photoLabel = document.getElementById('photoLabel'),
      stageEmpty = document.getElementById('stageEmpty'),
      preview = document.getElementById('preview'),
      resultVideo = document.getElementById('result'),
      resultActions = document.getElementById('resultActions'),
      make = document.getElementById('make'),
      statusBox = document.getElementById('status'),
      progressTrack = document.getElementById('progressTrack'),
      progressFill = document.getElementById('progressFill'),
      promptBox = document.getElementById('prompt');

document.querySelectorAll('.preset-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    promptBox.value = btn.getAttribute('data-prompt') || '';
    promptBox.focus();
  });
});

['dragenter','dragover'].forEach(evt => {
  photoDrop.addEventListener(evt, e => { e.preventDefault(); photoDrop.classList.add('is-dragover'); });
});
['dragleave','drop'].forEach(evt => {
  photoDrop.addEventListener(evt, () => photoDrop.classList.remove('is-dragover'));
});

photo.addEventListener('change', () => {
  const f = photo.files[0];
  if (!f) {
    preview.classList.add('hidden');
    resultVideo.classList.add('hidden');
    stageEmpty.classList.remove('hidden');
    photoLabel.textContent = 'Seret foto ke sini atau klik untuk memilih';
    return;
  }
  photoLabel.textContent = f.name + ' (' + (f.size / (1024 * 1024)).toFixed(2) + ' MB)';
  preview.src = URL.createObjectURL(f);
  preview.classList.remove('hidden');
  resultVideo.classList.add('hidden');
  resultActions.classList.add('hidden');
  stageEmpty.classList.add('hidden');
  statusBox.textContent = 'Foto siap diproses. Klik "Buat Video AI" untuk memulai.';
});

const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

async function preparePhoto(file){
  if (file.size <= 1.75 * 1024 * 1024) return file;
  statusBox.textContent = 'Mengoptimalkan ukuran foto agar sesuai batas upload server...';
  const img = new Image(), url = URL.createObjectURL(file);
  try {
    await new Promise((resolve, reject) => {
      img.onload = resolve;
      img.onerror = () => reject(new Error('Foto tidak dapat dibaca browser.'));
      img.src = url;
    });
    let w = img.naturalWidth, h = img.naturalHeight;
    if (Math.max(w, h) > 1800) {
      const s = 1800 / Math.max(w, h);
      w = Math.round(w * s);
      h = Math.round(h * s);
    }
    for (let pass = 0; pass < 6; pass++) {
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Browser tidak mendukung kompresi foto.');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, w, h);
      ctx.drawImage(img, 0, 0, w, h);
      let q = 0.84, blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', q));
      while (blob && blob.size > 1.75 * 1024 * 1024 && q > 0.5) {
        q -= 0.08;
        blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', q));
      }
      if (blob && blob.size <= 1.75 * 1024 * 1024) {
        return new File([blob], (file.name.replace(/\.[^.]+$/, '') || 'photo') + '.jpg', { type: 'image/jpeg' });
      }
      w = Math.round(w * 0.78);
      h = Math.round(h * 0.78);
    }
    throw new Error('Foto belum bisa diperkecil di bawah batas server. Coba foto lain.');
  } finally {
    URL.revokeObjectURL(url);
  }
}

make.addEventListener('click', async () => {
  const file = photo.files[0];
  if (!file) { statusBox.textContent = 'Pilih foto terlebih dahulu.'; return; }
  if (file.size > 12 * 1024 * 1024) { statusBox.textContent = 'Ukuran foto maksimal 12 MB.'; return; }

  make.disabled = true;
  resultActions.classList.add('hidden');
  progressTrack.style.display = 'block';
  progressFill.style.width = '12%';
  statusBox.textContent = 'Menyiapkan dan mengirim foto ke antrean AI...';

  try {
    const uploadFile = await preparePhoto(file);
    progressFill.style.width = '25%';
    const fd = new FormData();
    fd.append('image', uploadFile);
    fd.append('duration', document.getElementById('duration').value);
    fd.append('prompt', promptBox.value);

    const r = await fetch('generate.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || ('HTTP ' + r.status));
    if (!d.request_id) throw new Error('ID proses video tidak diterima.');

    progressFill.style.width = '38%';
    statusBox.textContent = 'Video masuk antrean AI. Sedang merender gerakan...';

    for (let attempt = 0; attempt < 90; attempt++) {
      await wait(4000);
      const pct = Math.min(94, 38 + Math.round((attempt / 45) * 55));
      progressFill.style.width = pct + '%';

      const sr = await fetch('status.php', { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
      const sd = await sr.json();
      if (!sr.ok) throw new Error(sd.error || ('Status HTTP ' + sr.status));

      if (sd.status === 'completed' && sd.video_url) {
        progressFill.style.width = '100%';
        preview.classList.add('hidden');
        stageEmpty.classList.add('hidden');
        resultVideo.src = sd.video_url;
        resultVideo.classList.remove('hidden');
        document.getElementById('download').href = sd.download_url || 'download.php';
        resultActions.classList.remove('hidden');
        statusBox.textContent = '✓ Video berhasil dibuat! Tekan Putar atau klik Simpan Video MP4.';
        return;
      }
      if (sd.status === 'failed' || sd.status === 'cancelled') {
        throw new Error(sd.error || 'Proses video gagal.');
      }
      statusBox.textContent = 'Sedang membuat video AI... (' + (attempt * 4 + 4) + ' detik)';
    }
    throw new Error('Pembuatan video berlangsung lebih lama dari batas tunggu halaman. Coba periksa beberapa saat lagi.');
  } catch (e) {
    progressTrack.style.display = 'none';
    statusBox.textContent = '⚠ Belum berhasil: ' + e.message;
  } finally {
    make.disabled = false;
  }
});
</script>
</body>
</html>
