<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabHeader extends Model {
    protected $table = 'rab_header';
    public $timestamps = true;
    protected $fillable = [
        'nomor_rab','pipeline_lead_id','project_id','produk_kode',
        'paket_konstruksi_id','atap_id','panjang','lebar','m2_total','bentangan_max','zona_id',
        'biaya_rangka','biaya_atap','biaya_jasa','biaya_addon','biaya_kondisi','biaya_pokok_total',
        'buffer_persen','biaya_setelah_buffer','margin_persen',
        'harga_sebelum_diskon','diskon_persen','diskon_nominal','harga_final',
        'catatan_surveyor','catatan_internal','status','tahap','is_estimasi_kasar',
        'dibuat_oleh','disetujui_oleh'
    ];

    protected $casts = ['panjang' => 'float','lebar' => 'float','m2_total' => 'float'];

    public function items() { return $this->hasMany(RabItem::class, 'rab_id'); }
    public function versi() { return $this->hasMany(RabVersi::class, 'rab_id'); }
    public function ttd() { return $this->hasOne(RabTtd::class, 'rab_id'); }
    public function approvalRequest() { return $this->hasMany(RabApprovalRequest::class, 'rab_id'); }
    public function lead() { return $this->belongsTo(PipelineLead::class, 'pipeline_lead_id'); }
    public function paketKonstruksi() { return $this->belongsTo(RabPaketKonstruksi::class, 'paket_konstruksi_id'); }
    public function atap() { return $this->belongsTo(RabAtap::class, 'atap_id'); }
    public function pembuat() { return $this->belongsTo(\App\Models\User::class, 'dibuat_oleh'); }

    // Generate nomor RAB otomatis
    public static function generateNomor(): string {
        $tgl = date('Ymd');
        $last = self::whereDate('created_at', today())
            ->orderByDesc('id')->first();
        $urutan = $last ? ((int)substr($last->nomor_rab, -4) + 1) : 1;
        return 'RAB-' . $tgl . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);
    }

    // Hitung ulang semua biaya dari items
    public function hitungUlang(): void {
        $this->biaya_rangka  = $this->items()->where('tipe','rangka')->sum(\DB::raw('qty * harga_satuan'));
        $this->biaya_atap    = $this->items()->where('tipe','atap')->sum(\DB::raw('qty * harga_satuan'));
        $this->biaya_jasa    = $this->items()->where('tipe','jasa')->sum(\DB::raw('qty * harga_satuan'));
        $this->biaya_addon   = $this->items()->whereIn('tipe',['addon'])->sum(\DB::raw('qty * harga_satuan'));
        $this->biaya_kondisi = $this->items()->where('tipe','kondisi')->sum(\DB::raw('qty * harga_satuan'));
        $this->biaya_pokok_total = $this->biaya_rangka + $this->biaya_atap + $this->biaya_jasa + $this->biaya_addon + $this->biaya_kondisi;
        $this->biaya_setelah_buffer = $this->biaya_pokok_total * (1 + $this->buffer_persen / 100);
        $this->harga_sebelum_diskon = $this->biaya_setelah_buffer * (1 + $this->margin_persen / 100);
        $this->diskon_nominal = $this->harga_sebelum_diskon * ($this->diskon_persen / 100);
        $this->harga_final = $this->harga_sebelum_diskon - $this->diskon_nominal;
        $this->save();
    }

    // Apakah diskon melebihi batas?
    public function isDiskonOverLimit(): bool {
        $margin = RabMarginSetting::byProduk($this->produk_kode);
        if (!$margin) return false;
        return $this->diskon_persen > $margin->diskon_max_persen;
    }

    public function hargaFinalFormatted(): string {
        return 'Rp ' . number_format($this->harga_final, 0, ',', '.');
    }

    // Status label untuk UI
    public function statusLabel(): string {
        return match($this->status) {
            'draft'       => 'Draft',
            'sent'        => 'Terkirim',
            'negotiating' => 'Negosiasi',
            'deal'        => 'Deal',
            'batal'       => 'Batal',
            'revised'     => 'Direvisi',
            default       => $this->status
        };
    }

    public function statusColor(): string {
        return match($this->status) {
            'draft'       => '#64748b',
            'sent'        => '#3b82f6',
            'negotiating' => '#f59e0b',
            'deal'        => '#22c55e',
            'batal'       => '#ef4444',
            'revised'     => '#8b5cf6',
            default       => '#64748b'
        };
    }
}
