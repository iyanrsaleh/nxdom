import { IndexedDBManager } from "./IndexDB.js";

export class NexaDb {
  constructor() {
    this.dbName = "NexaStoreDB";
    this.dbVersion = 9; // Incremented version for NexaXlsx stores (xlsxFiles, settings)
    this.db = null; // Not used in React Native, kept for compatibility
    // React Native compatible - get userId from global or default to 0
    this.userData = (typeof global !== 'undefined' && global.NEXA?.userId) || 
                     (typeof window !== 'undefined' && window.NEXA?.userId) || 0;
  }

  /**
   * Get default stores list - single source of truth
   * Cukup tambahkan nama store di sini, dan akan otomatis terbuat
   * @returns {Array<string>} Array of store names
   */
  getDefaultStores() {
    return [
      "nexaStore",
      "bucketsStore",
      "folderStructure",
      "activityLogs",
      "metadata",
      "recycleBin",
      "fileContents",
      "fileSettings",
      "presentations", // Store untuk NexaPptx presentations
      "programFiles", // Store untuk Program Files
      "xlsxFiles", // Store untuk NexaXlsx files
      "settings", // Store untuk NexaXlsx settings
      "userSession",
    ];
  }

  /**
   * Get store configuration for stores that need custom setup (indexes, keyPath, etc)
   * Store yang tidak ada di sini akan dibuat dengan konfigurasi default
   * @param {string} storeName - Name of the store
   * @returns {Object|null} Store configuration or null for default
   */
  getStoreConfig(storeName) {
    const storeConfigs = {
      folderStructure: {
        keyPath: "id",
        autoIncrement: false,
        indexes: [
          { name: "path", keyPath: "path", unique: false },
          { name: "parentPath", keyPath: "parentPath", unique: false },
        ],
      },
      activityLogs: {
        keyPath: "id",
        autoIncrement: true,
        indexes: [
          { name: "timestamp", keyPath: "timestamp", unique: false },
          { name: "action", keyPath: "action", unique: false },
          { name: "path", keyPath: "path", unique: false },
        ],
      },
      metadata: {
        keyPath: "key",
        autoIncrement: false,
        indexes: [],
      },
      recycleBin: {
        keyPath: "id",
        autoIncrement: true,
        indexes: [
          { name: "deletedDate", keyPath: "deletedDate", unique: false },
          { name: "originalPath", keyPath: "originalPath", unique: false },
          { name: "itemType", keyPath: "itemType", unique: false },
        ],
      },
      fileContents: {
        keyPath: "fileId",
        autoIncrement: false,
        indexes: [
          { name: "fileName", keyPath: "fileName", unique: false },
          { name: "fileType", keyPath: "fileType", unique: false },
          { name: "lastModified", keyPath: "lastModified", unique: false },
          { name: "fileNameType", keyPath: ["fileName", "fileType"], unique: true },
        ],
      },
      fileSettings: {
        keyPath: "id",
        autoIncrement: false,
        indexes: [
          { name: "fileName", keyPath: "fileName", unique: false },
          { name: "userData", keyPath: "userData", unique: false },
          { name: "savedAt", keyPath: "savedAt", unique: false },
        ],
      },
      presentations: {
        keyPath: "id",
        autoIncrement: true,
        indexes: [
          { name: "fileName", keyPath: "fileName", unique: false },
          { name: "namePerFile", keyPath: ["fileName", "name"], unique: true },
          { name: "createdAt", keyPath: "createdAt", unique: false },
          { name: "updatedAt", keyPath: "updatedAt", unique: false },
        ],
      },
      programFiles: {
        keyPath: "id",
        autoIncrement: true,
        indexes: [
          { name: "name", keyPath: "name", unique: false },
          { name: "path", keyPath: "path", unique: false },
          { name: "createdAt", keyPath: "createdAt", unique: false },
          { name: "updatedAt", keyPath: "updatedAt", unique: false },
        ],
      },
      xlsxFiles: {
        keyPath: "fileName",
        autoIncrement: false,
        indexes: [
          { name: "fileName", keyPath: "fileName", unique: true },
          { name: "lastModified", keyPath: "lastModified", unique: false },
        ],
      },
      settings: {
        keyPath: "id",
        autoIncrement: false,
        indexes: [
          { name: "id", keyPath: "id", unique: true },
        ],
      },
    };

    return storeConfigs[storeName] || null;
  }

