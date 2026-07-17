(function () {
const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
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
  buildMembers(S) {
    // K harus > 0: kotak<=0 (mis. input negatif / model tersimpan rusak) bikin loop scanline tak berhenti → freeze tab.
    const mem = [], V = S.verts, bb = bbox(V), K = (S.kotak > 0 ? S.kotak : 100), rem = S.removed || {};
    // frame: tiap sisi poligon
    V.forEach((v, i) => {
      const w = V[(i + 1) % V.length], id = 'F' + i;
      const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.frame;
      mem.push({ id, nama: 'F' + (i + 1), jenis: 'frame', panjang: Math.round(dist(v, w) * 10) / 10, material: mat, geom: { a: v, b: w } });
    });
    const addSeg = (id, a, b) => {
      if (rem[id]) return;
      const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
      mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(a, b)), material: mat, geom: { a, b } });
    };
    if (S.arah === 'h' || S.arah === '2') { let li = 0;
      for (let Y = bb.y0 + K; Y < bb.y1 - 1; Y += K, li++) { const xs = scanX(V, Y);
        for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); } }
    if (S.arah === 'v' || S.arah === '2') { let li = 0;
      for (let X = bb.x0 + K; X < bb.x1 - 1; X += K, li++) { const ys = scanY(V, X);
        for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); } }
    (S.supportsManual || []).forEach((m, i) => {
      const id = 'Sm_' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
      mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(m.a, m.b)), material: mat, geom: { a: m.a, b: m.b } });
    });
    (S.tiang || []).forEach((t, i) => {
      const id = 'T' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.tiang;
      mem.push({ id, nama: 'T' + (i + 1), jenis: 'tiang', panjang: S.tinggi, material: mat, geom: { p: t } });
    });
    return mem;
  },
  luasM2(S) { return Math.round(shoelace(S.verts) / 10000 * 100) / 100; },
  saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n); },
  // Tempel kotak ke 1 sisi lurus (sisiIdx): sisipkan "detour" 4 titik pengganti segmen yang
  // ketutup. Tanda `depth` menentukan arah — SATU fungsi yang sama menghasilkan tonjolan
  // keluar (nambah) atau notch ke dalam (lekukan), tergantung tanda itu. UI (DenahEditor) yang
  // memutuskan tandanya dari posisi drag — fungsi ini tak tahu & tak perlu tahu mana "luar"/"dalam".
  combineBox(verts, sisiIdx, offset, span, depth) {
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
    return isSimplePolygon(out) ? out : null;
  },
  // Dipanggil setelah S.verts.splice(at, 0, ...count vertex baru...) (mode "+ Sudut" atau
  // combineBox saat "+ Tambah Kotak"): index vertex combinedBoxes yg >= at ikut geser +count
  // (vertex baru masuk SEBELUM index itu).
  shiftBoxesInsert(boxes, at, count) {
    return (boxes || []).map(bx => ({ verts: bx.verts.map(i => i >= at ? i + count : i) }));
  },
  // Dipanggil setelah S.verts.splice(at, 1) (mode "− Sudut"): entry yg salah satu vertex-nya
  // PERSIS `at` dibuang (kotak itu dianggap bukan satu kesatuan lagi — salah satu sudutnya hilang).
  // Entry lain yg index-nya > at ikut geser -1.
  shiftBoxesDelete(boxes, at) {
    return (boxes || []).filter(bx => !bx.verts.includes(at)).map(bx => ({ verts: bx.verts.map(i => i > at ? i - 1 : i) }));
  },
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
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
      const first = this.besi[0].nama;
      if (!this.S.matDefault.frame) this.S.matDefault.frame = first;
      if (!this.S.matDefault.support) this.S.matDefault.support = first;
      if (!this.S.matDefault.tiang) this.S.matDefault.tiang = first;
    }
    this.undoStack = []; this.redoStack = [];
    this.mode = 'bentuk';
    this.armed = null;      // 'addV' | 'delV' | 'addSupport' | 'addBox'
    this.addSupportPt = null;
    this.boxPreview = null; // { sisiIdx, offset, span, depthMag, depthSign } selama armed === 'addBox'
    this.menuId = null;
    this.SC = 1;
    this.PAD = 44;
    this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
    this.uid = ++DenahEditor._n;   // id unik per instance (pattern grid dirujuk url(#..) yg resolve se-dokumen)

    this.el.innerHTML = DenahEditor.shellHTML();
    this._fillMatSelects();
    this._wireControls();
    this._wireRibbon();
    this._wireZoom();
    this._wireFullscreen();
    this.syncInputs();
    this.render();
  }

  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20, target: 100,
      kotak: 100, autoKotak: true, arah: '2', supportsManual: [], removed: {}, tiang: [],
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '' }, matOverride: {}, combinedBoxes: [],
    };
  }

  static shellHTML() {
    return `
<style>
.de-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.de-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.de-row>label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-card input[type=number],.de-card input[type=text]{width:78px;padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px}
.de-card select{padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff}
.de-tools{display:flex;flex-wrap:wrap;gap:10px;margin:2px 0}
.de-tool{padding:10px 14px;min-height:40px;box-sizing:border-box;display:inline-flex;align-items:center;border:1px solid #334155;background:#fff;border-radius:8px;font-size:13px;cursor:pointer;user-select:none}
.de-tool.on{background:#1e293b;color:#fff}
.de-mini{padding:9px 13px;min-height:40px;box-sizing:border-box;display:inline-flex;align-items:center;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:12px;cursor:pointer}
.de-hint{font-size:12px;color:#64748b;margin:6px 2px;min-height:16px}
.de-ribbon{position:sticky;top:0;z-index:15;margin-bottom:10px}
.de-ribbon-tabs{display:flex;border:1px solid #334155;border-radius:8px;overflow:hidden;background:#1e293b}
.de-ribbon-tab{flex:1;text-align:center;padding:11px 4px;min-height:40px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;color:#cbd5e1;cursor:pointer;user-select:none;border-right:1px solid #334155}
.de-ribbon-tab:nth-last-child(2){border-right:none}
.de-ribbon-tab.on{background:#0f2740;color:#38bdf8;font-weight:600}
.de-ribbon-strip{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:20;border:1px solid transparent;border-radius:8px;background:#f8fafc;padding:0;max-height:0;overflow:hidden;transition:max-height .15s ease;box-shadow:0 6px 18px rgba(0,0,0,.28)}
.de-ribbon-strip.open{border-color:#334155;padding:10px 12px;max-height:45vh;overflow-y:auto}
.de-ribbon-panel{display:none}
.de-ribbon-panel.on{display:block}
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.de-card.de-fullscreen{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;overflow-y:auto;border-radius:0;margin:0;box-shadow:none}
.de-fullscreen-exit{display:none;flex:0 0 auto;min-height:40px;box-sizing:border-box;padding:0 18px;margin-left:6px;border-radius:8px;background:#f59e0b;color:#1e293b;border:none;font-size:13px;font-weight:700;cursor:pointer;align-items:center;justify-content:center}
.de-canvas-wrap{position:relative;touch-action:none;overflow:hidden}
.de-canvas{background:#0f2740;border-radius:10px;padding:6px;overflow:hidden;transform-origin:0 0}
.de-canvas svg{max-width:100%;touch-action:none;display:block}
.de-zoom-reset{position:absolute;right:10px;bottom:10px;min-width:44px;min-height:44px;padding:0 14px;border-radius:22px;background:rgba(15,23,42,.85);color:#e2e8f0;border:1px solid #334155;font-size:13px;display:none;align-items:center;justify-content:center;cursor:pointer;user-select:none}
.de-zoom-reset.show{display:flex}
.de-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;font-size:12px;color:#475569}
.de-legend b{font-weight:600}
.de-sw{display:inline-block;width:11px;height:11px;border-radius:2px;margin-right:5px;vertical-align:middle}
.de-matbar{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.de-matbar label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-matmenu{position:fixed;z-index:9999;display:none;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px}
.de-matmenu select{width:150px}
.de-matmenu .de-mrow{display:flex;gap:6px;margin-top:6px}
</style>
<div class="de-card">
  <div class="de-ribbon">
  <div class="de-ribbon-tabs">
    <span class="de-ribbon-tab" data-tab="ukuran">Ukuran</span>
    <span class="de-ribbon-tab" data-tab="support">Support</span>
    <span class="de-ribbon-tab" data-tab="besi">Besi</span>
    <span class="de-ribbon-tab" data-tab="mode">Mode</span>
    <span class="de-ribbon-tab" data-tab="sisi">Ukur Sisi</span>
    <span class="de-fullscreen-exit" data-role="btnFullscreenExit">Selesai</span>
  </div>
  <div class="de-ribbon-strip" data-role="ribbonStrip">
    <div class="de-ribbon-panel" data-panel="ukuran">
      <div class="de-row">
        <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
        <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
        <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
        <label>Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
        <span class="de-mini" data-role="btnReset">Reset kotak dari Lebar×Panjang</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="support">
      <div class="de-row">
        <label>Arah support
          <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
        </label>
        <label>Kotak support (cm)<input type="number" data-role="inKotak" value="100" step="5" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="besi">
      <div class="de-matbar">
        <label>Besi frame<select data-role="matFrame"></select></label>
        <label>Besi support<select data-role="matSupport"></select></label>
        <label>Besi tiang<select data-role="matTiang"></select></label>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="mode">
      <div class="de-tools">
        <span class="de-tool on" data-mode="bentuk">Bentuk</span>
        <span class="de-tool" data-mode="besi">Ganti besi</span>
        <span class="de-tool" data-mode="support">Support</span>
        <span class="de-tool" data-mode="tiang">Tiang</span>
        <span class="de-mini" data-role="btnAddV">+ Sudut</span>
        <span class="de-mini" data-role="btnDelV">− Sudut</span>
        <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="sisi">
      <div class="de-legend" data-role="sisiPanel"></div>
    </div>
  </div>
  </div>
  <div class="de-quickbar">
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnRedo">Redo</span>
    <span class="de-mini" data-role="btnFullscreen">Perbesar Layar</span>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
  <div class="de-hint" data-role="hint">Mode Bentuk: seret bulatan sudut untuk mengubah bentuk. Ketuk angka cm di sisi untuk ketik panjang pasti.</div>
  <div class="de-canvas-wrap" data-role="canvasWrap">
    <div class="de-canvas"></div>
    <span class="de-zoom-reset" data-role="btnZoomReset">Reset</span>
  </div>
  <div class="de-legend" data-role="legend"></div>
  <div style="font-size:12px;color:#64748b;margin-top:4px">Luas denah: <b data-role="luas">–</b></div>
</div>
<div class="de-matmenu" data-role="matMenu">
  <div data-role="matMenuLabel" style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px"></div>
  <select data-role="matPick"></select>
  <div class="de-mrow"><span class="de-mini" data-role="matApply">Ganti</span><span class="de-mini" data-role="matClear">Pakai default</span><span class="de-mini" data-role="matCancel">Batal</span></div>
</div>`;
  }

  // ---- helpers DOM (SELALU scoped ke this.el — jangan pernah global) ----
  _q(sel) { return this.el.querySelector(sel); }
  _qa(sel) { return this.el.querySelectorAll(sel); }

  _fillMatSelects() {
    const optsHtml = this.besi.map(b => `<option>${b.nama}</option>`).join('');
    ['matFrame', 'matSupport', 'matTiang', 'matPick'].forEach(role => {
      const sel = this._q(`[data-role=${role}]`);
      if (sel) sel.innerHTML = optsHtml;
    });
  }

  _wireControls() {
    this._qa('.de-tool').forEach(elx => elx.onclick = () => {
      this._qa('.de-tool').forEach(t => t.classList.remove('on'));
      elx.classList.add('on');
      this.mode = elx.dataset.mode;
      this.armed = null; this.addSupportPt = null; this.boxPreview = null;
      this.setHint();
      this.render();
    });
    this._q('[data-role=btnAddV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'addV'; this.boxPreview = null; this.setHint('Klik sisi frame untuk sisipkan sudut baru.'); this.renderBoxPanel(); };
    this._q('[data-role=btnDelV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'delV'; this.boxPreview = null; this.setHint('Klik sudut untuk menghapus (min 3 sudut).'); this.renderBoxPanel(); };
    this._q('[data-role=btnUndo]').onclick = () => this.undo();
    this._q('[data-role=btnRedo]').onclick = () => this.redo();
    this._q('[data-role=btnAddSupport]').onclick = () => { if (this.mode !== 'support') return; this.armed = 'addSupport'; this.addSupportPt = null; this.setHint('Klik titik ke-1 support…'); };
    this._q('[data-role=btnAddBox]').onclick = () => {
      if (this.mode !== 'bentuk') return;
      this.armed = 'addBox';
      this.boxPreview = { sisiIdx: null, offset: 0, span: 100, depthMag: 100, depthSign: 1 };
      this.setHint('Ketuk sisi lurus tempat kotak mau nempel.');
      this.renderBoxPanel();
    };

    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this.render(); };
    this._q('[data-role=inKotak]').oninput = e => { this.S.kotak = Math.max(1, +e.target.value) || this.S.kotak; this.S.autoKotak = false; this.render(); };
    this._q('[data-role=inGrid]').onchange = e => { this.S.grid = +e.target.value; this.render(); };
    this._q('[data-role=inT]').oninput = e => { this.S.tinggi = +e.target.value || 300; this.render(); };
    this._q('[data-role=inL]').oninput = () => this.updSaranHint();
    this._q('[data-role=inL]').onchange = () => this.resizeBox();
    this._q('[data-role=inP]').onchange = () => this.resizeBox();
    this._q('[data-role=btnSaran]').onclick = () => this.applySaran();
    this._q('[data-role=btnReset]').onclick = () => this.resetBox();

    const matKeys = { matFrame: 'frame', matSupport: 'support', matTiang: 'tiang' };
    Object.keys(matKeys).forEach(role => {
      const sel = this._q(`[data-role=${role}]`);
      sel.onchange = e => { this.pushUndo(); this.S.matDefault[matKeys[role]] = e.target.value; this.render(); };
    });

    this._q('[data-role=matApply]').onclick = () => {
      if (this.menuId) { this.pushUndo(); this.S.matOverride[this.menuId] = this._q('[data-role=matPick]').value; this._q('[data-role=matMenu]').style.display = 'none'; this.render(); }
    };
    this._q('[data-role=matClear]').onclick = () => {
      if (this.menuId) { this.pushUndo(); delete this.S.matOverride[this.menuId]; this._q('[data-role=matMenu]').style.display = 'none'; this.render(); }
    };
    this._q('[data-role=matCancel]').onclick = () => { this._q('[data-role=matMenu]').style.display = 'none'; };
    this._docPointerDown = (e) => {
      const menu = this._q('[data-role=matMenu]');
      const canvas = this._q('.de-canvas');
      if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !(canvas && canvas.contains(e.target))) menu.style.display = 'none';
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
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) { closeRibbon(); return; }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
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
    const canvasEl = () => this._q('.de-canvas');
    const pointers = new Map();
    let pinch = null; // { startDist, startScale, startMidLocal, startTx, startTy }
    let lastTapTime = 0, lastTapX = 0, lastTapY = 0;

    const applyTransform = () => {
      const c = canvasEl();
      if (c) c.style.transform = `translate(${this.zoomTx}px, ${this.zoomTy}px) scale(${this.zoomScale})`;
      resetBtn.classList.toggle('show', Math.abs(this.zoomScale - 1) > 0.01 || Math.abs(this.zoomTx) > 0.5 || Math.abs(this.zoomTy) > 0.5);
    };
    const resetZoom = () => {
      const c = canvasEl();
      if (c) { c.style.transition = 'transform .2s ease'; setTimeout(() => { if (c) c.style.transition = ''; }, 220); }
      this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
      applyTransform();
    };
    this._resetZoom = resetZoom;
    resetBtn.onclick = resetZoom;

    const dist2 = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const mid2 = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

    wrap.addEventListener('pointerdown', e => {
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pointers.size === 2) {
        // jari ke-2 ini mungkin persis di atas vertex/support/box lain — cegah
        // bindSvg() (bubble-phase, listener di svg) memproses pointerdown ini
        // sebagai drag baru sebelum pinch dikenali (lihat fix Finding 2, task-4 review)
        e.stopPropagation();
        // batalkan drag 1-jari yg mungkin lagi jalan (vertex/support/box) di bindSvg
        const svg = canvasEl().querySelector('svg');
        if (svg) svg.dispatchEvent(new PointerEvent('pointercancel'));
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
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
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

  setHint(extra) {
    const HINTS = {
      bentuk: 'Mode Bentuk: seret bulatan sudut. Ketuk sisi frame untuk ketik panjang cm. "+ Sudut"/"− Sudut" untuk L/lekuk.',
      besi: 'Mode Ganti besi: klik batang/tiang di denah → pilih besi (atau balik ke default).',
      support: 'Mode Support: klik garis support untuk hapus/kembalikan. "Tambah manual" untuk gawang/WF melintang.',
      tiang: 'Mode Tiang: klik untuk taruh tiang (snap grid). Klik tiang lagi untuk hapus.',
    };
    this._q('[data-role=hint]').textContent = extra || HINTS[this.mode];
  }

  // ---- Undo ----
  // pushUndo() dipanggil sebelum SETIAP mutasi (satu titik terpusat) — cabang baru selalu
  // membuang riwayat redo lama, sama seperti undo/redo di editor pada umumnya.
  pushUndo() { this.undoStack.push(JSON.stringify(this.S)); if (this.undoStack.length > 40) this.undoStack.shift(); this.redoStack = []; }
  undo() {
    this.armed = null; this.boxPreview = null;
    if (!this.undoStack.length) { this.setHint('Tak ada langkah untuk di-undo'); return; }
    this.redoStack.push(JSON.stringify(this.S)); if (this.redoStack.length > 40) this.redoStack.shift();
    Object.assign(this.S, JSON.parse(this.undoStack.pop()));
    this.syncInputs();
    this.render();
  }
  redo() {
    this.armed = null; this.boxPreview = null;
    if (!this.redoStack.length) { this.setHint('Tak ada langkah untuk di-redo'); return; }
    this.undoStack.push(JSON.stringify(this.S)); if (this.undoStack.length > 40) this.undoStack.shift();
    Object.assign(this.S, JSON.parse(this.redoStack.pop()));
    this.syncInputs();
    this.render();
  }
  syncInputs() {
    this._q('[data-role=inArah]').value = this.S.arah;
    this._q('[data-role=inKotak]').value = this.S.kotak;
    this._q('[data-role=inGrid]').value = this.S.grid;
    this._q('[data-role=inT]').value = this.S.tinggi;
    this._q('[data-role=matFrame]').value = this.S.matDefault.frame;
    this._q('[data-role=matSupport]').value = this.S.matDefault.support;
    this._q('[data-role=matTiang]').value = this.S.matDefault.tiang;
    this.syncLP();
  }

  // ---- Kotak saran (pakai DenahConv.saranKotak — Task 1) ----
  saranKotak() {
    const L = +(this._q('[data-role=inL]').value) || 0;
    if (L <= 0) return this.S.kotak;
    return DenahConv.saranKotak(L, this.S.target);
  }
  applySaran() {
    this.S.autoKotak = true;
    this.S.kotak = this.saranKotak();
    this._q('[data-role=inKotak]').value = this.S.kotak;
    this.updSaranHint();
    this.render();
  }
  updSaranHint() {
    const sug = this.saranKotak();
    const L = +(this._q('[data-role=inL]').value) || 0;
    this._q('[data-role=saranHint]').textContent = `saran ~${this.S.target}cm → ${sug}cm (${Math.max(1, Math.round(L / this.S.target))} bagian simetris)`;
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
    if (this.S.autoKotak) this.S.kotak = DenahConv.saranKotak(L, this.S.target);
    this.render();
  }

  resetBox() {
    this.armed = null; this.boxPreview = null;
    this.undoStack = []; this.redoStack = [];
    const L = +(this._q('[data-role=inL]').value) || 400;
    const P = +(this._q('[data-role=inP]').value) || 300;
    this.S.verts = [{ x: 0, y: 0 }, { x: L, y: 0 }, { x: L, y: P }, { x: 0, y: P }];
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
  typeSide(i) {
    const n = this.S.verts.length, a = this.S.verts[i], b = this.S.verts[(i + 1) % n];
    const val = prompt(`Panjang sisi F${i + 1} (cm):`, Math.round(dist(a, b) * 10) / 10);
    if (val != null) this.setSideLength(i, val);
  }
  // Panel input sisi (mudah di HP — tak perlu tap garis)
  renderSides(mem) {
    const fr = mem.filter(m => m.jenis === 'frame');
    const panel = this._q('[data-role=sisiPanel]');
    panel.innerHTML =
      '<b style="width:100%;font-size:12px;color:#334155">Ukur sisi (cm) — ketik angka pasti:</b>' +
      fr.map((m, i) => `<label style="font-size:11px;color:#475569">F${i + 1}<input type="text" inputmode="decimal" value="${m.panjang}" data-side="${i}" style="width:66px;margin-left:4px;padding:6px 6px;border:1px solid #cbd5e1;border-radius:6px"></label>`).join('');
    panel.querySelectorAll('input').forEach(inp => inp.onchange = e => this.setSideLength(+e.target.dataset.side, e.target.value));
  }

  openMatMenu(evt, id) {
    this.menuId = id;
    const cur = this.S.matOverride[id] || '';
    const pick = this._q('[data-role=matPick]');
    pick.value = cur || (id[0] === 'F' ? this.S.matDefault.frame : id[0] === 'T' ? this.S.matDefault.tiang : this.S.matDefault.support);
    // Label biar jelas batang mana yang barusan diketuk (F3/S5/T2 — nomor SAMA seperti yang
    // tertulis di kanvas, bukan id internal), biar user yakin ini batang yang benar sebelum ganti.
    const mem = this.getMembers();
    const jenisNama = { frame: 'Frame', support: 'Support', tiang: 'Tiang' };
    const m = mem.find(x => x.id === id);
    let label = id;
    if (m) {
      // frame/tiang: m.nama sudah "F3"/"T2". support: nomor dihitung ulang sesuai urutan render
      // (nama mentah cuma "S" generik, bukan bernomor).
      const code = m.jenis === 'support' ? 'S' + (mem.filter(x => x.jenis === 'support').findIndex(x => x.id === id) + 1) : m.nama;
      label = `${jenisNama[m.jenis]} ${code} · ${m.panjang}cm`;
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

  // Terapkan: panggil combineBox murni (Task 1); kalau valid ganti S.verts, kalau tidak kasih hint & tetap di preview.
  applyBoxPreview() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const len = Math.hypot(b.x - a.x, b.y - a.y);
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const result = DenahConv.combineBox(this.S.verts, bp.sisiIdx, off, bp.span, bp.depthMag * bp.depthSign);
    if (!result) { this.setHint('Kotak tidak valid di posisi ini — geser lagi atau kecilkan ukurannya.'); return; }
    this.pushUndo();
    // Catat vertex mana yg jadi 1 kotak (buat drag-kotak-utuh, Task 6) — jumlah vertex baru yg
    // disisipkan sama persis logika `seq` di combineBox: offset>0 -> p1 baru, offset+span<len -> p2 baru,
    // p4+p3 selalu ada.
    const boxStart = bp.sisiIdx + 1;
    let count = 2;
    if (off > 1e-6) count++;
    if (off + bp.span < len - 1e-6) count++;
    this.S.combinedBoxes = DenahConv.shiftBoxesInsert(this.S.combinedBoxes, boxStart, count);
    this.S.combinedBoxes.push({ verts: Array.from({ length: count }, (_, k) => boxStart + k) });
    this.S.verts = result;
    this.armed = null; this.boxPreview = null;
    this.setHint();
    this.syncLP();
    this.render();
  }

  // ---- Render SVG ----
  render() {
    const S = this.S;
    const mem = DenahConv.buildMembers(S);
    const cmap = colorMap(mem);
    const bb = bbox(S.verts);
    const inLraw = +(this._q('[data-role=inL]').value) || 0;
    const inPraw = +(this._q('[data-role=inP]').value) || 0;
    const domW = Math.max(bb.x1, inLraw) * 1.12 + 20, domH = Math.max(bb.y1, inPraw) * 1.12 + 20;
    this.SC = Math.min(560 / domW, 400 / domH); if (!isFinite(this.SC) || this.SC <= 0) this.SC = 0.5;
    const PAD = this.PAD;
    const W = domW * this.SC + PAD * 2, H = domH * this.SC + PAD * 2;
    const X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    const gpx = S.grid * this.SC;
    let s = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">`;
    const gid = 'grid-' + this.uid;
    s += `<defs><pattern id="${gid}" width="${gpx}" height="${gpx}" patternUnits="userSpaceOnUse" x="${PAD}" y="${PAD}"><path d="M ${gpx} 0 L 0 0 0 ${gpx}" fill="none" stroke="#1e3a5f" stroke-width="0.5"/></pattern></defs>`;
    s += `<rect x="0" y="0" width="${W}" height="${H}" fill="#0f2740"/>`;
    s += `<rect x="${PAD}" y="${PAD}" width="${domW * this.SC}" height="${domH * this.SC}" fill="url(#${gid})"/>`;
    // support (grup — diredupkan saat seret sudut). Support manual dapat titik ujung yang bisa digeser.
    s += '<g id="supLayer">';
    mem.filter(m => m.jenis === 'support').forEach((m, i) => { const c = cmap[m.material]; const manual = m.id.startsWith('Sm_');
      const mx = (m.geom.a.x + m.geom.b.x) / 2, my = (m.geom.a.y + m.geom.b.y) / 2;
      // garis tampak (tanpa event) + garis transparan lebar (target ketuk) + label S{n}·panjang
      s += `<line ${manual ? `id="sm${m.id.slice(3)}"` : ''} x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="${c}" stroke-width="${manual ? 3 : 2}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="transparent" stroke-width="14" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      s += `<text ${manual ? `id="smlbl${m.id.slice(3)}"` : ''} x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">S${i + 1} · ${m.panjang}</text>`; });
    if (this.mode === 'support') mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_')).forEach(m => { const i = m.id.slice(3);
      ['a', 'b'].forEach(end => { const p = m.geom[end], cx = X(p.x), cy = Y(p.y);
        s += `<circle cx="${cx}" cy="${cy}" r="22" fill="transparent" data-sm="${i}" data-end="${end}" class="smhit" style="cursor:grab"/>`;
        s += `<circle id="smh${i}${end}" cx="${cx}" cy="${cy}" r="4" fill="#0f2740" stroke="#38bdf8" stroke-width="2.5" style="pointer-events:none"/>`; }); });
    s += '</g>';
    // frame (tebal) + label sisi — tiap sisi id fl{i}/fll{i} biar bisa diupdate saat seret
    mem.filter(m => m.jenis === 'frame').forEach((m, i) => { const c = cmap[m.material]; const a = m.geom.a, b = m.geom.b;
      s += `<line id="fl${i}" x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="${c}" stroke-width="5" stroke-linecap="round"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="transparent" stroke-width="16" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      const mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="13" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
    // kotak-support (Gabungan Kotak): hit-area transparan per kotak buat drag-kotak-utuh. Dirender
    // SEBELUM titik sudut (di bawah ini) supaya tap TEPAT di titik sudut tetap prioritas drag-sudut
    // biasa (SVG: elemen belakangan di markup ada di atas utk hit-testing).
    (S.combinedBoxes || []).forEach((bx, k) => {
      const pts = bx.verts.map(i => S.verts[i]).filter(Boolean);
      if (pts.length !== bx.verts.length) return; // index rusak (harusnya sudah tersaring reindex Task 5)
      s += `<polygon points="${pts.map(p => `${X(p.x)},${Y(p.y)}`).join(' ')}" fill="transparent" data-boxgroup="${k}" style="cursor:grab"/>`;
    });
    // tiang
    mem.filter(m => m.jenis === 'tiang').forEach((m, i) => { const c = cmap[m.material]; const p = m.geom.p;
      s += `<circle id="tc${i}" cx="${X(p.x)}" cy="${Y(p.y)}" r="6" fill="${c}" stroke="#0f2740" stroke-width="1.5" data-id="${m.id}" class="hit"><title>Tiang ${m.material} • ${m.panjang}cm</title></circle>`;
      s += `<text id="tl${i}" x="${X(p.x) + 9}" y="${Y(p.y) + 4}" fill="#fbbf24" font-size="10" paint-order="stroke" stroke="#0f2740" stroke-width="3">T${i + 1}</text>`; });
    // vertex: hit-area besar transparan (mudah ditekan di HP) + bulatan tampak (tak makan event)
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle id="vhit${i}" cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="5" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
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
    this._q('[data-role=luas]').textContent = (shoelace(S.verts) / 10000).toFixed(2) + ' m²';
    this.renderSides(mem);
    this.renderBoxPanel();
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
          this.armed = null; this.setHint(); this.render(); return; }
        if (this.armed === 'addV' && t.dataset.id && t.dataset.id.startsWith('F')) {
          this.pushUndo(); const i = +t.dataset.id.slice(1);
          this.S.verts.splice(i + 1, 0, { x: this.snap(cm.x), y: this.snap(cm.y) });
          this.S.combinedBoxes = DenahConv.shiftBoxesInsert(this.S.combinedBoxes, i + 1, 1);
          this.armed = null; this.setHint(); this.render(); return; }
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
        const hit = this.S.tiang.findIndex(p => dist(p, cm) < this.S.grid * 1.5);
        this.pushUndo();
        if (hit >= 0) {
          // Tunggu ada gerakan jari dulu (lihat pointermove) sebelum diputuskan drag-pindah atau
          // tap-hapus (perilaku lama) — keduanya mulai dari gestur yang sama.
          drag = { type: 'tiang', i: hit, startPt: cm, moved: false,
            tc: el.querySelector('#tc' + hit), tl: el.querySelector('#tl' + hit) };
          el.setPointerCapture(e.pointerId); e.preventDefault();
        } else {
          this.S.tiang.push({ x: this.snap(cm.x), y: this.snap(cm.y) });
          this.render();
        }
      } else if (this.mode === 'support') {
        if (t.dataset.sm != null) {
          this.pushUndo();
          const i = +t.dataset.sm, end = t.dataset.end;
          drag = { type: 'sup', i, end, hit: t, h: el.querySelector('#smh' + i + end), line: el.querySelector('#sm' + i) };
          el.setPointerCapture(e.pointerId); e.preventDefault(); return;
        }
        if (this.armed === 'addSupport') {
          if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
          else { this.pushUndo(); this.S.supportsManual.push({ a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) } }); this.addSupportPt = null; this.armed = null; this.setHint(); this.render(); }
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('Sm_')) {
          const i = +t.dataset.id.slice(3);
          const m = this.S.supportsManual[i];
          this.pushUndo();
          // Tunggu ada gerakan dulu sebelum diputuskan drag-pindah-garis-utuh atau tap-hapus
          // (perilaku lama) — sama pola dgn tiang di Task 3.
          drag = { type: 'supline', i, startPt: cm, moved: false,
            startA: { ...m.a }, startB: { ...m.b },
            line: el.querySelector('#sm' + i), hit: t, lbl: el.querySelector('#smlbl' + i),
            ha: el.querySelector('#smh' + i + 'a'), hb: el.querySelector('#smh' + i + 'b'),
            hita: el.querySelector('[data-sm="' + i + '"][data-end="a"]'),
            hitb: el.querySelector('[data-sm="' + i + '"][data-end="b"]') };
          el.setPointerCapture(e.pointerId); e.preventDefault(); return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          this.pushUndo(); const id = t.dataset.id;
          this.S.removed[id] = !this.S.removed[id];
          this.render();
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
            elx.setAttribute('x', X((a.x + b.x) / 2)); elx.setAttribute('y', Y((a.y + b.y) / 2) - 5);
            elx.textContent = 'F' + (i + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10); };
          upLbl(drag.tPrev, (vi - 1 + n) % n); upLbl(drag.tThis, vi); this.syncLP();
        } else if (drag.type === 'sup') {
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
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'tiang', i: drag.i });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap(cm, candidates, TH);
          this.S.tiang[drag.i] = { x: snap.x, y: snap.y };
          this._lastGuides = snap.guides;
          const px2 = PAD + snap.x * this.SC, py2 = PAD + snap.y * this.SC;
          if (drag.tc) { drag.tc.setAttribute('cx', px2); drag.tc.setAttribute('cy', py2); }
          if (drag.tl) { drag.tl.setAttribute('x', px2 + 9); drag.tl.setAttribute('y', py2 + 4); }
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'supline') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
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
          if (drag.lbl) { drag.lbl.setAttribute('x', X((a.x + b.x) / 2)); drag.lbl.setAttribute('y', Y((a.y + b.y) / 2) - 4); }
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
              drag.fll[idx].setAttribute('x', X((a.x + b.x) / 2)); drag.fll[idx].setAttribute('y', Y((a.y + b.y) / 2) - 5);
              drag.fll[idx].textContent = 'F' + (si + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10);
            }
          });
          if (drag.poly) drag.poly.setAttribute('points', drag.vertIdx.map(vi => `${X(this.S.verts[vi].x)},${Y(this.S.verts[vi].y)}`).join(' '));
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
    const end = () => { if (!drag) return;
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
      else if (drag.type === 'box') { this.boxPreview.offset = this.snap(this.boxPreview.offset); }
      else if (drag.type === 'tiang') {
        if (!drag.moved) {
          // Tak gerak = perilaku lama (tap tiang = hapus). Posisi belum diubah krn moved masih false.
          this.S.tiang.splice(drag.i, 1);
        } else {
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
        this._hideAlignGuides();
      }
      else if (drag.type === 'supline') {
        if (!drag.moved) {
          this.S.supportsManual.splice(drag.i, 1);
        } else {
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
      drag = null; this.render(); };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
  }

  // ---- API publik (dipakai Task 3) ----
  getModel() { return JSON.parse(JSON.stringify(this.S)); }
  getMembers() { return DenahConv.buildMembers(this.S); }
  getLuas() { return DenahConv.luasM2(this.S); }
  setModel(m) { this.armed = null; this.boxPreview = null; this.S = JSON.parse(JSON.stringify(m)); this.syncInputs(); this.render(); }
}

// ---- self-check ringkas, browser-only (guard: tak jalan di produksi/Node) ----
if (globalThis.__DENAH_SELFCHECK) {
  try {
    const div = document.createElement('div');
    const ed = new DenahEditor(div, { besi: [{ nama: 'Hollow 5x10', harga: 120000 }] });
    ed.setModel({
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 400 }, { x: 0, y: 400 }],
      grid: 20, target: 100, kotak: 100, autoKotak: true, arah: 'h',
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
