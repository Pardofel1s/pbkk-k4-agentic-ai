<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0B1220">
<meta name="description" content="Jejaksa membantu mengelola target, menelusuri pengujian keamanan web, dan meninjau bukti serta rekomendasi.">
<link rel="icon" type="image/png" href="{{ asset('brand/jejaksa-favicon-32.png') }}">
<link rel="preload" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans/PlusJakartaSans-Variable.ttf') }}" as="font" type="font/ttf" crossorigin>
<title>{{ $title ?? 'Jejaksa — Setiap celah, jelas jejaknya.' }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
