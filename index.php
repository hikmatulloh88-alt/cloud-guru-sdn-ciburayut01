<?php
/* Cloud Guru Self-Healing: observability only; no business/data mutation. */
require_once __DIR__.'/self-healing/logger.php';
sh_install_error_handler();
sh_install_shutdown_handler();

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/school_stats.php';
$pageSlug=strtolower(trim((string)($_GET['page']??'home')));
if(!preg_match('/^[a-z0-9-]{1,120}$/',$pageSlug))$pageSlug='home';
$settings=['site_name'=>'SDN CIBURAYUT 01','tagline'=>'Portal Informasi Sekolah','logo_url'=>'','favicon_url'=>'','address'=>'Cigombong, Kabupaten Bogor','phone'=>'','email'=>'info@sdnciburayut01.com','whatsapp'=>'','facebook_url'=>'','instagram_url'=>'','youtube_url'=>'','maps_embed_url'=>'','maps_link'=>'','footer_text'=>'© '.date('Y').' SDN Ciburayut 01. Semua hak dilindungi.','primary_color'=>'#1565c0','seo_title'=>'','seo_description'=>'Portal resmi SDN Ciburayut 01. Informasi sekolah, berita, agenda, profil guru, galeri, dan layanan digital.','seo_keywords'=>'SDN Ciburayut 01, sekolah dasar, Ciburayut, Cigombong, Bogor, portal sekolah','seo_author'=>'SDN Ciburayut 01','seo_og_image'=>'','seo_canonical'=>'','seo_robots'=>'index,follow'];
/* Homepage hero values must be whitelisted before reading website_builder_settings. */
$settings += [
    'hero_image' => '',
    'hero_eyebrow' => 'PORTAL INFORMASI SEKOLAH',
    'hero_title' => 'Sekolah Ramah Anak, Berkarakter, dan Berbudaya Digital',
    'hero_subtitle' => 'Portal resmi sekolah untuk informasi, kegiatan, profil guru, layanan pendidikan, dan berbagai informasi terkini.',
    'hero_button_text' => 'Jelajahi Portal',
    'hero_button_url' => '/?page=informasi',
    'hero_button2_text' => 'Profil Sekolah',
    'hero_button2_url' => '/?page=profil',
    'footer_quicklinks' => implode("\n", ['Beranda|/?page=home','Profil Sekolah|/?page=profil','Informasi|/?page=informasi','Profil Guru|/?page=guru','Galeri|/?page=galeri']),
    'footer_bg' => '#102a43',
    'footer_layout' => '3',
    'footer_show_contact' => '1',
    'footer_show_logo' => '1',
    'footer_show_quicklinks' => '1',
    'footer_show_social' => '1',
    'footer_text_color' => '#ffffff',
];
try{$q=$pdo->query("SELECT setting_key,setting_value FROM website_builder_settings");foreach($q as $r)if(array_key_exists($r['setting_key'],$settings))$settings[$r['setting_key']]=$r['setting_value'];}catch(Throwable $e){}

