<?php

namespace App\Http\Controllers;

use App\Models\TugasHarian;
use App\Models\TugasAssignee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TugasHarianController extends Controller
{
    // ----------------------------------------------------------------
    // INDEX — daftar tugas (owner/admin/supervisor: semua | karyawan: milik sendiri)
    // ----------------------------------------------------------------
    public function index(Request $request)
    {
        $user    = Auth::user();
        $tanggal = $request->tanggal ?? today()->toDateString();

        // Owner (1), Admin (2), Supervisor (3) → lihat semua tugas tanggal tsb
        if (in_array($user->level, [1, 2, 3])) {
            $tugasList = TugasHarian::with(['pembuat', 'assignees.user'])
                ->byTanggal($tanggal)
                ->orderBy('jam_mulai')
                ->get();
        } else {
            // Karyawan → hanya tugas yang di-assign ke mereka
            $tugasList = TugasHarian::with(['pembuat', 'assignees' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->whereHas('assignees', fn($q) => $q->where('user_id', $user->id))
            ->byTanggal($tanggal)
            ->orderBy('jam_mulai')
            ->get();
        }

        // Daftar karyawan untuk filter (owner/admin/supervisor)
        $daftarKaryawan = [];
        if (in_array($user->level, [1, 2, 3])) {
            $daftarKaryawan = User::where('status', 'aktif')
                ->whereIn('level', [2, 3, 4, 5, 6])
                ->orderBy('name')
                ->get(['id', 'name', 'jabatan']);
        }

        return view('tugas.index', compact('tugasList', 'tanggal', 'daftarKaryawan'));
    }

    // ----------------------------------------------------------------
    // CREATE — form buat tugas baru
    // ----------------------------------------------------------------
    public function create()
    {
        $karyawan = User::where('status', 'aktif')
            ->whereIn('level', [2, 3, 4, 5, 6])
            ->orderBy('name')
            ->get(['id', 'name', 'jabatan', 'level']);

        return view('tugas.create', compact('karyawan'));
    }

    // ----------------------------------------------------------------
    // STORE — simpan tugas baru + kirim notif WA
    // ----------------------------------------------------------------
    public function store(Request $request)
    {
        $request->validate([
            'judul'               => 'required|string|max:255',
            'tanggal'             => 'required|date',
            'prioritas'           => 'required|in:rendah,sedang,tinggi',
            'karyawan_ids'        => 'required|array|min:1',
            'karyawan_ids.*'      => 'exists:users,id',
            'deskripsi'           => 'nullable|string',
            'jam_mulai'           => 'nullable|date_format:H:i',
            'jam_selesai_target'  => 'nullable|date_format:H:i',
            'lokasi'              => 'nullable|string|max:255',
        ]);

        $tugas = TugasHarian::create([
            'judul'              => $request->judul,
            'deskripsi'          => $request->deskripsi,
            'tanggal'            => $request->tanggal,
            'jam_mulai'          => $request->jam_mulai,
            'jam_selesai_target' => $request->jam_selesai_target,
            'lokasi'             => $request->lokasi,
            'prioritas'          => $request->prioritas,
            'dibuat_oleh'        => Auth::id(),
        ]);

        // Assign ke karyawan + kirim WA
        foreach ($request->karyawan_ids as $userId) {
            TugasAssignee::create([
                'tugas_id' => $tugas->id,
                'user_id'  => $userId,
                'status'   => 'belum',
            ]);

            // Kirim WA notifikasi
            $karyawan = User::find($userId);
            if ($karyawan && $karyawan->no_hp) {
                $this->kirimNotifWA($karyawan, $tugas);

                // Tandai notif terkirim
                TugasAssignee::where('tugas_id', $tugas->id)
                    ->where('user_id', $userId)
                    ->update(['notif_wa_terkirim' => 1]);
            }
        }

        return redirect()->route('tugas.index')
            ->with('success', 'Tugas berhasil dibuat dan notifikasi WA terkirim!');
    }

    // ----------------------------------------------------------------
    // SHOW — detail tugas
    // ----------------------------------------------------------------
    public function show($id)
    {
        $user  = Auth::user();
        $tugas = TugasHarian::with(['pembuat', 'assignees.user'])->findOrFail($id);

        // Karyawan biasa hanya bisa lihat tugas miliknya
        if (!in_array($user->level, [1, 2, 3])) {
            $punya = $tugas->assignees->where('user_id', $user->id)->first();
            if (!$punya) abort(403, 'Akses ditolak.');
        }

        // Assignee milik user yang login (untuk update status)
        $myAssignee = $tugas->assignees->where('user_id', $user->id)->first();

        return view('tugas.show', compact('tugas', 'myAssignee'));
    }

    // ----------------------------------------------------------------
    // EDIT — form edit tugas (owner/admin/supervisor saja)
    // ----------------------------------------------------------------
    public function edit($id)
    {
        $tugas    = TugasHarian::with('assignees')->findOrFail($id);
        $karyawan = User::where('status', 'aktif')
            ->whereIn('level', [2, 3, 4, 5, 6])
            ->orderBy('name')
            ->get(['id', 'name', 'jabatan']);

        $assignedIds = $tugas->assignees->pluck('user_id')->toArray();

        return view('tugas.edit', compact('tugas', 'karyawan', 'assignedIds'));
    }

    // ----------------------------------------------------------------
    // UPDATE — simpan edit tugas
    // ----------------------------------------------------------------
    public function update(Request $request, $id)
    {
        $tugas = TugasHarian::findOrFail($id);

        $request->validate([
            'judul'               => 'required|string|max:255',
            'tanggal'             => 'required|date',
            'prioritas'           => 'required|in:rendah,sedang,tinggi',
            'karyawan_ids'        => 'required|array|min:1',
            'karyawan_ids.*'      => 'exists:users,id',
            'deskripsi'           => 'nullable|string',
            'jam_mulai'           => 'nullable|date_format:H:i',
            'jam_selesai_target'  => 'nullable|date_format:H:i',
            'lokasi'              => 'nullable|string|max:255',
        ]);

        $tugas->update([
            'judul'              => $request->judul,
            'deskripsi'          => $request->deskripsi,
            'tanggal'            => $request->tanggal,
            'jam_mulai'          => $request->jam_mulai,
            'jam_selesai_target' => $request->jam_selesai_target,
            'lokasi'             => $request->lokasi,
            'prioritas'          => $request->prioritas,
        ]);

        // Update assignees — tambah yang baru, jangan hapus yang sudah ada statusnya
        $existingIds = $tugas->assignees->pluck('user_id')->toArray();
        $newIds      = $request->karyawan_ids;

        // Tambah assignee baru
        foreach (array_diff($newIds, $existingIds) as $userId) {
            TugasAssignee::create([
                'tugas_id' => $tugas->id,
                'user_id'  => $userId,
                'status'   => 'belum',
            ]);
            // Kirim WA ke assignee baru
            $karyawan = User::find($userId);
            if ($karyawan && $karyawan->no_hp) {
                $this->kirimNotifWA($karyawan, $tugas);
            }
        }

        // Hapus assignee yang dihapus dari list (hanya yang belum mulai)
        foreach (array_diff($existingIds, $newIds) as $userId) {
            TugasAssignee::where('tugas_id', $tugas->id)
                ->where('user_id', $userId)
                ->where('status', 'belum')
                ->delete();
        }

        return redirect()->route('tugas.show', $tugas->id)
            ->with('success', 'Tugas berhasil diperbarui!');
    }

    // ----------------------------------------------------------------
    // DESTROY — hapus tugas
    // ----------------------------------------------------------------
    public function destroy($id)
    {
        $tugas = TugasHarian::findOrFail($id);
        $tugas->delete();
        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil dihapus.');
    }

    // ----------------------------------------------------------------
    // UPDATE STATUS — karyawan update status tugas mereka
    // ----------------------------------------------------------------
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        $assignee = TugasAssignee::where('tugas_id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'status'            => 'required|in:dikerjakan,selesai,tidak_selesai',
            'catatan_karyawan'  => 'nullable|string|max:500',
        ]);

        $data = [
            'status'           => $request->status,
            'catatan_karyawan' => $request->catatan_karyawan,
        ];

        if ($request->status === 'dikerjakan' && !$assignee->waktu_mulai) {
            $data['waktu_mulai'] = now();
        }
        if (in_array($request->status, ['selesai', 'tidak_selesai'])) {
            $data['waktu_selesai'] = now();
        }

        $assignee->update($data);

        // Notif ke pembuat tugas jika selesai/tidak selesai
        if (in_array($request->status, ['selesai', 'tidak_selesai'])) {
            $tugas    = TugasHarian::with('pembuat')->find($id);
            $pembuat  = $tugas->pembuat;
            if ($pembuat && $pembuat->no_hp) {
                $label  = $request->status === 'selesai' ? '✅ SELESAI' : '❌ TIDAK SELESAI';
                $catatan = $request->catatan_karyawan ? "\nCatatan: {$request->catatan_karyawan}" : '';
                $pesan  = "📋 *Update Tugas*\n\n*{$tugas->judul}*\n\n{$label} oleh {$user->name}{$catatan}\n\nTanggal: " . now()->format('d/m/Y H:i');
                $this->kirimWA($pembuat->no_hp, $pesan);
            }
        }

        return back()->with('success', 'Status tugas berhasil diperbarui!');
    }

    // ----------------------------------------------------------------
    // API — tugas hari ini untuk widget dashboard
    // ----------------------------------------------------------------
    public function tugasHariIni()
    {
        $user = Auth::user();

        $tugas = TugasAssignee::with('tugas')
            ->where('user_id', $user->id)
            ->whereHas('tugas', fn($q) => $q->whereDate('tanggal', today()))
            ->get();

        return response()->json($tugas);
    }

    // ----------------------------------------------------------------
    // PRIVATE — kirim notif WA ke karyawan saat dapat tugas
    // ----------------------------------------------------------------
    private function kirimNotifWA(User $karyawan, TugasHarian $tugas)
    {
        $tanggal = \Carbon\Carbon::parse($tugas->tanggal)->translatedFormat('l, d F Y');
        $jam     = $tugas->jam_mulai ? ' pukul ' . substr($tugas->jam_mulai, 0, 5) : '';
        $lokasi  = $tugas->lokasi ? "\nLokasi: {$tugas->lokasi}" : '';
        $desk    = $tugas->deskripsi ? "\nDetail: {$tugas->deskripsi}" : '';

        $prioritasEmoji = match($tugas->prioritas) {
            'tinggi' => '🔴',
            'sedang' => '🟡',
            'rendah' => '🟢',
            default  => '⚪',
        };

        $pesan = "📋 *TUGAS BARU*\n\nHalo {$karyawan->name}!\n\nKamu mendapat tugas baru:\n\n*{$prioritasEmoji} {$tugas->judul}*\n\nTanggal: {$tanggal}{$jam}{$lokasi}{$desk}\n\nSilakan buka aplikasi untuk update status tugas.\n\n_CanopiBSD v2_";

        $this->kirimWA($karyawan->no_hp, $pesan);
    }

    // ----------------------------------------------------------------
    // PRIVATE — kirim WA via Fonnte
    // ----------------------------------------------------------------
    private function kirimWA(string $noHp, string $pesan): void
    {
        try {
            $token = getenv('FONNTE_TOKEN');
            if (!$token) return;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'target'  => $noHp,
                    'message' => $pesan,
                ],
                CURLOPT_HTTPHEADER => ["Authorization: {$token}"],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // Silent fail — tidak mengganggu flow utama
        }
    }
}