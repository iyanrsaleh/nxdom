
import { spinner as nexaSpinner } from "./NexaSpinner.js";

/**
 * NexaDom Class - Flexible pagination component
 * Dapat digunakan di berbagai file untuk menampilkan data dengan pagination
 */
export class NexaDom {
    /**
     * Create pagination instance with data loaded
     */
    static async create(options = {}) {
        const instance = new NexaDom(options);
        await instance.init();
        return instance;
    }

    constructor(options = {}) {
        // ✅ Load saved items per page from localStorage (inline method for constructor)
        const loadSavedItemsPerPage = (containerSelector) => {
            try {
                const storageKey = `nexa_tabel_items_per_page_${containerSelector || 'default'}`;
                const saved = localStorage.getItem(storageKey);
                if (saved) {
                    const itemsPerPage = parseInt(saved);
                    if (!isNaN(itemsPerPage) && itemsPerPage > 0) {
                        return itemsPerPage;
                    }
                }
            } catch (error) {
            }
            return null;
        };
        
        const savedItemsPerPage = loadSavedItemsPerPage(options.container);

        const defaultSpinner = {
            enabled: true,
            centerScreen: true,
            type: "overlay",
            size: "medium",
            color: "#007bff",
            position: "center",
            message: "",
        };
        const spinnerUser =
            options.spinner === false
                ? { ...defaultSpinner, enabled: false }
                : {
                      ...defaultSpinner,
                      ...(typeof options.spinner === "object" && options.spinner !== null
                          ? options.spinner
                          : {}),
                  };

        // Default configuration
        this.config = {
            pagination: options.pagination || options.elementBy || '#pagination', // Support both for backward compatibility
            container: options.container || '#dataContainer', // Target container untuk data
            paginationinfo: options.paginationinfo || null,
            searchElement: options.searchElement || null, // Element selector untuk input search
            sortClickElement: options.sortClickElement || options.sortCklik || null, // Element untuk toggle sort
            render: options.render || null, // Function untuk render template custom
            itemsPerPage: savedItemsPerPage ? savedItemsPerPage : (options.itemsPerPage || options.order || 10),
            maxVisiblePages: options.maxVisiblePages || 5,
            dataLoader: options.dataLoader || StorageData,
            onPageChange: options.onPageChange || null,
            searchKeyword: options.searchKeyword || '',
            searchFields: options.searchFields || [],
            filterValue: options.filterValue || '',
            filterField: options.filterField || '',
            sortBy: options.sortBy || 'id',
            sortOrder: options.sortOrder || 'DESC',
            showFirstLast: options.showFirstLast !== false,
            showPrevNext: options.showPrevNext !== false,
            showInfo: options.showInfo !== false,
            // ✅ Query Configuration - bisa dari config object atau individual options
            queryConfig: options.config,
            // ✅ Raw data - jika disediakan, akan menggunakan data ini langsung tanpa konfigurasi
            raw: options.raw || null,
            ...options,
            spinner: spinnerUser,
        };

        // ✅ NXUI.Storage().model("tabel") — query builder SQL (NexaModels), bukan Office.executeOperation
        //    Opsi: storage: { model, select?, query?(q) => q } — query dipanggil sebelum search/sort/filter NexaDom
        if (options.storage && typeof options.storage.model === "string" && options.storage.model) {
            this.config.dataLoader = storageModelStorageData;
            const baseCfg =
                this.config.queryConfig && typeof this.config.queryConfig === "object"
                    ? this.config.queryConfig
                    : {};
            const st = options.storage;
            this.config.queryConfig = {
                ...baseCfg,
                storageModelTable: st.model,
                storageModelQueryHook: typeof st.query === "function" ? st.query : undefined,
                storageModelSelect: Object.prototype.hasOwnProperty.call(st, "select") ? st.select : undefined,
            };
        }

        // State management
        this.state = {
            currentPage: this.getInitialPage(),
            totalCount: 0,
            totalPages: 0,
            data: [],
            loading: false
        };
        
        // ✅ Flag untuk melacak apakah sorting pernah diklik
        this.hasSortingBeenClicked = false;

        /** Instance NexaSpinner (lazy), dipakai saat `config.spinner.enabled` */
        this._nexaSpinnerApi = null;
        /** Kedalaman show bersarang — hide hanya memanggil NexaSpinner.hide saat 0 */
        this._spinnerShowDepth = 0;

        // Initialize response property with sample data for immediate use
        this.response = [
            {nama: "Loading...", jabatan: "Please wait..."},
        ];
        
        // Load real data and replace sample data
        this.loadRealDataAndUpdate();

        // Bind methods
        this.init = this.init.bind(this);
        this.loadData = this.loadData.bind(this);
        this.goToPage = this.goToPage.bind(this);
        this.render = this.render.bind(this);
    }

    /**
     * Get initial page from localStorage only (URL disabled)
     */
    getInitialPage() {
        // ✅ Get page from localStorage only (no URL parameter)
        const storageKey = `NexaDom_page_${this.config.pagination}`;
        const pageFromStorage = localStorage.getItem(storageKey);
        
        if (pageFromStorage && !isNaN(pageFromStorage)) {
            const page = parseInt(pageFromStorage);
            if (page > 0) {
                return page;
            }
        }
        
        // Default to page 1
        return 1;
    }

