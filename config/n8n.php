<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Integrasi n8n
    |---------------------------------------------------------------------------
    |
    | n8n memegang seluruh logika surat: persetujuan, penomoran, pembuatan PDF,
    | dan notifikasi ke pemohon (K1). Laravel hanya memanggil webhook-nya.
    |
    | Nilai-nilai ini sebelumnya tertanam di dalam index.html baris 451-456 dan
    | 722. Bentuk payload tiap endpoint TIDAK BOLEH diubah — workflow n8n tidak
    | disentuh dalam pekerjaan ini (K6).
    |
    */

    'base_url' => env('N8N_BASE_URL', 'https://n8n.dewakreatif.com/webhook'),

    /*
    | Dikirim sebagai header "x-dashboard-secret" pada setiap permintaan.
    |
    | Dulu diketik staf di gate dashboard dan hidup di variabel `secret` pada
    | browser. Sekarang menjadi kredensial milik server dan tidak pernah sampai
    | ke klien (K5). Konsekuensinya: respons 401 dari n8n berarti kredensial
    | server yang bermasalah, BUKAN pengguna yang salah kode — jadi jangan
    | melempar pengguna keluar seperti kunciUlang() dulu.
    */
    'secret' => env('N8N_SECRET'),

    'timeout' => (int) env('N8N_TIMEOUT', 15),

    /*
    | Path relatif terhadap base_url. Lihat §8 pada spesifikasi.
    */
    'endpoints' => [
        'pending'   => '/surat-pending',    // GET
        'keputusan' => '/surat-keputusan',  // POST {id, jenis, aksi, nomor}
        'pdf'       => '/surat-pdf',        // GET  ?id=
        'selesai'   => '/surat-selesai',    // POST {id}
        'kepsek'    => '/status-kepsek',    // POST {status, estimasi, keterangan}
    ],

    /*
    | Nilai "jenis" yang diterima webhook /surat-keputusan.
    |
    | Perhatikan: nilai untuk surat keterangan adalah "keterangan_aktif",
    | BUKAN "surat_keterangan" seperti nama tabelnya. Ketidakcocokan ini berasal
    | dari n8n dan harus dipertahankan (§5.2 pada spesifikasi).
    */
    'jenis' => [
        'surat_tugas'      => 'surat_tugas',
        'surat_keterangan' => 'keterangan_aktif',
    ],

];
