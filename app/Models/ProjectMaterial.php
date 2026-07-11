<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMaterial extends Model
{
    protected $table = 'project_material';

    protected $fillable = [
        'id_project', 'id_rab_item', 'id_master_material',
        'nama_material', 'satuan', 'qty_aktual', 'harga_satuan', 'total',
        'tanggal_beli', 'keterangan',
        'status_vs_rab', 'alasan_melebihi', 'approved_by', 'approved_at',
        'dibuat_oleh'
    ];

    protected $casts = [
        'qty_aktual'  => 'decimal:2',
        'harga_satuan'=> 'integer',
        'total'       => 'integer',
        'tanggal_beli'=> 'date',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'id_project');
    }

    public function rabItem()
    {
        return $this->belongsTo(RabItem::class, 'id_rab_item');
    }

    protected static function booted()
    {
        static::saving(function ($m) {
            $m->total = (int) round($m->qty_aktual * $m->harga_satuan);
        });
    }
}
