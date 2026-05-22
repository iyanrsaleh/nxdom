/**
 * Entry CommonJS — pola electronv2: Express di index.js (akar proyek), dimuat dengan require() dari app.asar.
 */
const { app, BrowserWindow, dialog, nativeImage, Menu, session, ipcMain, nativeTheme } = require('electron');
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');
const https = require('https');
const http = require('http');
const WebSocket = require('ws');
let nexaAwaitWsServer = null;
const nexaAwaitWsSubscribers = new Set();

/**
 * APP_ROOT: folder proyek (dev: induk electron/). Terpaket: app.asar (package.json, index.js, node_modules, assets/…).
 * Folder electron/ dipaketkan ke app.asar; bootstrap mendukung fallback resources/electron untuk build lama.
 */
const APP_ROOT = app.isPackaged ? app.getAppPath() : path.join(__dirname, '..');
const ELECTRON_SHELL_JS = path.join(__dirname, 'electronShell.js');

/**
 * Geolocation di Electron memakai webservice Google; tanpa kunci → 403 di DevTools.
 * Prioritas: variabel lingkungan GOOGLE_API_KEY, lalu baris di `.env` di root proyek.
 * @see https://www.electronjs.org/docs/latest/api/environment-variables#google_api_key
 */
function loadGoogleApiKeyForChromium() {
  if (process.env.GOOGLE_API_KEY && String(process.env.GOOGLE_API_KEY).trim()) {
    return;
  }
  try {
    const envPath = path.join(APP_ROOT, '.env');
    if (!fs.existsSync(envPath)) return;
    const raw = fs.readFileSync(envPath, 'utf8');
    const lines = raw.split(/\r?\n/);
    for (const line of lines) {
      const t = line.trim();
      if (!t || t.startsWith('#')) continue;
      const m = t.match(/^GOOGLE_API_KEY\s*=\s*(.*)$/);
      if (!m) continue;
      let v = m[1].trim();
      if (
        (v.startsWith('"') && v.endsWith('"')) ||
        (v.startsWith("'") && v.endsWith("'"))
      ) {
        v = v.slice(1, -1);
      }
      if (v) {
        process.env.GOOGLE_API_KEY = v;
        break;
      }
    }
  } catch {
    /* abaikan */
  }
}
loadGoogleApiKeyForChromium();

function readEnvVarFromDotEnv(key) {
  const k = String(key || '').trim();
  if (!k) return '';
  const candidateEnvFiles = [
    path.join(APP_ROOT, '.env'),
    path.join(process.cwd(), '.env'),
    path.join(path.dirname(process.execPath), '.env'),
  ];
  for (const envPath of candidateEnvFiles) {
    try {
      if (!fs.existsSync(envPath)) continue;
      const raw = fs.readFileSync(envPath, 'utf8');
      const lines = raw.split(/\r?\n/);
      for (const line of lines) {
        const t = line.trim();
        if (!t || t.startsWith('#')) continue;
        const m = t.match(new RegExp(`^${k}\\s*=\\s*(.*)$`));
        if (!m) continue;
        let v = String(m[1] || '').trim();
        if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
          v = v.slice(1, -1);
        }
        if (v) return v;
      }
    } catch (_) {
      /* abaikan sumber .env yang gagal dibaca */
    }
  }
  return '';
}

const allowMultipleInstances = process.env.ELECTRON_SINGLE_INSTANCE !== '1';

/**
 * Hanya panggil setelah `app.whenReady` — `session.defaultSession` tidak tersedia sebelum itu.
 * Geolocation di renderer membutuhkan izin sesi.
 */
function registerSessionPermissionHandlers() {
  session.defaultSession.setPermissionRequestHandler((_webContents, permission, callback) => {
    if (permission === 'geolocation') {
      callback(true);
    } else {
      callback(false);
    }
  });
  session.defaultSession.setPermissionCheckHandler((_webContents, permission) => permission === 'geolocation');
}

/**
 * Sisipkan header yang diverifikasi index.js agar browser biasa tidak bisa membuka 127.0.0.1 / localhost.
 */
function registerElectronInternalRequestHeader() {
  let appConfig;
  try {
    appConfig = require(path.join(APP_ROOT, 'config.js'));
  } catch {
    return;
  }
  if (!appConfig || appConfig.electronInternalOnly !== true) {
    return;
  }
  const secret = String(
    process.env.NEXA_INTERNAL_ELECTRON_SECRET || appConfig.internalElectronSecret || '',
  ).trim();
  if (!secret) {
    console.error(
      '[Electron] electronInternalOnly aktif tetapi internalElectronSecret / NEXA_INTERNAL_ELECTRON_SECRET kosong.',
    );
    return;
  }
  session.defaultSession.webRequest.onBeforeSendHeaders((details, callback) => {
    try {
      const u = new URL(details.url);
      const h = u.hostname.toLowerCase();
      if (h !== '127.0.0.1' && h !== 'localhost' && h !== '[::1]' && h !== '::1') {
        return callback({ requestHeaders: details.requestHeaders });
      }
      const headers = {
        ...details.requestHeaders,
        'X-Nexa-Electron-Internal': secret,
      };
      callback({ requestHeaders: headers });
    } catch {
      callback({ requestHeaders: details.requestHeaders });
    }
  });
}

/** Diisi lewat require() di bootstrap — electronShell.js. */
let buildContextMenuTemplate;

/** Default jika `mainWindowLayout` tidak diekspor / tidak lengkap (sumber utama: electronShell.js). */
const DEFAULT_MAIN_WINDOW_LAYOUT = {
  width: 1280,
  height: 800,
  minWidth: 400,
  minHeight: 300,
  /** false = tidak pasang menu klik kanan kustom + cegah menu default Chromium */
  ContextMenu: true,
  /** true = menu «Console log» / «Inspect Element» (dev & produksi); false = sembunyikan. Sumber: `electronShell.js` */
  toggleDevTools: true,
};

const MAIN_WINDOW_LAYOUT_KEYS = [
  'width',
  'height',
  'minWidth',
  'minHeight',
  'maxWidth',
  'maxHeight',
  'x',
  'y',
  'center',
  'title',
  'resizable',
  'minimizable',
  'maximizable',
  'closable',
  'fullscreenable',
  'alwaysOnTop',
];

