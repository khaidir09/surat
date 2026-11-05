@extends('layouts.app')

@section('judul', 'Statistik')

@php
    $totalStatus = array_sum(array_column($status, 'jumlah'));

    $angka = fn ($n) => number_format($n, 0, ',', '.');

    // Nilai kartu dirakit di sini, bukan di dalam atribut komponen. Blade
    // menyusun atribut menjadi array PHP, dan ekspresi yang melintasi
    // beberapa baris merusak array itu — galatnya muncul sebagai ParseError
    // yang menunjuk nomor baris berkas hasil kompilasi, bukan berkas ini.
    $nilaiProses = $proses['nilai'] ?? '—';
    $ketProses = $proses['nilai']
        ? 'dari ' . $proses['jumlah'] . ' surat tugas'
        : 'belum ada surat tugas yang disetujui';

    // Ketiga angka chatbot berbagi satu sumber: log_permintaan. Bila tabelnya
    // hilang, ketiganya hilang bersama-sama — bukan salah satunya saja.
    $ketChatbot = $chatbot['tersedia'] ? 'tercatat di log n8n' : 'log n8n tidak tersedia';
    $ketTanya = $chatbot['tersedia'] ? 'dijawab chatbot' : 'log n8n tidak tersedia';

    $nilaiLog = fn (array $m) => $chatbot['tersedia'] ? $angka($m['jumlah']) : '—';

    // Nol pada kartu pertanyaan punya dua sebab yang di layar terlihat sama
    // (E-S16). Keduanya diberi kalimat sendiri: yang satu berarti n8n belum
    // pernah mencatat peristiwa itu sama sekali, yang lain berarti nama
    // statusnya sudah bukan "dijawab" lagi. Menyamakan keduanya berarti
    // menyembunyikan asumsi yang sudah basi di balik angka nol yang wajar.
    $catatanTanya = function (array $m) {
        if (! $m['sunyi']) {
            return null;
        }

        return $m['terlihat'] > 0
            ? 'Tercatat ' . $m['terlihat'] . ' kali, tetapi tidak satu pun bertanda "dijawab". Nama statusnya mungkin berubah di n8n.'
            : 'Belum pernah tercatat di log. Nama peristiwanya mungkin berbeda di n8n.';
    };

    $nilaiChatbot = $nilaiLog($chatbot['pengajuan']);
    $catatanChatbot = $chatbot['pengajuan']['sunyi']
        ? 'Log terisi tetapi tidak ada yang bertanda "diajukan". Nama peristiwanya mungkin berubah di n8n.'
        : null;

    $nilaiKepsek = $nilaiLog($chatbot['kepsek']);
    $catatanKepsek = $catatanTanya($chatbot['kepsek']);

    $nilaiJadwal = $nilaiLog($chatbot['jadwal']);
    $catatanJadwal = $catatanTanya($chatbot['jadwal']);
@endphp

