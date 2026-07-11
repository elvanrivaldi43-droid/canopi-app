<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranProject extends Model
{
    protected $table = 'pembayaran_project';

    protected $fillable = [
        'id_project', 'jenis', 'nominal', 'tanggal_bayar',
        'metode', 'bukti_transfer', 'keterangan',
        'dikonfirmasi_oleh', 'dikonfirmasi_at', 'status'
    ];

    protected $casts = [
        'nominal'          => 'integer',
        'tanggal_bayar'    => 'date',
        'dikonfirmasi_at'  => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'id_project');
    }

    public function getJenisLabelAttribute()
    {
        return match($this->jenis) {
            'dp'     => 'DP',
            'termin' => 'Termin',
            'lunas'  => 'Pelunasan',
            default  => $this->jenis,
        };
    }
}