/** Hasil normalisasi terakhir dari bootstrap (mode terpaket). */
let mainWindowLayoutResolved = { ...DEFAULT_MAIN_WINDOW_LAYOUT };

function normalizeMainWindowLayout(mod) {
  const base = { ...DEFAULT_MAIN_WINDOW_LAYOUT };
  const raw = mod?.mainWindowLayout;
  if (!raw || typeof raw !== 'object') return base;
  for (const k of MAIN_WINDOW_LAYOUT_KEYS) {
    if (raw[k] !== undefined) base[k] = raw[k];
  }
  if (raw.ContextMenu !== undefined) {
    base.ContextMenu = Boolean(raw.ContextMenu);
  }
  if (raw.toggleDevTools !== undefined) {
    base.toggleDevTools = Boolean(raw.toggleDevTools);
  }
  return base;
}

/**
 * Di development: invalidate require cache agar ubahan electronShell.js terpakai.
 */
function requireElectronShellFresh() {
  if (!app.isPackaged) {
    try {
      delete require.cache[require.resolve(ELECTRON_SHELL_JS)];
    } catch (_) {
      /* abaikan */
    }
  }
  return require(ELECTRON_SHELL_JS);
}

/**
 * Di development: baca ulang electronShell.js agar ubahan ukuran jendela terpakai saat jendela baru dibuat.
 */
async function getMainWindowLayout() {
  if (app.isPackaged) {
    return mainWindowLayoutResolved;
  }
  return normalizeMainWindowLayout(requireElectronShellFresh());
}

/**
 * Di development: require cache dibersihkan saat menu konteks dibuka agar edit shell langsung terasa.
 */
async function getContextMenuBuilder() {
  if (app.isPackaged) {
    return buildContextMenuTemplate;
  }
  return requireElectronShellFresh().buildContextMenuTemplate;
}

/**
 * Satu sumber dengan build.win.icon (package.json) — wajib untuk dev agar title bar & taskbar
 * tidak kembali ke ikon default Electron. Build installer tetap memakai path yang sama.
 */
function resolveWindowIcon() {
  try {
    const pkg = require(path.join(APP_ROOT, 'package.json'));
    const rel = pkg.build && pkg.build.win && pkg.build.win.icon;
    if (rel && typeof rel === 'string') {
      const abs = path.join(APP_ROOT, rel);
      if (fs.existsSync(abs)) {
        return abs;
      }
      console.warn('[Electron] build.win.icon tidak ada di disk — letakkan file di:', abs);
    }
  } catch (e) {
    console.warn('[Electron] Gagal baca package.json untuk icon:', e.message);
  }
  return undefined;
}

let mainWindow = null;

const isDev =
  !app.isPackaged ||
  process.env.NODE_ENV === 'development' ||
  process.env.ELECTRON_DEV === '1';

/** Diset setelah `startServer()` di `createWindow` — dipakai jendela tambahan (route baru). */
let getServerConfigRef = null;

/**
 * Path internal SPA saja (tanpa host/query) — cegah navigasi mencurigakan dari renderer.
 * @param {string} routePath
 * @returns {string|null}
 */
function validateInternalRoutePath(routePath) {
  const s = String(routePath || '').trim();
  if (!s.startsWith('/')) return null;
  if (s.includes('..') || s.includes('\\')) return null;
  const pathOnly = s.split('?')[0].split('#')[0];
  if (!/^\/[a-zA-Z0-9/_-]*$/.test(pathOnly)) return null;
  return pathOnly;
}

/** ID handoff dari renderer (`localStorage`) — hanya token aman untuk query. */
function validateHandoffId(id) {
  const s = String(id || '').trim();
  if (!s || s.length > 128) return null;
  if (!/^[a-zA-Z0-9_-]+$/.test(s)) return null;
  return s;
}

function getNoCacheLoadOpts() {
  return isDev && process.env.ELECTRON_DISABLE_HTTP_CACHE !== '0'
    ? { extraHeaders: 'pragma: no-cache\r\ncache-control: no-cache\r\n' }
    : {};
}

function attachDidFailLoad(browserWindow) {
  if (!browserWindow?.webContents || browserWindow.webContents.isDestroyed()) return;
  // Error codes that mean "server not ready yet" — retry silently before showing dialog.
  const RETRY_CODES = new Set([-102, -6, -7, -21, -100, -101, -105, -106]);
  const MAX_RETRIES = 8;
  const RETRY_DELAY_MS = 800;
  let retryCount = 0;

  browserWindow.webContents.on('did-fail-load', (_event, code, desc, url) => {
    console.error('[Electron] did-fail-load', { code, desc, url });

    // Ignore subframe / aborted navigations
    if (code === 0 || code === -3) return;

    if (RETRY_CODES.has(code) && retryCount < MAX_RETRIES) {
      retryCount++;
      console.warn(`[Electron] Koneksi gagal (${code}), retry ${retryCount}/${MAX_RETRIES} dalam ${RETRY_DELAY_MS}ms…`);
      setTimeout(() => {
        if (!browserWindow.isDestroyed()) {
          browserWindow.webContents.loadURL(url, { extraHeaders: 'pragma: no-cache\r\ncache-control: no-cache\r\n' })
            .catch(() => {/* handled by next did-fail-load */});
        }
      }, RETRY_DELAY_MS);
      return;
    }

    retryCount = 0;
    dialog.showErrorBox(
      'Gagal memuat halaman',
      `${desc}\n${url}\n\nPastikan port di config.js cocok dan tidak dipakai aplikasi lain.`
    );
  });
}

/**
 * Patch layout dari renderer — hanya kunci yang diizinkan + tipe aman.
 * @param {Record<string, *>} raw
 * @returns {Record<string, *>}
 */
function sanitizeRouteWindowLayout(raw) {
  if (!raw || typeof raw !== 'object') return {};
  const out = {};
  for (const k of MAIN_WINDOW_LAYOUT_KEYS) {
    if (raw[k] === undefined) continue;
    const v = raw[k];
    if (['width', 'height', 'minWidth', 'minHeight', 'maxWidth', 'maxHeight', 'x', 'y'].includes(k)) {
      const n = Number(v);
      if (Number.isFinite(n)) out[k] = Math.round(n);
    } else if (k === 'title') {
      if (typeof v === 'string' && v.length <= 500) out[k] = v;
    } else if (
      ['resizable', 'minimizable', 'maximizable', 'closable', 'fullscreenable', 'alwaysOnTop', 'center'].includes(
        k
      )
    ) {
      out[k] = Boolean(v);
    }
  }
  return out;
}

