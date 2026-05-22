import NexaFilter from "./NexaFilter.js";
import { storageModelStorageData } from "./NexaDom.js";
import { spinner as nexaSpinner } from "./NexaSpinner.js";

export class NexaForge {
  /**
   * Array field sensitif yang harus disembunyikan
   * @private
   */
  static SENSITIVE_FIELDS = [
    "userid",
    "password",
    "passwd",
    "pass",
    "secret",
    "token",
    "api_key",
    "apikey",
    "private_key",
    "access_token",
    "refresh_token",
    "auth_token",
    "session_id",
    "ssn",
    "credit_card",
    "pin",
  ];

  /**
   * Menambahkan field sensitif custom ke daftar field yang disembunyikan
   * @param {string|Array} fields - Field yang akan ditambahkan
   */
  static addSensitiveFields(fields) {
    const fieldsToAdd = Array.isArray(fields) ? fields : [fields];
    fieldsToAdd.forEach((field) => {
      if (
        typeof field === "string" &&
        !NexaForge.SENSITIVE_FIELDS.includes(field.toLowerCase())
      ) {
        NexaForge.SENSITIVE_FIELDS.push(field.toLowerCase());
      }
    });
  }

  /**
   * Menghapus field sensitif dari daftar
   * @param {string|Array} fields - Field yang akan dihapus
   */
  static removeSensitiveFields(fields) {
    const fieldsToRemove = Array.isArray(fields) ? fields : [fields];
    fieldsToRemove.forEach((field) => {
      if (typeof field === "string") {
        const index = NexaForge.SENSITIVE_FIELDS.indexOf(field.toLowerCase());
        if (index > -1) {
          NexaForge.SENSITIVE_FIELDS.splice(index, 1);
        }
      }
    });
  }

  /**
   * Ambil nilai atribut extractor (NexaForge / NexaHtml legacy; HTML5 menormalisasi ke huruf kecil).
   * @param {Element} el
   * @returns {string|null}
   */
  static getNexaForgeAttr(el) {
    if (!el || !el.getAttribute) return null;
    return (
      el.getAttribute("NexaForge") ||
      el.getAttribute("nexaforge") ||
      el.getAttribute("data-nexa-forge") ||
      el.getAttribute("NexaHtml") ||
      el.getAttribute("nexahtml") ||
      el.getAttribute("data-nexa-html")
    );
  }

  /**
   * Inisialisasi blok list template setelah HTML dimuat (mis. lewat NXUI.html).
   * Cari elemen dengan atribut NexaForge / NexaHtml (legacy) + id, lalu `new NexaForge({ elementById, ...lists })`.
   * @param {ParentNode} root - Container yang sudah berisi markup (setelah innerHTML)
   * @param {Record<string, Array>} lists - Data per extractor, mis. `{ user: [{ nama, email }] }`
   * @returns {NexaForge[]} instance yang dibuat
   */
  static hydrate(root, lists = {}) {
    if (!root || typeof root.querySelectorAll !== "function") {
      return [];
    }
    const instances = [];
    const nodes = root.querySelectorAll(
      "[NexaForge],[nexaforge],[data-nexa-forge],[NexaHtml],[nexahtml],[data-nexa-html]"
    );
    nodes.forEach((el) => {
      if (!el.id) {
        console.warn("NexaForge.hydrate: elemen perlu atribut id");
        return;
      }
      try {
        instances.push(
          new NexaForge({
            elementById: el.id,
            ...lists,
          })
        );
      } catch (err) {
        console.warn("NexaForge.hydrate:", err);
      }
    });
    return instances;
  }

  /**
   * Bentuk `queryConfig` untuk `storageModelStorageData` (sama pola dengan NexaDom: `storage: { model, select?, query? }`).
   * @param {{ model: string, select?: *, query?: (q: *) => * }} storage
   * @returns {Record<string, *>}
   */
  static buildStorageQueryConfig(storage) {
    if (!storage || typeof storage.model !== "string" || !storage.model) {
      return {};
    }
    return {
      storageModelTable: storage.model,
      storageModelQueryHook:
        typeof storage.query === "function" ? storage.query : undefined,
      storageModelSelect: Object.prototype.hasOwnProperty.call(
        storage,
        "select"
      )
        ? storage.select
        : undefined,
    };
  }

  /**
   * Ambil satu halaman baris dari `NXUI.Storage().model(tabel)` — selaras `NexaDom` + `docs/NexaDom.md` (opsi storage).
   * @param {object} options
   * @param {{ model: string, select?: *, query?: function }} options.storage — wajib
   * @param {number} [options.limit] — SQL LIMIT eksplisit (override batch)
   * @param {number} [options.storageFetchLimit] — alias **`fetchLimit`**: ukuran batch SQL. Default **10000** (bukan `order`). **`order`** hanya untuk jumlah baris per halaman di UI NexaForge.
   * @param {number} [options.offset] — default 0
   * @param {string} [options.searchKeyword]
   * @param {string[]} [options.searchableFields] — alias `searchFields`
   * @param {string} [options.filterValue]
   * @param {string} [options.filterField]
   * @param {string} [options.sortBy]
   * @param {string} [options.sortOrder]
   * @param {function(object): object} [options.mapRow] — petakan satu baris DB ke bentuk template
   * @returns {Promise<{ totalCount: number, response: Array, rows: Array }>}
   */
  static async fetchStorageRows(options = {}) {
    const st = options.storage;
    if (!st || typeof st.model !== "string" || !st.model) {
      throw new Error("NexaForge.fetchStorageRows: options.storage.model wajib (string)");
    }
    const queryConfig = NexaForge.buildStorageQueryConfig(st);
    /** SQL LIMIT: jangan pakai `order` (itu ukuran halaman UI). */
    const limit =
      options.limit != null
        ? options.limit
        : options.storageFetchLimit != null
          ? options.storageFetchLimit
          : options.fetchLimit != null
            ? options.fetchLimit
            : 10000;
    const offset = options.offset != null ? options.offset : 0;
    const searchFields =
      options.searchableFields || options.searchFields || [];
    const { totalCount, response } = await storageModelStorageData(
      limit,
      offset,
      options.searchKeyword != null ? options.searchKeyword : "",
      searchFields,
      options.filterValue != null ? options.filterValue : "",
      options.filterField != null ? options.filterField : "",
      options.sortBy != null ? options.sortBy : "id",
      options.sortOrder != null ? options.sortOrder : "DESC",
      queryConfig
    );
    let rows = Array.isArray(response) ? response : [];
    if (typeof options.mapRow === "function") {
      rows = rows.map((r) => options.mapRow(r));
    }
    return { totalCount, response: rows, rows };
  }

  /**
   * Seperti `hydrate`, tetapi data diisi lewat query builder tabel (`storage`) — pola sama dengan `new NexaDom({ storage: { model, select?, query? } })`.
   * Satu batch SQL dimuat ke memori (default **`fetchStorageRows`**: LIMIT 10000, bukan `order`). Pagination/search klien memakai **`order`** sebagai baris per halaman (bukan refetch per halaman seperti NexaDom).
   *
   * @param {ParentNode} root
   * @param {object} options
   * @param {{ model: string, select?: *, query?: function }} options.storage — wajib
   * @param {string} [options.extractor] — key data; default dari atribut NexaForge pada elemen pertama atau `listsKey`
   * @param {string} [options.listsKey] — alias `extractor`
   * @param {function(object): object} [options.mapRow] — samakan kolom SQL dengan placeholder template
   * @returns {Promise<{ instances: NexaForge[], totalCount: number, rows: Array }>}
   */
  /**
   * Opsi spinner untuk `hydrateStorage` — selaras `NexaDom` (`centerScreen`, `type`, dll.).
   * @returns {null | { target: string, type: string, size: string, color: string, message: string, position: string }}
   */
  static _hydrateStorageSpinnerNexaOpts(options, root) {
    if (options.spinner === false) return null;
    const defaults = {
      enabled: true,
      centerScreen: true,
      type: "overlay",
      size: "medium",
      color: "#007bff",
      position: "center",
      message: "",
    };
    if (options.spinner === undefined) return null;
    const s =
      options.spinner === true
        ? { ...defaults }
        : {
            ...defaults,
            ...(typeof options.spinner === "object" && options.spinner !== null
              ? options.spinner
              : {}),
          };
    if (s.enabled === false) return null;

    const center = s.centerScreen !== false;
    let type = s.type || "overlay";
    if (center) {
      if (type !== "inline") type = "overlay";
    } else if (type === "overlay") {
      type = "inline";
    }
    let target = "body";
    if (!center) {
      if (root && root.id) {
        target = `#${root.id}`;
      } else if (root && root.nodeType === 1) {
        const rid = root.id || `nexa-forge-hydrate-${Date.now()}`;
        if (!root.id) root.id = rid;
        target = `#${rid}`;
      }
    }
    return {
      target,
      type,
      size: s.size || "medium",
      color: s.color || "#007bff",
      message: s.message || "",
      position: s.position || "center",
    };
  }

  static async hydrateStorage(root, options = {}) {
    if (!root || typeof root.querySelectorAll !== "function") {
      return { instances: [], totalCount: 0, rows: [] };
    }
    const st = options.storage;
    if (!st || typeof st.model !== "string" || !st.model) {
      console.warn("NexaForge.hydrateStorage: options.storage.model wajib");
      return { instances: [], totalCount: 0, rows: [] };
    }

    const spinCfg = NexaForge._hydrateStorageSpinnerNexaOpts(options, root);
    let spinApi = null;
    if (spinCfg) {
      try {
        spinApi = nexaSpinner(spinCfg);
        spinApi.show();
      } catch (e) {
        console.warn("NexaForge.hydrateStorage spinner:", e);
      }
    }

    let totalCount = 0;
    let rows = [];
    try {
      const fetchOpts = { ...options };
      const result = await NexaForge.fetchStorageRows(fetchOpts);
      totalCount = result.totalCount;
      rows = result.rows;
    } finally {
      if (spinApi) {
        try {
          spinApi.hide();
        } catch (_) {
          /* ignore */
        }
      }
    }

    const nodes = root.querySelectorAll(
      "[NexaForge],[nexaforge],[data-nexa-forge],[NexaHtml],[nexahtml],[data-nexa-html]"
    );
    const instances = [];

    const forgeOpts = { ...options };
    delete forgeOpts.storage;
    delete forgeOpts.extractor;
    delete forgeOpts.listsKey;
    delete forgeOpts.limit;
    delete forgeOpts.storageFetchLimit;
    delete forgeOpts.fetchLimit;
    delete forgeOpts.offset;
    delete forgeOpts.mapRow;
    delete forgeOpts.searchKeyword;
    delete forgeOpts.spinner;

    nodes.forEach((el) => {
      if (!el.id) {
        console.warn("NexaForge.hydrateStorage: elemen perlu atribut id");
        return;
      }
      const extractor =
        options.extractor ||
        options.listsKey ||
        NexaForge.getNexaForgeAttr(el) ||
        el.id;
      try {
        instances.push(
          new NexaForge({
            elementById: el.id,
            [extractor]: rows,
            ...forgeOpts,
          })
        );
      } catch (err) {
        console.warn("NexaForge.hydrateStorage:", err);
      }
    });

    return { instances, totalCount, rows };
  }

