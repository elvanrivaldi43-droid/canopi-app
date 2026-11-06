<?php
// FILE: app/Services/PenugasanAturan.php
//
// Aturan murni Penugasan Harian (rencana kerja malam oleh Mandor, pekerja konfirmasi
// Hadir/Tidak Hadir via Telegram sampai 06:00 WIB). Tanpa DB/facade supaya gampang dites.
// Jam = string 'HH:MM' WIB (berpadding nol, jadi aman dibandingkan sebagai string).

namespace App\Services;

class PenugasanAturan
{
    // Teknisi(5), Driver(6), Mandor(3) ikut penugasan.
    const LEVEL_IKUT = [3, 5, 6];

    // Urutan kode pengingat; kode tak pernah dikirim bila urutannya <= yang terakhir terkirim.
    const URUT_MANDOR  = ['1900', '2030', '2100', 'owner'];
    const URUT_PEKERJA = ['2130', '0500', '0545'];

    public static function ikut(int $level): bool
    {
        return in_array($level, self::LEVEL_IKUT, true);
    }

    // True bila himpunan tugas (tahap+porsi) berbeda; urutan tak berpengaruh.
    public static function perluTanyaUlang(array $tugasLama, array $tugasBaru): bool
    {
        $kunci = function (array $list) {
            $k = array_map(fn($t) => $t['project_tahap_id'] . ':' . $t['porsi'], $list);
            sort($k);
            return $k;
        };
        return $kunci($tugasLama) !== $kunci($tugasBaru);
    }

    public static function orangHari(string $porsi): float
    {
        return match ($porsi) {
            'penuh'    => 1.0,
            'setengah' => 0.5,
            default    => throw new \InvalidArgumentException("Porsi tak dikenal: $porsi"),
        };
    }

    // Satu orang maksimal 1 hari kerja per tanggal ('penuh' tak boleh bareng tugas lain).
    public static function alasanTolakTugasOrang(array $porsiList): ?string
    {
        $total = array_sum(array_map([self::class, 'orangHari'], $porsiList));
        return $total > 1.0 ? 'Lebih dari 1 hari kerja' : null;
    }

    // Pengingat Mandor yang jatuh tempo & belum terkirim; lompat ke yang terbaru, tak mengulang.
    public static function pengingatMandor(string $jam, bool $sudahDisimpan, ?string $terakhir): ?string
    {
        if ($sudahDisimpan) return null;

        $due = null;
        foreach (['1900' => '19:00', '2030' => '20:30', '2100' => '21:00', 'owner' => '22:00'] as $kode => $batas) {
            if ($jam >= $batas) $due = $kode;
        }

        return self::lebihMaju(self::URUT_MANDOR, $due, $terakhir);
    }

    // Pengingat pekerja yang jatuh tempo; tak ada pesan 00:00-04:59 maupun >= 06:00.
    public static function pengingatPekerja(string $jam, ?string $jawaban, ?string $terakhir): ?string
    {
        if ($jawaban !== null) return null;

        $due = match (true) {
            $jam >= '21:30'                  => '2130',
            $jam >= '05:00' && $jam < '05:45' => '0500',
            $jam >= '05:45' && $jam < '06:00' => '0545',
            default                          => null,
        };

        return self::lebihMaju(self::URUT_PEKERJA, $due, $terakhir);
    }

    // Jawaban terbuka sampai tanggal kerja 06:00:00 (tepat 06:00 sudah tutup).
    public static function bolehJawab(string $tanggalKerja, \DateTimeInterface $sekarang): bool
    {
        return $sekarang < new \DateTimeImmutable("$tanggalKerja 06:00:00", $sekarang->getTimezone());
    }

    // 'kirim' | 'catat_tidak_hadir' | 'tanpa_jawaban'
    public static function keputusanKode(bool $adaRencana, bool $adaDiRencana, ?string $jawaban): string
    {
        if (!$adaRencana || !$adaDiRencana) return 'kirim';

        return match ($jawaban) {
            'hadir'       => 'kirim',
            'tidak_hadir' => 'catat_tidak_hadir',
            default       => 'tanpa_jawaban',
        };
    }

    private static function lebihMaju(array $urut, ?string $due, ?string $terakhir): ?string
    {
        if ($due === null) return null;
        $posTerakhir = $terakhir === null ? -1 : array_search($terakhir, $urut, true);
        return array_search($due, $urut, true) > $posTerakhir ? $due : null;
    }
}
