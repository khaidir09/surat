<?php

namespace Tests\Feature\Master;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

class SiswaTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_menambah_siswa(): void
    {
        $this->post('/siswa', [
            'nama' => 'Nadia Putri',
            'nis_nisn' => '0012345',
            'kelas' => 'X',
            'jurusan' => 'RPL',
            'aktif' => '1',
        ])->assertRedirect(route('siswa.index'));

        $this->assertDatabaseHas('siswa', ['nama' => 'Nadia Putri', 'kelas' => 'X']);
        $this->assertDatabaseHas('aktivitas', ['aksi' => 'buat_siswa']);
    }

    public function test_nis_harus_unik_bila_diisi(): void
    {
        Siswa::create(['nama' => 'Sudah Ada', 'nis_nisn' => '999', 'aktif' => true]);

        $this->post('/siswa', ['nama' => 'Kembar', 'nis_nisn' => '999'])
            ->assertSessionHasErrors('nis_nisn');
    }

    /**
     * E12: tanggal_lahir bertipe text dan berisi format bebas. Validasi date
     * akan menolak data yang selama ini dianggap sah.
     */
    public function test_tanggal_lahir_menerima_teks_bebas(): void
    {
        $this->post('/siswa', [
            'nama' => 'Format Aneh',
            'tanggal_lahir' => 'tgl 5 bln 3 th 2009',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('siswa', ['tanggal_lahir' => 'tgl 5 bln 3 th 2009']);
    }

    /** E7, E8: nama siswa harus tetap muncul di surat lama setelah dihapus. */
    public function test_hapus_soft_delete_dan_surat_lama_tetap_bernama(): void
    {
        $siswaId = $this->buatSiswa(['nama' => 'Alumni Sekali']);
        $suratId = $this->buatSuratKeterangan(['siswa_id' => $siswaId]);

        $this->delete("/siswa/$siswaId")->assertRedirect(route('siswa.index'));

        $this->assertSoftDeleted('siswa', ['id' => $siswaId]);

        // Pesan sukses menyebut nama yang baru dihapus, jadi flash-nya dibuang
        // dulu agar yang diperiksa benar-benar isi daftar.
        $this->flushSession();

        $this->get('/siswa')->assertDontSee('Alumni Sekali');

        $this->get("/riwayat/keterangan_aktif/$suratId")
            ->assertOk()
            ->assertSee('Alumni Sekali')
            ->assertSee('nonaktif');
    }

    public function test_filter_kelas_dan_jurusan(): void
    {
        $this->buatSiswa(['nama' => 'Anak RPL', 'kelas' => 'XI', 'jurusan' => 'RPL']);
        $this->buatSiswa(['nama' => 'Anak TKJ', 'kelas' => 'XII', 'jurusan' => 'TKJ']);

        $this->get('/siswa?jurusan=RPL')->assertSee('Anak RPL')->assertDontSee('Anak TKJ');
        $this->get('/siswa?kelas=XII')->assertSee('Anak TKJ')->assertDontSee('Anak RPL');
    }

    /** Siswa terhapus tidak boleh ikut mengunci NIS-nya. */
    public function test_nis_dapat_dipakai_ulang_setelah_siswa_dihapus(): void
    {
        $siswa = Siswa::create(['nama' => 'Pindah Sekolah', 'nis_nisn' => '555', 'aktif' => true]);
        $siswa->delete();

        $this->post('/siswa', ['nama' => 'Siswa Baru', 'nis_nisn' => '555'])
            ->assertSessionHasNoErrors();
    }
}