  /**
   * Menyembunyikan nilai field sensitif
   * @private
   * @param {string} fieldName - Nama field
   * @param {*} value - Nilai field
   * @returns {*} Nilai yang sudah disembunyikan atau nilai asli
   */
  _hideSensitiveField(fieldName, value) {
    if (!fieldName || value === null || value === undefined) {
      return value;
    }

    const normalizedFieldName = fieldName.toLowerCase();

    // Cek apakah field termasuk sensitif
    const isSensitive = NexaForge.SENSITIVE_FIELDS.some((sensitiveField) =>
      normalizedFieldName.includes(sensitiveField.toLowerCase())
    );

    if (isSensitive) {
      // Kembalikan string kosong atau placeholder untuk field sensitif
      return typeof value === "string" && value.length > 0 ? "••••••••" : "";
    }

    return value;
  }

  /**
   * Membersihkan data dari field sensitif
   * @private
   * @param {Object|Array} data - Data yang akan dibersihkan
   * @returns {Object|Array} Data yang sudah dibersihkan
   */
  _sanitizeData(data) {
    if (Array.isArray(data)) {
      return data.map((item) => this._sanitizeData(item));
    }

    if (data && typeof data === "object") {
      const sanitized = {};

      Object.keys(data).forEach((key) => {
        const value = data[key];

        // Rekursif untuk nested object
        if (value && typeof value === "object" && !Array.isArray(value)) {
          sanitized[key] = this._sanitizeData(value);
        } else {
          sanitized[key] = this._hideSensitiveField(key, value);
        }
      });

      return sanitized;
    }

    return data;
  }

  /**
   * Membuat element template
   * @private
   */
  createTemplateElement(firstKey, elementById, oldElement) {
    const template = document.createElement("script");
    template.type = "text/template";
    template.setAttribute("data-template", "list");
    template.id = `${firstKey}_${elementById}_${this._instanceId}`;
    template.setAttribute("data-nexadom", this._instanceId);

    let templateHtml = oldElement.innerHTML;
    templateHtml = templateHtml.trim();

    template.innerHTML = templateHtml;
    oldElement.parentNode.replaceChild(template, oldElement);
    return template;
  }

  /**
   * Membuat element konten
   * @private
   */
  createContentElement(elementById, className) {
    const content = document.createElement("div");
    content.id = `${elementById}_content_${this._instanceId}`;
    content.setAttribute("data-nexadom", this._instanceId);
    if (className) content.className = className;

    const template = document.querySelector(
      `[data-nexadom="${this._instanceId}"]`
    );
    if (template) {
      template.parentNode.insertBefore(content, template.nextSibling);
    }
    return content;
  }

  constructor(options) {
    // Tambahkan unique identifier
    this._instanceId = `nexadom_${Date.now()}_${Math.random()
      .toString(36)
      .substr(2, 9)}`;

    // Simpan options untuk digunakan di method lain dengan API endpoint support
    this._options = {
      hideSensitiveFields: true, // Default: sembunyikan field sensitif
      endpoint: null, // API endpoint configuration
      ...options,
    };

    // Validate endpoint configuration if provided
    if (this._options.endpoint) {
      this._validateEndpointConfig(this._options.endpoint);
    }

    // Gunakan WeakMap untuk data private
    this._storage = new WeakMap();
    this._storage.set(this, {
      data: null,
      filter: new NexaFilter(),
      domElements: {},
      templateCache: new Map(),
      fragmentCache: new Map(),
      apiState: {
        loading: false,
        error: null,
        lastResponse: null,
        retryCount: 0,
      },
    });

    // API related properties
    this._isApiMode = false;
    this._loadingElement = null;
    this._errorElement = null;

    if (!options || typeof options !== "object") {
      throw new Error("Parameter options harus berupa object");
    }

    // Modifikasi element ID untuk mencegah konflik
    const elementId = options.elementById;
    const oldElement = document.getElementById(elementId);

    if (!oldElement) {
      throw new Error(`Element dengan ID ${elementId} tidak ditemukan`);
    }

    // Set unique attribute untuk mengidentifikasi elements milik instance ini
    oldElement.setAttribute("data-nexadom", this._instanceId);

    // Setup API elements jika diperlukan
    this._setupApiElements(oldElement);

    // Ambil extractor dari attribute (HTML5: nama bisa jadi nexahtml)
    const extractor =
      NexaForge.getNexaForgeAttr(oldElement) || elementId;

    // Modifikasi fungsi validateAndSanitizeData untuk menangani data dengan lebih baik
    const validateAndSanitizeData = (data) => {
      // Helper function untuk encode ID
      const encodeId = (id) => {
        try {
          return btoa(String(id));
        } catch (error) {
          console.warn("NexaForge: Error encoding ID:", error);
          return String(id);
        }
      };

      // Jika data adalah options object tanpa data
      if (data && !data.data && !data[extractor]) {
        return {
          [extractor]: [],
        };
      }

      // Jika data adalah array
      if (Array.isArray(data)) {
        return {
          [extractor]: data.map((item, index) => ({
            ...item,
            no: index + 1, // Tambahkan nomor urut
            encoded_id: item.id ? encodeId(item.id) : null, // Tambahkan encoded_id untuk modal compatibility
          })),
        };
      }

      // Jika data adalah object dengan property data
      if (data && data.data) {
        return {
          [extractor]: Array.isArray(data.data)
            ? data.data.map((item, index) => ({
                ...item,
                no: index + 1,
                encoded_id: item.id ? encodeId(item.id) : null, // Tambahkan encoded_id untuk modal compatibility
              }))
            : [],
        };
      }

      // Object dengan array di key extractor (mis. { user: [...] } dari hydrate)
      if (data && Array.isArray(data[extractor])) {
        return {
          [extractor]: data[extractor].map((item, index) => ({
            ...item,
            no: index + 1,
            encoded_id: item.id ? encodeId(item.id) : null,
          })),
        };
      }

      // Default return empty array
      return {
        [extractor]: [],
      };
    };

    // Deklarasikan data dan originalData sebagai property class
    this._data = validateAndSanitizeData(options);
    this._originalData = { ...this._data };

    // Simpan template HTML asli sebelum diubah
    const originalTemplate = oldElement.innerHTML;

    // Buat template dan content element sekali saja
    const templateElement = this.createTemplateElement(
      extractor,
      elementId,
      oldElement
    );

    const contentElement = this.createContentElement(
      elementId,
      oldElement.className
    );

    // Setup template
    const template = originalTemplate;

    // Simpan reference ke this untuk digunakan dalam function
    const self = this;

    // Fungsi render yang diperbaiki
    function renderData(pageData) {
      requestAnimationFrame(() => {
        try {
          // Clear content terlebih dahulu
          contentElement.innerHTML = "";

          // Sanitasi data untuk menyembunyikan field sensitif jika diperlukan
          const sanitizedData =
            self._options?.hideSensitiveFields !== false
              ? self._sanitizeData(pageData)
              : pageData;

          // Render data
          const items = sanitizedData[extractor] || [];
          let rendered = template;

          items.forEach((item, index) => {
            let itemHtml = template;
            // Replace semua placeholder dengan nilai sebenarnya
            Object.keys(item).forEach((key) => {
              const regex = new RegExp(`{${extractor}\\.${key}}`, "g");
              // Pastikan field sensitif tetap tersembunyi saat rendering
              const safeValue = self._hideSensitiveField(key, item[key]);
              itemHtml = itemHtml.replace(regex, safeValue);
            });
            contentElement.innerHTML += itemHtml;
          });
        } catch (error) {
          console.error("Error saat render data:", error);
          contentElement.innerHTML = "Error rendering content";
        }
      });
    }

    // Bind renderData ke instance
    this.renderData = renderData.bind(this);

    // Initial render jika ada data
    if (this._data[extractor] && this._data[extractor].length > 0) {
      this.renderData(this._data);
    }

    // Terapkan pengurutan awal jika sortOrder didefinisikan
    if (options.sortOrder && options.sortBy) {
      const order = options.sortOrder.toUpperCase();
      if (this._data[extractor] && this._data[extractor].length > 0) {
        this._data[extractor] = sortData(
          this._data[extractor],
          order,
          options.sortBy
        );
        this._originalData[extractor] = [...this._data[extractor]];
      }
    }

    // Gunakan getter/setter untuk data
    Object.defineProperty(this, "data", {
      get: () => this._data,
      set: (value) => {
        this._data = validateAndSanitizeData(value);
        this._originalData = { ...this._data };
      },
    });

    // Update storage
    const storage = this._storage.get(this);
    storage.data = this._data;

    // Gunakan extractor untuk template
    const firstKey = extractor;
    const sID = "[@" + firstKey + "]";
    const eID = "[/" + firstKey + "]";
    const rowID = firstKey;

    // Inisialisasi NexaDomextractor dengan passing filter
    const domManager = new NexaDomextractor(this.filter);

    // Cache DOM elements dan event listeners untuk cleanup
    const domElements = {
      template: templateElement,
      content: contentElement,
      searchInput: options.search
        ? document.getElementById(options.search)
        : null,
    };

    // Tambahkan instance NexaFilter
    this.filter = new NexaFilter();

    this._templateSelector =
      options.templateSelector || '[data-template="list"]';

    // Implementasi deep copy yang aman
    function deepCopy(obj) {
      if (obj === null || typeof obj !== "object") return obj;

      const copy = Array.isArray(obj) ? [] : {};

      for (let key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
          copy[key] = deepCopy(obj[key]);
        }
      }

      return copy;
    }

    // Fungsi untuk mengurutkan data
    function sortData(data, order = "ASC", sortBy = "id") {
      if (!Array.isArray(data)) return data;

      return [...data].sort((a, b) => {
        const valueA = a[sortBy];
        const valueB = b[sortBy];

        // Handle nilai null atau undefined
        if (valueA === null || valueA === undefined)
          return order === "ASC" ? -1 : 1;
        if (valueB === null || valueB === undefined)
          return order === "ASC" ? 1 : -1;

        // Handle tipe data berbeda
        if (typeof valueA === "string" && typeof valueB === "string") {
          return order === "ASC"
            ? valueA.localeCompare(valueB)
            : valueB.localeCompare(valueA);
        }

        // Handle angka dan tipe data lainnya
        return order === "ASC"
          ? valueA > valueB
            ? 1
            : -1
          : valueA < valueB
          ? 1
          : -1;
      });
    }

    /**
     * @param {string} str - String yang akan dikonversi menjadi slug
     * @returns {string} Slug yang dihasilkan
     */
    function createSlug(str) {
      return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-");
    }

    /**
     * Memproses data dengan menambahkan slug
     */
    function processDataWithSlug(data) {
      if (!Array.isArray(data)) return data;

      return data.map((item) => ({
        ...item,
        slug: item.href ? createSlug(item.href) : null,
      }));
    }

    const pageLimit = options.order || 10;

    // Inisialisasi data dengan slug
    if (this._data[rowID]) {
      this._data[rowID] = processDataWithSlug(this._data[rowID]);
    }

    // Pindahkan deklarasi currentPage dan totalPages ke atas sebelum digunakan
    let currentPage = 1;
    const totalPages = Math.ceil(this._data[rowID].length / pageLimit);

    // Inisialisasi _activeFilters
    this._activeFilters = {};

