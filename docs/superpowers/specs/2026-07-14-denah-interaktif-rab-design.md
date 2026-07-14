# Desain: Denah Interaktif Rangka di RAB Opsi — CanopiBSD v2

**Tanggal:** 14 Juli 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Menggantikan arah:** `2026-07-14-perancang-rangka-design.md` (member-list sebagai halaman terpisah). Konsep member-list & mesinnya tetap dipakai, TAPI dilebur ke dalam RAB opsi sebagai **editor denah interaktif per blok**, bukan menu/halaman sendiri.

---

## 1. Ringkasan

Tiap blok di RAB opsi digambar sebagai **denah interaktif**: sebuah bidang (bisa berbentuk tidak beraturan) yang batang frame/support/tiang-nya lahir dari gambar, bisa diedit langsung, lalu dihitung biayanya oleh mesin cutting yang sudah divalidasi. Satu blok = satu denah. Beberapa blok = beberapa denah, dijumlah, margin ditambah di level opsi (persis aturan RAB sekarang).

Ini menyelesaikan dua hal sekaligus:
1. **Masalah kalibrasi/akurasi** — auto-layout rangka lama sering meleset (support kerapetan, profil per-blok, dst.). Dengan denah editable, layout digambar/dikoreksi user, bukan ditebak mesin.
2. **Belokan arah** — fitur "Perancang Rangka" sempat jadi halaman `/rangka-desain` owner-only yang terpisah dari RAB (melanggar prinsip "satu mesin"). Desain ini meleburnya ke alur RAB yang benar: pipeline → profil lokasi → **RAB opsi → denah per blok**.

---

## 2. Latar belakang & bukti

- **RAB opsi (`/rab-opsi`) sudah pakai mesin cutting yang benar**, bukan `keliling/6`. Alurnya: `rab-opsi/index.blade.php` (JS) → POST `/rab-blok/hitung` → `CuttingController::hitungProject` → `hitungSatuBlok` → `CuttingService::hitungRangka($box)`. Yang `keliling/6` (`RabKalkulasiService.php:164`) cuma dipakai wizard quick-quote `/rab/buat` yang lain — di luar scope ini.
- **Kekurangan sebenarnya:** rangka tiap blok **di-generate otomatis dari parameter kotak** (ukuran, kotak support, arah grid, jumlah tiang, centang sisi frame, besi tambahan). User tidak bisa mengedit batang/layout secara langsung; hanya menyetir lewat parameter.
- **Validasi PA-DUTA (14 Juli)** membuktikan auto-layout itu meleset: support 4x8 kerapetan (14 vs 9 asli), profil dianggap per-blok padahal keliling menerus, stok potong dipatok 600 padahal WF bisa 1200. (Detail: `CLAUDE.md` → "Temuan validasi PA-DUTA".)
- **Mesin cutting sehat untuk dipakai ulang** (sudah dibuktikan baca kode):
  - `CuttingService::potong` (`CuttingService.php:19`) sudah memecah potongan >600cm jadi segmen penuh + sisa dengan `jid` (sambungan), fix 14 Juli, divalidasi PA-DUTA & tes 700×730. Bukan lagi bug lama.
  - `RangkaDesignService::hitung(members, harga, lihatHarga)` (`RangkaDesignService.php:22`) menerima **daftar batang** apa pun → cutting per material → `per_material` (jumlah_batang, sambungan, harga_pokok, subtotal_besi). **Kontrak outputnya identik** dengan yang sudah dikonsumsi JS RAB opsi (`m.jumlah_batang`, `m.harga_pokok`, `m.subtotal_besi`).

Kesimpulan: yang perlu dibangun adalah **editor denah + penghubung denah→daftar-batang**; mesin biaya & cutting **dipakai ulang**.

---

## 3. Keputusan yang dikunci (dari brainstorming)

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Tingkat integrasi | Denah interaktif editable per blok (bukan "angka diam-diam", bukan tabel batang panjang) |
| 2 | Alur yang ditempeli | **RAB opsi multi-blok** (`/rab-opsi`), bukan wizard quick-quote |
| 3 | Cakupan | **Multi-blok langsung** — 1 denah = 1 blok; N blok = N denah, dijumlah |
| 4 | Cara ganti | **Ganti baru**: input parameter-kotak lama diganti editor denah (dengan migrasi aman, lihat §8) |
| 5 | Bentuk denah | **Poligon titik-sudut bisa diseret** (bentuk tidak beraturan / miring), tambah/kurang sudut, **snap ke grid** (10/20/25/50 cm) |
| 6 | Ukur sisi | Bisa **ketik panjang sisi (cm) pasti**, bukan cuma seret |
| 7 | Tiang | Bisa ditaruh **di titik mana saja** (snap grid), bukan cuma di sudut |
| 8 | Support | Auto-isi dari "kotak support" **+ bisa hapus / tambah / geser manual** (untuk gawang/WF melintang khusus) |
| 9 | Material | Per denah: pilih besi Frame / Support / Tiang (dari Master Material) |
| 10 | Kurva/lengkung | **Tidak didukung** — didekati dengan beberapa sudut lurus (sesuai fabrikasi asli) |
| 11 | Input sudut derajat | **Ditunda** — cukup titik bebas + ketik panjang sisi; tambah nanti kalau kepakai |
| 12 | Nasib `/rangka-desain` | **Dibuang** (halaman, controller, rute, menu owner) — lebur ke RAB opsi |
| 13 | Stok potong | **Per-material** (hollow 600, WF s/d 1200) — bukan `const 600` |
| 14 | UI | Responsif PC + HP; ikut gaya app (slate + amber), kanvas denah bergaya blueprint |