/**
 * Gabungkan `electronShell` layout dengan patch opsional (jendela route / sekunder).
 * @param {Record<string, *>|null|undefined} layoutPatch
 */
async function resolveShellLayout(layoutPatch) {
  const base = await getMainWindowLayout();
  const merged = { ...base };
  if (layoutPatch && typeof layoutPatch === 'object') {
    Object.assign(merged, sanitizeRouteWindowLayout(layoutPatch));
    if (layoutPatch.ContextMenu !== undefined) {
      merged.ContextMenu = Boolean(layoutPatch.ContextMenu);
    }
    if (layoutPatch.toggleDevTools !== undefined) {
      merged.toggleDevTools = Boolean(layoutPatch.toggleDevTools);
    }
  }
  return merged;
}

/**
 * Jendela baru dengan layout + preload + menu konteks (sama jendela utama, bisa di-patch).
 * @param {Record<string, *>|null} [layoutPatch]
 * @returns {Promise<import('electron').BrowserWindow>}
 */
async function createShellBrowserWindow(layoutPatch = null) {
  const iconPath = resolveWindowIcon();
  let windowIcon;
  if (iconPath) {
    try {
      const img = nativeImage.createFromPath(iconPath);
      windowIcon = img.isEmpty() ? iconPath : img;
    } catch (e) {
      console.warn('[Electron] Gagal memuat icon:', e?.message || e);
      windowIcon = iconPath;
    }
  }

  const layout = await resolveShellLayout(layoutPatch);
  const {
    ContextMenu: contextMenuEnabled = true,
    toggleDevTools: contextMenuDevTools = true,
    ...winLayout
  } = layout;

  const browserWindow = new BrowserWindow({
    ...winLayout,
    show: false,
    ...(windowIcon ? { icon: windowIcon } : {}),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      nativeWindowOpen: true,
    },
  });

  attachPageContextMenu(contextMenuEnabled, contextMenuDevTools, browserWindow);
  return browserWindow;
}

/**
 * Buka route SPA di jendela Electron baru (IPC dari renderer).
 * @param {string} routePath mis. `/ds/data`
 * @param {Record<string, *>|null} [layoutPatch]
 */
async function openRouteInNewWindow(routePath, layoutPatch = null, handoffId = null) {
  if (!getServerConfigRef) {
    console.warn('[Electron] openRouteInNewWindow: server belum siap');
    return { ok: false, error: 'server-not-ready' };
  }
  const validated = validateInternalRoutePath(routePath);
  if (!validated) {
    return { ok: false, error: 'invalid-path' };
  }
  const cfg = getServerConfigRef();
  let loadUrl = toLoadUrl(cfg.baseUrl);
  loadUrl = appendInitialPath(loadUrl, validated);
  const hid = validateHandoffId(handoffId);
  if (hid) {
    try {
      const u = new URL(loadUrl);
      u.searchParams.set('nexaHandoff', hid);
      loadUrl = u.href;
    } catch (e) {
      console.warn('[Electron] Gagal menambah nexaHandoff ke URL:', e?.message || e);
    }
  }
  console.log('[Electron] Jendela baru memuat', loadUrl);

  let childWindow;
  try {
    childWindow = await createShellBrowserWindow(layoutPatch);
  } catch (e) {
    console.error('[Electron] Gagal membuat jendela:', e);
    return { ok: false, error: String(e?.message || e) };
  }

  childWindow.once('ready-to-show', () => {
    childWindow.show();
    childWindow.focus();
  });
  attachDidFailLoad(childWindow);

  try {
    await childWindow.loadURL(loadUrl, getNoCacheLoadOpts());
  } catch (err) {
    console.error('[Electron] loadURL (jendela baru)', err);
    if (!childWindow.isDestroyed()) {
      childWindow.close();
    }
    return { ok: false, error: String(err?.message || err) };
  }

  if (process.env.ELECTRON_DEV === '1') {
    childWindow.webContents.openDevTools({ mode: 'detach' });
  }

  return { ok: true };
}

function parseOpenRouteWindowPayload(payload) {
  if (typeof payload === 'string') {
    return { routePath: payload, layout: null, handoffId: null };
  }
  if (payload && typeof payload === 'object') {
    const routePath = payload.routePath ?? payload.path;
    const layout =
      payload.layout && typeof payload.layout === 'object' ? payload.layout : null;
    const handoffId =
      payload.handoffId != null && String(payload.handoffId).trim()
        ? String(payload.handoffId).trim()
        : null;
    return { routePath, layout, handoffId };
  }
  return { routePath: null, layout: null, handoffId: null };
}

function registerOpenRouteWindowIpc() {
  ipcMain.removeHandler('nexa-open-route-window');
  ipcMain.handle('nexa-open-route-window', async (_event, payload) => {
    try {
      const { routePath, layout, handoffId } = parseOpenRouteWindowPayload(payload);
      return await openRouteInNewWindow(routePath, layout, handoffId);
    } catch (e) {
      return { ok: false, error: String(e?.message || e) };
    }
  });
}

/**
 * Device ID stabil berbasis MAC address + hostname, di-hash SHA-256.
 * Dipakai untuk validasi lisensi agar satu license_key
 * hanya bisa digunakan pada perangkat yang sama.
 */
function getDeviceId() {
  const interfaces = os.networkInterfaces();
  let mac = '';
  for (const iface of Object.values(interfaces)) {
    for (const addr of iface) {
      if (!addr.internal && addr.mac && addr.mac !== '00:00:00:00:00:00') {
        mac = addr.mac;
        break;
      }
    }
    if (mac) break;
  }
  const raw = `${os.hostname()}::${mac}::${process.platform}`;
  return crypto.createHash('sha256').update(raw).digest('hex');
}

