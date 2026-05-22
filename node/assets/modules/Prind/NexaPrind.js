/**
 * NexaPrind - Class untuk mencetak target elemen tertentu
 */
export class NexaPrind {
  // Static flag untuk mencegah multiple print calls
  static isPrinting = false;

  constructor() {
    this.originalContent = "";
    this.originalTitle = "";
    this.loadingTimeout = 3000; // Max loading time
  }

  /**
   * Cetak elemen berdasarkan ID
   * @param {string} targetId - ID elemen yang akan dicetak
   * @param {object} options - Opsi tambahan untuk pencetakan
   * @param {array} options.styleNot - Array CSS selectors untuk mengecualikan dari capture style (contoh: ['.tcx-center', '.tcx-footer'])
   */
  static printById(targetId, options = {}) {
    // Cegah multiple calls - tapi dengan timeout yang lebih pendek
    if (NexaPrind.isPrinting) {
      console.log("Print already in progress, skipping duplicate call...");
      return false;
    }

    const element = document.getElementById(targetId);
    if (!element) {
      console.error(`Element dengan ID '${targetId}' tidak ditemukan`);
      return false;
    }

    // Set flag sebelum print
    NexaPrind.isPrinting = true;
    console.log("NexaPrind: Starting print for", targetId);

    try {
      const instance = new NexaPrind();
      const result = instance.print(element, options);

      // Reset flag setelah delay yang lebih pendek
      setTimeout(() => {
        NexaPrind.isPrinting = false;
        console.log("NexaPrind: Print flag reset");
      }, 2000); // Kurangi menjadi 2 detik

      return result;
    } catch (error) {
      console.error("NexaPrind: Error in printById", error);
      // Reset flag on error
      setTimeout(() => {
        NexaPrind.isPrinting = false;
      }, 500);
      return false;
    }
  }

  /**
   * Cetak elemen berdasarkan selector
   * @param {string} selector - CSS selector
   * @param {object} options - Opsi tambahan untuk pencetakan
   * @param {array} options.styleNot - Array CSS selectors untuk mengecualikan dari capture style (contoh: ['.tcx-center', '.tcx-footer'])
   */
  static printBySelector(selector, options = {}) {
    const element = document.querySelector(selector);
    if (!element) {
      console.error(`Element dengan selector '${selector}' tidak ditemukan`);
      return false;
    }

    const instance = new NexaPrind();
    return instance.print(element, options);
  }

  /**
   * Check if URL is accessible
   * @param {string} url - URL to check
   */
  async isUrlAccessible(url) {
    try {
      const controller = new AbortController();
      setTimeout(() => controller.abort(), 1000); // 1 second timeout

      const response = await fetch(url, {
        method: "HEAD",
        signal: controller.signal,
      });
      return response.ok;
    } catch {
      return false;
    }
  }

