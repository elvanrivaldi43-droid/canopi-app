<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\TahapMaster;

class TahapMasterController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);

        $rows = TahapMaster::orderBy('urutan')->orderBy('id')->get();
        $jenisKerjaOptions = DB::table('rab_jenis_kerja')->orderBy('nama')->get(['id', 'nama']);

        return view('tahap-master.index', compact('rows', 'jenisKerjaOptions'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        $tersimpan = 0;
        foreach ((array) $request->input('rows', []) as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;

            $tipe = $row['tipe'] ?? '';
            if (!in_array($tipe, ['fab', 'inst'])) $tipe = null;

            $rabJenisKerjaId = $row['rab_jenis_kerja_id'] ?? '';
            $rabJenisKerjaId = is_numeric($rabJenisKerjaId) ? (int) $rabJenisKerjaId : null;

            $data = [
                'nama'               => $nama,
                'rab_jenis_kerja_id' => $rabJenisKerjaId,
                'tipe'               => $tipe,
                'urutan'             => is_numeric($row['urutan'] ?? null) ? (int) $row['urutan'] : 99,
                'is_active'          => !empty($row['is_active']),
            ];

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                TahapMaster::where('id', $id)->update($data);
            } else {
                TahapMaster::create($data);
            }
            $tersimpan++;
        }

        return redirect('/tahap-master')->with('success', "Tersimpan $tersimpan baris tahap.");
    }
}
