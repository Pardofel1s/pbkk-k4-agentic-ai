# Jejaksa — PBKK Kelompok 4

Final project PBKK (Pemrograman Berbasis Kerangka Kerja) TC ITS. Aplikasi asisten AI yang bisa memanggil *tools* secara otonom, dikemas sebagai aplikasi desktop dengan NativePHP.

Produk: **Jejaksa — Setiap celah, jelas jejaknya.** Fokus pengujian keamanan web/DAST, dengan identitas Midnight–Teal dan logo J Trace.

## Status frontend

Frontend tahap **F0 (demo interaktif)** tersedia dalam stack Laravel/Blade/Livewire yang sama:

- Landing publik dengan animasi cursor/edit berulang saat terlihat, transisi masuk/keluar, bottom bar ringkas, fallback statis, dan dukungan reduced motion. Tidak ada kontrol playback.
- Auth/profil Breeze dengan tema Jejaksa; workspace responsif dan navigasi keyboard.
- Tipografi Plus Jakarta Sans variable disertakan lokal melalui Vite; lisensi ada di `public/fonts/plus-jakarta-sans/OFL.txt`. Panduan visual khusus proyek tersedia di `.agents/skills/antislop/SKILL.md`.
- Target/scope/metadata akun uji, profil pengujian, wizard empat tahap, status scan, pembatalan, kegagalan/retry, serta review/retest temuan.
- Percakapan dengan rencana contoh yang harus disetujui, detail aktivitas tool, laporan HTML contoh dengan versi/snapshot, dan preview sertifikat yang belum dapat diterbitkan.
- Katalog tool membedakan aktif dan siap; halaman akses serta pengaturan menunjukkan batas integrasi.

Auth memakai database sebenarnya. Data domain demo disimpan dalam session, dipisahkan per pengguna, dan hilang ketika session diakhiri. Tidak ada scanner, proses shell, job pengujian, atau permintaan LLM yang dijalankan oleh demo. Password akun target tidak diminta atau disimpan. Model database, policy RBAC, action BE-1, client LLM, dan tool pertama dari `main` sudah tersedia. Workspace F0 masih memakai adapter session; koneksi UI ke data domain, izin per aksi, agent loop, PDF final, dan penerbitan sertifikat belum selesai.

Setelah setup dan build, buka `/` atau jalankan `php artisan serve`. Jalankan migration dan seeder role sesuai petunjuk setup sebelum daftar melalui `/register`; pendaftaran publik membuat akun `client`. Akun dummy development tersedia hanya setelah `DatabaseSeeder` dijalankan. API key LLM tidak diperlukan untuk frontend demo.

Titik implementasi: `resources/views/welcome.blade.php`, `resources/views/jejaksa/`, `resources/views/livewire/pages/workspace.blade.php`, dan adapter session `app/Livewire/DemoWorkspace.php`. Perilaku demo diuji pada `tests/Feature/JejaksaWorkspaceTest.php`.

Window NativePHP dikonfigurasi menjadi **Jejaksa**, 1440×900 (minimum 1024×768), masuk ke route workspace/login. Pengujian binary Windows dan pemulihan job nyata masih diperlukan. Route baru tetap memerlukan review BE-1 sesuai CODEOWNERS.

## Secure LLM Chat API Hub (Tugas Mandiri)

Backend API terproteksi yang menerima prompt teks, meneruskannya ke LLM (Senopati AI ITS atau Ollama), lalu menyimpan riwayatnya. Antarmuka Livewire dipasang di atasnya pada Pertemuan 12. Panduan lengkap, contoh `curl`/PowerShell, dan daftar kode status: [`docs/api-chat-ai.md`](docs/api-chat-ai.md).

| Endpoint | Auth | Fungsi |
|---|---|---|
| `POST /api/login` | tanpa token (maks. 5 percobaan/menit) | Tukar email + password dengan token Sanctum |
| `POST /api/chat-ai` | `auth:sanctum` | Kirim `prompt` (string, wajib, maks. 1000 karakter), terima jawaban LLM |
| `POST /api/logout` | `auth:sanctum` | Cabut token yang dipakai |

