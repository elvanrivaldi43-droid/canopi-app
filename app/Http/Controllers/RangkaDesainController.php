<?php

namespace App\Http\Controllers;

use App\Services\RangkaDesignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RangkaDesainController extends Controller
{
    private function bolehAkses(): bool
    {
        return Auth::check() && (int) Auth::user()->level === 1; // owner-only
    }

    public function index()
    {
        abort_if(!$this->bolehAkses(), 403);

        $besi = collect();
        try {
            $besi = DB::table('master_material')
                ->where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {
            $besi = collect();
        }

        $lihatHarga = (int) Auth::user()->level === 1;
        return view('rangka-desain.index', compact('besi', 'lihatHarga'));
    }

    public function seed(Request $request, RangkaDesignService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $in = [
            'lebar_cm'     => (float) $request->input('lebar_cm', 0),
            'panjang_cm'   => (float) $request->input('panjang_cm', 0),
            'tinggi_cm'    => (float) $request->input('tinggi_cm', 300),
            'kotak_cm'     => (float) $request->input('kotak_cm', 80),
            'arah_support' => (int) $request->input('arah_support', 2),
            'jml_tiang'    => (int) $request->input('jml_tiang', 2),
            'mat_frame'    => trim((string) $request->input('mat_frame', 'Frame')),
            'mat_support'  => trim((string) $request->input('mat_support', 'Support')),
            'mat_tiang'    => trim((string) $request->input('mat_tiang', 'Tiang')),
        ];
        return response()->json(['success' => true, 'data' => $svc->seedDariKotak($in)]);
    }

    public function hitung(Request $request, RangkaDesignService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lihatHarga = (int) Auth::user()->level === 1;
        $members = (array) $request->input('members', []);
        $harga   = (array) $request->input('harga', []);
        return response()->json(['success' => true, 'data' => $svc->hitung($members, $harga, $lihatHarga)]);
    }
}
