# Spesifikasi: Halaman Statistik Surat

**Status:** Belum dikerjakan. Disusun 19 Agustus 2026, diperbarui 19 Agustus 2026 setelah verifikasi data produksi.
**Penulis:** Hasil wawancara kebutuhan dengan Muhammad Khaidir
**Spesifikasi induk:** `docs/specs/dashboard-laravel-surat.md` — dokumen ini berdiri sendiri, tetapi setiap rujukan K1–K12, E1–E18, RS2–RS16 mengacu ke sana.

Penomoran di dokumen ini diberi awalan agar tidak bertabrakan dengan spesifikasi induk: **K-S** (keputusan), **E-S** (edge case), **R-S** (risiko), **AC-S** (acceptance criteria), **V-S** (verifikasi).

> **Verifikasi produksi 19 Agu 2026** — hasil query baca-saja, mengubah isi dokumen ini:
> 1. **`surat_tugas.approved_at` benar-benar terisi** (17 dari 18 baris; satu sisanya memang masih `menunggu`). Metrik rata-rata waktu proses layak dibangun; risiko R-S2 gugur.
> 2. **Status `ditolak` dinyatakan oleh pemilik sistem, belum terlihat di data.** Nilai `status` yang benar-benar ada saat ini hanya `selesai` (18) dan `menunggu` (1) — belum pernah ada surat ditolak, sehingga RS14 pada spesifikasi induk **belum tertutup**. K-S1 dan K-S2 sengaja dibuat tidak bergantung pada hal ini.
> 3. **Timezone sesi Supabase adalah UTC** (bawaan). Ini memicu temuan K-S15 di bawah.
> 4. **`public.log_permintaan` masih diisi n8n**, kini 39 baris. Tetapi isinya **bukan** yang diduga: ia mencatat **peristiwa perpindahan status**, bukan interaksi maupun permintaan — 20 pengajuan menghasilkan 39 baris. Kosakata kolom `status`-nya berbeda dari status surat: `diajukan`, `menunggu_ttd`, `diterbitkan`. Risiko R-S9 terbukti dan metrik 7 diubah karenanya. `durasi_proses_detik` seluruhnya NULL — kolom mati.
> 5. **Volume data jauh lebih besar dari RS13:** 18 surat tugas dan 1 surat keterangan (bukan 1 dan 2). **Jumlah surat keterangan justru berkurang** dari 2 menjadi 1 sejak 16 Agu — lihat §16.2.

---

## 1. Konteks dan masalah

Dashboard sekarang hanya bisa menjawab pertanyaan "apa yang perlu saya kerjakan sekarang" (`/antrean`) dan "surat mana yang pernah terbit" (`/riwayat`). Tidak ada satu pun halaman yang menjawab pertanyaan pimpinan:

| # | Pertanyaan yang belum terjawab | Keadaan sekarang |
|---|---|---|
| M-S1 | Berapa surat yang sudah kami terbitkan? | Harus menghitung manual dari `/riwayat` yang terpaginasi 25 baris (`app/Http/Controllers/RiwayatController.php:64`) |
| M-S2 | Berapa lama permintaan surat tugas menunggu keputusan? | Tidak terjawab sama sekali. Datanya ada (`created_at`, `approved_at`) tetapi tidak pernah dihitung |
| M-S3 | Surat jenis apa yang paling banyak diminta? | Harus menyaring `/riwayat` dua kali dan membandingkan angka total secara manual |
| M-S4 | Apakah permintaan sedang ramai atau sepi? | Tidak terjawab. Tidak ada tampilan berbasis waktu di mana pun |
| M-S5 | Ada berapa surat yang menggantung, dan di status apa? | `/antrean` hanya menampilkan yang dikembalikan n8n saat itu juga; tidak ada rekapitulasi |
| M-S6 | Seberapa banyak chatbot dipakai? | Tidak terjawab. `public.log_permintaan` mencatatnya sejak awal, tetapi tidak ada satu pun antarmuka yang membacanya |

Kepala Sekolah saat ini mendarat di `/riwayat` setelah login (`app/Models/User.php:79-82`) — daftar 25 baris terpaginasi, yang merupakan alat pencarian, bukan alat pemantauan.

---

## 2. Ringkasan solusi

Satu halaman baru `/statistik`, dapat diakses **kedua role**, menjadi **beranda baru bagi `kepsek`**. Berisi tujuh metrik: enam dihitung dari `public.surat_tugas` dan `public.surat_keterangan` yang sudah dibaca Laravel hari ini, satu dari `public.log_permintaan` yang baru mulai dibaca. **Tidak ada tabel baru, tidak ada kolom baru, tidak ada webhook n8n yang dipanggil.**

```
GET /statistik  (auth, kedua role)
        │
        ▼
StatistikController::index
        │  6 query agregasi, DB::table(), read-only
        ▼
Supabase: public.surat_tugas ∪ public.surat_keterangan
          public.log_permintaan  ← baru dibaca, hanya baris 'diajukan'
        │
        ▼
statistik/index.blade.php
        ├─ 4 kartu angka  ──────────── HTML/Blade
        ├─ tabel status   ──────────── HTML/Blade
        └─ <script type="application/json"> ──▶ resources/js/statistik.js ──▶ Chart.js
                                                (di-bundle Vite, bukan CDN)
```

---

## 3. Keputusan arsitektur

