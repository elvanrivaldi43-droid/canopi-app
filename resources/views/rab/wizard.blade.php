@extends('layouts.app')
@section('title', 'RAB Builder')
@section('content')

<style>
* { box-sizing: border-box; }
body { background: #0f172a; }
.rab-wrap { max-width: 480px; margin: 0 auto; padding: 12px 12px 120px; font-family: sans-serif; }
.rab-header { display: flex; align-items: center; gap: 10px; padding: 10px 0 16px; }
.rab-header .back-btn { width: 36px; height: 36px; background: #1e293b; border: 1px solid #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; color: #94a3b8; }
.rab-header h1 { font-size: 16px; font-weight: 600; color: #f1f5f9; margin: 0; flex: 1; }
.lead-badge { background: #1e3a5f; border: 1px solid #1d4ed8; border-radius: 6px; padding: 3px 10px; font-size: 11px; color: #93c5fd; }
.step-bar { display: flex; gap: 4px; margin-bottom: 20px; }
.step-bar .step { flex: 1; height: 4px; background: #1e293b; border-radius: 2px; transition: background .3s; }
.step-bar .step.active { background: #fbbf24; }
.step-bar .step.done   { background: #22c55e; }
.card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
.card-title { font-size: 13px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 14px; display: flex; align-items: center; gap: 6px; }
.jalur-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.jalur-btn { background: #0f172a; border: 2px solid #334155; border-radius: 10px; padding: 16px 12px; text-align: center; cursor: pointer; transition: all .2s; }
.jalur-btn:hover, .jalur-btn.active { border-color: #fbbf24; background: #1c1a0a; }
.jalur-btn .icon { font-size: 28px; margin-bottom: 6px; }
.jalur-btn .label { font-size: 13px; font-weight: 600; color: #f1f5f9; }
.jalur-btn .sub   { font-size: 11px; color: #64748b; margin-top: 2px; }
.produk-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.produk-btn { background: #0f172a; border: 2px solid #334155; border-radius: 10px; padding: 14px 10px; text-align: center; cursor: pointer; transition: all .2s; }
.produk-btn:hover, .produk-btn.active { border-color: #fbbf24; background: #1c1a0a; }
.produk-btn .icon { font-size: 22px; margin-bottom: 4px; }
.produk-btn .label { font-size: 12px; font-weight: 600; color: #f1f5f9; }
.katalog-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.katalog-item { border: 2px solid #334155; border-radius: 10px; overflow: hidden; cursor: pointer; transition: all .2s; position: relative; }
.katalog-item:hover, .katalog-item.active { border-color: #fbbf24; }
.katalog-item img { width: 100%; height: 100px; object-fit: cover; display: block; background: #0f172a; }
.katalog-item .katalog-info { padding: 8px; background: #1e293b; }
.katalog-item .katalog-nama { font-size: 11px; font-weight: 600; color: #f1f5f9; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.katalog-item .katalog-harga { font-size: 10px; color: #fbbf24; }
.katalog-item .katalog-check { position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; background: #fbbf24; border-radius: 50%; display: none; align-items: center; justify-content: center; }
.katalog-item.active .katalog-check { display: flex; }
.input-group { margin-bottom: 14px; }
.input-group label { display: block; font-size: 12px; color: #94a3b8; margin-bottom: 6px; font-weight: 500; }
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
input[type=number], input[type=text], select { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 12px 14px; font-size: 16px; color: #f1f5f9; outline: none; transition: border .2s; }
input[type=number]:focus, input[type=text]:focus, select:focus { border-color: #fbbf24; }
select option { background: #0f172a; }
.input-hint { font-size: 11px; color: #475569; margin-top: 4px; }
.input-rel { position: relative; }
.zona-info { background: #0f172a; border: 1px solid #1e3a5f; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: none; align-items: center; gap: 10px; }
.zona-info.show { display: flex; }
.zona-dot { width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
.zona-text { font-size: 12px; color: #93c5fd; }
.paket-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.paket-card { background: #0f172a; border: 2px solid #334155; border-radius: 10px; padding: 12px 8px; text-align: center; cursor: pointer; transition: all .2s; }
.paket-card.active { border-color: #fbbf24; background: #1c1a0a; }
.paket-card .paket-label { font-size: 11px; font-weight: 700; color: #fbbf24; margin-bottom: 4px; }
.paket-card .paket-konstruksi { font-size: 10px; color: #94a3b8; margin-bottom: 8px; min-height: 28px; }
.paket-card .paket-harga-sub { font-size: 9px; color: #475569; }
.atap-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.atap-item { background: #0f172a; border: 2px solid #334155; border-radius: 8px; padding: 10px 12px; cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: 10px; }
.atap-item:hover, .atap-item.active { border-color: #fbbf24; }
.atap-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.atap-dot.ringan { background: #22c55e; }
.atap-dot.sedang { background: #f59e0b; }
.atap-dot.berat  { background: #ef4444; }
.atap-nama { font-size: 12px; font-weight: 600; color: #f1f5f9; flex: 1; }
.atap-ket  { font-size: 10px; color: #64748b; margin-top: 2px; }
.addon-section { margin-bottom: 14px; }
.addon-section-title { font-size: 11px; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
.addon-item { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 10px 12px; margin-bottom: 6px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all .2s; }
.addon-item.checked { border-color: #fbbf24; background: #1c1a0a; }
.addon-check { width: 20px; height: 20px; border-radius: 4px; border: 2px solid #334155; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all .2s; }
.addon-item.checked .addon-check { background: #fbbf24; border-color: #fbbf24; }
.addon-check svg { display: none; }
.addon-item.checked .addon-check svg { display: block; }
.addon-info { flex: 1; }
.addon-nama { font-size: 12px; font-weight: 600; color: #f1f5f9; }
.addon-harga { font-size: 11px; color: #fbbf24; }
.addon-qty { display: none; align-items: center; gap: 6px; }
.addon-item.checked .addon-qty { display: flex; }
.addon-qty input { width: 56px !important; padding: 6px 8px !important; font-size: 14px !important; text-align: center; }
.addon-qty span { font-size: 11px; color: #475569; }
.kondisi-item { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 10px 12px; margin-bottom: 6px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all .2s; }
.kondisi-item.checked { border-color: #f59e0b; background: #1a1200; }
.kondisi-item.checked .addon-check { background: #f59e0b; border-color: #f59e0b; }
.kondisi-tambah { font-size: 11px; color: #f59e0b; margin-left: auto; }
.price-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #1e293b; border-top: 1px solid #334155; padding: 12px 16px; z-index: 100; }
.price-bar-inner { max-width: 480px; margin: 0 auto; }
.price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.price-label { font-size: 12px; color: #64748b; }
.price-value { font-size: 20px; font-weight: 700; color: #fbbf24; transition: all .3s; }
.price-loading { opacity: .5; }
.btn { padding: 12px; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all .2s; text-align: center; }
.btn-outline { background: transparent; border: 1px solid #334155; color: #94a3b8; }
.btn-gold { background: #fbbf24; color: #0f172a; }
.btn-gold:hover { background: #f59e0b; }
.btn-green { background: #22c55e; color: #0f172a; }
.btn-red { background: #ef4444; color: white; }
.btn:disabled { opacity: .4; cursor: not-allowed; }
.versi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px; }
.versi-card { background: #0f172a; border: 2px solid #334155; border-radius: 10px; padding: 12px 8px; text-align: center; cursor: pointer; transition: all .2s; }
.versi-card.active { border-color: #fbbf24; background: #1c1a0a; }
.versi-label { font-size: 10px; font-weight: 700; color: #fbbf24; margin-bottom: 4px; }
.versi-harga { font-size: 14px; font-weight: 700; color: #f1f5f9; }
.versi-harga-kecil { font-size: 9px; color: #64748b; margin-top: 2px; }
.mezzanine-notice { background: #1a0a00; border: 1px solid #92400e; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
.mezzanine-notice .title { font-size: 13px; font-weight: 700; color: #fbbf24; margin-bottom: 6px; }
.mezzanine-notice .body { font-size: 12px; color: #d97706; line-height: 1.5; }
.step-section { display: none; }
.step-section.show { display: block; }
.loading-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.8); z-index: 200; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 12px; }
.loading-overlay.show { display: flex; }
.spinner { width: 36px; height: 36px; border: 3px solid #334155; border-top-color: #fbbf24; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.loading-text { font-size: 13px; color: #94a3b8; }
</style>

<div class="rab-wrap">

    <div class="rab-header">
        <a href="{{ $lead ? route('pipeline.show', $lead->id) : route('rab.index') }}" class="back-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
        </a>
        <h1>RAB Builder</h1>
        @if($lead)<span class="lead-badge">{{ $lead->nama_customer }}</span>@endif
    </div>

    <div class="step-bar" id="stepBar">
        <div class="step active" id="stepDot1"></div>
        <div class="step" id="stepDot2"></div>
        <div class="step" id="stepDot3"></div>
        <div class="step" id="stepDot4"></div>
        <div class="step" id="stepDot5"></div>
    </div>

    {{-- STEP 1 --}}
    <div class="step-section show" id="sec1">
        <div class="card">
            <div class="card-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Jenis Proyek
            </div>
            <div class="produk-grid" id="produkGrid">
                <div style="color:#475569;font-size:12px;text-align:center;grid-column:1/-1;padding:20px">Memuat...</div>
            </div>
        </div>
        <div class="card" id="cardJalur" style="display:none">
            <div class="card-title">Jalur Konsultasi</div>
            <div class="jalur-grid">
                <div class="jalur-btn active" id="jalurA" onclick="pilihJalur('A')">
                    <div class="icon">🔍</div>
                    <div class="label">Lihat Katalog</div>
                    <div class="sub">Customer belum tahu model</div>
                </div>
                <div class="jalur-btn" id="jalurB" onclick="pilihJalur('B')">
                    <div class="icon">⚙️</div>
                    <div class="label">Input Langsung</div>
                    <div class="sub">Customer sudah paham spek</div>
                </div>
            </div>
        </div>
        <button class="btn btn-gold" style="width:100%" onclick="lanjutStep(2)" id="btnStep1Next" disabled>Lanjut →</button>
    </div>

    {{-- STEP 2A --}}
    <div class="step-section" id="sec2a">
        <div class="card">
            <div class="card-title">Pilih Referensi Model</div>
            <p style="font-size:12px;color:#64748b;margin:0 0 12px">Tunjukkan ke customer, biarkan dia pilih yang disukai</p>
            <div class="katalog-grid" id="katalogGrid">
                <div style="color:#475569;font-size:12px;text-align:center;grid-column:1/-1;padding:20px">Memuat katalog...</div>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline" onclick="lanjutStep(1)">← Kembali</button>
            <button class="btn btn-gold" style="flex:1" onclick="lanjutStep(3)" id="btnKatalogNext" disabled>Lanjut Ukuran →</button>
        </div>
    </div>

    {{-- STEP 2B --}}
    <div class="step-section" id="sec2b">
        <div class="card">
            <div class="card-title">Pilih Atap</div>
            <div class="atap-grid" id="atapGridB">
                <div style="color:#475569;font-size:12px;text-align:center;grid-column:1/-1;padding:20px">Memuat...</div>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline" onclick="lanjutStep(1)">← Kembali</button>
            <button class="btn btn-gold" style="flex:1" onclick="lanjutStep(3)">Lanjut Ukuran →</button>
        </div>
    </div>

    {{-- STEP 3 --}}
    <div class="step-section" id="sec3">
        <div class="card">
            <div class="card-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                Ukuran
            </div>
            <div class="input-row">
                <div class="input-group">
                    <label>Panjang (m)</label>
                    <input type="number" id="inpPanjang" placeholder="5.0" step="0.1" min="0.1" oninput="onUkuranChange()">
                </div>
                <div class="input-group">
                    <label>Lebar (m)</label>
                    <input type="number" id="inpLebar" placeholder="4.0" step="0.1" min="0.1" oninput="onUkuranChange()">
                </div>
            </div>
            <div class="input-group">
                <label>Bentangan terpanjang tanpa tiang (m)</label>
                <input type="number" id="inpBentangan" placeholder="= lebar jika tanpa tiang" step="0.1" min="0.1" oninput="onBentanganChange()">
                <div class="input-hint">Isi jika ada tiang di tengah. Kosongkan jika sama dengan lebar.</div>
            </div>
            <div id="m2Info" style="display:none;background:#0f172a;border-radius:8px;padding:10px 14px">
                <span style="font-size:12px;color:#64748b">Luas total: </span>
                <span id="m2Value" style="font-size:16px;font-weight:700;color:#fbbf24">0 m²</span>
            </div>
        </div>
        <div class="zona-info" id="zonaInfo">
            <div class="zona-dot"></div>
            <div class="zona-text" id="zonaText">Mendeteksi zona...</div>
        </div>
        <div class="card" id="cardSpek" style="display:none">
            <div class="card-title">Spek Rangka</div>
            <div class="input-group">
                <label>Frame Keliling</label>
                <select id="selFrame" onchange="onSpekChange()"></select>
            </div>
            <div class="input-row">
                <div class="input-group">
                    <label>Gording</label>
                    <select id="selGording" onchange="onSpekChange()"></select>
                </div>
                <div class="input-group">
                    <label>Jarak Kotakan Gording</label>
                    <select id="selJarak" onchange="onSpekChange()">
                        <option value="rekomendasi">Rekomendasi Sistem</option>
                        <option value="60">60 cm</option>
                        <option value="70">70 cm</option>
                        <option value="80">80 cm</option>
                        <option value="100">100 cm</option>
                    </select>
                </div>
            </div>
            <div class="input-group">
                <label>Material Tiang</label>
                <select id="selTiang" onchange="onSpekChange()"></select>
            </div>
            <div class="input-row">
                <div class="input-group">
                    <label>Jumlah Tiang (titik)</label>
                    <input type="number" id="inpJmlTiang" min="0" step="1" placeholder="auto" oninput="onSpekChange()">
                    <div class="input-hint" id="hintTiang">Rekomendasi: -</div>
                </div>
                <div class="input-group">
                    <label>Tinggi Tiang (m)</label>
                    <input type="number" id="inpTinggiTiang" value="3" min="1" step="0.5" oninput="onSpekChange()">
                </div>
            </div>
        </div>
        <div class="card" id="cardAtap" style="display:none">
            <div class="card-title">Pilih Atap</div>
            <div class="atap-grid" id="atapGridA"></div>
        </div>
        <div class="mezzanine-notice" id="mezzanineNotice" style="display:none">
            <div class="title">Mezzanine — Estimasi Awal</div>
            <div class="body">Harga adalah estimasi kasar dengan buffer 30%. RAB detail dikirim dalam 24 jam setelah booking fee.</div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline" onclick="backFromStep3()">← Kembali</button>
            <button class="btn btn-gold" style="flex:1" onclick="lanjutStep(4)" id="btnStep3Next" disabled>Lanjut →</button>
        </div>
    </div>

    {{-- STEP 4 --}}
    <div class="step-section" id="sec4">
        <div class="card">
            <div class="card-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Tambahan & Add-on
            </div>
            <div id="addonList"><div style="color:#475569;font-size:12px;text-align:center;padding:20px">Memuat...</div></div>
        </div>
        <div class="card">
            <div class="card-title" style="color:#f59e0b">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Kondisi Khusus
            </div>
            <div id="kondisiList"><div style="color:#475569;font-size:12px;text-align:center;padding:20px">Memuat...</div></div>
        </div>
        <div class="card">
            <div class="card-title">Catatan Surveyor</div>
            <textarea id="catatanSurveyor" rows="2" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;padding:10px 14px;font-size:14px;color:#f1f5f9;outline:none;resize:none" placeholder="Catatan kondisi lokasi, permintaan khusus customer..."></textarea>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline" onclick="lanjutStep(3)">← Kembali</button>
            <button class="btn btn-gold" style="flex:1" onclick="hitungDanLanjut()">Hitung Harga →</button>
        </div>
    </div>

    {{-- STEP 5: SALES COACH + NEGO --}}
    <div class="step-section" id="sec5">

        {{-- Info project --}}
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <div style="font-size:11px;color:#64748b">Customer</div>
                    <div style="font-size:14px;font-weight:700;color:#f1f5f9" id="hasilCustomer">-</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:11px;color:#64748b">Luas</div>
                    <div style="font-size:14px;font-weight:700;color:#fbbf24" id="hasilM2">-</div>
                </div>
            </div>
            <div style="background:#0f172a;border-radius:8px;padding:10px">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px"><span>Produk</span><span id="hasilProduk">-</span></div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px"><span>Konstruksi</span><span id="hasilKonstruksi">-</span></div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b"><span>Atap</span><span id="hasilAtap">-</span></div>
            </div>
        </div>

        {{-- 3 opsi paket --}}
        <div class="card">
            <div class="card-title">Harga Sesuai Spek</div>
            <div class="versi-grid" id="versiGrid"></div>
        </div>

        {{-- PERINGATAN HARGA TIDAK VALID (material inti belum lengkap) --}}
        <div class="card" id="peringatanHarga" style="display:none;border-color:#ef4444;background:#3f1515">
            <div style="display:flex;gap:10px;align-items:flex-start">
                <div style="font-size:22px">⚠️</div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:800;color:#fca5a5;margin-bottom:4px">Harga belum bisa dipakai untuk closing</div>
                    <div style="font-size:12px;color:#fecaca" id="peringatanList"></div>
                    <div style="font-size:11px;color:#fca5a5;margin-top:6px">Lengkapi material di Step 3, atau tambahkan material ke Master Material dulu. Tombol Deal dikunci sampai harga valid.</div>
                </div>
            </div>
        </div>

        {{-- Harga pembuka --}}
        <div class="card" style="border-color:#fbbf24">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <div style="font-size:11px;color:#64748b">Harga Penawaran Awal</div>
                    <div style="font-size:26px;font-weight:800;color:#fbbf24" id="hargaAwal">-</div>
                    <div style="font-size:11px;color:#475569;margin-top:2px">Sampaikan harga ini ke customer sebagai pembuka</div>
                </div>
            </div>
        </div>

        {{-- INPUT HARGA DEAL --}}
        <div class="card">
            <div class="card-title">Customer Nawar Berapa?</div>
            <div style="font-size:12px;color:#64748b;margin-bottom:10px">Ketik angka yang customer minta — sistem langsung kasih saran</div>
            <div style="position:relative">
                <div style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;color:#64748b;font-weight:600;pointer-events:none">Rp</div>
                <input type="text" id="inputHargaDeal" placeholder="0" inputmode="numeric"
                    style="width:100%;background:#0f172a;border:2px solid #334155;border-radius:10px;padding:14px 14px 14px 40px;font-size:22px;font-weight:700;color:#f1f5f9;outline:none;transition:border .2s"
                    oninput="onHargaDealInput(this)"
                    onfocus="this.style.borderColor='#fbbf24'"
                    onblur="this.style.borderColor='#334155'">
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:8px">
                <div style="font-size:11px;color:#475569">Harga deal saat ini:</div>
                <div style="font-size:13px;font-weight:700;color:#fbbf24" id="hargaFinalDisplay">Belum diisi</div>
            </div>
        </div>

        {{-- SALES COACH (muncul setelah input harga) --}}
        <div id="salesCoach"></div>

        {{-- Panduan sebelum input harga --}}
        <div id="panduanNego" class="card" style="text-align:center;padding:24px 16px">
            <div style="font-size:32px;margin-bottom:10px">🤝</div>
            <div style="font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:6px">Siap Nego!</div>
            <div style="font-size:12px;color:#64748b;line-height:1.6">Sampaikan harga pembuka ke customer.<br>Setelah customer nawar, ketik angkanya di atas.<br>Sistem akan kasih saran strategi langsung.</div>
            <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                <div style="background:#1e293b;border-radius:6px;padding:6px 12px;font-size:11px;color:#94a3b8">Garansi bocor 6 bln</div>
                <div style="background:#1e293b;border-radius:6px;padding:6px 12px;font-size:11px;color:#94a3b8">Rangka 1 tahun</div>
                <div style="background:#1e293b;border-radius:6px;padding:6px 12px;font-size:11px;color:#94a3b8">1.000+ project BSD</div>
            </div>
        </div>

        {{-- Tombol aksi (muncul setelah input harga) --}}
        <div id="btnDealArea" style="display:none">
            <div class="card">
                <div style="display:grid;gap:8px">
                    <button class="btn btn-gold" onclick="showTtdModal()">✍️ Deal — Tanda Tangan Sekarang</button>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <button class="btn btn-outline" onclick="kirimWaQuote()">📱 Kirim via WA</button>
                        <button class="btn btn-outline" onclick="simpanDraft()">💾 Simpan Draft</button>
                    </div>
                    <button class="btn btn-outline" onclick="lanjutStep(4)">← Revisi</button>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- FLOAT PRICE BAR --}}
<div class="price-bar" id="priceBar" style="display:none">
    <div class="price-bar-inner">
        <div class="price-row">
            <span class="price-label">Estimasi Harga</span>
            <span class="price-value" id="floatPrice">Rp —</span>
        </div>
    </div>
</div>

{{-- MODAL TTD --}}
<div id="ttdModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:300;overflow-y:auto">
    <div style="max-width:420px;margin:20px auto;padding:16px">
        <div style="background:#1e293b;border-radius:14px;padding:20px">
            <h3 style="font-size:16px;font-weight:700;color:#f1f5f9;margin:0 0 4px">Konfirmasi & Tanda Tangan</h3>
            <p style="font-size:12px;color:#64748b;margin:0 0 16px">Customer menandatangani sebagai tanda persetujuan harga</p>
            <div style="background:#0f172a;border-radius:8px;padding:12px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                    <span style="color:#64748b">Total Harga</span>
                    <span style="color:#fbbf24;font-weight:700" id="ttdHarga">-</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px">
                    <span style="color:#64748b">Produk</span>
                    <span style="color:#94a3b8" id="ttdProduk">-</span>
                </div>
            </div>
            <div class="input-group">
                <label>Nama Penandatangan</label>
                <input type="text" id="ttdNama" placeholder="Nama customer">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:6px">Tanda Tangan</label>
                <canvas id="ttdCanvas" width="380" height="150" style="width:100%;height:150px;background:#0f172a;border:1px solid #334155;border-radius:8px;touch-action:none;display:block"></canvas>
                <button onclick="clearTtd()" style="margin-top:6px;background:none;border:none;color:#64748b;font-size:11px;cursor:pointer">✕ Hapus tanda tangan</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <button class="btn btn-outline" onclick="closeTtdModal()">Batal</button>
                <button class="btn btn-green" onclick="submitDeal()">Konfirmasi Deal</button>
            </div>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loading-text" id="loadingText">Menghitung harga...</div>
</div>

<script>
// ============================================================
// STATE
// ============================================================
const state = {
    jalur:'A', produkKode:null, produkNama:null, katalogId:null,
    atapId:null, atapNama:null, paketId:null, paketLabel:null,
    panjang:0, lebar:0, bentangan:0, zonaId:null,
    addons:{}, kondisis:[], versiDipilih:'Standar',
    frameMatId:null, gordingMatId:null, tiangMatId:null,
    jarakGording:'rekomendasi', jumlahTiang:null, tinggiTiang:3,
    kalkulasi:null, diskonPersen:0, diskonMax:10,
    hargaNormal:0, hargaFinal:0, isEstimasiKasar:false,
    bonusDipilih:[],
};
let masterAtap=[], masterAddon={}, masterKondisi=[], masterStruktur=[], currentStep=1;
const leadId = {{ $lead ? $lead->id : 'null' }};
const leadNama = "{{ $lead ? $lead->nama_customer : '' }}";

// ============================================================
// SALES COACH DATA
// ============================================================
const ZONA_CFG = {
    kuat:  { min:40, warna:'#22c55e', gradient:'linear-gradient(90deg,#166534,#22c55e)', label:'Posisi Kuat — Pertahankan Harga', sub:'Customer serius beli. Mainkan value dulu, bukan harga.' },
    aman:  { min:30, warna:'#86efac', gradient:'linear-gradient(90deg,#14532d,#86efac)', label:'Harga Kompetitif — Mainkan Value', sub:'Masih bisa sedikit bergerak. Tawarkan bonus.' },
    tipis: { min:22, warna:'#f59e0b', gradient:'linear-gradient(90deg,#78350f,#f59e0b)', label:'Harga Ketat — Jangan Turun Lagi', sub:'Sudah kompetitif. Mainkan garansi dan urgensi jadwal.' },
    berat: { min:0,  warna:'#ef4444', gradient:'linear-gradient(90deg,#7f1d1d,#ef4444)', label:'Di Luar Kewenangan — Perlu Approval', sub:'Coba opsi spek turun dulu sebelum hubungi owner.' },
};
const SCRIPT_POOL = {
    kuat: [
        "Harga segitu udah include garansi bocor 6 bulan + rangka 1 tahun Pak. Kalau ada masalah kami balik gratis, gak perlu WA-WA dulu.",
        "Kami pakai bahan hollow Kuduhade Pak, bukan hollow biasa. Lebih tebal, anti karat, tahan lama.",
        "Bapak lihat dulu portfolio kami [tunjuk foto di HP]. Kualitas segini harga segitu udah sangat worth it Pak.",
        "Tetangga Bapak di cluster sebelah juga pakai kami bulan lalu Pak. Hasilnya bisa langsung Bapak lihat.",
        "Harga ini udah all-in Pak — material, pasang, bersih-bersih. Gak ada biaya tambahan lagi.",
    ],
    aman: [
        "Segini udah harga terbaik yang bisa saya kasih Pak. Kalau deal sekarang saya kasih bonus talang seng gratis.",
        "Jadwal kami minggu depan masih ada 1 slot Pak. Kalau deal hari ini bisa langsung kami masukkan jadwal.",
        "Gimana kalau DP dulu 50% sekarang, sisanya setelah kanopi selesai dipasang dan Bapak puas? Lebih aman kan Pak.",
        "Garansinya kami pegang Pak — bocor datang kami, rangka bermasalah datang kami. Tenang aja.",
    ],
    tipis: [
        "Saya udah usahakan yang terbaik Pak. Segini beneran udah harga saya. Garansinya tetap kami pegang ya.",
        "Kalau Bapak mau deal sekarang, saya kasih prioritas jadwal — minggu ini bisa langsung pasang Pak.",
        "Harga segini sama dengan yang kami kasih ke project perumahan cluster. Udah harga paket Pak.",
        "Bapak tenang soal kualitas — kami sudah pasang lebih dari 1.000 kanopi di BSD. Reputasi kami taruhannya.",
    ],
    berat: [
        "Wah Bapak nawarnya tajam sekali haha. Saya harus konsultasi dulu sama owner saya Pak, sebentar ya.",
        "Harga segini di luar kewenangan saya Pak. Izin saya WhatsApp owner dulu — biasanya fast response kok.",
        "Saya pengen bantu Bapak dapat harga terbaik, tapi ini perlu persetujuan dari atas dulu Pak.",
    ],
};
const BONUS_POOL = [
    { kode:'TALANG_SENG',     nama:'Talang Seng Gratis',         deskripsi:'Talang seng sepanjang kanopi, pasang include', biaya_real:320000, nilai_persepsi:600000, min_zona:'aman'  },
    { kode:'GARANSI_EXTRA',   nama:'Perpanjang Garansi +1 Tahun',deskripsi:'Garansi rangka jadi 2 tahun total',            biaya_real:0,      nilai_persepsi:500000, min_zona:'kuat'  },
    { kode:'CUCI_ATAP',       nama:'Free Cuci Atap 1x',          deskripsi:'Cuci atap gratis dalam 1 tahun pertama',      biaya_real:150000, nilai_persepsi:300000, min_zona:'aman'  },
    { kode:'ANGKUR_EXTRA',    nama:'Angkur Tanam Ekstra',         deskripsi:'Tambah angkur untuk konstruksi lebih kuat',  biaya_real:200000, nilai_persepsi:400000, min_zona:'aman'  },
    { kode:'CAT_CUSTOM',      nama:'Warna Cat Custom',            deskripsi:'Pilih warna cat sesuai keinginan Bapak',     biaya_real:100000, nilai_persepsi:250000, min_zona:'aman'  },
    { kode:'PRIORITAS_JADWAL',nama:'Prioritas Jadwal Pasang',     deskripsi:'Jadwal pasang diprioritaskan minggu ini',    biaya_real:0,      nilai_persepsi:200000, min_zona:'tipis' },
];
const OPSI_SPEK = [
    { kode:'GANTI_SPANDEK',   label:'Ganti atap alderon → spandek pasir',    hemat:800000, script:'Kalau Bapak mau pakai spandek pasir, harganya bisa lebih rendah. Kualitas anti bocor sama, hanya tampilan sedikit beda.' },
    { kode:'KURANGI_GARANSI', label:'Garansi rangka 6 bulan (bukan 1 tahun)',hemat:0,      script:'Garansinya kami sesuaikan jadi 6 bulan Pak biar harganya bisa kami turunkan.' },
    { kode:'TANPA_TALANG',    label:'Tanpa talang (pasang sendiri nanti)',    hemat:400000, script:'Kalau talangnya Bapak pasang sendiri nanti, harga bisa kami sesuaikan Pak.' },
    { kode:'BAYAR_TUNAI',     label:'Bayar tunai full di muka',               hemat:0,      script:'Kalau Bapak bisa bayar full sekarang, kami kasih potongan karena bantu cash flow kami.' },
];

function getZona(marginPersen) {
    if (marginPersen >= 40) return 'kuat';
    if (marginPersen >= 30) return 'aman';
    if (marginPersen >= 22) return 'tipis';
    return 'berat';
}

function renderSalesCoach(hargaDeal, biayaPokok, hargaNormal) {
    const margin = biayaPokok > 0 ? ((hargaDeal - biayaPokok) / hargaDeal) * 100 : 35;
    const zona = getZona(margin);
    const cfg = ZONA_CFG[zona];
    const barWidth = Math.min(Math.max(margin, 0), 60) / 60 * 100;
    const scripts = SCRIPT_POOL[zona];
    const script = scripts[Math.floor(Date.now()/15000) % scripts.length];
    const zonaOrder = ['berat','tipis','aman','kuat'];
    const bonus = BONUS_POOL.filter(b => zonaOrder.indexOf(zona) >= zonaOrder.indexOf(b.min_zona));
    const showSpek = zona === 'berat';
    const diskonPct = hargaNormal > 0 ? ((1 - hargaDeal/hargaNormal)*100).toFixed(1) : 0;

    return `
    <div style="background:#0f172a;border:2px solid ${cfg.warna};border-radius:12px;padding:16px;margin-bottom:12px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <div style="font-size:22px">${zona==='kuat'?'💪':zona==='aman'?'✅':zona==='tipis'?'⚠️':'🔴'}</div>
            <div style="flex:1">
                <div style="font-size:14px;font-weight:800;color:${cfg.warna}">${cfg.label}</div>
                <div style="font-size:11px;color:#64748b;margin-top:1px">${cfg.sub}</div>
            </div>
            <div style="text-align:right">
                <div style="font-size:10px;color:#475569">Diskon diminta</div>
                <div style="font-size:16px;font-weight:800;color:${cfg.warna}">${diskonPct}%</div>
            </div>
        </div>

        <!-- BAR MARGIN — tanpa angka persen -->
        <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#334155;margin-bottom:4px">
                <span>Rugi</span><span>BEP</span><span>Standar</span><span>Target</span>
            </div>
            <div style="height:10px;background:#1e293b;border-radius:5px;overflow:hidden;position:relative">
                <div style="position:absolute;left:36.7%;top:0;bottom:0;width:1px;background:#475569"></div>
                <div style="position:absolute;left:50%;top:0;bottom:0;width:1px;background:#475569"></div>
                <div style="position:absolute;left:66.7%;top:0;bottom:0;width:1px;background:#475569"></div>
                <div style="height:100%;width:${barWidth}%;background:${cfg.gradient};border-radius:5px;transition:width .4s ease"></div>
            </div>
        </div>

        <!-- SCRIPT -->
        <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px">
            <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Bisa bilang ke customer:</div>
            <div style="font-size:13px;color:#e2e8f0;line-height:1.6;font-style:italic">"${script}"</div>
        </div>

        <!-- BONUS -->
        ${bonus.length > 0 ? `
        <div style="margin-bottom:12px">
            <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Atau tawarkan bonus (tanpa turun harga):</div>
            ${bonus.map(b => `
            <label style="display:flex;align-items:center;gap:8px;background:#1e293b;border-radius:8px;padding:8px 10px;cursor:pointer;margin-bottom:6px">
                <input type="checkbox" class="bonus-check" data-kode="${b.kode}" data-biaya="${b.biaya_real}" style="width:16px;height:16px;accent-color:#fbbf24">
                <div style="flex:1">
                    <div style="font-size:12px;font-weight:600;color:#f1f5f9">${b.nama}</div>
                    <div style="font-size:10px;color:#64748b">${b.deskripsi}</div>
                </div>
                <div style="font-size:10px;color:#475569">~Rp ${Math.round(b.nilai_persepsi).toLocaleString('id-ID')}</div>
            </label>`).join('')}
        </div>` : ''}

        <!-- OPSI SPEK TURUN (zona berat) -->
        ${showSpek ? `
        <div style="margin-bottom:12px">
            <div style="font-size:10px;color:#f59e0b;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Coba dulu sebelum minta approval:</div>
            ${OPSI_SPEK.map(o => `
            <div style="background:#1e293b;border-radius:8px;padding:10px;cursor:pointer;border:1px solid #334155;margin-bottom:6px" onclick="tampilScript('${o.script.replace(/'/g,"\\'")}')">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div style="font-size:12px;font-weight:600;color:#f1f5f9">${o.label}</div>
                    ${o.hemat > 0 ? `<div style="font-size:11px;color:#22c55e">~Hemat Rp ${(o.hemat/1000).toFixed(0)}rb</div>` : ''}
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:3px;font-style:italic">"${o.script}"</div>
            </div>`).join('')}
        </div>` : ''}

        <!-- TOMBOL AKSI -->
        <div style="display:grid;grid-template-columns:${showSpek?'1fr 1fr':'1fr'};gap:8px">
            ${showSpek ? `
            <button onclick="dealHargaIni()" style="background:#334155;color:#94a3b8;border:none;border-radius:8px;padding:12px;font-size:13px;font-weight:700;cursor:pointer">
                Deal tanpa approval
            </button>
            <button onclick="requestApprovalOwner()" style="background:#f59e0b;color:#0f172a;border:none;border-radius:8px;padding:12px;font-size:13px;font-weight:700;cursor:pointer">
                Minta Approval Owner
            </button>` : `
            <button onclick="dealHargaIni()" style="background:#22c55e;color:#0f172a;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:800;cursor:pointer">
                Deal di Harga Ini
            </button>`}
        </div>
    </div>`;
}

function tampilScript(script) {
    const popup = document.createElement('div');
    popup.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#1e293b;border:1px solid #f59e0b;border-radius:10px;padding:14px 16px;max-width:340px;z-index:999;font-size:13px;color:#e2e8f0;line-height:1.6;box-shadow:0 10px 30px rgba(0,0,0,.5);width:90%';
    popup.innerHTML = `<div style="font-size:10px;color:#f59e0b;font-weight:700;margin-bottom:6px">SCRIPT:</div>"${script}"<div style="margin-top:10px;text-align:right"><button onclick="this.closest('div[style]').remove()" style="background:#334155;border:none;color:#94a3b8;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer">Tutup</button></div>`;
    document.body.appendChild(popup);
    setTimeout(() => { if(popup.parentNode) popup.remove(); }, 8000);
}

function requestApprovalOwner() { showTtdModal(); }

function dealHargaIni() {
    const bonusChecked = [...document.querySelectorAll('.bonus-check:checked')]
        .map(el => ({ kode: el.dataset.kode, biaya: parseInt(el.dataset.biaya) }));
    state.bonusDipilih = bonusChecked;
    showTtdModal();
}

// ============================================================
// HANDLER INPUT HARGA DEAL
// ============================================================
let negoTimer = null;
function onHargaDealInput(inputEl) {
    let raw = inputEl.value.replace(/[^0-9]/g, '');
    if (raw) inputEl.value = parseInt(raw).toLocaleString('id-ID');
    clearTimeout(negoTimer);
    negoTimer = setTimeout(() => prosesHargaDeal(parseInt(raw) || 0), 400);
}

function prosesHargaDeal(hargaDeal) {
    const MINIMUM = 5000000;
    const display = document.getElementById('hargaFinalDisplay');

    if (hargaDeal < 1000000) {
        display.textContent = 'Belum diisi';
        display.style.color = '#475569';
        document.getElementById('salesCoach').innerHTML = '';
        document.getElementById('btnDealArea').style.display = 'none';
        document.getElementById('panduanNego').style.display = 'block';
        return;
    }

    if (hargaDeal < MINIMUM) {
        display.textContent = 'Minimum Rp 5.000.000';
        display.style.color = '#ef4444';
        document.getElementById('salesCoach').innerHTML = `
            <div class="card" style="border-color:#ef4444;text-align:center;padding:20px">
                <div style="font-size:32px;margin-bottom:8px">🚫</div>
                <div style="font-size:14px;font-weight:700;color:#ef4444">Di Bawah Minimum Project</div>
                <div style="font-size:12px;color:#64748b;margin-top:6px">Harga minimum project kami Rp 5.000.000</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:8px;font-style:italic">"Maaf Pak, untuk project di bawah Rp 5 juta kami tidak bisa ambil karena biaya mobilisasi tim sudah segitu."</div>
            </div>`;
        document.getElementById('btnDealArea').style.display = 'none';
        document.getElementById('panduanNego').style.display = 'none';
        return;
    }

    state.hargaFinal = hargaDeal;
    state.diskonPersen = state.hargaNormal > 0 ? (1 - hargaDeal/state.hargaNormal)*100 : 0;

    display.textContent = formatRp(hargaDeal);
    display.style.color = '#fbbf24';
    document.getElementById('ttdHarga').textContent = formatRp(hargaDeal);
    document.getElementById('panduanNego').style.display = 'none';
    document.getElementById('btnDealArea').style.display = 'block';

    const versi = state.kalkulasi?.versi?.[0];
    const biayaPokok = versi?.biaya_pokok_total || 0;

    document.getElementById('salesCoach').innerHTML = renderSalesCoach(hargaDeal, biayaPokok, state.hargaNormal);
}

// ============================================================
// INIT & LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    await loadProduk();
    await loadAtap();
    await loadAddon();
    await loadKondisi();
    await loadMaterialStruktur();
    if (leadNama) document.getElementById('hasilCustomer').textContent = leadNama;
});

async function loadProduk() {
    renderProduk([
        {kode:'KANOPI_STD',    nama:'Kanopi Standar',   is_estimasi_saja:0},
        {kode:'KANOPI_DINDING',nama:'Kanopi + Dinding', is_estimasi_saja:0},
        {kode:'MEZZANINE',     nama:'Mezzanine',        is_estimasi_saja:1},
        {kode:'PAGAR',         nama:'Pagar',            is_estimasi_saja:0},
        {kode:'TRALIS',        nama:'Tralis',           is_estimasi_saja:0},
        {kode:'TENDA_MEMBRANE',nama:'Tenda Membrane',   is_estimasi_saja:0},
        {kode:'AWNING',        nama:'Awning',           is_estimasi_saja:0},
        {kode:'CARPORT',       nama:'Carport',          is_estimasi_saja:0},
    ]);
}
async function loadAtap() {
    try { const r=await fetch('/rab/api/atap'); masterAtap=await r.json(); renderAtapGrid('atapGridA'); renderAtapGrid('atapGridB'); } catch(e) {}
}
async function loadAddon() {
    try { const r=await fetch('/rab/api/addon'); masterAddon=await r.json(); renderAddonList(); } catch(e) {}
}
async function loadKondisi() {
    try { const r=await fetch('/rab/api/kondisi'); masterKondisi=await r.json(); renderKondisiList(); } catch(e) {}
}
async function loadKatalog(kode) {
    try { const r=await fetch('/rab/api/katalog?produk_kode='+kode); renderKatalog(await r.json()); } catch(e) {}
}
async function loadPaket(bentangan) {
    try {
        const r=await fetch('/rab/api/paket?bentangan='+bentangan);
        const d=await r.json();
        state.zonaId=d.zona.id;
        document.getElementById('zonaText').textContent=d.zona.nama+' — '+d.zona.deskripsi;
        document.getElementById('zonaInfo').classList.add('show');
        setRekomendasiMaterial(d.zona.id);
        document.getElementById('cardSpek').style.display='block';
    } catch(e) {}
}

const MATERIAL_FALLBACK = [
    {id:'f1', nama:'HG 4x6 1mm', harga:140000},
    {id:'f2', nama:'HG 4x8 1.2mm', harga:215000},
    {id:'f3', nama:'HG 5x10', harga:320000},
    {id:'f4', nama:'WF 100 (estimasi)', harga:1250000},
    {id:'f5', nama:'WF 150 (estimasi)', harga:1900000},
];

async function loadMaterialStruktur() {
    masterStruktur = [];

    // Sumber 1: endpoint khusus RAB
    try {
        const r = await fetch('/rab/api/material-struktur');
        if (r.ok) {
            const d = await r.json();
            if (Array.isArray(d) && d.length) masterStruktur = d;
        }
    } catch(e) {}

    // Sumber 2: endpoint master material global (modul project, sudah ada)
    if (!masterStruktur.length) {
        try {
            const keywords = ['hollow','hg','wf','kaso','pipa','besi'];
            const results = await Promise.all(keywords.map(k =>
                fetch('/api/material/search?q=' + k).then(r => r.ok ? r.json() : []).catch(() => [])
            ));
            const seen = {};
            results.forEach(d => {
                if (d && d.data) d = d.data;
                (d || []).forEach(m => {
                    const id = m.id;
                    if (!id || seen[id]) return;
                    seen[id] = true;
                    masterStruktur.push({
                        id: id,
                        nama: m.nama || m.nama_barang || m.name || 'Material',
                        harga: parseFloat(m.harga_pokok ?? m.harga ?? m.harga_satuan ?? 0),
                    });
                });
            });
            masterStruktur = masterStruktur.filter(m => m.harga > 0);
            masterStruktur.sort((a,b) => a.nama.localeCompare(b.nama));
        } catch(e) {}
    }

    // Sumber 3: fallback hardcode (harga real dari data lama)
    if (!masterStruktur.length) masterStruktur = MATERIAL_FALLBACK;
    isiDropdownMaterial();
}

function isiDropdownMaterial() {
    const opts = masterStruktur.map(m =>
        `<option value="${m.id}" data-harga="${m.harga}">${m.nama} — Rp ${Math.round(m.harga).toLocaleString('id-ID')}</option>`
    ).join('');
    ['selFrame','selGording','selTiang'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = opts;
    });
}

function cariMaterial(kw) {
    kw = kw.toLowerCase();
    return masterStruktur.find(m => (m.nama||'').toLowerCase().includes(kw));
}

function setRekomendasiMaterial(zonaId) {
    let frameKw='4x8', gordingKw='4x6', tiangKw='4x8';
    if (zonaId === 1)      { frameKw='4x6';  gordingKw='4x6';  tiangKw='4x6'; }
    else if (zonaId === 2) { frameKw='4x8';  gordingKw='4x6';  tiangKw='4x8'; }
    else if (zonaId === 3) { frameKw='5x10'; gordingKw='4x8';  tiangKw='5x10'; }
    else if (zonaId === 4) { frameKw='wf';   gordingKw='5x10'; tiangKw='wf'; }

    const setSel = (selId, kw, fb) => {
        const el = document.getElementById(selId);
        if (!el || !el.options.length) return;
        const m = cariMaterial(kw) || (fb ? cariMaterial(fb) : null);
        if (m) el.value = m.id;
    };
    setSel('selFrame', frameKw, '5x10');
    setSel('selGording', gordingKw, '4x6');
    setSel('selTiang', tiangKw, '5x10');

    const rekTiang = Math.max(2, Math.ceil(state.panjang / 3) + 1);
    document.getElementById('hintTiang').textContent = 'Rekomendasi: ' + rekTiang + ' titik (jarak max 3m)';
    document.getElementById('inpJmlTiang').placeholder = rekTiang;

    onSpekChange();
}

function getJarakRekomendasi() {
    const atap = masterAtap.find(a => a.id === state.atapId);
    if (!atap) return 80;
    if (atap.berat_kategori === 'berat') return 60;
    if (atap.berat_kategori === 'sedang') return 70;
    return 80;
}

function onSpekChange() {
    state.frameMatId   = document.getElementById('selFrame')?.value || null;
    state.gordingMatId = document.getElementById('selGording')?.value || null;
    state.tiangMatId   = document.getElementById('selTiang')?.value || null;
    const jv           = document.getElementById('selJarak')?.value || 'rekomendasi';
    state.jarakGording = jv === 'rekomendasi' ? getJarakRekomendasi() : parseFloat(jv);
    state.jumlahTiang  = parseInt(document.getElementById('inpJmlTiang')?.value) || null;
    state.tinggiTiang  = parseFloat(document.getElementById('inpTinggiTiang')?.value) || 3;
    cekStep3Complete();
    debounceHitung();
}

// ============================================================
// RENDER
// ============================================================
const PRODUK_ICON = {KANOPI_STD:'🏠',KANOPI_DINDING:'🧱',MEZZANINE:'🏗',PAGAR:'🚧',TRALIS:'🔒',TENDA_MEMBRANE:'⛺',AWNING:'☂',CARPORT:'🚗'};
function renderProduk(list) {
    document.getElementById('produkGrid').innerHTML = list.map(p =>
        `<div class="produk-btn" id="produkBtn_${p.kode}" onclick="pilihProduk('${p.kode}','${p.nama}',${p.is_estimasi_saja})">
            <div class="icon">${PRODUK_ICON[p.kode]||'📦'}</div>
            <div class="label">${p.nama}</div>
        </div>`
    ).join('');
}
function renderKatalog(list) {
    const g=document.getElementById('katalogGrid');
    if (!list.length) { g.innerHTML='<div style="color:#475569;font-size:12px;text-align:center;grid-column:1/-1;padding:20px">Belum ada katalog. Gunakan Jalur B.</div>'; return; }
    g.innerHTML=list.map(k=>`
        <div class="katalog-item" id="katalog_${k.id}" onclick="pilihKatalog(${k.id},'${k.atap_kode||''}','${k.nama}')">
            <img src="${k.foto_url}" onerror="this.src='https://via.placeholder.com/160x100/1e293b/475569?text=Foto'">
            <div class="katalog-info">
                <div class="katalog-nama">${k.judul}</div>
                <div class="katalog-harga">${formatKisaran(k.kisaran_harga_min,k.kisaran_harga_max)}</div>
            </div>
            <div class="katalog-check"><svg width="12" height="12" fill="none" stroke="#0f172a" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
        </div>`).join('');
}
function renderAtapGrid(cid) {
    document.getElementById(cid).innerHTML=masterAtap.map(a=>`
        <div class="atap-item" id="atap_${cid}_${a.id}" onclick="pilihAtap(${a.id},'${a.nama}','${a.berat_kategori}','${cid}')">
            <div class="atap-dot ${a.berat_kategori}"></div>
            <div><div class="atap-nama">${a.nama}</div><div class="atap-ket">${a.keterangan_customer||''}</div></div>
        </div>`).join('');
}

function renderAddonList() {
    const labels={talang:'Talang & Pembuangan',pembuangan:'Talang & Pembuangan',plafon:'Plafon',pencahayaan:'Lampu & Listrik',struktur:'Struktur Tambahan',dinding:'Dinding',finishing:'Finishing',lainnya:'Jasa Lainnya'};
    const done=new Set(); let html='';
    for(const [kat,items] of Object.entries(masterAddon)) {
        const lbl=labels[kat]||kat;
        if(done.has(lbl)) continue;
        done.add(lbl);
        html+=`<div class="addon-section-title">${lbl}</div>`;
        const all=kat==='talang'?[...items,...(masterAddon['pembuangan']||[])]:items;
        if(kat==='pembuangan') continue;
        html+=all.map(a=>`
            <div class="addon-item" id="addonItem_${a.id}" onclick="toggleAddon(${a.id},event)">
                <div class="addon-check"><svg width="10" height="10" fill="none" stroke="#0f172a" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="addon-info"><div class="addon-nama">${a.nama}</div><div class="addon-harga">Rp ${formatRibuan(a.harga_satuan)} / ${a.satuan}</div></div>
                <div class="addon-qty">
                    <input type="number" value="${a.qty_default||1}" min="0.1" step="0.5" onclick="event.stopPropagation()" oninput="updateAddonQty(${a.id},this.value)">
                    <span>${a.satuan}</span>
                </div>
            </div>`).join('');
    }
    document.getElementById('addonList').innerHTML=html;
}
function renderKondisiList() {
    document.getElementById('kondisiList').innerHTML=masterKondisi.map(k=>`
        <div class="kondisi-item" id="kondisiItem_${k.id}" onclick="toggleKondisi(${k.id})">
            <div class="addon-check"><svg width="10" height="10" fill="none" stroke="#0f172a" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="addon-info"><div class="addon-nama">${k.nama}</div><div class="addon-harga" style="color:#94a3b8">${k.deskripsi||''}</div></div>
            <span class="kondisi-tambah">${k.tipe==='flat_add'?'+ Rp '+formatRibuan(k.nilai):'+ '+k.nilai+'%'}</span>
        </div>`).join('');
}
function renderVersiGrid(versi) {
    const v = versi[0];
    const bom = state.kalkulasi?.bom;
    let bomHtml = '';
    if (bom) {
        bomHtml = `
        <div style="background:#0f172a;border-radius:8px;padding:12px;margin-top:10px">
            <div style="font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Rincian Material</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11px;color:#94a3b8">
                <div>Frame: <b style="color:#f1f5f9">${bom.frame.batang} batang</b></div>
                <div>Gording: <b style="color:#f1f5f9">${bom.gording.batang} batang</b> (${bom.gording.jalur} jalur @${bom.gording.jarak_cm}cm)</div>
                <div>Tiang: <b style="color:#f1f5f9">${bom.tiang.batang} batang</b> (${bom.tiang.titik} titik)</div>
                <div>Atap: <b style="color:#f1f5f9">${bom.atap.lembar} lembar</b> × ${bom.atap.panjang_lembar}m</div>
                <div>Estimasi kerja: <b style="color:#f1f5f9">${bom.upah.hari} hari</b></div>
                <div>Tim: <b style="color:#f1f5f9">1 tukang + 1 kenek</b></div>
            </div>
        </div>`;
    }
    document.getElementById('versiGrid').innerHTML = `
        <div style="grid-column:1/-1">
            <div class="versi-card active" style="text-align:left;padding:14px">
                <div class="versi-label">SESUAI SPEK PILIHAN</div>
                <div class="versi-harga" style="font-size:18px">${formatRp(v.harga_normal)}</div>
                <div class="versi-harga-kecil">${v.konstruksi||''}</div>
            </div>
            ${bomHtml}
        </div>`;
}

// ============================================================
// EVENT HANDLERS
// ============================================================
function pilihProduk(kode,nama,isEst) {
    state.produkKode=kode; state.produkNama=nama; state.isEstimasiKasar=isEst==1;
    document.querySelectorAll('.produk-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('produkBtn_'+kode)?.classList.add('active');
    document.getElementById('cardJalur').style.display='block';
    document.getElementById('btnStep1Next').disabled=false;
    if(isEst){pilihJalur('B');document.getElementById('jalurA').style.opacity='0.3';document.getElementById('jalurA').style.pointerEvents='none';}
    else{document.getElementById('jalurA').style.opacity='1';document.getElementById('jalurA').style.pointerEvents='auto';}
}
function pilihJalur(j) {
    state.jalur=j;
    document.getElementById('jalurA').classList.toggle('active',j==='A');
    document.getElementById('jalurB').classList.toggle('active',j==='B');
}
function pilihKatalog(id,atapKode,nama) {
    state.katalogId=id;
    document.querySelectorAll('.katalog-item').forEach(el=>el.classList.remove('active'));
    document.getElementById('katalog_'+id)?.classList.add('active');
    document.getElementById('btnKatalogNext').disabled=false;
    if(atapKode){const a=masterAtap.find(x=>x.kode===atapKode);if(a){state.atapId=a.id;state.atapNama=a.nama;}}
}
function pilihAtap(id,nama,berat,gid) {
    state.atapId=id; state.atapNama=nama;
    document.querySelectorAll('[id^="atap_"]').forEach(el=>el.classList.remove('active'));
    document.getElementById('atap_'+gid+'_'+id)?.classList.add('active');
    cekStep3Complete(); debounceHitung();
}

function onUkuranChange() {
    const p=parseFloat(document.getElementById('inpPanjang').value)||0;
    const l=parseFloat(document.getElementById('inpLebar').value)||0;
    state.panjang=p; state.lebar=l;
    if(!state.bentangan||state.bentangan===state.lebar) { state.bentangan=l; document.getElementById('inpBentangan').placeholder=l>0?l+' m (sama dengan lebar)':'= lebar jika tanpa tiang'; }
    const m2i=document.getElementById('m2Info');
    if(p>0&&l>0){document.getElementById('m2Value').textContent=(p*l).toFixed(2)+' m2';m2i.style.display='block';}
    else m2i.style.display='none';
    if(state.bentangan>0) loadPaket(state.bentangan);
    cekStep3Complete(); debounceHitung();
}
function onBentanganChange() {
    const b=parseFloat(document.getElementById('inpBentangan').value)||state.lebar;
    state.bentangan=b; if(b>0) loadPaket(b); debounceHitung();
}
function toggleAddon(id,e) {
    const item=document.getElementById('addonItem_'+id);
    const chk=item.classList.toggle('checked');
    if(chk){const q=item.querySelector('input[type=number]');state.addons[id]=parseFloat(q?.value||1);}
    else delete state.addons[id];
    debounceHitung();
}
function updateAddonQty(id,val) { if(state.addons[id]!==undefined){state.addons[id]=parseFloat(val)||1;debounceHitung();} }
function toggleKondisi(id) {
    document.getElementById('kondisiItem_'+id).classList.toggle('checked');
    const i=state.kondisis.indexOf(id);
    if(i===-1) state.kondisis.push(id); else state.kondisis.splice(i,1);
    debounceHitung();
}
function pilihVersi(label,hargaNormal,diskonMax) {
    state.versiDipilih=label; state.diskonMax=diskonMax||10;
    const versi=state.kalkulasi?.versi?.[0];
    state.hargaNormal = versi?.harga_target || hargaNormal;
    document.getElementById('hargaAwal').textContent=formatRp(state.hargaNormal);
    updateHasilInfo();
}
function cekStep3Complete() {
    const ok = state.panjang>0 && state.lebar>0 && state.atapId
        && state.frameMatId && state.gordingMatId && state.tiangMatId;
    document.getElementById('btnStep3Next').disabled = !ok;
}
function updateHasilInfo() {
    const versi=state.kalkulasi?.versi?.[0];
    if(!versi) return;
    document.getElementById('hasilKonstruksi').textContent=versi.konstruksi||'-';
    document.getElementById('hasilAtap').textContent=state.atapNama||'-';
    document.getElementById('hasilProduk').textContent=state.produkNama||'-';
    document.getElementById('hasilM2').textContent=(state.panjang*state.lebar).toFixed(2)+' m2';
    document.getElementById('ttdProduk').textContent=state.produkNama||'-';
}

// ============================================================
// HITUNG
// ============================================================
let hitungTimer=null;
function debounceHitung() { clearTimeout(hitungTimer); hitungTimer=setTimeout(hitungRealtimeUpdate,600); }
async function hitungRealtimeUpdate() {
    if(!state.panjang||!state.lebar||!state.produkKode) return;
    const fp=document.getElementById('floatPrice');
    fp.classList.add('price-loading');
    try {
        const r=await fetch('/rab/api/hitung',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':getCsrf()},body:JSON.stringify(buildInputBody())});
        const d=await r.json();
        if(d.success){
            state.kalkulasi=d.data;
            renderPeringatan(d.data);
            const std=d.data.versi[0];
            if(std){fp.textContent=formatRp(std.harga_normal);}
        }
    } catch(e){}
    fp.classList.remove('price-loading');
}
async function hitungDanLanjut() {
    if(!state.panjang||!state.lebar||!state.produkKode){alert('Lengkapi ukuran terlebih dahulu');return;}
    showLoading('Menghitung harga...');
    try {
        const r=await fetch('/rab/api/hitung',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':getCsrf()},body:JSON.stringify(buildInputBody())});
        const d=await r.json();
        if(d.success){
            state.kalkulasi=d.data;
            renderPeringatan(d.data);
            renderVersiGrid(d.data.versi);
            const std=d.data.versi[0];
            if(std) pilihVersi(std.label,std.harga_normal,std.diskon_max_persen||10);
            lanjutStep(5);
        } else alert('Gagal menghitung: '+(d.error||'Error'));
    } catch(e){alert('Gagal terhubung ke server');}
    hideLoading();
}
function renderPeringatan(data){
    const box=document.getElementById('peringatanHarga');
    const list=document.getElementById('peringatanList');
    if(!box||!list) return;
    const warns=(data&&data.peringatan)||[];
    const valid=!data||data.harga_valid!==false;
    if(!valid && warns.length){
        list.innerHTML=warns.map(w=>'• '+w).join('<br>');
        box.style.display='block';
    } else {
        box.style.display='none';
    }
}
function buildInputBody() {
    return {
        produk_kode: state.produkKode,
        panjang: state.panjang,
        lebar: state.lebar,
        bentangan: state.bentangan || state.lebar,
        atap_id: state.atapId,
        frame_material_id: state.frameMatId,
        gording_material_id: state.gordingMatId,
        tiang_material_id: state.tiangMatId,
        jarak_gording_cm: state.jarakGording || 80,
        jumlah_tiang: state.jumlahTiang,
        tinggi_tiang_m: state.tinggiTiang,
        addons: Object.entries(state.addons).map(([id,qty])=>({id:parseInt(id),qty})),
        kondisis: state.kondisis,
    };
}

// ============================================================
// NAVIGASI
// ============================================================
function lanjutStep(step) {
    if(step===2&&!state.produkKode){alert('Pilih jenis produk dulu');return;}
    currentStep=step;
    document.querySelectorAll('.step-section').forEach(s=>s.classList.remove('show'));
    if(step===1) document.getElementById('sec1').classList.add('show');
    else if(step===2){
        if(state.jalur==='A'){document.getElementById('sec2a').classList.add('show');loadKatalog(state.produkKode);document.getElementById('cardAtap').style.display='block';}
        else{document.getElementById('sec2b').classList.add('show');document.getElementById('cardAtap').style.display='none';}
    }
    else if(step===3){
        document.getElementById('sec3').classList.add('show');
        if(state.isEstimasiKasar) document.getElementById('mezzanineNotice').style.display='block';
        document.getElementById('priceBar').style.display='block';
    }
    else if(step===4){document.getElementById('sec4').classList.add('show');document.getElementById('priceBar').style.display='block';}
    else if(step===5){
        document.getElementById('sec5').classList.add('show');
        document.getElementById('priceBar').style.display='none';
        updateHasilInfo();
    }
    updateStepBar(step);
    window.scrollTo({top:0,behavior:'smooth'});
}
function backFromStep3() { lanjutStep(2); }
function updateStepBar(step) {
    for(let i=1;i<=5;i++){
        const d=document.getElementById('stepDot'+i);
        if(!d) continue;
        d.className='step';
        if(i<step) d.classList.add('done');
        else if(i===step) d.classList.add('active');
    }
}

// ============================================================
// TTD + DEAL
// ============================================================
let ttdCanvas,ttdCtx,isDrawing=false;
function showTtdModal(){
    if(state.kalkulasi && state.kalkulasi.harga_valid===false){
        alert('⚠️ Harga belum valid — masih ada material inti (rangka/gording/tiang/atap) yang belum lengkap. Lengkapi dulu sebelum deal.');
        return;
    }
    document.getElementById('ttdModal').style.display='block';initTtdCanvas();
}
function closeTtdModal(){document.getElementById('ttdModal').style.display='none';}
function initTtdCanvas(){
    ttdCanvas=document.getElementById('ttdCanvas');
    ttdCtx=ttdCanvas.getContext('2d');
    ttdCtx.strokeStyle='#fbbf24';ttdCtx.lineWidth=2.5;ttdCtx.lineCap='round';
    const getPos=e=>{const r=ttdCanvas.getBoundingClientRect(),sx=ttdCanvas.width/r.width,sy=ttdCanvas.height/r.height;return e.touches?[(e.touches[0].clientX-r.left)*sx,(e.touches[0].clientY-r.top)*sy]:[(e.clientX-r.left)*sx,(e.clientY-r.top)*sy];};
    ttdCanvas.onmousedown=ttdCanvas.ontouchstart=e=>{e.preventDefault();isDrawing=true;const[x,y]=getPos(e);ttdCtx.beginPath();ttdCtx.moveTo(x,y);};
    ttdCanvas.onmousemove=ttdCanvas.ontouchmove=e=>{e.preventDefault();if(!isDrawing)return;const[x,y]=getPos(e);ttdCtx.lineTo(x,y);ttdCtx.stroke();};
    ttdCanvas.onmouseup=ttdCanvas.ontouchend=()=>{isDrawing=false;};
}
function clearTtd(){ttdCtx?.clearRect(0,0,ttdCanvas.width,ttdCanvas.height);}
async function submitDeal(){
    const nama=document.getElementById('ttdNama').value.trim();
    if(!nama){alert('Isi nama penandatangan');return;}
    showLoading('Memproses deal...');
    try{
        const body=buildInputBody();
        body.versi_dipilih=state.versiDipilih;
        body.diskon_persen=state.diskonPersen;
        body.harga_final_deal=state.hargaFinal;
        body.bonus_dipilih=state.bonusDipilih||[];
        body.catatan=document.getElementById('catatanSurveyor').value;
        body.pipeline_lead_id=leadId;
        const sr=await fetch('/rab/simpan',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':getCsrf()},body:JSON.stringify(body)});
        const sd=await sr.json();
        if(!sd.success){hideLoading();alert('Gagal simpan RAB: '+(sd.error||''));return;}
        const dr=await fetch('/rab/'+sd.rab_id+'/deal',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':getCsrf()},body:JSON.stringify({nama_penandatangan:nama,ttd_data:ttdCanvas.toDataURL()})});
        const dd=await dr.json();
        hideLoading();
        if(dd.success){closeTtdModal();showSukses(dd,sd);}
        else alert('Deal gagal: '+(dd.error||''));
    }catch(e){hideLoading();alert('Gagal terhubung ke server');}
}
function showSukses(dd,sd){
    document.getElementById('sec5').innerHTML=`
        <div class="card" style="text-align:center;padding:32px 20px">
            <div style="font-size:48px;margin-bottom:12px">🎉</div>
            <div style="font-size:18px;font-weight:700;color:#22c55e;margin-bottom:8px">Deal Berhasil!</div>
            <div style="font-size:13px;color:#94a3b8;margin-bottom:20px">${sd.nomor||''}<br>Project terbuat. Notifikasi WA dikirim ke customer.</div>
            <div style="background:#0f172a;border-radius:8px;padding:14px;margin-bottom:20px;text-align:left">
                <div style="font-size:12px;color:#64748b;margin-bottom:4px">Total Deal</div>
                <div style="font-size:24px;font-weight:800;color:#fbbf24">${formatRp(state.hargaFinal)}</div>
            </div>
            <div style="display:grid;gap:8px">
                <a href="/projects/${dd.project_id}" class="btn btn-gold" style="display:block">Lihat Project →</a>
                <a href="${leadId?'/pipeline/'+leadId:'/rab'}" class="btn btn-outline" style="display:block">Kembali</a>
            </div>
        </div>`;
}
async function simpanDraft(){
    showLoading('Menyimpan draft...');
    try{
        const body=buildInputBody();
        body.versi_dipilih=state.versiDipilih;body.diskon_persen=state.diskonPersen;
        body.catatan=document.getElementById('catatanSurveyor').value;body.pipeline_lead_id=leadId;
        const r=await fetch('/rab/simpan',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':getCsrf()},body:JSON.stringify(body)});
        const d=await r.json();hideLoading();
        if(d.success){alert('Draft tersimpan: '+d.nomor);window.location.href='/rab/'+d.rab_id;}
    }catch(e){hideLoading();alert('Gagal');}
}
function kirimWaQuote(){
    const versi=state.kalkulasi?.versi?.[0];
    const pesan=encodeURIComponent('Halo, berikut penawaran dari Pusat Kanopi BSD:\n\nProduk: '+state.produkNama+'\nUkuran: '+state.panjang+'m x '+state.lebar+'m\nKonstruksi: '+(versi?.konstruksi||'-')+'\nAtap: '+(state.atapNama||'-')+'\nHarga: '+formatRp(state.hargaFinal||state.hargaNormal)+'\n\nHarga sudah termasuk pemasangan. Info lebih lanjut hubungi kami.');
    window.open('https://wa.me/?text='+pesan,'_blank');
}

// ============================================================
// UTILS
// ============================================================
function formatRp(n){if(!n)return'Rp 0';return'Rp '+Math.round(n).toLocaleString('id-ID');}
function formatRibuan(n){return Math.round(n||0).toLocaleString('id-ID');}
function formatKisaran(min,max){const f=n=>'Rp '+Math.round(n/1000000)+'jt';return min&&max?f(min)+' - '+f(max):'';}
function getCsrf(){return document.querySelector('meta[name="csrf-token"]')?.content||'';}
function showLoading(t){document.getElementById('loadingText').textContent=t;document.getElementById('loadingOverlay').classList.add('show');}
function hideLoading(){document.getElementById('loadingOverlay').classList.remove('show');}
</script>

@endsection