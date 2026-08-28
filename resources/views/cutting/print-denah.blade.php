<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }}</title>
<style>
  /* Pola sama cutting/print.blade.php — dokumen PRODUKSI: tanpa harga. */
  * { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  body { font-family:Arial, sans-serif; color:#111; margin:24px; font-size:12px; }
  h1 { font-size:18px; margin:0 0 2px; }
  .meta { color:#555; font-size:11px; margin-bottom:14px; }
  .blok { margin-bottom:26px; }
  .blok > h2 { font-size:15px; margin:0 0 8px; background:#1f2937; color:#fff; padding:6px 10px; border-radius:6px; }
  .denah-img { border:1px solid #e5e7eb; border-radius:6px; padding:6px; margin-bottom:10px; page-break-inside:avoid; }
  .denah-img img { max-width:100%; height:auto; display:block; }
  .mat { margin-bottom:18px; page-break-inside:avoid; }
  .mat h3 { font-size:13px; margin:0 0 6px; border-bottom:2px solid #fbbf24; padding-bottom:3px; }
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
  .warnbox { background:#fef3c7; border:1px solid #f59e0b; color:#92400e; border-radius:6px; padding:8px 12px; font-size:11px; margin-bottom:14px; }
  .toolbar { margin-bottom:14px; }
  .btn { background:#fbbf24; border:none; border-radius:6px; padding:9px 16px; font-weight:bold; cursor:pointer; font-size:13px; }
  @media print { .toolbar { display:none; } body { margin:0; } }
</style>
</head>
<body>
  <div class="toolbar">
    <button class="btn" onclick="window.print()">&#128424; Print / Simpan PDF</button>
  </div>

  <h1>{{ $judul }}</h1>
  <div class="meta">Dicetak: {{ $tanggal }} &nbsp;&bull;&nbsp; Dokumen produksi &mdash; tanpa harga. Potongan lebih panjang dari 1 batang disambung berurutan (satu huruf sambungan = satu batang jadi).</div>

  @if(!empty($peringatan))
    <div class="warnbox">&#9888; {{ $peringatan }}</div>
  @endif

  @foreach($bloks as $bd)
    <div class="blok">
      <h2>{{ $bd['opsi'] }} &mdash; {{ $bd['blok'] }}</h2>

      @if(!empty($bd['denah_svg']))
        {{-- <img> data-URI: browser mematikan script/sumber luar di dalam img (pola sama penawaran) --}}
        <div class="denah-img"><img src="data:image/svg+xml;base64,{{ base64_encode($bd['denah_svg']) }}" alt="Denah {{ $bd['blok'] }}"></div>
      @endif

      @php
        $jcol = fn($jid) => ['#f59e0b','#22c55e','#38bdf8','#f472b6','#c084fc','#fb7185','#2dd4bf','#facc15'][($jid-1) % 8];
        $jlet = fn($jid) => chr(64 + (($jid - 1) % 26) + 1);
      @endphp
      @foreach($bd['hasil']['per_material'] as $m)
        @php
          $joinBars = [];
          foreach ($m['bars'] as $bar) foreach ($bar['seg'] as $s)
            if (($s['jenis'] ?? '') === 'sambung') $joinBars[$s['jid']][] = ['bar' => $bar['no'], 'len' => $s['len'], 'label' => $s['label']];
        @endphp
        <div class="mat">
          <h3>{{ $m['material'] }} &mdash; {{ $m['jumlah_batang'] }} batang @if($m['sambungan']) &middot; {{ $m['sambungan'] }} sambungan @endif</h3>
          @foreach($m['bars'] as $bar)
            @php $stockBar = $bar['sisa'] + array_sum(array_column($bar['seg'], 'len')); @endphp
            <div class="batang">
              <div class="bl">Batang #{{ $bar['no'] }}</div>
              <div class="bar">
                @foreach($bar['seg'] as $s)
                  @if(($s['jenis'] ?? '')==='sambung')
                    <div class="seg" style="width:{{ number_format($s['len']/$stockBar*100,2) }}%;background:{{ $jcol($s['jid']) }}">{{ $s['len'] }} {{ $s['label'] }}&middot;{{ $jlet($s['jid']) }}</div>
                  @else
                    <div class="seg" style="width:{{ number_format($s['len']/$stockBar*100,2) }}%;background:#93c5fd">{{ $s['len'] }} {{ $s['label'] }}</div>
                  @endif
                @endforeach
                @if($bar['sisa']>0)<div class="seg sisa" style="width:{{ number_format($bar['sisa']/$stockBar*100,2) }}%">sisa {{ $bar['sisa'] }}</div>@endif
              </div>
              <ul class="potong">
                @foreach($bar['seg'] as $s)
                  <li>potong <b>{{ $s['len'] }} cm</b> &mdash; {{ $s['label'] }}
                    @if(($s['jenis'] ?? '')==='sambung')
                      <span class="dot" style="background:{{ $jcol($s['jid']) }}"></span><b style="color:{{ $jcol($s['jid']) }}">sambungan {{ $jlet($s['jid']) }}</b>
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
                @php $txt = implode(' + ', array_map(fn($p) => $p['len'].'cm (Batang #'.$p['bar'].')', $parts)); @endphp
                <div><span class="dot" style="background:{{ $jcol($jid) }}"></span><b>Sambungan {{ $jlet($jid) }}</b> &mdash; {{ $parts[0]['label'] }}: las {{ $txt }}</div>
              @endforeach
            </div>
          @endif

          <div class="legend"><i style="background:#93c5fd"></i>potong utuh &nbsp; <i style="background:#f59e0b"></i>perlu sambung (huruf = pasangannya) &nbsp; <i style="background:#e5e7eb"></i>sisa</div>
        </div>
      @endforeach
    </div>
  @endforeach
</body>
</html>