| # | Keputusan | Alasan |
|---|---|---|
| **K-S1** | **"Diterbitkan" didefinisikan sebagai `no_surat` terisi**, bukan sebagai daftar status tertentu | Surat yang ditolak tidak pernah diberi nomor oleh n8n, sedangkan surat yang disetujui selalu diberi nomor — sehingga satu predikat menjawab keduanya tanpa menyebut satu pun nilai status di dalam kode. Sejalan dengan K12: daftar nilai status tidak ditanam di kode. Terverifikasi 19 Agu 2026 setelah nilai `ditolak` diketahui; definisi ini tetap benar dan kini bukan lagi sekadar pilihan defensif |
| **K-S2** | **Panel status dibangun dari data** (`group by status`), bukan dari enum | Sama seperti `RiwayatController::statusTersedia()` (`app/Http/Controllers/RiwayatController.php:170-181`). Status baru dari n8n muncul sendiri tanpa ada kode yang perlu diubah — persis cacat E3 yang dihindari sejak awal. Meskipun keempat nilai kini sudah diketahui (`menunggu`, `menunggu_ttd`, `selesai`, `ditolak`), kolomnya tetap `text` tanpa constraint sehingga jaminannya tidak berubah |
| **K-S3** | **Rata-rata waktu proses = `approved_at − created_at`**, hanya untuk surat tugas | Satu-satunya rentang yang seluruhnya berasal dari kolom yang benar-benar ada **dan terbukti terisi** (verifikasi 19 Agu 2026). Alternatifnya, `log_permintaan.durasi_proses_detik`, **seluruhnya NULL** — kolom mati. Lihat K-S9 untuk yang sengaja tidak diukur |
| **K-S4** | **Grafik donat memecah angka "Total Diterbitkan"**, memakai definisi K-S1 yang sama persis | Donat yang memakai definisi berbeda dari kartu di atasnya akan menghasilkan dua angka total yang tidak cocok pada satu layar. Pembacanya akan menganggap salah satunya bug |
| **K-S5** | **Chart.js di-bundle lewat Vite sebagai entry point terpisah** (`resources/js/statistik.js`), bukan lewat CDN dan bukan lewat `app.js` | CDN dilarang: dashboard dipakai dari jaringan sekolah dan tidak boleh bergantung pada unduhan pihak ketiga — alasan yang sama yang membuat ikon ditulis inline (`resources/views/components/ikon.blade.php:5-7`) dan font memakai stack sistem (`resources/css/app.css:11-13`). Entry terpisah dipilih supaya ~60 KB Chart.js hanya diunduh di halaman yang memakainya; halaman antrean yang dipakai setiap hari tidak ikut menanggung |
| **K-S6** | **Data grafik dicetak sebagai `<script type="application/json">` di dalam halaman**, tanpa endpoint JSON | Tidak menambah route, tidak menambah permukaan otorisasi kedua, tidak menyentuh CSRF. Feature test dapat memeriksa angkanya langsung dari HTML — penting karena kanvas Chart.js tidak bisa diperiksa `assertSee` |
| **K-S7** | **`APP_TIMEZONE=Asia/Makassar` disetel global**, bukan dikonversi hanya di query statistik | `config/app.php:68` saat ini mengunci `'UTC'` dan `.env` tidak menyetel `APP_TIMEZONE`. Amuntai berada di WITA (UTC+8), sehingga surat yang dibuat pukul 07:00 WITA tercatat sebagai **hari sebelumnya**. Memperbaikinya hanya di query statistik akan membuat dua definisi "hari" hidup berdampingan di satu aplikasi. Konsekuensinya diakui: perubahan ini menyentuh halaman yang dipakai staf setiap hari — lihat R-S1 dan V-S1 |
| **K-S8** | **Query agregasi ditulis sebagai method privat di dalam controller**, bukan di kelas service tersendiri | Mengikuti bentuk `RiwayatController` yang sudah ada (lima method privat, `DB::table()` + `selectRaw`). Proyek ini sengaja tidak punya lapisan service kecuali untuk integrasi luar (`N8nClient`, `SupabaseStorage`), dan seluruh pengujiannya adalah feature test terhadap request nyata — kelas service akan menambah lapisan yang tidak ada yang mengujinya secara terpisah |
| **K-S9** | **Waktu "sampai selesai/TTD" tidak diukur** | Tidak ada kolom timestamp untuk peristiwa itu di kedua tabel. Satu-satunya jejaknya adalah `app.aktivitas` dengan `aksi = 'selesai'`, dan menurut §13 spesifikasi induk webhook `/surat-selesai` **belum pernah dipanggil sekali pun**. Metrik yang dihitung dari nol baris akan menampilkan angka yang terlihat sah padahal tidak berarti apa-apa |
| **K-S10** | **Tanpa cache**, query dijalankan setiap kali halaman dibuka | Volume produksi masih sangat kecil (RS13). Cache pada data sekecil ini hanya menambah kemungkinan angka basi tanpa manfaat yang terukur. Ditinjau ulang bila V-S5 menunjukkan halaman terasa lambat |
| **K-S11** | **`kepsek` mendarat di `/statistik`, `tas` tetap mendarat di `/antrean`** | Pekerjaan harian TAS adalah antrean; mendaratkannya di ringkasan akan menambah satu klik pada alur yang dipakai puluhan kali sehari. Kepsek tidak punya pekerjaan harian di sistem ini — ringkasan adalah tujuan yang tepat |
| **K-S12** | **`public.log_permintaan` dibaca hanya untuk menghitung baris berstatus `diajukan`.** Kolom `no_wa` dan `durasi_proses_detik` **tidak disentuh sama sekali** | *Diamandemen 19 Agu 2026 setelah §16.1 dijawab.* Bentuk semula (`count(*)` polos) menghitung **peristiwa**, bukan permintaan: 20 pengajuan menghasilkan 39 baris karena setiap perpindahan status ikut dicatat. Angka yang benar untuk menjawab M-S6 adalah jumlah baris `diajukan`. Kolom `status` karenanya ikut dibaca — kosakatanya kini terverifikasi (`diajukan`, `menunggu_ttd`, `diterbitkan`) dan ia bukan data pribadi. **`no_wa` tetap tidak dibaca**: ia berisi nomor WhatsApp pemohon, tidak dibutuhkan untuk menjawab M-S6, dan yang tidak dibaca tidak dapat bocor. `durasi_proses_detik` seluruhnya NULL |
| **K-S15** | **Timezone sesi Postgres wajib sama dengan `config('app.timezone')`**, ditegakkan lewat `'timezone'` pada `config/database.php` | **Ditemukan saat mengerjakan K-S7, 19 Agu 2026.** Laravel mengirim setiap `Carbon` ke PDO sebagai `'Y-m-d H:i:s'` **tanpa offset**; Postgres menafsirkannya memakai timezone sesi. Selama keduanya UTC hal ini tidak terlihat. Begitu `APP_TIMEZONE` menjadi WITA sementara sesi Supabase tetap UTC, **setiap penulisan ke kolom `timestamptz` tersimpan +8 jam di masa depan tanpa galat apa pun** — `app.aktivitas.created_at`, `agenda.created_at`/`updated_at`, dan seluruh `deleted_at`. Terbukti secara empiris, bukan disimpulkan. Pengujian **tidak dapat** menangkapnya: Postgres lokal ber-timezone `Asia/Kuala_Lumpur` yang offsetnya kebetulan sama dengan WITA, sehingga selisihnya nol justru di satu-satunya mesin yang diuji (R-S11) |
| **K-S13** | **`log_permintaan` dibaca lewat `DB::table()`, tanpa model Eloquent** | Konsisten dengan K-S8 dan dengan `RiwayatController` yang memakai `DB::table()` bahkan untuk tabel yang punya model. Sebuah model yang seumur hidupnya hanya menjawab `count()` adalah berkas yang keberadaannya mengundang pemakaian yang lebih luas dari yang pernah diputuskan. **Bila kelak ada kolom lain yang perlu dibaca, saat itulah model dengan trait `HanyaBaca` dibuat** — bukan sekarang |
| **K-S14** | **Penolakan tidak diberi kartu angka tersendiri**; ia muncul lewat panel status | Panel status sudah data-driven (K-S2), sehingga `ditolak` beserta jumlahnya tampil sendiri tanpa satu baris kode tambahan. Kartu terpisah akan menduplikasi angka yang sudah ada di layar yang sama. Persentase penolakan sengaja tidak ditampilkan: pada volume sekarang satu penolakan dapat terbaca sebagai angka yang dramatis (RS13) |

---

## 4. Data model

### 4.1 Kolom yang dibaca

Tidak ada penambahan skema. Seluruh metrik dihitung dari kolom yang sudah ada:

| Tabel | Kolom yang dibaca | Dipakai untuk |
|---|---|---|
| `public.surat_tugas` | `no_surat`, `status`, `created_at`, `approved_at` | Metrik 1–6 |
| `public.surat_keterangan` | `no_surat`, `status`, `created_at` | Metrik 1, 3, 4, 5, 6 |
| `public.log_permintaan` | `status` saja — **`no_wa` tidak pernah** | Metrik 7 |

Sifat kolom yang menentukan perilaku (dari §5.1 spesifikasi induk dan `database/migrations/uji/2020_01_01_000000_buat_tabel_n8n.php`):

- `no_surat` **nullable** pada kedua tabel surat — surat lama bisa belum bernomor (E10)
- `status` **`text` tanpa constraint maupun enum**, `NOT NULL` dengan default `'menunggu'`. Nilai yang kini diketahui seluruhnya: `menunggu`, `menunggu_ttd`, `selesai`, `ditolak`. **Daftar ini tetap tidak boleh ditanam di kode** (K-S2) — tidak ada constraint yang menjaganya tetap begitu
- `surat_tugas.created_at` `timestamptz` **default `now()`**
- `surat_keterangan.created_at` `timestamptz` **nullable tanpa default** — inilah bedanya dengan surat tugas, dan ia berkonsekuensi pada tren harian (E-S3)
- `approved_at` `timestamptz` nullable pada kedua tabel, diisi oleh n8n. **Terverifikasi terisi 19 Agu 2026** — 17 dari 18 surat tugas; satu sisanya memang masih `menunggu`
- `log_permintaan` — kolomnya `id`, `waktu`, `no_wa` bigint, `jenis_surat` text, `status` text, `durasi_proses_detik` integer. **39 baris per 19 Agu 2026**, `durasi_proses_detik` seluruhnya NULL. Satu baris = **satu perpindahan status**, bukan satu permintaan. Nilai `status` yang terlihat: `diajukan` (20), `menunggu_ttd` (17), `diterbitkan` (2). Nilai `jenis_surat` memakai kosakata webhook n8n (`surat_tugas`, `keterangan_aktif`), bukan nama tabel

### 4.2 Bentuk query

Ditulis dengan `DB::table()` + `selectRaw`, mengikuti `RiwayatController`. Semuanya baca-saja; model `SuratTugas` dan `SuratKeterangan` memakai trait `HanyaBaca` yang melempar `LogicException` pada setiap penulisan (`app/Models/Concerns/HanyaBaca.php:20-32`), dan halaman ini tidak pernah menulis apa pun.

**Predikat "terbit" (K-S1)** — dipakai identik di metrik 1 dan 4:

```sql
nullif(btrim(no_surat), '') is not null
```

`btrim` + `nullif` dipakai, bukan sekadar `is not null`: kolomnya `text` yang diisi dari luar Laravel, sehingga string kosong dan spasi tunggal adalah kemungkinan nyata, bukan kemungkinan teoretis.

**Ekspresi tanggal WITA** — dipakai di metrik 5:

```sql
(created_at at time zone 'Asia/Makassar')::date
```

Konversi ini **tetap wajib meskipun `APP_TIMEZONE` sudah disetel** (K-S7). `APP_TIMEZONE` hanya mengubah timezone PHP; ia tidak menyetel timezone sesi Postgres, sehingga `created_at::date` di sisi SQL akan tetap memakai timezone sesi. Timezone sesi Supabase **terverifikasi UTC** (default), sehingga konversi ini menggeser tepat sekali — bukan dua kali. Dua-duanya diperlukan, dan ini adalah jenis kesalahan yang tidak menghasilkan error, hanya angka yang salah 8 jam.

**Rata-rata waktu proses (K-S3):**

```sql
select avg(extract(epoch from (approved_at - created_at))) as detik,
       count(*) as jumlah
from surat_tugas
where approved_at is not null
  and created_at is not null
  and approved_at >= created_at
```

