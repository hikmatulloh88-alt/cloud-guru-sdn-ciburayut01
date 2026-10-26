<?php
declare(strict_types=1);

/*
 * Cloud Guru Visual Page Editor
 * Visual editing happens on the real public page inside a same-origin preview.
 * Drafts are isolated from published content until the administrator publishes.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';

wajib_login();
$cgRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($cgRole, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('Editor Visual hanya untuk Administrator.');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

function cgvb_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cgvb_json(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function cgvb_valid_slug(string $slug): bool {
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,118}[a-z0-9]$|^[a-z0-9]$/', $slug);
}
function cgvb_columns(PDO $pdo, string $table): array {
    $result = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    foreach ($stmt ?: [] as $row) {
        $result[(string)$row['Field']] = $row;
    }
    return $result;
}
function cgvb_first_col(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) return $candidate;
    }
    return null;
}
function cgvb_page_columns(PDO $pdo): array {
    return cgvb_columns($pdo, 'website_builder_pages');
}
function cgvb_page_row(PDO $pdo, string $slug): ?array {
    $cols = cgvb_page_columns($pdo);
    $slugCol = cgvb_first_col($cols, ['slug', 'page_slug', 'url_slug']);
    if (!$slugCol) return null;
    $q = $pdo->prepare('SELECT * FROM website_builder_pages WHERE `' . $slugCol . '` = ? LIMIT 1');
    $q->execute([$slug]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
function cgvb_page_list(PDO $pdo): array {
    try {
        $cols = cgvb_page_columns($pdo);
        $slugCol = cgvb_first_col($cols, ['slug', 'page_slug', 'url_slug']);
        $titleCol = cgvb_first_col($cols, ['title', 'judul', 'page_title', 'name']);
        if (!$slugCol || !$titleCol) return [['slug'=>'home','title'=>'Beranda']];
        $rows = $pdo->query('SELECT `' . $slugCol . '` AS page_slug, `' . $titleCol . '` AS page_title FROM website_builder_pages ORDER BY ' . (isset($cols['id']) ? 'id' : '`' . $titleCol . '`') . ' ASC')->fetchAll(PDO::FETCH_ASSOC);
        $pages = [['slug'=>'home','title'=>'Beranda']];
        foreach ($rows as $row) {
            $slug = strtolower(trim((string)($row['page_slug'] ?? '')));
            if (!cgvb_valid_slug($slug) || $slug === 'home') continue;
            $title = trim((string)($row['page_title'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
            $pages[] = ['slug'=>$slug, 'title'=>$title];
        }
        $bySlug = [];
        foreach ($pages as $page) $bySlug[$page['slug']] = $page;
        return array_values($bySlug);
    } catch (Throwable $e) {
        error_log('Cloud Guru Visual Builder page list: ' . $e->getMessage());
        return [['slug'=>'home','title'=>'Beranda']];
    }
}
function cgvb_published(PDO $pdo, string $slug): ?string {
    try {
        $cols = cgvb_columns($pdo, 'website_builder_published');
        $slugCol = cgvb_first_col($cols, ['page_slug', 'slug']);
        if (!$slugCol || !isset($cols['content'])) return null;
        $q = $pdo->prepare('SELECT content FROM website_builder_published WHERE `' . $slugCol . '`=? LIMIT 1');
        $q->execute([$slug]);
        $value = $q->fetchColumn();
        return is_string($value) && trim($value) !== '' ? $value : null;
    } catch (Throwable $e) {
        return null;
    }
}
function cgvb_ensure_drafts(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS website_builder_visual_drafts (
        page_slug VARCHAR(120) NOT NULL PRIMARY KEY,
        page_title VARCHAR(190) NOT NULL DEFAULT '',
        content_html LONGTEXT NOT NULL,
        hero_json TEXT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function cgvb_get_draft(PDO $pdo, string $slug): ?array {
    try {
        $q = $pdo->prepare('SELECT page_title, content_html, hero_json, updated_at FROM website_builder_visual_drafts WHERE page_slug=? LIMIT 1');
        $q->execute([$slug]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}
function cgvb_safe_url(string $url, bool $image = false): string {
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if (preg_match('#^(javascript|data|vbscript):#i', $url)) return '';
    if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) return $url;
    if (!$image && preg_match('#^(mailto|tel):#i', $url)) return $url;
    if (preg_match('#^[a-z0-9][a-z0-9/_\.-]*$#i', $url)) return '/' . ltrim($url, '/');
    return '';
}
function cgvb_sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    if (strlen($html) > 700000) {
        throw new RuntimeException('Konten terlalu besar. Kurangi jumlah gambar atau blok pada halaman.');
    }
    if (!class_exists('DOMDocument')) {
        // Safe fallback: remove executable elements and inline event attributes.
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|link|meta)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|link|meta)\b[^>]*/?>#is', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/i', '', $html) ?? '';
        return $html;
    }
    $allowed = array_flip(['section','div','span','h1','h2','h3','h4','h5','h6','p','br','hr','strong','b','em','i','u','s','small','ul','ol','li','a','img','figure','figcaption','blockquote','table','thead','tbody','tr','th','td','details','summary','article']);
    $allowedAttrs = array_flip(['class','id','href','src','alt','title','target','rel','style','width','height','loading','role','aria-label','aria-hidden','colspan','rowspan']);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="cgvb-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = null;
    foreach ($dom->getElementsByTagName('div') as $div) {
        if ($div->getAttribute('id') === 'cgvb-root') { $root = $div; break; }
    }
    if (!$root) return '';
    $walk = function(DOMNode $node) use (&$walk, $allowed, $allowedAttrs): void {
        for ($child = $node->firstChild; $child;) {
            $next = $child->nextSibling;
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!isset($allowed[$tag])) {
                    if (in_array($tag, ['script','style','iframe','object','embed','form','input','button','textarea','select','link','meta','svg','math'], true)) {
                        $node->removeChild($child);
                    } else {
                        while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                        $node->removeChild($child);
                    }
                } else {
                    $remove = [];
                    foreach ($child->attributes as $attr) {
                        $name = strtolower($attr->name);
                        if (str_starts_with($name, 'on') || $name === 'contenteditable' || str_starts_with($name, 'data-cgvb-')) {
                            $remove[] = $name; continue;
                        }
                        if (!isset($allowedAttrs[$name]) && !str_starts_with($name, 'data-')) {
                            $remove[] = $name; continue;
                        }
                        if (in_array($name, ['href','src'], true)) {
                            $isImage = ($tag === 'img' && $name === 'src');
                            $safe = cgvb_safe_url((string)$attr->value, $isImage);
                            if ($safe === '') { $remove[] = $name; } else { $child->setAttribute($name, $safe); }
                        }
                        if ($name === 'style' && preg_match('/expression\s*\(|javascript:|vbscript:|url\s*\(\s*[\x22\x27]?\s*(data:|javascript:)/i', (string)$attr->value)) {
                            $remove[] = $name;
                        }
                    }
                    foreach (array_unique($remove) as $name) $child->removeAttribute($name);
                    if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
                    $walk($child);
                }
            }
            $child = $next;
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $child) $out .= $dom->saveHTML($child);
    return $out;
}
function cgvb_store_draft(PDO $pdo, string $slug, string $title, string $html, ?array $hero, int $userId): void {
    cgvb_ensure_drafts($pdo);
    $heroJson = $hero ? json_encode($hero, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $q = $pdo->prepare("INSERT INTO website_builder_visual_drafts (page_slug,page_title,content_html,hero_json,updated_by)
        VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE page_title=VALUES(page_title), content_html=VALUES(content_html), hero_json=VALUES(hero_json), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP");
    $q->execute([$slug, $title, $html, $heroJson, $userId > 0 ? $userId : null]);
}
function cgvb_publish_html(PDO $pdo, string $slug, string $html, string $title): void {
    $cols = cgvb_columns($pdo, 'website_builder_published');
    $slugCol = cgvb_first_col($cols, ['page_slug', 'slug']);
    if (!$slugCol || !isset($cols['content'])) {
        throw new RuntimeException('Struktur tabel website_builder_published tidak memiliki kolom slug/content yang diperlukan.');
    }
    $exist = $pdo->prepare('SELECT 1 FROM website_builder_published WHERE `' . $slugCol . '`=? LIMIT 1');
    $exist->execute([$slug]);
    $hasRow = (bool)$exist->fetchColumn();
    $now = date('Y-m-d H:i:s');
    if ($hasRow) {
        $sets = ['content=?'];
        $params = [$html];
        foreach (['updated_at','published_at'] as $timeCol) {
            if (isset($cols[$timeCol])) { $sets[] = '`' . $timeCol . '`=?'; $params[] = $now; }
        }
        $params[] = $slug;
        $q = $pdo->prepare('UPDATE website_builder_published SET ' . implode(', ', $sets) . ' WHERE `' . $slugCol . '`=?');
        $q->execute($params);
    } else {
        $values = [$slugCol => $slug, 'content' => $html];
        foreach ($cols as $name => $meta) {
            if (isset($values[$name]) || ($meta['Default'] !== null) || $meta['Null'] === 'YES' || strpos((string)$meta['Extra'], 'auto_increment') !== false) continue;
            if (in_array($name, ['created_at','updated_at','published_at'], true)) $values[$name] = $now;
            elseif (in_array($name, ['title','judul','page_title'], true)) $values[$name] = $title;
            elseif (in_array($name, ['status','state'], true)) $values[$name] = 'published';
            elseif (in_array($name, ['created_by','updated_by','user_id','id_user'], true)) $values[$name] = (int)($_SESSION['user_id'] ?? 0);
            else throw new RuntimeException('Kolom wajib "' . $name . '" pada tabel halaman belum dikenali; publikasi dihentikan agar data tidak rusak.');
        }
        $names = array_keys($values);
        $quoted = array_map(fn($n) => '`' . $n . '`', $names);
        $marks = implode(',', array_fill(0, count($names), '?'));
        $q = $pdo->prepare('INSERT INTO website_builder_published (' . implode(',', $quoted) . ') VALUES (' . $marks . ')');
        $q->execute(array_values($values));
    }
}
function cgvb_update_page_status(PDO $pdo, string $slug, string $title): void {
    try {
        $cols = cgvb_page_columns($pdo);
        $slugCol = cgvb_first_col($cols, ['slug','page_slug','url_slug']);
        if (!$slugCol) return;
        $sets = [];
        $params = [];
        if (isset($cols['status'])) { $sets[] = '`status`=?'; $params[] = 'published'; }
        if (isset($cols['updated_at'])) { $sets[] = '`updated_at`=?'; $params[] = date('Y-m-d H:i:s'); }
        if ($slug !== 'home') {
            $titleCol = cgvb_first_col($cols, ['title','judul','page_title','name']);
            if ($titleCol && trim($title) !== '') { $sets[] = '`' . $titleCol . '`=?'; $params[] = $title; }
        }
        if ($sets) {
            $params[] = $slug;
            $q = $pdo->prepare('UPDATE website_builder_pages SET ' . implode(', ', $sets) . ' WHERE `' . $slugCol . '`=?');
            $q->execute($params);
        }
    } catch (Throwable $e) {
        error_log('Cloud Guru visual builder page status: ' . $e->getMessage());
    }
}
function cgvb_store_hero_settings(PDO $pdo, array $hero): void {
    $allowed = [
        'hero_image' => cgvb_safe_url((string)($hero['image'] ?? ''), true),
        'hero_eyebrow' => trim((string)($hero['eyebrow'] ?? '')),
        'hero_title' => trim((string)($hero['title'] ?? '')),
        'hero_subtitle' => trim((string)($hero['subtitle'] ?? '')),
        'hero_button_text' => trim((string)($hero['button_text'] ?? '')),
        'hero_button_url' => cgvb_safe_url((string)($hero['button_url'] ?? '')),
        'hero_button2_text' => trim((string)($hero['button2_text'] ?? '')),
        'hero_button2_url' => cgvb_safe_url((string)($hero['button2_url'] ?? '')),
    ];
    $cols = cgvb_columns($pdo, 'website_builder_settings');
    if (!isset($cols['setting_key'], $cols['setting_value'])) {
        throw new RuntimeException('Tabel pengaturan website tidak memiliki kolom setting_key/setting_value.');
    }
    $find = $pdo->prepare('SELECT 1 FROM website_builder_settings WHERE setting_key=? LIMIT 1');
    $update = $pdo->prepare('UPDATE website_builder_settings SET setting_value=? WHERE setting_key=?');
    $insert = $pdo->prepare('INSERT INTO website_builder_settings (setting_key,setting_value) VALUES (?,?)');
    foreach ($allowed as $key => $value) {
        if (in_array($key, ['hero_eyebrow','hero_title','hero_subtitle','hero_button_text','hero_button_url','hero_button2_text','hero_button2_url'], true) && $value === '') continue;
        $find->execute([$key]);
        if ($find->fetchColumn()) $update->execute([$value, $key]);
        else $insert->execute([$key, $value]);
    }
}
function cgvb_safe_page_payload(PDO $pdo, string $slug, string $title, int $userId): void {
    $action = (string)($_POST['action'] ?? '');
    $postedSlug = strtolower(trim((string)($_POST['page_slug'] ?? $slug)));
    if (!cgvb_valid_slug($postedSlug)) cgvb_json(400, ['ok'=>false,'message'=>'Alamat halaman tidak valid.']);
    if ($action === 'create_page') {
        $newTitle = trim((string)($_POST['new_title'] ?? ''));
        $newSlug = strtolower(trim((string)($_POST['new_slug'] ?? '')));
        if ($newTitle === '' || mb_strlen($newTitle, 'UTF-8') > 180 || !cgvb_valid_slug($newSlug)) {
            cgvb_json(400, ['ok'=>false,'message'=>'Isi judul dan alamat halaman (huruf kecil, angka, dan tanda hubung).']);
        }
        $cols = cgvb_page_columns($pdo);
        $slugCol = cgvb_first_col($cols, ['slug','page_slug','url_slug']);
        $titleCol = cgvb_first_col($cols, ['title','judul','page_title','name']);
        if (!$slugCol || !$titleCol) cgvb_json(500, ['ok'=>false,'message'=>'Struktur tabel halaman tidak cocok. Halaman baru belum dibuat.']);
        $existing = $pdo->prepare('SELECT 1 FROM website_builder_pages WHERE `' . $slugCol . '`=? LIMIT 1');
        $existing->execute([$newSlug]);
        if ($existing->fetchColumn() || $newSlug === 'home') cgvb_json(409, ['ok'=>false,'message'=>'Alamat halaman sudah dipakai. Pilih slug lain.']);
        $values = [$slugCol=>$newSlug, $titleCol=>$newTitle];
        foreach ($cols as $name => $meta) {
            if (isset($values[$name]) || $meta['Default'] !== null || $meta['Null'] === 'YES' || strpos((string)$meta['Extra'],'auto_increment') !== false) continue;
            if (in_array($name,['small_label','label_small','eyebrow','label'],true)) $values[$name]='HALAMAN SEKOLAH';
            elseif (in_array($name,['intro','pengantar','subtitle','description','deskripsi','content','isi','body','html','image_url','gambar_url'],true)) $values[$name]='';
            elseif (in_array($name,['status','state'],true)) $values[$name]='draft';
            elseif (in_array($name,['created_at','updated_at'],true)) $values[$name]=date('Y-m-d H:i:s');
            elseif (in_array($name,['created_by','user_id','id_user'],true)) $values[$name]=$userId;
            elseif (in_array($name,['sort_order','urutan','position'],true)) $values[$name]=0;
            else cgvb_json(500, ['ok'=>false,'message'=>'Kolom wajib "' . $name . '" belum dikenali. Halaman baru tidak dibuat.']);
        }
        try {
            $names = array_keys($values);
            $quoted = array_map(fn($n) => '`'.$n.'`', $names);
            $q = $pdo->prepare('INSERT INTO website_builder_pages (' . implode(',', $quoted) . ') VALUES (' . implode(',', array_fill(0,count($names),'?')) . ')');
            $q->execute(array_values($values));
            cgvb_ensure_drafts($pdo);
            $starter = '<section class="public-page-section" style="max-width:1100px;margin:40px auto;padding:0 24px"><div class="eyebrow">HALAMAN SEKOLAH</div><h1>' . cgvb_e($newTitle) . '</h1><p>Klik teks ini untuk mengganti dengan pengantar halaman.</p></section>';
            cgvb_store_draft($pdo,$newSlug,$newTitle,$starter,null,$userId);
            cgvb_json(200, ['ok'=>true,'message'=>'Halaman baru berhasil dibuat.','url'=>'/builder/?page='.rawurlencode($newSlug)]);
        } catch (Throwable $e) {
            error_log('Cloud Guru visual builder create page: '.$e->getMessage());
            cgvb_json(500, ['ok'=>false,'message'=>'Halaman belum bisa dibuat. Periksa skema tabel website_builder_pages dan log server.']);
        }
    }
    $pageRow = cgvb_page_row($pdo, $postedSlug);
    $titleCol = [];
    if ($pageRow) {
        try { $titleCol = cgvb_page_columns($pdo); } catch (Throwable $e) { $titleCol = []; }
    }
    $publishedContent = cgvb_published($pdo, $postedSlug);
    $pageTitle = trim((string)($_POST['page_title'] ?? ''));
    if ($pageTitle === '' && $pageRow) {
        foreach (['title','judul','page_title','name'] as $key) if (!empty($pageRow[$key])) { $pageTitle=(string)$pageRow[$key]; break; }
    }
    if ($pageTitle === '') $pageTitle = $postedSlug === 'home' ? 'Beranda' : ucwords(str_replace('-', ' ', $postedSlug));
    $rawHtml = (string)($_POST['content_html'] ?? '');
    try {
        $html = cgvb_sanitize_html($rawHtml);
        if (strlen($html) > 600000) cgvb_json(413, ['ok'=>false,'message'=>'Konten halaman terlalu besar setelah disaring.']);
        $hero = null;
        if ($postedSlug === 'home') {
            $heroRaw = json_decode((string)($_POST['hero_json'] ?? ''), true);
            if (is_array($heroRaw)) {
                $hero = [];
                foreach (['image','eyebrow','title','subtitle','button_text','button_url','button2_text','button2_url'] as $key) {
                    $hero[$key] = trim((string)($heroRaw[$key] ?? ''));
                }
                $hero['image'] = cgvb_safe_url($hero['image'], true);
                $hero['button_url'] = cgvb_safe_url($hero['button_url']);
                $hero['button2_url'] = cgvb_safe_url($hero['button2_url']);
            }
        }
        cgvb_store_draft($pdo, $postedSlug, $pageTitle, $html, $hero, $userId);
        if ($action === 'publish') {
            if ($postedSlug === 'home' && $hero !== null) cgvb_store_hero_settings($pdo, $hero);
            cgvb_publish_html($pdo, $postedSlug, $html, $pageTitle);
            cgvb_update_page_status($pdo, $postedSlug, $pageTitle);
            cgvb_json(200, ['ok'=>true,'message'=>'Halaman berhasil dipublikasikan.','url'=>$postedSlug==='home'?'/?page=home':'/?page='.rawurlencode($postedSlug)]);
        }
        cgvb_json(200, ['ok'=>true,'message'=>'Draft berhasil disimpan.','updated_at'=>date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        error_log('Cloud Guru visual builder save: '.$e->getMessage());
        cgvb_json(500, ['ok'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'Penyimpanan gagal. Tidak ada perubahan yang dipublikasikan.']);
    }
}

try {
    cgvb_ensure_drafts($pdo);
} catch (Throwable $e) {
    error_log('Cloud Guru visual builder draft schema: '.$e->getMessage());
    http_response_code(503);
    exit('Editor Visual belum siap: tabel draft tidak dapat disiapkan. Hubungi administrator hosting untuk memeriksa hak akses database.');
}
$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { cloudguru_csrf_require_valid(); }
    catch (Throwable $e) { cgvb_json(403, ['ok'=>false,'message'=>'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.']); }
    cgvb_safe_page_payload($pdo, '', '', $userId);
}

$pages = cgvb_page_list($pdo);
$pageSlug = strtolower(trim((string)($_GET['page'] ?? 'home')));
if (!cgvb_valid_slug($pageSlug)) $pageSlug = 'home';
$current = null;
foreach ($pages as $p) if ($p['slug'] === $pageSlug) { $current = $p; break; }
if (!$current) {
    $row = cgvb_page_row($pdo, $pageSlug);
    if ($row) {
        $title = '';
        foreach (['title','judul','page_title','name'] as $key) if (!empty($row[$key])) { $title=(string)$row[$key]; break; }
        $current = ['slug'=>$pageSlug,'title'=>$title ?: ucwords(str_replace('-',' ',$pageSlug))];
        $pages[] = $current;
    } else {
        $pageSlug = 'home';
        $current = ['slug'=>'home','title'=>'Beranda'];
    }
}
$pageRow = cgvb_page_row($pdo, $pageSlug);
$publishedContent = cgvb_published($pdo, $pageSlug);
$draft = cgvb_get_draft($pdo, $pageSlug);
$initialHtml = '';
if (!$publishedContent && $pageSlug !== 'home' && $pageRow) {
    $find = static function(array $row, array $keys): string {
        foreach ($keys as $key) if (isset($row[$key]) && trim((string)$row[$key]) !== '') return (string)$row[$key];
        return '';
    };
    $label = $find($pageRow,['small_label','label_small','eyebrow','label','category']) ?: 'HALAMAN SEKOLAH';
    $title = $find($pageRow,['title','judul','page_title','name']) ?: $current['title'];
    $intro = $find($pageRow,['intro','pengantar','subtitle','description','deskripsi']);
    $image = cgvb_safe_url($find($pageRow,['image_url','gambar_url','image','gambar']), true);
    $body = $find($pageRow,['content','isi','body','html']);
    $initialHtml = '<section class="public-page-section" style="max-width:1100px;margin:40px auto;padding:0 24px"><div class="eyebrow">'.cgvb_e($label).'</div><h1>'.cgvb_e($title).'</h1>';
    if ($intro !== '') $initialHtml .= '<p>'.nl2br(cgvb_e($intro)).'</p>';
    if ($image !== '') $initialHtml .= '<figure><img style="display:block;max-width:100%;height:auto;border-radius:16px" src="'.cgvb_e($image).'" alt="'.cgvb_e($title).'"></figure>';
    if ($body !== '') $initialHtml .= '<div class="public-page-content">'.nl2br(cgvb_e($body)).'</div>';
    $initialHtml .= '</section>';
}
$csrf = cloudguru_csrf_token();
$config = [
    'slug'=>$pageSlug,
    'title'=>$current['title'],
    'pages'=>$pages,
    'csrf'=>$csrf,
    'publishedUrl'=>$pageSlug==='home'?'/?page=home':'/?page='.rawurlencode($pageSlug),
    'initialHtml'=>$initialHtml,
    'draft'=> $draft ? [
        'content_html'=>(string)$draft['content_html'],
        'hero_json'=>json_decode((string)($draft['hero_json'] ?? ''),true) ?: null,
        'updated_at'=>(string)($draft['updated_at'] ?? '')
    ] : null
];
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor Visual - Cloud Guru</title>
<style>
:root{--blue:#155eef;--ink:#192537;--muted:#63748b;--line:#dce4ef;--bg:#eef3f9;--panel:#fff;--green:#16803d}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
button,input,select{font:inherit}button{border:0;border-radius:9px;padding:10px 13px;font-weight:750;cursor:pointer;background:#eef3fa;color:#25364c}button:hover{filter:brightness(.97)}button:disabled{opacity:.55;cursor:wait}
.topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:12px;padding:11px 16px;background:#fff;border-bottom:1px solid var(--line);box-shadow:0 3px 12px #13284b08}
.brand{font-size:15px;font-weight:850;white-space:nowrap}.brand small{display:block;font-size:11px;color:var(--muted);font-weight:500}
.page-select{max-width:260px;min-width:180px;padding:10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink)}
.top-spacer{flex:1}.btn-primary{background:var(--blue);color:#fff}.btn-green{background:#138044;color:#fff}.btn-outline{border:1px solid var(--line);background:#fff}.btn-danger{background:#fff0ef;color:#b42318}.status{font-size:12px;color:var(--muted);min-width:110px}.status[data-kind=error]{color:#b42318}.status[data-kind=success]{color:#137333}
.workspace{display:grid;grid-template-columns:202px minmax(320px,1fr) 235px;gap:12px;padding:12px;min-height:calc(100vh - 64px)}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:13px;box-shadow:0 4px 18px #13284b05;min-width:0}
.panel-title{font-size:12px;letter-spacing:.3px;text-transform:uppercase;font-weight:850;color:#60718a;padding:15px 15px 10px}
.insert-list{display:grid;gap:7px;padding:0 11px 12px}.insert-button{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:10px;border:1px solid #e6ebf3;background:#fff;font-weight:650}.insert-button .ico{display:grid;place-items:center;width:28px;height:28px;border-radius:8px;background:#eff5ff;font-size:16px}
.left-tip{margin:5px 11px 12px;padding:10px;border-radius:9px;background:#f4f8ff;color:#62748e;font-size:11px}
.canvas-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid var(--line)}
.canvas-head b{margin-right:auto;font-size:13px}.device-button{padding:7px 10px;font-size:12px}.device-button.active{background:#e4efff;color:#1254ca}
.canvas-wrap{padding:14px;background:#e8edf5;min-height:calc(100vh - 126px);overflow:auto;display:flex;align-items:flex-start;justify-content:center}
#siteFrame{width:100%;height:calc(100vh - 165px);min-height:570px;border:1px solid #d7dfeb;border-radius:8px;background:white;box-shadow:0 8px 25px #152c4b14;transition:width .2s ease}
.right-body{padding:0 14px 14px}.tool-help{font-size:12px;color:#60718a;line-height:1.65}.selected-info{padding:11px;border:1px solid #d4e4ff;border-radius:10px;background:#f4f8ff;font-size:12px;color:#345273;line-height:1.55}.actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:10px}.actions-grid button{padding:9px 7px;font-size:12px}
.newpage{display:grid;gap:7px;padding:0 11px 14px}.newpage input{width:100%;border:1px solid var(--line);border-radius:8px;padding:9px}.newpage label{font-size:11px;color:var(--muted);font-weight:700}
#imageFile{display:none}.bottom-status{display:flex;gap:10px;justify-content:space-between;align-items:center;padding:9px 14px;color:var(--muted);font-size:11px;border-top:1px solid var(--line)}
@media(max-width:1100px){.workspace{grid-template-columns:180px minmax(280px,1fr)}.right-panel{grid-column:1/-1}.right-panel .right-body{display:grid;grid-template-columns:1fr 1fr;gap:10px}.right-panel .panel-title{padding-bottom:7px}}
@media(max-width:700px){.topbar{flex-wrap:wrap;padding:9px}.brand{width:100%}.page-select{flex:1;max-width:none}.top-spacer{display:none}.status{flex:1}.workspace{display:flex;flex-direction:column;padding:8px}.left-panel{order:2}.center-panel{order:1}.right-panel{order:3}.insert-list{grid-template-columns:repeat(2,minmax(0,1fr))}.insert-button{font-size:12px;padding:8px}.insert-button .ico{flex-shrink:0}.canvas-wrap{padding:7px;min-height:420px}#siteFrame{height:68vh;min-height:430px}.right-panel .right-body{display:block}.topbar .btn-outline,.topbar .btn-primary,.topbar .btn-green{padding:9px;font-size:12px}}
</style>
</head>
<body>
<header class="topbar">
  <div class="brand">🧱 Editor Visual <small>Cloud Guru · SDN Ciburayut 01</small></div>
  <select id="pageSelect" class="page-select" aria-label="Pilih halaman"></select>
  <button id="newPageButton" class="btn-outline">＋ Halaman Baru</button>
  <div class="top-spacer"></div>
  <span id="status" class="status" aria-live="polite">Memuat halaman…</span>
  <button id="saveDraftButton" class="btn-primary">💾 Simpan Draft</button>
  <button id="publishButton" class="btn-green">🚀 Publikasikan</button>
  <a id="viewPublic" class="btn-outline" href="<?=cgvb_e($config['publishedUrl'])?>" target="_blank" rel="noopener" style="text-decoration:none;padding:10px 12px;border-radius:9px;font-weight:750;color:#24364c">↗ Lihat Publik</a>
  <a class="btn-outline" href="/admin/tampilan/" style="text-decoration:none;padding:10px 12px;border-radius:9px;font-weight:750;color:#24364c">⚙ Pengaturan</a>
</header>
<main class="workspace">
  <aside class="panel left-panel">
    <div class="panel-title">Tambah bagian</div>
    <div class="insert-list">
      <button class="insert-button" data-insert="heading"><span class="ico">T</span> Judul</button>
      <button class="insert-button" data-insert="text"><span class="ico">¶</span> Teks</button>
      <button class="insert-button" data-insert="image"><span class="ico">▧</span> Gambar</button>
      <button class="insert-button" data-insert="button"><span class="ico">↗</span> Tombol / Tautan</button>
      <button class="insert-button" data-insert="columns"><span class="ico">▥</span> Dua Kolom</button>
      <button class="insert-button" data-insert="divider"><span class="ico">―</span> Garis Pemisah</button>
      <button class="insert-button" data-insert="spacer"><span class="ico">↕</span> Jarak</button>
    </div>
    <div class="left-tip"><b>Tips:</b> Klik teks langsung di pratinjau untuk mengedit. Pilih bagian lalu gunakan panel kanan untuk memindahkan atau menghapus.</div>
    <div class="panel-title">Halaman baru</div>
    <div class="newpage">
      <label for="newTitle">Nama halaman</label>
      <input id="newTitle" maxlength="180" placeholder="Contoh: Program Sekolah">
      <label for="newSlug">Alamat halaman</label>
      <input id="newSlug" maxlength="120" placeholder="program-sekolah">
      <button id="createPageButton" class="btn-primary">＋ Buat Halaman</button>
    </div>
  </aside>
  <section class="panel center-panel">
    <div class="canvas-head">
      <b id="canvasTitle">Pratinjau website</b>
      <button class="device-button active" data-device="desktop">🖥 Desktop</button>
      <button class="device-button" data-device="mobile">📱 Mobile</button>
      <span id="draftLabel" class="status"></span>
    </div>
    <div class="canvas-wrap"><iframe id="siteFrame" title="Pratinjau halaman yang dapat diedit" src="<?=cgvb_e($config['publishedUrl'])?>&cgvisual=1" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe></div>
    <div class="bottom-status"><span>Draft disimpan terpisah dari website publik hingga dipublikasikan.</span><span>Perubahan teks otomatis mengikuti kanvas.</span></div>
  </section>
  <aside class="panel right-panel">
    <div class="panel-title">Bagian terpilih</div>
    <div class="right-body">
      <div id="selectionInfo" class="selected-info">Klik salah satu bagian di pratinjau. Anda bisa mengedit teks langsung pada halaman.</div>
      <div>
        <div class="actions-grid">
          <button id="moveUpButton">↑ Naikkan</button>
          <button id="moveDownButton">↓ Turunkan</button>
          <button id="replaceImageButton">🖼 Ganti Gambar</button>
          <button id="deleteBlockButton" class="btn-danger">Hapus Bagian</button>
        </div>
        <p class="tool-help" style="margin:12px 0 0">Untuk halaman Beranda, banner utama tetap terhubung ke pengaturan Hero. Teks dan gambar banner yang diubah di sini akan disimpan ke pengaturan Hero saat dipublikasikan.</p>
      </div>
    </div>
  </aside>
</main>
<input id="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
<script>
(function(){
'use strict';
var CFG = <?=json_encode($config, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
var frame=document.getElementById('siteFrame');
var statusEl=document.getElementById('status');
var selectionInfo=document.getElementById('selectionInfo');
var selectedBlock=null;
var selectedImage=null;
var currentMain=null;
var dragBlock=null;
var pageSlug=CFG.slug;
var csrf=CFG.csrf;
var loaded=false;

function setStatus(message,kind){statusEl.textContent=message;statusEl.dataset.kind=kind||'';}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function currentUrl(){return pageSlug==='home'? '/?page=home' : '/?page='+encodeURIComponent(pageSlug);}
function fillPageSelect(){
 var select=document.getElementById('pageSelect');
 select.textContent='';
 CFG.pages.forEach(function(p){
  var opt=document.createElement('option');opt.value=p.slug;opt.textContent=p.title;select.appendChild(opt);
 });
 if(!Array.from(select.options).some(function(o){return o.value===pageSlug;})){
  var opt=document.createElement('option');opt.value=pageSlug;opt.textContent=CFG.title;select.appendChild(opt);
 }
 select.value=pageSlug;
 document.getElementById('canvasTitle').textContent='Mengedit: '+CFG.title;
 document.getElementById('viewPublic').href=currentUrl();
}
function getDoc(){try{return frame.contentDocument || frame.contentWindow.document;}catch(e){return null;}}
function isProtectedBlock(el){
 if(!el||el.nodeType!==1)return true;
 var cl=String(el.className||'');
 return el.matches('.digital-hero,.school-live-stats') || /(^|\s)(digital-hero|school-live-stats)(\s|$)/.test(cl);
}
function clearDecoration(){
 if(!currentMain)return;
 currentMain.querySelectorAll('.cg-vb-selected').forEach(function(el){el.classList.remove('cg-vb-selected');});
 currentMain.querySelectorAll('[data-cgvb-index]').forEach(function(el){el.removeAttribute('data-cgvb-index');el.removeAttribute('draggable');});
}
function decorateBlocks(){
 if(!currentMain)return;
 clearDecoration();
 Array.from(currentMain.children).forEach(function(el,index){
  el.dataset.cgvbIndex=String(index);
  el.draggable=true;
  el.style.cursor='move';
  el.title='Klik untuk memilih bagian · seret untuk mengatur urutan';
 });
 if(selectedBlock && selectedBlock.isConnected) selectedBlock.classList.add('cg-vb-selected');
}
function selectBlock(el){
 if(!el||!currentMain)return;
 selectedBlock=el;
 selectedImage=null;
 currentMain.querySelectorAll('.cg-vb-selected').forEach(function(x){x.classList.remove('cg-vb-selected');});
 el.classList.add('cg-vb-selected');
 selectionInfo.textContent='Bagian dipilih: '+(el.tagName||'BAGIAN').toLowerCase()+'. Klik teks untuk mengedit langsung. Untuk mengatur urutan, seret bagian atau gunakan tombol Naikkan/Turunkan.';
 var locked=isProtectedBlock(el);
 document.getElementById('moveUpButton').disabled=locked;
 document.getElementById('moveDownButton').disabled=locked;
 document.getElementById('deleteBlockButton').disabled=locked;
 document.getElementById('replaceImageButton').disabled=false;
}
function applyHero(hero){
 if(!currentMain||!hero)return;
 var section=currentMain.querySelector('.digital-hero');
 if(!section)return;
 var eyebrow=section.querySelector('.digital-eyebrow');
 var title=section.querySelector('h2');
 var subtitle=section.querySelector('.digital-hero-content p');
 var image=section.querySelector('.digital-photo img');
 var buttons=section.querySelectorAll('.digital-actions a');
 if(eyebrow && hero.eyebrow!=null)eyebrow.textContent=hero.eyebrow;
 if(title && hero.title!=null)title.textContent=hero.title;
 if(subtitle && hero.subtitle!=null)subtitle.textContent=hero.subtitle;
 if(image && hero.image){image.src=hero.image;section.style.setProperty('--hero-image','url("'+hero.image.replace(/["\\)]/g,'')+'")');}
 if(buttons[0]){if(hero.button_text)buttons[0].textContent=hero.button_text;if(hero.button_url)buttons[0].setAttribute('href',hero.button_url);}
 if(buttons[1]){if(hero.button2_text)buttons[1].textContent=hero.button2_text;if(hero.button2_url)buttons[1].setAttribute('href',hero.button2_url);}
}
function decorateMain(){
 var doc=getDoc();
 if(!doc){setStatus('Pratinjau tidak dapat diakses.','error');return;}
 if(doc.head && !doc.getElementById('cgvb-editor-style')){
  var editStyle=doc.createElement('style');editStyle.id='cgvb-editor-style';
  editStyle.textContent='.cg-vb-selected{outline:3px solid #155eef!important;outline-offset:3px!important}.published-wrap [data-cgvb-index]:hover{outline:1px dashed #70a0f5;outline-offset:2px}.published-wrap .cg-visual-section{cursor:move}.published-wrap .cg-visual-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}@media(max-width:620px){.published-wrap .cg-visual-columns{grid-template-columns:1fr!important}}';
  doc.head.appendChild(editStyle);
 }
 currentMain=doc.querySelector('.published-wrap');
 if(!currentMain){setStatus('Kontainer halaman publik tidak ditemukan.','error');return;}
 if(CFG.draft){
  if(pageSlug==='home'){
   var heroEl=currentMain.querySelector('.digital-hero');
   var statsEl=currentMain.querySelector('.school-live-stats');
   var heroHtml=heroEl?heroEl.outerHTML:'';
   var statsHtml=statsEl?statsEl.outerHTML:'';
   currentMain.innerHTML=heroHtml+statsHtml+(CFG.draft.content_html||'');
   currentMain=currentMain.ownerDocument.querySelector('.published-wrap');
   applyHero(CFG.draft.hero_json);
  }else{
   currentMain.innerHTML=CFG.draft.content_html||'';
  }
  document.getElementById('draftLabel').textContent='Draft tersimpan '+(CFG.draft.updated_at||'');
 }else if(CFG.initialHtml && pageSlug!=='home' && !currentMain.querySelector('.public-news-detail')){
  currentMain.innerHTML=CFG.initialHtml;
 }
 currentMain.contentEditable='true';
 currentMain.setAttribute('spellcheck','true');
 currentMain.querySelectorAll('input,textarea,select,button,form').forEach(function(el){el.contentEditable='false';});
 currentMain.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(ev){ev.preventDefault();},true);});
 currentMain.addEventListener('click',function(ev){
  if(ev.target.closest('a'))ev.preventDefault();
  var img=ev.target.closest('img');
  if(img && currentMain.contains(img)){
   selectedImage=img;
   var block=img.closest('[data-cgvb-index]')||img.parentElement;
   if(block&&currentMain.contains(block))selectBlock(block);
   selectionInfo.textContent='Gambar dipilih. Gunakan “Ganti Gambar” untuk mengganti file ini.';
   return;
  }
  var block=ev.target.closest('[data-cgvb-index]') || ev.target.closest('section,article,figure');
  if(block&&currentMain.contains(block))selectBlock(block);
 },true);
 currentMain.addEventListener('input',function(){setStatus('Ada perubahan belum disimpan','');});
 currentMain.addEventListener('dragstart',function(ev){
  var block=ev.target.closest('[data-cgvb-index]');
  if(!block||isProtectedBlock(block)){ev.preventDefault();return;}
  dragBlock=block;
  if(ev.dataTransfer){ev.dataTransfer.effectAllowed='move';ev.dataTransfer.setData('text/plain',block.dataset.cgvbIndex||'');}
 });
 currentMain.addEventListener('dragover',function(ev){
  if(!dragBlock)return;
  var target=ev.target.closest('[data-cgvb-index]');
  if(!target||target===dragBlock||isProtectedBlock(target))return;
  ev.preventDefault();
  var rect=target.getBoundingClientRect();
  var after=ev.clientY>rect.top+rect.height/2;
  target.parentNode.insertBefore(dragBlock,after?target.nextSibling:target);
 });
 currentMain.addEventListener('drop',function(ev){if(dragBlock){ev.preventDefault();dragBlock=null;decorateBlocks();setStatus('Urutan bagian berubah; simpan draft untuk menyimpan.','');}});
 currentMain.addEventListener('dragend',function(){dragBlock=null;decorateBlocks();});
 // Avoid navigating to live links and prevent submitting forms inside the editable preview.
 currentMain.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(ev){ev.preventDefault();});});
 decorateBlocks();
 setStatus('Pratinjau siap diedit.','success');
 loaded=true;
}
frame.addEventListener('load',function(){
 try{decorateMain();}catch(e){setStatus('Gagal menyiapkan kanvas: '+e.message,'error');}
});
document.getElementById('pageSelect').addEventListener('change',function(){window.location.href='/builder/?page='+encodeURIComponent(this.value);});
document.querySelectorAll('[data-device]').forEach(function(button){
 button.addEventListener('click',function(){
  document.querySelectorAll('[data-device]').forEach(function(b){b.classList.toggle('active',b===button);});
  frame.style.width=button.dataset.device==='mobile'?'390px':'100%';
 });
});
function addSection(type){
 if(!currentMain){setStatus('Tunggu sampai pratinjau siap.','error');return;}
 var doc=getDoc();
 var html='';
 var outer='max-width:1100px;margin:32px auto;padding:0 24px;';
 if(type==='heading')html='<section class="cg-visual-section" style="'+outer+'"><h2>Judul bagian baru</h2></section>';
 if(type==='text')html='<section class="cg-visual-section" style="'+outer+'"><p style="font-size:17px;line-height:1.8;color:#334155">Klik di sini untuk menulis teks halaman.</p></section>';
 if(type==='image')html='<section class="cg-visual-section" style="'+outer+'"><figure style="margin:0"><img src="" alt="Gambar halaman" style="display:block;max-width:100%;height:auto;border-radius:16px"><figcaption>Keterangan gambar</figcaption></figure></section>';
 if(type==='button')html='<section class="cg-visual-section" style="'+outer+'"><a href="/?page=informasi" style="display:inline-flex;padding:12px 18px;border-radius:10px;background:#155eef;color:#fff;text-decoration:none;font-weight:800">Buka informasi</a></section>';
 if(type==='columns')html='<section class="cg-visual-section" style="'+outer+'"><div class="cg-visual-columns" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px"><div><h2>Kolom kiri</h2><p>Isi penjelasan di sini.</p></div><div><h2>Kolom kanan</h2><p>Tambahkan informasi di sini.</p></div></div></section>';
 if(type==='divider')html='<hr class="cg-visual-section" style="max-width:1100px;margin:34px auto;border:0;border-top:1px solid #d8e0ea">';
 if(type==='spacer')html='<div class="cg-visual-section" style="height:56px" aria-label="Jarak bagian"></div>';
 if(!html)return;
 var temp=doc.createElement('div');temp.innerHTML=html;var element=temp.firstElementChild;
 currentMain.appendChild(element);
 decorateBlocks();selectBlock(element);
 setStatus('Bagian ditambahkan; simpan draft untuk menyimpan.','');
}
document.querySelectorAll('[data-insert]').forEach(function(button){button.addEventListener('click',function(){if(button.dataset.insert==='image'){document.getElementById('imageFile').click();}else addSection(button.dataset.insert);});});
document.getElementById('moveUpButton').addEventListener('click',function(){
 if(!selectedBlock||isProtectedBlock(selectedBlock))return;
 var prev=selectedBlock.previousElementSibling;
 while(prev&&isProtectedBlock(prev))prev=prev.previousElementSibling;
 if(prev)selectedBlock.parentNode.insertBefore(selectedBlock,prev);
 decorateBlocks();selectBlock(selectedBlock);setStatus('Urutan bagian berubah; simpan draft.','');
});
document.getElementById('moveDownButton').addEventListener('click',function(){
 if(!selectedBlock||isProtectedBlock(selectedBlock))return;
 var next=selectedBlock.nextElementSibling;
 while(next&&isProtectedBlock(next))next=next.nextElementSibling;
 if(next)selectedBlock.parentNode.insertBefore(next,selectedBlock);
 decorateBlocks();selectBlock(selectedBlock);setStatus('Urutan bagian berubah; simpan draft.','');
});
document.getElementById('deleteBlockButton').addEventListener('click',function(){
 if(!selectedBlock||isProtectedBlock(selectedBlock))return;
 if(!window.confirm('Hapus bagian yang dipilih?'))return;
 var old=selectedBlock;selectedBlock=null;old.remove();decorateBlocks();selectionInfo.textContent='Bagian dihapus. Simpan draft untuk menyimpan perubahan.';
 setStatus('Bagian dihapus; simpan draft.','');
});
document.getElementById('replaceImageButton').addEventListener('click',function(){document.getElementById('imageFile').click();});
document.getElementById('imageFile').addEventListener('change',function(){
 var file=this.files&&this.files[0];if(!file)return;
 if(file.size>8*1024*1024){setStatus('Ukuran maksimum gambar 8 MB.','error');this.value='';return;}
 if(!/^image\/(jpeg|png|webp|gif)$/i.test(file.type)){setStatus('Gunakan JPG, PNG, WEBP, atau GIF.','error');this.value='';return;}
 uploadImage(file,this);
});
function uploadImage(file,input){
 setStatus('Mengunggah gambar…','');
 var fd=new FormData();fd.append('image',file,file.name);fd.append('csrf_token',csrf);
 fetch('/admin/tampilan/upload_page_image.php',{method:'POST',credentials:'same-origin',body:fd,headers:{'Accept':'application/json'}})
 .then(function(resp){return resp.json().then(function(data){if(!resp.ok||!data.ok)throw new Error(data.message||'Upload gambar gagal.');return data;});})
 .then(function(data){
  if(selectedImage&&currentMain.contains(selectedImage)){
   selectedImage.src=data.url;selectedImage.removeAttribute('srcset');
   var hero=selectedImage.closest('.digital-hero');
   if(hero)hero.style.setProperty('--hero-image','url("'+data.url.replace(/["\\)]/g,'')+'")');
  }else{
   addSection('image');
   var img=selectedBlock&&selectedBlock.querySelector('img');
   if(img)img.src=data.url;
   selectedImage=img||null;
  }
  setStatus('Gambar berhasil diunggah. Simpan Draft atau Publikasikan.','success');
  input.value='';
 }).catch(function(err){setStatus(err.message||'Upload gagal.','error');input.value='';});
}
function getHeroData(){
 var hero=currentMain&&currentMain.querySelector('.digital-hero');
 if(!hero)return null;
 var eyebrow=hero.querySelector('.digital-eyebrow');
 var title=hero.querySelector('h2');
 var subtitle=hero.querySelector('.digital-hero-content p');
 var img=hero.querySelector('.digital-photo img');
 var buttons=hero.querySelectorAll('.digital-actions a');
 return {
  image:img?img.getAttribute('src')||'':'',
  eyebrow:eyebrow?eyebrow.textContent.trim():'',
  title:title?title.textContent.trim():'',
  subtitle:subtitle?subtitle.textContent.trim():'',
  button_text:buttons[0]?buttons[0].textContent.trim():'',
  button_url:buttons[0]?buttons[0].getAttribute('href')||'':'',
  button2_text:buttons[1]?buttons[1].textContent.trim():'',
  button2_url:buttons[1]?buttons[1].getAttribute('href')||'':''
 };
}
function getContentHtml(){
 var clone=currentMain.cloneNode(true);
 clone.querySelectorAll('.cg-vb-selected').forEach(function(el){el.classList.remove('cg-vb-selected');});
 clone.querySelectorAll('[data-cgvb-index]').forEach(function(el){el.removeAttribute('data-cgvb-index');el.removeAttribute('draggable');el.style.removeProperty('cursor');el.removeAttribute('title');});
 clone.querySelectorAll('[contenteditable]').forEach(function(el){el.removeAttribute('contenteditable');});
 clone.querySelectorAll('script').forEach(function(el){el.remove();});
 clone.querySelectorAll('input,textarea,select,button,form').forEach(function(el){el.remove();});
 // Preserve dynamic/live components as placeholders so data is not frozen into a snapshot.
 clone.querySelectorAll('.news-placeholder,.agenda-placeholder').forEach(function(el){el.innerHTML='';});
 clone.querySelectorAll('[data-teachers-block]').forEach(function(el){el.innerHTML='';});
 clone.querySelectorAll('section[data-contact-block]').forEach(function(el){el.innerHTML='';});
 if(pageSlug==='home'){
  clone.querySelectorAll('.digital-hero,.school-live-stats,.school-live-stats-grid').forEach(function(el){el.remove();});
 }
 return clone.innerHTML;
}
function save(action){
 if(!currentMain||!loaded){setStatus('Pratinjau belum siap.','error');return Promise.reject(new Error('Pratinjau belum siap.'));}
 var fd=new FormData();
 fd.append('csrf_token',csrf);fd.append('action',action);fd.append('page_slug',pageSlug);fd.append('page_title',CFG.title);fd.append('content_html',getContentHtml());
 if(pageSlug==='home')fd.append('hero_json',JSON.stringify(getHeroData()||{}));
 setStatus(action==='publish'?'Mempublikasikan…':'Menyimpan draft…','');
 var buttons=[document.getElementById('saveDraftButton'),document.getElementById('publishButton')];buttons.forEach(function(b){b.disabled=true;});
 return fetch('/builder/',{method:'POST',credentials:'same-origin',body:fd,headers:{'Accept':'application/json'}})
 .then(function(resp){return resp.json().then(function(data){if(!resp.ok||!data.ok)throw new Error(data.message||'Operasi gagal.');return data;});})
 .then(function(data){
  setStatus(data.message||'Berhasil.','success');
  if(action==='save_draft'){document.getElementById('draftLabel').textContent='Draft disimpan '+(data.updated_at||'');}
  if(action==='publish'){
   document.getElementById('draftLabel').textContent='Sudah dipublikasikan';
   document.getElementById('viewPublic').href=data.url||currentUrl();
   frame.src=currentUrl()+'&cgvisual='+Date.now();
  }
  return data;
 }).catch(function(err){setStatus(err.message||'Operasi gagal.','error');throw err;})
 .finally(function(){buttons.forEach(function(b){b.disabled=false;});});
}
document.getElementById('saveDraftButton').addEventListener('click',function(){save('save_draft').catch(function(){});});
document.getElementById('publishButton').addEventListener('click',function(){if(window.confirm('Publikasikan perubahan ini ke website publik?'))save('publish').catch(function(){});});
function makeSlug(value){return String(value||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,120);}
var titleInput=document.getElementById('newTitle');var slugInput=document.getElementById('newSlug');var slugTouched=false;
titleInput.addEventListener('input',function(){if(!slugTouched)slugInput.value=makeSlug(titleInput.value);});
slugInput.addEventListener('input',function(){slugTouched=true;slugInput.value=makeSlug(slugInput.value);});
function createPage(){
 var title=titleInput.value.trim();var slug=makeSlug(slugInput.value);
 if(!title||!slug){setStatus('Isi nama dan alamat halaman.','error');return;}
 var fd=new FormData();fd.append('csrf_token',csrf);fd.append('action','create_page');fd.append('new_title',title);fd.append('new_slug',slug);
 var button=document.getElementById('createPageButton');button.disabled=true;setStatus('Membuat halaman…','');
 fetch('/builder/',{method:'POST',credentials:'same-origin',body:fd,headers:{'Accept':'application/json'}})
 .then(function(resp){return resp.json().then(function(data){if(!resp.ok||!data.ok)throw new Error(data.message||'Halaman gagal dibuat.');return data;});})
 .then(function(data){window.location.href=data.url;})
 .catch(function(err){setStatus(err.message||'Halaman gagal dibuat.','error');})
 .finally(function(){button.disabled=false;});
}
document.getElementById('createPageButton').addEventListener('click',createPage);
document.getElementById('newPageButton').addEventListener('click',function(){titleInput.focus();titleInput.scrollIntoView({behavior:'smooth',block:'center'});});
fillPageSelect();
if(frame.contentDocument&&frame.contentDocument.readyState==='complete'){try{decorateMain();}catch(e){}}
})();
</script>
</body>
</html>
