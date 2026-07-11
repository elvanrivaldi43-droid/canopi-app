@extends('layouts.app')
@section('title', 'Edit Luar Kota')

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
}
.karyawan-info {
    background: #0f172a; border-radius: 10px; padding: 12px 14px;
    margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
}
.karyawan-info-nama { font-size: 0.95rem; font-weight: 700; color: #f1f5f9; }
.karyawan-info-jabatan { font-size: 0.78rem; color: #64748b; }

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
textarea.form-control { min-height: 80px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:480px) { .form-row { grid-template-columns: 1fr; } }

.form-actions {
    display: flex; gap: 10px; justify-content: flex-end;
    margin-top: 20px; padding-top: 16px; border-top: 1px solid #334155;
}
.btn-submit {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 12px 28px; border-radius: 10px; font-weight: 700;
    font-size: 0.95rem; cursor: pointer;
}
.btn-cancel {
    background: #334155; color: #e2e8f0; border: none;
    padding: 12px 20px; border-radius: 10px; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center;
}
.error-msg { color: #f87171; font-size: 0.8rem; margin-top: 4px; }

/* Durasi preview */
.durasi-preview {
    background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.2);
    border-radius: 8px; padding: 10px 14px; margin-top: 8px;
    font-size: 0.85rem; color: #fbbf24; display: none;
}
.durasi-preview.show { display: block; }
</style>

<div class="container" style="max-width:560px; margin:0 auto; padding:16px;">
    <div style="margin-bottom:16px;">
        <a href="{{ route('luar-kota.index') }}" style="color:#94a3b8; font-size:0.85rem; text-decoration:none;">← Kembali</a>
    </div>

    <div class="form-card">
        <div class="form-title">Edit Jadwal Luar Kota</div>

        {{-- Info karyawan (readonly) --}}
        <div class="karyawan-info">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5" style="flex-shrink:0;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <div>
                <div class="karyawan-info-nama">{{ $luarKota->karyawan->name }}</div>
                <div class="karyawan-info-jabatan">{{ $luarKota->karyawan->jabatan }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('luar-kota.update', $luarKota->id) }}">
            @csrf
            @method('PUT')

            {{-- Lokasi --}}
            <div class="form-group">
                <label>Lokasi Project <span>*</span></label>
                <input type="text" name="lokasi" class="form-control"
                       value="{{ old('lokasi', $luarKota->lokasi) }}" required>
                @error('lokasi') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- Tanggal --}}
            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Mulai <span>*</span></label>
                    <input type="date" name="tanggal_mulai" id="tglMulai" class="form-control"
                           value="{{ old('tanggal_mulai', $luarKota->tanggal_mulai->toDateString()) }}"
                           oninput="hitungDurasi()" required>
                    @error('tanggal_mulai') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai <span>*</span></label>
                    <input type="date" name="tanggal_selesai" id="tglSelesai" class="form-control"
                           value="{{ old('tanggal_selesai', $luarKota->tanggal_selesai->toDateString()) }}"
                           oninput="hitungDurasi()" required>
                    @error('tanggal_selesai') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="durasi-preview show" id="durasiPreview">
                🗓️ Durasi: <strong id="durasiHari">{{ $luarKota->durasiHari() }} hari</strong>
            </div>

            {{-- Keterangan --}}
            <div class="form-group" style="margin-top:12px;">
                <label>Keterangan (opsional)</label>
                <textarea name="keterangan" class="form-control">{{ old('keterangan', $luarKota->keterangan) }}</textarea>
            </div>

            <div class="form-actions">
                <a href="{{ route('luar-kota.index') }}" class="btn-cancel">Batal</a>
                <button type="submit" class="btn-submit">Simpan &amp; Kirim WA</button>
            </div>
        </form>
    </div>
</div>

<script>
function hitungDurasi() {
    var mulai   = document.getElementById('tglMulai').value;
    var selesai = document.getElementById('tglSelesai').value;
    var preview = document.getElementById('durasiPreview');
    var label   = document.getElementById('durasiHari');

    if (mulai && selesai) {
        var d1 = new Date(mulai);
        var d2 = new Date(selesai);
        var diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
        if (diff > 0) {
            label.textContent = diff + ' hari';
            preview.classList.add('show');
        } else {
            label.textContent = '⚠️ Tanggal tidak valid';
        }
    }
}
</script>
@endsection