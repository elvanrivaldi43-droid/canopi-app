@extends('layouts.app')

@section('page-title', 'Dashboard Admin Toko Besi')

@section('sidebar-menu')
    <div class="nav-section">Utama</div>
    <a href="{{ route('toko.dashboard') }}" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span x-show="sidebarOpen">Dashboard</span>
    </a>

    <div class="nav-section">Toko Besi</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
        </svg>
        <span x-show="sidebarOpen">PO dari Pusat Kanopi</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>
        <span x-show="sidebarOpen">Sync Olsera</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
        $stats = [
            ['label' => 'PO Pending', 'value' => '0', 'icon' => '📋', 'color' => '#F59E0B', 'bg' => 'rgba(245,158,11,0.1)'],
            ['label' => 'PO Diproses', 'value' => '0', 'icon' => '✅', 'color' => '#10B981', 'bg' => 'rgba(16,185,129,0.1)'],
            ['label' => 'Status Sync', 'value' => 'Manual', 'icon' => '🔄', 'color' => '#C9A84C', 'bg' => 'rgba(201,168,76,0.1)'],
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

    {{-- PO List --}}
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-700 text-sm" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">
                📋 Purchase Order dari Pusat Kanopi
            </h3>
        </div>
        <div class="flex items-center justify-center h-32 text-slate-400 text-sm">
            Belum ada PO masuk
        </div>
    </div>

    {{-- Info Olsera --}}
    <div class="rounded-2xl p-5 border" style="border-color: rgba(201,168,76,0.2); background: rgba(201,168,76,0.05)">
        <div class="flex items-center gap-3 mb-2">
            <span class="text-xl">⚠️</span>
            <h3 class="font-700 text-sm" style="color: var(--gold)">Integrasi Olsera</h3>
        </div>
        <p class="text-xs leading-relaxed text-slate-400">
            Integrasi dengan Olsera API belum aktif. Saat ini input dilakukan manual.
            Hubungi owner untuk setup API key Olsera.
        </p>
    </div>

</div>
@endsection
