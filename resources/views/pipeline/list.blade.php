@extends('layouts.app')
@section('page-title', 'Pipeline — List View')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-pipeline')
@endsection

@section('content')
<div>

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <div style="font-size:20px;font-weight:800;color:#f1f5f9;">Pipeline — List</div>
            <div style="font-size:12px;color:#64748b;">{{ $leads->total() }} lead ditemukan</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('pipeline.index') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#1e293b;color:#94a3b8;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;border:1px solid #334155;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                Kanban
            </a>
            <a href="{{ route('pipeline.create') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fbbf24;color:#0f172a;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('pipeline.list') }}" style="background:#1e293b;border-radius:14px;padding:14px;margin-bottom:16px;border:1px solid #334155;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:4px;">Status</label>
                <select name="status" style="width:100%;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <option value="">Semua Status</option>
                    @foreach($statusList as $key => $label)
                    <option value="{{ $key }}" {{ request('status')==$key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:4px;">Produk</label>
                <select name="produk" style="width:100%;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <option value="">Semua Produk</option>
                    @foreach(['kanopi','pagar','tralis','tenda'] as $p)
                    <option value="{{ $p }}" {{ request('produk')==$p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / no HP / alamat…" style="flex:1;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px 12px;font-size:13px;">
            <button type="submit" style="padding:8px 16px;background:#fbbf24;color:#0f172a;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Cari</button>
            @if(request()->hasAny(['status','produk','search']))
            <a href="{{ route('pipeline.list') }}" style="padding:8px 12px;background:#1e293b;color:#94a3b8;border-radius:8px;text-decoration:none;font-size:13px;border:1px solid #334155;">Reset</a>
            @endif
        </div>
    </form>

    {{-- Lead Cards --}}
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
        @forelse($leads as $lead)
        @php $color = $colors[$lead->status]; @endphp
        <div style="background:#1e293b;border-radius:14px;border:1px solid {{ $lead->is_aging ? '#ef444466' : '#334155' }};overflow:hidden;">
            <div style="display:flex;align-items:stretch;">
                {{-- Status Stripe --}}
                <div style="width:4px;background:{{ $color }};flex-shrink:0;"></div>
                {{-- Content --}}
                <div style="flex:1;padding:14px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
                                <span style="font-size:14px;font-weight:700;color:#f1f5f9;">{{ $lead->nama_customer }}</span>
                                @if($lead->is_aging)
                                <span style="background:#ef444415;border:1px solid #ef444444;color:#ef4444;font-size:9px;font-weight:700;padding:1px 7px;border-radius:20px;">🔴 {{ $lead->aging }}h lama</span>
                                @endif
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-bottom:8px;">📱 {{ $lead->no_hp }}@if($lead->alamat) &nbsp;·&nbsp; 📍 {{ Str::limit($lead->alamat,40) }}@endif</div>
                            <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                <span style="background:#0f172a;color:#fbbf24;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;border:1px solid #334155;">{{ strtoupper($lead->produk) }}</span>
                                <span style="background:#0f172a;color:#64748b;font-size:10px;padding:2px 9px;border-radius:20px;border:1px solid #334155;">{{ $lead->sumber_lead }}</span>
                                <span style="background:#0f172a;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;border:1px solid {{ $color }}44;color:{{ $color }};">{{ $statusList[$lead->status] }}</span>
                                @if($lead->estimasi_nilai)
                                <span style="background:#0f172a;color:#22c55e;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;border:1px solid #22c55e44;">Rp {{ number_format($lead->estimasi_nilai,0,',','.') }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;">
                            <a href="{{ route('pipeline.show', $lead) }}" style="padding:7px 14px;background:#fbbf24;color:#0f172a;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;">Detail</a>
                            <span style="font-size:10px;color:#475569;">{{ ($lead->last_activity_at ?? $lead->updated_at)->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:48px 0;color:#475569;">
            <div style="font-size:32px;margin-bottom:10px;">📭</div>
            <div style="font-size:14px;font-weight:600;">Belum ada lead</div>
            <div style="font-size:12px;margin-top:4px;">Coba ubah filter atau tambah lead baru</div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($leads->hasPages())
    <div style="display:flex;justify-content:center;gap:6px;flex-wrap:wrap;">
        @if($leads->onFirstPage())
        <span style="padding:6px 12px;background:#1e293b;color:#334155;border-radius:8px;font-size:12px;">‹ Prev</span>
        @else
        <a href="{{ $leads->previousPageUrl() }}" style="padding:6px 12px;background:#1e293b;color:#94a3b8;border-radius:8px;font-size:12px;text-decoration:none;">‹ Prev</a>
        @endif

        <span style="padding:6px 12px;background:#fbbf24;color:#0f172a;border-radius:8px;font-size:12px;font-weight:700;">{{ $leads->currentPage() }} / {{ $leads->lastPage() }}</span>

        @if($leads->hasMorePages())
        <a href="{{ $leads->nextPageUrl() }}" style="padding:6px 12px;background:#1e293b;color:#94a3b8;border-radius:8px;font-size:12px;text-decoration:none;">Next ›</a>
        @else
        <span style="padding:6px 12px;background:#1e293b;color:#334155;border-radius:8px;font-size:12px;">Next ›</span>
        @endif
    </div>
    @endif

</div>
@endsection
