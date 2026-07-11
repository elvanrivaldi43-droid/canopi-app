<?php
// FILE: app/Http/Controllers/PipelineController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PipelineLead;
use App\Models\PipelineFollowup;

class PipelineController extends Controller
{
    public function index()
{
    $user  = Auth::user();
    $query = PipelineLead::with(['inputOleh', 'followups']);
    if ($user->level == 4) $query->where('input_oleh', $user->id);
    $leads    = $query->orderBy('updated_at', 'desc')->get();
    $statuses = PipelineLead::statusList();
    $colors   = PipelineLead::statusColors();
    $grouped  = [];
    foreach ($statuses as $key => $label) {
        $grouped[$key] = $leads->where('status', $key);
    }
    $totalNilai = $leads->whereNotIn('status',['tidak_jadi'])->sum('estimasi_nilai');
    $totalLead  = $leads->count();
    $statusList = $statuses;

    return view('pipeline.index', compact('grouped','statuses','colors','totalNilai','totalLead','statusList'));
}

    public function listView(Request $request)
    {
        $user  = Auth::user();
        $query = PipelineLead::with('inputOleh');
        if ($user->level == 4) $query->where('input_oleh', $user->id);
        if ($request->status) $query->where('status', $request->status);
        if ($request->produk) $query->where('produk', $request->produk);
        if ($request->search) $query->where(function($q) use ($request) {
            $q->where('nama_customer', 'like', "%{$request->search}%")
              ->orWhere('no_hp', 'like', "%{$request->search}%");
        });
        $leads      = $query->orderBy('updated_at','desc')->paginate(20);
        $statuses   = PipelineLead::statusList();
        $produkList = PipelineLead::produkList();
        return view('pipeline.list', compact('leads','statuses','produkList'));
    }

    public function create()
    {
        $statuses   = PipelineLead::statusList();
        $produkList = PipelineLead::produkList();
        $sumberList = PipelineLead::sumberList();
        return view('pipeline.create', compact('statuses','produkList','sumberList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_customer' => 'required|string|max:255',
            'no_hp'         => 'required|string|max:20',
            'produk'        => 'required',
            'sumber_lead'   => 'required',
            'status'        => 'required',
            'estimasi_nilai'=> 'nullable|numeric|min:0',
            'tgl_kunjungan' => 'nullable|date',
        ]);

        PipelineLead::create([
            'nama_customer'   => $request->nama_customer,
            'no_hp'           => $request->no_hp,
            'alamat'          => $request->alamat,
            'produk'          => $request->produk,
            'sumber_lead'     => $request->sumber_lead,
            'status'          => $request->status,
            'estimasi_nilai'  => $request->estimasi_nilai ?? 0,
            'catatan'         => $request->catatan,
            'tgl_kunjungan'   => $request->tgl_kunjungan,
            'last_activity_at'=> now(),
            'input_oleh'      => Auth::id(),
        ]);

        return redirect()->route('pipeline.index')->with('success', 'Lead berhasil ditambahkan!');
    }

    public function show(PipelineLead $pipeline)
    {
        $pipeline->load(['inputOleh', 'followups.user']);
        $statuses   = PipelineLead::statusList();
        $colors     = PipelineLead::statusColors();
        $metodeList = ['whatsapp'=>'💬 WhatsApp','telepon'=>'📞 Telepon','email'=>'📧 Email','kunjungan'=>'🚗 Kunjungan','lainnya'=>'📝 Lainnya'];
        return view('pipeline.show', compact('pipeline','statuses','colors','metodeList'));
    }

    public function edit(PipelineLead $pipeline)
    {
        $statuses   = PipelineLead::statusList();
        $produkList = PipelineLead::produkList();
        $sumberList = PipelineLead::sumberList();
        return view('pipeline.edit', compact('pipeline','statuses','produkList','sumberList'));
    }

    public function update(Request $request, PipelineLead $pipeline)
    {
        $request->validate([
            'nama_customer' => 'required|string|max:255',
            'no_hp'         => 'required|string|max:20',
            'produk'        => 'required',
            'sumber_lead'   => 'required',
            'status'        => 'required',
            'estimasi_nilai'=> 'nullable|numeric|min:0',
            'tgl_kunjungan' => 'nullable|date',
        ]);

        $pipeline->update([
            'nama_customer'   => $request->nama_customer,
            'no_hp'           => $request->no_hp,
            'alamat'          => $request->alamat,
            'produk'          => $request->produk,
            'sumber_lead'     => $request->sumber_lead,
            'status'          => $request->status,
            'estimasi_nilai'  => $request->estimasi_nilai ?? 0,
            'catatan'         => $request->catatan,
            'tgl_kunjungan'   => $request->tgl_kunjungan,
            'last_activity_at'=> now(),
        ]);

        return redirect()->route('pipeline.show', $pipeline)->with('success', 'Lead berhasil diupdate!');
    }

    public function updateStatus(Request $request, PipelineLead $pipeline)
    {
        $request->validate([
            'status'       => 'required|in:lead,dihubungi,dijadwalkan,dikunjungi,ditawar,deal,tidak_jadi',
            'tgl_kunjungan'=> 'nullable|date',
        ]);
        $pipeline->update([
            'status'          => $request->status,
            'tgl_kunjungan'   => $request->tgl_kunjungan,
            'last_activity_at'=> now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Status berhasil diupdate!']);
    }

    public function storeFollowup(Request $request, PipelineLead $pipeline)
    {
        $request->validate([
            'metode'                  => 'required|in:whatsapp,telepon,email,kunjungan,lainnya',
            'catatan'                 => 'required|string',
            'tgl_followup_berikutnya' => 'nullable|date',
        ]);
        PipelineFollowup::create([
            'pipeline_lead_id'        => $pipeline->id,
            'user_id'                 => Auth::id(),
            'metode'                  => $request->metode,
            'catatan'                 => $request->catatan,
            'tgl_followup_berikutnya' => $request->tgl_followup_berikutnya,
        ]);
        $pipeline->update(['last_activity_at' => now()]);
        return back()->with('success', 'Follow-up berhasil dicatat!');
    }
}