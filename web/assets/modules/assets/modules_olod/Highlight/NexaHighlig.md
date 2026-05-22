# NexaHighlig - Syntax Highlighting API

## Daftar Isi

- [Instalasi](#instalasi)
- [Quick Start](#quick-start)
- [API Reference](#api-reference)
- [Contoh Penggunaan](#contoh-penggunaan)
- [Tema Tersedia](#tema-tersedia)

---

## Instalasi

### 1. Import Class

```js
import NexaHighlig from "./assets/modules/Highlight/NexaHighlig.js";
```

### 2. Load CSS

```html
<link rel="stylesheet" href="./assets/modules/Highlight/nexaHighlig.css" />
```

### 3. Load Highlight.js (dari CDN)

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
```

---

## Quick Start

### Setup Dasar

```js
// Inisialisasi dengan config default
const highlighter = new NexaHighlig({
  theme: "atom-one-dark",
  lineNumbers: true,
  copyButton: true,
  autoDetect: true,
});

// Atau setup global
NexaHighlig.setupGlobal({ theme: "dracula" });
const highlighter = window.NexaHighlig;
```

### Highlight Kode Sederhana

```js
const code = `
function hello() {
  console.log('Hello, World!');
}
`;

const html = highlighter.highlight(code, "javascript");
console.log(html); // HTML dengan syntax highlighting
```

---

## API Reference

### Constructor

```js
new NexaHighlig(config);
```

**Config Options:**
| Opsi | Type | Default | Deskripsi |
|------|------|---------|-----------|
| `theme` | string | `'atom-one-dark'` | Tema CSS highlight.js |
| `lineNumbers` | boolean | `true` | Tampilkan nomor baris |
| `copyButton` | boolean | `true` | Tombol copy-to-clipboard |
| `autoDetect` | boolean | `true` | Auto-detect bahasa |
| `languages` | array | `[]` | Bahasa custom (kosong = semua) |
| `tabSize` | number | `2` | Ukuran indentasi tab |

---

### Method: highlight()

Highlight kode dengan bahasa tertentu.

```js
highlighter.highlight(code, language, options);
```

**Parameters:**

- `code` (string): Kode yang akan di-highlight
- `language` (string): Bahasa (js, php, html, css, python, java, dsb)
- `options` (object, optional):
  - `lineNumbers` (boolean): Override config global

**Return:** HTML string dengan syntax highlighting

**Contoh:**

```js
const code = `<?php echo "Hello"; ?>`;
const html = highlighter.highlight(code, "php");
```

---

### Method: highlightAuto()

Auto-detect bahasa dan highlight.

```js
highlighter.highlightAuto(code, options);
```

**Return:** Object

```js
{
  html: '...',           // HTML dengan highlight
  language: 'javascript', // Bahasa yang terdeteksi
  relevance: 8           // Confidence score (0-10)
}
```

**Contoh:**

```js
const result = highlighter.highlightAuto("const x = 10;");
console.log(result.language); // 'javascript'
```

---

### Method: highlightElement()

Highlight kode di elemen DOM tertentu.

```js
highlighter.highlightElement(selector, language);
```

**Parameters:**

- `selector` (string|HTMLElement): CSS selector atau element
- `language` (string, optional): Bahasa (auto-detect jika kosong)

**Contoh:**

```js
// Highlight <code id="myCode">
highlighter.highlightElement("#myCode", "javascript");

// Atau dengan element ref
const el = document.getElementById("myCode");
highlighter.highlightElement(el, "javascript");
```

---

### Method: highlightAll()

Highlight semua code block di halaman.

```js
highlighter.highlightAll(selector, auto);
```

**Parameters:**

- `selector` (string): CSS selector (default: `'pre code'`)
- `auto` (boolean): Auto-detect bahasa (default: `true`)

**Contoh:**

```js
// Highlight semua <pre><code> di halaman
highlighter.highlightAll();

// Dengan custom selector
highlighter.highlightAll(".code-snippet");

// Dengan bahasa fixed
highlighter.highlightAll("pre code", false);
```

---

### Method: renderCodeBlock()

Buat elemen `<pre><code>` dengan highlight.

```js
const element = highlighter.renderCodeBlock(code, language, options);
```

**Return:** HTMLElement `<pre>`

**Contoh:**

```js
const code = "function test() {}";
const block = highlighter.renderCodeBlock(code, "javascript");
document.body.appendChild(block);
```

---

### Method: renderInlineCode()

Buat inline `<code>` element dengan highlight.

```js
const element = highlighter.renderInlineCode(code, language);
```

**Return:** HTMLElement `<code>`

**Contoh:**

```js
const inline = highlighter.renderInlineCode("const x = 10", "javascript");
document.body.appendChild(inline);
```

---

### Method: setTheme()

Ubah tema CSS.

```js
highlighter.setTheme(themeName);
```

**Contoh:**

```js
highlighter.setTheme("dracula");
highlighter.setTheme("atom-one-light");
highlighter.setTheme("github-dark");
```

---

### Method: listLanguages()

Dapatkan daftar bahasa yang didukung.

```js
const languages = highlighter.listLanguages();
```

**Return:** Array string

**Contoh:**

```js
console.log(highlighter.listLanguages());
// ['javascript', 'python', 'php', 'html', 'css', ...]
```

---

### Method: isSupportedLanguage()

Check apakah bahasa didukung.

```js
highlighter.isSupportedLanguage(language);
```

**Return:** Boolean

**Contoh:**

```js
if (highlighter.isSupportedLanguage("php")) {
  highlighter.highlight(code, "php");
}
```

---

### Method: formatHtml(), formatJs(), formatJson()

Format code dengan inline method.

```js
highlighter.formatHtml(html);
highlighter.formatJs(js);
highlighter.formatJson(json);
```

**Contoh:**

```js
const formattedHtml = highlighter.formatHtml("<div>Hello</div>");
const formattedJs = highlighter.formatJs("const x = {a:1}");
const formattedJson = highlighter.formatJson({ name: "John", age: 30 });
```

---

### Static Method: setupFromCDN()

Load highlight.js dari CDN secara otomatis.

```js
await NexaHighlig.setupFromCDN();
```

**Contoh:**

```js
try {
  await NexaHighlig.setupFromCDN();
  const highlighter = new NexaHighlig();
  console.log("Ready to highlight!");
} catch (error) {
  console.error("Failed to load highlight.js");
}
```

---

### Static Method: setupGlobal()

Setup instance global di `window.NexaHighlig`.

```js
NexaHighlig.setupGlobal(config);
```

**Contoh:**

```js
NexaHighlig.setupGlobal({ theme: "dracula" });
// Sekarang bisa akses: window.NexaHighlig.highlight(code, lang)
```

---

## Contoh Penggunaan

### Contoh 1: Highlight Code Block dengan Line Numbers

```html
<pre id="code-block"><code>function hello() {
  console.log('Hello, World!');
}</code></pre>

<script type="module">
  import NexaHighlig from "./NexaHighlig.js";

  const highlighter = new NexaHighlig({
    theme: "atom-one-dark",
    lineNumbers: true,
    copyButton: true,
  });

  highlighter.highlightElement("#code-block", "javascript");
</script>
```

### Contoh 2: Dynamic Code Insertion

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig();

const examples = {
  js: "const x = 10; console.log(x);",
  php: '<?php echo "Hello"; ?>',
  html: '<div class="container">Content</div>',
};

Object.entries(examples).forEach(([lang, code]) => {
  const block = highlighter.renderCodeBlock(code, lang);
  document.getElementById("examples").appendChild(block);
});
```

### Contoh 3: Auto-Detect Language

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig({ autoDetect: true });

const snippets = [
  "const x = 10;",
  "function test() {}",
  '<?php echo "test"; ?>',
  "<div>HTML</div>",
];

snippets.forEach((code) => {
  const result = highlighter.highlightAuto(code);
  console.log(`Detected: ${result.language}, Confidence: ${result.relevance}`);
});
```

### Contoh 4: Custom Theme Switcher

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig();

const themes = [
  "atom-one-dark",
  "atom-one-light",
  "dracula",
  "github-dark",
  "monokai",
];

const selector = document.getElementById("theme-selector");
selector.innerHTML = themes
  .map((t) => `<option value="${t}">${t}</option>`)
  .join("");

selector.addEventListener("change", (e) => {
  highlighter.setTheme(e.target.value);
});
```

### Contoh 5: Highlight Semua Code di Halaman

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig({
  theme: "dracula",
  lineNumbers: true,
  copyButton: true,
});

// Auto-highlight semua <pre><code> saat DOM ready
document.addEventListener("DOMContentLoaded", () => {
  highlighter.highlightAll("pre code", true);
});
```

### Contoh 6: Inline Code Highlighting

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig();

// Highlight inline code dalam paragraf
const inline = highlighter.renderInlineCode("const x = 10", "javascript");
document.getElementById("intro").appendChild(inline);
```

---

## Tema Tersedia

Semua tema dari highlight.js v11.9.0:

**Dark Themes:**

- `atom-one-dark`
- `atom-one-dark-reasonable`
- `dark`
- `dracula`
- `github-dark`
- `github-dark-dimmed`
- `monokai`
- `monokai-sublime`
- `nord`
- `ocean`
- `sunburst`
- `tomorrow-night`
- `tomorrow-night-blue`
- `tomorrow-night-bright`
- `tomorrow-night-eighties`

**Light Themes:**

- `atom-one-light`
- `github`
- `github-light`
- `gist`
- `googlecode`
- `idea`
- `rainbow`
- `solarized-light`
- `stackoverflow-light`
- `xcode`

**Neutral:**

- `a11y-dark`
- `a11y-light`

---

## Bahasa yang Didukung

NexaHighlig mendukung 190+ bahasa melalui highlight.js:

**Populer:**

- JavaScript/TypeScript
- Python
- PHP
- HTML/XML
- CSS/SCSS/LESS
- Java
- C/C#/C++
- Go
- Rust
- Ruby
- Swift
- Kotlin
- JSON
- SQL
- Bash/Shell
- YAML
- Markdown

Gunakan `highlighter.listLanguages()` untuk daftar lengkap.

---

## Tips & Tricks

### 1. Performance

```js
// Cache instance
const highlighter = new NexaHighlig();

// Reuse untuk banyak kode
const codes = [code1, code2, code3];
codes.forEach((code) => {
  highlighter.highlight(code, "javascript");
});
```

### 2. Custom CSS Class

```html
<style>
  .my-code {
    border: 2px solid blue;
    border-radius: 12px;
  }
</style>

<div class="my-code" id="custom-code"><code></code></div>

<script>
  const block = highlighter.renderCodeBlock(code, "js");
  block.classList.add("my-code");
</script>
```

### 3. Progressive Enhancement

```js
// Graceful fallback jika highlight.js gagal
const highlighter = new NexaHighlig();

try {
  const html = highlighter.highlight(code, "php");
  element.innerHTML = html;
} catch (error) {
  // Fallback: tampilkan plain text
  element.textContent = code;
}
```

### 4. Integrasi dengan Markdown

```js
// Highlight code blocks saat render markdown
import marked from "./marked.js";

marked.setOptions({
  highlight: (code, lang) => {
    return highlighter.highlight(code, lang);
  },
});
```

---

## Troubleshooting

### highlight.js tidak ditemukan

```js
// Pastikan load highlight.js dulu:
// <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

// Atau gunakan setupFromCDN()
await NexaHighlig.setupFromCDN();
```

### Bahasa tidak ter-highlight dengan benar

```js
// Check apakah bahasa didukung
console.log(highlighter.isSupportedLanguage("rust")); // true/false

// Gunakan auto-detect
const result = highlighter.highlightAuto(code);
console.log(result.language); // Language yang terdeteksi
```

### Tombol Copy tidak berfungsi

```js
// Pastikan browser support Clipboard API
if (navigator.clipboard) {
  // Copy button akan berfungsi
}

// Atau disable copy button
const highlighter = new NexaHighlig({ copyButton: false });
```

---

## License

Bagian dari NexaUI Framework. Menggunakan highlight.js (BSD-3-Clause License).
