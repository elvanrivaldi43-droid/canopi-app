<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LiburService;
use App\Services\PenugasanAturan;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Penugasan Harian: Mandor Bertugas (atau Owner) menyusun rencana kerja BESOK;
// tiap pekerja dikirimi tombol Hadir / Tidak Hadir lewat Telegram.
class PenugasanController extends Controller
{
    private function besok(): Carbon
    {
        return today()->addDay();
    }

    private function mandorBertugasId(): ?int
    {
        $id = DB::table('setting_penugasan')->where('id', 1)->value('mandor_bertugas_id');
        return $id ? (int) $id : null;
    }

    private function bolehAkses(): bool
    {
        $u = auth()->user();
        return $u->level == 1 || ($this->mandorBertugasId() !== null && $u->id === $this->mandorBertugasId());
    }

    // Karyawan aktif yang ikut penugasan.
    private function pekerja()
    {
        return User::where('status', 'aktif')
            ->whereIn('level', PenugasanAturan::LEVEL_IKUT)
            ->orderBy('name')->get();
    }

    // [user_id => 'Izin'|'Libur'] untuk tanggal itu.
    private function halangan($users, Carbon $tgl): array
    {
        $izin = DB::table('izin_absen')->where('status', 'approved')
            ->whereDate('tanggal', $tgl->toDateString())->pluck('tipe', 'user_id')->all();
        $label = ['dinas_luar' => 'Dinas luar', 'sakit' => 'Sakit', 'cuti' => 'Cuti'];
        $libur = app(LiburService::class);
        $out = [];
        foreach ($users as $u) {
            if (isset($izin[$u->id])) {
                $out[$u->id] = $label[$izin[$u->id]] ?? 'Izin';
            } elseif ($libur->isLibur($u, $tgl)) {
                $out[$u->id] = 'Libur';
            }
        }
        return $out;
    }

