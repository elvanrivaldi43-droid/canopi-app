<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\RegistrasiKaryawanController;
use App\Http\Controllers\IzinAbsenController;
use App\Http\Controllers\PenggajianController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\KasbonKaryawanController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\TugasHarianController;
use App\Http\Controllers\LogBensinController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\MasterMaterialController;
use App\Http\Controllers\ProjectController;


// Halaman utama → redirect ke login
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes dari Breeze
require __DIR__.'/auth.php';

// ─── REGISTRASI KARYAWAN (Publik, tanpa login) ─────────────
Route::get('/registrasi-karyawan/{token}', [RegistrasiKaryawanController::class, 'show'])->name('registrasi.show');
Route::post('/registrasi-karyawan/{token}', [RegistrasiKaryawanController::class, 'simpan'])->name('registrasi.simpan');

// Redirect setelah login sesuai level
Route::middleware('auth')->get('/dashboard', function () {
    $level = auth()->user()->level;
    return match($level) {
        1 => redirect('/owner/dashboard'),
        2 => redirect('/admin/dashboard'),
        3 => redirect('/supervisor/dashboard'),
        4 => redirect('/marketing/dashboard'),
        5 => redirect('/teknisi/dashboard'),
        6 => redirect('/driver/dashboard'),
        7 => redirect('/toko/dashboard'),
        default => redirect('/teknisi/dashboard'),
    };
})->name('dashboard');

// ─── OWNER (Level 1) ───────────────────────────────────────
Route::middleware(['auth', 'level:1'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'owner'])->name('dashboard');
});

// ─── ADMIN OPERASIONAL (Level 1,2) ─────────────────────────
Route::middleware(['auth', 'level:1,2'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
});

// ─── SUPERVISOR LAPANGAN (Level 1,2,3) ─────────────────────
Route::middleware(['auth', 'level:1,2,3'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'supervisor'])->name('dashboard');
});

// ─── MARKETING (Level 1,2,4) ───────────────────────────────
Route::middleware(['auth', 'level:1,2,4'])->prefix('marketing')->name('marketing.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'marketing'])->name('dashboard');
});

// ─── TEKNISI (Level 1,2,3,5) ───────────────────────────────
Route::middleware(['auth', 'level:1,2,3,5'])->prefix('teknisi')->name('teknisi.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'teknisi'])->name('dashboard');
});

// ─── DRIVER (Level 1,2,3,6) ────────────────────────────────
Route::middleware(['auth', 'level:1,2,3,6'])->prefix('driver')->name('driver.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'driver'])->name('dashboard');
});

// ─── ADMIN TOKO BESI (Level 1,7) ───────────────────────────
Route::middleware(['auth', 'level:1,7'])->prefix('toko')->name('toko.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'toko'])->name('dashboard');
});

// ─── KARYAWAN (Level 1,2) ──────────────────────────────────
Route::middleware(['auth', 'level:1,2'])->prefix('karyawan')->name('karyawan.')->group(function () {
    Route::get('/', [KaryawanController::class, 'index'])->name('index');
    Route::get('/tambah', [KaryawanController::class, 'create'])->name('create');
    Route::post('/', [KaryawanController::class, 'store'])->name('store');
    Route::get('/{karyawan}', [KaryawanController::class, 'show'])->name('show');
    Route::get('/{karyawan}/edit', [KaryawanController::class, 'edit'])->name('edit');
    Route::put('/{karyawan}', [KaryawanController::class, 'update'])->name('update');
    Route::post('/{karyawan}/reset-password', [KaryawanController::class, 'resetPassword'])->name('reset-password');
    Route::post('/{karyawan}/nonaktifkan', [KaryawanController::class, 'nonaktifkan'])->name('nonaktifkan');
    Route::post('/{karyawan}/aktifkan', [KaryawanController::class, 'aktifkan'])->name('aktifkan');
    Route::post('/{karyawan}/kirim-ulang', [KaryawanController::class, 'kirimUlang'])->name('kirim-ulang');
});

