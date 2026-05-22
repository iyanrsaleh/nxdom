import { Validation } from "../Form/NexaValidation.js";
import { NexaFloating } from "../Form/NexaFloating.js";
import { ensureFormStylesheet } from "../Form/NexaForm.js";
import { NexaWizard } from "../Form/NexaWizard.js";
/**
 * NexaModal - Advanced Modal System with Persistence
 * 
 * PERSISTENCE BEHAVIOR:
 * =====================
 * 
 * 1. Minimized Modals (✅ ENABLED by default):
 *    - When user minimizes a modal, it's saved to IndexDB
 *    - After page refresh, minimized modal reappears in taskbar
 *    - User can restore it by clicking the taskbar item
 *    - This is intentional user action = good UX
 * 
 * 2. Active Modals (❌ DISABLED by default):
 *    - When user refreshes page, active modals are CLOSED
 *    - This is more intuitive - users expect refresh to clear state
 *    - Can be enabled via: setPersistActiveModals(true)
 *    - Only enable if you have specific use case requiring it
 * 
 * CONFIGURATION:
 * ==============
 * persistMinimized: true  → Minimize persistence (recommended)
 * persistActive: false    → Active modal persistence (NOT recommended)
 * 
 * USAGE:
 * ======
 * // Enable active modal persistence (not recommended)
 * nexaModal.setPersistActiveModals(true);
 * 
 * // Disable active modal persistence (default)
 * nexaModal.setPersistActiveModals(false);
 */

const _MODAL_SVG_NS = "http://www.w3.org/2000/svg";

