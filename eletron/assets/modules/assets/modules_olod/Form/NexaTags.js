export class NexaTags {
  constructor(options = {}) {
    this.onChange = options.onChange || null; // Callback function untuk perubahan
    
    // Handle multiple targetId (array atau single)
    const targetIds = Array.isArray(options.targetId) 
      ? options.targetId 
      : [options.targetId || "tags"];
    
    // Handle multiple validasi (array atau single)
    const validasis = Array.isArray(options.validasi)
      ? options.validasi
      : (options.validasi !== undefined ? [options.validasi] : [null]);
    
    // Handle multiple value (array atau single)
    const values = Array.isArray(options.value)
      ? options.value
      : (options.value !== undefined ? [options.value] : [null]);
    
    // Handle multiple hideValue (array atau single)
    const hideValues = Array.isArray(options.hideValue)
      ? options.hideValue
      : (options.hideValue !== undefined ? [options.hideValue] : [null]);
    
    // Handle multiple close (array atau single)
    const closes = Array.isArray(options.close)
      ? options.close
      : (options.close !== undefined ? [options.close] : [true]); // Default true (tampilkan close button)
    
    // Handle multiple data (array of data, false, atau single data)
    // Jika options.data adalah array of objects dengan property 'failed', gunakan untuk semua instance
    // Jika options.data adalah array of arrays, gunakan sesuai index
    // Jika options.data === false, gunakan false untuk semua
    let datas = [];
    if (options.data === false) {
      // Jika false, semua instance dapat false
      datas = targetIds.map(() => false);
    } else if (Array.isArray(options.data) && options.data.length > 0) {
      // Cek apakah elemen pertama adalah array (array of arrays)
      const firstItem = options.data[0];
      const isArrayOfArrays = Array.isArray(firstItem);
      
      if (isArrayOfArrays) {
        // Array of arrays: setiap instance dapat data berbeda
        datas = options.data;
      } else {
        // Single array (array of objects): semua instance dapat data yang sama
        // Clone array untuk setiap instance agar tidak share reference
        datas = targetIds.map(() => [...options.data]);
      }
    } else if (options.data !== undefined && options.data !== null) {
      // Single value: semua instance dapat data yang sama
      datas = targetIds.map(() => options.data);
    } else {
      // Tidak ada data: semua instance dapat array kosong
      datas = targetIds.map(() => []);
    }
    
    // Inisialisasi untuk setiap targetId
    this.instances = [];
    targetIds.forEach((targetId, index) => {
      // Ambil data untuk instance ini - pastikan dapat array yang benar
      let instanceData;
      if (datas[index] !== undefined) {
        instanceData = datas[index];
      } else if (datas[0] !== undefined) {
        instanceData = datas[0];
      } else {
        instanceData = [];
      }
      
      const instanceOptions = {
        targetId: targetId,
        data: instanceData, // Pass data ke instance
        validasi: validasis[index] !== undefined ? validasis[index] : validasis[0],
        value: values[index] !== undefined ? values[index] : values[0],
        hideValue: hideValues[index] !== undefined ? hideValues[index] : hideValues[0],
        close: closes[index] !== undefined ? closes[index] : closes[0],
        onChange: this.onChange
      };
      
      const instance = new NexaTagsInstance(instanceOptions);
      this.instances.push(instance);
    });
  }

  // Static method untuk mengambil semua tags dari semua instance
  static getAllTags() {
    const result = {};
    
    // Cari semua container nexa-tags
    const containers = document.querySelectorAll('.nexa-tags-container');
    
    containers.forEach(container => {
      // Cari input element di dalam container (bukan hidden input)
      const inputElement = container.querySelector('input.form-nexa-control, input[type="text"]');
      
      if (inputElement && inputElement.type !== "hidden") {
        // Ambil name attribute dari input element
        const name = inputElement.getAttribute("id");
        
        // Cari hidden input di dalam container yang sama (bukan hardcode _value)
        const outputElement = container.querySelector('input[type="hidden"]');
        
        if (outputElement) {
          // Ambil value
          const value = outputElement.value || "";
          
          // Convert string pipe-separated menjadi array
          const tagsArray = value 
            ? value.split("|").map(tag => tag.trim()).filter(tag => tag.length > 0)
            : [];
          
          // Simpan berdasarkan name dari input (atau ID jika tidak ada name)
          const key = name || inputElement.id;
          result[key] = {
            name: key,        // Name attribute dari input
            value: value,      // String: "title,deskripsi"
            array: tagsArray   // Array: ["title", "deskripsi"]
          };
        }
      }
    });
    
    return result;
  }
}

