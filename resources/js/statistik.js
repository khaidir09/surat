/*
 * Grafik halaman statistik (docs/specs/statistik-surat.md, K-S5 dan K-S6).
 *
 * Chart.js di-bundle Vite, bukan diambil dari CDN: dashboard ini dipakai dari
 * jaringan sekolah dan tidak boleh bergantung pada unduhan pihak ketiga —
 * alasan yang sama yang membuat ikon ditulis inline dan font memakai stack
 * sistem.
 *
 * Berkas ini hanya MENGGAMBAR. Setiap angka sudah dihitung server dan sudah
 * tercetak sebagai HTML: kartu, legenda komposisi, dan tabel status terbaca
 * penuh meski berkas ini gagal dimuat (E-S11). Yang hilang tanpa JavaScript
 * hanyalah bentuk grafiknya, bukan informasinya.
 */

import {
    ArcElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

// Didaftarkan satu per satu, bukan lewat "chart.js/auto": yang terakhir
// menarik seluruh jenis grafik termasuk yang tidak dipakai halaman ini.
Chart.register(
    ArcElement,
    CategoryScale,
    DoughnutController,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

Chart.defaults.font.family =
    "ui-sans-serif, system-ui, 'Segoe UI', sans-serif";

const sumber = document.getElementById('data-statistik');

if (sumber) {
    const data = JSON.parse(sumber.textContent);

    gambarKomposisi(data);
    gambarTren(data);
}

/**
 * Donat komposisi. Legendanya sudah ada sebagai HTML di sebelahnya, jadi
 * legenda bawaan Chart.js sengaja dimatikan — dua legenda untuk satu grafik
 * hanya menggandakan yang sama dan bisa berbeda bunyi.
 */
function gambarKomposisi({ komposisi, warna }) {
    const kanvas = document.getElementById('donat-komposisi');

    if (!kanvas) {
        return;
    }

    new Chart(kanvas, {
        type: 'doughnut',
        data: {
            labels: komposisi.map((b) => b.label),
            datasets: [
                {
                    data: komposisi.map((b) => b.terbit),
                    backgroundColor: komposisi.map(
                        (b) => warna[b.jenis] ?? warna.lainnya,
                    ),
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { display: false } },
        },
    });
}

/**
 * Garis tren. Sumbu X memakai skala kategori, bukan skala waktu: deret 30
 * harinya sudah lengkap dan berurutan dari server, termasuk hari bernilai nol
 * (E-S8), sehingga skala waktu hanya akan menambah adapter tanggal tanpa
 * mengubah hasilnya.
 */
function gambarTren({ tren, warna }) {
    const kanvas = document.getElementById('garis-tren');

    if (!kanvas) {
        return;
    }

    new Chart(kanvas, {
        type: 'line',
        data: {
            labels: tren.tanggal.map(labelPendek),
            datasets: tren.seri.map((seri) => ({
                label: seri.label,
                data: seri.data,
                borderColor: warna[seri.jenis] ?? warna.lainnya,
                backgroundColor: warna[seri.jenis] ?? warna.lainnya,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.25,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        // Label sumbu dipendekkan jadi "19/8"; tooltip
                        // mengembalikan tanggal utuh supaya tetap tidak ambigu.
                        title: (butir) => tren.tanggal[butir[0].dataIndex],
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: warna.teks,
                        maxRotation: 0,
                        autoSkipPadding: 16,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: warna.garis },
                    border: { display: false },
                    ticks: {
                        color: warna.teks,
                        // Jumlah surat selalu bilangan bulat; tanpa ini sumbu
                        // bernilai kecil akan menampilkan 0,5 dan 1,5.
                        precision: 0,
                    },
                },
            },
        },
    });
}

/** "2026-08-19" menjadi "19/8". */
function labelPendek(tanggal) {
    const [, bulan, hari] = tanggal.split('-');

    return `${Number(hari)}/${Number(bulan)}`;
}
