// Conditional import untuk expo-file-system (legacy API untuk kompatibilitas)
let FileSystem = null;
let FileSystemLoaded = false;

function loadFileSystem() {
  if (FileSystemLoaded) return FileSystem;
  
  try {
    if (typeof require !== 'undefined') {
      try {
        // Prioritaskan legacy API untuk Expo v54+ (menghindari deprecation warning)
        // Legacy API masih mendukung semua method yang kita butuhkan: getInfoAsync, makeDirectoryAsync, dll
        try {
          FileSystem = require('expo-file-system/legacy');
          FileSystemLoaded = true;
          // console.log('✅ expo-file-system/legacy loaded (Expo v54+)');
          return FileSystem;
        } catch (legacyError) {
          // Fallback to regular import (untuk Expo versi lama yang belum support legacy)
          FileSystem = require('expo-file-system');
          FileSystemLoaded = true;
          // console.log('✅ expo-file-system loaded (legacy fallback)');
          return FileSystem;
        }
      } catch (error) {
        console.warn('⚠️ expo-file-system not available:', error.message);
        return null;
      }
    }
  } catch (error) {
    console.warn('⚠️ Failed to load expo-file-system:', error.message);
    return null;
  }
  
  return null;
}

// Try to load FileSystem immediately
FileSystem = loadFileSystem();

/**
 * NexaDbJson - JSON File Storage Manager
 * Menyimpan data JSON langsung ke file di perangkat menggunakan expo-file-system
 * Tidak bergantung pada database, data disimpan sebagai file JSON
 */
