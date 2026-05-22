
/**
 * NexaSidebar - Collapsible Sidebar Menu Class
 * 
 * Class untuk membuat sidebar menu yang dapat di-collapse/expand
 * dengan struktur data yang fleksibel. Mendukung auto-detect active item,
 * persist state, dan custom link generation.
 * 
 * @example
 * // Basic usage dengan data dari docs.js
 * import { NexaSidebar } from './Sidebar/NexaSidebar.js';
 * import docs from './Sidebar/docs.js';
 * 
 * const sidebar = new NexaSidebar({
 *   container: '.nx-sidebar', // atau document.querySelector('.nx-sidebar')
 *   data: docs,
 *   basePath: '/docs',
 *   activeClass: 'active-grid',
 *   expandedByDefault: ['perkenalan']
 * });
 * 
 * sidebar.init();
 * 
 * @example
 * // Custom link generator
 * const sidebar = new NexaSidebar({
 *   container: '.nx-sidebar',
 *   data: docs,
 *   basePath: '/docs',
 *   linkGenerator: (item, basePath) => {
 *     return `${basePath}/${item.slug}`;
 *   },
 *   categoryFormatter: (key) => {
 *     return key.charAt(0).toUpperCase() + key.slice(1);
 *   }
 * });
 * 
 * @example
 * // Update path saat route berubah (untuk SPA)
 * sidebar.updatePath('/docs/fundamental/pengenalan-framework');
 * 
 * @example
 * // Manual control
 * sidebar.expandAll();
 * sidebar.collapseAll();
 * sidebar.toggleSection('perkenalan');
 */
export class NexaSidebar {
  /**
   * @param {Object} options - Konfigurasi sidebar
   * @param {HTMLElement|string} options.container - Container element atau selector
   * @param {Object} options.data - Data struktur menu (format: { category: [{name, slug}, ...] })
   * @param {string} [options.basePath='/docs'] - Base path untuk link
   * @param {string} [options.activeClass='active-grid'] - Class untuk item aktif
   * @param {Array<string>} [options.expandedByDefault=[]] - Kategori yang expanded by default
   * @param {Function} [options.linkGenerator] - Custom function untuk generate link (item, basePath) => string
   * @param {Function} [options.categoryFormatter] - Custom function untuk format nama kategori (key) => string
   * @param {boolean} [options.persistState=true] - Simpan state expanded/collapsed ke localStorage
   * @param {string} [options.storageKey='nexa-sidebar-state'] - Key untuk localStorage
   * @param {boolean} [options.autoDetectActive=true] - Auto detect active item dari URL
   */
  constructor(options = {}) {
    // Validasi required options
    if (!options.container) {
      throw new Error('NexaSidebar: container is required');
    }
    if (!options.data) {
      throw new Error('NexaSidebar: data is required');
    }

    // Set container
    this.container = typeof options.container === 'string' 
      ? document.querySelector(options.container) 
      : options.container;

    if (!this.container) {
      throw new Error('NexaSidebar: container element not found');
    }

    // Set options dengan defaults
    this.data = options.data;
    this.basePath = options.basePath || '/docs';
    this.activeClass = options.activeClass || 'active-grid';
    this.expandedByDefault = options.expandedByDefault || [];
    this.linkGenerator = options.linkGenerator || this.defaultLinkGenerator.bind(this);
    this.categoryFormatter = options.categoryFormatter || this.defaultCategoryFormatter.bind(this);
    this.persistState = options.persistState !== false;
    this.storageKey = options.storageKey || 'nexa-sidebar-state';
    this.autoDetectActive = options.autoDetectActive !== false;

    // State management
    this.expandedSections = new Set(this.expandedByDefault);
    this.activeItem = null;
    this.currentPath = window.location.pathname;

    // Load persisted state
    if (this.persistState) {
      this.loadState();
    }

    // Auto expand section yang mengandung active item
    if (this.autoDetectActive) {
      this.detectActiveItem();
    }
  }

