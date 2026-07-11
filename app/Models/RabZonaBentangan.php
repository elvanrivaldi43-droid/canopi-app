<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabZonaBentangan extends Model {
    protected $table = 'rab_zona_bentangan';
    public $timestamps = false;
    protected $fillable = ['nama','bentangan_min','bentangan_max','deskripsi','urutan','is_active'];

    public function paketKonstruksi() {
        return $this->hasMany(RabPaketKonstruksi::class, 'zona_id');
    }
    public static function cariZona(float $bentangan): ?self {
        return self::where('is_active', 1)
            ->where('bentangan_min', '<=', $bentangan)
            ->where('bentangan_max', '>=', $bentangan)
            ->first();
    }
}
