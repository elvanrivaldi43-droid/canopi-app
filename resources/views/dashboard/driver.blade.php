@extends('layouts.app')

@section('page-title', 'Dashboard Driver')

@section('sidebar-menu')
    <div class="nav-section">Utama</div>
    <a href="{{ route('driver.dashboard') }}" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span x-show="sidebarOpen">Dashboard</span>
    </a>

    <div class="nav-section">Operasional</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
        </svg>
        <span x-show="sidebarOpen">Log Bensin</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75"/>
        </svg>
        <span x-show="sidebarOpen">Checklist Kendaraan</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
        </svg>
        <span x-show="sidebarOpen">Absen</span>
    </a>

    <div class="nav-section">Pribadi</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
        </svg>
        <span x-show="sidebarOpen">Gaji Saya</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
        </svg>
        <span x-show="sidebarOpen">Kasbon Saya</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Perjalanan Hari Ini --}}
    <div class="rounded-2xl p-6" style="background: linear-gradient(135deg, #0F1117 0%, #1E2535 100%); border: 1px solid rgba(201,168,76,0.2)">
        <p class="text-xs font-600 mb-1" style="color: var(--gold)">🚗 Perjalanan Hari Ini</p>
        <h2 class="text-xl font-bold text-white mb-1">Belum ada jadwal</h2>
        <p class="text-slate-400 text-sm">Mandor akan assign perjalanan melalui sistem</p>
    </div>

    {{-- Checklist Kendaraan --}}
    <div class="stat-card">
        <h3 class="font-700 text-sm mb-4" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
            🚙 Checklist Kendaraan Hari Ini
        </h3>
        @php
        $checklist = ['Ban (kondisi & tekanan)','Kekencangan baud roda','Oli mesin','Air radiator','Lampu','Rem','BBM','Kebersihan'];
        @endphp
        <div class="grid grid-cols-2 gap-2">
            @foreach($checklist as $item)
            <div class="flex items-center gap-2 p-2 rounded-lg" :class="darkMode ? 'bg-white/5' : 'bg-slate-50'">
                <div class="w-4 h-4 rounded border-2 flex-shrink-0" style="border-color: var(--gold)"></div>
                <span class="text-xs" :class="darkMode ? 'text-slate-400' : 'text-slate-600'">{{ $item }}</span>
            </div>
            @endforeach
        </div>
        <div class="mt-4">
            <button class="w-full py-2.5 rounded-xl text-sm font-600 transition-colors"
                    style="background: var(--gold); color: white">
                Mulai Checklist
            </button>
        </div>
    </div>

    {{-- Log Bensin --}}
    <div class="stat-card">
        <h3 class="font-700 text-sm mb-3" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
            ⛽ Log Bensin Bulan Ini
        </h3>
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-slate-500">Total pengeluaran</span>
            <span class="font-bold" style="color: var(--gold)">Rp 0</span>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Rata-rata konsumsi</span>
            <span class="font-bold text-slate-400">- km/liter</span>
        </div>
    </div>

</div>
@endsection
