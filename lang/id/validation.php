<?php

/**
 * Pesan validasi bahasa Indonesia.
 *
 * Hanya memuat aturan yang benar-benar dipakai aplikasi ini, bukan salinan
 * lengkap berkas bawaan Laravel — aturan yang tidak dipakai akan jatuh ke
 * lang/en, yang masih terbaca, sementara daftar panjang berisi terjemahan
 * yang tidak pernah tampil justru menyulitkan pemeliharaan.
 *
 * Tanpa berkas ini, pesan tampil sebagai kunci mentah seperti
 * "validation.required": APP_LOCALE dan APP_FALLBACK_LOCALE sama-sama "id",
 * sehingga tidak ada tempat lain yang dapat dijadikan cadangan.
 */
return [

    'accepted' => ':attribute harus disetujui.',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang sah.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'different' => ':attribute dan :other harus berbeda.',
    'email' => ':attribute harus berupa alamat email yang sah.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'in' => ':attribute yang dipilih tidak sah.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'numeric' => ':attribute harus berupa angka.',
    'regex' => 'Format :attribute tidak sesuai.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah dipakai.',

    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],

    'min' => [
        'array' => ':attribute harus berisi minimal :min item.',
        'file' => ':attribute harus berukuran minimal :min kilobyte.',
        'numeric' => ':attribute harus bernilai minimal :min.',
        'string' => ':attribute harus terdiri dari minimal :min karakter.',
    ],

    /*
     * Pesan khusus per kolom, dipakai ketika pesan umum di atas terlalu
     * samar untuk menuntun perbaikan.
     */
    'custom' => [
        'aksi' => [
            'required' => 'Tidak diketahui tindakan apa yang diminta. Tekan tombol Setujui atau Tolak pada kartu surat.',
            'in' => 'Tindakan yang diminta tidak dikenal.',
        ],
        'nomor' => [
            'required' => 'Nomor urut surat wajib diisi saat menyetujui.',
            'regex' => 'Nomor urut harus berupa angka saja, sesuai buku agenda.',
        ],
        'tanggal' => [
            'date_format' => 'Tanggal surat harus dalam format TTTT-BB-HH, misalnya 2026-08-18.',
        ],
        'tanggal_selesai' => [
            'after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
        ],
        'password' => [
            'confirmed' => 'Ulangan kata sandi tidak sama.',
        ],
    ],

    /*
     * Nama kolom sebagaimana tertulis di layar, supaya pesan tidak berbunyi
     * "nis_nisn wajib diisi".
     */
    'attributes' => [
        'aksi' => 'Tindakan',
        'aktif' => 'Status aktif',
        'alamat' => 'Alamat',
        'cari' => 'Kata pencarian',
        'email' => 'Email',
        'estimasi' => 'Perkiraan kembali',
        'jabatan' => 'Jabatan',
        'jenis' => 'Jenis surat',
        'judul' => 'Judul kegiatan',
        'jurusan' => 'Jurusan',
        'kategori' => 'Kategori',
        'kelas' => 'Kelas',
        'keperluan' => 'Keperluan',
        'keterangan' => 'Keterangan',
        'nama' => 'Nama',
        'nama_orang_tua' => 'Nama orang tua',
        'nip' => 'NIP',
        'nis_nisn' => 'NIS/NISN',
        'nomor' => 'Nomor urut surat',
        'pangkat_golongan' => 'Pangkat/golongan',
        'password' => 'Kata sandi',
        'role' => 'Role',
        'status' => 'Status',
        'tahun_masuk' => 'Tahun masuk',
        'tanggal' => 'Tanggal surat',
        'tanggal_lahir' => 'Tanggal lahir',
        'tanggal_mulai' => 'Tanggal mulai',
        'tanggal_selesai' => 'Tanggal selesai',
        'tempat' => 'Tempat',
        'tempat_lahir' => 'Tempat lahir',
    ],

];
