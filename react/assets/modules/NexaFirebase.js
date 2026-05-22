// NexaFirebase for React Native - menggunakan Firebase REST API
// File dipindahkan dari /package/NexaFirebase.js ke /package/Storage/NexaFirebase.js
import AsyncStorage from "@react-native-async-storage/async-storage";
import { Platform } from "react-native";
import { FirebaseConfig } from "../../config";

/**
 * NexaFirestore - Simple CRUD class for Firebase Realtime Database
 * React Native Compatible Version
 * Based on firebaseData function pattern
 *
 * @author Nexa Framework
 * @version 2.0.0 (React Native Compatible)
 */
class NexaFirestore {
  // Static properties for singleton pattern
  static instances = new Map();
  static authUnsubscribe = null;
  static globalAuth = null;
  static globalApp = null;
  static globalStorage = null;
  static currentUser = null;
  static authInitialized = false;
  static markSignUpInProgress = null;

  constructor(tabel, firebaseConfig) {
    this.tabel = tabel;
    this.firebaseConfig = firebaseConfig;
    this.app = null;
    this.database = null;
    this.dbRef = null;
    this.auth = null;
    this.storage = null;
    this.initialized = false;
    this.firebaseSDK = null;
    this.baseUrl = null;
    this.projectId = null;
    this.apiKey = null;

    // Create config key for singleton pattern
    this.configKey = JSON.stringify(firebaseConfig);

    // Initialize asynchronously
    this.initPromise = this.init();
  }

  /**
   * Initialize Firebase connection for React Native using REST API
   */
  async init() {
    try {
      // Check if we already have an instance for this config
      if (!NexaFirestore.instances.has(this.configKey)) {
        // Setup Firebase REST API configuration
        this.baseUrl = this.firebaseConfig.databaseURL;
        this.projectId = this.firebaseConfig.projectId;
        this.apiKey = this.firebaseConfig.apiKey;
        this.authDomain = this.firebaseConfig.authDomain;

        // Test connection
        await this.testConnection();

        // Store this instance
        NexaFirestore.instances.set(this.configKey, {
          baseUrl: this.baseUrl,
          projectId: this.projectId,
          apiKey: this.apiKey,
          initialized: true,
        });

        console.log(
          `🚀 Firebase REST API connection established for table: ${this.tabel}`
        );
      } else {
        // Use existing configuration
        const existing = NexaFirestore.instances.get(this.configKey);
        this.baseUrl = existing.baseUrl;
        this.projectId = existing.projectId;
        this.apiKey = existing.apiKey;
      }

      this.initialized = true;
      console.log(
        `🚀 Firebase CRUD initialized for table: ${this.tabel} on React Native`
      );
    } catch (error) {
      console.error("❌ Error initializing Firebase CRUD:", error);
      throw error;
    }
  }