  /**
   * Fungsi utama untuk mencetak elemen
   * @param {HTMLElement} element - Elemen yang akan dicetak
   * @param {object} options - Opsi pencetakan
   */
  print(element, options = {}) {
    // Flag sudah di-check di printById, tidak perlu check lagi di sini
    // Langsung eksekusi print

    // Default options
    const defaultOptions = {
      title: "Print Document",
      captureDynamicStyles: true,
      forceBackgrounds: true,
      preserveLayout: true,
      skipBrokenResources: true,
      useFallbackFonts: true,
      optimizePerformance: true,
      newWindow: false,
      removeAfterPrint: false,
      // Style exclusions
      styleNot: [], // Array of CSS selectors to exclude from style capture
      // Paper settings
      paperSize: "A4", // A4, A3, A5, Letter, Legal, Custom
      orientation: "portrait", // portrait, landscape
      customSize: null, // {width: "210mm", height: "297mm"} untuk custom
      margins: "0", // margin dalam mm atau "none" untuk 0 (berlaku untuk semua halaman)
      // Margin detail untuk setiap sisi (berlaku untuk semua halaman)
      marginTop: null, // margin atas khusus (mm) - contoh: "20", "20mm", 20
      marginRight: null, // margin kanan khusus (mm) - contoh: "15", "15mm", 15
      marginBottom: null, // margin bawah khusus (mm) - contoh: "20", "20mm", 20
      marginLeft: null, // margin kiri khusus (mm) - contoh: "15", "15mm", 15
      // Font settings
      fontSize: "12pt", // Ukuran font untuk print (8pt, 10pt, 12pt, 14pt, 16pt, 18pt, dll)
      fontFamily: null, // Font family khusus untuk print, null = gunakan default
      lineHeight: "1.2", // Line height untuk print (0.8, 0.9, 1.0, 1.1, 1.2, dll)
    };

    const config = { ...defaultOptions, ...options };

    // Debug: Log final configuration
    console.log("Print Configuration:", {
      lineHeight: config.lineHeight,
      styleNot: config.styleNot,
      fontSize: config.fontSize,
    });

    // Debug: Generate excluded selectors for testing
    if (config.styleNot && config.styleNot.length > 0) {
      const excludedSelectors = config.styleNot
        .map(
          (selector) =>
            `${selector} h1, ${selector} h2, ${selector} h3, ${selector} h4, ${selector} h5, ${selector} h6`
        )
        .join(", ");
      console.log("Excluded heading selectors:", excludedSelectors);
    }

    try {
      // Jangan simpan / ganti document.body.innerHTML — di SPA itu menghapus semua listener
      // router (NexaRoute, dll.). Cetak lewat iframe atau window baru saja.

      // Buat konten untuk dicetak dengan optimizations
      this.preparePrintContentOptimized(element, config).then(
        (printContent) => {
          if (config.newWindow) {
            this.printInNewWindow(printContent, config);
          } else {
            this.printInHiddenIframe(printContent, config);
          }
        }
      );

      return true;
    } catch (error) {
      console.error("Error saat mencetak:", error);
      return false;
    }
  }

  /**
   * Check if element should be excluded from style capture
   * @param {HTMLElement} element - Element to check
   * @param {array} styleNot - Array of CSS selectors to exclude
   */
  shouldExcludeElement(element, styleNot = []) {
    if (!styleNot || styleNot.length === 0) return false;

    try {
      const shouldExclude = styleNot.some((selector) => {
        // Remove leading dot for class selectors
        const cleanSelector = selector.startsWith(".")
          ? selector.substring(1)
          : selector;

        // Check if element has the class
        if (selector.startsWith(".")) {
          const hasClass = element.classList.contains(cleanSelector);
          if (hasClass) {
            console.log(`Element excluded by styleNot: ${selector}`, element);
          }
          return hasClass;
        }

        // For other selectors, use matches method
        const matches = element.matches(selector);
        if (matches) {
          console.log(`Element excluded by styleNot: ${selector}`, element);
        }
        return matches;
      });

      return shouldExclude;
    } catch (e) {
      console.warn("Error checking style exclusion:", e);
      return false;
    }
  }

