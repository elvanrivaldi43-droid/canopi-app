<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'name', 'email', 'password',
        'level', 'jabatan', 'no_hp', 'alamat', 'foto',
        'gaji_harian', 'uang_makan', 'gaji_bulanan',
        'jam_masuk', 'jam_pulang', 'status',
        'tgl_masuk_kerja', 'tipe_gaji',
        'nama_bank', 'no_rekening', 'atas_nama',
        'nama_kontak_darurat', 'no_kontak_darurat',
        'tanggal_bergabung',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'tgl_masuk_kerja'   => 'date',
            'tanggal_bergabung' => 'date',
        ];
    }
    // Relasi ke tunjangan
    public function tunjangan()
    {
        return $this->belongsToMany(TunjanganMaster::class, 'karyawan_tunjangan', 'user_id', 'tunjangan_master_id')
                    ->withPivot('nominal')
                    ->withTimestamps();
    }
    // Relasi absensi
    public function absensi()
    {
        return $this->hasMany(Absensi::class);
    }
    public function izinAbsen()
    {
        return $this->hasMany(IzinAbsen::class);
    }
    public function absensiHariIni()
    {
        return $this->hasOne(Absensi::class)->whereDate('tanggal', today());
    }
    public function slipGaji()
    {
        return $this->hasMany(\App\Models\SlipGaji::class);
    }
    public function kasbon()
    {
        return $this->hasMany(\App\Models\Kasbon::class);
    }
    public function tabungan()
    {
        return $this->hasOne(\App\Models\TabunganKaryawan::class);
    }
    // Helper: nama level
    public function namaLevel(): string
    {
        $levels = [
            0 => 'Unknown',
            1 => 'Owner',
            2 => 'Admin Operasional',
            3 => 'Supervisor Lapangan',
            4 => 'Marketing',
            5 => 'Teknisi',
            6 => 'Driver',
            7 => 'Admin Toko Besi',
        ];
        return $levels[$this->level] ?? 'Unknown';
    }
    // Helper: warna status
    public function warnStatus(): string
    {
        return match($this->status) {
            'aktif'    => '#10B981',
            'sp1'      => '#F59E0B',
            'sp2'      => '#F97316',
            'sp3'      => '#EF4444',
            'nonaktif' => '#64748B',
            default    => '#64748B',
        };
    }
    // Helper: label status
    public function labelStatus(): string
    {
        return match($this->status) {
            'aktif'    => 'Aktif',
            'sp1'      => 'SP 1',
            'sp2'      => 'SP 2',
            'sp3'      => 'SP 3',
            'nonaktif' => 'Nonaktif',
            default    => 'Unknown',
        };
    }
    // Helper: masa kerja
    public function masaKerja(): string
    {
        if (!$this->tgl_masuk_kerja) return '-';
        $diff = $this->tgl_masuk_kerja->diff(now());
        if ($diff->y > 0) return $diff->y . ' tahun ' . $diff->m . ' bulan';
        if ($diff->m > 0) return $diff->m . ' bulan ' . $diff->d . ' hari';
        return $diff->d . ' hari';
    }
}