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

    protected function tugaskanPetugas(int $suratTugasId, int ...$pegawaiId): void
    {
        DB::table('surat_tugas_petugas')->insert(array_map(
            fn ($id) => ['surat_tugas_id' => $suratTugasId, 'pegawai_id' => $id],
            $pegawaiId
        ));
    }
}
