<?php

namespace App\Services;

/**
 * Kalkulator potong besi (cutting-stock) untuk blok rangka kanopi.
 * Stock 600cm/batang, minimalkan batang, maksimal 1 sambungan per potong.
 * Output: jumlah batang (untuk harga) + cutting list bergaris (untuk produksi).
 */
class CuttingService
{
    const STOCK = 600; // cm per batang

    /**
     * Inti cutting-stock: First-Fit Decreasing + split (maks 1 sambungan).
     * @param array $pieces  [ ['label'=>..,'len'=>cm], ... ]
     * @return array bars: [ ['no'=>1,'seg'=>[ ['label','len','jenis'=>'utuh|sambung','jid'?] ],'sisa'=>cm], ... ]
     */
    public function potong(array $pieces): array
    {
        usort($pieces, fn($a, $b) => $b['len'] <=> $a['len']);
        $bars = [];
        $jid  = 0;

        foreach ($pieces as $p) {
            $len = (float) $p['len'];
            if ($len <= 0) continue;

            // 1) coba taruh utuh di batang yang masih muat
            $placed = false;
            foreach ($bars as &$b) {
                if ($b['sisa'] >= $len) {
                    $b['seg'][] = ['label' => $p['label'], 'len' => $len, 'jenis' => 'utuh'];
                    $b['sisa'] -= $len;
                    $placed = true;
                    break;
                }
            }
            unset($b);
            if ($placed) continue;

            // 2) tidak muat utuh -> split jadi 2 (1 sambungan) pakai 2 sisa terbesar
            $idx = [];
            foreach ($bars as $i => $b) if ($b['sisa'] > 0) $idx[] = $i;
            usort($idx, fn($a, $c) => $bars[$c]['sisa'] <=> $bars[$a]['sisa']);

            if (count($idx) >= 2 && ($bars[$idx[0]]['sisa'] + $bars[$idx[1]]['sisa']) >= $len) {
                $jid++;
                $a   = min($bars[$idx[0]]['sisa'], $len);
                $rem = $len - $a;
                $bars[$idx[0]]['seg'][] = ['label' => $p['label'], 'len' => $a,   'jenis' => 'sambung', 'jid' => $jid];
                $bars[$idx[0]]['sisa'] -= $a;
                $bars[$idx[1]]['seg'][] = ['label' => $p['label'], 'len' => $rem, 'jenis' => 'sambung', 'jid' => $jid];
                $bars[$idx[1]]['sisa'] -= $rem;
                continue;
            }

            // 3) buka batang baru
            $bars[] = ['sisa' => self::STOCK - $len, 'seg' => [['label' => $p['label'], 'len' => $len, 'jenis' => 'utuh']]];
        }

        // nomori batang
        foreach ($bars as $i => &$b) $b['no'] = $i + 1;
        unset($b);

        return $bars;
    }

