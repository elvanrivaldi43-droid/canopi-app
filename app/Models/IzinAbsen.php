<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IzinAbsen extends Model
{
    protected $table = 'izin_absen';
    protected $fillable = [
        'user_id','tanggal','tipe','alasan',
        'foto_surat','status','diproses_oleh','catatan_mandor'
    ];
    protected $casts = ['tanggal' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function diprosesOleh() { return $this->belongsTo(User::class, 'diproses_oleh'); }
}
