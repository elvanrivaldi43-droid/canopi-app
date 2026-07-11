<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\CuttingService;

class CuttingController extends Controller
{
    private function bolehAkses(): bool
    {
        return in_array(Auth::user()->level, [1, 2, 3]); // owner, admin, surveyor
    }

    public function index()
    {
        abort_if(!$this->bolehAkses(), 403);

        $besi = collect();
        try {
            $besi = DB::table('master_material')
                ->where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) { $besi = collect(); }

        $jenisKerja = collect();
        $kondisi    = collect();
        $atap       = collect();
        try {
            $jenisKerja = DB::table('rab_jenis_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'satuan', 'produktivitas_per_hari', 'jml_tukang', 'jml_kenek', 'skill_default']);
            $kondisi = DB::table('rab_kondisi_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'pengali_upah', 'tambahan_per_hari']);
        } catch (\Throwable $e) {}
        try {
            $atap = DB::table('rab_atap')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')
                ->get(['id', 'nama', 'harga_per_m2', 'pemborosan_persen', 'upah_pasang_per_m2']);
        } catch (\Throwable $e) {}

        $addon = collect();
        try {
            $addon = DB::table('rab_addon')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')
                ->get(['id', 'nama', 'satuan', 'formula_type', 'harga_pokok_satuan', 'level']);
        } catch (\Throwable $e) {}

        $lihatHarga = Auth::user()->level == 1;
        return view('cutting.index', compact('besi', 'jenisKerja', 'kondisi', 'atap', 'addon', 'lihatHarga'));
    }

    private function ambilInput(Request $request): array
    {
        $b = fn($k, $def) => filter_var($request->input($k, $def), FILTER_VALIDATE_BOOLEAN);
        return [
            'lebar_cm'     => $request->input('lebar_cm'),
            'panjang_cm'   => $request->input('panjang_cm'),
            'tinggi_cm'    => $request->input('tinggi_cm', 300),
            'kotak_cm'     => $request->input('kotak_cm', 80),
            'arah_support' => $request->input('arah_support', 2),
            'jml_tiang'    => $request->input('jml_tiang', 2),
            'mat_frame'    => $request->input('mat_frame', 'Frame'),
            'mat_support'  => $request->input('mat_support', 'Support'),
            'mat_tiang'    => $request->input('mat_tiang', 'Tiang'),
            'frame_depan'    => $b('frame_depan', true),
            'frame_belakang' => $b('frame_belakang', true),
            'frame_kiri'     => $b('frame_kiri', true),
            'frame_kanan'    => $b('frame_kanan', true),
            'frame_tengah'   => $b('frame_tengah', true),
        ];
    }

    private function tempelHarga(array $hasil, array $harga, bool $lihatHarga): array
    {
        foreach ($hasil['per_material'] as &$m) {
            $h = ($lihatHarga && isset($harga[$m['material']])) ? (float) $harga[$m['material']] : null;
            $m['harga_pokok']   = $h;
            $m['subtotal_besi'] = $h !== null ? $h * $m['jumlah_batang'] : null;
        }
        unset($m);
        $hasil['total_biaya_besi'] = $lihatHarga
            ? array_sum(array_filter(array_column($hasil['per_material'], 'subtotal_besi')))
            : null;
        return $hasil;
    }

    /**
     * Harga total RANGKA + ATAP (owner). Besi + upah rangka + atap (material+upah) -> pokok -> jual.
     * Data kosong -> peringatan, BUKAN Rp0 diam-diam.
     */
    private function hitungHarga(array $hasil, Request $req, bool $lihatHarga): array
    {
        $hasil['harga'] = null;
        if (!$lihatHarga) return $hasil;

        $besi = (float) ($hasil['total_biaya_besi'] ?? 0);
        $L = (float) ($hasil['input']['L'] ?? 0);
        $P = (float) ($hasil['input']['P'] ?? 0);
        $luasKanopi = $L * $P / 10000;
        $warn = [];

        // ===== UPAH RANGKA =====
        $rangka = null; $upahRangka = 0.0;
        $jkId = (int) $req->input('jenis_kerja_id', 0);
        if ($jkId > 0) {
            $jk = DB::table('rab_jenis_kerja')->where('id', $jkId)->first();
            if ($jk) {
                $skill = DB::table('rab_skill')->where('nama', $jk->skill_default)->first();
                $prod = (float) ($jk->produktivitas_per_hari ?? 0);
                $prodInst = (float) ($jk->produktivitas_inst ?? 0);
                $nT = (int) ($jk->jml_tukang ?? 0); $nK = (int) ($jk->jml_kenek ?? 0);
                $uT = (float) ($skill->upah_tukang_harian ?? 0);
                $uK = (float) ($skill->upah_kenek_harian ?? 0);
                if ($prod <= 0)           $warn[] = "Produktivitas \"{$jk->nama}\" belum diisi";
                if ($nT <= 0 && $nK <= 0) $warn[] = "Tukang/kenek \"{$jk->nama}\" belum diisi";
                if ($nT > 0 && $uT <= 0)  $warn[] = "Upah tukang \"{$jk->skill_default}\" belum diisi";
                if ($nK > 0 && $uK <= 0)  $warn[] = "Upah kenek \"{$jk->skill_default}\" belum diisi";

                $hariFab = $prod > 0 ? $luasKanopi / $prod : 0;
                $hariInst = $prodInst > 0 ? $luasKanopi / $prodInst : 0;
                $hari = $hariFab + $hariInst;
                $upahHari = $nT * $uT + $nK * $uK;
                $base = $hari * $upahHari;

                $pengali = 1.0; $tambahanHari = 0.0; $kondNama = [];
                $kondIds = array_filter((array) $req->input('kondisi_ids', []));
                if ($kondIds) {
                    foreach (DB::table('rab_kondisi_kerja')->whereIn('id', $kondIds)->get() as $k) {
                        if (($k->pengali_upah ?? 0) > 0) $pengali *= (float) $k->pengali_upah;
                        $tambahanHari += (float) ($k->tambahan_per_hari ?? 0);
                        $kondNama[] = $k->nama;
                    }
                }
                $upahRangka = $base * $pengali + $tambahanHari * $hari;
                $rangka = [
                    'jenis_kerja' => $jk->nama, 'luas' => round($luasKanopi, 2),
                    'produktivitas' => $prod, 'hari' => round($hari, 2),
                    'jml_tukang' => $nT, 'jml_kenek' => $nK, 'upah_per_hari' => $upahHari,
                    'kondisi' => $kondNama, 'pengali' => $pengali, 'total' => round($upahRangka),
                ];
            }
        }

        // ===== ATAP (banyak bagian) =====
        $atapRows = []; $atapMaterial = 0.0; $atapUpah = 0.0;
        $aIds  = (array) $req->input('atap_jenis_id', []);
        $aLuas = (array) $req->input('atap_luas', []);
        foreach ($aIds as $i => $aid) {
            $aid = (int) $aid; $luas = (float) ($aLuas[$i] ?? 0);
            if ($aid <= 0 || $luas <= 0) continue;
            $a = DB::table('rab_atap')->where('id', $aid)->first();
            if (!$a) continue;
            $hm2   = (float) ($a->harga_per_m2 ?? 0);
            $boros = (float) ($a->pemborosan_persen ?? 0);
            $upm2  = (float) ($a->upah_pasang_per_m2 ?? 0);
            if ($hm2 <= 0)  $warn[] = "Harga/m² atap \"{$a->nama}\" belum diisi";
            if ($upm2 <= 0) $warn[] = "Upah pasang atap \"{$a->nama}\" belum diisi";
            $mat = $luas * $hm2 * (1 + $boros / 100);
            $up  = $luas * $upm2;
            $atapMaterial += $mat; $atapUpah += $up;
            $atapRows[] = [
                'nama' => $a->nama, 'luas' => round($luas, 2), 'harga_m2' => round($hm2),
                'boros' => $boros, 'material' => round($mat), 'upah' => round($up),
                'subtotal' => round($mat + $up),
            ];
        }

        // ===== ADD-ON (dari rab_addon: modal -> pokok -> margin global) =====
        $addonRows = []; $addonFisik = 0.0;
        $adId  = (array) $req->input('addon_id', []);
        $adQty = (array) $req->input('addon_qty', []);
        foreach ($adId as $i => $aid) {
            $aid = (int) $aid;
            if ($aid <= 0) continue;
            $ad = DB::table('rab_addon')->where('id', $aid)->first();
            if (!$ad) continue;
            $ft    = $ad->formula_type ?? 'per_unit';   // per_unit | per_meter | per_m2 | flat
            $level = $ad->level ?? 'total';
            $harga = (float) ($ad->harga_pokok_satuan ?? 0);
            $qty   = ($ft === 'flat') ? 1.0 : (float) ($adQty[$i] ?? 0);
            if ($ft !== 'flat' && $qty <= 0) continue; // tidak dipakai
            if ($harga <= 0) $warn[] = "Harga modal add-on \"{$ad->nama}\" belum diisi";
            $biaya = $qty * $harga;
            $addonFisik += $biaya;
            $addonRows[] = [
                'nama' => $ad->nama, 'satuan' => $ad->satuan, 'formula' => $ft,
                'level' => $level, 'qty' => $qty, 'harga' => round($harga), 'biaya' => round($biaya),
            ];
        }

        // ===== TOTAL (margin sekali, di atas semua pokok) =====
        $margin = min(0.9, max(0.0, (float) $req->input('margin_persen', 45) / 100));
        $pokok = $besi + $upahRangka + $atapMaterial + $atapUpah + $addonFisik;
        $jual = $pokok / (1 - $margin);

        $hasil['harga'] = [
            'besi'          => round($besi),
            'rangka'        => $rangka,
            'upah_rangka'   => round($upahRangka),
            'atap'          => $atapRows,
            'atap_material' => round($atapMaterial),
            'atap_upah'     => round($atapUpah),
            'addon'         => $addonRows,
            'addon_fisik'   => round($addonFisik),
            'pokok'         => round($pokok),
            'margin_persen' => round($margin * 100),
            'jual'          => round($jual),
            'peringatan'    => array_values(array_unique($warn)),
        ];
        return $hasil;
    }

    public function hitung(Request $request, CuttingService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lihatHarga = Auth::user()->level == 1;
        $hasil = $svc->hitungRangka($this->ambilInput($request));
        $hasil = $this->tempelHarga($hasil, (array) $request->input('harga', []), $lihatHarga);
        $hasil = $this->hitungHarga($hasil, $request, $lihatHarga);
        return response()->json(['success' => true, 'data' => $hasil]);
    }

    public function cetak(Request $request, CuttingService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lihatHarga = Auth::user()->level == 1;
        $hasil = $svc->hitungRangka($this->ambilInput($request));
        $hasil = $this->tempelHarga($hasil, (array) $request->input('harga', []), $lihatHarga);
        $hasil = $this->hitungHarga($hasil, $request, $lihatHarga);

        $judul = $request->input('judul', 'Cutting List Rangka');
        return view('cutting.print', [
            'd'          => $hasil,
            'judul'      => $judul,
            'lihatHarga' => $lihatHarga,
            'tanggal'    => now()->format('d/m/Y H:i'),
        ]);
    }

    // ================================================================
    // MULTI-BLOK (Tahap 1) — beberapa blok dalam 1 opsi, 1 total.
    // Engine lama TIDAK diubah; ini jalur baru di /rab-blok.
    // ================================================================

    public function projectIndex()
    {
        abort_if(!$this->bolehAkses(), 403);

        $besi = collect();
        try {
            $besi = DB::table('master_material')
                ->where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {}

        $besiSemua = collect();
        try {
            $besiSemua = DB::table('master_material')->where('aktif', 1)
                ->orderBy('kategori')->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {}

        $jenisKerja = collect(); $kondisi = collect(); $atap = collect(); $addon = collect();
        try {
            $jenisKerja = DB::table('rab_jenis_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'satuan', 'produktivitas_per_hari', 'jml_tukang', 'jml_kenek', 'skill_default']);
            $kondisi = DB::table('rab_kondisi_kerja')->where('is_active', 1)
                ->orderBy('urutan')->get(['id', 'nama', 'pengali_upah', 'tambahan_per_hari']);
            $atap = DB::table('rab_atap')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')->get(['id', 'nama', 'harga_per_m2', 'pemborosan_persen', 'upah_pasang_per_m2']);
            $addon = DB::table('rab_addon')->where('is_active', 1)
                ->orderBy('urutan')->orderBy('nama')->get(['id', 'nama', 'satuan', 'formula_type', 'harga_pokok_satuan', 'level']);
        } catch (\Throwable $e) {}

        $lihatHarga = Auth::user()->level == 1;
        return view('rab-blok.index', compact('besi', 'besiSemua', 'jenisKerja', 'kondisi', 'atap', 'addon', 'lihatHarga'));
    }

    public function hitungProject(Request $request, CuttingService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lihatHarga = in_array(Auth::user()->level, [1, 2, 3]); // owner+admin+surveyor lihat harga jual

        $bloks = (array) $request->input('blok', []);
        $hasilBlok = []; $totalPokok = 0.0; $warnAll = []; $totalHariInst = 0.0; $totalHariFab = 0.0; $maxTukang = 0; $maxKenek = 0;

        foreach ($bloks as $idx => $b) {
            $b = (array) $b;
            $aktif = filter_var($b['aktif'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $r = $this->hitungSatuBlok($b, $svc, $lihatHarga);
            $r['aktif'] = $aktif;
            $r['urut']  = $idx + 1;
            if ($aktif) {
                $totalPokok += (float) ($r['pokok_blok'] ?? 0);
                if (isset($r['rangka']['hari_inst'])) $totalHariInst += (float) $r['rangka']['hari_inst'];
                if (isset($r['rangka']['hari_fab']))  $totalHariFab  += (float) $r['rangka']['hari_fab'];
                if (isset($r['rangka']['jml_tukang_inst']) && (int) $r['rangka']['jml_tukang_inst'] > $maxTukang) $maxTukang = (int) $r['rangka']['jml_tukang_inst'];
                if (isset($r['rangka']['jml_kenek_inst'])  && (int) $r['rangka']['jml_kenek_inst']  > $maxKenek)  $maxKenek  = (int) $r['rangka']['jml_kenek_inst'];
                foreach ($r['peringatan'] as $w) $warnAll[] = "Blok " . ($r['nama'] ?: ('#' . ($idx + 1))) . ": " . $w;
            }
            $hasilBlok[] = $r;
        }

        // FINISHING PREMIUM per opsi: powder coating = luas rangka aktif × tarif owner
        if ($request->input('finishing', 'standar') === 'powder') {
            $setP = DB::table('rab_setting_global')->where('id', 1)->first();
            $tarifPowder = $setP ? (float) ($setP->powder_coating ?? 0) : 0;
            if ($tarifPowder > 0) {
                $luasRangkaTotal = 0.0;
                foreach ($hasilBlok as $rr) {
                    if (!empty($rr['aktif']) && isset($rr['rangka']['luas'])) $luasRangkaTotal += (float) $rr['rangka']['luas'];
                }
                $totalPokok += $luasRangkaTotal * $tarifPowder;
            }
        }

        $margin = min(0.9, max(0.0, (float) $request->input('margin_persen', 45) / 100));
        $jual = $lihatHarga ? ($totalPokok / (1 - $margin)) : null;

        return response()->json(['success' => true, 'data' => [
            'blok'          => $hasilBlok,
            'hari_inst_total' => round($totalHariInst, 2),
            'hari_fab_total'  => round($totalHariFab, 2),
            'tukang_max'      => $maxTukang,
            'kenek_max'       => $maxKenek,
            'pokok'         => $lihatHarga ? round($totalPokok) : null,
            'margin_persen' => round($margin * 100),
            'jual'          => $jual !== null ? round($jual) : null,
            'peringatan'    => array_values(array_unique($warnAll)),
            'lihat_harga'   => $lihatHarga,
        ]]);
    }

    /**
     * Hitung SATU blok (kanopi/manual) -> pokok blok TANPA margin.
     * Margin diterapkan di level project/opsi. Dipakai multi-blok & nanti multi-opsi.
     */
    private function hitungSatuBlok(array $b, CuttingService $svc, bool $lihatHarga): array
    {
        $tipe = ($b['tipe'] ?? 'kanopi') === 'manual' ? 'manual' : 'kanopi';
        $nama = trim((string) ($b['nama'] ?? ''));
        $warn = [];
        $besi = 0.0; $rincian = null; $cutting = null;

        if ($tipe === 'kanopi') {
            $bb = (function () use ($b) {
                $bool = fn($k, $def) => filter_var($b[$k] ?? $def, FILTER_VALIDATE_BOOLEAN);
                return [
                    'lebar_cm'     => $b['lebar_cm'] ?? null,
                    'panjang_cm'   => $b['panjang_cm'] ?? null,
                    'tinggi_cm'    => $b['tinggi_cm'] ?? 300,
                    'kotak_cm'     => $b['kotak_cm'] ?? 80,
                    'arah_support' => $b['arah_support'] ?? 2,
                    'jml_tiang'    => $b['jml_tiang'] ?? 2,
                    'mat_frame'    => $b['mat_frame'] ?? 'Frame',
                    'mat_support'  => $b['mat_support'] ?? 'Support',
                    'mat_tiang'    => $b['mat_tiang'] ?? 'Tiang',
                    'frame_depan'    => $bool('frame_depan', true),
                    'frame_belakang' => $bool('frame_belakang', true),
                    'frame_kiri'     => $bool('frame_kiri', true),
                    'frame_kanan'    => $bool('frame_kanan', true),
                    'frame_tengah'   => $bool('frame_tengah', true),
                ];
            })();
            $cutting = $svc->hitungRangka($bb);

            $harga = (array) ($b['harga'] ?? []);
            if ($lihatHarga) {
                foreach ($cutting['per_material'] as &$m) {
                    $h = isset($harga[$m['material']]) ? (float) $harga[$m['material']] : 0;
                    $m['harga_pokok'] = $h;
                    $m['subtotal_besi'] = $h * $m['jumlah_batang'];
                    $besi += $h * $m['jumlah_batang'];
                    if ($h <= 0) $warn[] = "Harga besi \"{$m['material']}\" belum diisi";
                }
                unset($m);
            }
            $rincian = $cutting['per_material'];
        } else {
            // MANUAL: daftar item besi (nama, qty, harga) diisi langsung
            $items = [];
            foreach ((array) ($b['manual_items'] ?? []) as $it) {
                $it = (array) $it;
                $nm = trim((string) ($it['nama'] ?? ''));
                $qty = (float) ($it['qty'] ?? 0);
                $hrg = (float) ($it['harga'] ?? 0);
                if ($nm === '' || $qty <= 0) continue;
                if ($lihatHarga && $hrg <= 0) $warn[] = "Harga item \"{$nm}\" belum diisi";
                $sub = $qty * $hrg;
                $besi += $sub;
                $items[] = ['nama' => $nm, 'qty' => $qty, 'harga' => round($hrg), 'subtotal' => round($sub)];
            }
            $rincian = $items;
        }

        // ===== UPAH =====
        $upah = 0.0; $rangka = null; $luasKanopiBlok = 0.0;
        if ($tipe === 'kanopi') {
            $L = (float) ($cutting['input']['L'] ?? 0);
            $P = (float) ($cutting['input']['P'] ?? 0);
            $luas = $L * $P / 10000; $luasKanopiBlok = $luas;
            $jkId = (int) ($b['jenis_kerja_id'] ?? 0);
            if ($lihatHarga && $jkId > 0) {
                $jk = DB::table('rab_jenis_kerja')->where('id', $jkId)->first();
                if ($jk) {
                    $skill = DB::table('rab_skill')->where('nama', $jk->skill_default)->first();
                    $prod = (float) ($jk->produktivitas_per_hari ?? 0);
                    $prodInst = (float) ($jk->produktivitas_inst ?? 0);
                    $nT = (int) ($jk->jml_tukang ?? 0); $nK = (int) ($jk->jml_kenek ?? 0);
                    $nTinst = (int) ($jk->jml_tukang_inst ?? 0); if ($nTinst <= 0) $nTinst = $nT;
                    $nKinst = (int) ($jk->jml_kenek_inst ?? 0);  if ($nKinst <= 0) $nKinst = $nK;
                    $uT = (float) ($skill->upah_tukang_harian ?? 0);
                    $uK = (float) ($skill->upah_kenek_harian ?? 0);
                    if ($prod <= 0)           $warn[] = "Produktivitas \"{$jk->nama}\" belum diisi";
                    if ($nT <= 0 && $nK <= 0) $warn[] = "Tukang/kenek \"{$jk->nama}\" belum diisi";
                    $hariFab = $prod > 0 ? $luas / $prod : 0;
                    $hariInst = $prodInst > 0 ? $luas / $prodInst : 0;
                    $hari = $hariFab + $hariInst;
                    $upahHariTim = $nT * $uT + $nK * $uK;              // tim fabrikasi
                    $upahHariInst = $nTinst * $uT + $nKinst * $uK;      // tim instalasi
                    $base = $hari * $upahHariTim;
                    $pengali = 1.0; $tambahanHari = 0.0; $kondNama = [];
                    $pengaliInst = 1.0; $pengaliFab = 1.0; // instalasi kena semua kondisi; fabrikasi hanya kena kondisi skill unik (kena=fabinst)
                    $kondIds = array_filter((array) ($b['kondisi_ids'] ?? []));
                    if ($kondIds) {
                        foreach (DB::table('rab_kondisi_kerja')->whereIn('id', $kondIds)->get() as $k) {
                            $p = (float) ($k->pengali_upah ?? 0);
                            if ($p > 0) {
                                $pengaliInst *= $p;
                                if (($k->kena ?? 'fabinst') === 'fabinst') $pengaliFab *= $p;
                                $pengali *= $p;
                            }
                            $tambahanHari += (float) ($k->tambahan_per_hari ?? 0);
                            $kondNama[] = $k->nama;
                        }
                    }
                    $upahFab  = $hariFab  * ($upahHariTim ?? 0) * $pengaliFab;
                    $upahInst = $hariInst * ($upahHariInst ?? 0) * $pengaliInst;
                    $upah = $upahFab + $upahInst + $tambahanHari * $hari;
                    $rangka = ['jenis_kerja' => $jk->nama, 'luas' => round($luas, 2), 'hari' => round($hari, 2),
                        'hari_fab' => round($hariFab, 2), 'hari_inst' => round($hariInst, 2),
                        'jml_tukang' => $nT, 'jml_kenek' => $nK, 'jml_tukang_inst' => $nTinst, 'jml_kenek_inst' => $nKinst,
                        'kondisi' => $kondNama, 'pengali' => $pengali, 'total' => round($upah)];
                }
            }
        } else {
            $upah = (float) ($b['manual_upah'] ?? 0);
            if ($upah > 0) $rangka = ['jenis_kerja' => 'Upah manual', 'total' => round($upah)];
        }

        // ===== ATAP =====
        $atapRows = []; $atapMaterial = 0.0; $atapUpah = 0.0;
        if ($lihatHarga) {
            $aIds  = (array) ($b['atap_jenis_id'] ?? []);
            $aLuas = (array) ($b['atap_luas'] ?? []);
            $aPasang = (array) ($b['atap_pasang'] ?? []);
            foreach ($aIds as $i => $aid) {
                $aid = (int) $aid; $luas = (float) ($aLuas[$i] ?? 0);
                if ($aid <= 0 || $luas <= 0) continue;
                $a = DB::table('rab_atap')->where('id', $aid)->first();
                if (!$a) continue;
                $hm2 = (float) ($a->harga_per_m2 ?? 0); $boros = (float) ($a->pemborosan_persen ?? 0);
                $upm2 = (float) ($a->upah_pasang_per_m2 ?? 0);
                $pasangSendiri = !empty($aPasang[$i]); // atap di rangka lama/reparasi -> upah pasang dihitung (kalau rangka baru, sudah termasuk upah instalasi)
                if ($hm2 <= 0)  $warn[] = "Harga/m² atap \"{$a->nama}\" belum diisi";
                if ($pasangSendiri && $upm2 <= 0) $warn[] = "Upah pasang atap \"{$a->nama}\" belum diisi";
                $mat = $luas * $hm2 * (1 + $boros / 100); $up = $pasangSendiri ? $luas * $upm2 : 0;
                $atapMaterial += $mat; $atapUpah += $up;
                $cAtapJenis = (float) ($a->consumable ?? 0);           // consumable jenis atap ini
                if ($cAtapJenis > 0) { $consumAtapJenis = ($consumAtapJenis ?? 0) + $luas * $cAtapJenis; }
                else { $luasAtapGlobal = ($luasAtapGlobal ?? 0) + $luas; } // belum diisi -> pakai global
                $atapRows[] = ['nama' => $a->nama, 'luas' => round($luas, 2), 'material' => round($mat),
                    'upah' => round($up), 'boros' => $boros, 'subtotal' => round($mat + $up)];
            }
        }

        // ===== ADD-ON =====
        $addonRows = []; $addonFisik = 0.0;
        if ($lihatHarga) {
            $adId  = (array) ($b['addon_id'] ?? []);
            $adQty = (array) ($b['addon_qty'] ?? []);
            foreach ($adId as $i => $aid) {
                $aid = (int) $aid;
                if ($aid <= 0) continue;
                $ad = DB::table('rab_addon')->where('id', $aid)->first();
                if (!$ad) continue;
                $ft = $ad->formula_type ?? 'per_unit'; $level = $ad->level ?? 'total';
                $harga = (float) ($ad->harga_pokok_satuan ?? 0);
                $qty = ($ft === 'flat') ? 1.0 : (float) ($adQty[$i] ?? 0);
                if ($ft !== 'flat' && $qty <= 0) continue;
                if ($harga <= 0) $warn[] = "Harga modal add-on \"{$ad->nama}\" belum diisi";
                $biaya = $qty * $harga; $addonFisik += $biaya;
                // upah add-on BERAT: dari durasi (kecepatan satuan/hari) x tarif tim rangka. FLAT dilewati (harga sudah lumpsum).
                $upAd = 0.0;
                if ($ft !== 'flat') {
                    $dFab = (float) ($ad->durasi_fab ?? 0);
                    $dInst = (float) ($ad->durasi_inst ?? 0);
                    $hFabAd = $dFab > 0 ? $qty / $dFab : 0;
                    $hInstAd = $dInst > 0 ? $qty / $dInst : 0;
                    $upAd = $hFabAd * ($upahHariTim ?? 0) + $hInstAd * ($upahHariInst ?? 0);
                }
                $addonUpah = ($addonUpah ?? 0) + $upAd;
                $addonRows[] = ['nama' => $ad->nama, 'satuan' => $ad->satuan, 'formula' => $ft,
                    'level' => $level, 'qty' => $qty, 'harga' => round($harga), 'biaya' => round($biaya), 'upah' => round($upAd)];
            }
        }

        // ===== CONSUMABLE per m² (bahan pelengkap otomatis: kawat las/cat rangka, sealant/roofing atap) =====
        $luasAtapBlok = 0.0;
        foreach ($atapRows as $ar) { $luasAtapBlok += (float) ($ar['luas'] ?? 0); }
        $consumRangka = 0.0; $consumAtap = 0.0; $finishingBlok = 0.0;
        if ($lihatHarga) {
            $setG = DB::table('rab_setting_global')->where('id', 1)->first();
            if ($setG) {
                $consumRangka = $luasKanopiBlok * (float) ($setG->consumable_rangka ?? 0);
                // atap: consumable per jenis (kalau diisi di Varian Atap) + sisanya pakai global
                $consumAtap   = ($consumAtapJenis ?? 0) + ($luasAtapGlobal ?? 0) * (float) ($setG->consumable_atap ?? 0);
                // finishing standar (cat/duco) melekat per m² rangka -> dipisah biar kelihatan di rincian
                $finishingBlok = $luasKanopiBlok * (float) ($setG->finishing_standar ?? 0);
            }
        }

        $pokokBlok = $besi + $upah + $atapMaterial + $atapUpah + $addonFisik + ($addonUpah ?? 0) + $consumRangka + $finishingBlok + $consumAtap;

        return [
            'tipe' => $tipe, 'nama' => $nama ?: ($tipe === 'manual' ? 'Blok manual' : 'Blok kanopi'),
            'rincian' => $rincian, 'cutting' => $cutting,
            'besi' => round($besi), 'rangka' => $rangka, 'upah' => round($upah),
            'atap' => $atapRows, 'atap_material' => round($atapMaterial), 'atap_upah' => round($atapUpah),
            'addon' => $addonRows, 'addon_fisik' => round($addonFisik), 'addon_upah' => round($addonUpah ?? 0),
            'consumable_rangka' => round($consumRangka), 'consumable_atap' => round($consumAtap), 'finishing' => round($finishingBlok),
            'pokok_blok' => round($pokokBlok),
            'peringatan' => array_values(array_unique($warn)),
        ];
    }
}