/** SVG untuk taskbar minimized + tombol close (tanpa Material Symbols) */
function createModalSvgIcon(kind, size = 18) {
  const svg = document.createElementNS(_MODAL_SVG_NS, "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("width", String(size));
  svg.setAttribute("height", String(size));
  svg.setAttribute("aria-hidden", "true");
  svg.classList.add("nx-modal-icon-svg");
  svg.style.display = "block";
  svg.style.fill = "none";
  svg.style.stroke = "currentColor";
  svg.style.strokeWidth = "2";
  svg.style.strokeLinecap = "round";
  svg.style.strokeLinejoin = "round";
  if (kind === "minimize") {
    const line = document.createElementNS(_MODAL_SVG_NS, "line");
    line.setAttribute("x1", "5");
    line.setAttribute("y1", "12");
    line.setAttribute("x2", "19");
    line.setAttribute("y2", "12");
    svg.appendChild(line);
  } else {
    const path = document.createElementNS(_MODAL_SVG_NS, "path");
    path.setAttribute("d", "M18 6L6 18M6 6l12 12");
    svg.appendChild(path);
  }
  return svg;
}

class NexaModal {
  constructor(options = {}) {
    // SEDERHANA: Hanya opsi yang diperlukan untuk membuka/menutup modal
    this.options = {
      animationDuration: 200,
      closeOnEscape: true,
      closeOnBackdrop: true,
      preventBodyScroll: true,
      allowMultipleModals: true,
      persistMinimized: true, // Enable IndexDB persistence for minimized modals
      persistActive: false, // ❌ DISABLED: Don't restore active modals after refresh (better UX)
      dbName: "NexaModalDB",
      dbVersion: 1,
      ...options,
    };

    this.activeModals = new Set();
    this.minimizedModals = new Set();
    this.db = null;
    this.isDbReady = false;
    this.init();
  }

  /**
   * Initialize modal system
   */
  init() {
    // Initialize minimize container
    this.initMinimizeContainer();

    // Initialize IndexDB for persistence (NON-BLOCKING)
    if (this.options.persistMinimized) {
      // Add timeout to prevent hanging
      const dbTimeout = setTimeout(() => {
        this.options.persistMinimized = false;
      }, 5000); // 5 second timeout

      // Initialize in background - don't block modal system
      this.initIndexDB()
        .then(() => {
          clearTimeout(dbTimeout);

          // ✅ ALWAYS restore minimized modals (intentional user action)
          this.restoreMinimizedModals();
          
          // ⚠️ Conditionally restore active modals based on config
          if (this.options.persistActive) {
            // Re-enable if needed, but disabled by default for better UX
            this.restoreActiveModals();
            this.setupActiveModalPersistence();
          } else {
            // ❌ Default: Clear active modals (modal closes on refresh)
            this.clearActiveModalsDB();
          }
        })
        .catch((error) => {
          clearTimeout(dbTimeout);

          this.options.persistMinimized = false;
        });
    }

    // Bind event listeners when DOM is ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        this.bindEvents();
      });
    } else {
      this.bindEvents();
    }
  }

  /**
   * Bind global event listeners
   */
  bindEvents() {
    // Initialize all modals
    const modals = document.querySelectorAll(".nx-modal");

    modals.forEach((modal) => {
      // Ensure initial state
      modal.style.display = "none";
      modal.classList.remove("show");

      // Add click handler to close buttons
      const closeButtons = modal.querySelectorAll(
        '.nx-modal-close, [data-dismiss="modal"]'
      );

      closeButtons.forEach((button) => {
        button.addEventListener("click", (e) => {
          e.preventDefault();
          this.close(modal.id);
        });
      });

      // Add click handler to minimize buttons
      const minimizeButtons = modal.querySelectorAll(".nx-modal-minimize");

      minimizeButtons.forEach((button) => {
        button.addEventListener("click", (e) => {
          e.preventDefault();

          this.minimize(modal.id);
        });
      });
    });
  }

  /**
   * Bind event listeners for a specific modal
   * @param {Element} modal - The modal element
   */
  bindModalEvents(modal) {
    // Remove existing listeners first to prevent duplicates
    const closeButtons = modal.querySelectorAll(
      '.nx-modal-close, [data-dismiss="modal"]'
    );
    const minimizeButtons = modal.querySelectorAll(".nx-modal-minimize");

    closeButtons.forEach((button) => {
      // Clone node to remove all existing event listeners
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", (e) => {
        e.preventDefault();

        this.close(modal.id);
      });
    });

    minimizeButtons.forEach((button) => {
      // Clone node to remove all existing event listeners
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", (e) => {
        e.preventDefault();

        this.minimize(modal.id);
      });
    });
  }

  /**
   * Opens a modal dialog
   * @param {string} modalId - The ID of the modal to open
   * @param {string} data - Optional data parameter (for display purposes only)
   * @returns {Promise} Promise that resolves when modal is opened
   */
  open(modalId, data = null) {
    return new Promise((resolve, reject) => {
      const maxRetries = 10;
      const delay = 100;
      let attempts = 0;
      let checkInterval = null;
      
      const proceedWithOpen = (modal) => {
        // Clear interval if it's running
        if (checkInterval) {
          clearInterval(checkInterval);
          checkInterval = null;
        }

      // Parse data if provided
      let parsedData = data;
      if (data && typeof data === "string") {
        try {
          // Try to parse as JSON first
          parsedData = JSON.parse(data);
        } catch (e) {
          // If JSON parse fails, try to decode URL encoding
          try {
            parsedData = decodeURIComponent(data);
          } catch (e2) {
            // If both fail, use original data
            parsedData = data;
          }
        }
      }

      // If modal is already active but closed, remove from active set first
      if (this.activeModals.has(modalId) && modal.style.display === "none") {
        this.activeModals.delete(modalId);
        this.minimizedModals.delete(modalId);
      }

      // Close any other open modals first (only if allowMultipleModals is false)
      if (!this.options.allowMultipleModals) {
        this.closeAll();
      }

      // Set modal display to block first (required for transitions)
      modal.style.display = "block";

      // Set z-index to ensure newer modals appear on top
      if (this.options.allowMultipleModals) {
        const baseZIndex = 1050; // Base z-index for modals
        const currentZIndex = baseZIndex + this.activeModals.size;
        modal.style.zIndex = currentZIndex;
      }

      // Fix accessibility attributes when opening modal
      modal.setAttribute("aria-hidden", "false");
      modal.setAttribute("aria-modal", "true");

      // Manage focus for accessibility
      this.manageFocus(modal, "open");

      // Force reflow to ensure display change is applied
      modal.offsetHeight;

      // Add show class for CSS transitions
      modal.classList.add("show");

      // Add to active modals set
      this.activeModals.add(modalId);

      // SIMPLIFIED: Modal hanya menampilkan konten dari Dom()
      // - Tidak ada rendering
      // - Tidak ada form processing
      // - Tidak ada submit handling
      // - Dom() yang menangani semua konten

      // Ensure event listeners are bound for this specific modal
      this.bindModalEvents(modal);

      // Auto make modal draggable (all modals are draggable by default)
      this.makeDraggable(modalId);

      // Prevent body scroll when modal is open
      if (this.options.preventBodyScroll) {
        document.body.style.overflow = "hidden";
      }

      // Add escape key listener
      if (this.options.closeOnEscape) {
        document.addEventListener("keydown", this.handleEscapeKey.bind(this));
      }

      // Add backdrop click listener
      if (this.options.closeOnBackdrop) {
        modal.addEventListener("click", this.handleBackdropClick.bind(this));
      }

      // Execute callback if defined
      this.executeCallback(modal);

      // Trigger custom event
      const event = new CustomEvent("nexaModalOpened", {
        detail: { modalId, data: parsedData, instance: this },
      });
      document.dispatchEvent(event);

      // Resolve promise after animation with parsed data
      setTimeout(() => {
        resolve(parsedData);
      }, this.options.animationDuration);
      };
      
      // Try to find modal immediately first
      let modal = document.getElementById(modalId);
      
      if (modal) {
        // Modal found immediately, proceed
        proceedWithOpen(modal);
      } else {
        // Modal not found, use retry mechanism
        attempts = 1;
        checkInterval = setInterval(() => {
          modal = document.getElementById(modalId);
          
          if (modal) {
            // Modal found, proceed
            proceedWithOpen(modal);
          } else {
            attempts++;
            if (attempts > maxRetries) {
              // Max retries reached, give up
              clearInterval(checkInterval);
            }
          }
        }, delay);
      }
    });
  }

  /**
   * Closes a specific modal dialog
   * @param {string} modalId - The ID of the modal to close
   * @param {boolean} force - Force close even if minimized
   * @returns {Promise} Promise that resolves when modal is closed
   */
  close(modalId, force = false) {
    return new Promise((resolve, reject) => {
      const modal = document.getElementById(modalId);

      if (!modal) {
        return;
      }

      // If modal is minimized and not forced, remove from minimized state only
      if (this.minimizedModals.has(modalId) && !force) {
        this.minimizedModals.delete(modalId);
        this.removeMinimizedItem(modalId);

        // Remove from IndexDB since modal is no longer minimized
        if (this.options.persistMinimized) {
          this.removeMinimizedFromDB(modalId).catch((error) => {
            console.warn(`🗃️ Failed to remove ${modalId} from IndexDB:`, error);
          });
        }

        // Don't close the modal completely, just remove from minimized state
        resolve({ modalId, action: "removed_from_minimized" });
        return;
      }

      // IMPORTANT: Manage focus FIRST before setting aria-hidden
      // This prevents the "Blocked aria-hidden" warning
      this.manageFocus(modal, "close");

      // Remove show class for CSS transitions
      modal.classList.remove("show");

      // Remove from active modals set
      this.activeModals.delete(modalId);

      // Clean up from IndexDB (user action: close)
      this.removeActiveFromDB(modalId);
      this.removeMinimizedFromDB(modalId);

      // Also remove from minimized set if present
      if (this.minimizedModals.has(modalId)) {
        this.minimizedModals.delete(modalId);
        this.removeMinimizedItem(modalId, false);
      }

      // Wait for animation to complete before hiding
      setTimeout(() => {
        modal.style.display = "none";

        // Reset accessibility attributes AFTER focus is managed
        modal.setAttribute("aria-hidden", "true");
        modal.setAttribute("aria-modal", "false");

        // Reset z-index when closing modal
        if (this.options.allowMultipleModals) {
          modal.style.zIndex = "";
        }

        // Restore body scroll if no other modals are open
        if (this.activeModals.size === 0 && this.options.preventBodyScroll) {
          document.body.style.overflow = "";
        }

        // Remove event listeners
        document.removeEventListener(
          "keydown",
          this.handleEscapeKey.bind(this)
        );
        modal.removeEventListener("click", this.handleBackdropClick.bind(this));

        // SIMPLIFIED: Just clear the modal content - no complex caching to clear
        // Dom() will provide fresh content each time modal opens
        modal.removeAttribute("data-modal-param");
        modal.removeAttribute("data-modal-parsed");

        // Clear #nexa_main content when modal is closed
        const nexaMainElement = document.getElementById("nexa_main");
        if (nexaMainElement) {
          nexaMainElement.innerHTML = "";
        }

        // Reset form data in the modal
        this.resetModalData(modal);

        // CRITICAL: Reset modal state completely for reuse
        this.resetModalState(modal);

        // MODIFIED: Don't remove modal element from DOM
        // Modal element should persist for reuse
        // modal.remove(); // Commented out to prevent "Modal not found" errors

        // Trigger custom event
        const event = new CustomEvent("nexaModalClosed", {
          detail: { modalId, instance: this },
        });
        document.dispatchEvent(event);

        resolve({ modalId });
      }, this.options.animationDuration);
    });
  }

  /**
   * Minimizes a modal dialog
   * @param {string} modalId - The ID of the modal to minimize
   * @returns {Promise} Promise that resolves when modal is minimized
   */
  minimize(modalId) {
    return new Promise((resolve, reject) => {
      const modal = document.getElementById(modalId);

      if (!modal) {
        return;
      }

      if (!this.activeModals.has(modalId)) {
        return;
      }

      // Add minimizing animation class
      modal.classList.add("minimizing");

      // Create minimized representation first
      this.createMinimizedItem(modalId, modal);

      // Wait for animation to complete
      setTimeout(() => {
        // Hide modal but keep in activeModals
        modal.classList.add("minimized");
        modal.classList.remove("show", "minimizing");
        modal.style.display = "none";

        // Add to minimized set
        this.minimizedModals.add(modalId);

        // Manage focus - return to body since modal is minimized
        this.manageFocus(modal, "close");

        // Restore body scroll if no visible modals remain
        const visibleModals = Array.from(this.activeModals).filter(
          (id) => !this.minimizedModals.has(id)
        );
        if (visibleModals.length === 0 && this.options.preventBodyScroll) {
          document.body.style.overflow = "";
        }

        // Save to IndexDB for persistence
        if (this.options.persistMinimized) {
          const modalTitle =
            modal.querySelector(".nx-modal-title")?.textContent ||
            modal.querySelector("h1, h2, h3")?.textContent ||
            modalId;

          // Debug modal config
          const modalConfig = modal._nexaModalConfig;

          if (modalConfig) {
          }

          // Save minimized state to IndexDB (user action: minimize)
          this.saveMinimizedState(modalId, {
            title: modalTitle,
            modal: modal, // Pass modal reference for data extraction
            modalConfig: modalConfig, // Pass modalHTML config if available
          }).catch((error) => {
            console.warn(
              `🗃️ Failed to save minimized state for ${modalId}:`,
              error
            );
          });
        }

        // Trigger custom event
        const event = new CustomEvent("nexaModalMinimized", {
          detail: { modalId, instance: this },
        });
        document.dispatchEvent(event);

        resolve({ modalId, action: "minimized" });
      }, 400); // Match CSS animation duration
    });
  }

  /**
   * Restores a minimized modal dialog
   * @param {string} modalId - The ID of the modal to restore
   * @returns {Promise} Promise that resolves when modal is restored
   */
  restore(modalId) {
    return new Promise((resolve, reject) => {
      const modal = document.getElementById(modalId);

      if (!modal) {
        return;
      }

      if (!this.minimizedModals.has(modalId)) {
        return;
      }

      // Remove from minimized set
      this.minimizedModals.delete(modalId);

      // Remove minimized item with animation
      this.removeMinimizedItem(modalId);

      // Show modal again
      modal.style.display = "block";
      modal.classList.remove("minimized");
      modal.classList.add("restoring");

      // Force reflow
      modal.offsetHeight;

      // Add show class for CSS transitions
      modal.classList.add("show");

      // Restore focus management
      this.manageFocus(modal, "open");

      // Prevent body scroll when modal is restored
      if (this.options.preventBodyScroll) {
        document.body.style.overflow = "hidden";
      }

      // Wait for animation to complete
      setTimeout(() => {
        modal.classList.remove("restoring");

        // Remove from IndexDB since modal is no longer minimized
        if (this.options.persistMinimized) {
          this.removeMinimizedFromDB(modalId).catch((error) => {
            console.warn(`🗃️ Failed to remove ${modalId} from IndexDB:`, error);
          });
        }

        // Trigger custom event
        const event = new CustomEvent("nexaModalRestored", {
          detail: { modalId, instance: this },
        });
        document.dispatchEvent(event);

        resolve({ modalId, action: "restored" });
      }, 400); // Match CSS animation duration
    });
  }

  /**
   * Toggle minimize/restore state of a modal
   * @param {string} modalId - The ID of the modal to toggle
   * @returns {Promise} Promise that resolves when action is complete
   */
  toggleMinimize(modalId) {
    if (this.minimizedModals.has(modalId)) {
      return this.restore(modalId);
    } else if (this.activeModals.has(modalId)) {
      return this.minimize(modalId);
    } else {
      return Promise.reject();
    }
  }

  /**
   * Initialize minimize container
   */
  initMinimizeContainer() {
    if (!document.getElementById("minimized-modals")) {
      const container = document.createElement("div");
      container.id = "minimized-modals";
      container.className = "nx-minimized-container";
      document.body.appendChild(container);
    }
  }

  /**
   * Create minimized item in taskbar
   * @param {string} modalId - The ID of the modal
   * @param {Element} modal - The modal element
   */
  createMinimizedItem(modalId, modal) {
    const container = document.getElementById("minimized-modals");

    if (!container) {
      this.initMinimizeContainer();
      const newContainer = document.getElementById("minimized-modals");
      if (!newContainer) {
        return;
      }
    }

    const title =
      modal.querySelector(
        ".nx-modal-title, .nx-modal-header h5, .nx-modal-header h4, .nx-modal-header h3"
      )?.textContent || modalId;

    // Remove existing minimized item if any
    this.removeMinimizedItem(modalId, false);

    const minimizedItem = document.createElement("div");
    minimizedItem.className = "nx-modal-minimized";
    minimizedItem.dataset.modalId = modalId;
    minimizedItem.title = `Click to restore ${title}`;
    
    // Apply custom minimized background color if set on modal
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
      const minimizedBg = modalElement.getAttribute('data-minimized-bg') || 
                          modalElement.style.getPropertyValue('--minimized-bg-color');
      if (minimizedBg) {
        minimizedItem.setAttribute('data-minimized-bg', minimizedBg);
        minimizedItem.style.setProperty('--minimized-bg-color', minimizedBg);
        // Calculate darker shades for gradient
        const darkerColor = this.darkenColor(minimizedBg, 15);
        const darkestColor = this.darkenColor(minimizedBg, 25);
        minimizedItem.style.setProperty('--minimized-bg-color-dark', darkerColor);
        minimizedItem.style.setProperty('--minimized-bg-color-darker', darkestColor);
      }
    }

    // Create title span with character limit
    const titleSpan = document.createElement("span");
    titleSpan.className = "nx-minimized-title";
    const maxLength = 15; // Limit karakter
    const displayTitle =
      title.length > maxLength ? title.substring(0, maxLength) + "..." : title;
    titleSpan.textContent = displayTitle;
    titleSpan.title = title; // Full title di tooltip

    const minimizeIconWrap = document.createElement("span");
    minimizeIconWrap.className = "nx-minimized-icon";
    minimizeIconWrap.setAttribute("aria-hidden", "true");
    minimizeIconWrap.appendChild(createModalSvgIcon("minimize", 16));

    // Create close button
    const closeButton = document.createElement("button");
    closeButton.className = "nx-minimized-close";
    closeButton.type = "button";
    closeButton.appendChild(createModalSvgIcon("close", 16));
    closeButton.title = `Close ${title}`;

    // Click title to restore
    titleSpan.addEventListener("click", (e) => {
      e.stopPropagation();
      this.restore(modalId);
    });

    // Click close button to close modal completely
    closeButton.addEventListener("click", (e) => {
      e.stopPropagation();
      this.close(modalId, true); // Force close
    });

    // Click container to restore (fallback)
    minimizedItem.addEventListener("click", () => {
      this.restore(modalId);
    });

    minimizedItem.appendChild(minimizeIconWrap);
    minimizedItem.appendChild(titleSpan);
    minimizedItem.appendChild(closeButton);

    const targetContainer =
      container || document.getElementById("minimized-modals");
    if (targetContainer) {
      targetContainer.appendChild(minimizedItem);
    }
  }

  /**
   * Remove minimized item from taskbar
   * @param {string} modalId - The ID of the modal
   * @param {boolean} animate - Whether to animate the removal
   */
  removeMinimizedItem(modalId, animate = true) {
    const container = document.getElementById("minimized-modals");
    const item = container.querySelector(`[data-modal-id="${modalId}"]`);

    if (item) {
      if (animate) {
        item.classList.add("removing");
        setTimeout(() => {
          if (item.parentNode) {
            item.remove();
          }
        }, 300); // Match CSS animation duration
      } else {
        item.remove();
      }
    }
  }

  /**
   * Closes all open modals
   * @returns {Promise} Promise that resolves when all modals are closed
   */
  closeAll() {
    // First, blur any focused elements in all modals to prevent aria-hidden warnings
    this.activeModals.forEach((modalId) => {
      const modal = document.getElementById(modalId);
      if (modal) {
        const focusedElement = document.activeElement;
        if (focusedElement && modal.contains(focusedElement)) {
          focusedElement.blur();
        }
      }
    });

    const closePromises = Array.from(this.activeModals).map((modalId) =>
      this.close(modalId)
    );
    return Promise.all(closePromises).then(() => {
      // Clear #nexa_main content when all modals are closed
      const nexaMainElement = document.getElementById("nexa_main");
      if (nexaMainElement) {
        nexaMainElement.innerHTML = "";
      }

      // MODIFIED: Don't remove modal elements from DOM
      // Modal elements should persist for reuse
      // Reset modal states instead of removing elements
      document.querySelectorAll(".nx-modal").forEach((modal) => {
        if (!modal.classList.contains("show")) {
          modal.style.display = "none";
          modal.classList.remove("minimized", "minimizing", "restoring");
          modal.setAttribute("aria-hidden", "true");
        }
      });
    });
  }

  /**
   * Handles escape key press to close modals
   * @param {KeyboardEvent} event
   */
  handleEscapeKey(event) {
    if (event.key === "Escape" || event.keyCode === 27) {
      const activeModal = document.querySelector(".nx-modal.show");
      if (activeModal) {
        // Blur any focused elements first to prevent aria-hidden warnings
        const focusedElement = document.activeElement;
        if (focusedElement && activeModal.contains(focusedElement)) {
          focusedElement.blur();
        }
        this.close(activeModal.id);
      }
    }
  }

  /**
   * Handles backdrop click to close modals
   * @param {MouseEvent} event
   */
  handleBackdropClick(event) {
    // Only close if clicking the backdrop (not modal content)
    if (event.target === event.currentTarget) {
      const modal = event.target;
      // Blur any focused elements first to prevent aria-hidden warnings
      const focusedElement = document.activeElement;
      if (focusedElement && modal.contains(focusedElement)) {
        focusedElement.blur();
      }
      this.close(modal.id);
    }
  }

  /**
   * REMOVED: handleModalData method - not needed since Dom() handles all rendering
   * Modal just displays content provided by Dom() - no data processing needed
   */

  /**
   * REMOVED: clearModalData method - not needed since we don't cache modal data
   * Dom() provides fresh content each time
   */

  /**
   * Utility function to check if any modal is open
   * @returns {boolean}
   */
  isModalOpen() {
    return this.activeModals.size > 0;
  }

  /**
   * Utility function to get currently open modal
   * @returns {Element|null}
   */
  getCurrentModal() {
    return document.querySelector(".nx-modal.show");
  }

  /**
   * Get all active modal IDs
   * @returns {Array} Array of active modal IDs
   */
  getActiveModals() {
    return Array.from(this.activeModals);
  }

  /**
   * Get all minimized modal IDs
   * @returns {Array} Array of minimized modal IDs
   */
  getMinimizedModals() {
    return Array.from(this.minimizedModals);
  }

  /**
   * Check if a modal is minimized
   * @param {string} modalId - The ID of the modal to check
   * @returns {boolean}
   */
  isMinimized(modalId) {
    return this.minimizedModals.has(modalId);
  }

  /**
   * Get count of minimized modals
   * @returns {number}
   */
  getMinimizedCount() {
    return this.minimizedModals.size;
  }

  /**
   * Get count of visible (non-minimized) modals
   * @returns {number}
   */
  getVisibleModalCount() {
    return this.activeModals.size - this.minimizedModals.size;
  }

  /**
   * Update modal options
   * @param {Object} newOptions - New options to merge
   */
  updateOptions(newOptions) {
    this.options = { ...this.options, ...newOptions };
  }

  /**
   * REMOVED: All FormElement cleanup methods - Modal TIDAK mengurusi form/submit
   * Form processing dan submit bukan tanggung jawab modal
   */

  /**
   * Enable or disable multiple modals
   * @param {boolean} allow - Whether to allow multiple modals
   */
  setAllowMultipleModals(allow) {
    this.options.allowMultipleModals = allow;
  }

  /**
   * Enable or disable active modal persistence
   * @param {boolean} enable - Whether to persist active modals across page refresh
   * 
   * ⚠️ WARNING: Enabling this feature may result in unexpected UX behavior.
   * Users typically expect modals to close when refreshing the page.
   * Only enable if you have a specific use case that requires it.
   */
  setPersistActiveModals(enable) {
    const wasEnabled = this.options.persistActive;
    this.options.persistActive = enable;

    if (enable && !wasEnabled) {
      // Just enabled - setup persistence handlers
      console.warn(
        "⚠️ Active modal persistence enabled. Modals will reopen after page refresh."
      );
      this.setupActiveModalPersistence();
    } else if (!enable && wasEnabled) {
      // Just disabled - clear any saved active modals
      console.log(
        "✅ Active modal persistence disabled. Modals will close on page refresh."
      );
      this.clearActiveModalsDB();
    }
  }

  /**
   * Makes a modal draggable by its header
   * @param {string} modalId - The ID of the modal to make draggable
   */
  makeDraggable(modalId) {
    // Use getElementById first, then querySelector for child elements
    // This handles IDs that start with numbers or contain special characters
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
      console.warn(`Modal element not found for modal: ${modalId}`);
      return;
    }
    
    const modalContent = modalElement.querySelector('.nx-modal-content');
    const modalHeader = modalElement.querySelector('.nx-modal-header');

    if (!modalContent || !modalHeader) {
      console.warn(`Modal content or header not found for modal: ${modalId}`);
      return;
    }

    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;

    // Set cursor style for header
    modalHeader.style.cursor = "move";

    // Add visual indicator
    modalHeader.style.userSelect = "none";
    modalHeader.setAttribute("title", "Drag to move modal");

    function dragStart(e) {
      if (e.type === "touchstart") {
        initialX = e.touches[0].clientX - xOffset;
        initialY = e.touches[0].clientY - yOffset;
      } else {
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
      }

      if (e.target === modalHeader || modalHeader.contains(e.target)) {
        isDragging = true;
        modalContent.style.transition = "none"; // Disable transition during drag
      }
    }

    function dragEnd(e) {
      isDragging = false;
      modalContent.style.transition = ""; // Re-enable transition
    }

    function drag(e) {
      if (isDragging) {
        e.preventDefault();

        if (e.type === "touchmove") {
          currentX = e.touches[0].clientX - initialX;
          currentY = e.touches[0].clientY - initialY;
        } else {
          currentX = e.clientX - initialX;
          currentY = e.clientY - initialY;
        }

        xOffset = currentX;
        yOffset = currentY;

        // Apply transform to modal content
        modalContent.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
      }
    }

    // Mouse events
    modalHeader.addEventListener("mousedown", dragStart, false);
    document.addEventListener("mousemove", drag, false);
    document.addEventListener("mouseup", dragEnd, false);

    // Touch events for mobile
    modalHeader.addEventListener("touchstart", dragStart, { passive: false });
    document.addEventListener("touchmove", drag, { passive: false });
    document.addEventListener("touchend", dragEnd, { passive: true });

    // Store drag state for cleanup
    if (!modalContent.dataset.draggable) {
      modalContent.dataset.draggable = "true";
    }
  }

  /**
   * Resets modal position to center
   * @param {string} modalId - The ID of the modal to reset position
   */
  resetModalPosition(modalId) {
    const modalContent = document.querySelector(
      `#${modalId} .nx-modal-content`
    );
    if (modalContent) {
      modalContent.style.transform = "";
    }
  }

  /**
   * Manage focus for accessibility compliance
   * @param {Element} modal - The modal element
   * @param {string} action - 'open' or 'close'
   */
  manageFocus(modal, action) {
    if (action === "open") {
      // Store the currently focused element
      modal.dataset.previousFocus = document.activeElement?.id || "";

      // Set focus to the modal itself or first focusable element
      const focusableElements = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );

      if (focusableElements.length > 0) {
        // Focus first focusable element
        focusableElements[0].focus();
      } else {
        // Focus the modal itself
        modal.focus();
      }

      // Trap focus within modal
      this.trapFocus(modal);
    } else if (action === "close") {
      // Remove focus trap FIRST
      this.removeFocusTrap(modal);

      // CRITICAL: Remove focus from any element inside the modal
      // This prevents the "Blocked aria-hidden" warning
      const focusedElement = document.activeElement;
      if (focusedElement && modal.contains(focusedElement)) {
        focusedElement.blur();
      }

      // Restore focus to previously focused element
      const previousFocusId = modal.dataset.previousFocus;
      if (previousFocusId) {
        const previousElement = document.getElementById(previousFocusId);
        if (previousElement && previousElement.focus) {
          // Use setTimeout to ensure focus change happens after blur
          setTimeout(() => {
            previousElement.focus();
          }, 0);
        }
      } else {
        // If no previous focus stored, focus document.body as fallback
        setTimeout(() => {
          document.body.focus();
        }, 0);
      }

      // Clear stored focus
      delete modal.dataset.previousFocus;
    }
  }

  /**
   * Trap focus within modal for accessibility
   * @param {Element} modal - The modal element
   */
  trapFocus(modal) {
    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    if (focusableElements.length === 0) return;

    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    const handleFocusTrap = (e) => {
      if (e.key === "Tab") {
        if (e.shiftKey) {
          // Shift + Tab
          if (document.activeElement === firstFocusable) {
            e.preventDefault();
            lastFocusable.focus();
          }
        } else {
          // Tab
          if (document.activeElement === lastFocusable) {
            e.preventDefault();
            firstFocusable.focus();
          }
        }
      }
    };

    // Store the handler for later removal
    modal.dataset.focusTrapHandler = "attached";
    modal.addEventListener("keydown", handleFocusTrap);

    // Store reference to handler for cleanup
    modal._focusTrapHandler = handleFocusTrap;
  }

  /**
   * Remove focus trap from modal
   * @param {Element} modal - The modal element
   */
  removeFocusTrap(modal) {
    if (modal._focusTrapHandler) {
      modal.removeEventListener("keydown", modal._focusTrapHandler);
      delete modal._focusTrapHandler;
      delete modal.dataset.focusTrapHandler;
    }
  }

  /**
   * Reset modal data when closed
   * @param {Element} modal - The modal element
   */
  resetModalData(modal) {
    // Reset all form elements in the modal
    const forms = modal.querySelectorAll("form");
    forms.forEach((form) => {
      if (typeof form.reset === "function") {
        form.reset();
      }
    });

    // Reset individual form controls that might not be in a form
    const inputs = modal.querySelectorAll("input, textarea, select");
    inputs.forEach((input) => {
      if (input.type === "checkbox" || input.type === "radio") {
        input.checked = input.defaultChecked;
      } else {
        input.value = input.defaultValue || "";
      }
    });

    // Clear any dynamic content areas
    const contentAreas = modal.querySelectorAll("[data-dynamic-content]");
    contentAreas.forEach((area) => {
      area.innerHTML = "";
    });
  }

  /**
   * Reset modal state completely for reuse
   * @param {Element} modal - The modal element
   */
  resetModalState(modal) {
    // Remove all modal state classes
    modal.classList.remove("show", "minimized", "minimizing", "restoring");

    // Reset display and visibility
    modal.style.display = "none";
    modal.style.opacity = "";
    modal.style.visibility = "";
    modal.style.zIndex = "";

    // Reset aria attributes
    modal.setAttribute("aria-hidden", "true");
    modal.setAttribute("aria-modal", "false");

    // Reset any transforms from dragging
    const modalContent = modal.querySelector(".nx-modal-content");
    if (modalContent) {
      modalContent.style.transform = "";
      modalContent.style.transition = "";
      delete modalContent.dataset.draggable;
    }

    // Clear any stored data attributes
    modal.removeAttribute("data-modal-param");
    modal.removeAttribute("data-modal-parsed");
    modal.removeAttribute("data-previous-focus");

    // Remove any focus trap handlers
    this.removeFocusTrap(modal);
  }

  /**
   * Execute callback method with data if defined
   * @param {Element} modal - The modal element
   */
  executeCallback(modal) {
    try {
      const callbackMethod = modal.getAttribute("data-callback-method");
      const callbackDataStr = modal.getAttribute("data-callback-data");

      if (callbackMethod && typeof window[callbackMethod] === "function") {
        let callbackData = {};

        // Parse callback data if exists
        if (callbackDataStr) {
          try {
            callbackData = JSON.parse(callbackDataStr);
          } catch (e) {
            console.warn("Failed to parse callback data:", e);
          }
        }

        // Execute callback method with data
        window[callbackMethod](callbackData);
      } else if (callbackMethod) {
        console.warn(
          `Callback method "${callbackMethod}" not found in window object`
        );
      }
    } catch (error) {
    }
  }

  // ========================================
  // IndexDB Persistence Methods
  // ========================================

  /**
   * Initialize IndexDB for modal persistence
   * @returns {Promise} Promise that resolves when DB is ready
   */
  async initIndexDB() {
    return new Promise((resolve, reject) => {
      if (!window.indexedDB) {
        reject();
        return;
      }

      const request = indexedDB.open(
        this.options.dbName,
        this.options.dbVersion
      );

      request.onerror = (event) => {
        reject();
      };

      request.onsuccess = (event) => {
        this.db = event.target.result;
        this.isDbReady = true;

        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        // Don't clear timeout here - upgrade is not complete until onsuccess fires
        const db = event.target.result;

        // Create object store for minimized modals
        if (!db.objectStoreNames.contains("minimizedModals")) {
          const store = db.createObjectStore("minimizedModals", {
            keyPath: "modalId",
          });
          store.createIndex("timestamp", "timestamp", { unique: false });
        }

        // Create object store for active modals
        if (!db.objectStoreNames.contains("activeModals")) {
          const activeStore = db.createObjectStore("activeModals", {
            keyPath: "modalId",
          });
          activeStore.createIndex("timestamp", "timestamp", { unique: false });
        }
      };
    });
  }

  /**
   * Save minimized modal state to IndexDB
   * @param {string} modalId - Modal ID
   * @param {Object} modalData - Modal data to persist
   */
  async saveMinimizedState(modalId, modalData) {
    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      const transaction = this.db.transaction(["minimizedModals"], "readwrite");
      const store = transaction.objectStore("minimizedModals");

      const data = {
        modalId,
        timestamp: Date.now(),
        title: modalData.title || modalId,
        formData: this.extractModalData(modalData.modal),
        position: this.getModalPosition(modalData.modal),
        modalConfig: modalData.modalConfig || null, // Store modalHTML config if available
        // Don't spread modalData as it contains DOM element which can't be cloned
      };

      await store.put(data);
    } catch (error) {
    }
  }

  /**
   * Restore minimized modals from IndexDB
   */
  async restoreMinimizedModals() {
    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      const transaction = this.db.transaction(["minimizedModals"], "readonly");
      const store = transaction.objectStore("minimizedModals");
      const request = store.getAll();

      request.onsuccess = () => {
        const minimizedModals = request.result;

        minimizedModals.forEach((modalData) => {
          this.restoreMinimizedModal(modalData);
        });
      };
    } catch (error) {
    }
  }

  /**
   * Restore a single minimized modal
   * @param {Object} modalData - Saved modal data
   */
  restoreMinimizedModal(modalData) {
    let modal = document.getElementById(modalData.modalId);

    // Check if modal exists but is empty/static (has modalConfig stored)
    if (modal && modalData.modalConfig) {
      const hasConfig = !!modal._nexaModalConfig;
      const bodyChildren =
        modal.querySelector(".nx-modal-body")?.children.length || 0;

      const isStaticModal = !hasConfig && bodyChildren === 0;

      if (isStaticModal) {
        modal.remove(); // Remove static modal
        modal = null; // Reset reference
      }
    }

    if (!modal && modalData.modalConfig) {
      try {
        // Try to recreate modal using stored config
        this.recreateModalFromConfig(modalData.modalId, modalData.modalConfig);
        modal = document.getElementById(modalData.modalId);

        if (modal) {
        }
      } catch (error) {}
    }

    if (!modal) {
      this.removeMinimizedFromDB(modalData.modalId);
      return;
    }

    // Add to minimized set
    this.minimizedModals.add(modalData.modalId);

    // Restore modal state
    modal.classList.add("minimized");
    modal.style.display = "none";

    // Restore form data if exists
    if (modalData.formData) {
      this.restoreModalData(modal, modalData.formData);
    }

    // Create minimized item in taskbar
    this.createMinimizedItem(modalData.modalId, modal);
  }

  /**
   * Extract form data from modal
   * @param {Element} modal - Modal element
   * @returns {Object} Form data
   */
  extractModalData(modal) {
    const formData = {};

    // Extract form inputs
    const inputs = modal.querySelectorAll("input, textarea, select");
    inputs.forEach((input) => {
      if (input.name || input.id) {
        const key = input.name || input.id;
        if (input.type === "checkbox" || input.type === "radio") {
          formData[key] = input.checked;
        } else {
          formData[key] = input.value;
        }
      }
    });

    // Extract dynamic content
    const contentAreas = modal.querySelectorAll("[data-dynamic-content]");
    contentAreas.forEach((area) => {
      if (area.id) {
        formData[`content_${area.id}`] = area.innerHTML;
      }
    });

    return formData;
  }

  /**
   * Restore form data to modal
   * @param {Element} modal - Modal element
   * @param {Object} formData - Form data to restore
   */
  restoreModalData(modal, formData) {
    Object.keys(formData).forEach((key) => {
      if (key.startsWith("content_")) {
        // Restore dynamic content
        const contentId = key.replace("content_", "");
        const contentArea = modal.querySelector(`#${contentId}`);
        if (contentArea) {
          contentArea.innerHTML = formData[key];
        }
      } else {
        // Restore form inputs
        const input = modal.querySelector(`[name="${key}"], #${key}`);
        if (input) {
          if (input.type === "checkbox" || input.type === "radio") {
            input.checked = formData[key];
          } else {
            input.value = formData[key];
          }
        }
      }
    });
  }

  /**
   * Get modal position
   * @param {Element} modal - Modal element
   * @returns {Object} Position data
   */
  getModalPosition(modal) {
    const content = modal.querySelector(".nx-modal-content");
    if (content) {
      return {
        transform: content.style.transform || "",
        top: content.style.top || "",
        left: content.style.left || "",
      };
    }
    return {};
  }

  /**
   * Remove minimized modal from IndexDB
   * @param {string} modalId - Modal ID
   */
  async removeMinimizedFromDB(modalId) {
    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      const transaction = this.db.transaction(["minimizedModals"], "readwrite");
      const store = transaction.objectStore("minimizedModals");
      await store.delete(modalId);
    } catch (error) {}
  }

  /**
   * Recreate modal from stored configuration
   * @param {string} modalId - Modal ID
   * @param {Object} modalConfig - Stored modal configuration
   */
  recreateModalFromConfig(modalId, modalConfig) {
    // Check if we have access to modalHTML function via global scope first
    if (typeof window.modalHTML === "function") {
      //  console.log("🔄 Using global window.modalHTML function");
      window.modalHTML(modalConfig);
    } else if (window.NexaUI) {
      //  console.log("🔄 Trying to access NexaUI.modalHTML function");
      try {
        const ui = window.NexaUI();
        // console.log("🔄 NexaUI instance created:", ui);

        if (ui && typeof ui.modalHTML === "function") {
          // console.log("🔄 Found ui.modalHTML function, calling...");
          ui.modalHTML(modalConfig);
        } else {
          throw new Error("modalHTML method not found in NexaUI instance");
        }
      } catch (error) {
        // console.warn("🗃️ Failed to access NexaUI.modalHTML:", error);
        throw new Error("modalHTML function not available for recreation");
      }
    } else {
      //console.warn("🗃️ Neither window.modalHTML nor window.NexaUI available");
      throw new Error("NexaUI not available for modal recreation");
    }

    // console.log(`🔄 Modal recreation attempt completed for ${modalId}`);
  }

  /**
   * DEPRECATED: Setup active modal persistence - save on page unload
   * 
   * ⚠️ This feature is DISABLED for better UX:
   * - Auto-restoring modals after page refresh is intrusive and unexpected
   * - Users often refresh to clear state, not to preserve it
   * - Only minimized modals should persist (intentional user action)
   * 
   * If you need to re-enable this feature, uncomment the call in init()
   */
  setupActiveModalPersistence() {
    console.warn(
      "⚠️ Active modal persistence is disabled by default. Only minimized modals will persist across page refreshes."
    );

    // Save active modals before page unload
    window.addEventListener("beforeunload", () => {
      this.saveActiveModals();
    });

    // Save active modals on page visibility change (mobile browsers)
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden") {
        this.saveActiveModals();
      }
    });
  }

  /**
   * DEPRECATED: Save currently active modals to IndexDB
   * 
   * ⚠️ This method is kept for backward compatibility but not actively used.
   * Active modal persistence is disabled by default for better UX.
   */
  async saveActiveModals() {
    console.warn(
      "⚠️ saveActiveModals() called but active modal persistence is disabled by default."
    );

    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      // Check if activeModals store exists
      if (!this.db.objectStoreNames.contains("activeModals")) {
        return;
      }

      const transaction = this.db.transaction(["activeModals"], "readwrite");
      const store = transaction.objectStore("activeModals");

      // Clear existing active modals
      await store.clear();

      // Save currently active modals
      for (const modalId of this.activeModals) {
        const modal = document.getElementById(modalId);
        if (modal && !this.minimizedModals.has(modalId)) {
          const modalData = {
            modalId,
            timestamp: Date.now(),
            title:
              modal.querySelector(".nx-modal-title")?.textContent || modalId,
            formData: this.extractModalData(modal),
            position: this.getModalPosition(modal),
            modalConfig: modal._nexaModalConfig || null,
            zIndex: modal.style.zIndex || "",
          };

          await store.put(modalData);
          console.log(`🗃️ Saved active modal state: ${modalId}`);
        }
      }
    } catch (error) {
    }
  }

  /**
   * DEPRECATED: Restore active modals from IndexDB
   * 
   * ⚠️ This method is kept for backward compatibility but not actively used.
   * Active modal persistence is disabled by default for better UX.
   * 
   * Only minimized modals will be restored after page refresh.
   */
  async restoreActiveModals() {
    console.warn(
      "⚠️ restoreActiveModals() called but active modal persistence is disabled by default."
    );

    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      // Check if activeModals store exists
      if (!this.db.objectStoreNames.contains("activeModals")) {
        return;
      }

      const transaction = this.db.transaction(["activeModals"], "readonly");
      const store = transaction.objectStore("activeModals");
      const request = store.getAll();

      request.onsuccess = () => {
        const activeModals = request.result;
        activeModals.forEach((modalData) => {
          // Use setTimeout to ensure DOM is ready
          setTimeout(() => {
            this.restoreActiveModal(modalData);
          }, 100);
        });
      };
    } catch (error) {
    }
  }

  /**
   * Restore a single active modal
   * @param {Object} modalData - Saved modal data
   */
  restoreActiveModal(modalData) {
    console.log(`🗃️ Restoring active modal: ${modalData.modalId}`);

    let modal = document.getElementById(modalData.modalId);

    // Handle dynamic modal recreation
    if (!modal && modalData.modalConfig) {
      console.log(
        `🔄 Recreating active modal ${modalData.modalId} from config...`
      );
      try {
        this.recreateModalFromConfig(modalData.modalId, modalData.modalConfig);
        modal = document.getElementById(modalData.modalId);
      } catch (error) {
        console.warn(
          `🗃️ Failed to recreate active modal ${modalData.modalId}:`,
          error
        );
      }
    }

    if (!modal) {
      console.warn(
        `🗃️ Active modal ${modalData.modalId} not found, removing from IndexDB`
      );
      this.removeActiveFromDB(modalData.modalId);
      return;
    }

    // Open the modal
    this.open(modalData.modalId)
      .then(() => {
        // Restore form data
        if (modalData.formData) {
          this.restoreModalData(modal, modalData.formData);
        }

        // Restore modal position if it was draggable
        if (modalData.position) {
          const modalContent = modal.querySelector(".nx-modal-content");
          if (modalContent && modalData.position.transform) {
            modalContent.style.transform = modalData.position.transform;
          }
          if (modalData.position.top)
            modalContent.style.top = modalData.position.top;
          if (modalData.position.left)
            modalContent.style.left = modalData.position.left;
        }

        // Restore z-index
        if (modalData.zIndex) {
          modal.style.zIndex = modalData.zIndex;
        }

        // Clean up from IndexDB since modal is now active
        this.removeActiveFromDB(modalData.modalId);
      })
      .catch((error) => {
        this.removeActiveFromDB(modalData.modalId);
      });
  }

  /**
   * Remove active modal from IndexDB
   * @param {string} modalId - Modal ID to remove
   */
  async removeActiveFromDB(modalId) {
    // Simple check: if IndexDB not ready, skip silently
    if (!this.options.persistMinimized || !this.isDbReady || !this.db) {
      return;
    }

    try {
      // Check if activeModals store exists
      if (!this.db.objectStoreNames.contains("activeModals")) {
        return;
      }

      const transaction = this.db.transaction(["activeModals"], "readwrite");
      const store = transaction.objectStore("activeModals");
      await store.delete(modalId);
    } catch (error) {
    }
  }

  /**
   * Clear all active modals from IndexDB
   */
  async clearActiveModalsDB() {
    if (!this.isDbReady || !this.db) {
      return;
    }

    try {
      const transaction = this.db.transaction(["activeModals"], "readwrite");
      const store = transaction.objectStore("activeModals");
      await store.clear();
    } catch (error) {}
  }

  /**
   * Clear all minimized modals from IndexDB
   */
  async clearMinimizedDB() {
    if (!this.isDbReady || !this.db) {
      return;
    }

    try {
      const transaction = this.db.transaction(["minimizedModals"], "readwrite");
      const store = transaction.objectStore("minimizedModals");
      await store.clear();
    } catch (error) {
    }
  }

  /**
   * Destroy modal instance and clean up
   */
  destroy() {
    // Close all modals (including minimized ones)
    this.closeAll();

    // Remove all event listeners
    document.removeEventListener("keydown", this.handleEscapeKey.bind(this));

    // Clear active and minimized modals
    this.activeModals.clear();
    this.minimizedModals.clear();

    // Clear IndexDB if persistence is enabled
    if (this.options.persistMinimized && this.isDbReady) {
      this.clearMinimizedDB().catch((error) => {
        console.warn("🗃️ Failed to clear IndexDB on destroy:", error);
      });

      // Close IndexDB connection
      if (this.db) {
        this.db.close();
        this.db = null;
        this.isDbReady = false;
      }
    }

    // Remove minimize container
    const container = document.getElementById("minimized-modals");
    if (container) {
      container.remove();
    }

    // Restore body scroll
    document.body.style.overflow = "";
  }

  /**
   * REMOVED: render method - Modal tidak menangani rendering
   * Rendering adalah tugas Dom() - Modal hanya menampilkan konten
   */

  /**
   * REMOVED: renderWithHtml method - Modal tidak menangani rendering
   */

  /**
   * REMOVED: renderSmooth method - Modal tidak menangani rendering
   */

  /**
   * REMOVED: renderOptimized method - Modal tidak menangani rendering
   */
  renderOptimized(prefix, data, modalId = null) {
    console.warn(
      "DEPRECATED: renderOptimized method removed. Use Dom().assignBlock() instead."
    );
    return;
  }

  /**
   * REMOVED: clearPlaceholders method - Modal tidak menangani rendering
   */

  /**
   * REMOVED: replacePlaceholders method - Modal tidak menangani rendering
   */

  /**
   * REMOVED: All rendering methods - Modal tidak menangani rendering
   * Modal hanya menampilkan konten yang sudah dirender oleh Dom()
   */

  /**
   * Helper function to darken a hex color
   * @param {string} hex - Hex color code (e.g., "#D32626")
   * @param {number} percent - Percentage to darken (0-100)
   * @returns {string} Darkened hex color
   */
  darkenColor(hex, percent) {
    // Remove # if present
    hex = hex.replace('#', '');
    
    // Parse RGB values
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    
    // Darken each component
    const newR = Math.max(0, Math.floor(r * (1 - percent / 100)));
    const newG = Math.max(0, Math.floor(g * (1 - percent / 100)));
    const newB = Math.max(0, Math.floor(b * (1 - percent / 100)));
    
    // Convert back to hex
    const toHex = (n) => {
      const hex = n.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    };
    
    return `#${toHex(newR)}${toHex(newG)}${toHex(newB)}`;
  }
}

