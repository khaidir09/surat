<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\StatusKepsek;
use App\Services\N8nClient;
use App\Services\N8nException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Status kehadiran kepala sekolah (AC-5).
 *
 * Kedua role boleh memperbaruinya: kepsek mengabarkan sendiri, staf TAS
 * memperbarui atas nama beliau ketika sedang tidak memegang perangkat.
 */
class StatusKepsekController extends Controller
{
    public function form(): View
    {
        return view('status-kepsek.form', [
            'terkini' => StatusKepsek::terkini(),
        ]);
    }

    public function store(Request $request, N8nClient $n8n): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(StatusKepsek::PILIHAN)],
            'estimasi' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $n8n->statusKepsek($data['status'], $data['estimasi'] ?? null, $data['keterangan'] ?? null);
        } catch (N8nException $e) {
            // Kegagalan ditampilkan sebagai kegagalan. Dashboard lama
            // (index.html:723-745) hanya memeriksa res.ok tanpa membedakan
            // sebabnya, sehingga 401 pun tampil sebagai "Gagal menyimpan"
            // yang tidak menuntun ke perbaikan apa pun.
            //
            // N8nUnauthorizedException sengaja tidak ditangkap di sini: ia
            // punya halaman sendiri yang menjelaskan bahwa masalahnya ada
            // pada kredensial server, bukan pada pengguna (E2).
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        // Dicatat hanya setelah n8n mengonfirmasi, supaya jejaknya tidak
        // pernah menyatakan sesuatu yang sebenarnya tidak terjadi (E1).
        Aktivitas::catat('status_kepsek', 'status_kepsek', null, [
            'status' => $data['status'],
            'estimasi' => $data['estimasi'] ?? null,
        ]);

        return redirect()
            ->route('status-kepsek.form')
            ->with('sukses', 'Status kepala sekolah diperbarui menjadi "' . $data['status'] . '".');
    }
}