// Class untuk instance individual
class NexaTagsInstance {
  constructor(options = {}) {
    this.targetId = options.targetId || "tags";
    this.allowFreeInput = options.data === false; // Mode bebas input jika data = false
    // ✅ Detect if we're in a modal
    this.isModal = options.isModal !== undefined 
      ? options.isModal 
      : (() => {
          // Delay detection until init() runs when DOM is ready
          return false; // Will be set during init()
        })();
    
    // Store the isModal option for later use in init()
    this._isModalOption = options.isModal;
    
    // Handle database - pastikan array diterima dengan benar
    if (this.allowFreeInput) {
      this.database = [];
    } else if (Array.isArray(options.data)) {
      this.database = options.data;
    } else if (options.data !== undefined && options.data !== null) {
      this.database = [options.data];
    } else {
      this.database = [];
    }
    
    this.initialValue = options.value || null; // Value awal dari options
    this.maxTags = options.validasi || null; // Batas maksimal tag yang bisa dipilih
    this.hideValue = options.hideValue || null; // Nama untuk hidden input value
    this.showClose = options.close !== undefined ? options.close : true; // Tampilkan tombol close (default true)
    this.onChange = options.onChange || null; // Callback function untuk perubahan
    this.tags = []; // Tags yang dipilih (mulai dari kosong)
    this.selectedIndex = -1; // Index untuk navigasi keyboard
    this.draggedIndex = null; // Index chip yang sedang di-drag
    
    this.init();
  }

  // ✅ Add CSS styles for tags dropdown (similar to addSuggestionsStyles in NexaFloating)
  addTagsDropdownStyles() {
    // Add CSS styles only once
    if (document.getElementById("nexa-tags-dropdown-styles")) return;

    const style = document.createElement("style");
    style.id = "nexa-tags-dropdown-styles";
    style.textContent = `
      .nexa-tags-container {
        position: relative;
        width: 100%;
      }
      
      .nexa-tags-chips {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        width: 100%;
        gap: 6px;
      }
      
      .nexa-tags-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 2147483647 !important;
        display: none;
        max-height: 200px;
        overflow-y: auto;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100%;
        left: 0;
        right: 0;
      }
      
      /* Modal-specific dropdown positioning */
      .nx-modal .nexa-tags-dropdown {
        position: absolute;
        z-index: 2147483647 !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100%;
      }
      
      .nx-modal .nexa-tags-container {
        position: relative;
      }
      
      /* Dropdown above input styling */
      .nexa-tags-dropdown.nexa-dropdown-above {
        box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
        border-radius: 4px 4px 0 0;
      }
    `;
    document.head.appendChild(style);
  }

