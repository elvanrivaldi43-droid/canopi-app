<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMaterial extends Model
{
    protected $table = 'master_material';

    protected $fillable = [
        'kode', 'nama', 'kategori', 'satuan', 'harga_pokok',
        'keterangan', 'aktif', 'created_by'
    ];

    protected $casts = [
        'harga_pokok' => 'integer',
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
}
