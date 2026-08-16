<?php

namespace Tests\Feature\Master;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dukungan\MembuatDataSurat;
use Tests\TestCase;

class PegawaiTest extends TestCase
{
    use MembuatDataSurat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_menambah_pegawai(): void
    {
        $this->post('/pegawai', [
            'nama' => 'Rina Marlina',
            'nip' => '19850101',
            'jabatan' => 'Wakil Kepala Sekolah',
            'aktif' => '1',
        ])->assertRedirect(route('pegawai.index'));

        $this->assertDatabaseHas('pegawai', ['nama' => 'Rina Marlina', 'aktif' => true]);

        // Jejak audit adalah satu-satunya tempat identitas pengubah tercatat (K7).
        $this->assertDatabaseHas('aktivitas', ['aksi' => 'buat_pegawai', 'subjek_tipe' => 'pegawai']);
    }

    public function test_nama_wajib(): void
    {
        $this->post('/pegawai', ['nama' => ''])->assertSessionHasErrors('nama');
    }

    public function test_nip_harus_unik_bila_diisi(): void
    {
        Pegawai::create(['nama' => 'Sudah Ada', 'nip' => '12345', 'aktif' => true]);

        $this->post('/pegawai', ['nama' => 'Kembar', 'nip' => '12345'])
            ->assertSessionHasErrors('nip');
    }

    public function test_nip_boleh_kosong_untuk_lebih_dari_satu_pegawai(): void
    {
        Pegawai::create(['nama' => 'Non ASN Satu', 'nip' => null, 'aktif' => true]);

        $this->post('/pegawai', ['nama' => 'Non ASN Dua', 'nip' => ''])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pegawai', ['nama' => 'Non ASN Dua']);
    }

    public function test_mengubah_pegawai_tercatat(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Lama', 'aktif' => true]);

        $this->put("/pegawai/{$pegawai->id}", ['nama' => 'Baru', 'aktif' => '1'])
            ->assertRedirect(route('pegawai.index'));

        $this->assertSame('Baru', $pegawai->fresh()->nama);
        $this->assertDatabaseHas('aktivitas', ['aksi' => 'ubah_pegawai', 'subjek_id' => $pegawai->id]);
    }

    /**
     * E7: menghapus pegawai tidak boleh menghilangkan namanya dari surat tugas
     * yang pernah terbit.
     */
    public function test_hapus_bersifat_soft_delete_dan_riwayat_tetap_utuh(): void
    {
        $pegawaiId = $this->buatPegawai(['nama' => 'Pernah Bertugas']);
        $suratId = $this->buatSuratTugas();
        $this->tugaskanPetugas($suratId, $pegawaiId);

        $this->delete("/pegawai/$pegawaiId")->assertRedirect(route('pegawai.index'));

        $this->assertSoftDeleted('pegawai', ['id' => $pegawaiId]);

        // Pesan sukses menyebut nama yang baru dihapus.
        $this->flushSession();

        $this->get('/pegawai')->assertDontSee('Pernah Bertugas');
        $this->get("/riwayat/surat_tugas/$suratId")->assertOk()->assertSee('Pernah Bertugas');
    }

    /** E11: nama boleh NULL pada data lama; daftar tidak boleh gagal karenanya. */
    public function test_daftar_tahan_nama_null(): void
    {
        $id = $this->buatPegawai(['nama' => null]);

        $this->get('/pegawai')->assertOk()->assertSee("(tanpa nama, id $id)");
    }

    public function test_pencarian_dan_filter_status(): void
    {
        $this->buatPegawai(['nama' => 'Ahmad Guru', 'aktif' => true]);
        $this->buatPegawai(['nama' => 'Budi Pensiun', 'aktif' => false]);

        $this->get('/pegawai?cari=ahmad')->assertSee('Ahmad Guru')->assertDontSee('Budi Pensiun');
        $this->get('/pegawai?status=nonaktif')->assertSee('Budi Pensiun')->assertDontSee('Ahmad Guru');
    }
}
