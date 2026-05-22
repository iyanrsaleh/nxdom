/**
 * Tabel data interaktif (filter teks, sort kolom, paginasi).
 * Stylesheet tabel + paginasi + form assets + tombol + select2 harus disertakan di halaman (mis. `index.html`), bukan dimuat dari sini.
 */

import {
  sanitizeFileName,
  dateStamp,
  downloadFile,
  toCSV,
  toXLSXHtml,
  exportToPDF,
} from "./NexaTablesExport.js";

/**
 * No-op: pemutusan tautan CSS dinamis — muat `table.css`, `pagination.css`, `assets/css/form.css`, `button.css`, `select2.min.css` lewat `<link>` di HTML.
 * Tetap diekspor agar `NXUI.ensureTableStylesheet()` / `await ensureTableStylesheet()` tidak patah.
 */
export async function ensureTableStylesheet() {
  if (typeof document === "undefined") return;
}

/**
 * Normalisasi respons Storage / API menjadi array baris.
 * @param {*} res — objek atau array (mis. `{ status, data: [...] }`, `{ response: [...] }`)
 * @returns {Array<object>}
 */
export function rowsFromStorageResponse(res) {
  if (res == null) return [];
  if (Array.isArray(res)) return res;
  if (Array.isArray(res.data)) return res.data;
  if (Array.isArray(res.response)) return res.response;
  if (Array.isArray(res.rows)) return res.rows;
  // Support nested shapes used by Storage().model().get() / API wrappers
  if (res.data && Array.isArray(res.data.rows)) return res.data.rows;
  if (res.data && Array.isArray(res.data.data)) return res.data.data;
  return [];
}

function inferColumns(rows) {
  if (!rows.length) return [];
  const keys = new Set();
  rows.forEach((row) => {
    if (row && typeof row === "object" && !Array.isArray(row)) {
      Object.keys(row).forEach((k) => keys.add(k));
    }
  });
  return [...keys].map((key) => ({ key, title: key }));
}

/** Tampilan teks header kolom: `_` dianggap spasi (mis. Tanggal_lahir → Tanggal lahir). */
function formatColumnHeaderLabel(raw) {
  return String(raw ?? "").replaceAll("_", " ");
}

function cellText(value) {
  if (value == null) return "";
  if (typeof value === "object") {
    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  }
  return String(value);
}

function compareValues(a, b) {
  if (a == null && b == null) return 0;
  if (a == null) return -1;
  if (b == null) return 1;
  if (typeof a === "number" && typeof b === "number") return a - b;
  const sa = cellText(a);
  const sb = cellText(b);
  const na = Number(sa);
  const nb = Number(sb);
  if (!Number.isNaN(na) && !Number.isNaN(nb) && sa !== "" && sb !== "") {
    return na - nb;
  }
  return sa.localeCompare(sb, undefined, { numeric: true, sensitivity: "base" });
}

/**
 * @typedef {{ key: string, title?: string }} NexaTablesColumn
 * @typedef {{
 *   container: string|Element,
 *   data?: Array<object>,
 *   columns?: NexaTablesColumn[],
 *   pageSize?: number,
 *   pageSizeOptions?: number[],
 *   paginationSize?: 'default'|'sm',
 *   paginationClass?: string,
 *   pageInfoClass?: string,
 *   searchable?: boolean,
 *   sortable?: boolean,
 *   tableClass?: string,
 *   wrapperClass?: string,
 *   caption?: string,
 *   formatCell?: (value: unknown, key: string, row: object) => string,
 *   paginationActiveBg?: string|null,
 *   paginationActiveBorder?: string|null,
 *   paginationActiveColor?: string|null,
 *   export?: {
 *     enabled?: boolean,
 *     types?: Array<'csv'|'json'|'xlsx'|'pdf'>,
 *     include?: 'filtered'|'all'|'page',
 *     fileName?: string
 *   },
 *   spinner?: {
 *     enabled?: boolean,
 *     centerScreen?: boolean,
 *     type?: 'overlay'|'inline'|'button',
 *     size?: 'small'|'medium'|'large',
 *     color?: string,
 *     position?: 'center'|'top'|'bottom',
 *     message?: string,
 *   },
 *   storage?: {
 *     model: string,
 *     select?: string|string[],
 *     query?: (builder: any) => any,
 *   },
 *   columnMenu?: boolean,
 *   actions?: {
 *     edit?: boolean,
 *     delete?: boolean,
 *     view?: boolean,
 *     add?: boolean,
 *     export?: boolean,
 *     import?: boolean,
 *     print?: boolean,
 *     share?: boolean,
 *   },
 *   actionsColumnTitle?: string,
 *   onAction?: (action: string, row: object) => void,
 *   editing?: {
 *     [key: string]: {
 *       type: 'text'|'number'|'checkbox'|'select'|'textarea'|'search'|'email'|'password'|'tel'|'url'|'date'|'datetime-local'|'time'|'color'|'range',
 *       options?: Array<string|{ value: string, label?: string }>,
 *       min?: number,
 *       max?: number,
 *       step?: number,
 *     }
 *   },
 *   onEdit?: (key: string, value: unknown, row: object) => void,
 *   rowNumberColumn?: boolean,
 *   rowNumberColumnTitle?: string,
 *   hideColumnKeys?: string[],
 * }} NexaTablesOptions
 */
export class NexaTables {
  /**
   * @param {NexaTablesOptions} options
   */
  constructor(options = {}) {
    // console.log('options:', options);
    const c = options.container;
    this.container =
      typeof c === "string" ? document.querySelector(c) : c;
    if (!this.container) {
      throw new Error("NexaTables: container tidak ditemukan.");
    }

    // Persist pagination state per-container.
    const containerKey = typeof c === "string" ? c : this.container?.id ? `#${this.container.id}` : "default";
    const safeKey = String(containerKey).replace(/[^a-zA-Z0-9_-]/g, "_");
    this._pageSizeStorageKey  = `NexaTables_pageSize_${safeKey}`;
    this._pageStorageKey      = `NexaTables_page_${safeKey}`;
    this._colWidthsStorageKey = `NexaTables_colWidths_${safeKey}`;

    // Column resize state
    this._colWidths = this._loadColWidths(); // { [colKey]: widthPx }
    this._colgroup  = null;

    this.columns = options.columns;
    this.pageSize = Math.max(1, options.pageSize ?? 10);

    // Server-side pagination
    this.serverSide = options.serverSide === true;
    this._serverTotal = (this.serverSide && Number.isFinite(options.serverTotal)) ? options.serverTotal : 0;
    this.onFetchPage = typeof options.onFetchPage === "function" ? options.onFetchPage : null;
    this._fetchingPage = false;
    this._serverSearch = "";        // current server-side search term
    this._searchDebounceTimer = null; // debounce handle for server search

    this.pageSizeOptions =
      Array.isArray(options.pageSizeOptions) && options.pageSizeOptions.length
        ? options.pageSizeOptions
            .map((n) => parseInt(n, 10))
            .filter((n) => Number.isFinite(n) && n > 0)
        : [10, 25, 50, 100];
    this.paginationSize = options.paginationSize === "sm" ? "sm" : "default";
    this.paginationClass =
      options.paginationClass ??
      (this.paginationSize === "sm" ? "pagination-sm" : "pagination");
    this.pageInfoClass =
      options.pageInfoClass ??
      (this.paginationSize === "sm" ? "page-info-sm" : "page-info");
    this.searchable = options.searchable !== false;
    this.sortable = options.sortable !== false;

    const DEFAULT_NX_TABLE_CLASSES =
      "nx-table nx-table-striped nx-table-hover nx-table-md nx-table-bordered";
    const settingClassRaw =
      options.setting &&
      typeof options.setting === "object" &&
      !Array.isArray(options.setting)
        ? String(options.setting.classTabel ?? "").trim()
        : "";
    if (typeof options.tableClass === "string" && options.tableClass.trim()) {
      this.tableClass = options.tableClass.trim();
    } else if (!settingClassRaw || settingClassRaw === "nx-table") {
      this.tableClass = DEFAULT_NX_TABLE_CLASSES;
    } else {
      this.tableClass = settingClassRaw;
    }

    this.wrapperClass = options.wrapperClass ?? "";
    this.caption = options.caption ?? "";
    this.formatCell = typeof options.formatCell === "function" ? options.formatCell : null;

    // Optional data loader (mirrors NexaDom `storage: { model, select?, query? }`).
    // If provided and `data` is not set, NexaTables will fetch rows dari `NXUI.Storage().model(...).select(...).get()`.
    this.storageModel = options.storage || null;

    this.exportOptions = options.export || null;

    /** Menu filter/sort per kolom (dropdown) di header */
    this.columnMenu = options.columnMenu !== false;

    // Row actions dropdown di ujung tabel.
    this.actions = options.actions || null;
    this.actionsColumnTitle = options.actionsColumnTitle || "Actions";
    this.onAction =
      typeof options.onAction === "function" ? options.onAction : null;
    this._hasRowActions =
      !!this.actions && Object.values(this.actions).some((v) => Boolean(v));

    // Inline editing per kolom.
    this.editing = options.editing || null;
    this.onEdit =
      typeof options.onEdit === "function" ? options.onEdit : null;

    /** Jika diisi, hanya kolom yang ada dalam array ini yang bisa diedit (meski `editing` mencakup semua kolom). */
    this.inlineColumns = Array.isArray(options.inline) && options.inline.length
      ? options.inline.map((k) => String(k).trim()).filter(Boolean)
      : null;

    /** Kolom pertama penomoran urut (1…n) mengikuti halaman + pageSize */
    this.rowNumberColumn = options.rowNumberColumn === true;
    this.rowNumberColumnTitle = options.rowNumberColumnTitle ?? "No.";

    /** Kunci kolom yang tidak ditampilkan (tetap ada di objek baris, mis. `id` sensitif) */
    this.hideColumnKeys = Array.isArray(options.hideColumnKeys)
      ? options.hideColumnKeys.filter((k) => typeof k === "string" && k.trim())
      : [];

    /** Override warna `.pagination .page-link.active` (dan hover halaman aktif) per instance */
    this.paginationActiveBg = options.paginationActiveBg ?? null;
    this.paginationActiveBorder = options.paginationActiveBorder ?? null;
    this.paginationActiveColor = options.paginationActiveColor ?? null;

    this._paginationDomId = null;
    this._paginationStyleEl = null;

    /** Konfigurasi spinner sederhana (mirip NexaDom), dipakai saat load storage model di mount(). */
    const spin = options.spinner || {};
    this.spinner = {
      enabled: !!spin.enabled,
      centerScreen: !!spin.centerScreen,
      type: spin.type || "overlay",
      size: spin.size || "medium",
      color: spin.color || "#007bff",
      position: spin.position || "center",
      message: spin.message || "",
    };
    this._spinnerEl = null;

    this._data = Array.isArray(options.data) ? [...options.data] : [];
    this._filtered = [...this._data];
    this._sortKey = null;
    this._sortDir = "asc";
    this._page = 0;

    this._hydratePaginationState();

    // Per kolom: { [colKey]: filterString }
    this._columnFilters = {};
    this._columnMenuEl = null;
    this._activeColumnMenuKey = null;
    this._columnMenuDebounceTimer = null;
    this._columnMenuDocAttached = false;
    this._columnMenuValueDatalistId =
      `nx-table-col-datalist-${Math.random().toString(36).slice(2)}`;
    this._boundDocMouseDown = (e) => {
      try {
        if (!this._columnMenuEl || !this._activeColumnMenuKey) return;
        const target = e.target;
        // Jangan tutup menu kalau user memilih value dari datalist/option.
        if (target?.tagName === "OPTION") return;
        if (target?.closest?.("datalist")) return;
        const menu = this._columnMenuEl;
        if (menu.contains(target)) return;
        const menuBtn = target?.closest?.(".nx-table-col-menu");
        if (menuBtn) return;
        this._closeColumnMenu();
      } catch {
        // ignore
      }
    };

    // Row actions dropdown (<details>) — track semua instance supaya bisa ditutup saat klik di luar.
    this._actionDetails = new Set();
    this._actionsDocHandlerAttached = false;
    this._boundActionsDocMouseDown = (e) => {
      try {
        if (!this._actionDetails || this._actionDetails.size === 0) return;
        const target = e.target;
        this._actionDetails.forEach((detailsEl) => {
          if (!detailsEl) return;
          if (!detailsEl.contains(target)) {
            detailsEl.removeAttribute("open");
          }
        });
      } catch {
        /* ignore */
      }
    };

    // Handler klik sel untuk inline editing.
    this._cellClickHandler = (e) => this._onCellClick(e);

    this._root = null;
    this._table = null;
    this._tbody = null;
    this._searchInput = null;
    this._paginationEl = null;
    this._captionEl = null;

    this._boundFilter = () => this._applyFilter();
    this._sortHandler = (e) => this._onSortClick(e);

    // Keep references for potential cleanup.
    this._exportButtons = [];
  }

  _hydratePaginationState() {
    try {
      if (typeof window === "undefined" || !window.localStorage) return;
      const ps = parseInt(window.localStorage.getItem(this._pageSizeStorageKey), 10);
      if (!Number.isNaN(ps) && ps > 0) this.pageSize = ps;
      // In server-side mode, always start at page 0 — initial data is fetched from page 0.
      // Restoring _page from localStorage would show wrong row numbers against server-fetched page-0 data.
      if (!this.serverSide) {
        const pg = parseInt(window.localStorage.getItem(this._pageStorageKey), 10);
        if (!Number.isNaN(pg) && pg >= 0) this._page = pg;
      }
    } catch {
      /* ignore */
    }
  }

  _persistPageSize() {
    try {
      if (typeof window === "undefined" || !window.localStorage) return;
      window.localStorage.setItem(this._pageSizeStorageKey, String(this.pageSize));
    } catch {
      /* ignore */
    }
  }

  _persistPage() {
    try {
      if (typeof window === "undefined" || !window.localStorage) return;
      window.localStorage.setItem(this._pageStorageKey, String(this._page));
    } catch {
      /* ignore */
    }
  }

  /**
   * Bangun instance dari respons Storage (ekstrak array otomatis).
   * @param {string|Element} container
   * @param {*} storageResult
   * @param {Omit<NexaTablesOptions, "container"|"data">} [options]
   * @returns {Promise<NexaTables>}
   */
  /**
   * Inject scroll CSS (nx-scroll-x, nx-scroll-thin) once per document.
   * Uses a data attribute to avoid duplicate injections.
   */
  static _injectScrollStyles() {
    if (document.querySelector("style[data-nexa-scroll]")) return;
    const style = document.createElement("style");
    style.setAttribute("data-nexa-scroll", "1");
    style.textContent = `
/* NexaTables — scroll wrapper styles (from nx-scroll module) */
.nx-scroll-x {
  overflow-x: auto;
  overflow-y: hidden;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}
.nx-scroll-x::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.nx-scroll-x::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}
.nx-scroll-x::-webkit-scrollbar-thumb {
  background: #bbb;
  border-radius: 4px;
}
.nx-scroll-x::-webkit-scrollbar-thumb:hover {
  background: #888;
}
.nx-scroll-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
/* dark mode */
body.dark-mode-grid .nx-scroll-x::-webkit-scrollbar-track { background: var(--main-code-bg, #1e1e1e); }
body.dark-mode-grid .nx-scroll-x::-webkit-scrollbar-thumb { background: var(--border-primary, #444); }
body.dark-mode-grid .nx-scroll-x::-webkit-scrollbar-thumb:hover { background: var(--menu-hover-bg, #666); }
`;
    document.head.appendChild(style);
  }

  static async fromStorageResponse(container, storageResult, options = {}) {
    const rows = rowsFromStorageResponse(storageResult);
    const t = new NexaTables({
      ...options,
      container,
      data: rows,
    });
    await t.mount();
    return t;
  }

  /**
   * Ambil data lewat `NXUI.Storage` (GET / POST / `api` POST), lalu `fromStorageResponse`.
   * Pola setingkat `NexaDom` + `storage: { model }`, tetapi untuk **NexaTables** (tanpa `render` HTML string).
   *
   * @param {string|Element} container
   * @param {{
   *   method?: 'get'|'post'|'api',
   *   url?: string,
   *   path?: string,
   *   options?: object,
   *   query?: object,
   *   body?: object,
   *   apiBody?: object,
   *   fetchOptions?: object,
   *   acceptObjectAsRow?: boolean,
   * }} load
   * @param {Omit<NexaTablesOptions, "container"|"data">} [tableOptions]
   * @returns {Promise<NexaTables>}
   *
   * - **get** (default): `Storage().get(load.url || load.path, load.options || load.query)`
   * - **post**: `Storage().post(load.path || load.url, load.body, load.fetchOptions)`
   * - **api**: `Storage().api(load.path || load.apiPath || load.url, load.body || load.apiBody)` — POST ke `baseAPI` (sama seperti `Storage().api("test", { … })` di route)
   * - **acceptObjectAsRow**: jika respons bukan array dan `rowsFromStorageResponse` kosong, bungkus satu objek `data` (atau root) jadi satu baris
   */
  static async fromStorage(container, load = {}, tableOptions = {}) {
    const storage = window.NXUI?.Storage?.();
    if (!storage || typeof storage.get !== "function") {
      throw new Error("NexaTables.fromStorage: NXUI.Storage tidak tersedia");
    }
    const method = String(load.method || "get").toLowerCase();
    let raw;
    if (method === "post") {
      raw = await storage.post(
        load.path || load.url,
        load.body ?? {},
        load.fetchOptions ?? {}
      );
    } else if (method === "api") {
      raw = await storage.api(
        load.path || load.apiPath || load.url,
        load.body ?? load.apiBody ?? {}
      );
    } else {
      raw = await storage.get(
        load.url || load.path,
        load.options ?? load.query ?? {}
      );
    }

    if (load.acceptObjectAsRow) {
      const rows = rowsFromStorageResponse(raw);
      if (
        rows.length === 0 &&
        raw &&
        typeof raw === "object"
      ) {
        const cand =
          raw.data !== undefined && raw.data !== null ? raw.data : raw;
        if (
          cand &&
          typeof cand === "object" &&
          !Array.isArray(cand)
        ) {
          raw = { ...raw, data: [cand] };
        }
      }
    }

    return NexaTables.fromStorageResponse(container, raw, tableOptions);
  }

  /**
   * Ambil data lewat `NXUI.Storage().buckets(app).get(getOptions)` (query / federated),
   * lalu `fromStorageResponse` — pola sama seperti `about.js` (objek `app` + alias).
   *
   * @param {string|Element} container
   * @param {object} app — konfigurasi bucket (alias, aliasNames, tabelName, operasi, access, id, …)
   * @param {object} [getOptions] — argumen ke `.get()`, mis. `{ limit: 50 }`
   * @param {Omit<NexaTablesOptions, "container"|"data">} [tableOptions]
   * @returns {Promise<NexaTables>}
   */
  static async fromBuckets(container, app, getOptions = {}, tableOptions = {}) {
    const storage = window.NXUI?.Storage?.();
    if (!storage || typeof storage.buckets !== "function") {
      throw new Error("NexaTables.fromBuckets: NXUI.Storage tidak tersedia");
    }
    const raw = await storage.buckets(app).get(getOptions);
    return NexaTables.fromStorageResponse(container, raw, tableOptions);
  }

  _isHiddenColumnKey(key) {
    if (!key || !Array.isArray(this.hideColumnKeys)) return false;
    return this.hideColumnKeys.includes(key);
  }

  getColumns() {
    let baseCols;
    if (this.columns && this.columns.length) {
      baseCols = this.columns.filter(
        (c) => c && c.key && !this._isHiddenColumnKey(c.key)
      );
    } else {
      baseCols = inferColumns(this._data).filter(
        (c) => c && c.key && !this._isHiddenColumnKey(c.key)
      );
    }

    if (this.rowNumberColumn) {
      baseCols = [
        { key: "__rowNo__", title: this.rowNumberColumnTitle || "No." },
        ...baseCols,
      ];
    }

    if (this._hasRowActions) {
      const exists = baseCols.some((c) => c && c.key === "__actions__");
      if (!exists) {
        return [
          ...baseCols,
          { key: "__actions__", title: this.actionsColumnTitle },
        ];
      }
    }
    return baseCols;
  }

