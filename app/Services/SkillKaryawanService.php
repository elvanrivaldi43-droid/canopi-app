<?php
// FILE: app/Services/SkillKaryawanService.php

namespace App\Services;

class SkillKaryawanService
{
    /**
     * Deteksi kategori tukang/kenek dari teks jabatan bebas.
     * Tidak ada field khusus di `users` — sengaja dari keyword match, lihat
     * spec Fase 2 keputusan #5b (data lama contoh: "Tukang Las", "Kepala Tukang",
     * "Admin Sales", "Surveyor").
     */
    public function deteksiKategori(string $jabatan): ?string
    {
        // strtolower (bukan mb_strtolower): mbstring belum tentu ada di server
        // (tak ada di CLI VPS ini), input jabatan ASCII ("Tukang"/"Kenek").
        $j = strtolower($jabatan);
        if (str_contains($j, 'kenek')) return 'kenek';
        if (str_contains($j, 'tukang')) return 'tukang';
        return null;
    }

    /**
     * Daftar id rab_skill yang otomatis nempel untuk 1 kategori, berdasarkan
     * kolom rab_skill.default_role. Skill dengan default_role='manual' TIDAK
     * PERNAH masuk sini, berapapun kategorinya.
     */
    public function skillOtomatisUntukKategori(?string $kategori, iterable $rabSkillRows): array
    {
        if ($kategori === null) return [];

        $cocok = $kategori === 'tukang'
            ? ['tukang', 'tukang_kenek']
            : ['kenek', 'tukang_kenek'];

        $ids = [];
        foreach ($rabSkillRows as $row) {
            $defaultRole = is_array($row) ? $row['default_role'] : $row->default_role;
            $id          = is_array($row) ? $row['id'] : $row->id;
            if (in_array($defaultRole, $cocok, true)) $ids[] = (int) $id;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Susun baris user_skill siap-insert dari daftar id yang dicentang di form.
     * Skill yang termasuk daftar otomatis kategori ini -> sumber 'default_role'.
     * Sisanya (dicentang manual, termasuk skill default_role='manual' ATAU skill
     * kategori LAIN yang dicentang manual) -> sumber 'manual'.
     */
    public function susunUserSkill(int $userId, array $skillIdDicentang, ?string $kategori, iterable $rabSkillRows): array
    {
        $otomatis = $this->skillOtomatisUntukKategori($kategori, $rabSkillRows);

        $hasil = [];
        foreach (array_unique($skillIdDicentang) as $skillId) {
            $skillId = (int) $skillId;
            $hasil[] = [
                'user_id'      => $userId,
                'rab_skill_id' => $skillId,
                'sumber'       => in_array($skillId, $otomatis, true) ? 'default_role' : 'manual',
            ];
        }
        return $hasil;
    }
}
