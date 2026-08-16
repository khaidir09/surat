<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membangun tabel milik n8n di schema public — HANYA untuk pengujian.
 *
 * Berkas ini berada di database/migrations/uji/ dan tidak pernah ikut
 * terbaca oleh "php artisan migrate" biasa; AppServiceProvider hanya
 * mendaftarkannya saat APP_ENV=testing. Di produksi tabel-tabel ini sudah
 * ada sejak lama, dibuat oleh n8n, dan tidak boleh disentuh Laravel (K1).
 *
 * Strukturnya menyalin §5.1 spesifikasi apa adanya, termasuk hal-hal yang
 * terasa keliru: siswa.tanggal_lahir bertipe text, hampir semua kolom
 * pegawai nullable, dan surat_tugas_petugas tanpa kolom urutan. Justru
 * itulah gunanya — tes harus menemui data seburuk yang ada di produksi.
 *
 * Nama tabel ditulis berawalan "public." karena search_path menempatkan
 * "app" lebih dulu; tanpa awalan itu tabel akan lahir di schema yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public.surat_tugas', function (Blueprint $table) {
            $table->increments('id');
            $table->text('no_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->text('kegiatan')->nullable();
            $table->text('tempat')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->text('dasar')->nullable();
            $table->text('penandatangan')->nullable();
            $table->bigInteger('pemohon_wa')->nullable();
            $table->text('status')->default('menunggu');
            $table->text('pdf_url')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->text('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('pembebanan_biaya')->nullable();
        });

        Schema::create('public.surat_keterangan', function (Blueprint $table) {
            $table->increments('id');
            $table->text('no_surat')->nullable();
            $table->integer('siswa_id')->nullable();
            $table->text('keperluan')->nullable();
            $table->bigInteger('pemohon_wa')->nullable();
            $table->text('status')->default('menunggu');
            $table->text('pdf_url')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->text('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
        });

        // Junction murni: tanpa primary key, tanpa kolom urutan. Inilah yang
        // membuat urutan petugas tidak terjamin (E14).
        Schema::create('public.surat_tugas_petugas', function (Blueprint $table) {
            $table->integer('surat_tugas_id');
            $table->integer('pegawai_id');
        });

        Schema::create('public.pegawai', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestampTz('created_at')->useCurrent();
            $table->string('nama')->nullable();
            $table->string('nip')->nullable();
            $table->string('pangkat_golongan')->nullable();
            $table->string('jabatan')->nullable();
        });

        Schema::create('public.siswa', function (Blueprint $table) {
            $table->increments('id');
            $table->text('nama');
            $table->text('tempat_lahir')->nullable();

            // Sengaja text, bukan date (K8, E12). Produksi menyimpannya
            // sebagai teks bebas dan Laravel tidak boleh mencoba mem-parse-nya.
            $table->text('tanggal_lahir')->nullable();

            $table->text('kelas')->nullable();
            $table->text('jurusan')->nullable();
            $table->text('nis_nisn')->nullable();
            $table->text('nama_orang_tua')->nullable();
            $table->text('alamat')->nullable();
        });

        Schema::create('public.agenda', function (Blueprint $table) {
            $table->increments('id');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('judul');
            $table->text('kategori')->nullable();
        });

        Schema::create('public.status_kepsek', function (Blueprint $table) {
            $table->integer('id')->default(1);
            $table->text('status')->default('Di sekolah');
            $table->text('keterangan')->nullable();
            $table->text('estimasi_kembali')->nullable();
            $table->timestampTz('diperbarui_pada')->useCurrent();
            $table->text('diperbarui_oleh')->nullable();
        });

        // counter, log_permintaan, dan sesi sengaja tidak dibuat: Laravel
        // tidak membaca maupun menulisnya (§5.1), jadi menghadirkannya di
        // sini hanya akan menyiratkan ketergantungan yang tidak ada.
    }

    public function down(): void
    {
        foreach ([
            'surat_tugas_petugas',
            'surat_tugas',
            'surat_keterangan',
            'pegawai',
            'siswa',
            'agenda',
            'status_kepsek',
        ] as $tabel) {
            Schema::dropIfExists("public.$tabel");
        }
    }
};