function getAppId() {
  try {
    const pkgPath = path.join(APP_ROOT, 'package.json');
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
    return pkg.name || app.getName();
  } catch {
    return app.getName();
  }
}

function fetchGoogleUserInfo(accessToken) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'www.googleapis.com',
      path: '/oauth2/v2/userinfo',
      method: 'GET',
      headers: { Authorization: `Bearer ${accessToken}` },
    };
    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          const user = JSON.parse(data);
          if (user.error) { reject(new Error(user.error.message || 'userinfo-error')); return; }
          resolve({ name: user.name, email: user.email, picture: user.picture });
        } catch (e) { reject(e); }
      });
    });
    req.on('error', reject);
    req.end();
  });
}

function startOAuthCallbackServer() {
  return new Promise((resolve, reject) => {
    let resolveCode;
    const waitForCode = new Promise((res) => { resolveCode = res; });
    const server = http.createServer((req, res) => {
      try {
        const u = new URL(req.url, `http://127.0.0.1`);
        const code  = u.searchParams.get('code')  || undefined;
        const error = u.searchParams.get('error') || undefined;
        const state = u.searchParams.get('state') || undefined;
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end('<html><body><h2 style="font-family:sans-serif">Login berhasil — silakan tutup tab ini.</h2></body></html>');
        resolveCode({ code, error, state });
      } catch (e) {
        res.writeHead(500).end();
        resolveCode({ error: e.message });
      }
    });
    server.listen(0, '127.0.0.1', () => {
      const port = server.address().port;
      resolve({ server, port, waitForCode });
    });
    server.on('error', reject);
  });
}

function registerGoogleSignInIpc() {
  ipcMain.removeHandler('nexa-get-google-client-id');
  ipcMain.handle('nexa-get-google-client-id', async () => {
    try {
      const fromEnv = String(process.env.GOOGLE_CLIENT_ID || '').trim();
      if (fromEnv) return { ok: true, clientId: fromEnv, source: 'process.env.GOOGLE_CLIENT_ID' };
      let appConfig;
      try { appConfig = require(path.join(APP_ROOT, 'config.js')); } catch { appConfig = {}; }
      const fromConfig = String(
        appConfig.googleClientId || appConfig.googleClientID || appConfig.GOOGLE_CLIENT_ID || '',
      ).trim();
      if (fromConfig) return { ok: true, clientId: fromConfig, source: 'config.js' };
      const fromDotEnv = String(readEnvVarFromDotEnv('GOOGLE_CLIENT_ID') || '').trim();
      if (fromDotEnv) return { ok: true, clientId: fromDotEnv, source: '.env' };
      return {
        ok: false,
        error: 'google-client-id-not-found',
        clientId: '',
        source: 'none',
        hints: [
          'Set GOOGLE_CLIENT_ID in system environment',
          'Add googleClientId in config.js',
          'Add GOOGLE_CLIENT_ID in .env near executable or project root',
        ],
      };
    } catch (e) {
      return { ok: false, error: e?.message || String(e), clientId: '', source: 'exception' };
    }
  });

  ipcMain.removeHandler('nexa-google-signin');
  ipcMain.handle('nexa-google-signin', async (_event, clientId) => {
    if (!clientId || typeof clientId !== 'string' || clientId.trim() === '') {
      return { ok: false, error: 'client-id-required' };
    }
    let callbackServer = null;
    let win = null;
    try {
      const verifier  = crypto.randomBytes(32).toString('base64url');
      const challenge = crypto.createHash('sha256').update(verifier).digest('base64url');
      const state     = crypto.randomBytes(16).toString('hex');
      const { server, port, waitForCode } = await startOAuthCallbackServer();
      callbackServer = server;
      const redirectUri = `http://127.0.0.1:${port}`;
      let appConfig;
      try { appConfig = require(path.join(APP_ROOT, 'config.js')); } catch { appConfig = {}; }
      const clientSecret = appConfig.googleClientSecret || '';
      const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' +
        new URLSearchParams({
          client_id: clientId, redirect_uri: redirectUri, response_type: 'code',
          scope: 'openid email profile', code_challenge: challenge,
          code_challenge_method: 'S256', state, access_type: 'online', prompt: 'select_account',
        }).toString();
      win = new BrowserWindow({
        width: 480, height: 640, show: true, autoHideMenuBar: true,
        title: 'Masuk dengan Google',
        parent: mainWindow && !mainWindow.isDestroyed() ? mainWindow : undefined,
        modal: false,
        webPreferences: { nodeIntegration: false, contextIsolation: true, partition: `persist:google-oauth-${Date.now()}` },
      });
      const windowClosed = new Promise((res) => { win.on('closed', () => res({ error: 'window-closed' })); });
      win.loadURL(authUrl).catch((e) => console.warn('[OAuth] loadURL error:', e.message));
      const result = await Promise.race([waitForCode, windowClosed]);
      try { if (win && !win.isDestroyed()) win.close(); } catch (_) {}
      try { callbackServer.close(); } catch (_) {}
      callbackServer = null;
      if (result.error) return { ok: false, error: result.error };
      if (!result.code || result.state !== state) return { ok: false, error: 'invalid-state' };
      const postBody = new URLSearchParams({
        code: result.code, client_id: clientId, redirect_uri: redirectUri,
        grant_type: 'authorization_code', code_verifier: verifier,
        ...(clientSecret ? { client_secret: clientSecret } : {}),
      }).toString();
      const tokens = await new Promise((resolve, reject) => {
        const tokenOptions = {
          hostname: 'oauth2.googleapis.com', path: '/token', method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(postBody) },
        };
        const req = https.request(tokenOptions, (res) => {
          let data = '';
          res.on('data', (c) => { data += c; });
          res.on('end', () => { try { resolve(JSON.parse(data)); } catch (e) { reject(e); } });
        });
        req.on('error', reject);
        req.write(postBody);
        req.end();
      });
      if (tokens.error) return { ok: false, error: tokens.error_description || tokens.error };
      const user = await fetchGoogleUserInfo(tokens.access_token);
      return { ok: true, user };
    } catch (e) {
      try { if (win && !win.isDestroyed()) win.close(); } catch (_) {}
      try { if (callbackServer) callbackServer.close(); } catch (_) {}
      return { ok: false, error: e.message };
    }
  });
}

