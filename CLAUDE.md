# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 12 dashboard used by SMKN 1 Amuntai's Tata Administrasi Sekolah (TAS) staff to approve letter requests and maintain master data (students, staff, agenda). Blade + Tailwind 4 via Vite, PostgreSQL on Supabase, no JS framework.

Serve it from Laragon with the vhost pointing at `public/` (`http://surat.test`). The pre-Laravel dashboard still lives at `public/legacy/index.html` and is reachable at `/legacy/` — see "Legacy dashboard" below before touching it.

```
php artisan test          # 114 tests, must stay green
npm run build             # required after editing any Blade or CSS
php artisan migrate       # writes to Supabase — read "Migrations" first
```

**All identifiers and UI copy are Indonesian**: `muatDaftar`, `putuskan`, `kartuAntrean`, `PastikanRole`, `HanyaBaca`, `namaTampil()`, `daftarKelas()`. Laravel's own conventions stay English (`User`, `boot`, `casts`). Keep both.

The design decisions behind everything here are recorded in **`docs/specs/dashboard-laravel-surat.md`** as K1–K12 (decisions), E1–E18 (edge cases), and RS1–RS15 (risks). Comments in the code cite them by number. Read that file before changing behaviour; it explains *why*, which the code cannot.

## The core constraint: n8n owns the letters

n8n at `https://n8n.dewakreatif.com/webhook` holds all letter logic — approval, numbering, PDF generation, WhatsApp notification to the requester. Its workflow is **not in this repo** and, by decision K6, **is not modified**. Requests originate from a WhatsApp/Telegram chatbot, also n8n's.

Everything Laravel does is arranged around that:

| Concern | Owner |
| --- | --- |
| Letter status, numbering, PDFs, notifications | n8n |
| Reading letters for display | Laravel (Eloquent, read-only) |
| `pegawai`, `siswa`, `agenda` CRUD | **Laravel only** — n8n merely reads them (K3) |
| Who did what | `app.aktivitas` (K7) |

All five webhooks are wrapped by `App\Services\N8nClient`. Never call `Http::` against n8n from a controller (AC-6). Payload shapes copy the old dashboard exactly, including sending `''` rather than `null` for skipped fields — n8n is not changed, so the request must not change either.

Two response shapes were confirmed against the live service and are easy to get wrong: `/surat-pending` replies `{"items": [...]}`, and `/surat-pdf` sometimes wraps its reply in a single-element array. `N8nClient` normalises both.

`N8nUnauthorizedException` deliberately **does not extend** `N8nException`. Controllers catch `N8nException` to show an in-form failure; a 401 means the *server's* `N8N_SECRET` is wrong, not the user's doing, so it renders its own page and never logs anyone out (E2). This differs from `kunciUlang()` in the old dashboard, where the access code belonged to the user.

## Two schemas, one database

`DB_SEARCH_PATH=app,public` is load-bearing (K4). Postgres creates new tables in the first schema on the path, so:

- **`app`** — Laravel's own tables: `users`, `sessions`, `cache`, `jobs`, `migrations`, `aktivitas`
- **`public`** — n8n's tables: `surat_tugas`, `surat_keterangan`, `surat_tugas_petugas`, `pegawai`, `siswa`, `agenda`, `status_kepsek`, plus `counter` (dead), `log_permintaan` and `sesi` (chatbot telemetry, untouched)

Models reference tables unqualified; the search path resolves them. **Migrations must write `public.` explicitly** when altering n8n's tables, or they will silently target `app`.

Connect through Supabase's **Session pooler** (port 5432, username `postgres.<ref>`). The Direct connection host is IPv6-only and unreachable from the school network.

## Migrations

Only ever **add** columns to `public` (K8). Never change a type, never drop. Every added column must be nullable or defaulted so existing rows stay valid and n8n's queries are unaffected.

Beware `add column ... default`: Postgres backfills every existing row. That is why `agenda.created_at` has no default — a default would have made 32 existing events claim they were created at migration time.

`database/migrations/uji/` builds n8n's tables from scratch **for tests only**. `AppServiceProvider` loads that path solely when `APP_ENV=testing`, so `php artisan migrate` in production never sees the files. Keep it that way; a conditional inside a migration would still be read in production.

