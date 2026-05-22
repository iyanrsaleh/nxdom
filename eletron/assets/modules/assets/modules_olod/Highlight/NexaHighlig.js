/**
 * NexaHighlig - Syntax Highlighting Wrapper untuk highlight.js
 * Menyediakan API mudah untuk syntax highlighting kode
 *
 * @author NexaUI
 * @version 1.0.0
 */

export class NexaHighlig {
  /**
   * Constructor untuk NexaHighlig
   * @param {Object} config - Konfigurasi
   * @param {string} config.theme - Tema CSS (default: 'atom-one-dark')
   * @param {boolean} config.lineNumbers - Tampilkan nomor baris (default: true)
   * @param {boolean} config.copyButton - Tombol copy-to-clipboard (default: true)
   * @param {Array} config.languages - Bahasa yang diaktifkan (default: semua)
   * @param {boolean} config.autoDetect - Auto-detect bahasa (default: true)
   */
  constructor(config = {}) {
    this.config = {
      theme: config.theme || "atom-one-dark",
      lineNumbers: config.lineNumbers !== false,
      copyButton: config.copyButton !== false,
      languages: config.languages || [],
      autoDetect: config.autoDetect !== false,
      tabSize: config.tabSize || 2,
    };

    this.hljs = window.hljs; // Reference ke highlight.js global
    this.registeredLanguages = new Set();

    if (!this.hljs) {
      console.warn("[NexaHighlig] highlight.js tidak ditemukan di window.hljs");
    }

    this._initTheme();
  }

  /**
   * Inisialisasi tema CSS
   * Tema dimuat dari HTML statis, tidak dari CDN
   * @private
   */
  _initTheme() {
    // Theme dimuat langsung dari HTML <link rel="stylesheet">
    // Tidak perlu dynamic loading dari CDN
    // User harus menyertakan theme CSS di HTML secara manual
  }

  /**
   * Set tema CSS
   * Ubah theme dengan mengganti href <link> element di HTML
   * @param {string} themeName - Nama tema (atom-one-dark, atom-one-light, dracula, github-dark, dsb)
   */
  setTheme(themeName) {
    this.config.theme = themeName;
    // Find existing theme link in HTML
    let themeLink = document.getElementById("nexaHighligTheme");
    if (themeLink) {
      themeLink.href = `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/${themeName}.min.css`;
    }
  }

  /**
   * Highlight kode dengan bahasa tertentu
   * @param {string} code - Kode yang akan di-highlight
   * @param {string} language - Bahasa pemrograman (js, php, html, css, python, dsb)
   * @param {Object} options - Opsi tambahan
   * @returns {string} HTML dengan highlight
   */
  highlight(code, language, options = {}) {
    if (!this.hljs) {
      return this._escapeHtml(code);
    }

    try {
      const useLineNumbers =
        options.lineNumbers !== undefined
          ? options.lineNumbers
          : this.config.lineNumbers;

      let highlighted = this.hljs.highlight(code, {
        language: language || "plaintext",
        ignoreIllegals: true,
      }).value;

      if (useLineNumbers) {
        highlighted = this._addLineNumbers(highlighted);
      }

      return highlighted;
    } catch (error) {
      console.error(`[NexaHighlig] Error highlighting ${language}:`, error);
      return this._escapeHtml(code);
    }
  }

  /**
   * Auto-detect bahasa dan highlight
   * @param {string} code - Kode yang akan di-highlight
   * @param {Object} options - Opsi tambahan
   * @returns {Object} { html, language, relevance }
   */
  highlightAuto(code, options = {}) {
    if (!this.hljs) {
      return {
        html: this._escapeHtml(code),
        language: "plaintext",
        relevance: 0,
      };
    }

    try {
      const result = this.hljs.highlightAuto(code);
      const useLineNumbers =
        options.lineNumbers !== undefined
          ? options.lineNumbers
          : this.config.lineNumbers;

      let highlighted = result.value;
      if (useLineNumbers) {
        highlighted = this._addLineNumbers(highlighted);
      }

      return {
        html: highlighted,
        language: result.language || "plaintext",
        relevance: result.relevance,
      };
    } catch (error) {
      console.error("[NexaHighlig] Error auto-highlighting:", error);
      return {
        html: this._escapeHtml(code),
        language: "plaintext",
        relevance: 0,
      };
    }
  }

  /**
   * Highlight seluruh kode di dalam elemen DOM
   * @param {HTMLElement|string} selector - Elemen atau CSS selector
   * @param {string} language - Bahasa (optional, auto-detect jika tidak ada)
   */
  highlightElement(selector, language) {
    const element = this._getElement(selector);
    if (!element) return;

    const code = element.textContent;
    const useLanguage = language || this._detectLanguageFromClass(element);

    const highlighted = this.highlight(code, useLanguage);
    element.innerHTML = highlighted;
    element.classList.add("nexaHighlig-highlighted");

    if (this.config.copyButton) {
      this._addCopyButton(element);
    }
  }

