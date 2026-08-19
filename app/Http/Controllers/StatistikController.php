<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Ringkasan angka untuk pimpinan (docs/specs/statistik-surat.md).
 *
 * Sepenuhnya baca-saja dan sepenuhnya dari database — halaman ini tidak
 * memanggil n8n sama sekali. Statistik yang bergantung pada webhook akan ikut
 * mati setiap kali n8n mati, padahal angkanya sudah ada di Supabase (K11).
 *
 * Query ditulis sebagai method privat di sini, bukan di kelas service
 * tersendiri, mengikuti bentuk RiwayatController (K-S8).
 */
class StatistikController extends Controller
{
    private const JENIS_TUGAS = 'surat_tugas';

    private const JENIS_KETERANGAN = 'keterangan_aktif';

    /**
     * Sebuah surat disebut "terbit" bila sudah bernomor, bukan bila statusnya
     * termasuk daftar tertentu (K-S1).
     *
     * n8n hanya memberi nomor pada surat yang disetujui, sehingga satu
     * predikat ini menjawab keduanya tanpa menyebut satu pun nilai status —
     * termasuk nilai penolakan, yang sampai hari ini belum pernah muncul
     * sebagai baris sungguhan.
     *
     * btrim + nullif, bukan sekadar "is not null": kolomnya text yang diisi
     * dari luar Laravel, jadi string kosong dan spasi tunggal adalah
     * kemungkinan nyata (E-S4).
     */
    private const TERBIT = "nullif(btrim(no_surat), '')";

    /** Banyaknya hari pada grafik tren, termasuk hari ini. */
    private const RENTANG_TREN = 30;

    public function index(): View
    {
        $surat = $this->hitunganSurat();
        $tren = $this->trenHarian();
        $status = $this->rekapStatus();
        $warna = $this->warna();

        return view('statistik.index', [
            'terbit' => $surat['terbit'],
            'permintaan' => $surat['permintaan'],
            'komposisi' => $surat['komposisi'],
            'proses' => $this->rataWaktuProses(),
            'chatbot' => $this->permintaanChatbot(),
            'status' => $status,
            'tren' => $tren,
            'tanpaTanggal' => $this->suratTanpaTanggal(),
            'warna' => $warna,

            // Payload grafik dirakit di sini, bukan di dalam @json pada Blade.
            // Direktif itu memecah ekspresinya dengan explode(',') untuk
            // mengambil argumen flags dan depth, sehingga array literal yang
            // punya lebih dari satu elemen ikut terpotong — kuncinya hilang
            // dan tanda kurungnya tidak pernah tertutup. Gejalanya berupa
            // ParseError yang menunjuk berkas hasil kompilasi, bukan Blade-nya.
            // @json aman hanya untuk satu variabel tunggal.
            'grafik' => [
                'komposisi' => $surat['komposisi'],
                'tren' => $tren,
                'status' => $status,
                'warna' => $warna,
            ],
        ]);
    }

    /**
     * Total permintaan dan total terbit, sekaligus pecahannya per jenis.
     *
     * Digabung dalam satu query, bukan empat: donat memecah angka yang sama
     * dengan kartu di atasnya (K-S4), dan menghitungnya lewat query terpisah
     * membuka peluang keduanya tidak cocok di layar yang sama.
     *
     * @return array{terbit: int, permintaan: int, komposisi: array<int, array{jenis: string, label: string, terbit: int}>}
     */
    private function hitunganSurat(): array
    {
        $gabungan = DB::table('surat_tugas')
            ->selectRaw(sprintf("'%s' as jenis, no_surat", self::JENIS_TUGAS))
            ->unionAll(
                DB::table('surat_keterangan')
                    ->selectRaw(sprintf("'%s' as jenis, no_surat", self::JENIS_KETERANGAN))
            );

        $baris = DB::query()
            ->fromSub($gabungan, 'surat')
            ->selectRaw('jenis, count(*) as permintaan, count(' . self::TERBIT . ') as terbit')
            ->groupBy('jenis')
            ->get()
            ->keyBy('jenis');

        $komposisi = [];

        foreach ([self::JENIS_TUGAS => 'Surat tugas', self::JENIS_KETERANGAN => 'Surat keterangan'] as $jenis => $label) {
            $komposisi[] = [
                'jenis' => $jenis,
                'label' => $label,
                // Jenis yang belum punya satu baris pun tetap disebut dengan
                // nilai nol, bukan dihilangkan dari daftar: legenda yang
                // berubah-ubah isinya lebih membingungkan daripada irisan nol.
                'terbit' => (int) ($baris[$jenis]->terbit ?? 0),
            ];
        }

        return [
            'terbit' => (int) $baris->sum('terbit'),
            'permintaan' => (int) $baris->sum('permintaan'),
            'komposisi' => $komposisi,
        ];
    }

