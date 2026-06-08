{{-- FILE: resources/views/penggajian/slip.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Slip Gaji - {{ $slip->user->name }}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:16px; }
  .slip-container { max-width:520px; margin:0 auto; }
  .slip-card { background:#1e293b; border:1px solid #334155; border-radius:12px; overflow:hidden; margin-bottom:16px; }
  .slip-header { background:linear-gradient(135deg,#1a1a2e,#0f172a); padding:20px; border-bottom:1px solid #334155; text-align:center; }
  .slip-logo { font-size:32px; margin-bottom:8px; }
  .slip-company { color:#fbbf24; font-size:18px; font-weight:700; }
  .slip-subtitle { color:#64748b; font-size:12px; margin-top:4px; }
  .slip-periode { background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.3); border-radius:8px; padding:8px 16px; display:inline-block; margin-top:10px; }
  .slip-periode span { color:#fbbf24; font-size:13px; font-weight:600; }
  .info-row { display:flex; justify-content:space-between; padding:10px 16px; border-bottom:1px solid #0f172a; font-size:13px; }
  .info-label { color:#64748b; }
  .info-value { color:#f1f5f9; font-weight:500; text-align:right; }
  .section-header { background:#0f172a; padding:8px 16px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px; }
  .total-row { display:flex; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #0f172a; font-size:14px; font-weight:700; }
  .gaji-bersih { background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(16,185,129,0.05)); border-top:2px solid #10b981; padding:20px 16px; text-align:center; }
  .gaji-bersih-label { color:#64748b; font-size:12px; margin-bottom:6px; }
  .gaji-bersih-nominal { color:#10b981; font-size:24px; font-weight:800; }
  .warning-box { background:rgba(245,158,11,0.15); border:1px solid #f59e0b; border-radius:10px; padding:12px; margin-bottom:16px; font-size:13px; color:#fcd34d; }

  /* Tombol aksi */
  .btn-wrap { display:flex; gap:10px; margin-bottom:16px; }
  .btn-download { flex:1; padding:14px; background:#10b981; color:#fff; border:none; border-radius:10px; font-weight:700; font-size:15px; cursor:pointer; }
  .btn-back { flex:1; padding:14px; background:#334155; color:#e2e8f0; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; display:block; text-align:center; line-height:1.2; }
  .btn-loading { opacity:0.7; cursor:not-allowed; }

  /* Rekap UM */
  .rekap-table { width:100%; border-collapse:collapse; }
  .rekap-table th { padding:8px 12px; font-size:11px; color:#64748b; font-weight:600; text-align:left; border-bottom:1px solid #334155; }
  .rekap-table td { padding:8px 12px; font-size:12px; border-bottom:1px solid #0f172a; }

  /* PDF mode - override dark colors */
  .pdf-mode body, .pdf-mode .slip-card { background:#fff !important; color:#000 !important; }

  @media print {
    .btn-wrap, .no-print { display:none !important; }
  }
</style>
</head>
<body>

<div class="slip-container">

  @if($slip->warning_batas_aman && $slip->status !== 'dibayar')
  <div class="warning-box no-print">⚠️ Gaji bersih di bawah batas aman (Rp 500.000). Perlu konfirmasi owner.</div>
  @endif

  {{-- Tombol Aksi --}}
  <div class="btn-wrap no-print">
    <button class="btn-download" id="btnDownload" onclick="downloadPDF()">
      📥 Download PDF
    </button>
    <a href="{{ route('penggajian.index') }}" class="btn-back">← Kembali</a>
  </div>

  {{-- Konten Slip (yang akan di-PDF) --}}
  <div id="slipContent" class="slip-card">
    {{-- Header --}}
    <div class="slip-header">
      <div class="slip-logo">🏠</div>
      <div class="slip-company">Pusat Kanopi BSD</div>
      <div class="slip-subtitle">CanopiBSD Management System</div>
      <div class="slip-periode">
        <span>{{ $slip->periodeLabel() }} — {{ $slip->namaBulan() }} {{ $slip->tahun }}</span>
      </div>
    </div>

    {{-- Info Karyawan --}}
    <div class="section-header">👤 Data Karyawan</div>
    <div class="info-row"><span class="info-label">Nama</span><span class="info-value">{{ $slip->user->name }}</span></div>
    <div class="info-row"><span class="info-label">Jabatan</span><span class="info-value">{{ $slip->user->jabatan }}</span></div>
    <div class="info-row"><span class="info-label">Bank</span><span class="info-value">{{ $slip->user->nama_bank ?? '-' }}</span></div>
    <div class="info-row"><span class="info-label">No. Rekening</span><span class="info-value">{{ $slip->user->no_rekening ?? '-' }}</span></div>
    <div class="info-row"><span class="info-label">Tanggal Generate</span><span class="info-value">{{ $slip->tanggal_generate->format('d/m/Y') }}</span></div>
    <div class="info-row"><span class="info-label">Status</span><span class="info-value">{{ $slip->statusLabel() }}</span></div>

    @if($slip->periode === 'uang_makan')
    {{-- REKAP DETAIL UM PER HARI --}}
    <div class="section-header">📅 Detail Uang Makan Tanggal 1–15</div>
    <div style="overflow-x:auto;">
      <table class="rekap-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Status</th>
            <th style="text-align:right;">UM</th>
          </tr>
        </thead>
        <tbody>
        @php
          $totalUM = 0;
          $namaHari = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
          $namaStatus = ['hadir'=>'Hadir','telat'=>'Telat','setengah_hari'=>'½ Hari','alpha'=>'Alpha','sakit'=>'Sakit','izin'=>'Izin','cuti'=>'Cuti','dinas_luar'=>'Dinas'];
          $warnaStatus = ['hadir'=>'#10b981','telat'=>'#f59e0b','setengah_hari'=>'#8b5cf6','alpha'=>'#ef4444','sakit'=>'#06b6d4','izin'=>'#6366f1','cuti'=>'#06b6d4','dinas_luar'=>'#94a3b8'];
        @endphp
        @for($tgl = 1; $tgl <= 15; $tgl++)
        @php
          $date    = \Carbon\Carbon::createFromDate($slip->tahun, $slip->bulan, $tgl);
          $dayName = $date->format('l');
          $isLibur = $dayName === 'Sunday';
          $absen   = $absensiDetail->get($date->format('Y-m-d'));
          $um      = $absen ? $absen->uang_makan_hari_ini : 0;
          $status  = $absen ? ($absen->status ?? '-') : ($isLibur ? 'libur' : '-');
          $totalUM += $um;
        @endphp
        <tr style="{{ $isLibur ? 'opacity:0.4;' : '' }}">
          <td style="color:#f1f5f9;">{{ $tgl }} {{ substr($slip->namaBulan(),0,3) }}</td>
          <td style="color:#94a3b8;">{{ $namaHari[$dayName] ?? '' }}</td>
          <td style="color:{{ $warnaStatus[$status] ?? '#64748b' }};">
            {{ $isLibur ? 'Libur' : ($namaStatus[$status] ?? '-') }}
          </td>
          <td style="text-align:right;color:{{ $um > 0 ? '#10b981' : '#475569' }};">
            {{ $um > 0 ? 'Rp '.number_format($um,0,',','.') : '—' }}
          </td>
        </tr>
        @endfor
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid #334155;">
            <td colspan="3" style="padding:10px 12px;font-weight:700;color:#f1f5f9;">Total</td>
            <td style="padding:10px 12px;text-align:right;font-weight:700;color:#10b981;">Rp {{ number_format($totalUM,0,',','.') }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="gaji-bersih">
      <div class="gaji-bersih-label">TOTAL UANG MAKAN 1–15</div>
      <div class="gaji-bersih-nominal">Rp {{ number_format($slip->total_uang_makan,0,',','.') }}</div>
      @if($slip->tanggal_bayar)
      <div style="color:#64748b;font-size:11px;margin-top:6px;">Dibayarkan: {{ $slip->tanggal_bayar->format('d/m/Y') }}</div>
      @endif
    </div>

    @else
    {{-- SLIP GAJI BULANAN --}}
    <div class="section-header">📋 Rekap Absensi</div>
    <div class="info-row"><span class="info-label">Hari Hadir</span><span class="info-value" style="color:#10b981;">{{ $slip->hari_hadir }} hari</span></div>
    <div class="info-row"><span class="info-label">Hari Izin/Sakit</span><span class="info-value" style="color:#6366f1;">{{ $slip->hari_izin }} hari</span></div>
    <div class="info-row"><span class="info-label">Hari Telat</span><span class="info-value" style="color:#f59e0b;">{{ $slip->hari_telat }} hari</span></div>
    <div class="info-row"><span class="info-label">Hari Alpha</span><span class="info-value" style="color:#ef4444;">{{ $slip->hari_alpha }} hari</span></div>
    <div class="info-row">
      <span class="info-label">KPI</span>
      <span class="info-value">
        @if($slip->kelas_kpi !== 'none')
          <span style="background:rgba(251,191,36,0.2);color:#fbbf24;padding:2px 10px;border-radius:20px;font-size:11px;">{{ $slip->kelasKpiLabel() }}</span>
        @else
          <span style="color:#64748b;">— Tidak dapat bonus</span>
        @endif
      </span>
    </div>

    <div class="section-header">💚 Pendapatan</div>
    @if($slip->gaji_pokok > 0)
    <div class="info-row"><span class="info-label">Gaji Pokok</span><span class="info-value">Rp {{ number_format($slip->gaji_pokok,0,',','.') }}</span></div>
    @endif
    @if($slip->total_uang_makan > 0)
    <div class="info-row"><span class="info-label">Uang Makan (16-akhir)</span><span class="info-value">Rp {{ number_format($slip->total_uang_makan,0,',','.') }}</span></div>
    @endif
    @if($slip->total_tunjangan > 0)
    <div class="info-row"><span class="info-label">Tunjangan</span><span class="info-value">Rp {{ number_format($slip->total_tunjangan,0,',','.') }}</span></div>
    @endif
    @if($slip->bonus_kpi > 0)
    <div class="info-row"><span class="info-label">Bonus KPI</span><span class="info-value" style="color:#fbbf24;">Rp {{ number_format($slip->bonus_kpi,0,',','.') }}</span></div>
    @endif
    @if($slip->bonus_lembur > 0)
    <div class="info-row"><span class="info-label">Lembur ({{ $slip->jam_lembur }} jam)</span><span class="info-value" style="color:#06b6d4;">Rp {{ number_format($slip->bonus_lembur,0,',','.') }}</span></div>
    @endif
    <div class="total-row" style="color:#10b981;"><span>Total Pendapatan</span><span>Rp {{ number_format($slip->total_pendapatan,0,',','.') }}</span></div>

    @if($slip->total_potongan > 0)
    <div class="section-header">❤️ Potongan</div>
    @if($slip->potongan_telat > 0)
    <div class="info-row"><span class="info-label">Potongan Telat</span><span class="info-value" style="color:#ef4444;">- Rp {{ number_format($slip->potongan_telat,0,',','.') }}</span></div>
    @endif
    @if($slip->potongan_kasbon > 0)
    <div class="info-row"><span class="info-label">Cicilan Kasbon</span><span class="info-value" style="color:#ef4444;">- Rp {{ number_format($slip->potongan_kasbon,0,',','.') }}</span></div>
    @endif
    @if($slip->potongan_insidental > 0)
    <div class="info-row"><span class="info-label">Potongan Insidental</span><span class="info-value" style="color:#ef4444;">- Rp {{ number_format($slip->potongan_insidental,0,',','.') }}</span></div>
    @endif
    @if($slip->tabungan_wajib > 0)
    <div class="info-row"><span class="info-label">Tabungan Wajib</span><span class="info-value" style="color:#8b5cf6;">- Rp {{ number_format($slip->tabungan_wajib,0,',','.') }}</span></div>
    @endif
    @if($slip->tabungan_lebaran > 0)
    <div class="info-row"><span class="info-label">Tabungan Lebaran</span><span class="info-value" style="color:#8b5cf6;">- Rp {{ number_format($slip->tabungan_lebaran,0,',','.') }}</span></div>
    @endif
    <div class="total-row" style="color:#ef4444;"><span>Total Potongan</span><span>- Rp {{ number_format($slip->total_potongan,0,',','.') }}</span></div>
    @endif

    <div class="gaji-bersih">
      <div class="gaji-bersih-label">GAJI BERSIH DITERIMA</div>
      <div class="gaji-bersih-nominal">Rp {{ number_format($slip->gaji_bersih,0,',','.') }}</div>
      @if($slip->tanggal_bayar)
      <div style="color:#64748b;font-size:11px;margin-top:6px;">Dibayarkan: {{ $slip->tanggal_bayar->format('d/m/Y') }}</div>
      @endif
    </div>
    @endif

    <div style="padding:14px 16px;text-align:center;border-top:1px solid #334155;">
      <div style="font-size:11px;color:#475569;">
        Slip digenerate otomatis oleh CanopiBSD v2.0<br>
        {{ now()->format('d/m/Y H:i') }}
      </div>
    </div>
  </div>{{-- end slipContent --}}

</div>

<script>
function downloadPDF() {
  const btn = document.getElementById('btnDownload');
  btn.textContent = '⏳ Menyiapkan PDF...';
  btn.disabled = true;
  btn.classList.add('btn-loading');

  const element = document.getElementById('slipContent');
  const nama    = '{{ $slip->user->name }}';
  const periode = '{{ $slip->periodeLabel() }}';
  const bulan   = '{{ $slip->namaBulan() }}';
  const tahun   = '{{ $slip->tahun }}';
  const filename = `Slip-${periode}-${bulan}-${tahun}-${nama}.pdf`.replace(/\s+/g, '-');

  const opt = {
    margin:       [10, 10, 10, 10],
    filename:     filename,
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#1e293b' },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };

  html2pdf().set(opt).from(element).save().then(() => {
    btn.textContent = '✅ PDF Berhasil Didownload!';
    btn.style.background = '#10b981';
    setTimeout(() => {
      btn.textContent = '📥 Download PDF';
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }, 3000);
  }).catch(() => {
    btn.textContent = '❌ Gagal, coba lagi';
    btn.disabled = false;
    btn.classList.remove('btn-loading');
  });
}
</script>
</body>
</html>