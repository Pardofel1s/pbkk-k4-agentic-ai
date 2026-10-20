@component('layouts.public')
    <div class="landing-container">
        <header class="landing-nav">
            <a href="{{ route('home') }}" aria-label="Jejaksa beranda">
                <img class="brand-lockup" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="142" height="50">
            </a>
            <nav class="landing-nav-links" aria-label="Navigasi utama">
                <a href="#fitur">Fitur</a>
                <a href="#alur">Alur kerja</a>
                <a href="#preview">Preview</a>
                <a href="#faq">FAQ</a>
            </nav>
            <a class="btn-secondary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                {{ auth()->check() ? 'Buka workspace' : 'Masuk' }}
                <x-jejaksa.icon name="arrow" size="15" />
            </a>
        </header>

        <main id="main-content">
            <section class="landing-hero" aria-labelledby="hero-title">
                <h1 class="hero-title" id="hero-title">
                    <span class="hero-line">Setiap celah,</span>
                    <span class="hero-line accent">jelas jejaknya.</span>
                </h1>
                <p class="hero-description">Atur target dan batas pengujian. Ikuti prosesnya, tinjau bukti, lalu catat langkah perbaikannya.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                        {{ auth()->check() ? 'Buka workspace' : 'Masuk ke Jejaksa' }}
                        <x-jejaksa.icon name="arrow" size="17" />
                    </a>
                    <a class="btn-secondary" href="#alur">Lihat alur kerja</a>
                </div>
                <p class="hero-note">Untuk web milik Anda atau target yang diizinkan untuk diuji.</p>

                <div id="preview" class="hero-preview" x-data="heroDemo" aria-label="Preview alur pengujian Jejaksa">
                    <div class="preview-chrome">
                        <span class="window-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="mono">jejaksa / workspace / asisten</span>
                        <span class="flex items-center gap-2"><span class="live-dot"></span>Demo animasi — data simulasi</span>
                    </div>
                    <div class="preview-body" aria-hidden="true">
                        <div class="preview-sidebar">
                            <img src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="" width="100" height="35" class="mb-6 ml-2">
                            <div><x-jejaksa.icon name="grid" size="14" />Ringkasan</div>
                            <div><x-jejaksa.icon name="target" size="14" />Target</div>
                            <div><x-jejaksa.icon name="scan" size="14" />Pemindaian</div>
                            <div class="selected"><x-jejaksa.icon name="sparkles" size="14" />Asisten AI</div>
                            <div><x-jejaksa.icon name="file" size="14" />Laporan</div>
                        </div>
                        <div class="preview-content">
                            <div class="preview-scene" :class="{ 'scene-enter': stage === 0, 'scene-out': stage === 6 }">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="preview-label">Sesi / Pemeriksaan header</p>
                                        <p class="preview-heading">Mulai dengan sebuah pertanyaan.</p>
                                    </div>
                                    <span class="preview-scope badge badge-success text-[9px]">Scope sesuai</span>
                                </div>
                                <div class="preview-command">
                                    <x-jejaksa.icon name="sparkles" size="16" class="shrink-0 text-teal-300" />
                                    <span class="preview-command-copy">
                                        <span x-show="stage < 2" x-cloak x-text="command"></span>
                                        <span x-show="stage >= 2">Periksa header <span :class="{ 'selected-text': stage === 2 && elapsed < 4400 }" x-text="stage === 2 && elapsed < 4400 ? 'target demo' : 'staging.example.test'">staging.example.test</span></span>
                                    </span>
                                    <span x-show="stage < 3" x-cloak class="text-teal-300">|</span>
                                </div>
                                <p class="mt-3 text-[10px] muted">Target <span class="ml-2 rounded border border-slate-600 px-2 py-1 text-teal-200">staging.example.test</span></p>
                                <div class="preview-plan">
                                    <p class="muted" x-text="stage < 3 ? 'Tinjau rencana sebelum pengujian.' : stage < 5 ? 'Cek scope → Periksa header → Catat bukti' : 'Rencana selesai · 1 bukti simulasi'">Rencana selesai · 1 bukti simulasi</p>
                                    <span class="preview-plan-action" x-text="stage < 3 ? 'Tinjau rencana' : stage < 4 ? 'Jalankan demo' : stage < 5 ? 'Memeriksa…' : 'Selesai'">Selesai</span>
                                </div>
                                <div class="preview-result-slot">
                                    <div class="preview-result" x-show="stage >= 4 && stage < 6"
                                        x-transition:enter="motion-pop-enter" x-transition:enter-start="motion-pop-start" x-transition:enter-end="motion-pop-end"
                                        x-transition:leave="motion-pop-leave" x-transition:leave-start="motion-pop-end" x-transition:leave-end="motion-pop-exit">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="preview-result-title">Content-Security-Policy belum diatur</span>
                                            <span class="badge badge-warning text-[9px]">Sedang</span>
                                        </div>
                                        <p class="muted">Bukti simulasi · Respons HTTP 200 tanpa header CSP.</p>
                                        <p class="text-teal-200">Terapkan kebijakan CSP sesuai kebutuhan aplikasi.</p>
                                    </div>
                                </div>
                            </div>
                            <svg x-cloak x-show="playing && stage > 0 && stage < 5" class="preview-cursor" :class="{ editing: stage === 2, running: stage >= 3 }" viewBox="0 0 24 28">
                                <path d="M2 1l19 14-10 1-4 9z" fill="currentColor" stroke="#042f2e" />
                            </svg>
                        </div>
                    </div>
                    <div class="preview-caption">
                        <p>Ilustrasi alur produk. Tidak menjalankan scan nyata.</p>
                        <div class="preview-stages" aria-hidden="true">
                            <span :class="{ current: stage < 3 }">01 / Instruksi</span>
                            <span :class="{ current: stage >= 3 && stage < 5 }">02 / Pemeriksaan</span>
                            <span :class="{ current: stage >= 5 }">03 / Bukti</span>
                        </div>
                    </div>
                    <p class="sr-only">Demo memperlihatkan penulisan instruksi, penggantian target, peninjauan rencana, pemeriksaan header, dan bukti CSP simulasi.</p>
                </div>
            </section>

            <section id="fitur" class="landing-section">
                <div class="section-intro" data-reveal="rise">
                    <div>
                        <p class="section-label">Konteks pengujian</p>
                        <h2 class="section-heading">Targetnya jelas.<br>Hasilnya bisa ditelusuri.</h2>
                    </div>
                    <p class="section-copy">Simpan batas, aktivitas, dan bukti dalam konteks yang sama. Jadi Anda tahu apa yang diuji dan apa yang perlu diperbaiki.</p>
                </div>
                <div class="feature-columns" data-stagger>
                    @foreach([
                        ['target', 'Scope', 'Tetapkan batasnya', 'Pilih target, path yang boleh diuji, dan bagian yang harus dikecualikan sebelum pengujian dimulai.', 'staging.example.test / scope'],
                        ['scan', 'Aktivitas', 'Ikuti setiap langkah', 'Lihat rencana, tool yang digunakan, dan status pengujian tanpa berpindah dari workspace.', 'Rencana → tool → hasil'],
                        ['shield', 'Bukti', 'Tinjau sebelum menutup', 'Baca lokasi dan bukti temuan. Catat keputusan review, perbaikan, dan kebutuhan retest.', 'Temuan → review → tindak lanjut'],
                    ] as [$icon, $category, $title, $copy, $detail])
                        <article class="feature-column" data-reveal="rise">
                            <div class="feature-column-top"><span>0{{ $loop->iteration }} / {{ $category }}</span><x-jejaksa.icon :name="$icon" size="18" /></div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $copy }}</p>
                            <span class="feature-detail mono">{{ $detail }}</span>
                        </article>
                    @endforeach
                </div>
            </section>

            <section id="alur" class="landing-section">
                <div data-reveal="side">
                    <p class="section-label">Alur kerja</p>
                    <h2 class="section-heading">Dari target pertama<br>sampai laporan.</h2>
                </div>
                <div class="workflow-grid" data-stagger>
                    @foreach([['Pilih target', 'Daftarkan web dan izin pengujiannya.'], ['Batasi scope', 'Tentukan path, pengecualian, dan akun uji.'], ['Tinjau rencana', 'Periksa profil sebelum pengujian dimulai.'], ['Baca temuan', 'Validasi bukti dan catat perbaikannya.'], ['Susun laporan', 'Bagikan hasil beserta batas pengujiannya.']] as [$title, $copy])
                        <article data-reveal="rise">
                            <span class="mono">0{{ $loop->iteration }}</span>
                            <h3>{{ $title }}</h3>
                            <p>{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="landing-section">
                <div class="editorial-grid">
                    <div data-reveal="side">
                        <p class="section-label">Meninjau hasil</p>
                        <h2 class="section-heading">Lihat buktinya.<br>Catat keputusan Anda.</h2>
                        <p class="section-copy">Sebuah temuan tetap terhubung dengan target, scan, dan aktivitas tool asalnya. Buka detailnya saat Anda perlu memeriksa kembali konteks.</p>
                        <a href="{{ auth()->check() ? route('findings.index') : route('login') }}" class="text-link mt-6 inline-flex items-center gap-2">Lihat workspace<x-jejaksa.icon name="arrow" size="16" /></a>
                    </div>
                    <div class="evidence-card" data-reveal="pop">
                        <div class="flex items-center justify-between gap-3"><span class="mono text-[11px] muted">FND-001 · Contoh</span><x-jejaksa.badge value="medium" /></div>
                        <h3 class="mt-6">Header CSP belum diatur</h3>
                        <p class="mt-2 text-xs muted">staging.example.test / · HTTP headers</p>
                        <div class="mt-6 rounded-lg border border-slate-700 bg-slate-950/40 p-4">
                            <p class="mb-3 text-[11px] muted">Bukti simulasi</p>
                            <pre class="text-teal-200">HTTP/1.1 200 OK
Content-Type: text/html
Content-Security-Policy: [tidak ditemukan]</pre>
                        </div>
                        <div class="mt-5 flex items-start gap-3 text-xs"><x-jejaksa.icon name="check" size="16" class="shrink-0 text-teal-300" /><p class="muted">Terapkan CSP, periksa kompatibilitas, lalu lakukan retest.</p></div>
                    </div>
                </div>
            </section>

            <aside class="scope-note" data-reveal="fade" aria-label="Batas penggunaan">
                <x-jejaksa.icon name="shield" size="26" class="mt-1 text-teal-300" />
                <h2>Pengujian tetap dalam kendali Anda.</h2>
                <p>Gunakan target berizin dan tinjau hasilnya secara manual. Preview ini memakai data simulasi; integrasi tool dan AI mengikuti kesiapan backend.</p>
            </aside>

            <section id="faq" class="landing-section">
                <div class="editorial-grid items-start">
                    <div data-reveal="rise"><p class="section-label">Ada pertanyaan?</p><h2 class="section-heading">Sebelum mulai.</h2><p class="section-copy">Beberapa hal tentang demo dan batas penggunaan Jejaksa.</p></div>
                    <div data-reveal="fade">
                        @foreach([
                            ['Apa yang bisa dicoba sekarang?', 'Buat akun, atur target demo, coba wizard scan, tinjau temuan, dan unduh laporan contoh. Demo tidak mengirim permintaan pengujian ke target.'],
                            ['Apakah semua jenis kerentanan didukung?', 'Cakupan mengikuti tool, profil, dan integrasi backend yang tersedia. Tool aktif di katalog belum tentu siap menjalankan pengujian.'],
                            ['Target seperti apa yang boleh diuji?', 'Gunakan web milik Anda atau target yang Anda memiliki izin untuk menguji. Tetapkan scope dan pengecualiannya terlebih dahulu.'],
                            ['Apakah hasilnya menjamin aplikasi aman?', 'Hasil hanya berlaku pada scope dan waktu pengujian, serta tetap perlu ditinjau. Laporan atau sertifikat contoh bukan jaminan bebas kerentanan.'],
                        ] as [$question, $answer])
                            <details class="faq-item" data-faq>
                                <summary>{{ $question }}</summary>
                                <div class="faq-answer"><p>{{ $answer }}</p></div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bottom-bar" aria-labelledby="closing-title" data-reveal="rise">
                <div><h2 id="closing-title">Mulai dari satu target.</h2><p>Siapkan scope dan lihat bagaimana alurnya bekerja.</p></div>
                <a class="bottom-bar-action" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Buka workspace' : 'Coba workspace Jejaksa' }}<x-jejaksa.icon name="arrow" size="22" /></a>
            </section>
        </main>

        <footer class="landing-footer">
            <div class="footer-brand"><a href="{{ route('home') }}" aria-label="Jejaksa beranda"><img src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="120" height="43"></a><p>© {{ date('Y') }} · PBKK Kelompok 4</p></div>
            <div class="footer-links"><a href="#faq">Panduan penggunaan</a><a class="footer-to-top" href="#main-content">Kembali ke atas<x-jejaksa.icon name="arrow" size="14" /></a></div>
        </footer>
    </div>
@endcomponent
