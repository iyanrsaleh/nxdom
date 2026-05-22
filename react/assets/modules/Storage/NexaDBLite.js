/**
 * ⚠️ PENTING: NexaDBLite untuk Native Platform SAJA
 * 
 * NexaDBLite menggunakan SQLite yang memerlukan WebAssembly (WASM).
 * SQLite TIDAK tersedia di web platform karena bundler (Metro/Webpack) 
 * tidak bisa resolve WASM module dengan baik.
 * 
 * SOLUSI UNTUK WEB:
 * 1. Gunakan NexaDBJson atau IndexDB untuk web platform
 * 2. Atau tambahkan konfigurasi Metro untuk exclude expo-sqlite di web:
 * 
 *    // metro.config.js
 *    module.exports = {
 *      resolver: {
 *        blockList: Platform.OS === 'web' ? [/expo-sqlite/] : [],
 *      },
 *    };
 * 
 * 3. Atau gunakan conditional import:
 *    const isWeb = typeof window !== 'undefined';
 *    const storage = isWeb ? NexaDBJson : NexaDBLite;
 */

// Conditional import untuk SQLite - hanya untuk native platforms
// Load SQLite hanya untuk native platform (TIDAK untuk web)
let SQLite = null;
let SQLiteLoaded = false;
let isWebPlatform = false;
// Mode maintenance: matikan semua operasi NexaDBLite sementara.
const NEXADBLITE_DISABLED = true;

// Helper untuk deteksi platform web (dipanggil saat runtime, bukan saat module load)
function detectWebPlatform() {
  try {
    // Cek menggunakan Platform dari React Native (lebih akurat)
    try {
      const { Platform } = require('react-native');
      return Platform.OS === 'web';
    } catch (e) {
      // Fallback: cek window/document (hanya untuk web browser, bukan React Native)
      // Di React Native, window mungkin ada tapi bukan web platform
      if (typeof window !== 'undefined' && typeof document !== 'undefined' && typeof navigator !== 'undefined') {
        // Cek lebih spesifik: apakah ini benar-benar web browser
        // React Native memiliki navigator.product === 'ReactNative'
        const isReactNative = typeof navigator.product === 'string' && navigator.product === 'ReactNative';
        // Hanya anggap web jika BUKAN React Native DAN ada window.location
        return !isReactNative && (typeof window.location !== 'undefined');
      }
      return false;
    }
  } catch (e) {
    // Jika semua cek gagal, anggap bukan web (untuk memastikan native tetap bekerja)
    return false;
  }
}

// Deteksi platform web saat module load (untuk early check)
// Tapi akan di-update saat runtime jika perlu
isWebPlatform = detectWebPlatform();

function loadSQLite() {
  if (NEXADBLITE_DISABLED) {
    return null;
  }
  // Update deteksi platform web saat runtime
  const currentIsWeb = detectWebPlatform();
  
  // Jangan load SQLite untuk web platform (akan error karena WASM)
  if (currentIsWeb) {
    console.warn('⚠️ [NexaDBLite] SQLite tidak tersedia di web platform. Gunakan NexaDBJson atau IndexDB untuk web.');
    return null;
  }
  
  if (SQLiteLoaded) return SQLite;
  
  try {
    if (typeof require !== 'undefined') {
      // Try expo-sqlite first (recommended for Expo)
      // Langsung require untuk native platform (Android/iOS) - tidak pakai string literal dinamis
      try {
        SQLite = require('expo-sqlite');
        SQLiteLoaded = true;
        // console.log('✅ expo-sqlite loaded');
        return SQLite;
      } catch (expoError) {
        // Fallback to react-native-sqlite-2
        try {
          const sqlite2 = require('react-native-sqlite-2');
          SQLite = sqlite2.default || sqlite2;
          SQLiteLoaded = true;
          console.log('✅ react-native-sqlite-2 loaded');
          return SQLite;
        } catch (sqlite2Error) {
          // Hanya log warning jika bukan web
          if (!currentIsWeb) {
            console.warn('⚠️ [NexaDBLite] SQLite not available. expo-sqlite error:', expoError.message);
            console.warn('⚠️ [NexaDBLite] react-native-sqlite-2 error:', sqlite2Error.message);
          }
          return null;
        }
      }
    }
  } catch (error) {
    // Hanya log warning jika bukan web
    if (!currentIsWeb) {
      console.warn('⚠️ [NexaDBLite] Failed to load SQLite:', error.message);
    }
    return null;
  }
  
  return null;
}

// Try to load SQLite immediately (hanya jika bukan web)
// Untuk native platform (Android/iOS), SQLite akan di-load
// Update isWebPlatform saat runtime
isWebPlatform = detectWebPlatform();
if (!isWebPlatform) {
  SQLite = loadSQLite();
}

