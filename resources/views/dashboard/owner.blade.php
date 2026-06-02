@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Welcome Banner --}}
    <div style="border-radius:16px;padding:20px;position:relative;overflow:hidden;background:linear-gradient(135deg,#0F1117 0%,#1E2535 100%);border:1px solid rgba(201,168,76,0.2)">
        <p style="font-size:12px;font-weight:600;color:var(--gold);margin:0 0 4px 0;">Selamat datang kembali 👋</p>
        <h2 class="font-display" style="font-size:22px;font-weight:700;color:white;margin:0 0 4px 0;">{{ auth()->user()->name }}</h2>
        <p style="font-size:12px;color:#94A3B8;margin:0;">{{ now()->isoFormat('dddd, D MMMM Y') }} · Pusat Kanopi BSD</p>
    </div>

    {{-- Stat Cards --}}
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
        @php
        $stats = [
            ['label'=>'Project Aktif','value'=>'0','icon'=>'🏗️','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)'],
            ['label'=>'Leads Bulan Ini','value'=>'0','icon'=>'📊','color'=>'#10B981','bg'=>'rgba(16,185,129,0.1)'],
            ['label'=>'Pendapatan','value'=>'Rp 0','icon'=>'💰','color'=>'#C9A84C','bg'=>'rgba(201,168,76,0.1)'],
            ['label'=>'Karyawan','value'=>'0','icon'=>'👥','color'=>'#8B5CF6','bg'=>'rgba(139,92,246,0.1)'],
        ];
        @endphp
        @foreach($stats as $stat)
        <div class="stat-card">
            <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px;background:{{ $stat['bg'] }}">{{ $stat['icon'] }}</div>
            <div style="font-size:20px;font-weight:700;margin-bottom:2px;color:{{ $stat['color'] }}">{{ $stat['value'] }}</div>
            <div style="font-size:11px;color:#94A3B8;">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Quick Access --}}
    <div>
        <p style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748B;margin:0 0 10px 0;">Akses Cepat</p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
            @php
            $qa = [
                ['label'=>'Buat RAB','icon'=>'📝','color'=>'#C9A84C'],
                ['label'=>'Pipeline','icon'=>'📈','color'=>'#3B82F6'],
                ['label'=>'Karyawan','icon'=>'👥','color'=>'#10B981'],
                ['label'=>'Penggajian','icon'=>'💵','color'=>'#8B5CF6'],
                ['label'=>'Kasbon','icon'=>'💳','color'=>'#F59E0B'],
                ['label'=>'Laporan','icon'=>'📊','color'=>'#EF4444'],
            ];
            @endphp
            @foreach($qa as $item)
            <button class="stat-card" style="text-align:center;padding:14px 8px;cursor:pointer;border:none;background:white;" :style="darkMode ? 'background:var(--dark3)' : 'background:white'">
                <div style="font-size:22px;margin-bottom:6px;">{{ $item['icon'] }}</div>
                <div style="font-size:11px;font-weight:600;color:{{ $item['color'] }}">{{ $item['label'] }}</div>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Info --}}
    <div class="stat-card" style="border:1px solid rgba(201,168,76,0.2);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <span style="font-size:18px;">🚀</span>
            <span style="font-size:13px;font-weight:700;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">Sistem Sedang Dibangun</span>
        </div>
        <p style="font-size:12px;line-height:1.6;margin:0 0 10px 0;color:#94A3B8;">
            Fondasi Laravel berhasil. Modul ditambahkan bertahap.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            @foreach(['RAB','Pipeline','Smart Work','Absensi','Gaji','KPI'] as $m)
            <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.2)">{{ $m }}</span>
            @endforeach
        </div>
    </div>

</div>
@endsection