  /**
   * Default link generator
   * @param {Object} item - Item data dengan name dan slug
   * @param {string} basePath - Base path
   * @returns {string} Generated link
   */
  defaultLinkGenerator(item, basePath) {
    const relativePath = `${basePath}/${item.slug}`;
    
    // Tambahkan window.NEXA?.url jika tersedia dan link belum full URL
    if (typeof window !== 'undefined' && window.NEXA?.url && !relativePath.startsWith('http://') && !relativePath.startsWith('https://')) {
      // Pastikan basePath tidak duplikat dengan NEXA.url
      const baseUrl = window.NEXA.url.replace(/\/$/, ''); // Remove trailing slash
      const cleanPath = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
      return `${baseUrl}${cleanPath}`;
    }
    
    return relativePath;
  }

  /**
   * Default category formatter
   * @param {string} key - Category key
   * @returns {string} Formatted category name
   */
  defaultCategoryFormatter(key) {
    // Capitalize first letter and replace underscores with spaces
    return key
      .replace(/_/g, ' ')
      .replace(/\b\w/g, l => l.toUpperCase());
  }

  /**
   * Detect active item dari current URL
   */
  detectActiveItem() {
    const currentPath = this.currentPath.toLowerCase();
    
    // Helper function untuk extract pathname dari full URL atau relative path
    const getPathname = (url) => {
      try {
        // Jika sudah full URL, extract pathname
        if (url.startsWith('http://') || url.startsWith('https://')) {
          const urlObj = new URL(url);
          return urlObj.pathname.toLowerCase();
        }
        // Jika relative path, return as is
        return url.toLowerCase();
      } catch (e) {
        // Jika error parsing, return as is
        return url.toLowerCase();
      }
    };
    
    // Cari item yang match dengan current path
    Object.keys(this.data).forEach(category => {
      const items = this.data[category];
      items.forEach(item => {
        const itemLink = this.linkGenerator(item, this.basePath);
        const itemPath = getPathname(itemLink);
        
        // Check exact match atau jika current path contains item path
        // Juga check jika slug match dengan bagian akhir dari path
        const slug = item.slug.toLowerCase();
        const pathEndsWithSlug = currentPath.endsWith(slug) || currentPath.includes(`/${slug}`);
        const exactMatch = currentPath === itemPath || currentPath === itemPath + '/';
        
        if (exactMatch || pathEndsWithSlug) {
          this.activeItem = { category, item };
          // Auto expand section yang mengandung active item
          this.expandedSections.add(category);
        }
      });
    });
  }

  /**
   * Load state dari localStorage
   */
  loadState() {
    try {
      const saved = localStorage.getItem(this.storageKey);
      if (saved) {
        const state = JSON.parse(saved);
        if (Array.isArray(state.expandedSections)) {
          // Merge dengan expandedByDefault
          this.expandedSections = new Set([
            ...this.expandedByDefault,
            ...state.expandedSections
          ]);
        }
      }
    } catch (e) {
      console.warn('NexaSidebar: Failed to load state from localStorage', e);
    }
  }

  /**
   * Save state ke localStorage
   */
  saveState() {
    if (!this.persistState) return;
    
    try {
      const state = {
        expandedSections: Array.from(this.expandedSections)
      };
      localStorage.setItem(this.storageKey, JSON.stringify(state));
    } catch (e) {
      console.warn('NexaSidebar: Failed to save state to localStorage', e);
    }
  }

  /**
   * Toggle expand/collapse section
   * @param {string} category - Category key
   */
  toggleSection(category) {
    if (this.expandedSections.has(category)) {
      this.expandedSections.delete(category);
    } else {
      this.expandedSections.add(category);
    }
    
    this.saveState();
    this.render();
  }

  /**
   * Set active item
   * @param {string} category - Category key
   * @param {Object} item - Item object
   */
  setActiveItem(category, item) {
    this.activeItem = { category, item };
    // Auto expand section
    this.expandedSections.add(category);
    this.saveState();
    this.render();
  }

  /**
   * Render sidebar menu
   */
  render() {
    if (!this.container) return;

    const html = this.generateHTML();
    this.container.innerHTML = html;

    // Attach event listeners
    this.attachEventListeners();
  }

