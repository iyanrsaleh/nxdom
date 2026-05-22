// ─── Internal helpers ──────────────────────────────────────────────────────

/**
 * Pretty-print HTML string using the live DOM.
 * @param {string} html
 * @returns {string}
 */
function _nxPrettyHTML(html) {
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  return _nxFormatNode(tmp.firstElementChild || tmp.firstChild, 0).trimEnd();
}

const _NX_VOID = new Set(['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']);

function _nxFormatNode(node, depth) {
  if (!node) return '';
  const pad = '  '.repeat(depth);
  if (node.nodeType === 3) {
    const t = node.textContent.trim();
    return t ? pad + t + '\n' : '';
  }
  if (node.nodeType !== 1) return '';
  const tag = node.tagName.toLowerCase();
  const attrs = Array.from(node.attributes).map(a => ` ${a.name}="${a.value}"`).join('');
  if (_NX_VOID.has(tag)) return `${pad}<${tag}${attrs}>\n`;
  if (!node.hasChildNodes()) return `${pad}<${tag}${attrs}></${tag}>\n`;
  const kids = Array.from(node.childNodes);
  if (kids.length === 1 && kids[0].nodeType === 3) {
    const t = kids[0].textContent.trim();
    return t ? `${pad}<${tag}${attrs}>${t}</${tag}>\n` : `${pad}<${tag}${attrs}></${tag}>\n`;
  }
  let out = `${pad}<${tag}${attrs}>\n`;
  kids.forEach(k => { out += _nxFormatNode(k, depth + 1); });
  return out + `${pad}</${tag}>\n`;
}

/**
 * Build the "show source" block: element HTML + <pre><innerTag>formatted source</innerTag></pre>.
 * @param {string} rootHTML
 * @param {string} [innerTag='code']
 * @returns {string}
 */
/**
 * Core map engine for builder chains.
 * Iterates `array`, calls `fn(item, index, array)` for each item, and
 * appends the result into `parent`. Accepts:
 *   - string / NexaViewResult / NexaElementBuilder / anything with toString()
 * Returns a NexaViewResult so the chain continues at the same level.
 *
 * @param {Element}  root
 * @param {Element}  parent
 * @param {Array}    array
 * @param {Function} fn
 * @returns {NexaViewResult}
 */
function _nxApplyMap(root, parent, array, fn) {
  const arr = Array.isArray(array) ? array : Array.from(array || []);
  arr.forEach((item, index) => {
    const result = fn(item, index, arr);
    if (result == null) return;
    if (result instanceof NexaElementBuilder) {
      if (!result._parent) {
        // Standalone builder (created with NXUI.tag() — no parent yet)
        parent.appendChild(result._el);
      } else {
        // Connected builder (e.g. chain ended with .icon().color())
        // The elements are already built into _root — append the root
        const node = result._root;
        if (node && node !== root && !node.parentElement) {
          parent.appendChild(node);
        }
      }
    } else if (result instanceof NexaViewResult) {
      const node = result._root;
      // Append only if it is a detached standalone node
      if (node && node !== root && !node.parentElement) {
        parent.appendChild(node);
      }
    } else if (result instanceof NexaContainerBuilder) {
      // NexaContainerBuilder: ambil _root (elemen container itu sendiri)
      const node = result._root;
      if (node && node !== root && !node.parentElement) {
        parent.appendChild(node);
      }
    } else if (typeof result === 'string') {
      parent.insertAdjacentHTML('beforeend', result);
    } else {
      parent.insertAdjacentHTML('beforeend', String(result));
    }
  });
  return new NexaViewResult(root, parent);
}

// ─── DOM → NexaKit JS source auto-generator ──────────────────────────────────
const _NX_VOID_TAGS = new Set(['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']);
// Layout/container tags: string arg = class name (bukan text content)
const _NX_LAYOUT_TAGS = new Set(['div','section','article','header','footer','main','nav','aside','form','ul','ol','table','thead','tbody','tfoot','tr','colgroup','fieldset','dl','figure','details','summary']);

