/**
 * NexaSlug - Class untuk generate slug secara dinamis dari input field
 * Mendukung format tanggal + slug (YYYY/MM/DD/slug-text)
 * 
 * @example
 * // Penggunaan otomatis dari storage.form (RECOMMENDED)
 * const slugGenerator = NexaSlug.fromFormData(storage.form);
 * 
 * @example
 * // Penggunaan dengan konfigurasi manual
 * const slugGenerator = NexaSlug.create({
 *   targetIds: ['title', 'pubdate'],
 *   failedIds: ['slug'],
 *   dateFieldId: 'pubdate'
 * });
 * 
 * @example
 * // Penggunaan dengan instantiation manual
 * const slugGenerator = new NexaSlug({
 *   targetIds: ['nama', 'tanggal'],
 *   failedIds: ['url_slug'],
 *   dateFieldId: 'tanggal',
 *   delay: 200
 * });
 * slugGenerator.init();
 */
class NexaSlug {
  /**
   * @param {Object} config - Konfigurasi slug generator
   * @param {Array<string>} config.targetIds - Array ID field sumber (contoh: ['title', 'pubdate'])
   * @param {Array<string>} config.failedIds - Array ID field target/output slug (contoh: ['slug'])
   * @param {string} config.dateFieldId - ID field untuk tanggal (default: 'pubdate')
   * @param {number} config.delay - Delay untuk initialize (ms, default: 100)
   */
  constructor(config = {}) {
    this.targetIds = config.targetIds || [];
    this.failedIds = config.failedIds || [];
    // dateFieldId bisa null jika tidak ada field date
    this.dateFieldId = config.dateFieldId || null;
    this.delay = config.delay || 100;
    
    // Field slug (target/output)
    this.slugFieldId = this.failedIds[0] || 'slug';
    
    // Field source untuk slug (bukan date field)
    // Jika dateFieldId null, ambil field pertama yang bukan slug
    if (this.dateFieldId) {
      this.slugSourceField = this.targetIds.find(id => id !== this.dateFieldId) || this.targetIds[0] || 'title';
    } else {
      this.slugSourceField = this.targetIds[0] || 'title';
    }
  }

  /**
   * Convert text ke format slug
   * @param {string} text - Text yang akan dikonversi
   * @returns {string} - Slug text
   */
  generateSlug(text) {
    if (!text || typeof text !== 'string') return '';
    
    return text
      .toLowerCase()
      .trim()
      .replace(/\s+/g, '-')           // Ganti spasi dengan dash
      .replace(/[^\w\-]+/g, '')       // Hapus karakter khusus
      .replace(/\-\-+/g, '-')         // Ganti multiple dash dengan single dash
      .replace(/^-+/, '')             // Hapus dash di awal
      .replace(/-+$/, '');            // Hapus dash di akhir
  }

