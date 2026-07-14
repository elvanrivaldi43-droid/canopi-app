# Desain: Perancang Rangka (Editable Member-List) — CanopiBSD v2

**Tanggal:** 14 Juli 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)

---

## 1. Ringkasan

Mengganti model perhitungan rangka yang kaku (berbasis blok + aturan tetap + "besi tambahan" manual) dengan satu konsep tunggal: **sebuah kanopi/produk = daftar batang yang bisa diedit, di mana tiap batang punya besinya sendiri.**

Semua kebutuhan yang selama ini jadi fitur terpisah — ganti besi per bagian, profil menerus, support beda-beda material, besi tambahan — **lebur jadi satu operasi: mengedit daftar batang.**

Dibangun **terpisah dari RAB produksi yang live** dan **bertahap**, supaya app yang dipakai sehari-hari aman selama dikerjakan.

---

## 2. Latar belakang & alasan

Validasi 14 Juli 2026 ke cutting list asli project PA-DUTA (lihat `CLAUDE.md` → "Validasi cutting engine") membongkar bahwa model blok punya masalah struktural:

- **Dedup sisi berbagi antar blok** harus ditangani manual (matikan sisi frame) — gampang salah/dobel.
- **Profil menerus vs per-blok** — profil mengikuti keliling luar menerus (mis. sisi 730 = 1 run), tapi model blok memecahnya (492+238) → jumlah batang meleset (5 vs 4 asli).
- **Material per-bagian kaku** — realita: frame luar 5×10, support 4×8, ada 3 balok tengah 5×10, profil 4×6 + 3×3; tiap bagian bisa beda besi, dan **"sering beda-beda" tiap proyek** (dikonfirmasi Elvan).
- **"Besi Tambahan" manual** — solusi tambal untuk profil/support ekstra, rawan salah hitung.

Kesimpulan: satu daftar batang yang bisa diedit **melenyapkan keempat masalah sekaligus**, dan lebih dekat ke cara fabricator berpikir (per batang, bukan per blok parametrik).

Fix cutting >600cm (fase sebelumnya, sudah live) tetap dipakai sebagai mesin potong di desain ini.

---

## 3. Konsep inti

- Satu **denah** (satu struktur fisik) = **satu daftar batang**.
- Tiap **batang** punya: nama, jenis (pengelompokan tampilan), panjang, arah, posisi, dan **besi-nya sendiri**.
- **"Lebur jadi satu" berlaku di dalam satu denah** — frame, support, profil, tambahan semuanya jadi baris di daftar yang sama.
- **Blok tetap ada, tapi maknanya = denah/produk terpisah** dalam satu penawaran (mis. carport, jemuran, rooftop — bahkan bisa beda produk: kanopi, pagar, tralis).
- **Kotak** = alat sketsa benih **di dalam** satu denah untuk membentuk outline berlekuk (L); bukan blok.

---

## 4. Hierarki & model data

```
PENAWARAN (Opsi)
 └─ BLOK / DENAH            ← struktur/produk terpisah (carport, jemuran, pagar, rooftop)
     └─ DAFTAR BATANG        ← semua lebur di sini; di-seed 1+ kotak, lalu diedit
         └─ BATANG           ← satu potong besi
```

### BLOK (denah)
| Field | Keterangan |
|---|---|
| `nama` | Carport / Jemuran / Pagar depan / Rooftop |
| `jenis_produk` | `kanopi` / `pagar` / `tralis` … (sekarang hanya `kanopi`, kolom disiapkan untuk masa depan) |
| `atap` | alderon / bening / — (pagar: kosong) |
| `batang[]` | daftar batang |

### BATANG (member)
| Field | Keterangan |
|---|---|
| `nama` | Frame A, Support 1, Profil depan … |
| `jenis` | `frame` / `support` / `tiang` / `profil` / `tambahan` — hanya untuk pengelompokan & warna tampilan |
| `panjang` | cm |
| `arah` | vertikal / horizontal (atau vektor arah untuk denah) |
| `posisi` | koordinat garis (untuk gambar denah + deteksi dobel) |
| `besi` | referensi ke `master_material` |

### Penyimpanan
Desain tiap blok disimpan sebagai **JSON** (daftar batang), menumpang pola **autosave** yang sudah ada di RAB Multi-Opsi. Tidak membuat tabel DB baru yang rumit pada tahap awal (prinsip minimal/YAGNI).

---

## 5. Cara kerja (alur)

1. **Benih** — isi kotak (lebar×panjang, kotak support). Untuk `kanopi`, mesin `hitungRangka` (yang sudah ada) menghasilkan batang default **berikut posisinya**. Produk lain (pagar) punya "penyeed" sendiri nanti; sisa alur sama.
2. **Edit** — batang diedit lewat **klik di denah** (lihat §6). Ganti besi, ubah panjang, hapus; plus tombol **+ Tambah Batang**.
3. **Notif dobel** — tiap hitung ulang, mesin memindai batang **berposisi sama** (mis. sisi berbagi antar kotak benih). Jika ada → **banner merah keras**: sebut kedua batang & posisinya → user pilih **Hapus salah satu** / **Biarkan**. Mesin mendeteksi & memperingatkan; **user yang memutuskan**. Jika dibiarkan, dobel tetap dihitung.
4. **Hitung** — batang dikelompokkan **per besi** → mesin cutting (`CuttingService::potong`, sudah fix >600cm) jalan per besi → batang + sambungan + biaya. Dihitung per blok, lalu **dijumlah jadi satu penawaran**.
5. **Denah preview** — gambar tampak-atas dari posisi batang, diwarnai per jenis/besi.

