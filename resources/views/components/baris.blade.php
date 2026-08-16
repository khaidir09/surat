@props(['label', 'nilai' => null, 'kosong' => '—'])

<div class="grid gap-1 border-b border-garis py-3 last:border-0 sm:grid-cols-[12rem_1fr] sm:gap-4">
    <dt class="text-sm text-redup">{{ $label }}</dt>
    <dd class="text-sm">
        {{-- Hampir semua kolom di tabel n8n nullable (E9-E11), jadi setiap
             baris keterangan wajib punya perilaku kosong yang jelas alih-alih
             menampilkan ruang hampa. --}}
        @if (trim($slot->toHtml()) !== '')
            {{ $slot }}
        @elseif (filled($nilai))
            {{ $nilai }}
        @else
            <span class="text-redup">{{ $kosong }}</span>
        @endif
    </dd>
</div>
