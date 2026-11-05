<?php

namespace Tests\Feature\Antrean;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TampilanTest extends TestCase
{
    use RefreshDatabase;

    private function tas(): User
    {
        return User::factory()->create();
    }

    /** @param array<int, array<string, mixed>> $item */
    private function antrean(array $item): void
    {
        Http::fake(['*' => Http::response(['items' => $item])]);
    }

    public function test_membagi_menjadi_tiga_seksi_seperti_dashboard_lama(): void
    {
        $this->antrean([
            ['id' => 1, 'jenis' => 'surat_tugas', 'status' => 'menunggu', 'kegiatan' => 'Bimtek Media'],
            ['id' => 2, 'jenis' => 'surat_tugas', 'status' => 'menunggu_ttd', 'kegiatan' => 'Rapat Dinas'],
            ['id' => 3, 'jenis' => 'keterangan_aktif', 'status' => 'menunggu', 'nama_siswa' => 'Elmira Hafizah'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Surat Tugas — Menunggu Persetujuan')
            ->assertSee('Surat Tugas — Menunggu TTD Kepsek')
            ->assertSee('Surat Keterangan — Menunggu Persetujuan')
            ->assertSee('Bimtek Media')
            ->assertSee('Rapat Dinas')
            ->assertSee('Elmira Hafizah');
    }

    /**
     * E3: render() pada index.html:573-602 membuang item yang tidak cocok
     * dengan tiga pasangan jenis×status tanpa jejak. Karena kolom status
     * bertipe text tanpa constraint, satu salah ketik di n8n cukup untuk
     * menghilangkan sebuah permintaan dari layar.
     */
    public function test_status_tak_dikenal_ditampilkan_bukan_dibuang(): void
    {
        $this->antrean([
            ['id' => 9, 'jenis' => 'surat_tugas', 'status' => 'menunggu_tttd', 'kegiatan' => 'Salah Ketik'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Status tidak dikenal')
            ->assertSee('menunggu_tttd')
            ->assertSee('Salah Ketik');
    }

    public function test_jenis_tak_dikenal_juga_ditampilkan(): void
    {
        $this->antrean([
            ['id' => 4, 'jenis' => 'surat_izin', 'status' => 'menunggu'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Status tidak dikenal')
            ->assertSee('surat_izin');
    }

    public function test_antrean_kosong(): void
    {
        $this->antrean([]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Belum ada permintaan yang perlu diproses');
    }

    /**
     * n8n mati tidak boleh terbaca sebagai "tidak ada pekerjaan" — itu
     * membuat staf pulang dengan tenang padahal antrean menumpuk.
     */
    public function test_n8n_mati_menampilkan_kegagalan_bukan_antrean_kosong(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Daftar tidak dapat dimuat')
            ->assertDontSee('Belum ada permintaan yang perlu diproses');
    }

    public function test_kartu_tugas_tanpa_petugas(): void
    {
        $this->antrean([
            ['id' => 5, 'jenis' => 'surat_tugas', 'status' => 'menunggu', 'petugas' => []],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Belum ada petugas');
    }

    public function test_kartu_menunggu_ttd_menampilkan_aksi_yang_berbeda(): void
    {
        $this->antrean([
            ['id' => 6, 'jenis' => 'surat_tugas', 'status' => 'menunggu_ttd'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Unduh PDF')
            ->assertSee('Sudah ditandatangani')
            ->assertDontSee('Setujui &amp; terbitkan', escape: false);
    }

    /**
     * Tanggal surat terisi hari ini karena itulah yang benar hampir setiap
     * kali, tetapi tetap sebagai input yang dapat diubah — bukan nilai mati.
     */
    public function test_kartu_keputusan_menyediakan_tanggal_terisi_hari_ini(): void
    {
        $this->antrean([
            ['id' => 7, 'jenis' => 'surat_tugas', 'status' => 'menunggu'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertSee('Tanggal surat')
            ->assertSee('name="tanggal" type="date"', escape: false)
            ->assertSee('value="' . now()->toDateString() . '"', escape: false);
    }

    /** Kartu menunggu TTD tidak memutuskan apa pun, jadi tanpa isian tanggal. */
    public function test_kartu_menunggu_ttd_tanpa_isian_tanggal(): void
    {
        $this->antrean([
            ['id' => 8, 'jenis' => 'surat_tugas', 'status' => 'menunggu_ttd'],
        ]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertDontSee('Tanggal surat');
    }

    /** AC-1: kredensial server tidak boleh sampai ke browser. */
    public function test_secret_tidak_muncul_di_halaman(): void
    {
        config(['n8n.secret' => 'rahasia-antrean']);

        $this->antrean([['id' => 1, 'jenis' => 'surat_tugas', 'status' => 'menunggu']]);

        $this->actingAs($this->tas())->get('/antrean')
            ->assertOk()
            ->assertDontSee('rahasia-antrean');
    }
}
