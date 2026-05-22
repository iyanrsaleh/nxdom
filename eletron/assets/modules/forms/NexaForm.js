import { Validation } from "../validation/NexaValidation.js";
import { NexaFloating } from "../floating/NexaFloating.js";

// Helper function to convert File to binary array (same as NexaValidation.js)
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

// Function to setup real-time color sync
function setupColorSync(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const colorInputs = container.querySelectorAll('input[type="color"]');
  colorInputs.forEach((colorInput) => {
    if (colorInput.id) {
      const textInput = container.querySelector(`#${colorInput.id}Value`);
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
 * No-op: `Form/form.css` dimuat lewat `nexa.css` (@import) / `<link>` di HTML.
 * Tetap diekspor agar `NXUI.ensureFormStylesheet()` tidak patah.
 */
export async function ensureFormStylesheet() {
  if (typeof document === "undefined") return;
}

async function collectFormData(containerId, getFormBy = ["id"]) {
  const container = document.getElementById(containerId);
  if (!container) {
    return {};
  }

  const formData = {};
  const radioGroups = new Set(); // Track radio groups we've processed
  const checkboxGroups = new Set(); // Track checkbox groups we've processed
  const collectionMethod = Array.isArray(getFormBy) ? getFormBy : [getFormBy];

  // Find all form elements in the container
  let formElements = container.querySelectorAll("input, select, textarea");

  // If using data-order method, also include all elements with data-order attribute
  if (collectionMethod.includes("data-order")) {
    const dataOrderElements = container.querySelectorAll("[data-order]");
    // Combine form elements with data-order elements (remove duplicates)
    const allElements = new Set([...formElements, ...dataOrderElements]);
    formElements = Array.from(allElements);
  }

  for (const element of formElements) {
    if (element.dataset?.collect === "false") {
      continue;
    }

    let key = null;

    // Special handling for radio buttons - use name as group key
    if (element.type === "radio" && element.name) {
      // For radio buttons, always use the name as the key for grouping
      key = element.name;

      // Skip if we've already processed this radio group
      if (radioGroups.has(key)) {
        continue;
      }

      // Mark this radio group as processed
      radioGroups.add(key);

      // Find the checked radio in this group
      const checkedRadio = container.querySelector(
        `input[type="radio"][name="${element.name}"]:checked`
      );
      if (checkedRadio) {
        // Only collect the group value - no individual radio states to avoid database confusion
        formData[key] = checkedRadio.value;
      } else {
        // No radio selected in this group
        formData[key] = null;
      }
      continue;
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
        continue;
      }

      // Mark this checkbox group as processed
      checkboxGroups.add(key);

      // Find all checked checkboxes in this group
      const groupName =
        element.name || element.getAttribute("data-original-name");
      const checkedCheckboxes = container.querySelectorAll(
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

      continue; // Skip normal processing for grouped checkboxes
    }

    // Skip text inputs that are companions to color pickers (e.g., favoriteColorValue)
    if (element.type === "text" && element.id && element.id.endsWith("Value")) {
      const colorPickerId = element.id.replace("Value", "");
      const colorPicker = container.querySelector(`#${colorPickerId}`);
      if (colorPicker && colorPicker.type === "color") {
        continue; // Skip this text input - it's just a companion to color picker
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

              // Return actual File objects so caller can use FormData for upload
              formData[key] = Array.from(element.files);
            } else {
              // Return actual File object so caller can use FormData for upload
              formData[key] = element.files[0];
            }
          } else {
            // No files selected
            formData[key] = element.multiple ? [] : null;
          }
        } else if (element.type === "color") {
          // For color inputs, collect the current effective value
          const textInput = container.querySelector(`#${key}Value`);
          if (textInput && textInput.value && textInput.value !== "") {
            // If text input has value, use that (user might have typed hex manually)
            formData[key] = textInput.value;
          } else {
            // Otherwise use color picker value
            formData[key] = element.value;
          }
          // Note: Don't collect textInput.id separately - only collect the main color field
        } else {
          if (
            typeof element.value === "string" &&
            key.startsWith("selected_field_") &&
            element.value.trim().startsWith("[")
          ) {
            try {
              formData[key] = JSON.parse(element.value);
            } catch (e) {
              formData[key] = element.value;
            }
          } else {
            formData[key] = element.value;
          }
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
  }

  return formData;
}

// Function to create form with onclick handling (compatible with old syntax)
async function NexaForm(data) {
  const Buckets=data?.storage?.form ?? data.setDataBy.form;
  const checkedForm = Object.values(Buckets).filter(item => item.condition === true);
  const validation = checkedForm.map(item => ({
     id: item?.id ?? item.name,
     name: item.name,
     validation: Number(item.validation)
   }));
  


  // Extract setDataBy parameter for callback usage
  const setDataBy = data.setDataBy || null;
  const preserveUpdateValues = (latestData) => {
    const rid = setDataBy?.recordId;
    const isUpdateMode =
      rid !== null &&
      rid !== undefined &&
      rid !== "" &&
      !(typeof rid === "number" && !Number.isFinite(rid));

    if (!isUpdateMode || !latestData || typeof latestData !== "object") {
      return;
    }

    if (setDataBy?.form && typeof setDataBy.form === "object") {
      for (const [fieldName, value] of Object.entries(latestData)) {
        const spec = setDataBy.form[fieldName];
        if (!spec || typeof spec !== "object" || Array.isArray(spec)) continue;
        setDataBy.form[fieldName] = { ...spec, value };
      }
    }

    if (floatingInstance && typeof floatingInstance.setData === "function") {
      floatingInstance.setData(latestData);
    }
  };

  const resolveHandler = (functionName) => {
    if (!functionName) {
      return null;
    }

    if (window.nx && typeof window.nx[functionName] === "function") {
      return window.nx[functionName];
    }

    if (window.NXUI && typeof window.NXUI[functionName] === "function") {
      return window.NXUI[functionName];
    }

    if (typeof window[functionName] === "function") {
      return window[functionName];
    }

    if (
      window.nx &&
      window.nx._global &&
      typeof window.nx._global[functionName] === "function"
    ) {
      return window.nx._global[functionName];
    }

    return null;
  };

  // Create form elements using native JavaScript DOM methods
  const formId = data.elementById || "myForm";

  // Create main form container
  const form = document.createElement("div");
  form.className = "form-container";
  form.id = formId;

  // Store form configuration
  form._nexaFormConfig = { ...data };

  // Create form header if label provided
  if (data.label) {
    const formHeader = document.createElement("div");
    formHeader.className = "form-header";

    const formTitle = document.createElement("h5");
    formTitle.className = "form-title";
    formTitle.textContent = data.label;

    formHeader.appendChild(formTitle);
    form.appendChild(formHeader);
  }

  // Create form body
  const formBody = document.createElement("div");
  formBody.className = "form-body";
  formBody.id = "body_" + formId;

  let floatingInstance = null;
  if (data.floating) {
    const rid = data?.setDataBy?.recordId;
    console.log('rid:', rid);
    if (rid) {
      NXUI.Rid=Number(rid);
    } else {
      NXUI.Rid=false;

    }
    const hasRecordId =
      rid !== null &&
      rid !== undefined &&
      rid !== "" &&
      !(typeof rid === "number" && !Number.isFinite(rid));
    const floatingMode = hasRecordId ? "update" : "insert";

    floatingInstance = new NexaFloating(data.floating, {
      formById: data.floating.id || data.floating.modalid,
      mode: floatingMode,
    });

    // Append formElement ke formBody (masih detached dari DOM nyata).
    // Select2 akan diinit setelah form masuk ke DOM di bawah.
    floatingInstance.render(formBody);
  } else if (data.content) {
    formBody.innerHTML = data.content;
  }

  form.appendChild(formBody);

  // Create footer if onclick provided
  if (data.onclick && data.onclick.send) {
    const formFooter = document.createElement("div");
    formFooter.className = "form-footer";
    formFooter.id = "footer" + formId;
    if (data.onclick.buttonsFull) {
      formFooter.style.cssText = "display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 0;";
    } else {
      formFooter.style.cssText = "display: flex; justify-content: flex-end; align-items: center; width: 100%; gap: 8px; padding: 12px 0 4px;";
    }

    // Create custom footer content container (left side)
    const customFooterContainer = document.createElement("div");
    customFooterContainer.className = "form-footer-custom";
    customFooterContainer.style.cssText = "flex: 1;";

    // Add custom footer content if specified
    if (data.footer) {
      customFooterContainer.innerHTML = data.footer;
    }

    // Create default buttons container (right side)
    const buttonsContainer = document.createElement("div");
    buttonsContainer.className = "form-footer-buttons";
    if (data.onclick.buttonsFull) {
      buttonsContainer.style.cssText = "display: flex; gap: 8px; width: 100%;";
      customFooterContainer.style.display = "none";
    } else {
      buttonsContainer.style.cssText = "display: flex; gap: 8px;";
    }

    const cancelButton = document.createElement("button");
    cancelButton.type = "button";
    cancelButton.className = data.onclick.cancelClass || "btn";
    if (data.onclick.buttonsFull) cancelButton.style.flex = "1";
    cancelButton.textContent = typeof data.onclick.cancel === 'string' ? data.onclick.cancel : "Batal";
    cancelButton.onclick = async () => {
      const resetHandlerName = data.onclick.reset;
      const resetHandler = resolveHandler(resetHandlerName);
      const shouldCloseOnCancel = data.onclick.closeOnCancel === true;

      if (resetHandler) {
        const formData = await collectFormData(formId, data.getFormBy);
        resetHandler(formId, formData, setDataBy);
      }

      // Default behavior: keep form visible. Only close when explicitly requested.
      if (shouldCloseOnCancel) {
        const targetElement = document.getElementById(formId);
        if (targetElement) {
          targetElement.innerHTML = "";
        }
      }
    };

    const saveButton = document.createElement("button");
    saveButton.type = "button";
    saveButton.className = data.onclick.submitClass || "btn btn-primary";
    if (data.onclick.buttonsFull) saveButton.style.flex = "1";
    const _saveTitle = data.onclick.title || "Simpan";
    if (data.onclick.submitIcon) {
      const iconSpan = document.createElement("span");
      iconSpan.className = data.onclick.submitIcon;
      saveButton.appendChild(iconSpan);
      saveButton.appendChild(document.createTextNode(" " + _saveTitle));
    } else {
      saveButton.textContent = _saveTitle;
    }

    if (data.onclick.validation || data.getValidationBy) {
      // Setup validation integration
      saveButton.id = "submit-" + formId;

      const enhancedValidation = data.onclick.validation || {};

      // Konversi array validation menjadi object sesuai getValidationBy
      const getValidationBy = data.getValidationBy || ["name"];
      const validationByKey = Array.isArray(getValidationBy) ? getValidationBy[0] : getValidationBy;
      
      // ✅ FIX: Buat object validasi dengan key berdasarkan getValidationBy, dan juga tambahkan key berdasarkan id dan name untuk memastikan matching
      const validasiObject = {};
      validation.forEach((item) => {
        const key = item[validationByKey] || item.name || item.id;
        if (key) {
          validasiObject[key] = item.validation;
        }
        // ✅ FIX: Tambahkan juga entry dengan id dan name sebagai key untuk memastikan matching bekerja dengan baik
        if (item.id && item.id !== key) {
          validasiObject[item.id] = item.validation;
        }
        if (item.name && item.name !== key && item.name !== item.id) {
          validasiObject[item.name] = item.validation;
        }
      });
   
      const validationConfig = {
        formid: `#${formId}`,
        submitid: `#submit-${formId}`,
        fileInput: true,
        failed: validation,
        validasi: validasiObject,
        getFormBy: data.getFormBy || ["id"],
        getValidationBy: data.getValidationBy || ["name"],
      };

      // Initialize validation and setup validated data collection
      setTimeout(() => {
        Validation(validationConfig, async (result) => {
          // Use the data that NexaValidation already collected
          const formData = result.response;
      
          const functionName = data.onclick.send;
          const targetFunction = resolveHandler(functionName);

          if (targetFunction) {
            await Promise.resolve(targetFunction(formId, formData, setDataBy));
            preserveUpdateValues(formData);
          }
        });
      }, 200);
    } else {
      // No validation - direct data collection
      saveButton.onclick = async () => {
        // Auto-collect form data from form
        const formData = await collectFormData(formId, data.getFormBy);

        const funcName = data.onclick.send;
        const targetFunction = resolveHandler(funcName);

        if (targetFunction) {
          await Promise.resolve(targetFunction(formId, formData, setDataBy));
          preserveUpdateValues(formData);
        }
      };
    }

    if (data.onclick.cancel !== false) {
      buttonsContainer.appendChild(cancelButton);
    }
    buttonsContainer.appendChild(saveButton);

    formFooter.appendChild(customFooterContainer);
    formFooter.appendChild(buttonsContainer);
    form.appendChild(formFooter);
  }

  // Clear existing content in target element before adding new form
  const targetElement = document.getElementById(formId);
  if (targetElement) {
    // Clear existing content in target element
    targetElement.innerHTML = "";

    // Store callback data if provided
    if (data.callback) {
      const callbackMethod = data.callback.method || "";
      const callbackData = JSON.stringify(data.callback.data || {});
      form.setAttribute("data-callback-method", callbackMethod);
      form.setAttribute("data-callback-data", callbackData);
    }

    // Insert form into DOM
    targetElement.appendChild(form);

    // Inisialisasi Select2 SETELAH form masuk ke DOM nyata (isConnected=true)
    if (floatingInstance) {
      floatingInstance.waitForSelect2AndInitialize();
    }
  } else {
    // Fallback: jika target element tidak ditemukan, masukkan sebelum nexa_main
    $("#nexa_main").before(form);
  }

  // Setup real-time color sync after form is inserted into DOM
  setTimeout(() => {
    setupColorSync(formId);
  }, 100);

  return form;
}

// Export the essential functions
export { collectFormData, fileToBinaryArray, setupColorSync, NexaForm };
