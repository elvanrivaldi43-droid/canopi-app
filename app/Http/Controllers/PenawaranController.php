<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenawaranController extends Controller
{
    public function show($id)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);

        $lead = DB::table('pipeline_leads')->where('id', $id)->first();
        abort_if(!$lead, 404);

        $pen = !empty($lead->penawaran_json) ? json_decode($lead->penawaran_json) : null;
        $deal = !empty($lead->deal_json) ? json_decode($lead->deal_json) : null;

        return view('penawaran.show', compact('lead', 'pen', 'deal'));
    }

    // Simpan DEAL + tanda tangan customer (menempel di lead)
    public function deal(Request $request, $id)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);

        $request->validate([
            'opsi' => 'required|string|max:255',
            'ttd'  => 'required|string',
        ]);

        $lead = DB::table('pipeline_leads')->where('id', $id)->first();
        abort_if(!$lead, 404);

        $deal = json_encode([
            'opsi'    => $request->opsi,
            'ttd'     => $request->ttd,
            'deal_at' => now()->toDateTimeString(),
            'oleh'    => Auth::user()->name ?? '-',
        ]);

        DB::table('pipeline_leads')->where('id', $id)->update([
            'deal_json'  => $deal,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}