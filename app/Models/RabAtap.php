<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabAtap extends Model {
    protected $table = 'rab_atap';
    public $timestamps = false;
    protected $fillable = [
        'kode','nama','kategori','berat_kategori','grade_adjustment',
        'harga_per_lembar','lebar_lembar_cm','keterangan_customer','urutan','is_active'
    ];

    // Hitung biaya atap berdasarkan luas
    public function hitungBiayaAtap(float $m2, float $hargaPerM2Override = 0): float {
        if ($hargaPerM2Override > 0) return $m2 * $hargaPerM2Override;
        if (!$this->harga_per_lembar || !$this->lebar_lembar_cm) return 0;
        $lebarMeter = $this->lebar_lembar_cm / 100;
        $luasPerLembar = $lebarMeter * 6; // panjang standar 6m
        $jumlahLembar = ceil($m2 / $luasPerLembar);
        return $jumlahLembar * $this->harga_per_lembar;
    }

    public static function aktif(): \Illuminate\Database\Eloquent\Collection {
        return self::where('is_active', 1)->orderBy('urutan')->get();
    }
}