    // Definisikan getFilteredData di awal, sebelum digunakan
    function getFilteredData() {
      // Jika ada filter aktif, gunakan data yang sudah difilter
      if (Object.keys(self._activeFilters).length > 0) {
        return self._data[rowID].filter((item) => {
          return Object.entries(self._activeFilters).every(
            ([filterType, filterValue]) => {
              return String(item[filterType]) === String(filterValue);
            }
          );
        });
      }
      // Jika tidak ada filter, kembalikan semua data
      return self._data[rowID];
    }

    // Tambahkan fungsi curPage yang menggunakan getFilteredData
    function curPage(page = 1) {
      const filteredData = getFilteredData();
      const startIndex = (page - 1) * pageLimit;
      const slicedData = filteredData.slice(startIndex, startIndex + pageLimit);
      return { [rowID]: slicedData };
    }

    // Cache untuk template yang sudah dirender
    const templateCache = new Map();

    // Cache untuk fragment DOM
    const fragmentCache = new Map();

    /**
     * Optimasi render dengan caching
     */
    function optimizedRender(data, templateId) {
      const cacheKey = JSON.stringify(data) + templateId;

      // Cek cache
      if (templateCache.has(cacheKey)) {
        return templateCache.get(cacheKey);
      }

      // Render template menggunakan NexaDomextractor
      const rendered = domManager.render(template, data, templateElement);

      // Simpan ke cache dengan batasan ukuran
      if (templateCache.size > 100) {
        const firstKey = templateCache.keys().next().value;
        templateCache.delete(firstKey);
      }
      templateCache.set(cacheKey, rendered);

      return rendered;
    }

    /**
     * Fragment caching untuk performa
     */
    function createCachedFragment(items, templateId) {
      const cacheKey = templateId + items.length;

      if (fragmentCache.has(cacheKey)) {
        return fragmentCache.get(cacheKey).cloneNode(true);
      }

      const fragment = document.createDocumentFragment();
      items.forEach((item) => {
        const rendered = optimizedRender({ [rowID]: [item] }, templateId);
        const div = document.createElement("div");
        div.innerHTML = rendered;
        fragment.appendChild(div.firstChild);
      });

      fragmentCache.set(cacheKey, fragment.cloneNode(true));
      return fragment;
    }

    /**
     * @param {Object} pageData - Data yang akan dirender
     */
    function renderData(pageData) {
      requestAnimationFrame(() => {
        try {
          // Set pagination info sebelum render
          domManager.setPaginationInfo(currentPage, pageLimit);

          // Clear content terlebih dahulu
          contentElement.innerHTML = "";

          // Render data
          const rendered = domManager.render(
            template,
            pageData,
            templateElement
          );
          contentElement.innerHTML = rendered;

          // Update pagination setelah render
          updatePaginationUI();
        } catch (error) {
          console.error("Error saat render data:", error);
        }
      });
    }