`approved_at >= created_at` bukan kehati-hatian berlebihan: nomor surat boleh di-backfill dengan tanggal lampau (`AntreanController.php:56-60` menerima parameter `tanggal` untuk keperluan itu), dan satu baris negatif cukup untuk menarik rata-rata ke arah yang menyesatkan. `count(*)` ikut diambil supaya tampilan dapat menyebut dari berapa surat angka itu berasal (E-S2).

**Permintaan lewat chatbot (K-S12):**

```sql
select count(*) from log_permintaan where status = 'diajukan'
```

Satu-satunya kolom yang disebut adalah `status`, dan satu-satunya nilai yang dicari adalah `'diajukan'`. `no_wa` tidak pernah muncul dalam query ini. Setiap penambahan di luar itu adalah keputusan baru yang perlu alasannya sendiri.

`'diajukan'` di sini **sengaja ditulis sebagai literal**, berbeda dari sikap terhadap status surat (K-S2). Alasannya berbeda pula: pada status surat kita menampilkan seluruh nilai apa adanya sehingga tidak boleh ada daftar tetap, sedangkan di sini kita menanyakan satu peristiwa tertentu — "pengajuan" — yang namanya harus disebut agar pertanyaannya punya arti. Konsekuensinya diterima dan dijaga E-S15: bila n8n mengganti nama peristiwa ini, angkanya jatuh ke nol dan **itu harus terlihat**, bukan tersamar.

---

## 5. Metrik yang ditampilkan

| # | Metrik | Bentuk | Sumber | Definisi |
|---|---|---|---|---|
| **1** | Total Surat Diterbitkan | Kartu angka | Kedua tabel surat | Jumlah baris dengan `no_surat` terisi (K-S1) |
| **2** | Rata-rata Waktu Proses Surat Tugas | Kartu angka | `surat_tugas` | Rata-rata `approved_at − created_at`, ditampilkan dalam satuan yang terbaca manusia (E-S2, E-S9, E-S10) |
| **3** | Total Permintaan Masuk | Kartu angka | Kedua tabel surat | Jumlah seluruh baris, tanpa penyaringan. Disandingkan dengan metrik 1 sehingga selisihnya terlihat |
| **7** | Permintaan lewat Chatbot | Kartu angka | `log_permintaan` | Jumlah baris berstatus `diajukan` (K-S12). **Label bukan "Total Interaksi Bot"** — satu baris bukan satu interaksi, lihat R-S9 |

**Metrik 6 tidak memakai kanvas.** §9 semula membayangkan tiga grafik, tetapi panel status sudah berupa batang HTML/CSS yang terbaca tanpa JavaScript. Menggantinya dengan kanvas Chart.js akan menghapus properti E-S11 yang sudah dimiliki, demi tampilan yang tidak lebih informatif. Kanvas hanya dipakai di tempat yang HTML polos memang tidak sanggup: donat dan garis.
| **4** | Komposisi per Jenis | Grafik donat | Kedua tabel surat | Pecahan dari metrik 1: surat tugas vs surat keterangan (K-S4) |
| **5** | Tren Harian | Grafik garis, 2 seri | Kedua tabel surat | Jumlah permintaan masuk per hari WITA, 30 hari terakhir termasuk hari ini |
| **6** | Status Surat Saat Ini | Grafik batang + tabel | Kedua tabel surat | `count(*) group by status` dari kedua tabel digabung, seluruh nilai apa adanya (K-S2) |

Nomor metrik mengikuti urutan pembahasan, bukan urutan tampilan; metrik 7 ditambahkan belakangan dan tampil sebagai kartu keempat. Penomoran sengaja tidak diurutkan ulang supaya rujukan di §14 dan §15 tetap menunjuk hal yang sama.

**Catatan pada metrik 3:** ia tidak ada di permintaan awal, tetapi tanpanya metrik 1 tidak dapat ditafsirkan. "142 surat diterbitkan" berarti sangat berbeda bila permintaan yang masuk 145 dibanding 400. Ia juga gratis — query-nya bagian dari metrik yang sama. Sejak nilai `ditolak` diketahui, selisih metrik 3 dan metrik 1 kini dapat dijelaskan seluruhnya: ia terdiri dari surat yang **ditolak** dan surat yang **masih menunggu**, dan keduanya terbaca angkanya di panel status.

---

## 6. Role dan otorisasi

| Kemampuan | `tas` | `kepsek` |
|---|:---:|:---:|
| Membuka `/statistik` | ✅ | ✅ |
| Mendarat di `/statistik` setelah login | ❌ (tetap `/antrean`) | ✅ |

Ditegakkan lewat grup middleware `['auth', 'aktif']` yang sudah ada di `routes/web.php:39`, **tanpa** middleware `role:` — sama seperti `/riwayat` dan `/agenda`. Menambahkan item ke `$menu` pada `resources/views/layouts/app.blade.php:18-26` hanyalah kerapian tampilan; penegakannya ada di route (§6 spesifikasi induk).

**Data pribadi:** halaman ini menampilkan angka agregat saja. Tidak ada nama siswa, tidak ada `pemohon_wa` dari tabel surat, dan **tidak ada `no_wa` dari `log_permintaan`** — kolom itu tidak dibaca sama sekali (K-S12). Karena itu tidak ada pembatasan tambahan di antara kedua role.

**Perubahan pada perilaku yang sudah ada:** `User::rutaBeranda()` (`app/Models/User.php:79-82`) mengembalikan `route('statistik.index')` untuk `kepsek`, menggantikan `route('riwayat.index')`. Ini memengaruhi `routes/web.php:41` dan `app/Http/Controllers/Auth/LoginController.php:41` tanpa keduanya perlu diubah — keduanya sudah memanggil `rutaBeranda()`.

---

## 7. Route dan halaman

```
GET /statistik    auth, aktif    statistik.index    StatistikController@index
```

Satu route, tanpa parameter, tanpa query string. Tidak ada filter periode (K-S10 dan §10 butir 2): kartu angka menghitung sepanjang waktu, grafik garis selalu 30 hari terakhir.

Letak di sidebar: **di atas `Antrean`**, sebagai item pertama. Ia adalah ringkasan; menempatkannya setelah daftar-daftar kerja akan membuatnya terbaca sebagai pelengkap.

---

## 8. File dan interface terkait

### 8.1 Yang dibuat baru

| Path | Isi |
|---|---|
| `app/Http/Controllers/StatistikController.php` | ✅ Satu method `index()` dan method privat agregasi (K-S8) |
| `resources/views/statistik/index.blade.php` | ✅ Empat kartu angka, tabel status, legenda komposisi, blok JSON (K-S6), dan dua kanvas |
| `resources/views/components/kartu-statistik.blade.php` | ✅ Komponen kartu angka: label, nilai besar, keterangan kecil, dan catatan opsional untuk angka yang benar tetapi mudah disalahpahami (E-S15) |
| `resources/js/statistik.js` | ✅ Membaca blok JSON, menyalakan dua grafik Chart.js. Komponen didaftarkan satu per satu, bukan lewat `chart.js/auto` yang menarik seluruh jenis grafik |
| `tests/Feature/Statistik/StatistikTest.php` | Lihat §12 |

### 8.2 Yang diubah

| Path | Perubahan | Catatan |
|---|---|---|
| `routes/web.php` | ✅ **Selesai.** Satu route di dalam grup `['auth','aktif']` yang sudah ada | Bukan di dalam grup `role:tas` |
| `app/Models/User.php:79-82` | ✅ **Selesai.** `rutaBeranda()` untuk `kepsek` → `statistik.index` | K-S11 |
| `resources/views/layouts/app.blade.php:18-26` | ✅ **Selesai.** Satu item `$menu` baru, `['statistik.index', 'Statistik', true, 'statistik']`, ditaruh paling atas | Elemen ketiga `true` = kedua role |
| `resources/views/components/ikon.blade.php` | ✅ **Selesai.** Satu path SVG baru bernama `statistik` (batang naik) | Inline, viewBox 24×24, `currentColor` — konvensi berkas itu |
| **`database/migrations/uji/2020_01_01_000000_buat_tabel_n8n.php`** | ✅ **Selesai.** **Tambah tabel `public.log_permintaan`**, dan tambahkan namanya ke daftar `dropIfExists` pada `down()` | **Komentar di baris 104-106 berkata `log_permintaan` sengaja tidak dibuat karena Laravel tidak membacanya. Komentar itu kini keliru dan wajib diperbarui**, bukan dibiarkan — komentar yang berbohong lebih berbahaya daripada tidak ada komentar. `sesi` dan `counter` tetap tidak dibuat |
| `vite.config.js:9` | ✅ **Selesai.** Tambah `'resources/js/statistik.js'` ke array `input` | K-S5 |
| `package.json` | ✅ **Selesai.** `chart.js` ^4.5.1 | Dependency JS runtime pertama proyek ini yang benar-benar dimuat. Ditaruh di `devDependencies` mengikuti isi berkas itu — seluruhnya di-bundle saat build, tidak ada yang dibaca saat runtime server |
| `config/app.php:68` | `'timezone' => env('APP_TIMEZONE', 'UTC')` | ✅ **Selesai.** K-S7. Nilai default tetap UTC supaya perilaku ditentukan `.env`, bukan tertanam di kode |
| `.env` dan `.env.example` | `APP_TIMEZONE=Asia/Makassar` | ✅ **Selesai.** `.env.example` sebelumnya tidak memuat kunci ini sama sekali |
| **`config/database.php`** (blok `pgsql`) | **`'timezone' => env('DB_TIMEZONE', env('APP_TIMEZONE', 'UTC'))`** | ✅ **Selesai.** K-S15 — tidak ada di rancangan awal, ditemukan saat mengerjakan K-S7. Tanpa ini setiap penulisan `timestamptz` di produksi bergeser +8 jam |
| **`phpunit.xml`** | **`<env name="APP_TIMEZONE" value="Asia/Makassar"/>`** | ✅ **Selesai.** Dipatok agar tes batas hari WITA memberi hasil sama di setiap mesin, tidak bergantung pada `.env` masing-masing |
| `tests/Feature/Auth/LoginTest.php:30-37` | ✅ **Selesai.** Diganti menjadi `test_kepsek_mendarat_di_statistik_bukan_antrean` | Nama ikut diganti: nama test yang berbohong lebih buruk daripada tidak ada test |