  init() {
    const targetElement = document.getElementById(this.targetId);
    if (!targetElement) {
      return;
    }

    // ✅ Detect modal after DOM is ready
    if (this._isModalOption !== undefined) {
      this.isModal = this._isModalOption;
    } else {
      this.isModal = targetElement.closest(".nx-modal") !== null;
    }

    // Ambil name attribute untuk outputId, gunakan hideValue jika ada, fallback ke name_value atau targetId_value
    const nameAttr = targetElement.getAttribute("name");
    if (this.hideValue) {
      this.outputId = this.hideValue;
    } else {
      this.outputId = nameAttr ? `${nameAttr}_value` : `${this.targetId}_value`;
    }

    // Prioritaskan value dari options, baru dari input attribute
    if (this.initialValue !== null && this.initialValue !== undefined) {
      if (Array.isArray(this.initialValue)) {
        // Jika array, langsung load
        this.loadInitialTagsFromArray(this.initialValue);
      } else if (typeof this.initialValue === "string" && this.initialValue.trim()) {
        // Jika string, parse dulu
        this.loadInitialTags(this.initialValue);
      }
    } else {
      // Jika tidak ada dari options, baca dari input attribute
      const inputValue = targetElement.value || targetElement.getAttribute("value") || "";
      if (inputValue && inputValue.trim()) {
        this.loadInitialTags(inputValue);
      }
    }

    // Buat wrapper container untuk chips dan input
    const wrapper = document.createElement("div");
    wrapper.className = "nexa-tags-container";
    wrapper.style.cssText = `
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-height: 38px;
      padding: 4px 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background: white;
      cursor: text;
      width: 100%;
    `;

    // Buat container untuk chips
    const chipsContainer = document.createElement("div");
    chipsContainer.className = "nexa-tags-chips";
    chipsContainer.style.cssText = `
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      gap: 6px;
      width: 100%;
      align-items: flex-start;
    `;

    // Style untuk input
    targetElement.style.cssText = `
      width: 100%;
      border: none;
      outline: none;
      padding: 4px 0;
      font-size: 14px;
      background: transparent;
    `;
    
    // Clear value dari input karena sudah diparse menjadi tags
    targetElement.value = "";
    targetElement.removeAttribute("value");

    // ✅ Add CSS styles for tags dropdown (similar to addSuggestionsStyles)
    this.addTagsDropdownStyles();

    // Buat dropdown untuk suggestions
    const dropdown = document.createElement("div");
    dropdown.className = "nexa-tags-dropdown";
    // ✅ Use higher z-index for modal, follow setModalSearchInput pattern
    const dropdownZIndex = this.isModal ? "2147483647" : "1000";
    dropdown.style.cssText = `
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      margin-top: 4px;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      max-height: 200px;
      overflow-y: auto;
      z-index: ${dropdownZIndex};
      width: 100%;
    `;
    // ✅ For modal, use important z-index like setModalSearchInput
    if (this.isModal) {
      dropdown.style.setProperty("z-index", dropdownZIndex, "important");
    }

    // Render chips awal (dari value yang sudah dibaca)
    this.renderChips(chipsContainer);

    // Event listener untuk input - pencarian (hanya jika bukan free input mode)
    targetElement.addEventListener("input", (e) => {
      if (!this.allowFreeInput) {
        const query = e.target.value.trim().toLowerCase();
        this.searchAndShowSuggestions(query, dropdown);
      }
    });

    // Event listener untuk focus - tampilkan semua saat fokus (hanya jika bukan free input mode)
    targetElement.addEventListener("focus", (e) => {
      if (!this.allowFreeInput) {
        const query = e.target.value.trim().toLowerCase();
        this.searchAndShowSuggestions(query, dropdown);
        // ✅ Ensure modal positioning is applied on focus
        if (this.isModal && dropdown.style.display === "block") {
          setTimeout(() => {
            this.applyModalDropdownPosition(targetElement, dropdown);
          }, 10);
        }
      }
    });

    // Event listener untuk keyboard navigation
    targetElement.addEventListener("keydown", (e) => {
      const suggestions = dropdown.querySelectorAll(".nexa-tags-suggestion-item");
      
      if (e.key === "ArrowDown") {
        e.preventDefault();
        this.selectedIndex = Math.min(this.selectedIndex + 1, suggestions.length - 1);
        this.highlightSuggestion(suggestions);
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
        this.highlightSuggestion(suggestions);
      } else if (e.key === "Enter" || (this.allowFreeInput && e.key === "|")) {
        e.preventDefault();
        
        if (this.allowFreeInput) {
          // Mode bebas input: tambah dari input value langsung
          const inputValue = targetElement.value.trim();
          if (inputValue) {
            // Remove pipe jika ada
            const tagValue = inputValue.replace(/\|$/, '').trim();
            if (tagValue) {
              const added = this.addTag(tagValue, chipsContainer);
              if (added) {
                targetElement.value = "";
              }
            }
          }
        } else {
          // Mode database: pilih dari suggestions
          if (this.selectedIndex >= 0 && suggestions[this.selectedIndex]) {
            const selectedItem = suggestions[this.selectedIndex];
            const failedValue = selectedItem.dataset.failed;
            const added = this.addTag(failedValue, chipsContainer);
            if (added) {
              targetElement.value = "";
              dropdown.style.display = "none";
              this.selectedIndex = -1;
            }
          } else if (targetElement.value.trim()) {
            // Jika ada input tapi tidak ada suggestion terpilih, coba tambahkan langsung (fallback)
            const inputValue = targetElement.value.trim();
            const added = this.addTag(inputValue, chipsContainer);
            if (added) {
              targetElement.value = "";
              dropdown.style.display = "none";
            }
          }
        }
      } else if (e.key === "Escape") {
        dropdown.style.display = "none";
        this.selectedIndex = -1;
      } else if (e.key === "Backspace" && !e.target.value && this.tags.length > 0) {
        // Hapus chip terakhir jika backspace ditekan di input kosong
        this.removeTag(this.tags.length - 1, chipsContainer);
      }
    });

    // Event listener untuk klik di luar (modal-aware)
    document.addEventListener("click", (e) => {
      if (!wrapper.contains(e.target)) {
        // ✅ For modal, add small delay like setModalSearchInput to allow click events
        if (this.isModal) {
          setTimeout(() => {
            if (!wrapper.contains(e.target)) {
              dropdown.style.display = "none";
            }
          }, 150);
        } else {
          dropdown.style.display = "none";
        }
      }
    });

    // ✅ Additional blur handler for modal (similar to setModalSearchInput)
    if (this.isModal) {
      targetElement.addEventListener("blur", (e) => {
        setTimeout(() => {
          const suggestionsDiv = dropdown;
          if (suggestionsDiv && !wrapper.contains(document.activeElement)) {
            suggestionsDiv.style.display = "none";
          }
        }, 150); // Small delay to allow click events
      });
    }

    // Update value awal (akan membuat outputElement jika belum ada)
    this.updateValue();

    // Wrap input dan chips
    targetElement.parentNode.insertBefore(wrapper, targetElement);
    wrapper.appendChild(chipsContainer);
    wrapper.appendChild(targetElement);
    wrapper.appendChild(dropdown);
    
    // Focus ke input saat klik container
    wrapper.addEventListener("click", () => {
      targetElement.focus();
    });
  }

