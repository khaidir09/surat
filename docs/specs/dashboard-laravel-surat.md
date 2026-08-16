# Spesifikasi: Dashboard Laravel untuk Sistem Surat SMKN 1 Amuntai

**Status:** Dalam pengerjaan — §17 langkah 1 selesai, lanjut ke langkah 2 (auth + role). §14.2, §14.3, §14.6, §14.8 terjawab; §14.1 terjawab sebagian (nilai penolakan belum diketahui, ditangani lewat K12); §14.4, §14.5 relevan saat menulis migration
**Tanggal:** 15 Agustus 2026, diperbarui 16 Agustus 2026
**Penulis:** Hasil wawancara kebutuhan dengan Muhammad Khaidir

---

## 1. Konteks dan masalah

Sistem surat SMKN 1 Amuntai saat ini terdiri dari tiga bagian:

1. **Chatbot WhatsApp/Telegram** — pemohon mengajukan surat lewat percakapan. State percakapan disimpan di tabel `sesi` (`chat_id`, `stage`, `draft_json`). Seluruh logikanya ada di n8n.
2. **Workflow n8n** (`n8n.dewakreatif.com`) — memiliki database, membuat PDF dari template Handlebars, dan mengirim notifikasi.
3. **Dashboard persetujuan** (`index.html`, 748 baris) — satu file HTML statis tanpa build step, dipakai staf TAS untuk menyetujui/menolak permintaan yang masuk.

**Masalah yang diselesaikan spesifikasi ini:**

| # | Masalah | Bukti |
|---|---|---|
| M1 | Data master (`pegawai`, `siswa`, `agenda`) hanya bisa diubah lewat SQL editor Supabase. Tidak ada UI, tidak ada validasi, rawan salah ketik. | Tidak ada antarmuka CRUD di `index.html` |
| M2 | Tidak ada halaman riwayat. Surat yang sudah diproses hilang dari pandangan meski datanya tersimpan permanen. | `render()` di `index.html:573-602` hanya menampilkan status `menunggu` dan `menunggu_ttd` |
| M3 | Tidak ada identitas pengguna. Satu kode akses dipakai bersama seluruh staf TAS, sehingga tidak diketahui siapa menyetujui apa. | `secret` di `index.html:459`, dikirim sebagai header `x-dashboard-secret` (`index.html:511`) |
| M4 | Kode akses diketik di browser dan berada di memori klien. | `index.html:707-716` |
| M5 | Kepala Sekolah tidak bisa memperbarui status kehadirannya sendiri; harus lewat staf TAS. | Panel status kepsek di `index.html:379-443` hanya ada di dashboard TAS |

---

## 2. Ringkasan solusi

Membangun aplikasi **Laravel 12** yang menggantikan `index.html` sebagai antarmuka staf, dengan tambahan halaman riwayat dan CRUD data master. **n8n tetap memegang seluruh logika bisnis surat** — Laravel tidak mengambil alih approval, penomoran, pembuatan PDF, maupun notifikasi.

```
                    ┌─────────────────────────────────────┐
                    │        Laravel 12 (BARU)            │
  Staf TAS ────────▶│  login per-user + role              │
  Kepala Sekolah    │                                     │
                    │  • Antrean persetujuan (port dari   │──── HTTP ────▶ n8n webhooks
                    │    index.html)                      │      (secret dari .env)
                    │  • Riwayat surat        ─────────┐  │
                    │  • CRUD pegawai/siswa/agenda ────┤  │
                    │  • Status kepsek                 │  │
                    └──────────────────────────────────┼──┘
                                                       │ Eloquent (baca + tulis master)
                                                       ▼
                    ┌──────────────────────────────────────┐
                    │  Supabase Postgres                   │
                    │  schema public ← milik n8n           │
                    │  schema app    ← milik Laravel (BARU)│
                    └──────────────────────────────────────┘
                                       ▲
                    Chatbot WA ────────┘ (lewat n8n, di luar scope)
```

---

## 3. Keputusan arsitektur

| # | Keputusan | Alasan |
|---|---|---|
| K1 | n8n tetap memegang approval, penomoran, PDF, notifikasi | Workflow n8n tidak ada di repo ini dan tidak dapat dibaca. Memindahkannya berarti menulis ulang logika yang tidak terdokumentasi — risiko terlalu besar untuk nilai yang kecil |
| K2 | Laravel membaca tabel surat **langsung** via Eloquent | Halaman riwayat butuh filter, pencarian, dan paginasi. Lewat webhook akan lambat dan menuntut perubahan n8n |
| K3 | Laravel adalah **satu-satunya penulis** `pegawai`, `siswa`, `agenda` | Menghindari konflik dua penulis. **Terkonfirmasi 16 Agu 2026:** n8n hanya membaca ketiga tabel ini; satu-satunya penulis saat ini adalah SQL editor Supabase secara manual — dan itulah yang digantikan Laravel |
| K4 | Tabel milik Laravel ditaruh di **schema `app`**, terpisah dari `public` | Batas kepemilikan tegas. `php artisan migrate` tidak akan pernah menyentuh tabel n8n; `migrate:fresh` yang salah jalan tidak menghapus data produksi |
| K5 | Secret n8n disimpan di `.env` server, **tidak pernah dikirim ke browser** | Memperbaiki M4. Pengguna login ke Laravel; Laravel yang menempelkan `x-dashboard-secret` saat memanggil webhook |
| K6 | **Workflow n8n tidak diubah sama sekali** | Keputusan pemilik sistem. Konsekuensi: lihat K7 |
| K7 | Audit trail dicatat Laravel di tabel `app.aktivitas` sendiri | Karena K6, `public.surat_*.approved_by` tetap diisi n8n dengan nilai lama. Ada dua catatan yang bisa berbeda — diterima sebagai trade-off |
| K8 | Perubahan skema hanya **penambahan kolom**, tidak mengubah tipe kolom yang ada | Kolom baru tidak merusak query n8n yang sudah jalan. `siswa.tanggal_lahir` tetap `text` meski seharusnya `date` |
| K9 | Penomoran surat tetap manual | Sesuai buku agenda fisik. Riwayat git (`0ea017f` → `5c8b481` → dipasang lagi) menunjukkan ini keputusan sadar, bukan kelalaian |
| K10 | Blade + Tailwind, tanpa Livewire/Filament | Paling dekat dengan `index.html` yang sudah ada dan bisa dipahami tanpa mempelajari abstraksi baru |
| K11 | Jika n8n tidak dapat dihubungi, operasi **gagal dan menampilkan error** | Sama seperti perilaku sekarang (`index.html:638-641`). Tanpa antrean retry, tanpa state tambahan, tanpa risiko keputusan ganda |
| K12 | Filter status di halaman riwayat **dibangun dari data** (`select distinct status`), bukan dari enum yang di-hardcode | Kolom `status` bertipe `text` tanpa constraint dan daftar nilainya belum lengkap (§14.1). Enum hardcoded akan menyembunyikan status yang belum diketahui — persis cacat E3 yang sedang diperbaiki. Data-driven membuat nilai baru dari n8n muncul sendiri tanpa perlu mengubah kode Laravel |

