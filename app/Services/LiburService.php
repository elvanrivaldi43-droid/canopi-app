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

    public function hitungHariKerjaPada(?int $hariLiburDefault, array $overrides, int $bulan, int $tahun): int
    {
        $hariKerja  = 0;
        $akhirBulan = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= $akhirBulan; $i++) {
            $tgl = Carbon::createFromDate($tahun, $bulan, $i);
            if (!$this->cocokLiburPada($hariLiburDefault, $overrides, $tgl)) {
                $hariKerja++;
            }
        }
        return $hariKerja;
    }

    // Wrapper database — dipakai cron & GajiService.
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = $this->ambilOverride($user, $tanggal, $tanggal);
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $awal->copy()->endOfMonth();
        $overrides = $this->ambilOverride($user, $awal, $akhir);
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun);
    }

    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        return JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('tanggal', '>=', $dari->format('Y-m-d'))
            ->whereDate('tanggal', '<=', $sampai->format('Y-m-d'))
            ->get(['tanggal', 'jenis'])
            ->map(fn($o) => ['tanggal' => $o->tanggal->format('Y-m-d'), 'jenis' => $o->jenis])
            ->toArray();
    }
}
