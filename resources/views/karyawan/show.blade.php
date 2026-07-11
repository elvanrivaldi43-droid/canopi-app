@extends('layouts.app')

@section('page-title', $karyawan->name)

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="max-width:680px;margin:0 auto;">

    {{-- Back --}}
    <a href="{{ route('karyawan.index') }}" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#94A3B8;text-decoration:none;margin-bottom:20px;">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
        </svg>
        Kembali
    </a>

    {{-- Profile Card --}}
    <div class="stat-card" style="margin-bottom:16px;text-align:center;padding:28px;">
        {{-- Avatar --}}
        <div style="width:80px;height:80px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;background:linear-gradient(135deg,#C9A84C,#A8872E);color:white;overflow:hidden;">
            @if($karyawan->foto)
                <img src="{{ Storage::url($karyawan->foto) }}" style="width:100%;height:100%;object-fit:cover;">
            @else
                {{ strtoupper(substr($karyawan->name, 0, 1)) }}
            @endif
        </div>

        <h2 style="font-size:20px;font-weight:700;margin:0 0 6px 0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">
            {{ $karyawan->name }}
        </h2>
        <div style="font-size:13px;color:#94A3B8;margin-bottom:12px;">{{ $karyawan->jabatan ?? $levels[$karyawan->level] }}</div>

        <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
            <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:{{ $karyawan->warnStatus() }}20;color:{{ $karyawan->warnStatus() }};border:1px solid {{ $karyawan->warnStatus() }}40;">
                {{ $karyawan->labelStatus() }}
            </span>
            <span style="font-size:11px;padding:4px 12px;border-radius:20px;background:rgba(201,168,76,0.1);color:#C9A84C;border:1px solid rgba(201,168,76,0.3);">
                {{ $levels[$karyawan->level] ?? '' }}
            </span>
        </div>
    </div>

    {{-- Info Grid --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        @php
        $infos = [
            ['label'=>'Email','value'=>$karyawan->email,'icon'=>'📧'],
            ['label'=>'No. HP','value'=>$karyawan->no_hp ?? '-','icon'=>'📱'],
            ['label'=>'Masuk Kerja','value'=>$karyawan->tgl_masuk_kerja ? $karyawan->tgl_masuk_kerja->format('d M Y') : '-','icon'=>'📅'],
            ['label'=>'Masa Kerja','value'=>$karyawan->masaKerja(),'icon'=>'⏱️'],
            ['label'=>'Jam Masuk','value'=>$karyawan->jam_masuk,'icon'=>'⏰'],
            ['label'=>'Jam Pulang','value'=>$karyawan->jam_pulang,'icon'=>'🏠'],
        ];
        @endphp
        @foreach($infos as $info)
        <div class="stat-card" style="padding:14px 16px;">
            <div style="font-size:18px;margin-bottom:4px;">{{ $info['icon'] }}</div>
            <div style="font-size:11px;color:#94A3B8;margin-bottom:2px;">{{ $info['label'] }}</div>
            <div style="font-size:13px;font-weight:600;word-break:break-all;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">{{ $info['value'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Alamat --}}
    @if($karyawan->alamat)
    <div class="stat-card" style="margin-bottom:16px;">
        <div style="font-size:12px;color:#94A3B8;margin-bottom:4px;">📍 Alamat</div>
        <div style="font-size:13px;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">{{ $karyawan->alamat }}</div>
    </div>
    @endif

    {{-- Data Gaji (Owner only) --}}
    @if(auth()->user()->level == 1)
    <div class="stat-card" style="margin-bottom:16px;">
        <h3 style="font-size:13px;font-weight:700;margin:0 0 16px 0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">💰 Data Gaji</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
                <div style="font-size:11px;color:#94A3B8;margin-bottom:2px;">Tipe Gaji</div>
                <div style="font-size:13px;font-weight:600;color:#C9A84C;">{{ ucfirst($karyawan->tipe_gaji) }}</div>
            </div>
            <div>
                <div style="font-size:11px;color:#94A3B8;margin-bottom:2px;">Gaji Harian</div>
                <div style="font-size:13px;font-weight:600;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">Rp {{ number_format($karyawan->gaji_harian, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size:11px;color:#94A3B8;margin-bottom:2px;">Gaji Bulanan</div>
                <div style="font-size:13px;font-weight:600;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">Rp {{ number_format($karyawan->gaji_bulanan, 0, ',', '.') }}</div>
            </div>
            <div>
                <div style="font-size:11px;color:#94A3B8;margin-bottom:2px;">Uang Makan/Hari</div>
                <div style="font-size:13px;font-weight:600;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">Rp {{ number_format($karyawan->uang_makan, 0, ',', '.') }}</div>
            </div>
        </div>

        @if($karyawan->tunjangan->count() > 0)
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid;" :style="darkMode ? 'border-color:rgba(255,255,255,0.06)' : 'border-color:#F1F5F9'">
            <div style="font-size:11px;color:#94A3B8;margin-bottom:10px;">Tunjangan</div>
            @foreach($karyawan->tunjangan as $t)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid;" :style="darkMode ? 'border-color:rgba(255,255,255,0.04)' : 'border-color:#F8FAFC'">
                <span style="font-size:12px;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">{{ $t->nama_tunjangan }}</span>
                <span style="font-size:12px;font-weight:600;color:#C9A84C;">Rp {{ number_format($t->pivot->nominal, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- Rekening --}}
    @if($karyawan->no_rekening)
    <div class="stat-card" style="margin-bottom:16px;">
        <h3 style="font-size:13px;font-weight:700;margin:0 0 12px 0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">🏦 Rekening Bank</h3>
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;background:rgba(201,168,76,0.1);">🏦</div>
            <div>
                <div style="font-size:14px;font-weight:700;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">{{ $karyawan->nama_bank }}</div>
                <div style="font-size:13px;color:#94A3B8;">{{ $karyawan->no_rekening }} · a.n {{ $karyawan->atas_nama }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Actions (Owner only) --}}
    @if(auth()->user()->level == 1)
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
        <a href="{{ route('karyawan.edit', $karyawan) }}"
           style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>
            </svg>
            Edit Data Karyawan
        </a>

        {{-- Reset Password --}}
        <div x-data="{ showReset: false }">
            <button @click="showReset = !showReset"
                    style="width:100%;padding:12px;border-radius:12px;font-size:13px;font-weight:600;border:1.5px solid;cursor:pointer;background:transparent;"
                    :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#94A3B8' : 'border-color:#E2E8F0;color:#64748B'">
                🔑 Reset Password
            </button>

            <div x-show="showReset" x-transition style="margin-top:10px;">
                <form method="POST" action="{{ route('karyawan.reset-password', $karyawan) }}">
                    @csrf
                    <div style="margin-bottom:10px;">
                        <input type="password" name="password" placeholder="Password baru (min 6 karakter)" required
                               style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                               :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    </div>
                    <div style="margin-bottom:10px;">
                        <input type="password" name="password_confirmation" placeholder="Konfirmasi password baru" required
                               style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                               :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    </div>
                    <button type="submit" style="width:100%;padding:12px;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;color:white;background:#3B82F6;">
                        Simpan Password Baru
                    </button>
                </form>
            </div>
        </div>

        {{-- Nonaktifkan / Aktifkan --}}
        @if($karyawan->status != 'nonaktif')
        <form method="POST" action="{{ route('karyawan.nonaktifkan', $karyawan) }}"
              onsubmit="return confirm('Yakin nonaktifkan {{ $karyawan->name }}?')">
            @csrf
            <button type="submit" style="width:100%;padding:12px;border-radius:12px;font-size:13px;font-weight:600;border:none;cursor:pointer;color:#EF4444;background:rgba(239,68,68,0.1);">
                ⚠️ Nonaktifkan Karyawan
            </button>
        </form>
        @else
        <form method="POST" action="{{ route('karyawan.aktifkan', $karyawan) }}">
            @csrf
            <button type="submit" style="width:100%;padding:12px;border-radius:12px;font-size:13px;font-weight:600;border:none;cursor:pointer;color:#10B981;background:rgba(16,185,129,0.1);">
                ✅ Aktifkan Kembali
            </button>
        </form>
        @endif
    </div>
    @endif

</div>
@endsection