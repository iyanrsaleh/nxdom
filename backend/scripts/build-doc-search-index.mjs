/**
 * Regenerates Search.php $items from theme/docs files + themeDev/workspace/docs menus.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const docsRoot = path.join(root, 'templates/theme/docs');
const themeDevDocs = path.join(root, 'templates/themeDev/workspace/docs');

const NEXA_PREFIX = /^nexa-/;
const SPECIAL_JS_SLUG = new Set([
  'index', 'entry', 'ringkasan-alur', 'inisiasi-app-js', 'menjalankan-development',
  'route-modul-meta', 'filetype', 'exampel', 'file-terkait', 'checklist-cepat',
  'indexddb', 'storage', 'nxui-dom', 'nexakit',
]);

function jsSlugToModule(slug) {
  if (slug === 'javascript' || slug === '') return '';
  if (slug === 'nexakit') return 'kit';
  if (slug === 'nexa-svg') return 'svg';
  if (NEXA_PREFIX.test(slug)) return slug.replace(NEXA_PREFIX, '');
  return slug;
}

function normalizeHref(raw) {
  let href = raw.trim();
  if (!href || href.startsWith('#') || href.startsWith('javascript:')) return null;

  href = href.replace(/^\/workspace\/docs\//, '/docs/');

  // Legacy workspace paths without /workspace
  if (href.startsWith('/docs/helper')) {
    href = href.replace(/^\/docs\/helper/, '/docs/backend/helper');
  } else if (href.match(/^\/docs\/(storages|dashboard|api)(\/|$)/)) {
    href = href.replace(/^\/docs\/(storages|dashboard|api)/, '/docs/backend/$1');
  } else if (href === '/docs/getting-started') {
    href = '/docs/backend/getting-started';
  } else if (href === '/docs/cli' || href === '/docs/routing' || href === '/docs/models' ||
    href === '/docs/storages' || href === '/docs/events' || href === '/docs/helper' ||
    href === '/docs/api' || href === '/docs/dashboard' || href === '/docs/forms') {
    const map = {
      '/docs/cli': '/docs/backend/cli',
      '/docs/routing': '/docs/backend/routing',
      '/docs/models': '/docs/backend/models',
      '/docs/storages': '/docs/backend/storages',
      '/docs/events': '/docs/backend/events',
      '/docs/helper': '/docs/backend/helper',
      '/docs/api': '/docs/backend/api',
      '/docs/dashboard': '/docs/backend/dashboard',
      '/docs/forms': '/docs/backend/helper/forms',
      '/docs/getting-started': '/docs/backend/getting-started',
    };
    href = map[href] || href;
  } else if (href.startsWith('/docs/forms/')) {
    href = href.replace(/^\/docs\/forms/, '/docs/backend/helper/forms');
  }

  // NexaJS → NXDOM modules
  if (href === '/docs/platform/javascript' || href === '/docs/platform/javascript/') {
    href = '/docs/modules';
  } else if (href.startsWith('/docs/platform/javascript/')) {
    const rest = href.slice('/docs/platform/javascript/'.length);
    const hashIdx = rest.indexOf('#');
    const slugPart = hashIdx >= 0 ? rest.slice(0, hashIdx) : rest;
    const hash = hashIdx >= 0 ? rest.slice(hashIdx) : '';
    const moduleSlug = jsSlugToModule(slugPart.replace(/\/$/, ''));
    href = `/docs/modules/${moduleSlug}${hash}`;
  }

  // Node public URL → nodejs folder
  if (href === '/docs/platform/node' || href === '/docs/platform/node/') {
    href = '/docs/platform/node';
  } else if (href.startsWith('/docs/platform/node/')) {
    // keep as-is (routing alias)
  } else if (href === '/docs/platform/nodejs' || href.startsWith('/docs/platform/nodejs/')) {
    href = href.replace('/docs/platform/nodejs', '/docs/platform/node');
  }

  if (href.includes('menu.html') || href.includes('menu-no-id')) return null;
  return href;
}

function hrefToTemplatePath(hrefBase) {
  const aliases = {
    '/docs/platform/node': 'platform/nodejs/index.html',
    '/docs/development/cli': 'development/cli.html',
    '/docs/development': null,
    '/docs/platform/javascript': 'modules/index.html',
    '/docs/modules': 'modules/index.html',
  };
  if (aliases[hrefBase] !== undefined) {
    return aliases[hrefBase];
  }
  if (hrefBase.startsWith('/docs/platform/node/')) {
    const slug = hrefBase.slice('/docs/platform/node/'.length);
    return `platform/nodejs/${slug}.html`;
  }
  const rel = hrefBase.replace(/^\/docs\//, '') + '.html';
  const full = path.join(docsRoot, rel);
  if (fs.existsSync(full)) return rel.replace(/\\/g, '/');
  return null;
}

function stripHtml(html) {
  return html
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function loadIds(fileRel) {
  if (!fileRel) return new Set();
  const full = path.join(docsRoot, fileRel);
  if (!fs.existsSync(full)) return new Set();
  const html = fs.readFileSync(full, 'utf8');
  const ids = new Set();
  for (const m of html.matchAll(/\bid=["']([^"']+)["']/g)) ids.add(m[1]);
  return ids;
}

function pageMeta(fileRel) {
  const full = path.join(docsRoot, fileRel);
  const html = fs.readFileSync(full, 'utf8');
  const h1 = html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
  const muted = html.match(/<p[^>]*class="[^"]*color-fg-muted[^"]*"[^>]*>([\s\S]*?)<\/p>/i);
  const title = h1 ? stripHtml(h1[1]) : path.basename(fileRel, '.html');
  const description = muted ? stripHtml(muted[1]).slice(0, 160) : `Dokumentasi ${title}`;
  return { title, description };
}

function iconForHref(hrefBase, groupHint) {
  if (hrefBase.includes('/platform/react')) return '/assets/images/react.svg';
  if (hrefBase.includes('/platform/electron')) return '/assets/images/electron.svg';
  if (hrefBase.includes('/platform/node')) return '/assets/images/nodejs.svg';
  if (hrefBase.includes('/modules')) return 'fa-brands fa-js';
  if (groupHint?.includes('React')) return '/assets/images/react.svg';
  if (groupHint?.includes('Electron')) return '/assets/images/electron.svg';
  if (groupHint?.includes('Node')) return '/assets/images/nodejs.svg';
  if (groupHint?.includes('NexaJS') || groupHint?.includes('NXDOM')) {
    return 'fa-brands fa-js';
  }
  if (hrefBase.includes('/api')) return 'fa-solid fa-plug';
  if (hrefBase.includes('/storages')) return 'fa-solid fa-database';
  if (hrefBase.includes('/dashboard')) return 'fa-solid fa-gauge-high';
  if (hrefBase.includes('/helper/forms')) return 'fa-solid fa-wpforms';
  if (hrefBase.includes('/helper')) return 'fa-solid fa-life-ring';
  if (hrefBase.includes('/cli') || groupHint?.includes('Development')) return 'fa-solid fa-terminal';
  if (hrefBase.includes('/platform')) return 'fa-solid fa-layer-group';
  if (hrefBase.includes('/backend')) return 'fa-solid fa-book-open';
  return 'fa-solid fa-file-lines';
}

function extractLinksFromHtml(html) {
  const links = [];
  const re = /<a\s+[^>]*href=["']([^"'#][^"']*)["'][^>]*>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    links.push({ href: m[1], text: stripHtml(m[2]) });
  }
  return links;
}

function walkDir(dir, files = []) {
  if (!fs.existsSync(dir)) return files;
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    const st = fs.statSync(p);
    if (st.isDirectory()) walkDir(p, files);
    else files.push(p);
  }
  return files;
}

function collectMenuFiles(dir) {
  return walkDir(dir).filter((p) => {
    const base = path.basename(p);
    return base === 'menu.html' || base === 'menu-no-id.html';
  });
}

const groupByMenuFile = new Map();

for (const menuPath of [
  ...collectMenuFiles(themeDevDocs),
  ...collectMenuFiles(docsRoot),
]) {
  const html = fs.readFileSync(menuPath, 'utf8');
  let group = 'Dokumentasi';
  const titleMatch = html.match(/nx-storage-menu-title[^>]*>([^<]+)</) ||
    html.match(/text-uppercase[^>]*>([^<]+)</);
  if (titleMatch) group = stripHtml(titleMatch[1]);

  const rel = path.relative(
    menuPath.includes('themeDev') ? themeDevDocs : docsRoot,
    path.dirname(menuPath),
  ).replace(/\\/g, '/');

  if (rel === 'platform/javascript') group = 'NXDOM — modul native';
  if (rel === 'development') group = 'Development — CLI';
  if (rel.startsWith('helper')) group = 'Helper — topik';
  if (rel === 'storages') group = 'Storages — bab';
  if (rel === 'dashboard') group = 'Dashboard — modul';
  if (rel.startsWith('platform/react')) group = 'React Native';
  if (rel.startsWith('platform/electron')) group = 'Electron — desktop';
  if (rel.startsWith('platform/nodejs') || rel === 'platform/node') group = 'Nexa Node — web';
  if (rel === 'platform') group = 'Platform';

  for (const { href: rawHref, text } of extractLinksFromHtml(html)) {
    const href = normalizeHref(rawHref);
    if (!href) continue;
    const base = href.split('#')[0];
    const key = `${href}\0${group}`;
    if (!groupByMenuFile.has(key)) {
      groupByMenuFile.set(key, { group, href, title: text || base, fromMenu: true });
    }
  }
}

// All doc pages (no menu.html)
const pageEntries = new Map();
for (const entryPath of walkDir(docsRoot)) {
  const entry = { path: entryPath, name: path.basename(entryPath) };
  if (!entry.name.endsWith('.html')) continue;
  if (entry.name === 'menu.html' || entry.name === 'menu-no-id.html') continue;
  const rel = path.relative(docsRoot, entryPath).replace(/\\/g, '/');
  let hrefBase = '/docs/' + rel.replace(/\.html$/, '').replace(/\/index$/, '');
  if (hrefBase.endsWith('/index')) hrefBase = hrefBase.slice(0, -6) || '/docs';
  // nodejs folder → public /docs/platform/node
  if (hrefBase === '/docs/platform/nodejs') hrefBase = '/docs/platform/node';
  else if (hrefBase.startsWith('/docs/platform/nodejs/')) {
    hrefBase = hrefBase.replace('/docs/platform/nodejs/', '/docs/platform/node/');
  }
  const { title, description } = pageMeta(rel);
  let group = 'Dokumentasi';
  if (hrefBase.includes('/backend/helper/forms')) group = 'Forms';
  else if (hrefBase.includes('/backend/helper')) group = 'Helper — topik';
  else if (hrefBase.includes('/backend/storages')) group = 'Storages — bab';
  else if (hrefBase.includes('/backend/dashboard')) group = 'Dashboard — modul';
  else if (hrefBase.includes('/backend/api')) group = 'API (JSON)';
  else if (hrefBase.includes('/backend')) group = 'Backend NexaDom';
  else if (hrefBase.includes('/modules')) group = 'NXDOM — modul native';
  else if (hrefBase.includes('/platform/react')) group = 'React Native';
  else if (hrefBase.includes('/platform/electron')) group = 'Electron — desktop';
  else if (hrefBase.includes('/platform/node')) group = 'Nexa Node — web';
  else if (hrefBase.includes('/platform')) group = 'Platform';

  pageEntries.set(hrefBase, { group, href: hrefBase, title, description, fromPage: true });
}

const merged = new Map();

function addEntry(entry) {
  const href = entry.href;
  const base = href.split('#')[0];
  const anchor = href.includes('#') ? href.split('#')[1] : '';

  const tpl = hrefToTemplatePath(base);
  if (tpl === null && base.startsWith('/docs/development')) {
    // Keep menu link for CLI install page even if HTML not deployed yet
    if (base !== '/docs/development/cli') return;
  } else if (tpl === null && !pageEntries.has(base)) {
    return;
  }

  if (anchor) {
    const ids = loadIds(tpl || (pageEntries.has(base) ? hrefToTemplatePath(base) : null));
    if (ids.size && !ids.has(anchor)) return;
  }

  const key = href;
  const existing = merged.get(key);
  const page = pageEntries.get(base);

  let title = entry.title;
  let description = entry.description || (page?.description ?? `Dokumentasi: ${title}`);
  if (page && !anchor && entry.fromPage) {
    title = page.title;
    description = page.description;
  } else if (page && anchor) {
    description = page.description ? `${page.title} — ${title}` : title;
  } else if (page && (!entry.fromMenu || description === 'Navigasi dokumentasi')) {
    if (!anchor) title = page.title;
    description = page.description;
  }
  if (existing) {
    if (description === 'Navigasi dokumentasi' && existing.description !== 'Navigasi dokumentasi') {
      return;
    }
    if (existing.description === 'Navigasi dokumentasi' && description !== 'Navigasi dokumentasi') {
      merged.delete(key);
    } else {
      return;
    }
  }

  const icon = iconForHref(base, entry.group);
  merged.set(key, { group: entry.group, icon, href, title, description });
}

for (const e of groupByMenuFile.values()) addEntry({ ...e, description: `Navigasi dokumentasi` });
for (const e of pageEntries.values()) addEntry(e);

// Sidebar core backend pages
const core = [
  ['/docs/', 'Pengenalan', 'Dokumentasi inti'],
  ['/docs/backend/getting-started', 'Memulai', 'Dokumentasi inti'],
  ['/docs/backend/cli', 'CLI Nxdom', 'Dokumentasi inti'],
  ['/docs/backend/routing', 'Routing', 'Dokumentasi inti'],
  ['/docs/backend/api', 'API', 'Dokumentasi inti'],
  ['/docs/backend/web', 'Web Applications', 'Dokumentasi inti'],
  ['/docs/backend/helper', 'Helper', 'Referensi'],
  ['/docs/backend/dashboard', 'Dashboard', 'Referensi'],
  ['/docs/backend/events', 'Events & injection', 'Dokumentasi inti'],
  ['/docs/backend/models', 'Models', 'Dokumentasi inti'],
  ['/docs/backend/storages', 'Storages', 'Dokumentasi inti'],
];
for (const [href, title, group] of core) {
  addEntry({ group, href, title, description: 'Dokumentasi Nexa Dom', fromPage: true });
}

const sorted = [...merged.values()].sort((a, b) => {
  const g = a.group.localeCompare(b.group);
  if (g !== 0) return g;
  return a.href.localeCompare(b.href);
});

function phpEscape(s) {
  return s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

const lines = sorted.map((row) =>
  `                ['group' => '${phpEscape(row.group)}', 'icon' => '${phpEscape(row.icon)}', 'href' => '${phpEscape(row.href)}', 'title' => '${phpEscape(row.title)}', 'description' => '${phpEscape(row.description)}'],`,
);

const outPath = path.join(root, 'scripts', 'doc-search-items.fragment.php');
fs.writeFileSync(
  outPath,
  `// Generated ${new Date().toISOString()} — ${sorted.length} entries\n` + lines.join('\n') + '\n',
  'utf8',
);
console.log(`Wrote ${sorted.length} entries to ${outPath}`);