  /**
   * Capture computed styles dengan optimizations
   * @param {HTMLElement} element - Elemen target
   * @param {object} config - Configuration options
   */
  captureDynamicStylesOptimized(element, config) {
    const styleMap = new Map();
    const elements = [element, ...element.querySelectorAll("*")];

    // Debug: Log styleNot configuration
    if (config.styleNot && config.styleNot.length > 0) {
      console.log("StyleNot configuration:", config.styleNot);
    }

    // Limit jumlah elemen untuk performance
    const maxElements = config.optimizePerformance ? 100 : elements.length;
    const limitedElements = elements.slice(0, maxElements);

    limitedElements.forEach((el, index) => {
      try {
        // Skip element if it matches any styleNot selector
        if (this.shouldExcludeElement(el, config.styleNot)) {
          return; // Skip this element
        }

        // Skip table elements to preserve their original styling completely
        // This ensures NexaPrind does NOT interfere with table styles at all
        const isTableElement = [
          "TABLE",
          "TBODY",
          "THEAD",
          "TFOOT",
          "TR",
          "TD",
          "TH",
        ].includes(el.tagName);
        if (isTableElement) {
          return; // Skip table elements - preserve ALL original table styles
        }

        const computed = window.getComputedStyle(el);
        const uniqueId = `dyn-${Date.now()}-${index}`;

        // Buat attribute selector yang unik
        el.setAttribute("data-print-id", uniqueId);

        // Ambil styles penting saja untuk performance
        const importantStyles = {};
        const criticalProps = [
          "color",
          "background-color",
          "background-image",
          "background",
          "border",
          "border-radius",
          "padding",
          "margin",
          "margin-top",
          "margin-left",
          "margin-right",
          "margin-bottom",
          "font-family",
          "font-size",
          "font-weight",
          "font-style",
          "text-align",
          "text-decoration",
          // "line-height", // Removed to preserve table line-height
          // "width",      // Removed to preserve table width
          // "height",     // Removed to preserve table height
          "display",
          "position",
          "box-shadow",
          "text-shadow",
          "opacity",
          "visibility",
        ];

        // Jika optimize performance off, ambil semua
        const propsToCheck = config.optimizePerformance
          ? criticalProps
          : Array.from({ length: computed.length }, (_, i) => computed[i]);

        propsToCheck.forEach((property) => {
          try {
            const value = computed.getPropertyValue(property);
            if (
              value &&
              value !== "none" &&
              value !== "auto" &&
              value !== "normal" &&
              value !== "initial" &&
              value !== "inherit" &&
              value !== "0px" &&
              value !== "transparent" &&
              !value.includes("initial")
            ) {
              importantStyles[property] = value;
            }
          } catch (e) {
            // Skip problematic properties
          }
        });

        if (Object.keys(importantStyles).length > 0) {
          styleMap.set(uniqueId, importantStyles);
        }
      } catch (e) {
        console.warn("Error capturing styles for element:", e);
      }
    });

    return styleMap;
  }

  /**
   * Generate CSS dari style map dengan error handling
   * @param {Map} styleMap - Map styles yang telah dicapture
   */
  generateCSSFromStyleMap(styleMap) {
    let css = "";

    try {
      styleMap.forEach((styles, uniqueId) => {
        if (Object.keys(styles).length > 0) {
          css += `[data-print-id="${uniqueId}"] {\n`;

          Object.entries(styles).forEach(([property, value]) => {
            // Sanitize values
            const sanitizedValue = value.replace(/[<>]/g, "");
            css += `  ${property}: ${sanitizedValue} !important;\n`;
          });

          css += "}\n";
        }
      });
    } catch (e) {
      console.warn("Error generating CSS:", e);
    }

    return css;
  }

  /**
   * Extract CSS dengan error handling untuk missing files
   */
  extractAccessibleCSS(config) {
    let allCSS = "";

    try {
      // Ambil dari stylesheet objects dengan error handling
      Array.from(document.styleSheets).forEach((sheet) => {
        try {
          if (sheet.cssRules || sheet.rules) {
            const rules = Array.from(sheet.cssRules || sheet.rules);
            rules.forEach((rule) => {
              try {
                if (rule.cssText && !rule.cssText.includes("404")) {
                  allCSS += rule.cssText + "\n";
                }
              } catch (e) {
                // Skip problematic rules
              }
            });
          } else if (sheet.href && config.skipBrokenResources) {
            // Skip external stylesheets that might be broken
            console.warn("Skipping external stylesheet:", sheet.href);
          }
        } catch (e) {
          console.warn("Cannot access stylesheet:", sheet.href || "inline");
        }
      });

      // Ambil dari style tags dengan validation
      document.querySelectorAll("style").forEach((styleTag) => {
        try {
          if (
            styleTag.textContent &&
            !styleTag.textContent.includes("404") &&
            !styleTag.textContent.includes("font-face")
          ) {
            allCSS += styleTag.textContent + "\n";
          }
        } catch (e) {
          console.warn("Error reading style tag:", e);
        }
      });
    } catch (e) {
      console.warn("Error extracting CSS rules:", e);
    }

    return allCSS;
  }