/**
 * NexaDBLite - SQLite Database Manager dengan auto-initialization
 * Menggunakan SQLite sebagai backend storage dengan enkripsi otomatis
 * Implementasi murni SQLite untuk performa optimal
 * 
 * ⚠️ CATATAN PENTING UNTUK WEB:
 * NexaDBLite TIDAK tersedia di web platform karena SQLite memerlukan WebAssembly (WASM)
 * yang tidak didukung dengan baik di web bundler (Metro/Webpack).
 * 
 * Untuk web platform, gunakan:
 * - NexaDBJson (file-based storage) - Recommended untuk web
 * - IndexDB (browser IndexedDB) - Alternative untuk web
 * 
 * Contoh penggunaan conditional:
 * ```javascript
 * import { NexaDBLite, NexaDBJson, IndexDB } from 'NexaUI';
 * 
 * const isWeb = typeof window !== 'undefined';
 * const storage = isWeb ? NexaDBJson : NexaDBLite;
 * 
 * await storage.set('users', { id: '1', name: 'John' });
 * ```
 */
// Log removed for cleaner console output

export const NexaDBLite = {
  db: null,
  dbName: null,
  version: null,
  stores: new Set(),
  observers: new Map(),
  pollingIntervals: new Map(),
  broadcastChannel: null,
  _openingPromise: null, // Track ongoing database opening to prevent concurrent opens
  _ensuredTables: new Set(), // Cache untuk table yang sudah dibuat (optimasi performa)
  _operationCount: 0, // Track jumlah operasi yang sedang berjalan
  _closing: false, // Flag untuk mencegah concurrent close operations
  
  // ============================================
  // 🔐 ENCRYPTION CONFIGURATION (AUTO ENABLED)
  // ============================================
  encryptionEnabled: true,
  encryptionSecretKey: "nexaui2025",
  encryptor: null,
  encryptedFields: new Set(),
  fieldsToSkip: new Set(["id", "createdAt", "updatedAt"]),
  encryptionInitialized: false,

  /**
   * Get default stores list - single source of truth
   * Cukup tambahkan nama store di sini, dan akan otomatis terbuat
   * Hanya store yang ada di daftar ini yang akan dibuat tabelnya
   * @returns {Array<string>} Array of store names
   */
  getDefaultStores: function () {
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
      "programFiles", // Store untuk Program Files (perbaikan typo: proogramFiles -> programFiles)
      "xlsxFiles", // Store untuk NexaXlsx files
      "settings", // Store untuk NexaXlsx settings
      "userSessions", // Store untuk user sessions (plural - digunakan di masuk.js, Uid.js)
      "userSession", // Store untuk user session (singular - untuk kompatibilitas)
    ];
  },

  /**
   * Close database connection dengan benar
   * Hanya tutup jika tidak ada operasi yang sedang berjalan
   */
  _closeDatabase: async function () {
    // Jangan tutup jika ada operasi yang sedang berjalan
    if (this._operationCount > 0) {
      console.warn(`⚠️ [NexaDBLite] Cannot close database: ${this._operationCount} operations still running`);
      return;
    }
    
    // Jangan tutup jika sedang dalam proses closing
    if (this._closing) {
      return;
    }
    
    this._closing = true;
    
    try {
      if (this.db && typeof this.db.closeAsync === 'function') {
        await this.db.closeAsync();
        console.log('✅ [NexaDBLite] Database closed successfully');
      }
    } catch (error) {
      console.warn('⚠️ [NexaDBLite] Error closing database:', error.message);
    } finally {
      this.db = null;
      this._openingPromise = null;
      this._ensuredTables.clear();
      this._closing = false;
    }
  },

  /**
   * Get database connection
   */
  _getDatabase: async function () {
    if (NEXADBLITE_DISABLED) {
      return null;
    }
    try {
      // Update deteksi platform web saat runtime (untuk memastikan akurat)
      const currentIsWeb = detectWebPlatform();
      
      // Cek apakah ini web platform - hanya block jika benar-benar web
      // Pastikan Android/iOS tetap bisa load SQLite
      if (currentIsWeb) {
        // Matikan error spam di web: NexaDBLite memang tidak didukung.
        // Caller bisa fallback ke NexaDBJson/IndexDB.
        return null;
      }
      
      // Pastikan dbName sudah di-set
      if (!this.dbName) {
        this.dbName = "NexaDBLite";
        this.version = 1;
        this.autoMode = true;
        if (!this.stores) {
          this.stores = new Set();
        }
      }

      // Load SQLite jika belum
      if (!SQLite) {
        SQLite = loadSQLite();
      }

      if (!SQLite) {
        throw new Error("SQLite is not available. Make sure expo-sqlite is installed. Note: SQLite tidak tersedia di web platform.");
      }

      // Jika database sudah terbuka, cek apakah masih valid
      // Hapus verifikasi query yang menyebabkan NullPointerException
      if (this.db && !this._closing) {
        // Cek sederhana: apakah object masih ada dan punya method
        if (typeof this.db === 'object' && 
            typeof this.db.runAsync === 'function' && 
            typeof this.db.getFirstAsync === 'function') {
          return this.db; // Anggap masih valid, langsung return
        } else {
          // Object tidak valid, reset (tapi jangan tutup jika ada operasi yang berjalan)
          if (this._operationCount === 0) {
            console.warn("⚠️ Database connection object invalid, reopening...");
            this.db = null;
            this._openingPromise = null;
            this._ensuredTables.clear();
          } else {
            // Ada operasi yang berjalan, tunggu sampai selesai
            console.warn("⚠️ Database connection object invalid but operations running, will reopen after operations complete");
            this.db = null;
          }
        }
      }

      // If database is being opened by another call, wait for it
      if (this._openingPromise) {
        console.log("⏳ [NexaDBLite] Waiting for ongoing database open...");
        return await this._openingPromise;
      }

      // Open database dengan retry mechanism (wrapped in promise to prevent concurrent opens)
      this._openingPromise = (async () => {
        let retries = 3; // Reduce retries untuk menghindari spam error
        let lastError = null;
        
        while (retries > 0) {
          try {
            // Reset db to null before opening
            this.db = null;
            
            // Wait a bit before opening to avoid race conditions
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open database - gunakan metode sederhana
            try {
              this.db = await SQLite.openDatabaseAsync(this.dbName);
            } catch (openError) {
              // Jika openDatabaseAsync throw error, skip
              throw new Error(`Failed to open database: ${openError.message}`);
            }
            
            if (!this.db) {
              throw new Error("Failed to open database: returned null");
            }

            // Wait for database to be fully initialized
            await new Promise(resolve => setTimeout(resolve, 200));

            // Sederhana: Cek apakah object ada dan punya method yang diperlukan
            // Hapus verifikasi kompleks yang menyebabkan NullPointerException
            if (!this.db || typeof this.db !== 'object') {
              throw new Error("Database object is invalid");
            }

            // Cek method tanpa memanggilnya (untuk menghindari NullPointerException)
            const hasMethods = 
              typeof this.db.runAsync === 'function' && 
              typeof this.db.getFirstAsync === 'function' && 
              typeof this.db.getAllAsync === 'function' &&
              typeof this.db.execAsync === 'function';
            
            if (!hasMethods) {
              throw new Error("Database object missing required methods");
            }
              
            // Jika semua check passed, anggap database siap
            // console.log(`✅ [NexaDBLite] Database "${this.dbName}" opened successfully`);
            this._openingPromise = null; // Clear opening promise on success
            return this.db;
          } catch (error) {
            // Log error hanya sekali untuk menghindari spam
            if (retries === 3) {
              console.warn(`⚠️ Failed to open database (${retries} retries left):`, error.message);
            }
            this.db = null;
            retries--;
            lastError = error;
            if (retries > 0) {
              await new Promise(resolve => setTimeout(resolve, 300)); // Wait 300ms before retry
            }
          }
        }

        // All retries failed - return null instead of throw untuk graceful degradation
        this._openingPromise = null; // Clear opening promise on failure
        console.error(`❌ [NexaDBLite] Failed to open database after ${3} retries:`, lastError?.message || 'Unknown error');
        return null; // Return null instead of throw
      })();

      const db = await this._openingPromise;
      
      // Jika database null, throw error yang jelas (non-web)
      if (!db) {
        throw new Error("Database connection failed. SQLite may not be available or database file is corrupted.");
      }
      
      return db;
    } catch (error) {
      // Matikan log error khusus web unsupported agar console tidak penuh.
      const message = String(error?.message || error);
      const currentIsWeb = detectWebPlatform();
      const isWebUnsupported = message.includes("NexaDBLite tidak tersedia di web platform");
      if (!(currentIsWeb && isWebUnsupported)) {
        console.error("❌ [NexaDBLite._getDatabase] Error:", error.message || error);
      }
      this.db = null; // Reset on error
      this._openingPromise = null; // Reset opening promise on error
      this._ensuredTables.clear(); // Reset cache on error
      throw error;
    }
  },

  /**
   * Get table name for store
   */
  _getTableName: function (storeName) {
    return `store_${storeName}`;
  },

  /**
   * Ensure table exists
   * Hanya membuat tabel untuk store yang ada di getDefaultStores()
   * Optimasi: Cache table yang sudah dibuat untuk menghindari query berulang
   */
  _ensureTable: async function (storeName) {
    if (NEXADBLITE_DISABLED) {
      return;
    }
    try {
      // Auto-initialize jika belum
      if (!this.dbName) {
        this.dbName = "NexaDBLite";
        this.version = 1;
        this.autoMode = true;
        if (!this.stores) {
          this.stores = new Set();
        }
        if (!this._ensuredTables) {
          this._ensuredTables = new Set();
        }
      }

      // Check if store is in default stores list atau store dinamis yang diizinkan
      const defaultStores = this.getDefaultStores();
      const isDynamicStore = 
        storeName.startsWith("formQueue_") || 
        storeName.startsWith("dataCache_") || 
        storeName.startsWith("flagCache_") || 
        storeName.startsWith("searchCache_") || 
        storeName === "formDrafts";
      
      if (!defaultStores.includes(storeName) && !isDynamicStore) {
        // Hanya log warning sekali untuk store yang sama (optimasi)
        if (!this._warnedStores) {
          this._warnedStores = new Set();
        }
        if (!this._warnedStores.has(storeName)) {
          const storesList = defaultStores.join(", ");
          console.warn(`⚠️ [NexaDBLite] Store "${storeName}" is not in getDefaultStores(). Table will not be created.`);
          console.warn(`⚠️ [NexaDBLite] Available stores: ${storesList}`);
          this._warnedStores.add(storeName);
        }
        // Still track the store but don't create table
        this.stores.add(storeName);
        return;
      }
      
      // Jika store dinamis yang diizinkan, lanjutkan untuk membuat tabel

      const tableName = this._getTableName(storeName);

      // OPTIMASI: Jika table sudah di-ensure sebelumnya, skip (tidak perlu query lagi)
      // Pastikan _ensuredTables sudah di-initialize
      if (!this._ensuredTables) {
        this._ensuredTables = new Set();
      }
      
      if (this._ensuredTables.has(tableName)) {
        this.stores.add(storeName);
        return; // Table sudah dibuat, langsung return (tidak log untuk menghindari spam)
      }

      // Ensure database is ready
      const db = await this._getDatabase();
      
      if (!db) {
        // Graceful degradation: jika database tidak bisa dibuka, skip table creation
        console.warn(`⚠️ [NexaDBLite] Database connection is null. Skipping table creation for "${storeName}"`);
        this.stores.add(storeName);
        return; // Return tanpa error
      }

      // Double check db is valid before using
      if (typeof db.execAsync !== 'function') {
        console.warn(`⚠️ [NexaDBLite] Database object missing execAsync method. Skipping table creation for "${storeName}"`);
        this.stores.add(storeName);
        return; // Return tanpa error
      }

      // Sederhana: Coba create table sekali saja, jika gagal anggap sudah ada atau skip
      try {
        await db.execAsync(`
          CREATE TABLE IF NOT EXISTS ${tableName} (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL,
            createdAt TEXT,
            updatedAt TEXT
          )
        `);
        this.stores.add(storeName);
        // Pastikan _ensuredTables sudah di-initialize sebelum add
        if (!this._ensuredTables) {
          this._ensuredTables = new Set();
        }
        this._ensuredTables.add(tableName); // Cache table yang sudah dibuat
        // Hanya log sekali saat pertama kali dibuat
        if (!this._loggedTables) {
          this._loggedTables = new Set();
        }
        if (!this._loggedTables.has(tableName)) {
          // console.log(`✅ [NexaDBLite] Table "${tableName}" ensured`);
          this._loggedTables.add(tableName);
        }
        return; // Success
      } catch (execError) {
        // Jika execAsync gagal, anggap table sudah ada atau skip (graceful degradation)
        // Jangan throw error, biarkan operasi database lainnya yang handle
        console.warn(`⚠️ [NexaDBLite] Failed to create table "${tableName}", assuming it exists or skipping:`, execError.message);
        this.stores.add(storeName);
        // Cache juga untuk menghindari retry berulang
        if (!this._ensuredTables) {
          this._ensuredTables = new Set();
        }
        this._ensuredTables.add(tableName);
        return; // Return tanpa error (graceful degradation)
      }
    } catch (error) {
      // Graceful degradation: jangan throw error, hanya log dan return
      const message = String(error?.message || error);
      const currentIsWeb = detectWebPlatform();
      const isWebUnsupported = message.includes("NexaDBLite tidak tersedia di web platform");
      if (!(currentIsWeb && isWebUnsupported)) {
        console.warn(`⚠️ [NexaDBLite._ensureTable] Error for store "${storeName}":`, error.message || error);
      }
      this.stores.add(storeName);
      // Cache untuk menghindari retry berulang
      if (!this._ensuredTables) {
        this._ensuredTables = new Set();
      }
      this._ensuredTables.add(this._getTableName(storeName));
      return; // Return tanpa error
    }
  },

  /**
   * Auto-initialize encryption
   */
  _autoInitEncryption: async function () {
    if (this.encryptionInitialized) return true;
    if (!this.encryptionEnabled) return false;

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
          console.warn("⚠️ NexaEncrypt tidak ditemukan. Encryption disabled.");
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
   * Setup encryption
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
          console.warn("⚠️ NexaEncrypt tidak ditemukan.");
          this.encryptionEnabled = false;
          return false;
        }
      }

      this.encryptor = new NexaEncrypt(secretKey);
      this.encryptionSecretKey = secretKey;
      this.encryptionEnabled = true;
      this.fieldsToSkip = new Set(fieldsToSkip);
      this.encryptionInitialized = true;

      return true;
    } catch (error) {
      console.error("❌ Error setting up encryption:", error);
      this.encryptionEnabled = false;
      return false;
    }
  },

  /**
   * Encrypt data fields
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
              console.warn(`⚠️ [NexaDBLite] Failed to decrypt field "${key}", keeping original value`);
            }
          }
        }
      }

      delete decrypted._encrypted;
      return decrypted;
    } catch (error) {
      console.error("❌ [NexaDBLite] Decryption error:", error.message);
      return data;
    }
  },

  /**
   * ============================================
   * 📝 SET - Menyimpan Data (Auto-initialize)
   * ============================================
   */
  set: function (storeName, data) {
    if (NEXADBLITE_DISABLED) {
      return Promise.resolve(null);
    }
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum di-initialize
        if (!this.dbName) {
          this.dbName = "NexaDBLite";
          this.version = 1;
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._getDatabase();
        }

        // Validate data has id - data.id digunakan sebagai key
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

        // Ensure table exists
        await this._ensureTable(storeName);

        // 🔐 Encrypt data jika encryption enabled (OTOMATIS)
        let dataToSave;
        try {
          dataToSave = await this.encryptDataFields(dataWithTimestamp);
        } catch (error) {
          console.error("Encryption error:", error);
          // Fallback: save without encryption
          dataToSave = dataWithTimestamp;
        }

        // Save to SQLite - data.id digunakan sebagai PRIMARY KEY
        let db = await this._getDatabase();
        
        // Re-initialize jika db null atau invalid (tapi jangan tutup jika ada operasi lain)
        if (!db || typeof db.runAsync !== 'function') {
          if (this._operationCount === 0) {
            console.warn(`⚠️ [NexaDBLite] Database connection is null or invalid in set(), closing and re-initializing...`);
            await this._closeDatabase();
            await new Promise(resolve => setTimeout(resolve, 500));
          }
          db = await this._getDatabase();
          
          if (!db || typeof db.runAsync !== 'function') {
            throw new Error("Database connection failed after re-initialization. Cannot save data.");
          }
        }
        
        const tableName = this._getTableName(storeName);
        
        // Helper function untuk menghitung properti secara rekursif
        const countPropertiesRecursive = (obj, visited = new WeakSet(), depth = 0) => {
          // Limit depth untuk mencegah stack overflow
          if (depth > 20) return 0;
          
          if (obj === null || obj === undefined) return 0;
          if (typeof obj !== 'object') return 0;
          
          // Prevent circular references
          if (visited.has(obj)) return 0;
          visited.add(obj);
          
          let count = 0;
          
          if (Array.isArray(obj)) {
            // Untuk array, hitung elemen dan properti di dalamnya
            count += obj.length;
            for (const item of obj) {
              if (item && typeof item === 'object') {
                count += countPropertiesRecursive(item, visited, depth + 1);
              }
            }
          } else {
            // Untuk object, hitung keys dan properti di dalamnya
            const keys = Object.keys(obj);
            count += keys.length;
            for (const key of keys) {
              const value = obj[key];
              if (value && typeof value === 'object') {
                count += countPropertiesRecursive(value, visited, depth + 1);
              }
            }
          }
          
          return count;
        };
        
        // Validasi jumlah properti secara rekursif SEBELUM stringify
        const propertyCount = countPropertiesRecursive(dataToSave);
        const maxProperties = 10000; // Limit lebih ketat untuk mencegah error "Property storage exceeds 196607"
        
        // Log warning jika mendekati limit
        if (propertyCount > maxProperties * 0.8) {
          console.warn(`⚠️ [NexaDBLite] Data for "${storeName}" has ${propertyCount} properties (${((propertyCount / maxProperties) * 100).toFixed(1)}% of limit)`);
        }
        
        if (propertyCount > maxProperties) {
          console.error(`❌ [NexaDBLite] Data rejected for "${storeName}": ${propertyCount} properties exceeds limit of ${maxProperties}`);
          throw new Error(`Data has too many properties (${propertyCount}). Maximum is ${maxProperties} properties. Please reduce data complexity, remove nested arrays/objects, or split into smaller chunks.`);
        }
        
        // Validasi ukuran data untuk mencegah error "Property storage exceeds"
        let jsonData;
        try {
          jsonData = JSON.stringify(dataToSave);
        } catch (stringifyError) {
          throw new Error(`Failed to serialize data for "${storeName}": ${stringifyError.message}. Data may be too complex or contain circular references.`);
        }
        
        const dataSize = jsonData.length;
        const maxSize = 5 * 1024 * 1024; // 5MB limit (dikurangi dari 10MB)
        
        if (dataSize > maxSize) {
          throw new Error(`Data too large (${(dataSize / 1024 / 1024).toFixed(2)}MB). Maximum size is ${(maxSize / 1024 / 1024).toFixed(2)}MB. Please reduce data size or split into smaller chunks.`);
        }

        // Handle NullPointerException dengan re-initialize database connection
        try {
          await db.runAsync(
            `INSERT OR REPLACE INTO ${tableName} (id, data, createdAt, updatedAt) VALUES (?, ?, ?, ?)`,
            [data.id, jsonData, dataWithTimestamp.createdAt, dataWithTimestamp.updatedAt]
          );
        } catch (runError) {
          const errorMessage = runError?.message || String(runError) || 'Unknown error';
          
          // Jika NullPointerException, close database dengan benar dan re-initialize
          if (errorMessage.includes("NullPointerException") || errorMessage.includes("null") || errorMessage.includes("prepareAsync")) {
            console.warn(`⚠️ [NexaDBLite] NullPointerException detected in set(), closing and re-initializing database connection...`);
            
            // Close database dengan benar sebelum re-initialize
            await this._closeDatabase();
            
            // Wait lebih lama setelah close untuk memastikan cleanup selesai
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // Re-initialize dan coba lagi sekali
            const retryDb = await this._getDatabase();
            if (retryDb && typeof retryDb.runAsync === 'function') {
              // Wait lagi setelah database dibuka untuk memastikan fully initialized
              await new Promise(resolve => setTimeout(resolve, 300));
              
              try {
                await retryDb.runAsync(
                  `INSERT OR REPLACE INTO ${tableName} (id, data, createdAt, updatedAt) VALUES (?, ?, ?, ?)`,
                  [data.id, jsonData, dataWithTimestamp.createdAt, dataWithTimestamp.updatedAt]
                );
                // Success setelah retry
                const decryptedData = await this.decryptDataFields(dataToSave);
                this.notifyObservers(storeName, decryptedData, "update");
                resolve(data.id);
                return;
              } catch (retryError) {
                throw new Error(`Failed to save data to "${storeName}" after re-initialization: ${retryError.message}`);
              }
            } else {
              throw new Error(`Database re-initialization failed for "${storeName}"`);
            }
          }
          
          // Handle khusus untuk error "Property storage exceeds"
          if (errorMessage.includes("Property storage exceeds") || 
              errorMessage.includes("196607") ||
              errorMessage.includes("storage exceeds")) {
            console.error(`❌ [NexaDBLite] Data too complex for "${storeName}". Property count: ${propertyCount}, Size: ${(dataSize / 1024).toFixed(2)}KB`);
            throw new Error(`Data too large or complex for "${storeName}" (${propertyCount} properties, ${(dataSize / 1024).toFixed(2)}KB). Please reduce data complexity, remove nested arrays/objects, or split into smaller chunks.`);
          }
          
          throw new Error(`Failed to save data to "${storeName}": ${errorMessage}`);
        }

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
   */
  get: function (storeName, key) {
    if (NEXADBLITE_DISABLED) {
      return Promise.resolve(null);
    }
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum
        if (!this.dbName) {
          this.dbName = "NexaDBLite";
          this.version = 1;
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._getDatabase();
        }

        // Validate key - jika key tidak ada atau undefined, return null
        if (key === undefined || key === null || key === "") {
          console.warn(`Key is ${key === undefined ? 'undefined' : key === null ? 'null' : 'empty'} for store "${storeName}", returning null`);
          resolve(null);
          return;
        }

        await this._ensureTable(storeName);

        let db = await this._getDatabase();
        
        // Re-initialize jika db null atau invalid
        if (!db || typeof db.getFirstAsync !== 'function') {
          console.warn(`⚠️ [NexaDBLite] Database connection is null or invalid in get(), closing and re-initializing...`);
          await this._closeDatabase();
          await new Promise(resolve => setTimeout(resolve, 500));
          db = await this._getDatabase();
          
          if (!db || typeof db.getFirstAsync !== 'function') {
            resolve(null);
            return;
          }
        }
        
        const tableName = this._getTableName(storeName);
        
        // Handle NullPointerException dengan re-initialize
        let result = null;
        try {
          result = await db.getFirstAsync(
            `SELECT data FROM ${tableName} WHERE id = ?`,
            [key]
          );
        } catch (getError) {
          const errorMessage = getError?.message || String(getError) || 'Unknown error';
          
          // Jika NullPointerException, close database dengan benar dan re-initialize
          if (errorMessage.includes("NullPointerException") || errorMessage.includes("null") || errorMessage.includes("prepareAsync")) {
            console.warn(`⚠️ [NexaDBLite] NullPointerException detected in get(), closing and re-initializing database connection...`);
            
            // Close database dengan benar sebelum re-initialize
            await this._closeDatabase();
            
            // Wait lebih lama setelah close untuk memastikan cleanup selesai
            await new Promise(resolve => setTimeout(resolve, 800));
            
            const retryDb = await this._getDatabase();
            if (retryDb && typeof retryDb.getFirstAsync === 'function') {
              // Wait lagi setelah database dibuka untuk memastikan fully initialized
              await new Promise(resolve => setTimeout(resolve, 300));
              
              try {
                result = await retryDb.getFirstAsync(
                  `SELECT data FROM ${tableName} WHERE id = ?`,
                  [key]
                );
              } catch (retryError) {
                console.warn(`⚠️ getFirstAsync failed after re-initialization for "${storeName}":`, retryError.message);
                resolve(null);
                return;
              }
            } else {
              resolve(null);
              return;
            }
          } else {
            // Error lain, return null
            console.warn(`⚠️ getFirstAsync failed for "${storeName}":`, errorMessage);
            resolve(null);
            return;
          }
        }

        if (!result || !result.data) {
          resolve(null);
          return;
        }

        try {
          const encryptedData = JSON.parse(result.data);
          
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
              console.warn(`⚠️ [NexaDBLite] Decryption error for store "${storeName}":`, decryptError.message);
              // Jika dekripsi gagal, coba return data as is (mungkin tidak terenkripsi)
              resolve(encryptedData);
            }
          } else {
            resolve(null);
          }
        } catch (parseError) {
          console.warn(`⚠️ [NexaDBLite] Error parsing data from store "${storeName}":`, parseError.message);
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
   */
  getAll: function (storeName) {
    if (NEXADBLITE_DISABLED) {
      return Promise.resolve({ data: [] });
    }
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum
        if (!this.dbName) {
          this.dbName = "NexaDBLite";
          this.version = 1;
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._getDatabase();
        }

        await this._ensureTable(storeName);

        let db = await this._getDatabase();
        
        // Re-initialize jika db null atau invalid
        if (!db || typeof db.getAllAsync !== 'function') {
          console.warn(`⚠️ [NexaDBLite] Database connection is null or invalid in getAll(), closing and re-initializing...`);
          await this._closeDatabase();
          await new Promise(resolve => setTimeout(resolve, 500));
          db = await this._getDatabase();
          
          if (!db || typeof db.getAllAsync !== 'function') {
            resolve({ data: [] });
            return;
          }
        }
        
        const tableName = this._getTableName(storeName);

        // Handle NullPointerException dengan re-initialize
        let results = [];
        try {
          results = await db.getAllAsync(
            `SELECT id, data FROM ${tableName}`
          );
        } catch (getAllError) {
          const errorMessage = getAllError?.message || String(getAllError) || 'Unknown error';
          
          // Jika NullPointerException, close database dengan benar dan re-initialize
          if (errorMessage.includes("NullPointerException") || errorMessage.includes("null") || errorMessage.includes("prepareAsync")) {
            console.warn(`⚠️ [NexaDBLite] NullPointerException detected in getAll(), closing and re-initializing database connection...`);
            
            // Close database dengan benar sebelum re-initialize
            await this._closeDatabase();
            
            // Wait lebih lama setelah close untuk memastikan cleanup selesai
            await new Promise(resolve => setTimeout(resolve, 800));
            
            const retryDb = await this._getDatabase();
            if (retryDb && typeof retryDb.getAllAsync === 'function') {
              // Wait lagi setelah database dibuka untuk memastikan fully initialized
              await new Promise(resolve => setTimeout(resolve, 300));
              
              try {
                results = await retryDb.getAllAsync(
                  `SELECT id, data FROM ${tableName}`
                );
              } catch (retryError) {
                console.warn(`⚠️ getAllAsync failed after re-initialization for "${storeName}":`, retryError.message);
                resolve({ data: [] });
                return;
              }
            } else {
              resolve({ data: [] });
              return;
            }
          } else {
            // Error lain, return empty array
            console.warn(`⚠️ getAllAsync failed for "${storeName}":`, errorMessage);
            resolve({ data: [] });
            return;
          }
        }
        
        // Parse and decrypt all items
        const allData = [];
        for (const row of results) {
          if (row && row.data) {
            try {
              const encryptedData = JSON.parse(row.data);
              // 🔐 Decrypt data jika encryption enabled (OTOMATIS)
              const decryptedData = await this.decryptDataFields(encryptedData);
              allData.push(decryptedData);
            } catch (error) {
              console.warn(`Error parsing/decrypting item from key "${row.id || 'unknown'}":`, error);
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
   */
  delete: function (storeName, key) {
    if (NEXADBLITE_DISABLED) {
      return Promise.resolve(false);
    }
    return new Promise(async (resolve, reject) => {
      try {
        // Auto-initialize jika belum
        if (!this.dbName) {
          this.dbName = "NexaDBLite";
          this.version = 1;
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._getDatabase();
        }

        // Validate key
        if (key === undefined || key === null || key === "") {
          resolve(false);
          return;
        }

        await this._ensureTable(storeName);

        let db = await this._getDatabase();
        
        // Re-initialize jika db null atau invalid
        if (!db || typeof db.runAsync !== 'function') {
          console.warn(`⚠️ [NexaDBLite] Database connection is null or invalid in delete(), closing and re-initializing...`);
          await this._closeDatabase();
          await new Promise(resolve => setTimeout(resolve, 500));
          db = await this._getDatabase();
          
          if (!db || typeof db.runAsync !== 'function') {
            resolve(false);
            return;
          }
        }
        
        const tableName = this._getTableName(storeName);
        
        // Handle NullPointerException dengan re-initialize
        try {
          await db.runAsync(
            `DELETE FROM ${tableName} WHERE id = ?`,
            [key]
          );
        } catch (deleteError) {
          const errorMessage = deleteError?.message || String(deleteError) || 'Unknown error';
          
          // Jika NullPointerException, close database dengan benar dan re-initialize
          if (errorMessage.includes("NullPointerException") || errorMessage.includes("null") || errorMessage.includes("prepareAsync")) {
            console.warn(`⚠️ [NexaDBLite] NullPointerException detected in delete(), closing and re-initializing database connection...`);
            
            // Close database dengan benar sebelum re-initialize
            await this._closeDatabase();
            
            // Wait lebih lama setelah close untuk memastikan cleanup selesai
            await new Promise(resolve => setTimeout(resolve, 800));
            
            const retryDb = await this._getDatabase();
            if (retryDb && typeof retryDb.runAsync === 'function') {
              // Wait lagi setelah database dibuka untuk memastikan fully initialized
              await new Promise(resolve => setTimeout(resolve, 300));
              
              try {
                await retryDb.runAsync(
                  `DELETE FROM ${tableName} WHERE id = ?`,
                  [key]
                );
                // Success setelah retry
                this.notifyObservers(storeName, { id: key }, "delete");
                resolve(true);
                return;
              } catch (retryError) {
                console.warn(`⚠️ runAsync (delete) failed after re-initialization for "${storeName}":`, retryError.message);
                resolve(false);
                return;
              }
            } else {
              resolve(false);
              return;
            }
          } else {
            // Error lain, return false
            console.warn(`⚠️ runAsync (delete) failed for "${storeName}":`, errorMessage);
            resolve(false);
            return;
          }
        }

        // Notify observers setelah delete
        this.notifyObservers(storeName, { id: key }, "delete");
        resolve(true);
      } catch (error) {
        console.error(`Error deleting from store "${storeName}":`, error);
        resolve(false);
      }
    });
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
        // Auto-initialize jika belum
        if (!this.dbName) {
          this.dbName = "NexaDBLite";
          this.version = 1;
          this.autoMode = true;
          if (!this.stores) {
            this.stores = new Set();
          }
          await this._getDatabase();
        }

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

  /**
   * ============================================
   * 🔧 UTILITY METHODS
   * ============================================
   */
  getInfo: function () {
    return {
      dbName: this.dbName,
      version: this.version,
      isInitialized: !!this.dbName,
      availableStores: Array.from(this.stores),
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
        if (!this.dbName) {
          resolve();
          return;
        }

        const db = await this._getDatabase();
        
        // Drop all tables
        for (const storeName of this.stores) {
          const tableName = this._getTableName(storeName);
          await db.execAsync(`DROP TABLE IF EXISTS ${tableName}`);
        }

        this.stores.clear();
        resolve();
      } catch (error) {
        console.error("Error resetting database:", error);
        reject(error);
      }
    });
  },
};

// Log removed for cleaner console output

// Default export for compatibility
export default NexaDBLite;

// Log removed for cleaner console output
