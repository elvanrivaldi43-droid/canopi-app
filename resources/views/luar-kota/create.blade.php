@extends('layouts.app')
@section('title', 'Aktifkan Luar Kota')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }
.form-card {
    background: #1e293b; border-radius: 16px;
    padding: 24px; border: 1px solid #334155;
    max-width: 640px; margin: 0 auto;
}
.form-title {
    font-size: 1.15rem; font-weight: 700; color: #fbbf24;
    margin: 0 0 20px 0; padding-bottom: 14px;
    border-bottom: 1px solid #334155;
}
.form-group { margin-bottom: 16px; }
.form-group > label {
    display: block; font-size: 0.85rem; color: #94a3b8;
    margin-bottom: 6px; font-weight: 500;
}
.form-group > label span { color: #ef4444; }
.form-control {
    width: 100%; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 12px 14px; font-size: 16px;
}
.form-control:focus { outline: none; border-color: #fbbf24; }
textarea.form-control { min-height: 80px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:480px) { .form-row { grid-template-columns: 1fr; } }

/* Karyawan chips */
.karyawan-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: 8px; margin-top: 8px;
}
.karyawan-chip {
    display: flex; align-items: center; gap: 8px;
    background: #0f172a; border: 2px solid #334155;
    border-radius: 10px; padding: 10px 12px; cursor: pointer;
    transition: all 0.15s; user-select: none;
}
.karyawan-chip:hover { border-color: #475569; }
.karyawan-chip input[type=checkbox] { display: none; }
.karyawan-chip.checked { border-color: #fbbf24; background: rgba(251,191,36,0.1); }
.karyawan-chip.checked .chip-check { background: #fbbf24; border-color: #fbbf24; }
.chip-check {
    width: 20px; height: 20px; border-radius: 5px;
    border: 2px solid #475569; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s; pointer-events: none;
}
.chip-check svg { display: none; pointer-events: none; }
.karyawan-chip.checked .chip-check svg { display: block; }
.chip-name { font-size: 0.85rem; font-weight: 600; color: #e2e8f0; pointer-events: none; }
.chip-jabatan { font-size: 0.72rem; color: #64748b; pointer-events: none; }

.select-all-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.btn-select-all {
    background: #334155; color: #e2e8f0; border: none;
    padding: 7px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer;
}

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

.info-box {
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;
    font-size: 0.82rem; color: #f59e0b; line-height: 1.6;
}
</style>

<div class="container" style="max-width:680px; margin:0 auto; padding:16px;">
    <div style="margin-bottom:16px;">
        <a href="{{ route('luar-kota.index') }}" style="color:#94a3b8; font-size:0.85rem; text-decoration:none;">← Kembali</a>
    </div>

    <div class="form-card">
        <div class="form-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:8px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Aktifkan Mode Luar Kota
        </div>

        <div class="info-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Selama mode luar kota aktif, karyawan tetap bisa absen seperti biasa. GPS akan dicatat tapi tidak mempengaruhi validasi (tidak ditolak karena jauh).
        </div>

        <form method="POST" action="{{ route('luar-kota.store') }}">
            @csrf

            {{-- Pilih Karyawan --}}
            <div class="form-group">
                <label>Karyawan <span>*</span></label>
                @error('user_ids') <div class="error-msg">{{ $message }}</div> @enderror
                <div class="select-all-bar">
                    <button type="button" class="btn-select-all" onclick="pilihSemua()">Pilih Semua</button>
                    <button type="button" class="btn-select-all" onclick="batalSemua()">Batalkan</button>
                    <span style="font-size:0.82rem;color:#94a3b8;" id="countLabel">0 dipilih</span>
                </div>
                <div class="karyawan-grid">
                    @foreach($karyawan as $k)
                    <div class="karyawan-chip {{ in_array($k->id, old('user_ids', [])) ? 'checked' : '' }}"
                         data-id="{{ $k->id }}">
                        <input type="checkbox" name="user_ids[]" value="{{ $k->id }}"
                               {{ in_array($k->id, old('user_ids', [])) ? 'checked' : '' }}>
                        <div class="chip-check">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        <div>
                            <div class="chip-name">{{ $k->name }}</div>
                            <div class="chip-jabatan">{{ $k->jabatan }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Lokasi --}}
            <div class="form-group">
                <label>Lokasi Project <span>*</span></label>
                <input type="text" name="lokasi" class="form-control"
                       placeholder="Contoh: Tangerang Selatan, Bekasi, Bandung"
                       value="{{ old('lokasi') }}" required>
                @error('lokasi') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- Tanggal --}}
            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Mulai <span>*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control"
                           value="{{ old('tanggal_mulai', today()->toDateString()) }}" required>
                    @error('tanggal_mulai') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai <span>*</span></label>
                    <input type="date" name="tanggal_selesai" class="form-control"
                           value="{{ old('tanggal_selesai', today()->toDateString()) }}" required>
                    @error('tanggal_selesai') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- Keterangan --}}
            <div class="form-group">
                <label>Keterangan (opsional)</label>
                <textarea name="keterangan" class="form-control"
                          placeholder="Contoh: Project pemasangan kanopi 3 unit">{{ old('keterangan') }}</textarea>
            </div>

            <div class="form-actions">
                <a href="{{ route('luar-kota.index') }}" class="btn-cancel">Batal</a>
                <button type="submit" class="btn-submit">
                    Aktifkan &amp; Kirim WA
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.karyawan-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        var cb = this.querySelector('input[type=checkbox]');
        cb.checked = !cb.checked;
        this.classList.toggle('checked', cb.checked);
        updateCount();
    });
});
function pilihSemua() {
    document.querySelectorAll('.karyawan-chip').forEach(function(chip) {
        chip.classList.add('checked');
        chip.querySelector('input').checked = true;
    });
    updateCount();
}
function batalSemua() {
    document.querySelectorAll('.karyawan-chip').forEach(function(chip) {
        chip.classList.remove('checked');
        chip.querySelector('input').checked = false;
    });
    updateCount();
}
function updateCount() {
    var n = document.querySelectorAll('.karyawan-chip.checked').length;
    document.getElementById('countLabel').textContent = n + ' dipilih';
}
updateCount();
</script>
@endsection