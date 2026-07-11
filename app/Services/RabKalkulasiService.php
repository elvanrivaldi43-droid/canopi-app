<?php
namespace App\Services;

use App\Models\RabZonaBentangan;
use App\Models\RabPaketKonstruksi;
use App\Models\RabAtap;
use App\Models\RabAddon;
use App\Models\RabKondisiLokasi;
use App\Models\RabMarginSetting;
use App\Models\RabHeader;
use App\Models\RabItem;
use App\Models\RabVersi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RabKalkulasiService
{
    const BIAYA_TETAP    = 1300000;  // mobilisasi + operasional per project
    const MINIMUM_HARGA  = 5000000;  // minimum project
    const PANJANG_BATANG = 6;        // meter per batang hollow/WF
    const UPAH_TUKANG    = 170000;   // per hari
    const UPAH_KENEK     = 120000;   // per hari
    const PRODUKTIVITAS  = 10;       // m² per hari per tim
    const AKSESORI_PER_M2 = 18000;   // ring, roofing, sealant, cat, baut

    // ============================================================
    // QUICK QUOTE — MODE BOM (Bill of Materials otomatis)
    // ============================================================
    public function hitungQuickQuote(array $input): array
    {
        $panjang    = (float)($input['panjang'] ?? 0);
        $lebar      = (float)($input['lebar'] ?? 0);
        $bentangan  = (float)($input['bentangan'] ?? $lebar);
        $m2         = $panjang * $lebar;
        $produkKode = $input['produk_kode'] ?? 'KANOPI_STD';
        $atapId     = (int)($input['atap_id'] ?? 0);
        $addons     = $input['addons'] ?? [];
        $kondisis   = $input['kondisis'] ?? [];

        // Zona bentangan (untuk info + rekomendasi)
        $zona   = RabZonaBentangan::cariZona($bentangan);
        $zonaId = $zona ? $zona->id : 1;

        // Atap
        $atap = $atapId ? RabAtap::find($atapId) : null;

        // ====== BOM: hitung material detail ======
        $bom = $this->hitungBOM($input, $panjang, $lebar, $m2, $atap);

        // Addon
        $biayaAddonDetail = $this->hitungAddons($addons, $m2);
        $totalAddon       = array_sum(array_column($biayaAddonDetail, 'total'));

        // Kondisi lokasi
        $kondisiDetail = $this->hitungKondisiLokasi($kondisis);

        // Margin setting
        $marginSetting = RabMarginSetting::byProduk($produkKode);
        $marginStandar = $marginSetting ? (float)$marginSetting->margin_standar_persen : 35;
        $marginMin     = $marginSetting ? (float)$marginSetting->margin_min_persen : 28;
        $marginTarget  = $marginSetting ? (float)$marginSetting->margin_target_persen : 45;
        $diskonMax     = $marginSetting ? (float)$marginSetting->diskon_max_persen : 10;

        // ====== TOTAL BIAYA POKOK ======
        $biayaRangka = $bom['total_struktur'];          // frame + gording + tiang + aksesori
        $biayaAtap   = $bom['atap']['total'];
        $biayaJasa   = $bom['upah']['total'];
        $biayaPokok  = self::BIAYA_TETAP + $biayaRangka + $biayaAtap + $biayaJasa + $totalAddon;

        // Kondisi: persen dihitung dari biaya pokok
        $totalKondisi = 0;
        foreach ($kondisiDetail as &$k) {
            if ($k['tipe'] === 'persen_add') {
                $k['total'] = $biayaPokok * ($k['nilai'] / 100);
            }
            $totalKondisi += $k['total'];
        }
        unset($k);

        $biayaPokokTotal = $biayaPokok + $totalKondisi;

        // Harga jual: margin dari harga jual
        $hargaNormal = max($biayaPokokTotal / (1 - $marginStandar / 100), self::MINIMUM_HARGA);
        $hargaMin    = max($biayaPokokTotal / (1 - $marginMin / 100), self::MINIMUM_HARGA);
        $hargaTarget = max($biayaPokokTotal / (1 - $marginTarget / 100), self::MINIMUM_HARGA);

        $versi = [[
            'paket_id'             => $bom['paket_id_ref'],
            'label'                => 'Standar',
            'konstruksi'           => $bom['label_konstruksi'],
            'frame'                => $bom['frame']['nama'] ?? '-',
            'metode'               => 'BOM',
            'interval_kremona'     => null,
            'biaya_tetap'          => self::BIAYA_TETAP,
            'biaya_rangka'         => $biayaRangka,
            'biaya_atap'           => $biayaAtap,
            'biaya_jasa'           => $biayaJasa,
            'biaya_addon'          => $totalAddon,
            'biaya_kondisi'        => $totalKondisi,
            'biaya_pokok_total'    => $biayaPokokTotal,
            'biaya_setelah_buffer' => $biayaPokokTotal,
            'margin_persen'        => $marginStandar,
            'harga_normal'         => round($hargaNormal),
            'harga_min_bep'        => round($hargaMin),
            'harga_target'         => round($hargaTarget),
            'diskon_max_persen'    => $diskonMax,
        ]];

        return [
            'produk_kode'    => $produkKode,
            'panjang'        => $panjang,
            'lebar'          => $lebar,
            'm2_total'       => $m2,
            'bentangan'      => $bentangan,
            'zona_id'        => $zonaId,
            'zona_nama'      => $zona ? $zona->nama : 'Zona 1',
            'atap'           => $atap ? ['id'=>$atap->id,'nama'=>$atap->nama,'kode'=>$atap->kode] : null,
            'biaya_atap'     => $biayaAtap,
            'addon_detail'   => $biayaAddonDetail,
            'kondisi_detail' => $kondisiDetail,
            'bom'            => $bom,
            'versi'          => $versi,
            'margin_min'     => $marginMin,
            'diskon_max'     => $diskonMax,
            'peringatan'     => $bom['peringatan'] ?? [],
            'harga_valid'    => $bom['harga_valid'] ?? true,
        ];
    }

    // ============================================================
    // BOM ENGINE — hitung material seperti surveyor manual
    // ============================================================
    private function hitungBOM(array $input, float $panjang, float $lebar, float $m2, ?RabAtap $atap): array
    {
        // Material pilihan surveyor (dari master_material)
        $frame   = $this->getMaterialMaster($input['frame_material_id'] ?? null);
        $gording = $this->getMaterialMaster($input['gording_material_id'] ?? null);
        $tiang   = $this->getMaterialMaster($input['tiang_material_id'] ?? null);

        $jarakCm     = (float)($input['jarak_gording_cm'] ?? 80);
        $jumlahTiang = (int)($input['jumlah_tiang'] ?? max(2, ceil($panjang / 3) + 1));
        $tinggiTiang = (float)($input['tinggi_tiang_m'] ?? 3);

        // ── PENGAMAN: JANGAN pakai harga tebakan. Kalau material inti tak ketemu, KASIH PERINGATAN ──
        $peringatan = [];
        $hargaFrame = $frame['harga'] ?? null;
        if (!$hargaFrame || $hargaFrame <= 0) {
            $peringatan[] = 'Material RANGKA (frame) belum dipilih atau tidak ada di Master Material — biaya rangka belum dihitung.';
            $hargaFrame = 0;
        }
        $hargaGording = $gording['harga'] ?? null;
        if (!$hargaGording || $hargaGording <= 0) {
            $peringatan[] = 'Material GORDING belum dipilih atau tidak ada di Master Material — biaya gording belum dihitung.';
            $hargaGording = 0;
        }
        $hargaTiang = $tiang['harga'] ?? null;
        if (!$hargaTiang || $hargaTiang <= 0) {
            $peringatan[] = 'Material TIANG belum dipilih atau tidak ada di Master Material — biaya tiang belum dihitung.';
            $hargaTiang = 0;
        }

        // ---- 1. FRAME KELILING ----
        $kelilingM   = 2 * ($panjang + $lebar);
        $frameBatang = (int)ceil($kelilingM / self::PANJANG_BATANG);
        $frameTotal  = $frameBatang * $hargaFrame;

        // ---- 2. GORDING (jalur melintang lebar, jarak sepanjang panjang) ----
        $jarakM      = max($jarakCm / 100, 0.3);
        $jumlahJalur = (int)ceil($panjang / $jarakM);
        $gordingBatang = $this->hitungBatangEfisien($jumlahJalur, $lebar);
        $gordingTotal  = $gordingBatang * $hargaGording;

        // ---- 3. TIANG ----
        $tiangBatang = $this->hitungBatangEfisien($jumlahTiang, $tinggiTiang);
        $tiangTotal  = $tiangBatang * $hargaTiang;

        // ---- 4. ATAP (dari dimensi aktual) ----
        $atapDetail = $this->hitungAtapDetail($atap, $panjang, $lebar);
        if (!empty($atapDetail['perlu_peringatan'])) {
            $peringatan[] = 'Material ATAP belum dipilih atau harga atap belum diatur di Master Material — biaya atap belum dihitung.';
        }

        // ---- 5. AKSESORI ----
        $aksesoriTotal = $m2 * self::AKSESORI_PER_M2;

        // ---- 6. UPAH ----
        $hariKerja = max(2, (int)ceil($m2 / self::PRODUKTIVITAS));
        $upahTotal = $hariKerja * (self::UPAH_TUKANG + self::UPAH_KENEK);

        // Label konstruksi untuk tampilan
        $labelKonstruksi = ($frame['nama'] ?? 'Frame std') . ' + gording ' . ($gording['nama'] ?? 'std');

        // Paket referensi (untuk kompatibilitas simpan)
        $zona  = RabZonaBentangan::cariZona((float)($input['bentangan'] ?? $lebar));
        $paket = $zona ? RabPaketKonstruksi::where('zona_id', $zona->id)->where('nama_paket','Standar')->first() : null;

        return [
            'frame' => [
                'nama' => $frame['nama'] ?? 'Default 4x8', 'harga_satuan' => $hargaFrame,
                'meter' => round($kelilingM, 1), 'batang' => $frameBatang, 'total' => $frameTotal,
            ],
            'gording' => [
                'nama' => $gording['nama'] ?? 'Default 4x6', 'harga_satuan' => $hargaGording,
                'jalur' => $jumlahJalur, 'jarak_cm' => $jarakCm, 'batang' => $gordingBatang, 'total' => $gordingTotal,
            ],
            'tiang' => [
                'nama' => $tiang['nama'] ?? 'Default 4x8', 'harga_satuan' => $hargaTiang,
                'titik' => $jumlahTiang, 'tinggi_m' => $tinggiTiang, 'batang' => $tiangBatang, 'total' => $tiangTotal,
            ],
            'atap'     => $atapDetail,
            'aksesori' => ['total' => $aksesoriTotal, 'per_m2' => self::AKSESORI_PER_M2],
            'upah'     => ['hari' => $hariKerja, 'per_hari' => self::UPAH_TUKANG + self::UPAH_KENEK, 'total' => $upahTotal],
            'total_struktur' => $frameTotal + $gordingTotal + $tiangTotal + $aksesoriTotal,
            'label_konstruksi' => $labelKonstruksi,
            'paket_id_ref' => $paket ? $paket->id : null,
            'peringatan'   => $peringatan,
            'harga_valid'  => empty($peringatan),
        ];
    }

    /**
     * Hitung jumlah batang dengan potongan efisien
     * Contoh: 5 jalur × 3m → 1 batang 6m = 2 jalur → butuh 3 batang
     */
    private function hitungBatangEfisien(int $jumlahPotongan, float $panjangPotongan): int
    {
        if ($jumlahPotongan <= 0 || $panjangPotongan <= 0) return 0;

        if ($panjangPotongan <= self::PANJANG_BATANG) {
            $potonganPerBatang = (int)floor(self::PANJANG_BATANG / $panjangPotongan);
            return (int)ceil($jumlahPotongan / max($potonganPerBatang, 1));
        }

        // Potongan lebih panjang dari 6m → perlu sambungan
        $batangPerPotongan = (int)ceil($panjangPotongan / self::PANJANG_BATANG);
        return $jumlahPotongan * $batangPerPotongan;
    }

    private function hitungAtapDetail(?RabAtap $atap, float $panjang, float $lebar): array
    {
        if (!$atap || !$atap->harga_per_lembar) {
            return ['nama' => 'Belum dipilih', 'lembar' => 0, 'panjang_lembar' => $panjang, 'harga_per_m' => 0, 'total' => 0, 'perlu_peringatan' => true];
        }

        $lebarLembarM = ($atap->lebar_lembar_cm ?? 80) / 100;
        if ($lebarLembarM <= 0) $lebarLembarM = 0.8;

        $jumlahLembar = (int)ceil($lebar / $lebarLembarM);
        $total = $jumlahLembar * $panjang * (float)$atap->harga_per_lembar;

        return [
            'nama'           => $atap->nama,
            'lembar'         => $jumlahLembar,
            'panjang_lembar' => $panjang,
            'harga_per_m'    => (float)$atap->harga_per_lembar,
            'total'          => $total,
        ];
    }

    /**
     * Ambil material dari master_material dengan deteksi kolom dinamis
     */
    private function getMaterialMaster($id): ?array
    {
        if (!$id) return null;
        try {
            $cols = Schema::getColumnListing('master_material');
            $namaCol  = in_array('nama', $cols) ? 'nama' : (in_array('nama_barang', $cols) ? 'nama_barang' : 'nama_material');
            $hargaCol = in_array('harga_pokok', $cols) ? 'harga_pokok' : (in_array('harga', $cols) ? 'harga' : 'harga_satuan');

            $row = DB::table('master_material')->where('id', $id)->first();
            if (!$row) return null;

            return [
                'id'    => $row->id,
                'nama'  => $row->{$namaCol} ?? 'Material',
                'harga' => (float)($row->{$hargaCol} ?? 0),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    // ============================================================
    // SIMPAN RAB
    // ============================================================
    public function simpanRab(array $input, int $userId): RabHeader
    {
        $kalkulasi    = $this->hitungQuickQuote($input);
        $versiDipilih = $kalkulasi['versi'][0];
        $bom          = $kalkulasi['bom'];

        $diskonPersen = (float)($input['diskon_persen'] ?? 0);

        $hargaFinal = isset($input['harga_final_deal']) && $input['harga_final_deal'] > 0
            ? (float)$input['harga_final_deal']
            : $versiDipilih['harga_normal'] * (1 - $diskonPersen / 100);

        $hargaFinal = max($hargaFinal, self::MINIMUM_HARGA);

        $rab = RabHeader::create([
            'nomor_rab'            => RabHeader::generateNomor(),
            'pipeline_lead_id'     => $input['pipeline_lead_id'] ?? null,
            'produk_kode'          => $kalkulasi['produk_kode'],
            'paket_konstruksi_id'  => $versiDipilih['paket_id'],
            'atap_id'              => $input['atap_id'] ?? null,
            'panjang'              => $kalkulasi['panjang'],
            'lebar'                => $kalkulasi['lebar'],
            'm2_total'             => $kalkulasi['m2_total'],
            'bentangan_max'        => $kalkulasi['bentangan'],
            'zona_id'              => $kalkulasi['zona_id'],
            'biaya_rangka'         => $versiDipilih['biaya_rangka'],
            'biaya_atap'           => $versiDipilih['biaya_atap'],
            'biaya_jasa'           => $versiDipilih['biaya_jasa'],
            'biaya_addon'          => $versiDipilih['biaya_addon'],
            'biaya_kondisi'        => $versiDipilih['biaya_kondisi'],
            'biaya_pokok_total'    => $versiDipilih['biaya_pokok_total'],
            'buffer_persen'        => 0,
            'biaya_setelah_buffer' => $versiDipilih['biaya_pokok_total'],
            'margin_persen'        => $versiDipilih['margin_persen'],
            'harga_sebelum_diskon' => $versiDipilih['harga_normal'],
            'diskon_persen'        => $diskonPersen,
            'diskon_nominal'       => max($versiDipilih['harga_normal'] - $hargaFinal, 0),
            'harga_final'          => $hargaFinal,
            'catatan_surveyor'     => $input['catatan'] ?? null,
            'status'               => 'draft',
            'tahap'                => 'quick',
            'dibuat_oleh'          => $userId,
        ]);

        $this->simpanItemsBOM($rab, $bom, $kalkulasi);

        RabVersi::create([
            'rab_id'              => $rab->id,
            'label'               => 'Standar',
            'paket_konstruksi_id' => $versiDipilih['paket_id'],
            'harga_final'         => $versiDipilih['harga_normal'],
            'margin_persen'       => $versiDipilih['margin_persen'],
            'detail_json'         => $versiDipilih,
            'dipilih'             => 1,
        ]);

        return $rab;
    }

    /**
     * Simpan item BOM detail — berguna untuk panduan produksi nanti
     */
    private function simpanItemsBOM(RabHeader $rab, array $bom, array $kalkulasi): void
    {
        $urutan = 1;

        RabItem::create([
            'rab_id' => $rab->id, 'tipe' => 'rangka',
            'nama_item' => 'Frame keliling: ' . $bom['frame']['nama'],
            'satuan' => 'batang', 'qty' => $bom['frame']['batang'],
            'harga_satuan' => $bom['frame']['harga_satuan'],
            'total' => $bom['frame']['total'], 'urutan' => $urutan++,
        ]);

        RabItem::create([
            'rab_id' => $rab->id, 'tipe' => 'rangka',
            'nama_item' => 'Gording: ' . $bom['gording']['nama'] . ' (jarak ' . $bom['gording']['jarak_cm'] . 'cm, ' . $bom['gording']['jalur'] . ' jalur)',
            'satuan' => 'batang', 'qty' => $bom['gording']['batang'],
            'harga_satuan' => $bom['gording']['harga_satuan'],
            'total' => $bom['gording']['total'], 'urutan' => $urutan++,
        ]);

        RabItem::create([
            'rab_id' => $rab->id, 'tipe' => 'rangka',
            'nama_item' => 'Tiang: ' . $bom['tiang']['nama'] . ' (' . $bom['tiang']['titik'] . ' titik × ' . $bom['tiang']['tinggi_m'] . 'm)',
            'satuan' => 'batang', 'qty' => $bom['tiang']['batang'],
            'harga_satuan' => $bom['tiang']['harga_satuan'],
            'total' => $bom['tiang']['total'], 'urutan' => $urutan++,
        ]);

        if (($bom['atap']['lembar'] ?? 0) > 0) {
            RabItem::create([
                'rab_id' => $rab->id, 'tipe' => 'atap', 'referensi_id' => $rab->atap_id,
                'nama_item' => 'Atap ' . $bom['atap']['nama'] . ' (' . $bom['atap']['lembar'] . ' lembar × ' . $bom['atap']['panjang_lembar'] . 'm)',
                'satuan' => 'lembar', 'qty' => $bom['atap']['lembar'],
                'harga_satuan' => $bom['atap']['panjang_lembar'] * ($bom['atap']['harga_per_m'] ?? 0),
                'total' => $bom['atap']['total'], 'urutan' => $urutan++,
            ]);
        } else {
            RabItem::create([
                'rab_id' => $rab->id, 'tipe' => 'atap', 'referensi_id' => $rab->atap_id,
                'nama_item' => 'Atap ' . ($bom['atap']['nama'] ?? '-'),
                'satuan' => 'ls', 'qty' => 1,
                'harga_satuan' => $bom['atap']['total'],
                'total' => $bom['atap']['total'], 'urutan' => $urutan++,
            ]);
        }

        RabItem::create([
            'rab_id' => $rab->id, 'tipe' => 'rangka',
            'nama_item' => 'Aksesori (ring, roofing, sealant, cat)',
            'satuan' => 'm2', 'qty' => $kalkulasi['m2_total'],
            'harga_satuan' => $bom['aksesori']['per_m2'],
            'total' => $bom['aksesori']['total'], 'urutan' => $urutan++,
        ]);

        RabItem::create([
            'rab_id' => $rab->id, 'tipe' => 'jasa',
            'nama_item' => 'Upah tim (' . $bom['upah']['hari'] . ' hari × tukang+kenek) + Biaya tetap',
            'satuan' => 'ls', 'qty' => 1,
            'harga_satuan' => $bom['upah']['total'] + self::BIAYA_TETAP,
            'total' => $bom['upah']['total'] + self::BIAYA_TETAP, 'urutan' => $urutan++,
        ]);

        foreach ($kalkulasi['addon_detail'] as $a) {
            RabItem::create([
                'rab_id' => $rab->id, 'tipe' => 'addon', 'referensi_id' => $a['id'],
                'nama_item' => $a['nama'], 'satuan' => $a['satuan'],
                'qty' => $a['qty'], 'harga_satuan' => $a['harga_sat'],
                'total' => $a['total'], 'urutan' => $urutan++,
            ]);
        }

        foreach ($kalkulasi['kondisi_detail'] as $k) {
            if ($k['total'] > 0) {
                RabItem::create([
                    'rab_id' => $rab->id, 'tipe' => 'kondisi', 'referensi_id' => $k['id'],
                    'nama_item' => $k['nama'], 'satuan' => 'ls',
                    'qty' => 1, 'harga_satuan' => $k['total'],
                    'total' => $k['total'], 'urutan' => $urutan++,
                ]);
            }
        }
    }

    // ============================================================
    // HELPERS LAMA (tetap dipakai)
    // ============================================================
    private function hitungAddons(array $addons, float $m2): array
    {
        $result = [];
        foreach ($addons as $a) {
            $addon = RabAddon::find($a['id'] ?? 0);
            if (!$addon) continue;
            $qty   = (float)($a['qty'] ?? $addon->qty_default ?? 1);
            $total = $qty * (float)$addon->harga_satuan;
            $result[] = [
                'id' => $addon->id, 'kode' => $addon->kode, 'nama' => $addon->nama,
                'satuan' => $addon->satuan, 'qty' => $qty,
                'harga_sat' => $addon->harga_satuan, 'total' => $total,
            ];
        }
        return $result;
    }

    private function hitungKondisiLokasi(array $kondisiIds): array
    {
        $result   = [];
        $kondisis = RabKondisiLokasi::whereIn('id', $kondisiIds)->get();
        foreach ($kondisis as $k) {
            $result[] = [
                'id' => $k->id, 'kode' => $k->kode, 'nama' => $k->nama,
                'tipe' => $k->tipe, 'nilai' => (float)$k->nilai,
                'total' => $k->tipe === 'flat_add' ? (float)$k->nilai : 0,
            ];
        }
        return $result;
    }
}