Respon sukses `POST /api/chat-ai`:

```json
{
  "status": "success",
  "data": {
    "session_id": 12,
    "model_used": "qwen3:14b",
    "prompt": "Jelaskan middleware!",
    "ai_response": "Middleware adalah...",
    "timestamp": "2026-11-15 09:30:00"
  }
}
```

`session_id` adalah id baris di tabel `chat_logs`. Prompt kosong, bukan string, atau lebih dari 1000 karakter ditolak `422`; tanpa token `401`; LLM tidak bisa dihubungi `502` (tidak ada log yang disimpan).

Alur request (terpisah dari agent loop di bagian Arsitektur):

```
POST /api/chat-ai ──> auth:sanctum ──> ChatAiRequest ──> ChatAiController ──> AskChatAi ──> LlmClient ──> LLM
                                       (validasi)                                │
                                                                                 ▼
                                                        chat_logs (SQLite) ──> ChatLogResource ──> JSON
```

Tabel `chat_logs`: `user_id`, `prompt`, `ai_response`, `model_used`, `prompt_tokens`, `completion_tokens`, `total_tokens`. Nama model dan jumlah token dicatat dari respon provider (`model`, `usage`), bukan dari config.

Titik implementasi: `routes/api.php`, `app/Http/Controllers/Api/`, `app/Http/Requests/Api/`, `app/Http/Resources/ChatLogResource.php`, `app/Actions/AskChatAi.php`, `app/Models/ChatLog.php`, dan migration `create_chat_logs_table`. Test: `tests/Feature/Api/` (login, 401, validasi, format respon, log, 502).

> Sanctum sudah tercatat di `composer.json`/`composer.lock`. Anggota lain cukup `composer install` (atau `composer run setup`) lalu `php artisan migrate`; **tidak perlu** menjalankan `php artisan install:api` lagi.

**Checklist tugas**

Sisi API & database (area BE-1)
- [x] Sanctum, `POST /api/login`, `POST /api/logout`
- [x] `POST /api/chat-ai` di dalam grup middleware `auth:sanctum`
- [x] `ChatAiRequest`: `prompt` required, string, max 1000
- [x] Tabel `chat_logs` (user, prompt, jawaban AI, model, token)
- [x] Respon dibungkus `ChatLogResource` (`status` + `data`)
- [x] Feature test untuk jalur API
- [ ] Uji ujung ke ujung dengan LLM sungguhan (Senopati / Ollama)

Sisi integrasi LLM (area BE-2)
- [ ] Challenge 3: fallback otomatis ke Senopati AI ITS bila Ollama lokal mati
- [ ] Challenge 2: system prompt bertema DAST
- [ ] Challenge 1: response streaming (Server-Sent Events)

**Bersama:** demo pitching 5 menit.

## Tema: Dynamic Application Security Testing (DAST)

Kami berperan sebagai vendor DAST pihak ketiga untuk portal web fintech hipotetis **PT XXX** (studi kasus lengkap: [`docs/rancangan.md`](docs/rancangan.md)). Layanan dibagi menjadi 4 pilar:

1. **Vulnerability Assessment (VA)** — pemindaian otomatis untuk memetakan aset, versi perangkat lunak rentan (CVE), port terbuka, dan miskonfigurasi.
2. **Penetration Testing (PT)** — analisis lanjutan atas temuan VA, termasuk celah logika bisnis.
3. **Runtime Testing** — pengujian aplikasi yang sedang berjalan dari sisi pengguna akhir (XSS, SQLi, CSRF, dll.).
4. **Code Review** — SAST + tinjauan manual untuk kredensial hardcoded dan cacat arsitektur.

