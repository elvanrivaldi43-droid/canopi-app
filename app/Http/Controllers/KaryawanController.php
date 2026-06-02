<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KaryawanController extends Controller
{
    // Daftar karyawan
    public function index(Request $request)
    {
        $query = User::where('id', '!=', auth()->id());

        // Filter level
        if ($request->level) {
            $query->where('level', $request->level);
        }

        // Filter status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Search nama
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $karyawan = $query->orderBy('level')->orderBy('name')->paginate(15);

        $levels = ['', 'Owner', 'Admin Operasional', 'Supervisor Lapangan', 'Marketing', 'Teknisi', 'Driver', 'Admin Toko Besi'];

        return view('karyawan.index', compact('karyawan', 'levels'));
    }

    // Form tambah karyawan
    public function create()
    {
        $levels = ['', 'Owner', 'Admin Operasional', 'Supervisor Lapangan', 'Marketing', 'Teknisi', 'Driver', 'Admin Toko Besi'];
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        $banks = ['BCA', 'BRI', 'Mandiri', 'BNI', 'BSI', 'CIMB Niaga', 'Danamon', 'Permata', 'Lainnya'];

        return view('karyawan.create', compact('levels', 'tunjangan', 'banks'));
    }

    // Simpan karyawan baru
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:6',
            'level'          => 'required|integer|between:1,7',
            'jabatan'        => 'required|string|max:100',
            'no_hp'          => 'required|string|max:20',
            'alamat'         => 'nullable|string',
            'foto'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'tgl_masuk_kerja'=> 'required|date',
            'jam_masuk'      => 'required',
            'jam_pulang'     => 'required',
            'tipe_gaji'      => 'required|in:harian,bulanan,project',
            'gaji_harian'    => 'nullable|numeric|min:0',
            'gaji_bulanan'   => 'nullable|numeric|min:0',
            'uang_makan'     => 'nullable|numeric|min:0',
            'nama_bank'      => 'nullable|string',
            'no_rekening'    => 'nullable|string|max:30',
            'atas_nama'      => 'nullable|string|max:100',
        ]);

        // Upload foto
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('karyawan/foto', 'public');
        }

        $karyawan = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'level'           => $request->level,
            'jabatan'         => $request->jabatan,
            'no_hp'           => $request->no_hp,
            'alamat'          => $request->alamat,
            'foto'            => $fotoPath,
            'tgl_masuk_kerja' => $request->tgl_masuk_kerja,
            'jam_masuk'       => $request->jam_masuk,
            'jam_pulang'      => $request->jam_pulang,
            'status'          => 'aktif',
            'tipe_gaji'       => $request->tipe_gaji,
            'gaji_harian'     => $request->gaji_harian ?? 0,
            'gaji_bulanan'    => $request->gaji_bulanan ?? 0,
            'uang_makan'      => $request->uang_makan ?? 0,
            'nama_bank'       => $request->nama_bank,
            'no_rekening'     => $request->no_rekening,
            'atas_nama'       => $request->atas_nama,
        ]);

        // Simpan tunjangan
        if ($request->tunjangan) {
            foreach ($request->tunjangan as $tunjId => $nominal) {
                if ($nominal > 0) {
                    $karyawan->tunjangan()->attach($tunjId, ['nominal' => $nominal]);
                }
            }
        }

        return redirect()->route('karyawan.index')
            ->with('success', 'Karyawan ' . $karyawan->name . ' berhasil ditambahkan.');
    }

    // Detail karyawan
    public function show(User $karyawan)
    {
        $levels = ['', 'Owner', 'Admin Operasional', 'Supervisor Lapangan', 'Marketing', 'Teknisi', 'Driver', 'Admin Toko Besi'];
        $karyawan->load('tunjangan');

        return view('karyawan.show', compact('karyawan', 'levels'));
    }

    // Form edit karyawan
    public function edit(User $karyawan)
    {
        $levels = ['', 'Owner', 'Admin Operasional', 'Supervisor Lapangan', 'Marketing', 'Teknisi', 'Driver', 'Admin Toko Besi'];
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        $banks = ['BCA', 'BRI', 'Mandiri', 'BNI', 'BSI', 'CIMB Niaga', 'Danamon', 'Permata', 'Lainnya'];
        $karyawan->load('tunjangan');

        return view('karyawan.edit', compact('karyawan', 'levels', 'tunjangan', 'banks'));
    }

    // Update karyawan
    public function update(Request $request, User $karyawan)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', Rule::unique('users')->ignore($karyawan->id)],
            'level'          => 'required|integer|between:1,7',
            'jabatan'        => 'required|string|max:100',
            'no_hp'          => 'required|string|max:20',
            'alamat'         => 'nullable|string',
            'foto'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'tgl_masuk_kerja'=> 'required|date',
            'jam_masuk'      => 'required',
            'jam_pulang'     => 'required',
            'tipe_gaji'      => 'required|in:harian,bulanan,project',
            'gaji_harian'    => 'nullable|numeric|min:0',
            'gaji_bulanan'   => 'nullable|numeric|min:0',
            'uang_makan'     => 'nullable|numeric|min:0',
            'nama_bank'      => 'nullable|string',
            'no_rekening'    => 'nullable|string|max:30',
            'atas_nama'      => 'nullable|string|max:100',
            'status'         => 'required|in:aktif,nonaktif,sp1,sp2,sp3',
        ]);

        // Upload foto baru
        if ($request->hasFile('foto')) {
            if ($karyawan->foto) Storage::disk('public')->delete($karyawan->foto);
            $karyawan->foto = $request->file('foto')->store('karyawan/foto', 'public');
        }

        $karyawan->update([
            'name'            => $request->name,
            'email'           => $request->email,
            'level'           => $request->level,
            'jabatan'         => $request->jabatan,
            'no_hp'           => $request->no_hp,
            'alamat'          => $request->alamat,
            'foto'            => $karyawan->foto,
            'tgl_masuk_kerja' => $request->tgl_masuk_kerja,
            'jam_masuk'       => $request->jam_masuk,
            'jam_pulang'      => $request->jam_pulang,
            'status'          => $request->status,
            'tipe_gaji'       => $request->tipe_gaji,
            'gaji_harian'     => $request->gaji_harian ?? 0,
            'gaji_bulanan'    => $request->gaji_bulanan ?? 0,
            'uang_makan'      => $request->uang_makan ?? 0,
            'nama_bank'       => $request->nama_bank,
            'no_rekening'     => $request->no_rekening,
            'atas_nama'       => $request->atas_nama,
        ]);

        // Update tunjangan
        $karyawan->tunjangan()->detach();
        if ($request->tunjangan) {
            foreach ($request->tunjangan as $tunjId => $nominal) {
                if ($nominal > 0) {
                    $karyawan->tunjangan()->attach($tunjId, ['nominal' => $nominal]);
                }
            }
        }

        return redirect()->route('karyawan.show', $karyawan)
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    // Reset password oleh owner
    public function resetPassword(Request $request, User $karyawan)
    {
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $karyawan->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password ' . $karyawan->name . ' berhasil direset.');
    }

    // Nonaktifkan karyawan
    public function nonaktifkan(User $karyawan)
    {
        $karyawan->update(['status' => 'nonaktif']);
        return back()->with('success', $karyawan->name . ' berhasil dinonaktifkan.');
    }

    // Aktifkan kembali
    public function aktifkan(User $karyawan)
    {
        $karyawan->update(['status' => 'aktif']);
        return back()->with('success', $karyawan->name . ' berhasil diaktifkan kembali.');
    }
}