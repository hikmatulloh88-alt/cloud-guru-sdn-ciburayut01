<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['guru_id']) || !empty($_SESSION['user']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']) || !empty($_SESSION['cloud_guru_user']);
if (!$loggedIn) { $next = rawurlencode('/ai-studio/sumber.php'); header('Location: /auth/login.php?next=' . $next); exit; }
require_once dirname(__DIR__) . '/includes/sidebar.php';
require_once dirname(__DIR__) . '/config/database.php';

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['guru_id'] ?? 0);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rpp_sumber_belajar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        nama_file_asli VARCHAR(255) NOT NULL,
        nama_file_simpan VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        extracted_text LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log('RPP sumber table error: ' . $e->getMessage());
}

$uploadMsg = '';
$uploadType = '';

// --- Handle upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload'])) {
    if (!isset($_FILES['file_sumber']) || $_FILES['file_sumber']['error'] !== UPLOAD_ERR_OK) {
        $uploadMsg = 'Gagal mengunggah file.';
        $uploadType = 'error';
    } else {
        $file = $_FILES['file_sumber'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','doc','docx','txt'], true)) {
            $uploadMsg = 'Hanya PDF, Word (.doc/.docx), dan TXT.';
            $uploadType = 'error';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $uploadMsg = 'Ukuran maksimal 10 MB.';
            $uploadType = 'error';
        } else {
            $uploadDir = dirname(__DIR__) . '/storage/sumber_rpp/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $namaSimpan = uniqid('sumber_', true) . '.' . $ext;
            $simpanPath = $uploadDir . $namaSimpan;
            if (move_uploaded_file($file['tmp_name'], $simpanPath)) {
                $extracted = '';
                try {
                    if ($ext === 'txt') {
                        $extracted = @file_get_contents($simpanPath) ?: '';
                    } elseif ($ext === 'docx') {
                        $zip = new ZipArchive();
                        if ($zip->open($simpanPath) === true) {
                            $xml = $zip->getFromName('word/document.xml');
                            $zip->close();
                            if ($xml !== false) {
                                $xml = strip_tags($xml);
                                $xml = preg_replace('/\s+/', ' ', $xml);
                                $extracted = trim(html_entity_decode($xml, ENT_QUOTES, 'UTF-8'));
                            }
                        }
                    } elseif ($ext === 'pdf') {
                        $cmd = 'pdftotext ' . escapeshellarg($simpanPath) . ' -';
                        $extracted = @shell_exec($cmd) ?: '';
                        if (!$extracted) $extracted = '(Teks PDF tidak dapat diekstraksi otomatis.)';
                    } elseif ($ext === 'doc') {
                        $extracted = '(File .doc lama tidak didukung ekstraksi otomatis.)';
                    }
                } catch (Throwable $e) {
                    $extracted = '(Gagal ekstraksi: ' . $e->getMessage() . ')';
                }
                $teksTambahan = trim((string)($_POST['teks_tambahan'] ?? ''));
                if ($teksTambahan !== '') {
                    $extracted = ($extracted ? $extracted . "\n\n" : '') . $teksTambahan;
                }
                $stmt = $pdo->prepare("INSERT INTO rpp_sumber_belajar (user_id, nama_file_asli, nama_file_simpan, file_path, file_type, file_size, extracted_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $file['name'], $namaSimpan, $simpanPath, $file['type'] ?? '', (int)$file['size'], $extracted]);
                $uploadMsg = 'Berhasil diunggah: ' . htmlspecialchars($file['name']);
                $uploadType = 'success';
            } else {
                $uploadMsg = 'Gagal menyimpan file.';
                $uploadType = 'error';
            }
        }
    }
}

// --- Handle hapus ---
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT file_path FROM rpp_sumber_belajar WHERE id=? AND user_id=? LIMIT 1");
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        @unlink($row['file_path']);
        $pdo->prepare("DELETE FROM rpp_sumber_belajar WHERE id=? AND user_id=?")->execute([$id, $userId]);
        $uploadMsg = 'File berhasil dihapus.';
        $uploadType = 'success';
    }
}

