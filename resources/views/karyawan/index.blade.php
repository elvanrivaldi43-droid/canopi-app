@extends('layouts.app')

@section('page-title', 'Karyawan')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:18px;font-weight:700;margin:0;" :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">Data Karyawan</h2>
            <p style="font-size:12px;color:#94A3B8;margin:4px 0 0 0;">Total {{ $karyawan->total() }} karyawan terdaftar</p>
        </div>
        @if(auth()->user()->level == 1 || auth()->user()->level == 2)
        <a href="{{ route('karyawan.create') }}"
           style="display:flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Karyawan
        </a>
        @endif
    </div>

    {{-- Filter & Search --}}
    <form method="GET" action="{{ route('karyawan.index') }}">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="🔍 Cari nama..."
                   style="flex:1;min-width:160px;padding:10px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                   :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">

            <select name="level"
                    style="padding:10px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;cursor:pointer;"
                    :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                <option value="">Semua Level</option>
                @foreach([1=>'Owner',2=>'Admin Ops',3=>'Supervisor',4=>'Marketing',5=>'Teknisi',6=>'Driver',7=>'Admin Toko'] as $l => $n)
                <option value="{{ $l }}" {{ request('level') == $l ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <select name="status"
                    style="padding:10px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;cursor:pointer;"
                    :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                <option value="">Semua Status</option>
                <option value="aktif" {{ request('status') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="sp1" {{ request('status') == 'sp1' ? 'selected' : '' }}>SP 1</option>
                <option value="sp2" {{ request('status') == 'sp2' ? 'selected' : '' }}>SP 2</option>
                <option value="sp3" {{ request('status') == 'sp3' ? 'selected' : '' }}>SP 3</option>
                <option value="nonaktif" {{ request('status') == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>

            <button type="submit" style="padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);">
                Filter
            </button>
        </div>
    </form>

    {{-- Daftar Karyawan --}}
    <div style="display:flex;flex-direction:column;gap:10px;">
        @forelse($karyawan as $k)
        <a href="{{ route('karyawan.show', $k) }}" style="text-decoration:none;">
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:14px 16px;cursor:pointer;transition:all 0.2s;"
                 onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">

                {{-- Avatar --}}
                <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0;color:white;background:linear-gradient(135deg,#C9A84C,#A8872E);">
                    @if($k->foto)
                        <img src="{{ Storage::url($k->foto) }}" style="width:44px;height:44px;border-radius:12px;object-fit:cover;">
                    @else
                        {{ strtoupper(substr($k->name, 0, 1)) }}
                    @endif
                </div>

                {{-- Info --}}
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:600;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                         :style="darkMode ? 'color:#F1F5F9' : 'color:#1E293B'">
                        {{ $k->name }}
                    </div>
                    <div style="font-size:12px;color:#94A3B8;">{{ $k->jabatan ?? $levels[$k->level] }}</div>
                </div>

                {{-- Status & Level --}}
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:{{ $k->warnStatus() }}20;color:{{ $k->warnStatus() }};border:1px solid {{ $k->warnStatus() }}40;">
                        {{ $k->labelStatus() }}
                    </span>
                    <span style="font-size:10px;color:#64748B;">{{ $levels[$k->level] ?? '' }}</span>
                </div>

                {{-- Arrow --}}
                <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;color:#64748B;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </div>
        </a>
        @empty
        <div class="stat-card" style="text-align:center;padding:40px;">
            <div style="font-size:32px;margin-bottom:12px;">👥</div>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px;" :style="darkMode ? 'color:#94A3B8' : 'color:#64748B'">Belum ada karyawan</div>
            <div style="font-size:12px;color:#94A3B8;">Tambah karyawan pertama dengan tombol di atas</div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($karyawan->hasPages())
    <div style="display:flex;justify-content:center;">
        {{ $karyawan->links() }}
    </div>
    @endif

</div>
@endsection