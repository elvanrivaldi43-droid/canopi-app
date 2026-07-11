<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RabProduk;
use App\Models\RabZonaBentangan;
use App\Models\RabAtap;
use App\Models\RabAddon;
use App\Models\RabKondisiLokasi;
use App\Models\RabKatalog;
use App\Models\RabMarginSetting;
use App\Models\RabHeader;
use App\Models\RabApprovalRequest;
use App\Models\RabTtd;
use App\Models\PipelineLead;
use App\Models\Project;
use App\Services\RabKalkulasiService;

class RabController extends Controller
{
    protected RabKalkulasiService $svc;

    public function __construct()
    {
        $this->svc = new RabKalkulasiService();
    }

    // ============================================================
    // WIZARD — Halaman utama builder
    // ============================================================

    // Step awal: pilih jalur (dari pipeline lead atau standalone)
    public function create(Request $request)
    {
        $leadId = $request->lead_id;
        $lead = $leadId ? PipelineLead::find($leadId) : null;
        $produk = RabProduk::aktif();

        return view('rab.wizard', compact('lead','produk'));
    }

    // API: ambil katalog foto berdasarkan produk
    public function apiKatalog(Request $request)
    {
        $produkKode = $request->produk_kode ?? 'KANOPI_STD';
        $katalog = RabKatalog::byProduk($produkKode);
        return response()->json($katalog);
    }

    // API: ambil paket konstruksi berdasarkan bentangan
    public function apiPaketKonstruksi(Request $request)
    {
        $bentangan = (float)($request->bentangan ?? 3);
        $zona = \App\Models\RabZonaBentangan::cariZona($bentangan);
        if (!$zona) return response()->json(['error' => 'Zona tidak ditemukan'], 404);

        $pakets = \App\Models\RabPaketKonstruksi::getByZona($zona->id);
        return response()->json([
            'zona' => $zona,
            'pakets' => $pakets,
        ]);
    }

    // API: ambil daftar atap
    public function apiAtap()
    {
        return response()->json(RabAtap::aktif());
    }

    // API: ambil addon by kategori
    public function apiAddon()
    {
        return response()->json(RabAddon::aktifByKategori());
    }

    // API: ambil kondisi lokasi
    public function apiKondisi()
    {
        return response()->json(RabKondisiLokasi::aktif());
    }

