<?php
// FILE: tests/penugasan/test_aturan.php
// Jalankan: php tests/penugasan/test_aturan.php
//
// Aturan murni Penugasan Harian (App\Services\PenugasanAturan). Task berikutnya
// menambah kasus di bawah blok "// --- tambah kasus di sini ---".

require __DIR__.'/../bootstrap.php';

use App\Services\PenugasanAturan as A;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// orangHari / alasanTolakTugasOrang
$check('orangHari setengah', A::orangHari('setengah'), 0.5);
$check('orangHari penuh', A::orangHari('penuh'), 1.0);
try { A::orangHari('x'); $check('orangHari x lempar', false, true); }
catch (\InvalidArgumentException $e) { $check('orangHari x lempar', true, true); }
$check('2 setengah ok', A::alasanTolakTugasOrang(['setengah', 'setengah']), null);
$check('penuh+setengah ditolak', A::alasanTolakTugasOrang(['penuh', 'setengah']) !== null, true);
$check('3 setengah ditolak', A::alasanTolakTugasOrang(['setengah', 'setengah', 'setengah']) !== null, true);
$check('penuh sendiri ok', A::alasanTolakTugasOrang(['penuh']), null);
$check('kosong ok', A::alasanTolakTugasOrang([]), null);

// pengingatMandor
$check('m 19:05', A::pengingatMandor('19:05', false, null), '1900');
$check('m 19:05 anti ganda', A::pengingatMandor('19:05', false, '1900'), null);
$check('m 21:10 lompat', A::pengingatMandor('21:10', false, '1900'), '2100');
$check('m 22:00 owner', A::pengingatMandor('22:00', false, '2100'), 'owner');
$check('m 22:30 selesai', A::pengingatMandor('22:30', false, 'owner'), null);
$check('m sudah disimpan', A::pengingatMandor('21:10', true, null), null);
$check('m 18:59', A::pengingatMandor('18:59', false, null), null);
$check('m 20:30', A::pengingatMandor('20:30', false, '1900'), '2030');
$check('m tak mundur', A::pengingatMandor('19:05', false, '2100'), null);

// pengingatPekerja
$check('p 21:35', A::pengingatPekerja('21:35', null, null), '2130');
$check('p 23:00 anti ganda', A::pengingatPekerja('23:00', null, '2130'), null);
$check('p 03:00 sepi', A::pengingatPekerja('03:00', null, '2130'), null);
$check('p 05:01', A::pengingatPekerja('05:01', null, '2130'), '0500');
$check('p 05:50', A::pengingatPekerja('05:50', null, '0500'), '0545');
$check('p sudah jawab', A::pengingatPekerja('05:50', 'hadir', null), null);
$check('p 06:01', A::pengingatPekerja('06:01', null, '0545'), null);
$check('p 05:50 tak mundur', A::pengingatPekerja('05:10', null, '0545'), null);

// bolehJawab
$check('jawab 05:59:59', A::bolehJawab('2026-11-07', new DateTime('2026-11-07 05:59:59')), true);
$check('jawab 06:00:00', A::bolehJawab('2026-11-07', new DateTime('2026-11-07 06:00:00')), false);
$check('jawab malam sebelumnya', A::bolehJawab('2026-11-07', new DateTime('2026-11-06 20:00')), true);

// keputusanKode
$check('kode tanpa rencana', A::keputusanKode(false, false, null), 'kirim');
$check('kode tak di rencana', A::keputusanKode(true, false, null), 'kirim');
$check('kode hadir', A::keputusanKode(true, true, 'hadir'), 'kirim');
$check('kode tidak hadir', A::keputusanKode(true, true, 'tidak_hadir'), 'catat_tidak_hadir');
$check('kode tanpa jawaban', A::keputusanKode(true, true, null), 'tanpa_jawaban');

// ikut
$check('ikut 2', A::ikut(2), false);
$check('ikut 5', A::ikut(5), true);
$check('ikut 3', A::ikut(3), true);
$check('ikut 7', A::ikut(7), false);

