@extends('layouts.app')
@section('title', 'Perancang Rangka')
@section('page-title', 'Perancang Rangka')
@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection
@section('content')
<div style="max-width:960px;margin:0 auto;padding:12px">
  <h1 style="font-size:20px;font-weight:800;margin:0 0 4px">Perancang Rangka <span style="font-size:12px;color:#f59e0b">(Fase 1 — uji coba)</span></h1>
  <p style="color:#64748b;font-size:13px;margin:0 0 16px">Isi kotak, sistem bikin daftar batang default. Ganti besi tiap batang, tambah/hapus, lalu lihat total.</p>

  {{-- 1. INPUT KOTAK --}}
  <div style="background:#0f172a0d;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
      <label>Lebar (cm)<input id="lebar" type="number" value="700" class="rd-in"></label>
      <label>Panjang (cm)<input id="panjang" type="number" value="730" class="rd-in"></label>
      <label>Tinggi tiang (cm)<input id="tinggi" type="number" value="300" class="rd-in"></label>
      <label>Kotak support (cm)<input id="kotak" type="number" value="80" class="rd-in"></label>
      <label>Arah support
        <select id="arah" class="rd-in"><option value="2">2 arah</option><option value="1">1 arah</option></select></label>
      <label>Jumlah tiang<input id="tiang" type="number" value="2" class="rd-in"></label>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:10px">
      <label>Besi Frame<select id="matFrame" class="rd-in rd-besi"></select></label>
      <label>Besi Support<select id="matSupport" class="rd-in rd-besi"></select></label>
      <label>Besi Tiang<select id="matTiang" class="rd-in rd-besi"></select></label>
    </div>
    <button id="btnSeed" class="rd-btn" style="margin-top:12px">Buat Denah Default</button>
  </div>

  {{-- 2. DENAH READ-ONLY + TABEL BATANG --}}
  <div id="hasil" style="display:none">
    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
      <div style="font-weight:700;margin-bottom:8px">Denah (tampak atas)</div>
      <div id="denah" style="overflow-x:auto"></div>
    </div>

    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:700">Daftar Batang</div>
        <button id="btnTambah" class="rd-btn rd-btn-sm">+ Tambah Batang</button>
      </div>
      <div style="overflow-x:auto"><table id="tblBatang" style="width:100%;border-collapse:collapse;font-size:13px"></table></div>
    </div>

    {{-- 3. RINGKASAN --}}
    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px">
      <div style="font-weight:700;margin-bottom:8px">Ringkasan Besi</div>
      <div id="ringkasan"></div>
      <div id="warn" style="color:#b45309;font-size:12px;margin-top:8px"></div>
    </div>
  </div>
</div>

