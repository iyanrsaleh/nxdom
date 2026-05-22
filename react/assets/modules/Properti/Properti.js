import { IndexedDBManager } from "../Storage/IndexDB";

const createApiSyncClient = () => ({
  put: async () => {
    throw new Error("Properti API sync is disabled.");
  },
});

/**
 * Class Properti - Mengelola data properti aplikasi
 * Menyediakan interface yang mudah untuk mengakses dan mengupdate properti
 * dengan caching otomatis dan update detection berdasarkan updatedAt
 */
class Properti {
  constructor() {
    this.storeName = "bucketsStore";
    this.key = "Apps";
    this.cache = null;
    this.lastFetchTime = null;
    this.isFetching = false; // Flag untuk mencegah multiple concurrent requests
    this.fetchPromise = null; // Promise untuk request yang sedang berjalan
    this.lastFetchAttempt = null; // Timestamp dari request terakhir
    this.fetchCooldown = 5000; // Cooldown 5 detik antara requests (dalam milliseconds)
    this.enableApiSync = false; // Nonaktifkan dulu sinkronisasi API properti (endpoint 404)
  }

  /**
   * Mendapatkan properti dari cache atau API
   * Otomatis update jika ada perubahan berdasarkan updatedAt
   * @returns {Promise<Object|null>} Data properti
   */
  async get() {
    // Jika sedang fetching, tunggu request yang sedang berjalan
    if (this.isFetching && this.fetchPromise) {
      try {
        return await this.fetchPromise;
      } catch (error) {
        // Jika request yang sedang berjalan gagal, lanjutkan dengan cache
      }
    }

    // Cek cache terlebih dahulu untuk return cepat
    const cachedData = await IndexedDBManager.getAuto(this.storeName, this.key);
    
    // Jika ada cache yang valid, return dulu (non-blocking)
    if (cachedData && typeof cachedData === 'object' && Object.keys(cachedData).length > 0) {
      this.cache = cachedData;
      
      // Fetch dari API di background (non-blocking) untuk update cache
      // Hanya fetch jika tidak dalam cooldown period
      const now = Date.now();
      if (!this.lastFetchAttempt || (now - this.lastFetchAttempt) >= this.fetchCooldown) {
        this.fetchFromAPI(cachedData).catch(err => {
          // Silent fail untuk semua error - sudah di-handle di fetchFromAPI
        });
      }
      
      return cachedData;
    }

    // Jika tidak ada cache, harus fetch dari API
    return await this.fetchFromAPI(null);
  }

