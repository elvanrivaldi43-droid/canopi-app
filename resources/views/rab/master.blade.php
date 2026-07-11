@extends('layouts.app')
@section('title', 'Master Setting RAB')
@section('content')
<style>
* { box-sizing: border-box; }
.wrap { max-width: 960px; margin: 0 auto; padding: 16px 16px 100px; }

/* HEADER */
.page-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
}
.page-header h1 { font-size: 18px; font-weight: 700; color: #f1f5f9; margin: 0; flex: 1; }
.back-btn {
    width: 36px; height: 36px; background: #1e293b;
    border: 1px solid #334155; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: #94a3b8; flex-shrink: 0;
}

/* TAB */
.tab-bar {
    display: flex; gap: 4px; margin-bottom: 20px;
    background: #1e293b; border-radius: 10px; padding: 4px;
    overflow-x: auto;
}
.tab-btn {
    padding: 8px 16px; border-radius: 8px; border: none;
    font-size: 13px; font-weight: 600; cursor: pointer;
    color: #64748b; background: transparent;
    white-space: nowrap; transition: all .2s;
}
.tab-btn.active { background: #fbbf24; color: #0f172a; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* CARD */
.card {
    background: #1e293b; border: 1px solid #334155;
    border-radius: 12px; padding: 16px; margin-bottom: 14px;
}
.card-title {
    font-size: 12px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .5px;
    margin: 0 0 14px; display: flex; align-items: center;
    justify-content: space-between;
}

/* MODE SWITCH */
.mode-switch {
    background: #0f172a; border: 1px solid #334155;
    border-radius: 12px; padding: 16px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; flex-wrap: wrap; gap: 12px;
}
.mode-info h3 { font-size: 15px; font-weight: 700; color: #f1f5f9; margin: 0 0 4px; }
.mode-info p  { font-size: 12px; color: #64748b; margin: 0; }
.mode-btns { display: flex; gap: 8px; }
.mode-btn {
    padding: 8px 20px; border-radius: 8px; border: 1px solid #334155;
    font-size: 13px; font-weight: 700; cursor: pointer;
    transition: all .2s; background: transparent; color: #94a3b8;
}
.mode-btn.standar.active { background: #3b82f6; border-color: #3b82f6; color: white; }
.mode-btn.target.active  { background: #fbbf24; border-color: #fbbf24; color: #0f172a; }

/* TABLE EDIT */
.edit-table { width: 100%; border-collapse: collapse; }
.edit-table th {
    font-size: 11px; color: #475569; font-weight: 600;
    text-align: left; padding: 8px 10px;
    border-bottom: 1px solid #334155;
    text-transform: uppercase; letter-spacing: .3px;
}
.edit-table td {
    padding: 8px 10px; border-bottom: 1px solid #1e293b;
    font-size: 13px; color: #f1f5f9; vertical-align: middle;
}
.edit-table tr:last-child td { border-bottom: none; }
.edit-table input[type=number], .edit-table input[type=text], .edit-table select {
    background: #0f172a; border: 1px solid #334155; border-radius: 6px;
    padding: 6px 10px; font-size: 13px; color: #f1f5f9;
    width: 100%; outline: none; transition: border .2s;
}
.edit-table input:focus, .edit-table select:focus { border-color: #fbbf24; }
.edit-table .label-cell { font-weight: 600; font-size: 13px; }
.edit-table .sub-cell   { font-size: 11px; color: #475569; }
.badge-hemat   { background: #1a2e1a; color: #86efac; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
.badge-standar { background: #1e3a5f; color: #93c5fd; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
.badge-premium { background: #2d1a0a; color: #fdba74; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }

/* SAVE BTN */
.btn-save {
    background: #fbbf24; color: #0f172a;
    border: none; border-radius: 8px;
    padding: 10px 24px; font-size: 13px; font-weight: 700;
    cursor: pointer; transition: all .2s;
}
.btn-save:hover { background: #f59e0b; }
.btn-add {
    background: transparent; color: #fbbf24;
    border: 1px dashed #fbbf24; border-radius: 8px;
    padding: 8px 16px; font-size: 12px; font-weight: 600;
    cursor: pointer; width: 100%; margin-top: 8px;
    transition: all .2s;
}
.btn-add:hover { background: #1c1a0a; }
.btn-del {
    background: transparent; border: none;
    color: #ef4444; cursor: pointer; font-size: 16px; padding: 4px 8px;
}
.btn-del:hover { color: #fca5a5; }

/* TOGGLE AKTIF */
.toggle {
    position: relative; width: 38px; height: 22px;
    display: inline-block;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0;
    background: #334155; border-radius: 22px; cursor: pointer;
    transition: .3s;
}
.toggle-slider:before {
    content: ''; position: absolute;
    width: 16px; height: 16px; border-radius: 50%;
    left: 3px; bottom: 3px;
    background: white; transition: .3s;
}
.toggle input:checked + .toggle-slider { background: #22c55e; }
.toggle input:checked + .toggle-slider:before { transform: translateX(16px); }

/* TOAST */
.toast {
    position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    background: #22c55e; color: white; padding: 10px 24px;
    border-radius: 20px; font-size: 13px; font-weight: 600;
    z-index: 999; display: none; white-space: nowrap;
}
.toast.error { background: #ef4444; }
.toast.show { display: block; animation: fadeInOut 2.5s ease forwards; }
@keyframes fadeInOut {
    0%   { opacity: 0; transform: translateX(-50%) translateY(10px); }
    15%  { opacity: 1; transform: translateX(-50%) translateY(0); }
    75%  { opacity: 1; }
    100% { opacity: 0; }
}

/* KATALOG */
.katalog-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}
.katalog-card {
    background: #0f172a; border: 1px solid #334155;
    border-radius: 10px; overflow: hidden; position: relative;
}
.katalog-card img {
    width: 100%; height: 100px; object-fit: cover;
    display: block; background: #1e293b;
}
.katalog-card .katalog-body { padding: 8px; }
.katalog-card .katalog-nama { font-size: 12px; font-weight: 600; color: #f1f5f9; margin-bottom: 2px; }
.katalog-card .katalog-harga { font-size: 10px; color: #fbbf24; }
.katalog-card .katalog-del {
    position: absolute; top: 4px; right: 4px;
    background: rgba(239,68,68,.8); border: none; border-radius: 50%;
    width: 22px; height: 22px; color: white; cursor: pointer;
    font-size: 12px; display: flex; align-items: center; justify-content: center;
}

/* ADD KATALOG FORM */
.add-form {
    background: #0f172a; border: 1px dashed #334155;
    border-radius: 10px; padding: 14px; margin-top: 12px;
    display: none;
}
.add-form.show { display: block; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group { margin-bottom: 10px; }
.form-group label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; font-weight: 600; }
.form-group input, .form-group select {
    width: 100%; background: #1e293b; border: 1px solid #334155;
    border-radius: 6px; padding: 8px 10px; font-size: 13px;
    color: #f1f5f9; outline: none;
}
.form-group input:focus, .form-group select:focus { border-color: #fbbf24; }
</style>

<div class="wrap">
    <div class="page-header">
        <a href="{{ route('rab.index') }}" class="back-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
        </a>
        <h1>Master Setting RAB</h1>
        <span style="font-size:11px;color:#475569">Owner Only</span>
    </div>

    {{-- TAB BAR --}}
    <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('margin')">💰 Margin & Diskon</button>
        <button class="tab-btn" onclick="showTab('paket')">🏗️ Paket Konstruksi</button>
        <button class="tab-btn" onclick="showTab('addon')">➕ Add-on</button>
        <button class="tab-btn" onclick="showTab('katalog')">🖼️ Katalog Foto</button>
        <button class="tab-btn" onclick="showTab('kondisi')">⚠️ Kondisi Lokasi</button>
    </div>

    {{-- TAB 1: MARGIN & MODE HARGA --}}
    <div class="tab-content active" id="tab-margin">
        {{-- MODE SWITCH --}}
        <div class="mode-switch">
            <div class="mode-info">
                <h3>Mode Harga Aktif</h3>
                <p>Semua quote baru pakai margin sesuai mode yang aktif</p>
            </div>
            <div class="mode-btns">
                <button class="mode-btn standar {{ $modeAktif === 'standar' ? 'active' : '' }}"
                    onclick="switchMode('standar')">
                    📊 Standar
                </button>
                <button class="mode-btn target {{ $modeAktif === 'target' ? 'active' : '' }}"
                    onclick="switchMode('target')">
                    🎯 Target (Kejar Profit)
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                Margin Per Produk
                <button class="btn-save" onclick="simpanMargin()">Simpan Margin</button>
            </div>
            <div style="overflow-x:auto">
                <table class="edit-table" id="tblMargin">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Margin Min % (BEP)</th>
                            <th>Margin Standar %</th>
                            <th>Margin Target %</th>
                            <th>Diskon Maks %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($produk as $p)
                        @php $m = $margins[$p->kode] ?? null @endphp
                        <tr data-kode="{{ $p->kode }}">
                            <td>
                                <div class="label-cell">{{ $p->nama }}</div>
                                <div class="sub-cell">{{ $p->kode }}</div>
                            </td>
                            <td><input type="number" name="min" value="{{ $m?->margin_min_persen ?? 15 }}" min="1" max="99" step="0.5"></td>
                            <td><input type="number" name="standar" value="{{ $m?->margin_standar_persen ?? 25 }}" min="1" max="99" step="0.5"></td>
                            <td><input type="number" name="target" value="{{ $m?->margin_target_persen ?? 35 }}" min="1" max="99" step="0.5"></td>
                            <td><input type="number" name="diskon" value="{{ $m?->diskon_max_persen ?? 15 }}" min="0" max="50" step="0.5"></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 2: PAKET KONSTRUKSI --}}
    <div class="tab-content" id="tab-paket">
        <div class="card">
            <div class="card-title">
                Harga Paket Konstruksi per Zona
                <button class="btn-save" onclick="simpanPaket()">Simpan Paket</button>
            </div>
            <p style="font-size:12px;color:#64748b;margin:0 0 14px">
                Harga di sini adalah <strong style="color:#fbbf24">harga pokok rangka per m²</strong> — sebelum buffer dan margin.
                Harga jual ke customer = harga pokok × (1 + buffer 20%) × (1 + margin%).
            </p>
            <div style="overflow-x:auto">
                <table class="edit-table" id="tblPaket">
                    <thead>
                        <tr>
                            <th>Zona</th>
                            <th>Paket</th>
                            <th>Label Konstruksi</th>
                            <th>Harga Rangka/m²</th>
                            <th>Harga Jasa/m²</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $zonas = \App\Models\RabZonaBentangan::where('is_active',1)->orderBy('urutan')->get();
                        @endphp
                        @foreach($zonas as $zona)
                        @php
                            $pakets = \App\Models\RabPaketKonstruksi::where('zona_id',$zona->id)->where('is_active',1)->orderBy('urutan')->get();
                        @endphp
                        @foreach($pakets as $paket)
                        <tr data-id="{{ $paket->id }}">
                            @if($loop->first)
                            <td rowspan="{{ $pakets->count() }}" style="vertical-align:top;padding-top:12px;border-right:1px solid #334155">
                                <div style="font-size:12px;font-weight:700;color:#f1f5f9">{{ $zona->nama }}</div>
                                <div style="font-size:10px;color:#475569;margin-top:2px">{{ $zona->deskripsi }}</div>
                            </td>
                            @endif
                            <td>
                                <span class="badge-{{ strtolower($paket->nama_paket) }}">{{ $paket->nama_paket }}</span>
                            </td>
                            <td>
                                <input type="text" name="label" value="{{ $paket->label_display }}" style="width:200px">
                            </td>
                            <td>
                                <input type="number" name="harga_rangka" value="{{ $paket->harga_per_m2_rangka }}" min="0" step="1000" style="width:120px">
                            </td>
                            <td>
                                <input type="number" name="harga_jasa" value="{{ $paket->harga_per_m2_jasa_pasang }}" min="0" step="1000" style="width:120px">
                            </td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- SIMULASI HARGA --}}
            <div style="background:#0f172a;border:1px solid #1e3a5f;border-radius:8px;padding:12px;margin-top:14px">
                <div style="font-size:12px;color:#93c5fd;font-weight:700;margin-bottom:10px">🧮 Simulasi Harga Jual</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <div>
                        <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px">Luas (m²)</label>
                        <input type="number" id="simM2" value="12" style="width:80px;background:#1e293b;border:1px solid #334155;border-radius:6px;padding:6px 10px;color:#f1f5f9;font-size:14px;outline:none">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#64748b;display:block;margin-bottom:4px">Margin %</label>
                        <input type="number" id="simMargin" value="25" style="width:70px;background:#1e293b;border:1px solid #334155;border-radius:6px;padding:6px 10px;color:#f1f5f9;font-size:14px;outline:none">
                    </div>
                    <button onclick="simulasi()" style="background:#3b82f6;color:white;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer">Hitung</button>
                </div>
                <div id="simResult" style="margin-top:12px;display:none">
                    <table style="width:100%;font-size:12px;border-collapse:collapse" id="simTable">
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: ADD-ON --}}
    <div class="tab-content" id="tab-addon">
        <div class="card">
            <div class="card-title">
                Daftar Add-on Modul
                <button class="btn-save" onclick="simpanAddon()">Simpan Add-on</button>
            </div>
            <div style="overflow-x:auto">
                <table class="edit-table" id="tblAddon">
                    <thead>
                        <tr>
                            <th>Nama Add-on</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th>Harga Jual/Sat</th>
                            <th>Harga Pokok/Sat</th>
                            <th>Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($addons as $a)
                        <tr data-id="{{ $a->id }}">
                            <td><input type="text" name="nama" value="{{ $a->nama }}" style="min-width:160px"></td>
                            <td>
                                <select name="kategori" style="width:120px">
                                    @foreach(['talang','pembuangan','plafon','pencahayaan','struktur','dinding','finishing','lainnya'] as $kat)
                                    <option value="{{ $kat }}" {{ $a->kategori === $kat ? 'selected' : '' }}>{{ ucfirst($kat) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="satuan" value="{{ $a->satuan }}" style="width:80px"></td>
                            <td><input type="number" name="harga_satuan" value="{{ $a->harga_satuan }}" min="0" step="1000" style="width:110px"></td>
                            <td><input type="number" name="harga_pokok" value="{{ $a->harga_pokok_satuan }}" min="0" step="1000" style="width:110px"></td>
                            <td>
                                <label class="toggle">
                                    <input type="checkbox" name="is_active" {{ $a->is_active ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button class="btn-add" onclick="tambahAddonRow()">+ Tambah Add-on Baru</button>
        </div>
    </div>

    {{-- TAB 4: KATALOG FOTO --}}
    <div class="tab-content" id="tab-katalog">
        <div class="card">
            <div class="card-title">Katalog Referensi Foto</div>

            {{-- Filter per produk --}}
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
                <button class="tab-btn active" onclick="filterKatalog('semua',this)" style="padding:5px 12px;font-size:11px">Semua</button>
                @foreach($produk as $p)
                <button class="tab-btn" onclick="filterKatalog('{{ $p->kode }}',this)" style="padding:5px 12px;font-size:11px">{{ $p->nama }}</button>
                @endforeach
            </div>

            <div class="katalog-grid" id="katalogGrid">
                @foreach($katalog as $k)
                <div class="katalog-card" data-produk="{{ $k->produk_kode }}" id="katalogCard_{{ $k->id }}">
                    <img src="{{ $k->foto_url }}" alt="{{ $k->judul }}"
                        onerror="this.src='https://via.placeholder.com/200x100/1e293b/475569?text=No+Foto'">
                    <button class="katalog-del" onclick="hapusKatalog({{ $k->id }})">×</button>
                    <div class="katalog-body">
                        <div class="katalog-nama">{{ $k->judul }}</div>
                        <div class="katalog-harga">
                            Rp {{ number_format($k->kisaran_harga_min/1000000,0) }}jt –
                            Rp {{ number_format($k->kisaran_harga_max/1000000,0) }}jt
                        </div>
                        <div style="font-size:9px;color:#475569;margin-top:2px">{{ $k->produk_kode }} | {{ $k->tipe_lokasi }}</div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Form tambah katalog --}}
            <button class="btn-add" onclick="document.getElementById('formKatalog').classList.toggle('show')">
                + Tambah Foto Katalog
            </button>
            <div class="add-form" id="formKatalog">
                <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:12px">Tambah Foto Baru</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Judul</label>
                        <input type="text" id="katJudul" placeholder="Kanopi Alderon Minimalis">
                    </div>
                    <div class="form-group">
                        <label>URL Foto (Pinterest/upload)</label>
                        <input type="url" id="katFoto" placeholder="https://i.pinimg.com/...">
                    </div>
                    <div class="form-group">
                        <label>Jenis Produk</label>
                        <select id="katProduk">
                            @foreach($produk as $p)
                            <option value="{{ $p->kode }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipe Lokasi</label>
                        <select id="katLokasi">
                            <option value="rumah">Rumah</option>
                            <option value="ruko">Ruko</option>
                            <option value="kafe">Kafe</option>
                            <option value="gudang">Gudang</option>
                            <option value="mall">Mall</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kisaran Harga Min (Rp)</label>
                        <input type="number" id="katMin" placeholder="8000000" step="500000">
                    </div>
                    <div class="form-group">
                        <label>Kisaran Harga Maks (Rp)</label>
                        <input type="number" id="katMax" placeholder="20000000" step="500000">
                    </div>
                    <div class="form-group">
                        <label>Material Atap Default</label>
                        <select id="katAtap">
                            <option value="">— Tidak ada —</option>
                            @foreach(\App\Models\RabAtap::where('is_active',1)->get() as $a)
                            <option value="{{ $a->kode }}">{{ $a->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tag Pencarian</label>
                        <input type="text" id="katTag" placeholder="minimalis modern alderon">
                    </div>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <input type="text" id="katDeskripsi" placeholder="Kanopi hollow alderon cocok rumah cluster...">
                </div>
                <div style="display:flex;gap:8px;margin-top:4px">
                    <button class="btn-save" onclick="simpanKatalog()">Simpan Foto</button>
                    <button onclick="document.getElementById('formKatalog').classList.remove('show')"
                        style="background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:10px 20px;font-size:13px;cursor:pointer">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 5: KONDISI LOKASI --}}
    <div class="tab-content" id="tab-kondisi">
        <div class="card">
            <div class="card-title">
                Kondisi Lokasi & Multiplier
                <button class="btn-save" onclick="simpanKondisi()">Simpan Kondisi</button>
            </div>
            <p style="font-size:12px;color:#64748b;margin:0 0 14px">
                <strong style="color:#f59e0b">persen_add</strong>: tambahkan X% dari biaya pokok &nbsp;|&nbsp;
                <strong style="color:#f59e0b">flat_add</strong>: tambahkan nominal tetap (Rp)
            </p>
            <div style="overflow-x:auto">
                <table class="edit-table" id="tblKondisi">
                    <thead>
                        <tr>
                            <th>Nama Kondisi</th>
                            <th>Tipe</th>
                            <th>Nilai (% atau Rp)</th>
                            <th>Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Models\RabKondisiLokasi::orderBy('urutan')->get() as $k)
                        <tr data-id="{{ $k->id }}">
                            <td>
                                <input type="text" name="nama" value="{{ $k->nama }}" style="min-width:180px">
                                <div class="sub-cell" style="margin-top:3px">{{ $k->deskripsi }}</div>
                            </td>
                            <td>
                                <select name="tipe" style="width:120px">
                                    <option value="persen_add" {{ $k->tipe === 'persen_add' ? 'selected' : '' }}>persen_add</option>
                                    <option value="flat_add"   {{ $k->tipe === 'flat_add'   ? 'selected' : '' }}>flat_add</option>
                                    <option value="multiplier" {{ $k->tipe === 'multiplier' ? 'selected' : '' }}>multiplier</option>
                                </select>
                            </td>
                            <td><input type="number" name="nilai" value="{{ $k->nilai }}" step="0.5" style="width:100px"></td>
                            <td>
                                <label class="toggle">
                                    <input type="checkbox" name="is_active" {{ $k->is_active ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- TOAST --}}
<div class="toast" id="toast"></div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// ============================================================
// TAB
// ============================================================
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}

// ============================================================
// SWITCH MODE HARGA
// ============================================================
async function switchMode(mode) {
    const resp = await post('/rab/switch-mode', { mode });
    if (resp.success) {
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.mode-btn.' + mode).classList.add('active');
        toast('Mode harga: ' + mode.toUpperCase());
    }
}

// ============================================================
// SIMPAN MARGIN
// ============================================================
async function simpanMargin() {
    const rows = document.querySelectorAll('#tblMargin tbody tr');
    const data = [];
    rows.forEach(row => {
        data.push({
            produk_kode:            row.dataset.kode,
            margin_min_persen:      row.querySelector('[name=min]').value,
            margin_standar_persen:  row.querySelector('[name=standar]').value,
            margin_target_persen:   row.querySelector('[name=target]').value,
            diskon_max_persen:      row.querySelector('[name=diskon]').value,
        });
    });
    const resp = await post('/rab/master/margin-bulk', { data });
    resp.success ? toast('Margin tersimpan') : toast('Gagal simpan', true);
}

// ============================================================
// SIMPAN PAKET KONSTRUKSI
// ============================================================
async function simpanPaket() {
    const rows = document.querySelectorAll('#tblPaket tbody tr');
    const data = [];
    rows.forEach(row => {
        data.push({
            id:           row.dataset.id,
            label_display:row.querySelector('[name=label]').value,
            harga_per_m2_rangka:      row.querySelector('[name=harga_rangka]').value,
            harga_per_m2_jasa_pasang: row.querySelector('[name=harga_jasa]').value,
        });
    });
    const resp = await post('/rab/master/paket-bulk', { data });
    resp.success ? toast('Paket konstruksi tersimpan') : toast('Gagal simpan', true);
}

// ============================================================
// SIMULASI HARGA
// ============================================================
function simulasi() {
    const m2     = parseFloat(document.getElementById('simM2').value) || 12;
    const margin = parseFloat(document.getElementById('simMargin').value) || 25;
    const rows   = document.querySelectorAll('#tblPaket tbody tr');
    let html = `<tr style="border-bottom:1px solid #334155">
        <th style="padding:6px 8px;color:#475569;text-align:left">Paket</th>
        <th style="padding:6px 8px;color:#475569;text-align:right">Harga Pokok</th>
        <th style="padding:6px 8px;color:#475569;text-align:right">+Buffer 20%</th>
        <th style="padding:6px 8px;color:#475569;text-align:right;color:#fbbf24">Harga Jual</th>
    </tr>`;
    rows.forEach(row => {
        const label  = row.querySelector('[name=label]').value;
        const rangka = parseFloat(row.querySelector('[name=harga_rangka]').value) || 0;
        const jasa   = parseFloat(row.querySelector('[name=harga_jasa]').value) || 0;
        const pokok  = m2 * (rangka + jasa);
        const buffer = pokok * 1.20;
        const jual   = buffer * (1 + margin / 100);
        html += `<tr style="border-bottom:1px solid #1e293b">
            <td style="padding:6px 8px;color:#94a3b8;font-size:11px">${label}</td>
            <td style="padding:6px 8px;text-align:right;color:#64748b">${fmt(pokok)}</td>
            <td style="padding:6px 8px;text-align:right;color:#64748b">${fmt(buffer)}</td>
            <td style="padding:6px 8px;text-align:right;font-weight:700;color:#fbbf24">${fmt(jual)}</td>
        </tr>`;
    });
    document.getElementById('simTable').innerHTML = html;
    document.getElementById('simResult').style.display = 'block';
}

// ============================================================
// SIMPAN ADDON
// ============================================================
async function simpanAddon() {
    const rows = document.querySelectorAll('#tblAddon tbody tr[data-id]');
    const data = [];
    rows.forEach(row => {
        data.push({
            id:               row.dataset.id,
            nama:             row.querySelector('[name=nama]').value,
            kategori:         row.querySelector('[name=kategori]').value,
            satuan:           row.querySelector('[name=satuan]').value,
            harga_satuan:     row.querySelector('[name=harga_satuan]').value,
            harga_pokok_satuan: row.querySelector('[name=harga_pokok]').value,
            is_active:        row.querySelector('[name=is_active]').checked ? 1 : 0,
        });
    });
    const resp = await post('/rab/master/addon-bulk', { data });
    resp.success ? toast('Add-on tersimpan') : toast('Gagal simpan', true);
}

function tambahAddonRow() {
    const tbody = document.querySelector('#tblAddon tbody');
    const tr = document.createElement('tr');
    tr.dataset.id = 'new_' + Date.now();
    tr.innerHTML = `
        <td><input type="text" name="nama" placeholder="Nama add-on baru" style="min-width:160px"></td>
        <td><select name="kategori" style="width:120px">
            ${['talang','pembuangan','plafon','pencahayaan','struktur','dinding','finishing','lainnya']
              .map(k => `<option value="${k}">${k}</option>`).join('')}
        </select></td>
        <td><input type="text" name="satuan" placeholder="m2" style="width:80px"></td>
        <td><input type="number" name="harga_satuan" placeholder="0" step="1000" style="width:110px"></td>
        <td><input type="number" name="harga_pokok" placeholder="0" step="1000" style="width:110px"></td>
        <td><button class="btn-del" onclick="this.closest('tr').remove()">×</button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('input').focus();
}

// ============================================================
// KATALOG
// ============================================================
function filterKatalog(kode, btn) {
    document.querySelectorAll('.katalog-card').forEach(c => {
        c.style.display = (kode === 'semua' || c.dataset.produk === kode) ? 'block' : 'none';
    });
    document.querySelectorAll('.tab-bar .tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

async function hapusKatalog(id) {
    if (!confirm('Hapus foto ini dari katalog?')) return;
    const resp = await post('/rab/master/katalog/hapus', { id });
    if (resp.success) {
        document.getElementById('katalogCard_' + id)?.remove();
        toast('Foto dihapus');
    }
}

async function simpanKatalog() {
    const data = {
        produk_kode:         document.getElementById('katProduk').value,
        judul:               document.getElementById('katJudul').value,
        foto_url:            document.getElementById('katFoto').value,
        deskripsi:           document.getElementById('katDeskripsi').value,
        tipe_lokasi:         document.getElementById('katLokasi').value,
        kisaran_harga_min:   document.getElementById('katMin').value,
        kisaran_harga_max:   document.getElementById('katMax').value,
        atap_kode:           document.getElementById('katAtap').value,
        tag:                 document.getElementById('katTag').value,
        sumber_foto:         'pinterest',
    };
    if (!data.judul || !data.foto_url) { toast('Judul dan URL foto wajib diisi', true); return; }
    const resp = await post('/rab/master/katalog/tambah', data);
    if (resp.success) {
        toast('Foto ditambahkan');
        document.getElementById('formKatalog').classList.remove('show');
        // Tambah card baru tanpa reload
        const grid = document.getElementById('katalogGrid');
        const div = document.createElement('div');
        div.className = 'katalog-card';
        div.dataset.produk = data.produk_kode;
        div.id = 'katalogCard_' + resp.id;
        div.innerHTML = `
            <img src="${data.foto_url}" onerror="this.src='https://via.placeholder.com/200x100/1e293b/475569?text=No+Foto'">
            <button class="katalog-del" onclick="hapusKatalog(${resp.id})">×</button>
            <div class="katalog-body">
                <div class="katalog-nama">${data.judul}</div>
                <div class="katalog-harga">Rp ${Math.round(data.kisaran_harga_min/1000000)}jt – Rp ${Math.round(data.kisaran_harga_max/1000000)}jt</div>
            </div>`;
        grid.prepend(div);
    } else {
        toast('Gagal tambah foto', true);
    }
}

// ============================================================
// SIMPAN KONDISI
// ============================================================
async function simpanKondisi() {
    const rows = document.querySelectorAll('#tblKondisi tbody tr');
    const data = [];
    rows.forEach(row => {
        data.push({
            id:        row.dataset.id,
            nama:      row.querySelector('[name=nama]').value,
            tipe:      row.querySelector('[name=tipe]').value,
            nilai:     row.querySelector('[name=nilai]').value,
            is_active: row.querySelector('[name=is_active]').checked ? 1 : 0,
        });
    });
    const resp = await post('/rab/master/kondisi-bulk', { data });
    resp.success ? toast('Kondisi tersimpan') : toast('Gagal simpan', true);
}

// ============================================================
// UTILS
// ============================================================
async function post(url, body) {
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body)
        });
        return await resp.json();
    } catch(e) {
        return { success: false, error: e.message };
    }
}

function fmt(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function toast(msg, isError = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast' + (isError ? ' error' : '') + ' show';
    setTimeout(() => el.classList.remove('show'), 2500);
}
</script>
@endsection