// ─── ABSENSI ───────────────────────────────────────────────
Route::middleware('auth')->prefix('absensi')->name('absensi.')->group(function () {
    Route::get('/',                         [AbsensiController::class, 'index'])->name('index');
    Route::get('/masuk',                    [AbsensiController::class, 'formMasuk'])->name('form-masuk');
    Route::post('/masuk',                   [AbsensiController::class, 'absenMasuk'])->name('masuk');
    Route::get('/siang',                    [AbsensiController::class, 'formSiang'])->name('form-siang');
    Route::post('/siang',                   [AbsensiController::class, 'absenSiang'])->name('siang');
    Route::get('/pulang',                   [AbsensiController::class, 'formPulang'])->name('form-pulang');
    Route::post('/pulang',                  [AbsensiController::class, 'absenPulang'])->name('pulang');
    Route::post('/cek-gps',                 [AbsensiController::class, 'cekGps'])->name('cek-gps');
    Route::get('/rekap',                    [AbsensiController::class, 'rekap'])->name('rekap');
    Route::post('/{id}/koreksi',            [AbsensiController::class, 'koreksi'])->name('koreksi');
    Route::post('/koreksi-baru/{userId}',   [AbsensiController::class, 'koreksiManual'])->name('koreksi-manual');
    Route::post('/validasi-kode',           [AbsensiController::class, 'validasiKode'])->name('validasi-kode');
    Route::get('/rekap-bulanan',            [AbsensiController::class, 'rekapBulanan'])->name('rekap-bulanan');
});

// ─── IZIN KARYAWAN ─────────────────────────────────────────
Route::middleware('auth')->prefix('izin')->name('izin.')->group(function () {
    Route::get('/',                     [IzinAbsenController::class, 'index'])->name('index');
    Route::get('/ajukan',               [IzinAbsenController::class, 'create'])->name('create');
    Route::post('/',                    [IzinAbsenController::class, 'store'])->name('store');
    Route::get('/approval',             [IzinAbsenController::class, 'approval'])->name('approval');
    Route::patch('/{izin}/approve',     [IzinAbsenController::class, 'approve'])->name('approve');
    Route::patch('/{izin}/reject',      [IzinAbsenController::class, 'reject'])->name('reject');
    Route::post('/dinas-luar',          [IzinAbsenController::class, 'dinasLuar'])->name('dinas-luar');
});

// ─── PENGGAJIAN ─────────────────────────────────────────────
Route::middleware('auth')->prefix('penggajian')->name('penggajian.')->group(function () {
    Route::get('/',                             [PenggajianController::class, 'index'])->name('index');
    Route::post('/generate',                    [PenggajianController::class, 'generate'])->name('generate');
    Route::post('/generate-semua',              [PenggajianController::class, 'generateSemua'])->name('generate-semua');
    Route::get('/slip/{slip}',                  [PenggajianController::class, 'show'])->name('slip');
    Route::post('/slip/{slip}/konfirmasi',      [PenggajianController::class, 'konfirmasi'])->name('konfirmasi');
    Route::post('/slip/{slip}/bayar',           [PenggajianController::class, 'bayar'])->name('bayar');
    Route::post('/bayar-semua',                 [PenggajianController::class, 'bayarSemua'])->name('bayar-semua');
    Route::get('/slip-saya',                    [PenggajianController::class, 'slipSaya'])->name('slip-saya');
    Route::get('/kasbon',                       [PenggajianController::class, 'kasbon'])->name('kasbon');
    Route::post('/kasbon',                      [PenggajianController::class, 'kasbonStore'])->name('kasbon.store');
    Route::post('/kasbon/{kasbon}/tunda',       [PenggajianController::class, 'kasbonTunda'])->name('kasbon.tunda');
    Route::post('/insidental',                  [PenggajianController::class, 'insidentalStore'])->name('insidental.store');
    Route::post('/tabungan-lebaran',            [PenggajianController::class, 'setTabunganLebaran'])->name('tabungan-lebaran');
    Route::post('/kasbon/{kasbon}/approve',     [PenggajianController::class, 'kasbonApprove'])->name('kasbon.approve');
    Route::post('/kasbon/{kasbon}/tolak',       [PenggajianController::class, 'kasbonTolak'])->name('kasbon.tolak');
});

