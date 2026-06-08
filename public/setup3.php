<?php
define('SECRET_KEY', 'canopi2026');
define('APP_PATH', '/home/u8221523/public_html/app');
define('ARTISAN', '/home/u8221523/public_html/app/artisan');

if (!isset($_GET['key']) || $_GET['key'] !== SECRET_KEY) {
    http_response_code(403);
    die('<h2 style="color:red;font-family:sans-serif;">❌ Akses ditolak.</h2>');
}

function runCmd($cmd) {
    $output = '';
    $code = 0;
    if (function_exists('proc_open')) {
        $desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
        $proc = proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
        } else {
            $output = 'proc_open gagal';
            $code = 1;
        }
    } elseif (function_exists('shell_exec')) {
        $output = shell_exec($cmd . ' 2>&1');
    } else {
        $output = 'Tidak ada fungsi eksekusi tersedia';
        $code = 1;
    }
    return ['output' => trim($output ?: '(kosong)'), 'success' => $code === 0];
}

function artisan($php, $command) {
    $art = ARTISAN;
    $cmd = "$php -d allow_url_fopen=On -d display_errors=On $art $command 2>&1";
    return runCmd($cmd);
}

$results = [];
$run = $_GET['run'] ?? '';
$php_bin = $_GET['php'] ?? '/usr/local/bin/php8.3';

// Daftar path PHP yang mungkin ada di Niagahoster
$php_candidates = [
    '/usr/local/bin/php8.3',
    '/usr/local/bin/php83',
    '/usr/local/bin/php8.3-cli',
    '/usr/bin/php8.3',
    '/opt/cpanel/ea-php83/root/usr/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
];

// Diagnostik
if ($run === 'diag') {
    // Cek semua path PHP
    foreach ($php_candidates as $p) {
        $res = runCmd("$p -v 2>&1");
        $results[] = ['label' => "PHP path: $p", 'res' => $res];
    }
    // Cek artisan langsung
    $results[] = ['label' => 'Cek file artisan', 'res' => runCmd("ls -la " . ARTISAN . " 2>&1")];
    $results[] = ['label' => 'Cek .env', 'res' => runCmd("cat " . APP_PATH . "/.env 2>&1 | head -20")];
    $results[] = ['label' => 'which php', 'res' => runCmd("which php 2>&1")];
    $results[] = ['label' => 'find php8.3', 'res' => runCmd("find /usr/local/bin /usr/bin /opt -name 'php8*' 2>/dev/null | head -10")];
}
elseif ($run === 'all') {
    $steps = [
        'key:generate --force' => '🔑 Generate App Key',
        'migrate --force'      => '🗄️ Migrate Database',
        'db:seed --force'      => '🌱 Seed Database',
        'storage:link --force' => '🔗 Storage Link',
        'config:cache'         => '⚡ Config Cache',
        'route:cache'          => '🚀 Route Cache',
        'view:cache'           => '👁️ View Cache',
    ];
    foreach ($steps as $cmd => $label) {
        $res = artisan($php_bin, $cmd);
        $results[] = ['label' => $label, 'cmd' => $cmd, 'res' => $res];
    }
}
elseif ($run !== '') {
    $allowed = [
        'key:generate --force','migrate --force','migrate:fresh --seed --force',
        'db:seed --force','storage:link --force','config:cache','config:clear',
        'route:cache','route:clear','view:cache','view:clear','cache:clear',
        'optimize:clear','optimize',
    ];
    if (in_array($run, $allowed)) {
        $res = artisan($php_bin, $run);
        $results[] = ['label' => "▶️ $run", 'cmd' => $run, 'res' => $res];
    }
}