<style>
  .rd-in{display:block;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;margin-top:4px;font-size:13px}
  label{font-size:12px;color:#475569;font-weight:600}
  .rd-btn{background:#1e40af;color:#fff;border:0;padding:10px 18px;border-radius:8px;font-size:14px;cursor:pointer}
  .rd-btn-sm{padding:6px 12px;font-size:13px}
  #tblBatang th{text-align:left;padding:6px;border-bottom:2px solid #e2e8f0;font-size:11px;color:#64748b;text-transform:uppercase}
  #tblBatang td{padding:6px;border-bottom:1px solid #f1f5f9}
  .rd-del{background:#fee2e2;color:#b91c1c;border:0;border-radius:6px;padding:4px 8px;cursor:pointer}
</style>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const LIHAT_HARGA = @json($lihatHarga);
const BESI = @json($besi);
let members = [];
let box = {L:0, P:0};   // dimensi kotak untuk outline denah
let hargaMap = {};
BESI.forEach(b => hargaMap[b.nama] = Number(b.harga_pokok) || 0);

function besiOptions(sel){
  return BESI.map(b => `<option value="${b.nama}" ${b.nama===sel?'selected':''}>${b.nama}</option>`).join('');
}
// isi dropdown besi kotak
['matFrame','matSupport','matTiang'].forEach(id => { document.getElementById(id).innerHTML = besiOptions(); });

async function post(url, body){
  const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(body)});
  return res.json();
}

document.getElementById('btnSeed').onclick = async () => {
  const j = await post('{{ url('/rangka-desain/seed') }}', {
    lebar_cm:+lebar.value, panjang_cm:+panjang.value, tinggi_cm:+tinggi.value,
    kotak_cm:+kotak.value, arah_support:+arah.value, jml_tiang:+tiang.value,
    mat_frame:matFrame.value, mat_support:matSupport.value, mat_tiang:matTiang.value,
  });
  if(!j.success){ alert('Gagal seed'); return; }
  members = j.data.members;
  box = {L:+lebar.value, P:+panjang.value};
  document.getElementById('hasil').style.display = 'block';
  renderTabel(); hitung();   // renderTabel() menggambar denah dari members
};

document.getElementById('btnTambah').onclick = () => {
  members.push({nama:'Batang baru', jenis:'tambahan', panjang:100, arah:'-', posisi:{}, material:BESI[0]?BESI[0].nama:''});
  renderTabel(); hitung();
};

function renderTabel(){
  let h = '<tr><th>Nama</th><th>Jenis</th><th>Panjang (cm)</th><th>Besi</th><th></th></tr>';
  members.forEach((m,i) => {
    h += `<tr>
      <td>${m.nama}</td>
      <td>${m.jenis}</td>
      <td><input type="number" value="${m.panjang}" data-i="${i}" class="rd-in rd-len" style="width:90px;margin:0"></td>
      <td><select data-i="${i}" class="rd-in rd-mat" style="margin:0">${besiOptions(m.material)}</select></td>
      <td><button class="rd-del" data-i="${i}">hapus</button></td>
    </tr>`;
  });
  document.getElementById('tblBatang').innerHTML = h;
  document.querySelectorAll('.rd-len').forEach(el => el.onchange = e => { members[+e.target.dataset.i].panjang = +e.target.value; hitung(); });
  document.querySelectorAll('.rd-mat').forEach(el => el.onchange = e => { members[+e.target.dataset.i].material = e.target.value; hitung(); });
  document.querySelectorAll('.rd-del').forEach(el => el.onclick = e => { members.splice(+e.target.dataset.i,1); renderTabel(); hitung(); });
  gambarDenah();   // denah selalu ikut members terkini (tambah/hapus langsung terlihat)
}

async function hitung(){
  const j = await post('{{ url('/rangka-desain/hitung') }}', {members, harga:hargaMap});
  if(!j.success) return;
  const d = j.data;
  let h = '<table style="width:100%;font-size:13px;border-collapse:collapse">';
  h += '<tr><th style="text-align:left">Besi</th><th style="text-align:right">Batang</th><th style="text-align:right">Sambungan</th>' + (LIHAT_HARGA?'<th style="text-align:right">Subtotal</th>':'') + '</tr>';
  d.per_material.forEach(m => {
    h += `<tr><td>${m.material}</td><td style="text-align:right">${m.jumlah_batang}</td><td style="text-align:right">${m.sambungan}</td>` +
      (LIHAT_HARGA?`<td style="text-align:right">${m.subtotal_besi!=null?('Rp '+Number(m.subtotal_besi).toLocaleString('id-ID')):'-'}</td>`:'') + '</tr>';
  });
  h += `<tr style="font-weight:800;border-top:2px solid #e2e8f0"><td>TOTAL</td><td style="text-align:right">${d.total_batang}</td><td></td>` +
    (LIHAT_HARGA?`<td style="text-align:right">${d.total_biaya_besi!=null?('Rp '+Number(d.total_biaya_besi).toLocaleString('id-ID')):'-'}</td>`:'') + '</tr>';
  h += '</table>';
  document.getElementById('ringkasan').innerHTML = h;
  document.getElementById('warn').innerHTML = (d.warn||[]).map(w=>'! '+w).join('<br>');
}

// Warna per jenis batang
const WARNA = {frame:'#1e40af', support:'#60a5fa', tiang:'#b45309', tambahan:'#16a34a'};
function esc(t){ return String(t).replace(/[<&>]/g, c => ({'<':'&lt;','&':'&amp;','>':'&gt;'}[c])); }

// Denah digambar dari members terkini (satu sumber data, bukan payload seed terpisah)
function gambarDenah(){
  const L = box.L, P = box.P, el = document.getElementById('denah');
  if(!L || !P){ el.innerHTML=''; return; }
  const pad = 42, sc = Math.min(520/L, 360/P);
  const W = L*sc+pad*2, H = P*sc+pad*2;
  let s = `<svg width="${W}" height="${H}" style="max-width:100%;font-family:sans-serif">`;
  // pemandu kotak: putus-putus tipis, biar frame tepi (solid berwarna) kelihatan hilang saat dihapus
  s += `<rect x="${pad}" y="${pad}" width="${L*sc}" height="${P*sc}" fill="none" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="5 4"/>`;
  let batangTambahan = 0;
  members.forEach(m => {
    const c = WARNA[m.jenis] || WARNA.tambahan;
    const nama = esc(m.nama || m.jenis || '');
    const p = m.posisi || {};
    const isF = m.jenis==='frame';
    // garis putih di belakang teks (paint-order) biar nama kebaca di atas garis
    const halo = 'paint-order="stroke" stroke="#fff" stroke-width="2.5"';
    if(m.arah==='vertikal' && p.x!=null){
      const x = pad+p.x*sc;
      s += `<line x1="${x}" y1="${pad}" x2="${x}" y2="${pad+P*sc}" stroke="${c}" stroke-width="${isF?3:1.5}"><title>${nama}</title></line>`;
      if(isF) s += `<text x="${x}" y="${pad-5}" font-size="9" fill="${c}" text-anchor="start" transform="rotate(-45 ${x} ${pad-5})">${nama}</text>`;
      else    s += `<text x="${x}" y="${pad+P*sc*0.35}" font-size="9" fill="${c}" text-anchor="middle" ${halo}>${nama}</text>`;
    } else if(m.arah==='horizontal' && p.y!=null){
      const y = pad+p.y*sc;
      s += `<line x1="${pad}" y1="${y}" x2="${pad+L*sc}" y2="${y}" stroke="${c}" stroke-width="${isF?3:1.5}"><title>${nama}</title></line>`;
      if(isF) s += `<text x="${pad-4}" y="${y+3}" font-size="9" fill="${c}" text-anchor="end">${nama}</text>`;
      else    s += `<text x="${pad+L*sc*0.65}" y="${y-3}" font-size="9" fill="${c}" text-anchor="middle" ${halo}>${nama}</text>`;
    } else if(m.arah==='tiang' && p.x!=null){
      const x = pad+p.x*sc, y = pad+(p.y||0)*sc;
      s += `<circle cx="${x}" cy="${y}" r="4" fill="${WARNA.tiang}"><title>${nama}</title></circle>`;
      s += `<text x="${x+6}" y="${y+3}" font-size="9" fill="${WARNA.tiang}" ${halo}>${nama}</text>`;
    } else {
      batangTambahan++;   // batang tanpa posisi (mis. + Tambah) tak tergambar
    }
  });
  s += '</svg>';
  // Legend
  let lg = '<div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:6px;font-size:11px;color:#475569">';
  [['frame','Frame'],['support','Support'],['tiang','Tiang']].forEach(([k,t]) =>
    lg += `<span><span style="display:inline-block;width:10px;height:10px;background:${WARNA[k]};border-radius:2px;margin-right:4px;vertical-align:middle"></span>${t}</span>`);
  if(batangTambahan) lg += `<span style="color:#16a34a">+${batangTambahan} batang tambahan (tanpa posisi, tak tergambar)</span>`;
  lg += '</div>';
  el.innerHTML = s + lg;
}
</script>
@endsection
