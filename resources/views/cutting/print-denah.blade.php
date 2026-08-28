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
  table.bg-tab { width:100%; border-collapse:collapse; margin-bottom:10px; }
  table.bg-tab th, table.bg-tab td { border:1px solid #ccc; padding:5px 8px; text-align:left; font-size:11px; vertical-align:top; }
  table.bg-tab th { background:#1f2937; color:#fff; }
  .tanda-las { background:#fbbf24; color:#111; font-weight:bold; font-size:10px; border-radius:4px; padding:1px 6px; margin:0 4px; white-space:nowrap; }
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
  <div class="meta">Dicetak: {{ $tanggal }} &nbsp;&bull;&nbsp; Dokumen produksi &mdash; tanpa harga. Urutan pakai: (1) tabel <b>Bagian</b> = apa yang mau dibuat &amp; caranya, (2) daftar <b>Batang</b> = potong batang mana saja. Kuning = perlu dilas; huruf sama = dilas jadi satu batang.</div>

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

      @foreach($bd['hasil']['per_material'] as $m)
        @php
          // ── Olah data utk tampilan (murni presentasi; mesin tak disentuh) ──
          // 1) rangkaian sambungan: jid => daftar keping (bar, len) + huruf A/B/C
          // 2) ringkasan per BAGIAN (F2, S3, B1...): semua potongannya di batang mana
          $chains = [];   // jid => ['label'=>..,'parts'=>[['bar'=>..,'len'=>..]]]
          $bagian = [];   // label => ['utuh'=>[['bar','len']], 'jids'=>[jid,..]]
          foreach ($m['bars'] as $bar) foreach ($bar['seg'] as $s) {
            $lab = $s['label'] !== '' ? $s['label'] : '—';
            if (($s['jenis'] ?? '') === 'sambung') {
              $chains[$s['jid']]['label'] = $lab;
              $chains[$s['jid']]['parts'][] = ['bar' => $bar['no'], 'len' => $s['len']];
              if (!in_array($s['jid'], $bagian[$lab]['jids'] ?? [], true)) $bagian[$lab]['jids'][] = $s['jid'];
            } else {
              $bagian[$lab]['utuh'][] = ['bar' => $bar['no'], 'len' => $s['len']];
            }
          }
          // huruf urut A,B,C sesuai urutan muncul (bukan nomor jid mentah yang bisa lompat)
          $huruf = []; $n = 0;
          foreach ($chains as $jid => $c) $huruf[$jid] = chr(65 + ($n++ % 26));
          $ket = function ($info) use ($chains, $huruf) {
            $t = [];
            foreach ($info['utuh'] ?? [] as $u) $t[] = "potong utuh {$u['len']} cm (Batang #{$u['bar']})";
            foreach ($info['jids'] ?? [] as $jid) {
              $c = $chains[$jid];
              $tot = array_sum(array_column($c['parts'], 'len'));
              $ps = implode(' + ', array_map(fn ($x) => "{$x['len']} cm (Batang #{$x['bar']})", $c['parts']));
              $t[] = "LAS \"{$huruf[$jid]}\" jadi satu ({$tot} cm): {$ps}";
            }
            return $t;
          };
        @endphp
        <div class="mat">
          <h3>{{ $m['material'] }} &mdash; beli {{ $m['jumlah_batang'] }} batang @if($m['sambungan']) &middot; {{ $m['sambungan'] }} titik las @endif</h3>

          {{-- LANGKAH 2 dulu di atas: tabel per BAGIAN — tukang yang mau bikin "F2"
               cukup baca satu baris, tak perlu memindai semua batang. --}}
          <table class="bg-tab">
            <tr><th>Bagian</th><th>Cara membuat</th></tr>
            @foreach($bagian as $lab => $info)
              <tr><td><b>{{ $lab }}</b></td><td>{!! implode('<br>', array_map('e', $ket($info))) !!}</td></tr>
            @endforeach
          </table>

          {{-- LANGKAH 1: potong per batang. 3 warna saja — biru=utuh, kuning=perlu las
               (huruf sama = dilas jadi satu), abu=sisa. Pasangan tiap keping ditulis
               LANGSUNG di barisnya, tidak perlu cari ke batang lain. --}}
          @foreach($m['bars'] as $bar)
            @php $stockBar = $bar['sisa'] + array_sum(array_column($bar['seg'], 'len')); @endphp
            <div class="batang">
              <div class="bl">Batang #{{ $bar['no'] }}</div>
              <div class="bar">
                @foreach($bar['seg'] as $s)
                  @if(($s['jenis'] ?? '')==='sambung')
                    <div class="seg" style="width:{{ number_format($s['len']/$stockBar*100,2) }}%;background:#fbbf24">{{ $s['label'] }}&nbsp;<b>{{ $huruf[$s['jid']] }}</b>&nbsp;{{ $s['len'] }}</div>
                  @else
                    <div class="seg" style="width:{{ number_format($s['len']/$stockBar*100,2) }}%;background:#93c5fd">{{ $s['label'] }} {{ $s['len'] }}</div>
                  @endif
                @endforeach
                @if($bar['sisa']>0)<div class="seg sisa" style="width:{{ number_format($bar['sisa']/$stockBar*100,2) }}%">sisa {{ $bar['sisa'] }}</div>@endif
              </div>
              <ul class="potong">
                @foreach($bar['seg'] as $s)
                  @if(($s['jenis'] ?? '')==='sambung')
                    @php
                      $c = $chains[$s['jid']]; $tot = array_sum(array_column($c['parts'], 'len'));
                      // buang HANYA SATU keping (keping ini sendiri) -- filter biasa akan
                      // membuang kembarannya juga kalau ada 2 keping sama panjang di 1 batang
                      $lain = $c['parts']; foreach ($lain as $i => $x) { if ($x['bar'] === $bar['no'] && $x['len'] === $s['len']) { unset($lain[$i]); break; } } $lain = array_values($lain);
                      $lainTxt = implode(' + ', array_map(fn ($x) => "{$x['len']} cm di Batang #{$x['bar']}", $lain));
                    @endphp
                    <li>potong <b>{{ $s['len'] }} cm</b> &mdash; {{ $s['label'] }}
                      <span class="tanda-las">LAS "{{ $huruf[$s['jid']] }}"</span>
                      <span style="color:#92400e">pasangannya: {{ $lainTxt }} (jadi {{ $tot }} cm)</span>
                    </li>
                  @else
                    <li>potong <b>{{ $s['len'] }} cm</b> &mdash; {{ $s['label'] }}</li>
                  @endif
                @endforeach
                @if($bar['sisa']>0)<li style="color:#666">sisa {{ $bar['sisa'] }} cm</li>@endif
              </ul>
            </div>
          @endforeach

          <div class="legend"><i style="background:#93c5fd"></i>potong utuh &nbsp; <i style="background:#fbbf24"></i>perlu dilas &mdash; huruf sama = dilas jadi satu &nbsp; <i style="background:#e5e7eb"></i>sisa</div>
        </div>
      @endforeach
    </div>
  @endforeach
</body>
</html>
