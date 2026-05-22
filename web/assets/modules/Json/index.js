/**
 * Helper `<json-viewer>` — pakai API pendek global:
 *
 * @example
 * const el = NX.Json({ theme: "light", rows });
 * host.appendChild(el);
 *
 * @example
 * // Host dari HTML string dipasang belakangan — polling sampai ada di DOM:
 * NX.Json.schedule({ hostId: "my-json-host", rows: data, theme: "light" });
 *
 * Utilitas di properti fungsi: `NX.Json.JSON_VIEWER_HOST_CSS`, `NX.Json.schedule`, dll.
 */

/** True jika `customElements.define("json-viewer", …)` sudah jalan (biasanya lewat Nexa.js). */
export function jsonViewerRegistered() {
  return !!globalThis.customElements?.get("json-viewer");
}

/**
 * Normalisasi input untuk atribut `data` pada `<json-viewer>` (harus object yang bisa JSON.stringify).
 * String dicoba-parse sebagai JSON; gagal → dibungkus `{ response: string }`.
 */
export function normalizeViewerPayload(payload) {
  if (payload && typeof payload === "object") {
    return payload;
  }
  if (typeof payload === "string") {
    try {
      return JSON.parse(payload);
    } catch {
      return { response: payload };
    }
  }
  return { response: payload ?? null };
}

/**
 * Terapkan tema pada elemen `<json-viewer>` yang sudah ada di DOM.
 * @param {HTMLElement} jsonViewer
 * @param {"light"|"dark"} [themeName="light"]
 */
export function applyJsonViewerTheme(jsonViewer, themeName = "light") {
  if (!jsonViewer || jsonViewer.nodeType !== 1) return;

  jsonViewer.classList.remove("light", "dark");
  if (themeName === "light") {
    jsonViewer.classList.add("light");
    jsonViewer.style.setProperty("--background-color", "#f8fafc00");
    jsonViewer.style.setProperty("--color", "#0f172a");
    jsonViewer.style.setProperty("--property-color", "#0369a1");
    jsonViewer.style.setProperty("--string-color", "#166534");
    jsonViewer.style.setProperty("--number-color", "#b45309");
    jsonViewer.style.setProperty("--boolean-color", "#4338ca");
    jsonViewer.style.setProperty("--null-color", "#7c3aed");
    jsonViewer.style.setProperty("--preview-color", "rgba(148, 163, 184, 0.95)");
    jsonViewer.style.setProperty("--highlight-color", "#fde68a");
    jsonViewer.style.setProperty("--outline-color", "#94a3b8");
    jsonViewer.style.setProperty("--indent-size", "1rem");
    jsonViewer.style.setProperty("--line-height", "1.35rem");
    jsonViewer.style.setProperty("--indentguide-size", "2px");
    jsonViewer.style.setProperty("--indentguide-style", "solid");
    jsonViewer.style.setProperty("--indentguide-color", "#cbd5e1");
    jsonViewer.style.setProperty("--indentguide-color-active", "#334155");
    jsonViewer.style.setProperty("overflow", "visible");
    return;
  }

  jsonViewer.classList.add("dark");
  jsonViewer.style.removeProperty("--background-color");
  jsonViewer.style.removeProperty("--color");
  jsonViewer.style.removeProperty("--property-color");
  jsonViewer.style.removeProperty("--string-color");
  jsonViewer.style.removeProperty("--number-color");
  jsonViewer.style.removeProperty("--boolean-color");
  jsonViewer.style.removeProperty("--null-color");
  jsonViewer.style.removeProperty("--preview-color");
  jsonViewer.style.removeProperty("--highlight-color");
  jsonViewer.style.removeProperty("--outline-color");
  jsonViewer.style.removeProperty("--indent-size");
  jsonViewer.style.removeProperty("--line-height");
  jsonViewer.style.removeProperty("--indentguide-size");
  jsonViewer.style.removeProperty("--indentguide-style");
  jsonViewer.style.removeProperty("--indentguide-color");
  jsonViewer.style.removeProperty("--indentguide-color-active");
  jsonViewer.style.setProperty("overflow", "visible");
}

const DEFAULT_FALLBACK_CLASS = "nx-json-viewer-fallback";

/**
 * @param {{ expandPaths?: string[], expandUnder?: { prefix: string, indices: number[] }, expandAll?: boolean }} options
 * @returns {{ mode: 'all'|'paths'|'collapse', paths?: string[] }}
 */
export function resolveJsonViewerExpandMode(options = {}) {
  const pathsRaw = options.expandPaths;
  if (Array.isArray(pathsRaw) && pathsRaw.length) {
    return { mode: "paths", paths: pathsRaw.map(String) };
  }
  const under = options.expandUnder;
  if (
    under &&
    typeof under.prefix === "string" &&
    Array.isArray(under.indices) &&
    under.indices.length
  ) {
    const prefix = String(under.prefix).replace(/\.+$/, "");
    const paths = under.indices.map((i) => `${prefix}.${i}`);
    return { mode: "paths", paths };
  }
  if (options.expandAll === false) {
    return { mode: "collapse" };
  }
  return { mode: "all" };
}

/** @param {HTMLElement & { expandAll?: Function, collapseAll?: Function, expand?: Function, updateComplete?: Promise<void> }} jsonViewer */
export function applyJsonViewerExpand(jsonViewer, expandMode) {
  if (!jsonViewer || typeof jsonViewer.expandAll !== "function") return;

  const run = () => {
    if (expandMode.mode === "all") {
      jsonViewer.expandAll();
      return;
    }
    if (expandMode.mode === "collapse") {
      jsonViewer.collapseAll?.();
      return;
    }
    if (expandMode.mode === "paths" && expandMode.paths?.length) {
      jsonViewer.collapseAll?.();
      expandMode.paths.forEach((p) => jsonViewer.expand?.(p));
    }
  };

  queueMicrotask(() => {
    const done = jsonViewer.updateComplete;
    if (done && typeof done.then === "function") {
      done.then(run).catch(run);
    } else {
      requestAnimationFrame(run);
    }
  });
}

/**
 * Buat satu node `<json-viewer>` atau `<pre>` fallback (tidak menempel ke DOM).
 * @param {*} payload
 * @param {{
 *   theme?: "light"|"dark",
 *   expandAll?: boolean,
 *   expandPaths?: string[],
 *   expandUnder?: { prefix: string, indices: number[] },
 *   fallbackClass?: string,
 * }} [options]
 * @returns {HTMLElement}
 */
export function createJsonViewerElement(payload, options = {}) {
  const theme = options.theme ?? "light";
  const fallbackClass = options.fallbackClass ?? DEFAULT_FALLBACK_CLASS;

  const normalizedPayload = normalizeViewerPayload(payload);

  if (!jsonViewerRegistered()) {
    const fallback = document.createElement("pre");
    fallback.className = fallbackClass;
    fallback.textContent = JSON.stringify(normalizedPayload, null, 2);
    return fallback;
  }

  const jsonViewer = document.createElement("json-viewer");
  jsonViewer.setAttribute("data", JSON.stringify(normalizedPayload));
  applyJsonViewerTheme(jsonViewer, theme);

  const expandMode = resolveJsonViewerExpandMode(options);
  applyJsonViewerExpand(jsonViewer, expandMode);

  return jsonViewer;
}

/**
 * Kosongkan container lalu isi dengan `<json-viewer>` atau `<pre>` fallback.
 * @param {HTMLElement|null} container
 * @param {*} payload
 * @param {{
 *   theme?: "light"|"dark",
 *   expandAll?: boolean,
 *   expandPaths?: string[],
 *   expandUnder?: { prefix: string, indices: number[] },
 *   fallbackClass?: string,
 * }} [options]
 * @returns {HTMLElement|null} node yang ditambahkan
 */
export function renderJsonViewer(container, payload, options = {}) {
  if (!container) return null;

  container.textContent = "";
  const el = createJsonViewerElement(payload, options);
  container.appendChild(el);
  return el;
}

/**
 * Cuplikan CSS untuk host: tambahkan class `nx-json-view-host` pada wrapper,
 * lalu selipkan string ini di dalam `<style>…</style>` halaman/modul Anda.
 */