## Read-only models

`SuratTugas`, `SuratKeterangan`, and `StatusKepsek` use the `HanyaBaca` trait, which throws on every write event including `saving` — `save()` on an unchanged model skips `updating` entirely, so guarding only `creating`/`updating`/`deleting` leaves a hole. `$guarded` alone is not enough: it stops mass assignment but not `$surat->status = 'x'; $surat->save();`.

Tests insert rows for these tables through the query builder (`Tests\Dukungan\MembuatDataSurat`), which also mirrors reality — those rows really do come from outside Laravel.

`Siswa`, `Pegawai`, and `Agenda` are full CRUD with `SoftDeletes`. Relations pointing at them from letters use `withTrashed()`: a letter that has already been issued must not lose the name of its student or officer because master data changed afterwards (E7, E8).

`siswa.tanggal_lahir` is `text` holding free-form input from the chatbot. Never cast or validate it as a date (E12).

## Roles

Two roles, `tas` and `kepsek`; no self-registration, and teachers and students have no accounts at all. The matrix is in spec §6. Enforcement is the `role:` middleware on route groups — filtering the nav menu in the layout is cosmetic only, and every restriction has a test that fires the request directly.

At least one active `tas` account must always remain; `PenggunaController` blocks the change that would remove the last one, because only `tas` can create accounts.

## Working on the queue page

`resources/views/antrean/index.blade.php` is the highest-risk file. Two things there exist because of specific failures:

**Never disable form controls on submit.** The busy indicator originally disabled every button in the card, including the one being pressed; browsers omit disabled controls from the payload, so `aksi` vanished and *every* decision failed. It now marks the form and dims the card instead.

**Unknown `jenis`/`status` combinations must stay visible.** `render()` in the old dashboard kept exactly three pairs and dropped the rest silently, so one typo in n8n could lose a request with no trace. The "Status tidak dikenal" section catches the remainder, and a test compares the section totals against the count n8n returned.

Letter numbers are typed per card and validated server-side as digits only (E4). A duplicate produces a warning that can be overridden, never a block — the physical agenda book is the authority (E5). Staff type just the sequence number; n8n composes `800.1.11.1/67-SMKN.1-AT/2026` around it.

There is no 30-second auto-refresh. The old dashboard had one, but numbers are now typed inside the cards and a refresh mid-typing would erase them.

## Testing

`phpunit.xml` points at a **local** PostgreSQL 17 (`surat_uji`, port **5433**, role `surat_test`) — never Supabase. `RefreshDatabase` runs `migrate:fresh`, which drops every table on the search path; pointed at production it would delete the school's letters. The port differs from 5432 specifically so no test value can coincide with a production one.

`Tests\TestCase` calls `Http::preventStrayRequests()`. Keep it. It already caught three tests that were calling the live n8n, and an unfaked call from the queue tests would issue a real letter and notify a real requester.

## Localisation

`APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`. The fallback is deliberately *not* `id`: with both set to `id` and a missing key, Laravel prints the raw key — staff saw `validation.required` and `pagination.previous` in production. Add new strings to `lang/id/`; anything missing degrades to readable English.

## Templates (`template/`)

`surat_tugas_template.html` and `surat_keterangan_template.html` are print layouts rendered **server-side by n8n**, not by Laravel. Handlebars syntax (`{{field}}`, `{{#each petugas}}`), so a new placeholder renders blank unless the n8n workflow supplies it. `{{ket}}` and `{{no}}` currently have no data source; `surat_tugas_petugas.urutan` and `.ket` were added for them but n8n does not read those columns yet.

Laid out for A4 with Times New Roman and `pt` units. Do not apply the dashboard's CSS conventions and do not run Prettier over them.

## Legacy dashboard

`public/legacy/index.html` is the original single-file dashboard, still functional and still the fallback if the Laravel one fails. Retire it only when asked. Its line numbers are cited throughout the code comments and the spec, so keep the file intact rather than trimming it.

`public/legacy/index copy.html` is a stale snapshot from before surat keterangan existed. Nothing loads it. It is a deletion candidate, but has not been deleted without instruction.
