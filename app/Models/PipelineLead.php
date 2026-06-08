<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineLead extends Model
{
    protected $fillable = [
        'user_id', 'nama_customer', 'no_hp', 'alamat', 'produk',
        'sumber_lead', 'status', 'estimasi_nilai', 'catatan',
        'tanggal_jadwal', 'jam_jadwal', 'last_activity_at',
    ];

    protected $casts = [
        'tanggal_jadwal'   => 'date',
        'last_activity_at' => 'datetime',
        'estimasi_nilai'   => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function followups()
    {
        return $this->hasMany(PipelineFollowup::class)->orderBy('created_at', 'desc');
    }

    public function getAgingAttribute(): int
    {
        $ref = $this->last_activity_at ?? $this->updated_at;
        return (int) now()->diffInDays($ref);
    }

    public function getIsAgingAttribute(): bool
    {
        return $this->aging > 7;
    }

    public static function statusList(): array
    {
        return [
            'lead'        => 'Lead',
            'dihubungi'   => 'Dihubungi',
            'dijadwalkan' => 'Dijadwalkan',
            'dikunjungi'  => 'Dikunjungi',
            'ditawar'     => 'Ditawar',
            'deal'        => 'Deal',
            'tidak_jadi'  => 'Tidak Jadi',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'lead'        => '#64748b',
            'dihubungi'   => '#3b82f6',
            'dijadwalkan' => '#8b5cf6',
            'dikunjungi'  => '#f59e0b',
            'ditawar'     => '#f97316',
            'deal'        => '#22c55e',
            'tidak_jadi'  => '#ef4444',
        ];
    }

    public static function produkList(): array
    {
        return ['kanopi', 'pagar', 'tralis', 'tenda'];
    }

    public static function sumberList(): array
    {
        return ['Instagram', 'WhatsApp', 'Referensi', 'Google', 'Spanduk', 'Lainnya'];
    }
}
