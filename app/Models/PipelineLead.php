<?php
// FILE: app/Models/PipelineLead.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PipelineLead extends Model
{
    protected $table = 'pipeline_leads';

    protected $fillable = [
        'nama_customer', 'no_hp', 'alamat', 'produk',
        'sumber_lead', 'status', 'estimasi_nilai', 'catatan',
        'tgl_kunjungan', 'last_activity_at', 'input_oleh',
    ];

    protected $casts = [
        'tgl_kunjungan'    => 'datetime',
        'last_activity_at' => 'datetime',
        'estimasi_nilai'   => 'integer',
    ];

    // ── Relasi ────────────────────────────────────────────
    public function inputOleh()
    {
        return $this->belongsTo(User::class, 'input_oleh');
    }

    public function followups()
    {
        return $this->hasMany(PipelineFollowup::class, 'pipeline_lead_id')->orderBy('created_at', 'desc');
    }

    // ── Accessor ──────────────────────────────────────────
    public function getAgingAttribute(): int
    {
        return (int) ($this->last_activity_at ?? $this->created_at)?->diffInDays(now());
    }

    public function getIsAgingAttribute(): bool
    {
        return $this->aging >= 7;
    }

    // ── Static helpers ────────────────────────────────────
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
            'dihubungi'   => '#06b6d4',
            'dijadwalkan' => '#8b5cf6',
            'dikunjungi'  => '#f59e0b',
            'ditawar'     => '#3b82f6',
            'deal'        => '#10b981',
            'tidak_jadi'  => '#ef4444',
        ];
    }

    public static function produkList(): array
    {
        return [
            'kanopi'         => '🏠 Kanopi',
            'pagar'          => '🔒 Pagar',
            'tralis'         => '🔧 Tralis',
            'tenda_membrane' => '⛺ Tenda Membrane',
            'lainnya'        => '📦 Lainnya',
        ];
    }

    public static function sumberList(): array
    {
        return [
            'instagram'  => '📸 Instagram',
            'whatsapp'   => '💬 WhatsApp',
            'referensi'  => '👥 Referensi',
            'google'     => '🔍 Google',
            'spanduk'    => '🪧 Spanduk',
            'lainnya'    => '📝 Lainnya',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusList()[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::statusColors()[$this->status] ?? '#64748b';
    }

    public function produkLabel(): string
    {
        return self::produkList()[$this->produk] ?? $this->produk;
    }

    public function sumberLabel(): string
    {
        return self::sumberList()[$this->sumber_lead] ?? $this->sumber_lead;
    }
}