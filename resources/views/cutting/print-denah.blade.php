<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }}</title>
<style>
  /* Dokumen PRODUKSI: tanpa harga. Dipadatkan supaya 1 project muat ~1 lembar
     (permintaan Elvan 30 Ags): font kecil, batang ramping, tanpa daftar ganda. */
  * { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  body { font-family:Arial, sans-serif; color:#111; margin:16px; font-size:11px; }
  h1 { font-size:16px; margin:0 0 2px; }
  .meta { color:#555; font-size:10px; margin-bottom:10px; }
  .blok { margin-bottom:14px; }
  .blok > h2 { font-size:13px; margin:0 0 6px; background:#1f2937; color:#fff; padding:4px 8px; border-radius:5px; }
  .denah-img { border:1px solid #e5e7eb; border-radius:5px; padding:4px; margin-bottom:8px; page-break-inside:avoid; text-align:center; }
  .denah-img img { max-width:100%; max-height:230px; height:auto; }
  .mat { margin-bottom:10px; }
  .mat h3 { font-size:12px; margin:0 0 4px; border-bottom:2px solid #fbbf24; padding-bottom:2px; }
  table.bg-tab { width:100%; border-collapse:collapse; margin-bottom:6px; }
  table.bg-tab th, table.bg-tab td { border:1px solid #ccc; padding:3px 6px; text-align:left; font-size:10.5px; vertical-align:top; }
  table.bg-tab th { background:#1f2937; color:#fff; }
  .bg-jenis { color:#777; font-weight:normal; font-size:9.5px; }
  .batang { margin-bottom:5px; page-break-inside:avoid; }
  .bl { font-weight:bold; font-size:10.5px; }
  .bar { display:flex; height:20px; border:1px solid #333; border-radius:3px; overflow:hidden; }
  .seg { display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:bold;
         color:#111; border-right:1px solid #fff; white-space:nowrap; overflow:hidden; padding:0 2px; }
  .seg.sisa { background:#e5e7eb; color:#666; }
  .cutline { font-size:10px; color:#333; margin:1px 0 0 2px; }
  .cutline .las { background:#fbbf24; border-radius:3px; padding:0 4px; font-weight:bold; }
  .legend { font-size:9.5px; color:#555; margin-top:2px; }
  .legend i { display:inline-block;width:10px;height:10px;border-radius:2px;vertical-align:middle;margin-right:2px; }
  .warnbox { background:#fef3c7; border:1px solid #f59e0b; color:#92400e; border-radius:5px; padding:6px 10px; font-size:10.5px; margin-bottom:10px; }
  .toolbar { margin-bottom:10px; }
  .btn { background:#fbbf24; border:none; border-radius:6px; padding:8px 14px; font-weight:bold; cursor:pointer; font-size:13px; }
  @media print { .toolbar { display:none; } body { margin:0; } }
  @page { margin: 8mm; }
</style>
</head>
<body>
  <div class="toolbar">
    <button class="btn" onclick="window.print()">&#128424; Print / Simpan PDF</button>
  </div>

  <h1>{{ $judul }}</h1>
  <div class="meta">Dicetak: {{ $tanggal }} &nbsp;&bull;&nbsp; Baca tabel <b>Bagian</b> dulu (apa yang dibuat &amp; dari batang mana), lalu potong per <b>Batang</b>. Kuning = perlu dilas.</div>

  @if(!empty($peringatan))
    <div class="warnbox">&#9888; {{ $peringatan }}</div>
  @endif

  @php
    // Huruf depan nama bagian -> jenis, biar tukang tak perlu hafal kode.
    $jenisDari = fn ($lab) => ['F' => 'Frame', 'S' => 'Support', 'T' => 'Tiang', 'B' => 'Balok'][strtoupper(substr($lab, 0, 1))] ?? '';
  @endphp

  @foreach($bloks as $bd)
    <div class="blok">
      @if($bd['opsi'] !== '' || count($bloks) > 1)
        <h2>@if($bd['opsi'] !== ''){{ $bd['opsi'] }} &mdash; @endif{{ $bd['blok'] }}</h2>
      @endif

      @if(!empty($bd['denah_svg']))
        {{-- <img> data-URI: browser mematikan script/sumber luar di dalam img (pola sama penawaran) --}}
        <div class="denah-img"><img src="data:image/svg+xml;base64,{{ base64_encode($bd['denah_svg']) }}" alt="Denah {{ $bd['blok'] }}"></div>
      @endif

      @foreach($bd['hasil']['per_material'] as $m)
        @php
          // Rangkaian las dinamai pakai NAMA BAGIANNYA (F7, S3) — bukan huruf A/B/C
          // (permintaan Elvan 30 Ags: "nama sambungan F7"). Huruf perantara dihapus:
          // keping-keping F7 di batang mana pun otomatis berpasangan lewat namanya.
          $chains = [];   // jid => ['label','parts'=>[['bar','len']]]
          $bagian = [];   // label => ['utuh'=>[...], 'jids'=>[...]]
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
        @endphp
        <div class="mat">
          <h3>{{ $m['material'] }} &mdash; beli {{ $m['jumlah_batang'] }} batang @if($m['sambungan']) &middot; {{ $m['sambungan'] }} titik las @endif</h3>

          <table class="bg-tab">
            <tr><th style="width:80px">Bagian</th><th>Ukuran &amp; cara membuat</th></tr>
            @foreach($bagian as $lab => $info)
              @php
                $baris = [];
                foreach ($info['utuh'] ?? [] as $u) $baris[] = "{$u['len']} cm — potong utuh dari Batang #{$u['bar']}";
                foreach ($info['jids'] ?? [] as $jid) {
                  $c = $chains[$jid];
                  $tot = array_sum(array_column($c['parts'], 'len'));
                  $ps = implode(' + ', array_map(fn ($x) => "{$x['len']} (Batang #{$x['bar']})", $c['parts']));
                  $baris[] = "{$tot} cm — perlu dilas: {$ps}";
                }
              @endphp
              <tr>
                <td><b>{{ $lab }}</b> <span class="bg-jenis">{{ $jenisDari($lab) }}</span></td>
                <td>{!! implode('<br>', array_map('e', $baris)) !!}</td>
              </tr>
            @endforeach
          </table>

          @foreach($m['bars'] as $bar)
            @php
              $stockBar = $bar['sisa'] + array_sum(array_column($bar['seg'], 'len'));
              $potong = [];
              foreach ($bar['seg'] as $s) {
                $las = ($s['jenis'] ?? '') === 'sambung';
                $potong[] = "<b>{$s['len']}</b> " . e($s['label']) . ($las ? ' <span class="las">las</span>' : '');
              }
              if ($bar['sisa'] > 0) $potong[] = '<span style="color:#888">sisa ' . $bar['sisa'] . '</span>';
            @endphp
            <div class="batang">
              <span class="bl">Batang #{{ $bar['no'] }}:</span>
              <div class="bar">
                @foreach($bar['seg'] as $s)
                  <div class="seg" style="width:{{ number_format($s['len']/$stockBar*100,2) }}%;background:{{ ($s['jenis'] ?? '')==='sambung' ? '#fbbf24' : '#93c5fd' }}">{{ $s['label'] }} {{ $s['len'] }}</div>
                @endforeach
                @if($bar['sisa']>0)<div class="seg sisa" style="width:{{ number_format($bar['sisa']/$stockBar*100,2) }}%">sisa {{ $bar['sisa'] }}</div>@endif
              </div>
              <div class="cutline">potong: {!! implode(' &nbsp;&middot;&nbsp; ', $potong) !!}</div>
            </div>
          @endforeach

          <div class="legend"><i style="background:#93c5fd"></i>potong utuh &nbsp; <i style="background:#fbbf24"></i>perlu dilas &mdash; nama sama = dilas jadi satu (lihat tabel Bagian) &nbsp; <i style="background:#e5e7eb"></i>sisa</div>
        </div>
      @endforeach
    </div>
  @endforeach
</body>
</html>