  /**
   * Fetch data dari API (internal method)
   * @param {Object|null} cachedData - Data cache yang sudah ada (optional)
   * @returns {Promise<Object|null>} Data properti
   */
  async fetchFromAPI(cachedData = null) {
    // API sync dimatikan sementara: gunakan cache/lokal saja
    if (!this.enableApiSync) {
      if (cachedData && typeof cachedData === 'object' && Object.keys(cachedData).length > 0) {
        this.cache = cachedData;
        return cachedData;
      }
      if (this.cache) return this.cache;
      return await IndexedDBManager.getAuto(this.storeName, this.key);
    }

    // Set flag untuk mencegah multiple concurrent requests
    if (this.isFetching) {
      return this.fetchPromise;
    }

    // Cooldown: jika baru saja fetch (dalam cooldown period), skip fetch
    const now = Date.now();
    if (this.lastFetchAttempt && (now - this.lastFetchAttempt) < this.fetchCooldown) {
      // Masih dalam cooldown, return cache jika ada
      if (cachedData && typeof cachedData === 'object' && Object.keys(cachedData).length > 0) {
        return cachedData;
      }
      if (this.cache) {
        return this.cache;
      }
      return null;
    }

    this.lastFetchAttempt = now;
    this.isFetching = true;
    this.fetchPromise = (async () => {
      try {
        const api = createApiSyncClient();

        const response = await api.put('support/properti', {
          id: 1
        });

        // Bandingkan updatedAt untuk menentukan apakah perlu update
        const needsUpdate = !cachedData || 
          !cachedData.updatedAt || 
          !response.data?.updatedAt ||
          new Date(response.data.updatedAt) > new Date(cachedData.updatedAt);
        
        if (needsUpdate && response.data) {
          // Jika ada perubahan (updatedAt lebih baru), update data di nexaDb
          await IndexedDBManager.setAuto(this.storeName, response.data);
          this.cache = response.data;
          this.lastFetchTime = new Date();
          return response.data;
        } else if (cachedData && (typeof cachedData === 'object' && Object.keys(cachedData).length > 0)) {
          // Jika tidak ada perubahan, gunakan data dari cache
          this.cache = cachedData;
          return cachedData;
        } else if (response.data) {
          // Fallback: jika tidak ada data di cache, gunakan data dari API
          await IndexedDBManager.setAuto(this.storeName, response.data);
          this.cache = response.data;
          this.lastFetchTime = new Date();
          return response.data;
        }

        // Jika response tidak valid, return cache atau null
        return cachedData || null;
      } catch (error) {
        // Handle timeout error dengan lebih baik
        if (error.data?.type === 'TIMEOUT_ERROR' || error.code === 408 || error.name === 'TimeoutError') {
          // Silent fail untuk timeout - tidak perlu log karena sudah di-handle di NexaStorage
          // Return cache jika ada, jika tidak return null
          if (cachedData && typeof cachedData === 'object' && Object.keys(cachedData).length > 0) {
            return cachedData;
          }
          if (this.cache) {
            return this.cache;
          }
          return null;
        }
        
        // Log error untuk debugging (hanya untuk error selain timeout)
        console.error('Error fetching properti:', error);
        
        // Jika error, coba return cache jika ada
        if (cachedData && typeof cachedData === 'object' && Object.keys(cachedData).length > 0) {
          return cachedData;
        }
        if (this.cache) {
          return this.cache;
        }
        return null;
      } finally {
        // Reset flag setelah selesai
        this.isFetching = false;
        this.fetchPromise = null;
      }
    })();

    return this.fetchPromise;
  }

  /**
   * Mendapatkan properti dari cache saja (tanpa fetch API)
   * Lebih cepat untuk akses yang tidak perlu data terbaru
   * @returns {Promise<Object|null>} Data properti dari cache
   */
  async getCache() {
    try {
      const cachedData = await IndexedDBManager.getAuto(this.storeName, this.key);
      if (cachedData && (typeof cachedData === 'object' && Object.keys(cachedData).length > 0)) {
        this.cache = cachedData;
        return cachedData;
      }
      return null;
    } catch (error) {
      console.error('Error getting properti from cache:', error);
      return this.cache || null;
    }
  }

  /**
   * Memperbarui properti di cache dan API
   * @param {Object} data - Data properti yang akan diupdate
   * @returns {Promise<Object>} Data properti yang sudah diupdate
   */
  async update(data) {
    try {
      const existingData = await IndexedDBManager.getAuto(this.storeName, this.key) || {};
      
      // Merge data yang sudah ada dengan data baru
      const updatedData = {
        ...existingData,
        ...data,
        id: this.key,
        updatedAt: new Date().toISOString()
      };

      // Simpan ke cache
      await IndexedDBManager.setAuto(this.storeName, updatedData);
      this.cache = updatedData;

      // API sync dinonaktifkan sementara (endpoint support/properti 404)
      if (this.enableApiSync) {
        const api = createApiSyncClient();
        await api.put('support/properti', updatedData);
      }

      return updatedData;
    } catch (error) {
      console.error('Error updating properti:', error);
      throw error;
    }
  }

  /**
   * Mendapatkan assetColor dari properti
   * @returns {Promise<Object>} Object assetColor
   */
  async getAssetColor() {
    // Coba ambil dari cache dulu untuk response cepat
    const cachedProperti = await this.getCache();
    if (cachedProperti?.assetColor) {
      // Trigger background update tanpa blocking
      this.get().catch(() => {}); // Silent fail
      return cachedProperti.assetColor;
    }
    
    // Jika tidak ada di cache, coba fetch dari API
    const properti = await this.get();
    return properti?.assetColor || {
      backgroundColor: '#24BCA9',
      color: '#009688',
      btnColor: '#e0f2f1',
      deleteColor: '#f44336',
      deleteBtnColor: '#ffebee',
      buttonColor: '#009688',
      buttonTextColor: '#fff',
      buttonPrevColor: '#e0e0e0',
      buttonPrevTextColor: '#666',
    };
  }

