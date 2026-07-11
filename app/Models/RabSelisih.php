<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RabSelisih extends Model {
    protected $table = 'rab_selisih';
    public $timestamps = false;
    protected $fillable = ['rab_id','project_id','surveyor_id','harga_deal','total_rab_detail','selisih','persen_selisih','status_tindak','catatan'];
    protected $appends = ['status_warna'];

    public function komponen() { return $this->hasMany(RabSelisihKomponen::class, 'selisih_id'); }

    public function getStatusWarnaAttribute(): string {
        $p = (float)$this->persen_selisih;
        if ($p < 5) return 'green';
        if ($p <= 15) return 'orange';
        return 'red';
    }

    public function hitungPersen(): void {
        if ($this->harga_deal > 0) {
            $this->selisih = $this->total_rab_detail - $this->harga_deal;
            $this->persen_selisih = round(abs($this->selisih) / $this->harga_deal * 100, 2);
            $this->status_tindak = $this->persen_selisih < 5 ? 'otomatis'
                : ($this->persen_selisih <= 15 ? 'review_admin' : 'hubungi_customer');
            $this->save();
        }
    }
}
