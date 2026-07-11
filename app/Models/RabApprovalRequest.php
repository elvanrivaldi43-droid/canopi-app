<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabApprovalRequest extends Model {
    protected $table = 'rab_approval_request';
    public $timestamps = true;
    protected $fillable = [
        'rab_id','diminta_oleh','harga_normal','harga_diminta',
        'diskon_diminta_persen','alasan','status','diproses_oleh','catatan_owner','notif_wa_terkirim'
    ];

    public function rab() { return $this->belongsTo(RabHeader::class, 'rab_id'); }
    public function peminta() { return $this->belongsTo(\App\Models\User::class, 'diminta_oleh'); }
}
