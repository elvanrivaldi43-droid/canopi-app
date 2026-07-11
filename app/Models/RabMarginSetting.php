<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabMarginSetting extends Model {
    protected $table = 'rab_margin_setting';
    public $timestamps = false;
    protected $fillable = [
        'produk_kode','margin_min_persen','margin_standar_persen',
        'margin_target_persen','diskon_max_persen','mode_aktif','updated_by'
    ];

    // Ambil margin yang aktif sesuai mode (standar/target)
    public function marginAktif(): float {
        return $this->mode_aktif === 'target'
            ? (float)$this->margin_target_persen
            : (float)$this->margin_standar_persen;
    }

    public static function byProduk(string $kode): ?self {
        return self::where('produk_kode', $kode)->first();
    }

    // Switch semua produk ke mode target sekaligus (owner 1 klik)
    public static function switchSemuaMode(string $mode, int $userId): void {
        self::whereIn('mode_aktif', ['standar','target'])
            ->update(['mode_aktif' => $mode, 'updated_by' => $userId]);
    }
}
