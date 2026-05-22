#!/usr/bin/env node
/**
 * NexaKit HTML → Fluent API Converter
 * Usage : node NexaKit_convert.js <input.html> [output.js]
 * Output: kit_<name>.js  (same folder as input, unless output arg provided)
 */

'use strict';
const fs   = require('fs');
const path = require('path');

// ── Constants ─────────────────────────────────────────────────────────────────
const VOID_TAGS = new Set([
  'area','base','br','col','embed','hr','img','input',
  'link','meta','param','source','track','wbr',
]);

// Tags that NexaKit handles with a dedicated shortcut (special tagCall)
const SKIP_SRC    = new Set(['img']);
const SKIP_HREF   = new Set(['a']);
const SKIP_ONCLICK_AS_BTN = new Set(['button']);

// ── Tokenizer ─────────────────────────────────────────────────────────────────
function tokenize(html) {
  const tokens = [];
  // Matches: HTML comments | tags | text
  const re = /<!--[\s\S]*?-->|<(\/)?([a-zA-Z][a-zA-Z0-9-]*)(\s(?:[^>"']|"[^"]*"|'[^']*')*)?\s*(\/?)>|([^<]+)/g;
  let m;
  while ((m = re.exec(html)) !== null) {
    if (m[0].startsWith('<!--')) continue;
    if (m[2]) {
      const isClose     = !!m[1];
      const isSelfClose = m[4] === '/' || VOID_TAGS.has(m[2].toLowerCase());
      tokens.push({
        type  : isClose ? 'close' : (isSelfClose ? 'self' : 'open'),
        tag   : m[2].toLowerCase(),
        attrs : isClose ? null : parseAttrs(m[3] || ''),
      });
    } else if (m[5]) {
      const text = m[5].replace(/\s+/g, ' ').trim();
      if (text) tokens.push({ type: 'text', text });
    }
  }
  return tokens;
}

// ── Attribute Parser ──────────────────────────────────────────────────────────
function parseAttrs(str) {
  const attrs = {};
  const re = /([^\s=/"'<>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'`=<>]*)))?/g;
  let m;
  while ((m = re.exec(str)) !== null) {
    const key = m[1].toLowerCase();
    const val = m[2] !== undefined ? m[2]
              : m[3] !== undefined ? m[3]
              : m[4] !== undefined ? m[4]
              : '';
    attrs[key] = val;
  }
  return attrs;
}

// ── Style Parser ──────────────────────────────────────────────────────────────
function parseStyle(str) {
  const s = {};
  if (!str) return s;
  str.split(';').forEach(rule => {
    const colon = rule.indexOf(':');
    if (colon === -1) return;
    const prop = rule.slice(0, colon).trim();
    const val  = rule.slice(colon + 1).trim();
    if (!prop || !val) return;
    const camel = prop.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
    s[camel] = val;
  });
  return s;
}

// ── AST Builder ───────────────────────────────────────────────────────────────
function buildAST(tokens) {
  const root  = { tag: '__root__', children: [] };
  const stack = [root];
  for (const tok of tokens) {
    const parent = stack[stack.length - 1];
    if (tok.type === 'text') {
      parent.children.push({ type: 'text', text: tok.text });
    } else if (tok.type === 'open') {
      const node = { type: 'element', tag: tok.tag, attrs: tok.attrs, children: [] };
      parent.children.push(node);
      stack.push(node);
    } else if (tok.type === 'self') {
      parent.children.push({ type: 'element', tag: tok.tag, attrs: tok.attrs, children: [], isVoid: true });
    } else if (tok.type === 'close') {
      for (let i = stack.length - 1; i > 0; i--) {
        if (stack[i].tag === tok.tag) { stack.length = i; break; }
      }
    }
  }
  return root;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Get sole text content of a node, or null if it has element children */
function soleText(node) {
  if (node.isVoid || node.children.length === 0) return '';
  if (node.children.length === 1 && node.children[0].type === 'text') {
    return node.children[0].text;
  }
  return null;
}

/**
 * Quote a string for JS output.
 * Uses double quotes when value contains single quotes (e.g. onclick="fn('x')")
 */
function quote(str) {
  if (str.includes("'")) return `"${str.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
  return `'${str.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/** Normalize style unit: strip 'px' suffix so NexaKit can re-add it */
function normUnit(val) {
  return /^\d+(\.\d+)?px$/i.test(val.trim()) ? val.trim().replace(/px$/i, '') : val.trim();
}

// ── Attribute Chain Generator ─────────────────────────────────────────────────
function genAttrCode(attrs, tag, style) {
  let code = '';
  const skipKeys = new Set(['id','class','style','href','src','onclick']);

  // .id()
  if (attrs.id) code += `.id(${quote(attrs.id)})`;

  // .class()  — not for <i> (class absorbed into .icon())
  if (attrs.class && tag !== 'i') code += `.class(${quote(attrs.class)})`;

  // .onclick()  — not for <button> (absorbed into .btn())
  if (attrs.onclick && !SKIP_ONCLICK_AS_BTN.has(tag)) {
    code += `.onclick(${quote(attrs.onclick)})`;
  }

  // data-* → .data(key, val)
  for (const [k, v] of Object.entries(attrs)) {
    if (k.startsWith('data-')) code += `.data(${quote(k.slice(5))}, ${quote(v)})`;
  }

  // Remaining attrs via .attr()
  for (const [k, v] of Object.entries(attrs)) {
    if (skipKeys.has(k) || k.startsWith('data-')) continue;
    if (SKIP_SRC.has(tag)  && k === 'src')     continue;
    if (SKIP_HREF.has(tag) && k === 'href')    continue;
    code += `.attr(${quote(k)}, ${quote(v)})`;
  }

  // ── Style shortcuts ──────────────────────────────────────────────────────
  if (style.color)    code += `.color(${quote(style.color)})`;
  if (style.fontSize) code += `.fs(${quote(style.fontSize)})`;

  if (style.width && style.height) {
    const w = normUnit(style.width);
    const h = normUnit(style.height);
    code += `.wh(${quote(w + 'x' + h)})`;
  } else {
    // Remaining style props not covered by shortcuts → .style({})
    const rest = Object.entries(style).filter(([k]) =>
      !['color','fontSize','width','height'].includes(k)
    );
    if (rest.length > 0) {
      const obj = rest.map(([k, v]) => `${k}: ${quote(v)}`).join(', ');
      code += `.style({${obj}})`;
    }
  }

  return code;
}

// ── Node Code Generator ───────────────────────────────────────────────────────
/**
 * @param {object}  node        AST node
 * @param {number}  depth       indent depth (1 = 2 spaces)
 * @param {boolean} isRootCall  true = prefix with NXUI. , false = prefix with .
 * @returns {string|null}
 */
function genNode(node, depth, isRootCall) {
  if (node.type === 'text') return null;

  const tag   = node.tag;
  const attrs = node.attrs || {};
  const style = parseStyle(attrs.style || '');
  const text  = soleText(node);
  const hasElemChildren = node.children.some(c => c.type === 'element');
  const isContainer     = hasElemChildren && !node.isVoid;

  const pad  = '  '.repeat(depth);
  const pfx  = isRootCall ? 'NXUI.' : '.';

  // ── Special: <i> → .icon() ────────────────────────────────────────────────
  if (tag === 'i') {
    const cls     = attrs.class || '';
    const tagCall = `${pfx}icon(${quote(cls)})`;
    const aCode   = genAttrCode({ ...attrs, class: undefined, style: undefined }, tag, style);
    return `${pad}${tagCall}${aCode}`;
  }

  // ── Build tagCall ──────────────────────────────────────────────────────────
  let tagCall;
  // For containers: absorb class as arg instead of .div('c').class('row')
  const absorbClass = isContainer && !!attrs.class;
  if (tag === 'button' && attrs.onclick) {
    tagCall = `${pfx}btn(${quote(attrs.onclick)})`;
  } else if (tag === 'a' && attrs.href) {
    tagCall = `${pfx}a(${quote(attrs.href)})`;
  } else if (tag === 'img') {
    tagCall = `${pfx}img(${quote(attrs.src || '')})`;
  } else if (absorbClass) {
    // .div('row') instead of .div('c').class('row')
    tagCall = `${pfx}${tag}(${quote(attrs.class)})`;
  } else {
    tagCall = isContainer ? `${pfx}${tag}('c')` : `${pfx}${tag}()`;
  }

  // ── Attribute chain (skip class if already absorbed as arg) ───────────────
  const attrsForCode = absorbClass ? { ...attrs, class: '' } : attrs;
  const attrStr = genAttrCode(attrsForCode, tag, style);

  // ── Leaf: text only or void ────────────────────────────────────────────────
  if (!isContainer) {
    const isSpecial = tag === 'button' || tag === 'a' || tag === 'img';
    if (text && !node.isVoid && !isSpecial) {
      // .h1('Hello World') instead of .h1().view('Hello World')
      tagCall = `${pfx}${tag}(${quote(text)})`;
      return `${pad}${tagCall}${attrStr}`;
    }
    const view = (text && !node.isVoid) ? `.view(${quote(text)})` : '';
    return `${pad}${tagCall}${attrStr}${view}`;
  }

  // ── Container: recurse into children ──────────────────────────────────────
  const lines = [`${pad}${tagCall}${attrStr}`];
  for (const child of node.children) {
    if (child.type !== 'element') continue;
    const childCode = genNode(child, depth + 1, false);
    if (childCode != null) lines.push(childCode);
  }
  if (!isRootCall) lines.push(`${pad}.end()`);

  return lines.join('\n');
}

// ── Main converter ────────────────────────────────────────────────────────────
function convert(htmlContent, inputName) {
  // Use <body> content if present, otherwise full input
  const bodyMatch = htmlContent.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
  const content   = bodyMatch ? bodyMatch[1] : htmlContent;

  const tokens = tokenize(content);
  const ast    = buildAST(tokens);
  const kids   = ast.children.filter(c => c.type === 'element');

  if (kids.length === 0) {
    console.error('No HTML elements found in input.');
    process.exit(1);
  }

  const header = [
    `// Generated by NexaKit Converter`,
    `// Source : ${inputName}`,
    `// Date   : ${new Date().toISOString().slice(0, 10)}`,
    ``,
  ];

  let body;
  if (kids.length === 1) {
    body = `const html =\n${genNode(kids[0], 1, true)};`;
  } else {
    body = kids.map((k, i) => {
      const varName = i === 0 ? 'html' : `html${i + 1}`;
      return `const ${varName} =\n${genNode(k, 1, true)};`;
    }).join('\n\n');
  }

  return [...header, body].join('\n');
}

// ── Entry point ───────────────────────────────────────────────────────────────
const args      = process.argv.slice(2);
const inputArg  = args[0];
const outputArg = args[1];

if (!inputArg) {
  console.error('Usage: node NexaKit_convert.js <input.html> [output.js]');
  console.error('       NexaKit.bat index.html');
  process.exit(1);
}

const inputPath  = path.resolve(inputArg);
const baseName   = path.basename(inputPath, path.extname(inputPath));
const outputPath = outputArg
  ? path.resolve(outputArg)
  : path.join(path.dirname(inputPath), `kit_${baseName}.js`);

if (!fs.existsSync(inputPath)) {
  console.error(`[ERROR] File not found: ${inputPath}`);
  process.exit(1);
}

const htmlContent = fs.readFileSync(inputPath, 'utf8');
const result      = convert(htmlContent, path.basename(inputPath));

fs.writeFileSync(outputPath, result, 'utf8');
console.log(`\u2713  ${path.basename(inputPath)}  \u2192  ${path.basename(outputPath)}`);
