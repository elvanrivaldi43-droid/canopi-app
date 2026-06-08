@extends('layouts.app')

@section('page-title', 'Absensi')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@section('content')
<div style="display:flex;flex-direction:column;gap:16px;max-width:600px;margin:0 auto;">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div style="padding:14px 16px;border-radius:12px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);font-size:13px;color:#10B981;display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">✅</span> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="padding:14px 16px;border-radius:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);font-size:13px;color:#F87171;display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">⚠️</span> {{ session('error') }}
    </div>
    @endif
    @if(session('info'))
    <div style="padding:14px 16px;border-radius:12px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);font-size:13px;color:#818CF8;display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">ℹ️</span> {{ session('info') }}
    </div>
    @endif

    {{-- Status Absen Hari Ini --}}
    <div style="border-radius:16px;padding:20px;background:linear-gradient(135deg,#0F1117 0%,#1E2535 100%);border:1px solid rgba(201,168,76,0.2);">
        <p style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#C9A84C;margin:0 0 4px 0;">
            {{ now()->isoFormat('dddd, D MMMM Y') }}
        </p>
        <h2 style="font-size:18px;font-weight:700;color:white;margin:0 0 16px 0;">Status Hari Ini</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            {{-- Masuk --}}
            <div style="padding:14px;border-radius:12px;background:rgba(255,255,255,0.05);text-align:center;">
                <div style="font-size:22px;margin-bottom:6px;">🏢</div>
                <div style="font-size:11px;color:#64748B;margin-bottom:4px;">JAM MASUK</div>
                <div style="font-size:16px;font-weight:700;color:{{ $absenHariIni?->jam_masuk ? '#10B981' : '#64748B' }};">
                    {{ $absenHariIni?->jam_masuk ? substr($absenHariIni->jam_masuk, 0, 5) : '--:--' }}
                </div>
            </div>
            {{-- Pulang --}}
            <div style="padding:14px;border-radius:12px;background:rgba(255,255,255,0.05);text-align:center;">
                <div style="font-size:22px;margin-bottom:6px;">🏠</div>
                <div style="font-size:11px;color:#64748B;margin-bottom:4px;">JAM PULANG</div>
                <div style="font-size:16px;font-weight:700;color:{{ $absenHariIni?->jam_pulang ? '#10B981' : '#64748B' }};">
                    {{ $absenHariIni?->jam_pulang ? substr($absenHariIni->jam_pulang, 0, 5) : '--:--' }}
                </div>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        @if(!$absenHariIni || !$absenHariIni->jam_masuk)
        <a href="{{ route('absensi.form-masuk') }}"
           style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);min-height:54px;">
            📷 ABSEN MASUK SEKARANG
        </a>

        @elseif($fase === 'perlu_absen_siang')
        {{-- Sudah masuk pagi, perlu absen siang --}}
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:10px 14px;border-radius:10px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);font-size:13px;color:#10B981;text-align:center;">
                ✅ Sudah absen masuk pukul {{ substr($absenHariIni->jam_masuk, 0, 5) }}
                @if($absenHariIni->status === 'telat')
                · <span style="color:#F59E0B;">⏰ Telat</span>
                @elseif($absenHariIni->status === 'setengah_hari')
                · <span style="color:#F59E0B;">⚠️ Setengah Hari</span>
                @endif
            </div>
            <a href="{{ route('absensi.form-siang') }}"
               style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#F59E0B,#D97706);min-height:54px;">
                🌤️ ABSEN SIANG SEKARANG
            </a>
        </div>

        @elseif($fase === 'perlu_pulang')
        {{-- Sudah masuk & absen siang, perlu pulang --}}
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:10px 14px;border-radius:10px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);font-size:13px;color:#10B981;text-align:center;">
                ✅ Masuk {{ substr($absenHariIni->jam_masuk, 0, 5) }}
                @if($absenHariIni->jam_absen_siang)
                · Siang {{ substr($absenHariIni->jam_absen_siang, 0, 5) }}
                @endif
            </div>
            <a href="{{ route('absensi.form-pulang') }}"
               style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;color:white;background:linear-gradient(135deg,#3B82F6,#1D4ED8);min-height:54px;">
                🏠 ABSEN PULANG SEKARANG
            </a>
        </div>

        @else
        {{-- Lengkap --}}
        <div style="padding:14px;border-radius:12px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;">🎉</div>
            <div style="font-size:14px;font-weight:700;color:#10B981;">Absensi Hari Ini Lengkap</div>
            <div style="font-size:12px;color:#64748B;margin-top:4px;">
                {{ substr($absenHariIni->jam_masuk, 0, 5) }} – {{ substr($absenHariIni->jam_pulang, 0, 5) }}
                · {{ $absenHariIni->statusLabel() }}
            </div>
        </div>
        @endif
    </div>

    {{-- Stats Bulan Ini --}}
    <div>
        <p style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748B;margin:0 0 10px 0;">
            Rekap {{ now()->isoFormat('MMMM Y') }}
        </p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
            @php
            $statCards = [
                ['label'=>'Hadir', 'value'=>$stats['hadir'],  'color'=>'#10B981','icon'=>'✅'],
                ['label'=>'Alpha', 'value'=>$stats['alpha'],  'color'=>'#EF4444','icon'=>'❌'],
                ['label'=>'Telat', 'value'=>$stats['telat'],  'color'=>'#F59E0B','icon'=>'⏰'],
            ];
            @endphp
            @foreach($statCards as $sc)
            <div class="stat-card" style="text-align:center;padding:14px 8px;">
                <div style="font-size:20px;margin-bottom:6px;">{{ $sc['icon'] }}</div>
                <div style="font-size:20px;font-weight:700;color:{{ $sc['color'] }};">{{ $sc['value'] }}</div>
                <div style="font-size:11px;color:#64748B;">{{ $sc['label'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Finansial bulan ini --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
            <div class="stat-card" style="padding:14px;">
                <div style="font-size:11px;color:#64748B;margin-bottom:4px;">Total Uang Makan</div>
                <div style="font-size:15px;font-weight:700;color:#C9A84C;">
                    Rp {{ number_format($stats['total_um'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
            <div class="stat-card" style="padding:14px;">
                <div style="font-size:11px;color:#64748B;margin-bottom:4px;">Potongan Telat</div>
                <div style="font-size:15px;font-weight:700;color:#EF4444;">
                    Rp {{ number_format($stats['total_potongan'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat 30 Hari --}}
    <div>
        <p style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748B;margin:0 0 10px 0;">Riwayat Absensi</p>

        @forelse($riwayat as $r)
        <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:14px 16px;margin-bottom:8px;">
            {{-- Tanggal --}}
            <div style="width:44px;text-align:center;flex-shrink:0;">
                <div style="font-size:18px;font-weight:700;line-height:1;color:{{ $r->statusColor() }};">{{ $r->tanggal->format('d') }}</div>
                <div style="font-size:10px;color:#64748B;text-transform:uppercase;">{{ $r->tanggal->isoFormat('MMM') }}</div>
            </div>

            {{-- Info --}}
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:#F1F5F9;margin-bottom:3px;">
                    {{ $r->statusLabel() }}
                    @if($r->dikoreksi ?? false)
                    <span style="font-size:10px;color:#94A3B8;margin-left:4px;">· dikoreksi</span>
                    @endif
                </div>
                <div style="font-size:12px;color:#94A3B8;">
                    @if($r->jam_masuk)
                        Masuk {{ substr($r->jam_masuk,0,5) }}
                        @if($r->jam_absen_siang) · Siang {{ substr($r->jam_absen_siang,0,5) }} @endif
                        @if($r->jam_pulang) · Pulang {{ substr($r->jam_pulang,0,5) }} @endif
                    @else
                        Tidak ada catatan
                    @endif
                </div>
            </div>

            {{-- Badge potongan --}}
            <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;flex-shrink:0;background:{{ $r->statusColor() }}20;color:{{ $r->statusColor() }};border:1px solid {{ $r->statusColor() }}40;">
                @if(($r->potongan_telat ?? 0) > 0)
                    -Rp{{ number_format($r->potongan_telat/1000,0) }}rb
                @else
                    {{ $r->statusLabel() }}
                @endif
            </span>
        </div>
        @empty
        <div class="stat-card" style="text-align:center;padding:40px;">
            <div style="font-size:32px;margin-bottom:12px;">📅</div>
            <div style="font-size:14px;font-weight:600;color:#64748B;">Belum ada riwayat absensi</div>
        </div>
        @endforelse
    </div>

</div>
@endsection