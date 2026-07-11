@extends('layouts.app')

@section('content')
<div style="padding: 16px; max-width: 900px; margin: 0 auto;">

    {{-- HEADER --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="color: #fbbf24; font-size: 20px; font-weight: 700; margin: 0;">📊 Poin Kinerja Karyawan</h1>
            <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0;">Dihitung otomatis setiap awal bulan</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('kpi.soal.index') }}" style="background: #1e293b; color: #94a3b8; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; border: 1px solid #334155;">📝 Bank Soal</a>
            <a href="{{ route('kpi.rapor.index') }}" style="background: #1e293b; color: #94a3b8; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; border: 1px solid #334155;">🎓 Rapor</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background: #064e3b; border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- FILTER BULAN --}}
    <form method="GET" style="margin-bottom: 20px;">
        <select name="bulan" onchange="this.form.submit()" style="background: #1e293b; color: #e2e8f0; border: 1px solid #334155; padding: 8px 12px; border-radius: 8px; font-size: 14px; margin-right: 8px;">
            @foreach($bulanOptions as $opt)
            <option value="{{ $opt['bulan'] }}" {{ $opt['bulan'] == $bulan && $opt['tahun'] == $tahun ? 'selected' : '' }}
                data-tahun="{{ $opt['tahun'] }}">
                {{ $opt['label'] }}
            </option>
            @endforeach
        </select>
        <input type="hidden" name="tahun" value="{{ $tahun }}">
    </form>

    {{-- STAT CARDS --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div style="background: #1e293b; border-radius: 10px; padding: 14px; border: 1px solid #334155; text-align: center;">
            <div style="font-size: 22px; font-weight: 700; color: #e2e8f0;">{{ $stats['total'] }}</div>
            <div style="font-size: 12px; color: #94a3b8;">Total Dihitung</div>
        </div>
        <div style="background: #1e293b; border-radius: 10px; padding: 14px; border: 1px solid #334155; text-align: center;">
            <div style="font-size: 22px; font-weight: 700; color: #fbbf24;">{{ $stats['bintang5'] }}</div>
            <div style="font-size: 12px; color: #94a3b8;">⭐⭐⭐⭐⭐</div>
        </div>
        <div style="background: #1e293b; border-radius: 10px; padding: 14px; border: 1px solid #334155; text-align: center;">
            <div style="font-size: 22px; font-weight: 700; color: #60a5fa;">{{ $stats['avg_poin'] }}</div>
            <div style="font-size: 12px; color: #94a3b8;">Rata-rata Poin</div>
        </div>
        <div style="background: #1e293b; border-radius: 10px; padding: 14px; border: 1px solid #ef4444; text-align: center;">
            <div style="font-size: 22px; font-weight: 700; color: #ef4444;">{{ $stats['red_zone'] }}</div>
            <div style="font-size: 12px; color: #94a3b8;">Red Zone</div>
        </div>
    </div>

    {{-- BINTANG BULAN LALU --}}
    @if($bintangBulanLalu->count() > 0)
    <div style="background: linear-gradient(135deg, #1c1400, #2d1f00); border: 1px solid #fbbf24; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
        <div style="color: #fbbf24; font-weight: 700; font-size: 15px; margin-bottom: 12px;">🏆 Bintang Bulan Lalu</div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            @foreach($bintangBulanLalu as $b)
            <div style="background: rgba(251,191,36,0.1); border: 1px solid #fbbf24; border-radius: 8px; padding: 8px 14px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">🏆</span>
                <div>
                    <div style="color: #fbbf24; font-weight: 600; font-size: 14px;">{{ $b->user->name }}</div>
                    <div style="color: #94a3b8; font-size: 11px;">{{ \App\Models\UjianSoal::namaJabatan($b->user->level) }} · {{ $b->total_poin }} poin</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- USULAN SP --}}
    @if($usulanSp->count() > 0)
    <div style="background: #1e293b; border: 1px solid #ef4444; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
        <div style="color: #ef4444; font-weight: 700; font-size: 15px; margin-bottom: 12px;">⚠️ Usulan SP Menunggu Konfirmasi ({{ $usulanSp->count() }})</div>
        @foreach($usulanSp as $sp)
        <div style="background: #0f172a; border-radius: 8px; padding: 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div>
                <span style="background: #ef4444; color: white; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 700;">{{ strtoupper($sp->level_sp) }}</span>
                <span style="color: #e2e8f0; font-weight: 600; margin-left: 8px;">{{ $sp->user->name }}</span>
                <div style="color: #94a3b8; font-size: 12px; margin-top: 4px;">{{ $sp->alasan }}</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <form method="POST" action="{{ route('kpi.sp.konfirmasi', $sp->id) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="aksi" value="setujui">
                    <button type="submit" style="background: #ef4444; color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;" onclick="return confirm('Setujui SP ini?')">✅ Setujui</button>
                </form>
                <form method="POST" action="{{ route('kpi.sp.konfirmasi', $sp->id) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="aksi" value="tolak">
                    <button type="submit" style="background: #1e293b; color: #94a3b8; border: 1px solid #334155; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;">❌ Tolak</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- TOMBOL HITUNG MANUAL --}}
    <form method="POST" action="{{ route('kpi.hitung') }}" style="margin-bottom: 20px;">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <button type="submit" style="background: #1e293b; color: #fbbf24; border: 1px solid #fbbf24; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 14px;" onclick="return confirm('Hitung ulang KPI bulan ini?')">
            🔄 Hitung KPI Manual
        </button>
    </form>

    {{-- TABEL KARYAWAN --}}
    <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden;">
        <div style="padding: 14px 16px; border-bottom: 1px solid #334155; color: #e2e8f0; font-weight: 600; font-size: 15px;">
            Daftar Poin Kinerja
        </div>
        @if($kpiList->count() > 0)
        @foreach($kpiList as $kpi)
        @php
            $poin = $kpi->total_poin;
            $bintang = str_repeat('⭐', $kpi->bintang);
            $barColor = $poin >= 75 ? '#fbbf24' : ($poin >= 45 ? '#60a5fa' : '#ef4444');
        @endphp
        <div style="padding: 14px 16px; border-bottom: 1px solid #0f172a; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 12px; min-width: 200px;">
                @if($kpi->is_bintang_jabatan)
                <span style="font-size: 20px;">🏆</span>
                @endif
                <div>
                    <div style="color: #e2e8f0; font-weight: 600; font-size: 14px;">{{ $kpi->user->name ?? '-' }}</div>
                    <div style="color: #64748b; font-size: 12px;">{{ \App\Models\UjianSoal::namaJabatan($kpi->user->level ?? 0) }}</div>
                </div>
            </div>
            <div style="flex: 1; min-width: 120px; max-width: 200px;">
                <div style="background: #0f172a; border-radius: 4px; height: 6px; overflow: hidden;">
                    <div style="background: {{ $barColor }}; width: {{ $poin }}%; height: 100%;"></div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="color: {{ $barColor }}; font-weight: 700; font-size: 16px;">{{ $poin }}</div>
                <div style="font-size: 12px;">{{ $bintang }}</div>
            </div>
            @if($kpi->bonus_nominal > 0)
            <div style="background: rgba(251,191,36,0.1); border: 1px solid #fbbf24; color: #fbbf24; font-size: 12px; padding: 3px 10px; border-radius: 6px; white-space: nowrap;">
                +Rp {{ number_format($kpi->bonus_nominal, 0, ',', '.') }}
            </div>
            @endif
            @if($kpi->is_alpha)
            <div style="background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                Ada Alpha
            </div>
            @endif
            <a href="{{ route('kpi.detail', $kpi->user_id) }}" style="background: #0f172a; color: #94a3b8; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; border: 1px solid #334155;">Detail</a>
        </div>
        @endforeach
        @else
        <div style="padding: 40px; text-align: center; color: #64748b;">
            Belum ada data KPI bulan ini.<br>
            <small>Klik "Hitung KPI Manual" untuk menghitung sekarang.</small>
        </div>
        @endif
    </div>

</div>
@endsection