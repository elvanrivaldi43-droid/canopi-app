@extends('layouts.app')
@section('title', 'Produktivitas & Upah')
@section('page-title', 'Produktivitas & Upah')
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
.pk-wrap { max-width:1040px; margin:0 auto; padding:16px 16px 120px; }
.pk-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 4px; }
.pk-sub { font-size:12px; color:#64748b; margin:0 0 18px; }
.pk-sec { background:#1e293b; border-radius:14px; padding:14px; margin-bottom:16px; }
.pk-sec h2 { font-size:14px; font-weight:700; color:#f1f5f9; margin:0 0 2px; }
.pk-sec p { font-size:11px; color:#64748b; margin:0 0 12px; }
.pk-tblwrap { overflow-x:auto; }
table.pk { width:100%; border-collapse:collapse; min-width:680px; }
table.pk th { text-align:left; font-size:10px; color:#64748b; text-transform:uppercase;
    padding:8px 6px; border-bottom:1px solid #334155; white-space:nowrap; }
table.pk td { padding:5px 6px; vertical-align:middle; }
table.pk input, table.pk select {
    width:100%; background:#0f172a; border:1px solid #334155; border-radius:7px;
    padding:9px 8px; color:#f1f5f9; font-size:13px; outline:none; min-height:42px; }
table.pk input:focus, table.pk select:focus { border-color:#fbbf24; }
.num input { text-align:right; }
.pk-rp input { color:#fbbf24; font-weight:700; }
.pk-del { background:none; border:none; color:#ef4444; font-size:16px; cursor:pointer; padding:4px 6px; }
.pk-add { background:transparent; border:1px dashed #475569; color:#cbd5e1; border-radius:9px;
    padding:9px; width:100%; font-size:13px; cursor:pointer; margin-top:8px; }
.pk-actions { position:fixed; left:0; right:0; bottom:0; background:#0f172acc;
    backdrop-filter:blur(8px); border-top:1px solid #334155; padding:12px 16px; z-index:50; }
.pk-actions-inner { max-width:1040px; margin:0 auto; }
.btn { border:none; border-radius:10px; padding:14px; min-height:50px; font-size:14px; font-weight:700; cursor:pointer; width:100%; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.pk-toast { position:fixed; top:16px; left:50%; transform:translateX(-50%);
    background:#22c55e; color:#0f172a; font-weight:700; padding:12px 20px; border-radius:10px;
    z-index:100; display:none; font-size:14px; }
.chk { width:22px; height:22px; }
.hint { font-size:11px; color:#475569; margin-top:6px; }
</style>

<div class="pk-wrap">
    <h1 class="pk-title">Produktivitas & Upah</h1>
    <p class="pk-sub">Dasar perhitungan upah otomatis di RAB — berlaku untuk SEMUA produk (kanopi, pagar, tralis, railing, tangga, dll). Isi angka sesuai data aslimu; biarkan kosong jika belum yakin (sistem tidak menebak). Hanya owner yang bisa melihat halaman ini.</p>

    {{-- 1. SKILL --}}
    <div class="pk-sec">
        <h2>1. Tarif Skill</h2>
        <p>Upah harian per keahlian. Pekerjaan ber-skill khusus (mis. stainless) dibayar lebih tinggi.</p>
        <div class="pk-tblwrap">
        <table class="pk" id="tblSkill">
            <thead><tr>
                <th style="min-width:150px">Nama Skill</th>
                <th>Upah Tukang / hari</th>
                <th>Upah Kenek / hari</th>
                <th>Aktif</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($skills as $s)
                <tr data-id="{{ $s->id }}">
                    <td><input class="f-nama" value="{{ $s->nama }}"></td>
                    <td class="num pk-rp"><input type="number" class="f-ut" value="{{ $s->upah_tukang_harian !== null ? (int)$s->upah_tukang_harian : '' }}" placeholder="kosong"></td>
                    <td class="num pk-rp"><input type="number" class="f-uk" value="{{ $s->upah_kenek_harian !== null ? (int)$s->upah_kenek_harian : '' }}" placeholder="kosong"></td>
                    <td><input type="checkbox" class="f-aktif chk" {{ $s->is_active?'checked':'' }}></td>
                    <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <button class="pk-add" onclick="addSkill()">+ Tambah Skill</button>
    </div>

    {{-- 2. JENIS KERJA (generik) --}}
    <div class="pk-sec">
        <h2>2. Jenis Kerja (semua produk)</h2>
        <p>Tiap baris = satu cara kerja. <b>Fabrikasi</b> = kecepatan bikin di bengkel; <b>Instalasi</b> = kecepatan pasang di lokasi (m²/hari). Total hari kerja = fabrikasi + instalasi. Biaya nginap/transport dihitung dari hari instalasi saja. Kosongkan Instalasi kalau belum yakin (harga tetap seperti sebelumnya).</p>
        <div class="pk-tblwrap">
        <table class="pk" id="tblKerja">
            <thead><tr>
                <th style="min-width:150px">Nama Pekerjaan</th>
                <th>Produk</th>
                <th>Satuan</th>
                <th>Skill</th>
                <th>Fabrikasi /hari</th>
                <th>Instalasi /hari</th>
                <th>Tukang (fab)</th>
                <th>Kenek (fab)</th>
                <th>Tukang (inst)</th>
                <th>Kenek (inst)</th>
                <th>Aktif</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($kerja as $r)
                <tr data-id="{{ $r->id }}">
                    <td><input class="f-nama" value="{{ $r->nama }}"></td>
                    <td>
                        <select class="f-produk">
                            @foreach($produkList as $p)
                                <option value="{{ $p }}" {{ $r->produk==$p?'selected':'' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="f-satuan">
                            @foreach($satuanList as $u)
                                <option value="{{ $u }}" {{ $r->satuan==$u?'selected':'' }}>{{ $u }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="f-skill">
                            @foreach($skills as $s)
                                <option value="{{ $s->nama }}" {{ $r->skill_default==$s->nama?'selected':'' }}>{{ $s->nama }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="num"><input type="number" step="0.1" class="f-prod" value="{{ $r->produktivitas_per_hari !== null ? rtrim(rtrim((string)$r->produktivitas_per_hari,'0'),'.') : '' }}" placeholder="kosong"></td>
                    <td class="num"><input type="number" step="0.1" class="f-prodinst" value="{{ $r->produktivitas_inst !== null ? rtrim(rtrim((string)$r->produktivitas_inst,'0'),'.') : '' }}" placeholder="kosong"></td>
                    <td class="num"><input type="number" class="f-tukang" value="{{ $r->jml_tukang !== null ? (int)$r->jml_tukang : '' }}" placeholder="-"></td>
                    <td class="num"><input type="number" class="f-kenek" value="{{ $r->jml_kenek !== null ? (int)$r->jml_kenek : '' }}" placeholder="-"></td>
                    <td class="num"><input type="number" class="f-tukang-inst" value="{{ $r->jml_tukang_inst !== null ? (int)$r->jml_tukang_inst : '' }}" placeholder="-"></td>
                    <td class="num"><input type="number" class="f-kenek-inst" value="{{ $r->jml_kenek_inst !== null ? (int)$r->jml_kenek_inst : '' }}" placeholder="-"></td>
                    <td><input type="checkbox" class="f-aktif chk" {{ $r->is_active?'checked':'' }}></td>
                    <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <button class="pk-add" onclick="addKerja()">+ Tambah Jenis Kerja</button>
        <p class="hint">Tim <b>(fab)</b> = jumlah orang saat bikin di bengkel. Tim <b>(inst)</b> = jumlah orang saat pasang di lokasi (biasanya lebih banyak). Kosongkan (inst) kalau sama dengan (fab). Satuan <b>lumpsum</b>: boleh dikosongkan.</p>
    </div>

    {{-- 3. KONDISI KERJA --}}
    <div class="pk-sec">
        <h2>3. Kondisi Kerja (tambahan biaya, bisa numpuk)</h2>
        <p>Pengali upah (mis. 1.5 = upah ×1,5) dan/atau tambahan Rp per hari (mis. akomodasi luar kota). Berlaku untuk semua produk. Boleh isi salah satu atau dua-duanya.</p>
        <div class="pk-tblwrap">
        <table class="pk" id="tblKondisi">
            <thead><tr>
                <th style="min-width:160px">Kondisi</th>
                <th>Pengali Upah (×)</th>
                <th>Tambahan / hari (Rp)</th>
                <th>Kena</th>
                <th>Aktif</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($kondisi as $k)
                <tr data-id="{{ $k->id }}">
                    <td><input class="f-nama" value="{{ $k->nama }}"></td>
                    <td class="num"><input type="number" step="0.01" class="f-pengali" value="{{ $k->pengali_upah !== null ? rtrim(rtrim((string)$k->pengali_upah,'0'),'.') : '' }}" placeholder="mis. 1.5"></td>
                    <td class="num pk-rp"><input type="number" class="f-tambah" value="{{ $k->tambahan_per_hari !== null ? (int)$k->tambahan_per_hari : '' }}" placeholder="mis. 200000"></td>
                    <td>
                        <select class="f-kena">
                            <option value="fabinst" {{ (($k->kena ?? 'fabinst')=='fabinst')?'selected':'' }}>Fab + Inst</option>
                            <option value="inst" {{ (($k->kena ?? 'fabinst')=='inst')?'selected':'' }}>Instalasi saja</option>
                        </select>
                    </td>
                    <td><input type="checkbox" class="f-aktif chk" {{ $k->is_active?'checked':'' }}></td>
                    <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <button class="pk-add" onclick="addKondisi()">+ Tambah Kondisi</button>
        <p class="hint">Kolom <b>Kena</b>: "Instalasi saja" untuk kondisi lokasi susah (mall, akses berat, kerja malam) — pengali cuma naikkan upah pasang. "Fab + Inst" untuk skill unik (stainless, tangga putar) — naikkan upah bengkel + pasang.</p>
    </div>
</div>

<div class="pk-actions"><div class="pk-actions-inner">
    <button class="btn btn-gold" id="btnSimpan" onclick="simpan()">💾 Simpan Semua</button>
</div></div>
<div class="pk-toast" id="toast"></div>

<script>
const CSRF = '{{ csrf_token() }}';
const SKILLS = @json($skills->pluck('nama'));
const PRODUK = @json($produkList);
const SATUAN = @json($satuanList);

function opts(list, sel){ return list.map(n=>`<option value="${n}" ${n===sel?'selected':''}>${n}</option>`).join(''); }

function addSkill(){
    const tr=document.createElement('tr'); tr.dataset.id='new_'+Date.now();
    tr.innerHTML=`<td><input class="f-nama" placeholder="nama skill"></td>
        <td class="num pk-rp"><input type="number" class="f-ut" placeholder="kosong"></td>
        <td class="num pk-rp"><input type="number" class="f-uk" placeholder="kosong"></td>
        <td><input type="checkbox" class="f-aktif chk" checked></td>
        <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>`;
    document.querySelector('#tblSkill tbody').appendChild(tr); tr.querySelector('.f-nama').focus();
}
function addKerja(){
    const tr=document.createElement('tr'); tr.dataset.id='new_'+Date.now();
    tr.innerHTML=`<td><input class="f-nama" placeholder="nama pekerjaan"></td>
        <td><select class="f-produk">${opts(PRODUK, PRODUK[0])}</select></td>
        <td><select class="f-satuan">${opts(SATUAN, SATUAN[0])}</select></td>
        <td><select class="f-skill">${opts(SKILLS, SKILLS[0])}</select></td>
        <td class="num"><input type="number" step="0.1" class="f-prod" placeholder="kosong"></td>
        <td class="num"><input type="number" step="0.1" class="f-prodinst" placeholder="kosong"></td>
        <td class="num"><input type="number" class="f-tukang" placeholder="-"></td>
        <td class="num"><input type="number" class="f-kenek" placeholder="-"></td>
        <td class="num"><input type="number" class="f-tukang-inst" placeholder="-"></td>
        <td class="num"><input type="number" class="f-kenek-inst" placeholder="-"></td>
        <td><input type="checkbox" class="f-aktif chk" checked></td>
        <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>`;
    document.querySelector('#tblKerja tbody').appendChild(tr); tr.querySelector('.f-nama').focus();
}
function addKondisi(){
    const tr=document.createElement('tr'); tr.dataset.id='new_'+Date.now();
    tr.innerHTML=`<td><input class="f-nama" placeholder="kondisi"></td>
        <td class="num"><input type="number" step="0.01" class="f-pengali" placeholder="mis. 1.5"></td>
        <td class="num pk-rp"><input type="number" class="f-tambah" placeholder="mis. 200000"></td>
        <td><select class="f-kena"><option value="fabinst">Fab + Inst</option><option value="inst">Instalasi saja</option></select></td>
        <td><input type="checkbox" class="f-aktif chk" checked></td>
        <td><button class="pk-del" onclick="this.closest('tr').remove()">✕</button></td>`;
    document.querySelector('#tblKondisi tbody').appendChild(tr); tr.querySelector('.f-nama').focus();
}

function val(tr,cls){ const el=tr.querySelector(cls); return el?el.value:''; }
function kumpulSkill(){
    return [...document.querySelectorAll('#tblSkill tbody tr')].map(tr=>({
        id:tr.dataset.id, nama:val(tr,'.f-nama'),
        upah_tukang_harian:val(tr,'.f-ut'), upah_kenek_harian:val(tr,'.f-uk'),
        is_active:tr.querySelector('.f-aktif').checked?1:0,
    })).filter(x=>x.nama.trim()!=='');
}
function kumpulKerja(){
    return [...document.querySelectorAll('#tblKerja tbody tr')].map(tr=>({
        id:tr.dataset.id, nama:val(tr,'.f-nama'),
        produk:val(tr,'.f-produk'), satuan:val(tr,'.f-satuan'), skill_default:val(tr,'.f-skill'),
        produktivitas_per_hari:val(tr,'.f-prod'), produktivitas_inst:val(tr,'.f-prodinst'), jml_tukang:val(tr,'.f-tukang'), jml_kenek:val(tr,'.f-kenek'), jml_tukang_inst:val(tr,'.f-tukang-inst'), jml_kenek_inst:val(tr,'.f-kenek-inst'),
        is_active:tr.querySelector('.f-aktif').checked?1:0,
    })).filter(x=>x.nama.trim()!=='');
}
function kumpulKondisi(){
    return [...document.querySelectorAll('#tblKondisi tbody tr')].map(tr=>({
        id:tr.dataset.id, nama:val(tr,'.f-nama'),
        pengali_upah:val(tr,'.f-pengali'), tambahan_per_hari:val(tr,'.f-tambah'), kena:val(tr,'.f-kena'),
        is_active:tr.querySelector('.f-aktif').checked?1:0,
    })).filter(x=>x.nama.trim()!=='');
}
function toast(msg, ok=true){
    const t=document.getElementById('toast');
    t.textContent=msg; t.style.background=ok?'#22c55e':'#ef4444'; t.style.display='block';
    setTimeout(()=>t.style.display='none', 2500);
}
async function simpan(){
    const btn=document.getElementById('btnSimpan'); btn.disabled=true; btn.textContent='Menyimpan...';
    try{
        const r=await fetch('{{ url("/produktivitas/simpan") }}',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body:JSON.stringify({skill:kumpulSkill(), kerja:kumpulKerja(), kondisi:kumpulKondisi()})
        });
        const res=await r.json();
        if(res.success){ toast('✓ Tersimpan. Muat ulang...'); setTimeout(()=>location.reload(),1000); }
        else toast('Gagal menyimpan', false);
    }catch(e){ toast('Error: '+e.message, false); }
    btn.disabled=false; btn.textContent='💾 Simpan Semua';
}
</script>
@endsection