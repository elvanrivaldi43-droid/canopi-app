# Spec: Support Beririsan (Menerus vs Putus) + Panjang Ruas Nyata

**Tanggal:** 30 Agustus 2026
**Status:** LIVE 31 Agustus 2026 (6 task via subagent-driven-development; lihat
`CLAUDE.md` Utang aktif #10 utk regresi yang ditemukan+dipulihkan di Task 5 dan
koreksi tapak menyeluruh yang SENGAJA belum dikerjakan). Disetujui Elvan 30
Ags 2026 (orientasi per denah dikonfirmasi; lihat catatan hollow banci di 1a).
**Pemicu:** Diskusi kalibrasi Tahap 1 (rangka). Elvan: di lapangan support H & V
tidak selalu tumpang tindih; sering beririsan sebidang — satu arah menerus, arah
lain dipotong-potong, tergantung surveyor. Sistem sekarang cuma tahu satu dunia
(semua menerus penuh dua arah), sehingga cutting list & jumlah batang salah untuk
gaya irisan.

## Keputusan yang sudah terkunci (diskusi 30 Ags, jangan didebat ulang saat implementasi)

1. **Prioritas mesin potong:** jumlah batang → jumlah las → sisa terkumpul di
   potongan panjang. (Perbaikan mesin ini TERPISAH dari spec ini dan boleh
   dikerjakan duluan — lihat bagian "Di luar lingkup".)
2. **Cutting list satu baris per potongan** — tanpa pengelompokan "24 × 76 cm";
   tiap ruas bisa dilacak ke gambar.
3. **Frame & Balok Melintang SELALU menerus.** Support yang menabrak balok ikut
   terpecah di titik itu.
4. **Putus × putus DITOLAK:** dua jalur putus yang bersilangan mustahil secara
   fisik — sistem memperingatkan, bukan diam.
5. **Gaya tumpuk (menerus × menerus) tanpa catatan tambahan** — tidak ada efek
   ke hitungan potong; arah tumpukan & ketebalan rangka = urusan tukang.
6. **Orientasi pemasangan per denah** (bukan per batang): "berdiri" (default,
   sisi kecil jadi tapak) atau "tidur" (sisi besar jadi tapak).
7. **Toleransi gerinda TIDAK dihitung** — cutting list menulis ukuran pas.

## 1. Model data

### 1a. Master Material — dimensi profil
Kolom baru `master_material`: `lebar_profil_cm` dan `tinggi_profil_cm`
(DECIMAL(5,1) NULL). SQL idempotent manual di phpMyAdmin SEBELUM push kode
(pola project). Diisi Owner lewat halaman Master Material.

**Fallback berlapis saat menghitung tapak:**
1. Kolom terisi → pakai (berdiri = min(lebar,tinggi); tidur = max).
2. Kolom kosong → tebak dari nama material (regex `(\d+[.,]?\d*)\s*[xX×]\s*(\d+[.,]?\d*)`,
   mis. "Hollow 4x8 1mm" → 4 & 8). Satuan dianggap cm.
   **PERHATIAN (dari Elvan 30 Ags): tebakan nama cuma CADANGAN.** Di pasar ada
   hollow "banci"/kotak tak full — disebut 4x8 tapi nyatanya cuma 3,5 cm.
   Kolom DB-lah sumber kebenaran; halaman Master Material perlu mendorong Owner
   mengisinya (mis. tanda kuning di baris yang kolom profilnya masih kosong).
3. Gagal menebak → tapak 0 (perilaku as-ke-as lama) + **peringatan di cutting
   list**: "tapak besi X belum diisi — panjang ruas masih as-ke-as".

### 1b. Model denah (field baru, semuanya punya default = perilaku lama)
- `S.supMenerus = { h: true, v: true }` — bawaan per arah. Model lama tanpa
  field ini = dua arah menerus = persis perilaku sekarang (kompat mundur mutlak,
  nol migrasi).
- Per entri support (grid terkunci & manual): flag `menerus` opsional —
  kalau tidak ada, ikut bawaan arahnya. Ini yang memungkinkan CAMPUR per jalur.
