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

const DenahConv = {
  buildMembers(S) {
    const mem = [], V = S.verts, bb = bbox(V), K = S.kotak, rem = S.removed || {};
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

export { DenahConv };

// For browser <script> tag
if (typeof window !== 'undefined') window.DenahConv = DenahConv;
