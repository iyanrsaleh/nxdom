/**
 * Express + static SPA — pola sama electronv2 (CommonJS + require dari main).
 * Dipakai: Electron main (app.asar) dan `npm run server` / `node index.js`.
 */
'use strict';

try {
  const dotenv = require('dotenv');
  const envPath = require('path').join(__dirname, '.env');
  dotenv.config({ path: envPath });
} catch (_) {}

const express = require('express');
const cors = require('cors');
const path = require('path');
const os = require('os');
const fsSync = require('fs');
const fs = require('fs').promises;
const { createProxyMiddleware } = require('http-proxy-middleware');
const config = require('./config.js');
const { buildContentSecurityPolicy } = require('./electron/csp.js');

/** Simpan endpoint bila config.js tidak bisa ditulis (asar / izin). Dipakai saat startup + setelah POST. */
function getEndpointOverridePath() {
  const fromEnv = process.env.NEXA_ENDPOINT_OVERRIDE_FILE;
  if (fromEnv && String(fromEnv).trim()) return String(fromEnv).trim();
  return path.join(os.homedir(), '.ekastic', 'config.endpoint.json');
}

function applyEndpointFieldsToConfig(payload) {
  if (!payload || typeof payload !== 'object') return;
  if (payload.urlApi) config.urlApi = payload.urlApi;
  if (payload.drive) config.drive = payload.drive;
  if (payload.rebit !== undefined) config.rebit = payload.rebit;
  if (payload.publik !== undefined) config.publik = payload.publik;
}

function loadEndpointOverrideFromDiskSync() {
  try {
    const p = getEndpointOverridePath();
    if (!fsSync.existsSync(p)) return;
    const raw = fsSync.readFileSync(p, 'utf8');
    const j = JSON.parse(raw);
    applyEndpointFieldsToConfig(j);
  } catch (e) {
    console.warn('[nexa-config] gagal memuat endpoint override:', e && e.message ? e.message : e);
  }
}

loadEndpointOverrideFromDiskSync();

const app = express();

function buildServerConfig() {
  try {
    const baseUrl = config.baseUrl || config.url;
    if (baseUrl) {
      const u = new URL(baseUrl);
      const port = u.port || (u.protocol === 'https:' ? 443 : 80);
      const hostname = u.hostname;
      const host = hostname === 'localhost' || hostname === '127.0.0.1' ? '127.0.0.1' : '0.0.0.0';
      const electronInitialPath =
        typeof config.electronInitialPath === 'string' ? config.electronInitialPath.trim() : '/beranda';
      return {
        host,
        port: parseInt(port, 10),
        hostname,
        baseUrl,
        electronInitialPath,
      };
    }
  } catch (error) {
    console.warn('⚠️ Could not read config.js, using default localhost:3000');
  }
  return {
    host: '127.0.0.1',
    port: 3000,
    hostname: 'localhost',
    baseUrl: 'http://localhost:3000',
    electronInitialPath:
      typeof config.electronInitialPath === 'string' ? config.electronInitialPath.trim() : '/beranda',
  };
}

let SERVER_CONFIG = {
  host: 'localhost',
  port: 3000,
  hostname: 'localhost',
  baseUrl: 'http://localhost:3000',
  electronInitialPath: '/beranda',
};

app.use(cors());
app.use(express.json({ limit: '25mb' }));
app.use(express.urlencoded({ extended: true, limit: '25mb' }));

const GITHUB_API_ORIGIN = 'https://api.github.com';
const GITHUB_PROXY_PASS_HEADERS = [
  'x-oauth-scopes',
  'x-accepted-oauth-scopes',
  'x-accepted-github-permissions',
  'x-ratelimit-limit',
  'x-ratelimit-remaining',
  'x-ratelimit-reset',
  'x-github-request-id',
  'x-github-api-version-selected',
];

function getGithubTokenFromReq(req) {
  const bearer = req.get('authorization') || req.get('Authorization');
  if (typeof bearer === 'string' && /^bearer\s+/i.test(bearer)) {
    return bearer.replace(/^bearer\s+/i, '').trim();
  }
  const headerToken = req.get('x-github-token') || req.get('X-GitHub-Token');
  if (typeof headerToken === 'string' && headerToken.trim()) {
    return headerToken.trim();
  }
  return '';
}

