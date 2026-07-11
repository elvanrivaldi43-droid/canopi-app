<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTim;
use App\Models\ProjectMaterial;
use App\Models\PembayaranProject;
use App\Models\RabItem;
use App\Models\RateKondisi;
use App\Models\MasterMaterial;
use App\Models\PipelineLead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    // ============================================================
    // LIST PROJECT
    // ============================================================
    public function index(Request $request)
    {
        $query = Project::with('rateKondisi')
            ->orderByRaw("FIELD(status,'pengerjaan','persiapan','garansi','selesai','dibatalkan')")
            ->orderByDesc('created_at');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('nama_customer', 'like', '%'.$request->search.'%')
                  ->orWhere('kode_project', 'like', '%'.$request->search.'%')
                  ->orWhere('jenis_project', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $projects = $query->paginate(15)->withQueryString();

        // Stats ringkasan
        $stats = [
            'total'      => Project::count(),
            'pengerjaan' => Project::where('status', 'pengerjaan')->count(),
            'persiapan'  => Project::where('status', 'persiapan')->count(),
            'selesai'    => Project::where('status', 'selesai')->count(),
        ];

        return view('projects.index', compact('projects', 'stats'));
    }

    // ============================================================
    // BUAT PROJECT BARU (dari pipeline Deal)
    // ============================================================
    public function create(Request $request)
    {
        // Kalau dari pipeline, ambil data lead
        $lead = null;
        if ($request->id_lead) {
            $lead = PipelineLead::find($request->id_lead);
        }

        $rateKondisi = RateKondisi::aktif()->orderBy('kode')->get();
        return view('projects.create', compact('lead', 'rateKondisi'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_customer'    => 'required|string|max:150',
            'jenis_project'    => 'required|string|max:100',
            'nilai_kontrak'    => 'required|integer|min:0',
            'tgl_mulai_target' => 'nullable|date',
            'tgl_selesai_target'=> 'nullable|date',
        ]);

        DB::transaction(function() use ($request) {
            // Ambil rate kondisi
            $multiplier = 1.00;
            $needsApproval = false;
            if ($request->id_rate_kondisi) {
                $rate = RateKondisi::find($request->id_rate_kondisi);
                if ($rate) {
                    $multiplier = $rate->multiplier;
                    $needsApproval = ($rate->kode !== 'STD');
                }
            }

            $project = Project::create([
                'id_lead'           => $request->id_lead,
                'kode_project'      => Project::generateKode(),
                'nama_customer'     => $request->nama_customer,
                'no_hp'             => $request->no_hp,
                'alamat_project'    => $request->alamat_project,
                'jenis_project'     => $request->jenis_project,
                'deskripsi'         => $request->deskripsi,
                'nilai_kontrak'     => $request->nilai_kontrak,
                'id_rate_kondisi'   => $request->id_rate_kondisi,
                'multiplier_upah'   => $multiplier,
                // Kondisi STD langsung approved, kondisi khusus butuh approval owner
                'kondisi_approved_by' => (!$needsApproval) ? Auth::id() : null,
                'kondisi_approved_at' => (!$needsApproval) ? now() : null,
                'status'            => 'persiapan',
                'tgl_mulai_target'  => $request->tgl_mulai_target,
                'tgl_selesai_target'=> $request->tgl_selesai_target,
                'dibuat_oleh'       => Auth::id(),
            ]);

            // Update status lead ke 'deal' kalau dari pipeline
            if ($request->id_lead) {
                PipelineLead::where('id', $request->id_lead)
                    ->update(['status' => 'deal']);
            }

            // Kirim notif WA ke owner kalau kondisi khusus
            if ($needsApproval && $rate) {
                $this->notifKondisiKhusus($project, $rate);
            }
        });

        return redirect()->route('projects.index')
            ->with('success', 'Project berhasil dibuat.');
    }

    // ============================================================
    // DETAIL PROJECT
    // ============================================================
    public function show(Project $project)
    {
        $project->load([
            'rateKondisi',
            'tim.user',
            'rabItems',
            'materialAktual',
            'pembayaran'
        ]);

        $rateKondisi   = RateKondisi::aktif()->get();
        $masterMaterial = MasterMaterial::aktif()->orderBy('kategori')->orderBy('nama')->get();
        $karyawan      = User::whereIn('level', [3,5,6])->where('status', 'aktif')->orderBy('name')->get();

        // Ringkasan RAB vs Aktual
        $totalRabPokok    = $project->rabItems->sum('total_pokok');
        $totalRabCustomer = $project->rabItems->sum('total_customer');
        $totalMaterialAktual = $project->materialAktual->sum('total');
        $totalUpahTim     = $project->tim->where('status','disetujui')->sum('total_upah');
        $selisihMaterial  = $totalRabPokok - $totalMaterialAktual;

        // Material yang melebihi RAB & pending approval
        $materialPendingApproval = $project->materialAktual
            ->where('status_vs_rab', 'melebihi_rab');

        return view('projects.show', compact(
            'project', 'rateKondisi', 'masterMaterial', 'karyawan',
            'totalRabPokok', 'totalRabCustomer', 'totalMaterialAktual',
            'totalUpahTim', 'selisihMaterial', 'materialPendingApproval'
        ));
    }

    // ============================================================
    // UPDATE STATUS PROJECT
    // ============================================================
    public function updateStatus(Request $request, Project $project)
    {
        $request->validate(['status' => 'required|in:persiapan,pengerjaan,selesai,garansi,dibatalkan']);

        $tglField = match($request->status) {
            'pengerjaan' => ['tgl_mulai_aktual' => now()->toDateString()],
            'selesai'    => ['tgl_selesai_aktual' => now()->toDateString()],
            default      => [],
        };

        $project->update(array_merge(['status' => $request->status], $tglField));

        return back()->with('success', 'Status project diupdate ke ' . Project::$statusLabel[$request->status]);
    }

    // ============================================================
    // APPROVE KONDISI KHUSUS (Owner only)
    // ============================================================
    public function approveKondisi(Project $project)
    {
        $project->update([
            'kondisi_approved_by' => Auth::id(),
            'kondisi_approved_at' => now(),
        ]);
        return back()->with('success', 'Kondisi kerja khusus disetujui.');
    }

    // ============================================================
    // ASSIGN TIM (SPV assign, owner approve kalau kondisi khusus)
    // ============================================================
    public function storeTim(Request $request, Project $project)
    {
        $request->validate([
            'id_user'          => 'required|exists:users,id',
            'jabatan_lapangan' => 'required|in:tukang,kenek',
            'tgl_masuk'        => 'required|date',
            'tgl_keluar'       => 'required|date|after_or_equal:tgl_masuk',
        ]);

        $rateDasar = $request->jabatan_lapangan === 'tukang'
            ? RateKondisi::RATE_TUKANG
            : RateKondisi::RATE_KENEK;

        $multiplier = $project->multiplier_upah ?? 1.00;
        $rateFinal  = (int) round($rateDasar * $multiplier);

        $jumlahHari = \Carbon\Carbon::parse($request->tgl_masuk)
            ->diffInDays(\Carbon\Carbon::parse($request->tgl_keluar)) + 1;

        // Kalau kondisi standar atau sudah approved → langsung disetujui
        $status = ($project->kondisi_approved_at || $project->multiplier_upah == 1.00)
            ? 'disetujui'
            : 'pending_approval';

        ProjectTim::create([
            'id_project'       => $project->id,
            'id_user'          => $request->id_user,
            'tgl_masuk'        => $request->tgl_masuk,
            'tgl_keluar'       => $request->tgl_keluar,
            'jumlah_hari'      => $jumlahHari,
            'jabatan_lapangan' => $request->jabatan_lapangan,
            'rate_dasar'       => $rateDasar,
            'multiplier'       => $multiplier,
            'rate_final'       => $rateFinal,
            'total_upah'       => $rateFinal * $jumlahHari,
            'di_assign_oleh'   => Auth::id(),
            'status'           => $status,
        ]);

        return back()->with('success', 'Anggota tim berhasil ditambahkan.');
    }

    public function destroyTim(ProjectTim $tim)
    {
        $tim->delete();
        return back()->with('success', 'Anggota tim dihapus.');
    }

    // ============================================================
    // INPUT MATERIAL AKTUAL
    // ============================================================
    public function storeMaterial(Request $request, Project $project)
    {
        $request->validate([
            'nama_material' => 'required|string|max:150',
            'satuan'        => 'required|string',
            'qty_aktual'    => 'required|numeric|min:0.01',
            'harga_satuan'  => 'required|integer|min:0',
            'tanggal_beli'  => 'nullable|date',
        ]);

        $total = (int) round($request->qty_aktual * $request->harga_satuan);

        // Cek apakah melebihi RAB
        $statusVsRab = 'normal';
        $rabItem     = null;

        if ($request->id_rab_item) {
            $rabItem = RabItem::find($request->id_rab_item);
            if ($rabItem) {
                // Total aktual untuk item ini (termasuk yang sudah diinput sebelumnya)
                $totalAktualExisting = ProjectMaterial::where('id_project', $project->id)
                    ->where('id_rab_item', $request->id_rab_item)
                    ->sum('total');
                $totalAktualBaru = $totalAktualExisting + $total;

                if ($totalAktualBaru > $rabItem->total_pokok) {
                    $statusVsRab = 'melebihi_rab';
                }
            }
        }

        $material = ProjectMaterial::create([
            'id_project'      => $project->id,
            'id_rab_item'     => $request->id_rab_item,
            'id_master_material' => $request->id_master_material,
            'nama_material'   => $request->nama_material,
            'satuan'          => $request->satuan,
            'qty_aktual'      => $request->qty_aktual,
            'harga_satuan'    => $request->harga_satuan,
            'total'           => $total,
            'tanggal_beli'    => $request->tanggal_beli ?? now()->toDateString(),
            'keterangan'      => $request->keterangan,
            'status_vs_rab'   => $statusVsRab,
            'dibuat_oleh'     => Auth::id(),
        ]);

        // Kalau melebihi RAB → kirim notif ke owner
        if ($statusVsRab === 'melebihi_rab') {
            $this->notifMelebihiRab($project, $material, $rabItem);
        }

        return back()->with(
            $statusVsRab === 'melebihi_rab' ? 'warning' : 'success',
            $statusVsRab === 'melebihi_rab'
                ? 'Material dicatat tapi MELEBIHI RAB. Menunggu approval owner.'
                : 'Material berhasil dicatat.'
        );
    }

    // Approve material melebihi RAB (Owner only)
    public function approveMaterial(ProjectMaterial $material)
    {
        $material->update([
            'status_vs_rab' => 'approved',
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
        ]);
        return back()->with('success', 'Pembelian material disetujui.');
    }

    public function destroyMaterial(ProjectMaterial $material)
    {
        $material->delete();
        return back()->with('success', 'Material dihapus.');
    }

    // ============================================================
    // PEMBAYARAN CUSTOMER
    // ============================================================
    public function storePembayaran(Request $request, Project $project)
    {
        $request->validate([
            'jenis'        => 'required|in:dp,termin,lunas',
            'nominal'      => 'required|integer|min:1',
            'tanggal_bayar'=> 'required|date',
            'metode'       => 'nullable|string',
        ]);

        PembayaranProject::create([
            'id_project'   => $project->id,
            'jenis'        => $request->jenis,
            'nominal'      => $request->nominal,
            'tanggal_bayar'=> $request->tanggal_bayar,
            'metode'       => $request->metode,
            'keterangan'   => $request->keterangan,
            'status'       => 'pending',
        ]);

        return back()->with('success', 'Pembayaran dicatat. Menunggu konfirmasi.');
    }

    public function konfirmasiPembayaran(PembayaranProject $pembayaran)
    {
        $pembayaran->update([
            'status'            => 'dikonfirmasi',
            'dikonfirmasi_oleh' => Auth::id(),
            'dikonfirmasi_at'   => now(),
        ]);

        // Update status project otomatis
        $project = $pembayaran->project;
        if ($pembayaran->jenis === 'dp') {
            $project->update(['status' => 'pengerjaan', 'tgl_mulai_aktual' => now()->toDateString()]);
        } elseif ($pembayaran->jenis === 'lunas') {
            $project->update(['status' => 'selesai', 'tgl_selesai_aktual' => now()->toDateString()]);
        }

        return back()->with('success', 'Pembayaran dikonfirmasi.');
    }

    // ============================================================
    // NOTIFIKASI WA (via Fonnte)
    // ============================================================
    private function notifKondisiKhusus(Project $project, RateKondisi $rate)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $pesan = "🔔 *Project Baru - Kondisi Khusus*\n\n"
            . "Project: *{$project->kode_project}*\n"
            . "Customer: {$project->nama_customer}\n"
            . "Jenis: {$project->jenis_project}\n"
            . "Nilai: Rp " . number_format($project->nilai_kontrak, 0, ',', '.') . "\n\n"
            . "⚠️ Kondisi Kerja: *{$rate->nama}* (×{$rate->multiplier})\n"
            . "Rate tukang: Rp " . number_format($rate->rate_tukang_final, 0, ',', '.') . "/hari\n"
            . "Rate kenek: Rp " . number_format($rate->rate_kenek_final, 0, ',', '.') . "/hari\n\n"
            . "Silakan login untuk approve kondisi kerja ini.";

        $this->kirimWA($owner->no_hp, $pesan);
    }

    private function notifMelebihiRab(Project $project, ProjectMaterial $material, $rabItem)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $rabNominal = $rabItem ? 'Rp ' . number_format($rabItem->total_pokok, 0, ',', '.') : '-';

        $pesan = "⚠️ *Pembelian Melebihi RAB*\n\n"
            . "Project: *{$project->kode_project}* - {$project->nama_customer}\n\n"
            . "Material: *{$material->nama_material}*\n"
            . "Pembelian baru: Rp " . number_format($material->total, 0, ',', '.') . "\n"
            . "Batas RAB: {$rabNominal}\n\n"
            . "Silakan login untuk approve atau tolak pembelian ini.";

        $this->kirimWA($owner->no_hp, $pesan);
    }

    private function kirimWA($noHp, $pesan)
    {
        try {
            $token = config('services.fonnte.token', '');
            if (!$token) return;

            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target'  => $noHp,
                'message' => $pesan,
            ]);
        } catch (\Exception $e) {
            // Silent fail — jangan sampai WA error bikin fitur utama error
        }
    }
}
