{{-- FILE: resources/views/absensi/rekap-bulanan.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Rekap Absensi Bulanan')

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

    {{-- Filter --}}
    <div class="stat-card" style="margin-bottom:16px;padding:16px;">
        <form method="GET" action="{{ route('absensi.rekap-bulanan') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
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
            @if(auth()->user()->level <= 2)
            <select name="user_id" style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:13px;">
                <option value="">-- Semua Karyawan --</option>
                @foreach($daftarKaryawan as $k)
                <option value="{{ $k->id }}" {{ $userId==$k->id?'selected':'' }}>{{ $k->name }} ({{ $k->jabatan }})</option>
                @endforeach
            </select>
            @endif
            <button type="submit" style="background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;">🔍 Tampilkan</button>
        </form>
    </div>

    @foreach($rekapData as $data)
    @php
        $k = $data['karyawan'];
        $absensiList = $data['absensi'];
        $stats = $data['stats'];
        $hariDalamBulan = $data['hari_dalam_bulan'];
    @endphp

    <div class="stat-card" style="padding:0;overflow:hidden;margin-bottom:20px;">
        {{-- Header Karyawan --}}
        <div style="padding:14px 16px;background:#0f172a;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:14px;font-weight:700;color:#f1f5f9;">{{ $k->name }}</div>
                <div style="font-size:11px;color:#64748b;">{{ $k->jabatan }}</div>
            </div>
            <div style="display:flex;gap:10px;font-size:12px;">
                <span style="color:#10b981;">✅ {{ $stats['hadir'] }}</span>
                <span style="color:#ef4444;">❌ {{ $stats['alpha'] }}</span>
                <span style="color:#f59e0b;">⏰ {{ $stats['telat'] }}</span>
                <span style="color:#06b6d4;">📋 {{ $stats['izin'] }}</span>
            </div>
        </div>

        {{-- Statistik Ringkas --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid #334155;">
            <div style="padding:10px;text-align:center;border-right:1px solid #334155;">
                <div style="font-size:16px;font-weight:700;color:#10b981;">{{ $stats['hadir'] }}</div>
                <div style="font-size:10px;color:#64748b;">Hadir</div>
            </div>
            <div style="padding:10px;text-align:center;border-right:1px solid #334155;">
                <div style="font-size:16px;font-weight:700;color:#ef4444;">{{ $stats['alpha'] }}</div>
                <div style="font-size:10px;color:#64748b;">Alpha</div>
            </div>
            <div style="padding:10px;text-align:center;border-right:1px solid #334155;">
                <div style="font-size:14px;font-weight:700;color:#fbbf24;">Rp {{ number_format($stats['total_potongan'],0,',','.') }}</div>
                <div style="font-size:10px;color:#64748b;">Potongan</div>
            </div>
            <div style="padding:10px;text-align:center;">
                <div style="font-size:14px;font-weight:700;color:#10b981;">Rp {{ number_format($stats['total_gaji'],0,',','.') }}</div>
                <div style="font-size:10px;color:#64748b;">Est. Gaji</div>
            </div>
        </div>

        {{-- Tabel Harian --}}
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:600px;">
                <thead>
                    <tr style="border-bottom:1px solid #334155;background:#0f172a;">
                        <th style="padding:8px 12px;text-align:left;font-size:11px;color:#64748b;width:80px;">Tgl</th>
                        <th style="padding:8px 8px;text-align:left;font-size:11px;color:#64748b;width:70px;">Hari</th>
                        <th style="padding:8px 8px;text-align:center;font-size:11px;color:#64748b;">Masuk</th>
                        <th style="padding:8px 8px;text-align:center;font-size:11px;color:#64748b;">Pulang</th>
                        <th style="padding:8px 8px;text-align:center;font-size:11px;color:#64748b;">Status</th>
                        <th style="padding:8px 8px;text-align:right;font-size:11px;color:#64748b;">Potongan</th>
                        <th style="padding:8px 8px;text-align:right;font-size:11px;color:#64748b;">Gaji</th>
                    </tr>
                </thead>
                <tbody>
                @php
                    $namaHari = ['Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu'];
                    $statusColors = ['hadir'=>'#10b981','telat'=>'#f59e0b','setengah_hari'=>'#8b5cf6','alpha'=>'#ef4444','sakit'=>'#06b6d4','izin'=>'#6366f1','cuti'=>'#06b6d4','dinas_luar'=>'#94a3b8'];
                    $statusLabel = ['hadir'=>'Hadir','telat'=>'Telat','setengah_hari'=>'½ Hari','alpha'=>'Alpha','sakit'=>'Sakit','izin'=>'Izin','cuti'=>'Cuti','dinas_luar'=>'Dinas'];
                @endphp
                @for($tgl=1; $tgl<=$hariDalamBulan; $tgl++)
                @php
                    $date    = \Carbon\Carbon::createFromDate($tahun, $bulan, $tgl);
                    $hari    = $date->format('D');
                    $isLibur = $hari === 'Sun';
                    $absen   = $absensiList->get($date->format('Y-m-d'));
                    $sc      = $statusColors[$absen?->status ?? ''] ?? '#475569';
                @endphp
                <tr style="border-bottom:1px solid #1e293b;{{ $isLibur ? 'opacity:0.4;background:#0a0f1e;' : '' }}">
                   <td style="padding:8px 12px;font-size:12px;color:#ffffff;font-weight:600;">
    {{ $tgl }} {{ substr(\Carbon\Carbon::createFromDate($tahun,$bulan,$tgl)->translatedFormat('M'),0,3) }}
</td>
                    <td style="padding:8px 8px;font-size:11px;color:#64748b;">
                        {{ $namaHari[$hari] ?? $hari }}
                    </td>
                    <td style="padding:8px 8px;text-align:center;font-size:12px;color:#10b981;">
                        {{ $absen?->jam_masuk ? substr($absen->jam_masuk,0,5) : ($isLibur ? '—' : '—') }}
                    </td>
                    <td style="padding:8px 8px;text-align:center;font-size:12px;color:#3b82f6;">
                        {{ $absen?->jam_pulang ? substr($absen->jam_pulang,0,5) : '—' }}
                    </td>
                    <td style="padding:8px 8px;text-align:center;">
                        @if($isLibur)
                            <span style="font-size:10px;color:#475569;">Libur</span>
                        @elseif($absen)
                            <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:{{ $sc }}20;color:{{ $sc }};border:1px solid {{ $sc }}40;">
                                {{ $statusLabel[$absen->status] ?? $absen->status }}
                            </span>
                            @if($absen->dikoreksi ?? false)
                            <span style="font-size:9px;color:#64748b;"> ✏️</span>
                            @endif
                        @else
                            <span style="font-size:10px;color:#334155;">—</span>
                        @endif
                    </td>
                    <td style="padding:8px 8px;text-align:right;font-size:11px;color:{{ ($absen?->potongan_telat ?? 0) > 0 ? '#ef4444' : '#334155' }};">
                        {{ ($absen?->potongan_telat ?? 0) > 0 ? 'Rp '.number_format($absen->potongan_telat,0,',','.') : '—' }}
                    </td>
                    <td style="padding:8px 8px;text-align:right;font-size:11px;color:{{ ($absen?->gaji_hari_ini ?? 0) > 0 ? '#10b981' : '#334155' }};">
                        {{ ($absen?->gaji_hari_ini ?? 0) > 0 ? 'Rp '.number_format($absen->gaji_hari_ini,0,',','.') : '—' }}
                    </td>
                </tr>
                @endfor
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #334155;background:#0f172a;">
                        <td colspan="5" style="padding:10px 12px;font-size:12px;font-weight:700;color:#f1f5f9;">TOTAL</td>
                        <td style="padding:10px 8px;text-align:right;font-size:12px;font-weight:700;color:#ef4444;">
                            Rp {{ number_format($stats['total_potongan'],0,',','.') }}
                        </td>
                        <td style="padding:10px 8px;text-align:right;font-size:12px;font-weight:700;color:#10b981;">
                            Rp {{ number_format($stats['total_gaji'],0,',','.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endforeach

    @if(empty($rekapData))
    <div class="stat-card" style="padding:40px;text-align:center;">
        <div style="font-size:32px;margin-bottom:12px;">📋</div>
        <div style="color:#64748b;">Tidak ada data absensi</div>
    </div>
    @endif

</div>
@endsection