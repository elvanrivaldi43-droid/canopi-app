(function () {
const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
// Titik terdekat di ruas garis a-b dari titik p (diklem ke ujung ruas). Dipakai "+ Sudut": titik
// baru harus NEMPEL ke garis sisi, bukan persis di posisi jari nempel (tap yg meleset dikit dari
// garis dulu bikin bentuk nyeleneh -- "paruh burung", laporan Elvan 22 Ags).
const closestOnSegment = (p, a, b) => {
  const ex = b.x - a.x, ey = b.y - a.y, len2 = ex * ex + ey * ey;
  if (len2 < 1e-9) return { x: a.x, y: a.y };
  const t = Math.max(0, Math.min(1, ((p.x - a.x) * ex + (p.y - a.y) * ey) / len2));
  return { x: a.x + ex * t, y: a.y + ey * t };
};
const bbox = (v) => {
  const xs = v.map(p => p.x), ys = v.map(p => p.y);
  return { x0: Math.min(...xs), y0: Math.min(...ys), x1: Math.max(...xs), y1: Math.max(...ys) };
};
const shoelace = (v) => {
  let a = 0, n = v.length;
  for (let i = 0; i < n; i++) { const p = v[i], q = v[(i + 1) % n]; a += p.x * q.y - q.x * p.y; }
  return Math.abs(a) / 2;
};
const scanX = (v, Y) => { // perpotongan garis mendatar y=Y dgn poligon (even-odd), urut x
  const xs = [], n = v.length;
  for (let i = 0; i < n; i++) { const a = v[i], b = v[(i + 1) % n];
    if ((a.y <= Y && b.y > Y) || (b.y <= Y && a.y > Y)) xs.push(a.x + (Y - a.y) / (b.y - a.y) * (b.x - a.x)); }
  return xs.sort((p, q) => p - q);
};
const scanY = (v, X) => {
  const ys = [], n = v.length;
  for (let i = 0; i < n; i++) { const a = v[i], b = v[(i + 1) % n];
    if ((a.x <= X && b.x > X) || (b.x <= X && a.x > X)) ys.push(a.y + (X - a.x) / (b.x - a.x) * (b.y - a.y)); }
  return ys.sort((p, q) => p - q);
};
const PALET = ['#f59e0b', '#38bdf8', '#a3e635', '#f472b6', '#c084fc', '#fb7185', '#2dd4bf', '#facc15'];
// warna per besi yang dipakai (stabil, urut kemunculan)
const colorMap = (mem) => {
  const used = []; mem.forEach(m => { if (!used.includes(m.material)) used.push(m.material); });
  const map = {}; used.forEach((n, i) => map[n] = PALET[i % PALET.length]); return map;
};
// Deteksi 2 segmen saling potong (dipakai validasi combineBox) — sengaja skip kasus kolinear/nyentuh
// ujung persis (jarang terjadi dari hasil combineBox, dan self-intersect nyata selalu ke-tangkap
// oleh pasangan sisi lain di sekitarnya).
const segInt = (p1, p2, p3, p4) => {
  const d = (a, b, c) => (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
  const d1 = d(p3, p4, p1), d2 = d(p3, p4, p2), d3 = d(p1, p2, p3), d4 = d(p1, p2, p4);
  return ((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0));
};
// Poligon sederhana = tak ada 2 sisi tak-bertetangga yang saling potong.
const isSimplePolygon = (v) => {
  const n = v.length;
  for (let i = 0; i < n; i++) {
    const a1 = v[i], a2 = v[(i + 1) % n];
    for (let j = i + 1; j < n; j++) {
      if ((j + 1) % n === i || (i + 1) % n === j) continue; // lewati sisi bertetangga (berbagi titik)
      if (segInt(a1, a2, v[j], v[(j + 1) % n])) return false;
    }
  }
  return true;
};
// Buang vertex yang segaris dengan 2 tetangganya (sudut ~180 derajat, termasuk "duri" arah
// balik). Dipakai combineBox: kalau kotak nempel PAS di sudut existing, sudut lama itu jadi
// segaris dengan ujung kotak baru + sisi berikutnya -> redundant, harus lenyap (bukan nambah
// sisi 100cm palsu). Kalau kotak di tengah sisi (bukan sudut), tak ada titik yang jadi segaris,
// jadi tak ada yang dibuang -> lekukan tetap 4 sisi penuh.
const removeCollinear = (v) => {
  const out = v.slice();
  let changed = true;
  while (changed && out.length > 3) {
    changed = false;
    for (let i = 0; i < out.length; i++) {
      const n = out.length;
      const prev = out[(i - 1 + n) % n], cur = out[i], next = out[(i + 1) % n];
      const e1x = cur.x - prev.x, e1y = cur.y - prev.y, e2x = next.x - cur.x, e2y = next.y - cur.y;
      const len1 = Math.hypot(e1x, e1y), len2 = Math.hypot(e2x, e2y);
      if (len1 < 1e-6 || len2 < 1e-6) continue;
      if (Math.abs(e1x * e2y - e1y * e2x) / (len1 * len2) < 1e-6) { out.splice(i, 1); changed = true; break; }
    }
  }
  return out;
};
// Inti combineBox: hitung titik kotak baru, sisip ke `verts`, bersihkan titik segaris, lalu
// validasi. Dipisah dari combineBox murni (return array) supaya applyBoxPreview bisa dapat
// `boxIdx` (index vertex milik kotak, buat fitur drag-kotak-utuh) dan `reindex` (map index lama
// -> index baru, -1 kalau sudut lama itu yang dibuang) tanpa duplikasi logika.
const combineBoxCore = (verts, sisiIdx, offset, span, depth) => {
  const n = verts.length;
  const a = verts[sisiIdx], b = verts[(sisiIdx + 1) % n];
  const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey);
  if (!(len > 1e-6) || !(span > 0) || offset < -1e-6 || offset + span > len + 1e-6 || !depth) return null;
  const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
  const p1 = { x: a.x + ux * offset, y: a.y + uy * offset };
  const p2 = { x: a.x + ux * (offset + span), y: a.y + uy * (offset + span) };
  const p4 = { x: p1.x + nx * depth, y: p1.y + ny * depth };
  const p3 = { x: p2.x + nx * depth, y: p2.y + ny * depth };
  const seq = [];
  if (offset > 1e-6) seq.push(p1);
  seq.push(p4, p3);
  if (offset + span < len - 1e-6) seq.push(p2);
  const out = [...verts.slice(0, sisiIdx + 1), ...seq, ...verts.slice(sisiIdx + 1)];
  const cleaned = removeCollinear(out);
  if (!isSimplePolygon(cleaned)) return null;
  const boxIdx = [], reindex = verts.map(() => -1);
  cleaned.forEach((p, i) => {
    if (seq.includes(p)) { boxIdx.push(i); return; }
    const oldI = verts.indexOf(p);
    if (oldI !== -1) reindex[oldI] = i;
  });
  return { verts: cleaned, boxIdx, reindex };
};
// Snap 1 titik ke sumbu X/Y titik acuan kalau jaraknya < threshold (dipakai ortho-snap
// drag ujung support manual — garis jadi lurus tanpa harus pas manual, pola sama seperti
// ortho-snap drag sudut poligon yang sudah ada di bindSvg()).
const orthoSnapToPoint = (p, anchor, TH) => {
  let { x, y } = p;
  if (Math.abs(x - anchor.x) < TH) x = anchor.x;
  if (Math.abs(y - anchor.y) < TH) y = anchor.y;
  return { x, y };
};
// Mesin snap generik Kelompok B: cari kandidat titik acuan TERDEKAT per-sumbu (independen X/Y)
// dalam threshold TH. Dipakai drag-pindah tiang/support-garis/kotak (bindSvg) — beda dari
// orthoSnapToPoint (Kelompok A, ortho-snap 1 anchor tetap) krn di sini kandidatnya banyak &
// dipilih yang paling dekat, bukan cuma 1 anchor tetap.
const findAlignSnap = (p, candidates, TH) => {
  let x = p.x, y = p.y, guideX = null, guideY = null, bestDx = TH, bestDy = TH;
  candidates.forEach(c => {
    const dx = Math.abs(p.x - c.x), dy = Math.abs(p.y - c.y);
    if (dx < bestDx) { bestDx = dx; x = c.x; guideX = c; }
    if (dy < bestDy) { bestDy = dy; y = c.y; guideY = c; }
  });
  const guides = [];
  if (guideX) guides.push({ axis: 'x', ref: guideX });
  if (guideY) guides.push({ axis: 'y', ref: guideY });
  return { x, y, guides };
};
// Kandidat titik acuan align-snap: titik tiang lain, titik ujung+tengah support manual lain,
// titik tengah tiap sisi frame SAAT INI (S.verts, dihitung ulang tiap panggilan — otomatis ikut
// kalau sisi berubah panjang karena lekukan/resize). `exclude` mencegah elemen yg sedang digeser
// sendiri jadi kandidat (nge-snap ke diri sendiri selalu "cocok", tak berguna).
const collectAlignCandidates = (S, exclude) => {
  const pts = [];
  (S.tiang || []).forEach((t, i) => { if (!(exclude && exclude.kind === 'tiang' && exclude.i === i)) pts.push({ x: t.x, y: t.y }); });
  (S.supportsManual || []).forEach((m, i) => {
    if (exclude && exclude.kind === 'sup' && exclude.i === i) return;
    pts.push({ x: m.a.x, y: m.a.y }, { x: m.b.x, y: m.b.y }, { x: (m.a.x + m.b.x) / 2, y: (m.a.y + m.b.y) / 2 });
  });
  const skipVerts = (exclude && exclude.kind === 'box' && exclude.vertIdx) || [];
  const n = S.verts.length;
  S.verts.forEach((v, i) => {
    const j = (i + 1) % n;
    if (skipVerts.includes(i) && skipVerts.includes(j)) return; // sisi internal milik kotak yg lagi digeser sendiri
    const w = S.verts[j]; pts.push({ x: (v.x + w.x) / 2, y: (v.y + w.y) / 2 });
  });
  return pts;
};

const DenahConv = {
  denahOrigin(S) { const bb = bbox(S.verts); return { x: bb.x0, y: bb.y1 }; },
  tiangFromOffset(S, dx, dy) { const o = this.denahOrigin(S); return { x: o.x + dx, y: o.y - dy }; },
  tiangToOffset(S, point) { const o = this.denahOrigin(S); return { dx: point.x - o.x, dy: o.y - point.y }; },
  tiangPreviewState(S, dx, dy, bounds) {
    const raw = this.tiangFromOffset(S, dx, dy);
    const point = {
      x: Math.max(bounds.x0, Math.min(bounds.x1, raw.x)),
      y: Math.max(bounds.y0, Math.min(bounds.y1, raw.y)),
    };
    return { raw, point, clamped: point.x !== raw.x || point.y !== raw.y };
  },
  // Angka input panel tiang numerik (Task 2): koma -> titik, trim spasi, tolak kosong/parsial/non-finite.
  // Commit dipanggil saat blur/Enter/change (bukan per keystroke) — lihat renderTiangPanel().
  parseCmValue(raw) {
    if (raw == null) return null;
    const s = String(raw).trim();
    if (!s) return null;
    const n = Number(s.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
  },
  // Kunci arah geser support grid (Redesign Support, 21 Agustus): support horizontal (id "Sh_...",
  // a.y===b.y) cuma boleh naik-turun; support vertikal (id "Sv_...", a.x===b.x) cuma boleh
  // kiri-kanan. Alasan: hasil geser HARUS tetap horizontal/vertikal (masuk akal secara struktur
  // rangka), tidak boleh miring diagonal.
  lockSupportAxis(id, startA, startB, dx, dy) {
    const horizontal = id.startsWith('Sh_');
    const ddx = horizontal ? 0 : dx;
    const ddy = horizontal ? dy : 0;
    return {
      a: { x: startA.x + ddx, y: startA.y + ddy },
      b: { x: startB.x + ddx, y: startB.y + ddy },
    };
  },
  // Snap posisi akhir saat grid support "naik kelas" jadi manual (dipanggil dari end()). HANYA
  // snap sumbu yang BOLEH bergerak (sama axis lock dgn lockSupportAxis) -- sumbu terkunci (mis. X
  // untuk support horizontal) datang dari scanX/scanY (perpotongan polygon presisi, BUKAN
  // kelipatan grid), snap di situ menggeser support sepanjang badannya sendiri (ubah panjang,
  // bisa lepas dari tepi frame) -- itu bug harga nyata, bukan kosmetik.
  snapPromotedSupport(id, a, b, grid) {
    const horiz = id.startsWith('Sh_');
    const snap = v => Math.round(v / grid) * grid;
    return horiz
      ? { a: { x: a.x, y: snap(a.y) }, b: { x: b.x, y: snap(b.y) } }
      : { a: { x: snap(a.x), y: a.y }, b: { x: snap(b.x), y: b.y } };
  },
  // Nomor S{n} KHUSUS support manual (id "Sm_..."), independen dari support grid otomatis.
  // SATU sumber angka dipakai render() (label kanvas), openMatMenu() (label menu Ganti Material),
  // dan renderSupportPanel() (panel daftar) — jangan hitung ulang beda-beda di tiap tempat, itu
  // akar masalah lama: S{n} kanvas dulu gabungan grid+manual, jadi beda dari nomor panel.
  numberSupportsManual(mem) {
    let n = 0;
    const map = {};
    mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_')).forEach(m => { n++; map[m.id] = n; });
    return map;
  },
  buildMembers(S) {
    // K harus > 0: kotak<=0 (mis. input negatif / model tersimpan rusak) bikin loop scanline tak berhenti → freeze tab.
    const mem = [], V = S.verts, bb = bbox(V), K = (S.kotak > 0 ? S.kotak : 100), rem = S.removed || {};
    // frame: tiap sisi poligon
    V.forEach((v, i) => {
      const w = V[(i + 1) % V.length], id = 'F' + i;
      const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.frame;
      mem.push({ id, nama: 'F' + (i + 1), jenis: 'frame', panjang: Math.round(dist(v, w) * 10) / 10, material: mat, geom: { a: v, b: w } });
    });
    if (Array.isArray(S.supportsLocked)) {
      // FASE TERKUNCI (Support ID Stabil, 23 Ags): entri grid nyimpan JALUR {axis,pos} — ujung
      // dihitung DI SINI dari polygon saat ini (frame berubah -> support ikut; coakan -> jalur
      // terbelah jadi beberapa member ber-ID SAMA, panjang per potongan dijumlah utk hitungan besi).
      // Entri nonaktif dilewati total (hilang dari gambar & hitungan, tetap ada di panel).
      S.supportsLocked.forEach(e => {
        if (e.aktif === false) return;
        const id = 'SL' + e.no;
        const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        const push = (a, b) => mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(a, b)), material: mat, geom: { a, b } });
        if (e.manual) push({ ...e.a }, { ...e.b });
        else if (e.axis === 'h') {
          const xs = scanX(V, e.pos);
          for (let s = 0; s + 1 < xs.length; s += 2) push({ x: xs[s], y: e.pos }, { x: xs[s + 1], y: e.pos });
        } else {
          const ys = scanY(V, e.pos);
          for (let s = 0; s + 1 < ys.length; s += 2) push({ x: e.pos, y: ys[s] }, { x: e.pos, y: ys[s + 1] });
        }
      });
    } else {
      // FASE PRATINJAU — blok lama VERBATIM, JANGAN diubah (harness ekuivalensi 25.200 variasi
      // per-sumbu + test_support_spacing harus tetap lolos).
      const addSeg = (id, a, b) => {
        if (rem[id]) return;
        const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(a, b)), material: mat, geom: { a, b } });
      };
      // Posisi garis per-sumbu (Spacing Per-Sumbu, 21 Ags). K lama jadi kotakFallback -- denah lama
      // tanpa modeH/kotakH tetap ke jalur 'cm' + fallback, hasil PERSIS sama (id Sh_/Sv_ tak bergeser).
      if (S.arah === 'h' || S.arah === '2') {
        const posH = DenahConv.posisiSupport(bb.y0, bb.y1, S.modeH || 'cm', S.kotakH, S.kolomH, K);
        posH.forEach((Y, li) => { const xs = scanX(V, Y);
          for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); });
      }
      if (S.arah === 'v' || S.arah === '2') {
        const posV = DenahConv.posisiSupport(bb.x0, bb.x1, S.modeV || 'cm', S.kotakV, S.kolomV, K);
        posV.forEach((X, li) => { const ys = scanY(V, X);
          for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); });
      }
      (S.supportsManual || []).forEach((m, i) => {
        const id = 'Sm_' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(m.a, m.b)), material: mat, geom: { a: m.a, b: m.b } });
      });
    }
    (S.tiang || []).forEach((t, i) => {
      const id = 'T' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.tiang;
      mem.push({ id, nama: 'T' + (i + 1), jenis: 'tiang', panjang: S.tinggi, material: mat, geom: { p: t } });
    });
    (S.balok || []).forEach(b => {
      const a = DenahConv.resolveBalokEndpoint(S, b.a), c = DenahConv.resolveBalokEndpoint(S, b.b);
      if (!a || !c) return;
      const mat = (S.matOverride && S.matOverride['B' + b.no]) || b.material;
      mem.push({ id: 'B' + b.no, nama: 'B' + b.no, jenis: 'balok', panjang: Math.round(dist(a, c)), material: mat, geom: { a, b: c } });
    });
    return mem;
  },
  luasM2(S) { return Math.round(shoelace(S.verts) / 10000 * 100) / 100; },
  saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n); },
  // Posisi garis support internal sepanjang 1 sumbu (lo..hi). Dua mode (Spacing Per-Sumbu, 21 Ags):
  //  - 'kolom': N kolom = N ruas sama besar = N-1 garis di jarak span/N. Bagi rata PERSIS, tanpa sisa.
  //  - 'cm'   : langkah kotak cm dari lo (algoritma LAMA, backward-compat) -- boleh sisa ruas terakhir.
  // kotakFallback dipakai kalau kotak kosong/<=0 (denah lama tanpa kotakH) atau kolom tak valid.
  // Semua jalur di-guard biar tak pernah bagi-nol / loop tak berhenti (span<=0, kotak<=0, kolom<1, NaN).
  posisiSupport(lo, hi, mode, kotak, kolom, kotakFallback) {
    const span = hi - lo;
    if (!(span > 0)) return [];
    const out = [];
    if (mode === 'kolom' && Number.isFinite(kolom) && kolom >= 1) {
      const K = span / kolom;
      for (let i = 1; i < kolom; i++) out.push(lo + K * i);
      return out;
    }
    // mode 'cm' (atau kolom tak valid -> fallback ke cm)
    const K = (kotak > 0 ? kotak : (kotakFallback > 0 ? kotakFallback : 100));
    for (let v = lo + K; v < hi - 1; v += K) out.push(v);
    return out;
  },
  // ---- Fase terkunci (Support ID Stabil, 23 Ags) ----
  // supportsLocked = array -> fase terkunci; null/absen -> fase pratinjau (model lama, nol migrasi).
  isLocked(S) { return Array.isArray(S.supportsLocked); },
  // Bekukan susunan support jadi entri ber-ID stabil. MURNI: tidak memutasi S; kembalikan patch
  // untuk Object.assign(S, patch). Penomoran SEKALI di sini: H atas->bawah, lalu V kiri->kanan,
  // manual melanjutkan (spec 2.2). lockSeq lama (kalau ada, dari kunci sebelum Susun Ulang)
  // DIPERTAHANKAN: nomor baru melanjutkan, tak pernah dipakai ulang.
  lockSupports(S) {
    const V = S.verts, bb = bbox(V), K = (S.kotak > 0 ? S.kotak : 100), rem = S.removed || {};
    const mo = { ...(S.matOverride || {}) };
    const entries = [];
    let no = S.lockSeq > 0 ? S.lockSeq - 1 : 0;
    // 1 garis grid lama = banyak id per-potongan (Sh_{li}_{s}). Entri lahir nonaktif kalau SEMUA
    // potongannya kadung di-"Kecualikan"; sebagian doang -> tetap aktif (jalur = 1 kesatuan di
    // model baru, bolong parsial tak bisa direpresentasikan — hilang sadar, sesuai spec 2.5).
    // Override material: ambil dari potongan pertama yang punya, sisanya dibuang.
    const addGrid = (axis, pos, oldPrefix, segCount) => {
      no++;
      let aktif = segCount === 0;
      for (let s2 = 0; s2 < segCount; s2++) if (!rem[oldPrefix + s2]) aktif = true;
      const ovKey = Object.keys(mo).find(k => k.startsWith(oldPrefix));
      if (ovKey != null) { mo['SL' + no] = mo[ovKey]; Object.keys(mo).forEach(k => { if (k.startsWith(oldPrefix)) delete mo[k]; }); }
      entries.push({ no, axis, pos, aktif });
    };
    if (S.arah === 'h' || S.arah === '2') {
      DenahConv.posisiSupport(bb.y0, bb.y1, S.modeH || 'cm', S.kotakH, S.kolomH, K)
        .forEach((Yp, li) => addGrid('h', Yp, 'Sh_' + li + '_', Math.floor(scanX(V, Yp).length / 2)));
    }
    if (S.arah === 'v' || S.arah === '2') {
      DenahConv.posisiSupport(bb.x0, bb.x1, S.modeV || 'cm', S.kotakV, S.kolomV, K)
        .forEach((Xp, li) => addGrid('v', Xp, 'Sv_' + li + '_', Math.floor(scanY(V, Xp).length / 2)));
    }
    (S.supportsManual || []).forEach((m, i) => {
      no++;
      if (mo['Sm_' + i] != null) { mo['SL' + no] = mo['Sm_' + i]; delete mo['Sm_' + i]; }
      entries.push({ no, manual: true, a: { ...m.a }, b: { ...m.b }, aktif: true });
    });
    return { supportsLocked: entries, lockSeq: no + 1, matOverride: mo, supportsManual: [], removed: {} };
  },
  // "Susun Ulang": balik ke fase pratinjau. Entri grid dibuang (input spacing hidup lagi),
  // entri manual balik jadi supportsManual (ujung nyata dipertahankan, termasuk yang nonaktif —
  // model lama tak kenal nonaktif, mending muncul lagi daripada data hilang diam-diam).
  // Override grid ikut hangus (sesuai peringatan "editan per-garis di-reset"); override manual
  // di-remap ke Sm_{i}. lockSeq SENGAJA tidak di-patch: nilai lama tetap di S, kunci berikutnya
  // melanjutkan nomor (spec 2.2). MURNI, tidak memutasi S.
  unlockSupports(S) {
    const mo = { ...(S.matOverride || {}) };
    const manual = [];
    (S.supportsLocked || []).forEach(e => {
      const key = 'SL' + e.no;
      if (!e.manual) { delete mo[key]; return; }
      if (mo[key] != null) { mo['Sm_' + manual.length] = mo[key]; delete mo[key]; }
      manual.push({ a: { ...e.a }, b: { ...e.b } });
    });
    return { supportsLocked: null, supportsManual: manual, matOverride: mo, removed: {} };
  },
  // Pindah entri terkunci = ketik angka RELATIF (spec 2.3). Arah difilter: garis h cuma
  // atas/bawah, v cuma kiri/kanan, manual 4 arah. Return entri BARU atau null (tak valid).
  // "atas" = y layar mengecil (kanvas y tumbuh ke bawah).
  moveLockedSupport(entry, arah, cm) {
    if (!Number.isFinite(cm) || cm <= 0) return null;
    const d = { atas: [0, -cm], bawah: [0, cm], kiri: [-cm, 0], kanan: [cm, 0] }[arah];
    if (!d) return null;
    if (entry.manual) {
      return { ...entry, a: { x: entry.a.x + d[0], y: entry.a.y + d[1] }, b: { x: entry.b.x + d[0], y: entry.b.y + d[1] } };
    }
    if (entry.axis === 'h') return d[0] !== 0 ? null : { ...entry, pos: entry.pos + d[1] };
    if (entry.axis === 'v') return d[1] !== 0 ? null : { ...entry, pos: entry.pos + d[0] };
    return null;
  },
  // Sorot toleran fase terkunci (spec 2.3): kandidat id support dalam threshold th (cm) urut
  // jarak titik-ke-ruas naik. Multi-potongan ber-id sama muncul sekali (jarak terkecil).
  // Cycling "tap lagi = kandidat berikutnya" urusan UI, bukan di sini.
  supportsNearPoint(mem, pt, th) {
    const best = {};
    mem.forEach(m => {
      if (m.jenis !== 'support') return;
      const d = dist(pt, closestOnSegment(pt, m.geom.a, m.geom.b));
      if (d <= th && (best[m.id] == null || d < best[m.id])) best[m.id] = d;
    });
    return Object.keys(best).sort((a, b) => best[a] - best[b]);
  },
  // Teks info baris panel (spec 2.4) — posisi RELATIF bbox frame saat ini (ikut bentuk).
  // Jalur yang kegeser sampai luar frame tak tergambar (scan kosong) tapi tetap terdaftar;
  // ditandai di sini biar user paham kenapa garisnya "hilang".
  // Sumbu H diukur dari DEPAN (bb.y1), BUKAN dari atas (bb.y0) -- konvensi SAMA persis panel
  // Tiang (tiangToOffset: origin = {x:bb.x0, y:bb.y1}, "X dari kiri / Y dari depan"). "Depan"
  // kanopi = sisi terbuka/luar (bb.y1 di koordinat model ini) -- Sumbu V ("dari kiri", bb.x0)
  // sudah cocok dari awal dgn Tiang, tak diubah. mem (opsional) dipakai nambahin PANJANG asli
  // (dijumlah dari member yang sudah dibangun buildMembers -- itu yang benar kalau garis
  // kepotong coakan jadi >1 potongan, bukan re-hitung scan sendiri di sini) -- permintaan Elvan
  // 27 Ags: panjang lebih berguna drpd cuma posisi buat milih di dropdown.
  describeLockedSupport(S, e, mem) {
    if (e.manual) return 'manual · ' + Math.round(dist(e.a, e.b)) + 'cm';
    const bb = bbox(S.verts);
    const panjang = mem ? mem.filter(m => m.jenis === 'support' && m.id === 'SL' + e.no).reduce((s, m) => s + m.panjang, 0) : 0;
    const panjangTxt = panjang > 0 ? ' · ' + panjang + 'cm' : '';
    const txt = e.axis === 'h'
      ? 'datar · ' + Math.round(bb.y1 - e.pos) + 'cm dari depan' + panjangTxt
      : 'tegak · ' + Math.round(e.pos - bb.x0) + 'cm dari kiri' + panjangTxt;
    const luar = e.axis === 'h' ? (e.pos <= bb.y0 || e.pos >= bb.y1) : (e.pos <= bb.x0 || e.pos >= bb.x1);
    return luar ? txt + ' (di luar frame)' : txt;
  },
  // Vektor satuan arah LUAR polygon dari sisi a->b. Deteksi pakai point-in-polygon (scanX
  // parity) di titik uji sedikit di sisi normal — robust utk winding CW/CCW & bentuk coakan,
  // tanpa asumsi urutan vertex. Dipakai penempatan label frame DI LUAR garis (aturan Elvan
  // 24 Ags: frame di luar, support di dalam, biar tak pernah tabrakan).
  outwardNormal(V, a, b) {
    const dx = b.x - a.x, dy = b.y - a.y, len = Math.hypot(dx, dy) || 1;
    const nx = dy / len, ny = -dx / len;
    const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    const eps = Math.max(1, len * 0.01);
    const probe = { x: mid.x + nx * eps, y: mid.y + ny * eps };
    const xs = scanX(V, probe.y);
    let c = 0; for (const x of xs) if (x < probe.x) c++;
    const inside = c % 2 === 1;
    return inside ? { x: -nx, y: -ny } : { x: nx, y: ny };
  },
  // Teks label support sesuai ruang (satuan svg; font 9 -> ~5.5/karakter + bantalan).
  // Potongan pendek turun ke nomor saja, super pendek tanpa label; tersorot selalu penuh.
  supportLabelText(fullText, shortText, lenSvg, selected) {
    if (selected) return fullText;
    if (lenSvg >= fullText.length * 5.5 + 8) return fullText;
    if (shortText && lenSvg >= shortText.length * 5.5 + 8) return shortText;
    return '';
  },
  // Denah versi KERTAS untuk penawaran cetak: palet layar (latar biru gelap) dipetakan ke
  // palet putih. Bukan sekadar tukar background -- warna besi PALET dirancang untuk latar
  // gelap; kuning/lime/tosca nyaris hilang di kertas putih, jadi tiap warna butuh pasangan
  // gelapnya sendiri. Sengaja fungsi MURNI string (bukan DOM) supaya bisa dites headless;
  // pembuangan elemen bantu editor (titik sudut, area sentuh) dikerjakan pemanggil lewat DOM.
  // Satu kali pass regex, bukan replace berantai -- kalau berantai, hasil replace pertama
  // (mis. teks jadi #ffffff) bisa kena aturan replace berikutnya.
  svgCetak(html) {
    if (typeof html !== 'string') return '';
    const map = {
      '#0f2740': '#ffffff', // latar kanvas + halo teks
      '#1e3a5f': '#e5e7eb', // garis grid
      '#e2e8f0': '#111827', // teks label frame/balok
      '#93c5fd': '#1d4ed8', // teks label support
      '#fbbf24': '#b45309', // teks label tiang
      '#f59e0b': '#b45309', '#38bdf8': '#0369a1', '#a3e635': '#4d7c0f', '#f472b6': '#be185d',
      '#c084fc': '#7e22ce', '#fb7185': '#be123c', '#2dd4bf': '#0f766e', '#facc15': '#a16207',
    };
    return html.replace(/#[0-9a-fA-F]{6}\b/g, m => map[m.toLowerCase()] || m);
  },
  // Potongan jalur (axis+pos absolut) dari polygon SAAT INI. Tiap potongan BERHENTI di
  // perpotongan frame — TIDAK menyeberangi coakan (keputusan Elvan 23 Ags: potongan yang
  // tak diperlukan harus bisa dibuang sendiri-sendiri). Dasar bersama utk form "+ Garis
  // support" numerik dan "Pecah jadi manual".
  jalurSegments(S, axis, pos) {
    const V = S.verts, out = [];
    if (axis === 'h') {
      const xs = scanX(V, pos);
      for (let s = 0; s + 1 < xs.length; s += 2) out.push({ a: { x: xs[s], y: pos }, b: { x: xs[s + 1], y: pos } });
    } else {
      const ys = scanY(V, pos);
      for (let s = 0; s + 1 < ys.length; s += 2) out.push({ a: { x: pos, y: ys[s] }, b: { x: pos, y: ys[s + 1] } });
    }
    return out;
  },
  // Entri manual dari posisi KETIK (cm relatif tepi DEPAN/bb.y1 utk datar, kiri/bb.x0 utk tegak —
  // konvensi SAMA persis describeLockedSupport & panel Tiang). Satu entri per potongan (nomor
  // lanjut lockSeq). MURNI. cmRel harus > 0: 0 = nempel tepi frame = duplikat batang frame, tolak.
  manualEntriesFromJalur(S, axis, cmRel) {
    if (!Number.isFinite(cmRel) || cmRel <= 0) return null;
    const bb = bbox(S.verts);
    const pos = axis === 'h' ? bb.y1 - cmRel : bb.x0 + cmRel;
    // Hardening (review Task 1): lockSeq bisa absen/stale relatif nomor entri yang sudah ada
    // (mis. abis pecah manual tanpa lockSeq ikut disimpan) — ambil max biar nomor baru gak dobel.
    let seq = Math.max(S.lockSeq || 1, ...(S.supportsLocked || []).map(e => e.no + 1), 1);
    const entries = DenahConv.jalurSegments(S, axis, pos)
      .map(sg => ({ no: seq++, manual: true, a: sg.a, b: sg.b, aktif: true }));
    return { entries, lockSeq: seq };
  },
  // "Pecah jadi manual": entri grid `no` diganti (in-place di daftar) oleh entri manual per
  // potongan — override besi disalin ke tiap potongan, aktif diwarisi. Setelah ini potongan
  // tak lagi ikut frame (konsekuensi sadar). MURNI; null kalau no bukan grid / 0 potongan.
  splitLockedGrid(S, no) {
    const list = (S.supportsLocked || []);
    const idx = list.findIndex(e => !e.manual && e.no === no);
    if (idx < 0) return null;
    const e = list[idx];
    const segs = DenahConv.jalurSegments(S, e.axis, e.pos);
    if (!segs.length) return null;
    const mo = { ...(S.matOverride || {}) };
    // Hardening (review Task 1): sama seperti manualEntriesFromJalur — max thd nomor entri
    // eksisting biar gak dobel kalau lockSeq absen/stale.
    let seq = Math.max(S.lockSeq || 1, ...(S.supportsLocked || []).map(e => e.no + 1), 1);
    const news = segs.map(sg => {
      const n2 = seq++;
      if (mo['SL' + no] != null) mo['SL' + n2] = mo['SL' + no];
      return { no: n2, manual: true, a: sg.a, b: sg.b, aktif: e.aktif !== false };
    });
    delete mo['SL' + no];
    const out = list.slice();
    out.splice(idx, 1, ...news);
    return { supportsLocked: out, lockSeq: seq, matOverride: mo, firstNo: news[0].no };
  },
  // Pindah entri terkunci LEWAT PANEL dgn re-clip ke frame (bug S16, 24 Ags: garis manual
  // lurus digeser masuk zona coakan tetap tergambar/terhitung menembus udara). Garis manual
  // LURUS (datar/tegak) di-rescan ke polygon di posisi barunya — berhenti di frame, terbelah
  // per potongan; potongan PERTAMA mempertahankan nomor entri (identitas "dipindah", bukan
  // entri baru), sisanya nomor baru + override dibawa. Garis MIRING (tap-2-titik) & entri
  // GRID tetap perilaku moveLockedSupport biasa. MURNI. Return null = arah/cm tak valid;
  // {entries: []} = posisi baru sepenuhnya di luar frame (caller wajib menolak pindah).
  moveManualReclip(S, entry, arah, cm) {
    const moved = DenahConv.moveLockedSupport(entry, arah, cm);
    if (!moved) return null;
    const mo = { ...(S.matOverride || {}) };
    const horiz = entry.manual && entry.a.y === entry.b.y;
    const vert = entry.manual && entry.a.x === entry.b.x;
    if (!horiz && !vert) return { entries: [moved], lockSeq: S.lockSeq, matOverride: mo };
    const axis = horiz ? 'h' : 'v';
    const pos = horiz ? moved.a.y : moved.a.x;
    let seq = Math.max(S.lockSeq || 1, ...(S.supportsLocked || []).map(e => e.no + 1), 1);
    const entries = DenahConv.jalurSegments(S, axis, pos).map((sg, i) => {
      const n2 = i === 0 ? entry.no : seq++;
      if (i > 0 && mo['SL' + entry.no] != null) mo['SL' + n2] = mo['SL' + entry.no];
      return { no: n2, manual: true, a: sg.a, b: sg.b, aktif: entry.aktif !== false };
    });
    return { entries, lockSeq: seq, matOverride: mo };
  },
  // ---- Balok melintang (portal frame + bracing) ----
  // Endpoint balok: {t: tiangIdx} = referensi tiang (ikut geser); {p:{x,y}} = titik bebas.
  // resolveBalokEndpoint: kembalikan koordinat cm ATAU null kalau ref tiang tak valid
  // (dilewati diam-diam di buildMembers/render — data tetap ada, tinggal user perbaiki lewat panel).
  resolveBalokEndpoint(S, end) {
    if (end && end.t != null) {
      const t = (S.tiang || [])[end.t];
      return t ? { x: t.x, y: t.y } : null;
    }
    if (end && end.p) return { x: end.p.x, y: end.p.y };
    return null;
  },
  // Hapus tiang di idx + kaskade ke balok: ref ke tiang itu -> dibekukan jadi titik bebas
  // (posisi TERAKHIR tiang tsb), ref ke tiang > idx -> shift -1. balokSeq tak berubah.
  // MURNI: tidak memutasi S; caller Object.assign(S, patch).
  cascadeTiangRemoval(S, tiangIdx) {
    const tiangLama = (S.tiang || [])[tiangIdx];
    const tiang = (S.tiang || []).filter((_, i) => i !== tiangIdx);
    const remap = (end) => {
      if (!end || end.t == null) return end && end.p ? { p: { x: end.p.x, y: end.p.y } } : end;
      if (end.t === tiangIdx) return { p: { x: tiangLama.x, y: tiangLama.y } };
      return { t: end.t > tiangIdx ? end.t - 1 : end.t };
    };
    const balok = (S.balok || []).map(b => ({ no: b.no, a: remap(b.a), b: remap(b.b), material: b.material }));
    return { tiang, balok, balokSeq: S.balokSeq || (balok.reduce((m, b) => Math.max(m, b.no), 0) + 1) };
  },
  // Tempel kotak ke 1 sisi lurus (sisiIdx): sisipkan "detour" 4 titik pengganti segmen yang
  // ketutup. Tanda `depth` menentukan arah — SATU fungsi yang sama menghasilkan tonjolan
  // keluar (nambah) atau notch ke dalam (lekukan), tergantung tanda itu. UI (DenahEditor) yang
  // memutuskan tandanya dari posisi drag — fungsi ini tak tahu & tak perlu tahu mana "luar"/"dalam".
  combineBox(verts, sisiIdx, offset, span, depth) {
    const r = combineBoxCore(verts, sisiIdx, offset, span, depth);
    return r ? r.verts : null;
  },
  // Sama seperti combineBox, tapi juga ngasih boxIdx (index vertex milik kotak baru, di array
  // hasil) dan reindex (index lama -> index baru / -1 kalau dibuang karena segaris). Dipakai
  // applyBoxPreview buat update pembukuan combinedBoxes tanpa nebak-nebak arithmetic offset.
  combineBoxWithMeta(verts, sisiIdx, offset, span, depth) {
    return combineBoxCore(verts, sisiIdx, offset, span, depth);
  },
  // Remap index vertex di combinedBoxes lewat tabel `reindex` (dari combineBoxWithMeta). Entry
  // yang salah satu vertex-nya kena buang (reindex -1) ikut dibuang, sama seperti shiftBoxesDelete.
  reindexBoxes(boxes, reindex) {
    return (boxes || []).map(bx => ({ verts: bx.verts.map(i => reindex[i]) })).filter(bx => !bx.verts.includes(-1));
  },
  // Dipanggil setelah S.verts.splice(at, 0, ...count vertex baru...) (mode "+ Sudut"): index
  // vertex combinedBoxes yg >= at ikut geser +count (vertex baru masuk SEBELUM index itu).
  // ("+ Tambah Kotak" pakai reindexBoxes lewat combineBoxWithMeta, bukan ini — bisa ada sudut
  // lama yg DIBUANG, bukan cuma sisip.)
  shiftBoxesInsert(boxes, at, count) {
    return (boxes || []).map(bx => ({ verts: bx.verts.map(i => i >= at ? i + count : i) }));
  },
  // Dipanggil setelah S.verts.splice(at, 1) (mode "− Sudut"): entry yg salah satu vertex-nya
  // PERSIS `at` dibuang (kotak itu dianggap bukan satu kesatuan lagi — salah satu sudutnya hilang).
  // Entry lain yg index-nya > at ikut geser -1.
  shiftBoxesDelete(boxes, at) {
    return (boxes || []).filter(bx => !bx.verts.includes(at)).map(bx => ({ verts: bx.verts.map(i => i > at ? i - 1 : i) }));
  },
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint, _closestOnSegment: closestOnSegment,
  findAlignSnap, collectAlignCandidates,
};