**Non-goals (eksplisit di luar scope):** kurva beneran, input derajat, wizard quick-quote `/rab/buat`, model SWE/produksi, multi-produk (pagar/tralis) — walau denah ini nanti bisa dipakai ulang untuk itu.

---

## 4. Cara kerja untuk pengguna (UX)

Di RAB opsi, tiap blok jadi kartu berisi **denah + panel material + rincian batang**:

1. Denah mulai dari kotak default. User membentuk: **seret sudut**, **tap "+ Sudut"** untuk sisipkan sudut (bikin L/berlekuk), **tap "− Sudut"** untuk hapus, atau **ketuk sisi lalu ketik cm** untuk ukuran pasti.
2. Atur **kotak support (cm)** & **arah grid** → support terisi otomatis mengikuti bentuk. Yang berlebih **dihapus** (mode Support); yang khusus (gawang/WF melintang) **ditambah/digeser** manual.
3. **Taruh tiang** di titik beban mana pun (mode Tiang).
4. Pilih **besi Frame/Support/Tiang**.
5. **Rincian batang + biaya blok** muncul langsung. **Tambah blok** → denah baru. Semua blok dijumlah; **margin di level opsi** (tak berubah).

Semua interaksi **snap ke grid** + target ketuk besar → nyaman di HP.

---

## 5. Arsitektur & komponen

Dipecah jadi unit kecil dengan tanggung jawab tunggal:

### 5.1 `DenahEditor` (frontend, baru)
- Modul JS vanilla (ikut pola app — tanpa framework), meng-render **SVG** di dalam kartu blok RAB opsi.
- **State (model denah) per blok:**
  ```
  {
    verts:   [{x, y}, ...],            // cm, urut keliling
    grid:    20,                        // cm snap
    kotak:   100, arah: 2,              // parameter auto-support
    supports:[{a:{x,y}, b:{x,y}, sumber:'auto'|'manual', off:bool}],
    tiang:   [{x, y}],                  // cm
    material:{frame, support, tiang}    // nama material (Master Material)
  }
  ```
- **Tool:** Bentuk (seret/tambah/kurang sudut, ketik sisi), Support (hapus/tambah/geser), Tiang (taruh/lepas). Interaksi via Pointer Events (satu jalur untuk mouse + sentuh), snap ke grid, `touch-action:none`.
- **Output:** memancarkan **daftar batang** (lihat 5.2) + menyimpan model denah untuk persist.
- **Tanggung jawab:** hanya UI + geometri denah. Tidak menghitung harga.

### 5.2 Konverter `denah → members` (baru, kecil, murni)
- Input: model denah. Output: `members[]` sesuai kontrak `RangkaDesignService::hitung`:
  `{ nama, jenis:'frame'|'support'|'tiang', panjang, material }`.
- Aturan: tiap **sisi poligon** → 1 member frame (panjang = jarak antar sudut); tiap **garis support aktif** → 1 member support (panjang = panjang tergunting di dalam poligon); tiap **tiang** → 1 member tiang (panjang = tinggi).
- Support otomatis: garis horizontal (dan vertikal bila arah=2) berjarak `kotak`, **diklip ke dalam poligon** (scanline: cari perpotongan sisi, ambil pasangan). Ini memungkinkan support mengisi bentuk tidak beraturan.
- **m² blok** = luas poligon (shoelace), dipakai untuk consumable/finishing per-m².

### 5.3 Endpoint hitung (ubah `CuttingController`)
- Terima **daftar batang + m² per blok** (bukan lagi hanya parameter kotak). Untuk tiap blok panggil `RangkaDesignService::hitung(members, harga, lihatHarga)` → `per_material`.
- Sisa pipa biaya per blok **tak berubah**: upah, consumable (pakai m² denah), finishing, add-on, operasional (Model Biaya V2 di `hitungSatuBlok`), lalu **margin di level opsi**.
- Backward-compat: jalur parameter-kotak lama (`hitungRangka($box)`) **tetap ada** sampai migrasi selesai (§8).

