<?php

namespace App\Http\Controllers;

use App\Models\PipelineLead;
use App\Models\PipelineFollowup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PipelineController extends Controller
{
    private function baseQuery()
    {
        $user = Auth::user();
        $q = PipelineLead::with(['user', 'followups']);
        if ($user->level === 4) {
            $q->where('user_id', $user->id);
        }
        return $q;
    }

    public function index()
    {
        $leads      = $this->baseQuery()->orderBy('last_activity_at', 'desc')->get();
        $statusList = PipelineLead::statusList();
        $colors     = PipelineLead::statusColors();
        $grouped    = collect($statusList)->mapWithKeys(
            fn($label, $key) => [$key => $leads->where('status', $key)->values()]
        );
        $totalNilai = $leads->whereNotIn('status', ['tidak_jadi'])->sum('estimasi_nilai');
        $totalDeal  = $leads->where('status', 'deal')->sum('estimasi_nilai');
        $totalLead  = $leads->whereNotIn('status', ['tidak_jadi', 'deal'])->count();
        return view('pipeline.index', compact('grouped', 'colors', 'statusList', 'totalNilai', 'totalDeal', 'totalLead'));
    }

    public function listView(Request $request)
    {
        $q = $this->baseQuery();
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('produk'))  $q->where('produk', $request->produk);
        if ($request->filled('sumber'))  $q->where('sumber_lead', $request->sumber);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($q2) => $q2->where('nama_customer', 'like', "%{$s}%")
                                    ->orWhere('no_hp', 'like', "%{$s}%")
                                    ->orWhere('alamat', 'like', "%{$s}%"));
        }
        $leads      = $q->orderBy('last_activity_at', 'desc')->paginate(25)->withQueryString();
        $statusList = PipelineLead::statusList();
        $colors     = PipelineLead::statusColors();
        return view('pipeline.list', compact('leads', 'statusList', 'colors'));
    }

    public function create()
    {
        return view('pipeline.create', [
            'statusList' => PipelineLead::statusList(),
            'produkList' => PipelineLead::produkList(),
            'sumberList' => PipelineLead::sumberList(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_customer'  => 'required|string|max:255',
            'no_hp'          => 'required|string|max:20',
            'alamat'         => 'nullable|string',
            'produk'         => 'required|in:kanopi,pagar,tralis,tenda',
            'sumber_lead'    => 'required|in:Instagram,WhatsApp,Referensi,Google,Spanduk,Lainnya',
            'estimasi_nilai' => 'nullable|numeric|min:0',
            'catatan'        => 'nullable|string',
            'status'         => 'required|in:lead,dihubungi,dijadwalkan,dikunjungi,ditawar,deal,tidak_jadi',
            'tanggal_jadwal' => 'nullable|date|required_if:status,dijadwalkan',
            'jam_jadwal'     => 'nullable|date_format:H:i|required_if:status,dijadwalkan',
        ]);

        $data['user_id']          = Auth::id();
        $data['last_activity_at'] = now();
        $lead = PipelineLead::create($data);

        PipelineFollowup::create([
            'pipeline_lead_id' => $lead->id,
            'user_id'          => Auth::id(),
            'catatan'          => 'Lead baru dibuat.',
            'status_sesudah'   => $data['status'],
        ]);

        return redirect()->route('pipeline.show', $lead)->with('success', 'Lead berhasil ditambahkan.');
    }

    public function show(PipelineLead $pipeline)
    {
        $this->gate($pipeline);
        $pipeline->load(['user', 'followups.user']);
        return view('pipeline.show', [
            'lead'       => $pipeline,
            'statusList' => PipelineLead::statusList(),
            'colors'     => PipelineLead::statusColors(),
            'produkList' => PipelineLead::produkList(),
            'sumberList' => PipelineLead::sumberList(),
        ]);
    }

    public function edit(PipelineLead $pipeline)
    {
        $this->gate($pipeline);
        return view('pipeline.edit', [
            'lead'       => $pipeline,
            'statusList' => PipelineLead::statusList(),
            'produkList' => PipelineLead::produkList(),
            'sumberList' => PipelineLead::sumberList(),
        ]);
    }

    public function update(Request $request, PipelineLead $pipeline)
    {
        $this->gate($pipeline);
        $data = $request->validate([
            'nama_customer'  => 'required|string|max:255',
            'no_hp'          => 'required|string|max:20',
            'alamat'         => 'nullable|string',
            'produk'         => 'required|in:kanopi,pagar,tralis,tenda',
            'sumber_lead'    => 'required|in:Instagram,WhatsApp,Referensi,Google,Spanduk,Lainnya',
            'estimasi_nilai' => 'nullable|numeric|min:0',
            'catatan'        => 'nullable|string',
            'status'         => 'required|in:lead,dihubungi,dijadwalkan,dikunjungi,ditawar,deal,tidak_jadi',
            'tanggal_jadwal' => 'nullable|date|required_if:status,dijadwalkan',
            'jam_jadwal'     => 'nullable|date_format:H:i|required_if:status,dijadwalkan',
        ]);

        $statusLama = $pipeline->status;
        $data['last_activity_at'] = now();
        $pipeline->update($data);

        $sl      = PipelineLead::statusList();
        $catatan = $statusLama !== $data['status']
            ? "Status diubah: {$sl[$statusLama]} → {$sl[$data['status']]}"
            : 'Data lead diupdate.';

        PipelineFollowup::create([
            'pipeline_lead_id' => $pipeline->id,
            'user_id'          => Auth::id(),
            'catatan'          => $catatan,
            'status_sebelum'   => $statusLama,
            'status_sesudah'   => $data['status'],
        ]);

        return redirect()->route('pipeline.show', $pipeline)->with('success', 'Lead berhasil diupdate.');
    }

    public function updateStatus(Request $request, PipelineLead $pipeline)
    {
        $this->gate($pipeline);
        $request->validate(['status' => 'required|in:lead,dihubungi,dijadwalkan,dikunjungi,ditawar,deal,tidak_jadi']);

        $statusLama = $pipeline->status;
        $sl = PipelineLead::statusList();
        $pipeline->update(['status' => $request->status, 'last_activity_at' => now()]);

        PipelineFollowup::create([
            'pipeline_lead_id' => $pipeline->id,
            'user_id'          => Auth::id(),
            'catatan'          => "Status diubah: {$sl[$statusLama]} → {$sl[$request->status]}",
            'status_sebelum'   => $statusLama,
            'status_sesudah'   => $request->status,
        ]);

        return back()->with('success', 'Status berhasil diupdate.');
    }

    public function storeFollowup(Request $request, PipelineLead $pipeline)
    {
        $this->gate($pipeline);
        $request->validate(['catatan' => 'required|string|max:1000']);

        PipelineFollowup::create([
            'pipeline_lead_id' => $pipeline->id,
            'user_id'          => Auth::id(),
            'catatan'          => $request->catatan,
        ]);
        $pipeline->update(['last_activity_at' => now()]);

        return back()->with('success', 'Follow-up berhasil dicatat.');
    }

    private function gate(PipelineLead $pipeline): void
    {
        $user = Auth::user();
        if ($user->level === 4 && $pipeline->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }
    }
}