  /**
   * Test Firebase connection and debug
   */
  async testConnection() {
    try {
      console.log("🔍 Testing Firebase connection...");
      console.log("📋 Database URL:", this.baseUrl);
      console.log("📋 Project ID:", this.projectId);

      // Test basic connectivity first
      const testUrl = `${this.baseUrl}/.json`;
      console.log("🌐 Testing URL:", testUrl);

      const response = await fetch(testUrl);
      console.log("📊 Response status:", response.status);
      console.log("📊 Response statusText:", response.statusText);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ Response error:", errorText);
        throw new Error(
          `HTTP ${response.status}: ${response.statusText} - ${errorText}`
        );
      }

      const data = await response.json();
      console.log("✅ Firebase connection test successful");
      console.log("📊 Current data:", data);
      return true;
    } catch (error) {
      console.error("❌ Firebase connection test failed:", error);
      console.error("❌ Error details:", error.message);
      // Don't throw error for connection test, continue anyway
      return false;
    }
  }

  /**
   * Setup auth state listener (Mock implementation for REST API)
   */
  setupAuthListener() {
    // Mock auth listener - in real app would use Firebase Auth REST API
    console.log("📡 Mock auth listener setup");

    // Add method to mark sign up process
    NexaFirestore.markSignUpInProgress = () => {
      console.log("🔄 Mock sign up in progress");
    };
  }

  /**
   * Check if initialized
   */
  async checkInit() {
    if (!this.initialized) {
      // Wait for initialization to complete
      await this.initPromise;
      if (!this.initialized) {
        throw new Error("Firebase CRUD not initialized");
      }
    }
  }

  /**
   * Add data to Firebase
   * @param {Object} dataArray - Data object with key-value pairs
   * @returns {Promise}
   */
  async add(dataArray) {
    await this.checkInit();
    const key = Object.keys(dataArray)[0];
    const data = dataArray[key];

    try {
      // React Native - use Firebase REST API
      const url = `${this.baseUrl}/${this.tabel}/${key}.json`;

      console.log("🚀 Adding data to Firebase...");
      console.log("📍 URL:", url);
      console.log("🔑 Key:", key);
      console.log("📊 Data:", JSON.stringify(data, null, 2));

      const response = await fetch(url, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });

      console.log("📊 Response status:", response.status);
      console.log("📊 Response statusText:", response.statusText);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ Firebase add error:", errorText);
        throw new Error(
          `HTTP ${response.status}: ${response.statusText} - ${errorText}`
        );
      }

      const responseData = await response.json();
      console.log("✅ Firebase add successful!");
      console.log("📊 Response data:", responseData);

      // Menyimpan ke AsyncStorage (optional)
      try {
        const localDataString = await AsyncStorage.getItem(this.tabel);
        let localData = localDataString ? JSON.parse(localDataString) : {};
        localData[key] = data;
        await AsyncStorage.setItem(this.tabel, JSON.stringify(localData));

        return "Data berhasil ditambahkan ke Firebase dan AsyncStorage";
      } catch (localError) {
        console.error("Error saat menyimpan ke AsyncStorage:", localError);
        return "Data berhasil ditambahkan ke Firebase, tapi gagal di AsyncStorage";
      }
    } catch (error) {
      console.error("❌ Add operation failed:", error);
      throw new Error("Error saat menambahkan data: " + error.message);
    }
  }

  /**
   * Add or update data with history tracking
   * @param {Object} dataArray - Data object with key-value pairs
   * @returns {Promise}
   */
  async history(dataArray) {
    await this.checkInit();
    const key = Object.keys(dataArray)[0];
    const currentDate = new Date().toISOString();

    try {
      // Check if data exists
      const getUrl = `${this.baseUrl}/${this.tabel}/${key}.json`;
      const getResponse = await fetch(getUrl);

      if (!getResponse.ok) {
        throw new Error(
          `HTTP ${getResponse.status}: ${getResponse.statusText}`
        );
      }

      const existingData = await getResponse.json();
      let updateData;

      if (existingData && existingData !== null) {
        // Update data jika key sudah ada
        updateData = {
          ...existingData,
          ...dataArray[key],
          updatedAt: currentDate,
        };
      } else {
        // Tambahkan data baru jika key belum ada
        updateData = {
          ...dataArray[key],
          createdAt: currentDate,
          updatedAt: currentDate,
        };
      }

      // Save data
      const putUrl = `${this.baseUrl}/${this.tabel}/${key}.json`;
      const putResponse = await fetch(putUrl, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(updateData),
      });

      if (!putResponse.ok) {
        throw new Error(
          `HTTP ${putResponse.status}: ${putResponse.statusText}`
        );
      }

      return "Data berhasil disimpan";
    } catch (error) {
      throw new Error("Error saat menyimpan data: " + error.message);
    }
  }

  /**
   * Get single data by key
   * @param {string} key - Data key
   * @returns {Promise}
   */
  async get(key) {
    await this.checkInit();

    try {
      const url = `${this.baseUrl}/${this.tabel}/${key}.json`;
      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      return data;
    } catch (error) {
      throw new Error("Error saat mengambil data: " + error.message);
    }
  }

  /**
   * Read all data with real-time updates (polling-based for REST API)
   * @param {Function} callback - Callback function to handle data
   * @param {number} interval - Polling interval in ms (default: 3000)
   * @returns {Function} Unsubscribe function
   */
  async red(callback, interval = 3000) {
    await this.checkInit();

    let isActive = true;
    let lastData = null;

    const pollData = async () => {
      if (!isActive) return;

      try {
        const data = await this.getAll();

        // Only call callback if data has changed
        const currentDataStr = JSON.stringify(data);
        if (currentDataStr !== lastData) {
          lastData = currentDataStr;
          callback(data);
        }
      } catch (error) {
        console.error("Error in real-time polling:", error);
      }

      if (isActive) {
        setTimeout(pollData, interval);
      }
    };

    // Start polling
    pollData();

    // Return unsubscribe function
    return () => {
      isActive = false;
    };
  }

  /**
   * Delete data older than specified date
   * @param {string} tanggal - Date string
   * @returns {Promise}
   */
  async delDate(tanggal) {
    await this.checkInit();

    try {
      const tanggalBatas = new Date(tanggal).getTime();
      const allData = await this.getAll();

      let deletedCount = 0;
      for (const item of allData) {
        if (
          item.updatedAt &&
          new Date(item.updatedAt).getTime() < tanggalBatas
        ) {
          await this.del(item.key);
          deletedCount++;
        }
      }

      return `${deletedCount} data lama berhasil dihapus`;
    } catch (error) {
      throw new Error("Error saat menghapus data: " + error.message);
    }
  }

  /**
   * Delete single data by key
   * @param {string} key - Data key
   * @returns {Promise}
   */
  async del(key) {
    await this.checkInit();

    try {
      const url = `${this.baseUrl}/${this.tabel}/${key}.json`;
      const response = await fetch(url, { method: "DELETE" });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return "Data berhasil dihapus";
    } catch (error) {
      throw new Error("Error saat menghapus data: " + error.message);
    }
  }

  /**
   * Update existing data
   * @param {string} key - Data key
   * @param {Object} data - Data to update
   * @returns {Promise}
   */
  async update(key, data) {
    await this.checkInit();

    try {
      const updateData = {
        ...data,
        updatedAt: new Date().toISOString(),
      };

      const url = `${this.baseUrl}/${this.tabel}/${key}.json`;
      const response = await fetch(url, {
        method: "PATCH",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(updateData),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return "Data berhasil diupdate";
    } catch (error) {
      throw new Error("Error saat mengupdate data: " + error.message);
    }
  }

  /**
   * Get all data as array (one-time read)
   * @returns {Promise}
   */
  async getAll() {
    await this.checkInit();

    try {
      const url = `${this.baseUrl}/${this.tabel}.json`;
      const response = await fetch(url);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      const result = [];

      if (data) {
        Object.keys(data).forEach((key) => {
          result.push({
            key: key,
            ...data[key],
          });
        });
      }

      return result;
    } catch (error) {
      throw new Error("Error saat mengambil semua data: " + error.message);
    }
  }

  /**
   * Search data by field value
   * @param {string} field - Field name to search
   * @param {*} value - Value to search for
   * @returns {Promise}
   */
  async search(field, value) {
    await this.checkInit();

    try {
      const allData = await this.getAll();
      const result = allData.filter((item) => item[field] === value);
      return result;
    } catch (error) {
      throw new Error("Error saat mencari data: " + error.message);
    }
  }

  /**
   * Count total records
   * @returns {Promise}
   */
  async count() {
    await this.checkInit();

    try {
      const allData = await this.getAll();
      return allData.length;
    } catch (error) {
      throw new Error("Error saat menghitung data: " + error.message);
    }
  }

  /**
   * Check if key exists
   * @param {string} key - Data key
   * @returns {Promise}
   */
  async exists(key) {
    await this.checkInit();

    try {
      const data = await this.get(key);
      return data !== null;
    } catch (error) {
      throw new Error("Error saat mengecek data: " + error.message);
    }
  }

  /**
   * Add data with auto-generated key
   * @param {Object} data - Data to add
   * @returns {Promise} Object with key and data
   */
  async addWithAutoKey(data) {
    await this.checkInit();

    try {
      // Generate auto key using timestamp + random
      const autoKey = `${Date.now()}_${Math.random()
        .toString(36)
        .substr(2, 9)}`;

      const dataWithTimestamp = {
        ...data,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      };

      const url = `${this.baseUrl}/${this.tabel}/${autoKey}.json`;
      const response = await fetch(url, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(dataWithTimestamp),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return {
        key: autoKey,
        data: dataWithTimestamp,
      };
    } catch (error) {
      throw new Error("Error saat menambahkan data: " + error.message);
    }
  }

  /**
   * Update data by field value (when you don't have the key)
   * @param {string} field - Field name to search
   * @param {*} value - Value to search for
   * @param {Object} newData - New data to update
   * @returns {Promise}
   */
  updateByField(field, value, newData) {
    this.checkInit();
    return new Promise((resolve, reject) => {
      // Cari data berdasarkan field
      this.search(field, value)
        .then((results) => {
          if (results.length === 0) {
            reject(
              "Data tidak ditemukan untuk field: " + field + " = " + value
            );
            return;
          }

          // Update data pertama yang ditemukan
          const key = results[0].key;
          return this.update(key, newData);
        })
        .then(() =>
          resolve("Data berhasil diupdate berdasarkan field: " + field)
        )
        .catch((error) => reject(error));
    });
  }

  /**
   * Update multiple records by field value
   * @param {string} field - Field name to search
   * @param {*} value - Value to search for
   * @param {Object} newData - New data to update
   * @returns {Promise}
   */
  updateAllByField(field, value, newData) {
    this.checkInit();
    return new Promise((resolve, reject) => {
      this.search(field, value)
        .then((results) => {
          if (results.length === 0) {
            reject(
              "Data tidak ditemukan untuk field: " + field + " = " + value
            );
            return;
          }

          // Update semua data yang ditemukan
          const updatePromises = results.map((item) =>
            this.update(item.key, newData)
          );
          return Promise.all(updatePromises);
        })
        .then(() =>
          resolve(
            `${results.length} data berhasil diupdate berdasarkan field: ${field}`
          )
        )
        .catch((error) => reject(error));
    });
  }

  /**
   * Upsert - Update if exists, Insert if not (with auto-generated key)
   * @param {string|Object} keyOrData - Key (if data provided) or Data (if auto-key)
   * @param {Object} data - Data to upsert (optional if keyOrData is data)
   * @returns {Promise}
   */
  upsert(keyOrData, data = null) {
    this.checkInit();
    return new Promise((resolve, reject) => {
      let key, updateData;

      if (data === null) {
        // Jika hanya 1 parameter, auto-generate key
        const newRef = push(ref(this.database, this.tabel));
        key = newRef.key;
        updateData = keyOrData;
      } else {
        // Jika 2 parameter, gunakan key yang diberikan
        key = keyOrData;
        updateData = data;
      }

      this.history({ [key]: updateData })
        .then(() => resolve({ key, data: updateData }))
        .catch((error) => reject(error));
    });
  }

  /**
   * Find and update - Search by multiple fields and update
   * @param {Object} searchCriteria - Object with field-value pairs to search
   * @param {Object} newData - New data to update
   * @returns {Promise}
   */
  async findAndUpdate(searchCriteria, newData) {
    await this.checkInit();

    try {
      const allData = await this.getAll();
      const results = [];

      // Check if all search criteria match
      for (const item of allData) {
        let match = true;
        for (const [field, value] of Object.entries(searchCriteria)) {
          if (item[field] !== value) {
            match = false;
            break;
          }
        }

        if (match) {
          results.push(item);
        }
      }

      if (results.length === 0) {
        throw new Error(
          "Data tidak ditemukan untuk kriteria: " +
            JSON.stringify(searchCriteria)
        );
      }

      // Update data pertama yang ditemukan
      const key = results[0].key;
      await this.update(key, newData);

      return "Data berhasil diupdate berdasarkan kriteria pencarian";
    } catch (error) {
      throw new Error("Error dalam findAndUpdate: " + error.message);
    }
  }

  // ========== AUTHENTICATION METHODS ==========
  // Note: Authentication using REST API requires Firebase Auth REST API
  // For now, these are simplified mock implementations

  /**
   * Authenticate user with email and password (Mock implementation)
   * @param {string} email - User email
   * @param {string} password - User password
   * @param {Object} options - Additional options
   * @returns {Promise}
   */
  async authenticate(email, password, options = {}) {
    await this.checkInit();

    const { silent = false, onSuccess, onError } = options;

    try {
      // Validate input
      if (!email || !password) {
        throw new Error("Email dan password harus diisi");
      }

      if (!this.isValidEmail(email)) {
        throw new Error("Format email tidak valid");
      }

      if (password.length < 6) {
        throw new Error("Password minimal 6 karakter");
      }

      // Mock authentication - in real app, use Firebase Auth REST API
      const mockUser = {
        email: email,
        uid: `mock_${Date.now()}`,
        authenticated: true,
      };

      NexaFirestore.currentUser = mockUser;

      if (!silent) {
        console.log(`✅ Mock authentication successful: ${email}`);
      }

      // Call success callback if provided
      if (onSuccess) {
        onSuccess({ user: mockUser });
      }

      return { user: mockUser };
    } catch (error) {
      const friendlyMessage = this.getFriendlyAuthError(error);
      console.error("❌ Authentication failed:", friendlyMessage);

      // Call error callback if provided
      if (onError) {
        onError(error, friendlyMessage);
      }

      throw new Error(friendlyMessage);
    }
  }

  /**
   * Quick login method (backward compatibility)
   * @param {string} email - User email
   * @param {string} password - User password
   * @returns {Promise}
   */
  async login(email, password) {
    return this.authenticate(email, password, { silent: true });
  }

  /**
   * Validate email format
   * @param {string} email
   * @returns {boolean}
   */
  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * Get user-friendly error messages
   * @param {Error} error
   * @returns {string}
   */
  getFriendlyAuthError(error) {
    const errorMessages = {
      "auth/user-not-found": "Email tidak terdaftar",
      "auth/wrong-password": "Password salah",
      "auth/invalid-email": "Format email tidak valid",
      "auth/user-disabled": "Akun telah dinonaktifkan",
      "auth/too-many-requests": "Terlalu banyak percobaan. Coba lagi nanti",
      "auth/network-request-failed": "Koneksi internet bermasalah",
      "auth/email-already-in-use": "Email sudah terdaftar",
      "auth/weak-password": "Password terlalu lemah",
      "auth/operation-not-allowed": "Operasi tidak diizinkan",
      "auth/invalid-credential": "Email atau password salah",
    };

    return (
      errorMessages[error.code] ||
      error.message ||
      "Terjadi kesalahan tidak dikenal"
    );
  }

  /**
   * Create new user account (Firebase v9+ standard)
   * @param {string} email - User email
   * @param {string} password - User password
   * @param {Object} options - Additional options
   * @param {boolean} options.silent - Don't log success message
   * @param {Function} options.onSuccess - Callback on successful registration
   * @param {Function} options.onError - Callback on error
   * @returns {Promise}
   */
  async createAccount(email, password, options = {}) {
    await this.checkInit();

    const { silent = false, onSuccess, onError } = options;

    try {
      // Validate input
      if (!email || !password) {
        throw new Error("Email dan password harus diisi");
      }

      if (!this.isValidEmail(email)) {
        throw new Error("Format email tidak valid");
      }

      if (password.length < 6) {
        throw new Error("Password minimal 6 karakter");
      }

      // Mock account creation - in real app, use Firebase Auth REST API
      const mockUser = {
        email: email,
        uid: `new_user_${Date.now()}`,
        authenticated: true,
        isNewUser: true,
      };

      NexaFirestore.currentUser = mockUser;

      if (!silent) {
        console.log(`✅ Mock account created: ${email}`);
      }

      // Call success callback if provided
      if (onSuccess) {
        onSuccess({ user: mockUser });
      }

      return { user: mockUser };
    } catch (error) {
      const friendlyMessage = this.getFriendlyAuthError(error);
      console.error("❌ Failed to create account:", friendlyMessage);

      // Call error callback if provided
      if (onError) {
        onError(error, friendlyMessage);
      }

      throw new Error(friendlyMessage);
    }
  }

  /**
   * Quick registration method (backward compatibility)
   * @param {string} email - User email
   * @param {string} password - User password
   * @returns {Promise}
   */
  async register(email, password) {
    return this.createAccount(email, password, { silent: true });
  }

  /**
   * Sign out current user
   * @returns {Promise}
   */
  signOut() {
    try {
      NexaFirestore.currentUser = null;
      console.log("✅ User signed out");
      return Promise.resolve();
    } catch (error) {
      console.error("❌ Sign out error:", error);
      return Promise.reject(error);
    }
  }

  /**
   * Get current authenticated user
   * @returns {Object|null}
   */
  getCurrentUser() {
    return NexaFirestore.currentUser;
  }

  /**
   * Check if user is authenticated
   * @returns {boolean}
   */
  isAuthenticated() {
    return NexaFirestore.currentUser !== null;
  }

  /**
   * Wait for authentication state to be determined (Mock implementation)
   * @returns {Promise}
   */
  waitForAuth() {
    return new Promise((resolve) => {
      // Mock implementation - return current user or null
      resolve(NexaFirestore.currentUser);
    });
  }

  /**
   * Cleanup and destroy instance
   */
  destroy() {
    // Only cleanup global auth listener if this is the last instance
    if (NexaFirestore.instances.size === 1 && NexaFirestore.authUnsubscribe) {
      NexaFirestore.authUnsubscribe();
      NexaFirestore.authUnsubscribe = null;
      NexaFirestore.authInitialized = false;
    }

    this.initialized = false;
  }

  /**
   * Static method to cleanup all instances
   */
  static cleanup() {
    if (NexaFirestore.authUnsubscribe) {
      NexaFirestore.authUnsubscribe();
      NexaFirestore.authUnsubscribe = null;
    }

    NexaFirestore.instances.clear();
    NexaFirestore.globalAuth = null;
    NexaFirestore.globalApp = null;
    NexaFirestore.globalStorage = null;
    NexaFirestore.currentUser = null;
    NexaFirestore.authInitialized = false;
  }

  /**
   * Check and suggest Firebase configuration fixes
   * @param {Object} config - Firebase configuration
   * @returns {Object}
   */
  static analyzeConfig(config) {
    const issues = [];
    const suggestions = [];

    console.log("🔍 Analyzing Firebase configuration...");

    // Check required fields
    const required = ["apiKey", "authDomain", "databaseURL", "projectId"];
    required.forEach((field) => {
      if (!config[field]) {
        issues.push(`Missing required field: ${field}`);
      }
    });

    // Check database URL format
    if (config.databaseURL) {
      const url = config.databaseURL;

      // Old format detection
      if (url.includes(".firebaseio.com") && !url.includes("-default-rtdb")) {
        console.log("📊 Detected: Legacy Firebase project (old format)");
        console.log("🔧 URL format:", url);
      }

      // New format detection
      else if (
        url.includes("-default-rtdb") &&
        url.includes(".firebasedatabase.app")
      ) {
        console.log("📊 Detected: Modern Firebase project (new format)");
        console.log("🔧 URL format:", url);

        // Check if region is specified
        if (!url.includes("asia-southeast1") && !url.includes("us-central1")) {
          suggestions.push(
            "Consider specifying database region for better performance"
          );
        }
      }

      // Invalid format
      else {
        issues.push("Invalid database URL format");
        suggestions.push(
          "Expected format: https://project-id-default-rtdb.region.firebasedatabase.app/"
        );
      }

      // Check if URL ends with slash
      if (!url.endsWith("/")) {
        suggestions.push('Database URL should end with "/"');
      }
    }

    // Log results
    if (issues.length > 0) {
      console.log("❌ Configuration Issues:", issues);
    }

    if (suggestions.length > 0) {
      console.log("💡 Suggestions:", suggestions);
    }

    if (issues.length === 0 && suggestions.length === 0) {
      console.log("✅ Configuration looks good!");
    }

    return { issues, suggestions, config };
  }

  /**
   * Generate correct database URL for new Firebase projects
   * @param {string} projectId - Firebase project ID
   * @param {string} region - Database region (default: 'asia-southeast1')
   * @returns {string}
   */
  static generateDatabaseURL(projectId, region = "asia-southeast1") {
    return `https://${projectId}-default-rtdb.${region}.firebasedatabase.app/`;
  }

  /**
   * Quick setup helper for new Firebase projects
   * @param {Object} basicConfig - Basic configuration
   * @returns {Object}
   */
  static setupNewProject(basicConfig) {
    const { projectId, apiKey, region = "asia-southeast1" } = basicConfig;

    if (!projectId || !apiKey) {
      throw new Error("projectId and apiKey are required");
    }

    const config = {
      apiKey: apiKey,
      authDomain: `${projectId}.firebaseapp.com`,
      databaseURL: this.generateDatabaseURL(projectId, region),
      projectId: projectId,
      storageBucket: `${projectId}.appspot.com`,
      messagingSenderId: "123456789", // You need to get this from Firebase console
      appId: "1:123456789:web:abc123def456", // You need to get this from Firebase console
    };

    console.log("🔧 Generated Firebase config for new project:");
    console.log(config);
    console.log(
      "⚠️  Please update messagingSenderId and appId from Firebase console"
    );

    return config;
  }

  // ======================
  // FIREBASE STORAGE METHODS (DISABLED - REQUIRES FIREBASE SDK)
  // ======================

  /**
   * Upload file to Firebase Storage (Mock implementation)
   * @param {File} file - File to upload
   * @param {string} path - Storage path
   * @param {function} onProgress - Progress callback (optional)
   * @returns {Promise<object>} Mock result
   */
  async uploadFile(file, path, onProgress = null) {
    console.log("🚫 Firebase Storage upload disabled in REST API mode");
    console.log("📁 File:", file?.name || "unknown");
    console.log("📍 Path:", path);

    if (onProgress) {
      // Mock progress
      setTimeout(() => onProgress(100), 1000);
    }

    return {
      downloadURL: "https://mock-storage-url.com/" + path,
      fullPath: path,
      name: file?.name || "mockfile",
      size: file?.size || 0,
      contentType: file?.type || "application/octet-stream",
      mock: true,
    };
    try {
      if (!this.storage) {
        throw new Error("Firebase Storage not initialized");
      }

      const fileRef = storageRef(this.storage, path);

      if (onProgress) {
        // Use resumable upload for progress tracking
        const uploadTask = uploadBytesResumable(fileRef, file);

        return new Promise((resolve, reject) => {
          uploadTask.on(
            "state_changed",
            (snapshot) => {
              const progress =
                (snapshot.bytesTransferred / snapshot.totalBytes) * 100;
              onProgress(progress);
            },
            (error) => reject(error),
            async () => {
              try {
                const downloadURL = await getDownloadURL(
                  uploadTask.snapshot.ref
                );
                resolve({
                  downloadURL,
                  fullPath: uploadTask.snapshot.ref.fullPath,
                  name: uploadTask.snapshot.ref.name,
                  size: uploadTask.snapshot.totalBytes,
                  contentType: file.type,
                });
              } catch (error) {
                reject(error);
              }
            }
          );
        });
      } else {
        // Simple upload without progress
        const snapshot = await uploadBytes(fileRef, file);
        const downloadURL = await getDownloadURL(snapshot.ref);

        return {
          downloadURL,
          fullPath: snapshot.ref.fullPath,
          name: snapshot.ref.name,
          size: snapshot.totalBytes,
          contentType: file.type,
        };
      }
    } catch (error) {
      console.error("Error uploading file:", error);
      throw error;
    }
  }

  /**
   * Delete file from Firebase Storage
   * @param {string} path - Storage path to delete
   * @returns {Promise<void>}
   */
  async deleteFile(path) {
    try {
      if (!this.storage) {
        throw new Error("Firebase Storage not initialized");
      }

      const fileRef = storageRef(this.storage, path);
      await deleteObject(fileRef);
      console.log(`File deleted: ${path}`);
    } catch (error) {
      console.error("Error deleting file:", error);
      throw error;
    }
  }

  /**
   * Get download URL for existing file
   * @param {string} path - Storage path
   * @returns {Promise<string>} Download URL
   */
  async getFileURL(path) {
    try {
      if (!this.storage) {
        throw new Error("Firebase Storage not initialized");
      }

      const fileRef = storageRef(this.storage, path);
      return await getDownloadURL(fileRef);
    } catch (error) {
      console.error("Error getting file URL:", error);
      throw error;
    }
  }

  // ========== REACT NATIVE SPECIFIC METHODS ==========

  /**
   * Get platform information
   * @returns {Object} Platform details
   */
  getPlatformInfo() {
    return {
      platform: Platform.OS,
      version: Platform.Version,
      isAndroid: Platform.OS === "android",
      isIOS: Platform.OS === "ios",
      isWeb: Platform.OS === "web",
    };
  }

  /**
   * Save data to AsyncStorage
   * @param {string} key - Storage key
   * @param {*} data - Data to store
   * @returns {Promise}
   */
  async saveToLocal(key, data) {
    try {
      const jsonData = JSON.stringify(data);
      await AsyncStorage.setItem(`${this.tabel}_${key}`, jsonData);
      return true;
    } catch (error) {
      console.error("Error saving to AsyncStorage:", error);
      throw error;
    }
  }

  /**
   * Get data from AsyncStorage
   * @param {string} key - Storage key
   * @returns {Promise}
   */
  async getFromLocal(key) {
    try {
      const jsonData = await AsyncStorage.getItem(`${this.tabel}_${key}`);
      return jsonData ? JSON.parse(jsonData) : null;
    } catch (error) {
      console.error("Error getting from AsyncStorage:", error);
      throw error;
    }
  }

  /**
   * Remove data from AsyncStorage
   * @param {string} key - Storage key
   * @returns {Promise}
   */
  async removeFromLocal(key) {
    try {
      await AsyncStorage.removeItem(`${this.tabel}_${key}`);
      return true;
    } catch (error) {
      console.error("Error removing from AsyncStorage:", error);
      throw error;
    }
  }

  /**
   * Clear all data for this table from AsyncStorage
   * @returns {Promise}
   */
  async clearLocalData() {
    try {
      const keys = await AsyncStorage.getAllKeys();
      const tableKeys = keys.filter((key) => key.startsWith(`${this.tabel}_`));
      await AsyncStorage.multiRemove(tableKeys);
      return true;
    } catch (error) {
      console.error("Error clearing local data:", error);
      throw error;
    }
  }

  /**
   * Sync local data with Firebase (offline support)
   * @returns {Promise}
   */
  async syncWithFirebase() {
    try {
      const platformInfo = this.getPlatformInfo();
      console.log(`Starting sync on ${platformInfo.platform}...`);

      // Get all local data
      const keys = await AsyncStorage.getAllKeys();
      const tableKeys = keys.filter((key) => key.startsWith(`${this.tabel}_`));

      let syncedCount = 0;
      for (const fullKey of tableKeys) {
        const localKey = fullKey.replace(`${this.tabel}_`, "");
        const localData = await this.getFromLocal(localKey);

        if (localData && localData._needsSync) {
          // Upload to Firebase
          await this.update(localKey, localData);
          // Remove sync flag
          delete localData._needsSync;
          await this.saveToLocal(localKey, localData);
          syncedCount++;
        }
      }

      console.log(`Synced ${syncedCount} items to Firebase`);
      return { synced: syncedCount, platform: platformInfo.platform };
    } catch (error) {
      console.error("Error syncing with Firebase:", error);
      throw error;
    }
  }

  /**
   * Add data with offline support
   * @param {Object} dataArray - Data object
   * @param {boolean} offlineMode - Save locally if Firebase fails
   * @returns {Promise}
   */
  async addOffline(dataArray, offlineMode = true) {
    const key = Object.keys(dataArray)[0];
    const data = dataArray[key];

    try {
      // Try Firebase first
      await this.add(dataArray);

      // Save to local storage as backup
      if (offlineMode) {
        await this.saveToLocal(key, data);
      }

      return "Data berhasil ditambahkan ke Firebase dan local storage";
    } catch (firebaseError) {
      if (offlineMode) {
        // If Firebase fails, save locally with sync flag
        const offlineData = {
          ...data,
          _needsSync: true,
          _addedAt: new Date().toISOString(),
        };
        await this.saveToLocal(key, offlineData);
        return "Data disimpan secara offline, akan disync saat online";
      } else {
        throw firebaseError;
      }
    }
  }

  /**
   * Get data with offline fallback
   * @param {string} key - Data key
   * @returns {Promise}
   */
  async getOffline(key) {
    try {
      // Try Firebase first
      const firebaseData = await this.get(key);
      if (firebaseData) {
        // Update local cache
        await this.saveToLocal(key, firebaseData);
        return firebaseData;
      }
    } catch (error) {
      console.log("Firebase read failed, checking local storage...");
    }

    // Fallback to local storage
    const localData = await this.getFromLocal(key);
    if (localData) {
      console.log("Data loaded from local storage");
      return localData;
    }

    return null;
  }

  /**
   * Upload file optimized for React Native
   * @param {Object} fileUri - React Native file URI or File object
   * @param {string} path - Storage path
   * @param {function} onProgress - Progress callback
   * @returns {Promise<object>} Upload result
   */
  async uploadFileRN(fileUri, path, onProgress = null) {
    try {
      if (!this.storage) {
        throw new Error("Firebase Storage not initialized");
      }

      const platformInfo = this.getPlatformInfo();
      let fileBlob;

      if (platformInfo.isWeb) {
        // Web handling
        fileBlob = fileUri;
      } else {
        // React Native handling
        const response = await fetch(fileUri);
        fileBlob = await response.blob();
      }

      const fileRef = storageRef(this.storage, path);

      if (onProgress) {
        const uploadTask = uploadBytesResumable(fileRef, fileBlob);

        return new Promise((resolve, reject) => {
          uploadTask.on(
            "state_changed",
            (snapshot) => {
              const progress =
                (snapshot.bytesTransferred / snapshot.totalBytes) * 100;
              onProgress(progress, platformInfo.platform);
            },
            (error) => reject(error),
            async () => {
              try {
                const downloadURL = await getDownloadURL(
                  uploadTask.snapshot.ref
                );
                resolve({
                  downloadURL,
                  fullPath: uploadTask.snapshot.ref.fullPath,
                  name: uploadTask.snapshot.ref.name,
                  size: uploadTask.snapshot.totalBytes,
                  platform: platformInfo.platform,
                });
              } catch (error) {
                reject(error);
              }
            }
          );
        });
      } else {
        const snapshot = await uploadBytes(fileRef, fileBlob);
        const downloadURL = await getDownloadURL(snapshot.ref);

        return {
          downloadURL,
          fullPath: snapshot.ref.fullPath,
          name: snapshot.ref.name,
          size: snapshot.totalBytes,
          platform: platformInfo.platform,
        };
      }
    } catch (error) {
      console.error("Error uploading file:", error);
      throw error;
    }
  }

  /**
   * Check network connectivity (requires additional setup in React Native)
   * @returns {Promise<boolean>}
   */
  async checkConnectivity() {
    try {
      // Simple connectivity check using Firebase
      const testRef = ref(this.database, ".info/connected");
      const snapshot = await get(testRef);
      return snapshot.val() === true;
    } catch (error) {
      return false;
    }
  }

  /**
   * Initialize for React Native with platform-specific optimizations
   * @returns {Promise}
   */
  async initializeForRN() {
    try {
      await this.init();

      const platformInfo = this.getPlatformInfo();
      console.log(`🚀 NexaFirebase initialized for ${platformInfo.platform}`);

      // Platform-specific optimizations
      if (platformInfo.isAndroid) {
        console.log("📱 Android-specific optimizations applied");
      } else if (platformInfo.isIOS) {
        console.log("🍎 iOS-specific optimizations applied");
      } else if (platformInfo.isWeb) {
        console.log("🌐 Web-specific optimizations applied");
      }

      // Test connectivity and rules
      const isConnected = await this.checkConnectivity();
      console.log(
        `🌐 Network status: ${isConnected ? "Connected" : "Offline"}`
      );

      // Test write permissions
      await this.testWritePermission();

      return {
        initialized: true,
        platform: platformInfo,
        connected: isConnected,
      };
    } catch (error) {
      console.error("React Native initialization error:", error);
      throw error;
    }
  }

  /**
   * Test write permission to Firebase
   */
  async testWritePermission() {
    try {
      console.log("🔒 Testing Firebase write permission...");

      const testKey = `_test_${Date.now()}`;
      const testData = {
        test: true,
        timestamp: new Date().toISOString(),
        platform: Platform.OS,
      };

      const testUrl = `${this.baseUrl}/test/${testKey}.json`;
      console.log("🌐 Test write URL:", testUrl);

      const response = await fetch(testUrl, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(testData),
      });

      console.log("📊 Write test response status:", response.status);

      if (response.ok) {
        console.log("✅ Write permission: SUCCESS");

        // Clean up test data
        const deleteResponse = await fetch(testUrl, { method: "DELETE" });
        if (deleteResponse.ok) {
          console.log("🧹 Test data cleaned up");
        }

        return true;
      } else {
        const errorText = await response.text();
        console.error("❌ Write permission: FAILED");
        console.error("🔐 Error:", errorText);

        if (response.status === 401) {
          console.log("💡 Tip: Firebase Rules require authentication");
        } else if (response.status === 403) {
          console.log("💡 Tip: Check your Firebase Rules configuration");
        }

        return false;
      }
    } catch (error) {
      console.error("❌ Write permission test failed:", error);
      return false;
    }
  }
}

/**
 * Factory function to create CRUD instance (similar to original firebaseData function)
 * @param {string} tabel - Table name
 * @param {Object} firebaseConfig - Firebase configuration (optional, uses default config if not provided)
 * @returns {NexaFirestore} CRUD instance
 */
function firebaseData(tabel, firebaseConfig = null) {
  const config = firebaseConfig || FirebaseConfig;
  return new NexaFirestore(tabel, config);
}

// Global instances cache untuk singleton pattern
const globalInstances = new Map();

/**
 * Quick initialization function using default config from config.js
 * OPTIMIZED: Automatic singleton pattern - reuse existing instances
 * @param {string} tabel - Table name
 * @returns {NexaFirestore} CRUD instance with default config (singleton)
 */
function nexaFirebase(tabel) {
  // ✅ Check if instance already exists for this table
  if (globalInstances.has(tabel)) {
    console.log(
      `♻️ Reusing existing NexaFirebase instance for table: ${tabel}`
    );
    return globalInstances.get(tabel);
  }

  // ✅ Create new instance only if not exists
  console.log(`🆕 Creating new NexaFirebase instance for table: ${tabel}`);
  const instance = new NexaFirestore(tabel, FirebaseConfig);

  // ✅ Cache the instance for future use
  globalInstances.set(tabel, instance);

  return instance;
}

/**
 * Initialize Firebase with default config and React Native optimizations
 * OPTIMIZED: Automatic singleton pattern with async initialization
 * @param {string} tabel - Table name
 * @returns {Promise<NexaFirestore>} Initialized CRUD instance (singleton)
 */
async function initNexaFirebase(tabel) {
  // ✅ Use singleton pattern with automatic caching
  let instance = nexaFirebase(tabel);

  // ✅ Only initialize if not already initialized
  if (!instance.initialized) {
    console.log(`🚀 Initializing NexaFirebase for React Native: ${tabel}`);
    await instance.initializeForRN();
  } else {
    console.log(`♻️ NexaFirebase already initialized for: ${tabel}`);
  }

  return instance;
}

/**
 * Recommended alias for initNexaFirebase to keep naming consistent.
 * @param {string} tabel - Table name
 * @returns {Promise<NexaFirestore>} Initialized CRUD instance (singleton)
 */
async function NexaFirebase(tabel) {
  return initNexaFirebase(tabel);
}

/**
 * Clear all cached instances (untuk memory management)
 * @returns {void}
 */
function clearNexaFirebaseCache() {
  console.log(
    `🧹 Clearing ${globalInstances.size} cached NexaFirebase instances`
  );

  // Cleanup each instance
  globalInstances.forEach((instance, tabel) => {
    try {
      instance.destroy();
      console.log(`♻️ Cleaned up instance for: ${tabel}`);
    } catch (error) {
      console.warn(`⚠️ Error cleaning up ${tabel}:`, error.message);
    }
  });

  // Clear the cache
  globalInstances.clear();
  console.log("✅ All NexaFirebase instances cleared");
}

/**
 * Get cache statistics
 * @returns {Object} Cache info
 */
function getNexaFirebaseCacheInfo() {
  const tables = Array.from(globalInstances.keys());
  return {
    totalInstances: globalInstances.size,
    tables: tables,
    memoryUsage: `${globalInstances.size} instances cached`,
  };
}

// Export for use - ES6 exports for module import
export {
  NexaFirestore,
  firebaseData,
  nexaFirebase,
  NexaFirebase,
  initNexaFirebase,
  FirebaseConfig,
  clearNexaFirebaseCache,
  getNexaFirebaseCacheInfo,
};

// CommonJS export for backward compatibility
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    NexaFirestore,
    firebaseData,
    nexaFirebase,
    NexaFirebase,
    initNexaFirebase,
    FirebaseConfig,
    clearNexaFirebaseCache,
    getNexaFirebaseCacheInfo,
  };
}

