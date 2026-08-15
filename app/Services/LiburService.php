<?php
// FILE: app/Services/LiburService.php

namespace App\Services;

use App\Models\JadwalLibur;
use App\Models\LiburNasional;
use App\Models\LiburNasionalPiket;
use App\Models\User;
use Carbon\Carbon;

class LiburService
{
    const HARI = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    // Logic murni — testable tanpa database.
    // $overrides: array asosiatif ['tanggal' => 'Y-m-d', 'jenis' => 'tambah'|'batal'][]
    public function cocokLiburPada(?int $hariLiburDefault, array $overrides, Carbon $tanggal): bool
    {
        $tglStr = $tanggal->format('Y-m-d');
        foreach ($overrides as $o) {
            if ($o['tanggal'] === $tglStr) {
                return $o['jenis'] === 'tambah';
            }
        }
        return $hariLiburDefault !== null && $tanggal->dayOfWeek === $hariLiburDefault;
    }

    public function hitungHariKerjaPada(?int $hariLiburDefault, array $overrides, int $bulan, int $tahun, ?int $sampaiHari = null): int
    {
        $hariKerja  = 0;
        $akhirBulan = $sampaiHari ?? Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= $akhirBulan; $i++) {
            $tgl = Carbon::createFromDate($tahun, $bulan, $i);
            if (!$this->cocokLiburPada($hariLiburDefault, $overrides, $tgl)) {
                $hariKerja++;
            }
        }
        return $hariKerja;
    }

    public function expandTukar(array $row): array
    {
        if ($row['jenis'] === 'tukar') {
            return [
                ['tanggal' => $row['tanggal'], 'jenis' => 'batal'],
                ['tanggal' => $row['tanggal_baru'], 'jenis' => 'tambah'],
            ];
        }
        return [['tanggal' => $row['tanggal'], 'jenis' => $row['jenis']]];
    }

    // $piketTanggal: array string 'Y-m-d' — tanggal dalam rentang ini yang DIKECUALIKAN (karyawan piket).
    public function expandLiburNasional(string $mulai, string $selesai, array $piketTanggal): array
    {
        $hasil = [];
        $cur   = Carbon::parse($mulai);
        $akhir = Carbon::parse($selesai);
        while ($cur->lte($akhir)) {
            $tglStr = $cur->format('Y-m-d');
            if (!in_array($tglStr, $piketTanggal, true)) {
                $hasil[] = ['tanggal' => $tglStr, 'jenis' => 'tambah'];
            }
            $cur->addDay();
        }
        return $hasil;
    }

    /**
     * Tanggal yang sudah DIAKTIFKAN "masuk kerja di hari libur" jadi override `batal`.
     *
     * Keputusan Bos: aktivasi membatalkan libur tanpa pengganti — tanggalnya menjadi
     * hari kerja biasa. Jadi hari itu masuk penyebut hari kerja DAN pembilang
     * kehadiran, persis seperti hari kerja lain.
     *
     * Rancangan awal fitur ini menahan tanggalnya tetap "libur" lalu mengeluarkan
     * barisnya dari statistik supaya persentase tidak tembus 100%. Akibatnya karyawan
     * yang sudah diaktifkan lalu TIDAK MASUK hilang dari laporan: alpha-nya tidak
     * terhitung dan KPI-nya tidak turun. Sekarang hitungannya konsisten tanpa
     * membuang record apa pun.
     *
     * Upah ekstra 1x gaji harian TIDAK hilang — dihitung terpisah lewat
     * `absensi.upah_hari_libur` (kompensasi jatah libur yang hangus).
     */
    public function expandAktivasi(array $tanggalAktivasi): array
    {
        $hasil = [];
        foreach ($tanggalAktivasi as $tgl) {
            $hasil[] = ['tanggal' => $tgl, 'jenis' => 'batal'];
        }
        return $hasil;
    }

    public function jendelaTukarSkip(Carbon $sekarang): array
    {
        $awal  = $sekarang->copy()->addDay()->startOfDay();
        $akhir = $sekarang->copy()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->subDay()->endOfDay();
        return [$awal, $akhir];
    }

    public function tanggalKandidatLibur(int $hariLiburDefault, Carbon $awal, Carbon $akhir): array
    {
        $hasil = [];
        $cur   = $awal->copy();
        while ($cur->lte($akhir)) {
            if ($cur->dayOfWeek === $hariLiburDefault) {
                $hasil[] = $cur->format('Y-m-d');
            }
            $cur->addDay();
        }
        return $hasil;
    }

    // Wrapper database — dipakai cron & GajiService.
    //
    // URUTAN MERGE = URUTAN PRIORITAS. cocokLiburPada() berhenti di override
    // PERTAMA yang tanggalnya cocok, jadi aktivasi kerja hari libur harus berada
    // paling depan: dia mengalahkan libur nasional, override jadwal, dan jadwal
    // libur default sekaligus.
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = array_merge(
            $this->ambilAktivasiKerjaLibur($user, $tanggal, $tanggal),
            $this->ambilLiburNasional($user, $tanggal, $tanggal),
            $this->ambilOverride($user, $tanggal, $tanggal)
        );
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun, ?int $sampaiHari = null): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $sampaiHari ? $awal->copy()->day($sampaiHari) : $awal->copy()->endOfMonth();
        // Aktivasi paling depan = prioritas tertinggi (lihat isLibur).
        $overrides = array_merge(
            $this->ambilAktivasiKerjaLibur($user, $awal, $akhir),
            $this->ambilLiburNasional($user, $awal, $akhir),
            $this->ambilOverride($user, $awal, $akhir)
        );
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun, $sampaiHari);
    }

    // Peta libur 1 bulan penuh untuk 1 karyawan: ['Y-m-d' => bool].
    // Override diambil SEKALI (bukan per tanggal) biar tidak N+1 query di halaman rekap.
    public function petaLiburBulan(User $user, int $bulan, int $tahun): array
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $awal->copy()->endOfMonth();
        // Aktivasi paling depan = prioritas tertinggi (lihat isLibur).
        $overrides = array_merge(
            $this->ambilAktivasiKerjaLibur($user, $awal, $akhir),
            $this->ambilLiburNasional($user, $awal, $akhir),
            $this->ambilOverride($user, $awal, $akhir)
        );

        $peta = [];
        for ($cur = $awal->copy(); $cur->lte($akhir); $cur->addDay()) {
            $peta[$cur->format('Y-m-d')] = $this->cocokLiburPada($user->hari_libur_default, $overrides, $cur);
        }
        return $peta;
    }

    /**
     * Tanggal aktivasi "masuk kerja di hari libur" milik user ini dalam rentang tsb.
     *
     * Dibaca dari tabel otorisasi (`kerja_hari_libur`) — satu-satunya sumber fakta
     * "hari itu memang diminta masuk". Sengaja TIDAK memeriksa apakah orangnya
     * akhirnya masuk: kalau sudah diaktifkan lalu mangkir, hari itu tetap hari kerja
     * dan alpha-nya memang harus terhitung.
     */
    private function ambilAktivasiKerjaLibur(User $user, Carbon $dari, Carbon $sampai): array
    {
        $tanggal = \App\Models\KerjaHariLibur::where('user_id', $user->id)
            ->whereBetween('tanggal', [$dari->format('Y-m-d'), $sampai->format('Y-m-d')])
            ->get(['tanggal'])
            ->map(fn($a) => $a->tanggal instanceof Carbon
                ? $a->tanggal->format('Y-m-d')
                : Carbon::parse($a->tanggal)->format('Y-m-d'))
            ->all();

        return $this->expandAktivasi($tanggal);
    }

    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->format('Y-m-d');
        $sampaiStr = $sampai->format('Y-m-d');

        $rows = JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($dariStr, $sampaiStr) {
                $q->whereBetween('tanggal', [$dariStr, $sampaiStr])
                  ->orWhereBetween('tanggal_baru', [$dariStr, $sampaiStr]);
            })
            ->get(['tanggal', 'tanggal_baru', 'jenis']);

        $overrides = [];
        foreach ($rows as $o) {
            $overrides = array_merge($overrides, $this->expandTukar([
                'tanggal'      => $o->tanggal->format('Y-m-d'),
                'tanggal_baru' => $o->tanggal_baru?->format('Y-m-d'),
                'jenis'        => $o->jenis,
            ]));
        }
        return $overrides;
    }

    private function ambilLiburNasional(User $user, Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->format('Y-m-d');
        $sampaiStr = $sampai->format('Y-m-d');

        $liburs = LiburNasional::where('tanggal_mulai', '<=', $sampaiStr)
            ->where('tanggal_selesai', '>=', $dariStr)
            ->get(['tanggal_mulai', 'tanggal_selesai']);

        // Piket dicocokkan per user+tanggal, BUKAN per libur_nasional_id — kalau 2 libur
        // nasional overlap di tanggal sama, 1 baris piket cukup mengecualikan semuanya.
        $piketTanggal = LiburNasionalPiket::where('user_id', $user->id)
            ->whereBetween('tanggal', [$dariStr, $sampaiStr])
            ->get(['tanggal'])
            ->map(fn($p) => $p->tanggal->format('Y-m-d'))
            ->toArray();

        $overrides = [];
        foreach ($liburs as $lb) {
            $overrides = array_merge($overrides, $this->expandLiburNasional(
                $lb->tanggal_mulai->format('Y-m-d'),
                $lb->tanggal_selesai->format('Y-m-d'),
                $piketTanggal
            ));
        }
        return $overrides;
    }
}
