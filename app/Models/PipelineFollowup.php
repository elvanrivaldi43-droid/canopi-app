<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineFollowup extends Model
{
    protected $fillable = [
        'pipeline_lead_id', 'user_id', 'catatan', 'status_sebelum', 'status_sesudah',
    ];

    public function lead()
    {
        return $this->belongsTo(PipelineLead::class, 'pipeline_lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
