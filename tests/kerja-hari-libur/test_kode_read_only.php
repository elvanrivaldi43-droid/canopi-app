<?php
// FILE: tests/kerja-hari-libur/test_kode_read_only.php
// Jalankan: php tests/kerja-hari-libur/test_kode_read_only.php
//
// TASK 6 — Membuka halaman "Kode Absen Hari Ini" TIDAK boleh membungkam Telegram cron.
//
// Cacatnya: `AbsensiController::kodeHariIni()` (sebuah GET) memanggil
// `KodeAbsen::kodeHariIniUntuk()`, yang MEMBUAT baris kode kalau belum ada.
// Padahal cron pagi memutuskan "kirim atau tidak" dari `wasRecentlyCreated`:
// kalau barisnya sudah ada, dia anggap kodenya sudah pernah dikirim lalu `continue`.
//
// Jadi kalau Owner/Mandor iseng membuka halaman itu SEBELUM cron jam 06:30 jalan,
// seluruh baris kode terlanjur dibuat lewat GET, dan cron kemudian melewati
// SEMUA karyawan tanpa mengirim satu pesan Telegram pun. Karyawan tidak dapat kode
// pagi itu, dan tidak ada error apa pun yang muncul — persis kelas kegagalan senyap
// yang sudah pernah kejadian di project ini (insiden kode terkirim 4x, 6 Agustus).
//
// Aturan barunya:
//   - GET = baca saja. Tidak pernah membuat baris kode.
//   - Cron pagi = satu-satunya pembuat+pengirim kode reguler.
//   - Aktivasi kerja hari libur = tetap membuat+mengirim kode pribadi seketika.
//   - Karyawan aktif yang belum punya kode setelah cron = tombol POST manual
//     "Buat & Kirim Kode" untuk Owner/Mandor, atomik, kirim sekali.
require __DIR__ . '/../bootstrap.php';

use App\Models\KodeAbsen;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Buang komentar sebelum memeriksa "apakah method ini dipanggil" — kalau tidak,
// komentar yang MENJELASKAN kenapa panggilan lama dihapus malah terhitung sebagai
// panggilan, dan tes jadi menuduh kode yang sudah benar.
$tanpaKomentar = function (string $kode): string {
    $bersih = '';
    foreach (token_get_all("<?php\n" . $kode) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $bersih .= is_array($t) ? $t[1] : $t;
    }
    return $bersih;
};

$srcModel = file_get_contents($base . '/app/Models/KodeAbsen.php');
$srcCtrl  = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');
$srcCron  = file_get_contents($base . '/public/cron-kode-absen.php');
$srcView  = file_get_contents($base . '/resources/views/absensi/kode-hari-ini.blade.php');
$srcRoute = file_get_contents($base . '/routes/web.php');

// ═══════════════════════════════════════════════════════════
// 1. Model punya pembacaan yang BENAR-BENAR read-only
// ═══════════════════════════════════════════════════════════
$check('KodeAbsen punya pembaca read-only kodeHariIniUntukJikaAda()',
    method_exists(KodeAbsen::class, 'kodeHariIniUntukJikaAda'), true);

$posBaca  = strpos($srcModel, 'function kodeHariIniUntukJikaAda(');
$bodyBaca = $posBaca === false ? '' : $tanpaKomentar(substr($srcModel, $posBaca, 500));

$check('pembaca read-only TIDAK memakai createOrFirst',
    str_contains($bodyBaca, 'createOrFirst'), false);
$check('pembaca read-only TIDAK memakai create(',
    str_contains($bodyBaca, '::create('), false);
$check('pembaca read-only TIDAK memanggil barisHariIniUntuk()',
    str_contains($bodyBaca, 'barisHariIniUntuk('), false);
$check('pembaca read-only memakai query baca biasa (first)',
    str_contains($bodyBaca, '->first('), true);

// ═══════════════════════════════════════════════════════════
// 2. GET kodeHariIni() tidak pernah membuat kode
// ═══════════════════════════════════════════════════════════
$posGet  = strpos($srcCtrl, 'function kodeHariIni(');
$posEnd  = $posGet === false ? false : strpos($srcCtrl, 'public function', $posGet + 20);
$bodyGet = $posGet === false ? '' : substr($srcCtrl, $posGet, ($posEnd ?: strlen($srcCtrl)) - $posGet);

$check('badan kodeHariIni() terbaca', strlen($bodyGet) > 300, true);

$getKode = $tanpaKomentar($bodyGet);
$check('kodeHariIni() TIDAK memanggil kodeHariIniUntuk() (pembuat kode)',
    str_contains($getKode, 'kodeHariIniUntuk('), false);
$check('kodeHariIni() TIDAK memanggil barisHariIniUntuk() (pembuat kode)',
    str_contains($getKode, 'barisHariIniUntuk('), false);
$check('kodeHariIni() memakai pembaca read-only',
    str_contains($getKode, 'kodeHariIniUntukJikaAda('), true);

