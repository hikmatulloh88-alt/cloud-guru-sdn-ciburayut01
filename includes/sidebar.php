<?php
declare(strict_types=1);

/* Cloud Guru sidebar navigation. Keep links aligned with deployed portal routes. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$cgRole = strtolower(trim((string)($_SESSION['role'] ?? 'guru')));
$cgIsAdmin = in_array($cgRole, ['admin', 'administrator'], true);
$cgName = $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'Pengguna';
$cgPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if (!function_exists('cg_sidebar_e')) {
    function cg_sidebar_e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('cg_sidebar_active')) {
    function cg_sidebar_active(array $paths, string $currentPath): bool {
        foreach ($paths as $item) {
            $item = (string)$item;
            if ($currentPath === $item || ($item !== '/' && strpos($currentPath, rtrim($item, '/') . '/') === 0)) {
                return true;
            }
        }
        return false;
    }
}
if (!function_exists('cg_sidebar_link')) {
    function cg_sidebar_link(string $url, string $icon, string $label, array $activePaths = []): void {
        global $cgPath;
        $targetPath = parse_url($url, PHP_URL_PATH) ?: $url;
        $active = false;

        // Storage's four folder menus share a route, so compare the folder query too.
        if ($targetPath === '/storage/index.php') {
            $targetQuery = [];
            $currentQuery = [];
            parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $targetQuery);
            parse_str((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY) ?? ''), $currentQuery);
            $targetFolder = (string)($targetQuery['folder'] ?? 'root_folder_id');
            $currentFolder = (string)($currentQuery['folder'] ?? 'root_folder_id');
            $active = ($cgPath === $targetPath && $targetFolder === $currentFolder);
        } else {
            $paths = $activePaths ?: [$targetPath];
            $active = cg_sidebar_active($paths, $cgPath);
        }

        echo '<a class="cg-nav-link' . ($active ? ' is-active' : '') . '" href="' . cg_sidebar_e($url) . '"' .
             ($active ? ' aria-current="page"' : '') . '>';
        echo '<span class="cg-nav-icon" aria-hidden="true">' . cg_sidebar_e($icon) . '</span>';
        echo '<span class="cg-nav-label">' . cg_sidebar_e($label) . '</span></a>';
    }
}
?>
<style>
:root{--cg-sidebar-width:250px;--cg-blue:#1a73e8;--cg-blue-light:rgba(74,144,226,.28);--cg-text:#f4f7ff;--cg-muted:#c4d2e8;--cg-border:rgba(220,232,255,.14);--cg-sidebar-bg:#102447}
.cg-sidebar{position:fixed;inset:0 auto 0 0;z-index:1200;width:var(--cg-sidebar-width);box-sizing:border-box;display:flex;flex-direction:column;padding:16px 12px;background:linear-gradient(180deg,#10213f 0%,#142b4d 100%);border-right:1px solid rgba(255,255,255,.08);color:#f4f7ff;font-family:Arial,Helvetica,sans-serif;transition:width .2s ease,transform .2s ease;overflow-y:auto;overscroll-behavior:contain}
.cg-brand{display:flex;align-items:center;gap:11px;padding:7px 10px 20px;min-height:48px;box-sizing:border-box}
.cg-brand-logo{display:grid;place-items:center;width:39px;height:39px;flex:0 0 39px;border-radius:12px;background:#213e68;color:#8ec0ff;font-size:23px}
.cg-brand-name{font-size:18px;font-weight:700;letter-spacing:-.4px;color:#fff}
.cg-brand-sub{display:block;margin-top:4px;color:#c4d2e8;font-size:11px}
.cg-user{display:flex;align-items:center;gap:10px;margin:0 4px 18px;padding:12px 9px;border:1px solid rgba(220,232,255,.14);border-radius:13px;min-width:0;background:rgba(255,255,255,.025)}
.cg-avatar{display:grid;place-items:center;flex:0 0 35px;width:35px;height:35px;border-radius:11px;background:#ffc54d;color:#172033;font-size:16px;font-weight:700}
.cg-user-info{min-width:0}.cg-user-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:600;color:#fff}.cg-user-role{margin-top:4px;color:#c4d2e8;font-size:11px}
.cg-section-title{margin:10px 10px 8px;color:#9db5d8;font-size:10px;font-weight:700;letter-spacing:1px}
.cg-nav{display:flex;flex-direction:column;gap:4px}
.cg-nav-link{display:flex;align-items:center;gap:13px;min-height:43px;box-sizing:border-box;padding:10px 13px;border-radius:12px;color:#d6e2f5;text-decoration:none;font-size:13px;font-weight:500;transition:background .15s ease,color .15s ease;cursor:pointer}
.cg-nav-link:hover{background:rgba(255,255,255,.10);color:#fff}.cg-nav-link.is-active{background:#244875;color:#fff;font-weight:700}.cg-nav-icon{display:inline-grid;place-items:center;flex:0 0 20px;width:20px;font-size:17px;color:#a9c8f5}.cg-nav-link.is-active .cg-nav-icon{color:#fff}.cg-nav-label{overflow-wrap:anywhere}
.cg-divider{height:1px;margin:15px 8px;background:rgba(255,255,255,.12)}.cg-bottom{margin-top:auto;padding-top:15px}.cg-logout{color:#ffd8d6!important}.cg-logout:hover{background:rgba(255,255,255,.10)}
.cg-sidebar-toggle{display:grid;place-items:center;width:34px;height:34px;border:1px solid rgba(255,255,255,.12);border-radius:9px;background:rgba(255,255,255,.06);color:#fff;font-size:18px;line-height:1;cursor:pointer;touch-action:manipulation}
.cg-mobile-bar{display:none}
.cg-overlay{display:none}
body.cg-sidebar-ready{--cg-content-offset:250px}
body.cg-sidebar-ready .cg-sidebar{width:var(--cg-sidebar-width)}
body.cg-sidebar-ready.cg-sidebar-collapsed{--cg-content-offset:76px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-sidebar{width:76px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand-text,body.cg-sidebar-ready.cg-sidebar-collapsed .cg-user-info,body.cg-sidebar-ready.cg-sidebar-collapsed .cg-section-title,body.cg-sidebar-ready.cg-sidebar-collapsed .cg-nav-label{display:none}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand{padding-left:8px;padding-right:8px;justify-content:center}.cg-brand-text{min-width:0}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand .cg-sidebar-toggle{display:none}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-user{justify-content:center;margin-left:0;margin-right:0;padding:8px 0}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-nav-link{justify-content:center;padding:10px 7px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-divider{margin-left:4px;margin-right:4px}
body.cg-sidebar-ready .cg-main-content{margin-left:var(--cg-content-offset);transition:margin-left .2s ease}
@media(min-width:901px){body.cg-sidebar-ready .cg-main-content{margin-left:var(--cg-content-offset)}}
@media(max-width:900px){
 .cg-sidebar{transform:translateX(-105%);width:280px!important;max-width:84vw;z-index:10002}
 body.cg-mobile-open .cg-sidebar{transform:translateX(0)}
 .cg-mobile-bar{display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10001;min-height:58px;padding:8px 14px;background:#10213f;color:#fff;box-shadow:0 3px 12px rgba(0,0,0,.12)}
 .cg-mobile-title{font-weight:700}
 .cg-overlay{position:fixed;inset:0;z-index:10000;background:rgba(7,18,35,.5)}
 body.cg-mobile-open .cg-overlay{display:block}
 body.cg-sidebar-ready .cg-main-content{margin-left:0!important}
 body.cg-sidebar-ready.cg-sidebar-collapsed .cg-sidebar{width:280px!important}
}
@media(max-width:420px){.cg-sidebar{padding:12px 10px}.cg-mobile-bar{padding:8px 12px}}
.cg-sidebar a,.cg-sidebar button{touch-action:manipulation;-webkit-tap-highlight-color:transparent}
</style>

<div class="cg-mobile-bar">
  <button class="cg-sidebar-toggle" type="button" onclick="cgToggleMobile()" aria-label="Buka menu">☰</button>
  <span class="cg-mobile-title">Cloud Guru</span>
</div>

<aside class="cg-sidebar" id="cgSidebar">
  <div class="cg-brand">
    <div class="cg-brand-logo" aria-hidden="true">☁</div>
    <div class="cg-brand-text"><div class="cg-brand-name">Cloud Guru</div><span class="cg-brand-sub">SDN Ciburayut 01</span></div>
    <button class="cg-sidebar-toggle" type="button" onclick="cgToggleDesktop()" aria-label="Ciutkan menu" style="margin-left:auto">‹</button>
  </div>

  <div class="cg-user">
    <div class="cg-avatar" aria-hidden="true"><?= cg_sidebar_e(mb_strtoupper(mb_substr(trim((string)$cgName), 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
    <div class="cg-user-info"><div class="cg-user-name"><?= cg_sidebar_e($cgName) ?></div><div class="cg-user-role"><?= $cgIsAdmin ? 'Administrator' : 'Guru' ?></div></div>
  </div>

  <nav class="cg-nav" aria-label="Menu utama">
    <?php
    cg_sidebar_link('/dashboard/index.php', '⌂', 'Dashboard', ['/dashboard']);
    cg_sidebar_link('/storage/index.php', '▣', 'Penyimpanan Saya', ['/storage']);
    ?>
    <div class="cg-section-title">RUANG KERJA</div>
    <?php
    cg_sidebar_link('/storage/index.php?folder=administrasi_folder_id', '▤', 'Administrasi Guru');
    cg_sidebar_link('/storage/index.php?folder=perencanaan_folder_id', '▧', 'Perencanaan');
    cg_sidebar_link('/storage/index.php?folder=asesmen_folder_id', '☑', 'Asesmen');
    ?>
    <div class="cg-divider"></div>
    <div class="cg-section-title">AKUN SAYA</div>
    <?php cg_sidebar_link('/guru/', '♙', $cgIsAdmin ? 'Profil Guru' : 'Profil Saya', ['/guru']); ?>
    <?php if ($cgIsAdmin): ?>
      <div class="cg-divider"></div><div class="cg-section-title">ADMINISTRASI</div>
      <?php
      cg_sidebar_link('/admin/guru/', '♧', 'Kelola Profil Guru', ['/admin/guru']);
      cg_sidebar_link('/admin/tampilan/', '▦', 'Website Sekolah', ['/admin/tampilan']);
      cg_sidebar_link('/absensi_sd/modules/pengguna/index.php', '⚿', 'Akun Guru', ['/absensi_sd/modules/pengguna']);
      cg_sidebar_link('/self-healing/admin.php', '⛨', 'Self-Healing', ['/self-healing']);
      ?>
    <?php endif; ?>
  </nav>

  <div class="cg-bottom"><a class="cg-nav-link cg-logout" href="/auth/logout.php"><span class="cg-nav-icon" aria-hidden="true">↪</span><span class="cg-nav-label">Keluar</span></a></div>
</aside>
<div class="cg-overlay" onclick="cgToggleMobile()"></div>

<script>
(function(){
  document.body.classList.add('cg-sidebar-ready');
  try { if (localStorage.getItem('cgSidebarCollapsed') === '1') document.body.classList.add('cg-sidebar-collapsed'); } catch(e) {}
  window.cgToggleDesktop = function(){
    if (window.innerWidth <= 900) { window.cgToggleMobile(); return; }
    document.body.classList.toggle('cg-sidebar-collapsed');
    try { localStorage.setItem('cgSidebarCollapsed', document.body.classList.contains('cg-sidebar-collapsed') ? '1' : '0'); } catch(e) {}
  };
  window.cgToggleMobile = function(){ document.body.classList.toggle('cg-mobile-open'); };
  document.addEventListener('keydown', function(event){ if(event.key === 'Escape') document.body.classList.remove('cg-mobile-open'); });
  document.addEventListener('click', function(event){
    var link = event.target.closest('.cg-sidebar a');
    if (link && window.innerWidth <= 900) document.body.classList.remove('cg-mobile-open');
  });
})();
</script>