// Create default instance with IndexDB persistence enabled
const nexaModal = new NexaModal({
  persistMinimized: true, // ENABLE IndexDB for all usage
  dbName: "NexaModalDB",
  dbVersion: 2,
  allowMultipleModals: true,
  debugMode: false,
});

// Store instance globally for cleanup access
window.nexaModalInstance = nexaModal;

// Store original redModal function
let userredModal = null;

// Export for global use (backward compatibility)
window.redModal = (modalId, data) => {
  // Parse data jika string JSON
  let parsedData = data;
  if (typeof data === "string") {
    try {
      parsedData = JSON.parse(data);
    } catch (e) {
      // Jika bukan JSON, gunakan data asli
      parsedData = data;
    }
  }

  // Panggil user function jika ada
  if (userredModal && typeof userredModal === "function") {
    userredModal(modalId, parsedData);
  }

  // Ganti data dengan yang sudah di-parse
  return nexaModal.open(modalId, parsedData);
};

// Override setter untuk menangkap user function
Object.defineProperty(window, "redModal", {
  get: function () {
    return (modalId, data) => {
      // Parse data jika string JSON
      let parsedData = data;
      if (typeof data === "string") {
        try {
          parsedData = JSON.parse(data);
        } catch (e) {
          parsedData = data;
        }
      }

      // Panggil user function jika ada
      if (userredModal && typeof userredModal === "function") {
        userredModal(modalId, parsedData);
      }

      return nexaModal.open(modalId, parsedData);
    };
  },
  set: function (fn) {
    userredModal = fn;
  },
});

