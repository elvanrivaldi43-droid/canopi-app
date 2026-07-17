# Desain: DenahEditor Kelompok C — saran kotak support 2 arah independen

**Tanggal:** 17 Juli 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** #10 di `MEMORI_PROYEK.md` — 6 permintaan update UI DenahEditor, dipecah 3 kelompok (A/B/C). Kelompok A (zoom/ukuran/ortho-support/ribbon/fullscreen) dan Kelompok B (drag-pindah-besi + snap-tengah) sudah selesai & di-deploy production. Ini Kelompok C: poin 6 dari 6 permintaan asli — **satu-satunya item** di kelompok ini, tidak digabung poin lain.

---

## 1. Ringkasan

Sekarang ukuran kotak support (`S.kotak`) cuma **1 angka**, dipaksa dipakai sama rata untuk arah horizontal maupun vertikal — kotak support selalu harus persegi. Kelompok C memisahkan ini jadi **2 nilai independen**: jarak sepanjang Lebar dan jarak sepanjang Panjang, masing-masing punya saran sendiri — kotak support bisa jadi persegi panjang kalau memang lebih pas buat bentuk denahnya.

Ditambah 1 cara baru mengisi angka itu: selain ketik cm langsung, surveyor bisa ketik **jumlah kolom** yang diinginkan per sumbu, dan mesin balik menghitung cm-nya (simetris, dibagi rata).

