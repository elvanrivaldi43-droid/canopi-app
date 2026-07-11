<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Project extends Model
{
    protected $table = 'projects';
    protected $fillable = [
        'id_lead', 'kode_project', 'nama_customer', 'no_hp', 'alamat_project',
        'jenis_project', 'deskripsi', 'nilai_kontrak',
        'id_rate_kondisi', 'multiplier_upah', 'kondisi_approved_by', 'kondisi_approved_at',
        'status', 'tgl_mulai_target', 'tgl_mulai_aktual',
        'tgl_selesai_target', 'tgl_selesai_aktual', 'dibuat_oleh',
        'rab_id', 'nama_project', 'nilai_project', // kolom baru dari RAB
    ];
    protected $casts = [
        'nilai_kontrak'       => 'integer',
        'multiplier_upah'     => 'decimal:2',
        'tgl_mulai_target'    => 'date',
        'tgl_mulai_aktual'    => 'date',
        'tgl_selesai_target'  => 'date',
        'tgl_selesai_aktual'  => 'date',
        'kondisi_approved_at' => 'datetime',
    ];
    public static $statusLabel = [
        'persiapan'    => 'Persiapan',
        'pengerjaan'   => 'Pengerjaan',
        'selesai'      => 'Selesai',
        'garansi'      => 'Garansi',
        'dibatalkan'   => 'Dibatalkan',
        'menunggu_dp'  => 'Menunggu DP',  // status baru dari RAB
    ];
    public static $statusColor = [
        'persiapan'   => '#f59e0b',
        'pengerjaan'  => '#3b82f6',
        'selesai'     => '#10b981',
        'garansi'     => '#8b5cf6',
        'dibatalkan'  => '#ef4444',
        'menunggu_dp' => '#fbbf24',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(PipelineLead::class, 'id_lead');
    }
    public function rateKondisi()
    {
        return $this->belongsTo(RateKondisi::class, 'id_rate_kondisi');
    }
    public function tim()
    {
        return $this->hasMany(ProjectTim::class, 'id_project');
    }
    public function rab()
    {
        return $this->belongsTo(RabHeader::class, 'rab_id');
    }
    // FIX: pakai foreign key rab_id bukan id_project
    public function rabItems()
    {
        return $this->hasMany(RabItem::class, 'rab_id', 'rab_id');
    }
    public function materialAktual()
    {
        return $this->hasMany(ProjectMaterial::class, 'id_project');
    }
    public function pembayaran()
    {
        return $this->hasMany(PembayaranProject::class, 'id_project');
    }

    // Computed attributes
    public function getTotalUpahAttribute()
    {
        return $this->tim()->sum('total_upah');
    }
    public function getTotalMaterialAttribute()
    {
        return $this->materialAktual()->sum('total');
    }
    public function getTotalBayarAttribute()
    {
        return $this->pembayaran()->where('status', 'dikonfirmasi')->sum('nominal');
    }
    public function getNilaiKontrakEfektifAttribute()
    {
        // Gunakan nilai_kontrak jika ada, fallback ke nilai_project dari RAB
        return $this->nilai_kontrak ?: $this->nilai_project ?: 0;
    }
    public function getProfitAttribute()
    {
        return $this->nilai_kontrak_efektif - $this->total_material - $this->total_upah;
    }
    public function getMarginPersenAttribute()
    {
        if ($this->nilai_kontrak_efektif <= 0) return 0;
        return round(($this->profit / $this->nilai_kontrak_efektif) * 100, 1);
    }
    public function getSisaTagihanAttribute()
    {
        return $this->nilai_kontrak_efektif - $this->total_bayar;
    }
    public static function generateKode()
    {
        $tahun  = date('Y');
        $bulan  = date('m');
        $prefix = "PRJ-{$tahun}{$bulan}-";
        $last   = self::where('kode_project', 'LIKE', $prefix . '%')
                      ->orderByDesc('id')->first();
        $urut   = $last ? (intval(substr($last->kode_project, -3)) + 1) : 1;
        return $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);
    }
    public function getStatusLabelAttribute()
    {
        return self::$statusLabel[$this->status] ?? $this->status;
    }
    public function getStatusColorAttribute()
    {
        return self::$statusColor[$this->status] ?? '#888';
    }
}