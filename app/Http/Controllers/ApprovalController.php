<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    // Surveyor/admin/owner kirim permintaan approval (diskon di luar batas)
    public function store(Request $request)
    {
        abort_if(!in_array(Auth::user()->level, [1, 2, 3]), 403);

        $request->validate([
            'lead_id'       => 'nullable|integer',
            'opsi_nama'     => 'nullable|string|max:255',
            'harga_normal'  => 'required|numeric|min:0',
            'harga_nawar'   => 'required|numeric|min:0',
            'diskon_persen' => 'required|numeric|min:0',
            'pokok'         => 'nullable|numeric|min:0',
        ]);

        $customer = null;
        if ($request->filled('lead_id')) {
            $lead = DB::table('pipeline_leads')->where('id', $request->lead_id)->first();
            $customer = $lead->nama_customer ?? null;
        }

        $id = DB::table('rab_approval')->insertGetId([
            'lead_id'       => $request->lead_id,
            'customer'      => $customer,
            'opsi_nama'     => $request->opsi_nama,
            'harga_normal'  => (int) round($request->harga_normal),
            'harga_nawar'   => (int) round($request->harga_nawar),
            'diskon_persen' => $request->diskon_persen,
            'pokok'         => (int) round($request->pokok ?? 0),
            'status'        => 'pending',
            'diminta_oleh'  => Auth::id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Notif WhatsApp ke owner (nomor tetap approval)
        $peminta = Auth::user();
        $pesan = "Permintaan Diskon RAB\n\n"
            . "Customer: " . ($customer ?: '-') . "\n"
            . ($request->opsi_nama ? "Opsi: " . $request->opsi_nama . "\n" : "")
            . "Harga normal: Rp " . number_format($request->harga_normal, 0, ',', '.') . "\n"
            . "Customer nawar: Rp " . number_format($request->harga_nawar, 0, ',', '.') . "\n"
            . "Diskon: " . $request->diskon_persen . "%\n"
            . "Dari: " . ($peminta->name ?? '-') . "\n\n"
            . "Setujui/tolak di: " . url('/rab-approval');
        $this->kirimTelegram($pesan);

        return response()->json(['success' => true, 'id' => $id, 'message' => 'Permintaan approval terkirim ke owner (Telegram).']);
    }

    // Owner: daftar permintaan approval
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);

        $rows = DB::table('rab_approval as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.diminta_oleh')
            ->orderByRaw("CASE WHEN a.status='pending' THEN 0 ELSE 1 END")
            ->orderBy('a.created_at', 'desc')
            ->get(['a.*', 'u.name as nama_peminta']);

        return view('rab-approval.index', compact('rows'));
    }

    // Owner: setuju / tolak
    public function proses(Request $request, $id)
    {
        abort_if(Auth::user()->level != 1, 403);

        $request->validate([
            'keputusan'     => 'required|in:approved,rejected',
            'catatan_owner' => 'nullable|string|max:500',
        ]);

        $apr = DB::table('rab_approval')->where('id', $id)->first();
        abort_if(!$apr, 404);

        DB::table('rab_approval')->where('id', $id)->update([
            'status'        => $request->keputusan,
            'catatan_owner' => $request->catatan_owner,
            'diputus_oleh'  => Auth::id(),
            'diputus_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Kabari owner hasil keputusan via Telegram
        $statusTeks = $request->keputusan === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $this->kirimTelegram("Keputusan approval: " . $statusTeks . "\nCustomer: " . ($apr->customer ?: '-') . "\nNawaran: Rp " . number_format($apr->harga_nawar, 0, ',', '.') . ($request->catatan_owner ? "\nCatatan: " . $request->catatan_owner : ""));

        $label = $request->keputusan === 'approved' ? 'disetujui' : 'ditolak';
        return redirect('/rab-approval')->with('success', 'Permintaan ' . $label . '.');
    }

    // Kirim notifikasi ke Telegram owner (gratis, anti-banned)
    private function kirimTelegram($pesan)
    {
        try {
            $token  = '8812397501:AAFFLbGTmjmhgV2mSDEc233-6ReCJq_S4Ns';
            $chatId = '8385647457';
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['chat_id' => $chatId, 'text' => $pesan],
                CURLOPT_TIMEOUT => 20,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Telegram approval gagal: ' . $e->getMessage());
        }
    }
}