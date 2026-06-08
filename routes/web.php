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

// ─── KARYAWAN (Level 1 & 2) ────────────────────────────────
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
    Route::get('/',              [AbsensiController::class, 'index'])->name('index');
    Route::get('/masuk',         [AbsensiController::class, 'formMasuk'])->name('form-masuk');
    Route::post('/masuk',        [AbsensiController::class, 'absenMasuk'])->name('masuk');
    Route::get('/siang',         [AbsensiController::class, 'formSiang'])->name('form-siang');
    Route::post('/siang',        [AbsensiController::class, 'absenSiang'])->name('siang');
    Route::get('/pulang',        [AbsensiController::class, 'formPulang'])->name('form-pulang');
    Route::post('/pulang',       [AbsensiController::class, 'absenPulang'])->name('pulang');
    Route::post('/cek-gps',      [AbsensiController::class, 'cekGps'])->name('cek-gps');
    Route::get('/rekap',         [AbsensiController::class, 'rekap'])->name('rekap');
    Route::post('/{id}/koreksi', [AbsensiController::class, 'koreksi'])->name('koreksi');
    // Tambahkan 2 baris ini di dalam Route::prefix('absensi') group:
Route::post('/koreksi-baru/{userId}', [AbsensiController::class, 'koreksiManual'])->name('koreksi-manual');
Route::post('/validasi-kode', [AbsensiController::class, 'validasiKode'])->name('validasi-kode');
Route::get('/rekap-bulanan', [AbsensiController::class, 'rekapBulanan'])->name('rekap-bulanan');

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
 
    // Dashboard & generate (owner)
    Route::get('/',                  [PenggajianController::class, 'index'])->name('index');
    Route::post('/generate',         [PenggajianController::class, 'generate'])->name('generate');
    Route::post('/generate-semua',   [PenggajianController::class, 'generateSemua'])->name('generate-semua');
    Route::get('/slip/{slip}',       [PenggajianController::class, 'show'])->name('slip');
    Route::post('/slip/{slip}/konfirmasi', [PenggajianController::class, 'konfirmasi'])->name('konfirmasi');
    Route::post('/slip/{slip}/bayar',[PenggajianController::class, 'bayar'])->name('bayar');
    Route::post('/bayar-semua',      [PenggajianController::class, 'bayarSemua'])->name('bayar-semua');
 
    // Slip karyawan (semua level)
    Route::get('/slip-saya',         [PenggajianController::class, 'slipSaya'])->name('slip-saya');
 
    // Kasbon
    Route::get('/kasbon',            [PenggajianController::class, 'kasbon'])->name('kasbon');
    Route::post('/kasbon',           [PenggajianController::class, 'kasbonStore'])->name('kasbon.store');
    Route::post('/kasbon/{kasbon}/tunda', [PenggajianController::class, 'kasbonTunda'])->name('kasbon.tunda');
 
    // Potongan insidental
    Route::post('/insidental',       [PenggajianController::class, 'insidentalStore'])->name('insidental.store');
 
    // Tabungan lebaran
    Route::post('/tabungan-lebaran', [PenggajianController::class, 'setTabunganLebaran'])->name('tabungan-lebaran');
    
    Route::post('/kasbon/{kasbon}/approve', [PenggajianController::class, 'kasbonApprove'])->name('kasbon.approve');
Route::post('/kasbon/{kasbon}/tolak',   [PenggajianController::class, 'kasbonTolak'])->name('kasbon.tolak');
});

// ─── KASBON KARYAWAN ────────────────────────────────────────
Route::middleware('auth')->prefix('kasbon-saya')->name('kasbon.karyawan.')->group(function () {
    Route::get('/',                  [KasbonKaryawanController::class, 'index'])->name('index');
    Route::post('/',                 [KasbonKaryawanController::class, 'store'])->name('store');
    Route::get('/{kasbon}/surat',    [KasbonKaryawanController::class, 'surat'])->name('surat');
});
 
// ─── PROFIL KARYAWAN ────────────────────────────────────────
Route::middleware('auth')->prefix('profil')->name('profil.')->group(function () {
    Route::get('/',  [ProfilController::class, 'index'])->name('index');
    Route::put('/',  [ProfilController::class, 'update'])->name('update');
});
 
 Route::middleware('auth')->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});