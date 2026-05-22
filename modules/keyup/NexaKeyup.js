/**
 * NexaKeyup - Class untuk menangani event keyup pada input field
 * Handler generik untuk keyup events tanpa fungsi slug generation
 * (Slug generation sudah ditangani oleh NexaSlug.js)
 * 
 * @example
 * // Penggunaan dengan konfigurasi manual
 * const keyupHandler = NexaKeyup.create({
 *   targetIds: ['title', 'description'],
 *   callback: (fieldId, value) => {
 *     console.log(`Field ${fieldId} changed: ${value}`);
 *   }
 * });
 * 
 * @example
 * // Penggunaan dengan instantiation manual
 * const keyupHandler = new NexaKeyup({
 *   targetIds: ['nama'],
 *   callback: (fieldId, value) => {
 *     // Custom handler logic
 *   },
 *   delay: 200
 * });
 * keyupHandler.init();
 * 
 * @example
 * // Penggunaan otomatis dari storage.form (RECOMMENDED)
 * const keyupHandler = NexaKeyup.fromFormData(storage.form, {
 *   callback: (fieldId, value, event) => {
 *     console.log(`Field ${fieldId} changed: ${value}`);
 *     // Custom logic untuk setiap keyup
 *   },
 *   delay: 100,
 *   karakter: 150,  // Limit maksimal 150 karakter
 *   stripHtml: true  // Hapus HTML tags dari value yang di-copy (optional)
 * });
 */
class NexaKeyup {
  /**
   * @param {Object} config - Konfigurasi keyup handler
   * @param {Array<string>} config.targetIds - Array ID field yang akan di-handle (contoh: ['title', 'description'])
   * @param {Function} config.callback - Callback function yang dipanggil saat keyup (fieldId, value, event)
   * @param {number} config.delay - Delay untuk initialize (ms, default: 100)
   * @param {number} config.karakter - Jumlah maksimal karakter yang diizinkan (default: tidak ada limit)
   * @param {number} config.carakter - Alias untuk karakter (typo support)
   */
  constructor(config = {}) {
    this.targetIds = config.targetIds || [];
    this.callback = config.callback || null;
    this.delay = config.delay || 100;
    // Support untuk 'karakter' dan 'carakter' (typo)
    this.karakter = config.karakter || config.carakter || null;
    // Daftar field yang diizinkan untuk dibatasi (HANYA target fields)
    this.allowedLimitFields = [];
  }

  /**
   * Get value dari field berdasarkan ID
   * @param {string} fieldId - ID field
   * @returns {string} - Value dari field
   */
  getFieldValue(fieldId) {
    try {
      return NXUI.id(fieldId).val() || '';
    } catch (error) {
      console.warn(`Field dengan ID "${fieldId}" tidak ditemukan`);
      return '';
    }
  }

  /**
   * Event handler untuk keyup event
   */
  handleKeyup(e) {
    const fieldId = e.target.id || e.target.getAttribute('id') || '';
    const fieldValue = e.target.value || '';
    
    if (this.callback && typeof this.callback === 'function') {
      this.callback(fieldId, fieldValue, e);
    }
  }

