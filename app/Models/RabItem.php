<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabItem extends Model {
    protected $table = 'rab_item';
    public $timestamps = false;
    protected $fillable = ['rab_id','tipe','referensi_id','nama_item','satuan','qty','harga_satuan','total','catatan','urutan'];

    protected $appends = ['total_computed'];

    public function rab() { return $this->belongsTo(RabHeader::class, 'rab_id'); }

    public function getTotalComputedAttribute(): float {
        return (float)$this->qty * (float)$this->harga_satuan;
    }

    public function totalFormatted(): string {
        return 'Rp ' . number_format($this->total_computed, 0, ',', '.');
    }
}
