<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabSelisihKomponen extends Model {
    protected $table = 'rab_selisih_komponen';
    public $timestamps = false;
    protected $fillable = ['selisih_id','nama_komponen','selisih_nilai'];
}
