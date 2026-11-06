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
    // Tanggal kerja yang direncanakan: sebelum 06:00 = hari ini, sejak 06:00 = besok
    // (sama dgn cron-penugasan & batas jawab; jangan pakai today()+1 mentah).
    private function tanggalKerja(): Carbon
    {
        return Carbon::parse(PenugasanAturan::tanggalKerja(now()));
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

    public static function tahapAktif()
    {
        return DB::table('project_tahap as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->whereIn('p.status', ['persiapan', 'pengerjaan'])
            ->whereIn('t.status', ['belum', 'sedang'])
            ->orderBy('p.id')->orderBy('t.urutan')
            ->get(['t.id', 't.project_id', 't.nama_tahap', 't.status', 'p.nama_project', 'p.nama_customer']);
    }

    public static function namaProject($row): string
    {
        return $row->nama_project ?: ($row->nama_customer ?: 'Project #' . $row->project_id);
    }

    public function rencana()
    {
        if (!$this->bolehAkses()) abort(403);

        $tgl     = $this->tanggalKerja();
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
        // Isian awal disalin dari hari kerja sebelumnya (bukan today(): sebelum 06:00 tanggal kerja = hari ini)
        $hariIni = DB::table('penugasan_harian')->where('tanggal', $tgl->copy()->subDay()->toDateString())->get()->groupBy('user_id');

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

        $tgl      = $this->tanggalKerja();
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
        $ulang = []; // user_id => true bila jawaban lama (non-null) ikut direset
        $jawabLamaMandor = null;
        DB::transaction(function () use ($users, $baru, $lama, $tglStr, $now, $mandorId, $mandorTidakMasuk, &$kirim, &$ulang, &$jawabLamaMandor) {
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
                    $lamaJawab = $jawabLamaMandor = DB::table('konfirmasi_hadir')->where($kunci)->value('jawaban');
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
                    $ulang[$u->id] = DB::table('konfirmasi_hadir')->where($kunci)->value('jawaban') !== null;
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
        $kataHari = PenugasanAturan::kataHari($tglStr, now());
        foreach ($kirim as $uid => $tugas) {
            $u = $users->firstWhere('id', $uid);
            $kid = DB::table('konfirmasi_hadir')->where(['tanggal' => $tglStr, 'user_id' => $uid])->value('id');
            $pesan = PenugasanAturan::pesanTanya($kataHari, $tgl->translatedFormat('d F Y'), array_map($tahapLabel, $tugas), $ulang[$uid] ?? false);
            $ok = $telegram->kirimTombol($u->telegram_chat_id, $pesan, PenugasanAturan::tombolHadir((int) $kid));
            if (!$ok) $gagal[] = $u->name . ($u->telegram_chat_id ? '' : ' (belum sambung Telegram)');
        }

        if (PenugasanAturan::perluKabariMandorTidakMasuk($mandorTidakMasuk, $jawabLamaMandor)) {
            $this->kabariOwner("Mandor Bertugas " . auth()->user()->name . " menyatakan TIDAK MASUK " . strtolower($kataHari) . " " . $tgl->translatedFormat('d F Y') . ". Mohon tunjuk pengganti.");
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
            ->where('tanggal', $this->tanggalKerja()->toDateString())->where('user_id', (int) $userId)
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
        // Mandor baru yang sudah ada di rencana sbg pekerja (belum menjawab) kini "otomatis hadir":
        // catat hadir supaya tetap dapat kode. Jawaban yang sudah ada TIDAK ditimpa.
        if ($id) {
            DB::table('konfirmasi_hadir')
                ->where('tanggal', $this->tanggalKerja()->toDateString())->where('user_id', (int) $id)
                ->whereNull('jawaban')
                ->update(['jawaban' => 'hadir', 'dijawab_at' => now(), 'updated_at' => now()]);
        }
        return back()->with('success', 'Mandor Bertugas diperbarui.');
    }

    // Tugas hari ini milik user (untuk form absen pulang), plus nama project/tahap.
    public static function tugasHariIni(int $userId)
    {
        return DB::table('penugasan_harian as h')
            ->join('project_tahap as t', 't.id', '=', 'h.project_tahap_id')
            ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
            ->where('h.tanggal', today()->toDateString())->where('h.user_id', $userId)
            ->orderBy('h.id')
            ->get(['h.id', 'h.project_tahap_id', 'h.porsi', 'h.laporan', 'h.catatan', 't.nama_tahap', 't.project_id', 'p.nama_project', 'p.nama_customer']);
    }

    // Laporan kerja sore (dari form absen pulang). Hanya baris milik sendiri, hari ini.
    // TIDAK terkait absen pulang: gagal lapor tak pernah menghalangi pulang.
    public function laporan(Request $request)
    {
        $request->validate([
            'tugas'                    => 'required|array|min:1',
            'tugas.*.id'               => 'required|integer',
            'tugas.*.laporan'          => 'required|in:selesai,lanjut',
            'tugas.*.project_tahap_id' => 'required|integer',
            'tugas.*.porsi'            => 'required|in:penuh,setengah',
            'tugas.*.catatan'          => 'nullable|string|max:1000',
        ]);

        $hariIni = today()->toDateString();
        $milik = DB::table('penugasan_harian')->where('tanggal', $hariIni)->where('user_id', auth()->id())
            ->get()->keyBy('id');
        $masuk = collect($request->input('tugas'))->keyBy(fn($t) => (int) $t['id']);

        foreach ($masuk->keys() as $id) {
            if (!isset($milik[$id])) {
                return response()->json(['success' => false, 'message' => 'Tugas tidak ditemukan untuk hari ini.'], 422);
            }
        }

        // Porsi akhir semua tugas hari ini (yang tak dikirim tetap porsi lamanya)
        $porsi = $milik->map(fn($r) => $masuk[$r->id]['porsi'] ?? $r->porsi)->values()->all();
        if ($alasan = PenugasanAturan::alasanTolakTugasOrang($porsi)) {
            return response()->json(['success' => false, 'message' => "$alasan dalam sehari. Periksa pilihan Penuh / Setengah."], 422);
        }

        $aktif = self::tahapAktif()->keyBy('id');
        foreach ($masuk as $id => $t) {
            $tid = (int) $t['project_tahap_id'];
            if ($tid !== (int) $milik[$id]->project_tahap_id && !isset($aktif[$tid])) {
                return response()->json(['success' => false, 'message' => 'Tahap pilihan tidak valid atau sudah selesai.'], 422);
            }
        }

        DB::transaction(function () use ($masuk, $hariIni) {
            $now = now();
            foreach ($masuk as $id => $t) {
                $tid = (int) $t['project_tahap_id'];
                DB::table('penugasan_harian')->where('id', $id)->update([
                    'laporan' => $t['laporan'], 'catatan' => $t['catatan'] ?? null, 'dilaporkan_at' => $now,
                    'project_tahap_id' => $tid, 'porsi' => $t['porsi'], 'updated_at' => $now,
                ]);

                $tahap = DB::table('project_tahap')->where('id', $tid)->lockForUpdate()->first();
                $status = $tahap ? PenugasanAturan::statusTahapDariLaporan($tahap->status, $t['laporan']) : null;
                if (!$status) continue;
                $ubah = ['status' => $status, 'updated_at' => $now];
                if (!$tahap->tanggal_mulai_aktual) $ubah['tanggal_mulai_aktual'] = $hariIni;
                if ($status === 'selesai') $ubah['tanggal_selesai_aktual'] = $hariIni;
                DB::table('project_tahap')->where('id', $tid)->update($ubah);
            }
        });

        return response()->json(['success' => true, 'message' => 'Laporan kerja tersimpan.']);
    }

    private function kabariOwner(string $pesan): void
    {
        app(\App\Services\TelegramService::class)->kirimOwner($pesan);
    }
}
