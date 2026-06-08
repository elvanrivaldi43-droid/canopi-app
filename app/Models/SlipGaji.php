<?php
// FILE: app/Models/SlipGaji.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlipGaji extends Model
{
    protected $table = 'slip_gaji';

    protected $fillable = [
        'user_id', 'periode', 'bulan', 'tahun',
        'tanggal_generate', 'tanggal_bayar', 'status',
        'hari_hadir', 'hari_alpha', 'hari_telat', 'hari_izin',
        'gaji_pokok', 'total_uang_makan', 'total_tunjangan',
        'bonus_kpi', 'kelas_kpi', 'bonus_lembur', 'jam_lembur',
        'potongan_telat', 'potongan_kasbon', 'potongan_insidental',
        'tabungan_wajib', 'tabungan_lebaran',
        'total_pendapatan', 'total_potongan', 'gaji_bersih',
        'warning_batas_aman', 'owner_konfirmasi', 'catatan',
    ];

    protected $casts = [
        'tanggal_generate'   => 'date',
        'tanggal_bayar'      => 'date',
        'warning_batas_aman' => 'boolean',
        'owner_konfirmasi'   => 'boolean',
    ];

    const BATAS_AMAN = 500000; // Rp 500.000

    // Bonus KPI
    const BONUS_KPI = [
        'platinum' => 300000,
        'gold'     => 150000,
        'silver'   => 75000,
        'none'     => 0,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function periodeLabel(): string
    {
        return match($this->periode) {
            'uang_makan'   => 'Uang Makan 1-15',
            'gaji_bulanan' => 'Gaji Bulanan',
            default        => '-',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft'               => '📝 Draft',
            'menunggu_konfirmasi' => '⚠️ Perlu Konfirmasi',
            'dibayar'             => '✅ Sudah Dibayar',
            default               => '-',
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'draft'               => '#64748B',
            'menunggu_konfirmasi' => '#F59E0B',
            'dibayar'             => '#10B981',
            default               => '#64748B',
        };
    }

    public function kelasKpiLabel(): string
    {
        return match($this->kelas_kpi) {
            'platinum' => '🏆 Platinum',
            'gold'     => '🥇 Gold',
            'silver'   => '🥈 Silver',
            default    => '—',
        };
    }

    public function namaBulan(): string
    {
        $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
        return $bulan[$this->bulan] ?? '-';
    }
}