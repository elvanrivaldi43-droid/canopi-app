{{-- FILE: resources/views/absensi/kode-hari-ini.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Kode Absen Hari Ini')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@section('content')
<div style="max-width:700px;margin:0 auto;">

    <div class="stat-card" style="padding:16px;margin-bottom:16px;">
        <div style="font-size:13px;color:#94a3b8;">{{ $tanggal->translatedFormat('l, d F Y') }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:4px;">Kode ini cuma bisa dipakai oleh karyawan yang bersangkutan. Yang belum "Sudah terhubung" perlu dikasih tahu manual.</div>
    </div>

    <div class="stat-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#0f172a;">
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Nama</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Kode</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Telegram</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $d)
                <tr style="border-top:1px solid #1e293b;">
                    <td style="padding:10px 14px;">
                        {{ $d['nama'] }}
                        <div style="font-size:11px;color:#64748b;">{{ $d['jabatan'] }}</div>
                    </td>
                    <td style="padding:10px 14px;font-family:monospace;font-weight:700;letter-spacing:1px;">
                        {{ $d['kode'] ?? '—' }}
                    </td>
                    <td style="padding:10px 14px;">
                        @if($d['connected'])
                            <span style="display:inline-flex;align-items:center;gap:4px;color:#34d399;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                Sudah terhubung
                            </span>
                        @else
                            <span style="display:inline-flex;align-items:center;gap:4px;color:#f87171;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                Belum connect
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
