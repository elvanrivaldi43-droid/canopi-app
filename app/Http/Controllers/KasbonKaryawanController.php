<?php
// FILE: app/Http/Controllers/KasbonKaryawanController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Kasbon;
use App\Models\User;

class KasbonKaryawanController extends Controller
{
    const BATAS_AMAN    = 500000;
    const MAX_KASBON    = 2;       // maks 2 kasbon aktif bersamaan
    const MAX_CICILAN   = 12;
    const MAX_NOMINAL   = 3;       // maks 3x gaji bulanan

    public function index()
    {
        $user = Auth::user();

        // Kasbon aktif (maks 2)
        $kasbonAktif = Kasbon::where('user_id', $user->id)
                             ->whereIn('status', ['aktif','pending','ditunda'])
                             ->orderBy('created_at', 'desc')
                             ->get();

        // Semua riwayat
        $semuaKasbon = Kasbon::where('user_id', $user->id)
                             ->orderBy('created_at', 'desc')
                             ->get();

        // Hitung masa kerja
        $tanggalBergabung = $user->tanggal_bergabung
            ? \Carbon\Carbon::parse($user->tanggal_bergabung)
            : \Carbon\Carbon::parse($user->created_at);
        $masaKerjaBulan = (int)$tanggalBergabung->diffInMonths(now());

        // Gaji bulanan estimasi
        $gajiBulanan       = ($user->gaji_harian ?? 0) * 26;
        $totalCicilanAktif = Kasbon::totalCicilanAktif($user->id);
        $maxKasbon         = $gajiBulanan * self::MAX_NOMINAL;

        // Syarat
        $jumlahKasbonAktif = $kasbonAktif->count();
        $syarat = [
            'masa_kerja'          => $masaKerjaBulan >= 12,
            'masa_kerja_bulan'    => $masaKerjaBulan,
            'slot_tersedia'       => $jumlahKasbonAktif < self::MAX_KASBON,
            'kasbon_aktif_count'  => $jumlahKasbonAktif,
            'bukan_sp3'           => ($user->status_sp ?? 'aktif') !== 'sp3',
            'gaji_aman'           => ($gajiBulanan - $totalCicilanAktif) > self::BATAS_AMAN,
        ];

        $bisaAjukan = $syarat['masa_kerja']
                   && $syarat['slot_tersedia']
                   && $syarat['bukan_sp3']
                   && $syarat['gaji_aman'];

        return view('kasbon.saya', compact(
            'user','kasbonAktif','semuaKasbon','syarat',
            'bisaAjukan','maxKasbon','totalCicilanAktif'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'kategori'        => 'required|in:kebutuhan_pribadi,kesehatan,pendidikan,renovasi_rumah,lainnya',
            'nominal'         => 'required|numeric|min:50000',
            'jumlah_cicilan'  => 'required|integer|min:1|max:12',
            'keterangan'      => 'required|string|max:500',
            'ttd_digital'     => 'required|string',
        ], [
            'ttd_digital.required' => 'Tanda tangan wajib diisi.',
        ]);

        // Validasi syarat
        $tanggalBergabung = $user->tanggal_bergabung
            ? \Carbon\Carbon::parse($user->tanggal_bergabung)
            : \Carbon\Carbon::parse($user->created_at);
        $masaKerjaBulan = (int)$tanggalBergabung->diffInMonths(now());

        if ($masaKerjaBulan < 12) {
            return back()->with('error', "Masa kerja belum 1 tahun ({$masaKerjaBulan} bulan).");
        }

        $jumlahAktif = Kasbon::jumlahAktif($user->id);
        if ($jumlahAktif >= self::MAX_KASBON) {
            return back()->with('error', 'Kamu sudah memiliki 2 kasbon aktif. Lunasi salah satu terlebih dahulu.');
        }

        if (($user->status_sp ?? 'aktif') === 'sp3') {
            return back()->with('error', 'Status SP3 tidak diizinkan mengajukan kasbon.');
        }

        $gajiBulanan = ($user->gaji_harian ?? 0) * 26;
        $maxKasbon   = $gajiBulanan * self::MAX_NOMINAL;

        if ($request->nominal > $maxKasbon) {
            return back()->with('error', 'Nominal melebihi batas maksimal (3x gaji = Rp ' . number_format($maxKasbon,0,',','.') . ').');
        }

        $cicilanPerBulan   = (int)ceil($request->nominal / $request->jumlah_cicilan);
        $totalCicilanAktif = Kasbon::totalCicilanAktif($user->id);
        $gajiBersih        = $gajiBulanan - $totalCicilanAktif - $cicilanPerBulan;

        // Warning jika gaji bersih < batas aman — tetap proses tapi warning ke owner
        $isWarning = $gajiBersih < self::BATAS_AMAN;

        $kasbon = Kasbon::create([
            'user_id'           => $user->id,
            'tanggal'           => today(),
            'nominal'           => $request->nominal,
            'keterangan'        => $request->keterangan,
            'kategori'          => $request->kategori,
            'kategori_lainnya'  => $request->kategori_lainnya,
            'cicilan_per_bulan' => $cicilanPerBulan,
            'jumlah_cicilan'    => $request->jumlah_cicilan,
            'sisa_kasbon'       => $request->nominal,
            'status'            => 'pending',
            'ttd_digital'       => $request->ttd_digital,
            'ttd_tanggal'       => now(),
        ]);

        // Notif WA ke owner
        $owner = User::where('level', 1)->first();
        if ($owner?->no_hp) {
            $warningText = $isWarning ? "\n⚠️ PERHATIAN: Gaji bersih akan < Rp 500.000 setelah cicilan!" : '';
            $this->kirimWA($owner->no_hp,
                "💳 *PENGAJUAN KASBON*\n" .
                "Dari: {$user->name} ({$user->jabatan})\n" .
                "Kategori: {$kasbon->kategoriLabel()}\n" .
                "Nominal: Rp " . number_format($request->nominal,0,',','.') . "\n" .
                "Cicilan: {$request->jumlah_cicilan}x Rp " . number_format($cicilanPerBulan,0,',','.') . "/bulan\n" .
                "Alasan: {$request->keterangan}" .
                $warningText . "\n" .
                "---\n" .
                "Approve/Tolak di: app.kanopibsd.co.id/penggajian/kasbon"
            );
        }

        return back()->with('success', 'Pengajuan kasbon berhasil dikirim! Menunggu persetujuan owner.');
    }

