<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app.aktivitas — jejak siapa melakukan apa (K7, §5.4).
 *
 * Ini kompensasi atas keputusan tidak mengubah n8n (K6). Kolom approved_by
 * pada tabel surat diisi n8n dengan nilainya sendiri, bukan identitas staf
 * yang menekan tombol. Tanpa tabel ini, tidak ada satu pun tempat yang
 * mencatat siapa menyetujui surat atau siapa mengubah data siswa.
 *
 * Tabel ini hanya pernah ditulis dan dibaca, tidak pernah diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas', function (Blueprint $table) {
            $table->id();

            // Tanpa cascade delete: akun memang tidak pernah dihapus, hanya
            // dinonaktifkan, justru supaya baris di sini tetap punya nama.
            $table->foreignId('user_id')->constrained('users');

            $table->string('aksi');
            $table->string('subjek_tipe');
            $table->integer('subjek_id')->nullable();

            // Rincian yang berbeda-beda per aksi, misalnya nomor surat yang
            // diberikan atau kolom mana yang berubah.
            $table->jsonb('detail')->nullable();

            $table->string('ip')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Dua pola baca yang dipakai: riwayat satu objek, dan daftar
            // aktivitas terbaru secara keseluruhan.
            $table->index(['subjek_tipe', 'subjek_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas');
    }
};
