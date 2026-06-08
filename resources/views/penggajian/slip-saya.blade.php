{{-- FILE: resources/views/penggajian/slip-saya.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Slip Gaji Saya</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:480px; margin:0 auto; padding-bottom:100px; }
  .card-slip { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; text-decoration:none; display:block; transition:border-color 0.2s; }
  .card-slip:hover { border-color:#fbbf24; }
  .slip-nominal { font-size:24px; font-weight:800; color:#10b981; margin:8px 0; }
  .slip-periode { font-size:12px; font-weight:600; }
  .slip-bulan { font-size:13px; color:#64748b; margin-top:2px; }
  .badge-status { font-size:10px; padding:3px 10px; border-radius:20px; }
  .stat-mini { display:flex; gap:12px; font-size:11px; color:#64748b; margin-top:8px; }
  .btn-lihat { display:block; text-align:center; background:#334155; color:#e2e8f0; border-radius:8px; padding:8px; font-size:13px; font-weight:600; text-decoration:none; margin-top:10px; }
  .empty-state { text-align:center; padding:60px 20px; color:#475569; }
  .empty-icon { font-size:48px; margin-bottom:16px; }

  /* Summary card */
  .summary-card { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:16px; }
  .summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .summary-item { background:#0f172a; border-radius:8px; padding:12px; text-align:center; }
  .summary-val { font-size:16px; font-weight:700; }
  .summary-lbl { font-size:10px; color:#64748b; margin-top:2px; }

  /* Tab filter */
  .tab-wrap { display:flex; gap:8px; margin-bottom:16px; }
  .tab-btn { flex:1; padding:8px; border-radius:8px; border:1px solid #334155; background:#1e293b; color:#94a3b8; font-size:12px; font-weight:600; text-align:center; text-decoration:none; }
  .tab-btn.active { background:#fbbf24; color:#0f172a; border-color:#fbbf24; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ url('/dashboard') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Slip Gaji Saya</div>
    <div style="color:#64748b; font-size:12px;">{{ $user->name }}</div>
  </div>
</div>

<div class="content">

  {{-- Summary Total --}}
  @if($slips->count() > 0)
  <div class="summary-card">
    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">📊 Ringkasan</div>
    <div class="summary-grid">
      <div class="summary-item">
        <div class="summary-val" style="color:#10b981;">Rp {{ number_format($slips->where('status','dibayar')->sum('gaji_bersih'),0,',','.') }}</div>
        <div class="summary-lbl">Total Diterima</div>
      </div>
      <div class="summary-item">
        <div class="summary-val" style="color:#fbbf24;">{{ $slips->where('status','dibayar')->count() }}</div>
        <div class="summary-lbl">Slip Dibayar</div>
      </div>
      <div class="summary-item">
        <div class="summary-val" style="color:#8b5cf6;">Rp {{ number_format($slips->where('status','dibayar')->sum('tabungan_wajib'),0,',','.') }}</div>
        <div class="summary-lbl">Tabungan Wajib</div>
      </div>
      <div class="summary-item">
        <div class="summary-val" style="color:#06b6d4;">Rp {{ number_format($slips->where('status','dibayar')->sum('tabungan_lebaran'),0,',','.') }}</div>
        <div class="summary-lbl">Tabungan Lebaran</div>
      </div>
    </div>
  </div>
  @endif

  {{-- Tab Filter --}}
  <div class="tab-wrap">
    <a href="{{ route('penggajian.slip-saya') }}" class="tab-btn {{ !request('periode') ? 'active' : '' }}">Semua</a>
    <a href="{{ route('penggajian.slip-saya', ['periode'=>'gaji_bulanan']) }}" class="tab-btn {{ request('periode')==='gaji_bulanan' ? 'active' : '' }}">💰 Gaji</a>
    <a href="{{ route('penggajian.slip-saya', ['periode'=>'uang_makan']) }}" class="tab-btn {{ request('periode')==='uang_makan' ? 'active' : '' }}">🍱 UM</a>
  </div>

  {{-- List Slip --}}
  @forelse($slips as $slip)
  @php
    $periodeColor = $slip->periode === 'gaji_bulanan' ? '#8b5cf6' : '#06b6d4';
    $periodeIcon  = $slip->periode === 'gaji_bulanan' ? '💰' : '🍱';
  @endphp
  <div class="card-slip">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="slip-periode" style="color:{{ $periodeColor }};">
          {{ $periodeIcon }} {{ $slip->periodeLabel() }}
        </div>
        <div class="slip-bulan">{{ $slip->namaBulan() }} {{ $slip->tahun }}</div>
      </div>
      <span class="badge-status" style="background:{{ $slip->statusColor() }}20;color:{{ $slip->statusColor() }};border:1px solid {{ $slip->statusColor() }}40;">
        {{ $slip->statusLabel() }}
      </span>
    </div>

    <div class="slip-nominal">Rp {{ number_format($slip->gaji_bersih, 0, ',', '.') }}</div>

    @if($slip->periode === 'gaji_bulanan')
    <div class="stat-mini">
      <span>✅ {{ $slip->hari_hadir }} hadir</span>
      <span>❌ {{ $slip->hari_alpha }} alpha</span>
      <span>⏰ {{ $slip->hari_telat }} telat</span>
      @if($slip->kelas_kpi !== 'none')
      <span>{{ $slip->kelasKpiLabel() }}</span>
      @endif
    </div>
    @endif

    @if($slip->bonus_kpi > 0)
    <div style="font-size:11px; color:#fbbf24; margin-top:4px;">
      🏆 Bonus KPI: Rp {{ number_format($slip->bonus_kpi, 0, ',', '.') }}
    </div>
    @endif

    @if($slip->potongan_kasbon > 0)
    <div style="font-size:11px; color:#ef4444; margin-top:2px;">
      💳 Cicilan kasbon: Rp {{ number_format($slip->potongan_kasbon, 0, ',', '.') }}
    </div>
    @endif

    @if($slip->tanggal_bayar)
    <div style="font-size:11px; color:#475569; margin-top:4px;">
      Dibayar: {{ $slip->tanggal_bayar->format('d/m/Y') }}
    </div>
    @endif

    <a href="{{ route('penggajian.slip', $slip) }}" class="btn-lihat">
      👁 Lihat & Print Slip Lengkap
    </a>
  </div>
  @empty
  <div class="empty-state">
    <div class="empty-icon">💰</div>
    <div style="font-size:15px; font-weight:600; margin-bottom:8px;">Belum ada slip gaji</div>
    <div style="font-size:13px;">Slip akan muncul di sini setelah owner memproses gaji</div>
  </div>
  @endforelse

</div>
</body>
</html>