<?php

namespace Tests\Feature\Riwayat;

use App\Models\SuratKeterangan;
use App\Models\SuratTugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

class RiwayatTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_menampilkan_kedua_jenis_surat(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/100', 'kegiatan' => 'Lomba LKS']);

        $siswa = $this->buatSiswa(['nama' => 'Rahmawati']);
        $this->buatSuratKeterangan(['no_surat' => '422/200', 'siswa_id' => $siswa]);

        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('421/100')
            ->assertSee('Lomba LKS')
            ->assertSee('422/200')
            ->assertSee('Rahmawati');
    }

    public function test_terbaru_di_atas(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/LAMA', 'tanggal_surat' => '2026-01-01']);
        $this->buatSuratTugas(['no_surat' => '421/BARU', 'tanggal_surat' => '2026-08-01']);

        $isi = $this->get('/riwayat')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($isi, '421/LAMA'),
            strpos($isi, '421/BARU'),
            'Surat terbaru harus muncul lebih dulu.'
        );
    }

    public function test_filter_jenis(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/300']);
        $this->buatSuratKeterangan(['no_surat' => '422/400', 'siswa_id' => $this->buatSiswa()]);

        $this->get('/riwayat?jenis=surat_tugas')
            ->assertSee('421/300')
            ->assertDontSee('422/400');

        $this->get('/riwayat?jenis=keterangan_aktif')
            ->assertSee('422/400')
            ->assertDontSee('421/300');
    }

    public function test_filter_status_dan_rentang_tanggal(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/SELESAI', 'status' => 'selesai', 'tanggal_surat' => '2026-03-10']);
        $this->buatSuratTugas(['no_surat' => '421/TUNGGU', 'status' => 'menunggu', 'tanggal_surat' => '2026-07-10']);

        $this->get('/riwayat?status=menunggu')
            ->assertSee('421/TUNGGU')
            ->assertDontSee('421/SELESAI');

        $this->get('/riwayat?dari=2026-07-01&sampai=2026-07-31')
            ->assertSee('421/TUNGGU')
            ->assertDontSee('421/SELESAI');
    }

    public function test_pencarian_menjangkau_nama_siswa(): void
    {
        $siswa = $this->buatSiswa(['nama' => 'Zulkifli Hasan']);
        $this->buatSuratKeterangan(['no_surat' => '422/500', 'siswa_id' => $siswa]);
        $this->buatSuratTugas(['no_surat' => '421/600', 'kegiatan' => 'Workshop']);

        $this->get('/riwayat?cari=zulkifli')
            ->assertSee('422/500')
            ->assertDontSee('421/600');
    }

    /**
     * K12: pilihan status dibangun dari isi database. Nilai untuk surat yang
     * ditolak belum diketahui (§14.1), jadi kode tidak boleh punya daftar tetap.
     */
    public function test_pilihan_status_dibangun_dari_data(): void
    {
        $this->buatSuratTugas(['status' => 'ditolak_oleh_kepsek']);

        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('ditolak_oleh_kepsek');
    }

    /**
     * E3: cacat index.html yang menyembunyikan status tak dikenal tidak boleh
     * ikut terbawa.
     */
    public function test_status_tak_dikenal_tetap_tampil(): void
    {
        $this->buatSuratTugas(['no_surat' => '421/ANEH', 'status' => 'entah_apa']);

        $this->get('/riwayat')
            ->assertSee('421/ANEH')
            ->assertSee('entah_apa');
    }

    /** E17: daftar tidak pernah memuat seluruh tabel. */
    public function test_daftar_terpaginasi(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->buatSuratTugas();
        }

        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('30 surat ditemukan')
            ->assertSee('pagination', escape: false);
    }

    /** E9, E10, E11: seluruh kolom bisa NULL. */
    public function test_nilai_null_tidak_membuat_halaman_gagal(): void
    {
        $this->buatSuratTugas([
            'no_surat' => null,
            'kegiatan' => null,
            'tanggal_surat' => null,
            'pdf_url' => null,
        ]);

        $this->buatSuratKeterangan(['no_surat' => null, 'siswa_id' => null]);

        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('belum bernomor');
    }

    public function test_detail_surat_tugas_menampilkan_petugas(): void
    {
        $surat = $this->buatSuratTugas(['no_surat' => '421/700']);
        $satu = $this->buatPegawai(['nama' => 'Ahmad Fauzi']);
        $dua = $this->buatPegawai(['nama' => null]);

        $this->tugaskanPetugas($surat, $dua, $satu);

        $this->get("/riwayat/surat_tugas/$surat")
            ->assertOk()
            ->assertSee('421/700')
            ->assertSee('Ahmad Fauzi')
            // Pegawai tanpa nama tetap harus terlihat, bukan menjadi baris kosong.
            ->assertSee("(tanpa nama, id $dua)");
    }

    /** E13: junction boleh kosong. */
    public function test_surat_tugas_tanpa_petugas(): void
    {
        $surat = $this->buatSuratTugas();

        $this->get("/riwayat/surat_tugas/$surat")
            ->assertOk()
            ->assertSee('belum ada petugas');
    }

    /** E8: siswa_id boleh menunjuk baris yang tidak ada. */
    public function test_detail_keterangan_dengan_siswa_hilang(): void
    {
        $surat = $this->buatSuratKeterangan(['siswa_id' => 999999]);

        $this->get("/riwayat/keterangan_aktif/$surat")
            ->assertOk()
            ->assertSee('data siswa tidak ditemukan (id 999999)');
    }

    /** E12: tanggal lahir ditampilkan apa adanya, tanpa di-parse. */
    public function test_tanggal_lahir_teks_bebas_ditampilkan_apa_adanya(): void
    {
        $siswa = $this->buatSiswa(['tanggal_lahir' => 'tgl 5 bln 3 th 2009']);
        $surat = $this->buatSuratKeterangan(['siswa_id' => $siswa]);

        $this->get("/riwayat/keterangan_aktif/$surat")
            ->assertOk()
            ->assertSee('tgl 5 bln 3 th 2009');
    }

    public function test_surat_yang_tidak_ada_menghasilkan_404(): void
    {
        $this->get('/riwayat/surat_tugas/999999')->assertNotFound();
    }

    /**
     * AC-3: Laravel tidak pernah menulis ke tabel surat. Dijaga oleh model,
     * bukan hanya oleh kesepakatan.
     */
    public function test_model_surat_menolak_ditulis(): void
    {
        $id = $this->buatSuratTugas();
        $surat = SuratTugas::findOrFail($id);

        $this->expectException(LogicException::class);

        $surat->status = 'selesai';
        $surat->save();
    }

    public function test_model_surat_keterangan_menolak_dihapus(): void
    {
        $id = $this->buatSuratKeterangan();

        $this->expectException(LogicException::class);

        SuratKeterangan::findOrFail($id)->delete();
    }
}