    // API: HITUNG HARGA REAL-TIME (dipanggil setiap perubahan di wizard)
    public function apiHitung(Request $request)
    {
        $input = $request->all();
        try {
            $result = $this->svc->hitungQuickQuote($input);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // Simpan RAB setelah wizard selesai
    public function store(Request $request)
    {
        $request->validate([
            'produk_kode' => 'required|string',
            'panjang'     => 'required|numeric|min:0.1',
            'lebar'       => 'required|numeric|min:0.1',
            'atap_id'     => 'nullable|integer',
            'versi_dipilih'=> 'required|in:Hemat,Standar,Premium',
            'diskon_persen'=> 'nullable|numeric|min:0|max:99',
        ]);

        $input = $request->all();
        $input['addons']   = json_decode($request->addons_json ?? '[]', true);
        $input['kondisis'] = json_decode($request->kondisis_json ?? '[]', true);

        // Cek apakah diskon melebihi batas — simpan dulu dengan flag
        $kalkulasi = $this->svc->hitungQuickQuote($input);
        $margin = RabMarginSetting::byProduk($input['produk_kode']);
        $diskonMax = $margin ? (float)$margin->diskon_max_persen : 15;
        $diskonDiminta = (float)($input['diskon_persen'] ?? 0);

        if ($diskonDiminta > $diskonMax) {
            // Simpan sebagai draft dulu, buat approval request
            $input['diskon_persen'] = $diskonMax; // simpan dengan batas aman dulu
            $rab = $this->svc->simpanRab($input, Auth::id());

            RabApprovalRequest::create([
                'rab_id'               => $rab->id,
                'diminta_oleh'         => Auth::id(),
                'harga_normal'         => $rab->harga_sebelum_diskon,
                'harga_diminta'        => $rab->harga_sebelum_diskon * (1 - $diskonDiminta / 100),
                'diskon_diminta_persen'=> $diskonDiminta,
                'alasan'               => $request->alasan_diskon,
            ]);

            // Kirim notif WA ke owner
            $this->kirimNotifApprovalWa($rab, $diskonDiminta);

            return response()->json([
                'success'  => true,
                'rab_id'   => $rab->id,
                'status'   => 'pending_approval',
                'pesan'    => 'Permintaan diskon tambahan sudah dikirim ke owner. Menunggu persetujuan.'
            ]);
        }

        $rab = $this->svc->simpanRab($input, Auth::id());

        return response()->json([
            'success' => true,
            'rab_id'  => $rab->id,
            'nomor'   => $rab->nomor_rab,
            'harga'   => $rab->harga_final,
            'status'  => 'draft',
        ]);
    }

    // Halaman detail RAB
    public function show(int $id)
    {
        $rab = RabHeader::with(['items','versi','ttd','paketKonstruksi','atap','pembuat','lead'])->findOrFail($id);
        $this->authorizeRab($rab);
        return view('rab.show', compact('rab'));
    }

    // Daftar semua RAB (owner: semua, lainnya: milik sendiri)
    public function index(Request $request)
    {
        $query = RabHeader::with(['pembuat','lead'])
            ->orderByDesc('created_at');

        if (Auth::user()->level > 1) {
            $query->where('dibuat_oleh', Auth::id());
        }

        if ($request->status) $query->where('status', $request->status);
        if ($request->search) {
            $query->whereHas('lead', fn($q) =>
                $q->where('nama_customer', 'like', '%'.$request->search.'%')
            );
        }

        $rabs = $query->paginate(20);
        return view('rab.index', compact('rabs'));
    }

    // ============================================================
    // DEAL — TTD Digital + Buat Project
    // ============================================================

    public function deal(Request $request, int $id)
    {
        $rab = RabHeader::findOrFail($id);
        $request->validate([
            'nama_penandatangan' => 'required|string|max:100',
            'ttd_data'           => 'required|string', // base64
        ]);

        // Simpan TTD
        RabTtd::updateOrCreate(['rab_id' => $rab->id], [
            'nama_penandatangan' => $request->nama_penandatangan,
            'ttd_data'           => $request->ttd_data,
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'lokasi_lat'         => $request->lokasi_lat,
            'lokasi_lng'         => $request->lokasi_lng,
        ]);

        // Update status RAB
        $rab->update(['status' => 'deal']);

        // Update status pipeline lead
        if ($rab->pipeline_lead_id) {
            PipelineLead::where('id', $rab->pipeline_lead_id)
                ->update(['status' => 'deal']);
        }

        // Buat project otomatis (cek duplikat)
        $existingProject = null;
        try {
            $existingProject = Project::where('rab_id', $rab->id)->first();
        } catch (\Exception $e) {
            // kolom rab_id mungkin belum ada, abaikan cek duplikat
        }

        $project = null;
        if (!$existingProject) {
            $lead = $rab->lead;
            $namaCustomer = $lead->nama_customer ?? $request->nama_penandatangan ?? 'Customer';
            $noHp         = $lead->no_hp ?? '';
            $alamat       = $lead->alamat ?? '';
            $namaProduk   = match($rab->produk_kode) {
                'KANOPI_STD'     => 'Kanopi Standar',
                'KANOPI_DINDING' => 'Kanopi + Dinding',
                'MEZZANINE'      => 'Mezzanine',
                'PAGAR'          => 'Pagar',
                'TRALIS'         => 'Tralis',
                'TENDA_MEMBRANE' => 'Tenda Membrane',
                'AWNING'         => 'Awning',
                'CARPORT'        => 'Carport',
                default          => $rab->produk_kode,
            };

            try {
                // Cek kolom apa saja yang ada di tabel projects
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing('projects');

                $data = ['dibuat_oleh' => Auth::id()];

                if (in_array('rab_id', $cols))          $data['rab_id']          = $rab->id;
                if (in_array('nilai_kontrak', $cols))   $data['nilai_kontrak']   = $rab->harga_final;
                if (in_array('kode_project', $cols))    $data['kode_project']    = Project::generateKode();
                if (in_array('jenis_project', $cols))   $data['jenis_project']   = $namaProduk;
                if (in_array('alamat_project', $cols))  $data['alamat_project']  = $alamat;
                if (in_array('nama_project', $cols))   $data['nama_project']  = $namaCustomer . ' - ' . $namaProduk;
                if (in_array('nama_customer', $cols))  $data['nama_customer'] = $namaCustomer;
                if (in_array('no_hp', $cols))          $data['no_hp']         = $noHp;
                if (in_array('alamat', $cols))         $data['alamat']        = $alamat;
                if (in_array('nilai_project', $cols))  $data['nilai_project'] = $rab->harga_final;
                if (in_array('status', $cols))         $data['status']        = 'menunggu_dp';

                $project = Project::create($data);

                // Update rab_header dengan project_id yang baru terbuat
                if ($project) {
                    $rab->update(['project_id' => $project->id]);
                }

            } catch (\Exception $e) {
                // Gagal buat project — log tapi jangan gagalkan deal
                \Illuminate\Support\Facades\Log::warning('Gagal buat project dari RAB: ' . $e->getMessage());
            }
        } else {
            $project = $existingProject;
            // Pastikan rab_header juga punya project_id
            if ($project && !$rab->project_id) {
                $rab->update(['project_id' => $project->id]);
            }
        }

        // Kirim WA info transfer ke customer
        $this->kirimNotifDeal($rab);

        return response()->json([
            'success'    => true,
            'project_id' => $project->id ?? ($existingProject->id ?? null),
            'pesan'      => 'Deal berhasil! Project terbuat. Notifikasi WA dikirim ke customer.'
        ]);
    }

    // ============================================================
    // APPROVAL (Owner only)
    // ============================================================

    public function approvalIndex()
    {
        abort_if(Auth::user()->level != 1, 403);
        $requests = RabApprovalRequest::with(['rab.lead','peminta'])
            ->where('status','pending')
            ->orderByDesc('created_at')
            ->get();
        return view('rab.approval', compact('requests'));
    }

    public function approvalProses(Request $request, int $id)
    {
        abort_if(Auth::user()->level != 1, 403);
        $apr = RabApprovalRequest::findOrFail($id);
        $apr->update([
            'status'         => $request->action, // 'approved' atau 'rejected'
            'diproses_oleh'  => Auth::id(),
            'catatan_owner'  => $request->catatan,
        ]);

        if ($request->action === 'approved') {
            // Update harga RAB dengan diskon yang disetujui
            $rab = $apr->rab;
            $diskon = $apr->diskon_diminta_persen;
            $rab->update([
                'diskon_persen'  => $diskon,
                'diskon_nominal' => $rab->harga_sebelum_diskon * ($diskon / 100),
                'harga_final'    => $rab->harga_sebelum_diskon * (1 - $diskon / 100),
            ]);
        }

        // Notif WA ke surveyor
        $this->kirimNotifHasilApproval($apr);

        return response()->json(['success' => true]);
    }

    // ============================================================
    // OWNER: Switch mode margin (Standar / Target)
    // ============================================================

    public function switchMode(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        $mode = $request->mode; // 'standar' atau 'target'
        RabMarginSetting::switchSemuaMode($mode, Auth::id());
        return response()->json([
            'success' => true,
            'mode'    => $mode,
            'pesan'   => 'Mode harga diubah ke ' . strtoupper($mode)
        ]);
    }

    // ============================================================
    // OWNER: Master setting (produk, margin, addon, katalog)
    // ============================================================

    public function masterIndex()
    {
        abort_if(Auth::user()->level != 1, 403);
        $produk  = RabProduk::orderBy('urutan')->get();
        $margins = RabMarginSetting::all()->keyBy('produk_kode');
        $addons  = RabAddon::orderBy('urutan')->get();
        $katalog = RabKatalog::orderBy('produk_kode')->orderBy('urutan')->get();
        $modeAktif = RabMarginSetting::first()?->mode_aktif ?? 'standar';
        return view('rab.master', compact('produk','margins','addons','katalog','modeAktif'));
    }

    public function masterUpdateMargin(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'produk_kode'           => 'required',
            'margin_min_persen'     => 'required|numeric|min:1|max:99',
            'margin_standar_persen' => 'required|numeric|min:1|max:99',
            'margin_target_persen'  => 'required|numeric|min:1|max:99',
            'diskon_max_persen'     => 'required|numeric|min:0|max:99',
        ]);
        RabMarginSetting::updateOrCreate(
            ['produk_kode' => $request->produk_kode],
            $request->only(['margin_min_persen','margin_standar_persen','margin_target_persen','diskon_max_persen'])
        );
        return response()->json(['success' => true]);
    }

