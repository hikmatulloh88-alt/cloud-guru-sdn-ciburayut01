<?php
declare(strict_types=1);

/**
 * Statistik beranda. Hanya membaca database; tidak mengubah data.
 * Mendukung nama tabel yang umum digunakan pada aplikasi sekolah.
 */
if (!function_exists('school_stats_tables')) {
 function school_stats_tables(PDO $pdo): array {
  try { return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); }
  catch (Throwable $e) { return []; }
 }
}
if (!function_exists('school_stats_find_table')) {
 function school_stats_find_table(PDO $pdo, array $preferred, array $hints): ?string {
  $tables=school_stats_tables($pdo);
  foreach ($preferred as $want) foreach ($tables as $table) if (strcasecmp((string)$table,$want)===0) return (string)$table;
  foreach ($tables as $table) {
   $name=strtolower((string)$table);
   foreach ($hints as $hint) if (strpos($name,$hint)!==false) return (string)$table;
  }
  return null;
 }
}
if (!function_exists('school_stats_columns')) {
 function school_stats_columns(PDO $pdo, string $table): array {
  if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) return [];
  try { return $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN); }
  catch (Throwable $e) { return []; }
 }
}
if (!function_exists('school_stats_count_table')) {
 function school_stats_count_table(PDO $pdo, ?string $table, bool $activeOnly=false): int {
  if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/',$table)) return 0;
  try {
   $cols=school_stats_columns($pdo,$table); $where='';
   if ($activeOnly) {
    foreach (['is_active','aktif','status'] as $col) {
     if (!in_array($col,$cols,true)) continue;
     if ($col==='status') {
      $values=$pdo->query('SELECT DISTINCT `status` FROM `'.$table.'` LIMIT 30')->fetchAll(PDO::FETCH_COLUMN);
      foreach ($values as $v) if (in_array(strtolower(trim((string)$v)),['aktif','active','1'],true)) {
       $where=' WHERE LOWER(TRIM(`status`)) IN ("aktif","active","1")'; break 2;
      }
      continue;
     }
     $where=' WHERE `'.$col.'` IN (1,"1","Aktif","aktif","active","Active")'; break;
    }
   }
   return max(0,(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`'.$where)->fetchColumn());
  } catch (Throwable $e) { return 0; }
 }
}
if (!function_exists('school_stats_photo_count')) {
 function school_stats_photo_count(PDO $pdo, ?string $table): int {
  if (!$table) return 0;
  $cols=school_stats_columns($pdo,$table);
  foreach (['foto','photo','photo_url','foto_guru','gambar','foto_siswa','photo_path','image'] as $col) {
   if (!in_array($col,$cols,true)) continue;
   try { return max(0,(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$col.'` IS NOT NULL AND TRIM(`'.$col.'`)<>""')->fetchColumn()); }
   catch (Throwable $e) { return 0; }
  }
  return 0;
 }
}
if (!function_exists('renderSchoolStats')) {
 function renderSchoolStats(PDO $pdo): string {
  $studentsTable=school_stats_find_table($pdo,['siswa','students','peserta_didik','data_siswa','student'],['siswa','student','peserta_didik']);
  $teachersTable=school_stats_find_table($pdo,['guru','teachers','data_guru','teacher','profil_guru'],['guru','teacher','pendidik']);
  $students=school_stats_count_table($pdo,$studentsTable,true);
  $teachers=school_stats_count_table($pdo,$teachersTable,true);
  $profiles=school_stats_count_table($pdo,$teachersTable,true);
  $photos=school_stats_photo_count($pdo,$teachersTable);
  $items=[['👨‍🎓',$students,'Siswa Aktif','student'],['👩‍🏫',$teachers,'Guru & Tenaga Pendidik','teacher'],['📋',$profiles,'Profil Guru','profile'],['📸',$photos,'Guru dengan Foto','photo']];
  $html='<section class="school-live-stats"><div class="school-live-stats-head"><span class="eyebrow">DATA SEKOLAH TERKINI</span><h2>SDN Ciburayut 01</h2><p>Ringkasan data sekolah yang tersedia pada sistem.</p></div><div class="school-live-stats-grid">';
  foreach($items as [$icon,$value,$label,$class]) $html.='<article class="school-live-stat '.htmlspecialchars($class,ENT_QUOTES,'UTF-8').'"><span class="school-live-icon">'.$icon.'</span><strong>'.number_format((int)$value,0,',','.').'</strong><small>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</small></article>';
  return $html.'</div></section>';
 }
}
