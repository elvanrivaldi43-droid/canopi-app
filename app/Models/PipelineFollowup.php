<?php
// FILE: app/Models/PipelineFollowup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineFollowup extends Model
{
    protected $table = 'pipeline_followups';

    protected $fillable = [
        'pipeline_lead_id', 'user_id', 'metode',
        'catatan', 'tgl_followup_berikutnya',
    ];

    protected $casts = [
        'tgl_followup_berikutnya' => 'date',
    ];

    public function lead()
    {
        return $this->belongsTo(PipelineLead::class, 'pipeline_lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metodeLabel(): string
    {
        return [
            'whatsapp'   => '💬 WhatsApp',
            'telepon'    => '📞 Telepon',
            'email'      => '📧 Email',
            'kunjungan'  => '🚗 Kunjungan',
            'lainnya'    => '📝 Lainnya',
        ][$this->metode] ?? $this->metode;
    }
}