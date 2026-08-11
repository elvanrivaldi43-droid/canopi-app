<?php
// FILE: tests/absensi/test_jam_individu.php
// Jalankan: php tests/absensi/test_jam_individu.php
// Mirror formula telat/lembur di AbsensiController — jam_masuk/jam_pulang per-karyawan
// (private hitungMenitTelat() tak bisa dipanggil dari luar, jadi rumusnya di-copy persis di sini).

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Mirror AbsensiController::hitungMenitTelat() (private, one-liner) persis.
$hitungMenitTelat = function (string $jamSekarang, string $jamTarget, int $toleransi = 0): int {
    return max(0, (int)((strtotime($jamSekarang) - strtotime($jamTarget) - ($toleransi * 60)) / 60));
};

// --- Telat: call site pakai $user->jam_masuk RAW (H:i:s), strtotime toleran H:i maupun H:i:s ---
$check('Telat: jam_masuk 07:00:00 (default lama) vs absen 07:30 -> 30 menit (nol-regresi)',
    $hitungMenitTelat('07:30', '07:00:00'), 30);

$check('Telat: jam_masuk custom 08:00:00 vs absen 07:30 -> 0 menit (belum telat, fitur inti)',
    $hitungMenitTelat('07:30', '08:00:00'), 0);

$check('Telat: jam_masuk custom 08:00:00 vs absen 08:15 -> 15 menit',
    $hitungMenitTelat('08:15', '08:00:00'), 15);

// --- Lembur: gate pakai STRING compare now()->format('H:i') >= substr(jam_pulang,0,5) ---
// (bukan strtotime — makanya substr wajib biar kedua sisi H:i, bukan H:i vs H:i:s)
$gateLembur = fn(string $now, string $jamPulangRaw): bool => $now >= substr($jamPulangRaw, 0, 5);

$check('Lembur: jam_pulang 17:00:00 (default), now 17:45 -> gate true',
    $gateLembur('17:45', '17:00:00'), true);
$check('Lembur: jam_pulang 17:00:00, menit lembur = 45',
    $hitungMenitTelat('17:45', substr('17:00:00', 0, 5)), 45);

$check('Lembur: jam_pulang custom 16:00:00, now 16:30 -> gate true (nol-regresi custom)',
    $gateLembur('16:30', '16:00:00'), true);
$check('Lembur: jam_pulang custom 16:00:00, menit lembur = 30',
    $hitungMenitTelat('16:30', substr('16:00:00', 0, 5)), 30);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