    // Tambahkan fungsi untuk virtual scrolling jika diperlukan
    function setupVirtualScroll() {
      if (!options.virtualScroll) return;

      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Load more data when reaching bottom
            if (currentPage < totalPages) {
              currentPage++;
              renderData(curPage(currentPage));
            }
          }
        });
      });

      // Observe last item
      const lastItem = contentElement.lastElementChild;
      if (lastItem) {
        observer.observe(lastItem);
      }

      // Cleanup
      return () => observer.disconnect();
    }

    // Cache DOM queries dan kalkulasi yang sering digunakan
    const cachedData = {
      totalPages: Math.ceil(this._data[rowID].length / pageLimit),
      searchDebounceTimer: null,
      // Cache DOM elements yang sering digunakan
      paginationElement:
        options.hasOwnProperty("pagination") && options.pagination !== false
          ? document.getElementById(options.pagination)
          : null,
      searchInput:
        options.hasOwnProperty("search") && options.search !== false
          ? document.getElementById(options.search)
          : null,
      // Tambahkan filter select
      filterSelect:
        options.hasOwnProperty("filter") && options.filter !== false
          ? document.getElementById(options.filter)
          : null,
    };

    /**
     * Fungsi debounce untuk search dengan cleanup
     */
    function debounceSearch(fn, delay = 300) {
      let timer = null;
      return function (...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
          timer = null;
          fn.apply(this, args);
        }, delay);
      };
    }

    // Tambahkan fungsi untuk membuat indeks pencarian
    function createSearchIndex(data, searchableFields) {
      const searchIndex = new Map();

      data.forEach((item, index) => {
        let searchText = searchableFields
          .map((field) => {
            return item[field] ? String(item[field]).toLowerCase() : "";
          })
          .join(" ");

        searchIndex.set(index, searchText);
      });

      return searchIndex;
    }

    // Modifikasi memoizedFilter
    const memoizedFilter = (function () {
      const cache = new Map();
      let searchIndex = null;

      return function (keyword, data, searchableFields) {
        const cacheKey = keyword.trim().toLowerCase();
        if (cache.has(cacheKey)) return cache.get(cacheKey);

        // Buat indeks jika belum ada
        if (!searchIndex) {
          searchIndex = createSearchIndex(data, searchableFields);
        }

        const filtered = data.filter((item, index) => {
          const indexedText = searchIndex.get(index);
          return indexedText.includes(cacheKey);
        });

        cache.set(cacheKey, filtered);
        if (cache.size > 100) {
          const firstKey = cache.keys().next().value;
          cache.delete(firstKey);
        }
        return filtered;
      };
    })();

    function debounceAndThrottle(fn, delay = 300, throttleDelay = 100) {
      let debounceTimer;
      let throttleTimer;
      let lastRun = 0;

      return function (...args) {
        // Clear existing debounce timer
        if (debounceTimer) clearTimeout(debounceTimer);

        // Throttle check
        const now = Date.now();
        if (now - lastRun >= throttleDelay) {
          fn.apply(this, args);
          lastRun = now;
        } else {
          // Debounce
          debounceTimer = setTimeout(() => {
            fn.apply(this, args);
            lastRun = Date.now();
          }, delay);
        }
      };
    }

    function handleSearch(keyword, searchableFields) {
      const self = this;
      if (!keyword) {
        self._data[rowID] = [...self._originalData[rowID]];
      } else {
        self._data[rowID] = self._originalData[rowID].filter((item) => {
          return searchableFields.some((field) => {
            const value = item[field];
            return (
              value &&
              value.toString().toLowerCase().includes(keyword.toLowerCase())
            );
          });
        });
      }

      currentPage = 1;
      renderData(curPage(1));
      updatePaginationUI();
    }

    function setupSearch() {
      if (!options.search) return;

      const searchInput = document.getElementById(options.search);
      if (!searchInput) {
        console.warn("Search input tidak ditemukan");
        return;
      }

      const searchHandler = debounceAndThrottle(
        (event) => {
          const keyword = event.target.value.trim();
          handleSearch.call(self, keyword, options.searchableFields);
        },
        300,
        100
      );

      searchInput.removeEventListener("input", searchHandler);
      searchInput.addEventListener("input", searchHandler);
    }

    // Modifikasi setupFilter
    function setupFilter() {
      if (!options.hasOwnProperty("filter")) return;

      const filterSelect = document.getElementById(options.filter);
      if (!filterSelect) return;

      filterSelect.addEventListener("change", function (event) {
        const value = event.target.value;

        if (value === "all") {
          this._data[rowID] = [...this._originalData[rowID]];
          renderData(curPage(1));
          return;
        }

        handleFilter(value, [options.filterBy]);
      });
    }

    // Modifikasi destroy untuk cleanup worker
    this.destroy = function () {
      if (cachedData.searchInput) {
        cachedData.searchInput.removeEventListener("input", handleSearch);
      }

      // Cleanup untuk filter
      if (cachedData.filterElements) {
        cachedData.filterElements.forEach(({ element, handler }) => {
          element.removeEventListener("change", handler);
        });
      }

      if (cachedData.searchDebounceTimer) {
        clearTimeout(cachedData.searchDebounceTimer);
      }

      // Clear memoization cache
      memoizedFilter.cache = new Map();

      // Cleanup DOM elements
      domElements.content?.remove();
      domElements.template?.remove();

      // Clear cached data
      Object.keys(cachedData).forEach((key) => {
        cachedData[key] = null;
      });

      // Cleanup semua elements dengan instance ID ini
      document
        .querySelectorAll(`[data-nexadom="${this._instanceId}"]`)
        .forEach((el) => el.remove());

      // Clear storage
      const storage = this._storage.get(this);
      if (storage) {
        storage.templateCache.clear();
        storage.fragmentCache.clear();
        storage.domElements = {};
        this._storage.delete(this);
      }
    };

    /**
     * Setup filter select untuk multiple filter
     */
    const setupFilterSelect = () => {
      if (!options.hasOwnProperty("filterBy")) {
        return false;
      }

      // Support untuk multiple filter
      const filterBy = Array.isArray(options.filterBy)
        ? options.filterBy
        : [options.filterBy];

      // Gunakan this langsung karena arrow function
      const handleFilter = (event) => {
        const selectedValue = event.target.value;
        const filterType = event.target.getAttribute("data-filter-type");

        // Reset ke halaman pertama saat filter berubah
        currentPage = 1;

        // Update nilai filter aktif
        if (selectedValue === "all") {
          delete this._activeFilters[filterType];
        } else {
          this._activeFilters[filterType] = selectedValue;
        }

        requestAnimationFrame(() => {
          // Reset data terlebih dahulu
          this._data[rowID] = [...this._originalData[rowID]];

          // Filter data berdasarkan semua filter yang aktif
          if (Object.keys(this._activeFilters).length > 0) {
            this._data[rowID] = this._data[rowID].filter((item) => {
              return Object.entries(this._activeFilters).every(
                ([filterType, filterValue]) => {
                  if (!item.hasOwnProperty(filterType)) {
                    console.warn(
                      `Properti "${filterType}" tidak ditemukan pada item:`,
                      item
                    );
                    return false;
                  }
                  return String(item[filterType]) === String(filterValue);
                }
              );
            });
          }

          // Update total pages berdasarkan data yang sudah difilter
          const totalItems = this._data[rowID].length;
          cachedData.totalPages = Math.ceil(totalItems / pageLimit);

          // Pastikan current page valid
          if (currentPage > cachedData.totalPages) {
            currentPage = cachedData.totalPages || 1;
          }

          // Batch DOM updates
          const updates = () => {
            // Render data halaman pertama
            renderData(curPage(currentPage));

            // Update tampilan pagination
            if (cachedData.paginationElement) {
              updatePaginationUI();
            }
          };

          requestAnimationFrame(updates);
        });
      };

      // Setup event listeners untuk setiap filter select
      filterBy.forEach((filterType) => {
        const selectElement = document.getElementById(filterType);
        if (selectElement) {
          selectElement.setAttribute("data-filter-type", filterType);

          // Cleanup dan setup event listener
          selectElement.removeEventListener("change", handleFilter);
          selectElement.addEventListener("change", handleFilter);

          if (!cachedData.filterElements) {
            cachedData.filterElements = [];
          }
          cachedData.filterElements.push({
            element: selectElement,
            handler: handleFilter,
            type: filterType,
          });
        } else {
          console.warn(
            `Element filter dengan ID "${filterType}" tidak ditemukan`
          );
        }
      });

      return true;
    };

    // Setup fitur-fitur dasar
    setupSearch();
    setupFilter();
    setupLazyLoading();

    // Panggil setupFilterSelect setelah didefinisikan
    setupFilterSelect();

    // Initial render
    renderData(curPage(1));

    /**
     * @param {Function} callback - Callback untuk memproses element
     */
    NexaForge.prototype.Element = function (callback) {
      if (typeof callback !== "function") {
        throw new Error("Parameter callback harus berupa function");
      }
      const filteredData = [...this.data.data[Object.keys(this.data.data)[0]]];
      callback(filteredData);
    };

    /**
     * Membuat element pagination jika belum ada
     */
    function createPaginationElement() {
      // Cek apakah pagination didefinisikan dan tidak false
      if (
        !options.hasOwnProperty("pagination") ||
        options.pagination === false
      ) {
        return null;
      }

      const paginationID = options.pagination;

      // Cek apakah element dengan ID yang sesuai ada di HTML
      let paginationElement = document.getElementById(paginationID);

      if (!paginationID || !paginationElement) {
        // console.warn("Element pagination tidak ditemukan atau ID tidak sesuai");
        return null;
      }

      // Pastikan element memiliki class pagination
      if (!paginationElement.classList.contains("pagination")) {
        paginationElement.classList.add("pagination");
      }

      return paginationElement;
    }

    /**
     * Membuat dan memperbarui UI pagination
     */
    function updatePaginationUI() {
      // Cek apakah pagination didefinisikan dan tidak false
      if (
        !options.hasOwnProperty("pagination") ||
        options.pagination === false
      ) {
        return false;
      }

      const paginationList = createPaginationElement();
      if (!paginationList) return false;

      // Reset pagination content
      paginationList.innerHTML = "";

      // Gunakan getFilteredData yang sudah didefinisikan di atas
      const filteredData = getFilteredData();
      const totalItems = filteredData.length;
      const currentTotalPages = Math.ceil(totalItems / pageLimit);

      // Validasi current page
      if (currentPage > currentTotalPages) {
        currentPage = currentTotalPages || 1;
      }

      // Update info halaman dan total data
      const pageInfoElement = document.getElementById("pageInfo");
      if (pageInfoElement) {
        const currentPageSpan = document.getElementById("currentPage");
        const totalPagesSpan = document.getElementById("totalPages");
        const totalItemsSpan = document.getElementById("totalItems");

        if (currentPageSpan) currentPageSpan.textContent = currentPage;
        if (totalPagesSpan) totalPagesSpan.textContent = currentTotalPages;
        if (totalItemsSpan) totalItemsSpan.textContent = totalItems;
      }

      // Satu halaman atau kosong: default sembunyikan (hemat ruang). Set
      // alwaysShowPagination: true untuk tetap menampilkan bar (mis. demo / UX).
      const hideWhenSingleOrEmpty =
        currentTotalPages <= 1 &&
        !(
          options.alwaysShowPagination === true &&
          totalItems > 0 &&
          currentTotalPages >= 1
        );
      if (hideWhenSingleOrEmpty) {
        paginationList.style.display = "none";
        return false;
      }

      paginationList.style.display = "flex";

      // Tombol First
      const firstLi = document.createElement("li");
      firstLi.classList.add("page-item");
      if (currentPage === 1) firstLi.classList.add("disabled");
      firstLi.innerHTML = `<button class="page-link" data-page="1">First</button>`;
      paginationList.appendChild(firstLi);

      // Tombol Previous
      const prevLi = document.createElement("li");
      prevLi.classList.add("page-item");
      if (currentPage === 1) prevLi.classList.add("disabled");
      prevLi.innerHTML = `<button class="page-link" data-page="${
        currentPage - 1
      }">Previous</button>`;
      paginationList.appendChild(prevLi);

      // Logika untuk menampilkan nomor halaman
      let startPage, endPage;
      if (currentTotalPages <= 5) {
        startPage = 1;
        endPage = currentTotalPages;
      } else {
        if (currentPage <= 3) {
          startPage = 1;
          endPage = 5;
        } else if (currentPage >= currentTotalPages - 2) {
          startPage = currentTotalPages - 4;
          endPage = currentTotalPages;
        } else {
          startPage = currentPage - 2;
          endPage = currentPage + 2;
        }
      }

      // Render nomor halaman
      for (let i = startPage; i <= endPage; i++) {
        const pageLi = document.createElement("li");
        pageLi.classList.add("page-item");
        if (i === currentPage) pageLi.classList.add("active");
        pageLi.innerHTML = `<button class="page-link" data-page="${i}">${i}</button>`;
        paginationList.appendChild(pageLi);
      }

      // Tombol Next
      const nextLi = document.createElement("li");
      nextLi.classList.add("page-item");
      if (currentPage === currentTotalPages) nextLi.classList.add("disabled");
      nextLi.innerHTML = `<button class="page-link" data-page="${
        currentPage + 1
      }">Next</button>`;
      paginationList.appendChild(nextLi);

      // Tombol Last
      const lastLi = document.createElement("li");
      lastLi.classList.add("page-item");
      if (currentPage === currentTotalPages) lastLi.classList.add("disabled");
      lastLi.innerHTML = `<button class="page-link" data-page="${currentTotalPages}">Last</button>`;
      paginationList.appendChild(lastLi);

      // Tambahkan event listener untuk pagination buttons
      paginationList.querySelectorAll(".page-link").forEach((button) => {
        button.addEventListener("click", function () {
          const newPage = parseInt(this.dataset.page);
          if (
            newPage &&
            newPage !== currentPage &&
            newPage >= 1 &&
            newPage <= currentTotalPages
          ) {
            currentPage = newPage;
            renderData(curPage(currentPage));
            updatePaginationUI();
          }
        });
      });
    }

    /**
     * Setup event listeners untuk pagination
     */
    function setupPaginationListeners() {
      const paginationList = document.getElementById(options.pagination);
      if (!paginationList) return;

      paginationList.addEventListener("click", function (event) {
        const button = event.target.closest(".page-link");
        if (!button) return;

        const newPage = parseInt(button.dataset.page);
        if (isNaN(newPage) || newPage < 1 || newPage > totalPages) return;
        if (newPage === currentPage) return;

        currentPage = newPage;
        renderData(curPage(currentPage));
        updatePaginationUI();

        // Update info page setiap kali halaman berubah
        const pageInfo = {
          pagination: {
            currentPage: currentPage,
            totalPages: Math.ceil(this._data[rowID].length / pageLimit),
            totalItems: this._data[rowID].length,
          },
        };

        const currentPageSpan = document.getElementById("currentPage");
        if (currentPageSpan) {
          currentPageSpan.textContent = pageInfo.pagination.currentPage;
        }
      });
    }

    // Initial setup
    setupPaginationListeners();

    /**
     * Fungsi untuk memuat ulang data
     * @param {Object|Array} newData - Data baru yang akan dimuat
     * @param {boolean} resetPage - Reset ke halaman pertama (default: true)
     */
    NexaForge.prototype.addData = function (newData, resetPage = true) {
      if (
        !newData ||
        (typeof newData !== "object" && !Array.isArray(newData))
      ) {
        throw new Error("Parameter newData harus berupa object atau array");
      }

      try {
        // Backup data lama untuk rollback jika terjadi error
        const oldData = { ...this._data };
        const oldOriginalData = { ...this._originalData };

        let newItems = [];

        // Validasi dan ekstrak data baru
        if (Array.isArray(newData)) {
          newItems = [...newData];
        } else {
          const firstKey = Object.keys(newData)[0];
          if (!firstKey || !Array.isArray(newData[firstKey])) {
            throw new Error("Data harus berupa array atau object dengan array");
          }
          newItems = [...newData[firstKey]];
        }

        // Validasi data baru tidak kosong
        if (newItems.length === 0) {
          throw new Error("Data baru tidak boleh kosong");
        }

        try {
          // Proses data baru dengan slug
          const processedNewItems = processDataWithSlug(newItems);

          // Gabungkan data baru di awal dengan data lama
          this._originalData[rowID] = [
            ...newItems,
            ...this._originalData[rowID],
          ];
          this._data[rowID] = [...processedNewItems, ...this._data[rowID]];
        } catch (slugError) {
          console.warn("Error saat memproses slug:", slugError);
          // Jika gagal memproses slug, tetap gabungkan data tanpa slug
          this._originalData[rowID] = [
            ...newItems,
            ...this._originalData[rowID],
          ];
          this._data[rowID] = [...newItems, ...this._data[rowID]];
        }

        // Update cache dan perhitungan terkait
        cachedData.totalPages = Math.ceil(this._data[rowID].length / pageLimit);

        // Reset pencarian jika ada
        if (cachedData.searchInput) {
          cachedData.searchInput.value = "";
        }

        // Reset ke halaman pertama jika diminta
        if (resetPage) {
          currentPage = 1;
        }

        // Clear memoization cache karena data berubah
        if (memoizedFilter && memoizedFilter.cache) {
          memoizedFilter.cache.clear();
        }

        // Render ulang dengan data baru
        requestAnimationFrame(() => {
          renderData(curPage(currentPage));

          // Trigger custom event untuk notifikasi reload selesai
          const reloadEvent = new CustomEvent("dataReloaded", {
            detail: {
              success: true,
              newItemsCount: newItems.length,
              totalItems: this._data[rowID].length,
              currentPage: currentPage,
            },
          });
          document.dispatchEvent(reloadEvent);
        });

        return true;
      } catch (error) {
        console.error("Error saat reload data:", error);

        // Rollback ke data lama jika terjadi error
        this._data = { ...oldData };
        this._originalData = { ...oldOriginalData };

        // Trigger custom event untuk notifikasi error
        const errorEvent = new CustomEvent("dataReloadError", {
          detail: {
            error: error.message,
          },
        });
        document.dispatchEvent(errorEvent);

        return false;
      }
    };

    /**
     * Fungsi untuk mendapatkan data saat ini
     * @returns {Object} Data yang sedang ditampilkan
     */
    NexaForge.prototype.getCurrentData = function () {
      const totalItems = this._data[rowID].length;
      const currentTotalPages = Math.ceil(totalItems / pageLimit);

      return {
        all: this._data[rowID],
        current: this.curPage(currentPage)[rowID],
        pagination: {
          currentPage: currentPage,
          totalPages: currentTotalPages,
          pageLimit: pageLimit,
          totalItems: totalItems,
        },
      };
    };

    /**
     * Fungsi untuk refresh manual dengan tombol
     * @param {string} buttonId - ID tombol refresh
     * @param {Object} options - Data dan opsi refresh
     */
    NexaForge.prototype.setupRefreshButton = function (buttonId, options = {}) {
      const refreshButton = document.getElementById(buttonId);
      if (!refreshButton) {
        console.warn("Tombol refresh tidak ditemukan:", buttonId);
        return null;
      }

      const {
        onStart,
        onSuccess,
        onError,
        loadingText = "Memperbarui...",
        data = null,
        reloadOptions = {},
      } = options;

      let isLoading = false;
      const originalText = refreshButton.innerHTML;

      const handleRefresh = async () => {
        if (isLoading) return;

        try {
          isLoading = true;
          refreshButton.disabled = true;
          refreshButton.innerHTML = loadingText;

          if (onStart) await onStart();

          if (data) {
            const success = this.Reload(data, reloadOptions);
            if (success && onSuccess) {
              await onSuccess({ data });
            }
          }
        } catch (error) {
          console.error("Error saat refresh:", error);
          if (onError) await onError(error);
        } finally {
          isLoading = false;
          refreshButton.disabled = false;
          refreshButton.innerHTML = originalText;
        }
      };

      const cleanup = () => {
        refreshButton.removeEventListener("click", handleRefresh);
      };

      refreshButton.addEventListener("click", handleRefresh);
      return cleanup;
    };

    /**
     * Fungsi untuk refresh dengan onclick
     * @param {Object} data - Data untuk refresh
     */
    NexaForge.prototype.Reload = function (newData, options = {}) {
      try {
        // Validasi data dengan lebih fleksibel
        let dataToLoad;
        const rowID = this._rowID; // Gunakan _rowID yang sudah diinisialisasi

        if (Array.isArray(newData)) {
          dataToLoad = { [rowID]: newData };
        } else if (newData && newData.data && newData.data[rowID]) {
          dataToLoad = { [rowID]: newData.data[rowID] };
        } else if (newData && newData[rowID]) {
          dataToLoad = newData;
        } else {
          throw new Error("Format data tidak valid untuk reload");
        }

        const {
          append = false,
          preserveFilters = false,
          resetPage = true,
        } = options;

        // Backup data lama untuk rollback
        const oldData = [...this._data[rowID]];
        const oldOriginalData = [...this._originalData[rowID]];

        try {
          if (append) {
            // Tambahkan data baru ke existing data
            this._originalData[rowID] = [
              ...this._originalData[rowID],
              ...dataToLoad[rowID],
            ];
            this._data[rowID] = [...this._data[rowID], ...dataToLoad[rowID]];
          } else {
            // Ganti dengan data baru
            this._originalData[rowID] = [...dataToLoad[rowID]];
            this._data[rowID] = [...dataToLoad[rowID]];
          }

          // Reset ke halaman pertama jika diminta
          if (resetPage) {
            this._currentPage = 1;
          }

          // Update UI
          this.renderData(this.curPage(this._currentPage));

          return {
            success: true,
            totalItems: this._data[rowID].length,
            currentPage: this._currentPage,
          };
        } catch (error) {
          // Rollback jika terjadi error
          this._data[rowID] = oldData;
          this._originalData[rowID] = oldOriginalData;
          throw error;
        }
      } catch (error) {
        console.error("Error dalam Reload:", error);
        return {
          success: false,
          error: error.message,
        };
      }
    };

    function renderLargeTemplate(template, data) {
      const chunkSize = 1000; // karakter
      const chunks = [];

      for (let i = 0; i < template.length; i += chunkSize) {
        chunks.push(template.slice(i, i + chunkSize));
      }

      let result = "";
      chunks.forEach((chunk, index) => {
        requestAnimationFrame(() => {
          result += processTemplateChunk(chunk, data);
          if (index === chunks.length - 1) {
            contentElement.innerHTML = result;
          }
        });
      });
    }

    /**
     * Setup lazy loading untuk gambar dan konten
     */
    function setupLazyLoading() {
      const options = {
        root: null,
        rootMargin: "50px",
        threshold: 0.1,
      };

      // Observer untuk gambar
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
              // Load gambar dengan fade effect
              img.style.opacity = "0";
              img.src = img.dataset.src;
              img.onload = () => {
                img.style.transition = "opacity 0.3s";
                img.style.opacity = "1";
              };
              delete img.dataset.src;
              imageObserver.unobserve(img);
            }
          }
        });
      }, options);

      // Observer untuk konten berat
      const contentObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const element = entry.target;
            if (element.dataset.content) {
              loadHeavyContent(element);
              contentObserver.unobserve(element);
            }
          }
        });
      }, options);

      // Load konten berat
      function loadHeavyContent(element) {
        const contentId = element.dataset.content;
      }

      // Observe semua gambar lazy
      document.querySelectorAll("img[data-src]").forEach((img) => {
        imageObserver.observe(img);
      });

      // Observe konten berat
      document.querySelectorAll("[data-content]").forEach((element) => {
        contentObserver.observe(element);
      });

      return {
        imageObserver,
        contentObserver,
      };
    }

    // Cleanup untuk lazy loading
    this.destroy = function () {
      if (this.lazyLoadObservers) {
        this.lazyLoadObservers.imageObserver.disconnect();
        this.lazyLoadObservers.contentObserver.disconnect();
      }
      // ... cleanup lainnya
    };

    // Setup lazy loading saat inisialisasi
    this.lazyLoadObservers = setupLazyLoading();

    /**
     * Setup virtual scrolling untuk data besar
     */
    function setupVirtualScrolling() {
      const viewportHeight = window.innerHeight;
      const itemHeight = 50; // Perkiraan tinggi setiap item
      const bufferSize = 5; // Jumlah item buffer atas dan bawah
      const visibleItems =
        Math.ceil(viewportHeight / itemHeight) + bufferSize * 2;

      let startIndex = 0;
      let scrollTimeout;

      const container = contentElement;
      const scrollContainer = document.createElement("div");
      scrollContainer.style.position = "relative";
      container.appendChild(scrollContainer);

      function updateVisibleItems() {
        const scrollTop = container.scrollTop;
        startIndex = Math.floor(scrollTop / itemHeight);
        startIndex = Math.max(0, startIndex - bufferSize);

        const visibleData = this._data[rowID].slice(
          startIndex,
          startIndex + visibleItems
        );
        const totalHeight = this._data[rowID].length * itemHeight;

        scrollContainer.style.height = `${totalHeight}px`;

        // Render hanya item yang visible
        const fragment = document.createDocumentFragment();
        visibleData.forEach((item, index) => {
          const itemElement = document.createElement("div");
          itemElement.style.position = "absolute";
          itemElement.style.top = `${(startIndex + index) * itemHeight}px`;
          itemElement.style.height = `${itemHeight}px`;

          const rendered = optimizedRender({ [rowID]: [item] }, rowID);
          itemElement.innerHTML = rendered;

          fragment.appendChild(itemElement);
        });

        // Clear dan update content
        while (scrollContainer.firstChild) {
          scrollContainer.removeChild(scrollContainer.firstChild);
        }
        scrollContainer.appendChild(fragment);
      }

      container.addEventListener("scroll", () => {
        if (scrollTimeout) {
          cancelAnimationFrame(scrollTimeout);
        }
        scrollTimeout = requestAnimationFrame(updateVisibleItems);
      });

      // Initial render
      updateVisibleItems();

      return {
        refresh: updateVisibleItems,
        destroy: () => {
          container.removeEventListener("scroll", updateVisibleItems);
          scrollContainer.remove();
        },
      };
    }

    /**
     * Setup data chunking dan storage
     */
    class DataChunkManager {
      constructor(dbName = "viewDB", storeName = "chunks") {
        this.dbName = dbName;
        this.storeName = storeName;
        this.chunkSize = 1000; // Items per chunk
        this.db = null;
      }

      async init() {
        return new Promise((resolve, reject) => {
          const request = indexedDB.open(this.dbName, 1);

          request.onerror = () => reject(request.error);
          request.onsuccess = () => {
            this.db = request.result;
            resolve();
          };

          request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(this.storeName)) {
              db.createObjectStore(this.storeName, { keyPath: "chunkId" });
            }
          };
        });
      }

      async storeChunks(data) {
        const chunks = this.createChunks(data);
        const store = this.db
          .transaction(this.storeName, "readwrite")
          .objectStore(this.storeName);

        return Promise.all(
          chunks.map(
            (chunk) =>
              new Promise((resolve, reject) => {
                const request = store.put(chunk);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
              })
          )
        );
      }

      async getChunk(chunkId) {
        return new Promise((resolve, reject) => {
          const request = this.db
            .transaction(this.storeName)
            .objectStore(this.storeName)
            .get(chunkId);

          request.onsuccess = () => resolve(request.result);
          request.onerror = () => reject(request.error);
        });
      }

      createChunks(data) {
        const chunks = [];
        for (let i = 0; i < data.length; i += this.chunkSize) {
          chunks.push({
            chunkId: Math.floor(i / this.chunkSize),
            data: data.slice(i, i + this.chunkSize),
          });
        }
        return chunks;
      }
    }

    /**
     * Setup data streaming untuk load data besar
     */
    class DataStreamManager {
      constructor(options = {}) {
        this.pageSize = options.pageSize || 50;
        this.chunkManager = new DataChunkManager();
      }

      async init() {
        await this.chunkManager.init();
        this.setupStreamHandlers();
      }

      setupStreamHandlers() {
        let currentChunk = 0;
        let isLoading = false;

        const loadNextChunk = async () => {
          if (isLoading) return;
          isLoading = true;

          try {
            const chunk = await this.chunkManager.getChunk(currentChunk);
            if (chunk) {
              // Process chunk dengan worker
              this.worker.postMessage({
                action: "processChunk",
                data: chunk.data,
              });
              currentChunk++;
            }
          } catch (error) {
            console.error("Error loading chunk:", error);
          } finally {
            isLoading = false;
          }
        };

        // Setup intersection observer untuk infinite scroll
        const observer = new IntersectionObserver(
          (entries) => {
            if (entries[0].isIntersecting) {
              loadNextChunk();
            }
          },
          { threshold: 0.5 }
        );

        // Observe loader element
        const loader = document.querySelector("#chunk-loader");
        if (loader) {
          observer.observe(loader);
        }
      }

      async processStreamedData(data) {
        // Store chunks di IndexedDB
        await this.chunkManager.storeChunks(data);

        // Setup virtual scrolling
        const virtualScroller = setupVirtualScrolling();

        return {
          destroy: () => {
            virtualScroller.destroy();
            // Cleanup lainnya
          },
        };
      }
    }

    // Inisialisasi managers
    const streamManager = new DataStreamManager();
    let virtualScroller = null;

    // Bind this ke initializeDataHandling
    const initializeDataHandling = async () => {
      await streamManager.init();

      if (this._data[rowID].length > 1000) {
        // Gunakan virtual scrolling untuk data besar
        virtualScroller = setupVirtualScrolling();

        // Process data dengan streaming
        await streamManager.processStreamedData(this._data[rowID]);
      } else {
        // Render normal untuk data kecil
        renderData(curPage(1));
      }
    };

    // Update destroy method to remove worker cleanup
    this.destroy = function () {
      if (virtualScroller) {
        virtualScroller.destroy();
      }
      // ... other cleanup
    };

    // Initialize dengan arrow function untuk mempertahankan this
    initializeDataHandling().catch(console.error);

    /**
     * Filter data berdasarkan key dan value
     */
    this.filterKey = function (key, value) {
      if (!key || !value) {
        console.warn("Parameter key dan value harus diisi");
        return this;
      }

      try {
        // Filter data berdasarkan key dan value
        this._data[rowID] = this._originalData[rowID].filter((item) => {
          return String(item[key]) === String(value);
        });

        // Update UI
        currentPage = 1;
        renderData(curPage(1));
        updatePaginationUI();

        // Return object dengan informasi hasil filter
        return {
          filtered: this._data[rowID].length,
          total: this._originalData[rowID].length,
          data: this._data[rowID],
        };
      } catch (error) {
        console.error("Error dalam filterKey:", error);
        return {
          filtered: 0,
          total: this._originalData[rowID].length,
          data: [],
        };
      }
    };

    /**
     * Internal filter state management
     */
    this._filterState = {
      active: {},
      history: [],

      add: function (key, value) {
        this.active[key] = value;
        this.history.push({
          key,
          value,
          timestamp: Date.now(),
        });
      },

      remove: function (key) {
        delete this.active[key];
      },

      clear: function () {
        this.active = {};
        this.history = [];
      },

      get: function (key) {
        return this.active[key];
      },

      getAll: function () {
        return { ...this.active };
      },
    };

    /**
     * Internal filter helper
     */
    this._internalFilter = function (key, value) {
      if (!key || !value) return;

      try {
        this._data[rowID] = this._data[rowID].filter((item) => {
          // Handle nested object
          if (key.includes(".")) {
            const keys = key.split(".");
            let val = item;
            for (const k of keys) {
              if (val === undefined) return false;
              val = val[k];
            }
            return String(val) === String(value);
          }

          // Handle array value
          if (Array.isArray(value)) {
            return value.includes(String(item[key]));
          }

          return String(item[key]) === String(value);
        });
      } catch (error) {
        console.error("Error pada internal filter:", error);
      }
    };

    /**
     * Method untuk mengubah urutan data
     * @param {string} order - 'ASC' atau 'DESC'
     * @param {string} sortBy - field yang akan diurutkan
     */
    NexaForge.prototype.sort = function (order, sortBy) {
      this._data[rowID] = sortData(this._data[rowID], order, sortBy);
      currentPage = 1;
      renderData(curPage(1));
      updatePaginationUI();
    };

    /**
     * Mendapatkan data berdasarkan ID
     * @param {number|string} id - ID yang dicari
     * @returns {Object|null} Data yang ditemukan atau null jika tidak ada
     */
    NexaForge.prototype.getID = function (id) {
      if (!id) {
        console.warn("Parameter ID harus diisi");
        return null;
      }

      try {
        // Cari data di originalData untuk memastikan mendapat data yang belum difilter
        const found = this._originalData[rowID].find(
          (item) =>
            // Konversi ke string untuk comparison yang lebih aman
            String(item.id) === String(id)
        );

        if (!found) {
          console.warn(`Data dengan ID ${id} tidak ditemukan`);
          return null;
        }

        // Filter out sensitive fields from the found data
        const filteredData = {};
        for (const key in found) {
          if (found.hasOwnProperty(key)) {
            // Check if the field is sensitive (case insensitive)
            const isSensitive = NexaForge.SENSITIVE_FIELDS.some(
              (sensitiveField) =>
                sensitiveField.toLowerCase() === key.toLowerCase()
            );

            if (!isSensitive) {
              filteredData[key] = found[key];
            }
          }
        }

        return {
          success: true,
          data: filteredData,
          message: `Data dengan ID ${id} ditemukan`,
        };
      } catch (error) {
        console.error("Error dalam getID:", error);
        return {
          success: false,
          data: null,
          message: `Error: ${error.message}`,
        };
      }
    };

    /**
     * NEW: Memperbarui data berdasarkan ID tertentu
     * @param {number|string} id - ID data yang akan diupdate
     * @param {Object} updateData - Data baru untuk mengupdate
     * @param {boolean} reRender - Apakah perlu render ulang (default: true)
     * @returns {Object} Status update dan data yang diupdate
     */
    NexaForge.prototype.updateById = function (id, updateData, reRender = true) {
      if (!id) {
        return {
          success: false,
          message: "Parameter ID harus diisi",
          data: null,
        };
      }

      if (!updateData || typeof updateData !== "object") {
        return {
          success: false,
          message: "Parameter updateData harus berupa object",
          data: null,
        };
      }

      try {
        // Cari index data di originalData
        const originalIndex = this._originalData[rowID].findIndex(
          (item) => String(item.id) === String(id)
        );

        if (originalIndex === -1) {
          return {
            success: false,
            message: `Data dengan ID ${id} tidak ditemukan`,
            data: null,
          };
        }

        // Cari index data di data yang sedang ditampilkan (mungkin sudah difilter)
        const currentIndex = this._data[rowID].findIndex(
          (item) => String(item.id) === String(id)
        );

        // Backup data lama untuk rollback jika terjadi error
        const oldOriginalData = { ...this._originalData[rowID][originalIndex] };
        const oldCurrentData =
          currentIndex !== -1 ? { ...this._data[rowID][currentIndex] } : null;

        try {
          // Update data di originalData
          this._originalData[rowID][originalIndex] = {
            ...this._originalData[rowID][originalIndex],
            ...updateData,
          };

          // Update data di data yang sedang ditampilkan jika ada
          if (currentIndex !== -1) {
            this._data[rowID][currentIndex] = {
              ...this._data[rowID][currentIndex],
              ...updateData,
            };
          }

          // Re-render jika diminta
          if (reRender) {
            requestAnimationFrame(() => {
              this.renderData(this.curPage(this._currentPage || currentPage));

              // Trigger custom event untuk notifikasi update
              const updateEvent = new CustomEvent("dataUpdated", {
                detail: {
                  success: true,
                  id: id,
                  updatedData: this._originalData[rowID][originalIndex],
                  oldData: oldOriginalData,
                },
              });
              document.dispatchEvent(updateEvent);
            });
          }

          return {
            success: true,
            message: `Data dengan ID ${id} berhasil diupdate`,
            data: this._originalData[rowID][originalIndex],
            oldData: oldOriginalData,
          };
        } catch (error) {
          // Rollback jika terjadi error
          this._originalData[rowID][originalIndex] = oldOriginalData;
          if (currentIndex !== -1 && oldCurrentData) {
            this._data[rowID][currentIndex] = oldCurrentData;
          }
          throw error;
        }
      } catch (error) {
        console.error("Error dalam updateById:", error);
        return {
          success: false,
          message: `Error: ${error.message}`,
          data: null,
        };
      }
    };

    /**
     * NEW: Memperbarui multiple data sekaligus berdasarkan kondisi
     * @param {Function|Object} condition - Function untuk menentukan data mana yang diupdate, atau object untuk exact match
     * @param {Object} updateData - Data baru untuk mengupdate
     * @param {boolean} reRender - Apakah perlu render ulang (default: true)
     * @returns {Object} Status update dan informasi data yang diupdate
     */
    NexaForge.prototype.updateData = function (
      condition,
      updateData,
      reRender = true
    ) {
      if (!condition) {
        return {
          success: false,
          message: "Parameter condition harus diisi",
          updatedCount: 0,
        };
      }

      if (!updateData || typeof updateData !== "object") {
        return {
          success: false,
          message: "Parameter updateData harus berupa object",
          updatedCount: 0,
        };
      }

      try {
        let updatedCount = 0;
        const updatedItems = [];
        const errors = [];

        // Backup data untuk rollback
        const originalBackup = JSON.parse(
          JSON.stringify(this._originalData[rowID])
        );
        const currentBackup = JSON.parse(JSON.stringify(this._data[rowID]));

        try {
          // Update originalData
          this._originalData[rowID].forEach((item, index) => {
            let shouldUpdate = false;

            if (typeof condition === "function") {
              shouldUpdate = condition(item, index);
            } else if (typeof condition === "object") {
              // Check if all properties in condition match
              shouldUpdate = Object.keys(condition).every(
                (key) => String(item[key]) === String(condition[key])
              );
            }

            if (shouldUpdate) {
              const oldData = { ...item };
              this._originalData[rowID][index] = {
                ...item,
                ...updateData,
              };
              updatedItems.push({
                index,
                oldData,
                newData: this._originalData[rowID][index],
              });
              updatedCount++;
            }
          });

          // Update current data jika ada yang match
          this._data[rowID].forEach((item, index) => {
            let shouldUpdate = false;

            if (typeof condition === "function") {
              shouldUpdate = condition(item, index);
            } else if (typeof condition === "object") {
              shouldUpdate = Object.keys(condition).every(
                (key) => String(item[key]) === String(condition[key])
              );
            }

            if (shouldUpdate) {
              this._data[rowID][index] = {
                ...item,
                ...updateData,
              };
            }
          });

          // Re-render jika diminta
          if (reRender && updatedCount > 0) {
            requestAnimationFrame(() => {
              this.renderData(this.curPage(this._currentPage || currentPage));

              // Trigger custom event
              const updateEvent = new CustomEvent("bulkDataUpdated", {
                detail: {
                  success: true,
                  updatedCount,
                  updatedItems,
                  condition,
                  updateData,
                },
              });
              document.dispatchEvent(updateEvent);
            });
          }

          return {
            success: true,
            message: `${updatedCount} data berhasil diupdate`,
            updatedCount,
            updatedItems,
          };
        } catch (error) {
          // Rollback semua perubahan
          this._originalData[rowID] = originalBackup;
          this._data[rowID] = currentBackup;
          throw error;
        }
      } catch (error) {
        console.error("Error dalam updateData:", error);
        return {
          success: false,
          message: `Error: ${error.message}`,
          updatedCount: 0,
        };
      }
    };

    /**
     * NEW: Menghapus data berdasarkan ID tertentu
     * @param {number|string} id - ID data yang akan dihapus
     * @param {boolean} reRender - Apakah perlu render ulang (default: true)
     * @returns {Object} Status penghapusan dan data yang dihapus
     */
    NexaForge.prototype.deleteById = function (id, reRender = true) {
      if (!id) {
        return {
          success: false,
          message: "Parameter ID harus diisi",
          data: null,
        };
      }

      try {
        // Cari index di originalData
        const originalIndex = this._originalData[rowID].findIndex(
          (item) => String(item.id) === String(id)
        );

        if (originalIndex === -1) {
          return {
            success: false,
            message: `Data dengan ID ${id} tidak ditemukan`,
            data: null,
          };
        }

        // Cari index di current data
        const currentIndex = this._data[rowID].findIndex(
          (item) => String(item.id) === String(id)
        );

        // Simpan data yang akan dihapus
        const deletedData = { ...this._originalData[rowID][originalIndex] };

        // Hapus dari originalData
        this._originalData[rowID].splice(originalIndex, 1);

        // Hapus dari current data jika ada
        if (currentIndex !== -1) {
          this._data[rowID].splice(currentIndex, 1);
        }

        // Update pagination
        const totalItems = this._data[rowID].length;
        const totalPages = Math.ceil(
          totalItems / (this._pageLimit || pageLimit)
        );

        // Adjust current page jika perlu
        if (this._currentPage > totalPages && totalPages > 0) {
          this._currentPage = totalPages;
        } else if (totalPages === 0) {
          this._currentPage = 1;
        }

        // Re-render jika diminta
        if (reRender) {
          requestAnimationFrame(() => {
            this.renderData(this.curPage(this._currentPage || currentPage));

            // Update pagination UI jika ada
            if (typeof this.updatePaginationUI === "function") {
              this.updatePaginationUI();
            }

            // Trigger custom event
            const deleteEvent = new CustomEvent("dataDeleted", {
              detail: {
                success: true,
                id: id,
                deletedData,
                totalItems,
                currentPage: this._currentPage,
              },
            });
            document.dispatchEvent(deleteEvent);
          });
        }

        return {
          success: true,
          message: `Data dengan ID ${id} berhasil dihapus`,
          data: deletedData,
          totalItems,
        };
      } catch (error) {
        console.error("Error dalam deleteById:", error);
        return {
          success: false,
          message: `Error: ${error.message}`,
          data: null,
        };
      }
    };

    // Tambahkan method setData
    this.setData = function (newData) {
      if (!Array.isArray(newData)) {
        throw new Error("Parameter data harus berupa array");
      }

      try {
        // Update data di storage
        const storage = this._storage.get(this);

        // Update both data and originalData
        this._data = {
          [extractor]: newData,
        };
        this._originalData = {
          [extractor]: [...newData],
        };

        // Update storage data
        storage.data = this._data;

        // Reset page
        this._currentPage = 1;

        // Render data
        renderData(curPage(1));
        updatePaginationUI();

        return true;
      } catch (error) {
        console.error("Error dalam setData:", error);
        return false;
      }
    };

    // Inisialisasi variabel penting
    this._currentPage = 1;
    this._pageLimit = options.order || 10;
    this._rowID = extractor;

    // Tambahkan performance tracking
    this._performance = {
      renderTime: [],
      filterTime: [],
      sortTime: [],
    };

    // Bind methods ke instance
    this.handleSearch = handleSearch.bind(this);
    this.renderData = renderData.bind(this);
    this.curPage = curPage.bind(this);
    this.updatePaginationUI = updatePaginationUI.bind(this);

    // Setup fitur-fitur
    setupSearch();
    setupFilter();
    setupPaginationListeners();

    // Initial render jika ada data
    if (this._data[this._rowID].length > 0) {
      this.renderData(this.curPage(1));
    }

    // Tambahkan template compilation
    this.compileTemplate = function (template) {
      return template.replace(/\{\{([^}]+)\}\}/g, (_, key) => {
        return `\${data.${key}}`;
      });
    };

    // Tambahkan mekanisme recovery
    this.recover = function () {
      this._data = { ...this._originalData };
      this.renderData(this.curPage(1));
      this.updatePaginationUI();
    };

    // Mengaktifkan/menonaktifkan penyembunyian field sensitif
    this.setSensitiveFieldsVisibility = function (enabled) {
      this._options.hideSensitiveFields = enabled;

      // Re-render data dengan setting baru
      if (this._data && this._currentPage) {
        this.renderData(this.curPage(this._currentPage));
      }
    };

    // Mendapatkan status penyembunyian field sensitif
    this.getSensitiveFieldsVisibility = function () {
      return this._options.hideSensitiveFields;
    };

    // Mendapatkan daftar field sensitif saat ini
    this.getSensitiveFields = function () {
      return [...NexaForge.SENSITIVE_FIELDS];
    };
  }

  /**
   * NEW: Validate endpoint configuration
   * @private
   */
  _validateEndpointConfig(endpoint) {
    if (!endpoint || typeof endpoint !== "object") {
      throw new Error("Endpoint harus berupa object");
    }

    if (!endpoint.url || typeof endpoint.url !== "string") {
      throw new Error("Endpoint.url harus berupa string");
    }

    // Validate URL format
    try {
      new URL(endpoint.url);
    } catch (error) {
      throw new Error("Endpoint.url tidak valid: " + error.message);
    }

    // Default values
    endpoint.method = endpoint.method || "GET";
    endpoint.headers = endpoint.headers || {};
    endpoint.timeout = endpoint.timeout || 30000; // 30 seconds default
    endpoint.retryAttempts = endpoint.retryAttempts || 3;
    endpoint.retryDelay = endpoint.retryDelay || 1000; // 1 second

    // Validate method
    const validMethods = ["GET", "POST", "PUT", "PATCH", "DELETE"];
    if (!validMethods.includes(endpoint.method.toUpperCase())) {
      throw new Error(
        `HTTP method '${
          endpoint.method
        }' tidak didukung. Gunakan: ${validMethods.join(", ")}`
      );
    }

    return true;
  }

  /**
   * NEW: Setup loading dan error UI elements for API
   * @private
   */
  _setupApiElements(parentElement) {
    // Create loading element
    this._loadingElement = document.createElement("div");
    this._loadingElement.className = "nexadom-loading text-center p-4";
    this._loadingElement.style.display = "none";
    this._loadingElement.innerHTML = `
      <div class="d-flex flex-column align-items-center">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-muted mb-0">Memuat data...</p>
      </div>
    `;

    // Create error element
    this._errorElement = document.createElement("div");
    this._errorElement.className = "nexadom-error alert alert-danger";
    this._errorElement.style.display = "none";
    this._errorElement.innerHTML = `
      <div class="d-flex align-items-center">
        <span class="material-symbols-outlined me-2">error</span>
        <div class="flex-grow-1">
          <strong>Error loading data!</strong>
          <p class="mb-0 mt-1 error-message">Tidak dapat memuat data dari server.</p>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm retry-btn">
          <span class="material-symbols-outlined">refresh</span>
          Retry
        </button>
      </div>
    `;

    // Insert elements after parent element
    parentElement.parentNode.insertBefore(
      this._loadingElement,
      parentElement.nextSibling
    );
    parentElement.parentNode.insertBefore(
      this._errorElement,
      this._loadingElement.nextSibling
    );

    // Setup retry button
    const retryBtn = this._errorElement.querySelector(".retry-btn");
    retryBtn.addEventListener("click", () => {
      this._retryApiCall();
    });
  }

  /**
   * NEW: Set API endpoint and fetch data
   * @param {Object} requestData - Data to send with API request (optional)
   * @param {Object} options - Additional options for API call
   * @returns {Promise} Promise that resolves with API response
   */
  async setApi(requestData = null, options = {}) {
    if (!this._options.endpoint) {
      throw new Error(
        "Endpoint tidak dikonfigurasi. Gunakan endpoint option saat inisialisasi."
      );
    }

    this._isApiMode = true;

    const apiOptions = {
      showLoading: true,
      showError: true,
      ...options,
    };

    try {
      // Show loading state
      if (apiOptions.showLoading) {
        this._showLoading();
      }

      // Prepare request configuration
      const requestConfig = {
        method: this._options.endpoint.method.toUpperCase(),
        headers: {
          "Content-Type": "application/json",
          ...this._options.endpoint.headers,
        },
      };

      // Add request body for non-GET requests
      if (["POST", "PUT", "PATCH"].includes(requestConfig.method)) {
        if (requestData) {
          requestConfig.body = JSON.stringify(requestData);
        }
      }

      // Add timeout support
      const controller = new AbortController();
      const timeoutId = setTimeout(
        () => controller.abort(),
        this._options.endpoint.timeout
      );
      requestConfig.signal = controller.signal;

      // Make API call with retry logic
      const response = await this._makeApiCallWithRetry(
        this._options.endpoint.url,
        requestConfig
      );

      clearTimeout(timeoutId);

      // Process response
      const data = await this._processApiResponse(response);

      // Update DOM with API data
      this._updateDomFromApi(data);

      // Hide loading state
      if (apiOptions.showLoading) {
        this._hideLoading();
      }

      // Store API state
      const storage = this._storage.get(this);
      storage.apiState = {
        loading: false,
        error: null,
        lastResponse: data,
        retryCount: 0,
      };

      // Trigger custom event
      this._dispatchApiEvent("dataLoaded", {
        success: true,
        data: data,
        requestData: requestData,
      });

      return {
        success: true,
        data: data,
        message: "Data berhasil dimuat dari API",
      };
    } catch (error) {
      console.error("API Error:", error);

      // Hide loading state
      if (apiOptions.showLoading) {
        this._hideLoading();
      }

      // Show error state
      if (apiOptions.showError) {
        this._showError(error);
      }

      // Store error state
      const storage = this._storage.get(this);
      storage.apiState = {
        loading: false,
        error: error,
        lastResponse: null,
        retryCount: storage.apiState.retryCount + 1,
      };

      // Trigger custom event
      this._dispatchApiEvent("dataError", {
        success: false,
        error: error.message,
        requestData: requestData,
      });

      return {
        success: false,
        error: error.message,
        data: null,
      };
    }
  }

  /**
   * NEW: Make API call with retry logic
   * @private
   */
  async _makeApiCallWithRetry(url, config, retryCount = 0) {
    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return response;
    } catch (error) {
      const maxRetries = this._options.endpoint.retryAttempts || 3;

      if (retryCount < maxRetries && !config.signal?.aborted) {
        console.warn(
          `API call failed, retrying (${retryCount + 1}/${maxRetries})...`
        );

        // Wait before retry
        await new Promise((resolve) =>
          setTimeout(
            resolve,
            this._options.endpoint.retryDelay * (retryCount + 1)
          )
        );

        return this._makeApiCallWithRetry(url, config, retryCount + 1);
      }

      throw error;
    }
  }

  /**
   * NEW: Process API response and extract data
   * @private
   */
  async _processApiResponse(response) {
    const contentType = response.headers.get("content-type");

    if (contentType && contentType.includes("application/json")) {
      const jsonData = await response.json();

      // Handle different response structures
      if (jsonData.data && Array.isArray(jsonData.data)) {
        return jsonData.data; // Standard { data: [...] } format
      } else if (Array.isArray(jsonData)) {
        return jsonData; // Direct array format
      } else if (jsonData.rows && Array.isArray(jsonData.rows)) {
        return jsonData.rows; // DataTables format compatibility
      } else if (jsonData.items && Array.isArray(jsonData.items)) {
        return jsonData.items; // Alternative items format
      } else {
        throw new Error("Response tidak mengandung array data yang valid");
      }
    } else {
      throw new Error("Response bukan JSON format");
    }
  }

  /**
   * NEW: Update DOM with data from API
   * @private
   */
  _updateDomFromApi(data) {
    if (!Array.isArray(data)) {
      throw new Error("Data dari API harus berupa array");
    }

    // Get extractor name from the first key of _data
    const extractor = Object.keys(this._data)[0];

    // Process data dengan nomor urut
    const processedData = {
      [extractor]: data.map((item, index) => ({
        ...item,
        no: index + 1,
      })),
    };

    // Update data properties
    this._originalData = { ...processedData };
    this._data = { ...processedData };

    // Re-render with new data
    this.renderData(this._data);
  }

  /**
   * NEW: Show loading state
   * @private
   */
  _showLoading() {
    if (this._loadingElement) {
      this._loadingElement.style.display = "block";
    }
    if (this._errorElement) {
      this._errorElement.style.display = "none";
    }
  }

  /**
   * NEW: Hide loading state
   * @private
   */
  _hideLoading() {
    if (this._loadingElement) {
      this._loadingElement.style.display = "none";
    }
  }

  /**
   * NEW: Show error state
   * @private
   */
  _showError(error) {
    if (this._errorElement) {
      const errorMessage = this._errorElement.querySelector(".error-message");
      if (errorMessage) {
        errorMessage.textContent =
          error.message || "Terjadi kesalahan saat memuat data";
      }
      this._errorElement.style.display = "block";
    }
    if (this._loadingElement) {
      this._loadingElement.style.display = "none";
    }
  }

  /**
   * NEW: Retry API call
   * @private
   */
  async _retryApiCall() {
    const storage = this._storage.get(this);
    const lastRequestData = storage.apiState?.lastRequestData || null;

    try {
      await this.setApi(lastRequestData);
    } catch (error) {
      console.error("Retry failed:", error);
    }
  }

  /**
   * NEW: Dispatch custom API events
   * @private
   */
  _dispatchApiEvent(eventType, detail) {
    const customEvent = new CustomEvent(`nexadom:${eventType}`, {
      detail: {
        domId: this._instanceId,
        elementId: this._options.elementById,
        ...detail,
      },
    });
    document.dispatchEvent(customEvent);
  }

  /**
   * NEW: Refresh data from API
   * @param {Object} requestData - New request data (optional)
   * @returns {Promise} Promise that resolves with API response
   */
  async refreshApi(requestData = null) {
    if (!this._isApiMode) {
      throw new Error(
        "DOM tidak dalam mode API. Gunakan setApi() terlebih dahulu."
      );
    }

    return await this.setApi(requestData, { showLoading: true });
  }

  /**
   * NEW: Update endpoint configuration
   * @param {Object} newEndpoint - New endpoint configuration
   */
  updateEndpoint(newEndpoint) {
    this._validateEndpointConfig(newEndpoint);
    this._options.endpoint = { ...this._options.endpoint, ...newEndpoint };
  }

  /**
   * NEW: Get current API state
   * @returns {Object} Current API state information
   */
  getApiState() {
    const storage = this._storage.get(this);
    return {
      isApiMode: this._isApiMode,
      ...storage.apiState,
    };
  }

  /**
   * NEW: Set custom loading message
   * @param {string} message - Loading message
   */
  setLoadingMessage(message) {
    if (this._loadingElement) {
      const messageElement = this._loadingElement.querySelector("p");
      if (messageElement) {
        messageElement.textContent = message;
      }
    }
  }

  /**
   * NEW: Enable/disable API mode
   * @param {boolean} enabled - Enable or disable API mode
   */
  setApiMode(enabled) {
    this._isApiMode = enabled;

    if (!enabled) {
      this._hideLoading();
      if (this._errorElement) {
        this._errorElement.style.display = "none";
      }
    }
  }

  /**
   * NEW: Check if DOM is in API mode
   * @returns {boolean} True if in API mode
   */
  isApiMode() {
    return this._isApiMode;
  }

  /**
   * NEW: Set request data for next API call
   * @param {Object} requestData - Request data to store
   */
  setRequestData(requestData) {
    const storage = this._storage.get(this);
    if (storage && storage.apiState) {
      storage.apiState.lastRequestData = requestData;
    }
  }

  /**
   * NEW: Get last API response
   * @returns {*} Last successful API response data
   */
  getLastApiResponse() {
    const storage = this._storage.get(this);
    return storage?.apiState?.lastResponse || null;
  }

  /**
   * NEW: Force retry API call (public method)
   * @param {Object} requestData - Optional new request data
   * @returns {Promise} Promise that resolves with API response
   */
  async retry(requestData = null) {
    if (!this._isApiMode) {
      throw new Error("DOM tidak dalam mode API.");
    }

    const storage = this._storage.get(this);
    const dataToUse = requestData || storage?.apiState?.lastRequestData || null;

    return await this.setApi(dataToUse);
  }

  /**
   * NEW: Set API timeout
   * @param {number} timeout - Timeout in milliseconds
   */
  setTimeout(timeout) {
    if (this._options.endpoint) {
      this._options.endpoint.timeout = timeout;
    }
  }

  /**
   * NEW: Set retry configuration
   * @param {Object} retryConfig - Retry configuration
   * @param {number} retryConfig.attempts - Number of retry attempts
   * @param {number} retryConfig.delay - Delay between retries in milliseconds
   */
  setRetryConfig(retryConfig) {
    if (this._options.endpoint) {
      if (retryConfig.attempts !== undefined) {
        this._options.endpoint.retryAttempts = retryConfig.attempts;
      }
      if (retryConfig.delay !== undefined) {
        this._options.endpoint.retryDelay = retryConfig.delay;
      }
    }
  }

  /**
   * NEW: Get endpoint configuration
   * @returns {Object|null} Current endpoint configuration
   */
  getEndpoint() {
    return this._options.endpoint ? { ...this._options.endpoint } : null;
  }
}

