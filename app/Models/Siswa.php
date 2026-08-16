<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * public.siswa — akan menjadi CRUD penuh pada langkah 4. Untuk sekarang
 * hanya dibaca oleh halaman riwayat.
 */
class Siswa extends Model
{
    protected $table = 'siswa';

    protected $guarded = ['id'];

    /**
     * Tabel n8n tidak punya kolom created_at/updated_at. Kolom itu baru
     * ditambahkan pada langkah 4 bersama soft delete (§5.3).
     */
    public $timestamps = false;

    /**
     * tanggal_lahir sengaja tidak di-cast ke date: kolomnya bertipe text
     * dan isinya format bebas hasil ketikan lewat chatbot (E12, K8).
     * Mencoba mem-parse-nya akan melempar pengecualian pada data yang
     * selama ini dianggap sah.
     */
    public function kelasJurusan(): string
    {
        return trim(implode(' ', array_filter([$this->kelas, $this->jurusan]))) ?: '—';
    }
}
