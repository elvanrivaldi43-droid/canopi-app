<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddonController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);
        $rows = DB::table('rab_addon')->orderBy('urutan')->orderBy('id')->get();
        return view('addon.index', compact('rows'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        $num = function ($v) {
            $v = preg_replace('/[^0-9.]/', '', (string) $v);
            return $v === '' ? 0 : (float) $v;
        };

        $rows = $request->input('rows', []);
        foreach ($rows as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;

            $ft = $row['formula_type'] ?? 'per_unit';
            if (!in_array($ft, ['per_unit', 'per_meter', 'per_m2', 'flat'])) $ft = 'per_unit';

            $lv = $row['level'] ?? 'total';
            if (!in_array($lv, ['rangka', 'atap', 'total'])) $lv = 'total';

            $data = [
                'nama'               => $nama,
                'satuan'             => trim($row['satuan'] ?? 'unit') ?: 'unit',
                'formula_type'       => $ft,
                'level'              => $lv,
                'harga_satuan'       => $num($row['harga_satuan'] ?? 0),
                'harga_pokok_satuan' => $num($row['harga_pokok_satuan'] ?? 0),
                'durasi_fab'         => $num($row['durasi_fab'] ?? 0),
                'durasi_inst'        => $num($row['durasi_inst'] ?? 0),
                'is_active'          => !empty($row['is_active']) ? 1 : 0,
                'updated_at'         => now(),
            ];

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                DB::table('rab_addon')->where('id', $id)->update($data);
            } else {
                $data['kode']            = $this->genKode($nama);
                $data['kategori']        = 'lainnya';
                $data['qty_default']     = 1;
                $data['perlu_input_qty'] = 1;
                $data['urutan']          = 999;
                $data['created_at']      = now();
                DB::table('rab_addon')->insert($data);
            }
        }

        return redirect('/addon')->with('success', 'Data add-on tersimpan.');
    }

    private function genKode($nama)
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($nama)));
        $base = trim($base, '_');
        if ($base === '') $base = 'ADDON';
        $base = substr($base, 0, 30);
        $kode = $base;
        $i = 1;
        while (DB::table('rab_addon')->where('kode', $kode)->exists()) {
            $kode = substr($base, 0, 26) . '_' . $i;
            $i++;
        }
        return $kode;
    }
}