// perluTanyaUlang
$t = fn($id, $p) => ['project_tahap_id' => $id, 'porsi' => $p];
$check('tanya sama', A::perluTanyaUlang([$t(1,'penuh')], [$t(1,'penuh')]), false);
$check('tanya urutan beda', A::perluTanyaUlang([$t(1,'setengah'),$t(2,'setengah')], [$t(2,'setengah'),$t(1,'setengah')]), false);
$check('tanya berubah', A::perluTanyaUlang([$t(1,'penuh')], [$t(1,'setengah'),$t(2,'setengah')]), true);
$check('tanya dari kosong', A::perluTanyaUlang([], [$t(1,'penuh')]), true);

// statusMandor (editor, centang, jawabanLama, adaTugas)
$check('mandor owner+lama tidak_hadir+tugas', A::statusMandor(false, false, 'tidak_hadir', true), 'biarkan');
$check('mandor owner+lama tidak_hadir+tanpa tugas', A::statusMandor(false, false, 'tidak_hadir', false), 'biarkan');
$check('mandor sendiri hapus centang', A::statusMandor(true, false, 'tidak_hadir', true), 'hadir');
$check('mandor sendiri centang', A::statusMandor(true, true, null, true), 'tidak_hadir');
$check('mandor sendiri centang tanpa tugas', A::statusMandor(true, true, null, false), 'tidak_hadir');
$check('owner tanpa lama + tugas', A::statusMandor(false, false, null, true), 'hadir');
$check('owner tanpa lama + tanpa tugas', A::statusMandor(false, false, null, false), 'hapus');
$check('mandor sendiri uncheck tanpa tugas', A::statusMandor(true, false, 'tidak_hadir', false), 'hapus');

// parseTombol
$check('tombol hadir', A::parseTombol('hadir:12'), ['jawaban'=>'hadir','id'=>12]);
$check('tombol tidak', A::parseTombol('tidak:7'), ['jawaban'=>'tidak_hadir','id'=>7]);
$check('tombol abc', A::parseTombol('hadir:abc'), null);
$check('tombol lain', A::parseTombol('lain:1'), null);
$check('tombol kosong', A::parseTombol(''), null);

// alasanTolakJawaban (chatPenekan, chatPemilik, boleh)
$check('tolak ok', A::alasanTolakJawaban('55', '55', true), null);
$check('tolak chat beda', A::alasanTolakJawaban('55', '66', true) !== null, true);
$check('tolak pemilik belum terhubung', A::alasanTolakJawaban('55', null, true) !== null, true);
$check('tolak penekan kosong', A::alasanTolakJawaban(null, '55', true) !== null, true);
$check('tolak lewat 06:00', A::alasanTolakJawaban('55', '55', false), 'Batas jawab jam 06:00 sudah lewat. Hubungi mandor.');

// adaDiRencana (adaPenugasan, adaKonfirmasi) — Mandor Bertugas TIDAK otomatis dianggap di rencana
$check('diRencana penugasan', A::adaDiRencana(true, false), true);
$check('diRencana konfirmasi saja', A::adaDiRencana(false, true), true);
$check('diRencana tidak ada', A::adaDiRencana(false, false), false);

// keputusanKode + adaDiRencana: diam TAK PERNAH dianggap hadir
$check('kode: rencana, ditugaskan, diam', A::keputusanKode(true, A::adaDiRencana(true, true), null), 'tanpa_jawaban');
$check('kode: rencana, tak ditugaskan', A::keputusanKode(true, A::adaDiRencana(false, false), null), 'kirim');
$check('kode: tanpa rencana tersimpan', A::keputusanKode(false, A::adaDiRencana(true, true), null), 'kirim');

// pesanTanpaJawaban
$check('ringkasan kosong', A::pesanTanpaJawaban([]), null);
$check('ringkasan isi', A::pesanTanpaJawaban(['Andi', 'Budi']),
    "Tidak dapat kode absen hari ini karena tak menjawab konfirmasi: Andi, Budi.\nMereka bisa dibuatkan kode manual oleh Owner (menu Kode Absen Hari Ini).");