  /**
   * Highlight semua <code> block di halaman
   * @param {string} selector - CSS selector (default: 'pre code')
   * @param {boolean} auto - Auto-detect bahasa (default: true)
   */
  highlightAll(selector = "pre code", auto = this.config.autoDetect) {
    const elements = document.querySelectorAll(selector);
    elements.forEach((element) => {
      const language = auto
        ? undefined
        : this._detectLanguageFromClass(element);
      this.highlightElement(element, language);
    });
  }

  /**
   * Dapatkan daftar bahasa yang tersedia
   * @returns {Array} Array nama bahasa
   */
  listLanguages() {
    return this.hljs ? this.hljs.listLanguages() : [];
  }

  /**
   * Check apakah bahasa didukung
   * @param {string} language - Nama bahasa
   * @returns {boolean}
   */
  isSupportedLanguage(language) {
    return this.hljs && this.hljs.listLanguages().includes(language);
  }

  /**
   * Render code block dengan wrapper
   * @param {string} code - Kode
   * @param {string} language - Bahasa
   * @param {Object} options - Opsi
   * @returns {HTMLElement} DOM element <pre>
   */
  renderCodeBlock(code, language, options = {}) {
    const pre = document.createElement("pre");
    const codeEl = document.createElement("code");

    const highlighted = this.highlight(code, language, options);
    codeEl.innerHTML = highlighted;
    codeEl.className = `language-${language || "plaintext"}`;

    pre.appendChild(codeEl);
    pre.className = "nexaHighlig-block";

    if (this.config.copyButton) {
      this._addCopyButton(codeEl);
    }

    return pre;
  }

  /**
   * Render inline code
   * @param {string} code - Kode
   * @param {string} language - Bahasa
   * @returns {HTMLElement} DOM element <code>
   */
  renderInlineCode(code, language) {
    const codeEl = document.createElement("code");
    const highlighted = this.highlight(code, language, { lineNumbers: false });
    codeEl.innerHTML = highlighted;
    codeEl.className = `nexaHighlig-inline language-${language || "plaintext"}`;
    return codeEl;
  }

  /**
   * Format HTML dengan indentasi
   * @param {string} html - HTML code
   * @returns {string} Formatted HTML
   */
  formatHtml(html) {
    return this.highlight(html, "html");
  }

  /**
   * Format JavaScript dengan indentasi
   * @param {string} js - JavaScript code
   * @returns {string} Formatted JavaScript
   */
  formatJs(js) {
    return this.highlight(js, "javascript");
  }

  /**
   * Format JSON dengan indentasi
   * @param {Object|string} json - JSON object atau string
   * @returns {string} Formatted JSON
   */
  formatJson(json) {
    const jsonStr =
      typeof json === "string" ? json : JSON.stringify(json, null, 2);
    return this.highlight(jsonStr, "json");
  }

  /**
   * Tambahkan nomor baris ke kode
   * @private
   */
  _addLineNumbers(html) {
    const lines = html.split("\n");
    return lines
      .map((line, i) => {
        const lineNum = i + 1;
        return `<div class="nexaHighlig-line"><span class="nexaHighlig-line-num">${lineNum}</span><span class="nexaHighlig-line-code">${line || " "}</span></div>`;
      })
      .join("");
  }

  /**
   * Deteksi bahasa dari class HTML
   * @private
   */
  _detectLanguageFromClass(element) {
    const className = element.className || "";
    const match = className.match(/language-(\w+)/);
    return match ? match[1] : undefined;
  }

  /**
   * Escape HTML entities
   * @private
   */
  _escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }

  /**
   * Dapatkan elemen DOM
   * @private
   */
  _getElement(selector) {
    if (typeof selector === "string") {
      return document.querySelector(selector);
    }
    return selector;
  }

  /**
   * Tambahkan tombol copy ke elemen
   * @private
   */
  _addCopyButton(element) {
    if (element.querySelector(".nexaHighlig-copy-btn")) {
      return; // Button sudah ada
    }

    const btn = document.createElement("button");
    btn.className = "nexaHighlig-copy-btn";
    btn.textContent = "Copy";
    btn.title = "Copy to clipboard";

    btn.addEventListener("click", () => {
      const code = element.textContent;
      navigator.clipboard.writeText(code).then(() => {
        btn.textContent = "Copied!";
        setTimeout(() => {
          btn.textContent = "Copy";
        }, 2000);
      });
    });

    const wrapper = element.parentElement;
    if (wrapper && wrapper.tagName === "PRE") {
      wrapper.style.position = "relative";
      wrapper.appendChild(btn);
    }
  }

  /**
   * Setup highlight.js dengan CDN (jika belum ada)
   * @static
   * @returns {Promise}
   */
  static async setupFromCDN() {
    return new Promise((resolve, reject) => {
      if (window.hljs) {
        resolve(window.hljs);
        return;
      }

      // Load highlight.js dari CDN
      const script = document.createElement("script");
      script.src =
        "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js";
      script.onload = () => {
        resolve(window.hljs);
      };
      script.onerror = () => {
        reject(new Error("Failed to load highlight.js from CDN"));
      };
      document.head.appendChild(script);
    });
  }

  /**
   * Setup global instance di window
   * @static
   */
  static setupGlobal(config = {}) {
    window.NexaHighlig = new NexaHighlig(config);
    return window.NexaHighlig;
  }
}

// Export default
export default NexaHighlig;
