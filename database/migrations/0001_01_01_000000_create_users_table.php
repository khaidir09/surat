<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hanya staf TAS dan kepala sekolah yang punya akun. Guru dan siswa
        // berinteraksi lewat chatbot, jadi tidak ada registrasi mandiri dan
        // tidak ada verifikasi email — kolom email_verified_at sengaja tidak
        // dibuat agar tabel tidak menjanjikan alur yang tak pernah ada.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');

            // Divalidasi di sisi aplikasi lewat User::ROLE. Sengaja tidak
            // memakai tipe enum Postgres: menambah role baru nanti tidak
            // boleh menuntut ALTER TYPE pada database milik bersama.
            $table->string('role');

            // Menonaktifkan akun, bukan menghapusnya — supaya baris
            // app.aktivitas yang menunjuk pengguna ini tetap punya nama.
            $table->boolean('aktif')->default(true);

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