// statusTahapDariLaporan (statusTahap, laporan)
$check('tahap belum + lanjut', A::statusTahapDariLaporan('belum', 'lanjut'), 'sedang');
$check('tahap sedang + selesai', A::statusTahapDariLaporan('sedang', 'selesai'), 'selesai');
$check('tahap belum + selesai', A::statusTahapDariLaporan('belum', 'selesai'), 'selesai');
$check('tahap selesai + lanjut', A::statusTahapDariLaporan('selesai', 'lanjut'), null);
$check('tahap sedang + lanjut', A::statusTahapDariLaporan('sedang', 'lanjut'), null);
$check('tahap selesai + selesai', A::statusTahapDariLaporan('selesai', 'selesai'), null);

// tanggalKerja: sebelum 06:00 = hari ini (masih bisa dijawab), sejak 06:00 = besok
$check('tglKerja 23:59', A::tanggalKerja(new DateTime('2026-11-06 23:59')), '2026-11-07');
$check('tglKerja 00:30', A::tanggalKerja(new DateTime('2026-11-07 00:30')), '2026-11-07');
$check('tglKerja 05:59', A::tanggalKerja(new DateTime('2026-11-07 05:59:59')), '2026-11-07');
$check('tglKerja 06:00', A::tanggalKerja(new DateTime('2026-11-07 06:00')), '2026-11-08');
$check('tglKerja akhir bulan', A::tanggalKerja(new DateTime('2026-11-30 20:00')), '2026-12-01');

// kataHari
$check('kata hari ini', A::kataHari('2026-11-07', new DateTime('2026-11-07 05:30')), 'Hari ini');
$check('kata besok', A::kataHari('2026-11-08', new DateTime('2026-11-07 21:00')), 'Besok');

// pengingatPekerja: jendela 2130 berhenti di 22:00 (tak ada pesan pekerja 22:00-04:59)
$check('p 21:59', A::pengingatPekerja('21:59', null, null), '2130');
$check('p 22:00 diam', A::pengingatPekerja('22:00', null, null), null);
$check('p 23:00 diam', A::pengingatPekerja('23:00', null, null), null);
$check('p 05:00 tanpa 2130', A::pengingatPekerja('05:00', null, null), '0500');
$check('p 05:45', A::pengingatPekerja('05:45', null, null), '0545');

// pesanTanya (+ awalan bila rencana berubah setelah dijawab)
$check('tanya biasa', A::pesanTanya('Besok', '07 November 2026', ['A - Las (penuh)']),
    'Besok 07 November 2026 kamu di A - Las (penuh). Bersedia hadir?');
$check('tanya ulang', A::pesanTanya('Hari ini', '07 November 2026', ['A - Las (penuh)', 'B - Cat (setengah hari)'], true),
    "Rencana berubah — jawaban sebelumnya dibatalkan, mohon jawab lagi.\nHari ini 07 November 2026 kamu di A - Las (penuh) + B - Cat (setengah hari). Bersedia hadir?");

// pesanBerubahHadir: hanya transisi tidak_hadir -> hadir
$check('berubah hadir', A::pesanBerubahHadir('tidak_hadir', 'hadir', 'Andi', 'Besok', '07 November 2026'),
    'Andi berubah: HADIR besok (07 November 2026) — batalkan pengganti bila sudah dicari');
$check('berubah hadir hari ini', A::pesanBerubahHadir('tidak_hadir', 'hadir', 'Andi', 'Hari ini', '07 November 2026'),
    'Andi berubah: HADIR hari ini (07 November 2026) — batalkan pengganti bila sudah dicari');
$check('berubah dari null', A::pesanBerubahHadir(null, 'hadir', 'Andi', 'Besok', 'x'), null);
$check('berubah hadir->hadir', A::pesanBerubahHadir('hadir', 'hadir', 'Andi', 'Besok', 'x'), null);
$check('berubah ke tidak', A::pesanBerubahHadir('hadir', 'tidak_hadir', 'Andi', 'Besok', 'x'), null);

// perluKabariMandorTidakMasuk: simpan ulang tak mengulang notif ke Owner
$check('kabari pertama', A::perluKabariMandorTidakMasuk(true, null), true);
$check('kabari dari hadir', A::perluKabariMandorTidakMasuk(true, 'hadir'), true);
$check('kabari simpan ulang', A::perluKabariMandorTidakMasuk(true, 'tidak_hadir'), false);
$check('kabari tak centang', A::perluKabariMandorTidakMasuk(false, null), false);

// --- tambah kasus di sini ---

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
