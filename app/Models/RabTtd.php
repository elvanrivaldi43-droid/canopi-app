<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabTtd extends Model {
    protected $table = 'rab_ttd';
    public $timestamps = false;
    protected $fillable = ['rab_id','nama_penandatangan','ttd_data','ip_address','user_agent','lokasi_lat','lokasi_lng'];

    public function rab() { return $this->belongsTo(RabHeader::class, 'rab_id'); }
}
