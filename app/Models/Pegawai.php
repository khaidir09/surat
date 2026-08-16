<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * public.pegawai — akan menjadi CRUD penuh pada langkah 4.
 *
 * Seluruh kolom selain id dan created_at nullable, termasuk nama (E11).
 * Tampilan tidak boleh mengandaikan satu pun terisi.
 */
class Pegawai extends Model
{
    protected $table = 'pegawai';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function namaTampil(): string
    {
        return filled($this->nama) ? $this->nama : "(tanpa nama, id {$this->id})";
    }
}
