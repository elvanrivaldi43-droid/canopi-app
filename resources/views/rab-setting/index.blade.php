@extends('layouts.app')
@section('title', 'Pengaturan RAB')
@section('page-title', 'Pengaturan RAB')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.st-wrap { max-width:600px; margin:0 auto; padding:14px 12px 40px; }
.st-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.st-sub { font-size:12px; color:#64748b; margin:0 0 14px; }
.st-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:12px; }
.st-cardhead { font-size:13px; font-weight:700; color:#fbbf24; margin-bottom:10px; }
.st-field { margin-bottom:12px; }
.st-field label { display:block; font-size:12px; color:#94a3b8; margin-bottom:5px; }
.st-field input {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px;
    padding:11px 10px; color:#f1f5f9; font-size:14px; outline:none; min-height:48px; }
.st-field input:focus { border-color:#fbbf24; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:520px){ .grid2 { grid-template-columns:1fr; } }
.btn { border:none; border-radius:10px; padding:13px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
.btn-gold { background:#fbbf24; color:#0f172a; }
</style>

<div class="st-wrap">
    <h1 class="st-title">Pengaturan RAB</h1>
    <p class="st-sub">Nilai default yang dipakai mesin RAB. Batas diskon berlaku untuk semua produk. Angka ini masih data tes — sesuaikan saat kalibrasi.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">✅ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ url('/rab-setting') }}">
        @csrf

        <div class="st-card">
            <div class="st-cardhead">Diskon & Margin</div>
            <div class="grid2">
                <div class="st-field">
                    <label>Batas diskon maksimal (%) — semua produk</label>
                    <input type="number" name="diskon_max" step="0.1" min="0" max="99" value="{{ $s->diskon_max ?? 15 }}">
                </div>
                <div class="st-field">
                    <label>Margin default (%)</label>
                    <input type="number" name="margin_default" step="0.1" min="0" max="90" value="{{ $s->margin_default ?? 45 }}">
                </div>
            </div>
            <div style="font-size:11px;color:#64748b;">Diskon di atas batas ini akan butuh persetujuan owner (dibangun di langkah berikutnya).</div>
        </div>

        <div class="st-card">
            <div class="st-cardhead">Tingkat Layanan (%)</div>
            <div class="grid2">
                <div class="st-field">
                    <label>Hemat: potong harga (%)</label>
                    <input type="number" name="lay_hemat" step="0.1" min="0" max="50" value="{{ $s->lay_hemat ?? 5 }}">
                </div>
                <div class="st-field">
                    <label>Kilat: tambah harga (%)</label>
                    <input type="number" name="lay_kilat" step="0.1" min="0" max="100" value="{{ $s->lay_kilat ?? 10 }}">
                </div>
            </div>
        </div>

        <div class="st-card">
            <div class="st-cardhead">Tarif Operasional</div>
            <div class="grid2">
                <div class="st-field">
                    <label>Biaya transport / km</label>
                    <input type="number" name="tarif_km" min="0" value="{{ $s->tarif_km ?? 5000 }}">
                </div>
                <div class="st-field">
                    <label>Sewa genset / hari</label>
                    <input type="number" name="tarif_genset" min="0" value="{{ $s->tarif_genset ?? 150000 }}">
                </div>
                <div class="st-field">
                    <label>Hotel / orang / malam</label>
                    <input type="number" name="tarif_hotel" min="0" value="{{ $s->tarif_hotel ?? 0 }}">
                </div>
                <div class="st-field">
                    <label>Kontrakan (flat)</label>
                    <input type="number" name="tarif_kontrakan" min="0" value="{{ $s->tarif_kontrakan ?? 0 }}">
                </div>
                <div class="st-field">
                    <label>Uang makan / orang / hari</label>
                    <input type="number" name="tarif_makan" min="0" value="{{ $s->tarif_makan ?? 25000 }}">
                </div>
            </div>
        </div>

        <div class="st-card">
            <div class="st-cardhead">Bahan Pelengkap per m² (otomatis)</div>
            <div class="grid2">
                <div class="st-field">
                    <label>Consumable rangka / m² (kawat las + cat + gerinda digabung)</label>
                    <input type="number" name="consumable_rangka" min="0" value="{{ $s->consumable_rangka ?? 0 }}">
                </div>
                <div class="st-field">
                    <label>Consumable atap / m² (sealant + paku roofing + serat digabung)</label>
                    <input type="number" name="consumable_atap" min="0" value="{{ $s->consumable_atap ?? 0 }}">
                </div>
                <div class="st-field">
                    <label>Finishing standar / m² rangka (cat / duco dasar — melekat otomatis)</label>
                    <input type="number" name="finishing_standar" min="0" value="{{ $s->finishing_standar ?? 0 }}">
                </div>
                <div class="st-field">
                    <label>Powder coating / m² rangka (finishing premium — surveyor pilih per opsi)</label>
                    <input type="number" name="powder_coating" min="0" value="{{ $s->powder_coating ?? 0 }}">
                </div>
            </div>
            <div style="font-size:11px;color:#64748b;">Otomatis dikali luas tiap RAB — tidak perlu dicentang, tidak bisa lupa. Isi 0 kalau belum mau dihitung.</div>
        </div>

        <button type="submit" class="btn btn-gold">💾 Simpan Pengaturan</button>
    </form>
</div>
@endsection