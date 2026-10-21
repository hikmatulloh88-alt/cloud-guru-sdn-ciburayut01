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
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Studio — Cloud Guru</title>
<style>
:root{color-scheme:dark;--bg:#0b1020;--card:#141c32;--line:#2b3655;--muted:#aab5d1;--text:#f4f6ff;--accent:#8ea2ff}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at top right,#1b2b58 0,transparent 48%),var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}
main{width:min(980px,100% - 28px);margin:28px auto}.top{display:flex;gap:14px;align-items:center;margin-bottom:22px}.logo{width:50px;height:50px;border-radius:16px;background:#28345e;display:grid;place-items:center;font-size:25px}.sub{color:var(--muted);margin:3px 0 0}.card{background:rgba(20,28,50,.94);border:1px solid var(--line);border-radius:22px;padding:22px;margin-bottom:16px;box-shadow:0 18px 50px #0002}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field{display:flex;flex-direction:column;gap:8px;margin:14px 0}label{font-weight:650}input,textarea,select{width:100%;background:#0d1428;color:var(--text);border:1px solid #384568;border-radius:12px;padding:13px;font:inherit}textarea{min-height:110px;resize:vertical}button,.btn{border:0;border-radius:12px;background:var(--accent);color:#10152b;padding:13px 18px;font-weight:800;font:inherit;cursor:pointer;text-decoration:none;display:inline-flex;justify-content:center;align-items:center}button:disabled{opacity:.55;cursor:wait}.actions{display:flex;gap:10px;flex-wrap:wrap}.preview{width:100%;max-height:420px;object-fit:contain;border-radius:14px;background:#080c18}.status{padding:12px;border-radius:12px;background:#0c1428;color:var(--muted);margin-top:12px;overflow-wrap:anywhere}.hint{color:var(--muted);font-size:13px}.hidden{display:none}h1{font-size:clamp(25px,4vw,34px);margin:0}h2{font-size:19px;margin:0 0 8px}@media(max-width:700px){.grid{grid-template-columns:1fr}.card{padding:16px}main{margin:16px auto}}
</style>
</head>
<body><main>
<header class="top"><div class="logo">✦</div><div><h1>AI Studio</h1><p class="sub">Cloud Guru · Foto menjadi video realistis 5–10 detik</p></div></header>
<section class="card">
<h2>1. Pilih foto</h2><p class="hint">Gunakan foto yang Anda berhak gunakan. Format JPG, PNG, atau WebP; maksimal 12 MB.</p>
<div class="field"><input id="photo" type="file" accept="image/jpeg,image/png,image/webp"></div>
<img id="preview" class="preview hidden" alt="Pratinjau foto">
<div class="grid">
<div class="field"><label for="duration">Durasi</label><select id="duration"><option value="5">5 detik</option><option value="10">10 detik</option></select></div>
<div class="field"><label for="motion">Gaya gerakan</label><select id="motion"><option value="Gerakan natural, realistis, sinematik lembut">Natural sinematik</option><option value="Subjek tersenyum lembut lalu menoleh ke kamera, gerakan wajah alami">Senyum dan menoleh</option><option value="Gerakan kamera perlahan mendekat, rambut dan pakaian bergerak lembut tertiup angin">Kamera mendekat</option><option value="Subjek melambaikan tangan secara alami, menjaga wajah dan identitas tetap konsisten">Melambaikan tangan</option></select></div>
</div>
<div class="field"><label for="prompt">Instruksi tambahan</label><textarea id="prompt" maxlength="1500" placeholder="Contoh: latar tetap sama, ekspresi ramah, gerakan tidak berlebihan.">Pertahankan identitas dan bentuk wajah, gerakan realistis dan halus, latar tetap konsisten, hindari distorsi dan perubahan karakter.</textarea></div>
<div class="actions"><button id="make">✦ Buat Video AI</button><a class="btn" href="/" style="background:#263352;color:#fff">Kembali ke Cloud Guru</a></div>
<div id="status" class="status" role="status">Pilih foto, lalu buat video. Pembuatan video memakai API AI dan dapat dikenai biaya.</div>
</section>
<section id="resultCard" class="card hidden"><h2>2. Hasil video</h2><video id="result" class="preview" controls playsinline></video><p><a id="download" class="btn" download="cloud-guru-ai-video.mp4">Simpan MP4</a></p></section>
</main>
<script>
const photo=document.getElementById('photo'),preview=document.getElementById('preview'),make=document.getElementById('make'),statusBox=document.getElementById('status');
photo.addEventListener('change',()=>{const f=photo.files[0];if(!f){preview.classList.add('hidden');return}preview.src=URL.createObjectURL(f);preview.classList.remove('hidden')});
make.addEventListener('click',async()=>{const file=photo.files[0];if(!file){statusBox.textContent='Pilih foto terlebih dahulu.';return}if(file.size>12*1024*1024){statusBox.textContent='Ukuran foto maksimal 12 MB.';return}
make.disabled=true;statusBox.textContent='Mengirim foto ke layanan AI. Proses dapat memerlukan beberapa menit.';
try{const fd=new FormData();fd.append('image',file);fd.append('duration',document.getElementById('duration').value);fd.append('motion',document.getElementById('motion').value);fd.append('prompt',document.getElementById('prompt').value);
const r=await fetch('generate.php',{method:'POST',body:fd,credentials:'same-origin'});const d=await r.json();if(!r.ok)throw new Error(d.error||('HTTP '+r.status));if(!d.video_url)throw new Error('URL video tidak ditemukan dalam respons.');
const video=document.getElementById('result');video.src=d.video_url;document.getElementById('download').href=d.video_url;document.getElementById('resultCard').classList.remove('hidden');statusBox.textContent='Video berhasil dibuat. Tekan putar atau Simpan MP4.';
}catch(e){statusBox.textContent='Belum berhasil: '+e.message+'. Pastikan API key fal.ai sudah dikonfigurasi di server.'}finally{make.disabled=false}});
</script></body></html>