async function githubProxyHandler(req, res) {
  const token = getGithubTokenFromReq(req);
  if (!token) {
    return res.status(401).json({
      success: false,
      message: 'GitHub token tidak ditemukan. Kirim Authorization Bearer atau x-github-token.',
    });
  }

  const subPath = String(req.params.path || '').trim();
  if (!subPath || subPath.includes('..')) {
    return res.status(400).json({ success: false, message: 'Path GitHub API tidak valid.' });
  }

  const query = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : '';
  const targetUrl = `${GITHUB_API_ORIGIN}/${subPath}${query}`;

  const headers = {
    Accept: 'application/vnd.github+json',
    'User-Agent': 'NexaUI-GitSource-Proxy',
    Authorization: `Bearer ${token}`,
  };

  const method = String(req.method || 'GET').toUpperCase();
  const options = { method, headers };

  if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
    headers['Content-Type'] = 'application/json';
    if (req.body && Object.keys(req.body).length > 0) {
      options.body = JSON.stringify(req.body);
    }
  }

  try {
    const upstream = await fetch(targetUrl, options);
    const contentType = upstream.headers.get('content-type') || '';
    const responseText = await upstream.text();

    // Keep useful GitHub headers so frontend can inspect token scopes/permissions.
    for (const headerName of GITHUB_PROXY_PASS_HEADERS) {
      const headerValue = upstream.headers.get(headerName);
      if (headerValue?.length) {
        res.setHeader(headerName, headerValue);
      }
    }

    if (contentType.includes('application/json')) {
      try {
        const data = responseText ? JSON.parse(responseText) : null;
        return res.status(upstream.status).json(data);
      } catch {
        return res.status(upstream.status).type('application/json').send(responseText || '{}');
      }
    }

    return res.status(upstream.status).type(contentType || 'text/plain').send(responseText);
  } catch (error) {
    return res.status(502).json({
      success: false,
      message: `GitHub proxy error: ${error && error.message ? error.message : String(error)}`,
    });
  }
}

app.all('/nexa-github/:path(*)', githubProxyHandler);

// ─── Ollama proxy — pipe langsung tanpa buffering untuk streaming NDJSON ─────
// GET/POST /nexa-ollama/* → http://127.0.0.1:11434/*
const http = require('http');
app.all('/nexa-ollama/*', (req, res) => {
  const subPath = req.path.replace(/^\/nexa-ollama/, '') || '/';
  const query = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : '';
  const options = {
    hostname: '127.0.0.1',
    port: 11434,
    path: subPath + query,
    method: req.method,
    headers: { ...req.headers, host: '127.0.0.1:11434' },
  };
  const proxyReq = http.request(options, (proxyRes) => {
    // Teruskan status dan header langsung (tanpa buffering)
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    // Pipe stream langsung — tidak pernah buffer di memory
    proxyRes.pipe(res, { end: true });
  });
  proxyReq.on('error', (err) => {
    console.error('[nexa-ollama proxy] error:', err.message);
    if (!res.headersSent) {
      res.status(502).json({ error: 'Ollama tidak bisa dihubungi. Pastikan `ollama serve` berjalan.' });
    }
  });
  if (req.body && Object.keys(req.body).length > 0) {
    proxyReq.write(JSON.stringify(req.body));
  } else {
    req.pipe(proxyReq, { end: true });
    return;
  }
  proxyReq.end();
});

const devNoCacheAssets =
  process.env.NODE_ENV !== 'production' && process.env.NEXA_DEV_NO_CACHE !== '0';
app.use((req, res, next) => {
  if (!devNoCacheAssets) {
    return next();
  }
  const p = req.path;
  if (
    p.startsWith('/templates/') ||
    p.startsWith('/assets/') ||
    p.startsWith('/nexa-context/') ||
    p === '/App.js' ||
    p === '/sw.js'
  ) {
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    res.setHeader('Pragma', 'no-cache');
    res.setHeader('Expires', '0');
  }
  next();
});

const staticOpts = devNoCacheAssets
  ? { index: false, etag: false, lastModified: false, maxAge: 0 }
  : { index: false };

const SENSITIVE_PUBLIC_NAMES = new Set([
  'index.js',
  'server.js',
  'node.js',
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
  if (first === 'electron') {
    return res.status(404).end();
  }
  next();
});

function getInternalElectronSecret() {
  const fromEnv = process.env.NEXA_INTERNAL_ELECTRON_SECRET && String(process.env.NEXA_INTERNAL_ELECTRON_SECRET).trim();
  if (fromEnv) return fromEnv;
  const fromCfg =
    config.internalElectronSecret != null && String(config.internalElectronSecret).trim()
      ? String(config.internalElectronSecret).trim()
      : '';
  return fromCfg;
}

