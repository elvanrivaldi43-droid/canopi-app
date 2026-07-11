<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettingRabController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);
        $s = DB::table('rab_setting_global')->where('id', 1)->first();
        return view('rab-setting.index', compact('s'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        $request->validate([
            'diskon_max'      => 'required|numeric|min:0|max:99',
            'margin_default'  => 'required|numeric|min:0|max:90',
            'lay_hemat'       => 'required|numeric|min:0|max:50',
            'lay_kilat'       => 'required|numeric|min:0|max:100',
            'tarif_km'        => 'required|integer|min:0',
            'tarif_genset'    => 'required|integer|min:0',
            'tarif_hotel'     => 'required|integer|min:0',
            'tarif_kontrakan' => 'required|integer|min:0',
            'tarif_makan'     => 'required|integer|min:0',
            'consumable_rangka' => 'required|numeric|min:0',
            'consumable_atap'   => 'required|numeric|min:0',
            'finishing_standar' => 'required|numeric|min:0',
            'powder_coating'    => 'required|numeric|min:0',
        ]);

        DB::table('rab_setting_global')->where('id', 1)->update([
            'diskon_max'      => $request->diskon_max,
            'margin_default'  => $request->margin_default,
            'lay_hemat'       => $request->lay_hemat,
            'lay_kilat'       => $request->lay_kilat,
            'tarif_km'        => $request->tarif_km,
            'tarif_genset'    => $request->tarif_genset,
            'tarif_hotel'     => $request->tarif_hotel,
            'tarif_kontrakan' => $request->tarif_kontrakan,
            'tarif_makan'     => $request->tarif_makan,
            'consumable_rangka' => $request->consumable_rangka,
            'consumable_atap'   => $request->consumable_atap,
            'finishing_standar' => $request->finishing_standar,
            'powder_coating'    => $request->powder_coating,
            'updated_at'      => now(),
        ]);

        return redirect('/rab-setting')->with('success', 'Pengaturan RAB tersimpan.');
    }
}