{{-- FILE: resources/views/dashboard/owner.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Dashboard Owner')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@section('content')
<div style="max-width:900px;margin:0 auto;">

    {{-- Header Greeting --}}
    <div style="margin-bottom:20px;">
        <div style="font-size:22px;font-weight:800;color:#f1f5f9;">
            Selamat {{ $greeting }}, {{ auth()->user()->name }}! 👋
        </div>
        <div style="color:#64748b;font-size:13px;margin-top:4px;">
            {{ now()->translatedFormat('l, d F Y') }} · {{ now()->format('H:i') }} WIB
        </div>
    </div>

    {{-- ═══ ABSENSI HARI INI ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">📋 Absensi Hari Ini</div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;">
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#10b981;">{{ $absensi['hadir'] }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">✅ Hadir</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#ef4444;">{{ $absensi['alpha'] }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">❌ Alpha</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#06b6d4;">{{ $absensi['izin'] }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">📋 Izin</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#f59e0b;">{{ $absensi['belum'] }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">⏳ Belum</div>
        </div>
    </div>

    {{-- Siapa yang Alpha & Belum Pulang --}}
    @if($absensi['list_alpha']->count() > 0 || $absensi['list_belum_pulang']->count() > 0)
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
        @if($absensi['list_alpha']->count() > 0)
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;font-weight:700;color:#ef4444;margin-bottom:8px;">❌ Alpha Hari Ini</div>
            @foreach($absensi['list_alpha'] as $k)
            <div style="font-size:12px;color:#f1f5f9;padding:4px 0;border-bottom:1px solid #0f172a;">
                {{ $k->name }}
                <span style="color:#64748b;font-size:10px;">· {{ $k->jabatan }}</span>
            </div>
            @endforeach
        </div>
        @endif
        @if($absensi['list_belum_pulang']->count() > 0)
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;font-weight:700;color:#f59e0b;margin-bottom:8px;">⏳ Belum Pulang</div>
            @foreach($absensi['list_belum_pulang'] as $k)
            <div style="font-size:12px;color:#f1f5f9;padding:4px 0;border-bottom:1px solid #0f172a;">
                {{ $k->name }}
                <span style="color:#64748b;font-size:10px;">· {{ $k->absensi->first()?->jam_masuk ? substr($k->absensi->first()->jam_masuk,0,5) : '-' }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ PENDING APPROVAL ═══ --}}
    @if($pending['izin'] > 0 || $pending['slip'] > 0)
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">⚠️ Perlu Perhatian</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
        @if($pending['izin'] > 0)
        <a href="{{ route('izin.approval') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:14px;border:1px solid rgba(245,158,11,0.3);">
                <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $pending['izin'] }}</div>
                <div style="font-size:11px;color:#64748b;">📋 Izin Pending</div>
                <div style="font-size:10px;color:#f59e0b;margin-top:4px;">Tap untuk approve →</div>
            </div>
        </a>
        @endif
        @if($pending['slip'] > 0)
        <a href="{{ route('penggajian.index') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:14px;border:1px solid rgba(239,68,68,0.3);">
                <div style="font-size:24px;font-weight:800;color:#ef4444;">{{ $pending['slip'] }}</div>
                <div style="font-size:11px;color:#64748b;">💰 Slip Perlu Konfirmasi</div>
                <div style="font-size:10px;color:#ef4444;margin-top:4px;">Tap untuk konfirmasi →</div>
            </div>
        </a>
        @endif
    </div>
    @endif

    {{-- ═══ KEUANGAN BULAN INI ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">💰 Keuangan Bulan Ini</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Kewajiban Gaji</div>
            <div style="font-size:16px;font-weight:700;color:#ef4444;">Rp {{ number_format($keuangan['kewajiban_gaji'],0,',','.') }}</div>
            <div style="font-size:10px;color:#475569;">Belum dibayar</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Sudah Dibayar</div>
            <div style="font-size:16px;font-weight:700;color:#10b981;">Rp {{ number_format($keuangan['sudah_bayar'],0,',','.') }}</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Proyeksi UM tgl 15</div>
            <div style="font-size:16px;font-weight:700;color:#fbbf24;">Rp {{ number_format($keuangan['proyeksi_um'],0,',','.') }}</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Total Kasbon Aktif</div>
            <div style="font-size:16px;font-weight:700;color:#8b5cf6;">Rp {{ number_format($keuangan['kasbon_aktif'],0,',','.') }}</div>
        </div>
    </div>

    {{-- ═══ ESENSI LAPORAN KEUANGAN ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">💵 Laporan Keuangan</div>
    <div class="stat-card" style="padding:16px;margin-bottom:20px;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
            <div style="text-align:center;">
                <div style="font-size:15px;font-weight:700;color:#10b981;">Rp {{ number_format($laporanKeuangan['pemasukan'],0,',','.') }}</div>
                <div style="font-size:10px;color:#64748b;margin-top:2px;">📈 Pemasukan</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:15px;font-weight:700;color:#ef4444;">Rp {{ number_format($laporanKeuangan['pengeluaran'],0,',','.') }}</div>
                <div style="font-size:10px;color:#64748b;margin-top:2px;">📉 Pengeluaran</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:15px;font-weight:700;color:{{ $laporanKeuangan['profit'] >= 0 ? '#10b981' : '#ef4444' }};">
                    Rp {{ number_format(abs($laporanKeuangan['profit']),0,',','.') }}
                </div>
                <div style="font-size:10px;color:#64748b;margin-top:2px;">{{ $laporanKeuangan['profit'] >= 0 ? '✅ Profit' : '❌ Rugi' }}</div>
            </div>
        </div>
        <div style="text-align:center;margin-top:10px;font-size:11px;color:#475569;">
            *Data dari modul Keuangan (belum tersedia)
        </div>
    </div>

    {{-- ═══ ESENSI PROJECT ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">🏗️ Project</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#06b6d4;">{{ $project['aktif'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">🔨 Aktif</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $project['selesai'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">✅ Selesai</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $project['pending'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">⏳ Pending</div>
        </div>
    </div>
    <div class="stat-card" style="padding:14px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:11px;color:#64748b;">Nilai Project Bulan Ini</div>
                <div style="font-size:18px;font-weight:700;color:#fbbf24;">Rp {{ number_format($project['nilai_bulan_ini'],0,',','.') }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:#64748b;">Total Nilai Aktif</div>
                <div style="font-size:18px;font-weight:700;color:#06b6d4;">Rp {{ number_format($project['nilai_total'],0,',','.') }}</div>
            </div>
        </div>
        <div style="text-align:center;margin-top:8px;font-size:11px;color:#475569;">
            *Data dari modul Project (belum tersedia)
        </div>
    </div>

    {{-- ═══ ESENSI LEADS & SURVEI ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">🎯 Leads & Survei</div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:20px;">
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Leads Bulan Ini</div>
            <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $leads['bulan_ini'] }}</div>
            <div style="font-size:10px;color:#64748b;">Total: {{ $leads['total'] }}</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Survei Pending</div>
            <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $leads['survei_pending'] }}</div>
            <div style="font-size:10px;color:#64748b;">Perlu ditindaklanjuti</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Closing Rate</div>
            <div style="font-size:24px;font-weight:800;color:#fbbf24;">{{ $leads['closing_rate'] }}%</div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Deal Bulan Ini</div>
            <div style="font-size:24px;font-weight:800;color:#06b6d4;">{{ $leads['deal_bulan_ini'] }}</div>
        </div>
    </div>
    <div style="text-align:center;font-size:11px;color:#475569;margin-bottom:20px;">
        *Data dari modul Pipeline Survey (belum tersedia)
    </div>

    {{-- ═══ STATISTIK SDM ═══ --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">👥 SDM Bulan Ini</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#fbbf24;">{{ $sdm['total_karyawan'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">👥 Total Aktif</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#ef4444;">{{ $sdm['total_alpha'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">❌ Total Alpha</div>
        </div>
        <div class="stat-card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $sdm['total_telat'] }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">⏰ Total Telat</div>
        </div>
    </div>

    {{-- Shortcut --}}
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">⚡ Akses Cepat</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
        <a href="{{ route('absensi.rekap') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">📋</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Rekap Absen</div>
            </div>
        </a>
        <a href="{{ route('penggajian.index') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">💰</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Penggajian</div>
            </div>
        </a>
        <a href="{{ route('izin.approval') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">✅</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Approval Izin</div>
            </div>
        </a>
        <a href="{{ route('karyawan.index') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">👥</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Karyawan</div>
            </div>
        </a>
        <a href="{{ route('penggajian.kasbon') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">💳</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Kasbon</div>
            </div>
        </a>
        <a href="{{ route('profil.index') }}" style="text-decoration:none;">
            <div class="stat-card" style="padding:12px;text-align:center;">
                <div style="font-size:20px;">👤</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Profil</div>
            </div>
        </a>
    </div>

</div>
@endsection