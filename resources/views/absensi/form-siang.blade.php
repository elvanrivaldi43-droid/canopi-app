{{-- FILE: resources/views/absensi/form-siang.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Absen Siang</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; padding:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .topbar-sub { color:#64748b; font-size:12px; }
  .content { padding:16px; padding-bottom:120px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
  .foto-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:10px; }
  .foto-slot { aspect-ratio:1; border-radius:8px; overflow:hidden; background:#0f172a; border:2px dashed #334155; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; }
  .foto-slot img { width:100%; height:100%; object-fit:cover; }
  .foto-slot.aktif { border-color:#fbbf24; }
  .foto-slot .plus { color:#475569; font-size:24px; }
  .foto-slot .hapus { position:absolute; top:4px; right:4px; background:rgba(239,68,68,0.8); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:12px; cursor:pointer; display:none; }
  .foto-slot:hover .hapus { display:block; }
  input.form-control-dark, select.form-control-dark, textarea.form-control-dark {
    background:#0f172a; border:1px solid #475569; color:#f1f5f9;
    border-radius:8px; padding:10px 12px; width:100%; font-size:14px;
  }
  input.form-control-dark:focus, select.form-control-dark:focus, textarea.form-control-dark:focus {
    border-color:#fbbf24; outline:none;
  }
  select.form-control-dark option { background:#1e293b; }
  .radio-group { display:flex; flex-direction:column; gap:8px; }
  .radio-item { display:flex; align-items:center; gap:10px; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px 12px; cursor:pointer; }
  .radio-item input { accent-color:#fbbf24; width:16px; height:16px; }
  .radio-item label { cursor:pointer; font-size:14px; }
  .radio-item.selected { border-color:#fbbf24; background:rgba(251,191,36,0.05); }
  .kendala-box { background:rgba(239,68,68,0.1); border:1px solid #ef4444; border-radius:10px; padding:14px; margin-top:10px; display:none; }
  .submit-bar { position:fixed; bottom:60px; left:0; right:0; padding:12px 16px; background:rgba(15,23,42,0.97); border-top:1px solid #334155; z-index:200; }
  .btn-submit { width:100%; padding:14px; border-radius:12px; border:none; font-weight:700; font-size:16px; background:#f59e0b; color:#0f172a; }
  .btn-submit:disabled { background:#334155; color:#64748b; }
  .gps-bar { display:flex; align-items:center; gap:10px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  #kameraModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:999; flex-direction:column; align-items:center; justify-content:center; }
  #kameraModal video { width:100%; max-width:480px; border-radius:12px; }
  #kameraModal .btn-capture { margin-top:16px; background:#fbbf24; color:#0f172a; border:none; border-radius:10px; padding:12px 32px; font-weight:700; font-size:16px; }
  #kameraModal .btn-tutup { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.2); color:#fff; border:none; border-radius:8px; padding:8px 14px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('absensi.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Absen Siang + Laporan</div>
    <div class="topbar-sub">Setelah istirahat · {{ now()->format('H:i') }}</div>
  </div>
  <div style="margin-left:auto; font-size:13px; color:#fbbf24;" id="jamLive">--:--</div>
</div>

<div class="content">

  <div id="alertBox" style="display:none;"></div>

  {{-- GPS --}}
  <div class="card-dark">
    <div class="section-label">📍 Lokasi</div>
    <div class="gps-bar">
      <span id="gpsIcon" style="font-size:22px;">📍</span>
      <div style="flex:1;">
        <div style="font-size:13px; font-weight:600;" id="gpsStatus">Mendeteksi lokasi...</div>
        <div style="font-size:11px; color:#64748b;" id="gpsDetail"></div>
      </div>
      <button onclick="refreshGPS()" style="background:#334155; border:none; color:#94a3b8; border-radius:8px; padding:6px 10px; font-size:12px;">Refresh</button>
    </div>
  </div>

  {{-- Foto Progress --}}
  <div class="card-dark">
    <div class="section-label">📸 Foto Progress (min. 1, maks. 3)</div>
    <div class="foto-grid">
      <div class="foto-slot" id="slot0" onclick="bukaKamera(0)">
        <span class="plus">+</span>
        <button class="hapus" onclick="hapusFoto(event,0)">✕</button>
      </div>
      <div class="foto-slot" id="slot1" onclick="bukaKamera(1)">
        <span class="plus">+</span>
        <button class="hapus" onclick="hapusFoto(event,1)">✕</button>
      </div>
      <div class="foto-slot" id="slot2" onclick="bukaKamera(2)">
        <span class="plus">+</span>
        <button class="hapus" onclick="hapusFoto(event,2)">✕</button>
      </div>
    </div>
    <div style="color:#475569; font-size:11px;">Foto kondisi pekerjaan di lapangan</div>
  </div>

  {{-- Status Pekerjaan --}}
  <div class="card-dark">
    <div class="section-label">📋 Status Pekerjaan</div>
    <div class="radio-group" id="radioStatus">
      @foreach($statusPekerjaan as $key => $label)
      <div class="radio-item" onclick="pilihStatus('{{ $key }}', this)">
        <input type="radio" name="status_pekerjaan" value="{{ $key }}" id="sp_{{ $key }}">
        <label for="sp_{{ $key }}">{{ $label }}</label>
      </div>
      @endforeach
    </div>

    {{-- Kendala (muncul jika bukan normal) --}}
    <div class="kendala-box" id="kendalaBox">
      <div style="color:#fca5a5; font-weight:600; margin-bottom:10px;">⚠️ Detail Kendala</div>
      <div class="mb-3">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Jenis Kendala</label>
        <select id="jenisKendala" class="form-control-dark">
          <option value="">-- Pilih jenis kendala --</option>
          @foreach($jenisKendala as $key => $label)
          <option value="{{ $key }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Keterangan Kendala</label>
        <textarea id="deskripsiKendala" class="form-control-dark" rows="3" placeholder="Jelaskan kendala yang terjadi..."></textarea>
      </div>
    </div>
  </div>

</div>

{{-- Submit --}}
<div class="submit-bar">
  <button class="btn-submit" id="btnSubmit" disabled onclick="submitAbsen()">
    🟡 Absen Siang Sekarang
  </button>
</div>

{{-- Modal Kamera --}}
<div id="kameraModal">
  <button class="btn-tutup" onclick="tutupKamera()">✕ Tutup</button>
  <video id="kameraVideo" autoplay playsinline muted></video>
  <canvas id="kameraCanvas" style="display:none;"></canvas>
  <button class="btn-capture" onclick="jepret()">📷 Jepret</button>
</div>

<script>
let fotoSlots = [null, null, null];
let slotAktif = 0;
let lat = null, lng = null;
let gpsValid = false;
let statusDipilih = null;
let kameraStream = null;

// Jam live
setInterval(() => {
  const now = new Date();
  document.getElementById('jamLive').textContent =
    [now.getHours(), now.getMinutes()].map(n=>String(n).padStart(2,'0')).join(':');
}, 1000);

// GPS
function refreshGPS() {
  gpsValid = false; cekSubmit();
  navigator.geolocation.getCurrentPosition(pos => {
    lat = pos.coords.latitude; lng = pos.coords.longitude;
    fetch('/absensi/cek-gps', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({lat,lng})
    }).then(r=>r.json()).then(data => {
      gpsValid = data.valid;
      document.getElementById('gpsIcon').textContent = data.valid ? '✅' : '❌';
      document.getElementById('gpsStatus').innerHTML = data.valid
        ? '<span style="color:#10b981">Lokasi valid ✓</span>'
        : '<span style="color:#ef4444">Di luar radius!</span>';
      document.getElementById('gpsDetail').textContent = data.jarak + ' dari kantor';
      cekSubmit();
    });
  }, () => {
    document.getElementById('gpsStatus').textContent = 'GPS gagal — coba refresh';
  }, {enableHighAccuracy:true, timeout:10000});
}
refreshGPS();

// Kamera
function bukaKamera(slot) {
  slotAktif = slot;
  document.getElementById('kameraModal').style.display = 'flex';
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
    .then(stream => {
      kameraStream = stream;
      document.getElementById('kameraVideo').srcObject = stream;
    });
}

function tutupKamera() {
  if (kameraStream) kameraStream.getTracks().forEach(t=>t.stop());
  document.getElementById('kameraModal').style.display = 'none';
}

function jepret() {
  const video = document.getElementById('kameraVideo');
  const canvas = document.getElementById('kameraCanvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);

  // Timestamp
  ctx.fillStyle = 'rgba(0,0,0,0.6)';
  ctx.fillRect(0, canvas.height-36, canvas.width, 36);
  ctx.fillStyle = '#fff';
  ctx.font = '13px monospace';
  ctx.fillText(new Date().toLocaleString('id-ID'), 8, canvas.height-12);

  const data = canvas.toDataURL('image/jpeg', 0.8);
  fotoSlots[slotAktif] = data;

  // Tampil di slot
  const slot = document.getElementById('slot' + slotAktif);
  slot.innerHTML = `<img src="${data}"><button class="hapus" onclick="hapusFoto(event,${slotAktif})">✕</button>`;
  slot.style.border = '2px solid #10b981';

  tutupKamera();
  cekSubmit();
}

function hapusFoto(e, slot) {
  e.stopPropagation();
  fotoSlots[slot] = null;
  const el = document.getElementById('slot' + slot);
  el.innerHTML = '<span class="plus">+</span><button class="hapus" onclick="hapusFoto(event,' + slot + ')">✕</button>';
  el.style.border = '2px dashed #334155';
  cekSubmit();
}

// Status pekerjaan
function pilihStatus(key, el) {
  document.querySelectorAll('.radio-item').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;
  statusDipilih = key;
  document.getElementById('kendalaBox').style.display = key !== 'normal' ? 'block' : 'none';
  cekSubmit();
}

function cekSubmit() {
  const adaFoto = fotoSlots.some(f => f !== null);
  const adaKendala = statusDipilih && statusDipilih !== 'normal';
  const kendalaLengkap = !adaKendala || (
    document.getElementById('jenisKendala').value &&
    document.getElementById('deskripsiKendala').value.trim()
  );
  const btn = document.getElementById('btnSubmit');
  btn.disabled = !(gpsValid && adaFoto && statusDipilih && kendalaLengkap);
}

document.getElementById('jenisKendala')?.addEventListener('change', cekSubmit);
document.getElementById('deskripsiKendala')?.addEventListener('input', cekSubmit);

function submitAbsen() {
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.textContent = 'Menyimpan...';

  const adaKendala = statusDipilih !== 'normal';
  const body = new FormData();
  body.append('_token', '{{ csrf_token() }}');
  body.append('lat', lat);
  body.append('lng', lng);
  body.append('status_pekerjaan', statusDipilih);
  body.append('ada_kendala', adaKendala ? 1 : 0);
  if (fotoSlots[0]) body.append('foto_1', fotoSlots[0]);
  if (fotoSlots[1]) body.append('foto_2', fotoSlots[1]);
  if (fotoSlots[2]) body.append('foto_3', fotoSlots[2]);
  if (adaKendala) {
    body.append('jenis_kendala', document.getElementById('jenisKendala').value);
    body.append('deskripsi_kendala', document.getElementById('deskripsiKendala').value);
  }

  fetch('{{ route("absensi.siang") }}', {
    method:'POST', headers:{'Accept':'application/json'}, body
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => window.location.href = data.redirect, 1500);
    } else {
      showAlert('error', data.message);
      btn.disabled = false;
      btn.textContent = '🟡 Absen Siang Sekarang';
    }
  });
}

function showAlert(type, msg) {
  const box = document.getElementById('alertBox');
  box.className = 'alert-box alert-' + type;
  box.textContent = msg;
  box.style.display = 'block';
  window.scrollTo(0,0);
}
</script>
</body>
</html>