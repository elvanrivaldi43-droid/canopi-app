<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabAddon extends Model {
    protected $table = 'rab_addon';
    public $timestamps = false;
    protected $fillable = [
        'kode','nama','kategori','satuan','formula_type',
        'harga_satuan','harga_pokok_satuan','qty_default','deskripsi','perlu_input_qty','urutan','is_active'
    ];

    public function hitungTotal(float $qty): float {
        return $qty * $this->harga_satuan;
    }

    public static function aktifByKategori(): array {
        $addons = self::where('is_active', 1)->orderBy('urutan')->get();
        $grouped = [];
        foreach ($addons as $a) {
            $grouped[$a->kategori][] = $a;
        }
        return $grouped;
    }
}
