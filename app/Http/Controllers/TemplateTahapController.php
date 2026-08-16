<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TemplateTahap;
use App\Models\TemplateTahapItem;
use App\Models\TahapMaster;
use App\Models\Project;

class TemplateTahapController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);
        $templates = TemplateTahap::with('items.tahapMaster')->orderBy('nama')->get();
        return view('template-tahap.index', compact('templates'));
    }

    public function create()
    {
        abort_if(Auth::user()->level != 1, 403);
        $tahapList = TahapMaster::where('is_active', true)->orderBy('urutan')->get();
        $jenisProjectOptions = array_values(Project::$jenisProjectOptions);
        return view('template-tahap.create', compact('tahapList', 'jenisProjectOptions'));
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'nama'          => 'required|string|max:255',
            'jenis_project' => 'required|string|max:255',
            'tahap_ids'     => 'required|array|min:1',
            'tahap_ids.*'   => 'integer|exists:tahap_master,id',
        ]);

        $template = TemplateTahap::create([
            'nama'          => $request->nama,
            'jenis_project' => $request->jenis_project,
            'is_active'     => true,
        ]);

        foreach (array_values($request->tahap_ids) as $urutan => $tahapMasterId) {
            TemplateTahapItem::create([
                'template_tahap_id' => $template->id,
                'tahap_master_id'   => $tahapMasterId,
                'urutan'            => $urutan,
            ]);
        }

        return redirect('/template-tahap')->with('success', 'Template "' . $template->nama . '" tersimpan.');
    }

    public function edit(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $templateTahap->load('items');
        $tahapList = TahapMaster::where('is_active', true)->orderBy('urutan')->get();
        $jenisProjectOptions = array_values(Project::$jenisProjectOptions);
        $selectedIds = $templateTahap->items->pluck('tahap_master_id')->toArray();
        return view('template-tahap.edit', compact('templateTahap', 'tahapList', 'jenisProjectOptions', 'selectedIds'));
    }

    public function update(Request $request, TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'nama'          => 'required|string|max:255',
            'jenis_project' => 'required|string|max:255',
            'tahap_ids'     => 'required|array|min:1',
            'tahap_ids.*'   => 'integer|exists:tahap_master,id',
        ]);

        $templateTahap->update([
            'nama'          => $request->nama,
            'jenis_project' => $request->jenis_project,
        ]);

        $templateTahap->items()->delete();
        foreach (array_values($request->tahap_ids) as $urutan => $tahapMasterId) {
            TemplateTahapItem::create([
                'template_tahap_id' => $templateTahap->id,
                'tahap_master_id'   => $tahapMasterId,
                'urutan'            => $urutan,
            ]);
        }

        return redirect('/template-tahap')->with('success', 'Template "' . $templateTahap->nama . '" diupdate.');
    }

    public function destroy(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $nama = $templateTahap->nama;
        $templateTahap->delete();
        return redirect('/template-tahap')->with('success', 'Template "' . $nama . '" dihapus.');
    }

    public function toggleAktif(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $templateTahap->update(['is_active' => !$templateTahap->is_active]);
        return back()->with('success', 'Status template diupdate.');
    }
}
