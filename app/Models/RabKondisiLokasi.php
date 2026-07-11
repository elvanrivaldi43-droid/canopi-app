<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabKondisiLokasi extends Model {
    protected $table = 'rab_kondisi_lokasi';
    public $timestamps = false;
    protected $fillable = ['kode','nama','deskripsi','tipe','nilai','urutan','is_active'];

    public function hitungTambahan(float $biayaPokok): float {
        if ($this->tipe === 'flat_add') return (float)$this->nilai;
        if ($this->tipe === 'persen_add') return $biayaPokok * ($this->nilai / 100);
        if ($this->tipe === 'multiplier') return $biayaPokok * ($this->nilai - 1);
        return 0;
    }

    public static function aktif(): \Illuminate\Database\Eloquent\Collection {
        return self::where('is_active', 1)->orderBy('urutan')->get();
    }
}
