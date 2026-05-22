/**
 * NexaExport - Dynamic Function Export System
 *
 * Provides functionality to export functions to global scope with auto-discovery
 * and dynamic function calling capabilities for modal systems.
 */

/**
 * NexaExport class for managing function exports and dynamic discovery
 */
export class NexaExport {
  constructor() {
    this.exportedFunctions = new Map();
  }

  /**
   * Export functions to target scope (default: window)
   * @param {Object|Window} target - Target object to export to (default: window)
   * @param {Object} exports - Object containing functions to export
   * @returns {Object} - The target object with exported functions
   */
  export(target, exports) {
    const g =
      typeof globalThis !== "undefined"
        ? globalThis
        : typeof window !== "undefined"
          ? window
          : undefined;

    // Satu argumen: objek berisi fungsi → pasang ke global (bukan `null` — typeof null === "object")
    if (
      arguments.length === 1 &&
      target != null &&
      typeof target === "object" &&
      !Array.isArray(target) &&
      !(typeof Window !== "undefined" && target instanceof Window)
    ) {
      exports = target;
      target = g;
    }

    if (!exports || typeof exports !== "object") {
      console.warn("NexaExport: exports parameter must be an object", exports);
      return target;
    }

    if (target == null || typeof target !== "object") {
      console.warn("NexaExport: target must be an object (e.g. globalThis)", target);
      return target;
    }

    Object.keys(exports).forEach((key) => {
      if (typeof exports[key] === "function") {
        this.exportedFunctions.set(key, exports[key]);
        target[key] = exports[key];
        // console.log(`[NexaExport] ✅ Exported: ${key} → ${typeof target[key]}`);
      }
    });

    // console.log(`[NexaExport] Total exported functions: ${this.exportedFunctions.size}`);
    return target;
  }

  /**
   * Get exported function by name
   * @param {string} functionName - Name of the function to retrieve
   * @returns {Function|null} - The function if found, null otherwise
   */
  getFunction(functionName) {
    // Check in stored exported functions first (most reliable)
    if (this.exportedFunctions.has(functionName)) {
      return this.exportedFunctions.get(functionName);
    }

    const g =
      typeof globalThis !== "undefined"
        ? globalThis
        : typeof window !== "undefined"
          ? window
          : undefined;
    if (g && typeof g[functionName] === "function") {
      return g[functionName];
    }

    return null;
  }

  /**
   * Dynamic function lookup for modal system
   * @param {string} functionName - Name of the function to find
   * @returns {Function|null} - The function if found, null otherwise
   */
  getDynamicFunction(functionName) {
    const func = this.getFunction(functionName);
    if (!func) {
      console.error(`Function ${functionName} not found in global scope`);
    }
    return func;
  }

  /**
   * Get all exported functions
   * @returns {Map} - Map of all exported functions
   */
  getExportedFunctions() {
    return new Map(this.exportedFunctions);
  }

  /**
   * Clear all exported functions
   */
  clear() {
    this.exportedFunctions.clear();
  }

  /**
   * Check if function exists
   * @param {string} functionName - Name of the function to check
   * @returns {boolean} - True if function exists, false otherwise
   */
  hasFunction(functionName) {
    return this.getFunction(functionName) !== null;
  }
}

/** Satu instance untuk seluruh aplikasi — di `window` agar aman walau modul dimuat lewat beberapa jalur import (entry vs dynamic). */
let _nexaExportNoWindow = null;

function getNexaExportSingleton() {
  const g =
    typeof globalThis !== "undefined"
      ? globalThis
      : typeof window !== "undefined"
        ? window
        : undefined;
  if (g) {
    if (!g.__nexaExportSingleton) {
      g.__nexaExportSingleton = new NexaExport();
      g.NexaExport = g.__nexaExportSingleton;
    }
    return g.__nexaExportSingleton;
  }
  if (!_nexaExportNoWindow) {
    _nexaExportNoWindow = new NexaExport();
  }
  return _nexaExportNoWindow;
}

/**
 * Static export method for NexaUI integration
 * @param {Object|Window} target - Target object to export to
 * @param {Object} exports - Object containing functions to export
 * @returns {Object} - The target object with exported functions
 */
export function exportToGlobal(target, exports) {
  return getNexaExportSingleton().export(target, exports);
}

/**
 * Dynamic function lookup for modal system
 * @param {string} functionName - Name of the function to find
 * @returns {Function|null} - The function if found, null otherwise
 */
export function getDynamicFunction(functionName) {
  return getNexaExportSingleton().getDynamicFunction(functionName);
}

/**
 * Get the global NexaExport instance
 * @returns {NexaExport} - The global instance
 */
export function getNexaExportInstance() {
  return getNexaExportSingleton();
}

export default NexaExport;