// ─── PIPELINE SURVEY (Level 1,2,3,4) ─────────────────────────
Route::middleware(['auth', 'level:1,2,3,4'])->prefix('pipeline')->name('pipeline.')->group(function () {
    Route::get('/',                         [PipelineController::class, 'index'])->name('index');
    Route::get('/list',                     [PipelineController::class, 'listView'])->name('list');
    Route::get('/tambah',                   [PipelineController::class, 'create'])->name('create');
    Route::post('/',                        [PipelineController::class, 'store'])->name('store');
    Route::get('/{pipeline}',               [PipelineController::class, 'show'])->name('show');
    Route::get('/{pipeline}/edit',          [PipelineController::class, 'edit'])->name('edit');
    Route::put('/{pipeline}',               [PipelineController::class, 'update'])->name('update');
    Route::patch('/{pipeline}/status',      [PipelineController::class, 'updateStatus'])->name('update-status');
    Route::post('/{pipeline}/followup',     [PipelineController::class, 'storeFollowup'])->name('followup');
});


// RAB Builder
Route::prefix('rab')->name('rab.')->middleware(['auth'])->group(function () {

    // Daftar RAB
    Route::get('/', [App\Http\Controllers\RabController::class, 'index'])->name('index');

    // Wizard buat RAB baru
    Route::get('/buat', [App\Http\Controllers\RabController::class, 'create'])->name('create');
    Route::post('/simpan', [App\Http\Controllers\RabController::class, 'store'])->name('store');

    // Detail RAB
    Route::get('/{id}', [App\Http\Controllers\RabController::class, 'show'])->name('show');

    // Deal + TTD digital
    Route::post('/{id}/deal', [App\Http\Controllers\RabController::class, 'deal'])->name('deal');

    // API endpoints (dipanggil oleh wizard JS)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/katalog',   [App\Http\Controllers\RabController::class, 'apiKatalog'])->name('katalog');
        Route::get('/paket',     [App\Http\Controllers\RabController::class, 'apiPaketKonstruksi'])->name('paket');
        Route::get('/atap',      [App\Http\Controllers\RabController::class, 'apiAtap'])->name('atap');
        Route::get('/addon',     [App\Http\Controllers\RabController::class, 'apiAddon'])->name('addon');
        Route::get('/kondisi',   [App\Http\Controllers\RabController::class, 'apiKondisi'])->name('kondisi');
        Route::get('/material-struktur', [App\Http\Controllers\RabController::class, 'apiMaterialStruktur'])->name('material.struktur');
        Route::post('/hitung',   [App\Http\Controllers\RabController::class, 'apiHitung'])->name('hitung');
    });

    // Approval request diskon (owner only)
    Route::get('/approval/daftar',     [App\Http\Controllers\RabController::class, 'approvalIndex'])->name('approval.index');
    Route::post('/approval/{id}/proses',[App\Http\Controllers\RabController::class, 'approvalProses'])->name('approval.proses');

    // Switch mode harga (owner only)
    Route::post('/switch-mode', [App\Http\Controllers\RabController::class, 'switchMode'])->name('switch-mode');

    // Master setting (owner only)
    Route::get('/master/setting',           [App\Http\Controllers\RabController::class, 'masterIndex'])->name('master.index');
    Route::post('/master/margin',           [App\Http\Controllers\RabController::class, 'masterUpdateMargin'])->name('master.margin');
    Route::post('/master/addon/{id}',       [App\Http\Controllers\RabController::class, 'masterUpdateAddon'])->name('master.addon');
    Route::post('/master/katalog/tambah',   [App\Http\Controllers\RabController::class, 'masterTambahKatalog'])->name('master.katalog.tambah');
    Route::post('/master/margin-bulk',  [App\Http\Controllers\RabController::class, 'masterMarginBulk'])->name('master.margin.bulk');
    Route::post('/master/paket-bulk',   [App\Http\Controllers\RabController::class, 'masterPaketBulk'])->name('master.paket.bulk');
    Route::post('/master/addon-bulk',   [App\Http\Controllers\RabController::class, 'masterAddonBulk'])->name('master.addon.bulk');
    Route::post('/master/kondisi-bulk', [App\Http\Controllers\RabController::class, 'masterKondisiBulk'])->name('master.kondisi.bulk');
    Route::post('/master/katalog/hapus',[App\Http\Controllers\RabController::class, 'masterKatalogHapus'])->name('master.katalog.hapus');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/master-atap',          [\App\Http\Controllers\RabController::class, 'kelolaMaterial']);
    Route::post('/master-atap/simpan',  [\App\Http\Controllers\RabController::class, 'atapSimpan']);
    Route::post('/master-atap/nonaktif',[\App\Http\Controllers\RabController::class, 'atapNonaktif']);
});