### 8.2.1 Jebakan yang ditemui saat langkah 4

**`@json` pada Blade tidak boleh menerima array literal.** Direktif itu memecah ekspresinya dengan `explode(',')` untuk mengambil argumen `flags` dan `depth`, sehingga `@json(['a' => $a, 'b' => $b])` terpotong di koma pertama: kunci berikutnya hilang dan `[` tidak pernah ditutup. Hasilnya `ParseError` yang menunjuk **berkas hasil kompilasi**, bukan berkas Blade-nya, sehingga penyebabnya tidak terbaca dari pesan galat.

`Blade::compileString()` **tidak** menangkapnya — ia hanya menghasilkan string dan tidak pernah mem-parse hasilnya sebagai PHP. Satu-satunya cara melihatnya adalah `php -l` terhadap keluaran kompilasi.

Karena itu payload grafik dirakit di controller dan diteruskan sebagai satu variabel: `@json($grafik)`. Aturan umumnya — **`@json` hanya aman untuk satu variabel tunggal.**

### 8.3 Yang sengaja tidak disentuh

| Path | Alasan |
|---|---|
| `app/Services/N8nClient.php` | Halaman ini tidak memanggil n8n sama sekali. Statistik yang bergantung pada webhook akan ikut mati setiap kali n8n mati (K11) |
| `app/Models/SuratTugas.php`, `SuratKeterangan.php` | Query memakai `DB::table()`, tidak butuh scope baru pada model read-only |
| `app/Models/` — tidak ada model `LogPermintaan` | K-S13 |
| `database/migrations/` (di luar `uji/`) | Tidak ada kolom baru, tidak ada tabel baru di produksi |
| `public.sesi`, `public.counter` | Tidak dibaca. `sesi` adalah state machine percakapan, `counter` tabel mati |
| `template/` | Dirender n8n, tidak ada hubungannya |
| `public/legacy/` | Tetap utuh sampai diminta dipensiunkan |

---

## 9. UI

```
┌─ Statistik ─────────────────────────────────────────────────────────────┐
│                                                                          │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐              │
│ │ Surat      │ │ Rata-rata  │ │ Permintaan │ │ Permintaan │              │
│ │ Diterbitkan│ │ Waktu Proses│ │ Masuk      │ │ lewat Chatbot│            │
│ │     18     │ │  3 jam 12 m │ │     19     │ │     20     │              │
│ │sudah bernomor│ │dari 17 surat│ │seluruh riwayat│ │tercatat di log n8n│    │
│ └────────────┘ └────────────┘ └────────────┘ └────────────┘              │
│                                                                          │
│ ┌── Komposisi per Jenis ────┐ ┌── Status Surat Saat Ini ──────────────┐  │
│ │                           │ │  selesai        ████████ 138          │  │
│ │        ( donat )          │ │  menunggu_ttd   █          4          │  │
│ │   ● Surat tugas     58    │ │  menunggu       █          3          │  │
│ │   ● Surat keterangan 84   │ │  ditolak        █          2          │  │
│ └───────────────────────────┘ └───────────────────────────────────────┘  │
│                                                                          │
│ ┌── Tren Harian — 30 hari terakhir ────────────────────────────────────┐ │
│ │      (garis, dua seri: surat tugas & surat keterangan)               │ │
│ └──────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────┘
```

Empat kartu pada satu baris di layar lebar, dua kolom di tablet, satu kolom di ponsel — mengikuti pola grid yang sudah dipakai `resources/views/riwayat/index.blade.php:7`.

**Warna** diambil dari token yang sudah ada di `resources/css/app.css:20-32`, bukan dari palet bawaan Chart.js — supaya halaman ini tidak terlihat seperti tempelan dari aplikasi lain:

| Elemen | Token | Nilai |
|---|---|---|
| Surat tugas | `--color-pine` | `#16584a` |
| Surat keterangan | `--color-ket` | `#6d3b8e` |
| Status `menunggu` | `--color-amber` | `#a9721a` |
| Status `menunggu_ttd` | `--color-info` | `#1f5f8b` |
| Status `selesai` | `--color-setuju` | `#1f7a43` |
| Status `ditolak` | `--color-tolak` | `#b23a32` |
| Status lain / belum dikenal | `--color-redup` | `#5c6b63` |

Pemetaan warna status **hanya kosmetik**: status yang tidak ada di tabel di atas tetap tampil dengan warna netral dan labelnya apa adanya (K-S2, E-S5). Warna yang hilang tidak boleh membuat baris itu hilang.

Nilai heksadesimalnya ditulis di JSON yang dicetak controller, bukan dibaca dari CSS oleh JavaScript: `getComputedStyle` terhadap custom property Tailwind 4 tidak dapat diandalkan berjalan sebelum grafik digambar.

---

## 10. Di luar scope

1. **Waktu proses sampai surat ditandatangani/selesai** (K-S9).
2. **Filter periode** — tidak ada input dari/sampai, tidak ada pilihan periode cepat. Ditolak untuk rilis pertama karena menggandakan jumlah kasus uji sementara data produksi belum cukup untuk membuat perbedaan periode terlihat (RS13).
3. **Kolom `log_permintaan` selain `status`** — `no_wa`, `jenis_surat`, `waktu`, `durasi_proses_detik` (K-S12). Termasuk "pemohon unik" lewat `count(distinct no_wa)`, yang sempat dipertimbangkan dan ditolak: ia menuntut membaca kolom nomor telepon untuk menjawab pertanyaan yang belum ada yang mengajukannya. Juga termasuk memecah metrik 7 per `jenis_surat`, meski datanya kini diketahui ada.
4. **`public.sesi`** — state machine percakapan chatbot. Tidak dibaca sama sekali.
5. **Corong permintaan → surat terbit** (berapa percakapan bot yang berakhir menjadi surat). Menarik, dan datanya mungkin cukup, tetapi menuntut pemetaan `log_permintaan.jenis_surat` ke jenis surat Laravel yang kosakatanya belum diverifikasi (R-S9, §16.4).
6. **Kartu atau persentase penolakan tersendiri** (K-S14).
7. **Ekspor PDF/Excel** dari halaman statistik.
8. **Statistik per pengguna** ("siapa menyetujui berapa surat") dari `app.aktivitas`. Datanya ada, tetapi ini metrik tentang orang, bukan tentang pekerjaan — konsekuensinya perlu dibicarakan tersendiri.
9. **Auto-refresh.** Halaman antrean sengaja tidak punya (§17 langkah 6 spesifikasi induk), dan tidak ada alasan halaman ini memulai kebiasaan baru.
10. **Memanggil n8n** untuk data apa pun di halaman ini.
11. ~~**Memperbaiki `whereYear('created_at', …)` di `AntreanController.php:160`.**~~ **Tidak lagi diperlukan.** K-S15 menyetel timezone sesi Postgres ke WITA, sehingga `extract(year …)` di sisi SQL ikut dihitung menurut WITA. Terbetulkan sebagai efek samping, bukan sebagai pekerjaan tersendiri — dan tetap tanpa test khusus, sesuai cakupan §12.
12. **Cache** (K-S10).

---

## 11. Edge cases

