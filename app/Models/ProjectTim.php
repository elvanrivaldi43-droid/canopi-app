<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTim extends Model
{
    protected $table = 'project_tim';

    protected $fillable = [
        'id_project', 'id_user', 'tgl_masuk', 'tgl_keluar', 'jumlah_hari',
        'jabatan_lapangan', 'rate_dasar', 'multiplier', 'rate_final', 'total_upah',
        'override_rate', 'alasan_override', 'override_approved_by', 'override_approved_at',
        'di_assign_oleh', 'status'
    ];

    protected $casts = [
        'tgl_masuk'             => 'date',
        'tgl_keluar'            => 'date',
        'multiplier'            => 'decimal:2',
        'override_approved_at'  => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'id_project');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_user');
    }

    // Hitung jumlah hari & total upah otomatis sebelum save
    protected static function booted()
    {
        static::saving(function ($tim) {
            if ($tim->tgl_masuk && $tim->tgl_keluar) {
                $tim->jumlah_hari = \Carbon\Carbon::parse($tim->tgl_masuk)
                    ->diffInDays(\Carbon\Carbon::parse($tim->tgl_keluar)) + 1;
            }

            // Rate final: pakai override kalau ada, kalau tidak pakai rate_dasar x multiplier
            $rateEfektif      = $tim->override_rate ?: $tim->rate_final;
            $tim->total_upah  = $rateEfektif * ($tim->jumlah_hari ?? 0);
        });
    }

    public function getJabatanLabelAttribute()
    {
        return $this->jabatan_lapangan === 'tukang' ? 'Tukang' : 'Kenek';
    }
}
