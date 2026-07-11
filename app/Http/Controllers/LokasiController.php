<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LokasiController extends Controller
{
    private function bolehAkses(): bool
    {
        return in_array(Auth::user()->level, [1, 2, 3]);
    }

    public function index($id)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lead = DB::table('pipeline_leads')->where('id', $id)->first();
        abort_if(!$lead, 404);
        return view('lokasi.index', compact('lead'));
    }

    public function simpan(Request $request, $id)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lead = DB::table('pipeline_leads')->where('id', $id)->first();
        abort_if(!$lead, 404);

        $request->validate([
            'lokasi_area'           => 'nullable|string|max:255',
            'lokasi_patokan'        => 'nullable|string|max:255',
            'lokasi_maps_link'      => 'nullable|string|max:500',
            'lokasi_sharelok'       => 'nullable|string|max:500',
            'lokasi_lat'            => 'nullable|numeric',
            'lokasi_lng'            => 'nullable|numeric',
            'lokasi_jarak_km'       => 'nullable|numeric|min:0',
            'lokasi_listrik'        => 'nullable|string|max:20',
            'lokasi_jarak_listrik_m'=> 'nullable|integer|min:0',
            'lokasi_akses'          => 'nullable|string|max:50',
            'lokasi_catatan'        => 'nullable|string',
            'lokasi_foto'           => 'nullable|string',
        ]);

        $data = [
            'lokasi_area'            => $request->lokasi_area,
            'lokasi_patokan'         => $request->lokasi_patokan,
            'lokasi_maps_link'       => $request->lokasi_maps_link,
            'lokasi_sharelok'        => $request->lokasi_sharelok,
            'lokasi_jarak_km'        => $request->lokasi_jarak_km,
            'lokasi_listrik'         => $request->lokasi_listrik,
            'lokasi_jarak_listrik_m' => $request->lokasi_jarak_listrik_m,
            'lokasi_akses'           => $request->lokasi_akses,
            'lokasi_catatan'         => $request->lokasi_catatan,
            'lokasi_foto'            => $request->lokasi_foto,
            'lokasi_oleh'            => Auth::id(),
            'lokasi_updated_at'      => now(),
            'last_activity_at'       => now(),
            'updated_at'             => now(),
        ];

        // GPS hanya disimpan kalau dikirim (surveyor/owner) — jangan timpa dengan kosong
        if ($request->filled('lokasi_lat') && $request->filled('lokasi_lng')) {
            $data['lokasi_lat']    = $request->lokasi_lat;
            $data['lokasi_lng']    = $request->lokasi_lng;
            $data['lokasi_gps_at'] = now();
        }

        DB::table('pipeline_leads')->where('id', $id)->update($data);

        // Wizard: tombol "Lanjut" -> lanjut ke Buat RAB; selain itu balik ke lead
        if ($request->input('goto') === 'rab') {
            return redirect('/rab-opsi?lead=' . $id)->with('success', 'Profil lokasi tersimpan. Lanjut buat RAB.');
        }
        return redirect('/pipeline/' . $id)->with('success', 'Profil lokasi tersimpan.');
    }
}