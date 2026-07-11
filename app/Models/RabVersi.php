<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabVersi extends Model {
    protected $table = 'rab_versi';
    public $timestamps = false;
    protected $fillable = ['rab_id','label','paket_konstruksi_id','harga_final','margin_persen','detail_json','dipilih'];
    protected $casts = ['detail_json' => 'array'];

    public function paket() { return $this->belongsTo(RabPaketKonstruksi::class, 'paket_konstruksi_id'); }

    public function hargaFormatted(): string {
        return 'Rp ' . number_format($this->harga_final, 0, ',', '.');
    }
}
