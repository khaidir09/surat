<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AntreanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\StatusKepsekController;
use App\Http\Controllers\SuratPdfController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Route mengikuti §7 docs/specs/dashboard-laravel-surat.md — seluruhnya terisi.
|
| Pembatasan role ditegakkan di sini, bukan dengan menyembunyikan tombol di
| Blade: menu yang disaring hanya merapikan tampilan, sedangkan permintaan
| yang ditembakkan langsung tetap harus ditolak.
|------------------------------------------------------------------------------
*/

// Nilai {jenis} mengikuti yang diterima webhook n8n, bukan nama tabel:
// surat keterangan dikirim sebagai "keterangan_aktif" (lihat config/n8n.php).
$jenis = array_values(config('n8n.jenis'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'form'])->name('login');

    // Dibatasi supaya kode akses tidak bisa ditebak dengan percobaan beruntun.
    Route::post('/login', [LoginController::class, 'masuk'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'keluar'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'aktif'])->group(function () use ($jenis) {

    Route::get('/', fn () => redirect(request()->user()->rutaBeranda()))->name('beranda');

    // --- Antrean persetujuan (langkah 6, selesai) -----------------------------
    Route::middleware('role:tas')->group(function () use ($jenis) {
        Route::get('/antrean', [AntreanController::class, 'index'])->name('antrean.index');

        Route::post('/antrean/{jenis}/{id}/putuskan', [AntreanController::class, 'putuskan'])
            ->whereIn('jenis', $jenis)->whereNumber('id')->name('antrean.putuskan');

        Route::post('/antrean/{jenis}/{id}/selesai', [AntreanController::class, 'selesai'])
            ->whereIn('jenis', $jenis)->whereNumber('id')->name('antrean.selesai');
    });

    // Dipakai antrean maupun riwayat, karena kolom pdf_url hanya berisi nama
    // objek dan wajib ditandatangani lebih dulu — menautkannya langsung dari
    // Blade menghasilkan 404. Kepsek boleh mengunduh, hanya tidak boleh
    // memutuskan (§6). Menyimpang dari §7 yang menempatkannya di bawah
    // /antrean saja.
    Route::get('/surat/{jenis}/{id}/pdf', SuratPdfController::class)
        ->whereIn('jenis', $jenis)->whereNumber('id')->name('surat.pdf');

    // --- Riwayat surat (langkah 3, selesai) -----------------------------------
    Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
    Route::get('/riwayat/{jenis}/{id}', [RiwayatController::class, 'show'])
        ->whereIn('jenis', $jenis)->whereNumber('id')->name('riwayat.show');

    // --- Data master (langkah 4, selesai) -------------------------------------
    // Kepsek boleh melihat agenda, tidak boleh mengubahnya (§6).
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');

    Route::middleware('role:tas')->group(function () {
        Route::resource('agenda', AgendaController::class)->except(['index', 'show']);
        Route::resource('pegawai', PegawaiController::class)->except('show')->parameters(['pegawai' => 'pegawai']);
        Route::resource('siswa', SiswaController::class)->except('show')->parameters(['siswa' => 'siswa']);
    });

    // --- Status kepala sekolah (langkah 5, selesai) ---------------------------
    // Kedua role boleh memperbarui (§6): kepsek mengabarkan sendiri, TAS
    // memperbarui atas nama beliau saat sedang tidak memegang perangkat.
    Route::get('/status-kepsek', [StatusKepsekController::class, 'form'])->name('status-kepsek.form');
    Route::post('/status-kepsek', [StatusKepsekController::class, 'store'])->name('status-kepsek.store');

    // --- Kelola pengguna (langkah 2, selesai) ---------------------------------
    Route::middleware('role:tas')->group(function () {
        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/create', [PenggunaController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{pengguna}/edit', [PenggunaController::class, 'edit'])->name('pengguna.edit');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->name('pengguna.update');
        Route::delete('/pengguna/{pengguna}', [PenggunaController::class, 'destroy'])->name('pengguna.destroy');
    });
});
