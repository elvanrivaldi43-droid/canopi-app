<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Absensi extends Model
{
    protected $table = 'absensi';
    protected $fillable = [
        'user_id','tanggal','jam_masuk','jam_pulang',
        'foto_masuk','foto_pulang',
        'lat_masuk','lng_masuk','lat_pulang','lng_pulang',
        'status','keterangan','foto_surat',
        'potongan_telat','uang_makan_hari_ini','gaji_hari_ini',
        'dikoreksi','alasan_koreksi','dikoreksi_oleh',
    ];
    protected $casts = ['tanggal' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function dikoreksiOleh() { return $this->belongsTo(User::class, 'dikoreksi_oleh'); }

    public function statusLabel(): string {
        return match($this->status) {
            'hadir'        => '✅ Hadir',
            'telat'        => '⏰ Telat',
            'setengah_hari'=> '🌗 Setengah Hari',
            'sakit'        => '🏥 Sakit',
            'izin'         => '📋 Izin',
            'diliburkan'   => '😴 Diliburkan',
            'alpha'        => '❌ Alpha',
            default        => '-'
        };
    }

    public function statusColor(): string {
        return match($this->status) {
            'hadir'        => '#10B981',
            'telat'        => '#F59E0B',
            'setengah_hari'=> '#8B5CF6',
            'sakit'        => '#3B82F6',
            'izin'         => '#06B6D4',
            'diliburkan'   => '#94A3B8',
            'alpha'        => '#EF4444',
            default        => '#64748B'
        };
    }
}