---

## 4. Prasyarat teknis

**Blocker yang harus dibereskan sebelum baris kode pertama:**

| Prasyarat | Kondisi terverifikasi | Tindakan |
|---|---|---|
| `pdo_pgsql` | ✅ **Aktif dan terverifikasi** 16 Agu 2026 — `php -m` menampilkan `pdo_pgsql` | Selesai. Hanya ada satu `php.ini` (`C:\laragon\bin\php\php-8.2.24-nts-Win32-vs16-x64\php.ini`, tanpa scan direktori tambahan), jadi CLI dan Apache memakai konfigurasi yang sama. Ekstensi `pgsql` standalone tidak diperlukan Laravel |
| PHP | 8.2.24 — memenuhi syarat minimum Laravel 12 (8.2) | Tidak ada tindakan |
| Composer | 2.3.5 (rilis 2022) | Sebaiknya `composer self-update`; versi lama pernah bermasalah dengan resolusi dependency Laravel modern |
| Node | v22.12.0 — cukup untuk Vite + Tailwind | Tidak ada tindakan |
| `.gitignore` | **Tidak ada di repo.** | Wajib dibuat sebelum `composer create-project`, jika tidak `vendor/`, `node_modules/`, dan **`.env`** akan ikut ter-commit |
| Sequence `pegawai`, `siswa`, `agenda` | **Belum diperiksa** — lihat RS12 | Jalankan V-0 langkah 4. Jika ada yang tertinggal, setel ulang sekali sebelum Laravel menulis:<br>`select setval(pg_get_serial_sequence('public.pegawai','id'), (select max(id) from public.pegawai));`<br>Ulangi untuk `siswa` dan `agenda`. **Ini operasi tulis** — jalankan sadar, di luar jam pakai, dan hanya jika langkah 4 memang menunjukkan ketertinggalan |

---

## 5. Data model

### 5.1 Tabel yang sudah ada (schema `public`, milik n8n)

Terverifikasi dari `information_schema.columns`. **Laravel tidak boleh mengubah struktur kolom-kolom ini.**

**`surat_tugas`** — `id` integer (sequence), `no_surat` text, `tanggal_surat` date, `kegiatan` text, `tempat` text, `tanggal_mulai` date, `tanggal_selesai` date, `dasar` text, `penandatangan` text, `pemohon_wa` bigint, `status` text NOT NULL default `'menunggu'`, `pdf_url` text, `created_at` timestamptz default `now()`, `approved_by` text, `approved_at` timestamptz, `pembebanan_biaya` text

**`surat_keterangan`** — `id` integer (sequence), `no_surat` text, `siswa_id` integer, `keperluan` text, `pemohon_wa` bigint, `status` text NOT NULL default `'menunggu'`, `pdf_url` text, `created_at` timestamptz, `approved_by` text, `approved_at` timestamptz

#### 5.1.1 Nilai `status` yang diketahui

Kolom `status` bertipe `text` **tanpa constraint maupun enum**, sehingga daftar ini bersifat empiris, bukan jaminan.

| Nilai | Sumber pengetahuan | Arti |
|---|---|---|
| `menunggu` | default kolom + `index.html:575,581` | Perlu persetujuan TAS |
| `menunggu_ttd` | `index.html:578` | Disetujui, menunggu TTD kepsek |
| `selesai` | data nyata (16 Agu 2026) | Sudah ditandatangani dan diserahkan |
| *(penolakan)* | **belum diketahui** | Lihat §14.1 |

**Volume data saat ini: 3 surat seluruhnya** (1 surat tugas, 2 surat keterangan), semuanya berstatus `selesai`. Antrean sedang kosong. Sistem masih sangat baru — ini memengaruhi prioritas beberapa hal, lihat RS13.

**`surat_tugas_petugas`** — `surat_tugas_id` integer NOT NULL, `pegawai_id` integer NOT NULL. Tabel junction murni; tidak ada kolom urutan maupun keterangan

**`pegawai`** — `id` bigint NOT NULL **identity `BY DEFAULT`** (terverifikasi 16 Agu 2026), `created_at` timestamptz NOT NULL default `now()`, `nama` varchar, `nip` varchar, `pangkat_golongan` varchar, `jabatan` varchar. Seluruh kolom selain `id` dan `created_at` nullable — tampilan wajib tahan NULL (E11)

**`siswa`** — `id` integer (sequence), `nama` text NOT NULL, `tempat_lahir` text, `tanggal_lahir` **text**, `kelas` text, `jurusan` text, `nis_nisn` text, `nama_orang_tua` text, `alamat` text

**`agenda`** — `id` integer (sequence), `tanggal_mulai` date NOT NULL, `tanggal_selesai` date NOT NULL, `judul` text NOT NULL, `kategori` text

