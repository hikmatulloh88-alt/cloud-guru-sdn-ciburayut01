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
<link rel="stylesheet" href="/assets/css/glass-ui.css?v=1">
</head>
<body class="ai-studio-page"><main>
<header class="top"><div class="logo">✦</div><div><h1>AI Studio</h1><p class="sub">Cloud Guru · Foto menjadi video realistis 5–10 detik</p></div></header>
<section class="card">
<h2>1. Pilih foto</h2><p class="hint">Gunakan foto yang Anda berhak gunakan. Format JPG, PNG, atau WebP; maksimal 12 MB.</p>
<div class="field"><input id="photo" type="file" accept="image/jpeg,image/png,image/webp"></div>
<img id="preview" class="preview hidden" alt="Pratinjau foto">
<div class="field"><label for="duration">Durasi video</label><select id="duration"><option value="5">5 detik</option><option value="10">10 detik</option></select></div>
<div class="field"><label for="prompt">Instruksi bebas</label><textarea id="prompt" maxlength="2000" placeholder="Tulis bebas gerakan, ekspresi, suasana, atau arahan kamera yang kamu inginkan...">Pertahankan identitas dan bentuk wajah, gerakan realistis dan halus, latar tetap konsisten, hindari distorsi dan perubahan karakter.</textarea><p class="hint">Instruksi bebas—tidak dibatasi pilihan gerakan.</p></div>
<div class="actions"><button id="make">✦ Buat Video AI</button><a class="btn" href="/" style="background:#263352;color:#fff">Kembali ke Cloud Guru</a></div>
<div id="status" class="status" role="status">Pilih foto, lalu buat video. Pembuatan video memakai API AI dan dapat dikenai biaya.</div>
</section>
<section id="resultCard" class="card hidden"><h2>2. Hasil video</h2><video id="result" class="preview" controls playsinline></video><p><a id="download" class="btn" download="cloud-guru-ai-video.mp4">Simpan MP4</a></p></section>
</main>
<script>
const photo=document.getElementById('photo'),preview=document.getElementById('preview'),make=document.getElementById('make'),statusBox=document.getElementById('status');
photo.addEventListener('change',()=>{const f=photo.files[0];if(!f){preview.classList.add('hidden');return}preview.src=URL.createObjectURL(f);preview.classList.remove('hidden')});
const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function preparePhoto(file){
  if(file.size<=1.75*1024*1024)return file;
  statusBox.textContent='Mengoptimalkan ukuran foto untuk batas upload server...';
  const img=new Image(),url=URL.createObjectURL(file);
  try{
    await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=()=>reject(new Error('Foto tidak dapat dibaca browser.'));img.src=url;});
    let w=img.naturalWidth,h=img.naturalHeight;
    if(Math.max(w,h)>1800){const s=1800/Math.max(w,h);w=Math.round(w*s);h=Math.round(h*s);}
    for(let pass=0;pass<6;pass++){
      const canvas=document.createElement('canvas');canvas.width=w;canvas.height=h;
      const ctx=canvas.getContext('2d');if(!ctx)throw new Error('Browser tidak mendukung kompresi foto.');
      ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);ctx.drawImage(img,0,0,w,h);
      let q=0.84,blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',q));
      while(blob&&blob.size>1.75*1024*1024&&q>0.5){q-=0.08;blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',q));}
      if(blob&&blob.size<=1.75*1024*1024)return new File([blob],(file.name.replace(/\\.[^.]+$/,'')||'photo')+'.jpg',{type:'image/jpeg'});
      w=Math.round(w*0.78);h=Math.round(h*0.78);
    }
    throw new Error('Foto belum bisa diperkecil di bawah batas server. Coba foto lain.');
  }finally{URL.revokeObjectURL(url);}
}
make.addEventListener('click',async()=>{const file=photo.files[0];if(!file){statusBox.textContent='Pilih foto terlebih dahulu.';return}if(file.size>12*1024*1024){statusBox.textContent='Ukuran foto maksimal 12 MB.';return}
make.disabled=true;document.getElementById('resultCard').classList.add('hidden');statusBox.textContent='Mengirim foto ke antrean AI...';
try{const uploadFile=await preparePhoto(file);const fd=new FormData();fd.append('image',uploadFile);fd.append('duration',document.getElementById('duration').value);fd.append('prompt',document.getElementById('prompt').value);
const r=await fetch('generate.php',{method:'POST',body:fd,credentials:'same-origin'});const d=await r.json();if(!r.ok)throw new Error(d.error||('HTTP '+r.status));if(!d.request_id)throw new Error('ID proses video tidak diterima.');
statusBox.textContent='Video masuk antrean. Sedang dibuat...';
for(let attempt=0;attempt<90;attempt++){await wait(4000);const sr=await fetch('status.php',{method:'GET',credentials:'same-origin',cache:'no-store'});const sd=await sr.json();if(!sr.ok)throw new Error(sd.error||('Status HTTP '+sr.status));
if(sd.status==='completed'&&sd.video_url){const video=document.getElementById('result');video.src=sd.video_url;document.getElementById('download').href=sd.download_url||'download.php';document.getElementById('resultCard').classList.remove('hidden');statusBox.textContent='Video berhasil dibuat. Tekan putar atau Simpan MP4.';return}
if(sd.status==='failed'||sd.status==='cancelled')throw new Error(sd.error||'Proses video gagal.');
statusBox.textContent='Sedang membuat video AI... Status: '+(sd.status||'menunggu')+'.';}
throw new Error('Pembuatan video masih berlangsung lebih lama dari batas tunggu halaman. Coba cek lagi beberapa saat kemudian.');
}catch(e){statusBox.textContent='Belum berhasil: '+e.message+'. Pastikan sesi login dan API key fal.ai sudah benar di server.'}finally{make.disabled=false}});
</script></body></html>