// ═══════════════════════════════════════════════════════════
// 3. Cron tetap SATU sumber pembuatan + pengiriman pagi reguler
// ═══════════════════════════════════════════════════════════
$check('cron pagi tetap memakai jalur atomik barisHariIniUntuk()',
    str_contains($srcCron, 'barisHariIniUntuk('), true);
$check('cron pagi tetap memutuskan kirim dari wasRecentlyCreated',
    str_contains($srcCron, 'wasRecentlyCreated'), true);

// ═══════════════════════════════════════════════════════════
// 4. Aktivasi kerja hari libur TETAP membuat + mengirim kode seketika
// ═══════════════════════════════════════════════════════════
$posAkt  = strpos($srcCtrl, 'function aktifkanKerjaHariLibur(');
$posAktE = $posAkt === false ? false : strpos($srcCtrl, 'public function', $posAkt + 20);
$bodyAkt = $posAkt === false ? '' : substr($srcCtrl, $posAkt, ($posAktE ?: strlen($srcCtrl)) - $posAkt);

$check('aktivasi tetap membuat kode pribadi',
    str_contains($bodyAkt, 'kodeHariIniUntuk(') || str_contains($bodyAkt, 'barisHariIniUntuk('), true);
$check('aktivasi tetap mengirim Telegram',
    str_contains($bodyAkt, 'TelegramService'), true);

// ═══════════════════════════════════════════════════════════
// 5. Endpoint POST manual "Buat & Kirim Kode" (Owner/Mandor)
// ═══════════════════════════════════════════════════════════
$check('controller punya method kirimKodeAbsen()',
    str_contains($srcCtrl, 'function kirimKodeAbsen('), true);

$posKirim  = strpos($srcCtrl, 'function kirimKodeAbsen(');
$posKirimE = $posKirim === false ? false : strpos($srcCtrl, 'public function', $posKirim + 20);
$bodyKirim = $posKirim === false ? '' : substr($srcCtrl, $posKirim, ($posKirimE ?: strlen($srcCtrl)) - $posKirim);

$check('endpoint manual memakai helper ATOMIK barisHariIniUntuk()',
    str_contains($bodyKirim, 'barisHariIniUntuk('), true);
$check('endpoint manual mengirim HANYA kalau barisnya baru dibuat (wasRecentlyCreated)',
    str_contains($bodyKirim, 'wasRecentlyCreated'), true);
$check('endpoint manual menolak karyawan yang tidak aktif',
    str_contains($bodyKirim, 'aktif'), true);

// Route terdaftar dengan pagar yang benar (dibaca dari daftar route Laravel ASLI).
$json   = shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null');
$routes = json_decode((string) $json, true);
$byName = [];
foreach ((array) $routes as $r) {
    if (!empty($r['name'])) $byName[$r['name']] = $r;
}

$check('route `absensi.kirim-kode` terdaftar', isset($byName['absensi.kirim-kode']), true);
$check('route `absensi.kirim-kode` memakai method POST',
    in_array('POST', explode('|', (string) ($byName['absensi.kirim-kode']['method'] ?? '')), true), true);
$check('route `absensi.kirim-kode` dibatasi Owner + Mandor (level:1,3)',
    in_array('level:1,3', $byName['absensi.kirim-kode']['middleware'] ?? [], true), true);
$check('route `absensi.kirim-kode` tetap butuh login',
    in_array('auth', $byName['absensi.kirim-kode']['middleware'] ?? [], true), true);

// Halaman kode absen sendiri TIDAK berubah URL/namanya.
$check('route `absensi.kode-hari-ini` tetap ada dengan nama yang sama',
    isset($byName['absensi.kode-hari-ini']), true);
$check('route `absensi.kode-hari-ini` tetap GET',
    in_array('GET', explode('|', (string) ($byName['absensi.kode-hari-ini']['method'] ?? '')), true), true);

// ═══════════════════════════════════════════════════════════
// 6. View: tombol muncul untuk yang belum punya kode
// ═══════════════════════════════════════════════════════════
$check('view punya form ke route kirim-kode',
    str_contains($srcView, "route('absensi.kirim-kode'"), true);
$check('form kirim-kode memakai method POST',
    (bool) preg_match('/<form[^>]*method="POST"[^>]*action="\{\{\s*route\(\x27absensi\.kirim-kode\x27/', $srcView), true);
$check('form kirim-kode menyertakan @csrf',
    substr_count($srcView, '@csrf'), 2); // 1 aktivasi hari libur + 1 kirim kode
$check('tombol kirim-kode hanya muncul kalau kodenya belum ada',
    (bool) preg_match('/@if\s*\(\s*!\s*\$d\[\x27kode\x27\]\s*\)/', $srcView), true);

// Aturan project: pakai SVG, jangan emoji baru di blade (emoji bisa korup di server).
$posForm2 = strpos($srcView, "route('absensi.kirim-kode'");
$potongan = $posForm2 === false ? '' : substr($srcView, $posForm2, 600);
$check('tombol baru tidak memakai emoji (aturan project: SVG saja)',
    (bool) preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $potongan), false);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