**`status_kepsek`** — `id` integer default `1` (tabel baris tunggal), `status` text NOT NULL default `'Di sekolah'`, `keterangan` text, `estimasi_kembali` text, `diperbarui_pada` timestamptz default `now()`, `diperbarui_oleh` text

**`counter`** — `tahun` integer NOT NULL, `n` integer NOT NULL default `0`. **Tabel mati** — dikonfirmasi tidak dipakai lagi (16 Agu 2026), sisa dari percobaan penomoran otomatis yang di-revert (`0ea017f` → `5c8b481`). Laravel tidak membaca maupun menulisnya

**`log_permintaan`** — `id`, `waktu`, `no_wa` bigint, `jenis_surat` text, `status` text, `durasi_proses_detik` integer. Telemetri chatbot

**`sesi`** — `chat_id` bigint NOT NULL, `stage` text, `draft_json` jsonb, `updated_at` timestamptz, `jenis` text. State machine percakapan chatbot. **Laravel tidak menyentuh tabel ini**

### 5.2 Pemetaan skema ke item model dashboard

Field `jenis` yang dipakai `render()` (`index.html:574-582`) **tidak ada di database** — n8n mensintesisnya saat menggabungkan dua tabel menjadi satu array:

| `jenis` di dashboard | Tabel sumber | Catatan |
|---|---|---|
| `"surat_tugas"` | `public.surat_tugas` | Nama cocok |
| `"keterangan_aktif"` | `public.surat_keterangan` | **Nama tidak cocok dengan tabelnya.** Laravel harus mempertahankan nilai `keterangan_aktif` saat memanggil `/surat-keputusan`, bukan `surat_keterangan` |

Field `nama_siswa` dan `kelas_jurusan` (`index.html:553-555`) juga tidak ada sebagai kolom — n8n melakukan join ke `siswa` dan menggabungkan `kelas` + `jurusan`. Laravel melakukan hal yang sama lewat relasi Eloquent.

### 5.3 Penambahan skema (migration Laravel ke schema `public`)

Hanya penambahan kolom, sesuai K8. Setiap kolom nullable agar baris lama tetap valid dan query n8n tidak terpengaruh.

```
siswa      + deleted_at  timestamptz NULL
           + aktif       boolean NOT NULL DEFAULT true
           + tahun_masuk integer NULL

pegawai    + deleted_at  timestamptz NULL
           + aktif       boolean NOT NULL DEFAULT true

agenda     + created_at  timestamptz NULL DEFAULT now()
           + updated_at  timestamptz NULL
           + deleted_at  timestamptz NULL
           + tempat      text NULL
           + keterangan  text NULL

surat_tugas_petugas + urutan integer NULL
                    + ket    text NULL
```

Dua kolom terakhir menutup lubang di §14.1: `{{ket}}` dan `{{no}}` pada `template/surat_tugas_template.html:91,95`. **Kolom ini hanya berguna jika n8n dimodifikasi untuk membacanya** — karena K6 melarang itu, kolom ini disiapkan untuk masa depan dan sementara hanya diisi/ditampilkan oleh Laravel.

### 5.4 Tabel baru (schema `app`, milik Laravel)

```
app.users              id, nama, email, password, role, aktif, timestamps
app.sessions           bawaan Laravel
app.cache              bawaan Laravel
app.jobs               bawaan Laravel
app.password_reset_tokens  bawaan Laravel

app.aktivitas          id
                       user_id      → app.users
                       aksi         string   (setujui|tolak|selesai|status_kepsek|
                                              buat_siswa|ubah_siswa|hapus_siswa|…)
                       subjek_tipe  string   (surat_tugas|surat_keterangan|siswa|
                                              pegawai|agenda|status_kepsek)
                       subjek_id    integer  nullable
                       detail       jsonb    nullable (mis. {"nomor":"421"})
                       ip           string   nullable
                       created_at   timestamptz
```

`app.aktivitas` adalah kompensasi atas K7: karena n8n tidak diubah, inilah satu-satunya tempat identitas penyetuju tercatat.

### 5.5 Model Eloquent

| Model | Tabel | Catatan penting |
|---|---|---|
| `User` | `app.users` | Punya `role` |
| `Aktivitas` | `app.aktivitas` | Hanya ditulis, tidak pernah diubah |
| `SuratTugas` | `public.surat_tugas` | **Read-only dari Laravel.** `$guarded = ['*']`. `belongsToMany(Pegawai::class, 'surat_tugas_petugas')` |
| `SuratKeterangan` | `public.surat_keterangan` | **Read-only dari Laravel.** `belongsTo(Siswa::class)` |
| `Pegawai` | `public.pegawai` | CRUD penuh + SoftDeletes |
| `Siswa` | `public.siswa` | CRUD penuh + SoftDeletes. Accessor `kelas_jurusan` |
| `Agenda` | `public.agenda` | CRUD penuh + SoftDeletes |
| `StatusKepsek` | `public.status_kepsek` | Baris tunggal, tidak ditulis langsung — perubahan lewat webhook n8n |

Model surat wajib read-only di sisi Laravel: statusnya milik n8n, dan menulisnya langsung akan melanggar K1 sekaligus membuat dua sumber kebenaran.

---

## 6. Role dan otorisasi

Dua role. Guru dan siswa **tidak** memiliki akun — mereka berinteraksi lewat chatbot.

| Kemampuan | `tas` | `kepsek` |
|---|:---:|:---:|
| Melihat antrean persetujuan | ✅ | ❌ |
| Menyetujui / menolak surat | ✅ | ❌ |
| Menandai surat sudah ditandatangani | ✅ | ❌ |
| Mengunduh PDF | ✅ | ✅ |
| Melihat riwayat surat | ✅ | ✅ |
| Melihat agenda | ✅ | ✅ |
| CRUD agenda | ✅ | ❌ |
| CRUD pegawai / siswa | ✅ | ❌ |
| Mengubah status kehadiran kepsek | ✅ | ✅ |
| Mengelola akun pengguna | ✅ | ❌ |

