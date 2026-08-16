<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateTahap extends Model
{
    protected $table = 'template_tahap';

    protected $fillable = [
        'nama', 'jenis_project', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(TemplateTahapItem::class, 'template_tahap_id')->orderBy('urutan');
    }
}
