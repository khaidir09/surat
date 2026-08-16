@props([
    'nama',
    'label',
    'tipe' => 'text',
    'nilai' => null,
    'wajib' => false,
    'daftar' => null,
    'petunjuk' => null,
])

@php
    $isi = old($nama, $nilai);
    $idDaftar = $daftar ? "daftar-$nama" : null;
@endphp

<div>
    <label for="{{ $nama }}" class="mb-1 block text-sm font-medium">
        {{ $label }}
        @unless ($wajib)
            <span class="font-normal text-redup">(opsional)</span>
        @endunless
    </label>

    @if ($tipe === 'textarea')
        <textarea id="{{ $nama }}" name="{{ $nama }}" rows="3" @required($wajib)
                  class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">{{ $isi }}</textarea>
    @else
        <input id="{{ $nama }}" name="{{ $nama }}" type="{{ $tipe }}" value="{{ $isi }}"
               @required($wajib) @if ($idDaftar) list="{{ $idDaftar }}" @endif
               class="w-full rounded-lg border border-garis px-3 py-2 text-sm focus:border-pine focus:outline-none">

        {{-- Datalist, bukan select: nilai di luar daftar tetap boleh diketik.
             Kelas dan jurusan di data lama tidak seragam, dan memaksakan
             pilihan tertutup akan membuat baris lama gagal disimpan ulang. --}}
        @if ($idDaftar)
            <datalist id="{{ $idDaftar }}">
                @foreach ($daftar as $pilihan)
                    <option value="{{ $pilihan }}"></option>
                @endforeach
            </datalist>
        @endif
    @endif

    @if ($petunjuk)
        <p class="mt-1 text-xs text-redup">{{ $petunjuk }}</p>
    @endif

    @error($nama)
        <p class="mt-1 text-xs text-tolak">{{ $message }}</p>
    @enderror
</div>