  /**
   * Instance method to initialize NXUI.ref using this instance's properties
   * @param {Array} stores - Array of store names (optional, defaults to all required stores)
   * @returns {Promise<Object>} - Returns the initialized NXUI.ref
   */
  async Ref(stores = null) {
    try {
      // Check if NXUI is available
      if (typeof NXUI === 'undefined' || !NXUI) {
        throw new Error('NXUI is not available. Make sure nexa-ui.js is loaded first.');
      }
      
      // If NXUI.ref already exists, return it
      if (NXUI.ref) {
        return NXUI.ref;
      }

      // Default stores if not provided - menggunakan getDefaultStores() sebagai single source of truth
      const defaultStores = this.getDefaultStores();

      const storesToInit = stores || defaultStores;
      const nexaUI = NexaUI();

      // Initialize NXUI.ref using instance properties
      NXUI.ref = await nexaUI
        .Storage()
        .indexedDB.init(this.dbName, this.dbVersion, storesToInit);
      return NXUI.ref;
    } catch (error) {
      console.error("Failed to initialize NXUI.ref:", error);
      throw error;
    }
  }

  // React Native compatible - no need to initialize database
  async initDatabase() {
    return new Promise((resolve) => {
      // In React Native, AsyncStorage doesn't need initialization
      // Just mark as initialized
      this.db = { initialized: true }; // Dummy object for compatibility
      resolve(this.db);
    });
  }
  /**
   * Ensure all required object stores are ready (React Native compatible)
   */
  async uninstall() {
    try {
      // Use IndexedDBManager to reset database
      await IndexedDBManager.resetDatabase();
      this.db = null;
    } catch (error) {
      console.error("Gagal menghapus database:", error);
      throw error;
    }
  }

  // React Native compatible - stores are created automatically
  async install() {
    try {
      // In React Native, stores are created automatically when used
      // Just ensure IndexedDBManager is initialized
      if (!IndexedDBManager.dbName) {
        await IndexedDBManager.init(this.dbName, this.dbVersion, this.getDefaultStores());
      } else {
        // Add stores if not already tracked
        const requiredStores = this.getDefaultStores();
        await IndexedDBManager.addStores(requiredStores);
      }

      return true;
    } catch (error) {
      console.error("Install error:", error);
      throw error;
    }
  }

  /**
   * Force database upgrade (React Native compatible - no-op)
   */
  async forceDatabaseUpgrade() {
    // In React Native, stores are created automatically
    // Just increment version for tracking
    this.dbVersion = this.dbVersion + 1;
    return Promise.resolve({ version: this.dbVersion });
  }

  /**
   * Create object stores (React Native compatible - no-op, stores created automatically)
   * Store yang ada di getDefaultStores() akan otomatis dibuat saat digunakan
   * Konfigurasi khusus (indexes, keyPath) diambil dari getStoreConfig() untuk reference saja
   */
  createObjectStores(oldVersion = 0) {
    // In React Native, stores are created automatically when first used
    // This method is kept for compatibility but does nothing
    const defaultStores = this.getDefaultStores();
    // Stores will be created automatically via IndexedDBManager
    return defaultStores;
  }

  // Method untuk kompatibilitas dengan NexaSystem (React Native compatible)
  async getActivityLogs(limit = 5000) {
    try {
      const { IndexedDBManager } = await import("./IndexDB.js");
      if (!IndexedDBManager.dbName) return [];

      const result = await IndexedDBManager.getAll("activityLogs");
      const logs = result.data || [];
      // Sort by timestamp descending (newest first) and limit
      const sortedLogs = logs
        .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
        .slice(0, limit);
      return sortedLogs;
    } catch (error) {
      console.error("Error getting activity logs:", error);
      return [];
    }
  }

  async getAllFileContents() {
    try {
      const { IndexedDBManager } = await import("./IndexDB.js");
      if (!IndexedDBManager.dbName) return [];

      const result = await IndexedDBManager.getAll("fileContents");
      return result.data || [];
    } catch (error) {
      console.error("Error getting file contents:", error);
      return [];
    }
  }

  async getAllFileSettings() {
    try {
      const { IndexedDBManager } = await import("./IndexDB.js");
      if (!IndexedDBManager.dbName) return [];

      const result = await IndexedDBManager.getAll("fileSettings");
      return result.data || [];
    } catch (error) {
      console.error("Error getting file settings:", error);
      return [];
    }
  }

  async getRecycleBinItems() {
    try {
      const { IndexedDBManager } = await import("./IndexDB.js");
      if (!IndexedDBManager.dbName) return [];

      const result = await IndexedDBManager.getAll("recycleBin");
      return result.data || [];
    } catch (error) {
      console.error("Error getting recycle bin items:", error);
      return [];
    }
  }
}