function normalizeHostnameForGate(hostname) {
  let h = String(hostname || '').toLowerCase().trim();
  if (h.startsWith('[') && h.includes(']')) {
    h = h.slice(1, h.indexOf(']'));
  }
  return h.split('%')[0];
}

function isLoopbackHostnameForGate(hostname) {
  const h = normalizeHostnameForGate(hostname);
  return h === 'localhost' || h === '127.0.0.1' || h === '::1';
}

/**
 * Hanya klien yang mengirim header internal (disisipkan Electron) — bukan browser biasa.
 * Aktifkan dengan: `electronInternalOnly: true` di config.js + isi `internalElectronSecret`.
 */
app.use((req, res, next) => {
  if (config.electronInternalOnly !== true) {
    return next();
  }
  const secret = getInternalElectronSecret();
  if (!secret) {
    console.error(
      '[Nexa] electronInternalOnly aktif tetapi internalElectronSecret / NEXA_INTERNAL_ELECTRON_SECRET kosong.',
    );
    return res
      .status(503)
      .type('text')
      .send('Konfigurasi server: isi internalElectronSecret atau env NEXA_INTERNAL_ELECTRON_SECRET.\n');
  }
  if (req.method === 'OPTIONS') {
    return next();
  }
  const got = (req.get('x-nexa-electron-internal') || '').trim();
  if (got === secret) {
    return next();
  }
  res
    .status(403)
    .type('text')
    .send('Akses ditolak: halaman ini hanya untuk aplikasi Desktop (bukan browser).\n');
});

/**
 * Opsional: cegah pembukaan app lewat http://IP-LAN:port di browser (config + env).
 * Tidak mengganti firewall; hanya validasi Host. Opsi mati secara default (kompatibel LAN).
 */
app.use((req, res, next) => {
  const enabled =
    config.restrictBrowserToLoopback === true ||
    String(process.env.NEXA_LOCALHOST_ONLY || '').trim() === '1';
  if (!enabled) {
    return next();
  }
  if (req.method === 'OPTIONS') {
    return next();
  }
  const hn = req.hostname || '';
  if (isLoopbackHostnameForGate(hn)) {
    return next();
  }
  res
    .status(403)
    .type('text')
    .send(
      'Akses ditolak: host ini hanya boleh dibuka lewat http://localhost atau http://127.0.0.1 (bukan alamat IP LAN).\n' +
        'Ubah restrictBrowserToLoopback di config.js atau hapus env NEXA_LOCALHOST_ONLY jika akses LAN sengaja diperlukan.\n',
    );
});

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
      const sameHost = upstreamHost && parsed.hostname === upstreamHost;
      if (sameOrigin || sameHost) {
        return clientOrigin + parsed.pathname + parsed.search + parsed.hash;
      }
    } catch {
      /* keep original */
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

function patchReqUrlToOriginal(req, res, next) {
  if (req.originalUrl) req.url = req.originalUrl;
  next();
}

/**
 * Tulis ulang body dari req.body (sudah di-parse oleh express.json) ke upstream request.
 * Wajib karena express.json() mengkonsumsi stream — tanpa ini proxy hang menunggu body.
 *
 * Jangan dipakai untuk multipart/form-data: stream masih mentah untuk upstream; kalau paksa
 * JSON.stringify(req.body) (sering `{}`), PHP dapat `{}` tanpa $_FILES → "No file uploaded".
 */
function fixProxyBody(proxyReq, req) {
  const ct = String(req.headers['content-type'] || '').toLowerCase();
  if (ct.includes('multipart/form-data')) return;
  if (!req.body) return;
  const method = (req.method || '').toUpperCase();
  if (method !== 'POST' && method !== 'PUT' && method !== 'PATCH') return;
  const body = JSON.stringify(req.body);
  proxyReq.setHeader('Content-Type', 'application/json');
  proxyReq.setHeader('Content-Length', Buffer.byteLength(body));
  proxyReq.write(body);
}

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
            proxyReq(proxyReq, req) {
              fixProxyBody(proxyReq, req);
            },
            proxyRes(proxyRes, req, res) {
              rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin);
            },
          },
        });
        app.use('/api', patchReqUrlToOriginal, apiProxy);
        console.log(`🔀 API proxy: /api → ${config.urlApi} (dari config.urlApi)`);

        /* Legacy /upload/* → upstream (multipart cloud pakai /api/cloud/add|update|avatar di NexaStorage) */
        const uploadProxy = createProxyMiddleware({
          target: upstreamOrigin,
          changeOrigin: true,
          on: {
            proxyReq(proxyReq, req) {
              /* multipart: jangan fixProxyBody */
            },
            proxyRes(proxyRes, req, res) {
              rewriteUpstreamRedirectHeaders(proxyRes, req, upstreamOrigin);
            },
          },
        });
        app.use('/upload', patchReqUrlToOriginal, uploadProxy);
        console.log(`🔀 Upload proxy: /upload → ${upstreamOrigin}/upload`);
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
            proxyReq(proxyReq, req) {
              fixProxyBody(proxyReq, req);
            },
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
            proxyReq(proxyReq, req) {
              fixProxyBody(proxyReq, req);
            },
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

