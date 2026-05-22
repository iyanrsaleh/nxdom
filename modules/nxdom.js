// ===========================================
// NEXAUI GLOBAL INITIALIZATION
// ===========================================
// Entry point untuk membuat NXUI tersedia secara global
// File independen yang tidak bergantung pada nexa-ui.js

import {NexaRoute,NexaPage} from "./Route/NexaRoute.js";
import { setPageMeta } from "./Route/setPageMeta.js";
import { NexaGlobal } from "./Event/NexaGlobal.js";
import { NexaEvent } from "./Event/NexaEvent.js";
import { NexaKit, _nxSetWorker } from "./Kit/NexaKit.js";

import { NexaDimensi } from "./dimensi/NexaDimensi.js";
import { NexaStylesheet } from "./assets/NexaStylesheet.js";
import { NexaScript } from "./assets/NexaScript.js";
import { NexaDom as NexaDomClass, StorageData, storageModelStorageData as StorageModelData} from "./dom/NexaDom.js";
import { NexaField } from "./field/NexaField.js";
import * as NexaType from "./type/NexaType.js";
const { fileType } = NexaType;

import { NexaSplit } from "./split/NexaSplit.js";

import { NexaForge, NexaDomextractor } from "./forge/NexaForge.js";
import NexaFilter from "./filter/NexaFilter.js";
// cards
import { NexaLayer } from "./cards/NexaLayer.js";
import { spinner } from "./spinner/NexaSpinner.js";
import { NexaCheckable } from "./check/NexaCheckable.js";
import { NexaTags } from "./tags/NexaTags.js";
import { NexaChart } from "./chart/NexaChart.js";




import { NexaForm, ensureFormStylesheet } from "./forms/NexaForm.js";

import { NexaWild } from "./wild/NexaWild.js";
import { wilayahPropinsi,wilayahKabupaten } from "./flag/wilayah.js";
import { NexaWizard } from "./wizard/NexaWizard.js";
import { NexaCmirror } from "./codemirror/NexaCmirror.js";

import { Qrcode } from "./Qrcode/NexaQrcode.js";
import { NexaPrind } from "./Prind/NexaPrind.js";
import { NexaEscpos } from "./Escpos/NexaEscpos.js";
import { NexaSortable } from "./sortable/NexaSortable.js";
import { NexaScroll } from "./scroll/NexaScroll.js";
import { NexaLink, LinkDefault, NexaLinkUI, onSkip } from "./Link/NexaLink.js";
import { NexaNetwork } from "./Network/NexaNetwork.js";
import { NexaGeolocation } from "./Geolocation/NexaGeolocation.js";
import { NexaClick } from "./Click/NexaClick.js";
import { NexaMode } from "./Mode/NexaMode.js";
import { NexaEditor } from "./editor/NexaEditor.js";
  
// JSON Utilities: kelas viewer dari bundle; helper di Json/index.js → NXUI.Json
import { JsonViewer } from "./Json/bundle.js";
import * as NexaJson from "./Json/index.js";

import {
  NexaTables,
  ensureTableStylesheet,
  rowsFromStorageResponse,
} from "./tables/NexaTables.js";

// STORAGE
import { NexaDb } from "./Buckets/NexaDb.js";
import {NexaFetch, nexaFetch } from "./Buckets/NexaFetch.js";
import {Storage } from "./Buckets/NexaStorage.js";
import NexaModels from "./Buckets/NexaModels.js";
import NexaAwait from "./Buckets/NexaAwait.js";
import NexaEncrypt from "./Buckets/NexaEncrypt.js";
import NexaCrypto from "./Buckets/NexaCrypto.js";
import {NexaBuildQuery} from "./Buckets/NexaBuildQuery.js";


import { NexaFederated } from "./Buckets/NexaFederated.js";
import { NexaVoice } from "./Voice/NexaVoice.js";
import { createNexaWorkerClient } from "./Worker/NexaWorkerClient.js";
import {
  initNexaServiceWorker as registerNexaServiceWorkerModule,
  unregisterNexaServiceWorker as unregisterNexaServiceWorkerModule,
  registerBackgroundSync as registerNexaBackgroundSyncModule,
} from "./ServiceWorker/registerNexaServiceWorker.js";
import { initNexaPwa as registerNexaPwaModule } from "./Pwa/NexaPwa.js";
// Notification System
import { NexaNotif } from "./notifikasi/NexaNotif.js";

import { Buckets } from "./Storage/Buckets/NexaBuckets.js";

// WORKER

import { NexaDropdown } from "./Dropdown/NexaDropdown.js";
import { NexaSidebar, initSidebar, getSidebarInstance, updateSidebarPath } from "./Sidebar/NexaSidebar.js";
import { NexaGrid } from "./grid/NexaGrid.js";
import { Svg, svgContent } from "./Svg/index.js";
// MODAL
import { NexaModal, nexaModal,modalHTML, ensureModalStylesheet } from "./modal/NexaModal.js";



import { NexaLightbox } from "./Lightbox/NexaLightbox.js";
import "./utilities/jquery.js";
import "./chart/chart.umd.min.js";
import "./utilities/jquery-ui.js";
import "./select2/select2.min.js";
// import config from '../../config.js';
import { NexaTerminal } from "./Terminal/NexaTerminal.js";
// import {
//   NexaElectron,
//   ROUTE_WINDOW_LAYOUT_SHELL,
//   ROUTE_WINDOW_LAYOUT_COMPACT,
//   ROUTE_WINDOW_HANDOFF_PREFIX,
// } from "./Electron/NexaElectron.js";

import { NexaFormsScreen } from "./forms/NexaFormsScreen.js";
import { NexaModalScreen } from "./modal/NexaModalScreen.js";
import { NexaWizardScreen } from "./wizard/NexaWizardScreen.js";
import { NexaTabelScreen } from "./tables/NexaTabelScreen.js";
import { NexaNativeScreen } from "./native/NexaNativeScreen.js";
import { NexaPayload } from "./Buckets/NexaPayload.js";
/** Setara `new NXUI.Page(config)` — dipanggil lewat `new NXUI.Tatiye(config).run()`. */
class NexaTatiye {
  constructor(config = {}) {
    this._config = config;
  }
  run() {
    return new NexaPage(this._config);
  }
}

/** Satukan API: `NXUI.Modal(config)` bangun DOM; `NXUI.Modal.open` / `NXUI.Modal.close` delegasi ke `nexaModal`. */
modalHTML.open = (modalId, data) => nexaModal.open(modalId, data);
modalHTML.close = (modalId, force) => nexaModal.close(modalId, force);
export class Screen {
  static handlers = {
    forms: async (screen) => NexaFormsScreen.render(screen.data),
    modal: async (screen) => NexaModalScreen.render(screen.data),
    wizard: async (screen) => NexaWizardScreen.render(screen.data),
    datatable: async (screen) => NexaTabelScreen.render(screen.data),
    native: async (screen) => NexaNativeScreen.render(screen.data),
    /** Alias bahasa Indonesia / singkat — sama dengan `datatable`. */
    tabel: async (screen) => NexaTabelScreen.render(screen.data),
  };

  static register(name, handler) {
    if (!name || typeof handler !== 'function') {
      return;
    }
    Screen.handlers[String(name)] = handler;
  }

  constructor(data) {
    const payload = data && typeof data === "object" ? { ...data } : data;
    // Normalisasi alias umum agar screen handler cukup baca `userid`.
    if (
      payload &&
      typeof payload === "object" &&
      payload.userid == null &&
      payload.userId != null
    ) {
      payload.userid = payload.userId;
    }
    this.data = payload;
  }

  async run(name, ...args) {
    const methodName = String(name || '').trim();
    const handler = Screen.handlers[methodName];

    if (typeof handler !== 'function') {
      throw new Error(`Screen method "${methodName}" tidak terdaftar.`);
    }

    return handler(this, ...args);
  }

  async forms(...args) {
    return this.run('forms', ...args);
  }

  async modal(...args) {
    return this.run('modal', ...args);
  }

  async wizard(...args) {
    return this.run('wizard', ...args);
  }

  async datatable(...args) {
    return this.run('datatable', ...args);
  }

  async tabel(...args) {
    return this.run('tabel', ...args);
  }

  async native(...args) {
    return this.run('native', ...args);
  }

}
// ===========================================
// UTILITY FUNCTIONS
// ===========================================

/** Normalisasi teks menjadi segmen slug (tanpa path tanggal). */
const slugifyText = (text) =>
  String(text || '')
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-')
    .replace(/^-+/, '')
    .replace(/-+$/, '');

/** true jika argumen pertama berupa tanggal publikasi (ISO YYYY-MM-DD, Date, atau string yang bisa di-parse). */
const isPubDateInput = (v) => {
  if (v == null || v === '') return false;
  if (v instanceof Date) return !isNaN(v.getTime());
  const s = String(v).trim();
  if (/^\d{4}-\d{1,2}-\d{1,2}/.test(s)) return true;
  const t = Date.parse(s);
  return !isNaN(t);
};

/** { y, m, d } untuk path `yyyy/mm/dd` — atau null jika tidak valid. */
const ymdFromPubdate = (pubdate) => {
  if (pubdate == null || pubdate === '') return null;
  const dt = pubdate instanceof Date ? pubdate : new Date(pubdate);
  if (!isNaN(dt.getTime())) {
    return {
      y: String(dt.getFullYear()),
      m: String(dt.getMonth() + 1).padStart(2, '0'),
      d: String(dt.getDate()).padStart(2, '0'),
    };
  }
  const s = String(pubdate).trim();
  const m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) {
    return {
      y: m[1],
      m: m[2].padStart(2, '0'),
      d: m[3].padStart(2, '0'),
    };
  }
  return null;
};

/**
 * Membuat slug untuk URL.
 * - Bentuk lama: createSlug(text, id?, prefix?)
 * - Bentuk dengan tanggal: createSlug(pubdate, text, id?, prefix?) → `yyyy/mm/dd/slug-judul`
 *   (gabungkan dengan href seperti `guides/${slug}` → /guides/2025/07/15/judul-...)
 *   Jika `id` ada: tidak ditambahkan ke string URL; disimpan lewat setSlugId (session + local)
 *   agar getSlugId tetap bisa resolve. Sufiks `--id` opsional lewat URL lama masih didukung.
 */
const createSlug = (...args) => {
  const n = args.length;
  const a = args[0];
  const b = args[1];
  const c = args[2];
  const d = args[3];

  let text;
  let id = null;
  let prefix = 'slug';
  let pubdate = null;

  // Empat argumen: (pubdate, title, id, prefix). Jika pubdate kosong/invalid → pakai title, id, prefix (b, c, d).
  if (n >= 4 && isPubDateInput(a)) {
    pubdate = a;
    text = b;
    id = c !== undefined ? c : null;
    prefix = d !== undefined ? d : 'slug';
  } else if (n >= 4 && !isPubDateInput(a)) {
    text = b;
    id = c !== undefined ? c : null;
    prefix = d !== undefined ? d : 'slug';
  } else if (n === 3 && isPubDateInput(a)) {
    pubdate = a;
    text = b;
    id = c !== undefined ? c : null;
    prefix = 'slug';
  } else {
    text = a;
    id = b !== undefined ? b : null;
    prefix = c !== undefined ? c : 'slug';
  }

  const titleSlug = slugifyText(text);

  let out = titleSlug;
  if (pubdate != null && pubdate !== '') {
    const segs = ymdFromPubdate(pubdate);
    if (segs) {
      out = `${segs.y}/${segs.m}/${segs.d}/${titleSlug}`;
    }
  }

  if (id !== null && id !== undefined && id !== '') {
    setSlugId(out, id, prefix);
  }

  return out;
};

// Fungsi untuk mengubah slug kembali menjadi teks yang dapat dibaca
const parseSlug = (slug) => {
  // Hapus id dari slug jika ada (format: slug--id)
  const slugWithoutId = String(slug || '').split('--')[0];
  
  return String(slugWithoutId || '')
    .trim()
    .replace(/-+/g, ' ')
    .replace(/\s+/g, ' ')
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
    .trim();
};

// Fungsi untuk mengekstrak id dari slug (format: slug--id)
const extractIdFromSlug = (slug) => {
  if (!slug) return null;
  
  const parts = String(slug).split('--');
  // Jika ada bagian setelah --, itu adalah id
  if (parts.length > 1) {
    return parts[parts.length - 1];
  }
  
  return null;
};

// Fungsi helper untuk menyimpan mapping slug->id ke sessionStorage
const setSlugId = (slug, id, prefix = 'slug') => {
  if (!slug || !id) return false;
  try {
    const key = `${prefix}_${slug}`;
    const v = String(id);
    sessionStorage.setItem(key, v);
    try {
      localStorage.setItem(key, v);
    } catch (e2) {
      // Quota / private mode — abaikan
    }
    return true;
  } catch (e) {
    return false;
  }
};

