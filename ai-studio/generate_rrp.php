<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $status, array $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) || !empty($_SESSION['user']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) reply(401, ['error' => 'Sesi login tidak ditemukan. Silakan login ulang ke Cloud Guru.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['error' => 'Gunakan POST.']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) reply(400, ['error' => 'Data tidak valid.']);

$sekolah     = trim((string)($input['sekolah'] ?? 'SDN Ciburayut 01'));
$mapel       = trim((string)($input['mata_pelajaran'] ?? ''));
$kelas       = trim((string)($input['kelas'] ?? ''));
$semester    = trim((string)($input['semester'] ?? ''));
$tema        = trim((string)($input['tema'] ?? ''));
$subTema     = trim((string)($input['sub_tema'] ?? ''));
$materi      = trim((string)($input['materi'] ?? ''));
$alokasi     = trim((string)($input['alokasi_waktu'] ?? '2 x 40 menit'));
$metode      = trim((string)($input['metode'] ?? 'Diskusi dan Tanya Jawab'));
$model       = trim((string)($input['model_pembelajaran'] ?? 'Problem Based Learning'));
$sumber      = trim((string)($input['sumber_belajar'] ?? 'Buku paket, LKS, sumber belajar lainnya'));
$sumberId    = trim((string)($input['sumber_id'] ?? ''));
$teksSumber  = trim((string)($input['teks_sumber'] ?? ''));

// Ambil teks ekstraksi dari sumber yang tersimpan jika ID diberikan
$teksSumberDb = '';
if ($sumberId !== '' && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT extracted_text FROM rpp_sumber_belajar WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$sumberId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['extracted_text'])) {
            $teksSumberDb = (string)$row['extracted_text'];
        }
    } catch (Throwable $e) {
        error_log('RPP sumber fetch error: ' . $e->getMessage());
    }
}

if ($mapel === '' || $kelas === '' || $semester === '' || $tema === '' || $materi === '') {
    reply(422, ['error' => 'Mata Pelajaran, Kelas, Semester, Tema, dan Materi Pokok wajib diisi.']);
}

$apiKey = getenv('OPENAI_API_KEY') ?: '';
$html = '';

if ($apiKey !== '' && extension_loaded('curl')) {
    $prompt = "Buatlah Rencana Pelaksanaan Pembelajaran (RPP) lengkap untuk sekolah dasar kurikulum Indonesia dengan format HTML. Data: Sekolah=$sekolah, Nama Guru=$namaGuru, NIP=$nip, Mapel=$mapel, Kelas=$kelas, Semester=$semester, Tema=$tema, SubTema=$subTema, Materi=$materi, Alokasi=$alokasi, Metode=$metode, Model=$model, Sumber=$sumber. Sertakan: identitas, tujuan, materi pokok, langkah kegiatan (awal/inti/akhir), penilaian, dan sumber belajar." . ($teksSumberDb !== '' ? "\n\nBUKU SUMBER RUJUKAN (isi buku yang diupload guru, gunakan sebagai referensi materi pokok):\n" . $teksSumberDb : "") . ($teksSumber !== '' ? "\n\nCATATAN TAMBAHAN DARI GURU:\n" . $teksSumber : "") . "\nOutput hanya HTML, tanpa penjelasan di luar HTML.";
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.7])
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res !== false && $http >= 200 && $http < 300) {
        $dec = json_decode($res, true);
        if (is_array($dec) && !empty($dec['choices'][0]['message']['content'])) {
            $html = (string)$dec['choices'][0]['message']['content'];
        }
    }
}

if ($html === '') {
    $html = buildLocalRRP($sekolah, $namaGuru, $nip, $mapel, $kelas, $semester, $tema, $subTema, $materi, $alokasi, $metode, $model, $sumber, $teksSumberDb, $teksSumber);
}

reply(200, ['html' => $html]);

