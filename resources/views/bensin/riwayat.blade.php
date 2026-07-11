@extends('layouts.app')
@section('title', 'Riwayat BBM')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
}
.page-title { font-size: 1.2rem; font-weight: 700; color: #fbbf24; margin: 0; }
.btn-catat {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 10px 18px; border-radius: 10px; font-weight: 700;
    font-size: 0.9rem; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-catat:hover { background: #f59e0b; color: #0f172a; }

/* Alert aktif */
.alert-aktif {
    background: rgba(245,158,11,0.12); border: 1px solid #f59e0b;
    border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap;
}
.alert-aktif-text { font-size: 0.88rem; color: #fbbf24; }
.btn-pulang {
    background: #22c55e; color: #0f172a; border: none;
    padding: 8px 16px; border-radius: 8px; font-weight: 700;
    font-size: 0.85rem; cursor: pointer; text-decoration: none;
    white-space: nowrap;
}

/* Stats */
.stats-bar {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px; margin-bottom: 18px;
}
@media(max-width:480px) { .stats-bar { grid-template-columns: repeat(3, 1fr); } }
.stat-card {
    background: #1e293b; border-radius: 12px; padding: 14px;
    text-align: center; border: 1px solid #334155;
}
.stat-num { font-size: 1.3rem; font-weight: 800; }
.stat-label { font-size: 0.72rem; color: #64748b; margin-top: 2px; }

/* Filter */
.filter-bar {
    background: #1e293b; border-radius: 12px; padding: 12px 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 16px; border: 1px solid #334155;
}
.filter-bar select, .filter-bar input {
    background: #0f172a; color: #e2e8f0; border: 1px solid #334155;
    border-radius: 8px; padding: 8px 12px; font-size: 0.9rem;
}
.btn-filter {
    background: #334155; color: #e2e8f0; border: none;
    padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem;
}

/* Log card */
.log-card {
    background: #1e293b; border-radius: 12px; padding: 16px;
    margin-bottom: 10px; border: 1px solid #334155;
    border-left: 4px solid #334155;
}
.log-card.selesai { border-left-color: #22c55e; }
.log-card.berangkat { border-left-color: #f59e0b; }
.log-card.boros { border-left-color: #ef4444; }

.log-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 8px; margin-bottom: 10px;
}
.log-tujuan { font-size: 0.95rem; font-weight: 700; color: #f1f5f9; }
.badge-status {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}

.log-meta {
    display: flex; flex-wrap: wrap; gap: 10px;
    font-size: 0.8rem; color: #64748b;
}
.log-meta span { display: flex; align-items: center; gap: 4px; }

/* Konsumsi indicator */
.konsumsi-bar {
    margin-top: 10px; padding-top: 10px; border-top: 1px solid #334155;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.konsumsi-val {
    font-size: 0.95rem; font-weight: 700;
}
.konsumsi-val.normal { color: #4ade80; }
.konsumsi-val.boros { color: #f87171; }
.konsumsi-standar { font-size: 0.78rem; color: #475569; }

.empty-state { text-align: center; padding: 50px 20px; color: #475569; }
.alert-success {
    background: rgba(34,197,94,.15); border: 1px solid #22c55e;
    color: #4ade80; padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-size: 0.9rem;
}
</style>

<div class="container" style="max-width:720px; margin:0 auto; padding:16px;">

    @if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <h1 class="page-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px; margin-right:6px;"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Riwayat BBM
        </h1>
        <a href="{{ route('bensin.create') }}" class="btn-catat">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Catat BBM
        </a>
    </div>

    {{-- Alert log aktif (belum input pulang) --}}
    @if($logAktif)
    <div class="alert-aktif">
        <div class="alert-aktif-text">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Perjalanan ke <strong>{{ $logAktif->tujuan }}</strong> belum dicatat KM pulang!
        </div>
        <a href="{{ route('bensin.pulang', $logAktif->id) }}" class="btn-pulang">
            Isi KM Pulang →
        </a>
    </div>
    @endif

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
            <div class="stat-num" style="color:#22c55e;">Rp{{ number_format($totalNominal/1000, 0) }}k</div>
            <div class="stat-label">Total BBM</div>
        </div>
    </div>

    {{-- Filter bulan --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('bensin.riwayat') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
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
            <button type="submit" class="btn-filter">Tampilkan</button>
        </form>
    </div>

    {{-- Daftar log --}}
    @forelse($logs as $log)
    @php
        $boros = $log->status === 'selesai' && $log->isBoros();
        $cardClass = $log->status === 'berangkat' ? 'berangkat' : ($boros ? 'boros' : 'selesai');
    @endphp
    <div class="log-card {{ $cardClass }}">
        <div class="log-header">
            <div class="log-tujuan">{{ $log->tujuan }}</div>
            <span class="badge-status" style="background:{{ $log->warnaStatus() }}20; color:{{ $log->warnaStatus() }};">
                {{ $log->labelStatus() }}
            </span>
        </div>
        <div class="log-meta">
            <span>📅 {{ $log->tanggal->format('d/m/Y') }}</span>
            <span>🚗 {{ $log->kendaraan->nama }}</span>
            <span>⛽ {{ $log->liter }} liter</span>
            <span>💰 Rp {{ number_format($log->nominal, 0, ',', '.') }}</span>
            @if($log->km_awal)
            <span>📍 KM {{ number_format($log->km_awal, 1) }}</span>
            @endif
        </div>

        @if($log->status === 'selesai' && $log->km_tempuh)
        <div class="konsumsi-bar">
            <div>
                <div class="konsumsi-val {{ $boros ? 'boros' : 'normal' }}">
                    {{ $log->konsumsi_aktual }} km/liter
                    @if($boros) ⚠️ @else ✅ @endif
                </div>
                <div class="konsumsi-standar">Standar: {{ $log->kendaraan->standar_km_per_liter }} km/liter</div>
            </div>
            <div style="margin-left:auto; text-align:right;">
                <div style="font-size:0.88rem; font-weight:700; color:#94a3b8;">{{ number_format($log->km_tempuh, 1) }} km</div>
                <div style="font-size:0.72rem; color:#475569;">jarak tempuh</div>
            </div>
        </div>
        @endif

        @if($log->status === 'berangkat')
        <div style="margin-top:10px;">
            <a href="{{ route('bensin.pulang', $log->id) }}"
               style="background:#22c55e; color:#0f172a; padding:7px 14px; border-radius:8px; font-size:0.82rem; font-weight:700; text-decoration:none;">
                Isi KM Pulang →
            </a>
        </div>
        @endif
    </div>
    @empty
    <div class="empty-state">
        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4"/></svg>
        <p>Belum ada log BBM bulan ini.</p>
    </div>
    @endforelse

</div>
@endsection