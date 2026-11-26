const CACHE_NAME = 'tayar-plus-v5';
const STATIC_CACHE = `${CACHE_NAME}-static`;
const PAGE_CACHE = `${CACHE_NAME}-pages`;

const PRECACHE_URLS = [
    '/manifest.json',
    '/fonts/ibm-plex-sans-arabic-400.woff2',
    '/fonts/ibm-plex-sans-arabic-500.woff2',
    '/fonts/ibm-plex-sans-arabic-600.woff2',
    '/fonts/ibm-plex-sans-arabic-700.woff2',
    '/icons/tayar-icon-192.png',
    '/icons/tayar-icon-512.png',
    '/img/tayar-plus-logo.png',
];

function isNetworkOnlyPath(pathname) {
    return pathname.startsWith('/api/v1/sync/')
        || pathname.startsWith('/api/v1/auth/')
        || pathname.startsWith('/api/');
}

function isStaticAssetPath(pathname) {
    return pathname.startsWith('/build/')
        || pathname.startsWith('/fonts/');
}

function isRevalidatedPwaAsset(pathname) {
    return pathname === '/manifest.json'
        || pathname.startsWith('/icons/')
        || pathname.startsWith('/img/');
}

function isHtmlNavigationRequest(request, pathname) {
    if (request.mode === 'navigate' || pathname === '/pos') {
        return true;
    }
    const accept = request.headers.get('accept') || '';
    return accept.includes('text/html');
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await Promise.allSettled(
            PRECACHE_URLS.map((url) => cache.add(url)),
        );

        // Pre-cache active Vite production assets from build/manifest.json if present
        try {
            const manifestRes = await fetch('/build/manifest.json', { cache: 'no-store' });
            if (manifestRes.ok) {
                const manifest = await manifestRes.json();
                const assetUrls = new Set();
                for (const entry of Object.values(manifest)) {
                    if (entry && entry.file) {
                        assetUrls.add(`/build/${entry.file}`);
                    }
                    if (Array.isArray(entry?.css)) {
                        for (const cssFile of entry.css) {
                            assetUrls.add(`/build/${cssFile}`);
                        }
                    }
                }
                await Promise.allSettled(
                    Array.from(assetUrls).map((url) => cache.add(url)),
                );
            }
        } catch {
            // Ignore precache manifest errors during offline install
        }

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((k) => k !== STATIC_CACHE && k !== PAGE_CACHE)
                .map((k) => caches.delete(k)),
        );
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // 1. Network-Only: Sync & Auth APIs must NEVER be cached
    if (isNetworkOnlyPath(url.pathname)) {
        event.respondWith(fetch(request));
        return;
    }

    // 2. Network-First (bypassing stale HTTP disk cache, with offline fallback): manifest.json, icons & brand images
    if (isRevalidatedPwaAsset(url.pathname)) {
        event.respondWith((async () => {
            const cache = await caches.open(STATIC_CACHE);
            try {
                const networkRes = await fetch(request, { cache: 'no-cache' });
                if (networkRes && networkRes.ok && networkRes.type === 'basic') {
                    cache.put(request, networkRes.clone());
                }
                return networkRes;
            } catch {
                const cached = await cache.match(request, { ignoreSearch: true });
                if (cached) {
                    return cached;
                }
                throw new Error('Offline and asset not cached');
            }
        })());
        return;
    }

    // 3. Cache-First: Immutable hashed static assets (/build/*, /fonts/*)
    if (isStaticAssetPath(url.pathname)) {
        event.respondWith((async () => {
            const cache = await caches.open(STATIC_CACHE);
            const cached = await cache.match(request);
            if (cached) {
                return cached;
            }
            const networkRes = await fetch(request);
            if (networkRes && networkRes.ok && networkRes.type === 'basic') {
                cache.put(request, networkRes.clone());
            }
            return networkRes;
        })());
        return;
    }

    // 3. Network-First (with offline cache fallback): HTML navigation requests including /pos
    if (isHtmlNavigationRequest(request, url.pathname)) {
        event.respondWith((async () => {
            const cache = await caches.open(PAGE_CACHE);
            try {
                const networkRes = await fetch(request);
                if (networkRes && networkRes.ok && !networkRes.redirected && networkRes.type === 'basic') {
                    cache.put(request, networkRes.clone());
                    if (url.pathname === '/pos') {
                        cache.put('/pos', networkRes.clone());
                    }
                }
                return networkRes;
            } catch {
                const cached = (await cache.match(request))
                    || (url.pathname === '/pos' ? await cache.match('/pos') : null);
                if (cached) {
                    return cached;
                }

                return new Response(
                    '<!DOCTYPE html><html lang="ar" dir="rtl"><meta charset="utf-8"><body style="font-family:sans-serif;text-align:center;padding:40px"><h2>تيار بلس — تعذر فتح الكاشير أوفلاين</h2><p>يرجى فتح /pos مرة واحدة أثناء توفر الإنترنت لتخزين الصفحة محلياً.</p></body></html>',
                    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
                );
            }
        })());
    }
});