export const JSON_VIEWER_HOST_CSS = `
.nx-json-view-host json-viewer {
	display: block;
	--indent-size: 1rem;
	--line-height: 1.35rem;
	--indentguide-size: 2px;
	--indentguide-style: solid;
}
.nx-json-view-host .${DEFAULT_FALLBACK_CLASS} {
	margin: 0;
	white-space: pre-wrap;
	word-break: break-word;
	color: #334155;
	font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
}
`.trim();

/**
 * Satu pemanggilan untuk bikin atau pasang viewer JSON.
 * @param {{
 *   theme?: "light"|"dark",
 *   rows?: *,
 *   payload?: *,
 *   data?: *,
 *   expandAll?: boolean,
 *   expandPaths?: string[],
 *   expandUnder?: { prefix: string, indices: number[] },
 *   fallbackClass?: string,
 *   container?: HTMLElement|null,
 *   host?: HTMLElement|null,
 *   el?: HTMLElement|null,
 * }} [options]
 * @returns {HTMLElement|null} root `<json-viewer>` atau `<pre>` fallback; jika `container` diisi, sama seperti `renderJsonViewer`.
 */
export function Json(options = {}) {
  const opts = options && typeof options === "object" ? options : {};
  const payload = opts.rows ?? opts.payload ?? opts.data ?? {};
  const inner = {
    theme: opts.theme ?? "light",
    expandAll: opts.expandAll,
    expandPaths: opts.expandPaths,
    expandUnder: opts.expandUnder,
    fallbackClass: opts.fallbackClass,
  };
  const container = opts.container ?? opts.host ?? opts.el ?? null;
  if (container) {
    return renderJsonViewer(container, payload, inner);
  }
  return createJsonViewerElement(payload, inner);
}

/**
 * Tunggu elemen host ada di DOM (mis. HTML dari route/Layer dipasang belakangan), lalu `Json({ container, rows, … })`.
 * @param {{
 *   hostId?: string,
 *   id?: string,
 *   selector?: string,
 *   rows?: *,
 *   payload?: *,
 *   data?: *,
 *   theme?: "light"|"dark",
 *   expandAll?: boolean,
 *   expandPaths?: string[],
 *   expandUnder?: { prefix: string, indices: number[] },
 *   fallbackClass?: string,
 *   maxAttempts?: number,
 *   delayMs?: number,
 *   warnLabel?: string,
 * }} [options]
 */
export function scheduleJsonViewer(options = {}) {
  if (typeof document === "undefined") return;

  const o = options && typeof options === "object" ? options : {};
  const rawId = o.hostId ?? o.id;
  const selector = o.selector;
  const maxAttempts = o.maxAttempts ?? 80;
  const delayMs = o.delayMs ?? 40;
  const warnLabel = o.warnLabel ?? "[Json.schedule]";

  const resolveHost = () => {
    if (rawId != null && rawId !== "") {
      const id = String(rawId).replace(/^#/, "");
      return document.getElementById(id);
    }
    if (selector) return document.querySelector(selector);
    return null;
  };

  const payload = o.rows ?? o.payload ?? o.data ?? {};
  const theme = o.theme ?? "light";
  const fallbackClass = o.fallbackClass;
  const expandAll = o.expandAll;
  const expandPaths = o.expandPaths;
  const expandUnder = o.expandUnder;

  function tick(attempt) {
    const host = resolveHost();
    if (host && typeof Json === "function") {
      Json({
        theme,
        rows: payload,
        container: host,
        expandAll,
        expandPaths,
        expandUnder,
        fallbackClass,
      });
      return;
    }
    if (attempt < maxAttempts) {
      setTimeout(() => tick(attempt + 1), delayMs);
    } else if (typeof console !== "undefined" && console.warn) {
      console.warn(
        `${warnLabel} Host (${rawId ?? selector ?? "?"}) atau Json tidak tersedia — viewer tidak di-render.`,
      );
    }
  }

  setTimeout(() => tick(0), 0);
}

Object.assign(Json, {
  JSON_VIEWER_HOST_CSS,
  renderJsonViewer,
  createJsonViewerElement,
  normalizeViewerPayload,
  applyJsonViewerTheme,
  applyJsonViewerExpand,
  resolveJsonViewerExpandMode,
  jsonViewerRegistered,
  schedule: scheduleJsonViewer,
});