    /**
     * Hitung satu blok rangka kanopi single.
     * @param array $in lebar_cm, panjang_cm, tinggi_cm, kotak_cm, arah_support(1|2),
     *                  jml_tiang, mat_frame, mat_support, mat_tiang
     * @return array daftar per-material + ringkasan
     */
    public function hitungRangka(array $in): array
    {
        $L      = max(0, (float)($in['lebar_cm']   ?? 0));
        $P      = max(0, (float)($in['panjang_cm'] ?? 0));
        $T      = max(0, (float)($in['tinggi_cm']  ?? 300));
        $kotak  = max(1, (float)($in['kotak_cm']   ?? 80));
        $arah   = ((int)($in['arah_support'] ?? 2) === 1) ? 1 : 2;
        $nTiang = max(0, (int)($in['jml_tiang'] ?? 2));

        $matFrame   = trim($in['mat_frame']   ?? 'Frame');
        $matSupport = trim($in['mat_support'] ?? 'Support');
        $matTiang   = trim($in['mat_tiang']   ?? 'Tiang');

        // pilihan sisi frame (default semua nyala) + frame tengah
        $bool = fn($v, $def) => array_key_exists($v, $in) ? (bool)$in[$v] : $def;
        $fDepan    = $bool('frame_depan', true);
        $fBelakang = $bool('frame_belakang', true);
        $fKiri     = $bool('frame_kiri', true);
        $fKanan    = $bool('frame_kanan', true);
        $fTengah   = $bool('frame_tengah', true);  // default ON (kanopi single)

        // kumpulkan potongan per material — tiap potong punya label SENDIRI
        $byMat = []; // nama_material => [ ['label','len'], ... ]
        $add = function ($mat, $label, $len) use (&$byMat) {
            $byMat[$mat][] = ['label' => $label, 'len' => $len];
        };

        // ===== PEMBAGIAN SIMETRIS: bagi lebar & panjang jadi sel sama besar =====
        $nK = max(2, (int)round($L / $kotak));
        if ($fTengah && $nK % 2 !== 0) $nK++;   // genapkan agar ada garis tengah pas
        $nR = max(2, (int)round($P / $kotak));
        if ($fTengah && $nR % 2 !== 0) $nR++;
        $cellL = $L / $nK;
        $cellR = $P / $nR;
        $midV = ($fTengah && $nK % 2 === 0) ? intdiv($nK, 2) : -1;
        $midH = ($fTengah && $nR % 2 === 0) ? intdiv($nR, 2) : -1;

        // garis support interior (kecuali garis tengah)
        $vSupX = [];
        for ($i = 1; $i < $nK; $i++) if ($i !== $midV) $vSupX[] = round($i * $cellL, 1);
        $hSupY = [];
        if ($arah === 2) {
            for ($i = 1; $i < $nR; $i++) if ($i !== $midH) $hSupY[] = round($i * $cellR, 1);
        }

        // ===== DENAH (posisi garis + nama) =====
        $denahV = []; $denahH = [];
        if ($fKiri)   $denahV[] = ['x' => 0,      'tipe' => 'frame', 'nama' => 'Frame kiri'];
        if ($fKanan)  $denahV[] = ['x' => $L,     'tipe' => 'frame', 'nama' => 'Frame kanan'];
        if ($fTengah) $denahV[] = ['x' => $L / 2, 'tipe' => 'frame', 'nama' => 'Frame tengah'];
        if ($fDepan)    $denahH[] = ['y' => 0,      'tipe' => 'frame', 'nama' => 'Frame depan'];
        if ($fBelakang) $denahH[] = ['y' => $P,     'tipe' => 'frame', 'nama' => 'Frame belakang'];
        if ($fTengah)   $denahH[] = ['y' => $P / 2, 'tipe' => 'frame', 'nama' => 'Frame tengah'];

        // ===== POTONGAN + nama S1..Sn (vertikal dulu, lalu horizontal) =====
        // frame membujur (vertikal, panjang = P)
        if ($fKiri)   $add($matFrame, 'Frame kiri', $P);
        if ($fKanan)  $add($matFrame, 'Frame kanan', $P);
        if ($fTengah) $add($matFrame, 'Frame tengah (membujur)', $P);
        // frame melintang (horizontal, panjang = L)
        if ($fDepan)    $add($matFrame, 'Frame depan', $L);
        if ($fBelakang) $add($matFrame, 'Frame belakang', $L);
        if ($fTengah)   $add($matFrame, 'Frame tengah (melintang)', $L);

        $s = 0;
        foreach ($vSupX as $x) { $s++; $add($matSupport, "S$s", $P); $denahV[] = ['x' => $x, 'tipe' => 'support', 'nama' => "S$s"]; }
        foreach ($hSupY as $y) { $s++; $add($matSupport, "S$s", $L); $denahH[] = ['y' => $y, 'tipe' => 'support', 'nama' => "S$s"]; }

        $nFrameV = ($fKiri ? 1 : 0) + ($fKanan ? 1 : 0) + ($fTengah ? 1 : 0);
        $nFrameH = ($fDepan ? 1 : 0) + ($fBelakang ? 1 : 0) + ($fTengah ? 1 : 0);

        // TIANG — ikut kotak pertama tiap sisi (di depan), dinomori
        $tiangPos = [];
        if ($nTiang > 0 && $T > 0) {
            for ($i = 1; $i <= $nTiang; $i++) $add($matTiang, "Tiang #$i", $T);
            if ($nTiang === 2 && count($vSupX) >= 1) {
                $tiangPos[] = ['x' => $vSupX[0], 'y' => $P, 'nama' => 'Tiang #1'];
                $tiangPos[] = ['x' => end($vSupX), 'y' => $P, 'nama' => 'Tiang #2'];
            } else {
                for ($i = 1; $i <= $nTiang; $i++) $tiangPos[] = ['x' => round($i * $L / ($nTiang + 1), 1), 'y' => $P, 'nama' => "Tiang #$i"];
            }
        }

        // hitung per material
        $hasil = [];
        foreach ($byMat as $mat => $pieces) {
            $bars = $this->potong($pieces);
            $joins = 0;
            foreach ($bars as $b) foreach ($b['seg'] as $sg) if (($sg['jenis'] ?? '') === 'sambung') { $joins++; }
            $joins = intdiv($joins, 2);
            $hasil[] = [
                'material'      => $mat,
                'jumlah_batang' => count($bars),
                'sambungan'     => $joins,
                'bars'          => $bars,
                'jml_potong'    => count($pieces),
            ];
        }

        return [
            'input' => compact('L', 'P', 'T', 'kotak', 'arah', 'nTiang') + ['frame_tengah' => $fTengah],
            'denah' => [
                'L' => $L, 'P' => $P, 'T' => $T,
                'kotak_l' => round($cellL, 1), 'kotak_p' => round($cellR, 1),
                'v' => $denahV, 'h' => $denahH, 'tiang' => $tiangPos,
            ],
            'rincian_jumlah' => [
                'frame_vertikal'     => ['qty' => $nFrameV, 'len' => $P],
                'frame_horizontal'   => ['qty' => $nFrameH, 'len' => $L],
                'support_vertikal'   => ['qty' => count($vSupX), 'len' => $P],
                'support_horizontal' => ['qty' => count($hSupY), 'len' => $L],
                'tiang'              => ['qty' => $nTiang, 'len' => $T],
            ],
            'per_material'  => $hasil,
            'total_batang'  => array_sum(array_column($hasil, 'jumlah_batang')),
        ];
    }
}