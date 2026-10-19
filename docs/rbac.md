# Desain RBAC (Spatie Laravel Permission)

Status: **backend sudah diimplementasi** (BE-1, `feat/be1-rbac`). Halaman per role dikerjakan FE — lihat [Cara pakai untuk FE / BE-2](#cara-pakai-untuk-fe--be-2).

> Jangan tertukar: *nasabah / mitra / admin* di [`rancangan.md`](rancangan.md) adalah user **aplikasi target PT XXX**. Mereka disimpan sebagai akun uji di `target_test_accounts.label_role`, **bukan** role Spatie. RBAC di dokumen ini mengatur siapa yang memakai **aplikasi DAST kita**.

## Role

| Role | Siapa | Cara akun dibuat |
|---|---|---|
| `admin` | Pengelola vendor DAST | **Hanya** `RolesAndAdminSeeder` (kredensial dari `.env`). Tidak ada UI untuk membuat/menaikkan admin. |
| `analyst` | Security analyst / ethical hacker vendor | Didaftarkan oleh admin (halaman kelola user). |
| `client` | Perwakilan klien (mis. PT XXX) | Register publik Breeze → otomatis `client`. |

## Alur target

```
client daftar target (pending) ──> admin verifikasi (verified / rejected) ──> analyst scan + chat agent
admin buat target sendiri (dummy demo / riset) ──> langsung verified
```

- Scan hanya boleh dijalankan ke target `verified` (rem etis: tidak ada pengujian ke sistem tanpa izin).
- `targets.user_id` = pemilik target (client, atau admin untuk target internal).
- Target `pending` / `rejected`: pemilik boleh edit & hapus. Target `verified`: **terkunci** untuk client (ubah `base_url`/scope = celah). Admin boleh mengembalikan ke `pending` atau menghapus target apa pun.

## Permission

Kode **hanya mengecek permission** (`can('scans.run')`, middleware `can:`), tidak pernah nama role. Ganti hak akses = ubah seeder, bukan kode.

| Permission | admin | analyst | client |
|---|:-:|:-:|:-:|
| `users.manage` — daftarkan analyst, hapus user (bukan diri sendiri) | ✓ | | |
| `targets.create` | ✓ (langsung verified) | | ✓ (pending) |
| `targets.verify` | ✓ | | |
| `targets.view-any` — lihat semua target | ✓ | ✓ | |
| `scans.run` | | ✓ | |
| `chat.use` — chat dengan agent AI | | ✓ | |
| `chat.view-any` — audit semua chat session (read-only) | ✓ | | |
| `findings.manage` — ubah status temuan | | ✓ | |
| `reports.view` | ✓ | ✓ | ✓ milik sendiri |
| `certificates.issue` | ✓ | | |
| `comments.create` | | ✓ | ✓ milik sendiri |

*Separation of duties:* admin memverifikasi izin tapi tidak ikut menjalankan scan/chat.

## Akses client (read-only + komentar)

| Data | client |
|---|---|
| Target, scope rules, akun uji miliknya | lihat (edit/hapus hanya saat belum verified) |
| Scan: status & progres | lihat (real-time, polling Livewire) |
| Findings | lihat, tidak bisa ubah |
| Reports & certificates | lihat & unduh |
| Komentar di finding / report miliknya | tulis & hapus komentar sendiri |
| Chat session & tool call | ✗ (catatan kerja internal vendor) |

## Kepemilikan → Policy

Spatie tidak tahu "milik sendiri". Aturan kepemilikan ada di **Policy** Laravel yang menggabungkan permission + pemilik. Semua turunan target dicek lewat target induknya:

```
Finding / Report / Certificate ──> Scan ──> Target ──> user_id === $user->id
Comment ──> commentable (Finding | Report) ──> ... ──> Target
```

Policy: `TargetPolicy`, `ScanPolicy`, `FindingPolicy`, `ReportPolicy`, `CertificatePolicy`, `CommentPolicy`, `ChatSessionPolicy`. Akses ke data milik orang lain → 403/404 (menutup IDOR).

## Perubahan skema: `comments` (polymorphic)

```
comments: id, user_id (FK cascade), commentable_type, commentable_id, isi (text), timestamps
```

- `morphs('commentable')`; `Finding` dan `Report` punya `comments()` (`morphMany`), `User` punya `comments()`.
- Tidak ada edit komentar (thread = catatan audit). Hapus hanya komentar sendiri.
- `commentable_id` tidak bisa diberi FK → komentar dibersihkan di level model saat `Finding` / `Report` dihapus, termasuk saat terhapus berantai dari `Target` / `Scan` (cascade database tidak memicu event model). Wajib ada test-nya.
- Tabel lain tidak berubah. **ERD (Mermaid di README + gambar Redgate untuk slide) perlu ditambah `comments`.**

## Seeder & admin di aplikasi desktop

- `RolesAndAdminSeeder` — role, permission, dan 1 admin. Idempotent (`firstOrCreate`), dibutuhkan di semua lingkungan.
- `DatabaseSeeder` — memanggil `RolesAndAdminSeeder`, lalu data dummy (development saja).
- Kredensial admin dari `.env`: `ADMIN_EMAIL`, `ADMIN_PASSWORD`. Kalau `ADMIN_PASSWORD` kosong, seeder gagal dengan pesan jelas (tidak ada password default di kode).
- `.exe` NativePHP: migration jalan otomatis, seeder **tidak** → `NativeAppServiceProvider::boot()` memanggil `RolesAndAdminSeeder` setiap app menyala. Isi `ADMIN_*` di `.env` sebelum `php artisan native:build`.

## Pembagian kerja

**BE-1 (@Pardofel1s)**
- [x] Migration + model + factory `Comment`
- [x] `RolesAndAdminSeeder`, `HasRoles` di `User`, `ADMIN_EMAIL` / `ADMIN_PASSWORD` di `.env.example`
- [x] Policy di atas (auto-discovery Laravel, tanpa registrasi manual) + `UserPolicy`
- [x] Action class: `CreateTarget` (admin → verified, client → pending), `VerifyTarget`, `RegisterAnalyst`
- [x] Route + middleware `can:`
- [x] Satu baris di area FE (minta review @shzirley): `assignRole('client')` di `livewire/pages/auth/register.blade.php`, panggil seeder di `NativeAppServiceProvider::boot()`
- [x] Test: tiap role diizinkan/ditolak, IDOR antar client, target terkunci setelah verified, register = client, pembersihan komentar

**FE (@shzirley)** — halaman Volt memanggil `$this->authorize(...)` + action class BE-1; menu disembunyikan dengan `@can`
- [ ] Target: daftar, form (client & admin), halaman verifikasi (admin)
- [ ] Kelola user: daftar user, form daftarkan analyst (admin)
- [ ] Detail scan / finding / report untuk client (read-only, polling progres) + thread komentar
- [ ] Navigasi per role (`@can`)

**BE-2 (@build-zaidana)**
- [ ] Agent loop & tool hanya jalan untuk user dengan `chat.use` / `scans.run`, dan hanya ke target `verified`

## Cara pakai untuk FE / BE-2

Akun dev setelah `php artisan migrate:fresh --seed` (password `password`): `analyst@example.com`, `client@example.com`. Admin dari `ADMIN_EMAIL` / `ADMIN_PASSWORD` di `.env`.

**Route baru** — controller biasa: `auth` + `can:` (contoh `chat-sessions.show` di `routes/web.php`). Halaman Volt full-page: `auth` di route, lalu `authorize()` di `mount()`:

```php
// routes/web.php
Volt::route('targets/{target}', 'targets.show')->middleware('auth')->name('targets.show');

// resources/views/livewire/targets/show.blade.php
public function mount(Target $target): void
{
    $this->authorize('view', $target);
    $this->target = $target;
}
```

**Di komponen Volt / Livewire:**

```php
// Daftar: jangan Target::all() — pakai scope yang sudah memfilter per user
Target::visibleTo(auth()->user())->latest()->get();

// Cek sebelum aksi (403 kalau ditolak)
$this->authorize('update', $target);               // edit target, scope rules, akun uji
$this->authorize('create', [Scan::class, $target]); // jalankan scan (analyst + target verified)
$this->authorize('create', [Comment::class, $finding]);

// Aksi yang punya aturan khusus: panggil action class (validasi + otorisasi sudah di dalam)
app(CreateTarget::class)->handle(auth()->user(), $this->only(['nama', 'base_url', 'lingkungan']));
app(VerifyTarget::class)->handle(auth()->user(), $target, Target::VERIFIED);
app(IssueCertificate::class)->handle(auth()->user(), $retestScan); // ValidationException['scan'] kalau syarat belum terpenuhi
app(RegisterAnalyst::class)->handle(auth()->user(), $this->only(['name', 'email', 'password', 'password_confirmation']));
```

**Di Blade:** `@can('targets.verify') ... @endcan`, `@can('view', $report) ... @endcan`.

- `status_verifikasi` **tidak fillable** — jangan set lewat `update()` / `create()`; hanya lewat `CreateTarget` / `VerifyTarget`.
- Komentar: `$finding->comments()->make(['isi' => $isi])->user()->associate(auth()->user())->save()` setelah `authorize('create', [Comment::class, $finding])`. Hapus: `authorize('delete', $comment)`.
- Kepemilikan dicek lewat query (`User::ownsTarget()`, `User::ownsScan()`), jadi aman dipakai di dalam loop meski `preventLazyLoading()` aktif.
- BE-2: sebelum agent menjalankan tool, cek `$user->can('create', [Scan::class, $target])` (analyst + target verified) di samping allowlist host.

## Sertifikat

`IssueCertificate` (admin, `certificates.issue`) hanya menerbitkan sertifikat kalau:

1. scan adalah **re-test** (`retest_of_id` terisi),
2. statusnya `completed` (`Scan::COMPLETED`),
3. **tidak ada finding `open`** di scan re-test itu (finding di scan awal tidak dihitung),
4. scan itu belum punya sertifikat (satu sertifikat per scan).

Nomor: `CERT-<tahun>-<id scan 5 digit>`, berlaku 1 tahun (`due_date`). Pemilik target bisa melihatnya lewat `CertificatePolicy`.