  /**
   * @param {Array<object>} rows
   */
  setData(rows) {
    this._data = Array.isArray(rows) ? [...rows] : [];
    this._sortKey = null;
    this._sortDir = "asc";
    this._page = 0;
    this._applyFilter();
  }

  _applyFilter() {
    const q = (this._searchInput?.value || "").trim().toLowerCase();

    // In server-side mode: send search to server via debounced _fetchServerPage(0).
    if (this.serverSide) {
      const newSearch = q; // already trimmed + lowercased above
      if (newSearch !== this._serverSearch) {
        // Search term changed — debounce then re-fetch from page 0.
        this._serverSearch = newSearch;
        if (this._searchDebounceTimer) clearTimeout(this._searchDebounceTimer);
        this._searchDebounceTimer = setTimeout(() => {
          this._page = 0;
          this._fetchServerPage(0);
        }, 350);
      } else {
        // No search change (e.g. called from column-menu sort or column filter) — re-render local data with column filters.
        let rows = Array.isArray(this._data) ? [...this._data] : [];
        const colFilters = this._columnFilters || {};
        const activeColEntries = Object.entries(colFilters)
          .filter(([_, v]) => v != null && String(v).trim() !== "")
          .map(([k, v]) => [k, String(v).trim().toLowerCase()]);
        if (activeColEntries.length) {
          rows = rows.filter((row) => {
            if (!row || typeof row !== "object") return false;
            return activeColEntries.every(([k, fv]) => {
              const val = cellText(row?.[k]).toLowerCase();
              return val.includes(fv);
            });
          });
        }
        this._filtered = rows;
        if (this._sortKey) this._sortRows();
        const maxPage = Math.max(0, Math.ceil(this._filtered.length / this.pageSize) - 1);
        if (this._page > maxPage) this._page = maxPage;
        this._renderBody();
        this._renderPagination();
      }
      return;
    }

    // ── Client-side mode below ──

    // 1) Column filters
    let rows = Array.isArray(this._data) ? [...this._data] : [];
    const colFilters = this._columnFilters || {};
    const activeColEntries = Object.entries(colFilters)
      .filter(([_, v]) => v != null && String(v).trim() !== "")
      .map(([k, v]) => [k, String(v).trim().toLowerCase()]);

    if (activeColEntries.length) {
      rows = rows.filter((row) => {
        if (!row || typeof row !== "object") return false;
        return activeColEntries.every(([k, fv]) => {
          const val = cellText(row?.[k]).toLowerCase();
          return val.includes(fv);
        });
      });
    }

    // 2) Global search
    if (q) {
      rows = rows.filter((row) => {
        if (!row || typeof row !== "object") return false;
        return Object.values(row).some((v) =>
          cellText(v).toLowerCase().includes(q)
        );
      });
    }

    this._filtered = rows;

    if (this._sortKey) {
      this._sortRows();
    }
    const maxPage = Math.max(
      0,
      Math.ceil(this._filtered.length / this.pageSize) - 1
    );
    if (this._page > maxPage) this._page = maxPage;
    this._persistPage();
    this._renderBody();
    this._renderPagination();
  }

  /**
   * Load data dari `NXUI.Storage().model().select().get()` menggunakan pola:
   * `storage: { model, select?, query?(builder) }`
   */
  async _loadStorageModelDataIfNeeded() {
    if (!this.storageModel || typeof this.storageModel.model !== "string") return;
    if (Array.isArray(this._data) && this._data.length > 0) return; // data precedence

    const storage = window.NXUI?.Storage?.();
    if (!storage || typeof storage.model !== "function") return;

    const model = this.storageModel.model;
    const sel =
      this.storageModel.select !== undefined && this.storageModel.select !== null
        ? this.storageModel.select
        : "*";

    let q = storage.model(model).select(sel);
    if (typeof this.storageModel.query === "function") {
      const out = this.storageModel.query(q);
      if (out) q = out;
    }

    const res = await q.get();
    const rows = rowsFromStorageResponse(res);
    this._data = rows;
    this._filtered = [...rows];
  }

  _sortRows() {
    const key = this._sortKey;
    if (!key) return;
    const dir = this._sortDir === "desc" ? -1 : 1;
    this._filtered.sort((a, b) => dir * compareValues(a?.[key], b?.[key]));
  }

  _onSortClick(e) {
    // Jangan trigger sort saat klik menu filter kolom
    if (e.target?.closest?.(".nx-table-col-menu")) return;
    const th = e.target.closest("th.sortable");
    if (!th || !this._table.contains(th)) return;
    const key = th.getAttribute("data-col");
    if (!key) return;
    if (this._sortKey === key) {
      this._sortDir = this._sortDir === "asc" ? "desc" : "asc";
    } else {
      this._sortKey = key;
      this._sortDir = "asc";
    }
    this._sortRows();
    this._page = 0;
    this._persistPage();
    this._renderBody();
    this._renderPagination();
    this._updateSortIndicators();
  }

  _updateSortIndicators() {
    if (!this._table) return;
    this._table.querySelectorAll("thead th.sortable").forEach((th) => {
      const key = th.getAttribute("data-col");
      const base = th.getAttribute("data-title") || key;
      if (this._sortKey === key) {
        th.setAttribute(
          "aria-sort",
          this._sortDir === "asc" ? "ascending" : "descending"
        );
        // Switch indikator sort menjadi ikon Font Awesome.
        // Ascending: A–Z / kecil → besar
        // Descending: Z–A / besar → kecil
        const ascIcon = "fas fa-arrow-down-a-z";
        const descIcon = "fas fa-arrow-up-z-a";
        const iconClass = this._sortDir === "asc" ? ascIcon : descIcon;

        const titleEl = th.querySelector(".nx-table-th-title");
        if (titleEl) titleEl.textContent = base;

        const iconEl = th.querySelector(".nx-table-sort-icon");
        if (iconEl) {
          iconEl.className = iconClass;
          iconEl.style.display = "inline-block";
        }
      } else {
        th.removeAttribute("aria-sort");
        const titleEl = th.querySelector(".nx-table-th-title");
        if (titleEl) titleEl.textContent = base;
        const iconEl = th.querySelector(".nx-table-sort-icon");
        if (iconEl) {
          iconEl.className = "nx-table-sort-icon";
          iconEl.style.display = "none";
        }
      }
    });
  }

  _pageSlice() {
    // In server-side mode, the server already applied offset — always slice from index 0.
    // This ensures initial over-fetch (e.g. 200 rows) still shows only pageSize rows.
    if (this.serverSide) return this._filtered.slice(0, this.pageSize);
    const start = this._page * this.pageSize;
    return this._filtered.slice(start, start + this.pageSize);
  }

  /**
   * Nilai unik (string) untuk satu kolom dari `this._data` — dipakai untuk opsi inline `type: "search"`.
   * @param {string} colKey
   * @returns {string[]}
   */
  _getDistinctColumnValues(colKey) {
    const rows = Array.isArray(this._data) ? this._data : [];
    const set = new Set();
    rows.forEach((r) => {
      if (!r || typeof r !== "object") return;
      const v = r[colKey];
      const s = cellText(v).trim();
      if (s !== "") set.add(s);
    });
    return Array.from(set).sort((a, b) =>
      a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" })
    );
  }

  // ─── Column resize ──────────────────────────────────────────────────────────

  _loadColWidths() {
    try {
      const raw = window.localStorage?.getItem(this._colWidthsStorageKey);
      return raw ? JSON.parse(raw) : {};
    } catch { return {}; }
  }

  _saveColWidths() {
    try {
      window.localStorage?.setItem(this._colWidthsStorageKey, JSON.stringify(this._colWidths));
    } catch { /* ignore */ }
  }

  /** Default min-width (px) for a column if no stored width exists */
  _defaultColMinWidth(colKey) {
    if (colKey === "__rowNo__")  return 52;
    if (colKey === "__actions__") return 80;
    return 140;
  }

  /** Build / rebuild <colgroup> applying stored widths */
  _buildColgroup() {
    const cg   = document.createElement("colgroup");
    const cols = this.getColumns();
    cols.forEach((col) => {
      const colEl = document.createElement("col");
      colEl.dataset.col = col.key;
      const w = this._colWidths[col.key];
      if (w) {
        colEl.style.width = `${w}px`;
      } else {
        colEl.style.width = `${this._defaultColMinWidth(col.key)}px`;
      }
      cg.appendChild(colEl);
    });
    return cg;
  }

  /** Sync <colgroup> widths after a resize drag */
  _applyColWidths() {
    if (!this._colgroup) return;
    this._colgroup.querySelectorAll("col[data-col]").forEach((colEl) => {
      const key = colEl.dataset.col;
      const w   = this._colWidths[key];
      colEl.style.width = w ? `${w}px` : "";
    });
  }

  /**
   * Attach drag-to-resize handles on every <th>.
   * A thin (4 px) absolutely-positioned div sits on the right edge of each header cell.
   * Dragging it updates the matching <col> width and persists to localStorage.
   */
  _initColResize() {
    if (!this._table) return;
    const ths = this._table.querySelectorAll("thead tr th");
    ths.forEach((th) => {
      const colKey = th.getAttribute("data-col");
      if (!colKey) return;

      // Make <th> position:relative so the handle is positioned relative to it
      th.style.position = "relative";
      th.style.userSelect = "none";

      const handle = document.createElement("div");
      handle.className = "nx-col-resize-handle";
      handle.style.cssText = [
        "position:absolute",
        "top:0",
        "right:0",
        "width:5px",
        "height:100%",
        "cursor:col-resize",
        "z-index:1",
        "background:transparent",
      ].join(";");

      // Hover highlight
      handle.addEventListener("mouseenter", () => { handle.style.background = "rgba(9,105,218,.35)"; });
      handle.addEventListener("mouseleave", () => { handle.style.background = "transparent"; });

      handle.addEventListener("mousedown", (e) => {
        e.preventDefault();
        e.stopPropagation();

        const startX   = e.clientX;
        const startW   = th.offsetWidth;

        const onMove = (mv) => {
          const newW = Math.max(40, startW + (mv.clientX - startX));
          // Update <col>
          const colEl = this._colgroup?.querySelector(`col[data-col="${CSS.escape(colKey)}"]`);
          if (colEl) colEl.style.width = `${newW}px`;
        };

        const onUp = (mu) => {
          document.removeEventListener("mousemove", onMove);
          document.removeEventListener("mouseup",   onUp);
          handle.style.background = "transparent";

          const finalW = Math.max(40, startW + (mu.clientX - startX));
          this._colWidths[colKey] = finalW;
          this._saveColWidths();
        };

        document.addEventListener("mousemove", onMove);
        document.addEventListener("mouseup",   onUp);
      });

      th.appendChild(handle);
    });
  }

