@extends('layouts.app')
@section('title', 'RAB Multi-Opsi')
@section('page-title', 'RAB Multi-Opsi')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.ro-wrap { max-width:1000px; margin:0 auto; padding:14px 12px 150px; }
.ro-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 4px; }
.ro-sub { font-size:12px; color:#64748b; margin:0 0 14px; }
.ro-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:12px; }
.ro-field label { display:block; font-size:11px; color:#94a3b8; margin-bottom:4px; }
.ro-field input, .ro-field select {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px;
    padding:11px 10px; color:#f1f5f9; font-size:14px; outline:none; min-height:48px; }
.ro-field input:focus, .ro-field select:focus { border-color:#fbbf24; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:520px){ .grid2 { grid-template-columns:1fr; } }
.btn { border:none; border-radius:10px; padding:13px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-grey { background:#334155; color:#e2e8f0; }
.btn-blue { background:#3b82f6; color:#fff; }
/* tabs opsi */
.tabbar { display:flex; gap:6px; overflow-x:auto; padding:4px 0 8px; -webkit-overflow-scrolling:touch; }
.tab { flex:none; background:#0f172a; border:1px solid #334155; color:#cbd5e1; border-radius:10px 10px 0 0;
       padding:10px 14px; min-height:44px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.tab.act { background:#fbbf24; color:#0f172a; border-color:#fbbf24; }
.tab-add { flex:none; background:#334155; color:#e2e8f0; border:none; border-radius:10px; padding:10px 14px; min-height:44px; font-size:13px; font-weight:700; cursor:pointer; }
.opsi-pane { display:none; }
.opsi-pane.act { display:block; }
.opsi-bar { display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap; }
.opsi-bar .nm { flex:1; min-width:140px; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; font-weight:700; min-height:46px; }
.ckf { font-size:13px; color:#cbd5e1; display:flex; align-items:center; gap:6px; cursor:pointer; }
.ckf input { width:20px; height:20px; }
.row3 { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
.row3 select, .row3 input { background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; min-height:46px; }
.row3 .del { background:#7f1d1d; color:#fff; border:none; border-radius:8px; width:46px; min-height:46px; cursor:pointer; flex:none; }
.blok-card { background:#1e293b; border:1px solid #334155; border-radius:12px; margin-bottom:12px; overflow:hidden; }
.blok-card.off { opacity:.5; }
.blok-head { display:flex; align-items:center; gap:8px; padding:10px 12px; background:#0f172a; }
.blok-head .b-nama { flex:1; min-width:80px; background:#1e293b; border:1px solid #334155; border-radius:8px; padding:9px; color:#f1f5f9; font-size:13px; font-weight:700; min-height:42px; }
.tag { font-size:10px; font-weight:700; padding:3px 7px; border-radius:6px; background:#fbbf24; color:#0f172a; flex:none; }
.tag.manual { background:#a78bfa; }
.tag.denah { background:#38bdf8; }
.blok-body { padding:12px; }
.subhead { font-size:12px; color:#fbbf24; margin:12px 0 8px; }
.iconbtn { background:none; border:none; cursor:pointer; font-size:17px; padding:4px; }
.sw { position:relative; width:44px; height:26px; flex:none; }
.sw input { opacity:0; width:0; height:0; }
.sw .sl { position:absolute; inset:0; background:#475569; border-radius:26px; transition:.2s; }
.sw .sl:before { content:''; position:absolute; width:20px; height:20px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; }
.sw input:checked + .sl { background:#22c55e; }
.sw input:checked + .sl:before { transform:translateX(18px); }
.sum-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #334155; font-size:13px; }
.sum-row b { color:#fbbf24; }
/* banding */
.cmp { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
@media (max-width:720px){ .cmp { grid-template-columns:1fr; } }
.cmp-col { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:12px; }
.cmp-col h3 { font-size:14px; color:#fbbf24; margin:0 0 8px; }
.cmp-harga { font-size:20px; font-weight:800; color:#fbbf24; margin:8px 0; }
.actbar { position:fixed; left:0; right:0; bottom:0; background:#0f172acc; backdrop-filter:blur(8px); border-top:1px solid #334155; padding:10px 12px; z-index:50; }
.actbar-in { max-width:1000px; margin:0 auto; display:flex; gap:8px; }
</style>

<div class="ro-wrap">
    <h1 class="ro-title">RAB Multi-Opsi</h1>
    <p class="ro-sub">Wizard 3 langkah: isi item RAB → finalisasi (durasi, nginap, layanan) → harga keluar.</p>
    <div style="display:flex;gap:6px;margin-bottom:14px">
        <div id="wzDot1" style="flex:1;text-align:center;padding:8px;border-radius:8px;background:#fbbf24;color:#0f172a;font-size:12px;font-weight:700">1. Item RAB</div>
        <div id="wzDot2" style="flex:1;text-align:center;padding:8px;border-radius:8px;background:#334155;color:#cbd5e1;font-size:12px;font-weight:700">2. Finalisasi</div>
        <div id="wzDot3" style="flex:1;text-align:center;padding:8px;border-radius:8px;background:#334155;color:#cbd5e1;font-size:12px;font-weight:700">3. Harga</div>
    </div>

    @if(isset($lead) && $lead)
    <div class="ro-card" style="border:1px solid #fbbf24">
        <div style="font-size:13px;color:#fbbf24;font-weight:700">RAB untuk Lead #{{ $lead->id }} — {{ $lead->nama_customer }}</div>
        <div style="font-size:12px;color:#94a3b8;margin-top:4px">
            {{ $lead->produk ?? '-' }}@if(!empty($lead->atap_diminati)) · atap: {{ $lead->atap_diminati }}@endif @if(!empty($lead->no_hp)) · {{ $lead->no_hp }}@endif
        </div>
        @if($lihatHarga)
        <div style="font-size:12px;color:#cbd5e1;margin-top:6px" id="leadInfoHarga">
            @if(!empty($lead->estimasi_max))Estimasi admin: Rp {{ number_format($lead->estimasi_min ?? 0,0,',','.') }}–{{ number_format($lead->estimasi_max,0,',','.') }}@else Belum ada estimasi admin.@endif
            @if(!empty($lead->harga_final)) · Harga final tersimpan: Rp {{ number_format($lead->harga_final,0,',','.') }}@endif
        </div>
        <div style="font-size:11px;color:#64748b;margin-top:6px">Setelah Bandingkan, tiap opsi punya tombol untuk disimpan ke lead ini sebagai Estimasi (admin) atau Harga Final (surveyor).</div>
        @endif
    </div>
    @endif

    <div class="wz-step" id="stepD" style="display:none">
    <div class="ro-card" id="ringkasOpsiCard" style="display:none">
        <div id="ringkasOpsi"></div>
        <div style="font-size:11px;color:#64748b;margin-top:4px">Hari &amp; tim dihitung otomatis dari mesin (ukuran ÷ kecepatan). Angka <b>Instalasi</b> tiap opsi BISA DIKOREKSI kalau kondisi lapangan bikin lebih lama — nginap/makan/genset opsi itu ikut menyesuaikan.</div>
    </div>
    <div class="ro-card">
        <div class="grid2">
            <div class="ro-field"><label>Nama Project / Customer</label><input type="text" id="projNama" placeholder="mis. Rumah Pak Budi"></div>
            @if($lihatModal)
            <div class="ro-field"><label>Margin (%)</label><input type="number" id="projMargin" value="{{ $setting->margin_default ?? 45 }}" min="0" max="90"></div>
            @else
            <input type="hidden" id="projMargin" value="{{ $setting->margin_default ?? 45 }}">
            @endif
        </div>
    </div>

    @if($lihatHarga)
    <div class="ro-card">
        <div style="font-size:14px;color:#fbbf24;font-weight:700;margin-bottom:4px">Profil Lokasi & Operasional</div>
        <div style="font-size:11px;color:#64748b;margin-bottom:10px">Data ini memicu biaya otomatis: jarak→transport, listrik kurang→genset, jarak jauh &amp; lama kerja→nginap otomatis (hotel/kontrakan), lama kerja→makan. Jarak &amp; listrik terisi dari Profil Lokasi. Biaya ini ditambahkan ke SEMUA opsi (sama untuk 1 project).</div>
        <div class="grid2">
            <div class="ro-field"><label>Jarak workshop→lokasi (km, 1 arah)</label><input type="number" id="opJarak" value="0" min="0" step="0.1"></div>
            <div class="ro-field"><label>Daya listrik lokasi</label><select id="opListrik"><option value="cukup">Cukup</option><option value="kurang">Kurang</option><option value="tidak">Tidak ada</option></select></div>
            <div class="ro-field"><label>Tim nginap gratis di tempat customer?</label><select id="opNginap"><option value="tidak">Tidak (sistem hitung penginapan)</option><option value="boleh">Boleh, gratis di lokasi</option></select></div>
        </div>
        @if($lihatModal)
        <div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">
            <div style="font-size:11px;color:#94a3b8;margin-bottom:8px">Tarif (owner) — diisi sekali, dipakai untuk hitung di atas</div>
            <div class="grid2">
                <div class="ro-field"><label>Biaya transport / km</label><input type="number" id="opTarifKm" value="{{ $setting->tarif_km ?? 5000 }}" min="0"></div>
                <div class="ro-field"><label>Sewa genset / hari</label><input type="number" id="opTarifGenset" value="{{ $setting->tarif_genset ?? 150000 }}" min="0"></div>
                <div class="ro-field"><label>Hotel / orang / malam (nginap 3–5 hari)</label><input type="number" id="opTarifHotel" value="{{ $setting->tarif_hotel ?? 0 }}" min="0"></div>
                <div class="ro-field"><label>Kontrakan flat (nginap &gt;5 hari)</label><input type="number" id="opTarifKontrakan" value="{{ $setting->tarif_kontrakan ?? 0 }}" min="0"></div>
                <div class="ro-field"><label>Uang makan / orang / hari</label><input type="number" id="opTarifMakan" value="{{ $setting->tarif_makan ?? 25000 }}" min="0"></div>
            </div>
        </div>
        @else
        <input type="hidden" id="opTarifKm" value="{{ $setting->tarif_km ?? 5000 }}">
        <input type="hidden" id="opTarifGenset" value="{{ $setting->tarif_genset ?? 150000 }}">
        <input type="hidden" id="opTarifHotel" value="{{ $setting->tarif_hotel ?? 0 }}">
        <input type="hidden" id="opTarifKontrakan" value="{{ $setting->tarif_kontrakan ?? 0 }}">
        <input type="hidden" id="opTarifMakan" value="{{ $setting->tarif_makan ?? 25000 }}">
        @endif
        <div id="opPreview" style="font-size:12px;color:#cbd5e1;margin-top:10px"></div>
        <div id="nginapNote" style="font-size:12px;margin-top:8px"></div>
    </div>

    <div class="ro-card">
        <div style="font-size:14px;color:#fbbf24;font-weight:700;margin-bottom:4px">Tingkat Layanan (kecepatan)</div>
        <div style="font-size:11px;color:#64748b;margin-bottom:10px">Pilih SATU. Hemat = lebih murah tapi antri lebih lama. Kilat = lebih cepat (lembur/dahulukan) tapi lebih mahal. Diterapkan di atas harga jual SEMUA opsi.</div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <label class="ckf"><input type="radio" name="tingkatLayanan" value="hemat"> Hemat (lebih murah)</label>
            <label class="ckf"><input type="radio" name="tingkatLayanan" value="reguler" checked> Reguler (harga normal)</label>
            <label class="ckf"><input type="radio" name="tingkatLayanan" value="kilat"> Kilat (lebih cepat)</label>
        </div>
        @if($lihatModal)
        <div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">
            <div style="font-size:11px;color:#94a3b8;margin-bottom:8px">Persentase (owner)</div>
            <div class="grid2">
                <div class="ro-field"><label>Hemat: potong harga (%)</label><input type="number" id="layHemat" value="{{ $setting->lay_hemat ?? 5 }}" min="0" max="50"></div>
                <div class="ro-field"><label>Kilat: tambah harga (%)</label><input type="number" id="layKilat" value="{{ $setting->lay_kilat ?? 10 }}" min="0" max="100"></div>
            </div>
        </div>
        @else
        <input type="hidden" id="layHemat" value="{{ $setting->lay_hemat ?? 5 }}">
        <input type="hidden" id="layKilat" value="{{ $setting->lay_kilat ?? 10 }}">
        @endif
        <div id="layPreview" style="font-size:12px;color:#cbd5e1;margin-top:10px"></div>
    </div>
    @endif
    </div>{{-- /stepD --}}

    <div class="wz-step" id="step1">
    <div class="tabbar" id="tabbar"></div>

    <div class="opsi-bar">
        <input type="text" class="nm" id="opsiNama" placeholder="Nama opsi aktif">
        <button class="btn-grey" style="border:none;border-radius:8px;padding:10px 14px;min-height:46px;font-weight:700;cursor:pointer" onclick="duplikatOpsi()">⎘ Duplikat opsi ini</button>
        <button class="iconbtn" title="Hapus opsi" onclick="hapusOpsi()">🗑️</button>
    </div>

    <div id="panes"></div>

    <div style="display:flex;gap:8px;margin-bottom:14px">
        <button class="btn btn-grey" onclick="tambahBlok(null,'kanopi')">+ Blok Kanopi</button>
        <button class="btn btn-grey" onclick="tambahBlok(null,'manual')">+ Blok Manual</button>
        <button class="btn btn-grey" onclick="tambahBlok(null,'denah')">+ Blok Denah</button>
    </div>
    </div>{{-- /step1 --}}

    <div class="wz-step" id="stepHarga" style="display:none">
    <div id="hasil"></div>
    </div>{{-- /stepHarga --}}
</div>

<div class="actbar"><div class="actbar-in">
    <button class="btn btn-blue wz-n1" onclick="tambahOpsi()">+ Opsi</button>
    <button class="btn btn-gold wz-n1" onclick="lanjutFinalisasi()">Lanjut → Finalisasi</button>
    <button class="btn btn-grey wz-n2" style="display:none" onclick="wzGo(1)">← Kembali</button>
    <button class="btn btn-gold wz-n2" style="display:none" onclick="wzHitung()">Hitung Harga →</button>
    <button class="btn btn-grey wz-n3" style="display:none" onclick="wzGo(2)">← Ubah Finalisasi</button>
    <button class="btn btn-gold wz-n3" style="display:none" onclick="buatPenawaran()">Buat Penawaran →</button>
</div></div>

<script src="{{ asset('js/denah-editor.js') }}"></script>
<script>
const CSRF='{{ csrf_token() }}';
const LEAD = @json($lead ?? null);
const DISKON_MAX = {{ $setting->diskon_max ?? 15 }};
const LIHAT_HARGA = {{ $lihatHarga ? 'true' : 'false' }};
const LIHAT_MODAL = {{ ($lihatModal ?? false) ? 'true' : 'false' }};
const BESI=@json($besi);
const BESI_SEMUA=@json($besiSemua);
const JK=@json($jenisKerja);
const KOND=@json($kondisi);
const ATAP=@json($atap);
const ADDON=@json($addon);
let opsiSeq=0, blokSeq=0, opsiAktif=null;
const DENAH = new WeakMap(); // card -> instance DenahEditor (blok tipe 'denah')

function rp(n){ return 'Rp '+Math.round(n||0).toLocaleString('id-ID'); }
function esc(s){ return String(s==null?'':s).replace(/"/g,'&quot;'); }
function hargaOf(nama){ const b=BESI_SEMUA.find(function(x){return x.nama===nama;}); return b?(b.harga_pokok||0):0; }
function besiOpts(){ return '<option value="">— pilih —</option>'+BESI.map(function(b){return `<option value="${esc(b.nama)}">${esc(b.nama)}</option>`;}).join(''); }
function besiSemuaOpts(){ return '<option value="">— pilih besi —</option>'+BESI_SEMUA.map(function(b){return `<option value="${esc(b.nama)}" data-harga="${b.harga_pokok||0}">${esc(b.nama)}</option>`;}).join(''); }
function jkOpts(){ return '<option value="0">— tidak hitung upah —</option>'+JK.map(function(j){return `<option value="${j.id}">${esc(j.nama)}</option>`;}).join(''); }
function atapOpts(){ return '<option value="0">— pilih atap —</option>'+ATAP.map(function(a){return `<option value="${a.id}">${esc(a.nama)}</option>`;}).join(''); }
function addonOpts(level){ var list = level ? ADDON.filter(function(a){return (a.level||'total')===level;}) : ADDON; return '<option value="0">— pilih —</option>'+list.map(function(a){return '<option value="'+a.id+'">'+esc(a.nama)+' ('+a.formula_type+')</option>';}).join(''); }
function addonSection(judul, level){ return '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px"><div class="subhead" style="margin-top:0">'+judul+'</div><div class="b-addonRows-'+level+'"></div><button type="button" class="btn btn-grey" style="padding:9px" onclick="addAddonRow(this,\''+level+'\')">+ '+judul+'</button></div>'; }
function addonFormula(id){ const a=ADDON.find(function(x){return x.id==id;}); return a?a.formula_type:''; }
function kondHtml(){ return KOND.map(function(k){return `<label class="ckf"><input type="checkbox" class="b-kond" value="${k.id}"> ${esc(k.nama)}</label>`;}).join(''); }

function setVal(el,v){ if(el!=null && v!=null && v!=='') el.value=v; }
function setChk(el,v){ if(el!=null) el.checked=!!v; }

// ============ OPERASIONAL (profil lokasi) ============
function timDariOpsi(){
    var out=window.LAST_OUT; var mx=0;
    if(out){ for(var i=0;i<out.length;i++){ var d=out[i].data; if(d){ var t=(d.tukang_max||0)+(d.kenek_max||0); if(t>mx) mx=t; } } }
    return mx;
}
function hitungOperasional(baseOverride, orangOverride){
    if(!LIHAT_HARGA) return {transport:0,genset:0,kontrakan:0,makan:0,total:0};
    const v=function(id){ const e=document.getElementById(id); return e?(+e.value||0):0; };
    const sel=function(id){ const e=document.getElementById(id); return e?e.value:''; };
    const km=v('opJarak');
    var orang = (typeof orangOverride==='number' && orangOverride>0) ? orangOverride : timDariOpsi(); // jumlah tim (tukang+kenek) otomatis
    // hari EFEKTIF = hari kerja x faktor layanan (Kilat lebih cepat, Hemat lebih lama)
    var hariEfektif = estimasiDurasi(tingkatLayanan(), baseOverride);
    const transport = km*2*v('opTarifKm');                                  // PP
    const genset    = (sel('opListrik')!=='cukup') ? v('opTarifGenset')*hariEfektif : 0;
    // NGINAP OTOMATIS: jarak >=15km & lama kerja -> hotel (3-5 hari) / kontrakan (>5 hari)
    var nginap=0, nginapMode='';
    if(sel('opNginap')!=='boleh' && km>=15){
        if(hariEfektif>=3 && hariEfektif<=5){ nginap=v('opTarifHotel')*orang*hariEfektif; nginapMode='hotel'; }
        else if(hariEfektif>5){ nginap=v('opTarifKontrakan'); nginapMode='kontrakan'; }
    }
    const makan     = (km>=15) ? orang*hariEfektif*v('opTarifMakan') : 0; // Aturan B: makan HANYA luar kota (>=15km); dalam kota tim makan sendiri
    return { transport:transport, genset:genset, nginap:nginap, nginapMode:nginapMode, makan:makan, hariEfektif:hariEfektif, total:transport+genset+nginap+makan };
}
function updateOpPreview(){
    const el=document.getElementById('opPreview'); if(!el) return;
    if(!LIHAT_MODAL){ el.innerHTML=''; return; } // angka biaya operasional = owner saja
    const o=hitungOperasional();
    if(o.total<=0){ el.innerHTML=''; return; }
    let parts=[];
    if(o.transport>0) parts.push('transport '+rp(o.transport));
    if(o.genset>0)    parts.push('genset '+rp(o.genset));
    if(o.nginap>0)    parts.push((o.nginapMode==='hotel'?'hotel ':'kontrakan ')+rp(o.nginap));
    if(o.makan>0)     parts.push('makan '+rp(o.makan));
    el.innerHTML='<b style="color:#fbbf24">Operasional: '+rp(o.total)+'</b> ('+parts.join(' + ')+') — ditambahkan ke tiap opsi sebelum margin.';
}

function updateNginapNote(){
    var el=document.getElementById('nginapNote'); if(!el) return;
    if(!LIHAT_HARGA){ el.innerHTML=''; return; }
    var o=hitungOperasional();
    if(o.nginapMode==='hotel'){ el.innerHTML='<b style="color:#fbbf24">\ud83c\udfe8 Sistem: WAJIB nginap — Hotel</b> (\u00b1'+o.hariEfektif+' hari kerja, jarak jauh).'; }
    else if(o.nginapMode==='kontrakan'){ el.innerHTML='<b style="color:#fbbf24">\ud83c\udfe0 Sistem: WAJIB nginap — Kontrakan</b> (\u00b1'+o.hariEfektif+' hari kerja &gt;5, jarak jauh).'; }
    else { el.innerHTML=''; }
}

// ============ TINGKAT LAYANAN ============
function tingkatLayanan(){
    if(!LIHAT_HARGA) return {nama:'Reguler', persen:0};
    const r=document.querySelector('input[name="tingkatLayanan"]:checked');
    const lvl=r?r.value:'reguler';
    const v=function(id){ const e=document.getElementById(id); return e?(+e.value||0):0; };
    if(lvl==='hemat') return {nama:'Hemat', persen:-Math.abs(v('layHemat'))};
    if(lvl==='kilat') return {nama:'Kilat', persen:Math.abs(v('layKilat'))};
    return {nama:'Reguler', persen:0};
}
function updateLayPreview(){
    const el=document.getElementById('layPreview'); if(!el) return;
    const l=tingkatLayanan();
    var dt=durasiTeks(l);
    var ddur = dt ? ' · estimasi '+dt : '';
    if(l.persen===0){ el.innerHTML='<b style="color:#cbd5e1">Reguler</b> — harga normal.'+ddur; return; }
    // non-owner: tampilkan nama layanan + durasi saja, sembunyikan persen
    if(!LIHAT_MODAL){ el.innerHTML='<b style="color:#fbbf24">'+l.nama+'</b>'+ddur; return; }
    const arah=l.persen>0?'+':'';
    el.innerHTML='<b style="color:#fbbf24">'+l.nama+'</b>: harga jual tiap opsi '+arah+l.persen+'%.'+ddur;
}
// estimasi durasi (output): hari kerja × faktor layanan. Hari kerja = Senin–Jumat (tanpa Sabtu/Minggu).
function hariInstOpsi(i){
    var out=window.LAST_OUT; if(!out||!out[i]||!out[i].data) return 0;
    if(window.HARI_EDIT && window.HARI_EDIT[i] && window.HARI_EDIT[i].inst>0) return window.HARI_EDIT[i].inst;
    return out[i].data.hari_inst_total||0;
}
function hariInstMaxOpsi(){
    var out=window.LAST_OUT; var mx=0;
    if(out){ for(var i=0;i<out.length;i++){ var hv=hariInstOpsi(i); if(hv>mx) mx=hv; } }
    return mx;
}
function estimasiDurasi(lay, baseOverride){
    var hari;
    if(typeof baseOverride === 'number' && baseOverride>0){ hari = baseOverride; }
    else { hari = hariInstMaxOpsi(); }
    if(hari<=0) return 0;
    var faktor = 1;
    if(lay && lay.nama==='Kilat') faktor=0.7;   // dipercepat 30% (lembur/dahulukan)
    if(lay && lay.nama==='Hemat') faktor=1.2;   // 20% lebih lama (waiting list)
    return Math.ceil(hari*faktor);
}
// konversi hari kerja (Sen–Jum) ke hari kalender (termasuk Sabtu/Minggu)
function hariKalender(w){
    if(w<=0) return 0;
    return w + 2*Math.floor((w-1)/5);
}
function durasiTeks(lay){
    var w=estimasiDurasi(lay);
    if(w<=0) return '';
    var k=hariKalender(w);
    return '±'+w+' hari kerja (≈'+k+' hari kalender)';
}

// ================= OPSI =================
function paneAktif(){ return document.querySelector('.opsi-pane.act'); }

function tambahOpsi(nama, bloks, finishing){
    opsiSeq++;
    const id='opsi'+opsiSeq;
    const tab=document.createElement('button');
    tab.className='tab'; tab.dataset.opsi=id; tab.textContent=nama||('Opsi '+opsiSeq);
    tab.onclick=function(){ switchOpsi(id); };
    document.getElementById('tabbar').appendChild(tab);

    const pane=document.createElement('div');
    pane.className='opsi-pane'; pane.id=id; pane.dataset.nama=nama||('Opsi '+opsiSeq);
    pane.dataset.finishing=(finishing==='powder')?'powder':'standar';
    var finBox='<div style="background:#1e293b;border-radius:10px;padding:10px;margin-bottom:10px">'+
        '<div style="font-size:12px;color:#fbbf24;font-weight:700;margin-bottom:6px">Finishing opsi ini</div>'+
        '<select class="opsi-finishing" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;padding:10px;font-size:13px" onchange="this.closest(\'.opsi-pane\').dataset.finishing=this.value">'+
        '<option value="standar"'+(pane.dataset.finishing==='standar'?' selected':'')+'>Standar (cat/duco — sudah termasuk)</option>'+
        '<option value="powder"'+(pane.dataset.finishing==='powder'?' selected':'')+'>Powder coating (tambah biaya per m² rangka)</option>'+
        '</select></div>';
    pane.innerHTML=finBox+'<div class="blok-list"></div>';
    document.getElementById('panes').appendChild(pane);

    switchOpsi(id);
    if(bloks && bloks.length){ bloks.forEach(function(bd){ tambahBlok(pane, bd.tipe, bd); }); }
    else { tambahBlok(pane, 'kanopi'); }
    return pane;
}

function switchOpsi(id){
    opsiAktif=id;
    [].slice.call(document.querySelectorAll('.tab')).forEach(function(t){ t.classList.toggle('act', t.dataset.opsi===id); });
    [].slice.call(document.querySelectorAll('.opsi-pane')).forEach(function(p){ p.classList.toggle('act', p.id===id); });
    const pane=document.getElementById(id);
    document.getElementById('opsiNama').value = pane ? pane.dataset.nama : '';
}

document.getElementById('opsiNama').addEventListener('input', function(){
    const pane=paneAktif(); if(!pane) return;
    pane.dataset.nama=this.value;
    const tab=document.querySelector('.tab[data-opsi="'+pane.id+'"]');
    if(tab) tab.textContent=this.value||'Opsi';
});

function duplikatOpsi(){
    const pane=paneAktif(); if(!pane) return;
    const data=bacaPane(pane);
    tambahOpsi((pane.dataset.nama||'Opsi')+' (salinan)', data.blok, pane.dataset.finishing);
}

function hapusOpsi(){
    const panes=[].slice.call(document.querySelectorAll('.opsi-pane'));
    if(panes.length<=1){ alert('Minimal harus ada 1 opsi.'); return; }
    if(!confirm('Hapus opsi ini?')) return;
    const pane=paneAktif();
    const tab=document.querySelector('.tab[data-opsi="'+pane.id+'"]');
    if(tab) tab.remove();
    // musnahkan editor denah di dalam opsi ini dulu (lepas listener document, cegah bocor)
    pane.querySelectorAll('.blok-card').forEach(function(card){ var ed=DENAH.get(card); if(ed){ ed.destroy(); DENAH.delete(card); } });
    pane.remove();
    const first=document.querySelector('.opsi-pane');
    if(first) switchOpsi(first.id);
}

// ================= BLOK =================
function tambahBlok(pane, tipe, data){
    pane = pane || paneAktif();
    if(!pane) return;
    const list=pane.querySelector('.blok-list');
    blokSeq++;
    const card=document.createElement('div');
    card.className='blok-card'; card.dataset.tipe=tipe;

    let body='';
    if(tipe==='kanopi'){
        body=
        '<div class="grid2">'+
          '<div class="ro-field"><label>Lebar (cm)</label><input type="number" class="b-lebar" value="400"></div>'+
          '<div class="ro-field"><label>Panjang (cm)</label><input type="number" class="b-panjang" value="300"></div>'+
          '<div class="ro-field"><label>Tinggi tiang (cm)</label><input type="number" class="b-tinggi" value="300"></div>'+
          '<div class="ro-field"><label>Kotak support (cm)</label><input type="number" class="b-kotak" value="80"></div>'+
          '<div class="ro-field"><label>Arah support</label><select class="b-arah"><option value="2">Grid 2 arah</option><option value="1">1 arah</option></select></div>'+
          '<div class="ro-field"><label>Jumlah tiang</label><input type="number" class="b-tiang" value="2"></div>'+
          '<div class="ro-field"><label>Material Frame</label><select class="b-matFrame">'+besiOpts()+'</select></div>'+
          '<div class="ro-field"><label>Material Support</label><select class="b-matSupport">'+besiOpts()+'</select></div>'+
          '<div class="ro-field"><label>Material Tiang</label><select class="b-matTiang">'+besiOpts()+'</select></div>'+
        '</div>'+
        '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
          '<div style="font-size:12px;color:#94a3b8;margin-bottom:8px">Sisi Frame (matikan sisi yang nempel)</div>'+
          '<div style="display:flex;flex-wrap:wrap;gap:12px">'+
            '<label class="ckf"><input type="checkbox" class="b-fDepan" checked> Depan</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fBelakang" checked> Belakang</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fKiri" checked> Kiri</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fKanan" checked> Kanan</label>'+
            '<label class="ckf" style="color:#fbbf24"><input type="checkbox" class="b-fTengah" checked> + Tengah</label>'+
          '</div>'+
        '</div>'+
        '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
          '<div style="font-size:12px;color:#fbbf24;font-weight:700;margin-bottom:8px">Besi Tambahan (support/reng/besi lain)</div>'+
          '<div class="b-besiExtra"></div>'+
          '<button type="button" class="btn btn-grey" style="padding:9px" onclick="addBesiRow(this)">+ Besi Tambahan</button>'+
          '<div style="font-size:10px;color:#64748b;margin-top:6px">Untuk hollow/besi yang tak tercakup rangka otomatis (mis. reng 3x3, gording 4x8, besi beton). Pilih jenis + jumlah batang.</div>'+
        '</div>'+
        (LIHAT_HARGA ? (
        '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
          '<div class="subhead" style="margin-top:0">Upah</div>'+
          '<div class="ro-field"><label>Jenis Kerja</label><select class="b-jk">'+jkOpts()+'</select></div>'+
          (KOND.length ? '<div style="margin-top:8px;font-size:11px;color:#94a3b8;margin-bottom:6px">Kondisi kerja</div><div style="display:flex;flex-wrap:wrap;gap:12px">'+kondHtml()+'</div>' : '')+
        '</div>') : '');
    } else if(tipe==='denah'){
        body=
        '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
          '<div style="font-size:12px;color:#fbbf24;font-weight:700;margin-bottom:8px">Besi Tambahan (support/reng/besi lain)</div>'+
          '<div class="b-besiExtra"></div>'+
          '<button type="button" class="btn btn-grey" style="padding:9px" onclick="addBesiRow(this)">+ Besi Tambahan</button>'+
          '<div style="font-size:10px;color:#64748b;margin-top:6px">Untuk hollow/besi yang tak tercakup rangka otomatis (mis. reng 3x3, gording 4x8, besi beton). Pilih jenis + jumlah batang.</div>'+
        '</div>'+
        '<div class="b-denah" style="margin-top:10px"></div>'+
        (LIHAT_HARGA ? (
        '<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
          '<div class="subhead" style="margin-top:0">Upah</div>'+
          '<div class="ro-field"><label>Jenis Kerja</label><select class="b-jk">'+jkOpts()+'</select></div>'+
          (KOND.length ? '<div style="margin-top:8px;font-size:11px;color:#94a3b8;margin-bottom:6px">Kondisi kerja</div><div style="display:flex;flex-wrap:wrap;gap:12px">'+kondHtml()+'</div>' : '')+
        '</div>') : '');
    } else {
        body=
        '<div style="font-size:12px;color:#a78bfa;margin-bottom:8px">Mode manual — isi daftar besi/bahan langsung.</div>'+
        '<div class="b-manualRows"></div>'+
        '<button type="button" class="btn btn-grey" style="padding:10px;margin-top:4px" onclick="addManualRow(this)">+ Tambah Item</button>'+
        (LIHAT_HARGA ? '<div class="ro-field" style="margin-top:10px"><label>Upah pasang (lumpsum)</label><input type="number" class="b-manualUpah" value="0" min="0"></div>' : '');
    }

    let extra='';
    if(LIHAT_HARGA){
        var hasR=ADDON.some(function(a){return (a.level||'total')==='rangka';});
        var hasA=ADDON.some(function(a){return (a.level||'total')==='atap';});
        var hasT=ADDON.some(function(a){return (a.level||'total')==='total';});
        if(hasR){ extra+=addonSection('Add-on Rangka','rangka'); }
        if(ATAP.length){
            extra+='<div style="margin-top:10px;padding:10px;background:#0f172a;border-radius:10px">'+
                   '<div class="subhead" style="margin-top:0">Atap</div><div class="b-atapRows"></div>'+
                   '<button type="button" class="btn btn-grey" style="padding:9px" onclick="addAtapRow(this)">+ Atap</button></div>';
        }
        if(hasA){ extra+=addonSection('Add-on Atap','atap'); }
        if(hasT){ extra+=addonSection('Add-on Lain','total'); }
    }

    var tagCls=(tipe==='manual'?'manual':(tipe==='denah'?'denah':''));
    var tagLbl=(tipe==='manual'?'MANUAL':(tipe==='denah'?'DENAH':'KANOPI'));
    var namaDefault=(tipe==='manual'?'Pagar/Railing':(tipe==='denah'?'Denah':'Kanopi'));
    card.innerHTML=
        '<div class="blok-head">'+
          '<span class="tag '+tagCls+'">'+tagLbl+'</span>'+
          '<input type="text" class="b-nama" value="'+namaDefault+'">'+
          '<label class="sw"><input type="checkbox" class="b-aktif" checked><span class="sl"></span></label>'+
          '<button class="iconbtn" onclick="lipat(this)">▾</button>'+
          '<button class="iconbtn" onclick="hapusBlok(this)">🗑️</button>'+
        '</div>'+
        '<div class="blok-body">'+body+extra+'</div>';

    list.appendChild(card);

    // blok denah: mount DenahEditor (Task 1-2) di dalam kartu, registry per-kartu di WeakMap DENAH
    if(tipe==='denah'){
        const mount=card.querySelector('.b-denah');
        const ed=new DenahEditor(mount, {
            besi: BESI.map(function(b){ return { nama:b.nama, harga:Number(b.harga_pokok)||0 }; }),
            model: (data && data.denah) ? data.denah : null,
            onChange: function(){ jadwalkanHitung(pane); }
        });
        DENAH.set(card, ed);
    }

    // default material tebakan
    if(tipe==='kanopi' && !data){
        const cari=function(kw){ const b=BESI.find(function(x){return x.nama.toLowerCase().replace(/\s/g,'').includes(kw);}); return b?b.nama:''; };
        const f=cari('5x10'); const mf=card.querySelector('.b-matFrame'), mt=card.querySelector('.b-matTiang');
        if(f && mf) mf.value=f; if(f && mt) mt.value=f;
    }
    card.querySelector('.b-aktif').addEventListener('change',function(){ card.classList.toggle('off', !this.checked); });

    if(data) isiBlok(card, data);
    return card;
}

// blok denah dibuang: musnahkan instance DenahEditor (lepas listener document) & buang dari registry
function hapusBlok(btn){
    if(!confirm('Hapus blok?')) return;
    var card=btn.closest('.blok-card');
    var ed=DENAH.get(card);
    if(ed){ ed.destroy(); DENAH.delete(card); }
    card.remove();
}

function isiBlok(card, d){
    setVal(card.querySelector('.b-nama'), d.nama);
    setChk(card.querySelector('.b-aktif'), d.aktif!==false);
    card.classList.toggle('off', d.aktif===false);
    if(card.dataset.tipe==='kanopi'){
        setVal(card.querySelector('.b-lebar'), d.lebar_cm); setVal(card.querySelector('.b-panjang'), d.panjang_cm);
        setVal(card.querySelector('.b-tinggi'), d.tinggi_cm); setVal(card.querySelector('.b-kotak'), d.kotak_cm);
        setVal(card.querySelector('.b-arah'), d.arah_support); setVal(card.querySelector('.b-tiang'), d.jml_tiang);
        setVal(card.querySelector('.b-matFrame'), d.mat_frame); setVal(card.querySelector('.b-matSupport'), d.mat_support); setVal(card.querySelector('.b-matTiang'), d.mat_tiang);
        var bx=d.besi_extra||[];
        bx.forEach(function(x){ var r=addBesiRowTo(card); setVal(r.querySelector('.bx-jenis'), x.material); setVal(r.querySelector('.bx-batang'), x.batang); });
        setChk(card.querySelector('.b-fDepan'), d.frame_depan); setChk(card.querySelector('.b-fBelakang'), d.frame_belakang);
        setChk(card.querySelector('.b-fKiri'), d.frame_kiri); setChk(card.querySelector('.b-fKanan'), d.frame_kanan); setChk(card.querySelector('.b-fTengah'), d.frame_tengah);
        setVal(card.querySelector('.b-jk'), d.jenis_kerja_id);
        const kset=d.kondisi_ids||[];
        [].slice.call(card.querySelectorAll('.b-kond')).forEach(function(c){ c.checked = kset.indexOf(+c.value)>=0; });
    } else if(card.dataset.tipe==='denah'){
        // model denah (poligon/matDefault) sudah dioper via {model:d.denah} ke constructor DenahEditor
        // di tambahBlok — DenahEditor.syncInputs() sendiri yang mengisi ulang select besi default-nya.
        var bxD=d.besi_extra||[];
        bxD.forEach(function(x){ var r=addBesiRowTo(card); setVal(r.querySelector('.bx-jenis'), x.material); setVal(r.querySelector('.bx-batang'), x.batang); });
        setVal(card.querySelector('.b-jk'), d.jenis_kerja_id);
        const ksetD=d.kondisi_ids||[];
        [].slice.call(card.querySelectorAll('.b-kond')).forEach(function(c){ c.checked = ksetD.indexOf(+c.value)>=0; });
    } else {
        (d.manual_items||[]).forEach(function(it){ const r=addManualRowTo(card); setVal(r.querySelector('.m-nama'), it.nama); setVal(r.querySelector('.m-qty'), it.qty); setVal(r.querySelector('.m-harga'), it.harga); });
        setVal(card.querySelector('.b-manualUpah'), d.manual_upah);
    }
    const aj=d.atap_jenis_id||[], al=d.atap_luas||[], ap=d.atap_pasang||[];
    aj.forEach(function(id,i){ const r=addAtapRowTo(card); setVal(r.querySelector('.atap-jenis'), id); setVal(r.querySelector('.atap-luas'), al[i]); var pc=r.querySelector('.atap-pasang'); if(pc) pc.checked = ap[i]?true:false; });
    const ai=d.addon_id||[], aq=d.addon_qty||[];
    ai.forEach(function(id,i){ var a=ADDON.find(function(x){return x.id==id;}); var lv=a?(a.level||'total'):'total'; const r=addAddonRowTo(card, lv); setVal(r.querySelector('.addon-jenis'), id); addonSync(r.querySelector('.addon-jenis')); setVal(r.querySelector('.addon-qty'), aq[i]); });
}

// ---- baris atap/addon/manual (versi "To card" + versi tombol) ----
function addBesiRowTo(card){
    var box=card.querySelector('.b-besiExtra'); if(!box) return document.createElement('div');
    var r=document.createElement('div'); r.className='row3'; r.style.flexWrap='wrap';
    r.innerHTML='<select class="bx-jenis" style="flex:2;min-width:130px">'+besiSemuaOpts()+'</select>'+
                '<input type="number" class="bx-batang" style="flex:1;min-width:70px" placeholder="jml batang" min="0" step="0.5">'+
                '<button type="button" class="del" onclick="this.closest(\'.row3\').remove()">✕</button>';
    box.appendChild(r); return r;
}
function addBesiRow(btn){ addBesiRowTo(btn.closest('.blok-card')); }
function addAtapRowTo(card){
    const box=card.querySelector('.b-atapRows'); if(!box) return document.createElement('div');
    const r=document.createElement('div'); r.className='row3'; r.style.flexWrap='wrap';
    r.innerHTML='<select class="atap-jenis" style="flex:2;min-width:120px">'+atapOpts()+'</select>'+
                '<input type="number" class="atap-luas" style="flex:1;min-width:70px" placeholder="luas m²" min="0" step="0.01">'+
                '<button type="button" class="del" onclick="this.closest(\'.row3\').remove()">✕</button>'+
                '<label style="flex-basis:100%;display:flex;align-items:center;gap:6px;font-size:11px;color:#94a3b8;margin-top:2px">'+
                '<input type="checkbox" class="atap-pasang"> Atap ini dipasang di rangka lama / reparasi (hitung upah pasang)</label>';
    box.appendChild(r); return r;
}
function addAddonRowTo(card, level){
    level = level || 'total';
    const box=card.querySelector('.b-addonRows-'+level); if(!box) return document.createElement('div');
    const r=document.createElement('div'); r.className='row3';
    r.innerHTML='<select class="addon-jenis" style="flex:2" onchange="addonSync(this)">'+addonOpts(level)+'</select>'+
                '<input type="number" class="addon-qty" style="flex:1" placeholder="jumlah" min="0" step="0.01">'+
                '<button type="button" class="del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(r); return r;
}
function addManualRowTo(card){
    const box=card.querySelector('.b-manualRows'); if(!box) return document.createElement('div');
    const r=document.createElement('div'); r.className='row3';
    r.innerHTML='<select class="m-nama" style="flex:2" onchange="mNamaSync(this)">'+besiSemuaOpts()+'</select>'+
                '<input type="number" class="m-qty" style="width:64px" placeholder="qty" min="0" step="0.01">'+
                '<input type="number" class="m-harga" style="flex:1" placeholder="harga" min="0">'+
                '<button type="button" class="del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(r); return r;
}
function addAtapRow(btn){ addAtapRowTo(btn.closest('.blok-card')); }
function addAddonRow(btn, level){ addAddonRowTo(btn.closest('.blok-card'), level); }
function addManualRow(btn){ addManualRowTo(btn.closest('.blok-card')); }
function addonSync(sel){
    if(!sel) return;
    const q=sel.parentNode.querySelector('.addon-qty'); const ft=addonFormula(+sel.value);
    if(q){ q.disabled=(ft==='flat'); q.placeholder=(ft==='flat'?'lumpsum':(ft==='per_meter'?'meter':(ft==='per_m2'?'m²':'jumlah'))); if(ft==='flat') q.value=''; }
}
function mNamaSync(sel){
    const h=sel.parentNode.querySelector('.m-harga'); const o=sel.options[sel.selectedIndex];
    if(h && o){ const hp=+(o.getAttribute('data-harga')||0); if(hp>0) h.value=hp; }
}
function lipat(btn){ const b=btn.closest('.blok-card').querySelector('.blok-body'); b.style.display=(b.style.display==='none'?'block':'none'); btn.textContent=(b.style.display==='none'?'▸':'▾'); }

// ---- baca data ----
function bacaBlok(card){
    const tipe=card.dataset.tipe;
    const g=function(c){ const el=card.querySelector(c); return el?el.value:''; };
    const ck=function(c){ const el=card.querySelector(c); return el?el.checked:true; };
    const b={ aktif:ck('.b-aktif'), tipe:tipe, nama:g('.b-nama') };
    if(tipe==='kanopi'){
        const mf=g('.b-matFrame'), ms=g('.b-matSupport'), mt=g('.b-matTiang');
        const harga={}; [mf,ms,mt].forEach(function(n){ if(n) harga[n]=hargaOf(n); });
        b.besi_extra=[];
        [].slice.call(card.querySelectorAll('.b-besiExtra .row3')).forEach(function(r){
            var j=r.querySelector('.bx-jenis'), q=r.querySelector('.bx-batang');
            var nm=j?j.value:''; var bt=q?(+q.value||0):0;
            if(nm && bt>0){ b.besi_extra.push({material:nm, batang:bt}); harga[nm]=hargaOf(nm); }
        });
        b.lebar_cm=+g('.b-lebar'); b.panjang_cm=+g('.b-panjang'); b.tinggi_cm=+g('.b-tinggi');
        b.kotak_cm=+g('.b-kotak'); b.arah_support=+g('.b-arah'); b.jml_tiang=+g('.b-tiang');
        b.mat_frame=mf||'Frame'; b.mat_support=ms||'Support'; b.mat_tiang=mt||'Tiang';
        b.frame_depan=ck('.b-fDepan'); b.frame_belakang=ck('.b-fBelakang');
        b.frame_kiri=ck('.b-fKiri'); b.frame_kanan=ck('.b-fKanan'); b.frame_tengah=ck('.b-fTengah');
        b.harga=harga; b.jenis_kerja_id=+g('.b-jk')||0;
        b.kondisi_ids=[].slice.call(card.querySelectorAll('.b-kond:checked')).map(function(c){return +c.value;});
    } else if(tipe==='denah'){
        const ed=DENAH.get(card);
        const members=ed?ed.getMembers():[];
        const hargaD={};
        members.forEach(function(m){ hargaD[m.material]=hargaOf(m.material); });
        b.besi_extra=[];
        [].slice.call(card.querySelectorAll('.b-besiExtra .row3')).forEach(function(r){
            var j=r.querySelector('.bx-jenis'), q=r.querySelector('.bx-batang');
            var nm=j?j.value:''; var bt=q?(+q.value||0):0;
            if(nm && bt>0){ b.besi_extra.push({material:nm, batang:bt}); hargaD[nm]=hargaOf(nm); }
        });
        b.luas_m2=ed?ed.getLuas():0;
        b.members=members.map(function(m){ return { nama:m.nama, jenis:m.jenis, panjang:m.panjang, material:m.material }; });
        b.harga=hargaD;
        b.denah=ed?ed.getModel():null; // ikut ke rab_snapshot untuk rehidrasi
        b.jenis_kerja_id=+g('.b-jk')||0;
        b.kondisi_ids=[].slice.call(card.querySelectorAll('.b-kond:checked')).map(function(c){return +c.value;});
    } else {
        b.manual_items=[].slice.call(card.querySelectorAll('.b-manualRows .row3')).map(function(r){
            return { nama:(r.querySelector('.m-nama')||{}).value||'', qty:+((r.querySelector('.m-qty')||{}).value||0), harga:+((r.querySelector('.m-harga')||{}).value||0) };
        }).filter(function(x){ return x.nama && x.qty>0; });
        b.manual_upah=+g('.b-manualUpah')||0;
    }
    b.atap_jenis_id=[]; b.atap_luas=[]; b.atap_pasang=[];
    [].slice.call(card.querySelectorAll('.b-atapRows .row3')).forEach(function(r){
        const j=r.querySelector('.atap-jenis'), l=r.querySelector('.atap-luas'), p=r.querySelector('.atap-pasang');
        if(j && +j.value>0 && l && +l.value>0){ b.atap_jenis_id.push(+j.value); b.atap_luas.push(+l.value); b.atap_pasang.push(p&&p.checked?1:0); }
    });
    b.addon_id=[]; b.addon_qty=[];
    [].slice.call(card.querySelectorAll('.addon-jenis')).forEach(function(j){
        const q=j.parentNode.querySelector('.addon-qty');
        if(j && +j.value>0){ b.addon_id.push(+j.value); b.addon_qty.push(q?(+q.value||0):0); }
    });
    return b;
}
function bacaPane(pane){
    return { nama:pane.dataset.nama, finishing:(pane.dataset.finishing||'standar'), blok:[].slice.call(pane.querySelectorAll('.blok-card')).map(bacaBlok) };
}

// ---- bandingkan ----
async function hitungSatuOpsi(pane, margin){
    var pd=bacaPane(pane); const body={ margin_persen:margin, finishing:pd.finishing, blok:pd.blok };
    const r=await fetch('{{ url("/rab-blok/hitung") }}',{method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)});
    return r.json();
}

async function bandingkan(){
    const panes=[].slice.call(document.querySelectorAll('.opsi-pane'));
    if(!panes.length){ alert('Belum ada opsi.'); return; }
    const mEl=document.getElementById('projMargin');
    const margin=+((mEl&&mEl.value)||45);
    const lay=tingkatLayanan();
    const hasil=document.getElementById('hasil');
    hasil.innerHTML='<div class="ro-card">Menghitung...</div>';
    try{
        const out=[];
        for(let i=0;i<panes.length;i++){
            const res=await hitungSatuOpsi(panes[i], margin);
            out.push({ nama:panes[i].dataset.nama||('Opsi '+(i+1)), data:(res&&res.data)?res.data:null });
        }
        window.LAST_OUT = out;      // simpan utk ringkasan per opsi di Finalisasi
        renderRingkasOpsi();
        const op2=hitungOperasional(); // hitung ulang operasional pakai hari yang mungkin baru terisi
        renderBanding(out, op2, margin, lay);
    }catch(e){ hasil.innerHTML='<div class="ro-card">Error: '+esc(e.message)+'</div>'; }
}

function renderBanding(out, op, margin, lay){
    op = op || {total:0}; lay = lay || {nama:'Reguler', persen:0};
    let h='<div class="ro-card"><div style="font-size:14px;color:#fbbf24;font-weight:700;margin-bottom:10px">Perbandingan Opsi</div>';
    var dt=durasiTeks(lay);
    if(LIHAT_HARGA && (dt!=='' || (LIHAT_MODAL && (op.total>0 || lay.persen!==0)))){
        h+='<div style="font-size:12px;color:#cbd5e1;margin-bottom:10px;padding:8px;background:#0f172a;border-radius:8px">';
        var sep=false;
        if(LIHAT_MODAL){ h+='Operasional: <b style="color:#fbbf24">dihitung per opsi (lihat tiap kolom)</b>'; sep=true; }
        if(LIHAT_MODAL && lay.persen!==0){ h+=(sep?' · ':'')+'Layanan <b style="color:#fbbf24">'+lay.nama+' '+(lay.persen>0?'+':'')+lay.persen+'%</b>'; sep=true; }
        if(dt!==''){ h+=(sep?' · ':'')+'Estimasi durasi <b style="color:#fbbf24">'+dt+'</b>'; }
        h+='</div>';
    }
    h+='<div class="cmp">';
    window.CMP=[]; out.forEach(function(o,iOpsi){
        h+='<div class="cmp-col"><h3>'+esc(o.nama)+'</h3>';
        if(!o.data){ h+='<div style="color:#f87171;font-size:12px">gagal hitung</div></div>'; return; }
        const d=o.data;
        const aktif=(d.blok||[]).filter(function(b){return b.aktif;});
        h+='<div style="font-size:11px;color:#94a3b8;margin-bottom:6px">'+aktif.length+' blok aktif</div>';
        aktif.forEach(function(b){
            h+='<div class="sum-row" style="font-size:12px"><span>'+esc(b.nama)+'</span>'+(LIHAT_MODAL?'<span>'+rp(b.pokok_blok)+'</span>':'')+'</div>';
        });
        if(LIHAT_HARGA && d.pokok!=null){
            var opOpsi = hitungOperasional(hariInstOpsi(iOpsi), (d.tukang_max||0)+(d.kenek_max||0)); // hari instalasi (edit surveyor / mesin) + tim OPSI INI
            const pokokTotal=d.pokok + (opOpsi.total||0);
            const jual = pokokTotal/(1 - margin/100);
            const jualFinal = jual * (1 + lay.persen/100);
            if(LIHAT_MODAL && opOpsi.total>0) h+='<div class="sum-row" style="font-size:12px"><span>Operasional ('+opOpsi.hariEfektif+' hr)</span><span>'+rp(opOpsi.total)+'</span></div>';
            if(LIHAT_MODAL){
                var rc={besi:0,upah:0,atapMat:0,atapUpah:0,cRangka:0,cAtap:0,finish:0,addonF:0,addonU:0};
                aktif.forEach(function(b){
                    rc.besi+=(+b.besi||0); rc.upah+=(+b.upah||0); rc.atapMat+=(+b.atap_material||0); rc.atapUpah+=(+b.atap_upah||0);
                    rc.cRangka+=(+b.consumable_rangka||0); rc.cAtap+=(+b.consumable_atap||0); rc.finish+=(+b.finishing||0);
                    rc.addonF+=(+b.addon_fisik||0); rc.addonU+=(+b.addon_upah||0);
                });
                function rr(lbl,val){ return (val>0)?('<div class="sum-row" style="font-size:11px;color:#94a3b8"><span>'+lbl+'</span><span>'+rp(val)+'</span></div>'):''; }
                var besiDetail='';
                aktif.forEach(function(bb){
                    var rin=bb.rincian||[];
                    rin.forEach(function(m){
                        var jb=+m.jumlah_batang||0, hp=+m.harga_pokok||0, sub=+m.subtotal_besi||0;
                        if(jb>0){ besiDetail+='<div class="sum-row" style="font-size:10px;color:#64748b;padding-left:12px"><span>• '+esc(m.material)+' — '+jb+' btg × '+rp(hp)+'</span><span>'+rp(sub)+'</span></div>'; }
                    });
                });
                h+='<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #334155">'+
                   '<div style="font-size:11px;color:#fbbf24;font-weight:700;margin-bottom:4px">Rincian pokok (owner)</div>'+
                   rr('Besi rangka', rc.besi)+ besiDetail+ rr('Upah rangka', rc.upah)+
                   rr('Material atap', rc.atapMat)+ rr('Upah pasang atap', rc.atapUpah)+
                   rr('Consumable rangka', rc.cRangka)+ rr('Finishing (cat)', rc.finish)+
                   rr('Consumable atap', rc.cAtap)+ rr('Add-on bahan', rc.addonF)+ rr('Add-on upah', rc.addonU)+
                   rr('Operasional', opOpsi.total||0)+ (function(){
                       var timOrg=(d.tukang_max||0)+(d.kenek_max||0);
                       function sub(l,v){ return (v>0)?('<div class="sum-row" style="font-size:10px;color:#64748b;padding-left:12px"><span>'+l+'</span><span>'+rp(v)+'</span></div>'):''; }
                       return sub('• transport PP', opOpsi.transport)+
                              sub('• genset', opOpsi.genset)+
                              sub('• '+(opOpsi.nginapMode==='hotel'?'hotel':'kontrakan'), opOpsi.nginap)+
                              sub('• makan ('+opOpsi.hariEfektif+' hr × '+timOrg+' org)', opOpsi.makan);
                   })()+
                   '</div>';
            }
            if(LIHAT_MODAL) h+='<div style="font-size:11px;color:#64748b;margin-top:8px">pokok '+rp(pokokTotal)+' · margin '+margin+'%</div>';
            if(LIHAT_MODAL && lay.persen!==0){
                h+='<div style="font-size:11px;color:#64748b">jual reguler '+rp(jual)+' · '+lay.nama+' '+(lay.persen>0?'+':'')+lay.persen+'%</div>';
            }
            window.CMP[iOpsi] = { nama:o.nama, jual:jualFinal, pokok:pokokTotal };
            h+='<div class="cmp-harga">'+rp(jualFinal)+'</div>';
            if(LEAD){
                h+='<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">'+
                   '<button class="btn btn-grey" style="padding:9px;font-size:12px" onclick="simpanEstimasiOpsi('+iOpsi+')">Jadikan Estimasi Awal</button>'+
                   '<button class="btn btn-gold" style="padding:9px;font-size:12px" onclick="pilihOpsiNego('+iOpsi+')">Pilih &amp; Nego</button>'+
                   '</div>';
            }
        }
        if(d.peringatan && d.peringatan.length){ h+='<div style="font-size:11px;color:#fca5a5;margin-top:6px">⚠ '+d.peringatan.length+' data belum lengkap</div>'; }
        h+='</div>';
    });
    h+='</div>';
    h+='<div id="negoPanel" style="margin-top:14px"></div>';
    h+='</div>';
    document.getElementById('hasil').innerHTML=h;
}

// ---- NEGO (customer nawar harga) ----
var OPSI_SPEK = [
    { label:'Ganti atap alderon → spandek pasir', hemat:800000, script:'Kalau Bapak mau pakai spandek pasir, harganya bisa lebih rendah. Kualitas anti bocor sama, hanya tampilan sedikit beda.' },
    { label:'Garansi rangka 6 bulan (bukan 1 tahun)', hemat:0, script:'Garansinya kami sesuaikan jadi 6 bulan Pak biar harganya bisa kami turunkan.' },
    { label:'Tanpa talang (pasang sendiri nanti)', hemat:400000, script:'Kalau talangnya Bapak pasang sendiri nanti, harga bisa kami sesuaikan Pak.' },
    { label:'Bayar tunai full di muka', hemat:0, script:'Kalau Bapak bisa bayar full sekarang, kami kasih potongan karena bantu cash flow kami.' }
];
function pilihOpsiNego(i){
    var c=window.CMP[i]; if(!c) return;
    window.NEGO={ i:i, hargaNormal:c.jual, pokok:c.pokok, nama:c.nama, nawar:c.jual };
    renderNego();
    var p=document.getElementById('negoPanel'); if(p && p.scrollIntoView){ p.scrollIntoView({behavior:'smooth'}); }
}
function negoHitung(){
    var N=window.NEGO;
    var diskon = N.hargaNormal>0 ? (1 - N.nawar/N.hargaNormal)*100 : 0; if(diskon<0) diskon=0;
    var luar = diskon > DISKON_MAX;
    var warna = luar ? '#ef4444' : (diskon > DISKON_MAX*0.5 ? '#f59e0b' : '#22c55e');
    var label = luar ? 'Di Luar Kewenangan — Perlu Approval' : (diskon > DISKON_MAX*0.5 ? 'Harga Ketat — Jangan Turun Lagi' : 'Masih Aman — Mainkan Value');
    return { diskon:diskon, luar:luar, warna:warna, label:label };
}
function renderNego(){
    var g=document.getElementById('negoPanel'); if(!g || !window.NEGO) return;
    var N=window.NEGO; var st=negoHitung();
    var h='<div class="ro-card" style="border:1px solid '+st.warna+'">';
    h+='<div style="font-size:14px;font-weight:700;color:#fbbf24;margin-bottom:4px">Nego: '+esc(N.nama)+'</div>';
    h+='<div style="font-size:12px;color:#64748b">Harga penawaran awal: <b style="color:#fbbf24">'+rp(N.hargaNormal)+'</b></div>';
    h+='<div class="ro-field" style="margin-top:10px"><label>Customer nawar berapa? (Rp)</label><input type="number" id="negoNawar" value="'+Math.round(N.nawar)+'" min="0" oninput="updateNegoStatus()" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;padding:11px;color:#f1f5f9;font-size:16px;min-height:48px"></div>';
    h+='<div id="negoStatus" style="display:flex;justify-content:space-between;align-items:center;border-radius:8px;padding:10px;margin-top:8px;background:'+st.warna+'22;border:1px solid '+st.warna+'"><div style="font-size:13px;font-weight:700;color:'+st.warna+'">'+st.label+'</div><div style="font-size:16px;font-weight:800;color:'+st.warna+'">'+st.diskon.toFixed(1)+'%</div></div>';
    if(LIHAT_MODAL){ h+='<div id="negoUntung" style="font-size:11px;color:#64748b;margin-top:6px">untung tersisa: '+rp(N.nawar-N.pokok)+'</div>'; }
    h+='<div style="font-size:11px;color:#f59e0b;font-weight:700;margin-top:12px">COBA DULU SEBELUM MINTA APPROVAL:</div>';
    for(var k=0;k<OPSI_SPEK.length;k++){ var o=OPSI_SPEK[k];
        h+='<div style="background:#0f172a;border-radius:8px;padding:10px;margin-top:6px"><div style="font-size:13px;color:#e2e8f0;font-weight:700">'+o.label+(o.hemat>0?' <span style="color:#22c55e">~hemat '+rp(o.hemat)+'</span>':'')+'</div><div style="font-size:11px;color:#64748b;margin-top:3px">'+o.script+'</div></div>';
    }
    h+='<div id="negoBtns" style="display:flex;flex-direction:column;gap:6px;margin-top:12px"></div>';
    h+='</div>';
    g.innerHTML=h;
    updateNegoStatus();
}
function updateNegoStatus(){
    var N=window.NEGO; if(!N) return;
    var el=document.getElementById('negoNawar'); if(el){ N.nawar=parseFloat((el.value||'0'))||0; }
    var st=negoHitung();
    var box=document.getElementById('negoStatus');
    if(box){ box.style.background=st.warna+'22'; box.style.borderColor=st.warna;
        box.innerHTML='<div style="font-size:13px;font-weight:700;color:'+st.warna+'">'+st.label+'</div><div style="font-size:16px;font-weight:800;color:'+st.warna+'">'+st.diskon.toFixed(1)+'%</div>'; }
    var u=document.getElementById('negoUntung'); if(u){ u.innerHTML='untung tersisa: '+rp(N.nawar-N.pokok); }
    var bt=document.getElementById('negoBtns');
    if(bt){
        if(st.luar){ bt.innerHTML='<button class="btn btn-gold" onclick="approvalNego()">Minta Approval Owner</button><button class="btn btn-grey" onclick="dealNego()">Deal tanpa approval</button>'; }
        else { bt.innerHTML='<button class="btn btn-gold" onclick="dealNego()">Deal & Buat Penawaran</button>'; }
    }
}
function dealNego(){
    var N=window.NEGO; if(!N) return;
    if(!LEAD){ alert('Buka RAB dari lead dulu.'); return; }
    if(!confirm('Pakai harga '+rp(N.nawar)+' lalu lanjut ke halaman penawaran untuk tanda tangan?')) return;
    fetch('{{ url("/rab-opsi/simpan-final") }}', {method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ lead_id:LEAD.id, harga:Math.round(N.nawar), snapshot:JSON.stringify({panes:bacaSemuaOpsi()}) })
    }).then(function(r){ return r.json(); }).then(function(res){
        if(res && res.success){ buatPenawaran(); }
        else { alert('Gagal simpan harga: '+((res&&res.message)||'error')); }
    }).catch(function(e){ alert('Error: '+e.message); });
}
function approvalNego(){
    var N=window.NEGO; if(!N){ return; }
    if(!LEAD){ alert('Buka RAB dari lead dulu untuk minta approval.'); return; }
    var st=negoHitung();
    if(!confirm('Kirim permintaan approval ke owner?\nCustomer nawar '+rp(N.nawar)+' (diskon '+st.diskon.toFixed(1)+'%)')) return;
    fetch('{{ url("/rab-approval") }}', {method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ lead_id:LEAD.id, opsi_nama:N.nama, harga_normal:Math.round(N.hargaNormal), harga_nawar:Math.round(N.nawar), diskon_persen:Number(st.diskon.toFixed(2)), pokok:Math.round(N.pokok) })
    }).then(function(r){ return r.json(); }).then(function(res){
        if(res && res.success){ alert('Permintaan approval terkirim ke owner. Tunggu keputusan owner.\n\n[Diagnosa WA]\n'+(res.wa||'-')); }
        else { alert('Gagal kirim: '+((res&&res.message)||'error')); }
    }).catch(function(e){ alert('Error kirim approval: '+e.message); });
}
function simpanEstimasiOpsi(i){ var c=window.CMP[i]; if(!c) return; simpanKeLead(Math.round(c.jual), 'estimasi'); }

// ---- simpan ke lead (estimasi / final) ----
async function simpanKeLead(harga, mode){
    if(!LEAD){ return; }
    var url = (mode==='final') ? '{{ url("/rab-opsi/simpan-final") }}' : '{{ url("/rab-opsi/simpan-estimasi") }}';
    var labelMode = (mode==='final') ? 'Harga Final' : 'Estimasi Awal';
    if(!confirm('Simpan '+rp(harga)+' sebagai '+labelMode+' ke Lead #'+LEAD.id+' ('+LEAD.nama_customer+')?')) return;
    try{
        var body={ lead_id:LEAD.id, harga:harga };
        if(mode==='final') body.snapshot = JSON.stringify({ panes: bacaSemuaOpsi() });
        var r=await fetch(url,{method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body:JSON.stringify(body)});
        var res=await r.json();
        if(!res || !res.success){ alert('Gagal simpan: '+((res&&res.message)||'error')); return; }
        var info=document.getElementById('leadInfoHarga');
        if(mode==='final'){
            if(info) info.innerHTML='Harga final tersimpan: <b style="color:#fbbf24">'+rp(res.harga_final)+'</b>';
            if(res.warning){ alert('⚠ '+res.warning); }
            else { alert('Harga final tersimpan ke lead.'); }
        } else {
            if(info) info.innerHTML='Estimasi admin: <b style="color:#fbbf24">'+rp(res.estimasi_min)+'–'+rp(res.estimasi_max)+'</b>';
            alert('Estimasi awal tersimpan (range '+rp(res.estimasi_min)+'–'+rp(res.estimasi_max)+').');
        }
    }catch(e){ alert('Error simpan: '+e.message); }
}
function bacaSemuaOpsi(){
    return [].slice.call(document.querySelectorAll('.opsi-pane')).map(function(p){ return bacaPane(p); });
}

// mulai: muat snapshot RAB kalau lead sudah punya, kalau tidak buat 1 opsi default
(function(){
    var loaded=false;
    if(LEAD && LEAD.rab_snapshot){
        try{
            var snap=JSON.parse(LEAD.rab_snapshot);
            if(snap && snap.panes && snap.panes.length){
                snap.panes.forEach(function(p){ tambahOpsi(p.nama||'Opsi', (p.blok||[]), p.finishing); });
                loaded=true;
            }
        }catch(e){ loaded=false; }
    }
    if(!loaded) tambahOpsi('Standar');
    if(LEAD){
        var _pn=document.getElementById('projNama'); if(_pn) _pn.value=LEAD.nama_customer;
        var _oj=document.getElementById('opJarak'); if(_oj && LEAD.lokasi_jarak_km) _oj.value=LEAD.lokasi_jarak_km;
        var _ol=document.getElementById('opListrik'); if(_ol && LEAD.lokasi_listrik) _ol.value=LEAD.lokasi_listrik;
    }
})();

// preview operasional realtime
['opJarak','opListrik','opNginap','opTarifKm','opTarifGenset','opTarifHotel','opTarifKontrakan','opTarifMakan'].forEach(function(id){
    const el=document.getElementById(id);
    if(el){ el.addEventListener('input', function(){ updateOpPreview(); updateNginapNote(); }); el.addEventListener('change', function(){ updateOpPreview(); updateNginapNote(); }); }
});
// preview tingkat layanan realtime
['layHemat','layKilat'].forEach(function(id){
    const el=document.getElementById(id);
    if(el) el.addEventListener('input', updateLayPreview);
});
[].slice.call(document.querySelectorAll('input[name="tingkatLayanan"]')).forEach(function(r){
    r.addEventListener('change', function(){ updateLayPreview(); updateOpPreview(); updateNginapNote(); });
});
// hari kerja juga memengaruhi estimasi durasi & nginap
updateLayPreview();
updateNginapNote();

// ============ VALIDASI STEP 1 (blok) ============
function validasiStep1(){
    var errors=[]; var tanpaTiang=[];
    var panes=[].slice.call(document.querySelectorAll('.opsi-pane'));
    var totalBlokAktif=0;
    for(var p=0;p<panes.length;p++){
        var cards=[].slice.call(panes[p].querySelectorAll('.blok-card'));
        for(var c=0;c<cards.length;c++){
            var card=cards[c];
            var aktifEl=card.querySelector('.b-aktif');
            if(aktifEl && !aktifEl.checked) continue; // blok dimatikan
            totalBlokAktif++;
            var namaEl=card.querySelector('.b-nama');
            var namaBlok=(namaEl && namaEl.value) ? namaEl.value : ('Blok '+(c+1));
            var lebarEl=card.querySelector('.b-lebar');
            if(lebarEl){ // blok kanopi (punya ukuran)
                var lebar=parseFloat(lebarEl.value||'0');
                var panjangEl=card.querySelector('.b-panjang');
                var panjang=parseFloat((panjangEl&&panjangEl.value)||'0');
                if(!(lebar>0)) errors.push(namaBlok+': lebar belum diisi');
                else if(lebar<50) errors.push(namaBlok+': lebar '+lebar+' cm terlalu kecil (minimal 50 cm)');
                if(!(panjang>0)) errors.push(namaBlok+': panjang belum diisi');
                else if(panjang<50) errors.push(namaBlok+': panjang '+panjang+' cm terlalu kecil (minimal 50 cm)');
                var mf=card.querySelector('.b-matFrame');
                if(mf && (mf.value==='' || mf.value==='0')) errors.push(namaBlok+': material Frame belum dipilih');
                var ms=card.querySelector('.b-matSupport');
                if(ms && (ms.value==='' || ms.value==='0')) errors.push(namaBlok+': material Support belum dipilih');
                var mt=card.querySelector('.b-matTiang');
                if(mt && (mt.value==='' || mt.value==='0')) tanpaTiang.push(namaBlok); // tiang boleh kosong -> konfirmasi
            }
        }
    }
    if(totalBlokAktif===0) errors.push('Belum ada blok aktif — tambah minimal 1 blok dulu');
    return { errors:errors, tanpaTiang:tanpaTiang };
}
function tampilDanger(list){
    var box=document.getElementById('wzDanger');
    if(!box){
        box=document.createElement('div');
        box.id='wzDanger';
        box.style.cssText='position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;max-width:440px;width:92%;background:#7f1d1d;border:1px solid #ef4444;border-radius:10px;padding:12px 14px;color:#fff;box-shadow:0 8px 28px rgba(0,0,0,.45)';
        document.body.appendChild(box);
    }
    var svg='<svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="flex:none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="#fecaca" stroke-width="2" stroke-linejoin="round"/><line x1="12" y1="9" x2="12" y2="14" stroke="#fecaca" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#fecaca"/></svg>';
    var items='';
    for(var i=0;i<list.length;i++){ items+='<li style="margin:3px 0">'+list[i]+'</li>'; }
    box.innerHTML='<div style="display:flex;gap:10px;align-items:flex-start">'+svg+'<div style="flex:1"><div style="font-weight:700;margin-bottom:4px">Belum bisa lanjut — perbaiki dulu:</div><ul style="margin:0;padding-left:16px;font-size:13px">'+items+'</ul></div></div>';
    box.style.display='block';
    clearTimeout(window._wzDangerT);
    window._wzDangerT=setTimeout(function(){ box.style.display='none'; }, 7000);
}
function sembunyiDanger(){ var b=document.getElementById('wzDanger'); if(b) b.style.display='none'; }
function namaAtap(id){ for(var i=0;i<ATAP.length;i++){ if(+ATAP[i].id===+id) return ATAP[i].nama; } return 'Atap'; }
function buildPenawaran(){
    if(!window.CMP || !window.CMP.length){ alert('Tekan "Hitung Harga" dulu supaya harga tiap opsi keluar.'); return null; }
    var panes=[].slice.call(document.querySelectorAll('.opsi-pane'));
    var opsiOut=[];
    for(var i=0;i<panes.length;i++){
        var pd=bacaPane(panes[i]);
        var cmp=window.CMP[i]||{};
        var blokOut=[];
        for(var b=0;b<pd.blok.length;b++){
            var bl=pd.blok[b];
            if(bl.aktif===false) continue;
            if(bl.tipe==='kanopi'){
                var atapArr=[];
                var aj=bl.atap_jenis_id||[], al=bl.atap_luas||[];
                for(var a=0;a<aj.length;a++){ atapArr.push(namaAtap(aj[a])+' ('+al[a]+' m2)'); }
                blokOut.push({ nama:bl.nama||('Blok '+(b+1)), ukuran:(bl.lebar_cm||0)+' x '+(bl.panjang_cm||0)+' cm',
                    frame:bl.mat_frame, support:bl.mat_support, tiang:bl.mat_tiang, atap:atapArr });
            } else {
                var items=(bl.manual_items||[]).map(function(m){ return m.nama+' x'+m.qty; });
                blokOut.push({ nama:bl.nama||('Blok '+(b+1)), manual:items });
            }
        }
        opsiOut.push({ nama:pd.nama||('Opsi '+(i+1)), harga:cmp.jual||0, blok:blokOut });
    }
    return { customer:LEAD?LEAD.nama_customer:'', alamat:(LEAD&&LEAD.lokasi_area)?LEAD.lokasi_area:'', opsi:opsiOut };
}
function buatPenawaran(){
    if(!LEAD){ alert('Buka RAB dari lead dulu.'); return; }
    var p=buildPenawaran(); if(!p) return;
    fetch('{{ url("/rab-opsi/simpan-penawaran") }}', {method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ lead_id:LEAD.id, penawaran:JSON.stringify(p) })
    }).then(function(r){ return r.json(); }).then(function(res){
        if(res && res.success){ window.open('{{ url("/penawaran") }}/'+LEAD.id, '_blank'); }
        else { alert('Gagal buat penawaran: '+((res&&res.message)||'error')); }
    }).catch(function(e){ alert('Error: '+e.message); });
}
// blok denah: DenahEditor memanggil onChange tiap kali model berubah (drag sudut, ganti besi, dst).
// Tidak ada mesin hitung-langsung untuk blok kanopi/manual (harga baru dihitung saat tombol
// "Hitung Harga"/navigasi wizard ditekan, lihat wzHitung/wzGo/lanjutFinalisasi -> autoSave()).
// Supaya konsisten (tak menambah jalur hitung-harga baru yang tak ada di kanopi/manual), blok
// denah hanya di-debounce ke autoSave() yang sudah ada, agar draft denah tak hilang saat pindah step.
let _hitungTimer=null;
function jadwalkanHitung(pane){
    clearTimeout(_hitungTimer);
    _hitungTimer=setTimeout(function(){ autoSave(); }, 800);
}
function autoSave(){
    if(!LEAD){ return; }
    try{
        var body = { lead_id: LEAD.id, snapshot: JSON.stringify({ panes: bacaSemuaOpsi() }) };
        fetch('{{ url("/rab-opsi/autosave") }}', {method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body: JSON.stringify(body)});
    }catch(e){}
}
function lanjutFinalisasi(){
    var hasil=validasiStep1();
    if(hasil.errors.length>0){ tampilDanger(hasil.errors); return; }
    sembunyiDanger();
    if(hasil.tanpaTiang.length>0){
        var t = (hasil.tanpaTiang.length===1) ? ('blok "'+hasil.tanpaTiang[0]+'"') : ('blok: '+hasil.tanpaTiang.join(', '));
        if(!confirm('Apakah benar '+t+' memang TANPA tiang?\n\nOK = ya, lanjut\nBatal = perbaiki dulu')) return;
    }
    autoSave();
    wzShow(2);
    hitungHariSemuaOpsi();
}

// ============ WIZARD STEP ============
async function hitungHariSemuaOpsi(){
    var card=document.getElementById('ringkasOpsiCard');
    var box=document.getElementById('ringkasOpsi');
    if(card && box){ card.style.display='block'; box.innerHTML='<div style="color:#94a3b8;font-size:12px">Menghitung hari &amp; tim...</div>'; }
    var panes=[].slice.call(document.querySelectorAll('.opsi-pane'));
    var mEl=document.getElementById('projMargin');
    var margin=+((mEl&&mEl.value)||45);
    var out=[];
    for(var i=0;i<panes.length;i++){
        var res=await hitungSatuOpsi(panes[i], margin);
        out.push({ nama:panes[i].dataset.nama||('Opsi '+(i+1)), data:(res&&res.data)?res.data:null });
    }
    window.LAST_OUT = out;
    renderRingkasOpsi();
}
function renderRingkasOpsi(){
    var card=document.getElementById('ringkasOpsiCard');
    var box=document.getElementById('ringkasOpsi');
    if(!card||!box) return;
    var out=window.LAST_OUT;
    if(!out || !out.length){ card.style.display='none'; box.innerHTML=''; return; }
    var h='<div class="subhead" style="margin-top:0">Estimasi Hari & Tim per Opsi</div>';
    var ada=false;
    for(var i=0;i<out.length;i++){
        var d=out[i].data; if(!d) continue;
        ada=true;
        var hf=d.hari_fab_total||0, hi=d.hari_inst_total||0;
        var nt=d.tukang_max||0, nk=d.kenek_max||0;
        var hiVal=(window.HARI_EDIT && window.HARI_EDIT[i] && window.HARI_EDIT[i].inst>0)?window.HARI_EDIT[i].inst:hi;
        h+='<div style="background:#0f172a;border-radius:8px;padding:8px 10px;margin-bottom:6px">'+
           '<div style="color:#fbbf24;font-weight:700;font-size:13px">'+esc(out[i].nama)+'</div>'+
           '<div style="color:#cbd5e1;font-size:12px;margin-top:2px">Fabrikasi (bengkel): <b>'+hf+' hari</b></div>'+
           '<div style="color:#cbd5e1;font-size:12px;margin-top:2px;display:flex;align-items:center;gap:6px">Instalasi (lokasi): <input type="number" min="0" step="0.5" class="edit-inst" data-opsi="'+i+'" value="'+hiVal+'" style="width:64px;background:#1e293b;border:1px solid #fbbf24;border-radius:6px;color:#f1f5f9;padding:6px;font-size:12px"> hari</div>'+
           '<div style="color:#94a3b8;font-size:12px;margin-top:2px">Tim: '+nt+' tukang + '+nk+' kenek</div>'+
           '</div>';
    }
    if(!ada){ card.style.display='none'; box.innerHTML=''; return; }
    box.innerHTML=h;
    card.style.display='block';
    [].slice.call(box.querySelectorAll('.edit-inst')).forEach(function(inp){
        inp.addEventListener('input', function(){
            var idx=+inp.getAttribute('data-opsi');
            if(!window.HARI_EDIT){ window.HARI_EDIT={}; }
            window.HARI_EDIT[idx]={ inst:(+inp.value||0) };
            updateNginapNote(); updateLayPreview();
        });
    });
}
function wzShow(nStep){
    var ids=['step1','stepD','stepHarga'];
    for(var i=0;i<ids.length;i++){ var e=document.getElementById(ids[i]); if(e) e.style.display=(i===(nStep-1))?'block':'none'; }
    [].slice.call(document.querySelectorAll('.wz-n1')).forEach(function(b){ b.style.display=(nStep===1)?'':'none'; });
    [].slice.call(document.querySelectorAll('.wz-n2')).forEach(function(b){ b.style.display=(nStep===2)?'':'none'; });
    [].slice.call(document.querySelectorAll('.wz-n3')).forEach(function(b){ b.style.display=(nStep===3)?'':'none'; });
    for(var d=1;d<=3;d++){ var dot=document.getElementById('wzDot'+d); if(dot){ dot.style.background=(d===nStep)?'#fbbf24':'#334155'; dot.style.color=(d===nStep)?'#0f172a':'#cbd5e1'; } }
    try{ window.scrollTo(0,0); }catch(e){}
    if(nStep===2){ renderRingkasOpsi(); }
}
function wzGo(nStep){ autoSave(); wzShow(nStep); }
function wzHitung(){ autoSave(); bandingkan(); wzShow(3); }
wzShow(1);
</script>
@endsection