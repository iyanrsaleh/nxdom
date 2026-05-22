/**
 * Helper renderer untuk jendela Electron (route baru + opsi layout).
 * Main memproses layout lewat `sanitizeRouteWindowLayout` — hanya kunci yang diizinkan.
 *
 * @see electron/electronShell.js — `mainWindowLayout`
 */

/** Selaras default `electronShell.js` (jendela sekunder bisa beda lewat `layout`). */
export const ROUTE_WINDOW_LAYOUT_SHELL = {
  width: 1280,
  height: 800,
  minWidth: 400,
  minHeight: 300,
  resizable: true,
  maximizable: true,
};

export const ROUTE_WINDOW_LAYOUT_COMPACT = {
  width: 960,
  height: 640,
  minWidth: 400,
  minHeight: 300,
  resizable: true,
  maximizable: true,
};

/** Prefix kunci `localStorage` handoff (bukan `sessionStorage` — tiap BrowserWindow punya session terpisah). */
export const ROUTE_WINDOW_HANDOFF_PREFIX = '__nexaHandoff_';

/** Kunci yang boleh di `bindRouteLink(..., { width, height, … })` — selaras mainWindowLayout + ContextMenu. */
const BIND_LAYOUT_KEYS = new Set([
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
  'ContextMenu',
]);

export class NexaElectron {
  /** Bridge dari preload (`contextBridge.exposeInMainWorld`). */
  static get api() {
    return typeof globalThis !== 'undefined' ? globalThis.electronAPI : undefined;
  }

  static isAvailable() {
    return typeof this.api?.openRouteWindow === 'function';
  }

  /**
   * Gabungkan opsi layout tanpa elemen (preset + kunci datar + `layout` bersarang).
   * Urutan menimpa: `preset` → datar (`width`, …) → `options.layout`.
   *
   * @param {Record<string, *>} [options]
   * @returns {Record<string, *>|null}
   */
  static mergeLayoutProgrammatic(options = {}) {
    return this.mergeLayout(null, options);
  }

  /**
   * Buka route di jendela Electron baru dari kode (tanpa klik).
   * Opsi layout sama seperti `bindRouteLink`: datar, `preset`, dan/atau `layout`.
   *
   * @param {string} routePath — mis. `/ds/data`
   * @param {Record<string, *>} [options]
   * @returns {Promise<{ ok?: boolean, error?: string }|void>}
   */
  static openRouteWindow(routePath, options = {}) {
    return this.openRoute(routePath, options, null);
  }

  /**
   * Objek JSON-serializable untuk jendela target: `data` atau `windowPayload` (keduanya opsional).
   * Disimpan di `localStorage` (dibagi antar jendela origin sama); target membaca lewat `WindowPayload()` lalu menghapus kunci.
   *
   * @param {Record<string, *>} options
   * @returns {Record<string, *>|null}
   */
  static getWindowPayloadOption(options) {
    if (!options || typeof options !== 'object') return null;
    const w = options.windowPayload;
    if (w != null && typeof w === 'object' && !Array.isArray(w)) return w;
    const d = options.data;
    if (d != null && typeof d === 'object' && !Array.isArray(d)) return d;
    return null;
  }

  /**
   * @returns {string|null} id untuk query `nexaHandoff`
   */
  static storeHandoffPayload(payload) {
    try {
      const id =
        globalThis.crypto?.randomUUID?.() ||
        `h_${Date.now()}_${Math.random().toString(36).slice(2, 12)}`;
      const key = `${ROUTE_WINDOW_HANDOFF_PREFIX}${id}`;
      localStorage.setItem(key, JSON.stringify(payload));
      return id;
    } catch (e) {
      console.warn('[NexaElectron] storeHandoffPayload:', e);
      return null;
    }
  }

  /**
   * Di route target (jendela baru): baca payload handoff, hapus `localStorage` + query `nexaHandoff` dari URL.
   * @returns {unknown|null}
   */
  static WindowPayload() {
    try {
      if (typeof window === 'undefined' || !window.location) return null;
      const params = new URLSearchParams(window.location.search);
      const id = params.get('nexaHandoff');
      if (!id || !/^[a-zA-Z0-9_-]{1,128}$/.test(id)) return null;
      const key = `${ROUTE_WINDOW_HANDOFF_PREFIX}${id}`;
      const raw = localStorage.getItem(key);
      if (raw == null) return null;
      localStorage.removeItem(key);
      const data = JSON.parse(raw);
      try {
        params.delete('nexaHandoff');
        const q = params.toString();
        const next = `${window.location.pathname}${q ? `?${q}` : ''}${window.location.hash || ''}`;
        window.history.replaceState({}, '', next);
      } catch {
        /* abaikan */
      }
      return data;
    } catch {
      return null;
    }
  }

  /** @deprecated Gunakan `WindowPayload()` */
  static consumeRouteWindowPayload() {
    return this.WindowPayload();
  }

