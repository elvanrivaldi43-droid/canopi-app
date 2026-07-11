<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasHarian extends Model
{
    protected $table = 'tugas_harian';

    protected $fillable = [
        'judul', 'deskripsi', 'tanggal', 'jam_mulai',
        'jam_selesai_target', 'lokasi', 'prioritas', 'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Relasi ke user pembuat
    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // Relasi ke assignees (pivot)
    public function assignees()
    {
        return $this->hasMany(TugasAssignee::class, 'tugas_id');
    }

    // Relasi ke users via pivot
    public function karyawan()
    {
        return $this->belongsToMany(User::class, 'tugas_assignee', 'tugas_id', 'user_id')
                    ->withPivot('status', 'catatan_karyawan', 'waktu_mulai', 'waktu_selesai', 'notif_wa_terkirim')
                    ->withTimestamps();
    }

    // Badge warna prioritas
    public function warnaPrioritas()
    {
        return match($this->prioritas) {
            'tinggi' => '#ef4444',
            'sedang' => '#f59e0b',
            'rendah' => '#22c55e',
            default  => '#94a3b8',
        };
    }

    // Scope tugas hari ini
    public function scopeHariIni($query)
    {
        return $query->whereDate('tanggal', today());
    }

    // Scope by tanggal
    public function scopeByTanggal($query, $tanggal)
    {
        return $query->whereDate('tanggal', $tanggal);
    }
}