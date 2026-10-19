# Proyek PBKK — Kelompok 4

**Dynamic Application Security Testing (Menemukan Kerentanan Pada Aplikasi Web Secara Mandiri): Analisis Studi Kasus**

## Case Overview

Untuk memberikan pemahaman praktis dan teoritis tentang bagaimana menambah keamanan aplikasi web modern (dalam kasus ini web PT XXX) yang terus dikembangkan; serta pemahaman tentang teknik pengujian keamanan dinamis (Dynamic Application Security Testing — DAST) yang digunakan untuk menemukan kerentanan pada aplikasi saat sedang berjalan (runtime) secara mandiri.

## Hypothetical Case Study Details

PT XXX adalah salah satu perusahaan rintisan (startup) teknologi finansial (FinTech) terkemuka di Indonesia. XXX menyediakan layanan dompet digital, pembayaran tagihan, dan pinjaman online. Menjelang peluncuran pembaruan besar-besaran (versi 2.0) dari portal web utama mereka, manajemen memutuskan bahwa keamanan adalah prioritas mutlak mengingat meningkatnya serangan siber pada sektor finansial.

Portal web ini berinteraksi langsung dengan nasabah, mitra bank, dan administrator internal. XXX memutuskan untuk mengimplementasikan DAST dari pihak ketiga untuk menguji aplikasi secara mandiri dari sudut pandang peretas (black-box testing) tanpa harus melihat kode sumber.

Sebagai perusahaan penyedia layanan DAST pihak ketiga, kami mendekati PT XXX untuk menyelesaikan masalah tersebut. Fungsi dan tugas utama yang perlu dipertimbangkan:

1. Nasabah, mitra, dan admin harus dapat melakukan hal berikut melalui sistem web:
   - Nasabah dapat login, melihat saldo, dan melakukan transfer dana.
   - Nasabah dapat mengunggah dokumen identitas (KTP/NPWP) untuk verifikasi KYC.
   - Mitra bisnis (seperti merchant) dapat memeriksa riwayat transaksi dan menarik dana.
   - Admin internal mengelola data pengguna dan memonitor aktivitas mencurigakan.
   - *Karena dynamic, proses pengujian kita tidak mengganggu pekerjaan user.*
2. Aplikasi web ini di-host di infrastruktur cloud publik (AWS/GCP) dengan berbagai API pihak ketiga yang terintegrasi (seperti gateway SMS dan API Bank Indonesia).
3. Proses pengujian keamanan (DAST) harus dilakukan secara mandiri oleh tim keamanan internal perusahaan tanpa mengganggu ketersediaan layanan aplikasi web di lingkungan staging.
   - *Karena dynamic, proses pengujian kita tidak mengganggu layanan aplikasi web.*
4. Aplikasi web ini memiliki konektivitas langsung ke: Bank Indonesia (BI), Otoritas Jasa Keuangan (OJK), penyedia layanan cloud, dan gateway pembayaran pihak ketiga.
   - *Web terhubung dengan pihak luar; pengujian tidak boleh bocor ke pihak luar atau salah diidentifikasi sebagai ancaman.*
5. Infrastruktur keamanan dan proses pengujian harus mampu mengidentifikasi ancaman secara real-time dan membangun kepercayaan publik bahwa data finansial dikelola dengan standar keamanan tertinggi.
   - *Memberikan laporan keamanan dan identifikasi ancaman selama aplikasi berjalan, serta solusi kepada klien.*

## Task

1. Identifikasi potensi risiko/ancaman terhadap sistem web tersebut berdasarkan pengujian DAST.
2. Identifikasi persyaratan keamanan utama pada aplikasi/pengguna/infrastruktur untuk mendukung DAST.
3. Identifikasi semua fitur/komponen utama dari rencana pengujian keamanan untuk mengatasi masalah di atas.
4. Jelaskan bagaimana mengatasi poin nomor 5 fungsi dan tugas utama, dengan pendekatan teknis dan non-teknis untuk meningkatkan kepercayaan publik.

## 1) Potensi risiko/ancaman

- **Infrastruktur kedaluwarsa & miskonfigurasi** — versi library usang atau port terbuka ke publik tanpa perlindungan.
- **Celah logika bisnis** — manipulasi alur aplikasi (mis. parameter harga menjadi nol); tidak terdeteksi pemindai otomatis dan butuh keahlian manusia.
- **Kerentanan sesi pengguna (runtime)** — pencurian sesi lewat XSS atau manipulasi data lewat IDOR saat aplikasi berjalan.
- **Kelemahan kriptografi & kredensial bocor di kode** — API key, kata sandi database, atau hashing lemah di source code.

## 2) Persyaratan keamanan untuk mendukung DAST

- **Akses repositori kode (read-only)** untuk Code Review, dengan NDA ketat.
- **Lingkungan UAT/Staging** replika produksi agar pengujian tidak menyebabkan downtime bagi nasabah.
- **Akun pengguna berjenjang** (nasabah, mitra, admin) untuk menguji eskalasi hak akses.
- **Whitelisting WAF** untuk IP vendor selama Vulnerability Assessment.

## 3) Fitur/komponen utama — 4 pilar layanan

1. **Vulnerability Assessment (VA)** — pemindai otomatis untuk memetakan aset klien, versi perangkat lunak rentan (CVE), port terbuka, dan miskonfigurasi server. Memberi gambaran dasar postur keamanan infrastruktur.
2. **Penetration Testing (PT)** — tim ethical hacker tersertifikasi menindaklanjuti temuan VA, merangkai kerentanan kecil, dan menemukan celah logika bisnis yang tidak bisa ditemukan software otomatis.
3. **Runtime Testing** — pengujian aplikasi yang sedang berjalan dari perspektif pengguna akhir untuk mendeteksi kerentanan dinamis seperti XSS, SQLi, dan CSRF.
4. **Code Review** — kombinasi SAST dan inspeksi manual untuk menemukan kredensial hardcoded, manajemen memori buruk, dan cacat arsitektur di akar masalah.

## 4) Membangun kepercayaan publik

**Teknis**

- **Remediasi menyeluruh** — panduan perbaikan spesifik (hingga baris kode) dari temuan Code Review sampai Runtime.
- **Laporan audit & sertifikat** — setelah klien memperbaiki temuan dan lulus re-test, diterbitkan laporan pentest resmi.

**Non-teknis**

- **Validasi independen pihak ketiga** — laporan vendor independen menunjukkan kepada investor, mitra, dan nasabah bahwa klien serius menjaga keamanan data.
- **Seal of Trust** — "Sertifikat Lulus Uji Penetrasi" yang dapat ditampilkan klien di profil bisnis atau situsnya.

## Referensi

- Studi kasus: ResearchGate
- Gambaran proyek: [Samuel-James971/Dynamic-Application-Security-Testing-DAST-](https://github.com/Samuel-James971/Dynamic-Application-Security-Testing-DAST-) (satu aplikasi fintech sebagai target uji, satu aplikasi DAST)
- Contoh DAST: [zaproxy/zaproxy](https://github.com/zaproxy/zaproxy)
