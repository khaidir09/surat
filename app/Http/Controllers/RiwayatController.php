<?php

namespace App\Http\Controllers;

use App\Models\SuratKeterangan;
use App\Models\SuratTugas;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Riwayat surat yang sudah diproses (AC-3). Sepenuhnya baca-saja.
 */
class RiwayatController extends Controller
{
    private const JENIS_TUGAS = 'surat_tugas';

    private const JENIS_KETERANGAN = 'keterangan_aktif';

    /**
     * Tanggal surat tugas boleh kosong pada baris lama, jadi jatuh kembali ke
     * tanggal pembuatan agar barisnya tetap terurut dan tetap terjaring filter
     * rentang tanggal.
     */
    private const TANGGAL_TUGAS = 'coalesce(surat_tugas.tanggal_surat, surat_tugas.created_at::date)';

    private const TANGGAL_KETERANGAN = 'surat_keterangan.created_at::date';

    public function index(Request $request): View
    {
        $saring = $request->validate([
            'jenis' => ['nullable', 'in:' . self::JENIS_TUGAS . ',' . self::JENIS_KETERANGAN],
            'status' => ['nullable', 'string', 'max:100'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        $bagian = [];

        if (($saring['jenis'] ?? null) !== self::JENIS_KETERANGAN) {
            $bagian[] = $this->kueriSuratTugas($saring);
        }

        if (($saring['jenis'] ?? null) !== self::JENIS_TUGAS) {
            $bagian[] = $this->kueriSuratKeterangan($saring);
        }

        $gabungan = array_shift($bagian);

        foreach ($bagian as $tambahan) {
            $gabungan->unionAll($tambahan);
        }

        // Digabung di SQL, bukan di PHP: paginasi harus dihitung dari kedua
        // tabel sekaligus, dan mengambil semuanya lebih dulu untuk diurutkan
        // di memori persis melanggar E17.
        $surat = DB::query()
            ->fromSub($gabungan, 'surat')
            ->orderByRaw('tanggal desc nulls last')
            ->orderByRaw('created_at desc nulls last')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('riwayat.index', [
            'surat' => $surat,
            'saring' => $saring,
            'statusTersedia' => $this->statusTersedia(),
        ]);
    }

    public function show(string $jenis, int $id): View
    {
        if ($jenis === self::JENIS_TUGAS) {
            return view('riwayat.tugas', [
                'surat' => SuratTugas::with('petugas')->findOrFail($id),
            ]);
        }

        return view('riwayat.keterangan', [
            'surat' => SuratKeterangan::with('siswa')->findOrFail($id),
        ]);
    }

    /**
     * @param  array<string, mixed>  $saring
     */
    private function kueriSuratTugas(array $saring): Builder
    {
        $kueri = DB::table('surat_tugas')->selectRaw(sprintf(
            "'%s' as jenis, surat_tugas.id, surat_tugas.no_surat, surat_tugas.kegiatan as judul,
             %s as tanggal, surat_tugas.status, surat_tugas.pdf_url, surat_tugas.created_at",
            self::JENIS_TUGAS,
            self::TANGGAL_TUGAS
        ));

        $this->terapkanSaringan($kueri, $saring, self::TANGGAL_TUGAS);

        if (filled($saring['cari'] ?? null)) {
            $kata = '%' . $saring['cari'] . '%';
            $kueri->where(function (Builder $q) use ($kata) {
                $q->where('surat_tugas.no_surat', 'ilike', $kata)
                    ->orWhere('surat_tugas.kegiatan', 'ilike', $kata)
                    ->orWhere('surat_tugas.tempat', 'ilike', $kata);
            });
        }

        return $kueri;
    }

    /**
     * @param  array<string, mixed>  $saring
     */
    private function kueriSuratKeterangan(array $saring): Builder
    {
        $kueri = DB::table('surat_keterangan')
            ->leftJoin('siswa', 'siswa.id', '=', 'surat_keterangan.siswa_id')
            ->selectRaw(sprintf(
                "'%s' as jenis, surat_keterangan.id, surat_keterangan.no_surat, siswa.nama as judul,
                 %s as tanggal, surat_keterangan.status, surat_keterangan.pdf_url, surat_keterangan.created_at",
                self::JENIS_KETERANGAN,
                self::TANGGAL_KETERANGAN
            ));

        $this->terapkanSaringan($kueri, $saring, self::TANGGAL_KETERANGAN);

        if (filled($saring['cari'] ?? null)) {
            $kata = '%' . $saring['cari'] . '%';
            $kueri->where(function (Builder $q) use ($kata) {
                $q->where('surat_keterangan.no_surat', 'ilike', $kata)
                    ->orWhere('surat_keterangan.keperluan', 'ilike', $kata)
                    ->orWhere('siswa.nama', 'ilike', $kata);
            });
        }

        return $kueri;
    }

    /**
     * @param  array<string, mixed>  $saring
     */
    private function terapkanSaringan(Builder $kueri, array $saring, string $ekspresiTanggal): void
    {
        // Dicocokkan apa adanya, tanpa daftar nilai yang sah di dalam kode:
        // status apa pun yang benar-benar ada di database harus bisa disaring,
        // termasuk yang belum kita kenal (K12, E3).
        if (filled($saring['status'] ?? null)) {
            $kueri->where('status', $saring['status']);
        }

        if (filled($saring['dari'] ?? null)) {
            $kueri->whereRaw("$ekspresiTanggal >= ?", [$saring['dari']]);
        }

        if (filled($saring['sampai'] ?? null)) {
            $kueri->whereRaw("$ekspresiTanggal <= ?", [$saring['sampai']]);
        }
    }

    /**
     * Pilihan filter status dibangun dari isi database, bukan dari daftar
     * tetap (K12). Nilai untuk surat yang ditolak belum diketahui (§14.1);
     * begitu n8n mulai memakainya, pilihannya muncul sendiri di sini tanpa
     * ada kode yang perlu diubah.
     *
     * @return array<int, string>
     */
    private function statusTersedia(): array
    {
        return DB::table('surat_tugas')
            ->select('status')
            ->union(DB::table('surat_keterangan')->select('status'))
            ->pluck('status')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