window.closeModal = (modalId) => nexaModal.close(modalId);
window.closeAllModals = () => nexaModal.closeAll();
window.isModalOpen = () => nexaModal.isModalOpen();
window.getCurrentModal = () => nexaModal.getCurrentModal();
window.resetModalPosition = (modalId) => nexaModal.resetModalPosition(modalId);
window.setAllowMultipleModals = (allow) =>
  nexaModal.setAllowMultipleModals(allow);
window.setPersistActiveModals = (enable) =>
  nexaModal.setPersistActiveModals(enable);

// Minimize feature global functions
window.minimizeModal = (modalId) => nexaModal.minimize(modalId);
window.restoreModal = (modalId) => nexaModal.restore(modalId);
window.toggleMinimizeModal = (modalId) => nexaModal.toggleMinimize(modalId);
window.isModalMinimized = (modalId) => nexaModal.isMinimized(modalId);
window.getMinimizedModals = () => nexaModal.getMinimizedModals();
window.getMinimizedCount = () => nexaModal.getMinimizedCount();
window.getVisibleModalCount = () => nexaModal.getVisibleModalCount();

// IndexDB persistence global functions
window.clearMinimizedModalDB = () => nexaModal.clearMinimizedDB();
window.removeMinimizedModalFromDB = (modalId) =>
  nexaModal.removeMinimizedFromDB(modalId);

// Active modal persistence global functions
window.clearActiveModalsDB = () => nexaModal.clearActiveModalsDB();
window.removeActiveModalFromDB = (modalId) =>
  nexaModal.removeActiveFromDB(modalId);
window.saveActiveModals = () => nexaModal.saveActiveModals();
// REMOVED: FormElement cleanup functions - Modal TIDAK mengurusi form/submit
// REMOVED: Filter-related global functions - Modal tidak menangani rendering

// Export class for ES6 modules
window.NexaModal = NexaModal;
window.nexaModal = nexaModal;

// Export for Node.js/CommonJS (if needed)
if (typeof module !== "undefined" && module.exports) {
  module.exports = { NexaModal, nexaModal };
}

// Export for AMD (if needed)
if (typeof define === "function" && define.amd) {
  define([], function () {
    return { NexaModal, nexaModal };
  });
}

// ES6 Module Exports
export { NexaModal, nexaModal };

// Function to auto-collect form data from modal
// getFormBy options: ["id"], ["name"], ["data-key"], ["data-order"]
// Example: data-order='["deskripsi","title","nama"]' will return the actual array ["deskripsi","title","nama"] as value
// Note: When using "data-order", it will collect from ALL elements with data-order (including div, span, etc.)
function collectFormData(modalId, getFormBy = ["id"]) {
  const modal = document.getElementById(modalId);
  if (!modal) {
    console.warn(`Modal with ID "${modalId}" not found for data collection`);
    return {};
  }

  const formData = {};
  const radioGroups = new Set(); // Track radio groups we've processed
  const checkboxGroups = new Set(); // Track checkbox groups we've processed
  const collectionMethod = Array.isArray(getFormBy) ? getFormBy : [getFormBy];

  // Find all form elements in the modal
  let formElements = modal.querySelectorAll("input, select, textarea");

  // If using data-order method, also include all elements with data-order attribute
  if (collectionMethod.includes("data-order")) {
    const dataOrderElements = modal.querySelectorAll("[data-order]");
    // Combine form elements with data-order elements (remove duplicates)
    const allElements = new Set([...formElements, ...dataOrderElements]);
    formElements = Array.from(allElements);
  }

  formElements.forEach((element) => {
    let key = null;

    // Special handling for radio buttons - use name as group key
    if (element.type === "radio" && element.name) {
      // For radio buttons, always use the name as the key for grouping
      key = element.name;

      // Skip if we've already processed this radio group
      if (radioGroups.has(key)) {
        return;
      }

      // Mark this radio group as processed
      radioGroups.add(key);

      // Find the checked radio in this group
      const checkedRadio = modal.querySelector(
        `input[type="radio"][name="${element.name}"]:checked`
      );
      if (checkedRadio) {
        // Only collect the group value - no individual radio states to avoid database confusion
        formData[key] = checkedRadio.value;
      } else {
        // No radio selected in this group
        formData[key] = null;
      }
      return;
    }

    // Special handling for checkbox groups - use name as group key
    if (
      element.type === "checkbox" &&
      (element.name || element.getAttribute("data-original-name")) &&
      element.hasAttribute("value")
    ) {
      key = element.name || element.getAttribute("data-original-name");

      // Skip if we've already processed this checkbox group
      if (checkboxGroups.has(key)) {
        return;
      }

      // Mark this checkbox group as processed
      checkboxGroups.add(key);

      // Find all checked checkboxes in this group
      const groupName =
        element.name || element.getAttribute("data-original-name");
      const checkedCheckboxes = modal.querySelectorAll(
        `input[type="checkbox"][name="${groupName}"]:checked, input[type="checkbox"][data-original-name="${groupName}"]:checked`
      );

      const values = [];
      checkedCheckboxes.forEach((checkbox) => {
        if (checkbox.value && checkbox.value !== "on") {
          values.push(checkbox.value);
        }
      });

      // Set group value as comma-separated string - no individual states to avoid database confusion
      formData[key] = values.length > 0 ? values.join(",") : "";

      return; // Skip normal processing for grouped checkboxes
    }

    // Skip text inputs that are companions to color pickers (e.g., favoriteColorValue)
    if (element.type === "text" && element.id && element.id.endsWith("Value")) {
      const colorPickerId = element.id.replace("Value", "");
      const colorPicker = modal.querySelector(`#${colorPickerId}`);
      if (colorPicker && colorPicker.type === "color") {
        return; // Skip this text input - it's just a companion to color picker
      }
    }

    // Normal key determination for non-radio/non-grouped-checkbox elements
    for (const method of collectionMethod) {
      if (method === "id" && element.id) {
        key = element.id;
        break;
      } else if (method === "name" && element.name) {
        key = element.name;
        break;
      } else if (method === "data-key" && element.dataset.key) {
        key = element.dataset.key;
        break;
      } else if (method === "data-order" && element.dataset.order) {
        // For data-order, use element ID or fallback as key, but value will be the parsed array
        key =
          element.id ||
          element.dataset.order ||
          "data_order_" + Math.random().toString(36).substr(2, 9);
        break;
      }
    }

    // If no key found, use id as fallback
    if (!key && element.id) {
      key = element.id;
    }

    // If still no key, use name as fallback
    if (!key && element.name) {
      key = element.name;
    }

    // Collect value if key exists
    if (key) {
      // Check if this is a form element or non-form element
      const isFormElement = element.matches("input, select, textarea");

      if (isFormElement) {
        // Handle form elements
        if (element.type === "checkbox") {
          // Individual checkbox (not part of a named group)
          if (
            element.hasAttribute("value") &&
            element.value !== "" &&
            element.value !== "on"
          ) {
            formData[key] = element.checked ? element.value : null;
          } else {
            formData[key] = element.checked;
          }
        } else if (element.type === "file") {
          if (element.files.length > 0) {
            if (element.multiple) {
              // Handle multiple files - consistent with NexaValidation.js structure
              const filesArray = Array.from(element.files).map((file) => ({
                name: file.name,
                size: file.size,
                type: file.type,
                lastModified: file.lastModified,
                file: file, // Keep original File object for processing
              }));
              formData[key] = filesArray; // Array of file objects
            } else {
              // Handle single file - consistent with NexaValidation.js structure
              const file = element.files[0];
              formData[key] = {
                name: file.name,
                size: file.size,
                type: file.type,
                lastModified: file.lastModified,
                file: file, // Keep original File object for processing
              };
            }
          } else {
            // No files selected - consistent with NexaValidation.js
            formData[key] = element.multiple ? [] : null;
          }
        } else if (element.type === "color") {
          // For color inputs, collect the current effective value
          const textInput = modal.querySelector(`#${key}Value`);
          if (textInput && textInput.value && textInput.value !== "") {
            // If text input has value, use that (user might have typed hex manually)
            formData[key] = textInput.value;
          } else {
            // Otherwise use color picker value
            formData[key] = element.value;
          }
          // Note: Don't collect textInput.id separately - only collect the main color field
        } else {
          formData[key] = element.value;
        }
      } else {
        // Handle non-form elements (div, span, etc.)
        let elementValue = null;

        // Special handling for data-order elements
        if (element.dataset.order && collectionMethod.includes("data-order")) {
          try {
            // Parse and return the actual array value from data-order
            elementValue = JSON.parse(element.dataset.order);
          } catch (e) {
            // If parsing fails, return as string
            elementValue = element.dataset.order;
          }
        } else {
          // Try to get value from various sources for other elements
          if (element.hasAttribute("value")) {
            // If element has explicit value attribute
            elementValue = element.getAttribute("value");
          } else if (element.dataset.value) {
            // If element has data-value attribute
            elementValue = element.dataset.value;
          } else if (element.textContent && element.textContent.trim() !== "") {
            // If element has text content
            elementValue = element.textContent.trim();
          } else if (element.innerHTML && element.innerHTML.trim() !== "") {
            // If element has HTML content
            elementValue = element.innerHTML.trim();
          }
        }

        formData[key] = elementValue;
      }
    }
  });

  return formData;
}