  /**
   * Format tanggal dari YYYY-MM-DD ke YYYY/MM/DD
   * @param {string} dateValue - Tanggal dalam format YYYY-MM-DD
   * @returns {string|null} - Tanggal dalam format YYYY/MM/DD atau null jika invalid
   */
  formatDate(dateValue) {
    if (!dateValue || typeof dateValue !== 'string') return null;
    
    const dateParts = dateValue.split('-');
    if (dateParts.length === 3) {
      return `${dateParts[0]}/${dateParts[1]}/${dateParts[2]}`;
    }
    return null;
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
   * Set value ke slug field
   * @param {string} value - Value yang akan di-set
   */
  setSlugValue(value) {
    try {
      NXUI.id(this.slugFieldId).val(value);
    } catch (error) {
      console.warn(`Field slug dengan ID "${this.slugFieldId}" tidak ditemukan`);
    }
  }

  /**
   * Generate final slug dengan format tanggal + slug jika diperlukan
   * @param {string} slugText - Text slug
   * @returns {string} - Final slug
   */
  generateFinalSlug(slugText) {
    // Jika tidak ada dateFieldId, return slug saja
    if (!this.dateFieldId) {
      return slugText || '';
    }
    
    const pubdateValue = this.getFieldValue(this.dateFieldId);
    const formattedDate = this.formatDate(pubdateValue);
    
    if (formattedDate) {
      // Jika ada tanggal, gabungkan dengan slug
      return slugText ? `${formattedDate}/${slugText}` : formattedDate;
    } else {
      // Jika tidak ada tanggal, return slug saja
      return slugText || '';
    }
  }

  /**
   * Event handler untuk field source (keyup)
   */
  handleSourceFieldChange(e) {
    const fieldValue = e.target.value;
    const slugFromField = this.generateSlug(fieldValue);
    const finalSlug = this.generateFinalSlug(slugFromField);
    
    this.setSlugValue(finalSlug);
  }

  /**
   * Event handler untuk date field (input)
   */
  handleDateFieldChange(e) {
    const pubdateValue = e.target.value;
    const formattedDate = this.formatDate(pubdateValue);
    
    if (formattedDate) {
      // Ambil slug dari source field jika ada
      const sourceValue = this.getFieldValue(this.slugSourceField);
      let slugPart = '';
      
      if (sourceValue) {
        // Generate slug dari source field
        slugPart = this.generateSlug(sourceValue);
      } else {
        // Jika source field kosong, ambil dari slug field (hilangkan bagian tanggal jika ada)
        const currentSlug = this.getFieldValue(this.slugFieldId);
        // Hapus format tanggal dari depan jika ada (YYYY/MM/DD/)
        slugPart = currentSlug.replace(/^\d{4}\/\d{2}\/\d{2}\//, '');
      }
      
      // Gabungkan tanggal dengan slug
      const finalSlug = slugPart ? `${formattedDate}/${slugPart}` : formattedDate;
      this.setSlugValue(finalSlug);
    }
  }

  /**
   * Initialize event handlers untuk semua field
   */
  init() {
    setTimeout(() => {
      try {
        // Event handler untuk field yang menghasilkan slug (biasanya 'title')
        if (this.targetIds.includes(this.slugSourceField)) {
          NXUI.id(this.slugSourceField).on("keyup", (e) => {
            this.handleSourceFieldChange(e);
          });
        }
        
        // Event handler untuk date field (hanya jika dateFieldId ada)
        if (this.dateFieldId) {
          const pubdateField = this.targetIds.find(id => id === this.dateFieldId);
          if (pubdateField) {
            NXUI.id(pubdateField).on("input", (e) => {
              this.handleDateFieldChange(e);
            });
          }
        }
      } catch (error) {
        console.error("❌ Error initializing NexaSlug:", error);
      }
    }, this.delay);
  }

  /**
   * Static method untuk create dan init langsung
   * @param {Object} config - Konfigurasi
   * @returns {NexaSlug} - Instance NexaSlug
   */
  static create(config) {
    const instance = new NexaSlug(config);
    instance.init();
    return instance;
  }

  /**
   * Static method untuk auto-configure dari storage.form
   * Menerima storage.form dan otomatis extract semua konfigurasi yang diperlukan
   * @param {Object} formData - Object dari storage.form
   * @param {Object} options - Opsi tambahan (delay, dll)
   * @returns {NexaSlug|null} - Instance NexaSlug atau null jika gagal
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

      // Find slug type item
      const result2 = formArray
        .filter(item => item.type === 'slug')
        .map(item => ({
          ...item.slug,
        }));

      if (!result2 || result2.length === 0) {
        console.warn('⚠️ Tidak ada field dengan type "slug" ditemukan');
        return null;
      }

      // Extract failedIds dan targetIds
      const failedIds = result2[0]?.failed?.array || [];
      const targetIds = result2[0]?.variabel?.array || [];

      if (failedIds.length === 0 || targetIds.length === 0) {
        console.warn('⚠️ failedIds atau targetIds kosong');
        return null;
      }

      // Find date field dari formData
      const findDateFieldId = () => {
        // Loop melalui targetIds dan cek field di formData
        for (const fieldId of targetIds) {
          const field = formData[fieldId];
          if (field) {
            const fieldType = field.type || '';
            // Cek apakah field bertipe date atau datetime
            if (fieldType === 'date' || fieldType === 'datetime' || fieldType === 'datetime-local') {
              return fieldId;
            }
          }
        }
        
        // Fallback: cari dari targetIds yang mengandung kata 'date'
        const dateFieldFromName = targetIds.find(id => 
          id.toLowerCase().includes('date') || id.toLowerCase().includes('tanggal')
        );
        
        return dateFieldFromName || null;
      };

      const dateFieldId = findDateFieldId();

      // Create dan init NexaSlug
      return NexaSlug.create({
        targetIds: targetIds,
        failedIds: failedIds,
        dateFieldId: dateFieldId,
        delay: options.delay || 100
      });

    } catch (error) {
      console.error('❌ Error creating NexaSlug from formData:', error);
      return null;
    }
  }
}

// Export ES6 module
export { NexaSlug };

// Export untuk CommonJS (untuk kompatibilitas)
if (typeof module !== 'undefined' && module.exports) {
  module.exports = NexaSlug;
}

// Global untuk NXUI (untuk kompatibilitas)
if (typeof NXUI !== 'undefined') {
  NXUI.NexaSlug = NexaSlug;
}

