<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasAssignee extends Model
{
    protected $table = 'tugas_assignee';

    protected $fillable = [
        'tugas_id', 'user_id', 'status',
        'catatan_karyawan', 'waktu_mulai', 'waktu_selesai', 'notif_wa_terkirim',
    ];

    protected $casts = [
        'waktu_mulai'   => 'datetime',
        'waktu_selesai' => 'datetime',
        'notif_wa_terkirim' => 'boolean',
    ];

    public function tugas()
    {
        return $this->belongsTo(TugasHarian::class, 'tugas_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Label status human-readable
    public function labelStatus()
    {
        return match($this->status) {
            'belum'         => 'Belum Dikerjakan',
            'dikerjakan'    => 'Sedang Dikerjakan',
            'selesai'       => 'Selesai',
            'tidak_selesai' => 'Tidak Selesai',
            default         => '-',
        };
    }

    // Warna badge status
    public function warnaStatus()
    {
        return match($this->status) {
            'belum'         => '#64748b',
            'dikerjakan'    => '#3b82f6',
            'selesai'       => '#22c55e',
            'tidak_selesai' => '#ef4444',
            default         => '#94a3b8',
        };
    }
}