    // Download surat pernyataan
    public function surat(Kasbon $kasbon)
    {
        // Pastikan hanya pemilik yang bisa akses
        if ($kasbon->user_id !== Auth::id()) abort(403);

        $user = $kasbon->user;
        $html = "
        <!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>
          body { font-family: Arial, sans-serif; padding: 40px; color: #000; }
          h2 { text-align:center; margin-bottom:4px; }
          .sub { text-align:center; color:#666; margin-bottom:30px; }
          table { width:100%; border-collapse:collapse; margin-bottom:20px; }
          td { padding:8px; border-bottom:1px solid #eee; }
          td:first-child { color:#666; width:40%; }
          .ttd-section { margin-top:30px; }
          .ttd-img { border:1px solid #ccc; padding:10px; border-radius:4px; }
        </style>
        </head><body>
        <h2>SURAT PERNYATAAN KASBON</h2>
        <div class='sub'>Pusat Kanopi BSD — CanopiBSD System</div>
        <table>
          <tr><td>Nama</td><td><strong>{$user->name}</strong></td></tr>
          <tr><td>Jabatan</td><td>{$user->jabatan}</td></tr>
          <tr><td>Tanggal Pengajuan</td><td>{$kasbon->tanggal->format('d/m/Y')}</td></tr>
          <tr><td>Kategori</td><td>{$kasbon->kategoriLabel()}</td></tr>
          <tr><td>Nominal Kasbon</td><td>Rp " . number_format($kasbon->nominal,0,',','.') . "</td></tr>
          <tr><td>Jumlah Cicilan</td><td>{$kasbon->jumlah_cicilan} bulan</td></tr>
          <tr><td>Cicilan per Bulan</td><td>Rp " . number_format($kasbon->cicilan_per_bulan,0,',','.') . "</td></tr>
          <tr><td>Keterangan</td><td>{$kasbon->keterangan}</td></tr>
        </table>
        <p>Saya yang bertanda tangan di bawah ini menyatakan bahwa:</p>
        <ol>
          <li>Saya mengajukan kasbon sesuai kebutuhan yang saya sebutkan</li>
          <li>Saya bersedia dipotong cicilan setiap bulan dari gaji</li>
          <li>Jika saya resign, sisa kasbon akan dipotong dari gaji terakhir</li>
          <li>Saya bertanggung jawab atas kasbon ini sepenuhnya</li>
        </ol>
        <div class='ttd-section'>
          <p>Tanda Tangan:</p>
          <img src='{$kasbon->ttd_digital}' class='ttd-img' width='200'>
          <p><small>Ditandatangani digital pada: {$kasbon->ttd_tanggal?->format('d/m/Y H:i')}</small></p>
        </div>
        </body></html>";

        return response($html)->header('Content-Type', 'text/html');
    }

    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;
        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}