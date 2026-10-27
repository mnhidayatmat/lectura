{{-- PWA Meta Tags --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#4F46E5">
<meta name="mobile-web-app-capable" content="yes">

{{-- QR check-in is offered only in the installed PWA (and the native app), never in a browser tab --}}
<script>
    if (window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches || window.matchMedia('(display-mode: fullscreen)').matches) {
        document.documentElement.classList.add('is-pwa');
    }
</script>
<style>
    html:not(.is-pwa) .pwa-only { display: none !important; }
    html.is-pwa .web-only { display: none !important; }
</style>

{{-- iOS PWA --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Lectura">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Favicons --}}
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="shortcut icon" href="/favicon.ico">

{{-- Microsoft --}}
<meta name="msapplication-TileColor" content="#4F46E5">
<meta name="msapplication-TileImage" content="/icons/icon-144x144.png">
