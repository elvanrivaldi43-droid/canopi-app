<?php
// FILE: app/Http/Controllers/AbsensiController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Absensi;
use App\Models\User;
use App\Models\LuarKota;

class AbsensiController extends Controller
{
    const LOKASI = [
        'workshop' => ['lat' => -6.326891466682671, 'lng' => 106.68817860852242],
        'kantor'   => ['lat' => -6.377479957005854, 'lng' => 106.68087679503195],
    ];

    const RADIUS_MASUK_PULANG = 100;
    const RADIUS_SIANG        = 200;
    const LEVEL_WORKSHOP      = [3, 5, 6];
    const LEVEL_KANTOR        = [2, 4, 7];
    const JAM_BUKA_ABSEN      = '06:30';
    const JAM_MASUK           = '07:00';
    const JAM_SETENGAH        = '10:00';
    const JAM_MASUK_SIANG     = '13:00';
    const JAM_SKIP_SIANG      = '14:00';
    const JAM_PULANG          = '16:30';
    const JAM_LEMBUR          = '17:00';
    const TOLERANSI_SIANG     = 3;
    const POTONGAN_TELAT      = 20000;
    const LEMBUR_MAX_JAM      = 5;

    const STATUS_PEKERJAAN = [
        'normal'   => '🟢 Berjalan normal sesuai rencana',
        'lambat'   => '🟡 Sedikit terlambat dari target',
        'terhenti' => '🔴 Terhenti — ada kendala',
    ];

    const JENIS_KENDALA = [
        'cuaca'     => '⛈️ Cuaca (hujan/angin)',
        'material'  => '🔩 Material kurang/tidak sesuai',
        'peralatan' => '🔧 Peralatan bermasalah',
        'tenaga'    => '👤 Kekurangan tenaga',
        'teknis'    => '📐 Masalah teknis pemasangan',
        'customer'  => '🏠 Kendala dari pihak customer',
        'lainnya'   => '📝 Lainnya',
    ];

