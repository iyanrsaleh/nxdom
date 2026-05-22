# NexaHighlig - Syntax Highlighting Module

Modul complete untuk syntax highlighting menggunakan **highlight.js** dalam framework NexaJS.

## 📁 File Structure

```
Highlight/
├── NexaHighlig.js              # Main class (ES6 module)
├── nextHighlig.css             # Styling dan themes
├── NexaHighlig.md              # Dokumentasi lengkap API
├── index.html                  # Demo & testing interface
├── example-nexajs-route.js     # Contoh integrasi NexaJS
└── highlight.js                # Library highlight.js (v11.9.0)
```

---

## 🚀 Quick Start

### 1. Gunakan di HTML

```html
<!-- Import CSS -->
<link rel="stylesheet" href="assets/modules/Highlight/nexaHighlig.css" />

<!-- Load highlight.js dari CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<!-- Setup dan gunakan -->
<script type="module">
  import NexaHighlig from "assets/modules/Highlight/NexaHighlig.js";

  const highlighter = new NexaHighlig({ theme: "dracula" });

  const code = "function test() {}";
  const html = highlighter.highlight(code, "javascript");
  console.log(html); // HTML dengan syntax highlighting
</script>
```

### 2. Gunakan di NexaJS Route

```js
// templates/my-code-demo.js
import NexaHighlig from "../../assets/modules/Highlight/NexaHighlig.js";

export default {
  init() {
    const highlighter = new NexaHighlig();

    return `
      <div id="code-output"></div>
    `;
  },

  async afterRender() {
    const highlighter = window.NexaHighlig || new NexaHighlig();
    const code = `const x = 10;`;
    const block = highlighter.renderCodeBlock(code, "javascript");
    document.getElementById("code-output").appendChild(block);
  },
};
```

### 3. Demo Langsung

Buka file `index.html` di browser untuk melihat demo interaktif dengan:

- Real-time syntax highlighting
- Theme switcher
- Predefined code examples
- Language support list

---

## 📚 API Overview

### Constructor

```js
const highlighter = new NexaHighlig({
  theme: "atom-one-dark", // CSS theme
  lineNumbers: true, // Show line numbers
  copyButton: true, // Show copy button
  autoDetect: true, // Auto-detect language
  tabSize: 2, // Tab indentation
});
```

### Main Methods

| Method                             | Usage                                 | Return      |
| ---------------------------------- | ------------------------------------- | ----------- |
| `highlight(code, lang)`            | Highlight kode dengan bahasa tertentu | HTML string |
| `highlightAuto(code)`              | Auto-detect bahasa dan highlight      | Object      |
| `highlightElement(selector, lang)` | Highlight elemen DOM                  | -           |
| `highlightAll(selector)`           | Highlight semua code blocks           | -           |
| `renderCodeBlock(code, lang)`      | Buat `<pre><code>` element            | HTMLElement |
| `renderInlineCode(code, lang)`     | Buat inline `<code>` element          | HTMLElement |
| `setTheme(theme)`                  | Ubah tema CSS                         | -           |
| `listLanguages()`                  | Daftar bahasa didukung                | Array       |
| `isSupportedLanguage(lang)`        | Check bahasa                          | Boolean     |

Dokumentasi lengkap: [NexaHighlig.md](./NexaHighlig.md)

---

## 🎨 Features

✅ **190+ Bahasa** - JavaScript, PHP, Python, HTML, CSS, JSON, SQL, Java, dan lebih banyak  
✅ **20+ Themes** - Dark, light, colorful themes dari highlight.js  
✅ **Line Numbers** - Optional nomor baris untuk setiap line  
✅ **Copy Button** - Button otomatis untuk copy-to-clipboard  
✅ **Auto-detect** - Deteksi bahasa otomatis  
✅ **Inline Code** - Support untuk inline code highlighting  
✅ **Responsive** - Mobile-friendly styling  
✅ **Theme Switcher** - Ganti tema secara runtime  
✅ **Zero Dependencies** - Hanya membutuhkan highlight.js

---

## 📖 Usage Examples

### Example 1: Basic Highlighting

```js
import NexaHighlig from "./NexaHighlig.js";

const highlighter = new NexaHighlig();
const code = `console.log('Hello');`;
const html = highlighter.highlight(code, "javascript");
document.getElementById("output").innerHTML = html;
```

### Example 2: Auto-detect Language

```js
const result = highlighter.highlightAuto("const x = 10;");
console.log(result.language); // 'javascript'
console.log(result.html); // highlighted HTML
```

### Example 3: Dynamic Code Block

```js
const code = `
function fibonacci(n) {
  return n <= 1 ? n : fibonacci(n-1) + fibonacci(n-2);
}
`;

const element = highlighter.renderCodeBlock(code, "javascript");
document.body.appendChild(element);
```

### Example 4: Theme Switching

```js
// Change theme
highlighter.setTheme("dracula");

// Re-highlight previous code
const html = highlighter.highlight(code, "javascript");
```

### Example 5: Highlight All Code Blocks

```js
// Auto-detect bahasa dan highlight semua <pre><code>
highlighter.highlightAll("pre code", true);
```

---

## 🎯 Supported Languages

**Popular:**

- JavaScript/TypeScript
- Python
- PHP
- HTML/XML
- CSS/SCSS/LESS
- Java, C, C++, C#
- Go, Rust, Swift
- Ruby, Kotlin
- JSON, SQL
- Bash, YAML
- Markdown

