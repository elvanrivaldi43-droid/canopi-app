@extends('layouts.app')
@section('title', 'Rekap Log Bensin')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
}
.page-title { font-size: 1.2rem; font-weight: 700; color: #fbbf24; margin: 0; }
.btn-kendaraan {
    background: #334155; color: #e2e8f0; border: none;
    padding: 9px 16px; border-radius: 10px; font-size: 0.85rem;
    cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}

.stats-bar {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 10px; margin-bottom: 18px;
}
@media(max-width:600px) { .stats-bar { grid-template-columns: repeat(2, 1fr); } }
.stat-card {
    background: #1e293b; border-radius: 12px; padding: 14px;
    text-align: center; border: 1px solid #334155;
}
.stat-num { font-size: 1.3rem; font-weight: 800; }
.stat-label { font-size: 0.72rem; color: #64748b; margin-top: 2px; }

.filter-bar {
    background: #1e293b; border-radius: 12px; padding: 12px 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 16px; border: 1px solid #334155;
}
.filter-bar select {
    background: #0f172a; color: #e2e8f0; border: 1px solid #334155;
    border-radius: 8px; padding: 8px 12px; font-size: 0.9rem;
}
.btn-filter {
    background: #334155; color: #e2e8f0; border: none;
    padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem;
}

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th {
    text-align: left; font-size: 0.75rem; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.5px;
    padding: 10px 12px; border-bottom: 1px solid #334155;
    background: #1e293b;
}
td {
    padding: 11px 12px; font-size: 0.85rem; border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
tr:hover td { background: rgba(255,255,255,0.02); }

.badge {
    display: inline-block; padding: 3px 8px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 600;
}
.konsumsi-normal { color: #4ade80; font-weight: 700; }
.konsumsi-boros { color: #f87171; font-weight: 700; }
.empty-state { text-align: center; padding: 50px 20px; color: #475569; }
.alert-success {
    background: rgba(34,197,94,.15); border: 1px solid #22c55e;
    color: #4ade80; padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-size: 0.9rem;
}
</style>

<div class="container" style="max-width:900px; margin:0 auto; padding:16px;">

    @if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <h1 class="page-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px; margin-right:6px;"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Rekap Log Bensin
        </h1>
        @if(Auth::user()->level == 1)
        <a href="{{ route('bensin.kendaraan') }}" class="btn-kendaraan">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Master Kendaraan
        </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-num" style="color:#fbbf24;">{{ number_format($totalLiter, 1) }}</div>
            <div class="stat-label">Total Liter</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#3b82f6;">{{ number_format($totalKm, 0) }}</div>
            <div class="stat-label">Total KM</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#22c55e;">{{ $rataKonsumsi }}</div>
            <div class="stat-label">Rata KM/Liter</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#94a3b8;">Rp{{ number_format($totalNominal/1000, 0) }}k</div>
            <div class="stat-label">Total Pengeluaran</div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('bensin.index') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <select name="bulan">
                @foreach(range(1,12) as $b)
                <option value="{{ $b }}" {{ $bulan == $b ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($b)->translatedFormat('F') }}
                </option>
                @endforeach
            </select>
            <select name="tahun">
                @foreach([2025, 2026, 2027] as $t)
                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
            <select name="kendaraan_id">
                <option value="">Semua Kendaraan</option>
                @foreach($daftarKendaraan as $k)
                <option value="{{ $k->id }}" {{ $kendaraanId == $k->id ? 'selected' : '' }}>
                    {{ $k->nama }}
                </option>
                @endforeach
            </select>
            <button type="submit" class="btn-filter">Tampilkan</button>
        </form>
    </div>

    {{-- Tabel --}}
    <div style="background:#1e293b; border-radius:14px; border:1px solid #334155; overflow:hidden;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Driver</th>
                        <th>Kendaraan</th>
                        <th>Tujuan</th>
                        <th>BBM</th>
                        <th>KM Tempuh</th>
                        <th>Konsumsi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php $boros = $log->status === 'selesai' && $log->isBoros(); @endphp
                    <tr>
                        <td style="color:#94a3b8;">{{ $log->tanggal->format('d/m/Y') }}</td>
                        <td style="font-weight:600;">{{ $log->driver->name ?? '-' }}</td>
                        <td style="color:#94a3b8; font-size:0.8rem;">
                            {{ $log->kendaraan->nama ?? '-' }}<br>
                            <span style="color:#475569;">{{ $log->kendaraan->plat ?? '' }}</span>
                        </td>
                        <td>{{ Str::limit($log->tujuan, 35) }}</td>
                        <td>
                            <div style="font-weight:600;">{{ $log->liter }} L</div>
                            <div style="font-size:0.75rem; color:#475569;">Rp {{ number_format($log->nominal, 0, ',', '.') }}</div>
                        </td>
                        <td style="color:#94a3b8;">
                            {{ $log->km_tempuh ? number_format($log->km_tempuh, 1) . ' km' : '-' }}
                        </td>
                        <td>
                            @if($log->konsumsi_aktual)
                            <span class="{{ $boros ? 'konsumsi-boros' : 'konsumsi-normal' }}">
                                {{ $log->konsumsi_aktual }} km/L
                                @if($boros) ⚠️ @endif
                            </span>
                            @else
                            <span style="color:#475569;">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="background:{{ $log->warnaStatus() }}20; color:{{ $log->warnaStatus() }};">
                                {{ $log->labelStatus() }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:#475569;">
                            Tidak ada data untuk periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection