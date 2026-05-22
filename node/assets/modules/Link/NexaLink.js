import { spinner as nexaSpinner } from "../spinner/NexaSpinner.js";



/**
 * @typedef {object} NexaLinkNavigateDetail
 * @property {string} href
 * @property {HTMLAnchorElement} element
 * @property {string} text
 * @property {MouseEvent} event
 */

/**
 * Intercept tautan di dalam root: pushState + event `link:navigate`.
 * Menggunakan event delegation pada `root` agar elemen baru ikut ter-cover.
 */
export class NexaLink {
  /**
   * @param {string} selector - Contoh: "a" atau "summary a"
   * @param {ParentNode|string} [root=document] - Elemen induk atau selector CSS
   */
  constructor(selector, root = document) {
    this.selector = selector;
    this.root = root;
    /** @type {((e: MouseEvent) => void) | null} */
    this._delegateHandler = null;
    /** @type {ParentNode | null} */
    this._delegateBase = null;
  }

  /** @returns {ParentNode|null} */
  getBase() {
    const r = this.root;
    return typeof r === "string" ? document.querySelector(r) : r;
  }

  /**
   * Satu listener di root; `onNavigate` dapat info elemen yang benar-benar diklik.
   * @param {{ onNavigate?: (detail: NexaLinkNavigateDetail) => void }} [options]
   * @returns {this}
   */
  attach(options = {}) {
    const base = this.getBase();
    const sel = this.selector;
    const { onNavigate } = options;
    if (!base || typeof sel !== "string" || !sel.trim()) {
      return this;
    }

    if (this._delegateHandler && this._delegateBase) {
      this._delegateBase.removeEventListener("click", this._delegateHandler);
    }

    const handler = (e) => {
      if (!(e.target instanceof Element)) return;
      const link = e.target.closest(sel);
      if (
        !link ||
        !(link instanceof HTMLAnchorElement) ||
        !base.contains(link)
      ) {
        return;
      }

      // Skip link yang ada dalam container #nx-skip-links
      if (document.getElementById("nx-skip-links")?.contains(link)) {
        return;
      }

      if (e.defaultPrevented) return;
      if (e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (link.target === "_blank" || link.hasAttribute("download")) return;

      const href = (link.getAttribute("href") || "").trim();
      const detail = {
        href,
        element: link,
        text: (link.textContent || "").trim(),
        event: e,
      };
      onNavigate?.(detail);

      if (!href) return;
      if (/^(javascript:|mailto:|tel:|data:)/i.test(href)) return;

      // Hanya `#bagian` di dokumen ini — biarkan scroll/hash native
      if (href.startsWith("#")) return;

      let pushUrl;
      try {
        const u = new URL(href, window.location.href);
        if (u.origin !== window.location.origin) return;
        pushUrl = `${u.pathname}${u.search}${u.hash}`;
      } catch {
        return;
      }

      e.preventDefault();
      history.pushState({}, "", pushUrl);
      window.dispatchEvent(new Event("link:navigate"));
    };

    base.addEventListener("click", handler);
    this._delegateHandler = handler;
    this._delegateBase = base;
    return this;
  }
}

/**
 * @param {string} selector
 * @param {ParentNode|string} [root=document]
 * @param {{ onNavigate?: (detail: NexaLinkNavigateDetail) => void }} [options]
 * @returns {NexaLink}
 */
export function LinkDefault(selector, root = document, options = {}) {
  return new NexaLink(selector, root).attach(options);
}

/**
 * Fungsi onSkip: Handle link yang bypass AJAX dan full page reload
 * Link di dalam container ini akan melakukan navigasi normal (full page reload)
 * bukan AJAX loading.
 *
 * @param {string} selector - CSS selector untuk root container
 * @param {string} [linkSelector="a"] - CSS selector untuk link elements
 * @returns {void}
 *
 * @example
 * onSkip("#nx-skip-links", "a");  // Semua link di #nx-skip-links akan full reload
 */
export function onSkip(selector, linkSelector = "a") {
  // Support array of selectors — iterate and register each individually
  if (Array.isArray(selector)) {
    selector.forEach((s) => onSkip(s, linkSelector));
    return;
  }

  const container = document.querySelector(selector);
  if (!container) {
    console.warn(
      `onSkip: Container dengan selector "${selector}" tidak ditemukan`,
    );
    return;
  }

  container.addEventListener(
    "click",
    (e) => {
      if (!(e.target instanceof Element)) return;
      const link = e.target.closest(linkSelector);
      if (!link || !(link instanceof HTMLAnchorElement)) return;

      const href = link.getAttribute("href");
      if (!href) return;

      // Check link attributes yang tidak perlu di-navigate
      if (link.target === "_blank" || link.hasAttribute("download")) return;
      if (/^(javascript:|mailto:|tel:|data:)/i.test(href)) return;
      if (href.startsWith("#")) return;

      // Hentikan propagasi agar SPA handler (bubble) tidak ikut mengintersep
      e.stopImmediatePropagation();
      // Full page navigate (reload)
      window.location.href = href;
    },
    true,
  ); // Gunakan capture untuk prioritas tertinggi
}

/**
 * @typedef {object} NexaLinkUISpinnerOptions
 * @property {boolean} [enabled=true]
 * @property {boolean} [centerScreen=true] — true: overlay layar penuh (`body`); false: `inline` di `target`
 * @property {string} [target] — selector container untuk inline jika `centerScreen: false` (default `#dokumentasi`)
 * @property {'overlay'|'inline'|'button'} [type]
 * @property {'small'|'medium'|'large'} [size]
 * @property {string} [color]
 * @property {'center'|'top'|'bottom'} [position]
 * @property {string} [message]
 *
 * @typedef {object} NexaLinkUIOptions
 * @property {string[]} [loadContentSelectors] — dicoba berurutan saat `load()` (parse HTML & dokumen aktif)
 * @property {string} [loadContentSelector] — satu selector; setara `[loadContentSelector]`
 * @property {NexaLinkUISpinnerOptions|false|null} [spinner] — opsi untuk `Dom/NexaSpinner.js` saat `load()`; `false` / `enabled: false` mematikan
 * @property {boolean} [enablePopstate=true] — jika true, tombol Back/Forward browser akan memanggil `load()`
 */

/**
 * API gaya `new NXUI.Link({ … }).onDefault(...).onClick(...)` — `info()` = data klik terakhir.
 */
export class NexaLinkUI {
  /**
   * @param {NexaLinkUIOptions} [options]
   */
  constructor(options = {}) {
    /** @type {NexaLinkNavigateDetail | null} */
    this._last = null;
    /** @type {((detail: NexaLinkNavigateDetail) => void) | null} */
    this._onClick = null;

    const fallback = ["#dokumentasi .nx-doc-content", ".nx-doc-content"];
    if (
      typeof options.loadContentSelector === "string" &&
      options.loadContentSelector.trim()
    ) {
      this._loadContentSelectors = [options.loadContentSelector.trim()];
    } else if (
      Array.isArray(options.loadContentSelectors) &&
      options.loadContentSelectors.length
    ) {
      this._loadContentSelectors = options.loadContentSelectors.filter(
        (s) => typeof s === "string" && s.trim(),
      );
      if (!this._loadContentSelectors.length) {
        this._loadContentSelectors = fallback.slice();
      }
    } else {
      this._loadContentSelectors = fallback.slice();
    }

    /** @type {Record<string, *>|null} — argumen siap pakai untuk `nexaSpinner()` atau null */
    this._spinnerFactoryOpts = this._normalizeSpinnerFactoryOpts(
      options.spinner,
    );

    /** @type {boolean} */
    this._enablePopstate = options.enablePopstate !== false;
    /** @type {((e: PopStateEvent) => void) | null} */
    this._popstateHandler = null;
    if (this._enablePopstate) {
      this._bindPopstate();
    }
  }

  _bindPopstate() {
    if (this._popstateHandler) return;
    this._popstateHandler = () => {
      this.load(window.location.href, {
        forceFetch: true,
        skipSpinner: true,
      }).catch((err) => {
        console.error("NexaLinkUI popstate load error:", err);
      });
    };
    window.addEventListener("popstate", this._popstateHandler);
  }

  /**
   * @param {NexaLinkUISpinnerOptions|false|null|undefined} raw
   * @returns {Record<string, *>|null}
   */
  _normalizeSpinnerFactoryOpts(raw) {
    if (raw == null || raw === false) return null;
    if (typeof raw === "object" && raw.enabled === false) return null;

    const s = typeof raw === "object" && raw !== null ? raw : {};
    const center = s.centerScreen !== false;
    let target = "body";
    let type = s.type;
    if (center) {
      target = "body";
      if (!type) type = "overlay";
    } else {
      target =
        typeof s.target === "string" && s.target.trim()
          ? s.target.trim()
          : "#dokumentasi";
      if (!type) type = "inline";
    }
    return {
      target,
      type: type || "overlay",
      size: s.size || "medium",
      color: s.color || "#007bff",
      message: typeof s.message === "string" ? s.message : "",
      position: s.position || "center",
    };
  }

  /**
   * @param {ParentNode} root
   * @returns {Element | null}
   */
  _firstContentMatch(root) {
    for (const sel of this._loadContentSelectors) {
      try {
        const el = root.querySelector(sel);
        if (el) return el;
      } catch {
        /* selector tidak valid */
      }
    }
    return null;
  }

  /**
   * Menghindari reload: listener delegation di `root`, semua `<a>` yang cocok `selector` di dalamnya di-intersep.
   *
   * Bentuk pemanggilan:
   * - `Default("#nx-js-doc-menu")` → selector `"a"`, root menu
   * - `Default("#nx-js-doc-menu", "summary a")` → root lalu selector
   * - `Default("a", "#nx-js-doc-menu")` → sama seperti di atas (urutan seperti `LinkDefault`)
   *
   * @param {string} first
   * @param {string|ParentNode|{ selector?: string, root?: ParentNode|string, onClick?: (detail: NexaLinkNavigateDetail) => void }} [second]
   */
  onDefault(first, second) {
    let root;
    let selector = "a";
    let optsOnClick;

    if (
      second != null &&
      typeof second === "object" &&
      !(second instanceof Element) &&
      !(second instanceof Document)
    ) {
      const o = second;
      optsOnClick = o.onClick;
      if (
        typeof first === "string" &&
        (first.startsWith("#") || first.startsWith("."))
      ) {
        root = first;
        selector = o.selector ?? "a";
      } else {
        selector = first ?? "a";
        root = o.root ?? document;
      }
    } else if (second === undefined || second === null) {
      if (
        typeof first === "string" &&
        (first.startsWith("#") || first.startsWith("."))
      ) {
        root = first;
        selector = "a";
      } else {
        selector = first && String(first).trim() ? first : "a";
        root = document;
      }
    } else if (typeof second === "string") {
      const aIsRoot =
        typeof first === "string" &&
        (first.startsWith("#") || first.startsWith("."));
      const bIsRoot = second.startsWith("#") || second.startsWith(".");
      if (bIsRoot) {
        selector = first;
        root = second;
      } else if (aIsRoot) {
        root = first;
        selector = second;
      } else {
        root = first;
        selector = second;
      }
    } else {
      selector = typeof first === "string" && first.trim() ? first : "a";
      root = second;
    }

    const cb = optsOnClick ?? this._onClick;

    const inst = new NexaLink(selector, root);
    inst.attach({
      onNavigate: (detail) => {
        this._last = detail;
        window.dispatchEvent(new CustomEvent("nexa:link", { detail }));
        cb?.(detail);
      },
    });
    this._instance = inst;
    return this;
  }

  /**
   * Callback tiap klik tautan. Untuk efek penuh, panggil sebelum `Default()` atau gunakan opsi `onClick` di argumen kedua `Default`.
   * @param {(detail: NexaLinkNavigateDetail) => void} fn
   */
  onClick(fn) {
    this._onClick = fn;
    return this;
  }