export const NexaDbJson = {
  basePath: null, // Base directory untuk menyimpan file
  stores: new Set(), // Track all requested stores
  observers: new Map(), // Real-time observers
  pollingIntervals: new Map(), // Polling intervals
  
  // ============================================
  // 🔐 ENCRYPTION CONFIGURATION (AUTO ENABLED)
  // ============================================
  encryptionEnabled: true, // 🔐 ENABLED BY DEFAULT - Auto encrypt semua data
  encryptionSecretKey: "nexaui2025", // Default secret key
  encryptor: null, // NexaEncrypt instance (will be auto-loaded)
  encryptedFields: new Set(), // Track which fields are encrypted
  fieldsToSkip: new Set(["id", "createdAt", "updatedAt"]), // Fields that won't be encrypted
  encryptionInitialized: false, // Flag untuk track initialization
  autoMode: false, // Auto-create stores mode

  /**
   * Helper: Get base directory path
   * Auto-create directory jika belum ada
   */
  _getBasePath: async function () {
    if (!this.basePath) {
      // Auto-initialize dengan default path
      this.basePath = "NexaDbJson";
      await this._ensureBaseDirectory();
    }
    return this.basePath;
  },

  /**
   * Helper: Ensure base directory exists
   */
  _ensureBaseDirectory: async function () {
    if (!FileSystem) {
      FileSystem = loadFileSystem();
    }
    
    if (!FileSystem) {
      throw new Error("expo-file-system is not available. Please install: expo install expo-file-system");
    }

    try {
      // Get document directory path
      const documentPath = FileSystem.documentDirectory;
      const baseDir = `${documentPath}${this.basePath}/`;
      
      // Check if directory exists
      const dirInfo = await FileSystem.getInfoAsync(baseDir);
      
      if (!dirInfo.exists) {
        // Create directory
        await FileSystem.makeDirectoryAsync(baseDir, { intermediates: true });
      }
      
      return baseDir;
    } catch (error) {
      console.error("Error ensuring base directory:", error);
      throw error;
    }
  },

  /**
   * Helper: Get file path for store
   * Format: {basePath}/{storeName}.json
   */
  _getStoreFilePath: async function (storeName) {
    const baseDir = await this._ensureBaseDirectory();
    return `${baseDir}${storeName}.json`;
  },

  /**
   * Helper: Read JSON file
   */
  _readStoreFile: async function (storeName) {
    try {
      const filePath = await this._getStoreFilePath(storeName);
      const fileInfo = await FileSystem.getInfoAsync(filePath);
      
      if (!fileInfo.exists) {
        return {}; // Return empty object jika file belum ada
      }
      
      const fileContent = await FileSystem.readAsStringAsync(filePath);
      return JSON.parse(fileContent || '{}');
    } catch (error) {
      console.warn(`Error reading store file "${storeName}":`, error);
      return {}; // Return empty object jika error
    }
  },

  /**
   * Helper: Write JSON file
   */
  _writeStoreFile: async function (storeName, data) {
    try {
      const filePath = await this._getStoreFilePath(storeName);
      const jsonString = JSON.stringify(data, null, 2);
      await FileSystem.writeAsStringAsync(filePath, jsonString);
      return true;
    } catch (error) {
      console.error(`Error writing store file "${storeName}":`, error);
      throw error;
    }
  },

  /**
   * Auto-initialize encryption (called automatically)
   */
  _autoInitEncryption: async function () {
    if (this.encryptionInitialized) {
      return true;
    }

    if (!this.encryptionEnabled) {
      return false;
    }

    try {
      let NexaEncrypt;
      try {
        const module = await import("./NexaEncrypt.js");
        NexaEncrypt = module.default || module.NexaEncrypt;
      } catch (importError) {
        try {
          const module = await import("../NexaEncrypt.js");
          NexaEncrypt = module.default || module.NexaEncrypt;
        } catch (importError2) {
          console.warn("⚠️ NexaEncrypt tidak ditemukan. Encryption disabled. Data akan tersimpan tanpa enkripsi.");
          this.encryptionEnabled = false;
          this.encryptionInitialized = true;
          return false;
        }
      }

      this.encryptor = new NexaEncrypt(this.encryptionSecretKey);
      this.encryptionInitialized = true;
      
      return true;
    } catch (error) {
      console.warn("⚠️ Failed to initialize encryption:", error);
      this.encryptionEnabled = false;
      this.encryptionInitialized = true;
      return false;
    }
  },

  /**
   * Setup enkripsi (OPTIONAL - hanya jika ingin custom key)
   */
  setupEncryption: async function (secretKey = "nexaui2025", fieldsToSkip = ["id", "createdAt", "updatedAt"]) {
    try {
      let NexaEncrypt;
      try {
        NexaEncrypt = (await import("./NexaEncrypt.js")).default || (await import("./NexaEncrypt.js")).NexaEncrypt;
      } catch (importError) {
        try {
          NexaEncrypt = (await import("../NexaEncrypt.js")).default || (await import("../NexaEncrypt.js")).NexaEncrypt;
        } catch (importError2) {
          console.warn("⚠️ NexaEncrypt tidak ditemukan. Pastikan NexaEncrypt.js tersedia.");
          this.encryptionEnabled = false;
          return false;
        }
      }

      this.encryptor = new NexaEncrypt(secretKey);
      this.encryptionSecretKey = secretKey;
      this.encryptionEnabled = true;
      this.fieldsToSkip = new Set(fieldsToSkip);
      this.encryptionInitialized = true;

      console.log("✅ Encryption enabled dengan secret key:", secretKey);
      return true;
    } catch (error) {
      console.error("❌ Error setting up encryption:", error);
      this.encryptionEnabled = false;
      return false;
    }
  },

  /**
   * Disable enkripsi
   */
  disableEncryption: function () {
    this.encryptionEnabled = false;
    this.encryptor = null;
    this.encryptionSecretKey = null;
    this.encryptedFields.clear();
    console.log("🔓 Encryption disabled");
  },

  /**
   * Encrypt data fields (kecuali metadata)
   */
  encryptDataFields: async function (data) {
    if (!this.encryptionInitialized) {
      await this._autoInitEncryption();
    }

    if (!this.encryptionEnabled || !this.encryptor) {
      return data;
    }

    try {
      const encrypted = JSON.parse(JSON.stringify(data));

      for (const key in encrypted) {
        if (!this.fieldsToSkip.has(key) && encrypted[key] !== null && encrypted[key] !== undefined) {
          try {
            if (typeof encrypted[key] === "object") {
              encrypted[key] = this.encryptor.obfuscateJson(encrypted[key]);
            } else {
              encrypted[key] = this.encryptor.obfuscateJson(String(encrypted[key]));
            }
            this.encryptedFields.add(key);
          } catch (error) {
            console.warn(`⚠️ Failed to encrypt field "${key}":`, error);
          }
        }
      }

      encrypted._encrypted = true;
      return encrypted;
    } catch (error) {
      console.error("❌ Encryption error:", error);
      return data;
    }
  },

  /**
   * Decrypt data fields
   */
  decryptDataFields: async function (data) {
    if (!this.encryptionInitialized) {
      await this._autoInitEncryption();
    }

    if (!this.encryptionEnabled || !this.encryptor) {
      return data;
    }

    // Jika tidak ada flag _encrypted, data tidak terenkripsi
    if (!data || !data._encrypted) {
      return data;
    }

    try {
      const decrypted = JSON.parse(JSON.stringify(data));

      // Decrypt semua field kecuali yang ada di fieldsToSkip
      // Jangan hanya decrypt field yang ada di encryptedFields (karena Set global bisa tidak akurat)
      for (const key in decrypted) {
        // Skip field yang tidak perlu didekripsi
        if (this.fieldsToSkip.has(key) || key === '_encrypted') {
          continue;
        }
        
        // Decrypt field jika tidak null/undefined
        if (decrypted[key] !== null && decrypted[key] !== undefined) {
          try {
            decrypted[key] = this.encryptor.deobfuscateJson(decrypted[key]);
          } catch (error) {
            // Jika dekripsi gagal, mungkin field tidak terenkripsi atau format berbeda
            // Coba parse sebagai JSON jika memungkinkan
            try {
              if (typeof decrypted[key] === 'string' && decrypted[key].trim().startsWith('{')) {
                decrypted[key] = JSON.parse(decrypted[key]);
              }
            } catch (parseError) {
              // Jika parse juga gagal, biarkan value as is
              console.warn(`⚠️ [NexaDBJson] Failed to decrypt field "${key}", keeping original value`);
            }
          }
        }
      }

      delete decrypted._encrypted;
      return decrypted;
    } catch (error) {
      console.error("❌ [NexaDBJson] Decryption error:", error.message);
      return data;
    }
  },

  /**
   * Initialize storage
   */
  init: function (basePath = "NexaDbJson") {
    return new Promise(async (resolve, reject) => {
      try {
        this.basePath = basePath;
        this.autoMode = true;
        
        if (!this.stores) {
          this.stores = new Set();
        }

        await this._ensureBaseDirectory();

        resolve({
          set: (storeName, data) => this.set(storeName, data),
          get: (storeName, key) => this.get(storeName, key),
          getAll: (storeName) => this.getAll(storeName),
          delete: (storeName, key) => this.delete(storeName, key),
          updateFields: (storeName, id, fieldUpdates) => this.updateFields(storeName, id, fieldUpdates),
          updateField: (storeName, id, fieldName, fieldValue) => this.updateField(storeName, id, fieldName, fieldValue),
          search: (storeName, query, fields = []) => this.search(storeName, query, fields),
          getLatest: (storeName, count = 1) => this.getLatest(storeName, count),
          getOldest: (storeName, count = 1) => this.getOldest(storeName, count),
          getInfo: () => this.getInfo(),
          hasStore: (storeName) => this.hasStore(storeName),
          listStores: () => this.listStores(),
          resetDatabase: () => this.resetDatabase(),
        });
      } catch (error) {
        reject(error);
      }
    });
  },

  /**
   * ============================================
   * 📝 SET - Menyimpan Data (Auto-initialize)
   * ============================================
   */
  set: function (storeName, data) {
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum di-initialize
        if (!this.basePath) {
          this.basePath = "NexaDbJson";
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._ensureBaseDirectory();
        }

        // Validate data has id - data.id digunakan sebagai key (konsisten dengan NexaDBLite.js)
        if (!data || !data.id) {
          reject(new Error("Data must have an 'id' field"));
          return;
        }

        // Tambahkan timestamp otomatis jika belum ada
        const dataWithTimestamp = {
          ...data,
          createdAt: data.createdAt || new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };

        // Track store
        this.stores.add(storeName);

        // Read existing data
        const storeData = await this._readStoreFile(storeName);
        
        // 🔐 Encrypt data jika encryption enabled (OTOMATIS)
        let dataToSave;
        try {
          dataToSave = await this.encryptDataFields(dataWithTimestamp);
        } catch (error) {
          console.error("Encryption error:", error);
          // Fallback: save without encryption
          dataToSave = dataWithTimestamp;
        }

        // Update store data - data.id digunakan sebagai key (konsisten dengan NexaDBLite.js)
        storeData[data.id] = dataToSave;

        // Write back to file
        await this._writeStoreFile(storeName, storeData);

        // Notify observers dengan data yang sudah di-decrypt
        const decryptedData = await this.decryptDataFields(dataToSave);
        this.notifyObservers(storeName, decryptedData, "update");

        resolve(data.id);
      } catch (error) {
        console.error(`Error setting data in store "${storeName}":`, error);
        reject(error);
      }
    });
  },

  /**
   * ============================================
   * 📖 GET - Mengambil Data (Auto-initialize)
   * ============================================
   * Penggunaan mudah: const user = await NexaDbJson.get("users", "1")
   */
  get: function (storeName, key) {
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum di-initialize
        if (!this.basePath) {
          this.basePath = "NexaDbJson";
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._ensureBaseDirectory();
        }

        // Validate key - jika key tidak ada atau undefined, return null
        if (key === undefined || key === null || key === "") {
          console.warn(`Key is ${key === undefined ? 'undefined' : key === null ? 'null' : 'empty'} for store "${storeName}", returning null`);
          resolve(null);
          return;
        }

        // Track store
        this.stores.add(storeName);

        // Read store data
        const storeData = await this._readStoreFile(storeName);
        const encryptedData = storeData[key];

        if (!encryptedData) {
          resolve(null);
          return;
        }

        try {
          // 🔐 Decrypt data jika encryption enabled (OTOMATIS)
          if (encryptedData) {
            try {
              // Cek apakah data terenkripsi (punya flag _encrypted)
              if (encryptedData._encrypted) {
                const decryptedData = await this.decryptDataFields(encryptedData);
                resolve(decryptedData);
              } else {
                // Data tidak terenkripsi, return langsung
                resolve(encryptedData);
              }
            } catch (decryptError) {
              console.warn(`⚠️ [NexaDBJson] Decryption error for store "${storeName}":`, decryptError.message);
              // Jika dekripsi gagal, coba return data as is (mungkin tidak terenkripsi)
              resolve(encryptedData);
            }
          } else {
            resolve(null);
          }
        } catch (parseError) {
          console.warn(`⚠️ [NexaDBJson] Error parsing data from store "${storeName}":`, parseError.message);
          resolve(null);
        }
      } catch (error) {
        // Handle error gracefully - return null instead of reject
        console.warn(`Error accessing store "${storeName}":`, error.message);
        resolve(null);
      }
    });
  },

  /**
   * ============================================
   * 📚 GETALL - Mengambil Semua Data (Auto-initialize)
   * ============================================
   * Penggunaan mudah: const users = await NexaDbJson.getAll("users")
   */
  getAll: function (storeName) {
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum di-initialize
        if (!this.basePath) {
          this.basePath = "NexaDbJson";
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._ensureBaseDirectory();
        }

        // Track store
        this.stores.add(storeName);

        // Read store data
        const storeData = await this._readStoreFile(storeName);
        
        // Parse and decrypt all items
        const allData = [];
        for (const key in storeData) {
          if (storeData[key]) {
            try {
              const encryptedData = storeData[key];
              // 🔐 Decrypt data jika encryption enabled (OTOMATIS)
              // Cek apakah data terenkripsi (punya flag _encrypted)
              if (encryptedData._encrypted) {
                const decryptedData = await this.decryptDataFields(encryptedData);
                allData.push(decryptedData);
              } else {
                // Data tidak terenkripsi, push langsung
                allData.push(encryptedData);
              }
            } catch (error) {
              console.warn(`⚠️ [NexaDBJson] Error parsing/decrypting item from key "${key}":`, error.message);
            }
          }
        }

        resolve({ data: allData });
      } catch (error) {
        console.error(`Error accessing store "${storeName}":`, error);
        resolve({ data: [] }); // Return empty instead of reject for compatibility
      }
    });
  },

  /**
   * ============================================
   * 🗑️ DELETE - Menghapus Data (Auto-initialize)
   * ============================================
   * Penggunaan mudah: await NexaDbJson.delete("users", "1")
   */
  delete: function (storeName, key) {
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum di-initialize
        if (!this.basePath) {
          this.basePath = "NexaDbJson";
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._ensureBaseDirectory();
        }

        // Validate key
        if (key === undefined || key === null || key === "") {
          resolve(false);
          return;
        }

        // Track store
        this.stores.add(storeName);

        // Read store data
        const storeData = await this._readStoreFile(storeName);
        
        if (!storeData[key]) {
          resolve(false);
          return;
        }

        // Delete key
        delete storeData[key];

        // Write back to file
        await this._writeStoreFile(storeName, storeData);

        // Notify observers
        this.notifyObservers(storeName, { id: key }, "delete");
        resolve(true);
      } catch (error) {
        console.error(`Error deleting from store "${storeName}":`, error);
        resolve(false);
      }
    });
  },

  /**
   * Shortcut: Delete data (alias untuk delete)
   */
  del: function (storeName, key) {
    return this.delete(storeName, key);
  },

  /**
   * ============================================
   * 🔔 OBSERVER PATTERN
   * ============================================
   */
  notifyObservers: function (storeName, data, changeType = "update") {
    if (!this.observers.has(storeName)) return;

    this.observers.get(storeName).forEach((observer, watchId) => {
      try {
        observer.callback({
          storeName,
          data,
          changeType,
          timestamp: new Date().toISOString(),
          watchId,
        });
      } catch (error) {
        console.error(`Observer error for ${storeName}:`, error);
      }
    });
  },

  watch: function (storeName, callback, options = {}) {
    const watchId = `${storeName}_${Date.now()}_${Math.random()}`;

    if (!this.observers.has(storeName)) {
      this.observers.set(storeName, new Map());
    }

    this.observers.get(storeName).set(watchId, {
      callback,
      options: {
        immediate: false,
        ...options,
      },
    });

    return () => this.unwatch(storeName, watchId);
  },

  unwatch: function (storeName, watchId) {
    if (this.observers.has(storeName)) {
      this.observers.get(storeName).delete(watchId);

      if (this.observers.get(storeName).size === 0) {
        this.observers.delete(storeName);
      }
    }
  },

  /**
   * ============================================
   * 🔧 UTILITY METHODS
   * ============================================
   */
  getInfo: function () {
    return {
      basePath: this.basePath,
      isInitialized: !!this.basePath,
      availableStores: Array.from(this.stores),
      requestedStores: Array.from(this.stores),
    };
  },

  hasStore: function (storeName) {
    return this.stores.has(storeName);
  },

  listStores: function () {
    return Array.from(this.stores);
  },

  resetDatabase: function () {
    return new Promise(async (resolve, reject) => {
      try {
        if (!this.basePath) {
          resolve();
          return;
        }

        const baseDir = await this._ensureBaseDirectory();
        const storeNames = Array.from(this.stores);

        // Delete all store files
        for (const storeName of storeNames) {
          try {
            const filePath = await this._getStoreFilePath(storeName);
            const fileInfo = await FileSystem.getInfoAsync(filePath);
            if (fileInfo.exists) {
              await FileSystem.deleteAsync(filePath, { idempotent: true });
            }
          } catch (error) {
            console.warn(`Error deleting store file "${storeName}":`, error);
          }
        }

        this.stores.clear();
        this.basePath = null;

        resolve();
      } catch (error) {
        reject(error);
      }
    });
  },

  /**
   * ============================================
   * 📊 DATA ACCESS METHODS
   * ============================================
   */
  getLatest: function (storeName, count = 1) {
    return new Promise(async (resolve, reject) => {
      try {
        const allDataResult = await this.getAll(storeName);
        const allData = allDataResult.data || [];

        if (allData.length === 0) {
          resolve({
            data: count === 1 ? null : [],
            total: 0,
            requested: count,
          });
          return;
        }

        // Sort berdasarkan updatedAt atau createdAt (terbaru dulu)
        const sortedData = allData.sort((a, b) => {
          const dateA = new Date(a.updatedAt || a.createdAt || 0);
          const dateB = new Date(b.updatedAt || b.createdAt || 0);
          return dateB - dateA;
        });

        const result = count === 1 ? sortedData[0] : sortedData.slice(0, count);
        resolve({
          data: result,
          total: allData.length,
          requested: count,
        });
      } catch (error) {
        console.error(`Error getting latest data from store "${storeName}":`, error);
        resolve({
          data: count === 1 ? null : [],
          total: 0,
          requested: count,
        });
      }
    });
  },

  getOldest: function (storeName, count = 1) {
    return new Promise(async (resolve, reject) => {
      try {
        const allDataResult = await this.getAll(storeName);
        const allData = allDataResult.data || [];

        if (allData.length === 0) {
          resolve({
            data: count === 1 ? null : [],
            total: 0,
            requested: count,
          });
          return;
        }

        // Sort berdasarkan updatedAt atau createdAt (terlama dulu)
        const sortedData = allData.sort((a, b) => {
          const dateA = new Date(a.updatedAt || a.createdAt || 0);
          const dateB = new Date(b.updatedAt || b.createdAt || 0);
          return dateA - dateB;
        });

        const result = count === 1 ? sortedData[0] : sortedData.slice(0, count);
        resolve({
          data: result,
          total: allData.length,
          requested: count,
        });
      } catch (error) {
        console.error(`Error getting oldest data from store "${storeName}":`, error);
        resolve({
          data: count === 1 ? null : [],
          total: 0,
          requested: count,
        });
      }
    });
  },

  search: function (storeName, query, fields = []) {
    return new Promise(async (resolve, reject) => {
      try {
        if (!query || query.trim() === "") {
          resolve({ data: [], total: 0, query });
          return;
        }

        const allDataResult = await this.getAll(storeName);
        const allData = allDataResult.data || [];
        const searchTerm = query.toLowerCase();

        const filteredData = allData.filter((item) => {
          if (fields.length === 0) {
            return JSON.stringify(item).toLowerCase().includes(searchTerm);
          }

          return fields.some((field) => {
            const value = item[field];
            if (value === null || value === undefined) return false;
            return String(value).toLowerCase().includes(searchTerm);
          });
        });

        // Sort berdasarkan relevance dan tanggal
        const sortedData = filteredData.sort((a, b) => {
          const dateA = new Date(a.updatedAt || a.createdAt || 0);
          const dateB = new Date(b.updatedAt || b.createdAt || 0);
          return dateB - dateA;
        });

        resolve({
          data: sortedData,
          total: allData.length,
          found: sortedData.length,
          query,
          searchFields: fields,
        });
      } catch (error) {
        console.error(`Error searching in store "${storeName}":`, error);
        resolve({
          data: [],
          total: 0,
          found: 0,
          query,
          searchFields: fields,
        });
      }
    });
  },

  /**
   * ============================================
   * 🔧 PARTIAL UPDATE METHODS
   * ============================================
   */
  updateFields: function (storeName, id, fieldUpdates) {
    return new Promise(async (resolve, reject) => {
      try {
        // Get existing data
        const existingData = await this.get(storeName, id);
        if (!existingData) {
          reject(new Error(`Data with ID "${id}" not found in store "${storeName}"`));
          return;
        }

        // Merge with new fields
        const updatedData = {
          ...existingData,
          ...fieldUpdates,
          updatedAt: new Date().toISOString(),
        };

        // Save updated data
        await this.set(storeName, updatedData);
        resolve(updatedData);
      } catch (error) {
        console.error(`❌ Failed to update fields in store "${storeName}":`, error);
        reject(error);
      }
    });
  },

  updateField: function (storeName, id, fieldName, fieldValue) {
    return this.updateFields(storeName, id, { [fieldName]: fieldValue });
  },
};

// Default export for compatibility
export default NexaDbJson;

