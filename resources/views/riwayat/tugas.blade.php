@extends('layouts.app')

@section('judul', 'Surat tugas')

@section('isi')
    <p class="mb-4 text-sm">
        <a href="{{ url()->previous() === url()->current() ? route('riwayat.index') : url()->previous() }}"
           class="text-pine hover:underline">&larr; Kembali ke riwayat</a>
    </p>

    <dl class="rounded-xl border border-garis bg-permukaan px-5 py-2">
        <x-baris label="Nomor surat" :nilai="$surat->no_surat" kosong="belum bernomor" />
        <x-baris label="Tanggal surat" :nilai="$surat->tanggal_surat?->translatedFormat('j F Y')" />
        <x-baris label="Kegiatan" :nilai="$surat->kegiatan" />
        <x-baris label="Tempat" :nilai="$surat->tempat" />

        <x-baris label="Pelaksanaan">
            @if ($surat->tanggal_mulai || $surat->tanggal_selesai)
                {{ $surat->tanggal_mulai?->translatedFormat('j F Y') ?? '?' }}
                &ndash;
                {{ $surat->tanggal_selesai?->translatedFormat('j F Y') ?? '?' }}
            @endif
        </x-baris>

        <x-baris label="Dasar" :nilai="$surat->dasar" />
        <x-baris label="Pembebanan biaya" :nilai="$surat->pembebanan_biaya" />
        <x-baris label="Penandatangan" :nilai="$surat->penandatangan" />

        <x-baris label="Petugas">
            @if ($surat->petugas->isEmpty())
                {{-- Junction boleh kosong (E13). --}}
                <span class="text-redup">belum ada petugas</span>
            @else
                <ol class="list-decimal space-y-1 ps-5">
                    @foreach ($surat->petugas as $orang)
                        <li>
                            {{ $orang->namaTampil() }}
                            @if (filled($orang->nip))
                                <span class="text-redup">— NIP {{ $orang->nip }}</span>
                            @endif
                            @if (filled($orang->jabatan))
                                <span class="text-redup">, {{ $orang->jabatan }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-2 text-xs text-redup">
                    Urutan mengikuti nomor pegawai, bukan urutan pada surat: tabel
                    penghubung di n8n belum menyimpan urutan.
                </p>
            @endif
        </x-baris>

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
