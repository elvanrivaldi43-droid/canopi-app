@extends('layouts.app')
@section('title', 'Daftar RAB')
@section('content')
<div style="padding:16px; max-width:1100px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="color:#fbbf24; font-size:20px; font-weight:700; margin:0;">Daftar RAB</h1>
            <p style="color:#94a3b8; font-size:13px; margin:4px 0 0;">Rencana Anggaran Biaya & penawaran</p>
        </div>
        <a href="{{ route('rab.create') }}"
           style="background:#fbbf24; color:#0f172a; padding:10px 18px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Buat RAB Baru
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nama customer..."
               style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; flex:1; min-width:180px; outline:none;">
        <select name="status"
                style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; outline:none;">
            <option value="">Semua Status</option>
            <option value="draft" {{ request('status')=='draft'?'selected':'' }}>Draft</option>
            <option value="deal" {{ request('status')=='deal'?'selected':'' }}>Deal</option>
        </select>
        <button type="submit"
                style="background:#3b82f6; color:#fff; padding:9px 18px; border-radius:8px; border:none; font-weight:600; font-size:14px; cursor:pointer;">Cari</button>
        @if(request()->hasAny(['search','status']))
        <a href="{{ route('rab.index') }}"
           style="background:#334155; color:#94a3b8; padding:9px 14px; border-radius:8px; font-size:14px; text-decoration:none;">Reset</a>
        @endif
    </form>

    {{-- Daftar --}}
    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px; min-width:680px;">
                <thead>
                    <tr style="background:#0f172a; border-bottom:1px solid #334155;">
                        <th style="padding:12px 16px; text-align:left; color:#94a3b8; font-weight:600; white-space:nowrap;">No. RAB</th>
                        <th style="padding:12px 16px; text-align:left; color:#94a3b8; font-weight:600;">Customer</th>
                        <th style="padding:12px 16px; text-align:left; color:#94a3b8; font-weight:600;">Produk</th>
                        <th style="padding:12px 16px; text-align:right; color:#94a3b8; font-weight:600;">Harga Final</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Status</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Tgl</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rabs as $r)
                    @php
                        $st = $r->status ?? 'draft';
                        $stColor = $st==='deal' ? ['#052e1a','#6ee7b7'] : ($st==='pending_approval' ? ['#3a2a08','#fbbf24'] : ['#1e293b','#94a3b8']);
                    @endphp
                    <tr style="border-bottom:1px solid #334155;">
                        <td style="padding:12px 16px; color:#e2e8f0; font-weight:600; white-space:nowrap;">{{ $r->nomor_rab ?? ('#'.$r->id) }}</td>
                        <td style="padding:12px 16px; color:#e2e8f0;">{{ optional($r->lead)->nama_customer ?? '—' }}</td>
                        <td style="padding:12px 16px; color:#94a3b8;">{{ $r->produk_kode ?? '—' }}</td>
                        <td style="padding:12px 16px; text-align:right; color:#fbbf24; font-weight:600; white-space:nowrap;">
                            Rp {{ number_format($r->harga_final ?? 0, 0, ',', '.') }}
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            <span style="background:{{ $stColor[0] }}; color:{{ $stColor[1] }}; padding:3px 10px; border-radius:20px; font-size:12px; white-space:nowrap;">
                                {{ ucfirst(str_replace('_',' ', $st)) }}
                            </span>
                        </td>
                        <td style="padding:12px 16px; text-align:center; color:#64748b; white-space:nowrap; font-size:12px;">
                            {{ optional($r->created_at)->format('d/m/y') ?? '—' }}
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            <a href="{{ route('rab.show', $r->id) }}"
                               style="background:#1d4ed8; color:#fff; padding:5px 14px; border-radius:6px; font-size:12px; text-decoration:none;">Lihat</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding:40px; text-align:center; color:#64748b;">
                            Belum ada RAB.<br>
                            <a href="{{ route('rab.create') }}" style="color:#fbbf24; text-decoration:none;">Buat RAB pertama →</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rabs->hasPages())
        <div style="padding:14px 16px; border-top:1px solid #334155; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            @if($rabs->previousPageUrl())
                <a href="{{ $rabs->previousPageUrl() }}" style="background:#334155; color:#e2e8f0; padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none;">← Sebelumnya</a>
            @else
                <span></span>
            @endif
            <span style="color:#64748b; font-size:13px;">Hal {{ $rabs->currentPage() }} / {{ $rabs->lastPage() }}</span>
            @if($rabs->nextPageUrl())
                <a href="{{ $rabs->nextPageUrl() }}" style="background:#334155; color:#e2e8f0; padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none;">Berikutnya →</a>
            @else
                <span></span>
            @endif
        </div>
        @endif
    </div>

</div>
@endsection