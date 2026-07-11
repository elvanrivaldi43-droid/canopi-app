@extends('layouts.app')
@section('title', 'Kalkulator Potong Besi')
@section('page-title', 'Kalkulator Potong Besi')
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
.ct-wrap { max-width:1000px; margin:0 auto; padding:16px 16px 120px; }
.ct-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 4px; }
.ct-sub { font-size:12px; color:#64748b; margin:0 0 16px; }
.ct-card { background:#1e293b; border-radius:14px; padding:16px; margin-bottom:16px; }
.ct-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.ct-field label { display:block; font-size:11px; color:#94a3b8; margin-bottom:4px; }
.ct-field input, .ct-field select {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px;
    padding:11px 10px; color:#f1f5f9; font-size:14px; outline:none; min-height:46px; }
.ct-field input:focus, .ct-field select:focus { border-color:#fbbf24; }
.btn { border:none; border-radius:10px; padding:14px; min-height:50px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
.btn-gold { background:#fbbf24; color:#0f172a; margin-top:12px; }
.sum-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #334155; font-size:13px; }
.sum-row b { color:#fbbf24; }
.mat-head { font-size:14px; font-weight:700; color:#f1f5f9; margin:14px 0 8px; display:flex; justify-content:space-between; }
.mat-head span.badge { background:#fbbf24; color:#0f172a; border-radius:6px; padding:2px 10px; font-size:12px; }
/* bar potong */
.bar { display:flex; height:38px; border-radius:6px; overflow:hidden; margin-bottom:6px;
       border:1px solid #334155; background:#0f172a; }
.seg { display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;
       color:#0f172a; border-right:1px solid #0f172a99; overflow:hidden; white-space:nowrap; padding:0 2px; }
.seg.utuh { background:#60a5fa; }
.seg.sambung { background:#fbbf24; }
.seg.sisa { background:#334155; color:#94a3b8; }
.bar-label { font-size:11px; color:#94a3b8; margin:8px 0 3px; }
.barwrap { margin-bottom:10px; }
.note-sambung { font-size:11px; color:#fbbf24; margin-top:2px; }
.cut-list { list-style:none; margin:6px 0 0; padding:0; }
.cut-list li { font-size:12px; color:#cbd5e1; padding:3px 0; border-bottom:1px dashed #1e293b; }
.cut-list li b { color:#f1f5f9; }
.tag-sambung { color:#fbbf24; font-size:11px; }
.tag-sisa { color:#64748b; font-style:italic; }
.ckf { font-size:13px; color:#cbd5e1; display:flex; align-items:center; gap:6px; cursor:pointer; }
.ckf input { width:20px; height:20px; }
.atap-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
.atap-row .atap-jenis { flex:2; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; min-height:44px; }
.atap-row .atap-luas { flex:1; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; min-height:44px; }
.atap-row .atap-del { background:#7f1d1d; color:#fff; border:none; border-radius:8px; width:44px; min-height:44px; cursor:pointer; }
.legend { font-size:11px; color:#64748b; margin:6px 0 0; display:flex; gap:14px; flex-wrap:wrap; }
.legend i { display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:middle; margin-right:4px; }
</style>

<div class="ct-wrap">
    <h1 class="ct-title">Kalkulator Potong Besi</h1>
    <p class="ct-sub">Input ukuran → jumlah batang otomatis + cutting list bergaris. Batang 600cm, maksimal 1 sambungan/potong. (Halaman uji — nanti masuk ke wizard block.)</p>

    <div class="ct-card">
        <div class="ct-grid">
            <div class="ct-field"><label>Lebar (cm)</label><input type="number" id="lebar" value="500"></div>
            <div class="ct-field"><label>Panjang (cm)</label><input type="number" id="panjang" value="400"></div>
            <div class="ct-field"><label>Tinggi tiang (cm)</label><input type="number" id="tinggi" value="300"></div>
            <div class="ct-field"><label>Kotak support (cm)</label><input type="number" id="kotak" value="80"></div>
            <div class="ct-field"><label>Arah support</label>
                <select id="arah"><option value="2">Grid 2 arah</option><option value="1">1 arah saja</option></select>
            </div>
            <div class="ct-field"><label>Jumlah tiang (titik)</label><input type="number" id="tiang" value="2"></div>
        </div>
        <div class="ct-grid" style="margin-top:10px">
            <div class="ct-field"><label>Material Frame</label>
                <select id="matFrame" class="matsel"></select>
            </div>
            <div class="ct-field"><label>Material Support</label>
                <select id="matSupport" class="matsel"></select>
            </div>
            <div class="ct-field"><label>Material Tiang</label>
                <select id="matTiang" class="matsel"></select>
            </div>
        </div>

        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:10px">
            <div style="font-size:12px;color:#94a3b8;margin-bottom:8px">Sisi Frame (centang yang dipasang)</div>
            <div style="display:flex;flex-wrap:wrap;gap:14px">
                <label class="ckf"><input type="checkbox" id="fDepan" checked> Depan</label>
                <label class="ckf"><input type="checkbox" id="fBelakang" checked> Belakang</label>
                <label class="ckf"><input type="checkbox" id="fKiri" checked> Kiri</label>
                <label class="ckf"><input type="checkbox" id="fKanan" checked> Kanan</label>
                <label class="ckf" style="color:#fbbf24"><input type="checkbox" id="fTengah" checked> + Frame Tengah</label>
            </div>
            <div style="font-size:11px;color:#475569;margin-top:6px">Frame tengah ON = palang silang di tengah; support otomatis berkurang 1 di tiap arah.</div>
        </div>

        @if($lihatHarga)
        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:10px">
            <div style="font-size:12px;color:#fbbf24;margin-bottom:8px">Upah & Harga Jual (hanya owner)</div>
            @if($jenisKerja->isEmpty())
                <div style="font-size:12px;color:#f87171">Tabel produktivitas belum ada / kosong. Buka /produktivitas dan isi dulu.</div>
            @else
            <div class="ct-grid">
                <div class="ct-field"><label>Jenis Kerja (upah)</label>
                    <select id="jenisKerja">
                        <option value="0">— tidak hitung upah —</option>
                        @foreach($jenisKerja as $jk)
                            <option value="{{ $jk->id }}">{{ $jk->nama }} ({{ $jk->satuan }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="ct-field"><label>Margin (%)</label><input type="number" id="margin" value="45" min="0" max="90"></div>
            </div>
            @if($kondisi->count())
            <div style="margin-top:10px">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:6px">Kondisi kerja (centang jika ada)</div>
                <div style="display:flex;flex-wrap:wrap;gap:14px">
                    @foreach($kondisi as $k)
                        <label class="ckf"><input type="checkbox" class="kond" value="{{ $k->id }}"> {{ $k->nama }}</label>
                    @endforeach
                </div>
            </div>
            @endif
            @endif
        </div>
        @endif

        @if($lihatHarga && $atap->count())
        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:10px">
            <div style="font-size:12px;color:#fbbf24;margin-bottom:8px">Atap (boleh lebih dari 1 jenis)</div>
            <div id="atapRows"></div>
            <button type="button" class="btn" style="background:#334155;color:#e2e8f0;margin-top:8px;padding:10px" onclick="tambahAtap()">+ Tambah Bagian Atap</button>
            <div style="font-size:11px;color:#475569;margin-top:6px">Isi luas tiap bagian (m²). Harga/m², pemborosan & upah pasang diambil dari /master-atap.</div>
        </div>
        @endif

        @if($lihatHarga && $addon->count())
        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:10px">
            <div style="font-size:12px;color:#fbbf24;margin-bottom:8px">Add-on (talang, kaca film, tiang tambahan, plafon, dll)</div>
            <div id="addonRows"></div>
            <button type="button" class="btn" style="background:#334155;color:#e2e8f0;margin-top:8px;padding:10px" onclick="tambahAddon()">+ Tambah Add-on</button>
            <div style="font-size:11px;color:#475569;margin-top:6px">Pilih add-on, isi jumlah sesuai satuannya (lumpsum otomatis terisi). Harga modal diambil dari data add-on, ikut kena margin.</div>
        </div>
        @endif

        <button class="btn btn-gold" onclick="hitung()">⚙️ Hitung</button>
        <button class="btn" style="background:#334155;color:#e2e8f0;margin-top:8px" onclick="cetak()">🖨️ Cetak / Simpan PDF Cutting List</button>
    </div>

    {{-- form tersembunyi untuk buka halaman cetak di tab baru --}}
    <form id="formCetak" method="POST" action="{{ url('/cutting-test/cetak') }}" target="_blank" style="display:none">
        @csrf
        <input type="hidden" name="lebar_cm" id="c_lebar">
        <input type="hidden" name="panjang_cm" id="c_panjang">
        <input type="hidden" name="tinggi_cm" id="c_tinggi">
        <input type="hidden" name="kotak_cm" id="c_kotak">
        <input type="hidden" name="arah_support" id="c_arah">
        <input type="hidden" name="jml_tiang" id="c_tiang">
        <input type="hidden" name="mat_frame" id="c_mf">
        <input type="hidden" name="mat_support" id="c_ms">
        <input type="hidden" name="mat_tiang" id="c_mt">
        <input type="hidden" name="frame_depan" id="c_fd">
        <input type="hidden" name="frame_belakang" id="c_fb">
        <input type="hidden" name="frame_kiri" id="c_fki">
        <input type="hidden" name="frame_kanan" id="c_fka">
        <input type="hidden" name="frame_tengah" id="c_ft">
        <input type="hidden" name="jenis_kerja_id" id="c_jk">
        <input type="hidden" name="margin_persen" id="c_mg">
        <span id="c_kond_box"></span>
        <input type="hidden" name="judul" id="c_judul" value="Cutting List Rangka Kanopi">
    </form>

    <div id="hasil"></div>
</div>

<script>
const CSRF='{{ csrf_token() }}';
const BESI=@json($besi); // [{id,nama,harga_pokok}]
const LIHAT_HARGA=@json($lihatHarga);
const ATAP=@json($atap); // [{id,nama,harga_per_m2,pemborosan_persen,upah_pasang_per_m2}]
const ADDON=@json($addon); // [{id,nama,satuan,formula_type,harga_pokok_satuan,level}]
const STOCK=600;

function atapOpts(){
    return '<option value="0">— pilih atap —</option>'+ATAP.map(function(a){return `<option value="${a.id}">${a.nama}</option>`;}).join('');
}
function tambahAtap(){
    const box=document.getElementById('atapRows'); if(!box) return;
    const row=document.createElement('div');
    row.className='atap-row';
    row.innerHTML='<select class="atap-jenis">'+atapOpts()+'</select>'+
                  '<input type="number" class="atap-luas" placeholder="luas m²" min="0" step="0.01">'+
                  '<button type="button" class="atap-del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(row);
}

function addonFormula(id){ const a=ADDON.find(function(x){return x.id==id;}); return a?a.formula_type:''; }
function addonOpts(){
    return '<option value="0">— pilih add-on —</option>'+ADDON.map(function(a){return `<option value="${a.id}">[${a.level||'total'}] ${a.nama} (${a.formula_type})</option>`;}).join('');
}
function addonPh(ft){
    if(ft==='per_meter') return 'panjang (m)';
    if(ft==='per_m2')    return 'luas (m²)';
    if(ft==='flat')      return 'lumpsum (otomatis 1)';
    return 'jumlah'; // per_unit
}
function tambahAddon(){
    const box=document.getElementById('addonRows'); if(!box) return;
    const row=document.createElement('div');
    row.className='atap-row';
    row.innerHTML='<select class="addon-jenis" onchange="addonSync(this)">'+addonOpts()+'</select>'+
                  '<input type="number" class="addon-qty" placeholder="jumlah" min="0" step="0.01">'+
                  '<button type="button" class="atap-del" onclick="this.parentNode.remove()">✕</button>';
    box.appendChild(row);
}
function addonSync(sel){
    const q=sel.parentNode.querySelector('.addon-qty');
    const ft=addonFormula(+sel.value);
    if(q){ q.placeholder=addonPh(ft); q.disabled=(ft==='flat'); if(ft==='flat') q.value=''; }
}
function hapusAtap(b){ b.parentNode.remove(); }

function cetak(){
    c_lebar.value=lebar.value; c_panjang.value=panjang.value; c_tinggi.value=tinggi.value;
    c_kotak.value=kotak.value; c_arah.value=arah.value; c_tiang.value=tiang.value;
    c_mf.value=matFrame.value||'Frame'; c_ms.value=matSupport.value||'Support'; c_mt.value=matTiang.value||'Tiang';
    c_fd.value=fDepan.checked?1:0; c_fb.value=fBelakang.checked?1:0;
    c_fki.value=fKiri.checked?1:0; c_fka.value=fKanan.checked?1:0; c_ft.value=fTengah.checked?1:0;
    const jkEl=document.getElementById('jenisKerja');
    const box=document.getElementById('c_kond_box'); box.innerHTML='';
    if(jkEl){
        c_jk.value=+jkEl.value||0;
        var mEl=document.getElementById('margin');
        c_mg.value=+((mEl&&mEl.value)||45);
        [].slice.call(document.querySelectorAll('.kond:checked')).forEach(function(c){
            const i=document.createElement('input'); i.type='hidden'; i.name='kondisi_ids[]'; i.value=c.value; box.appendChild(i);
        });
    }
    [].slice.call(document.querySelectorAll('.atap-row')).forEach(function(r){
        const j=r.querySelector('.atap-jenis'), l=r.querySelector('.atap-luas');
        if(j && +j.value>0 && l && +l.value>0){
            const i1=document.createElement('input'); i1.type='hidden'; i1.name='atap_jenis_id[]'; i1.value=j.value; box.appendChild(i1);
            const i2=document.createElement('input'); i2.type='hidden'; i2.name='atap_luas[]'; i2.value=l.value; box.appendChild(i2);
        }
    });
    [].slice.call(document.querySelectorAll('#addonRows .atap-row')).forEach(function(r){
        const j=r.querySelector('.addon-jenis'), q=r.querySelector('.addon-qty');
        if(j && +j.value>0){
            const i1=document.createElement('input'); i1.type='hidden'; i1.name='addon_id[]'; i1.value=j.value; box.appendChild(i1);
            const i2=document.createElement('input'); i2.type='hidden'; i2.name='addon_qty[]'; i2.value=(q?q.value:0)||0; box.appendChild(i2);
        }
    });
    document.getElementById('formCetak').submit();
}

// isi dropdown material
function fillMat(){
    const opts = '<option value="">— pilih —</option>' +
        BESI.map(b=>`<option value="${b.nama}" data-harga="${b.harga_pokok||0}">${b.nama}</option>`).join('');
    document.querySelectorAll('.matsel').forEach(s=>s.innerHTML=opts);
    // default tebakan: cari yang ada "5x10" / "5 x 10" untuk frame & tiang
    const cari=(kw)=>BESI.find(b=>b.nama.toLowerCase().replace(/\s/g,'').includes(kw));
    const f=cari('5x10'); const t=cari('5x10')||cari('4x8');
    if(f){ matFrame.value=f.nama; matTiang.value=(t?t.nama:f.nama); }
}
fillMat();

function hargaOf(nama){ const b=BESI.find(x=>x.nama===nama); return b?(b.harga_pokok||0):0; }
function rp(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID'); }

async function hitung(){
    const harga={};
    [matFrame.value,matSupport.value,matTiang.value].forEach(n=>{ if(n) harga[n]=hargaOf(n); });
    const body={
        lebar_cm:+lebar.value, panjang_cm:+panjang.value, tinggi_cm:+tinggi.value,
        kotak_cm:+kotak.value, arah_support:+arah.value, jml_tiang:+tiang.value,
        mat_frame:matFrame.value||'Frame', mat_support:matSupport.value||'Support', mat_tiang:matTiang.value||'Tiang',
        frame_depan:fDepan.checked, frame_belakang:fBelakang.checked,
        frame_kiri:fKiri.checked, frame_kanan:fKanan.checked, frame_tengah:fTengah.checked,
        harga
    };
    // data upah (hanya ada kalau owner)
    const jkEl=document.getElementById('jenisKerja');
    if(jkEl){
        body.jenis_kerja_id=+jkEl.value||0;
        var mEl=document.getElementById('margin');
        body.margin_persen=+((mEl&&mEl.value)||45);
        body.kondisi_ids=[].slice.call(document.querySelectorAll('.kond:checked')).map(function(c){return +c.value;});
    }
    body.atap_jenis_id=[]; body.atap_luas=[];
    [].slice.call(document.querySelectorAll('.atap-row')).forEach(function(r){
        const j=r.querySelector('.atap-jenis'), l=r.querySelector('.atap-luas');
        if(j && +j.value>0 && l && +l.value>0){ body.atap_jenis_id.push(+j.value); body.atap_luas.push(+l.value); }
    });
    body.addon_id=[]; body.addon_qty=[];
    [].slice.call(document.querySelectorAll('#addonRows .atap-row')).forEach(function(r){
        const j=r.querySelector('.addon-jenis'), q=r.querySelector('.addon-qty');
        if(j && +j.value>0){ body.addon_id.push(+j.value); body.addon_qty.push(q? (+q.value||0):0); }
    });
    const r=await fetch('{{ url("/cutting-test/hitung") }}',{method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify(body)});
    const res=await r.json();
    if(res.success) render(res.data); else document.getElementById('hasil').innerHTML='<div class="ct-card">Gagal hitung</div>';
}

const JOIN_COLORS=['#f59e0b','#22c55e','#ec4899','#06b6d4','#a855f7','#ef4444','#84cc16','#f97316','#14b8a6','#eab308'];
function joinColor(jid){ return JOIN_COLORS[(jid-1)%JOIN_COLORS.length]; }
function letter(jid){ return String.fromCharCode(64+jid); }

function drawDenah(dn){
    if(!dn||!dn.L||!dn.P) return '';
    const maxW=340, sc=maxW/dn.L, x0=64, y0=42, W=dn.L*sc, H=dn.P*sc;
    const PX=cm=>x0+cm*sc, PY=cm=>y0+cm*sc;
    const vbW=x0+W+90, vbH=y0+H+54;
    let s=`<svg width="100%" viewBox="0 0 ${vbW} ${vbH}" style="max-width:520px">`;
    s+=`<text x="${x0+W/2}" y="22" text-anchor="middle" fill="#94a3b8" font-size="12">Lebar ${dn.L}cm · kotak ±${dn.kotak_l}×${dn.kotak_p}cm</text>`;
    s+=`<text x="${x0-46}" y="${y0+H/2}" text-anchor="middle" fill="#94a3b8" font-size="12">${dn.P}cm</text>`;
    dn.h.forEach(l=>{ const y=PY(l.y); const f=l.tipe==='frame';
        s+=`<line x1="${x0}" y1="${y}" x2="${x0+W}" y2="${y}" stroke="${f?'#BA7517':'#378ADD'}" stroke-width="${f?4:2}"/>`;
        if(!f) s+=`<text x="${x0+4}" y="${y-3}" fill="#185FA5" font-size="10">${l.nama}</text>`;
    });
    dn.v.forEach(l=>{ const x=PX(l.x); const f=l.tipe==='frame';
        s+=`<line x1="${x}" y1="${y0}" x2="${x}" y2="${y0+H}" stroke="${f?'#BA7517':'#378ADD'}" stroke-width="${f?4:2}"/>`;
        if(!f) s+=`<text x="${x}" y="${y0-4}" text-anchor="middle" fill="#185FA5" font-size="10">${l.nama}</text>`;
    });
    (dn.tiang||[]).forEach(t=>{ s+=`<circle cx="${PX(t.x)}" cy="${PY(t.y)}" r="6" fill="#854F0B"/>`; });
    s+=`<text x="${x0+W/2}" y="${y0-26}" text-anchor="middle" fill="#BA7517" font-size="11">Frame depan</text>`;
    s+=`<text x="${x0+W/2}" y="${y0+H+18}" text-anchor="middle" fill="#BA7517" font-size="11">Frame belakang</text>`;
    s+=`<text x="${x0-8}" y="${y0+14}" text-anchor="end" fill="#BA7517" font-size="11">kiri</text>`;
    s+=`<text x="${x0+W+8}" y="${y0+14}" fill="#BA7517" font-size="11">kanan</text>`;
    s+='</svg>';
    return s;
}
function drawSamping(dn){
    if(!dn||!dn.T||!(dn.tiang||[]).length) return '';
    const maxW=340, sc=maxW/dn.L, x0=30, yTop=30, W=dn.L*sc, Hs=Math.min(dn.T*sc,150);
    const PX=cm=>x0+cm*sc, yGround=yTop+Hs;
    let s=`<svg width="100%" viewBox="0 0 ${x0+W+30} ${yGround+30}" style="max-width:520px">`;
    s+=`<line x1="${x0}" y1="${yTop}" x2="${x0+W}" y2="${yTop}" stroke="#BA7517" stroke-width="4"/>`;
    s+=`<text x="${x0+W/2}" y="${yTop-8}" text-anchor="middle" fill="#BA7517" font-size="11">Frame (tampak samping)</text>`;
    dn.tiang.forEach(t=>{ const x=PX(t.x);
        s+=`<line x1="${x}" y1="${yTop}" x2="${x}" y2="${yGround}" stroke="#854F0B" stroke-width="5"/>`;
        s+=`<circle cx="${x}" cy="${yTop}" r="5" fill="#854F0B"/>`;
    });
    s+=`<line x1="${x0+W+12}" y1="${yTop}" x2="${x0+W+12}" y2="${yGround}" stroke="#94a3b8" stroke-width="1"/>`;
    s+=`<text x="${x0+W+18}" y="${(yTop+yGround)/2}" fill="#94a3b8" font-size="11">tinggi ${dn.T}cm</text>`;
    s+=`<line x1="${x0}" y1="${yGround}" x2="${x0+W}" y2="${yGround}" stroke="#475569" stroke-width="1" stroke-dasharray="4 3"/>`;
    s+='</svg>';
    return s;
}

function render(d){
    let h='<div class="ct-card">';
    h+='<div class="mat-head" style="margin-top:0">Denah Rangka</div>';
    h+=drawDenah(d.denah);
    const sv=drawSamping(d.denah); if(sv){ h+='<div style="margin-top:10px;border-top:1px solid #334155;padding-top:8px"></div>'+sv; }
    h+='</div>';

    h+='<div class="ct-card">';
    h+='<div class="mat-head" style="margin-top:0">Ringkasan Jumlah <span class="badge">Total '+d.total_batang+' batang</span></div>';
    const r=d.rincian_jumlah;
    if(d.denah && d.denah.kotak_l){ h+=`<div class="sum-row"><span>Kotak support (disesuaikan simetris)</span><span><b>${Math.round(d.denah.kotak_l)} × ${Math.round(d.denah.kotak_p)} cm</b></span></div>`; }
    const baris=(nm,o)=> (o&&o.qty>0) ? `<div class="sum-row"><span>${nm}</span><span>${o.qty} potong @ ${o.len} cm</span></div>`:'';
    h+=baris('Frame vertikal (kiri/kanan/tengah)',r.frame_vertikal)
      +baris('Frame horizontal (depan/belakang/tengah)',r.frame_horizontal)
      +baris('Support vertikal',r.support_vertikal)+baris('Support horizontal',r.support_horizontal)
      +baris('Tiang',r.tiang);
    if(LIHAT_HARGA && d.total_biaya_besi>0){ h+=`<div class="sum-row"><span><b>Estimasi biaya besi</b></span><span><b>${rp(d.total_biaya_besi)}</b></span></div>`; }
    h+='</div>';

    if(d.harga){ const g=d.harga;
        h+='<div class="ct-card">';
        h+='<div class="mat-head" style="margin-top:0">Harga Jual (besi + upah + atap)</div>';
        if(g.peringatan && g.peringatan.length){
            h+='<div style="background:#7f1d1d;color:#fecaca;border-radius:8px;padding:10px;font-size:12px;margin-bottom:8px">⚠ '+g.peringatan.join('<br>⚠ ')+'<br><b>Isi angka yang kosong (/produktivitas atau /master-atap) agar harga benar.</b></div>';
        }
        h+=`<div class="sum-row"><span>Biaya besi</span><span>${rp(g.besi)}</span></div>`;
        if(g.rangka){ const u=g.rangka;
            h+=`<div class="sum-row"><span>Upah rangka — ${u.jenis_kerja}</span><span>${rp(g.upah_rangka)}</span></div>`;
            h+=`<div class="sum-row" style="font-size:11px;color:#94a3b8"><span>↳ ${u.luas} m² ÷ ${u.produktivitas||'-'} = ${u.hari} hari · ${u.jml_tukang}tk+${u.jml_kenek}kn${(u.kondisi&&u.kondisi.length)?(' · '+u.kondisi.join(', ')+' ×'+u.pengali):''}</span><span></span></div>`;
        }
        if(g.atap && g.atap.length){
            g.atap.forEach(a=>{
                h+=`<div class="sum-row"><span>Atap — ${a.nama} (${a.luas} m²)</span><span>${rp(a.subtotal)}</span></div>`;
                h+=`<div class="sum-row" style="font-size:11px;color:#94a3b8"><span>↳ material ${rp(a.material)} (boros ${a.boros}%) + pasang ${rp(a.upah)}</span><span></span></div>`;
            });
        }
        if(g.addon && g.addon.length){
            const grup={rangka:'Add-on Rangka', atap:'Add-on Atap', total:'Add-on Total/Project'};
            ['rangka','atap','total'].forEach(function(lv){
                const items=g.addon.filter(function(a){return (a.level||'total')===lv;});
                if(!items.length) return;
                h+=`<div class="sum-row" style="font-size:11px;color:#fbbf24;border-bottom:1px solid #334155"><span>${grup[lv]}</span><span></span></div>`;
                items.forEach(a=>{
                    if(a.formula==='flat'){
                        h+=`<div class="sum-row"><span>${a.nama} (lumpsum)</span><span>${rp(a.biaya)}</span></div>`;
                    } else {
                        h+=`<div class="sum-row"><span>${a.nama} (${a.qty} ${a.satuan||''} × ${rp(a.harga)})</span><span>${rp(a.biaya)}</span></div>`;
                    }
                });
            });
        }
        h+=`<div class="sum-row"><span><b>Biaya pokok</b></span><span><b>${rp(g.pokok)}</b></span></div>`;
        h+=`<div class="sum-row"><span>Margin</span><span>${g.margin_persen}%</span></div>`;
        h+=`<div class="sum-row" style="font-size:16px"><span><b style="color:#fbbf24">HARGA JUAL</b></span><span><b style="color:#fbbf24">${rp(g.jual)}</b></span></div>`;
        h+='<div style="font-size:11px;color:#64748b;margin-top:6px">Margin sekali di atas total (besi + upah + atap + add-on). Modal add-on diambil dari rab_addon.</div>';
        h+='</div>';
    }

    d.per_material.forEach(m=>{
        h+='<div class="ct-card">';
        h+=`<div class="mat-head">${m.material} <span class="badge">${m.jumlah_batang} batang${m.sambungan?(' · '+m.sambungan+' sambungan'):''}</span></div>`;
        if(LIHAT_HARGA && m.subtotal_besi){ h+=`<div class="sum-row"><span>${m.jumlah_batang} × ${rp(m.harga_pokok)}</span><span><b>${rp(m.subtotal_besi)}</b></span></div>`; }
        const joinBars={};
        m.bars.forEach(bar=>{
            h+='<div class="barwrap"><div class="bar-label">Batang #'+bar.no+'</div><div class="bar">';
            bar.seg.forEach(s=>{
                const w=(s.len/STOCK*100).toFixed(2);
                if(s.jenis==='sambung'){
                    const col=joinColor(s.jid), lt=letter(s.jid);
                    (joinBars[s.jid]=joinBars[s.jid]||[]).push({bar:bar.no,len:s.len,label:s.label});
                    h+=`<div class="seg" style="width:${w}%;background:${col};color:#0f172a" title="${s.label} · sambungan ${lt}">${s.len} ${s.label}·${lt}</div>`;
                } else {
                    h+=`<div class="seg" style="width:${w}%;background:#3b82f6;color:#fff" title="${s.label}">${s.len} ${s.label}</div>`;
                }
            });
            if(bar.sisa>0){ const w=(bar.sisa/STOCK*100).toFixed(2); h+=`<div class="seg sisa" style="width:${w}%">sisa ${bar.sisa}</div>`; }
            h+='</div>';
            h+='<ul class="cut-list">';
            bar.seg.forEach(s=>{
                if(s.jenis==='sambung'){ const lt=letter(s.jid), col=joinColor(s.jid);
                    h+=`<li>✂️ <b>${s.len}cm</b> — ${s.label} <span style="color:${col};font-weight:700">● sambungan ${lt}</span></li>`;
                } else { h+=`<li>✂️ <b>${s.len}cm</b> — ${s.label}</li>`; }
            });
            if(bar.sisa>0) h+=`<li class="tag-sisa">sisa ${bar.sisa}cm</li>`;
            h+='</ul></div>';
        });
        const jids=Object.keys(joinBars);
        if(jids.length){
            h+='<div style="margin-top:8px;padding-top:8px;border-top:1px solid #334155"><div style="font-size:12px;color:#94a3b8;margin-bottom:4px">Daftar Sambungan (las):</div>';
            jids.forEach(jid=>{ const col=joinColor(+jid), lt=letter(+jid);
                const parts=joinBars[jid].map(p=>`${p.len}cm (Batang #${p.bar})`).join(' + ');
                const nm=joinBars[jid][0].label;
                h+=`<div style="font-size:12px;color:#cbd5e1;padding:2px 0"><span style="color:${col};font-weight:700">● Sambungan ${lt}</span> — ${nm}: las ${parts}</div>`;
            });
            h+='</div>';
        }
        h+='<div class="legend"><span><i style="background:#3b82f6"></i>potong utuh</span><span><i style="background:#f59e0b"></i>perlu disambung (warna = pasangannya)</span><span><i style="background:#334155"></i>sisa</span></div>';
        h+='</div>';
    });
    document.getElementById('hasil').innerHTML=h;
}
hitung();
</script>
@endsection