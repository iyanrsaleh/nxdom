import express from 'express';
import cors from 'cors';
import path from 'path';
import fs from 'fs/promises';
import { fileURLToPath, pathToFileURL } from 'url';
import { createProxyMiddleware } from 'http-proxy-middleware';
import config from './config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Create Express app
const app = express();

// Baca URL server dari config.js (sama objek dengan yang di-import App.js di browser)
function buildServerConfig() {
  const envPortRaw = process.env.PORT;
  if (envPortRaw != null && String(envPortRaw).trim() !== '') {
    const p = parseInt(String(envPortRaw).trim(), 10);
    if (Number.isFinite(p) && p >= 1 && p <= 65535) {
      // Bind ke 127.0.0.1 (bukan "localhost") agar konsisten di Windows: "localhost" sering ::1-only,
      // sementara Nexa/Electron mengecek kesiapan port lewat IPv4 127.0.0.1.
      return {
        host: '127.0.0.1',
        port: p,
        hostname: 'localhost',
        baseUrl: `http://127.0.0.1:${p}`,
      };
    }
  }
  try {
    const baseUrl = config.baseUrl || config.url;
    if (baseUrl) {
      const u = new URL(baseUrl);
      const port = u.port || (u.protocol === 'https:' ? 443 : 80);
      const hostname = u.hostname;
      const host = (hostname === 'localhost' || hostname === '127.0.0.1') ? 'localhost' : '0.0.0.0';
      return {
        host,
        port: parseInt(port, 10),
        hostname,
        baseUrl,
      };
    }
  } catch (error) {
    console.warn('⚠️ Could not read config.js, using default localhost:3000');
  }
  return {
    host: 'localhost',
    port: 3000,
    hostname: 'localhost',
    baseUrl: 'http://localhost:3000',
  };
}

// Server config will be set when startServer is called
let SERVER_CONFIG = {
  host: 'localhost',
  port: 3000,
  hostname: 'localhost',
  baseUrl: 'http://localhost:3000',
};

// Middleware
app.use(cors());

// --- Keamanan (setara ide .htaccess: bedakan file klien vs server) ---
// App.js / assets dilayani publik; config disuntik lewat HTML (bukan URL /config.js).
// server.js, config.js mentah, package.json, .env, node_modules tidak dilayani publik.
const SENSITIVE_PUBLIC_NAMES = new Set([
  'server.js',
  'index.js',
  'config.js',
  'package.json',
  'package-lock.json',
  '.env',
  '.env.local',
  '.env.production',
]);

app.use((req, res, next) => {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    return next();
  }
  const seg = req.path.replace(/^\/+/, '').split('/').filter(Boolean);
  const first = (seg[0] || '').toLowerCase();
  if (first === 'node_modules' || seg.some((s) => s.toLowerCase() === 'node_modules')) {
    return res.status(404).end();
  }
  if (SENSITIVE_PUBLIC_NAMES.has(first)) {
    return res.status(404).end();
  }
  next();
});

/**
 * Origin yang dilihat browser (localhost vs 127.0.0.1 bisa beda — pakai Host request).
 */
function clientOriginFromReq(req) {
  const host = req.headers['x-forwarded-host'] || req.headers.host;
  if (!host || typeof host !== 'string') return null;
  let proto = req.headers['x-forwarded-proto'];
  if (typeof proto === 'string' && proto.includes(',')) {
    proto = proto.split(',')[0].trim();
  }
  if (!proto) proto = req.protocol || 'http';
  return `${proto}://${host}`;
}

/**
 * Backend sering mengembalikan redirect absolut ke host sendiri (mis. http://192.168.1.5/home).
 * Browser mengikuti ke origin lain → CORS. Tulis ulang Location ke origin SPA (proxy).
 */
function rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin) {
  const clientOrigin = clientOriginFromReq(req);
  if (!clientOrigin) return;

  let upstreamHost;
  try {
    upstreamHost = new URL(upstreamOrigin).hostname;
  } catch {
    upstreamHost = null;
  }

  const rewrite = (val) => {
    if (typeof val !== 'string' || !val.trim()) return val;
    try {
      const parsed = new URL(val, clientOrigin);
      const sameOrigin = parsed.origin === upstreamOrigin;
      const sameHost =
        upstreamHost && parsed.hostname === upstreamHost;
      if (sameOrigin || sameHost) {
        return clientOrigin + parsed.pathname + parsed.search + parsed.hash;
      }
    } catch {
      /* biarkan */
    }
    return val;
  };

  if (proxyRes.headers.location) {
    proxyRes.headers.location = rewrite(proxyRes.headers.location);
  }
  if (proxyRes.headers['content-location']) {
    proxyRes.headers['content-location'] = rewrite(proxyRes.headers['content-location']);
  }
}

