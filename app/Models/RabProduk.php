<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabProduk extends Model {
    protected $table = 'rab_produk';
    public $timestamps = true;
    protected $fillable = ['kode','nama','deskripsi','icon','is_estimasi_saja','buffer_estimasi_persen','urutan','is_active'];

    public function marginSetting() {
        return $this->hasOne(RabMarginSetting::class, 'produk_kode', 'kode');
    }
    public function katalog() {
        return $this->hasMany(RabKatalog::class, 'produk_kode', 'kode');
    }
    public static function aktif() {
        return self::where('is_active', 1)->orderBy('urutan')->get();
    }
}
