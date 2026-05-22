export class NexaLayer {
  constructor(options = {}) {
    this.options = {
      container: ".nx-row",
      item: "> div", // Menggunakan direct child div, bukan class selector
      dragClass: "nx-dragging",
      dropClass: "nx-drop-zone",
      minimize: true, // Default enable minimize
      /** Tampilkan blok `.nx-card-header` (judul + kontrol). Default true. */
      showHeader: true,
      /** Global: tampilkan footer jika baris punya `footer` (string). Per baris: `footer: false` menyembunyikan footer. */
      showFooter: true,
      ...options,
    };
    if (options.header !== undefined) {
      this.options.showHeader = options.header !== false;
    }
    if (options.footer !== undefined) {
      this.options.showFooter = options.footer !== false;
    }

    // Bind methods
    this.minimize = this.minimize.bind(this);
    this.close = this.close.bind(this);

    // Initialize minimize container if not exists
    this.initMinimizeContainer();
  }

  // Initialize minimize container for taskbar-like minimized items
  initMinimizeContainer() {
    if (!document.getElementById("minimized-cards")) {
      const container = document.createElement("div");
      container.id = "minimized-cards";
      container.className = "nx-minimized-container";
      document.body.appendChild(container);
    }
  }


  drop() {
    setTimeout(() => {
      const container = document.querySelector(this.options.container);
      if (!container) return;

      // Destroy existing sortable if any
      if ($(container).hasClass("ui-sortable")) {
        $(container).sortable("destroy");
      }

      // Setup event handlers for existing minimize, close, and menu buttons
      this.setupControlHandlers(container);

      const showHeader = this.options.showHeader !== false;
      const dragHandle = showHeader ? ".nx-card-title" : ".nx-card";

      $(container).sortable({
        items: "div[class*='nx-col-']", // Semua div dengan class nx-col (tidak harus direct child)
        containment: this.options.container,
        helper: "clone",
        cursor: "move",
        opacity: 0.8,
        tolerance: "pointer",
        forcePlaceholderSize: true,
        scroll: false,
        handle: dragHandle,
        cancel: ".nx-card-controls button", // Prevent dragging from buttons
      });
    }, 100);
  }

  // Setup event handlers for existing minimize and close buttons
  Container(data) {
    let tempalatefield = "";
    const showHeaderGlobal = this.options.showHeader !== false;
    const showFooterGlobal = this.options.showFooter !== false;
    data.content.forEach((row, index) => {
      // ID konsisten: kolom luar #nx_card_{safeId}, area isi/scroll #nx_body_{safeId} (sama safeId)
      const rawRowId =
        row?.id != null && String(row.id).trim() !== "" ? String(row.id).trim() : "";
      const safeId =
        rawRowId !== ""
          ? rawRowId.replace(/[^a-zA-Z0-9_-]/g, "_") || "nx_card"
          : "nx_card";
      const cardWrapId = `nx_card_${safeId}`;
      const cardBodyId = `nx_body_${safeId}`;

      const scrollType = row.scroll?.type || "";
      const scrollHeight = row.scroll?.height || "";
      const cardBodyClass = scrollType
        ? `nx-card-body ${scrollType}`
        : "nx-card-body";
      const cardBodyStyle = scrollHeight
        ? `padding:3px; height:${scrollHeight}`
        : "padding:3px";

      /** Teks judul kartu: `header` (string) seperti `footer`; string kosong → fallback `title`. Tanpa `header`/`title` → tidak ada blok header. `header: false` → tanpa header. */
      let cardHeaderText = null;
      if (row.header === false) {
        cardHeaderText = null;
      } else if (typeof row.header === "string") {
        const h = row.header.trim();
        if (h !== "") {
          cardHeaderText = h;
        } else if (row.title != null && String(row.title).trim() !== "") {
          cardHeaderText = String(row.title).trim();
        }
      } else if (row.title != null && String(row.title).trim() !== "") {
        cardHeaderText = String(row.title).trim();
      }
      const rowShowHeader = showHeaderGlobal && cardHeaderText != null;

      /** Per baris: `footer: false` → tanpa `.nx-card-footer`. Jika string non-kosong, isi footer; jika dihilangkan/`undefined` → tidak ada footer. */
      const rowShowFooter =
        showFooterGlobal &&
        row.footer !== false &&
        row.footer != null &&
        String(row.footer).trim() !== "";

      const headerBlock = rowShowHeader
        ? `
              <div class="nx-card-header">
                <h6 class="nx-card-title mb-0" style="cursor: grab; user-select: none;">${
                  cardHeaderText
                }</h6>
                <div class="nx-card-controls align-right">
                 <button class="nx-btn-text nx-minimize" type="button" title="Minimize">
                    <span class="material-symbols-outlined nx-icon-sm">minimize</span>
                  </button>
                  <button class="nx-btn-text maximize" type="button" title="maximize">
                    <span class="material-symbols-outlined nx-icon-sm">magnification_large</span>
                  </button>
                  <button class="nx-btn-text nx-menu" type="button" title="Menu">
                    <span class="material-symbols-outlined nx-icon-sm">more_vert</span>
                 </button>
                 </div>
              </div>`
        : "";

      const footerBlock = rowShowFooter
        ? `
              <div class="nx-card-footer">
               ${row.footer}
               
              </div>
            `
        : "";

      tempalatefield += `
        <div id="${cardWrapId}" class="${
        row.col
      }" data-index="${index}" data-id="${index}" data-col="${row.col}" data-nx-card-id="${safeId}">
            <div class="nx-card">
              ${headerBlock}
              <div id="${cardBodyId}" class="${cardBodyClass}" style="${cardBodyStyle}">
                ${row.html}
              </div>

            ${footerBlock}
            </div>
        </div>
      `;
    });

    return tempalatefield;
  }

  async refreshContainer(byID, storage, methods) {
    await NXUI.NexaRender.refresh(storage, methods, {
      containerSelector: byID,
    });
  }

  setupControlHandlers(container) {
    // Find all minimize buttons in the container
    const minimizeButtons = container.querySelectorAll(".nx-minimize");
    minimizeButtons.forEach((btn) => {
      // Remove existing click handlers
      btn.removeEventListener("click", this.minimize);

      // Make sure buttons don't trigger drag
      btn.classList.add("ui-sortable-cancel");

      // Add new click handler
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        e.preventDefault();

        // Get the closest card container
        const cardContainer = btn.closest(".nx-card");
        if (cardContainer) {
          // Get the parent column
          const column = cardContainer.closest("[class*='nx-col-']");
          if (column) {
            this.minimizeItem(column);
          }
        }
      });
    });

    // Find all close buttons in the container
    const closeButtons = container.querySelectorAll(".nx-close");
    closeButtons.forEach((btn) => {
      // Remove existing click handlers
      btn.removeEventListener("click", this.close);

      // Make sure buttons don't trigger drag
      btn.classList.add("ui-sortable-cancel");

      // Add new click handler
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        e.preventDefault();

        // Get the closest card container
        const cardContainer = btn.closest(".nx-card");
        if (cardContainer) {
          // Get the parent column
          const column = cardContainer.closest("[class*='nx-col-']");
          if (column) {
            this.closeItem(column);
          }
        }
      });
    });

    // Find all maximize buttons in the container
    const maximizeButtons = container.querySelectorAll(".maximize");
    maximizeButtons.forEach((btn) => {
      // Remove existing click handlers
      btn.removeEventListener("click", this.maximize);

      // Make sure buttons don't trigger drag
      btn.classList.add("ui-sortable-cancel");

      // Add new click handler
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        e.preventDefault();

        // Get the closest card container
        const cardContainer = btn.closest(".nx-card");
        if (cardContainer) {
          // Get the parent column
          const column = cardContainer.closest("[class*='nx-col-']");
          if (column) {
            this.maximizeItem(column);
          }
        }
      });
    });

    // Find all menu buttons in the container
    const menuButtons = container.querySelectorAll(".nx-menu");
    menuButtons.forEach((btn) => {
      // Remove existing click handlers
      btn.removeEventListener("click", this.handleMenu);

      // Make sure buttons don't trigger drag
      btn.classList.add("ui-sortable-cancel");

      // Add new click handler
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        e.preventDefault();

        // Get the closest card container
        const cardContainer = btn.closest(".nx-card");
        if (cardContainer) {
          // Get the parent column
          const column = cardContainer.closest("[class*='nx-col-']");
          if (column) {
            this.showMenu(column, btn);
          }
        }
      });
    });
  }

  // Show menu for card
  showMenu(column, button) {
    document.querySelectorAll(".nx-card-menu[data-nx-open]").forEach((el) => el.remove());

    const menu = document.createElement("div");
    menu.className = "nx-card-menu";
    menu.setAttribute("role", "menu");
    menu.setAttribute("data-nx-open", "1");
    menu.innerHTML = `
      <button type="button" class="nx-menu-item" role="menuitem" data-action="minimize">Minimize</button>
      <button type="button" class="nx-menu-item" role="menuitem" data-action="close">Close</button>
      <button type="button" class="nx-menu-item" role="menuitem" data-action="settings">Settings</button>
    `;

    const rect = button.getBoundingClientRect();
    const menuWidth = 192;
    let left = rect.right - menuWidth;
    if (left < 8) left = 8;
    if (left + menuWidth > window.innerWidth - 8) {
      left = Math.max(8, window.innerWidth - menuWidth - 8);
    }

    menu.style.position = "fixed";
    menu.style.top = `${rect.bottom + 4}px`;
    menu.style.left = `${left}px`;
    menu.style.zIndex = "10050";

    document.body.appendChild(menu);

    const onMenuClick = (e) => {
      e.stopPropagation();
      const item = e.target.closest("[data-action]");
      const action = item && item.dataset ? item.dataset.action : null;
      if (action === "minimize") {
        this.minimizeItem(column);
      } else if (action === "close") {
        this.closeItem(column);
      } else if (action === "settings") {
        // Settings functionality can be implemented here
      }
      menu.remove();
      document.removeEventListener("click", onOutside);
    };

    const onOutside = (e) => {
      if (!menu.isConnected) {
        document.removeEventListener("click", onOutside);
        return;
      }
      if (!menu.contains(e.target)) {
        menu.remove();
        document.removeEventListener("click", onOutside);
      }
    };

    menu.addEventListener("click", onMenuClick);

    setTimeout(() => {
      document.addEventListener("click", onOutside);
    }, 0);
  }

  // Maximize functionality for individual item
  maximizeItem(item) {
    if (!item) return;

    // Get current column classes
    const currentClasses = item.className;
    const originalCol = item.getAttribute("data-col");

    // Check if item is currently maximized
    const isMaximized = item.classList.contains("nx-maximized");

    if (isMaximized) {
      // Restore to original size
      item.className = currentClasses.replace(/nx-col-\d+/g, "").trim();
      item.classList.add(originalCol);
      item.classList.remove("nx-maximized");

      // Update maximize icon
      const maximizeIcon = item.querySelector(
        ".maximize .material-symbols-outlined"
      );
      if (maximizeIcon) {
        maximizeIcon.textContent = "magnification_large";
      }

      // Update button title
      const maximizeBtn = item.querySelector(".maximize");
      if (maximizeBtn) {
        maximizeBtn.title = "maximize";
      }
    } else {
      // Maximize to full width
      item.className = currentClasses.replace(/nx-col-\d+/g, "").trim();
      item.classList.add("nx-col-12", "nx-maximized");

      // Update maximize icon
      const maximizeIcon = item.querySelector(
        ".maximize .material-symbols-outlined"
      );
      if (maximizeIcon) {
        maximizeIcon.textContent = "magnification_small";
      }

      // Update button title
      const maximizeBtn = item.querySelector(".maximize");
      if (maximizeBtn) {
        maximizeBtn.title = "restore";
      }
    }
  }

  // Minimize functionality for container
  minimize() {
    const container = document.querySelector(this.options.container);
    if (!container) return;

    // Toggle minimized state
    if (container.classList.contains("nx-minimized")) {
      container.classList.remove("nx-minimized");

      // Show content
      const items = container.querySelectorAll(this.options.item);
      items.forEach((item) => {
        item.style.display = "block";
      });
    } else {
      container.classList.add("nx-minimized");

      // Hide content but keep container visible
      const items = container.querySelectorAll(this.options.item);
      items.forEach((item) => {
        item.style.display = "none";
      });
    }
  }

  // Create minimized item in taskbar
  createMinimizedItem(itemId, item) {
    const container = document.getElementById("minimized-cards");
    if (!container) {
      this.initMinimizeContainer();
    }

    // Get title from card
    const cardTitle =
      item.querySelector(".nx-card-title")?.textContent || itemId;

    // Remove existing minimized item if any
    this.removeMinimizedItem(itemId, false);

    // Create minimized item
    const minimizedItem = document.createElement("div");
    minimizedItem.className = "nx-card-minimized";
    minimizedItem.dataset.cardId = itemId;
    minimizedItem.title = `Click to restore ${cardTitle}`;

    // Create title span with character limit
    const titleSpan = document.createElement("span");
    titleSpan.className = "nx-minimized-title";
    const maxLength = 15;
    const displayTitle =
      cardTitle.length > maxLength
        ? cardTitle.substring(0, maxLength) + "..."
        : cardTitle;
    titleSpan.textContent = displayTitle;
    titleSpan.title = cardTitle;

    // Create close button
    const closeButton = document.createElement("button");
    closeButton.className = "nx-minimized-close";
    closeButton.innerHTML =
      '<span class="material-symbols-outlined">close</span>';
    closeButton.title = `Close ${cardTitle}`;

    // Click title to restore
    titleSpan.addEventListener("click", (e) => {
      e.stopPropagation();
      this.restoreItem(itemId, item);
    });

    // Click close button to close card completely
    closeButton.addEventListener("click", (e) => {
      e.stopPropagation();
      this.closeItem(item);
      this.removeMinimizedItem(itemId, true);
    });

    // Click container to restore (fallback)
    minimizedItem.addEventListener("click", () => {
      this.restoreItem(itemId, item);
    });

    minimizedItem.appendChild(titleSpan);
    minimizedItem.appendChild(closeButton);

    const targetContainer = document.getElementById("minimized-cards");
    if (targetContainer) {
      targetContainer.appendChild(minimizedItem);
    }
  }

  // Remove minimized item from taskbar
  removeMinimizedItem(itemId, animate = true) {
    const container = document.getElementById("minimized-cards");
    const item = container?.querySelector(`[data-card-id="${itemId}"]`);

    if (item) {
      if (animate) {
        item.classList.add("removing");
        setTimeout(() => {
          if (item.parentNode) {
            item.remove();
          }
        }, 300);
      } else {
        item.remove();
      }
    }
  }

  // Minimize functionality for individual item
  minimizeItem(item) {
    if (!item) return;

    const itemId =
      item.getAttribute("data-id") ||
      item.getAttribute("id") ||
      `item-${Math.random().toString(36).substr(2, 9)}`;


    // Add minimizing animation class
    item.classList.add("minimizing");

    // Create minimized representation in taskbar
    this.createMinimizedItem(itemId, item);

    // Hide the item after animation
    setTimeout(() => {
      // Hide the item
      item.style.display = "none";
      item.classList.add("nx-minimized");
      item.classList.remove("minimizing");

      // Update minimize icon
      const minimizeIcon = item.querySelector(
        ".nx-minimize .material-symbols-outlined"
      );
      if (minimizeIcon) minimizeIcon.textContent = "expand_more";
    }, 300);
  }

  // Restore minimized item
  restoreItem(itemId, item) {
    // If item is provided directly
    if (item) {
      this.doRestoreItem(item);
      this.removeMinimizedItem(itemId);
      return;
    }

    // Find item by ID
    const container = document.querySelector(this.options.container);
    if (!container) return;

    const targetItem =
      container.querySelector(`[data-id="${itemId}"]`) ||
      document.getElementById(itemId);

    if (targetItem) {
      this.doRestoreItem(targetItem);
      this.removeMinimizedItem(itemId);
    }
  }

  // Actually restore the item
  doRestoreItem(item) {
    // Show the item
    item.style.display = "block";
    item.classList.remove("nx-minimized");
    item.classList.add("restoring");

    // Update minimize icon
    const minimizeIcon = item.querySelector(
      ".nx-minimize .material-symbols-outlined"
    );
    if (minimizeIcon) minimizeIcon.textContent = "minimize";


    // Remove animation class after animation completes
    setTimeout(() => {
      item.classList.remove("restoring");
    }, 300);
  }

  // Close functionality for container
  close() {
    const container = document.querySelector(this.options.container);
    if (!container) return;


    // Clear content
    container.innerHTML = "";
  }

  // Close functionality for individual item
  closeItem(item) {
    if (!item) return;

    // Remove the item from DOM
    item.remove();
  }

  // Update data attributes after reordering
  updateDataAttributes() {
    const container = document.querySelector(this.options.container);
    if (!container) return;

    const items = container.querySelectorAll(this.options.item);
    items.forEach((item, index) => {
      item.setAttribute("data-index", index);
      item.setAttribute("data-id", index);
    });
  }


  // Static method to get instance by container ID
  static getInstance(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    if (!container._nexaLayerInstance) {
      container._nexaLayerInstance = new NexaLayer({
        container: `#${containerId}`,
      });
    }

    return container._nexaLayerInstance;
  }

  // Static minimize method for container
  static minimize(containerId) {
    const instance = NexaLayer.getInstance(containerId);
    if (instance) {
      instance.minimize();
    }
  }

  // Static minimize method for item
  static minimizeItem(itemId) {
    const item = document.getElementById(itemId);
    if (!item) return;

    // Find container
    const container = item.closest(".nx-row");
    if (container) {
      const containerId = container.id;
      const instance = NexaLayer.getInstance(containerId);
      if (instance) {
        instance.minimizeItem(item);
      }
    }
  }

  // Static close method for container
  static close(containerId) {
    const instance = NexaLayer.getInstance(containerId);
    if (instance) {
      instance.close();
    }
  }

  // Static close method for item
  static closeItem(itemId) {
    const item = document.getElementById(itemId);
    if (!item) return;

    // Find container
    const container = item.closest(".nx-row");
    if (container) {
      const containerId = container.id;
      const instance = NexaLayer.getInstance(containerId);
      if (instance) {
        instance.closeItem(item);
      }
    }
  }

  // Static maximize method for item
  static maximizeItem(itemId) {
    const item = document.getElementById(itemId);
    if (!item) return;

    // Find container
    const container = item.closest(".nx-row");
    if (container) {
      const containerId = container.id;
      const instance = NexaLayer.getInstance(containerId);
      if (instance) {
        instance.maximizeItem(item);
      }
    }
  }
}

// Global window methods for easy access
window.minimizeLayer = (containerId) => {
  NexaLayer.minimize(containerId);
};

window.minimizeLayerItem = (itemId) => {
  NexaLayer.minimizeItem(itemId);
};

window.closeLayer = (containerId) => {
  NexaLayer.close(containerId);
};

window.closeLayerItem = (itemId) => {
  NexaLayer.closeItem(itemId);
};

window.maximizeLayerItem = (itemId) => {
  NexaLayer.maximizeItem(itemId);
};

// Deprecated: use minimizeLayer* / closeLayer* / maximizeLayer*
window.minimizeDrag = window.minimizeLayer;
window.minimizeDragItem = window.minimizeLayerItem;
window.closeDrag = window.closeLayer;
window.closeDragItem = window.closeLayerItem;
window.maximizeDragItem = window.maximizeLayerItem;