| # | Kondisi | Perilaku yang diharapkan |
|---|---|---|
| **E-S1** | **Database kosong sama sekali** | Halaman tetap **200**. Setiap kartu menampilkan `0`; grafik donat dan batang digantikan kalimat "Belum ada data". Grafik garis **tetap digambar** dengan 30 hari bernilai nol — garis datar di nol adalah informasi, kanvas kosong adalah kegagalan yang menyerupai bug |
| **E-S2** | **Tidak ada satu pun surat tugas dengan `approved_at` terisi** — pembagian nol | Kartu rata-rata menampilkan "—" dengan keterangan "belum ada surat tugas yang disetujui", **bukan** `0 menit` dan bukan `NaN`. `0 menit` akan terbaca sebagai "prosesnya instan" |
| **E-S3** | **`surat_keterangan.created_at` NULL** (kolomnya nullable tanpa default) | Baris itu tetap dihitung pada metrik 1, 3, 4, dan 6, tetapi **tidak dapat** masuk ke tren harian. Melewatinya diam-diam **tidak boleh**: bila ada baris semacam itu, tren harian menampilkan catatan kaki "n surat tidak punya tanggal dan tidak tergambar di sini" |
| **E-S4** | **`no_surat` berisi string kosong atau spasi**, bukan NULL | Dihitung sebagai **belum terbit**. Predikatnya memakai `nullif(btrim(...), '')`, bukan `is not null` (§4.2) |
| **E-S5** | **Status bernilai di luar yang diketahui.** Yang benar-benar ada di data hanya `selesai` dan `menunggu`; `menunggu_ttd` dan `ditolak` diketahui dari sumber lain dan belum pernah terlihat sebagai baris | **Tetap tampil** di panel status dengan labelnya apa adanya dan warna netral. Tidak dikelompokkan ke "lainnya", tidak disembunyikan (K-S2, E3). Kolomnya `text` tanpa constraint, dan dua dari empat nilai yang "diketahui" pun belum terbukti ada — justru itu alasannya tidak boleh ada daftar tetap |
| **E-S6** | **`approved_at` lebih awal dari `created_at`** akibat backfill tanggal lampau | Baris itu dikeluarkan dari perhitungan rata-rata (`approved_at >= created_at`). Tidak dihitung sebagai durasi negatif |
| **E-S7** | **Surat dibuat 07:00 WITA** (= 23:00 UTC hari sebelumnya) | Masuk ke tanggal **WITA**-nya pada tren harian. Inilah yang diuji V-S1 dan §12 |
| **E-S8** | **Hari tanpa satu pun surat** di dalam rentang 30 hari | Muncul sebagai titik bernilai `0`, bukan dilewati. Deret 30 hari dibangun di PHP dan diisi dari hasil query, bukan diambil apa adanya dari SQL |
| **E-S9** | **Rata-rata di bawah satu menit** | Ditampilkan dalam detik ("48 detik"), bukan "0 menit" |
| **E-S10** | **Rata-rata lebih dari 24 jam** | Ditampilkan dalam hari dan jam ("2 hari 5 jam"), bukan "53 jam" |
| **E-S11** | **JavaScript mati atau Chart.js gagal dimuat** | Keempat angka pada kartu dan **tabel status tetap terbaca** — tabel status dirender Blade, bukan digambar Chart.js. Grafiknya hilang; halamannya tidak |
| **E-S12** | **Supabase tidak dapat dihubungi** | Sama seperti halaman lain yang membaca database langsung: error 500 bawaan Laravel. Berbeda dari `/antrean` yang menangkap `N8nException` — di sana sumbernya webhook yang memang bisa mati sendiri, di sini database yang matinya berarti seluruh aplikasi mati |
| **E-S13** | **`log_permintaan` kosong** | Kartu menampilkan `0`. Bukan "—": nol permintaan adalah fakta yang terukur, berbeda dari rata-rata yang tidak dapat dihitung (E-S2) |
| **E-S15** | **n8n mengganti nama peristiwa `diajukan`** | Angkanya jatuh ke `0` sementara `log_permintaan` terus terisi. Ini **tidak boleh tersamar**: bila metrik 7 bernilai 0 padahal tabelnya berisi baris, kartu menampilkan keterangan "log terisi tetapi tidak ada yang bertanda `diajukan`". Satu-satunya tempat di halaman ini yang membandingkan hasil query dengan keberadaan datanya sendiri — dan ia ada justru karena literal `'diajukan'` adalah asumsi yang disengaja (§4.2) |
| **E-S14** | **`log_permintaan` tidak ada di database** — misalnya n8n memindahkan atau mengganti namanya | Query melempar `QueryException` dan **seluruh halaman jatuh 500**, padahal enam metrik lain baik-baik saja. Kartu ini karenanya dibungkus penanganan tersendiri: bila query gagal, kartu menampilkan "—" dengan keterangan "tidak tersedia" sementara sisa halaman tetap tampil. Ini satu-satunya tabel yang Laravel baca tanpa pernah punya jaminan atasnya (R-S9) |

---

## 12. Strategi pengujian

`tests/Feature/Statistik/StatistikTest.php`, memakai `RefreshDatabase` dan trait `Tests\Dukungan\MembuatDataSurat` yang sudah ada — baris surat disisipkan lewat query builder karena modelnya read-only.

**Prasyarat — ✅ selesai 19 Agu 2026.** `public.log_permintaan` sudah ada di `database/migrations/uji/2020_01_01_000000_buat_tabel_n8n.php` (§8.2). Strukturnya diambil dari `information_schema` produksi, bukan dari §5.1: seluruh kolom selain `id` nullable, `waktu` berdefault `now()`, dan `durasi_proses_detik` dihadirkan meski selalu NULL — mengikuti prinsip berkas itu, tes harus menemui tabel seburuk aslinya.

**Yang wajib ditiru saat menulis test:** satu baris `log_permintaan` adalah satu **perpindahan status**, bukan satu permintaan. Test yang memberi satu baris per surat uji akan hijau untuk bentuk data yang di produksi tidak pernah ada. Setiap surat uji perlu setidaknya baris `diajukan` **dan** baris lanjutannya, supaya metrik 7 benar-benar terbukti menyaring, bukan sekadar menghitung.

**Diminta secara eksplisit (19 Agu 2026):**

| Fokus | Isi |
|---|---|
| **Kebenaran angka setiap metrik** | Sisipkan komposisi baris yang diketahui, lalu pastikan setiap angka persis: total terbit hanya menghitung yang `no_surat`-nya terisi; surat tanpa nomor tidak ikut; surat berstatus `ditolak` **tidak** terhitung terbit tetapi **tetap** terhitung pada "Permintaan Masuk" dan muncul di panel status; rata-rata waktu proses cocok dengan hitungan tangan; komposisi donat berjumlah sama dengan kartu total (K-S4); panel status memuat setiap nilai status yang ada di database; kartu interaksi bot sama dengan jumlah baris `log_permintaan` |
| **Batas hari menurut WITA** | Sisipkan surat pada `2026-08-18 23:30 UTC` (= 19 Agu 07:30 WITA) dan pastikan ia jatuh pada **19** Agustus di data tren, bukan 18. Inilah satu-satunya test yang membuktikan K-S7 benar-benar bekerja; tanpanya perubahan timezone hanya asumsi |

**Wajib meski tidak diminta**, karena bukan penambahan cakupan melainkan menjaga suite tetap hijau:

- `tests/Feature/Auth/LoginTest.php:30-37` disesuaikan: kepsek kini mendarat di `/statistik`. Nama methodnya (`test_kepsek_mendarat_di_riwayat_bukan_antrean`) ikut diganti, karena nama test yang berbohong lebih buruk daripada tidak ada test. **Dikerjakan di langkah 6 bersama `rutaBeranda()`**, bukan di langkah 5 — perilaku dan tesnya harus berpindah dalam satu commit.

**Sengaja tidak dicakup, atas keputusan pemilik sistem** — dicatat di sini supaya keputusannya terlihat, bukan terlupa:

- Test otorisasi kedua role terhadap `/statistik`. Konsekuensinya: bila route salah tempat dan masuk ke grup `role:tas`, kepsek akan menerima **403 pada halaman berandanya sendiri** dan tidak ada test yang menangkapnya. Mitigasi manual: V-S2.
- Test ketahanan terhadap NULL dan database kosong (E-S1, E-S2, E-S3, E-S13, E-S14). Konsekuensinya: pembagian nol pada rata-rata dan kegagalan `log_permintaan` tidak terjaring otomatis. Mitigasi manual: V-S3.

**Dua kait yang lahir dari langkah 5:**

- `data-kartu="…"` pada nilai setiap kartu (`components/kartu-statistik.blade.php`). Tanpanya tes harus mencocokkan kelas Tailwind, sehingga setiap perubahan tampilan memerahkan tes yang sebenarnya menguji angka.
- `buatPengajuanChatbot()` pada `Tests\Dukungan\MembuatDataSurat`, yang sengaja menyisipkan **lebih dari satu baris** per pengajuan. Penolong yang menyisipkan satu baris saja akan membuat metrik 7 hijau untuk bentuk data yang di produksi tidak pernah ada.

**Test batas hari WITA sudah dibuktikan bukan test kosong:** dengan `APP_TIMEZONE` diubah sementara ke `UTC`, ia gagal tepat pada kasusnya — surat 07:30 WITA mendarat di tanggal sebelumnya. Pembuktian itu perlu diulang bila ekspresi tanggalnya pernah disentuh lagi.

Cara membaca angka dari halaman untuk pengujian: blok `<script type="application/json" id="data-statistik">` (K-S6). Kanvas Chart.js tidak menghasilkan teks yang dapat diperiksa `assertSee`; blok JSON-nya bisa, dan ia adalah data yang sama persis dengan yang digambar.