### Yang universal vs per-produk
- **Beda per produk:** hanya **penyeed** (kanopi = `hitungRangka`; pagar = penyeed baru nanti).
- **Sama semua produk:** daftar batang, edit, notif dobel, cutting, denah, penyimpanan.
- Konsekuensi: menambah pagar/tralis = menambah **satu penyeed**, inti tidak dirombak (mendukung roadmap #7 multi-produk).

---

## 6. UX — edit lewat denah interaktif

Menghindari "tabel raksasa" yang bikin step pilih item RAB kepanjangan:

- **Edit/ganti besi/hapus** → **klik batang di denah** → panel edit kecil muncul untuk batang itu saja (ganti besi ▾ · ubah panjang · hapus).
- **Tambah batang baru** → tombol **+ Tambah Batang** (tidak bisa lewat klik, karena belum ada di denah). Bisa "tambah di sisi ini" atau isi manual.
- **Notif dobel** → batang dobel **disorot merah di denah**, klik untuk pilih hapus/biarkan.
- Di samping denah hanya ada **ringkasan** (total besi + biaya per jenis), **bukan** daftar semua baris.

Layar = **denah interaktif + ringkasan kecil + panel edit yang muncul saat diklik.** Bukan CAD (tidak ada drag/gambar-bebas).

**Catatan build:** denah yang bisa diklik (SVG + handler + sorot + panel) adalah **potongan terberat** dari fitur ini. Ini alasan pentahapan (§8): buktikan mesin dulu, poles UX kemudian.

---

## 7. Penanganan error

- **Batang dobel** → notif keras, user pilih; jika dibiarkan tetap dihitung.
- **Besi belum dipilih / harga kosong** → peringatan kuning (pola `$warn` yang ada), tetap bisa lanjut.
- **Ganti ukuran kotak setelah mengedit** → konfirmasi keras: "Ini akan reset denah & edit-anmu. Lanjut?" — cegah kerja hilang diam-diam.
- **Batang tanpa posisi** (ditambah manual) → biayanya tetap dihitung; di denah masuk daftar "belum diposisikan" agar gambar tidak kacau.
- **Besi WF panjang** → sementara masih kena aturan 600cm. **Bergantung pada TODO #2 (stok per-material)** — dicatat sebagai dependensi agar palang WF (mis. 700cm) tidak ter-split keliru.

---

## 8. Rencana fase

| Fase | Isi | Kriteria selesai |
|---|---|---|
| **1 — Mesin & data** | Model data batang + seed 1 kotak (`hitungRangka`) + edit besi/tambah/hapus (tabel sementara) + cutting per besi + biaya. Denah read-only. Halaman baru terpisah. | **Reproduksi PA-DUTA keluar 10/9/4/4 + profil** (cocok cutting list asli) |
| **2 — Denah interaktif** | Denah bisa diklik + panel edit + tombol tambah. Ganti tabel sementara. | Bisa edit besi & tambah batang lewat klik denah, biaya update |
| **3 — Lengkap** | Multi-blok (carport/jemuran/rooftop) + seed multi-kotak (bentuk L) + notif dobel + jumlah antar-blok | Penawaran 3 denah + notif dobel berfungsi |
| **Nanti** | Integrasi ke penawaran resmi · pagar/tralis (tambah penyeed) · stok per-material (TODO #2) | — |

Dibangun **terpisah dari RAB live** sampai matang; RAB lama & Multi-Opsi tidak disentuh.

---

## 9. Cara tes (bukti, bukan klaim)

1. **Standalone PHP** — beri daftar batang → cek batang/sambungan/biaya. **Patokan penerimaan: reproduksi PA-DUTA → 10/9/4/4 + profil.**
2. **Notif dobel** — beri batang tumpang-tindih → pastikan banner muncul.
3. **End-to-end** (skill `verify`) — buka halaman: seed 1 kotak → ganti besi 1 support → tambah 1 batang → biaya berubah → screenshot.
4. **Regresi** — RAB lama & Multi-Opsi live tidak berubah (dibangun terpisah).

---

## 10. Di luar scope (non-goals)

- **Bukan** editor CAD drag-drop / gambar bebas.
- **Tidak** menyentuh/mengganti RAB lama (`/rab`) & RAB Multi-Opsi live pada tahap awal.
- **Belum** membangun penyeed pagar/tralis (hanya menyiapkan kolom `jenis_produk`).
- **Belum** mengubah stok potong jadi per-material (itu TODO #2, dependensi terpisah).

---

## 11. Dependensi

- **Sudah ada & dipakai:** `CuttingService::potong` (fix >600cm, sudah live), `hitungRangka`, `master_material`, pola autosave RAB Multi-Opsi.
- **Perlu menyusul:** TODO #2 stok per-material (agar WF panjang tidak ter-split keliru) — sebaiknya mendarat sebelum fitur ini dipakai untuk proyek ber-WF panjang.