    /**
     * Apakah spinner Nexa (NXUI.spinner / NexaSpinner) aktif untuk instance ini.
     */
    _isSpinnerEnabled() {
        const s = this.config.spinner;
        return !!(s && s.enabled !== false);
    }

    /**
     * Opsi untuk `nexaSpinner()` — selaras exsampel: centerScreen + type overlay/inline.
     */
    _getSpinnerCreateOptions() {
        const s = this.config.spinner;
        const center = s.centerScreen !== false;
        let type = s.type || "overlay";
        if (center) {
            if (type !== "inline") type = "overlay";
        } else if (type === "overlay") {
            type = "inline";
        }
        const target = center ? "body" : this.config.container || "body";
        return {
            target,
            type,
            size: s.size || "medium",
            color: s.color || "#007bff",
            message: s.message || "",
            position: s.position || "center",
        };
    }

    showSpinner() {
        if (!this._isSpinnerEnabled()) return;
        try {
            const opts = this._getSpinnerCreateOptions();
            if (!this._nexaSpinnerApi) {
                this._nexaSpinnerApi = nexaSpinner(opts);
            }
            this._spinnerShowDepth++;
            if (this._spinnerShowDepth === 1) {
                this._nexaSpinnerApi.show();
            }
        } catch (e) {
            console.warn("NexaDom.showSpinner", e);
        }
    }

    hideSpinner() {
        if (!this._isSpinnerEnabled()) return;
        if (!this._nexaSpinnerApi) return;
        if (this._spinnerShowDepth <= 0) return;
        this._spinnerShowDepth--;
        if (this._spinnerShowDepth > 0) return;
        try {
            this._nexaSpinnerApi.hide();
        } catch (e) {
            console.warn("NexaDom.hideSpinner", e);
        }
    }

    /**
     * Save current page to localStorage only (URL disabled)
     */
    savePageState(page) {
        // ✅ Save to localStorage only (no URL update)
        const storageKey = `NexaDom_page_${this.config.pagination}`;
        if (page > 1) {
            localStorage.setItem(storageKey, page.toString());
        } else {
            localStorage.removeItem(storageKey); // Remove page=1 from storage
        }
    }

