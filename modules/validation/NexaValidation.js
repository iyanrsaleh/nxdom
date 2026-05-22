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
    if (!submitButton) return;
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
    const fileInputs = form.querySelectorAll(".form-group input[type='file']");

    fileInputs.forEach((input) => {
      const container = input.closest(".form-group");
      const dragDropArea = container.querySelector(".file-upload-area");
      const preview = container.querySelector(".file-preview");
      const fileList = container.querySelector(".file-list");
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

        // Reset error message and hide error anchor
        if (errorMessage) {
          errorMessage.textContent = "";
          errorMessage.style.display = "none";
        }
        // Also reset error anchor in label (if present)
        const errorAnchor = container.querySelector(`#errors_${input.name}`);
        if (errorAnchor) {
          errorAnchor.textContent = "";
          errorAnchor.style.display = "none";
          errorAnchor.style.color = "";
        }

        // Validate number of files
        if (input.multiple && files.length > maxFiles) {
          const msg = `Maksimal ${maxFiles} file yang dapat diunggah`;
          if (errorMessage) {
            errorMessage.textContent = msg;
            errorMessage.style.display = "block";
            errorMessage.style.color = "#dc3545";
          }
          if (errorAnchor) {
            errorAnchor.textContent = msg;
            errorAnchor.style.display = "inline";
            errorAnchor.style.color = "#dc3545";
          }
          input.value = "";
          return;
        }

        // Validate file size
        const maxSizeBytes = parseFileSize(maxSize);
        const totalSize = files.reduce((sum, file) => sum + file.size, 0);

        if (totalSize > maxSizeBytes) {
          const msg = `Total ukuran file tidak boleh lebih dari ${maxSize}`;
          if (errorMessage) {
            errorMessage.textContent = msg;
            errorMessage.style.display = "block";
            errorMessage.style.color = "#dc3545";
          }
          if (errorAnchor) {
            errorAnchor.textContent = msg;
            errorAnchor.style.display = "inline";
            errorAnchor.style.color = "#dc3545";
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
  const submitButton =
    getElement(submitSelector) ||
    form.querySelector('button[type="submit"], input[type="submit"], .btn-submit, [data-submit]');

  // Store original submit button text for loading state
  if (submitButton && !submitButton.dataset.originalText) {
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
      const elementName = element.name;
      // Handle hidden input khusus - mereka tidak memiliki formGroup visible
      if (element.type === "hidden") {
        let errorMessage = document.getElementById(`errors_${elementName}`);
        if (errorMessage) {
          errorMessage.style.display = "none";
          errorMessage.textContent = "";
        }
        element.classList.remove("is-invalid");
        return;
      }
      // Untuk group field, hapus error anchor group label jika valid
      if (["checkbox", "radio"].includes(element.type) || element.classList.contains("nx-switch-input") || element.classList.contains("single-select-switch")) {
        // Hapus error hanya di anchor group label
        let errorMessage = document.getElementById(`errors_${elementName}`);
        if (errorMessage) {
          errorMessage.style.display = "none";
          errorMessage.textContent = "";
        }
        // Hapus error class dari .form-group
        const groupDiv = element.closest('.form-group');
        if (groupDiv) groupDiv.classList.remove('error');
        // Jangan hapus is-invalid dari setiap input option (karena tidak dipakai)
        return;
      }
      // Hapus error normal untuk field lain
      let formGroup = element.closest(
        ".form-group"
      );
      if (formGroup) {
        formGroup.classList.remove("error");
        let errorMessage = document.getElementById(`errors_${elementName}`);
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
          ".form-group, .form-checkbox"
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
            return ` wajib diisi`;
          }
        } else if (typeof validationValue === 'number' && validationValue > 2) {
          // Numeric validation value > 2 means minLength (e.g., 10, 150)
          const minLength = validationValue;
          
          // Jika field kosong dan ada validasi minLength, langsung return error
          if (!element.value || !element.value.trim()) {
            return `tidak boleh kosong (minimal ${minLength} karakter)`;
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
            return `harus dipilih`;
          }
          break;

        case "checkbox":
          // For single-select checkboxes, check if any option is selected
          if (element.classList.contains("single-select-checkbox")) {
            const sameName = document.querySelectorAll(
              `input[name="${element.name}"].single-select-checkbox:checked`
            );
            if (sameName.length === 0) {
              return `harus dipilih`;
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
              return ` harus dipilih`;
            }
            // If current element is not checked but others are, it's valid (skip validation)
            if (!element.checked && sameName.length > 0) {
              return null;
            }
          } else {
            // Untuk checkbox tunggal biasa
            if (!element.checked) {
              return `harus dicentang`;
            }
          }
          break;

        case "select-one":
          if (!element.value || element.value === "") {
            return `wajib dipilih`;
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
            return `file belum diupload`;
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

      // === UNIVERSAL ERROR ANCHOR LOGIC (label/anchor placement) ===
      // Try to find the label for this element
      let labelEl = null;
      if (element.id) {
        labelEl = document.querySelector(`label[for='${element.id}']`);
      }
      if (!labelEl && element.name) {
        labelEl = document.querySelector(`label[for='${element.name}']`);
      }
      // If still not found, try to find the closest label in the same form group
      if (!labelEl) {
        const group = element.closest('.form-group, .form-checkbox');
        if (group) {
          labelEl = group.querySelector('label');
        }
      }
      // Place or update error anchor after label if possible
      let errorAnchor = null;
      if (labelEl) {
        // Try to find existing error anchor after label
        errorAnchor = labelEl.nextElementSibling && labelEl.nextElementSibling.id === `errors_${element.name}`
          ? labelEl.nextElementSibling
          : document.getElementById(`errors_${element.name}`);
        if (!errorAnchor) {
          errorAnchor = document.createElement('span');
          errorAnchor.className = 'error';
          errorAnchor.id = `errors_${element.name}`;
          errorAnchor.setAttribute('data-field-name', element.name);
          errorAnchor.style.display = 'inline';
          errorAnchor.style.color = '#dc3545';
          // --- PATCH: For file/flag, always insert after label (not inside input wrapper)
          if (element.type === 'file' || element.classList.contains('flag-kabupaten-select') || element.classList.contains('flag-kecamatan-select') || element.classList.contains('flag-desa-select')) {
            labelEl.parentNode.insertBefore(errorAnchor, labelEl.nextSibling);
          } else {
            labelEl.parentNode.insertBefore(errorAnchor, labelEl.nextSibling);
          }
        }
        errorAnchor.textContent = message;
        errorAnchor.style.display = 'inline';
        errorAnchor.style.color = '#dc3545';
      }

      // === KHUSUS FILE INPUT ===
      if (element.type === "file") {
        // Cari warnId sesuai NexaFloating/_fileFields.js: id="{element.id}-warn"
        const warnId = `${element.id}-warn`;
        const warnSpan = document.getElementById(warnId);
        if (warnSpan) {
          warnSpan.textContent = message;
          warnSpan.style.display = "inline";
          warnSpan.style.color = "red";
        }
        // Tampilkan juga error di anchor header (id=errors_{name})
        let errorHeader = document.getElementById(`errors_${element.name}`);
        if (errorHeader) {
          errorHeader.textContent = message;
          errorHeader.style.display = "inline";
          errorHeader.style.color = "#dc3545";
        }
        // Tambahkan class error pada .form-group col-12 (pastikan parent benar)
        let groupDiv = element.closest('.form-group');
        if (groupDiv) {
          groupDiv.classList.add('error');
          // Jika parent .form-group col-12, tambahkan juga di parentnya
          if (groupDiv.classList.contains('col-12') && groupDiv.parentElement && groupDiv.parentElement.classList.contains('form-group')) {
            groupDiv.parentElement.classList.add('error');
          }
        } else {
          // Fallback: cari .form-group.col-12 di atasnya
          let parent = element.parentElement;
          while (parent) {
            if (parent.classList && parent.classList.contains('form-group') && parent.classList.contains('col-12')) {
              parent.classList.add('error');
              break;
            }
            parent = parent.parentElement;
          }
        }
        element.classList.add("is-invalid");
        return;
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
        const elementName = element.name;
        // Cek apakah error element sudah ada
        let errorElement = document.getElementById(`errors_${elementName}`);
        if (!errorElement) {
          // Jika tidak ada, buat error element baru setelah hidden input
          errorElement = document.createElement("small");
          errorElement.className = "error-message";
          errorElement.id = `errors_${elementName}`;
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
        formGroup = element.closest(
          ".form-group, .form-checkbox"
        );
        if (formGroup && !formGroup.contains(element)) {
          formGroup = null;
        }
        if (!formGroup) {
          let parent = element.parentElement;
          while (parent && parent !== form) {
            if (parent.classList.contains("form-group") || 
              parent.classList.contains("form-checkbox")) {
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
            if (currentParent.classList.contains("form-group") || 
              currentParent.classList.contains("form-checkbox") ||
              currentParent.classList.contains("nx-checkbox-item") ||
              currentParent.classList.contains("nx-radio-item") ||
              currentParent.classList.contains("nx-switch-item")) {
            formGroup = currentParent;
            break;
          }
          currentParent = currentParent.parentElement;
        }
      }

      const elementName = element.name;
      // Untuk group field, error hanya di anchor group label (id=errors_{element.name}), tidak per option
      const isGroupField = ["checkbox", "radio"].includes(element.type) || element.classList.contains("nx-switch-input") || element.classList.contains("single-select-switch");
      if (isGroupField) {
        // Hanya update anchor group label sekali, jangan per option
        // Hanya update jika ini adalah option pertama di group (atau yang sedang di-blur/di-submit)
        // Cek jika sudah ada error anchor, update di situ
        let errorEl = document.getElementById(`errors_${elementName}`);
        if (errorEl) {
          errorEl.textContent = message;
          errorEl.style.display = "inline";
        } else {
          // Fallback: cari .form-group-header lalu append anchor
          const formGroup = element.closest('.form-group');
          if (formGroup) {
            let header = formGroup.querySelector('.form-group-header');
            if (!header) header = formGroup;
            const errorSpan = document.createElement("span");
            errorSpan.className = "error";
            errorSpan.id = `errors_${elementName}`;
            errorSpan.setAttribute("data-field-name", elementName);
            errorSpan.textContent = message;
            errorSpan.style.display = "inline";
            header.appendChild(errorSpan);
          }
        }
        // Tambahkan error class hanya ke .form-group, bukan ke setiap input option
        const groupDiv = element.closest('.form-group');
        if (groupDiv) groupDiv.classList.add('error');
        // Jangan tambahkan is-invalid ke setiap input option
        return;
      }
      // --- Standar untuk field lain (text, email, dsb) ---
      if (formGroup) {
        formGroup.classList.add("error");
        element.classList.add("is-invalid");
        let errorEl = document.getElementById(`errors_${elementName}`);
        if (errorEl) {
          errorEl.textContent = message;
          errorEl.style.display = "inline";
        } else {
          const errorSpan = document.createElement("span");
          errorSpan.className = "error";
          errorSpan.id = `errors_${elementName}`;
          errorSpan.setAttribute("data-field-name", elementName);
          errorSpan.textContent = message;
          errorSpan.style.display = "inline";
          const header = formGroup.querySelector(".form-group-header");
          if (header) {
            header.appendChild(errorSpan);
          } else {
            formGroup.appendChild(errorSpan);
          }
        }
      } else {
        const parentContainer = element.closest(".form-nexa-body, .nx-modal-body, .nx-form-body, form");
        if (parentContainer) {
          const errorDiv = document.createElement("div");
          errorDiv.className = "error-message";
          errorDiv.textContent = message;
          errorDiv.style.fontSize = "0.65rem";
          errorDiv.style.color = "#dc3545";
          errorDiv.style.marginTop = "4px";
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

    // Tangani submit form (fallback ke event submit form jika tombol selector tidak ditemukan)
    const submitEventTarget = submitButton || form;
    const submitEventName = submitButton ? "click" : "submit";
    submitEventTarget.addEventListener(submitEventName, async (e) => {
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
           
           // let errorEl = document.getElementById(`errors_${elementName}`);
          } else if (input.type === "checkbox" || input.type === "radio") {
            input.checked = false;
          } else {
            input.value = "";
          }
        });

        // 🔄 RESET FLOATING LABELS: Remove active class from all floating labels
        form
          .querySelectorAll(".form-group label.active")
          .forEach((label) => {
            label.classList.remove("active");
          });

        // Clear file preview if exists
        const formResponse = document.querySelector(".form-nexa-file-preview");
        if (formResponse) {
          formResponse.innerHTML = "";
        }

        // Clear any existing error messages
        form.querySelectorAll(".error").forEach((element) => {
          element.classList.remove("error");
        });
        form.querySelectorAll(".error-message").forEach((element) => {
          element.remove();
        });
      }

      // Collect form data manually — sama seperti NexaWild + collectFormData: simpan File (bukan binary).
      for (const input of inputs) {
        if (input.type === "file") {
          if (input.files.length > 0) {
            if (input.multiple) {
              dataObject[input.name] = Array.from(input.files);
            } else {
              dataObject[input.name] = input.files[0];
            }
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
      form.querySelectorAll(".error").forEach((errorGroup) => {
        errorGroup.classList.remove("error");
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
