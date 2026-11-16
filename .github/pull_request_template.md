## Apa yang berubah

<!-- 1-3 kalimat. Tautkan issue kalau ada: Closes #12 -->

## Area

- [ ] FE (Livewire / NativePHP)
- [ ] BE-1 (Database / Model / Auth / RBAC)
- [ ] BE-2 (LLM / Tools / Queue)

## Cek sebelum merge

<!-- Tidak lagi dipaksa CI (workflow "PR checklist" dinonaktifkan); CI otomatis yang mengecek test, pint, build dan secret. Tetap centang dengan jujur. -->

- [ ] `composer test` lulus di lokal
- [ ] `vendor/bin/pint` sudah dijalankan (PSR-12)
- [ ] Tidak ada API key / `.env` yang ikut ter-commit
- [ ] Screenshot (perubahan UI) / bukti Debugbar (query) sudah dilampirkan, atau tidak relevan
- [ ] Diff sudah aku baca ulang sendiri sebelum merge