    /**
     * Initialize pagination
     */
    async init() {
        try {
            this.state.loading = true;
            this.showSpinner();

            // Wait for DOM element to be available
            await this.waitForElement();

            await this.loadData();
            this.render();
            this.attachEventListeners();
            
            // ✅ Call onAfterRender callback after initial render
            if (this.config.onAfterRender && typeof this.config.onAfterRender === 'function') {
                const container = document.querySelector(this.config.container);
                if (container && this.state.data) {
                    // Use requestAnimationFrame to ensure browser has painted before callback
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            this.config.onAfterRender(this.state.data, container);
                        }, 100);
                    });
                }
            }
        } catch (error) {
            this.renderError(error.message);
        } finally {
            this.state.loading = false;
            this.hideSpinner();
        }
    }

    /**
     * Wait for DOM element to be available
     */
    async waitForElement(maxAttempts = 50) {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            
            const checkElement = () => {
                const element = document.querySelector(this.config.pagination);
                
                if (element) {
                    resolve(element);
                } else if (attempts >= maxAttempts) {
                    reject(new Error(`Element ${this.config.pagination} not found after ${maxAttempts} attempts`));
                } else {
                    attempts++;
                    setTimeout(checkElement, 100);
                }
            };
            
            checkElement();
        });
    }

    /**
     * Load data using the provided data loader or raw data
     */
    async loadData() {
        // ✅ Jika raw data disediakan, gunakan data tersebut langsung
        if (this.config.raw && Array.isArray(this.config.raw)) {
            let filteredData = [...this.config.raw];
            
            // ✅ Apply search filter jika ada keyword
            if (this.config.searchKeyword && this.config.searchFields.length > 0) {
                const keyword = this.config.searchKeyword.toLowerCase();
                filteredData = filteredData.filter(item => {
                    return this.config.searchFields.some(field => {
                        const value = item[field];
                        return value && String(value).toLowerCase().includes(keyword);
                    });
                });
            }
            
            // ✅ Apply field filter jika ada
            if (this.config.filterValue && this.config.filterField) {
                filteredData = filteredData.filter(item => {
                    return String(item[this.config.filterField]) === String(this.config.filterValue);
                });
            }
            
            // ✅ Apply sorting
            if (this.config.sortBy) {
                filteredData.sort((a, b) => {
                    const aVal = a[this.config.sortBy];
                    const bVal = b[this.config.sortBy];
                    
                    // Handle null/undefined
                    if (aVal == null && bVal == null) return 0;
                    if (aVal == null) return 1;
                    if (bVal == null) return -1;
                    
                    // Compare values
                    let comparison = 0;
                    if (typeof aVal === 'string' && typeof bVal === 'string') {
                        comparison = aVal.localeCompare(bVal);
                    } else if (typeof aVal === 'number' && typeof bVal === 'number') {
                        comparison = aVal - bVal;
                    } else {
                        comparison = String(aVal).localeCompare(String(bVal));
                    }
                    
                    return this.config.sortOrder === 'ASC' ? comparison : -comparison;
                });
            }
            
            // ✅ Set total count dari filtered data
            this.state.totalCount = filteredData.length;
            this.state.totalPages = Math.ceil(this.state.totalCount / this.config.itemsPerPage);
            
            // ✅ Apply pagination
            const offset = (this.state.currentPage - 1) * this.config.itemsPerPage;
            this.state.data = filteredData.slice(offset, offset + this.config.itemsPerPage);
            
            // Validate current page doesn't exceed total pages
            if (this.state.currentPage > this.state.totalPages && this.state.totalPages > 0) {
                this.state.currentPage = this.state.totalPages;
                this.savePageState(this.state.currentPage);
                // Reload dengan page yang benar
                await this.loadData();
            }
            
            return;
        }
        
        // ✅ Jika tidak ada raw data, gunakan dataLoader seperti biasa
        if (!this.config.dataLoader) {
            throw new Error('Data loader function is required when raw data is not provided');
        }

        const offset = (this.state.currentPage - 1) * this.config.itemsPerPage;
        
        const result = await this.config.dataLoader(
            this.config.itemsPerPage,
            offset,
            this.config.searchKeyword,
            this.config.searchFields,
            this.config.filterValue,
            this.config.filterField,
            this.config.sortBy,
            this.config.sortOrder,
            this.config.queryConfig  // Pass query config object
        );

        this.state.totalCount = result.totalCount || 0;
        this.state.data = result.response || [];
        this.state.totalPages = Math.ceil(this.state.totalCount / this.config.itemsPerPage);
        
        // Validate current page doesn't exceed total pages
        if (this.state.currentPage > this.state.totalPages && this.state.totalPages > 0) {
            this.state.currentPage = this.state.totalPages;
            this.savePageState(this.state.currentPage);
        }
    }

    /**
     * Go to specific page
     */
    async goToPage(page) {
        if (page < 1 || page > this.state.totalPages || page === this.state.currentPage) {
            return;
        }

        this.state.currentPage = page;
        this.state.loading = true;
        this.showSpinner();

        // Save page state to URL and localStorage
        this.savePageState(page);

        try {
            await this.loadData();
            
            // Call onPageChange callback if provided
            if (this.config.onPageChange) {
                this.config.onPageChange(this.state.currentPage, this.state.data);
            }
            
            // Always render pagination after data update
            this.render();
        } catch (error) {
        } finally {
            this.state.loading = false;
            this.hideSpinner();
        }
    }

    /**
     * Update search parameters and reload
     */
    async updateSearch(searchKeyword, searchFields = []) {
        this.config.searchKeyword = searchKeyword;
        this.config.searchFields = searchFields;
        this.state.currentPage = 1;
        this.savePageState(1); // Save reset to page 1
        await this.init();
    }

    /**
     * Update filter parameters and reload
     */
    async updateFilter(filterValue, filterField) {
        this.config.filterValue = filterValue;
        this.config.filterField = filterField;
        this.state.currentPage = 1;
        this.savePageState(1); // Save reset to page 1

        this.showSpinner();
        try {
            // ✅ Load data dengan filter baru
            await this.loadData();

            // ✅ Update UI components
            this.updateDataContainer();
            this.renderPaginationInfo();
            this.render();
        } catch (error) {
            console.error('❌ Error updating filter:', error);
        } finally {
            this.hideSpinner();
        }
    }

    /**
     * Update sorting and reload
     */
    async updateSort(sortBy, sortOrder = 'DESC') {
        this.config.sortBy = sortBy;
        this.config.sortOrder = sortOrder;
        this.state.currentPage = 1;
        this.savePageState(1); // Save reset to page 1
        await this.init();
    }

    /**
     * Load saved items per page from localStorage
     */
    loadSavedItemsPerPage(containerSelector) {
        try {
            const storageKey = `nexa_tabel_items_per_page_${containerSelector || 'default'}`;
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const itemsPerPage = parseInt(saved);
                if (!isNaN(itemsPerPage) && itemsPerPage > 0) {
                    return itemsPerPage;
                }
            }
        } catch (error) {
        }
        return null;
    }

    /**
     * Save items per page to localStorage
     */
    saveItemsPerPage(itemsPerPage) {
        try {
            const storageKey = `nexa_tabel_items_per_page_${this.config.container || 'default'}`;
            localStorage.setItem(storageKey, itemsPerPage.toString());
        } catch (error) {
        }
    }

    /**
     * Update items per page and reload
     */
    async updateItemsPerPage(itemsPerPage) {
        const newLimit = parseInt(itemsPerPage);
        if (isNaN(newLimit) || newLimit <= 0) {
            return;
        }

        // Update configuration
        this.config.itemsPerPage = newLimit;
        
        // ✅ Save to localStorage for persistence
        this.saveItemsPerPage(newLimit);
        
        // Reset to page 1 when changing items per page
        this.state.currentPage = 1;
        this.savePageState(1);
        
        this.showSpinner();
        try {
            // Reload data with new limit
            await this.loadData();

            // Update total pages calculation
            this.state.totalPages = Math.ceil(this.state.totalCount / this.config.itemsPerPage);

            // Update UI components
            this.updateDataContainer();
            this.renderPaginationInfo();
            this.render();
        } finally {
            this.hideSpinner();
        }
    }

    /**
     * Generate pagination HTML
     */
    generatePaginationHTML() {
        
        if (this.state.totalPages <= 1) {
            return '';
        }

        let html = '<nav aria-label="pagination"><ul class="pagination-sm" id="paginationContainer">';

        // First page button
        if (this.config.showFirstLast) {
            const firstDisabled = this.state.currentPage === 1 ? 'disabled' : '';
            html += `
                <li class="page-item ${firstDisabled}" data-nav="first">
                    <a class="page-link" href="#" data-page="1" title="First Page">First</a>
                </li>
            `;
        }

        // Previous page button
        if (this.config.showPrevNext) {
            const prevDisabled = this.state.currentPage === 1 ? 'disabled' : '';
            const prevPage = this.state.currentPage - 1;
            html += `
                <li class="page-item ${prevDisabled}" data-nav="prev">
                    <a class="page-link" href="#" data-page="${prevPage}" title="Previous Page">Prev</a>
                </li>
            `;
        }

        // Page numbers
        const { startPage, endPage } = this.getPageRange();
        
        // Show ellipsis before if needed
        if (startPage > 1) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if (startPage > 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Page number buttons
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === this.state.currentPage ? 'active' : '';
            html += `
                <li class="page-item ${activeClass}">
                    <a class="page-link ${activeClass}" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        // Show ellipsis after if needed
        if (endPage < this.state.totalPages) {
            if (endPage < this.state.totalPages - 1) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${this.state.totalPages}">${this.state.totalPages}</a></li>`;
        }

        // Next page button
        if (this.config.showPrevNext) {
            const nextDisabled = this.state.currentPage === this.state.totalPages ? 'disabled' : '';
            const nextPage = this.state.currentPage + 1;
            html += `
                <li class="page-item ${nextDisabled}" data-nav="next">
                    <a class="page-link" href="#" data-page="${nextPage}" title="Next Page">Next</a>
                </li>
            `;
        }

        // Last page button
        if (this.config.showFirstLast) {
            const lastDisabled = this.state.currentPage === this.state.totalPages ? 'disabled' : '';
            html += `
                <li class="page-item ${lastDisabled}" data-nav="last">
                    <a class="page-link" href="#" data-page="${this.state.totalPages}" title="Last Page">Last</a>
                </li>
            `;
        }

        html += '</ul></nav>';

        return html;
    }

    /**
     * Get page range for pagination display
     */
    getPageRange() {
        const maxVisible = this.config.maxVisiblePages;
        const current = this.state.currentPage;
        const total = this.state.totalPages;

        let startPage = Math.max(1, current - Math.floor(maxVisible / 2));
        let endPage = Math.min(total, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        return { startPage, endPage };
    }

    /**
     * Render pagination to DOM
     */
    render() {
        // Wait a bit to ensure DOM is ready
        setTimeout(() => {
            const container = document.querySelector(this.config.pagination);
            if (!container) {
                return;
            }

            if (this.state.loading) {
                if (!this._isSpinnerEnabled()) {
                    container.innerHTML =
                        '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
                }
                return;
            }

            container.innerHTML = this.generatePaginationHTML();
            
            // Re-attach event listeners after rendering
            this.attachEventListeners();
        }, 50);
    }

    /**
     * Render error message
     */
    renderError(message) {
        const container = document.querySelector(this.config.pagination);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${message}
                </div>
            `;
        }
    }

    /**
     * Attach event listeners to pagination buttons
     */
    attachEventListeners() {
        const container = document.querySelector(this.config.pagination);
        if (!container) return;

        // Remove existing listeners if they exist
        if (this.handlePaginationClick) {
            container.removeEventListener('click', this.handlePaginationClick);
        }
        
        // Create new listener
        this.handlePaginationClick = (e) => {
            e.preventDefault();
            
            if (e.target.classList.contains('page-link') && !e.target.closest('.disabled')) {
                const page = parseInt(e.target.getAttribute('data-page'));
                if (page && !isNaN(page)) {
                    this.goToPage(page);
                }
            }
        };

        // Add new listener
        container.addEventListener('click', this.handlePaginationClick);
    }

    /**
     * Get current state
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Get current data
     */
    getData() {
        return this.state.data;
    }

    /**
     * Refresh current page with UI feedback
     */
    async refresh(showLoading = true) {
        try {
            if (showLoading) {
                if (this._isSpinnerEnabled()) {
                    this.showSpinner();
                } else {
                    this.showRefreshLoading();
                }
            }

            // Clear cache if exists
            this.clearCache();

            // Reload data
            await this.loadData();

            // Update UI components
            this.updateDataContainer();
            this.renderPaginationInfo();
            this.render();

            // Show success feedback
            if (showLoading) {
                this.showRefreshSuccess();
            }
        } catch (error) {
            if (showLoading) {
                this.showRefreshError(error.message);
            }
        } finally {
            if (showLoading && this._isSpinnerEnabled()) {
                this.hideSpinner();
            }
        }
    }

    /**
     * Show loading state during refresh
     */
    showRefreshLoading() {
        const container = document.querySelector(this.config.container);
        if (container) {
            const loadingHTML = `
                <div class="refresh-loading text-center p-4">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                  
                </div>
            `;
            container.innerHTML = loadingHTML;
        }
    }

    /**
     * Show success feedback after refresh
     */
    showRefreshSuccess() {
        // this.showTemporaryMessage('Data refreshed successfully', 'success');
    }

    /**
     * Show error feedback if refresh fails
     */
    showRefreshError(message) {
        this.showTemporaryMessage(`Refresh failed: ${message}`, 'danger');
    }

    /**
     * Show temporary message with auto-hide
     */
    showTemporaryMessage(message, type = 'info', duration = 3000, title='Tabel') {
        console.log('label:', message);

          const Notif = new NXUI.Notifikasi({ autoHideDelay: duration }); // 2 seconds
          Notif.show({
            type: type,
            title:title,
            subtitle:message,
            actions: true,
          });

    }

    /**
     * Clear any cached data
     */
    clearCache() {
        // Clear filter cache if exists (for EkasticTabel integration)
        if (this.filterCache) {
            this.filterCache = {};
        }
        
        // Clear EkasticTabel filter cache if available
        if (window.currentEkasticTabel && window.currentEkasticTabel.filterCache) {
            window.currentEkasticTabel.filterCache = {};
        }
        
        // Reset any cached states
        this.response = [];
    }

    /**
     * Load real data and update UI
     */
    async loadRealDataAndUpdate() {
        this.showSpinner();
        try {
            // ✅ Jika raw data disediakan, gunakan data tersebut langsung
            if (this.config.raw && Array.isArray(this.config.raw)) {
                try {
                    await this.loadData();
                    this.response = this.state.data || [];

                    setTimeout(() => {
                        this.updateDataContainer();
                        this.renderPaginationInfo();
                        this.autoSetup();
                    }, 100);
                } catch (error) {
                    this.response = [];
                }
                return;
            }

            // ✅ Jika tidak ada raw data, gunakan dataLoader (default StorageData)
            const offset = (this.state.currentPage - 1) * this.config.itemsPerPage;

            const loader =
                typeof this.config.dataLoader === "function" ? this.config.dataLoader : StorageData;

            try {
                const data = await loader(
                    this.config.itemsPerPage,
                    offset,
                    "",
                    [],
                    "",
                    "",
                    this.config.sortBy,
                    this.config.sortOrder,
                    this.config.queryConfig
                );

                this.response = data.response || [];
                this.state.data = this.response;
                this.state.totalCount = data.totalCount || 0;
                this.state.totalPages = Math.ceil(
                    this.state.totalCount / this.config.itemsPerPage
                );

                if (this.state.currentPage > this.state.totalPages && this.state.totalPages > 0) {
                    this.state.currentPage = this.state.totalPages;
                    this.savePageState(this.state.currentPage);
                    await this.loadRealDataAndUpdate();
                    return;
                }

                setTimeout(() => {
                    this.updateDataContainer();
                    this.renderPaginationInfo();
                    this.autoSetup();
                }, 100);
            } catch (error) {
                this.response = [];
            }
        } finally {
            this.hideSpinner();
        }
    }

    /**
     * Auto setup pagination and data container update
     */
    autoSetup() {
        // Render pagination
        const element = document.querySelector(this.config.pagination);
        if (element) {
            this.render();
        }
        
        // Setup auto data container update on page change
        const originalGoToPage = this.goToPage.bind(this);
        this.goToPage = async (page) => {
            await originalGoToPage(page);
            // Auto update data container and pagination info
            this.updateDataContainer();
            this.renderPaginationInfo();
        };

        // Setup search functionality if search element provided
        this.setupSearchListener();
        
        // Setup sort click functionality if sort element provided
        this.setupSortClickListener();
    }

    /**
     * Auto update data container with current data
     */
    updateDataContainer() {
        const container = document.querySelector(this.config.container);
        if (container && this.state.data) {
            let template = "";
            
            // ✅ Gunakan render dari user jika ada
            if (this.config.render && typeof this.config.render === 'function') {
                // ✅ Tambahkan nomor urut otomatis ke setiap item
                const dataWithNumbers = this.state.data.map((item, index) => {
                    // ✅ Hitung nomor berdasarkan posisi global
                    let globalPosition;
                    
                    // ✅ Cek apakah ada sorting aktif (bukan default)
                    const isSortingActive = this.config.sortBy !== 'id' || this.hasSortingBeenClicked;
                    
                    if (!isSortingActive) {
                        // ✅ Tidak ada sorting aktif: gunakan penomoran normal 1,2,3,4,5...
                        globalPosition = ((this.state.currentPage - 1) * this.config.itemsPerPage) + index + 1;
                    } else {
                        // ✅ Ada sorting aktif: nomor mengikuti urutan sorting
                        if (this.config.sortOrder === 'ASC') {
                            // Ascending: nomor dari awal (1, 2, 3, ...)
                            globalPosition = ((this.state.currentPage - 1) * this.config.itemsPerPage) + index + 1;
                        } else {
                            // Descending: nomor dari akhir dataset yang sudah di-sort
                            const itemsBeforeCurrentPage = (this.state.currentPage - 1) * this.config.itemsPerPage;
                            globalPosition = this.state.totalCount - itemsBeforeCurrentPage - index;
                            
                            // ✅ Sanity check: ensure nomor tidak negatif atau nol
                            if (globalPosition <= 0) {
                                globalPosition = ((this.state.currentPage - 1) * this.config.itemsPerPage) + index + 1;
                            }
                        }
                    }
                    
                    return {
                        ...item,
                        no: globalPosition  // ✅ Nomor sesuai kondisi sorting
                    };
                });
                
                template = this.config.render(dataWithNumbers);
            } else {
                // Default template jika user tidak provide - hanya debug info
                template = `
                <div class="alert alert-info">
                    <h5>⚠️ No render template provided</h5>
                    <p>Please provide a <code>render</code> function in NexaDom configuration.</p>
                    <p><strong>Data loaded:</strong> ${this.state.data.length} items</p>
                </div>
                `;
            }
            
            container.innerHTML = template;
            
            // ✅ Load column widths IMMEDIATELY setelah DOM ready (synchronous, no delay)
            // ✅ Use currentEkasticTabel instead of currentEkasticPackage untuk avoid race condition
            if (window.currentEkasticTabel && window.currentEkasticTabel.loadColumnWidths) {
                window.currentEkasticTabel.loadColumnWidths();
            }
            
            // 🔧 Simpan instance NexaDom ke container untuk akses di onAfterRender
            container._NexaDomInstance = this;
            container.setAttribute('data-nexa-dom', 'true');
            
            // 🔧 Simpan state ke global untuk akses mudah
            if (!window.NXUI) window.NXUI = {};
            window.NXUI.currentState = {
                totalCount: this.state.totalCount,
                currentPage: this.state.currentPage,
                totalPages: this.state.totalPages,
                dataLength: this.state.data.length,
                lastUpdated: new Date().toISOString()
            };
            
            // ✅ Trigger callback setelah DOM di-update untuk inisialisasi elemen dinamis
            if (this.config.onAfterRender && typeof this.config.onAfterRender === 'function') {
                // Use requestAnimationFrame to ensure browser has painted before callback
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        this.config.onAfterRender(this.state.data, container);
                    }, 50);
                });
            }
            
            // ✅ Reinitialize column resize functionality after data update (via onAfterRender callback)
            // Note: initializeColumnResize akan dipanggil di onAfterRender callback untuk ensure proper timing
            
            // ✅ Update inline editing table data
            if (window.currentEkasticTabel && window.currentEkasticTabel.updateInlineTableData) {
                window.currentEkasticTabel.updateInlineTableData(this.state.data);
            }
        }
    }

    /**
     * Render pagination info to target element
     */
    renderPaginationInfo() {
        if (!this.config.paginationinfo) return;
        
        const infoContainer = document.querySelector(this.config.paginationinfo);
        if (!infoContainer) return;

        const startItem = ((this.state.currentPage - 1) * this.config.itemsPerPage) + 1;
        const endItem = Math.min(this.state.currentPage * this.config.itemsPerPage, this.state.totalCount);
         NXUI.pageInfo={
           page: this.state.currentPage, 
           total: this.state.totalPages, 
        }
        const infoHTML = `
            ${startItem} sampai ${endItem} dari ${this.state.totalCount} data
            (Halaman ${this.state.currentPage} dari ${this.state.totalPages})
        `;

      
        
        infoContainer.innerHTML = infoHTML;
    }

    /**
     * Setup search listener on search element
     */
    setupSearchListener() {
        if (!this.config.searchElement) return;

        const searchInput = document.querySelector(this.config.searchElement);
        if (!searchInput) return;

        if (this._searchDebouncedHandler) {
            searchInput.removeEventListener("input", this._searchDebouncedHandler);
            this._searchDebouncedHandler = null;
        }

        let searchTimeout;
        this._searchDebouncedHandler = (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                const keyword = e.target.value.trim();
                await this.performSearch(keyword);
            }, 300);
        };
        searchInput.addEventListener("input", this._searchDebouncedHandler);
    }

    /**
     * Setup sort click listener on sort element
     */
    setupSortClickListener() {
        if (!this.config.sortClickElement) return;

        const sortElement = document.querySelector(this.config.sortClickElement);
        if (!sortElement) return;

        // Remove existing listener if exists
        if (this.handleSortClick) {
            sortElement.removeEventListener('click', this.handleSortClick);
        }

        // Create new listener
        this.handleSortClick = async (e) => {
            e.preventDefault();
            
            // ✅ Set flag bahwa sorting sudah pernah diklik
            this.hasSortingBeenClicked = true;
            
            // Toggle sort order
            const newSortOrder = this.config.sortOrder === 'ASC' ? 'DESC' : 'ASC';
            
            
            // Update sort order
            this.config.sortOrder = newSortOrder;
            this.state.currentPage = 1; // Reset ke halaman 1
            this.savePageState(1); // Save reset to page 1
            
            // Update button text to show current sort
            this.updateSortButtonText(sortElement, newSortOrder);

            this.showSpinner();
            try {
                await this.loadData();

                // Update response untuk akses langsung
                this.response = this.state.data;

                this.updateDataContainer();
                this.renderPaginationInfo();

                // Render pagination
                setTimeout(() => {
                    this.render();
                }, 50);
            } catch (error) {
            } finally {
                this.hideSpinner();
            }
        };

        // Add new listener
        sortElement.addEventListener('click', this.handleSortClick);
        
        // Set initial button text
        this.updateSortButtonText(sortElement, this.config.sortOrder);
    }

    /**
     * Update sort button icon to show current sort direction
     */
    updateSortButtonText(element, sortOrder) {
        // Change icon based on sort direction
        const iconElement = element.querySelector('.material-symbols-outlined');
        if (iconElement) {
            iconElement.textContent = sortOrder === 'ASC' ? 'arrow_upward' : 'arrow_downward';
        }
    }

    /**
     * Perform search with keyword
     */
    async performSearch(keyword) {
        this.config.searchKeyword = keyword;
        this.state.currentPage = 1; // Reset ke halaman 1
        this.savePageState(1); // Save reset to page 1

        this.showSpinner();
        try {
            await this.loadData();

            // Update response untuk akses langsung
            this.response = this.state.data;

            this.updateDataContainer();
            this.renderPaginationInfo();

            // Render pagination - akan muncul jika totalPages > 1
            setTimeout(() => {
                this.render();
            }, 50);
        } catch (error) {
        } finally {
            this.hideSpinner();
        }
    }

    /**
     * Destroy pagination instance
     */
    destroy() {
        const container = document.querySelector(this.config.pagination);
        if (container) {
            container.removeEventListener('click', this.handlePaginationClick);
            container.innerHTML = '';
        }
        
        // Remove sort click listener
        if (this.config.sortClickElement && this.handleSortClick) {
            const sortElement = document.querySelector(this.config.sortClickElement);
            if (sortElement) {
                sortElement.removeEventListener('click', this.handleSortClick);
            }
        }

        if (this.config.searchElement && this._searchDebouncedHandler) {
            const searchInput = document.querySelector(this.config.searchElement);
            if (searchInput) {
                searchInput.removeEventListener("input", this._searchDebouncedHandler);
            }
            this._searchDebouncedHandler = null;
        }

        if (this._nexaSpinnerApi && typeof this._nexaSpinnerApi.destroy === "function") {
            try {
                this._spinnerShowDepth = 0;
                this._nexaSpinnerApi.destroy();
            } catch (_) {
                /* ignore */
            }
            this._nexaSpinnerApi = null;
        }

        // Optionally clear localStorage (uncomment if needed)
        // const storageKey = `NexaDom_page_${this.config.pagination}`;
        // localStorage.removeItem(storageKey);
    }
}

/**
 * Normalisasi baris hasil NXUI.Storage().model().get() / API Storage.
 */
function normalizeStorageModelRows(res) {
    if (res == null) return [];
    if (Array.isArray(res)) return res;
    if (Array.isArray(res.data)) return res.data;
    if (Array.isArray(res.response)) return res.response;
    if (Array.isArray(res.rows)) return res.rows;
    if (res.data && Array.isArray(res.data.rows)) return res.data.rows;
    if (res.data && Array.isArray(res.data.data)) return res.data.data;
    return [];
}

/**
 * Normalisasi total dari count() / agregat.
 */
function normalizeStorageModelCount(res) {
    if (res == null) return 0;
    if (typeof res === "number") return res;
    if (typeof res.total === "number") return res.total;
    if (typeof res.totalCount === "number") return res.totalCount;
    if (typeof res.count === "number") return res.count;
    const rows = normalizeStorageModelRows(res);
    if (rows.length && rows[0]) {
        const r = rows[0];
        if (typeof r.total !== "undefined") return parseInt(r.total, 10) || 0;
        if (typeof r.count !== "undefined") return parseInt(r.count, 10) || 0;
    }
    if (res.data != null && typeof res.data === "object") {
        if (typeof res.data.total !== "undefined") return parseInt(res.data.total, 10) || 0;
        if (typeof res.data.count !== "undefined") return parseInt(res.data.count, 10) || 0;
    }
    return 0;
}

/**
 * Data loader untuk `new NexaDom({ storage: { model, select?, query? }, ... })`.
 * - `select`: kolom (default "*") — sama argumen dengan NexaModels.select.
 * - `query`: (builder) => builder — tambah where/join/dll. sebelum filter pencarian & order NexaDom.
 * Memakai NXUI.Storage().model(tabel) — docs/NexaModels.md.
 */
export async function storageModelStorageData(
    limit = 10,
    offset = 0,
    searchKeyword = "",
    searchFields = [],
    filterValue = "",
    filterField = "",
    sortBy = "id",
    sortOrder = "DESC",
    queryConfig = {}
) {
    const table = queryConfig && queryConfig.storageModelTable;
    if (!table || typeof NXUI === "undefined" || !NXUI.Storage) {
        return { totalCount: 0, response: [] };
    }
    try {
        const sel =
            queryConfig.storageModelSelect !== undefined && queryConfig.storageModelSelect !== null
                ? queryConfig.storageModelSelect
                : "*";
        let q = NXUI.Storage().model(table).select(sel);

        if (typeof queryConfig.storageModelQueryHook === "function") {
            const out = queryConfig.storageModelQueryHook(q);
            if (out) q = out;
        }

        if (searchKeyword && searchFields.length > 0) {
            const kw = `%${String(searchKeyword)}%`;
            const kwEscaped = q.escapeValue(kw);
            if (searchFields.length === 1) {
                q.where(searchFields[0], "LIKE", kw);
            } else {
                /** Satu grup OR dalam kurung — wajib bila ada kondisi dasar (storage.query), supaya tidak jadi `A AND b OR c`. */
                const inner = searchFields.map((f) => `${f} LIKE ${kwEscaped}`).join(" OR ");
                q.whereConditions.push([null, null, inner, "AND", "RAW"]);
            }
        }
        if (filterValue && filterField) {
            q.where(filterField, "=", filterValue);
        }
        if (sortBy) {
            q.orderBy(sortBy, sortOrder || "DESC");
        }

        const countQ = q.clone();
        countQ.limitValue = null;
        countQ.offsetValue = null;
        countQ.orderByFields = [];
        const countRes = await countQ.count();
        const totalCount = normalizeStorageModelCount(countRes);

        const dataQ = q.clone();
        dataQ.limit(limit);
        dataQ.offset(offset);
        const dataRes = await dataQ.get();
        const response = normalizeStorageModelRows(dataRes);

        return { totalCount, response };
    } catch (e) {
        console.warn("storageModelStorageData", e);
        return { totalCount: 0, response: [] };
    }
}

/**
 * Dynamic StorageData function - dapat digunakan sebagai dataLoader default
 */
export async function StorageData(limit=10, offset=0, searchKeyword='', searchFields=[], filterValue='', filterField='', sortBy='id', sortOrder='DESC', queryConfig={}) {
    // Config harus disediakan dari caller
    if (!queryConfig.alias || !queryConfig.operasi) {
        throw new Error('StorageData requires complete queryConfig with alias and operasi');
    }
    // console.log('filterField:', filterField);
    // Clone config untuk tidak mengubah original
    const app = {
        "alias": [...queryConfig.alias],
        "aliasNames": [...queryConfig.aliasNames],
        "tabelName": [...queryConfig.tabelName],
        "where": queryConfig.where || false,
        "group": queryConfig.group || false,
        "order": queryConfig.order || false, // Will be overridden if sorting needed
        "operasi": { ...queryConfig.operasi },
        "limit": limit,
        "offset": offset,
        "access": queryConfig.access || "public",
        "subquery": queryConfig.subquery || "",
        "subnested": queryConfig.subnested || ""
    };
    
    // Set dynamic order jika diperlukan
    if (sortBy && sortOrder) {
        // Ambil table alias dari operasi pertama
        const firstTable = Object.keys(app.operasi)[0];
        app.order = `${firstTable}.${sortBy} ${sortOrder.toUpperCase()}`;
    }

    // Build WHERE conditions
    const conditions = [];
    
    // Tambahkan kondisi search jika ada keyword
    if (searchKeyword && searchFields.length > 0) {
        const searchConditions = searchFields.map(field => {
            // Cari field di alias array
            const aliasEntry = queryConfig.alias.find(alias => alias.includes(`AS ${field}`));
            let column = field; // fallback
            
            if (aliasEntry) {
                // Extract column name dari alias (contoh: "user.nama AS nama" -> "user.nama")
                const columnMatch = aliasEntry.match(/^(.+?)\s+AS\s+/i);
                if (columnMatch) {
                    column = columnMatch[1];
                }
            }
            
            return `${column} LIKE '%${searchKeyword}%'`;
        }).join(' OR ');
        
        conditions.push(`(${searchConditions})`);
    }
    
    // Tambahkan kondisi filter jika ada
    if (filterValue && filterField) {
        // Sama seperti search, gunakan alias array untuk mencari column name yang benar
        const aliasEntry = queryConfig.alias.find(alias => alias.includes(`AS ${filterField}`));
        let column = filterField; // fallback
        
        if (aliasEntry) {
            const columnMatch = aliasEntry.match(/^(.+?)\s+AS\s+/i);
            if (columnMatch) {
                column = columnMatch[1];
            }
        }
        
        const filterCondition = `${column} = '${filterValue}'`;
        conditions.push(filterCondition);
    }
    
    // Set WHERE clause - gabungkan dengan existing WHERE dari config
    let finalWhere = [];
    
    // Tambahkan existing WHERE dari config jika ada
    if (queryConfig.where && queryConfig.where !== false) {
        finalWhere.push(`(${queryConfig.where})`);
    }
    
    // Tambahkan search/filter conditions
    if (conditions.length > 0) {
        finalWhere.push(conditions.join(' AND '));
    }
    
    // Set final WHERE clause
    if (finalWhere.length > 0) {
        app.where = finalWhere.join(' AND ');
    }



        try {
            console.log('app:', app);
     
          const Tabel = await NXUI.Storage().models("Office").executeOperation(app);
          const dataTabel = Tabel.data;

           
            NXUI.AppFederated={
              totalCount: dataTabel.totalCount  || 0, 
              response: dataTabel.response ||  [], 
            };
          
        return {
            totalCount: dataTabel.totalCount  || 0, 
            response: dataTabel.response ||  [], 
        };
    } catch (error) {
        return {
            totalCount: 0,
            response: []
        };
    }
}