// Function to setup real-time color sync
function setupColorSync(modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;

  const colorInputs = modal.querySelectorAll('input[type="color"]');
  colorInputs.forEach((colorInput) => {
    if (colorInput.id) {
      const textInput = modal.querySelector(`#${colorInput.id}Value`);
      if (textInput && !colorInput.hasAttribute("data-sync-listener")) {
        // Set initial value
        textInput.value = colorInput.value;

        // Add real-time sync event listener
        colorInput.addEventListener("input", function () {
          textInput.value = this.value;
        });

        // Also sync when text input changes (if user types hex)
        textInput.addEventListener("input", function () {
          const hexRegex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
          if (hexRegex.test(this.value)) {
            colorInput.value = this.value;
          }
        });

        colorInput.setAttribute("data-sync-listener", "true");
      }
    }
  });
}

/**
 * No-op: `modal.css` dimuat lewat `nexa.css` (@import) / `<link>` di HTML.
 * Tetap diekspor agar `NXUI.ensureModalStylesheet()` tidak patah.
 */
export async function ensureModalStylesheet() {
  if (typeof document === "undefined") return;
}

const SVG_NS = "http://www.w3.org/2000/svg";

/** Ikon header modal (SVG), memakai currentColor — tanpa font Material Symbols */
function createModalHeaderIcon(kind) {
  const svg = document.createElementNS(SVG_NS, "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("width", "20");
  svg.setAttribute("height", "20");
  svg.setAttribute("aria-hidden", "true");
  svg.classList.add("nx-modal-icon-svg");
  svg.style.display = "block";
  svg.style.fill = "none";
  svg.style.stroke = "currentColor";
  svg.style.strokeWidth = "2";
  svg.style.strokeLinecap = "round";
  svg.style.strokeLinejoin = "round";

  if (kind === "minimize") {
    const line = document.createElementNS(SVG_NS, "line");
    line.setAttribute("x1", "5");
    line.setAttribute("y1", "12");
    line.setAttribute("x2", "19");
    line.setAttribute("y2", "12");
    svg.appendChild(line);
  } else {
    const path = document.createElementNS(SVG_NS, "path");
    path.setAttribute("d", "M18 6L6 18M6 6l12 12");
    svg.appendChild(path);
  }
  return svg;
}

/**
 * Bangun objek `validasi` untuk NexaValidation dari `storage.form` / `setDataBy.form`
 * (selaras dengan NexaForm: field `condition: true`, `validation` angka).
 * Digabung dengan `onclick.validation` bila ada (onclick menimpa key yang sama).
 */
function buildValidasiFromModalFormBuckets(data) {
  const Buckets = data?.storage?.form ?? data?.setDataBy?.form;
  if (!Buckets || typeof Buckets !== "object") return {};
  const checkedForm = Object.values(Buckets).filter(
    (item) => item && item.condition === true
  );
  const getValidationBy = data.getValidationBy || ["name"];
  const validationByKey = Array.isArray(getValidationBy)
    ? getValidationBy[0]
    : getValidationBy;
  const validasiObject = {};
  checkedForm.forEach((item) => {
    const key = item[validationByKey] || item.name || item.id;
    if (key) validasiObject[key] = Number(item.validation);
    if (item.id && item.id !== key) validasiObject[item.id] = Number(item.validation);
    if (item.name && item.name !== key && item.name !== item.id) {
      validasiObject[item.name] = Number(item.validation);
    }
  });
  return validasiObject;
}

export async function modalHTML(data) {
  // Extract setDataBy parameter for callback usage
  const setDataBy = data.setDataBy || null; // ✅ Support for setDataBy parameter

  const validasiFromBuckets = buildValidasiFromModalFormBuckets(data);
  const onclickValidationObj =
    data.onclick &&
    typeof data.onclick.validation === "object" &&
    data.onclick.validation !== null
      ? data.onclick.validation
      : {};
  const mergedModalValidasi = { ...validasiFromBuckets, ...onclickValidationObj };

  // ✅ Validasi showMinimize parameter
  if (data.showMinimize && !data.minimize) {
    console.warn(
      "showMinimize: true requires minimize: true. Ignoring showMinimize."
    );
    data.showMinimize = false;
  }
  const showMinimize = data.showMinimize || false; // ✅ Support for showMinimize parameter

  // Create modal elements using native JavaScript DOM methods
  const modalId = data.elementById || "myModal";
  // Create main modal container
  const modal = document.createElement("div");
  modal.className = "nx-modal";
  modal.id = modalId;

  // Store modal configuration for IndexDB persistence
  modal._nexaModalConfig = { ...data };
  
  // Set custom minimized background color if provided
  if (data.minimizedBg) {
    modal.style.setProperty('--minimized-bg-color', data.minimizedBg);
    modal.setAttribute('data-minimized-bg', data.minimizedBg);
  }
  
  // Set custom padding top if provided
  if (data.paddingTop) {
    modal.setAttribute('data-padding-top', data.paddingTop);
  }

   if (data.bodyPadding) {
    modal.setAttribute('data-padding-body', data.bodyPadding);
  }
  
  // Create modal dialog
  const modalDialog = document.createElement("div");
  modalDialog.className = "nx-modal-dialog " + data.styleClass;
  
  // Apply padding top to modal dialog if provided
  if (data.paddingTop) {
    modalDialog.style.paddingTop = data.paddingTop;
  }

  // Create modal content
  const modalContent = document.createElement("div");
  modalContent.className = "nx-modal-content";

  // Create modal header
  const modalHeader = document.createElement("div");
  modalHeader.className = "nx-modal-header";

  const modalTitle = document.createElement("h5");
  modalTitle.className = "nx-modal-title";
  modalTitle.textContent = data.label;

  // Create modal controls container
  const modalControls = document.createElement("div");
  modalControls.className = "nx-modal-controls";
  if (data.minimize) {
    // Create minimize button
    const minimizeButton = document.createElement("button");
    minimizeButton.className = "nx-modal-minimize";
    minimizeButton.type = "button";
    minimizeButton.title = "Minimize";
    minimizeButton.onclick = () => {
      // Toggle modal minimized state
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.toggle("minimized");
      }
    };

    minimizeButton.appendChild(createModalHeaderIcon("minimize"));

    // Add minimize button to modal controls
    modalControls.appendChild(minimizeButton);
  }
  // Create close button
  const closeButton = document.createElement("button");
  closeButton.className = "nx-modal-close";
  closeButton.type = "button";
  closeButton.setAttribute("data-dismiss", "modal");
  closeButton.onclick = () => window.closeModal(modalId);

  closeButton.appendChild(createModalHeaderIcon("close"));

  // Add buttons to controls container
  modalControls.appendChild(closeButton);

  modalHeader.appendChild(modalTitle);
  modalHeader.appendChild(modalControls);
  // Create modal body
  const modalBody = document.createElement("div");
  modalBody.className = "nx-modal-body";
  modalBody.id = "body_" + data.elementById;
  if (data.bodyPadding) {
    modalBody.style.padding = data.bodyPadding;
    modalBody.style.overflowY = 'hidden';
  }
  if (data.wizard) {
    await ensureFormStylesheet();
    const wizard = new NexaWizard(data.wizard, {
      mode: data.mode || "insert",
      footer: data.footer !== false,
    });
    modalBody.innerHTML = wizard.html();
    wizard.bindToDom(modalBody);
  } else if (data.floating) {
    const template = new NexaFloating(data.floating, {
      formById: data.floating.id || data.floating.modalid,
      mode: "insert",
    });
    modalBody.innerHTML = template.html();
  } else {
    modalBody.innerHTML = data.content ?? "";
  }
  // Assemble modal content
  modalContent.appendChild(modalHeader);
  modalContent.appendChild(modalBody);
  // Create footer if needed
  if (data.onclick && data.onclick.send && !data.wizard) {
    const modalFooter = document.createElement("div");
    modalFooter.className = "nx-modal-footer";
    modalFooter.id = "footer" + data.elementById;

    // Create custom footer content container (left side)
    const customFooterContainer = document.createElement("div");
    customFooterContainer.className = "nx-footer-custom";

    // Add custom footer content if specified
    if (data.footer) {
      customFooterContainer.innerHTML = data.footer;
    }

    // Create default buttons container (right side)
    const buttonsContainer = document.createElement("div");
    buttonsContainer.className = "nx-footer-buttons";

    const cancelButton = document.createElement("button");
    cancelButton.type = "button";
    cancelButton.className = "btn btn-secondary";
    cancelButton.setAttribute("data-dismiss", "modal");
    cancelButton.textContent = data.onclick.cancel;
    cancelButton.onclick = () => window.closeModal(modalId);

    const saveButton = document.createElement("button");
    saveButton.type = "button";
    saveButton.className = "btn btn-primary";
    saveButton.textContent = data.onclick.title || "Save";

    if (data.onclick.validation || data.getValidationBy) {
      // Setup validation integration
      saveButton.id = "submit-" + data.elementById;

      // Setup NexaValidation with modal form
      // Note: Optional checkbox/switch groups will have their 'name' attributes removed
      // before validation to prevent individual validation
      const enhancedValidation = mergedModalValidasi;

      const validationConfig = {
        formid: `#${modalId}`,
        submitid: `#submit-${data.elementById}`,
        fileInput: true,
        validasi: enhancedValidation,
        getFormBy: data.getFormBy || ["id"],
        getValidationBy: data.getValidationBy || ["id"],
      };

      // Initialize validation and setup validated data collection
      setTimeout(() => {
        // CRITICAL FIX: Handle single-select checkbox groups
        // For single-select checkbox groups, only one needs to be selected for validation
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
          // ✅ Find all checkbox groups with validation rules (these should be single-select)
          const validationKeys = Object.keys(enhancedValidation || {});
          const checkboxGroupsWithValidation = new Set();

          // Check validation rules for checkbox groups
          validationKeys.forEach((fieldName) => {
            const checkboxes = modalElement.querySelectorAll(
              `input[type="checkbox"][name="${fieldName}"]`
            );
            if (checkboxes.length > 1) {
              checkboxGroupsWithValidation.add(fieldName);
            }
          });

          // ✅ Find all single-select checkbox groups (both explicit class and validation-based)
          const singleSelectGroups = modalElement.querySelectorAll(
            'input[type="checkbox"].single-select-checkbox'
          );
          const processedGroups = new Set();

          // Process explicitly marked single-select checkboxes
          singleSelectGroups.forEach((checkbox) => {
            const groupName = checkbox.name;
            if (groupName && !processedGroups.has(groupName)) {
              processedGroups.add(groupName);

              // Mark all checkboxes in this group as single-select for validation
              const groupElements = modalElement.querySelectorAll(
                `input[type="checkbox"][name="${groupName}"]`
              );

              groupElements.forEach((element) => {
                element.classList.add("single-select-checkbox");
                element.setAttribute("data-single-select-group", groupName);
              });
            }
          });

          // ✅ Process checkbox groups found in validation rules
          checkboxGroupsWithValidation.forEach((groupName) => {
            if (!processedGroups.has(groupName)) {
              processedGroups.add(groupName);

              // Mark all checkboxes in this group as single-select for validation
              const groupElements = modalElement.querySelectorAll(
                `input[type="checkbox"][name="${groupName}"]`
              );

              groupElements.forEach((element) => {
                element.classList.add("single-select-checkbox");
                element.setAttribute("data-single-select-group", groupName);
              });
            }
          });

          // ✅ Handle optional checkbox groups (groups that don't require validation)
          const optionalGroups = [
            // Optional groups that don't need validation can be added here:
            // { name: "preferences", description: "User Preferences (optional)" }
            // { name: "notifications", description: "Notification Settings (optional)" }
          ];

          // ✅ Process optional groups dynamically
          optionalGroups.forEach((group) => {
            const groupElements = modalElement.querySelectorAll(
              `input[type="checkbox"][name="${group.name}"]`
            );

            groupElements.forEach((element) => {
              element.setAttribute("data-original-name", element.name); // Backup for collection
              element.removeAttribute("name"); // Remove to prevent validation
              element.classList.add("optional-checkbox-group");
            });
          });
        }

        Validation(validationConfig, (result) => {
          const modalElement = document.getElementById(modalId);
          if (modalElement) {
            const elementsToRestore = modalElement.querySelectorAll(
              "[data-original-name]"
            );

            elementsToRestore.forEach((element) => {
              const originalName = element.getAttribute("data-original-name");
              element.name = originalName;
              element.removeAttribute("data-original-name");
            });
          }

          // Auto-collect form data from modal after validation passes
          const formData = collectFormData(modalId, data.getFormBy);

          // Try multiple ways to access the function
          const functionName = data.onclick.send;
          let targetFunction = null;

          // Try window first (original behavior)
          if (
            window[functionName] &&
            typeof window[functionName] === "function"
          ) {
            targetFunction = window[functionName];
          }
          // Try NXUI global functions
          else if (
            window.NXUI &&
            window.NXUI[functionName] &&
            typeof window.NXUI[functionName] === "function"
          ) {
            targetFunction = window.NXUI[functionName];
          }
          // Try nx global functions
          else if (
            window.nx &&
            window.nx[functionName] &&
            typeof window.nx[functionName] === "function"
          ) {
            targetFunction = window.nx[functionName];
          }
          // Try nx._global for NexaGlobal functions
          else if (
            window.nx &&
            window.nx._global &&
            window.nx._global[functionName] &&
            typeof window.nx._global[functionName] === "function"
          ) {
            targetFunction = window.nx._global[functionName];
          }

          if (targetFunction) {
            targetFunction(modalId, formData, setDataBy);
          }
        });
      }, 200);
    } else {
      // No validation - direct data collection
      saveButton.onclick = () => {
        // Auto-collect form data from modal
        const formData = collectFormData(modalId, data.getFormBy);
        // Try multiple function access patterns for compatibility
        const funcName = data.onclick.send;
        let targetFunction = null;

        // Try nx first (new structure)
        if (window.nx && typeof window.nx[funcName] === "function") {
          targetFunction = window.nx[funcName];
        }
        // Try NXUI
        else if (window.NXUI && typeof window.NXUI[funcName] === "function") {
          targetFunction = window.NXUI[funcName];
        }
        // Try direct window access (legacy)
        else if (typeof window[funcName] === "function") {
          targetFunction = window[funcName];
        }

        if (targetFunction) {
          targetFunction(modalId, formData, setDataBy);
        }
      };
    }

    buttonsContainer.appendChild(cancelButton);
    buttonsContainer.appendChild(saveButton);

    modalFooter.appendChild(customFooterContainer);
    modalFooter.appendChild(buttonsContainer);
    modalContent.appendChild(modalFooter);
  }
  // Assemble complete modal
  modalDialog.appendChild(modalContent);
  modal.appendChild(modalDialog);

  // CRITICAL FIX: Remove existing modal with same ID before adding new one
  // This prevents old data from accumulating in DOM
  const existingModal = document.getElementById(modalId);
  if (existingModal) {
    existingModal.remove();
  }

  // Store callback data if provided
  if (data.callback) {
    const callbackMethod = data.callback.method || "";
    const callbackData = JSON.stringify(data.callback.data || {});
    modal.setAttribute("data-callback-method", callbackMethod);
    modal.setAttribute("data-callback-data", callbackData);
  }

  // Insert modal before #nexa_main when present; otherwise append to body (halaman tanpa anchor)
  const nexaMain = document.getElementById("nexa_main");
  if (nexaMain && nexaMain.parentNode) {
    nexaMain.parentNode.insertBefore(modal, nexaMain);
  } else {
    document.body.appendChild(modal);
  }

  // ✅ TAMBAHKAN LOGIKA SHOWMINIMIZE
  // Langsung minimize modal jika showMinimize = true
  if (showMinimize && data.minimize) {
    setTimeout(() => {
      const modalElement = document.getElementById(modalId);
      if (modalElement) {
        // Tambahkan class minimized
        modalElement.classList.add("minimized");

        // Trigger minimize event untuk konsistensi dengan NexaModal
        if (
          window.nexaModal &&
          typeof window.nexaModal.minimize === "function"
        ) {
          window.nexaModal.minimize(modalId);
        }

        // Update state di NexaModal system jika ada
        if (window.nexaModal && window.nexaModal.minimizedModals) {
          window.nexaModal.minimizedModals.add(modalId);
        }

        // Optional: Trigger custom event
        const minimizeEvent = new CustomEvent("modalMinimized", {
          detail: { modalId: modalId, autoMinimize: true },
        });
        modalElement.dispatchEvent(minimizeEvent);
      }
    }, 100); // Delay untuk memastikan modal sudah render
  }

  // Setup real-time color sync after modal is inserted into DOM
  setTimeout(() => {
    setupColorSync(modalId);
  }, 100);

  // Initialize feather icons after content is injected
}