// Fungsi helper untuk mengambil id: dari sufiks ...--{id} di URL (URL lama), lalu sessionStorage, lalu localStorage
const getSlugId = (slug, prefix = 'slug') => {
  if (!slug) return null;
  try {
    const s = String(slug).trim();
    const m = s.match(/--([^/]+)$/);
    if (m) {
      return m[1];
    }
    const key = `${prefix}_${s}`;
    return sessionStorage.getItem(key) || localStorage.getItem(key);
  } catch (e) {
    return null;
  }
};

/** Cari item berdasarkan slug hasil `createSlug(...)`. */
const findBySlug = (list, slug, options = {}) => {
  if (!Array.isArray(list) || !slug) return null;
  const {
    pubdate = null,
    prefix = 'slug',
    titleKey = 'title',
    idKey = 'id',
  } = options || {};
  const targetSlug = String(slug).replace(/^\/+|\/+$/g, '');

  return (
    list.find((item) => {
      if (!item || typeof item !== 'object') return false;
      const itemSlug =
        pubdate != null && pubdate !== ''
          ? createSlug(pubdate, item[titleKey], item[idKey], prefix)
          : createSlug(item[titleKey], item[idKey], prefix);
      return String(itemSlug) === targetSlug;
    }) || null
  );
};

// ===========================================
// NEXA WINDOW — deteksi state jendela Electron
// ===========================================

/**
 * Utility deteksi state jendela (maximize/restore) untuk Electron.
 * Dapat diakses via `NXUI.Window.isMaximized()` dari file mana pun.
 *
 * @example
 * if (NXUI.Window.isMaximized()) { ... }
 * NXUI.Window.onResize((maximized) => console.log(maximized));
 */
const NexaWindow = {
  /**
   * Kembalikan `true` jika jendela Electron dalam keadaan maximize.
   * Deteksi via perbandingan `outerWidth` vs `screen.availWidth` (toleransi 10px).
   */
  isMaximized() {
    if (typeof window === 'undefined') return false;
    return window.outerWidth >= window.screen.availWidth - 10;
  },

  /**
   * Kolom tree saat maximize vs restore.
   * @param {{ maximize?: string, restore?: string }} opts
   */
  treeCol(opts = {}) {
    const { maximize = 'nx-col-2', restore = 'nx-col-3' } = opts;
    return this.isMaximized() ? maximize : restore;
  },

  /**
   * Kolom konten (sisa grid) saat maximize vs restore.
   * @param {{ maximize?: string, restore?: string }} opts
   */
  contentCol(opts = {}) {
    const { maximize = 'nx-col-10', restore = 'nx-col-9' } = opts;
    return this.isMaximized() ? maximize : restore;
  },

  /**
   * Pasang listener `resize` yang memanggil `callback(isMaximized: boolean)`.
   * Kembalikan fungsi untuk melepas listener.
   * @param {function(boolean): void} callback
   * @returns {function(): void} unsubscribe
   */
  onResize(callback) {
    if (typeof window === 'undefined' || typeof callback !== 'function') return () => {};
    const handler = () => callback(this.isMaximized());
    window.addEventListener('resize', handler);
    return () => window.removeEventListener('resize', handler);
  },

  // -----------------------------------------------------------------
  // Dimensi viewport (Electron renderer = Chromium, jadi window.innerWidth /
  // innerHeight / screen.* semuanya tersedia tanpa konfigurasi tambahan).
  // -----------------------------------------------------------------

  /** Lebar viewport (CSS pixels). */
  width() {
    if (typeof window === 'undefined') return 0;
    return window.innerWidth || document.documentElement.clientWidth || 0;
  },

  /** Tinggi viewport (CSS pixels). */
  height() {
    if (typeof window === 'undefined') return 0;
    return window.innerHeight || document.documentElement.clientHeight || 0;
  },

  /** Lebar layar fisik (Electron: layar yang sedang dipakai). */
  screenWidth() {
    if (typeof window === 'undefined' || !window.screen) return 0;
    return window.screen.availWidth || window.screen.width || 0;
  },

  /** Tinggi layar fisik. */
  screenHeight() {
    if (typeof window === 'undefined' || !window.screen) return 0;
    return window.screen.availHeight || window.screen.height || 0;
  },

  /** `{ w, h, sw, sh }` ringkasan dimensi viewport + screen. */
  size() {
    return {
      w: this.width(),
      h: this.height(),
      sw: this.screenWidth(),
      sh: this.screenHeight(),
    };
  },

  /**
   * Klasifikasi breakpoint berdasarkan lebar viewport.
   * Mengikuti konvensi Bootstrap/Primer-ish:
   *   xs < 576, sm < 768, md < 992, lg < 1200, xl < 1400, xxl >=
   * @returns {'xs'|'sm'|'md'|'lg'|'xl'|'xxl'}
   */
  breakpoint() {
    const w = this.width();
    if (w < 576) return 'xs';
    if (w < 768) return 'sm';
    if (w < 992) return 'md';
    if (w < 1200) return 'lg';
    if (w < 1400) return 'xl';
    return 'xxl';
  },

  /**
   * Helper untuk parameter `dimensi: [sub, unit]` di NexaRoute.Layer.
   *
   * PENTING — semantik `dimensi` di NexaDimensi.height(selector, sub, unit):
   *   `result = (#selector.height - sub) → di-convert ke unit`
   *   `sub` SELALU dalam **pixel**, dan `unit` hanya format output.
   *
   * Karena `vh` sudah responsive otomatis terhadap tinggi viewport,
   * helper ini hanya berguna jika unit-nya `px` / `rem` / dll yang
   * tidak otomatis menyesuaikan, atau jika header/footer punya tinggi
   * yang berbeda di breakpoint kecil.
   *
   * Default tidak mengubah `sub` per breakpoint (sub = 75 px untuk semua),
   * karena offset header NexaLayer biasanya konstan. Jika perlu beda,
   * isi `opts` sesuai kebutuhan layout.
   *
   * @param {{ short?: number, normal?: number, large?: number, unit?: string }} [opts]
   * @returns {[number, string]} `[subtract_px, unit]`
   */
  dimensi(opts = {}) {
    const {
      short = 75,
      normal = 75,
      large = 75,
      unit = 'vh',
    } = opts;
    const h = this.height();
    if (h < 600) return [short, unit];
    if (h < 800) return [normal, unit];
    return [large, unit];
  },
};

export function initSelect2(selector, options = {}) {
  // Check if jQuery and Select2 are available
  if (typeof $ === "undefined") {
    console.error("❌ jQuery is not available for Select2 initialization");
    return null;
  }

  if (typeof $.fn.select2 === "undefined") {
    console.error(
      "❌ Select2 is not available. Make sure select2.min.js is loaded"
    );
    return null;
  }

  const defaultOptions = {
    placeholder: "Pilih opsi...",
    allowClear: true,
    width: "100%",
  };

  const mergedOptions = { ...defaultOptions, ...options };

  try {
    return $(selector).select2(mergedOptions);
  } catch (error) {
    console.error("❌ Error initializing Select2:", error);
    return null;
  }
}
// Event handlers untuk Select2
export function onSelect2Change(selector, callback) {
  $(selector).on("select2:select", callback);
}

export { NexaLink, LinkDefault, NexaLinkUI, onSkip };

export function onSelect2Open(selector, callback) {
  $(selector).on("select2:open", callback);
}

const PRISM_CORE_LOCAL_URL = new URL("./prism/prism-core.min.js", import.meta.url).href;
const PRISM_AUTOLOADER_LOCAL_URL = new URL("./prism/plugins/autoloader/prism-autoloader.min.js", import.meta.url).href;
const PRISM_COMPONENTS_LOCAL_URL = new URL("./prism/components/", import.meta.url).href;
let _prismLoadingPromise = null;

function loadScriptOnceBySrc(src, marker) {
  return new Promise((resolve, reject) => {
    const existingByMarker = marker ? document.querySelector(`script[data-nx-prism="${marker}"]`) : null;
    const existingBySrc = document.querySelector(`script[src="${src}"]`);
    const existing = existingByMarker || existingBySrc;

    if (existing) {
      if (existing.dataset.loaded === "true") {
        resolve();
        return;
      }
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error(`Gagal memuat Prism script: ${src}`)), { once: true });
      return;
    }

    const script = document.createElement("script");
    script.src = src;
    script.async = true;
    if (marker) {
      script.dataset.nxPrism = marker;
    }
    script.addEventListener("load", () => {
      script.dataset.loaded = "true";
      resolve();
    }, { once: true });
    script.addEventListener("error", () => reject(new Error(`Gagal memuat Prism script: ${src}`)), { once: true });
    document.head.appendChild(script);
  });
}

async function ensureLocalPrismReady() {
  if (typeof document === "undefined") {
    return false;
  }

  if (globalThis.Prism?.plugins?.autoloader) {
    globalThis.Prism.plugins.autoloader.languages_path = PRISM_COMPONENTS_LOCAL_URL;
    return true;
  }

  if (!_prismLoadingPromise) {
    _prismLoadingPromise = (async () => {
      await loadScriptOnceBySrc(PRISM_CORE_LOCAL_URL, "core");
      await loadScriptOnceBySrc(PRISM_AUTOLOADER_LOCAL_URL, "autoloader");
      if (globalThis.Prism?.plugins?.autoloader) {
        globalThis.Prism.plugins.autoloader.languages_path = PRISM_COMPONENTS_LOCAL_URL;
      }
    })().finally(() => {
      _prismLoadingPromise = null;
    });
  }

  await _prismLoadingPromise;
  return Boolean(globalThis.Prism);
}

export async function prism(containerSelectorOrElement = document) {
  const ready = await ensureLocalPrismReady();
  if (!ready) {
    return false;
  }

  try {
    const root = typeof containerSelectorOrElement === 'string'
      ? (document.querySelector(containerSelectorOrElement) || document)
      : (containerSelectorOrElement || document);

    const blocks = root.querySelectorAll('pre code[class*="language-"], code[class*="language-"]');
    blocks.forEach((block) => {
      globalThis.Prism.highlightElement(block);
    });
    return true;
  } catch (error) {
    return false;
  }
}

export function highlightPrismBlocks(containerSelectorOrElement = document) {
  return prism(containerSelectorOrElement);
}



