@extends('layouts.app')

@section('page-title', 'Dashboard Supervisor')

@section('sidebar-menu')
    <div class="nav-section">Utama</div>
    <a href="{{ route('supervisor.dashboard') }}" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span x-show="sidebarOpen">Dashboard</span>
    </a>

    <div class="nav-section">Lapangan</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
        </svg>
        <span x-show="sidebarOpen">Survey Mobile</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75"/>
        </svg>
        <span x-show="sidebarOpen">Tugas Tim</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
        </svg>
        <span x-show="sidebarOpen">Manufacture</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
        </svg>
        <span x-show="sidebarOpen">Inventaris Alat</span>
    </a>

    <div class="nav-section">Pribadi</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
        </svg>
        <span x-show="sidebarOpen">Absensi</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
        </svg>
        <span x-show="sidebarOpen">Gaji Saya</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $stats = [
            ['label' => 'Project Aktif', 'value' => '0', 'icon' => '🏗️', 'color' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.1)'],
            ['label' => 'Survey Hari Ini', 'value' => '0', 'icon' => '📍', 'color' => '#C9A84C', 'bg' => 'rgba(201,168,76,0.1)'],
            ['label' => 'Teknisi Aktif', 'value' => '0', 'icon' => '👷', 'color' => '#10B981', 'bg' => 'rgba(16,185,129,0.1)'],
            ['label' => 'Alat Tersedia', 'value' => '0', 'icon' => '🔧', 'color' => '#8B5CF6', 'bg' => 'rgba(139,92,246,0.1)'],
        ];
        @endphp
        @foreach($stats as $stat)
        <div class="stat-card">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg mb-3" style="background: {{ $stat['bg'] }}">{{ $stat['icon'] }}</div>
            <div class="text-2xl font-bold mb-1" style="color: {{ $stat['color'] }}">{{ $stat['value'] }}</div>
            <div class="text-xs text-slate-500">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Project Board --}}
    <div class="stat-card">
        <h3 class="font-700 text-sm mb-4" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
            🏗️ Production Board Hari Ini
        </h3>
        <div class="flex items-center justify-center h-32 text-slate-400 text-sm">
            Belum ada project aktif
        </div>
    </div>

    {{-- Rekomendasi Tugas --}}
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-700 text-sm" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
                📋 Rekomendasi Tugas Besok
            </h3>
            <span class="text-xs px-2.5 py-1 rounded-full" style="background: rgba(201,168,76,0.1); color: var(--gold)">
                Dikirim 20:00
            </span>
        </div>
        <div class="flex items-center justify-center h-24 text-slate-400 text-sm">
            Rekomendasi akan muncul malam hari
        </div>
    </div>

</div>
@endsection
