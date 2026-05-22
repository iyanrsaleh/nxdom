/**
 * NexaDropdown - Modular Dropdown Component
 * Handles dropdown menu functionality with multiple instances support
 */
export class NexaDropdown {
  constructor(options = {}) {
    this.options = {
      // Selector untuk trigger element (button/link yang membuka dropdown)
      triggerSelector: options.triggerSelector || null,
      // Selector untuk menu dropdown
      menuSelector: options.menuSelector || null,
      // ID trigger element (alternatif dari selector)
      triggerId: options.triggerId || null,
      // ID menu element (alternatif dari selector)
      menuId: options.menuId || null,
      // Class untuk show state (default: 'show')
      showClass: options.showClass || 'show',
      // Close other dropdowns when opening this one
      closeOthers: options.closeOthers !== false, // default: true
      // Close on outside click
      closeOnOutsideClick: options.closeOnOutsideClick !== false, // default: true
      // Close on Escape key
      closeOnEscape: options.closeOnEscape !== false, // default: true
      // Auto initialize on DOM ready
      autoInit: options.autoInit !== false, // default: true
      // Callback when dropdown opens
      onOpen: options.onOpen || null,
      // Callback when dropdown closes
      onClose: options.onClose || null,
    };

    this.trigger = null;
    this.menu = null;
    this.isOpen = false;
    this.clickOutsideHandler = null;
    this.escapeKeyHandler = null;
    
    // Simpan instance ke array instances untuk akses global
    if (!NexaDropdown.instances) {
      NexaDropdown.instances = [];
    }
    NexaDropdown.instances.push(this);

    // Initialize if autoInit is true
    if (this.options.autoInit) {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.init());
      } else {
        this.init();
      }
    }
  }

  /**
   * Initialize dropdown
   */
  init() {
    // Get trigger element
    if (this.options.triggerId) {
      this.trigger = document.getElementById(this.options.triggerId);
    } else if (this.options.triggerSelector) {
      this.trigger = document.querySelector(this.options.triggerSelector);
    }

    // Get menu element
    if (this.options.menuId) {
      this.menu = document.getElementById(this.options.menuId);
    } else if (this.options.menuSelector) {
      this.menu = document.querySelector(this.options.menuSelector);
    }

    if (!this.trigger || !this.menu) {
      console.warn('NexaDropdown: Trigger or menu element not found', {
        trigger: this.trigger,
        menu: this.menu,
        options: this.options
      });
      return;
    }

    // Attach click event to trigger dengan capture phase untuk memastikan dipanggil lebih awal
    // Simpan reference handler untuk bisa di-remove nanti
    this._triggerClickHandler = (e) => this.handleTriggerClick(e);
    this.trigger.addEventListener('click', this._triggerClickHandler, true);

    // Setup outside click handler
    if (this.options.closeOnOutsideClick) {
      this.setupOutsideClickHandler();
    }

    // Setup Escape key handler
    if (this.options.closeOnEscape) {
      this.setupEscapeKeyHandler();
    }
  }

  /**
   * Handle trigger click
   */
  handleTriggerClick(e) {
    e.preventDefault();
    e.stopPropagation();
    this.toggle();
  }

  /**
   * Toggle dropdown
   */
  toggle() {
    // Sync state dengan DOM sebelum toggle (untuk handle kasus DOM dimanipulasi langsung)
    this.syncState();
    
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  /**
   * Open dropdown
   */
  open() {
    if (!this.menu) return;

    // Close other dropdowns if configured
    if (this.options.closeOthers) {
      NexaDropdown.closeAll(this);
    }

    this.menu.classList.add(this.options.showClass);
    this.isOpen = true;

    // Call onOpen callback
    if (this.options.onOpen && typeof this.options.onOpen === 'function') {
      this.options.onOpen(this);
    }
  }

  /**
   * Close dropdown
   */
  close() {
    if (!this.menu) return;

    this.menu.classList.remove(this.options.showClass);
    this.isOpen = false;

    // Call onClose callback
    if (this.options.onClose && typeof this.options.onClose === 'function') {
      this.options.onClose(this);
    }
  }

  /**
   * Setup outside click handler
   */
  setupOutsideClickHandler() {
    if (this.clickOutsideHandler) return;

    this.clickOutsideHandler = (e) => {
      if (!this.isOpen) return;

      // Check if click is outside both trigger and menu
      if (
        this.trigger &&
        this.menu &&
        !this.trigger.contains(e.target) &&
        !this.menu.contains(e.target)
      ) {
        this.close();
      }
    };

    document.addEventListener('click', this.clickOutsideHandler);
  }

  /**
   * Setup Escape key handler
   */
  setupEscapeKeyHandler() {
    if (this.escapeKeyHandler) return;

    this.escapeKeyHandler = (e) => {
      if (e.key === 'Escape' && this.isOpen) {
        this.close();
      }
    };

    document.addEventListener('keydown', this.escapeKeyHandler);
  }

  /**
   * Destroy dropdown instance
   */
  destroy() {
    if (this.clickOutsideHandler) {
      document.removeEventListener('click', this.clickOutsideHandler);
      this.clickOutsideHandler = null;
    }

    if (this.escapeKeyHandler) {
      document.removeEventListener('keydown', this.escapeKeyHandler);
      this.escapeKeyHandler = null;
    }

    if (this.trigger && this._triggerClickHandler) {
      this.trigger.removeEventListener('click', this._triggerClickHandler, true);
    }

    this.close();
    this.trigger = null;
    this.menu = null;
    
    // Hapus dari instances array
    if (NexaDropdown.instances) {
      const index = NexaDropdown.instances.indexOf(this);
      if (index > -1) {
        NexaDropdown.instances.splice(index, 1);
      }
    }
  }
  
  /**
   * Sync state dengan DOM (cek apakah menu memiliki class show)
   * Berguna untuk sinkronisasi state setelah manipulasi DOM langsung
   */
  syncState() {
    if (this.menu) {
      const hasShowClass = this.menu.classList.contains(this.options.showClass);
      if (hasShowClass !== this.isOpen) {
        this.isOpen = hasShowClass;
      }
    }
  }
  
  /**
   * Static method: Get instance berdasarkan menu element atau ID
   */
  static getInstanceByMenu(menuElementOrId) {
    if (!NexaDropdown.instances) return null;
    
    const menu = typeof menuElementOrId === 'string' 
      ? document.getElementById(menuElementOrId)
      : menuElementOrId;
    
    if (!menu) return null;
    
    return NexaDropdown.instances.find(instance => instance.menu === menu);
  }

  /**
   * Static method: Initialize multiple dropdowns from configuration
   */
  static init(configs) {
    const instances = [];

    if (Array.isArray(configs)) {
      configs.forEach((config) => {
        const instance = new NexaDropdown(config);
        instances.push(instance);
      });
    } else if (typeof configs === 'object') {
      const instance = new NexaDropdown(configs);
      instances.push(instance);
    }

    return instances.length === 1 ? instances[0] : instances;
  }

  /**
   * Static method: Close all dropdown instances
   */
  static closeAll(excludeInstance = null) {
    // Get all elements with show class
    const allMenus = document.querySelectorAll('[class*="dropdown-menu"], [class*="nav-dropdown-menu"]');
    
    allMenus.forEach((menu) => {
      // Check if menu has show class
      if (menu.classList.contains('show')) {
        menu.classList.remove('show');
      }
    });

    // Also close any instance that's not excluded
    if (NexaDropdown.instances) {
      NexaDropdown.instances.forEach((instance) => {
        if (instance !== excludeInstance && instance.isOpen) {
          instance.close();
        }
      });
    }
  }

  /**
   * Static method: Auto-initialize dropdowns from data attributes
   */
  static autoInit(container = document) {
    const instances = [];

    // Find all elements with data-dropdown-trigger attribute
    const triggers = container.querySelectorAll('[data-dropdown-trigger]');

    triggers.forEach((trigger) => {
      const menuId = trigger.getAttribute('data-dropdown-trigger');
      const menu = document.getElementById(menuId);

      if (menu) {
        const instance = new NexaDropdown({
          triggerId: trigger.id || null,
          menuId: menuId,
          autoInit: false, // We're initializing manually
        });
        instance.init();
        instances.push(instance);
      }
    });

    return instances;
  }
}

// Store instances for static methods
NexaDropdown.instances = [];

// Auto-initialize on DOM ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      NexaDropdown.autoInit();
    });
  } else {
    NexaDropdown.autoInit();
  }
}

// Export default
export default NexaDropdown;

