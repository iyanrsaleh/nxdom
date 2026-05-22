// _publicApi.js — Public API methods mixin for NexaFloating
// Handles: render, destroy, reset, setData, setMode, getMode,
//          validasi, getFieldValidation, getValidationInfo, html, htmlString

export const PublicApiMixin = {

  // ─── Rendering ───────────────────────────────────────────────────────────

  render(container) {
    if (typeof container === 'string') container = document.querySelector(container);
    if (container && this.formElement) {
      container.appendChild(this.formElement);
    }
    return this.formElement;
  },

  destroy() {
    if (typeof this.detachFloatingUploadSuccessListener === 'function') {
      this.detachFloatingUploadSuccessListener();
    }
    this.destroySelect2();
    if (this.formElement && this.formElement.parentNode) {
      this.formElement.parentNode.removeChild(this.formElement);
    }
  },

  reset(modalId = null) {
    if (modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        const form = modal.querySelector('form') || modal.querySelector(`#${this.formId}`);
        if (form && typeof form.reset === 'function') {
          form.reset();
          modal.querySelectorAll('.form-nexa-floating label.active').forEach((l) => l.classList.remove('active'));
          return;
        }
      }
    }
    if (this.formElement) {
      this.formElement.reset();
      this.clearErrors();
      this.formElement.querySelectorAll('label.active').forEach((l) => l.classList.remove('active'));
    }
  },

  // ─── Data binding ────────────────────────────────────────────────────────

  setData(data) {
    if (!this.formElement) return;

    for (const [fieldName, value] of Object.entries(data)) {
      const input = this.formElement.querySelector(`[name="${fieldName}"]`);
      if (!input) continue;

      switch (input.type) {
        case 'checkbox': {
          const checkboxes = this.formElement.querySelectorAll(
            `[name="${fieldName}"], [name="${fieldName}[]"]`,
          );
          if (checkboxes.length > 1 && Array.isArray(value)) {
            checkboxes.forEach((cb) => { cb.checked = value.includes(cb.value); });
          } else if (checkboxes.length === 1) {
            checkboxes[0].checked = Boolean(value);
          } else {
            input.checked = Boolean(value);
          }
          break;
        }

        case 'radio': {
          this.formElement
            .querySelectorAll(`[name="${fieldName}"]`)
            .forEach((radio) => { radio.checked = radio.value === value; });
          break;
        }

        case 'file':
          // Cannot be set programmatically (browser security)
          break;

        case 'text':
          if (input.classList.contains('form-nexa-search-input') && typeof value === 'object') {
            input.value = value.label || '';
            input.setAttribute('data-value', value.value || '');
          } else {
            input.value = value || '';
          }
          this.triggerFloatingLabel(input);
          break;

        case 'number':
        case 'currency':
        case 'range':
          input.value = value !== null && value !== undefined ? value : '';
          this.triggerFloatingLabel(input);
          break;

        case 'color':
          input.value = value || '#000000';
          break;

        case 'date':
        case 'datetime-local':
        case 'time':
        case 'month':
        case 'week':
          if (value instanceof Date) {
            if      (input.type === 'date')           input.value = value.toISOString().split('T')[0];
            else if (input.type === 'datetime-local') input.value = value.toISOString().slice(0, 16);
            else if (input.type === 'time')           input.value = value.toTimeString().slice(0, 5);
            else if (input.type === 'month')          input.value = value.toISOString().slice(0, 7);
            else if (input.type === 'week') {
              const year = value.getFullYear();
              const week = Math.ceil(((value - new Date(year, 0, 1)) / 86400000 + 1) / 7);
              input.value = `${year}-W${week.toString().padStart(2, '0')}`;
            }
          } else if (typeof value === 'string') {
            input.value = value;
          }
          this.triggerFloatingLabel(input);
          break;

        default:
          input.value = value || '';
          this.triggerFloatingLabel(input);
      }
    }
  },

  // ─── Mode ────────────────────────────────────────────────────────────────

  setMode(mode) {
    this.options.mode = mode;
    if (this.formElement) {
      this.generateForm();
      const existingForm = document.getElementById(this.formId);
      if (existingForm && existingForm.parentNode) {
        existingForm.parentNode.replaceChild(this.formElement, existingForm);
        this.attachEventListeners();
        this.initializeSelect2();
      }
    }
  },

  getMode() {
    return this.options.mode;
  },

  // ─── Validation config ───────────────────────────────────────────────────

  validasi() {
    const validationConfig = {};
    if (!this.formData.form) return validationConfig;

    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      if (fieldName === 'id' && this.options.mode === 'insert') continue;
      const v = fieldConfig.validation;
      if (v === '2' || v === 2)       validationConfig[fieldName] = 2;
      else if (v === '1' || v === 1)  validationConfig[fieldName] = 1;
      else                            validationConfig[fieldName] = 0;
    }
    return validationConfig;
  },

  getFieldValidation(fieldName) {
    const fieldConfig  = this.getFieldConfig(fieldName);
    const validation   = fieldConfig.validation;
    return {
      fieldName,
      level:    (validation === '2' || validation === 2) ? 2 : (validation === '1' || validation === 1) ? 1 : 0,
      required: validation === '2' || validation === 2,
      label:    fieldConfig.label || fieldConfig.placeholder || fieldName,
      type:     fieldConfig.type  || 'text',
    };
  },

  getValidationInfo() {
    const validationInfo = {};
    if (!this.formData.form) return validationInfo;
    const orderedFields = this.getOrderedFields();
    for (const [fieldName] of orderedFields) {
      if (fieldName === 'id' && this.options.mode === 'insert') continue;
      validationInfo[fieldName] = this.getFieldValidation(fieldName);
    }
    return validationInfo;
  },

  // ─── HTML output ─────────────────────────────────────────────────────────

  html() {
    const formHTML = this.formElement ? this.formElement.outerHTML : this.buildFormHTML();
    const style    = this.getFormStyle();
    if (style.floating) {
      setTimeout(() => { this.generateFloatingLabelsScript(); }, 0);
    }
    return formHTML;
  },

  htmlString() {
    return this.buildFormHTML();
  },
};
