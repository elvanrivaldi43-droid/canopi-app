<?php

namespace App\Http\Controllers;

use App\Models\MasterMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MasterMaterialController extends Controller
{
    // Daftar semua material
    public function index(Request $request)
    {
        $query = MasterMaterial::orderBy('kategori')->orderBy('nama');

        if ($request->search) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }
        if ($request->kategori) {
            $query->where('kategori', $request->kategori);
        }
        if ($request->has('aktif') && $request->aktif !== '') {
            $query->where('aktif', $request->aktif);
        }
        if ($request->sumber && Schema::hasColumn('master_material', 'sumber')) {
            $query->where('sumber', $request->sumber);
        }

        $materials = $query->paginate(50)->withQueryString();
        $kategoriList = MasterMaterial::$kategoriLabel;

        // Stats per kategori
        $stats = MasterMaterial::selectRaw('kategori, count(*) as total')
            ->groupBy('kategori')->pluck('total', 'kategori');

        return view('master-material.index', compact('materials', 'kategoriList', 'stats'));
    }

    // Form tambah
    public function create()
    {
        $kategoriList = MasterMaterial::$kategoriLabel;
        $satuanList = ['pcs','batang','lembar','m2','meter','roll','kaleng','liter','kg','gulung','lubang','item'];
        return view('master-material.create', compact('kategoriList', 'satuanList'));
    }

    // Simpan material baru
    public function store(Request $request)
    {
        $request->validate([
            'nama'        => 'required|string|max:150',
            'kategori'    => 'required|in:rangka_besi,kaca,atap,cat_finishing,aksesori,talang,konsumabel,jasa,lainnya',
            'satuan'      => 'required|string|max:20',
            'harga_pokok' => 'required|integer|min:0',
        ]);

        $material = MasterMaterial::create([
            'kode'        => $request->kode,
            'nama'        => $request->nama,
            'kategori'    => $request->kategori,
            'satuan'      => $request->satuan,
            'harga_pokok' => $request->harga_pokok,
            'keterangan'  => $request->keterangan,
            'aktif'       => 1,
            'created_by'  => Auth::id(),
        ]);

        if (Schema::hasColumn('master_material', 'sumber')) {
            $material->sumber = $request->sumber === 'pos' ? 'pos' : 'luar';
            $material->save();
        }

        return redirect()->route('master-material.index')
            ->with('success', 'Material "' . $request->nama . '" berhasil ditambahkan.');
    }

    // Form edit
    public function edit(MasterMaterial $masterMaterial)
    {
        $kategoriList = MasterMaterial::$kategoriLabel;
        $satuanList = ['pcs','batang','lembar','m2','meter','roll','kaleng','liter','kg','gulung','lubang','item'];
        return view('master-material.edit', compact('masterMaterial', 'kategoriList', 'satuanList'));
    }

    // Update material
    public function update(Request $request, MasterMaterial $masterMaterial)
    {
        $request->validate([
            'nama'        => 'required|string|max:150',
            'kategori'    => 'required',
            'satuan'      => 'required|string|max:20',
            'harga_pokok' => 'required|integer|min:0',
        ]);

        $masterMaterial->update([
            'kode'        => $request->kode,
            'nama'        => $request->nama,
            'kategori'    => $request->kategori,
            'satuan'      => $request->satuan,
            'harga_pokok' => $request->harga_pokok,
            'keterangan'  => $request->keterangan,
        ]);

        if (Schema::hasColumn('master_material', 'sumber')) {
            $masterMaterial->sumber = $request->sumber === 'pos' ? 'pos' : 'luar';
            $masterMaterial->save();
        }

        return redirect()->route('master-material.index')
            ->with('success', 'Material "' . $request->nama . '" berhasil diupdate.');
    }

    // Toggle aktif/nonaktif
    public function toggleAktif(MasterMaterial $masterMaterial)
    {
        $masterMaterial->update(['aktif' => !$masterMaterial->aktif]);
        $status = $masterMaterial->aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', '"' . $masterMaterial->nama . '" berhasil ' . $status . '.');
    }

    // API: cari material (untuk RAB builder autocomplete)
    public function search(Request $request)
    {
        $items = MasterMaterial::aktif()
            ->where('nama', 'like', '%' . $request->q . '%')
            ->orderBy('nama')
            ->limit(20)
            ->get(['id', 'nama', 'kategori', 'satuan', 'harga_pokok']);

        return response()->json($items);
    }
}