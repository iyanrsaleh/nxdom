class NexaChild {
  constructor() {
    this.isEditing = false;
    this.currentEditingElement = null;
    this.currentInputElement = null;
    this.currentSaveFunction = null;
  }

  // Method untuk edit text
  editText(element) {
    // Cek apakah element disabled atau readonly
    if (element.hasAttribute("disabled") || element.hasAttribute("readonly")) {
      return;
    }

    // Jika sedang edit element lain, paksa save atau cancel dulu
    if (this.isEditing && this.currentEditingElement !== element) {
      this.forceSaveOrCancel();
    }

    // Jika sudah edit element yang sama, return
    if (this.isEditing && this.currentEditingElement === element) {
      return;
    }

    this.isEditing = true;
    this.currentEditingElement = element;
    const inputType = element.getAttribute("type") || "text";
    const label = element.getAttribute("label") || "";
    
    // Untuk tags dan tags-input, ambil nilai dari data-tags attribute jika ada, karena textContent sudah berubah menjadi chips
    let originalText;
    if (inputType === "tags" || inputType === "tags-input") {
      // Coba ambil dari data-tags attribute dulu
      originalText = element.getAttribute("data-tags") || "";
      
      // Jika data-tags kosong, coba parse dari textContent (chips)
      if (!originalText || originalText === "Null" || originalText === "null") {
        // Coba ambil dari chips yang sudah ada
        const chips = element.querySelectorAll(".nexa-tag-chip");
        if (chips.length > 0) {
          originalText = Array.from(chips).map(chip => chip.textContent.trim()).join(", ");
        } else {
          // Fallback ke textContent jika tidak ada chips
          originalText = element.textContent.trim();
          // Bersihkan placeholder text
          if (originalText === "-- Tidak ada tags --" || originalText === "Null" || originalText === "null") {
            originalText = "";
          }
        }
      }
    } else {
      originalText = element.textContent;
    }

    // Simpan nilai original untuk perbandingan
    element.setAttribute("data-original-text", originalText);

    // Cek apakah element sudah dalam display container
    const displayContainer = element.parentNode.classList.contains(
      "nexa-display-container"
    )
      ? element.parentNode
      : null;

    let inputElement;
    let isSaved = false; // Flag untuk mencegah double save

    // Buat input berdasarkan type
    if (inputType === "select") {
      inputElement = this.createSelectInput(element, originalText);
    } else if (inputType === "tags") {
      inputElement = this.createTagsInput(element, originalText);
    } else if (inputType === "tags-input") {
      // Type baru: input text biasa untuk mengetik tags
      inputElement = this.createTagsTextInput(element, originalText);
    } else if (inputType === "textarea") {
      inputElement = this.createTextareaInput(originalText);
    } else if (inputType === "checkbox") {
      inputElement = this.createCheckboxInput(element, originalText);
    } else if (inputType === "radio") {
      inputElement = this.createRadioInput(element, originalText);
    } else {
      inputElement = this.createTextInput(inputType, originalText);
    }

    // Simpan referensi input element
    this.currentInputElement = inputElement;

    // Handle replacement - jika ada display container, ganti hanya element di dalamnya
    if (inputType === "checkbox" || inputType === "radio") {
      // Untuk checkbox dan radio, tidak perlu replacement karena kita hanya mengubah nilai input yang sudah ada
      // inputElement sudah di-set ke element asli di createCheckboxInput/createRadioInput
    } else if (inputType === "tags") {
      // Untuk tags, perlu replacement tapi tidak perlu focus/select
      if (displayContainer) {
        const elementInContainer = displayContainer.querySelector(".editable");
        if (elementInContainer) {
          displayContainer.replaceChild(inputElement, elementInContainer);
        }
      } else {
        element.parentNode.replaceChild(inputElement, element);
      }
    } else if (inputType === "tags-input") {
      // Untuk tags-input, ganti element dan focus pada input
      if (displayContainer) {
        const elementInContainer = displayContainer.querySelector(".editable");
        if (elementInContainer) {
          displayContainer.replaceChild(inputElement, elementInContainer);
        }
      } else {
        element.parentNode.replaceChild(inputElement, element);
      }
      inputElement.focus();
      inputElement.select();
    } else {
      if (displayContainer) {
        // Jika ada display container, ganti hanya element (biarkan label tetap tampil)
        const elementInContainer = displayContainer.querySelector(".editable");
        if (elementInContainer) {
          displayContainer.replaceChild(inputElement, elementInContainer);
        }
      } else {
        // Tidak ada display container, ganti element langsung
        element.parentNode.replaceChild(inputElement, element);
      }

      inputElement.focus();

      // Select text untuk input biasa
      if (inputType !== "select" && inputType !== "textarea") {
        inputElement.select();
      }
    }

    // Function untuk save
    const saveEdit = () => {
      if (isSaved) return; // Cegah double save
      isSaved = true;

      // Ambil nilai berdasarkan type input
      let newText;
      if (inputType === "checkbox") {
        // Untuk checkbox, ambil dari checkbox input di dalam element
        const checkbox = inputElement.querySelector("input[type='checkbox']");
        newText = checkbox ? (checkbox.checked ? "true" : "false") : "false";
      } else if (inputType === "radio") {
        // Untuk radio, ambil dari radio input di dalam element
        const radio = inputElement.querySelector("input[type='radio']");
        newText = radio ? (radio.checked ? "true" : "false") : "false";
      } else if (inputType === "select") {
        // Untuk select dengan Select2, ambil nilai dari Select2
        if (
          window.jQuery &&
          window.jQuery(inputElement).hasClass("select2-hidden-accessible")
        ) {
          newText = window.jQuery(inputElement).val() || "";
        } else {
          newText = inputElement.value.trim();
        }
      } else if (inputType === "tags-input") {
        // Untuk tags-input, ambil nilai text dan normalisasi format (hapus spasi berlebih)
        const rawText = inputElement.value.trim();
        if (rawText) {
          // Normalisasi: pisah dengan koma, trim setiap tag, filter yang kosong, gabung lagi
          const tags = rawText.split(",").map(t => t.trim()).filter(t => t.length > 0);
          newText = tags.join(", ");
        } else {
          newText = "";
        }
      } else if (inputType === "tags") {
        // Untuk tags dengan Select2 multiple, ambil array dan gabungkan dengan koma
        if (
          window.jQuery &&
          window.jQuery(inputElement).hasClass("select2-hidden-accessible")
        ) {
          const selectedValues = window.jQuery(inputElement).val() || [];
          newText = Array.isArray(selectedValues) ? selectedValues.join(", ") : selectedValues;
        } else {
          // Fallback untuk select multiple biasa
          const selectedOptions = Array.from(inputElement.selectedOptions);
          newText = selectedOptions.map(opt => opt.value).join(", ");
        }
      } else {
        newText = inputElement.value.trim();
      }

      // Cek apakah inputElement masih ada di DOM
      if (!inputElement.parentNode) {
        this.resetEditingState();
        return;
      }

      // Cek apakah nilai benar-benar berubah
      if (newText === originalText.trim()) {
        // Untuk checkbox, radio, tags, dan tags-input tidak perlu replacement
        if (inputType !== "checkbox" && inputType !== "radio" && inputType !== "tags" && inputType !== "tags-input" && inputElement.parentNode) {
          inputElement.parentNode.replaceChild(element, inputElement);
        } else if (inputType === "tags" || inputType === "tags-input") {
          // Untuk tags dan tags-input, tetap update display dan data-tags meskipun tidak ada perubahan
          element.setAttribute("data-tags", newText);
          this.updateTagsDisplay(element, newText);
          
          // Untuk tags-input, perlu replace element
          if (inputType === "tags-input" && inputElement.parentNode) {
            const displayContainer = inputElement.parentNode.classList.contains(
              "nexa-display-container"
            )
              ? inputElement.parentNode
              : null;
            
            if (displayContainer) {
              displayContainer.replaceChild(element, inputElement);
            } else {
              inputElement.parentNode.replaceChild(element, inputElement);
            }
          }
        }
        this.resetEditingState();
        return;
      }

      // Validasi berdasarkan type
      if (this.validateInput(newText, inputType, element)) {
        // Sembunyikan error jika ada
        this.hideValidationError();

        // Untuk checkbox, radio, tags, dan tags-input jangan ubah textContent karena akan menghapus child elements
        if (inputType !== "checkbox" && inputType !== "radio" && inputType !== "tags" && inputType !== "tags-input") {
          element.textContent = newText;
        } else if (inputType === "tags" || inputType === "tags-input") {
          // Untuk tags (dropdown), tutup Select2 dan destroy sebelum update tampilan
          if (
            inputType === "tags" &&
            window.jQuery &&
            window.jQuery(inputElement).hasClass("select2-hidden-accessible")
          ) {
            // Tutup dropdown terlebih dahulu
            window.jQuery(inputElement).select2("close");
            
            // Hapus event listeners dengan namespace
            window.jQuery(inputElement).off("change.select2-nexa");
            window.jQuery(inputElement).off("select2:select.select2-nexa");
            window.jQuery(inputElement).off("select2:close.select2-nexa");
            window.jQuery(inputElement).off("select2:unselect.select2-nexa");
            window.jQuery(inputElement).off("select2:select.select2-nexa-enter");

            // Hapus event listener dari search input dan container
            const select2Container = window
              .jQuery(inputElement)
              .next(".select2-container");
            if (select2Container.length > 0) {
              const searchInput = select2Container.find(".select2-search__field");
              if (searchInput.length > 0) {
                searchInput.off("keydown.select2-nexa");
                searchInput.off("keyup.select2-nexa");
                // Hapus juga event listener dari DOM element jika ada
                const searchInputDom = searchInput[0];
                if (searchInputDom) {
                  // Handler sudah dihapus di level jQuery, tidak perlu hapus lagi dari DOM
                }
              }
              select2Container.off("click.select2-nexa");
              select2Container.off("blur.select2-nexa");
            }
            
            // Hapus document level handler jika ada
            if (inputElement._nexaEnterHandler) {
              document.removeEventListener("keydown", inputElement._nexaEnterHandler, true);
              delete inputElement._nexaEnterHandler;
            }

            // Destroy Select2
            window.jQuery(inputElement).select2("destroy");
            
            // Setelah destroy, pastikan inputElement dihapus dari DOM
            // karena untuk tags kita tidak melakukan replace di bawah
            if (inputElement.parentNode) {
              // Ganti inputElement dengan element asli
              const displayContainer = inputElement.parentNode.classList.contains(
                "nexa-display-container"
              )
                ? inputElement.parentNode
                : null;
              
              if (displayContainer) {
                // Jika ada display container, ganti hanya input dengan element
                displayContainer.replaceChild(element, inputElement);
              } else {
                // Tidak ada display container, ganti input dengan element langsung
                inputElement.parentNode.replaceChild(element, inputElement);
              }
            }
          }
          
          // Update tampilan chips dan simpan nilai di data attribute
          element.setAttribute("data-tags", newText);
          this.updateTagsDisplay(element, newText);
        }

        const fieldName = element.getAttribute("name") || "unknown";

        // Callback jika ada
        if (this.onSave) {
          this.onSave(element.id, newText, element, inputType, fieldName);
        }

        // Replace hanya jika masih ada di DOM dan bukan checkbox/radio/tags/tags-input
        if (
          inputType !== "checkbox" &&
          inputType !== "radio" &&
          inputType !== "tags" &&
          inputType !== "tags-input" &&
          inputElement.parentNode
        ) {
          // Untuk select dengan Select2, cleanup event listeners dan destroy Select2 sebelum replace
          if (
            (inputType === "select" || inputType === "tags") &&
            window.jQuery &&
            window.jQuery(inputElement).hasClass("select2-hidden-accessible")
          ) {
            // Hapus event listeners dengan namespace
            window.jQuery(inputElement).off("change.select2-nexa");
            window.jQuery(inputElement).off("select2:select.select2-nexa");
            window.jQuery(inputElement).off("select2:close.select2-nexa");

            // Hapus event listener dari search input dan container
            const select2Container = window
              .jQuery(inputElement)
              .next(".select2-container");
            if (select2Container.length > 0) {
              const searchInput = select2Container.find(
                ".select2-search__field"
              );
              if (searchInput.length > 0) {
                searchInput.off("keydown.select2-nexa");
              }
              select2Container.off("click.select2-nexa");
            }

            window.jQuery(inputElement).select2("destroy");
          }

          // Cek apakah input ada dalam display container
          const displayContainer = inputElement.parentNode.classList.contains(
            "nexa-display-container"
          )
            ? inputElement.parentNode
            : null;

          if (displayContainer) {
            // Jika ada display container, ganti hanya input dengan element
            displayContainer.replaceChild(element, inputElement);
          } else {
            // Tidak ada display container, ganti input dengan element langsung
            inputElement.parentNode.replaceChild(element, inputElement);
          }
        } else if (inputType === "tags-input") {
          // Untuk tags-input, perlu replace element setelah save
          if (inputElement.parentNode) {
            const displayContainer = inputElement.parentNode.classList.contains(
              "nexa-display-container"
            )
              ? inputElement.parentNode
              : null;
            
            if (displayContainer) {
              displayContainer.replaceChild(element, inputElement);
            } else {
              inputElement.parentNode.replaceChild(element, inputElement);
            }
          }
        }
        this.resetEditingState();
      } else {
        // Jika validasi gagal, jangan replace element, biarkan user memperbaiki
        isSaved = false; // Reset flag agar bisa dicoba lagi
        return;
      }
    };

    // Function untuk cancel
    const cancelEdit = () => {
      if (isSaved) return; // Cegah cancel setelah save
      isSaved = true;

      // Untuk checkbox dan radio, kembalikan nilai input ke original
      if (inputType === "checkbox") {
        const checkbox = element.querySelector("input[type='checkbox']");
        if (checkbox) {
          const isChecked =
            originalText.toLowerCase() === "true" ||
            originalText === "1" ||
            originalText.toLowerCase() === "yes" ||
            originalText.toLowerCase() === "ya" ||
            originalText.toLowerCase() === "on";
          checkbox.checked = isChecked;
        }
      } else if (inputType === "radio") {
        const radio = element.querySelector("input[type='radio']");
        if (radio) {
          const isChecked =
            originalText.toLowerCase() === "true" ||
            originalText === "1" ||
            originalText.toLowerCase() === "yes" ||
            originalText.toLowerCase() === "ya" ||
            originalText.toLowerCase() === "on";
          radio.checked = isChecked;
        }
      } else if (inputType === "select" || inputType === "tags") {
        // Untuk select/tags dengan Select2, cleanup event listeners dan destroy Select2 sebelum replace
        if (
          window.jQuery &&
          window.jQuery(inputElement).hasClass("select2-hidden-accessible")
        ) {
          // Hapus event listeners dengan namespace
          window.jQuery(inputElement).off("change.select2-nexa");
          window.jQuery(inputElement).off("select2:select.select2-nexa");
          window.jQuery(inputElement).off("select2:close.select2-nexa");
          window.jQuery(inputElement).off("select2:unselect.select2-nexa");

          // Hapus event listener dari search input dan container
          const select2Container = window
            .jQuery(inputElement)
            .next(".select2-container");
          if (select2Container.length > 0) {
            const searchInput = select2Container.find(".select2-search__field");
            if (searchInput.length > 0) {
              searchInput.off("keydown.select2-nexa");
            }
            select2Container.off("click.select2-nexa");
            select2Container.off("blur.select2-nexa");
          }

          window.jQuery(inputElement).select2("destroy");
        }
        if (inputType === "tags") {
          this.updateTagsDisplay(element, originalText);
        } else {
          element.textContent = originalText;
        }
      } else if (inputType === "tags-input") {
        // Untuk tags-input, update tampilan chips dengan original text
        this.updateTagsDisplay(element, originalText);
      } else {
        element.textContent = originalText;
      }

      // Replace hanya jika masih ada di DOM dan bukan checkbox/radio/tags/tags-input
      if (
        inputType !== "checkbox" &&
        inputType !== "radio" &&
        inputType !== "tags" &&
        inputType !== "tags-input" &&
        inputElement.parentNode
      ) {
        // Cek apakah input ada dalam display container
        const displayContainer = inputElement.parentNode.classList.contains(
          "nexa-display-container"
        )
          ? inputElement.parentNode
          : null;

        if (displayContainer) {
          // Jika ada display container, ganti hanya input dengan element
          displayContainer.replaceChild(element, inputElement);
        } else {
          // Tidak ada display container, ganti input dengan element langsung
          inputElement.parentNode.replaceChild(element, inputElement);
        }
      }
      this.resetEditingState();
    };

    // Simpan fungsi save untuk digunakan nanti
    this.currentSaveFunction = saveEdit;

    // Event listeners (kecuali untuk checkbox, radio, dan tags dropdown)
    if (inputType !== "checkbox" && inputType !== "radio" && inputType !== "tags") {
      inputElement.addEventListener("blur", saveEdit);
      inputElement.addEventListener("keydown", (e) => {
        // Untuk textarea, Ctrl+Enter untuk save, Escape untuk cancel
        if (inputType === "textarea") {
          if (e.key === "Enter" && e.ctrlKey) {
            e.preventDefault();
            saveEdit();
          }
          if (e.key === "Escape") {
            e.preventDefault();
            cancelEdit();
          }
        } else if (inputType === "tags-input") {
          // Untuk tags-input, Enter untuk save, Escape untuk cancel
          // Tapi biarkan koma berfungsi normal untuk pemisah
          if (e.key === "Enter") {
            e.preventDefault();
            saveEdit();
          }
          if (e.key === "Escape") {
            e.preventDefault();
            cancelEdit();
          }
        } else {
          if (e.key === "Enter") {
            saveEdit();
          }
          if (e.key === "Escape") {
            cancelEdit();
          }
        }
      });
    }

    // Untuk select, gunakan change event tapi hapus blur
    if (inputType === "select") {
      inputElement.removeEventListener("blur", saveEdit);
      inputElement.addEventListener("change", saveEdit);

      // Tambahkan event listener untuk Select2 setelah initialization
      setTimeout(() => {
        if (
          window.jQuery &&
          window.jQuery(inputElement).hasClass("select2-hidden-accessible")
        ) {
          // Hapus event listener sebelumnya untuk menghindari duplikasi
          window.jQuery(inputElement).off("change.select2-nexa");
          window.jQuery(inputElement).off("select2:select.select2-nexa");
          window.jQuery(inputElement).off("select2:close.select2-nexa");

          // Event untuk Select2 change dengan namespace
          window.jQuery(inputElement).on("change.select2-nexa", saveEdit);

          // Event untuk Select2 select (ketika item dipilih)
          window
            .jQuery(inputElement)
            .on("select2:select.select2-nexa", saveEdit);

          // Event untuk Escape key pada Select2
          window.jQuery(inputElement).on("select2:close.select2-nexa", (e) => {
            // Jika Select2 ditutup tanpa memilih (Escape), cancel edit
            setTimeout(() => {
              if (!isSaved) {
                cancelEdit();
              }
            }, 100);
          });

          // Tambahkan event listener untuk Enter key pada search input Select2
          const select2Container = window
            .jQuery(inputElement)
            .next(".select2-container");
          if (select2Container.length > 0) {
            const searchInput = select2Container.find(".select2-search__field");
            if (searchInput.length > 0) {
              searchInput.off("keydown.select2-nexa");
              searchInput.on("keydown.select2-nexa", (e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  // Jika ada hasil pencarian, pilih yang pertama
                  const results = select2Container.find(
                    ".select2-results__option--highlighted"
                  );
                  if (results.length > 0) {
                    results.trigger("mouseup");
                  } else {
                    // Jika tidak ada hasil, tutup dropdown dan save
                    window.jQuery(inputElement).select2("close");
                    setTimeout(saveEdit, 100);
                  }
                }
              });
            }
          }

          // Tambahkan event listener untuk klik pada dropdown Select2
          select2Container.off("click.select2-nexa");
          select2Container.on("click.select2-nexa", (e) => {
            // Jika klik pada option yang dipilih, langsung save
            if (window.jQuery(e.target).hasClass("select2-results__option")) {
              setTimeout(saveEdit, 50);
            }
          });
        }
      }, 150); // Delay lebih lama untuk memastikan Select2 sudah terinisialisasi
    }

    // Untuk tags, gunakan change event dan tidak auto-save (user harus klik di luar atau tekan Escape)
    if (inputType === "tags") {
      inputElement.removeEventListener("blur", saveEdit);
      
      // Flag untuk menandai Enter ditekan
      let enterKeyPressed = false;
      
      // Tambahkan event listener untuk Select2 setelah initialization
      setTimeout(() => {
        if (
          window.jQuery &&
          window.jQuery(inputElement).hasClass("select2-hidden-accessible")
        ) {
          // Hapus event listener sebelumnya untuk menghindari duplikasi
          window.jQuery(inputElement).off("change.select2-nexa");
          window.jQuery(inputElement).off("select2:select.select2-nexa");
          window.jQuery(inputElement).off("select2:unselect.select2-nexa");
          window.jQuery(inputElement).off("select2:close.select2-nexa");

          // Event untuk Select2 change (tidak auto-save, hanya update tampilan)
          window.jQuery(inputElement).on("change.select2-nexa", () => {
            // Update tampilan tags saat ini (tidak save)
          });

          // Event untuk Escape key pada Select2 - cancel edit
          window.jQuery(inputElement).on("select2:close.select2-nexa", (e) => {
            setTimeout(() => {
              if (!isSaved) {
                // Jika Enter ditekan, langsung save
                if (enterKeyPressed) {
                  enterKeyPressed = false;
                  saveEdit();
                  return;
                }
                
                // Cek apakah user menekan Escape atau klik di luar
                const select2Container = window
                  .jQuery(inputElement)
                  .next(".select2-container");
                if (select2Container.length > 0 && !select2Container.find(".select2-search__field").is(":focus")) {
                  // Jika tidak fokus, mungkin user klik di luar - save
                  saveEdit();
                }
              }
            }, 100);
          });

          // Tambahkan event listener untuk blur pada container Select2
          const select2Container = window
            .jQuery(inputElement)
            .next(".select2-container");
          if (select2Container.length > 0) {
            // Save ketika klik di luar Select2
            select2Container.on("blur.select2-nexa", () => {
              setTimeout(() => {
                if (!isSaved && document.activeElement !== inputElement) {
                  saveEdit();
                }
              }, 200);
            });

            // Tambahkan event listener untuk Enter key pada search input Select2
            const searchInput = select2Container.find(".select2-search__field");
            if (searchInput.length > 0) {
              // Hapus handler sebelumnya
              searchInput.off("keydown.select2-nexa");
              searchInput.off("keyup.select2-nexa");
              
              // Dapatkan DOM element asli (bukan jQuery object)
              const searchInputDom = searchInput[0];
              
              // Handler untuk Enter key
              const handleEnterKey = (e) => {
                if (e.key === "Enter" || e.keyCode === 13) {
                  e.preventDefault();
                  e.stopPropagation();
                  e.stopImmediatePropagation();
                  
                  // Cek apakah sudah ada tag yang dipilih
                  const selectedValues = window.jQuery(inputElement).val() || [];
                  const hasSelectedTags = Array.isArray(selectedValues) && selectedValues.length > 0;
                  
                  if (hasSelectedTags) {
                    // Jika sudah ada tag yang dipilih, langsung tutup dan save tanpa perlu memilih lagi
                    window.jQuery(inputElement).select2("close");
                    setTimeout(() => {
                      if (!isSaved) {
                        saveEdit();
                      }
                    }, 100);
                  } else {
                    // Jika belum ada tag yang dipilih, cek apakah ada hasil pencarian yang di-highlight
                    const results = select2Container.find(".select2-results__option--highlighted");
                    if (results.length > 0) {
                      // Pilih option yang di-highlight
                      results.trigger("mouseup");
                      // Setelah memilih, tutup dropdown dan save
                      setTimeout(() => {
                        window.jQuery(inputElement).select2("close");
                        setTimeout(() => {
                          if (!isSaved) {
                            saveEdit();
                          }
                        }, 150);
                      }, 100);
                    } else {
                      // Jika tidak ada hasil yang di-highlight, tutup dropdown dan save langsung
                      window.jQuery(inputElement).select2("close");
                      setTimeout(() => {
                        if (!isSaved) {
                          saveEdit();
                        }
                      }, 150);
                    }
                  }
                  return false;
                } else if (e.key === "Escape") {
                  e.preventDefault();
                  e.stopPropagation();
                  cancelEdit();
                }
              };
              
              // Tambahkan handler dengan jQuery (untuk kompatibilitas)
              searchInput.on("keydown.select2-nexa", handleEnterKey);
              
              // Tambahkan juga handler langsung pada DOM dengan capture phase (lebih agresif)
              if (searchInputDom) {
                // Hapus listener sebelumnya jika ada
                searchInputDom.removeEventListener("keydown", handleEnterKey, true);
                // Tambahkan dengan capture: true untuk menangkap event sebelum Select2
                searchInputDom.addEventListener("keydown", handleEnterKey, true);
              }
              
              // Juga tambahkan handler dengan keyup sebagai backup
              searchInput.on("keyup.select2-nexa", (e) => {
                if (e.key === "Enter" || e.keyCode === 13) {
                  // Backup handler jika keydown tidak terdeteksi
                  const selectedValues = window.jQuery(inputElement).val() || [];
                  const hasSelectedTags = Array.isArray(selectedValues) && selectedValues.length > 0;
                  const isDropdownOpen = select2Container.find(".select2-dropdown").is(":visible");
                  
                  // Jika sudah ada tag yang dipilih atau dropdown sudah tertutup, langsung save
                  if ((hasSelectedTags || !isDropdownOpen) && !isSaved) {
                    setTimeout(() => {
                      if (!isSaved) {
                        saveEdit();
                      }
                    }, 100);
                  }
                }
              });
            }
            
            // Tambahkan juga handler pada select2:select untuk menangkap ketika item dipilih dengan Enter
            window.jQuery(inputElement).off("select2:select.select2-nexa-enter");
            window.jQuery(inputElement).on("select2:select.select2-nexa-enter", (e) => {
              // Ketika item dipilih, tunggu sebentar lalu tutup dan save
              setTimeout(() => {
                const isDropdownOpen = select2Container.find(".select2-dropdown").is(":visible");
                if (!isDropdownOpen) {
                  // Jika dropdown sudah tertutup, langsung save
                  if (!isSaved) {
                    saveEdit();
                  }
                }
              }, 200);
            });
            
            // Tambahkan handler pada document level untuk menangkap Enter key ketika fokus di search input
            const documentEnterHandler = (e) => {
              // Cek apakah fokus ada di search input Select2 untuk tags ini
              const activeElement = document.activeElement;
              const searchInputDom = select2Container.find(".select2-search__field")[0];
              
              if (activeElement === searchInputDom && (e.key === "Enter" || e.keyCode === 13)) {
                // Cek apakah sudah ada tag yang dipilih
                const selectedValues = window.jQuery(inputElement).val() || [];
                const hasSelectedTags = Array.isArray(selectedValues) && selectedValues.length > 0;
                
                if (hasSelectedTags) {
                  // Jika sudah ada tag yang dipilih, langsung tutup dan save
                  e.preventDefault();
                  e.stopPropagation();
                  window.jQuery(inputElement).select2("close");
                  setTimeout(() => {
                    if (!isSaved) {
                      saveEdit();
                    }
                  }, 100);
                }
              }
            };
            
            // Tambahkan event listener pada document dengan capture
            document.addEventListener("keydown", documentEnterHandler, true);
            
            // Simpan reference untuk cleanup nanti
            if (!inputElement._nexaEnterHandler) {
              inputElement._nexaEnterHandler = documentEnterHandler;
            }
          }
        }
      }, 150);
    }

    // Untuk checkbox, gunakan change event
    if (inputType === "checkbox") {
      const checkbox = inputElement.querySelector("input[type='checkbox']");
      if (checkbox) {
        checkbox.addEventListener("change", saveEdit);
      }
    }

    // Untuk radio, gunakan change event
    if (inputType === "radio") {
      const radio = inputElement.querySelector("input[type='radio']");
      if (radio) {
        radio.addEventListener("change", saveEdit);
      }
    }
  }

  // Method untuk save element yang sedang diedit
  saveCurrentEdit() {
    if (this.currentSaveFunction) {
      this.currentSaveFunction();
    }
  }

  // Method untuk paksa save atau cancel saat switch element
  forceSaveOrCancel() {
    if (
      !this.isEditing ||
      !this.currentInputElement ||
      !this.currentEditingElement
    ) {
      return;
    }

    // Ambil nilai saat ini berdasarkan type
    let currentValue;
    const inputType = this.currentEditingElement.getAttribute("type") || "text";

    if (inputType === "checkbox") {
      // Untuk checkbox, ambil dari checkbox input di dalam element
      const checkbox = this.currentInputElement.querySelector(
        "input[type='checkbox']"
      );
      currentValue = checkbox ? (checkbox.checked ? "true" : "false") : "false";
    } else if (inputType === "radio") {
      // Untuk radio, ambil dari radio input di dalam element
      const radio = this.currentInputElement.querySelector(
        "input[type='radio']"
      );
      currentValue = radio ? (radio.checked ? "true" : "false") : "false";
    } else if (inputType === "select") {
      // Untuk select dengan Select2, ambil nilai dari Select2
      if (
        window.jQuery &&
        window
          .jQuery(this.currentInputElement)
          .hasClass("select2-hidden-accessible")
      ) {
        currentValue = window.jQuery(this.currentInputElement).val() || "";
      } else {
        currentValue = this.currentInputElement.value.trim();
      }
    } else if (inputType === "tags") {
      // Untuk tags dengan Select2 multiple, ambil array dan gabungkan dengan koma
      if (
        window.jQuery &&
        window
          .jQuery(this.currentInputElement)
          .hasClass("select2-hidden-accessible")
      ) {
        const selectedValues = window.jQuery(this.currentInputElement).val() || [];
        currentValue = Array.isArray(selectedValues) ? selectedValues.join(", ") : selectedValues;
      } else {
        const selectedOptions = Array.from(this.currentInputElement.selectedOptions);
        currentValue = selectedOptions.map(opt => opt.value).join(", ");
      }
    } else if (inputType === "tags-input") {
      // Untuk tags-input, ambil nilai text dan normalisasi
      const rawText = this.currentInputElement.value.trim();
      if (rawText) {
        const tags = rawText.split(",").map(t => t.trim()).filter(t => t.length > 0);
        currentValue = tags.join(", ");
      } else {
        currentValue = "";
      }
    } else {
      currentValue = this.currentInputElement.value.trim();
    }
    const originalValue =
      this.currentEditingElement.getAttribute("data-original-text") ||
      this.currentEditingElement.textContent;

    // Jika ada perubahan, save tanpa validasi
    if (currentValue !== originalValue.trim()) {
      // Untuk checkbox, radio, tags, dan tags-input tidak perlu mengubah textContent
      if (inputType !== "checkbox" && inputType !== "radio" && inputType !== "tags" && inputType !== "tags-input") {
        this.currentEditingElement.textContent = currentValue;
      } else if (inputType === "tags" || inputType === "tags-input") {
        // Update data attribute dan tampilan chips
        this.currentEditingElement.setAttribute("data-tags", currentValue);
        this.updateTagsDisplay(this.currentEditingElement, currentValue);
      }

      // Callback jika ada
      if (this.onSave) {
        const fieldName =
          this.currentEditingElement.getAttribute("name") || "unknown";
        const inputType =
          this.currentEditingElement.getAttribute("type") || "text";
        this.onSave(
          this.currentEditingElement.id,
          currentValue,
          this.currentEditingElement,
          inputType,
          fieldName
        );
      }
    }

    // Hapus error jika ada (sebelum replace element)
    this.hideValidationError();

    // Kembalikan ke element asli (kecuali untuk checkbox, radio, tags, dan tags-input)
    if (
      inputType !== "checkbox" &&
      inputType !== "radio" &&
      inputType !== "tags" &&
      inputType !== "tags-input" &&
      this.currentInputElement &&
      this.currentInputElement.parentNode
    ) {
      // Untuk select/tags dengan Select2, cleanup event listeners dan destroy Select2 sebelum replace
      if (
        (inputType === "select" || inputType === "tags") &&
        window.jQuery &&
        window
          .jQuery(this.currentInputElement)
          .hasClass("select2-hidden-accessible")
      ) {
        // Hapus event listeners dengan namespace
        window.jQuery(this.currentInputElement).off("change.select2-nexa");
        window
          .jQuery(this.currentInputElement)
          .off("select2:select.select2-nexa");
        window
          .jQuery(this.currentInputElement)
          .off("select2:close.select2-nexa");
        window
          .jQuery(this.currentInputElement)
          .off("select2:unselect.select2-nexa");

        // Hapus event listener dari search input dan container
        const select2Container = window
          .jQuery(this.currentInputElement)
          .next(".select2-container");
        if (select2Container.length > 0) {
          const searchInput = select2Container.find(".select2-search__field");
          if (searchInput.length > 0) {
            searchInput.off("keydown.select2-nexa");
          }
          select2Container.off("click.select2-nexa");
          select2Container.off("blur.select2-nexa");
        }

        window.jQuery(this.currentInputElement).select2("destroy");
      }

      // Cek apakah input ada dalam display container
      const displayContainer =
        this.currentInputElement.parentNode.classList.contains(
          "nexa-display-container"
        )
          ? this.currentInputElement.parentNode
          : null;

      if (displayContainer) {
        // Jika ada display container, ganti hanya input dengan element
        displayContainer.replaceChild(
          this.currentEditingElement,
          this.currentInputElement
        );
      } else {
        // Tidak ada display container, ganti input dengan element langsung
        this.currentInputElement.parentNode.replaceChild(
          this.currentEditingElement,
          this.currentInputElement
        );
      }
    }

    // Reset state
    this.resetEditingState();
  }

  // Method untuk reset state editing
  resetEditingState() {
    this.isEditing = false;
    this.currentEditingElement = null;
    this.currentInputElement = null;
    this.currentSaveFunction = null;
  }

  // Method untuk membuat text input
  createTextInput(inputType, originalText) {
    const input = document.createElement("input");
    input.type = inputType;
    input.value = originalText;

    // Style minimal untuk input
    this.setInputStyle(input, inputType);

    return input;
  }

  // Method untuk membuat select input
  createSelectInput(element, originalText) {
    const select = document.createElement("select");

    // Set unique ID untuk Select2
    select.id = `nexa-select-${Date.now()}-${Math.random()
      .toString(36)
      .substr(2, 9)}`;

    // Style minimal untuk select
    this.setSelectStyle(select);

    // Ambil options dari data-options attribute
    const optionsData = element.getAttribute("data-options") || "";
    let options = optionsData
      .split("|")
      .filter((opt) => opt.trim())
      .map((opt) => opt.trim());

    // Jika tidak ada options, buat default
    if (options.length === 0) {
      options = ["Option 1", "Option 2", "Option 3"];
    }

    // Jika originalText tidak ada di options, tambahkan sebagai option pertama
    const currentValue = originalText.trim();
    if (currentValue && !options.includes(currentValue)) {
      options.unshift(currentValue);
    }

    // Buat option elements
    options.forEach((optionText) => {
      const option = document.createElement("option");
      option.value = optionText;
      option.textContent = optionText;

      // Set selected jika sama dengan text asli
      if (optionText === currentValue) {
        option.selected = true;
      }

      select.appendChild(option);
    });

    // Initialize Select2 setelah element ditambahkan ke DOM
    setTimeout(() => {
      if (window.NXUI && window.NXUI.initSelect2) {
        const isRequired = element.hasAttribute("required");
        const select2Options = {
          placeholder: "-- Select --",
          searchInputPlaceholder: "Cari opsi...",
          allowClear: !isRequired,
          width: "100%",
          language: {
            noResults: function () {
              return "Tidak ada hasil ditemukan";
            },
            searching: function () {
              return "Mencari...";
            },
          },
        };
        // Initialize Select2 for this specific select element
        window.NXUI.initSelect2(`#${select.id}`, select2Options);
      }
    }, 100);

    return select;
  }

  // Method untuk membuat tags text input (input text biasa untuk mengetik tags)
  createTagsTextInput(element, originalText) {
    const input = document.createElement("input");
    input.type = "text";
    input.value = originalText;
    input.placeholder = "Ketik tags dipisah koma (contoh: Tag1, Tag2, Tag3)";

    // Style minimal untuk input - menyesuaikan dengan element asli
    const computedStyle = window.getComputedStyle(element);
    input.style.fontFamily = computedStyle.fontFamily;
    input.style.fontSize = computedStyle.fontSize;
    input.style.fontWeight = computedStyle.fontWeight;
    input.style.color = computedStyle.color;
    input.style.backgroundColor = computedStyle.backgroundColor;
    input.style.border = "1px solid #ccc";
    input.style.padding = "4px 8px";
    input.style.margin = "0";
    input.style.width = "100%";
    input.style.boxSizing = "border-box";

    return input;
  }

  // Method untuk membuat tags input (multiple selection)
  createTagsInput(element, originalText) {
    const select = document.createElement("select");
    select.multiple = true;

    // Set unique ID untuk Select2
    select.id = `nexa-tags-${Date.now()}-${Math.random()
      .toString(36)
      .substr(2, 9)}`;

    // Style minimal untuk select
    this.setSelectStyle(select);

    // Ambil options dari data-options attribute
    const optionsData = element.getAttribute("data-options") || "";
    let options = optionsData
      .split("|")
      .filter((opt) => opt.trim())
      .map((opt) => opt.trim());

    // Jika tidak ada options, buat default
    if (options.length === 0) {
      options = ["Nama", "Alamat", "Email", "Telepon"];
    }

    // Parse nilai yang sudah dipilih dari originalText (format: "value1, value2, value3")
    const selectedValues = originalText
      .split(",")
      .map((val) => val.trim())
      .filter((val) => val.length > 0 && val !== "Null" && val !== "null");

    // Buat option elements
    options.forEach((optionText) => {
      const option = document.createElement("option");
      option.value = optionText;
      option.textContent = optionText;

      // Set selected jika ada di selectedValues
      if (selectedValues.includes(optionText)) {
        option.selected = true;
      }

      select.appendChild(option);
    });

    // Initialize Select2 dengan multiple mode setelah element ditambahkan ke DOM
    setTimeout(() => {
      if (window.NXUI && window.NXUI.initSelect2) {
        const isRequired = element.hasAttribute("required");
        const select2Options = {
          placeholder: "-- Pilih tags --",
          searchInputPlaceholder: "Cari atau pilih...",
          allowClear: !isRequired,
          width: "100%",
          multiple: true,
          tags: false, // Gunakan false karena kita sudah punya options
          language: {
            noResults: function () {
              return "Tidak ada hasil ditemukan";
            },
            searching: function () {
              return "Mencari...";
            },
          },
        };
        // Initialize Select2 for this specific select element
        window.NXUI.initSelect2(`#${select.id}`, select2Options);
        
        // Pastikan nilai yang sudah dipilih ter-set setelah Select2 ready
        if (selectedValues.length > 0) {
          // Set nilai setelah Select2 siap
          setTimeout(() => {
            window.jQuery(select).val(selectedValues).trigger("change");
          }, 150);
        }
      }
    }, 100);

    return select;
  }

  // Method untuk update tampilan tags sebagai chips
  updateTagsDisplay(element, tagsText) {
    // Hapus chips yang lama
    const oldChips = element.querySelectorAll(".nexa-tag-chip");
    oldChips.forEach((chip) => chip.remove());

    // Parse tags dari text (format: "tag1, tag2, tag3")
    const tags = tagsText
      .split(",")
      .map((tag) => tag.trim())
      .filter((tag) => tag.length > 0);

    // Jika tidak ada tags, tampilkan placeholder
    if (tags.length === 0) {
      element.textContent = "-- Tidak ada tags --";
      element.style.color = "#999";
      element.style.fontStyle = "italic";
      return;
    }

    // Reset style
    element.style.color = "";
    element.style.fontStyle = "";

    // Buat container untuk chips
    const chipsContainer = document.createElement("span");
    chipsContainer.style.cssText = `
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      align-items: center;
    `;

    // Buat chip untuk setiap tag
    tags.forEach((tag) => {
      const chip = document.createElement("span");
      chip.className = "nexa-tag-chip";
      chip.textContent = tag;
      chip.style.cssText = `
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        background-color: #e3f2fd;
        color: #1976d2;
        border-radius: 12px;
        font-size: 0.85rem;
        white-space: nowrap;
        border: 1px solid #90caf9;
      `;
      chipsContainer.appendChild(chip);
    });

    // Clear element dan tambahkan chips
    element.textContent = "";
    element.appendChild(chipsContainer);
  }

  // Method untuk membuat textarea input
  createTextareaInput(originalText) {
    const textarea = document.createElement("textarea");
    textarea.value = originalText;

    // Style minimal untuk textarea
    this.setTextareaStyle(textarea);

    return textarea;
  }

  // Method untuk membuat checkbox input
  createCheckboxInput(element, originalText) {
    // Untuk checkbox, kita tidak membuat element baru
    // Tapi mengubah checkbox yang sudah ada di dalam element
    const existingCheckbox = element.querySelector("input[type='checkbox']");

    if (existingCheckbox) {
      // Tentukan nilai checkbox berdasarkan originalText
      const isChecked =
        originalText.toLowerCase() === "true" ||
        originalText === "1" ||
        originalText.toLowerCase() === "yes" ||
        originalText.toLowerCase() === "ya" ||
        originalText.toLowerCase() === "on";

      existingCheckbox.checked = isChecked;

      // Return element asli yang sudah dimodifikasi
      return element;
    }

    // Jika tidak ada checkbox, return element asli
    return element;
  }

  // Method untuk membuat radio input
  createRadioInput(element, originalText) {
    // Untuk radio, kita tidak membuat element baru
    // Tapi mengubah radio yang sudah ada di dalam element
    const existingRadio = element.querySelector("input[type='radio']");

    if (existingRadio) {
      // Tentukan nilai radio berdasarkan originalText
      const isChecked =
        originalText.toLowerCase() === "true" ||
        originalText === "1" ||
        originalText.toLowerCase() === "yes" ||
        originalText.toLowerCase() === "ya" ||
        originalText.toLowerCase() === "on";

      existingRadio.checked = isChecked;

      // Return element asli yang sudah dimodifikasi
      return element;
    }

    // Jika tidak ada radio, return element asli
    return element;
  }

  // Method untuk set style berdasarkan type (minimal, tidak mengganggu UI)
  setInputStyle(input, type) {
    // Style dasar untuk semua input - menyesuaikan dengan element asli
    const originalElement = this.currentEditingElement;

    // Copy style dari element asli
    const computedStyle = window.getComputedStyle(originalElement);
    input.style.fontFamily = computedStyle.fontFamily;
    input.style.fontSize = computedStyle.fontSize;
    input.style.fontWeight = computedStyle.fontWeight;
    input.style.color = computedStyle.color;
    input.style.backgroundColor = computedStyle.backgroundColor;
    input.style.border = "1px solid #ccc";
    input.style.padding = "2px 4px";
    input.style.margin = "0";
    input.style.width = "100%";
    input.style.boxSizing = "border-box";

    // Style khusus berdasarkan type (minimal)
    switch (type) {
      case "number":
        input.style.textAlign = "right";
        break;
      case "email":
        input.style.textTransform = "lowercase";
        break;
      case "password":
        input.style.letterSpacing = "1px";
        break;
      case "tel":
        // Hapus text-align center, biarkan default (left)
        break;
      case "date":
        input.style.textAlign = "center";
        input.style.fontFamily = "monospace, " + computedStyle.fontFamily;
        break;
    }
  }

  // Method untuk set style select
  setSelectStyle(select) {
    const originalElement = this.currentEditingElement;
    const computedStyle = window.getComputedStyle(originalElement);

    select.style.fontFamily = computedStyle.fontFamily;
    select.style.fontSize = computedStyle.fontSize;
    select.style.fontWeight = computedStyle.fontWeight;
    select.style.color = computedStyle.color;
    select.style.backgroundColor = computedStyle.backgroundColor;
    select.style.border = "1px solid #ccc";
    select.style.padding = "2px 4px";
    select.style.margin = "0";
    select.style.width = "100%";
    select.style.boxSizing = "border-box";
  }

  // Method untuk set style textarea
  setTextareaStyle(textarea) {
    const originalElement = this.currentEditingElement;
    const computedStyle = window.getComputedStyle(originalElement);

    textarea.style.fontFamily = computedStyle.fontFamily;
    textarea.style.fontSize = computedStyle.fontSize;
    textarea.style.fontWeight = computedStyle.fontWeight;
    textarea.style.color = computedStyle.color;
    textarea.style.backgroundColor = computedStyle.backgroundColor;
    textarea.style.border = "1px solid #ccc";
    textarea.style.padding = "2px 4px";
    textarea.style.margin = "0";
    textarea.style.width = "100%";
    textarea.style.boxSizing = "border-box";
    textarea.style.minHeight = "60px";
    textarea.style.maxHeight = "200px";
    textarea.style.resize = "vertical";

    // Auto-resize berdasarkan content
    textarea.style.height = "auto";
    textarea.style.height = Math.min(textarea.scrollHeight, 200) + "px";
  }

  // Method untuk validasi berdasarkan type
  validateInput(value, type, element) {
    // Gunakan parameter element atau fallback ke currentEditingElement
    const targetElement = element || this.currentEditingElement;

    // Cek jika element null
    if (!targetElement) {
      return true; // Skip validasi jika tidak ada element
    }

    // Validasi required
    const isRequired = targetElement.hasAttribute("required");
    if (isRequired && (!value || value.trim() === "")) {
      this.showValidationError("Field ini wajib diisi");
      return false;
    }

    // Jika kosong dan tidak required, anggap valid
    if (!value || value.trim() === "") {
      return true;
    }

    // Validasi panjang minimum
    const minLength = targetElement.getAttribute("data-min-length");
    if (minLength && value.length < parseInt(minLength)) {
      this.showValidationError(`Minimal ${minLength} karakter`);
      return false;
    }

    // Validasi panjang maksimum
    const maxLength = targetElement.getAttribute("data-max-length");
    if (maxLength && value.length > parseInt(maxLength)) {
      this.showValidationError(`Maksimal ${maxLength} karakter`);
      return false;
    }

    // Validasi custom pattern
    const pattern = targetElement.getAttribute("data-pattern");
    if (pattern) {
      const regex = new RegExp(pattern);
      if (!regex.test(value)) {
        const patternMessage =
          targetElement.getAttribute("data-pattern-message") ||
          "Format tidak valid";
        this.showValidationError(patternMessage);
        return false;
      }
    }

    // Validasi berdasarkan type
    switch (type) {
      case "number":
        if (isNaN(value) || value === "") {
          this.showValidationError("Harus berupa angka");
          return false;
        }

        // Validasi min/max untuk number
        const min = targetElement.getAttribute("data-min");
        const max = targetElement.getAttribute("data-max");
        const numValue = parseFloat(value);

        if (min && numValue < parseFloat(min)) {
          this.showValidationError(`Nilai minimal ${min}`);
          return false;
        }

        if (max && numValue > parseFloat(max)) {
          this.showValidationError(`Nilai maksimal ${max}`);
          return false;
        }

        return true;

      case "email":
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          this.showValidationError("Format email tidak valid");
          return false;
        }
        return true;

      case "url":
        try {
          new URL(value);
          return true;
        } catch {
          this.showValidationError("Format URL tidak valid");
          return false;
        }

      case "tel":
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(value)) {
          this.showValidationError("Format nomor telepon tidak valid");
          return false;
        }
        return true;

      case "date":
        // Validasi format tanggal YYYY-MM-DD
        const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        if (!dateRegex.test(value)) {
          this.showValidationError("Format tanggal harus YYYY-MM-DD");
          return false;
        }

        // Validasi apakah tanggal valid
        const dateObj = new Date(value);
        const [year, month, day] = value.split("-").map(Number);

        if (
          dateObj.getFullYear() !== year ||
          dateObj.getMonth() !== month - 1 ||
          dateObj.getDate() !== day
        ) {
          this.showValidationError("Tanggal tidak valid");
          return false;
        }

        // Validasi min/max date jika ada
        const minDate = targetElement.getAttribute("data-min-date");
        const maxDate = targetElement.getAttribute("data-max-date");

        if (minDate && value < minDate) {
          this.showValidationError(`Tanggal minimal ${minDate}`);
          return false;
        }

        if (maxDate && value > maxDate) {
          this.showValidationError(`Tanggal maksimal ${maxDate}`);
          return false;
        }

        return true;

      case "select":
        // Validasi apakah value ada dalam options
        const optionsData = targetElement.getAttribute("data-options") || "";
        const options = optionsData
          .split("|")
          .filter((opt) => opt.trim())
          .map((opt) => opt.trim());

        if (options.length > 0 && !options.includes(value.trim())) {
          this.showValidationError("Pilihan tidak valid");
          return false;
        }
        return true;

      case "tags":
        // Validasi tags - value adalah string yang dipisahkan koma
        const tagsValue = value.trim();
        if (!tagsValue) {
          // Jika kosong dan required, sudah di-handle di atas
          return true;
        }

        // Parse tags
        const tags = tagsValue
          .split(",")
          .map((tag) => tag.trim())
          .filter((tag) => tag.length > 0);

        // Validasi min/max tags jika ada
        const minTags = targetElement.getAttribute("data-min-tags");
        const maxTags = targetElement.getAttribute("data-max-tags");

        if (minTags && tags.length < parseInt(minTags)) {
          this.showValidationError(`Minimal ${minTags} tag harus dipilih`);
          return false;
        }

        if (maxTags && tags.length > parseInt(maxTags)) {
          this.showValidationError(`Maksimal ${maxTags} tag yang bisa dipilih`);
          return false;
        }

        // Validasi apakah semua tags ada dalam options
        const tagsOptionsData = targetElement.getAttribute("data-options") || "";
        const tagsOptions = tagsOptionsData
          .split("|")
          .filter((opt) => opt.trim())
          .map((opt) => opt.trim());

        if (tagsOptions.length > 0) {
          const invalidTags = tags.filter((tag) => !tagsOptions.includes(tag));
          if (invalidTags.length > 0) {
            this.showValidationError(`Tag tidak valid: ${invalidTags.join(", ")}`);
            return false;
          }
        }

        return true;

      case "tags-input":
        // Validasi tags-input - sama seperti tags tapi tidak ada validasi options
        const tagsInputValue = value.trim();
        if (!tagsInputValue) {
          // Jika kosong dan required, sudah di-handle di atas
          return true;
        }

        // Parse tags
        const tagsInput = tagsInputValue
          .split(",")
          .map((tag) => tag.trim())
          .filter((tag) => tag.length > 0);

        // Validasi min/max tags jika ada
        const minTagsInput = targetElement.getAttribute("data-min-tags");
        const maxTagsInput = targetElement.getAttribute("data-max-tags");

        if (minTagsInput && tagsInput.length < parseInt(minTagsInput)) {
          this.showValidationError(`Minimal ${minTagsInput} tag harus diisi`);
          return false;
        }

        if (maxTagsInput && tagsInput.length > parseInt(maxTagsInput)) {
          this.showValidationError(`Maksimal ${maxTagsInput} tag yang bisa diisi`);
          return false;
        }

        // Validasi panjang setiap tag jika ada
        const minTagLength = targetElement.getAttribute("data-min-tag-length");
        const maxTagLength = targetElement.getAttribute("data-max-tag-length");

        if (minTagLength) {
          const shortTags = tagsInput.filter(tag => tag.length < parseInt(minTagLength));
          if (shortTags.length > 0) {
            this.showValidationError(`Setiap tag minimal ${minTagLength} karakter`);
            return false;
          }
        }

        if (maxTagLength) {
          const longTags = tagsInput.filter(tag => tag.length > parseInt(maxTagLength));
          if (longTags.length > 0) {
            this.showValidationError(`Setiap tag maksimal ${maxTagLength} karakter`);
            return false;
          }
        }

        return true;

      case "textarea":
        return true;

      case "checkbox":
        // Checkbox selalu valid (boolean value)
        return true;

      case "radio":
        // Radio selalu valid (boolean value)
        return true;

      default:
        return true;
    }
  }

  // Method untuk menampilkan keterangan validasi
  showValidationError(message) {
    // Hapus error sebelumnya
    this.hideValidationError();

    if (!this.currentInputElement) return;

    // Buat element text sederhana untuk error
    const errorElement = document.createElement("div");
    errorElement.className = "validation-error";
    errorElement.textContent = message;

    // Style untuk error message - kecil dan merah
    errorElement.style.cssText = `
      color: #ff4444;
      font-size: 0.75rem;
      margin-top: 2px;
      margin-bottom: 0;
      font-family: inherit;
      line-height: 1.2;
    `;

    // Tambahkan setelah input element atau container
    const parentElement = this.currentInputElement.parentNode;
    const targetParent = parentElement.classList.contains(
      "nexa-display-container"
    )
      ? parentElement.parentNode
      : parentElement;
    const targetElement = parentElement.classList.contains(
      "nexa-display-container"
    )
      ? parentElement
      : this.currentInputElement;

    targetParent.insertBefore(errorElement, targetElement.nextSibling);
  }

  // Method untuk menyembunyikan error validasi
  hideValidationError() {
    if (this.currentInputElement && this.currentInputElement.parentNode) {
      const parent = this.currentInputElement.parentNode;
      if (parent) {
        const errorElement = parent.querySelector(".validation-error");
        if (errorElement) {
          errorElement.remove();
        }
      }
    }

    // Juga cari dan hapus error di seluruh dokumen sebagai backup
    const allErrors = document.querySelectorAll(".validation-error");
    allErrors.forEach((error) => error.remove());
  }

  // Method untuk set callback saat save
  onSaveCallback(callback) {
    this.onSave = callback;
  }

  // Method untuk init semua element dengan class tertentu
  initElements(selector = ".editable") {
    const elements = document.querySelectorAll(selector);
    
    elements.forEach((element) => {
      const type = element.getAttribute("type") || "text";
      const isDisabled = element.hasAttribute("disabled");
      const isReadonly = element.hasAttribute("readonly");

      // Tambahkan label jika ada
      this.addLabelToElement(element);

      // Tambahkan keterangan validasi sederhana
      this.addValidationInfo(element, type);

      // Untuk type tags dan tags-input, update tampilan chips jika ada nilai
      if (type === "tags" || type === "tags-input") {
        // Ambil nilai dari textContent atau dari data attribute jika ada
        let tagsValue = element.textContent.trim();
        
        // Jika textContent kosong atau "Null", coba ambil dari data-tags attribute
        if (!tagsValue || tagsValue === "Null" || tagsValue === "null") {
          tagsValue = element.getAttribute("data-tags") || "";
        }
        
        // Jika masih ada nilai, update tampilan chips
        if (tagsValue && tagsValue !== "Null" && tagsValue !== "null") {
          this.updateTagsDisplay(element, tagsValue);
        } else {
          // Jika tidak ada nilai, set placeholder
          element.textContent = "-- Tidak ada tags --";
          element.style.color = "#999";
          element.style.fontStyle = "italic";
        }
      }

      // Set visual state berdasarkan disabled/readonly
      this.setElementState(element, isDisabled, isReadonly);

      // Hanya tambahkan event listener jika tidak disabled dan tidak readonly
      if (!isDisabled && !isReadonly) {
        element.style.cursor = "pointer";
        element.addEventListener("click", () => this.editText(element));
      }
    });
  }

  // Method untuk mengatur visual state element berdasarkan disabled/readonly
  setElementState(element, isDisabled, isReadonly) {
    if (isDisabled) {
      // Style untuk disabled element - jangan ubah warna text
      element.style.cssText += `
        opacity: 0.7;
        cursor: not-allowed !important;
        background-color: #f5f5f5;
        pointer-events: none;
      `;
      element.title = "This field is disabled and cannot be edited";
      
      // Tambahkan class untuk styling CSS
      element.classList.add("nexa-disabled");
      
    } else if (isReadonly) {
      // Style untuk readonly element
      element.style.cssText += `
        cursor: default !important;
        background-color: #f9f9f9;
        color: #666;
        border: 1px solid #e0e0e0;
      `;
      element.title = "This field is read-only";
      
      // Tambahkan class untuk styling CSS
      element.classList.add("nexa-readonly");
      
    } else {
      // Style untuk editable element
      element.style.cssText += `
        cursor: pointer;
        transition: background-color 0.2s ease;
      `;
      element.title = element.title || "Click to edit";
      
      // Tambahkan class untuk styling CSS
      element.classList.add("nexa-editable");
      
      // Hover effect untuk editable elements
      element.addEventListener("mouseenter", () => {
        if (!this.isEditing) {
          element.style.backgroundColor = "#f0f8ff";
        }
      });
      
      element.addEventListener("mouseleave", () => {
        if (!this.isEditing) {
          element.style.backgroundColor = "";
        }
      });
    }
  }

  // Method untuk mengubah state element secara dinamis
  setElementDisabled(elementId, disabled = true) {
    const element = document.getElementById(elementId);
    if (!element) return false;

    if (disabled) {
      element.setAttribute("disabled", "");
    } else {
      element.removeAttribute("disabled");
    }

    // Update visual state
    const isReadonly = element.hasAttribute("readonly");
    this.setElementState(element, disabled, isReadonly);

    // Update event listeners
    if (disabled || isReadonly) {
      element.style.cursor = disabled ? "not-allowed" : "default";
      // Remove click event (akan di-handle oleh editText method)
    } else {
      element.style.cursor = "pointer";
    }

    return true;
  }

  // Method untuk mengubah readonly state element secara dinamis
  setElementReadonly(elementId, readonly = true) {
    const element = document.getElementById(elementId);
    if (!element) return false;

    if (readonly) {
      element.setAttribute("readonly", "");
    } else {
      element.removeAttribute("readonly");
    }

    // Update visual state
    const isDisabled = element.hasAttribute("disabled");
    this.setElementState(element, isDisabled, readonly);

    // Update event listeners
    if (isDisabled || readonly) {
      element.style.cursor = isDisabled ? "not-allowed" : "default";
    } else {
      element.style.cursor = "pointer";
    }

    return true;
  }

  // Method untuk menambahkan label ke element
  addLabelToElement(element) {
    const label = element.getAttribute("label");
    const icon = element.getAttribute("icon");

    // Debug: log untuk melihat nilai label dan icon
    // Hanya tambahkan label/icon jika attribute ada dan tidak kosong
    if (
      ((label && label.trim() !== "") || (icon && icon.trim() !== "")) &&
      !element.parentNode.classList.contains("nexa-display-container")
    ) {
      // Buat container untuk icon, label dan element
      const container = document.createElement("div");
      container.className = "nexa-display-container";

      // Style untuk container
      container.style.cssText = `
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
      `;

      // Buat element icon jika ada
      if (icon && icon.trim() !== "") {
        const iconElement = this.createIconElement(icon.trim(), element);
        if (iconElement) {
          container.appendChild(iconElement);
        }
      }

      // Buat element label jika ada
      if (label && label.trim() !== "") {
        const labelElement = document.createElement("span");
        labelElement.className = "nexa-display-label";
        labelElement.textContent = label;

        // Style untuk label
        labelElement.style.cssText = `
          font-size: 0.9rem;
          color: #555;
          white-space: nowrap;
          margin: 0;
          font-family: inherit;
          pointer-events: none;
        `;

        container.appendChild(labelElement);
      }

      // Pindahkan element ke dalam container
      const parent = element.parentNode;
      parent.insertBefore(container, element);
      container.appendChild(element);
    } else {
    }
  }

  // Method untuk membuat element icon dengan fallback
  createIconElement(iconName, element) {
    const iconElement = document.createElement("span");
    iconElement.className = "material-symbols-outlined";
    
    // Validasi dan fallback icon berdasarkan type element
    const validatedIcon = this.validateAndFallbackIcon(iconName, element);
    iconElement.textContent = validatedIcon;

    // Style untuk icon - menggunakan style yang konsisten dengan sistem
    iconElement.style.cssText = `
      font-size: 18px;
      color: #666;
      margin: 0;
      pointer-events: none;
      line-height: 1;
      vertical-align: middle;
      margin-right: 6px;
    `;

    return iconElement;
  }

  // Method untuk validasi icon dan fallback berdasarkan type
  validateAndFallbackIcon(iconName, element) {
    // Daftar icon yang umum digunakan untuk fallback berdasarkan type
    const typeIconMap = {
      'text': 'edit',
      'email': 'email',
      'password': 'lock',
      'number': 'numbers',
      'tel': 'phone',
      'url': 'link',
      'date': 'calendar_today',
      'time': 'schedule',
      'textarea': 'description',
      'select': 'arrow_drop_down',
      'tags': 'label',
      'tags-input': 'sell',
      'checkbox': 'check_box',
      'radio': 'radio_button_checked',
      'file': 'attach_file'
    };

    // Jika icon yang diberikan kosong, gunakan fallback berdasarkan type
    if (!iconName || iconName.trim() === '') {
      const elementType = element.getAttribute('type') || 'text';
      return typeIconMap[elementType] || 'edit';
    }

    // Langsung return iconName yang diberikan karena sistem sudah menggunakan Material Symbols
    // Tidak perlu validasi karena Material Symbols mendukung semua icon yang valid
    return iconName;
  }

  // Method untuk menambahkan keterangan validasi
  addValidationInfo(element, type) {
    const info = [];

    // Cek required
    if (element.hasAttribute("required")) {
      info.push("Wajib diisi");
    }

    // Cek min/max length
    const minLength = element.getAttribute("data-min-length");
    const maxLength = element.getAttribute("data-max-length");

    if (minLength && maxLength) {
      info.push(`${minLength}-${maxLength} karakter`);
    } else if (minLength) {
      info.push(`Min ${minLength} karakter`);
    } else if (maxLength) {
      info.push(`Max ${maxLength} karakter`);
    }

    // Cek min/max value untuk number
    const min = element.getAttribute("data-min");
    const max = element.getAttribute("data-max");

    if (min && max) {
      info.push(`Nilai ${min}-${max}`);
    } else if (min) {
      info.push(`Min ${min}`);
    } else if (max) {
      info.push(`Max ${max}`);
    }

    // Cek custom pattern
    const patternMessage = element.getAttribute("data-pattern-message");
    if (patternMessage) {
      info.push(patternMessage);
    }

    // Info berdasarkan type
    switch (type) {
      case "email":
        info.push("Format email");
        break;
      case "url":
        info.push("Format URL");
        break;
      case "tel":
        info.push("Format telepon");
        break;
      case "number":
        if (!min && !max) info.push("Hanya angka");
        break;
      case "date":
        info.push("Format: YYYY-MM-DD");
        const minDate = element.getAttribute("data-min-date");
        const maxDate = element.getAttribute("data-max-date");
        if (minDate && maxDate) {
          info.push(`Tanggal ${minDate} s/d ${maxDate}`);
        } else if (minDate) {
          info.push(`Min ${minDate}`);
        } else if (maxDate) {
          info.push(`Max ${maxDate}`);
        }
        break;
      case "select":
        const options = element.getAttribute("data-options");
        if (options) {
          const optionsList = options.split("|").slice(0, 2).join("/");
          info.push(`Pilihan: ${optionsList}`);
        }
        break;
      case "tags":
        info.push("Multiple selection");
        const tagsOptions = element.getAttribute("data-options");
        if (tagsOptions) {
          const tagsOptionsList = tagsOptions.split("|").slice(0, 3).join(", ");
          info.push(`Opsi: ${tagsOptionsList}`);
        }
        const minTags = element.getAttribute("data-min-tags");
        const maxTags = element.getAttribute("data-max-tags");
        if (minTags && maxTags) {
          info.push(`${minTags}-${maxTags} tags`);
        } else if (minTags) {
          info.push(`Min ${minTags} tags`);
        } else if (maxTags) {
          info.push(`Max ${maxTags} tags`);
        }
        break;
      case "tags-input":
        info.push("Ketik tags (pisah dengan koma)");
        const minTagsInput = element.getAttribute("data-min-tags");
        const maxTagsInput = element.getAttribute("data-max-tags");
        if (minTagsInput && maxTagsInput) {
          info.push(`${minTagsInput}-${maxTagsInput} tags`);
        } else if (minTagsInput) {
          info.push(`Min ${minTagsInput} tags`);
        } else if (maxTagsInput) {
          info.push(`Max ${maxTagsInput} tags`);
        }
        const minTagLength = element.getAttribute("data-min-tag-length");
        const maxTagLength = element.getAttribute("data-max-tag-length");
        if (minTagLength || maxTagLength) {
          if (minTagLength && maxTagLength) {
            info.push(`Panjang tag: ${minTagLength}-${maxTagLength} karakter`);
          } else if (minTagLength) {
            info.push(`Min ${minTagLength} karakter per tag`);
          } else if (maxTagLength) {
            info.push(`Max ${maxTagLength} karakter per tag`);
          }
        }
        break;
      case "checkbox":
        info.push("Ya/Tidak");
        break;
      case "radio":
        info.push("Ya/Tidak");
        break;
    }

    // Set title dengan info validasi dan icon
    if (info.length > 0) {
      const infoText = info.join(" • ");
      const icon = element.getAttribute("icon");
      const iconInfo = icon ? ` (Icon: ${icon})` : "";
      
      // Tambahkan info disabled/readonly
      let stateInfo = "";
      if (element.hasAttribute("disabled")) {
        stateInfo = " [DISABLED]";
      } else if (element.hasAttribute("readonly")) {
        stateInfo = " [READ-ONLY]";
      } else {
        stateInfo = " [EDITABLE]";
      }
      
      element.title = `${infoText}${iconInfo}${stateInfo}`;
    }
  }

  // Method untuk set visual indicator berdasarkan type - TIDAK DIGUNAKAN
  /*
  setElementIndicator(element, type) {
    // Tambahkan border bawah dengan warna berbeda berdasarkan type
    const colors = {
      'text': '#007bff',
      'number': '#28a745',
      'email': '#ffc107',
      'password': '#dc3545',
      'url': '#17a2b8',
      'tel': '#6f42c1'
    };
    
    element.style.borderBottom = `2px solid ${colors[type] || colors.text}`;
    element.style.paddingBottom = '2px';
    element.title = `Click to edit (${type})`;
  }
  */
}

// Export untuk digunakan
window.NexaChild = NexaChild;
export default NexaChild;
export { NexaChild };
