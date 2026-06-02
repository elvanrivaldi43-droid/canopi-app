<!DOCTYPE html>
<html lang="id" class="h-full"
      x-data="{
          darkMode: localStorage.getItem('darkMode') === 'true',
          sidebarOpen: true,
          mobileMenu: false
      }"
      x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))"
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{{ $title ?? 'Pusat Kanopi' }} — CanopiBSD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .topbar h1 {
    font-size: 11px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}
        :root {
            --gold: #C9A84C;
            --gold-light: #E8C96D;
            --gold-dark: #A8872E;
            --gold-bg: #FDF8EC;
            --dark: #0F1117;
            --dark2: #161B27;
            --dark3: #1E2535;
            --sidebar-w: 260px;
            --bottom-nav-h: 64px;
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; box-sizing: border-box; }
        .font-display { font-family: 'Playfair Display', serif; }
        html, body { height: 100%; margin: 0; padding: 0; }

        /* ── LAYOUT UTAMA ──────────────────────────── */
        body {
            display: flex;
            overflow: hidden;
        }

        /* ── SIDEBAR (Desktop) ─────────────────────── */
        .sidebar {
            display: flex;
            flex-direction: column;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--dark2);
            border-right: 1px solid rgba(201,168,76,0.15);
            transition: width 0.3s ease;
            flex-shrink: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar-collapsed { width: 72px; }

        /* SEMBUNYIKAN sidebar di mobile - CSS ONLY, tidak pakai x-show */
        @media (max-width: 1023px) {
            .sidebar {
                display: none !important;
            }
        }

        /* ── MOBILE DRAWER ─────────────────────────── */
        .mobile-drawer {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
        }
        @media (max-width: 1023px) {
            .mobile-drawer { display: block; pointer-events: none; }
            .mobile-drawer.open { pointer-events: all; }
        }
        .mobile-drawer-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .mobile-drawer.open .mobile-drawer-overlay { opacity: 1; }
        .mobile-drawer-panel {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 280px;
            background: var(--dark2);
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            overflow-y: auto;
            border-right: 1px solid rgba(201,168,76,0.15);
            z-index: 1000;
        }
        .mobile-drawer.open .mobile-drawer-panel {
            transform: translateX(0);
        }

        /* ── BOTTOM NAV (Mobile) ───────────────────── */
        .bottom-nav {
            display: none;
        }
        @media (max-width: 1023px) {
            .bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0; left: 0; right: 0;
                height: var(--bottom-nav-h);
                align-items: center;
                justify-content: space-around;
                z-index: 40;
                padding-bottom: env(safe-area-inset-bottom);
                border-top: 1px solid rgba(201,168,76,0.15);
            }
        }
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 8px 12px;
            border-radius: 12px;
            color: #64748B;
            font-size: 10px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            min-width: 52px;
            -webkit-tap-highlight-color: transparent;
        }
        .bottom-nav-item.active { color: var(--gold); }
        .bottom-nav-item svg { width: 22px; height: 22px; }

        /* ── MAIN CONTENT ──────────────────────────── */
        .main-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .page-content {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 1023px) {
            .page-content {
                padding-bottom: calc(var(--bottom-nav-h) + 16px +  env(safe-area-inset-bottom));
            }
        }

        /* ── NAV ITEMS ─────────────────────────────── */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: #94A3B8;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        .nav-item:hover { background: rgba(201,168,76,0.1); color: var(--gold); }
        .nav-item.active {
            background: rgba(201,168,76,0.15);
            color: var(--gold);
            border-left: 3px solid var(--gold);
        }
        .nav-item svg { flex-shrink: 0; width: 18px; height: 18px; }
        .nav-section {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1.2px; color: #475569;
            padding: 16px 16px 6px; text-transform: uppercase;
        }

        /* ── CARDS ─────────────────────────────────── */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #F1F5F9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .dark .stat-card {
            background: var(--dark3);
            border-color: rgba(255,255,255,0.06);
        }

        /* ── TOPBAR ────────────────────────────────── */
        .topbar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #E2E8F0;
            background: white;
        }
        .dark .topbar {
            background: var(--dark2);
            border-color: rgba(255,255,255,0.06);
        }

        /* ── MISC ──────────────────────────────────── */
        .gold-text { color: var(--gold); }
        .level-badge {
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 20px;
            background: rgba(201,168,76,0.15); color: var(--gold);
            border: 1px solid rgba(201,168,76,0.3);
            display: inline-block;
        }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(201,168,76,0.3); border-radius: 10px; }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #059669; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .alert-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #EF4444; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #D97706; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        button, a { -webkit-tap-highlight-color: transparent; }
        input, select, textarea { font-size: 16px !important; }
    </style>
