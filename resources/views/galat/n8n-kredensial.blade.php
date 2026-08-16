@extends('layouts.app')

@section('judul', 'Layanan surat menolak kredensial server')

@section('isi')
    <div class="max-w-2xl rounded-xl border border-tolak/30 bg-tolak/5 p-6">
        <p class="mb-3 text-sm">{{ $pesan }}</p>

        <p class="mb-3 text-sm text-redup">
            Ini bukan kesalahan Anda dan bukan karena sesi Anda berakhir. Kode
            akses ke layanan surat kini tersimpan di server, bukan diketik
            pengguna, sehingga keluar dan masuk kembali tidak akan mengubah
            apa pun.
        </p>

        <p class="mb-4 text-sm text-redup">
            Sampaikan ke admin bahwa <code class="rounded bg-latar px-1.5 py-0.5">N8N_SECRET</code>
            pada server perlu diperiksa. Selama itu, data yang sudah tersimpan
            tetap dapat dilihat &mdash; hanya pengiriman ke layanan surat yang
            terhenti.
        </p>

        <a href="{{ route('beranda') }}" class="text-sm text-pine hover:underline">&larr; Kembali</a>
    </div>
@endsection
