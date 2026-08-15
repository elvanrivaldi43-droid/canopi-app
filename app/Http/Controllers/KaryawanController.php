<?php
// FILE: app/Http/Controllers/KaryawanController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrasiToken;
use App\Services\KaryawanAksesService;
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

    /**
     * Pagar tunggal modul ini: aktor hanya boleh menyentuh karyawan yang levelnya
     * memang boleh dia kelola. Owner: semua. Admin: level 3-7 saja — jadi baris
     * Owner DAN baris Admin lain (termasuk dirinya sendiri) tertutup.
     *
     * Dipanggil PALING AWAL di tiap method per-karyawan, sebelum data dimuat atau
     * diubah. Route `level:1,2` hanya menjaga "boleh masuk modulnya", bukan
     * "boleh menyentuh baris ini".
     */
    private function pastikanBolehKelola(User $karyawan): void
    {
        abort_unless(
            KaryawanAksesService::bolehKelola(auth()->user()?->level, $karyawan->level),
            403,
            'Kamu tidak punya akses ke data karyawan ini.'
        );
    }

    public function index(Request $request)
    {
        $query = User::where('id', '!=', auth()->id());

        // Admin hanya melihat level 3-7. Batas ini di QUERY, bukan di tampilan:
        // kalau cuma tombolnya yang disembunyikan, URL /karyawan/{id} tetap tembus.
        $batasLevel = KaryawanAksesService::levelUntukIndex(auth()->user()?->level);
        if ($batasLevel !== null) $query->whereIn('level', $batasLevel);

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
        $aktor = auth()->user()?->level;

        // Aturan finansial hanya ditegakkan untuk Owner — form Admin memang tidak
        // merender field itu, jadi `required` di sana bikin dia tidak pernah bisa
        // menyimpan sama sekali (lihat KaryawanAksesService::aturanFinansial).
        $request->validate(array_merge([
            'email'           => 'required|email|unique:users,email',
            // Daftar level dibatasi per aktor: Admin tidak bisa submit level 1-2
            // walau dropdown-nya dipalsukan lewat DevTools.
            // Aturan PEMBUATAN dipisah dari aturan perubahan: Owner pun tidak bisa
            // membuat Owner kedua dari sini (lihat aturanLevelCreate), sementara
            // update() tetap boleh 1-7 untuk baris yang sudah ada.
            'level'           => KaryawanAksesService::aturanLevelCreate($aktor),
            'jabatan'         => 'required|string|max:100',
            'jam_masuk'       => 'required|date_format:H:i',
            'jam_pulang'      => 'required|date_format:H:i',
            'tgl_masuk_kerja' => 'required|date',
            'hari_libur_default' => 'nullable|integer|between:0,6',
        ], KaryawanAksesService::aturanFinansial($aktor)));

        // Nominal dari form hanya dipercaya kalau aktornya Owner. Untuk Admin,
        // seluruh field finansial diganti default aman (harian, 0) — karyawan baru
        // belum tergaji sampai Owner melengkapi angkanya.
        $finansial = KaryawanAksesService::payloadCreate($aktor, [
            'tipe_gaji'    => $request->tipe_gaji,
            'gaji_harian'  => $request->gaji_harian  ?? 0,
            'gaji_bulanan' => $request->gaji_bulanan ?? 0,
            'uang_makan'   => $request->uang_makan   ?? 0,
            'uang_bonus'   => $request->uang_bonus   ?? 0,
        ]);

        $karyawan = User::create(array_merge([
            'name'              => 'Karyawan Baru',
            'email'             => $request->email,
            'password'          => Hash::make(Str::random(20)),
            'level'             => $request->level,
            'jabatan'           => $request->jabatan,
            'jam_masuk'         => $request->jam_masuk,
            'jam_pulang'        => $request->jam_pulang,
            'tgl_masuk_kerja'   => $request->tgl_masuk_kerja,
            'status'            => 'aktif',
            'status_registrasi' => 'menunggu',
            'hari_libur_default' => $request->hari_libur_default,
        ], $finansial));

        // Tunjangan = komponen gaji, jadi Owner saja.
        if (KaryawanAksesService::bolehTunjangan($aktor) && $request->tunjangan) {
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
        $this->pastikanBolehKelola($karyawan);

        $levels = $this->levels;
        $banks  = $this->banks;
        $karyawan->load('tunjangan');
        return view('karyawan.show', compact('karyawan','levels','banks'));
    }

    public function edit(User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

        $levels    = $this->levels;
        $banks     = $this->banks;
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        $karyawan->load('tunjangan');
        return view('karyawan.edit', compact('karyawan','levels','tunjangan','banks'));
    }

    public function update(Request $request, User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

        $aktor = auth()->user()?->level;

        // Sama seperti store(): aturan finansial hanya untuk Owner (form Admin tidak
        // merender field itu). Ini juga memperbaiki cacat lama — blok Data Gaji di
        // form Edit sudah Owner-only sejak dulu, tapi validasinya tetap menuntutnya,
        // jadi Admin tidak pernah bisa menyimpan Edit Karyawan sama sekali.
        $request->validate(array_merge([
            'level'              => KaryawanAksesService::aturanLevel($aktor),
            'jabatan'            => 'required|string|max:100',
            'jam_masuk'          => 'required|date_format:H:i',
            'jam_pulang'         => 'required|date_format:H:i',
            'tgl_masuk_kerja'    => 'required|date',
            'hari_libur_default' => 'nullable|integer|between:0,6',
        ], KaryawanAksesService::aturanFinansial($aktor)));

        // Payload disaring SEBELUM update: field finansial/rekening/tunjangan/
        // tanggal_bergabung dibuang kalau aktornya bukan Owner, jadi POST manual
        // pun tidak bisa menitipkannya.
        $data = KaryawanAksesService::saringPayload($aktor, [
            'name'                => $request->name ?? $karyawan->name,
            'no_hp'               => $request->no_hp,
            'level'               => $request->level,
            'jabatan'             => $request->jabatan,
            'tipe_gaji'           => $request->tipe_gaji,
            'gaji_harian'         => $request->gaji_harian  ?? 0,
            'gaji_bulanan'        => $request->gaji_bulanan ?? 0,
            'uang_makan'          => $request->uang_makan   ?? 0,
            'uang_bonus'          => $request->uang_bonus   ?? 0,
            'jam_masuk'           => $request->jam_masuk,
            'jam_pulang'          => $request->jam_pulang,
            'tgl_masuk_kerja'     => $request->tgl_masuk_kerja,
            'nama_bank'           => $request->nama_bank,
            'no_rekening'         => $request->no_rekening,
            'atas_nama'           => $request->atas_nama,
            'tanggal_bergabung'   => $request->tanggal_bergabung,
            'alamat'              => $request->alamat,
            'hari_libur_default'  => $request->hari_libur_default,
        ]);

        $karyawan->update($data);

        // detach() HANYA kalau aktor memang berhak — kalau tidak, update oleh Admin
        // akan menghapus seluruh tunjangan karyawan tanpa dia sadari (dia bahkan
        // tidak melihat field-nya).
        if (KaryawanAksesService::bolehTunjangan($aktor)) {
            $karyawan->tunjangan()->detach();
            if ($request->tunjangan) {
                foreach ($request->tunjangan as $tunjId => $nominal) {
                    if ($nominal > 0) {
                        $karyawan->tunjangan()->attach($tunjId, ['nominal' => $nominal]);
                    }
                }
            }
        }

        return redirect()->route('karyawan.show', $karyawan)
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function kirimUlang(User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

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
        $this->pastikanBolehKelola($karyawan);

        $request->validate(['password' => 'required|min:6|confirmed']);
        $karyawan->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Password '.$karyawan->name.' berhasil direset.');
    }

    public function nonaktifkan(User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

        $karyawan->update(['status' => 'nonaktif']);
        return back()->with('success', $karyawan->name.' berhasil dinonaktifkan.');
    }

    public function aktifkan(User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

        $karyawan->update(['status' => 'aktif']);
        return back()->with('success', $karyawan->name.' berhasil diaktifkan kembali.');
    }
}
