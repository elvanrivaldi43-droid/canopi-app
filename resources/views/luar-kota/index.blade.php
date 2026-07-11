@extends('layouts.app')
@section('title', 'Mode Luar Kota')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
}
.page-title { font-size: 1.2rem; font-weight: 700; color: #fbbf24; margin: 0; }
.btn-tambah {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 10px 18px; border-radius: 10px; font-weight: 700;
    font-size: 0.9rem; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-tambah:hover { background: #f59e0b; color: #0f172a; }

/* Sedang luar kota hari ini */
.hari-ini-card {
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3);
    border-radius: 14px; padding: 16px; margin-bottom: 20px;
}
.hari-ini-title {
    font-size: 0.82rem; font-weight: 700; color: #f59e0b;
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
}
.karyawan-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.karyawan-chip-lk {
    background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);
    border-radius: 20px; padding: 5px 12px; font-size: 0.82rem; color: #fbbf24;
    display: flex; align-items: center; gap: 6px;
}

/* Filter */
.filter-bar {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;
}
.filter-btn {
    padding: 7px 14px; border-radius: 20px; font-size: 0.82rem;
    border: 1px solid #334155; background: transparent; color: #94a3b8;
    cursor: pointer; text-decoration: none;
}
.filter-btn.active {
    background: #fbbf24; color: #0f172a; border-color: #fbbf24; font-weight: 700;
}

/* Kartu luar kota */
.lk-card {
    background: #1e293b; border-radius: 14px; padding: 16px;
    margin-bottom: 10px; border: 1px solid #334155;
    border-left: 4px solid #334155;
}
.lk-card.aktif { border-left-color: #f59e0b; }
.lk-card.selesai { border-left-color: #22c55e; }
.lk-card.dibatalkan { border-left-color: #ef4444; }

.lk-header {
    display: flex; justify-content: space-between;
    align-items: flex-start; gap: 10px; margin-bottom: 10px;
}
.lk-nama { font-size: 1rem; font-weight: 700; color: #f1f5f9; }
.lk-jabatan { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

.badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}

.lk-meta {
    display: flex; flex-wrap: wrap; gap: 12px;
    font-size: 0.8rem; color: #64748b; margin-bottom: 10px;
}
.lk-meta span { display: flex; align-items: center; gap: 4px; }

.lk-actions {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding-top: 10px; border-top: 1px solid #334155;
}
.btn-sm {
    padding: 6px 14px; border-radius: 8px; font-size: 0.8rem;
    border: none; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center;
}
.btn-edit { background: #334155; color: #e2e8f0; }
.btn-selesai { background: rgba(34,197,94,.15); color: #4ade80; }
.btn-batal { background: rgba(239,68,68,.15); color: #f87171; }

/* Progress bar tanggal */
.progress-tanggal {
    margin-top: 8px; background: #0f172a; border-radius: 6px; height: 6px; overflow: hidden;
}
.progress-fill { height: 100%; background: #f59e0b; border-radius: 6px; transition: width .3s; }

.empty-state { text-align: center; padding: 50px 20px; color: #475569; }
.alert-success {
    background: rgba(34,197,94,.15); border: 1px solid #22c55e;
    color: #4ade80; padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-size: 0.9rem;
}
</style>

<div class="container" style="max-width:760px; margin:0 auto; padding:16px;">

    @if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <h1 class="page-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Mode Luar Kota
        </h1>
        <a href="{{ route('luar-kota.create') }}" class="btn-tambah">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Aktifkan Luar Kota
        </a>
    </div>

    {{-- Sedang luar kota hari ini --}}
    @if($sedangLuarKota->count() > 0)
    <div class="hari-ini-card">
        <div class="hari-ini-title">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Sedang Luar Kota Hari Ini ({{ $sedangLuarKota->count() }} orang)
        </div>
        <div class="karyawan-chips">
            @foreach($sedangLuarKota as $lk)
            <div class="karyawan-chip-lk">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $lk->karyawan->name }} — {{ $lk->lokasi }}
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Filter status --}}
    <div class="filter-bar">
        <a href="{{ route('luar-kota.index', ['status'=>'aktif']) }}"
           class="filter-btn {{ $status=='aktif'?'active':'' }}">Aktif</a>
        <a href="{{ route('luar-kota.index', ['status'=>'selesai']) }}"
           class="filter-btn {{ $status=='selesai'?'active':'' }}">Selesai</a>
        <a href="{{ route('luar-kota.index', ['status'=>'dibatalkan']) }}"
           class="filter-btn {{ $status=='dibatalkan'?'active':'' }}">Dibatalkan</a>
        <a href="{{ route('luar-kota.index', ['status'=>'semua']) }}"
           class="filter-btn {{ $status=='semua'?'active':'' }}">Semua</a>
    </div>

    {{-- Daftar --}}
    @forelse($daftar as $lk)
    @php
        $today    = today();
        $total    = $lk->tanggal_mulai->diffInDays($lk->tanggal_selesai) + 1;
        $lewat    = $lk->status === 'aktif' ? min($lk->tanggal_mulai->diffInDays($today) + 1, $total) : $total;
        $persen   = $total > 0 ? min(100, round(($lewat / $total) * 100)) : 100;
    @endphp
    <div class="lk-card {{ $lk->status }}">
        <div class="lk-header">
            <div>
                <div class="lk-nama">{{ $lk->karyawan->name ?? '-' }}</div>
                <div class="lk-jabatan">{{ $lk->karyawan->jabatan ?? '' }}</div>
            </div>
            <span class="badge" style="background:{{ $lk->warnaStatus() }}20; color:{{ $lk->warnaStatus() }};">
                {{ $lk->labelStatus() }}
            </span>
        </div>

        <div class="lk-meta">
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $lk->lokasi }}
            </span>
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ $lk->tanggal_mulai->format('d/m/Y') }} — {{ $lk->tanggal_selesai->format('d/m/Y') }}
            </span>
            <span>🗓️ {{ $lk->durasiHari() }} hari</span>
            <span>Oleh: {{ $lk->dibuatOleh->name ?? '-' }}</span>
        </div>

        @if($lk->keterangan)
        <div style="font-size:0.82rem; color:#94a3b8; margin-bottom:8px; font-style:italic;">
            "{{ $lk->keterangan }}"
        </div>
        @endif

        {{-- Progress bar hari --}}
        @if($lk->status === 'aktif')
        <div style="font-size:0.75rem; color:#64748b; margin-bottom:4px;">
            Hari ke-{{ $lewat }} dari {{ $total }}
        </div>
        <div class="progress-tanggal">
            <div class="progress-fill" style="width:{{ $persen }}%;"></div>
        </div>
        @endif

        {{-- Aksi --}}
        @if($lk->status === 'aktif')
        <div class="lk-actions">
            <a href="{{ route('luar-kota.edit', $lk->id) }}" class="btn-sm btn-edit">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Tanggal
            </a>
            <form method="POST" action="{{ route('luar-kota.selesai', $lk->id) }}" style="display:inline;"
                  onsubmit="return confirm('Tandai selesai hari ini?')">
                @csrf
                <button type="submit" class="btn-sm btn-selesai">Tandai Selesai</button>
            </form>
            <form method="POST" action="{{ route('luar-kota.batalkan', $lk->id) }}" style="display:inline;"
                  onsubmit="return confirm('Batalkan mode luar kota ini?')">
                @csrf
                <button type="submit" class="btn-sm btn-batal">Batalkan</button>
            </form>
        </div>
        @endif
    </div>
    @empty
    <div class="empty-state">
        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        <p>Tidak ada data luar kota.</p>
    </div>
    @endforelse

</div>
@endsection