{{-- FILE: resources/views/registrasi/form.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi Karyawan — Pusat Kanopi BSD</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; }
  .card-section { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  .section-title { color: #fbbf24; font-weight: 600; font-size: 15px; border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 16px; }
  .form-label { color: #94a3b8; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
  .form-control, .form-select {
    background: #0f172a !important;
    border: 1px solid #475569 !important;
    color: #f1f5f9 !important;
    border-radius: 8px;
    padding: 10px 14px;
  }
  .form-control:focus, .form-select:focus {
    border-color: #fbbf24 !important;
    box-shadow: 0 0 0 2px rgba(251,191,36,0.2) !important;
    background: #0f172a !important;
    color: #f1f5f9 !important;
  }
  .form-control::placeholder { color: #475569 !important; }
  .form-control:disabled { opacity: 0.5; cursor: not-allowed; }
  .form-select option { background: #1e293b; color: #f1f5f9; }
  .btn-submit {
    background: #fbbf24;
    color: #0f172a;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    padding: 14px;
    font-size: 16px;
    width: 100%;
    transition: background 0.2s;
  }
  .btn-submit:hover { background: #f59e0b; color: #0f172a; }
  .header-badge { background: #1e293b; border: 1px solid #334155; border-radius: 999px; padding: 6px 16px; display: inline-block; color: #94a3b8; font-size: 13px; }
  .logo-box { width: 64px; height: 64px; background: #fbbf24; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px; }
  .alert-error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; border-radius: 10px; padding: 16px; margin-bottom: 20px; color: #fca5a5; }
  .text-muted-sm { color: #475569; font-size: 12px; margin-top: 4px; }
</style>
</head>
<body class="py-5 px-3">
<div class="container" style="max-width: 680px;">

  {{-- Header --}}
  <div class="text-center mb-4">
    <div class="logo-box">🏠</div>
    <h1 style="color:#fbbf24; font-size:22px; font-weight:700;">Pusat Kanopi BSD</h1>
    <p style="color:#64748b; font-size:13px; margin-top:4px;">Lengkapi data diri untuk mengaktifkan akunmu</p>
    <div class="header-badge mt-3">
      {{ $levels[$karyawan->level] ?? '' }} &nbsp;·&nbsp; {{ $karyawan->jabatan }}
    </div>
  </div>

  {{-- Error --}}
  @if($errors->any())
  <div class="alert-error">
    <strong>⚠️ Ada yang perlu diperbaiki:</strong>
    <ul style="margin:8px 0 0 0; padding-left:18px;">
      @foreach($errors->all() as $e)<li style="font-size:13px;">{{ $e }}</li>@endforeach
    </ul>
  </div>
  @endif

  <form method="POST" action="/registrasi-karyawan/{{ $token }}" enctype="multipart/form-data">
    @csrf

    {{-- 1. DATA AKUN --}}
    <div class="card-section">
      <div class="section-title">🔐 Data Akun</div>
      <div class="mb-3">
        <label class="form-label">Email <span style="color:#475569">(tidak bisa diubah)</span></label>
        <input type="email" class="form-control" value="{{ $karyawan->email }}" disabled>
      </div>
      <div class="mb-3">
        <label class="form-label">Password Baru <span style="color:#ef4444">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter">
      </div>
      <div class="mb-0">
        <label class="form-label">Konfirmasi Password <span style="color:#ef4444">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password">
      </div>
    </div>

    {{-- 2. DATA DIRI --}}
    <div class="card-section">
      <div class="section-title">👤 Data Diri</div>
      <div class="mb-3">
        <label class="form-label">Nama Lengkap <span style="color:#ef4444">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Sesuai KTP">
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <label class="form-label">Tempat Lahir <span style="color:#ef4444">*</span></label>
          <input type="text" name="tempat_lahir" class="form-control" value="{{ old('tempat_lahir') }}" placeholder="Kota">
        </div>
        <div class="col-6">
          <label class="form-label">Tanggal Lahir <span style="color:#ef4444">*</span></label>
          <input type="date" name="tgl_lahir" class="form-control" value="{{ old('tgl_lahir') }}">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">No HP / WhatsApp <span style="color:#ef4444">*</span></label>
        <input type="text" name="no_hp" class="form-control" value="{{ old('no_hp') }}" placeholder="08xxxxxxxxxx">
      </div>
      <div class="mb-3">
        <label class="form-label">Alamat Lengkap <span style="color:#ef4444">*</span></label>
        <textarea name="alamat" class="form-control" rows="3" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota">{{ old('alamat') }}</textarea>
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <label class="form-label">Status Pernikahan <span style="color:#ef4444">*</span></label>
          <select name="status_nikah" class="form-select">
            <option value="">-- Pilih --</option>
            <option value="belum_menikah" {{ old('status_nikah')=='belum_menikah'?'selected':'' }}>Belum Menikah</option>
            <option value="menikah" {{ old('status_nikah')=='menikah'?'selected':'' }}>Menikah</option>
            <option value="cerai" {{ old('status_nikah')=='cerai'?'selected':'' }}>Cerai</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Jumlah Tanggungan <span style="color:#ef4444">*</span></label>
          <input type="number" name="jumlah_tanggungan" class="form-control" value="{{ old('jumlah_tanggungan',0) }}" min="0" max="20">
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <label class="form-label">Golongan Darah <span style="color:#ef4444">*</span></label>
          <select name="golongan_darah" class="form-select">
            <option value="">-- Pilih --</option>
            @foreach(['A','B','AB','O','A+','B+','AB+','O+','A-','B-','AB-','O-'] as $gd)
            <option value="{{ $gd }}" {{ old('golongan_darah')==$gd?'selected':'' }}>{{ $gd }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Ukuran Baju/Seragam <span style="color:#ef4444">*</span></label>
          <select name="ukuran_baju" class="form-select">
            <option value="">-- Pilih --</option>
            @foreach(['XS','S','M','L','XL','XXL','XXXL'] as $uk)
            <option value="{{ $uk }}" {{ old('ukuran_baju')==$uk?'selected':'' }}>{{ $uk }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mb-0">
        <label class="form-label">Foto Profil <span style="color:#ef4444">*</span></label>
        <input type="file" name="foto" accept="image/*" class="form-control">
        <div class="text-muted-sm">Format JPG/PNG, maksimal 3MB. Foto tampak wajah jelas.</div>
      </div>
    </div>

    {{-- 3. IDENTITAS --}}
    <div class="card-section">
      <div class="section-title">🪪 Identitas</div>
      <div class="mb-3">
        <label class="form-label">Nomor KTP <span style="color:#ef4444">*</span></label>
        <input type="text" name="no_ktp" class="form-control" value="{{ old('no_ktp') }}" placeholder="16 digit" maxlength="16">
      </div>
      <div class="mb-3">
        <label class="form-label">Nomor Kartu Keluarga (KK) <span style="color:#ef4444">*</span></label>
        <input type="text" name="no_kk" class="form-control" value="{{ old('no_kk') }}" placeholder="16 digit" maxlength="16">
      </div>
      <div class="mb-3">
        <label class="form-label">No BPJS Kesehatan <span style="color:#475569">(opsional)</span></label>
        <input type="text" name="no_bpjs_kesehatan" class="form-control" value="{{ old('no_bpjs_kesehatan') }}" placeholder="Kosongkan jika belum ada">
      </div>
      <div class="mb-0">
        <label class="form-label">No BPJS Ketenagakerjaan <span style="color:#475569">(opsional)</span></label>
        <input type="text" name="no_bpjs_ketenagakerjaan" class="form-control" value="{{ old('no_bpjs_ketenagakerjaan') }}" placeholder="Kosongkan jika belum ada">
      </div>
    </div>

    {{-- 4. KONTAK DARURAT --}}
    <div class="card-section">
      <div class="section-title">🆘 Kontak Darurat</div>
      <div class="mb-3">
        <label class="form-label">Nama Kontak Darurat <span style="color:#ef4444">*</span></label>
        <input type="text" name="darurat_nama" class="form-control" value="{{ old('darurat_nama') }}" placeholder="Nama lengkap">
      </div>
      <div class="row mb-0">
        <div class="col-6">
          <label class="form-label">No HP Kontak Darurat <span style="color:#ef4444">*</span></label>
          <input type="text" name="darurat_no_hp" class="form-control" value="{{ old('darurat_no_hp') }}" placeholder="08xxxxxxxxxx">
        </div>
        <div class="col-6">
          <label class="form-label">Hubungan <span style="color:#ef4444">*</span></label>
          <select name="darurat_hubungan" class="form-select">
            <option value="">-- Pilih --</option>
            @foreach(['Orang Tua','Pasangan','Kakak','Adik','Saudara','Teman'] as $hub)
            <option value="{{ $hub }}" {{ old('darurat_hubungan')==$hub?'selected':'' }}>{{ $hub }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    {{-- 5. REKENING --}}
    <div class="card-section">
      <div class="section-title">🏦 Rekening Bank (untuk pembayaran gaji)</div>
      <div class="mb-3">
        <label class="form-label">Nama Bank <span style="color:#ef4444">*</span></label>
        <select name="nama_bank" class="form-select">
          <option value="">-- Pilih Bank --</option>
          @foreach($banks as $bank)
          <option value="{{ $bank }}" {{ old('nama_bank')==$bank?'selected':'' }}>{{ $bank }}</option>
          @endforeach
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Nomor Rekening <span style="color:#ef4444">*</span></label>
        <input type="text" name="no_rekening" class="form-control" value="{{ old('no_rekening') }}" placeholder="Nomor rekening">
      </div>
      <div class="mb-0">
        <label class="form-label">Atas Nama <span style="color:#ef4444">*</span></label>
        <input type="text" name="atas_nama" class="form-control" value="{{ old('atas_nama') }}" placeholder="Sesuai buku tabungan">
      </div>
    </div>

    {{-- Submit --}}
    <button type="submit" class="btn-submit mb-3">✅ Simpan & Aktifkan Akun</button>
    <p class="text-center" style="color:#475569; font-size:12px;">Data kamu tersimpan aman dan hanya dapat diakses oleh manajemen Pusat Kanopi BSD.</p>

  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>