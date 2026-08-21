<?php
// FILE: app/Services/RekomendasiPicService.php

namespace App\Services;

class RekomendasiPicService
{
    /**
     * hari_estimasi_default = qty / produktivitas
     * multiplier            = hari_estimasi_default / target_hari
     * jumlah_disarankan     = ceil(tim_default * multiplier), minimal 1
     *
     * null kalau salah satu input kosong/tidak valid buat dihitung (bukan exception) —
     * pemanggil (controller) yang memutuskan pesan ke user, service ini murni angka.
     */
    public function hitungJumlahDisarankan(?float $qty, ?float $produktivitas, ?int $timDefault, ?int $targetHari): ?int
    {
        if ($qty === null || $produktivitas === null || $timDefault === null || $targetHari === null) return null;
        if ($produktivitas <= 0 || $targetHari <= 0) return null;

        $hariEstimasiDefault = $qty / $produktivitas;
        $multiplier          = $hariEstimasiDefault / $targetHari;

        return max(1, (int) ceil($timDefault * $multiplier));
    }

    /**
     * Urutkan kandidat PIC: cocok & tidak sibuk dulu, lalu cocok & sibuk, lalu
     * tidak cocok paling bawah. Urutan asli dalam grup yang sama dipertahankan
     * (usort PHP 8+ stable).
     */
    public function urutkanKandidat(array $kandidat): array
    {
        $peringkat = function (array $k): int {
            if ($k['cocok'] && !$k['sibuk']) return 0;
            if ($k['cocok'] && $k['sibuk'])  return 1;
            return 2;
        };

        usort($kandidat, fn ($a, $b) => $peringkat($a) <=> $peringkat($b));
        return $kandidat;
    }
}