  /**
   * Generate HTML untuk sidebar
   * @returns {string} HTML string
   */
  generateHTML() {
    let html = '';

    Object.keys(this.data).forEach(category => {
      const items = this.data[category];
      const isExpanded = this.expandedSections.has(category);
      const categoryName = this.categoryFormatter(category);

      // Check jika ada items
      if (!items || items.length === 0) {
        // Jika tidak ada items, render sebagai simple header
        html += `
          <div class="nx-sidebar-section">
            <h3>${this.escapeHtml(categoryName)}</h3>
          </div>
        `;
        return;
      }

      // Render section dengan collapsible functionality
      html += `
        <div class="nx-sidebar-section">
          <button 
            class="section-header-grid" 
            aria-expanded="${isExpanded}"
            data-category="${this.escapeHtml(category)}"
          >
            <span>${this.escapeHtml(categoryName)}</span>
            <span class="material-symbols-outlined arrow-icon-grid">chevron_right</span>
          </button>
          <nav class="nx-sidebar-nav ${isExpanded ? '' : 'collapsed'}">
      `;

      // Render items
      items.forEach(item => {
        const link = this.linkGenerator(item, this.basePath);
        const isActive = this.activeItem && 
                        this.activeItem.category === category && 
                        this.activeItem.item.slug === item.slug;
        const activeClass = isActive ? this.activeClass : '';

        html += `
          <a 
            class="nx-nav-item ${activeClass}" 
            href="${this.escapeHtml(link)}"
            data-category="${this.escapeHtml(category)}"
            data-slug="${this.escapeHtml(item.slug)}"
          >
            ${this.escapeHtml(item.name)}
          </a>
        `;
      });

      html += `
          </nav>
        </div>
      `;
    });

    return html;
  }

  /**
   * Attach event listeners
   */
  attachEventListeners() {
    // Toggle buttons
    const toggleButtons = this.container.querySelectorAll('.section-header-grid');
    toggleButtons.forEach(button => {
      button.addEventListener('click', (e) => {
        const category = button.getAttribute('data-category');
        this.toggleSection(category);
      });
    });

    // Link clicks untuk update active state
    const links = this.container.querySelectorAll('.nx-nav-item');
    links.forEach(link => {
      link.addEventListener('click', (e) => {
        const category = link.getAttribute('data-category');
        const slug = link.getAttribute('data-slug');
        const item = this.data[category]?.find(i => i.slug === slug);
        
        if (item) {
          this.setActiveItem(category, item);
        }
      });
    });
  }

  /**
   * Escape HTML untuk prevent XSS
   * @param {string} text 
   * @returns {string}
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Initialize sidebar
   */
  init() {
    this.render();
    return this;
  }

  /**
   * Update current path dan re-detect active item
   * @param {string} path - New path
   */
  updatePath(path) {
    this.currentPath = path;
    this.activeItem = null;
    if (this.autoDetectActive) {
      this.detectActiveItem();
    }
    this.render();
  }

  /**
   * Update data dan re-render
   * @param {Object} newData - New data structure
   */
  updateData(newData) {
    this.data = newData;
    this.detectActiveItem();
    this.render();
  }

  /**
   * Expand semua sections
   */
  expandAll() {
    Object.keys(this.data).forEach(category => {
      this.expandedSections.add(category);
    });
    this.saveState();
    this.render();
  }

  /**
   * Collapse semua sections
   */
  collapseAll() {
    // Keep only expandedByDefault
    this.expandedSections = new Set(this.expandedByDefault);
    this.saveState();
    this.render();
  }

  /**
   * Destroy instance dan cleanup
   */
  destroy() {
    if (this.container) {
      this.container.innerHTML = '';
    }
    // Clear event listeners jika diperlukan
  }
}

// Export default untuk backward compatibility
export default NexaSidebar;

// Export alias untuk backward compatibility dengan navigasi.html
export const SidebarMenu = NexaSidebar;

/**
 * Global sidebar instance manager
 * Mengelola instance sidebar secara global untuk menghindari inisialisasi ganda
 */
class SidebarManager {
  constructor() {
    this.instance = null;
    this.initOptions = null;
    this.cssLoaded = false; // Track apakah CSS sudah di-load
  }

