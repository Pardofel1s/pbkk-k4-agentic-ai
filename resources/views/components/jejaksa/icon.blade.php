@props(['name' => 'grid', 'size' => 20])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'target' => 'M21 12a9 9 0 1 1-9-9 M17 3h4v4 M21 3l-9 9 M16 12a4 4 0 1 1-4-4',
        'scan' => 'M7 3H3v4 M17 3h4v4 M21 17v4h-4 M7 21H3v-4 M3 12h18 M7 8h10v8H7z',
        'shield' => 'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M9 12l2 2 4-4',
        'chat' => 'M21 11a8 8 0 0 1-8 8H7l-4 3V6a3 3 0 0 1 3-3h7a8 8 0 0 1 8 8z M8 10h8 M8 14h5',
        'file' => 'M14 3H5v18h14V8z M14 3v5h5 M8 12h8 M8 16h6',
        'award' => 'M8 14l-2 8 6-3 6 3-2-8 M19 9a7 7 0 1 1-14 0 7 7 0 0 1 14 0z',
        'tool' => 'M14 6a5 5 0 0 0-6 6L3 17a3 3 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-4 4-4-4z',
        'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
        'users' => 'M16 21v-3a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v3 M13 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0z M17 3a4 4 0 0 1 0 8 M22 21v-3a4 4 0 0 0-3-4',
        'search' => 'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0z',
        'plus' => 'M12 5v14 M5 12h14', 'arrow' => 'M5 12h14 M14 7l5 5-5 5',
        'chevron' => 'M9 5l7 7-7 7', 'check' => 'M5 12l4 4L19 6',
        'clock' => 'M12 7v5l3 2 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'alert' => 'M12 3L2 21h20z M12 9v5 M12 17v.1',
        'sparkles' => 'M12 3l2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z',
        'logout' => 'M9 4H3v16h6 M9 12h12 M17 8l4 4-4 4',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16', 'close' => 'M6 6l12 12 M6 18L18 6',
        'download' => 'M12 3v12 M7 10l5 5 5-5 M4 17v4h16v-4',
        'code' => 'M8 5l-6 7 6 7 M16 5l6 7-6 7 M14 3l-4 18',
        'link' => 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-2 2 M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l2-2',
        'play' => 'M8 4l12 8-12 8z', 'lock' => 'M5 10h14v11H5z M8 10V6a4 4 0 0 1 8 0v4',
    ];
@endphp
<svg {{ $attributes }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>