// --- Daftar sumber ---
$daftar = [];
try {
    $stmt = $pdo->prepare("SELECT id, nama_file_asli, file_type, file_size, created_at, extracted_text IS NOT NULL AND extracted_text <> '' AS ada_teks FROM rpp_sumber_belajar WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('RPP sumber fetch error: ' . $e->getMessage());
}

function ukuranFormat($bytes) {
    $u = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return round($bytes, 2) . ' ' . $u[$i];
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sumber Belajar — AI Studio</title>
<link rel="stylesheet" href="/assets/css/glass-ui.css?v=4">
<style>
body{margin:0;background:#f0f5fc;color:#0f172a;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
.cg-main-content{min-height:100vh;padding:34px 40px 64px}
.shell{max-width:900px;margin:0 auto}
.card{border:1px solid rgba(255,255,255,.9);border-radius:20px;padding:24px;background:rgba(255,255,255,.84);box-shadow:0 12px 30px rgba(15,35,68,.06);margin-bottom:20px}
h2{margin:0 0 10px;font-size:20px;font-weight:800;color:#0f172a}
.label{display:block;font-size:12.5px;font-weight:700;color:#64748b;margin-top:12px;margin-bottom:6px}
input,select,textarea{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:10px 13px;font:inherit;font-size:13.5px}
textarea{min-height:140px;resize:vertical}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:none;border-radius:13px;background:#1d4ed8;color:#fff;font-weight:700;cursor:pointer;text-decoration:none}
.btn:hover{background:#1e40af}
.btn-outline{border:1px solid #cbd5e1;background:#fff;color:#1e293b}
.btn-outline:hover{border-color:#2563eb}
.btn-sm{padding:6px 10px;font-size:12px}
.btn-danger{border:1px solid #fca5a5;background:#fff;color:#b91c1c}
.btn-danger:hover{background:#fef2f2;border-color:#fecaca}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{border:1px solid #e2e8f0;padding:8px 10px;text-align:left}
th{background:#f1f5f9;color:#475569;font-weight:700}
tr:nth-child(even){background:#f8fafc}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.msg{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13.5px}
.msg.success{background:#ecfdf5;color:#065f46;border:1px solid #bbf7d0}
.msg.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
</style>
</head>
<body>
<main class="cg-main-content">
<div class="shell">
<?php if ($uploadMsg): ?>
<div class="msg <?= $uploadType ?>"><?= htmlspecialchars($uploadMsg) ?></div>
<?php endif; ?>

<div class="card">
<h2>📚 Upload Buku Sumber RPP</h2>
<p style="color:#64748b;font-size:13.5px">Unggah buku paket, modul, atau referensi (PDF / Word / TXT). Sistem akan mencoba membaca isi file agar AI menghasilkan RPP yang lebih akurat dan relevan.</p>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action_upload" value="1">
<div class="label">Pilih File</div>
<input type="file" name="file_sumber" accept=".pdf,.doc,.docx,.txt" required>
<div class="label">Tambahan Teks (opsional)</div>
<textarea name="teks_tambahan" placeholder="Salin bagian penting dari buku yang ingin dijadikan acuan RPP..."></textarea>
<button type="submit" class="btn">📤 Unggah &amp; Simpan</button>
</form>
</div>

<div class="card">
<h2>Daftar Sumber Belajar</h2>
<?php if (!$daftar): ?>
<p style="color:#64748b">Belum ada file sumber yang diunggah.</p>
<?php else: ?>
<table>
<thead><tr><th>File</th><th>Tipe</th><th>Ukuran</th><th>Teks</th><th>Diupload</th><th>Aksi</th></tr></thead>
<tbody>
<?php foreach ($daftar as $row): ?>
<tr>
<td><?= htmlspecialchars($row['nama_file_asli']) ?></td>
<td><span class="badge"><?= strtoupper(pathinfo($row['nama_file_asli'], PATHINFO_EXTENSION)) ?></span></td>
<td><?= ukuranFormat((int)$row['file_size']) ?></td>
<td><?= $row['ada_teks'] ? '✓' : '✗' ?></td>
<td><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
<td><a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus sumber ini?')">Hapus</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<p><a href="/ai-studio/rrp.php" class="btn btn-outline">⬅ Kembali ke Pembuat RPP</a></p>
</div>
</main>
</body>
</html>