// ─── TUGAS HARIAN (Semua level) ────────────────────────────
Route::middleware('auth')->prefix('tugas')->name('tugas.')->group(function () {
    Route::get('/',             [TugasHarianController::class, 'index'])->name('index');
    Route::get('/buat',         [TugasHarianController::class, 'create'])->name('create');
    Route::post('/buat',        [TugasHarianController::class, 'store'])->name('store');
    Route::get('/{id}',         [TugasHarianController::class, 'show'])->name('show');
    Route::get('/{id}/edit',    [TugasHarianController::class, 'edit'])->name('edit');
    Route::put('/{id}',         [TugasHarianController::class, 'update'])->name('update');
    Route::delete('/{id}',      [TugasHarianController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/status', [TugasHarianController::class, 'updateStatus'])->name('updateStatus');
});

// ─── LOG BENSIN ─────────────────────────────────────────────
// Rekap semua: Owner + Supervisor/Mandor (level 1,3)
Route::middleware(['auth', 'level:1,3'])->prefix('bensin')->name('bensin.')->group(function () {
    Route::get('/rekap', [LogBensinController::class, 'index'])->name('index');
});

// ─── MODE LUAR KOTA (Level 1,2,3) ──────────────────────────
Route::middleware(['auth', 'level:1,2,3'])->prefix('luar-kota')->name('luar-kota.')->group(function () {
    Route::get('/',             [App\Http\Controllers\LuarKotaController::class, 'index'])->name('index');
    Route::get('/tambah',       [App\Http\Controllers\LuarKotaController::class, 'create'])->name('create');
    Route::post('/',            [App\Http\Controllers\LuarKotaController::class, 'store'])->name('store');
    Route::get('/{id}/edit',    [App\Http\Controllers\LuarKotaController::class, 'edit'])->name('edit');
    Route::put('/{id}',         [App\Http\Controllers\LuarKotaController::class, 'update'])->name('update');
    Route::post('/{id}/selesai',[App\Http\Controllers\LuarKotaController::class, 'selesai'])->name('selesai');
    Route::post('/{id}/batal',  [App\Http\Controllers\LuarKotaController::class, 'batalkan'])->name('batalkan');
});


// Master kendaraan: Owner saja (level 1)
Route::middleware(['auth', 'level:1'])->prefix('bensin')->name('bensin.')->group(function () {
    Route::get('/kendaraan',                [LogBensinController::class, 'kendaraan'])->name('kendaraan');
    Route::post('/kendaraan',               [LogBensinController::class, 'kendaraanStore'])->name('kendaraan.store');
    Route::put('/kendaraan/{id}',           [LogBensinController::class, 'kendaraanUpdate'])->name('kendaraan.update');
    Route::post('/kendaraan/{id}/toggle',   [LogBensinController::class, 'kendaraanToggle'])->name('kendaraan.toggle');
});

// Input & riwayat: Driver saja (level 6)
Route::middleware(['auth', 'level:6'])->prefix('bensin')->name('bensin.')->group(function () {
    Route::get('/catat',            [LogBensinController::class, 'create'])->name('create');
    Route::post('/catat',           [LogBensinController::class, 'store'])->name('store');
    Route::get('/pulang/{id}',      [LogBensinController::class, 'pulang'])->name('pulang');
    Route::post('/pulang/{id}',     [LogBensinController::class, 'pulangStore'])->name('pulang.store');
    Route::get('/riwayat',          [LogBensinController::class, 'riwayat'])->name('riwayat');
});

// ─── KASBON KARYAWAN ────────────────────────────────────────
Route::middleware('auth')->prefix('kasbon-saya')->name('kasbon.karyawan.')->group(function () {
    Route::get('/',                 [KasbonKaryawanController::class, 'index'])->name('index');
    Route::post('/',                [KasbonKaryawanController::class, 'store'])->name('store');
    Route::get('/{kasbon}/surat',   [KasbonKaryawanController::class, 'surat'])->name('surat');
});

// ─── PROFIL KARYAWAN ────────────────────────────────────────
Route::middleware('auth')->prefix('profil')->name('profil.')->group(function () {
    Route::get('/', [ProfilController::class, 'index'])->name('index');
    Route::put('/', [ProfilController::class, 'update'])->name('update');
});

// ─── PROFILE (Breeze default) ───────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile',      [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',    [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',   [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

// KPI — Owner lihat semua
Route::middleware(['auth', 'level:1'])->group(function () {
    Route::get('/kpi', [KpiController::class, 'index'])->name('kpi.index');
    Route::post('/kpi/hitung-manual', [KpiController::class, 'hitungManual'])->name('kpi.hitung');
    Route::post('/kpi/sp/konfirmasi/{id}', [KpiController::class, 'konfirmasiSp'])->name('kpi.sp.konfirmasi');
    Route::post('/kpi/sp/buat', [KpiController::class, 'buatSpManual'])->name('kpi.sp.buat');
    Route::post('/kpi/komplain/simpan', [KpiController::class, 'simpanKomplain'])->name('kpi.komplain.simpan');
    Route::get('/kpi/rapor', [KpiController::class, 'raporIndex'])->name('kpi.rapor.index');
    Route::post('/kpi/ujian/toggle', [KpiController::class, 'toggleUjian'])->name('kpi.ujian.toggle');

    // Bank soal
    Route::get('/kpi/soal', [KpiController::class, 'soalIndex'])->name('kpi.soal.index');
    Route::get('/kpi/soal/tambah', [KpiController::class, 'soalCreate'])->name('kpi.soal.create');
    Route::post('/kpi/soal/simpan', [KpiController::class, 'soalStore'])->name('kpi.soal.store');
    Route::get('/kpi/soal/{id}/edit', [KpiController::class, 'soalEdit'])->name('kpi.soal.edit');
    Route::post('/kpi/soal/{id}/update', [KpiController::class, 'soalUpdate'])->name('kpi.soal.update');
    Route::post('/kpi/soal/{id}/hapus', [KpiController::class, 'soalHapus'])->name('kpi.soal.hapus');
});

// Detail KPI — Owner lihat semua, karyawan lihat sendiri
Route::middleware(['auth', 'level:1,2,3,4,5,6'])->group(function () {
    Route::get('/kpi/detail/{userId?}', [KpiController::class, 'detail'])->name('kpi.detail');
});

// Ujian Online — Karyawan level 2-6
Route::middleware(['auth', 'level:2,3,4,5,6'])->group(function () {
    Route::get('/kpi/ujian', [KpiController::class, 'ujianIndex'])->name('kpi.ujian.index');
    Route::post('/kpi/ujian/mulai', [KpiController::class, 'ujianMulai'])->name('kpi.ujian.mulai');
    Route::get('/kpi/ujian/kerjakan', [KpiController::class, 'ujianKerjakan'])->name('kpi.ujian.kerjakan');
    Route::post('/kpi/ujian/jawab', [KpiController::class, 'ujianSimpanJawaban'])->name('kpi.ujian.jawab');
    Route::post('/kpi/ujian/submit', [KpiController::class, 'ujianSubmit'])->name('kpi.ujian.submit');
    Route::get('/kpi/ujian/hasil', [KpiController::class, 'ujianHasil'])->name('kpi.ujian.hasil');
});

// ================================================================
// MASTER MATERIAL (Owner + Admin)
// ================================================================
Route::middleware(['auth', 'level:1,2'])->group(function () {
    Route::get('/master-material', [MasterMaterialController::class, 'index'])->name('master-material.index');
    Route::get('/master-material/create', [MasterMaterialController::class, 'create'])->name('master-material.create');
    Route::post('/master-material', [MasterMaterialController::class, 'store'])->name('master-material.store');
    Route::get('/master-material/{masterMaterial}/edit', [MasterMaterialController::class, 'edit'])->name('master-material.edit');
    Route::put('/master-material/{masterMaterial}', [MasterMaterialController::class, 'update'])->name('master-material.update');
    Route::patch('/master-material/{masterMaterial}/toggle', [MasterMaterialController::class, 'toggleAktif'])->name('master-material.toggle');
    // API untuk autocomplete di RAB builder
    Route::get('/api/material/search', [MasterMaterialController::class, 'search'])->name('api.material.search');
});

// ================================================================
// PROJECT MANAGEMENT
// ================================================================
Route::middleware(['auth', 'level:1,2,3'])->group(function () {

    // List & buat project
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    // Detail project
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    // Update status
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');

    // Tim (SPV assign, owner approve kondisi khusus)
    Route::post('/projects/{project}/tim', [ProjectController::class, 'storeTim'])->name('projects.tim.store');
    Route::delete('/project-tim/{tim}', [ProjectController::class, 'destroyTim'])->name('projects.tim.destroy');

    // Material aktual (Admin input)
    Route::post('/projects/{project}/material', [ProjectController::class, 'storeMaterial'])->name('projects.material.store');
    Route::delete('/project-material/{material}', [ProjectController::class, 'destroyMaterial'])->name('projects.material.destroy');

    // Pembayaran customer
    Route::post('/projects/{project}/pembayaran', [ProjectController::class, 'storePembayaran'])->name('projects.pembayaran.store');
});

// Owner-only actions
Route::middleware(['auth', 'level:1'])->group(function () {
    Route::post('/projects/{project}/approve-kondisi', [ProjectController::class, 'approveKondisi'])->name('projects.approve-kondisi');
    Route::post('/project-material/{material}/approve', [ProjectController::class, 'approveMaterial'])->name('projects.material.approve');
    Route::post('/pembayaran-project/{pembayaran}/konfirmasi', [ProjectController::class, 'konfirmasiPembayaran'])->name('projects.pembayaran.konfirmasi');
});

// ================================================================
// PRODUKTIVITAS (owner)
// ================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/produktivitas',        [\App\Http\Controllers\ProduktivitasController::class, 'index']);
    Route::post('/produktivitas/simpan',[\App\Http\Controllers\ProduktivitasController::class, 'simpan']);
    Route::get('/addon', [\App\Http\Controllers\AddonController::class, 'index']);
    Route::post('/addon/simpan', [\App\Http\Controllers\AddonController::class, 'simpan']);
});

