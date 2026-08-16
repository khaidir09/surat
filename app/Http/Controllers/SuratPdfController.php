<?php

namespace App\Http\Controllers;

use App\Models\SuratKeterangan;
use App\Models\SuratTugas;
use App\Services\N8nClient;
use App\Services\N8nException;
use App\Services\SupabaseException;
use App\Services\SupabaseStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Mengantarkan PDF surat, dari antrean maupun dari riwayat.
 *
 * Kolom pdf_url TIDAK berisi URL. Isinya nama objek di bucket Supabase
 * ("SK-3.pdf"), sehingga menautkannya langsung dari Blade menghasilkan 404
 * — itulah cacat yang ditemukan 16 Agu 2026. Tautan harus selalu melewati
 * controller ini supaya ditandatangani sesaat sebelum dipakai.
 *
 * Kedua role boleh mengunduh; kepsek hanya tidak boleh memutuskan (§6).
 */
class SuratPdfController extends Controller
{
    public function __invoke(
        string $jenis,
        int $id,
        SupabaseStorage $penyimpanan,
        N8nClient $n8n
    ): RedirectResponse {
        $surat = $jenis === 'surat_tugas'
            ? SuratTugas::findOrFail($id)
            : SuratKeterangan::findOrFail($id);

        $objek = $surat->pdf_url;

        if (blank($objek)) {
            // E15: tidak dijadikan tautan yang membuka tab kosong.
            return back()->withErrors(['pdf' => "Berkas PDF surat #$id belum dibuat."]);
        }

        // Baris lama — atau perubahan di n8n kelak — bisa saja menyimpan URL
        // utuh alih-alih nama objek.
        if (Str::startsWith($objek, ['http://', 'https://'])) {
            return redirect()->away($objek);
        }

        if ($penyimpanan->terkonfigurasi()) {
            try {
                return redirect()->away($penyimpanan->urlBertandaTangan($objek));
            } catch (SupabaseException $e) {
                return back()->withErrors(['pdf' => $e->getMessage()]);
            }
        }

        return $this->lewatN8n($id, $n8n);
    }

    /**
     * Cadangan selama SUPABASE_SERVICE_KEY belum diisi.
     *
     * Webhook /surat-pdf hanya melayani surat tugas — untuk surat keterangan
     * ia membalas null, karena di dashboard lama tombol unduh memang tidak
     * pernah ada pada kartu surat keterangan.
     */
    private function lewatN8n(int $id, N8nClient $n8n): RedirectResponse
    {
        try {
            $url = $n8n->pdf($id);
        } catch (N8nException $e) {
            return back()->withErrors(['pdf' => $e->getMessage()]);
        }

        if (blank($url)) {
            return back()->withErrors(['pdf' => 'Berkas PDF belum dapat diambil. '
                . 'Admin perlu mengisi SUPABASE_SERVICE_KEY agar surat keterangan juga dapat diunduh.']);
        }

        return redirect()->away($url);
    }
}