</head>
<body :class="darkMode ? 'bg-[#0F1117] text-slate-200' : 'bg-slate-50 text-slate-800'">

    {{-- ═══ SIDEBAR (Desktop only — CSS hide di mobile) ════════ --}}
    <aside class="sidebar" :class="sidebarOpen ? '' : 'sidebar-collapsed'">

        {{-- Logo --}}
        <div style="display:flex; align-items:center; gap:12px; padding:20px 16px; border-bottom:1px solid rgba(255,255,255,0.05); flex-shrink:0;">
            <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:linear-gradient(135deg,var(--gold),var(--gold-dark))">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:20px;height:20px;">
                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
                    <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
                </svg>
            </div>
            <div x-show="sidebarOpen">
                <div class="font-display" style="font-size:14px;font-weight:700;color:var(--gold)">Pusat Kanopi</div>
                <div style="font-size:11px;color:#64748B;">CanopiBSD System</div>
            </div>
        </div>

        {{-- User Info --}}
        <div style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.05);flex-shrink:0;" x-show="sidebarOpen">
            @php $levels = ['','Owner','Admin Ops','Supervisor','Marketing','Teknisi','Driver','Admin Toko']; @endphp
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:white;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div style="min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:#E2E8F0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ auth()->user()->name }}</div>
                    <span class="level-badge">{{ $levels[auth()->user()->level] ?? 'User' }}</span>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav style="flex:1;padding:16px 12px;overflow-y:auto;">
            @yield('sidebar-menu')
        </nav>

        {{-- Bottom --}}
        <div style="padding:12px;border-top:1px solid rgba(255,255,255,0.05);flex-shrink:0;">
            <a href="#" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-show="sidebarOpen">Profil Saya</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                    <span x-show="sidebarOpen">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ═══ MOBILE DRAWER ══════════════════════════════════════ --}}
    <div class="mobile-drawer" :class="mobileMenu ? 'open' : ''">
        <div class="mobile-drawer-overlay" @click="mobileMenu = false"></div>
        <div class="mobile-drawer-panel">
            {{-- Header Drawer --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 16px;border-bottom:1px solid rgba(255,255,255,0.05);">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--gold),var(--gold-dark))">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:20px;height:20px;">
                            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
                            <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-display" style="font-size:14px;font-weight:700;color:var(--gold)">Pusat Kanopi</div>
                        <div style="font-size:11px;color:#64748B;">CanopiBSD</div>
                    </div>
                </div>
                <button @click="mobileMenu = false" style="padding:8px;color:#94A3B8;background:none;border:none;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- User --}}
            <div style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.05);">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:white;">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#E2E8F0;">{{ auth()->user()->name }}</div>
                        <span class="level-badge">{{ $levels[auth()->user()->level] ?? 'User' }}</span>
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            <nav style="padding:16px 12px;">
                @yield('sidebar-menu')
            </nav>

            {{-- Bottom --}}
            <div style="padding:12px;border-top:1px solid rgba(255,255,255,0.05);">
                <a href="#" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span>Profil Saya</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══ MAIN CONTENT ═══════════════════════════════════════ --}}
    <div class="main-wrapper" :class="darkMode ? 'bg-[#0F1117]' : 'bg-slate-50'">

        {{-- Topbar --}}
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                {{-- Mobile: buka drawer --}}
                <button @click="mobileMenu = true" style="display:none;padding:8px;border-radius:8px;background:none;border:none;cursor:pointer;" :style="darkMode ? 'color:#94A3B8' : 'color:#64748B'" id="mobileMenuBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>
                {{-- Desktop: toggle sidebar --}}
                <button @click="sidebarOpen = !sidebarOpen" style="padding:8px;border-radius:8px;background:none;border:none;cursor:pointer;" :style="darkMode ? 'color:#94A3B8' : 'color:#64748B'" id="desktopMenuBtn" style="display:none;padding:8px;...>
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>

                <div>
                    <h1 style="font-size:11px !important;font-weight:700;margin:0;line-height:1.2;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">
                        @yield('page-title', 'Dashboard')
                    </h1>
                    <p style="font-size:11px;margin:0;" :style="darkMode ? 'color:#64748B' : 'color:#94A3B8'">
                        {{ now()->isoFormat('D MMM Y') }}
                    </p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;">
                {{-- Dark Mode --}}
                <button @click="darkMode = !darkMode" style="padding:8px;border-radius:8px;background:none;border:none;cursor:pointer;" :style="darkMode ? 'color:#94A3B8' : 'color:#64748B'">
                    <svg x-show="!darkMode" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                    <svg x-show="darkMode" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                </button>

                {{-- Notifikasi --}}
                <button style="padding:8px;border-radius:8px;background:none;border:none;cursor:pointer;position:relative;" :style="darkMode ? 'color:#94A3B8' : 'color:#64748B'">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--gold);"></span>
                </button>

                {{-- Avatar --}}
                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:white;cursor:pointer;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <div class="page-content">
            <div style="padding:16px;">
                @if(session('success'))
                    <div class="alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert-danger">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>

    {{-- ═══ BOTTOM NAVIGATION (Mobile) ════════════════════════ --}}
    <nav class="bottom-nav" :class="darkMode ? 'bg-[#161B27]' : 'bg-white'">
        @yield('bottom-nav')
    </nav>

    <script>
        // Tampilkan tombol yang benar sesuai ukuran layar
        function updateMenuButtons() {
            const isMobile = window.innerWidth < 1024;
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const desktopBtn = document.getElementById('desktopMenuBtn');
            if (mobileBtn && desktopBtn) {
                mobileBtn.style.display = isMobile ? 'block' : 'none';
                desktopBtn.style.display = isMobile ? 'none' : 'block';
            }
        }
        window.addEventListener('resize', updateMenuButtons);
        window.addEventListener('load', updateMenuButtons);
        document.addEventListener('DOMContentLoaded', updateMenuButtons);
    </script>
</body>
</html>
