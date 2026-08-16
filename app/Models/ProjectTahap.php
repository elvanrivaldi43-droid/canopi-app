<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTahap extends Model
{
    protected $table = 'project_tahap';

    protected $fillable = [
        'project_id', 'tahap_master_id', 'nama_tahap', 'urutan', 'status',
        'qty', 'satuan', 'tanggal_mulai_target', 'tanggal_selesai_target',
        'tanggal_mulai_aktual', 'tanggal_selesai_aktual',
        'jumlah_tukang_disarankan', 'jumlah_kenek_disarankan',
        'catatan', 'dibuat_oleh',
    ];

    protected $casts = [
        'qty'                    => 'decimal:2',
        'tanggal_mulai_target'   => 'date',
        'tanggal_selesai_target' => 'date',
        'tanggal_mulai_aktual'   => 'date',
        'tanggal_selesai_aktual' => 'date',
    ];

    public static $statusLabel = [
        'belum'   => 'Belum Mulai',
        'sedang'  => 'Sedang Berjalan',
        'selesai' => 'Selesai',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function pic()
    {
        return $this->hasMany(ProjectTahapPic::class, 'project_tahap_id');
    }

    public function getStatusLabelAttribute()
    {
        return self::$statusLabel[$this->status] ?? $this->status;
    }
}