Hasilnya berupa temuan (`findings`), laporan (`reports`), dan sertifikat setelah re-test lulus (`certificates`) — lihat [ERD](#erd).

> **Batasan etis:** tool hanya boleh diarahkan ke target yang kita miliki atau punya izin tertulis (allowlist `LLM_TOOL_ALLOWED_HOSTS` + `target_scope_rules`). Untuk demo pakai aplikasi target dummy di lingkungan lokal, bukan sistem orang lain.

## Stack

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP **8.4**) |
| Frontend | Blade + Livewire 3 (Volt, class API) |
| Auth | Laravel Breeze (stack Livewire) |
| Authorization | Spatie Laravel Permission (RBAC) |
| API auth | Laravel Sanctum (personal access token) |
| Database | SQLite + Eloquent |
| LLM | Senopati AI ITS (default) / Ollama (cadangan) — endpoint OpenAI-compatible |
| Tool execution | Action class (`App\Tools\Tool`) + Symfony Process |
| Background job | Laravel Queue (driver `database`) |
| Desktop | NativePHP Desktop (Electron) |
| Testing | Pest + `Http::fake()` |
| Code style | Pint, preset **PSR-12** |

## Tim & area

| Peran | Orang | Area (lihat `.github/CODEOWNERS`) |
|---|---|---|
| FE — Livewire UI & packaging | [@shzirley](https://github.com/shzirley) | `resources/`, `app/Livewire/`, `config/nativephp.php`, `NativeAppServiceProvider` |
| BE-1 — Data, Auth, RBAC, HTTP | [@Pardofel1s](https://github.com/Pardofel1s) | `database/`, `app/Models/`, `app/Http/`, `routes/` |
| BE-2 — LLM, Tools, Queue | [@build-zaidana](https://github.com/build-zaidana) | `app/Services/`, `app/Tools/`, `app/Jobs/`, `config/llm.php` |

`tests/`, `.github/`, dan README milik bersama. Tiap orang menulis test untuk area sendiri.

## Setup (Windows)

1. **PHP 8.4 + Composer.** Paling gampang pakai [Laravel Herd](https://herd.laravel.com/windows). Kalau pakai XAMPP, pastikan PHP-nya 8.4 dan aktifkan `extension=zip` di `php.ini` (wajib untuk NativePHP).
2. **Node.js 22+** dan **git**.
3. Clone & install:

   ```bash
   git clone https://github.com/Pardofel1s/pbkk-k4-agentic-ai.git
   cd pbkk-k4-agentic-ai
   composer run setup
   ```

   `setup` meng-copy `.env`, generate key, migrate SQLite, dan build asset. Lalu isi `ADMIN_PASSWORD` di `.env` dan jalankan `php artisan db:seed` untuk role, admin, dan data dummy (login dev: `analyst@example.com` / `client@example.com`, password `password`).
4. Untuk integrasi LLM, isi `LLM_API_KEY` di `.env` — **tanya langsung ke Kamal ([@Pardofel1s](https://github.com/Pardofel1s))** lewat chat pribadi. Jangan di-commit dan jangan ditempel di grup/issue/PR. Mau pakai Ollama lokal? Ganti `LLM_BASE_URL=http://localhost:11434/v1` dan kosongkan key. Frontend demo tidak memerlukan API key LLM.
5. Jalankan:

   | Perintah | Untuk |
   |---|---|
   | `composer run dev` | Web di browser (server + queue + vite) |
   | `composer native:dev` | Jendela desktop NativePHP |
   | `composer test` | Semua test |
   | `vendor/bin/pint` | Rapikan kode ke PSR-12 |

Mencoba API chat (login, token, `POST /api/chat-ai`): lihat [`docs/api-chat-ai.md`](docs/api-chat-ai.md). Setelah `php artisan db:seed`, akun dev `analyst@example.com` / `password` bisa dipakai untuk login lewat `POST /api/login`.

> Path folder yang mengandung apostrof (mis. `Adinata's`) **dan** folder berawalan angka (mis. `5th Semester`) bikin Pest error `InvalidTestClassName`. Clone ke path biasa saja.

## Alur kerja Git

- **Tidak boleh commit langsung ke `main`** (diproteksi). Semua lewat Pull Request.
- Nama branch: `<tipe>/<area>-<deskripsi>`, contoh `feat/be1-chat-models`, `feat/fe-chat-ui`, `fix/be2-tool-injection`.
- Commit pakai [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `test:`, `docs:`, `chore:`.
- PR wajib **CI hijau** (`tests`: Pint + Pest) dan **semua checklist "Cek sebelum merge" dicentang** (`checklist`). Approval tidak wajib — boleh merge PR sendiri, tapi minta review kalau menyentuh area orang lain. Merge pakai **squash**, jadi `git log` di `main` = daftar kontribusi per orang (dinilai di UAS).

```bash
git switch main && git pull
git switch -c feat/be1-chat-models
# ...kerja, commit...
git push -u origin feat/be1-chat-models
gh pr create --fill
```

## Arsitektur

Modular monolith: Laravel MVC + service layer + agent tool-calling. Diagram berikut adalah arah integrasi backend; F0 memakai adapter demo tanpa menjalankan agent loop.

```
Livewire (chat UI) ──> Agent loop (BE-2) ──> LLM /chat/completions (Senopati/Ollama)
                              │  tool_calls
                              ▼
                       ToolRegistry ──> Tool (Action class) ──> Symfony Process
                              │                       (dijalankan via Queue Job)
                              ▼
                 ChatSession / ChatMessage / ToolExecutionLog (SQLite)
```

Kontrak yang sudah disediakan:

- `config/llm.php` — `base_url`, `model`, `api_key`, `timeout`, dan daftar `tools`.
- `App\Tools\Tool` — interface setiap tool: `name()`, `description()`, `parameters()` (JSON Schema), `handle()`.
- `App\Tools\ToolRegistry` — singleton dari `config('llm.tools')`; `schemas()` menghasilkan field `tools` untuk request LLM, `get($name)` menolak tool yang tidak terdaftar.

## Tugas Week 9 (kumpul link repo di LMS H-1 Pertemuan 10)

**BE-1 — @Pardofel1s**
- [x] Migration semua tabel (inti + DAST) dengan foreign key + `cascadeOnDelete()`
- [x] Model + relasi: User `hasMany` ChatSession → `hasMany` ChatMessage → `hasMany` ToolCall (dan `belongsTo` baliknya)
- [x] Factory + seeder data dummy relasional
- [x] Query riwayat chat dengan nested eager loading
- [ ] Screenshot Debugbar bebas N+1 (untuk slide)
- [x] (A+) `ToolCall` polymorphic: `nullableMorphs('loggable')`
- [x] ERD di bagian bawah README

**FE — @shzirley**
- [x] Halaman daftar chat session + halaman chat (Volt), memakai data demo session
- [ ] Coba `composer native:dev` jalan di laptop sendiri; set judul & ukuran window di `NativeAppServiceProvider`
- [x] Test Livewire untuk halaman chat dan persetujuan rencana demo

**BE-2 — @build-zaidana**
- [x] Service client LLM (`app/Services/Llm`) memanggil `{LLM_BASE_URL}/chat/completions` dengan `tools` dari `ToolRegistry`
- [x] Test client LLM pakai `Http::fake()` (respons biasa + respons `tool_calls`)
- [x] Tool pertama sesuai tema: `CheckSecurityHeadersTool` (dengan allowlist host)

**Bersama:** slide pitch maks. 5 halaman / 5 menit.

## Tugas Week 11 (kumpul link repo di LMS H-1 Pertemuan 12)

**BE — @artificialangel**
- [x] Tool DAST lanjutan & eksekusi aksi tervalidasi di ToolRegistry (validasi input, allowlist target, dan penanganan error)
- [x] Alur persetujuan pengguna (Human-in-the-Loop) untuk aksi berisiko tinggi pada ChatSession / ToolCall
- [x] Logging polymorphic ToolCall (`loggable`) ke tabel hasil pemindaian / temuan DAST
- [x] Integrasi end-to-end loop agentic (`LlmService` → `ToolCall` → respons final ke `ChatMessage`)
- [ ] Feature & unit test alur agentic loop, eksekusi tool, dan validasi keamanan

**FE — @shfawr**
- [ ] Integrasi UI chat Livewire Volt dengan backend agentic loop (menggantikan data demo session dengan data asli)
- [ ] Tampilan status eksekusi tool, card persetujuan (approve/reject), dan riwayat `ToolCall` di antarmuka chat
- [ ] Visualisasi ringkasan hasil audit & temuan keamanan pada halaman detail sesi
- [ ] Pengujian aplikasi desktop lewat `composer native:dev` serta penyesuaian konfigurasi window di `NativeAppServiceProvider`
- [ ] Test Livewire untuk pengiriman pesan, interaksi persetujuan tool, dan pembaruan state chat

Bersama: pengujian end-to-end aplikasi & pembaruan dokumentasi pada `README.md`.

## Menuju UAS (Week 16)

| Target | Pemilik |
|---|---|
| Chat Livewire berfungsi, loading state / polling | FE |
| `php artisan native:build win` → `.exe` jalan di laptop lain | FE |
| RBAC Spatie sesuai [`docs/rbac.md`](docs/rbac.md) (seeder, Policy, action class), tabel `comments`, CRUD lengkap, API Sanctum (login token + `POST /api/chat-ai` sudah ada, lihat [`docs/api-chat-ai.md`](docs/api-chat-ai.md)) | BE-1 |
| Halaman per role sesuai [`docs/rbac.md`](docs/rbac.md): target, verifikasi, kelola user, detail scan/finding/report + komentar | FE |
| Agent loop otonom, tools sesuai tema, mitigasi command injection, eksekusi via Queue | BE-2 |
| Minimal 5 skenario test lulus | Semua |

Rubrik UAS: demo **wajib** dari binary NativePHP (bukan browser), prompt → function calling → hasil tool. Siapkan cadangan Ollama `qwen3:14b` + video demo.

## Pertanyaan terbuka

- Aplikasi target dummy untuk demo (mis. app fintech sederhana yang kita buat sendiri, dijalankan lokal).

## RBAC

Role `admin` (seed saja), `analyst` (didaftarkan admin), `client` (register publik). Alur: client daftar target → admin verifikasi → analyst scan. Kode mengecek **permission**, kepemilikan dicek **Policy**. Detail lengkap, matriks permission, dan pembagian tugas: [`docs/rbac.md`](docs/rbac.md).

## AI coding assistant

Repo ini sudah berisi guideline & skill resmi Laravel ([Laravel Boost](https://laravel.com/docs/boost)) untuk **Claude Code** (`CLAUDE.md`, `.claude/skills/`) dan **Codex** (`AGENTS.md`, `.agents/skills/`, `.codex/config.toml`), plus MCP server `laravel-boost`.

- Aturan tim untuk agent ada di `.ai/guidelines/team.md`. Setelah mengubahnya atau update package, jalankan `php artisan boost:update`.
- Jangan edit `CLAUDE.md` / `AGENTS.md` langsung — file itu digenerate.

## ERD

Model inti Week 9: `User` → `ChatSession` → `ChatMessage` → `ToolCall` (= *ToolExecutionLog* di slide). Sisanya domain DAST. `chat_logs` adalah log endpoint `POST /api/chat-ai` (tugas mandiri) dan berdiri sendiri, terpisah dari `chat_sessions`.

Versi gambar untuk slide: [`docs/erd.png`](docs/erd.png) / [`docs/erd.svg`](docs/erd.svg) — dirender dari diagram Mermaid di bawah. Kalau skema berubah, ubah Mermaid-nya lalu render ulang: `npx @mermaid-js/mermaid-cli -i erd.mmd -o docs/erd.png -b white -s 3`.

Akses (role, permission, policy) dijelaskan di [`docs/rbac.md`](docs/rbac.md); tabel Spatie (`roles`, `permissions`, `model_has_roles`, …) tidak digambar.

```mermaid
erDiagram
    users ||--o{ chat_sessions : has
    users ||--o{ chat_logs : has
    users ||--o{ targets : owns
    chat_sessions ||--o{ chat_messages : has
    chat_messages ||--o{ tool_calls : triggers
    tools ||--o{ tool_calls : "executed as"
    scans |o--o{ tool_calls : "runs in"
    targets ||--o{ target_scope_rules : has
    targets ||--o{ target_test_accounts : has
    targets ||--o{ scans : has
    target_test_accounts |o--o{ scans : "logs in as"
    scan_profiles ||--o{ scans : configures
    scans |o--o{ scans : "retest of"
    scans ||--o{ findings : finds
    vulnerability_types ||--o{ findings : classifies
    scans ||--o{ reports : produces
    scans ||--o| certificates : earns
    users ||--o{ comments : writes
    findings ||--o{ comments : commentable
    reports ||--o{ comments : commentable
    targets |o--o{ tool_calls : loggable
    findings |o--o{ tool_calls : loggable

    users {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
    }
    chat_sessions {
        bigint id PK
        bigint user_id FK
        string judul "nullable"
        string model_llm "nullable"
    }
    chat_logs {
        bigint id PK
        bigint user_id FK
        text prompt
        longtext ai_response
        string model_used
        int prompt_tokens "nullable"
        int completion_tokens "nullable"
        int total_tokens "nullable"
    }
    chat_messages {
        bigint id PK
        bigint chat_session_id FK
        string role
        text isi
    }
    tool_calls {
        bigint id PK
        bigint chat_message_id FK
        bigint scan_id FK "nullable"
        bigint tool_id FK
        string loggable_type "nullable, polymorphic"
        bigint loggable_id "nullable, polymorphic"
        json parameter
        json hasil "nullable"
        string status
        longtext error_msg "nullable"
    }
    tools {
        bigint id PK
        string nama UK "= Tool::name()"
        text deskripsi
        boolean status
    }
    targets {
        bigint id PK
        bigint user_id FK
        string nama
        string base_url
        string lingkungan
        string status_verifikasi
    }
    target_scope_rules {
        bigint id PK
        bigint target_id FK
        string pola_domain
        string tipe
    }
    target_test_accounts {
        bigint id PK
        bigint target_id FK
        string label_role
        string username
        text encrypted_password "encrypted cast"
    }
    scan_profiles {
        bigint id PK
        string nama
        string tipe
        longtext deskripsi "nullable"
        json pengaturan "nullable"
    }
    scans {
        bigint id PK
        bigint target_id FK
        bigint target_test_account_id FK "nullable"
        bigint scan_profile_id FK
        bigint retest_of_id FK "nullable, self"
        string status
        tinyint progress
        timestamp started_at "nullable"
        timestamp finished_at "nullable"
    }
    vulnerability_types {
        bigint id PK
        string nama "nullable"
        string kode_cwe
        string kategori_owasp
        longtext deskripsi
        longtext rekomendasi
    }
    findings {
        bigint id PK
        bigint scan_id FK
        bigint vulnerability_type_id FK
        string severity
        string url_terdampak
        string parameter "nullable"
        longtext proof "nullable"
        longtext rekomendasi "nullable"
        string status
    }
    reports {
        bigint id PK
        bigint scan_id FK
        int versi
        string format
        string file_path
        timestamp generated_at
    }
    comments {
        bigint id PK
        bigint user_id FK
        string commentable_type "polymorphic"
        bigint commentable_id "polymorphic"
        text isi
    }
    certificates {
        bigint id PK
        bigint scan_id FK, UK
        string nomor UK
        timestamp issued_at
        date due_date "nullable"
    }
```

Semua tabel juga punya `created_at`/`updated_at`.

- **Polymorphic (A+):** `tool_calls.loggable` = subjek yang dikenai tool, saat ini `Target` atau `Finding` (`morphMany('loggable')`). `comments.commentable` = `Finding` atau `Report`; karena tidak bisa diberi FK, komentar yatim dibersihkan `Comment::pruneOrphans()` setiap kali user/target/scan/finding/report dihapus.
- **onDelete:** relasi kepemilikan `cascade` (user → chat/target → scan → finding/report/certificate, message → tool call). Tabel lookup (`tools`, `scan_profiles`, `vulnerability_types`) `restrict`. `scan_id`, `target_test_account_id`, `retest_of_id` → `set null`.
- **Bebas N+1:** `ChatSessionController@show` memuat `chatMessages.toolCalls.tool` + `chatMessages.toolCalls.loggable` sekaligus; `Model::preventLazyLoading()` aktif di luar production, dan `tests/Feature/Database/SchemaTest.php` memastikan jumlah query konstan. Screenshot Debugbar: `php artisan migrate:fresh --seed`, login `analyst@example.com` / `password`, buka `/chat-sessions/1`.
