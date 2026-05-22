/**
 * Content-Security-Policy untuk halaman Nexa (disuntik di awal head oleh index.js).
 */
'use strict';

function buildContentSecurityPolicy(config = {}) {
  const origins = new Set();
  for (const key of ['url', 'urlApi', 'drive', 'typicode']) {
    const u = config[key];
    if (typeof u === 'string' && u) {
      try {
        origins.add(new URL(u).origin);
      } catch {
        /* abaikan */
      }
    }
  }
  const originList = [...origins].join(' ');

  const parts = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.gstatic.com https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.responsivevoice.org https://*.firebaseio.com https://*.googleapis.com https://*.gstatic.com https://*.firebase.com",
    "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    "media-src 'self' http://localhost:* https://localhost:* http: https:",
    `connect-src 'self' http://localhost:* https://localhost:* ws://localhost:* ws://127.0.0.1:* ws://127.0.0.1:4117 wss://localhost:* wss://127.0.0.1:* http: https: ${originList} https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.responsivevoice.org https://*.firebaseio.com https://*.googleapis.com https://*.firebase.com wss://*.firebaseio.com`,
    "img-src 'self' data: blob: http://localhost:* https://localhost:* http: https:",
    "font-src 'self' data: https:",
    `frame-src 'self' ${originList} http://localhost:* https://localhost:* https://*.firebaseio.com https://*.googleapis.com https://*.firebase.com https://youtube.com https://www.youtube.com https://*.youtube.com https://youtube-nocookie.com https://www.youtube-nocookie.com https://*.youtube-nocookie.com https://*.googlevideo.com`,
  ];

  return parts
    .map((p) => p.trim().replace(/\s+/g, ' '))
    .filter(Boolean)
    .join('; ');
}

module.exports = { buildContentSecurityPolicy };