function buildLocalRRP(string $s, string $ng, string $nip, string $m, string $k, string $sem, string $t, string $st, string $mat, string $alok, string $met, string $model, string $sum, string $sumberDb = '', string $teksSumber = ''): string {
    $esc = fn(string $x): string => htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subTemaHtml = $st !== '' ? '<tr><th style="width:30%">Sub Tema</th><td>' . $esc($st) . '</td></tr>' : '';
    $sumberTabelHtml = '';
    if ($sumberDb !== '') {
        $sumberTabelHtml = '<tr><th>Sumber Buku (dari upload)</th><td>' . $esc($sumberDb) . '</td></tr>';
    }
    if ($teksSumber !== '') {
        $sumberTabelHtml .= '<tr><th>Catatan Guru</th><td>' . $esc($teksSumber) . '</td></tr>';
    }
    $out = <<<HTML
<div class="rrp-header" style="text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:12px;margin-bottom:16px;">
  <div style="font-size:11px;color:#64748b;letter-spacing:1px;text-transform:uppercase">Rencana Pelaksanaan Pembelajaran</div>
  <div style="font-size:20px;font-weight:800;color:#1e3a8a;margin-top:4px">RPP / Rencana Pelaksanaan Pembelajaran</div>
  <div style="font-size:12px;color:#64748b;margin-top:2px">Kurikulum Sekolah Dasar</div>
</div>

<table>
  <tr><th style="width:30%">Sekolah</th><td>{$esc($s)}</td></tr>\n  <tr><th>Nama Guru</th><td>{$esc($ng)}</td></tr>\n  <tr><th>NIP</th><td>{$esc($nip)}</td></tr>\n  <tr><th>Sumber Belajar</th><td>{$esc($sum)}</td></tr>\n  {$sumberTabelHtml}
  <tr><th>Mata Pelajaran</th><td>{$esc($m)}</td></tr>
  <tr><th>Kelas / Semester</th><td>{$esc($k)} / {$esc($sem)}</td></tr>
  {$subTemaHtml}
  <tr><th>Tema</th><td>{$esc($t)}</td></tr>
  <tr><th>Materi Pokok</th><td>{$esc($mat)}</td></tr>
  <tr><th>Alokasi Waktu</th><td>{$esc($alok)}</td></tr>
  <tr><th>Metode Pembelajaran</th><td>{$esc($met)}</td></tr>
  <tr><th>Model Pembelajaran</th><td>{$esc($model)}</td></tr>
  <tr><th>Sumber Belajar</th><td>{$esc($sum)}</td></tr>
</table>

<h2>I. Tujuan Pembelajaran</h2>
<p>Setelah mengikuti pembelajaran, peserta didik diharapkan dapat:</p>
<ul>
  <li>Memahami konsep inti materi {$esc($mat)} sesuai dengan tema {$esc($t)}.</li>
  <li>Menerapkan pengetahuan yang diperoleh dalam kehidupan sehari-hari.</li>
  <li>Berkontribusi secara aktif dalam kegiatan pembelajaran di kelas.</li>
</ul>

<h2>II. Materi Pokok</h2>
<p>{$esc($mat)}</p>

<h2>III. Metode dan Model Pembelajaran</h2>
<p><strong>Metode:</strong> {$esc($met)}</p>
<p><strong>Model:</strong> {$esc($model)}</p>

<h2>IV. Langkah-Langkah Kegiatan</h2>

<h3>A. Kegiatan Awal (Â±10 menit)</h3>
<ul>
  <li>Guru membuka pelajaran dengan sapaan dan memeriksa kehadiran peserta didik.</li>
  <li>Guru memberikan apersepsi terkait tema {$esc($t)} untuk menarik perhatian peserta didik.</li>
  <li>Guru menyampaikan tujuan pembelajaran yang akan dicapai.</li>
  <li>Guru memberikan motivasi terkait manfaat mempelajari {$esc($m)}.</li>
</ul>

<h3>B. Kegiatan Inti (Â±{$alok})</h3>
<ul>
  <li>Guru menyampaikan materi pokok {$esc($mat)} menggunakan metode {$esc($met)}.</li>
  <li>Peserta didik aktif berdiskusi dan menanyakan hal yang belum dipahami.</li>
  <li>Guru memberikan contoh kasus atau studi kasus terkait tema {$esc($t)}.</li>
  <li>Peserta didik melakukan latihan atau kegiatan praktik secara individu maupun berkelompok.</li>
  <li>Guru memberikan umpan balik dan memperkuat pemahaman peserta didik.</li>
</ul>

<h3>C. Kegiatan Akhir (Â±10 menit)</h3>
<ul>
  <li>Guru dan peserta didik bersama-sama menyimpulkan materi yang telah dipelajari.</li>
  <li>Guru memberikan refleksi singkat tentang kegiatan pembelajaran.</li>
  <li>Guru memberikan tugas rumah atau latihan tambahan (jika diperlukan).</li>
  <li>Guru menutup kegiatan pembelajaran dengan doa dan salam.</li>
</ul>

<h2>V. Penilaian</h2>
<ul>
  <li><strong>Pengetahuan:</strong> Tes tertulis, kuis, dan tugas.</li>
  <li><strong>Keterampilan:</strong> Penilaian kinerja dalam praktik dan presentasi.</li>
  <li><strong>Sikap:</strong> Observasi terhadap sikap religius, nasionalisme, gotong royong, dan integritas selama pembelajaran berlangsung.</li>
</ul>

<h2>VI. Sumber Belajar</h2>
<p>{$esc($sum)}</p>

<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;text-align:center;">
  RPP ini dihasilkan oleh AI Studio Cloud Guru SDN Ciburayut 01. Silakan disesuaikan kembali oleh guru mata pelajaran sebelum digunakan.
</div>
HTML;
    return $out;
}
