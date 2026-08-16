<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Menutup semua jalur tulis Eloquent pada sebuah model.
 *
 * Status surat adalah milik n8n (K1). Kalau Laravel ikut menulisnya, ada dua
 * sumber kebenaran untuk satu kolom yang sama, dan surat bisa berpindah
 * status tanpa notifikasi ke pemohon terkirim.
 *
 * $guarded = ['*'] saja tidak cukup: itu hanya menghalangi mass assignment,
 * sementara $surat->status = 'selesai'; $surat->save(); tetap lolos. Penjaga
 * ini berupa pengecualian yang dilempar, bukan pengabaian diam-diam, supaya
 * kesalahan seperti itu tertangkap saat pengujian dan bukan di produksi.
 */
trait HanyaBaca
{
    public static function bootHanyaBaca(): void
    {
        // "saving" ikut dijaga karena ia satu-satunya yang selalu terpicu:
        // save() pada model yang tidak berubah melewati "updating" begitu saja,
        // sehingga penjaga tanpa "saving" punya celah yang tidak kentara.
        foreach (['saving', 'creating', 'updating', 'deleting'] as $peristiwa) {
            static::registerModelEvent($peristiwa, function ($model) use ($peristiwa) {
                throw new LogicException(sprintf(
                    '%s hanya boleh dibaca. Perubahan pada %s dikerjakan n8n lewat webhook, bukan lewat Eloquent (%s).',
                    class_basename($model),
                    $model->getTable(),
                    $peristiwa
                ));
            });
        }
    }
}