  loadInitialTagsFromArray(tagArray) {
    if (!Array.isArray(tagArray) || tagArray.length === 0) {
      return;
    }

    // Validasi dan tambahkan tag
    tagArray.forEach(tagName => {
      // Cek batas maksimal tag
      if (this.maxTags !== null && this.maxTags !== undefined) {
        if (this.tags.length >= this.maxTags) {
          return; // Skip jika sudah mencapai batas
        }
      }

      // Handle jika tagName adalah object dengan property failed
      const actualTagName = typeof tagName === "object" && tagName.failed 
        ? tagName.failed 
        : String(tagName).trim();
      
      if (!actualTagName) {
        return;
      }

      // Jika free input mode, langsung tambahkan tanpa validasi database
      if (this.allowFreeInput) {
        const exists = this.tags.some(item => item.failed === actualTagName);
        if (!exists) {
          this.tags.push({ failed: actualTagName });
        }
      } else {
        // Cek apakah tag ada di database
        const inDatabase = this.database.some(item => item.failed === actualTagName);
        if (inDatabase) {
          // Cek duplikasi
          const exists = this.tags.some(item => item.failed === actualTagName);
            if (!exists) {
              this.tags.push({ failed: actualTagName });
            }
          }
      }
    });
  }

  loadInitialTags(valueString) {
    if (!valueString || !valueString.trim()) {
      return;
    }

    // Parse value yang dipisahkan pipe
    const tagNames = valueString
      .split("|")
      .map(tag => tag.trim())
      .filter(tag => tag.length > 0);

    // Gunakan method yang sama untuk array
    this.loadInitialTagsFromArray(tagNames);
  }

  renderChips(container) {
    container.innerHTML = "";
    this.tags.forEach((item, index) => {
      const chip = this.createChip(item.failed, index, container);
      container.appendChild(chip);
    });
  }

