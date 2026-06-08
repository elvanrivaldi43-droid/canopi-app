@extends('layouts.app')
@section('page-title', $lead->nama_customer)

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
@php $color = $colors[$lead->status]; @endphp
<div style="max-width:680px;margin:0 auto;">

    {{-- Back + Actions --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <a href="{{ route('pipeline.index') }}" style="display:inline-flex;align-items:center;gap:6px;color:#94a3b8;text-decoration:none;font-size:13px;font-weight:600;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Kanban
        </a>
        <a href="{{ route('pipeline.edit', $lead) }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#1e293b;color:#e2e8f0;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;border:1px solid #334155;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
            Edit
        </a>
    </div>

    {{-- Lead Header --}}
    <div style="background:#1e293b;border-radius:16px;padding:20px;margin-bottom:14px;border:1px solid #334155;border-top:3px solid {{ $color }};">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div style="font-size:22px;font-weight:800;color:#f1f5f9;margin-bottom:6px;">{{ $lead->nama_customer }}</div>
                <div style="font-size:13px;color:#94a3b8;margin-bottom:10px;">📱 {{ $lead->no_hp }}</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <span style="background:#0f172a;color:#fbbf24;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #334155;">{{ strtoupper($lead->produk) }}</span>
                    <span style="background:#0f172a;color:#64748b;font-size:11px;padding:3px 10px;border-radius:20px;border:1px solid #334155;">{{ $lead->sumber_lead }}</span>
                    @if($lead->is_aging)
                    <span style="background:#ef444415;border:1px solid #ef444444;color:#ef4444;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">🔴 {{ $lead->aging }} hari tidak diupdate</span>
                    @endif
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:#64748b;margin-bottom:4px;">Estimasi Nilai</div>
                <div style="font-size:20px;font-weight:800;color:#22c55e;">
                    {{ $lead->estimasi_nilai ? 'Rp '.number_format($lead->estimasi_nilai,0,',','.') : '—' }}
                </div>
            </div>
        </div>

        @if($lead->alamat)
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #334155;font-size:12px;color:#94a3b8;">
            📍 {{ $lead->alamat }}
        </div>
        @endif

        @if($lead->catatan)
        <div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:8px;font-size:12px;color:#94a3b8;border-left:2px solid {{ $color }};">
            {{ $lead->catatan }}
        </div>
        @endif

        <div style="margin-top:12px;font-size:11px;color:#475569;">
            Dibuat oleh {{ $lead->user->name ?? '—' }} · {{ $lead->created_at->format('d M Y H:i') }}
        </div>
    </div>

    {{-- Status Update --}}
    <div style="background:#1e293b;border-radius:16px;padding:16px;margin-bottom:14px;border:1px solid #334155;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;">Status Sekarang</div>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;background:#0f172a;border-radius:12px;border:1px solid {{ $color }}44;">
                <div style="width:10px;height:10px;border-radius:50%;background:{{ $color }};box-shadow:0 0 8px {{ $color }}88;"></div>
                <span style="font-size:14px;font-weight:700;color:{{ $color }};">{{ $statusList[$lead->status] }}</span>
            </div>
            @if($lead->status === 'dijadwalkan' && $lead->tanggal_jadwal)
            <div style="font-size:12px;color:#a78bfa;background:#0f172a;padding:8px 14px;border-radius:10px;border:1px solid #8b5cf644;">
                📅 {{ $lead->tanggal_jadwal->format('d M Y') }}
                @if($lead->jam_jadwal) · {{ substr($lead->jam_jadwal,0,5) }} WIB @endif
            </div>
            @endif
        </div>

        <form method="POST" action="{{ route('pipeline.update-status', $lead) }}">
            @csrf @method('PATCH')
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select name="status" style="flex:1;min-width:160px;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:10px;padding:10px 12px;font-size:13px;">
                    @foreach($statusList as $key => $label)
                    <option value="{{ $key }}" {{ $lead->status == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" style="padding:10px 18px;background:#fbbf24;color:#0f172a;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">Update Status</button>
            </div>
        </form>
    </div>

    {{-- Follow-up Form --}}
    <div style="background:#1e293b;border-radius:16px;padding:16px;margin-bottom:14px;border:1px solid #334155;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;">Catat Follow-Up</div>
        <form method="POST" action="{{ route('pipeline.followup', $lead) }}">
            @csrf
            @error('catatan')<div style="color:#ef4444;font-size:12px;margin-bottom:8px;">{{ $message }}</div>@enderror
            <textarea name="catatan" rows="3" placeholder="Tulis catatan interaksi, hasil panggilan, atau update terbaru…" style="width:100%;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:10px;padding:10px 12px;font-size:13px;resize:vertical;box-sizing:border-box;">{{ old('catatan') }}</textarea>
            <button type="submit" style="margin-top:8px;width:100%;padding:10px;background:#fbbf24;color:#0f172a;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
                Simpan Follow-Up
            </button>
        </form>
    </div>

    {{-- Follow-up Timeline --}}
    <div style="background:#1e293b;border-radius:16px;padding:16px;margin-bottom:14px;border:1px solid #334155;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:16px;">
            Riwayat Interaksi ({{ $lead->followups->count() }})
        </div>

        @forelse($lead->followups as $fu)
        <div style="display:flex;gap:12px;margin-bottom:16px;">
            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#fbbf24,#c9a84c);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#0f172a;flex-shrink:0;">
                    {{ strtoupper(substr($fu->user->name ?? 'U', 0, 1)) }}
                </div>
                @if(!$loop->last)
                <div style="width:1px;flex:1;background:#334155;margin-top:4px;min-height:16px;"></div>
                @endif
            </div>
            <div style="flex:1;min-width:0;padding-bottom:4px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    <span style="font-size:12px;font-weight:700;color:#e2e8f0;">{{ $fu->user->name ?? '—' }}</span>
                    <span style="font-size:10px;color:#475569;">{{ $fu->created_at->format('d M Y · H:i') }}</span>
                </div>
                @if($fu->status_sebelum && $fu->status_sesudah)
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;flex-wrap:wrap;">
                    <span style="font-size:10px;background:#0f172a;color:{{ $colors[$fu->status_sebelum] ?? '#64748b' }};padding:1px 8px;border-radius:20px;border:1px solid {{ $colors[$fu->status_sebelum] ?? '#64748b' }}44;">{{ $statusList[$fu->status_sebelum] ?? $fu->status_sebelum }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;color:#64748b;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    <span style="font-size:10px;background:#0f172a;color:{{ $colors[$fu->status_sesudah] ?? '#fbbf24' }};padding:1px 8px;border-radius:20px;border:1px solid {{ $colors[$fu->status_sesudah] ?? '#fbbf24' }}44;font-weight:700;">{{ $statusList[$fu->status_sesudah] ?? $fu->status_sesudah }}</span>
                </div>
                @endif
                <div style="font-size:12px;color:#94a3b8;line-height:1.5;background:#0f172a;padding:8px 12px;border-radius:8px;border-left:2px solid #334155;">
                    {{ $fu->catatan }}
                </div>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:20px 0;color:#475569;font-size:12px;">Belum ada riwayat interaksi</div>
        @endforelse
    </div>

</div>
@endsection
