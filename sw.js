/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/* Service worker — Transparência Jardim Maceió */
const VERSAO = 'jm-20260919092433';
const BASE = self.registration.scope.replace(/\/$/, '');
const PRECACHE = [
  BASE + '/offline.html',
  BASE + '/assets/favicon.svg',
  BASE + '/assets/icons/icon-192.png',
  BASE + '/assets/icons/icon-512.png',
  BASE + '/manifest.webmanifest'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSAO).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys()
    .then(ks => Promise.all(ks.filter(k => k !== VERSAO && k !== 'jm-dados').map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // APIs: sempre rede; sem conexão, devolve a última resposta vista (leitura offline)
  if (url.origin === location.origin && url.pathname.includes('/api/')) {
    if (url.search.includes('acao=logout')) return;
    e.respondWith(fetch(req).then(r => {
      if (r.ok) { const cp = r.clone(); caches.open('jm-dados').then(c => c.put(req, cp)); }
      return r;
    }).catch(() => caches.match(req, { cacheName: 'jm-dados' }).then(r => {
      const h = { 'Content-Type': 'application/json', 'X-JM-Reserva': '1' };
      if (!r) return new Response(JSON.stringify({ ok: false, rede: true, erro: 'Sem conexão — dado ainda não visto neste aparelho.' }), { status: 503, headers: h });
      return r.blob().then(corpo => new Response(corpo, { status: 200, headers: Object.assign(h, { 'X-JM-Salvo-Em': r.headers.get('date') || '' }) }));
    })));
    return;
  }
  if (url.origin === location.origin && url.pathname.includes('/uploads/')) return;

  // Páginas: rede primeiro; offline cai na última versão vista ou na página offline
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then(r => {
      if (r.ok && !r.redirected) { const cp = r.clone(); caches.open(VERSAO).then(c => c.put(req, cp)); }
      return r;
    }).catch(() => caches.match(req).then(r => r || caches.match(BASE + '/offline.html'))));
    return;
  }

  // CSS/JS/fontes/ícones: cache e atualiza em segundo plano
  if (/\.(css|js|png|jpe?g|svg|webp|woff2?|webmanifest)$/.test(url.pathname) || url.host.includes('fonts.g') || url.host.includes('jsdelivr')) {
    e.respondWith(caches.match(req).then(c => {
      const rede = fetch(req).then(r => {
        if (r.ok || r.type === 'opaque') { const cp = r.clone(); caches.open(VERSAO).then(ca => ca.put(req, cp)); }
        return r;
      }).catch(() => c);
      return c || rede;
    }));
  }
});

self.addEventListener('message', e => {
  if (e.data === 'skipWaiting') self.skipWaiting();
  if (e.data === 'limparDados') { caches.delete('jm-dados'); caches.keys().then(ks => ks.forEach(k => caches.open(k).then(c => c.keys().then(rs => rs.forEach(r => { if (r.mode === 'navigate' || /\/views\//.test(r.url)) c.delete(r); }))))); }
});
