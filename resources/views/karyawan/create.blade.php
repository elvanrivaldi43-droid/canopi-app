@extends('layouts.app')

@section('page-title', 'Tambah Karyawan')

@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection

@section('bottom-nav')
    @include('partials.bottomnav-owner')
@endsection

@section('content')
<div style="max-width:680px;margin:0 auto;">

    {{-- Back --}}
    <a href="{{ route('karyawan.index') }}" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#94A3B8;text-decoration:none;margin-bottom:20px;">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
        </svg>
        Kembali ke Daftar Karyawan
    </a>

    <form method="POST" action="{{ route('karyawan.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- DATA PRIBADI --}}
        <div class="stat-card" style="margin-bottom:16px;">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 20px 0;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.06);"
                :style="darkMode ? 'color:#F1F5F9;border-color:rgba(255,255,255,0.06)' : 'color:#1E293B;border-color:#F1F5F9'">
                👤 Data Pribadi
            </h3>

            {{-- Foto --}}
            <div style="margin-bottom:20px;text-align:center;" x-data="{ preview: null }">
                <div style="width:80px;height:80px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;background:linear-gradient(135deg,#C9A84C,#A8872E);color:white;overflow:hidden;cursor:pointer;" onclick="document.getElementById('foto').click()">
                    <img x-show="preview" :src="preview" style="width:100%;height:100%;object-fit:cover;">
                    <span x-show="!preview">+</span>
                </div>
                <input type="file" id="foto" name="foto" accept="image/*" style="display:none;"
                       @change="const file = $event.target.files[0]; if(file) { const reader = new FileReader(); reader.onload = e => preview = e.target.result; reader.readAsDataURL(file); }">
                <div style="font-size:12px;color:#94A3B8;">Tap untuk upload foto</div>
                @error('foto')<div style="font-size:11px;color:#F87171;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            @php
            $fields = [
                ['name'=>'name','label'=>'Nama Lengkap','type'=>'text','placeholder'=>'Nama lengkap karyawan','required'=>true],
                ['name'=>'email','label'=>'Email','type'=>'email','placeholder'=>'email@kanopibsd.co.id','required'=>true],
                ['name'=>'password','label'=>'Password Awal','type'=>'password','placeholder'=>'Min. 6 karakter','required'=>true],
                ['name'=>'no_hp','label'=>'No. HP (WhatsApp)','type'=>'text','placeholder'=>'08xxxxxxxxxx','required'=>true],
                ['name'=>'alamat','label'=>'Alamat','type'=>'text','placeholder'=>'Alamat lengkap','required'=>false],
            ];
            @endphp

            @foreach($fields as $f)
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">
                    {{ $f['label'] }} @if($f['required'])<span style="color:#EF4444;">*</span>@endif
                </label>
                <input type="{{ $f['type'] }}" name="{{ $f['name'] }}"
                       value="{{ old($f['name']) }}"
                       placeholder="{{ $f['placeholder'] }}"
                       {{ $f['required'] ? 'required' : '' }}
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                @error($f['name'])<div style="font-size:11px;color:#F87171;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            @endforeach
        </div>

        {{-- DATA PEKERJAAN --}}
        <div class="stat-card" style="margin-bottom:16px;">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 20px 0;padding-bottom:12px;border-bottom:1px solid;"
                :style="darkMode ? 'color:#F1F5F9;border-color:rgba(255,255,255,0.06)' : 'color:#1E293B;border-color:#F1F5F9'">
                💼 Data Pekerjaan
            </h3>

            {{-- Level --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Level <span style="color:#EF4444;">*</span></label>
                <select name="level" required
                        style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;cursor:pointer;"
                        :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    <option value="">Pilih level</option>
                    @foreach([1=>'Owner',2=>'Admin Operasional',3=>'Supervisor Lapangan',4=>'Marketing',5=>'Teknisi',6=>'Driver',7=>'Admin Toko Besi'] as $l => $n)
                    <option value="{{ $l }}" {{ old('level') == $l ? 'selected' : '' }}>Level {{ $l }} — {{ $n }}</option>
                    @endforeach
                </select>
                @error('level')<div style="font-size:11px;color:#F87171;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            {{-- Jabatan --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Jabatan <span style="color:#EF4444;">*</span></label>
                <input type="text" name="jabatan" value="{{ old('jabatan') }}" placeholder="cth: Tukang Las, Admin Sales, Surveyor" required
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                @error('jabatan')<div style="font-size:11px;color:#F87171;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            {{-- Tanggal Masuk --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Tanggal Masuk Kerja <span style="color:#EF4444;">*</span></label>
                <input type="date" name="tgl_masuk_kerja" value="{{ old('tgl_masuk_kerja', now()->format('Y-m-d')) }}" required
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
            </div>

            {{-- Jam Kerja --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Jam Masuk</label>
                    <input type="time" name="jam_masuk" value="{{ old('jam_masuk', '07:30') }}" required
                           style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                           :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Jam Pulang</label>
                    <input type="time" name="jam_pulang" value="{{ old('jam_pulang', '17:00') }}" required
                           style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                           :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                </div>
            </div>
        </div>

        {{-- DATA GAJI (Owner only) --}}
        @if(auth()->user()->level == 1)
        <div class="stat-card" style="margin-bottom:16px;">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 20px 0;padding-bottom:12px;border-bottom:1px solid;"
                :style="darkMode ? 'color:#F1F5F9;border-color:rgba(255,255,255,0.06)' : 'color:#1E293B;border-color:#F1F5F9'">
                💰 Data Gaji <span style="font-size:11px;font-weight:400;color:#94A3B8;">(Hanya Owner yang bisa lihat)</span>
            </h3>

            {{-- Tipe Gaji --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Tipe Gaji</label>
                <div style="display:flex;gap:8px;">
                    @foreach(['harian'=>'Per Hari','bulanan'=>'Per Bulan','project'=>'Per Project'] as $val => $label)
                    <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border-radius:10px;border:1.5px solid;cursor:pointer;font-size:12px;font-weight:600;transition:all 0.2s;"
                           :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#94A3B8' : 'border-color:#E2E8F0;color:#64748B'"
                           x-bind:style="'{{ $val }}' === $el.querySelector('input').checked ? 'border-color:#C9A84C;color:#C9A84C;background:rgba(201,168,76,0.1)' : ''">
                        <input type="radio" name="tipe_gaji" value="{{ $val }}" {{ old('tipe_gaji','harian') == $val ? 'checked' : '' }} style="accent-color:#C9A84C;">
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Nominal Gaji --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Gaji Harian (Rp)</label>
                    <input type="number" name="gaji_harian" value="{{ old('gaji_harian', 0) }}" min="0"
                           style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                           :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Gaji Bulanan (Rp)</label>
                    <input type="number" name="gaji_bulanan" value="{{ old('gaji_bulanan', 0) }}" min="0"
                           style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                           :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Uang Makan Per Hari (Rp)</label>
                <input type="number" name="uang_makan" value="{{ old('uang_makan', 0) }}" min="0"
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
            </div>

            {{-- Tunjangan --}}
            @if($tunjangan->count() > 0)
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:10px;">Tunjangan Tambahan</label>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($tunjangan as $t)
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;border:1.5px solid;"
                         :style="darkMode ? 'border-color:rgba(255,255,255,0.06)' : 'border-color:#F1F5F9'">
                        <div style="flex:1;">
                            <div style="font-size:12px;font-weight:600;" :style="darkMode ? 'color:#E2E8F0' : 'color:#1E293B'">{{ $t->nama_tunjangan }}</div>
                            <div style="font-size:11px;color:#94A3B8;">Per {{ $t->tipe == 'harian' ? 'hari' : 'bulan' }}</div>
                        </div>
                        <input type="number" name="tunjangan[{{ $t->id }}]"
                               value="{{ old('tunjangan.'.$t->id, $t->nominal_default) }}"
                               min="0" placeholder="0"
                               style="width:120px;padding:8px 12px;border-radius:8px;font-size:12px;outline:none;border:1.5px solid;background:transparent;text-align:right;"
                               :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- DATA REKENING --}}
        <div class="stat-card" style="margin-bottom:16px;">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 20px 0;padding-bottom:12px;border-bottom:1px solid;"
                :style="darkMode ? 'color:#F1F5F9;border-color:rgba(255,255,255,0.06)' : 'color:#1E293B;border-color:#F1F5F9'">
                🏦 Data Rekening Bank
            </h3>

            {{-- Bank --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Nama Bank</label>
                <select name="nama_bank"
                        style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;cursor:pointer;"
                        :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    <option value="">Pilih bank</option>
                    @foreach($banks as $bank)
                    <option value="{{ $bank }}" {{ old('nama_bank') == $bank ? 'selected' : '' }}>{{ $bank }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Nomor Rekening</label>
                <input type="text" name="no_rekening" value="{{ old('no_rekening') }}" placeholder="xxxx xxxx xxxx"
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Atas Nama</label>
                <input type="text" name="atas_nama" value="{{ old('atas_nama') }}" placeholder="Nama sesuai buku tabungan"
                       style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;"
                       :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
            </div>
        </div>

        {{-- Submit --}}
        <div style="display:flex;gap:10px;">
            <a href="{{ route('karyawan.index') }}"
               style="flex:1;display:flex;align-items:center;justify-content:center;padding:14px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid;text-align:center;"
               :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#94A3B8' : 'border-color:#E2E8F0;color:#64748B'">
                Batal
            </a>
            <button type="submit"
                    style="flex:2;padding:14px;border-radius:12px;font-size:13px;font-weight:700;border:none;cursor:pointer;color:#0F1117;background:linear-gradient(135deg,#C9A84C,#A8872E);">
                Simpan Karyawan
            </button>
        </div>

    </form>
</div>
@endsection