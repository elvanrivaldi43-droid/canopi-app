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
  _dist: dist, _bbox: bbox,
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
    this.undoStack = [];
    this.mode = 'bentuk';
    this.armed = null;      // 'addV' | 'delV' | 'addSupport'
    this.addSupportPt = null;
    this.menuId = null;
    this.SC = 1;
    this.PAD = 44;
    this.uid = ++DenahEditor._n;   // id unik per instance (pattern grid dirujuk url(#..) yg resolve se-dokumen)

    this.el.innerHTML = DenahEditor.shellHTML();
    this._fillMatSelects();
    this._wireControls();
    this.syncInputs();
    this.render();
  }

  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20, target: 100,
      kotak: 100, autoKotak: true, arah: '2', supportsManual: [], removed: {}, tiang: [],
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '' }, matOverride: {},
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
.de-tools{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}
.de-tool{padding:7px 11px;border:1px solid #334155;background:#fff;border-radius:8px;font-size:13px;cursor:pointer;user-select:none}
.de-tool.on{background:#1e293b;color:#fff}
.de-mini{padding:6px 9px;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:12px;cursor:pointer;display:inline-block}
.de-hint{font-size:12px;color:#64748b;margin:6px 2px;min-height:16px}
.de-canvas{background:#0f2740;border-radius:10px;padding:6px;overflow:auto}
.de-canvas svg{max-width:100%;touch-action:none;display:block}
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
  <div class="de-row">
    <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
    <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
    <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
    <label>Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
    <span class="de-mini" data-role="btnReset">Reset kotak dari Lebar×Panjang</span>
  </div>
  <div class="de-row" style="margin-top:8px">
    <label>Arah support
      <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
    </label>
    <label>Kotak support (cm)<input type="number" data-role="inKotak" value="100" step="5" min="1"></label>
    <span class="de-mini" data-role="btnSaran">Pakai saran</span>
    <span class="de-hint" data-role="saranHint"></span>
  </div>
  <div class="de-matbar">
    <label>Besi frame<select data-role="matFrame"></select></label>
    <label>Besi support<select data-role="matSupport"></select></label>
    <label>Besi tiang<select data-role="matTiang"></select></label>
  </div>
  <div class="de-tools">
    <span class="de-tool on" data-mode="bentuk">Bentuk</span>
    <span class="de-tool" data-mode="besi">Ganti besi</span>
    <span class="de-tool" data-mode="support">Support</span>
    <span class="de-tool" data-mode="tiang">Tiang</span>
    <span style="width:10px"></span>
    <span class="de-mini" data-role="btnAddV">+ Sudut</span>
    <span class="de-mini" data-role="btnDelV">− Sudut</span>
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnAddSupport" style="margin-left:6px">+ Support manual</span>
  </div>
  <div class="de-hint" data-role="hint">Mode Bentuk: seret bulatan sudut untuk mengubah bentuk. Ketuk angka cm di sisi untuk ketik panjang pasti.</div>
  <div class="de-canvas"></div>
  <div class="de-legend" data-role="legend"></div>
  <div style="font-size:12px;color:#64748b;margin-top:4px">Luas denah: <b data-role="luas">–</b></div>
  <div class="de-legend" data-role="sisiPanel" style="margin-top:10px"></div>
</div>
<div class="de-matmenu" data-role="matMenu">
  <select data-role="matPick"></select>
  <div class="de-mrow"><span class="de-mini" data-role="matApply">Ganti</span><span class="de-mini" data-role="matClear">Pakai default</span></div>
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
      this.armed = null; this.addSupportPt = null;
      this.setHint();
      this.render();
    });
    this._q('[data-role=btnAddV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'addV'; this.setHint('Klik sisi frame untuk sisipkan sudut baru.'); };
    this._q('[data-role=btnDelV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'delV'; this.setHint('Klik sudut untuk menghapus (min 3 sudut).'); };
    this._q('[data-role=btnUndo]').onclick = () => this.undo();
    this._q('[data-role=btnAddSupport]').onclick = () => { if (this.mode !== 'support') return; this.armed = 'addSupport'; this.addSupportPt = null; this.setHint('Klik titik ke-1 support…'); };

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
    this._docPointerDown = (e) => {
      const menu = this._q('[data-role=matMenu]');
      const canvas = this._q('.de-canvas');
      if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !(canvas && canvas.contains(e.target))) menu.style.display = 'none';
    };
    document.addEventListener('pointerdown', this._docPointerDown);
  }

  // lepas listener document saat instance dibuang (blok di-hapus/off di RAB opsi)
  destroy() {
    if (this._docPointerDown) document.removeEventListener('pointerdown', this._docPointerDown);
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
  pushUndo() { this.undoStack.push(JSON.stringify(this.S)); if (this.undoStack.length > 40) this.undoStack.shift(); }
  undo() {
    if (!this.undoStack.length) { this.setHint('Tak ada langkah untuk di-undo'); return; }
    Object.assign(this.S, JSON.parse(this.undoStack.pop()));
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
    this.undoStack = [];
    const L = +(this._q('[data-role=inL]').value) || 400;
    const P = +(this._q('[data-role=inP]').value) || 300;
    this.S.verts = [{ x: 0, y: 0 }, { x: L, y: 0 }, { x: L, y: P }, { x: 0, y: P }];
    this.S.removed = {}; this.S.supportsManual = []; this.S.matOverride = {};
    this.S.tiang = [];   // JANGAN auto-taruh tiang di sudut — user yang tentukan tiang (mode Tiang)
    this.S.grid = +(this._q('[data-role=inGrid]').value);
    this.S.tinggi = +(this._q('[data-role=inT]').value);
    this.S.arah = this._q('[data-role=inArah]').value;
    this.applySaran();
  }

  // screen→cm
  toCm(evt, el) {
    const pt = el.createSVGPoint(); pt.x = evt.clientX; pt.y = evt.clientY;
    const m = el.getScreenCTM().inverse();
    const p = pt.matrixTransform(m);
    return { x: (p.x - this.PAD) / this.SC, y: (p.y - this.PAD) / this.SC };
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
    const menu = this._q('[data-role=matMenu]');
    menu.style.left = (evt.clientX + 6) + 'px';
    menu.style.top = (evt.clientY + 6) + 'px';
    menu.style.display = 'block';
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
      s += `<text x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">S${i + 1} · ${m.panjang}</text>`; });
    if (this.mode === 'support') mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_')).forEach(m => { const i = m.id.slice(3);
      ['a', 'b'].forEach(end => { const p = m.geom[end], cx = X(p.x), cy = Y(p.y);
        s += `<circle cx="${cx}" cy="${cy}" r="22" fill="transparent" data-sm="${i}" data-end="${end}" class="smhit" style="cursor:grab"/>`;
        s += `<circle id="smh${i}${end}" cx="${cx}" cy="${cy}" r="8" fill="#0f2740" stroke="#38bdf8" stroke-width="2.5" style="pointer-events:none"/>`; }); });
    s += '</g>';
    // frame (tebal) + label sisi — tiap sisi id fl{i}/fll{i} biar bisa diupdate saat seret
    mem.filter(m => m.jenis === 'frame').forEach((m, i) => { const c = cmap[m.material]; const a = m.geom.a, b = m.geom.b;
      s += `<line id="fl${i}" x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="${c}" stroke-width="4" stroke-linecap="round"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="transparent" stroke-width="16" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      const mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="10" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
    // tiang
    mem.filter(m => m.jenis === 'tiang').forEach((m, i) => { const c = cmap[m.material]; const p = m.geom.p;
      s += `<circle cx="${X(p.x)}" cy="${Y(p.y)}" r="6" fill="${c}" stroke="#0f2740" stroke-width="1.5" data-id="${m.id}" class="hit"><title>Tiang ${m.material} • ${m.panjang}cm</title></circle>`;
      s += `<text x="${X(p.x) + 9}" y="${Y(p.y) + 4}" fill="#fbbf24" font-size="10" paint-order="stroke" stroke="#0f2740" stroke-width="3">T${i + 1}</text>`; });
    // vertex: hit-area besar transparan (mudah ditekan di HP) + bulatan tampak (tak makan event)
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="10" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
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
    this._changed();
  }

  bindSvg(el) {
    let drag = null, raf = 0, pend = null;
    const PAD = this.PAD;
    const X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    el.addEventListener('pointerdown', e => {
      const t = e.target; const cm = this.toCm(e, el);
      if (this.mode === 'bentuk') {
        if (this.armed === 'delV' && t.dataset.vert != null) { if (this.S.verts.length > 3) { this.pushUndo(); this.S.verts.splice(+t.dataset.vert, 1); } this.armed = null; this.setHint(); this.render(); return; }
        if (this.armed === 'addV' && t.dataset.id && t.dataset.id.startsWith('F')) {
          this.pushUndo(); const i = +t.dataset.id.slice(1); this.S.verts.splice(i + 1, 0, { x: this.snap(cm.x), y: this.snap(cm.y) }); this.armed = null; this.setHint(); this.render(); return; }
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
        this.pushUndo();
        const hit = this.S.tiang.findIndex(p => dist(p, cm) < this.S.grid * 1.5);
        if (hit >= 0) this.S.tiang.splice(hit, 1); else this.S.tiang.push({ x: this.snap(cm.x), y: this.snap(cm.y) });
        this.render();
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
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          this.pushUndo(); const id = t.dataset.id;
          if (id.startsWith('Sm_')) { this.S.supportsManual.splice(+id.slice(3), 1); }
          else { this.S.removed[id] = !this.S.removed[id]; }
          this.render();
        }
      } else if (this.mode === 'besi') {
        if (t.dataset.id) this.openMatMenu(e, t.dataset.id);
      }
    });
    // Seret mulus: hanya ubah atribut node terkait, TANPA render ulang. Ikuti jari (tak snap); snap saat lepas.
    el.addEventListener('pointermove', e => {
      if (!drag) return; pend = this.toCm(e, el); if (raf) return;
      raf = requestAnimationFrame(() => { raf = 0; const cm = pend, px = PAD + cm.x * this.SC, py = PAD + cm.y * this.SC;
        if (drag.type === 'vert') { const vi = drag.vi, n = this.S.verts.length;
          this.S.verts[vi] = { x: cm.x, y: cm.y };
          drag.vh.setAttribute('cx', px); drag.vh.setAttribute('cy', py);
          drag.vhit.setAttribute('cx', px); drag.vhit.setAttribute('cy', py);
          if (drag.lPrev) { drag.lPrev.setAttribute('x2', px); drag.lPrev.setAttribute('y2', py); }
          if (drag.lThis) { drag.lThis.setAttribute('x1', px); drag.lThis.setAttribute('y1', py); }
          const upLbl = (elx, i) => { if (!elx) return; const a = this.S.verts[i], b = this.S.verts[(i + 1) % n];
            elx.setAttribute('x', X((a.x + b.x) / 2)); elx.setAttribute('y', Y((a.y + b.y) / 2) - 5);
            elx.textContent = 'F' + (i + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10); };
          upLbl(drag.tPrev, (vi - 1 + n) % n); upLbl(drag.tThis, vi); this.syncLP();
        } else if (drag.type === 'sup') {
          this.S.supportsManual[drag.i][drag.end] = { x: cm.x, y: cm.y };
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py);
          drag.h.setAttribute('cx', px); drag.h.setAttribute('cy', py);
          drag.hit.setAttribute('cx', px); drag.hit.setAttribute('cy', py);
        }
      });
    });
    const end = () => { if (!drag) return;
      if (drag.type === 'vert') { const vi = drag.vi; this.S.verts[vi] = { x: this.snap(this.S.verts[vi].x), y: this.snap(this.S.verts[vi].y) }; }
      else if (drag.type === 'sup') { const p = this.S.supportsManual[drag.i][drag.end]; this.S.supportsManual[drag.i][drag.end] = { x: this.snap(p.x), y: this.snap(p.y) }; }
      drag = null; if (raf) { cancelAnimationFrame(raf); raf = 0; } this.render(); };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
  }

  // ---- API publik (dipakai Task 3) ----
  getModel() { return JSON.parse(JSON.stringify(this.S)); }
  getMembers() { return DenahConv.buildMembers(this.S); }
  getLuas() { return DenahConv.luasM2(this.S); }
  setModel(m) { this.S = JSON.parse(JSON.stringify(m)); this.syncInputs(); this.render(); }
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
