const CACHE_NAME = 'cemetery-forms-v1';
const urlsToCache = [
  '/',
  '/form/',
  '/forms_list.php',
  '/css/style.css',
  '/css/rtl.css',
  '/js/main.js',
  '/offline.html'
];

// Installation
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Activation
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(cacheName => {
          return cacheName !== CACHE_NAME;
        }).map(cacheName => caches.delete(cacheName))
      );
    })
  );
});

// Fetch - Network First Strategy for API calls, Cache First for assets
self.addEventListener('fetch', event => {
  if (event.request.url.includes('ajax/') || event.request.url.includes('/api/')) {
    // Network First for API calls
    event.respondWith(
      fetch(event.request)
        .then(response => {
          if (response.ok) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
  } else {
    // Cache First for static assets
    event.respondWith(
      caches.match(event.request)
        .then(response => response || fetch(event.request))
        .catch(() => {
          if (event.request.mode === 'navigate') {
            return caches.match('offline.html');
          }
        })
    );
  }
});

// Handle Share Target API
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  if (url.pathname === '/share-target.php' && event.request.method === 'POST') {
    event.respondWith(handleShareTarget(event.request));
  }
});

async function handleShareTarget(request) {
  const formData = await request.formData();
  const files = formData.getAll('files');
  const title = formData.get('title') || '';
  const text = formData.get('text') || '';
  const url = formData.get('url') || '';
  
  // Store shared data temporarily
  const cache = await caches.open('shared-files');
  const sharedData = {
    files: files,
    title: title,
    text: text,
    url: url,
    timestamp: Date.now()
  };
  
  await cache.put('/shared-data', new Response(JSON.stringify(sharedData)));
  
  // Redirect to form selection page
  return Response.redirect('/select-form-for-share.php', 303);
}

// Push Notifications
self.addEventListener('push', event => {
  const options = {
    body: event.data ? event.data.text() : 'טופס חדש נוצר',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'view',
        title: 'צפה בטופס',
        icon: '/icons/view.png'
      },
      {
        action: 'close',
        title: 'סגור',
        icon: '/icons/close.png'
      }
    ],
    dir: 'rtl',
    lang: 'he'
  };
  
  event.waitUntil(
    self.registration.showNotification('מערכת טפסים', options)
  );
});

// Notification Click Handler
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view') {
    event.waitUntil(
      clients.openWindow('/forms_list.php')
    );
  }
});

// Background Sync for offline form submission
self.addEventListener('sync', event => {
  if (event.tag === 'sync-forms') {
    event.waitUntil(syncForms());
  }
});

async function syncForms() {
  const cache = await caches.open('offline-forms');
  const requests = await cache.keys();
  
  for (const request of requests) {
    try {
      const response = await fetch(request.clone());
      if (response.ok) {
        await cache.delete(request);
      }
    } catch (error) {
      console.error('Sync failed:', error);
    }
  }
}