### 5.4 Mesin dipakai ulang (tak berubah kecuali 5.6)
- `RangkaDesignService::hitung` — members → cutting → biaya.
- `CuttingService::potong` — cutting-stock >600 + sambungan (sudah benar).

### 5.5 Persistensi denah (baru)
- Simpan **model denah JSON per blok** supaya RAB bisa dibuka & diedit ulang. Reuse mekanisme simpan opsi yang ada (`rab_opsi` / `penawaran_json` di `RabOpsiController`), tambahkan field denah per blok. Tidak bikin tabel baru kalau kolom JSON cukup.

### 5.6 Stok potong per-material (ubah `CuttingService`)
- `const STOCK = 600` → **parameter per material** (hollow 600, WF s/d 1200, bisa custom). Sumber panjang stok: kolom di `master_material` (tambah bila belum ada, idempotent). Default 600 bila kosong.

### 5.7 Pembersihan (hapus)
- Hapus halaman/rute/controller `/rangka-desain` (`RangkaDesainController`, rute di `routes/web.php:380-382`) + link menu di `sidebar-owner.blade.php:49-55`. `RangkaDesignService` **tetap** (jadi mesin members→biaya).

---

## 6. Alur data

```
DenahEditor (per blok)
  → model denah  → [konverter] → members[] + m²
  → POST /rab-opsi hitung
     → per blok: RangkaDesignService.hitung(members, harga) → per_material (batang+sambungan+besi)
                 + upah/consumable(m²)/finishing/addon/operasional  (V2, per blok)
                 → pokok_blok
     → jumlah semua blok → margin di opsi → total opsi
  → tampil rincian + total (real-time)
  → simpan: model denah JSON per blok (bisa dibuka lagi)
```

---

## 7. Penanganan error & kasus batas

- **Material belum diisi harganya** → `RangkaDesignService` sudah mengembalikan `warn` ("Harga besi X belum diisi"); tampilkan, kunci simpan/deal sampai valid (pola sama seperti wizard sekarang).
- **Poligon tidak valid** (sisi saling silang / <3 sudut) → tolak simpan, tandai visual; luas/scanline bisa kacau.
- **Support di bentuk cekung (L)** → scanline even-odd sudah menangani (segmen berpasangan), diuji khusus.
- **Denah kosong / 0 batang** → biaya rangka 0, bukan error.
- **WF 1200 tanpa data stok** → fallback 600 + `warn` biar ketahuan.
- **Bug laten `potong` case-2** (`CuttingService.php:73-82`, sambungan bisa kurang di kasus ekstrem) — **di luar scope**, dicatat, dibereskan terpisah.

---

## 8. Cara ganti yang aman (migrasi)

RAB opsi dipakai untuk penawaran nyata, jadi **jangan cabut input lama sebelum yang baru terbukti**:

1. Bangun `DenahEditor` + endpoint members **berdampingan** dengan jalur parameter-kotak (di belakang flag/tab).
2. **Bukti reproduksi PA-DUTA** (§9) harus lulus sebelum jalur lama dimatikan.
3. Baru ganti default kartu blok ke denah; jalur kotak lama dihapus setelah stabil.
4. Data RAB masih status **TES** (belum ke customer asli) → risiko lebih kecil, tapi bukti tetap wajib.

---

## 9. Testing

- **Reproduksi PA-DUTA (kunci):** gambar denah proyek alderon PA-DUTA → konverter → `RangkaDesignService.hitung` harus mendekati cutting list asli (frame 5x10 ≈ 10 batang, dst., dengan stok per-material aktif). Tes PHP standalone (pola `tests/rangka/test_hitung.php` yang sudah ada).
- **Konverter denah→members:** denah kotak sederhana + denah L → jumlah & panjang member sesuai hitungan tangan (assert).
- **Scanline support:** bentuk cekung (L) → segmen support benar (tidak bocor keluar poligon).
- **Stok per-material:** member 1000cm dari WF-1200 → 1 batang 0 sambungan; dari hollow-600 → 2 batang 1 sambungan.
- **Frontend:** cek manual PC+HP (seret sudut, ketik sisi, tiang, support manual); satu self-check kecil untuk konverter.

---

## 10. Yang ditunda / dicatat

- Input sudut derajat (keputusan #11).
- Kurva beneran (keputusan #10) — selamanya didekati sudut lurus, kecuali kebutuhan berubah.
- Bug laten `CuttingService::potong` case-2 (§7).
- `hitungRangka` auto-layout lama boleh dipensiunkan setelah denah editor menggantikannya sepenuhnya.
- Denah ini berpotensi dipakai ulang untuk multi-produk (pagar/tralis) & SWE — di luar scope sekarang.