/**
 * Jalur khusus Test Endpoint Gateway.
 * Frontend POST ke /nexa-gateway-test dengan body { method, url, headers, body }.
 * Node membuat HTTP request ke target (backend PHP / URL bebas) dan mengembalikan
 * status + body ke frontend — tanpa dibatasi same-origin atau header Electron internal.
 */
app.post('/nexa-gateway-test', express.json(), async (req, res) => {
  const { method = 'GET', url: targetUrl, headers: reqHeaders = {}, body: reqBody } = req.body || {};
  if (!targetUrl || !/^https?:\/\//i.test(targetUrl)) {
    return res.status(400).json({ error: 'URL tidak valid. Harus diawali http:// atau https://' });
  }
  try {
    const fetchOpts = {
      method: String(method).toUpperCase(),
      headers: { ...reqHeaders },
    };
    if (reqBody && !['GET', 'HEAD'].includes(fetchOpts.method)) {
      fetchOpts.body = typeof reqBody === 'string' ? reqBody : JSON.stringify(reqBody);
      if (!fetchOpts.headers['Content-Type'] && !fetchOpts.headers['content-type']) {
        fetchOpts.headers['Content-Type'] = 'application/json';
      }
    }
    const upstream = await fetch(targetUrl, fetchOpts);
    const text = await upstream.text();
    const upstreamHeaders = {};
    upstream.headers.forEach((v, k) => { upstreamHeaders[k] = v; });
    return res.status(200).json({
      status: upstream.status,
      statusText: upstream.statusText,
      headers: upstreamHeaders,
      body: text,
    });
  } catch (err) {
    return res.status(502).json({ error: err.message || String(err) });
  }
});

/** Path ke electron/components di dalam app.asar (produksi) atau proyek (dev). */
function resolveElectronComponentsDir() {
  return path.join(__dirname, 'electron', 'components');
}

app.use('/nexa-context', express.static(resolveElectronComponentsDir(), staticOpts));

function escapeHtmlAttrValue(s) {
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

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
      /* keep original */
    }
  }
  raw.googleClientId = (process.env.GOOGLE_CLIENT_ID || '').trim();
  return raw;
}

async function sendSpaHtml(req, res) {
  const htmlPath = path.join(__dirname, 'index.html');
  let html = await fs.readFile(htmlPath, 'utf8');
  const payload = JSON.stringify(buildClientEndpointPayload(req));
  const csp = buildContentSecurityPolicy(config);
  const cspMeta = `<meta http-equiv="Content-Security-Policy" content="${escapeHtmlAttrValue(csp)}">`;
  const endpointScript = `<script>window.__NEXA_ENDPOINT__=${payload}</script>`;
  const hrefHashScript = `<script>document.addEventListener('click',function(e){var a=e.target.closest('a');if(a&&(a.getAttribute('href')==='#'||a.getAttribute('href')==='javascript:void(0);')){e.preventDefault();}},true);</script>`;
  const onclickCursorStyle = `<style>[onclick]{cursor:pointer;}</style>`;
  const injectHead = `${cspMeta}\n${endpointScript}\n${hrefHashScript}\n${onclickCursorStyle}`;
  if (!html.includes('<head>')) {
    res.type('html').send(html);
    return;
  }
  html = html.replace(/<head[^>]*>/i, (m) => `${m}\n${injectHead}\n`);
  res.type('html').send(html);
}

app.get(['/', '/index.html'], async (req, res, next) => {
  try {
    await sendSpaHtml(req, res);
  } catch (err) {
    next(err);
  }
});

app.use(express.static(path.join(__dirname), staticOpts));

