@extends('layouts.app')
@section('title', 'Persetujuan Diskon')
@section('page-title', 'Persetujuan Diskon')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.ap-wrap { max-width:640px; margin:0 auto; padding:14px 12px 40px; }
.ap-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.ap-sub { font-size:12px; color:#64748b; margin:0 0 14px; }
.ap-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:12px; }
.ap-row { display:flex; justify-content:space-between; font-size:13px; color:#cbd5e1; padding:3px 0; }
.ap-row b { color:#f1f5f9; }
.badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
.b-pending { background:rgba(251,191,36,.15); color:#fbbf24; }
.b-ok { background:rgba(16,185,129,.15); color:#6ee7b7; }
.b-no { background:rgba(239,68,68,.15); color:#fca5a5; }
.ap-field { margin:10px 0; }
.ap-field input { width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; font-size:13px; min-height:44px; }
.btn { border:none; border-radius:8px; padding:12px; font-size:13px; font-weight:700; cursor:pointer; flex:1; }
.btn-ok { background:#16a34a; color:#fff; }
.btn-no { background:#7f1d1d; color:#fff; }
</style>

<div class="ap-wrap">
    <h1 class="ap-title">Persetujuan Diskon</h1>
    <p class="ap-sub">Permintaan diskon dari surveyor yang melebihi batas kewenangan. Setujui atau tolak di sini.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">✅ {{ session('success') }}</div>
    @endif

    @php $adaPending = false; @endphp
    @foreach($rows as $r)
        @if($r->status === 'pending') @php $adaPending = true; @endphp @endif
    @endforeach

    @if(count($rows) === 0)
    <div class="ap-card" style="text-align:center;color:#64748b;font-size:13px;">Belum ada permintaan approval.</div>
    @endif

    @foreach($rows as $r)
    <div class="ap-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="font-size:14px;font-weight:700;color:#fbbf24;">{{ $r->customer ?? 'Tanpa nama' }}</div>
            @if($r->status === 'pending')<span class="badge b-pending">MENUNGGU</span>
            @elseif($r->status === 'approved')<span class="badge b-ok">DISETUJUI</span>
            @else<span class="badge b-no">DITOLAK</span>@endif
        </div>

        @if($r->opsi_nama)<div class="ap-row"><span>Opsi</span><b>{{ $r->opsi_nama }}</b></div>@endif
        <div class="ap-row"><span>Harga normal</span><b>Rp {{ number_format($r->harga_normal,0,',','.') }}</b></div>
        <div class="ap-row"><span>Customer nawar</span><b style="color:#fbbf24">Rp {{ number_format($r->harga_nawar,0,',','.') }}</b></div>
        <div class="ap-row"><span>Diskon</span><b>{{ rtrim(rtrim(number_format($r->diskon_persen,1),'0'),'.') }}%</b></div>
        @if($r->pokok > 0)<div class="ap-row"><span>Untung tersisa</span><b>Rp {{ number_format($r->harga_nawar - $r->pokok,0,',','.') }}</b></div>@endif
        <div class="ap-row"><span>Diminta oleh</span><b>{{ $r->nama_peminta ?? '-' }}</b></div>
        <div class="ap-row"><span>Waktu</span><b>{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}</b></div>

        @if($r->status === 'pending')
        <form method="POST" action="{{ url('/rab-approval/'.$r->id.'/proses') }}" style="margin-top:10px;">
            @csrf
            <div class="ap-field">
                <input type="text" name="catatan_owner" placeholder="Catatan untuk surveyor (opsional)">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" name="keputusan" value="approved" class="btn btn-ok">✓ Setujui</button>
                <button type="submit" name="keputusan" value="rejected" class="btn btn-no">✗ Tolak</button>
            </div>
        </form>
        @elseif($r->catatan_owner)
        <div style="margin-top:8px;font-size:12px;color:#94a3b8;background:#0f172a;border-radius:8px;padding:8px;">Catatan: {{ $r->catatan_owner }}</div>
        @endif
    </div>
    @endforeach
</div>
@endsection