function registerDeviceIdIpc() {
  ipcMain.removeHandler('nexa-get-device-id');
  ipcMain.handle('nexa-get-device-id', () => getDeviceId());
  ipcMain.removeHandler('nexa-get-app-id');
  ipcMain.handle('nexa-get-app-id', () => getAppId());
}

/**
 * Sinkronkan tema bawaan OS (title bar / scrollbar / native dialog) dengan
 * preferensi dark-mode di renderer. Dipanggil saat user men-toggle "Mode Visual"
 * pada halaman Account, atau saat startup berdasarkan localStorage.
 *
 * Nilai diizinkan: 'system' | 'light' | 'dark'.
 * @see https://www.electronjs.org/docs/latest/api/native-theme#nativethemethemesource
 */
function applyNativeTheme(source) {
  const allowed = new Set(['system', 'light', 'dark']);
  const value = allowed.has(source) ? source : 'system';
  try {
    nativeTheme.themeSource = value;
  } catch (e) {
    console.warn('[Electron] Gagal set nativeTheme.themeSource:', e?.message || e);
    return { ok: false, error: String(e?.message || e) };
  }
  return {
    ok: true,
    themeSource: nativeTheme.themeSource,
    shouldUseDarkColors: nativeTheme.shouldUseDarkColors,
  };
}

function registerNativeThemeIpc() {
  ipcMain.removeHandler('nexa-set-native-theme');
  ipcMain.handle('nexa-set-native-theme', (_event, source) => applyNativeTheme(source));
  ipcMain.removeHandler('nexa-get-native-theme');
  ipcMain.handle('nexa-get-native-theme', () => ({
    themeSource: nativeTheme.themeSource,
    shouldUseDarkColors: nativeTheme.shouldUseDarkColors,
  }));
}

function resolveAwaitTransport() {
  if (typeof globalThis?.nexaAwaitTransport === 'function') {
    return globalThis.nexaAwaitTransport;
  }
  if (globalThis?.nexaAwaitTransport && typeof globalThis.nexaAwaitTransport.post === 'function') {
    return globalThis.nexaAwaitTransport;
  }
  if (typeof globalThis?.__nexaAwaitDispatch === 'function') {
    return ({ endpoint, payload }) => globalThis.__nexaAwaitDispatch({ endpoint, payload });
  }
  return {
    post: async (endpoint, payload) => executeInternalAwaitTransport(endpoint, payload),
  };
}

function loadAwaitConfig() {
  const envRaw = process.env.NEXA_AWAIT_CONFIG || '';
  if (envRaw.trim()) {
    try {
      return JSON.parse(envRaw);
    } catch (error) {
      console.warn('[NexaAwait] NEXA_AWAIT_CONFIG invalid JSON:', error?.message || error);
    }
  }
  return {
    sqlite3: {
      filename: process.env.NEXA_SQLITE_FILE || 'userData:nexa.sqlite',
    },
    websocket: {
      url: process.env.NEXA_WS_URL || 'ws://127.0.0.1:4117',
      timeoutMs: Number(process.env.NEXA_WS_TIMEOUT || 10000),
    },
  };
}