    private function tahapAktif()
    {
        return DB::table('project_tahap as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->whereIn('p.status', ['persiapan', 'pengerjaan'])
            ->whereIn('t.status', ['belum', 'sedang'])
            ->orderBy('p.id')->orderBy('t.urutan')
            ->get(['t.id', 't.project_id', 't.nama_tahap', 't.status', 'p.nama_project', 'p.nama_customer']);
    }

    private function namaProject($row): string
    {
        return $row->nama_project ?: ($row->nama_customer ?: 'Project #' . $row->project_id);
    }

    public function rencana()
    {
        if (!$this->bolehAkses()) abort(403);

        $tgl     = $this->besok();
        $users   = $this->pekerja();
        $tahap   = $this->tahapAktif();
        $mandorId = $this->mandorBertugasId();

        // Label grup: "sedang X, tinggal Y, Z"
        $grup = [];
        foreach ($tahap->groupBy('project_id') as $pid => $rows) {
            $sedang = $rows->where('status', 'sedang')->pluck('nama_tahap')->all();
            $sisa   = $rows->where('status', 'belum')->pluck('nama_tahap')->all();
            $label  = $this->namaProject($rows->first());
            $label .= ' — ' . ($sedang ? 'sedang ' . implode(', ', $sedang) : 'belum ada tahap berjalan');
            if ($sisa) $label .= ', tinggal ' . implode(', ', $sisa);
            $grup[] = ['label' => $label, 'tahap' => $rows->values()];
        }
        $tahapIds = $tahap->pluck('id')->all();

        $besok = DB::table('penugasan_harian')->where('tanggal', $tgl->toDateString())->get()->groupBy('user_id');
        $hariIni = DB::table('penugasan_harian')->where('tanggal', today()->toDateString())->get()->groupBy('user_id');

        $isi = [];        // user_id => [['tahap'=>,'porsi'=>], ...]
        $dariHariIni = []; // user_id => true bila isian disalin dari hari ini
        foreach ($users as $u) {
            $rows = $besok->get($u->id);
            if (!$rows || $rows->isEmpty()) {
                $rows = ($hariIni->get($u->id) ?? collect())->filter(fn($r) => in_array($r->project_tahap_id, $tahapIds));
                if ($rows->isNotEmpty()) $dariHariIni[$u->id] = true;
            }
            $isi[$u->id] = $rows->take(2)->map(fn($r) => ['tahap' => $r->project_tahap_id, 'porsi' => $r->porsi])->values()->all();
        }

        $konfirmasi = DB::table('konfirmasi_hadir')->where('tanggal', $tgl->toDateString())->get()->keyBy('user_id');

        return view('penugasan.rencana', [
            'tanggal'     => $tgl,
            'users'       => $users,
            'halangan'    => $this->halangan($users, $tgl),
            'grup'        => $grup,
            'isi'         => $isi,
            'dariHariIni' => $dariHariIni,
            'konfirmasi'  => $konfirmasi,
            'mandorId'    => $mandorId,
            'daftarMandor' => auth()->user()->level == 1
                ? User::where('status', 'aktif')->where('level', 3)->orderBy('name')->get() : collect(),
            'tersimpan'   => DB::table('rencana_harian')->where('tanggal', $tgl->toDateString())->first(),
        ]);
    }

    public function simpan(Request $request)
    {
        if (!$this->bolehAkses()) abort(403);

        $tgl      = $this->besok();
        $tglStr   = $tgl->toDateString();
        $users    = $this->pekerja();
        $halangan = $this->halangan($users, $tgl);
        $mandorId = $this->mandorBertugasId();
        $tahap    = $this->tahapAktif()->keyBy('id');
        $input    = $request->input('tugas', []);

        // 1) Validasi + susun tugas baru per user
        $baru = [];
        $galat = [];
        foreach ($users as $u) {
            $list = [];
            foreach (array_slice((array) ($input[$u->id] ?? []), 0, 2) as $baris) {
                $tid   = (int) ($baris['tahap'] ?? 0);
                $porsi = (string) ($baris['porsi'] ?? '');
                if (!$tid) continue; // baris kosong
                if (!isset($tahap[$tid])) { $galat[] = "{$u->name}: tahap tidak valid / sudah selesai."; continue; }
                if (!in_array($porsi, ['penuh', 'setengah'], true)) { $galat[] = "{$u->name}: porsi tidak valid."; continue; }
                $list[] = ['project_tahap_id' => $tid, 'porsi' => $porsi];
            }
            if ($list && isset($halangan[$u->id])) {
                $galat[] = "{$u->name}: besok {$halangan[$u->id]}, tidak bisa diberi tugas.";
                continue;
            }
            if ($alasan = PenugasanAturan::alasanTolakTugasOrang(array_column($list, 'porsi'))) {
                $galat[] = "{$u->name}: $alasan.";
                continue;
            }
            $baru[$u->id] = $list;
        }
        if ($galat) {
            return back()->withInput()->withErrors($galat);
        }

        $lama = DB::table('penugasan_harian')->where('tanggal', $tglStr)->get()->groupBy('user_id')
            ->map(fn($rows) => $rows->map(fn($r) => ['project_tahap_id' => (int) $r->project_tahap_id, 'porsi' => $r->porsi])->all());
        $mandorTidakMasuk = $mandorId && $request->boolean('mandor_tidak_masuk') && auth()->id() === $mandorId;
        $now = now();

        // 2) Simpan (transaksi): tugas, konfirmasi, rencana
        $kirim = []; // user_id => list tugas yang perlu ditanyakan
        DB::transaction(function () use ($users, $baru, $lama, $tglStr, $now, $mandorId, $mandorTidakMasuk, &$kirim) {
            foreach ($users as $u) {
                $tugas = $baru[$u->id];
                $tugasLama = $lama->get($u->id, []);

                DB::table('penugasan_harian')->where('tanggal', $tglStr)->where('user_id', $u->id)->delete();
                foreach ($tugas as $t) {
                    DB::table('penugasan_harian')->insert($t + [
                        'tanggal' => $tglStr, 'user_id' => $u->id, 'dibuat_oleh' => auth()->id(),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                $kunci = ['tanggal' => $tglStr, 'user_id' => $u->id];
                if ($u->id === $mandorId) {
                    // Mandor Bertugas otomatis hadir; 'tidak masuk' hanya diubah oleh dirinya sendiri
                    $lamaJawab = DB::table('konfirmasi_hadir')->where($kunci)->value('jawaban');
                    $aksi = PenugasanAturan::statusMandor(auth()->id() === $mandorId, $mandorTidakMasuk, $lamaJawab, (bool) $tugas);
                    if ($aksi === 'hapus') {
                        DB::table('konfirmasi_hadir')->where($kunci)->delete();
                    } elseif ($aksi !== 'biarkan') {
                        DB::table('konfirmasi_hadir')->updateOrInsert($kunci, [
                            'jawaban' => $aksi, 'dijawab_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                } elseif (!$tugas) {
                    // keluar dari rencana -> kembali ke "tak ada di rencana" (kode normal)
                    DB::table('konfirmasi_hadir')->where($kunci)->delete();
                } elseif (!$tugasLama || PenugasanAturan::perluTanyaUlang($tugasLama, $tugas)) {
                    DB::table('konfirmasi_hadir')->updateOrInsert($kunci, [
                        'jawaban' => null, 'dijawab_at' => null, 'pengingat' => null,
                        'dikirim_at' => $now, 'updated_at' => $now,
                    ]);
                    $kirim[$u->id] = $tugas;
                }
            }
            DB::table('rencana_harian')->updateOrInsert(['tanggal' => $tglStr], [
                'disimpan_oleh' => auth()->id(), 'disimpan_at' => $now, 'updated_at' => $now,
            ]);
        });

        // 3) Telegram SETELAH commit; gagal kirim tidak membatalkan rencana
        $tahapLabel = fn($t) => $this->namaProject($tahap[$t['project_tahap_id']]) . ' - ' . $tahap[$t['project_tahap_id']]->nama_tahap
            . ' (' . ($t['porsi'] === 'penuh' ? 'penuh' : 'setengah hari') . ')';
        $gagal = [];
        $telegram = new TelegramService();
        foreach ($kirim as $uid => $tugas) {
            $u = $users->firstWhere('id', $uid);
            $kid = DB::table('konfirmasi_hadir')->where(['tanggal' => $tglStr, 'user_id' => $uid])->value('id');
            $pesan = 'Besok ' . $tgl->translatedFormat('d F Y') . ' kamu di ' . implode(' + ', array_map($tahapLabel, $tugas)) . '. Bersedia hadir?';
            $ok = $telegram->kirimTombol($u->telegram_chat_id, $pesan, [[
                ['text' => 'Hadir', 'callback_data' => "hadir:$kid"],
                ['text' => 'Tidak Hadir', 'callback_data' => "tidak:$kid"],
            ]]);
            if (!$ok) $gagal[] = $u->name . ($u->telegram_chat_id ? '' : ' (belum sambung Telegram)');
        }

        if ($mandorTidakMasuk) {
            $this->kabariOwner("Mandor Bertugas " . auth()->user()->name . " menyatakan TIDAK MASUK besok " . $tgl->translatedFormat('d F Y') . ". Mohon tunjuk pengganti.");
        }

        $resp = redirect()->route('penugasan.rencana')->with('success', 'Rencana besok tersimpan.');
        if ($gagal) {
            $resp->with('warning', 'Konfirmasi belum terkirim ke: ' . implode(', ', $gagal) . '. Konfirmasikan manual (tombol "Tandai Hadir" di barisnya).');
        }
        return $resp;
    }

    // Mandor/Owner menandai Hadir untuk pekerja yang belum sambung Telegram.
    public function tandaiHadir($userId)
    {
        if (!$this->bolehAkses()) abort(403);

        $n = DB::table('konfirmasi_hadir')
            ->where('tanggal', $this->besok()->toDateString())->where('user_id', (int) $userId)
            ->whereNull('jawaban')
            ->update(['jawaban' => 'hadir', 'dijawab_at' => now(), 'updated_at' => now()]);

        return redirect()->route('penugasan.rencana')
            ->with($n ? 'success' : 'warning', $n ? 'Ditandai hadir.' : 'Tidak ada konfirmasi menunggu untuk karyawan itu.');
    }

    public function mandorBertugas(Request $request)
    {
        $request->validate(['mandor_id' => 'nullable|integer']);
        $id = $request->input('mandor_id');
        if ($id && !User::where('id', $id)->where('level', 3)->where('status', 'aktif')->exists()) {
            return back()->withErrors(['Mandor tidak valid (harus level Supervisor/Mandor yang aktif).']);
        }
        DB::table('setting_penugasan')->where('id', 1)->update(['mandor_bertugas_id' => $id ?: null, 'updated_at' => now()]);
        return back()->with('success', 'Mandor Bertugas diperbarui.');
    }

    private function kabariOwner(string $pesan): void
    {
        try {
            $token = getenv('TELEGRAM_OWNER_TOKEN');
            $chatId = getenv('TELEGRAM_OWNER_CHAT_ID');
            if (!$token || !$chatId) return;
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['chat_id' => $chatId, 'text' => $pesan], CURLOPT_TIMEOUT => 20,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Telegram owner penugasan gagal: ' . $e->getMessage());
        }
    }
}
