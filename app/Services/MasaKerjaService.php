<?php
// FILE: app/Services/MasaKerjaService.php
// Sumber kebenaran TUNGGAL untuk "sudah berapa lama karyawan ini bekerja".
// Murni — tanpa database, tanpa Auth — supaya bisa diuji langsung
// (lihat tests/keamanan/test_masa_kerja_kasbon.php).

namespace App\Services;

use Carbon\Carbon;

class MasaKerjaService
{
    /**
     * Urutan sumber tanggal mulai bekerja (keputusan Bos, 15 Agustus 2026 — terkunci).
     *
     * Kenapa berurutan begini:
     *   1. `tanggal_bergabung`  — diisi Owner, paling sengaja & paling akurat.
     *   2. `tgl_masuk_kerja`    — wajib diisi di form karyawan, jadi hampir selalu ada.
     *   3. `created_at`         — TANGGAL BARIS DIBUAT DI SISTEM, bukan tanggal orangnya
     *                             mulai kerja. Karyawan lama yang barisnya baru dibuat
     *                             waktu sistem dipasang akan terlihat "baru" kalau ini
     *                             dipakai duluan. Jaring pengaman terakhir saja.
     *
     * Hanya Owner yang boleh mengubah `tanggal_bergabung` (lihat KaryawanAksesService),
     * karena kolom ini menentukan kelayakan Kasbon.
     */
    const URUTAN_SUMBER = ['tanggal_bergabung', 'tgl_masuk_kerja', 'created_at'];

    /**
     * Tanggal mulai bekerja yang efektif + kolom asalnya.
     *
     * Balikan: ['kolom' => 'tgl_masuk_kerja', 'tanggal' => '2024-05-01'] atau null
     * kalau ketiganya kosong/tidak sah. Null SENGAJA tidak diganti tebakan apa pun.
     */
    public static function sumberEfektif($tanggalBergabung, $tglMasukKerja, $createdAt): ?array
    {
        $kandidat = [
            'tanggal_bergabung' => $tanggalBergabung,
            'tgl_masuk_kerja'   => $tglMasukKerja,
            'created_at'        => $createdAt,
        ];

        foreach (self::URUTAN_SUMBER as $kolom) {
            $tanggal = self::normalkan($kandidat[$kolom] ?? null);
            if ($tanggal !== null) {
                return ['kolom' => $kolom, 'tanggal' => $tanggal];
            }
        }

        return null;
    }

    /**
     * Masa kerja dalam bulan penuh, dihitung dari sumber efektif.
     *
     * Tanpa sumber apa pun -> 0 bulan: gagal TERTUTUP (pengajuan ditolak),
     * bukan terbuka. Tanggal di masa depan (salah input) juga jadi 0, tidak negatif.
     */
    public static function masaKerjaBulan($tanggalBergabung, $tglMasukKerja, $createdAt, $sekarang = null): int
    {
        $sumber = self::sumberEfektif($tanggalBergabung, $tglMasukKerja, $createdAt);
        if ($sumber === null) return 0;

        $acuan = $sekarang instanceof Carbon ? $sekarang->copy() : Carbon::parse($sekarang ?? 'now');
        $mulai = Carbon::parse($sumber['tanggal']);

        if ($mulai->greaterThan($acuan)) return 0;

        return (int) $mulai->diffInMonths($acuan);
    }

    /**
     * Ubah nilai apa pun (string, Carbon, DateTime, null) jadi 'Y-m-d' — atau null
     * kalau tidak sah. Yang dianggap TIDAK sah: null, string kosong, '0000-00-00',
     * dan apa pun yang gagal di-parse. Tanpa penjagaan ini, kolom bernilai ''
     * akan di-parse Carbon jadi "sekarang" dan '0000-00-00' jadi tahun -1,
     * dua-duanya diam-diam menghasilkan masa kerja yang salah total.
     */
    private static function normalkan($nilai): ?string
    {
        if ($nilai === null) return null;

        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        $teks = trim((string) $nilai);
        if ($teks === '' || str_starts_with($teks, '0000-00-00')) return null;

        try {
            $tanggal = Carbon::parse($teks);
        } catch (\Throwable $e) {
            return null;
        }

        // Carbon menerima teks bebas seperti "besok" atau "bukan-tanggal" (sebagian
        // dilempar, sebagian tidak). Yang tidak berpola tanggal ditolak eksplisit.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $teks)) return null;

        return $tanggal->format('Y-m-d');
    }
}