/** Path relatif root `templates/` — mis. `/dashboard/x.css` → `/templates/dashboard/x.css`. */
function resolveTemplatesCssHref(href) {
  const h = String(href || "").trim();
  if (!h) return h;
  if (/^https?:\/\//i.test(h)) return h;
  if (h.startsWith("/templates/")) return h;
  if (h.startsWith("/assets/") || h.startsWith("/nexa-context/")) return h;
  if (h.startsWith("/")) return "/templates" + h;
  return "/templates/" + h.replace(/^\/+/, "");
}

export function Css(hrefOrList) {
  if (typeof document === "undefined") return;
  const list = Array.isArray(hrefOrList) ? hrefOrList : [hrefOrList];
  for (const href of list) {
    const resolved = resolveTemplatesCssHref(href);
    if (!resolved) continue;
    const id = "nexa-css-" + String(resolved).replace(/[^\w-]/g, "_");
    if (document.getElementById(id)) continue;
    const link = document.createElement("link");
    link.id = id;
    link.rel = "stylesheet";
    link.href = resolved;
    document.head.appendChild(link);
  }
}




// ===========================================
// GLOBAL INITIALIZATION
// ===========================================

if (typeof window !== "undefined") {
  // Create NXUI as a ready-to-use instance (not a function)
  // Create unified NXUI instance with core features
  const nexaGlobalInstance = new NexaGlobal();
  const nexaKitInstance = new NexaKit();
  const nexaGridInstance = new NexaGrid();

  // Ensure NXUI is available immediately with basic fallback
  window.NXUI = {
    // Core constructors available immediately
    NexaPage: NexaPage,
    Page: NexaPage,
    Tatiye: NexaTatiye,
    // Basic fallback methods to prevent errors
    class: function (className) {
      // NXUI not fully initialized, using fallback
      return document.getElementsByClassName(className);
    },
    id: function (id) {
      // NXUI not fully initialized, using fallback
      return document.getElementById(id);
    },
    selector: function (selector) {
      // NXUI not fully initialized, using fallback
      return document.querySelector(selector);
    },
    /** Fallback sebelum nxuiBase: set/get innerHTML by selector (bukan template eventload) */
    htmlSelector: function (selector, value) {
      const element = document.querySelector(selector);
      if (element) {
        if (value !== undefined) {
          element.innerHTML = value;
        } else {
          return element.innerHTML;
        }
      }
    },
  };

  // Pastikan NEXA object tersedia
  // NEXA.url akan di-set oleh NexaPage dari url di routes.js (tidak menebak dari window.location)
  // Jangan set NEXA.url di sini, tunggu dari registrasi di routes.js
  if (typeof window.NEXA === 'undefined') {
    window.NEXA = {
      url: '', // Akan di-set oleh NexaPage dari url di routes.js
      userId: 0,
      apiBase: '', // Di-set lewat NXUI.updateNEXAUrl / fallback init
      tabel: { submenu: [] },
      controllers: {
        packages: 'packages',
        Storage: Storage
      }
    };
  } else {
    // Pastikan controllers dan Storage tersedia
    if (!window.NEXA.controllers) {
      window.NEXA.controllers = {
        packages: 'packages',
        Storage: Storage
      };
    } else if (!window.NEXA.controllers.Storage) {
      window.NEXA.controllers.Storage = Storage;
    }
  }
  
  // Helper function untuk normalize URL: fix double slashes, ensure HTTPS if page is HTTPS
  const normalizeUrl = (url) => {
    if (!url) return url;
    
    // Ensure HTTPS if page is loaded over HTTPS
    if (typeof window !== 'undefined' && window.location.protocol === 'https:') {
      url = url.replace(/^http:\/\//i, 'https://');
    }
    
    // Fix double slashes (except after protocol like http:// or https://)
    url = url.replace(/([^:]\/)\/+/g, '$1');
    
    return url;
  };

  // Helper function untuk join URL parts dengan aman (hindari double slash)
  const joinUrl = (...parts) => {
    if (parts.length === 0) return '';
    if (parts.length === 1) return normalizeUrl(String(parts[0]));
    
    const firstPart = String(parts[0] || '');
    const restParts = parts.slice(1)
      .filter(part => part) // Remove empty parts
      .map(part => String(part).replace(/^\/+|\/+$/g, '')); // Remove leading/trailing slashes
    
    // If first part is a full URL (http:// or https://)
    if (firstPart.match(/^https?:\/\//)) {
      const baseUrl = firstPart.replace(/\/+$/, ''); // Remove trailing slashes
      const path = restParts.join('/');
      return normalizeUrl(path ? baseUrl + '/' + path : baseUrl);
    }
    
    // Otherwise, treat as relative path
    const allParts = [firstPart.replace(/^\/+|\/+$/g, ''), ...restParts]
      .filter(part => part);
    return normalizeUrl('/' + allParts.join('/'));
  };

  /** NEXA.apiBase: prioritas argumen urlApi → window.nexaPage.urlApi (dari NXUI.Page) → url + /api */
  const resolveNexaApiBase = (baseAppUrl, explicitUrlApi) => {
    if (typeof explicitUrlApi === 'string' && explicitUrlApi.trim() !== '') {
      return normalizeUrl(explicitUrlApi.trim());
    }
    if (typeof window !== 'undefined' && window.nexaPage && window.nexaPage.urlApi) {
      const u = window.nexaPage.urlApi;
      if (typeof u === 'string' && u.trim() !== '') {
        return normalizeUrl(u.trim());
      }
    }
    return normalizeUrl(joinUrl(baseAppUrl, 'api'));
  };

  // Simpan fungsi untuk update NEXA.url dari registrasi (dipanggil oleh NexaPage)
  window.NXUI = window.NXUI || {};
  window.NXUI.updateNEXAUrl = function(url, urlApi) {
    if (!url) {
      // url tidak diberikan
      return;
    }
    
    // Pastikan NEXA object ada
    if (typeof window.NEXA === 'undefined') {
      window.NEXA = {
        url: url, // Set url dari parameter, bukan dari window.NEXA.url yang belum ada
        userId: 0,
        tabel: { submenu: [] },
        controllers: {
          packages: 'packages',
          Storage: Storage
        }
      };
    } else {
      // Set url dengan eksplisit
      window.NEXA.url = url;
    }
    
    // Normalize URL sebelum digunakan
    url = normalizeUrl(url);
    
    window.NEXA.apiBase = resolveNexaApiBase(url, urlApi);

    // Update controllers jika belum ada
    if (!window.NEXA.controllers) {
      window.NEXA.controllers = {
        packages: 'packages',
        bucketsInBackground: normalizeUrl(joinUrl(window.NEXA.apiBase, 'user', 'Fetch')),
        Storage: Storage
      };
    } else {
      // Pastikan Storage tersedia di controllers
      if (!window.NEXA.controllers.Storage) {
        window.NEXA.controllers.Storage = Storage;
      }
    }
    
    // Dispatch event bahwa NEXA.url sudah di-update
    // Ini berguna untuk komponen yang menunggu NEXA.url tersedia
    if (typeof window.dispatchEvent !== 'undefined') {
      window.dispatchEvent(new CustomEvent('nexaUrlUpdated', {
        detail: { url: window.NEXA.url }
      }));
    }
  };

  /**
   * Gabungkan konfigurasi endpoint (multi-API) ke NEXA.endpoint dan salin URL/string ke properti top-level
   * (kecuali url, urlApi, apiBase, dan kunci sistem).
   */
  const syncNexaEndpoints = function (endpoint) {
    if (typeof window === 'undefined' || !window.NEXA) return;
    if (!endpoint || typeof endpoint !== 'object') {
      window.NEXA.endpoint = {};
      return;
    }
    const out = {};
    for (const [k, v] of Object.entries(endpoint)) {
      if (typeof v === 'string') {
        const t = v.trim();
        out[k] = t ? normalizeUrl(t) : v;
      } else if (v !== undefined) {
        out[k] = v;
      }
    }
    window.NEXA.endpoint = out;
    const skipFlat = new Set([
      'url',
      'urlApi',
      'apiBase',
      'userId',
      'controllers',
      'worker',
      'serviceWorker',
      'endpoint',
    ]);
    for (const [k, v] of Object.entries(out)) {
      if (skipFlat.has(k)) continue;
      window.NEXA[k] = v;
    }
  };

  // Create enhanced NXUI that combines all functionalities
  const nxuiBase = {
    // Core features: NexaDb and NexaRoute (tersedia segera)
    NexaRoute: NexaRoute,
    NexaPage,
    Page:NexaPage,
    Tatiye: NexaTatiye,
    /** Memperbarui title dan meta di head setelah navigasi SPA */
    setPageMeta,
    /** Endpoint multi-API dari NXUI.Page → NEXA.endpoint + mirror ke NEXA (mis. typicode) */
    syncNexaEndpoints,
    // Storage function untuk IndexedDB access
    NexaDb: NexaDb,
    NexaFetch,
    Storage: Storage,
    NexaModels,
    NexaAwait,
    JsonViewer,
    /** JSON viewer: panggil `NX.Json({ theme, rows, container })`; utilitas di `NX.Json.*`. */
    Json: NexaJson.Json,
    /**
     * Render `<json-viewer>` ke container (alias 3 arg — sama dengan `NX.Json({ ...opts, payload, container })`).
     * @param {HTMLElement|null} container
     * @param {*} payload
     * @param {{ theme?: "light"|"dark", expandAll?: boolean, fallbackClass?: string }} [options]
     */
    renderJson(container, payload, options = {}) {
      return NexaJson.Json({ ...options, payload, container });
    },
    Models: NexaModels,
    Await: NexaAwait,
    getDb,
    LinkDefault,
    NexaLink,
    Link: NexaLinkUI,
    NexaFilter,
    Filter: NexaFilter,
    NexaLightbox,
    Buckets: Buckets,
    Screen,
    Lightbox:NexaLightbox,
    wilayahPropinsi,
    Propinsi:wilayahPropinsi,
    wilayahKabupaten,
    Kabupaten:wilayahKabupaten,


    // Utility function untuk membuat slug
    createSlug: createSlug,
    findBySlug: findBySlug,
    parseSlug: parseSlug,
    extractIdFromSlug: extractIdFromSlug,
    setSlugId: setSlugId,
    getSlugId: getSlugId,
    initSelect2,  
    onSelect2Change,
    onSelect2Open,
    prism,
    highlightPrismBlocks,
    NexaLayer,
    Layer: NexaLayer,
    // NexaRender,
    NexaTerminal,
    NexaSplit,
    Split:NexaSplit,
    Terminal: NexaTerminal,
    /** Jendela Electron sekunder + handoff; alias pendek: `NXUI.Electron` */
    // NexaElectron,
    // Electron: NexaElectron,
    // ROUTE_WINDOW_LAYOUT_SHELL,
    // ROUTE_WINDOW_LAYOUT_COMPACT,
    // ROUTE_WINDOW_HANDOFF_PREFIX,
     // Voice system (Text-to-Speech functionality)
    NexaVoice: function (options = {}) {
      return new NexaVoice(options);
    },
    Voice: function (options = {}) {
      return new NexaVoice(options);
    },
    /**
     * `list.map(fn).join(joiner)` — helper string HTML dari array (bukan “render” virtual DOM / bukan opsi `render` di `Refresh.partial`).
     * @param {Iterable<*>|ArrayLike<*>|null|undefined} list — `null`/`undefined` → array kosong.
     * @param {(item: *, index: number, array: Array<*>) => string} fn
     * @param {string} [joiner='']
     * @returns {string}
     * @example const html = NXUI.mapJoin(local.response, (item) => `<p>${item.a1}</p>`);
     */
    mapJoin: function mapJoin(list, fn, joiner = "") {
      if (typeof fn !== "function") return "";
      let arr = [];
      if (list == null) {
        arr = [];
      } else if (Array.isArray(list)) {
        arr = list;
      } else {
        try {
          arr = Array.from(list);
        } catch (_) {
          arr = [];
        }
      }
      return arr.map(fn).join(joiner);
    },
    /** Alias `mapJoin` — nama lama; perilaku sama. */
    render: function render(list, fn, joiner = "") {
      return NXUI.mapJoin(list, fn, joiner);
    },
    /**
     * `map` + `join` secara async — gunakan **`await NXUI.map(...)`** di fungsi `async` agar urutan jelas dan `fn` boleh async (dijalankan paralel dengan `Promise.all`).
     * @param {Iterable<*>|ArrayLike<*>|null|undefined} list
     * @param {(item: *, index: number, array: Array<*>) => string|Promise<string>} fn
     * @param {string} [joiner='']
     * @returns {Promise<string>}
     */
    map: async function map(list, fn, joiner = "") {
      if (typeof fn !== "function") return "";
      let arr = [];
      if (list == null) {
        arr = [];
      } else if (Array.isArray(list)) {
        arr = list;
      } else {
        try {
          arr = Array.from(list);
        } catch (_) {
          arr = [];
        }
      }
      const parts = await Promise.all(
        arr.map((item, index) => Promise.resolve(fn(item, index, arr)))
      );
      return parts.join(joiner);
    },

    /**
     * NXUI.Worker — HTML builder di Web Worker thread agar tidak memblok UI.
     *
     * Worker menggunakan NexaBuilderWorker.js dengan NX spec builder
     * (API identik NXUI tapi pure string, tanpa DOM).
     *
     * @example
     * // map besar ke worker — fn menerima (item, index, NX)
     * const html = await NXUI.Worker.buildMap(components, (item, i, NX) =>
     *   NX.div().class('card').container()
     *     .p().view(item.label)
     *     .span().view(item.version)
     * );
     * container.innerHTML = html;
     *
     * // build spec tree di worker
     * const html = await NXUI.Worker.build({
     *   tag: 'div', a: { id: 'tes' }, c: [
     *     { tag: 'p', t: 'hello' }
     *   ]
     * });
     */
    Worker: (() => {
      let _client = null;
      let _initTried = false;

      function _getClient() {
        if (_client) return _client;
        if (_initTried) return null;
        _initTried = true;
        if (typeof Worker === 'undefined') return null;
        try {
          const url = new URL('./Worker/NexaBuilderWorker.js', import.meta.url).href;
          _client = createNexaWorkerClient(url);
        } catch (e) {
          console.warn('[NXUI.Worker] init failed:', e);
          _client = null;
        }
        return _client;
      }

      return {
        /**
         * Build HTML dari spec tree di worker.
         * @param {Object} spec — { tag, a, s, c, t }
         * @returns {Promise<string>}
         */
        async build(spec) {
          const c = _getClient();
          if (!c) return '';
          return c.build(spec);
        },

        /**
         * Map array di worker — fn adalah fungsi yang HARUS menggunakan NX builder
         * (bukan NXUI) karena berjalan di worker context.
         *
         * fn(item, index, NX) → NX.div()... atau string
         *
         * @param {Array}    data
         * @param {Function} fn — (item, index, NX) => NX builder | string
         * @returns {Promise<string>}
         * @example
         * const html = await NXUI.Worker.buildMap(products, (item, i, NX) =>
         *   NX.li().class('item').container()
         *     .span().view(item.name)
         *     .small().view(item.price)
         * );
         */
        async buildMap(data, fn) {
          const c = _getClient();
          if (!c) return '';
          return c.buildMap(data, fn);
        },

        /**
         * Pretty-print HTML string di worker (async, non-blocking).
         * @param {string} html
         * @returns {Promise<string>}
         */
        async pretty(html) {
          const c = _getClient();
          if (!c) return html;
          return c.pretty(html);
        },

        /** @returns {boolean} apakah worker berhasil diinisialisasi */
        get ready() { return !!_getClient(); },
      };
    })(),

    // DOM
    NexaDom: NexaDomClass,
    Dom: NexaDomClass,
    /** NexaDom `storage: { model, select?, query?(builder) }` — `Storage().model()` + hook where/join */
    StorageModelData,

    /** Inline edit `.editable` — disarankan: `new NXUI.Field()` */
    Field: NexaField,
    NexaChild: NexaField,
    NexaField,
    Qrcode,
    // Config: config,
    NexaPrind,
    Prind:NexaPrind,
    NexaEditor,
    Editor:NexaEditor,
    /** RAW ESC/POS byte builder — NXUI.Escpos / NexaEscpos */
    NexaEscpos,
    Escpos: NexaEscpos,
    NexaTags,
    Tags:NexaTags,
    NexaChart,
    NexaPayload,
    Payload:NexaPayload,
    Chart: (opts) => NexaChart.create(opts),

    /** Checkbox + radio — disarankan: `new NXUI.Checkable()` */
    Checkable: NexaCheckable,

    NexaCheckable,
    /** @deprecated alias — sama dengan NexaCheckable */
    NexaCheckbox: NexaCheckable,
    Checkbox: NexaCheckable,
    
    NexaSortable,
    Sortable: NexaSortable,
    NexaScroll,
    Scroll:NexaScroll,
    NexaNetwork,
    Network:NexaNetwork,
    NexaGeolocation,
    Geolocation:NexaGeolocation,
    NexaClick,
    Click:NexaClick,
    NexaMode,
    Mode:NexaMode,
    /** Modul tipe file / ikon preview — `NXUI.NexaType.fileType` */
    NexaType,
    Type: NexaType,
    NexaEvent: NexaEvent,
    Event: NexaEvent,
    fileType,
    NexaKit,
    UIKit: NexaKit,
    // ── Fluent Element Builder ──────────────────────────────────────────────
    // NXUI.div().id('box').class('card').style({ color: 'red' }).html('Hello')
    // → '<div id="box" class="card" style="color: red;">Hello</div>'
    tag:        (tagName, c) => nexaKitInstance.tag(tagName, c),
    div:        (c) => nexaKitInstance.div(c),
    span:       (c) => nexaKitInstance.span(c),
    p:          (c) => nexaKitInstance.p(c),
    a:          (href) => nexaKitInstance.a(href),
    button:     (c) => nexaKitInstance.button(c),
    btn:        (handler) => nexaKitInstance.btn(handler),
    input:      (c) => nexaKitInstance.input(c),
    textarea:   (c) => nexaKitInstance.textarea(c),
    ul:         (c) => nexaKitInstance.ul(c),
    ol:         (c) => nexaKitInstance.ol(c),
    li:         (c) => nexaKitInstance.li(c),
    h1:         (c) => nexaKitInstance.h1(c),
    h2:         (c) => nexaKitInstance.h2(c),
    h3:         (c) => nexaKitInstance.h3(c),
    h4:         (c) => nexaKitInstance.h4(c),
    h5:         (c) => nexaKitInstance.h5(c),
    h6:         (c) => nexaKitInstance.h6(c),
    img:        (src) => nexaKitInstance.img(src),
    section:    (c) => nexaKitInstance.section(c),
    article:    (c) => nexaKitInstance.article(c),
    footer:     (c) => nexaKitInstance.footer(c),
    nav:        (c) => nexaKitInstance.nav(c),
    aside:      (c) => nexaKitInstance.aside(c),
    thead:      (c) => nexaKitInstance.thead(c),
    tbody:      (c) => nexaKitInstance.tbody(c),
    tr:         (c) => nexaKitInstance.tr(c),
    td:         (c) => nexaKitInstance.td(c),
    th:         (c) => nexaKitInstance.th(c),
    strong:     (c) => nexaKitInstance.strong(c),
    em:         (c) => nexaKitInstance.em(c),
    small:      (c) => nexaKitInstance.small(c),
    code:       (c) => nexaKitInstance.code(c),
    pre:        (c) => nexaKitInstance.pre(c),
    blockquote: (c) => nexaKitInstance.blockquote(c),
    icon:       (classes) => nexaKitInstance.icon(classes),
    // ───────────────────────────────────────────────────────────────────────
    NexaDimensi,
    Dimensi: NexaDimensi,
    NexaWindow,
    Window: NexaWindow,
    NexaStylesheet,
    Stylesheet:NexaStylesheet,
    NexaBuildQuery,
    Css,
    BuildQuery:NexaBuildQuery,
    /** Muat skrip ES / classic — `Dom`, `NexaUi`, `modules` (alias `NexaUi`, relatif ke folder `assets/modules`) */
    NexaScript,
    Script: NexaScript,
    // Dropdown component
    NexaDropdown: NexaDropdown,
    Dropdown: NexaDropdown,
    // Sidebar component
    NexaSidebar: NexaSidebar,
    Sidebar: NexaSidebar,
    // Sidebar utility functions
    initSidebar: initSidebar,
    getSidebarInstance: getSidebarInstance,
    updateSidebarPath: updateSidebarPath,
    // Spinner (`Dom/NexaSpinner.js`) — `NXUI.spinner(opts)`; dipakai `NexaLinkUI.load()` bila `spinner` di opsi konstruktor
    spinner: spinner,
    // Grid component
    NexaGrid: NexaGrid,
    NexaForm,
    Form: NexaForm,
    NexaWild,
    ensureFormStylesheet,
    NexaTables,
    Tables: NexaTables,
    ensureTableStylesheet,
    rowsFromStorageResponse,
    /** Form bertahap (step-by-step) — kelas `NexaWizard` dari `Form/NexaWizard.js` */
    NexaWizard,
    FormWizard: NexaWizard,
    NexaCmirror,
    Codemirror:NexaCmirror,
    Grid: NexaGrid,
    // Grid instance for direct usage
    grid: nexaGridInstance,
    // Modal — `Modal(data)` = DOM dinamis; `Modal.open` / `Modal.close` = delegasi `nexaModal`; kelas: `NexaModal`
    NexaModal: NexaModal,
    Modal: modalHTML,
    nexaModal: nexaModal,
    modalHTML: modalHTML,
    ensureModalStylesheet,
    /**
     * Refresh view SPA: muat ulang route aktif tanpa klik (delegasi ke `window.nexaRoute.refresh`).
     * @param {{ route?: string, pushState?: boolean, hard?: boolean }} [options] — `hard: true` = reload browser penuh.
     * @example await NXUI.Refresh.refresh();
     * @example await NXUI.Refresh.refresh({ route: 'contact/data' });
     *
     * Partial: perbarui satu node DOM tanpa `nexaRoute.navigate` / handler route.
     * @param {{ target: string|Element, scope?: string|Element|false|null, html?: string, render?: () => (string|Element|Promise<string|Element>), update?: (el: Element) => (void|Promise<void>), event?: boolean, keepScroll?: boolean }} options
     * `scope`: akar pencarian (default: `#main` jika ada). `null`/`false` = seluruh `document` (hindari duplikat id di luar route).
     * `keepScroll`: simpan scroll `[id^="nx_body_"]` lain di dalam `scope` agar kolom sibling tidak melompat (NexaLayer).
     * @example await NXUI.Refresh.partial({ target: '#list', render: async () => '<ul>...</ul>' });
     * @example await NXUI.Refresh.partial({ scope: '#main', target: '#nx_body_X', keepScroll: true, render: async () => html });
     */
    Refresh: {
      refresh: async function (options) {
        if (typeof window !== 'undefined' && window.nexaRoute && typeof window.nexaRoute.refresh === 'function') {
          return window.nexaRoute.refresh(options);
        }
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('[NXUI.Refresh] nexaRoute belum siap — pastikan NexaRoute sudah diinisialisasi.');
        }
      },
      partial: async function (options) {
        if (!options || typeof options !== 'object') {
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('[NXUI.Refresh.partial] options wajib berupa object.');
          }
          return;
        }
        const target = options.target;
        if (target == null) {
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('[NXUI.Refresh.partial] options.target (selector atau Element) wajib diisi.');
          }
          return;
        }
        const resolvePartialEl = (t, scopeOpt) => {
          if (typeof document === 'undefined') return null;
          if (t instanceof Element) return t;
          if (typeof t !== 'string') return null;
          let root = null;
          if (scopeOpt === false || scopeOpt === null) {
            root = null;
          } else if (scopeOpt !== undefined) {
            root = typeof scopeOpt === 'string' ? document.querySelector(scopeOpt) : scopeOpt;
          } else {
            root = document.getElementById('main');
          }
          const idMatch = t.match(/^#([\w-]+)$/);
          if (idMatch) {
            const id = idMatch[1];
            if (root && root.nodeType === 1) {
              const inside = root.querySelector('#' + id);
              if (inside) return inside;
            }
            return document.getElementById(id);
          }
          if (root && root.nodeType === 1) {
            return root.querySelector(t);
          }
          return document.querySelector(t);
        };
        const el = resolvePartialEl(target, options.scope);
        if (!el || !(el instanceof Element)) {
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('[NXUI.Refresh.partial] elemen tidak ditemukan:', target);
          }
          return;
        }
        const getScopeRootEl = (scopeOpt) => {
          if (typeof document === 'undefined') return null;
          if (scopeOpt === false || scopeOpt === null) return document.body;
          if (scopeOpt !== undefined) {
            return typeof scopeOpt === 'string' ? document.querySelector(scopeOpt) : scopeOpt;
          }
          return document.getElementById('main');
        };
        let scrollRestore = null;
        if (options.keepScroll === true) {
          const root = getScopeRootEl(options.scope);
          scrollRestore = new Map();
          if (root && root.nodeType === 1) {
            root.querySelectorAll('[id^="nx_body_"]').forEach((node) => {
              if (node !== el && node.id) scrollRestore.set(node.id, node.scrollTop);
            });
          }
        }
        const emit = options.event !== false;
        try {
          if (typeof options.update === 'function') {
            await options.update(el);
          } else if (typeof options.render === 'function') {
            const out = await options.render();
            if (out instanceof Element) {
              el.replaceChildren(out);
            } else if (out != null && typeof out === 'object' && out.nodeType === 11) {
              el.replaceChildren(out);
            } else if (typeof out === 'string') {
              el.innerHTML = out;
            } else {
              if (typeof console !== 'undefined' && console.warn) {
                console.warn('[NXUI.Refresh.partial] render() harus mengembalikan string atau Element.');
              }
            }
          } else if (typeof options.html === 'string') {
            el.innerHTML = options.html;
          } else {
            if (typeof console !== 'undefined' && console.warn) {
              console.warn('[NXUI.Refresh.partial] isi salah satu: html, render, atau update.');
            }
            return;
          }
          if (scrollRestore && scrollRestore.size > 0 && typeof window !== 'undefined') {
            requestAnimationFrame(() => {
              scrollRestore.forEach((top, id) => {
                const n = document.getElementById(id);
                if (n) n.scrollTop = top;
              });
            });
          }
          if (emit && typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
            const sel = typeof target === 'string' ? target : null;
            window.dispatchEvent(
              new CustomEvent('nxui:partialRefresh', {
                detail: { target: sel, element: el },
              })
            );
          }
        } catch (e) {
          console.error('[NXUI.Refresh.partial]', e);
          throw e;
        }
      },
    },
    /**
     * Navigasi programatik ke route terdaftar di `App.js` — delegasi ke `window.nexaRoute.navigate`.
     * Setara klik tautan `/blog`, `/ds/data`, atau sub-route `guides/foo`.
     * @param {string} route — path tanpa slash depan, mis. `'blog'`, `'ds/data'`, `'markdown'`
     * @param {boolean|{ pushState?: boolean }} [pushStateOrOptions] — default `true` (tambah history); `false` atau `{ pushState: false }` untuk replace
     * @returns {Promise<void>}
     * @example await NXUI.load('blog');
     * @example await NXUI.load('contact/data', false);
     */
    load: async function (route, pushStateOrOptions = true) {
      if (route == null || String(route).trim() === "") {
        if (typeof console !== "undefined" && console.warn) {
          console.warn("[NXUI.load] route wajib berupa string non-kosong.");
        }
        return;
      }
      const r = String(route).trim().replace(/^\/+/g, "").replace(/\/+$/g, "");
      let pushState = true;
      if (typeof pushStateOrOptions === "boolean") {
        pushState = pushStateOrOptions;
      } else if (
        pushStateOrOptions &&
        typeof pushStateOrOptions === "object" &&
        Object.prototype.hasOwnProperty.call(pushStateOrOptions, "pushState")
      ) {
        pushState = !!pushStateOrOptions.pushState;
      }
      if (
        typeof window !== "undefined" &&
        window.nexaRoute &&
        typeof window.nexaRoute.navigate === "function"
      ) {
        return window.nexaRoute.navigate(r, pushState);
      }
      if (typeof console !== "undefined" && console.warn) {
        console.warn(
          "[NXUI.load] nexaRoute belum siap — pastikan NXUI.Page (App.js) sudah diinisialisasi."
        );
      }
    },
    // Add NexaGlobal features
    global: nexaGlobalInstance,
    Svg,
    /** Objek koleksi string SVG (`forgot`, `nexa`, `qr`, …) — sama isinya dengan `svgContent.js` */
    svgContent,
    htmlDecode,
    Secure: NexaEncrypt,
    NexaNotif,
    Notifikasi:NexaNotif,
    applications,
    appBuckets,
    /** Factory `html()` (GET fragmen `.html`) / `Markdown()` (GET `.md` + render di klien) — panggil tanpa `new` */
    NexaHtml: NexaHtml,
    /**
     * Muat fragmen HTML statis lewat GET (`{template}/{row}.html` di origin halaman). Bukan eventload.
     * Opsional: `options.templateOrigin` untuk basis URL selain `location.origin`.
     * @param {string} row nama file tanpa ekstensi, mis. "exam"
     * @param {object} endpoints variabel untuk substitusi `{kunci}` di HTML (hanya key yang ada di object)
     * @param {string} [template] folder relatif ke origin — jika dihilangkan, pakai `nexaPage.appRoot` (App.js `appRoot`)
     * Respons sukses menyertakan `hydrate(root, lists)` untuk `NexaForge.hydrate` (list `{user.nama}` dll.).
     */
    html: async function (row, endpoints, template, options = {}) {
      return NexaHtml().html(row, endpoints, template, options);
    },
    Markdown: async function (fileOrContent, variables, template, options = {}) {
      return NexaHtml().Markdown(fileOrContent, variables, template, options);
    },
    /** Class dari NexaForge.js (view/DOM) — `new NXUI.NexaForgeView(...)`; static `hydrate` untuk list template */
    NexaForgeView: NexaForge,
    /** @deprecated gunakan `NexaForgeView` — alias ke kelas yang sama */
    NexaHtmlView: NexaForge,
    /** Bridge ke NexaWebWorker — diisi lewat initNexaWorker */
    nexaWorker: null,
    /**
     * Sinkronkan `NEXA.userId` dari oauth/credential (userid atau userId).
     * Dipanggil otomatis setelah `syncNexaAuth` / saat lisensi valid.
     */
    applyNexaUserIdFromAuth: function () {
      if (typeof window === "undefined") return;
      window.NEXA = window.NEXA || {};
      const o =
        window.NEXA.oauth && typeof window.NEXA.oauth === "object"
          ? window.NEXA.oauth
          : {};
      const c =
        window.NEXA.credential && typeof window.NEXA.credential === "object"
          ? window.NEXA.credential
          : {};
      const raw = o.userid ?? o.userId ?? c.userid ?? c.userId;
      const n = Number(raw);
      window.NEXA.userId =
        raw !== undefined &&
        raw !== null &&
        raw !== "" &&
        Number.isFinite(n)
          ? Math.trunc(n)
          : 0;
    },
    /**
     * Sinkronkan credential dan oauth dari IndexedDB ke NEXA global.
     * Bisa dipanggil dari route mana pun: await NXUI.syncNexaAuth();
     */
    syncNexaAuth: async function (payload = {}) {
      if (typeof window === "undefined") {
        return { credential: {}, oauth: {} };
      }

      window.NEXA = window.NEXA || {};

      let credential = payload.credential;
      let oauth = payload.oauth;

      try {
        if (credential == null && window.NXUI?.ref) {
          credential = await window.NXUI.ref.get("bucketsStore", "credential");
        }
        if (oauth == null && window.NXUI?.ref) {
          oauth = await window.NXUI.ref.get("bucketsStore", "oauth");
        }
      } catch (e) {
        console.warn("syncNexaAuth failed:", e);
      }

      window.NEXA.credential = credential || window.NEXA.credential || {};
      window.NEXA.oauth = (oauth?.data ?? oauth) || window.NEXA.oauth || {};

      window.NXUI.applyNexaUserIdFromAuth();

      return {
        credential: window.NEXA.credential,
        oauth: window.NEXA.oauth,
      };
    },
    /**
     * Validasi lisensi — panggil di awal setiap halaman yang dilindungi.
     * Otomatis redirect ke 'licenses' jika tidak valid / expired.
     * @returns {object|null} credential jika valid, null jika redirect
     *
     * Contoh: const cred = await NXUI.checkLicense(); if (!cred) return;
     */
    checkLicense: async function () {
      const cred = await window.NXUI.ref.get("bucketsStore", "credential");
      if (!cred) { window.NXUI.load("licenses"); return null; }
      if (typeof window !== "undefined") {
        window.NEXA = window.NEXA || {};
        window.NEXA.credential = cred || {};
        window.NXUI.applyNexaUserIdFromAuth();
      }
      const now = Math.floor(Date.now() / 1000);
      const expiredAt = cred["expired_at"] ?? 0;
      if (expiredAt > 0 && now > expiredAt) {
        try {
          const device_id = await window.electronAPI?.getDeviceId?.() ?? "unknown";
          const app_id = await window.electronAPI?.getAppId?.() ?? "unknown";
          const res = await window.NXUI.Storage().licenses().key({ licenses: cred["license_key"], device_id, app_id });
          const red = res?.data ?? res ?? null;
          if (red?.status === "valid") {
            const merged = { id: "credential", device_id, app_id, ...red };
            await window.NXUI.ref.set("bucketsStore", merged);
            if (typeof window !== "undefined") {
              window.NEXA = window.NEXA || {};
              window.NEXA.credential = { ...(window.NEXA.credential || {}), ...merged };
              window.NXUI.applyNexaUserIdFromAuth();
            }
            return { ...red, device_id, app_id };
          }
        } catch (e) { /* network error — fallback ke licenses */ }
        await window.NXUI.ref.delete("bucketsStore", "credential");
        window.NXUI.load("licenses");
        return null;
      }
      return cred;
    },
    /**
     * Aktifkan Web Worker untuk fetch API (Storage().package / example().method).
     * @param {{ enabled?: boolean, storage?: boolean, workerUrl?: string, debug?: boolean }} workerConfig
     * Setelah sukses: window.NEXA.worker.ready === true, event "nexaWorkerReady".
     * Verifikasi: ?nexaWorkerDebug=1 atau webWorker.debug: true → log konsol + log tiap Storage lewat worker.
     */
    initNexaWorker: function (workerConfig = {}) {
      const cfg = { ...workerConfig };
      if (typeof window !== "undefined") {
        try {
          if (new URLSearchParams(window.location.search).get("nexaWorkerDebug") === "1") {
            cfg.debug = true;
          }
        } catch (e) {
          /* ignore */
        }
      }

      const setNexaWorkerInfo = (info) => {
        if (typeof window !== "undefined" && window.NEXA) {
          window.NEXA.worker = { ...window.NEXA.worker, ...info };
        }
      };

      if (typeof Worker === "undefined") {
        this.nexaWorker = null;
        setNexaWorkerInfo({ ready: false, reason: "Worker API tidak tersedia" });
        if (cfg.debug) {
          console.info("[NexaWorker] tidak diaktifkan: Worker API tidak tersedia");
        }
        return null;
      }
      if (cfg.enabled === false) {
        this.nexaWorker = null;
        setNexaWorkerInfo({ ready: false, reason: "disabled" });
        return null;
      }
      try {
        const scriptUrl =
          cfg.workerUrl ||
          new URL("./Worker/NexaWebWorker.js", import.meta.url).href;
        this.nexaWorker = createNexaWorkerClient(scriptUrl);
        setNexaWorkerInfo({
          ready: true,
          scriptUrl,
          storage: cfg.storage !== false,
          debug: !!cfg.debug,
        });
        if (typeof window !== "undefined" && window.dispatchEvent) {
          window.dispatchEvent(
            new CustomEvent("nexaWorkerReady", {
              detail: { scriptUrl, config: cfg, nexaWorker: this.nexaWorker },
            })
          );
        }
        if (cfg.debug) {
          console.info("[NexaWorker] siap — fetch Storage lewat thread terpisah:", scriptUrl);
        }
        return this.nexaWorker;
      } catch (e) {
        console.warn("NexaWorker init failed:", e);
        this.nexaWorker = null;
        setNexaWorkerInfo({ ready: false, reason: String(e && e.message ? e.message : e) });
        if (typeof window !== "undefined" && window.dispatchEvent) {
          window.dispatchEvent(
            new CustomEvent("nexaWorkerFailed", {
              detail: { error: e, config: cfg },
            })
          );
        }
        return null;
      }
    },
    /** Registrasi Service Worker (bukan Web Worker) — cache/BG sync terpisah */
    nexaServiceWorkerRegistration: null,
    /**
     * @param {{ enabled?: boolean, scriptUrl?: string, scope?: string, backgroundSync?: boolean, debug?: boolean }} swConfig
     * enabled: true = daftar; enabled: false = hapus registrasi SW (scriptUrl) agar tidak aktif.
     */
    initNexaServiceWorker: function (swConfig = {}) {
      return registerNexaServiceWorkerModule(swConfig).then((reg) => {
        this.nexaServiceWorkerRegistration = reg;
        return reg;
      });
    },
    unregisterNexaServiceWorker: function (swConfig = {}) {
      return unregisterNexaServiceWorkerModule(swConfig);
    },
    /**
     * Background Sync — panggil saat request gagal/offline; tag default nexa-background-sync.
     * @param {string} [tag]
     */
    registerNexaBackgroundSync: function (tag) {
      const t = tag || "nexa-background-sync";
      const reg = this.nexaServiceWorkerRegistration;
      if (reg) {
        return registerNexaBackgroundSyncModule(reg, t);
      }
      if (typeof navigator !== "undefined" && navigator.serviceWorker) {
        return navigator.serviceWorker.ready.then((r) =>
          registerNexaBackgroundSyncModule(r, t)
        );
      }
      return Promise.resolve(false);
    },
    initNexaPwa: function (pwaConfig = {}) {
      return registerNexaPwaModule(pwaConfig);
    },
    NexaFederated,
    Federated:NexaFederated,
    // Crypto system (Enhanced wrapper with optional key)
    NexaCrypto: function (key = null) {
      if (key) {
        // Return enhanced crypto instance with predefined key
        return {
          // Basic methods (no key required)
          encode: (data) => NexaCrypto.encode(data),
          decode: (data) => NexaCrypto.decode(data),
          encodeJson: (jsonString) => NexaCrypto.encodeJson(jsonString),
          decodeToJson: (data) => NexaCrypto.decodeToJson(data),
          isValidBase64: (data) => NexaCrypto.isValidBase64(data),
          generateKey: (length) => NexaCrypto.generateKey(length),
          isSupported: () => NexaCrypto.isSupported(),

          // Key-based methods (using predefined key)
          encodeWithKey: (data) => NexaCrypto.encodeWithKey(data, key),
          decodeWithKey: (data) => NexaCrypto.decodeWithKey(data, key),
          createKey: (string) => NexaCrypto.createKey(string || key),

          // Access to original class and key
          raw: NexaCrypto,
          key: key,
        };
      }
      // Return original class if no key provided
      return NexaCrypto;
    },
    Crypto: function (key = null) {
      if (key) {
        // Return enhanced crypto instance with predefined key
        return {
          // Basic methods (no key required)
          encode: (data) => NexaCrypto.encode(data),
          decode: (data) => NexaCrypto.decode(data),
          encodeJson: (jsonString) => NexaCrypto.encodeJson(jsonString),
          decodeToJson: (data) => NexaCrypto.decodeToJson(data),
          isValidBase64: (data) => NexaCrypto.isValidBase64(data),
          generateKey: (length) => NexaCrypto.generateKey(length),
          isSupported: () => NexaCrypto.isSupported(),

          // Key-based methods (using predefined key)
          encodeWithKey: (data) => NexaCrypto.encodeWithKey(data, key),
          decodeWithKey: (data) => NexaCrypto.decodeWithKey(data, key),
          createKey: (string) => NexaCrypto.createKey(string || key),

          // Access to original class and key
          raw: NexaCrypto,
          key: key,
        };
      }
      // Return original class if no key provided
      return NexaCrypto;
    },



    // Add NexaKit features with shorthand methods
    id: (elementId) => nexaKitInstance.id(elementId),
    class: (className) => nexaKitInstance.class(className),
    classAll: (className) => nexaKitInstance.classAll(className),
    selector: (cssSelector) => nexaKitInstance.selector(cssSelector),
    selectorAll: (cssSelector) => nexaKitInstance.selectorAll(cssSelector),
    createElement: (tagName, attributesOrContent) => {
      // Jika parameter kedua adalah string, treat as content
      if (typeof attributesOrContent === "string") {
        const element = nexaKitInstance.createElement(tagName);
        element.innerHTML = attributesOrContent;
        return element;
      }
      // Jika parameter kedua adalah object, treat as attributes
      return nexaKitInstance.createElement(tagName, attributesOrContent);
    },
    /** Get/set innerHTML via selector (bukan template eventload — itu `NXUI.html(file, vars, template)`) */
    uiHtml: (selector, value) => {
      if (value !== undefined) {
        return nexaKitInstance.selector(selector).html(value);
      }
      return nexaKitInstance.selector(selector).html();
    },
    addID: (selector, additionalId) =>
      nexaKitInstance.selector(selector).addID(additionalId),

    // ===== ELEMENT FACTORY — fluent HTML builder =====
    // Usage: NXUI.div().id('x').class('y').style({ color: 'red' }).html('Hello')
    //        → '<div id="x" class="y" style="color: red;">Hello</div>'
    tag:        (tagName, c) => nexaKitInstance.tag(tagName, c),
    div:        (c) => nexaKitInstance.div(c),
    span:       (c) => nexaKitInstance.span(c),
    p:          (c) => nexaKitInstance.p(c),
    a:          (href) => nexaKitInstance.a(href),
    button:     (c) => nexaKitInstance.button(c),
    btn:        (handler) => nexaKitInstance.btn(handler),
    input:      (c) => nexaKitInstance.input(c),
    section:    (c) => nexaKitInstance.section(c),
    article:    (c) => nexaKitInstance.article(c),
    header:     (c) => nexaKitInstance.header(c),
    footer:     (c) => nexaKitInstance.footer(c),
    nav:        (c) => nexaKitInstance.nav(c),
    aside:      (c) => nexaKitInstance.aside(c),
    table:      (c) => nexaKitInstance.table(c),
    thead:      (c) => nexaKitInstance.thead(c),
    tbody:      (c) => nexaKitInstance.tbody(c),
    tr:         (c) => nexaKitInstance.tr(c),
    td:         (c) => nexaKitInstance.td(c),
    th:         (c) => nexaKitInstance.th(c),
    strong:     (c) => nexaKitInstance.strong(c),
    em:         (c) => nexaKitInstance.em(c),
    small:      (c) => nexaKitInstance.small(c),
    code:       (c) => nexaKitInstance.code(c),
    pre:        (c) => nexaKitInstance.pre(c),
    blockquote: (c) => nexaKitInstance.blockquote(c),
    icon:       (classes) => nexaKitInstance.icon(classes),

    // Direct access to instances
    ui: nexaKitInstance,

    // Convenience methods for common tasks
    setData: (key, value) => nexaGlobalInstance.setData(key, value),
    getData: (key) => nexaGlobalInstance.getData(key),
    showArray: (arr) => nexaGlobalInstance.showArray(arr),
    showObject: (obj) => nexaGlobalInstance.showObject(obj),
    getElementData: (elementId, clean = true) =>
      nexaGlobalInstance.getElementData(elementId, clean),
    getFormData: (formId) => nexaGlobalInstance.getFormData(formId),
    showAllData: () => nexaGlobalInstance.showAllData(),
    clearData: (key) => nexaGlobalInstance.clearData(key),

    // Enhanced element selection with combined features
    element: (selector) => {
      // If selector starts with #, use ID selection
      if (selector.startsWith("#")) {
        return nexaKitInstance.id(selector.substring(1));
      }
      // If selector starts with ., use class selection
      else if (selector.startsWith(".")) {
        return nexaKitInstance.class(selector.substring(1));
      }
      // Otherwise use general selector
      else {
        return nexaKitInstance.selector(selector);
      }
    },

    // Quick access methods
    $: (selector) => {
      if (selector.startsWith("#")) {
        return nexaKitInstance.id(selector.substring(1));
      } else if (selector.startsWith(".")) {
        return nexaKitInstance.class(selector.substring(1));
      } else {
        return nexaKitInstance.selector(selector);
      }
    },

    // Global data management helpers
    setGlobal: function (key, value) {
      this[key] = value; // Will trigger auto-sync to window
      return this;
    },

    getGlobal: function (key) {
      return this[key] || window[key];
    },

    hasGlobal: function (key) {
      return key in this || key in window;
    },

    deleteGlobal: function (key) {
      delete this[key];
      delete window[key];
      return this;
    },
  };

  // Populate NXUI with all features
  Object.assign(window.NXUI, nxuiBase);

  // Create NXUI with Proxy for auto-sync to window
  window.NXUI = new Proxy(window.NXUI, {
    set(target, property, value) {
      // Set property di NXUI
      target[property] = value;

      // Auto-sync data properties ke window (kecuali methods dan private properties)
      if (
        typeof value !== "function" &&
        !property.startsWith("_") &&
        property !== "constructor" &&
        property !== "prototype"
      ) {
        window[property] = value;
      }

      return true;
    },
    get(target, property) {
      // Return property dari NXUI dulu
      if (property in target) {
        return target[property];
      }

      // Fallback ke window untuk backward compatibility
      if (property in window && typeof window[property] !== "undefined") {
        return window[property];
      }

      return undefined;
    },
  });

  // Create NX as shorthand for NXUI with enhanced features
  window.nx = {
    // All NXUI features
    ...window.NXUI,

    // Direct access to original instances for advanced usage
    _global: nexaGlobalInstance,
    _ui: nexaKitInstance,
  };

  // Enhanced Proxy for backward compatibility with dynamic function access
  // This ensures functions assigned to nx are also available globally for modal callbacks
  const originalNx = window.nx;
  window.nx = new Proxy(originalNx, {
    set(target, property, value) {
      // Set property ke nx instance
      target[property] = value;

      // Jika property adalah function, juga buat global function untuk backward compatibility
      if (typeof value === "function" && property !== "constructor") {
        window[property] = value;
      }

      return true;
    },
    get(target, property) {
      // Return property from nx first
      if (property in target) {
        return target[property];
      }

      // Fallback to window for backward compatibility
      if (property in window && typeof window[property] === "function") {
        return window[property];
      }

      return undefined;
    },
  });

  // NX: alias pendek, sama referensi dan perilaku dengan NXUI (Proxy sinkron data + get fallback)
  window.NX = window.NXUI;

  // Hubungkan NexaKit builder ke NXUI.Worker sehingga .worker() terminal bekerja
  // Contoh: const html = await NXUI.div().id('box').container().p().view('hi').worker();
  if (window.NXUI.Worker?.ready !== false) {
    _nxSetWorker(window.NXUI.Worker);
  }

  // Initialize NexaDb ref for NXUI.ref access (required by NexaRoute)
  // Pastikan NEXA tersedia atau gunakan fallback
  (async () => {
    try {
      // Pastikan NEXA object tersedia dengan semua property yang diperlukan
      // NEXA.url seharusnya sudah di-set oleh NexaPage dari url di routes.js
      // Jangan set url di sini karena akan di-override oleh NexaPage
      if (typeof window.NEXA === 'undefined') {
        window.NEXA = {
          // Jangan set url di sini, tunggu dari NexaPage
          userId: 0,
          controllers: {
            packages: 'packages',
            Storage: Storage
          }
        };
      } else {
        // Pastikan controllers dan Storage tersedia
        if (!window.NEXA.controllers) {
          window.NEXA.controllers = {
            packages: 'packages',
            Storage: Storage
          };
        } else if (!window.NEXA.controllers.Storage) {
          window.NEXA.controllers.Storage = Storage;
        }
      }
      
      // Set bucketsInBackground setelah NEXA.url tersedia (akan di-update di bagian lain)
      // Jangan set di sini karena NEXA.url mungkin belum tersedia
     
      // TUNGGU NEXA.url tersedia SEBELUM membuat NexaDb
      // NexaDb constructor memanggil dbName() yang membutuhkan NEXA.url
      const waitForNEXAUrl = async (maxAttempts = 50) => {
        for (let i = 0; i < maxAttempts; i++) {
          if (window.NEXA && window.NEXA.url && window.NEXA.url !== '') {
            return true;
          }
          // Tunggu 100ms sebelum cek lagi
          await new Promise(resolve => setTimeout(resolve, 100));
        }
        return false;
      };
      
      // Tunggu NEXA.url tersedia (maksimal 5 detik)
      const hasUrl = await waitForNEXAUrl();
      
      // Jika masih belum ada setelah 5 detik, gunakan fallback
      if (!hasUrl) {
        const fallbackUrl = normalizeUrl(window.location.origin || 'NexaStoreDB');
        window.NEXA.url = fallbackUrl;
        window.NEXA.apiBase = resolveNexaApiBase(fallbackUrl);
      }

      // Pastikan NEXA.controllers dan NEXA.apiBase tersedia untuk Storage()
      if (!window.NEXA.controllers) {
        const baseUrl = normalizeUrl(window.NEXA.url || window.location.origin || '');
        const apiRoot = window.NEXA.apiBase || resolveNexaApiBase(baseUrl);
        window.NEXA.controllers = {
          packages: 'packages',
          bucketsInBackground: normalizeUrl(joinUrl(apiRoot, 'user', 'Fetch')),
          Storage: Storage
        };
      } else {
        // Pastikan Storage tersedia di controllers
        if (!window.NEXA.controllers.Storage) {
          window.NEXA.controllers.Storage = Storage;
        }
      }
      if (!window.NEXA.apiBase) {
        const baseUrl = normalizeUrl(window.NEXA.url || window.location.origin || '');
        window.NEXA.apiBase = resolveNexaApiBase(baseUrl);
      }
      if (window.NEXA.userId === undefined) {
        window.NEXA.userId = 0;
      }

      // Satukan inisialisasi DB lewat getDb() (tunggu url + singleton)
      if (window.NXUI && typeof window.NXUI.getDb === "function") {
        await window.NXUI.getDb();
      }

      // Restore NEXA.tabel dari persistent storage (agar tidak hilang saat refresh)
      try {
        const tabeldata = await NXUI.ref.get("bucketsStore", "tabel");
        if (tabeldata?.submenu && Array.isArray(tabeldata.submenu) && tabeldata.submenu.length > 0) {
          if (!window.NEXA.tabel) window.NEXA.tabel = {};
          window.NEXA.tabel.submenu = tabeldata.submenu;
        }
      } catch (_) {}
    } catch (error) {
      // Failed to initialize NexaDb ref
    }
  })();

  // Dispatch event bahwa NXUI sudah siap
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      window.dispatchEvent(new CustomEvent("nxuiReady"));
    });
  } else {
    window.dispatchEvent(new CustomEvent("nxuiReady"));
  }
}
export function NexaHtml() {
  // Helper function untuk normalize URL: fix double slashes, ensure HTTPS if page is HTTPS
  const normalizeUrl = (url) => {
    if (!url) return url;
    
    // Ensure HTTPS if page is loaded over HTTPS
    if (typeof window !== 'undefined' && window.location.protocol === 'https:') {
      url = url.replace(/^http:\/\//i, 'https://');
    }
    
    // Fix double slashes (except after protocol like http:// or https://)
    url = url.replace(/([^:]\/)\/+/g, '$1');
    
    return url;
  };

  // Helper function untuk join URL parts dengan aman (hindari double slash)
  const joinUrl = (...parts) => {
    if (parts.length === 0) return '';
    if (parts.length === 1) return normalizeUrl(String(parts[0]));
    
    const firstPart = String(parts[0] || '');
    const restParts = parts.slice(1)
      .filter(part => part) // Remove empty parts
      .map(part => String(part).replace(/^\/+|\/+$/g, '')); // Remove leading/trailing slashes
    
    // If first part is a full URL (http:// or https://)
    if (firstPart.match(/^https?:\/\//)) {
      const baseUrl = firstPart.replace(/\/+$/, ''); // Remove trailing slashes
      const path = restParts.join('/');
      return normalizeUrl(path ? baseUrl + '/' + path : baseUrl);
    }
    
    // Otherwise, treat as relative path
    const allParts = [firstPart.replace(/^\/+|\/+$/g, ''), ...restParts]
      .filter(part => part);
    return normalizeUrl('/' + allParts.join('/'));
  };

  // Normalize base URL to handle both cases:
  // Local: "http://localhost" (no trailing slash)
  // Online: "https://adm.tatiye.net/" (with trailing slash)
  // Gunakan window.NEXA dengan fallback untuk memastikan tersedia saat refresh
  const getNEXA = () => {
    // Prioritas: window.NEXA > global NEXA > fallback
    if (typeof window !== 'undefined' && window.NEXA) {
      return window.NEXA;
    }
    if (typeof NEXA !== 'undefined') {
      return NEXA;
    }
    // Fallback jika NEXA belum tersedia (hanya origin, jangan pathname)
    const fallbackUrl = normalizeUrl(window.location.origin);
    return {
      url: fallbackUrl,
      userId: 0,
      controllers: {
        packages: 'packages',
        bucketsInBackground: normalizeUrl(joinUrl(fallbackUrl, 'api', 'user', 'Fetch')),
        Storage: Storage
      },
      apiBase: normalizeUrl(joinUrl(fallbackUrl, 'api'))
    };
  };

  // Get nexaFetch with fallback
  const getNexaFetch = () => {
    // Prioritas: imported nexaFetch > window.nexaFetch > window.NXUI.nexaFetch
    if (typeof nexaFetch !== 'undefined' && nexaFetch) {
      return nexaFetch;
    }
    if (typeof window !== 'undefined' && window.nexaFetch) {
      return window.nexaFetch;
    }
    if (typeof window !== 'undefined' && window.NXUI && window.NXUI.nexaFetch) {
      return window.NXUI.nexaFetch;
    }
    throw new Error('nexaFetch is not available. Make sure NexaFetch is properly imported.');
  };

  const nexa = getNEXA();
  // Hanya gunakan NEXA.url atau origin, jangan pernah gunakan pathname
  const base = normalizeUrl(nexa.url || window.location.origin || '');
  const baseMarkdownURL = normalizeUrl(joinUrl(base, 'eventMarkdownload'));
  const basePckg = (nexa.controllers && nexa.controllers.packages ? nexa.controllers.packages : 'packages') + "/";
  
  /**
   * Process markdown HTML content: extract scripts, load Prism, and highlight code
   */
  async function processMarkdownContent(htmlContent) {
    if (!htmlContent || typeof htmlContent !== 'string') {
      return htmlContent;
    }

    // Extract script tags, link tags, and style tags from HTML
    const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    const linkRegex = /<link[^>]*href=["']([^"']+)["'][^>]*>/gi;
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    const scripts = [];
    const links = [];
    const styles = [];
    let processedHtml = htmlContent;

    // Extract and remove script tags
    processedHtml = processedHtml.replace(scriptRegex, (match, scriptContent) => {
      scripts.push(scriptContent);
      return ''; // Remove script tag from HTML
    });

    // Extract and remove link tags (for Prism CSS)
    processedHtml = processedHtml.replace(linkRegex, (match, href) => {
      if (href && (href.includes('prism') || href.includes('highlight'))) {
        links.push({ type: 'link', href: href, fullTag: match });
        return ''; // Remove link tag from HTML
      }
      return match; // Keep other links
    });

    // Extract and remove style tags (for Prism styles only)
    // Keep paragraph styles and other non-Prism styles in HTML
    processedHtml = processedHtml.replace(styleRegex, (match, styleContent) => {
      // Only extract Prism-related styles
      if (styleContent && (
        styleContent.includes('prism') || 
        styleContent.includes('language-') || 
        styleContent.includes('code[class*=')
      )) {
        // Check if it's NOT a paragraph style (has data-paragraph-style attribute)
        if (!match.includes('data-paragraph-style')) {
          styles.push(styleContent);
          return ''; // Remove style tag from HTML (will be loaded separately)
        }
      }
      // Keep paragraph styles and other styles in HTML (don't extract)
      return match;
    });

    // Load Prism CSS and styles if not already loaded
    const loadPrismCSS = () => {
      // Always load Prism CSS (check if already exists)
      const prismCSSUrl = './prism/index.css';
      const existingPrismCSS = document.querySelector(`link[href="${prismCSSUrl}"]`);
      if (!existingPrismCSS) {
        const linkEl = document.createElement('link');
        linkEl.rel = 'stylesheet';
        linkEl.href = prismCSSUrl;
        linkEl.onerror = () => {};
        linkEl.onload = () => {};
        document.head.appendChild(linkEl);
      }
      
      // Load extracted link tags (external CSS)
      links.forEach(link => {
        if (link.href && !document.querySelector(`link[href="${link.href}"]`)) {
          const linkEl = document.createElement('link');
          linkEl.rel = 'stylesheet';
          linkEl.href = link.href;
          linkEl.onerror = () => {};
          linkEl.onload = () => {};
          document.head.appendChild(linkEl);
        }
      });
      
      // Always add Prism override styles (for transparent background)
      // const prismOverrideStyle = `
      //   /* Prism.js override styles */
      //   :not(pre)>code[class*=language-], pre[class*=language-] {
      //     background: transparent !important;
      //   }
      //   code[class*=language-], pre[class*=language-] {
      //     background: transparent !important;
      //   }
      //   pre[class*=language-] {
      //     padding: 16px;
      //     overflow: auto;
      //     border-radius: 6px;
      //     margin: 16px 0;
      //     font-size: 85%;
      //     line-height: 1.45;
      //     font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
      //   }
      //   code[class*=language-] {
      //     font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
      //   }
      // `;
      
      // Check if override style already exists
      const existingOverride = document.querySelector('style[data-prism-override]');
      if (!existingOverride) {
        const styleEl = document.createElement('style');
        styleEl.setAttribute('data-prism-override', 'true');
        // styleEl.textContent = prismOverrideStyle;
        document.head.appendChild(styleEl);
      }
      
      // Load extracted style tags (inline CSS)
      styles.forEach(styleContent => {
        if (!styleContent) return;
        
        // Check if this style is already in the document
        const existingStyles = Array.from(document.querySelectorAll('style'));
        const styleExists = existingStyles.some(style => {
          const existingContent = style.textContent || '';
          return existingContent.includes(styleContent.substring(0, 50));
        });
        
        if (!styleExists) {
          const styleEl = document.createElement('style');
          styleEl.textContent = styleContent;
          styleEl.setAttribute('data-prism-style', 'true');
          document.head.appendChild(styleEl);
        }
      });
    };

    // Load Prism.js if not already loaded
    const loadPrismJS = () => {
      return new Promise((resolve, reject) => {
        if (typeof Prism !== 'undefined') {
          resolve();
          return;
        }

        // Check if Prism is already being loaded
        const existingScript = document.querySelector('script[src*="prism-core"]');
        if (existingScript) {
          // Wait for Prism to load
          let attempts = 0;
          const maxAttempts = 50; // 5 seconds max wait
          const checkPrism = setInterval(() => {
            attempts++;
            if (typeof Prism !== 'undefined') {
              clearInterval(checkPrism);
              resolve();
            } else if (attempts >= maxAttempts) {
              clearInterval(checkPrism);
              reject(new Error('Prism.js failed to load'));
            }
          }, 100);
          return;
        }

        // Load Prism core
        const prismCore = document.createElement('script');
        prismCore.src = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js';
        prismCore.onerror = () => {
          reject(new Error('Failed to load Prism core'));
        };
        prismCore.onload = () => {
          // Load Prism autoloader
          const prismAutoloader = document.createElement('script');
          prismAutoloader.src = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js';
          prismAutoloader.onerror = () => {
            reject(new Error('Failed to load Prism autoloader'));
          };
          prismAutoloader.onload = () => {
            if (typeof Prism !== 'undefined') {
              if (Prism.plugins && Prism.plugins.autoloader) {
                Prism.plugins.autoloader.languages_path = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/';
              }
              resolve();
            } else {
              reject(new Error('Prism is undefined after loading'));
            }
          };
          document.head.appendChild(prismAutoloader);
        };
        document.head.appendChild(prismCore);
      });
    };

    // Execute extracted scripts
    const executeScripts = () => {
      scripts.forEach(scriptContent => {
        try {
          // Check if script contains Prism.highlightAll
          if (scriptContent && scriptContent.includes('Prism.highlightAll')) {
            // Don't execute immediately, will be called after content is in DOM
            return;
          }
          // Execute other scripts
          if (scriptContent) {
            const script = document.createElement('script');
            script.textContent = scriptContent;
            document.head.appendChild(script);
          }
        } catch (e) {
          // Silent fail
        }
      });
    };

    // Load Prism resources
    try {
      loadPrismCSS();
      await loadPrismJS();
      // Execute scripts (except Prism.highlightAll)
      executeScripts();
    } catch (error) {
      // Continue anyway, Prism might already be loaded
    }

    // Return processed HTML (without script/link tags, they're already loaded)
    return processedHtml;
  }

  /**
   * Initialize Prism highlighting for dynamically loaded content
   * Call this after inserting markdown HTML content into DOM
   */
  function highlightMarkdownContent(containerElement) {
    // Wait for Prism to be available
    const tryHighlight = (attempts = 0) => {
      if (typeof Prism === 'undefined') {
        if (attempts < 50) {
          // Wait up to 5 seconds for Prism to load
          setTimeout(() => tryHighlight(attempts + 1), 100);
        }
        return;
      }

      // Wait a bit for DOM to be ready
      setTimeout(() => {
        try {
          if (containerElement) {
            // Highlight code blocks within the container
            const codeBlocks = containerElement.querySelectorAll('pre code[class*="language-"], code[class*="language-"]');
            if (codeBlocks.length > 0) {
              codeBlocks.forEach(block => {
                if (block && !block.classList.contains('language-none')) {
                  try {
                    Prism.highlightElement(block);
                  } catch (e) {
                    // Silent fail
                  }
                }
              });
            }
          } else {
            // Highlight all code blocks on page
            Prism.highlightAll();
          }
        } catch (error) {
          // Silent fail
        }
      }, 200);
    };

    tryHighlight();
  }

  /**
   * Base untuk eventload / eventMarkdownload.
   * Prioritas `nexaPage.urlApi` (endpoint.urlApi di App.js) agar POST ke server PHP/API, bukan origin yang hanya mengembalikan index.html SPA.
   * Tanpa urlApi → NEXA.url.
   */
  function resolveNexaBaseForEvents(currentNEXA) {
    if (
      typeof window !== "undefined" &&
      window.nexaPage &&
      typeof window.nexaPage.urlApi === "string" &&
      window.nexaPage.urlApi.trim() !== ""
    ) {
      return normalizeUrl(window.nexaPage.urlApi.trim());
    }
    return normalizeUrl(currentNEXA.url || window.location.origin || "");
  }

  /** NexaFetch bisa mengembalikan string (text/html) — ubah ke bentuk error yang jelas */
  function normalizeJsonApiResponse(data) {
    if (data == null) return data;
    if (typeof data === "string") {
      const t = data.trim();
      if (t.startsWith("<!DOCTYPE") || /^<html[\s>]/i.test(t)) {
        return {
          success: false,
          content: null,
          error: new Error(
            "Respons bukan JSON: server mengembalikan dokumen HTML (mis. halaman SPA). Pastikan backend melayani POST /eventload; gunakan NEXA.apiBase (endpoint.urlApi) jika PHP/API tidak di origin yang sama dengan endpoint.url."
          ),
          rawHtmlResponse: true,
        };
      }
      try {
        return JSON.parse(t);
      } catch (e) {
        return {
          success: false,
          content: null,
          error: new Error((e && e.message ? e.message : String(e)) + ": " + t.substring(0, 120)),
        };
      }
    }
    return data;
  }

  function resolveHtmlTemplateFolder(explicit) {
    if (explicit != null && String(explicit).trim() !== "") {
      return String(explicit).replace(/^\/+|\/+$/g, "");
    }
    if (
      typeof window !== "undefined" &&
      window.nexaPage &&
      typeof window.nexaPage.appRoot === "string" &&
      window.nexaPage.appRoot.trim() !== ""
    ) {
      return window.nexaPage.appRoot.trim().replace(/^\/+|\/+$/g, "");
    }
    return "template";
  }

  /** Respons fetch yang sebenarnya adalah dokumen HTML (mis. fallback SPA untuk route .md). */
  function responseTextLooksLikeHtmlDocument(s) {
    if (s == null || typeof s !== "string") return false;
    const t = s.trim();
    return /^<!DOCTYPE\s+html/i.test(t) || /^<html[\s>]/i.test(t);
  }

  function textFromFetchRaw(raw) {
    if (typeof raw === "string") return raw;
    if (raw != null && typeof raw === "object" && "content" in raw && raw.content != null) {
      return String(raw.content);
    }
    return String(raw ?? "");
  }

  /**
   * Tambah id pada h1–h6 (slug GFM/GitHub) agar tautan [teks](#slug) di daftar isi bisa scroll.
   */
  async function applyHeadingAnchorIds(html) {
    if (typeof document === "undefined" || !html || typeof DOMParser === "undefined") {
      return html;
    }
    try {
      const slugMod = await import("https://esm.sh/github-slugger@2.0.0");
      const GitHubSlugger = slugMod.default || slugMod.GitHubSlugger;
      if (!GitHubSlugger) return html;
      const slugger = new GitHubSlugger();
      const doc = new DOMParser().parseFromString(
        '<div class="nexa-md-root">' + html + "</div>",
        "text/html"
      );
      const root = doc.querySelector(".nexa-md-root");
      if (!root) return html;
      root.querySelectorAll("h1, h2, h3, h4, h5, h6").forEach((h) => {
        if (h.getAttribute("id")) return;
        const text = (h.textContent || "").trim();
        if (!text) return;
        h.id = slugger.slug(text);
      });
      return root.innerHTML;
    } catch (e) {
      return html;
    }
  }

  /**
   * `<base href="/">` membuat `href="#slug"` ter-resolve ke `/#slug` (path aktif hilang).
   * Ubah jadi `{pathname}#slug` agar cocok dengan route SPA (mis. /markdown#slug).
   * @param {string} [explicitPath] — opsi; default `window.location.pathname`
   */
  function rewriteMarkdownHashOnlyAnchors(html, explicitPath) {
    if (typeof window === "undefined" || !html || typeof html !== "string") {
      return html;
    }
    let path =
      explicitPath != null && String(explicitPath).trim() !== ""
        ? String(explicitPath).trim()
        : window.location.pathname || "/";
    path = path.replace(/\/+$/, "");
    if (path === "") path = "/";
    return html.replace(/\bhref=(["'])#([^"']*)\1/gi, (full, quote, frag) => {
      if (frag === "") return full;
      return "href=" + quote + path + "#" + frag + quote;
    });
  }

  /**
   * Markdown teks → HTML (marked lewat esm.sh). Tanpa jaringan: fallback escape + &lt;br&gt;.
   */
  async function renderMarkdownToHtml(markdown) {
    if (markdown == null) return "";
    const md = String(markdown);
    try {
      const mod = await import("https://esm.sh/marked@12.0.0");
      const markedFn = mod.marked || mod.default;
      let html = "";
      if (markedFn && typeof markedFn.parse === "function") {
        html = markedFn.parse(md);
      } else if (typeof markedFn === "function") {
        html = markedFn(md);
      }
      if (html) {
        return await applyHeadingAnchorIds(html);
      }
    } catch (e) {
      /* fallback */
    }
    return (
      '<div class="nexa-md-fallback">' +
      md
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\n/g, "<br>\n") +
      "</div>"
    );
  }

  return {
    html: async function (row, endpoints, template, options = {}) {
      const currentNEXA = getNEXA();
      const templateFolder = resolveHtmlTemplateFolder(template);
      const baseName = String(row || "").replace(/\.html$/i, "");
      const templateFile = templateFolder + "/" + baseName + ".html";

      let baseOrigin =
        typeof window !== "undefined" && window.location && window.location.origin
          ? window.location.origin
          : normalizeUrl(currentNEXA.url || "");
      if (
        options &&
        typeof options.templateOrigin === "string" &&
        options.templateOrigin.trim() !== ""
      ) {
        baseOrigin = normalizeUrl(options.templateOrigin.trim());
      }

      const currentBaseURL = normalizeUrl(joinUrl(baseOrigin, templateFile));
      const fetchInstance = getNexaFetch();

      try {
        const raw = await fetchInstance.get(currentBaseURL, options);
        let html =
          typeof raw === "string"
            ? raw
            : raw != null && typeof raw === "object" && "content" in raw && raw.content != null
              ? String(raw.content)
              : String(raw ?? "");

        if (endpoints && typeof endpoints === "object") {
          // `{{kunci}}` (opsional, lama); `{kunci}` — `(?<!\{)` agar tidak memotong `{{kunci}}`
          html = html
            .replace(/\{\{(\w+)\}\}/g, (_, key) =>
              Object.prototype.hasOwnProperty.call(endpoints, key)
                ? String(endpoints[key] ?? "")
                : `{{${key}}}`
            )
            .replace(/(?<!\{)\{(\w+)\}/g, (full, key) =>
              Object.prototype.hasOwnProperty.call(endpoints, key)
                ? String(endpoints[key] ?? "")
                : full
            );
        }

        return {
          success: true,
          content: html,
          /** Pasang setelah `innerHTML`: `result.hydrate(container, { user: [...] })` — pakai `NexaForge.hydrate` */
          hydrate: (root, lists) => NexaForge.hydrate(root, lists),
        };
      } catch (error) {
        return {
          success: false,
          error: error,
          content: null,
          hydrate: null,
        };
      }
    },
    /**
     * Muat `.md` seperti `html()` memuat `.html`: GET `{appRoot}/{row}.md` dari origin (bukan POST API).
     * Substitusi `{kunci}` / `{{kunci}}` sama seperti `html`. MD → HTML via `marked` (esm.sh), lalu Prism.
     * @param {{ fromString?: boolean, anchorPath?: string }} options
     * @param {boolean} [options.fromString] — jika true, `fileOrContent` adalah teks Markdown mentah (tanpa GET).
     * @param {string} [options.anchorPath] — path untuk tautan `#anchor` (default: `location.pathname`; untuk `<base href="/">`).
     */
    Markdown: async function (fileOrContent, variables = {}, template, options = {}) {
      const currentNEXA = getNEXA();
      const templateFolder = resolveHtmlTemplateFolder(template);
      const baseName = String(fileOrContent || "")
        .replace(/\.md$/i, "")
        .replace(/\.html$/i, "");
      const templateFile = templateFolder + "/" + baseName + ".md";

      let baseOrigin =
        typeof window !== "undefined" && window.location && window.location.origin
          ? window.location.origin
          : normalizeUrl(currentNEXA.url || "");
      if (
        options &&
        typeof options.templateOrigin === "string" &&
        options.templateOrigin.trim() !== ""
      ) {
        baseOrigin = normalizeUrl(options.templateOrigin.trim());
      }

      const fetchInstance = getNexaFetch();

      try {
        let mdText;
        if (options && options.fromString === true) {
          mdText = String(fileOrContent ?? "");
        } else {
          const primaryUrl = normalizeUrl(joinUrl(baseOrigin, templateFile));
          let raw = await fetchInstance.get(primaryUrl, options);
          mdText = textFromFetchRaw(raw);

          /* Dev server SPA sering mengembalikan index.html untuk GET /templates/*.md — coba assets/markdown/ */
          if (responseTextLooksLikeHtmlDocument(mdText)) {
            const assetFallback = normalizeUrl(
              joinUrl(baseOrigin, "assets", "markdown", baseName + ".md")
            );
            if (assetFallback !== primaryUrl) {
              try {
                raw = await fetchInstance.get(assetFallback, options);
                const alt = textFromFetchRaw(raw);
                if (!responseTextLooksLikeHtmlDocument(alt)) {
                  mdText = alt;
                }
              } catch (e) {
                /* tetap pakai mdText pertama untuk pesan error di bawah */
              }
            }
          }

          if (responseTextLooksLikeHtmlDocument(mdText)) {
            return {
              success: false,
              content: null,
              error: new Error(
                "Bukan Markdown: server mengembalikan HTML (biasanya fallback SPA untuk /" +
                  templateFile +
                  "). Taruh salinan di /assets/markdown/" +
                  baseName +
                  ".md atau layani file .md sebagai statis."
              ),
              hydrate: null,
            };
          }
        }

        if (variables && typeof variables === "object") {
          mdText = mdText
            .replace(/\{\{(\w+)\}\}/g, (_, key) =>
              Object.prototype.hasOwnProperty.call(variables, key)
                ? String(variables[key] ?? "")
                : `{{${key}}}`
            )
            .replace(/(?<!\{)\{(\w+)\}/g, (full, key) =>
              Object.prototype.hasOwnProperty.call(variables, key)
                ? String(variables[key] ?? "")
                : full
            );
        }

        const htmlRaw = await renderMarkdownToHtml(mdText);
        let htmlOut = await processMarkdownContent(htmlRaw);
        htmlOut = rewriteMarkdownHashOnlyAnchors(
          htmlOut,
          options && options.anchorPath != null ? options.anchorPath : undefined
        );

        const data = {
          success: true,
          content: htmlOut,
          hydrate: (root, lists) => NexaForge.hydrate(root, lists),
        };

        data.highlight = function (containerElement) {
          highlightMarkdownContent(containerElement);
        };

        data.insertAndHighlight = function (containerSelector) {
          const container =
            typeof containerSelector === "string"
              ? document.querySelector(containerSelector)
              : containerSelector;
          if (!container) {
            return;
          }
          container.innerHTML = data.content;
          setTimeout(() => {
            highlightMarkdownContent(container);
          }, 300);
        };

        data.getContent = function () {
          return data.content;
        };

        data.ensurePrismLoaded = async function () {
          return new Promise((resolve) => {
            if (typeof Prism !== "undefined") {
              resolve();
              return;
            }
            const loadPrism = async () => {
              try {
                const prismCSSUrl =
                  "./prism/index.css";
                if (!document.querySelector(`link[href="${prismCSSUrl}"]`)) {
                  const linkEl = document.createElement("link");
                  linkEl.rel = "stylesheet";
                  linkEl.href = prismCSSUrl;
                  document.head.appendChild(linkEl);
                }
                if (!document.querySelector('script[src*="prism-core"]')) {
                  const prismCore = document.createElement("script");
                  prismCore.src =
                    "https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js";
                  await new Promise((resolveCore) => {
                    prismCore.onload = () => {
                      const prismAutoloader = document.createElement("script");
                      prismAutoloader.src =
                        "https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js";
                      prismAutoloader.onload = () => {
                        if (
                          typeof Prism !== "undefined" &&
                          Prism.plugins &&
                          Prism.plugins.autoloader
                        ) {
                          Prism.plugins.autoloader.languages_path =
                            "https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/";
                        }
                        resolveCore();
                      };
                      document.head.appendChild(prismAutoloader);
                    };
                    document.head.appendChild(prismCore);
                  });
                }
                let attempts = 0;
                const checkPrism = setInterval(() => {
                  if (typeof Prism !== "undefined") {
                    clearInterval(checkPrism);
                    resolve();
                  } else if (attempts++ > 50) {
                    clearInterval(checkPrism);
                    resolve();
                  }
                }, 100);
              } catch (error) {
                resolve();
              }
            };
            loadPrism();
          });
        };

        return data;
      } catch (error) {
        return {
          success: false,
          content: null,
          error: error,
          hydrate: null,
        };
      }
    }
  };
}

/** Alias modul: `import { NexaDom } from "./Nexa.js"` — sama dengan NexaHtml() */
export function NexaDom() {
  return NexaHtml();
}

/**
 * Get database reference (ref) untuk akses IndexedDB
 * Menginisialisasi NexaDb dan mengembalikan ref
 * Menunggu NEXA.url (dari NexaPage) atau fallback ke origin — jangan panggil new NexaDb sebelum url ada.
 */
let _dbInitPromise = null;
export async function getDb() {
  if (typeof window !== "undefined" && window.NXUI && window.NXUI.ref) {
    return window.NXUI.ref;
  }
  if (_dbInitPromise) {
    return _dbInitPromise;
  }
  _dbInitPromise = (async () => {
    if (typeof window !== "undefined") {
      window.NEXA = window.NEXA || {};
      for (let i = 0; i < 50; i++) {
        if (window.NEXA.url && window.NEXA.url !== "") {
          break;
        }
        await new Promise((r) => setTimeout(r, 100));
      }
      if (!window.NEXA.url || window.NEXA.url === "") {
        const origin = (window.location && window.location.origin) || "NexaStoreDB";
        if (window.NXUI && typeof window.NXUI.updateNEXAUrl === "function") {
          window.NXUI.updateNEXAUrl(origin);
        } else {
          window.NEXA.url = origin;
        }
      }
    }
    const db = new NexaDb();
    await db.initDatabase();
    const ref = await db.Ref();
    if (typeof window !== "undefined" && window.NXUI && ref) {
      window.NXUI.ref = ref;
    }
    return ref;
  })();
  return _dbInitPromise;
}
export async function applications(row,data) {

  const agenda = await NXUI.Storage().sdk(row+"/app",data);
  const crypto = NXUI.Crypto("NexaApp");
  const Config = crypto.decode(agenda.token);
  return Config;
}
export async function appBuckets(row,data) {
  const agenda = await NXUI.Storage().sdk(row+"/buckets",data);
  const crypto = NXUI.Crypto("NexaBuckets");
  const Config = crypto.decode(agenda.token);
   const tabel = await NXUI.ref.set('nexaStore', Config);
  return Config;
}

export  function htmlDecode(value) {
 return String(value || "")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&#x27;/g, "'")
    .replace(/&#x2F;/g, "/")
    .replace(/&#x60;/g, "`")
    .replace(/&#x3D;/g, "=");
}
const clickSound = new NexaClick();
// Initialize saat DOM loaded
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => clickSound.init());
} else {
  clickSound.init();
}
// Jangan await getDb() di sini — modul ini load sebelum App.js mengisi NEXA.url; inisialisasi lewat getDb() + IIFE di atas.
// Export for ES6 modules
export {
  NexaDb,
  NexaRoute,
  NexaPage,
  NexaPage as Page,
  NexaTatiye,
  NexaTatiye as Tatiye,
  NexaGlobal,
  NexaKit,
  NexaSidebar,
  initSidebar,
  getSidebarInstance,
  updateSidebarPath,
  NexaGrid,
  NexaModal,
  nexaModal,
  modalHTML,
  modalHTML as Modal,
  ensureModalStylesheet,
  ensureFormStylesheet,
  NexaTables,
  ensureTableStylesheet,
  rowsFromStorageResponse,
  createSlug,
  findBySlug,
  parseSlug,
  extractIdFromSlug,
  setSlugId,
  getSlugId,
  NexaDomClass as NexaDomClass,
  StorageData,
  StorageModelData,
  NexaForge,
  NexaDomextractor,
  NexaType,
  fileType,
  // NexaElectron,
  // ROUTE_WINDOW_LAYOUT_SHELL,
  // ROUTE_WINDOW_LAYOUT_COMPACT,
  // ROUTE_WINDOW_HANDOFF_PREFIX,
};
export default {
  NexaDb,
  NexaRoute,
  NexaPage,
  Page: NexaPage,
  NexaTatiye,
  Tatiye: NexaTatiye,
  NexaGlobal,
  NexaKit,
  NexaSidebar,
  initSidebar,
  getSidebarInstance,
  updateSidebarPath,
  NexaGrid,
  NexaModal,
  nexaModal,
  modalHTML,
  Modal: modalHTML,
  ensureModalStylesheet,
  ensureFormStylesheet,
  NexaTables,
  ensureTableStylesheet,
  rowsFromStorageResponse,
  createSlug,
  findBySlug,
  parseSlug,
  extractIdFromSlug,
  setSlugId,
  getSlugId,
  NexaDomClass,
  StorageData,
  StorageModelData,
  NexaForm,
  NexaWild,
  NexaWizard,
  NexaType,
  Type: NexaType,
  fileType,
  // NexaElectron,
  // Electron: NexaElectron,
  // ROUTE_WINDOW_LAYOUT_SHELL,
  // ROUTE_WINDOW_LAYOUT_COMPACT,
  // ROUTE_WINDOW_HANDOFF_PREFIX,
};
