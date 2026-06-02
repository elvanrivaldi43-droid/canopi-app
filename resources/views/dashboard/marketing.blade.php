@extends('layouts.app')

@section('page-title', 'Dashboard Marketing')

@section('sidebar-menu')
    <div class="nav-section">Utama</div>
    <a href="{{ route('marketing.dashboard') }}" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span x-show="sidebarOpen">Dashboard</span>
    </a>

    <div class="nav-section">Marketing</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
        </svg>
        <span x-show="sidebarOpen">Input Leads</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z"/>
        </svg>
        <span x-show="sidebarOpen">Artikel & Konten</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
        </svg>
        <span x-show="sidebarOpen">Laporan Leads</span>
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

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $stats = [
            ['label' => 'Leads Bulan Ini', 'value' => '0', 'icon' => '📊', 'color' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.1)'],
            ['label' => 'Closing Rate', 'value' => '0%', 'icon' => '🎯', 'color' => '#10B981', 'bg' => 'rgba(16,185,129,0.1)'],
            ['label' => 'Konten Dipost', 'value' => '0', 'icon' => '📸', 'color' => '#C9A84C', 'bg' => 'rgba(201,168,76,0.1)'],
            ['label' => 'Follower Growth', 'value' => '0', 'icon' => '📈', 'color' => '#8B5CF6', 'bg' => 'rgba(139,92,246,0.1)'],
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

    {{-- Leads per Channel --}}
    <div class="stat-card">
        <h3 class="font-700 text-sm mb-4" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
            📣 Leads per Channel Bulan Ini
        </h3>
        @php
        $channels = [
            ['name' => 'Instagram', 'count' => 0, 'color' => '#E1306C'],
            ['name' => 'TikTok', 'count' => 0, 'color' => '#000000'],
            ['name' => 'WhatsApp', 'count' => 0, 'color' => '#25D366'],
            ['name' => 'Google', 'count' => 0, 'color' => '#4285F4'],
            ['name' => 'Referensi', 'count' => 0, 'color' => '#C9A84C'],
            ['name' => 'Datang Sendiri', 'count' => 0, 'color' => '#10B981'],
        ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach($channels as $ch)
            <div class="flex items-center gap-3 p-3 rounded-xl border"
                 :class="darkMode ? 'border-white/5 bg-white/3' : 'border-slate-100 bg-slate-50'">
                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background: {{ $ch['color'] }}"></div>
                <div>
                    <div class="text-xs font-600" :class="darkMode ? 'text-slate-300' : 'text-slate-700'">{{ $ch['name'] }}</div>
                    <div class="text-lg font-bold" style="color: {{ $ch['color'] }}">{{ $ch['count'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