`Tests\TestCase` memanggil `Http::preventStrayRequests()` (`tests/TestCase.php:16`) — halaman ini tidak memanggil HTTP apa pun, jadi tidak ada yang perlu di-fake.

---

## 13. Compatibility

| Aspek | Dampak |
|---|---|
| **n8n** | **Nol.** Tidak ada webhook yang dipanggil, tidak ada payload yang berubah, tidak ada kolom yang ditulis. K6 tidak tersentuh |
| **Skema database produksi** | **Nol.** Tidak ada migration. K8 tidak tersentuh |
| **Chatbot** | **Baca-saja, satu `count(*)` bersyarat `status = 'diajukan'`.** `log_permintaan` tidak pernah ditulis, `no_wa` tidak pernah dibaca, `sesi` tidak disentuh. Perlu disadari: ini pertama kalinya Laravel bergantung pada tabel yang sepenuhnya dikelola workflow chatbot dan tidak punya kesepakatan apa pun atasnya (R-S10) |
| **Dashboard legacy** | **Nol.** `public/legacy/index.html` tidak punya halaman statistik dan tidak diubah |
| **`/antrean`, `/riwayat`, data master** | Tidak ada perubahan kode. **Tetapi `APP_TIMEZONE` memengaruhi seluruhnya**: setiap tanggal yang dirender PHP bergeser 8 jam ke depan. Yang paling terlihat: `diffForHumans()` pada kartu antrean (`resources/views/components/kartu-antrean.blade.php:12`), `translatedFormat('j M Y')` di riwayat (`resources/views/riwayat/index.blade.php:92`), dan tanggal default form agenda (`app/Http/Controllers/AgendaController.php:43`). Semuanya berubah dari salah menjadi benar, tetapi **berubah** — V-S1 memastikannya |
| **Migration pengujian** | `database/migrations/uji/` bertambah satu tabel. Berkas itu hanya dimuat saat `APP_ENV=testing` (`app/Providers/AppServiceProvider.php:38-40`), sehingga `php artisan migrate` di produksi tetap tidak pernah melihatnya |
| **Bundle frontend** | Chart.js ~60 KB gzip, **hanya pada `/statistik`** (K-S5). Halaman lain tidak berubah ukurannya. `npm run build` wajib dijalankan |
| **Test suite** | Satu test disesuaikan (§12). Sisanya tidak tersentuh — kecuali bila ada test yang diam-diam bergantung pada tanggal UTC; V-S0 yang membuktikannya |

---

## 14. Acceptance criteria

### AC-S1 Akses dan navigasi
- [ ] `tas` dan `kepsek` sama-sama menerima **200** pada `GET /statistik`
- [ ] Tamu yang membuka `/statistik` diarahkan ke `/login`
- [ ] `kepsek` yang login mendarat di `/statistik`
- [ ] `tas` yang login **tetap** mendarat di `/antrean`
- [ ] Item "Statistik" tampil di sidebar untuk kedua role, sebagai item pertama
- [ ] Ikon "statistik" tampil, bukan ruang kosong (nama ikon yang tidak dikenal membuat `ikon.blade.php` tidak merender apa pun)

### AC-S2 Kebenaran angka
- [ ] "Total Surat Diterbitkan" hanya menghitung baris dengan `no_surat` terisi, dari kedua tabel (K-S1)
- [ ] Surat dengan `no_surat` NULL, string kosong, atau spasi **tidak** dihitung sebagai terbit (E-S4)
- [ ] Surat berstatus `ditolak` **tidak** terhitung sebagai diterbitkan
- [ ] Surat berstatus `ditolak` **tetap** terhitung pada "Total Permintaan Masuk" dan tampil di panel status
- [ ] "Total Permintaan Masuk" menghitung seluruh baris kedua tabel surat tanpa penyaringan
- [ ] Rata-rata waktu proses cocok dengan perhitungan tangan atas data uji
- [ ] Baris dengan `approved_at` NULL tidak ikut menarik rata-rata (E-S2)
- [ ] Baris dengan `approved_at < created_at` dikeluarkan (E-S6)
- [ ] Jumlah kedua irisan donat sama persis dengan angka "Total Surat Diterbitkan" (K-S4)
- [ ] Panel status memuat **setiap** nilai `status` yang ada di database, tanpa terkecuali (K-S2, E-S5)
- [ ] Jumlah seluruh angka di panel status sama dengan "Total Permintaan Masuk"
- [ ] "Permintaan lewat Chatbot" sama dengan `select count(*) from log_permintaan where status = 'diajukan'`
- [ ] Kartu itu **tidak** berlabel "Total Interaksi Bot" dan tidak menampilkan `count(*)` polos (R-S9)

### AC-S3 Tren harian
- [ ] Selalu tepat 30 titik, berakhir pada hari ini menurut WITA
- [ ] Hari tanpa surat muncul sebagai `0`, tidak dilewati (E-S8)
- [ ] Surat yang dibuat 07:00 WITA masuk ke tanggal WITA-nya, bukan hari sebelumnya (E-S7)
- [ ] Surat keterangan tanpa `created_at` tidak tergambar, dan jumlahnya disebutkan sebagai catatan kaki (E-S3)
- [ ] Dua seri terpisah: surat tugas dan surat keterangan

### AC-S4 Ketahanan
- [ ] Database tanpa satu pun surat: halaman **200**, tanpa error, tanpa `NaN`, tanpa `0 menit` yang menyesatkan (E-S1, E-S2)
- [ ] `log_permintaan` kosong → kartu menampilkan `0` (E-S13)
- [ ] `log_permintaan` tidak ada / query gagal → kartu menampilkan "—", **halaman tetap 200** dan enam metrik lain tetap tampil (E-S14)
- [ ] Rata-rata < 1 menit tampil dalam detik; > 24 jam tampil dalam hari dan jam (E-S9, E-S10)
- [ ] Dengan JavaScript dimatikan, keempat kartu angka dan tabel status tetap terbaca (E-S11)
- [ ] Halaman tidak pernah menulis ke tabel mana pun

### AC-S5 Integrasi dan aset
- [ ] Tidak ada satu pun permintaan ke n8n dari halaman ini
- [ ] Tidak ada permintaan ke host pihak ketiga mana pun saat halaman dimuat — dapat diverifikasi dari tab Network: seluruh aset berasal dari origin sendiri
- [ ] Chart.js hanya dimuat di `/statistik`; membuka `/antrean` tidak mengunduhnya
- [ ] Warna grafik memakai token `resources/css/app.css`, bukan palet bawaan Chart.js
- [ ] Tidak ada migration produksi baru; `php artisan migrate --pretend` tidak menghasilkan operasi apa pun
- [ ] Query terhadap `log_permintaan` hanya menyebut kolom `status` (K-S12), dapat diperiksa dengan membaca controller
- [ ] `no_wa` tidak muncul di HTML, JSON, maupun log mana pun

### AC-S6 Timezone
- [ ] `APP_TIMEZONE=Asia/Makassar` ada di `.env` **dan** `.env.example`
- [ ] `config/app.php` membaca `env('APP_TIMEZONE', 'UTC')`, tidak lagi mengunci nilai
- [ ] Query tren harian tetap memakai `at time zone 'Asia/Makassar'` secara eksplisit meskipun `APP_TIMEZONE` sudah disetel (§4.2)
- [ ] Seluruh suite tetap hijau setelah perubahan timezone

---

## 15. Verifikasi end-to-end

### V-S0 Dasar — sebelum menyentuh statistik
1. `php artisan test` hijau **sebelum** perubahan apa pun. Catat jumlahnya sebagai garis dasar
2. Setel `APP_TIMEZONE=Asia/Makassar`, ubah `config/app.php`, jalankan `php artisan test` lagi. **Setiap test yang berubah hasilnya di sini adalah test yang selama ini bergantung pada UTC** — periksa satu per satu sebelum melanjutkan, jangan diperbaiki dengan menyesuaikan ekspektasinya begitu saja
3. `php artisan migrate --pretend` tidak menghasilkan operasi apa pun

### V-S1 Timezone pada halaman yang sudah dipakai — regresi
Dijalankan **sebelum** halaman statistik dibuat, supaya bila ada yang rusak penyebabnya tidak tersamar.

1. **Buka `/riwayat`.** Empat baris `surat_tugas` di produksi tanggalnya **berpindah satu hari** karena `created_at`-nya jatuh antara 16:00–24:00 UTC. Inilah perubahan yang paling terlihat, dan ia perbaikan: tanggal barunya sesuai hari kerja sungguhan
2. **Buka `/agenda`, klik tambah kegiatan.** Tanggal default harus **hari ini menurut WITA**. Bedanya hanya muncul antara pukul 00:00–08:00 WITA; di luar jam itu keduanya sama dan langkah ini tidak membuktikan apa-apa
3. **Setujui satu surat uji, lalu periksa `app.aktivitas.created_at`.** Ini yang paling penting dan paling mudah terlewat — kolomnya `timestamptz`, dan **sebelum K-S15 nilainya akan tersimpan +8 jam di masa depan**. Bandingkan dengan jam dinding, bukan dengan tampilan halaman (keduanya akan sama-sama salah bila K-S15 tidak dipasang)
4. **Yang TIDAK berubah dan tidak perlu diperiksa:** "x jam yang lalu" pada kartu antrean. `diffForHumans()` membandingkan dua instan absolut, sehingga ia sudah benar bahkan ketika aplikasi masih UTC. Rancangan awal dokumen ini keliru menyebutnya terpaut 8 jam — dikoreksi 19 Agu 2026 setelah diperiksa terhadap data sungguhan

