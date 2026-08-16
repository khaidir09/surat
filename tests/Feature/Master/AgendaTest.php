<?php

namespace Tests\Feature\Master;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_menambah_kegiatan(): void
    {
        $this->post('/agenda', [
            'judul' => 'Rapat Dinas',
            'tanggal_mulai' => '2026-09-10',
            'tanggal_selesai' => '2026-09-10',
            'tempat' => 'Aula',
        ])->assertRedirect(route('agenda.index', ['bulan' => '2026-09']));

        $this->assertDatabaseHas('agenda', ['judul' => 'Rapat Dinas']);
        $this->assertDatabaseHas('aktivitas', ['aksi' => 'buat_agenda']);
    }

    /** E18: rentang terbalik ditolak, tidak diam-diam ditukar. */
    public function test_tanggal_selesai_tidak_boleh_mendahului_mulai(): void
    {
        $this->post('/agenda', [
            'judul' => 'Terbalik',
            'tanggal_mulai' => '2026-09-10',
            'tanggal_selesai' => '2026-09-01',
        ])->assertSessionHasErrors('tanggal_selesai');

        $this->assertDatabaseMissing('agenda', ['judul' => 'Terbalik']);
    }

    public function test_judul_wajib(): void
    {
        $this->post('/agenda', [
            'judul' => '',
            'tanggal_mulai' => '2026-09-10',
            'tanggal_selesai' => '2026-09-10',
        ])->assertSessionHasErrors('judul');
    }

    public function test_hapus_bersifat_soft_delete(): void
    {
        $agenda = Agenda::create([
            'judul' => 'Batal Dilaksanakan',
            'tanggal_mulai' => '2026-09-10',
            'tanggal_selesai' => '2026-09-10',
        ]);

        $this->delete("/agenda/{$agenda->id}")->assertRedirect();

        $this->assertSoftDeleted('agenda', ['id' => $agenda->id]);

        // Pesan sukses menyebut judul yang baru dihapus.
        $this->flushSession();

        $this->get('/agenda?bulan=2026-09')->assertDontSee('Batal Dilaksanakan');
    }

    /**
     * Kegiatan yang melintasi pergantian bulan harus terlihat di kedua bulan,
     * bukan hanya di bulan tanggal mulainya.
     */
    public function test_kegiatan_lintas_bulan_muncul_di_kedua_bulan(): void
    {
        Agenda::create([
            'judul' => 'Ujian Semester',
            'tanggal_mulai' => '2026-09-28',
            'tanggal_selesai' => '2026-10-03',
        ]);

        $this->get('/agenda?bulan=2026-09')->assertSee('Ujian Semester');
        $this->get('/agenda?bulan=2026-10')->assertSee('Ujian Semester');
        $this->get('/agenda?bulan=2026-11')->assertDontSee('Ujian Semester');
    }

    public function test_kepsek_melihat_agenda_tanpa_tombol_ubah(): void
    {
        Agenda::create([
            'judul' => 'Upacara Bendera',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
        ]);

        $this->actingAs(User::factory()->kepsek()->create())
            ->get('/agenda')
            ->assertOk()
            ->assertSee('Upacara Bendera')
            ->assertDontSee('Tambah kegiatan');
    }
}
