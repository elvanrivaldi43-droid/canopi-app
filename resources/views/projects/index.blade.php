@extends('layouts.app')

@section('content')
<div style="padding:16px; max-width:1200px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="color:#fbbf24; font-size:20px; font-weight:700; margin:0;">Project Management</h1>
            <p style="color:#94a3b8; font-size:13px; margin:4px 0 0;">Kelola semua project aktif & selesai</p>
        </div>
        @if(auth()->user()->level <= 2)
        <a href="{{ route('projects.create') }}"
           style="background:#fbbf24; color:#0f172a; padding:10px 18px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Buat Project
        </a>
        @endif
    </div>

    

    {{-- Stats --}}
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:20px;">
        @foreach([['label'=>'Total Project','val'=>$stats['total'],'color'=>'#94a3b8'],['label'=>'Pengerjaan','val'=>$stats['pengerjaan'],'color'=>'#3b82f6'],['label'=>'Persiapan','val'=>$stats['persiapan'],'color'=>'#f59e0b'],['label'=>'Selesai','val'=>$stats['selesai'],'color'=>'#10b981']] as $s)
        <div style="background:#1e293b; border:1px solid #334155; border-radius:10px; padding:14px; text-align:center;">
            <div style="color:{{ $s['color'] }}; font-size:24px; font-weight:700;">{{ $s['val'] }}</div>
            <div style="color:#64748b; font-size:12px; margin-top:2px;">{{ $s['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <input name="search" value="{{ request('search') }}" placeholder="Cari customer, kode, jenis..."
               style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; flex:1; min-width:200px; outline:none;">
        <select name="status" style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; outline:none;">
            <option value="">Semua Status</option>
            @foreach(\App\Models\Project::$statusLabel as $k => $l)
            <option value="{{ $k }}" {{ request('status')==$k?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
        <button type="submit" style="background:#3b82f6; color:#fff; padding:9px 18px; border-radius:8px; border:none; font-weight:600; font-size:14px; cursor:pointer;">Cari</button>
        @if(request()->hasAny(['search','status']))
        <a href="{{ route('projects.index') }}" style="background:#334155; color:#94a3b8; padding:9px 14px; border-radius:8px; font-size:14px; text-decoration:none;">Reset</a>
        @endif
    </form>

    {{-- List Project --}}
    <div style="display:flex; flex-direction:column; gap:10px;">
        @forelse($projects as $p)
        <a href="{{ route('projects.show', $p) }}"
           style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px 20px; text-decoration:none; display:block; transition:border-color 0.2s;"
           onmouseover="this.style.borderColor='#fbbf24'" onmouseout="this.style.borderColor='#334155'">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                <div style="flex:1;">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
                        <span style="color:#64748b; font-size:12px; font-family:monospace;">{{ $p->kode_project }}</span>
                        <span style="background:{{ $p->status_color }}22; color:{{ $p->status_color }}; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                            {{ $p->status_label }}
                        </span>
                        @if($p->rateKondisi && $p->rateKondisi->kode !== 'STD')
                        <span style="background:#7c3aed22; color:#a78bfa; padding:2px 10px; border-radius:20px; font-size:11px;">
                            {{ $p->rateKondisi->nama }}
                        </span>
                        @endif
                    </div>
                    <div style="color:#e2e8f0; font-size:16px; font-weight:700; margin-bottom:4px;">{{ $p->nama_customer }}</div>
                    <div style="color:#94a3b8; font-size:13px;">{{ $p->jenis_project }}</div>
                    @if($p->alamat_project)
                    <div style="color:#64748b; font-size:12px; margin-top:4px;">📍 {{ Str::limit($p->alamat_project, 60) }}</div>
                    @endif
                </div>
                <div style="text-align:right;">
                    <div style="color:#fbbf24; font-size:18px; font-weight:700;">Rp {{ number_format($p->nilai_kontrak, 0, ',', '.') }}</div>
                    @if($p->tgl_mulai_target)
                    <div style="color:#64748b; font-size:12px; margin-top:4px;">
                        Target: {{ \Carbon\Carbon::parse($p->tgl_mulai_target)->format('d M Y') }}
                        @if($p->tgl_selesai_target)
                        → {{ \Carbon\Carbon::parse($p->tgl_selesai_target)->format('d M Y') }}
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </a>
        @empty
        <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:48px; text-align:center; color:#64748b;">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px; display:block; opacity:0.4;"><path d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/></svg>
            Belum ada project
        </div>
        @endforelse
    </div>

    @if($projects->hasPages())
    <div style="margin-top:16px;">{{ $projects->links() }}</div>
    @endif

</div>
@endsection
