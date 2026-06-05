/**
 * NexaStylesheet - Utility class untuk memuat CSS stylesheet secara dinamis
 * 
 * @example
 * import { NexaStylesheet } from './NexaStylesheet.js';
 * 
 * // Menggunakan path relatif dari module saat ini
 * const stylesheet = new NexaStylesheet('explorer.css');
 * stylesheet.load();
 * 
 * // Menggunakan path absolut
 * const stylesheet2 = new NexaStylesheet('https://example.com/style.css', true);
 * stylesheet2.load();
 */
export class NexaStylesheet {
  // Static registry untuk tracking CSS per handler
  static handlerRegistry = new Map(); // Map<handlerName, Set<stylesheetInstance>>

  /**
   * @param {string} cssPath - Path ke file CSS (relatif atau absolut)
   * @param {boolean|string} isAbsoluteOrBasePath - Jika boolean true, gunakan path absolut. Jika string, gunakan sebagai base path. Jika false, path relatif dari module saat ini
   * @param {string} identifier - Identifier unik untuk CSS (opsional, default: filename dari cssPath)
   * @param {string} handlerName - Nama handler untuk tracking (opsional)
   */
  constructor(cssPath, isAbsoluteOrBasePath = false, identifier = null, handlerName = null) {
    this.cssPath = cssPath;
    
    // Handle different parameter types
    if (typeof isAbsoluteOrBasePath === 'string') {
      // If second parameter is string, treat it as base path
      this.isAbsolute = false;
      this.basePath = isAbsoluteOrBasePath;
    } else {
      // If second parameter is boolean, use as isAbsolute flag
      this.isAbsolute = isAbsoluteOrBasePath;
      this.basePath = null;
    }
    
    this.identifier = identifier || this.extractFilename(cssPath);
    this.handlerName = handlerName || NexaStylesheet.getCurrentHandler();
  }

  /**
   * Extract filename dari path
   * @private
   */
  extractFilename(path) {
    return path.split('/').pop().split('?')[0];
  }

  /**
   * Get current handler name from stack trace
   * @private
   * @static
   * @returns {string|null} Handler name or null if not found
   */
  static getCurrentHandler() {
    try {
      const stack = new Error().stack;
      if (!stack) return null;
      const stackLines = stack.split('\n');
      for (let i = 2; i < stackLines.length; i++) {
        const line = stackLines[i];
        // Cari handler path: /handler/{handlerName}/index.js
        const handlerMatch = line.match(/handler\/([^\/]+)\/index\.js/);
        if (handlerMatch) {
          return handlerMatch[1];
        }
      }
      return null;
    } catch (error) {
      return null;
    }
  }

  /**
   * Get caller module path from stack trace
   * @private
   * @static
   * @returns {string|null} Caller module directory path or null if not found
   */
  static getCallerModulePath() {
    try {
      const stack = new Error().stack;
      if (!stack) return null;

      const stackLines = stack.split('\n');
      // Skip first 2 lines (Error message and this function)
      // Look for the caller (usually 3rd or 4th line)
      for (let i = 2; i < stackLines.length; i++) {
        const line = stackLines[i];
        
        // Match various URL patterns: http://, https://, file://
        // Pattern: (protocol://domain)(/path/to/file)
        const urlPatterns = [
          /(https?:\/\/[^\/\s]+)(\/[^:\s\)]+)/,  // http:// or https://
          /(file:\/\/\/[^\/\s]+)(\/[^:\s\)]+)/,  // file:/// (Windows)
          /(file:\/\/[^\/\s]+)(\/[^:\s\)]+)/     // file:// (Unix)
        ];
        
