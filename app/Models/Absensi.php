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
        'gps_valid_masuk','gps_valid_pulang',
        'status','keterangan','foto_surat',
        'potongan_telat','uang_makan_hari_ini','gaji_hari_ini',
        'dikoreksi','alasan_koreksi','dikoreksi_oleh',
        // Kolom baru
        'foto_siang_1','foto_siang_2','foto_siang_3',
        'lat_siang','lng_siang','gps_valid_siang','jam_absen_siang',
        'status_pekerjaan','ada_kendala','jenis_kendala','deskripsi_kendala',
        'lembur_jam','lembur_approved','lembur_approved_oleh',
        'potongan_siang_dicatat',
        // Lapor Progress + Kembali Kerja (13 Agustus)
        'jam_lapor_progress','pertanyaan_progress','jawaban_progress','kendala_kenapa',
        'potongan_progress_dicatat',
        'lat_kembali_kerja','lng_kembali_kerja','gps_valid_kembali_kerja',
        // Kerja hari libur (15 Agustus)
        'kerja_hari_libur','upah_hari_libur',
        // Rincian asal potongan_telat (30 September) — lihat App\Services\RincianPotongan
        'rincian_potongan',
    ];

    protected $casts = [
        'tanggal'          => 'date',
        'kerja_hari_libur' => 'boolean',
        'rincian_potongan' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function dikoreksiOleh() { return $this->belongsTo(User::class, 'dikoreksi_oleh'); }

    // ─── Izin pulang cepat (spec 2026-10-26) ───────────────────────────
    const LEVEL_PULANG_CEPAT_MANDOR = [5, 6]; // Teknisi & Driver: Mandor juga boleh menyetujui

    public static function bolehAjukanPulangCepat(bool $sudahMasuk, bool $sudahPulang, string $jamSekarang, string $jamPulangJadwal, ?string $statusAjuan): ?string
    {
        if (!$sudahMasuk)  return 'Absen masuk dulu.';
        if ($sudahPulang)  return 'Kamu sudah absen pulang hari ini.';
        if (substr($jamSekarang, 0, 5) >= substr($jamPulangJadwal, 0, 5)) return 'Sudah jam pulang, langsung absen pulang saja.';
        if (in_array($statusAjuan, ['menunggu', 'disetujui'], true)) return 'Hari ini sudah ada ajuan pulang cepat.';
        return null;
    }

    public static function alasanTolakProsesPulangCepat(?string $status, string $tanggal, string $hariIni, bool $sudahPulang, int $pemilikId, int $aktorId, int $pemilikLevel, int $aktorLevel): ?string
    {
        if ($status !== 'menunggu')  return 'Ajuan ini sudah diproses.';
        if ($tanggal !== $hariIni)   return 'Ajuan sudah kedaluwarsa (bukan untuk hari ini).';
        if ($sudahPulang)            return 'Karyawan sudah absen pulang. Kalau darurat, pakai Koreksi Owner.';
        if ($pemilikId === $aktorId) return 'Tidak bisa memproses ajuan sendiri.';
        if ($aktorLevel === 1)       return null;
        if ($aktorLevel === 3 && in_array($pemilikLevel, self::LEVEL_PULANG_CEPAT_MANDOR, true)) return null;
        return 'Ajuan ini hanya bisa diproses Owner.';
    }

    public function statusLabel(): string {
        return match($this->status) {
            'hadir'         => '✅ Hadir',
            'telat'         => '⏰ Telat',
            'setengah_hari' => '🌗 Setengah Hari',
            'sakit'         => '🏥 Sakit',
            'izin'          => '📋 Izin',
            'diliburkan'    => '😴 Diliburkan',
            'alpha'         => '❌ Alpha',
            default         => '-'
        };
    }

    public function statusColor(): string {
        return match($this->status) {
            'hadir'         => '#10B981',
            'telat'         => '#F59E0B',
            'setengah_hari' => '#8B5CF6',
            'sakit'         => '#3B82F6',
            'izin'          => '#06B6D4',
            'diliburkan'    => '#94A3B8',
            'alpha'         => '#EF4444',
            default         => '#64748B'
        };
    }
}