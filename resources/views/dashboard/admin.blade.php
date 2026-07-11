@extends('layouts.app')

@section('page-title', 'Dashboard Admin')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-karyawan')
@endsection

@section('content')
<div style="display:flex;flex-direction:column;gap:16px;max-width:600px;margin:0 auto;">

    {{-- Selamat datang --}}
    <div style="border-radius:16px;padding:20px;background:linear-gradient(135deg,#0F1117 0%,#1E2535 100%);border:1px solid rgba(201,168,76,0.2);">
        <p style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#C9A84C;margin:0 0 4px 0;">
            {{ now()->isoFormat('dddd, D MMMM Y') }}
        </p>
        <h2 style="font-size:18px;font-weight:700;color:white;margin:0 0 4px 0;">
            Halo, {{ auth()->user()->name }}! 👋
        </h2>
        <p style="font-size:13px;color:#64748B;margin:0;">
            {{ auth()->user()->jabatan }} · {{ auth()->user()->namaLevel() }}
        </p>
    </div>

    {{-- Status Absen Hari Ini --}}
    @php $absen = auth()->user()->absensiHariIni; @endphp
    <div style="border-radius:12px;padding:16px;
        @if(!$absen || !$absen->jam_masuk) background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);
        @elseif($absen->jam_masuk && !$absen->jam_pulang) background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);
        @else background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);
        @endif">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:12px;color:#94A3B8;margin-bottom:4px;">📋 Absensi Hari Ini</div>
                @if(!$absen || !$absen->jam_masuk)
                    <div style="font-weight:700;color:#EF4444;">Belum Absen!</div>
                @elseif($absen->jam_masuk && !$absen->jam_pulang)
                    <div style="font-weight:700;color:#F59E0B;">Masuk {{ substr($absen->jam_masuk,0,5) }} · Belum Pulang</div>
                @else
                    <div style="font-weight:700;color:#10B981;">Lengkap ✓ {{ substr($absen->jam_masuk,0,5) }}–{{ substr($absen->jam_pulang,0,5) }}</div>
                @endif
            </div>
            <a href="{{ route('absensi.index') }}"
               style="background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;text-decoration:none;">
                {{ !$absen || !$absen->jam_masuk ? 'Absen Sekarang' : 'Lihat Detail' }}
            </a>
        </div>
    </div>

    {{-- Info Gaji --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748B;margin-bottom:4px;">Gaji/Hari</div>
            <div style="font-size:15px;font-weight:700;color:#C9A84C;">
                Rp {{ number_format(auth()->user()->gaji_harian ?? 0, 0, ',', '.') }}
            </div>
        </div>
        <div class="stat-card" style="padding:14px;">
            <div style="font-size:11px;color:#64748B;margin-bottom:4px;">Uang Makan/Hari</div>
            <div style="font-size:15px;font-weight:700;color:#10B981;">
                Rp {{ number_format(auth()->user()->uang_makan ?? 0, 0, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Menu Cepat --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <a href="{{ route('absensi.index') }}" style="text-decoration:none;">
            <div class="stat-card" style="text-align:center;padding:20px;cursor:pointer;">
                <div style="font-size:28px;margin-bottom:8px;">📋</div>
                <div style="font-size:13px;font-weight:600;color:#F1F5F9;">Absensi</div>
            </div>
        </a>
        <a href="#" style="text-decoration:none;">
            <div class="stat-card" style="text-align:center;padding:20px;cursor:pointer;">
                <div style="font-size:28px;margin-bottom:8px;">💰</div>
                <div style="font-size:13px;font-weight:600;color:#F1F5F9;">Gaji & Kasbon</div>
            </div>
        </a>
        <a href="#" style="text-decoration:none;">
            <div class="stat-card" style="text-align:center;padding:20px;cursor:pointer;">
                <div style="font-size:28px;margin-bottom:8px;">📝</div>
                <div style="font-size:13px;font-weight:600;color:#F1F5F9;">Tugas Harian</div>
            </div>
        </a>
        <a href="#" style="text-decoration:none;">
            <div class="stat-card" style="text-align:center;padding:20px;cursor:pointer;">
                <div style="font-size:28px;margin-bottom:8px;">🏆</div>
                <div style="font-size:13px;font-weight:600;color:#F1F5F9;">KPI & Bonus</div>
            </div>
        </a>
    </div>

</div>
@endsection