/* FINAL CONTENT DEFAULTS: jangan biarkan footer kosong ketika database lama belum punya nilai. */
$defaultQuickLinks="Beranda|/?page=home\nProfil Sekolah|/?page=profil\nInformasi|/?page=informasi\nProfil Guru|/?page=guru\nGaleri|/?page=galeri";
if(trim((string)($settings['footer_quicklinks']??''))==='')$settings['footer_quicklinks']=$defaultQuickLinks;
if(trim((string)($settings['footer_text']??''))==='')$settings['footer_text']='© '.date('Y').' SDN Ciburayut 01. Semua hak dilindungi.';
$navbar=['navbar_enabled'=>true,'navbar_items'=>[['label'=>'Beranda','url'=>'/?page=home'],['label'=>'Profil','url'=>'/?page=profil'],['label'=>'Informasi','url'=>'/?page=informasi'],['label'=>'Guru','url'=>'/?page=guru'],['label'=>'Galeri','url'=>'/?page=galeri']],'navbar_cta_text'=>'☁️ Masuk Cloud Guru','navbar_cta_url'=>'/auth/login.php','navbar_style'=>'glass'];
try{$q=$pdo->query("SELECT setting_key,setting_value FROM website_builder_settings WHERE setting_key LIKE 'navbar_%'");foreach($q as $r)if(array_key_exists($r['setting_key'],$navbar))$navbar[$r['setting_key']]=in_array($r['setting_key'],['navbar_items'])?(json_decode($r['setting_value'],true)?:$navbar[$r['setting_key']]):$r['setting_value'];}catch(Throwable $e){}
$navbar['navbar_enabled']=$navbar['navbar_enabled']==='1'||$navbar['navbar_enabled']===true;
// Final public navigation guard: never render dead # links.
$navFallback=['Beranda|/?page=home','Profil|/?page=profil','Informasi|/?page=informasi','Guru|/?page=guru','Galeri|/?page=galeri'];
$cleanNav=[]; foreach((array)$navbar['navbar_items'] as $it){$label=trim((string)($it['label']??''));$url=trim((string)($it['url']??'')); if($label===''||$url===''||$url==='#') continue; if(!preg_match('#^(https?://|/|\?)[^\s]{0,400}$#i',$url)) continue; $cleanNav[]=['label'=>$label,'url'=>$url];}
if(!$cleanNav){foreach($navFallback as $line){[$label,$url]=explode('|',$line,2);$cleanNav[]=['label'=>$label,'url'=>$url];}} $navbar['navbar_items']=$cleanNav;
if(trim((string)$navbar['navbar_cta_url'])===''||$navbar['navbar_cta_url']==='#')$navbar['navbar_cta_url']='/auth/login.php';
$header=['header_height'=>'78','header_sticky'=>'1','header_transparent'=>'0','header_bg'=>'#ffffff','header_overlay'=>'0','header_logo_width'=>'46','header_align'=>'between'];
try{$q=$pdo->query("SELECT setting_key,setting_value FROM website_builder_settings WHERE setting_key LIKE 'header_%'");foreach($q as $r)if(array_key_exists($r['setting_key'],$header))$header[$r['setting_key']]=$r['setting_value'];}catch(Throwable $e){}
$header['header_sticky']=$header['header_sticky']==='1';$header['header_transparent']=$header['header_transparent']==='1';$header['header_overlay']=$header['header_overlay']==='1';
$published=null;
try{$q=$pdo->prepare("SELECT content FROM website_builder_published WHERE page_slug=? LIMIT 1");$q->execute([$pageSlug]);$published=$q->fetchColumn();}catch(Throwable $e){}
$schoolGallery = [
  '/uploads/builder/img_19468920d02bb42e87819eea.jpg','/uploads/builder/img_df9e02ff2848da94b9b5d0a5.jpg','/uploads/builder/img_e312cc78216603db61ef11e1.jpg','/uploads/builder/img_59c57f0e9dd6ac00cde09f4b.jpg','/uploads/builder/img_f45840b26f3550aa1c0c6654.jpg','/uploads/builder/Gambar-WhatsApp-2025-08-13-pukul-16-21-57_63657117-82483e4aa9.jpg','/uploads/builder/IMG-20250714-WA0011-13e3488818.jpg','/uploads/builder/img_a4e2b17a7f3fb34e381f5a3c.jpg'
];
if(!$published && $pageSlug!=='home'){
    // Public menu must remain usable even when a Builder page has not been published yet.
    $publicFallbacks = [
      'profil' => '<section class="public-page-section"><div class="eyebrow">PROFIL SEKOLAH</div><h1>'.htmlspecialchars($settings['site_name']).'</h1><p>Selamat datang di portal resmi SDN Ciburayut 01. Halaman ini memuat informasi profil, visi, misi, sejarah, dan identitas sekolah.</p><div class="public-info-grid"><div><b>Nama Sekolah</b><span>'.htmlspecialchars($settings['site_name']).'</span></div><div><b>Alamat</b><span>'.htmlspecialchars($settings['address']).'</span></div><div><b>Tagline</b><span>'.htmlspecialchars($settings['tagline']).'</span></div></div></section>',
      'informasi' => '<section class="public-page-section"><div class="eyebrow">INFORMASI SEKOLAH</div><h1>Pengumuman & Informasi</h1><p>Informasi terbaru dari SDN Ciburayut 01.</p><div class="news-placeholder">Memuat informasi sekolah...</div></section>',
      'guru' => '<section class="public-page-section"><div class="eyebrow">TENAGA PENDIDIK</div><h1>Profil Guru</h1><p>Kenali tenaga pendidik dan kependidikan SDN Ciburayut 01.</p><div data-teachers-block="1"></div></section>',
      'galeri' => '<section class="public-page-section"><div class="eyebrow">DOKUMENTASI</div><h1>Galeri Sekolah</h1><p>Dokumentasi kegiatan SDN Ciburayut 01.</p><div class="builder-gallery"><div class="builder-gallery-grid">'.implode('', array_map(function($u,$i){return '<figure class="builder-gallery-item"><a href="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'" class="gallery-link" data-lightbox="1"><img src="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'" alt="Foto kegiatan sekolah '.($i+1).'"></a></figure>';}, $schoolGallery ?? [], array_keys($schoolGallery ?? []))).'</div></div></section>',
      'contact' => '<section class="public-page-section" data-contact-block="1"><div class="eyebrow">HUBUNGI KAMI</div><h1>Kontak Sekolah</h1><p>Gunakan informasi berikut untuk menghubungi SDN Ciburayut 01.</p></section>'
    ];
    $published = $publicFallbacks[$pageSlug] ?? '<section class="public-page-section"><div class="eyebrow">HALAMAN SEKOLAH</div><h1>'.htmlspecialchars(ucwords(str_replace('-',' ',$pageSlug))).'</h1><p>Informasi halaman sekolah akan tersedia setelah dipublikasikan melalui Builder.</p></section>';
}
if(!$published){$published='<div class="preview-nav"><div class="preview-logo">01</div><div class="preview-school">'.htmlspecialchars($settings['site_name']).'</div><div class="preview-links"><span>Beranda</span><span>Profil</span><span>Informasi</span><span>Guru</span></div></div><div class="hero-block"><div class="eyebrow">'.htmlspecialchars($settings['tagline']).'</div><h1>Selamat Datang di '.htmlspecialchars($settings['site_name']).'</h1><p>Portal resmi sekolah untuk informasi, kegiatan, profil guru, dan layanan digital.</p></div>';}

/* FINAL PUBLIC HERO FALLBACK */
if ($pageSlug === 'home' && (!is_string($published) || stripos($published, 'hero-block') === false)) {
    $hero = '<section class="hero-block builder-hero public-hero-fallback">'
          . '<div class="hero-content">'
          . '<div class="eyebrow">'.htmlspecialchars($settings['tagline']).'</div>'
          . '<h1>Selamat Datang di '.htmlspecialchars($settings['site_name']).'</h1>'
          . '<p>Portal resmi sekolah untuk informasi, kegiatan, profil guru, dan layanan digital.</p>'
          . '<div class="hero-actions">'
          . '<a class="hero-btn-primary" href="/?page=informasi">Jelajahi Portal →</a>'
          . '<a class="hero-btn-secondary" href="/?page=profil">Profil Sekolah</a>'
          . '</div></div>'
          . '<div class="hero-image-badge">🏫<span>Portal Sekolah Digital</span></div>'
          . '</section>';
    $published = $hero . $published;
}

function renderPublicNews($pdo){
 try{$rows=$pdo->query("SELECT slug,title,excerpt,image_url,category,published_at FROM website_builder_news WHERE status='published' ORDER BY published_at DESC,id DESC LIMIT 6")->fetchAll();}catch(Throwable $e){$rows=[];}
 if(!$rows)return '<div class="news-empty">Belum ada berita atau pengumuman yang dipublikasikan.</div>';
 $h='<div class="public-news-grid">'; foreach($rows as $r){$h.='<article class="public-news-card">'; if($r['image_url'])$h.='<img src="'.htmlspecialchars($r['image_url']).'" alt="'.htmlspecialchars($r['title']).'">'; $h.='<div class="public-news-body"><small>'.htmlspecialchars($r['category']).' · '.htmlspecialchars($r['published_at']?date('d M Y',strtotime($r['published_at'])):'').'</small><h3>'.htmlspecialchars($r['title']).'</h3><p>'.htmlspecialchars($r['excerpt']?:'Baca informasi selengkapnya dari sekolah.').'</p><a href="/?page=informasi&news='.rawurlencode($r['slug']).'">Baca selengkapnya →</a></div></article>'; } return $h.'</div>';
}

