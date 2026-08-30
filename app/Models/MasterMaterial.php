<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMaterial extends Model
{
    protected $table = 'master_material';

    protected $fillable = [
        'kode', 'nama', 'kategori', 'satuan', 'harga_pokok',
        'lebar_profil_cm', 'tinggi_profil_cm',
        'keterangan', 'aktif', 'created_by'
    ];

    protected $casts = [
        'harga_pokok' => 'integer',
        'lebar_profil_cm' => 'float',
        'tinggi_profil_cm' => 'float',
        'aktif' => 'boolean',
    ];

    public static $kategoriLabel = [
        'rangka_besi'    => 'Rangka Besi',
        'kaca'           => 'Kaca',
        'atap'           => 'Atap',
        'cat_finishing'  => 'Cat & Finishing',
        'aksesori'       => 'Aksesori',
        'talang'         => 'Talang',
        'konsumabel'     => 'Konsumabel',
        'jasa'           => 'Jasa',
        'lainnya'        => 'Lainnya',
    ];

    public function getHargaPokokRpAttribute()
    {
        return 'Rp ' . number_format($this->harga_pokok, 0, ',', '.');
    }

    public function getKategoriLabelAttribute()
    {
        return self::$kategoriLabel[$this->kategori] ?? $this->kategori;
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', 1);
    }

    public function scopeKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    /** Tebak dimensi profil dari nama ("Hollow 4x8 1mm" -> [4.0, 8.0]). CADANGAN
     *  saja — kolom DB sumber kebenaran (hollow "banci" 4x8 nyatanya 3,5cm). */
    public static function parseProfil(?string $nama): ?array
    {
        if ($nama === null) return null;
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)/u', $nama, $m)) return null;
        return [(float) str_replace(',', '.', $m[1]), (float) str_replace(',', '.', $m[2])];
    }

    /** [lebar, tinggi] cm: kolom DB kalau terisi, else tebak nama, else null. */
    public function profilCm(): ?array
    {
        $l = $this->lebar_profil_cm; $t = $this->tinggi_profil_cm;
        if ($l !== null && $t !== null && (float) $l > 0 && (float) $t > 0) return [(float) $l, (float) $t];
        return self::parseProfil($this->nama);
    }
}