    public function masterUpdateAddon(Request $request, int $id)
    {
        abort_if(Auth::user()->level != 1, 403);
        $addon = RabAddon::findOrFail($id);
        $addon->update($request->only(['nama','harga_satuan','harga_pokok_satuan','is_active']));
        return response()->json(['success' => true]);
    }

    public function masterTambahKatalog(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'produk_kode' => 'required',
            'judul'       => 'required',
            'foto_url'    => 'required|url',
        ]);
        RabKatalog::create($request->only([
            'produk_kode','judul','deskripsi','foto_url','sumber_foto',
            'atap_kode','konstruksi_label','kisaran_harga_min','kisaran_harga_max',
            'tipe_lokasi','tag','urutan'
        ]));
        return response()->json(['success' => true]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function authorizeRab(RabHeader $rab): void
    {
        $user = Auth::user();
        if ($user->level == 1) return; // owner lihat semua
        abort_if($rab->dibuat_oleh != $user->id, 403);
    }

    private function kirimNotifApprovalWa(RabHeader $rab, float $diskon): void
    {
        $owner = \App\Models\User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $pembuat = Auth::user();
        $lead = $rab->lead;
        $pesan = "🔔 *Request Diskon RAB*\n\n"
            . "No RAB: {$rab->nomor_rab}\n"
            . "Customer: " . ($lead->nama_customer ?? '-') . "\n"
            . "Harga Normal: " . $rab->hargaFinalFormatted() . "\n"
            . "Diskon Diminta: {$diskon}%\n"
            . "Harga Diminta: Rp " . number_format($rab->harga_sebelum_diskon * (1 - $diskon/100), 0, ',', '.') . "\n"
            . "Oleh: {$pembuat->name}\n\n"
            . "Approve/tolak di: " . url('/rab/approval');

        $this->kirimWa($owner->no_hp, $pesan);
    }

    private function kirimNotifDeal(RabHeader $rab): void
    {
        $lead = $rab->lead;
        if (!$lead || !$lead->no_hp) return;

        $pesan = "Halo {$lead->nama_customer}, terima kasih sudah mempercayakan kanopi Anda kepada kami!\n\n"
            . "Detail pesanan:\n"
            . "No RAB: {$rab->nomor_rab}\n"
            . "Total: " . $rab->hargaFinalFormatted() . "\n\n"
            . "Silakan transfer DP ke:\n"
            . "Bank BCA: 1234567890\n"
            . "A/N: Pusat Kanopi BSD\n\n"
            . "Setelah transfer, mohon konfirmasi ke kami. Terima kasih!";

        $this->kirimWa($lead->no_hp, $pesan);
    }

    private function kirimNotifHasilApproval(RabApprovalRequest $apr): void
    {
        $peminta = $apr->peminta;
        if (!$peminta || !$peminta->no_hp) return;

        $status = $apr->status === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "Info Request Diskon RAB {$apr->rab->nomor_rab}\n"
            . "Status: {$status}\n"
            . ($apr->catatan_owner ? "Catatan owner: {$apr->catatan_owner}" : '');

        $this->kirimWa($peminta->no_hp, $pesan);
    }

    private function kirimWa(string $noHp, string $pesan): void
    {
        try {
            $token = config('services.fonnte.token');
            if (!$token) return;
            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $token
            ])->post('https://api.fonnte.com/send', [
                'target'  => $noHp,
                'message' => $pesan,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WA RAB gagal: ' . $e->getMessage());
        }
    }

    // ============================================================
    // MASTER SETTING — BULK SAVE
    // ============================================================

    public function masterMarginBulk(Request $request)
    {
        abort_if(auth()->user()->level != 1, 403);
        foreach ($request->data ?? [] as $row) {
            RabMarginSetting::updateOrCreate(
                ['produk_kode' => $row['produk_kode']],
                [
                    'margin_min_persen'     => $row['margin_min_persen'],
                    'margin_standar_persen' => $row['margin_standar_persen'],
                    'margin_target_persen'  => $row['margin_target_persen'],
                    'diskon_max_persen'     => $row['diskon_max_persen'],
                    'updated_by'            => Auth::id(),
                ]
            );
        }
        return response()->json(['success' => true]);
    }

    public function masterPaketBulk(Request $request)
    {
        abort_if(auth()->user()->level != 1, 403);
        foreach ($request->data ?? [] as $row) {
            RabPaketKonstruksi::where('id', $row['id'])->update([
                'label_display'            => $row['label_display'],
                'harga_per_m2_rangka'      => $row['harga_per_m2_rangka'],
                'harga_per_m2_jasa_pasang' => $row['harga_per_m2_jasa_pasang'],
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function masterAddonBulk(Request $request)
    {
        abort_if(auth()->user()->level != 1, 403);
        foreach ($request->data ?? [] as $row) {
            if (str_starts_with((string)($row['id'] ?? ''), 'new_')) {
                RabAddon::create([
                    'kode'               => 'CUSTOM_' . time() . '_' . rand(100,999),
                    'nama'               => $row['nama'],
                    'kategori'           => $row['kategori'],
                    'satuan'             => $row['satuan'],
                    'formula_type'       => 'per_unit',
                    'harga_satuan'       => $row['harga_satuan'],
                    'harga_pokok_satuan' => $row['harga_pokok_satuan'] ?? 0,
                    'is_active'          => $row['is_active'] ?? 1,
                ]);
            } else {
                RabAddon::where('id', $row['id'])->update([
                    'nama'               => $row['nama'],
                    'kategori'           => $row['kategori'],
                    'satuan'             => $row['satuan'],
                    'harga_satuan'       => $row['harga_satuan'],
                    'harga_pokok_satuan' => $row['harga_pokok_satuan'] ?? 0,
                    'is_active'          => $row['is_active'] ?? 1,
                ]);
            }
        }
        return response()->json(['success' => true]);
    }

    public function masterKondisiBulk(Request $request)
    {
        abort_if(auth()->user()->level != 1, 403);
        foreach ($request->data ?? [] as $row) {
            RabKondisiLokasi::where('id', $row['id'])->update([
                'nama'      => $row['nama'],
                'tipe'      => $row['tipe'],
                'nilai'     => $row['nilai'],
                'is_active' => $row['is_active'] ?? 1,
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function masterKatalogHapus(Request $request)
    {
        abort_if(auth()->user()->level != 1, 403);
        RabKatalog::destroy($request->id);
        return response()->json(['success' => true]);
    }

    // ============================================================
    // API MATERIAL STRUKTUR (untuk dropdown BOM di wizard)
    // ============================================================
    public function apiMaterialStruktur()
    {
        try {
            $cols = \Illuminate\Support\Facades\Schema::getColumnListing('master_material');
            $namaCol  = in_array('nama', $cols) ? 'nama' : (in_array('nama_barang', $cols) ? 'nama_barang' : 'nama_material');
            $hargaCol = in_array('harga_pokok', $cols) ? 'harga_pokok' : (in_array('harga', $cols) ? 'harga' : 'harga_satuan');

            $keywords = ['hollow', 'holo', 'HG', 'hg', 'WF', 'wf', 'kaso', 'pipa', 'galvanis', 'besi'];

            $items = \Illuminate\Support\Facades\DB::table('master_material')
                ->where(function ($q) use ($keywords, $namaCol) {
                    foreach ($keywords as $k) {
                        $q->orWhere($namaCol, 'like', "%{$k}%");
                    }
                })
                ->orderBy($namaCol)
                ->limit(60)
                ->get(['id', $namaCol . ' as nama', $hargaCol . ' as harga']);

            return response()->json($items);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    // ============================================================
    // KELOLA MASTER MATERIAL & ATAP (Owner only)
    // ============================================================

    public function kelolaMaterial()
    {
        abort_if(!in_array(Auth::user()->level, [1, 2]), 403);

        $cols      = \Illuminate\Support\Facades\Schema::getColumnListing('master_material');
        $adaSumber = in_array('sumber', $cols);

        $materials = \Illuminate\Support\Facades\DB::table('master_material')
            ->orderBy('kategori')->orderBy('nama')->get();
        $ataps = \Illuminate\Support\Facades\DB::table('rab_atap')
            ->orderBy('urutan')->orderBy('nama')->get();

        $katMaterial = $this->enumValues('master_material', 'kategori');
        $katAtap     = $this->enumValues('rab_atap', 'kategori');
        $beratAtap   = $this->enumValues('rab_atap', 'berat_kategori');

        return view('rab.kelola_material', compact(
            'materials', 'ataps', 'katMaterial', 'katAtap', 'beratAtap', 'adaSumber'
        ));
    }

    private function enumValues(string $table, string $column): array
    {
        try {
            $row = \Illuminate\Support\Facades\DB::select(
                "SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$column]
            );
            if (!$row) return [];
            if (preg_match('/^enum\((.*)\)$/i', $row[0]->Type, $m)) {
                preg_match_all("/'([^']*)'/", $m[1], $vals);
                return $vals[1];
            }
        } catch (\Exception $e) {}
        return [];
    }

    public function materialSimpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        $cols      = \Illuminate\Support\Facades\Schema::getColumnListing('master_material');
        $adaSumber = in_array('sumber', $cols);
        $simpan = 0;

        foreach ($request->data ?? [] as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;

            $payload = [
                'nama'        => $nama,
                'kategori'    => $row['kategori'] ?? 'lainnya',
                'satuan'      => $row['satuan'] ?? 'pcs',
                'harga_pokok' => (int) preg_replace('/[^0-9]/', '', (string)($row['harga_pokok'] ?? 0)),
                'aktif'       => (int)($row['aktif'] ?? 1),
            ];
            if ($adaSumber) {
                $payload['sumber'] = ($row['sumber'] ?? 'luar') === 'pos' ? 'pos' : 'luar';
            }

            $isBaru = empty($row['id']) || str_starts_with((string)$row['id'], 'new_');

            if ($isBaru) {
                $payload['created_by'] = Auth::id();
                $payload['created_at'] = now();
                $payload['updated_at'] = now();
                \Illuminate\Support\Facades\DB::table('master_material')->insert($payload);
            } else {
                $payload['updated_at'] = now();
                \Illuminate\Support\Facades\DB::table('master_material')
                    ->where('id', $row['id'])->update($payload);
            }
            $simpan++;
        }

        return response()->json(['success' => true, 'tersimpan' => $simpan]);
    }

    public function materialNonaktif(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        \Illuminate\Support\Facades\DB::table('master_material')
            ->where('id', $request->id)
            ->update(['aktif' => 0, 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function atapSimpan(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2]), 403);
        $simpan = 0;

        foreach ($request->data ?? [] as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;

            $payload = [
                'nama'                => $nama,
                'kategori'            => $row['kategori'] ?? 'tembus_cahaya',
                'berat_kategori'      => $row['berat_kategori'] ?? 'ringan',
                'harga_per_lembar'    => (float) preg_replace('/[^0-9.]/', '', (string)($row['harga_per_lembar'] ?? 0)),
                'lebar_lembar_cm'     => (float)($row['lebar_lembar_cm'] ?? 80),
                'harga_per_m2'        => (float) preg_replace('/[^0-9.]/', '', (string)($row['harga_per_m2'] ?? 0)),
                'pemborosan_persen'   => (float) preg_replace('/[^0-9.]/', '', (string)($row['pemborosan_persen'] ?? 10)),
                'upah_pasang_per_m2'  => (float) preg_replace('/[^0-9.]/', '', (string)($row['upah_pasang_per_m2'] ?? 0)),
                'consumable'          => (float) preg_replace('/[^0-9.]/', '', (string)($row['consumable'] ?? 0)),
                'keterangan_customer' => trim($row['keterangan_customer'] ?? '') ?: null,
                'is_active'           => (int)($row['is_active'] ?? 1),
            ];

            $isBaru = empty($row['id']) || str_starts_with((string)$row['id'], 'new_');

            if ($isBaru) {
                $payload['kode'] = 'ATP_' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nama), 0, 8))
                    . '_' . rand(100, 999);
                $payload['urutan'] = 99;
                \Illuminate\Support\Facades\DB::table('rab_atap')->insert($payload);
            } else {
                \Illuminate\Support\Facades\DB::table('rab_atap')
                    ->where('id', $row['id'])->update($payload);
            }
            $simpan++;
        }

        return response()->json(['success' => true, 'tersimpan' => $simpan]);
    }

    public function atapNonaktif(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2]), 403);
        \Illuminate\Support\Facades\DB::table('rab_atap')
            ->where('id', $request->id)->update(['is_active' => 0]);
        return response()->json(['success' => true]);
    }
}