Ditegakkan lewat Policy per model dan middleware role pada grup route. **Bukan** lewat menyembunyikan tombol di Blade — tombol yang disembunyikan tetap harus ditolak di sisi server.

Pembuatan akun dilakukan lewat seeder dan halaman kelola pengguna oleh `tas`. Tidak ada registrasi mandiri.

---

## 7. Route dan halaman

```
GET  /login                          tamu
POST /login                          tamu
POST /logout                         auth

GET  /                               auth        → redirect sesuai role
GET  /antrean                        role:tas    Antrean persetujuan (port index.html)
POST /antrean/{jenis}/{id}/putuskan  role:tas    aksi=setujui|tolak, nomor wajib jika setujui
POST /antrean/{jenis}/{id}/selesai   role:tas    tandai sudah ditandatangani
GET  /antrean/{jenis}/{id}/pdf       auth        redirect ke URL dari n8n

GET  /riwayat                        auth        filter: jenis, status, rentang tanggal, cari
GET  /riwayat/{jenis}/{id}           auth        detail satu surat

GET  /agenda                         auth        daftar + kalender bulanan
GET  /agenda/create                  role:tas
POST /agenda                         role:tas
GET  /agenda/{id}/edit               role:tas
PUT  /agenda/{id}                    role:tas
DELETE /agenda/{id}                  role:tas    soft delete

GET|POST|PUT|DELETE /pegawai…        role:tas    resource penuh + impor CSV
GET|POST|PUT|DELETE /siswa…          role:tas    resource penuh + impor CSV

GET  /status-kepsek                  auth        form
POST /status-kepsek                  auth        teruskan ke webhook n8n

GET|POST|PUT|DELETE /pengguna…       role:tas    kelola akun
```

Parameter `{jenis}` dibatasi pada `surat_tugas|keterangan_aktif` lewat `->whereIn()`, sesuai nilai yang diterima webhook n8n.

---

## 8. Kontrak integrasi n8n

Base: `https://n8n.dewakreatif.com/webhook`, dari `config/n8n.php` yang membaca `.env`. Header `x-dashboard-secret` ditempelkan oleh service, **tidak pernah** muncul di Blade atau JavaScript.

| Endpoint | Method | Payload | Dipakai oleh |
|---|---|---|---|
| `/surat-pending` | GET | — | `AntreanController@index` |
| `/surat-keputusan` | POST | `{id, jenis, aksi, nomor}` | `AntreanController@putuskan` |
| `/surat-pdf?id=` | GET | — | `SuratPdfController` |
| `/surat-selesai` | POST | `{id}` | `AntreanController@selesai` |
| `/status-kepsek` | POST | `{status, estimasi, keterangan}` | `StatusKepsekController@store` |

**Payload tidak boleh berubah bentuknya** — n8n tidak diubah (K6). Termasuk tidak menambahkan field identitas pengguna, meski itu akan berguna. Identitas dicatat ke `app.aktivitas` saja.

Seluruhnya dibungkus satu kelas `App\Services\N8nClient` dengan:
- timeout eksplisit (usulan: 15 detik)
- `401` → lempar `N8nUnauthorizedException` → halaman error yang menyebut kredensial server bermasalah, **bukan** logout pengguna (kode akses sekarang milik server, bukan pengguna — ini perbedaan penting dari `kunciUlang()` di `index.html:700`)
- gagal koneksi / non-2xx → `N8nException` → flash error, aksi dibatalkan

---

## 9. File dan interface terkait

### Yang menjadi rujukan (repo sekarang)

| Path | Perannya bagi pekerjaan ini |
|---|---|
| `index.html:449-746` | Sumber kebenaran perilaku antrean. Setiap fungsi di sini punya padanan di Laravel |
| `index.html:451-456`, `index.html:722` | Definisi kelima endpoint — pindah ke `config/n8n.php` |
| `index.html:8-24` | Design token (`--pine #16584a`, `--amber`, `--info`, `--ket #6d3b8e`, `--approve`, `--reject`). Dipetakan ke `tailwind.config.js` agar tampilan tetap sama |
| `index.html:526-548` | `kartuTugas()` — acuan komponen `resources/views/components/kartu-tugas.blade.php` |
| `index.html:550-562` | `kartuKeterangan()` — acuan komponen kartu keterangan |
| `index.html:604-642` | `putuskan()` — acuan alur validasi nomor → busy → kirim → refresh |
| `index.html:379-443` | Panel status kepsek — dipindah jadi halaman tersendiri |
| `template/surat_tugas_template.html` | **Tidak disentuh.** Dirender n8n |
| `template/surat_keterangan_template.html` | **Tidak disentuh.** Dirender n8n |
| `index copy.html` | Snapshot basi. Dihapus saat `index.html` dipensiunkan |
| `CLAUDE.md` | Perlu ditulis ulang menyeluruh setelah migrasi, bukan ditambal |

### Yang akan dibuat

```
app/Models/{User,Aktivitas,SuratTugas,SuratKeterangan,Pegawai,Siswa,Agenda,StatusKepsek}.php
app/Services/N8nClient.php
app/Exceptions/{N8nException,N8nUnauthorizedException}.php
app/Http/Controllers/{Antrean,Riwayat,Agenda,Pegawai,Siswa,StatusKepsek,Pengguna}Controller.php
app/Http/Middleware/EnsureRole.php
app/Policies/{Agenda,Pegawai,Siswa,User}Policy.php
app/Support/CatatAktivitas.php
config/n8n.php
database/migrations/…  (schema app + penambahan kolom §5.3)
resources/views/…
tests/Feature/…
.gitignore                     ← belum ada di repo, wajib
```

---

## 10. Di luar scope

Ditegaskan agar tidak ada kebingungan di kemudian hari:

1. **Pengajuan surat oleh guru/siswa lewat Laravel.** Pengajuan tetap lewat chatbot WhatsApp/Telegram di n8n (`sesi`, `log_permintaan`).
2. **Memindahkan logika apa pun dari n8n ke Laravel** — approval, penomoran, pembuatan PDF, notifikasi.
3. **Mengubah workflow n8n** (K6).
4. **Penomoran surat otomatis.** Tabel `counter` sudah mati dan tidak disentuh — tidak dibaca, tidak ditulis, tidak dihapus.
5. **Tanda tangan digital.** Kepsek tetap menandatangani cetakan fisik; TAS menandai selesai secara manual.
6. **Antrean/retry saat n8n mati** (K11).
7. **Mengubah tipe kolom yang ada**, termasuk `siswa.tanggal_lahir` yang seharusnya `date` (K8).
8. **Mengubah template surat** di `template/`.
9. **Deployment produksi.** Fase ini hanya Laragon lokal; spesifikasi menyiapkan jalannya, tidak mengeksekusinya.
10. **Jenis surat baru** di luar surat tugas dan surat keterangan aktif.
11. **Mobile app / PWA.**
12. **Mengisi `approved_by` di Supabase dengan identitas Laravel** — konsekuensi langsung dari K6/K7.

---

## 11. Edge cases

| # | Kondisi | Perilaku yang diharapkan |
|---|---|---|
| E1 | n8n mati saat menyetujui | Transaksi dibatalkan, flash error, tidak ada baris di `app.aktivitas`, surat tetap di antrean |
| E2 | n8n membalas `401` | Halaman error "kredensial server bermasalah, hubungi admin". Pengguna **tidak** di-logout — secret milik server |
| E3 | `/surat-pending` mengembalikan item dengan `status` tak dikenal | **Harus ditampilkan** di seksi "Status tidak dikenal", bukan dibuang diam-diam. `render()` di `index.html:573-602` saat ini menyembunyikannya tanpa jejak — cacat yang tidak boleh ikut terbawa |
| E4 | Nomor surat bukan angka | Ditolak validasi server `regex:/^\d+$/`, bukan hanya di klien seperti `index.html:612` |
| E5 | Nomor surat sudah dipakai di tahun berjalan | **Peringatan, bukan penolakan.** Buku agenda fisik adalah otoritas; sistem tidak boleh memblokir |
| E6 | Dua staf menyetujui surat yang sama bersamaan | n8n yang menentukan hasilnya. Laravel menampilkan apa pun balasannya lalu memuat ulang daftar |
| E7 | Menghapus siswa yang punya riwayat surat | Soft delete saja. Riwayat tetap menampilkan namanya |
| E8 | `surat_keterangan.siswa_id` menunjuk siswa terhapus | Riwayat menampilkan nama dari relasi `withTrashed()`, diberi penanda "nonaktif" |
| E9 | `surat_keterangan.siswa_id` NULL | Tampilkan "—", jangan error. Kolom ini nullable |
| E10 | Surat lama dengan `no_surat` NULL | Tampilkan "belum bernomor" |
| E11 | `pegawai.nama` NULL | Semua kolom `pegawai` selain `id` dan `created_at` nullable. Tampilan wajib tahan NULL |
| E12 | `siswa.tanggal_lahir` berisi format tak terduga | Tampilkan apa adanya. Jangan parse, jangan error — kolomnya `text` (K8) |
| E13 | Surat tugas tanpa petugas | Junction bisa kosong. Tampilkan "belum ada petugas" |
| E14 | Urutan petugas | Postgres tidak menjamin urutan tanpa `ORDER BY`. Selalu urutkan eksplisit (`urutan`, lalu `pegawai_id`) |
| E15 | `pdf_url` kadaluarsa/kosong | Tombol unduh dinonaktifkan dengan keterangan, bukan membuka tab kosong |
| E16 | Sesi Laravel kedaluwarsa saat mengisi form | Redirect ke login, kembali ke halaman semula setelah berhasil |
| E17 | Riwayat berisi puluhan ribu baris | Paginasi wajib. Jangan pernah memuat seluruh tabel |
| E18 | Agenda `tanggal_selesai` < `tanggal_mulai` | Ditolak validasi `after_or_equal` |

---

## 12. Acceptance criteria

### AC-1 Autentikasi dan role
- [ ] Pengguna tanpa akun tidak dapat mengakses halaman mana pun selain `/login`
- [ ] `kepsek` yang membuka `/antrean`, `/pegawai`, `/siswa`, atau `/pengguna` menerima **403**
- [ ] `kepsek` dapat membuka `/riwayat`, `/agenda`, dan `/status-kepsek`
- [ ] Otorisasi ditegakkan di server; menembak route langsung tanpa lewat UI tetap ditolak
- [ ] Nilai `x-dashboard-secret` tidak muncul di HTML, JavaScript, maupun respons mana pun — dapat diverifikasi lewat "view source"

### AC-2 Antrean persetujuan
- [ ] Menampilkan tiga seksi yang sama dengan `index.html:588-601`: surat tugas menunggu, surat tugas menunggu TTD, surat keterangan menunggu
- [ ] Jumlah per seksi ditampilkan seperti `.pill` sekarang
- [ ] Menyetujui meminta nomor urut, ditolak jika bukan angka (E4)
- [ ] Nomor duplikat memunculkan peringatan namun tetap dapat dilanjutkan (E5)
- [ ] Selama permintaan berjalan, tombol kartu dinonaktifkan (padanan `.busy` di `index.html:300-303`)
- [ ] Berhasil → daftar dimuat ulang; gagal → kartu aktif kembali, pesan error tampil
- [ ] Item dengan status tak dikenal tetap terlihat (E3)
- [ ] Setiap keputusan menghasilkan satu baris `app.aktivitas` berisi `user_id`, aksi, subjek, dan nomor