async function executeSqlite3(payload, config) {
  const sql = String(payload?.sqlPlain || payload?.sql || '').trim();
  if (!sql && String(payload?.type || '').toLowerCase() !== 'upload') {
    throw new Error('SQL sqlite3 kosong.');
  }
  const sqlite3 = require('sqlite3').verbose();
  const payloadConnection =
    payload?.connection && typeof payload.connection === 'object' ? payload.connection : {};
  const resolvedConfig = {
    ...(config || {}),
    ...payloadConnection,
  };
  const rawFilename = String(resolvedConfig?.filename || 'userData:nexa.sqlite');
  let filename = '';
  if (rawFilename.startsWith('userData:')) {
    filename = path.join(app.getPath('userData'), rawFilename.slice('userData:'.length));
  } else if (path.isAbsolute(rawFilename)) {
    filename = rawFilename;
  } else {
    filename = path.join(app.getPath('userData'), rawFilename);
  }
  fs.mkdirSync(path.dirname(filename), { recursive: true });
  const bindingsRaw = Array.isArray(payload?.bindings) ? payload.bindings : [];
  const placeholderCount = (sql.match(/\?/g) || []).length;
  const bindings = placeholderCount > 0 ? bindingsRaw.slice(0, placeholderCount) : [];

  return await new Promise((resolve, reject) => {
    const db = new sqlite3.Database(filename, (openErr) => {
      if (openErr) {
        reject(openErr);
      }
    });

    const closeWith = (fn) => {
      db.close((closeErr) => {
        if (closeErr) return reject(closeErr);
        fn();
      });
    };
    const runInsert = (done) => {
      db.run(sql, bindings, function (err) {
        if (err) return done(err);
        return done(null, { changes: this.changes, lastID: this.lastID });
      });
    };
    const inferSqliteType = (value) => {
      if (Buffer.isBuffer(value)) return 'BLOB';
      if (typeof value === 'number') return Number.isInteger(value) ? 'INTEGER' : 'REAL';
      if (typeof value === 'boolean') return 'INTEGER';
      return 'TEXT';
    };
    const ensureTableForInsert = (tableName, data, done) => {
      if (!tableName || !data || typeof data !== 'object') {
        done(new Error('Gagal auto-create tabel: payload data tidak valid.'));
        return;
      }
      const entries = Object.entries(data);
      if (!entries.length) {
        done(new Error('Gagal auto-create tabel: data insert kosong.'));
        return;
      }
      const safeTable = String(tableName).replace(/[^a-zA-Z0-9_]/g, '');
      if (!safeTable) {
        done(new Error('Gagal auto-create tabel: nama tabel tidak valid.'));
        return;
      }
      const cols = entries.map(([key, value]) => {
        const safeKey = String(key).replace(/[^a-zA-Z0-9_]/g, '');
        return `"${safeKey}" ${inferSqliteType(value)}`;
      });
      const createSql = `CREATE TABLE IF NOT EXISTS "${safeTable}" (id INTEGER PRIMARY KEY AUTOINCREMENT, ${cols.join(', ')})`;
      db.run(createSql, [], (createErr) => done(createErr));
    };
    const noSuchTableRegex = /no such table:\s*([a-zA-Z0-9_]+)/i;
    const buildUploadRow = (data) => {
      const row = {};
      Object.entries(data || {}).forEach(([key, value]) => {
        const safeKey = String(key).replace(/[^a-zA-Z0-9_]/g, '');
        if (!safeKey) return;
        if (value && typeof value === 'object' && !Array.isArray(value)) {
          const maybeUrl = String(value.url || '');
          const base64Match = maybeUrl.match(/^data:([^;]+);base64,(.+)$/);
          if (base64Match) {
            row[`${safeKey}_blob`] = Buffer.from(base64Match[2], 'base64');
            row[`${safeKey}_type`] = String(value.type || base64Match[1] || '');
            row[`${safeKey}_name`] = String(value.name || '');
            row[`${safeKey}_size`] = Number(value.size || row[`${safeKey}_blob`].length || 0);
            row[safeKey] = JSON.stringify({
              name: String(value.name || ''),
              type: String(value.type || base64Match[1] || ''),
              size: Number(value.size || row[`${safeKey}_blob`].length || 0),
            });
            return;
          }
        }
        row[safeKey] = value;
      });
      return row;
    };
    const insertFromData = (tableName, data, done) => {
      const row = buildUploadRow(data);
      const entries = Object.entries(row || {});
      if (!entries.length) {
        done(new Error('Data upload kosong.'));
        return;
      }
      const safeTable = String(tableName || '').replace(/[^a-zA-Z0-9_]/g, '');
      if (!safeTable) {
        done(new Error('Nama tabel upload tidak valid.'));
        return;
      }
      const cols = entries.map(([key]) => `"${String(key).replace(/[^a-zA-Z0-9_]/g, '')}"`);
      const placeholders = entries.map(() => '?').join(', ');
      const values = entries.map(([, value]) => value);
      const insertSql = `INSERT INTO "${safeTable}" (${cols.join(', ')}) VALUES (${placeholders})`;
      db.run(insertSql, values, function (err) {
        if (err) return done(err);
        done(null, { changes: this.changes, lastID: this.lastID, sql: insertSql });
      });
    };

    const type = String(payload?.type || '').toLowerCase();
    if (type === 'upload') {
      const tableName = String(payload?.tableName || '').trim();
      insertFromData(tableName, payload?.data || {}, (err, result) => {
        if (!err) {
          closeWith(() => resolve({ ok: true, mode: 'sqlite3', type, sql: result.sql, data: { changes: result.changes, lastID: result.lastID } }));
          return;
        }
        const match = String(err?.message || '').match(noSuchTableRegex);
        if (match?.[1]) {
          ensureTableForInsert(match[1], buildUploadRow(payload?.data || {}), (createErr) => {
            if (createErr) return closeWith(() => reject(createErr));
            insertFromData(match[1], payload?.data || {}, (retryErr, retryResult) => {
              if (retryErr) return closeWith(() => reject(retryErr));
              closeWith(() => resolve({ ok: true, mode: 'sqlite3', type, sql: retryResult.sql, autoCreatedTable: match[1], data: { changes: retryResult.changes, lastID: retryResult.lastID } }));
            });
          });
          return;
        }
        closeWith(() => reject(err));
      });
      return;
    }

    if (type === 'insert' || type === 'update' || type === 'delete') {
      runInsert((err, result) => {
        if (!err) {
          closeWith(() => resolve({ ok: true, mode: 'sqlite3', type, sql, data: result }));
          return;
        }
        const match = String(err?.message || '').match(noSuchTableRegex);
        if (type === 'insert' && match?.[1]) {
          ensureTableForInsert(match[1], payload?.data, (createErr) => {
            if (createErr) return closeWith(() => reject(createErr));
            runInsert((retryErr, retryResult) => {
              if (retryErr) return closeWith(() => reject(retryErr));
              closeWith(() => resolve({ ok: true, mode: 'sqlite3', type, sql, autoCreatedTable: match[1], data: retryResult }));
            });
          });
          return;
        }
        closeWith(() => reject(err));
      });
      return;
    }

    if (type === 'first' || type === 'exists' || type === 'count') {
      db.get(sql, bindings, (err, row) => {
        if (err) return closeWith(() => reject(err));
        closeWith(() => resolve({ ok: true, mode: 'sqlite3', type, sql, data: row || null }));
      });
      return;
    }

    db.all(sql, bindings, (err, rows) => {
      if (err) return closeWith(() => reject(err));
      closeWith(() => resolve({ ok: true, mode: 'sqlite3', type: type || 'select', sql, data: rows || [] }));
    });
  });
}

async function executeWebSocket(payload, config) {
  const url = String(config?.url || 'ws://127.0.0.1:4117');
  const timeoutMs = Number(config?.timeoutMs || 10000);
  return await new Promise((resolve, reject) => {
    let settled = false;
    const ws = new WebSocket(url);
    const timer = setTimeout(() => {
      if (settled) return;
      settled = true;
      try { ws.close(); } catch (_) {}
      reject(new Error(`WebSocket timeout (${timeoutMs} ms) ke ${url}`));
    }, timeoutMs);
    ws.on('open', () => ws.send(JSON.stringify(payload)));
    ws.on('message', (raw) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      try { ws.close(); } catch (_) {}
      try {
        const text = String(raw);
        resolve({ ok: true, mode: 'websocket', data: JSON.parse(text), raw: text });
      } catch {
        resolve({ ok: true, mode: 'websocket', data: null, raw: String(raw) });
      }
    });
    ws.on('error', (err) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      reject(err);
    });
  });
}