  createChip(text, index, container) {
    const chip = document.createElement("div");
    chip.className = "nexa-tag-chip";
    chip.draggable = true;
    chip.dataset.index = index;
    chip.style.cssText = `
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 6px;
      background: #4285f4;
      color: white;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 500;
      cursor: move;
      user-select: none;
      transition: background-color 0.2s, opacity 0.2s, transform 0.2s;
    `;

    // Drag event handlers
    chip.addEventListener("dragstart", (e) => {
      chip.style.opacity = "0.5";
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/html", chip.innerHTML);
      e.dataTransfer.setData("text/plain", index.toString());
      this.draggedIndex = index;
    });

    chip.addEventListener("dragend", (e) => {
      chip.style.opacity = "1";
      chip.style.transform = "";
      
      // Remove all drag over styles
      const allChips = container.querySelectorAll(".nexa-tag-chip");
      allChips.forEach(c => {
        c.style.backgroundColor = "#4285f4";
        c.style.transform = "";
      });
      
      this.draggedIndex = null;
    });

    chip.addEventListener("dragover", (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      
      if (this.draggedIndex === null || this.draggedIndex === undefined) return;
      
      const currentIndex = parseInt(chip.dataset.index);
      
      if (currentIndex !== this.draggedIndex && !isNaN(currentIndex)) {
        // Highlight drop zone
        chip.style.backgroundColor = "#5a9df5";
        chip.style.transform = "scale(1.05)";
      }
    });

    chip.addEventListener("dragleave", (e) => {
      chip.style.backgroundColor = "#4285f4";
      chip.style.transform = "";
    });

    chip.addEventListener("drop", (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      if (this.draggedIndex === null || this.draggedIndex === undefined) return;
      
      const dropIndex = parseInt(chip.dataset.index);
      const draggedIndex = this.draggedIndex;
      
      if (dropIndex !== draggedIndex && !isNaN(dropIndex)) {
        // Reorder tags array
        const draggedTag = this.tags[draggedIndex];
        this.tags.splice(draggedIndex, 1);
        
        // Calculate new position after removal
        const newDropIndex = draggedIndex < dropIndex ? dropIndex - 1 : dropIndex;
        this.tags.splice(newDropIndex, 0, draggedTag);
        
        // Re-render chips
        this.renderChips(container);
        
        // Update value
        this.dispatchChangeEvent();
      }
      
      // Reset styles
      const allChips = container.querySelectorAll(".nexa-tag-chip");
      allChips.forEach(c => {
        c.style.backgroundColor = "#4285f4";
        c.style.transform = "";
      });
    });

    // Icon X (hanya jika showClose true)
    let closeIcon = null;
    if (this.showClose) {
      closeIcon = document.createElement("span");
      closeIcon.innerHTML = "×";
      closeIcon.style.cssText = `
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        line-height: 1;
        margin-left: -2px;
        padding: 0 1px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        transition: background-color 0.2s;
      `;

      // Prevent drag when clicking close icon
      closeIcon.addEventListener("mousedown", (e) => {
        e.stopPropagation();
      });

      closeIcon.addEventListener("mouseenter", () => {
        closeIcon.style.backgroundColor = "rgba(255, 255, 255, 0.2)";
      });

      closeIcon.addEventListener("mouseleave", () => {
        closeIcon.style.backgroundColor = "transparent";
      });

      closeIcon.addEventListener("click", (e) => {
        e.stopPropagation();
        this.removeTag(index, container);
      });
    }

    // Text content
    const chipText = document.createElement("span");
    chipText.textContent = text;
    chipText.style.cssText = `
      white-space: nowrap;
      pointer-events: none;
    `;

    chip.appendChild(chipText);
    if (closeIcon) {
      chip.appendChild(closeIcon);
    }

    return chip;
  }

  searchAndShowSuggestions(query, dropdown) {
    if (!dropdown) {
      return;
    }

    const targetElement = document.getElementById(this.targetId);
    if (!targetElement) {
      return;
    }

    const chipsContainer = targetElement.parentElement ? targetElement.parentElement.querySelector(".nexa-tags-chips") : null;
    
    // Cek apakah database kosong
    if (!this.database || this.database.length === 0) {
      dropdown.style.display = "none";
      return;
    }
    
    // Filter database berdasarkan query
    const filtered = this.database.filter(item => {
      if (!item) return false;
      // ✅ Support both 'failed' and 'value' property for compatibility
      const itemValue = item.failed || item.value || String(item);
      if (!itemValue) return false;
      const failed = String(itemValue).toLowerCase();
      // Filter yang belum dipilih dan cocok dengan query
      const notSelected = !this.tags.some(tag => {
        const tagValue = tag.failed || tag.value || String(tag);
        return String(tagValue) === String(itemValue);
      });
      const matchesQuery = query === "" || failed.includes(query);
      return notSelected && matchesQuery;
    });

    // Tampilkan semua jika query kosong, atau hasil filter
    const results = query === "" ? this.database.filter(item => {
      if (!item) return false;
      // ✅ Support both 'failed' and 'value' property
      const itemValue = item.failed || item.value || String(item);
      if (!itemValue) return false;
      return !this.tags.some(tag => {
        const tagValue = tag.failed || tag.value || String(tag);
        return String(tagValue) === String(itemValue);
      });
    }) : filtered;

    if (results.length === 0 && query !== "") {
      dropdown.style.display = "none";
      return;
    }

    // Render suggestions
    dropdown.innerHTML = "";
    
    // Cek apakah sudah mencapai batas maksimal
    const isAtLimit = this.maxTags !== null && this.maxTags !== undefined && this.tags.length >= this.maxTags;
    
    if (isAtLimit) {
      // Tampilkan pesan limit
      const limitMessage = document.createElement("div");
      limitMessage.className = "nexa-tags-suggestion-limit";
      limitMessage.style.cssText = `
        padding: 12px;
        text-align: center;
        color: #ff9800;
        font-size: 13px;
        font-weight: 500;
        background: #fff3e0;
        border-bottom: 1px solid #ff9800;
      `;
      limitMessage.textContent = `Maksimal ${this.maxTags} tag (${this.tags.length}/${this.maxTags} terpilih)`;
      dropdown.appendChild(limitMessage);
      dropdown.style.display = "block";
      return;
    }
    
    if (results.length > 0) {
      results.forEach((item, index) => {
        const suggestionItem = document.createElement("div");
        suggestionItem.className = "nexa-tags-suggestion-item";
        // ✅ Support both 'failed' and 'value' property
        const itemValue = item.failed || item.value || String(item);
        suggestionItem.dataset.failed = itemValue;
        suggestionItem.style.cssText = `
          padding: 8px 12px;
          cursor: pointer;
          font-size: 14px;
          border-bottom: 1px solid #f0f0f0;
          transition: background-color 0.2s;
        `;
        
        // Highlight text yang cocok
        const failed = String(itemValue);
        if (query && query !== "") {
          const regex = new RegExp(`(${query})`, "gi");
          suggestionItem.innerHTML = failed.replace(regex, "<strong>$1</strong>");
        } else {
          suggestionItem.textContent = failed;
        }

        suggestionItem.addEventListener("mouseenter", () => {
          suggestionItem.style.backgroundColor = "#f5f5f5";
          this.selectedIndex = index;
        });

        suggestionItem.addEventListener("mouseleave", () => {
          suggestionItem.style.backgroundColor = "transparent";
        });

        suggestionItem.addEventListener("click", () => {
          // ✅ Support both 'failed' and 'value' property
          const itemValue = item.failed || item.value || String(item);
          const added = this.addTag(itemValue, chipsContainer);
          if (added) {
            targetElement.value = "";
            dropdown.style.display = "none";
            this.selectedIndex = -1;
          }
          // Jika tidak berhasil (sudah mencapai limit), dropdown tetap terbuka
        });

        dropdown.appendChild(suggestionItem);
      });
      
      // ✅ Set display to block BEFORE applying modal positioning
      dropdown.style.display = "block";
      
      // ✅ Apply modal-aware positioning (similar to setModalSearchInput)
      if (this.isModal) {
        // Use setTimeout to ensure DOM is ready for positioning calculation
        setTimeout(() => {
          this.applyModalDropdownPosition(targetElement, dropdown);
        }, 10);
      } else {
        // For non-modal, ensure dropdown is visible
        dropdown.style.visibility = "visible";
        dropdown.style.opacity = "1";
      }
    } else {
      dropdown.style.display = "none";
    }
  }

