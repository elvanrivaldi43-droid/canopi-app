<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }}</title>
<style>
  * { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  body { font-family:Arial, sans-serif; color:#111; margin:24px; font-size:12px; }
  h1 { font-size:18px; margin:0 0 2px; }
  .meta { color:#555; font-size:11px; margin-bottom:14px; }
  .inputbox { background:#f3f4f6; border:1px solid #ddd; border-radius:6px; padding:8px 12px; font-size:11px; margin-bottom:14px; }
  .denah-wrap { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; page-break-inside:avoid; }
  .denah-wrap > div { flex:1; min-width:300px; border:1px solid #e5e7eb; border-radius:6px; padding:8px; }
  .denah-wrap h3 { font-size:12px; margin:0 0 4px; color:#374151; }
  table.sum { width:100%; border-collapse:collapse; margin-bottom:16px; }
  table.sum th, table.sum td { border:1px solid #ccc; padding:6px 8px; text-align:left; font-size:11px; }
  table.sum th { background:#1f2937; color:#fff; }
  .mat { margin-bottom:18px; page-break-inside:avoid; }
  .mat h2 { font-size:14px; margin:0 0 6px; border-bottom:2px solid #fbbf24; padding-bottom:3px; }
  .batang { margin-bottom:10px; page-break-inside:avoid; }
  .batang .bl { font-weight:bold; font-size:12px; margin-bottom:3px; }
  .bar { display:flex; height:30px; border:1px solid #333; border-radius:4px; overflow:hidden; }
  .seg { display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:bold;
         color:#111; border-right:1px solid #fff; white-space:nowrap; overflow:hidden; padding:0 2px; }
  .seg.sisa { background:#e5e7eb; color:#666; }
  ul.potong { margin:5px 0 0; padding-left:18px; }
  ul.potong li { font-size:11px; margin-bottom:1px; }
  .dot { display:inline-block;width:9px;height:9px;border-radius:50%;vertical-align:middle;margin-right:3px; }
  .sambox { margin-top:6px; border-top:1px solid #ddd; padding-top:5px; }
  .sambox .t { font-size:11px; color:#555; margin-bottom:3px; font-weight:bold; }
  .sambox div { font-size:11px; padding:1px 0; }
  .legend { font-size:10px; color:#555; margin-top:4px; }
  .legend i { display:inline-block;width:11px;height:11px;border-radius:2px;vertical-align:middle;margin-right:3px; }
  .toolbar { margin-bottom:14px; }
  .btn { background:#fbbf24; border:none; border-radius:6px; padding:9px 16px; font-weight:bold; cursor:pointer; font-size:13px; }
  @media print { .toolbar { display:none; } body { margin:0; } }
</style>
</head>
<body>
  <div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ Print / Simpan PDF</button>
  </div>

  <h1>{{ $judul }}</h1>
  <div class="meta">Dicetak: {{ $tanggal }} &nbsp;•&nbsp; Stock besi 600 cm/batang &nbsp;•&nbsp; maks 1 sambungan/potong</div>

  @php $in = $d['input']; @endphp
  <div class="inputbox">
    <b>Ukuran:</b> Lebar {{ $in['L'] }}cm × Panjang {{ $in['P'] }}cm × Tinggi {{ $in['T'] }}cm
    &nbsp;|&nbsp; Kotak support ±{{ $in['kotak'] }}cm ({{ $in['arah']==2?'grid 2 arah':'1 arah' }})
    &nbsp;|&nbsp; Tiang {{ $in['nTiang'] }} titik
  </div>

  <div class="denah-wrap">
    <div><h3>Denah Rangka (tampak atas)</h3><div id="denah"></div></div>
    <div><h3>Tampak Samping (tiang)</h3><div id="samping"></div></div>
  </div>

  <table class="sum">
    <tr><th>Bagian</th><th>Jumlah potong</th><th>Panjang</th></tr>
    @php $r=$d['rincian_jumlah']; @endphp
    @if($r['frame_vertikal']['qty'])<tr><td>Frame vertikal (kiri/kanan/tengah)</td><td>{{ $r['frame_vertikal']['qty'] }}</td><td>{{ $r['frame_vertikal']['len'] }} cm</td></tr>@endif
    @if($r['frame_horizontal']['qty'])<tr><td>Frame horizontal (depan/belakang/tengah)</td><td>{{ $r['frame_horizontal']['qty'] }}</td><td>{{ $r['frame_horizontal']['len'] }} cm</td></tr>@endif
    @if($r['support_vertikal']['qty'])<tr><td>Support vertikal</td><td>{{ $r['support_vertikal']['qty'] }}</td><td>{{ $r['support_vertikal']['len'] }} cm</td></tr>@endif
    @if($r['support_horizontal']['qty'])<tr><td>Support horizontal</td><td>{{ $r['support_horizontal']['qty'] }}</td><td>{{ $r['support_horizontal']['len'] }} cm</td></tr>@endif
    @if($r['tiang']['qty'])<tr><td>Tiang</td><td>{{ $r['tiang']['qty'] }}</td><td>{{ $r['tiang']['len'] }} cm</td></tr>@endif
    <tr><th>TOTAL BATANG</th><th colspan="2">{{ $d['total_batang'] }} batang</th></tr>
    @if($lihatHarga && $d['total_biaya_besi'])<tr><th>Estimasi biaya besi</th><th colspan="2">Rp {{ number_format($d['total_biaya_besi'],0,',','.') }}</th></tr>@endif
  </table>

  @if($lihatHarga && !empty($d['harga']))
    @php $g=$d['harga']; @endphp
    <table class="sum">
      <tr><th colspan="2">Harga Jual (besi + upah + atap)</th></tr>
      @if(!empty($g['peringatan']))
        <tr><td colspan="2" style="color:#b91c1c">⚠ {!! implode('<br>⚠ ', array_map('e',$g['peringatan'])) !!}</td></tr>
      @endif
      <tr><td>Biaya besi</td><td>Rp {{ number_format($g['besi'],0,',','.') }}</td></tr>
      @if(!empty($g['rangka']))
        @php $u=$g['rangka']; @endphp
        <tr><td>Upah rangka — {{ $u['jenis_kerja'] }}<br><small>{{ $u['luas'] }} m² ÷ {{ $u['produktivitas']?:'-' }} = {{ $u['hari'] }} hari · {{ $u['jml_tukang'] }}tk+{{ $u['jml_kenek'] }}kn @if(!empty($u['kondisi'])) · {{ implode(', ',$u['kondisi']) }} ×{{ $u['pengali'] }}@endif</small></td><td>Rp {{ number_format($g['upah_rangka'],0,',','.') }}</td></tr>
      @endif
      @foreach($g['atap'] as $a)
        <tr><td>Atap — {{ $a['nama'] }} ({{ $a['luas'] }} m²)<br><small>material Rp {{ number_format($a['material'],0,',','.') }} (boros {{ $a['boros'] }}%) + pasang Rp {{ number_format($a['upah'],0,',','.') }}</small></td><td>Rp {{ number_format($a['subtotal'],0,',','.') }}</td></tr>
      @endforeach
      @php $grupAddon = ['rangka'=>'Add-on Rangka','atap'=>'Add-on Atap','total'=>'Add-on Total/Project']; @endphp
      @foreach($grupAddon as $lv => $judulLv)
        @php $itemsLv = collect($g['addon'])->filter(function($x) use ($lv){ return ($x['level'] ?? 'total')===$lv; }); @endphp
        @if($itemsLv->count())
          <tr><td colspan="2" style="color:#92670a;font-weight:bold;background:#fffbeb">{{ $judulLv }}</td></tr>
          @foreach($itemsLv as $a)
            @if(($a['formula'] ?? '')==='flat')
              <tr><td>{{ $a['nama'] }} (lumpsum)</td><td>Rp {{ number_format($a['biaya'],0,',','.') }}</td></tr>
            @else
              <tr><td>{{ $a['nama'] }} ({{ $a['qty'] }} {{ $a['satuan'] ?? '' }} × Rp {{ number_format($a['harga'],0,',','.') }})</td><td>Rp {{ number_format($a['biaya'],0,',','.') }}</td></tr>
            @endif
          @endforeach
        @endif
      @endforeach
      <tr><th>Biaya pokok</th><th>Rp {{ number_format($g['pokok'],0,',','.') }}</th></tr>
      <tr><td>Margin</td><td>{{ $g['margin_persen'] }}%</td></tr>
      <tr><th>HARGA JUAL</th><th>Rp {{ number_format($g['jual'],0,',','.') }}</th></tr>
    </table>
  @endif

  @php
    $JC = ['#f59e0b','#22c55e','#ec4899','#06b6d4','#a855f7','#ef4444','#84cc16','#f97316','#14b8a6','#eab308'];
    $jcol = fn($jid) => $JC[($jid-1)%count($JC)];
    $jlet = fn($jid) => chr(64+$jid);
  @endphp

  @foreach($d['per_material'] as $m)
    @php
      $joinBars=[];
      foreach($m['bars'] as $bar){ foreach($bar['seg'] as $s){ if(($s['jenis']??'')==='sambung'){ $joinBars[$s['jid']][]=['bar'=>$bar['no'],'len'=>$s['len'],'label'=>$s['label']]; } } }
    @endphp
    <div class="mat">
      <h2>{{ $m['material'] }} — {{ $m['jumlah_batang'] }} batang @if($m['sambungan']) · {{ $m['sambungan'] }} sambungan @endif
        @if($lihatHarga && $m['subtotal_besi']) <span style="float:right;font-size:12px">Rp {{ number_format($m['subtotal_besi'],0,',','.') }}</span>@endif
      </h2>
      @foreach($m['bars'] as $bar)
        <div class="batang">
          <div class="bl">Batang #{{ $bar['no'] }}</div>
          <div class="bar">
            @foreach($bar['seg'] as $s)
              @if(($s['jenis'] ?? '')==='sambung')
                @php $c=$jcol($s['jid']); $lt=$jlet($s['jid']); @endphp
                <div class="seg" style="width:{{ number_format($s['len']/600*100,2) }}%;background:{{ $c }};color:#111">{{ $s['len'] }} {{ $s['label'] }}·{{ $lt }}</div>
              @else
                <div class="seg" style="width:{{ number_format($s['len']/600*100,2) }}%;background:#93c5fd;color:#111">{{ $s['len'] }} {{ $s['label'] }}</div>
              @endif
            @endforeach
            @if($bar['sisa']>0)<div class="seg sisa" style="width:{{ number_format($bar['sisa']/600*100,2) }}%">sisa {{ $bar['sisa'] }}</div>@endif
          </div>
          <ul class="potong">
            @foreach($bar['seg'] as $s)
              <li>
                potong <b>{{ $s['len'] }} cm</b> — {{ $s['label'] }}
                @if(($s['jenis'] ?? '')==='sambung')
                  @php $c=$jcol($s['jid']); $lt=$jlet($s['jid']); @endphp
                  <span class="dot" style="background:{{ $c }}"></span><b style="color:{{ $c }}">sambungan {{ $lt }}</b>
                @endif
              </li>
            @endforeach
            @if($bar['sisa']>0)<li style="color:#666">sisa {{ $bar['sisa'] }} cm</li>@endif
          </ul>
        </div>
      @endforeach

      @if(count($joinBars))
        <div class="sambox">
          <div class="t">Daftar Sambungan (las):</div>
          @foreach($joinBars as $jid => $parts)
            @php $c=$jcol($jid); $lt=$jlet($jid); $nm=$parts[0]['label'];
              $txt=implode(' + ', array_map(fn($p)=>$p['len'].'cm (Batang #'.$p['bar'].')', $parts)); @endphp
            <div><span class="dot" style="background:{{ $c }}"></span><b>Sambungan {{ $lt }}</b> — {{ $nm }}: las {{ $txt }}</div>
          @endforeach
        </div>
      @endif

      <div class="legend"><i style="background:#93c5fd"></i>potong utuh &nbsp; <i style="background:#f59e0b"></i>perlu sambung (warna = pasangannya) &nbsp; <i style="background:#e5e7eb"></i>sisa</div>
    </div>
  @endforeach

  <script>
  const D=@json($d);
  function drawDenah(dn){
    if(!dn||!dn.L||!dn.P) return '';
    const maxW=320, sc=maxW/dn.L, x0=60, y0=40, W=dn.L*sc, H=dn.P*sc;
    const PX=cm=>x0+cm*sc, PY=cm=>y0+cm*sc;
    let s=`<svg width="100%" viewBox="0 0 ${x0+W+80} ${y0+H+50}">`;
    s+=`<text x="${x0+W/2}" y="20" text-anchor="middle" fill="#555" font-size="11">Lebar ${dn.L}cm · kotak ±${dn.kotak_l}×${dn.kotak_p}cm</text>`;
    s+=`<text x="${x0-42}" y="${y0+H/2}" text-anchor="middle" fill="#555" font-size="11">${dn.P}cm</text>`;
    dn.h.forEach(l=>{ const y=PY(l.y); const f=l.tipe==='frame';
      s+=`<line x1="${x0}" y1="${y}" x2="${x0+W}" y2="${y}" stroke="${f?'#BA7517':'#1d4ed8'}" stroke-width="${f?4:2}"/>`;
      if(!f) s+=`<text x="${x0+4}" y="${y-3}" fill="#1d4ed8" font-size="10">${l.nama}</text>`;
    });
    dn.v.forEach(l=>{ const x=PX(l.x); const f=l.tipe==='frame';
      s+=`<line x1="${x}" y1="${y0}" x2="${x}" y2="${y0+H}" stroke="${f?'#BA7517':'#1d4ed8'}" stroke-width="${f?4:2}"/>`;
      if(!f) s+=`<text x="${x}" y="${y0-4}" text-anchor="middle" fill="#1d4ed8" font-size="10">${l.nama}</text>`;
    });
    (dn.tiang||[]).forEach(t=>{ s+=`<circle cx="${PX(t.x)}" cy="${PY(t.y)}" r="6" fill="#854F0B"/>`; });
    s+=`<text x="${x0+W/2}" y="${y0-24}" text-anchor="middle" fill="#BA7517" font-size="11">Frame depan</text>`;
    s+=`<text x="${x0+W/2}" y="${y0+H+18}" text-anchor="middle" fill="#BA7517" font-size="11">Frame belakang</text>`;
    s+=`<text x="${x0-6}" y="${y0+14}" text-anchor="end" fill="#BA7517" font-size="11">kiri</text>`;
    s+=`<text x="${x0+W+6}" y="${y0+14}" fill="#BA7517" font-size="11">kanan</text>`;
    s+='</svg>'; return s;
  }
  function drawSamping(dn){
    if(!dn||!dn.T||!(dn.tiang||[]).length) return '<div style="font-size:11px;color:#999">—</div>';
    const maxW=300, sc=maxW/dn.L, x0=20, yTop=24, W=dn.L*sc, Hs=Math.min(dn.T*sc,140);
    const PX=cm=>x0+cm*sc, yGround=yTop+Hs;
    let s=`<svg width="100%" viewBox="0 0 ${x0+W+60} ${yGround+24}">`;
    s+=`<line x1="${x0}" y1="${yTop}" x2="${x0+W}" y2="${yTop}" stroke="#BA7517" stroke-width="4"/>`;
    dn.tiang.forEach(t=>{ const x=PX(t.x);
      s+=`<line x1="${x}" y1="${yTop}" x2="${x}" y2="${yGround}" stroke="#854F0B" stroke-width="5"/>`;
    });
    s+=`<line x1="${x0+W+12}" y1="${yTop}" x2="${x0+W+12}" y2="${yGround}" stroke="#999" stroke-width="1"/>`;
    s+=`<text x="${x0+W+16}" y="${(yTop+yGround)/2}" fill="#555" font-size="11">tinggi ${dn.T}cm</text>`;
    s+=`<line x1="${x0}" y1="${yGround}" x2="${x0+W}" y2="${yGround}" stroke="#999" stroke-width="1" stroke-dasharray="4 3"/>`;
    s+='</svg>'; return s;
  }
  window.onload=function(){
    document.getElementById('denah').innerHTML=drawDenah(D.denah);
    document.getElementById('samping').innerHTML=drawSamping(D.denah);
    setTimeout(function(){ window.print(); }, 500);
  };
  </script>
</body>
</html>