    /**
     * Lama sebuah surat tugas menunggu keputusan (K-S3).
     *
     * Hanya surat tugas: surat keterangan langsung menjadi selesai setelah
     * disetujui dan tidak punya tahap menunggu yang setara.
     *
     * @return array{nilai: string|null, jumlah: int}
     */
    private function rataWaktuProses(): array
    {
        $hasil = DB::table('surat_tugas')
            ->selectRaw('avg(extract(epoch from (approved_at - created_at))) as detik, count(*) as jumlah')
            ->whereNotNull('approved_at')
            ->whereNotNull('created_at')
            // Nomor surat boleh di-backfill dengan tanggal lampau, sehingga
            // durasi negatif bukan hal mustahil. Satu baris saja cukup untuk
            // menarik rata-rata ke arah yang menyesatkan (E-S6).
            ->whereRaw('approved_at >= created_at')
            ->first();

        return [
            'nilai' => $hasil?->detik !== null ? $this->formatDurasi((float) $hasil->detik) : null,
            'jumlah' => (int) ($hasil->jumlah ?? 0),
        ];
    }

    /**
     * Durasi dalam satuan yang terbaca manusia.
     *
     * Rata-rata di bawah semenit tidak boleh tampil sebagai "0 menit" (E-S9):
     * nol terbaca sebagai "prosesnya instan", padahal artinya "cepat sekali"
     * — dan pada data sekecil ini, bisa juga berarti satu surat yang
     * disetujui sedetik setelah masuk. Di ujung lain, "53 jam" menuntut
     * pembacanya membagi sendiri (E-S10).
     */
    private function formatDurasi(float $detik): string
    {
        $detik = max(0, (int) round($detik));

        if ($detik < 60) {
            return $detik . ' detik';
        }

        if ($detik < 3600) {
            return intdiv($detik, 60) . ' menit';
        }

        if ($detik < 86400) {
            $jam = intdiv($detik, 3600);
            $menit = intdiv($detik % 3600, 60);

            return $menit > 0 ? "$jam jam $menit menit" : "$jam jam";
        }

        $hari = intdiv($detik, 86400);
        $jam = intdiv($detik % 86400, 3600);

        return $jam > 0 ? "$hari hari $jam jam" : "$hari hari";
    }

    /**
     * Berapa kali surat diajukan lewat chatbot (K-S12).
     *
     * Bukan count(*): satu baris log_permintaan adalah satu PERPINDAHAN
     * STATUS, bukan satu permintaan — 20 pengajuan meninggalkan 39 baris di
     * produksi. Menghitung seluruh barisnya menghasilkan angka yang benar
     * secara aritmetika dan salah secara arti (R-S9).
     *
     * Tabel ini milik workflow chatbot dan Laravel tidak punya kesepakatan
     * apa pun atasnya (R-S10). Kegagalannya karena itu ditangkap di sini:
     * enam metrik lain tidak boleh ikut hilang hanya karena n8n mengganti
     * nama tabel telemetrinya (E-S14).
     *
     * @return array{tersedia: bool, jumlah: int, sunyi: bool}
     */
    private function permintaanChatbot(): array
    {
        try {
            $diajukan = DB::table('log_permintaan')->where('status', 'diajukan')->count();
            $seluruh = DB::table('log_permintaan')->count();
        } catch (QueryException $e) {
            Log::warning('log_permintaan tidak terbaca', ['sebab' => $e->getMessage()]);

            return ['tersedia' => false, 'jumlah' => 0, 'sunyi' => false];
        }

        return [
            'tersedia' => true,
            'jumlah' => $diajukan,

            // Log terisi tetapi tidak satu pun bertanda "diajukan" — pertanda
            // n8n mengganti nama peristiwanya. Angka nol yang muncul karena
            // asumsi kita basi tidak boleh terlihat sama dengan nol yang
            // berarti memang belum ada pengajuan (E-S15).
            'sunyi' => $diajukan === 0 && $seluruh > 0,
        ];
    }

    /**
     * Jumlah surat per status, apa adanya (K-S2).
     *
     * Tanpa daftar nilai yang sah di dalam kode: kolomnya text tanpa
     * constraint, dan dua dari empat nilai yang "diketahui" pun belum pernah
     * terlihat sebagai baris. Status yang belum dikenal harus tetap muncul,
     * bukan hilang tanpa jejak (E3, E-S5).
     *
     * @return array<int, array{status: string, jumlah: int}>
     */
    private function rekapStatus(): array
    {
        $gabungan = DB::table('surat_tugas')->select('status')
            ->unionAll(DB::table('surat_keterangan')->select('status'));

        return DB::query()
            ->fromSub($gabungan, 'surat')
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->orderByDesc('jumlah')
            ->orderBy('status')
            ->get()
            ->map(fn ($b) => ['status' => (string) $b->status, 'jumlah' => (int) $b->jumlah])
            ->all();
    }

