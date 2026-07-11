@extends('layouts.app')
@section('title', 'Catat BBM Berangkat')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }
.form-card {
    background: #1e293b; border-radius: 16px;
    padding: 24px; border: 1px solid #334155;
    max-width: 520px; margin: 0 auto;
}
.form-title {
    font-size: 1.15rem; font-weight: 700; color: #fbbf24;
    margin: 0 0 20px 0; padding-bottom: 14px;
    border-bottom: 1px solid #334155;
    display: flex; align-items: center; gap: 8px;
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block; font-size: 0.85rem; color: #94a3b8;
    margin-bottom: 6px; font-weight: 500;
}
.form-group label span { color: #ef4444; }
.form-control {
    width: 100%; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 12px 14px; font-size: 16px;
}
.form-control:focus { outline: none; border-color: #fbbf24; }
select.form-control { cursor: pointer; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:480px) { .form-row { grid-template-columns: 1fr; } }

.info-box {
    background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.3);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;
    font-size: 0.82rem; color: #fbbf24;
    display: flex; align-items: flex-start; gap: 8px;
}

.btn-submit {
    width: 100%; background: #fbbf24; color: #0f172a; border: none;
    padding: 14px; border-radius: 10px; font-weight: 700;
    font-size: 1rem; cursor: pointer; margin-top: 8px;
}
.btn-submit:hover { background: #f59e0b; }
.btn-back {
    color: #94a3b8; font-size: 0.85rem; text-decoration: none;
}
.error-msg { color: #f87171; font-size: 0.8rem; margin-top: 4px; }

/* Harga estimasi */
.estimasi-box {
    background: #0f172a; border-radius: 10px; padding: 12px 14px;
    margin-top: 12px; display: none;
}
.estimasi-box.show { display: block; }
.estimasi-label { font-size: 0.78rem; color: #64748b; }
.estimasi-nilai { font-size: 1rem; font-weight: 700; color: #fbbf24; }
</style>

<div class="container" style="max-width:560px; margin:0 auto; padding:16px;">
    <div style="margin-bottom:16px;">
        <a href="{{ route('bensin.riwayat') }}" class="btn-back">← Kembali</a>
    </div>

    @if(session('success'))
    <div style="background:rgba(34,197,94,.15); border:1px solid #22c55e; color:#4ade80; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.9rem;">
        {{ session('success') }}
    </div>
    @endif

    <div class="form-card">
        <div class="form-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Catat BBM — Berangkat
        </div>

        <div class="info-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Isi form ini sebelum berangkat. Setelah pulang, jangan lupa input KM akhir.
        </div>

        <form method="POST" action="{{ route('bensin.store') }}">
            @csrf

            <div class="form-group">
                <label>Kendaraan <span>*</span></label>
                <select name="kendaraan_id" class="form-control" required>
                    <option value="">-- Pilih Kendaraan --</option>
                    @foreach($kendaraan as $k)
                    <option value="{{ $k->id }}" {{ old('kendaraan_id') == $k->id ? 'selected' : '' }}>
                        {{ $k->nama }} ({{ $k->plat }})
                    </option>
                    @endforeach
                </select>
                @error('kendaraan_id') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Tanggal <span>*</span></label>
                <input type="date" name="tanggal" class="form-control"
                       value="{{ old('tanggal', today()->toDateString()) }}" required>
            </div>

            <div class="form-group">
                <label>Tujuan <span>*</span></label>
                <input type="text" name="tujuan" class="form-control"
                       placeholder="Contoh: Alam Sutera Blok A3 – Pasang Kanopi"
                       value="{{ old('tujuan') }}" required>
                @error('tujuan') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>KM Odometer Awal <span>*</span></label>
                <input type="number" name="km_awal" class="form-control"
                       placeholder="Contoh: 45230" step="0.1"
                       value="{{ old('km_awal') }}" required>
                @error('km_awal') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Jumlah BBM (liter) <span>*</span></label>
                    <input type="number" name="liter" id="inputLiter" class="form-control"
                           placeholder="Contoh: 10" step="0.01" min="0.1"
                           value="{{ old('liter') }}" oninput="hitungEstimasi()" required>
                    @error('liter') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Nominal BBM (Rp) <span>*</span></label>
                    <input type="number" name="nominal" id="inputNominal" class="form-control"
                           placeholder="Contoh: 100000" step="1000" min="1000"
                           value="{{ old('nominal') }}" oninput="hitungEstimasi()" required>
                    @error('nominal') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="estimasi-box" id="estimasiBox">
                <div class="estimasi-label">Harga per liter</div>
                <div class="estimasi-nilai" id="hargaPerLiter">—</div>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label>Catatan (opsional)</label>
                <input type="text" name="catatan" class="form-control"
                       placeholder="Contoh: BBM full tank"
                       value="{{ old('catatan') }}">
            </div>

            <button type="submit" class="btn-submit">
                ⛽ Catat &amp; Berangkat
            </button>
        </form>
    </div>
</div>

<script>
function hitungEstimasi() {
    var liter   = parseFloat(document.getElementById('inputLiter').value) || 0;
    var nominal = parseFloat(document.getElementById('inputNominal').value) || 0;
    var box     = document.getElementById('estimasiBox');
    var label   = document.getElementById('hargaPerLiter');

    if (liter > 0 && nominal > 0) {
        var harga = Math.round(nominal / liter);
        label.textContent = 'Rp ' + harga.toLocaleString('id-ID') + '/liter';
        box.classList.add('show');
    } else {
        box.classList.remove('show');
    }
}
</script>
@endsection