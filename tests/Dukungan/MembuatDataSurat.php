<?php

namespace Tests\Dukungan;

use Illuminate\Support\Facades\DB;

/**
 * Menyisipkan baris ke tabel milik n8n untuk keperluan pengujian.
 *
 * Sengaja lewat query builder, bukan factory Eloquent: model SuratTugas dan
 * SuratKeterangan memakai trait HanyaBaca yang melempar pengecualian pada
 * setiap penulisan. Itu memang yang diinginkan — dan tes tidak boleh
 * melemahkan penjaganya demi kenyamanan sendiri. Cara ini juga lebih jujur
 * menirukan kenyataan: baris-baris ini memang lahir dari luar Laravel.
 */
trait MembuatDataSurat
{
    /** @param array<string, mixed> $atribut */
    protected function buatSuratTugas(array $atribut = []): int
    {
        return DB::table('surat_tugas')->insertGetId(array_merge([
            'no_surat' => '421/' . fake()->unique()->numberBetween(1, 9999),
            'tanggal_surat' => '2026-08-01',
            'kegiatan' => 'Rapat koordinasi kurikulum',
            'tempat' => 'Aula SMKN 1 Amuntai',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-02',
            'status' => 'selesai',
            'created_at' => now(),
        ], $atribut));
    }

    /** @param array<string, mixed> $atribut */
    protected function buatSuratKeterangan(array $atribut = []): int
    {
        return DB::table('surat_keterangan')->insertGetId(array_merge([
            'no_surat' => '422/' . fake()->unique()->numberBetween(1, 9999),
            'keperluan' => 'Pendaftaran beasiswa',
            'status' => 'selesai',
            'created_at' => now(),
        ], $atribut));
    }

    /** @param array<string, mixed> $atribut */
    protected function buatSiswa(array $atribut = []): int
    {
        return DB::table('siswa')->insertGetId(array_merge([
            'nama' => 'Siti Aminah',
            'kelas' => 'XI',
            'jurusan' => 'TKJ',
            'nis_nisn' => (string) fake()->unique()->numberBetween(10000, 99999),
            'tanggal_lahir' => '17 Agustus 2008',
        ], $atribut));
    }

    /** @param array<string, mixed> $atribut */
    protected function buatPegawai(array $atribut = []): int
    {
        return DB::table('pegawai')->insertGetId(array_merge([
            'nama' => 'Budi Santoso',
            'nip' => (string) fake()->unique()->numberBetween(100000, 999999),
            'jabatan' => 'Guru',
            'created_at' => now(),
        ], $atribut));
    }

    /**
     * Satu pengajuan lewat chatbot, beserta jejak lanjutannya.
     *
     * Sengaja menyisipkan LEBIH DARI SATU baris. Di produksi log_permintaan
     * mencatat setiap perpindahan status, sehingga 20 pengajuan meninggalkan
     * 39 baris. Penolong yang hanya menyisipkan satu baris per pengajuan akan
     * membuat tes hijau untuk bentuk data yang tidak pernah ada di sana —
     * dan metrik yang menghitung seluruh baris akan lolos tanpa ketahuan.
     *
     * @param  array<int, string>  $lanjutan  peristiwa sesudah pengajuan
     */
    protected function buatPengajuanChatbot(
        string $jenis = 'surat_tugas',
        array $lanjutan = ['menunggu_ttd'],
    ): void {
        $baris = [['jenis_surat' => $jenis, 'status' => 'diajukan', 'waktu' => now()]];

        foreach ($lanjutan as $status) {
            $baris[] = ['jenis_surat' => $jenis, 'status' => $status, 'waktu' => now()];
        }

        DB::table('log_permintaan')->insert($baris);
    }

    /**
     * Pertanyaan yang dijawab chatbot tanpa menghasilkan surat.
     *
     * Terpisah dari buatPengajuanChatbot karena bentuknya memang berbeda:
     * pertanyaan tidak punya daur hidup dan meninggalkan satu baris saja.
     * Penolong yang menyamakan keduanya akan menyembunyikan justru bedanya
     * — dan bedanya itulah yang menentukan cara menghitungnya.
     *
     * $status dapat diisi selain "dijawab" untuk menirukan pertanyaan yang
     * tercatat tetapi belum terjawab.
     */
    protected function buatTanyaChatbot(string $jenis, int $jumlah = 1, string $status = 'dijawab'): void
    {
        DB::table('log_permintaan')->insert(array_fill(0, $jumlah, [
            'jenis_surat' => $jenis,
            'status' => $status,
            'waktu' => now(),
        ]));
    }

    protected function tugaskanPetugas(int $suratTugasId, int ...$pegawaiId): void
    {
        DB::table('surat_tugas_petugas')->insert(array_map(
            fn ($id) => ['surat_tugas_id' => $suratTugasId, 'pegawai_id' => $id],
            $pegawaiId
        ));
    }
}