  // ✅ New method for modal dropdown positioning (based on setModalSearchInput pattern)
  applyModalDropdownPosition(targetElement, dropdown) {
    if (!targetElement || !dropdown) {
      return;
    }

    const wrapper = targetElement.closest(".nexa-tags-container");
    if (!wrapper) {
      return;
    }

    // Check if we're inside a modal
    const modalContainer = targetElement.closest(".nx-modal");
    if (!modalContainer) {
      // Still apply high z-index even if modal not found
    }

    // Ensure container has relative positioning
    if (window.getComputedStyle(wrapper).position === "static") {
      wrapper.style.position = "relative";
    }

    // Ensure dropdown is in the correct container
    if (dropdown.parentNode !== wrapper) {
      wrapper.appendChild(dropdown);
    }

    // Always set high z-index for modal context
    dropdown.style.setProperty("z-index", "2147483647", "important");
    dropdown.style.visibility = "visible";
    dropdown.style.opacity = "1";
    dropdown.style.display = "block";

    if (!modalContainer) {
      // Not in modal, use normal positioning but with high z-index
      dropdown.style.top = wrapper.offsetHeight + "px";
      dropdown.style.left = "0px";
      dropdown.style.right = "0px";
      dropdown.style.width = "100%"; // ✅ Match container width
      return;
    }

    const modalBody = modalContainer.querySelector(".nx-modal-body");
    if (!modalBody) {
      dropdown.style.top = wrapper.offsetHeight + "px";
      dropdown.style.left = "0px";
      dropdown.style.right = "0px";
      dropdown.style.width = "100%"; // ✅ Match container width
      return;
    }

    // Calculate positions
    const inputRect = targetElement.getBoundingClientRect();
    const modalBodyRect = modalBody.getBoundingClientRect();
    
    // Check for modal footer and other elements
    const modalFooter = modalContainer.querySelector(".nx-modal-footer");
    const modalContent = modalContainer.querySelector(".nx-modal-content");

    // Calculate available space
    let availableSpaceBelow = modalBodyRect.bottom - inputRect.bottom;
    let availableSpaceAbove = inputRect.top - modalBodyRect.top;

    // Account for footer
    if (modalFooter) {
      const footerRect = modalFooter.getBoundingClientRect();
      const footerSpace = footerRect.top - inputRect.bottom;
      availableSpaceBelow = Math.min(availableSpaceBelow, footerSpace);
    }

    // Account for modal content padding
    if (modalContent) {
      const contentRect = modalContent.getBoundingClientRect();
      const contentSpace = contentRect.bottom - inputRect.bottom;
      availableSpaceBelow = Math.min(availableSpaceBelow, contentSpace);
    }

    const dropdownHeight = 200; // max-height from CSS
    const minSpaceRequired = 120;

    // Check if dropdown would be cut off
    const testDropdownBottom = inputRect.bottom + dropdownHeight;
    const wouldBeCutOff = testDropdownBottom > modalBodyRect.bottom;
    const overflowAmount = testDropdownBottom - modalBodyRect.bottom;
    const allowOverflow = overflowAmount < 30;

    // Check footer overlap
    const footerOverlap = modalFooter
      ? Math.max(0, inputRect.bottom + dropdownHeight - modalFooter.getBoundingClientRect().top)
      : 0;
    const hasFooterOverlap = footerOverlap > 0;

    // Apply positioning - ensure width matches container (like addSuggestionsStyles)
    dropdown.style.position = "absolute";
    dropdown.style.left = "0px";
    dropdown.style.right = "0px";
    dropdown.style.width = "100%"; // ✅ Use 100% width to match container width
    dropdown.style.setProperty("z-index", "2147483647", "important");
    dropdown.style.visibility = "visible";
    dropdown.style.opacity = "1";

    // Position above or below based on available space
    if ((wouldBeCutOff && !allowOverflow) || hasFooterOverlap) {
      if (availableSpaceAbove > 50) {
        // Position above
        const heightAbove = Math.min(dropdownHeight, availableSpaceAbove - 5);
        dropdown.style.top = "-" + heightAbove + "px";
        dropdown.style.maxHeight = heightAbove + "px";
        dropdown.style.left = "0px";
        dropdown.style.right = "0px";
        dropdown.style.width = "100%"; // ✅ Match container width
        dropdown.classList.add("nexa-dropdown-above");
      } else {
        // Position below with limited height
        const maxSafeHeight = Math.max(150, availableSpaceBelow - 5);
        dropdown.style.top = wrapper.offsetHeight + "px";
        dropdown.style.maxHeight = maxSafeHeight + "px";
        dropdown.style.left = "0px";
        dropdown.style.right = "0px";
        dropdown.style.width = "100%"; // ✅ Match container width
        dropdown.classList.remove("nexa-dropdown-above");
      }
    } else {
      // Normal positioning below
      dropdown.style.top = wrapper.offsetHeight + "px";
      dropdown.style.maxHeight = "200px";
      dropdown.style.left = "0px";
      dropdown.style.right = "0px";
      dropdown.style.width = "100%"; // ✅ Match container width
      dropdown.classList.remove("nexa-dropdown-above");
    }

    // Final force visibility
    dropdown.style.display = "block";
    dropdown.style.visibility = "visible";
    dropdown.style.opacity = "1";
    dropdown.style.setProperty("z-index", "2147483647", "important");
  }