app.use('/assets', express.static(path.join(__dirname, 'assets'), staticOpts));


/**
 * Endpoint: kembalikan isi README.md penuh dari modul assets/modules/{label}/README.md.
 * Dipakai mode Code (UI) dan nanti injeksi konteks model bila perlu dibatasi di sisi pemanggil.
 */
app.get('/nexa-module-doc/:label', async (req, res) => {
  const label = String(req.params.label || '').replace(/[^a-zA-Z0-9_-]/g, '');
  if (!label) return res.status(400).type('text').send('label kosong');
  const readmePath = path.join(__dirname, 'assets', 'modules', label, 'README.md');
  try {
    const content = await fs.readFile(readmePath, 'utf8');
    res.type('text').send(content);
  } catch {
    res.status(404).type('text').send('');
  }
});

/**
 * Workspace Quide: `templates/Workspace/{folder}/README.md` (sama pola folder dengan modul Code).
 */
app.get('/nexa-workspace-quide/:folder', async (req, res) => {
  const folder = String(req.params.folder || '').replace(/[^a-zA-Z0-9_-]/g, '');
  if (!folder) return res.status(400).type('text').send('folder kosong');
  const workspaceRoot = path.join(__dirname, 'templates', 'Workspace');
  const readmePath = path.join(workspaceRoot, folder, 'README.md');
  const rel = path.relative(workspaceRoot, readmePath);
  if (rel.startsWith('..') || path.isAbsolute(rel)) {
    return res.status(400).type('text').send('path invalid');
  }
  try {
    const content = await fs.readFile(readmePath, 'utf8');
    res.type('text/plain; charset=utf-8').send(content);
  } catch {
    res.status(404).type('text').send('');
  }
});

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

// ─── Email helpers ───────────────────────────────────────────────────────────
function createMailTransport() {
  const nodemailer = require('nodemailer');
  return nodemailer.createTransport({
    host: process.env.MAIL_HOST || 'smtp.gmail.com',
    port: parseInt(process.env.MAIL_PORT || '587'),
    secure: false,
    auth: {
      user: process.env.MAIL_USERNAME,
      pass: process.env.MAIL_PASSWORD,
    },
  });
}

// Kirim email aktivasi lisensi
app.post('/nexa-auth/send-activation', async (req, res) => {
  const { email, nama, token } = req.body || {};
  if (!email || !token) {
    return res.status(400).json({ success: false, message: 'email dan token diperlukan' });
  }
  try {
    const transporter = createMailTransport();
    await transporter.sendMail({
      from: `"${process.env.MAIL_FROM_NAME || 'NexaDom'}" <${process.env.MAIL_FROM_ADDRESS || process.env.MAIL_USERNAME}>`,
      to: email,
      subject: 'Aktivasi Lisensi NexaDom Anda',
      html: `
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px">
          <h2 style="color:#2563eb">Selamat Datang di NexaDom!</h2>
          <p>Halo <b>${nama || 'Pengguna'}</b>,</p>
          <p>Terima kasih telah mendaftar. Berikut kunci lisensi Anda:</p>
          <div style="background:#f0f4ff;border:1px solid #b3c9ff;border-radius:6px;padding:16px 24px;text-align:center;margin:20px 0">
            <code style="font-size:22px;letter-spacing:3px;color:#1d4ed8">${token}</code>
          </div>
          <p>Masukkan kunci ini di halaman aktivasi aplikasi NexaDom.</p>
          <p style="color:#888;font-size:12px">Jika Anda tidak mendaftar, abaikan email ini.</p>
        </div>`,
    });
    res.json({ success: true });
  } catch (err) {
    console.error('[send-activation] error:', err.message);
    res.status(500).json({ success: false, message: err.message });
  }
});

// Kirim email reset password
app.post('/nexa-auth/send-reset', async (req, res) => {
  const { email, nama, password } = req.body || {};
  if (!email || !password) {
    return res.status(400).json({ success: false, message: 'email dan password diperlukan' });
  }
  try {
    const transporter = createMailTransport();
    await transporter.sendMail({
      from: `"${process.env.MAIL_FROM_NAME || 'NexaDom'}" <${process.env.MAIL_FROM_ADDRESS || process.env.MAIL_USERNAME}>`,
      to: email,
      subject: 'Reset Password NexaDom Anda',
      html: `
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px">
          <h2 style="color:#2563eb">Reset Password NexaDom</h2>
          <p>Halo <b>${nama || 'Pengguna'}</b>,</p>
          <p>Password sementara Anda adalah:</p>
          <div style="background:#fffbe6;border:1px solid #ffd666;border-radius:6px;padding:16px 24px;text-align:center;margin:20px 0">
            <code style="font-size:22px;letter-spacing:3px;color:#d4380d">${password}</code>
          </div>
          <p>Segera login dan ganti password Anda.</p>
          <p style="color:#888;font-size:12px">Jika Anda tidak meminta reset password, abaikan email ini.</p>
        </div>`,
    });
    res.json({ success: true });
  } catch (err) {
    console.error('[send-reset] error:', err.message);
    res.status(500).json({ success: false, message: err.message });
  }
});
// ─────────────────────────────────────────────────────────────────────────────

