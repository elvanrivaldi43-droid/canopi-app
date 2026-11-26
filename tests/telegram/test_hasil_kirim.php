<?php
// FILE: tests/telegram/test_hasil_kirim.php
// Jalankan: php tests/telegram/test_hasil_kirim.php
//
// Insiden 26 Nov 2026: Telegram membalas "Bad Gateway" ~16 dtk per pesan. Cron kode absen
// membuat baris kode SEBELUM mengirim; kirim gagal -> baris tetap ada -> percobaan ulang
// mengiranya "sudah dikirim" -> beberapa karyawan tak pernah dapat kode. Kini hasil kirim
// dibedakan: pasti gagal (baris dibatalkan, bisa diulang) vs tak pasti (jangan diulang,
// supaya karyawan tak menerima dua kode) dan cron berhenti rapi sebelum dipotong server.
require __DIR__ . '/../bootstrap.php';

use App\Models\KodeAbsen;
use App\Services\TelegramService;

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Klasifikasi jawaban Telegram
$check('ok:true -> terkirim', TelegramService::klasifikasiHasil(['ok' => true, 'result' => []]), 'terkirim');
$check('502 Bad Gateway (ok:false) -> ditolak', TelegramService::klasifikasiHasil(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway']), 'ditolak');
$check('429 -> ditolak', TelegramService::klasifikasiHasil(['ok' => false, 'error_code' => 429]), 'ditolak');
$check('tak ada jawaban terbaca (timeout) -> tak_pasti', TelegramService::klasifikasiHasil([]), 'tak_pasti');
$check('ok bukan boolean true -> bukan terkirim', TelegramService::klasifikasiHasil(['ok' => 'true']), 'ditolak');

// Tanpa chat_id / tanpa permintaan: pasti tidak terkirim
$check('chat_id null -> ditolak', (new TelegramService())->kirimDenganHasil(null, 'tes'), 'ditolak');
$check('kirim() chat_id kosong tetap false', (new TelegramService())->kirim('', 'tes'), false);

// Aksi cron
$check('terkirim -> catat', KodeAbsen::aksiSetelahKirim('terkirim'), 'catat');
$check('ditolak -> batalkan (boleh diulang)', KodeAbsen::aksiSetelahKirim('ditolak'), 'batalkan');
$check('tak_pasti -> tahan (jangan kirim ulang)', KodeAbsen::aksiSetelahKirim('tak_pasti'), 'tahan');
$check('nilai asing -> tahan (aman: tidak menghapus, tidak mengulang)', KodeAbsen::aksiSetelahKirim('apa-saja'), 'tahan');

// Alur cron (server ini tak punya PDO, cron dibaca sumbernya)
$cron = file_get_contents(__DIR__ . '/../../public/cron-kode-absen.php');
$check('cron memakai kirimDenganHasil dengan timeout pendek', (bool) preg_match('/kirimDenganHasil\(\$k->telegram_chat_id, \$pesan, 10\)/', $cron), true);
$check('cron tak lagi memakai kirim() bool untuk kode', str_contains($cron, '->kirim($k->telegram_chat_id'), false);
$check('baris dihapus hanya pada aksi batalkan', (bool) preg_match("/case 'batalkan':.*?\\\$baris->delete\\(\\);.*?default:/s", $cron), true);
$check('aksi tak pasti TIDAK menghapus baris', (bool) preg_match('/default:\s*\$takPasti\[\] = \$k->name;(?:(?!\$baris->delete).)*?\}/s', $cron), true);
$check('ada penjaga waktu sebelum diproses tiap karyawan', (bool) preg_match('/foreach \(\$karyawan as \$k\) \{.*?BATAS_DETIK.*?break;/s', $cron), true);
$check('batas waktu < 120 dtk (batas tunggu cron)', (bool) preg_match('/const BATAS_DETIK = (\d+);/', $cron, $m) && (int) $m[1] < 120, true);
$check('Owner dilapori untuk yang tak pasti', str_contains($cron, 'TAK PASTI'), true);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
