export function Validation(ret, callback) {
  const formSelector = ret.formid;
  const submitSelector = ret.submitid;
  const fileInput = ret.fileInput;
  const validasi = ret.validasi || {}; // Get validation rules if they exist
  const endpoint = ret.endpoint || null; // Add endpoint configuration

  const getValidationBy = Array.isArray(ret.getValidationBy)
    ? ret.getValidationBy
    : ret.getValidationBy
    ? [ret.getValidationBy]
    : ["name", "id"];

  /** Resolves which key in `validasi` applies to this element (order matches getValidationBy, e.g. modal + NexaModalHtml). */
  const pickValidationKey = (element) => {
    const candidates = [];
    for (const method of getValidationBy) {
      if (method === "id" && element.id) candidates.push(element.id);
      else if (method === "name" && element.name) candidates.push(element.name);
      else if (
        typeof method === "string" &&
        element.getAttribute &&
        element.getAttribute(method)
      ) {
        candidates.push(element.getAttribute(method));
      }
    }
    for (const key of candidates) {
      if (validasi[key] !== undefined) return key;
    }
    return null;
  };

  const hasValidationRule = (element) => pickValidationKey(element) !== null;

  // Helper function to get element by selector
  const getElement = (selector) => {
    if (!selector) return null;

    // Try querySelector first
    const element = document.querySelector(selector);
    if (element) return element;

    // If not found and selector doesn't start with # or ., try as ID
    if (!selector.startsWith("#") && !selector.startsWith(".")) {
      const elementById = document.getElementById(selector);
      if (elementById) return elementById;
    }
    return null;
  };

  // Helper function untuk parse ukuran file
  function parseFileSize(size) {
    // Handle undefined, null, or empty values
    if (!size || size === "") {
      return 15 * 1024 * 1024; // Default to 15MB
    }

    const units = {
      B: 1,
      KB: 1024,
      MB: 1024 * 1024,
      GB: 1024 * 1024 * 1024,
    };

    // Check if size is a string that matches the pattern
    if (typeof size === "string") {
      const match = size.match(/^(\d+)\s*(B|KB|MB|GB)$/i);
      if (match) {
        const [, value, unit] = match;
        return parseInt(value) * units[unit.toUpperCase()];
      }
    }

    // If it's a number or doesn't match the pattern, treat as MB
    const numericValue = parseInt(size);
    return isNaN(numericValue) ? 15 * 1024 * 1024 : numericValue * 1024 * 1024;
  }

  // Helper function untuk format ukuran file
  function formatFileSize(bytes) {
    const sizes = ["B", "KB", "MB", "GB"];
    if (bytes === 0) return "0 B";
    const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
    return Math.round(bytes / Math.pow(1024, i), 2) + " " + sizes[i];
  }

  // Helper function untuk mengirim data ke server
  const sendToServer = async (data) => {
    if (!endpoint || !endpoint.url) {
      throw new Error("Endpoint configuration is required");
    }

    const config = {
      method: endpoint.method || "POST",
      headers: {
        "Content-Type": "application/json",
        ...endpoint.headers,
      },
      body: JSON.stringify(data),
    };

    try {
      const response = await fetch(endpoint.url, config);

      // Get response text first for better error handling
      const responseText = await response.text();

      if (!response.ok) {
        let errorMessage = `HTTP error! status: ${response.status}`;
        let errorDetails = null;

        // Try to parse error response as JSON
        try {
          errorDetails = JSON.parse(responseText);
          errorMessage = errorDetails.message || errorMessage;
        } catch (parseError) {
          // If not JSON, use the text response
          errorMessage += responseText ? ` - ${responseText}` : "";
        }

        const error = new Error(errorMessage);
        error.status = response.status;
        error.response = errorDetails;
        error.responseText = responseText;
        throw error;
      }

      // Try to parse success response as JSON
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        // If response is not JSON, wrap it
        result = {
          message: "Data berhasil dikirim",
          data: responseText,
        };
      }

      return result;
    } catch (error) {
      throw error;
    }
  };

  // Helper function untuk menampilkan loading state
  const setLoadingState = (isLoading) => {
    if (isLoading) {
      submitButton.disabled = true;
      submitButton.textContent = "Loading...";
      submitButton.classList.add("loading");
    } else {
      submitButton.disabled = false;
      submitButton.textContent = submitButton.dataset.originalText || "Submit";
      submitButton.classList.remove("loading");
    }
  };

  // Helper function untuk menampilkan response
  const showResponse = (response, isError = false) => {
    // Cari atau buat container untuk response
    let responseContainer = form.querySelector(".form-response");
    if (!responseContainer) {
      responseContainer = document.createElement("div");
      responseContainer.className = "form-response";
      form.appendChild(responseContainer);
    }

    // Reset classes
    responseContainer.className = "form-response";

    if (isError) {
      responseContainer.classList.add("error");

      let errorMessage = "Terjadi kesalahan";
      let errorDetails = null;

      if (typeof response === "string") {
        errorMessage = response;
      } else if (response.message) {
        errorMessage = response.message;
      }

      // Check if there are validation errors from server
      if (response.response && response.response.errors) {
        errorDetails = response.response.errors;
      } else if (response.errors) {
        errorDetails = response.errors;
      }

      let errorHtml = `
        <div class="response-message error">
          <strong>Error ${response.status || ""}:</strong> ${errorMessage}
        </div>
      `;

      // Show validation errors if available
      if (errorDetails) {
        errorHtml += `<div class="response-data error-details">`;
        errorHtml += `<strong>Detail Error:</strong><br>`;
        if (typeof errorDetails === "object") {
          Object.entries(errorDetails).forEach(([field, error]) => {
            errorHtml += `<div class="error-item"><strong>${field}:</strong> ${error}</div>`;
          });
        } else {
          errorHtml += `<pre>${JSON.stringify(errorDetails, null, 2)}</pre>`;
        }
        errorHtml += `</div>`;
      }

      // Show raw response for debugging (in development)
      if (window.location.hostname === "localhost" && response.responseText) {
        errorHtml += `
          <div class="response-data debug-info">
            <strong>Debug Info (Raw Response):</strong>
            <pre>${response.responseText}</pre>
          </div>
        `;
      }

      responseContainer.innerHTML = errorHtml;
    } else {
      responseContainer.classList.add("success");
      responseContainer.innerHTML = `
        <div class="response-message success">
          <strong>Success:</strong> ${
            response.message || "Data berhasil dikirim"
          }
        </div>
        ${
          response.data
            ? `<div class="response-data"><pre>${JSON.stringify(
                response.data,
                null,
                2
              )}</pre></div>`
            : ""
        }
      `;

      // Auto hide after 5 seconds for success messages
      setTimeout(() => {
        responseContainer.style.opacity = "0";
        setTimeout(() => {
          if (responseContainer.parentNode) {
            responseContainer.remove();
          }
        }, 300);
      }, 5000);
    }
  };

  // Fungsi untuk menginisialisasi file input - moved before it's called
  const initFileInput = () => {
    const fileInputs = form.querySelectorAll(".form-nexa-file-input");

    fileInputs.forEach((input) => {
      const container = input.closest(".form-nexa");
      const dragDropArea = container.querySelector(".form-nexa-file-dragdrop");
      const preview = container.querySelector(".form-nexa-file-preview");
      const fileList = container.querySelector(".form-nexa-file-list");
      const errorMessage = container.querySelector(".error-message");

      // Handle Drag & Drop events
      if (dragDropArea) {
        ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
          dragDropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
          e.preventDefault();
          e.stopPropagation();
        }

        ["dragenter", "dragover"].forEach((eventName) => {
          dragDropArea.addEventListener(eventName, () => {
            dragDropArea.classList.add("highlight");
          });
        });

        ["dragleave", "drop"].forEach((eventName) => {
          dragDropArea.addEventListener(eventName, () => {
            dragDropArea.classList.remove("highlight");
          });
        });

        dragDropArea.addEventListener("drop", (e) => {
          const dt = e.dataTransfer;
          const files = dt.files;
          input.files = files;

          // Trigger change event untuk memicu validasi dan preview
          const changeEvent = new Event("change");
          input.dispatchEvent(changeEvent);
        });
      }

      // Handle file validation
      input.addEventListener("change", () => {
        const maxSize = input.dataset.maxSize || "15MB"; // Default to 15MB if not specified
        const maxFiles =
          parseInt(input.dataset.maxFiles) || (input.multiple ? 5 : 1);
        const files = Array.from(input.files);

        // Reset error message
        if (errorMessage) {
          errorMessage.textContent = "";
        }

        // Validate number of files
        if (input.multiple && files.length > maxFiles) {
          if (errorMessage) {
            errorMessage.textContent = `Maksimal ${maxFiles} file yang dapat diunggah`;
          }
          input.value = "";
          return;
        }

        // Validate file size
        const maxSizeBytes = parseFileSize(maxSize);
        const totalSize = files.reduce((sum, file) => sum + file.size, 0);

        if (totalSize > maxSizeBytes) {
          if (errorMessage) {
            errorMessage.textContent = `Total ukuran file tidak boleh lebih dari ${maxSize}`;
          }
          input.value = "";
          return;
        }

        // Update file list
        if (fileList) {
          fileList.innerHTML = files
            .map(
              (file) => `
            <div class="file-item">
              <span class="file-name">${file.name}</span>
              <span class="file-size">(${formatFileSize(file.size)})</span>
            </div>
          `
            )
            .join("");
        }
      });
    });
  };

  // Get form element using the new helper
  const form = getElement(formSelector);
  if (!form) {
    return Promise.reject(
      new Error(`Form with selector "${formSelector}" not found`)
    );
  }

  // Get submit button using the new helper
  const submitButton = getElement(submitSelector);
  if (!submitButton) {
    return Promise.reject(
      new Error(`Submit button with selector "${submitSelector}" not found`)
    );
  }

  // Store original submit button text for loading state
  if (!submitButton.dataset.originalText) {
    submitButton.dataset.originalText = submitButton.textContent;
  }

  // formInput,submitForm
  if (fileInput) {
    initFileInput();
  }

  // Tambahkan fungsi untuk mengisi nilai input
  const setFormValues = (values) => {
    if (!values || typeof values !== "object") return;

    Object.entries(values).forEach(([name, value]) => {
      const elements = form.querySelectorAll(`[name="${name}"]`);

      elements.forEach((element) => {
        switch (element.type) {
          case "file":
            // File input tidak bisa diisi secara langsung karena alasan keamanan
            break;

          case "checkbox":
          case "radio":
            element.checked = Array.isArray(value)
              ? value.includes(element.value)
              : element.value === value;
            break;

          case "select-one":
          case "select-multiple":
            if (Array.isArray(value)) {
              Array.from(element.options).forEach((option) => {
                option.selected = value.includes(option.value);
              });
            } else {
              element.value = value;
            }
            break;

          default:
            element.value = value;
        }

        // Trigger change event untuk memicu validasi
        const event = new Event("change", { bubbles: true });
        element.dispatchEvent(event);

        // Handle floating label setelah set value
        handleFloatingLabel(element);
      });
    });
  };

  // Jika ada nilai awal di ret.value, set nilai form
  if (ret.value) {
    setFormValues(ret.value);
  }

  // Mengembalikan Promise untuk menangani data form
  return new Promise((resolve) => {
    // Fungsi untuk menghapus class error
    const removeErrorClass = (element) => {
      // ✅ FIX: Hapus error berdasarkan id field atau name
      const elementId = element.id || element.name;
      const elementName = element.name;
      
      // ✅ FIX: Handle hidden input khusus - mereka tidak memiliki formGroup visible
      if (element.type === "hidden") {
        // Cari error element berdasarkan id
        let errorMessage = document.getElementById(`errors_${elementId}`);
        if (!errorMessage && elementName) {
          errorMessage = document.getElementById(`errors_${elementName}`);
        }
        if (errorMessage) {
          errorMessage.style.display = "none";
          errorMessage.textContent = "";
        }
        element.classList.remove("is-invalid");
        return; // Early return untuk hidden input
      }
      
      // Hapus error normal untuk semua element
      let formGroup;
      if (element.type === "checkbox" || element.type === "radio") {
        formGroup = element.closest(
          ".form-nexa-check, .nx-checkbox-item, .nx-radio-item, .nx-switch-item"
        );
      } else {
        formGroup = element.closest(
          ".form-nexa-floating, .form-nexa, .form-nexa-group"
        );
      }

      if (formGroup) {
        formGroup.classList.remove("form-error");
        
        // ✅ FIX: Check for error by id first, then by name, then in formGroup
        let errorMessage = document.getElementById(`errors_${elementId}`);
        if (!errorMessage && elementName) {
          errorMessage = document.getElementById(`errors_${elementName}`);
        }
        if (!errorMessage) {
          errorMessage = formGroup.querySelector(".error-message");
        }
        if (errorMessage) {
          errorMessage.remove();
        }
        element.classList.remove("is-invalid");
      }
    };

    // Fungsi validasi berdasarkan tipe input
    const validateInput = (element) => {
      const type = element.type;
      const name = element.name;
      const placeholder = element.placeholder;
      const elementId = element.id || name;
      
      // ✅ FIX: Skip validasi untuk field slug (ditangani khusus oleh NexaSlug)
      if (element.hasAttribute("data-slug-field") || element.getAttribute("data-slug-field") === "true") {
        return null; // Field slug tidak perlu divalidasi, ditangani khusus
      }
      
      // ✅ FIX: Hanya validasi jika field ini ada di data validation
      if (!hasValidationRule(element)) {
        return null; // Skip validasi jika field tidak ada di data validation
      }

      // ✅ FIX: Cari label yang user-friendly untuk error message - pastikan label sesuai dengan element
      const getFieldLabel = (element) => {
        // Prioritas 1: Cari label berdasarkan for attribute yang sesuai dengan element.id
        if (element.id) {
          const labelFor = form.querySelector(`label[for="${element.id}"]`);
          if (labelFor) {
            return labelFor.textContent.trim();
          }
        }
        
        // Prioritas 2: Cari label berdasarkan for attribute yang sesuai dengan element.name (jika id tidak ada)
        if (element.name && !element.id) {
          const labelForName = form.querySelector(`label[for="${element.name}"]`);
          if (labelForName) {
            return labelForName.textContent.trim();
          }
        }

        // Prioritas 3: Cari label dalam container yang SAMA dengan element (bukan container lain)
        const container = element.closest(
          ".form-nexa-floating, .form-nexa, .form-nexa-group, .form-nexa-check"
        );
        if (container) {
          // ✅ FIX: Pastikan label yang ditemukan benar-benar dalam container yang sama dengan element
          const labelInContainer = container.querySelector(`label[for="${element.id}"], label[for="${element.name}"]`);
          if (labelInContainer && container.contains(labelInContainer)) {
            return labelInContainer.textContent.trim();
          }
          
          // Fallback: cari label pertama dalam container yang berisi element
          const allLabelsInContainer = container.querySelectorAll("label");
          for (const label of allLabelsInContainer) {
            if (container.contains(label) && container.contains(element)) {
              // Pastikan label ini terkait dengan element (cek for attribute)
              const labelFor = label.getAttribute("for");
              if (!labelFor || labelFor === element.id || labelFor === element.name) {
                return label.textContent.trim();
              }
            }
          }
        }

        // Fallback ke placeholder atau name
        return (
          placeholder ||
          name.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase())
        );
      };

      const fieldLabel = getFieldLabel(element);

      const validationKey = pickValidationKey(element);
      
      if (validationKey && validasi[validationKey] && type !== "file") {
        const validationValue = validasi[validationKey];
        
        // ✅ FIX: Handle validation value 2 as required field, other numbers as minLength
        if (validationValue === 2 || validationValue === "2") {
          // Required field - check if empty
          if (!element.value || !element.value.trim()) {
            return `${fieldLabel} wajib diisi`;
          }
        } else if (typeof validationValue === 'number' && validationValue > 2) {
          // Numeric validation value > 2 means minLength (e.g., 10, 150)
          const minLength = validationValue;
          
          // Jika field kosong dan ada validasi minLength, langsung return error
          if (!element.value || !element.value.trim()) {
            return `${fieldLabel} tidak boleh kosong (minimal ${minLength} karakter)`;
          }
          
          // Validasi panjang karakter untuk field yang sudah terisi
          if (element.value.length < minLength) {
            return `${fieldLabel} minimal ${minLength} karakter`;
          }
        } else if (Array.isArray(validationValue)) {
          // Array means [minLength, maxLength]
          const [minLength, maxLength] = validationValue;
          if (!element.value || !element.value.trim()) {
            return `${fieldLabel} tidak boleh kosong (minimal ${minLength} karakter)`;
          }
          if (element.value.length < minLength) {
            return `${fieldLabel} minimal ${minLength} karakter`;
          }
          if (maxLength && element.value.length > maxLength) {
            return `${fieldLabel} tidak boleh lebih dari ${maxLength} karakter`;
          }
        } else {
          // Single number as minLength
          const minLength = validationValue;
          if (!element.value || !element.value.trim()) {
            return `${fieldLabel} tidak boleh kosong (minimal ${minLength} karakter)`;
          }
          if (element.value.length < minLength) {
            return `${fieldLabel} minimal ${minLength} karakter`;
          }
        }
      }

      switch (type) {
        case "email":
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(element.value)) {
            return `${fieldLabel} harus berupa email yang valid`;
          }
          break;

        case "tel":
          const cleanNumber = element.value.replace(/[^\d]/g, "");
          const isStartWith08 = /^08\d{8,11}$/.test(cleanNumber);
          const isStartWith62 = /^62\d{9,12}$/.test(cleanNumber);
          const isStartWithArea = /^[2-3]\d{8,11}$/.test(cleanNumber);

          if (!element.value) {
            return `${fieldLabel} tidak boleh kosong`;
          }

          if (!isStartWith08 && !isStartWith62 && !isStartWithArea) {
            return `${fieldLabel} tidak valid. Gunakan format: 08xx, +62xx, 02x, atau 03x`;
          }

          if (cleanNumber.length < 8 || cleanNumber.length > 13) {
            return `${fieldLabel} harus antara 8-13 digit`;
          }
          break;

        case "url":
          const urlRegex =
            /^(https?:\/\/)?([\w\-]+(\.[\w\-]+)+\.?(:\d+)?(\/[^\s]*)?|localhost(:\d+)?)$/i;

          if (!element.value) {
            return `${fieldLabel} tidak boleh kosong`;
          }

          if (!urlRegex.test(element.value)) {
            return `${fieldLabel} harus berupa URL yang valid (contoh: https://example.com)`;
          }
          break;

        case "radio":
          const radioGroup = document.querySelectorAll(`input[name="${name}"]`);
          const isChecked = Array.from(radioGroup).some(
            (radio) => radio.checked
          );
          if (!isChecked) {
            return `${fieldLabel} harus dipilih`;
          }
          break;

        case "checkbox":
          // For single-select checkboxes, check if any option is selected
          if (element.classList.contains("single-select-checkbox")) {
            const sameName = document.querySelectorAll(
              `input[name="${element.name}"].single-select-checkbox:checked`
            );
            if (sameName.length === 0) {
              return `${fieldLabel} harus dipilih`;
            }
            // If current element is not checked but others are, it's valid (skip validation)
            if (!element.checked && sameName.length > 0) {
              return null;
            }
          } else if (element.classList.contains("single-select-switch")) {
            // For single-select switches, check if any option is selected
            const sameName = document.querySelectorAll(
              `input[name="${element.name}"].single-select-switch:checked`
            );
            if (sameName.length === 0) {
              return `${fieldLabel} harus dipilih`;
            }
            // If current element is not checked but others are, it's valid (skip validation)
            if (!element.checked && sameName.length > 0) {
              return null;
            }
          } else {
            // Untuk checkbox tunggal biasa
            if (!element.checked) {
              return `${fieldLabel} harus dicentang`;
            }
          }
          break;

        case "select-one":
          if (!element.value || element.value === "") {
            return `${fieldLabel} harus dipilih`;
          }
          break;

        case "select-multiple":
          const selectedOptions = Array.from(element.selectedOptions);
          if (selectedOptions.length === 0) {
            return `${fieldLabel} minimal pilih satu`;
          }
          break;

        case "hidden":
          // ✅ FIX: Hidden input sudah ditangani oleh validasi custom di atas
          // Jika sampai di sini, berarti tidak ada validasi custom atau tidak required
          // Hidden input biasanya tidak perlu validasi default, jadi return null
          return null;

        case "file":
          if (element.required && element.files.length === 0) {
            return `${fieldLabel} tidak boleh kosong`;
          }

          if (element.files.length > 0) {
            // Check total size of all files
            const totalSize = Array.from(element.files).reduce(
              (sum, file) => sum + file.size,
              0
            );

            const fileVk = pickValidationKey(element);
            const fileRule = fileVk ? validasi[fileVk] : undefined;
            // Get max size from validasi or data attribute
            const maxSizeMB = Array.isArray(fileRule)
              ? fileRule[0]
              : element.dataset.maxSize
              ? parseInt(element.dataset.maxSize)
              : 15;
            const maxSizeBytes = maxSizeMB * 1024 * 1024;

            if (totalSize > maxSizeBytes) {
              return `${fieldLabel} maksimal ${maxSizeMB}MB`;
            }

            // Check number of files (for both single and multiple)
            const maxFiles =
              Array.isArray(fileRule) && fileRule.length > 1
                ? fileRule[1] // Use validation rules [sizeMB, maxFiles]
                : element.dataset.maxFiles
                ? parseInt(element.dataset.maxFiles)
                : element.multiple
                ? 5
                : 1; // Default: 1 for single, 5 for multiple

            if (element.files.length > maxFiles) {
              return `${fieldLabel} maksimal ${maxFiles} file yang dapat diunggah`;
            }

            // Check file types
            if (element.accept) {
              const allowedTypes = element.accept
                .split(",")
                .map((type) => type.trim());
              const fileType = element.files[0].type;
              const fileExtension =
                "." + element.files[0].name.split(".").pop().toLowerCase();

              const isValidType = allowedTypes.some((type) => {
                if (type.startsWith(".")) {
                  // Check file extension
                  return type.toLowerCase() === fileExtension;
                } else {
                  // Check mime type
                  return fileType.match(new RegExp(type.replace("*", ".*")));
                }
              });

              if (!isValidType) {
                return `${fieldLabel} tidak didukung. Gunakan: ${element.accept}`;
              }
            }
          }
          break;

        default:
          // ✅ VALIDATION BYPASS: Check if input has preset value (for search inputs that use type="text")
          if (element.hasAttribute('data-has-preset-value')) {
            const presetValue = element.getAttribute('data-preset-value');
            if (presetValue && presetValue.trim() !== '') {
              return null; // Skip validation - field has valid preset value
            }
          }
          
          if (!element.value.trim()) {
            return `${fieldLabel} tidak boleh kosong`;
          }
      }
      return null;
    };

    const addErrorClass = (element, message) => {
      // ✅ FIX: Skip error message untuk field slug (ditangani khusus oleh NexaSlug)
      if (element.hasAttribute("data-slug-field") || element.getAttribute("data-slug-field") === "true") {
        return; // Field slug tidak perlu menampilkan error message
      }
      
      // ✅ FIX: Hidden input dari generateHiddenInput tidak menampilkan error message
      // Hidden input lainnya tetap bisa menampilkan error
      if (element.type === "hidden") {
        // Khusus untuk hidden input dari generateHiddenInput (memiliki attribute data-hidden-no-error)
        if (element.hasAttribute("data-hidden-no-error") || element.getAttribute("data-hidden-no-error") === "true") {
          // Hidden input ini tidak perlu menampilkan error message
          return; // Early return untuk hidden input dari generateHiddenInput
        }
        
        // Untuk hidden input lainnya (seperti dari tags, textarea, dll), tampilkan error seperti biasa
        const elementId = element.id || element.name;
        
        // Cek apakah error element sudah ada
        let errorElement = document.getElementById(`errors_${elementId}`);
        if (!errorElement) {
          // Jika tidak ada, buat error element baru setelah hidden input
          errorElement = document.createElement("small");
          errorElement.className = "error-message";
          errorElement.id = `errors_${elementId}`;
          errorElement.setAttribute("data-field-id", elementId);
          errorElement.setAttribute("data-field-name", element.name);
          
          // Insert setelah hidden input — tapi jika di dalam nexa-search-container,
          // harus keluar ke luar .form-nexa-floating agar ikon tidak bergeser
          const searchContainer = element.closest(".nexa-search-container");
          if (searchContainer) {
            const floatingWrapper = searchContainer.closest(".form-nexa-floating");
            const anchor = floatingWrapper || searchContainer;
            const anchorParent = anchor.parentNode;
            if (anchorParent) {
              if (anchor.nextSibling) {
                anchorParent.insertBefore(errorElement, anchor.nextSibling);
              } else {
                anchorParent.appendChild(errorElement);
              }
            } else {
              searchContainer.appendChild(errorElement);
            }
          } else if (element.nextSibling) {
            element.parentNode.insertBefore(errorElement, element.nextSibling);
          } else {
            element.parentNode.appendChild(errorElement);
          }
        }
        
        // Set error message
        errorElement.textContent = message;
        errorElement.style.display = "block";
        errorElement.style.fontSize = "0.65rem";
        errorElement.style.color = "#dc3545";
        element.classList.add("is-invalid");
        
        return; // Early return untuk hidden input lainnya
      }
      
      // ✅ FIX: Cari container yang tepat berdasarkan tipe input - pastikan formGroup benar-benar berisi element ini
      let formGroup;

      if (element.type === "checkbox" || element.type === "radio") {
        formGroup = element.closest(
          ".form-nexa-check, .nx-checkbox-item, .nx-radio-item, .nx-switch-item"
        );
        if (!formGroup) {
          // Fallback: try to find parent grid container
          formGroup = element.closest(
            ".nx-checkbox-grid, .nx-radio-grid, .nx-switch-grid"
          );
        }
      } else {
        // ✅ FIX: Pastikan formGroup benar-benar berisi element yang sedang divalidasi
        formGroup = element.closest(
          ".form-nexa-floating, .form-nexa, .form-nexa-group, .form-nexa-group1"
        );
        
        // ✅ FIX: Jika formGroup adalah .form-nexa yang bersarang di dalam .form-nexa-floating,
        // gunakan .form-nexa-floating sebagai container agar error muncul di luar inner div
        if (formGroup && formGroup.classList.contains("form-nexa") && 
            !formGroup.classList.contains("form-nexa-floating") &&
            !formGroup.classList.contains("form-nexa-group")) {
          const outerFloating = formGroup.closest(".form-nexa-floating");
          if (outerFloating) {
            formGroup = outerFloating;
          }
        }
        
        // Verifikasi bahwa formGroup benar-benar berisi element ini
        if (formGroup && !formGroup.contains(element)) {
          // Jika formGroup tidak berisi element, cari lagi
          formGroup = null;
        }
        
        // Jika masih tidak ditemukan, cari parent yang berisi element
        if (!formGroup) {
          let parent = element.parentElement;
          while (parent && parent !== form) {
            if (parent.classList.contains("form-nexa-floating") || 
                parent.classList.contains("form-nexa") || 
                parent.classList.contains("form-nexa-group") ||
                parent.classList.contains("form-nexa-group1")) {
              formGroup = parent;
              break;
            }
            parent = parent.parentElement;
          }
        }
      }
      
      // ✅ FIX: Pastikan formGroup benar-benar berisi element yang sedang divalidasi
      if (formGroup && !formGroup.contains(element)) {
        // Jika formGroup tidak berisi element, cari parent yang tepat
        let currentParent = element.parentElement;
        while (currentParent && currentParent !== form) {
          if (currentParent.classList.contains("form-nexa-floating") || 
              currentParent.classList.contains("form-nexa") || 
              currentParent.classList.contains("form-nexa-group") ||
              currentParent.classList.contains("form-nexa-group1") ||
              currentParent.classList.contains("form-nexa-check") ||
              currentParent.classList.contains("nx-checkbox-item") ||
              currentParent.classList.contains("nx-radio-item") ||
              currentParent.classList.contains("nx-switch-item")) {
            formGroup = currentParent;
            break;
          }
          currentParent = currentParent.parentElement;
        }
      }

      if (formGroup) {
        formGroup.classList.add("form-error");

        // ✅ FIX: Hapus SEMUA error message yang terkait dengan field ini untuk mencegah duplikasi
        // Check for existing error by id first (errors_${element.id}), then by name (errors_${element.name})
        const elementId = element.id || element.name;
        const elementName = element.name;
        
        // Hapus error berdasarkan id
        let existingError = document.getElementById(`errors_${elementId}`);
        if (existingError) {
          existingError.remove();
        }
        
        // Hapus error berdasarkan name jika berbeda dengan id
        if (elementName && elementName !== elementId) {
          existingError = document.getElementById(`errors_${elementName}`);
          if (existingError) {
            existingError.remove();
          }
        }
        
        // ✅ FIX: Hapus semua error di formGroup yang terkait dengan field ini berdasarkan data attribute
        const allErrors = formGroup.querySelectorAll(".error-message");
        allErrors.forEach(err => {
          const errFieldId = err.getAttribute("data-field-id");
          const errFieldName = err.getAttribute("data-field-name");
          // Hapus jika error ini terkait dengan field yang sedang divalidasi
          if (errFieldId === elementId || errFieldId === elementName || 
              errFieldId === element.id || errFieldId === element.name ||
              errFieldName === elementName || errFieldName === elementId ||
              errFieldName === element.name || errFieldName === element.id) {
            err.remove();
          }
        });
        
        // ✅ FIX: Hapus juga error yang tidak memiliki data attribute tapi memiliki id yang sesuai
        const formGroupErrors = formGroup.querySelectorAll(".error-message");
        formGroupErrors.forEach(err => {
          const errId = err.id;
          if (errId === `errors_${elementId}` || errId === `errors_${elementName}` ||
              errId === `errors_${element.id}` || errId === `errors_${element.name}`) {
            err.remove();
          }
        });

        // Tambahkan border error ke input
        element.classList.add("is-invalid");

        // ✅ FIX: Buat error message dengan id yang sesuai field id atau name
        const errorDiv = document.createElement("div");
        errorDiv.className = "error-message";
        errorDiv.id = `errors_${elementId}`; // Use element.id or name as fallback
        errorDiv.setAttribute("data-field-id", elementId);
        errorDiv.setAttribute("data-field-name", elementName);
        errorDiv.textContent = message;
        
        // Set style dasar untuk error message
        errorDiv.style.fontSize = "0.65rem";
        errorDiv.style.color = "#dc3545";
        errorDiv.style.width = "100%";
        errorDiv.style.display = "block";

        // Positioning berdasarkan tipe container
        if (formGroup.classList.contains("nexa-search-container")) {
          // Search input: error harus di luar seluruh .form-nexa-floating/.form-nexa-icon
          // Naik ke .form-nexa-floating jika ada, lalu insert setelahnya
          const floatingWrapper = formGroup.closest(".form-nexa-floating");
          const iconWrapper = formGroup.closest(".form-nexa-icon");
          const anchor = floatingWrapper || iconWrapper || formGroup;
          const anchorParent = anchor.parentNode;
          if (anchorParent) {
            if (anchor.nextSibling) {
              anchorParent.insertBefore(errorDiv, anchor.nextSibling);
            } else {
              anchorParent.appendChild(errorDiv);
            }
          } else {
            formGroup.appendChild(errorDiv);
          }
          errorDiv.style.marginTop = "4px";
          errorDiv.style.marginBottom = "0";
        } else if (formGroup.classList.contains("form-nexa-floating")) {
          // Untuk floating labels - posisikan setelah icon container atau label
          const iconContainer = formGroup.querySelector(".form-nexa-icon");
          const label = formGroup.querySelector("label");
          
          // Prioritas: setelah icon container > setelah label > setelah input > di akhir container
          if (iconContainer) {
            // Jika ada icon container, letakkan langsung setelah icon container (di luar icon container)
            // Icon container adalah child dari formGroup, jadi insert setelah iconContainer
            if (iconContainer.nextSibling) {
              formGroup.insertBefore(errorDiv, iconContainer.nextSibling);
            } else {
              formGroup.appendChild(errorDiv);
            }
          } else if (label) {
            // Jika ada label tapi tidak ada icon container, letakkan setelah label
            if (label.nextSibling) {
              label.parentNode.insertBefore(errorDiv, label.nextSibling);
            } else {
              label.parentNode.appendChild(errorDiv);
            }
          } else {
            // ✅ FIX: Gunakan element yang sedang divalidasi, bukan querySelector yang bisa mengambil field lain
            // Pastikan kita menggunakan element yang benar berdasarkan id atau name
            let insertAfter = element;
            
            // Jika input ada di dalam wrapper, gunakan wrapper
            const wrapper = element.closest(".form-nexa-icon, .form-nexa, .form-nexa-group1");
            if (wrapper && wrapper.parentNode === formGroup) {
              insertAfter = wrapper;
            } else if (element.parentNode === formGroup) {
              insertAfter = element;
            } else {
              // Cari wrapper yang tepat untuk element ini
              let currentElement = element.parentNode;
              while (currentElement && currentElement !== formGroup) {
                if (currentElement.parentNode === formGroup) {
                  insertAfter = currentElement;
                  break;
                }
                currentElement = currentElement.parentNode;
              }
            }
            
            if (insertAfter && insertAfter.nextSibling) {
              formGroup.insertBefore(errorDiv, insertAfter.nextSibling);
            } else {
              formGroup.appendChild(errorDiv);
            }
          }
          
          // Tambahkan margin top untuk spacing
          errorDiv.style.marginTop = "4px";
          errorDiv.style.marginBottom = "0";
        } else if (
          formGroup.classList.contains("form-nexa-check") ||
          formGroup.classList.contains("nx-checkbox-item") ||
          formGroup.classList.contains("nx-radio-item") ||
          formGroup.classList.contains("nx-switch-item")
        ) {
          // Untuk checkbox/radio/switch, posisikan di bawah label
          errorDiv.style.marginTop = "4px";
          formGroup.appendChild(errorDiv);
        } else {
          // ✅ FIX: Untuk form-nexa, form-nexa-group, form-nexa-group1 - posisikan setelah element yang benar
          // Gunakan element yang sedang divalidasi, bukan querySelector yang bisa mengambil field lain
          let insertAfter = element;
          
          // Cari parent yang tepat (mungkin ada wrapper seperti form-nexa-icon)
          const inputWrapper = element.closest(".form-nexa-icon, .form-nexa, .form-nexa-group1");
          if (inputWrapper && inputWrapper.parentNode === formGroup) {
            // Jika element ada dalam wrapper di dalam formGroup, letakkan setelah wrapper
            insertAfter = inputWrapper;
          } else if (element.parentNode === formGroup) {
            // Jika element langsung child dari formGroup
            insertAfter = element;
          } else {
            // Cari wrapper yang tepat untuk element ini
            let currentElement = element.parentNode;
            while (currentElement && currentElement !== formGroup) {
              if (currentElement.parentNode === formGroup) {
                insertAfter = currentElement;
                break;
              }
              currentElement = currentElement.parentNode;
            }
          }
          
          // Insert error setelah element atau wrapper yang tepat
          if (insertAfter && insertAfter.nextSibling) {
            formGroup.insertBefore(errorDiv, insertAfter.nextSibling);
          } else if (insertAfter) {
            formGroup.appendChild(errorDiv);
          } else {
            formGroup.appendChild(errorDiv);
          }
          errorDiv.style.marginTop = "4px";
        }
      } else {
        // Fallback: jika formGroup tidak ditemukan, buat wrapper sementara
        // Cari parent container terdekat
        const parentContainer = element.closest(".form-nexa-body, .nx-modal-body, .nx-form-body, form");
        if (parentContainer) {
          // Buat error message dengan positioning relatif ke input
          const errorDiv = document.createElement("div");
          errorDiv.className = "error-message";
          errorDiv.textContent = message;
          errorDiv.style.fontSize = "0.65rem";
          errorDiv.style.color = "#dc3545";
          errorDiv.style.marginTop = "4px";
          
          // Coba insert setelah element atau parent terdekat
          const elementParent = element.parentNode;
          if (elementParent) {
            elementParent.insertBefore(errorDiv, element.nextSibling);
          } else {
            parentContainer.appendChild(errorDiv);
          }
          
          element.classList.add("is-invalid");
        }
      }
    };

    // Helper function untuk menangani floating label
    const handleFloatingLabel = (element) => {
      // Hanya untuk input yang mendukung floating label
      if (element.type === "checkbox" || element.type === "radio") {
        return;
      }

      // Cari label yang terkait
      const label =
        form.querySelector(`label[for="${element.id}"]`) ||
        element.closest(".form-nexa-floating")?.querySelector("label");

      if (label && label.tagName === "LABEL") {
        let hasValue = false;

        // Handle different input types
        if (element.type === "file") {
          hasValue = element.files && element.files.length > 0;
        } else {
          hasValue = element.value && element.value.trim() !== "";
        }

        if (hasValue) {
          label.classList.add("active");
        } else {
          label.classList.remove("active");
        }
      }
    };

    // ✅ FIX: Event listeners untuk validasi real-time - hanya untuk field yang ada di data validation
    form.querySelectorAll("[name]").forEach((element) => {
      if (!hasValidationRule(element)) {
        removeErrorClass(element);
        return;
      }

      const events = ["input", "change", "blur", "focus"];
      events.forEach((eventType) => {
        element.addEventListener(eventType, () => {
          // Handle floating label untuk semua input
          handleFloatingLabel(element);

          // Untuk single-select groups, validasi seluruh group
          if (
            element.classList.contains("single-select-checkbox") ||
            element.classList.contains("single-select-switch")
          ) {
            // Clear errors from all elements in the group first
            const allInGroup = form.querySelectorAll(
              `input[name="${element.name}"]`
            );
            allInGroup.forEach((el) => removeErrorClass(el));

            // Check if any element in group is selected
            const checkedInGroup = form.querySelectorAll(
              `input[name="${element.name}"]:checked`
            );
            if (checkedInGroup.length === 0) {
              // Show error on first element in group
              const firstInGroup = form.querySelector(
                `input[name="${element.name}"]`
              );
              if (firstInGroup) {
                const errorMessage = validateInput(firstInGroup);
                if (errorMessage) {
                  addErrorClass(firstInGroup, errorMessage);
                }
              }
            }
          } else {
            // Validasi normal untuk element lainnya
            const errorMessage = validateInput(element);
            if (errorMessage) {
              addErrorClass(element, errorMessage);
            } else {
              removeErrorClass(element);
            }
          }
        });
      });
    });

    // ✅ FIX: Tambahkan event listener khusus untuk select elements - hanya yang ada di data validation
    form.querySelectorAll("select[name]").forEach((element) => {
      if (!hasValidationRule(element)) {
        removeErrorClass(element);
        return;
      }
      
      const events = ["change", "blur", "focus"];
      events.forEach((eventType) => {
        element.addEventListener(eventType, () => {
          // Handle floating label untuk select
          handleFloatingLabel(element);

          const errorMessage = validateInput(element);
          if (errorMessage) {
            addErrorClass(element, errorMessage);
          } else {
            removeErrorClass(element);
          }
        });
      });
    });

    // ✅ FIX: Tambahkan event listener untuk input file - hanya yang ada di data validation
    form.querySelectorAll('input[type="file"]').forEach((element) => {
      if (!hasValidationRule(element)) {
        removeErrorClass(element);
        return;
      }
      
      const events = ["input", "change", "blur", "focus"];
      events.forEach((eventType) => {
        element.addEventListener(eventType, () => {
          // Handle floating label untuk file input
          handleFloatingLabel(element);

          // Validasi file
          const errorMessage = validateInput(element);
          if (errorMessage) {
            addErrorClass(element, errorMessage);
          } else {
            removeErrorClass(element);
          }
        });
      });
    });

    // Tangani submit form
    submitButton.addEventListener("click", async (e) => {
      e.preventDefault();

      // Create data object to store form values
      const dataObject = {
        // Include initial values from ret.value if they exist
        ...(ret.value || {}),
      };

      // Get all input elements from the form
      const inputs = form.querySelectorAll("[name]");

      // Helper function to reset form - moved inside scope where inputs is defined
      function resetForm() {
        // Reset all form inputs manually
        inputs.forEach((input) => {
          if (input.type === "file") {
            input.value = "";
          } else if (input.type === "checkbox" || input.type === "radio") {
            input.checked = false;
          } else {
            input.value = "";
          }
        });

        // 🔄 RESET FLOATING LABELS: Remove active class from all floating labels
        form
          .querySelectorAll(".form-nexa-floating label.active")
          .forEach((label) => {
            label.classList.remove("active");
          });

        // Clear file preview if exists
        const formResponse = document.querySelector(".form-nexa-file-preview");
        if (formResponse) {
          formResponse.innerHTML = "";
        }

        // Clear any existing error messages
        form.querySelectorAll(".form-error").forEach((element) => {
          element.classList.remove("form-error");
        });
        form.querySelectorAll(".error-message").forEach((element) => {
          element.remove();
        });
      }

      // Helper function to convert File to binary array (NO BASE64)
      const fileToBinaryArray = (file) => {
        return new Promise((resolve) => {
          const reader = new FileReader();
          reader.onload = function (e) {
            // Convert ArrayBuffer to Uint8Array then to regular array
            const arrayBuffer = e.target.result;
            const uint8Array = new Uint8Array(arrayBuffer);
            const byteArray = Array.from(uint8Array);
            resolve(byteArray); // Returns [255, 216, 255, ...] - raw bytes
          };
          reader.readAsArrayBuffer(file);
        });
      };

      // Collect form data manually instead of using FormData - now with async support
      for (const input of inputs) {
        if (input.type === "file") {
          // Handle file inputs separately
          if (input.files.length > 0) {
            if (input.multiple) {
              // Handle multiple files - convert each to binary array
              const filePromises = Array.from(input.files).map(async (file) => {
                const binaryArray = await fileToBinaryArray(file);
                return {
                  name: file.name,
                  size: file.size,
                  type: file.type,
                  content: binaryArray, // ✅ Binary array [255, 216, 255, ...]
                };
              });
              dataObject[input.name] = await Promise.all(filePromises);
            } else {
              // Handle single file - convert to binary array
              const file = input.files[0];
              const binaryArray = await fileToBinaryArray(file);
              dataObject[input.name] = {
                name: file.name,
                size: file.size,
                type: file.type,
                content: binaryArray, // ✅ Binary array instead of File object
              };
            }
          } else {
            // No files selected - keep null/empty
          }
        } else if (input.tagName.toLowerCase() === "select") {
          // Handle select elements
          if (input.multiple) {
            // Handle select-multiple
            const selectedValues = Array.from(input.selectedOptions).map(
              (option) => option.value
            );
            if (selectedValues.length > 0) {
              dataObject[input.name] = selectedValues;
            }
          } else {
            // Handle select-one
            if (input.value) {
              // For select elements, try to get the label of selected option
              const selectedOption = input.options[input.selectedIndex];
              const finalValue = selectedOption
                ? selectedOption.text
                : input.value;
              dataObject[input.name] = finalValue;
            }
          }
        } else if (input.type === "checkbox") {
          // Handle checkboxes
          if (input.checked) {
            // Use label from data-label attribute if available, otherwise use value
            const value = input.getAttribute("data-label") || input.value;

            // For single-select checkboxes and switches, store the selected value
            if (
              input.classList.contains("single-select-checkbox") ||
              input.classList.contains("single-select-switch")
            ) {
              dataObject[input.name] = value;
            } else {
              // For regular checkboxes, use boolean or collect multiple values
              if (dataObject[input.name]) {
                // If already exists, make it an array
                if (Array.isArray(dataObject[input.name])) {
                  dataObject[input.name].push(value);
                } else {
                  dataObject[input.name] = [dataObject[input.name], value];
                }
              } else {
                dataObject[input.name] = value;
              }
            }
          }
        } else if (input.type === "radio") {
          // Handle radio buttons
          if (input.checked) {
            // Use label from data-label attribute if available, otherwise use value
            const value = input.getAttribute("data-label") || input.value;
            dataObject[input.name] = value;
          }
        } else {
          // Handle regular inputs
          // ✅ Special handling for inputs with preset values (including search inputs that use type="text")
          if (input.hasAttribute('data-has-preset-value')) {
            if (!input.value || input.value.trim() === '') {
              // Use preset value if current value is empty
              const presetValue = input.getAttribute('data-preset-value');
              if (presetValue && presetValue.trim() !== '') {
                dataObject[input.name] = presetValue;
              }
            } else {
              dataObject[input.name] = input.value;
            }
          } else {
            // Only override if input has a value
            if (input.value) {
              dataObject[input.name] = input.value;
            }
          }
        }
      }

      // Hapus semua error message sebelum validasi ulang
      form.querySelectorAll(".error-message").forEach((errorMsg) => {
        errorMsg.remove();
      });
      form.querySelectorAll(".form-error").forEach((errorGroup) => {
        errorGroup.classList.remove("form-error");
      });
      form.querySelectorAll(".is-invalid").forEach((invalidInput) => {
        invalidInput.classList.remove("is-invalid");
      });

      // ✅ FIX: Validasi hanya field yang ada di data validation
      let isValid = true;
      const processedGroups = new Set(); // Track groups yang sudah diproses
      
      form.querySelectorAll("[name]").forEach((element) => {
        if (!hasValidationRule(element)) {
          removeErrorClass(element);
          return;
        }
        
        // Untuk single-select groups, hanya validasi sekali per group
        if (
          element.classList.contains("single-select-checkbox") ||
          element.classList.contains("single-select-switch") ||
          element.type === "radio"
        ) {
          const groupKey = `${element.name}-${
            element.classList.contains("single-select-checkbox")
              ? "checkbox"
              : element.classList.contains("single-select-switch")
              ? "switch"
              : "radio"
          }`;

          if (processedGroups.has(groupKey)) {
            return; // Skip jika group ini sudah diproses
          }
          processedGroups.add(groupKey);

          // Validasi group: cek apakah ada yang tercentang
          const checkedInGroup = form.querySelectorAll(
            `input[name="${element.name}"]:checked`
          );
          if (checkedInGroup.length === 0) {
            // Tampilkan error di elemen pertama dari group
            const firstInGroup = form.querySelector(
              `input[name="${element.name}"]`
            );
            if (firstInGroup) {
              const errorMessage = validateInput(firstInGroup);
              if (errorMessage) {
                addErrorClass(firstInGroup, errorMessage);
                isValid = false;
              }
            }
          }
        } else {
          // Validasi normal untuk element lainnya
          const errorMessage = validateInput(element);
          if (errorMessage) {
            addErrorClass(element, errorMessage);
            isValid = false;
          }
        }
      });

      if (isValid) {
        // Call callback if provided
        if (callback) {
          callback({
            response: dataObject,
          });
        }

        // Send data to server if endpoint is configured
        if (endpoint) {
          setLoadingState(true);
          try {
            const result = await sendToServer(dataObject);
            // showResponse(result); // Removed: tidak perlu menampilkan hasil respon di UI

            // Reset form after successful server response
            resetForm();
          } catch (error) {
            // showResponse(error, true); // Removed: tidak perlu menampilkan hasil respon di UI
            return; // Don't reset form on error
          } finally {
            setLoadingState(false);
          }
        } else if (!endpoint && callback) {
          // Reset form only if no endpoint but callback exists
          resetForm();
        }
      }
    });

    resolve({
      setValues: setFormValues, // Ekspos fungsi setValues
    });
  });
}
