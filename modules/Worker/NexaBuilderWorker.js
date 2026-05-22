/**
 * NexaBuilderWorker — pure string-based HTML builder di Web Worker thread.
 *
 * Tidak menggunakan DOM (document.createElement tidak tersedia di worker).
 * Menggunakan string concatenation murni.
 *
 * Messages yang diterima:
 *   { id, type: 'BUILD',      spec }              → toHtml(spec)
 *   { id, type: 'BUILD_MAP',  data, fn }          → data.map(fn).join('')
 *   { id, type: 'PRETTY',     html }              → pretty-print HTML string
 *   { id, type: 'PARSE_JSON', text }              → JSON.parse(text)
 *
 * Semua reply: { id, ok: true, html } atau { id, ok: false, errorMessage }
 */

// ─── HTML helpers ────────────────────────────────────────────────────────────

const _VOID = new Set([
  'area','base','br','col','embed','hr','img','input',
  'link','meta','param','source','track','wbr',
]);

function _esc(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Convert a spec node to HTML string.
 * Spec shape:
 *   { tag, a?: {attrs}, s?: {styles}, c?: [children], t?: 'text', raw?: 'rawHtml' }
 *   or a plain string (inserted as-is).
 */
function toHtml(node) {
  if (node == null) return '';
  if (typeof node === 'string') return node;
  if (Array.isArray(node)) return node.map(toHtml).join('');

  const { tag, a = {}, s = {}, c = [], t, raw } = node;
  if (!tag) return raw ? String(raw) : (t ? _esc(t) : '');

  // Attributes
  let attrStr = '';
  for (const [k, v] of Object.entries(a)) {
    if (v == null) continue;
    attrStr += ` ${k}="${_esc(v)}"`;
  }

  // Inline styles
  const styleEntries = Object.entries(s);
  if (styleEntries.length) {
    attrStr += ` style="${styleEntries.map(([k, v]) => `${k}:${v}`).join('; ')}"`;
  }

  if (_VOID.has(tag)) return `<${tag}${attrStr}>`;

  // Inner content
  let inner = '';
  if (raw != null)      inner += String(raw);
  else if (t != null)   inner += _esc(t);
  inner += c.map(toHtml).join('');

  return `<${tag}${attrStr}>${inner}</${tag}>`;
}

// ─── NX spec builder (available inside fn strings) ───────────────────────────

/**
 * Lightweight spec builder — mirrors the NXUI fluent API but returns plain objects.
 * Used inside BUILD_MAP callback functions running in this worker.
 *
 * Usage (inside fn string):
 *   (item, index, NX) => NX.div().class('card').container()
 *     .p().view(item.label)
 *     .span().view(item.version)
 *
 * API: .id() .class() .attr() .style() .container() .view() .end() .el()
 */
const NX = (() => {
  // NexaSpecBuilder — pure object, no DOM
  class NexaSpecBuilder {
    constructor(tag, root = null, parent = null) {
      this._spec = { tag, a: {}, s: {}, c: [] };
      this._root = root || this._spec;
      this._parent = parent || null;
      if (parent) parent.c.push(this._spec);
    }

    id(v)    { this._spec.a.id = String(v); return this; }
    class(v) { this._spec.a.class = String(v); return this; }
    attr(k, v) { this._spec.a[k] = String(v); return this; }

    /**
     * Pasang event handler inline — hanya string yang didukung di Worker context.
     * Function diabaikan (tidak bisa di-serialize ke HTML).
     * @param {string}          event
     * @param {string|Function} handler — gunakan string untuk inline handler
     */
    on(event, handler) {
      if (typeof handler === 'string') this._spec.a['on' + event] = handler;
      return this;
    }
    /** Shortcut `.on('click', handler)` */
    onclick(handler) { return this.on('click', handler); }

    style(obj) {
      if (obj && typeof obj === 'object') Object.assign(this._spec.s, obj);
      return this;
    }

    container() { return new NexaSpecContainerBuilder(this._root, this._spec); }

    view(content = '') {
      this._spec.t = content;
      return new NexaSpecViewResult(this._root, this._parent);
    }

    html(content = '') {
      this._spec.raw = content;
      return toHtml(this._root);
    }

    end() {
      const up = this._parent && this._parent !== this._root
        ? (this._parent._parent || this._root)
        : this._root;
      return new NexaSpecViewResult(this._root, up);
    }

    map(array, fn) {
      const arr = Array.isArray(array) ? array : Array.from(array || []);
      arr.forEach((item, i) => {
        const result = fn(item, i, NX);
        if (result == null) return;
        const spec = result instanceof NexaSpecBuilder ? result._spec
          : (result && result._root ? result._root : null);
        if (spec && spec !== this._spec) {
          // only append if not already appended via constructor
          if (!this._spec.c.includes(spec)) this._spec.c.push(spec);
        }
      });
      return new NexaSpecViewResult(this._root, this._parent);
    }

    toString() { return toHtml(this._root); }
    valueOf()  { return this.toString(); }
    el()       { return this._spec; }
    root()     { return this._root; }
  }

  class NexaSpecContainerBuilder {
    constructor(root, parent) { this._root = root; this._parent = parent; }

    _child(tag, c) {
      const b = new NexaSpecBuilder(tag, this._root, this._parent);
      return c ? b.container() : b;
    }

    div(c)       { return this._child('div', c); }
    span(c)      { return this._child('span', c); }
    p(c)         { return this._child('p', c); }
    /** @param {string} [href] — jika diisi, set atribut href otomatis */
    a(href)      { const b = this._child('a'); return href != null ? b.attr('href', href) : b; }
    button(c)    { return this._child('button', c); }
    /** Shortcut button dengan click handler — setara `.button().onclick(handler)`. */
    btn(handler) { return this._child('button').onclick(handler); }
    input(c)     { return this._child('input', c); }
    ul(c)        { return this._child('ul', c); }
    ol(c)        { return this._child('ol', c); }
    li(c)        { return this._child('li', c); }
    h1(c)        { return this._child('h1', c); }
    h2(c)        { return this._child('h2', c); }
    h3(c)        { return this._child('h3', c); }
    h4(c)        { return this._child('h4', c); }
    h5(c)        { return this._child('h5', c); }
    h6(c)        { return this._child('h6', c); }
    /** @param {string} [src] — jika diisi, set atribut src otomatis */
    img(src)     { const b = this._child('img'); return src != null ? b.attr('src', src) : b; }
    section(c)   { return this._child('section', c); }
    article(c)   { return this._child('article', c); }
    nav(c)       { return this._child('nav', c); }
    aside(c)     { return this._child('aside', c); }
    strong(c)    { return this._child('strong', c); }
    em(c)        { return this._child('em', c); }
    small(c)     { return this._child('small', c); }
    code(c)      { return this._child('code', c); }
    pre(c)       { return this._child('pre', c); }
    table(c)     { return this._child('table', c); }
    thead(c)     { return this._child('thead', c); }
    tbody(c)     { return this._child('tbody', c); }
    tr(c)        { return this._child('tr', c); }
    td(c)        { return this._child('td', c); }
    th(c)        { return this._child('th', c); }
    icon(classes)   { const b = this._child('i'); return classes ? b.class(classes) : b; }

    map(array, fn) {
      const arr = Array.isArray(array) ? array : Array.from(array || []);
      arr.forEach((item, i) => {
        const result = fn(item, i, NX);
        if (result == null) return;
        if (result instanceof NexaSpecBuilder && !result._parent) {
          this._parent.c.push(result._spec);
        }
      });
      return new NexaSpecViewResult(this._root, this._parent);
    }
  }

  class NexaSpecViewResult {
    constructor(root, parent) { this._root = root; this._parent = parent; }

    _sibling(tag, c) {
      const b = new NexaSpecBuilder(tag, this._root, this._parent);
      return c ? b.container() : b;
    }

    div(c)       { return this._sibling('div', c); }
    span(c)      { return this._sibling('span', c); }
    p(c)         { return this._sibling('p', c); }
    /** @param {string} [href] — jika diisi, set atribut href otomatis */
    a(href)      { const b = this._sibling('a'); return href != null ? b.attr('href', href) : b; }
    button(c)    { return this._sibling('button', c); }
    /** Shortcut button dengan click handler — setara `.button().onclick(handler)`. */
    btn(handler) { return this._sibling('button').onclick(handler); }
    ul(c)        { return this._sibling('ul', c); }
    ol(c)        { return this._sibling('ol', c); }
    li(c)        { return this._sibling('li', c); }
    h1(c)        { return this._sibling('h1', c); }
    h2(c)        { return this._sibling('h2', c); }
    h3(c)        { return this._sibling('h3', c); }
    h4(c)        { return this._sibling('h4', c); }
    h5(c)        { return this._sibling('h5', c); }
    h6(c)        { return this._sibling('h6', c); }
    /** @param {string} [src] — jika diisi, set atribut src otomatis */
    img(src)     { const b = this._sibling('img'); return src != null ? b.attr('src', src) : b; }
    section(c)   { return this._sibling('section', c); }
    article(c)   { return this._sibling('article', c); }
    nav(c)       { return this._sibling('nav', c); }
    strong(c)    { return this._sibling('strong', c); }
    em(c)        { return this._sibling('em', c); }
    small(c)     { return this._sibling('small', c); }
    icon(classes)   { const b = this._sibling('i'); return classes ? b.class(classes) : b; }

    end() {
      const up = this._parent && this._parent !== this._root
        ? (this._parent._parent || this._root)
        : this._root;
      return new NexaSpecViewResult(this._root, up);
    }

    map(array, fn) {
      const arr = Array.isArray(array) ? array : Array.from(array || []);
      arr.forEach((item, i) => {
        const result = fn(item, i, NX);
        if (result == null) return;
        if (result instanceof NexaSpecBuilder && !result._parent) {
          if (this._parent) this._parent.c.push(result._spec);
        }
      });
      return this;
    }

    toString() { return toHtml(this._root); }
    valueOf()  { return this.toString(); }
    el()       { return this._root; }
    root()     { return this._root; }
  }

  // Factory: NX.div(), NX.span(), etc.
  return new Proxy({}, {
    get(_, tag) {
      if (tag === '_classes') return { NexaSpecBuilder, NexaSpecContainerBuilder, NexaSpecViewResult };
      return () => new NexaSpecBuilder(tag);
    }
  });
})();

// ─── Pretty-printer (string-based, no DOM) ────────────────────────────────────

function _prettySpec(node, depth) {
  if (!node || typeof node !== 'object') return '';
  const pad = '  '.repeat(depth);
  const { tag, a = {}, s = {}, c = [], t, raw } = node;
  if (!tag) return t ? `${pad}${_esc(t)}\n` : '';

  let attrStr = '';
  for (const [k, v] of Object.entries(a)) {
    if (v == null) continue;
    attrStr += ` ${k}="${_esc(v)}"`;
  }
  const styleEntries = Object.entries(s);
  if (styleEntries.length) {
    attrStr += ` style="${styleEntries.map(([k, v]) => `${k}:${v}`).join('; ')}"`;
  }

  if (_VOID.has(tag)) return `${pad}<${tag}${attrStr}>\n`;

  const inner = raw ?? t ?? null;
  if (!c.length && inner != null && String(inner).trim() !== '') {
    return `${pad}<${tag}${attrStr}>${inner != null ? _esc(String(inner)) : ''}</${tag}>\n`;
  }

  let out = `${pad}<${tag}${attrStr}>\n`;
  c.forEach(ch => { out += _prettySpec(ch, depth + 1); });
  if (!c.length && inner != null) out += `${'  '.repeat(depth + 1)}${_esc(String(inner ?? ''))}\n`;
  return out + `${pad}</${tag}>\n`;
}

/**
 * Pretty-print an HTML string by parsing it as a spec tree first.
 * Falls back to simple indent-based formatting.
 */
function _prettyHtml(html) {
  // Simple regex-based formatter (no DOM needed)
  const lines = [];
  let depth = 0;
  const tokens = html.replace(/>\s*</g, '>\n<').split('\n');

  for (const raw of tokens) {
    const t = raw.trim();
    if (!t) continue;
    const isClose  = /^<\/[^>]+>/.test(t);
    const isSingle = _VOID.has((t.match(/^<([a-z][a-z0-9-]*)/i) || [])[1]?.toLowerCase() ?? '');
    const isSelfClose = t.endsWith('/>');
    const isOpen   = /^<[^/][^>]*[^/]>$/.test(t) || /^<[^/][^>]*>$/.test(t);

    if (isClose) depth = Math.max(0, depth - 1);
    lines.push('  '.repeat(depth) + t);
    if (isOpen && !isSingle && !isSelfClose && !isClose) depth++;
  }
  return lines.join('\n');
}

// ─── Message handler ─────────────────────────────────────────────────────────

self.onmessage = async (e) => {
  const msg = e.data || {};
  const { id, type } = msg;

  // ── PARSE_JSON (compat with NexaWebWorker) ──
  if (type === 'PARSE_JSON') {
    try {
      self.postMessage({ id, ok: true, parsed: JSON.parse(msg.text) });
    } catch (err) {
      self.postMessage({ id, ok: false, errorMessage: err.message });
    }
    return;
  }

  // ── BUILD — render a spec tree to HTML string ──
  if (type === 'BUILD') {
    try {
      const html = toHtml(msg.spec);
      self.postMessage({ id, ok: true, html });
    } catch (err) {
      self.postMessage({ id, ok: false, errorMessage: err?.message || String(err) });
    }
    return;
  }

  // ── BUILD_MAP — iterate array with fn string, return joined HTML ──
  if (type === 'BUILD_MAP') {
    const { data, fn: fnStr } = msg;
    try {
      // fn receives (item, index, NX) where NX is the worker spec builder
      // eslint-disable-next-line no-new-func
      const fn = new Function('item', 'index', 'NX', `return (${fnStr})(item, index, NX)`);
      const arr = Array.isArray(data) ? data : [];
      const parts = arr.map((item, i) => {
        const result = fn(item, i, NX);
        if (result == null) return '';
        if (typeof result === 'string') return result;
        return String(result); // triggers toString() → toHtml()
      });
      self.postMessage({ id, ok: true, html: parts.join('') });
    } catch (err) {
      self.postMessage({ id, ok: false, errorMessage: err?.message || String(err) });
    }
    return;
  }

  // ── PRETTY — pretty-print an HTML string ──
  if (type === 'PRETTY') {
    try {
      self.postMessage({ id, ok: true, html: _prettyHtml(msg.html || '') });
    } catch (err) {
      self.postMessage({ id, ok: false, errorMessage: err?.message || String(err) });
    }
    return;
  }

  // Unknown type
  self.postMessage({ id, ok: false, errorMessage: `Unknown BUILD message type: ${type}` });
};