    /**
     * Permintaan masuk per hari WITA, selalu sepanjang RENTANG_TREN hari.
     *
     * Deretnya dibangun di PHP dan baru diisi dari hasil query, bukan diambil
     * apa adanya dari SQL: hari tanpa surat harus muncul sebagai nol, karena
     * jarak antar-titik yang tidak lagi mewakili waktu membuat grafik garis
     * berbohong tentang kerapatan (E-S8).
     *
     * @return array{tanggal: array<int, string>, seri: array<int, array{jenis: string, label: string, data: array<int, int>}>}
     */
    private function trenHarian(): array
    {
        $mulai = Carbon::today()->subDays(self::RENTANG_TREN - 1);

        $tanggal = [];
        for ($h = 0; $h < self::RENTANG_TREN; $h++) {
            $tanggal[] = $mulai->copy()->addDays($h)->toDateString();
        }

        $seri = [];

        foreach ([
            self::JENIS_TUGAS => ['surat_tugas', 'Surat tugas'],
            self::JENIS_KETERANGAN => ['surat_keterangan', 'Surat keterangan'],
        ] as $jenis => [$tabel, $label]) {
            $per = $this->hitungPerHari($tabel, $mulai);

            $seri[] = [
                'jenis' => $jenis,
                'label' => $label,
                'data' => array_map(fn ($t) => (int) ($per[$t] ?? 0), $tanggal),
            ];
        }

        return ['tanggal' => $tanggal, 'seri' => $seri];
    }

    /**
     * @return array<string, int>
     */
    private function hitungPerHari(string $tabel, Carbon $mulai): array
    {
        $tanggal = $this->tanggalWita('created_at');

        return DB::table($tabel)
            ->selectRaw("$tanggal as tanggal, count(*) as jumlah")
            ->whereNotNull('created_at')
            ->whereRaw("$tanggal >= ?", [$mulai->toDateString()])
            ->groupByRaw($tanggal)
            ->pluck('jumlah', 'tanggal')
            ->all();
    }

    /**
     * Surat yang tidak dapat digambar pada tren karena tidak punya tanggal.
     *
     * surat_keterangan.created_at nullable tanpa default, jadi ini bukan
     * kemungkinan teoretis. Melewatinya diam-diam berarti grafik menampilkan
     * lebih sedikit surat daripada kartu di atasnya tanpa penjelasan (E-S3).
     */
    private function suratTanpaTanggal(): int
    {
        return DB::table('surat_tugas')->whereNull('created_at')->count()
            + DB::table('surat_keterangan')->whereNull('created_at')->count();
    }

    /**
     * Pengelompokan per hari harus mengikuti WITA, bukan timezone sesi.
     *
     * config('app.timezone') sudah menyetel timezone sesi Postgres lewat
     * config/database.php, sehingga sebenarnya "::date" saja pun sudah benar
     * hari ini. Ditulis eksplisit justru supaya tetap benar bila kelak ada
     * yang mengubah salah satunya — dan supaya pembaca query ini tidak perlu
     * mengetahui isi konfigurasi untuk memahami hasilnya.
     *
     * Nilai timezone berasal dari konfigurasi, bukan dari permintaan
     * pengguna, sehingga aman disisipkan ke SQL — pola yang sama dengan
     * konstanta tanggal di RiwayatController.
     */
    private function tanggalWita(string $kolom): string
    {
        return "($kolom at time zone '" . config('app.timezone') . "')::date";
    }

    /**
     * Warna per status, diambil dari token resources/css/app.css.
     *
     * Hanya kosmetik: status di luar daftar ini tetap tampil dengan warna
     * netral dan labelnya apa adanya. Warna yang tidak ada tidak boleh
     * membuat barisnya ikut hilang (K-S2).
     *
     * Nilai heksadesimalnya ditulis di sini, bukan dibaca dari CSS oleh
     * JavaScript: getComputedStyle terhadap custom property Tailwind 4 tidak
     * dapat diandalkan berjalan sebelum grafik digambar.
     *
     * @return array<string, string>
     */
    private function warna(): array
    {
        return [
            'menunggu' => '#a9721a',        // amber
            'menunggu_ttd' => '#1f5f8b',    // info
            'selesai' => '#1f7a43',         // setuju
            'ditolak' => '#b23a32',         // tolak
            self::JENIS_TUGAS => '#16584a', // pine
            self::JENIS_KETERANGAN => '#6d3b8e', // ket
            'lainnya' => '#5c6b63',         // redup

            // Dipakai grafik untuk sumbu dan garis bantu, supaya kanvas tidak
            // memakai abu-abu bawaan Chart.js yang tidak ada di palet mana pun.
            'teks' => '#5c6b63',            // redup
            'garis' => '#e1e6e0',           // garis
        ];
    }
}