  /**
   * Event handler untuk limit karakter
   * HANYA bekerja untuk field yang ada di allowedLimitFields
   */
  handleCharacterLimit(e) {
    if (!this.karakter || this.karakter <= 0) {
      return;
    }

    const element = e.target;
    const fieldId = element.id || element.getAttribute('id') || '';
    
    // PENTING: Hanya batasi jika field ada di allowedLimitFields (target fields)
    // JANGAN batasi source fields seperti "title"
    if (!this.allowedLimitFields.includes(fieldId)) {
      return; // Field ini tidak diizinkan untuk dibatasi, langsung return
    }

    const currentValue = element.value || '';
    
    if (currentValue.length > this.karakter) {
      // Truncate value ke batas karakter
      const truncatedValue = currentValue.substring(0, this.karakter);
      element.value = truncatedValue;
      
      // Trigger input event untuk memastikan event lainnya ter-trigger
      element.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  /**
   * Initialize event handlers untuk semua field
   */
  init() {
    setTimeout(() => {
      try {
        let attachedCount = 0;
        
        // Event handler untuk semua targetIds (source fields)
        this.targetIds.forEach(fieldId => {
          try {
            // Coba dengan native DOM element
            const element = document.getElementById(fieldId);
            if (element) {
              // Handler untuk keyup event (tanpa limit pada source field)
              element.addEventListener('keyup', (e) => {
                this.handleKeyup(e);
              });
              
              attachedCount++;
            } else {
              // Fallback ke NXUI.id jika native tidak ditemukan
              try {
                const nxElement = NXUI.id(fieldId);
                if (nxElement && nxElement.length > 0) {
                  nxElement.on("keyup", (e) => {
                    this.handleKeyup(e);
                  });
                  
                  attachedCount++;
                }
              } catch (nxError) {
                console.warn(`⚠️ Field "${fieldId}" tidak ditemukan`);
              }
            }
          } catch (error) {
            console.warn(`⚠️ Error attaching event ke field "${fieldId}":`, error);
          }
        });
        
        if (attachedCount === 0 && this.targetIds.length > 0) {
          console.warn('⚠️ NexaKeyup: Tidak ada event handler yang berhasil di-attach');
        }
      } catch (error) {
        console.error("❌ Error initializing NexaKeyup:", error);
      }
    }, this.delay);
  }

  /**
   * Attach limit karakter ke target field (field yang menerima copy)
   * @param {string} targetFieldId - ID field target
   */
  attachLimitToTarget(targetFieldId) {
    if (!this.karakter || this.karakter <= 0 || !targetFieldId) {
      return;
    }

    // Tambahkan ke daftar field yang diizinkan untuk dibatasi
    if (!this.allowedLimitFields.includes(targetFieldId)) {
      this.allowedLimitFields.push(targetFieldId);
    }

    setTimeout(() => {
      try {
        const element = document.getElementById(targetFieldId);
        if (element) {
          // Handler untuk limit karakter pada target field
          element.addEventListener('input', (e) => {
            this.handleCharacterLimit(e);
          });
        } else {
          // Fallback ke NXUI.id
          try {
            const nxElement = NXUI.id(targetFieldId);
            if (nxElement && nxElement.length > 0) {
              nxElement.on("input", (e) => {
                this.handleCharacterLimit(e);
              });
            }
          } catch (nxError) {
            console.warn(`⚠️ Target field "${targetFieldId}" tidak ditemukan untuk limit`);
          }
        }
      } catch (error) {
        console.warn(`⚠️ Error attaching limit ke target field "${targetFieldId}":`, error);
      }
    }, this.delay + 50);
  }

  /**
   * Static method untuk create dan init langsung
   * @param {Object} config - Konfigurasi
   * @returns {NexaKeyup} - Instance NexaKeyup
   */
  static create(config) {
    const instance = new NexaKeyup(config);
    instance.init();
    return instance;
  }

  /**
   * Strip HTML tags dari text dan ambil hanya text content
   * @param {string} htmlString - String yang mungkin mengandung HTML tags
   * @returns {string} - Text tanpa HTML tags
   */
  static stripHtmlTags(htmlString) {
    if (!htmlString || typeof htmlString !== 'string') {
      return '';
    }
    
    // Buat temporary DOM element untuk extract text (metode paling aman)
    try {
      const tmp = document.createElement('div');
      tmp.innerHTML = htmlString;
      let text = tmp.textContent || tmp.innerText || '';
      // Bersihkan whitespace berlebihan
      text = text.replace(/\s+/g, ' ').trim();
      return text;
    } catch (error) {
      // Fallback: gunakan regex untuk strip HTML tags
      return htmlString.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    }
  }

  /**
   * Static method untuk auto-configure dari storage.form
   * Menerima storage.form dan otomatis extract targetIds dari konfigurasi slug
   * @param {Object} formData - Object dari storage.form
   * @param {Object} options - Opsi tambahan (delay, callback, karakter, dll)
   * @param {number} options.karakter - Jumlah maksimal karakter yang diizinkan
   * @param {number} options.carakter - Alias untuk karakter (typo support)
   * @param {boolean} options.stripHtml - Hapus HTML tags dari value yang di-copy (default: false)
   * @param {boolean} options.cleanHtml - Alias untuk stripHtml
   * @param {boolean} options.removeHtml - Alias untuk stripHtml
   * @returns {NexaKeyup|null} - Instance NexaKeyup atau null jika gagal
   */
  static fromFormData(formData, options = {}) {
    try {
      // Convert formData object to array
      let formArray = [];
      if (formData) {
        if (Array.isArray(formData)) {
          formArray = formData;
        } else if (typeof formData === 'object') {
          formArray = Object.values(formData);
        }
      }

      // Find keyup type item untuk extract targetIds
      const result2 = formArray
        .filter(item => item.type === 'keyup')
        .map(item => ({
          ...item.keyup,
        }));
      
      // Extract source fields (variabel) dan target fields (failed)
      let sourceFields = [];
      let targetFields = [];
      
      if (result2 && result2.length > 0) {
        // Ambil source fields dari variabel.array
        sourceFields = result2[0]?.variabel?.array || [];
        // Ambil target fields dari failed.array
        targetFields = result2[0]?.failed?.array || [];
      } else {
        // Fallback: ambil semua field dari formData
        if (typeof formData === 'object' && !Array.isArray(formData)) {
          sourceFields = Object.keys(formData);
        } else {
          sourceFields = formArray.map(item => item.name).filter(Boolean);
        }
      }

      // Gunakan sourceFields sebagai targetIds untuk attach event handler
      const targetIds = sourceFields;

      if (targetIds.length === 0) {
        console.warn('⚠️ NexaKeyup: Tidak ada targetIds ditemukan');
        return null;
      }

      // Ambil limit karakter dari options (support 'karakter' dan 'carakter')
      const karakterLimit = options.karakter || options.carakter || null;
      // Opsi untuk strip HTML tags (support 'stripHtml', 'cleanHtml', 'removeHtml')
      // Default: false, hanya aktif jika secara eksplisit di-set true
      const stripHtml = options.stripHtml === true || options.cleanHtml === true || options.removeHtml === true;

      // Buat callback otomatis untuk copy value dari source ke target
      const autoCallback = (fieldId, fieldValue, event) => {
        // Jika ada custom callback, jalankan dengan value original (tanpa limit)
        if (options.callback && typeof options.callback === 'function') {
          options.callback(fieldId, fieldValue, event);
        }
        
        // Copy value dari source field ke target field
        if (targetFields.length > 0 && sourceFields.includes(fieldId)) {
          // Cari index source field yang sedang di-trigger
          const sourceIndex = sourceFields.indexOf(fieldId);
          // Ambil target field yang sesuai dengan index yang sama
          const targetFieldId = targetFields[sourceIndex];
          
          if (targetFieldId) {
            try {
              // STEP 1: Strip HTML tags jika opsi diaktifkan
              let cleanValue = fieldValue;
              if (stripHtml) {
                cleanValue = NexaKeyup.stripHtmlTags(fieldValue);
              }
              
              // STEP 2: Apply limit karakter HANYA pada value yang di-copy ke target
              let limitedValue = cleanValue;
              // Pastikan karakterLimit adalah number yang valid
              const limitNum = (karakterLimit && !isNaN(karakterLimit)) ? parseInt(karakterLimit, 10) : null;
              
              if (limitNum && limitNum > 0) {
                // SELALU limit jika ada karakterLimit dan value melebihi limit
                if (cleanValue && cleanValue.length > limitNum) {
                  limitedValue = cleanValue.substring(0, limitNum);
                } else {
                  limitedValue = cleanValue; // Tidak perlu di-limit
                }
              } else {
                limitedValue = cleanValue; // Tidak ada limit
              }
              
              // Set value ke target field (dengan limit jika ada)
              const targetElement = document.getElementById(targetFieldId);
              if (targetElement) {
                // PASTIKAN value tidak melebihi limit
                if (limitNum && limitNum > 0 && limitedValue.length > limitNum) {
                  limitedValue = limitedValue.substring(0, limitNum);
                }
                targetElement.value = limitedValue;
                
                // Double check setelah set - jika masih melebihi, potong lagi
                if (limitNum && limitNum > 0) {
                  setTimeout(() => {
                    if (targetElement && targetElement.value && targetElement.value.length > limitNum) {
                      targetElement.value = targetElement.value.substring(0, limitNum);
                    }
                  }, 10);
                }
                
                // Trigger input event untuk memastikan event lainnya ter-trigger
                targetElement.dispatchEvent(new Event('input', { bubbles: true }));
              } else {
                // Fallback menggunakan NXUI.id
                try {
                  // PASTIKAN value tidak melebihi limit
                  if (limitNum && limitNum > 0 && limitedValue.length > limitNum) {
                    limitedValue = limitedValue.substring(0, limitNum);
                  }
                  NXUI.id(targetFieldId).val(limitedValue);
                  NXUI.id(targetFieldId).trigger('input');
                } catch (nxError) {
                  console.warn(`⚠️ Target field "${targetFieldId}" tidak ditemukan`);
                }
              }
            } catch (error) {
              console.warn(`⚠️ Error copying value to "${targetFieldId}":`, error);
            }
          }
        }
      };

      // Create dan init NexaKeyup dengan delay lebih lama untuk memastikan DOM ready
      // JANGAN set karakter di instance utama karena itu akan membatasi source field
      // Karakter limit HANYA diterapkan di target field via attachLimitToTarget
      const instance = NexaKeyup.create({
        targetIds: targetIds,
        callback: autoCallback,
        delay: options.delay || 300
        // TIDAK set karakter di sini untuk menghindari limit di source field
      });

      // Attach limit karakter HANYA ke target fields (bukan source fields)
      if (karakterLimit && karakterLimit > 0 && targetFields.length > 0) {
        targetFields.forEach(targetFieldId => {
          if (targetFieldId) {
            // Set karakter di instance hanya untuk attachLimitToTarget
            instance.karakter = karakterLimit;
            instance.attachLimitToTarget(targetFieldId);
          }
        });
      }

      return instance;

    } catch (error) {
      console.error('❌ Error creating NexaKeyup from formData:', error);
      return null;
    }
  }
}

// Export ES6 module
export { NexaKeyup };

// Export untuk CommonJS (untuk kompatibilitas)
if (typeof module !== 'undefined' && module.exports) {
  module.exports = NexaKeyup;
}

// Global untuk NXUI (untuk kompatibilitas)
if (typeof NXUI !== 'undefined') {
  NXUI.NexaKeyup = NexaKeyup;
}