  _renderHead() {
    const cols = this.getColumns();
    const thead = document.createElement("thead");
    const tr = document.createElement("tr");
    cols.forEach((col) => {
      const th = document.createElement("th");
      th.setAttribute("data-col", col.key);
      const headerLabel = formatColumnHeaderLabel(col.title || col.key);
      th.setAttribute("data-title", headerLabel);
      const isMetaCol = col.key === "__actions__" || col.key === "__rowNo__";
      const useRichHeader =
        !isMetaCol && (this.sortable || this.columnMenu);

      if (useRichHeader) {
        if (this.sortable) {
          th.className = "sortable";
        }
        // Jangan set `display:flex` langsung di <th> (bisa merusak layout tabel),
        // tapi pakai wrapper di dalamnya.
        const wrap = document.createElement("div");
        wrap.style.display = "flex";
        wrap.style.alignItems = "center";
        wrap.style.justifyContent = "space-between";
        wrap.style.gap = "8px";

        const title = document.createElement("span");
        title.className = "nx-table-th-title";
        title.textContent = headerLabel;

        const right = document.createElement("div");
        right.style.display = "flex";
        right.style.alignItems = "center";
        right.style.justifyContent = "flex-end";
        right.style.gap = "10px";

        if (this.sortable) {
          const icon = document.createElement("i");
          icon.className = "nx-table-sort-icon";
          icon.style.display = "none";
          icon.style.fontSize = "0.9em";
          right.appendChild(icon);
        }

        if (this.columnMenu) {
          const menuIcon = document.createElement("i");
          menuIcon.className = "nx-table-col-menu fas fa-filter";
          menuIcon.setAttribute("data-col", col.key);
          menuIcon.style.cursor = "pointer";
          menuIcon.style.fontSize = "0.9em";
          menuIcon.setAttribute("aria-label", `Column menu ${headerLabel}`);
          menuIcon.addEventListener("click", (ev) => {
            ev.stopPropagation();
            this._openColumnMenu(col.key, th);
          });
          right.appendChild(menuIcon);
        }

        wrap.appendChild(title);
        wrap.appendChild(right);
        th.appendChild(wrap);
      } else {
        th.textContent = headerLabel;
        if (col.key === "__actions__") {
          th.style.textAlign = "right";
        }
        if (col.key === "__rowNo__") {
          th.style.textAlign = "center";
          th.style.width = "3.25rem";
        }
      }
      tr.appendChild(th);
    });
    thead.appendChild(tr);
    return thead;
  }