$php_ver    = phpversion();
$art_ok     = file_exists(ARTISAN) ? '✅ Ada' : '❌ Tidak ada';
$env_ok     = file_exists(APP_PATH.'/.env') ? '✅ Ada' : '❌ Tidak ada';
$vendor_ok  = is_dir(APP_PATH.'/vendor') ? '✅ Ada' : '❌ Tidak ada';
$storage_ok = is_dir(APP_PATH.'/storage') ? '✅ Ada' : '❌ Tidak ada';
$proc_ok    = function_exists('proc_open') ? '✅ Tersedia' : '❌ Diblokir';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CanopiBSD v2 — Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:16px}
.wrap{max-width:800px;margin:0 auto}
h1{text-align:center;color:#f59e0b;font-size:1.5rem;padding:20px 0 4px}
.sub{text-align:center;color:#64748b;font-size:.8rem;margin-bottom:20px}
.card{background:#1e293b;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #334155}
.card h2{color:#f59e0b;font-size:.9rem;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #334155}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.info{background:#0f172a;border-radius:6px;padding:8px 12px}
.info .lbl{font-size:.7rem;color:#64748b;margin-bottom:2px}
.info .val{font-size:.85rem}
.warn{background:#451a03;border:1px solid #92400e;border-radius:6px;padding:10px 14px;color:#fcd34d;font-size:.8rem;margin-bottom:12px}
.btn-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.btn{display:block;text-align:center;padding:10px;border-radius:6px;text-decoration:none;font-size:.82rem;font-weight:600;cursor:pointer}
.btn:hover{opacity:.85}
.yellow{background:#f59e0b;color:#0f172a}
.blue{background:#3b82f6;color:#fff}
.green{background:#22c55e;color:#0f172a}
.red{background:#ef4444;color:#fff}
.gray{background:#475569;color:#fff}
.purple{background:#8b5cf6;color:#fff}
.full{grid-column:1/-1}
.res-item{background:#0f172a;border-radius:6px;margin-bottom:10px;border:1px solid #334155;overflow:hidden}
.res-head{padding:8px 12px;display:flex;align-items:center;gap:8px}
.res-head.ok{border-left:4px solid #22c55e}
.res-head.err{border-left:4px solid #ef4444}
.res-lbl{flex:1;font-size:.85rem;font-weight:600}
.badge{font-size:.7rem;padding:2px 8px;border-radius:20px}
.bok{background:#14532d;color:#86efac}
.berr{background:#450a0a;color:#fca5a5}
pre{background:#020617;padding:10px 12px;font-size:.75rem;line-height:1.6;color:#94a3b8;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.php-sel{background:#0f172a;border:1px solid #475569;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:.82rem;width:100%}
.note{text-align:center;color:#475569;font-size:.72rem;padding:16px 0}
@media(max-width:500px){.grid2,.btn-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<h1>⚙️ CanopiBSD v2 — Server Setup</h1>
<p class="sub">app.kanopibsd.co.id · Laravel 11 · PHP <?= htmlspecialchars($php_ver) ?></p>

<div class="card">
  <h2>📋 Status Server</h2>
  <div class="grid2">
    <div class="info"><div class="lbl">PHP Version</div><div class="val"><?= htmlspecialchars($php_ver) ?></div></div>
    <div class="info"><div class="lbl">artisan</div><div class="val"><?= $art_ok ?></div></div>
    <div class="info"><div class="lbl">.env</div><div class="val"><?= $env_ok ?></div></div>
    <div class="info"><div class="lbl">vendor/</div><div class="val"><?= $vendor_ok ?></div></div>
    <div class="info"><div class="lbl">storage/</div><div class="val"><?= $storage_ok ?></div></div>
    <div class="info"><div class="lbl">proc_open</div><div class="val"><?= $proc_ok ?></div></div>
  </div>
</div>

<div class="card">
  <h2>🔧 Pilih Path PHP CLI</h2>
  <p style="font-size:.8rem;color:#94a3b8;margin-bottom:10px">Klik <strong style="color:#8b5cf6">🔍 Diagnostik</strong> dulu untuk cari path PHP 8.3 yang benar, lalu pilih dari dropdown sebelum klik Jalankan SEMUA.</p>
  <select class="php-sel" id="phpSel">
    <?php foreach($php_candidates as $p): ?>
    <option value="<?= $p ?>" <?= $p===$php_bin?'selected':'' ?>><?= $p ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="card">
  <h2>🚀 Jalankan Setup</h2>
  <div class="warn">⚠️ <strong>MIGRATE FRESH</strong> hapus semua data. Hanya untuk setup awal!</div>
  <div class="btn-grid">
    <a href="#" onclick="goRun('all')" class="btn yellow full">▶️ Jalankan SEMUA (Key → Migrate → Seed → Storage → Cache)</a>
    <a href="?key=canopi2026&run=diag" class="btn purple full">🔍 Diagnostik — Cari Path PHP yang Benar</a>
    <a href="#" onclick="goRun('key:generate --force')" class="btn blue">🔑 Generate App Key</a>
    <a href="#" onclick="goRun('migrate --force')" class="btn green">🗄️ Migrate</a>
    <a href="#" onclick="goRun('db:seed --force')" class="btn blue">🌱 Seed Database</a>
    <a href="#" onclick="goRun('storage:link --force')" class="btn blue">🔗 Storage Link</a>
    <a href="#" onclick="goRun('config:cache')" class="btn gray">⚡ Config Cache</a>
    <a href="#" onclick="goRun('route:cache')" class="btn gray">🚀 Route Cache</a>
    <a href="#" onclick="goRun('view:cache')" class="btn gray">👁️ View Cache</a>
    <a href="#" onclick="goRun('optimize:clear')" class="btn gray">🧹 Clear Cache</a>
    <a href="#" onclick="goRun('optimize')" class="btn gray">✨ Optimize</a>
    <a href="#" onclick="goRun('migrate:fresh --seed --force')" class="btn red full">💣 MIGRATE FRESH + SEED (Hapus semua data!)</a>
  </div>
</div>

<?php if (!empty($results)): ?>
<div class="card">
  <h2>📄 Hasil Eksekusi</h2>
  <?php foreach ($results as $r): ?>
  <div class="res-item">
    <div class="res-head <?= $r['res']['success']?'ok':'err' ?>">
      <span class="res-lbl"><?= htmlspecialchars($r['label']) ?></span>
      <span class="badge <?= $r['res']['success']?'bok':'berr' ?>"><?= $r['res']['success']?'SUKSES':'ERROR' ?></span>
    </div>
    <pre><?= htmlspecialchars($r['res']['output']) ?></pre>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<p class="note">⚠️ Hapus file ini setelah setup selesai untuk keamanan server.</p>
</div>

<script>
function goRun(cmd) {
  var php = document.getElementById('phpSel').value;
  window.location.href = '?key=canopi2026&run=' + encodeURIComponent(cmd) + '&php=' + encodeURIComponent(php);
}
</script>
</body>
</html>