### AC-3 Riwayat surat
- [ ] Menampilkan surat tugas dan surat keterangan yang sudah diproses, terbaru di atas
- [ ] Filter berfungsi: jenis, status, rentang tanggal, kata kunci
- [ ] Pilihan filter status dibangun dari `select distinct status` (K12), bukan daftar tetap di dalam kode
- [ ] Status yang belum dikenal — termasuk status penolakan yang belum diketahui (§14.1) — tetap muncul sebagai pilihan filter dan tetap tampil di daftar, dengan label apa adanya
- [ ] Terpaginasi; tidak pernah memuat seluruh tabel (E17)
- [ ] Kolom yang tampil: nomor surat, jenis, judul/nama, tanggal, status, tautan PDF
- [ ] Nilai NULL tidak menyebabkan error di halaman mana pun (E9–E11)
- [ ] Halaman detail menampilkan seluruh field, termasuk daftar petugas terurut (E14)
- [ ] Laravel tidak pernah menulis ke `surat_tugas` maupun `surat_keterangan`

### AC-4 CRUD data master
- [ ] Pegawai: tambah, ubah, nonaktifkan (soft delete), cari
- [ ] Siswa: idem, plus filter kelas dan jurusan
- [ ] Agenda: tambah, ubah, hapus, tampilan daftar dan kalender bulanan
- [ ] Menghapus = soft delete; data tetap terbaca dari riwayat (E7, E8)
- [ ] Validasi: `nama` wajib; `nip` dan `nis_nisn` unik jika diisi; tanggal agenda konsisten (E18)
- [ ] `tanggal_lahir` diterima sebagai teks bebas tanpa parsing (E12)
- [ ] Setiap perubahan tercatat di `app.aktivitas`

### AC-5 Status kepala sekolah
- [ ] `tas` dan `kepsek` sama-sama dapat memperbarui
- [ ] Pilihan status sama persis dengan `index.html:405-409`: Di sekolah, Dinas Luar, Rapat, Tidak di tempat, Cuti
- [ ] Estimasi dan keterangan opsional
- [ ] Diteruskan ke webhook n8n dengan bentuk payload yang sama
- [ ] Nilai saat ini ditampilkan saat halaman dibuka, dibaca dari `public.status_kepsek`
- [ ] Kegagalan menampilkan error, bukan sukses palsu — memperbaiki `index.html:723-745` yang tidak menangani 401

### AC-6 Integrasi dan ketahanan
- [ ] Seluruh panggilan n8n lewat `N8nClient`; tidak ada `Http::` yang tersebar di controller
- [ ] Timeout dan penanganan kegagalan sesuai E1 dan E2
- [ ] `config/n8n.php` membaca `.env`; tidak ada URL yang di-hardcode
- [ ] Migration Laravel hanya menyentuh schema `app` dan penambahan kolom §5.3
- [ ] `php artisan migrate:fresh` **tidak** menghapus tabel mana pun di schema `public` — wajib diuji di salinan database, bukan produksi

---

## 13. Verifikasi end-to-end

### V-0 Prasyarat
1. ✅ `php -m | grep pgsql` menampilkan `pdo_pgsql` — lulus 16 Agu 2026
2. `php artisan migrate --pretend` menunjukkan seluruh operasi menyasar schema `app` atau berupa `ALTER TABLE … ADD COLUMN`
3. `git status` bersih setelah `composer install` — membuktikan `.gitignore` benar
4. **Sinkronisasi sequence (RS12).** Untuk `pegawai`, `siswa`, dan `agenda`, pastikan `last_value` sequence tidak tertinggal di belakang `max(id)`:
   ```sql
   select t.tabel,
          (select last_value from pg_sequences s
            where s.schemaname = 'public' and s.sequencename = t.seq) as seq_last_value,
          t.max_id
   from (
     select 'pegawai' as tabel, 'pegawai_id_seq' as seq, (select max(id) from public.pegawai) as max_id
     union all
     select 'siswa',   'siswa_id_seq',   (select max(id) from public.siswa)
     union all
     select 'agenda',  'agenda_id_seq',  (select max(id) from public.agenda)
   ) t;
   ```
   Jika `seq_last_value < max_id` untuk tabel mana pun, sequence harus disetel ulang sebelum Laravel menulis (lihat §4). Ini **query baca saja** — perbaikannya terpisah dan harus dijalankan sadar.

### V-1 Alur persetujuan penuh (jalur utama)
1. Ajukan satu surat tugas lewat chatbot WhatsApp seperti biasa
2. Login sebagai `tas` → surat muncul di seksi "Menunggu Persetujuan"
3. Setujui dengan nomor urut baru → notifikasi sukses, surat berpindah ke "Menunggu TTD Kepsek"
4. Verifikasi di Supabase: `surat_tugas.status`, `no_surat`, dan `pdf_url` terisi oleh n8n
5. Verifikasi `app.aktivitas` berisi satu baris dengan `user_id` staf yang login
6. Klik "Unduh PDF" → PDF terbuka, kop surat dan daftar petugas benar
7. Klik "Sudah ditandatangani" → surat hilang dari antrean
8. Buka `/riwayat` → surat tersebut muncul dengan nomor dan tautan PDF

### V-2 Otorisasi
1. Login sebagai `kepsek`, buka `/antrean` → **403**
2. Kirim POST langsung ke `/antrean/surat_tugas/1/putuskan` sebagai `kepsek` → **403**
3. Sebagai `kepsek` buka `/riwayat` dan `/status-kepsek` → berhasil
4. "View source" di seluruh halaman → nilai secret tidak ditemukan

### V-3 Kegagalan n8n
1. Ubah `N8N_BASE` di `.env` ke host yang tidak ada
2. Coba setujui satu surat → pesan error tampil, surat tetap di antrean
3. Verifikasi tidak ada baris baru di `app.aktivitas`
4. Kembalikan `.env`, ulangi → berhasil

### V-4 Integritas data master
1. Tambah siswa lewat Laravel → muncul di Supabase dengan `aktif = true`
2. Ajukan surat keterangan lewat chatbot untuk siswa itu → n8n menemukannya (membuktikan penambahan kolom tidak merusak n8n)
3. Nonaktifkan siswa tersebut di Laravel → hilang dari daftar aktif
4. Buka riwayat surat keterangannya → nama tetap tampil, ditandai nonaktif (E8)

