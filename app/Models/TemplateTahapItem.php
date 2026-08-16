<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateTahapItem extends Model
{
    protected $table = 'template_tahap_item';

    protected $fillable = [
        'template_tahap_id', 'tahap_master_id', 'urutan',
    ];

    public function tahapMaster()
    {
        return $this->belongsTo(TahapMaster::class, 'tahap_master_id');
    }
}
