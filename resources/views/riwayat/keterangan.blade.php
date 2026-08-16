@extends('layouts.app')

@section('judul', 'Surat keterangan aktif')

@section('isi')
    <p class="mb-4 text-sm">
        <a href="{{ route('riwayat.index') }}" class="text-pine hover:underline">&larr; Kembali ke riwayat</a>
    </p>

    <dl class="rounded-xl border border-garis bg-permukaan px-5 py-2">
        <x-baris label="Nomor surat" :nilai="$surat->no_surat" kosong="belum bernomor" />

        <x-baris label="Siswa">
            @if ($surat->siswa)
                {{ $surat->siswa->nama }}
            @elseif ($surat->siswa_id)
                {{-- Tanpa foreign key, siswa_id bisa menunjuk baris yang sudah
                     tidak ada. Nomornya tetap ditampilkan agar dapat ditelusuri. --}}
                <span class="text-redup">data siswa tidak ditemukan (id {{ $surat->siswa_id }})</span>
            @else
                <span class="text-redup">—</span>
            @endif
        </x-baris>

        <x-baris label="Kelas / Jurusan" :nilai="$surat->siswa?->kelasJurusan()" />
        <x-baris label="NIS / NISN" :nilai="$surat->siswa?->nis_nisn" />
        <x-baris label="Tempat lahir" :nilai="$surat->siswa?->tempat_lahir" />

        {{-- Ditampilkan sebagai teks tanpa diformat: kolomnya bertipe text dan
             isinya format bebas (E12). --}}
        <x-baris label="Tanggal lahir" :nilai="$surat->siswa?->tanggal_lahir" />

        <x-baris label="Nama orang tua" :nilai="$surat->siswa?->nama_orang_tua" />
        <x-baris label="Alamat" :nilai="$surat->siswa?->alamat" />
        <x-baris label="Keperluan" :nilai="$surat->keperluan" />
        <x-baris label="Status" :nilai="$surat->status" />
        <x-baris label="Disetujui oleh" :nilai="$surat->approved_by" />
        <x-baris label="Disetujui pada" :nilai="$surat->approved_at?->translatedFormat('j F Y, H:i')" />
        <x-baris label="Dibuat" :nilai="$surat->created_at?->translatedFormat('j F Y, H:i')" />

        <x-baris label="Berkas PDF">
            @if (filled($surat->pdf_url))
                <a href="{{ $surat->pdf_url }}" target="_blank" rel="noopener" class="text-pine hover:underline">
                    Unduh PDF
                </a>
            @else
                <span class="text-redup">belum tersedia</span>
            @endif
        </x-baris>
    </dl>
@endsection