function _nxToKitSource(el, depth, isFirst) {
  if (!el || el.nodeType !== 1) return '';
  const pad = '  '.repeat(depth);
  const tag = el.tagName.toLowerCase();
  const attrs = Array.from(el.attributes);
  let classV = '', idV = '', hrefV = null, srcV = null;
  const extra = [];
  for (const a of attrs) {
    if      (a.name === 'class') classV = a.value;
    else if (a.name === 'id')    idV    = a.value;
    else if (a.name === 'href')  hrefV  = a.value;
    else if (a.name === 'src')   srcV   = a.value;
    else extra.push(a);
  }
  const kids = Array.from(el.childNodes).filter(n =>
    n.nodeType === 1 || (n.nodeType === 3 && n.textContent.trim())
  );
  const hasElKids = !_NX_VOID_TAGS.has(tag) && kids.some(n => n.nodeType === 1);
  const onlyText  = kids.length === 1 && kids[0].nodeType === 3;
  const txtVal    = onlyText ? kids[0].textContent.trim().replace(/'/g, "\\'") : '';
  // Containers with class: absorb class as arg → .div('row') not .div('c').class('row')
  const absorbClass = hasElKids && classV && tag !== 'a' && tag !== 'img';
  const isSpecial   = tag === 'a' || tag === 'img';

  // Build tagCall
  let tagCall;
  if (isFirst) {
    if      (absorbClass)            tagCall = `NXUI.${tag}('${classV.replace(/'/g,"\\'")}')`;
    else if (onlyText && !isSpecial) tagCall = `NXUI.${tag}('${txtVal}')`;
    else tagCall = hasElKids ? `NXUI.${tag}('c')` : `NXUI.${tag}()`;
  } else {
    if      (tag === 'a'   && hrefV !== null) tagCall = `.a('${hrefV.replace(/'/g,"\\'")}')`;
    else if (tag === 'img' && srcV  !== null) tagCall = `.img('${srcV.replace(/'/g,"\\'")}')`;
    else if (absorbClass)                     tagCall = `.${tag}('${classV.replace(/'/g,"\\'")}')`;
    else if (onlyText && !isSpecial)          tagCall = `.${tag}('${txtVal}')`;
    else tagCall = hasElKids ? `.${tag}('c')` : `.${tag}()`;
  }

  // Attribute chain
  let attrChain = '';
  if (idV)                    attrChain += `.id('${idV}')`;
  if (!absorbClass && classV) attrChain += `.class('${classV}')`;
  for (const a of extra)      attrChain += `.attr('${a.name}', '${a.value.replace(/'/g,"\\'")}')`;
  const method = tagCall + attrChain;

  if (_NX_VOID_TAGS.has(tag) || kids.length === 0) return pad + method + '\n';
  if (onlyText) {
    if (isSpecial) return pad + method + `.view('${txtVal}')` + '\n';
    return pad + method + '\n'; // text already inline in tagCall
  }
  let out = pad + method + '\n';
  for (const k of kids) if (k.nodeType === 1) out += _nxToKitSource(k, depth + 1, false);
  return out + pad + '.end()\n';
}

function _nxSourceBlock(rootHTML, innerTag = 'code', jsSource = null) {
  // Auto-generate NexaKit JS source dari DOM jika tidak diberikan
  if (!jsSource) {
    const tmp = document.createElement('div');
    tmp.innerHTML = rootHTML;
    const rootEl = tmp.firstElementChild;
    jsSource = rootEl ? _nxToKitSource(rootEl, 0, true).trimEnd() : '';
  }
  // Escape HTML source untuk kolom kiri
  const formatted = _nxPrettyHTML(rootHTML);
  const escapedHTML = formatted
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
  // Escape JS source untuk kolom kanan
  const escapedJS = String(jsSource)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
  if (typeof window !== 'undefined' && window.hljs) {
    requestAnimationFrame(() => {
      document.querySelectorAll('pre code[data-nx-hl]').forEach(el => {
        try {
          window.hljs.highlightElement(el);
        } catch (e) {
          const result = window.hljs.highlightAuto(el.textContent);
          el.innerHTML = result.value;
          el.classList.add('hljs');
        }
        el.removeAttribute('data-nx-hl');
      });
    });
  }
  return `<div class="nx-row">`
    + `<div class="col-12">${rootHTML}</div>`
    + `<div class="col-6">
         <h1>ES+</h1>
        <pre><${innerTag} class="language-javascript" data-nx-hl>${escapedJS}</${innerTag}></pre>

      </div>`
    + `<div class="col-6">
           <h1>Output:HTML</h1>
           <pre><${innerTag} class="language-html" data-nx-hl>${escapedHTML}</${innerTag}></pre>
       </div>`
    + `</div>`;
}

// ─── Worker integration ──────────────────────────────────────────────────────

/** Set oleh Nexa.js setelah NXUI.Worker diinisialisasi. */
let _nxWorkerRef = null;

/**
 * Hubungkan builder ke NXUI.Worker.
 * Dipanggil dari Nexa.js: `_nxSetWorker(NXUI.Worker)`
 * @param {{ build(spec):Promise<string> }} workerAPI
 */
export function _nxSetWorker(workerAPI) { _nxWorkerRef = workerAPI; }

/**
 * Konversi DOM element ke plain spec object yang bisa dikirim ke Worker.
 * Spec: { tag, a:{attrs}, s:{styles}, c:[children], t:'text' }
 * @param {Node} el
 * @returns {Object|null}
 */
function _nxDomToSpec(el) {
  if (!el) return null;
  if (el.nodeType === 3) {
    const t = (el.textContent || '').trim();
    return t ? { tag: null, t } : null;
  }
  if (el.nodeType !== 1) return null;
  const spec = { tag: el.tagName.toLowerCase(), a: {}, s: {}, c: [] };
  for (const { name, value } of el.attributes) {
    if (name === 'style') continue;
    spec.a[name] = value;
  }
  if (el.style?.cssText) {
    for (const prop of el.style) {
      spec.s[prop] = el.style.getPropertyValue(prop);
    }
  }
  for (const child of el.childNodes) {
    const cs = _nxDomToSpec(child);
    if (cs) spec.c.push(cs);
  }
  return spec;
}

// ────────────────────────────────────────────────────────────────────────────

/**
 * NexaElementBuilder — fluent builder untuk membuat elemen HTML baru.
 *
 * Usage (standalone):
 *   NXUI.div().id('box').class('card').style({ width: '200px' }).html('Hello')
 *   // → '<div id="box" class="card" style="width: 200px;">Hello</div>'
 *
 * Usage (tree / container):
 *   NXUI.div().id('tes')
 *     .container()
 *     .p().id('p1')
 *     .span().id('s1').view('hello')
 *   // → '<div id="tes"><p id="p1"></p><span id="s1">hello</span></div>'
 *
 *   NXUI.div().id('tes')
 *     .container()
 *     .p().id('p1').container()
 *       .span().view('nested')
 *   // → '<div id="tes"><p id="p1"><span>nested</span></p></div>'
 *
 * API:
 *   .container() → enter child-building mode (returns NexaContainerBuilder)
 *   .view(str)   → terminal, sets innerHTML, returns ROOT outerHTML
 *   .html(str)   → terminal, sets innerHTML, returns THIS element's outerHTML
 *   .el()        → raw DOM element
 *   .root()      → raw root DOM element
 *   tag methods  → when inside a container, create SIBLINGS (same parent)
 */
export class NexaElementBuilder {
  /**
   * @param {string}       tagName
   * @param {Element|null} root   — root of the whole tree; null = this element IS the root
   * @param {Element|null} parent — DOM parent to append into; null = standalone root
   */
  constructor(tagName, root = null, parent = null) {
    this._el = document.createElement(tagName);
    this._root = root || this._el;
    this._parent = parent || null;
    if (parent) {
      parent.appendChild(this._el);
    }
  }

  /** Set id attribute — chainable */
  id(value) {
    this._el.id = value;
    return this;
  }

  /** Set className (replaces all classes) — chainable */
  class(value) {
    this._el.className = value;
    return this;
  }

  /** Add class(es) without replacing existing — chainable */
  addClass(value) {
    this._el.classList.add(...String(value).split(' ').filter(Boolean));
    return this;
  }

  /** Set any HTML attribute — chainable */
  attr(name, value) {
    this._el.setAttribute(name, value);
    return this;
  }

  /** Set data-* attribute — chainable */
  data(key, value) {
    this._el.dataset[key] = value;
    return this;
  }

  /**
   * Set inline styles from an object — chainable.
   * Supports both camelCase (`fontSize`) and hyphenated (`font-size`) properties.
   * @param {Object} stylesObj — e.g. { width: '200px', 'font-style': 'italic' }
   */
  style(stylesObj) {
    if (typeof stylesObj === 'object' && stylesObj !== null) {
      Object.entries(stylesObj).forEach(([prop, val]) => {
        if (prop.includes('-')) {
          this._el.style.setProperty(prop, val);
        } else {
          this._el.style[prop] = val;
        }
      });
    }
    return this;
  }

  /**
   * Pasang event handler di elemen — chainable.
   * - String  → di-set sebagai atribut inline `on<event>="..."` (tetap di outerHTML).
   * - Function → addEventListener (hanya aktif jika elemen di-mount langsung via `.el()`).
   *
   * @param {string}          event   — nama event tanpa prefix 'on', e.g. 'click', 'change'
   * @param {string|Function} handler — inline string atau function reference
   * @returns {NexaElementBuilder}
   * @example
   *   NXUI.p().on('click', "buatPackage('Buat')").view('label')
   *   // → <p onclick="buatPackage('Buat')">label</p>
   */
  on(event, handler) {
    if (typeof handler === 'string') {
      this._el.setAttribute('on' + event, handler);
    } else if (typeof handler === 'function') {
      this._el.addEventListener(event, handler);
    }
    return this;
  }

  /**
   * Shortcut `.on('click', handler)` — chainable.
   * @param {string|Function} handler
   * @returns {NexaElementBuilder}
   * @example
   *   .p().onclick("buatPackage('Buat')").view(item.label)
   *   // → <p onclick="buatPackage('Buat')">alerts</p>
   */
  onclick(handler) { return this.on('click', handler); }

  /** Shortcut untuk .attr('src', value) — chainable */
  src(value)  { return this.attr('src', value); }

  /** Shortcut untuk .attr('href', value) — chainable */
  href(value) { return this.attr('href', value); }

  /** Shortcut untuk style.color — chainable */
  color(value) { this._el.style.color = value; return this; }
  /** Shortcut untuk style.fontSize — chainable. Unit opsional, default px. */
  fs(value)    { this._el.style.fontSize = String(value) + (/[a-z%]/.test(String(value)) ? '' : 'px'); return this; }

  /**
   * Set width & height sekaligus dari string format "WxH" — chainable.
   * Unit opsional; jika tidak ada unit maka default ke `px`.
   * @param {string} value — e.g. '300x400', '300x400px', '50%x100vh', '100vwx100vh'
   * @returns {NexaElementBuilder}
   * @example
   *   NXUI.div().wh('300x400').id('box')        // width:300px; height:400px
   *   NXUI.div('c').wh('100%x200px').id('wrap')  // width:100%; height:200px
   */
  wh(value) {
    const m = String(value).match(/^(\d+(?:\.\d+)?[a-z%]*)[xX](\d+(?:\.\d+)?[a-z%]*)$/);
    if (m) {
      this._el.style.width  = m[1] + (/[a-z%]/.test(m[1]) ? '' : 'px');
      this._el.style.height = m[2] + (/[a-z%]/.test(m[2]) ? '' : 'px');
    }
    return this;
  }

  /** Append child (NexaElementBuilder | Element | HTML string) — chainable */
  append(child) {
    if (typeof child === 'string') {
      this._el.insertAdjacentHTML('beforeend', child);
    } else if (child instanceof NexaElementBuilder) {
      this._el.appendChild(child._el);
    } else if (child instanceof Element) {
      this._el.appendChild(child);
    }
    return this;
  }

  /**
   * Enter container mode — tag calls after this create CHILDREN of the current element.
   * @returns {NexaContainerBuilder}
   * @example
   *   NXUI.div().id('wrap').container().span().view('hi')
   *   // → '<div id="wrap"><span>hi</span></div>'
   */
  container() {
    return new NexaContainerBuilder(this._root, this._el);
  }

  // ── Sibling tag methods ────────────────────────────────────────────────────
  // When this builder has a _parent (i.e. was created inside a container), these
  // create SIBLING elements appended to the same parent.
  // When called on a root element (no parent), creates a fresh standalone builder.
  //
  // Semua tag method menerima optional arg `c` (container shortcut):
  //   .div()           → NexaElementBuilder   (seperti biasa)
  //   .div('container') atau .div(true) atau .div('c')
  //                    → langsung masuk container mode (NexaContainerBuilder)
  _sibling(tagName, c) {
    const b = this._parent
      ? new NexaElementBuilder(tagName, this._root, this._parent)
      : new NexaElementBuilder(tagName);
    if (!c) return b;
    if (c === 'c' || _NX_LAYOUT_TAGS.has(tagName)) {
      if (c !== 'c') b._el.className = c;
      return b.container();
    }
    b._el.innerHTML = c;
    return b;
  }

  div(c)       { return this._sibling('div', c); }
  span(c)      { return this._sibling('span', c); }
  p(c)         { return this._sibling('p', c); }
  /** @param {string} [href] — jika diisi, set atribut href otomatis */
  a(href)      { const b = this._sibling('a'); return href != null ? b.attr('href', href) : b; }
  button(c)    { return this._sibling('button', c); }
  /**
   * Shortcut button dengan click handler — setara `.button().onclick(handler)`.
   * @param {string|Function} handler
   * @returns {NexaElementBuilder}
   */
  btn(handler) { return this._sibling('button').onclick(handler); }
  input(c)     { return this._sibling('input', c); }
  select(c)    { return this._sibling('select', c); }
  textarea(c)  { return this._sibling('textarea', c); }
  fieldset(c)  { return this._sibling('fieldset', c); }
  legend(c)    { return this._sibling('legend', c); }
  dl(c)        { return this._sibling('dl', c); }
  dt(c)        { return this._sibling('dt', c); }
  dd(c)        { return this._sibling('dd', c); }
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
  form(c)      { return this._sibling('form', c); }
  label(c)     { return this._sibling('label', c); }
  section(c)   { return this._sibling('section', c); }
  article(c)   { return this._sibling('article', c); }
  header(c)    { return this._sibling('header', c); }
  footer(c)    { return this._sibling('footer', c); }
  main(c)      { return this._sibling('main', c); }
  nav(c)       { return this._sibling('nav', c); }
  aside(c)     { return this._sibling('aside', c); }
  table(c)     { return this._sibling('table', c); }
  thead(c)     { return this._sibling('thead', c); }
  tbody(c)     { return this._sibling('tbody', c); }
  tr(c)        { return this._sibling('tr', c); }
  td(c)        { return this._sibling('td', c); }
  th(c)        { return this._sibling('th', c); }
  strong(c)    { return this._sibling('strong', c); }
  em(c)        { return this._sibling('em', c); }
  small(c)     { return this._sibling('small', c); }
  code(c)      { return this._sibling('code', c); }
  /**
   * No arg → create sibling <pre> element (chainable).
   * With arg — TERMINAL: kiri = preview HTML, kanan = source code.
   * @param {string} [innerTag] — e.g. 'code'
   * @param {string} [jsSource] — kode JS yang ditampilkan di kolom kanan (opsional)
   */
  pre(innerTag, jsSource)  {
    if (innerTag === undefined) return this._sibling('pre');
    return _nxSourceBlock(this._root.outerHTML, innerTag, jsSource);
  }
  blockquote(c) { return this._sibling('blockquote', c); }
  /** Buat elemen <i> sebagai sibling — opsional class icon — chainable */
  icon(classes)    { const b = this._sibling('i'); return classes ? b.class(classes) : b; }

  /**
   * Iterate an array and append each result as a child of THIS element
   * (implicitly enters the element as a container).
   * @param {Array}    array
   * @param {Function} fn — (item, index, array) → string | NexaViewResult | NexaElementBuilder
   * @returns {NexaViewResult}
   */
  map(array, fn) {
    return _nxApplyMap(this._root, this._el, array, fn);
  }

  /**
   * Eksekusi fn(this) jika condition truthy — tetap di posisi chain yang sama jika false.
   * Berguna untuk conditional inline tanpa memutus chain.
   * @param {*}        condition
   * @param {Function} fn — (builder) → builder
   * @returns {NexaElementBuilder|NexaViewResult|NexaContainerBuilder}
   * @example
   *   NXUI.div('c')
   *     .h1().view(item.label)
   *     .when(item.version, b => b.span().color('gray').view(item.version))
   *     .when(!item.status, b => b.icon('bi bi-x').color('red'))
   */
  when(condition, fn) { return condition ? (fn(this) ?? this) : this; }

  /**
   * Set innerHTML — TERMINAL. Returns outerHTML of THIS element only.
   * @param {string} content
   * @returns {string}
   */
  html(content) {
    this._el.innerHTML = content;
    return this._el.outerHTML;
  }

  /**
   * Set innerHTML — chainable terminal. Returns NexaViewResult which:
   *   • coerces to root outerHTML in string context (template literals, innerHTML =)
   *   • exposes sibling tag methods so the chain can continue
   *
   * @param {string} [content='']
   * @returns {NexaViewResult}
   * @example
   *   NXUI.div().id('tes').container()
   *     .p().id('p1').view('hello')
   *     .span().id('s1').view('world')
   *   // toString() → '<div id="tes"><p id="p1">hello</p><span id="s1">world</span></div>'
   */
  view(content = '') {
    this._el.innerHTML = content;
    return new NexaViewResult(this._root, this._parent);
  }

  /**
   * Set textContent — TERMINAL. Returns outerHTML of THIS element.
   * @param {string} content
   * @returns {string}
   */
  text(content) {
    this._el.textContent = content;
    return this._el.outerHTML;
  }

  /**
   * Go back up one nesting level — returns a NexaViewResult whose sibling
   * methods create elements inside the PARENT of the current container.
   *
   * Use after finishing a nested .container() block to add siblings at the
   * outer level.
   *
   * @returns {NexaViewResult}
   * @example
   *   NXUI.div().id('wrap').container()
   *     .div().id('inner').container()
   *       .span().view('nested')
   *     .end()                  // ← back to div#wrap level
   *     .p().view('sibling')
   *   // → <div id="wrap"><div id="inner"><span>nested</span></div><p>sibling</p></div>
   */
  end() {
    // Clamp at root — calling .end() at the top level stays at root (safe).
    if (!this._parent || this._parent === this._root) {
      return new NexaViewResult(this._root, this._root);
    }
    const upParent = this._parent.parentElement || this._root;
    return new NexaViewResult(this._root, upParent);
  }

  /**
   * Serialize tree dan render di Web Worker — async, tidak memblok UI.
   * Fallback ke Promise.resolve(outerHTML) jika Worker belum tersedia.
   *
   * @returns {Promise<string>}
   * @example
   *   const html = await NXUI.div().id('box').container()
   *     .p().view('hello')
   *     .worker();
   *   container.innerHTML = html;
   */
  worker() {
    const spec = _nxDomToSpec(this._root);
    if (_nxWorkerRef && spec) return _nxWorkerRef.build(spec);
    return Promise.resolve(this._root.outerHTML);
  }

  /** Get the raw DOM Element. */
  el() {
    return this._el;
  }

  /** Get the raw root DOM Element (top of the tree). */
  root() {
    return this._root;
  }

  /** Returns outerHTML when coerced to a string. */
  toString() {
    return this._el.outerHTML;
  }
}

/**
 * NexaViewResult — returned by NexaElementBuilder.view().
 *
 * Acts as a string in string contexts (template literals, innerHTML =) returning
 * the root element's outerHTML. Also exposes sibling tag methods so the chain
 * can continue after a .view() call.
 *
 * @example
 *   const html = NXUI.div().id('tes').container()
 *     .p().id('tes1').view('Multiple')       // NexaViewResult
 *     .span().id('tes2').view('hello world') // NexaViewResult
 *   // `${html}` or html.toString() → full div outerHTML
 */
export class NexaViewResult {
  constructor(root, parent) {
    this._root = root;
    this._parent = parent;
  }

  /** Returns root outerHTML — used by template literals and string coercion. */
  toString() { return this._root ? this._root.outerHTML : ''; }
  valueOf()  { return this.toString(); }

  _sibling(tagName, c) {
    const b = this._parent
      ? new NexaElementBuilder(tagName, this._root, this._parent)
      : new NexaElementBuilder(tagName);
    if (!c) return b;
    if (c === 'c' || _NX_LAYOUT_TAGS.has(tagName)) {
      if (c !== 'c') b._el.className = c;
      return b.container();
    }
    b._el.innerHTML = c;
    return b;
  }

  div(c)       { return this._sibling('div', c); }
  span(c)      { return this._sibling('span', c); }
  p(c)         { return this._sibling('p', c); }
  /** @param {string} [href] — jika diisi, set atribut href otomatis */
  a(href)      { const b = this._sibling('a'); return href != null ? b.attr('href', href) : b; }
  button(c)    { return this._sibling('button', c); }
  /** Shortcut button dengan click handler — setara `.button().onclick(handler)`. */
  btn(handler) { return this._sibling('button').onclick(handler); }
  input(c)     { return this._sibling('input', c); }
  select(c)    { return this._sibling('select', c); }
  textarea(c)  { return this._sibling('textarea', c); }
  fieldset(c)  { return this._sibling('fieldset', c); }
  legend(c)    { return this._sibling('legend', c); }
  dl(c)        { return this._sibling('dl', c); }
  dt(c)        { return this._sibling('dt', c); }
  dd(c)        { return this._sibling('dd', c); }
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
  form(c)      { return this._sibling('form', c); }
  label(c)     { return this._sibling('label', c); }
  section(c)   { return this._sibling('section', c); }
  article(c)   { return this._sibling('article', c); }
  header(c)    { return this._sibling('header', c); }
  footer(c)    { return this._sibling('footer', c); }
  main(c)      { return this._sibling('main', c); }
  nav(c)       { return this._sibling('nav', c); }
  aside(c)     { return this._sibling('aside', c); }
  table(c)     { return this._sibling('table', c); }
  thead(c)     { return this._sibling('thead', c); }
  tbody(c)     { return this._sibling('tbody', c); }
  tr(c)        { return this._sibling('tr', c); }
  td(c)        { return this._sibling('td', c); }
  th(c)        { return this._sibling('th', c); }
  strong(c)    { return this._sibling('strong', c); }
  em(c)        { return this._sibling('em', c); }
  small(c)     { return this._sibling('small', c); }
  code(c)      { return this._sibling('code', c); }
  /**
   * No arg → create sibling <pre> element.
   * With arg — TERMINAL: kiri = preview HTML, kanan = source code.
   * @param {string} [innerTag] — e.g. 'code'
   * @param {string} [jsSource] — kode JS yang ditampilkan di kolom kanan (opsional)
   */
  pre(innerTag, jsSource) {
    if (innerTag === undefined) return this._sibling('pre');
    return _nxSourceBlock(this._root ? this._root.outerHTML : '', innerTag, jsSource);
  }
  blockquote(c) { return this._sibling('blockquote', c); }
  /** Buat elemen <i> sebagai sibling — opsional class icon — chainable */
  icon(classes)    { const b = this._sibling('i'); return classes ? b.class(classes) : b; }

  /**
   * Go back up one nesting level — same as NexaElementBuilder.end().
   * @returns {NexaViewResult}
   */
  end() {
    // Clamp at root — calling .end() at the top level stays at root (safe).
    if (!this._parent || this._parent === this._root) {
      return new NexaViewResult(this._root, this._root);
    }
    const upParent = this._parent.parentElement || this._root;
    return new NexaViewResult(this._root, upParent);
  }

  /**
   * Serialize tree dan render di Web Worker — async, tidak memblok UI.
   * Fallback ke Promise.resolve(outerHTML) jika Worker belum tersedia.
   * @returns {Promise<string>}
   */
  worker() {
    const spec = _nxDomToSpec(this._root);
    if (_nxWorkerRef && spec) return _nxWorkerRef.build(spec);
    return Promise.resolve(this._root ? this._root.outerHTML : '');
  }

  /**
   * Iterate an array and append each result into the current parent.
   * fn receives (item, index, array) and should return a string,
   * NexaViewResult, or NexaElementBuilder.
   *
   * @param {Array}    array
   * @param {Function} fn
   * @returns {NexaViewResult}
   * @example
   *   NXUI.ul().container()
   *     .map(items, item => NXUI.li().view(item.label))
   *   // → <ul><li>...</li><li>...</li></ul>
   */
  map(array, fn) {
    return _nxApplyMap(this._root, this._parent, array, fn);
  }

  /** Eksekusi fn(this) jika condition truthy, tetap di chain jika false. */
  when(condition, fn) { return condition ? (fn(this) ?? this) : this; }

  /** Get the raw root DOM element. */
  el()   { return this._root; }
  root() { return this._root; }
}

/**
 * NexaContainerBuilder — returned by NexaElementBuilder.container().
 * Tag methods create CHILD elements appended to the container element.
 *
 * @example
 *   NXUI.div().id('wrap')
 *     .container()
 *     .p().id('a')       // child of div#wrap
 *     .span().id('b')    // sibling of p#a (also child of div#wrap)
 *     .view('hi')        // → '<div id="wrap"><p id="a"></p><span id="b">hi</span></div>'
 */
export class NexaContainerBuilder {
  /**
   * @param {Element} root   — root element of the whole tree
   * @param {Element} parent — element to append children into
   */
  constructor(root, parent) {
    this._root = root;
    this._parent = parent;
  }

  // ── Attribute setters (operate on the container element itself) ───────────
  // Dipakai setelah .div('c') agar bisa langsung .div('c').id('x').class('y')
  id(value)       { this._parent.id = value; return this; }
  class(value)    { this._parent.className = value; return this; }
  addClass(value) { this._parent.classList.add(...String(value).split(' ').filter(Boolean)); return this; }
  attr(name, val) { this._parent.setAttribute(name, val); return this; }
  data(key, val)  { this._parent.dataset[key] = val; return this; }
  src(value)      { return this.attr('src', value); }
  href(value)     { return this.attr('href', value); }
  /** Shortcut untuk style.color — chainable */
  color(value)    { this._parent.style.color = value; return this; }
  /** Shortcut untuk style.fontSize — chainable. Unit opsional, default px. */
  fs(value)       { this._parent.style.fontSize = String(value) + (/[a-z%]/.test(String(value)) ? '' : 'px'); return this; }
  style(obj) {
    if (typeof obj === 'object' && obj !== null) {
      Object.entries(obj).forEach(([p, v]) => {
        if (p.includes('-')) this._parent.style.setProperty(p, v);
        else this._parent.style[p] = v;
      });
    }
    return this;
  }
  on(event, handler) {
    if (typeof handler === 'string') this._parent.setAttribute('on' + event, handler);
    else if (typeof handler === 'function') this._parent.addEventListener(event, handler);
    return this;
  }
  onclick(handler) { return this.on('click', handler); }
  /**
   * Set width & height sekaligus dari string format "WxH" — chainable.
   * @param {string} value — e.g. '300x400', '300x400px', '50%x100vh'
   */
  wh(value) {
    const m = String(value).match(/^(\d+(?:\.\d+)?[a-z%]*)[xX](\d+(?:\.\d+)?[a-z%]*)$/);
    if (m) {
      this._parent.style.width  = m[1] + (/[a-z%]/.test(m[1]) ? '' : 'px');
      this._parent.style.height = m[2] + (/[a-z%]/.test(m[2]) ? '' : 'px');
    }
    return this;
  }
  // ─────────────────────────────────────────────────────────────────────────

  _child(tagName, c) {
    const b = new NexaElementBuilder(tagName, this._root, this._parent);
    if (!c) return b;
    if (c === 'c' || _NX_LAYOUT_TAGS.has(tagName)) {
      if (c !== 'c') b._el.className = c;
      return b.container();
    }
    b._el.innerHTML = c;
    return b;
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
  select(c)    { return this._child('select', c); }
  textarea(c)  { return this._child('textarea', c); }
  fieldset(c)  { return this._child('fieldset', c); }
  legend(c)    { return this._child('legend', c); }
  dl(c)        { return this._child('dl', c); }
  dt(c)        { return this._child('dt', c); }
  dd(c)        { return this._child('dd', c); }
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
  form(c)      { return this._child('form', c); }
  label(c)     { return this._child('label', c); }
  section(c)   { return this._child('section', c); }
  article(c)   { return this._child('article', c); }
  header(c)    { return this._child('header', c); }
  footer(c)    { return this._child('footer', c); }
  main(c)      { return this._child('main', c); }
  nav(c)       { return this._child('nav', c); }
  aside(c)     { return this._child('aside', c); }
  table(c)     { return this._child('table', c); }
  thead(c)     { return this._child('thead', c); }
  tbody(c)     { return this._child('tbody', c); }
  tr(c)        { return this._child('tr', c); }
  td(c)        { return this._child('td', c); }
  th(c)        { return this._child('th', c); }
  strong(c)    { return this._child('strong', c); }
  em(c)        { return this._child('em', c); }
  small(c)     { return this._child('small', c); }
  code(c)      { return this._child('code', c); }
  pre(c)       { return this._child('pre', c); }
  blockquote(c){ return this._child('blockquote', c); }
  /** Buat elemen <i> sebagai child — opsional class icon — chainable */
  icon(classes)   { const b = this._child('i'); return classes ? b.class(classes) : b; }

  /**
   * Iterate an array and append each result as a child of the current container.
   * @param {Array}    array
   * @param {Function} fn — (item, index, array) → string | NexaViewResult | NexaElementBuilder
   * @returns {NexaViewResult}
   */
  map(array, fn) {
    return _nxApplyMap(this._root, this._parent, array, fn);
  }

  /** Eksekusi fn(this) jika condition truthy, tetap di chain jika false. */
  when(condition, fn) { return condition ? (fn(this) ?? this) : this; }

  /**
   * Serialize tree dan render di Web Worker — async, tidak memblok UI.
   * @returns {Promise<string>}
   */
  worker() {
    const spec = _nxDomToSpec(this._root);
    if (_nxWorkerRef && spec) return _nxWorkerRef.build(spec);
    return Promise.resolve(this._root ? this._root.outerHTML : '');
  }
}

export class NexaKit {
  constructor() {
    // Constructor kosong, semua method akan static atau instance method
  }

  // Method untuk memilih element berdasarkan ID
  id(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih element berdasarkan class (mengambil element pertama)
  class(className) {
    const element = document.getElementsByClassName(className)[0];
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih element berdasarkan class (mengambil semua elements)
  classAll(className) {
    const elements = document.getElementsByClassName(className);
    if (elements.length === 0) {
      return [];
    }
    return Array.from(elements).map((el) => this.wrapElement(el));
  }

  // Method untuk memilih element berdasarkan CSS selector
  selector(cssSelector) {
    const element = document.querySelector(cssSelector);
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih semua elements berdasarkan CSS selector
  selectorAll(cssSelector) {
    const elements = document.querySelectorAll(cssSelector);
    if (elements.length === 0) {
      return [];
    }
    return Array.from(elements).map((el) => this.wrapElement(el));
  }

  // Method untuk membuat element baru
  createElement(tagName, attributes = {}) {
    const element = document.createElement(tagName);

    // Set attributes jika ada
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === "id") {
        element.id = value;
      } else if (key === "class") {
        element.className = value;
      } else if (key.startsWith("data-")) {
        element.dataset[key.replace("data-", "")] = value;
      } else {
        element.setAttribute(key, value);
      }
    });

    return this.wrapElement(element);
  }

  // Method untuk membungkus element agar mendukung chaining
  wrapElement(element) {
    return {
      // Property innerHTML untuk get/set content
      get innerHTML() {
        return element.innerHTML;
      },
      set innerHTML(value) {
        element.innerHTML = value;
      },

      // Property textContent untuk get/set text
      get textContent() {
        return element.textContent;
      },
      set textContent(value) {
        element.textContent = value;
      },

      // Method html() sebagai alias untuk innerHTML (jQuery-like)
      html(value = null) {
        if (value === null) {
          return element.innerHTML;
        }
        element.innerHTML = value;
        return this;
      },

      // Property untuk mengakses element asli
      get element() {
        return element;
      },

      // Method untuk menambahkan class
      addClass(className) {
        element.classList.add(className);
        return this;
      },

      // Method untuk menghapus class
      removeClass(className) {
        element.classList.remove(className);
        return this;
      },

      // Method untuk toggle class
      toggleClass(className) {
        element.classList.toggle(className);
        return this;
      },

      // Method untuk set style
      setStyle(property, value) {
        element.style[property] = value;
        return this;
      },

      // Method untuk set multiple styles
      setStyles(styles = {}) {
        for (const [property, value] of Object.entries(styles)) {
          element.style[property] = value;
        }
        return this;
      },

      // Method untuk remove style property
      removeStyle(property) {
        element.style.removeProperty(property);
        return this;
      },

      // Method untuk menambahkan event listener
      // Jika jQuery tersedia, gunakan jQuery .on() agar mendukung
      // event yang di-trigger via jQuery (Select2, Bootstrap, plugin lainnya).
      // Fallback ke native addEventListener jika jQuery tidak ada.
      on(event, handler) {
        if (window.$) {
          window.$(element).on(event, handler);
        } else {
          element.addEventListener(event, handler);
        }
        return this;
      },

      // Method untuk menghapus element
      remove() {
        element.remove();
        return this;
      },

      // Method untuk show element
      show() {
        element.style.display = "";
        return this;
      },

      // Method untuk hide element
      hide() {
        element.style.display = "none";
        return this;
      },

      // ===== ATTRIBUTE MANIPULATION =====

      // Method untuk set/get attribute
      attr(name, value = null) {
        if (value === null) {
          return element.getAttribute(name);
        }
        element.setAttribute(name, value);
        return this;
      },

      // Method untuk remove attribute
      removeAttr(name) {
        element.removeAttribute(name);
        return this;
      },

      // Method untuk check apakah attribute ada
      hasAttr(name) {
        return element.hasAttribute(name);
      },

      // Method untuk set/get data attribute
      data(key, value = null) {
        if (value === null) {
          return element.dataset[key];
        }
        element.dataset[key] = value;
        return this;
      },

      // Method untuk menambahkan ID ke existing ID
      addID(additionalId) {
        const currentId = element.id;
        const newId = currentId ? `${currentId} ${additionalId}` : additionalId;
        element.id = newId;
        return this;
      },

      // ===== VALUE MANIPULATION =====

      // Method untuk set/get value (untuk input, select, textarea)
      val(value = null) {
        if (value === null) {
          return element.value || "";
        }
        element.value = value;
        return this;
      },

      // Method untuk set/get placeholder
      placeholder(text = null) {
        if (text === null) {
          return element.placeholder || "";
        }
        element.placeholder = text;
        return this;
      },

      // ===== CLASS MANIPULATION ADVANCED =====

      // Method untuk check apakah class ada
      hasClass(className) {
        return element.classList.contains(className);
      },

      // Method untuk replace class
      replaceClass(oldClass, newClass) {
        element.classList.replace(oldClass, newClass);
        return this;
      },

      // Method untuk set multiple classes sekaligus
      setClasses(classNames) {
        element.className = classNames;
        return this;
      },

      // Method untuk get all classes
      getClasses() {
        return Array.from(element.classList);
      },

      // ===== CONTENT MANIPULATION ADVANCED =====

      // Method untuk append content
      append(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("beforeend", content);
        } else {
          element.appendChild(content);
        }
        return this;
      },

      // Method untuk prepend content
      prepend(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("afterbegin", content);
        } else {
          element.insertBefore(content, element.firstChild);
        }
        return this;
      },

      // Method untuk clear content
      clear() {
        element.innerHTML = "";
        return this;
      },

      // Method untuk insert before element
      before(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("beforebegin", content);
        } else {
          element.parentNode.insertBefore(content, element);
        }
        return this;
      },

      // Method untuk insert after element
      after(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("afterend", content);
        } else {
          element.parentNode.insertBefore(content, element.nextSibling);
        }
        return this;
      },

      // Method untuk replace element dengan content baru
      replaceWith(content) {
        if (typeof content === "string") {
          element.outerHTML = content;
        } else {
          element.parentNode.replaceChild(content, element);
        }
        return this;
      },

      // Method untuk wrap element dengan wrapper
      wrap(wrapper) {
        if (typeof wrapper === "string") {
          const tempDiv = document.createElement("div");
          tempDiv.innerHTML = wrapper;
          const wrapperElement = tempDiv.firstChild;
          element.parentNode.insertBefore(wrapperElement, element);
          wrapperElement.appendChild(element);
        } else {
          element.parentNode.insertBefore(wrapper, element);
          wrapper.appendChild(element);
        }
        return this;
      },

      // Method untuk unwrap element (remove parent wrapper)
      unwrap() {
        const parent = element.parentNode;
        if (parent && parent !== document.body) {
          const grandParent = parent.parentNode;
          while (parent.firstChild) {
            grandParent.insertBefore(parent.firstChild, parent);
          }
          grandParent.removeChild(parent);
        }
        return this;
      },

      // Method untuk empty element (remove all children but keep element)
      empty() {
        while (element.firstChild) {
          element.removeChild(element.firstChild);
        }
        return this;
      },

      // Method untuk detach element (remove but keep in memory)
      detach() {
        if (element.parentNode) {
          element.parentNode.removeChild(element);
        }
        return this;
      },

      // ===== POSITIONING & DIMENSIONS =====

      // Method untuk get/set width
      width(value = null) {
        if (value === null) {
          return element.offsetWidth;
        }
        element.style.width = typeof value === "number" ? value + "px" : value;
        return this;
      },

      // Method untuk get/set height
      height(value = null) {
        if (value === null) {
          return element.offsetHeight;
        }
        element.style.height = typeof value === "number" ? value + "px" : value;
        return this;
      },

      // Method untuk get position
      position() {
        const rect = element.getBoundingClientRect();
        return {
          top: rect.top,
          left: rect.left,
          right: rect.right,
          bottom: rect.bottom,
          width: rect.width,
          height: rect.height,
        };
      },

      // ===== SCROLL MANIPULATION =====

      // Method untuk scroll to element
      scrollIntoView(options = { behavior: "smooth" }) {
        element.scrollIntoView(options);
        return this;
      },

      // Method untuk set scroll position
      scrollTo(top = 0, left = 0) {
        element.scrollTop = top;
        element.scrollLeft = left;
        return this;
      },

      // ===== FOCUS & SELECTION =====

      // Method untuk focus element
      focus() {
        element.focus();
        return this;
      },

      // Method untuk blur element
      blur() {
        element.blur();
        return this;
      },

      // Method untuk select text (untuk input/textarea)
      select() {
        if (element.select) {
          element.select();
        }
        return this;
      },

      // ===== ANIMATION & EFFECTS =====

      // Method untuk fade in
      fadeIn(duration = 300) {
        element.style.opacity = "0";
        element.style.display = "";
        element.style.transition = `opacity ${duration}ms ease`;

        setTimeout(() => {
          element.style.opacity = "1";
        }, 10);

        return this;
      },

      // Method untuk fade out
      fadeOut(duration = 300) {
        element.style.transition = `opacity ${duration}ms ease`;
        element.style.opacity = "0";

        setTimeout(() => {
          element.style.display = "none";
        }, duration);

        return this;
      },

      // Method untuk slide up
      slideUp(duration = 300) {
        element.style.transition = `height ${duration}ms ease`;
        element.style.height = element.offsetHeight + "px";
        element.style.overflow = "hidden";

        setTimeout(() => {
          element.style.height = "0";
        }, 10);

        setTimeout(() => {
          element.style.display = "none";
        }, duration);

        return this;
      },

      // Method untuk slide down
      slideDown(duration = 300) {
        element.style.display = "";
        const targetHeight = element.scrollHeight;
        element.style.height = "0";
        element.style.overflow = "hidden";
        element.style.transition = `height ${duration}ms ease`;

        setTimeout(() => {
          element.style.height = targetHeight + "px";
        }, 10);

        setTimeout(() => {
          element.style.height = "";
          element.style.overflow = "";
        }, duration);

        return this;
      },

      // ===== PARENT & CHILDREN MANIPULATION =====

      // Method untuk get parent element
      parent() {
        return element.parentElement
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              element.parentElement
            )
          : null;
      },

      // Method untuk get children elements
      children() {
        return Array.from(element.children).map((child) =>
          this.constructor.prototype.wrapElement.call(
            this.constructor.prototype,
            child
          )
        );
      },

      // Method untuk find element di dalam current element
      find(selector) {
        const found = element.querySelector(selector);
        return found
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              found
            )
          : null;
      },

      // Method untuk find all elements di dalam current element
      findAll(selector) {
        const found = element.querySelectorAll(selector);
        return Array.from(found).map((el) =>
          this.constructor.prototype.wrapElement.call(
            this.constructor.prototype,
            el
          )
        );
      },

      // ===== VALIDATION & UTILITIES =====

      // Method untuk check visibility
      isVisible() {
        return element.offsetWidth > 0 && element.offsetHeight > 0;
      },

      // Method untuk check apakah element ada di viewport
      isInViewport() {
        const rect = element.getBoundingClientRect();
        return (
          rect.top >= 0 &&
          rect.left >= 0 &&
          rect.bottom <=
            (window.innerHeight || document.documentElement.clientHeight) &&
          rect.right <=
            (window.innerWidth || document.documentElement.clientWidth)
        );
      },

      // Method untuk clone element
      clone(deep = true) {
        const cloned = element.cloneNode(deep);
        return this.constructor.prototype.wrapElement.call(
          this.constructor.prototype,
          cloned
        );
      },

      // ===== SIBLING MANIPULATION =====

      // Method untuk get next sibling
      next(selector = null) {
        let sibling = element.nextElementSibling;
        if (selector) {
          while (sibling && !sibling.matches(selector)) {
            sibling = sibling.nextElementSibling;
          }
        }
        return sibling
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              sibling
            )
          : null;
      },

      // Method untuk get previous sibling
      prev(selector = null) {
        let sibling = element.previousElementSibling;
        if (selector) {
          while (sibling && !sibling.matches(selector)) {
            sibling = sibling.previousElementSibling;
          }
        }
        return sibling
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              sibling
            )
          : null;
      },

      // Method untuk get all next siblings
      nextAll(selector = null) {
        const siblings = [];
        let sibling = element.nextElementSibling;
        while (sibling) {
          if (!selector || sibling.matches(selector)) {
            siblings.push(
              this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                sibling
              )
            );
          }
          sibling = sibling.nextElementSibling;
        }
        return siblings;
      },

      // Method untuk get all previous siblings
      prevAll(selector = null) {
        const siblings = [];
        let sibling = element.previousElementSibling;
        while (sibling) {
          if (!selector || sibling.matches(selector)) {
            siblings.unshift(
              this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                sibling
              )
            );
          }
          sibling = sibling.previousElementSibling;
        }
        return siblings;
      },

      // Method untuk get all siblings
      siblings(selector = null) {
        return [...this.prevAll(selector), ...this.nextAll(selector)];
      },

      // ===== FORM SPECIFIC METHODS =====

      // Method untuk check/uncheck checkbox/radio
      checked(value = null) {
        if (value === null) {
          return element.checked || false;
        }
        element.checked = !!value;
        return this;
      },

      // Method untuk disable/enable element
      disabled(value = null) {
        if (value === null) {
          return element.disabled || false;
        }
        element.disabled = !!value;
        return this;
      },

      // Method untuk submit form
      submit() {
        if (element.tagName.toLowerCase() === "form") {
          element.submit();
        }
        return this;
      },

      // Method untuk reset form
      reset() {
        if (element.tagName.toLowerCase() === "form") {
          element.reset();
        }
        return this;
      },

      // Method untuk serialize form data
      serialize() {
        if (element.tagName.toLowerCase() === "form") {
          const formData = new FormData(element);
          const result = {};
          for (let [key, value] of formData.entries()) {
            if (result[key]) {
              if (Array.isArray(result[key])) {
                result[key].push(value);
              } else {
                result[key] = [result[key], value];
              }
            } else {
              result[key] = value;
            }
          }
          return result;
        }
        return {};
      },

      // ===== ADDITIONAL UTILITIES =====

      // Method untuk trigger event
      trigger(eventType, eventData = {}) {
        const event = new CustomEvent(eventType, { detail: eventData });
        element.dispatchEvent(event);
        return this;
      },

      // Method untuk check if element matches selector
      is(selector) {
        return element.matches ? element.matches(selector) : false;
      },

      // Method untuk check if element has content
      hasContent() {
        return element.innerHTML.trim().length > 0;
      },

      // Method untuk get/set outer HTML
      outerHTML(value = null) {
        if (value === null) {
          return element.outerHTML;
        }
        element.outerHTML = value;
        return this;
      },

      // Method untuk get tag name
      tagName() {
        return element.tagName.toLowerCase();
      },

      // Method untuk get computed style
      css(property = null) {
        const computed = window.getComputedStyle(element);
        if (property) {
          return computed.getPropertyValue(property);
        }
        return computed;
      },

      // ===== ELEMENT INSPECTION METHODS =====

      // Method untuk get semua attributes element
      getAllAttrs() {
        const attrs = {};
        for (let i = 0; i < element.attributes.length; i++) {
          const attr = element.attributes[i];
          attrs[attr.name] = attr.value;
        }
        return attrs;
      },

      // Method untuk get semua data attributes
      getAllData() {
        const dataAttrs = {};
        for (let key in element.dataset) {
          dataAttrs[key] = element.dataset[key];
        }
        return dataAttrs;
      },

      // Method untuk get element properties (comprehensive info)
      getProperties() {
        return {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          dataAttributes: this.getAllData(),
          innerHTML: element.innerHTML,
          textContent: element.textContent,
          value: element.value || null,
          checked: element.checked || null,
          disabled: element.disabled || null,
          hidden: element.hidden || null,
          style: element.getAttribute("style") || null,
          position: this.position(),
          dimensions: {
            width: element.offsetWidth,
            height: element.offsetHeight,
            scrollWidth: element.scrollWidth,
            scrollHeight: element.scrollHeight,
          },
          visibility: {
            isVisible: this.isVisible(),
            isInViewport: this.isInViewport(),
            display: window.getComputedStyle(element).display,
            visibility: window.getComputedStyle(element).visibility,
            opacity: window.getComputedStyle(element).opacity,
          },
          hierarchy: {
            parent: element.parentElement
              ? element.parentElement.tagName.toLowerCase()
              : null,
            children: Array.from(element.children).map((child) =>
              child.tagName.toLowerCase()
            ),
            siblings: Array.from(element.parentElement?.children || [])
              .filter((sibling) => sibling !== element)
              .map((sibling) => sibling.tagName.toLowerCase()),
            nextSibling: element.nextElementSibling
              ? element.nextElementSibling.tagName.toLowerCase()
              : null,
            prevSibling: element.previousElementSibling
              ? element.previousElementSibling.tagName.toLowerCase()
              : null,
          },
        };
      },

      // Method untuk get full element info as JSON string
      inspect() {
        return JSON.stringify(this.getProperties(), null, 2);
      },

      // Method untuk print element info ke console
      debug() {
        // Debug logging disabled
        return this;
      },

      // Method untuk get attributes dengan filter
      getAttrs(filter = null) {
        const allAttrs = this.getAllAttrs();
        if (!filter) return allAttrs;

        const filtered = {};
        for (let [name, value] of Object.entries(allAttrs)) {
          if (
            name.includes(filter) ||
            (typeof filter === "function" && filter(name, value))
          ) {
            filtered[name] = value;
          }
        }
        return filtered;
      },

      // Method untuk check apakah element punya attribute tertentu
      hasAttrs(attributeNames) {
        if (typeof attributeNames === "string") {
          return element.hasAttribute(attributeNames);
        }
        if (Array.isArray(attributeNames)) {
          return attributeNames.every((attr) => element.hasAttribute(attr));
        }
        return false;
      },

      // Method untuk get list nama semua attributes
      getAttrNames() {
        return Array.from(element.attributes).map((attr) => attr.name);
      },

      // Method untuk get element structure tree
      getStructure(maxDepth = 3, currentDepth = 0) {
        if (currentDepth >= maxDepth) return null;

        return {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          children: Array.from(element.children)
            .map((child) => {
              const wrapped = this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                child
              );
              return wrapped.getStructure(maxDepth, currentDepth + 1);
            })
            .filter(Boolean),
        };
      },

      // ===== JSON HANDLING METHODS =====

      // Method untuk convert element data ke JSON string
      toJSON(includeChildren = false) {
        const data = {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          dataAttributes: this.getAllData(),
          content: {
            innerHTML: element.innerHTML,
            textContent: element.textContent,
          },
          style: element.getAttribute("style") || null,
          value: element.value || null,
        };

        if (includeChildren) {
          data.children = Array.from(element.children).map((child) => {
            const wrapped = this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              child
            );
            return wrapped.toJSON(false); // Avoid deep recursion
          });
        }

        return JSON.stringify(data, null, 2);
      },

      // Method untuk load data dari JSON string
      fromJSON(jsonString, options = {}) {
        try {
          const data = JSON.parse(jsonString);

          // Set attributes
          if (data.attributes && options.loadAttributes !== false) {
            Object.entries(data.attributes).forEach(([name, value]) => {
              if (name !== "id" && name !== "class") {
                // Skip id and class
                element.setAttribute(name, value);
              }
            });
          }

          // Set data attributes
          if (data.dataAttributes && options.loadDataAttributes !== false) {
            Object.entries(data.dataAttributes).forEach(([key, value]) => {
              element.dataset[key] = value;
            });
          }

          // Set classes
          if (data.classes && options.loadClasses !== false) {
            element.className = data.classes.join(" ");
          }

          // Set content
          if (data.content && options.loadContent !== false) {
            if (data.content.innerHTML) {
              element.innerHTML = data.content.innerHTML;
            } else if (data.content.textContent) {
              element.textContent = data.content.textContent;
            }
          }

          // Set value for form elements
          if (data.value !== null && options.loadValue !== false) {
            element.value = data.value;
          }

          // Set inline styles
          if (data.style && options.loadStyle !== false) {
            element.setAttribute("style", data.style);
          }

          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk export form data sebagai JSON
      exportFormJSON() {
        if (element.tagName.toLowerCase() === "form") {
          const formData = this.serialize();
          return JSON.stringify(formData, null, 2);
        }
        return "{}";
      },

      // Method untuk import form data dari JSON
      importFormJSON(jsonString) {
        if (element.tagName.toLowerCase() !== "form") {
          return this;
        }

        try {
          const data = JSON.parse(jsonString);

          Object.entries(data).forEach(([name, value]) => {
            const input = element.querySelector(`[name="${name}"]`);
            if (input) {
              if (input.type === "checkbox" || input.type === "radio") {
                input.checked = !!value;
              } else {
                input.value = value;
              }
            }
          });

          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk save element state ke localStorage sebagai JSON
      saveToStorage(key, options = {}) {
        const data = {
          attributes:
            options.saveAttributes !== false ? this.getAllAttrs() : {},
          dataAttributes:
            options.saveDataAttributes !== false ? this.getAllData() : {},
          classes: options.saveClasses !== false ? this.getClasses() : [],
          content:
            options.saveContent !== false
              ? {
                  innerHTML: element.innerHTML,
                  textContent: element.textContent,
                }
              : {},
          value: options.saveValue !== false ? element.value || null : null,
          style:
            options.saveStyle !== false ? element.getAttribute("style") : null,
          timestamp: Date.now(),
        };

        try {
          localStorage.setItem(key, JSON.stringify(data));
          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk load element state dari localStorage
      loadFromStorage(key, options = {}) {
        try {
          const data = JSON.parse(localStorage.getItem(key) || "{}");

          if (Object.keys(data).length === 0) {
            return this;
          }

          return this.fromJSON(JSON.stringify(data), options);
        } catch (error) {
          return this;
        }
      },

      // Method untuk convert element ke backup object (deep)
      createBackup() {
        return {
          element: {
            tag: element.tagName.toLowerCase(),
            attributes: this.getAllAttrs(),
            dataAttributes: this.getAllData(),
            classes: this.getClasses(),
            innerHTML: element.innerHTML,
            value: element.value || null,
          },
          timestamp: Date.now(),
          version: "1.0",
        };
      },

      // Method untuk restore dari backup object
      restoreFromBackup(backup) {
        try {
          if (!backup.element) {
            return this;
          }

          const data = backup.element;

          // Restore attributes
          Object.entries(data.attributes || {}).forEach(([name, value]) => {
            element.setAttribute(name, value);
          });

          // Restore data attributes
          Object.entries(data.dataAttributes || {}).forEach(([key, value]) => {
            element.dataset[key] = value;
          });

          // Restore classes
          if (data.classes) {
            element.className = data.classes.join(" ");
          }

          // Restore content
          if (data.innerHTML) {
            element.innerHTML = data.innerHTML;
          }

          // Restore value
          if (data.value !== null) {
            element.value = data.value;
          }

          return this;
        } catch (error) {
          return this;
        }
      },
    };
  }

  // ===== ELEMENT FACTORY METHODS =====
  // Membuat elemen HTML baru dengan fluent/chainable builder API.
  // Usage: NXUI.div().id('tes').class('foo').style({ color: 'red' }).html('Hello')
  //        → '<div id="tes" class="foo" style="color: red;">Hello</div>'

  /** Buat elemen baru berdasarkan tag name — kembalikan NexaElementBuilder */
  tag(tagName, c) {
    const b = new NexaElementBuilder(tagName);
    if (!c) return b;
    if (c === 'c' || _NX_LAYOUT_TAGS.has(tagName)) {
      if (c !== 'c') b._el.className = c;
      return b.container();
    }
    b._el.innerHTML = c;
    return b;
  }

  // Shorthand untuk tag HTML umum
  // Semua menerima optional `c` (truthy) untuk langsung masuk container mode:
  //   NXUI.div('container') atau NXUI.div(true)  →  NexaContainerBuilder
  //   NXUI.div()                                 →  NexaElementBuilder (seperti biasa)
  div(c)       { return this.tag('div', c); }
  span(c)      { return this.tag('span', c); }
  p(c)         { return this.tag('p', c); }
  a(href)      { const b = this.tag('a'); return href != null ? b.attr('href', href) : b; }
  button(c)    { return this.tag('button', c); }
  /** Shortcut button dengan click handler — setara `.button().onclick(handler)`. */
  btn(handler) { return this.tag('button').onclick(handler); }
  input(c)     { return this.tag('input', c); }
  select(c)    { return this.tag('select', c); }
  textarea(c)  { return this.tag('textarea', c); }
  fieldset(c)  { return this.tag('fieldset', c); }
  legend(c)    { return this.tag('legend', c); }
  dl(c)        { return this.tag('dl', c); }
  dt(c)        { return this.tag('dt', c); }
  dd(c)        { return this.tag('dd', c); }
  ul(c)        { return this.tag('ul', c); }
  ol(c)        { return this.tag('ol', c); }
  li(c)        { return this.tag('li', c); }
  h1(c)        { return this.tag('h1', c); }
  h2(c)        { return this.tag('h2', c); }
  h3(c)        { return this.tag('h3', c); }
  h4(c)        { return this.tag('h4', c); }
  h5(c)        { return this.tag('h5', c); }
  h6(c)        { return this.tag('h6', c); }
  /** @param {string} [src] — jika diisi, set atribut src otomatis */
  img(src)     { const b = this.tag('img'); return src != null ? b.attr('src', src) : b; }
  form(c)      { return this.tag('form', c); }
  label(c)     { return this.tag('label', c); }
  section(c)   { return this.tag('section', c); }
  article(c)   { return this.tag('article', c); }
  header(c)    { return this.tag('header', c); }
  footer(c)    { return this.tag('footer', c); }
  nav(c)       { return this.tag('nav', c); }
  main(c)      { return this.tag('main', c); }
  aside(c)     { return this.tag('aside', c); }
  table(c)     { return this.tag('table', c); }
  thead(c)     { return this.tag('thead', c); }
  tbody(c)     { return this.tag('tbody', c); }
  tr(c)        { return this.tag('tr', c); }
  td(c)        { return this.tag('td', c); }
  th(c)        { return this.tag('th', c); }
  strong(c)    { return this.tag('strong', c); }
  em(c)        { return this.tag('em', c); }
  small(c)     { return this.tag('small', c); }
  code(c)      { return this.tag('code', c); }
  pre(c)       { return this.tag('pre', c); }
  blockquote(c){ return this.tag('blockquote', c); }
  /** Buat elemen <i> — opsional class icon — chainable */
  icon(classes)   { const b = this.tag('i'); return classes ? b.class(classes) : b; }

  /** Eksekusi fn(this) jika condition truthy, tetap di chain jika false. */
  when(condition, fn) { return condition ? (fn(this) ?? this) : this; }

  // ── Form shortcuts ────────────────────────────────────────────────────────
  /**
   * Buat div.form-group — opsional tambah state class.
   * @param {string} [state] — 'required'|'errored'|'warn'|'successful'|'loading'|'flattened'
   * @example
   *   NXUI.formGroup('required')  → <div class="form-group required">
   */
  formGroup(state)   { const b = this.tag('div','c').class('form-group'); return state ? b.addClass(state) : b; }
  /** div.input-group — opsional class 'inline' */
  inputGroup(inline) { const b = this.tag('div','c').class('input-group'); return inline ? b.addClass('inline') : b; }
  /** div.input-group-button */
  inputGroupBtn()    { return this.tag('div','c').class('input-group-button'); }
  /** div.hfields (horizontal fields) */
  hfields()          { return this.tag('div','c').class('hfields'); }
  /** div.form-checkbox */
  formCheckbox()     { return this.tag('div','c').class('form-checkbox'); }
  /** div.radio-group */
  radioGroup()       { return this.tag('div','c').class('radio-group'); }
  /** div.form-actions */
  formActions()      { return this.tag('div','c').class('form-actions'); }
  /** div.form-warning */
  formWarning()      { return this.tag('div','c').class('form-warning'); }
  /**
   * input.form-control — chainable.
   * @param {string} [type='text']
   * @example
   *   NXUI.formControl('email').attr('placeholder','email@...')
   */
  formControl(type)  { return this.tag('input').class('form-control').attr('type', type || 'text'); }
  /** select.form-select — chainable container */
  formSelect()       { return this.tag('select','c').class('form-select'); }
  /** p.note (helper text) */
  note()             { return this.tag('p').class('note'); }

  // Method untuk membuat dummy element jika element tidak ditemukan
  createDummyElement() {
    const dummy = () => this.createDummyElement();
    return {
      // Basic properties
      get innerHTML() {
        return "";
      },
      set innerHTML(value) {
        // Silent failure
      },
      get textContent() {
        return "";
      },
      set textContent(value) {
        // Silent failure
      },
      html: (value) => (value === null ? "" : dummy()),
      get element() {
        return null;
      },

      // Class manipulation
      addClass: dummy,
      removeClass: dummy,
      toggleClass: dummy,
      hasClass: () => false,
      replaceClass: dummy,
      setClasses: dummy,
      getClasses: () => [],

      // Style manipulation
      setStyle: dummy,
      setStyles: dummy,
      removeStyle: dummy,

      // Event handling
      on: dummy,

      // Visibility
      show: dummy,
      hide: dummy,
      remove: dummy,

      // Attributes
      attr: (name, value) => (value === null ? "" : dummy()),
      removeAttr: dummy,
      hasAttr: () => false,
      data: (key, value) => (value === null ? "" : dummy()),
      addID: dummy,

      // Fluent builder methods (id/class/style on wrapped elements)
      id: (value) => (value === null || value === undefined ? null : dummy()),
      class: (value) => (value === null || value === undefined ? '' : dummy()),
      style: dummy,

      // Values
      val: (value) => (value === null ? "" : dummy()),
      placeholder: (text) => (text === null ? "" : dummy()),

      // Content manipulation
      append: dummy,
      prepend: dummy,
      clear: dummy,
      before: dummy,
      after: dummy,
      replaceWith: dummy,
      wrap: dummy,
      unwrap: dummy,
      empty: dummy,
      detach: dummy,

      // Dimensions
      width: (value) => (value === null ? 0 : dummy()),
      height: (value) => (value === null ? 0 : dummy()),
      position: () => ({
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        width: 0,
        height: 0,
      }),

      // Scroll
      scrollIntoView: dummy,
      scrollTo: dummy,

      // Focus
      focus: dummy,
      blur: dummy,
      select: dummy,

      // Animation
      fadeIn: dummy,
      fadeOut: dummy,
      slideUp: dummy,
      slideDown: dummy,

      // Traversal
      parent: () => null,
      children: () => [],
      find: () => null,
      findAll: () => [],

      // Utilities
      isVisible: () => false,
      isInViewport: () => false,
      clone: dummy,

      // Sibling manipulation
      next: () => null,
      prev: () => null,
      nextAll: () => [],
      prevAll: () => [],
      siblings: () => [],

      // Form methods
      checked: (value) => (value === null ? false : dummy()),
      disabled: (value) => (value === null ? false : dummy()),
      submit: dummy,
      reset: dummy,
      serialize: () => ({}),

      // Additional utilities
      trigger: dummy,
      is: () => false,
      hasContent: () => false,
      outerHTML: (value) => (value === null ? "" : dummy()),
      tagName: () => "",
      css: () => "",

      // Element inspection
      getAllAttrs: () => ({}),
      getAllData: () => ({}),
      getProperties: () => ({
        tag: "",
        id: null,
        classes: [],
        attributes: {},
        dataAttributes: {},
        innerHTML: "",
        textContent: "",
        value: null,
        checked: null,
        disabled: null,
        hidden: null,
        style: null,
        position: { top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0 },
        dimensions: { width: 0, height: 0, scrollWidth: 0, scrollHeight: 0 },
        visibility: {
          isVisible: false,
          isInViewport: false,
          display: "none",
          visibility: "hidden",
          opacity: "0",
        },
        hierarchy: {
          parent: null,
          children: [],
          siblings: [],
          nextSibling: null,
          prevSibling: null,
        },
      }),
      inspect: () => "{}",
      debug: dummy,
      getAttrs: () => ({}),
      hasAttrs: () => false,
      getAttrNames: () => [],
      getStructure: () => ({
        tag: "",
        id: null,
        classes: [],
        attributes: {},
        children: [],
      }),
    };
  }
}
