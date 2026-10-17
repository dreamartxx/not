<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; connect-src 'self'; worker-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#333d4c"><meta name="description" content="Not — kişisel düşünce ve çalışma alanın."><title>Not · Kişisel alanım</title><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/apple-touch-icon-3d.png"><meta name="apple-mobile-web-app-title" content="Not"><link rel="icon" href="assets/icon-3d-32.png" type="image/png" sizes="32x32"><link rel="stylesheet" href="assets/style.css?v=<?= substr(hash_file('sha256', __DIR__.'/assets/style.css'),0,12) ?>"><script type="module" src="assets/app.js?v=<?= substr(hash('sha256', hash_file('sha256', __DIR__.'/assets/app.js').hash_file('sha256', __DIR__.'/assets/studio.js').hash_file('sha256', __DIR__.'/assets/canvas.js').hash_file('sha256', __DIR__.'/assets/pwa.js')),0,12) ?>"></script></head>
<body><div id="app"><div class="loading"><span class="brand-mark">n.</span><p>Çalışma alanın açılıyor…</p></div></div><div id="toast" role="status" aria-live="polite"></div><dialog id="dialog"></dialog></body></html>
