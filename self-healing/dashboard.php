<?php
declare(strict_types=1);

require_once __DIR__ . '/logger.php';

$config = sh_config();
$expected = (string) ($config['access_token'] ?? '');
$provided = (string) ($_SERVER['HTTP_X_SELF_HEALING_TOKEN'] ?? $_GET['token'] ?? '');

$configured = $expected !== '' && $expected !== 'CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN';
$authorized = $configured && hash_equals($expected, $provided);

if (!$authorized) {
    http_response_code($configured ? 403 : 503);
    ?>
    <!doctype html>
    <html lang="id">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Cloud Guru — Self-Healing</title>
      <style>
        body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f4f7fb;margin:0;padding:32px;color:#172033}
        .card{max-width:760px;margin:8vh auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;box-shadow:0 12px 35px #0000000d}
        h1{margin-top:0}.warn{background:#fff7ed;border:1px solid #fed7aa;padding:16px;border-radius:12px}
        code{background:#eef2ff;padding:2px 6px;border-radius:5px}
      </style>
    </head>
    <body><div class="card">
      <h1>🩺 Cloud Guru Self-Healing</h1>
      <?php if (!$configured): ?>
        <div class="warn"><b>Self-Healing belum dikonfigurasi.</b><br>
        Salin <code>config.example.php</code> menjadi <code>config.local.php</code> di server,
        lalu ganti access token dengan token acak yang panjang.</div>
      <?php else: ?>
        <div class="warn"><b>Akses ditolak.</b><br>Gunakan token Self-Healing melalui header <code>X-Self-Healing-Token</code>.</div>
      <?php endif; ?>
    </div></body></html>
    <?php
    exit;
}

$runtime = sh_runtime_dir();
$logFile = $runtime . '/self-healing.log';
$lines = [];
if (is_file($logFile)) {
    $raw = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($raw)) {
        $lines = array_slice(array_reverse($raw), 0, 50);
    }
}

$events = [];
foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (is_array($row)) $events[] = $row;
}

$fatal = 0; $warnings = 0; $checks = 0;
foreach ($events as $e) {
    if (($e['event'] ?? '') === 'fatal_error') $fatal++;
    if (($e['event'] ?? '') === 'php_error') $warnings++;
    if (($e['event'] ?? '') === 'health_check') $checks++;
}

$recentErrors = array_values(array_filter($events, static function(array $e): bool {
    return in_array($e['event'] ?? '', ['fatal_error', 'php_error'], true);
}));

$status = $fatal > 0 ? 'attention' : 'healthy';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cloud Guru — Kesehatan Sistem</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;color:#172033;background:#f4f7fb}
*{box-sizing:border-box}body{margin:0}.wrap{max-width:1100px;margin:auto;padding:28px}
.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}
h1{margin:0 0 5px}.muted{color:#64748b}.badge{padding:8px 13px;border-radius:999px;font-weight:700;background:#dcfce7;color:#166534}
.badge.attention{background:#fee2e2;color:#991b1b}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 8px 25px #00000008}
.num{font-size:30px;font-weight:800;margin-top:7px}.section{margin-top:20px}
table{width:100%;border-collapse:collapse}.scroll{overflow:auto}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:top}
small{color:#64748b}.event{font-weight:700}.fatal{color:#b91c1c}.php{color:#a16207}
.notice{background:#eff6ff;border:1px solid #bfdbfe;padding:14px;border-radius:12px;margin-bottom:18px}
@media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:500px){.grid{grid-template-columns:1fr}.wrap{padding:16px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div><h1>🩺 Kesehatan Sistem</h1><div class="muted">Cloud Guru Self-Healing V1</div></div>
    <div class="badge <?= $status === 'attention' ? 'attention' : '' ?>">● <?= $status === 'attention' ? 'Perlu perhatian' : 'Sistem sehat' ?></div>
  </div>

  <div class="notice">
    <b>Mode V1:</b> monitoring dan audit saja. Sistem <b>belum</b> mengubah kode atau data secara otomatis.
  </div>

  <div class="grid">
    <div class="card"><div class="muted">Fatal error</div><div class="num"><?= $fatal ?></div></div>
    <div class="card"><div class="muted">PHP warning/error</div><div class="num"><?= $warnings ?></div></div>
    <div class="card"><div class="muted">Health check</div><div class="num"><?= $checks ?></div></div>
    <div class="card"><div class="muted">Log ditampilkan</div><div class="num"><?= count($events) ?></div></div>
  </div>

  <div class="card section">
    <h2>🤖 AI Analyzer</h2>
    <p class="muted">Menganalisis error terbaru dan menghasilkan usulan patch. <b>Tidak mengubah file.</b></p>
    <form method="post" action="ai_analyzer.php">
      <input type="hidden" name="token" value="<?= htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" style="border:0;border-radius:10px;padding:11px 16px;font-weight:700;cursor:pointer;background:#172033;color:#fff">Analisis Error dengan AI</button>
    </form>
  </div>

  <div class="card section">
    <h2>Riwayat Error Terbaru</h2>
    <?php if (!$recentErrors): ?>
      <p class="muted">Belum ada error yang tercatat.</p>
    <?php else: ?>
    <div class="scroll"><table>
      <thead><tr><th>Waktu</th><th>Jenis</th><th>Pesan</th><th>File</th><th>Baris</th></tr></thead>
      <tbody>
      <?php foreach ($recentErrors as $e): $ctx=(array)($e['context']??[]); ?>
        <tr>
          <td><small><?= htmlspecialchars((string)($e['time']??'')) ?></small></td>
          <td class="event <?= ($e['event']??'')==='fatal_error'?'fatal':'php' ?>"><?= htmlspecialchars((string)($e['event']??'')) ?></td>
          <td><?= htmlspecialchars((string)($ctx['message']??'')) ?></td>
          <td><small><?= htmlspecialchars((string)($ctx['file']??'')) ?></small></td>
          <td><?= htmlspecialchars((string)($ctx['line']??'')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card section">
    <h2>Audit Self-Healing</h2>
    <div class="scroll"><table>
      <thead><tr><th>Waktu</th><th>Event</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach ($events as $e): ?>
        <tr>
          <td><small><?= htmlspecialchars((string)($e['time']??'')) ?></small></td>
          <td><?= htmlspecialchars((string)($e['event']??'')) ?></td>
          <td><small><?= htmlspecialchars(json_encode($e['context']??[], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
</body>
</html>
