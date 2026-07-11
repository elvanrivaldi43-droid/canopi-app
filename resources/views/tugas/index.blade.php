@extends('layouts.app')
@section('title', 'Tugas Harian')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-title { font-size: 1.3rem; font-weight: 700; color: #fbbf24; margin: 0; }
.btn-buat {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 10px 20px; border-radius: 10px; font-weight: 700;
    font-size: 0.9rem; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-buat:hover { background: #f59e0b; color: #0f172a; }

/* Filter tanggal */
.filter-bar {
    background: #1e293b; border-radius: 12px; padding: 14px 16px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    margin-bottom: 20px; border: 1px solid #334155;
}
.filter-bar label { color: #94a3b8; font-size: 0.85rem; }
.filter-bar input[type=date] {
    background: #0f172a; color: #e2e8f0; border: 1px solid #334155;
    border-radius: 8px; padding: 8px 12px; font-size: 0.9rem;
}
.btn-filter {
    background: #334155; color: #e2e8f0; border: none;
    padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 0.9rem;
}
.btn-filter:hover { background: #475569; }
.btn-today {
    background: #1d4ed8; color: #fff; border: none;
    padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem;
}

/* Stats bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
@media (max-width: 600px) {
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
}
.stat-card {
    background: #1e293b; border-radius: 12px; padding: 14px;
    text-align: center; border: 1px solid #334155;
}
.stat-num { font-size: 1.6rem; font-weight: 800; }
.stat-label { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }

/* Kartu tugas */
.tugas-card {
    background: #1e293b; border-radius: 14px; padding: 16px;
    margin-bottom: 12px; border: 1px solid #334155;
    border-left: 4px solid #334155;
    transition: border-color 0.2s;
}
.tugas-card.prioritas-tinggi { border-left-color: #ef4444; }
.tugas-card.prioritas-sedang { border-left-color: #f59e0b; }
.tugas-card.prioritas-rendah { border-left-color: #22c55e; }

.tugas-header {
    display: flex; justify-content: space-between;
    align-items: flex-start; gap: 10px; margin-bottom: 10px;
}
.tugas-judul {
    font-size: 1rem; font-weight: 700; color: #f1f5f9;
    text-decoration: none;
}
.tugas-judul:hover { color: #fbbf24; }

.badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 600; white-space: nowrap;
}
.badge-prioritas-tinggi { background: rgba(239,68,68,0.2); color: #f87171; }
.badge-prioritas-sedang { background: rgba(245,158,11,0.2); color: #fbbf24; }
.badge-prioritas-rendah { background: rgba(34,197,94,0.2); color: #4ade80; }

.tugas-meta {
    font-size: 0.8rem; color: #94a3b8;
    display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 10px;
}
.tugas-meta span { display: flex; align-items: center; gap: 4px; }

/* Assignees */
.assignee-list {
    display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;
}
.assignee-chip {
    display: flex; align-items: center; gap: 6px;
    background: #0f172a; border-radius: 20px;
    padding: 4px 10px; font-size: 0.78rem;
    border: 1px solid #334155;
}
.status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}

/* Tombol aksi */
.tugas-actions {
    display: flex; gap: 8px; margin-top: 12px;
    padding-top: 10px; border-top: 1px solid #334155;
    flex-wrap: wrap;
}
.btn-sm {
    padding: 6px 14px; border-radius: 8px; font-size: 0.8rem;
    border: none; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-detail { background: #1d4ed8; color: #fff; }
.btn-edit { background: #334155; color: #e2e8f0; }
.btn-hapus { background: rgba(239,68,68,0.15); color: #f87171; }

/* Empty state */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: #475569;
}
.empty-state svg { margin-bottom: 16px; opacity: 0.4; }
.empty-state p { font-size: 0.95rem; }

/* Alert */
.alert-success {
    background: rgba(34,197,94,0.15); border: 1px solid #22c55e;
    color: #4ade80; padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-size: 0.9rem;
}
</style>

<div class="container" style="max-width:800px; margin:0 auto; padding:16px;">

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    {{-- Header --}}
    <div class="page-header">
        <h1 class="page-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px; margin-right:6px;">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
                <polyline points="9 16 11 18 15 14"/>
            </svg>
            Tugas Harian
        </h1>
        @if(in_array(Auth::user()->level, [1,2,3]))
        <a href="{{ route('tugas.create') }}" class="btn-buat">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Buat Tugas
        </a>
        @endif
    </div>

    {{-- Filter Tanggal --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('tugas.index') }}" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <label>Tanggal:</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}">
            <button type="submit" class="btn-filter">Tampilkan</button>
            <a href="{{ route('tugas.index') }}" class="btn-today">Hari Ini</a>
        </form>
        <span style="color:#475569; font-size:0.82rem; margin-left:auto;">
            {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
        </span>
    </div>

    {{-- Stats bar (hanya owner/admin/supervisor) --}}
    @if(in_array(Auth::user()->level, [1,2,3]))
    @php
        $totalTugas   = $tugasList->count();
        $totalAssign  = $tugasList->sum(fn($t) => $t->assignees->count());
        $totalSelesai = $tugasList->sum(fn($t) => $t->assignees->where('status','selesai')->count());
        $totalBelum   = $tugasList->sum(fn($t) => $t->assignees->where('status','belum')->count());
    @endphp
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-num" style="color:#fbbf24;">{{ $totalTugas }}</div>
            <div class="stat-label">Total Tugas</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#3b82f6;">{{ $totalAssign }}</div>
            <div class="stat-label">Karyawan Ditugaskan</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#22c55e;">{{ $totalSelesai }}</div>
            <div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#94a3b8;">{{ $totalBelum }}</div>
            <div class="stat-label">Belum Dikerjakan</div>
        </div>
    </div>
    @endif

    {{-- Daftar Tugas --}}
    @forelse($tugasList as $tugas)
    @php
        $myAssignee = $tugas->assignees->where('user_id', Auth::id())->first();
    @endphp
    <div class="tugas-card prioritas-{{ $tugas->prioritas }}">
        <div class="tugas-header">
            <a href="{{ route('tugas.show', $tugas->id) }}" class="tugas-judul">
                {{ $tugas->judul }}
            </a>
            <span class="badge badge-prioritas-{{ $tugas->prioritas }}">
                {{ ucfirst($tugas->prioritas) }}
            </span>
        </div>

        <div class="tugas-meta">
            @if($tugas->jam_mulai)
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ substr($tugas->jam_mulai, 0, 5) }}
                @if($tugas->jam_selesai_target) — {{ substr($tugas->jam_selesai_target, 0, 5) }} @endif
            </span>
            @endif
            @if($tugas->lokasi)
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $tugas->lokasi }}
            </span>
            @endif
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Oleh {{ $tugas->pembuat->name ?? '-' }}
            </span>
        </div>

        @if($tugas->deskripsi)
        <div style="font-size:0.85rem; color:#94a3b8; margin-bottom:10px; line-height:1.5;">
            {{ Str::limit($tugas->deskripsi, 120) }}
        </div>
        @endif

        {{-- Assignees (untuk admin/owner/supervisor) --}}
        @if(in_array(Auth::user()->level, [1,2,3]) && $tugas->assignees->count() > 0)
        <div class="assignee-list">
            @foreach($tugas->assignees as $a)
            <div class="assignee-chip">
                <div class="status-dot" style="background:{{ $a->warnaStatus() }};"></div>
                {{ $a->user->name ?? '-' }}
                <span style="color:#475569; font-size:0.72rem;">— {{ $a->labelStatus() }}</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Status milik sendiri (untuk karyawan) --}}
        @if($myAssignee && !in_array(Auth::user()->level, [1,2,3]))
        <div style="margin-top:10px;">
            <span class="badge" style="background:{{ $myAssignee->warnaStatus() }}20; color:{{ $myAssignee->warnaStatus() }};">
                {{ $myAssignee->labelStatus() }}
            </span>
        </div>
        @endif

        <div class="tugas-actions">
            <a href="{{ route('tugas.show', $tugas->id) }}" class="btn-sm btn-detail">Lihat Detail</a>
            @if(in_array(Auth::user()->level, [1,2,3]))
            <a href="{{ route('tugas.edit', $tugas->id) }}" class="btn-sm btn-edit">Edit</a>
            <form method="POST" action="{{ route('tugas.destroy', $tugas->id) }}"
                  onsubmit="return confirm('Hapus tugas ini?')" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn-sm btn-hapus">Hapus</button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state">
        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p>Tidak ada tugas untuk tanggal ini.</p>

    </div>
    @endforelse

</div>
@endsection