  /**
   * Load CSS untuk sidebar (hanya sekali)
   */
  async loadCSS() {
    if (this.cssLoaded) return;
    
    try {
      if (typeof NXUI !== 'undefined' && NXUI.NexaStylesheet && NXUI.NexaStylesheet.Dom) {
        await NXUI.NexaStylesheet.Dom(['NexaSidebar.css']);
        this.cssLoaded = true;
      }
    } catch (error) {
      console.warn('NexaSidebar: Failed to load CSS:', error);
    }
  }

  /**
   * Initialize atau get sidebar instance
   * @param {Object} options - Konfigurasi sidebar (sama seperti NexaSidebar constructor)
   * @returns {NexaSidebar|null} Sidebar instance atau null jika container tidak ditemukan
   */
  async init(options) {
    // Load CSS terlebih dahulu (hanya sekali)
    await this.loadCSS();
    
    // Validasi container dulu
    const container = typeof options.container === 'string' 
      ? document.querySelector(options.container) 
      : options.container;

    if (!container) {
      // Container belum ada, tapi jika sudah ada instance dengan options yang sama,
      // tetap return instance (untuk handle container yang di-render ulang)
      if (this.instance && this.optionsMatch(this.initOptions, options)) {
        // Tunggu container muncul dan re-render
        return this.instance;
      }
      return null;
    }

    // Jika sudah ada instance dan options sama, cek apakah container masih valid
    if (this.instance && this.optionsMatch(this.initOptions, options)) {
      // Cek apakah container instance masih sama atau sudah di-replace
      const currentContainer = typeof options.container === 'string' 
        ? document.querySelector(options.container) 
        : options.container;
      
      // Cek apakah container lama masih connected ke DOM
      const isOldContainerValid = this.instance.container && 
                                  this.instance.container.isConnected &&
                                  document.contains(this.instance.container);
      
      // Jika container masih sama dan valid, update path dan re-render
      if (this.instance.container === currentContainer && currentContainer && isOldContainerValid) {
        if (options.autoDetectActive !== false) {
          this.instance.updatePath(window.location.pathname);
        }
        // Re-render untuk memastikan content ter-update
        this.instance.render();
        return this.instance;
      } else {
        // Container sudah di-replace (di-render ulang), update container reference dan re-render
        // Jangan destroy instance, cukup update container dan re-render
        this.instance.container = currentContainer;
        if (currentContainer) {
          // Re-render dengan container baru
          this.instance.render();
          if (options.autoDetectActive !== false) {
            this.instance.updatePath(window.location.pathname);
          }
        }
        return this.instance;
      }
    }

    try {
      // Destroy existing instance jika ada
      if (this.instance) {
        this.instance.destroy();
      }

      // Create new instance dengan container yang baru
      this.instance = new NexaSidebar(options);
      this.initOptions = { ...options };
      this.instance.init();

      // Setup route change listeners
      this.setupRouteListeners();

      return this.instance;
    } catch (error) {
      console.error('Failed to initialize NexaSidebar:', error);
      return null;
    }
  }

  /**
   * Check jika options match
   */
  optionsMatch(opts1, opts2) {
    if (!opts1 || !opts2) return false;
    return opts1.container === opts2.container && 
           opts1.basePath === opts2.basePath;
  }

  /**
   * Setup route change listeners
   */
  setupRouteListeners() {
    if (!this.instance) return;

    // Remove existing listeners jika ada
    if (this._popstateHandler) {
      window.removeEventListener('popstate', this._popstateHandler);
    }
    if (this._routeChangeHandler) {
      window.removeEventListener('nxui:routeChange', this._routeChangeHandler);
    }

    // Popstate handler (browser back/forward)
    this._popstateHandler = () => {
      if (this.instance) {
        this.instance.updatePath(window.location.pathname);
      }
    };
    window.addEventListener('popstate', this._popstateHandler);

    // Custom route change event handler
    this._routeChangeHandler = (event) => {
      if (this.instance && event.detail && event.detail.path) {
        this.instance.updatePath(event.detail.path);
      }
    };
    window.addEventListener('nxui:routeChange', this._routeChangeHandler);
  }

  /**
   * Get current instance
   */
  getInstance() {
    return this.instance;
  }

