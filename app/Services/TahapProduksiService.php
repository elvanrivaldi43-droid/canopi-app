<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTahap;
use App\Models\TemplateTahap;
use Illuminate\Support\Collection;

class TahapProduksiService
{
    /**
     * Pilih 1 template yang jenis_project-nya cocok & aktif. Kalau lebih dari
     * satu kandidat cocok, pilih yang id-nya terbesar (paling baru dibuat).
     * Pure: $templates boleh Collection Eloquent ATAU array asosiatif biasa
     * (dites pakai array biar tidak perlu DB).
     *
     * @param Collection $templates
     * @param string $jenisProject
     * @return array|TemplateTahap|null
     */
    public function pilihTemplateCocok(Collection $templates, string $jenisProject)
    {
        return $templates
            ->filter(fn ($t) => $this->ambil($t, 'is_active') && $this->ambil($t, 'jenis_project') === $jenisProject)
            ->sortByDesc(fn ($t) => $this->ambil($t, 'id'))
            ->first();
    }

    /**
     * Generate baris project_tahap dari template yang cocok jenis_project-nya
     * ke project. Tidak ketemu template cocok -> tidak generate apa-apa (return 0),
     * BUKAN error — pemanggil (RabController::approve()) tidak boleh gagal gara-gara ini.
     */
    public function generateUntukProject(Project $project): int
    {
        $templates = TemplateTahap::with('items.tahapMaster')->get();
        $template  = $this->pilihTemplateCocok($templates, (string) $project->jenis_project);

        if (!$template) {
            return 0;
        }

        $jumlah = 0;
        foreach ($template->items as $item) {
            ProjectTahap::create([
                'project_id'      => $project->id,
                'tahap_master_id' => $item->tahap_master_id,
                'nama_tahap'      => $item->tahapMaster->nama,
                'urutan'          => $item->urutan,
                'status'          => 'belum',
                'dibuat_oleh'     => $project->dibuat_oleh,
            ]);
            $jumlah++;
        }

        return $jumlah;
    }

    private function ambil($t, string $key)
    {
        return is_array($t) ? ($t[$key] ?? null) : ($t->$key ?? null);
    }
}
