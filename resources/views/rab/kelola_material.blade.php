@extends('layouts.app')
@section('title', 'Kelola Varian Atap')
@section('content')
<style>
* { box-sizing: border-box; }
.ka-wrap { max-width: 1100px; margin: 0 auto; padding: 16px 16px 120px; }
.ka-head { display:flex; align-items:center; gap:12px; margin-bottom:8px; }
.ka-head h1 { font-size:18px; font-weight:700; color:#fbbf24; margin:0; flex:1; }
.ka-back { color:#94a3b8; text-decoration:none; font-size:13px; }
.ka-hint { font-size:12px; color:#64748b; margin:0 0 14px; }
.ka-card { background:#1e293b; border-radius:12px; padding:4px; overflow-x:auto; }
table.ka { width:100%; border-collapse:collapse; min-width:1040px; }
table.ka th { text-align:left; font-size:11px; color:#64748b; text-transform:uppercase;
    padding:10px 8px; border-bottom:1px solid #334155; white-space:nowrap; }
table.ka td { padding:6px 8px; border-bottom:1px solid #1e293b; vertical-align:middle; }
table.ka input, table.ka select {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:7px;
    padding:9px 8px; color:#f1f5f9; font-size:13px; outline:none; min-height:40px; }
table.ka input:focus, table.ka select:focus { border-color:#fbbf24; }
.col-harga input { text-align:right; font-weight:700; color:#fbbf24; }
.col-rab th, .col-rab2 { background:#0b1220; }
.th-rab { color:#fbbf24 !important; }
.ka-del { background:none; border:none; color:#ef4444; font-size:18px; cursor:pointer; padding:4px 8px; }
.ka-nonaktif td { opacity:.45; }
.ka-actions { position:fixed; left:0; right:0; bottom:0; background:#0f172acc;
    backdrop-filter:blur(8px); border-top:1px solid #334155; padding:12px 16px; z-index:50; }
.ka-actions-inner { max-width:1100px; margin:0 auto; display:flex; gap:10px; }
.btn { border:none; border-radius:10px; padding:13px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-gold { background:#fbbf24; color:#0f172a; flex:1; }
.btn-outline { background:transparent; border:1px solid #475569; color:#cbd5e1; }
.ka-toast { position:fixed; top:16px; left:50%; transform:translateX(-50%);
    background:#22c55e; color:#0f172a; font-weight:700; padding:12px 20px; border-radius:10px;
    z-index:100; display:none; font-size:14px; }
</style>

<div class="ka-wrap">
    <div class="ka-head">
        <a href="{{ url('/master-material') }}" class="ka-back">← Master Material</a>
        <h1 style="text-align:center">Kelola Varian Atap</h1>
        <span style="width:90px"></span>
    </div>
    <p class="ka-hint">Tambah/edit varian atap untuk RAB. Tulis nama LENGKAP + varian (mis. "Alderon transparan"). <b style="color:#fbbf24">Kolom RAB (Harga/m², Boros %, Upah Pasang/m²)</b> dipakai mesin harga block-mode: biaya atap = luas × harga/m² × (1+boros%) + luas × upah pasang/m². Kolom Harga/Lembar & Lebar dipakai wizard lama. <b style="color:#fbbf24">Consumable/m²</b> = bahan pelengkap khusus atap ini (mis. alderon: sealant + roof seal + serat fiber; kaca/spandek beda) — otomatis dikali luas atap.</p>

    <div class="ka-card">
        <table class="ka" id="tblAtap">
            <thead><tr>
                <th style="min-width:180px">Nama Atap</th>
                <th>Kategori</th>
                <th>Berat</th>
                <th style="min-width:110px">Harga/Lembar</th>
                <th>Lebar (cm)</th>
                <th class="th-rab" style="min-width:120px">Harga/m² (RAB)</th>
                <th class="th-rab" style="min-width:80px">Boros %</th>
                <th class="th-rab" style="min-width:120px">Upah Pasang/m²</th>
                <th class="th-rab" style="min-width:120px">Consumable/m²</th>
                <th style="min-width:150px">Keterangan Customer</th>
                <th>Aktif</th>
                <th></th>
            </tr></thead>
            <tbody>
            @foreach($ataps as $a)
                <tr data-id="{{ $a->id }}" class="atap-row {{ $a->is_active ? '' : 'ka-nonaktif' }}">
                    <td><input type="text" class="a-nama" value="{{ $a->nama }}"></td>
                    <td>
                        <select class="a-kat">
                            @foreach($katAtap as $k)
                                <option value="{{ $k }}" {{ $a->kategori==$k?'selected':'' }}>{{ $k }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="a-berat">
                            @foreach($beratAtap as $b)
                                <option value="{{ $b }}" {{ $a->berat_kategori==$b?'selected':'' }}>{{ $b }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="col-harga"><input type="number" class="a-harga" value="{{ (int)$a->harga_per_lembar }}"></td>
                    <td><input type="number" class="a-lebar" value="{{ (float)$a->lebar_lembar_cm }}" style="max-width:80px"></td>
                    <td class="col-harga"><input type="number" class="a-hargam2" value="{{ (int)($a->harga_per_m2 ?? 0) }}"></td>
                    <td><input type="number" class="a-boros" value="{{ $a->pemborosan_persen ?? 10 }}" step="0.1" style="max-width:70px"></td>
                    <td class="col-harga"><input type="number" class="a-upahpasang" value="{{ (int)($a->upah_pasang_per_m2 ?? 0) }}"></td>
                    <td class="col-harga"><input type="number" class="a-consumable" value="{{ (int)($a->consumable ?? 0) }}"></td>
                    <td><input type="text" class="a-ket" value="{{ $a->keterangan_customer }}"></td>
                    <td><input type="checkbox" class="a-aktif" {{ $a->is_active?'checked':'' }} style="width:22px;height:22px"></td>
                    <td><button class="ka-del" title="Nonaktifkan" onclick="nonaktif(this)">🗑️</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px"><button class="btn btn-outline" style="width:100%" onclick="tambahBaris()">+ Tambah Atap Baru</button></div>
</div>

<div class="ka-actions"><div class="ka-actions-inner">
    <button class="btn btn-gold" id="btnSimpan" onclick="simpan()">💾 Simpan Semua Perubahan</button>
</div></div>
<div class="ka-toast" id="toast"></div>

<script>
const CSRF = '{{ csrf_token() }}';
const KAT = @json($katAtap);
const BERAT = @json($beratAtap);

function opt(list, sel){ return list.map(v=>`<option value="${v}" ${v===sel?'selected':''}>${v}</option>`).join(''); }

function tambahBaris(){
    const id='new_'+Date.now();
    const tr=document.createElement('tr');
    tr.className='atap-row'; tr.dataset.id=id;
    tr.innerHTML=`<td><input type="text" class="a-nama" placeholder="Nama varian atap"></td>
        <td><select class="a-kat">${opt(KAT, KAT[0])}</select></td>
        <td><select class="a-berat">${opt(BERAT, BERAT[0])}</select></td>
        <td class="col-harga"><input type="number" class="a-harga" value="0"></td>
        <td><input type="number" class="a-lebar" value="80" style="max-width:80px"></td>
        <td class="col-harga"><input type="number" class="a-hargam2" value="0"></td>
        <td><input type="number" class="a-boros" value="10" step="0.1" style="max-width:70px"></td>
        <td class="col-harga"><input type="number" class="a-upahpasang" value="0"></td>
        <td class="col-harga"><input type="number" class="a-consumable" value="0"></td>
        <td><input type="text" class="a-ket" placeholder="opsional"></td>
        <td><input type="checkbox" class="a-aktif" checked style="width:22px;height:22px"></td>
        <td><button class="ka-del" onclick="this.closest('tr').remove()">✕</button></td>`;
    document.querySelector('#tblAtap tbody').prepend(tr);
    tr.querySelector('.a-nama').focus();
}

function kumpul(){
    return [...document.querySelectorAll('#tblAtap tbody tr')].map(tr=>({
        id: tr.dataset.id,
        nama: tr.querySelector('.a-nama').value,
        kategori: tr.querySelector('.a-kat').value,
        berat_kategori: tr.querySelector('.a-berat').value,
        harga_per_lembar: tr.querySelector('.a-harga').value,
        lebar_lembar_cm: tr.querySelector('.a-lebar').value,
        harga_per_m2: tr.querySelector('.a-hargam2').value,
        pemborosan_persen: tr.querySelector('.a-boros').value,
        upah_pasang_per_m2: tr.querySelector('.a-upahpasang').value,
        consumable: tr.querySelector('.a-consumable').value,
        keterangan_customer: tr.querySelector('.a-ket').value,
        is_active: tr.querySelector('.a-aktif').checked ? 1 : 0,
    })).filter(x=>x.nama.trim()!=='');
}

async function post(url, body){
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify(body)});
    return r.json();
}
function toast(msg, ok=true){
    const t=document.getElementById('toast');
    t.textContent=msg; t.style.background=ok?'#22c55e':'#ef4444'; t.style.display='block';
    setTimeout(()=>t.style.display='none', 2500);
}
async function simpan(){
    const btn=document.getElementById('btnSimpan');
    btn.disabled=true; btn.textContent='Menyimpan...';
    try{
        const res=await post('{{ url("/master-atap/simpan") }}', {data: kumpul()});
        if(res.success){ toast('✓ Tersimpan ('+(res.tersimpan||0)+'). Muat ulang...'); setTimeout(()=>location.reload(),1200); }
        else toast('Gagal menyimpan', false);
    }catch(e){ toast('Error: '+e.message, false); }
    btn.disabled=false; btn.textContent='💾 Simpan Semua Perubahan';
}
async function nonaktif(el){
    if(!confirm('Nonaktifkan varian atap ini? (tidak dihapus, hanya disembunyikan dari wizard)')) return;
    const tr=el.closest('tr'); const id=tr.dataset.id;
    if(String(id).startsWith('new_')){ tr.remove(); return; }
    const res=await post('{{ url("/master-atap/nonaktif") }}', {id});
    if(res.success){ tr.classList.add('ka-nonaktif'); tr.querySelector('.a-aktif').checked=false; toast('Dinonaktifkan'); }
}
</script>
@endsection