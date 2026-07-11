@extends('layouts.app')

@section('content')
<div style="padding:16px; max-width:600px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px;">
        <a href="{{ route('master-material.index') }}"
           style="color:#94a3b8; text-decoration:none; display:flex; align-items:center;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 style="color:#fbbf24; font-size:18px; font-weight:700; margin:0;">
                {{ isset($masterMaterial) ? 'Edit Material' : 'Tambah Material' }}
            </h1>
            <p style="color:#64748b; font-size:13px; margin:0;">
                {{ isset($masterMaterial) ? $masterMaterial->nama : 'Material baru untuk RAB' }}
            </p>
        </div>
    </div>

    {{-- Form --}}
    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:24px;">
        <form method="POST"
              action="{{ isset($masterMaterial) ? route('master-material.update', $masterMaterial) : route('master-material.store') }}">
            @csrf
            @if(isset($masterMaterial)) @method('PUT') @endif

            {{-- Nama --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                    Nama Material <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="nama" value="{{ old('nama', $masterMaterial->nama ?? '') }}"
                       placeholder="contoh: HG 5x10, Kaca 8mm, Cat Duco..."
                       required
                       style="width:100%; background:#0f172a; border:1px solid {{ $errors->has('nama') ? '#ef4444' : '#334155' }}; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
                @error('nama') <p style="color:#ef4444; font-size:12px; margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            {{-- Kategori --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                    Kategori <span style="color:#ef4444;">*</span>
                </label>
                <select name="kategori" required
                        style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none;">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($kategoriList as $k => $l)
                    <option value="{{ $k }}" {{ old('kategori', $masterMaterial->kategori ?? '') == $k ? 'selected' : '' }}>
                        {{ $l }}
                    </option>
                    @endforeach
                </select>
                @error('kategori') <p style="color:#ef4444; font-size:12px; margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            {{-- Sumber (POS / Luar) --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                    Sumber Harga <span style="color:#ef4444;">*</span>
                </label>
                <select name="sumber" required
                        style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none;">
                    <option value="luar" {{ old('sumber', $masterMaterial->sumber ?? 'luar') == 'luar' ? 'selected' : '' }}>Beli dari Luar (update manual)</option>
                    <option value="pos"  {{ old('sumber', $masterMaterial->sumber ?? '') == 'pos' ? 'selected' : '' }}>Pusat Besi / POS (auto-update nanti)</option>
                </select>
                <p style="color:#64748b; font-size:11px; margin-top:4px;">POS = barang tokomu sendiri (nanti ikut harga POS otomatis). Luar = beli dari distributor lain (harga kamu update manual).</p>
            </div>

            {{-- Satuan & Harga Pokok --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                        Satuan <span style="color:#ef4444;">*</span>
                    </label>
                    <select name="satuan" required
                            style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none;">
                        @foreach($satuanList as $s)
                        <option value="{{ $s }}" {{ old('satuan', $masterMaterial->satuan ?? 'pcs') == $s ? 'selected' : '' }}>
                            {{ $s }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                        Harga Pokok (Rp) <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="number" name="harga_pokok" value="{{ old('harga_pokok', $masterMaterial->harga_pokok ?? 0) }}"
                           min="0" required
                           style="width:100%; background:#0f172a; border:1px solid {{ $errors->has('harga_pokok') ? '#ef4444' : '#334155' }}; color:#fbbf24; padding:11px 14px; border-radius:8px; font-size:14px; font-weight:700; outline:none; box-sizing:border-box;">
                </div>
            </div>

            {{-- Kode (opsional) --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                    Kode <span style="color:#64748b; font-weight:400;">(opsional)</span>
                </label>
                <input type="text" name="kode" value="{{ old('kode', $masterMaterial->kode ?? '') }}"
                       placeholder="contoh: HG5X10"
                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
            </div>

            {{-- Keterangan --}}
            <div style="margin-bottom:24px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">
                    Keterangan <span style="color:#64748b; font-weight:400;">(opsional)</span>
                </label>
                <textarea name="keterangan" rows="2" placeholder="Catatan tambahan..."
                          style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; resize:vertical; box-sizing:border-box;">{{ old('keterangan', $masterMaterial->keterangan ?? '') }}</textarea>
            </div>

            {{-- Preview harga --}}
            <div id="previewHarga" style="background:#0f172a; border:1px solid #334155; border-radius:8px; padding:14px; margin-bottom:20px; display:none;">
                <p style="color:#64748b; font-size:12px; margin:0 0 6px;">Preview ke customer (asumsi margin 25%):</p>
                <p style="color:#10b981; font-size:16px; font-weight:700; margin:0;" id="hargaCustomerPreview">-</p>
            </div>

            {{-- Tombol --}}
            <div style="display:flex; gap:10px;">
                <button type="submit"
                        style="flex:1; background:#fbbf24; color:#0f172a; padding:12px; border-radius:8px; border:none; font-weight:700; font-size:15px; cursor:pointer;">
                    {{ isset($masterMaterial) ? 'Simpan Perubahan' : 'Tambah Material' }}
                </button>
                <a href="{{ route('master-material.index') }}"
                   style="flex:1; background:#334155; color:#94a3b8; padding:12px; border-radius:8px; font-weight:600; font-size:15px; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Preview harga customer saat user isi harga pokok
const inputHarga = document.querySelector('[name="harga_pokok"]');
const preview = document.getElementById('previewHarga');
const previewText = document.getElementById('hargaCustomerPreview');

inputHarga.addEventListener('input', function() {
    const pokok = parseInt(this.value) || 0;
    if (pokok > 0) {
        const customer = Math.round(pokok * 1.25);
        previewText.textContent = 'Rp ' + customer.toLocaleString('id-ID');
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});
// Trigger sekali saat load (untuk mode edit)
if (inputHarga.value > 0) inputHarga.dispatchEvent(new Event('input'));
</script>
@endsection