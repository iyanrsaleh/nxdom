// _formData.js — Form data collection, validation, and submission mixin for NexaFloating
// File: kirim sebagai File (selaras NexaWild / collectFormData), bukan `{ content: number[] }`.

export const FormDataMixin = {

  // ─── Submit ──────────────────────────────────────────────────────────────

  async handleSubmit() {
    const formData = await this.getFormData();
    const style    = this.getFormStyle();

    if (style.validation) {
      const validation = this.validateForm(formData);
      if (!validation.isValid) {
        this.displayErrors(validation.errors);
        return;
      }
    }

    this.clearErrors();

    document.dispatchEvent(
      new CustomEvent('nexaFormSubmit', {
        detail: {
          formData,
          formId:    this.formId,
          className: this.className,
          tableName: this.tableName,
          tableKey:  this.formData.tableKey,
          formMeta:  {
            id:      this.formData.id,
            version: this.formData.version,
            store:   this.formData.store,
            label:   this.formData.label,
          },
        },
      }),
    );
  },

  // ─── Data collection ─────────────────────────────────────────────────────

  async getFormData() {
    const formData = {};
    const inputs   = this.formElement.querySelectorAll('input, select, textarea');

    for (const input of inputs) {
      if (!input.name) continue;

      switch (input.type) {
        case 'checkbox':
          if (input.checked) {
            const value = input.getAttribute('data-label') || input.value;
            if (input.classList.contains('single-select-checkbox') || input.classList.contains('single-select-switch')) {
              formData[input.name] = value;
            } else if (input.name.endsWith('[]')) {
              const fieldName = input.name.slice(0, -2);
              if (!formData[fieldName]) formData[fieldName] = [];
              formData[fieldName].push(value);
            } else {
              formData[input.name] = input.checked;
            }
          }
          break;

        case 'radio':
          if (input.checked) {
            formData[input.name] = input.getAttribute('data-label') || input.value;
          }
          break;

        case 'file':
          if (input.files.length > 0) {
            if (input.multiple) {
              formData[input.name] = Array.from(input.files);
            } else {
              formData[input.name] = input.files[0];
            }
          } else {
            formData[input.name] = input.multiple ? [] : null;
          }
          break;

        case 'text':
          if (input.classList.contains('form-nexa-search-input')) {
            formData[input.name] = { value: input.getAttribute('data-value') || input.value, label: input.value };
          } else {
            formData[input.name] = input.value;
          }
          break;

        case 'number':
        case 'currency':
          formData[input.name] = input.value ? parseFloat(input.value) : null;
          break;

        case 'range':
          formData[input.name] = parseFloat(input.value);
          break;

        case 'color':
          formData[input.name] = input.value;
          break;

        case 'date':
        case 'datetime-local':
        case 'time':
        case 'month':
        case 'week':
          formData[input.name] = input.value ? new Date(input.value) : null;
          break;

        case 'hidden':
          formData[input.name] = input.value;
          break;

        default:
          if (input.tagName.toLowerCase() === 'select') {
            const selectedOption = input.options[input.selectedIndex];
            formData[input.name] = selectedOption ? selectedOption.text : input.value;
          } else {
            formData[input.name] = input.value;
          }
      }
    }

    return formData;
  },

  // ─── Validation ──────────────────────────────────────────────────────────

  validateForm(formData) {
    const errors = {};
    let isValid  = true;

    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      if (fieldName === 'id' && this.options.mode === 'insert') continue;

      const value       = formData[fieldName];
      const validation  = fieldConfig.validation;
      const placeholder = fieldConfig.placeholder || fieldConfig.label || fieldName;

      if (validation === '2' || validation === true || validation === 2) {
        const isFileFilled =
          (typeof File !== 'undefined' && value instanceof File) ||
          (typeof Blob !== 'undefined' && value instanceof Blob) ||
          (Array.isArray(value) &&
            value.some(
              (it) =>
                (typeof File !== 'undefined' && it instanceof File) ||
                (typeof Blob !== 'undefined' && it instanceof Blob),
            ));
        const missingRequired =
          !isFileFilled &&
          (!value ||
            (typeof value === 'object' &&
              value !== null &&
              !value.value &&
              value.value !== 0));

        if (missingRequired) {
          errors[fieldName] = `${placeholder} wajib diisi`;
          isValid = false;
        }
      }

      if (value) {
        switch (fieldConfig.type) {
          case 'email':
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
              errors[fieldName] = `${placeholder} harus berupa email yang valid`;
              isValid = false;
            }
            break;
          case 'number':
            if (isNaN(value)) {
              errors[fieldName] = `${placeholder} harus berupa angka`;
              isValid = false;
            }
            break;
          case 'url':
            try { new URL(value); } catch {
              errors[fieldName] = `${placeholder} harus berupa URL yang valid`;
              isValid = false;
            }
            break;
        }
      }
    }

    return { isValid, errors };
  },

  // ─── Error display ────────────────────────────────────────────────────────

  displayErrors(errors) {
    for (const [fieldName, errorMessage] of Object.entries(errors)) {
      const errorElement = document.getElementById(`errors_${fieldName}`);
      if (errorElement) {
        errorElement.textContent = errorMessage;
        errorElement.style.display = 'inline';
      }
      const input = document.getElementById(fieldName);
      if (input) {
        const formGroup = input.closest('.form-group');
        if (formGroup) formGroup.classList.add('errored');
      }
    }
  },

  clearErrors() {
    this.formElement.querySelectorAll('[id^="errors_"]').forEach((el) => {
      el.textContent     = '';
      el.style.display   = 'none';
    });
    this.formElement.querySelectorAll('.is-invalid').forEach((el) => {
      el.classList.remove('is-invalid');
    });
    this.formElement.querySelectorAll('.form-group.errored, .form-group.form-error').forEach((c) => {
      c.classList.remove('errored', 'form-error');
    });
  },

  // ─── Shorthand ───────────────────────────────────────────────────────────

  async getData() {
    return await this.getFormData();
  },
};