/**
 * Express memangkas prefix mount → `req.url` jadi `/outside` bukan `/api/outside`.
 * http-proxy membaca `req.url` — set ke `originalUrl` sebelum proxy jalan.
 */
function patchReqUrlToOriginal(req, res, next) {
  if (req.originalUrl) req.url = req.originalUrl;
  next();
}

/**
 * Proxy /api, /assets/drive, /rebit ke host asli dari config.
 * Hanya jika origin backend ≠ origin listener (sama logika dengan buildServerConfig + PORT).
 */
function mountUpstreamProxies() {
  let listenerOrigin;
  try {
    listenerOrigin = new URL(buildServerConfig().baseUrl).origin;
  } catch {
    try {
      listenerOrigin = new URL(config.url || 'http://localhost:3000').origin;
    } catch {
      listenerOrigin = 'http://localhost:3000';
    }
  }
  try {
    if (config.urlApi && /^https?:\/\//i.test(config.urlApi)) {
      const u = new URL(config.urlApi);
      const upstreamOrigin = u.origin;
      if (upstreamOrigin !== listenerOrigin) {
        const apiProxy = createProxyMiddleware({
          target: upstreamOrigin,
          changeOrigin: true,
          on: {
            proxyRes(proxyRes, req, res) {
              rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin);
            },
          },
        });
        app.use('/api', patchReqUrlToOriginal, apiProxy);
        console.log(`🔀 API proxy: /api → ${config.urlApi} (dari config.urlApi)`);
      }
    }
  } catch (e) {
    console.warn('⚠️ API proxy tidak dipasang:', e && e.message ? e.message : e);
  }
  try {
    if (config.drive && /^https?:\/\//i.test(config.drive)) {
      const d = new URL(config.drive);
      const upstreamOrigin = d.origin;
      if (upstreamOrigin !== listenerOrigin) {
        const basePath = d.pathname.replace(/\/$/, '') || '/assets/drive';
        const driveProxy = createProxyMiddleware({
          target: upstreamOrigin,
          changeOrigin: true,
          on: {
            proxyRes(proxyRes, req, res) {
              rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin);
            },
          },
        });
        app.use(basePath, patchReqUrlToOriginal, driveProxy);
        console.log(`🔀 Drive proxy: ${basePath} → ${config.drive}`);
      }
    }
  } catch (e) {
    console.warn('⚠️ Drive proxy tidak dipasang:', e && e.message ? e.message : e);
  }
  try {
    if (config.rebit && /^https?:\/\//i.test(config.rebit)) {
      const r = new URL(config.rebit);
      const upstreamOrigin = r.origin;
      if (upstreamOrigin !== listenerOrigin) {
        const basePath = r.pathname.replace(/\/$/, '') || '/rebit';
        const rebitProxy = createProxyMiddleware({
          target: upstreamOrigin,
          changeOrigin: true,
          on: {
            proxyRes(proxyRes, req, res) {
              rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin);
            },
          },
        });
        app.use(basePath, patchReqUrlToOriginal, rebitProxy);
        console.log(`🔀 Rebit proxy: ${basePath} → ${config.rebit}`);
      }
    }
  } catch (e) {
    console.warn('⚠️ Rebit proxy tidak dipasang:', e && e.message ? e.message : e);
  }
}

mountUpstreamProxies();

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

/**
 * Objek untuk browser: `url` = host server yang listen.
 * `urlApi` / `drive`: jika backend beda host/port (PHP dll.), browser dapat
 * same-origin `http://…:5000/api` + `…/assets/drive` (lewat proxy di atas);
 * jika sudah satu origin dengan `config.url`, selaraskan ke origin server yang listen.
 *
 * `req` (opsional): pakai Host browser agar `localhost` vs `127.0.0.1` tidak beda origin dengan apiBase.
 */
