@extends('layouts.app')
@section('title', 'Kelola Tahap Produksi')
@section('page-title', 'Kelola Tahap Produksi')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tm-wrap { padding:14px 12px 60px; }
.tm-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.tm-sub { font-size:12px; color:#64748b; margin:0 0 14px; max-width:760px; }
.tm-scroll { overflow-x:auto; background:#1e293b; border-radius:12px; padding:10px; }
table.tm { border-collapse:collapse; width:100%; min-width:760px; }
table.tm th { font-size:11px; color:#94a3b8; text-align:left; padding:6px; border-bottom:1px solid #334155; white-space:nowrap; }
table.tm td { padding:4px 6px; border-bottom:1px solid #263349; }
table.tm input, table.tm select { background:#0f172a; border:1px solid #334155; border-radius:6px; padding:8px 6px; color:#f1f5f9; font-size:13px; width:100%; min-height:38px; }
table.tm input:focus, table.tm select:focus { border-color:#fbbf24; outline:none; }
.w-nama { min-width:180px; } .w-tipe { width:100px; } .w-jk { min-width:200px; } .w-urut { width:80px; } .w-akt { width:60px; text-align:center; }
.tm-actions { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-gold { background:#fbbf24; color:#0f172a; }
</style>

<div class="tm-wrap">
    <h1 class="tm-title">Kelola Tahap Produksi</h1>
    <p class="tm-sub">Daftar jenis tahap kerja (potong, las, cat, kirim, instal, dll). Tautkan opsional ke "Jenis Kerja RAB" biar Fase 2 nanti bisa hitung saran jumlah orang otomatis — kosongkan kalau tahap ini cuma checklist manual.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('tahap-master.simpan') }}">
        @csrf
        <div class="tm-scroll">
            <table class="tm" id="tblTahap">
                <thead>
                    <tr>
                        <th class="w-nama">Nama Tahap</th>
                        <th class="w-tipe">Tipe</th>
                        <th class="w-jk">Jenis Kerja RAB (opsional)</th>
                        <th class="w-urut">Urutan</th>
                        <th class="w-akt">Aktif</th>
                    </tr>
                </thead>
                <tbody id="tmBody">
                    @foreach($rows as $i => $r)
                    <tr data-id="{{ $r->id }}">
                        <td class="w-nama">
                            <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $r->id }}">
                            <input type="text" name="rows[{{ $i }}][nama]" value="{{ $r->nama }}">
                        </td>
                        <td class="w-tipe">
                            <select name="rows[{{ $i }}][tipe]">
                                <option value="">-</option>
                                <option value="fab"  {{ $r->tipe=='fab'?'selected':'' }}>Fabrikasi</option>
                                <option value="inst" {{ $r->tipe=='inst'?'selected':'' }}>Instalasi</option>
                            </select>
                        </td>
                        <td class="w-jk">
                            <select name="rows[{{ $i }}][rab_jenis_kerja_id]">
                                <option value="">- tidak ditautkan -</option>
                                @foreach($jenisKerjaOptions as $jk)
                                <option value="{{ $jk->id }}" {{ $r->rab_jenis_kerja_id==$jk->id?'selected':'' }}>{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="w-urut"><input type="number" name="rows[{{ $i }}][urutan]" value="{{ $r->urutan }}"></td>
                        <td class="w-akt"><input type="checkbox" name="rows[{{ $i }}][is_active]" value="1" {{ $r->is_active?'checked':'' }}></td>
                    </tr>
                    @endforeach
                    <tr data-id="0">
                        <td class="w-nama">
                            <input type="hidden" name="rows[baru][id]" value="0">
                            <input type="text" name="rows[baru][nama]" placeholder="Nama tahap baru...">
                        </td>
                        <td class="w-tipe">
                            <select name="rows[baru][tipe]">
                                <option value="">-</option>
                                <option value="fab">Fabrikasi</option>
                                <option value="inst">Instalasi</option>
                            </select>
                        </td>
                        <td class="w-jk">
                            <select name="rows[baru][rab_jenis_kerja_id]">
                                <option value="">- tidak ditautkan -</option>
                                @foreach($jenisKerjaOptions as $jk)
                                <option value="{{ $jk->id }}">{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="w-urut"><input type="number" name="rows[baru][urutan]" value="99"></td>
                        <td class="w-akt"><input type="checkbox" name="rows[baru][is_active]" value="1" checked></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="tm-actions">
            <button type="submit" class="btn btn-gold">Simpan Semua</button>
        </div>
    </form>
</div>
@endsection
