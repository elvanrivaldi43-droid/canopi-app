<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Penawaran — Pusat Kanopi BSD</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Segoe UI', Arial, sans-serif; color:#1e293b; background:#e2e8f0; padding:16px; }
.sheet { max-width:800px; margin:0 auto; background:#fff; padding:28px 30px; box-shadow:0 4px 20px rgba(0,0,0,.12); }
.head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #1e3a8a; padding-bottom:14px; margin-bottom:16px; }
.head .brand { font-size:20px; font-weight:800; color:#1e3a8a; line-height:1.2; }
.head .brand small { display:block; font-size:12px; font-weight:600; color:#475569; letter-spacing:1px; }
.head .brand .telp { display:block; font-size:13px; font-weight:600; color:#1e293b; margin-top:4px; }
.head .doc { text-align:right; font-size:12px; color:#475569; }
.head .doc b { font-size:15px; color:#1e3a8a; }
.cust { display:flex; justify-content:space-between; font-size:13px; margin-bottom:18px; }
.cust .box { background:#f1f5f9; border-radius:8px; padding:10px 12px; min-width:46%; }
.cust .box .lbl { font-size:11px; color:#64748b; text-transform:uppercase; }
.cust .box .val { font-size:14px; font-weight:700; color:#1e293b; }
.opsi { border:1px solid #cbd5e1; border-radius:10px; margin-bottom:14px; overflow:hidden; }
.opsi .otop { background:#1e3a8a; color:#fff; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; }
.opsi .otop .onama { font-size:15px; font-weight:700; }
.opsi .otop .oharga { font-size:18px; font-weight:800; }
.opsi .obody { padding:12px 14px; }
.blok { border-bottom:1px dashed #e2e8f0; padding:8px 0; }
.blok:last-child { border-bottom:none; }
.blok .bnama { font-size:14px; font-weight:700; color:#1e3a8a; margin-bottom:3px; }
.blok .brow { font-size:12.5px; color:#475569; margin:1px 0; }
.blok .brow b { color:#1e293b; }
.catatan { margin-top:18px; }
.catatan .ct-title { font-size:13px; font-weight:700; color:#1e3a8a; border-bottom:1px solid #cbd5e1; padding-bottom:4px; margin-bottom:8px; }
.catatan ol { margin-left:18px; font-size:12.5px; color:#334155; }
.catatan ol li { margin:3px 0; }
.terms { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px; }
.terms .tbox { border:1px solid #cbd5e1; border-radius:8px; padding:10px; }
.terms .tbox .lbl { font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:3px; }
.terms .editable { min-height:22px; font-size:13px; font-weight:600; color:#1e293b; outline:none; border-bottom:1px dashed #94a3b8; padding:2px; }
.terms .editable:empty:before { content:attr(data-ph); color:#94a3b8; font-weight:400; }
.foot { margin-top:22px; text-align:center; font-size:11px; color:#94a3b8; }
.toolbar { max-width:800px; margin:0 auto 14px; display:flex; gap:8px; }
.btn { border:none; border-radius:8px; padding:12px 18px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-print { background:#1e3a8a; color:#fff; }
.btn-back { background:#64748b; color:#fff; text-decoration:none; display:inline-flex; align-items:center; }
.empty { max-width:800px; margin:40px auto; background:#fff; padding:30px; border-radius:10px; text-align:center; color:#64748b; }
.rekening { background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px 14px; margin-top:16px; }
.rekening .rk-title { font-size:12px; font-weight:700; color:#166534; margin-bottom:6px; text-transform:uppercase; }
.rekening .rk-row { font-size:13.5px; color:#1e293b; margin:2px 0; }
.opilih { padding:10px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; }
.pilih-btn { border:1px solid #1e3a8a; background:#fff; color:#1e3a8a; border-radius:8px; padding:9px 14px; font-size:13px; font-weight:700; cursor:pointer; width:100%; }
.opsi.dipilih { border-color:#16a34a; border-width:2px; }
.opsi.dipilih .pilih-btn { background:#16a34a; color:#fff; border-color:#16a34a; }
.opsi.redup { opacity:.5; }
.ttd-area { margin-top:18px; border:1px solid #cbd5e1; border-radius:10px; padding:14px; }
.ttd-area .tt-title { font-size:13px; font-weight:700; color:#1e3a8a; margin-bottom:6px; }
.ttd-area .tt-info { font-size:12px; color:#64748b; margin-bottom:8px; }
#ttdCanvas { width:100%; height:180px; border:1px dashed #94a3b8; border-radius:8px; background:#fff; touch-action:none; display:block; }
.tt-btns { display:flex; gap:8px; margin-top:10px; }
.tt-btns button { flex:1; border:none; border-radius:8px; padding:12px; font-size:13px; font-weight:700; cursor:pointer; }
.tt-hapus { background:#64748b; color:#fff; }
.tt-simpan { background:#16a34a; color:#fff; }
.deal-done { background:#f0fdf4; border:2px solid #16a34a; border-radius:10px; padding:16px; margin-top:18px; text-align:center; }
.deal-done .dd-big { font-size:16px; font-weight:800; color:#16a34a; }
.deal-done .dd-row { font-size:13px; color:#1e293b; margin:4px 0; }
.deal-done img { max-width:280px; border:1px solid #cbd5e1; border-radius:8px; margin-top:8px; background:#fff; }
.no-print {}
@media print {
    .no-print { display:none !important; }
    body { background:#fff; padding:0; }
    .sheet { box-shadow:none; max-width:100%; padding:10px 6px; }
    .toolbar { display:none; }
    .terms .editable { border-bottom:none; }
}
</style>
</head>
<body>

@if(!$pen)
    <div class="empty">
        <h2 style="color:#1e3a8a;margin-bottom:8px">Penawaran belum dibuat</h2>
        <p>Buka RAB lead ini, tekan <b>Hitung Harga</b>, lalu <b>Buat Penawaran</b> untuk menghasilkan halaman ini.</p>
        <p style="margin-top:14px"><a href="{{ url('/rab-opsi?lead='.$lead->id) }}" style="color:#1e3a8a;font-weight:700">← Buka RAB lead ini</a></p>
    </div>
@else

<div class="toolbar">
    <button class="btn btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    <a class="btn btn-back" href="{{ url('/rab-opsi?lead='.$lead->id) }}">← Kembali ke RAB</a>
</div>

<div class="sheet">
    <div class="head">
        <div class="brand">
            Pusat Kanopi BSD
            <small>BUANA KARYA TEKNIK</small>
            <span class="telp">Telp / WA: 085872043157</span>
        </div>
        <div class="doc">
            <b>SURAT PENAWARAN</b><br>
            No: PNW/{{ str_pad($lead->id, 4, '0', STR_PAD_LEFT) }}/{{ date('m/Y') }}<br>
            Tanggal: {{ date('d/m/Y') }}
        </div>
    </div>

    <div class="cust">
        <div class="box">
            <div class="lbl">Kepada Yth.</div>
            <div class="val">{{ $pen->customer ?? ($lead->nama_customer ?? '-') }}</div>
        </div>
        <div class="box">
            <div class="lbl">Lokasi</div>
            <div class="val">{{ $pen->alamat ?? ($lead->lokasi_area ?? ($lead->alamat ?? '-')) }}</div>
        </div>
    </div>

    @foreach($pen->opsi as $opsi)
    @php $namaOpsi = $opsi->nama ?? ('Opsi '.($loop->index+1)); @endphp
    @if($deal && $namaOpsi !== ($deal->opsi ?? ''))
        @continue
    @endif
    <div class="opsi">
        <div class="otop">
            <div class="onama">{{ $opsi->nama ?? 'Opsi' }}</div>
            <div class="oharga">Rp {{ number_format($opsi->harga ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="obody">
            @foreach($opsi->blok as $b)
            <div class="blok">
                <div class="bnama">{{ $b->nama ?? 'Blok' }}</div>
                @if(!empty($b->ukuran))
                <div class="brow">Ukuran: <b>{{ $b->ukuran }}</b></div>
                @endif
                @if(!empty($b->frame))
                <div class="brow">Rangka: <b>{{ $b->frame }}</b>@if(!empty($b->support)) · Support: <b>{{ $b->support }}</b>@endif @if(!empty($b->tiang)) · Tiang: <b>{{ $b->tiang }}</b>@endif</div>
                @endif
                @if(!empty($b->atap) && count($b->atap))
                <div class="brow">Atap: <b>{{ implode(', ', (array)$b->atap) }}</b></div>
                @endif
                @if(!empty($b->manual) && count($b->manual))
                <div class="brow">Item: <b>{{ implode(', ', (array)$b->manual) }}</b></div>
                @endif
            </div>
            @endforeach
        </div>
        @if(!$deal)
        <div class="opilih no-print" data-opsi-nama="{{ $opsi->nama ?? ('Opsi '.($loop->index+1)) }}">
            <button type="button" class="pilih-btn" onclick="pilihOpsi(this)">Pilih Opsi Ini</button>
        </div>
        @endif
    </div>
    @endforeach

    <div class="rekening">
        <div class="rk-title">Pembayaran ditransfer ke rekening:</div>
        <div class="rk-row">Bank: <b>BCA Syariah</b></div>
        <div class="rk-row">No. Rekening: <b>0420017279</b></div>
        <div class="rk-row">Atas Nama: <b>MOHAMMAD ELVAN RIVALDI M</b></div>
    </div>

    <div class="terms">
        <div class="tbox">
            <div class="lbl">Term Pembayaran</div>
            <div class="editable" contenteditable="true" data-ph="ketik: DP 30% + 40% + 40% / lunas / dll"></div>
        </div>
        <div class="tbox">
            <div class="lbl">Garansi</div>
            <div class="editable" contenteditable="true" data-ph="ketik: 1 tahun rangka / dll"></div>
        </div>
        <div class="tbox">
            <div class="lbl">Estimasi Waktu Pengerjaan</div>
            <div class="editable" contenteditable="true" data-ph="ketik: 7 hari kerja / dll"></div>
        </div>
        <div class="tbox">
            <div class="lbl">Masa Berlaku Penawaran</div>
            <div class="editable" contenteditable="true" data-ph="ketik: 14 hari / dll"></div>
        </div>
    </div>

    <div class="catatan">
        <div class="ct-title">Catatan Detail</div>
        <ol>
            <li>Ketersediaan sumber listrik merupakan tanggung jawab customer</li>
            <li>Jadwal pemasangan bisa berubah sesuai dengan kondisi lapangan</li>
            <li>Perubahan spek besi atau model akan dikenakan biaya tambahan</li>
        </ol>
    </div>

    @if($deal)
    <div class="deal-done">
        <div class="dd-big">✓ SUDAH DEAL</div>
        <div class="dd-row">Opsi disetujui: <b>{{ $deal->opsi ?? '-' }}</b></div>
        <div class="dd-row">Tanggal: <b>{{ !empty($deal->deal_at) ? \Carbon\Carbon::parse($deal->deal_at)->format('d/m/Y H:i') : '-' }}</b></div>
        <div class="dd-row">Tanda tangan customer:</div>
        @if(!empty($deal->ttd))<img src="{{ $deal->ttd }}" alt="tanda tangan">@endif
    </div>
    @else
    <div class="ttd-area no-print" id="ttdArea">
        <div class="tt-title">Konfirmasi Deal + Tanda Tangan</div>
        <div class="tt-info">1) Tekan "Pilih Opsi Ini" pada opsi yang disetujui customer di atas. 2) Minta customer tanda tangan pakai jari di kotak bawah. 3) Tekan Simpan Deal.</div>
        <div class="tt-info" id="pilihInfo" style="color:#1e3a8a;font-weight:700">Belum ada opsi dipilih</div>
        <canvas id="ttdCanvas"></canvas>
        <div class="tt-btns">
            <button type="button" class="tt-hapus" onclick="hapusTtd()">Hapus</button>
            <button type="button" class="tt-simpan" onclick="simpanDeal()">Simpan Deal &amp; Tanda Tangan</button>
        </div>
    </div>
    @endif

    <div class="foot">Terima kasih atas kepercayaan Anda — Pusat Kanopi BSD · Buana Karya Teknik · 085872043157</div>
</div>

@endif
<script>
@if($pen && !$deal)
var CSRF='{{ csrf_token() }}';
var DEAL_URL='{{ url("/penawaran/".$lead->id."/deal") }}';
var selectedOpsi=null;
function pilihOpsi(btn){
    var wrap=btn.parentNode;
    selectedOpsi=wrap.getAttribute('data-opsi-nama');
    var cards=document.querySelectorAll('.opsi');
    for(var i=0;i<cards.length;i++){ cards[i].classList.remove('dipilih'); cards[i].classList.add('redup'); }
    var card=wrap.parentNode;
    card.classList.add('dipilih'); card.classList.remove('redup');
    var info=document.getElementById('pilihInfo');
    if(info) info.innerHTML='Opsi disetujui: '+selectedOpsi;
}
var canvas=document.getElementById('ttdCanvas');
var ctx=canvas?canvas.getContext('2d'):null;
var drawing=false, kosong=true;
function siapkanCanvas(){
    if(!canvas) return;
    var rect=canvas.getBoundingClientRect();
    canvas.width=rect.width; canvas.height=180;
    ctx.lineWidth=2.5; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#1e293b';
}
function titik(e){
    var rect=canvas.getBoundingClientRect();
    var t=(e.touches && e.touches[0])?e.touches[0]:e;
    return { x:t.clientX-rect.left, y:t.clientY-rect.top };
}
function mulai(e){ if(!ctx)return; drawing=true; var p=titik(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); if(e.cancelable)e.preventDefault(); }
function gerak(e){ if(!drawing||!ctx)return; var p=titik(e); ctx.lineTo(p.x,p.y); ctx.stroke(); kosong=false; if(e.cancelable)e.preventDefault(); }
function selesai(){ drawing=false; }
if(canvas){
    siapkanCanvas();
    canvas.addEventListener('mousedown',mulai); canvas.addEventListener('mousemove',gerak);
    canvas.addEventListener('mouseup',selesai); canvas.addEventListener('mouseleave',selesai);
    canvas.addEventListener('touchstart',mulai); canvas.addEventListener('touchmove',gerak); canvas.addEventListener('touchend',selesai);
}
function hapusTtd(){ if(ctx){ ctx.clearRect(0,0,canvas.width,canvas.height); kosong=true; } }
function simpanDeal(){
    if(!selectedOpsi){ alert('Pilih dulu opsi yang disetujui customer (tombol "Pilih Opsi Ini").'); return; }
    if(kosong){ alert('Minta customer tanda tangan dulu di kotak.'); return; }
    var ttd=canvas.toDataURL('image/png');
    fetch(DEAL_URL,{method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body:JSON.stringify({ opsi:selectedOpsi, ttd:ttd })
    }).then(function(r){ return r.json(); }).then(function(res){
        if(res && res.success){ alert('Deal + tanda tangan tersimpan.'); location.reload(); }
        else { alert('Gagal simpan: '+((res&&res.message)||'error')); }
    }).catch(function(e){ alert('Error: '+e.message); });
}
@endif
</script>
</body>
</html>