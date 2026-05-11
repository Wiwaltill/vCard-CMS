<?php header('Content-Type: application/javascript; charset=utf-8'); ?>
const CACHE='vcard-pwa-v1';
self.addEventListener('install', e => e.waitUntil(caches.open(CACHE).then(c => c.addAll(['/','/manifest.webmanifest']))));
self.addEventListener('fetch', e => e.respondWith(fetch(e.request).catch(() => caches.match(e.request))));
