<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProduktivitasController extends Controller
{
    // pilihan baku (bisa kamu ubah kapan saja di sini)
    private array $produkList = ['kanopi', 'pagar', 'tralis', 'railing', 'tangga', 'lainnya'];
    private array $satuanList = ['m2', 'meter', 'unit', 'lumpsum'];

    public function index()
    {
        abort_if(Auth::user()->level != 1, 403); // owner-only (upah = data sensitif)

        $skills  = DB::table('rab_skill')->orderBy('urutan')->orderBy('nama')->get();
        $kerja   = DB::table('rab_jenis_kerja')->orderBy('produk')->orderBy('urutan')->orderBy('nama')->get();
        $kondisi = DB::table('rab_kondisi_kerja')->orderBy('urutan')->orderBy('nama')->get();

        $produkList = $this->produkList;
        $satuanList = $this->satuanList;

        return view('produktivitas.index', compact('skills', 'kerja', 'kondisi', 'produkList', 'satuanList'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        // angka kosong -> null (biar "belum diisi" tetap kosong, bukan dipaksa 0)
        $num = function ($v) {
            if ($v === null || $v === '' || $v === false) return null;
            return is_numeric($v) ? $v + 0 : null;
        };

        $tersimpan = 0;

        // ---- SKILL ----
        foreach ((array) $request->input('skill', []) as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;
            $data = [
                'upah_tukang_harian' => $num($row['upah_tukang_harian'] ?? null),
                'upah_kenek_harian'  => $num($row['upah_kenek_harian'] ?? null),
                'is_active'          => !empty($row['is_active']) ? 1 : 0,
                'updated_at'         => now(),
            ];
            $id = $row['id'] ?? '';
            if (is_numeric($id)) {
                DB::table('rab_skill')->where('id', $id)->update($data + ['nama' => $nama]);
            } else {
                DB::table('rab_skill')->updateOrInsert(['nama' => $nama], $data + ['urutan' => 99, 'created_at' => now()]);
            }
            $tersimpan++;
        }

        // ---- JENIS KERJA (generik) ----
        foreach ((array) $request->input('kerja', []) as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;
            $satuan = trim($row['satuan'] ?? 'm2') ?: 'm2';
            if (!in_array($satuan, $this->satuanList)) $satuan = 'm2';
            $produk = trim($row['produk'] ?? 'kanopi') ?: 'kanopi';
            if (!in_array($produk, $this->produkList)) $produk = 'lainnya';

            $data = [
                'produk'                 => $produk,
                'satuan'                 => $satuan,
                'skill_default'          => trim($row['skill_default'] ?? 'umum') ?: 'umum',
                'produktivitas_per_hari' => $num($row['produktivitas_per_hari'] ?? null),
                'produktivitas_inst'     => $num($row['produktivitas_inst'] ?? null),
                'jml_tukang'             => $num($row['jml_tukang'] ?? null),
                'jml_kenek'              => $num($row['jml_kenek'] ?? null),
                'jml_tukang_inst'        => $num($row['jml_tukang_inst'] ?? null),
                'jml_kenek_inst'         => $num($row['jml_kenek_inst'] ?? null),
                'is_active'              => !empty($row['is_active']) ? 1 : 0,
                'updated_at'             => now(),
            ];
            $id = $row['id'] ?? '';
            if (is_numeric($id)) {
                DB::table('rab_jenis_kerja')->where('id', $id)->update($data + ['nama' => $nama]);
            } else {
                DB::table('rab_jenis_kerja')->updateOrInsert(['nama' => $nama], $data + ['urutan' => 99, 'created_at' => now()]);
            }
            $tersimpan++;
        }

        // ---- KONDISI KERJA ----
        foreach ((array) $request->input('kondisi', []) as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;
            $kena = ($row['kena'] ?? 'fabinst') === 'inst' ? 'inst' : 'fabinst';
            $data = [
                'pengali_upah'      => $num($row['pengali_upah'] ?? null),
                'tambahan_per_hari' => $num($row['tambahan_per_hari'] ?? null),
                'kena'              => $kena,
                'is_active'         => !empty($row['is_active']) ? 1 : 0,
                'updated_at'        => now(),
            ];
            $id = $row['id'] ?? '';
            if (is_numeric($id)) {
                DB::table('rab_kondisi_kerja')->where('id', $id)->update($data + ['nama' => $nama]);
            } else {
                DB::table('rab_kondisi_kerja')->updateOrInsert(['nama' => $nama], $data + ['urutan' => 99, 'created_at' => now()]);
            }
            $tersimpan++;
        }

        return response()->json(['success' => true, 'tersimpan' => $tersimpan]);
    }
}