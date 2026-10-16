<?php
declare(strict_types=1);

/* Statistik beranda: baca tabel yang tersedia, tanpa mengubah data. */
if (!function_exists('school_stats_table_exists')) {
 function school_stats_table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
  try { $q=$pdo->prepare('SHOW TABLES LIKE ?'); $q->execute([$table]); return (bool)$q->fetchColumn(); }
  catch (Throwable $e) { return false; }
 }
}
if (!function_exists('school_stats_count')) {
 function school_stats_count(PDO $pdo, array $tables, array $activeColumns=[]): int {
  foreach ($tables as $table) {
   if (!school_stats_table_exists($pdo,$table)) continue;
   try {
    $cols=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
    $where='';
    foreach ($activeColumns as $col) if (in_array($col,$cols,true)) { $where=' WHERE `'.$col.'` IN (1, "1", "Aktif", "aktif", "active", "Active")'; break; }
    return max(0,(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`'.$where)->fetchColumn());
   } catch (Throwable $e) { continue; }
  }
  return 0;
 }
}
if (!function_exists('renderSchoolStats')) {
 function renderSchoolStats(PDO $pdo): string {
  $students=school_stats_count($pdo,['siswa','students','peserta_didik','data_siswa','student'],['status','is_active','aktif']);
  $teachers=school_stats_count($pdo,['guru','teachers','data_guru','teacher'],['status','is_active','aktif']);
  $profiles=school_stats_count($pdo,['teachers','guru','profil_guru','data_guru'],['is_active','status','aktif']);
  $photos=0;
  foreach (['teachers','guru','profil_guru','data_guru'] as $table) {
   if (!school_stats_table_exists($pdo,$table)) continue;
   try {
    $cols=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
    $photoCol=null;
    foreach (['foto','photo','photo_url','foto_guru','gambar'] as $col) if (in_array($col,$cols,true)) { $photoCol=$col; break; }
    if ($photoCol===null) continue;
    $photos=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$photoCol.'` IS NOT NULL AND TRIM(`'.$photoCol.'`) <> ""')->fetchColumn();
    break;
   } catch (Throwable $e) { continue; }
  }
  $items=[['👨‍🎓',$students,'Siswa Aktif','student'],['👩‍🏫',$teachers,'Guru dan Tenaga Pendidik','teacher'],['📋',$profiles,'Profil Guru','profile'],['📸',$photos,'Guru dengan Foto','photo']];
  $html='<section class="school-live-stats"><div class="school-live-stats-head"><span class="eyebrow">DATA SEKOLAH</span><h2>Statistik Sekolah</h2><p>Data terkini yang tersedia pada sistem sekolah.</p></div><div class="school-live-stats-grid">';
  foreach ($items as [$icon,$value,$label,$class]) $html.='<article class="school-live-stat '.htmlspecialchars($class,ENT_QUOTES,'UTF-8').'"><span class="school-live-icon">'.$icon.'</span><strong>'.number_format((int)$value,0,',','.').'</strong><small>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</small></article>';
  return $html.'</div></section>';
 }
}
