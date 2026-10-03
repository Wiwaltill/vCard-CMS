<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
?>
const CACHE = 'vcard-public-v5';
const CORE = ['/assets/icons/icon.svg'];
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(CORE)));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith('vcard-') && key !== CACHE).map(key => caches.delete(key))
  )).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Only explicitly listed static assets; never cards, uploads, admin or API data.
  if (event.request.method !== 'GET' || url.origin !== self.location.origin || url.search || !CORE.includes(url.pathname)) return;
  event.respondWith(fetch(event.request).then(response => {
    if (response.ok && !response.redirected && response.type === 'basic') {
      event.waitUntil(caches.open(CACHE).then(cache => cache.put(event.request, response.clone())));
    }
    return response;
  }).catch(() => caches.match(event.request).then(response => response || Response.error())));
});