        for (const pattern of urlPatterns) {
          const match = line.match(pattern);
          if (match && match[2]) {
            const fullPath = match[2];
            // Check if it's not the NexaStylesheet.js itself
            if (!fullPath.includes('NexaStylesheet.js')) {
              // Extract directory (remove filename)
              const dirPath = fullPath.substring(0, fullPath.lastIndexOf('/'));
              if (dirPath) {
                // Return full URL with directory path
                return match[1] + dirPath;
              }
            }
          }
        }
      }
      return null;
    } catch (error) {
      return null;
    }
  }

  /**
   * Check apakah CSS sudah dimuat
   * @returns {boolean}
   */
  isLoaded() {
    const selector = `link[href*="${this.identifier}"]`;
    return document.querySelector(selector) !== null;
  }

  /**
   * Get full path CSS (relatif dari module saat ini atau absolut)
   * @private
   */
  getFullPath() {
    if (this.isAbsolute) {
      return this.cssPath;
    }

    // If basePath is provided, use it
    if (this.basePath) {
      // Remove trailing slash if exists
      const base = this.basePath.endsWith('/') ? this.basePath.slice(0, -1) : this.basePath;
      // Remove leading ./ or / from cssPath
      const css = this.cssPath.replace(/^\.\//, '').replace(/^\//, '');
      return `${base}/${css}`;
    }

    // Try to get caller module path automatically
    const callerPath = NexaStylesheet.getCallerModulePath();
    if (callerPath) {
      return `${callerPath}/${this.cssPath}`;
    }

    // Fallback: Get current module's directory path (NexaStylesheet.js location)
    const currentModulePath = import.meta.url;
    const moduleDir = currentModulePath.substring(0, currentModulePath.lastIndexOf('/'));
    return `${moduleDir}/${this.cssPath}`;
  }

  /**
   * Memuat CSS stylesheet
   * @returns {Promise<void>} Promise yang resolve ketika CSS sudah dimuat
   */
  load() {
    return new Promise((resolve, reject) => {
      // Check if already loaded
      if (this.isLoaded()) {
        // Register to handler jika belum
        if (this.handlerName) {
          NexaStylesheet.registerToHandler(this.handlerName, this);
        }
        resolve();
        return;
      }

      // Create and append CSS link
      const cssLink = document.createElement('link');
      cssLink.rel = 'stylesheet';
      cssLink.href = this.getFullPath();
      cssLink.id = `nexa-stylesheet-${this.identifier}`;
      cssLink.setAttribute('data-handler', this.handlerName || '');

      // Handle load event
      cssLink.onload = () => {
        // Register to handler setelah berhasil load
        if (this.handlerName) {
          NexaStylesheet.registerToHandler(this.handlerName, this);
        }
        resolve();
      };

      // Handle error event
      cssLink.onerror = () => {
        reject(new Error(`Failed to load CSS: ${this.identifier}`));
      };

      // Append to head
      document.head.appendChild(cssLink);
    });
  }

  /**
   * Hapus CSS yang sudah dimuat
   * @returns {boolean} True jika berhasil dihapus, false jika tidak ditemukan
   */
  unload() {
    const linkElement = document.querySelector(`link[href*="${this.identifier}"]`);
    if (linkElement) {
      linkElement.remove();
      return true;
    }
    return false;
  }

  /**
   * Register stylesheet instance to handler
   * @private
   * @static
   * @param {string} handlerName - Handler name
   * @param {NexaStylesheet} instance - Stylesheet instance
   */
  static registerToHandler(handlerName, instance) {
    if (!handlerName) return;
    if (!NexaStylesheet.handlerRegistry.has(handlerName)) {
      NexaStylesheet.handlerRegistry.set(handlerName, new Set());
    }
    NexaStylesheet.handlerRegistry.get(handlerName).add(instance);
  }

  /**
   * Cleanup semua CSS dari handler tertentu
   * @static
   * @param {string} handlerName - Handler name yang akan di-cleanup
   * @returns {number} Jumlah CSS yang berhasil dihapus
   */
  static cleanupHandler(handlerName) {
    if (!handlerName || !NexaStylesheet.handlerRegistry.has(handlerName)) {
      return 0;
    }

    const instances = NexaStylesheet.handlerRegistry.get(handlerName);
    let count = 0;
    
    instances.forEach(instance => {
      if (instance.unload()) {
        count++;
      }
    });

    // Clear registry
    NexaStylesheet.handlerRegistry.delete(handlerName);
    
    // Juga hapus dari DOM jika ada yang terlewat
    const links = document.querySelectorAll(`link[data-handler="${handlerName}"]`);
    links.forEach(link => {
      link.remove();
      count++;
    });

    return count;
  }

  /**
   * Load multiple CSS files sekaligus
   * @static
   * @param {Array<string>} cssPaths - Array of CSS paths
   * @param {boolean|string|undefined} isAbsoluteOrBasePath - Jika boolean true, semua path adalah absolut. Jika string, gunakan sebagai base path. Jika undefined, otomatis detect dari caller
   * @param {string} handlerName - Handler name untuk tracking (opsional, auto-detect jika tidak diisi)
   * @returns {Promise<void[]>} Promise yang resolve ketika semua CSS sudah dimuat
   */
  static Dom(cssPaths, isAbsoluteOrBasePath = undefined, handlerName = null) {
    // If parameter not provided (undefined), try to auto-detect caller
    let basePath = isAbsoluteOrBasePath;
    if (isAbsoluteOrBasePath === undefined) {
      const callerPath = NexaStylesheet.getCallerModulePath();
      basePath = callerPath || false; // Use caller path if found, otherwise fallback to false
    }

    // Auto-detect handler name jika tidak diisi
    const detectedHandler = handlerName || NexaStylesheet.getCurrentHandler();
    
    const promises = cssPaths.map(path => {
      const stylesheet = new NexaStylesheet(path, basePath, null, detectedHandler);
      return stylesheet.load();
    });
    return Promise.all(promises);
  }

  /**
   * Get NexaUi base path secara otomatis
   * @private
   * @static
   * @returns {string|null} Base path ke folder NexaUi atau null jika tidak ditemukan
   */
  static getNexaUiBasePath() {
    try {
      // Method 1: Dari current module (NexaStylesheet.js)
      const currentModulePath = import.meta.url;
      // Cari folder NexaUi dalam path
      const nexaUiMatch = currentModulePath.match(/(.*\/modules)\//);
      if (nexaUiMatch) {
        return nexaUiMatch[1];
      }

      // Method 2: Dari document base URI atau scripts
      if (typeof document !== 'undefined') {
        const scripts = document.getElementsByTagName('script');
        for (let script of scripts) {
          if (script.src) {
            const nexaUiMatch = script.src.match(/(.*\/modules)\//);
            if (nexaUiMatch) {
              return nexaUiMatch[1];
            }
          }
        }

        // Method 3: Dari base tag atau window location
        const baseTag = document.querySelector('base');
        if (baseTag && baseTag.href) {
          const nexaUiMatch = baseTag.href.match(/(.*\/modules)\//);
          if (nexaUiMatch) {
            return nexaUiMatch[1];
          }
        }

        // Method 4: Dari window.location
        if (window.location && window.location.href) {
          const nexaUiMatch = window.location.href.match(/(.*\/modules)\//);
          if (nexaUiMatch) {
            return nexaUiMatch[1];
          }
        }
      }

      return null;
    } catch (error) {
      return null;
    }
  }

  /**
   * Load multiple CSS files dari folder NexaUi secara otomatis
   * @static
   * @param {Array<string>} cssPaths - Array of CSS paths relatif dari folder NexaUi (contoh: ['css/style.css', 'Dom/explorer.css'])
   * @param {string} handlerName - Handler name untuk tracking (opsional, auto-detect jika tidak diisi)
   * @returns {Promise<void[]>} Promise yang resolve ketika semua CSS sudah dimuat
   * @example
   * // Load dari folder NexaUi
   * await NXUI.NexaStylesheet.NexaUi(['css/style.css', 'Dom/explorer.css']);
   */
  static async NexaUi(cssPaths, handlerName = null) {
    const nexaUiBasePath = NexaStylesheet.getNexaUiBasePath();
    
    if (!nexaUiBasePath) {
      throw new Error('NexaUi base path not found');
    }

    // Auto-detect handler name jika tidak diisi
    const detectedHandler = handlerName || NexaStylesheet.getCurrentHandler();

    const promises = cssPaths.map(path => {
      // Remove leading slash if exists
      const cleanPath = path.startsWith('/') ? path.slice(1) : path;
      const fullPath = `${nexaUiBasePath}/${cleanPath}`;
      const stylesheet = new NexaStylesheet(cleanPath, nexaUiBasePath, null, detectedHandler);
      return stylesheet.load();
    });
    
    return Promise.all(promises);
  }

  /**
   * Alias untuk Dom (backward compatibility)
   * @static
   * @deprecated Gunakan Dom() sebagai gantinya
   */
  static loadMultiple(cssPaths, isAbsoluteOrBasePath = undefined) {
    return NexaStylesheet.Dom(cssPaths, isAbsoluteOrBasePath);
  }
}