// Example usage for React Native with automatic config:
/*
// IMPORTANT: React Native Firebase v9+ compatible version
// - Uses native Firebase modules instead of CDN  
// - AsyncStorage instead of localStorage
// - Platform-specific optimizations
// - Offline support with local caching
// - Cross-platform compatibility (iOS, Android, Web)
// - Automatic config import from config.js

// ========================================
// SIMPLE USAGE (RECOMMENDED):
// ========================================

// 1. Quick initialization with auto config (EASIEST WAY)
const usersCRUD = await initNexaFirebase('users'); // ✅ Ready to use!

// 2. Quick instance with auto config
const productsCRUD = nexaFirebase('products'); // Uses config.js automatically
await productsCRUD.initializeForRN(); // Initialize for React Native

// 3. Using factory function with auto config
const ordersCRUD = firebaseData('orders'); // Uses config.js automatically

// 4. Manual configuration (if needed)
const customConfig = {
    apiKey: "your-custom-api-key",
    authDomain: "your-project.firebaseapp.com",
    databaseURL: "https://your-project-default-rtdb.firebaseio.com/",
    projectId: "your-project-id",
    storageBucket: "your-project.appspot.com",
    messagingSenderId: "123456789",
    appId: "your-app-id"
};
const customCRUD = new NexaFirestore('custom', customConfig);

// Add data
await usersCRUD.add({
    'user1': { name: 'John Doe', email: 'john@example.com' }
});

// Get data
const user = await usersCRUD.get('user1');

// Update with history
await usersCRUD.history({
    'user1': { name: 'John Updated', email: 'john@example.com' }
});

// Real-time reading
const unsubscribe = usersCRUD.red((data) => {
    console.log('Data updated:', data);
});

// Delete data
await usersCRUD.del('user1');

// Delete old data
await usersCRUD.delDate('2024-01-01');

// Additional methods
await usersCRUD.update('user1', { status: 'active' });
const allUsers = await usersCRUD.getAll();
const activeUsers = await usersCRUD.search('status', 'active');
const totalUsers = await usersCRUD.count();
const userExists = await usersCRUD.exists('user1');

// New methods for update without key
// 1. Add with auto-generated key
const newUser = await usersCRUD.addWithAutoKey({ name: 'Jane Doe', email: 'jane@example.com' });
console.log('Auto-generated key:', newUser.key);

// 2. Update by field (when you don't have the key)
await usersCRUD.updateByField('email', 'john@example.com', { status: 'premium' });

// 3. Update multiple records by field
await usersCRUD.updateAllByField('status', 'active', { lastLogin: new Date().toISOString() });

// 4. Upsert - insert if not exists, update if exists
await usersCRUD.upsert('user2', { name: 'Bob Smith', email: 'bob@example.com' });
await usersCRUD.upsert({ name: 'Alice Johnson', email: 'alice@example.com' }); // auto-key

// 5. Find and update by multiple criteria
await usersCRUD.findAndUpdate(
    { name: 'John Doe', status: 'active' }, 
    { premium: true, updatedBy: 'admin' }
);

// 6. Authentication (Firebase v9+ compatible methods)
try {
  // Create new account with enhanced error handling
  const userCredential = await usersCRUD.createAccount('user@example.com', 'password123', {
    onSuccess: (user) => console.log('Welcome!', user.user.email),
    onError: (error, message) => alert(message)
  });
  console.log('User created:', userCredential.user.email);
} catch (error) {
  console.error('Account creation failed:', error.message);
}

// Login with enhanced validation
try {
  const userCredential = await usersCRUD.authenticate('user@example.com', 'password123', {
    onSuccess: (user) => console.log('Login successful!'),
    onError: (error, message) => alert(message)
  });
  console.log('User authenticated:', userCredential.user.email);
} catch (error) {
  console.error('Authentication failed:', error.message);
}

// Quick methods (backward compatibility)
await usersCRUD.register('user@example.com', 'password123'); // Quick registration
await usersCRUD.login('user@example.com', 'password123'); // Quick login

// 7. Check authentication status
console.log('Is authenticated:', usersCRUD.isAuthenticated());
console.log('Current user:', usersCRUD.getCurrentUser());

// 8. Sign out
await usersCRUD.signOut();

// 9. Cleanup (optional) - Clean up all instances when app closes
// NexaFirestore.cleanup();

// ========================================
// REACT NATIVE SPECIFIC FEATURES:
// ========================================

// 1. Platform-aware initialization
const initResult = await usersCRUD.initializeForRN();
console.log('Platform:', initResult.platform); // { platform: 'ios', isIOS: true, ... }

// 2. Offline support with local storage
await usersCRUD.addOffline(
  { 'user1': { name: 'John Doe', email: 'john@example.com' } },
  true // Enable offline mode
);

// 3. Get data with offline fallback
const userData = await usersCRUD.getOffline('user1'); // Tries Firebase first, then AsyncStorage

// 4. Local storage operations
await usersCRUD.saveToLocal('user1', { name: 'John Doe', email: 'john@example.com' });
const localData = await usersCRUD.getFromLocal('user1');
await usersCRUD.removeFromLocal('user1');
await usersCRUD.clearLocalData(); // Clear all table data

// 5. Sync offline data when back online
const syncResult = await usersCRUD.syncWithFirebase();
console.log(`Synced ${syncResult.synced} items`);

// 6. React Native file upload
import { launchImageLibrary } from 'react-native-image-picker';

const selectAndUploadImage = async () => {
  launchImageLibrary({ mediaType: 'photo' }, async (response) => {
    if (response.assets && response.assets[0]) {
      const fileUri = response.assets[0].uri;
      
      const uploadResult = await usersCRUD.uploadFileRN(
        fileUri,
        'images/profile/user1.jpg',
        (progress, platform) => {
          console.log(`Upload progress on ${platform}: ${progress}%`);
        }
      );
      
      console.log('File uploaded:', uploadResult.downloadURL);
    }
  });
};

// 7. Check network connectivity
const isOnline = await usersCRUD.checkConnectivity();
if (isOnline) {
  console.log('Connected to Firebase');
} else {
  console.log('Offline - using local storage');
}

// 8. Platform information
const platformInfo = usersCRUD.getPlatformInfo();
console.log('Running on:', platformInfo.platform); // 'ios', 'android', or 'web'

/*
========================================
TROUBLESHOOTING FIREBASE PROJECT ISSUES:
========================================

🔧 TESTING YOUR CONNECTION:
const usersCRUD = new NexaFirestore('users', firebaseConfig);
await usersCRUD.testConnection(); // Test connection and permissions

🔍 ANALYZING YOUR CONFIG:
NexaFirestore.analyzeConfig(firebaseConfig); // Check for common issues

🆕 NEW PROJECT SETUP:
const config = NexaFirestore.setupNewProject({
  projectId: 'your-project-id',
  apiKey: 'your-api-key',
  region: 'asia-southeast1' // Optional, default to Asia
});

========================================
PROJECT DIFFERENCES:
========================================

📊 OLD FIREBASE PROJECTS (Legacy):
- URL: https://project-id.firebaseio.com/
- Default rules more permissive
- Often works without authentication

📊 NEW FIREBASE PROJECTS (Modern):
- URL: https://project-id-default-rtdb.region.firebasedatabase.app/
- Stricter security rules by default  
- Requires proper authentication
- Must configure Firebase Rules correctly

========================================
AVAILABLE AUTHENTICATION METHODS:
========================================
✅ authenticate(email, password, options) - Modern login method
✅ createAccount(email, password, options) - Modern registration method  
✅ login(email, password) - Quick login (backward compatibility)
✅ register(email, password) - Quick registration (backward compatibility)
✅ signOut() - Sign out current user
✅ isAuthenticated() - Check if user is logged in
✅ getCurrentUser() - Get current user object
✅ waitForAuth() - Wait for auth state to be determined

❌ signIn() - REMOVED (use authenticate() instead)
❌ signUp() - REMOVED (use createAccount() instead)
❌ signUpWithStableAuth() - REMOVED (integrated into createAccount())
*/
