<?php
// FILE: app/Services/RincianPotongan.php
//
// Rincian asal-usul absensi.potongan_telat — kolom itu menampung telat pagi, telat
// absen siang, denda Lapor Progress, denda tidak absen Kembali Kerja, dan koreksi
// Owner sekaligus (spec 2026-09-30-rincian-potongan-design.md).
//
// MURNI, tanpa DB. Ini PENJELASAN saja: potongan_telat tetap patokan uang. Selisih
// total vs jumlah rincian (data sebelum fitur, perbaikan SQL manual) tampil sebagai
// baris "Belum terinci" — jadi jumlah yang tampil selalu sama dengan total.

namespace App\Services;

class RincianPotongan
{
    const LABEL = [
        'telat_pagi'    => 'Telat masuk',
        'telat_siang'   => 'Telat absen siang',
        'lupa_progress' => 'Lupa Lapor Progress',
        'skip_siang'    => 'Tidak absen Kembali Kerja',
        'koreksi'       => 'Koreksi Owner',
        'belum_terinci' => 'Belum terinci (data lama)',
    ];

    public static function tambah(?array $rincian, string $jenis, float $nominal, ?int $menit = null, ?string $alasan = null): array
    {
        $rincian = $rincian ?? [];
        $n = round($nominal, 2);
        if ($n == 0.0) return $rincian;

        $e = ['j' => $jenis, 'n' => $n];
        if ($menit !== null) $e['m'] = $menit;
        if ($alasan !== null && $alasan !== '') $e['a'] = $alasan;
        $rincian[] = $e;
        return $rincian;
    }

    public static function baris(?array $rincian, float $total): array
    {
        $out = [];
        $sum = 0.0;
        foreach ($rincian ?? [] as $e) {
            $sum  += $e['n'];
            $out[] = [
                'j'     => $e['j'],
                'label' => self::LABEL[$e['j']] ?? $e['j'],
                'n'     => (float) $e['n'],
                'ket'   => isset($e['m']) ? $e['m'] . ' menit' : ($e['a'] ?? null),
            ];
        }
        $sisa = round($total - $sum, 2);
        if (abs($sisa) >= 1) {
            $out[] = ['j' => 'belum_terinci', 'label' => self::LABEL['belum_terinci'], 'n' => $sisa, 'ket' => null];
        }
        return $out;
    }

    public static function ringkasBulan(iterable $absensi): array
    {
        $r = [];
        foreach ($absensi as $a) {
            foreach (self::baris($a->rincian_potongan ?? null, (float) ($a->potongan_telat ?? 0)) as $b) {
                $r[$b['j']]['x'] = ($r[$b['j']]['x'] ?? 0) + 1;
                $r[$b['j']]['n'] = round(($r[$b['j']]['n'] ?? 0) + $b['n'], 2);
            }
        }
        return $r;
    }
}
