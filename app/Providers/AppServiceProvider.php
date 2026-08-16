<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tabel milik n8n di schema public sudah ada di produksi dan tidak
        // boleh dibuat ulang oleh Laravel. Di database pengujian tabel itu
        // belum ada, sehingga harus dibangun dari nol — hanya di sana.
        //
        // Pemisahan lewat direktori, bukan lewat percabangan di dalam
        // migration: "php artisan migrate" pada produksi tidak akan pernah
        // menemukan berkasnya, sehingga tidak ada yang bisa salah jalan.
        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(database_path('migrations/uji'));
        }
    }
}
