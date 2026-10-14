<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f7f7f5"><meta name="description" content="Not — kişisel düşünce ve çalışma alanın."><title>Not · Kişisel alanım</title><link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css?v=<?= substr(hash_file('sha256', __DIR__.'/assets/style.css'),0,12) ?>"><script type="module" src="assets/app.js?v=<?= substr(hash_file('sha256', __DIR__.'/assets/app.js'),0,12) ?>"></script></head>
<body><div id="app"><div class="loading"><span class="brand-mark">n.</span><p>Çalışma alanın açılıyor…</p></div></div><div id="toast" role="status" aria-live="polite"></div><dialog id="dialog"></dialog></body></html>
