@extends('layouts.app')
@section('title', 'RAB Multi-Blok')
@section('page-title', 'RAB Multi-Blok')
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
.rb-wrap { max-width:1000px; margin:0 auto; padding:16px 16px 140px; }
.rb-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 4px; }
.rb-sub { font-size:12px; color:#64748b; margin:0 0 16px; }
.rb-card { background:#1e293b; border-radius:14px; padding:16px; margin-bottom:14px; }
.rb-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.rb-field label { display:block; font-size:11px; color:#94a3b8; margin-bottom:4px; }
.rb-field input, .rb-field select {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px;
    padding:11px 10px; color:#f1f5f9; font-size:14px; outline:none; min-height:46px; }
.rb-field input:focus, .rb-field select:focus { border-color:#fbbf24; }
.btn { border:none; border-radius:10px; padding:14px; min-height:50px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-grey { background:#334155; color:#e2e8f0; }
.btn-half { width:auto; flex:1; padding:12px; min-height:46px; }
.blok-card { background:#1e293b; border:1px solid #334155; border-radius:14px; margin-bottom:14px; overflow:hidden; }
.blok-card.off { opacity:.5; }
.blok-head { display:flex; align-items:center; gap:10px; padding:12px 14px; background:#0f172a; }
.blok-head .b-nama { flex:1; background:#1e293b; border:1px solid #334155; border-radius:8px; padding:9px 10px; color:#f1f5f9; font-size:14px; font-weight:700; min-height:42px; }
.blok-head .tag { font-size:10px; font-weight:700; padding:3px 8px; border-radius:6px; background:#fbbf24; color:#0f172a; }
.blok-head .tag.manual { background:#a78bfa; }
.blok-body { padding:14px; }
.blok-tools { display:flex; gap:6px; align-items:center; }
.iconbtn { background:none; border:none; cursor:pointer; font-size:18px; padding:4px 6px; }
.sw { position:relative; width:44px; height:26px; flex:none; }
.sw input { opacity:0; width:0; height:0; }
.sw .sl { position:absolute; inset:0; background:#475569; border-radius:26px; transition:.2s; }
.sw .sl:before { content:''; position:absolute; width:20px; height:20px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; }
.sw input:checked + .sl { background:#22c55e; }
.sw input:checked + .sl:before { transform:translateX(18px); }
.ckf { font-size:13px; color:#cbd5e1; display:flex; align-items:center; gap:6px; cursor:pointer; }
.ckf input { width:20px; height:20px; }
.row3 { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
.row3 select, .row3 input { background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; min-height:44px; }
.row3 .del { background:#7f1d1d; color:#fff; border:none; border-radius:8px; width:44px; min-height:44px; cursor:pointer; flex:none; }
.subhead { font-size:12px; color:#fbbf24; margin:14px 0 8px; }
.sum-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #334155; font-size:13px; }
.sum-row b { color:#fbbf24; }
.actbar { position:fixed; left:0; right:0; bottom:0; background:#0f172acc; backdrop-filter:blur(8px); border-top:1px solid #334155; padding:12px 16px; z-index:50; }
.actbar-in { max-width:1000px; margin:0 auto; display:flex; gap:10px; }
</style>

<div class="rb-wrap">
    <h1 class="rb-title">RAB Multi-Blok</h1>
    <p class="rb-sub">Satu project bisa banyak blok (kanopi depan, belakang, pagar, railing, tangga...). Tiap blok bisa dimatikan kalau customer ralat. Semua dijumlahkan jadi satu harga. (Tahap 1 — nanti dibungkus jadi 3 opsi.)</p>

    <div class="rb-card">
        <div class="rb-grid">
            <div class="rb-field"><label>Nama Project / Customer</label><input type="text" id="projNama" placeholder="mis. Rumah Pak Budi"></div>
            @if($lihatHarga)
            <div class="rb-field"><label>Margin (%)</label><input type="number" id="projMargin" value="45" min="0" max="90"></div>
            @endif
        </div>
    </div>

    <div id="blokList"></div>

    <div style="display:flex;gap:10px;margin-bottom:16px">
        <button class="btn btn-grey btn-half" onclick="tambahBlok('kanopi')">+ Blok Kanopi</button>
        <button class="btn btn-grey btn-half" onclick="tambahBlok('manual')">+ Blok Manual (pagar/railing/tangga)</button>
    </div>

    <div id="hasil"></div>
</div>

<div class="actbar"><div class="actbar-in">
    <button class="btn btn-gold" onclick="hitungSemua()">⚙️ Hitung Semua Blok</button>
</div></div>

<script>
const CSRF='{{ csrf_token() }}';
const LIHAT_HARGA = {{ $lihatHarga ? 'true' : 'false' }};
const BESI=@json($besi);            // [{id,nama,harga_pokok}] kategori rangka_besi
const BESI_SEMUA=@json($besiSemua); // semua material aktif
const JK=@json($jenisKerja);
const KOND=@json($kondisi);
const ATAP=@json($atap);
const ADDON=@json($addon);
let blokSeq=0;

function rp(n){ return 'Rp '+Math.round(n||0).toLocaleString('id-ID'); }
function esc(s){ return String(s==null?'':s).replace(/"/g,'&quot;'); }
function hargaOf(nama){ const b=BESI_SEMUA.find(function(x){return x.nama===nama;}); return b?(b.harga_pokok||0):0; }

function besiOpts(){ return '<option value="">— pilih —</option>'+BESI.map(function(b){return `<option value="${esc(b.nama)}">${esc(b.nama)}</option>`;}).join(''); }
function besiSemuaOpts(){ return '<option value="">— pilih besi —</option>'+BESI_SEMUA.map(function(b){return `<option value="${esc(b.nama)}" data-harga="${b.harga_pokok||0}">${esc(b.nama)}</option>`;}).join(''); }
function jkOpts(){ return '<option value="0">— tidak hitung upah —</option>'+JK.map(function(j){return `<option value="${j.id}">${esc(j.nama)}</option>`;}).join(''); }
function atapOpts(){ return '<option value="0">— pilih atap —</option>'+ATAP.map(function(a){return `<option value="${a.id}">${esc(a.nama)}</option>`;}).join(''); }
function addonOpts(){ return '<option value="0">— pilih add-on —</option>'+ADDON.map(function(a){return `<option value="${a.id}">[${a.level||'total'}] ${esc(a.nama)} (${a.formula_type})</option>`;}).join(''); }
function addonFormula(id){ const a=ADDON.find(function(x){return x.id==id;}); return a?a.formula_type:''; }
function kondHtml(){ return KOND.map(function(k){return `<label class="ckf"><input type="checkbox" class="b-kond" value="${k.id}"> ${esc(k.nama)}</label>`;}).join(''); }

// ---- baris atap & add-on (dipakai di dalam tiap blok) ----
function addAtapRow(btn){
    const box=btn.parentNode.querySelector('.b-atapRows');
    const r=document.createElement('div'); r.className='row3';
    r.innerHTML='<select class="atap-jenis" style="flex:2">'+atapOpts()+'</select>'+
                '<input type="number" class="atap-luas" style="flex:1" placeholder="luas m²" min="0" step="0.01">'+
                '<button type="button" class="del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(r);
}
function addAddonRow(btn){
    const box=btn.parentNode.querySelector('.b-addonRows');
    const r=document.createElement('div'); r.className='row3';
    r.innerHTML='<select class="addon-jenis" style="flex:2" onchange="addonSync(this)">'+addonOpts()+'</select>'+
                '<input type="number" class="addon-qty" style="flex:1" placeholder="jumlah" min="0" step="0.01">'+
                '<button type="button" class="del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(r);
}
function addonSync(sel){
    const q=sel.parentNode.querySelector('.addon-qty'); const ft=addonFormula(+sel.value);
    if(q){ q.disabled=(ft==='flat'); q.placeholder=(ft==='flat'?'lumpsum':(ft==='per_meter'?'meter':(ft==='per_m2'?'m²':'jumlah'))); if(ft==='flat') q.value=''; }
}
function addManualRow(btn){
    const box=btn.parentNode.querySelector('.b-manualRows');
    const r=document.createElement('div'); r.className='row3';
    r.innerHTML='<select class="m-nama" style="flex:2" onchange="mNamaSync(this)">'+besiSemuaOpts()+'</select>'+
                '<input type="number" class="m-qty" style="width:70px" placeholder="qty" min="0" step="0.01">'+
                '<input type="number" class="m-harga" style="flex:1" placeholder="harga/satuan" min="0">'+
                '<button type="button" class="del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(r);
}
function mNamaSync(sel){
    const h=sel.parentNode.querySelector('.m-harga');
    const o=sel.options[sel.selectedIndex];
    if(h && o){ const hp=+(o.getAttribute('data-harga')||0); if(hp>0) h.value=hp; }
}

// ---- blok ----
function tambahBlok(tipe){
    blokSeq++;
    const id='blok'+blokSeq;
    const card=document.createElement('div');
    card.className='blok-card'; card.dataset.tipe=tipe; card.id=id;

    let body='';
    if(tipe==='kanopi'){
        body=
        '<div class="rb-grid">'+
          '<div class="rb-field"><label>Lebar (cm)</label><input type="number" class="b-lebar" value="400"></div>'+
          '<div class="rb-field"><label>Panjang (cm)</label><input type="number" class="b-panjang" value="300"></div>'+
          '<div class="rb-field"><label>Tinggi tiang (cm)</label><input type="number" class="b-tinggi" value="300"></div>'+
          '<div class="rb-field"><label>Kotak support (cm)</label><input type="number" class="b-kotak" value="80"></div>'+
          '<div class="rb-field"><label>Arah support</label><select class="b-arah"><option value="2">Grid 2 arah</option><option value="1">1 arah</option></select></div>'+
          '<div class="rb-field"><label>Jumlah tiang</label><input type="number" class="b-tiang" value="2"></div>'+
        '</div>'+
        '<div class="rb-grid" style="margin-top:10px">'+
          '<div class="rb-field"><label>Material Frame</label><select class="b-matFrame">'+besiOpts()+'</select></div>'+
          '<div class="rb-field"><label>Material Support</label><select class="b-matSupport">'+besiOpts()+'</select></div>'+
          '<div class="rb-field"><label>Material Tiang</label><select class="b-matTiang">'+besiOpts()+'</select></div>'+
        '</div>'+
        '<div style="margin-top:12px;padding:12px;background:#0f172a;border-radius:10px">'+
          '<div style="font-size:12px;color:#94a3b8;margin-bottom:8px">Sisi Frame (matikan sisi yang nempel ke blok lain)</div>'+
          '<div style="display:flex;flex-wrap:wrap;gap:14px">'+
            '<label class="ckf"><input type="checkbox" class="b-fDepan" checked> Depan</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fBelakang" checked> Belakang</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fKiri" checked> Kiri</label>'+
            '<label class="ckf"><input type="checkbox" class="b-fKanan" checked> Kanan</label>'+
            '<label class="ckf" style="color:#fbbf24"><input type="checkbox" class="b-fTengah" checked> + Frame Tengah</label>'+
          '</div>'+
        '</div>'+
        (LIHAT_HARGA ? (
        '<div style="margin-top:12px;padding:12px;background:#0f172a;border-radius:10px">'+
          '<div class="subhead" style="margin-top:0">Upah (owner)</div>'+
          '<div class="rb-field"><label>Jenis Kerja</label><select class="b-jk">'+jkOpts()+'</select></div>'+
          (KOND.length ? '<div style="margin-top:8px;font-size:11px;color:#94a3b8;margin-bottom:6px">Kondisi kerja</div><div style="display:flex;flex-wrap:wrap;gap:14px">'+kondHtml()+'</div>' : '')+
        '</div>') : '');
    } else {
        body=
        '<div style="font-size:12px;color:#a78bfa;margin-bottom:8px">Mode manual — isi daftar besi/bahan langsung (sementara, sampai resep otomatis dibuat).</div>'+
        '<div class="b-manualRows"></div>'+
        '<button type="button" class="btn btn-grey" style="padding:10px;margin-top:4px" onclick="addManualRow(this)">+ Tambah Item Besi/Bahan</button>'+
        (LIHAT_HARGA ? '<div class="rb-field" style="margin-top:12px"><label>Upah pasang (lumpsum, Rp)</label><input type="number" class="b-manualUpah" value="0" min="0"></div>' : '');
    }

    // bagian atap & add-on (sama untuk kedua tipe, owner only)
    let extra='';
    if(LIHAT_HARGA){
        if(ATAP.length){
            extra+='<div style="margin-top:12px;padding:12px;background:#0f172a;border-radius:10px">'+
                   '<div class="subhead" style="margin-top:0">Atap (boleh >1)</div><div class="b-atapRows"></div>'+
                   '<button type="button" class="btn btn-grey" style="padding:9px" onclick="addAtapRow(this)">+ Atap</button></div>';
        }
        if(ADDON.length){
            extra+='<div style="margin-top:12px;padding:12px;background:#0f172a;border-radius:10px">'+
                   '<div class="subhead" style="margin-top:0">Add-on</div><div class="b-addonRows"></div>'+
                   '<button type="button" class="btn btn-grey" style="padding:9px" onclick="addAddonRow(this)">+ Add-on</button></div>';
        }
    }

    card.innerHTML=
        '<div class="blok-head">'+
          '<span class="tag '+(tipe==='manual'?'manual':'')+'">'+(tipe==='manual'?'MANUAL':'KANOPI')+'</span>'+
          '<input type="text" class="b-nama" placeholder="Nama blok (mis. Kanopi depan)" value="'+(tipe==='manual'?'Pagar/Railing':'Kanopi')+'">'+
          '<div class="blok-tools">'+
            '<label class="sw"><input type="checkbox" class="b-aktif" checked><span class="sl"></span></label>'+
            '<button class="iconbtn" title="Lipat" onclick="lipat(this)">▾</button>'+
            '<button class="iconbtn" title="Hapus" onclick="hapusBlok(this)">🗑️</button>'+
          '</div>'+
        '</div>'+
        '<div class="blok-body">'+body+extra+'</div>';

    document.getElementById('blokList').appendChild(card);
    // default material tebakan utk kanopi
    if(tipe==='kanopi'){
        const cari=function(kw){ const b=BESI.find(function(x){return x.nama.toLowerCase().replace(/\s/g,'').includes(kw);}); return b?b.nama:''; };
        const f=cari('5x10'); const mf=card.querySelector('.b-matFrame'), mt=card.querySelector('.b-matTiang');
        if(f && mf) mf.value=f; if(f && mt) mt.value=f;
    }
    // toggle aktif -> dim
    card.querySelector('.b-aktif').addEventListener('change',function(){ card.classList.toggle('off', !this.checked); });
}

function lipat(btn){ const b=btn.closest('.blok-card').querySelector('.blok-body'); b.style.display=(b.style.display==='none'?'block':'none'); btn.textContent=(b.style.display==='none'?'▸':'▾'); }
function hapusBlok(btn){ if(confirm('Hapus blok ini?')) btn.closest('.blok-card').remove(); }

// ---- kumpulkan & hitung ----
function bacaRows(card, sel, fn){ return [].slice.call(card.querySelectorAll(sel)).map(fn); }

function kumpulBlok(card){
    const tipe=card.dataset.tipe;
    const g=function(c){ const el=card.querySelector(c); return el?el.value:''; };
    const ck=function(c){ const el=card.querySelector(c); return el?el.checked:true; };
    const b={ aktif:ck('.b-aktif'), tipe:tipe, nama:g('.b-nama') };

    if(tipe==='kanopi'){
        const mf=g('.b-matFrame'), ms=g('.b-matSupport'), mt=g('.b-matTiang');
        const harga={}; [mf,ms,mt].forEach(function(n){ if(n) harga[n]=hargaOf(n); });
        b.lebar_cm=+g('.b-lebar'); b.panjang_cm=+g('.b-panjang'); b.tinggi_cm=+g('.b-tinggi');
        b.kotak_cm=+g('.b-kotak'); b.arah_support=+g('.b-arah'); b.jml_tiang=+g('.b-tiang');
        b.mat_frame=mf||'Frame'; b.mat_support=ms||'Support'; b.mat_tiang=mt||'Tiang';
        b.frame_depan=ck('.b-fDepan'); b.frame_belakang=ck('.b-fBelakang');
        b.frame_kiri=ck('.b-fKiri'); b.frame_kanan=ck('.b-fKanan'); b.frame_tengah=ck('.b-fTengah');
        b.harga=harga;
        b.jenis_kerja_id=+g('.b-jk')||0;
        b.kondisi_ids=[].slice.call(card.querySelectorAll('.b-kond:checked')).map(function(c){return +c.value;});
    } else {
        b.manual_items=bacaRows(card, '.b-manualRows .row3', function(r){
            return { nama:(r.querySelector('.m-nama')||{}).value||'', qty:+((r.querySelector('.m-qty')||{}).value||0), harga:+((r.querySelector('.m-harga')||{}).value||0) };
        }).filter(function(x){ return x.nama && x.qty>0; });
        b.manual_upah=+g('.b-manualUpah')||0;
    }

    b.atap_jenis_id=[]; b.atap_luas=[];
    bacaRows(card, '.b-atapRows .row3', function(r){
        const j=r.querySelector('.atap-jenis'), l=r.querySelector('.atap-luas');
        if(j && +j.value>0 && l && +l.value>0){ b.atap_jenis_id.push(+j.value); b.atap_luas.push(+l.value); }
    });
    b.addon_id=[]; b.addon_qty=[];
    bacaRows(card, '.b-addonRows .row3', function(r){
        const j=r.querySelector('.addon-jenis'), q=r.querySelector('.addon-qty');
        if(j && +j.value>0){ b.addon_id.push(+j.value); b.addon_qty.push(q?(+q.value||0):0); }
    });
    return b;
}

async function hitungSemua(){
    const cards=[].slice.call(document.querySelectorAll('.blok-card'));
    if(!cards.length){ alert('Tambah minimal satu blok dulu.'); return; }
    const mEl=document.getElementById('projMargin');
    const body={ margin_persen:+((mEl&&mEl.value)||45), blok:cards.map(kumpulBlok) };
    try{
        const r=await fetch('{{ url("/rab-blok/hitung") }}',{method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body:JSON.stringify(body)});
        const res=await r.json();
        if(res.success) render(res.data); else document.getElementById('hasil').innerHTML='<div class="rb-card">Gagal hitung</div>';
    }catch(e){ document.getElementById('hasil').innerHTML='<div class="rb-card">Error: '+e.message+'</div>'; }
}

function render(d){
    let h='';
    if(d.peringatan && d.peringatan.length){
        h+='<div class="rb-card" style="background:#7f1d1d">'+
           '<div style="color:#fecaca;font-size:12px">⚠ '+d.peringatan.join('<br>⚠ ')+'</div></div>';
    }
    d.blok.forEach(function(bk){
        h+='<div class="rb-card" style="'+(bk.aktif?'':'opacity:.55')+'">';
        h+='<div class="mat-head" style="display:flex;justify-content:space-between;font-weight:700;color:#f1f5f9;margin-bottom:8px">'+
           '<span>Blok '+bk.urut+': '+esc(bk.nama)+' '+(bk.aktif?'':'<span style="color:#f87171">(dimatikan)</span>')+'</span>'+
           (LIHAT_HARGA?'<span style="color:#fbbf24">'+rp(bk.pokok_blok)+'</span>':'')+'</div>';
        if(bk.tipe==='kanopi' && bk.cutting){
            h+='<div class="sum-row"><span>Total batang besi</span><span>'+bk.cutting.total_batang+' batang</span></div>';
        }
        if(bk.tipe==='manual' && bk.rincian){
            bk.rincian.forEach(function(it){ h+='<div class="sum-row"><span>'+esc(it.nama)+' ('+it.qty+'×'+rp(it.harga)+')</span><span>'+rp(it.subtotal)+'</span></div>'; });
        }
        if(LIHAT_HARGA){
            if(bk.besi>0) h+='<div class="sum-row"><span>Besi</span><span>'+rp(bk.besi)+'</span></div>';
            if(bk.upah>0) h+='<div class="sum-row"><span>Upah</span><span>'+rp(bk.upah)+'</span></div>';
            (bk.atap||[]).forEach(function(a){ h+='<div class="sum-row"><span>Atap — '+esc(a.nama)+' ('+a.luas+' m²)</span><span>'+rp(a.subtotal)+'</span></div>'; });
            (bk.addon||[]).forEach(function(a){ h+='<div class="sum-row"><span>Add-on — '+esc(a.nama)+(a.formula==='flat'?' (lumpsum)':' ('+a.qty+' '+(a.satuan||'')+')')+'</span><span>'+rp(a.biaya)+'</span></div>'; });
        }
        h+='</div>';
    });
    if(LIHAT_HARGA && d.jual!=null){
        h+='<div class="rb-card" style="border:1px solid #fbbf24">';
        h+='<div class="sum-row"><span><b>Total biaya pokok (blok aktif)</b></span><span><b>'+rp(d.pokok)+'</b></span></div>';
        h+='<div class="sum-row"><span>Margin</span><span>'+d.margin_persen+'%</span></div>';
        h+='<div class="sum-row" style="font-size:17px"><span><b style="color:#fbbf24">HARGA JUAL PROJECT</b></span><span><b style="color:#fbbf24">'+rp(d.jual)+'</b></span></div>';
        h+='<div style="font-size:11px;color:#64748b;margin-top:6px">Margin sekali di atas total semua blok aktif. Blok yang dimatikan tidak ikut dihitung.</div>';
        h+='</div>';
    }
    document.getElementById('hasil').innerHTML=h;
}

// mulai dengan 1 blok kanopi
tambahBlok('kanopi');
</script>
@endsection