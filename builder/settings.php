<?php
declare(strict_types=1);

/*
 * Cloud Guru Visual Editor settings API.
 * Only existing, public-facing website settings are writable here.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';

wajib_login();
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['admin', 'administrator'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'message'=>'Pengaturan hanya untuk Administrator.']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function cgvs_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function cgvs_allowed_settings(): array {
    return [
        'site_name' => 'SDN CIBURAYUT 01',
        'tagline' => 'Portal Informasi Sekolah',
        'logo_url' => '',
        'favicon_url' => '',
        'primary_color' => '#155eef',
        'hero_image' => '',
        'hero_eyebrow' => 'PORTAL INFORMASI SEKOLAH',
        'hero_title' => 'Sekolah Ramah Anak, Berkarakter, dan Berbudaya Digital',
        'hero_subtitle' => 'Portal resmi sekolah untuk informasi, kegiatan, profil guru, layanan pendidikan, dan berbagai informasi terkini.',
        'hero_button_text' => 'Jelajahi Portal',
        'hero_button_url' => '/?page=informasi',
        'hero_button2_text' => 'Profil Sekolah',
        'hero_button2_url' => '/?page=profil',
        'navbar_enabled' => '1',
        'navbar_items' => '[{"label":"Beranda","url":"/?page=home"},{"label":"Profil","url":"/?page=profil"},{"label":"Informasi","url":"/?page=informasi"},{"label":"Guru","url":"/?page=guru"},{"label":"Galeri","url":"/?page=galeri"}]',
        'navbar_cta_text' => '☁️ Masuk Cloud Guru',
        'navbar_cta_url' => '/auth/login.php',
        'navbar_style' => 'glass',
        'header_height' => '78',
        'header_sticky' => '1',
        'header_transparent' => '0',
        'header_bg' => '#ffffff',
        'header_overlay' => '0',
        'header_logo_width' => '46',
        'header_align' => 'between',
        'address' => 'Cigombong, Kabupaten Bogor',
        'phone' => '',
        'email' => 'info@sdnciburayut01.com',
        'whatsapp' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'youtube_url' => '',
        'maps_embed_url' => '',
        'maps_link' => '',
        'footer_text' => '© ' . date('Y') . ' SDN Ciburayut 01. Semua hak dilindungi.',
        'footer_quicklinks' => "Beranda|/?page=home\nProfil Sekolah|/?page=profil\nInformasi|/?page=informasi\nProfil Guru|/?page=guru\nGaleri|/?page=galeri",
        'footer_bg' => '#102a43',
        'footer_layout' => 'columns',
        'footer_show_contact' => '1',
        'footer_show_logo' => '1',
        'footer_show_quicklinks' => '1',
        'footer_show_social' => '1',
        'footer_text_color' => '#ffffff',
        'seo_title' => '',
        'seo_description' => 'Portal resmi SDN Ciburayut 01. Informasi sekolah, berita, agenda, profil guru, galeri, dan layanan digital.',
        'seo_keywords' => 'SDN Ciburayut 01, sekolah dasar, Ciburayut, Cigombong, Bogor, portal sekolah',
        'seo_author' => 'SDN Ciburayut 01',
        'seo_og_image' => '',
        'seo_canonical' => '',
        'seo_robots' => 'index,follow',
    ];
}
function cgvs_columns(PDO $pdo): array {
    $cols = [];
    $q = $pdo->query('SHOW COLUMNS FROM website_builder_settings');
    foreach ($q ?: [] as $row) $cols[(string)$row['Field']] = true;
    return $cols;
}
function cgvs_ensure_drafts(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS website_builder_visual_settings_drafts (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function cgvs_valid_url(string $value, bool $allowMailTel = false): bool {
    $value = trim($value);
    if ($value === '') return true;
    if (preg_match('#^(javascript|data|vbscript):#i', $value)) return false;
    if (preg_match('#^https?://#i', $value)) return (bool)filter_var($value, FILTER_VALIDATE_URL);
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) return true;
    if (str_starts_with($value, '?') || str_starts_with($value, '#')) return true;
    if ($allowMailTel && preg_match('#^(mailto:|tel:)#i', $value)) return true;
    return false;
}
$defaults = cgvs_allowed_settings();
try {
    $cols = cgvs_columns($pdo);
    if (!isset($cols['setting_key'], $cols['setting_value'])) {
        cgvs_json(503, ['ok'=>false,'message'=>'Struktur tabel website_builder_settings belum sesuai.']);
    }
} catch (Throwable $e) {
    error_log('Cloud Guru visual settings schema: ' . $e->getMessage());
    cgvs_json(503, ['ok'=>false,'message'=>'Pengaturan website belum dapat dibaca. Periksa koneksi database.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $values = $defaults;
    try {
        $q = $pdo->query('SELECT setting_key, setting_value FROM website_builder_settings');
        foreach ($q ?: [] as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if (array_key_exists($key, $defaults)) $values[$key] = (string)($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        error_log('Cloud Guru visual settings read: ' . $e->getMessage());
        cgvs_json(500, ['ok'=>false,'message'=>'Pengaturan tidak dapat dimuat.']);
    }
    try {
        cgvs_ensure_drafts($pdo);
        $draftRows = $pdo->query('SELECT setting_key, setting_value FROM website_builder_visual_settings_drafts')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($draftRows as $draftRow) {
            $draftKey = (string)($draftRow['setting_key'] ?? '');
            if (array_key_exists($draftKey, $defaults)) $values[$draftKey] = (string)($draftRow['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        error_log('Cloud Guru visual settings draft read: ' . $e->getMessage());
    }
    $navItems = json_decode((string)$values['navbar_items'], true);
    if (!is_array($navItems)) $navItems = json_decode($defaults['navbar_items'], true) ?: [];
    $values['navbar_items_text'] = implode("\n", array_map(static function($item): string {
        return trim((string)($item['label'] ?? '')) . '|' . trim((string)($item['url'] ?? ''));
    }, $navItems));
    cgvs_json(200, ['ok'=>true,'settings'=>$values]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: GET, POST');
    cgvs_json(405, ['ok'=>false,'message'=>'Metode permintaan tidak didukung.']);
}
cloudguru_csrf_require_valid();
$incoming = json_decode((string)($_POST['settings'] ?? ''), true);
if (!is_array($incoming)) cgvs_json(400, ['ok'=>false,'message'=>'Data pengaturan tidak valid.']);

if (isset($incoming['navbar_items_text']) && is_string($incoming['navbar_items_text'])) {
    $items = [];
    foreach (preg_split('/\r\n|\r|\n/', $incoming['navbar_items_text']) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode('|', $line, 2);
        $label = trim((string)($parts[0] ?? ''));
        $url = trim((string)($parts[1] ?? ''));
        if ($label === '' || $url === '') continue;
        if (mb_strlen($label, 'UTF-8') > 80 || strlen($url) > 400 || !cgvs_valid_url($url, true)) {
            cgvs_json(400, ['ok'=>false,'message'=>'Format navigasi tidak valid. Gunakan satu baris per menu: Label|URL.']);
        }
        $items[] = ['label'=>$label,'url'=>$url];
        if (count($items) > 15) cgvs_json(400, ['ok'=>false,'message'=>'Maksimal 15 menu navigasi.']);
    }
    if (!$items) cgvs_json(400, ['ok'=>false,'message'=>'Navigasi harus memiliki minimal satu menu.']);
    $incoming['navbar_items'] = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    unset($incoming['navbar_items_text']);
}
$action = (string)($_POST['action'] ?? 'save_draft');
if (!in_array($action, ['save_draft','publish'], true)) cgvs_json(400, ['ok'=>false,'message'=>'Aksi pengaturan tidak valid.']);

$validUrlKeys = [
    'logo_url','favicon_url','hero_image','hero_button_url','hero_button2_url','navbar_cta_url',
    'facebook_url','instagram_url','youtube_url','maps_embed_url','maps_link','seo_og_image','seo_canonical'
];
$colorKeys = ['primary_color','header_bg','footer_bg','footer_text_color'];
$boolKeys = ['navbar_enabled','header_sticky','header_transparent','header_overlay','footer_show_contact','footer_show_logo','footer_show_quicklinks','footer_show_social'];
$numericRanges = ['header_height'=>[48,150], 'header_logo_width'=>[24,140]];
$values = [];
foreach ($incoming as $key => $value) {
    if (!array_key_exists((string)$key, $defaults)) continue;
    if (!is_scalar($value) && $value !== null) cgvs_json(400, ['ok'=>false,'message'=>'Nilai pengaturan tidak valid: ' . $key]);
    $value = trim((string)($value ?? ''));
    if (strlen($value) > 5000) cgvs_json(413, ['ok'=>false,'message'=>'Nilai terlalu panjang: ' . $key]);
    if (in_array($key, $validUrlKeys, true) && !cgvs_valid_url($value, in_array($key, ['seo_canonical'], true))) {
        cgvs_json(400, ['ok'=>false,'message'=>'URL tidak valid pada pengaturan ' . $key . '.']);
    }
    if (in_array($key, $colorKeys, true) && $value !== '' && !preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value)) {
        cgvs_json(400, ['ok'=>false,'message'=>'Warna harus menggunakan format HEX, misalnya #155eef.']);
    }
    if (in_array($key, $boolKeys, true)) $value = in_array(strtolower($value), ['1','true','yes','on'], true) ? '1' : '0';
    if (isset($numericRanges[$key])) {
        $value = (string)max($numericRanges[$key][0], min($numericRanges[$key][1], (int)$value));
    }
    if ($key === 'navbar_style' && !in_array($value, ['glass','solid','minimal','transparent'], true)) $value = 'glass';
    if ($key === 'header_align' && !in_array($value, ['between','left','center','right'], true)) $value = 'between';
    if ($key === 'footer_layout' && !in_array($value, ['columns','stacked','centered'], true)) $value = 'columns';
    if ($key === 'seo_robots' && !in_array($value, ['index,follow','noindex,follow','index,nofollow','noindex,nofollow'], true)) $value = 'index,follow';
    if (in_array($key, ['site_name','tagline','hero_eyebrow','hero_title','hero_subtitle','hero_button_text','hero_button2_text','navbar_cta_text','footer_text','seo_title','seo_description','seo_keywords','seo_author'], true) && mb_strlen($value, 'UTF-8') > 500) {
        cgvs_json(400, ['ok'=>false,'message'=>'Teks terlalu panjang: ' . $key]);
    }
    $values[$key] = $value;
}
if (!$values) cgvs_json(400, ['ok'=>false,'message'=>'Tidak ada pengaturan yang bisa disimpan.']);
try {
    cgvs_ensure_drafts($pdo);
    $pdo->beginTransaction();
    $draftExists = $pdo->prepare('SELECT 1 FROM website_builder_visual_settings_drafts WHERE setting_key=? LIMIT 1');
    $draftUpdate = $pdo->prepare('UPDATE website_builder_visual_settings_drafts SET setting_value=?, updated_by=? WHERE setting_key=?');
    $draftInsert = $pdo->prepare('INSERT INTO website_builder_visual_settings_drafts (setting_key, setting_value, updated_by) VALUES (?,?,?)');
    foreach ($values as $key => $value) {
        $draftExists->execute([$key]);
        if ($draftExists->fetchColumn()) $draftUpdate->execute([$value, (int)($_SESSION['user_id'] ?? 0), $key]);
        else $draftInsert->execute([$key, $value, (int)($_SESSION['user_id'] ?? 0)]);
    }
    if ($action === 'publish') {
        $exists = $pdo->prepare('SELECT 1 FROM website_builder_settings WHERE setting_key=? LIMIT 1');
        $update = $pdo->prepare('UPDATE website_builder_settings SET setting_value=? WHERE setting_key=?');
        $insert = $pdo->prepare('INSERT INTO website_builder_settings (setting_key, setting_value) VALUES (?,?)');
        foreach ($values as $key => $value) {
            $exists->execute([$key]);
            if ($exists->fetchColumn()) $update->execute([$value, $key]);
            else $insert->execute([$key, $value]);
        }
        $delete = $pdo->prepare('DELETE FROM website_builder_visual_settings_drafts WHERE setting_key=?');
        foreach (array_keys($values) as $key) $delete->execute([$key]);
    }
    $pdo->commit();
    cgvs_json(200, [
        'ok'=>true,
        'message'=>$action === 'publish' ? 'Pengaturan tersimpan dan dipublikasikan.' : 'Draft pengaturan berhasil disimpan; website publik belum berubah.',
        'action'=>$action,
        'updated_keys'=>array_keys($values)
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Cloud Guru visual settings save: ' . $e->getMessage());
    cgvs_json(500, ['ok'=>false,'message'=>'Pengaturan belum tersimpan. Database membatalkan perubahan; periksa log server.']);
}