  /**
   * Siapkan konten untuk pencetakan dengan optimizations
   * @param {HTMLElement} element - Elemen yang akan dicetak
   * @param {object} config - Konfigurasi pencetakan
   */
  async preparePrintContentOptimized(element, config) {
    // Clone elemen untuk preservasi
    const clonedElement = element.cloneNode(true);

    let allStyles = "";

    // 1. Extract CSS yang accessible saja
    allStyles += this.extractAccessibleCSS(config);

    // 2. Capture computed styles dengan optimizations
    if (config.captureDynamicStyles) {
      const styleMap = this.captureDynamicStylesOptimized(element, config);
      const dynamicCSS = this.generateCSSFromStyleMap(styleMap);
      allStyles += `\n/* Dynamic Computed Styles */\n${dynamicCSS}`;
    }

    // 3. Font settings dan fallback fonts
    const fontSettings = `
                        /* Font settings untuk print - KECUALI heading di dalam styleNot */
                body {
                    ${
                      config.fontSize
                        ? `font-size: ${config.fontSize} !important;`
                        : ""
                    }
                    ${
                      config.lineHeight
                        ? `line-height: ${config.lineHeight} !important;`
                        : ""
                    }
                    ${
                      config.fontFamily
                        ? `font-family: ${config.fontFamily} !important;`
                        : ""
                    }
                }
                /* Font settings untuk semua elemen KECUALI heading dan table elements */
                *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot) {
                    ${
                      config.fontSize
                        ? `font-size: ${config.fontSize} !important;`
                        : ""
                    }
                    ${
                      config.fontFamily
                        ? `font-family: ${config.fontFamily} !important;`
                        : ""
                    }
                }
                
                /* Pastikan heading tetap natural - tidak terpengaruh pengaturan global */
                h1, h2, h3, h4, h5, h6 {
                    font-size: revert !important;
                    line-height: revert !important;
                    font-family: revert !important;
                }
                
                /* Override: Reset font-size untuk heading di dalam styleNot dengan spesifisitas tinggi */
                ${
                  config.styleNot && config.styleNot.length > 0
                    ? (() => {
                        const headingOverrideCSS = config.styleNot
                          .map(
                            (selector) => `
                ${selector} h1, ${selector} h2, ${selector} h3, ${selector} h4, ${selector} h5, ${selector} h6 {
                    font-size: revert !important;
                }
                body ${selector} h1, body ${selector} h2, body ${selector} h3, body ${selector} h4, body ${selector} h5, body ${selector} h6 {
                    font-size: revert !important;
                }
                html body ${selector} h1, html body ${selector} h2, html body ${selector} h3, html body ${selector} h4, html body ${selector} h5, html body ${selector} h6 {
                    font-size: revert !important;
                }
                `
                          )
                          .join("");
                        console.log(
                          "Heading override CSS:",
                          headingOverrideCSS
                        );
                        return headingOverrideCSS;
                      })()
                    : ""
                }
                
                /* Pastikan lineHeight berlaku untuk elemen yang TIDAK dikecualikan dari styleNot KECUALI heading */
                ${
                  config.lineHeight
                    ? `
                body *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot), div, p, span, li, a, section, article {
                    line-height: ${config.lineHeight} !important;
                }
                `
                    : ""
                }
                
                /* LineHeight khusus untuk elemen yang dikecualikan styleNot */
                ${
                  config.styleNot && config.styleNot.length > 0
                    ? (() => {
                        const styleNotCSS = config.styleNot
                          .map(
                            (selector) => `
                ${selector} {
                    line-height: 1.2 !important;
                    font-size: unset !important;
                }
                /* Atur line-height untuk child elements KECUALI heading dan table elements */
                ${selector} *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot) {
                    line-height: 1.2 !important;
                }
                /* Biarkan heading elements natural tanpa pengaturan */
                ${selector} h1, ${selector} h2, ${selector} h3, ${selector} h4, ${selector} h5, ${selector} h6 {
                    font-size: revert !important;
                    line-height: revert !important;
                }
                `
                          )
                          .join("");

                        // CSS khusus untuk semua tabel ketika ada styleNot - DISABLED
                        const tableCSS = `
                /* Table styles disabled - let them use original settings */
                `;

                        const finalCSS = styleNotCSS + tableCSS;
                        console.log("Generated styleNot CSS:", finalCSS);
                        return finalCSS;
                      })()
                    : ""
                }
    `;

    const fallbackFonts = config.useFallbackFonts
      ? `
            /* Fallback fonts untuk mengatasi font errors - EXCLUDE table elements */
            *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th) {
                font-family: ${
                  config.fontFamily ||
                  "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif"
                } !important;
            }
        `
      : "";

    // 4. CSS khusus untuk print dengan error recovery
    const printForceCSS = `
            <style>
                ${fontSettings}
                ${fallbackFonts}
                
                /* Force backgrounds dan colors untuk print - EXCLUDE table elements */
                *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th) {
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                /* Print media queries */
                @media print {
                    *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th) {
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    
                    body { 
                        margin: 0 !important; 
                        padding: 0 !important; 
                        background: white !important;
                        font-family: ${
                          config.fontFamily || "system-ui, Arial, sans-serif"
                        } !important;
                        ${
                          config.fontSize
                            ? `font-size: ${config.fontSize} !important;`
                            : ""
                        }
                        ${
                          config.lineHeight
                            ? `line-height: ${config.lineHeight} !important;`
                            : ""
                        }
                        width: 100% !important;
                        height: auto !important;
                    }
                    
                    /* Font settings untuk semua elemen dalam print KECUALI heading dan table elements */
                    *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot) {
                        ${
                          config.fontSize
                            ? `font-size: ${config.fontSize} !important;`
                            : ""
                        }
                        ${
                          config.fontFamily
                            ? `font-family: ${config.fontFamily} !important;`
                            : ""
                        }
                    }
                    
                    /* Pastikan heading tetap natural dalam print mode */
                    h1, h2, h3, h4, h5, h6 {
                        font-size: revert !important;
                        line-height: revert !important;
                        font-family: revert !important;
                    }
                    
                    /* Override: Reset font-size untuk heading di dalam styleNot dalam print */
                    ${
                      config.styleNot && config.styleNot.length > 0
                        ? config.styleNot
                            .map(
                              (selector) => `
                    ${selector} h1, ${selector} h2, ${selector} h3, ${selector} h4, ${selector} h5, ${selector} h6 {
                        font-size: revert !important;
                    }
                    `
                            )
                            .join("")
                        : ""
                    }
                    
                    /* Force lineHeight untuk elemen yang TIDAK dikecualikan styleNot KECUALI heading */
                    ${
                      config.lineHeight
                        ? `
                    body *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot), div, p, span, li, a, section, article {
                        line-height: ${config.lineHeight} !important;
                    }
                    `
                        : ""
                    }
                    
                    /* LineHeight khusus untuk elemen yang dikecualikan styleNot dalam print */
                    ${
                      config.styleNot && config.styleNot.length > 0
                        ? (() => {
                            const styleNotCSS = config.styleNot
                              .map(
                                (selector) => `
                    ${selector} {
                        line-height: 1.2 !important;
                        font-size: unset !important;
                    }
                    /* Atur line-height untuk child elements KECUALI heading dan table elements dalam print */
                    ${selector} *:not(h1):not(h2):not(h3):not(h4):not(h5):not(h6):not(table):not(td):not(th):not(tr):not(tbody):not(thead):not(tfoot) {
                        line-height: 1.2 !important;
                    }
                    /* Biarkan heading elements natural tanpa pengaturan dalam print */
                    ${selector} h1, ${selector} h2, ${selector} h3, ${selector} h4, ${selector} h5, ${selector} h6 {
                        font-size: revert !important;
                        line-height: revert !important;
                    }
                    `
                              )
                              .join("");

                            // CSS khusus untuk semua tabel ketika ada styleNot dalam print - DISABLED
                            const tableCSS = `
                    /* Table styles disabled in print - let them use original settings */
                    `;

                            return styleNotCSS + tableCSS;
                          })()
                        : ""
                    }
                    
                    /* Pengaturan halaman - berlaku untuk semua halaman */
                    @page { 
                        margin: ${this.getMarginCSS(config)} !important;
                        size: ${this.getPaperSizeCSS(config)} !important;
                    }
                    
                    /* Pengaturan khusus untuk halaman pertama jika diperlukan */
                    @page :first {
                        margin: ${this.getMarginCSS(config)} !important;
                    }
                    
                    /* Pengaturan untuk halaman kiri dan kanan (untuk duplex printing) */
                    @page :left {
                        margin: ${this.getMarginCSS(config)} !important;
                    }
                    
                    @page :right {
                        margin: ${this.getMarginCSS(config)} !important;
                    }
                    
                    .no-print { 
                        display: none !important; 
                    }
                    
                    /* Paksa semua elemen untuk natural flow tanpa page break restrictions - EXCLUDE tables */
                    *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th), 
                    *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th):before, 
                    *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th):after {
                        page-break-inside: auto !important;
                        page-break-before: auto !important;
                        page-break-after: auto !important;
                        break-inside: auto !important;
                        break-before: auto !important;
                        break-after: auto !important;
                    }
                    
                    /* Hide broken images */
                    img[src*="404"], img[src=""] {
                        display: none !important;
                    }
                    
                    /* Pastikan konten memiliki spacing yang konsisten di setiap halaman */
                    .print-content, body > * {
                        margin-top: 0 !important;
                        width: 100% !important;
                        max-width: none !important;
                    }
                    
                    /* Konten utama mengisi penuh halaman */
                    .tx_content, #prind {
                        width: 100% !important;
                        max-width: none !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        min-height: auto !important;
                    }
                    
                    /* Hindari orphan dan widow lines */
                    p, h1, h2, h3, h4, h5, h6 {
                        orphans: 1;
                        widows: 1;
                        page-break-after: auto;
                        page-break-before: auto;
                    }
                    
                    /* Pastikan tabel tidak terpotong di tengah halaman - page-break only */
                    table {
                        page-break-inside: auto;
                        page-break-after: auto;
                        page-break-before: auto;
                        /* All table styling preserved - no forced overrides */
                    }
                    
                    /* Minimal page-break untuk table rows - no other styling */
                    tr {
                        page-break-inside: auto;
                    }
                }
                
                /* Screen styles untuk preview */
                @media screen {
                    body {
                        padding: 20px;
                        font-family: system-ui, -apple-system, sans-serif;
                        line-height: 1.4;
                    }
                }
                
                /* Force display untuk semua elemen KECUALI table elements */
                ${
                  config.preserveLayout
                    ? `
                    *:not(table):not(tbody):not(thead):not(tfoot):not(tr):not(td):not(th) {
                        box-sizing: border-box !important;
                        margin-block-start: 0 !important;
                        margin-block-end: 0 !important;
                        page-break-inside: auto !important;
                        page-break-before: auto !important;
                        page-break-after: auto !important;
                    }
                    
                    /* Allow natural page breaks untuk konten panjang - excludes tables */
                    .tx_content, .document-info, .summary-section, div, section, article {
                        page-break-inside: auto !important;
                        page-break-before: auto !important;
                        page-break-after: auto !important;
                    }
                    
                    /* Tables completely excluded from layout forcing */
                    table, tbody, thead, tfoot, tr, td, th {
                        /* No forced styles - preserve original table layout */
                    }
                `
                    : ""
                }
            </style>
        `;

    // Hapus marker sementara di DOM asli (captureDynamicStyles menulis ke pohon live)
    this.stripPrintMarkers(element);

    return `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>${config.title}</title>
                <style>
                    ${allStyles}
                </style>
                ${printForceCSS}
            </head>
            <body>
                ${clonedElement.outerHTML}
                <script>
                    // Force print backgrounds dengan error handling
                    try {
                        window.addEventListener('beforeprint', function() {
                            document.body.style.webkitPrintColorAdjust = 'exact';
                            document.body.style.colorAdjust = 'exact';
                            document.body.style.printColorAdjust = 'exact';
                        });
                    } catch(e) {
                        console.warn('Cannot set print color adjust:', e);
                    }
                    
                    // Auto-trigger print untuk new window - HAPUS karena sudah di-handle di printInNewWindow
                    // Script ini tidak diperlukan lagi karena printInNewWindow sudah memanggil window.print()
                    // Menghapus untuk mencegah double print dialog
                </script>
            </body>
            </html>
        `;
  }

  /** Hapus data-print-id dari subtree live setelah style map dibuat */
  stripPrintMarkers(root) {
    try {
      if (!root || !root.querySelectorAll) return;
      root.querySelectorAll("[data-print-id]").forEach((el) => {
        el.removeAttribute("data-print-id");
      });
      if (root.hasAttribute && root.hasAttribute("data-print-id")) {
        root.removeAttribute("data-print-id");
      }
    } catch (e) {
      console.warn("NexaPrind: stripPrintMarkers", e);
    }
  }

  /**
   * Cetak di window baru dengan error handling
   * @param {string} content - Konten HTML untuk dicetak
   * @param {object} config - Konfigurasi pencetakan
   */
  printInNewWindow(content, config) {
    try {
      const printWindow = window.open(
        "",
        "printWindow",
        "width=800,height=600,scrollbars=yes"
      );

      if (!printWindow) {
        alert("Pop-up diblokir! Silakan izinkan pop-up untuk mencetak.");
        return;
      }

      printWindow.document.write(content);
      printWindow.document.close();

      // Flag untuk mencegah multiple print calls
      let printCalled = false;

      // Function untuk memanggil print (hanya sekali)
      const callPrint = () => {
        if (printCalled) {
          return; // Sudah dipanggil, skip
        }
        printCalled = true;

        try {
          printWindow.focus();
          printWindow.print();

          if (config.removeAfterPrint) {
            printWindow.addEventListener("afterprint", () => {
              printWindow.close();
            });
          }
        } catch (e) {
          console.error("Print execution failed:", e);
        }
      };

      // Tunggu dengan timeout sebagai fallback
      const timeoutId = setTimeout(() => {
        callPrint();
      }, this.loadingTimeout);

      // Cleanup timeout if window loads earlier
      printWindow.onload = () => {
        clearTimeout(timeoutId);
        setTimeout(() => {
          callPrint();
        }, 500);
      };
    } catch (error) {
      console.error("Error opening print window:", error);
    }
  }

  /**
   * Cetak tanpa mengganti document.body (aman untuk SPA).
   * Konten penuh ditulis ke iframe tersembunyi lalu print() pada iframe.
   * @param {string} content - Dokumen HTML lengkap (sama seperti window baru)
   * @param {object} config - Konfigurasi pencetakan
   */
  printInHiddenIframe(content, config) {
    let iframe = null;
    let cleaned = false;
    let restoreTimeoutId = null;

    const cleanup = () => {
      if (cleaned) return;
      cleaned = true;
      if (restoreTimeoutId != null) {
        clearTimeout(restoreTimeoutId);
        restoreTimeoutId = null;
      }
      try {
        if (iframe && iframe.parentNode) {
          iframe.parentNode.removeChild(iframe);
        }
      } catch (e) {
        console.warn("NexaPrind: iframe cleanup", e);
      }
      iframe = null;
    };

    try {
      iframe = document.createElement("iframe");
      iframe.setAttribute("title", config.title || "Print");
      iframe.setAttribute("aria-hidden", "true");
      Object.assign(iframe.style, {
        position: "fixed",
        right: "0",
        bottom: "0",
        width: "0",
        height: "0",
        border: "0",
        opacity: "0",
        pointerEvents: "none",
      });
      document.body.appendChild(iframe);

      const idoc =
        iframe.contentDocument || iframe.contentWindow?.document;
      if (!idoc) {
        cleanup();
        console.error("NexaPrind: iframe document tidak tersedia");
        return;
      }

      idoc.open();
      idoc.write(content);
      idoc.close();

      const win = iframe.contentWindow;
      if (!win) {
        cleanup();
        return;
      }

      const runPrint = () => {
        try {
          win.focus();
          win.print();
        } catch (e) {
          console.error("NexaPrind: print di iframe gagal", e);
          cleanup();
        }
      };

      win.addEventListener("afterprint", cleanup, { once: true });

      restoreTimeoutId = setTimeout(cleanup, 60000);

      let printScheduled = false;
      const schedulePrint = () => {
        if (printScheduled) return;
        printScheduled = true;
        setTimeout(runPrint, 300);
      };

      if (win.document.readyState === "complete") {
        schedulePrint();
      } else {
        win.addEventListener("load", schedulePrint, { once: true });
        setTimeout(schedulePrint, 800);
      }
    } catch (error) {
      console.error("NexaPrind: printInHiddenIframe", error);
      cleanup();
    }
  }

  /**
   * Generate CSS untuk ukuran kertas berdasarkan konfigurasi
   * @param {object} config - Konfigurasi pencetakan
   */
  getPaperSizeCSS(config) {
    const { paperSize, orientation, customSize } = config;

    // Paper sizes dalam mm
    const paperSizes = {
      A4: { width: 210, height: 297 },
      A3: { width: 297, height: 420 },
      A5: { width: 148, height: 210 },
      Letter: { width: 215.9, height: 279.4 },
      Legal: { width: 215.9, height: 355.6 },
    };

    let size = "";

    if (paperSize === "Custom" && customSize) {
      size = `${customSize.width} ${customSize.height}`;
    } else if (paperSizes[paperSize]) {
      const paper = paperSizes[paperSize];
      if (orientation === "landscape") {
        size = `${paper.height}mm ${paper.width}mm`;
      } else {
        size = `${paper.width}mm ${paper.height}mm`;
      }
    } else {
      // Default fallback
      size = orientation === "landscape" ? "A4 landscape" : "A4 portrait";
    }

    return size;
  }

  /**
   * Generate CSS untuk margin berdasarkan konfigurasi
   * @param {object} config - Konfigurasi pencetakan
   */
  getMarginCSS(config) {
    const { margins, marginTop, marginRight, marginBottom, marginLeft } =
      config;

    // Helper function untuk menambahkan unit jika diperlukan
    const addUnit = (value) => {
      if (!value || value === "0" || value === "none") return "0mm";
      if (typeof value === "string" && /^\d+$/.test(value)) {
        return value + "mm"; // Tambahkan mm jika hanya angka
      }
      if (typeof value === "number") {
        return value + "mm"; // Tambahkan mm jika number
      }
      return value; // Sudah ada unit atau format khusus
    };

    // Jika ada margin detail per sisi
    if (
      marginTop !== null ||
      marginRight !== null ||
      marginBottom !== null ||
      marginLeft !== null
    ) {
      const top = addUnit(
        marginTop || (margins === "none" ? "0" : margins || "0")
      );
      const right = addUnit(
        marginRight || (margins === "none" ? "0" : margins || "0")
      );
      const bottom = addUnit(
        marginBottom || (margins === "none" ? "0" : margins || "0")
      );
      const left = addUnit(
        marginLeft || (margins === "none" ? "0" : margins || "0")
      );

      return `${top} ${right} ${bottom} ${left}`;
    }

    // Jika hanya margin umum
    if (margins === "none") return "0mm";
    return addUnit(margins || "0");
  }

  /**
   * Kembalikan konten asli dengan error handling
   */
  restoreOriginalContent() {
    try {
      if (this.originalContent) {
        document.body.innerHTML = this.originalContent;
        document.title = this.originalTitle;
      }
    } catch (error) {
      console.error("Error restoring content:", error);
      // Fallback: reload page
      window.location.reload();
    }
  }
}

// Export default
export default NexaPrind;