**Total:** 190+ bahasa

Gunakan `highlighter.listLanguages()` untuk daftar lengkap.

---

## 🌈 Available Themes

**Dark Themes:**

- atom-one-dark (default)
- dracula
- github-dark
- monokai
- nord
- tomorrow-night

**Light Themes:**

- atom-one-light
- github-light
- solarized-light
- xcode

**Total:** 20+ themes

---

## ⚙️ Configuration

```js
new NexaHighlig({
  // CSS theme (default: 'atom-one-dark')
  theme: "dracula",

  // Show line numbers (default: true)
  lineNumbers: true,

  // Show copy button (default: true)
  copyButton: true,

  // Auto-detect language (default: true)
  autoDetect: true,

  // Tab indentation size (default: 2)
  tabSize: 2,

  // Specific languages (empty = all)
  languages: ["javascript", "php", "python"],
});
```

---

## 📦 Installation

### Option 1: From CDN (Recommended)

```html
<!-- CSS -->
<link rel="stylesheet" href="assets/modules/Highlight/nexaHighlig.css" />

<!-- highlight.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<!-- NexaHighlig -->
<script type="module">
  import NexaHighlig from "assets/modules/Highlight/NexaHighlig.js";
</script>
```

### Option 2: Direct Integration

```js
// In your NexaJS app
import NexaHighlig from "./assets/modules/Highlight/NexaHighlig.js";

// Setup global
window.highlighter = new NexaHighlig();
```

---

## 🔧 Integration with NexaJS

### In Your Route Template

```js
// templates/blog-post.js
import NexaHighlig from "../../assets/modules/Highlight/NexaHighlig.js";

export default {
  async init() {
    const highlighter = new NexaHighlig({
      theme: "dracula",
      lineNumbers: true,
      copyButton: true,
    });

    window.highlighter = highlighter;

    // Fetch blog content
    const post = await NXUI.Storage().get("/api/posts/1");

    return `
      <article class="blog-post">
        <h1>${post.title}</h1>
        <div id="content">${post.content}</div>
      </article>
    `;
  },

  async afterRender() {
    // Highlight all code blocks in the post
    window.highlighter.highlightAll("pre code", true);
  },
};
```

### In NexaJS Pages

```html
<!-- in index.html -->
<link rel="stylesheet" href="assets/modules/Highlight/nexaHighlig.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<script type="module">
  import NexaHighlig from "assets/modules/Highlight/NexaHighlig.js";

  // Global setup
  NexaHighlig.setupGlobal({
    theme: "atom-one-dark",
    lineNumbers: true,
    copyButton: true,
  });
</script>
```

---

## 🐛 Troubleshooting

### highlight.js not found

```js
// Make sure highlight.js is loaded first:
// <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

// Or use dynamic loading:
await NexaHighlig.setupFromCDN();
```

### Language not highlighting correctly

```js
// Check if language is supported
console.log(window.highlighter.isSupportedLanguage("rust")); // true/false

// Use auto-detect instead
const result = highlighter.highlightAuto(code);
console.log(result.language); // Detected language
```

### Copy button not working

```js
// Check browser support
if (!navigator.clipboard) {
  // Disable copy button
  new NexaHighlig({ copyButton: false });
}
```

---

## 🎓 Learning Resources

1. **Full API Docs:** [NexaHighlig.md](./NexaHighlig.md)
2. **Interactive Demo:** Open `index.html` in browser
3. **Example Route:** See `example-nexajs-route.js`
4. **highlight.js Docs:** https://highlightjs.org/

---

## 📋 Class Methods Reference

### Highlight Methods

- `highlight(code, language, options)` - Basic highlight
- `highlightAuto(code, options)` - Auto-detect language
- `highlightElement(selector, language)` - Highlight DOM element
- `highlightAll(selector, auto)` - Highlight all elements

### Render Methods

- `renderCodeBlock(code, language)` - Render `<pre><code>`
- `renderInlineCode(code, language)` - Render inline code
- `formatHtml(html)` - Format HTML
- `formatJs(js)` - Format JavaScript
- `formatJson(json)` - Format JSON

### Utility Methods

- `setTheme(theme)` - Change theme
- `listLanguages()` - Get supported languages
- `isSupportedLanguage(language)` - Check language support

### Static Methods

- `setupFromCDN()` - Load from CDN
- `setupGlobal(config)` - Setup global instance

---

## 💡 Tips & Best Practices

1. **Cache Instance**

```js
const highlighter = new NexaHighlig();
// Reuse for multiple highlights
```

2. **Progressive Enhancement**

```js
try {
  element.innerHTML = highlighter.highlight(code, lang);
} catch (error) {
  element.textContent = code; // Fallback
}
```

3. **Performance**

```js
// Highlight on demand, not all at once
highlighter.highlightElement("#code-1");
highlighter.highlightElement("#code-2");
// Better: lazy load when visible
```

4. **Theme Persistence**

```js
const theme = localStorage.getItem("highlighter-theme") || "atom-one-dark";
highlighter.setTheme(theme);
```

---

## 📄 License

NexaHighlig menggunakan **highlight.js** v11.9.0  
License: BSD-3-Clause

Bagian dari **NexaUI Framework**

---

## 📞 Support

Untuk pertanyaan atau issue:

1. Baca dokumentasi: [NexaHighlig.md](./NexaHighlig.md)
2. Lihat demo: `index.html`
3. Cek contoh: `example-nexajs-route.js`

---

**Happy Highlighting! 🎨**
