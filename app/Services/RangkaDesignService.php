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