function registerAwaitWebSocketServer() {
  if (nexaAwaitWsServer) return;
  const cfg = loadAwaitConfig();
  const wsUrl = String(cfg?.websocket?.url || 'ws://127.0.0.1:4117');
  let port = 4117;
  try {
    const parsed = new URL(wsUrl);
    port = Number(parsed.port || 4117);
  } catch (_) {}
  const WebSocketServer = WebSocket.WebSocketServer;
  nexaAwaitWsServer = new WebSocketServer({ host: '127.0.0.1', port });
  nexaAwaitWsServer.on('connection', (socket) => {
    socket.on('close', () => {
      for (const sub of Array.from(nexaAwaitWsSubscribers)) {
        if (sub.socket === socket) nexaAwaitWsSubscribers.delete(sub);
      }
    });
    socket.on('message', async (raw) => {
      try {
        const packet = JSON.parse(String(raw || '{}'));
        if (String(packet?.type || '').toLowerCase() === 'subscribe-select') {
          const backendMode = String(packet?.backendMode || 'sqlite3').toLowerCase();
          const query = packet?.query && typeof packet.query === 'object' ? packet.query : {};
          const subscriber = { socket, backendMode, query };
          nexaAwaitWsSubscribers.add(subscriber);
          const initial = await executeInternalAwaitTransport('Fetch', { ...query, mode: backendMode });
          socket.send(JSON.stringify({ ok: true, mode: 'websocket', event: 'snapshot', data: initial }));
          return;
        }
        const backendMode = String(packet?.backendMode || 'sqlite3').toLowerCase();
        const forwardedPacket = { ...packet, mode: backendMode, __skipBroadcast: true };
        const response = await executeInternalAwaitTransport('Fetch', forwardedPacket);
        socket.send(JSON.stringify(response));
        const reqType = String(packet?.type || '').toLowerCase();
        if (response?.ok && ['insert', 'update', 'delete', 'upload'].includes(reqType)) {
          await broadcastAwaitRealtimeUpdates({ ...packet, mode: backendMode });
        }
      } catch (error) {
        socket.send(JSON.stringify({ ok: false, error: String(error?.message || error) }));
      }
    });
  });
  nexaAwaitWsServer.on('error', (error) => {
    console.warn('[NexaAwait WS] server error:', error?.message || error);
  });
}

async function broadcastAwaitRealtimeUpdates(changedPayload = {}) {
  if (!nexaAwaitWsSubscribers.size) return;
  for (const sub of Array.from(nexaAwaitWsSubscribers)) {
    try {
      if (!sub?.socket || sub.socket.readyState !== WebSocket.OPEN) {
        nexaAwaitWsSubscribers.delete(sub);
        continue;
      }
      const snapshot = await executeInternalAwaitTransport('Fetch', {
        ...(sub.query || {}),
        mode: String(sub.backendMode || 'sqlite3'),
      });
      sub.socket.send(JSON.stringify({
        ok: true,
        mode: 'websocket',
        event: 'snapshot',
        changedType: String(changedPayload?.type || ''),
        changedTable: String(changedPayload?.tableName || ''),
        data: snapshot,
      }));
    } catch (error) {
      try {
        sub.socket.send(JSON.stringify({
          ok: false,
          mode: 'websocket',
          event: 'snapshot-error',
          error: String(error?.message || error),
        }));
      } catch (_) {}
    }
  }
}

async function executeInternalAwaitTransport(endpoint, payload) {
  const mode = String(payload?.mode || 'sqlite3').toLowerCase();
  const config = loadAwaitConfig();
  const packet = { ...(payload || {}), endpoint };
  let result = null;
  if (mode === 'sqlite3') result = await executeSqlite3(packet, config.sqlite3 || {});
  else if (mode === 'websocket') result = await executeWebSocket(packet, config.websocket || {});
  else {
    return {
      ok: false,
      error: `Mode transport tidak didukung: ${mode}. Gunakan sqlite3/websocket.`,
      endpoint,
      payload,
    };
  }
  const type = String(packet?.type || '').toLowerCase();
  if (result?.ok && ['insert', 'update', 'delete', 'upload'].includes(type) && !packet?.__skipBroadcast) {
    await broadcastAwaitRealtimeUpdates(packet);
  }
  return result;
}

function registerAwaitDispatchIpc() {
  ipcMain.removeHandler('nexa-await-dispatch');
  ipcMain.handle('nexa-await-dispatch', async (_event, packet) => {
    try {
      const endpoint = packet?.endpoint ?? 'Fetch';
      const payload = packet?.payload ?? {};
      const transport = resolveAwaitTransport();
      if (!transport) {
        return {
          ok: false,
          error:
            'Internal await transport belum terpasang di main process. Set globalThis.nexaAwaitTransport atau globalThis.__nexaAwaitDispatch.',
          endpoint,
          payload,
        };
      }
      if (typeof transport === 'function') {
        return await transport({ endpoint, payload });
      }
      return await transport.post(endpoint, payload);
    } catch (error) {
      return { ok: false, error: String(error?.message || error) };
    }
  });
}

/**
 * Membersihkan cache HTTP + storage (setara electronv2/main.js).
 */
async function clearAllCache(options = {}) {
  const {
    clearCache = true,
    clearStorage = true,
    storages = [
      'appcache',
      'serviceworkers',
      'cachestorage',
      'filesystem',
      'indexdb',
      'localstorage',
      'shadercache',
      'websql',
    ],
  } = options;

  const results = {
    cache: { success: false, error: null },
    storage: { success: false, error: null, cleared: [] },
  };

  try {
    if (clearCache) {
      try {
        await session.defaultSession.clearCache();
        results.cache.success = true;
        console.log('✅ HTTP cache cleared');
      } catch (error) {
        results.cache.error = error.message;
        console.error('❌ Failed to clear HTTP cache:', error.message);
      }
    }

    if (clearStorage) {
      try {
        await session.defaultSession.clearStorageData({ storages });
        results.storage.success = true;
        results.storage.cleared = storages;
        console.log('✅ Storage data cleared:', storages.join(', '));
      } catch (error) {
        results.storage.error = error.message;
        console.error('❌ Failed to clear storage data:', error.message);
      }
    }

    try {
      await session.defaultSession.clearHostResolverCache();
    } catch (error) {
      console.warn('⚠️ Failed to clear host resolver cache:', error.message);
    }

    return {
      success: results.cache.success || results.storage.success,
      results,
      timestamp: new Date().toISOString(),
    };
  } catch (error) {
    console.error('❌ Error clearing cache:', error.message);
    return {
      success: false,
      error: error.message,
      results,
      timestamp: new Date().toISOString(),
    };
  }
}

const CLEAR_CACHE_STORAGES = [
  'appcache',
  'serviceworkers',
  'cachestorage',
  'filesystem',
  'indexdb',
  'localstorage',
  'shadercache',
  'websql',
];

