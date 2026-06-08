{{-- FILE: resources/views/penggajian/index.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Penggajian')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@section('content')
<div style="max-width:900px;margin:0 auto;">

    @if(session('success'))
    <div style="padding:14px;border-radius:10px;background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#6ee7b7;margin-bottom:16px;font-size:13px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="padding:14px;border-radius:10px;background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;margin-bottom:16px;font-size:13px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Filter Bulan/Tahun --}}
    <div class="stat-card" style="margin-bottom:16px;padding:16px;">
        <form method="GET" action="{{ route('penggajian.index') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select name="bulan" style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;">
                @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i=>$nb)
                <option value="{{ $i+1 }}" {{ $bulan==$i+1?'selected':'' }}>{{ $nb }}</option>
                @endforeach
            </select>
            <select name="tahun" style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;">
                @for($y=2025;$y<=2027;$y++)
                <option value="{{ $y }}" {{ $tahun==$y?'selected':'' }}>{{ $y }}</option>
                @endfor
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama..."
                style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;width:150px;">
            <button type="submit" style="background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;">Tampilkan</button>
        </form>
    </div>

    {{-- Filter Level --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        @php
        $levels = [0=>'Semua',2=>'Admin',3=>'Supervisor',4=>'Marketing',5=>'Teknisi',6=>'Driver',7=>'Toko'];
        $levelAktif = request('level', 0);
        @endphp
        @foreach($levels as $lv => $lb)
        <a href="{{ route('penggajian.index', ['bulan'=>$bulan,'tahun'=>$tahun,'level'=>$lv]) }}"
                   style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
           {{ $levelAktif==$lv ? 'background:#fbbf24;color:#0f172a;' : 'background:#1e293b;color:#94a3b8;border:1px solid #334155;' }}">
            {{ $lb }}
        </a>
        @endforeach
    </div>

    {{-- Widget Kewajiban --}}
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px;">
        <div class="stat-card" style="padding:16px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">💰 Total Kewajiban</div>
            <div style="font-size:18px;font-weight:700;color:#ef4444;">Rp {{ number_format($totalKewajiban,0,',','.') }}</div>
            <div style="font-size:11px;color:#64748b;">Belum dibayar</div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">✅ Sudah Dibayar</div>
            <div style="font-size:18px;font-weight:700;color:#10b981;">Rp {{ number_format($totalSudahBayar,0,',','.') }}</div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">⚠️ Perlu Konfirmasi</div>
            <div style="font-size:18px;font-weight:700;color:#f59e0b;">{{ $perluKonfirmasi }}</div>
            <div style="font-size:11px;color:#64748b;">Gaji di bawah batas aman</div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div style="font-size:11px;color:#64748b;margin-bottom:4px;">📅 Proyeksi UM tgl 15</div>
            <div style="font-size:18px;font-weight:700;color:#fbbf24;">Rp {{ number_format($proyeksiUM,0,',','.') }}</div>
        </div>
    </div>

    {{-- Generate Semua --}}
    <div class="stat-card" style="margin-bottom:16px;padding:16px;">
        <div style="font-size:13px;font-weight:600;color:#94a3b8;margin-bottom:12px;">⚡ Generate Slip Semua Karyawan</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <form method="POST" action="{{ route('penggajian.generate-semua') }}">
                @csrf
                <input type="hidden" name="periode" value="uang_makan">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <button type="submit" style="background:#06b6d4;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    📋 Generate UM 1-15
                </button>
            </form>
            <form method="POST" action="{{ route('penggajian.generate-semua') }}">
                @csrf
                <input type="hidden" name="periode" value="gaji_bulanan">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <button type="submit" style="background:#8b5cf6;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    💰 Generate Gaji Bulanan
                </button>
            </form>
            <form method="POST" action="{{ route('penggajian.bayar-semua') }}">
                @csrf
                <input type="hidden" name="periode" value="gaji_bulanan">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <button type="submit" onclick="return confirm('Proses bayar semua slip draft?')"
                    style="background:#10b981;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    ✅ Bayar Semua
                </button>
            </form>
        </div>
    </div>

    {{-- Tabel Karyawan --}}
    <div class="stat-card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid #334155;display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:13px;font-weight:600;color:#94a3b8;">
                👥 Status Slip Per Karyawan
                <span style="color:#475569;font-size:11px;margin-left:8px;">({{ $karyawan->count() }} orang)</span>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #334155;">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;color:#64748b;font-weight:600;">Karyawan</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;font-weight:600;">Level</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;font-weight:600;">UM 1-15</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;font-weight:600;">Gaji Bulanan</th>
                        <th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;font-weight:600;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($karyawan as $k)
                    @php
                        $slipUM   = $k->slipGaji->where('periode','uang_makan')->first();
                        $slipGaji = $k->slipGaji->where('periode','gaji_bulanan')->first();
                        $levelLabels = [1=>'Owner',2=>'Admin',3=>'Supervisor',4=>'Marketing',5=>'Teknisi',6=>'Driver',7=>'Toko'];
                        $levelColors = [2=>'#06b6d4',3=>'#8b5cf6',4=>'#f59e0b',5=>'#10b981',6=>'#3b82f6',7=>'#ec4899'];
                        $lc = $levelColors[$k->level] ?? '#64748b';
                    @endphp
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:12px 16px;">
                            <div style="font-size:13px;font-weight:600;color:#f1f5f9;">{{ $k->name }}</div>
                            <div style="font-size:11px;color:#64748b;">{{ $k->jabatan }}</div>
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            <span style="font-size:10px;padding:3px 8px;border-radius:20px;background:{{ $lc }}20;color:{{ $lc }};border:1px solid {{ $lc }}40;">
                                {{ $levelLabels[$k->level] ?? '-' }}
                            </span>
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            @if($slipUM)
                                <span style="font-size:10px;padding:3px 8px;border-radius:20px;background:{{ $slipUM->statusColor() }}20;color:{{ $slipUM->statusColor() }};border:1px solid {{ $slipUM->statusColor() }}40;">
                                    {{ $slipUM->statusLabel() }}
                                </span>
                                <div style="font-size:11px;color:#64748b;margin-top:2px;">Rp {{ number_format($slipUM->gaji_bersih,0,',','.') }}</div>
                                <a href="{{ route('penggajian.slip', $slipUM) }}" style="font-size:10px;color:#fbbf24;">lihat</a>
                            @else
                                <form method="POST" action="{{ route('penggajian.generate') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $k->id }}">
                                    <input type="hidden" name="periode" value="uang_makan">
                                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                                    <button type="submit" style="font-size:10px;background:#06b6d4;color:#fff;padding:3px 8px;border-radius:6px;border:none;cursor:pointer;">⚡ Generate</button>
                                </form>
                            @endif
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            @if($slipGaji)
                                <span style="font-size:10px;padding:3px 8px;border-radius:20px;background:{{ $slipGaji->statusColor() }}20;color:{{ $slipGaji->statusColor() }};border:1px solid {{ $slipGaji->statusColor() }}40;">
                                    {{ $slipGaji->statusLabel() }}
                                </span>
                                <div style="font-size:11px;color:#64748b;margin-top:2px;">Rp {{ number_format($slipGaji->gaji_bersih,0,',','.') }}</div>
                            @else
                                <span style="font-size:10px;color:#475569;">—</span>
                            @endif
                        </td>
                        <td style="padding:12px 8px;text-align:center;">
                            <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                @if($slipGaji)
                                    <a href="{{ route('penggajian.slip', $slipGaji) }}" style="font-size:11px;background:#334155;color:#e2e8f0;padding:4px 8px;border-radius:6px;text-decoration:none;">👁 Lihat</a>
                                    @if($slipGaji->status === 'menunggu_konfirmasi')
                                    <form method="POST" action="{{ route('penggajian.konfirmasi', $slipGaji) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" style="font-size:11px;background:#f59e0b;color:#0f172a;padding:4px 8px;border-radius:6px;border:none;cursor:pointer;">⚠️ Konfirmasi</button>
                                    </form>
                                    @endif
                                    @if($slipGaji->status === 'draft')
                                    <form method="POST" action="{{ route('penggajian.bayar', $slipGaji) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" onclick="return confirm('Proses bayar gaji {{ $k->name }}?')"
                                            style="font-size:11px;background:#10b981;color:#fff;padding:4px 8px;border-radius:6px;border:none;cursor:pointer;">💰 Bayar</button>
                                    </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('penggajian.generate') }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $k->id }}">
                                        <input type="hidden" name="periode" value="gaji_bulanan">
                                        <input type="hidden" name="bulan" value="{{ $bulan }}">
                                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                                        <button type="submit" style="font-size:11px;background:#6366f1;color:#fff;padding:4px 8px;border-radius:6px;border:none;cursor:pointer;">⚡ Generate</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:30px;color:#475569;">Tidak ada karyawan ditemukan</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection