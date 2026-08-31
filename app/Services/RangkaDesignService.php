<?php

namespace App\Services;

/**
 * Perancang Rangka (Fase 1) — mesin "daftar batang -> cutting per besi + biaya".
 * Product-agnostic: menerima daftar batang apa pun. Membungkus CuttingService
 * (mesin potong 600cm/batang, sudah fix >600cm 14 Juli 2026).
 */
class RangkaDesignService
{
    public function __construct(
        private CuttingService $cutting = new CuttingService()
    ) {}

    /**
     * Hitung batang + sambungan + biaya per besi dari daftar batang.
     *
     * @param array $members  tiap: ['nama'=>string,'panjang'=>float,'material'=>string, ...]
     * @param array $harga    ['<material>' => <harga_pokok>]
     */
    /**
     * Ambil blok denah dari rab_snapshot lead (JSON autosave RAB Multi-Opsi).
     * MURNI (tanpa DB) supaya bisa dites. Dipakai 2 pintu:
     * - halaman Cutting List (kalibrasi): $opsiDeal null -> semua opsi
     * - halaman Project (produksi): $opsiDeal = nama opsi dari deal_json
     * Nama opsi deal yang tak ketemu (data diedit setelah deal) TIDAK membuat hasil
     * kosong diam-diam -- semua opsi dikembalikan, biar pemanggil yang menandai.
     * Blok nonaktif & blok tanpa members dilewati (tak ada yang bisa dipotong).
     *
     * @return array{opsi:string, blok:string, members:array, warns:string[]}[]
     */
    public function blokDenahDariSnapshot(array $snap, ?string $opsiDeal = null): array
    {
        $panes = $snap['panes'] ?? null;
        if (!is_array($panes)) return [];

        $ambil = function (?string $filter) use ($panes): array {
            $out = [];
            foreach ($panes as $i => $p) {
                $p = (array) $p;
                $namaOpsi = trim((string) ($p['nama'] ?? '')) ?: ('Opsi ' . ($i + 1));
                if ($filter !== null && $namaOpsi !== $filter) continue;
                foreach ((array) ($p['blok'] ?? []) as $j => $b) {
                    $b = (array) $b;
                    if (($b['tipe'] ?? '') !== 'denah') continue;
                    if (array_key_exists('aktif', $b) && filter_var($b['aktif'], FILTER_VALIDATE_BOOLEAN) === false) continue;
                    $members = array_values(array_filter(
                        array_map(fn ($m) => (array) $m, (array) ($b['members'] ?? [])),
                        fn ($m) => trim((string) ($m['material'] ?? '')) !== '' && (float) ($m['panjang'] ?? 0) > 0
                    ));
                    if (!$members) continue;
                    // Penyimpangan sengaja dari brief (verbatim brief cuma array_slice+strval):
                    // rab_snapshot ditulis klien lewat autosave, sama tak-terpercaya-nya dengan
                    // 'warns' di cuttingDenahManual (CuttingController) -- disamakan supaya tak
                    // asimetris: batasi PANJANG tiap item (bukan cuma jumlah), dan buang elemen
                    // non-scalar (array/objek bersarang) SEBELUM strval -- strval atas array
                    // memicu "Warning: Array to string conversion" dan hasilnya literal "Array".
                    $warns = [];
                    foreach (array_slice((array) ($b['denah_warns'] ?? []), 0, 20) as $w) {
                        if (!is_scalar($w)) continue;
                        $w = substr(trim((string) $w), 0, 200); // substr, bukan mb_substr: ext-mbstring
                        // tak tersedia di CLI VPS ini (mb_substr FATAL saat dites nyata di sini,
                        // padahal php -l/canopi-check --full tak pernah mengeksekusinya) --
                        // dipotong per-byte, bukan per-karakter. Batas cuma pengaman panjang
                        // wajar, bukan titik presisi UTF-8, jadi diterima.
                        if ($w !== '') $warns[] = $w;
                    }
                    $out[] = [
                        'opsi'    => $namaOpsi,
                        'blok'    => trim((string) ($b['nama'] ?? '')) ?: ('Blok ' . ($j + 1)),
                        'members' => $members,
                        'warns'   => $warns,
                    ];
                }
            }
            return $out;
        };

        $out = $ambil($opsiDeal);
        if ($opsiDeal !== null && !$out) $out = $ambil(null);
        return $out;
    }

    public function hitung(array $members, array $harga = [], bool $lihatHarga = false, array $stok = []): array
    {
        // Kelompokkan panjang per besi
        $byMat = [];
        foreach ($members as $m) {
            $mat = trim((string) ($m['material'] ?? ''));
            $len = (float) ($m['panjang'] ?? 0);
            if ($mat === '' || $len <= 0) continue;
            $byMat[$mat][] = ['label' => (string) ($m['nama'] ?? ''), 'len' => $len];
        }

        $per = [];
        $warn = [];
        $totalBatang = 0;
        $totalBiaya = 0.0;

        foreach ($byMat as $mat => $pieces) {
            $bars = $this->cutting->potong($pieces, $stok[$mat] ?? null);
            $segPerJid = [];
            foreach ($bars as $b) {
                foreach ($b['seg'] as $s) {
                    if (($s['jenis'] ?? '') === 'sambung' && isset($s['jid'])) {
                        $segPerJid[$s['jid']] = ($segPerJid[$s['jid']] ?? 0) + 1;
                    }
                }
            }
            $joins = 0;
            foreach ($segPerJid as $cnt) $joins += max(0, $cnt - 1);
            $batang = count($bars);

            $h = ($lihatHarga && isset($harga[$mat])) ? (float) $harga[$mat] : null;
            $sub = $h !== null ? $h * $batang : null;
            if ($lihatHarga && ($h === null || $h <= 0)) {
                $warn[] = "Harga besi \"$mat\" belum diisi";
            }

            $per[] = [
                'material'      => $mat,
                'jumlah_batang' => $batang,
                'sambungan'     => $joins,
                'harga_pokok'   => $h,
                'subtotal_besi' => $sub,
                'jml_potong'    => count($pieces),
                // Daftar potong per batang -- dipakai halaman cetak cutting list produksi/
                // kalibrasi. Dulu dihitung lalu DIBUANG di sini (cuma jumlahnya yang keluar).
                'bars'          => $bars,
            ];
            $totalBatang += $batang;
            if ($sub !== null) $totalBiaya += $sub;
        }

        return [
            'per_material'     => $per,
            'total_batang'     => $totalBatang,
            'total_biaya_besi' => $lihatHarga ? $totalBiaya : null,
            'warn'             => array_values(array_unique($warn)),
        ];
    }

    /**
     * Ubah 1 kotak jadi daftar batang rata (frame/support/tiang) + posisi,
     * memakai hitungRangka. Hasil ini yang jadi seed awal untuk diedit.
     */
    public function seedDariKotak(array $in): array
    {
        $r = $this->cutting->hitungRangka($in);
        $matFrame   = trim((string) ($in['mat_frame']   ?? 'Frame'));
        $matSupport = trim((string) ($in['mat_support'] ?? 'Support'));
        $matTiang   = trim((string) ($in['mat_tiang']   ?? 'Tiang'));

        $L = (float) ($r['denah']['L'] ?? 0);
        $P = (float) ($r['denah']['P'] ?? 0);
        $T = (float) ($r['denah']['T'] ?? 0);

        $members = [];
        // Garis vertikal (membujur) panjang = P
        foreach ($r['denah']['v'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => $ln['tipe'],
                'panjang'  => $P,
                'arah'     => 'vertikal',
                'posisi'   => ['x' => $ln['x']],
                'material' => $ln['tipe'] === 'frame' ? $matFrame : $matSupport,
            ];
        }
        // Garis horizontal (melintang) panjang = L
        foreach ($r['denah']['h'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => $ln['tipe'],
                'panjang'  => $L,
                'arah'     => 'horizontal',
                'posisi'   => ['y' => $ln['y']],
                'material' => $ln['tipe'] === 'frame' ? $matFrame : $matSupport,
            ];
        }
        // Tiang panjang = T
        foreach ($r['denah']['tiang'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => 'tiang',
                'panjang'  => $T,
                'arah'     => 'tiang',
                'posisi'   => ['x' => $ln['x'], 'y' => $ln['y']],
                'material' => $matTiang,
            ];
        }

        return ['members' => $members, 'denah' => $r['denah']];
    }
}
