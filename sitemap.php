<?php
require_once __DIR__.'/config/database.php';
header('Content-Type: application/xml; charset=utf-8');
$urls=['https://sdnciburayut01.com/'];
try{$rows=$pdo->query("SELECT slug FROM website_builder_pages WHERE slug<>'' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);foreach($rows as $slug){$slug=preg_replace('/[^a-z0-9-]/','',(string)$slug);if($slug && $slug!=='home')$urls[]='https://sdnciburayut01.com/?page='.rawurlencode($slug);}}catch(Throwable $e){}
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach(array_unique($urls) as $u)echo '<url><loc>'.htmlspecialchars($u,ENT_XML1,'UTF-8').'</loc></url>';
echo '</urlset>';
