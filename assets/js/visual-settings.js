(function(){
'use strict';
var cfg = window.CGVisualSettings || {};
var trigger = document.getElementById('websiteSettingsButton');
if (!trigger) return;
var csrf = (document.querySelector('meta[name="cg-visual-csrf"]') || {}).content || '';
var values = {};
var activeGroup = 'identitas';
var dirty = false;
var loading = false;
var uploadTarget = 'hero_image';
var globalFileInput = null;
var categories = [
 {id:'identitas',label:'🏫 Identitas',title:'Identitas Website',desc:'Nama sekolah, tagline, logo, dan favicon yang dipakai di website publik.',fields:[
  {key:'site_name',label:'Nama sekolah',type:'text',required:true},
  {key:'tagline',label:'Tagline',type:'text'},
  {key:'logo_url',label:'Logo sekolah',type:'image'},
  {key:'favicon_url',label:'Favicon browser',type:'image'}
 ]},
 {id:'beranda',label:'✨ Beranda / Hero',title:'Beranda dan Banner Hero',desc:'Atur judul utama, deskripsi, gambar besar, dan kedua tombol pada halaman depan.',fields:[
  {key:'hero_eyebrow',label:'Label kecil di atas judul',type:'text'},
  {key:'hero_title',label:'Judul hero',type:'textarea'},
  {key:'hero_subtitle',label:'Deskripsi hero',type:'textarea'},
  {key:'hero_image',label:'Foto hero utama',type:'image'},
  {key:'hero_button_text',label:'Teks tombol utama',type:'text'},
  {key:'hero_button_url',label:'Alamat tombol utama',type:'url'},
  {key:'hero_button2_text',label:'Teks tombol kedua',type:'text'},
  {key:'hero_button2_url',label:'Alamat tombol kedua',type:'url'}
 ]},
 {id:'profil',label:'🏫 Profil',title:'Profil Sekolah',desc:'Konten Profil dikelola sebagai halaman visual, sehingga foto, judul, paragraf, kolom, dan tombol bisa disusun di kanvas.',special:'profile'},
 {id:'navigasi',label:'🧭 Navigasi',title:'Navigasi dan Header',desc:'Atur menu di bagian atas website. Satu baris berisi Label|URL, misalnya Profil|/?page=profil.',fields:[
  {key:'navbar_enabled',label:'Tampilkan menu navigasi',type:'checkbox'},
  {key:'navbar_items_text',label:'Daftar menu (satu baris per menu)',type:'textarea',placeholder:'Beranda|/?page=home\nProfil|/?page=profil\nInformasi|/?page=informasi\nGuru|/?page=guru\nGaleri|/?page=galeri'},
  {key:'navbar_cta_text',label:'Teks tombol kanan',type:'text'},
  {key:'navbar_cta_url',label:'Tujuan tombol kanan',type:'url'},
  {key:'navbar_style',label:'Gaya navigasi',type:'select',options:[['glass','Glass / transparan'],['solid','Solid'],['minimal','Minimal'],['transparent','Transparan']]},
  {key:'header_height',label:'Tinggi header (48–150 px)',type:'number'},
  {key:'header_logo_width',label:'Lebar logo (24–140 px)',type:'number'},
  {key:'header_sticky',label:'Header tetap di atas saat scroll',type:'checkbox'},
  {key:'header_transparent',label:'Header transparan',type:'checkbox'},
  {key:'header_overlay',label:'Header menumpuk di atas Hero',type:'checkbox'},
  {key:'header_align',label:'Posisi menu',type:'select',options:[['between','Logo kiri, menu kanan'],['left','Rata kiri'],['center','Tengah'],['right','Rata kanan']]}
 ]},
 {id:'kontak',label:'📍 Kontak',title:'Kontak dan Media Sosial',desc:'Data ini digunakan pada bagian informasi kontak website.',fields:[
  {key:'address',label:'Alamat sekolah',type:'textarea'},
  {key:'phone',label:'Nomor telepon',type:'text'},
  {key:'email',label:'Email sekolah',type:'text'},
  {key:'whatsapp',label:'Nomor WhatsApp',type:'text'},
  {key:'facebook_url',label:'URL Facebook',type:'url'},
  {key:'instagram_url',label:'URL Instagram',type:'url'},
  {key:'youtube_url',label:'URL YouTube',type:'url'},
  {key:'maps_link',label:'Link Google Maps',type:'url'},
  {key:'maps_embed_url',label:'URL peta sematan (embed)',type:'url'}
 ]},
 {id:'footer',label:'🧩 Footer',title:'Footer Website',desc:'Kelola tulisan penutup, link cepat, warna, dan bagian footer yang terlihat.',fields:[
  {key:'footer_text',label:'Tulisan footer',type:'textarea'},
  {key:'footer_quicklinks',label:'Menu cepat (satu baris: Label|URL)',type:'textarea'},
  {key:'footer_layout',label:'Tata letak footer',type:'select',options:[['2','2 kolom'],['3','3 kolom'],['4','4 kolom']]},
  {key:'footer_show_logo',label:'Tampilkan logo',type:'checkbox'},
  {key:'footer_show_contact',label:'Tampilkan kontak',type:'checkbox'},
  {key:'footer_show_quicklinks',label:'Tampilkan menu cepat',type:'checkbox'},
  {key:'footer_show_social',label:'Tampilkan media sosial',type:'checkbox'}
 ]},
 {id:'seo',label:'🔎 SEO',title:'Pengaturan SEO',desc:'Informasi untuk judul browser, hasil pencarian, dan pratinjau saat tautan dibagikan.',fields:[
  {key:'seo_title',label:'Judul SEO',type:'text'},
  {key:'seo_description',label:'Deskripsi SEO',type:'textarea'},
  {key:'seo_keywords',label:'Kata kunci',type:'textarea'},
  {key:'seo_author',label:'Penulis / pemilik',type:'text'},
  {key:'seo_og_image',label:'Gambar pratinjau berbagi',type:'image'},
  {key:'seo_canonical',label:'URL canonical',type:'url'},
  {key:'seo_robots',label:'Pengaturan indeks mesin pencari',type:'select',options:[['index,follow','Index dan follow'],['noindex,follow','No index, follow'],['index,nofollow','Index, no follow'],['noindex,nofollow','No index dan no follow']]}
 ]},
 {id:'media',label:'🖼 Media',title:'Media Website',desc:'Upload gambar langsung dari komputer. Pilih apakah gambar akan menjadi logo, Hero, favicon, gambar SEO, atau blok gambar di kanvas.',special:'media'},
 {id:'bagian',label:'🧩 Bagian Beranda',title:'Bagian dan Susunan Halaman',desc:'Tambahkan blok langsung ke kanvas. Klik teks dalam pratinjau untuk mengedit, pilih bagian untuk memindahkan atau menghapus.',special:'sections'},
 {id:'halaman',label:'📄 Halaman',title:'Kelola Halaman',desc:'Buka halaman untuk diedit atau buat halaman baru tanpa keluar dari Editor Visual.',special:'pages'},
 {id:'gambar',label:'🌄 Gambar Beranda',title:'Gambar untuk Beranda',desc:'Pilih halaman Beranda di dropdown bagian atas untuk menambahkan foto sebagai blok. Untuk banner utama, pilih “Hero Beranda” sebagai tujuan upload.',special:'homeimages'},
 {id:'tema',label:'🎨 Tema',title:'Tema dan Warna Website',desc:'Sesuaikan warna utama, warna header, dan footer. Perubahan masuk ke draft sampai dipublikasikan.',fields:[
  {key:'primary_color',label:'Warna utama',type:'color'},
  {key:'header_bg',label:'Warna latar header',type:'color'},
  {key:'footer_bg',label:'Warna latar footer',type:'color'},
  {key:'footer_text_color',label:'Warna tulisan footer',type:'color'}
 ]}
];
var modal, tabList, content, status, saveBtn, publishBtn, currentTitle;
function el(tag, attrs, child) {
 var node=document.createElement(tag);
 Object.keys(attrs||{}).forEach(function(k){if(k==='class')node.className=attrs[k];else if(k==='text')node.textContent=attrs[k];else node.setAttribute(k,attrs[k]);});
 if(child!=null)node.appendChild(typeof child==='string'?document.createTextNode(child):child);
 return node;
}
function injectStyle(){
 var style=document.createElement('style');
 style.textContent=
 '.cg-settings-overlay{position:fixed;inset:0;z-index:20000;background:rgba(10,25,48,.55);display:flex;align-items:center;justify-content:center;padding:16px}' +
 '.cg-settings-dialog{width:min(1120px,100%);height:min(780px,94vh);background:#f6f8fc;border:1px solid #d8e1ef;border-radius:18px;box-shadow:0 25px 90px rgba(10,25,48,.3);display:flex;flex-direction:column;overflow:hidden;color:#1f2937;font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}' +
 '.cg-settings-header{display:flex;align-items:center;gap:12px;padding:15px 18px;background:#fff;border-bottom:1px solid #dfe6f0}' +
 '.cg-settings-header h2{margin:0;font-size:18px}.cg-settings-header p{margin:2px 0 0;color:#66758b;font-size:12px}.cg-settings-header .cg-fill{flex:1}' +
 '.cg-settings-close{border:1px solid #d8e1ef;background:#fff;border-radius:9px;padding:8px 12px;font-size:19px;line-height:1;cursor:pointer}' +
 '.cg-settings-body{display:grid;grid-template-columns:205px minmax(0,1fr);min-height:0;flex:1}' +
 '.cg-settings-tabs{padding:12px 9px;overflow:auto;background:#f0f4fa;border-right:1px solid #dfe6f0;display:flex;flex-direction:column;gap:5px}' +
 '.cg-settings-tab{padding:10px 11px;text-align:left;border:1px solid transparent;border-radius:9px;background:transparent;color:#42536b;font:650 13px system-ui;cursor:pointer}' +
 '.cg-settings-tab:hover{background:#e3ecf9}.cg-settings-tab.active{background:#dfeaff;color:#0b57d0;border-color:#c4d8ff}' +
 '.cg-settings-content{padding:22px;overflow:auto;background:#fff}.cg-settings-content h3{margin:0 0 5px;font-size:20px}.cg-settings-description{margin:0 0 18px;color:#63748b;font-size:13px;max-width:720px}' +
 '.cg-settings-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}' +
 '.cg-settings-field{display:flex;flex-direction:column;gap:6px;min-width:0}.cg-settings-field.wide{grid-column:1/-1}.cg-settings-field>label,.cg-settings-label{font-size:12px;font-weight:750;color:#32445d}' +
 '.cg-settings-input,.cg-settings-field input[type=text],.cg-settings-field input[type=url],.cg-settings-field input[type=number],.cg-settings-field textarea,.cg-settings-field select{width:100%;max-width:100%;box-sizing:border-box;border:1px solid #ccd7e6;border-radius:9px;padding:10px 11px;font:14px system-ui;color:#1f2937;background:#fff}' +
 '.cg-settings-field textarea{min-height:100px;resize:vertical;line-height:1.55}.cg-settings-field input[type=color]{width:85px;height:42px;padding:4px;border:1px solid #ccd7e6;border-radius:9px;background:#fff}' +
 '.cg-settings-check{display:flex;gap:10px;align-items:center;padding:11px 12px;border:1px solid #e0e7f0;border-radius:10px;background:#fbfdff}.cg-settings-check input{width:18px;height:18px}' +
 '.cg-settings-image-row{display:flex;gap:7px;align-items:center}.cg-settings-image-row input{flex:1;min-width:0}.cg-settings-image-row button,.cg-settings-special button{border:1px solid #d1dceb;background:#f5f8fd;border-radius:8px;padding:9px 11px;font-weight:700;cursor:pointer;white-space:nowrap}' +
 '.cg-settings-special{display:grid;gap:13px}.cg-settings-note{border:1px solid #cbdcf8;background:#f4f8ff;border-radius:11px;padding:13px;color:#385371;font-size:13px;line-height:1.6}.cg-settings-actions-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.cg-settings-actions-grid button{white-space:normal;text-align:left;min-height:44px}' +
 '.cg-settings-upload{border:1px dashed #aac3e9;background:#f7faff;padding:15px;border-radius:12px;display:grid;gap:10px}.cg-settings-upload input[type=file]{width:100%;max-width:100%;box-sizing:border-box}.cg-settings-upload select{width:100%;border:1px solid #ccd7e6;border-radius:9px;background:#fff;padding:10px}.cg-settings-status{margin-top:12px;font-size:12px;color:#62748b;min-height:18px}.cg-settings-status[data-kind=error]{color:#b42318}.cg-settings-status[data-kind=success]{color:#137333}' +
 '.cg-settings-footer{display:flex;gap:9px;align-items:center;padding:13px 17px;background:#fff;border-top:1px solid #dfe6f0}.cg-settings-footer .cg-settings-status{flex:1;margin:0}.cg-settings-btn{border:0;border-radius:9px;padding:10px 14px;background:#e7edf6;color:#25364c;font:750 13px system-ui;cursor:pointer}.cg-settings-btn.primary{background:#155eef;color:#fff}.cg-settings-btn.green{background:#16803d;color:#fff}.cg-settings-btn:disabled{opacity:.55;cursor:wait}' +
 '@media(max-width:740px){.cg-settings-overlay{padding:5px}.cg-settings-dialog{height:96vh;border-radius:12px}.cg-settings-body{grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr)}.cg-settings-tabs{flex-direction:row;overflow-x:auto;border-right:0;border-bottom:1px solid #dfe6f0;padding:7px;gap:6px}.cg-settings-tab{white-space:nowrap;padding:8px 10px}.cg-settings-content{padding:15px}.cg-settings-fields{grid-template-columns:1fr}.cg-settings-field.wide{grid-column:auto}.cg-settings-footer{flex-wrap:wrap}.cg-settings-footer .cg-settings-status{flex-basis:100%;order:3}.cg-settings-actions-grid{grid-template-columns:1fr 1fr}.cg-settings-header h2{font-size:16px}}';
 document.head.appendChild(style);
}
function createModal(){
 modal=el('div',{class:'cg-settings-overlay',role:'dialog','aria-modal':'true','aria-label':'Pengaturan Website'});
 modal.style.display='flex';
 var dialog=el('div',{class:'cg-settings-dialog'});
 var header=el('div',{class:'cg-settings-header'});
 var titles=el('div',{});
 header.appendChild(titles);
 titles.appendChild(el('h2',{text:'⚙ Pengaturan Website'}));
 titles.appendChild(el('p',{text:'Satu tempat untuk mengatur seluruh website sekolah.'}));
 header.appendChild(el('div',{class:'cg-fill'}));
 var close=el('button',{class:'cg-settings-close',type:'button',title:'Tutup',text:'×'});
 close.addEventListener('click',closeModal);
 header.appendChild(close);
 var body=el('div',{class:'cg-settings-body'});
 tabList=el('nav',{class:'cg-settings-tabs','aria-label':'Kategori pengaturan'});
 content=el('section',{class:'cg-settings-content'});
 body.appendChild(tabList);body.appendChild(content);
 var footer=el('div',{class:'cg-settings-footer'});
 status=el('div',{class:'cg-settings-status','aria-live':'polite',text:'Memuat pengaturan…'});
 saveBtn=el('button',{class:'cg-settings-btn primary',type:'button',text:'💾 Simpan Draft Pengaturan'});
 publishBtn=el('button',{class:'cg-settings-btn green',type:'button',text:'🚀 Simpan & Publikasikan'});
 saveBtn.addEventListener('click',function(){saveSettings('save_draft');});
 publishBtn.addEventListener('click',function(){
  if(window.confirm('Publikasikan pengaturan website ini? Pengaturan aktif di website akan diperbarui.'))saveSettings('publish');
 });
 footer.appendChild(status);footer.appendChild(saveBtn);footer.appendChild(publishBtn);
 dialog.appendChild(header);dialog.appendChild(body);dialog.appendChild(footer);modal.appendChild(dialog);
 globalFileInput=el('input',{type:'file',id:'cg-settings-global-file',accept:'image/jpeg,image/png,image/webp,image/gif',style:'display:none'});
 globalFileInput.addEventListener('change',function(){if(globalFileInput.files&&globalFileInput.files[0])uploadSettingImage(globalFileInput,uploadTarget,null);});
 modal.appendChild(globalFileInput);
 modal.addEventListener('click',function(ev){if(ev.target===modal)closeModal();});
 document.addEventListener('keydown',function(ev){if(ev.key==='Escape'&&modal&&modal.isConnected)closeModal();});
 document.body.appendChild(modal);
 renderTabs();
}
function setStatus(message,kind){if(!status)return;status.textContent=message;status.dataset.kind=kind||'';}
function closeModal(){if(modal)modal.style.display='none';}
function renderTabs(){
 tabList.textContent='';
 categories.forEach(function(cat){
  var b=el('button',{class:'cg-settings-tab'+(cat.id===activeGroup?' active':''),type:'button',text:cat.label,'data-group':cat.id});
  b.addEventListener('click',function(){activeGroup=cat.id;renderTabs();renderGroup(cat.id);});
  tabList.appendChild(b);
 });
}
function fieldHtml(field){
 var val=values[field.key];
 if(val==null)val='';
 var wrap=el('div',{class:'cg-settings-field'+(['textarea','navlist'].includes(field.type)?' wide':'')});
 if(field.type==='checkbox'){
  var label=el('label',{class:'cg-settings-check'});
  var check=el('input',{type:'checkbox','data-key':field.key});
  check.checked=String(val)==='1'||String(val).toLowerCase()==='true';
  label.appendChild(check);label.appendChild(el('span',{text:field.label}));wrap.appendChild(label);return wrap;
 }
 wrap.appendChild(el('label',{for:'cg-setting-'+field.key,text:field.label}));
 var input;
 if(field.type==='textarea'){
  input=el('textarea',{id:'cg-setting-'+field.key,'data-key':field.key,placeholder:field.placeholder||'',rows:field.key==='navbar_items_text'?7:4});
  input.value=String(val);
 }else if(field.type==='select'){
  input=el('select',{id:'cg-setting-'+field.key,'data-key':field.key});
  (field.options||[]).forEach(function(opt){var o=el('option',{value:opt[0],text:opt[1]});input.appendChild(o);});
  input.value=String(val);
 }else if(field.type==='color'){
  input=el('input',{type:'color',id:'cg-setting-'+field.key,'data-key':field.key});
  input.value=/^#[0-9a-f]{6}$/i.test(String(val))?String(val):'#155eef';
 }else{
  input=el('input',{type:field.type==='number'?'number':(field.type==='url'?'url':'text'),id:'cg-setting-'+field.key,'data-key':field.key,placeholder:field.placeholder||''});
  input.value=String(val);
  if(field.type==='number'){input.min=field.key==='header_height'?'48':'24';input.max=field.key==='header_height'?'150':'140';}
 }
 wrap.appendChild(input);
 if(field.type==='image'){
  var row=el('div',{class:'cg-settings-image-row'});
  var upload=el('button',{type:'button',text:'📤 Upload'});
  upload.addEventListener('click',function(){startUpload(field.key);});
  row.appendChild(upload);wrap.appendChild(row);
 }
 return wrap;
}
function specialHtml(id){
 var box=el('div',{class:'cg-settings-special'});
 if(id==='profil'){
  box.appendChild(el('div',{class:'cg-settings-note',text:'Profil Sekolah, Profil Guru, dan halaman informasi dikelola sebagai halaman visual. Pilih halaman pada dropdown Editor Visual di bagian atas, lalu edit langsung di kanvas. Tab ini adalah pintasan ke halaman terkait.'}));
  var row=el('div',{class:'cg-settings-actions-grid'});
  [['Edit Profil Sekolah','profil'],['Edit Profil Guru','guru'],['Edit Informasi','informasi']].forEach(function(pair){
   var b=el('button',{type:'button',text:'↗ '+pair[0]});
   b.addEventListener('click',function(){window.location.href='/builder/?page='+encodeURIComponent(pair[1]);});
   row.appendChild(b);
  });box.appendChild(row);
 }
 if(id==='media'||id==='homeimages'){
  var note=id==='media'?'Pilih tujuan gambar. Setelah upload berhasil, URL akan masuk ke pengaturan yang dipilih.':'Pilih Beranda pada dropdown halaman sebelum memilih “Tambahkan sebagai blok gambar”. Untuk banner utama, pilih “Hero Beranda”.';
  box.appendChild(el('div',{class:'cg-settings-note',text:note}));
  var uploadBox=el('div',{class:'cg-settings-upload'});
  uploadBox.appendChild(el('label',{class:'cg-settings-label',text:'Pasang gambar sebagai'}));
  var target=el('select',{id:'cg-settings-upload-target'});
  [
   ['hero_image','Hero Beranda / gambar banner'],
   ['logo_url','Logo sekolah'],
   ['favicon_url','Favicon browser'],
   ['seo_og_image','Gambar pratinjau saat dibagikan'],
   ['page-block','Tambahkan sebagai blok gambar di kanvas']
  ].forEach(function(op){target.appendChild(el('option',{value:op[0],text:op[1]}));});
  target.value=id==='homeimages'?'hero_image':'hero_image';
  target.addEventListener('change',function(){uploadTarget=target.value;});
  uploadBox.appendChild(target);
  var file=el('input',{id:'cg-settings-upload-file',type:'file',accept:'image/jpeg,image/png,image/webp,image/gif'});
  uploadBox.appendChild(file);
  var up=el('button',{type:'button',text:'📤 Upload & Pasang Gambar'});
  up.addEventListener('click',function(){uploadSettingImage(file,target.value,up);});
  uploadBox.appendChild(up);
  var hint=el('div',{class:'cg-settings-status',id:'cg-settings-upload-status',text:'Format JPG, PNG, WEBP, GIF — maksimal 8 MB.'});
  uploadBox.appendChild(hint);
  var preview=el('img',{id:'cg-settings-upload-preview',alt:'Pratinjau gambar',style:'display:none;max-width:240px;max-height:150px;object-fit:cover;border-radius:9px;border:1px solid #dbe4f0'});
  uploadBox.appendChild(preview);box.appendChild(uploadBox);
  if(id==='media'){
   box.appendChild(el('div',{class:'cg-settings-note',text:'URL logo, favicon, Hero, dan gambar SEO juga bisa langsung disalin atau disunting pada tab Identitas, Beranda / Hero, dan SEO.'}));
  }else{
   var home=el('button',{type:'button',text:'🏠 Buka Beranda di Editor Visual'});
   home.addEventListener('click',function(){window.location.href='/builder/?page=home';});
   box.appendChild(home);
  }
 }
 if(id==='bagian'){
  box.appendChild(el('div',{class:'cg-settings-note',text:'Blok yang ditambahkan akan masuk ke halaman yang sedang dibuka. Untuk mengatur beranda, pilih “Beranda” di dropdown atas. Setelah mengedit, simpan draft halaman, lalu publikasikan.'}));
  var row=el('div',{class:'cg-settings-actions-grid'});
  [['heading','＋ Judul'],['text','¶ Teks'],['image','▧ Gambar'],['button','↗ Tombol / Tautan'],['columns','▥ Dua Kolom'],['divider','― Garis Pemisah'],['spacer','↕ Jarak']].forEach(function(pair){
   var b=el('button',{type:'button',text:pair[1]});
   b.addEventListener('click',function(){closeModal();var existing=document.querySelector('[data-insert="'+pair[0]+'"]');if(existing)existing.click();});
   row.appendChild(b);
  });
  box.appendChild(row);
 }
 if(id==='halaman'){
  box.appendChild(el('div',{class:'cg-settings-note',text:'Semua halaman dapat diedit pada kanvas. Halaman yang disimpan sebagai draft tidak mengubah website publik sebelum dipublikasikan.'}));
  var current=el('select',{id:'cg-settings-page-select'});
  var original=document.getElementById('pageSelect');
  if(original)Array.from(original.options).forEach(function(opt){current.appendChild(el('option',{value:opt.value,text:opt.textContent}));});
  if(original)current.value=original.value;
  var open=el('button',{type:'button',text:'Buka Halaman Terpilih'});
  open.addEventListener('click',function(){window.location.href='/builder/?page='+encodeURIComponent(current.value);});
  var pageRow=el('div',{class:'cg-settings-actions-grid'});pageRow.appendChild(current);pageRow.appendChild(open);box.appendChild(pageRow);
  var create=el('button',{type:'button',text:'＋ Buat Halaman Baru'});
  create.addEventListener('click',function(){closeModal();var title=document.getElementById('newTitle');if(title){title.focus();title.scrollIntoView({behavior:'smooth',block:'center'});}});
  box.appendChild(create);
 }
 return box;
}
function renderGroup(id){
 var cat=categories.find(function(x){return x.id===id;});
 if(!cat)return;
 content.textContent='';
 content.appendChild(el('h3',{text:cat.title}));
 content.appendChild(el('p',{class:'cg-settings-description',text:cat.desc||''}));
 if(cat.fields){
  var fields=el('div',{class:'cg-settings-fields'});
  cat.fields.forEach(function(field){fields.appendChild(fieldHtml(field));});
  content.appendChild(fields);
 }
 if(cat.special)content.appendChild(specialHtml(cat.special));
 if(id==='halaman'||id==='bagian'||id==='profil'){
  // These sections contain live canvas actions instead of a second content form.
 }
 content.querySelectorAll('[data-key]').forEach(function(input){
  input.addEventListener('input',function(){values[input.dataset.key]=input.type==='checkbox'?(input.checked?'1':'0'):input.value;dirty=true;});
  input.addEventListener('change',function(){values[input.dataset.key]=input.type==='checkbox'?(input.checked?'1':'0'):input.value;dirty=true;});
 });
}
function loadSettings(){
 loading=true;setStatus('Memuat pengaturan website…','');
 fetch('/builder/settings.php',{method:'GET',credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'})
 .then(function(r){return r.json().then(function(d){if(!r.ok||!d.ok)throw new Error(d.message||'Pengaturan gagal dimuat.');return d;});})
 .then(function(d){values=d.settings||{};dirty=false;renderGroup(activeGroup);setStatus('Pengaturan dimuat. Pilih tab, ubah nilai, lalu simpan.','success');})
 .catch(function(e){setStatus(e.message||'Pengaturan gagal dimuat.','error');})
 .finally(function(){loading=false;});
}
function saveSettings(action){
 if(loading)return;
 var form=new FormData();
 form.append('csrf_token',csrf);
 form.append('action',action);
 form.append('settings',JSON.stringify(values));
 loading=true;saveBtn.disabled=true;publishBtn.disabled=true;
 setStatus(action==='publish'?'Menyimpan dan memublikasikan…':'Menyimpan draft pengaturan…','');
 fetch('/builder/settings.php',{method:'POST',credentials:'same-origin',body:form,headers:{'Accept':'application/json'}})
 .then(function(r){return r.json().then(function(d){if(!r.ok||!d.ok)throw new Error(d.message||'Pengaturan gagal disimpan.');return d;});})
 .then(function(d){
  dirty=false;
  setStatus(d.message||'Pengaturan tersimpan.', 'success');
  if(action==='publish'){
   setTimeout(function(){var frame=document.getElementById('siteFrame');if(frame){var select=document.getElementById('pageSelect');var slug=select?select.value:'home';frame.src=(slug==='home'? '/?page=home':'/?page='+encodeURIComponent(slug))+'&cgvisual='+Date.now();}},200);
  }
 })
 .catch(function(e){setStatus(e.message||'Pengaturan gagal disimpan.','error');})
 .finally(function(){loading=false;saveBtn.disabled=false;publishBtn.disabled=false;});
}
function startUpload(key){
 var target=document.getElementById('cg-settings-upload-target');
 if(target)target.value=key;
 uploadTarget=key;
 if(globalFileInput){globalFileInput.value='';globalFileInput.click();}
}
function uploadSettingImage(fileInput,target,button){
 var file=fileInput&&fileInput.files&&fileInput.files[0];
 if(!file){setStatus('Pilih file gambar terlebih dahulu.','error');return;}
 if(file.size>8*1024*1024){setStatus('Ukuran gambar maksimal 8 MB.','error');return;}
 if(!/^image\/(jpeg|png|webp|gif)$/i.test(file.type)){setStatus('Format gambar harus JPG, PNG, WEBP, atau GIF.','error');return;}
 var uploadStatus=document.getElementById('cg-settings-upload-status');
 var preview=document.getElementById('cg-settings-upload-preview');
 if(button)button.disabled=true;
 if(uploadStatus){uploadStatus.textContent='Sedang mengunggah gambar…';uploadStatus.dataset.kind='';}
 var form=new FormData();form.append('csrf_token',csrf);form.append('image',file,file.name);
 fetch('/admin/tampilan/upload_page_image.php',{method:'POST',credentials:'same-origin',body:form,headers:{'Accept':'application/json'}})
 .then(function(r){return r.json().then(function(d){if(!r.ok||!d.ok)throw new Error(d.message||'Upload gambar gagal.');return d;});})
 .then(function(d){
  if(target==='page-block'){
   if(typeof window.CGVisualBuilderAddImage==='function')window.CGVisualBuilderAddImage(d.url,file.name);
   else throw new Error('Kanvas belum siap. Pilih ulang halaman lalu coba lagi.');
  }else{
   values[target]=d.url;dirty=true;
   var field=document.querySelector('[data-key="'+target+'"]');
   if(field)field.value=d.url;
   if(target==='hero_image'&&typeof window.CGVisualBuilderApplyHeroImage==='function')window.CGVisualBuilderApplyHeroImage(d.url);
  }
  if(preview){preview.src=d.url;preview.style.display='block';}
  if(uploadStatus){uploadStatus.textContent='✓ '+d.message+' Klik Simpan Draft Pengaturan atau Simpan & Publikasikan.';uploadStatus.dataset.kind='success';}
  setStatus('Gambar diunggah. Perubahan URL belum dipublikasikan.','success');
 })
 .catch(function(e){if(uploadStatus){uploadStatus.textContent=e.message||'Upload gagal.';uploadStatus.dataset.kind='error';}setStatus(e.message||'Upload gagal.','error');})
 .finally(function(){if(button)button.disabled=false;});
}
injectStyle();
trigger.addEventListener('click',function(ev){ev.preventDefault();if(!modal)createModal();modal.style.display='flex';loadSettings();});
})();