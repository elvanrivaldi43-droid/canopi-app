<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTahapPic extends Model
{
    protected $table = 'project_tahap_pic';

    protected $fillable = [
        'project_tahap_id', 'user_id', 'peran', 'ditambahkan_oleh',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectTahap()
    {
        return $this->belongsTo(ProjectTahap::class, 'project_tahap_id');
    }

    public function getPeranLabelAttribute()
    {
        return $this->peran === 'tukang' ? 'Tukang' : 'Kenek';
    }
}
