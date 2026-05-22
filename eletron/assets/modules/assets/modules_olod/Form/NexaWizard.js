import { setFlag } from "./NexaFlag.js";
import { NexaSlug } from "./NexaSlug.js";
import { getIconByType } from "./icon.js";
import { ensureFormStylesheet } from "./NexaForm.js";

// ✅ CRITICAL: Helper function to convert File to binary array
const fileToBinaryArray = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const arrayBuffer = reader.result;
      const uint8Array = new Uint8Array(arrayBuffer);
      const binaryArray = Array.from(uint8Array);
      resolve(binaryArray);
    };
    reader.onerror = () => reject(reader.error);
    reader.readAsArrayBuffer(file);
  });
};

export function generateUniqueName(name = "") {
  const clean = name.trim().toLowerCase().replace(/\s+/g, "_");
  const rand = Math.random().toString(36).substring(2, 10); // 8 karakter acak
  const time = Date.now().toString(36); // waktu unik
  return `${clean}_${time}_${rand}`;
}

export class NexaWizard {
  constructor(formData, options = {}) {
    // ✅ Menggunakan format baru langsung
    this.formData = formData || {};
    this.form = formData.form || {};
    this.settings = formData.settings || {};
    // Validasi struktur data baru
    if (!this.formData.form || typeof this.formData.form !== "object") {
      throw new Error(
        'NexaWizard: Invalid form data structure. Expected "form" property with field definitions.'
      );
    }

    this.options = {
      footer: true, // Default: tampilkan footer/submit button
      mode: options.mode    ?? "insert",
      value: options.value  ??{},
      ...options, // Merge with user options
    };
   

    // Ambil ID form dari struktur baru
    this.formId = this.formData.id || this.formData.modalid || "nexaForm";
    this.className = this.formData.className || "Form";
    this.tableName = this.formData.tableName || "Data";
    this.label = this.formData.label || "Form";
    this.value = this.options.value ?? {};
    this._wizardStep = 0;
    this._wizardStepCount = 0;
    this._wizardStepGroups = [];
   
    this.init();
  }

  init() {
    this.generateForm();
  }

  generateForm() {
    void ensureFormStylesheet();
    const formHTML = this.buildFormHTML();
    this.formElement = this.createFormElement(formHTML);
    this.initWizardState();
    this.attachEventListeners();
  }

  /**
   * Konfigurasi wizard: `settings.wizard` atau `options.wizard`.
   * - `steps`: array nama field per langkah, mis. [["nama","email"],["tel"]]
   * - `fieldsPerStep`: jumlah field per langkah jika `steps` tidak diisi (default 2)
   * - `titles`: judul tiap langkah (opsional)
   */
  getWizardConfig() {
    const w =
      this.formData.settings?.wizard ||
      this.options.wizard ||
      {};
    const labels = w.labels || {};
    return {
      fieldsPerStep: Math.max(1, Number(w.fieldsPerStep) || 2),
      steps: Array.isArray(w.steps) ? w.steps : null,
      titles: Array.isArray(w.titles) ? w.titles : [],
      showProgress: w.showProgress !== false,
      labels: {
        prev: labels.prev || w.prevLabel || "Sebelumnya",
        next: labels.next || w.nextLabel || "Berikutnya",
        submit: labels.submit || w.submitLabel || "Submit",
      },
    };
  }

  /**
   * Kelompokkan field menjadi beberapa langkah (array of [name, config][]).
   */
  getWizardStepGroups() {
    const ordered = this.getOrderedFields();
    const filtered = [];
    for (const [name, cfg] of ordered) {
      if (name === "id" && this.options.mode === "insert") {
        continue;
      }
      filtered.push([name, cfg]);
    }
    const cfg = this.getWizardConfig();
    if (cfg.steps && cfg.steps.length) {
      const map = new Map(filtered);
      const groups = [];
      for (const stepNames of cfg.steps) {
        const g = [];
        for (const n of stepNames) {
          if (map.has(n)) {
            g.push([n, map.get(n)]);
          }
        }
        if (g.length) {
          groups.push(g);
        }
      }
      const used = new Set();
      groups.forEach((g) => g.forEach(([n]) => used.add(n)));
      const rest = filtered.filter(([n]) => !used.has(n));
      if (rest.length) {
        groups.push(rest);
      }
      return groups.length ? groups : [filtered];
    }
    const n = cfg.fieldsPerStep;
    const groups = [];
    for (let i = 0; i < filtered.length; i += n) {
      groups.push(filtered.slice(i, i + n));
    }
    return groups.length ? groups : [[]];
  }

  escapeHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/"/g, "&quot;");
  }

  buildWizardFooter(totalSteps) {
    if (!this.options.footer || totalSteps <= 1) {
      return "";
    }
    const cfg = this.getWizardConfig();
    return `
<div class="form-nexa-wizard-buttons">
  <button type="button" class="nx-btn-secondary-outline is-small nx-wizard-prev" aria-label="${this.escapeHtml(cfg.labels.prev)}">${this.escapeHtml(cfg.labels.prev)}</button>
  <button type="button" class="nx-btn-primary is-small nx-wizard-next" aria-label="${this.escapeHtml(cfg.labels.next)}">${this.escapeHtml(cfg.labels.next)}</button>
  <button type="submit" class="nx-btn-primary is-small nx-wizard-submit" style="display:none" aria-label="${this.escapeHtml(cfg.labels.submit)}">${this.escapeHtml(cfg.labels.submit)}</button>
</div>`;
  }

  initWizardState() {
    this._wizardStepGroups = this.getWizardStepGroups();
    this._wizardStepCount = this._wizardStepGroups.length;
    this._wizardStep = 0;
    if (this._wizardStepCount > 1) {
      this.syncWizardUI();
    }
  }

  syncWizardUI() {
    if (!this.formElement || this._wizardStepCount <= 1) {
      return;
    }
    const progressSteps = this.formElement.querySelectorAll(
      ".form-nexa-wizard-progress-step"
    );
    progressSteps.forEach((el, i) => {
      el.classList.remove("active", "completed");
      if (i < this._wizardStep) {
        el.classList.add("completed");
      } else if (i === this._wizardStep) {
        el.classList.add("active");
      }
    });
    const panels = this.formElement.querySelectorAll(".form-nexa-wizard-step");
    panels.forEach((el, i) => {
      el.classList.toggle("active", i === this._wizardStep);
    });
    const prev = this.formElement.querySelector(".nx-wizard-prev");
    const next = this.formElement.querySelector(".nx-wizard-next");
    const submit = this.formElement.querySelector(".nx-wizard-submit");
    if (prev) {
      prev.disabled = this._wizardStep === 0;
    }
    if (next) {
      next.style.display =
        this._wizardStep >= this._wizardStepCount - 1 ? "none" : "";
    }
    if (submit) {
      submit.style.display =
        this._wizardStep >= this._wizardStepCount - 1 ? "" : "none";
    }
  }

  async validateCurrentWizardStep() {
    const groups = this._wizardStepGroups.length
      ? this._wizardStepGroups
      : this.getWizardStepGroups();
    const step = groups[this._wizardStep];
    if (!step || !step.length) {
      return true;
    }
    const formData = await this.getFormData();
    const errors = {};
    let isValid = true;

    for (const [fieldName, fieldConfig] of step) {
      if (fieldName === "id" && this.options.mode === "insert") {
        continue;
      }
      const value = formData[fieldName];
      const validation = fieldConfig.validation;
      const placeholder =
        fieldConfig.placeholder || fieldConfig.label || fieldName;

      if (validation === "2" || validation === true || validation === 2) {
        if (!value || (typeof value === "object" && !value.value)) {
          errors[fieldName] = `${placeholder} wajib diisi`;
          isValid = false;
        }
      }

      if (value) {
        switch (fieldConfig.type) {
          case "email": {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
              errors[fieldName] = `${placeholder} harus berupa email yang valid`;
              isValid = false;
            }
            break;
          }
          case "number":
            if (isNaN(value)) {
              errors[fieldName] = `${placeholder} harus berupa angka`;
              isValid = false;
            }
            break;
          case "url":
            try {
              new URL(value);
            } catch {
              errors[fieldName] = `${placeholder} harus berupa URL yang valid`;
              isValid = false;
            }
            break;
          default:
            break;
        }
      }
    }

    if (!isValid) {
      this.displayErrors(errors);
      return false;
    }
    this.clearErrors();
    return true;
  }

  buildFormHTML() {
    const style = this.getFormStyle();
    const isGrid = this.hasGridColumns();

    const formClass = style.floating
      ? "form-nexa nexa-floating-form form-nexa-wizard"
      : "form-nexa form-nexa-wizard";
    let formHTML = ``;

    // Check if form contains file inputs
    const hasFileInputs = Object.values(this.formData.form || {}).some(
      (field) => field.type === "file"
    );
    const enctype = hasFileInputs ? ' enctype="multipart/form-data"' : "";

    this._wizardStepGroups = this.getWizardStepGroups();
    const stepGroups = this._wizardStepGroups;
    const wizardCfg = this.getWizardConfig();
    const multi = stepGroups.length > 1;

    formHTML += `<form id="${this.formId}" class="${formClass}"${enctype} data-nexa-wizard="${multi ? "1" : "0"}">`;

    if (multi && wizardCfg.showProgress) {
      formHTML += `<div class="form-nexa-wizard-progress" role="navigation" aria-label="Langkah formulir">`;
      stepGroups.forEach((_, i) => {
        const title =
          wizardCfg.titles[i] || `Langkah ${i + 1}`;
        const stepCls =
          i === 0 ? "form-nexa-wizard-progress-step active" : "form-nexa-wizard-progress-step";
        formHTML += `<div class="${stepCls}" data-wizard-progress-index="${i}">`;
        formHTML += `<div class="step-number">${i + 1}</div>`;
        formHTML += `<div class="step-text">${this.escapeHtml(title)}</div>`;
        formHTML += `</div>`;
      });
      formHTML += `</div>`;
    }

    stepGroups.forEach((fields, stepIndex) => {
      const panelCls =
        stepIndex === 0
          ? "form-nexa-wizard-step active"
          : "form-nexa-wizard-step";
      formHTML += `<div class="${panelCls}" data-wizard-step="${stepIndex}" role="group">`;
      if (isGrid) {
        formHTML += `<div class="nx-row">`;
      }
      for (const [fieldName, fieldConfig] of fields) {
        formHTML += this.generateField(fieldName, fieldConfig);
      }
      if (isGrid) {
        formHTML += `</div>`;
      }
      formHTML += `</div>`;
    });

    formHTML += this.buildWizardFooter(stepGroups.length);
    formHTML += `</form>`;
    return formHTML;
  }

  /**
   * Get form style configuration from new structure
   * @returns {Object}
   */
  getFormStyle() {
    const defaultStyle = {
      floating: true,
      size: "form-nexa-control",
      layout: "vertical",
      button: "nx-btn-primary",
      validation: false,
    };

    // Prioritas: assets.style > settings > default
    let style = { ...defaultStyle };

    if (this.formData.assets && this.formData.assets.style) {
      style = { ...style, ...this.formData.assets.style };
    }

    if (this.formData.settings) {
      const settings = this.formData.settings;
      if (settings.floating !== undefined) style.floating = settings.floating;
      if (settings.validation !== undefined)
        style.validation = settings.validation;
      if (settings.buttontype) style.button = settings.buttontype;
      if (settings.layout) style.layout = settings.layout;
      if (settings.size) style.size = settings.size;
    }

    return style;
  }

  /**
   * Check if any field has grid column classes
   * @returns {boolean}
   */
  hasGridColumns() {
    if (!this.formData.form) return false;

    return Object.values(this.formData.form).some(
      (field) => field.columnWidth && field.columnWidth.startsWith("nx-col-")
    );
  }

  /**
   * Get fields ordered according to variables array
   * Filter only fields with condition: true (fields that should be displayed)
   * @returns {Array} Array of [fieldName, fieldConfig] tuples in correct order
   */
  getOrderedFields() {
    const formFields = this.formData.form || {};
    const variables = this.formData.variables || [];
    const orderedFields = [];

    // First, add fields in the order specified by variables array (only condition: true)
    variables.forEach((fieldName) => {
      if (formFields[fieldName] && formFields[fieldName].condition === true) {
        orderedFields.push([fieldName, formFields[fieldName]]);
      }
    });

    // Then, add any remaining fields that are not in variables array (only condition: true)
    Object.entries(formFields).forEach(([fieldName, fieldConfig]) => {
      if (!variables.includes(fieldName) && fieldConfig.condition === true) {
        orderedFields.push([fieldName, fieldConfig]);
      }
    });

    return orderedFields;
  }
  // formFields[fieldName]
  generateField(fieldName, fieldConfig) {
    // Skip field jika condition bukan true (field tidak aktif/tidak ditampilkan)
    if (fieldConfig.condition !== true) {
      return ""; // Jangan tampilkan apapun
    }

    // Skip field hidden - tidak perlu ditampilkan secara visual
    if (fieldConfig.type === "hidden") {
      return this.generateHiddenInput(
        fieldName,
        fieldConfig.placeholder || fieldConfig.label || fieldName,
        "",
        false
      );
    }



    // Skip field slug - generate sebagai hidden input tapi tetap initialize NexaSlug
    if (fieldConfig.type === "slug") {
      return this.generateTextSlug(
        fieldName,
        fieldConfig.placeholder || fieldConfig.label || fieldName,
        "",
        false,
        this.value[fieldName] ?? ''
      );
    }
    const style = this.getFormStyle();
    const placeholder =fieldConfig.placeholder || fieldConfig.label || fieldName;
    const gridClass = fieldConfig.columnWidth;
    const isFloating = style.floating;
    const size = style.size || "form-nexa-control";
    const fieldType = fieldConfig.type || "text";
    const isHidden = fieldConfig.hiddenForm === true;
    const icon = isHidden ? null : getIconByType(fieldType,fieldConfig.icons);
    const setHidden = fieldConfig.hiddenForm;
    const formSettings = this.formData.settings || {};
    const readonly = fieldConfig?.readonly ? 'readonly' : '';


    // Set value based on fieldType
    let setValue = this.value[fieldName] ?? '';
  
    // Handle different field types for setValue
    switch (fieldType) {
      case "checkbox":
      case "radio":
        // For checkbox/radio, setValue should be the selected value(s)
        setValue = this.value[fieldName] ?? '';
        break;
      case "select":
        // For select, setValue should be the selected option value
        setValue = this.value[fieldName] ?? '';
        break;
      case "textarea":
        // For textarea, setValue should be the text content
        setValue = this.value[fieldName] ?? '';
        break;
      case "file":
        // For file inputs, setValue might be file path or file object
        setValue = this.value[fieldName] ?? '';
        break;
      case "switch":
        // For switch, setValue should be boolean or string representation
        setValue = this.value[fieldName] ?? '';
        break;
      case "hidden":
        // For hidden inputs, setValue should be the hidden value
        setValue = this.value[fieldName] ?? '';
        break;
      case "flag":
        // For flag inputs, setValue should be the flag value
        setValue = this.value[fieldName] ?? '';
        break;
      default:
        // For text, email, password, number, date, etc.
        setValue = this.value[fieldName] ?? '';
        break;
    }
    
 

    let fieldHTML = "";

    if (gridClass) {
      fieldHTML += `<div class="${gridClass}">`;
    }

    // Special handling for checkbox, radio, switch, file, textarea, and tags - only use grid, no floating or icon wrapper
    if (
      fieldType === "checkbox" ||
      fieldType === "radio" ||
      fieldType === "switch" ||
      fieldType === "file" ||
      fieldType === "textarea" ||
      fieldType === "tags"
    ) {
      let inputHTML = "";

      if (fieldType === "checkbox") {
        inputHTML = this.generateCheckboxInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          setValue
        );
      } else if (fieldType === "radio") {
        inputHTML = this.generateRadioInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          setValue
        );
      } else if (fieldType === "switch") {
        inputHTML = this.generateSwitchInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          setValue
        );
      } else if (fieldType === "file") {
        inputHTML = this.generateFileInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue
        );
      } else if (fieldType === "textarea") {
        inputHTML = this.generateTextareaInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
      } else if (fieldType === "tags") {
        inputHTML = this.generateTagsInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          formSettings,
          setValue
        );
      }

      fieldHTML += inputHTML;
      if (gridClass) {
        fieldHTML += `</div>`; // Close grid column
      }

      return fieldHTML;
    }

    // ✅ Jika hidden, langsung return input tanpa wrapper, icon, dan label
    if (isHidden) {
      let inputHTML = "";
      // Generate input based on type (akan otomatis menjadi type="hidden" di method generateXXXInput)
      switch (fieldType) {
        case "text":
          inputHTML = this.generateTextInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "instansi":
          inputHTML = this.generateInstansiInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "email":
          inputHTML = this.generateEmailInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "password":
          inputHTML = this.generatePasswordInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "number":
          inputHTML = this.generateNumberInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "currency":
          inputHTML = this.generateCurrencyInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "tel":
          inputHTML = this.generateTelInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "url":
          inputHTML = this.generateUrlInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "date":
          inputHTML = this.generateDateInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "datetime-local":
          inputHTML = this.generateDateTimeInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "time":
          inputHTML = this.generateTimeInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "month":
          inputHTML = this.generateMonthInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "week":
          inputHTML = this.generateWeekInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "color":
          inputHTML = this.generateColorInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "range":
          inputHTML = this.generateRangeInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
          break;
        case "keyup":
          inputHTML = this.generateKeyupInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            fieldConfig,
            formSettings,
            setValue
          );
          break;
        default:
          inputHTML = this.generateTextInput(
            fieldName,
            placeholder,
            size,
            isFloating,
            setValue,
            fieldConfig
          );
      }
      
      // Return input tanpa wrapper, icon, dan label
      if (gridClass) {
        return `<div class="${gridClass}">${inputHTML}</div>`;
      }
      return inputHTML;
    }

    // Wrapper for floating or regular form (for non-checkbox inputs)
    const wrapperClass = isFloating ? "form-nexa-floating" : "form-nexa-group";
    fieldHTML += `<div class="${wrapperClass}">`;

    // For floating mode, add icon container here
    if (isFloating && icon) {
      if (fieldType == "flag") {
        fieldHTML += ``; // Close form-nexa-icon
      } else if (fieldType == "select") {
        fieldHTML += `<div class="form-nexa">`;
      } else {
        fieldHTML += `<div class="form-nexa-icon">`;
      }
    }

    // Generate input based on type
    let inputHTML = "";
    switch (fieldType) {
      case "text":
        inputHTML = this.generateTextInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "instansi":
        inputHTML = this.generateInstansiInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "email":
        inputHTML = this.generateEmailInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "password":
        inputHTML = this.generatePasswordInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "number":
        inputHTML = this.generateNumberInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "currency":
        inputHTML = this.generateCurrencyInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "tel":
        inputHTML = this.generateTelInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "url":
        inputHTML = this.generateUrlInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "date":
        inputHTML = this.generateDateInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "datetime-local":
        inputHTML = this.generateDateTimeInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "time":
        inputHTML = this.generateTimeInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "month":
        inputHTML = this.generateMonthInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "week":
        inputHTML = this.generateWeekInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;
      case "color":
        inputHTML = this.generateColorInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );

        break;
      case "range":
        inputHTML = this.generateRangeInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig
        );
        break;

      case "hidden":
        inputHTML = this.generateHiddenInput(
          fieldName,
          placeholder,
          size,
          isFloating
        );
        break;
      case "select":
        inputHTML = this.generateSelectInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          setValue
        );
        break;
      case "search":
        inputHTML = this.generateSearchInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          formSettings,
          setValue,
          this.formData
        );
        break;
      case "maps":
        inputHTML = this.generateMapsInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          formSettings,
          setValue
        );
        break;
      case "keyup":
        inputHTML = this.generateKeyupInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          formSettings,
          setValue
        );
        break;
      case "flag":
         const random = generateUniqueName(fieldName)
        // Handle async flag input
        this.generateFlagInput(fieldName, placeholder, size, isFloating,fieldConfig,formSettings,setValue).then(
          (flagHTML) => {
            // Update the field with the flag HTML when ready
            const fieldContainer = document.querySelector(
              `[data-field="${random}"]`
            );
            if (fieldContainer) {
              fieldContainer.innerHTML = flagHTML;
              
              // Initialize flag dropdowns after HTML is inserted
              setTimeout(() => {
                this.initializeFlagDropdowns(fieldContainer);
              }, 200);
            }
          }
        );
        inputHTML = `<div data-field="${random}">Loading flag input...</div>`;
        break;
      default:
        inputHTML = this.generateTextInput(
          fieldName,
          placeholder,
          size,
          isFloating,
          setValue,
          fieldConfig

        );
    }
    // Check if field type should skip label (date, datetime-local, time, month, week, maps, tags)
    // Note: tags, textarea, checkbox, radio, switch, file are already handled above and return early
    const skipLabelTypes = ["date", "datetime-local", "time", "month", "week", "maps"];
    const skipLabel = skipLabelTypes.includes(fieldType);
    
    // Add label and input with correct structure based on floating mode
    if (isFloating) {
      // Floating labels: input first, then label inside icon container

      if (icon) {
        fieldHTML += inputHTML;
        if (fieldType == "flag") {
          fieldHTML += ``; // Close form-nexa-icon
        } else {
          if (fieldType == "select") {
            fieldHTML += ``; // Close form-nexa-icon
          } else {
            fieldHTML += `<i class="material-symbols-outlined">${icon}</i>`;
          }

          // Skip label for date types
          if (!skipLabel) {
            fieldHTML += `<label for="${fieldName}">${placeholder}</label>`;
          }
          fieldHTML += `</div>`; // Close form-nexa-icon
        }
      } else {
        fieldHTML += inputHTML;
        // Skip label for date types
        if (!skipLabel) {
          fieldHTML += `<label for="${fieldName}">${placeholder}</label>`;
        }
      }
    } else {
      
      // Non-floating: label first, then input (label outside icon container)
      // Skip label for date types
      if (!skipLabel) {
        fieldHTML += `<label for="${fieldName}">${placeholder}</label>`;
      }
      if (icon) {
        fieldHTML += `<div class="form-nexa-icon">`;
        fieldHTML += inputHTML;
        fieldHTML += `<i class="material-symbols-outlined">${icon}</i>`;
        fieldHTML += `</div>`; // Close form-nexa-icon
      } else {
        fieldHTML += inputHTML;
      }
    }

    fieldHTML += `</div>`; // Close form-nexa-floating/form-nexa-group

    if (gridClass) {
      fieldHTML += `</div>`; // Close grid column
    }

    return fieldHTML;
  }

  generateTextInput(fieldName, placeholder, size, isFloating, setValue = '',fieldConfig) {
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
      const type =fieldConfig?.hiddenForm ? 'hidden' : fieldConfig?.type
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateInstansiInput(fieldName, placeholder, size, isFloating, setValue = '',fieldConfig) {
    console.log('fieldConfig:', fieldConfig);
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const type =fieldConfig?.hiddenForm ? 'hidden' : 'text'
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input value="${NEXA?.userData?.instansi || ''}" type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr}  disabled />`;
  }








  generateKeyupInput( 
    fieldName,
    placeholder,
    size,
    isFloating,
    config,
    settings,
    setValue) {
    const type = config?.hiddenForm ? 'hidden' : 'text';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = config?.disabled ? 'disabled' : '';
    const readonly = config?.readonly ? 'readonly' : '';
          NXUI.NexaKeyup.fromFormData(this.form, { 
          delay: 100, 
          carakter :10, 
          cleanHtml: true  // Alias untuk stripHtml
        });
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateTagsInput(fieldName,
          placeholder,
          size,
          isFloating,
          fieldConfig,
          formSettings,
          setValue) {

    console.log('formSettings:', fieldConfig);
     const valueAttr = setValue ? `value="${setValue}"` : '';
 
   const limit = fieldConfig?.limit?? 10;
   const fieldData = fieldConfig?.tags?.data;
   const result2 = Array.isArray(fieldData)
     ? fieldData
         .map(item => ({
          failed: item.value || item.label, // ✅ Use 'failed' property as expected by NexaTags
         }))
     : [];

   // ✅ Detect if we're in a modal
   const isModal = formSettings?.model === "Modal";
   const random = generateUniqueName(fieldName);
    const countlength =result2.length;
  const placeholderAttr=`placeholder="${placeholder}"`;
  const placeholderText = countlength
    ? "Cari tag atau ketik tag baru lalu tekan Enter..." 
    : `Ketik maksimal ${fieldConfig?.limit || '2'} tag, lalu tekan Enter untuk menambah...`;
     const hiddenInput = `<input type="hidden" id="${fieldName}" name="${fieldName}" ${placeholderAttr} ${valueAttr}> `;

   setTimeout(async () => {
     try {
      // ✅ FIX: Setup event listener untuk hidden input tags agar error hilang saat value berubah
      const hiddenInputEl = document.getElementById(fieldName);
      if (hiddenInputEl) {
        // ✅ FIX: Intercept value setter untuk mendeteksi perubahan value secara programmatic
        let currentValue = hiddenInputEl.value || '';
        
        try {
          const valueProperty = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
          
          // Override value setter jika memungkinkan
          if (valueProperty && valueProperty.set) {
            Object.defineProperty(hiddenInputEl, 'value', {
              get: valueProperty.get,
              set: function(newValue) {
                const oldValue = currentValue;
                valueProperty.set.call(this, newValue);
                
                // Jika value berubah, trigger validasi untuk menghapus error
                if (newValue !== oldValue) {
                  currentValue = newValue;
                  
                  // Trigger events untuk validasi (baik kosong maupun terisi)
                  setTimeout(() => {
                    const inputEvent = new Event('input', { bubbles: true });
                    this.dispatchEvent(inputEvent);
                    
                    const changeEvent = new Event('change', { bubbles: true });
                    this.dispatchEvent(changeEvent);
                  }, 10);
                }
              },
              configurable: true
            });
          }
        } catch (e) {
          // Fallback jika defineProperty gagal
        }
      }

      new NXUI.NexaTags({
        targetId: ["tags"+random], // ✅ FIX: Use fieldName instead of hardcoded "tags"
        hideValue: [`${fieldName}`],
         data: result2 && result2.length > 0 ? result2 : false,  // Gunakan result2 jika ada data, false jika tidak ada
         validasi: [limit],
         isModal: isModal, // ✅ Pass modal detection info
         onChange: (data) => {
           // ✅ FIX: Trigger validasi saat tags berubah untuk menghapus error
           if (hiddenInputEl) {
             // Small delay untuk memastikan value sudah di-update
             setTimeout(() => {
               const inputEvent = new Event('input', { bubbles: true });
               hiddenInputEl.dispatchEvent(inputEvent);
               
               const changeEvent = new Event('change', { bubbles: true });
               hiddenInputEl.dispatchEvent(changeEvent);
             }, 100);
           }
         }
       });
     } catch (error) {
     }
   }, isModal ? 200 : 100); // ✅ Slightly longer delay for modal to ensure DOM is ready
    return `
      <input type="text" id="tags${random}"  class="${size}" placeholder="${placeholderText}" ${valueAttr}/>
       ${hiddenInput}

     `;
  }

 


  generateTextareaInput(fieldName, placeholder, size, isFloating, setValue = '',fieldConfig) {
    // Hidden input untuk menyimpan value dari editor
    const random = generateUniqueName(fieldName);
    const limit =Number(fieldConfig.limit); 
    const hiddenInputId = `hidden_${random}`;
    const textareaInput = `textareaInput${random}_${fieldName}`;
    const hiddenInput = `<textarea style="display:none;" id="${hiddenInputId}" name="${fieldName}">${setValue || ''}</textarea>`;

    // Initialize editor setelah DOM ready
    setTimeout(async () => {
      try {
        const editorElement = document.getElementById(`${textareaInput}`);
        const editor = new NXUI.Editor(`#${textareaInput}`, {
          height:`${fieldConfig.height ||"150"}px`,
          placeholder:`Mulai mengetik  ${placeholder} di sini...`,
        });

        // Set initial content jika ada setValue
        if (setValue && setValue.trim() !== '') {
          editor.insertHTML(setValue);
          // Update hidden input dengan initial value
          const hiddenInputEl = document.getElementById(hiddenInputId);
          if (hiddenInputEl) {
            hiddenInputEl.value = setValue;
                  const textContent = editor.getTextOnly();
                  const outputDiv = NXUI.id(fieldConfig.bubbling).val(textContent.trim().substring(0, limit));
          }
        }

        // Update hidden input saat content berubah melalui keyup
        editor.onElementKeyup(`#${textareaInput}`, (content, element, event) => {
          const hiddenInputEl = document.getElementById(hiddenInputId);
          if (hiddenInputEl) {
            hiddenInputEl.value = content || '';

                const textContent = editor.getTextOnly();
                  const outputDiv = NXUI.id(fieldConfig.bubbling).val(textContent.trim().substring(0, limit));

          }
        });

        // Update hidden input untuk event paste dan perubahan lainnya
        // Menggunakan MutationObserver untuk mendeteksi perubahan content
        const observer = new MutationObserver(() => {
          const hiddenInputEl = document.getElementById(hiddenInputId);
          if (hiddenInputEl && editor) {
            try {
              const content = editor.getContent();
              if (content !== hiddenInputEl.value) {
                hiddenInputEl.value = content || '';
                const textContent = editor.getTextOnly();
                  const outputDiv = NXUI.id(fieldConfig.bubbling).val(textContent.trim().substring(0, limit));
              }
            } catch (e) {
              // Ignore error jika editor belum ready
            }
          }
        });

        // Observe perubahan pada editor element
        if (editorElement) {
          observer.observe(editorElement, {
            childList: true,
            subtree: true,
            characterData: true
          });
        }

      } catch (error) {
      }
    }, 100);

    return `
      ${hiddenInput}
      <div style="border: 1px solid #e0e0e0;" id="${textareaInput}"></div>
    `;
  }
 generateTextSlug(fieldName, placeholder, size, isFloating, setValue = '') {
    const valueAttr = setValue ? `value="${setValue}"` : '';
        const placeholderAttr = `placeholder="${placeholder}"`;
    // Initialize NexaSlug setelah DOM ready
    setTimeout(async () => {
        NexaSlug.fromFormData(this.form, { delay: 100 });
    }, 0);
    
    // ✅ FIX: Return hidden input untuk slug field dengan attribute khusus untuk skip validasi
    // Field slug ditangani khusus oleh NexaSlug, jadi tidak perlu menampilkan error message
    return `<input type="hidden" id="${fieldName}" name="${fieldName}" data-slug-field="true" ${placeholderAttr} ${valueAttr} />`;
  }
  generateMapsInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue = '') {
    // Handle setValue jika berupa object
    let valueString = '';
     const random = generateUniqueName(fieldName);
    if (setValue) {
      if (typeof setValue === 'object') {
        // Jika setValue adalah object dengan lat/lon atau latitude/longitude
        if (setValue.lat !== undefined && setValue.lon !== undefined) {
          valueString = `${setValue.lat},${setValue.lon}`;
        } else if (setValue.latitude !== undefined && setValue.longitude !== undefined) {
          valueString = `${setValue.latitude},${setValue.longitude}`;
        } else if (setValue.coords) {
          // Jika ada property coords
          const lat = setValue.coords.lat || setValue.coords.latitude;
          const lon = setValue.coords.lon || setValue.coords.longitude;
          if (lat !== undefined && lon !== undefined) {
            valueString = `${lat},${lon}`;
          }
        } else {
          // Jika tidak ada property yang dikenali, coba konversi langsung
          valueString = String(setValue);
        }
      } else {
        valueString = String(setValue);
      }
    }
    
    const valueAttr = valueString ? `value="${valueString}"` : '';

    // Function untuk mendapatkan geolocation
    const getGeolocation = () => {
      if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;

            // Gabungkan ke string (bukan object)
            const coords = `${lat},${lon}`;

            // Tunggu hingga input ada di DOM
            const checkInput = () => {
              const input = document.getElementById(random);
              if (input) {
                input.value = coords; // isi value dengan string koordinat
              } else {
                // Jika input belum ada, coba lagi setelah 100ms
                setTimeout(checkInput, 100);
              }
            };
            checkInput();
          },
          function(error) {
          },
          {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
          }
        );
      } else {
      }
    };

    // Initialize geolocation setelah DOM ready (hanya jika tidak ada setValue)
    if (!valueString) {
      setTimeout(() => {
        // Check jika document sudah ready atau window sudah loaded
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          // Jika sudah ready, langsung jalankan
          getGeolocation();
        } else {
          // Jika belum ready, tunggu DOMContentLoaded atau load event
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', getGeolocation);
          }
          window.addEventListener('load', getGeolocation);
        }
      }, 100);
    }
    
    // Return input untuk maps field
    return `<input type="text" id="${random}" class="${size}" name="${fieldName}" ${valueAttr} />`;
  }






  async generateFlagInput(fieldName,placeholder,size,isFloating,fieldConfig,formSettings,setValue) {
    return await setFlag(fieldName,placeholder,size,isFloating,fieldConfig,formSettings,setValue);
  }
  generateSelectInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    const options = fieldConfig.select.data || [];
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    let selectHTML = `<select id="${fieldName}" name="${fieldName}" class="${size} nexa-floating-select" ${disabled} ${readonly}>`;

    // Always use "Pilihan" as default option text
    selectHTML += `<option value="">Pilihan</option>`;

    if (options && Array.isArray(options)) {
      options.forEach((option) => {
        const value = option.value || option.id;
        const label = option.text || option.label || option.value;
        const selected = setValue && setValue == value ? 'selected' : '';
        selectHTML += `<option value="${value}" ${selected}>${label}</option>`;
      });
    }

    selectHTML += `</select>`;
    return selectHTML;
  }

  generateSearchInput(
    fieldName,
    placeholder,
    size,
    isFloating,
    config,
    settings,
    setValue,
    formData
  ) {

     const randomID = generateUniqueName(fieldName)
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
        const valueAttr = setValue ? `value="${setValue}"` : '';
    setTimeout(async () => {
      if (settings.model == "Modal") {
        await this.setModalSearchInput(config, fieldName,randomID,formData);
      } else {
        await this.contentSearchInput(config, fieldName,randomID,formData);
      }
    }, 0);
    let hiddenvalue = "";
    // ✅ Jika mode update, jangan tampilkan hidden input kecuali ada proses pencarian
    // Hidden input akan dibuat dinamis saat user melakukan pencarian
    if (config?.search?.hiddenFormvalue && this.options.mode !== "update") {
      hiddenvalue = `<input type="hidden" id="hidden${config?.search?.hiddenFormvalue}" name="${config?.search?.hiddenFormvalue}"/>`;
    } else {
      hiddenvalue = "";
    }
      // ✅ Add validation bypass attributes if setValue exists
      const validationBypassAttrs = setValue && setValue.trim() !== '' 
        ? `data-has-preset-value="true" data-preset-value="${setValue.replace(/"/g, '&quot;')}"` 
        : '';

      return `
      <div class="form-nexa-group1 nexa-search-container">
        <input type="text" id="${randomID}" name="${fieldName}" class="${size}" ${valueAttr} ${placeholderAttr} ${validationBypassAttrs} />
        ${hiddenvalue}
        </div>
      <div id="suggestions_${randomID}" class="nexa-suggestions-dropdown"></div>
    `;
  }

  async contentSearchInput(config, fieldName,randomID,formData) {
    try {
      const inputEl = NXUI.id(randomID);
      const configEl = config.search;

      // Add CSS styles for suggestions dropdown
      this.addSuggestionsStyles();

      inputEl.on("keyup", async function (e) {
        // Jika input kosong, hapus suggestions
        if (!e.target.value || e.target.value.trim() === "") {
          const suggestionsDiv = document.getElementById(
            `suggestions_${randomID}`
          );
          if (suggestionsDiv) {
            suggestionsDiv.innerHTML = "";
            suggestionsDiv.style.display = "none";
            suggestionsDiv.classList.remove("nexa-dropdown-above");
            suggestionsDiv.style.maxHeight = "";
          }
          return;
        }
        const searchParams = {
          access: config.search?.access,
          metadata: Number(config.search?.tabelName),
          field: config.search?.tabeltext,
          label: config.search?.tabeltext,
          title: config.search?.labelvalue ?? config.search?.tabeltext,
          value: config.search?.tabelvalue,
          where: {
            field: config.search?.wheretext,
            value: config.search?.wherevalue,
          },
        
        };

        const result = await NXUI.Storage()
          .models("Office")
          .searchAt(searchParams, e.target.value);
        
        // Simpan result.data untuk digunakan saat klik suggestion
        let myData = [];
        if (result && Array.isArray(result.data)) {
          myData = result.data;
        }

        // Tampilkan hasil di <div id="suggestions_${fieldName}">
        const suggestionsDiv = document.getElementById(
          `suggestions_${randomID}`
        );
        if (suggestionsDiv) {
          // Position dropdown to match input field
          const inputElement = inputEl[0] || inputEl;
          // Ensure we have a DOM element
          const domElement =
            inputElement && inputElement.getBoundingClientRect
              ? inputElement
              : document.getElementById(randomID);
          const inputRect = domElement.getBoundingClientRect();

          suggestionsDiv.style.left = inputRect.left + "px";
          suggestionsDiv.style.top = inputRect.bottom + window.scrollY + "px";
          suggestionsDiv.style.width = inputRect.width + "px";

          if (result && Array.isArray(result.data) && result.data.length > 0) {
            // Buat list hasil
            let html = '<ul class="nexa-suggestions-list">';
            result.data.forEach((item) => {
             html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
               <h4 style="color: black; margin: 0;"> ${item.label || item.value || item.data}</h4>
                ${item.title ?` <span style="font-size:10px; color: black;"> ${item.title || item.label }</span>`:''}
              </li>`;
            });
            html += "</ul>";
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = "block";

            // Event klik pada suggestion
            suggestionsDiv
              .querySelectorAll(".nexa-suggestion-item")
              .forEach((li) => {
                li.addEventListener("click", function () {
                  // Set input value to show label, but store actual value
                  const label = this.textContent.trim();
                  const id = this.getAttribute("data-id");
                  const value = this.getAttribute("data-value");
                  if (configEl?.hiddenFormvalue) {
                    // ✅ Untuk mode update, buat hidden input secara dinamis saat pencarian
                    let hiddenInput = document.getElementById("hidden" + config?.search?.hiddenFormvalue);
                    if (!hiddenInput) {
                      // Create hidden input if it doesn't exist (especially for update mode)
                      hiddenInput = document.createElement("input");
                      hiddenInput.type = "hidden";
                      hiddenInput.id = "hidden" + config?.search?.hiddenFormvalue;
                      hiddenInput.name = config?.search?.hiddenFormvalue;
                      
                      // Insert hidden input in the search container
                      const searchContainer = document.getElementById(randomID).closest('.nexa-search-container');
                      if (searchContainer) {
                        searchContainer.appendChild(hiddenInput);
                      }
                    }
                    // Set the hidden input value
                    hiddenInput.value = id;
                  }
                  NXUI.id(randomID).val(value);

                  // ✅ Jika config.target aktif, isi field form berdasarkan mapping add
                  if (config.target) {
                    // Cari data dari myData berdasarkan id yang dipilih
                    const selectedData = myData.find(item => item.id == id || item.id === parseInt(id));
                    
                    if (selectedData && formData?.target?.[fieldName]?.add) {
                      // Ambil mapping dari target[fieldName].add (object seperti {nama: 'nama', title: 'jabatan', deskripsi: 'telepon'})
                      const addMapping = formData.target[fieldName].add;
                      
                      // Isi setiap field condition sesuai mapping (iterasi object)
                      Object.keys(addMapping).forEach(fieldCondition => {
                        const variableName = addMapping[fieldCondition];
                        
                        // Cari field input berdasarkan name atau id
                        const conditionField = document.querySelector(`[name="${fieldCondition}"]`) || 
                                              document.getElementById(fieldCondition) ||
                                              document.querySelector(`input[data-field="${fieldCondition}"]`);
                        
                        if (conditionField && selectedData[variableName] !== undefined) {
                          // Set nilai ke field condition
                          if (conditionField.tagName === 'INPUT' || conditionField.tagName === 'TEXTAREA') {
                            NXUI.id(conditionField.id || conditionField.name)?.val(selectedData[variableName]);
                            // Trigger input dan focus event untuk update floating label
                            conditionField.dispatchEvent(new Event('input', { bubbles: true }));
                            conditionField.dispatchEvent(new Event('focus', { bubbles: true }));
                            conditionField.dispatchEvent(new Event('blur', { bubbles: true }));
                          } else if (conditionField.tagName === 'SELECT') {
                            conditionField.value = selectedData[variableName];
                            // Trigger change dan input event untuk update floating label
                            conditionField.dispatchEvent(new Event('change', { bubbles: true }));
                            conditionField.dispatchEvent(new Event('input', { bubbles: true }));
                            conditionField.dispatchEvent(new Event('focus', { bubbles: true }));
                            conditionField.dispatchEvent(new Event('blur', { bubbles: true }));
                          }
                        }
                      });
                    }
                  }

                  suggestionsDiv.innerHTML = "";
                  suggestionsDiv.style.display = "none";
                  suggestionsDiv.classList.remove("nexa-dropdown-above");
                });
              });
          } else {
            suggestionsDiv.innerHTML =
              '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
            suggestionsDiv.style.display = "block";
            // Clear input field when no results found
            NXUI.id(randomID).val("");
          }
        }
      });
    } catch (error) {
    }
  }

  async setModalSearchInput(config, fieldName,randomID,formData) {
    try {
      console.log('formData:', formData);
      const inputEl = NXUI.id(randomID);
      const configEl = config.search;

      // Add CSS styles for suggestions dropdown
      this.addSuggestionsStyles();

      // Hide dropdown when input loses focus
      inputEl.on("blur", function (e) {
        setTimeout(() => {
          const suggestionsDiv = document.getElementById(
            `suggestions_${randomID}`
          );
          if (suggestionsDiv) {
            suggestionsDiv.style.display = "none";
          }
        }, 150); // Small delay to allow click events
      });

      // Hide dropdown when clicking outside
      document.addEventListener("click", function (e) {
        const suggestionsDiv = document.getElementById(
          `suggestions_${randomID}`
        );
        const inputElement = document.getElementById(randomID);

        if (
          suggestionsDiv &&
          inputElement &&
          !suggestionsDiv.contains(e.target) &&
          !inputElement.contains(e.target)
        ) {
          suggestionsDiv.style.display = "none";
        }
      });

      inputEl.on("keyup", async function (e) {
        // Jika input kosong, hapus suggestions
        if (!e.target.value || e.target.value.trim() === "") {
          const suggestionsDiv = document.getElementById(
            `suggestions_${randomID}`
          );
          if (suggestionsDiv) {
            suggestionsDiv.innerHTML = "";
            suggestionsDiv.style.display = "none";
            suggestionsDiv.classList.remove("nexa-dropdown-above");
            suggestionsDiv.style.maxHeight = "";
            suggestionsDiv.style.zIndex = "";
            suggestionsDiv.style.position = "";
            suggestionsDiv.style.top = "";
            suggestionsDiv.style.left = "";
            suggestionsDiv.style.width = "";
            suggestionsDiv.style.visibility = "";
            suggestionsDiv.style.opacity = "";
          }
          return;
        }
 
        const searchParams = {
          access: config.search?.access,
          metadata: Number(config.search?.tabelName),
          field: config.search?.tabeltext,
          label: config.search?.tabeltext,
          title: config.search?.labelvalue ?? config.search?.tabeltext,
          value: config.search?.tabelvalue,
          where: {
            field: config.search?.wheretext,
            value: config.search?.wherevalue,
          },
        
        };
        let myData=[]
        const result = await NXUI.Storage()
          .models("Office")
          .searchAt(searchParams, e.target.value);
          myData=result.data
   







        // Tampilkan hasil di <div id="suggestions_${fieldName}">
        const suggestionsDiv = document.getElementById(
          `suggestions_${randomID}`
        );
        if (suggestionsDiv) {
          // Position dropdown to match input field
          const inputElement = inputEl[0] || inputEl;
          // Ensure we have a DOM element
          const domElement =
            inputElement && inputElement.getBoundingClientRect
              ? inputElement
              : document.getElementById(randomID);
          const inputRect = domElement.getBoundingClientRect();

          // Check if we're inside a modal
          const modalContainer = domElement.closest(".nx-modal");
          let leftPosition = inputRect.left;
          let topPosition = inputRect.bottom;

          if (modalContainer) {
            // In modal context, use relative positioning within the modal
            const modalBody = modalContainer.querySelector(".nx-modal-body");

            if (modalBody) {
              // Find the search container or create one
              let searchContainer = domElement.closest(
                ".nexa-search-container"
              );
              if (!searchContainer) {
                // Wrap the input in a search container
                searchContainer = document.createElement("div");
                searchContainer.className = "nexa-search-container";
                searchContainer.style.position = "relative";

                // Insert the container and move the input and dropdown into it
                domElement.parentNode.insertBefore(searchContainer, domElement);
                searchContainer.appendChild(domElement);
                searchContainer.appendChild(suggestionsDiv);
              }

              // Calculate available space below and above the input
              const modalBodyRect = modalBody.getBoundingClientRect();
              const inputRect = domElement.getBoundingClientRect();

              // Check for modal footer and other elements that might block the dropdown
              const modalFooter =
                modalContainer.querySelector(".nx-modal-footer");
              const modalContent =
                modalContainer.querySelector(".nx-modal-content");
              const modalDialog =
                modalContainer.querySelector(".nx-modal-dialog");

              // Calculate actual available space considering modal structure
              let availableSpaceBelow = modalBodyRect.bottom - inputRect.bottom;
              let availableSpaceAbove = inputRect.top - modalBodyRect.top;

              // If there's a footer, reduce available space below
              if (modalFooter) {
                const footerRect = modalFooter.getBoundingClientRect();
                const footerSpace = footerRect.top - inputRect.bottom;
                availableSpaceBelow = Math.min(
                  availableSpaceBelow,
                  footerSpace
                );
              }

              // If there's modal content with padding, account for it
              if (modalContent) {
                const contentRect = modalContent.getBoundingClientRect();
                const contentSpace = contentRect.bottom - inputRect.bottom;
                availableSpaceBelow = Math.min(
                  availableSpaceBelow,
                  contentSpace
                );
              }

              const dropdownHeight = 250; // max-height from CSS
              const minSpaceRequired = 120; // Reduced minimum space needed to show dropdown below

              // More aggressive approach: check if dropdown would be cut off
              const testDropdownBottom = inputRect.bottom + dropdownHeight;
              const wouldBeCutOff = testDropdownBottom > modalBodyRect.bottom;

              // Allow some overflow if it's not too much
              const overflowAmount = testDropdownBottom - modalBodyRect.bottom;
              const allowOverflow = overflowAmount < 30; // Reduced to 30px overflow allowance

              // Additional check: ensure dropdown doesn't overlap with footer
              const footerOverlap = modalFooter
                ? Math.max(
                    0,
                    inputRect.bottom +
                      dropdownHeight -
                      modalFooter.getBoundingClientRect().top
                  )
                : 0;
              const hasFooterOverlap = footerOverlap > 0;
              // Position relative to the search container
              suggestionsDiv.style.position = "absolute";
              suggestionsDiv.style.left = "0px";
              suggestionsDiv.style.width = domElement.offsetWidth + "px";

              // Alternative approach: Use fixed positioning within modal for better control
              if ((wouldBeCutOff && !allowOverflow) || hasFooterOverlap) {
                // Use absolute positioning with smart height limiting
                suggestionsDiv.style.position = "absolute";
                suggestionsDiv.style.left = "0px";
                suggestionsDiv.style.width = domElement.offsetWidth + "px";
                suggestionsDiv.style.zIndex = "9999999";

                // Ensure dropdown stays in the correct container
                if (suggestionsDiv.parentNode !== domElement.parentNode) {
                  domElement.parentNode.appendChild(suggestionsDiv);
                }

                // Force z-index to be applied
                suggestionsDiv.style.setProperty(
                  "z-index",
                  "2147483647",
                  "important"
                );

                // Also try setting it directly
                suggestionsDiv.style.zIndex = "2147483647";
                suggestionsDiv.style.setProperty("z-index", "2147483647");

                // Force visibility
                suggestionsDiv.style.visibility = "visible";
                suggestionsDiv.style.opacity = "1";
                // Try to position above first
                if (availableSpaceAbove > 50) {
                  const heightAbove = Math.min(
                    dropdownHeight,
                    availableSpaceAbove - 5
                  );

                  // Always use absolute positioning relative to container
                  suggestionsDiv.style.top = "-" + heightAbove + "px";

                  suggestionsDiv.style.maxHeight = heightAbove + "px";
                  suggestionsDiv.classList.add("nexa-dropdown-above");
                } else {
                  // Position below with height that fits exactly within available space
                  const maxSafeHeight = Math.max(150, availableSpaceBelow - 5);

                  // Always use absolute positioning relative to container
                  suggestionsDiv.style.top = domElement.offsetHeight + "px";

                  suggestionsDiv.style.maxHeight = maxSafeHeight + "px";
                  suggestionsDiv.classList.remove("nexa-dropdown-above");
                  // Ensure dropdown is visible
                  suggestionsDiv.style.display = "block";
                }
              } else {
                // Use normal positioning (allow some overflow)
                suggestionsDiv.style.position = "absolute";
                suggestionsDiv.style.left = "0px";
                suggestionsDiv.style.top = domElement.offsetHeight + "px";
                suggestionsDiv.style.width = domElement.offsetWidth + "px";
                suggestionsDiv.style.maxHeight = "250px";
                suggestionsDiv.style.zIndex = "9999999";
                suggestionsDiv.classList.remove("nexa-dropdown-above");

                // Ensure dropdown stays in the correct container
                if (suggestionsDiv.parentNode !== domElement.parentNode) {
                  domElement.parentNode.appendChild(suggestionsDiv);
                }
              }
            } else {
              // Fallback to fixed positioning with overflow detection
              const viewportHeight = window.innerHeight;
              const spaceBelow = viewportHeight - inputRect.bottom;
              const spaceAbove = inputRect.top;
              const dropdownHeight = 200;

              suggestionsDiv.style.position = "fixed";
              suggestionsDiv.style.left = leftPosition + "px";
              suggestionsDiv.style.width = inputRect.width + "px";
              suggestionsDiv.style.zIndex = "9999999";

              // Ensure dropdown stays in the correct container
              if (suggestionsDiv.parentNode !== domElement.parentNode) {
                domElement.parentNode.appendChild(suggestionsDiv);
              }

              if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
                // Show above the input
                suggestionsDiv.style.top =
                  inputRect.top - dropdownHeight + "px";
                suggestionsDiv.classList.add("nexa-dropdown-above");
              } else {
                // Show below the input (default)
                suggestionsDiv.style.top = topPosition + "px";
                suggestionsDiv.style.display = "none";
                suggestionsDiv.classList.remove("nexa-dropdown-above");
              }
            }
          } else {
            // Regular content positioning with overflow detection
            const viewportHeight = window.innerHeight;
            const spaceBelow = viewportHeight - inputRect.bottom;
            const spaceAbove = inputRect.top;
            const dropdownHeight = 200;

            suggestionsDiv.style.position = "fixed";
            suggestionsDiv.style.left = leftPosition + "px";
            suggestionsDiv.style.width = inputRect.width + "px";
            suggestionsDiv.style.zIndex = "9999999";

            if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
              // Show above the input
              suggestionsDiv.style.top = inputRect.top - dropdownHeight + "px";
              suggestionsDiv.classList.add("nexa-dropdown-above");
            } else {
              // Show below the input (default)
              suggestionsDiv.style.top = topPosition + "px";
              suggestionsDiv.style.display = "none";
              suggestionsDiv.classList.remove("nexa-dropdown-above");
            }
          }

          if (result && Array.isArray(result.data) && result.data.length > 0) {
            // Buat list hasil
            let html = '<ul class="nexa-suggestions-list">';
            result.data.forEach((item) => {
              html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
               <h4 style="color: black; margin: 0;"> ${item.label || item.value || item.data}</h4>
                ${item.title ?` <span style="font-size:10px; color: black;"> ${item.title || item.label }</span>`:''}
              </li>`;
            });
            html += "</ul>";
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = "block";

            // Event klik pada suggestion
            suggestionsDiv
              .querySelectorAll(".nexa-suggestion-item")
              .forEach((li) => {
                li.addEventListener("click", function () {
                  // Set input value to show label, but store actual value
                  const label = this.textContent.trim();
                  const id = this.getAttribute("data-id");
                  const value = this.getAttribute("data-value");
                  if (configEl?.hiddenFormvalue) {
                    // ✅ Untuk mode update, buat hidden input secara dinamis saat pencarian
                    let hiddenInput = document.getElementById("hidden" + config?.search?.hiddenFormvalue);
                    if (!hiddenInput) {
                      // Create hidden input if it doesn't exist (especially for update mode)
                      hiddenInput = document.createElement("input");
                      hiddenInput.type = "hidden";
                      hiddenInput.id = "hidden" + config?.search?.hiddenFormvalue;
                      hiddenInput.name = config?.search?.hiddenFormvalue;
                      
                      // Insert hidden input in the search container
                      const searchContainer = document.getElementById(randomID).closest('.nexa-search-container');
                      if (searchContainer) {
                        searchContainer.appendChild(hiddenInput);
                      }
                    }
                    // Set the hidden input value
                    hiddenInput.value = id;



            



                  }
                  NXUI.id(randomID).val(value);

                 if (config.target) {
                // Cari data dari myData berdasarkan id yang dipilih
                const selectedData = myData.find(item => item.id == id || item.id === parseInt(id));
                
                if (selectedData && formData?.target?.[fieldName]?.add) {
                  // Ambil mapping dari target[fieldName].add (object seperti {nama: 'nama', title: 'jabatan', deskripsi: 'telepon'})
                  const addMapping = formData.target[fieldName].add;
                  
                  // Isi setiap field condition sesuai mapping (iterasi object)
                  Object.keys(addMapping).forEach(fieldCondition => {
                    const variableName = addMapping[fieldCondition];
                    
                    // Cari field input berdasarkan name atau id
                    const conditionField = document.querySelector(`[name="${fieldCondition}"]`) || 
                                          document.getElementById(fieldCondition) ||
                                          document.querySelector(`input[data-field="${fieldCondition}"]`);
                    
                    if (conditionField && selectedData[variableName] !== undefined) {
                      // Set nilai ke field condition
                      if (conditionField.tagName === 'INPUT' || conditionField.tagName === 'TEXTAREA') {
                        NXUI.id(conditionField.id || conditionField.name)?.val(selectedData[variableName]);
                        // Trigger input dan focus event untuk update floating label
                        conditionField.dispatchEvent(new Event('input', { bubbles: true }));
                        conditionField.dispatchEvent(new Event('focus', { bubbles: true }));
                        conditionField.dispatchEvent(new Event('blur', { bubbles: true }));
                      } else if (conditionField.tagName === 'SELECT') {
                        conditionField.value = selectedData[variableName];
                        // Trigger change dan input event untuk update floating label
                        conditionField.dispatchEvent(new Event('change', { bubbles: true }));
                        conditionField.dispatchEvent(new Event('input', { bubbles: true }));
                        conditionField.dispatchEvent(new Event('focus', { bubbles: true }));
                        conditionField.dispatchEvent(new Event('blur', { bubbles: true }));
                      }
                    }
                  });
                }








                      }

                  suggestionsDiv.innerHTML = "";
                  suggestionsDiv.style.display = "none";
                  suggestionsDiv.classList.remove("nexa-dropdown-above");
                });
              });
          } else {
            suggestionsDiv.innerHTML =
              '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
            suggestionsDiv.style.display = "block";
            // Clear input field when no results found
            NXUI.id(randomID).val("");
          }
        }
      });
    } catch (error) {
    }
  }

  addSuggestionsStyles() {
    // Add CSS styles only once
    if (document.getElementById("nexa-suggestions-styles")) return;

    const style = document.createElement("style");
    style.id = "nexa-suggestions-styles";
    style.textContent = `
      .nexa-search-container {
        position: relative;
      }
      
      .nexa-suggestions-dropdown {
        position: fixed;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 2147483647 !important;
        display: none;
        max-height: 250px;
        overflow-y: auto;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      /* Modal-specific dropdown positioning */
      .nx-modal .nexa-suggestions-dropdown {
        position: absolute;
        z-index: 2147483647 !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      .nx-modal .nexa-search-container {
        position: relative;
      }
      
      /* Dropdown above input styling */
      .nexa-suggestions-dropdown.nexa-dropdown-above {
        box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
        border-radius: 4px 4px 0 0;
      }
      
      .nexa-suggestions-dropdown.nexa-dropdown-above .nexa-suggestion-item:first-child {
        border-radius: 4px 4px 0 0;
      }
      
      .nexa-suggestions-dropdown.nexa-dropdown-above .nexa-suggestion-item:last-child {
        border-radius: 0 0 0 0;
        border-bottom: 1px solid #f0f0f0;
      }
      
      /* Limited height dropdown styling */
      .nexa-suggestions-dropdown[style*="max-height"] {
        border-bottom: 1px solid #ddd;
      }
      
      .nexa-suggestions-dropdown[style*="max-height"] .nexa-suggestion-item:last-child {
        border-bottom: none;
      }
      
      .nexa-suggestions-list {
        list-style: none;
        margin: 0;
        padding: 0;
      }
      
      .nexa-suggestion-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
        color: black;
      }
      
      .nexa-suggestion-item h4 {
        color: black !important;
        margin: 0;
      }
      
      .nexa-suggestion-item span {
        color: black !important;
      }
      
      .nexa-suggestion-item:hover {
        background-color: #f5f5f5;
      }
      
      .nexa-suggestion-item:last-child {
        border-bottom: none;
      }
      
      .nexa-suggestion-empty {
        padding: 8px 12px;
        color: #666;
        font-style: italic;
        text-align: center;
      }
    `;
    document.head.appendChild(style);
  }

  generateEmailInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'email';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generatePasswordInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'password';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateNumberInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'number';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    const options = this.getOptions(fieldName);
    let attributes = "";

    if (options) {
      if (options.min !== undefined) attributes += ` min="${options.min}"`;
      if (options.max !== undefined) attributes += ` max="${options.max}"`;
      if (options.step !== undefined) attributes += ` step="${options.step}"`;
    }

    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly}${attributes} />`;
  }

  generateCurrencyInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'number';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    const options = this.getOptions(fieldName);
    let attributes = "";

    if (options) {
      if (options.min !== undefined) attributes += ` min="${options.min}"`;
      if (options.max !== undefined) attributes += ` max="${options.max}"`;
      if (options.step !== undefined) attributes += ` step="${options.step}"`;
    }

    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly}${attributes} />`;
  }

  generateTelInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'tel';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateUrlInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'url';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateDateInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
        const placeholderAttr=`placeholder="${placeholder}"`;
    // Date input tidak perlu placeholder karena sudah ada UI date picker dari browser
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'date';
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" ${placeholderAttr} class="${size}" ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateDateTimeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'datetime-local';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateTimeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'time';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateMonthInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'month';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateWeekInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'week';
    const placeholderAttr = isFloating
      ? 'placeholder=" "'
      : `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  }

  generateColorInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'color';
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${valueAttr} ${disabled} ${readonly} />`;
  }







  generateRangeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type = fieldConfig?.hiddenForm ? 'hidden' : 'range';
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';
    const options = this.getOptions(fieldName);
    let attributes = "";

    if (options) {
      if (options.min !== undefined) attributes += ` min="${options.min}"`;
      if (options.max !== undefined) attributes += ` max="${options.max}"`;
      if (options.step !== undefined) attributes += ` step="${options.step}"`;
      // Use setValue if provided, otherwise use options.value
      if (setValue) {
        attributes += ` value="${setValue}"`;
      } else if (options.value !== undefined) {
        attributes += ` value="${options.value}"`;
      }
    } else if (setValue) {
      attributes += ` value="${setValue}"`;
    }

    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="form-nexa-range" ${disabled} ${readonly}${attributes} />`;
  }

  generateFileInput(fieldName, placeholder, size, isFloating,setValue) {
    // ✅ Generate unique random ID for this file input instance
    const randomID = fieldName + Math.floor(Math.random() * 1000);
    
    // ✅ FIX: Use this.formData.form instead of this.form
    const fileUploadConfig = this.formData.form?.[fieldName] || {};

    // ambil konfigurasi dari data terbaru
    let acceptAttribute = fileUploadConfig.fieldAccept || "";

    // ✅ NORMALIZE: Ensure accept attribute has proper format for HTML5
    if (acceptAttribute) {
      acceptAttribute = acceptAttribute
        .split(",")
        .map((ext) => {
          ext = ext.trim().toLowerCase();
          // Add dot if not present
          return ext.startsWith(".") ? ext : "." + ext;
        })
        .join(",");
    }

    let multipleAttribute = fileUploadConfig.fieldMultiple ? "multiple" : "";

    let fileHTML = `
    <div class="nx-media nx-fileupload" id="fileUpload-${randomID}">
      <img
        style="height: 50px; width: 50px"
        src="${window.NEXA.url}/assets/images/500px.png"
        alt="preview"
        class="nx-media-img"
        id="preview-image-${randomID}"
      />
      <i
        id="fa-icon-${randomID}"
        class="fas fa-file"
        style="
          display: none;
          height: 50px;
          width: 50px;
          font-size: 32px;
          color: #666;
          align-items: center;
          justify-content: center;
        "
      ></i>
      <input
        type="file"
        id="${randomID}"
        name="${fieldName}" 
        class="form-nexa-file-input"
        accept="${acceptAttribute}" 
        ${multipleAttribute}
        data-max-size="${fileUploadConfig.fileUploadSize || "5MB"}"
        data-max-files="${fileUploadConfig.fieldMultiple ? "5" : "1"}"
      />
      <div class="nx-media-body">
        <h5>${fileUploadConfig.placeholder || placeholder}</h5>
        <p id="nx-file-type-${randomID}">Maksimal ${
      fileUploadConfig.fileUploadSize || "5MB"
    }, tipe: ${acceptAttribute || "semua"}</p>
        <small id="file-name-${randomID}" style="color: #666"></small>
      </div>
    </div>`;

    // jalankan setelah DOM render
    setTimeout(() => {
      this.fileUpload(fileUploadConfig, fieldName, setValue, randomID);
    }, 0);

    return fileHTML;
  }

  fileUpload(data = {}, fieldName, setValue, randomID) {
    // ✅ CRITICAL FIX: Add comprehensive null checks and use unique IDs
    const forImages = window.NEXA.url + "/assets/images/500px.png";
    const fileInput = document.getElementById(randomID);
    const previewImage = document.getElementById(`preview-image-${randomID}`);
    const faIcon = document.getElementById(`fa-icon-${randomID}`);
    const fileName = document.getElementById(`file-name-${randomID}`);
    const fileTypeDisplay = document.getElementById(
      `nx-file-type-${randomID}`
    );
    const defaultSrc = forImages;
    
    // ✅ Store original setValue for restoration when file input is cleared
    let originalSetValue = setValue;
    let originalFileName = '';
    let originalExtension = '';
    let originalIsImage = false;

    // ✅ NEW: Handle existing file path from setValue
    if (setValue && typeof setValue === 'string' && setValue.trim() !== '') {
      // Extract filename from path
      const pathParts = setValue.split('/');
      const fileNameFromPath = pathParts[pathParts.length - 1];
      
      // Extract file extension
      const extension = fileNameFromPath.split('.').pop().toLowerCase();
      
      // Store original values for restoration
      originalFileName = fileNameFromPath;
      originalExtension = extension;
      
      // Set filename display
      if (fileName) {
        fileName.textContent = fileNameFromPath;
      }
      
      // Check if it's an image file
      const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
      originalIsImage = imageExtensions.includes(extension);
      
      if (originalIsImage) {
        // Show existing image
        if (previewImage) {
          previewImage.src = window.NEXA.url + "/" + setValue;
          previewImage.alt = fileNameFromPath;
          previewImage.style.display = "block";
        }
        if (faIcon) {
          faIcon.style.display = "none";
        }
      } else {
        // Show appropriate icon for non-image files
        const fileIcons = {
          // Documents
          pdf: "fas fa-file-pdf",
          doc: "fas fa-file-word",
          docx: "fas fa-file-word",
          xls: "fas fa-file-excel",
          xlsx: "fas fa-file-excel",
          ppt: "fas fa-file-powerpoint",
          pptx: "fas fa-file-powerpoint",
          txt: "fas fa-file-alt",
          rtf: "fas fa-file-alt",
          // Archives
          zip: "fas fa-file-archive",
          rar: "fas fa-file-archive",
          "7z": "fas fa-file-archive",
          // Audio
          mp3: "fas fa-file-audio",
          wav: "fas fa-file-audio",
          // Video
          mp4: "fas fa-file-video",
          avi: "fas fa-file-video",
          mov: "fas fa-file-video"
        };
        
        const fileColors = {
          pdf: "#F44336", doc: "#2196F3", docx: "#2196F3",
          xls: "#4CAF50", xlsx: "#4CAF50", ppt: "#FF9800", pptx: "#FF9800",
          txt: "#9E9E9E", rtf: "#9E9E9E", zip: "#795548", rar: "#795548",
          "7z": "#795548", mp3: "#E91E63", wav: "#E91E63",
          mp4: "#9C27B0", avi: "#9C27B0", mov: "#9C27B0"
        };
        
        if (previewImage) {
          previewImage.style.display = "none";
        }
        if (faIcon) {
          faIcon.style.display = "flex";
          faIcon.className = fileIcons[extension] || "fas fa-file";
          faIcon.style.color = fileColors[extension] || "#666";
        }
      }
    } else {
      // No existing file, set default preview
      if (previewImage) {
        previewImage.src = forImages;
        previewImage.style.display = "block";
      }
      if (faIcon) {
        faIcon.style.display = "none";
      }
    }

    // ✅ SAFE: Set accept attribute from data parameter with null checks
    if (data && typeof data === "object" && data.fieldAccept) {
      if (fileInput) {
        // ✅ NORMALIZE: Ensure accept attribute has proper format for HTML5
        let normalizedAccept = data.fieldAccept
          .split(",")
          .map((ext) => {
            ext = ext.trim().toLowerCase();
            // Add dot if not present
            return ext.startsWith(".") ? ext : "." + ext;
          })
          .join(",");

        fileInput.setAttribute("accept", normalizedAccept);
      }
      // Display accepted file types
      if (fileTypeDisplay) {
        fileTypeDisplay.textContent = `Maksimal ${
          data.fileUploadSize || "5MB"
        }, tipe: ${data.fieldAccept}`;
      }
    } else {
      if (fileTypeDisplay) {
        fileTypeDisplay.textContent = `Maksimal ${
          data.fileUploadSize || "5MB"
        }, tipe: semua`;
      }
    }

    // ✅ SAFE: Validation function for file types with null checks
    function validateFileType(file) {
      if (
        !data ||
        typeof data !== "object" ||
        !data.fieldAccept ||
        typeof data.fieldAccept !== "string"
      ) {
        return true; // Allow all files if no fieldAccept specified
      }

      try {
        const allowedExtensions = data.fieldAccept
          .split(",")
          .map((ext) => ext.trim().toLowerCase());
        const fileName = file.name.toLowerCase();
        const fileExtension = fileName.split(".").pop(); // Get extension without dot
        const fileExtensionWithDot = "." + fileExtension; // With dot

        // ✅ FIXED: Check both formats - with and without dot
        const isValid =
          allowedExtensions.includes(fileExtension) ||
          allowedExtensions.includes(fileExtensionWithDot);

        return isValid;
      } catch (error) {
        return true; // Allow file if validation fails
      }
    }
    // File type to FontAwesome icon mapping
    const fileIcons = {
      // Images
      jpg: "fas fa-file-image",
      jpeg: "fas fa-file-image",
      png: "fas fa-file-image",
      gif: "fas fa-file-image",
      webp: "fas fa-file-image",
      bmp: "fas fa-file-image",
      svg: "fas fa-file-image",

      // Documents
      pdf: "fas fa-file-pdf",
      doc: "fas fa-file-word",
      docx: "fas fa-file-word",
      xls: "fas fa-file-excel",
      xlsx: "fas fa-file-excel",
      ppt: "fas fa-file-powerpoint",
      pptx: "fas fa-file-powerpoint",
      txt: "fas fa-file-alt",
      rtf: "fas fa-file-alt",

      // Data & Config
      xml: "fas fa-file-code",
      yaml: "fas fa-file-code",
      yml: "fas fa-file-code",
      json: "fas fa-file-code",
      csv: "fas fa-file-csv",

      // Archives
      zip: "fas fa-file-archive",
      rar: "fas fa-file-archive",
      "7z": "fas fa-file-archive",
      tar: "fas fa-file-archive",
      gz: "fas fa-file-archive",

      // Audio
      mp3: "fas fa-file-audio",
      wav: "fas fa-file-audio",
      flac: "fas fa-file-audio",
      aac: "fas fa-file-audio",
      ogg: "fas fa-file-audio",

      // Video
      mp4: "fas fa-file-video",
      avi: "fas fa-file-video",
      mov: "fas fa-file-video",
      wmv: "fas fa-file-video",
      flv: "fas fa-file-video",
      mkv: "fas fa-file-video",
      webm: "fas fa-file-video",
    };

    // Color mapping for file types
    const fileColors = {
      // Images
      jpg: "#4CAF50",
      jpeg: "#4CAF50",
      png: "#4CAF50",
      gif: "#4CAF50",
      webp: "#4CAF50",
      bmp: "#4CAF50",
      svg: "#4CAF50",

      // Documents
      pdf: "#F44336",
      doc: "#2196F3",
      docx: "#2196F3",
      xls: "#4CAF50",
      xlsx: "#4CAF50",
      ppt: "#FF9800",
      pptx: "#FF9800",
      txt: "#9E9E9E",
      rtf: "#9E9E9E",

      // Data & Config
      xml: "#FF5722",
      yaml: "#FF5722",
      yml: "#FF5722",
      json: "#FFC107",
      csv: "#4CAF50",

      // Archives
      zip: "#795548",
      rar: "#795548",
      "7z": "#795548",
      tar: "#795548",
      gz: "#795548",

      // Audio
      mp3: "#E91E63",
      wav: "#E91E63",
      flac: "#E91E63",
      aac: "#E91E63",
      ogg: "#E91E63",

      // Video
      mp4: "#9C27B0",
      avi: "#9C27B0",
      mov: "#9C27B0",
      wmv: "#9C27B0",
      flv: "#9C27B0",
      mkv: "#9C27B0",
      webm: "#9C27B0",
    };

    function showImage() {
      if (previewImage) {
        previewImage.style.display = "block";
      }
      if (faIcon) {
        faIcon.style.display = "none";
      }
    }

    function showIcon(iconClass, extension) {
      if (previewImage) {
        previewImage.style.display = "none";
      }
      if (faIcon) {
        faIcon.style.display = "flex";
        faIcon.className = iconClass;

        // Apply color based on file type
        const color = fileColors[extension] || "#666";
        faIcon.style.color = color;
      }
    }
    if (!fileInput) {
      return;
    }

    fileInput.addEventListener("change", function (event) {
      const file = event.target.files[0];
      if (file) {
        // ✅ SAFE: Validate file type with better error handling
        if (!validateFileType(file)) {
          // Show error message in nx-file-type
          if (fileTypeDisplay) {
            const acceptTypes =
              data && data.fieldAccept ? data.fieldAccept : "specified";
            fileTypeDisplay.textContent = `❌ File type not allowed. Only ${acceptTypes} files are permitted.`;
            fileTypeDisplay.style.color = "#F44336";
          }

          // Reset input
          fileInput.value = "";
          if (fileName) {
            fileName.textContent = "";
          }
          if (previewImage) {
            previewImage.src = defaultSrc;
            previewImage.alt = "preview";
          }
          showImage();

          // Reset error message after 3 seconds
          setTimeout(() => {
            if (fileTypeDisplay) {
              const acceptTypes =
                data && data.fieldAccept ? data.fieldAccept : "semua";
              fileTypeDisplay.textContent = `Maksimal ${
                data.fileUploadSize || "5MB"
              }, tipe: ${acceptTypes}`;
              fileTypeDisplay.style.color = "";
            }
          }, 3000);

          return;
        }

        if (fileName) {
          fileName.textContent = file.name;
        }

        // Get file extension
        const extension = file.name.split(".").pop().toLowerCase();

        if (file.type.startsWith("image/")) {
          // Show image preview
          const reader = new FileReader();
          reader.onload = function (e) {
            if (previewImage) {
              previewImage.src = e.target.result;
              previewImage.alt = file.name;
            }
            showImage();
          };
          reader.readAsDataURL(file);
        } else if (fileIcons[extension]) {
          // Show FontAwesome icon
          showIcon(fileIcons[extension], extension);
        } else {
          // Show default file icon
          showIcon("fas fa-file", "default");
        }
      } else {
        // Reset to original setValue or default
        if (originalSetValue && originalSetValue.trim() !== '') {
          // Restore original setValue preview
          if (fileName) {
            fileName.textContent = originalFileName;
          }
          
          if (originalIsImage) {
            // Restore original image
            if (previewImage) {
              previewImage.src = window.NEXA.url + "/" + originalSetValue;
              previewImage.alt = originalFileName;
              previewImage.style.display = "block";
            }
            if (faIcon) {
              faIcon.style.display = "none";
            }
          } else {
            // Restore original icon
            const fileIcons = {
              pdf: "fas fa-file-pdf", doc: "fas fa-file-word", docx: "fas fa-file-word",
              xls: "fas fa-file-excel", xlsx: "fas fa-file-excel", ppt: "fas fa-file-powerpoint", pptx: "fas fa-file-powerpoint",
              txt: "fas fa-file-alt", rtf: "fas fa-file-alt", zip: "fas fa-file-archive", rar: "fas fa-file-archive",
              "7z": "fas fa-file-archive", mp3: "fas fa-file-audio", wav: "fas fa-file-audio",
              mp4: "fas fa-file-video", avi: "fas fa-file-video", mov: "fas fa-file-video"
            };
            const fileColors = {
              pdf: "#F44336", doc: "#2196F3", docx: "#2196F3", xls: "#4CAF50", xlsx: "#4CAF50",
              ppt: "#FF9800", pptx: "#FF9800", txt: "#9E9E9E", rtf: "#9E9E9E", zip: "#795548",
              rar: "#795548", "7z": "#795548", mp3: "#E91E63", wav: "#E91E63",
              mp4: "#9C27B0", avi: "#9C27B0", mov: "#9C27B0"
            };
            
            if (previewImage) {
              previewImage.style.display = "none";
            }
            if (faIcon) {
              faIcon.style.display = "flex";
              faIcon.className = fileIcons[originalExtension] || "fas fa-file";
              faIcon.style.color = fileColors[originalExtension] || "#666";
            }
          }
        } else {
          // No original setValue, reset to default
          if (previewImage) {
            previewImage.src = defaultSrc;
            previewImage.alt = "preview";
          }
          if (fileName) {
            fileName.textContent = "";
          }
          showImage();
        }
      }
    });
    // Click handlers
    if (previewImage) {
      previewImage.addEventListener("click", function () {
        if (fileInput) {
          fileInput.click();
        }
      });
    }

    if (faIcon) {
      faIcon.addEventListener("click", function () {
        if (fileInput) {
          fileInput.click();
        }
      });
    }
  }

  generateCheckboxInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    const options = this.getOptions(fieldName);
    let checkboxHTML = "";

    // Add group label/title outside the grid
    checkboxHTML += `<div class="form-nexa-group-checkbox mb-10px">`;
    checkboxHTML += `<div class="mb-10px">${placeholder}</div>`;
    checkboxHTML += `<div class="nx-checkbox-grid">`;

    if (fieldConfig.select.data && Array.isArray(fieldConfig.select.data)) {
      // Multiple checkboxes for single selection behavior
      fieldConfig.select.data.forEach((option, index) => {
        const checkboxId = `${fieldName}_${index}`;
        const checked = setValue && setValue == option.value ? 'checked' : '';
        checkboxHTML += `<div class="nx-checkbox-item">`;
        checkboxHTML += `<input type="checkbox" id="${checkboxId}" name="${fieldName}" value="${option.value}" data-label="${option.value}" class="single-select-checkbox" ${checked} />`;
        checkboxHTML += `<label for="${checkboxId}">`;
        checkboxHTML += `<span class="nx-checkmark"></span>`;
        checkboxHTML += `<span class="nx-checkbox-text">${option.label}</span>`;
        checkboxHTML += `</label>`;
        checkboxHTML += `<small id="errors_${checkboxId}" class="error-message"></small>`;
        checkboxHTML += `</div>`;
      });
    }
    checkboxHTML += `</div>`; // Close nx-checkbox-grid
    checkboxHTML += `</div>`; // Close nx-checkbox-grid
    return checkboxHTML;
  }

  generateRadioInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    const options = this.getOptions(fieldName);
    let radioHTML = "";
    // Add group label/title outside the grid
    radioHTML += `<div class="form-nexa-group-checkbox mb-10px">`;
    radioHTML += `<div class="mb-10px">${placeholder}</div>`;
    radioHTML += `<div class="nx-radio-grid">`;

    if (fieldConfig.select.data) {
      fieldConfig.select.data.forEach((option, index) => {
        const radioId = `${fieldName}_${index}`;
        const checked = setValue && setValue == option.value ? 'checked' : '';
        radioHTML += `<div class="nx-radio-item">`;
        radioHTML += `<input type="radio" id="${radioId}" name="${fieldName}" value="${option.value}" data-label="${option.value}" ${checked} />`;
        radioHTML += `<label for="${radioId}">`;
        radioHTML += `<span class="nx-radio-mark"></span>`;
        radioHTML += `<span class="nx-radio-text">${option.label}</span>`;
        radioHTML += `</label>`;
        radioHTML += `<small id="errors_${fieldName}" class="error-message"></small>`;
        radioHTML += `</div>`;
      });
    }

    radioHTML += `</div>`; // Close nx-radio-grid
    radioHTML += `</div>`; // Close nx-radio-grid
    return radioHTML;
  }

  generateSwitchInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    const options = this.getOptions(fieldName);
    let switchHTML = "";

    // Add group label/title outside the grid
    switchHTML += `<div class="form-nexa-group-checkbox mb-10px">`;
    switchHTML += `<div class="mb-10px">${placeholder}</div>`;
    switchHTML += `<div class="nx-switch-grid">`;

    if (fieldConfig.select.data && Array.isArray(fieldConfig.select.data)) {
      // Multiple switches for single selection - use radio behavior
      fieldConfig.select.data.forEach((option, index) => {
        const switchId = `${fieldName}_${index}`;
        const isChecked = (setValue && setValue == option.value) || option.checked ? "checked" : "";
        switchHTML += `<div class="nx-switch-item">`;
        // Use radio type but styled as switch for single selection
        switchHTML += `<input type="checkbox" id="${switchId}" name="${fieldName}" value="${option.value}" data-label="${option.value}" class="single-select-switch" ${isChecked} />`;
        switchHTML += `<label for="${switchId}">`;
        switchHTML += `<span class="nx-switch"></span>`;
        switchHTML += `<span class="nx-switch-text">${option.label}</span>`;
        switchHTML += `</label>`;
        switchHTML += `<small id="errors_${switchId}" class="error-message"></small>`;
        switchHTML += `</div>`;
      });
    }

    switchHTML += `</div>`; // Close nx-switch-grid
    switchHTML += `</div>`; // Close nx-switch-grid
    return switchHTML;
  }



  generateHiddenInput(fieldName, placeholder, size, isFloating) {
    const options = this.getOptions(fieldName);
    const value = options?.value || "";
    // ✅ FIX: Hidden input dari generateHiddenInput tidak menampilkan error message
    // Tambahkan attribute khusus untuk membedakan dengan hidden input lainnya
    return `<input type="hidden" id="${fieldName}" name="${fieldName}" data-hidden-no-error="true" value="${value}" />`;
  }

  generateSubmitButton(data) {
    const style = this.getFormStyle();
    const buttonType = style.button || "nx-btn-primary";
    const isGrid = this.hasGridColumns();
    if (!this.options.footer) {
      return "";
    }
    return `
   <div class="nx-content-footer">
     <div class="nx-footer-custom"></div>
        <div class="nx-footer-buttons">
         <button type="button" class="btn btn-secondary">Cancel</button>
         <button type="button" class="${
           this.settings.buttontype || "btn btn-primary"
         }" id="sendContent_${
      data.id
    }" data-original-text="Submit">Submit</button>
       </div>
      </div>
    </div>

    `;
  }

  /**
   * Get field configuration from new structure
   * @param {string} fieldName
   * @returns {Object}
   */
  getFieldConfig(fieldName) {
    if (!this.formData.form || !this.formData.form[fieldName]) {
      return {};
    }
    return this.formData.form[fieldName];
  }

  /**
   * Get placeholder for field
   * @param {string} fieldName
   * @returns {string}
   */
  getPlaceholder(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    return fieldConfig.placeholder || fieldConfig.label || fieldName;
  }

  /**
   * Get icon for field
   * @param {string} fieldName
   * @returns {string|null}
   */
  getIcon(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    return fieldConfig.icons || null;
  }

  /**
   * Get options for select/radio/checkbox fields
   * @param {string} fieldName
   * @returns {Array|null}
   */
  getOptions(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    return fieldConfig.options || null;
  }

  /**
   * Get grid class for field
   * @param {string} fieldName
   * @returns {string|null}
   */
  getGridClass(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    return fieldConfig.columnWidth || null;
  }

  createFormElement(htmlString) {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = htmlString;
    return tempDiv.firstElementChild;
  }

  attachWizardNavigation() {
    if (!this.formElement) {
      return;
    }
    if (this.formElement.dataset.nexaWizardNavBound === "1") {
      return;
    }
    this.formElement.dataset.nexaWizardNavBound = "1";
    const total = this._wizardStepCount || this.getWizardStepGroups().length;
    if (total <= 1) {
      return;
    }
    const prev = this.formElement.querySelector(".nx-wizard-prev");
    const next = this.formElement.querySelector(".nx-wizard-next");
    prev?.addEventListener("click", async () => {
      if (this._wizardStep > 0) {
        this.clearErrors();
        this._wizardStep--;
        this.syncWizardUI();
      }
    });
    next?.addEventListener("click", async () => {
      if (!(await this.validateCurrentWizardStep())) {
        return;
      }
      if (this._wizardStep < total - 1) {
        this._wizardStep++;
        this.syncWizardUI();
      }
    });
  }

  attachEventListeners() {
    if (!this.formElement) return;
    if (this.formElement.dataset.nexaWizardListenersBound === "1") {
      return;
    }
    this.formElement.dataset.nexaWizardListenersBound = "1";

    // Handle search inputs
    const searchInputs = this.formElement.querySelectorAll(
      ".form-nexa-search-input"
    );
    searchInputs.forEach((input) => {
      this.attachSearchEvents(input);
    });

    // Handle form submission (wizard: Enter / submit hanya mengirim di langkah terakhir)
    this.formElement.addEventListener("submit", async (e) => {
      e.preventDefault();
      const total = this._wizardStepCount || this.getWizardStepGroups().length;
      if (total > 1 && this._wizardStep < total - 1) {
        if (!(await this.validateCurrentWizardStep())) {
          return;
        }
        this._wizardStep++;
        this.syncWizardUI();
        return;
      }
      await this.handleSubmit();
    });

    this.attachWizardNavigation();

    // Handle floating labels for all inputs
    const inputs = this.formElement.querySelectorAll(
      'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea'
    );
    inputs.forEach((input) => {
      this.handleFloatingLabel(input);
    });

    // File upload is now handled directly in generateFileInput with setTimeout

    // Handle range inputs
    const rangeInputs = this.formElement.querySelectorAll(
      'input[type="range"]'
    );
    rangeInputs.forEach((rangeInput) => {
      this.attachRangeEvents(rangeInput);
    });

    // Handle checkbox and radio inputs
    const checkboxInputs = this.formElement.querySelectorAll(
      'input[type="checkbox"], input[type="radio"]'
    );
    checkboxInputs.forEach((input) => {
      this.attachCheckboxRadioEvents(input);
    });

    // Handle single-select behavior for checkboxes and switches
    this.attachSingleSelectBehavior();

    // Initialize Select2 for select elements with delay to ensure DOM is ready
    setTimeout(() => {
      this.initializeSelect2();
    }, 100);
  }

  attachSearchEvents(input) {
    const container = input.closest(".form-nexa-search-container");
    const dropdown = container.querySelector(".form-nexa-search-dropdown");
    const items = container.querySelectorAll(".form-nexa-search-item");

    input.addEventListener("focus", () => {
      container.classList.add("active");
    });

    input.addEventListener("blur", (e) => {
      // Delay hiding to allow click on items
      setTimeout(() => {
        container.classList.remove("active");
      }, 200);
    });

    input.addEventListener("input", (e) => {
      const value = e.target.value.toLowerCase();
      items.forEach((item) => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(value) ? "block" : "none";
      });
    });

    items.forEach((item) => {
      item.addEventListener("click", () => {
        input.value = item.textContent;
        input.setAttribute("data-value", item.getAttribute("data-value"));
        container.classList.remove("active");
        this.triggerFloatingLabel(input);
      });
    });
  }

  handleFloatingLabel(element) {
    // Cache label reference for performance
    const label = this.findLabelForElement(element);

    if (!label || label.tagName !== "LABEL") {
      return; // Exit early if no label found
    }

    const updateLabel = () => {
      if (element.value && element.value !== "") {
        label.classList.add("active");
      } else {
        label.classList.remove("active");
      }
    };

    const handleFocus = () => {
      label.classList.add("active");
    };

    // Optimized: Only use essential events with cached label
    element.addEventListener("input", updateLabel); // Real-time updates
    element.addEventListener("focus", handleFocus); // Focus handling
    element.addEventListener("blur", updateLabel); // Blur handling

    // Initial check
    updateLabel();
  }

  // Smart label finder for different input structures
  findLabelForElement(element) {
    // Method 1: Standard structure - label is nextElementSibling
    let label = element.nextElementSibling;

    // If next sibling is icon, get the sibling after icon
    if (label && label.tagName === "I") {
      label = label.nextElementSibling;
    }

    // Method 2: If label found and is LABEL, return it
    if (label && label.tagName === "LABEL") {
      return label;
    }

    // Method 3: For search inputs, look for label in parent container
    const isSearchInput = element.closest(".form-nexa-search-container");
    if (isSearchInput) {
      // For search inputs, the label is typically after the entire search structure
      const searchWrapper = element.closest(".form-nexa-search");
      if (searchWrapper && searchWrapper.nextElementSibling) {
        let nextElement = searchWrapper.nextElementSibling;

        // If there's an icon after search wrapper, label is after icon
        if (nextElement && nextElement.tagName === "I") {
          nextElement = nextElement.nextElementSibling;
        }

        if (nextElement && nextElement.tagName === "LABEL") {
          return nextElement;
        }
      }

      // Alternative: look in parent floating container
      const floatingContainer = element.closest(".form-nexa-floating");
      if (floatingContainer) {
        const labelInContainer = floatingContainer.querySelector(
          `label[for="${element.name}"]`
        );
        if (labelInContainer) {
          return labelInContainer;
        }
      }
    }

    // Method 4: Generic fallback - find by 'for' attribute
    const formElement = element.closest("form") || document;
    return (
      formElement.querySelector(`label[for="${element.name}"]`) ||
      formElement.querySelector(`label[for="${element.id}"]`)
    );
  }

  triggerFloatingLabel(input) {
    // Use smart label finder for consistency
    let label = this.findLabelForElement(input);

    if (label && label.tagName === "LABEL") {
      if (input.value && input.value !== "") {
        label.classList.add("active");
      } else {
        label.classList.remove("active");
      }
    }
  }

  attachRangeEvents(rangeInput) {
    // Create value display
    const valueDisplay = document.createElement("div");
    valueDisplay.className = "form-nexa-range-value";
    valueDisplay.textContent = rangeInput.value;
    rangeInput.parentNode.insertBefore(valueDisplay, rangeInput.nextSibling);

    rangeInput.addEventListener("input", (e) => {
      valueDisplay.textContent = e.target.value;
    });

    rangeInput.addEventListener("change", (e) => {
      valueDisplay.textContent = e.target.value;
    });
  }

  attachCheckboxRadioEvents(input) {
    // Add change event for data handling
    input.addEventListener("change", (e) => {
      // Trigger custom validation or events
      const event = new CustomEvent("nexaInputChange", {
        detail: {
          name: input.name,
          value: input.type === "checkbox" ? input.checked : input.value,
          type: input.type,
        },
      });
      document.dispatchEvent(event);
    });

    // Ensure proper click handling
    input.addEventListener("click", (e) => {
      // Let the default behavior work
      e.stopPropagation();
    });
  }

  attachSingleSelectBehavior() {
    if (!this.formElement) {
      return;
    }

    // Handle single-select checkboxes
    const singleSelectCheckboxes = this.formElement.querySelectorAll(
      ".single-select-checkbox"
    );

    singleSelectCheckboxes.forEach((checkbox) => {
      checkbox.addEventListener("change", (e) => {
        if (e.target.checked) {
          // Uncheck all other checkboxes with the same name
          const sameName = this.formElement.querySelectorAll(
            `input[name="${e.target.name}"].single-select-checkbox`
          );

          sameName.forEach((other) => {
            if (other !== e.target) {
              other.checked = false;
            }
          });
        }
      });
    });

    // Handle single-select switches
    const singleSelectSwitches = this.formElement.querySelectorAll(
      ".single-select-switch"
    );

    singleSelectSwitches.forEach((switchEl) => {
      switchEl.addEventListener("change", (e) => {
        if (e.target.checked) {
          // Uncheck all other switches with the same name
          const sameName = this.formElement.querySelectorAll(
            `input[name="${e.target.name}"].single-select-switch`
          );

          sameName.forEach((other) => {
            if (other !== e.target) {
              other.checked = false;
            }
          });
        }
      });
    });
  }

  initializeSelect2() {
    if (!this.formElement) {
      return;
    }

    // Check if NXUI and initSelect2 are available
    if (!window.NXUI || !window.NXUI.initSelect2) {
      return;
    }

    // Find all select elements with nexa-floating-select class, but exclude flag dropdowns and no-select2 elements
    const selectElements = this.formElement.querySelectorAll(
      "select.nexa-floating-select:not(.flag-kabupaten-select):not(.flag-kecamatan-select):not(.flag-desa-select):not(.no-select2)"
    );

    if (selectElements.length === 0) {
      return;
    }

    selectElements.forEach((selectElement) => {
      try {
        // Get field configuration
        const fieldName = selectElement.name;
        const fieldConfig = this.getFieldConfig(fieldName);

        // Check if element is already initialized
        if (selectElement.hasAttribute("data-select2-initialized")) {
          return;
        }

        // Skip if element is part of flag component
        if (selectElement.closest('[data-field]') && selectElement.closest('[data-field]').innerHTML.includes('Loading flag input')) {
          return;
        }

        const select2Options = {
          placeholder: fieldConfig.placeholder || "Pilih opsi...",
          searchInputPlaceholder: "Cari opsi...",
          allowClear:
            fieldConfig.validation !== "2" && fieldConfig.validation !== 2, // Allow clear if not required
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

        // Initialize Select2
        window.NXUI.initSelect2(`#${selectElement.id}`, select2Options);

        // Mark as initialized
        selectElement.setAttribute("data-select2-initialized", "true");
      } catch (error) {
      }
    });
  }

  /**
   * Re-initialize Select2 for dynamically added select elements
   */
  reinitializeSelect2() {
    // Destroy existing Select2 instances first
    this.destroySelect2();

    // Re-initialize
    this.initializeSelect2();
  }

  /**
   * Destroy all Select2 instances in the form
   */
  destroySelect2() {
    if (!this.formElement) return;

    const selectElements = this.formElement.querySelectorAll(
      "select.nexa-floating-select"
    );

    selectElements.forEach((selectElement) => {
      try {
        // Check if element was initialized
        if (selectElement.hasAttribute("data-select2-initialized")) {
          if (window.$ && window.$.fn.select2) {
            $(selectElement).select2("destroy");
          }

          // Remove initialization marker
          selectElement.removeAttribute("data-select2-initialized");
        }
      } catch (error) {
        // Error destroying Select2
      }
    });
  }

  async handleSubmit() {
    const formData = await this.getFormData();
    const style = this.getFormStyle();

    if (style.validation) {
      const validation = this.validateForm(formData);
      if (!validation.isValid) {
        this.displayErrors(validation.errors);
        return;
      }
    }

    // Clear any existing errors
    this.clearErrors();

    // Trigger custom event dengan metadata form
    const event = new CustomEvent("nexaFormSubmit", {
      detail: {
        formData,
        formId: this.formId,
        className: this.className,
        tableName: this.tableName,
        tableKey: this.formData.tableKey,
        formMeta: {
          id: this.formData.id,
          version: this.formData.version,
          store: this.formData.store,
          label: this.formData.label,
        },
      },
    });
    document.dispatchEvent(event);
    this.invokeSubmitHandlers(event.detail);
  }

  /**
   * Panggil callback dari `options.onSubmit`, `settings.onSubmit`, atau nama fungsi `send` (seperti NexaForm).
   * @param {object} detail — sama dengan `CustomEvent#detail` pada `nexaFormSubmit`
   */
  invokeSubmitHandlers(detail) {
    const settings = this.formData.settings || {};
    const onSubmit = this.options.onSubmit ?? settings.onSubmit;
    if (typeof onSubmit === "function") {
      try {
        onSubmit(detail);
      } catch (e) {
        console.error("[NexaWizard] onSubmit:", e);
      }
      return;
    }
    const sendName = this.options.send ?? settings.send;
    if (!sendName || typeof sendName !== "string") {
      return;
    }
    const setDataBy = this.options.setDataBy ?? settings.setDataBy;
    let targetFunction = null;
    if (typeof window !== "undefined" && typeof window[sendName] === "function") {
      targetFunction = window[sendName];
    } else if (
      typeof window !== "undefined" &&
      window.NXUI &&
      typeof window.NXUI[sendName] === "function"
    ) {
      targetFunction = window.NXUI[sendName];
    } else if (
      typeof window !== "undefined" &&
      window.nx &&
      typeof window.nx[sendName] === "function"
    ) {
      targetFunction = window.nx[sendName];
    } else if (
      typeof window !== "undefined" &&
      window.nx &&
      window.nx._global &&
      typeof window.nx._global[sendName] === "function"
    ) {
      targetFunction = window.nx._global[sendName];
    }
    if (targetFunction) {
      try {
        targetFunction(this.formId, detail.formData, setDataBy);
      } catch (e) {
        console.error("[NexaWizard] send:", sendName, e);
      }
    }
  }

  async getFormData() {
    const formData = {};
    const inputs = this.formElement.querySelectorAll("input, select, textarea");

    // ✅ FIXED: Change to for...of to support async/await properly
    for (const input of inputs) {
      if (!input.name) continue; // Skip inputs without names

      switch (input.type) {
        case "checkbox":
          // Handle checkboxes
          if (input.checked) {
            // Use label from data-label attribute if available, otherwise use value for checked state
            const value = input.getAttribute("data-label") || input.value;

            // For single-select checkboxes and switches, store the selected value
            if (
              input.classList.contains("single-select-checkbox") ||
              input.classList.contains("single-select-switch")
            ) {
              formData[input.name] = value;
            } else {
              // For regular checkboxes, use boolean or collect multiple values
              if (input.name.endsWith("[]")) {
                const fieldName = input.name.slice(0, -2); // Remove '[]' from name
                if (!formData[fieldName]) {
                  formData[fieldName] = [];
                }
                formData[fieldName].push(value);
              } else {
                formData[input.name] = input.checked;
              }
            }
          }
          break;

        case "radio":
          if (input.checked) {
            // Use label from data-label attribute if available, otherwise use value
            const value = input.getAttribute("data-label") || input.value;
            formData[input.name] = value;
          }
          break;

        case "file":
          if (input.files.length > 0) {
            if (input.multiple) {
              const filesArray = [];
              for (const file of input.files) {
                const binaryArray = await fileToBinaryArray(file);
                filesArray.push({
                  name: file.name,
                  size: file.size,
                  type: file.type,
                  lastModified: file.lastModified,
                  content: binaryArray, // ✅ FIXED: Binary array instead of File object
                });
              }
              formData[input.name] = filesArray;
            } else {
              const file = input.files[0];
              const binaryArray = await fileToBinaryArray(file);
              formData[input.name] = {
                name: file.name,
                size: file.size,
                type: file.type,
                lastModified: file.lastModified,
                content: binaryArray, // ✅ FIXED: Binary array instead of File object
              };
            }
          } else {
            formData[input.name] = input.multiple ? [] : null;
          }
          break;

        case "text":
          if (input.classList.contains("form-nexa-search-input")) {
            formData[input.name] = {
              value: input.getAttribute("data-value") || input.value,
              label: input.value,
            };
          } else {
            formData[input.name] = input.value;
          }
          break;

        case "number":
        case "currency":
          formData[input.name] = input.value ? parseFloat(input.value) : null;
          break;

        case "range":
          formData[input.name] = parseFloat(input.value);
          break;

        case "color":
          formData[input.name] = input.value;
          break;

        case "date":
        case "datetime-local":
        case "time":
        case "month":
        case "week":
          formData[input.name] = input.value ? new Date(input.value) : null;
          break;

        case "hidden":
          formData[input.name] = input.value;
          break;

        default:
          // Handle select, email, password, tel, url, textarea, etc.
          if (input.tagName.toLowerCase() === "select") {
            // For select elements, try to get the label of selected option
            const selectedOption = input.options[input.selectedIndex];
            const finalValue = selectedOption
              ? selectedOption.text
              : input.value;
            formData[input.name] = finalValue;
          } else {
            formData[input.name] = input.value;
          }
      }
    }

    return formData;
  }

  validateForm(formData) {
    const errors = {};
    let isValid = true;

    // Validasi berdasarkan field configuration dari format baru dengan urutan
    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      // Skip field 'id' jika mode adalah 'insert'
      if (fieldName === "id" && this.options.mode === "insert") {
        continue;
      }

      const value = formData[fieldName];
      const validation = fieldConfig.validation;
      const placeholder =
        fieldConfig.placeholder || fieldConfig.label || fieldName;

      // Cek required field (validation = "2" atau validation = true)
      if (validation === "2" || validation === true || validation === 2) {
        if (!value || (typeof value === "object" && !value.value)) {
          errors[fieldName] = `${placeholder} wajib diisi`;
          isValid = false;
        }
      }

      // Validasi tambahan berdasarkan tipe field
      if (value) {
        switch (fieldConfig.type) {
          case "email":
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
              errors[
                fieldName
              ] = `${placeholder} harus berupa email yang valid`;
              isValid = false;
            }
            break;
          case "number":
            if (isNaN(value)) {
              errors[fieldName] = `${placeholder} harus berupa angka`;
              isValid = false;
            }
            break;
          case "url":
            try {
              new URL(value);
            } catch {
              errors[fieldName] = `${placeholder} harus berupa URL yang valid`;
              isValid = false;
            }
            break;
        }
      }
    }

    return { isValid, errors };
  }

  /**
   * Cari input/select/textarea di dalam form ini (selaras pola NexaValidation).
   * @param {string} fieldName — biasanya sama dengan `id` / `name` skema
   * @returns {Element|null}
   */
  resolveFieldElement(fieldName) {
    if (!this.formElement || !fieldName) {
      return null;
    }
    try {
      const byId = this.formElement.querySelector(`#${CSS.escape(fieldName)}`);
      if (byId?.matches?.("input, select, textarea")) {
        return byId;
      }
    } catch (_) {
      /* id tidak valid untuk selector */
    }
    return this.formElement.querySelector(
      `input[name="${CSS.escape(fieldName)}"], select[name="${CSS.escape(fieldName)}"], textarea[name="${CSS.escape(fieldName)}"]`
    );
  }

  /**
   * Tampilkan error di UI — buat node `.error-message` jika belum ada (seperti NexaValidation),
   * karena field teks NexaWizard tidak menyisipkan `<small id="errors_*">` di HTML.
   */
  displayErrors(errors) {
    if (!this.formElement) {
      return;
    }
    for (const [fieldName, errorMessage] of Object.entries(errors)) {
      const input = this.resolveFieldElement(fieldName);
      if (!input) {
        continue;
      }

      if (
        input.type === "hidden" &&
        (input.hasAttribute("data-hidden-no-error") ||
          input.getAttribute("data-hidden-no-error") === "true")
      ) {
        continue;
      }

      let formGroup = null;
      if (input.type === "checkbox" || input.type === "radio") {
        formGroup = input.closest(
          ".form-nexa-check, .nx-checkbox-item, .nx-radio-item, .nx-switch-item, .nx-checkbox-grid, .nx-radio-grid, .nx-switch-grid"
        );
      }
      if (!formGroup) {
        formGroup = input.closest(
          ".form-nexa-floating, .form-nexa, .form-nexa-group, .form-nexa-group1"
        );
      }

      const elementId = input.id || input.name || fieldName;
      const errId = `errors_${elementId}`;
      const existing = this.formElement.querySelector(`#${CSS.escape(errId)}`);
      if (existing) {
        existing.remove();
      }
      if (elementId !== fieldName) {
        const ex2 = this.formElement.querySelector(
          `#${CSS.escape(`errors_${fieldName}`)}`
        );
        if (ex2) {
          ex2.remove();
        }
      }

      const errorDiv = document.createElement("div");
      errorDiv.className = "error-message";
      errorDiv.id = `errors_${elementId}`;
      errorDiv.setAttribute("data-field-id", String(elementId));
      errorDiv.setAttribute("data-field-name", input.name || "");
      errorDiv.textContent = errorMessage;
      errorDiv.style.fontSize = "0.65rem";
      errorDiv.style.color = "#dc3545";
      errorDiv.style.width = "100%";
      errorDiv.style.display = "block";

      input.classList.add("is-invalid");

      if (formGroup) {
        formGroup.classList.add("form-error");
        const iconContainer = formGroup.querySelector(".form-nexa-icon");
        if (iconContainer) {
          if (iconContainer.nextSibling) {
            formGroup.insertBefore(errorDiv, iconContainer.nextSibling);
          } else {
            formGroup.appendChild(errorDiv);
          }
        } else {
          const label = formGroup.querySelector("label");
          if (label && formGroup.contains(label)) {
            if (label.nextSibling) {
              formGroup.insertBefore(errorDiv, label.nextSibling);
            } else {
              formGroup.appendChild(errorDiv);
            }
          } else {
            formGroup.appendChild(errorDiv);
          }
        }
      } else {
        input.insertAdjacentElement("afterend", errorDiv);
      }

      try {
        input.scrollIntoView({ block: "nearest", behavior: "smooth" });
      } catch (_) {
        /* ignore */
      }
    }
  }

  clearErrors() {
    if (!this.formElement) {
      return;
    }

    this.formElement.querySelectorAll(".error-message").forEach((el) => {
      el.remove();
    });

    this.formElement.querySelectorAll(".form-error").forEach((container) => {
      container.classList.remove("form-error");
    });

    this.formElement
      .querySelectorAll(".is-invalid")
      .forEach((el) => el.classList.remove("is-invalid"));
  }

  // NOTE: File uploads are now handled by inline scripts in generateFileInput()
  // This method is no longer needed since each field has its own script
  // initializeFileUploads() - REMOVED

  // Public methods
  render(container) {
    if (typeof container === "string") {
      container = document.querySelector(container);
    }

    if (container && this.formElement) {
      container.appendChild(this.formElement);

      // Initialize Select2 after form is rendered to DOM
      setTimeout(() => {
        this.initializeSelect2();
      }, 200);
    }

    return this.formElement;
  }

  /**
   * Hubungkan instance ke &lt;form&gt; yang sudah ada di DOM (mis. setelah `innerHTML` dari `html()`
   * ke body modal). Tanpa ini, event wizard tidak aktif pada node hasil parse HTML.
   */
  bindToDom(container) {
    if (!container) {
      return this;
    }
    let form = null;
    try {
      form = container.querySelector(`#${CSS.escape(this.formId)}`);
    } catch (_) {
      form = null;
    }
    if (!form) {
      form = container.querySelector("form");
    }
    if (!form) {
      console.warn("[NexaWizard] bindToDom: form tidak ditemukan", this.formId);
      return this;
    }
    this.formElement = form;
    delete this.formElement.dataset.nexaWizardNavBound;
    delete this.formElement.dataset.nexaWizardListenersBound;
    this.initWizardState();
    this.attachEventListeners();
    setTimeout(() => this.initializeSelect2(), 150);
    return this;
  }

  destroy() {
    // Destroy Select2 instances before removing form
    this.destroySelect2();

    if (this.formElement && this.formElement.parentNode) {
      this.formElement.parentNode.removeChild(this.formElement);
    }
  }

  reset(modalId = null) {
    // If modalId provided, find form inside modal
    if (modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        // Find form inside modal
        const form =
          modal.querySelector("form") || modal.querySelector(`#${this.formId}`);
        if (form && typeof form.reset === "function") {
          form.reset();
          // Reset floating labels in modal
          const activeLabels = modal.querySelectorAll(
            ".form-nexa-floating label.active"
          );
          activeLabels.forEach((label) => label.classList.remove("active"));
          return;
        }
      }
    }

    // Default reset for this.formElement
    if (this.formElement) {
      this.formElement.reset();
      this.clearErrors();

      // Reset floating labels
      const labels = this.formElement.querySelectorAll("label.active");
      labels.forEach((label) => label.classList.remove("active"));
    }
  }

  setData(data) {
    if (!this.formElement) return;

    for (const [fieldName, value] of Object.entries(data)) {
      const input = this.formElement.querySelector(`[name="${fieldName}"]`);
      if (!input) continue;

      switch (input.type) {
        case "checkbox":
          // Handle multiple checkboxes
          const checkboxes = this.formElement.querySelectorAll(
            `[name="${fieldName}"], [name="${fieldName}[]"]`
          );

          if (checkboxes.length > 1 && Array.isArray(value)) {
            // Multiple checkboxes with array values
            checkboxes.forEach((checkbox) => {
              checkbox.checked = value.includes(checkbox.value);
            });
          } else if (checkboxes.length === 1) {
            // Single checkbox
            checkboxes[0].checked = Boolean(value);
          } else {
            // Fallback for single input found
            input.checked = Boolean(value);
          }
          break;

        case "radio":
          const radioButtons = this.formElement.querySelectorAll(
            `[name="${fieldName}"]`
          );
          radioButtons.forEach((radio) => {
            radio.checked = radio.value === value;
          });
          break;

        case "file":
          // File inputs cannot be programmatically set for security reasons
          break;

        case "text":
          if (
            input.classList.contains("form-nexa-search-input") &&
            typeof value === "object"
          ) {
            input.value = value.label || "";
            input.setAttribute("data-value", value.value || "");
          } else {
            input.value = value || "";
          }
          this.triggerFloatingLabel(input);
          break;

        case "number":
        case "currency":
        case "range":
          input.value = value !== null && value !== undefined ? value : "";
          this.triggerFloatingLabel(input);
          break;

        case "color":
          input.value = value || "#000000";
          break;

        case "date":
        case "datetime-local":
        case "time":
        case "month":
        case "week":
          if (value instanceof Date) {
            if (input.type === "date") {
              input.value = value.toISOString().split("T")[0];
            } else if (input.type === "datetime-local") {
              input.value = value.toISOString().slice(0, 16);
            } else if (input.type === "time") {
              input.value = value.toTimeString().slice(0, 5);
            } else if (input.type === "month") {
              input.value = value.toISOString().slice(0, 7);
            } else if (input.type === "week") {
              const year = value.getFullYear();
              const week = Math.ceil(
                ((value - new Date(year, 0, 1)) / 86400000 + 1) / 7
              );
              input.value = `${year}-W${week.toString().padStart(2, "0")}`;
            }
          } else if (typeof value === "string") {
            input.value = value;
          }
          this.triggerFloatingLabel(input);
          break;

        default:
          // Handle select, email, password, tel, url, textarea, hidden, etc.
          input.value = value || "";
          this.triggerFloatingLabel(input);
      }
    }
  }

  async getData() {
    return await this.getFormData();
  }

  /**
   * Set form mode (insert/update)
   * @param {string} mode - 'insert' atau 'update'
   */
  setMode(mode) {
    this.options.mode = mode;

    // Re-generate form jika sudah di-render
    if (this.formElement) {
      this.generateForm();

      // Re-render jika form sudah ada di DOM
      const existingForm = document.getElementById(this.formId);
      if (existingForm && existingForm.parentNode) {
        existingForm.parentNode.replaceChild(this.formElement, existingForm);
        this.attachEventListeners();
        // Re-initialize Select2 after re-rendering
        this.initializeSelect2();
      }
    }
  }

  /**
   * Get current form mode
   * @returns {string}
   */
  getMode() {
    return this.options.mode;
  }

  /**
   * Get validation configuration for all fields
   * @returns {Object} Object dengan field name sebagai key dan validation level sebagai value
   */
  validasi() {
    const validationConfig = {};

    if (!this.formData.form) {
      return validationConfig;
    }

    // Loop through ordered fields dan ambil validation level
    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      // Skip field 'id' jika mode adalah 'insert'
      if (fieldName === "id" && this.options.mode === "insert") {
        continue;
      }

      const validation = fieldConfig.validation;

      // Convert validation ke format yang konsisten
      if (validation === "2" || validation === 2) {
        validationConfig[fieldName] = 2; // Required
      } else if (validation === "1" || validation === 1) {
        validationConfig[fieldName] = 1; // Optional with validation
      } else {
        validationConfig[fieldName] = 0; // No validation
      }
    }

    return validationConfig;
  }

  /**
   * Get validation info for specific field
   * @param {string} fieldName - Nama field
   * @returns {Object} Informasi validasi field
   */
  getFieldValidation(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    const validation = fieldConfig.validation;

    return {
      fieldName,
      level:
        validation === "2" || validation === 2
          ? 2
          : validation === "1" || validation === 1
          ? 1
          : 0,
      required: validation === "2" || validation === 2,
      label: fieldConfig.label || fieldConfig.placeholder || fieldName,
      type: fieldConfig.type || "text",
    };
  }

  /**
   * Get all validation info with detailed information
   * @returns {Object} Detailed validation information
   */
  getValidationInfo() {
    const validationInfo = {};

    if (!this.formData.form) {
      return validationInfo;
    }

    // Loop through ordered fields
    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      // Skip field 'id' jika mode adalah 'insert'
      if (fieldName === "id" && this.options.mode === "insert") {
        continue;
      }

      validationInfo[fieldName] = this.getFieldValidation(fieldName);
    }

    return validationInfo;
  }

  html() {
    const formHTML = this.formElement
      ? this.formElement.outerHTML
      : this.buildFormHTML();

    // Jika floating labels enabled, tambahkan inline JavaScript
    const style = this.getFormStyle();
    if (style.floating) {
      setTimeout(() => {
        this.generateFloatingLabelsScript();
      }, 0);

      return formHTML;
    }

    return formHTML;
  }

  htmlString() {
    return this.buildFormHTML();
  }

  // Generate inline JavaScript untuk floating labels (auto-execute)
  generateFloatingLabelsScript() {
    const formId = this.formId;

    // Wait for DOM ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initFloatingLabels);
    } else {
      initFloatingLabels();
    }

    function initFloatingLabels() {
      // Find the form
      const form =
        document.getElementById(formId) ||
        document.querySelector("form.nexa-floating-form");
      if (!form) {
        return;
      }

      // Find all inputs that support floating labels
      const inputs = form.querySelectorAll(
        'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea'
      );

      // Setup floating for each input
      inputs.forEach(function (input, index) {
        setupFloatingForInput(input);
      });
    }

    function setupFloatingForInput(input) {
      // Smart label finder
      function findLabel(input) {
        // Method 1: Find by 'for' attribute
        let label =
          document.querySelector('label[for="' + input.name + '"]') ||
          document.querySelector('label[for="' + input.id + '"]');
        if (label) return label;

        // Method 2: Find in floating container
        const container = input.closest(".form-nexa-floating");
        if (container) {
          label = container.querySelector("label");
          if (label) return label;
        }

        // Method 3: Sibling search
        label = input.nextElementSibling;
        if (label && label.tagName === "I") {
          label = label.nextElementSibling; // Skip icon
        }
        if (label && label.tagName === "LABEL") return label;

        // Method 4: Parent search
        const parent = input.parentElement;
        if (parent) {
          label = parent.querySelector("label");
          if (label) return label;
        }

        return null;
      }

      // Floating handler function
      function handleFloating() {
        const label = findLabel(input);
        if (label && label.tagName === "LABEL") {
          if (input.value && input.value.trim() !== "") {
            label.classList.add("active");
          } else {
            label.classList.remove("active");
          }
        }
      }

      // Focus handler - always activate on focus
      function handleFocus() {
        const label = findLabel(input);
        if (label && label.tagName === "LABEL") {
          label.classList.add("active");
        }
      }

      // Optimized: Only essential events
      input.addEventListener("input", handleFloating);
      input.addEventListener("focus", handleFocus);
      input.addEventListener("blur", handleFloating);

      // Initial check
      handleFloating();
    }

    // Add single-select behavior for checkboxes and switches
    function initSingleSelectBehavior() {
      // Handle single-select checkboxes
      const singleSelectCheckboxes = document.querySelectorAll(
        ".single-select-checkbox"
      );

      singleSelectCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener("change", function (e) {
          if (e.target.checked) {
            // Uncheck all other checkboxes with the same name
            const sameName = document.querySelectorAll(
              'input[name="' + e.target.name + '"].single-select-checkbox'
            );

            sameName.forEach(function (other) {
              if (other !== e.target) {
                other.checked = false;
              }
            });
          }
        });
      });

      // Handle single-select switches
      const singleSelectSwitches = document.querySelectorAll(
        ".single-select-switch"
      );

      singleSelectSwitches.forEach(function (switchEl) {
        switchEl.addEventListener("change", function (e) {
          if (e.target.checked) {
            // Uncheck all other switches with the same name
            const sameName = document.querySelectorAll(
              'input[name="' + e.target.name + '"].single-select-switch'
            );

            sameName.forEach(function (other) {
              if (other !== e.target) {
                other.checked = false;
              }
            });
          }
        });
      });
    }

    // Initialize Select2 for script mode
    function initSelect2() {
      const form =
        document.getElementById(formId) ||
        document.querySelector("form.nexa-floating-form");
      if (!form) return;

      const selectElements = form.querySelectorAll(
        "select.nexa-floating-select"
      );

      selectElements.forEach((selectElement) => {
        if (NXUI && NXUI.initSelect2) {
          const select2Options = {
            placeholder: "Pilih opsi...",
            searchInputPlaceholder: "Cari opsi...",
            allowClear: true,
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

          NXUI.initSelect2(`#${selectElement.id}`, select2Options);
        }
      });
    }

    // Initialize everything
    initFloatingLabels();
    initSingleSelectBehavior();
    initSelect2();
  }

  // Method khusus untuk initialize floating labels setelah HTML di-insert ke DOM
  initializeFloatingLabels(containerSelector) {
    // Find the container
    const container =
      typeof containerSelector === "string"
        ? document.querySelector(containerSelector)
        : containerSelector;

    if (!container) {
      return;
    }

    // Find the form inside container
    this.formElement =
      container.querySelector(`#${this.formId}`) ||
      container.querySelector("form") ||
      container; // If container is the form itself

    if (!this.formElement) {
      return;
    }

    // Attach event listeners for floating labels
    this.attachFloatingLabelEvents();

    // Attach other events
    this.attachEventListeners();

    // Attach single select behavior for HTML mode
    this.attachSingleSelectBehavior();

    // Initialize Select2 for HTML mode
    this.initializeSelect2();

    return this;
  }

  // Dedicated method for floating label events only
  attachFloatingLabelEvents() {
    if (!this.formElement) return;

    // Handle floating labels for all inputs
    const inputs = this.formElement.querySelectorAll(
      'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea'
    );

    inputs.forEach((input, index) => {
      this.handleFloatingLabel(input);
    });
  }

  // Quick method untuk manual floating setup (emergency)
  setupFloatingLabelsManual(containerSelector) {
    const container =
      typeof containerSelector === "string"
        ? document.querySelector(containerSelector)
        : containerSelector;

    if (!container) {
      return;
    }

    // Find all inputs in container
    const inputs = container.querySelectorAll(
      'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea'
    );

    inputs.forEach((input, index) => {
      // Universal floating label handler
      const handleFloating = () => {
        // Find label for this input
        let label = this.findLabelForInput(input);

        if (label && label.tagName === "LABEL") {
          if (input.value && input.value.trim() !== "") {
            label.classList.add("active");
          } else {
            label.classList.remove("active");
          }
        } else {
        }
      };

      // Optimized: Only essential events
      input.addEventListener("input", handleFloating);
      input.addEventListener("focus", handleFloating);
      input.addEventListener("blur", handleFloating);

      // Initial check
      handleFloating();
    });

    // Initialize Select2 for manual setup
    this.initializeSelect2();
  }

  // Simplified label finder for manual mode
  findLabelForInput(input) {
    // Method 1: Find by 'for' attribute (most reliable)
    const container = input.closest("form") || document;
    let label =
      container.querySelector(`label[for="${input.name}"]`) ||
      container.querySelector(`label[for="${input.id}"]`);

    if (label) return label;

    // Method 2: Find in same floating container
    const floatingContainer = input.closest(".form-nexa-floating");
    if (floatingContainer) {
      label = floatingContainer.querySelector("label");
      if (label) return label;
    }

    // Method 3: Standard sibling search
    label = input.nextElementSibling;
    if (label && label.tagName === "I") {
      label = label.nextElementSibling; // Skip icon
    }
    if (label && label.tagName === "LABEL") return label;

    // Method 4: Search in parent
    const parent = input.parentElement;
    if (parent) {
      label = parent.querySelector("label");
      if (label) return label;
    }

    return null;
  }

  // Reset all floating labels
  resetFloatingLabels() {
    const allLabels = this.formElement.querySelectorAll(
      ".form-nexa-floating label"
    );
    allLabels.forEach((label) => {
      label.classList.remove("active");
    });
  }

  // Force activate all floating labels (for testing)
  forceActivateAllLabels() {
    const allLabels = this.formElement.querySelectorAll(
      ".form-nexa-floating label"
    );
    allLabels.forEach((label, index) => {
      label.classList.add("active");
    });

    // Test if CSS is applied
    setTimeout(() => {
      allLabels.forEach((label, index) => {
        const styles = window.getComputedStyle(label);
      });
    }, 200);
  }

  // Global debug functions for single select

  /**
   * Force initialize Select2 - useful for debugging or manual initialization
   */
  forceInitializeSelect2() {
    this.initializeSelect2();
  }

  /**
   * Check if Select2 is available and ready
   */
  isSelect2Ready() {
    return !!(window.NXUI && window.NXUI.initSelect2);
  }

  /**
   * Wait for Select2 to be available and then initialize
   */
  waitForSelect2AndInitialize(maxAttempts = 10, delay = 500) {
    let attempts = 0;

    const checkAndInitialize = () => {
      attempts++;

      if (this.isSelect2Ready()) {
        this.initializeSelect2();
        return;
      }

      if (attempts < maxAttempts) {
        setTimeout(checkAndInitialize, delay);
      }
    };

    checkAndInitialize();
  }

  /**
   * Initialize flag dropdowns with proper event handling
   */
  initializeFlagDropdowns(container) {
    if (!container) return;

    try {
      // Find all flag select elements
      const flagSelects = container.querySelectorAll('.flag-kabupaten-select, .flag-kecamatan-select, .flag-desa-select');
      
      flagSelects.forEach(select => {
        // Ensure proper event delegation
        if (!select.hasAttribute('data-flag-initialized')) {
          // Add event listeners using event delegation
          select.addEventListener('change', function(e) {
            // Prevent event bubbling that might cause conflicts
            e.stopPropagation();
            
            // Add small delay to prevent rapid firing
            setTimeout(() => {
              if (this.classList.contains('flag-kabupaten-select')) {
                if (typeof window.filterKecamatan === 'function') {
                  window.filterKecamatan(this.value);
                }
              } else if (this.classList.contains('flag-kecamatan-select')) {
                if (typeof window.filterKecamatanDesa === 'function') {
                  window.filterKecamatanDesa(this.value);
                }
              }
            }, 50);
          });
          
          // Mark as initialized
          select.setAttribute('data-flag-initialized', 'true');
          
          // Add CSS class to prevent Select2 initialization
          select.classList.add('no-select2');
        }
      });

      // Ensure floating labels work for flag dropdowns
      const flagInputs = container.querySelectorAll('select');
      flagInputs.forEach(input => {
        this.handleFloatingLabel(input);
      });

    } catch (error) {
    }
  }
}

if (typeof window !== "undefined") {
  setTimeout(() => {
    ensureFormStylesheet().catch(() => {});
  }, 0);
}