  _buildInlineEditor(colKey, rawValue, row, cfg) {
    const type = cfg?.type || "text";
    let el;
    if (type === "checkbox") {
      el = document.createElement("input");
      el.type = "checkbox";
      el.checked = Boolean(rawValue);
      el.addEventListener("change", () => {
        const next = el.checked;
        row[colKey] = next;
        if (this.onEdit) {
          this.onEdit(colKey, next, row);
        }
        if (this.container) {
          this.container.dispatchEvent(
            new CustomEvent("nexa-table-edit", {
              detail: { key: colKey, value: next, row },
            })
          );
        }
      });
      return el;
    }

    el = document.createElement("input");
    el.type = type === "number" ? "number" : "text";
    el.className = "form-nexa-control form-nexa-control-sm";
    if (type === "number") {
      if (cfg.min != null) el.min = String(cfg.min);
      if (cfg.max != null) el.max = String(cfg.max);
      if (cfg.step != null) el.step = String(cfg.step);
    }
    el.value =
      rawValue == null
        ? ""
        : typeof rawValue === "object"
        ? JSON.stringify(rawValue)
        : String(rawValue);

    const commit = () => {
      let next;
      if (type === "number") {
        const n = Number(el.value);
        next = Number.isNaN(n) ? null : n;
      } else {
        next = el.value;
      }
      row[colKey] = next;
      if (this.onEdit) {
        this.onEdit(colKey, next, row);
      }
      if (this.container) {
        this.container.dispatchEvent(
          new CustomEvent("nexa-table-edit", {
            detail: { key: colKey, value: next, row },
          })
        );
      }
    };

    el.addEventListener("change", commit);
    el.addEventListener("blur", commit);
    el.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        commit();
      }
    });

    return el;
  }

  _buildRowActionsDropdown(row) {
    if (!this._hasRowActions || !this.actions) return null;
    const enabled = this.actions || {};

    const entries = [
      { key: "view", label: "View", icon: "fas fa-eye" },
      { key: "add", label: "Add", icon: "fas fa-plus" },
      { key: "edit", label: "Edit", icon: "fas fa-pen-to-square" },
      { key: "delete", label: "Delete", icon: "fas fa-trash" },
      { key: "export", label: "Export", icon: "fas fa-file-export" },
      { key: "import", label: "Import", icon: "fas fa-file-import" },
      { key: "print", label: "Print", icon: "fas fa-print" },
      { key: "share", label: "Share", icon: "fas fa-share-nodes" },
    ];

    const anyEnabled = entries.some((e) => Boolean(enabled[e.key]));
    if (!anyEnabled) return null;

    const details = document.createElement("details");
    details.style.position = "relative";
    details.style.display = "block";
    details.style.marginLeft = "auto";
    details.style.marginRight = "0";
    details.style.width = "100%";

    const summary = document.createElement("summary");
    summary.style.cursor = "pointer";
    summary.style.listStyle = "none";
    summary.style.display = "flex";
    summary.style.alignItems = "center";
    summary.style.justifyContent = "flex-end";
    summary.style.width = "100%";
    summary.style.height = "28px";
    summary.style.paddingRight = "6px";

    const btnIcon = document.createElement("i");
    btnIcon.className = "fas fa-ellipsis-v";
    summary.appendChild(btnIcon);
    details.appendChild(summary);

    const menu = document.createElement("div");
    menu.style.position = "absolute";
    menu.style.right = "0";
    menu.style.top = "100%";
    menu.style.background = "#ffffff";
    menu.style.border = "1px solid rgba(0,0,0,0.1)";
    menu.style.borderRadius = "8px";
    menu.style.boxShadow = "0 10px 24px rgba(0,0,0,0.12)";
    menu.style.padding = "6px";
    menu.style.minWidth = "190px";
    menu.style.zIndex = "9999";

    const actionsHandler = (actionKey) => {
      try {
        if (this.onAction) {
          this.onAction(actionKey, row);
          return;
        }
        if (this.container) {
          this.container.dispatchEvent(
            new CustomEvent("nexa-table-action", {
              detail: { action: actionKey, row },
            })
          );
        }
      } catch {
        /* ignore */
      }
    };

    entries.forEach((e) => {
      if (!enabled[e.key]) return;

      const item = document.createElement("button");
      item.type = "button";
      item.style.width = "100%";
      item.style.display = "flex";
      item.style.alignItems = "center";
      item.style.gap = "10px";
      item.style.padding = "10px 10px";
      item.style.border = "none";
      item.style.background = "transparent";
      item.style.cursor = "pointer";
      item.style.textAlign = "left";

      const i = document.createElement("i");
      i.className = e.icon;
      item.appendChild(i);

      const span = document.createElement("span");
      span.textContent = e.label;
      item.appendChild(span);

      item.addEventListener("click", (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        actionsHandler(e.key);
        details.removeAttribute("open");
      });

      item.addEventListener("mouseenter", () => {
        item.style.background = "rgba(0,0,0,0.04)";
      });
      item.addEventListener("mouseleave", () => {
        item.style.background = "transparent";
      });

      menu.appendChild(item);
    });

    details.appendChild(menu);
    details._nxActionMenu = menu;
    details._nxActionSummary = summary;

    details.addEventListener("toggle", () => {
      if (details.open) {
        this._attachRowActionsMenuPosition(details);
      } else {
        this._detachRowActionsMenuPosition(details);
      }
    });

    this._actionDetails.add(details);

    if (!this._actionsDocHandlerAttached) {
      document.addEventListener("mousedown", this._boundActionsDocMouseDown);
      this._actionsDocHandlerAttached = true;
    }

    return details;
  }

  /**
   * Menu baris memakai position:absolute di dalam area overflow — baris terakhir terpotong.
   * Saat <details> open, pindahkan ke fixed + sync posisi (scroll/resize).
   */
  _attachRowActionsMenuPosition(details) {
    const menu = details._nxActionMenu;
    const summary = details._nxActionSummary;
    if (!menu || !summary) return;

    this._detachRowActionsMenuPosition(details);

    const reposition = () => {
      if (!details.open) return;
      const r = summary.getBoundingClientRect();
      menu.style.position = "fixed";
      menu.style.right = "auto";
      menu.style.bottom = "auto";
      menu.style.display = "block";

      const mh = menu.offsetHeight || 1;
      const mw = menu.offsetWidth || 190;
      const pad = 6;

      let top = r.bottom + pad;
      if (top + mh > window.innerHeight - pad) {
        const above = r.top - mh - pad;
        if (above >= pad) {
          top = above;
        } else {
          top = Math.max(pad, window.innerHeight - mh - pad);
        }
      }

      let left = r.right - mw;
      if (left < pad) left = pad;
      if (left + mw > window.innerWidth - pad) {
        left = Math.max(pad, window.innerWidth - mw - pad);
      }

      menu.style.top = `${Math.round(top)}px`;
      menu.style.left = `${Math.round(left)}px`;
    };

    requestAnimationFrame(() => requestAnimationFrame(reposition));

    details._nxRowActionsReposition = reposition;
    window.addEventListener("scroll", reposition, true);
    window.addEventListener("resize", reposition);
  }

  _detachRowActionsMenuPosition(details) {
    if (!details) return;
    const menu = details._nxActionMenu;
    if (details._nxRowActionsReposition) {
      window.removeEventListener("scroll", details._nxRowActionsReposition, true);
      window.removeEventListener("resize", details._nxRowActionsReposition);
      details._nxRowActionsReposition = null;
    }
    if (menu) {
      menu.style.position = "absolute";
      menu.style.top = "100%";
      menu.style.left = "auto";
      menu.style.right = "0";
      menu.style.bottom = "auto";
    }
  }

  _closeColumnMenu() {
    if (this._columnMenuEl) {
      this._columnMenuEl.style.display = "none";
    }
    this._activeColumnMenuKey = null;
  }

  _openColumnMenu(colKey, thEl) {
    if (!this.columnMenu) return;
    if (!thEl) return;

    // If already open for same col, toggle off
    if (this._activeColumnMenuKey === colKey && this._columnMenuEl?.style.display !== "none") {
      this._closeColumnMenu();
      return;
    }

    this._activeColumnMenuKey = colKey;

    if (!this._columnMenuEl) {
      const panel = document.createElement("div");
      panel.className = "nx-table-column-menu";
      panel.style.position = "absolute";
      panel.style.zIndex = "9999";
      panel.style.background = "#ffffff";
      panel.style.border = "1px solid rgba(0,0,0,0.1)";
      panel.style.borderRadius = "8px";
      panel.style.boxShadow = "0 10px 24px rgba(0,0,0,0.12)";
      panel.style.padding = "10px";
      panel.style.minWidth = "240px";
      panel.style.display = "none";
      this._columnMenuEl = panel;

      const valInput = document.createElement("input");
      valInput.type = "text";
      valInput.placeholder = "Contains…";
      valInput.className = "form-nexa-control form-nexa-control-sm";
      valInput.setAttribute("aria-label", `Filter value ${colKey}`);
      valInput.setAttribute("list", this._columnMenuValueDatalistId);

      // Value input row
      const opRow = document.createElement("div");
      opRow.style.display = "flex";
      opRow.style.flexDirection = "column";
      opRow.style.gap = "8px";
      opRow.appendChild(valInput);

      // Datalist suggestions (berdasarkan data tabel)
      const datalistEl = document.createElement("datalist");
      datalistEl.id = this._columnMenuValueDatalistId;
      panel.appendChild(datalistEl);

      // Sort section
      const sortTitle = document.createElement("div");
      sortTitle.textContent = "Sort";
      sortTitle.style.fontWeight = "600";
      sortTitle.style.marginTop = "10px";
      sortTitle.style.marginBottom = "8px";
      sortTitle.style.fontSize = "0.9rem";
      sortTitle.style.color = "var(--nx-gray-700, #374151)";

      const sortItemsWrap = document.createElement("div");
      sortItemsWrap.style.display = "flex";
      sortItemsWrap.style.flexDirection = "column";
      sortItemsWrap.style.gap = "6px";

      const makeItem = (label, action) => {
        const item = document.createElement("div");
        item.textContent = label;
        item.dataset.action = action;
        item.style.cursor = "pointer";
        item.style.padding = "8px 10px";
        item.style.borderRadius = "6px";
        item.style.userSelect = "none";
        item.addEventListener("mouseenter", () => {
          item.style.background = "var(--nx-gray-100, #f3f4f6)";
        });
        item.addEventListener("mouseleave", () => {
          item.style.background = "";
        });
        return item;
      };

      const itemAsc = makeItem("Sort Ascending", "asc");
      const itemDesc = makeItem("Sort Descending", "desc");
      const itemClear = makeItem("Clear sort", "clear");

      sortItemsWrap.appendChild(itemAsc);
      sortItemsWrap.appendChild(itemDesc);
      sortItemsWrap.appendChild(itemClear);

      panel.appendChild(opRow);
      panel.appendChild(sortTitle);
      panel.appendChild(sortItemsWrap);

      // Save refs
      this._columnMenuValueInputEl = valInput;
      this._columnMenuItems = { itemAsc, itemDesc, itemClear };

      // Input debounce
      valInput.addEventListener("input", () => {
        const key = this._activeColumnMenuKey;
        if (!key) return;
        const v = valInput.value == null ? "" : String(valInput.value);
        if (v.trim() === "") delete this._columnFilters[key];
        else this._columnFilters[key] = v.trim();
        this._page = 0;
        this._persistPage();
        this._applyFilter();
      });

      // Terapkan segera saat user memilih value (datalist biasanya trigger `change`)
      valInput.addEventListener("change", () => {
        const key = this._activeColumnMenuKey;
        if (!key) return;
        const v = valInput.value == null ? "" : String(valInput.value);
        if (v.trim() === "") delete this._columnFilters[key];
        else this._columnFilters[key] = v.trim();
        this._page = 0;
        this._persistPage();
        this._applyFilter();
      });

      valInput.addEventListener("blur", () => {
        const key = this._activeColumnMenuKey;
        if (!key) return;
        const v = valInput.value == null ? "" : String(valInput.value);
        if (v.trim() === "") delete this._columnFilters[key];
        else this._columnFilters[key] = v.trim();
        this._page = 0;
        this._persistPage();
        this._applyFilter();
      });

      // Sort actions
      itemAsc.addEventListener("click", () => {
        const key = this._activeColumnMenuKey;
        if (!key) return;
        this._sortKey = key;
        this._sortDir = "asc";
        this._page = 0;
        this._persistPage();
        this._applyFilter();
        this._closeColumnMenu();
      });

      itemDesc.addEventListener("click", () => {
        const key = this._activeColumnMenuKey;
        if (!key) return;
        this._sortKey = key;
        this._sortDir = "desc";
        this._page = 0;
        this._persistPage();
        this._applyFilter();
        this._closeColumnMenu();
      });

      itemClear.addEventListener("click", () => {
        // Clear sort + clear filter kolom yang aktif
        const key = this._activeColumnMenuKey;
        this._sortKey = null;
        this._sortDir = "asc";
        if (key) delete this._columnFilters[key];
        this._page = 0;
        this._persistPage();
        this._applyFilter();
        this._closeColumnMenu();
      });
    }

    // Position panel relative to container
    if (!this.container) return;
    const cs = window.getComputedStyle(this.container);
    if (cs.position === "static" || !cs.position) {
      this.container.style.position = "relative";
    }

    if (!this._columnMenuEl.parentNode) {
      this.container.appendChild(this._columnMenuEl);
    }

    // Update input from current filter
    const current = this._columnFilters?.[colKey];
    if (this._columnMenuValueInputEl) {
      this._columnMenuValueInputEl.value = current ? String(current) : "";
    }

    // Update datalist suggestions for this column.
    try {
      const datalist = this._columnMenuEl?.querySelector(
        `datalist#${this._columnMenuValueDatalistId}`
      );
      if (datalist) {
        datalist.innerHTML = "";
        // Pakai data mentah agar opsi tetap ada walaupun hasil filter sebelumnya sudah mengecil / 0 baris.
        const sourceRows = Array.isArray(this._data) ? this._data : [];
        const values = Array.isArray(sourceRows)
          ? sourceRows
              .map((r) => (r ? r[colKey] : null))
              .filter((v) => v != null && String(v).trim() !== "")
          : [];
        const uniq = Array.from(new Set(values.map((v) => String(v).trim())));
        uniq.sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
        uniq.slice(0, 100).forEach((v) => {
          const opt = document.createElement("option");
          opt.value = v;
          datalist.appendChild(opt);
        });
      }
    } catch {
      /* ignore */
    }


    const containerRect = this.container.getBoundingClientRect();
    const thRect = thEl.getBoundingClientRect();

    const top = thRect.bottom - containerRect.top + 6;
    const left = thRect.left - containerRect.left;

    this._columnMenuEl.style.top = `${top}px`;
    this._columnMenuEl.style.left = `${left}px`;
    this._columnMenuEl.style.display = "block";

    // Start listening outside click once
    if (!this._columnMenuDocAttached) {
      document.addEventListener("mousedown", this._boundDocMouseDown);
      this._columnMenuDocAttached = true;
    }
  }

  _renderBody() {
    if (!this._tbody) return;
    const cols = this.getColumns();
    this._tbody.innerHTML = "";
    const slice = this._pageSlice();

    if (!slice || slice.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = Math.max(1, cols.length);
      td.textContent = "Tidak ada data.";
      td.style.textAlign = "center";
      td.style.color = "var(--nx-gray-600, #6b7280)";
      td.style.fontWeight = "600";
      td.style.fontSize = "0.95rem";
      td.style.backgroundColor = "rgba(0,0,0,0.02)";
      td.style.padding = "14px 10px";
      tr.appendChild(td);
      this._tbody.appendChild(tr);
      this._updateSortIndicators();
      return;
    }

    slice.forEach((row, rowIdx) => {
      const tr = document.createElement("tr");
      cols.forEach((col) => {
        const td = document.createElement("td");
        if (col.key === "__rowNo__") {
          td.textContent = String(this._page * this.pageSize + rowIdx + 1);
          td.style.textAlign = "center";
          td.style.verticalAlign = "middle";
        } else if (col.key === "__actions__") {
          const el = this._buildRowActionsDropdown(row);
          if (el) td.appendChild(el);
          td.style.textAlign = "right";
          td.style.verticalAlign = "middle";
          td.style.whiteSpace = "nowrap";
        } else {
          const raw = row?.[col.key];
          const displayText = this.formatCell
            ? String(this.formatCell(raw, col.key, row))
            : cellText(raw);

          // Satu baris + ellipsis (gaya phpMyAdmin) — klik untuk lihat full
          td.textContent = displayText;
          td.style.verticalAlign = "middle";
          td.style.maxWidth = "220px";
          td.style.overflow = "hidden";
          td.style.textOverflow = "ellipsis";
          td.style.whiteSpace = "nowrap";
          if (displayText.length > 30) {
            td.title = displayText; // tooltip teks lengkap saat hover
          }

          // Tandai sel yang bisa diedit agar hanya aktif saat di-klik.
          const editCfg = this.editing && this.editing[col.key];
          const isInlineAllowed = !this.inlineColumns || this.inlineColumns.includes(col.key);
          if (editCfg && isInlineAllowed) {
            td.dataset.editable = "1";
            td.dataset.col = col.key;
            td.__nexaRow = row;
            td.classList.add("nx-table-editable-cell");
            td.style.cursor = "pointer";
          }
        }
        tr.appendChild(td);
      });
      this._tbody.appendChild(tr);
    });
    this._updateSortIndicators();
  }

  /**
   * Fetch a server-side page. Calls onFetchPage(page, pageSize) and replaces _data/_filtered.
   * @param {number} page
   */
  async _fetchServerPage(page) {
    if (this._fetchingPage) return;
    this._fetchingPage = true;
    try {
      // Pass current search term as 3rd arg so caller can send it to backend.
      const result = await this.onFetchPage(page, this.pageSize, this._serverSearch || "");
      const rows = Array.isArray(result?.rows) ? result.rows : [];
      // Only update _serverTotal if the backend returned a real value (>= 0).
      // total === -1 means backend skipped COUNT(*) — keep the existing total.
      if (Number.isFinite(result?.total) && result.total >= 0) this._serverTotal = result.total;
      this._data = rows;
      this._filtered = [...rows];
      if (this._sortKey) this._sortRows();
      this._renderBody();
      this._renderPagination();
    } catch (err) {
      console.error("NexaTables._fetchServerPage error:", err);
    } finally {
      this._fetchingPage = false;
    }
  }

  _renderPagination() {
    if (!this._paginationEl) return;
    const total = this.serverSide ? this._serverTotal : this._filtered.length;
    const pages = Math.max(1, Math.ceil(total / this.pageSize));
    this._paginationEl.innerHTML = "";
    this._paginationEl.className = "nx-table-pagination";

    // Layout custom: butuh dropdown di kiri dan pagination di kanan.
    this._paginationEl.style.display = "block";
    this._paginationEl.style.width = "100%";

    const wrapper = document.createElement("div");
    wrapper.style.display = "flex";
    wrapper.style.alignItems = "center";
    wrapper.style.justifyContent = "space-between";
    wrapper.style.gap = "12px";
    wrapper.style.width = "100%";

    const from = total === 0 ? 0 : this._page * this.pageSize + 1;
    // In server-side mode: _data may have more rows than pageSize (e.g. initial over-fetch).
    // Use Math.min(_data.length, pageSize) so "to" matches what's actually displayed.
    const displayedCount = this.serverSide
      ? Math.min(this._data.length, this.pageSize)
      : this._filtered.length;
    const to = Math.min(
      this._page * this.pageSize + displayedCount,
      total
    );

    const info = document.createElement("div");
    info.className = this.pageInfoClass;
    info.textContent = `${from}–${to} dari ${total} baris`;

    const lengthWrap = document.createElement("div");
    lengthWrap.style.display = "flex";
    lengthWrap.style.alignItems = "center";
    lengthWrap.style.gap = "8px";

    const select = document.createElement("select");
    select.className = "form-nexa-control form-nexa-control-sm";
    select.style.maxWidth = "120px";
    select.style.width = "auto";
    select.setAttribute("aria-label", "entries per page");

    const optionValues = Array.from(
      new Set([...this.pageSizeOptions, this.pageSize])
    ).sort((a, b) => a - b);

    optionValues.forEach((v) => {
      const opt = document.createElement("option");
      opt.value = String(v);
      opt.textContent = String(v);
      if (v === this.pageSize) opt.selected = true;
      select.appendChild(opt);
    });

    select.addEventListener("change", () => {
      const next = parseInt(select.value, 10);
      if (Number.isNaN(next) || next <= 0) return;
      this.pageSize = next;
      this._page = 0; // reset halaman
      this._persistPageSize();
      this._persistPage();
      if (this.serverSide && typeof this.onFetchPage === "function") {
        this._fetchServerPage(0);
      } else {
        this._applyFilter();
      }
    });

    lengthWrap.appendChild(select);

    const rightWrap = document.createElement("div");
    rightWrap.style.display = "flex";
    rightWrap.style.alignItems = "center";
    rightWrap.style.gap = "16px";
    rightWrap.style.flexWrap = "wrap";
    rightWrap.appendChild(info);

    const ul = document.createElement("ul");
    ul.className = this.paginationClass;
    ul.setAttribute("role", "navigation");
    ul.setAttribute("aria-label", "Paginasi tabel");

    /**
     * @param {string} label
     * @param {number} targetPage
     * @param {{ disabled?: boolean, active?: boolean, nav?: string }} [opts]
     */
    const appendPageControl = (label, targetPage, opts = {}) => {
      const { disabled = false, active = false, nav } = opts;
      const li = document.createElement("li");
      li.className = "page-item";
      if (disabled) li.classList.add("disabled");
      if (active) li.classList.add("active");
      if (nav) li.setAttribute("data-nav", nav);

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "page-link";
      if (active) btn.classList.add("active");
      btn.textContent = label;
      if (nav) btn.setAttribute("data-nav", nav);
      btn.disabled = !!disabled;

      btn.addEventListener("click", () => {
        if (disabled || active) return;
        if (targetPage === this._page) return;
        this._page = targetPage;
        this._persistPage();
        if (this.serverSide && typeof this.onFetchPage === "function") {
          this._fetchServerPage(targetPage);
        } else {
          this._renderBody();
          this._renderPagination();
        }
      });

      li.appendChild(btn);
      ul.appendChild(li);
    };

    const appendEllipsis = () => {
      const li = document.createElement("li");
      li.className = "page-item disabled";
      const span = document.createElement("span");
      span.className = "page-link";
      span.textContent = "…";
      span.style.cursor = "default";
      span.setAttribute("aria-hidden", "true");
      li.appendChild(span);
      ul.appendChild(li);
    };

    appendPageControl("«", Math.max(0, this._page - 1), {
      disabled: this._page <= 0,
      nav: "prev",
    });

    /** Maksimal tombol nomor halaman berurutan di jendela tengah (bukan termasuk 1 / … / terakhir). */
    const maxShown = 5;
    let left = Math.max(0, this._page - Math.floor(maxShown / 2));
    let right = left + maxShown - 1;
    if (right >= pages) {
      right = pages - 1;
      left = Math.max(0, right - maxShown + 1);
    }

    if (left > 0) {
      appendPageControl("1", 0, { active: this._page === 0 });
      if (left > 1) appendEllipsis();
    }

    for (let p = left; p <= right; p++) {
      appendPageControl(String(p + 1), p, { active: p === this._page });
    }

    if (right < pages - 1) {
      if (right < pages - 2) appendEllipsis();
      appendPageControl(String(pages), pages - 1, {
        active: this._page === pages - 1,
      });
    }

    appendPageControl("»", Math.min(pages - 1, this._page + 1), {
      disabled: this._page >= pages - 1,
      nav: "next",
    });

    rightWrap.appendChild(ul);
    wrapper.appendChild(lengthWrap);
    wrapper.appendChild(rightWrap);
    this._paginationEl.appendChild(wrapper);
    this._applyPaginationActiveTheme();
  }

  /**
   * Terapkan warna tombol halaman aktif (mengalahkan `pagination.css` termasuk state hover).
   */
  _applyPaginationActiveTheme() {
    const hasCustom =
      this.paginationActiveBg ||
      this.paginationActiveBorder ||
      this.paginationActiveColor;
    if (!this._paginationDomId) return;

    if (!hasCustom) {
      if (this._paginationStyleEl?.parentNode) {
        this._paginationStyleEl.remove();
        this._paginationStyleEl = null;
      }
      return;
    }

    const bg = this.paginationActiveBg || "#DC2626";
    const bd = this.paginationActiveBorder || bg;
    const fg = this.paginationActiveColor || "#ffffff";
    const id = this._paginationDomId;

    const css = `#${id} .pagination .page-link.active,
#${id} .pagination .page-item.active .page-link:hover {
  background-color: ${bg} !important;
  border-color: ${bd} !important;
  color: ${fg} !important;
}`;

    if (!this._paginationStyleEl) {
      this._paginationStyleEl = document.createElement("style");
      this._paginationStyleEl.setAttribute("data-nexa-tables-pagination-theme", this._paginationDomId);
      document.head.appendChild(this._paginationStyleEl);
    }
    this._paginationStyleEl.textContent = css;
  }

  /**
   * Render ke DOM (panggil sekali). Gunakan `setData` untuk memperbarui isi.
   */
  async mount() {
    this.container.innerHTML = "";
    this._root = document.createElement("div");
    if (this.wrapperClass) {
      this._root.className = this.wrapperClass;
    }

    const exportOpts = this.exportOptions;
    const exportEnabled = !!exportOpts?.enabled;

    const hasTopBar = this.searchable || exportEnabled;
    const topBar = document.createElement("div");
    topBar.style.display = "flex";
    topBar.style.alignItems = "center";
    topBar.style.gap = "12px";
    topBar.style.justifyContent =
      this.searchable && exportEnabled ? "space-between" : "flex-end";
    topBar.style.marginBottom = this.searchable ? "10px" : "6px";

    // Search input (kiri)
    if (this.searchable) {
      const filterWrap = document.createElement("div");
      filterWrap.className = "nx-table-filter";
      const input = document.createElement("input");
      input.type = "search";
      input.className = "form-control input-sm input-block";
      input.placeholder = "Cari…";
      input.setAttribute("autocomplete", "off");
      input.setAttribute("aria-label", "Cari di tabel");
      input.addEventListener("input", this._boundFilter);
      filterWrap.appendChild(input);
      const searchIcon = document.createElement("i");
      searchIcon.className = "material-symbols-outlined";
      searchIcon.setAttribute("aria-hidden", "true");
      searchIcon.textContent = "search";
      filterWrap.appendChild(searchIcon);
      topBar.appendChild(filterWrap);
      this._searchInput = input;
    }

    // Optional export controls (kanan)
    if (exportEnabled) {
      const types =
        Array.isArray(exportOpts.types) && exportOpts.types.length
          ? exportOpts.types
          : ["csv"];
      const include = exportOpts.include || "filtered";
      const fileName =
        exportOpts.fileName || (this.caption ? this.caption : "nexa-tables");

      const exportWrap = document.createElement("div");
      exportWrap.className = "nx-table-export";
      exportWrap.style.display = "flex";
      exportWrap.style.gap = "8px";
      exportWrap.style.alignItems = "center";
      exportWrap.style.justifyContent = "flex-end";
      exportWrap.style.transform = "none";
      exportWrap.style.alignSelf = "center";
      exportWrap.style.marginTop = "0";
      exportWrap.style.gap = "6px";

      const download = (type) => {
        const rows = this._getExportRows(include);
        if (!rows || rows.length === 0) return;

        const base = this._sanitizeFileName(fileName);
        const stamp = this._dateStamp();
        switch (type) {
          case "json": {
            this._downloadFile(
              `${base}_${stamp}.json`,
              "application/json;charset=utf-8",
              JSON.stringify(rows, null, 2)
            );
            return;
          }
          case "xlsx": {
            const xlsx = this._toXLSX(rows);
            this._downloadFile(
              `${base}_${stamp}.xlsx`,
              "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
              xlsx
            );
            return;
          }
          case "pdf": {
            this._exportToPDF(rows, base);
            return;
          }
          case "csv":
          default: {
            const csv = this._toCSV(rows);
            this._downloadFile(
              `${base}_${stamp}.csv`,
              "text/csv;charset=utf-8",
              csv
            );
            return;
          }
        }
      };

      const iconMap = {
        csv: { iconClass: "icon icon-csv" },
        json: { iconClass: "icon icon-json" },
        xlsx: { iconClass: "icon icon-xlsx" },
        pdf: { iconClass: "icon icon-pdf" },
      };
      const labelMap = {
        csv: "CSV",
        json: "JSON",
        xlsx: "XLSX",
        pdf: "PDF",
      };

      types.forEach((type) => {
        const btn = document.createElement("button");
        btn.type = "button";

        btn.className = "nx-btn nx-btn-primary1 is-small icon-button";
        btn.style.display = "inline-flex";
        btn.style.alignItems = "center";
        btn.style.justifyContent = "center";
        btn.style.padding = "5px 10px";
        btn.style.fontSize = "11px";
        btn.style.lineHeight = "1.1";
        btn.style.minHeight = "32px";
        btn.style.minWidth = "0";
        btn.style.gap = "2px";
        btn.style.columnGap = "2px";
        btn.style.whiteSpace = "nowrap";
        btn.style.backgroundColor = "transparent";
        btn.style.border = "none";
        btn.style.borderRadius = "0";
        btn.style.boxShadow = "none";

        const icon = document.createElement("span");
        icon.className = iconMap[type]?.iconClass || "icon icon-default";
        icon.style.width = "14px";
        icon.style.height = "14px";
        icon.style.margin = "0";
        icon.style.flex = "0 0 auto";
        btn.appendChild(icon);

        const span = document.createElement("span");
        span.textContent = labelMap[type] || "Download";
        // Tampilkan teks agar jelas tipe yang akan diunduh
        span.style.display = "inline-block";
        span.style.fontSize = "11px";
        span.style.fontWeight = "600";
        span.style.margin = "0";
        span.style.padding = "0";
        span.style.flex = "0 0 auto";
        btn.appendChild(span);

        btn.setAttribute("aria-label", `Download ${labelMap[type] || type}`);
        btn.addEventListener("click", () => download(type));
        this._exportButtons.push({ btn, type });
        exportWrap.appendChild(btn);
      });

      topBar.appendChild(exportWrap);
    }

    if (hasTopBar) {
      this._root.appendChild(topBar);
    }

    // If user uses `storage: { model, query }` like NexaDom,
    // load data first so head/columns can be inferred.
    let hideSpinner = null;
    try {
      if (this.spinner.enabled && this.storageModel) {
        hideSpinner = this._showSpinner();
      }
      await this._loadStorageModelDataIfNeeded();
    } catch (e) {
      console.warn("NexaTables: failed to load storage model data:", e);
      this._data = [];
      this._filtered = [];
    } finally {
      if (typeof hideSpinner === "function") {
        hideSpinner();
      }
    }

    // Horizontal scroll wrapper — prevents column squeezing when there are many columns
    this._tableWrapper = document.createElement("div");
    this._tableWrapper.className = "nx-scroll-x nx-scroll-thin";
    NexaTables._injectScrollStyles();

    this._table = document.createElement("table");
    this._table.className = this.sortable
      ? `${this.tableClass} nx-table-sortable`
      : this.tableClass;
    this._table.style.tableLayout = "fixed";
    this._table.style.width = "max-content";
    this._table.style.minWidth = "100%";

    this._colgroup = this._buildColgroup();
    this._table.appendChild(this._colgroup);
    this._table.appendChild(this._renderHead());
    this._tbody = document.createElement("tbody");
    this._table.appendChild(this._tbody);
    this._tableWrapper.appendChild(this._table);
    this._root.appendChild(this._tableWrapper);

    this._initColResize();

    if (this.sortable) {
      this._table.addEventListener("click", this._sortHandler);
    }
    if (this._tbody) {
      this._tbody.addEventListener("click", this._cellClickHandler);
    }

    this._paginationEl = document.createElement("div");
    this._paginationEl.className = "nx-table-pagination";
    this._paginationDomId = `nx-tables-pag-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
    this._paginationEl.id = this._paginationDomId;
    this._root.appendChild(this._paginationEl);

    this.container.appendChild(this._root);

    if (this.serverSide) {
      // Server-side mode: render directly without client-side filter/page-reset.
      this._renderBody();
      this._renderPagination();
    } else {
      this._applyFilter();
    }
    return this;
  }

  /**
   * Spinner sederhana untuk proses async (saat ini hanya storage model di `mount()`).
   * Mengembalikan fungsi untuk menghilangkan spinner.
   */
  _showSpinner() {
    if (!this.container || typeof document === "undefined") return () => {};

    const overlay = document.createElement("div");
    const color = this.spinner.color || "#007bff";
    const msg = this.spinner.message || "";

    if (this.spinner.centerScreen) {
      overlay.style.position = "fixed";
      overlay.style.inset = "0";
      overlay.style.display = "flex";
      overlay.style.alignItems = "center";
      overlay.style.justifyContent = "center";
      overlay.style.background = "rgba(0,0,0,0.25)";
      overlay.style.zIndex = "9999";
    } else {
      overlay.style.position = "absolute";
      overlay.style.inset = "0";
      overlay.style.display = "flex";
      overlay.style.alignItems =
        this.spinner.position === "top"
          ? "flex-start"
          : this.spinner.position === "bottom"
          ? "flex-end"
          : "center";
      overlay.style.justifyContent = "center";
      overlay.style.background = "rgba(255,255,255,0.6)";
      overlay.style.zIndex = "10";
    }

    const box = document.createElement("div");
    box.style.display = "flex";
    box.style.flexDirection = "column";
    box.style.alignItems = "center";
    box.style.gap = "8px";

    const circle = document.createElement("div");
    const sizePx =
      this.spinner.size === "small"
        ? 24
        : this.spinner.size === "large"
        ? 48
        : 32;
    circle.style.width = `${sizePx}px`;
    circle.style.height = `${sizePx}px`;
    circle.style.borderRadius = "50%";
    circle.style.border = `3px solid ${color}`;
    circle.style.borderTopColor = "transparent";
    circle.style.animation = "nexa-tables-spin 0.8s linear infinite";

    // Inject keyframes sekali saja
    if (!document.getElementById("nexa-tables-spinner-style")) {
      const style = document.createElement("style");
      style.id = "nexa-tables-spinner-style";
      style.textContent =
        "@keyframes nexa-tables-spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}";
      document.head.appendChild(style);
    }

    box.appendChild(circle);

    if (msg) {
      const text = document.createElement("div");
      text.textContent = msg;
      text.style.color = this.spinner.centerScreen ? "#fff" : "#333";
      text.style.fontSize = "0.9rem";
      box.appendChild(text);
    }

    overlay.appendChild(box);

    // Saat bukan centerScreen, pastikan container relatif
    if (!this.spinner.centerScreen) {
      const cs = window.getComputedStyle(this.container);
      if (cs.position === "static" || !cs.position) {
        this.container.style.position = "relative";
      }
    }

    this.container.appendChild(overlay);
    this._spinnerEl = overlay;

    return () => {
      if (this._spinnerEl && this._spinnerEl.parentNode) {
        this._spinnerEl.parentNode.removeChild(this._spinnerEl);
      }
      this._spinnerEl = null;
    };
  }

  _getExportRows(include) {
    if (include === "page") return this._pageSlice();
    if (include === "all") return Array.isArray(this._data) ? this._data : [];
    // default: filtered
    return Array.isArray(this._filtered) ? this._filtered : [];
  }

  _sanitizeFileName(name) {
    return sanitizeFileName(name);
  }

  _dateStamp() {
    return dateStamp();
  }

  _downloadFile(fileName, mime, content) {
    downloadFile(fileName, mime, content);
  }

  _exportColumns() {
    return this.getColumns().filter(
      (c) =>
        c?.key &&
        c.key !== "__actions__" &&
        c.key !== "__rowNo__"
    );
  }

  _toCSV(rows) {
    const cols = this._exportColumns();
    return toCSV(rows, cols, this.formatCell);
  }

  _escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  _rowValueForExport(row, colKey) {
    const raw = row?.[colKey];
    if (this.formatCell) {
      try {
        return this.formatCell(raw, colKey, row);
      } catch {
        // fall back to raw
      }
    }
    return raw;
  }

  /**
   * Export XLSX: menggunakan format HTML + MIME XLSX (mirip recordExport.js)
   * agar bisa diunduh tanpa dependency library besar.
   */
  _toXLSX(rows) {
    const cols = this._exportColumns();
    return toXLSXHtml(rows, cols, this.formatCell);
  }

  _exportToPDF(rows, baseFileName) {
    const cols = this._exportColumns();
    exportToPDF(rows, cols, baseFileName, this.formatCell);
  }

  /**
   * Klik pada sel body untuk mengaktifkan inline editing hanya pada sel tersebut.
   * @param {MouseEvent} e
   */
  _onCellClick(e) {
    const td = e.target?.closest?.("td");
    if (!td || !this._tbody || !this._tbody.contains(td)) return;
    if (!td.dataset || td.dataset.editable !== "1") return;
    // Jika sudah ada editor (input / select / textarea), jangan re-init lagi.
    if (td.querySelector("input, select, textarea")) return;

    const colKey = td.dataset.col;
    if (!colKey || !this.editing || !this.editing[colKey]) return;
    if (this.inlineColumns && !this.inlineColumns.includes(colKey)) return;
    const cfg = this.editing[colKey];
    const row = td.__nexaRow;
    if (!row) return;

    this._enterInlineEdit(td, colKey, row, cfg);
  }

  /**
   * Masuk ke mode edit pada satu sel.
   */
  _enterInlineEdit(td, colKey, row, cfg) {
    const type = cfg?.type || "text";
    const rawValue = row?.[colKey];
    const normalize = (v) =>
      v == null
        ? ""
        : typeof v === "object"
        ? JSON.stringify(v)
        : String(v);
    const originalNorm = normalize(rawValue);

    // Bersihkan isi sel dan bangun editor.
    td.innerHTML = "";
    td.classList.add("nx-table-editing");
    td.style.cursor = "default";
    // Flag supaya commit hanya sekali per sesi edit.
    td.__nexaCommitted = false;

    if (type === "checkbox") {
      const input = document.createElement("input");
      input.type = "checkbox";
      input.checked = Boolean(rawValue);

      const commitCheckbox = () => {
        if (td.__nexaCommitted) return;
        td.__nexaCommitted = true;
        const next = Boolean(input.checked);
        const nextNorm = normalize(next);
        if (nextNorm !== originalNorm) {
          row[colKey] = next;
          if (this.onEdit) {
            this.onEdit(colKey, next, row);
          }
          if (this.container) {
            this.container.dispatchEvent(
              new CustomEvent("nexa-table-edit", {
                detail: { key: colKey, value: next, row },
              })
            );
          }
        }
        // Kembali ke tampilan teks setelah edit.
        this._renderBody();
      };

      input.addEventListener("change", commitCheckbox);
      input.addEventListener("blur", commitCheckbox);

      td.appendChild(input);
      input.focus();
      return;
    }

    // SELECT — atau SEARCH (Select2 + opsi dari kolom / options)
    if (type === "select" || type === "search") {
      const select = document.createElement("select");
      select.className = "form-nexa-control form-nexa-control-sm";
      let opts;
      if (type === "select") {
        opts = Array.isArray(cfg.options) ? cfg.options : [];
      } else {
        // type === "search": seperti select dengan data kolom; opsi eksplisit mengalahkan.
        opts =
          Array.isArray(cfg.options) && cfg.options.length
            ? cfg.options
            : this._getDistinctColumnValues(colKey);
      }
      const currentStr =
        rawValue == null
          ? ""
          : typeof rawValue === "object"
          ? JSON.stringify(rawValue)
          : String(rawValue);
      if (type === "search" && currentStr) {
        const exists = opts.some((opt) => {
          if (typeof opt === "string") return opt === currentStr;
          if (opt && typeof opt === "object")
            return String(opt.value ?? "") === currentStr;
          return false;
        });
        if (!exists) opts = [currentStr, ...opts];
      }
      opts.forEach((opt) => {
        const o = document.createElement("option");
        if (typeof opt === "string") {
          o.value = opt;
          o.textContent = opt;
        } else if (opt && typeof opt === "object") {
          o.value = String(opt.value ?? "");
          o.textContent = opt.label ?? String(opt.value ?? "");
        }
        select.appendChild(o);
      });
      const current =
        rawValue == null
          ? ""
          : typeof rawValue === "object"
          ? JSON.stringify(rawValue)
          : String(rawValue);
      select.value = current;

      const commitSelect = () => {
        if (td.__nexaCommitted) return;
        td.__nexaCommitted = true;
        const next = select.value;
        const nextNorm = normalize(next);
        if (nextNorm !== originalNorm) {
          row[colKey] = next;
          if (this.onEdit) {
            this.onEdit(colKey, next, row);
          }
          if (this.container) {
            this.container.dispatchEvent(
              new CustomEvent("nexa-table-edit", {
                detail: { key: colKey, value: next, row },
              })
            );
          }
        }
        this._renderBody();
      };

      // Untuk select, commit hanya saat value berubah (event change),
      // jangan di blur supaya user sempat memilih opsi dari dropdown.
      select.addEventListener("change", commitSelect);

      td.appendChild(select);
      select.focus();
      // Upgrade ke Select2 jika tersedia lewat NXUI.initSelect2
      try {
        const nx = window.NXUI;
        if (nx && typeof nx.initSelect2 === "function") {
          // Gunakan selector langsung ke element, Select2 mendukung ini.
          nx.initSelect2(select, {
            width: "100%",
          });
          // Jika helper onSelect2Change tersedia, pastikan commitSelect terpanggil
          // saat user memilih opsi di dropdown Select2.
          if (typeof nx.onSelect2Change === "function") {
            nx.onSelect2Change(select, () => {
              commitSelect();
            });
          }
        }
      } catch {
        // abaikan error Select2
      }
      return;
    }

    // TEXTAREA
    if (type === "textarea") {
      const ta = document.createElement("textarea");
      ta.className = "form-nexa-control form-nexa-control-sm";
      ta.rows = cfg.rows != null ? cfg.rows : 3;
      ta.value =
        rawValue == null
          ? ""
          : typeof rawValue === "object"
          ? JSON.stringify(rawValue)
          : String(rawValue);

      const commitTextarea = () => {
        if (td.__nexaCommitted) return;
        td.__nexaCommitted = true;
        const next = ta.value;
        const nextNorm = normalize(next);
        if (nextNorm !== originalNorm) {
          row[colKey] = next;
          if (this.onEdit) {
            this.onEdit(colKey, next, row);
          }
          if (this.container) {
            this.container.dispatchEvent(
              new CustomEvent("nexa-table-edit", {
                detail: { key: colKey, value: next, row },
              })
            );
          }
        }
        this._renderBody();
      };

      ta.addEventListener("blur", commitTextarea);
      ta.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter" && (ev.ctrlKey || ev.metaKey)) {
          ev.preventDefault();
          commitTextarea();
        }
        if (ev.key === "Escape") {
          ev.preventDefault();
          this._renderBody();
        }
      });

      td.appendChild(ta);
      ta.focus();
      ta.select?.();
      return;
    }

    const input = document.createElement("input");
    const allowedTypes = [
      "text",
      "number",
      "email",
      "password",
      "tel",
      "url",
      "date",
      "datetime-local",
      "time",
      "color",
      "range",
    ];
    const htmlType = allowedTypes.includes(type) ? type : "text";
    input.type = htmlType;
    input.className = "form-nexa-control form-nexa-control-sm";
    if (type === "number") {
      if (cfg.min != null) input.min = String(cfg.min);
      if (cfg.max != null) input.max = String(cfg.max);
      if (cfg.step != null) input.step = String(cfg.step);
    }
    input.value =
      rawValue == null
        ? ""
        : typeof rawValue === "object"
        ? JSON.stringify(rawValue)
        : String(rawValue);

    const commitText = () => {
      if (td.__nexaCommitted) return;
      td.__nexaCommitted = true;
      let next;
      if (type === "number") {
        const n = Number(input.value);
        next = Number.isNaN(n) ? null : n;
      } else {
        next = input.value;
      }
      const nextNorm = normalize(next);
      if (nextNorm !== originalNorm) {
        row[colKey] = next;
        if (this.onEdit) {
          this.onEdit(colKey, next, row);
        }
        if (this.container) {
          this.container.dispatchEvent(
            new CustomEvent("nexa-table-edit", {
              detail: { key: colKey, value: next, row },
            })
          );
        }
      }
      // Setelah commit, render ulang body sehingga sel kembali menjadi teks.
      this._renderBody();
    };

    input.addEventListener("blur", commitText);
    input.addEventListener("keydown", (ev) => {
      if (ev.key === "Enter") {
        ev.preventDefault();
        commitText();
      }
      if (ev.key === "Escape") {
        ev.preventDefault();
        // Batalkan dan kembali ke tampilan awal tanpa menyimpan.
        this._renderBody();
      }
    });

    td.appendChild(input);
    input.focus();
    input.select?.();
  }

  destroy() {
    if (this._table && this.sortable) {
      this._table.removeEventListener("click", this._sortHandler);
    }
    if (this._tbody && this._cellClickHandler) {
      this._tbody.removeEventListener("click", this._cellClickHandler);
    }
    if (this._searchInput) {
      this._searchInput.removeEventListener("input", this._boundFilter);
    }

    if (this._boundDocMouseDown) {
      document.removeEventListener("mousedown", this._boundDocMouseDown);
    }
    this._boundDocMouseDown = null;
    if (this._columnMenuEl?.parentNode) {
      this._columnMenuEl.parentNode.removeChild(this._columnMenuEl);
    }
    this._columnMenuEl = null;
    this._activeColumnMenuKey = null;
    if (this._columnMenuDebounceTimer) {
      clearTimeout(this._columnMenuDebounceTimer);
    }
    this._columnMenuDebounceTimer = null;

    if (this._boundActionsDocMouseDown && this._actionsDocHandlerAttached) {
      document.removeEventListener("mousedown", this._boundActionsDocMouseDown);
    }
    this._boundActionsDocMouseDown = null;
    this._actionsDocHandlerAttached = false;
    if (this._actionDetails) {
      this._actionDetails.forEach((d) => this._detachRowActionsMenuPosition(d));
      this._actionDetails.clear();
    }

    if (this._paginationStyleEl?.parentNode) {
      this._paginationStyleEl.remove();
    }
    this._paginationStyleEl = null;
    this._paginationDomId = null;
    this._exportButtons = [];
    if (this.container) {
      this.container.innerHTML = "";
    }
    this._root = null;
    this._tableWrapper = null;
    this._table = null;
    this._tbody = null;
    this._colgroup = null;
    this._searchInput = null;
    this._paginationEl = null;
  }
}
