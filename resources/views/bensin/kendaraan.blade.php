@extends('layouts.app')
@section('title', 'Master Kendaraan')

@section('content')
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; }
.page-title { font-size: 1.2rem; font-weight: 700; color: #fbbf24; margin: 0 0 20px 0; }
.card { background: #1e293b; border-radius: 14px; padding: 20px; border: 1px solid #334155; margin-bottom: 16px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }
.form-control {
    width: 100%; background: #0f172a; color: #e2e8f0;
    border: 1px solid #334155; border-radius: 10px;
    padding: 11px 14px; font-size: 16px;
}
.form-control:focus { outline: none; border-color: #fbbf24; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:480px) { .form-row { grid-template-columns: 1fr; } }
.btn-submit {
    background: #fbbf24; color: #0f172a; border: none;
    padding: 11px 24px; border-radius: 10px; font-weight: 700;
    font-size: 0.9rem; cursor: pointer;
}

/* Tabel kendaraan */
.kend-card {
    background: #0f172a; border-radius: 12px; padding: 14px 16px;
    margin-bottom: 10px; border: 1px solid #334155;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap;
}
.kend-info { flex: 1; }
.kend-nama { font-size: 0.95rem; font-weight: 700; color: #f1f5f9; }
.kend-meta { font-size: 0.78rem; color: #64748b; margin-top: 3px; }
.kend-actions { display: flex; gap: 8px; align-items: center; }
.btn-sm {
    background: #334155; color: #e2e8f0; border: none;
    padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer;
}
.btn-sm:hover { background: #475569; }
.btn-nonaktif { background: rgba(239,68,68,.15); color: #f87171; }
.btn-aktif { background: rgba(34,197,94,.15); color: #4ade80; }
.badge-aktif { background: rgba(34,197,94,.15); color: #4ade80; padding: 3px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
.badge-nonaktif { background: rgba(100,116,139,.15); color: #64748b; padding: 3px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
.alert-success {
    background: rgba(34,197,94,.15); border: 1px solid #22c55e;
    color: #4ade80; padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-size: 0.9rem;
}
.btn-back { color: #94a3b8; font-size: 0.85rem; text-decoration: none; display: inline-block; margin-bottom: 16px; }
</style>

<div class="container" style="max-width:680px; margin:0 auto; padding:16px;">
    <a href="{{ route('bensin.index') }}" class="btn-back">← Kembali ke Rekap</a>

    @if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
    @endif

    <h1 class="page-title">Master Kendaraan</h1>

    {{-- Form tambah kendaraan --}}
    <div class="card">
        <div style="font-size:0.95rem; font-weight:700; color:#fbbf24; margin-bottom:14px;">Tambah Kendaraan Baru</div>
        <form method="POST" action="{{ route('bensin.kendaraan.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Kendaraan</label>
                    <input type="text" name="nama" class="form-control" placeholder="Suzuki Carry SS T120" required>
                </div>
                <div class="form-group">
                    <label>Plat Nomor</label>
                    <input type="text" name="plat" class="form-control" placeholder="B 1234 XYZ" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Jenis</label>
                    <select name="jenis" class="form-control">
                        <option value="Pickup">Pickup</option>
                        <option value="Mobil">Mobil</option>
                        <option value="Motor">Motor</option>
                        <option value="Truk">Truk</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Standar Konsumsi (km/liter)</label>
                    <input type="number" name="standar_km_per_liter" class="form-control"
                           placeholder="9" step="0.5" min="1" value="9" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">Tambah Kendaraan</button>
        </form>
    </div>

    {{-- Daftar kendaraan --}}
    <div style="font-size:0.9rem; font-weight:700; color:#94a3b8; margin-bottom:10px;">
        Daftar Kendaraan ({{ $daftar->count() }})
    </div>

    @forelse($daftar as $k)
    <div class="kend-card">
        <div class="kend-info">
            <div class="kend-nama">{{ $k->nama }}</div>
            <div class="kend-meta">
                {{ $k->plat }} · {{ $k->jenis }} · Standar: {{ $k->standar_km_per_liter }} km/liter
            </div>
        </div>
        <div class="kend-actions">
            <span class="{{ $k->is_active ? 'badge-aktif' : 'badge-nonaktif' }}">
                {{ $k->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
            <button class="btn-sm" onclick="toggleEdit({{ $k->id }})">Edit</button>
            <form method="POST" action="{{ route('bensin.kendaraan.toggle', $k->id) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-sm {{ $k->is_active ? 'btn-nonaktif' : 'btn-aktif' }}">
                    {{ $k->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>
        </div>

        {{-- Form edit (tersembunyi) --}}
        <div id="edit-{{ $k->id }}" style="display:none; width:100%; margin-top:12px; padding-top:12px; border-top:1px solid #334155;">
            <form method="POST" action="{{ route('bensin.kendaraan.update', $k->id) }}">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="{{ $k->nama }}" required>
                    </div>
                    <div class="form-group">
                        <label>Plat</label>
                        <input type="text" name="plat" class="form-control" value="{{ $k->plat }}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis</label>
                        <select name="jenis" class="form-control">
                            @foreach(['Pickup','Mobil','Motor','Truk'] as $j)
                            <option value="{{ $j }}" {{ $k->jenis == $j ? 'selected' : '' }}>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Standar (km/liter)</label>
                        <input type="number" name="standar_km_per_liter" class="form-control"
                               value="{{ $k->standar_km_per_liter }}" step="0.5" min="1" required>
                    </div>
                </div>
                <button type="submit" class="btn-submit" style="font-size:0.85rem; padding:9px 18px;">Simpan</button>
                <button type="button" class="btn-sm" onclick="toggleEdit({{ $k->id }})" style="margin-left:6px;">Batal</button>
            </form>
        </div>
    </div>
    @empty
    <div style="text-align:center; padding:30px; color:#475569;">Belum ada kendaraan.</div>
    @endforelse
</div>

<script>
function toggleEdit(id) {
    var el = document.getElementById('edit-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endsection