<?php
declare(strict_types=1);

/* Cloud Guru unified sidebar navigation. Keep links aligned with deployed portal routes. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$cgRole = strtolower(trim((string)($_SESSION['role'] ?? 'guru')));
$cgIsAdmin = in_array($cgRole, ['admin', 'administrator'], true);
$cgName = trim((string)($_SESSION['user_name'] ?? $_SESSION['nama'] ?? $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'Pengguna')) ?: 'Pengguna';
$cgPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if (!function_exists('cg_sidebar_e')) {
    function cg_sidebar_e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cg_sidebar_svg')) {
    function cg_sidebar_svg(string $name): string {
        $icons = [
            'cloud' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>',
            'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/><path d="M9 20v-6h6v6"/></svg>',
            'folder' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>',
            'briefcase' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',
            'check-square' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'sparkles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
            'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.8-3.6 5-5.5 8-5.5s6.2 1.9 8 5.5"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 19.5c1.5-3 4-4.5 6.5-4.5s5 1.5 6.5 4.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17.5 14.5c2 .4 3.5 1.7 4.5 3.8"/></svg>',
            'layout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
            'key' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M17 6l3 3M14.5 8.5l2.5 2.5"/></svg>',
            'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>',
            'globe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        ];
        return $icons[$name] ?? $icons['folder'];
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
    function cg_sidebar_link(string $url, string $iconName, string $label, array $activePaths = [], string $badge = ''): void {
        global $cgPath;
        $targetPath = parse_url($url, PHP_URL_PATH) ?: $url;
        $active = false;

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

        echo '<a class="cg-nav-link' . ($active ? ' is-active' : '') . '" href="' . cg_sidebar_e($url) . '" title="' . cg_sidebar_e($label) . '"' .
             ($active ? ' aria-current="page"' : '') . '>';
        echo '<span class="cg-nav-icon" aria-hidden="true">' . cg_sidebar_svg($iconName) . '</span>';
        echo '<span class="cg-nav-label">' . cg_sidebar_e($label) . '</span>';
        if ($badge !== '') {
            echo '<span class="cg-nav-badge">' . cg_sidebar_e($badge) . '</span>';
        }
        echo '</a>';
    }
}

$cgInitial = strtoupper(function_exists('mb_substr') ? mb_substr($cgName, 0, 1, 'UTF-8') : substr($cgName, 0, 1));
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --cg-sidebar-width:264px;
  --cg-font:'Plus Jakarta Sans',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  --cg-text:#f8fafc;
  --cg-muted:#94a3b8;
  --cg-border:rgba(255,255,255,.12);
}
.cg-sidebar{
  position:fixed;inset:0 auto 0 0;z-index:1200;width:var(--cg-sidebar-width);box-sizing:border-box;
  display:flex;flex-direction:column;padding:18px 14px;
  background:radial-gradient(circle at 15% 0%,rgba(56,189,248,.16),transparent 42%),linear-gradient(175deg,#091936 0%,#0f2752 55%,#0b1e40 100%);
  border-right:1px solid rgba(255,255,255,.12);
  box-shadow:12px 0 40px rgba(7,18,40,.22);
  color:var(--cg-text);font-family:var(--cg-font);
  transition:width .22s cubic-bezier(.4,0,.2,1),transform .24s cubic-bezier(.4,0,.2,1);
  overflow-y:auto;overscroll-behavior:contain;
}
.cg-sidebar::-webkit-scrollbar{width:5px}
.cg-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:99px}
.cg-brand{display:flex;align-items:center;gap:11px;padding:4px 6px 18px;min-height:48px;box-sizing:border-box}
.cg-brand-logo{
  display:grid;place-items:center;width:40px;height:40px;flex:0 0 40px;
  border:1px solid rgba(125,211,252,.35);border-radius:13px;
  background:linear-gradient(135deg,rgba(56,189,248,.28),rgba(37,99,235,.22));
  box-shadow:inset 0 1px rgba(255,255,255,.3),0 8px 20px rgba(2,12,32,.25);
  color:#bae6fd;
}
.cg-brand-logo svg{width:21px;height:21px}
.cg-brand-name{font-size:17px;font-weight:800;letter-spacing:-.03em;color:#fff;line-height:1.15}
.cg-brand-sub{display:block;margin-top:2px;color:#93c5fd;font-size:11px;font-weight:600}
.cg-user{
  display:flex;align-items:center;gap:11px;margin:0 2px 18px;padding:11px 12px;
  border:1px solid rgba(255,255,255,.13);border-radius:16px;min-width:0;
  background:linear-gradient(135deg,rgba(255,255,255,.11),rgba(255,255,255,.04));
  box-shadow:inset 0 1px rgba(255,255,255,.12);
}
.cg-avatar{
  display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;
  border:1px solid rgba(255,255,255,.35);border-radius:12px;
  background:linear-gradient(135deg,#38bdf8,#2563eb);
  color:#fff;font-size:15px;font-weight:800;box-shadow:0 6px 16px rgba(37,99,235,.32);
}
.cg-user-info{min-width:0;flex:1}
.cg-user-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:700;color:#fff}
.cg-user-role{
  display:inline-flex;align-items:center;gap:5px;margin-top:3px;
  color:#bae6fd;font-size:11px;font-weight:600;
}
.cg-user-role:before{content:"";width:6px;height:6px;border-radius:50%;background:#34d399}
.cg-section-title{margin:12px 10px 6px;color:#7dd3fc;opacity:.75;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}
.cg-nav{display:flex;flex-direction:column;gap:3px}
.cg-nav-link{
  display:flex;align-items:center;gap:12px;min-height:42px;box-sizing:border-box;padding:9px 12px;
  border:1px solid transparent;border-radius:12px;color:#cbd5e1;text-decoration:none;
  font-size:13.5px;font-weight:500;transition:all .18s ease;cursor:pointer;
}
.cg-nav-link:hover{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.08);color:#fff;transform:translateX(2px)}
.cg-nav-link.is-active{
  border-color:rgba(125,211,252,.32);
  background:linear-gradient(110deg,rgba(56,189,248,.24),rgba(59,130,246,.16));
  box-shadow:inset 0 1px rgba(255,255,255,.16),0 6px 18px rgba(4,14,35,.2);
  color:#fff;font-weight:700;
}
.cg-nav-icon{display:inline-grid;place-items:center;flex:0 0 20px;width:20px;height:20px;color:#93c5fd;transition:color .18s ease}
.cg-nav-icon svg{width:18px;height:18px}
.cg-nav-link:hover .cg-nav-icon,.cg-nav-link.is-active .cg-nav-icon{color:#fff}
.cg-nav-label{flex:1;min-width:0;overflow-wrap:anywhere}
.cg-nav-badge{
  padding:2px 7px;border-radius:999px;background:rgba(56,189,248,.22);border:1px solid rgba(125,211,252,.3);
  color:#bae6fd;font-size:10px;font-weight:800;letter-spacing:.03em;
}
.cg-divider{height:1px;margin:12px 8px;background:rgba(255,255,255,.1)}
.cg-bottom{margin-top:auto;padding-top:14px;display:flex;flex-direction:column;gap:4px}
.cg-logout{color:#fecaca!important}
.cg-logout .cg-nav-icon{color:#fca5a5}
.cg-logout:hover{background:rgba(239,68,68,.16);border-color:rgba(248,113,113,.25);color:#fff!important}
.cg-sidebar-toggle{
  display:grid;place-items:center;width:32px;height:32px;border:1px solid rgba(255,255,255,.16);
  border-radius:10px;background:rgba(255,255,255,.08);color:#e2e8f0;font-size:16px;line-height:1;
  cursor:pointer;touch-action:manipulation;transition:background .18s ease,color .18s ease;
}
.cg-sidebar-toggle:hover{background:rgba(255,255,255,.16);color:#fff}
.cg-mobile-bar{display:none}
.cg-overlay{display:none}

body.cg-sidebar-ready{--cg-content-offset:264px}
body.cg-sidebar-ready .cg-sidebar{width:var(--cg-sidebar-width)}
body.cg-sidebar-ready.cg-sidebar-collapsed{--cg-content-offset:78px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-sidebar{width:78px;padding-left:10px;padding-right:10px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand-text,
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-user-info,
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-section-title,
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-nav-label,
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-nav-badge{display:none}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand{padding-left:0;padding-right:0;justify-content:center;cursor:pointer}
.cg-brand-text{min-width:0}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-brand .cg-sidebar-toggle{display:none}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-user{justify-content:center;margin-left:0;margin-right:0;padding:8px 0}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-nav-link{justify-content:center;padding:10px 8px}
body.cg-sidebar-ready.cg-sidebar-collapsed .cg-divider{margin-left:4px;margin-right:4px}
body.cg-sidebar-ready .cg-main-content{margin-left:var(--cg-content-offset);transition:margin-left .22s cubic-bezier(.4,0,.2,1)}

@media(min-width:901px){
  body.cg-sidebar-ready .cg-main-content{margin-left:var(--cg-content-offset)}
}
@media(max-width:900px){
  .cg-sidebar{transform:translateX(-105%);width:284px!important;max-width:85vw;z-index:10002}
  body.cg-mobile-open .cg-sidebar{transform:translateX(0)}
  .cg-mobile-bar{
    display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:10001;
    min-height:60px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,.14);
    background:linear-gradient(120deg,#0a1e42,#10336e);color:#fff;
    box-shadow:0 8px 25px rgba(7,18,40,.16);font-family:var(--cg-font);
  }
  .cg-mobile-left{display:flex;align-items:center;gap:11px}
  .cg-mobile-title{font-weight:800;font-size:15px;letter-spacing:-.02em}
  .cg-mobile-sub{font-size:11px;color:#93c5fd;font-weight:600}
  .cg-overlay{position:fixed;inset:0;z-index:10000;background:rgba(6,15,34,.55);backdrop-filter:blur(3px)}
  body.cg-mobile-open .cg-overlay{display:block}
  body.cg-sidebar-ready .cg-main-content{margin-left:0!important}
  body.cg-sidebar-ready.cg-sidebar-collapsed .cg-sidebar{width:284px!important}
}
.cg-sidebar a,.cg-sidebar button{touch-action:manipulation;-webkit-tap-highlight-color:transparent}
</style>

<div class="cg-mobile-bar">
  <div class="cg-mobile-left">
    <button class="cg-sidebar-toggle" type="button" onclick="cgToggleMobile()" aria-label="Buka menu navigasi">☰</button>
    <div>
      <div class="cg-mobile-title">Cloud Guru</div>
      <div class="cg-mobile-sub">SDN Ciburayut 01</div>
    </div>
  </div>
  <a href="/dashboard/index.php" style="color:#bae6fd;text-decoration:none;font-size:12px;font-weight:700;padding:6px 10px;border-radius:9px;background:rgba(255,255,255,.1)">Dashboard</a>
</div>

<aside class="cg-sidebar" id="cgSidebar">
  <div class="cg-brand" onclick="if(document.body.classList.contains('cg-sidebar-collapsed'))cgToggleDesktop()" title="Cloud Guru SDN Ciburayut 01">
    <div class="cg-brand-logo" aria-hidden="true"><?= cg_sidebar_svg('cloud') ?></div>
    <div class="cg-brand-text">
      <div class="cg-brand-name">Cloud Guru</div>
      <span class="cg-brand-sub">SDN Ciburayut 01</span>
    </div>
    <button class="cg-sidebar-toggle" type="button" onclick="event.stopPropagation();cgToggleDesktop()" aria-label="Ciutkan menu" style="margin-left:auto">‹</button>
  </div>

  <div class="cg-user">
    <div class="cg-avatar" aria-hidden="true"><?= cg_sidebar_e($cgInitial) ?></div>
    <div class="cg-user-info">
      <div class="cg-user-name"><?= cg_sidebar_e($cgName) ?></div>
      <div class="cg-user-role"><?= $cgIsAdmin ? 'Administrator' : 'Guru' ?></div>
    </div>
  </div>

  <nav class="cg-nav" aria-label="Menu utama">
    <?php
    cg_sidebar_link('/dashboard/index.php', 'home', 'Dashboard', ['/dashboard']);
    cg_sidebar_link('/storage/index.php', 'folder', 'Penyimpanan Saya', ['/storage']);
    ?>
    <div class="cg-section-title">Ruang Kerja</div>
    <?php
    cg_sidebar_link('/storage/index.php?folder=administrasi_folder_id', 'briefcase', 'Administrasi Guru');
    cg_sidebar_link('/storage/index.php?folder=perencanaan_folder_id', 'calendar', 'Perencanaan');
    cg_sidebar_link('/storage/index.php?folder=asesmen_folder_id', 'check-square', 'Asesmen');
    cg_sidebar_link('/ai-studio/index.php', 'sparkles', 'AI Studio', ['/ai-studio'], 'AI');
    ?>
    <div class="cg-divider"></div>
    <div class="cg-section-title">Akun Saya</div>
    <?php cg_sidebar_link('/guru/', 'user', $cgIsAdmin ? 'Profil Guru' : 'Profil Saya', ['/guru']); ?>
    <?php if ($cgIsAdmin): ?>
      <div class="cg-divider"></div>
      <div class="cg-section-title">Administrasi</div>
      <?php
      cg_sidebar_link('/admin/guru/', 'users', 'Kelola Profil Guru', ['/admin/guru']);
      cg_sidebar_link('/admin/tampilan/', 'layout', 'Website Sekolah', ['/admin/tampilan']);
      cg_sidebar_link('/absensi_sd/modules/pengguna/index.php', 'key', 'Akun Guru', ['/absensi_sd/modules/pengguna']);
      cg_sidebar_link('/self-healing/admin.php', 'shield', 'Self-Healing', ['/self-healing']);
      ?>
    <?php endif; ?>
  </nav>

  <div class="cg-bottom">
    <a class="cg-nav-link" href="/?page=home" target="_blank" rel="noopener" title="Buka Portal Publik Sekolah">
      <span class="cg-nav-icon" aria-hidden="true"><?= cg_sidebar_svg('globe') ?></span>
      <span class="cg-nav-label">Portal Sekolah</span>
    </a>
    <a class="cg-nav-link cg-logout" href="/auth/logout.php" title="Keluar dari akun">
      <span class="cg-nav-icon" aria-hidden="true"><?= cg_sidebar_svg('logout') ?></span>
      <span class="cg-nav-label">Keluar</span>
    </a>
  </div>
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