### V-S2 Otorisasi — manual, menggantikan test yang tidak dicakup (§12)

**✅ Lulus 19 Agu 2026** (langkah 6). Dijalankan sekali lewat berkas uji sementara yang kemudian dihapus — pemeriksaan, bukan tes permanen; risiko yang diterima R-S5 tetap berlaku untuk perubahan berikutnya.

1. ✅ Login sebagai `kepsek` → mendarat di `/statistik`. Ini satu-satunya langkah yang kini dijaga tes permanen (`LoginTest`)
2. ✅ Sebagai `kepsek`, buka `/statistik` → **200**, bukan 403
3. ✅ Login sebagai `tas` → mendarat di `/antrean`; `/statistik` → **200**
4. ✅ Logout, buka `/statistik` → diarahkan ke `/login`
5. ✅ Penyaringan menu tidak terganggu oleh item baru: `tas` melihat tujuh menu, `kepsek` melihat statistik, riwayat, agenda, dan status kepsek

**Catatan cara memeriksa:** menu harus dicocokkan lewat `href`, bukan lewat teks labelnya. Item menu merender ikon lalu label pada baris terpisah, sehingga pencocokan teks memberi jawaban yang salah di **kedua** arah — "Antrean" tidak ditemukan meski ada, dan "Statistik" ditemukan lewat `<title>` serta `<h1>` meski menunya tidak dirender. Kekeliruan ini benar-benar terjadi pada percobaan pertama V-S2.

### V-S3 Ketahanan data — manual, menggantikan test yang tidak dicakup (§12)
Seluruhnya pada database **pengujian** (`surat_uji`, port 5433 — **jangan produksi**).
1. Kosongkan kedua tabel surat dan `log_permintaan`, buka `/statistik`: halaman tampil dengan angka nol dan "belum ada data", bukan error
2. Sisipkan satu surat tugas dengan `approved_at` NULL saja: kartu rata-rata menampilkan "—", bukan `0 menit`
3. Sisipkan satu surat keterangan dengan `created_at` NULL: catatan kaki tren harian menyebutkannya
4. Sisipkan satu surat dengan `status = 'entah_apa'`: nilainya muncul di panel status dengan label apa adanya
5. **Ganti nama tabel `log_permintaan` sementara**, muat `/statistik`: kartu interaksi bot menampilkan "—", **halaman tetap 200**, enam metrik lain tetap benar. Kembalikan namanya

### V-S4 Angka terhadap data produksi
1. Buka `/statistik` dan `/riwayat` berdampingan
2. Saring `/riwayat` dengan jenis = surat tugas, hitung totalnya dari teks "n surat ditemukan" (`resources/views/riwayat/index.blade.php:60`). Bandingkan dengan irisan donat surat tugas — **selisih apa pun harus dapat dijelaskan** oleh surat yang belum bernomor, bukan didiamkan
3. Ulangi untuk surat keterangan
4. Jumlahkan angka panel status; harus sama dengan "Total Permintaan Masuk"
5. Saring `/riwayat` dengan status = `ditolak`; jumlahnya harus sama dengan baris `ditolak` di panel status
6. Bandingkan kartu "Permintaan lewat Chatbot" dengan `select count(*) from public.log_permintaan where status = 'diajukan'` langsung di Supabase
7. **Bandingkan angka itu dengan "Total Permintaan Masuk".** Per 19 Agu 2026 selisihnya tepat satu (20 vs 19) — dan selisih itu belum dijelaskan (§16.1). Selisih yang membesar berarti surat hilang dari tabel tanpa sepengetahuan Laravel

### V-S5 Kinerja dan aset

**Sebagian sudah diperiksa 19 Agu 2026** (langkah 7), lewat berkas uji sementara yang kemudian dihapus:
- ✅ `/statistik` memuat tepat dua aset, keduanya dari origin sendiri: `app.css` dan `statistik.js`
- ✅ `statistik.js` **tidak** dimuat di halaman lain
- ✅ Tidak ada satu pun URL ke host di luar `surat.test`
- ✅ Kedua kanvas terender, dan legenda komposisi tetap ada sebagai HTML di sebelahnya

Sisanya menuntut peramban sungguhan dari jaringan sekolah:
1. Buka `/statistik` dari jaringan sekolah, bukan dari mesin pengembang. Catat waktu muatnya
2. Tab Network: pastikan **tidak ada** permintaan ke host selain origin sendiri
3. Buka `/antrean`, pastikan Chart.js **tidak** ikut terunduh di sana
4. Matikan JavaScript, muat ulang `/statistik`: keempat kartu angka dan tabel status tetap terbaca (E-S11)
5. Bila waktu muat terasa mengganggu, K-S10 (tanpa cache) ditinjau ulang — dengan angka, bukan dengan kesan

---

## 16. Yang masih perlu dikonfirmasi

**Terjawab 19 Agu 2026** dan sudah dipindahkan ke dalam isi dokumen: `approved_at` terisi; timezone Supabase UTC; `log_permintaan` hidup dan mencatat perpindahan status dengan kosakata `diajukan`/`menunggu_ttd`/`diterbitkan`; volume data kini 19 surat. Yang tersisa:

**~~16.1 Satu baris `surat_keterangan` hilang.~~ — TERJAWAB 19 Agu 2026.** Dihapus manual lewat SQL editor Supabase saat uji coba, bukan oleh n8n. Kemungkinan 2 pada §14.1 spesifikasi induk **tidak** terjadi; angka "Total Permintaan Masuk" tetap dapat dipercaya sebagai cerminan isi tabel.

Sisa risikonya berpindah ke RS5 spesifikasi induk, bukan ke ketepatan metrik: SQL editor tetap dapat menghapus baris tanpa melewati Laravel, dan tidak ada halaman mana pun yang akan menyadarinya. Selisih tetap satu antara `diajukan` (20) dan jumlah baris surat (19) kini terjelaskan seluruhnya.

**16.2 Nilai `status` untuk surat yang ditolak — belum terlihat di data.**
Pemilik sistem menyatakan nilainya `ditolak` dan barisnya dipertahankan. Query 19 Agu 2026 tidak menemukan satu pun baris berstatus itu — belum pernah ada surat ditolak, jadi pernyataan tersebut belum dapat dibandingkan dengan kenyataan. **Tidak memblokir:** K-S1 tidak menyebut nilai status apa pun, dan K-S2 menampilkan apa pun yang muncul. RS14 pada spesifikasi induk tetap terbuka sampai ada satu penolakan sungguhan.

**16.3 Apakah tahun ajaran perlu menjadi satuan waktu nanti.**
Rilis pertama memakai kalender (30 hari terakhir, sepanjang waktu). Bila pimpinan ternyata berpikir dalam tahun ajaran, batas tahun ajarannya harus ditentukan — informasi itu tidak ada di mana pun di database.

**16.4 Rentang `log_permintaan` hanya dua hari** (18–19 Agu 2026). Tabelnya tampak baru atau pernah dibersihkan. Konsekuensinya: metrik 7 tidak sebanding dengan metrik 1 dan 3 yang mencakup sejak Juli. **Keterangan pada kartunya wajib menyebut hal ini** selama rentangnya masih jauh lebih pendek daripada riwayat surat.

---