- `S.orientasi = 'berdiri' | 'tidur'` — default `'berdiri'`.
- Balok & frame tidak butuh flag (selalu menerus, keputusan #3).

## 2. Logika pemecahan (buildMembers)

Untuk tiap jalur support ber-status PUTUS:
1. Kumpulkan semua "pemotong" yang menyilanginya: jalur support menerus arah
   lain, sisi frame, dan balok melintang yang bersilangan.
2. Pecah jalur di tiap titik silang. Panjang tiap ruas = jarak as-ke-as
   DIKURANGI setengah tapak pemotong di tiap ujungnya:
   `ruas = jarak − (tapak_kiri/2 + tapak_kanan/2)`.
   Tapak per pemotong dihitung dari MATERIAL pemotong itu (bisa beda-beda:
   ujung satu ketemu frame 5×10 berdiri = 2,5 cm; ujung lain ketemu support
   4×8 berdiri = 2 cm).
3. Ruas ≤ 0 (silangan terlalu rapat) → ruas dibuang + peringatan.
4. Penamaan member: jalur S5 pecah jadi `S5·1, S5·2, ...` urut dari
   kiri/depan. Nama ini yang tampil di cutting list (keputusan #2) dan label
   kanvas (label ruas pendek otomatis menyusut — pakai aturan
   `supportLabelText` yang sudah ada).

**Validasi putus×putus:** saat dua jalur putus bersilangan, silangan itu TIDAK
memecah apa pun + editor menampilkan peringatan (badge merah di panel Support +
baris peringatan di cutting list). Bukan error yang memblokir — surveyor bisa
lanjut kerja sambil membetulkan.

**Interaksi dengan fitur yang sudah ada (jangan dirusak):**
- Coakan/jalur terpotong frame (`jalurSegments`) tetap jalan — pemecahan irisan
  diterapkan SETELAH pemotongan frame (satu ruas frame bisa terpecah lagi oleh
  silangan).
- "Pecah jadi manual", pindah/re-clip, ceklis aktif/nonaktif per entri: semua
  bekerja pada JALUR (bukan ruas) — tidak berubah.
- Hitung harga & legend batang otomatis benar (keduanya membaca members).

## 3. UI (minimal, menumpang panel yang ada)

- **Fase pratinjau, tab Support:** baris `rowSupArah` ditambah dropdown kecil
  "Menerus:" [Dua arah ▾ / Horizontal / Vertikal] → mengisi `S.supMenerus`.
  Default "Dua arah".
- **Fase terkunci, panel Support:** tiap baris entri dapat toggle kecil
  "menerus/putus" (pola ceklis aktif yang sudah ada) → flag per jalur.
- **Orientasi:** satu dropdown "Pasang: berdiri/tidur" di baris yang sama
  dengan pilihan Menerus.
- **Master Material:** dua input baru lebar/tinggi profil di form yang ada.
- TIDAK ada saran arah otomatis di versi ini — belum pernah diputuskan Elvan;
  boleh diusulkan lagi belakangan setelah fitur dasarnya terbukti dipakai.

## 4. Testing

- `.mjs` baru: pemecahan ruas — panjang ruas dgn tapak beda tiap ujung, ruas
  pinggir vs tengah, campur per jalur, putus×putus terdeteksi, ruas ≤0 dibuang.
- Harness ekuivalensi: model lama (tanpa field baru) → members HARUS identik
  byte-per-byte dengan sebelum perubahan (pola harness Spacing Per-Sumbu).
- PHP: fallback tapak (kolom → nama → 0+warning).
- Test lama rangka (14 file .mjs) wajib tetap hijau.

## 5. Di luar lingkup (sengaja)

- Perbaikan prioritas mesin potong (batang→las→sisa) — pekerjaan terpisah,
  sudah disetujui, bisa jalan sebelum/paralel spec ini.
- Efek gaya irisan ke UPAH (lebih banyak potong+las) — masuk kalibrasi tahap
  tenaga kerja, bukan tahap rangka.
- Catatan arah tumpukan / ketebalan rangka (keputusan #5).
- Saran arah menerus otomatis.
- Pengelompokan potongan kembar di cutting list (keputusan #2).