  highlightSuggestion(suggestions) {
    suggestions.forEach((item, index) => {
      if (index === this.selectedIndex) {
        item.style.backgroundColor = "#e3f2fd";
        item.scrollIntoView({ block: "nearest", behavior: "smooth" });
      } else {
        item.style.backgroundColor = "transparent";
      }
    });
  }

  canAddTag() {
    // Helper method untuk cek apakah bisa menambah tag
    if (this.maxTags === null || this.maxTags === undefined) {
      return true; // Tidak ada batasan
    }
    return this.tags.length < this.maxTags;
  }

  addTag(failedValue, container) {
    if (!failedValue) {
      return false;
    }

    // Validasi 1: Cek batas maksimal tag - PRIORITAS UTAMA (sebelum semua cek lainnya)
    if (this.maxTags !== null && this.maxTags !== undefined) {
      if (this.tags.length >= this.maxTags) {
        this.showLimitMessage();
        return false;
      }
    }

    // Validasi 2: Cek duplikasi
    const exists = this.tags.some(item => item.failed === failedValue);
    if (exists) {
      return false;
    }

    // Validasi 3: Cek apakah ada di database (skip jika free input mode)
    if (!this.allowFreeInput) {
      const inDatabase = this.database.some(item => item.failed === failedValue);
      if (!inDatabase) {
        return false;
      }
    }

    // Validasi 4: Double check batas maksimal sebelum benar-benar menambah (prevent race condition)
    if (this.maxTags !== null && this.maxTags !== undefined) {
      if (this.tags.length >= this.maxTags) {
        this.showLimitMessage();
        return false;
      }
    }

    // Tambahkan tag baru
    this.tags.push({ failed: failedValue });
    
    const chip = this.createChip(failedValue, this.tags.length - 1, container);
    container.appendChild(chip);

    // Update dropdown setelah menambah tag
    const targetElement = document.getElementById(this.targetId);
    const dropdown = targetElement.parentElement.querySelector(".nexa-tags-dropdown");
    if (dropdown && targetElement.value) {
      this.searchAndShowSuggestions(targetElement.value.toLowerCase(), dropdown);
    } else if (dropdown) {
      // Update dropdown untuk cek apakah sudah mencapai limit
      this.searchAndShowSuggestions("", dropdown);
    }

    // Trigger custom event
    this.dispatchChangeEvent();
    return true;
  }

