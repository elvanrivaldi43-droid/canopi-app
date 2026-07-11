<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegistrasiToken extends Model
{
    protected $table = 'registrasi_token';
    protected $fillable = ['user_id','token','expired_at','used'];
    protected $casts = [
        'expired_at' => 'datetime',
        'used'       => 'boolean',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}