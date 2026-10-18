<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
header('Permissions-Policy: microphone=(), camera=()');
$files=['education-guest','education','education-course','education-tutorial','education-book-course','education-outline-model','education-visuals','education-model'];
$version=substr(hash('sha256',implode('',array_map(fn($f)=>hash_file('sha256',__DIR__.'/assets/'.$f.'.js'),$files))),0,12);
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Not · Misafir eğitim</title><link rel="icon" href="assets/icon-3d-32.png"><link rel="stylesheet" href="assets/style.css?v=<?=substr(hash_file('sha256',__DIR__.'/assets/style.css'),0,12)?>"><link rel="stylesheet" href="assets/education.css?v=<?=substr(hash_file('sha256',__DIR__.'/assets/education.css'),0,12)?>"><link rel="stylesheet" href="assets/education-guest.css?v=<?=substr(hash_file('sha256',__DIR__.'/assets/education-guest.css'),0,12)?>"><script type="module" src="assets/education-guest.js?v=<?=$version?>"></script></head><body class="guest-education"><div id="app"><div class="loading"><span class="brand-mark">n.</span><p>Eğitim alanı açılıyor…</p></div></div><div id="toast" role="status"></div><dialog id="dialog"></dialog></body></html>