async function clearCacheAndNotify() {
  try {
    const result = await clearAllCache({
      clearCache: true,
      clearStorage: true,
      storages: CLEAR_CACHE_STORAGES,
    });
    if (result.success) {
      console.log('✅ Cache cleared successfully');
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send('cache-cleared', {
          success: true,
          timestamp: new Date().toISOString(),
        });
      }
    } else {
      console.error('❌ Failed to clear cache:', result.error);
    }
  } catch (error) {
    console.error('❌ Error clearing cache:', error.message);
  }
}

function showAboutDialog() {
  let name = 'Eletron';
  let version = '';
  try {
    const pkg = require(path.join(APP_ROOT, 'package.json'));
    name = pkg.productName || pkg.name || name;
    version = pkg.version || '';
  } catch {
    // ignore
  }
  const parent =
    BrowserWindow.getFocusedWindow() ||
    (mainWindow && !mainWindow.isDestroyed() ? mainWindow : undefined);
  dialog.showMessageBox(parent, {
    type: 'info',
    title: 'Tentang',
    message: name,
    detail: version ? `Versi ${version}` : undefined,
  });
}

/** Menu klik kanan: template di electronShell.js (mati jika mainWindowLayout.ContextMenu === false). */
function attachPageContextMenu(enabled, toggleDevTools, browserWindow) {
  const win = browserWindow;
  if (!win || win.isDestroyed()) return;
  win.webContents.on('context-menu', async (event, _params) => {
    if (!enabled) {
      event.preventDefault();
      return;
    }
    try {
      const builder = await getContextMenuBuilder();
      const template = builder({
        getMainWindow: () => win,
        toggleDevTools,
        clearCacheAndNotify,
        showAboutDialog,
        contextMenuParams: _params,
      });
      Menu.buildFromTemplate(template).popup({ window: win });
    } catch (err) {
      console.error('[Electron] Gagal memuat electronShell.js:', err);
    }
  });
}

if (process.env.ELECTRON_DISABLE_GPU !== '0') {
  app.commandLine.appendSwitch('disable-gpu');
  app.commandLine.appendSwitch('disable-gpu-sandbox');
  app.disableHardwareAcceleration();
}

// Chromium: matikan cache HTTP disk agar perubahan /templates & /assets langsung terbaca (set ELECTRON_DISABLE_HTTP_CACHE=0 untuk menonaktifkan ini)
if (process.env.ELECTRON_DISABLE_HTTP_CACHE !== '0') {
  app.commandLine.appendSwitch('disable-http-cache');
}

// Windows: ikon taskbar / grouping (selaras appId di electron-builder)
if (process.platform === 'win32') {
  app.setAppUserModelId(app.isPackaged ? 'com.electron.dashboard' : 'com.electron.dashboard.dev');
}

function toLoadUrl(baseUrl) {
  try {
    const u = new URL(baseUrl);
    const h = u.hostname.toLowerCase();
    if (h === 'localhost' || h === '::1') {
      u.hostname = '127.0.0.1';
    }
    return u.href;
  } catch {
    return baseUrl;
  }
}

function appendInitialPath(baseHref, pathSeg) {
  if (pathSeg == null || pathSeg === '') return baseHref;
  const seg = String(pathSeg).replace(/^\/+|\/+$/g, '');
  if (!seg) return baseHref;
  return String(baseHref).replace(/\/+$/, '') + '/' + seg;
}

async function createWindow(startServer, getServerConfig) {
  await startServer();
  getServerConfigRef = getServerConfig;
  const cfg = getServerConfig();
  let loadUrl = toLoadUrl(cfg.baseUrl);
  loadUrl = appendInitialPath(loadUrl, cfg.electronInitialPath);

  console.log('[Electron] Memuat', loadUrl);

  mainWindow = await createShellBrowserWindow();

  Menu.setApplicationMenu(null);

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    mainWindow.focus();
  });

  attachDidFailLoad(mainWindow);

  try {
    await mainWindow.loadURL(loadUrl, getNoCacheLoadOpts());
  } catch (err) {
    console.error('[Electron] loadURL', err);
    dialog.showErrorBox('Electron', String(err?.message || err));
    throw err;
  }

  if (process.env.ELECTRON_DEV === '1') {
    mainWindow.webContents.openDevTools({ mode: 'detach' });
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

function registerSingleInstanceHandlers() {
  const gotLock = app.requestSingleInstanceLock();
  if (!gotLock) {
    console.warn(
      '[Electron] Instance lain sudah berjalan — keluar. Set ELECTRON_SINGLE_INSTANCE=0 untuk multi-instance (lihat electron/main.js).'
    );
    app.quit();
    return false;
  }
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });
  return true;
}

function bootstrap() {
  const shellMod = require(ELECTRON_SHELL_JS);
  buildContextMenuTemplate = shellMod.buildContextMenuTemplate;
  mainWindowLayoutResolved = normalizeMainWindowLayout(shellMod);

  const { startServer, getServerConfig } = require(path.join(APP_ROOT, 'index.js'));

  const run = () => {
    app.whenReady().then(() => {
      registerSessionPermissionHandlers();
      registerElectronInternalRequestHeader();
      registerOpenRouteWindowIpc();
      registerDeviceIdIpc();
      registerGoogleSignInIpc();
      registerNativeThemeIpc();
      registerAwaitDispatchIpc();
      registerAwaitWebSocketServer();
      createWindow(startServer, getServerConfig).catch((err) => {
        console.error(err);
        dialog.showErrorBox('Electron', String(err?.message || err));
        app.quit();
      });
    });
  };

  if (allowMultipleInstances) {
    run();
  } else if (registerSingleInstanceHandlers()) {
    run();
  }

  app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
      app.quit();
    }
  });

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow(startServer, getServerConfig).catch(console.error);
    }
  });
}

try {
  bootstrap();
} catch (err) {
  console.error(err);
  const msg = String(err?.message || err);
  app
    .whenReady()
    .then(() => {
      try {
        dialog.showErrorBox('EletronNexa — gagal memulai', msg);
      } catch (_) {
        /* abaikan */
      }
      app.quit();
    })
    .catch(() => process.exit(1));
}

