# Desain: Validasi Silang Izin ↔ Jadwal Libur

**Tanggal:** 12 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Ketemu pas ngobrolin beda Izin/Sakit/Cuti (`IzinAbsenController`) vs Jadwal Libur Tukar/Skip/Tambah (`JadwalLiburController`) — kedua sistem ini gak saling ngecek sama sekali, karyawan bisa dapat ajuan approved di kedua sistem buat tanggal yang sama tanpa ditolak.

---

## 1. Ringkasan

Tambah pengecekan silang di titik `store()` masing-masing controller: sebelum simpan ajuan baru, cek apakah tanggal yang sama sudah "dipegang" ajuan aktif (`pending`/`approved`) di sistem SATUNYA, buat karyawan yang sama. Kalau bentrok, ditolak dengan pesan jelas.

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Arah pengecekan | Dua arah: `IzinAbsenController::store()` cek `JadwalLibur`, `JadwalLiburController::store()` cek `IzinAbsen`. |
| 2 | Dinas Luar DIKECUALIKAN | `IzinAbsenController::dinasLuar()` (dicatat langsung Owner/Mandor, bukan ajuan karyawan) TIDAK ikut divalidasi — keputusan sadar Owner, bukan celah yang perlu dicegah sistem. |
| 3 | Status yang dianggap "aktif"/bentrok | `pending` dan `approved` — konsisten sama pola pengecekan duplikat yang sudah ada di kedua controller. `rejected` tidak dianggap bentrok. |
| 4 | Kolom tanggal yang dicek | Izin cuma punya 1 kolom (`tanggal`). Jadwal Libur bisa punya 2 (`tanggal` + `tanggal_baru` khusus jenis `tukar`) — pengecekan dari sisi Izin harus cek KEDUA kolom itu; pengecekan dari sisi Jadwal Libur cek tanggal yang mau diajukan (1 atau 2, tergantung jenis) lawan `izin_absen.tanggal`. |
| 5 | Scope per-karyawan | Pengecekan cuma buat `user_id` yang sama (bukan lintas karyawan) — 2 karyawan beda boleh punya izin & libur di tanggal yang sama. |

---

## 3. Titik integrasi (kode)

| # | File | Perubahan |
|---|---|---|
| 1 | `app/Http/Controllers/IzinAbsenController.php` — `store()` | Setelah cek duplikat izin yang sudah ada (baris ~70-77), tambah cek: `JadwalLibur::where('user_id',$user->id)->whereIn('status',['pending','approved'])->where(fn($q)=>$q->whereDate('tanggal',$request->tanggal)->orWhereDate('tanggal_baru',$request->tanggal))->exists()` → kalau true, `back()->with('error', ...)`. |
| 2 | `app/Http/Controllers/JadwalLiburController.php` — `store()` | Setelah cek bentrok Jadwal Libur yang sudah ada, tambah cek serupa ke `IzinAbsen` — cek `tanggal` (dan `tanggal_baru` kalau jenis `tukar`) lawan `izin_absen.tanggal` di baris `pending`/`approved` milik user yang sama. |

**Yang TIDAK berubah:** `dinasLuar()`, semua validasi lain yang sudah ada di kedua controller (H-1, H-3 cuti, duplikat internal), model/migration (gak ada kolom/tabel baru).

---

## 4. Testing

Kedua perubahan murni query DB di controller (butuh DB) — tidak ada logic pure baru yang bisa distandalone-test (beda dari `LiburService`). Verifikasi lewat `php -l` + manual di production, konsisten sama pola proyek ini buat controller logic yang butuh DB.

**Manual di production:**
- Karyawan A ajuin Cuti tanggal X (pending) → coba ajuin Tukar Libur dengan tanggal lama/baru = X → harus ditolak.
- Karyawan A ajuin Tukar Libur tanggal lama Y, tanggal baru Z (approved) → coba ajuin Izin di tanggal Y ATAU Z → harus ditolak di dua-duanya.
- Karyawan A dan Karyawan B (beda orang) — A ajuin Izin tanggal X, B ajuin Tukar Libur tanggal X → harus TETAP BISA (beda karyawan).
- Owner catat Dinas Luar di tanggal yang sudah ada ajuan Jadwal Libur aktif buat karyawan itu → harus TETAP BISA (Dinas Luar dikecualikan).

---

## 5. Di luar cakupan

- Tidak ada perubahan ke `dinasLuar()` (sengaja, lihat §2 poin 2).
- Tidak ada UI tambahan (dropdown/indikator "tanggal ini udah dipegang sistem lain") — cukup validasi tolak di `store()`, konsisten sama pola pengecekan duplikat yang sudah ada (juga cuma pesan error, bukan UI pencegahan).