  /**
   * @param {string} routePath — mis. `/ds/data` (sama seperti `href` internal)
   * @param {Record<string, *>} [options] — layout (`preset` / datar / `layout`), plus `data` atau `windowPayload` untuk handoff
   * @param {HTMLAnchorElement|null} [linkElement] — untuk `bindRouteLink` (dataset layout); programatis: `null`
   * @returns {Promise<{ ok?: boolean, error?: string }|void>}
   */
  static async openRoute(routePath, options = {}, linkElement = null) {
    const api = this.api;
    if (!api?.openRouteWindow) {
      return { ok: false, error: 'not-electron' };
    }
    const path = String(routePath || '').trim().split('?')[0].split('#')[0];
    if (!path) {
      return { ok: false, error: 'invalid-route' };
    }
    const layout = this.mergeLayout(linkElement, options);
    const p = this.getWindowPayloadOption(options);
    let handoffId = null;
    if (p != null && typeof p === 'object' && Object.keys(p).length > 0) {
      handoffId = this.storeHandoffPayload(p);
    }
    const hasLayout = layout && Object.keys(layout).length > 0;
    let ipcPayload;
    if (!hasLayout && !handoffId) {
      ipcPayload = path;
    } else {
      ipcPayload = { routePath: path };
      if (hasLayout) ipcPayload.layout = layout;
      if (handoffId) ipcPayload.handoffId = handoffId;
    }
    return api.openRouteWindow(ipcPayload);
  }

  /**
   * Baca angka boolean layout dari `data-nexa-electron-*` pada `<a>`.
   * Contoh: `data-nexa-electron-width="900"` `data-nexa-electron-height="700"`
   *
   * @param {HTMLElement} el
   * @returns {Record<string, *>|null}
   */
  static layoutFromDataset(el) {
    if (!el?.dataset) return null;
    const d = el.dataset;
    /** @type {Record<string, *>} */
    const layout = {};
    const num = (key, outKey) => {
      const v = d[key];
      if (v === undefined || v === '') return;
      const n = Number(v);
      if (Number.isFinite(n)) layout[outKey] = Math.round(n);
    };
    num('nexaElectronWidth', 'width');
    num('nexaElectronHeight', 'height');
    num('nexaElectronMinWidth', 'minWidth');
    num('nexaElectronMinHeight', 'minHeight');
    num('nexaElectronMaxWidth', 'maxWidth');
    num('nexaElectronMaxHeight', 'maxHeight');
    if (d.nexaElectronResizable === 'true') layout.resizable = true;
    if (d.nexaElectronResizable === 'false') layout.resizable = false;
    if (d.nexaElectronMaximizable === 'true') layout.maximizable = true;
    if (d.nexaElectronMaximizable === 'false') layout.maximizable = false;
    return Object.keys(layout).length ? layout : null;
  }

  /**
   * Ambil properti jendela dari objek opsi datar (`width`, `height`, `ContextMenu`, …).
   * @param {Record<string, *>} options
   * @returns {Record<string, *>}
   */
  static layoutFromFlatBindOptions(options) {
    if (!options || typeof options !== 'object') return {};
    /** @type {Record<string, *>} */
    const out = {};
    for (const k of BIND_LAYOUT_KEYS) {
      if (options[k] !== undefined) out[k] = options[k];
    }
    return out;
  }

  /**
   * Gabungkan: `preset` → `data-*` (jika `el` ada) → opsi datar → `options.layout` (paling kuat).
   *
   * @param {HTMLAnchorElement|null} el — `null` untuk pembangunan layout hanya dari objek opsi (programatis).
   * @param {{
   *   layout?: Record<string, *>|null,
   *   preset?: Record<string, *>|null
   * } & Record<string, *>} [options]
   * @returns {Record<string, *>|null}
   */
  static mergeLayout(el, options = {}) {
    const fromData =
      el instanceof HTMLElement ? (this.layoutFromDataset(el) || {}) : {};
    const preset = options.preset && typeof options.preset === 'object' ? options.preset : {};
    const fromFlat = this.layoutFromFlatBindOptions(options);
    const fromOpts = options.layout && typeof options.layout === 'object' ? options.layout : {};
    const merged = { ...preset, ...fromData, ...fromFlat, ...fromOpts };
    return Object.keys(merged).length ? merged : null;
  }

  /**
   * Pasang klik: Electron → IPC jendela baru; selain itu biarkan router/browser.
   * Sertakan `data-nexa-electron-window` pada `<a>` agar NexaRoute (capture) tidak memblokir.
   *
   * @param {ParentNode} container
   * @param {string} selector
   * @param {{
   *   route?: string,
   *   layout?: Record<string, *>|null,
   *   preset?: Record<string, *>|null,
   *   width?: number, height?: number, minWidth?: number, minHeight?: number,
   *   maxWidth?: number, maxHeight?: number, x?: number, y?: number,
   *   center?: boolean, title?: string,
   *   resizable?: boolean, minimizable?: boolean, maximizable?: boolean,
   *   closable?: boolean, fullscreenable?: boolean, alwaysOnTop?: boolean,
   *   ContextMenu?: boolean,
   *   data?: Record<string, *>, windowPayload?: Record<string, *>
   * }} [options] — `data` / `windowPayload`: objek ke route target (`WindowPayload`)
   */
  static bindRouteLink(container, selector, options = {}) {
    const el = container.querySelector(selector);
    if (!el || !(el instanceof HTMLAnchorElement)) return;

    const route =
      (options.route && String(options.route).trim()) ||
      el.getAttribute('data-nexa-electron-route')?.trim() ||
      el.getAttribute('href')?.trim();
    if (!route) return;

    el.addEventListener('click', (e) => {
      if (!NexaElectron.isAvailable()) return;
      e.preventDefault();
      NexaElectron.openRoute(route, options, el).then((res) => {
        if (res && res.ok === false) {
          console.warn('[NexaElectron] openRoute:', res.error);
        }
      });
    });
  }
}
