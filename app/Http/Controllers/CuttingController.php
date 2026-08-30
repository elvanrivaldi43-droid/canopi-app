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

    /**
     * Panjang batang (stok potong) per material dari master_material.
     * Map ['<nama>' => panjang_cm]; kolom kosong/absen -> default 600 di hilir.
     */
    private function stokMap(): array
    {
        try {
            return DB::table('master_material')
                ->where('aktif', 1)
                ->get(['nama', 'panjang_batang_cm'])
                ->mapWithKeys(fn ($m) => [$m->nama => (float) ($m->panjang_batang_cm ?: 600)])
                ->toArray();
        } catch (\Throwable $e) {
            return []; // kolom belum ada / DB error -> semua default 600 di hilir
        }
    }

    public function index()
    {
        abort_if(!$this->bolehAkses(), 403);

        // Halaman dirombak 28 Ags 2026 (permintaan Elvan): kalkulator kotak-polos lama
        // DIBUANG, diganti editor denah yang sama dengan RAB Multi-Opsi -- gambar di
        // sini, keluar cutting list. Karena dokumen produksi/kalibrasi tanpa harga,
        // cuma butuh NAMA besi (harga tidak dikirim ke halaman ini).
        $besi = collect();
        try {
            $besi = \App\Models\MasterMaterial::where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get()
                ->map(function ($m) {
                    $p = $m->profilCm();
                    return ['id' => $m->id, 'nama' => $m->nama,
                            'lebar' => $p[0] ?? null, 'tinggi' => $p[1] ?? null];
                })->values();
        } catch (\Throwable $e) { $besi = collect(); }

        // Pemilih lead utk cutting list dari denah RAB tersimpan. LIKE murah krn cuma
        // menyaring kandidat; blok denah nonaktif/kosong disaring lagi saat render.
        $leadDenah = collect();
        try {
            $leadDenah = DB::table('pipeline_leads')
                ->where('rab_snapshot', 'like', '%"tipe":"denah"%')
                ->orderByDesc('updated_at')
                ->get(['id', 'nama_customer']);
        } catch (\Throwable $e) { $leadDenah = collect(); }

        return view('cutting.index', compact('besi', 'leadDenah'));
    }

    // Kalkulator kotak-polos lama (hitung/cetak + ambilInput/tempelHarga/hitungHarga)
    // DIHAPUS 28 Ags 2026 -- halaman ini kini memakai editor denah (bentuk apa pun).
    // Mesin CuttingService::hitungRangka TETAP ada: dipakai RangkaDesignService::seed.

    /**
     * Cutting list PRODUKSI/KALIBRASI dari denah RAB Multi-Opsi (rab_snapshot lead).
     * Dua pintu, satu halaman cetak:
     * - /cutting-denah?lead=N  (kalibrasi, dari halaman Cutting List): semua opsi
     * - /project/{id}/cutting-list (produksi, pasca-deal): hanya opsi di deal_json
     * Dokumen produksi: TANPA harga. Gambar denah ikut kalau penawaran punya
     * snapshot SVG-nya (dicocokkan nama opsi+blok).
     */
    private function renderCuttingDenah(object $lead, ?string $opsiDeal, string $judul)
    {
        $rd = new \App\Services\RangkaDesignService();
        $snap = json_decode((string) $lead->rab_snapshot, true);
        $bloks = is_array($snap) ? $rd->blokDenahDariSnapshot($snap, $opsiDeal) : [];

        $peringatan = '';
        if ($opsiDeal !== null && $bloks && $bloks[0]['opsi'] !== $opsiDeal
            && !array_filter($bloks, fn ($b) => $b['opsi'] === $opsiDeal)) {
            $peringatan = "Opsi deal \"{$opsiDeal}\" tidak ditemukan di RAB (mungkin diganti nama setelah deal) — menampilkan SEMUA opsi.";
        }
        if (!$bloks) {
            $peringatan = 'Lead ini tidak punya blok Denah dengan batang besi. Cutting list hanya tersedia untuk blok yang digambar di editor denah.';
        }

        // Gambar denah dari snapshot penawaran (kalau sudah pernah Buat Penawaran).
        $svgMap = [];
        $pen = json_decode((string) ($lead->penawaran_json ?? ''), true);
        foreach ((array) ($pen['opsi'] ?? []) as $po) {
            foreach ((array) ($po['blok'] ?? []) as $pb) {
                if (!empty($pb['denah_svg'])) $svgMap[($po['nama'] ?? '') . '|' . ($pb['nama'] ?? '')] = $pb['denah_svg'];
            }
        }

        foreach ($bloks as &$b) $b['denah_svg'] = $svgMap[$b['opsi'] . '|' . $b['blok']] ?? null;
        unset($b);

        return $this->tampilkanCuttingDenah($bloks, $judul, $peringatan);
    }

    /** Bagian tampilan bersama: hitung daftar potong per blok lalu render halaman cetak. */
    private function tampilkanCuttingDenah(array $bloks, string $judul, string $peringatan = '')
    {
        $rd = new \App\Services\RangkaDesignService();
        $stok = $this->stokMap();
        foreach ($bloks as &$b) $b['hasil'] = $rd->hitung($b['members'], [], false, $stok);   // tanpa harga
        unset($b);

        return view('cutting.print-denah', [
            'judul'      => $judul,
            'bloks'      => $bloks,
            'peringatan' => $peringatan,
            'tanggal'    => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Cutting list dari denah yang digambar LANGSUNG di halaman Cutting List
     * (tanpa lead). Members + foto denah dikirim dari editor di klien.
     */
    public function cuttingDenahManual(Request $request)
    {
        abort_if(!$this->bolehAkses(), 403);

        $members = json_decode((string) $request->input('members', '[]'), true);
        if (!is_array($members)) $members = [];
        $bersih = [];
        foreach (array_slice($members, 0, 3000) as $m) {
            $m = (array) $m;
            $mat = trim((string) ($m['material'] ?? ''));
            $len = (float) ($m['panjang'] ?? 0);
            if ($mat === '' || $len <= 0) continue;
            $bersih[] = ['nama' => (string) ($m['nama'] ?? ''), 'material' => $mat, 'panjang' => $len];
        }

        $svg = (string) $request->input('denah_svg', '');
        // Jangan telan SVG raksasa/aneh -- foto denah normal puluhan KB.
        if (strlen($svg) > 512 * 1024 || ($svg !== '' && !str_starts_with(ltrim($svg), '<svg'))) $svg = '';

        $judul = trim((string) $request->input('judul', '')) ?: 'Cutting List Denah';
        $bloks = $bersih ? [['opsi' => '', 'blok' => 'Denah', 'members' => $bersih, 'denah_svg' => $svg ?: null]] : [];

        return $this->tampilkanCuttingDenah($bloks, $judul,
            $bersih ? '' : 'Denah belum punya batang besi — gambar bentuknya dulu, baru buka cutting list.');
    }

    public function cuttingDenahLead(Request $request)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lead = DB::table('pipeline_leads')->where('id', (int) $request->query('lead'))->first();
        abort_if(!$lead, 404, 'Lead tidak ditemukan');
        return $this->renderCuttingDenah($lead, null,
            'Cutting List Denah — ' . ($lead->nama_customer ?? ('Lead #' . $lead->id)));
    }

    public function cuttingDenahProject(\App\Models\Project $project)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lead = $project->id_lead ? DB::table('pipeline_leads')->where('id', $project->id_lead)->first() : null;
        abort_if(!$lead, 404, 'Project ini tidak terhubung ke lead RAB');
        $deal = json_decode((string) ($lead->deal_json ?? ''), true);
        return $this->renderCuttingDenah($lead, $deal['opsi'] ?? null,
            'Cutting List Produksi — ' . ($project->nama_customer ?? $project->kode_project ?? ('Project #' . $project->id)));
    }

    public function cuttingDenah(Request $request, \App\Services\RangkaDesignService $rd)
    {
        abort_if(!$this->bolehAkses(), 403);

        $members = (array) $request->input('members', []);
        // Batas wajar: denah terpadat pun jauh di bawah ini. Mencegah request gemuk
        // dipakai membebani server (endpoint ini dipanggil otomatis & sering).
        if (count($members) > 3000) return response()->json(['ok' => false, 'message' => 'Terlalu banyak batang'], 422);

        $bersih = [];
        foreach ($members as $m) {
            $m   = (array) $m;
            $mat = trim((string) ($m['material'] ?? ''));
            $len = (float) ($m['panjang'] ?? 0);
            if ($mat === '' || $len <= 0) continue;
            $bersih[] = ['nama' => (string) ($m['nama'] ?? ''), 'material' => $mat, 'panjang' => $len];
        }

        $batang = [];
        foreach ($rd->hitung($bersih, [], false, $this->stokMap())['per_material'] as $r) {
            $batang[$r['material']] = (int) $r['jumlah_batang'];
        }

        return response()->json(['ok' => true, 'batang' => $batang]);
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
        $tipe = $b['tipe'] ?? 'kanopi';
        if (!in_array($tipe, ['kanopi', 'manual', 'denah'], true)) $tipe = 'kanopi';
        $nama = trim((string) ($b['nama'] ?? ''));
        $warn = [];
        $besi = 0.0; $rincian = null; $cutting = null;

        if ($tipe === 'kanopi') {
            // Blok tipe KANOPI DIPENSIUNKAN 28 Ags 2026 (keputusan Elvan). Pembuatan blok baru
            // memang sudah lama mati (tombolnya tak ada di wizard); sekarang jalur hitungnya ikut
            // dibuang. Data lama SENGAJA tidak dihitung diam-diam jadi 0 -- dikembalikan dengan
            // peringatan jelas supaya kelihatan harus dibuat ulang, bukan hilang tanpa jejak.
            // CATATAN: mesin CuttingService::hitungRangka TIDAK ikut dihapus -- masih dipakai
            // untuk bentuk awal blok Denah (RangkaDesignService) dan halaman cutting list.
            $warn[] = "Blok \"{$nama}\" bertipe KANOPI (format lama) sudah tidak didukung — buat ulang sebagai blok Denah.";
            $rincian = [];
            $cutting = ['per_material' => [], 'input' => ['L' => 0, 'P' => 0], 'luas_m2' => 0.0];
        } elseif ($tipe === 'denah') {
            // DENAH: daftar batang (members) dari denah interaktif -> RangkaDesignService.
            $members = array_map(fn ($m) => (array) $m, (array) ($b['members'] ?? []));
            $harga   = (array) ($b['harga'] ?? []);
            $rd = (new \App\Services\RangkaDesignService())
                ->hitung($members, $harga, $lihatHarga, $this->stokMap());
            $cutting = [
                'per_material' => $rd['per_material'],
                'input'        => ['L' => 0, 'P' => 0],
                'luas_m2'      => (float) ($b['luas_m2'] ?? 0),
            ];
            if ($lihatHarga) {
                $besi = (float) ($rd['total_biaya_besi'] ?? 0);
                foreach ((array) ($rd['warn'] ?? []) as $w) $warn[] = $w;
                // besi tambahan manual (kalau masih dipakai) — pola sama seperti kanopi
                foreach ((array) ($b['besi_extra'] ?? []) as $bx) {
                    $bx = (array) $bx;
                    $nm = trim((string) ($bx['material'] ?? '')); $bt = (float) ($bx['batang'] ?? 0);
                    if ($nm === '' || $bt <= 0) continue;
                    $h = isset($harga[$nm]) ? (float) $harga[$nm] : 0;
                    $besi += $bt * $h;
                    $cutting['per_material'][] = ['material' => $nm, 'jumlah_batang' => $bt, 'harga_pokok' => $h, 'subtotal_besi' => $h * $bt];
                    if ($h <= 0) $warn[] = "Harga besi tambahan \"{$nm}\" belum diisi";
                }
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
        if ($tipe !== 'manual') {
            $L = (float) ($cutting['input']['L'] ?? 0);
            $P = (float) ($cutting['input']['P'] ?? 0);
            $luas = ($tipe === 'denah')
                ? (float) ($cutting['luas_m2'] ?? 0)
                : $L * $P / 10000;
            $luasKanopiBlok = $luas;
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