// ── Update config (urlApi, drive, rebit, publik) dari UI ─────────────────────
app.post('/nexa-config/update', async (req, res) => {
  const { endpoint } = req.body || {};
  if (!endpoint || typeof endpoint !== 'string') {
    return res.status(400).json({ success: false, message: 'endpoint wajib diisi' });
  }
  const base = endpoint.replace(/\/+$/, '');
  const configPath = path.join(__dirname, 'config.js');
  const overridePayload = {
    urlApi: `${base}/api`,
    drive: `${base}/assets/drive`,
    rebit: `${base}/rebit`,
    publik: base,
  };

  try {
    let src = await fs.readFile(configPath, 'utf8');
    src = src
      .replace(/(urlApi\s*:\s*)['"`][^'"\`]*['"`]/, `$1"${overridePayload.urlApi}"`)
      .replace(/(drive\s*:\s*)['"`][^'"\`]*['"`]/, `$1"${overridePayload.drive}"`)
      .replace(/(rebit\s*:\s*)[`'"][^`'"]*[`'"]/, `$1\`${overridePayload.rebit}\``)
      .replace(/(publik\s*:\s*)[`'"][^`'"]*[`'"]/, `$1\`${base}\``);
    await fs.writeFile(configPath, src, 'utf8');
    applyEndpointFieldsToConfig(overridePayload);
    return res.json({ success: true, message: 'config.js berhasil diupdate', base });
  } catch (err) {
    console.warn('[nexa-config] tulis config.js gagal (sering di app terpaket/read-only):', err.message);
    try {
      const overridePath = getEndpointOverridePath();
      await fs.mkdir(path.dirname(overridePath), { recursive: true });
      await fs.writeFile(overridePath, JSON.stringify(overridePayload, null, 2), 'utf8');
      applyEndpointFieldsToConfig(overridePayload);
      return res.json({
        success: true,
        message:
          'Endpoint disimpan di file override (config.js tidak bisa ditulis). Mulai ulang server jika proxy /api belum ikut.',
        base,
        overridePath,
      });
    } catch (err2) {
      console.error('[nexa-config] update error:', err2.message);
      return res.status(500).json({
        success: false,
        message: 'Gagal menyimpan endpoint: ' + err2.message,
      });
    }
  }
});
// ─────────────────────────────────────────────────────────────────────────────

app.get('/health', (req, res) => {
  res.json({ status: 'healthy', uptime: process.uptime() });
});

/**
 * Jika static tidak menemukan berkas, jangan layani SPA (index.html) untuk jalur aset/modul.
 * Mengembalikan HTML untuk URL seperti /assets/modules/Nexa.js memicu error MIME pada <script type="module">.
 */
function isAssetOrModulePathNoSpaFallback(pathname) {
  const p = typeof pathname === 'string' ? pathname : '';
  if (!p || p === '/') return false;
  if (p.startsWith('/assets/') || p.startsWith('/templates/') || p.startsWith('/nexa-context/')) {
    return true;
  }
  if (
    p === '/App.js' ||
    p === '/sw.js' ||
    p === '/index.js' ||
    p === '/server.js'
  ) {
    return true;
  }
  return /\.(js|mjs|cjs|css|map|json|wasm|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|webp|ico|mp3|mp4|webm|pdf)$/i.test(
    p,
  );
}

app.use((req, res, next) => {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    return next();
  }
  if (isAssetOrModulePathNoSpaFallback(req.path)) {
    return res.status(404).type('text/plain').send('Not found');
  }
  sendSpaHtml(req, res).catch(next);
});

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

function getServerConfig() {
  return SERVER_CONFIG;
}

if (require.main === module) {
  startServer().catch(() => {
    process.exitCode = 1;
  });
}

module.exports = { app, startServer, getServerConfig };