  /**
   * Update path
   */
  updatePath(path) {
    if (this.instance) {
      this.instance.updatePath(path);
    }
  }

  /**
   * Destroy instance
   */
  destroy() {
    if (this.instance) {
      this.instance.destroy();
      this.instance = null;
      this.initOptions = null;
    }

    // Remove listeners
    if (this._popstateHandler) {
      window.removeEventListener('popstate', this._popstateHandler);
      this._popstateHandler = null;
    }
    if (this._routeChangeHandler) {
      window.removeEventListener('nxui:routeChange', this._routeChangeHandler);
      this._routeChangeHandler = null;
    }
  }
}

// Global sidebar manager instance
const sidebarManager = new SidebarManager();

/**
 * Utility function untuk inisialisasi sidebar dari route files
 * Function ini akan:
 * 1. Mencoba inisialisasi sidebar dengan retry mechanism
 * 2. Mengelola instance secara global (singleton)
 * 3. Auto-update path saat route berubah
 * 
 * BISA DIGUNAKAN DI ROUTE FILE MANAPUN (beranda.js, guides.js, about.js, dll)
 * 
 * @param {Object} options - Konfigurasi sidebar
 * @param {string|HTMLElement} options.container - Container selector atau element (contoh: '.nx-sidebar')
 * @param {Object} options.data - Data struktur menu (format: { category: [{name, slug}, ...] })
 * @param {string} [options.basePath='/docs'] - Base path untuk link
 * @param {string} [options.activeClass='active-grid'] - Class untuk item aktif
 * @param {Array<string>} [options.expandedByDefault=[]] - Kategori yang expanded by default
 * @param {number} maxRetries - Maximum retry attempts (default: 10)
 * @param {number} retryDelay - Delay antara retry dalam ms (default: 200)
 * @returns {Promise<NexaSidebar|null>} Sidebar instance atau null
 * 
 * @example
 * // Contoh 1: Di beranda.js
 * export async function beranda(page, route) {
 *   route.register(page, async (routeName, container) => {
 *     container.innerHTML = content;
 *     
 *     // Init sidebar setelah content loaded
 *     setTimeout(async () => {
 *       await NXUI.initSidebar({
 *         container: '.nx-sidebar',
 *         data: docs,
 *         basePath: '/docs'
 *       });
 *     }, 100);
 *   });
 * }
 * 
 * @example
 * // Contoh 2: Di guides.js
 * export async function guides(page, route) {
 *   route.register(page, async (routeName, container) => {
 *     container.innerHTML = content;
 *     
 *     // Init sidebar dengan data berbeda
 *     await NXUI.initSidebar({
 *       container: '.nx-sidebar',
 *       data: guidesData,
 *       basePath: '/guides'
 *     });
 *   });
 * }
 * 
 * @example
 * // Contoh 3: Di about.js
 * export async function about(page, route) {
 *   route.register(page, async (routeName, container) => {
 *     container.innerHTML = content;
 *     
 *     // Init sidebar
 *     if (typeof NXUI !== 'undefined' && NXUI.initSidebar) {
 *       await NXUI.initSidebar({
 *         container: '.nx-sidebar',
 *         data: aboutData,
 *         basePath: '/about'
 *       });
 *     }
 *   });
 * }
 */
export async function initSidebar(options, maxRetries = 10, retryDelay = 200) {
  // Load CSS terlebih dahulu
  await sidebarManager.loadCSS();
  
  // Coba init langsung
  let instance = await sidebarManager.init(options);
  
  if (instance) {
    return instance;
  }

  // Jika gagal, retry dengan delay
  for (let i = 0; i < maxRetries; i++) {
    await new Promise(resolve => setTimeout(resolve, retryDelay));
    instance = await sidebarManager.init(options);
    
    if (instance) {
      return instance;
    }
  }

  console.warn('NexaSidebar: Failed to initialize after', maxRetries, 'retries');
  return null;
}

/**
 * Get current sidebar instance
 */
export function getSidebarInstance() {
  return sidebarManager.getInstance();
}

/**
 * Update sidebar path manually
 */
export function updateSidebarPath(path) {
  sidebarManager.updatePath(path);
}

