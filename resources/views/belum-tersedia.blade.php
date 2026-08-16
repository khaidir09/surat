@extends('layouts.app')

@section('judul', 'Belum tersedia')

@section('isi')
    <div class="rounded-xl border border-garis bg-permukaan p-6">
        <p class="mb-2">
            Halaman <code class="rounded bg-latar px-1.5 py-0.5 text-sm">{{ $jalur }}</code>
            belum dikerjakan.
        </p>
        <p class="text-sm text-redup">
            Alamat dan pembatasan role-nya sudah final; isinya menyusul pada langkah
            berikutnya. Selama itu, dashboard lama masih dapat dipakai di
            <a href="/legacy/" class="text-pine underline">/legacy/</a>.
        </p>
    </div>
@endsection
