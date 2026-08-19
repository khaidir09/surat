@props(['label', 'nilai', 'keterangan' => null, 'catatan' => null, 'kunci' => null])

{{-- Kartu angka tunggal. Nilai sengaja diterima sebagai string yang sudah
     jadi, bukan angka mentah: satuan waktu dirakit di controller (E-S9,
     E-S10) dan nilai yang tidak dapat dihitung dikirim sebagai "—" (E-S2),
     sehingga komponen ini tidak perlu tahu bedanya. --}}
<div class="rounded-xl border border-garis bg-permukaan p-4">
    <p class="text-xs font-medium text-redup">{{ $label }}</p>

    {{-- data-kartu adalah kait untuk feature test. Tanpanya tes harus
         mencocokkan kelas Tailwind, yang berarti setiap perubahan tampilan
         memerahkan tes yang sebenarnya menguji angka, bukan tampilan. --}}
    <p @if ($kunci) data-kartu="{{ $kunci }}" @endif
       class="mt-2 text-3xl font-semibold tabular-nums text-tinta">{{ $nilai }}</p>

    @if (filled($keterangan))
        <p class="mt-1 text-xs text-redup">{{ $keterangan }}</p>
    @endif

    {{-- Dipakai saat angkanya benar tetapi mudah disalahpahami — misalnya nol
         yang muncul karena asumsi kita basi, bukan karena memang kosong. --}}
    @if (filled($catatan))
        <p class="mt-2 rounded-lg bg-amber/10 px-2 py-1 text-xs text-amber">{{ $catatan }}</p>
    @endif
</div>
