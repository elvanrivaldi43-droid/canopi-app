<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabPaketKonstruksi extends Model {
    protected $table = 'rab_paket_konstruksi';
    public $timestamps = false;
    protected $fillable = [
        'zona_id','produk_kode','nama_paket','label_display',
        'frame_material','frame_ukuran','frame_tebal',
        'support_material','support_ukuran','metode','interval_kremona_cm',
        'harga_per_m2_rangka','harga_per_m2_jasa_pasang','catatan_teknis','urutan','is_active'
    ];

    public function zona() {
        return $this->belongsTo(RabZonaBentangan::class, 'zona_id');
    }
    public static function getByZona(int $zonaId): \Illuminate\Database\Eloquent\Collection {
        return self::where('zona_id', $zonaId)
            ->where('is_active', 1)
            ->orderBy('urutan')
            ->get();
    }
}
