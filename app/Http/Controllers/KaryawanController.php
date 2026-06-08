<?php
// FILE: app/Http/Controllers/KaryawanController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrasiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KaryawanController extends Controller
{
    private array $levels = ['','Owner','Admin Operasional','Supervisor Lapangan','Marketing','Teknisi','Driver','Admin Toko Besi'];
    private array $banks  = ['BCA','BCA Syariah','BRI','BNI','Mandiri','BSI','CIMB Niaga','Danamon','Permata','Lainnya'];

    public function index(Request $request)
    {
        $query = User::where('id', '!=', auth()->id());
        if ($request->level)  $query->where('level', $request->level);
        if ($request->status) $query->where('status', $request->status);
        if ($request->search) $query->where('name', 'like', '%'.$request->search.'%');
        $karyawan = $query->orderBy('level')->orderBy('name')->paginate(15);
        $levels   = $this->levels;
        return view('karyawan.index', compact('karyawan','levels'));
    }

    public function create()
    {
        $levels    = $this->levels;
        $banks     = $this->banks;
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        return view('karyawan.create', compact('levels','tunjangan','banks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'email'           => 'required|email|unique:users,email',
            'level'           => 'required|integer|between:1,7',
            'jabatan'         => 'required|string|max:100',
            'tipe_gaji'       => 'required|in:harian,bulanan,project',
            'gaji_harian'     => 'nullable|numeric|min:0',
            'gaji_bulanan'    => 'nullable|numeric|min:0',
            'uang_makan'      => 'nullable|numeric|min:0',
            'uang_bonus'      => 'nullable|numeric|min:0',
            'jam_masuk'       => 'required',
            'jam_pulang'      => 'required',
            'tgl_masuk_kerja' => 'required|date',
        ]);

        $karyawan = User::create([
            'name'              => 'Karyawan Baru',
            'email'             => $request->email,
            'password'          => Hash::make(Str::random(20)),
            'level'             => $request->level,
            'jabatan'           => $request->jabatan,
            'tipe_gaji'         => $request->tipe_gaji,
            'gaji_harian'       => $request->gaji_harian  ?? 0,
            'gaji_bulanan'      => $request->gaji_bulanan ?? 0,
            'uang_makan'        => $request->uang_makan   ?? 0,
            'uang_bonus'        => $request->uang_bonus   ?? 0,
            'jam_masuk'         => $request->jam_masuk,
            'jam_pulang'        => $request->jam_pulang,
            'tgl_masuk_kerja'   => $request->tgl_masuk_kerja,
            'status'            => 'aktif',
            'status_registrasi' => 'menunggu',
        ]);

        if ($request->tunjangan) {
            foreach ($request->tunjangan as $tunjId => $nominal) {
                if ($nominal > 0) {
                    $karyawan->tunjangan()->attach($tunjId, ['nominal' => $nominal]);
                }
            }
        }

        $token = Str::random(48);
        RegistrasiToken::create([
            'user_id'    => $karyawan->id,
            'token'      => $token,
            'expired_at' => now()->addHours(24),
        ]);

        $link = url('/registrasi-karyawan/' . $token);
        Mail::send('emails.undangan-karyawan', [
            'link'    => $link,
            'jabatan' => $request->jabatan,
            'level'   => $this->levels[$request->level] ?? '',
        ], function($mail) use ($request) {
            $mail->to($request->email)
                 ->subject('Undangan Registrasi — Pusat Kanopi BSD');
        });

        return redirect()->route('karyawan.index')
            ->with('success', 'Undangan registrasi berhasil dikirim ke '.$request->email.'. Link berlaku 24 jam.');
    }

    public function show(User $karyawan)
    {
        $levels = $this->levels;
        $banks  = $this->banks;
        $karyawan->load('tunjangan');
        return view('karyawan.show', compact('karyawan','levels','banks'));
    }

    public function edit(User $karyawan)
    {
        $levels    = $this->levels;
        $banks     = $this->banks;
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        $karyawan->load('tunjangan');
        return view('karyawan.edit', compact('karyawan','levels','tunjangan','banks'));
    }

    public function update(Request $request, User $karyawan)
    {
        $request->validate([
            'level'        => 'required|integer|between:1,7',
            'jabatan'      => 'required|string|max:100',
            'tipe_gaji'    => 'required|in:harian,bulanan,project',
            'gaji_harian'  => 'nullable|numeric|min:0',
            'gaji_bulanan' => 'nullable|numeric|min:0',
            'uang_makan'   => 'nullable|numeric|min:0',
            'uang_bonus'   => 'nullable|numeric|min:0',
            'status'       => 'required|in:aktif,nonaktif,sp1,sp2,sp3',
            'jam_masuk'    => 'required',
            'jam_pulang'   => 'required',
        ]);

        $karyawan->update([
            'level'        => $request->level,
            'jabatan'      => $request->jabatan,
            'tipe_gaji'    => $request->tipe_gaji,
            'gaji_harian'  => $request->gaji_harian  ?? 0,
            'gaji_bulanan' => $request->gaji_bulanan ?? 0,
            'uang_makan'   => $request->uang_makan   ?? 0,
            'uang_bonus'   => $request->uang_bonus   ?? 0,
            'status'       => $request->status,
            'jam_masuk'    => $request->jam_masuk,
            'jam_pulang'   => $request->jam_pulang,
        ]);

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

    public function kirimUlang(User $karyawan)
    {
        RegistrasiToken::where('user_id', $karyawan->id)->delete();

        $token = Str::random(48);
        RegistrasiToken::create([
            'user_id'    => $karyawan->id,
            'token'      => $token,
            'expired_at' => now()->addHours(24),
        ]);

        $link = url('/registrasi-karyawan/'.$token);
        Mail::send('emails.undangan-karyawan', [
            'link'    => $link,
            'jabatan' => $karyawan->jabatan,
            'level'   => $this->levels[$karyawan->level] ?? '',
        ], function($mail) use ($karyawan) {
            $mail->to($karyawan->email)
                 ->subject('Undangan Registrasi (Kirim Ulang) — Pusat Kanopi BSD');
        });

        return back()->with('success', 'Link registrasi baru berhasil dikirim ke '.$karyawan->email);
    }

    public function resetPassword(Request $request, User $karyawan)
    {
        $request->validate(['password' => 'required|min:6|confirmed']);
        $karyawan->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Password '.$karyawan->name.' berhasil direset.');
    }

    public function nonaktifkan(User $karyawan)
    {
        $karyawan->update(['status' => 'nonaktif']);
        return back()->with('success', $karyawan->name.' berhasil dinonaktifkan.');
    }

    public function aktifkan(User $karyawan)
    {
        $karyawan->update(['status' => 'aktif']);
        return back()->with('success', $karyawan->name.' berhasil diaktifkan kembali.');
    }
}