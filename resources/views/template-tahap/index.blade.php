@extends('layouts.app')
@section('title', 'Template Tahap')
@section('page-title', 'Template Tahap')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tt-wrap { padding:14px 12px 60px; }
.tt-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.tt-sub { font-size:12px; color:#64748b; margin:0 0 14px; max-width:760px; }
.tt-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:10px; }
.tt-nama { font-size:15px; font-weight:700; color:#f1f5f9; }
.tt-jenis { font-size:12px; color:#94a3b8; margin-top:2px; }
.tt-tahap-list { font-size:12px; color:#cbd5e1; margin-top:8px; }
.tt-badge { display:inline-block; background:#0f172a; border:1px solid #334155; border-radius:999px; padding:3px 10px; margin:2px 4px 0 0; }
.tt-nonaktif { opacity:0.5; }
.tt-actions { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.btn { border:none; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-grey { background:#334155; color:#e2e8f0; }
.btn-red { background:#ef4444; color:#fff; }
</style>

<div class="tt-wrap">
    <h1 class="tt-title">Template Tahap</h1>
    <p class="tt-sub">Paket tahap per jenis project — dipilih otomatis saat RAB deal jadi project, cocok berdasarkan jenis project.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <a href="{{ route('template-tahap.create') }}" class="btn btn-gold" style="margin-bottom:14px;">+ Template Baru</a>

    @forelse($templates as $t)
    <div class="tt-card {{ !$t->is_active ? 'tt-nonaktif' : '' }}">
        <div class="tt-nama">{{ $t->nama }}</div>
        <div class="tt-jenis">Jenis project: {{ $t->jenis_project }} — {{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</div>
        <div class="tt-tahap-list">
            @foreach($t->items as $item)
            <span class="tt-badge">{{ $loop->iteration }}. {{ $item->tahapMaster->nama ?? '(tahap dihapus)' }}</span>
            @endforeach
        </div>
        <div class="tt-actions">
            <a href="{{ route('template-tahap.edit', $t) }}" class="btn btn-grey">Edit</a>
            <form method="POST" action="{{ route('template-tahap.toggle', $t) }}" style="display:inline;">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-grey">{{ $t->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
            <form method="POST" action="{{ route('template-tahap.destroy', $t) }}" style="display:inline;" onsubmit="return confirm('Hapus template ini?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-red">Hapus</button>
            </form>
        </div>
    </div>
    @empty
    <p style="color:#64748b;font-size:13px;">Belum ada template. Klik "+ Template Baru" buat mulai.</p>
    @endforelse
</div>
@endsection
