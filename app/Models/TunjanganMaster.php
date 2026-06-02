<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TunjanganMaster extends Model
{
    protected $table = 'tunjangan_master';
    protected $fillable = ['nama_tunjangan', 'tipe', 'nominal_default', 'aktif'];

    public function karyawan()
    {
        return $this->belongsToMany(User::class, 'karyawan_tunjangan', 'tunjangan_master_id', 'user_id')
                    ->withPivot('nominal')->withTimestamps();
    }
}
