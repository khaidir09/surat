<?php

namespace Tests\Feature\Statistik;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

/**
 * Angka pada halaman statistik (docs/specs/statistik-surat.md §12).
 *
 * Dua fokus yang diminta: kebenaran angka setiap metrik, dan batas hari
 * menurut WITA. Ketahanan terhadap NULL serta otorisasi kedua role sengaja
 * TIDAK dicakup di sini — keduanya diverifikasi manual lewat V-S2 dan V-S3
 * (R-S5). Kalau kelak salah satunya masuk cakupan, tempatnya di berkas ini.
 */
class StatistikTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    // -- Metrik 1 dan 3: terbit dan permintaan masuk ---------------------------

    public function test_terbit_hanya_menghitung_surat_yang_sudah_bernomor(): void
    {
        $this->buatSuratTugas(['no_surat' => '800.1.11.1/67-SMKN.1-AT/2026']);
        $this->buatSuratKeterangan(['no_surat' => '400.3.12.1/89-SMKN.1-AT/2026']);

        // Ketiganya belum bernomor, masing-masing dengan cara yang berbeda.
        // String kosong dan spasi datang dari luar Laravel, jadi bukan
        // kemungkinan teoretis (E-S4).
        $this->buatSuratTugas(['no_surat' => null]);
        $this->buatSuratTugas(['no_surat' => '']);
        $this->buatSuratTugas(['no_surat' => '   ']);

        $isi = $this->muat();

        $this->assertSame('2', $this->kartu($isi, 'terbit'));
        $this->assertSame('5', $this->kartu($isi, 'permintaan'));
    }

    public function test_surat_ditolak_tidak_terbit_tetapi_tetap_terhitung_dan_terlihat(): void
    {
        // Sampai hari ini belum pernah ada surat ditolak di produksi; nilainya
        // diketahui dari pemilik sistem, bukan dari baris sungguhan. Justru
        // karena itu perilakunya diuji: yang menentukan bukan nilai statusnya,
        // melainkan bahwa surat ditolak tidak pernah diberi nomor (K-S1).
        $this->buatSuratTugas(['no_surat' => null, 'status' => 'ditolak']);
        $this->buatSuratTugas(['no_surat' => '421/1', 'status' => 'selesai']);

        $isi = $this->muat();

        $this->assertSame('1', $this->kartu($isi, 'terbit'));
        $this->assertSame('2', $this->kartu($isi, 'permintaan'));

        $this->assertSame(
            ['ditolak' => 1, 'selesai' => 1],
            $this->rekapStatus($isi),
            'Surat ditolak harus tetap terlihat di panel status.'
        );
    }

    // -- Metrik 2: rata-rata waktu proses -------------------------------------

    public function test_rata_rata_waktu_proses_dihitung_dari_surat_yang_layak_saja(): void
    {
        $awal = Carbon::parse('2026-08-10 08:00:00');

        // Dua jam dan empat jam -> rata-rata tepat tiga jam.
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => $awal->copy()->addHours(2)]);
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => $awal->copy()->addHours(4)]);

        // Belum diputuskan: tidak boleh ikut menarik rata-rata (E-S2).
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => null]);

        // Backfill dengan tanggal lampau menghasilkan durasi negatif. Satu
        // baris saja cukup untuk menyeret rata-rata (E-S6).
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => $awal->copy()->subDay()]);

        $isi = $this->muat();

        $this->assertSame('3 jam', $this->kartu($isi, 'proses'));
        $this->assertStringContainsString('dari 2 surat tugas', $isi);
    }

    public function test_satuan_rata_rata_mengikuti_besarnya(): void
    {
        $awal = Carbon::parse('2026-08-10 08:00:00');

        // Di bawah semenit tidak boleh tampil sebagai "0 menit": nol terbaca
        // sebagai "prosesnya instan" (E-S9).
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => $awal->copy()->addSeconds(48)]);

        $this->assertSame('48 detik', $this->kartu($this->muat(), 'proses'));
    }

    public function test_rata_rata_lebih_dari_sehari_ditampilkan_dalam_hari(): void
    {
        $awal = Carbon::parse('2026-08-10 08:00:00');

        // "53 jam" menuntut pembacanya membagi sendiri (E-S10).
        $this->buatSuratTugas(['created_at' => $awal, 'approved_at' => $awal->copy()->addHours(53)]);

        $this->assertSame('2 hari 5 jam', $this->kartu($this->muat(), 'proses'));
    }

    // -- Metrik 4: komposisi ---------------------------------------------------

    public function test_komposisi_memecah_angka_yang_sama_dengan_kartu_terbit(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/1']);
        $this->buatSuratTugas(['no_surat' => '421/2']);
        $this->buatSuratTugas(['no_surat' => null]);
        $this->buatSuratKeterangan(['no_surat' => '422/1']);

        $isi = $this->muat();
        $komposisi = $this->grafik($isi)['komposisi'];

        $this->assertSame(
            ['surat_tugas' => 2, 'keterangan_aktif' => 1],
            array_column($komposisi, 'terbit', 'jenis')
        );

        // K-S4: dua angka berbeda untuk hal yang sama di satu layar akan
        // membuat pembacanya menganggap salah satunya bug.
        $this->assertSame(
            (int) $this->kartu($isi, 'terbit'),
            array_sum(array_column($komposisi, 'terbit'))
        );
    }

    // -- Metrik 6: status ------------------------------------------------------

    public function test_status_yang_belum_dikenal_tetap_muncul(): void
    {
        $this->buatSuratTugas(['status' => 'selesai']);
        $this->buatSuratTugas(['status' => 'menunggu']);

        // Kolomnya text tanpa constraint. Satu salah ketik di n8n tidak boleh
        // membuat surat hilang dari layar tanpa jejak (K-S2, E-S5, E3).
        $this->buatSuratKeterangan(['status' => 'entah_apa']);

        $isi = $this->muat();

        $this->assertSame(
            ['entah_apa' => 1, 'menunggu' => 1, 'selesai' => 1],
            $this->rekapStatus($isi)
        );

        $this->assertStringContainsString('entah_apa', $isi);
    }

    public function test_jumlah_panel_status_sama_dengan_permintaan_masuk(): void
    {
        $this->buatSuratTugas(['status' => 'selesai']);
        $this->buatSuratTugas(['status' => 'menunggu_ttd']);
        $this->buatSuratKeterangan(['status' => 'selesai']);

        $isi = $this->muat();

        $this->assertSame(
            (int) $this->kartu($isi, 'permintaan'),
            array_sum($this->rekapStatus($isi))
        );
    }

    // -- Metrik 7: permintaan lewat chatbot ------------------------------------

    public function test_chatbot_menghitung_pengajuan_bukan_peristiwa(): void
    {
        // Dua pengajuan meninggalkan lima baris: inilah bentuk yang membuat
        // count(*) menghasilkan angka benar secara aritmetika dan salah
        // secara arti (R-S9).
        $this->buatPengajuanChatbot('surat_tugas', ['menunggu_ttd', 'diterbitkan']);
        $this->buatPengajuanChatbot('keterangan_aktif', ['diterbitkan']);

        $this->assertSame('2', $this->kartu($this->muat(), 'chatbot'));
    }

    // -- Metrik 5: tren harian -------------------------------------------------

    public function test_surat_pagi_hari_masuk_ke_tanggal_wita_bukan_hari_sebelumnya(): void
    {
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00', 'Asia/Makassar'));

        // 23:30 UTC pada 18 Agustus adalah 07:30 WITA pada 19 Agustus —
        // jam kerja yang sangat biasa di sekolah. Selama aplikasi memakai UTC,
        // surat ini tercatat sebagai kemarin (E-S7, K-S7).
        $this->buatSuratTugas(['created_at' => '2026-08-18 23:30:00+00']);

        $tren = $this->grafik($this->muat())['tren'];
        $per = array_combine($tren['tanggal'], $tren['seri'][0]['data']);

        $this->assertSame(1, $per['2026-08-19'], 'Surat 07:30 WITA harus jatuh pada tanggal WITA-nya.');
        $this->assertSame(0, $per['2026-08-18']);
    }

    public function test_tren_selalu_tiga_puluh_hari_dan_hari_kosong_bernilai_nol(): void
    {
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00', 'Asia/Makassar'));

        $this->buatSuratTugas(['created_at' => '2026-08-19 03:00:00+00']);   // 11:00 WITA hari ini
        $this->buatSuratKeterangan(['created_at' => '2026-08-15 03:00:00+00']);

        // Di luar rentang: 30 hari terakhir berakhir pada 21 Juli.
        $this->buatSuratTugas(['created_at' => '2026-07-01 03:00:00+00']);

        $tren = $this->grafik($this->muat())['tren'];

        $this->assertCount(30, $tren['tanggal']);
        $this->assertSame('2026-07-21', $tren['tanggal'][0]);
        $this->assertSame('2026-08-19', end($tren['tanggal']));

        $tugas = array_combine($tren['tanggal'], $tren['seri'][0]['data']);
        $keterangan = array_combine($tren['tanggal'], $tren['seri'][1]['data']);

        $this->assertSame(1, $tugas['2026-08-19']);
        $this->assertSame(1, $keterangan['2026-08-15']);

        // Hari tanpa surat hadir sebagai nol, bukan dilewati — jarak
        // antar-titik harus tetap mewakili waktu (E-S8).
        $this->assertSame(0, $tugas['2026-08-18']);
        $this->assertSame(30, count($tugas), 'Tidak boleh ada hari yang hilang dari deret.');

        // Surat 1 Juli tidak tergambar, tetapi tetap terhitung di kartu.
        $this->assertSame('3', $this->kartu($this->muat(), 'permintaan'));
    }

    // -- Penolong --------------------------------------------------------------

    private function muat(): string
    {
        return $this->get('/statistik')->assertOk()->getContent();
    }

    /**
     * Nilai sebuah kartu angka, dibaca lewat kait data-kartu.
     */
    private function kartu(string $isi, string $kunci): string
    {
        $cocok = preg_match('#data-kartu="' . preg_quote($kunci, '#') . '"[^>]*>(.*?)</p>#s', $isi, $bagian);

        $this->assertSame(1, $cocok, "Kartu \"$kunci\" tidak ditemukan di halaman.");

        return trim($bagian[1]);
    }

    /**
     * Isi blok JSON yang juga dipakai grafik (K-S6).
     *
     * Kanvas Chart.js tidak menghasilkan teks yang dapat diperiksa assertSee;
     * blok ini bisa, dan ia data yang sama persis dengan yang digambar.
     *
     * @return array<string, mixed>
     */
    private function grafik(string $isi): array
    {
        $cocok = preg_match(
            '#<script type="application/json" id="data-statistik">(.*?)</script>#s',
            $isi,
            $bagian
        );

        $this->assertSame(1, $cocok, 'Blok data-statistik tidak ditemukan di halaman.');

        return json_decode(html_entity_decode($bagian[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, int>
     */
    private function rekapStatus(string $isi): array
    {
        $status = array_column($this->grafik($isi)['status'], 'jumlah', 'status');
        ksort($status);

        return $status;
    }
}
