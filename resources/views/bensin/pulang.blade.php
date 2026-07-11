@extends('layouts.app')
@section('title', 'Catat KM Pulang')

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
    font-size: 1.15rem; font-weight: 700; color: #22c55e;
    margin: 0 0 20px 0; padding-bottom: 14px;
    border-bottom: 1px solid #334155;
    display: flex; align-items: center; gap: 8px;
}
.summary-box {
    background: #0f172a; border-radius: 12px; padding: 16px;
    margin-bottom: 20px; border: 1px solid #334155;
}
.summary-row {
    display: flex; justify-content: space-between;
    font-size: 0.88rem; padding: 6px 0;
    border-bottom: 1px solid #1e293b;
}
.summary-row:last-child { border-bottom: none; }
.summary-label { color: #64748b; }
.summary-val { color: #e2e8f0; font-weight: 600; }

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
.form-control:focus { outline: none; border-color: #22c55e; }

.preview-km {
    background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.3);
    border-radius: 10px; padding: 14px; margin-top: 12px;
    display: none; text-align: center;
}
.preview-km.show { display: block; }
.preview-km-num { font-size: 1.6rem; font-weight: 800; color: #4ade80; }
.preview-km-label { font-size: 0.78rem; color: #64748b; margin-top: 2px; }

.btn-submit {
    width: 100%; background: #22c55e; color: #0f172a; border: none;
    padding: 14px; border-radius: 10px; font-weight: 700;
    font-size: 1rem; cursor: pointer; margin-top: 8px;
}
.btn-submit:hover { background: #16a34a; }
.btn-back { color: #94a3b8; font-size: 0.85rem; text-decoration: none; }
.error-msg { color: #f87171; font-size: 0.8rem; margin-top: 4px; }
</style>

<div class="container" style="max-width:560px; margin:0 auto; padding:16px;">
    <div style="margin-bottom:16px;">
        <a href="{{ route('bensin.riwayat') }}" class="btn-back">← Kembali</a>
    </div>

    <div class="form-card">
        <div class="form-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Catat KM — Sudah Pulang
        </div>

        {{-- Ringkasan perjalanan --}}
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Kendaraan</span>
                <span class="summary-val">{{ $log->kendaraan->nama }} ({{ $log->kendaraan->plat }})</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Tujuan</span>
                <span class="summary-val">{{ $log->tujuan }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Tanggal</span>
                <span class="summary-val">{{ $log->tanggal->format('d/m/Y') }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">KM Awal</span>
                <span class="summary-val">{{ number_format($log->km_awal, 1) }} km</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">BBM</span>
                <span class="summary-val">{{ $log->liter }} liter — Rp {{ number_format($log->nominal, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Standar konsumsi</span>
                <span class="summary-val">{{ $log->kendaraan->standar_km_per_liter }} km/liter</span>
            </div>
        </div>

        <form method="POST" action="{{ route('bensin.pulang.store', $log->id) }}">
            @csrf

            <div class="form-group">
                <label>KM Odometer Sekarang (Akhir) <span>*</span></label>
                <input type="number" name="km_akhir" id="inputKmAkhir" class="form-control"
                       placeholder="Contoh: 45318" step="0.1" min="{{ $log->km_awal }}"
                       value="{{ old('km_akhir') }}"
                       oninput="hitungKm()" required>
                @error('km_akhir') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="preview-km" id="previewKm">
                <div class="preview-km-num" id="kmTempuh">—</div>
                <div class="preview-km-label">km ditempuh</div>
                <div style="margin-top:10px; display:flex; justify-content:center; gap:20px;">
                    <div style="text-align:center;">
                        <div style="font-size:1.1rem; font-weight:700;" id="konsumsiAktual">—</div>
                        <div style="font-size:0.72rem; color:#64748b;">km/liter aktual</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.1rem; font-weight:700; color:#94a3b8;">{{ $log->kendaraan->standar_km_per_liter }}</div>
                        <div style="font-size:0.72rem; color:#64748b;">km/liter standar</div>
                    </div>
                </div>
                <div id="statusBoros" style="margin-top:10px; font-size:0.82rem;"></div>
            </div>

            <button type="submit" class="btn-submit">
                ✅ Simpan &amp; Selesai
            </button>
        </form>
    </div>
</div>

<script>
var kmAwal  = {{ $log->km_awal }};
var liter   = {{ $log->liter }};
var standar = {{ $log->kendaraan->standar_km_per_liter }};

function hitungKm() {
    var kmAkhir = parseFloat(document.getElementById('inputKmAkhir').value) || 0;
    var preview = document.getElementById('previewKm');

    if (kmAkhir > kmAwal) {
        var tempuh   = (kmAkhir - kmAwal).toFixed(1);
        var konsumsi = liter > 0 ? (tempuh / liter).toFixed(2) : 0;
        var boros    = parseFloat(konsumsi) < standar;

        document.getElementById('kmTempuh').textContent = tempuh + ' km';
        document.getElementById('konsumsiAktual').textContent = konsumsi + ' km/liter';
        document.getElementById('konsumsiAktual').style.color = boros ? '#f87171' : '#4ade80';

        var statusEl = document.getElementById('statusBoros');
        if (boros) {
            statusEl.innerHTML = '<span style="color:#f87171;">⚠️ Di bawah standar — owner akan mendapat notif WA</span>';
        } else {
            statusEl.innerHTML = '<span style="color:#4ade80;">✅ Konsumsi normal</span>';
        }

        preview.classList.add('show');
    } else {
        preview.classList.remove('show');
    }
}
</script>
@endsection