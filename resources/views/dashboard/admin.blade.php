@extends('layouts.app')

@section('page-title', 'Dashboard Admin')

@section('sidebar-menu')
    <div class="nav-section">Utama</div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span x-show="sidebarOpen">Dashboard</span>
    </a>

    <div class="nav-section">Sales</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
        </svg>
        <span x-show="sidebarOpen">Pipeline Survey</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
        <span x-show="sidebarOpen">Quotation</span>
    </a>

    <div class="nav-section">Project</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/>
        </svg>
        <span x-show="sidebarOpen">Project Aktif</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
        </svg>
        <span x-show="sidebarOpen">Pembayaran</span>
    </a>

    <div class="nav-section">SDM</div>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
        </svg>
        <span x-show="sidebarOpen">Slip Gaji</span>
    </a>
    <a href="#" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
        </svg>
        <span x-show="sidebarOpen">Kasbon</span>
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $stats = [
            ['label' => 'Leads Aktif', 'value' => '0', 'icon' => '📊', 'color' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.1)'],
            ['label' => 'Project Berjalan', 'value' => '0', 'icon' => '🏗️', 'color' => '#10B981', 'bg' => 'rgba(16,185,129,0.1)'],
            ['label' => 'Pembayaran Pending', 'value' => '0', 'icon' => '💳', 'color' => '#F59E0B', 'bg' => 'rgba(245,158,11,0.1)'],
            ['label' => 'Quotation Aktif', 'value' => '0', 'icon' => '📝', 'color' => '#C9A84C', 'bg' => 'rgba(201,168,76,0.1)'],
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

    {{-- Pipeline & Pembayaran --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="stat-card">
            <h3 class="font-700 text-sm mb-4" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">Pipeline Survey</h3>
            @php
            $pipeline = ['Lead','Dihubungi','Dijadwalkan','Dikunjungi','Ditawar','Deal','Tidak Jadi'];
            $colors = ['#64748B','#3B82F6','#8B5CF6','#F59E0B','#EC4899','#10B981','#EF4444'];
            @endphp
            <div class="space-y-2">
                @foreach($pipeline as $i => $status)
                <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-white/5 last:border-0">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full" style="background: {{ $colors[$i] }}"></div>
                        <span class="text-xs" :class="darkMode ? 'text-slate-400' : 'text-slate-600'">{{ $status }}</span>
                    </div>
                    <span class="text-xs font-700" style="color: {{ $colors[$i] }}">0</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="stat-card">
            <h3 class="font-700 text-sm mb-4" :class="darkMode ? 'text-slate-200' : 'text-slate-700'">Pembayaran Pending</h3>
            <div class="flex items-center justify-center h-32 text-slate-400 text-sm">
                Belum ada pembayaran pending
            </div>
        </div>
    </div>

</div>
@endsection
