@extends('layouts.app')

@section('content')
<div style="padding:16px; max-width:700px; margin:0 auto;">

    <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px;">
        <a href="{{ route('projects.index') }}" style="color:#94a3b8; text-decoration:none;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 style="color:#fbbf24; font-size:18px; font-weight:700; margin:0;">Buat Project Baru</h1>
            @if($lead)
            <p style="color:#64748b; font-size:13px; margin:0;">Dari lead: {{ $lead->nama_customer }}</p>
            @endif
        </div>
    </div>

    @if($lead)
    <div style="background:#1e3a5f; border:1px solid #3b82f6; border-radius:10px; padding:14px 16px; margin-bottom:20px;">
        <p style="color:#93c5fd; font-size:13px; margin:0 0 4px; font-weight:600;">Data dari Pipeline Survey:</p>
        <p style="color:#e2e8f0; font-size:14px; margin:0;">{{ $lead->nama_customer }} — {{ $lead->produk }} — Estimasi Rp {{ number_format($lead->estimasi_nilai ?? 0, 0, ',', '.') }}</p>
    </div>
    @endif

    <div style="background:#1e293b; border:1px solid #334155; border-radius:12px; padding:24px;">
        <form method="POST" action="{{ route('projects.store') }}">
            @csrf
            @if($lead)
            <input type="hidden" name="id_lead" value="{{ $lead->id }}">
            @endif

            {{-- Nama Customer --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Nama Customer *</label>
                <input type="text" name="nama_customer" value="{{ old('nama_customer', $lead->nama_customer ?? '') }}" required
                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
            </div>

            {{-- No HP & Jenis --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">No HP</label>
                    <input type="text" name="no_hp" value="{{ old('no_hp', $lead->no_hp ?? '') }}"
                           style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Jenis Project *</label>
                    <input type="text" name="jenis_project" value="{{ old('jenis_project', $lead->produk ?? '') }}" required
                           placeholder="Kanopi, Pagar, Tralis..."
                           style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
                </div>
            </div>

            {{-- Alamat --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Alamat Project</label>
                <textarea name="alamat_project" rows="2"
                          style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; resize:vertical; box-sizing:border-box;">{{ old('alamat_project', $lead->alamat ?? '') }}</textarea>
            </div>

            {{-- Nilai Kontrak --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Nilai Kontrak (Rp) *</label>
                <input type="number" name="nilai_kontrak" value="{{ old('nilai_kontrak', $lead->estimasi_nilai ?? 0) }}" min="0" required
                       style="width:100%; background:#0f172a; border:1px solid #334155; color:#fbbf24; padding:11px 14px; border-radius:8px; font-size:16px; font-weight:700; outline:none; box-sizing:border-box;">
            </div>

            {{-- Kondisi Kerja --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Kondisi Kerja</label>
                <select name="id_rate_kondisi" id="selectKondisi"
                        style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none;">
                    @foreach($rateKondisi as $r)
                    <option value="{{ $r->id }}"
                            data-multiplier="{{ $r->multiplier }}"
                            data-tukang="{{ number_format($r->rate_tukang_final, 0, ',', '.') }}"
                            data-kenek="{{ number_format($r->rate_kenek_final, 0, ',', '.') }}"
                            data-khusus="{{ $r->kode !== 'STD' ? '1' : '0' }}"
                            {{ old('id_rate_kondisi') == $r->id || $r->kode === 'STD' ? 'selected' : '' }}>
                        {{ $r->nama }}
                    </option>
                    @endforeach
                </select>
                {{-- Preview rate — HANYA OWNER --}}
                @if(auth()->user()->level == 1)
                <div id="previewRate" style="background:#0f172a; border:1px solid #334155; border-radius:8px; padding:12px 14px; margin-top:8px; font-size:13px; display:none;">
                    <span style="color:#94a3b8;">Rate Tukang: </span>
                    <span id="rateTukang" style="color:#fbbf24; font-weight:700;"></span>
                    <span style="color:#334155; margin:0 8px;">|</span>
                    <span style="color:#94a3b8;">Rate Kenek: </span>
                    <span id="rateKenek" style="color:#fbbf24; font-weight:700;"></span>
                    <span id="badgeKhusus" style="display:none; background:#7c3aed22; color:#a78bfa; padding:2px 8px; border-radius:20px; font-size:11px; margin-left:8px;">⚠️ Butuh Approval Owner</span>
                </div>
                @else
                <div id="previewRate" style="display:none;"></div>
                @endif
            </div>

            {{-- Tanggal --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Target Mulai</label>
                    <input type="date" name="tgl_mulai_target" value="{{ old('tgl_mulai_target') }}"
                           style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Target Selesai</label>
                    <input type="date" name="tgl_selesai_target" value="{{ old('tgl_selesai_target') }}"
                           style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;">
                </div>
            </div>

            {{-- Deskripsi --}}
            <div style="margin-bottom:24px;">
                <label style="display:block; color:#94a3b8; font-size:13px; margin-bottom:6px; font-weight:600;">Deskripsi / Catatan</label>
                <textarea name="deskripsi" rows="3" placeholder="Detail pekerjaan, catatan khusus..."
                          style="width:100%; background:#0f172a; border:1px solid #334155; color:#e2e8f0; padding:11px 14px; border-radius:8px; font-size:14px; outline:none; resize:vertical; box-sizing:border-box;">{{ old('deskripsi') }}</textarea>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit"
                        style="flex:1; background:#fbbf24; color:#0f172a; padding:12px; border-radius:8px; border:none; font-weight:700; font-size:15px; cursor:pointer;">
                    Buat Project
                </button>
                <a href="{{ route('projects.index') }}"
                   style="flex:1; background:#334155; color:#94a3b8; padding:12px; border-radius:8px; font-weight:600; font-size:15px; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const sel = document.getElementById('selectKondisi');
const preview = document.getElementById('previewRate');

function updatePreview() {
    const opt = sel.options[sel.selectedIndex];
    const tukang = opt.dataset.tukang;
    const kenek = opt.dataset.kenek;
    const khusus = opt.dataset.khusus === '1';
    document.getElementById('rateTukang').textContent = 'Rp ' + tukang + '/hari';
    document.getElementById('rateKenek').textContent = 'Rp ' + kenek + '/hari';
    document.getElementById('badgeKhusus').style.display = khusus ? 'inline' : 'none';
    preview.style.display = 'block';
}

sel.addEventListener('change', updatePreview);
updatePreview();
</script>
@endsection
