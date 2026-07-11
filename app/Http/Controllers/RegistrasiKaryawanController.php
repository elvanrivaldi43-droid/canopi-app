<?php
// FILE: app/Http/Controllers/RegistrasiKaryawanController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrasiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RegistrasiKaryawanController extends Controller
{
    // Tampilkan form registrasi
    public function show(string $token)
    {
        $reg = RegistrasiToken::where('token', $token)
            ->where('used', false)
            ->where('expired_at', '>', now())
            ->firstOrFail();

        $karyawan = $reg->user;
        $levels   = ['','Owner','Admin Operasional','Supervisor Lapangan','Marketing','Teknisi','Driver','Admin Toko Besi'];
        $banks    = ['BCA','BCA Syariah','BRI','BNI','Mandiri','BSI','CIMB Niaga','Danamon','Permata','Lainnya'];

        return view('registrasi.form', compact('token','karyawan','levels','banks'));
    }

    // Simpan data diri karyawan
    public function simpan(Request $request, string $token)
    {
        $reg = RegistrasiToken::where('token', $token)
            ->where('used', false)
            ->where('expired_at', '>', now())
            ->firstOrFail();

        $request->validate([
            'name'              => 'required|string|max:255',
            'password'          => 'required|min:8|confirmed',
            'no_hp'             => 'required|string|max:20',
            'alamat'            => 'required|string',
            'tempat_lahir'      => 'required|string|max:100',
            'tgl_lahir'         => 'required|date',
            'no_ktp'            => 'required|string|size:16',
            'no_kk'             => 'required|string|size:16',
            'foto'              => 'required|image|mimes:jpg,jpeg,png|max:3072',
            'darurat_nama'      => 'required|string|max:255',
            'darurat_no_hp'     => 'required|string|max:20',
            'darurat_hubungan'  => 'required|string|max:50',
            'nama_bank'         => 'required|string',
            'no_rekening'       => 'required|string|max:30',
            'atas_nama'         => 'required|string|max:100',
            'ukuran_baju'       => 'required|in:XS,S,M,L,XL,XXL,XXXL',
            'status_nikah'      => 'required|in:belum_menikah,menikah,cerai',
            'jumlah_tanggungan' => 'required|integer|min:0|max:20',
            'golongan_darah'    => 'required|in:A,B,AB,O,A+,B+,AB+,O+,A-,B-,AB-,O-',
            'no_bpjs_kesehatan'         => 'nullable|string|max:20',
            'no_bpjs_ketenagakerjaan'   => 'nullable|string|max:20',
        ], [
            'name.required'             => 'Nama lengkap wajib diisi',
            'password.min'              => 'Password minimal 8 karakter',
            'password.confirmed'        => 'Konfirmasi password tidak cocok',
            'no_ktp.size'               => 'Nomor KTP harus 16 digit',
            'no_kk.size'                => 'Nomor KK harus 16 digit',
            'foto.required'             => 'Foto profil wajib diupload',
            'foto.max'                  => 'Ukuran foto maksimal 3MB',
        ]);

        $karyawan = $reg->user;

        // Upload foto
        $fotoPath = $request->file('foto')->store('karyawan/foto', 'public');

        // Update semua data
        $karyawan->update([
            'name'                      => $request->name,
            'password'                  => Hash::make($request->password),
            'no_hp'                     => $request->no_hp,
            'alamat'                    => $request->alamat,
            'tempat_lahir'              => $request->tempat_lahir,
            'tgl_lahir'                 => $request->tgl_lahir,
            'no_ktp'                    => $request->no_ktp,
            'no_kk'                     => $request->no_kk,
            'foto'                      => $fotoPath,
            'darurat_nama'              => $request->darurat_nama,
            'darurat_no_hp'             => $request->darurat_no_hp,
            'darurat_hubungan'          => $request->darurat_hubungan,
            'nama_bank'                 => $request->nama_bank,
            'no_rekening'               => $request->no_rekening,
            'atas_nama'                 => $request->atas_nama,
            'ukuran_baju'               => $request->ukuran_baju,
            'status_nikah'              => $request->status_nikah,
            'jumlah_tanggungan'         => $request->jumlah_tanggungan,
            'golongan_darah'            => $request->golongan_darah,
            'no_bpjs_kesehatan'         => $request->no_bpjs_kesehatan,
            'no_bpjs_ketenagakerjaan'   => $request->no_bpjs_ketenagakerjaan,
            'status_registrasi'         => 'lengkap',
        ]);

        // Tandai token sudah dipakai
        $reg->update(['used' => true]);

        return view('registrasi.sukses', compact('karyawan'));
    }

    // Token expired / tidak valid
    public function expired()
    {
        return view('registrasi.expired');
    }
}