### V-5 Regresi visual
Bandingkan `/antrean` dengan `index.html` berdampingan: tiga seksi, warna per jenis surat, susunan kartu, dan penempatan tombol setara.

---

## 14. Yang masih perlu dikonfirmasi

Tidak diasumsikan. Masing-masing harus dijawab sebelum implementasi bagian terkait.

**14.1 Nilai `status` — TERJAWAB SEBAGIAN (16 Agu 2026).** Query mengembalikan hanya `selesai`: 1 baris di `surat_tugas`, 2 baris di `surat_keterangan`. Lihat §5.1.1 untuk daftar nilai yang sudah diketahui.

**Yang masih belum diketahui: nilai untuk surat yang ditolak.** Belum pernah ada surat ditolak, sehingga tidak ada baris contoh. Tiga kemungkinan, dan ketiganya berbeda konsekuensinya bagi AC-3:
1. n8n menulis status baru (mis. `ditolak`) → muncul di riwayat sebagai surat ditolak
2. n8n menghapus barisnya → penolakan tidak meninggalkan jejak apa pun di Supabase
3. n8n hanya mengirim notifikasi tanpa menyentuh baris → surat tersangkut selamanya di status `menunggu`

Karena K12 membuat filter status bersifat data-driven, ketiganya **tidak memblokir implementasi**. Tetap perlu dipastikan sebelum AC-3 dinyatakan lulus, lewat salah satu cara: tolak satu surat uji lalu amati barisnya (operasi tulis — keputusan pemilik sistem), atau periksa node terkait di workflow n8n.

**14.2 ~~Apakah `counter` masih dipakai?~~ — TERJAWAB (16 Agu 2026).** Tidak dipakai lagi. Ditandai sebagai tabel mati di §5.1. Laravel mengabaikannya. Penghapusan tabelnya di luar scope.

**14.3 ~~Apakah `pegawai.id` kolom identity?~~ — TERJAWAB (16 Agu 2026).** Ya: `is_identity = YES`, `identity_generation = BY DEFAULT`. Eloquent dapat menambah pegawai tanpa menyuplai id, seperti perilaku bawaannya. **Tetapi lihat RS12** — `BY DEFAULT` mengizinkan insert dengan id eksplisit tanpa menggerakkan sequence, dan data selama ini diisi manual lewat SQL editor.

**14.4 Foreign key yang benar-benar ada.** Belum diverifikasi apakah `surat_keterangan.siswa_id` dan `surat_tugas_petugas` punya FK sungguhan. Perlu diketahui sebelum mengandalkan `ON DELETE` apa pun.

**14.5 Ketidaksesuaian tipe.** `pegawai.id` bertipe `bigint` sedangkan `surat_tugas_petugas.pegawai_id` bertipe `integer`. Perlu dipastikan apakah ini disengaja.

**14.6 ~~Kolom mana yang ditulis n8n.~~ — TERJAWAB (16 Agu 2026).** n8n tidak menulis `pegawai`, `siswa`, maupun `agenda`; hanya membaca. Penulisan selama ini dilakukan manual lewat SQL editor Supabase. K3 sah, dan RS5 turun menjadi risiko rendah. Catatan: verifikasinya berdasar keterangan pemilik sistem, bukan pembacaan workflow — workflow n8n tetap tidak dapat diperiksa dari repo ini.

**14.7 Placeholder template yang tidak punya kolom.** `nama_sekolah`, `alamat_sekolah`, `email_sekolah`, `npsn`, `tahun_pelajaran`, `nama_kepala`, `nip_kepala`, `pangkat_kepala`, `kota_surat`, dan lainnya tidak ada di database — tampaknya konstanta di dalam n8n. Jika suatu saat Laravel mengambil alih pembuatan PDF, semuanya perlu menjadi tabel pengaturan.

**14.8 ~~Perubahan `template/` yang belum di-commit.~~ — SELESAI (16 Agu 2026).** Sudah di-commit sebagai `fc3d4ab "update logo"`. Path logo kini `logo-pemprov-kalsel.png` dan `logo-smkn1amuntai.png`, cocok dengan berkas di `template/`. **Belum diuji end-to-end:** perlu dipastikan n8n masih dapat me-resolve path baru ini saat membuat PDF — cek pada V-1 langkah 6.

**14.9 Retensi data.** Berapa lama riwayat surat disimpan? Belum ada kebijakan arsip.

**14.10 Rencana produksi.** Jika kelak di-deploy ke server, perlu ditentukan hosting, HTTPS, dan cara mengelola `.env`.

---