function renderPublicTeachers($pdo){
  /*
   * MASTER PROFIL GURU: database aplikasi Absensi SD.
   * Perubahan ini hanya memengaruhi halaman publik ?page=guru.
   * Menu, Builder, dan data guru website tidak diubah.
   */
  $rows = [];
  $absensi = null;

  /*
   * Jangan memakai placeholder DB_HOST/DB_USER sebagai kredensial literal.
   * Kredensial Absensi hanya boleh berasal dari konfigurasi server yang tidak
   * disimpan di Git. Jika konstanta belum disediakan, gunakan fallback PDO.
   */
  $dbHost = defined('ABSENSI_DB_HOST') ? ABSENSI_DB_HOST : (defined('DB_HOST') ? DB_HOST : null);
  $dbUser = defined('ABSENSI_DB_USER') ? ABSENSI_DB_USER : (defined('DB_USER') ? DB_USER : null);
  $dbPass = defined('ABSENSI_DB_PASS') ? ABSENSI_DB_PASS : (defined('DB_PASS') ? DB_PASS : null);
  $dbName = defined('ABSENSI_DB_NAME') ? ABSENSI_DB_NAME : (defined('DB_NAME') ? DB_NAME : null);

  try {
    if ($dbHost && $dbUser && $dbName) {
      $absensi = new mysqli($dbHost, $dbUser, (string)($dbPass ?? ''), $dbName);
    }
    if ($absensi instanceof mysqli && !$absensi->connect_errno) {
      $absensi->set_charset('utf8mb4');
      $sql = "SELECT id_guru,nama,nip,nuptk,jabatan,jenis_ptk,foto,no_hp
              FROM guru
              WHERE status='Aktif'
              ORDER BY
                CASE
                  WHEN LOWER(COALESCE(jabatan,'')) LIKE '%kepala sekolah%' THEN 0
                  WHEN LOWER(COALESCE(jenis_ptk,'')) LIKE '%kepala sekolah%' THEN 0
                  ELSE 1
                END,
                nama ASC, id_guru ASC
              LIMIT 100";
      $q = $absensi->query($sql);
      if ($q) while ($r = $q->fetch_assoc()) $rows[] = $r;
      $absensi->close();
    }
  } catch (Throwable $e) {
    $rows = [];
    if ($absensi instanceof mysqli) @$absensi->close();
  }

  /* Jika database Absensi tidak dapat diakses, pertahankan fallback lama. */
  if (!$rows) {
    try {
      $rows = $pdo->query("SELECT * FROM teachers WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 50")->fetchAll();
    } catch (Throwable $e) {
      $rows = [];
    }
  }

  if(!$rows) return '<div class="teachers-empty"><b>Profil guru belum tersedia.</b><span>Data akan tampil setelah profil guru diaktifkan pada aplikasi Absensi.</span></div>';

  $h='<div class="public-teacher-grid">';
  foreach($rows as $r){
    $name=trim((string)($r['nama']??$r['name']??'Guru')) ?: 'Guru';
    $photo=trim((string)($r['foto']??$r['photo']??$r['photo_url']??''));
    if($photo){
      if(preg_match('#^https?://#i',$photo)) {
        // URL eksternal dipertahankan.
      } elseif(str_starts_with($photo, '/')) {
        // Path absolut lokal dipertahankan.
      } elseif(str_starts_with($photo, 'assets/images/guru/')) {
        $photo='/absensi_sd/'.ltrim($photo,'/');
      } elseif(str_starts_with($photo, 'uploads/guru/')) {
        $photo='/'.ltrim($photo,'/');
      } else {
        $photo='/absensi_sd/assets/images/guru/'.basename($photo);
      }
    }
    $position=trim((string)($r['jabatan']??$r['position']??$r['role']??''));
    $subject=trim((string)($r['jenis_ptk']??$r['subject']??''));
    $nip=trim((string)($r['nip']??''));
    $nuptk=trim((string)($r['nuptk']??''));

    $h.='<article class="public-teacher-card">';
    if($photo){
      $h.='<img src="'.htmlspecialchars($photo,ENT_QUOTES,'UTF-8').'" alt="Foto '.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'" loading="lazy">';
    }else{
      $h.='<div class="teacher-avatar">👨‍🏫</div>';
    }
    $h.='<div class="public-teacher-body">';
    $h.='<h3>'.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'</h3>';
    if($position) $h.='<div class="teacher-position">'.htmlspecialchars($position,ENT_QUOTES,'UTF-8').'</div>';
    if($subject) $h.='<div class="teacher-meta">📚 '.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</div>';
    if($nip) $h.='<div class="teacher-meta">🪪 NIP: '.htmlspecialchars($nip,ENT_QUOTES,'UTF-8').'</div>';
    if($nuptk) $h.='<div class="teacher-meta">🆔 NUPTK: '.htmlspecialchars($nuptk,ENT_QUOTES,'UTF-8').'</div>';
    $h.='</div></article>';
  }
  return $h.'</div>';
}

