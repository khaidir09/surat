<?php

namespace App\Providers;

use App\Services\N8nClient;
use App\Services\SupabaseStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu-satunya tempat konfigurasi n8n dibaca. Controller menerima
        // N8nClient lewat injeksi dan tidak pernah menyentuh config() sendiri,
        // sehingga tidak ada URL maupun secret yang tersebar (AC-6).
        $this->app->singleton(N8nClient::class, fn () => new N8nClient(
            baseUrl: config('n8n.base_url'),
            secret: config('n8n.secret'),
            timeout: config('n8n.timeout'),
            endpoint: config('n8n.endpoints'),
        ));

        $this->app->singleton(SupabaseStorage::class, fn () => new SupabaseStorage(
            url: config('supabase.url'),
            serviceKey: config('supabase.service_key'),
            bucket: config('supabase.bucket_surat'),
            ttl: config('supabase.ttl_url'),
        ));
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