// ============================================================================
// DenahEditor — kelas UI per-instance (Tahap 1B Task 2). Diporting dari
// tests/rangka/denah_prototype.html (disetujui Elvan). Perbedaan vs prototype:
// - Global S/mode/armed/... → this.S/this.mode/this.armed/... (state per instance)
// - Lookup DOM global by-id → this.el.querySelector(...) / this._q(...)
//   (tiap instance bikin sub-DOM sendiri lewat this.el.innerHTML, boleh banyak
//   instance hidup bareng di 1 halaman tanpa tabrakan id)
// - BESI hardcoded → this.besi dari opsi konstruktor
// - fetch('/rangka-desain/hitung') (biaya) DIHAPUS → this._changed() (app yang hitung)
// - undoStack global → this.undoStack
// ============================================================================
class DenahEditor {
  constructor(el, opts) {
    this.el = el;
    this.opts = opts || {};
    this.besi = this.opts.besi || [];
    this.S = this.opts.model ? JSON.parse(JSON.stringify(this.opts.model)) : DenahEditor.defaultModel();
    if (this.besi.length) {
      // Nebak default per-jenis (sama pola dgn "default material tebakan" blok kanopi di
      // rab-opsi/index.blade.php): cari besi yang namanya MENGANDUNG kode ukuran, bukan
      // hardcode nama persis (nama di master_material bisa ada embel2 tebal/merek).
      // Frame+Tiang -> hollow 5x10, Support -> hollow 4x8. Gak ketemu -> fallback besi pertama.
      const cari = (kw) => { const b = this.besi.find(x => x.nama && x.nama.toLowerCase().replace(/\s/g, '').includes(kw)); return b ? b.nama : this.besi[0].nama; };
      if (!this.S.matDefault.frame) this.S.matDefault.frame = cari('5x10');
      if (!this.S.matDefault.support) this.S.matDefault.support = cari('4x8');
      if (!this.S.matDefault.tiang) this.S.matDefault.tiang = cari('5x10');
      if (!this.S.matDefault.balok) this.S.matDefault.balok = cari('wf') || this.besi[0].nama;
    }
    // Sumber angka batang: endpoint server (lihat _jadwalCutting). Kosong = fitur diam.
    this.cutUrl = this.opts.cuttingUrl || '';
    this.cutCsrf = this.opts.csrf || '';
    this.batangKunci = ''; this.batangHtml = ''; this._cutTimer = null;
    this.undoStack = []; this.redoStack = [];
    this.mode = 'bentuk';
    this.armed = null;      // 'addV' | 'delV' | 'addSupport' | 'addBox'
    this.addSupportPt = null;
    this.boxPreview = null; // { sisiIdx, offset, span, depthMag, depthSign } selama armed === 'addBox'
    this.menuId = null;
    this.SC = 1;
    this.PAD = 44;
    this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
    this.tiangPreview = null; // visual-only; tidak pernah masuk model/Undo/autosave
    this.moveOn = false;      // toggle move quickbar (spec 2.3) — penanda alat, bukan data model
    this.selSup = null;       // no entri support terkunci yang tersorot (null = tak ada)
    this.supPanelOpen = false; // lipatan panel daftar Support (dilipat default, spec 2.4)
    this.selBalok = null;      // no balok yang tersorot (null = tak ada)
    this.balokPanelOpen = false; // lipatan panel daftar Balok Melintang
    this.selSisi = null;       // index sisi (F{n}) yang kotak Ukur-nya sedang dibuka (null = semua terlipat)
    this.sisiShowAll = false;  // checklist "Tampilkan semua" panel Ukur Sisi -- semua F1..Fn diketik sekaligus
    this.selTiang = null;      // index tiang (T{n}) yang baris edit-nya sedang dibuka (null = dropdown kosong)
    this.tiangShowAll = false; // checklist "Tampilkan semua" panel Tiang -- semua T1..Tn diedit sekaligus
    this.uid = ++DenahEditor._n;   // id unik per instance (pattern grid dirujuk url(#..) yg resolve se-dokumen)

    // Blok pinch-zoom HALAMAN (WebKit/iOS event gesturestart) selama ada editor di halaman --
    // pasangan body{touch-action:manipulation} di shell CSS (blok double-tap-zoom). Viewport meta
    // halaman memang sudah minta user-scalable=no tapi iOS SENGAJA ngabaikan itu; enforce di sini.
    // Zoom kanvas TIDAK terganggu (pakai pointer events sendiri, bukan native page zoom).
    // Sekali per halaman (guard static), sengaja tanpa removal: intent-nya page-scoped, dan
    // destroy() per-instance gak boleh nyabut penjaga selagi instance lain masih hidup.
    if (!DenahEditor._pageZoomGuard) {
      DenahEditor._pageZoomGuard = true;
      const stopGesture = (e) => e.preventDefault();
      document.addEventListener('gesturestart', stopGesture);
      document.addEventListener('gesturechange', stopGesture);
    }

    this.el.innerHTML = DenahEditor.shellHTML();
    // Blok menu long-press (context menu Android, jalur Google Lens Chrome) di dalam editor --
    // long-press di editor artinya gestur kita (menu tiang/support, dpad tahan), bukan menu
    // browser. Input/select DIKECUALIKAN: menu paste di kolom angka harus tetap hidup.
    this.el.addEventListener('contextmenu', (e) => {
      if (!e.target.closest('input,select,textarea')) e.preventDefault();
    });
    // Lapisan terdalam anti-seleksi: batalkan event selectstart-nya langsung. WebKit patuh ke
    // ini bahkan di kasus dia ngabaikan CSS user-select:none (teks svg saat long-press zoom
    // ekstrem). Input dikecualikan (seleksi saat ngetik/paste harus tetap jalan).
    this.el.addEventListener('selectstart', (e) => {
      if (!(e.target.closest && e.target.closest('input,select,textarea'))) e.preventDefault();
    });
    this._wireMatCombos();
    this._wireControls();
    this._wireRibbon();
    this._wireZoom();
    this._wireFullscreen();
    this.syncInputs();
    this.render();
  }

  // Foto denah utk dokumen (penawaran cetak, cutting list): SVG di layar diklon,
  // alat bantu editor dibuang (pita sentuh transparan, bulatan titik sudut, handle
  // ujung support, garis bantu snap, tooltip), sorotan support/balok dilepas
  // sementara lalu dipulihkan (sorotan balok sengaja awet -- sering masih nyala),
  // lalu warna dipetakan ke palet kertas (svgCetak). CATATAN: lingkaran tiang yang
  // TAMPAK juga ber-class "hit" -- patokan buang = warna transparan, bukan class itu.
  snapshotCetak() {
    const sSup = this.selSup, sBal = this.selBalok, sorot = (sSup != null || sBal != null);
    if (sorot) { this.selSup = null; this.selBalok = null; this.render(); }
    const svg = this._q('.de-canvas svg');
    let out = '';
    if (svg) {
      const k = svg.cloneNode(true);
      [].slice.call(k.querySelectorAll('[stroke="transparent"],[fill="transparent"],.vh,[id^=smh],[id^=slh],[id^=agx],[id^=agy],[data-boxprev],title')).forEach(e => e.remove());
      out = DenahConv.svgCetak(k.outerHTML);
    }
    if (sorot) { this.selSup = sSup; this.selBalok = sBal; this.render(); }
    return out;
  }

