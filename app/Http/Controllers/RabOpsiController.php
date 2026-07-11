<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RabOpsiController extends Controller
{
    public function index(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);

        $besi = collect();
        try {
            $besi = DB::table('master_material')
                ->where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {}

        $besiSemua = collect();
        try {
            $besiSemua = DB::table('master_material')->where('aktif', 1)
                ->orderBy('kategori')->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {}

        $jenisKerja = collect(); $kondisi = collect(); $atap = collect(); $addon = collect();
        try {
            $jenisKerja = DB::table('rab_jenis_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'satuan', 'produktivitas_per_hari', 'jml_tukang', 'jml_kenek', 'skill_default']);
            $kondisi = DB::table('rab_kondisi_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'pengali_upah', 'tambahan_per_hari']);
            $atap = DB::table('rab_atap')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')->get(['id', 'nama', 'harga_per_m2', 'pemborosan_persen', 'upah_pasang_per_m2']);
            $addon = DB::table('rab_addon')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')->get(['id', 'nama', 'satuan', 'formula_type', 'harga_pokok_satuan', 'level']);
        } catch (\Throwable $e) {}

        // ---- konteks lead (kalau dibuka dari pipeline: /rab-opsi?lead=ID) ----
        $lead = null;
        if ($request->filled('lead')) {
            try {
                $lead = DB::table('pipeline_leads')->where('id', $request->lead)->first();
            } catch (\Throwable $e) { $lead = null; }
        }

        // ---- pengaturan RAB global (rab_setting_global, 1 baris id=1) ----
        $setting = null;
        try {
            $setting = DB::table('rab_setting_global')->where('id', 1)->first();
        } catch (\Throwable $e) { $setting = null; }

        $lihatHarga = in_array(Auth::user()->level, [1, 2, 3]); // harga jual: admin+surveyor+owner
        $lihatModal = Auth::user()->level == 1;                  // margin/modal/tarif: owner saja
        return view('rab-opsi.index', compact('besi', 'besiSemua', 'jenisKerja', 'kondisi', 'atap', 'addon', 'lihatHarga', 'lihatModal', 'lead', 'setting'));
    }

    // Admin: simpan hasil mesin (kasar) sebagai ESTIMASI AWAL (range, condong ke atas)
    public function simpanEstimasi(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);
        $request->validate([
            'lead_id' => 'required|integer',
            'harga'   => 'required|numeric|min:0',
        ]);

        $lead = DB::table('pipeline_leads')->where('id', $request->lead_id)->first();
        if (!$lead) return response()->json(['success' => false, 'message' => 'Lead tidak ditemukan'], 404);

        $min = (int) round($request->harga);
        $max = (int) ceil($request->harga * 1.2); // buffer 20% — condong ke atas

        DB::table('pipeline_leads')->where('id', $request->lead_id)->update([
            'estimasi_min'     => $min,
            'estimasi_max'     => $max,
            'estimasi_nilai'   => $max,
            'last_activity_at' => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estimasi awal tersimpan ke lead.',
            'estimasi_min' => $min,
            'estimasi_max' => $max,
        ]);
    }

    // Surveyor: simpan opsi terpilih sebagai HARGA FINAL (disimpan TERPISAH dari estimasi admin)
    public function simpanFinal(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);
        $request->validate([
            'lead_id'  => 'required|integer',
            'harga'    => 'required|numeric|min:0',
            'snapshot' => 'nullable|string',
        ]);

        $lead = DB::table('pipeline_leads')->where('id', $request->lead_id)->first();
        if (!$lead) return response()->json(['success' => false, 'message' => 'Lead tidak ditemukan'], 404);

        $final = (int) round($request->harga);

        // peringatan kalau final > 15% di atas estimasi_max admin
        $warning = null;
        $estMax = (int) ($lead->estimasi_max ?? 0);
        if ($estMax > 0 && $final > $estMax * 1.15) {
            $lebih = round(($final / $estMax - 1) * 100);
            $warning = 'Harga final ' . $lebih . '% di atas estimasi admin (Rp ' . number_format($estMax, 0, ',', '.') . '). Jelaskan ke customer sebelum closing.';
        }

        DB::table('pipeline_leads')->where('id', $request->lead_id)->update([
            'harga_final'      => $final,
            'rab_snapshot'     => $request->snapshot,
            'final_oleh'       => Auth::id(),
            'final_at'         => now(),
            'last_activity_at' => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Harga final tersimpan ke lead.',
            'harga_final' => $final,
            'warning'     => $warning,
        ]);
    }

    // AUTO-SAVE: simpan snapshot wizard diam-diam (TIDAK sentuh harga final)
    public function autosave(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);
        $request->validate([
            'lead_id'  => 'required|integer',
            'snapshot' => 'nullable|string',
        ]);
        DB::table('pipeline_leads')->where('id', $request->lead_id)->update([
            'rab_snapshot' => $request->snapshot,
            'updated_at'   => now(),
        ]);
        return response()->json(['success' => true]);
    }

    // F1: simpan data penawaran (semua opsi + harga + rincian, sudah dihitung di layar)
    public function simpanPenawaran(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);
        $request->validate([
            'lead_id'   => 'required|integer',
            'penawaran' => 'required|string',
        ]);
        DB::table('pipeline_leads')->where('id', $request->lead_id)->update([
            'penawaran_json' => $request->penawaran,
            'updated_at'     => now(),
        ]);
        return response()->json(['success' => true]);
    }
}