// ================================================================
// KALKULATOR POTONG BESI (single blok — referensi)
// ================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/cutting-test',        [\App\Http\Controllers\CuttingController::class, 'index']);
    Route::post('/cutting-test/hitung',[\App\Http\Controllers\CuttingController::class, 'hitung']);
    Route::post('/cutting-test/cetak', [\App\Http\Controllers\CuttingController::class, 'cetak']);
});

// ================================================================
// RAB MULTI-BLOK (Tahap 1)
// ================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/rab-blok',         [\App\Http\Controllers\CuttingController::class, 'projectIndex']);
    Route::post('/rab-blok/hitung', [\App\Http\Controllers\CuttingController::class, 'hitungProject']);
});

// ================================================================
// RAB MULTI-OPSI (Tahap 2) — halaman isi & banding 3 opsi
// Hitung numpang endpoint /rab-blok/hitung yang sudah ada.
// ================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/rab-opsi', [\App\Http\Controllers\RabOpsiController::class, 'index']);
    Route::post('/rab-opsi/simpan-estimasi', [\App\Http\Controllers\RabOpsiController::class, 'simpanEstimasi']);
    Route::post('/rab-opsi/simpan-final', [\App\Http\Controllers\RabOpsiController::class, 'simpanFinal']);
    Route::get('/lokasi/{id}', [\App\Http\Controllers\LokasiController::class, 'index']);
    Route::post('/lokasi/{id}', [\App\Http\Controllers\LokasiController::class, 'simpan']);
    Route::get('/rab-setting', [\App\Http\Controllers\SettingRabController::class, 'index']);
    Route::post('/rab-setting', [\App\Http\Controllers\SettingRabController::class, 'simpan']);
    Route::post('/rab-approval', [\App\Http\Controllers\ApprovalController::class, 'store']);
    Route::get('/rab-approval', [\App\Http\Controllers\ApprovalController::class, 'index']);
    Route::post('/rab-approval/{id}/proses', [\App\Http\Controllers\ApprovalController::class, 'proses']);
    Route::post('/rab-opsi/autosave', [\App\Http\Controllers\RabOpsiController::class, 'autosave']);
    Route::post('/rab-opsi/simpan-penawaran', [\App\Http\Controllers\RabOpsiController::class, 'simpanPenawaran']);
    Route::get('/penawaran/{id}', [\App\Http\Controllers\PenawaranController::class, 'show']);
    Route::post('/penawaran/{id}/deal', [\App\Http\Controllers\PenawaranController::class, 'deal']);
});