  // Jumlah batang per material di legend — DITANYAKAN KE SERVER, tidak dihitung sendiri.
  // Rumus lokal "total panjang / 600" tak memperhitungkan sisa potongan yang terbuang,
  // batas 1 sambungan per potong, dan panjang batang per material (tak selalu 6m). Uji
  // 6000 kombinasi ukuran wajar: rumus itu TAK PERNAH lebih besar dari kebutuhan nyata —
  // selisihnya selalu ke arah kurang beli (~8% kasus). Server memakai mesin yang sama
  // dengan perhitungan harga, jadi angka di sini dan di step Harga tak akan beda.
  // Dipanggil dari render() (sering); permintaan ditunda 1,5 dtk setelah gambar berhenti
  // berubah, dan dilewati sama sekali kalau susunan batang sama dengan hasil terakhir.
  _jadwalCutting(mem) {
    const el = this._q('[data-role=legendBatang]');
    if (!el) return;
    if (!this.cutUrl) { el.innerHTML = ''; return; }   // dipakai di luar halaman RAB: fitur diam
    const items = (mem || []).filter(m => m.material && m.panjang > 0)
      .map(m => ({ nama: m.nama, material: m.material, panjang: m.panjang }));
    const kunci = items.map(i => i.material + '|' + i.panjang).sort().join(',');
    if (kunci === this.batangKunci) { el.innerHTML = this.batangHtml || ''; return; }
    el.innerHTML = '<span style="color:#94a3b8">menghitung batang…</span>';
    // Penanda permintaan terbaru: jawaban yang datang untuk gambar yang SUDAH BERUBAH
    // harus dibuang, bukan ditampilkan. Tanpa ini angka lama sempat nongol sebagai
    // angka baru — dan angka batang itu dipakai orang buat belanja besi.
    this._cutKunci = kunci;
    clearTimeout(this._cutTimer);
    this._cutTimer = setTimeout(() => {
      if (!items.length) { this.batangKunci = kunci; this.batangHtml = ''; const e = this._q('[data-role=legendBatang]'); if (e) e.innerHTML = ''; return; }
      fetch(this.cutUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cutCsrf, 'Accept': 'application/json' },
        body: JSON.stringify({ members: items }),
      }).then(r => r.json()).then(res => {
        if (this._cutKunci !== kunci) return;            // gambar sudah berubah — jawaban ini basi
        if (!res || !res.ok) throw new Error('tolak');
        const ks = Object.keys(res.batang || {});
        this.batangKunci = kunci;
        this.batangHtml = ks.length
          ? '<span style="color:#a855f7;font-weight:600">' + ks.map(k => `${k}: ${res.batang[k]} batang`).join(' · ') + '</span>'
          : '';
        const e = this._q('[data-role=legendBatang]'); if (e) e.innerHTML = this.batangHtml;
      }).catch(() => {
        if (this._cutKunci !== kunci) return;
        // Sinyal lapangan putus itu wajar. Jangan tinggalkan angka basi yang bisa dipakai
        // belanja — kosongkan kuncinya supaya percobaan berikutnya menghitung ulang.
        this.batangKunci = '';
        const e = this._q('[data-role=legendBatang]'); if (e) e.innerHTML = '<span style="color:#94a3b8">jumlah batang belum bisa dihitung (sinyal?)</span>';
      });
    }, 1500);
  }

  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20,
      kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [],
      balok: [], balokSeq: 1,
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '', balok: '' }, matOverride: {}, combinedBoxes: [],
    };
  }

  static shellHTML() {
    return `
<style>
.de-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.de-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.de-row>label{font-size:11px;display:flex;flex-direction:column;gap:2px}
/* font-size input/select TAK BISA di bawah 16px -- aturan global
   "input,select,textarea{font-size:16px!important}" di layouts/app.blade.php
   sengaja mencegah iOS Safari auto-zoom halaman saat tap kolom. Kompak lewat
   padding/lebar kotak saja. */
.de-card input[type=number],.de-card input[type=text]{width:64px;box-sizing:border-box;padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px}
.de-card select{padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;background:#fff}
.de-tool{padding:6px 10px;min-height:34px;box-sizing:border-box;display:inline-flex;align-items:center;gap:5px;border:1px solid #334155;background:#fff;border-radius:7px;font-size:12px;cursor:pointer;user-select:none}
.de-tool.on{background:#1e293b;color:#fff}
.de-mini{padding:6px 10px;min-height:34px;box-sizing:border-box;display:inline-flex;align-items:center;gap:5px;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:11px;cursor:pointer}
.de-mini.on{background:#1e293b;color:#fff;border-color:#1e293b}
.de-hint{font-size:12px;color:#64748b;margin:6px 2px;min-height:16px}
.de-sup-axis{align-items:flex-end;gap:8px}
.de-sup-axname{font-size:12px;font-weight:600;color:#334155;min-width:0;padding-bottom:7px;display:inline-flex;align-items:center;gap:3px}
.de-sup-axis>label{flex:0 1 auto}
.de-sup-cm{font-size:12px;font-weight:700;color:#0369a1;padding-bottom:7px;white-space:nowrap}
/* Wrapper sticky ribbon+quickbar: dulu cuma .de-ribbon yang sticky, jadi pas scroll/zoom dalam
   ikon quickbar (grid/undo/redo/fullscreen) ketinggalan di atas (laporan Elvan 22 Ags malam).
   Pola sticky yang sama persis, cuma cakupannya diperluas -- bukan position:fixed (iOS-sensitif). */
.de-sticky{position:sticky;top:0;z-index:15;background:#fff}
.de-ribbon{position:relative;z-index:15;margin-bottom:10px}
.de-ribbon-tabs{display:flex;border:1px solid #334155;border-radius:8px;overflow:hidden;background:#1e293b}
.de-ribbon-tab{flex:1;text-align:center;padding:11px 4px;min-height:40px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;color:#cbd5e1;cursor:pointer;user-select:none;border-right:1px solid #334155}
.de-ribbon-tab:nth-last-child(2){border-right:none}
.de-ribbon-tab.on{background:#0f2740;color:#38bdf8;font-weight:600}
.de-ribbon-strip{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:20;border:1px solid transparent;border-radius:8px;background:#f8fafc;padding:0;max-height:0;overflow:hidden;transition:max-height .15s ease;box-shadow:0 6px 18px rgba(0,0,0,.28)}
.de-ribbon-strip.open{border-color:#334155;padding:10px 12px;max-height:45vh;overflow-y:auto}
.de-ribbon-panel{display:none}
.de-ribbon-panel.on{display:block}
.de-quickbar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;align-items:center}
.de-quickbar .de-mini,.de-quickbar .de-tool{padding:9px 10px}
.de-ico{width:18px;height:18px;display:block;flex:0 0 auto}
.de-card.de-fullscreen{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;overflow-y:auto;border-radius:0;margin:0;box-shadow:none}
.de-fullscreen-exit{display:none;flex:0 0 auto;min-height:40px;box-sizing:border-box;padding:0 18px;margin-left:6px;border-radius:8px;background:#f59e0b;color:#1e293b;border:none;font-size:13px;font-weight:700;cursor:pointer;align-items:center;justify-content:center}
/* Halaman ber-editor: matikan double-tap-zoom halaman iOS (jebakan "tersesat" 22 Ags: zoom
   HALAMAN nyangkut, pinch di kanvas ketelan sistem zoom kanvas jadi gak bisa pinch-out balik,
   tombol center/reset cuma reset zoom KANVAS). Kanvas punya zoom sendiri + mode Perbesar Layar;
   zoom halaman di sini gak ada gunanya, cuma jadi jebakan. Pinch halaman diblok terpisah via
   gesturestart (lihat constructor). */
body{touch-action:manipulation}
/* Lanjutan jebakan iOS yang sama: setelah double-tap-zoom mati, double-tap jatuh ke perilaku
   default berikutnya = SELEKSI TEKS (blok biru nyangkut di tab/hint/label), dan di Chrome iOS
   long-press bisa munculin Google Lens. Kanvas udah anti-seleksi dari dulu; ratakan ke seluruh
   kartu editor. Input/select DIKECUALIKAN -- user-select:none di input bisa blok fokus/ketik di
   iOS lama. */
/* color eksplisit -- tanpa ini, teks yang gak diset warnanya sendiri WARIS warna dari <body>
   halaman (mode gelap app nyetel itu jadi abu-abu terang, buat latar gelap). Editor denah
   SELALU berlatar terang apapun mode app-nya, jadi warisan itu bikin teksnya nyaris tak
   keliatan di atas latar terang ini (laporan Elvan 27 Ags: "semua tulisan jadi transparan"
   pas app di mode gelap -- ternyata bukan animasi/CSS transparan, murni warna teks ke-inherit). */
.de-card,.de-matmenu,.de-tiangmenu,.de-supportmenu{color:#334155;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
.de-card input,.de-card select,.de-card textarea{-webkit-user-select:auto;user-select:auto}
/* Cegah pull-to-refresh (tarik-bawah = reload halaman) nyamber di tengah ngedit -- Chrome
   Android & iOS 16+. Scroll biasa tetap normal, cuma "mantul di ujung atas" yang gak lagi
   jadi reload. */
body,.page-content{overscroll-behavior-y:contain}
/* Desktop: cegah drag-ghost gambar/svg pas geser elemen denah pakai mouse */
.de-card img,.de-card svg{-webkit-user-drag:none}
.de-canvas-wrap{position:relative;touch-action:none;overflow:hidden;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
.de-canvas{background:#0f2740;border-radius:10px;padding:6px;overflow:hidden;transform-origin:0 0}
.de-canvas svg{max-width:100%;touch-action:none;display:block;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
/* Label ukuran/nama di svg = murni visual (tap sisi kerjanya lewat GARIS, bukan teksnya).
   pointer-events:none bikin long-press di zoom ekstrem gak bisa "megang" teksnya sama sekali --
   user-select:none saja TIDAK cukup: bug WebKit, teks svg kadang tetap terseleksi (Google bar
   Salin/Terjemahkan, laporan Elvan 22 Ags malam). Bonus: tap di atas label tembus ke garis. */
.de-canvas svg text{pointer-events:none}
.de-zoom-reset{position:absolute;right:10px;bottom:10px;min-width:44px;min-height:44px;padding:0 14px;border-radius:22px;background:rgba(15,23,42,.85);color:#e2e8f0;border:1px solid #334155;font-size:13px;display:none;align-items:center;justify-content:center;cursor:pointer;user-select:none}
.de-zoom-reset.show{display:flex}
.de-pan{position:absolute;left:10px;bottom:10px;display:none;grid-template-columns:repeat(3,34px);grid-template-rows:repeat(3,34px);gap:3px;z-index:6;touch-action:none}
.de-pan.show{display:grid}
.de-pan-btn{display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.8);border:1px solid #334155;border-radius:8px;color:#e2e8f0;cursor:pointer;user-select:none;-webkit-user-select:none;touch-action:none}
.de-pan-btn[data-pan=up]{grid-column:2;grid-row:1}
.de-pan-btn[data-pan=home]{grid-column:2;grid-row:2}
.de-pan-btn[data-pan=left]{grid-column:1;grid-row:2}
.de-pan-btn[data-pan=right]{grid-column:3;grid-row:2}
.de-pan-btn[data-pan=down]{grid-column:2;grid-row:3}
.de-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;font-size:12px;color:#475569}
.de-legend b{font-weight:600}
.de-sw{display:inline-block;width:11px;height:11px;border-radius:2px;margin-right:5px;vertical-align:middle}
.de-matmenu{position:fixed;z-index:9999;display:none;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px}
.de-matmenu .de-combo-input{width:150px}
/* Combobox besi: input teks biasa + daftar hasil filter di bawahnya (tap pilih).
   Dipakai di 4 titik (Frame/Support/Tiang default + popup Ganti Material) --
   native <select> diganti krn Elvan minta bisa DIKETIK cari besinya, dan
   <input list=datalist> TIDAK dipakai krn dukungan visualnya di iOS Safari
   tak konsisten (resiko "kelihatan ada, gak jalan" di HP -- app ini nyaris
   semua dipakai di iPhone). */
.de-combo-input{width:100%;box-sizing:border-box}
.de-combo-list{position:absolute;top:100%;left:0;right:0;z-index:40;background:#fff;border:1px solid #cbd5e1;border-radius:6px;max-height:180px;overflow-y:auto;display:none;box-shadow:0 4px 10px rgba(0,0,0,.15);margin-top:2px}
.de-combo-item{padding:8px 10px;font-size:13px;cursor:pointer;border-bottom:1px solid #f1f5f9}
.de-combo-item:last-child{border-bottom:none}
.de-combo-item:active{background:#f1f5f9}
.de-combo-empty{padding:8px 10px;font-size:12px;color:#94a3b8}
.de-matmenu .de-mrow{display:flex;gap:6px;margin-top:6px}
.de-tiangmenu{position:fixed;z-index:9999;display:none;flex-direction:column;gap:4px;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:6px}
.de-tiangmenu.show{display:flex}
.de-supportmenu{position:fixed;z-index:9999;display:none;flex-direction:column;gap:4px;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:6px}
.de-supportmenu.show{display:flex}
.de-tiang-panel{scroll-margin-top:56px}
.de-tiang-item{padding:6px 0;border-bottom:1px solid #e2e8f0}
.de-tiang-head{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:4px}
.de-tiang-actions{display:flex;gap:4px}
.de-tiang-actions .de-mini,.de-tiang-apply{min-height:30px;padding:4px 8px;font-size:11px}
.de-tiang-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:6px;align-items:end}
.de-tiang-fields>label{min-width:0;font-size:11px;display:flex;flex-direction:column;gap:2px}
.de-tiang-fields input{width:100%!important;min-width:0;box-sizing:border-box}
</style>
<div class="de-card">
  <div class="de-sticky">
  <div class="de-ribbon">
  <div class="de-ribbon-tabs">
    <span class="de-ribbon-tab" data-tab="rangka">Rangka</span>
    <span class="de-ribbon-tab" data-tab="support">Support</span>
    <span class="de-ribbon-tab" data-tab="tiang">Tiang</span>
    <span class="de-fullscreen-exit" data-role="btnFullscreenExit">Selesai</span>
  </div>
  <div class="de-ribbon-strip" data-role="ribbonStrip">
    <div class="de-ribbon-panel" data-panel="rangka">
      <div class="de-row">
        <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
        <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
        <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
      </div>
      <div class="de-row" style="margin-top:8px;align-items:flex-end">
        <label style="position:relative;flex:0 1 170px">Besi frame
          <input type="text" class="de-combo-input" data-role="matFrame" autocomplete="off" placeholder="Ketik/pilih besi">
          <div class="de-combo-list" data-role="matFrameList"></div>
        </label>
        <span class="de-mini" data-role="btnReset" title="Reset kotak dari Lebar×Panjang"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>Reset</span>
      </div>
      <div class="de-row" style="margin-top:8px">
        <span class="de-mini" data-role="btnAddV" title="Tambah Sudut — sisipkan titik baru di sisi"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 12l10 10 10-10z"/><path d="M12 8v8M8 12h8"/></svg>Sudut</span>
        <span class="de-mini" data-role="btnDelV" title="Hapus Sudut"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 12l10 10 10-10z"/><path d="M8 12h8"/></svg>Sudut</span>
        <span class="de-mini" data-role="btnAddBox" title="Tambah Kotak — lekukan/tonjolan di sisi"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="6" width="11" height="11" rx="1.5"/><path d="M18 9v6M15 12h6"/></svg>Kotak</span>
      </div>
      <div class="de-legend" data-role="sisiPanel" style="margin-top:8px"></div>
    </div>
    <div class="de-ribbon-panel" data-panel="support">
      <div class="de-row" data-role="rowSupArah">
        <label style="flex:1 1 auto">Arah support
          <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
        </label>
        <span class="de-mini" data-role="btnSaran" title="Isi Kotak (cm) otomatis dari Ideal per kotak"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.3A7 7 0 0 0 12 2Z"/></svg>Saran</span>
      </div>
      <div class="de-row" data-role="rowSupIdeal" style="margin-top:8px">
        <label>Ideal per kotak (cm)<input type="number" data-role="inIdeal" value="100" step="5" min="1"></label>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
      <div class="de-row de-sup-axis" data-role="rowSupH" style="margin-top:10px">
        <span class="de-sup-axname" title="Horizontal"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16M4 16h16"/></svg>H</span>
        <label>Mode<select data-role="modeH"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakH">Kotak (cm)<input type="number" data-role="inKotakH" step="5" min="1"></label>
        <label data-role="lblKolomH" style="display:none">Jumlah kolom<input type="number" data-role="inKolomH" step="1" min="1" max="200"></label>
        <span class="de-sup-cm" data-role="hintH"></span>
      </div>
      <div class="de-row de-sup-axis" data-role="rowSupV" style="margin-top:8px">
        <span class="de-sup-axname" title="Vertikal"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4v16M16 4v16"/></svg>V</span>
        <label>Mode<select data-role="modeV"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakV">Kotak (cm)<input type="number" data-role="inKotakV" step="5" min="1"></label>
        <label data-role="lblKolomV" style="display:none">Jumlah kolom<input type="number" data-role="inKolomV" step="1" min="1" max="200"></label>
        <span class="de-sup-cm" data-role="hintV"></span>
      </div>
      <div class="de-row" style="margin-top:8px">
        <label style="position:relative;flex:0 1 170px">Besi support
          <input type="text" class="de-combo-input" data-role="matSupport" autocomplete="off" placeholder="Ketik/pilih besi">
          <div class="de-combo-list" data-role="matSupportList"></div>
        </label>
        <span class="de-mini" data-role="btnAddSupport" title="Support manual — tap 2 titik di kanvas buat gambar garis sendiri"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18 18 4"/><path d="M18 7v6M15 10h6"/></svg>Manual</span>
        <span class="de-mini" data-role="btnRestoreSup" title="Pulihkan garis yang sudah dihapus"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/></svg>Pulihkan</span>
        <span class="de-mini" data-role="btnSusunUlang" style="display:none" title="Susun ulang grid support dari awal"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>Susun Ulang</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="tiang">
      <div class="de-row">
        <label style="position:relative;flex:0 1 170px">Besi tiang
          <input type="text" class="de-combo-input" data-role="matTiang" autocomplete="off" placeholder="Ketik/pilih besi">
          <div class="de-combo-list" data-role="matTiangList"></div>
        </label>
      </div>
    </div>
  </div>
  </div>
  <div class="de-quickbar">
    <label title="Snap grid — kelipatan pembulatan saat menggeser (cm)" style="display:flex;align-items:center;gap:5px">
      <svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
      <select data-role="inGrid"><option>1</option><option>2</option><option>5</option><option>10</option><option selected>20</option><option>25</option><option>50</option></select>
    </label>
    <span class="de-tool" data-mode="besi" title="Ganti besi (ubah besi 1 batang)" aria-label="Ganti besi"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v18M8 3 4 7M8 3l4 4M16 21V3M16 21l-4-4M16 21l4-4"/></svg></span>
    <span class="de-mini" data-role="btnMove" title="Geser/sorot elemen (mati = kanvas murni lihat)" aria-label="Toggle move" style="display:none"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20M12 2 9 5M12 2l3 3M12 22l-3-3M12 22l3-3M2 12l3-3M2 12l3 3M22 12l-3-3M22 12l-3 3"/></svg></span>
    <span class="de-mini" data-role="btnUndo" title="Undo" aria-label="Undo"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-4"/></svg></span>
    <span class="de-mini" data-role="btnRedo" title="Redo" aria-label="Redo"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h4"/></svg></span>
    <span class="de-mini" data-role="btnFullscreen" title="Perbesar Layar" aria-label="Perbesar Layar"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H4a1 1 0 0 0-1 1v4M16 3h4a1 1 0 0 1 1 1v4M8 21H4a1 1 0 0 1-1-1v-4M16 21h4a1 1 0 0 0 1-1v-4"/></svg></span>
  </div>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
  <div class="de-hint" data-role="hint"></div>
  <div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="tiangPanel"></div>
  <div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="supportPanel"></div>
  <div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="balokPanel"></div>
  <div class="de-canvas-wrap" data-role="canvasWrap">
    <div class="de-canvas"></div>
    <span class="de-zoom-reset" data-role="btnZoomReset">Reset</span>
    <div class="de-pan" data-role="panPad">
      <span class="de-pan-btn" data-pan="up" aria-label="Geser atas"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></svg></span>
      <span class="de-pan-btn" data-pan="home" aria-label="Balik ke tampilan penuh"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg></span>
      <span class="de-pan-btn" data-pan="left" aria-label="Geser kiri"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></span>
      <span class="de-pan-btn" data-pan="right" aria-label="Geser kanan"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
      <span class="de-pan-btn" data-pan="down" aria-label="Geser bawah"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
    </div>
  </div>
  <div class="de-legend" data-role="legend"></div>
  <div class="de-legend" data-role="legendBatang" style="margin-top:4px"></div>
  <div style="font-size:12px;color:#64748b;margin-top:4px">Luas denah: <b data-role="luas">–</b></div>
</div>
<div class="de-matmenu" data-role="matMenu">
  <div data-role="matMenuLabel" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px"></div>
  <div style="position:relative">
    <input type="text" class="de-combo-input" data-role="matPick" autocomplete="off" placeholder="Ketik/pilih besi">
    <div class="de-combo-list" data-role="matPickList"></div>
  </div>
  <div class="de-mrow"><span class="de-mini" data-role="matApply">Ganti</span><span class="de-mini" data-role="matClear">Pakai default</span><span class="de-mini" data-role="matCancel">Batal</span></div>
</div>
<div class="de-tiangmenu" data-role="tiangMenu">
  <span class="de-mini" data-role="tiangMenuTambah">Tambah Tiang</span>
  <span class="de-mini" data-role="tiangMenuGanti">Ganti Besi</span>
  <span class="de-mini" data-role="tiangMenuHapus">Hapus</span>
  <span class="de-mini" data-role="tiangMenuCancel">Batal</span>
</div>
<div class="de-supportmenu" data-role="supportMenu">
  <span class="de-mini" data-role="supportMenuKecualikan">Kecualikan</span>
  <span class="de-mini" data-role="supportMenuGanti">Ganti Material</span>
  <span class="de-mini" data-role="supportMenuHapus">Hapus</span>
  <span class="de-mini" data-role="supportMenuCancel">Batal</span>
</div>`;
  }

  // ---- helpers DOM (SELALU scoped ke this.el — jangan pernah global) ----
  _q(sel) { return this.el.querySelector(sel); }
  _qa(sel) { return this.el.querySelectorAll(sel); }

  _wireMatCombos() {
    const matKeys = { matFrame: 'frame', matSupport: 'support', matTiang: 'tiang' };
    Object.keys(matKeys).forEach(role => {
      const input = this._q(`[data-role=${role}]`), list = this._q(`[data-role=${role}List]`);
      if (input && list) this._wireBesiCombo(input, list, v => { this.pushUndo(); this.S.matDefault[matKeys[role]] = v; this.render(); });
    });
    // matPick (popup Ganti Material): pilih di combobox cuma isi kotaknya -- komit sungguhan
    // lewat tombol "Ganti" (matApply, baca .value persis seperti waktu masih <select>).
    const pickInput = this._q('[data-role=matPick]'), pickList = this._q('[data-role=matPickList]');
    if (pickInput && pickList) this._wireBesiCombo(pickInput, pickList, () => {});
  }

  // Combobox besi: input teks + daftar hasil filter (tap pilih). SATU implementasi dipakai di
  // 4 titik (Frame/Support/Tiang default + popup Ganti Material) -- native <select> diganti krn
  // Elvan minta bisa DIKETIK cari besinya; <input list=datalist> dihindari krn dukungan visualnya
  // di iOS Safari tak konsisten (app ini nyaris semua dipakai di iPhone, resiko "kelihatan ada,
  // gak jalan"). onPick dipanggil HANYA saat nilai valid (ada di this.besi) -- baik lewat tap
  // item maupun ketik nama PERSIS lalu blur. Nilai tak valid saat blur -> revert ke nilai
  // terakhir yang valid -- native <select> tak pernah bisa nyimpan nilai tak valid, kombobox
  // custom ini WAJIB jaga invariant yang sama, kalau tidak nama besi ngaco masuk ke
  // matDefault/matOverride -> hargaOf() gak ketemu -> harga RAB salah diam-diam.
  _wireBesiCombo(input, listEl, onPick) {
    let lastGood = input.value;
    const filterList = (q) => {
      const needle = (q || '').toLowerCase();
      const matches = this.besi.filter(b => b.nama.toLowerCase().includes(needle)).slice(0, 40);
      listEl.innerHTML = matches.length
        ? matches.map(b => `<div class="de-combo-item" data-nama="${b.nama.replace(/"/g, '&quot;')}">${b.nama}</div>`).join('')
        : '<div class="de-combo-empty">Tak ditemukan</div>';
      listEl.style.display = 'block';
    };
    // Kosongkan (bukan input.select()) saat fokus -- .select() di iOS Safari memicu menu sistem
    // "Potong/Salin/Tempel/Isi-Auto" yang nutupin & rebutan sentuhan sama dropdown custom di
    // bawahnya (laporan Elvan 27 Ags: dropdown "selalu hilang" pas mau tap pilih -- bukan bug
    // tap-nya). Set .value langsung (bukan seleksi) tidak memicu menu itu, dan blur tetap balik
    // ke lastGood otomatis kalau dikosongin lalu ditinggal tanpa pilih apa-apa.
    input.addEventListener('focus', () => {
      lastGood = input.value; input.value = ''; filterList('');
      // Panel Rangka/Support/Tiang (.de-ribbon-strip) scroll sendiri (overflow-y:auto, max-
      // height 45vh) -- dropdown absolute ini ikut kepotong batas box itu kalau inputnya deket
      // bawah panel (laporan Elvan: Besi support cuma nongol 1 baris, krn baris itu paling
      // bawah panel Support). Geser panel biar input mepet ke atas -> nyisain ruang buat daftar
      // (180px, ~5 baris) di bawahnya. setTimeout nunggu scroll-ke-fokus bawaan browser (kalau
      // ada) beres duluan sebelum kita ukur & geser sendiri.
      setTimeout(() => {
        const strip = input.closest('.de-ribbon-strip');
        if (strip) strip.scrollTop += input.getBoundingClientRect().top - strip.getBoundingClientRect().top - 8;
      }, 50);
    });
    input.addEventListener('input', () => filterList(input.value));
    // click (bukan pointerdown): pointerdown+preventDefault dulu dipakai cegah blur nutup
    // listEl duluan, tapi preventDefault di pointerdown juga MEMBATALKAN gestur scroll dari
    // titik sentuh itu -- di HP jari nyentuh langsung dianggap pilih, gak sempat digeser cari
    // dulu (laporan Elvan 27 Ags: mau scroll dropdown dulu baru pilih). click tetap jalan
    // normal abis blur karena blur SENGAJA nunda tutup listEl 150ms (lihat bawah) -- cukup buat
    // click sempat kebaca, dan scroll di dalam list kini tidak lagi diblokir.
    listEl.addEventListener('click', e => {
      const item = e.target.closest('.de-combo-item');
      if (!item) return;
      input.value = item.dataset.nama;
      lastGood = item.dataset.nama;
      listEl.style.display = 'none';
      onPick(item.dataset.nama);
    });
    input.addEventListener('blur', () => {
      setTimeout(() => {
        listEl.style.display = 'none';
        if (this.besi.some(b => b.nama === input.value)) {
          if (input.value !== lastGood) { lastGood = input.value; onPick(input.value); }
        } else {
          input.value = lastGood;
        }
      }, 150);
    });
  }

  // Atribut label frame: posisi di LUAR polygon + rotasi ikut arah sisi (teks selalu tegak-baca).
  // SATU sumber utk render() DAN update live saat drag (upLbl vert-drag & boxgroup) — dua jalur
  // itu wajib pakai ini juga, kalau tidak label "lompat" balik ke gaya lama selama drag.
  _frameLabelAttrs(a, b) {
    const n = DenahConv.outwardNormal(this.S.verts, a, b);
    const off = 11 / this.SC;
    const mx = (a.x + b.x) / 2 + n.x * off, my = (a.y + b.y) / 2 + n.y * off;
    let ang = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
    if (ang > 90 || ang <= -90) ang += 180;   // jangan pernah terbalik bacanya
    const lx = this.PAD + mx * this.SC, ly = this.PAD + my * this.SC;
    return { lx, ly, ang };
  }

  // Sinkron tanda visual "on" tombol + Sudut / − Sudut ke state this.armed (dipakai mode sticky).
  _syncVertBtns() {
    const a = this._q('[data-role=btnAddV]'), d = this._q('[data-role=btnDelV]');
    if (a) a.classList.toggle('on', this.armed === 'addV');
    if (d) d.classList.toggle('on', this.armed === 'delV');
  }

  _wireControls() {
    this._qa('.de-tool').forEach(elx => elx.onclick = () => {
      this._qa('.de-tool').forEach(t => t.classList.remove('on'));
      elx.classList.add('on');
      this.mode = elx.dataset.mode;
      this.armed = null; this.addSupportPt = null; this.boxPreview = null;
      this.setHint();
      this.render();   // render() sinkron tanda +Sudut/-Sudut (titik tunggal)
      if (this.mode === 'tiang') requestAnimationFrame(() => {
        const panel = this._q('[data-role=tiangPanel]');
        if (panel) panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
      });
    });
    // + Sudut / − Sudut = mode STICKY yang bisa di-toggle: tap sekali nyala & TETAP nyala,
    // jadi bisa klik sisi/sudut berkali-kali tanpa bolak-balik pencet tombol (permintaan Elvan
    // 22 Ags). Tap tombol yang sama lagi = matiin. Pindah tab juga matiin (via _wireControls).
    this._q('[data-role=btnAddV]').onclick = () => {
      if (this.mode !== 'bentuk') return;
      this.armed = (this.armed === 'addV') ? null : 'addV'; this.boxPreview = null;
      this.setHint(this.armed === 'addV' ? 'Mode Tambah Sudut aktif — klik sisi frame berkali-kali. Buka tab Rangka lagi lalu tap "Sudut" untuk berhenti.' : '');
      this._syncVertBtns(); this.renderBoxPanel();
      this._closeRibbon();
    };
    this._q('[data-role=btnDelV]').onclick = () => {
      if (this.mode !== 'bentuk') return;
      this.armed = (this.armed === 'delV') ? null : 'delV'; this.boxPreview = null;
      this.setHint(this.armed === 'delV' ? 'Mode Hapus Sudut aktif — klik sudut berkali-kali (min 3 sudut). Buka tab Rangka lagi lalu tap "Sudut" untuk berhenti.' : '');
      this._syncVertBtns(); this.renderBoxPanel();
      this._closeRibbon();
    };
    this._q('[data-role=btnUndo]').onclick = () => this.undo();
    this._q('[data-role=btnRedo]').onclick = () => this.redo();
    this._q('[data-role=btnAddSupport]').onclick = () => { if (this.mode !== 'support') return; this.armed = 'addSupport'; this.addSupportPt = null; this.setHint('Klik titik ke-1 support…'); };
    // Pulihkan semua garis support otomatis yang pernah di-"Kecualikan"/kadung hilang -- garis
    // grid cuma DITANDAI skip di S.removed, bukan dihapus beneran, jadi pemulihan = hapus tanda.
    // Bisa di-Undo (pushUndo dulu). Permintaan Elvan 22 Ags: grid bolong2 bekas insiden pinch,
    // re-bikin manual gak mungkin presisi.
    this._q('[data-role=btnRestoreSup]').onclick = () => {
      if (this.mode !== 'support') return;
      if (!Object.keys(this.S.removed || {}).length) { this.setHint('Tidak ada support yang pernah dihapus/dikecualikan.'); return; }
      this.pushUndo();
      this.S.removed = {};
      this.setHint('Semua support otomatis dipulihkan.');
      this.render();
    };
    // Toggle move (spec 2.3): default MATI = kanvas murni lihat/zoom/pan. Menyalakan pertama
    // kali di fase pratinjau = pintu kunci otomatis (spec 2.1). Hanya hidup di tab Support
    // (tahap ini; Frame/Tiang belum, spec bagian 4).
    this._q('[data-role=btnMove]').onclick = () => {
      if (this.mode !== 'support') return;
      this.moveOn = !this.moveOn;
      if (this.moveOn) this._lockNow();      // no-op kalau sudah terkunci
      if (!this.moveOn) this.selSup = null;  // matiin toggle = lepas sorotan
      this.render();
    };
    // "Susun Ulang" (spec 2.1): konfirmasi eksplisit, tak ada regenerate diam-diam. Bisa di-Undo.
    this._q('[data-role=btnSusunUlang]').onclick = () => {
      if (this.mode !== 'support') return;
      if (!DenahConv.isLocked(this.S)) return;
      if (!confirm('Susun ulang support? Editan per-garis (nonaktif, pindah posisi, besi per-garis grid) akan di-reset. Bisa di-Undo.')) return;
      this.pushUndo();
      Object.assign(this.S, DenahConv.unlockSupports(this.S));
      this.moveOn = false; this.selSup = null;
      this.setHint('Kembali ke mode susun: atur spacing, lalu kunci lagi saat siap.');
      this.syncInputs();
      this.render();
    };
    this._q('[data-role=btnAddBox]').onclick = () => {
      if (this.mode !== 'bentuk') return;
      this.armed = 'addBox';
      this.boxPreview = { sisiIdx: null, offset: 0, span: 100, depthMag: 100, depthSign: 1 };
      this.setHint('Ketuk sisi lurus tempat kotak mau nempel.');
      this._syncVertBtns();  // Tambah Kotak nggak lewat render() -> matikan tanda +Sudut/-Sudut manual
      this.renderBoxPanel();
      this._closeRibbon();
    };

    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inIdeal]').oninput = e => { this.S.idealKotak = Math.max(1, +e.target.value) || 100; this.updSaranHint(); };
    // Per-sumbu: mode dropdown ganti field yang tampil (cm vs kolom) + tulis ke model + render.
    this._q('[data-role=modeH]').onchange = e => { this.S.modeH = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=modeV]').onchange = e => { this.S.modeV = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inKotakH]').oninput = e => { this.S.modeH = 'cm'; this.S.kotakH = Math.max(1, +e.target.value) || this.S.kotakH; this.render(); };
    this._q('[data-role=inKotakV]').oninput = e => { this.S.modeV = 'cm'; this.S.kotakV = Math.max(1, +e.target.value) || this.S.kotakV; this.render(); };
    this._q('[data-role=inKolomH]').oninput = e => { this.S.modeH = 'kolom'; this.S.kolomH = Math.min(200, Math.max(1, Math.floor(+e.target.value))) || this.S.kolomH; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inKolomV]').oninput = e => { this.S.modeV = 'kolom'; this.S.kolomV = Math.min(200, Math.max(1, Math.floor(+e.target.value))) || this.S.kolomV; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inGrid]').onchange = e => { this.S.grid = +e.target.value; this.render(); };
    this._q('[data-role=inT]').oninput = e => { this.S.tinggi = +e.target.value || 300; this.render(); };
    this._q('[data-role=inL]').oninput = () => this.updSaranHint();
    this._q('[data-role=inL]').onchange = () => this.resizeBox();
    this._q('[data-role=inP]').onchange = () => this.resizeBox();
    this._q('[data-role=btnSaran]').onclick = () => this.applySaran();
    this._q('[data-role=btnReset]').onclick = () => { this.resetBox(); this._closeRibbon(); };

    // matFrame/matSupport/matTiang: dikawat lewat _wireMatCombos() (combobox besi).

    this._q('[data-role=matApply]').onclick = () => {
      if (this.menuId) { this.pushUndo(); this.S.matOverride[this.menuId] = this._q('[data-role=matPick]').value; this._q('[data-role=matMenu]').style.display = 'none'; this.render(); }
    };
    this._q('[data-role=matClear]').onclick = () => {
      if (this.menuId) { this.pushUndo(); delete this.S.matOverride[this.menuId]; this._q('[data-role=matMenu]').style.display = 'none'; this.render(); }
    };
    this._q('[data-role=matCancel]').onclick = () => { this._q('[data-role=matMenu]').style.display = 'none'; };

    // Menu tekan-tahan tiang — SATU pola dipakai buat DUA konteks: tiang yang sudah ada (Ganti
    // Besi/Hapus, this._tiangMenuIdx) DAN tempat kosong (Tambah, this._tiangAddPt) — dua-duanya
    // tak pernah aktif bareng, tombol yang tak relevan disembunyikan (lihat openTiangMenu/
    // openTiangAddMenu). Tak ada lagi commit otomatis/instan di mana pun — SEMUA mutasi tiang
    // (tambah/hapus/ganti besi) sekarang WAJIB lewat tap eksplisit di salah satu tombol menu ini.
    this._q('[data-role=tiangMenuTambah]').onclick = () => {
      if (this._tiangAddPt) { this.pushUndo(); this.S.tiang.push(this.clampTiang(this._tiangAddPt)); this._closeTiangMenu(); this.render(); }
    };
    this._q('[data-role=tiangMenuHapus]').onclick = () => {
      if (this._tiangMenuIdx != null) {
        this.pushUndo();
        const i = this._tiangMenuIdx;
        const affected = (this.S.balok || []).filter(b => (b.a.t === i) || (b.b.t === i)).length;
        Object.assign(this.S, DenahConv.cascadeTiangRemoval(this.S, i));
        if (affected) this.setHint(`${affected} balok yg terhubung tiang T${i + 1} dibekukan ujungnya jadi titik bebas.`);
        this._closeTiangMenu(); this.render();
      }
    };
    this._q('[data-role=tiangMenuGanti]').onclick = e => {
      if (this._tiangMenuIdx != null) { const id = 'T' + this._tiangMenuIdx; this._closeTiangMenu(); this.openMatMenu(e, id); }
    };
    this._q('[data-role=tiangMenuCancel]').onclick = () => this._closeTiangMenu();

    // Tombol menu Support — Hapus HANYA berlaku untuk manual (Sm_), grid tidak punya "Hapus"
    // (cuma Kecualikan). "Sertakan" DIHAPUS (finding review): support grid yang sudah dikecualikan
    // tak lagi punya garis/hit-target tergambar (buildMembers.addSeg skip id yg ada di S.removed),
    // jadi tombol itu TAK PERNAH bisa dijangkau tekan-tahan -- dead UI. Satu-satunya jalan balik dari
    // "Kecualikan" yang salah pencet adalah Undo (limitation lama, bukan regresi baru).
    // Ganti Material reuse openMatMenu() yang sudah generic per-prefix.
    this._q('[data-role=supportMenuKecualikan]').onclick = () => {
      if (this._supportMenuId) { this.pushUndo(); this.S.removed[this._supportMenuId] = true; this._closeSupportMenu(); this.render(); }
    };
    this._q('[data-role=supportMenuGanti]').onclick = e => {
      if (this._supportMenuId) { const id = this._supportMenuId; this._closeSupportMenu(); this.openMatMenu(e, id); }
    };
    this._q('[data-role=supportMenuHapus]').onclick = () => {
      if (this._supportMenuId && this._supportMenuId.startsWith('Sm_')) {
        this.pushUndo();
        const i = +this._supportMenuId.slice(3);
        this._spliceSupportManual(i);
        this._closeSupportMenu();
        this.render();
      }
    };
    this._q('[data-role=supportMenuCancel]').onclick = () => this._closeSupportMenu();

    this._docPointerDown = (e) => {
      const menu = this._q('[data-role=matMenu]');
      const tmenu = this._q('[data-role=tiangMenu]');
      const smenu = this._q('[data-role=supportMenu]');
      const canvas = this._q('.de-canvas');
      if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !(canvas && canvas.contains(e.target))) menu.style.display = 'none';
      if (tmenu && tmenu.classList.contains('show') && !tmenu.contains(e.target) && !(canvas && canvas.contains(e.target))) this._closeTiangMenu();
      if (smenu && smenu.classList.contains('show') && !smenu.contains(e.target) && !(canvas && canvas.contains(e.target))) this._closeSupportMenu();
    };
    document.addEventListener('pointerdown', this._docPointerDown);
  }

  _wireRibbon() {
    const tabs = this._qa('.de-ribbon-tab');
    const strip = this._q('[data-role=ribbonStrip]');
    const panels = {};
    this._qa('.de-ribbon-panel').forEach(p => { panels[p.dataset.panel] = p; });
    let openTab = null;
    const closeRibbon = () => {
      if (!openTab) return;
      strip.classList.remove('open');
      panels[openTab].classList.remove('on');
      tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); });
      openTab = null;
    };
    // Dipanggil dari tombol aksi (Reset/+Sudut/-Sudut/+Kotak, lihat _wireControls) supaya
    // panel langsung terlipat begitu ditekan -- kanvas kelihatan tanpa tap tab lagi
    // (permintaan Elvan 27 Ags). Mode armed (+/-Sudut, +Kotak) TETAP aktif walau panel
    // tertutup -- keadaan armed independen dari visibilitas panel.
    this._closeRibbon = closeRibbon;
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) { closeRibbon(); return; }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
      // Tiap tab = 1 mode (Ribbon 3 Tab, 22 Ags). Buka tab -> aktifkan mode-nya.
      const tabMode = { rangka: 'bentuk', support: 'support', tiang: 'tiang' }[name];
      if (tabMode && this.mode !== tabMode) {
        this._qa('.de-tool').forEach(el2 => el2.classList.remove('on'));
        this.mode = tabMode;
        this.armed = null; this.addSupportPt = null; this.boxPreview = null;
        this.setHint();
        this.render();   // render() sinkron tanda +Sudut/-Sudut (titik tunggal)
        if (tabMode === 'tiang') requestAnimationFrame(() => {
          const panel = this._q('[data-role=tiangPanel]');
          if (panel) panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
      }
    });
    this._docPointerDownRibbon = (e) => {
      const ribbon = this._q('.de-ribbon');
      if (openTab && ribbon && !ribbon.contains(e.target)) closeRibbon();
    };
    document.addEventListener('pointerdown', this._docPointerDownRibbon);
  }

  // Halaman ini scroll di kontainer `.page-content` (layouts/app.blade.php), yang pakai
  // -webkit-overflow-scrolling:touch — di Safari iOS, `position:fixed` pada elemen yang
  // BERSARANG di dalam kontainer semacam itu jadi rusak (ikut ke-scroll bareng kontainer,
  // bukan nempel viewport beneran). Solusinya: pindahkan `this.el` (mount point, isinya
  // .de-card + .de-matmenu) jadi anak langsung <body> selama fullscreen, baru position:fixed
  // jalan benar. Posisi asli diingat & dikembalikan pas keluar.
  _wireFullscreen() {
    const card = this._q('.de-card');
    const enterBtn = this._q('[data-role=btnFullscreen]');
    const exitBtn = this._q('[data-role=btnFullscreenExit]');
    const mount = this.el;
    const originalParent = mount.parentNode, originalNext = mount.nextSibling;
    const pageContent = document.querySelector('.page-content');
    enterBtn.onclick = () => {
      document.body.appendChild(mount);
      card.classList.add('de-fullscreen');
      card.scrollTop = 0;
      exitBtn.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      if (pageContent) pageContent.style.overflow = 'hidden';
    };
    exitBtn.onclick = () => {
      card.classList.remove('de-fullscreen');
      exitBtn.style.display = 'none';
      document.body.style.overflow = '';
      if (pageContent) pageContent.style.overflow = '';
      if (originalNext) originalParent.insertBefore(mount, originalNext); else originalParent.appendChild(mount);
      if (this._resetZoom) this._resetZoom();
    };
  }

  // Pinch-zoom + pan (CSS transform di atas .de-canvas — TIDAK menyentuh viewBox/SC/toCm,
  // yang tetap dipakai bindSvg() untuk drag vertex/support/box seperti sebelumnya).
  _wireZoom() {
    const wrap = this._q('[data-role=canvasWrap]');
    const resetBtn = this._q('[data-role=btnZoomReset]');
    const panPad = this._q('[data-role=panPad]');
    const canvasEl = () => this._q('.de-canvas');
    const pointers = new Map();
    let pinch = null; // { startDist, startScale, startMidLocal, startTx, startTy }
    let lastTapTime = 0, lastTapX = 0, lastTapY = 0;

    // Batasi geser (pan) supaya konten TAK PERNAH bisa keluar penuh dari layar -> gak bisa "tersesat".
    // transform-origin 0 0: konten menempati [tx, tx + natW*scale]; jaga tetap menutup wrap [0, wrapW].
    const clampPan = () => {
      const c = canvasEl(); if (!c) return;
      const scaledW = c.offsetWidth * this.zoomScale, scaledH = c.offsetHeight * this.zoomScale;
      const minTx = Math.min(0, wrap.clientWidth - scaledW), minTy = Math.min(0, wrap.clientHeight - scaledH);
      this.zoomTx = Math.max(minTx, Math.min(0, this.zoomTx));
      this.zoomTy = Math.max(minTy, Math.min(0, this.zoomTy));
    };
    const applyTransform = () => {
      clampPan();
      const c = canvasEl();
      if (c) c.style.transform = `translate(${this.zoomTx}px, ${this.zoomTy}px) scale(${this.zoomScale})`;
      resetBtn.classList.toggle('show', Math.abs(this.zoomScale - 1) > 0.01 || Math.abs(this.zoomTx) > 0.5 || Math.abs(this.zoomTy) > 0.5);
      if (panPad) panPad.classList.toggle('show', this.zoomScale > 1.01);
    };
    const resetZoom = () => {
      const c = canvasEl();
      if (c) { c.style.transition = 'transform .2s ease'; setTimeout(() => { if (c) c.style.transition = ''; }, 220); }
      this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
      applyTransform();
    };
    this._resetZoom = resetZoom;
    resetBtn.onclick = resetZoom;

    // Tombol geser (dpad, muncul saat zoom-in) -- "nyetir" zoomTx/zoomTy yg sama dgn pinch pan.
    // Arah = sisi yg ingin dilihat (tekan "kanan" -> konten kanan muncul). Tekan-tahan = geser terus.
    const PAN_STEP = 55;
    const panDelta = { up: [0, PAN_STEP], down: [0, -PAN_STEP], left: [PAN_STEP, 0], right: [-PAN_STEP, 0] };
    this._qa('[data-pan]').forEach(btn => {
      if (btn.dataset.pan === 'home') { btn.addEventListener('pointerdown', e => { e.preventDefault(); e.stopPropagation(); resetZoom(); }); return; }
      let timer = null;
      const step = () => { if (this.zoomScale <= 1) return; const [dx, dy] = panDelta[btn.dataset.pan]; this.zoomTx += dx; this.zoomTy += dy; applyTransform(); };
      const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
      btn.addEventListener('pointerdown', e => { e.preventDefault(); e.stopPropagation(); step(); stop(); timer = setInterval(step, 90); });
      btn.addEventListener('pointerup', stop);
      btn.addEventListener('pointerleave', stop);
      btn.addEventListener('pointercancel', stop);
    });

    const dist2 = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const mid2 = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

    wrap.addEventListener('pointerdown', e => {
      // Cegah event mouse-kompat (disintesis sebagian browser/WebView dari 1 sentuhan jari asli)
      // ikut dihitung sebagai jari ke-2 -> pinch nyasar padahal cuma 1 tangan. Pinch cuma masuk
      // akal dari sentuhan/pena asli, bukan mouse.
      if (e.pointerType === 'mouse') return;
      // Sentuhan di dpad geser bukan urusan pinch/zoom-reset -- diabaikan biar tak masuk peta pointer.
      if (e.target.closest && e.target.closest('[data-pan]')) return;
      // Jaga-jaga: buang entry pointer basi (>2 detik) sebelum nambah yang baru -- andai ADA 1
      // pointerup/pointercancel yg somehow gak nyampe wrap (mis. race kondisi lain), entry lama
      // gak numpuk selamanya dan bikin tap tunggal BERIKUTNYA keitung "jari ke-2" (pinch palsu).
      const nowTs = Date.now();
      for (const [pid, pv] of pointers) { if (nowTs - (pv.t || nowTs) > 2000) pointers.delete(pid); }
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY, t: nowTs });
      if (pointers.size === 2) {
        // jari ke-2 ini mungkin persis di atas vertex/support/box lain — cegah
        // bindSvg() (bubble-phase, listener di svg) memproses pointerdown ini
        // sebagai drag baru sebelum pinch dikenali (lihat fix Finding 2, task-4 review)
        e.stopPropagation();
        // batalkan drag 1-jari yg mungkin lagi jalan (vertex/support/box) di bindSvg --
        // panggil LANGSUNG (jalur dispatch synthetic event lama cacat: bubbles:false,
        // gak pernah nyampe listener; lihat komentar di bindSvg)
        if (this._cancelDrag) this._cancelDrag();
        const [p1, p2] = [...pointers.values()];
        const rect = wrap.getBoundingClientRect();
        const startMid = mid2(p1, p2);
        pinch = {
          startDist: dist2(p1, p2), startScale: this.zoomScale,
          startMidLocal: { x: startMid.x - rect.left, y: startMid.y - rect.top },
          startTx: this.zoomTx, startTy: this.zoomTy,
        };
      }
    }, { capture: true });

    wrap.addEventListener('pointermove', e => {
      if (!pointers.has(e.pointerId)) return;
      // WAJIB ikut refresh stempel `t`: tulisan tanpa `t` bikin penjaga jari-basi di pointerdown
      // (pv.t || nowTs -> umur 0) tak pernah bisa buang jari hantu yang pointerup-nya hilang
      // (swipe notifikasi/gestur pinggir iOS) -> semua tap berikutnya dianggap jari ke-2 pinch,
      // SELURUH kanvas mati di semua tab sampai halaman di-reload (laporan Elvan 24 Ags malam).
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY, t: Date.now() });
      if (pointers.size === 2 && pinch) {
        e.preventDefault();
        const [p1, p2] = [...pointers.values()];
        const rect = wrap.getBoundingClientRect();
        const newDist = dist2(p1, p2);
        const newMid = mid2(p1, p2);
        const newMidLocal = { x: newMid.x - rect.left, y: newMid.y - rect.top };
        const newScale = Math.min(4, Math.max(1, pinch.startScale * (newDist / pinch.startDist)));
        const ratio = newScale / pinch.startScale;
        this.zoomTx = newMidLocal.x - (pinch.startMidLocal.x - pinch.startTx) * ratio;
        this.zoomTy = newMidLocal.y - (pinch.startMidLocal.y - pinch.startTy) * ratio;
        this.zoomScale = newScale;
        applyTransform();
      }
    }, { passive: false });

    const clearPointer = e => { pointers.delete(e.pointerId); if (pointers.size < 2) pinch = null; };
    wrap.addEventListener('pointerup', e => {
      const wasSingle = pointers.size === 1 && !pinch;
      clearPointer(e);
      if (wasSingle && this.zoomScale !== 1) {
        const isEmpty = e.target.dataset.vert == null && !e.target.dataset.id && e.target.dataset.sm == null && !e.target.dataset.boxprev;
        const now = Date.now();
        if (isEmpty && now - lastTapTime < 450 && Math.hypot(e.clientX - lastTapX, e.clientY - lastTapY) < 40) {
          resetZoom(); lastTapTime = 0;
        } else { lastTapTime = now; lastTapX = e.clientX; lastTapY = e.clientY; }
      }
    });
    wrap.addEventListener('pointercancel', clearPointer);
  }

  // lepas listener document saat instance dibuang (blok di-hapus/off di RAB opsi)
  destroy() {
    if (this._docPointerDown) document.removeEventListener('pointerdown', this._docPointerDown);
    if (this._docPointerDownRibbon) document.removeEventListener('pointerdown', this._docPointerDownRibbon);
    // Blok dihapus selagi fullscreen aktif (this.el ke-detach ke <body>) — buang manual,
    // tak ada parent asli lagi yang otomatis membersihkannya.
    if (this.el.parentNode === document.body) this.el.remove();
  }

  _changed() { if (this.opts.onChange) this.opts.onChange(); }

  snap(v) { return Math.round(v / this.S.grid) * this.S.grid; }

  // Batasi posisi tiang tetap di dalam area yang BENERAN kegambar svg -- bug nyata dari Elvan:
  // tiang digeser sampai keluar area gambar utama (lewat sisi atas) jadi kepotong (svg
  // overflow:hidden default) -> tak kelihatan/tak bisa disentuh lagi. Ini kejadian LIVE saat drag
  // (attribute cx/cy di-update manual di pointermove tanpa render() ulang), jadi klem harus di
  // titik mutasi posisi, bukan cuma pas render.
  // PENTING: batas bawah BUKAN 0 -- render() kasih bantalan PAD (this.PAD satuan svg / this.SC)
  // di SEMUA sisi termasuk kiri/atas verts=0, itu jatah nyata yg MEMANG kegambar (bukan cuma di
  // dalam bentuk utama). Kalau batas bawahnya dipatok 0, tiang gak akan pernah bisa ditaruh di
  // ATAS/KIRI bentuk utama sama sekali -- padahal itu justru kasus asli yg mau diselesaikan
  // (tiang pinggir kanopi yg menjorok ke atas/kiri). 8cm inset dari tepi svg (bukan tepi verts)
  // biar gak pas mepet piksel.
  clampTiang(pt) {
    const inset = 8;
    const minC = -(this.PAD / this.SC) + inset;
    const maxX = (this.domW || 400) - inset, maxY = (this.domH || 400) - inset;
    return { x: Math.min(maxX, Math.max(minC, pt.x)), y: Math.min(maxY, Math.max(minC, pt.y)) };
  }

  setHint(extra) {
    // Hanya tampilkan petunjuk aksi sesaat (mis. "Klik sisi untuk sisipkan sudut"); tanpa aksi
    // aktif dibiarkan kosong biar tak makan tempat (deskripsi mode panjang dihapus 22 Ags).
    this._q('[data-role=hint]').textContent = extra || '';
  }

  // ---- Undo ----
  // pushUndo() dipanggil sebelum SETIAP mutasi (satu titik terpusat) — cabang baru selalu
  // membuang riwayat redo lama, sama seperti undo/redo di editor pada umumnya.
  pushUndo() { this.undoStack.push(JSON.stringify(this.S)); if (this.undoStack.length > 40) this.undoStack.shift(); this.redoStack = []; }
  undo() {
    // +Sudut/-Sudut = penanda alat UI (bukan bagian data), sengaja DIPERTAHANKAN lintas undo/redo
    // biar bisa lanjut hapus/tambah sudut tanpa pencet tombol lagi. addBox/addSupport tetap dimatikan
    // karena nyimpan preview posisi yg bisa basi setelah gambar berubah.
    if (this.armed !== 'addV' && this.armed !== 'delV') this.armed = null;
    this.boxPreview = null; this.addSupportPt = null;
    this._lastPickPt = null;   // cycling tap-ganti-kandidat direset; selSup divalidasi di render()
    this.selBalok = null;
    this.selSisi = null;
    this.selTiang = null;
    if (!this.undoStack.length) { this.setHint('Tak ada langkah untuk di-undo'); return; }
    this.redoStack.push(JSON.stringify(this.S)); if (this.redoStack.length > 40) this.redoStack.shift();
    // Assignment wholesale (bukan Object.assign): snapshot = state utuh, Object.assign tak
    // menghapus key yang absen di snapshot (mis. supportsLocked pas balik ke pratinjau).
    this.S = JSON.parse(this.undoStack.pop());
    this.syncInputs();
    this.render();
  }
  redo() {
    if (this.armed !== 'addV' && this.armed !== 'delV') this.armed = null;  // lihat catatan di undo()
    this.boxPreview = null; this.addSupportPt = null;
    this._lastPickPt = null;   // cycling tap-ganti-kandidat direset; selSup divalidasi di render()
    this.selBalok = null;
    this.selSisi = null;
    this.selTiang = null;
    if (!this.redoStack.length) { this.setHint('Tak ada langkah untuk di-redo'); return; }
    this.undoStack.push(JSON.stringify(this.S)); if (this.undoStack.length > 40) this.undoStack.shift();
    // Sama seperti undo(): assignment wholesale, bukan Object.assign (lihat catatan di atas).
    this.S = JSON.parse(this.redoStack.pop());
    this.syncInputs();
    this.render();
  }
  syncInputs() {
    this._q('[data-role=inArah]').value = this.S.arah;
    this._q('[data-role=inIdeal]').value = this.S.idealKotak != null ? this.S.idealKotak : 100;
    // Denah lama tanpa kotakH/kotakV -> tampilkan S.kotak sebagai nilai efektif (yang lagi tergambar).
    this._q('[data-role=modeH]').value = this.S.modeH || 'cm';
    this._q('[data-role=modeV]').value = this.S.modeV || 'cm';
    this._q('[data-role=inKotakH]').value = this.S.kotakH != null ? this.S.kotakH : this.S.kotak;
    this._q('[data-role=inKotakV]').value = this.S.kotakV != null ? this.S.kotakV : this.S.kotak;
    this._q('[data-role=inKolomH]').value = this.S.kolomH != null ? this.S.kolomH : '';
    this._q('[data-role=inKolomV]').value = this.S.kolomV != null ? this.S.kolomV : '';
    this._q('[data-role=inGrid]').value = this.S.grid;
    this._q('[data-role=inT]').value = this.S.tinggi;
    this._q('[data-role=matFrame]').value = this.S.matDefault.frame;
    this._q('[data-role=matSupport]').value = this.S.matDefault.support;
    this._q('[data-role=matTiang]').value = this.S.matDefault.tiang;
    this._syncSupportRows();
    this.syncLP();
  }

  // Tampil/sembunyikan baris setelan per-sumbu (Spacing Per-Sumbu, 21 Ags):
  //  - arah '2' -> dua baris; 'h' -> horizontal saja; 'v' -> vertikal saja (yang lain disembunyikan).
  //  - tiap baris: mode 'cm' tampilkan input Kotak, mode 'kolom' tampilkan input Jumlah kolom.
  _syncSupportRows() {
    const locked = DenahConv.isLocked(this.S);
    const arah = this.S.arah;
    // Terkunci: input spacing disembunyikan total, diganti tombol Susun Ulang (spec 2.1).
    this._q('[data-role=rowSupArah]').style.display = locked ? 'none' : '';
    // "Ideal per kotak" cuma berguna kalau cuma 1 sumbu yang dipakai (2 arah sudah punya 2 baris
    // Kotak(cm) sendiri-sendiri buat diisi manual) -- permintaan Elvan 27 Ags: sembunyikan biar
    // gak numpuk pas Grid 2 arah dipilih.
    this._q('[data-role=rowSupIdeal]').style.display = (!locked && arah !== '2') ? '' : 'none';
    this._q('[data-role=btnSusunUlang]').style.display = locked ? '' : 'none';
    this._q('[data-role=btnRestoreSup]').style.display = locked ? 'none' : '';
    this._q('[data-role=rowSupH]').style.display = (!locked && (arah === 'h' || arah === '2')) ? '' : 'none';
    this._q('[data-role=rowSupV]').style.display = (!locked && (arah === 'v' || arah === '2')) ? '' : 'none';
    const modeH = this.S.modeH || 'cm', modeV = this.S.modeV || 'cm';
    this._q('[data-role=lblKotakH]').style.display = modeH === 'cm' ? '' : 'none';
    this._q('[data-role=lblKolomH]').style.display = modeH === 'kolom' ? '' : 'none';
    this._q('[data-role=lblKotakV]').style.display = modeV === 'cm' ? '' : 'none';
    this._q('[data-role=lblKolomV]').style.display = modeV === 'kolom' ? '' : 'none';
    // Mode 'kolom': tampilkan hasil bagi rata (cm per kotak) di sebelah input jumlah kolom.
    //  H bagi span Y (Panjang), V bagi span X (Lebar) -- konvensi sama dgn applySaran/updSaranHint.
    const bb = DenahConv._bbox(this.S.verts);
    const fmtCm = (span, kolom) => (span > 0 && kolom >= 1) ? `= ${Math.round(span / kolom * 10) / 10} cm/kotak` : '';
    this._q('[data-role=hintH]').textContent = modeH === 'kolom' ? fmtCm(bb.y1 - bb.y0, this.S.kolomH) : '';
    this._q('[data-role=hintV]').textContent = modeV === 'kolom' ? fmtCm(bb.x1 - bb.x0, this.S.kolomV) : '';
  }

  // ---- Saran spacing per-sumbu (Spacing Per-Sumbu, 21 Ags) ----
  // Ideal per kotak diambil dari S.idealKotak (default 100). Dihitung TERPISAH:
  //  - Horizontal (garis Sh_, melangkah sepanjang Y) pakai span Y = Panjang.
  //  - Vertikal   (garis Sv_, melangkah sepanjang X) pakai span X = Lebar.
  _idealKotak() { return this.S.idealKotak > 0 ? this.S.idealKotak : 100; }
  applySaran() {
    const bb = DenahConv._bbox(this.S.verts);
    const spanY = bb.y1 - bb.y0, spanX = bb.x1 - bb.x0;
    const ideal = this._idealKotak();
    if (spanY > 0) { this.S.modeH = 'cm'; this.S.kotakH = DenahConv.saranKotak(spanY, ideal); }
    if (spanX > 0) { this.S.modeV = 'cm'; this.S.kotakV = DenahConv.saranKotak(spanX, ideal); }
    this.syncInputs();
    this.render();
  }
  updSaranHint() {
    const bb = DenahConv._bbox(this.S.verts);
    const spanY = bb.y1 - bb.y0, spanX = bb.x1 - bb.x0, ideal = this._idealKotak();
    const kH = spanY > 0 ? DenahConv.saranKotak(spanY, ideal) : 0;
    const kV = spanX > 0 ? DenahConv.saranKotak(spanX, ideal) : 0;
    this._q('[data-role=saranHint]').textContent = `saran ~${ideal}cm -> H ${kH}cm, V ${kV}cm`;
  }

  // sinkron Lebar/Panjang = bbox
  syncLP() {
    const bb = bbox(this.S.verts);
    this._q('[data-role=inL]').value = Math.round(bb.x1 - bb.x0);
    this._q('[data-role=inP]').value = Math.round(bb.y1 - bb.y0);
    this.updSaranHint();
  }

  // Ubah ukuran denah dari input Lebar/Panjang: skala semua titik (verts, tiang, support manual)
  // proporsional ke bounding-box baru — bentuk (L/berlekuk) tetap, cuma ukurannya berubah.
  resizeBox() {
    const L = +(this._q('[data-role=inL]').value) || 0;
    const P = +(this._q('[data-role=inP]').value) || 0;
    if (L <= 0 || P <= 0) return;
    const bb = DenahConv._bbox(this.S.verts);
    const w = (bb.x1 - bb.x0) || 1, h = (bb.y1 - bb.y0) || 1;
    const sx = L / w, sy = P / h;
    const sc = (pt) => ({ x: (pt.x - bb.x0) * sx + bb.x0, y: (pt.y - bb.y0) * sy + bb.y0 });
    this.pushUndo();
    this.S.verts = this.S.verts.map(sc);
    this.S.tiang = (this.S.tiang || []).map(sc);
    this.S.supportsManual = (this.S.supportsManual || []).map(m => ({ a: sc(m.a), b: sc(m.b) }));
    // Auto-recompute spacing saat resize DIHAPUS -- spacing sekarang eksplisit per-sumbu via input/saran, tidak lagi auto-ikut resize. Ini keputusan sadar: mode 'kolom' tetap rata otomatis walau ukuran berubah, mode 'cm' tetap nilai yang diketik user.
    this.render();
  }

  // Momen kunci otomatis (spec 2.1 opsi b): dipanggil dari toggle move ATAU header panel ajakan.
  // Mutasi model biasa lewat pushUndo -> bisa di-Undo (Undo balikin supportsLocked -> null).
  _lockNow() {
    if (DenahConv.isLocked(this.S)) return;
    this.pushUndo();
    Object.assign(this.S, DenahConv.lockSupports(this.S));
    this.supPanelOpen = true;
    this.setHint('Susunan dikunci — kelola per garis lewat panel Support.');
    this.render();
  }

  resetBox() {
    this.armed = null; this.boxPreview = null;
    // Sebelumnya BUANG total this.undoStack/redoStack -- bug: bentuk sebelum reset jadi hilang
    // permanen, Undo gak bisa balikin (laporan Elvan 22 Ags). Ganti pushUndo() (pola sama SEMUA
    // mutasi lain di file ini): simpan state SEKARANG dulu sbg 1 langkah undo, baru reset --
    // riwayat sebelum reset tetap ada, Undo bisa balik ke bentuk sebelum reset.
    this.pushUndo();
    const L = +(this._q('[data-role=inL]').value) || 400;
    const P = +(this._q('[data-role=inP]').value) || 300;
    this.S.verts = [{ x: 0, y: 0 }, { x: L, y: 0 }, { x: L, y: P }, { x: 0, y: P }];
    // Sengaja TIDAK menyentuh S.supportsLocked/lockSeq di sini -- kalau fase terkunci, garis
    // lock tetap ada dan ikut mengikuti bentuk frame baru (bukan "reset ke pratinjau"); ini
    // perilaku disengaja (bisa di-Undo lewat pushUndo() di atas), bukan celah yang perlu ditutup.
    this.S.removed = {}; this.S.supportsManual = []; this.S.matOverride = {}; this.S.combinedBoxes = [];
    this.S.tiang = [];   // JANGAN auto-taruh tiang di sudut — user yang tentukan tiang (mode Tiang)
    this.S.grid = +(this._q('[data-role=inGrid]').value);
    this.S.tinggi = +(this._q('[data-role=inT]').value);
    this.S.arah = this._q('[data-role=inArah]').value;
    this.applySaran();
  }

  // screen→cm. Ukur kotak svg langsung di layar (getBoundingClientRect, sudah termasuk
  // SEMUA skala yang berlaku: pinch-zoom kita + max-width:100% browser), bukan getScreenCTM
  // (bergantung browser mengurai CSS transform leluhur dgn benar — tak konsisten di sebagian
  // browser HP, bikin drag meleset dari jari saat pinch-zoom aktif).
  toCm(evt, el) {
    const rect = el.getBoundingClientRect();
    const svgW = el.width.baseVal.value, svgH = el.height.baseVal.value;
    const scaleX = rect.width / svgW, scaleY = rect.height / svgH;
    const localX = (evt.clientX - rect.left) / scaleX;
    const localY = (evt.clientY - rect.top) / scaleY;
    return { x: (localX - this.PAD) / this.SC, y: (localY - this.PAD) / this.SC };
  }

  // Rasio TAMPIL svg saat ini (rect.width layar / lebar intrinsik viewBox) — BEDA dari this.SC
  // (skala auto-fit konten ke viewBox, dihitung sekali per render, TETAP walau layar disusutkan
  // CSS max-width:100% di HP sempit atau di-pinch-zoom). Dipakai buat threshold sentuh (radius hit-
  // test tiang) biar toleransinya konsisten dgn PIKSEL LAYAR beneran, sama basis kayak toCm() di
  // atas — bug nyata: threshold lama (24/this.SC saja) di HP lebar ~360px jadi cuma ~12-13px
  // toleransi sentuh beneran (bukan 24px yg dimaksud), bikin tap tepat di tiang sering dianggap
  // meleset -> gagal digeser & tekan-tahan-nya nyasar buka menu "Tambah Tiang".
  screenScale(el) {
    const svgW = el.width.baseVal.value;
    return svgW ? el.getBoundingClientRect().width / svgW : 1;
  }

  // Set panjang sisi F(i) ke nilai pasti: geser vertex tujuan sepanjang arah sisi.
  // PRESISI: terima koma (148,5), TIDAK di-snap ke grid.
  setSideLength(i, raw) {
    const L = parseFloat(String(raw).replace(',', '.'));
    if (!(L > 0)) return;
    const n = this.S.verts.length, a = this.S.verts[i], b = this.S.verts[(i + 1) % n];
    const cur = dist(a, b) || 1, ux = (b.x - a.x) / cur, uy = (b.y - a.y) / cur;
    this.pushUndo();
    this.S.verts[(i + 1) % n] = { x: a.x + ux * L, y: a.y + uy * L };
    this.syncLP();
    this.render();
  }
  // Tap garis sisi di kanvas = pilih sisi itu di panel "Ukur Sisi", bukan lagi prompt() bawaan
  // browser. prompt() di iOS menutupi kanvas, tampilannya lepas dari panel lain yang sudah
  // dirapikan, dan jalur penggantinya memang sudah ada sejak 27 Ags (dropdown F1..Fn).
  // Satu tujuan, dua jalan masuk: lewat dropdown panel, atau tap garisnya langsung.
  typeSide(i) {
    this.selSisi = i;
    // Panel Rangka bisa sedang terlipat (auto-lipat setelah Reset/+Sudut/+Kotak). Klik tab-nya
    // HANYA kalau belum aktif — tab yang sama kalau diklik ulang justru menutup (toggle).
    const tab = this._q('.de-ribbon-tab[data-tab=rangka]');
    if (tab && !tab.classList.contains('on')) tab.click();
    this.renderSides(DenahConv.buildMembers(this.S));
    const inp = this._q(`[data-role=sisiInput][data-i="${i}"]`);
    if (!inp) return;
    // setTimeout: beri waktu panel selesai membuka (transition max-height) sebelum posisinya diukur.
    setTimeout(() => {
      const strip = inp.closest('.de-ribbon-strip');
      if (strip) strip.scrollTop += inp.getBoundingClientRect().top - strip.getBoundingClientRect().top - 8;
      inp.focus();
    }, 180);
  }
  // Panel input sisi (mudah di HP — tak perlu tap garis). Dropdown pilih sisi (F1..Fn) ->
  // kotak ketik angka muncul utk sisi terpilih; checklist "Tampilkan semua" di sampingnya
  // membuka semua kotak F1..Fn sekaligus (permintaan Elvan 27 Ags malam: dropdown gantikan
  // deretan chip F1..Fn yang selalu tampil penuh, tapi tetap sediakan jalan lihat semua).
  renderSides(mem) {
    const fr = mem.filter(m => m.jenis === 'frame');
    const panel = this._q('[data-role=sisiPanel]');
    if (this.selSisi != null && !fr[this.selSisi]) this.selSisi = null; // sisi dihapus/Undo -> lepas sorotan
    const editRow = (i, m) => `<div style="width:100%;display:flex;align-items:center;gap:6px;margin-top:6px">
        <b style="font-size:12px;color:#334155">F${i + 1}</b>
        <input type="text" inputmode="decimal" value="${m.panjang}" data-role="sisiInput" data-i="${i}" style="width:74px;box-sizing:border-box;padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px">
        <span style="font-size:11px;color:#64748b">cm</span>
      </div>`;
    const rows = this.sisiShowAll
      ? fr.map((m, i) => editRow(i, m)).join('')
      : (this.selSisi == null ? '' : editRow(this.selSisi, fr[this.selSisi]));
    panel.innerHTML =
      `<b style="width:100%;font-size:12px;color:#334155">Ukur sisi:</b>
      <div style="width:100%;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <select data-role="sisiSelect" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px">
          <option value="">Pilih sisi…</option>
          ${fr.map((m, i) => `<option value="${i}"${this.selSisi === i ? ' selected' : ''}>F${i + 1} (${m.panjang} cm)</option>`).join('')}
        </select>
        <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#334155">
          <input type="checkbox" data-role="sisiShowAll"${this.sisiShowAll ? ' checked' : ''}> Tampilkan semua
        </label>
      </div>` + rows;
    panel.querySelector('[data-role=sisiSelect]').onchange = e => {
      this.selSisi = e.target.value === '' ? null : +e.target.value;
      this.renderSides(mem);
    };
    panel.querySelector('[data-role=sisiShowAll]').onchange = e => {
      this.sisiShowAll = e.target.checked;
      this.renderSides(mem);
    };
    panel.querySelectorAll('[data-role=sisiInput]').forEach(inp => {
      inp.onchange = e => this.setSideLength(+inp.dataset.i, e.target.value);
      // Fokus = kosongkan, biar di HP langsung ketik angka baru tanpa hapus manual dulu.
      // JANGAN pakai select(): di iOS Safari itu memunculkan menu sistem Potong/Salin/Tempel
      // yang menutupi & merebut sentuhan (pelajaran combobox besi 27 Ags).
      let lama = inp.value;
      inp.onfocus = () => { lama = inp.value; inp.value = ''; };
      inp.onblur = () => { if (inp.value.trim() === '') inp.value = lama; };
    });
  }

  // Panel daftar tiang numerik (Task 2) — jalur input angka X/Y selain drag/tekan-tahan di kanvas.
  // Label T{n} SELALU dari urutan this.S.tiang, sama persis label SVG (id="tl{i}", lihat render()).
  // Commit (tambah/edit) HANYA saat change/Enter (bukan oninput per-digit) — pushUndo() satu kali,
  // lalu clampTiang(tiangFromOffset(...)) persis pola tiangMenuTambah/drag tiang yang sudah ada.
  renderTiangPanel(mem) {
    const panel = this._q('[data-role=tiangPanel]');
    panel.style.display = this.mode === 'tiang' ? '' : 'none';
    if (this.mode !== 'tiang') { panel.innerHTML = ''; return; }
    const tiangMem = mem.filter(m => m.jenis === 'tiang');
    if (this.selTiang != null && !tiangMem[this.selTiang]) this.selTiang = null; // tiang dihapus/Undo -> lepas pilihan
    const rowFor = i => {
      const m = tiangMem[i], off = DenahConv.tiangToOffset(this.S, m.geom.p);
      return `<div class="de-tiang-item" data-trow="${i}">
        <div class="de-tiang-head">
          <b style="font-size:12px">T${i + 1}</b>
          <div class="de-tiang-actions"><span class="de-mini" data-role="tFokus" data-i="${i}">Fokus</span><span class="de-mini" data-role="tHapus" data-i="${i}">Hapus</span></div>
        </div>
        <div class="de-tiang-fields">
          <label>X dari kiri<input type="text" inputmode="decimal" data-role="tx" data-i="${i}" value="${off.dx}"></label>
          <label>Y dari depan<input type="text" inputmode="decimal" data-role="ty" data-i="${i}" value="${off.dy}"></label>
          <span class="de-mini de-tiang-apply" data-role="tApply" data-i="${i}">Terapkan</span>
        </div>
      </div>`;
    };
    // Dropdown pilih T# dulu, baru baris edit muncul -- pola sama Support (Elvan 27 Ags malam:
    // daftar penuh langsung tampil susah dicari-edit di HP kalau tiang banyak). Checklist
    // "Tampilkan semua" balikin ke daftar penuh (perilaku lama).
    const picker = this.tiangShowAll ? '' : `<div style="width:100%;margin:6px 0">
      <select data-role="tPick" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px">
        <option value="">Pilih tiang…</option>
        ${tiangMem.map((m, i) => `<option value="${i}"${this.selTiang === i ? ' selected' : ''}>T${i + 1}</option>`).join('')}
      </select>
    </div>`;
    const rows = this.tiangShowAll
      ? tiangMem.map((m, i) => rowFor(i)).join('')
      : (this.selTiang == null ? '' : rowFor(this.selTiang));
    panel.innerHTML =
      `<div class="de-tiang-head">
        <b style="font-size:12px;color:#334155">Posisi Tiang (cm dari kiri-depan)</b>
        <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#334155">
          <input type="checkbox" data-role="tShowAll"${this.tiangShowAll ? ' checked' : ''}> Tampilkan semua
        </label>
      </div>` +
      picker +
      '<div data-role="tiangPanelMsg" style="font-size:11px;color:#dc2626;margin:3px 0"></div>' +
      rows +
      `<div class="de-tiang-item" style="border-bottom:0">
        <div class="de-tiang-head"><b style="font-size:12px">+ Tiang baru</b></div>
        <div class="de-tiang-fields">
          <label>X dari kiri<input type="text" inputmode="decimal" data-role="tAddX"></label>
          <label>Y dari depan<input type="text" inputmode="decimal" data-role="tAddY"></label>
          <div class="de-tiang-actions"><span class="de-mini de-tiang-apply" data-role="tTambah">Tambah</span><span class="de-mini" data-role="tPreviewBatal">Batal</span></div>
        </div>
      </div>`;

    const tPickEl = this._q('[data-role=tPick]');
    if (tPickEl) tPickEl.onchange = e => { this.selTiang = e.target.value === '' ? null : +e.target.value; this.renderTiangPanel(mem); };
    this._q('[data-role=tShowAll]').onchange = e => { this.tiangShowAll = e.target.checked; this.renderTiangPanel(mem); };

    const msgEl = () => this._q('[data-role=tiangPanelMsg]');
    const showMsg = (txt) => { const el = msgEl(); if (el) el.textContent = txt; };

    panel.querySelectorAll('[data-role=tApply]').forEach(btn => {
      const commitRow = () => {
        const i = +btn.dataset.i;
        const row = panel.querySelector(`[data-trow="${i}"]`);
        const xEl = row.querySelector('[data-role=tx]'), yEl = row.querySelector('[data-role=ty]');
        const dx = DenahConv.parseCmValue(xEl.value), dy = DenahConv.parseCmValue(yEl.value);
        if (dx == null || dy == null) {
          showMsg(`T${i + 1}: X/Y harus angka (koma/titik boleh), tidak boleh kosong.`);
          const off = DenahConv.tiangToOffset(this.S, this.S.tiang[i]);
          xEl.value = off.dx; yEl.value = off.dy;
          return;
        }
        showMsg('');
        this.pushUndo();
        this.S.tiang[i] = this.clampTiang(DenahConv.tiangFromOffset(this.S, dx, dy));
        this.render();
      };
      btn.onclick = commitRow;
      const row = panel.querySelector(`[data-trow="${btn.dataset.i}"]`);
      row.querySelectorAll('[data-role=tx],[data-role=ty]').forEach(inp => {
        inp.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); commitRow(); } };
      });
    });
    panel.querySelectorAll('[data-role=tFokus]').forEach(btn => {
      btn.onclick = () => {
        const i = +btn.dataset.i;
        this.selTiang = i;
        const tc = this.el.querySelector('#tc' + i);
        if (!tc) return;
        this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
        const prevStroke = tc.getAttribute('stroke'), prevW = tc.getAttribute('stroke-width');
        tc.setAttribute('stroke', '#facc15'); tc.setAttribute('stroke-width', '4');
        setTimeout(() => { if (tc.isConnected) { tc.setAttribute('stroke', prevStroke); tc.setAttribute('stroke-width', prevW); } }, 900);
      };
    });
    panel.querySelectorAll('[data-role=tHapus]').forEach(btn => {
      btn.onclick = () => {
        const i = +btn.dataset.i;
        this.pushUndo();
        const affected = (this.S.balok || []).filter(b => (b.a.t === i) || (b.b.t === i)).length;
        Object.assign(this.S, DenahConv.cascadeTiangRemoval(this.S, i));
        this.selTiang = null; // indeks tiang lain ikut geser -- jangan nebak, biar dipilih ulang dari dropdown
        if (affected) this.setHint(`${affected} balok terhubung dibekukan.`);
        this.render();
      };
    });
    const xAdd = this._q('[data-role=tAddX]'), yAdd = this._q('[data-role=tAddY]');
    const updatePreview = () => this.updateTiangPreview(xAdd, yAdd, showMsg);
    xAdd.oninput = updatePreview;
    yAdd.oninput = updatePreview;
    this._q('[data-role=tPreviewBatal]').onclick = () => {
      xAdd.value = ''; yAdd.value = ''; showMsg('');
      this.clearTiangPreview();
    };
    this._q('[data-role=tTambah]').onclick = () => {
      const dx = DenahConv.parseCmValue(xAdd.value), dy = DenahConv.parseCmValue(yAdd.value);
      if (dx == null || dy == null) {
        showMsg('Isi X/Y dengan angka (koma/titik boleh) sebelum menambah tiang.');
        return;
      }
      showMsg('');
      this.clearTiangPreview();
      this.pushUndo();
      this.S.tiang.push(this.clampTiang(DenahConv.tiangFromOffset(this.S, dx, dy)));
      this.selTiang = this.S.tiang.length - 1;
      this.render();
    };
  }

  // Panel Support (spec 2.4). Fase pratinjau: daftar manual lama + SATU baris ajakan kunci
  // (pintu masuk #2 kunci otomatis). Fase terkunci: daftar ceklis S1..Sn lengkap.
  renderSupportPanel(mem) {
    const panel = this._q('[data-role=supportPanel]');
    if (!panel) return;
    // Preview pindah (ditambah di bawah) digambar LANGSUNG ke svg tanpa lewat renderSupportPanel
    // lagi (ketik tiap huruf gak boleh render-ulang panel, ilang fokus input -- pola sama
    // drawSupJalurPreview form tambah). Makanya harus dibersihkan manual di sini tiap panel ini
    // BENERAN di-render-ulang (ganti pilihan/tab/dst) -- kalau tidak, preview lama nyangkut di
    // kanvas walau form-nya udah gak ada/ganti entri.
    this.drawSupJalurPreview([]);
    if (this.mode !== 'support') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    const locked = DenahConv.isLocked(this.S);

    if (!locked) {
      // ── PRATINJAU: baris ajakan + daftar manual lama (perilaku 21 Ags dipertahankan) ──
      panel.style.display = '';
      const supNum = DenahConv.numberSupportsManual(mem);
      const manualMem = mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_'));
      const rows = manualMem.map(m => {
        const i = +m.id.slice(3);
        return `<div class="de-tiang-item" data-srow="${i}">
          <div class="de-tiang-head">
            <b style="font-size:12px">S${supNum[m.id]}</b>
            <div class="de-tiang-actions"><span class="de-mini" data-role="sFokus" data-i="${i}">Fokus</span><span class="de-mini" data-role="sHapus" data-i="${i}">Hapus</span></div>
          </div>
        </div>`;
      }).join('');
      panel.innerHTML =
        `<div class="de-tiang-head" data-role="supLockInvite" style="cursor:pointer">
           <b style="font-size:12px">Support — kelola per garis</b>
           <span class="de-mini">Kunci susunan</span>
         </div>` +
        (manualMem.length ? '<b style="font-size:12px;color:#334155">Daftar Support Manual</b>' + rows : '');
      this._q('[data-role=supLockInvite]').onclick = () => this._lockNow();
      // wiring sFokus/sHapus lama VERBATIM (copy dari implementasi sekarang)
      panel.querySelectorAll('[data-role=sFokus]').forEach(btn => {
        btn.onclick = () => {
          const i = +btn.dataset.i;
          const line = this.el.querySelector('#sm' + i);
          if (!line) return;
          this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
          const prevStroke = line.getAttribute('stroke'), prevW = line.getAttribute('stroke-width');
          line.setAttribute('stroke', '#facc15'); line.setAttribute('stroke-width', '6');
          setTimeout(() => { if (line.isConnected) { line.setAttribute('stroke', prevStroke); line.setAttribute('stroke-width', prevW); } }, 900);
        };
      });
      panel.querySelectorAll('[data-role=sHapus]').forEach(btn => {
        btn.onclick = () => {
          const i = +btn.dataset.i;
          this.pushUndo();
          this._spliceSupportManual(i);
          this.render();
        };
      });
      return;
    }

    // ── TERKUNCI: header + toggle Semua + lipat/buka + baris ceklis ──
    panel.style.display = '';
    const entries = this.S.supportsLocked;
    const anyOff = entries.some(e2 => e2.aktif === false);
    const head =
      `<div class="de-tiang-head">
        <b style="font-size:12px">Support (${entries.length})</b>
        <div class="de-tiang-actions">
          <span class="de-mini" data-role="slSemua">${anyOff ? 'Aktifkan semua' : 'Nonaktifkan semua'}</span>
          <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#334155">
            <input type="checkbox" data-role="slLipat"${this.supPanelOpen ? ' checked' : ''}> Tampilkan semua
          </label>
        </div>
      </div>`;
    // Tak "Tampilkan semua": dropdown pilih S# dulu (list panjang susah dicari-edit di HP,
    // laporan Elvan 27 Ags malam), baru baris edit-nya muncul -- pakai this.selSup yang sama
    // dipakai tap-canvas/Fokus, biar 2 jalur pilih itu saling sinkron.
    const picker = this.supPanelOpen ? '' : `<div style="width:100%;margin-top:6px">
      <select data-role="slPick" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px">
        <option value="">Pilih support…</option>
        ${entries.map(e2 => `<option value="${e2.no}"${this.selSup === e2.no ? ' selected' : ''}>S${e2.no} — ${DenahConv.describeLockedSupport(this.S, e2, mem)}${e2.aktif === false ? ' (nonaktif)' : ''}</option>`).join('')}
      </select>
    </div>`;
    const list = this.supPanelOpen ? entries : entries.filter(e2 => e2.no === this.selSup);
    const rows = list.map(e2 => {
      const sel = e2.no === this.selSup;
      const desc = DenahConv.describeLockedSupport(this.S, e2, mem);
      // Arah difilter per tipe (spec 2.3): h cuma atas/bawah, v cuma kiri/kanan, manual 4 arah —
      // KECUALI manual LURUS: geser sejajar garisnya sendiri (mis. datar digeser kiri/kanan) tak
      // mengubah posisi jalur yg dipakai moveManualReclip buat re-clip, jadi no-op bisu. Cuma arah
      // TEGAK LURUS garis yang ditawarkan utk manual lurus; manual MIRING & grid tak berubah.
      const dirs = !e2.manual ? (e2.axis === 'h' ? ['atas', 'bawah'] : ['kiri', 'kanan'])
        : e2.a.y === e2.b.y ? ['atas', 'bawah']
        : e2.a.x === e2.b.x ? ['kiri', 'kanan']
        : ['atas', 'bawah', 'kiri', 'kanan'];
      const editRow = !sel ? '' :
        `<div class="de-tiang-fields" style="margin-top:4px">
          <label>Arah<select data-role="slDir">${dirs.map(d => `<option>${d}</option>`).join('')}</select></label>
          <label>cm<input type="text" inputmode="decimal" data-role="slCm"></label>
          <span class="de-mini de-tiang-apply" data-role="slApply">Terapkan</span>
        </div>
        <div class="de-tiang-actions" style="margin-top:4px">
          <span class="de-mini" data-role="slBesi">Ganti besi</span>
          ${e2.manual ? '<span class="de-mini" data-role="slHapus">Hapus</span>'
                      : '<span class="de-mini" data-role="slPecah">Pecah jadi manual</span>'}
        </div>`;
      return `<div class="de-tiang-item" data-slrow="${e2.no}" style="${sel ? 'background:#fef9c3;border-radius:6px;padding-left:4px;' : ''}${e2.aktif === false ? 'opacity:.45;' : ''}">
        <div class="de-tiang-head">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px">
            <input type="checkbox" data-role="slAktif" data-no="${e2.no}" ${e2.aktif === false ? '' : 'checked'}>
            <b>S${e2.no}</b> <span style="color:#64748b">${desc}</span>
          </label>
          <div class="de-tiang-actions"><span class="de-mini" data-role="slFokus" data-no="${e2.no}">Fokus</span></div>
        </div>${editRow}
      </div>`;
    }).join('');
    const formTambah = !this.supPanelOpen ? '' :
      `<div class="de-tiang-item" style="border-bottom:0">
        <div class="de-tiang-head"><b style="font-size:12px">+ Garis support (ketik posisi)</b></div>
        <div class="de-tiang-fields">
          <label>Arah<select data-role="sjAxis"><option value="h">datar</option><option value="v">tegak</option></select></label>
          <label><span data-role="sjLbl">cm dari depan</span><input type="text" inputmode="decimal" data-role="sjPos"></label>
          <div class="de-tiang-actions"><span class="de-mini de-tiang-apply" data-role="sjTambah">Tambah</span><span class="de-mini" data-role="sjBatal">Batal</span></div>
        </div>
      </div>`;
    panel.innerHTML = head + picker + `<div data-role="slMsg" style="font-size:11px;color:#dc2626"></div>` + rows + formTambah;

    const pickEl = this._q('[data-role=slPick]');
    if (pickEl) pickEl.onchange = e => { this.selSup = e.target.value === '' ? null : +e.target.value; this.renderSupportPanel(mem); };
    this._q('[data-role=slLipat]').onchange = e => { this.drawSupJalurPreview([]); this.supPanelOpen = e.target.checked; this.renderSupportPanel(mem); };
    this._q('[data-role=slSemua]').onclick = () => {
      this.pushUndo();
      entries.forEach(e2 => { e2.aktif = anyOff; });
      this.render();
    };
    panel.querySelectorAll('[data-role=slAktif]').forEach(cb => cb.onchange = () => {
      const e2 = entries.find(x => x.no === +cb.dataset.no);
      if (!e2) return;
      this.pushUndo(); e2.aktif = cb.checked; this.render();
    });
    panel.querySelectorAll('[data-role=slFokus]').forEach(btn => btn.onclick = () => {
      this.selSup = +btn.dataset.no;
      this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
      this.render();   // render() menyorot garis di kanvas (Task 4) + melebarkan baris ini (dropdown ikut sinkron)
    });
    // Preview pindah (permintaan Elvan 27 Ags: samain pola sama Tiang -- lihat dulu baru
    // Terapkan). moveManualReclip MURNI (tak memutasi S), aman dipanggil tiap ketik/ganti arah;
    // hasilnya cuma digambar ghost dashed cyan (drawSupJalurPreview, pola sama form tambah),
    // BUKAN di-commit -- commit sungguhan tetap cuma lewat tombol Terapkan di bawah.
    const dirEl = this._q('[data-role=slDir]'), cmEl = this._q('[data-role=slCm]');
    if (dirEl && cmEl) {
      // moveManualReclip utk entri GRID (bukan manual/miring) balikin {axis,pos} MENTAH, bukan
      // {a,b} -- itu memang bentuk penyimpanan grid yang benar (lihat buildMembers, a/b dihitung
      // ulang saat render dari axis+pos), tapi drawSupJalurPreview butuh titik a/b buat gambar.
      // Tanpa konversi ini preview CRASH DIAM-DIAM (uncaught, gak kelihatan efeknya) khusus utk
      // support bawaan/grid -- laporan Elvan 27 Ags: preview gak nongol pas mindahin support.
      // jalurSegments = fungsi sama yg dipakai form "+ Garis support" buat hal serupa.
      const toSegs = list => list.flatMap(en => (en.a && en.b) ? [{ a: en.a, b: en.b }] : DenahConv.jalurSegments(this.S, en.axis, en.pos));
      const updateMovePreview = () => {
        const e2 = entries.find(x => x.no === this.selSup);
        const cmVal = DenahConv.parseCmValue(cmEl.value);
        const r = e2 && cmVal != null ? DenahConv.moveManualReclip(this.S, e2, dirEl.value, cmVal) : null;
        this.drawSupJalurPreview(r ? toSegs(r.entries) : []);
        this._q('[data-role=slMsg]').textContent =
          (cmVal != null && r && !r.entries.length) ? 'Posisi baru di luar frame — pindah akan dibatalkan.' : '';
      };
      dirEl.onchange = updateMovePreview;
      cmEl.oninput = updateMovePreview;
    }
    const applyBtn = this._q('[data-role=slApply]');
    if (applyBtn) applyBtn.onclick = () => {
      const e2 = entries.find(x => x.no === this.selSup);
      const cmVal = DenahConv.parseCmValue(this._q('[data-role=slCm]').value);
      const r = e2 && cmVal != null ? DenahConv.moveManualReclip(this.S, e2, this._q('[data-role=slDir]').value, cmVal) : null;
      if (!r) { this._q('[data-role=slMsg]').textContent = 'Isi cm dengan angka > 0.'; return; }
      if (!r.entries.length) { this._q('[data-role=slMsg]').textContent = 'Posisi baru di luar frame — pindah dibatalkan.'; return; }
      this.pushUndo();
      const idx = entries.indexOf(e2);
      entries.splice(idx, 1, ...r.entries);
      this.S.lockSeq = r.lockSeq;
      this.S.matOverride = r.matOverride;
      this.selSup = r.entries[0].no;
      if (r.entries.length > 1) this.setHint(`Garis terbelah ${r.entries.length} potongan di frame — hapus yang tak perlu dari panel.`);
      this.render();
    };
    const besiBtn = this._q('[data-role=slBesi]');
    if (besiBtn) besiBtn.onclick = (ev) => this.openMatMenu(ev, 'SL' + this.selSup);
    const hapusBtn = this._q('[data-role=slHapus]');
    if (hapusBtn) hapusBtn.onclick = () => {
      const idx = entries.findIndex(x => x.no === this.selSup);
      if (idx < 0) return;
      this.pushUndo();
      delete this.S.matOverride['SL' + this.selSup];
      entries.splice(idx, 1);       // ID stabil: nomor lain TIDAK bergeser, tak ada remap
      this.selSup = null;
      this.render();
    };
    const sjAxis = this._q('[data-role=sjAxis]'), sjPos = this._q('[data-role=sjPos]');
    if (sjAxis && sjPos) {
      const updLbl = () => { this._q('[data-role=sjLbl]').textContent = sjAxis.value === 'h' ? 'cm dari depan' : 'cm dari kiri'; };
      const updPreview = () => {
        const cmv = DenahConv.parseCmValue(sjPos.value);
        const r = cmv != null ? DenahConv.manualEntriesFromJalur(this.S, sjAxis.value, cmv) : null;
        this.drawSupJalurPreview(r ? r.entries : []);
        this._q('[data-role=slMsg]').textContent =
          (cmv != null && r && !r.entries.length) ? 'Posisi di luar frame — tak ada garis.' : '';
      };
      sjAxis.onchange = () => { updLbl(); updPreview(); };
      sjPos.oninput = updPreview;
      this._q('[data-role=sjBatal]').onclick = () => { sjPos.value = ''; this.drawSupJalurPreview([]); this._q('[data-role=slMsg]').textContent = ''; };
      this._q('[data-role=sjTambah]').onclick = () => {
        const cmv = DenahConv.parseCmValue(sjPos.value);
        const r = cmv != null ? DenahConv.manualEntriesFromJalur(this.S, sjAxis.value, cmv) : null;
        if (!r) { this._q('[data-role=slMsg]').textContent = 'Isi posisi dengan angka > 0.'; return; }
        if (!r.entries.length) { this._q('[data-role=slMsg]').textContent = 'Posisi di luar frame — tak ada garis.'; return; }
        this.pushUndo();
        this.S.supportsLocked.push(...r.entries);
        this.S.lockSeq = r.lockSeq;
        this.selSup = r.entries[0].no;
        this.setHint(r.entries.length > 1 ? `${r.entries.length} potongan ditambah (berhenti di frame) — hapus yang tak perlu dari panel.` : '');
        this.render();
      };
    }
    const pecahBtn = this._q('[data-role=slPecah]');
    if (pecahBtn) pecahBtn.onclick = () => {
      const p = DenahConv.splitLockedGrid(this.S, this.selSup);
      if (!p) return;
      this.pushUndo();
      const { firstNo, ...state } = p; // firstNo = petunjuk UI, jangan masuk model
      Object.assign(this.S, state);
      this.selSup = firstNo;
      this.setHint('Jalur dipecah jadi potongan manual — tiap potongan kini bisa dihapus sendiri (tak ikut frame lagi).');
      this.render();
    };
  }

  // Panel daftar Balok Melintang di tab Tiang. Muncul selalu di mode tiang (form tambah harus
  // terjangkau); baris ceklis tak diperlukan (balok pasti dipakai, tak ada konsep nonaktif).
  renderBalokPanel(mem) {
    const panel = this._q('[data-role=balokPanel]');
    if (!panel) return;
    if (this.mode !== 'tiang') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    panel.style.display = '';
    const list = this.S.balok || [];
    const desc = (end) => end && end.t != null ? ((this.S.tiang || [])[end.t] ? 'T' + (end.t + 1) : 'ref patah') : 'bebas';
    const rows = !this.balokPanelOpen ? '' : list.map(b => {
      const a = DenahConv.resolveBalokEndpoint(this.S, b.a), c = DenahConv.resolveBalokEndpoint(this.S, b.b);
      const panj = (a && c) ? Math.round(Math.hypot(c.x - a.x, c.y - a.y)) + 'cm' : 'ref patah';
      const sel = this.selBalok === b.no;
      return `<div class="de-tiang-item" data-brow="${b.no}" style="${sel ? 'background:#fef9c3;border-radius:6px;padding-left:4px;' : ''}">
        <div class="de-tiang-head">
          <div><b style="font-size:12px">B${b.no}</b> <span style="color:#64748b;font-size:11px">${desc(b.a)}↔${desc(b.b)} · ${b.material} · ${panj}</span></div>
          <div class="de-tiang-actions">
            <span class="de-mini" data-role="bFokus" data-no="${b.no}">Fokus</span>
            <span class="de-mini" data-role="bBesi" data-no="${b.no}">Ganti besi</span>
            <span class="de-mini" data-role="bHapus" data-no="${b.no}">Hapus</span>
          </div>
        </div>
      </div>`;
    }).join('');
    const nTiang = (this.S.tiang || []).length;
    const opsTiang = Array.from({ length: nTiang }, (_, i) => `<option value="${i}">T${i + 1}</option>`).join('');
    const opsBesi = this.besi.map(b => `<option>${b.nama}</option>`).join('');
    const endHtml = (label, prefix) => `
      <div style="border:1px solid #e2e8f0;border-radius:6px;padding:6px;margin-top:4px">
        <label style="font-size:11px;color:#334155">${label}
          <select data-role="${prefix}Tipe" style="margin-left:6px"><option value="t">Tiang</option><option value="p">Titik bebas</option></select>
        </label>
        <div data-role="${prefix}Tiang" style="margin-top:4px"><label style="font-size:11px">Tiang<select data-role="${prefix}T" ${nTiang ? '' : 'disabled'}>${opsTiang || '<option>—</option>'}</select></label></div>
        <div data-role="${prefix}Bebas" style="display:none;margin-top:4px" class="de-tiang-fields">
          <label style="font-size:11px">X dari kiri<input type="text" inputmode="decimal" data-role="${prefix}X"></label>
          <label style="font-size:11px">Y dari depan<input type="text" inputmode="decimal" data-role="${prefix}Y"></label>
        </div>
      </div>`;
    const form = !this.balokPanelOpen ? '' :
      `<div class="de-tiang-item" style="border-bottom:0">
        <div class="de-tiang-head"><b style="font-size:12px">+ Balok melintang</b></div>
        ${endHtml('Ujung 1', 'b1')}
        ${endHtml('Ujung 2', 'b2')}
        <div style="margin-top:6px"><label style="font-size:11px">Besi<select data-role="bMat">${opsBesi}</select></label></div>
        <div class="de-tiang-actions" style="margin-top:6px">
          <span class="de-mini de-tiang-apply" data-role="bTambah">Tambah</span>
          <span class="de-mini" data-role="bBatal">Batal</span>
        </div>
      </div>`;
    panel.innerHTML =
      `<div class="de-tiang-head">
        <b style="font-size:12px">Balok Melintang (${list.length})</b>
        <span class="de-mini" data-role="bLipat">${this.balokPanelOpen ? 'Lipat' : 'Buka'}</span>
      </div>${rows}${form}<div data-role="bMsg" style="font-size:11px;color:#dc2626;margin-top:4px"></div>`;
    this._q('[data-role=bLipat]').onclick = () => { this.clearBalokPreview(); this.balokPanelOpen = !this.balokPanelOpen; this.renderBalokPanel(mem); };
    // Fokus = toggle: tap lagi di baris yang sama melepas sorotan (satu-satunya jalur lepas
    // sorot balok -- balok tak bisa ditap di kanvas kecuali mode besi, jadi tanpa toggle ini
    // halo sorot nempel selamanya).
    panel.querySelectorAll('[data-role=bFokus]').forEach(btn => btn.onclick = () => {
      const no = +btn.dataset.no;
      if (this.selBalok === no) { this.selBalok = null; this.render(); return; }
      this.selBalok = no; this.balokPanelOpen = true;
      this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
      this.render();
    });
    panel.querySelectorAll('[data-role=bHapus]').forEach(btn => btn.onclick = () => {
      const no = +btn.dataset.no;
      this.pushUndo();
      this.S.balok = (this.S.balok || []).filter(x => x.no !== no);
      delete this.S.matOverride['B' + no];
      if (this.selBalok === no) this.selBalok = null;
      this.render();
    });
    panel.querySelectorAll('[data-role=bBesi]').forEach(btn => btn.onclick = (ev) => this.openMatMenu(ev, 'B' + btn.dataset.no));
    if (!this.balokPanelOpen) return;
    this._q('[data-role=bMat]').value = this.S.matDefault.balok;
    const updatePreview = () => {
      const a = this._readBalokEnd('b1'), b = this._readBalokEnd('b2');
      const pa = a && DenahConv.resolveBalokEndpoint(this.S, a), pb = b && DenahConv.resolveBalokEndpoint(this.S, b);
      this.drawBalokPreview(pa && pb ? { a: pa, b: pb } : null);
    };
    ['b1', 'b2'].forEach(prefix => {
      const tipeSel = this._q(`[data-role=${prefix}Tipe]`);
      // Sync tampilan (Tiang/Titik bebas) dipisah dari updatePreview -- setup awal cuma sync
      // tampilan, TANPA preview, biar panel baru dibuka gak langsung gambar garis panjang-nol
      // (default kedua ujung sama-sama T1).
      const syncDisplay = () => {
        const isTiang = tipeSel.value === 't';
        this._q(`[data-role=${prefix}Tiang]`).style.display = isTiang ? '' : 'none';
        this._q(`[data-role=${prefix}Bebas]`).style.display = isTiang ? 'none' : '';
      };
      tipeSel.onchange = () => { syncDisplay(); updatePreview(); };
      this._q(`[data-role=${prefix}T]`).onchange = updatePreview;
      this._q(`[data-role=${prefix}X]`).oninput = updatePreview;
      this._q(`[data-role=${prefix}Y]`).oninput = updatePreview;
      syncDisplay();
    });
    const bTambah = this._q('[data-role=bTambah]');
    if (bTambah) bTambah.onclick = () => {
      const a = this._readBalokEnd('b1'), b = this._readBalokEnd('b2');
      const mat = this._q('[data-role=bMat]').value;
      if (!a || !b) { this._q('[data-role=bMsg]').textContent = 'Lengkapi kedua ujung (pilih tiang atau isi X/Y titik bebas).'; return; }
      const pa = DenahConv.resolveBalokEndpoint(this.S, a), pb = DenahConv.resolveBalokEndpoint(this.S, b);
      if (!pa || !pb || (pa.x === pb.x && pa.y === pb.y)) { this._q('[data-role=bMsg]').textContent = 'Dua ujung tak boleh sama.'; return; }
      this.pushUndo();
      this.S.balok = this.S.balok || [];
      const no = Math.max(this.S.balokSeq || 1, ...this.S.balok.map(x => x.no + 1), 1);
      this.S.balok.push({ no, a, b, material: mat });
      this.S.balokSeq = no + 1;
      this.selBalok = no;
      this.setHint(`Balok B${no} ditambah.`);
      this.render();
    };
    const bBatal = this._q('[data-role=bBatal]');
    if (bBatal) bBatal.onclick = () => { this.clearBalokPreview(); this._q('[data-role=bMsg]').textContent = ''; };
  }

  // Baca form ujung (prefix b1/b2) → {t:i} | {p:{x,y}} | null (belum lengkap). Titik bebas
  // diketik "X dari kiri / Y dari depan" (SAMA konvensi panel Tiang) -- HARUS lewat
  // tiangFromOffset, jangan dipakai mentah sbg koordinat model (bug nyata: dulu Y=50 jatuh
  // ~50cm dari BELAKANG/atas, bukan 50cm dari depan seperti labelnya -- laporan Elvan 24 Ags).
  _readBalokEnd(prefix) {
    const tipe = this._q(`[data-role=${prefix}Tipe]`).value;
    if (tipe === 't') {
      const sel = this._q(`[data-role=${prefix}T]`);
      const t = +sel.value;
      return Number.isInteger(t) && (this.S.tiang || [])[t] ? { t } : null;
    }
    const dx = DenahConv.parseCmValue(this._q(`[data-role=${prefix}X]`).value);
    const dy = DenahConv.parseCmValue(this._q(`[data-role=${prefix}Y]`).value);
    return (dx != null && dy != null) ? { p: DenahConv.tiangFromOffset(this.S, dx, dy) } : null;
  }

  // Ghost preview garis Balok (pola sama drawSupJalurPreview): dashed ungu.
  drawBalokPreview(seg) {
    const svg = this._q('.de-canvas svg');
    if (!svg) return;
    const old = svg.querySelector('[data-balok-preview]');
    if (old) old.remove();
    if (!seg) return;
    const NS = 'http://www.w3.org/2000/svg';
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-balok-preview', '1');
    g.setAttribute('style', 'pointer-events:none');
    const ln = document.createElementNS(NS, 'line');
    [['x1', this.PAD + seg.a.x * this.SC], ['y1', this.PAD + seg.a.y * this.SC],
     ['x2', this.PAD + seg.b.x * this.SC], ['y2', this.PAD + seg.b.y * this.SC]].forEach(([k, v]) => ln.setAttribute(k, v));
    ln.setAttribute('stroke', '#a855f7'); ln.setAttribute('stroke-width', '4'); ln.setAttribute('stroke-dasharray', '8,5');
    g.appendChild(ln);
    svg.appendChild(g);
  }
  clearBalokPreview() { this.drawBalokPreview(null); }

  // Ghost preview garis support numerik (pola sama drawTiangPreview): dashed cyan per potongan.
  // entries [] / tombol Batal = hapus preview.
  drawSupJalurPreview(entries) {
    const svg = this._q('.de-canvas svg');
    if (!svg) return;
    const old = svg.querySelector('[data-sup-preview]');
    if (old) old.remove();
    if (!entries || !entries.length) return;
    const NS = 'http://www.w3.org/2000/svg';
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-sup-preview', '1');
    g.setAttribute('style', 'pointer-events:none');
    entries.forEach(e => {
      const ln = document.createElementNS(NS, 'line');
      [['x1', this.PAD + e.a.x * this.SC], ['y1', this.PAD + e.a.y * this.SC],
       ['x2', this.PAD + e.b.x * this.SC], ['y2', this.PAD + e.b.y * this.SC]].forEach(([k, v]) => ln.setAttribute(k, v));
      ln.setAttribute('stroke', '#22d3ee'); ln.setAttribute('stroke-width', '3'); ln.setAttribute('stroke-dasharray', '6,4');
      g.appendChild(ln);
    });
    svg.appendChild(g);
  }

  // Preview Task 3: gambar langsung ke SVG tanpa render() agar state, Undo dan onChange tak tersentuh.
  updateTiangPreview(xEl, yEl, showMsg) {
    const dx = DenahConv.parseCmValue(xEl.value), dy = DenahConv.parseCmValue(yEl.value);
    if (dx == null || dy == null) {
      this.clearTiangPreview();
      if (xEl.value.trim() || yEl.value.trim()) showMsg('Isi X dan Y dengan angka untuk melihat preview.');
      else showMsg('');
      return;
    }
    const inset = 8;
    const minC = -(this.PAD / this.SC) + inset;
    this.tiangPreview = {
      ...DenahConv.tiangPreviewState(this.S, dx, dy, {
        x0: minC, y0: minC,
        x1: (this.domW || 400) - inset,
        y1: (this.domH || 400) - inset,
      }),
      dx, dy,
    };
    showMsg(this.tiangPreview.clamped ? 'Posisi di luar area gambar; preview ditampilkan di batas terdekat.' : 'Preview aktif — tekan Tambah untuk menyimpan.');
    this.drawTiangPreview();
  }

  clearTiangPreview() {
    this.tiangPreview = null;
    const old = this.el.querySelector('[data-tiang-preview]');
    if (old) old.remove();
  }

  drawTiangPreview() {
    const svg = this._q('.de-canvas svg');
    if (!svg) return;
    const old = svg.querySelector('[data-tiang-preview]');
    if (old) old.remove();
    if (!this.tiangPreview) return;
    const NS = 'http://www.w3.org/2000/svg';
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-tiang-preview', '1');
    g.setAttribute('style', 'pointer-events:none');
    const px = this.PAD + this.tiangPreview.point.x * this.SC;
    const py = this.PAD + this.tiangPreview.point.y * this.SC;
    const line = (x1, y1, x2, y2) => {
      const el = document.createElementNS(NS, 'line');
      [['x1', x1], ['y1', y1], ['x2', x2], ['y2', y2]].forEach(([k, v]) => el.setAttribute(k, v));
      el.setAttribute('stroke', '#22d3ee'); el.setAttribute('stroke-width', '2'); el.setAttribute('stroke-dasharray', '4,3');
      return el;
    };
    g.appendChild(line(px - 14, py, px + 14, py));
    g.appendChild(line(px, py - 14, px, py + 14));
    const circle = document.createElementNS(NS, 'circle');
    circle.setAttribute('cx', px); circle.setAttribute('cy', py); circle.setAttribute('r', '8');
    circle.setAttribute('fill', 'rgba(34,211,238,.22)'); circle.setAttribute('stroke', '#22d3ee'); circle.setAttribute('stroke-width', '2');
    g.appendChild(circle);
    const label = document.createElementNS(NS, 'text');
    const viewBox = (svg.getAttribute('viewBox') || '').trim().split(/\s+/).map(Number);
    const svgW = viewBox[2] || 0;
    const labelAtRight = svgW > 0 && px > svgW - 150;
    label.setAttribute('x', labelAtRight ? px - 12 : px + 12);
    label.setAttribute('text-anchor', labelAtRight ? 'end' : 'start');
    label.setAttribute('y', Math.max(14, py - 12));
    label.setAttribute('fill', '#67e8f9'); label.setAttribute('font-size', '12'); label.setAttribute('font-weight', '700');
    label.setAttribute('paint-order', 'stroke'); label.setAttribute('stroke', '#0f2740'); label.setAttribute('stroke-width', '3');
    label.textContent = `Preview X${this.tiangPreview.dx} Y${this.tiangPreview.dy}`;
    g.appendChild(label);
    svg.appendChild(g);
  }

  openMatMenu(evt, id) {
    this.menuId = id;
    const cur = this.S.matOverride[id] || '';
    const pick = this._q('[data-role=matPick]');
    pick.value = cur || (id[0] === 'F' ? this.S.matDefault.frame : id[0] === 'T' ? this.S.matDefault.tiang : id[0] === 'B' ? this.S.matDefault.balok : this.S.matDefault.support);
    // Label biar jelas batang mana yang barusan diketuk (F3/S5/T2 — nomor SAMA seperti yang
    // tertulis di kanvas, bukan id internal), biar user yakin ini batang yang benar sebelum ganti.
    const mem = this.getMembers();
    const jenisNama = { frame: 'Frame', support: 'Support', tiang: 'Tiang', balok: 'Balok' };
    const m = mem.find(x => x.id === id);
    let label = id;
    if (!m && id.startsWith('SL')) {
      // Entri nonaktif/di-luar-frame gak hasilkan member (tak ada potongan tergambar) --
      // tetap kasih label yg masuk akal ("Support S7") ketimbang id mentah "SL7".
      label = 'Support S' + id.slice(2);
    } else if (m) {
      // frame/tiang: m.nama sudah "F3"/"T2". support MANUAL: nomor dari DenahConv.numberSupportsManual
      // (SATU sumber sama dgn label kanvas & panel, lihat Task 1/3). Support GRID: tak dinomori
      // lagi (ID-nya tak stabil lintas render), cukup ditandai "grid" biar user tahu ini bukan
      // support yang bisa di-Fokus dari panel.
      let code;
      if (m.jenis === 'support') {
        code = id.startsWith('SL') ? 'S' + id.slice(2)
             : id.startsWith('Sm_') ? 'S' + DenahConv.numberSupportsManual(mem)[id] : 'grid';
      } else if (m.jenis === 'balok') {
        code = m.nama;   // "B3"
      } else {
        code = m.nama;
      }
      // Fase terkunci: 1 id 'SL{no}' bisa terbelah beberapa potongan (coakan) -- panjang yang
      // ditampilkan TOTAL semua potongan, bukan cuma potongan yang barusan diketuk.
      const totLen = mem.filter(x => x.id === id).reduce((a2, x) => a2 + x.panjang, 0);
      label = `${jenisNama[m.jenis]} ${code} · ${id.startsWith('SL') ? totLen : m.panjang}cm`;
    }
    this._q('[data-role=matMenuLabel]').textContent = label;
    const menu = this._q('[data-role=matMenu]');
    // Tampilkan dulu baru ukur (offsetWidth/Height=0 kalau masih display:none) — kalau titik
    // ketukan dekat tepi layar (sering kejadian pas zoom-in), geser ke kiri/atas biar popup
    // tak kepotong keluar viewport.
    menu.style.left = '0px'; menu.style.top = '0px'; menu.style.display = 'block';
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    let left = evt.clientX + 6, top = evt.clientY + 6;
    if (left + mw > window.innerWidth) left = Math.max(6, evt.clientX - mw - 6);
    if (top + mh > window.innerHeight) top = Math.max(6, evt.clientY - mh - 6);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }

  // Menu tekan-tahan tiang — dipicu dari bindSvg() saat tekan-tahan TANPA gerak jari (kalau gerak,
  // jadi drag-pindah biasa / gerak dibatalkan, bukan menu). Dua konteks pakai 1 popup yang sama:
  // tiang yang SUDAH ADA (Ganti Besi/Hapus) via openTiangMenu(), atau tempat KOSONG (Tambah) via
  // openTiangAddMenu() — saling eksklusif, tombol yang tak relevan disembunyikan.
  openTiangMenu(evt, i) {
    this._tiangMenuIdx = i;
    this._tiangAddPt = null;
    this._q('[data-role=tiangMenuTambah]').style.display = 'none';
    this._q('[data-role=tiangMenuGanti]').style.display = '';
    this._q('[data-role=tiangMenuHapus]').style.display = '';
    this._showTiangMenuAt(evt);
  }
  openTiangAddMenu(evt, x, y) {
    this._tiangMenuIdx = null;
    this._tiangAddPt = { x, y };
    this._q('[data-role=tiangMenuTambah]').style.display = '';
    this._q('[data-role=tiangMenuGanti]').style.display = 'none';
    this._q('[data-role=tiangMenuHapus]').style.display = 'none';
    this._showTiangMenuAt(evt);
  }
  // Posisi clamp-ke-viewport sama persis openMatMenu() di atas.
  _showTiangMenuAt(evt) {
    const menu = this._q('[data-role=tiangMenu]');
    menu.style.left = '0px'; menu.style.top = '0px'; menu.classList.add('show');
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    let left = evt.clientX + 6, top = evt.clientY + 6;
    if (left + mw > window.innerWidth) left = Math.max(6, evt.clientX - mw - 6);
    if (top + mh > window.innerHeight) top = Math.max(6, evt.clientY - mh - 6);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }
  _closeTiangMenu() {
    this._q('[data-role=tiangMenu]').classList.remove('show');
    this._tiangMenuIdx = null;
    this._tiangAddPt = null;
  }

  // Menu tekan-tahan Support — SATU popup dipakai 2 konteks: grid otomatis (Kecualikan + Ganti
  // Material) dan manual/titik-ujungnya (Hapus + Ganti Material) — saling eksklusif, tombol tak
  // relevan disembunyikan. Pola identik openTiangMenu di atas.
  openSupportMenu(evt, id) {
    this._supportMenuId = id;
    const isGrid = id.startsWith('Sh_') || id.startsWith('Sv_');
    this._q('[data-role=supportMenuKecualikan]').style.display = isGrid ? '' : 'none';
    this._q('[data-role=supportMenuHapus]').style.display = isGrid ? 'none' : '';
    this._showSupportMenuAt(evt);
  }
  // Posisi clamp-ke-viewport sama persis openMatMenu()/_showTiangMenuAt() di atas.
  _showSupportMenuAt(evt) {
    const menu = this._q('[data-role=supportMenu]');
    menu.style.left = '0px'; menu.style.top = '0px'; menu.classList.add('show');
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    let left = evt.clientX + 6, top = evt.clientY + 6;
    if (left + mw > window.innerWidth) left = Math.max(6, evt.clientX - mw - 6);
    if (top + mh > window.innerHeight) top = Math.max(6, evt.clientY - mh - 6);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }
  _closeSupportMenu() {
    this._q('[data-role=supportMenu]').classList.remove('show');
    this._supportMenuId = null;
  }
  // Hapus 1 entri supportsManual DAN remap key matOverride yang bergeser -- splice tanpa ini
  // bikin override "Sm_{j}" utk j>i diam-diam nempel ke support LAIN (yang geser turun 1 index
  // ke slot itu), salah material terbawa ke perhitungan biaya. SATU titik dipakai kedua tombol
  // Hapus (panel & menu tekan-tahan) biar tak ada jalur kedua yang lupa remap ini.
  _spliceSupportManual(i) {
    this.S.supportsManual.splice(i, 1);
    const mo = this.S.matOverride, out = {};
    Object.keys(mo).forEach(k => {
      if (!k.startsWith('Sm_')) { out[k] = mo[k]; return; }
      const j = +k.slice(3);
      if (j === i) return;
      out[j > i ? 'Sm_' + (j - 1) : k] = mo[k];
    });
    this.S.matOverride = out;
  }

  // Panel input span/menjorok + Terapkan/Batal — cuma tampil selagi armed === 'addBox'.
  renderBoxPanel() {
    const panel = this._q('[data-role=boxPanel]');
    if (this.armed !== 'addBox') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    const bp = this.boxPreview;
    panel.style.display = 'flex';
    panel.innerHTML =
      '<label style="font-size:12px;display:flex;flex-direction:column;gap:3px">Panjang di sisi ini (cm)' +
      '<input type="number" data-role="inBoxSpan" value="' + bp.span + '" min="1" step="10"></label>' +
      '<label style="font-size:12px;display:flex;flex-direction:column;gap:3px">Menjorok (cm)' +
      '<input type="number" data-role="inBoxDepth" value="' + bp.depthMag + '" min="1" step="10"></label>' +
      (bp.sisiIdx != null ? '<span class="de-mini" data-role="btnBoxApply">Terapkan</span>' : '') +
      '<span class="de-mini" data-role="btnBoxCancel">Batal</span>';
    const refreshBoxPreview = () => {
      if (bp.sisiIdx == null) return;
      const pv = this.computeBoxPreviewVerts();
      const poly = this.el.querySelector('[data-boxprev]');
      if (poly) poly.setAttribute('points', [pv.p1, pv.p4, pv.p3, pv.p2].map(p => `${this.PAD + p.x * this.SC},${this.PAD + p.y * this.SC}`).join(' '));
    };
    this._q('[data-role=inBoxSpan]').oninput = e => { bp.span = Math.max(1, +e.target.value) || bp.span; refreshBoxPreview(); };
    this._q('[data-role=inBoxDepth]').oninput = e => { bp.depthMag = Math.max(1, +e.target.value) || bp.depthMag; refreshBoxPreview(); };
    this._q('[data-role=btnBoxCancel]').onclick = () => { this.armed = null; this.boxPreview = null; this.setHint(); this.render(); };
    const apply = this._q('[data-role=btnBoxApply]');
    if (apply) apply.onclick = () => this.applyBoxPreview();
  }

  // Titik kotak-preview sekarang (cm), dari sisi+offset+span+depth yang sedang diedit/digeser.
  computeBoxPreviewVerts() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey) || 1;
    const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const p1 = { x: a.x + ux * off, y: a.y + uy * off };
    const p2 = { x: a.x + ux * (off + bp.span), y: a.y + uy * (off + bp.span) };
    const d = bp.depthMag * bp.depthSign;
    const p4 = { x: p1.x + nx * d, y: p1.y + ny * d };
    const p3 = { x: p2.x + nx * d, y: p2.y + ny * d };
    return { p1, p2, p3, p4 };
  }

  // Terapkan: panggil combineBoxWithMeta (Task 1 + fix titik segaris di sudut); kalau valid
  // ganti S.verts, kalau tidak kasih hint & tetap di preview.
  applyBoxPreview() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const len = Math.hypot(b.x - a.x, b.y - a.y);
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const result = DenahConv.combineBoxWithMeta(this.S.verts, bp.sisiIdx, off, bp.span, bp.depthMag * bp.depthSign);
    if (!result) { this.setHint('Kotak tidak valid di posisi ini — geser lagi atau kecilkan ukurannya.'); return; }
    this.pushUndo();
    // boxIdx/reindex dari combineBoxWithMeta: kotak nempel pas di sudut existing bisa bikin
    // sudut lama itu segaris (dibuang), jadi index-nya bukan cuma soal geser +count lagi.
    this.S.combinedBoxes = DenahConv.reindexBoxes(this.S.combinedBoxes, result.reindex);
    this.S.combinedBoxes.push({ verts: result.boxIdx });
    this.S.verts = result.verts;
    this.armed = null; this.boxPreview = null;
    this.setHint();
    this.syncLP();
    this.render();
  }

  // ---- Render SVG ----
  render() {
    // Render berarti state/kanvas berubah; draft visual lama tidak boleh tersisa atau tersimpan.
    this.tiangPreview = null;
    // Titik sinkron TUNGGAL tanda visual +Sudut/-Sudut ke this.armed. Ditaruh di sini biar SEMUA
    // jalur yg mengubah armed lalu render() (undo/redo, reset, Tambah Kotak, Ganti besi, pindah
    // tab) otomatis konsisten -- tak perlu nambal tiap handler satu-satu (rawan kelewat).
    this._syncVertBtns();
    // Toggle move cuma tampil di tab Support (tahap ini). Sorotan basi (entri sudah dihapus /
    // Undo balikin ke pratinjau) dilepas di sini — SATU titik validasi utk semua jalur mutasi.
    // Validasi selSup + reset moveOn HARUS jalan duluan, baru sinkron visual tombol move di
    // bawahnya -- kalau kebalik, toggle 'on' sempat kepasang pakai nilai moveOn lama (basi).
    if (this.selSup != null && !(DenahConv.isLocked(this.S) && this.S.supportsLocked.some(e => e.no === this.selSup))) this.selSup = null;
    // Sorotan Balok basi (dihapus / Undo balikin ke snapshot tanpa entri itu) -- pola sama selSup.
    if (this.selBalok != null && !(this.S.balok || []).some(b => b.no === this.selBalok)) this.selBalok = null;
    // Undo bisa balikin kunci -> pratinjau (supportsLocked=null) sementara moveOn tetap nyala dari
    // sebelumnya -- tombol jadi "on" padahal tak ada entri terkunci. Reset di sini (titik validasi
    // yang sama), bukan di undo()/redo(), biar semua jalur balik-ke-pratinjau otomatis konsisten.
    if (!DenahConv.isLocked(this.S)) this.moveOn = false;
    const mv = this._q('[data-role=btnMove]');
    if (mv) { mv.style.display = this.mode === 'support' ? '' : 'none'; mv.classList.toggle('on', this.moveOn); }
    this._syncSupportRows();
    const S = this.S;
    const mem = DenahConv.buildMembers(S);
    const cmap = colorMap(mem);
    // bbox HARUS ikut tiang (bukan cuma S.verts) -- tiang boleh digeser keluar bentuk utama (mis.
    // tiang pinggir kanopi yg menjorok), kalau kanvas cuma dihitung dari verts, tiang di luar situ
    // kepotong dari area gambar (svg overflow:hidden default) -> gak kegambar penuh, susah/tak bisa
    // disentuh lagi. Bug nyata dari Elvan: tiang digeser ke luar kotak utama jadi tak ketemu lagi.
    const bb = bbox([...S.verts, ...S.tiang]);
    const inLraw = +(this._q('[data-role=inL]').value) || 0;
    const inPraw = +(this._q('[data-role=inP]').value) || 0;
    const domW = Math.max(bb.x1, inLraw) * 1.12 + 20, domH = Math.max(bb.y1, inPraw) * 1.12 + 20;
    this.SC = Math.min(560 / domW, 400 / domH); if (!isFinite(this.SC) || this.SC <= 0) this.SC = 0.5;
    this.domW = domW; this.domH = domH; // dipakai clampTiang() biar tiang gak bisa digeser keluar kanvas
    const PAD = this.PAD;
    const W = domW * this.SC + PAD * 2, H = domH * this.SC + PAD * 2;
    const X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    // Grid latar jangan lebih rapat dari ~8px biar tak jadi bidang solid saat snap kecil (mis. 1cm).
    // Snap (S.grid) tetap presisi apa adanya; ini murni kerapatan garis latar (visual).
    let visCm = S.grid > 0 ? S.grid : 20; while (visCm * this.SC < 8 && visCm < 1000) visCm *= 2;
    const gpx = visCm * this.SC;
    let s = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">`;
    const gid = 'grid-' + this.uid;
    s += `<defs><pattern id="${gid}" width="${gpx}" height="${gpx}" patternUnits="userSpaceOnUse" x="${PAD}" y="${PAD}"><path d="M ${gpx} 0 L 0 0 0 ${gpx}" fill="none" stroke="#1e3a5f" stroke-width="0.5"/></pattern></defs>`;
    s += `<rect x="0" y="0" width="${W}" height="${H}" fill="#0f2740"/>`;
    s += `<rect x="${PAD}" y="${PAD}" width="${domW * this.SC}" height="${domH * this.SC}" fill="url(#${gid})"/>`;
    // support (grup — diredupkan saat seret sudut). Support manual dapat titik ujung yang bisa digeser.
    s += '<g id="supLayer">';
    const supNum = DenahConv.numberSupportsManual(mem);
    const locked = DenahConv.isLocked(S);
    const segCount = {};
    mem.filter(m => m.jenis === 'support').forEach(m => { const c = cmap[m.material]; const manual = m.id.startsWith('Sm_');
      const mx = (m.geom.a.x + m.geom.b.x) / 2, my = (m.geom.a.y + m.geom.b.y) / 2;
      // Fase terkunci: id member 'SL{no}'. Garis tersorot (this.selSup) menyala kuning-tebal
      // (spec 2.3 "sorot dulu, aksi kemudian" — sinkron dua arah dgn baris panel).
      const lockedEntry = locked && m.id.startsWith('SL');
      const selected = lockedEntry && +m.id.slice(2) === this.selSup;
      const stroke = selected ? '#facc15' : c;
      const sw = selected ? 5 : (manual ? 3 : 2);
      // garis tampak (tanpa event) + garis transparan lebar (target ketuk) + label. id garis tampak
      // SELALU ada (bukan cuma manual seperti dulu) -- grid support butuh id stabil-per-render
      // "sg_{id}" biar drag-preview (Task 3) bisa update atribut x1/y1/x2/y2-nya langsung tanpa
      // render ulang, sama pola dgn manual (sm{i}).
      const segIdx = segCount[m.id] = (segCount[m.id] || 0) + 1;
      const lineId = lockedEntry ? 'slg' + m.id.slice(2) + '_' + (segIdx - 1) : (manual ? 'sm' + m.id.slice(3) : 'sg_' + m.id);
      s += `<line id="${lineId}" x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="${stroke}" stroke-width="${sw}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="transparent" stroke-width="14" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      // Label S{n} KHUSUS manual/terkunci (nomor independen dari grid, lihat DenahConv.numberSupportsManual).
      // Grid support (fase pratinjau) tidak diberi nomor lagi (id-nya tak stabil lintas render, lihat Task 1).
      const fullLabel = lockedEntry ? `S${m.id.slice(2)} · ${m.panjang}` : (manual ? `S${supNum[m.id]} · ${m.panjang}` : `${m.panjang}`);
      const shortLabel = lockedEntry ? `S${m.id.slice(2)}` : (manual ? `S${supNum[m.id]}` : '');
      const label = DenahConv.supportLabelText(fullLabel, shortLabel, m.panjang * this.SC, selected);
      // Support horizontal & vertikal yang berpotongan deket tengah kotak dulu labelnya numpuk
      // (dua-duanya persis di titik tengah garis masing-masing). Vertikal digeser ke titik 30%
      // (dari ujung "atas"/awal garis, bukan lagi persis di tengah) biar gak ketiban label
      // horizontal yang selalu di titik tengah garisnya (kasus S5×S11, aturan Elvan 24 Ags).
      // Support horizontal: label mendatar sedikit di atas garis (spt sekarang). Support vertikal:
      // teks diputar -90 (baca bawah->atas) biar ikut arah garis, digeser sedikit ke sisi garis.
      const isHoriz = Math.abs(m.geom.a.y - m.geom.b.y) < Math.abs(m.geom.a.x - m.geom.b.x);
      const t3 = 0.3;
      const px = isHoriz ? mx : m.geom.a.x + (m.geom.b.x - m.geom.a.x) * t3;
      const py = isHoriz ? my : m.geom.a.y + (m.geom.b.y - m.geom.a.y) * t3;
      const lx = X(px), ly = Y(py);
      const rot = isHoriz ? '' : ` dy="-6" transform="rotate(-90 ${lx} ${ly})"`;
      const ty = isHoriz ? ly - 4 : ly;
      if (label) s += `<text ${manual ? `id="smlbl${m.id.slice(3)}"` : ''} x="${lx}" y="${ty}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3"${rot}>${label}</text>`; });
    if (this.mode === 'support' && !locked) mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_')).forEach(m => { const i = m.id.slice(3);
      ['a', 'b'].forEach(end => { const p = m.geom[end], cx = X(p.x), cy = Y(p.y);
        s += `<circle cx="${cx}" cy="${cy}" r="22" fill="transparent" data-sm="${i}" data-end="${end}" class="smhit" style="cursor:grab"/>`;
        s += `<circle id="smh${i}${end}" cx="${cx}" cy="${cy}" r="4" fill="#0f2740" stroke="#38bdf8" stroke-width="2.5" style="pointer-events:none"/>`; }); });
    else if (this.mode === 'support' && locked && this.moveOn && this.selSup != null) {
      const e = S.supportsLocked.find(x => x.no === this.selSup && x.manual && x.aktif !== false);
      if (e) ['a', 'b'].forEach(end => { const p = e[end], cx = X(p.x), cy = Y(p.y);
        s += `<circle cx="${cx}" cy="${cy}" r="22" fill="transparent" data-slend="${e.no}" data-end="${end}" style="cursor:grab"/>`;
        s += `<circle id="slh${e.no}${end}" cx="${cx}" cy="${cy}" r="4" fill="#0f2740" stroke="#facc15" stroke-width="2.5" style="pointer-events:none"/>`; });
    }
    s += '</g>';
    // frame (tebal) + label sisi — tiap sisi id fl{i}/fll{i} biar bisa diupdate saat seret
    mem.filter(m => m.jenis === 'frame').forEach((m, i) => { const c = cmap[m.material]; const a = m.geom.a, b = m.geom.b;
      // Garis tampak frame TIDAK ikut hit-testing (tooltip pindah ke pita sentuh), dan pita
      // sentuhnya digating ke mode yang MEMANG mengonsumsi tap frame (bentuk: ketik sisi/+Sudut/
      // kotak; besi: ganti material). Tanpa gating ini, ujung support manual hasil ketik-posisi
      // (yang selalu jatuh PERSIS di garis frame) ketutup pita frame -> gak bisa ditarik di mode
      // support (laporan Elvan 25 Ags, Tes 1 no.4). Pola sama gating balok/vhit.
      const fpe = (this.mode === 'bentuk' || this.mode === 'besi') ? 'auto' : 'none';
      s += `<line id="fl${i}" x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="${c}" stroke-width="5" stroke-linecap="round" style="pointer-events:none"/>`;
      s += `<line x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="transparent" stroke-width="16" data-id="${m.id}" class="hit" style="cursor:pointer;pointer-events:${fpe}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      const fla = this._frameLabelAttrs(a, b);
      s += `<text id="fll${i}" x="${fla.lx}" y="${fla.ly}" fill="#e2e8f0" font-size="13" text-anchor="middle" dominant-baseline="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3" transform="rotate(${fla.ang} ${fla.lx} ${fla.ly})">F${i + 1} · ${m.panjang}</text>`; });
    // kotak-support (Gabungan Kotak): hit-area transparan per kotak buat drag-kotak-utuh. Dirender
    // SEBELUM titik sudut (di bawah ini) supaya tap TEPAT di titik sudut tetap prioritas drag-sudut
    // biasa (SVG: elemen belakangan di markup ada di atas utk hit-testing).
    (S.combinedBoxes || []).forEach((bx, k) => {
      const pts = bx.verts.map(i => S.verts[i]).filter(Boolean);
      if (pts.length !== bx.verts.length) return; // index rusak (harusnya sudah tersaring reindex Task 5)
      s += `<polygon points="${pts.map(p => `${X(p.x)},${Y(p.y)}`).join(' ')}" fill="transparent" data-boxgroup="${k}" style="cursor:grab;pointer-events:${this.mode === 'bentuk' ? 'auto' : 'none'}"/>`;
    });
    // vertex: hit-area besar transparan (mudah ditekan di HP) + bulatan tampak (tak makan event).
    // Hit-area cuma aktif mode Bentuk (sama pola kotak-support di atas) -- sebelumnya tak digating,
    // jadi walau tak dipakai logika hit-test mode Tiang (itu murni jarak-cm, bukan e.target), tetap
    // salah/inkonsisten kalau area sentuh gede nongol di mode lain.
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle id="vhit${i}" cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab;pointer-events:${this.mode === 'bentuk' ? 'auto' : 'none'}"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="5" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
    // tiang -- SENGAJA dirender SETELAH titik sudut (di atas), bukan sebelum. Bug nyata dari Elvan:
    // tiang yang ditaruh pas di titik sudut kotak jadi ketutup bulatan putih titik sudut (elemen
    // belakangan menang tampil di atas), keliatan cuma 1 titik & susah dibedain/ditemuin lagi.
    mem.filter(m => m.jenis === 'tiang').forEach((m, i) => { const c = cmap[m.material]; const p = m.geom.p;
      s += `<circle id="tc${i}" cx="${X(p.x)}" cy="${Y(p.y)}" r="6" fill="${c}" stroke="#0f2740" stroke-width="1.5" data-id="${m.id}" class="hit"><title>Tiang ${m.material} • ${m.panjang}cm</title></circle>`;
      s += `<text id="tl${i}" x="${X(p.x) + 10}" y="${Y(p.y) + 5}" fill="#fbbf24" font-size="13" font-weight="700" paint-order="stroke" stroke="#0f2740" stroke-width="4">T${i + 1}</text>`; });
    // Balok melintang (Portal/Bracing). Layer di atas support, di bawah frame — visual utama.
    // Ref tiang patah? `buildMembers` sudah menyaringnya (member tak dibuat); di sini pakai `mem` filter.
    const bSel = this.selBalok;
    mem.filter(m => m.jenis === 'balok').forEach(m => {
      const c = cmap[m.material];
      const no = +m.id.slice(1);
      const selected = bSel === no;
      const stroke = c || '#a855f7';
      const ax = X(m.geom.a.x), ay = Y(m.geom.a.y), bx = X(m.geom.b.x), by = Y(m.geom.b.y);
      // Tersorot = HALO kuning DI BAWAH garis, bukan mengganti warnanya -- garis balok selalu
      // tampil warna besi aslinya. Dulu tersorot = seluruh garis jadi kuning; karena sorotan balok
      // awet (gak ada tap-lepas di kanvas spt support), ganti besi kelihatan "warnanya gak
      // berubah" padahal cuma ketutup highlight (laporan Elvan 25 Ags).
      if (selected) s += `<line x1="${ax}" y1="${ay}" x2="${bx}" y2="${by}" stroke="#facc15" stroke-width="12" stroke-linecap="round" style="pointer-events:none"/>`;
      // Hit-testing digating ke mode 'besi' saja (satu-satunya konsumen id 'B{n}', lihat bindSvg) --
      // di mode lain layer ini transparan ke event biar gak nelan tap tab Rangka/Support di bawahnya.
      s += `<line x1="${ax}" y1="${ay}" x2="${bx}" y2="${by}" stroke="${stroke}" stroke-width="6" stroke-linecap="round" style="pointer-events:${this.mode === 'besi' ? 'auto' : 'none'}"/>`;
      s += `<line x1="${ax}" y1="${ay}" x2="${bx}" y2="${by}" stroke="transparent" stroke-width="18" data-id="${m.id}" class="hit" style="cursor:pointer;pointer-events:${this.mode === 'besi' ? 'auto' : 'none'}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      // Label di titik tengah ikut arah garis (pola sama frame — jangan pernah terbalik).
      const lx = (ax + bx) / 2, ly = (ay + by) / 2;
      let ang = Math.atan2(by - ay, bx - ax) * 180 / Math.PI;
      if (ang > 90 || ang <= -90) ang += 180;
      const fullText = `${m.nama} · ${m.material} · ${m.panjang}`;
      const shortText = m.nama;
      const label = DenahConv.supportLabelText(fullText, shortText, dist(m.geom.a, m.geom.b) * this.SC, selected);
      if (label) s += `<text x="${lx}" y="${ly - 10}" fill="#e2e8f0" font-size="12" font-weight="700" text-anchor="middle" dominant-baseline="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3" transform="rotate(${ang} ${lx} ${ly - 10})">${label}</text>`;
    });
    // Endpoint marker (visual saja): ref tiang = solid ungu, bebas = kosong.
    (S.balok || []).forEach(b => {
      const a = DenahConv.resolveBalokEndpoint(S, b.a), c = DenahConv.resolveBalokEndpoint(S, b.b);
      [['a', a, b.a], ['b', c, b.b]].forEach(([, pt, end]) => {
        if (!pt) return;
        const cx = X(pt.x), cy = Y(pt.y), isTiang = end && end.t != null;
        s += `<circle cx="${cx}" cy="${cy}" r="5" fill="${isTiang ? '#a855f7' : '#0f2740'}" stroke="#a855f7" stroke-width="2" style="pointer-events:none"/>`;
      });
    });
    if (this.armed === 'addBox' && this.boxPreview.sisiIdx != null) {
      const pv = this.computeBoxPreviewVerts();
      const pts = [pv.p1, pv.p4, pv.p3, pv.p2].map(p => `${X(p.x)},${Y(p.y)}`).join(' ');
      s += `<polygon points="${pts}" fill="rgba(56,189,248,0.35)" stroke="#38bdf8" stroke-width="2" data-boxprev="1" style="cursor:grab"/>`;
    }
    // Garis bantu align-snap (Kelompok B) — 2 elemen tetap, disembunyikan/ditampilkan &
    // di-update posisinya lewat _updateAlignGuides()/_hideAlignGuides() selama drag, TANPA render ulang.
    s += `<line id="agx${this.uid}" x1="0" y1="0" x2="0" y2="0" stroke="#facc15" stroke-width="1.5" stroke-dasharray="5,4" style="display:none;pointer-events:none"/>`;
    s += `<line id="agy${this.uid}" x1="0" y1="0" x2="0" y2="0" stroke="#facc15" stroke-width="1.5" stroke-dasharray="5,4" style="display:none;pointer-events:none"/>`;
    s += '</svg>';
    const canvas = this._q('.de-canvas');
    canvas.innerHTML = s;
    this.bindSvg(canvas.querySelector('svg'));
    // legend
    const used = Object.keys(cmap);
    this._q('[data-role=legend]').innerHTML = used.length
      ? used.map(n => `<span><span class="de-sw" style="background:${cmap[n]}"></span><b>${n}</b></span>`).join('')
      : '<span style="color:#94a3b8">Belum ada batang</span>';
    this._jadwalCutting(mem);
    this._q('[data-role=luas]').textContent = (shoelace(S.verts) / 10000).toFixed(2) + ' m²';
    this.renderSides(mem);
    this.renderBoxPanel();
    this.renderTiangPanel(mem);
    this.renderSupportPanel(mem);
    this.renderBalokPanel(mem);
    this._changed();
  }

  // Update/sembunyikan garis bantu align-snap (dipakai drag tiang/support-garis/kotak, Kelompok B).
  // movingPt = posisi cm SAAT INI dari elemen yg digeser (ujung garis yg bergerak).
  _updateAlignGuides(guides, movingPt) {
    const PAD = this.PAD, X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    const gx = this._q('#agx' + this.uid), gy = this._q('#agy' + this.uid);
    if (!gx || !gy) return;
    const gRefX = (guides || []).find(g => g.axis === 'x');
    const gRefY = (guides || []).find(g => g.axis === 'y');
    if (gRefX) { gx.setAttribute('x1', X(gRefX.ref.x)); gx.setAttribute('y1', Y(gRefX.ref.y)); gx.setAttribute('x2', X(movingPt.x)); gx.setAttribute('y2', Y(movingPt.y)); gx.style.display = ''; }
    else gx.style.display = 'none';
    if (gRefY) { gy.setAttribute('x1', X(gRefY.ref.x)); gy.setAttribute('y1', Y(gRefY.ref.y)); gy.setAttribute('x2', X(movingPt.x)); gy.setAttribute('y2', Y(movingPt.y)); gy.style.display = ''; }
    else gy.style.display = 'none';
  }
  _hideAlignGuides() {
    const gx = this._q('#agx' + this.uid), gy = this._q('#agy' + this.uid);
    if (gx) gx.style.display = 'none';
    if (gy) gy.style.display = 'none';
  }

  bindSvg(el) {
    let drag = null;
    const PAD = this.PAD;
    const X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    el.addEventListener('pointerdown', e => {
      // Snapshot model SEBELUM branching -- kalau gestur ini ternyata jari pertama pinch-zoom
      // (jari ke-2 nyusul -> pointercancel), seluruh efek drag di-ROLLBACK ke sini, bukan
      // dikomit (lihat handler cancel di bawah; laporan Elvan 22 Ags malam: support kegeser
      // & grid "naik kelas" jadi manual gara2 jari pertama zoom).
      this._preDragS = JSON.stringify(this.S);
      const t = e.target; const cm = this.toCm(e, el);
      if (this.mode === 'bentuk') {
        if (this.armed === 'addBox' && this.boxPreview.sisiIdx == null && t.dataset.id && t.dataset.id.startsWith('F')) {
          const i = +t.dataset.id.slice(1);
          const a = this.S.verts[i], b = this.S.verts[(i + 1) % this.S.verts.length];
          const len = dist(a, b);
          const bp = this.boxPreview;
          bp.sisiIdx = i;
          bp.span = Math.min(bp.span, Math.max(1, Math.round(len - 1)));
          bp.offset = Math.max(0, (len - bp.span) / 2);
          this.setHint('Geser kotak buat pas-in posisi & arah (luar = nambah, dalam = lekukan), lalu ketuk Terapkan.');
          this.renderBoxPanel();
          this.render();
          return;
        }
        if (t.dataset.boxprev && this.boxPreview && this.boxPreview.sisiIdx != null) {
          drag = { type: 'box' };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (!this.armed && t.dataset.boxgroup != null) {
          this.pushUndo();
          const k = +t.dataset.boxgroup;
          const bx = this.S.combinedBoxes[k];
          const n = this.S.verts.length;
          const sideSet = new Set();
          bx.verts.forEach(v => { sideSet.add((v - 1 + n) % n); sideSet.add(v % n); });
          const sides = [...sideSet];
          drag = { type: 'boxgroup', k, startPt: cm, moved: false,
            vertIdx: bx.verts.slice(),
            startVerts: bx.verts.map(i => ({ ...this.S.verts[i] })),
            vh: bx.verts.map(i => el.querySelector('#vh' + i)),
            vhit: bx.verts.map(i => el.querySelector('#vhit' + i)),
            sides,
            fl: sides.map(i => el.querySelector('#fl' + i)),
            fll: sides.map(i => el.querySelector('#fll' + i)),
            poly: t };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (this.armed === 'delV' && t.dataset.vert != null) {
          if (this.S.verts.length > 3) {
            this.pushUndo();
            const vi = +t.dataset.vert;
            this.S.verts.splice(vi, 1);
            this.S.combinedBoxes = DenahConv.shiftBoxesDelete(this.S.combinedBoxes, vi);
          }
          // STICKY: armed TIDAK di-reset -- tetap mode hapus sudut biar bisa tap sudut lain lagi.
          this.setHint(this.S.verts.length > 3 ? 'Mode Hapus Sudut aktif — klik sudut lain, atau tap "− Sudut" untuk berhenti.' : 'Minimum 3 sudut. Tap "− Sudut" untuk berhenti.');
          this.render(); return; }
        if (this.armed === 'addV' && t.dataset.id && t.dataset.id.startsWith('F')) {
          this.pushUndo(); const i = +t.dataset.id.slice(1), nv = this.S.verts.length;
          const onLine = closestOnSegment(cm, this.S.verts[i], this.S.verts[(i + 1) % nv]);
          this.S.verts.splice(i + 1, 0, { x: this.snap(onLine.x), y: this.snap(onLine.y) });
          this.S.combinedBoxes = DenahConv.shiftBoxesInsert(this.S.combinedBoxes, i + 1, 1);
          // STICKY: armed TIDAK di-reset -- tetap mode tambah sudut biar bisa tap sisi lain lagi.
          this.setHint('Mode Tambah Sudut aktif — klik sisi lain, atau tap "+ Sudut" untuk berhenti.');
          this.render(); return; }
        if (t.dataset.vert != null) {
          this.pushUndo();
          const vi = +t.dataset.vert, n = this.S.verts.length;
          drag = { type: 'vert', vi, vh: el.querySelector('#vh' + vi), vhit: t,
                lPrev: el.querySelector('#fl' + ((vi - 1 + n) % n)), lThis: el.querySelector('#fl' + vi),
                tPrev: el.querySelector('#fll' + ((vi - 1 + n) % n)), tThis: el.querySelector('#fll' + vi) };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          const sup = el.querySelector('#supLayer'); if (sup) sup.style.opacity = '0.25';
        } else if (t.dataset.id && t.dataset.id.startsWith('F')) { this.typeSide(+t.dataset.id.slice(1)); }
      } else if (this.mode === 'tiang') {
        // Threshold sentuh berbasis PIKSEL LAYAR BENERAN (24px, dibagi screenScale() dulu baru SC) —
        // BUKAN cm dunia (grid*1.5, bug lama gelombang 1: di denah besar/zoom-out jadi cuma beberapa
        // piksel) DAN BUKAN cuma dibagi SC doang (bug lama gelombang 2, 18 Juli: SC itu skala auto-
        // fit KONTEN ke viewBox, tetap sama walau HP nyusutin tampilan svg via CSS max-width:100% —
        // di layar HP ~360px lebar, itu bikin toleransi sentuh NYATA cuma ~12-13px, bukan ~24px yg
        // dimaksud, jadi tap tepat di tiang sering dianggap meleset/kosong -> susah digeser & tekan-
        // tahan nyasar buka menu "Tambah Tiang" alih-alih "Ganti Besi/Hapus"). Sama pola r=24 hit-
        // area titik sudut poligon (itu elemen SVG asli, otomatis ikut skala tampil — ini versi JS-
        // nya harus disamakan basisnya manual lewat screenScale()).
        const TH = 24 / this.screenScale(el) / this.SC;
        const hit = this.S.tiang.findIndex(p => dist(p, cm) < TH);
        if (hit >= 0) {
          // pushUndo() SENGAJA belum dipanggil di sini (beda dari drag lain di file ini) — banyak
          // gestur di tiang berakhir TANPA mutasi sama sekali (tap sekejap, buka menu lalu Batal),
          // jadi tiap cabang yang BENERAN mengubah data (gerak pertama di pointermove, tiangMenuHapus)
          // motong undo snapshot-nya sendiri persis sebelum mutasi. "Ganti Besi" pakai push milik
          // matApply yang sudah ada, tak perlu tambahan di sini.
          // Sentuh+geser tiang yang sudah ada SELALU cuma pindah (persis titik sudut, tak ada lagi
          // nebak-nebak tap-vs-drag). Tekan-tahan TANPA gerak -> menu Ganti Besi/Hapus (pola klik-
          // kanan PC). Tap sekejap tanpa gerak & lepas sebelum 450ms -> tak ada aksi (aman, bukan
          // hapus diam-diam seperti perilaku lama).
          const myDrag = { type: 'tiang', i: hit, startPt: cm, moved: false,
            tc: el.querySelector('#tc' + hit), tl: el.querySelector('#tl' + hit) };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return; // gestur ini sudah berubah/berakhir, batal
            this.openTiangMenu(e, hit);
            drag = null; // gestur "dipakai" buat menu, jangan lanjut jadi drag/hapus pas pointerup
          }, 450);
        } else {
          // REDESAIN TOTAL (18 Juli, langsung permintaan Elvan setelah 3 percobaan tambal-sulam
          // meleset): taruh tiang baru TAK LAGI commit sendiri lewat tap/timer sama sekali.
          // Sekarang WAJIB tekan-tahan dulu (450ms, persis pola tiang yang sudah ada di atas),
          // baru muncul menu "Tambah Tiang" — user harus tap tombol itu buat beneran nambah.
          // Ini otomatis menutup 2 celah dari percobaan sebelumnya sekaligus, bukan cuma
          // ditambal lagi: (a) tap meleset dikit dari tiang lama tak lagi diam-diam nambah
          // duplikat — nambah baru kini juga butuh tekan-tahan+konfirmasi, bukan cuma tap;
          // (b) jari pertama gestur pinch-zoom yang mendarat di tempat kosong tak lagi nambah
          // apa pun — pointercancel dari jari ke-2 (lihat _wireZoom()) membatalkan timer 450ms
          // ini jauh sebelum menu sempat muncul, apalagi sebelum ada tombol yang bisa dipencet.
          const myAdd = { type: 'tiangPendingPlace', startPt: cm, x: this.snap(cm.x), y: this.snap(cm.y), moved: false };
          drag = myAdd;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myAdd.longPressTimer = setTimeout(() => {
            if (drag !== myAdd || myAdd.moved) return;
            this.openTiangAddMenu(e, myAdd.x, myAdd.y);
            drag = null;
          }, 450);
        }
      } else if (this.mode === 'support') {
        const locked = DenahConv.isLocked(this.S);
        if (locked) {
          // FASE TERKUNCI (spec 2.3): menu tekan-tahan DIHAPUS; geser garis DIHAPUS (pindah =
          // ketik angka di panel). Yang tersisa di kanvas: (a) + Support manual 2 tap,
          // (b) tarik ujung manual TERSOROT (moveOn nyala), (c) tap toleran = sorot/ganti kandidat.
          // moveOn mati = kanvas murni lihat/zoom/pan, tak ada satupun yang merespons.
          if (this.armed === 'addSupport') {
            if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
            else {
              this.pushUndo();
              const no = this.S.lockSeq || 1;
              this.S.supportsLocked.push({ no, manual: true, a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) }, aktif: true });
              this.S.lockSeq = no + 1;      // entri baru melanjutkan nomor (spec 2.2)
              // supPanelOpen SENGAJA tidak dipaksa: panel di ATAS kanvas — buka otomatis bikin
              // layout loncat & tap berikutnya meleset (laporan Elvan 25 Ags).
              this.addSupportPt = null; this.armed = null; this.selSup = no; this.setHint(); this.render();
            }
            return;
          }
          if (!this.moveOn) return;
          if (t.dataset.slend != null) {
            // Tarik ujung: HANYA manual tersorot (grid tak punya ujung tarikan — otomatis dari frame).
            const no = +t.dataset.slend, end = t.dataset.end;
            const myDrag = { type: 'lockend', no, end, startPt: cm, moved: false,
              h: el.querySelector('#slh' + no + end) };
            drag = myDrag;
            el.setPointerCapture(e.pointerId); e.preventDefault();
            return;
          }
          // Tap toleran: garis terdekat menang; tap lagi di tempat sama = kandidat berikutnya.
          const mem2 = DenahConv.buildMembers(this.S);
          const TH = 24 / this.screenScale(el) / this.SC;
          const ids = DenahConv.supportsNearPoint(mem2, cm, TH);
          if (!ids.length) { if (this.selSup != null) { this.selSup = null; this.render(); } return; }
          const curId = 'SL' + this.selSup;
          const k = ids.indexOf(curId);
          const sameSpot = this._lastPickPt && dist(cm, this._lastPickPt) < TH;
          this._lastPickPt = cm;
          this.selSup = +((k >= 0 && sameSpot) ? ids[(k + 1) % ids.length] : ids[0]).slice(2);
          // Panel TIDAK dibuka otomatis dari tap kanvas (dulu iya): panel di ATAS kanvas, buka
          // otomatis mendorong kanvas turun di bawah jari -> tap berikutnya meleset ke garis lain
          // + kerasa ribet (laporan Elvan 25 Ags). Buka panel = manual lewat tombol "Buka".
          this.render();
          return;
        }
        // FASE PRATINJAU: blok lama verbatim (data-sm / addSupport / Sm_ / supgrid) — jangan diubah.
        // Redesign Support (21 Agustus): SEMUA sub-elemen pakai pola Tiang -- tap cepat tanpa
        // gerak = no-op, geser = pindah, tahan diam 450ms = menu. pushUndo() TIDAK di sini
        // (banyak gestur berakhir tanpa mutasi) -- dipindah ke pointermove persis saat gerakan
        // nyata pertama terdeteksi, sama pola persis Tiang.
        if (t.dataset.sm != null) {
          // Titik ujung support manual: menu-nya SAMA dengan badan garis (Hapus = hapus seluruh
          // garis) -- struktur data {a,b} tak terpisahkan, tak ada konsep "hapus 1 titik saja"
          // (lihat spec Keputusan #2).
          const i = +t.dataset.sm, end = t.dataset.end;
          const myDrag = { type: 'sup', i, end, startPt: cm, moved: false,
            hit: t, h: el.querySelector('#smh' + i + end), line: el.querySelector('#sm' + i) };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, 'Sm_' + i);
            drag = null;
          }, 450);
          return;
        }
        if (this.armed === 'addSupport') {
          if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
          else { this.pushUndo(); this.S.supportsManual.push({ a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) } }); this.addSupportPt = null; this.armed = null; this.setHint(); this.render(); }
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('Sm_')) {
          const i = +t.dataset.id.slice(3);
          const m = this.S.supportsManual[i];
          const myDrag = { type: 'supline', i, startPt: cm, moved: false,
            startA: { ...m.a }, startB: { ...m.b },
            line: el.querySelector('#sm' + i), hit: t, lbl: el.querySelector('#smlbl' + i),
            ha: el.querySelector('#smh' + i + 'a'), hb: el.querySelector('#smh' + i + 'b'),
            hita: el.querySelector('[data-sm="' + i + '"][data-end="a"]'),
            hitb: el.querySelector('[data-sm="' + i + '"][data-end="b"]') };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, 'Sm_' + i);
            drag = null;
          }, 450);
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          // Support grid otomatis: sekarang BISA digeser (dikunci searah, lihat pointermove tipe
          // "supgrid" & DenahConv.lockSupportAxis) -- begitu dilepas dgn gerakan nyata, "naik
          // kelas" jadi entri supportsManual (lihat end()). Tahan diam 450ms tanpa gerak = menu
          // Kecualikan + Ganti Material.
          const id = t.dataset.id;
          const mem = DenahConv.buildMembers(this.S);
          const m = mem.find(x => x.id === id);
          if (!m) return;
          const myDrag = { type: 'supgrid', id, startPt: cm, moved: false,
            startA: { ...m.geom.a }, startB: { ...m.geom.b }, hit: t };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, id);
            drag = null;
          }, 450);
        }
      } else if (this.mode === 'besi') {
        if (t.dataset.id) this.openMatMenu(e, t.dataset.id);
      }
    });
    // Seret mulus: hanya ubah atribut node terkait, TANPA render ulang. Ikuti jari (tak snap); snap saat lepas.
    // Update LANGSUNG tiap pointermove (tak nunggu requestAnimationFrame) — jeda 1 frame kerasa lebih jauh
    // secara visual pas di-zoom (Elvan lapor drag "belum ikutin jari" saat pinch-zoom aktif).
    el.addEventListener('pointermove', e => {
      if (!drag) return; const cm = this.toCm(e, el), px = PAD + cm.x * this.SC, py = PAD + cm.y * this.SC;
      {
        if (drag.type === 'vert') { const vi = drag.vi, n = this.S.verts.length;
          // ORTHO-SNAP: kalau hampir sejajar dgn sudut tetangga (kiri/kanan poligon), kunci ke sumbunya
          // → sisi jadi lurus vertikal/horizontal tanpa harus pas manual. Bikin lekukan gampang.
          const pv = this.S.verts[(vi - 1 + n) % n], nx = this.S.verts[(vi + 1) % n];
          const TH = (this.S.grid || 20) * 0.8;
          let ax = cm.x, ay = cm.y;
          if (Math.abs(ax - pv.x) < TH) ax = pv.x; else if (Math.abs(ax - nx.x) < TH) ax = nx.x;
          if (Math.abs(ay - pv.y) < TH) ay = pv.y; else if (Math.abs(ay - nx.y) < TH) ay = nx.y;
          this.S.verts[vi] = { x: ax, y: ay };
          const px2 = PAD + ax * this.SC, py2 = PAD + ay * this.SC;
          drag.vh.setAttribute('cx', px2); drag.vh.setAttribute('cy', py2);
          drag.vhit.setAttribute('cx', px2); drag.vhit.setAttribute('cy', py2);
          if (drag.lPrev) { drag.lPrev.setAttribute('x2', px2); drag.lPrev.setAttribute('y2', py2); }
          if (drag.lThis) { drag.lThis.setAttribute('x1', px2); drag.lThis.setAttribute('y1', py2); }
          const upLbl = (elx, i) => { if (!elx) return; const a = this.S.verts[i], b = this.S.verts[(i + 1) % n];
            const fla = this._frameLabelAttrs(a, b);
            elx.setAttribute('x', fla.lx); elx.setAttribute('y', fla.ly);
            elx.setAttribute('transform', `rotate(${fla.ang} ${fla.lx} ${fla.ly})`);
            elx.textContent = 'F' + (i + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10); };
          upLbl(drag.tPrev, (vi - 1 + n) % n); upLbl(drag.tThis, vi); this.syncLP();
        } else if (drag.type === 'sup') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 1.2;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          this.S.supportsManual[drag.i][drag.end] = snapped;
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px2);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py2);
          drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2);
          drag.hit.setAttribute('cx', px2); drag.hit.setAttribute('cy', py2);
        } else if (drag.type === 'box') {
          const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
          const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
          const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey) || 1;
          const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
          const vx = cm.x - a.x, vy = cm.y - a.y;
          const along = vx * ux + vy * uy, side = vx * nx + vy * ny;
          bp.offset = Math.max(0, Math.min(along - bp.span / 2, len - bp.span));
          bp.depthSign = side >= 0 ? 1 : -1;
          const pv = this.computeBoxPreviewVerts();
          const poly = el.querySelector('[data-boxprev]');
          if (poly) poly.setAttribute('points', [pv.p1, pv.p4, pv.p3, pv.p2].map(p => `${X(p.x)},${Y(p.y)}`).join(' '));
        } else if (drag.type === 'tiang') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            // pushUndo() persis di sini (gerak nyata pertama kali terdeteksi, SEBELUM posisi ditulis
            // baris di bawah) — bukan di pointerdown (byk gestur tiang berakhir tanpa mutasi sama
            // sekali) atau di end() (posisi sudah berubah duluan lewat pointermove, kepagian/salah
            // snapshot kalau push di situ).
            this.pushUndo();
          }
          if (!drag.moved) return;
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'tiang', i: drag.i });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap(cm, candidates, TH);
          const clamped = this.clampTiang(snap);
          this.S.tiang[drag.i] = clamped;
          this._lastGuides = snap.guides;
          const px2 = PAD + clamped.x * this.SC, py2 = PAD + clamped.y * this.SC;
          if (drag.tc) { drag.tc.setAttribute('cx', px2); drag.tc.setAttribute('cy', py2); }
          if (drag.tl) { drag.tl.setAttribute('x', px2 + 9); drag.tl.setAttribute('y', py2 + 4); }
          this._updateAlignGuides(snap.guides, clamped);
        } else if (drag.type === 'tiangPendingPlace') {
          // Jari gerak berarti niatnya BUKAN tekan-tahan diam (mungkin geser pandangan/tak sengaja)
          // — batalkan niat taruh tiang sama sekali, jangan taruh apa pun. Beda dari tiang yang
          // sudah ada (gerak = pindah): di tempat kosong belum ada apa-apa buat "dipindah".
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            drag = null;
          }
        } else if (drag.type === 'supline') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const midStart = { x: (drag.startA.x + drag.startB.x) / 2 + dx, y: (drag.startA.y + drag.startB.y) / 2 + dy };
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'sup', i: drag.i });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap(midStart, candidates, TH);
          const adjX = snap.x - midStart.x, adjY = snap.y - midStart.y;
          const a = { x: drag.startA.x + dx + adjX, y: drag.startA.y + dy + adjY };
          const b = { x: drag.startB.x + dx + adjX, y: drag.startB.y + dy + adjY };
          this.S.supportsManual[drag.i] = { a, b };
          this._lastGuides = snap.guides;
          const ax = X(a.x), ay = Y(a.y), bx = X(b.x), by = Y(b.y);
          if (drag.line) { drag.line.setAttribute('x1', ax); drag.line.setAttribute('y1', ay); drag.line.setAttribute('x2', bx); drag.line.setAttribute('y2', by); }
          if (drag.hit) { drag.hit.setAttribute('x1', ax); drag.hit.setAttribute('y1', ay); drag.hit.setAttribute('x2', bx); drag.hit.setAttribute('y2', by); }
          if (drag.ha) { drag.ha.setAttribute('cx', ax); drag.ha.setAttribute('cy', ay); }
          if (drag.hb) { drag.hb.setAttribute('cx', bx); drag.hb.setAttribute('cy', by); }
          if (drag.hita) { drag.hita.setAttribute('cx', ax); drag.hita.setAttribute('cy', ay); }
          if (drag.hitb) { drag.hitb.setAttribute('cx', bx); drag.hitb.setAttribute('cy', by); }
          if (drag.lbl) { const lvert = Math.abs(a.y - b.y) >= Math.abs(a.x - b.x);
            const t3 = 0.3;
            const lmx = lvert ? X(a.x + (b.x - a.x) * t3) : X((a.x + b.x) / 2);
            const lmy = lvert ? Y(a.y + (b.y - a.y) * t3) : Y((a.y + b.y) / 2);
            drag.lbl.setAttribute('x', lmx); drag.lbl.setAttribute('y', lvert ? lmy : lmy - 4);
            if (lvert) { drag.lbl.setAttribute('dy', '-6'); drag.lbl.setAttribute('transform', `rotate(-90 ${lmx} ${lmy})`); }
            else { drag.lbl.removeAttribute('dy'); drag.lbl.removeAttribute('transform'); } }
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'boxgroup') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const cx0 = drag.startVerts.reduce((acc, p) => acc + p.x, 0) / drag.startVerts.length + dx;
          const cy0 = drag.startVerts.reduce((acc, p) => acc + p.y, 0) / drag.startVerts.length + dy;
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'box', vertIdx: drag.vertIdx });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap({ x: cx0, y: cy0 }, candidates, TH);
          const adjX = snap.x - cx0, adjY = snap.y - cy0;
          drag.vertIdx.forEach((vi, idx) => {
            this.S.verts[vi] = { x: drag.startVerts[idx].x + dx + adjX, y: drag.startVerts[idx].y + dy + adjY };
          });
          this._lastGuides = snap.guides;
          drag.vertIdx.forEach((vi, idx) => {
            const p = this.S.verts[vi], px2 = X(p.x), py2 = Y(p.y);
            if (drag.vh[idx]) { drag.vh[idx].setAttribute('cx', px2); drag.vh[idx].setAttribute('cy', py2); }
            if (drag.vhit[idx]) { drag.vhit[idx].setAttribute('cx', px2); drag.vhit[idx].setAttribute('cy', py2); }
          });
          drag.sides.forEach((si, idx) => {
            const n = this.S.verts.length;
            const a = this.S.verts[si], b = this.S.verts[(si + 1) % n];
            if (drag.fl[idx]) { drag.fl[idx].setAttribute('x1', X(a.x)); drag.fl[idx].setAttribute('y1', Y(a.y)); drag.fl[idx].setAttribute('x2', X(b.x)); drag.fl[idx].setAttribute('y2', Y(b.y)); }
            if (drag.fll[idx]) {
              const fla = this._frameLabelAttrs(a, b);
              drag.fll[idx].setAttribute('x', fla.lx); drag.fll[idx].setAttribute('y', fla.ly);
              drag.fll[idx].setAttribute('transform', `rotate(${fla.ang} ${fla.lx} ${fla.ly})`);
              drag.fll[idx].textContent = 'F' + (si + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10);
            }
          });
          if (drag.poly) drag.poly.setAttribute('points', drag.vertIdx.map(vi => `${X(this.S.verts[vi].x)},${Y(this.S.verts[vi].y)}`).join(' '));
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'supgrid') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const locked = DenahConv.lockSupportAxis(drag.id, drag.startA, drag.startB, dx, dy);
          drag.curA = locked.a; drag.curB = locked.b;
          const ax = X(locked.a.x), ay = Y(locked.a.y), bx = X(locked.b.x), by = Y(locked.b.y);
          const line = el.querySelector('#sg_' + drag.id);
          if (line) { line.setAttribute('x1', ax); line.setAttribute('y1', ay); line.setAttribute('x2', bx); line.setAttribute('y2', by); }
          if (drag.hit) { drag.hit.setAttribute('x1', ax); drag.hit.setAttribute('y1', ay); drag.hit.setAttribute('x2', bx); drag.hit.setAttribute('y2', by); }
        } else if (drag.type === 'lockend') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) { drag.moved = true; this.pushUndo(); }
          if (!drag.moved) return;
          const e2 = this.S.supportsLocked.find(x => x.no === drag.no);
          if (!e2) return;
          const anchor = e2[drag.end === 'a' ? 'b' : 'a'];
          const TH = (this.S.grid || 20) * 1.2;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          e2[drag.end] = snapped;
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          if (drag.h) { drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2); }
          // GARIS ikut jari live (bukan cuma titiknya) — dulu garis baru pindah saat jari
          // dilepas, kerasa "seluruh garis loncat/miring tiba-tiba" (laporan Elvan 25 Ags).
          // Entri manual terkunci = 1 potongan -> id 'slg{no}_0'. Pola sama drag 'sup' pratinjau.
          const lline = el.querySelector('#slg' + drag.no + '_0');
          if (lline) {
            lline.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px2);
            lline.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py2);
          }
        }
      }
    });
    const end = () => { if (!drag) return;
      if (drag.type === 'tiangPendingPlace') {
        // Lepas jari SEBELUM menu "Tambah Tiang" sempat muncul (< 450ms, atau dibatalkan gerak/
        // pointercancel pinch-zoom) -> tak ada aksi sama sekali, tak nambah apa pun. Nambah beneran
        // CUMA lewat tap tombol "Tambah Tiang" di menu (lihat openTiangAddMenu di _wireControls()).
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        drag = null; return;
      }
      if (drag.type === 'vert') {
        // Sama seperti support manual di bawah: snap-grid tanpa syarat bisa menggeser lagi sudut
        // yang barusan pas ortho-snap-kan ke sudut tetangga (pv/nx sering tak persis kelipatan
        // grid) — sisi vertikal jadi lurus tapi sisi horizontal (atau sebaliknya) miring lagi pas
        // dilepas, hasil akhir tidak siku. Kalau sumbu itu SUDAH persis sama salah satu tetangga,
        // pertahankan persis.
        const vi = drag.vi, n = this.S.verts.length;
        const pv = this.S.verts[(vi - 1 + n) % n], nx = this.S.verts[(vi + 1) % n];
        const v = this.S.verts[vi];
        this.S.verts[vi] = {
          x: (v.x === pv.x || v.x === nx.x) ? v.x : this.snap(v.x),
          y: (v.y === pv.y || v.y === nx.y) ? v.y : this.snap(v.y),
        };
      }
      else if (drag.type === 'sup') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved) {
          // Snap grid biasa BISA menggeser lagi titik yang barusan pas ortho-snap-kan ke anchor
          // (anchor sering tak persis kelipatan grid — datang dari resize/"Ukur Sisi" presisi yang
          // sengaja tak di-snap). Kalau sumbu itu SUDAH persis sama anchor (ortho-snap aktif pas
          // drag), pertahankan persis — jangan di-snap-grid lagi, biar tak jadi bengkok pas dilepas.
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const p = this.S.supportsManual[drag.i][drag.end];
          this.S.supportsManual[drag.i][drag.end] = {
            x: p.x === anchor.x ? p.x : this.snap(p.x),
            y: p.y === anchor.y ? p.y : this.snap(p.y),
          };
        }
        // !moved dan menu belum sempat kebuka (dilepas cepat < 450ms) -> tak ada aksi, titik
        // tetap di tempat semula. Sama pola persis Tiang -- tak lagi ambigu drag vs menu.
      }
      else if (drag.type === 'box') { this.boxPreview.offset = this.snap(this.boxPreview.offset); }
      else if (drag.type === 'tiang') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved) {
          // Sama pola fix Kelompok A (vert/sup): kalau sumbu SUDAH persis sama titik align-snap yg
          // aktif barusan, jangan snap-grid lagi di situ — cegah "lurus pas drag, bengkok pas lepas".
          const p = this.S.tiang[drag.i];
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          this.S.tiang[drag.i] = {
            x: (gx && p.x === gx.ref.x) ? p.x : this.snap(p.x),
            y: (gy && p.y === gy.ref.y) ? p.y : this.snap(p.y),
          };
        }
        // !moved dan menu belum sempat kebuka (dilepas cepat < 450ms) -> tak ada aksi sama sekali,
        // tiang tetap di tempat semula. Beda dari perilaku lama (tap = hapus) yang bikin ambigu
        // sama drag — sekarang hapus/ganti-besi cuma lewat menu tekan-tahan (openTiangMenu).
        this._hideAlignGuides();
      }
      else if (drag.type === 'supline') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved) {
          const m = this.S.supportsManual[drag.i];
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          const mid = { x: (m.a.x + m.b.x) / 2, y: (m.a.y + m.b.y) / 2 };
          // Kalau sumbu itu barusan aktif align-snap (ada di this._lastGuides), JANGAN grid-snap sumbu
          // itu sama sekali -- posisi sekarang sudah pas (dipercaya langsung, tak dibandingkan nilai persis
          // krn midpoint di sini hasil rekomputasi arithmetic yg bisa beda dikit scr floating-point dari nilai
          // yg dicocokkan waktu pointermove -- BEDA dari vert/sup/tiang yg nilainya tersimpan persis tanpa
          // rekomputasi, jadi aman pakai === di situ).
          const snappedMid = {
            x: gx ? mid.x : this.snap(mid.x),
            y: gy ? mid.y : this.snap(mid.y),
          };
          // Geser KEDUA ujung dgn OFFSET SAMA persis — garis tetap lurus, arah/panjang tak berubah.
          const shiftX = snappedMid.x - mid.x, shiftY = snappedMid.y - mid.y;
          this.S.supportsManual[drag.i] = {
            a: { x: m.a.x + shiftX, y: m.a.y + shiftY },
            b: { x: m.b.x + shiftX, y: m.b.y + shiftY },
          };
        }
        this._hideAlignGuides();
      }
      else if (drag.type === 'boxgroup') {
        if (drag.moved) {
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          const cen = {
            x: drag.vertIdx.reduce((acc, vi) => acc + this.S.verts[vi].x, 0) / drag.vertIdx.length,
            y: drag.vertIdx.reduce((acc, vi) => acc + this.S.verts[vi].y, 0) / drag.vertIdx.length,
          };
          // PENTING: sama seperti supline (Task 4) — JANGAN bandingkan nilai (===) ke guide.ref di sini.
          // `cen` adalah hasil rekomputasi rata-rata dari vertex yang masing-masing sudah digeser lewat
          // rantai aritmatika terpisah di pointermove — bisa beda dikit scr floating-point dari nilai
          // yang dicocokkan waktu itu, walau axis itu barusan PERSIS ter-align (pelajaran dari bug nyata
          // yang ketemu & diperbaiki 2x di Task 4, kelas bug sama: "lurus pas drag, bengkok pas lepas").
          // Cukup cek KEHADIRAN guide di this._lastGuides: kalau ada, percaya posisi sekarang apa adanya.
          const snappedCen = {
            x: gx ? cen.x : this.snap(cen.x),
            y: gy ? cen.y : this.snap(cen.y),
          };
          const shiftX = snappedCen.x - cen.x, shiftY = snappedCen.y - cen.y;
          drag.vertIdx.forEach(vi => { const p = this.S.verts[vi]; this.S.verts[vi] = { x: p.x + shiftX, y: p.y + shiftY }; });
        }
        this._hideAlignGuides();
      }
      else if (drag.type === 'supgrid') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved && drag.curA && drag.curB) {
          // "Naik kelas": ID grid tak stabil lintas render (lihat DenahConv.lockSupportAxis
          // komentar + spec Keputusan #4) -- JANGAN simpan offset nempel ke ID itu. Konversi jadi
          // entri supportsManual sungguhan (ID stabil Sm_i), lalu kecualikan posisi grid asal
          // biar tak dobel tergambar.
          const { a, b } = DenahConv.snapPromotedSupport(drag.id, drag.curA, drag.curB, this.S.grid);
          const newIdx = this.S.supportsManual.length;
          this.S.supportsManual.push({ a, b });
          if (this.S.matOverride[drag.id] != null) {
            this.S.matOverride['Sm_' + newIdx] = this.S.matOverride[drag.id];
            delete this.S.matOverride[drag.id];
          }
          this.S.removed[drag.id] = true;
        }
        // !moved dan menu belum sempat kebuka (dilepas cepat < 450ms) -> tak ada aksi sama sekali.
      }
      else if (drag.type === 'lockend') {
        if (drag.moved) {
          const e2 = this.S.supportsLocked.find(x => x.no === drag.no);
          if (e2) {
            // Pola sama tipe 'sup': sumbu yg barusan ortho-snap ke anchor JANGAN di-grid-snap lagi.
            const anchor = e2[drag.end === 'a' ? 'b' : 'a'], p = e2[drag.end];
            e2[drag.end] = { x: p.x === anchor.x ? p.x : this.snap(p.x), y: p.y === anchor.y ? p.y : this.snap(p.y) };
          }
        }
      }
      drag = null; this.render(); };
    el.addEventListener('pointerup', end);
    // pointercancel BEDA dari pointerup: gestur DIBATALKAN (jari ke-2 pinch-zoom nyusul, atau
    // browser ambil alih). Dulu disamakan dengan end() -> semua efek drag dikomit (support
    // kegeser, grid kadung naik-kelas jadi manual). Sekarang: ROLLBACK penuh ke snapshot
    // _preDragS yang diambil di pointerdown -- gestur batal = tak terjadi apa-apa (janji yang
    // sama dgn redesign tiang 18 Juli). Entry undo yang kadung di-push saat drag mulai (isinya
    // identik snapshot) ikut dicabut biar tak ada langkah undo kosong.
    const cancelDrag = () => {
      if (!drag) return;
      if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
      this._hideAlignGuides && this._hideAlignGuides();
      if (this._preDragS) {
        if (this.undoStack.length && this.undoStack[this.undoStack.length - 1] === this._preDragS) this.undoStack.pop();
        Object.assign(this.S, JSON.parse(this._preDragS));
      }
      drag = null; this.syncInputs(); this.render();
    };
    el.addEventListener('pointercancel', cancelDrag);
    // Dipanggil LANGSUNG oleh _wireZoom saat jari ke-2 pinch terdeteksi. Jalur lama (dispatch
    // synthetic PointerEvent('pointercancel') ke <svg>) CACAT sejak awal: event buatan default
    // bubbles:false, listener-nya di kontainer -> sinyal gak pernah nyampe, drag jalan terus
    // bareng pinch (support kegeser/naik-kelas, zoom kacau -- laporan Elvan 22 Ags malam, 2x).
    this._cancelDrag = cancelDrag;
  }

  // ---- API publik (dipakai Task 3) ----
  getModel() { return JSON.parse(JSON.stringify(this.S)); }
  getMembers() { return DenahConv.buildMembers(this.S); }
  getLuas() { return DenahConv.luasM2(this.S); }
  setModel(m) { this.armed = null; this.boxPreview = null; this.S = JSON.parse(JSON.stringify(m)); this.selSup = null; this.selBalok = null; this.selSisi = null; this.sisiShowAll = false; this.selTiang = null; this.tiangShowAll = false; this.moveOn = false; this._lastPickPt = null; this.syncInputs(); this.render(); }
}

// ---- self-check ringkas, browser-only (guard: tak jalan di produksi/Node) ----
if (globalThis.__DENAH_SELFCHECK) {
  try {
    const div = document.createElement('div');
    const ed = new DenahEditor(div, { besi: [{ nama: 'Hollow 5x10', harga: 120000 }] });
    ed.setModel({
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 400 }, { x: 0, y: 400 }],
      grid: 20, kotak: 100, arah: 'h',
      supportsManual: [], removed: {}, tiang: [], tinggi: 300,
      matDefault: { frame: 'Hollow 5x10', support: 'Hollow 5x10', tiang: 'Hollow 5x10' }, matOverride: {},
    });
    console.assert(!!ed._q('[data-role=btnAddBox]'), 'DenahEditor selfcheck: tombol + Tambah Kotak ada');
    const mem = ed.getMembers();
    const fr = mem.filter(m => m.jenis === 'frame');
    console.assert(fr.length === 4, 'DenahEditor selfcheck: frame square=4', fr.length);
    console.assert(fr.every(f => f.panjang === 400), 'DenahEditor selfcheck: frame len=400');
    ed.destroy();
    console.log('%cself-check DenahEditor OK', 'color:green');
  } catch (e) {
    console.error('DenahEditor selfcheck FAILED', e);
  }
}

// Ekspos sbg global classic-script (browser: globalThis===window; Node test: globalThis===global).
// SENGAJA TANPA ESM `export`: file dimuat browser lewat <script> KLASIK di blade rab-opsi, dan
// package.json "type":"module" membuat `export` gagal di classic script. Node memuat via read+eval
// (lihat tests/rangka/test_konverter.mjs).
globalThis.DenahConv = DenahConv;
DenahEditor._n = 0;   // counter instance untuk id pattern grid unik
globalThis.DenahEditor = DenahEditor;
})();
