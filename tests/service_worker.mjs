// Run with: node tests/service_worker.mjs
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const listeners = {};
const stored = [];
const removed = [];
let networkFails = false;
let response = new Response('icon', {status: 200});
// A same-origin fetch produces a basic response in a real Service Worker.
Object.defineProperty(response, 'type', {value: 'basic'});
const fallback = new Response('cached icon');
const cache = {addAll: async () => {}, put: async (request) => stored.push(request.url)};
const context = {
  URL, Response, Promise,
  self: {location: {origin: 'https://example.test'}, skipWaiting() {}, clients: {claim: async () => {}},
    addEventListener(name, handler) {listeners[name] = handler;}},
  caches: {open: async () => cache, keys: async () => ['vcard-pwa-v4', 'vcard-public-v5', 'other-app'],
    delete: async (key) => removed.push(key), match: async () => fallback},
  fetch: async () => {if (networkFails) throw new Error('offline'); return response;},
};
vm.runInNewContext(fs.readFileSync(new URL('../public/sw.php', import.meta.url), 'utf8').replace(/^<\?php[\s\S]*?\?>/, ''), context);
let checks = 0;
for (const path of ['/admin', '/admin/settings', '/api/contacts', '/api.php', '/ab', '/uploads/photo.png', '/assets/icons/icon.svg?token=secret', 'https://elsewhere.test/assets/icons/icon.svg']) {
  let intercepted = false;
  listeners.fetch({request: {url: new URL(path, 'https://example.test').href, method: 'GET'}, respondWith() {intercepted = true;}});
  assert.equal(intercepted, false, path + ' must bypass the cache'); checks++;
}
async function asset(method = 'GET') {
  const pending = [];
  let result;
  listeners.fetch({request: {url: 'https://example.test/assets/icons/icon.svg', method},
    respondWith(value) {result = value;}, waitUntil(value) {pending.push(value);}});
  const resolved = result ? await result : undefined;
  await Promise.all(pending);
  return resolved;
}
assert.equal(await asset('POST'), undefined); checks++;
assert.equal((await asset()).status, 200); checks++;
assert.equal(stored.length, 1); checks++;
networkFails = true;
assert.equal(await (await asset()).text(), 'cached icon'); checks++;
let activation;
listeners.activate({waitUntil(value) {activation = value;}});
await activation;
assert.deepEqual(removed, ['vcard-pwa-v4']); checks++;
console.log(`OK: ${checks} Service Worker security checks passed.`);
