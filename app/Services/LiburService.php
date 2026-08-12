<?php
// FILE: app/Services/LiburService.php

namespace App\Services;

use App\Models\JadwalLibur;
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
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = $this->ambilOverride($user, $tanggal, $tanggal);
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun, ?int $sampaiHari = null): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $sampaiHari ? $awal->copy()->day($sampaiHari) : $awal->copy()->endOfMonth();
        $overrides = $this->ambilOverride($user, $awal, $akhir);
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun, $sampaiHari);
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
}