  /**
   * Mendapatkan nilai field tertentu dari properti
   * @param {string} field - Nama field yang ingin diambil
   * @param {any} defaultValue - Nilai default jika field tidak ada
   * @returns {Promise<any>} Nilai field
   */
  async getField(field, defaultValue = null) {
    // Coba ambil dari cache dulu untuk response cepat
    const cachedProperti = await this.getCache();
    if (cachedProperti && cachedProperti[field] !== undefined) {
      // Trigger background update tanpa blocking
      this.get().catch(() => {}); // Silent fail
      return cachedProperti[field];
    }
    
    // Jika tidak ada di cache, coba fetch dari API
    const properti = await this.get();
    return properti?.[field] ?? defaultValue;
  }

  /**
   * Clear cache
   */
  clearCache() {
    this.cache = null;
    this.lastFetchTime = null;
  }

  /**
   * Refresh data dari API (force update)
   * @returns {Promise<Object>} Data properti terbaru
   */
  async refresh() {
    this.clearCache();
    return await this.get();
  }

  /**
   * Memperbarui data properti dari bucketsStore ke server
   * Method ini dipanggil setelah data di-update di bucketsStore (misalnya dari refScreen.js)
   * @returns {Promise<Object>} Data properti yang sudah diupdate ke server
   */
  async syncToServer() {
    if (!this.enableApiSync) {
      return await IndexedDBManager.getAuto(this.storeName, this.key);
    }

    try {
      // Ambil data terbaru dari bucketsStore
      const localData = await IndexedDBManager.getAuto(this.storeName, this.key);
      
      if (!localData || (typeof localData === 'object' && Object.keys(localData).length === 0)) {
        console.warn('No local data found to sync to server');
        return null;
      }

      // Siapkan data untuk dikirim ke server
      const dataToSync = {
        ...localData,
        id: this.key,
        updatedAt: new Date().toISOString()
      };

      // Update ke API
      const api = createApiSyncClient();
      
      const response = await api.put('support/properti', dataToSync);

      // Update cache setelah berhasil sync
      if (response?.data) {
        await IndexedDBManager.setAuto(this.storeName, response.data);
        this.cache = response.data;
        this.lastFetchTime = new Date();
        return response.data;
      }

      return dataToSync;
    } catch (error) {
      console.error('Error syncing properti to server:', error);
      throw error;
    }
  }

  /**
   * Memperbarui assetColor ke server
   * Method khusus untuk update assetColor saja
   * @param {Object} assetColor - Object assetColor yang akan diupdate
   * @returns {Promise<Object>} Data properti yang sudah diupdate
   */
  async updateAssetColor(assetColor) {
    try {
      const existingData = await IndexedDBManager.getAuto(this.storeName, this.key) || {};
      
      // Merge assetColor dengan data yang sudah ada
      const updatedData = {
        ...existingData,
        assetColor: assetColor,
        id: this.key,
        updatedAt: new Date().toISOString()
      };

      // Simpan ke cache
      await IndexedDBManager.setAuto(this.storeName, updatedData);
      this.cache = updatedData;

      // Update ke API jika sync diaktifkan
      let response = null;
      if (this.enableApiSync) {
        const api = createApiSyncClient();
        response = await api.put('support/properti', updatedData);
      }

      // Update cache dengan response dari server jika ada
      if (response?.data) {
        await IndexedDBManager.setAuto(this.storeName, response.data);
        this.cache = response.data;
        return response.data;
      }

      return updatedData;
    } catch (error) {
      console.error('Error updating assetColor:', error);
      throw error;
    }
  }
}

// Export singleton instance
const properti = new Properti();

export default Properti;
export { properti };