    public function index()
    {
        $user         = Auth::user();
        $absenHariIni = Absensi::where('user_id', $user->id)->whereDate('tanggal', today())->first();

        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_absen_siang
            && !$absenHariIni->potongan_siang_dicatat
            && now()->format('H:i') >= self::JAM_SKIP_SIANG) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'         => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'          => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_siang_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }

        $riwayat  = Absensi::where('user_id', $user->id)->orderBy('tanggal','desc')->limit(30)->get();
        $bulanIni = Absensi::where('user_id', $user->id)
                           ->whereMonth('tanggal', now()->month)
                           ->whereYear('tanggal', now()->year)->get();

        $stats = [
            'hadir'          => $bulanIni->whereIn('status',['hadir','telat','setengah_hari'])->count(),
            'alpha'          => $bulanIni->where('status','alpha')->count(),
            'telat'          => $bulanIni->where('status','telat')->count(),
            'total_um'       => $bulanIni->sum('uang_makan_hari_ini'),
            'total_potongan' => $bulanIni->sum('potongan_telat'),
            'total_gaji'     => $bulanIni->sum('gaji_hari_ini'),
        ];

        $fase          = $this->getFaseAbsen($absenHariIni);
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.index', compact('user','absenHariIni','riwayat','stats','fase','luarKotaAktif'));
    }

    public function formMasuk()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();

        if ($absen?->jam_masuk) return redirect()->route('absensi.index')->with('info','Kamu sudah absen masuk hari ini.');
        if (now()->format('H:i') < self::JAM_BUKA_ABSEN) return redirect()->route('absensi.index')->with('error','Absen masuk baru bisa mulai jam 06:30');

        $lokasi        = $this->getLokasiUser($user->level);
        $setengahHari  = now()->format('H:i') >= self::JAM_SETENGAH;
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.form-masuk', compact('user','lokasi','setengahHari','luarKotaAktif'));
    }

    public function absenMasuk(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'foto' => 'required|string',
            'lat'  => 'required|numeric',
            'lng'  => 'required|numeric',
            'kode' => 'required|string',
        ]);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        // Validasi GPS — skip kalau luar kota
        $lokasi = $this->getLokasiUser($user->level);
        $jarak  = $this->hitungJarak($request->lat, $request->lng, $lokasi['lat'], $lokasi['lng']);
        if ($jarak > self::RADIUS_MASUK_PULANG && !$sedangLuarKota) {
            return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)}). Pastikan kamu sudah berada di lokasi kerja."]);
        }

        // Validasi kode absen (tetap wajib walau luar kota)
        $kode      = strtoupper(trim($request->kode));
        $kodeValid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                          ->where('kode', $kode)
                                          ->exists();
        if (!$kodeValid) {
            return response()->json(['success'=>false,'message'=>'❌ Kode absen salah! Cek kode di WhatsApp kamu.']);
        }

        $fotoPath     = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        $jamSekarang  = now()->format('H:i');
        $setengahHari = $jamSekarang >= self::JAM_SETENGAH;
        $menitTelat   = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK);

        if ($setengahHari) {
            $potongan    = 0;
            $status      = 'setengah_hari';
            $gajiHariIni = ($user->gaji_harian??0)*0.5;
            $uangMakan   = ($user->uang_makan??0)*0.5;
        } else {
            $potongan    = $this->hitungPotongan($menitTelat);
            $status      = $menitTelat>0?'telat':'hadir';
            $gajiHariIni = ($user->gaji_harian??0)-$potongan;
            $uangMakan   = $user->uang_makan??0;
        }

        Absensi::updateOrCreate(
            ['user_id'=>$user->id,'tanggal'=>today()],
            [
                'jam_masuk'       => now()->format('H:i:s'),
                'foto_masuk'      => $fotoPath,
                'lat_masuk'       => $request->lat,
                'lng_masuk'       => $request->lng,
                'gps_valid_masuk' => true, // selalu true, GPS tetap dicatat
                'status'          => $status,
                'potongan_telat'  => $potongan,
                'gaji_hari_ini'   => $gajiHariIni,
                'uang_makan_hari_ini' => $uangMakan,
            ]
        );

        $pesan = match($status) {
            'setengah_hari' => "⚠️ Absen masuk berhasil. Tercatat SETENGAH HARI (masuk jam ".now()->format('H:i').")",
            'telat'         => "✅ Absen masuk berhasil. Telat {$menitTelat} menit — potongan Rp ".number_format($potongan,0,',','.'),
            default         => "✅ Absen masuk berhasil jam ".now()->format('H:i'),
        };

        if ($sedangLuarKota) {
            $pesan .= "\n✈️ Mode luar kota aktif — GPS tidak divalidasi.";
        }

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function validasiKode(Request $request)
    {
        $kode  = strtoupper(trim($request->kode ?? ''));
        $valid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                      ->where('kode', $kode)
                                      ->exists();
        return response()->json(['valid' => $valid]);
    }

    public function cekGps(Request $request)
    {
        $user   = Auth::user();
        $tipe   = $request->tipe ?? 'masuk';

        // Kalau luar kota — langsung valid
        if (LuarKota::sedangLuarKota($user->id)) {
            return response()->json(['valid'=>true,'jarak'=>'0m','meter'=>0,'luar_kota'=>true]);
        }

        $lokasi = $this->getLokasiCek($user->level, $tipe);
        $radius = $tipe==='siang' ? self::RADIUS_SIANG : self::RADIUS_MASUK_PULANG;
        $jarak  = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
        return response()->json(['valid'=>$jarak<=$radius,'jarak'=>$this->formatJarak($jarak),'meter'=>round($jarak),'luar_kota'=>false]);
    }

    public function formSiang()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return redirect()->route('absensi.index')->with('error','Kamu belum absen masuk pagi.');
        if ($absen?->jam_absen_siang) return redirect()->route('absensi.index')->with('info','Kamu sudah absen siang hari ini.');

        $lokasi          = $this->getLokasiCek($user->level,'siang');
        $statusPekerjaan = self::STATUS_PEKERJAAN;
        $jenisKendala    = self::JENIS_KENDALA;
        $gpsWajib        = in_array($user->level, self::LEVEL_KANTOR);
        $luarKotaAktif   = LuarKota::getAktif($user->id);

        return view('absensi.form-siang', compact('user','lokasi','statusPekerjaan','jenisKendala','gpsWajib','luarKotaAktif'));
    }

    public function absenSiang(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen) return response()->json(['success'=>false,'message'=>'Belum absen masuk pagi.']);

        $request->validate([
            'foto_1'             => 'required|string',
            'lat'                => 'required|numeric',
            'lng'                => 'required|numeric',
            'status_pekerjaan'   => 'required|string',
            'ada_kendala'        => 'required',
            'jenis_kendala'      => 'required_if:ada_kendala,1',
            'deskripsi_kendala'  => 'required_if:ada_kendala,1',
        ]);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        $gpsValid = true;
        if (in_array($user->level, self::LEVEL_KANTOR) && !$sedangLuarKota) {
            $lokasi   = $this->getLokasiCek($user->level,'siang');
            $jarak    = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
            $gpsValid = $jarak <= self::RADIUS_SIANG;
            if (!$gpsValid) {
                return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
            }
        }

        $jamSekarang   = now()->format('H:i');
        $menitTelat    = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK_SIANG, self::TOLERANSI_SIANG);
        $potonganSiang = $this->hitungPotongan($menitTelat);
        $folder        = 'absensi/'.$user->id.'/'.today()->format('Ymd');

        $absen->update([
            'foto_siang_1'           => $this->simpanFotoBase64($request->foto_1,$folder),
            'foto_siang_2'           => $request->foto_2 ? $this->simpanFotoBase64($request->foto_2,$folder) : null,
            'foto_siang_3'           => $request->foto_3 ? $this->simpanFotoBase64($request->foto_3,$folder) : null,
            'lat_siang'              => $request->lat,
            'lng_siang'              => $request->lng,
            'gps_valid_siang'        => true, // selalu true, GPS tetap dicatat
            'jam_absen_siang'        => now()->format('H:i:s'),
            'status_pekerjaan'       => $request->status_pekerjaan,
            'ada_kendala'            => $request->ada_kendala,
            'jenis_kendala'          => $request->jenis_kendala,
            'deskripsi_kendala'      => $request->deskripsi_kendala,
            'potongan_telat'         => ($absen->potongan_telat??0) + $potonganSiang,
            'potongan_siang_dicatat' => true,
            'gaji_hari_ini'          => ($absen->gaji_hari_ini??0) - $potonganSiang,
        ]);

        if ($request->ada_kendala==1) $this->kirimNotifKendala($user,$absen,$request);

        $pesan = $menitTelat>0
            ? "✅ Absen siang berhasil. Telat {$menitTelat} menit — potongan Rp ".number_format($potonganSiang,0,',','.')
            : "✅ Absen siang berhasil.";

        if ($sedangLuarKota) $pesan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function formPulang()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return redirect()->route('absensi.index')->with('error','Kamu belum absen masuk.');
        if ($absen?->jam_pulang) return redirect()->route('absensi.index')->with('info','Kamu sudah absen pulang.');

        $lokasi        = $this->getLokasiUser($user->level);
        $adaLembur     = $absen->lembur_approved ?? false;
        $jamLemburMax  = self::JAM_LEMBUR;
        $absenHariIni  = $absen;
        $luarKotaAktif = LuarKota::getAktif($user->id);

        return view('absensi.form-pulang', compact('user','lokasi','absen','absenHariIni','adaLembur','jamLemburMax','luarKotaAktif'));
    }

    public function absenPulang(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen) return response()->json(['success'=>false,'message'=>'Belum absen masuk.']);

        $request->validate(['foto'=>'required|string','lat'=>'required|numeric','lng'=>'required|numeric']);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        // Validasi GPS pulang — skip kalau luar kota
        $lokasi = $this->getLokasiUser($user->level);
        $jarak  = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
        if ($jarak > self::RADIUS_MASUK_PULANG && !$sedangLuarKota) {
            return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
        }

        $fotoPath  = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        $jamPulang = now()->format('H:i:s');

        $menitKerja   = $this->hitungMenitKerja($absen->jam_masuk,$jamPulang);
        $setengahHari = $absen->status!=='setengah_hari' && $menitKerja<225;

        $lemburJam=$gajiLembur=0;
        if ($absen->lembur_approved && now()->format('H:i')>=self::JAM_LEMBUR) {
            $lemburJam  = min(round($this->hitungMenitTelat(now()->format('H:i'),self::JAM_LEMBUR)/60,2),self::LEMBUR_MAX_JAM);
            $gajiLembur = $lemburJam*(($user->gaji_harian??0)/7.5)*1.2;
        }

        $statusBaru = $setengahHari?'setengah_hari':$absen->status;
        $umHariIni  = $statusBaru==='setengah_hari'?($user->uang_makan??0)*0.5:($user->uang_makan??0);
        $gajiBersih = $statusBaru==='setengah_hari'
            ?($user->gaji_harian??0)*0.5-($absen->potongan_telat??0)+$gajiLembur
            :($absen->gaji_hari_ini??0)+$gajiLembur;

        $absen->update([
            'jam_pulang'          => $jamPulang,
            'foto_pulang'         => $fotoPath,
            'lat_pulang'          => $request->lat,
            'lng_pulang'          => $request->lng,
            'gps_valid_pulang'    => true, // selalu true, GPS tetap dicatat
            'status'              => $statusBaru,
            'uang_makan_hari_ini' => $umHariIni,
            'gaji_hari_ini'       => $gajiBersih,
            'lembur_jam'          => $lemburJam,
        ]);

        $pesan = "✅ Absen pulang berhasil jam ".now()->format('H:i');
        if ($setengahHari) $pesan .= " (setengah hari)";
        if ($lemburJam>0) $pesan .= " + lembur {$lemburJam} jam";
        if ($sedangLuarKota) $pesan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }

    public function rekap(Request $request)
    {
        $tanggal     = $request->tanggal ?? today()->format('Y-m-d');
        $levelFilter = (int)($request->level ?? 0);

        $query = User::where('level', '!=', 1)
                     ->where('status', 'aktif')
                     ->with(['absensi' => fn($q) => $q->whereDate('tanggal', $tanggal)]);

        if ($levelFilter > 0) $query->where('level', $levelFilter);

        $karyawan = $query->orderBy('level')->orderBy('name')->get();

        // Siapa yang sedang luar kota hari ini
        $sedangLuarKota = LuarKota::aktifPadaTanggal()->pluck('user_id')->toArray();

        return view('absensi.rekap', compact('karyawan', 'tanggal', 'levelFilter', 'sedangLuarKota'));
    }

    public function rekapBulanan(Request $request)
    {
        $bulan  = (int)($request->bulan ?? now()->month);
        $tahun  = (int)($request->tahun ?? now()->year);
        $userId = $request->user_id;
        $user   = Auth::user();

        if ($user->level > 2) $userId = $user->id;

        $hariDalamBulan = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;

        $daftarKaryawan = collect();
        if ($user->level <= 2) {
            $daftarKaryawan = User::where('level','!=',1)->where('status','aktif')->orderBy('name')->get();
        }

        if ($userId) {
            $karyawanList = User::where('id', $userId)->get();
        } elseif ($user->level <= 2) {
            $karyawanList = User::where('level','!=',1)->where('status','aktif')->orderBy('level')->orderBy('name')->get();
        } else {
            $karyawanList = User::where('id', $user->id)->get();
        }

        $rekapData = [];
        foreach ($karyawanList as $k) {
            $absensiRaw = Absensi::where('user_id', $k->id)
                                 ->whereMonth('tanggal', $bulan)
                                 ->whereYear('tanggal', $tahun)
                                 ->get()
                                 ->keyBy(fn($a) => \Carbon\Carbon::parse($a->tanggal)->format('Y-m-d'));

            $stats = [
                'hadir'          => $absensiRaw->whereIn('status',['hadir','telat','setengah_hari'])->count(),
                'alpha'          => $absensiRaw->where('status','alpha')->count(),
                'telat'          => $absensiRaw->where('status','telat')->count(),
                'izin'           => $absensiRaw->whereIn('status',['sakit','izin','cuti','dinas_luar'])->count(),
                'total_potongan' => $absensiRaw->sum('potongan_telat'),
                'total_gaji'     => $absensiRaw->sum('gaji_hari_ini'),
                'total_um'       => $absensiRaw->sum('uang_makan_hari_ini'),
            ];

            $rekapData[] = [
                'karyawan'         => $k,
                'absensi'          => $absensiRaw,
                'stats'            => $stats,
                'hari_dalam_bulan' => $hariDalamBulan,
            ];
        }

        return view('absensi.rekap-bulanan', compact('rekapData','bulan','tahun','userId','daftarKaryawan'));
    }

    public function koreksi(Request $request, $id)
    {
        $request->validate([
            'jam_masuk'  => 'nullable|date_format:H:i',
            'jam_pulang' => 'nullable|date_format:H:i',
            'status'     => 'required|string',
            'alasan'     => 'required|string',
        ]);

        $absen = Absensi::findOrFail($id);
        $user  = $absen->user;

        $gajiHariIni = match($request->status) {
            'hadir', 'telat'                      => $user->gaji_harian ?? 0,
            'setengah_hari'                       => ($user->gaji_harian ?? 0) * 0.5,
            'sakit', 'izin', 'cuti', 'dinas_luar' => 0,
            'alpha'                               => 0,
            default                               => $absen->gaji_hari_ini,
        };
        $umHariIni = match($request->status) {
            'hadir', 'telat', 'sakit', 'izin', 'cuti', 'dinas_luar' => $user->uang_makan ?? 0,
            'setengah_hari' => ($user->uang_makan ?? 0) * 0.5,
            'alpha'         => 0,
            default         => $absen->uang_makan_hari_ini,
        };

        $absen->update([
            'jam_masuk'           => $request->jam_masuk ? $request->jam_masuk.':00' : $absen->jam_masuk,
            'jam_pulang'          => $request->jam_pulang ? $request->jam_pulang.':00' : $absen->jam_pulang,
            'status'              => $request->status,
            'gaji_hari_ini'       => $gajiHariIni,
            'uang_makan_hari_ini' => $umHariIni,
            'dikoreksi'           => true,
            'alasan_koreksi'      => $request->alasan,
            'dikoreksi_oleh'      => Auth::id(),
        ]);

        return back()->with('success', 'Koreksi absen berhasil disimpan.');
    }

    public function koreksiManual(Request $request, $userId)
    {
        $request->validate([
            'jam_masuk'  => 'nullable|date_format:H:i',
            'jam_pulang' => 'nullable|date_format:H:i',
            'status'     => 'required|string',
            'alasan'     => 'required|string',
        ]);

        $user    = User::findOrFail($userId);
        $tanggal = $request->tanggal ?? today();

        $gajiHariIni = match($request->status) {
            'hadir', 'telat' => $user->gaji_harian ?? 0,
            'setengah_hari'  => ($user->gaji_harian ?? 0) * 0.5,
            default          => 0,
        };
        $umHariIni = match($request->status) {
            'hadir', 'telat', 'sakit', 'izin', 'cuti', 'dinas_luar' => $user->uang_makan ?? 0,
            'setengah_hari' => ($user->uang_makan ?? 0) * 0.5,
            default         => 0,
        };

        Absensi::updateOrCreate(
            ['user_id' => $userId, 'tanggal' => $tanggal],
            [
                'jam_masuk'           => $request->jam_masuk ? $request->jam_masuk.':00' : null,
                'jam_pulang'          => $request->jam_pulang ? $request->jam_pulang.':00' : null,
                'status'              => $request->status,
                'gaji_hari_ini'       => $gajiHariIni,
                'uang_makan_hari_ini' => $umHariIni,
                'dikoreksi'           => true,
                'alasan_koreksi'      => $request->alasan,
                'dikoreksi_oleh'      => Auth::id(),
            ]
        );

        return back()->with('success', 'Absen manual berhasil dicatat untuk '.$user->name);
    }

    private function getLokasiUser(int $level): array
    {
        return in_array($level,self::LEVEL_WORKSHOP)?self::LOKASI['workshop']:self::LOKASI['kantor'];
    }

    private function getLokasiCek(int $level, string $tipe): array
    {
        if ($tipe==='siang' && in_array($level,self::LEVEL_WORKSHOP)) return self::LOKASI['workshop'];
        return $this->getLokasiUser($level);
    }

    private function hitungJarak(float $lat1,float $lng1,float $lat2,float $lng2): float
    {
        $R=6371000; $dLat=deg2rad($lat2-$lat1); $dLng=deg2rad($lng2-$lng1);
        $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
        return $R*2*atan2(sqrt($a),sqrt(1-$a));
    }

    private function formatJarak(float $meter): string
    { return $meter>=1000?round($meter/1000,1).'km':round($meter).'m'; }

    private function hitungMenitTelat(string $jamSekarang,string $jamTarget,int $toleransi=0): int
    { return max(0,(int)((strtotime($jamSekarang)-strtotime($jamTarget)-($toleransi*60))/60)); }

    private function hitungPotongan(int $menitTelat): float
    { return ($menitTelat/60)*self::POTONGAN_TELAT; }

    private function hitungMenitKerja(string $jamMasuk,string $jamPulang): int
    {
        $total=(strtotime($jamPulang)-strtotime($jamMasuk))/60;
        return (int)(strtotime($jamPulang)>strtotime(self::JAM_MASUK_SIANG.':00')?$total-60:$total);
    }

    private function getFaseAbsen(?Absensi $absen): string
    {
        if (!$absen||!$absen->jam_masuk) return 'belum_masuk';
        if (!$absen->jam_absen_siang) {
            $jam = now()->format('H:i');
            if ($jam < self::JAM_MASUK_SIANG) { /* belum waktunya */ }
            elseif ($jam < self::JAM_SKIP_SIANG) return 'perlu_absen_siang';
        }
        if (!$absen->jam_pulang) return 'perlu_pulang';
        return 'lengkap';
    }

    private function simpanFotoBase64(string $base64,string $folder): string
    {
        $imageData=preg_replace('/^data:image\/\w+;base64,/','',$base64);
        $filename=$folder.'/'.date('His').'_'.uniqid().'.jpg';
        Storage::disk('public')->put($filename,base64_decode($imageData));
        return $filename;
    }

    private function kirimNotifKendala(User $user,Absensi $absen,Request $request): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('no_hp')->get();
        $jenisLabel=self::JENIS_KENDALA[$request->jenis_kendala]??$request->jenis_kendala;
        foreach ($penerima as $p) {
            $this->kirimWA($p->no_hp,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nKendala: {$jenisLabel}\nKeterangan: {$request->deskripsi_kendala}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }

    private function kirimWA(string $noHp,string $pesan): void
    {
        $token=env('FONNTE_TOKEN','');
        if (!$token) return;
        $noHp=preg_replace('/^0/','62',preg_replace('/[^0-9]/','',$noHp));
        $ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>'https://api.fonnte.com/send',CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['target'=>$noHp,'message'=>$pesan],CURLOPT_HTTPHEADER=>['Authorization: '.$token]]);
        curl_exec($ch); curl_close($ch);
    }
}