## 15. Risiko dan trade-off

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| ~~RS1~~ | ~~`pdo_pgsql` belum aktif~~ | — | **Selesai 16 Agu 2026**, terverifikasi lewat `php -m` |
| RS2 | Migration Laravel menyentuh tabel n8n | Kehilangan data produksi | Schema `app` terpisah (K4); `migrate --pretend` wajib ditinjau; `migrate:fresh` hanya di salinan |
| RS3 | Skema `public` berubah oleh n8n tanpa sepengetahuan Laravel | Halaman riwayat rusak mendadak | Konsekuensi K2 yang diterima. Feature test terhadap kolom yang dipakai akan menangkapnya |
| RS4 | Audit trail Laravel dan `approved_by` di Supabase berbeda isi | Kebingungan saat audit | Konsekuensi langsung K6/K7. Dokumentasikan bahwa `app.aktivitas` adalah rujukan resmi |
| RS5 | Dua sistem menulis data master | Data tertimpa | **Turun ke risiko rendah** setelah 14.6 terjawab. Sisa risikonya: SQL editor Supabase tetap bisa dipakai manual dan melewati validasi serta soft delete Laravel. Sepakati bahwa perubahan data master hanya lewat Laravel |
| RS6 | `siswa.tanggal_lahir` tetap `text` | Data kotor terus bertambah | Konsekuensi K8. Validasi lunak di form; perbaikan tipe ditunda |
| RS7 | Penomoran manual memungkinkan duplikat dan lompatan | Nomor surat kacau | E5: peringatan tanpa memblokir. Buku agenda tetap otoritas (K9) |
| RS8 | `{{ket}}` dan `{{no}}` tetap kosong di PDF | Cacat cetak berlanjut | Kolom disiapkan (§5.3) tetapi tidak aktif sampai n8n boleh diubah |
| RS9 | Dua antarmuka hidup berdampingan saat transisi | Staf bingung mana yang benar | Pensiunkan `index.html` segera setelah V-1 dan V-5 lulus; hapus sekalian `index copy.html` |
| RS10 | Latensi Supabase dari Laragon lokal | Halaman terasa lambat | Paginasi (E17), indeks pada kolom filter, hindari query N+1 |
| RS11 | Skala proyek melonjak dari 748 baris menjadi aplikasi penuh | Beban pemeliharaan naik tajam | Trade-off yang disadari. Feature test untuk alur kritis sebagai jaring pengaman |
| **RS12** | **Sequence tertinggal di belakang `max(id)`** pada `pegawai`, `siswa`, `agenda` | **Insert pertama dari Laravel gagal dengan `duplicate key value violates unique constraint`.** Terlihat seperti bug Laravel padahal masalah data | `pegawai.id` adalah identity `BY DEFAULT` (bukan `ALWAYS`), dan ketiga tabel selama ini diisi manual lewat SQL editor — insert dengan id eksplisit tidak menggerakkan sequence. **Wajib diperiksa di V-0 langkah 4 sebelum menulis baris pertama** |
| **RS13** | **Data produksi nyaris kosong** — hanya 3 surat, semuanya `selesai` | Halaman riwayat hampir tidak berisi apa-apa saat rilis; paginasi dan performa tidak teruji secara nyata; V-1 tidak dapat diverifikasi terhadap data historis | Nilai halaman riwayat tumbuh seiring waktu, bukan segera — pertimbangkan menaikkan prioritas CRUD data master di atasnya (§17). Untuk pengujian, siapkan seeder; jangan menyimpulkan performa dari data sekecil ini |
| **RS14** | **Perilaku penolakan belum pernah terjadi sekali pun** | Jalur "Tolak" di AC-2 dan tampilannya di AC-3 dibangun tanpa satu pun contoh nyata | K12 membuat kode tahan terhadap ketiga kemungkinan di §14.1. Tetap wajib diuji dengan satu penolakan sungguhan sebelum dinyatakan lulus |

---

## 16. Strategi pengujian

Pest/PHPUnit, alur kritis saja. Integrasi n8n **di-mock** dengan `Http::fake()` — pengujian tidak boleh memicu pembuatan surat sungguhan atau mengirim notifikasi ke nomor asli.

```
tests/Feature/Auth/LoginTest.php                login, gagal login, logout
tests/Feature/Auth/RoleTest.php                 matriks §6, termasuk POST langsung
tests/Feature/Antrean/PutuskanTest.php          setujui, tolak, nomor tak valid,
                                                nomor duplikat, n8n gagal, n8n 401
tests/Feature/Antrean/TampilanTest.php          partisi tiga seksi, status tak dikenal (E3)
tests/Feature/Riwayat/RiwayatTest.php           filter, paginasi, ketahanan NULL
tests/Feature/Master/{Siswa,Pegawai,Agenda}Test.php  CRUD, validasi, soft delete
tests/Feature/StatusKepsekTest.php              kedua role, kegagalan n8n
tests/Unit/N8nClientTest.php                    header, timeout, pemetaan exception
```

Database pengujian: Postgres lokal terpisah, **bukan** Supabase produksi. Ini menuntut migration yang dapat membangun tabel `public` dari nol untuk keperluan tes — sementara di produksi tabel tersebut sudah ada dan tidak boleh disentuh. Perbedaan ini harus disadari saat menulis migration.

---

## 17. Urutan pengerjaan yang disarankan

1. ~~`.gitignore`, `composer create-project`, koneksi ke Supabase, schema `app`~~ — **selesai.** Laravel 12.66.0, Postgres 17.6 lewat Session pooler `aws-0-ap-northeast-1.pooler.supabase.com:5432` (Direct connection tidak dipakai: hostnya IPv6-only dan tidak terjangkau dari jaringan sekolah). Migration bawaan Laravel mendarat di schema `app` (9 tabel), `public` tetap 10 tabel milik n8n — K4 terbukti berjalan. Sisa tindakan manual: mengarahkan vhost Laragon ke `public/`
2. Auth + role + halaman kelola pengguna (AC-1) — fondasi bagi semua yang lain
3. Model read-only surat + halaman riwayat (AC-3) — tidak menulis apa pun, risiko terendah. **Catatan RS13:** dengan hanya 3 surat di database, manfaatnya belum terasa hari ini dan baru tumbuh seiring waktu. Tetap dikerjakan lebih dulu karena membangun pola query dan tampilan yang dipakai ulang di langkah berikutnya — bukan karena nilainya paling besar
4. CRUD data master (AC-4) — tulisan pertama Laravel ke schema `public`
5. `N8nClient` + status kepsek (AC-5, AC-6) — integrasi paling sederhana sebagai uji coba
6. Antrean persetujuan (AC-2) — paling berisiko, dikerjakan terakhir setelah pola mapan
7. Verifikasi V-1 sampai V-5, lalu pensiunkan `index.html` dan `index copy.html`
8. Tulis ulang `CLAUDE.md`

Langkah 3 sengaja mendahului langkah 6: halaman riwayat tidak menyentuh alur yang sedang dipakai staf setiap hari, sehingga aman dijadikan tempat mematangkan pola.

**Urutan ini boleh ditukar.** Jika yang paling mendesak adalah menghentikan pengeditan lewat SQL editor, langkah 4 (CRUD data master) dapat dinaikkan ke atas langkah 3 — keduanya tidak saling bergantung. Yang tidak boleh berpindah: langkah 1 dan 2 tetap paling awal, langkah 6 tetap paling akhir.
