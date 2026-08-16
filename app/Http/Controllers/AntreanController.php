<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Services\N8nClient;
use App\Services\N8nException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Antrean persetujuan — pengganti index.html sebagai alat kerja harian TAS.
 *
 * Seluruh daftar berasal dari n8n, bukan dari database: n8n yang menyusun
 * gabungan dua tabel dan menambahkan field turunan seperti nama_siswa dan
 * kelas_jurusan (§5.2). Laravel tidak menghitung ulang apa pun di sini
 * supaya yang dilihat staf persis sama dengan yang diketahui n8n.
 */
class AntreanController extends Controller
{
    public function index(N8nClient $n8n): View
    {
        try {
            $item = $n8n->pending();
            $galat = null;
        } catch (N8nException $e) {
            // n8n mati bukan berarti halaman kosong. Halaman kosong terbaca
            // sebagai "tidak ada pekerjaan", padahal justru sedang buta.
            $item = [];
            $galat = $e->getMessage();
        }

        return view('antrean.index', [
            'galat' => $galat,
            'tugasMenunggu' => $this->saring($item, 'surat_tugas', 'menunggu'),
            'tugasTtd' => $this->saring($item, 'surat_tugas', 'menunggu_ttd'),
            'keteranganMenunggu' => $this->saring($item, 'keterangan_aktif', 'menunggu'),
            'takDikenal' => $this->takDikenal($item),
            'jumlah' => count($item),
        ]);
    }

    public function putuskan(Request $request, string $jenis, int $id, N8nClient $n8n): RedirectResponse
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['setujui', 'tolak'])],

            // Divalidasi di server, bukan hanya di klien seperti
            // index.html:612 yang dapat dilewati begitu saja (E4).
            'nomor' => ['exclude_unless:aksi,setujui', 'required', 'regex:/^\d+$/'],
        ], [
            'nomor.required' => 'Nomor urut surat wajib diisi saat menyetujui.',
            'nomor.regex' => 'Nomor urut harus berupa angka saja, sesuai buku agenda.',
        ]);

        $nomor = $data['nomor'] ?? null;

        // E5: nomor ganda diperingatkan, TIDAK diblokir. Buku agenda fisik
        // adalah otoritasnya; sistem yang menolak akan memaksa staf mencari
        // jalan keluar di luar sistem.
        if ($nomor && ! $request->boolean('abaikan_duplikat') && $this->nomorSudahDipakai($jenis, $nomor)) {
            return back()->withInput()->with('konfirmasi', [
                'jenis' => $jenis,
                'id' => $id,
                'nomor' => $nomor,
                'pesan' => "Nomor $nomor sepertinya sudah dipakai pada surat lain tahun ini. "
                    . 'Periksa buku agenda. Bila memang benar, lanjutkan.',
            ]);
        }

        try {
            $n8n->putuskan($id, $jenis, $data['aksi'], $nomor);
        } catch (N8nException $e) {
            // E1: tidak ada jejak yang ditulis, surat tetap di antrean.
            return back()->withInput()->withErrors(['antrean' => $e->getMessage()]);
        }

        Aktivitas::catat($data['aksi'], $jenis, $id, array_filter(['nomor' => $nomor]));

        return redirect()->route('antrean.index')->with('sukses', $data['aksi'] === 'setujui'
            ? "Surat #$id disetujui — sedang diterbitkan."
            : "Surat #$id ditolak.");
    }

    public function selesai(string $jenis, int $id, N8nClient $n8n): RedirectResponse
    {
        try {
            $n8n->selesai($id);
        } catch (N8nException $e) {
            return back()->withErrors(['antrean' => $e->getMessage()]);
        }

        Aktivitas::catat('selesai', $jenis, $id);

        return redirect()->route('antrean.index')
            ->with('sukses', "Surat #$id ditandai selesai — pemohon diberi tahu.");
    }

    /**
     * Kepsek boleh mengunduh PDF meski tidak boleh memutuskan (§6).
     */
    public function pdf(string $jenis, int $id, N8nClient $n8n): RedirectResponse
    {
        try {
            $url = $n8n->pdf($id);
        } catch (N8nException $e) {
            return back()->withErrors(['antrean' => $e->getMessage()]);
        }

        if (blank($url)) {
            // E15: tautan kosong tidak dibuka sebagai tab kosong.
            return back()->withErrors(['antrean' => "Berkas PDF untuk surat #$id belum tersedia."]);
        }

        return redirect()->away($url);
    }

    /**
     * @param  array<int, array<string, mixed>>  $item
     * @return array<int, array<string, mixed>>
     */
    private function saring(array $item, string $jenis, string $status): array
    {
        return array_values(array_filter(
            $item,
            fn ($s) => ($s['jenis'] ?? null) === $jenis && ($s['status'] ?? null) === $status
        ));
    }

    /**
     * Sisa yang tidak masuk ketiga seksi.
     *
     * render() pada index.html:573-602 hanya menyaring tiga pasangan
     * jenis×status dan membuang sisanya tanpa jejak. Karena kolom status
     * bertipe text tanpa constraint, satu salah ketik di n8n cukup untuk
     * membuat permintaan hilang dari layar tanpa siapa pun tahu (E3).
     *
     * @param  array<int, array<string, mixed>>  $item
     * @return array<int, array<string, mixed>>
     */
    private function takDikenal(array $item): array
    {
        $dikenal = [
            'surat_tugas|menunggu',
            'surat_tugas|menunggu_ttd',
            'keterangan_aktif|menunggu',
        ];

        return array_values(array_filter($item, fn ($s) => ! in_array(
            ($s['jenis'] ?? '?') . '|' . ($s['status'] ?? '?'),
            $dikenal,
            true
        )));
    }

    /**
     * Nomor yang diketik staf hanyalah angka urut; n8n merangkainya menjadi
     * nomor lengkap seperti 800.1.11.1/67-SMKN.1-AT/2026. Pencocokan
     * karenanya mencari pola "/67-" di dalam nomor yang sudah terbit.
     *
     * Heuristik, bukan kepastian — dan memang cukup, karena hasilnya hanya
     * dipakai untuk memperingatkan.
     */
    private function nomorSudahDipakai(string $jenis, string $nomor): bool
    {
        $tabel = $jenis === 'surat_tugas' ? 'surat_tugas' : 'surat_keterangan';

        return DB::table($tabel)
            ->whereNotNull('no_surat')
            ->whereYear('created_at', now()->year)
            ->where('no_surat', 'like', '%/' . $nomor . '-%')
            ->exists();
    }
}