  showLimitMessage() {
    const targetElement = document.getElementById(this.targetId);
    if (!targetElement) return;

    // Buat atau update pesan limit
    let limitMessage = targetElement.parentElement.querySelector(".nexa-tags-limit-message");
    
    if (!limitMessage) {
      limitMessage = document.createElement("div");
      limitMessage.className = "nexa-tags-limit-message";
      limitMessage.style.cssText = `
        position: absolute;
        top: -35px;
        left: 0;
        padding: 6px 12px;
        background: #ff9800;
        color: white;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        z-index: 1001;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        animation: slideDown 0.3s ease;
      `;
      targetElement.parentElement.style.position = "relative";
      targetElement.parentElement.appendChild(limitMessage);
    }

    limitMessage.textContent = `Maksimal ${this.maxTags} tag yang dapat dipilih`;
    limitMessage.style.display = "block";

    // Hide setelah 3 detik
    setTimeout(() => {
      if (limitMessage) {
        limitMessage.style.animation = "slideUp 0.3s ease";
        setTimeout(() => {
          limitMessage.style.display = "none";
        }, 300);
      }
    }, 3000);
  }

  removeTag(index, container) {
    if (index >= 0 && index < this.tags.length) {
      this.tags.splice(index, 1);
      this.renderChips(container);
      
      // Update dropdown setelah menghapus tag
      const targetElement = document.getElementById(this.targetId);
      const dropdown = targetElement.parentElement.querySelector(".nexa-tags-dropdown");
      if (dropdown && targetElement.value) {
        this.searchAndShowSuggestions(targetElement.value.toLowerCase(), dropdown);
      }
      
      this.dispatchChangeEvent();
    }
  }

  getTags() {
    return this.tags;
  }

  setTags(newTags) {
    this.tags = [...newTags];
    const container = document.querySelector(`#${this.targetId}`).parentElement.querySelector(".nexa-tags-chips");
    if (container) {
      this.renderChips(container);
    }
  }

  getValue() {
    // Return string tags yang dipisahkan pipe
    return this.tags.map(tag => tag.failed).join("|");
  }

  updateValue() {
    // Cari atau buat output element
    let outputElement = document.getElementById(this.outputId);
    
    if (!outputElement) {
      const targetElement = document.getElementById(this.targetId);
      if (targetElement && targetElement.parentNode) {
        outputElement = document.createElement("input");
        outputElement.type = "hidden";
        outputElement.id = this.outputId;
        outputElement.name = this.outputId;
        targetElement.parentNode.appendChild(outputElement);
      }
    }
    
    if (outputElement) {
      const value = this.getValue();
      outputElement.value = value;
      
      // Juga set sebagai attribute
      outputElement.setAttribute("value", value);
    }
  }

  dispatchChangeEvent() {
    const targetElement = document.getElementById(this.targetId);
    
    // Update value
    this.updateValue();
    
    // Ambil name attribute
    const nameAttr = targetElement ? targetElement.getAttribute("id") : null;
    
    // Data untuk callback dan event
    const changeData = {
      tags: this.tags,
      value: this.getValue(),
      array: this.tags.map(tag => tag.failed),
      name: nameAttr || this.targetId,
      targetId: this.targetId,
      outputId: this.outputId
    };
    
    // Panggil callback jika ada
    if (this.onChange && typeof this.onChange === "function") {
      try {
        this.onChange(changeData);
      } catch (error) {
        // Silent error handling
      }
    }
    
    // Dispatch custom event
    if (targetElement) {
      const event = new CustomEvent("tagsChange", {
        detail: changeData
      });
      targetElement.dispatchEvent(event);
    }
  }
}

