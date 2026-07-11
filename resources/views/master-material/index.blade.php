@extends('layouts.app')

@section('content')
<div style="padding: 16px; max-width: 1200px; margin: 0 auto;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="color:#fbbf24; font-size:20px; font-weight:700; margin:0;">Master Material</h1>
            <p style="color:#94a3b8; font-size:13px; margin:4px 0 0;">Harga pokok bahan untuk RAB</p>
        </div>
        <a href="{{ route('master-material.create') }}"
           style="background:#fbbf24; color:#0f172a; padding:10px 18px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Tambah Material
        </a>
    </div>

    {{-- Alert --}}
    @if(session('success'))
    <div style="background:#064e3b; border:1px solid #10b981; color:#6ee7b7; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">
        {{ session('success') }}
    </div>
    @endif

    {{-- Stats per Kategori --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; margin-bottom:20px;">
        @foreach($kategoriList as $key => $label)
        <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:10px; text-align:center;">
            <div style="color:#fbbf24; font-size:18px; font-weight:700;">{{ $stats[$key] ?? 0 }}</div>
            <div style="color:#94a3b8; font-size:11px; margin-top:2px;">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nama material..."
               style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; flex:1; min-width:200px; outline:none;">
        <select name="kategori"
                style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; outline:none;">
            <option value="">Semua Kategori</option>
            @foreach($kategoriList as $k => $l)
            <option value="{{ $k }}" {{ request('kategori')==$k?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
        <select name="aktif"
                style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; outline:none;">
            <option value="">Semua Status</option>
            <option value="1" {{ request('aktif')==='1'?'selected':'' }}>Aktif</option>
            <option value="0" {{ request('aktif')==='0'?'selected':'' }}>Nonaktif</option>
        </select>
        <select name="sumber"
                style="background:#1e293b; border:1px solid #334155; color:#e2e8f0; padding:9px 14px; border-radius:8px; font-size:14px; outline:none;">
            <option value="">Semua Sumber</option>
            <option value="pos"  {{ request('sumber')==='pos'?'selected':'' }}>Pusat Besi (POS)</option>
            <option value="luar" {{ request('sumber')==='luar'?'selected':'' }}>Beli Luar</option>
        </select>
        <button type="submit"
                style="background:#3b82f6; color:#fff; padding:9px 18px; border-radius:8px; border:none; font-weight:600; font-size:14px; cursor:pointer;">
            Cari
        </button>
        @if(request()->hasAny(['search','kategori','aktif']))
        <a href="{{ route('master-material.index') }}"
           style="background:#334155; color:#94a3b8; padding:9px 14px; border-radius:8px; font-size:14px; text-decoration:none;">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#0f172a; border-bottom:1px solid #334155;">
                        <th style="padding:12px 16px; text-align:left; color:#94a3b8; font-weight:600; white-space:nowrap;">Nama Material</th>
                        <th style="padding:12px 16px; text-align:left; color:#94a3b8; font-weight:600;">Kategori</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Satuan</th>
                        <th style="padding:12px 16px; text-align:right; color:#94a3b8; font-weight:600;">Harga Pokok</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Sumber</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Status</th>
                        <th style="padding:12px 16px; text-align:center; color:#94a3b8; font-weight:600;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materials as $m)
                    <tr style="border-bottom:1px solid #334155; {{ $m->aktif ? '' : 'opacity:0.5;' }}">
                        <td style="padding:12px 16px; color:#e2e8f0; font-weight:500;">
                            {{ $m->nama }}
                            @if($m->kode)
                            <span style="color:#64748b; font-size:11px; margin-left:6px;">[{{ $m->kode }}]</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="background:#334155; color:#94a3b8; padding:3px 10px; border-radius:20px; font-size:12px;">
                                {{ $m->kategori_label }}
                            </span>
                        </td>
                        <td style="padding:12px 16px; text-align:center; color:#94a3b8;">{{ $m->satuan }}</td>
                        <td style="padding:12px 16px; text-align:right; color:#fbbf24; font-weight:600;">
                            Rp {{ number_format($m->harga_pokok, 0, ',', '.') }}
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            @php $sm = $m->sumber ?? 'luar'; @endphp
                            <span style="background:{{ $sm==='pos' ? '#052e1a' : '#3a2a08' }}; color:{{ $sm==='pos' ? '#6ee7b7' : '#fbbf24' }}; padding:3px 10px; border-radius:20px; font-size:12px;">
                                {{ $sm==='pos' ? 'POS' : 'Luar' }}
                            </span>
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            <span style="background:{{ $m->aktif ? '#064e3b' : '#3f1515' }}; color:{{ $m->aktif ? '#6ee7b7' : '#fca5a5' }}; padding:3px 10px; border-radius:20px; font-size:12px;">
                                {{ $m->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                                <a href="{{ route('master-material.edit', $m) }}"
                                   style="background:#1d4ed8; color:#fff; padding:5px 12px; border-radius:6px; font-size:12px; text-decoration:none;">Edit</a>
                                <form method="POST" action="{{ route('master-material.toggle', $m) }}" style="display:inline;">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            style="background:{{ $m->aktif ? '#7c2d12' : '#064e3b' }}; color:{{ $m->aktif ? '#fca5a5' : '#6ee7b7' }}; padding:5px 12px; border-radius:6px; font-size:12px; border:none; cursor:pointer;">
                                        {{ $m->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding:40px; text-align:center; color:#64748b;">
                            Tidak ada material ditemukan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($materials->hasPages())
        <div style="padding:16px; border-top:1px solid #334155;">
            {{ $materials->links() }}
        </div>
        @endif
    </div>

</div>
@endsection