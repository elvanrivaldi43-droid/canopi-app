<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\SettingGajiService;

/**
 * Saklar kebijakan gaji (Owner saja). Dibuat 31 Ags 2026: bonus KPI & tabungan wajib
 * dulu dipaku di kode, sekarang bisa dinyalakan/dimatikan tanpa ubah kode.
 * Pola menumpang SettingRabController (tabel 1 baris, id=1).
 */
class SettingGajiController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);
        $s = SettingGajiService::ambil();
        return view('setting-gaji.index', compact('s'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        // Checkbox tak dicentang = tidak terkirim -> boolean() menghasilkan false (MATI).
        $data = [
            'bonus_kpi_aktif'      => $request->boolean('bonus_kpi_aktif') ? 1 : 0,
            'tabungan_wajib_aktif' => $request->boolean('tabungan_wajib_aktif') ? 1 : 0,
            'updated_at'           => now(),
        ];

        try {
            $ada = DB::table('setting_gaji')->where('id', 1)->exists();
            $ada ? DB::table('setting_gaji')->where('id', 1)->update($data)
                 : DB::table('setting_gaji')->insert($data + ['id' => 1]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Tabel setting_gaji belum ada — jalankan docs/sql/2026-08-31-setting-gaji.sql di phpMyAdmin dulu.');
        }

        return redirect('/setting-gaji')->with('success', 'Pengaturan gaji tersimpan. Slip yang BELUM dibayar perlu dihitung ulang supaya ikut berubah.');
    }
}
