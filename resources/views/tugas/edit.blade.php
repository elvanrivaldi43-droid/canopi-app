@extends('layouts.app')
@section('title', 'Edit Tugas')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }

.form-card {
    background: #1e293b; border-radius: 16px;
    padding: 24px; border: 1px solid #334155;
    max-width: 680px; margin: 0 auto;
}
.form-title {
    font-size: 1.2rem; font-weight: 700; color: #fbbf24;
    margin: 0 0 24px 0; padding-bottom: 16px;
    border-bottom: 1px solid #334155;
}
.form-group { margin-bottom: 18px; }
.form-group > label {
    display: block; font-size: 0.85rem; color: #94a3b8;
    margin-bottom: 6px; font-weight: 500;
}
.form-group > label span { color: #ef4444; }
.form-control {
    width: 100%; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 12px 14px; font-size: 16px; cursor: pointer;
}
.form-control:focus { outline: none; border-color: #fbbf24; }
textarea.form-control { min-height: 90px; resize: vertical; cursor: text; }
select.form-control option { background: #0f172a; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }

/* Dropdown jam */
.jam-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.jam-select-wrap { display: flex; align-items: center; gap: 6px; }
.jam-select-wrap select {
    flex: 1; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 12px 10px; font-size: 16px; cursor: pointer;
}
.jam-select-wrap select:focus { outline: none; border-color: #fbbf24; }
.jam-sep { color: #475569; font-weight: 700; font-size: 1.1rem; }

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
    border: 2px solid #475569; background: transparent;
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    transition: all 0.15s; pointer-events: none;
}
.chip-check svg { display: none; pointer-events: none; }
.karyawan-chip.checked .chip-check svg { display: block; }
.chip-name { font-size: 0.85rem; font-weight: 600; color: #e2e8f0; pointer-events: none; }
.chip-jabatan { font-size: 0.72rem; color: #64748b; pointer-events: none; }

.select-all-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.btn-select-all {
    background: #334155; color: #e2e8f0; border: none;
    padding: 7px 14px; border-radius: 8px; font-size: 0.82rem; cursor: pointer;
}
.btn-select-all:hover { background: #475569; }

.form-actions {
    display: flex; gap: 10px; justify-content: flex-end;
    margin-top: 24px; padding-top: 16px; border-top: 1px solid #334155;
}
.btn-submit {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 12px 28px; border-radius: 10px; font-weight: 700;
    font-size: 0.95rem; cursor: pointer;
}
.btn-cancel {
    background: #334155; color: #e2e8f0; border: none;
    padding: 12px 20px; border-radius: 10px; font-size: 0.95rem;
    cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center;
}
.error-msg { color: #f87171; font-size: 0.8rem; margin-top: 4px; }
</style>

@php
    // Parse jam dari DB untuk dropdown
    $jamMulaiVal    = substr($tugas->jam_mulai ?? '', 0, 5);
    $jamSelesaiVal  = substr($tugas->jam_selesai_target ?? '', 0, 5);
    $jmJam  = $jamMulaiVal  ? explode(':', $jamMulaiVal)[0]  : '';
    $jmMnt  = $jamMulaiVal  ? explode(':', $jamMulaiVal)[1]  : '';
    $jsJam  = $jamSelesaiVal ? explode(':', $jamSelesaiVal)[0] : '';
    $jsMnt  = $jamSelesaiVal ? explode(':', $jamSelesaiVal)[1] : '';
@endphp

<div class="container" style="max-width:720px; margin:0 auto; padding:16px;">
    <div style="margin-bottom:16px;">
        <a href="{{ route('tugas.show', $tugas->id) }}" style="color:#94a3b8; font-size:0.85rem; text-decoration:none;">← Kembali</a>
    </div>

    <div class="form-card">
        <h2 class="form-title">Edit Tugas</h2>

        <form method="POST" action="{{ route('tugas.update', $tugas->id) }}" id="formEdit">
            @csrf
            @method('PUT')

            {{-- Judul --}}
            <div class="form-group">
                <label>Judul Tugas <span>*</span></label>
                <input type="text" name="judul" class="form-control"
                       value="{{ old('judul', $tugas->judul) }}" required>
                @error('judul') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- Tanggal & Prioritas --}}
            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal <span>*</span></label>
                    <input type="date" name="tanggal" class="form-control"
                           value="{{ old('tanggal', $tugas->tanggal->toDateString()) }}" required>
                </div>
                <div class="form-group">
                    <label>Prioritas <span>*</span></label>
                    <select name="prioritas" class="form-control" required>
                        <option value="sedang" {{ old('prioritas', $tugas->prioritas)=='sedang'?'selected':'' }}>🟡 Sedang</option>
                        <option value="tinggi" {{ old('prioritas', $tugas->prioritas)=='tinggi'?'selected':'' }}>🔴 Tinggi</option>
                        <option value="rendah" {{ old('prioritas', $tugas->prioritas)=='rendah'?'selected':'' }}>🟢 Rendah</option>
                    </select>
                </div>
            </div>

            {{-- Jam Mulai & Target Selesai — pakai dropdown --}}
            <div class="form-row">
                <div class="form-group">
                    <label>Jam Mulai (opsional)</label>
                    <div class="jam-select-wrap">
                        <select id="jm_jam" onchange="updateJamMulai()">
                            <option value="">--</option>
                            @for($h = 6; $h <= 21; $h++)
                            <option value="{{ str_pad($h,2,'0',STR_PAD_LEFT) }}"
                                {{ old('_jm_jam', $jmJam) == str_pad($h,2,'0',STR_PAD_LEFT) ? 'selected' : '' }}>
                                {{ str_pad($h,2,'0',STR_PAD_LEFT) }}
                            </option>
                            @endfor
                        </select>
                        <span class="jam-sep">:</span>
                        <select id="jm_mnt" onchange="updateJamMulai()">
                            <option value="">--</option>
                            @foreach(['00','15','30','45'] as $m)
                            <option value="{{ $m }}" {{ old('_jm_mnt', $jmMnt) == $m ? 'selected' : '' }}>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="jam_mulai" id="jam_mulai_val"
                           value="{{ old('jam_mulai', $jamMulaiVal) }}">
                </div>
                <div class="form-group">
                    <label>Target Selesai (opsional)</label>
                    <div class="jam-select-wrap">
                        <select id="js_jam" onchange="updateJamSelesai()">
                            <option value="">--</option>
                            @for($h = 6; $h <= 21; $h++)
                            <option value="{{ str_pad($h,2,'0',STR_PAD_LEFT) }}"
                                {{ old('_js_jam', $jsJam) == str_pad($h,2,'0',STR_PAD_LEFT) ? 'selected' : '' }}>
                                {{ str_pad($h,2,'0',STR_PAD_LEFT) }}
                            </option>
                            @endfor
                        </select>
                        <span class="jam-sep">:</span>
                        <select id="js_mnt" onchange="updateJamSelesai()">
                            <option value="">--</option>
                            @foreach(['00','15','30','45'] as $m)
                            <option value="{{ $m }}" {{ old('_js_mnt', $jsMnt) == $m ? 'selected' : '' }}>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="jam_selesai_target" id="jam_selesai_val"
                           value="{{ old('jam_selesai_target', $jamSelesaiVal) }}">
                </div>
            </div>

            {{-- Lokasi --}}
            <div class="form-group">
                <label>Lokasi (opsional)</label>
                <input type="text" name="lokasi" class="form-control"
                       value="{{ old('lokasi', $tugas->lokasi) }}"
                       placeholder="Contoh: Workshop, Alam Sutera Blok A3">
            </div>

            {{-- Deskripsi --}}
            <div class="form-group">
                <label>Deskripsi / Detail Tugas (opsional)</label>
                <textarea name="deskripsi" class="form-control">{{ old('deskripsi', $tugas->deskripsi) }}</textarea>
            </div>

            {{-- Pilih Karyawan --}}
            <div class="form-group">
                <label>Karyawan yang Ditugaskan <span>*</span></label>
                @error('karyawan_ids') <div class="error-msg">{{ $message }}</div> @enderror
                <div class="select-all-bar">
                    <button type="button" class="btn-select-all" onclick="pilihSemua()">Pilih Semua</button>
                    <button type="button" class="btn-select-all" onclick="batalSemua()">Batalkan Semua</button>
                    <span style="font-size:0.82rem; color:#94a3b8;" id="countLabel">0 dipilih</span>
                </div>
                <div class="karyawan-grid" id="karyawanGrid">
                    @foreach($karyawan as $k)
                    @php $isChecked = in_array($k->id, old('karyawan_ids', $assignedIds)); @endphp
                    <div class="karyawan-chip {{ $isChecked ? 'checked' : '' }}"
                         data-id="{{ $k->id }}">
                        <input type="checkbox" name="karyawan_ids[]"
                               value="{{ $k->id }}"
                               id="cb_{{ $k->id }}"
                               {{ $isChecked ? 'checked' : '' }}>
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

            <div class="form-actions">
                <a href="{{ route('tugas.show', $tugas->id) }}" class="btn-cancel">Batal</a>
                <button type="submit" class="btn-submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Chip karyawan ──────────────────────────────────────────
document.querySelectorAll('.karyawan-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        const cb = this.querySelector('input[type=checkbox]');
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

// ── Dropdown jam ──────────────────────────────────────────
function updateJamMulai() {
    var j = document.getElementById('jm_jam').value;
    var m = document.getElementById('jm_mnt').value;
    document.getElementById('jam_mulai_val').value = (j && m) ? j + ':' + m : '';
}

function updateJamSelesai() {
    var j = document.getElementById('js_jam').value;
    var m = document.getElementById('js_mnt').value;
    document.getElementById('jam_selesai_val').value = (j && m) ? j + ':' + m : '';
}
</script>
@endsection