function safeExternalUrl($url,$allowedHosts=[]){
 $url=trim((string)$url); if(!$url) return '';
 $parts=parse_url($url); if(!$parts || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']),['http','https'],true)) return '';
 if($allowedHosts && !in_array(strtolower($parts['host']??''),$allowedHosts,true)) return '';
 return $url;
}
function renderPublicContact($settings){
 $address=trim((string)($settings['address']??'')); $phone=trim((string)($settings['phone']??'')); $wa=preg_replace('/\D+/','',(string)($settings['whatsapp']??'')); $email=trim((string)($settings['email']??''));
 $maps=safeExternalUrl($settings['maps_link']??'');
 $fb=safeExternalUrl($settings['facebook_url']??''); $ig=safeExternalUrl($settings['instagram_url']??''); $yt=safeExternalUrl($settings['youtube_url']??'');
 $embed=safeExternalUrl($settings['maps_embed_url']??'', ['www.google.com','www.google.co.id']);
 $h='<div class="contact-public-grid"><div class="contact-public-info">';
 if($address)$h.='<a class="contact-row" href="'.htmlspecialchars($maps).'"'.($maps?' target="_blank" rel="noopener"':'').'><span>📍</span><div><b>Alamat</b><small>'.htmlspecialchars($address).'</small></div></a>';
 if($phone)$h.='<a class="contact-row" href="tel:'.htmlspecialchars(preg_replace('/[^0-9+]/','',$phone)).'"><span>📞</span><div><b>Telepon</b><small>'.htmlspecialchars($phone).'</small></div></a>';
 if($email)$h.='<a class="contact-row" href="mailto:'.htmlspecialchars($email).'"><span>✉️</span><div><b>Email</b><small>'.htmlspecialchars($email).'</small></div></a>';
 if($wa)$h.='<a class="contact-row" href="https://wa.me/'.htmlspecialchars($wa).'" target="_blank" rel="noopener"><span>💬</span><div><b>WhatsApp</b><small>Hubungi sekolah melalui WhatsApp</small></div></a>';
 $h.='<div class="contact-social">'; if($fb)$h.='<a href="'.htmlspecialchars($fb).'" target="_blank" rel="noopener">Facebook</a>'; if($ig)$h.='<a href="'.htmlspecialchars($ig).'" target="_blank" rel="noopener">Instagram</a>'; if($yt)$h.='<a href="'.htmlspecialchars($yt).'" target="_blank" rel="noopener">YouTube</a>'; $h.='</div></div>';
 if($embed)$h.='<div class="contact-map"><iframe src="'.htmlspecialchars($embed).'" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi sekolah"></iframe></div>'; else $h.='<div class="contact-map contact-map-empty"><span>📍</span><b>Lokasi Sekolah</b><small>Tambahkan Google Maps Embed URL di pengaturan Kontak.</small></div>';
 return $h.'</div>';
}

function renderPublicAgenda($pdo){
 try{$rows=$pdo->query("SELECT title,category,event_date,start_time,end_time,location,description FROM website_builder_agenda WHERE status='published' AND event_date>=CURDATE() ORDER BY event_date ASC,start_time ASC,id ASC LIMIT 9")->fetchAll();}catch(Throwable $e){$rows=[];}
 if(!$rows)return '<div class="agenda-empty">Belum ada agenda kegiatan yang dipublikasikan.</div>';
 $h='<div class="agenda-public-grid">';
 foreach($rows as $r){
  $date=date('d M Y',strtotime($r['event_date']));
  $time=''; if($r['start_time']){$time=date('H:i',strtotime($r['start_time'])); if($r['end_time'])$time.='–'.date('H:i',strtotime($r['end_time']));}
  $h.='<article class="agenda-public-card"><span class="agenda-public-date">📅 '.htmlspecialchars($date).'</span><h3>'.htmlspecialchars($r['title']).'</h3><div class="agenda-public-meta">🏷️ '.htmlspecialchars($r['category']);
  if($time)$h.=' · ⏰ '.htmlspecialchars($time); if($r['location'])$h.=' · 📍 '.htmlspecialchars($r['location']);
  $h.='</div>'; if($r['description'])$h.='<p>'.htmlspecialchars(mb_strimwidth($r['description'],0,180,'…')).'</p>'; $h.='</article>';
 }
 return $h.'</div>';
}

/* FINAL GALLERY CLEANUP: buang gambar demo Picsum dari website publik dan gunakan foto sekolah lokal. */
if ($pageSlug === 'home' && is_string($published)) {
    $demoGallery = [
        'https://picsum.photos/seed/ciburayut1/800/500',
        'https://picsum.photos/seed/ciburayut2/800/500',
        'https://picsum.photos/seed/ciburayut3/800/500'
    ];
    $galleryIndex=0;
    $published=str_replace($demoGallery, $schoolGallery, $published);
    $published=preg_replace_callback('/<div class="builder-gallery"([^>]*)>.*?<\/div>\s*<\/div>/s', function($m) use ($schoolGallery,&$galleryIndex){
        // Hanya ganti gallery yang masih kosong/berisi sumber demo; gallery lokal tetap dipertahankan.
        $block=$m[0];
        if (stripos($block,'picsum.photos')===false) return $block;
        $attrs=$m[1];
        $items=[];
        foreach($schoolGallery as $i=>$u){$items[]='<figure class="builder-gallery-item"><a href="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'" class="gallery-link" data-lightbox="1"><img src="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'" alt="Foto kegiatan sekolah '.($i+1).'"></a></figure>';}
        return '<div class="builder-gallery"'.$attrs.'><div class="builder-gallery-grid">'.implode('', $items).'</div></div>';
    }, $published);
}

/* MODERN DIGITAL HOMEPAGE: hanya memengaruhi halaman Beranda. Menu/modul lain tetap memakai konten masing-masing. */
if ($pageSlug === 'home') {
    $heroPhoto = trim((string)($settings['hero_image'] ?? ''));
    if ($heroPhoto === '') $heroPhoto = $schoolGallery[0] ?? '';
    $heroPhotoEsc = htmlspecialchars($heroPhoto, ENT_QUOTES, 'UTF-8');
    $siteNameRaw = trim((string)$settings['site_name']);
    $taglineRaw = trim((string)$settings['tagline']);
    $heroEyebrow = trim((string)($settings['hero_eyebrow'] ?? '')) ?: 'PORTAL INFORMASI SEKOLAH';
    $heroTitle = trim((string)($settings['hero_title'] ?? '')) ?: 'Sekolah Ramah Anak, Berkarakter, dan Berbudaya Digital';
    $heroSub = trim((string)($settings['hero_subtitle'] ?? '')) ?: 'Portal resmi sekolah untuk informasi, kegiatan, profil guru, layanan pendidikan, dan berbagai informasi terkini.';
    $btn1 = trim((string)($settings['hero_button_text'] ?? '')) ?: 'Jelajahi Portal';
    $btn1url = trim((string)($settings['hero_button_url'] ?? '')) ?: '/?page=informasi';
    $btn2 = trim((string)($settings['hero_button2_text'] ?? '')) ?: 'Profil Sekolah';
    $btn2url = trim((string)($settings['hero_button2_url'] ?? '')) ?: '/?page=profil';
    $digitalHero = '<section class="digital-hero" style="--hero-image:url(\''.$heroPhotoEsc.'\')">'
      .'<div class="digital-grid"></div><div class="digital-glow digital-glow-a"></div><div class="digital-glow digital-glow-b"></div>'
      .'<div class="digital-hero-content"><div class="digital-eyebrow">'.htmlspecialchars($heroEyebrow,ENT_QUOTES,'UTF-8').'</div>'
      .'<h1>'.htmlspecialchars($siteNameRaw,ENT_QUOTES,'UTF-8').'</h1><h2>'.htmlspecialchars($heroTitle,ENT_QUOTES,'UTF-8').'</h2>'
      .'<p>'.htmlspecialchars($heroSub,ENT_QUOTES,'UTF-8').'</p><div class="digital-actions">'
      .'<a class="digital-btn digital-btn-main" href="'.htmlspecialchars($btn1url,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($btn1,ENT_QUOTES,'UTF-8').' <span>→</span></a>'
      .'<a class="digital-btn digital-btn-ghost" href="'.htmlspecialchars($btn2url,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($btn2,ENT_QUOTES,'UTF-8').'</a></div>'
      .'<div class="digital-chips"><span>◈ Kurikulum Merdeka</span><span>◈ Berbasis Digital</span><span>◈ Berkarakter</span></div></div>'
      .'<div class="digital-hero-visual"><div class="digital-photo"><img src="'.$heroPhotoEsc.'" alt="'.htmlspecialchars($siteNameRaw,ENT_QUOTES,'UTF-8').'" onerror="this.style.display=\'none\'">'
      .'<div class="photo-overlay"></div><div class="photo-label"><b>PORTAL SEKOLAH DIGITAL</b><small>'.htmlspecialchars($taglineRaw,ENT_QUOTES,'UTF-8').'</small></div></div>'
      .'<div class="digital-orb orb-one"></div><div class="digital-orb orb-two"></div></div></section>';
    // Remove the first legacy hero block on the homepage only, then insert the new hero.
    $published = preg_replace('/<section[^>]*class="[^"\']*hero-block[^"\']*"[^>]*>.*?<\/section>/is','',$published,1);
    $published = $digitalHero.$published;
}

if(str_contains((string)$published,'data-agenda-block="1"')){$agendaHtml=renderPublicAgenda($pdo);$published=preg_replace('/<div class="agenda-placeholder">.*?<\/div>/s',''.$agendaHtml,$published);}
if(str_contains((string)$published,'data-contact-block="1"')){$contactHtml=renderPublicContact($settings);$published=preg_replace('/(<section[^>]*data-contact-block="1"[^>]*>).*?(<\/section>)/s','$1<div class="eyebrow">HUBUNGI KAMI</div><h2>Kontak Sekolah</h2>'.$contactHtml.'$2',$published);}
$published=preg_replace('/<footer[^>]*data-builder-footer="1"[^>]*>.*?<\/footer>/s','',$published);

if(str_contains((string)$published,'data-news-block="1"')){$newsHtml=renderPublicNews($pdo);$published=preg_replace('/<div class="news-placeholder">.*?<\/div>/s',''.$newsHtml,$published);}
if(str_contains((string)$published,'data-teachers-block="1"')){$teacherHtml=renderPublicTeachers($pdo);$published=preg_replace('/(<section[^>]*data-teachers-block="1"[^>]*>).*?(<\/section>)/s','$1'.$teacherHtml.'$2',$published);}

// PUBLIC GURU: halaman ini selalu terhubung ke data Profil Guru admin, meskipun konten Builder lama tidak memiliki marker guru.
if ($pageSlug === 'guru') {
    $published = '<section class="public-page-section public-guru-page"><div class="eyebrow">TENAGA PENDIDIK</div><h1>Profil Guru</h1><p>Kenali tenaga pendidik dan kependidikan SDN Ciburayut 01.</p>'.renderPublicTeachers($pdo).'</section>';
}

// Statistik sekolah live hanya di beranda. Konten Builder dan menu lama tetap dipertahankan.
$schoolStatsHtml = ($pageSlug === 'home') ? renderSchoolStats($pdo) : '';
if ($pageSlug === 'home' && $schoolStatsHtml !== '') {
    // V4.1: Statistik harus selalu berada SETELAH Hero digital.
    // Hapus blok statistik lama terlebih dahulu agar tidak muncul di atas Hero / dobel.
    $published = preg_replace('/<section[^>]*class="school-live-stats"[^>]*>.*?<\/section>/s', '', (string)$published, 1);
    if (str_contains((string)$published, 'data-school-stats="1"')) {
        $published = preg_replace('/<section[^>]*data-school-stats="1"[^>]*>.*?<\/section>/s', $schoolStatsHtml, (string)$published, 1);
    } else {
        $inserted = false;
        // Prioritas: tepat setelah Hero digital V4.
        $published = preg_replace_callback('/(<section[^>]*class="[^"]*digital-hero[^"]*"[^>]*>.*?<\/section>)/s', function($m) use ($schoolStatsHtml, &$inserted){
            if($inserted) return $m[0];
            $inserted=true;
            return $m[0].$schoolStatsHtml;
        }, (string)$published, 1);
        // Fallback aman untuk halaman lama: setelah hero-block.
        if (!$inserted) {
            $published = preg_replace_callback('/(<section[^>]*class="[^"]*hero-block[^"]*"[^>]*>.*?<\/section>)/s', function($m) use ($schoolStatsHtml, &$inserted){
                if($inserted) return $m[0];
                $inserted=true;
                return $m[0].$schoolStatsHtml;
            }, (string)$published, 1);
        }
        if (!$inserted) $published = $schoolStatsHtml.$published;
    }
}

if(isset($_GET['news']) && $pageSlug==='informasi'){
 try{$q=$pdo->prepare("SELECT * FROM website_builder_news WHERE slug=? AND status='published' LIMIT 1");$q->execute([$_GET['news']]);$nr=$q->fetch();if($nr){$published='<article class="public-news-detail"><div class="eyebrow">'.htmlspecialchars($nr['category']).'</div><h1>'.htmlspecialchars($nr['title']).'</h1><small>'.htmlspecialchars($nr['published_at']?date('d M Y',strtotime($nr['published_at'])):'').'</small>'.($nr['image_url']?'<img src="'.htmlspecialchars($nr['image_url']).'" alt="'.htmlspecialchars($nr['title']).'">':'').'<div class="news-content">'.nl2br(htmlspecialchars($nr['content']?:$nr['excerpt'])).'</div><a href="/?page=informasi">← Kembali ke Informasi</a></article>';}}catch(Throwable $e){}
}

$pageTitle='';
try{$q=$pdo->prepare("SELECT title FROM website_builder_pages WHERE slug=? LIMIT 1");$q->execute([$pageSlug]);$pageTitle=$q->fetchColumn()?:'';}catch(Throwable $e){}
if(isset($nr) && !empty($nr['title'])){$pageTitle=$nr['title'];}
if(!$pageTitle){$pageTitle=['home'=>'Beranda','profil'=>'Profil Sekolah','informasi'=>'Informasi','guru'=>'Profil Guru','galeri'=>'Galeri','contact'=>'Kontak Sekolah'][$pageSlug]??ucwords(str_replace('-',' ',$pageSlug));}
$baseTitle=trim((string)($settings['seo_title']??'')) ?: $settings['site_name'];
$title=$pageTitle ? $pageTitle.' — '.$settings['site_name'] : $baseTitle;
$description=trim((string)($settings['seo_description']??''));
if(isset($nr) && !empty($nr['excerpt'])){$description=trim((string)$nr['excerpt']);}
if(!$description)$description=$settings['tagline'];
$description=mb_substr($description,0,320);
$canonical=trim((string)($settings['seo_canonical']??''));
if(!$canonical)$canonical='https://sdnciburayut01.com/'.($pageSlug!=='home'?'?page='.rawurlencode($pageSlug):'');
if(isset($_GET['news']) && $pageSlug==='informasi' && !empty($_GET['news'])){$canonical='https://sdnciburayut01.com/?page=informasi&news='.rawurlencode((string)$_GET['news']);}
// Public safety normalization: never expose stale Builder placeholders from older published drafts.
$schoolName = trim((string)($settings['site_name'] ?? 'SDN CIBURAYUT 01')) ?: 'SDN CIBURAYUT 01';
$published = preg_replace('/(<h[1-6][^>]*>)\s*undefined\s*(<\/h[1-6]>)/i', '$1'.htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8').'$2', (string)$published);
$published = preg_replace('/(<(?:p|span|small|div|b|strong)[^>]*>)\s*undefined\s*(<\/[^>]+>)/i', '$1$2', (string)$published);
$published = preg_replace('/<button[^>]*>\s*Tombol Baru\s*<\/button>/i', '', (string)$published);
$published = preg_replace('/<a[^>]*>\s*Tombol Baru\s*<\/a>/i', '', (string)$published);

$robots=trim((string)($settings['seo_robots']??'index,follow'));
$ogImage=trim((string)($settings['seo_og_image']??''));
if(!$ogImage)$ogImage=trim((string)($settings['hero_image']??''));
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<meta name="description" content="<?=htmlspecialchars($description)?>">
<meta name="keywords" content="<?=htmlspecialchars($settings['seo_keywords']??'')?>">
<meta name="author" content="<?=htmlspecialchars($settings['seo_author']??$settings['site_name'])?>">
<meta name="robots" content="<?=htmlspecialchars($robots)?>">
<link rel="canonical" href="<?=htmlspecialchars($canonical)?>">
<meta property="og:type" content="<?=isset($nr)?'article':'website'?>">
<meta property="og:title" content="<?=htmlspecialchars($title)?>">
<meta property="og:description" content="<?=htmlspecialchars($description)?>">
<meta property="og:url" content="<?=htmlspecialchars($canonical)?>">
<?php if($ogImage):?><meta property="og:image" content="<?=htmlspecialchars($ogImage)?>"><?php endif;?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=htmlspecialchars($title)?>">
<meta name="twitter:description" content="<?=htmlspecialchars($description)?>">
<?php if($ogImage):?><meta name="twitter:image" content="<?=htmlspecialchars($ogImage)?>"><?php endif;?>
<?php if($settings['favicon_url']):?><link rel="icon" href="<?=htmlspecialchars($settings['favicon_url'])?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode(["@context"=>"https://schema.org","@type"=>"EducationalOrganization","name"=>$settings['site_name'],"url"=>"https://sdnciburayut01.com/","description"=>$description,"logo"=>$settings['logo_url']?:null,"telephone"=>$settings['phone']?:null,"email"=>$settings['email']?:null,"address"=>["@type"=>"PostalAddress","streetAddress"=>$settings['address']],"sameAs"=>array_values(array_filter([$settings['facebook_url'],$settings['instagram_url'],$settings['youtube_url']]))],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<link rel="stylesheet" href="/assets/css/public.css">
<style>
:root{--school-primary:<?=htmlspecialchars($settings['primary_color'])?>}
body{margin:0;background:#f5f8fb}
.published-header{--p:var(--school-primary);position:<?= $header['header_sticky'] ? 'sticky' : 'relative' ?>;top:0;z-index:1000;min-height:<?=max(56,min(120,(int)$header['header_height']))?>px;padding:0 clamp(18px,5vw,70px);display:flex;align-items:center;justify-content:<?= $header['header_align']==='center'?'center':($header['header_align']==='left'?'flex-start':'space-between') ?>;gap:18px;background:<?=htmlspecialchars($header['header_bg'])?>;border-bottom:1px solid rgba(148,163,184,.18);box-shadow:0 7px 25px rgba(15,23,42,.06);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
.published-header.header-transparent{background:transparent!important;box-shadow:none;border-bottom-color:transparent}.published-header.header-overlay{position:absolute;left:0;right:0;color:#fff}.published-header.header-overlay .ph-copy b{color:#fff}.published-header.header-overlay .ph-copy small{color:rgba(255,255,255,.78)}.published-header.header-overlay .ph-nav{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)}.published-header.header-overlay .ph-nav a{color:#fff}.ph-brand{display:flex;align-items:center;gap:11px;min-width:0}.ph-brand img,.ph-logo{width:<?=max(32,min(72,(int)$header['header_logo_width']))?>px;height:<?=max(32,min(72,(int)$header['header_logo_width']))?>px;border-radius:13px;object-fit:cover;flex:0 0 46px}.ph-logo{display:grid;place-items:center;background:var(--p);color:#fff;font-weight:900;box-shadow:0 8px 20px color-mix(in srgb,var(--p) 25%,transparent)}
.ph-copy{min-width:0}.ph-copy b{display:block;font-size:14px;color:#102a43;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ph-copy small{display:block;margin-top:3px;color:#8291a3;font-size:9px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ph-nav{display:flex;align-items:center;gap:3px;padding:5px;background:#f8fafc;border:1px solid #edf1f5;border-radius:14px}.ph-nav a{padding:9px 11px;border-radius:9px;text-decoration:none;color:#52657a;font-size:12px;font-weight:700;transition:.18s ease}.ph-nav a:hover{background:#fff;color:var(--p);box-shadow:0 4px 12px rgba(15,23,42,.06)}.ph-nav a.ph-cta{margin-left:6px;background:linear-gradient(135deg,var(--p),color-mix(in srgb,var(--p) 78%,#000));color:#fff;box-shadow:0 8px 18px color-mix(in srgb,var(--p) 22%,transparent);padding:10px 15px}
.ph-menu{display:none;border:0;width:42px;height:42px;border-radius:11px;background:#f1f5f9;color:#334e68;font-size:22px;cursor:pointer;position:relative;z-index:1102;pointer-events:auto;-webkit-tap-highlight-color:transparent}.published-wrap{--school-primary:<?=htmlspecialchars($settings['primary_color'])?>}.published-wrap .preview-button,.published-wrap .preview-logo{background:var(--school-primary)!important}.published-wrap .preview-nav{display:none!important}
.published-footer{padding:32px 5%;background:#102a43;color:#fff;font-size:12px}.published-footer span{opacity:.8}
@media(max-width:900px){.published-header{padding:11px 4%}.ph-nav a{padding:9px 8px;font-size:11px}.ph-nav .ph-cta{padding:9px 11px}}
@media(max-width:760px){.published-header{padding:10px 4%;position:sticky;z-index:1100}.ph-menu{display:block;margin-left:auto;position:relative;z-index:1102;pointer-events:auto}.ph-nav{display:none;position:absolute;left:4%;right:4%;top:70px;z-index:1101;flex-direction:column;align-items:stretch;padding:10px;background:rgba(255,255,255,.98);border:1px solid #e6ebf1;border-radius:16px;box-shadow:0 18px 45px rgba(16,42,67,.16)}.ph-nav.open{display:flex}.ph-nav a{text-align:center}.ph-nav a.ph-cta{margin-left:0;margin-top:3px}.ph-copy b{font-size:12px}.ph-copy small{font-size:8px}.ph-brand img,.ph-logo{width:42px;height:42px;flex-basis:42px}}
@media(max-width:420px){.ph-copy{max-width:180px}.ph-copy small{display:none}}
.footer-cols-1{grid-template-columns:1fr}.footer-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.footer-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.footer-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.published-footer{padding:42px 5%;font-size:12px}.footer-inner{max-width:1200px;margin:auto;display:grid;gap:28px;align-items:start}.footer-brand b,.footer-links b,.footer-contact b,.footer-social b{font-size:15px}.footer-brand span,.footer-contact span{display:block;margin-top:7px;opacity:.78;line-height:1.6}.footer-links,.footer-contact,.footer-social{display:grid;gap:8px}.footer-links a,.footer-social a{color:inherit;text-decoration:none;opacity:.82}.footer-links a:hover,.footer-social a:hover{opacity:1;text-decoration:underline}.footer-copy{max-width:1200px;margin:26px auto 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.16);opacity:.72}@media(max-width:760px){.footer-cols-2,.footer-cols-3,.footer-cols-4{grid-template-columns:1fr}}
/* MODERN DIGITAL SCHOOL PORTAL - HOME ONLY */
.digital-hero{position:relative;isolation:isolate;min-height:520px;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.02fr) minmax(430px,.98fr);gap:28px;align-items:center;padding:72px clamp(24px,6vw,90px);background:linear-gradient(120deg,#061a38 0%,#0b3f7b 48%,#0d67ad 100%);color:#fff}
.digital-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,15,39,.96) 0%,rgba(4,37,78,.82) 42%,rgba(4,44,84,.32) 72%,rgba(2,15,39,.15) 100%),var(--hero-image) center/cover no-repeat;z-index:-3}
.digital-grid{position:absolute;inset:0;z-index:-2;opacity:.34;background-image:linear-gradient(rgba(76,201,255,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(76,201,255,.16) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(90deg,#000,transparent 90%)}
.digital-grid:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 22% 55%,rgba(0,195,255,.35),transparent 30%),radial-gradient(circle at 78% 30%,rgba(99,102,241,.25),transparent 30%)}
.digital-glow{position:absolute;border-radius:50%;filter:blur(8px);pointer-events:none}.digital-glow-a{width:360px;height:360px;right:18%;top:-180px;background:rgba(0,204,255,.18)}.digital-glow-b{width:260px;height:260px;left:-100px;bottom:-130px;background:rgba(30,64,175,.42)}
.digital-hero-content{position:relative;z-index:3;max-width:700px}.digital-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border:1px solid rgba(125,211,252,.35);background:rgba(14,165,233,.12);border-radius:999px;color:#bdeeff;font-weight:900;font-size:11px;letter-spacing:1.4px}.digital-eyebrow:before{content:"●";font-size:7px;color:#4ade80}.digital-hero h1{margin:18px 0 3px;font-size:clamp(34px,5vw,66px);line-height:.98;letter-spacing:-2px;font-weight:950;color:#fff}.digital-hero h2{margin:0 0 18px;font-size:clamp(22px,3vw,40px);line-height:1.08;letter-spacing:-1px;color:#f7fbff;max-width:680px}.digital-hero p{max-width:650px;margin:0;color:#d9efff;font-size:16px;line-height:1.75}.digital-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.digital-btn{display:inline-flex;align-items:center;gap:9px;padding:13px 19px;border-radius:13px;text-decoration:none;font-weight:900;font-size:13px;transition:.2s ease}.digital-btn-main{background:#fff;color:#07519b;box-shadow:0 12px 30px rgba(0,0,0,.18)}.digital-btn-main:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(0,0,0,.25)}.digital-btn-ghost{border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.09);color:#fff;backdrop-filter:blur(10px)}.digital-btn-ghost:hover{background:rgba(255,255,255,.16)}.digital-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:28px}.digital-chips span{padding:8px 11px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.08);font-size:11px;font-weight:800;color:#dff5ff;backdrop-filter:blur(10px)}
.digital-hero-visual{position:relative;z-index:2;min-height:390px;display:grid;place-items:center}.digital-photo{position:relative;width:min(570px,100%);height:370px;border-radius:28px;overflow:hidden;border:1px solid rgba(255,255,255,.3);box-shadow:0 30px 70px rgba(0,0,0,.3);transform:perspective(1200px) rotateY(-4deg) rotateX(1deg)}.digital-photo img{width:100%;height:100%;object-fit:cover;display:block}.digital-photo .photo-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,23,55,.03) 30%,rgba(0,20,48,.72) 100%)}.photo-label{position:absolute;left:20px;right:20px;bottom:18px;padding:14px 16px;border-radius:15px;background:rgba(4,23,49,.62);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(12px)}.photo-label b{display:block;font-size:12px;letter-spacing:.9px}.photo-label small{display:block;margin-top:4px;color:#c9e9ff}.digital-orb{position:absolute;border-radius:50%;border:1px solid rgba(125,211,252,.35);background:radial-gradient(circle,rgba(56,189,248,.18),rgba(37,99,235,.03) 60%,transparent 70%)}.orb-one{width:190px;height:190px;right:-55px;top:-20px}.orb-two{width:110px;height:110px;left:-35px;bottom:0}
.published-wrap .school-live-stats{margin-top:0;position:relative;z-index:5}.published-wrap .school-live-stats-grid{margin-top:-42px}.published-wrap .school-live-stat{box-shadow:0 18px 40px rgba(8,47,73,.1)}
@media(max-width:980px){.digital-hero{grid-template-columns:1fr;padding:62px 5% 52px}.digital-hero-content{max-width:780px}.digital-hero-visual{min-height:300px}.digital-photo{height:320px;transform:none}.published-wrap .school-live-stats-grid{margin-top:20px}}
@media(max-width:620px){.digital-hero{min-height:0;padding:48px 20px 34px}.digital-hero h1{font-size:40px;letter-spacing:-1.4px}.digital-hero h2{font-size:25px}.digital-hero p{font-size:14px}.digital-hero-visual{min-height:245px;margin-top:10px}.digital-photo{height:245px;border-radius:20px}.digital-chips span{font-size:10px}.digital-btn{width:100%;justify-content:center}.digital-orb{display:none}}
</style>


<link rel="stylesheet" href="/assets/css/cloud-guru-public-overrides.css?v=1"></head><body>
<header class="published-header<?= $header['header_transparent']?' header-transparent':'' ?><?= $header['header_overlay']?' header-overlay':'' ?>">
<div class="ph-brand"><?php if($settings['logo_url']):?><img src="<?=htmlspecialchars($settings['logo_url'])?>" alt="Logo sekolah"><?php else:?><div class="ph-logo">01</div><?php endif;?><div class="ph-copy"><b><?=htmlspecialchars($settings['site_name'])?></b><small><?=htmlspecialchars($settings['tagline'])?></small></div></div>
<?php if($navbar['navbar_enabled']):?><details class="ph-menu-wrap"><summary class="ph-menu" aria-label="Buka menu"><span class="ph-menu-open">☰</span><span class="ph-menu-close">✕</span></summary><nav class="ph-nav nav-style-<?=htmlspecialchars($navbar['navbar_style'])?>"><?php foreach($navbar['navbar_items'] as $it):?><a href="<?=htmlspecialchars($it['url']??'#')?>"><?=htmlspecialchars($it['label']??'Menu')?></a><?php endforeach;?></nav></details><?php endif;?><a class="ph-cta ph-cloud-login" href="/auth/login.php">☁️ Masuk Cloud Guru</a></header>
<main class="published-wrap"><?= $published ?></main>
<footer class="published-footer" style="background:<?=htmlspecialchars($settings['footer_bg']??'#102a43')?>;color:<?=htmlspecialchars($settings['footer_text_color']??'#fff')?>"><div class="footer-inner footer-cols-<?=htmlspecialchars($settings['footer_layout']??'3')?>"><?php if(($settings['footer_show_logo']??'1')==='1'):?><div class="footer-brand"><b><?=htmlspecialchars($settings['site_name'])?></b><br><span><?=htmlspecialchars($settings['tagline'])?></span></div><?php endif;?><?php if(($settings['footer_show_quicklinks']??'1')==='1'):?><div class="footer-links"><b>Menu Cepat</b><?php foreach(preg_split('/\r?\n/',(string)($settings['footer_quicklinks']??'')) as $line){$parts=explode('|',$line,2);if(count($parts)===2&&trim($parts[0])):?><a href="<?=htmlspecialchars($parts[1])?>"><?=htmlspecialchars($parts[0])?></a><?php endif;}?></div><?php endif;?><?php if(($settings['footer_show_contact']??'1')==='1'):?><div class="footer-contact"><b>Hubungi Kami</b><span><?=htmlspecialchars($settings['address'])?></span><?php if($settings['phone']):?><span><?=htmlspecialchars($settings['phone'])?></span><?php endif;?><?php if($settings['email']):?><span><?=htmlspecialchars($settings['email'])?></span><?php endif;?></div><?php endif;?><?php if(($settings['footer_show_social']??'1')==='1'):?><div class="footer-social"><b>Sosial Media</b><?php if(!empty($settings['facebook_url'])):?><a href="<?=htmlspecialchars(safeExternalUrl($settings['facebook_url']))?>" target="_blank" rel="noopener">Facebook</a><?php endif;?><?php if(!empty($settings['instagram_url'])):?><a href="<?=htmlspecialchars(safeExternalUrl($settings['instagram_url']))?>" target="_blank" rel="noopener">Instagram</a><?php endif;?><?php if(!empty($settings['youtube_url'])):?><a href="<?=htmlspecialchars(safeExternalUrl($settings['youtube_url']))?>" target="_blank" rel="noopener">YouTube</a><?php endif;?><?php if(empty($settings['facebook_url'])&&empty($settings['instagram_url'])&&empty($settings['youtube_url'])):?><span>Media sosial sekolah akan ditambahkan.</span><?php endif;?></div><?php endif;?></div><div class="footer-copy"><?=htmlspecialchars($settings['footer_text'])?></div></footer>
<script id="public-menu-responsive-init">
(function(){
  function initPublicMenu(){
    var menu=document.querySelector('.published-header details.ph-menu-wrap');
    if(!menu) return;
    var trigger=menu.querySelector('summary.ph-menu');
    if(!trigger) return;
    trigger.setAttribute('aria-expanded',menu.open?'true':'false');
    menu.addEventListener('toggle',function(){
      trigger.setAttribute('aria-expanded',menu.open?'true':'false');
    });
    menu.querySelectorAll('.ph-nav a').forEach(function(link){
      link.addEventListener('click',function(){
        if(window.matchMedia('(max-width: 760px)').matches) menu.open=false;
      });
    });
    document.addEventListener('click',function(event){
      if(menu.open && window.matchMedia('(max-width: 760px)').matches && !menu.contains(event.target)) menu.open=false;
    });
    window.addEventListener('resize',function(){
      if(window.matchMedia('(min-width: 761px)').matches) menu.open=false;
    },{passive:true});
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initPublicMenu,{once:true});
  else initPublicMenu();
})();
</script>
</body></html>
