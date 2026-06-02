<?php
namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\IzinAbsen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AbsensiController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $today = today();
        $absenHariIni = Absensi::where('user_id', $user->id)
            ->whereDate('tanggal', $today)->first();

        $riwayat = Absensi::where('user_id', $user->id)
            ->orderBy('tanggal', 'desc')
            ->take(30)->get();

        $bulanIni = Absensi::where('user_id', $user->id)
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->get();

        $stats = [
            'hadir'          => $bulanIni->whereIn('status', ['hadir','telat','setengah_hari','sakit','izin'])->count(),
            'alpha'          => $bulanIni->where('status', 'alpha')->count(),
            'telat'          => $bulanIni->where('status', 'telat')->count(),
            'total_uang_makan' => $bulanIni->sum('uang_makan_hari_ini'),
            'total_gaji'     => $bulanIni->sum('gaji_hari_ini'),
            'total_potongan' => $bulanIni->sum('potongan_telat'),
        ];

        return view('absensi.index', compact('absenHariIni', 'riwayat', 'stats'));
    }

    public function formMasuk()
    {
        $user = auth()->user();
        $sudahAbsen = Absensi::where('user_id', $user->id)
            ->whereDate('tanggal', today())
            ->whereNotNull('jam_masuk')->exists();

        if ($sudahAbsen) {
            return redirect()->route('absensi.index')
                ->with('error', 'Kamu sudah absen masuk hari ini.');
        }

        return view('absensi.form-masuk');
    }

    public function absenMasuk(Request $request)
    {
        $request->validate([
            'foto'      => 'required|string',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = auth()->user();
        $today = today();
        $jamSekarang = now()->format('H:i:s');
        $jamMasukNormal = $user->jam_masuk ?? '07:30:00';

        $fotoPath = $this->simpanFotoBase64($request->foto, 'absensi/masuk');

        $workshopLat = -6.3269;
        $workshopLng = 106.6882;
        $jarak = $this->hitungJarak($request->latitude, $request->longitude, $workshopLat, $workshopLng);

        $status = 'hadir';
        $potongan = 0;
        $jamTelat = 0;

        if ($jamSekarang > $jamMasukNormal) {
            $status = 'telat';
            $menitTelat = Carbon::parse($jamMasukNormal)->diffInMinutes(Carbon::parse($jamSekarang));
            $jamTelat = ceil($menitTelat / 60);
            $potongan = $jamTelat * 20000;
        }

        $uangMakan  = $user->uang_makan ?? 0;
        $gajiHarian = $user->gaji_harian ?? 0;

        Absensi::updateOrCreate(
            ['user_id' => $user->id, 'tanggal' => $today],
            [
                'jam_masuk'          => $jamSekarang,
                'foto_masuk'         => $fotoPath,
                'lat_masuk'          => $request->latitude,
                'lng_masuk'          => $request->longitude,
                'status'             => $status,
                'potongan_telat'     => $potongan,
                'uang_makan_hari_ini'=> $uangMakan,
                'gaji_hari_ini'      => $gajiHarian - $potongan,
            ]
        );

        $pesan = $status === 'telat'
            ? "Absen masuk berhasil. Kamu telat {$jamTelat} jam. Potongan: Rp " . number_format($potongan, 0, ',', '.')
            : "Absen masuk berhasil! Selamat bekerja 💪";

        return redirect()->route('absensi.index')->with('success', $pesan);
    }

    public function formPulang()
    {
        $user = auth()->user();
        $absenHariIni = Absensi::where('user_id', $user->id)
            ->whereDate('tanggal', today())->first();

        if (!$absenHariIni || !$absenHariIni->jam_masuk) {
            return redirect()->route('absensi.index')
                ->with('error', 'Kamu belum absen masuk hari ini.');
        }

        if ($absenHariIni->jam_pulang) {
            return redirect()->route('absensi.index')
                ->with('error', 'Kamu sudah absen pulang hari ini.');
        }

        return view('absensi.form-pulang', compact('absenHariIni'));
    }

    public function absenPulang(Request $request)
    {
        $request->validate([
            'foto'      => 'required|string',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = auth()->user();
        $jamSekarang = now()->format('H:i:s');
        $jamPulangNormal = $user->jam_pulang ?? '17:00:00';

        $fotoPath = $this->simpanFotoBase64($request->foto, 'absensi/pulang');

        $absensi = Absensi::where('user_id', $user->id)
            ->whereDate('tanggal', today())->first();

        $menitKerja    = Carbon::parse($absensi->jam_masuk)->diffInMinutes(Carbon::parse($jamSekarang));
        $jamKerja      = $menitKerja / 60;
        $totalJamKerja = Carbon::parse($absensi->jam_masuk)->diffInHours(Carbon::parse($jamPulangNormal));

        $status    = $absensi->status;
        $uangMakan = $absensi->uang_makan_hari_ini;
        $gaji      = $absensi->gaji_hari_ini;

        if ($jamKerja < ($totalJamKerja / 2) && $request->alasan_pulang_awal) {
            $status    = 'setengah_hari';
            $uangMakan = $uangMakan / 2;
            $gaji      = $gaji / 2;
        }

        $absensi->update([
            'jam_pulang'          => $jamSekarang,
            'foto_pulang'         => $fotoPath,
            'lat_pulang'          => $request->latitude,
            'lng_pulang'          => $request->longitude,
            'status'              => $status,
            'uang_makan_hari_ini' => $uangMakan,
            'gaji_hari_ini'       => $gaji,
            'keterangan'          => $request->alasan_pulang_awal,
        ]);

        return redirect()->route('absensi.index')
            ->with('success', 'Absen pulang berhasil! Selamat istirahat 🏠');
    }

    public function rekap(Request $request)
    {
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $absensi = Absensi::with('user')
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderBy('tanggal', 'desc')
            ->paginate(30);

        return view('absensi.rekap', compact('absensi', 'bulan', 'tahun'));
    }

    public function koreksi(Request $request, Absensi $absensi)
    {
        $request->validate([
            'status'         => 'required|in:hadir,telat,setengah_hari,sakit,izin,diliburkan,alpha',
            'alasan_koreksi' => 'required|string',
        ]);

        $absensi->update([
            'status'         => $request->status,
            'alasan_koreksi' => $request->alasan_koreksi,
            'dikoreksi'      => true,
            'dikoreksi_oleh' => auth()->id(),
        ]);

        return back()->with('success', 'Absensi berhasil dikoreksi.');
    }

    private function simpanFotoBase64(string $base64, string $folder): string
    {
        $data      = explode(',', $base64);
        $imageData = base64_decode($data[1] ?? $data[0]);
        $filename  = $folder . '/' . auth()->id() . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $imageData);
        return $filename;
    }

    private function hitungJarak(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLng/2) * sin($dLng/2);
        return $r * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}