## 17. Risiko dan trade-off

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| **R-S1** | **Perubahan `APP_TIMEZONE` menyentuh seluruh aplikasi**, termasuk `/antrean` yang dipakai staf setiap hari | Tanggal di seluruh halaman bergeser 8 jam sekaligus. Bila ada perilaku yang diam-diam bergantung pada UTC, ia rusak di semua tempat pada saat yang sama | Dikerjakan dan diverifikasi **terpisah lebih dulu** (V-S0 langkah 2, V-S1) sebelum satu baris kode statistik ditulis, supaya penyebab kerusakan tidak tersamar di antara fitur baru. Ini juga alasan K-S7 dipilih di atas konversi lokal: dua definisi "hari" dalam satu aplikasi adalah utang yang bunganya dibayar setiap fitur berikutnya |
| ~~R-S2~~ | ~~`approved_at` mungkin tidak pernah diisi n8n~~ | — | **Gugur 19 Agu 2026** — terverifikasi terisi |
| **R-S3** | **`APP_TIMEZONE` tidak menyetel timezone sesi Postgres** | Query yang mengelompokkan tanggal di sisi SQL tetap salah 8 jam — dan **tidak menghasilkan error**, hanya angka yang keliru | `at time zone 'Asia/Makassar'` ditulis eksplisit di setiap query tanggal (§4.2). Timezone Supabase terverifikasi UTC, sehingga konversinya menggeser tepat sekali. Satu test khusus (§12) menjadi jaring pengamannya |
| **R-S4** | **Dependency JS pertama yang benar-benar dimuat.** Sampai hari ini layout hanya memuat `@vite('resources/css/app.css')` — `resources/js/app.js` tidak pernah dimuat halaman mana pun | Menambah permukaan pemeliharaan: pembaruan Chart.js, ukuran bundle, kompatibilitas peramban | Entry point terpisah (K-S5) menjaga biayanya tetap di satu halaman. E-S11 memastikan halaman tetap berguna tanpa JavaScript, sehingga kegagalan Chart.js tidak pernah menjadi kegagalan halaman |
| **R-S5** | **Test otorisasi dan test ketahanan NULL sengaja tidak dicakup** (§12) | Route yang salah tempat membuat kepsek 403 di berandanya sendiri; pembagian nol dan kegagalan `log_permintaan` lolos ke produksi | V-S2 dan V-S3 sebagai pemeriksaan manual. Perlu diulang setiap kali route atau perhitungan rata-rata disentuh — dan pengulangan manual inilah biayanya |
| **R-S6** | **Angka statistik dan angka `/riwayat` dapat berbeda** | Staf melihat dua angka berbeda untuk hal yang terasa sama, lalu tidak mempercayai keduanya | Selisihnya kini punya penjelasan lengkap: `/riwayat` menghitung seluruh baris, statistik menghitung yang bernomor (K-S1), dan sisanya terdiri dari `ditolak` + `menunggu` yang keduanya terbaca di panel status. Metrik 3 ditampilkan justru supaya selisih itu terlihat di layar yang sama, bukan ditemukan sebagai kejutan. **Yang tidak dapat dijelaskan halaman ini** adalah baris yang dihapus dari luar lewat SQL editor — terbukti pernah terjadi (§16.1), dan tidak meninggalkan jejak di mana pun (RS5) |
| **R-S7** | **Data produksi terlalu sedikit** (RS13) | Grafik hampir kosong saat rilis; kinerjanya tidak teruji secara nyata | Terima. E-S1 dan E-S8 memastikan halaman kosong tetap terbaca sebagai "belum ada data", bukan sebagai kerusakan. Jangan menyimpulkan kinerja dari data sekecil ini |
| **R-S8** | **Skema `public` dapat berubah oleh n8n tanpa sepengetahuan Laravel** (RS3) | Halaman statistik rusak mendadak, sama seperti riwayat | Konsekuensi K2 yang sudah diterima. Feature test §12 menyentuh setiap kolom yang dipakai, sehingga perubahan kolom tertangkap saat pengujian |
| **R-S9** | ~~Arti satu baris `log_permintaan` belum dipastikan~~ — **terbukti dan sudah ditangani** | Dugaannya benar: `count(*)` menghitung **peristiwa**, bukan permintaan. Kartu berlabel "Total Interaksi Bot" berisi 39 akan dibaca sebagai jumlah percakapan, padahal pengajuannya hanya 20. Persis risiko yang dikhawatirkan: tidak ada error, tidak ada test merah, angkanya hanya berarti hal lain dari yang dikira | K-S12 diamandemen: menghitung baris `diajukan` saja, dan **labelnya "Permintaan lewat Chatbot", bukan "Total Interaksi Bot"**. Sisa risikonya berpindah ke E-S15 (nama peristiwa berubah) dan §16.4 (rentang waktunya jauh lebih pendek dari riwayat surat) |
| **R-S11** | **Pengujian tidak dapat menangkap ketidakcocokan timezone app vs sesi DB** (K-S15) | Postgres lokal ber-timezone `Asia/Kuala_Lumpur`, offsetnya kebetulan sama dengan WITA. Kesalahan konfigurasi yang merusak setiap `timestamptz` di produksi akan **hijau sempurna** di seluruh 132 test. Ini kebalikan dari niat `phpunit.xml`, yang justru memilih port 5433 supaya tidak ada nilai tes yang kebetulan cocok dengan produksi — di sini kebetulan itu terjadi pada hal yang tidak dipatok siapa pun | Invariannya ditegakkan di konfigurasi, bukan diserahkan pada pengujian: `config/database.php` menurunkan timezone sesi dari `APP_TIMEZONE` sehingga keduanya tidak dapat berbeda tanpa seseorang sengaja menyetel `DB_TIMEZONE`. Alasannya ditulis panjang di berkas itu, karena satu-satunya penjaga yang tersisa adalah orang yang membacanya |
| **R-S10** | **`log_permintaan` adalah tabel pertama yang dibaca Laravel tanpa kesepakatan apa pun atasnya.** `surat_*` dan data master setidaknya punya K2/K3; tabel ini murni telemetri internal chatbot | n8n boleh mengganti nama, memangkas, atau membersihkan tabel ini kapan saja tanpa memberi tahu siapa pun, dan itu bukan kesalahan n8n | E-S14: kegagalan query pada kartu ini tidak boleh menjatuhkan halaman. K-S12 menjaga ketergantungannya setipis mungkin — satu kolom, satu nilai. E-S15 menjaga sisi yang tidak melempar galat: nama peristiwa yang berubah membuat angkanya jatuh ke nol secara diam-diam |

---

## 18. Urutan pengerjaan yang disarankan

1. ✅ **SELESAI 19 Agu 2026.** Query verifikasi produksi. Hasilnya mengubah metrik 7 (K-S12 diamandemen, R-S9 terbukti) dan memunculkan §16.1 sebagai pertanyaan baru
2. ✅ **SELESAI 19 Agu 2026.** Timezone, tersendiri. `config/app.php`, `.env`, `.env.example` — **ditambah `config/database.php` dan `phpunit.xml`** yang tidak ada di rancangan awal (K-S15). V-S0 lulus: 132 test hijau sebelum dan sesudah, `migrate --pretend` bersih. **V-S1 langkah 3 belum dijalankan** karena menuntut persetujuan surat sungguhan
3. ✅ **SELESAI 19 Agu 2026.** `log_permintaan` ke `database/migrations/uji/`, komentar yang menyatakan tabel itu sengaja tidak dibuat sudah diperbarui, dan namanya masuk ke `down()`. Suite tetap **132 hijau tanpa satu pun test baru**; struktur di DB uji terverifikasi identik dengan produksi
4. ✅ **SELESAI 19 Agu 2026.** Controller + Blade tanpa grafik: empat kartu, tabel status, legenda komposisi, dan blok JSON — seluruhnya dirender server. **Route ikut dikerjakan di sini**, menyimpang dari langkah 6: tanpa route halaman tidak dapat dirender maupun diuji, sehingga langkah 5 mustahil. Menu sidebar, ikon, dan `rutaBeranda()` tetap di langkah 6. Blok JSON juga dimajukan ke sini karena ia HTML biasa, dan §12 memang mengandalkannya sebagai cara membaca angka saat pengujian
5. ✅ **SELESAI 19 Agu 2026.** `tests/Feature/Statistik/StatistikTest.php` — 11 test, 54 assertion; suite naik dari 132 ke **143 hijau**. **Penyesuaian `LoginTest.php:30-37` DIPINDAH ke langkah 6**: test itu menguji tujuan pendaratan kepsek, jadi ia harus berubah bersama `rutaBeranda()`. Mengubahnya lebih dulu membuat suite merah di antara dua commit
6. ✅ **SELESAI 19 Agu 2026.** Menu sidebar, ikon, `rutaBeranda()`, dan penyesuaian `LoginTest` yang ditunda dari langkah 5 — perilaku dan tesnya berpindah dalam satu commit. Route sudah dikerjakan di langkah 4. **V-S2 lulus seluruhnya**, dijalankan sekali lewat berkas sementara yang kemudian dihapus
7. ✅ **SELESAI 19 Agu 2026.** Chart.js 4.5.1, entry Vite tersendiri, `resources/js/statistik.js`, dan **dua kanvas — bukan tiga**. Panel status tetap batang HTML/CSS: ia sudah terbaca tanpa JavaScript, dan menggantinya dengan kanvas akan menghapus properti yang sudah ada (E-S11) tanpa menambah apa pun. Bundel `statistik.js` **57,7 kB gzip**, dimuat **hanya** di `/statistik`
8. **V-S3, V-S4, V-S5**

Langkah 4 sengaja mendahului langkah 7, dengan alasan yang sama seperti §17 spesifikasi induk menempatkan riwayat sebelum antrean: bagian yang tidak bergantung pada hal baru dikerjakan lebih dulu, supaya ketika yang baru diperkenalkan, hanya ada satu tersangka.

Yang tidak boleh berpindah: **langkah 2 tetap paling awal di antara pekerjaan kode.** Menggabungkan perubahan timezone dengan fitur baru berarti membuang satu-satunya kesempatan melihat dampaknya secara terpisah.