  /** Data klik terakhir (`null` sampai ada klik). */
  info() {
    return this._last;
  }

  /**
   * @param {URL} url
   */
  _scrollToHashFromUrl(url) {
    const id = url.hash ? decodeURIComponent(url.hash.slice(1)) : "";
    if (!id) return;
    requestAnimationFrame(() => {
      document.getElementById(id)?.scrollIntoView({ behavior: "smooth" });
    });
  }

  /** @param {URL} url */
  _hasMeaningfulHash(url) {
    if (!url.hash || url.hash === "#") return false;
    return Boolean(String(decodeURIComponent(url.hash.slice(1))).trim());
  }

  /**
   * Setelah konten dok baru (tanpa fragmen): window + induk scroll overflow dari area konten.
   * @param {Element | null} contentEl — biasanya node dari `loadContentSelectors`
   */
  _scrollToTopAfterNavigate(contentEl) {
    requestAnimationFrame(() => {
      window.scrollTo({ top: 0, left: 0, behavior: "auto" });
      let el = contentEl;
      const seen = new Set();
      while (el && el !== document.body && el !== document.documentElement) {
        if (seen.has(el)) break;
        seen.add(el);
        const st = getComputedStyle(el);
        const oy = st.overflowY;
        if (
          (oy === "auto" || oy === "scroll" || oy === "overlay") &&
          el.scrollHeight > el.clientHeight
        ) {
          el.scrollTop = 0;
        }
        el = el.parentElement;
      }
    });
  }

  /**
   * Same-origin: `fetch` HTML, ganti isi target dari `loadContentSelectors` / `loadContentSelector`, scroll ke hash.
   * Jika `pathname` + `search` sama dengan dokumen saat ini (hanya beda hash / fragmen), tidak fetch — cukup scroll ke elemen.
   * @param {string} href
   * @param {{ forceFetch?: boolean, skipSpinner?: boolean }} [options]
   * @returns {Promise<void>}
   */
  async load(href, options = {}) {
    if (!href || href.startsWith("#")) return;
    let url;
    try {
      url = new URL(href, window.location.href);
    } catch {
      return;
    }
    if (url.origin !== window.location.origin) return;

    const nextPathQuery = `${url.pathname}${url.search}`;
    const curPathQuery = `${window.location.pathname}${window.location.search}`;
    if (!options.forceFetch && nextPathQuery === curPathQuery) {
      if (this._hasMeaningfulHash(url)) {
        this._scrollToHashFromUrl(url);
      } else {
        this._scrollToTopAfterNavigate(this._firstContentMatch(document));
      }
      return;
    }

    let spin = null;
    if (!options.skipSpinner && this._spinnerFactoryOpts) {
      spin = nexaSpinner(this._spinnerFactoryOpts);
      spin.show();
    }

    try {
      const pathForFetch = `${url.pathname}${url.search}`;
      const res = await fetch(pathForFetch, {
        credentials: "same-origin",
        headers: { Accept: "text/html" },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const html = await res.text();
      const parsed = new DOMParser().parseFromString(html, "text/html");
      const nextDok = parsed.getElementById("dokumentasi");
      const curDok = document.getElementById("dokumentasi");
      /** @type {Element | null} */
      let contentEl = null;
      if (nextDok && curDok) {
        curDok.innerHTML = nextDok.innerHTML;
        contentEl = this._firstContentMatch(document);
      } else {
        const next = this._firstContentMatch(parsed);
        const cur = this._firstContentMatch(document);
        if (next && cur) {
          cur.innerHTML = next.innerHTML;
        }
        contentEl = cur;
      }
      const t = parsed.querySelector("title");
      if (t?.textContent) {
        document.title = t.textContent.trim();
      }
      if (this._hasMeaningfulHash(url)) {
        this._scrollToHashFromUrl(url);
      } else {
        this._scrollToTopAfterNavigate(contentEl);
      }
    } finally {
      spin?.destroy();
    }
  }
}

