<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi_foto_2026_x7q') {
    http_response_code(403);
    die('Akses ditolak.');
}

$retensiHari = 60;
$root        = __DIR__ . '/../storage/app/public/absensi';
$aksi        = $_GET['aksi'] ?? 'lihat';
$konfirmasi  = ($_GET['konfirmasi'] ?? '') === 'ya';

function folderSize(string $dir): array
{
    $size = 0;
    $count = 0;
    foreach (glob($dir . '/*') as $f) {
        if (is_file($f)) {
            $size += filesize($f);
            $count++;
        }
    }
    return [$size, $count];
}

function formatBytes(int $b): string
{
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024) return round($b / 1024, 2) . ' KB';
    return $b . ' B';
}

if (!is_dir($root)) {
    die('Folder storage/app/public/absensi belum ada / masih kosong.');
}

$rows          = [];
$totalSize     = 0;
$totalFile     = 0;
$expiredSize   = 0;
$expiredFile   = 0;
$expiredFolders = [];

foreach (glob($root . '/*', GLOB_ONLYDIR) as $userDir) {
    $userId = basename($userDir);
    foreach (glob($userDir . '/*', GLOB_ONLYDIR) as $dateDir) {
        $tanggalStr = basename($dateDir);
        $tanggal    = DateTime::createFromFormat('Ymd', $tanggalStr);
        $umur       = $tanggal ? (int) $tanggal->diff(new DateTime())->days : null;
        [$size, $count] = folderSize($dateDir);

        $totalSize += $size;
        $totalFile += $count;

        $kadaluarsa = $umur !== null && $umur > $retensiHari;
        if ($kadaluarsa) {
            $expiredSize += $size;
            $expiredFile += $count;
            $expiredFolders[] = $dateDir;
        }

        $rows[] = [$userId, $tanggalStr, $umur, $count, $size, $kadaluarsa];
    }
}

usort($rows, fn($a, $b) => strcmp($b[1], $a[1]));

$dihapusFile   = 0;
$dihapusFolder = 0;
if ($aksi === 'hapus' && $konfirmasi) {
    foreach ($expiredFolders as $dateDir) {
        foreach (glob($dateDir . '/*') as $f) {
            if (is_file($f) && unlink($f)) $dihapusFile++;
        }
        if (is_dir($dateDir) && count(glob($dateDir . '/*')) === 0) {
            rmdir($dateDir);
            $dihapusFolder++;
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Foto Absen — Cek &amp; Bersihkan</title>
<style>
body{font-family:sans-serif;font-size:14px;padding:16px;color:#1e293b}
table{border-collapse:collapse;width:100%;margin-top:12px}
td,th{border:1px solid #cbd5e1;padding:4px 8px;text-align:left}
tr.expired{background:#fee2e2}
.summary{background:#f0f9ff;padding:12px;border-radius:8px}
</style>
</head>
<body>
<h2>Foto Absen — Cek Ukuran &amp; Bersihkan (retensi <?= $retensiHari ?> hari)</h2>
<div class="summary">
Total sekarang: <b><?= $totalFile ?></b> file, <b><?= formatBytes($totalSize) ?></b><br>
Lebih dari <?= $retensiHari ?> hari (boleh dihapus): <b><?= $expiredFile ?></b> file, <b><?= formatBytes($expiredSize) ?></b>
<?php if ($aksi === 'hapus' && $konfirmasi): ?>
    <br><br><span style="color:#16a34a">✅ Sudah dihapus: <?= $dihapusFile ?> file, <?= $dihapusFolder ?> folder tanggal.</span>
<?php elseif ($expiredFile > 0): ?>
    <br><br>
    <a href="?key=<?= urlencode($key) ?>&aksi=hapus&konfirmasi=ya"
       onclick="return confirm('Yakin hapus <?= $expiredFile ?> foto (<?= formatBytes($expiredSize) ?>) yang lebih dari <?= $retensiHari ?> hari? Ini TIDAK BISA dibatalkan.')"
       style="color:#dc2626;font-weight:bold">🗑️ Hapus semua foto kadaluarsa sekarang</a>
<?php endif; ?>
</div>
<table>
<tr><th>User ID</th><th>Tanggal</th><th>Umur (hari)</th><th>Jml File</th><th>Ukuran</th><th>Status</th></tr>
<?php foreach ($rows as [$userId, $tgl, $umur, $count, $size, $kadaluarsa]): ?>
<tr class="<?= $kadaluarsa ? 'expired' : '' ?>">
<td><?= htmlspecialchars($userId) ?></td>
<td><?= htmlspecialchars($tgl) ?></td>
<td><?= $umur ?? '?' ?></td>
<td><?= $count ?></td>
<td><?= formatBytes($size) ?></td>
<td><?= $kadaluarsa ? 'Kadaluarsa' : 'Simpan' ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
