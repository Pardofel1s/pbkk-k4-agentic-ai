# Desain RBAC (Spatie Laravel Permission)

Status: **disepakati, belum diimplementasi.** Implementasi backend oleh BE-1 di branch `feat/be1-rbac`; halaman oleh FE.

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
- [ ] Migration + model + factory `Comment`
- [ ] `RolesAndAdminSeeder`, `HasRoles` di `User`, `ADMIN_EMAIL` / `ADMIN_PASSWORD` di `.env.example`
- [ ] Policy di atas + registrasi
- [ ] Action class: `CreateTarget` (admin → verified, client → pending), `VerifyTarget`, `RegisterAnalyst`
- [ ] Route + middleware `can:`
- [ ] Satu baris di area FE (minta review @shzirley): `assignRole('client')` di `livewire/pages/auth/register.blade.php`, panggil seeder di `NativeAppServiceProvider::boot()`
- [ ] Test: tiap role diizinkan/ditolak, IDOR antar client, target terkunci setelah verified, register = client, pembersihan komentar

**FE (@shzirley)** — halaman Volt memanggil `$this->authorize(...)` + action class BE-1; menu disembunyikan dengan `@can`
- [ ] Target: daftar, form (client & admin), halaman verifikasi (admin)
- [ ] Kelola user: daftar user, form daftarkan analyst (admin)
- [ ] Detail scan / finding / report untuk client (read-only, polling progres) + thread komentar
- [ ] Navigasi per role (`@can`)

**BE-2 (@build-zaidana)**
- [ ] Agent loop & tool hanya jalan untuk user dengan `chat.use` / `scans.run`, dan hanya ke target `verified`

## Belum diputuskan (bukan RBAC)

- Syarat menerbitkan sertifikat (mis. re-test tanpa finding `open`).
