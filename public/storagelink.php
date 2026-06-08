<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'canopi2026') {
    die('<h2 style="color:red;font-family:sans-serif">❌ Akses ditolak.</h2>');
}

$publicStorage = '/home/u8221523/public_html/app/public/storage';
$realStorage   = '/home/u8221523/public_html/app/storage/app/public';
$results = [];

// Pastikan folder storage/app/public ada
if (!is_dir($realStorage)) {
    mkdir($realStorage, 0755, true);
    $results[] = ['✅ Dibuat', 'storage/app/public/'];
}

// Cek apakah sudah ada symlink atau folder
if (is_link($publicStorage)) {
    $results[] = ['✅ Sudah ada', 'public/storage (symlink)'];
} elseif (is_dir($publicStorage)) {
    $results[] = ['✅ Sudah ada', 'public/storage (folder)'];
} else {
    // Coba symlink dulu
    if (@symlink($realStorage, $publicStorage)) {
        $results[] = ['✅ Symlink berhasil', 'public/storage → storage/app/public'];
    } else {
        // Symlink gagal → buat folder biasa + copy file
        if (@mkdir($publicStorage, 0755, true)) {
            $results[] = ['✅ Folder dibuat', 'public/storage/ (tanpa symlink)'];
            
            // Copy file yang ada di storage/app/public ke public/storage
            $files = glob($realStorage . '/*');
            if ($files) {
                foreach ($files as $file) {
                    $dest = $publicStorage . '/' . basename($file);
                    if (is_file($file)) {
                        copy($file, $dest);
                        $results[] = ['✅ Copy', basename($file)];
                    }
                }
            } else {
                $results[] = ['ℹ️ Info', 'storage/app/public masih kosong (normal untuk install baru)'];
            }
        } else {
            $results[] = ['❌ Gagal', 'Tidak bisa buat public/storage'];
        }
    }
}

// Fix permission
@chmod($publicStorage, 0755);
@chmod($realStorage, 0755);
$results[] = ['✅ Permission', 'public/storage = 755'];

$allOk = !in_array(false, array_map(fn($r) => strpos($r[0], '❌') === false, $results));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Storage Link Fix</title>
<style>
body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;padding:20px}
.wrap{max-width:600px;margin:0 auto}
h1{color:#f59e0b;text-align:center;margin-bottom:20px}
.status{border-radius:8px;padding:14px;text-align:center;font-weight:700;margin-bottom:16px;font-size:1rem}
.ok{background:#14532d;color:#86efac}
.err{background:#450a0a;color:#fca5a5}
.log{background:#020617;border-radius:8px;padding:12px}
.row{font-size:.82rem;padding:4px 0;border-bottom:1px solid #0f172a}
</style>
</head>
<body>
<div class="wrap">
<h1>🔗 Storage Link Fix</h1>
<div class="status <?= $allOk?'ok':'err' ?>">
<?= $allOk ? '✅ Storage link berhasil dibuat!' : '⚠️ Ada masalah, cek log di bawah' ?>
</div>
<div class="log">
<?php foreach($results as $r): ?>
<div class="row"><?= $r[0] ?> — <?= htmlspecialchars($r[1]) ?></div>
<?php endforeach; ?>
</div>
</div>
</body>
</html>
