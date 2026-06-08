@extends('layouts.app')
@section('page-title', 'Tambah Lead')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-pipeline')
@endsection

@section('content')
<div style="max-width:600px;margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <a href="{{ route('pipeline.index') }}" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;background:#1e293b;border-radius:10px;text-decoration:none;border:1px solid #334155;flex-shrink:0;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#94a3b8" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        </a>
        <div>
            <div style="font-size:20px;font-weight:800;color:#f1f5f9;">Tambah Lead Baru</div>
            <div style="font-size:12px;color:#64748b;">Isi data prospek customer</div>
        </div>
    </div>

    <form method="POST" action="{{ route('pipeline.store') }}" x-data="{ status: '{{ old('status','lead') }}' }">
        @csrf

        {{-- Customer Info --}}
        <div style="background:#1e293b;border-radius:16px;padding:18px;margin-bottom:14px;border:1px solid #334155;">
            <div style="font-size:11px;font-weight:700;color:#fbbf24;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;">👤 Data Customer</div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Nama Customer *</label>
                <input type="text" name="nama_customer" value="{{ old('nama_customer') }}" placeholder="Nama lengkap customer" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid {{ $errors->has('nama_customer') ? '#ef4444' : '#334155' }};border-radius:10px;padding:10px 14px;font-size:14px;box-sizing:border-box;" required>
                @error('nama_customer')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">No HP / WhatsApp *</label>
                <input type="tel" name="no_hp" value="{{ old('no_hp') }}" placeholder="08xxxxxxxxxx" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid {{ $errors->has('no_hp') ? '#ef4444' : '#334155' }};border-radius:10px;padding:10px 14px;font-size:14px;box-sizing:border-box;" required>
                @error('no_hp')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Alamat</label>
                <textarea name="alamat" rows="2" placeholder="Alamat lengkap customer" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid #334155;border-radius:10px;padding:10px 14px;font-size:14px;resize:vertical;box-sizing:border-box;">{{ old('alamat') }}</textarea>
            </div>
        </div>

        {{-- Lead Info --}}
        <div style="background:#1e293b;border-radius:16px;padding:18px;margin-bottom:14px;border:1px solid #334155;">
            <div style="font-size:11px;font-weight:700;color:#fbbf24;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;">📋 Info Lead</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Produk *</label>
                    <select name="produk" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid {{ $errors->has('produk') ? '#ef4444' : '#334155' }};border-radius:10px;padding:10px 12px;font-size:14px;" required>
                        <option value="">Pilih produk</option>
                        @foreach($produkList as $p)
                        <option value="{{ $p }}" {{ old('produk')==$p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                    @error('produk')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Sumber Lead *</label>
                    <select name="sumber_lead" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid {{ $errors->has('sumber_lead') ? '#ef4444' : '#334155' }};border-radius:10px;padding:10px 12px;font-size:14px;" required>
                        <option value="">Pilih sumber</option>
                        @foreach($sumberList as $s)
                        <option value="{{ $s }}" {{ old('sumber_lead')==$s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('sumber_lead')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Estimasi Nilai (Rp)</label>
                <input type="number" name="estimasi_nilai" value="{{ old('estimasi_nilai') }}" placeholder="Contoh: 15000000" min="0" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid #334155;border-radius:10px;padding:10px 14px;font-size:14px;box-sizing:border-box;">
                @error('estimasi_nilai')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Status & Jadwal --}}
        <div style="background:#1e293b;border-radius:16px;padding:18px;margin-bottom:14px;border:1px solid #334155;">
            <div style="font-size:11px;font-weight:700;color:#fbbf24;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;">🏷️ Status</div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;">Status Lead *</label>
                <select name="status" x-model="status" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid #334155;border-radius:10px;padding:10px 12px;font-size:14px;" required>
                    @foreach($statusList as $key => $label)
                    <option value="{{ $key }}" {{ old('status','lead')==$key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Jadwal (tampil hanya jika status = dijadwalkan) --}}
            <div x-show="status === 'dijadwalkan'" x-transition style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#0f172a;border-radius:10px;padding:14px;border:1px solid #8b5cf644;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a78bfa;margin-bottom:6px;">📅 Tanggal Survey</label>
                    <input type="date" name="tanggal_jadwal" value="{{ old('tanggal_jadwal') }}" style="width:100%;background:#1e293b;color:#f1f5f9;border:1px solid #4c1d95;border-radius:8px;padding:9px 12px;font-size:14px;box-sizing:border-box;">
                    @error('tanggal_jadwal')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a78bfa;margin-bottom:6px;">⏰ Jam</label>
                    <input type="time" name="jam_jadwal" value="{{ old('jam_jadwal') }}" style="width:100%;background:#1e293b;color:#f1f5f9;border:1px solid #4c1d95;border-radius:8px;padding:9px 12px;font-size:14px;box-sizing:border-box;">
                    @error('jam_jadwal')<div style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Catatan --}}
        <div style="background:#1e293b;border-radius:16px;padding:18px;margin-bottom:20px;border:1px solid #334155;">
            <div style="font-size:11px;font-weight:700;color:#fbbf24;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;">📝 Catatan</div>
            <textarea name="catatan" rows="3" placeholder="Catatan tambahan tentang customer atau lead ini…" style="width:100%;background:#0f172a;color:#f1f5f9;border:1px solid #334155;border-radius:10px;padding:10px 14px;font-size:14px;resize:vertical;box-sizing:border-box;">{{ old('catatan') }}</textarea>
        </div>

        {{-- Submit --}}
        <button type="submit" style="width:100%;padding:14px;background:#fbbf24;color:#0f172a;border:none;border-radius:14px;font-size:15px;font-weight:800;cursor:pointer;letter-spacing:.3px;">
            Simpan Lead →
        </button>
    </form>

</div>
@endsection