function buildClientEndpointPayload(req) {
  const raw = JSON.parse(JSON.stringify(config));
  let baseUrl = SERVER_CONFIG.baseUrl || raw.url || 'http://127.0.0.1:3000';
  let origin;
  try {
    origin = new URL(baseUrl).origin;
  } catch {
    origin = 'http://127.0.0.1:3000';
  }
  if (req && typeof req.get === 'function') {
    const host = req.get('host');
    if (host) {
      let proto = req.headers['x-forwarded-proto'];
      if (typeof proto === 'string' && proto.includes(',')) {
        proto = proto.split(',')[0].trim();
      }
      if (!proto) proto = req.protocol || 'http';
      origin = `${proto}://${host}`;
      baseUrl = origin;
    }
  }
  let appOrigin;
  try {
    appOrigin = new URL(config.url || baseUrl).origin;
  } catch {
    appOrigin = origin;
  }
  raw.url = baseUrl;
  for (const key of ['urlApi', 'drive', 'rebit']) {
    const v = raw[key];
    if (typeof v !== 'string' || !v.startsWith('http')) continue;
    try {
      const u = new URL(v);
      if (u.origin !== origin) {
        const p = u.pathname.replace(/\/$/, '');
        raw[key] = origin + p + u.search + u.hash;
        continue;
      }
      if (u.origin === appOrigin) {
        raw[key] = origin + u.pathname + u.search + u.hash;
      }
    } catch {
      /* biarkan */
    }
  }
  return raw;
}

/** HTML SPA: suntik endpoint ke window.__NEXA_ENDPOINT__ agar tidak ada GET /config.js */
async function sendSpaHtml(req, res) {
  const htmlPath = path.join(__dirname, 'index.html');
  let html = await fs.readFile(htmlPath, 'utf8');
  const payload = JSON.stringify(buildClientEndpointPayload(req));
  const inject = `<script>window.__NEXA_ENDPOINT__=${payload}</script>`;
  if (!html.includes('</head>')) {
    res.type('html').send(html);
    return;
  }
  html = html.replace('</head>', `${inject}</head>`);
  res.type('html').send(html);
}

app.get(['/', '/index.html'], async (req, res, next) => {
  try {
    await sendSpaHtml(req, res);
  } catch (err) {
    next(err);
  }
});

// Serve static files dari root directory (index otomatis mati — / pakai sendSpaHtml)
app.use(express.static(path.join(__dirname), { index: false }));

// Serve assets folder dengan path yang benar (setelah proxy /assets/drive ke upstream jika ada)
app.use('/assets', express.static(path.join(__dirname, 'assets')));

// Contoh API Node (bukan /api — supaya tidak bentrok dengan proxy → PHP)
app.get('/nexa-dev/status', (req, res) => {
  res.json({
    status: 'online',
    message: 'Express server is running',
    timestamp: new Date().toISOString(),
  });
});

app.get('/nexa-dev/info', (req, res) => {
  res.json({
    platform: process.platform,
    arch: process.arch,
    nodeVersion: process.version,
    electronVersion: process.versions.electron,
    chromeVersion: process.versions.chrome,
    serverPort: SERVER_CONFIG.port,
    serverHost: SERVER_CONFIG.hostname,
    baseUrl: SERVER_CONFIG.baseUrl,
  });
});

app.post('/nexa-dev/message', (req, res) => {
  const { message } = req.body;
  console.log('📨 Received message:', message);
  res.json({
    success: true,
    echo: message,
    timestamp: new Date().toISOString(),
  });
});

app.get('/nexa-dev/users', (req, res) => {
  res.json({
    users: [
      { id: 1, name: 'John Doe', email: 'john@example.com' },
      { id: 2, name: 'Jane Smith', email: 'jane@example.com' },
    ],
  });
});

app.post('/nexa-dev/users', (req, res) => {
  const { name, email } = req.body;
  res.json({
    success: true,
    user: {
      id: Date.now(),
      name,
      email,
    },
  });
});

app.get('/health', (req, res) => {
  res.json({ status: 'healthy', uptime: process.uptime() });
});

// SPA fallback (/api diteruskan ke proxy PHP di atas)
app.use((req, res, next) => {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    return next();
  }
  sendSpaHtml(req, res).catch(next);
});

// Port dan baseUrl dari config.js — tidak pindah port otomatis
async function startServer() {
  SERVER_CONFIG = buildServerConfig();

  return new Promise((resolve, reject) => {
    const server = app.listen(SERVER_CONFIG.port, SERVER_CONFIG.host, () => {
      console.log(`✅ Express server running on ${SERVER_CONFIG.baseUrl}`);
      resolve(server);
    });

    server.on('error', (err) => {
      if (err.code === 'EADDRINUSE') {
        console.error(
          `❌ Port ${SERVER_CONFIG.port} sudah dipakai — tidak mengubah port (harus sama dengan url/baseUrl di config.js: ${SERVER_CONFIG.baseUrl}).`
        );
        console.error('   Tutup proses Node lain di port ini, atau ubah url di config.js.');
        reject(err);
        return;
      }
      reject(err);
    });
  });
}

const isMain =
  process.argv[1] &&
  pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url;

if (isMain) {
  startServer().catch(() => {
    process.exitCode = 1;
  });
}

export function getServerConfig() {
  return SERVER_CONFIG;
}

export { app, startServer };
