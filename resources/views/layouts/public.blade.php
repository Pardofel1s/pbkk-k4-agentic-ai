<!DOCTYPE html>
<html lang="id">
    <head>
        <x-jejaksa.head />
    </head>
    <body class="landing antialiased">
        <a class="skip-link" href="#main-content">Lewati ke konten</a>
        {{ $slot }}
        @livewireScripts
    </body>
</html>
