<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penambahan kolom pada tabel milik n8n (§5.3). Ini tulisan struktural
 * pertama Laravel ke schema public.
 *
 * Aturan yang dipegang (K8): hanya menambah, tidak pernah mengubah tipe atau
 * menghapus. Setiap kolom nullable atau berdefault, sehingga:
 *   - baris lama tetap sah tanpa perlu diisi ulang, dan
 *   - query n8n yang menyebut kolomnya satu per satu tidak terpengaruh sama
 *     sekali. Workflow n8n tidak diubah dalam pekerjaan ini (K6).
 *
 * Nama tabel diawali "public." karena search_path menempatkan "app" lebih
 * dulu — tanpa awalan itu, Postgres bisa mencari di schema yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public.siswa', function (Blueprint $table) {
            // Menghapus siswa tidak boleh menghilangkan namanya dari surat
            // yang pernah terbit (E7).
            $table->softDeletesTz();

            // Berbeda dari deleted_at: siswa yang lulus tetap ada dan tetap
            // dapat dirujuk riwayat, hanya tidak lagi muncul sebagai pilihan.
            $table->boolean('aktif')->default(true);

            $table->integer('tahun_masuk')->nullable();
        });

        Schema::table('public.pegawai', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->boolean('aktif')->default(true);
        });

        Schema::table('public.agenda', function (Blueprint $table) {
            // Agenda sama sekali belum punya jejak waktu.
            //
            // Sengaja tanpa default now(): "add column ... default" mengisi
            // seluruh baris lama dengan waktu migrasi berjalan, sehingga 32
            // kegiatan yang sudah ada akan mengaku dibuat pada detik itu.
            // NULL jujur menyatakan "tidak diketahui"; Eloquent mengisi kolom
            // ini untuk baris baru, dan Laravel satu-satunya penulis agenda (K3).
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->softDeletesTz();

            $table->text('tempat')->nullable();
            $table->text('keterangan')->nullable();
        });

        // Menutup {{no}} dan {{ket}} pada template surat tugas yang selama ini
        // tidak punya sumber data. Karena n8n tidak diubah (K6), untuk
        // sementara kolom ini hanya diisi dan ditampilkan Laravel.
        Schema::table('public.surat_tugas_petugas', function (Blueprint $table) {
            $table->integer('urutan')->nullable();
            $table->text('ket')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public.siswa', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn(['aktif', 'tahun_masuk']);
        });

        Schema::table('public.pegawai', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn('aktif');
        });

        Schema::table('public.agenda', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn(['created_at', 'updated_at', 'tempat', 'keterangan']);
        });

        Schema::table('public.surat_tugas_petugas', function (Blueprint $table) {
            $table->dropColumn(['urutan', 'ket']);
        });
    }
};
