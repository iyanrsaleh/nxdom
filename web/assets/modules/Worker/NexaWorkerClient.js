/**
 * Main-thread bridge ke NexaWebWorker.js
 */
let seq = 0;
const pending = new Map();

export function createNexaWorkerClient(workerScriptUrl) {
  if (typeof Worker === "undefined") {
    return null;
  }

  const worker = new Worker(workerScriptUrl, { type: "classic" });

  worker.onmessage = (e) => {
    const data = e.data || {};
    const { id, ok, parsed, errorMessage, status, statusText } = data;
    const entry = pending.get(id);
    if (!entry) return;
    pending.delete(id);

    if (errorMessage && ok !== true) {
      const err = new Error(errorMessage);
      if (status != null) err.status = status;
      if (statusText != null) err.statusText = statusText;
      if (parsed !== undefined) err.details = parsed;
      entry.reject(err);
      return;
    }

    if (ok === false) {
      const err = new Error(errorMessage || `HTTP Error: ${status}`);
      err.status = status;
      err.statusText = statusText;
      err.details = parsed;
      entry.reject(err);
      return;
    }

    entry.resolve(parsed);
  };

  worker.onerror = (ev) => {
    const msg = ev.message || "Web Worker error";
    for (const [id, entry] of pending) {
      pending.delete(id);
      entry.reject(new Error(msg));
    }
  };

  return {
    /**
     * Fetch generik di worker (GET/POST/…). GET tanpa body.
     * @param {string} url
     * @param {{ method?: string, body?: unknown, headers?: Record<string, string> }} [opts]
     */
    request(url, opts = {}) {
      const method = (opts.method || "POST").toUpperCase();
      const isGet =
        method === "GET" ||
        method === "HEAD" ||
        method === "OPTIONS";
      const body =
        opts.body != null && !isGet
          ? typeof opts.body === "string"
            ? opts.body
            : JSON.stringify(opts.body)
          : undefined;
      const id = ++seq;
      return new Promise((resolve, reject) => {
        pending.set(id, { resolve, reject });
        worker.postMessage({
          id,
          type: "FETCH",
          url,
          method,
          body,
          headers:
            opts.headers ||
            (isGet
              ? { Accept: "application/json, */*" }
              : { "Content-Type": "application/json" }),
        });
      });
    },

    post(url, data) {
      return this.request(url, { method: "POST", body: data });
    },

    parseJson(text) {
      const id = ++seq;
      return new Promise((resolve, reject) => {
        pending.set(id, { resolve, reject });
        worker.postMessage({ id, type: "PARSE_JSON", text });
      });
    },

    /**
     * Build HTML dari spec tree di worker thread (non-blocking).
     * Spec adalah plain object: { tag, a, s, c, t }
     * @param {Object} spec
     * @returns {Promise<string>}
     */
    build(spec) {
      const id = ++seq;
      return new Promise((resolve, reject) => {
        pending.set(id, {
          resolve: (result) => resolve(result.html ?? result.parsed ?? ''),
          reject,
        });
        worker.postMessage({ id, type: 'BUILD', spec });
      });
    },

    /**
     * Map array dengan template fn di worker thread (non-blocking).
     * fn adalah fungsi yang menerima (item, index, NX) dan mengembalikan
     * NX builder atau string. fn akan di-serialize ke string.
     *
     * @param {Array}    data  — array data
     * @param {Function} fn    — (item, index, NX) => NX builder | string
     * @returns {Promise<string>}
     * @example
     * const html = await workerClient.buildMap(components, (item, i, NX) =>
     *   NX.div().class('card').container()
     *     .p().view(item.label)
     *     .span().view(item.version)
     * );
     */
    buildMap(data, fn) {
      const id = ++seq;
      const fnStr = typeof fn === 'string' ? fn : fn.toString();
      return new Promise((resolve, reject) => {
        pending.set(id, {
          resolve: (result) => resolve(result.html ?? result.parsed ?? ''),
          reject,
        });
        worker.postMessage({ id, type: 'BUILD_MAP', data, fn: fnStr });
      });
    },

    /**
     * Pretty-print HTML string di worker (non-blocking).
     * @param {string} html
     * @returns {Promise<string>}
     */
    pretty(html) {
      const id = ++seq;
      return new Promise((resolve, reject) => {
        pending.set(id, {
          resolve: (result) => resolve(result.html ?? ''),
          reject,
        });
        worker.postMessage({ id, type: 'PRETTY', html });
      });
    },

    terminate() {
      worker.terminate();
      pending.clear();
    },

    get raw() {
      return worker;
    },
  };
}