Berlaku untuk **seluruh bentuk denah sebagai satu kesatuan** — termasuk bagian yang berasal dari "+ Tambah Kotak" (Gabungan Kotak, #9), karena begitu ditempel, kotak itu sudah menyatu jadi 1 bentuk poligon (bukan area terpisah dengan pengaturan sendiri).

---

## 2. Kenapa dibutuhkan

Permintaan asli Elvan (16 Juli, poin 6 dari 6): saran ukuran kotak (`DenahConv.saranKotak`) cuma menghitung dari Lebar, dipakai juga untuk Panjang — padahal Lebar dan Panjang denah sering beda jauh, jadi hasil kotak kurang pas kalau dipaksa persegi. Selama brainstorming (17 Juli), muncul kebutuhan tambahan: surveyor di lapangan sering berpikir dalam "berapa kolom" (misal "mau 10 kolom di sisi ini"), bukan langsung dalam cm — jadi perlu jalur masuk lewat jumlah kolom juga, bukan cuma cm.

---

## 3. Keputusan yang dikunci

### 3.1 Data model

| # | Keputusan | Detail |
|---|---|---|
| 1 | State baru | `S.kotakLebar` (cm, jarak antar garis support sepanjang sisi Lebar) + `S.kotakPanjang` (cm, sepanjang sisi Panjang), menggantikan `S.kotak`. `S.autoKotakLebar` + `S.autoKotakPanjang` (boolean, per sumbu), menggantikan `S.autoKotak`. |
| 2 | Kolom (jumlah bagian) | **Tidak disimpan sebagai data terpisah.** Kolom yang ditampilkan di layar selalu dihitung ULANG dari `kotakLebar`/`kotakPanjang` yang tersimpan (`kolom = round(ukuran ÷ kotak)`). cm tetap satu-satunya sumber kebenaran — mencegah kolom & cm jadi tidak sinkron kalau ukuran berubah lewat jalur lain (resize, drag, dsb). |
| 3 | Migrasi data lama | Model denah lama (cuma punya `S.kotak`/`S.autoKotak`, dari sebelum Kelompok C) — saat dibuka, `kotakLebar`/`kotakPanjang` diisi dari nilai `S.kotak` lama itu (begitu juga `autoKotakLebar`/`autoKotakPanjang` dari `S.autoKotak`). Tampilan denah lama tidak berubah sama sekali saat pertama dibuka. |
| 4 | Cakupan | 1 pengaturan berlaku untuk **seluruh bentuk denah** (termasuk bagian dari kotak tambahan "+ Tambah Kotak") — BUKAN pengaturan per-area/per-region. Dikonfirmasi eksplisit oleh Elvan. |
| 5 | Yang TIDAK berubah | `S.verts`/`S.tiang`/`S.supportsManual`/`S.combinedBoxes` — field lain tidak disentuh. |

### 3.2 Mesin hitung (`DenahConv`)

| # | Fungsi | Perubahan |
|---|---|---|
| 1 | `saranKotak(ukuran, target)` | Logika jumlah bagian simetris (`n = round(ukuran/target)`) **tidak berubah**. Pembulatan hasil akhir diubah dari bulat-cm jadi **1 angka desimal**: `Math.round(ukuran/n*10)/10`. |
| 2 | `kotakFromKolom(ukuran, kolom)` **(baru)** | `Math.round(ukuran/Math.max(1,kolom)*10)/10` — cm dari jumlah kolom yang diketik user, 1 desimal, kolom minimal 1. |
| 3 | `kolomFromKotak(ukuran, kotak)` **(baru)** | `Math.max(1, Math.round(ukuran/kotak))` — kolom dari cm yang diketik user, bilangan bulat, minimal 1. |
| 4 | `buildMembers(S)` | Ganti 1 variabel jarak (`K`) jadi 2, dipasangkan sesuai arah scan: garis horizontal (`arah==='h'`, ditumpuk sepanjang sisi Panjang) pakai `S.kotakPanjang`; garis vertikal (`arah==='v'`, ditumpuk sepanjang sisi Lebar) pakai `S.kotakLebar`. Mode `arah==='2'` pakai dua-duanya sekaligus, masing-masing di jalurnya. Logika `scanX`/`scanY` (cara cari potongan garis di dalam bentuk poligon, termasuk bentuk hasil Gabungan Kotak) **sama sekali tidak berubah** — cuma terima angka jarak berapa pun (bulat/desimal). |

### 3.3 UI (tab Support)

| # | Keputusan | Detail |
|---|---|---|
| 1 | Layout | Input "Kotak support (cm)" (1 kolom) diganti jadi 2 pasang input: **Lebar** (Kotak cm + Kolom) dan **Panjang** (Kotak cm + Kolom). Ngetik salah satu (cm ATAU kolom) di satu sumbu langsung hitung ulang satunya (pakai `kotakFromKolom`/`kolomFromKotak`), pakai ukuran Lebar/Panjang denah saat itu. |
| 2 | Tombol "Pakai saran" | 1 tombol, isi KEDUA sumbu sekaligus dari `saranKotak(Lebar, target)` dan `saranKotak(Panjang, target)`; Kolom di kedua sumbu otomatis ikut terhitung ulang dari situ. |
| 3 | Status auto per sumbu | Ngetik manual (lewat cm ATAU kolom, keduanya cuma 2 pintu masuk ke angka yang sama) di salah satu sumbu → cuma sumbu itu yang berhenti ikut saran otomatis pas resize Lebar/Panjang. Sumbu yang belum disentuh tetap ikut. |
| 4 | Nonaktif sesuai mode Arah | Mode "1 arah horizontal" → pasangan input Lebar (cm+Kolom) dinonaktifkan (abu-abu, tak dipakai mesin). Mode "1 arah vertikal" → pasangan Panjang dinonaktifkan. Mode "Grid 2 arah" → dua-duanya aktif. Ganti mode Arah langsung update status aktif/nonaktif. |
| 5 | Simetris | Sudah otomatis terjaga oleh cara hitungnya sendiri — pembagian dari jumlah Kolom selalu membagi rata persis (semua kotak sama besar), dan `saranKotak` sudah lama dirancang cari jumlah bagian simetris dari target. Tidak perlu logika tambahan. |

---

## 4. Alur pakai (UI)

1. Buka tab **Support** → lihat 2 pasang input: Kotak Lebar (cm) + Kolom X, Kotak Panjang (cm) + Kolom Y.
2. Ketuk "Pakai saran" → dua-duanya terisi dari target 100cm (mis. denah 700×700cm → 7 kolom × 100cm di kedua sumbu).
3. Surveyor mau ubah jadi 10 kolom di sisi Lebar → ketik "10" di Kolom X → Kotak Lebar (cm) otomatis jadi 70cm. Kolom Y & Kotak Panjang tetap seperti sebelumnya (tidak ikut berubah).
4. Ganti mode Arah ke "1 arah horizontal" → pasangan input Lebar (Kotak+Kolom) jadi abu-abu (tak dipakai), cuma pasangan Panjang yang aktif.
5. Kanvas & hitung harga langsung ikut update sesuai kotak support baru — sama seperti perubahan pengaturan support lain yang sudah ada.

Sisa alur (drag-pindah besi Kelompok B, ortho-snap Kelompok A, dsb.) **tidak berubah**.

---

## 5. Testing

- **Fungsi murni** (`saranKotak` revisi, `kotakFromKolom`, `kolomFromKotak`) — testable headless via Node (pola `tests/rangka/test_konverter.mjs`): pembulatan 1 desimal benar, jumlah kolom simetris, kolom minimal 1 (tak boleh 0/negatif), sinkron 2 arah (isi kolom → hitung cm → hitung kolom lagi dari cm itu → balik ke kolom yang sama).
- **`buildMembers` dengan 2 kotak berbeda** — testable headless: kasih `kotakLebar` ≠ `kotakPanjang` pada bentuk uji yang sudah ada, cek jumlah & posisi garis support horizontal vs vertikal beda sesuai masing-masing; cek arah `'h'`/`'v'` tunggal cuma pakai 1 dari 2 nilai.
- **UI** (2 pasang input, nonaktif sesuai mode Arah, sinkron cm↔kolom di layar, migrasi data lama saat buka denah) — murni interaksi & tampilan, divalidasi manual oleh Elvan di HP production, sama seperti fitur editor sebelumnya.

---

## 6. Yang TIDAK berubah

`app/Services/CuttingService.php`, `RangkaDesignService.php`, `CuttingController::hitungSatuBlok` (jalur `tipe:'denah'`), `buildPenawaran()`, jalur kanopi/manual, format `S.verts`/`S.tiang`/`S.supportsManual`/`S.combinedBoxes`, algoritma `scanX`/`scanY`/`combineBox`/`luasM2`, seluruh fitur Kelompok A (ribbon/zoom/ukuran visual/ortho-snap/fullscreen) dan Kelompok B (drag-pindah tiang/support/kotak + align-snap). Pengaturan kotak support tetap 1 untuk seluruh denah (bukan per-area) — dikonfirmasi eksplisit, di luar cakupan kalau nanti ada permintaan pengaturan per-area terpisah.
