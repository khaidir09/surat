# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-file, no-build static dashboard (`index.html`) used by SMKN 1 Amuntai's Tata Administrasi Sekolah (TAS) staff to approve/reject letter requests. There is no package.json, no bundler, no test suite — open the file in a browser or serve it from Laragon (`http://localhost/surat/` or `http://surat.test`). Changes are verified by loading the page, not by running commands.

All UI copy, function names, and variables are Indonesian (`muatDaftar`, `putuskan`, `tandaiSelesai`, `kartuTugas`). Keep that convention when adding code.

## Architecture

**Frontend ↔ backend split:** all state lives in n8n. `index.html` is a thin client over five webhooks defined at the top of the `<script>` block (`N8N_BASE = https://n8n.dewakreatif.com/webhook`):

| Endpoint           | Method | Purpose                                     |
| ------------------ | ------ | ------------------------------------------- |
| `/surat-pending`   | GET    | list of pending items                       |
| `/surat-keputusan` | POST   | `{id, jenis, aksi, nomor}` — approve/reject |
| `/surat-pdf?id=`   | GET    | returns `{url}` for the generated PDF       |
| `/surat-selesai`   | POST   | mark a signed letter as done                |
| `/status-kepsek`   | POST   | principal's presence status                 |

n8n owns the database, PDF generation, numbering, and notifications. Adding a feature usually means changing the n8n workflow too — that workflow is **not** in this repo, so state clearly when a change depends on it.

**Auth:** the access code typed at the gate is kept only in the in-memory `secret` variable (never localStorage) and sent as the `x-dashboard-secret` header on every request. The gate does not validate anything locally — a wrong code surfaces as a `401` from the first fetch, and every handler must check `res.status === 401` and call `kunciUlang(...)` to drop back to the gate. Any new endpoint call must repeat that pattern.

**Item model** drives the entire render. Each item from `/surat-pending` carries:

- `jenis`: `"surat_tugas"` | `"keterangan_aktif"`
- `status`: `"menunggu"` (needs approval) | `"menunggu_ttd"` (approved, waiting for the principal's signature)

`render()` partitions the flat array into three sections by that pair; `kartuTugas()` switches its action buttons on `menunggu_ttd` (Unduh PDF / Sudah ditandatangani) vs `menunggu` (Setujui / Tolak). Adding a letter type means: a new `jenis` value, a new `kartu*()` builder, a new `seksi()` call in `render()`, and a matching `.pill`/`.card` color modifier in the `:root` custom properties.

**Rendering is string concatenation into `innerHTML`.** Every interpolated field must go through `esc()`, and handlers are wired via inline `onclick` attributes, so any new card action must be a global function. Cards are disabled during a request by adding `.busy` to the closest `.card`; on failure the class is removed, on success `muatDaftar()` re-fetches. The list also auto-refreshes every `AUTO_REFRESH_MS` (30s).

**Letter numbers are entered manually** via `prompt()` in `putuskan()` and validated as digits-only before POSTing. This was reverted once (`5c8b481`) then reintroduced — treat manual entry as the intentional current design, not an oversight.

## Templates (`template/`)

`surat_tugas_template.html` and `surat_keterangan_template.html` are print layouts rendered server-side by n8n, not loaded by the dashboard. They use Handlebars syntax — `{{field}}` and `{{#each petugas}}` — so any new placeholder must be supplied by the n8n workflow or it renders blank.

They are laid out for A4 print with Times New Roman, `pt` units, and table-based kop surat; do not restyle them with the dashboard's CSS conventions or reformat them with Prettier (the dashboard file is Prettier-formatted, the templates are hand-formatted and compact).

## `index copy.html`

A stale snapshot from before surat keterangan and the principal-status panel were added. It is not loaded by anything and is not a fallback — do not edit it alongside `index.html` unless asked to refresh or delete it.