// Export class-class tambahan jika diperlukan

export class NexaDomextractor {
  constructor(filter, options = {}) {
    if (!filter || !(filter instanceof NexaFilter)) {
      this.filter = new NexaFilter();
    } else {
      this.filter = filter;
    }

    // Simpan options untuk penyembunyian field sensitif
    this.options = {
      hideSensitiveFields: true,
      ...options,
    };

    // Tambahkan method untuk set pagination info
    this.paginationInfo = {
      currentPage: 1,
      pageLimit: 10,
    };

    this.setPaginationInfo = function (currentPage, pageLimit) {
      this.paginationInfo.currentPage = currentPage || 1;
      this.paginationInfo.pageLimit = pageLimit || 10;
    };

    /**
     * Menyembunyikan nilai field sensitif (sama logika dengan NexaForge)
     * @private
     */
    this._hideSensitiveField = function (fieldName, value) {
      if (!fieldName || value === null || value === undefined) {
        return value;
      }

      const normalizedFieldName = fieldName.toLowerCase();

      // Cek apakah field termasuk sensitif
      const isSensitive = NexaForge.SENSITIVE_FIELDS.some((sensitiveField) =>
        normalizedFieldName.includes(sensitiveField.toLowerCase())
      );

      if (isSensitive) {
        // Kembalikan string kosong atau placeholder untuk field sensitif
        return typeof value === "string" && value.length > 0 ? "••••••••" : "";
      }

      return value;
    };

    this.render = function (template, data, element) {
      try {
        let result = "";
       const base_url = NEXA.url || null;
       const page_home = NEXA.controllers.page_home || null;
       const page_index = NEXA.controllers.page_index || null;
       const projectAssets = NEXA.controllers.projectAssets || null;
       const projectDrive = NEXA.controllers.projectDrive || null; 
        const dataKeys = Object.keys(data);

        dataKeys.forEach((key) => {
          const items = data[key];
          if (!Array.isArray(items)) {
            console.warn(`Data untuk key ${key} bukan array:`, items);
            return;
          }

          // Gunakan nx-row sebagai wrapper
          result = `<div class="nx-row">
            ${items
              .map((item, index) => {
                // Gunakan template asli tanpa menambahkan class baru
                let itemTemplate = template;

                const startNumber =
                  (this.paginationInfo.currentPage - 1) *
                    this.paginationInfo.pageLimit +
                  1;
                const itemWithNumber = {
                  ...item,
                  no: startNumber + index,
                  base_url: base_url,
                  page_index: page_index,
                  projectAssets: projectAssets,
                  projectDrive: projectDrive,
                };

                // FIRST: Process ternary expressions if method is available (NEW FEATURE)
                try {
                  if (
                    this.filter &&
                    typeof this.filter.processTernary === "function"
                  ) {
                    itemTemplate = this.filter.processTernary(
                      itemTemplate,
                      itemWithNumber
                    );
                  }
                } catch (ternaryError) {
                  console.warn(
                    "Error processing ternary expressions in NexaForge render:",
                    ternaryError
                  );
                }

                // SECOND: Process switch statements if method is available
                try {
                  if (
                    this.filter &&
                    typeof this.filter.processSwitch === "function"
                  ) {
                    itemTemplate = this.filter.processSwitch(
                      itemTemplate,
                      itemWithNumber
                    );
                  }
                } catch (switchError) {
                  console.warn(
                    "Error processing switch statements in NexaForge render:",
                    switchError
                  );
                }

                // Process nested properties
                const processNestedProperty = (obj, path) => {
                  if (!path) return undefined;

                  const parts = path.split(".");
                  let value = obj;

                  for (const part of parts) {
                    if (value === undefined || value === null) return undefined;
                    value = value[part];
                  }

                  return value;
                };

                // Helper untuk mendapatkan value dari path bersarang
                const getNestedValue = (item, propPath) => {
                  // Handle direct property
                  if (!propPath.includes(".")) {
                    return item[propPath];
                  }

                  // Handle nested property
                  return processNestedProperty(item, propPath);
                };

                // THIRD: Proses template untuk placeholder biasa (setelah ternary dan switch)
                // Handle curly brace syntax untuk semua level kedalaman
                const fullPattern = /{([^}|]+)(?:\|([^}]+))?}/g;

                itemTemplate = itemTemplate.replace(
                  fullPattern,
                  (match, propPath, filters) => {
                    try {
                      // Skip jika sudah diproses sebagai ternary atau switch
                      if (
                        match.includes("?") ||
                        match.includes("===") ||
                        match.includes("!==") ||
                        match.includes("==") ||
                        match.includes("!=") ||
                        match.includes(">=") ||
                        match.includes("<=") ||
                        match.includes(">") ||
                        match.includes("<") ||
                        match.includes("switch") ||
                        match.includes("case")
                      ) {
                        return match;
                      }

                      // Cek apakah ini adalah properti bersarang dengan prefix
                      if (
                        propPath.startsWith(`${key}.`) ||
                        propPath.startsWith("row.")
                      ) {
                        // Hilangkan prefix
                        const cleanPath = propPath
                          .replace(`${key}.`, "")
                          .replace("row.", "");
                        let value = getNestedValue(itemWithNumber, cleanPath);

                        // Sembunyikan field sensitif jika diperlukan
                        if (this.options.hideSensitiveFields) {
                          value = this._hideSensitiveField(cleanPath, value);
                        }

                        // Apply filters jika ada
                        if (filters) {
                          try {
                            // Parse filters menggunakan NexaFilter parseFilters method
                            const filtersList =
                              this.filter.parseFilters(filters);

                            // Apply setiap filter secara berurutan
                            filtersList.forEach((filter) => {
                              value = this.filter.Filter(
                                value,
                                filter.name,
                                filter.args
                              );
                            });
                          } catch (filterError) {
                            console.warn(
                              "Error applying filters:",
                              filterError,
                              filters
                            );
                          }
                        }

                        return value !== undefined ? value : "";
                      }
                      // Direct property access
                      else if (itemWithNumber[propPath] !== undefined) {
                        let value = itemWithNumber[propPath];

                        // Sembunyikan field sensitif jika diperlukan
                        if (this.options.hideSensitiveFields) {
                          value = this._hideSensitiveField(propPath, value);
                        }

                        // Apply filters jika ada
                        if (filters) {
                          try {
                            // Parse filters menggunakan NexaFilter parseFilters method
                            const filtersList =
                              this.filter.parseFilters(filters);

                            // Apply setiap filter secara berurutan
                            filtersList.forEach((filter) => {
                              value = this.filter.Filter(
                                value,
                                filter.name,
                                filter.args
                              );
                            });
                          } catch (filterError) {
                            console.warn(
                              "Error applying filters:",
                              filterError,
                              filters
                            );
                          }
                        }

                        return value;
                      }

                      return "";
                    } catch (error) {
                      console.error("Error processing template:", error, match);
                      return "";
                    }
                  }
                );

                return itemTemplate;
              })
              .join("")}
          </div>`;
        });

        return result;
      } catch (error) {
        console.error("Error dalam render:", error);
        return "";
      }
    }.bind(this);

    /**
     * Parse string template menjadi DOM elements
     * @param {string} template - Template string
     * @returns {DocumentFragment}
     */
    this.parse = function (template) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(template, "text/html");
      const fragment = document.createDocumentFragment();

      while (doc.body.firstChild) {
        fragment.appendChild(doc.body.firstChild);
      }

      return fragment;
    };

    /**
     * Sanitize string untuk mencegah XSS
     * @param {string} str - String yang akan disanitize
     * @returns {string}
     */
    this.sanitize = function (str) {
      const div = document.createElement("div");
      div.textContent = str;
      return div.innerHTML;
    };
  }
}
