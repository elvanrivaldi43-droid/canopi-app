@extends('layouts.app')
@section('title', 'Kelola Add-on')
@section('page-title', 'Kelola Add-on')
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
.ad-wrap { padding:14px 12px 60px; }
.ad-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.ad-sub { font-size:12px; color:#64748b; margin:0 0 14px; max-width:760px; }
.ad-scroll { overflow-x:auto; background:#1e293b; border-radius:12px; padding:10px; }
table.ad { border-collapse:collapse; width:100%; min-width:920px; }
table.ad th { font-size:11px; color:#94a3b8; text-align:left; padding:6px 6px; border-bottom:1px solid #334155; white-space:nowrap; }
table.ad td { padding:4px 6px; border-bottom:1px solid #263349; }
table.ad input, table.ad select { background:#0f172a; border:1px solid #334155; border-radius:6px; padding:8px 6px; color:#f1f5f9; font-size:13px; width:100%; min-height:38px; }
table.ad input:focus, table.ad select:focus { border-color:#fbbf24; outline:none; }
.w-nama { min-width:180px; } .w-sat { width:90px; } .w-form { width:110px; } .w-lvl { width:95px; }
.w-num { width:110px; } .w-dur { width:80px; } .w-akt { width:50px; text-align:center; }
.ad-actions { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-grey { background:#334155; color:#e2e8f0; }
.hint { font-size:11px; color:#64748b; margin-top:8px; }
.grp { font-size:11px; font-weight:700; color:#fbbf24; text-transform:uppercase; }
</style>

<div class="ad-wrap">
    <h1 class="ad-title">Kelola Add-on</h1>
    <p class="ad-sub">Semua add-on di satu tempat — tidak perlu buka phpMyAdmin. <b>Durasi Fabrikasi/Instalasi</b> = kecepatan kerja per hari (mis. talang 5 = 5 meter/hari). Kosongkan durasi (0) untuk add-on ringan (lampu, stop kontak). Add-on <b>Flat/borongan</b> tidak pakai durasi (harga sudah termasuk semua).</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">✅ {{ session('success') }}</div>
    @endif

    <div class="ad-scroll">
        <table class="ad" id="tblAddon">
            <thead>
                <tr>
                    <th class="w-nama">Nama Add-on</th>
                    <th class="w-sat">Satuan</th>
                    <th class="w-form">Cara Hitung</th>
                    <th class="w-lvl">Kelompok</th>
                    <th class="w-num">Harga Jual</th>
                    <th class="w-num">Modal</th>
                    <th class="w-dur">Fab /hari</th>
                    <th class="w-dur">Inst /hari</th>
                    <th class="w-akt">Aktif</th>
                </tr>
            </thead>
            <tbody id="adBody">
                @foreach($rows as $r)
                <tr data-id="{{ $r->id }}">
                    <td class="w-nama"><input type="text" class="f-nama" value="{{ $r->nama }}"></td>
                    <td class="w-sat"><input type="text" class="f-sat" value="{{ $r->satuan }}"></td>
                    <td class="w-form">
                        <select class="f-form">
                            <option value="per_unit"  {{ $r->formula_type=='per_unit'?'selected':'' }}>per unit</option>
                            <option value="per_meter" {{ $r->formula_type=='per_meter'?'selected':'' }}>per meter</option>
                            <option value="per_m2"    {{ $r->formula_type=='per_m2'?'selected':'' }}>per m²</option>
                            <option value="flat"      {{ $r->formula_type=='flat'?'selected':'' }}>flat/borongan</option>
                        </select>
                    </td>
                    <td class="w-lvl">
                        <select class="f-lvl">
                            <option value="rangka" {{ $r->level=='rangka'?'selected':'' }}>rangka</option>
                            <option value="atap"   {{ $r->level=='atap'?'selected':'' }}>atap</option>
                            <option value="total"  {{ $r->level=='total'?'selected':'' }}>total</option>
                        </select>
                    </td>
                    <td class="w-num"><input type="number" class="f-jual" value="{{ $r->harga_satuan !== null ? rtrim(rtrim((string)$r->harga_satuan,'0'),'.') : '' }}"></td>
                    <td class="w-num"><input type="number" class="f-modal" value="{{ $r->harga_pokok_satuan !== null ? rtrim(rtrim((string)$r->harga_pokok_satuan,'0'),'.') : '' }}"></td>
                    <td class="w-dur"><input type="number" step="0.1" class="f-fab" value="{{ $r->durasi_fab !== null && $r->durasi_fab != 0 ? rtrim(rtrim((string)$r->durasi_fab,'0'),'.') : '' }}" placeholder="0"></td>
                    <td class="w-dur"><input type="number" step="0.1" class="f-inst" value="{{ $r->durasi_inst !== null && $r->durasi_inst != 0 ? rtrim(rtrim((string)$r->durasi_inst,'0'),'.') : '' }}" placeholder="0"></td>
                    <td class="w-akt"><input type="checkbox" class="f-akt" {{ $r->is_active ? 'checked' : '' }}></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="hint">Kosongkan Fab &amp; Inst untuk add-on tanpa durasi. Untuk yang berat: isi kecepatan kerja per hari sesuai satuan (talang per meter, dinding per m², dst).</div>

    <div class="ad-actions">
        <button type="button" class="btn btn-grey" onclick="tambahBaris()">+ Tambah Add-on</button>
        <button type="button" class="btn btn-gold" onclick="simpanSemua()">💾 Simpan Semua</button>
    </div>
</div>

<form id="frmAddon" method="POST" action="{{ url('/addon/simpan') }}" style="display:none">
    @csrf
    <div id="frmHidden"></div>
</form>

<script>
function barisBaru(){
    var tr=document.createElement('tr');
    tr.setAttribute('data-id','');
    tr.innerHTML='<td class="w-nama"><input type="text" class="f-nama" placeholder="nama add-on"></td>'+
        '<td class="w-sat"><input type="text" class="f-sat" placeholder="unit/meter/m2"></td>'+
        '<td class="w-form"><select class="f-form"><option value="per_unit">per unit</option><option value="per_meter">per meter</option><option value="per_m2">per m²</option><option value="flat">flat/borongan</option></select></td>'+
        '<td class="w-lvl"><select class="f-lvl"><option value="rangka">rangka</option><option value="atap">atap</option><option value="total" selected>total</option></select></td>'+
        '<td class="w-num"><input type="number" class="f-jual" placeholder="0"></td>'+
        '<td class="w-num"><input type="number" class="f-modal" placeholder="0"></td>'+
        '<td class="w-dur"><input type="number" step="0.1" class="f-fab" placeholder="0"></td>'+
        '<td class="w-dur"><input type="number" step="0.1" class="f-inst" placeholder="0"></td>'+
        '<td class="w-akt"><input type="checkbox" class="f-akt" checked></td>';
    return tr;
}
function tambahBaris(){
    document.getElementById('adBody').appendChild(barisBaru());
}
function val(tr, sel){ var e=tr.querySelector(sel); return e ? e.value : ''; }
function simpanSemua(){
    var trs=[].slice.call(document.querySelectorAll('#adBody tr'));
    var hidden=document.getElementById('frmHidden');
    hidden.innerHTML='';
    for(var i=0;i<trs.length;i++){
        var tr=trs[i];
        var nama=val(tr,'.f-nama').trim();
        if(nama===''){ continue; }
        var akt=tr.querySelector('.f-akt');
        var data={
            id: tr.getAttribute('data-id') || '',
            nama: nama,
            satuan: val(tr,'.f-sat'),
            formula_type: val(tr,'.f-form'),
            level: val(tr,'.f-lvl'),
            harga_satuan: val(tr,'.f-jual'),
            harga_pokok_satuan: val(tr,'.f-modal'),
            durasi_fab: val(tr,'.f-fab'),
            durasi_inst: val(tr,'.f-inst'),
            is_active: (akt && akt.checked) ? '1' : ''
        };
        for(var k in data){
            if(!data.hasOwnProperty(k)) continue;
            var inp=document.createElement('input');
            inp.type='hidden'; inp.name='rows['+i+']['+k+']'; inp.value=data[k];
            hidden.appendChild(inp);
        }
    }
    document.getElementById('frmAddon').submit();
}
</script>
@endsection