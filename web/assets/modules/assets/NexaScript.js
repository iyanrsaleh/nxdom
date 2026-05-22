/**
 * NexaScript - Utility class untuk memuat JavaScript modules secara dinamis
 * 
 * @example
 * import { NexaScript } from './NexaScript.js';
 * 
 * // Menggunakan path relatif dari module saat ini (auto-detect)
 * await NexaScript.Dom(['helper.js', 'utils.js']);
 * 
 * // Menggunakan path absolut
 * await NexaScript.Dom(['https://cdn.example.com/library.js'], true);
 */
export class NexaScript {
  // Static registry untuk tracking JS per handler
  static handlerRegistry = new Map(); // Map<handlerName, Set<scriptInstance>>

  /**
   * @param {string} scriptPath - Path ke file JavaScript (relatif atau absolut)
   * @param {boolean|string} isAbsoluteOrBasePath - Jika boolean true, gunakan path absolut. Jika string, gunakan sebagai base path. Jika false, path relatif dari module saat ini
   * @param {string} identifier - Identifier unik untuk script (opsional, default: filename dari scriptPath)
   * @param {string} handlerName - Nama handler untuk tracking (opsional)
   */
  constructor(scriptPath, isAbsoluteOrBasePath = false, identifier = null, handlerName = null) {
    this.scriptPath = scriptPath;
    
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
    
    this.identifier = identifier || this.extractFilename(scriptPath);
    this.module = null; // Store loaded module
    this.handlerName = handlerName || NexaScript.getCurrentHandler();
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
            // Check if it's not the NexaScript.js itself
            if (!fullPath.includes('NexaScript.js')) {
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
   * Check apakah script sudah dimuat
   * @returns {boolean}
   */
  isLoaded() {
    // Check if script tag exists
    const scriptTag = document.querySelector(`script[data-nexa-script="${this.identifier}"]`);
    return scriptTag !== null || this.module !== null;
  }

  /**
   * Get full path script (relatif dari module saat ini atau absolut)
   * @private
   */
  getFullPath() {
    if (this.isAbsolute) {
      return this.scriptPath;
    }

    // If basePath is provided, use it
    if (this.basePath) {
      // Remove trailing slash if exists
      const base = this.basePath.endsWith('/') ? this.basePath.slice(0, -1) : this.basePath;
      // Remove leading slash from scriptPath if exists
      const script = this.scriptPath.startsWith('/') ? this.scriptPath.slice(1) : this.scriptPath;
      return `${base}/${script}`;
    }

    // Try to get caller module path automatically
    const callerPath = NexaScript.getCallerModulePath();
    if (callerPath) {
      return `${callerPath}/${this.scriptPath}`;
    }

    // Fallback: Get current module's directory path (NexaScript.js location)
    const currentModulePath = import.meta.url;
    const moduleDir = currentModulePath.substring(0, currentModulePath.lastIndexOf('/'));
    return `${moduleDir}/${this.scriptPath}`;
  }

  /**
   * Memuat JavaScript module secara dinamis
   * @returns {Promise<Module>} Promise yang resolve dengan module yang sudah dimuat
   */
  async load() {
    // Check if already loaded
    if (this.isLoaded() && this.module) {
      // Register to handler jika belum
      if (this.handlerName) {
        NexaScript.registerToHandler(this.handlerName, this);
      }
      return this.module;
    }

    try {
      const fullPath = this.getFullPath();
      
      // Use dynamic import for ES modules
      const module = await import(fullPath);
      
      // Store module for reuse
      this.module = module;
      
      // Register to handler setelah berhasil load
      if (this.handlerName) {
        NexaScript.registerToHandler(this.handlerName, this);
      }
      
      return module;
    } catch (error) {
      throw new Error(`Failed to load script: ${this.identifier} - ${error.message}`);
    }
  }

  /**
   * Memuat sebagai classic script (non-module) dengan script tag
   * @returns {Promise<void>} Promise yang resolve ketika script sudah dimuat
   */
  loadAsScript() {
    return new Promise((resolve, reject) => {
      // Check if already loaded
      if (this.isLoaded()) {
        // Register to handler jika belum
        if (this.handlerName) {
          NexaScript.registerToHandler(this.handlerName, this);
        }
        resolve();
        return;
      }

      const fullPath = this.getFullPath();
      
      // Create and append script tag
      const scriptTag = document.createElement('script');
      scriptTag.type = 'text/javascript';
      scriptTag.src = fullPath;
      scriptTag.setAttribute('data-nexa-script', this.identifier);
      scriptTag.setAttribute('data-handler', this.handlerName || '');
      scriptTag.id = `nexa-script-${this.identifier}`;

      // Handle load event
      scriptTag.onload = () => {
        // Register to handler setelah berhasil load
        if (this.handlerName) {
          NexaScript.registerToHandler(this.handlerName, this);
        }
        resolve();
      };

      // Handle error event
      scriptTag.onerror = () => {
        reject(new Error(`Failed to load script: ${this.identifier}`));
      };

      // Append to head
      document.head.appendChild(scriptTag);
    });
  }

  /**
   * Hapus script yang sudah dimuat
   * @returns {boolean} True jika berhasil dihapus, false jika tidak ditemukan
   */
  unload() {
    const scriptElement = document.querySelector(`script[data-nexa-script="${this.identifier}"]`);
    if (scriptElement) {
      scriptElement.remove();
      this.module = null;
      return true;
    }
    return false;
  }

  /**
   * Get loaded module
   * @returns {Module|null} Module yang sudah dimuat atau null
   */
  getModule() {
    return this.module;
  }

  /**
   * Register script instance to handler
   * @private
   * @static
   * @param {string} handlerName - Handler name
   * @param {NexaScript} instance - Script instance
   */
  static registerToHandler(handlerName, instance) {
    if (!handlerName) return;
    if (!NexaScript.handlerRegistry.has(handlerName)) {
      NexaScript.handlerRegistry.set(handlerName, new Set());
    }
    NexaScript.handlerRegistry.get(handlerName).add(instance);
  }

  /**
   * Cleanup semua JS dari handler tertentu
   * @static
   * @param {string} handlerName - Handler name yang akan di-cleanup
   * @returns {number} Jumlah JS yang berhasil dihapus
   */
  static cleanupHandler(handlerName) {
    if (!handlerName || !NexaScript.handlerRegistry.has(handlerName)) {
      return 0;
    }

    const instances = NexaScript.handlerRegistry.get(handlerName);
    let count = 0;
    
    instances.forEach(instance => {
      if (instance.unload()) {
        count++;
      }
    });

    // Clear registry
    NexaScript.handlerRegistry.delete(handlerName);
    
    // Juga hapus dari DOM jika ada yang terlewat (untuk classic scripts)
    const scripts = document.querySelectorAll(`script[data-handler="${handlerName}"]`);
    scripts.forEach(script => {
      script.remove();
      count++;
    });

    return count;
  }

  /**
   * Load multiple JavaScript modules sekaligus
   * @static
   * @param {Array<string>} scriptPaths - Array of script paths
   * @param {boolean|string|undefined} isAbsoluteOrBasePath - Jika boolean true, semua path adalah absolut. Jika string, gunakan sebagai base path. Jika undefined, otomatis detect dari caller
   * @param {boolean} asScriptTag - Jika true, gunakan script tag (non-module). Jika false, gunakan dynamic import (ES module)
   * @param {string} handlerName - Handler name untuk tracking (opsional, auto-detect jika tidak diisi)
   * @returns {Promise<Module[]|void[]>} Promise yang resolve ketika semua script sudah dimuat
   */
  static async Dom(scriptPaths, isAbsoluteOrBasePath = undefined, asScriptTag = false, handlerName = null) {
    // If parameter not provided (undefined), try to auto-detect caller
    let basePath = isAbsoluteOrBasePath;
    if (isAbsoluteOrBasePath === undefined) {
      const callerPath = NexaScript.getCallerModulePath();
      basePath = callerPath || false; // Use caller path if found, otherwise fallback to false
    }

    // Auto-detect handler name jika tidak diisi
    const detectedHandler = handlerName || NexaScript.getCurrentHandler();
    
    if (asScriptTag) {
      // Load as classic scripts (non-module)
      const promises = scriptPaths.map(path => {
        const script = new NexaScript(path, basePath, null, detectedHandler);
        return script.loadAsScript();
      });
      return Promise.all(promises);
    } else {
      // Load as ES modules (default)
      const promises = scriptPaths.map(path => {
        const script = new NexaScript(path, basePath, null, detectedHandler);
        return script.load();
      });
      return Promise.all(promises);
    }
  }

  /**
   * Get NexaUi base path secara otomatis
   * @private
   * @static
   * @returns {string|null} Base path ke folder NexaUi atau null jika tidak ditemukan
   */
  static getNexaUiBasePath() {
    try {
      // Method 1: Dari current module (NexaScript.js)
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
   * Load multiple JavaScript modules dari folder NexaUi secara otomatis
   * @static
   * @param {Array<string>} scriptPaths - Array of script paths relatif dari folder NexaUi (contoh: ['js/helper.js', 'Dom/utils.js'])
   * @param {boolean} asScriptTag - Jika true, gunakan script tag (non-module). Jika false, gunakan dynamic import (ES module)
   * @param {string} handlerName - Handler name untuk tracking (opsional, auto-detect jika tidak diisi)
   * @returns {Promise<Module[]|void[]>} Promise yang resolve ketika semua script sudah dimuat
   * @example
   * // Load ES modules dari folder NexaUi
   * const modules = await NXUI.NexaScript.NexaUi(['js/helper.js', 'Dom/utils.js']);
   * 
   * // Load classic scripts dari folder NexaUi
   * await NXUI.NexaScript.NexaUi(['js/legacy.js'], true);
   */
  static async NexaUi(scriptPaths, asScriptTag = false, handlerName = null) {
    const nexaUiBasePath = NexaScript.getNexaUiBasePath();
    
    if (!nexaUiBasePath) {
      throw new Error('NexaUi base path not found');
    }

    // Auto-detect handler name jika tidak diisi
    const detectedHandler = handlerName || NexaScript.getCurrentHandler();

    if (asScriptTag) {
      // Load as classic scripts (non-module)
      const promises = scriptPaths.map(path => {
        const cleanPath = path.startsWith('/') ? path.slice(1) : path;
        const script = new NexaScript(cleanPath, nexaUiBasePath, null, detectedHandler);
        return script.loadAsScript();
      });
      return Promise.all(promises);
    } else {
      // Load as ES modules (default)
      const promises = scriptPaths.map(path => {
        const cleanPath = path.startsWith('/') ? path.slice(1) : path;
        const script = new NexaScript(cleanPath, nexaUiBasePath, null, detectedHandler);
        return script.load();
      });
      return Promise.all(promises);
    }
  }

  /**
   * Path relatif ke folder **`assets/modules`** (bukan folder `templates/` route).
   * Untuk perilaku sama seperti `NexaStylesheet.Dom(['modules/foo.css'])` dari file route,
   * pakai **`NexaScript.Dom(['modules/foo.js'], undefined, true)`** — basis URL = folder pemanggil;
   * argumen ketiga **`true`** = sisipkan `<script src>` di `head` (terlihat di inspector).
   * @param {Array<string>} scriptPaths
   * @param {boolean} [asScriptTag=false]
   * @param {string|null} [handlerName=null]
   * @returns {Promise<Module[]|void[]>}
   */
  static async modules(scriptPaths, asScriptTag = false, handlerName = null) {
    return NexaScript.NexaUi(scriptPaths, asScriptTag, handlerName);
  }

  /**
   * Alias untuk Dom (backward compatibility)
   * @static
   * @deprecated Gunakan Dom() sebagai gantinya
   */
  static async loadMultiple(scriptPaths, isAbsoluteOrBasePath = undefined, asScriptTag = false) {
    return NexaScript.Dom(scriptPaths, isAbsoluteOrBasePath, asScriptTag);
  }
}