@section('isi')
    {{-- Tiga kolom, bukan empat: baris pertama menjadi angka surat dan baris
         kedua angka chatbot. Pengelompokan itu bukan kerapian belaka —
         ketiga kartu bawah berbagi satu sumber dan menghilang bersama-sama
         bila log_permintaan tidak terbaca. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-kartu-statistik kunci="terbit" label="Surat Diterbitkan" :nilai="$angka($terbit)" keterangan="sudah bernomor" />

        {{-- E-S2: tanpa satu pun approved_at, ini "—", bukan "0 menit".
             Nol akan terbaca sebagai "prosesnya instan". --}}
        <x-kartu-statistik kunci="proses" label="Rata-rata Waktu Proses" :nilai="$nilaiProses" :keterangan="$ketProses" />

        <x-kartu-statistik kunci="permintaan" label="Permintaan Masuk" :nilai="$angka($permintaan)" keterangan="seluruh riwayat" />

        {{-- Label sengaja BUKAN "Total Interaksi Bot": satu baris log adalah
             satu perpindahan status, bukan satu interaksi (R-S9). --}}
        <x-kartu-statistik kunci="chatbot" label="Permintaan lewat Chatbot" :nilai="$nilaiChatbot"
                           :keterangan="$ketChatbot" :catatan="$catatanChatbot" />

        {{-- Dua pertanyaan yang dijawab chatbot tanpa menghasilkan surat
             (K-S16). Angkanya sengaja tidak ikut ke panel status maupun ke
             tren: keduanya tidak meninggalkan satu baris surat pun, jadi
             menjumlahkannya ke sana akan membuat total surat berbohong. --}}
        <x-kartu-statistik kunci="tanya-kepsek" label="Tanya Keberadaan Kepsek" :nilai="$nilaiKepsek"
                           :keterangan="$ketTanya" :catatan="$catatanKepsek" />

        <x-kartu-statistik kunci="tanya-jadwal" label="Tanya Jadwal Agenda" :nilai="$nilaiJadwal"
                           :keterangan="$ketTanya" :catatan="$catatanJadwal" />
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-garis bg-permukaan p-4">
            <h2 class="mb-3 text-sm font-medium">Komposisi per Jenis</h2>

            @if ($terbit === 0)
                <p class="py-8 text-center text-sm text-redup">Belum ada surat yang terbit.</p>
            @else
                {{-- Legenda di bawah sudah memuat seluruh angkanya, jadi
                     kanvas ini murni tambahan bentuk: halaman tetap utuh
                     kalau Chart.js gagal dimuat (E-S11). --}}
                <div class="mb-3 h-40">
                    <canvas id="donat-komposisi" role="img"
                            aria-label="Komposisi surat terbit per jenis"></canvas>
                </div>

                <dl class="space-y-2 text-sm">
                    @foreach ($komposisi as $bagian)
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                  style="background: {{ $warna[$bagian['jenis']] ?? $warna['lainnya'] }}"></span>
                            <dt class="grow">{{ $bagian['label'] }}</dt>
                            <dd class="tabular-nums font-medium">{{ $angka($bagian['terbit']) }}</dd>
                            <dd class="w-12 text-right text-xs text-redup">
                                {{ round($bagian['terbit'] / $terbit * 100) }}%
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        <section class="rounded-xl border border-garis bg-permukaan p-4">
            <h2 class="mb-3 text-sm font-medium">Status Surat Saat Ini</h2>

            @if (! $status)
                <p class="py-8 text-center text-sm text-redup">Belum ada data.</p>
            @else
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($status as $baris)
                            <tr>
                                {{-- Label ditulis apa adanya. Status yang belum
                                     dikenal tetap harus terlihat, bukan
                                     dikelompokkan ke "lainnya" (K-S2, E-S5). --}}
                                <td class="py-1.5 pe-3 whitespace-nowrap">{{ $baris['status'] }}</td>
                                <td class="w-full py-1.5">
                                    <span class="block h-2 rounded-full"
                                          style="width: {{ max(2, round($baris['jumlah'] / $totalStatus * 100)) }}%;
                                                 background: {{ $warna[$baris['status']] ?? $warna['lainnya'] }}"></span>
                                </td>
                                <td class="py-1.5 ps-3 text-right tabular-nums font-medium">
                                    {{ $angka($baris['jumlah']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <section class="rounded-xl border border-garis bg-permukaan p-4">
        <h2 class="mb-3 text-sm font-medium">Tren Harian — {{ count($tren['tanggal']) }} hari terakhir</h2>

        <div class="h-56">
            <canvas id="garis-tren" role="img"
                    aria-label="Tren permintaan surat harian"></canvas>
        </div>

        <p class="mt-2 text-xs text-redup">
            {{ \Illuminate\Support\Carbon::parse($tren['tanggal'][0])->translatedFormat('j M Y') }}
            &ndash;
            {{ \Illuminate\Support\Carbon::parse(end($tren['tanggal']))->translatedFormat('j M Y') }},
            dihitung menurut waktu setempat (WITA).
        </p>

        {{-- E-S3: surat tanpa created_at tidak dapat digambar. Melewatinya
             diam-diam membuat grafik menampilkan lebih sedikit surat daripada
             kartu di atasnya tanpa penjelasan. --}}
        @if ($tanpaTanggal > 0)
            <p class="mt-1 text-xs text-amber">
                {{ $tanpaTanggal }} surat tidak punya tanggal dan tidak tergambar di sini.
            </p>
        @endif
    </section>

    {{-- Data grafik dicetak di dalam halaman, bukan diambil lewat endpoint
         JSON (K-S6): tidak ada route tambahan, tidak ada otorisasi kedua,
         tidak ada urusan CSRF — dan angkanya dapat diperiksa langsung oleh
         feature test, yang tidak mungkin dilakukan terhadap kanvas. --}}
    <script type="application/json" id="data-statistik">@json($grafik)</script>

    {{-- Hanya halaman ini yang memuat Chart.js (K-S5). --}}
    @push('skrip')
        @vite('resources/js/statistik.js')
    @endpush
@endsection
