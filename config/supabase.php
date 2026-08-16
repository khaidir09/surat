<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Supabase Storage
    |---------------------------------------------------------------------------
    |
    | Berkas PDF surat dibuat n8n dan disimpan di bucket Supabase Storage.
    | Kolom pdf_url pada tabel surat TIDAK berisi URL, melainkan nama objek
    | saja (mis. "SK-3.pdf") — menautkannya langsung dari halaman akan
    | menghasilkan 404.
    |
    | Bucket-nya privat, jadi setiap permintaan unduh perlu URL bertanda
    | tangan yang dibuat sesaat sebelum dipakai.
    |
    */

    'url' => env('SUPABASE_URL'),

    /*
    | Kunci service_role. Berkewenangan penuh dan melewati seluruh RLS, jadi
    | ia hanya boleh hidup di server dan tidak pernah dikirim ke browser —
    | ketentuan yang sama dengan N8N_SECRET (K5).
    */
    'service_key' => env('SUPABASE_SERVICE_KEY'),

    'bucket_surat' => env('SUPABASE_BUCKET_SURAT', 'surat'),

    /*
    | Masa berlaku URL bertanda tangan, dalam detik.
    |
    | n8n memakai 60 detik, yang terlalu pendek: staf yang mengeklik unduh
    | lalu memilih folder penyimpanan bisa kehabisan waktu di tengah jalan.
    | Lima menit cukup longgar untuk itu dan tetap terlalu singkat untuk
    | disebarkan ulang secara berarti.
    */
    'ttl_url' => (int) env('SUPABASE_TTL_URL', 300),

];
