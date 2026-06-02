<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\AbsensiController;

// Halaman utama → redirect ke login
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes dari Breeze
require __DIR__.'/auth.php';

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
});

// ─── ABSENSI - Semua karyawan ──────────────────────────────
Route::middleware(['auth'])->prefix('absensi')->name('absensi.')->group(function () {
    Route::get('/', [AbsensiController::class, 'index'])->name('index');
    Route::get('/masuk', [AbsensiController::class, 'formMasuk'])->name('form-masuk');
    Route::post('/masuk', [AbsensiController::class, 'absenMasuk'])->name('absen-masuk');
    Route::get('/pulang', [AbsensiController::class, 'formPulang'])->name('form-pulang');
    Route::post('/pulang', [AbsensiController::class, 'absenPulang'])->name('absen-pulang');
});

// ─── REKAP ABSENSI - Owner & Admin ─────────────────────────
Route::middleware(['auth', 'level:1,2'])->prefix('absensi')->name('absensi.')->group(function () {
    Route::get('/rekap', [AbsensiController::class, 'rekap'])->name('rekap');
    Route::post('/{absensi}/koreksi', [AbsensiController::class, 'koreksi'])->name('koreksi');
});