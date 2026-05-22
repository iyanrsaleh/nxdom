// File upload label update utility (global, only define if not present)
if (typeof globalThis.fileUploadUpdate !== "function") {
  globalThis.fileUploadUpdate = function fileUploadUpdate(input, labelId, dropId) {
    const labelEl = document.getElementById(labelId);
    const dropEl = document.getElementById(dropId);
    if (!input || !labelEl || !dropEl) return;
    const count = input.files ? input.files.length : 0;
    if (count <= 0) {
      labelEl.textContent = labelEl.getAttribute("data-hint") || "";
      dropEl.classList.remove("has-file");
      return;
    }
    labelEl.textContent = count === 1 ? input.files[0].name : `${count} file dipilih`;
    dropEl.classList.add("has-file");
  };
}
// Password toggle utility (global, only define if not present)
if (typeof globalThis.togglePasswordVisibility !== "function") {
  globalThis.togglePasswordVisibility = function togglePasswordVisibility(buttonEl) {
    const targetId = buttonEl?.dataset?.target;
    const inputEl = targetId ? document.getElementById(targetId) : null;
    if (!inputEl) return;
    const show = inputEl.type === "password";
    inputEl.type = show ? "text" : "password";
    buttonEl.dataset.passwordVisible = show ? "true" : "false";
    buttonEl.setAttribute("aria-label", show ? "Sembunyikan password" : "Tampilkan password");
    // Toggle icon visibility if present
    const eyeOn = buttonEl.querySelector('.pwd-eye-on');
    const eyeOff = buttonEl.querySelector('.pwd-eye-off');
    if (eyeOn && eyeOff) {
      if (show) {
        eyeOn.style.display = 'none';
        eyeOff.style.display = '';
      } else {
        eyeOn.style.display = '';
        eyeOff.style.display = 'none';
      }
    }
  };
}
/**
 * NexaFloating.js — Main entry point
 *
 * Responsibility: bootstrap the NexaFloating class with core form-building
 * logic (constructor, field routing, form structure helpers).
 * All heavy feature groups are split into dedicated mixin files and applied
 * at the bottom via Object.assign(NexaFloating.prototype, …).
 *
 * Module map:
 *   _helpers.js          — generateUniqueName (legacy fileToBinaryArray untuk wizard tertentu)
 *   _textFields.js       — text/email/password/number/currency/tel/url/hidden/slug/keyup
 *   _dateFields.js       — date/datetime-local/time/month/week
 *   _mediaFields.js      — color/range/file (+ fileUpload DOM logic)
 *   _choiceFields.js     — select/checkbox/radio/switch
 *   _specialFields.js    — textarea, tags, maps, flag, search
 *   _specialPopulate.js  — populate (+ modal/content lookup)
 *   _events.js           — form event attachment, range, checkbox/single-select
 *   _floatingLabels.js   — floating label handling and script generation
 *   _select2.js          — Select2 lifecycle management
 *   _formData.js         — data collection, validation, submit
 *   _publicApi.js        — render, destroy, reset, setData, setMode, validasi, html…
 */

import { generateUniqueName } from './_helpers.js';
import { getIconByTypeUniversal } from '../icons/NexaIcons.js';

import { TextFieldsMixin }     from './_textFields.js';
import { DateFieldsMixin }     from './_dateFields.js';
import { MediaFieldsMixin }    from './_mediaFields.js';
import { ChoiceFieldsMixin }   from './_choiceFields.js';
import { SpecialFieldsMixin }    from './_specialFields.js';
import { SpecialPopulateMixin } from './_specialPopulate.js';
import { generateTagsInput } from './_tags.js';
import { generateInstansiInput } from './_Instansi.js';
import { EventsMixin }         from './_events.js';
import { FloatingLabelsMixin } from './_floatingLabels.js';
import { Select2Mixin }        from './_select2.js';
import { FormDataMixin }       from './_formData.js';
import { PublicApiMixin }      from './_publicApi.js';

// Re-export helper so consumers can use it without knowing the internal path
export { generateUniqueName } from './_helpers.js';
import { generateFileInput } from './_fileFields.js';

// ─────────────────────────────────────────────────────────────────────────────

export class NexaFloating {
  constructor(formData, options = {}) {
    this.formData = formData || {};
    this.form     = formData.form     || {};
    this.settings = formData.settings || {};

    if (!this.formData.form || typeof this.formData.form !== 'object') {
      throw new Error(
        'NexaFloating: Invalid form data structure. Expected "form" property with field definitions.',
      );
    }
 
    this.options = {
      footer: true,
      mode:   options.mode  ?? 'insert',
      value:  options.value ?? {},
      ...options,
    };
  console.log('this.formData:', this.formData);
    this.formId    = this.formData.id        || this.formData.modalid || 'nexaForm';
    this.className = this.formData.className || 'Form';
    this.tableName = this.formData.tableName || 'Data';
    this.label     = this.formData.label     || 'Form';
    this.value     = options.value ?? this.formData.value ?? {};

    this.init();
  }

  // ─── Life-cycle ─────────────────────────────────────────────────────────

  init() {
    this.generateForm();
  }

  generateForm() {
    const formHTML   = this.buildFormHTML();
    this.formElement = this.createFormElement(formHTML);
    this.attachEventListeners();
  }

  // ─── HTML builder ────────────────────────────────────────────────────────

  buildFormHTML() {
    const style  = this.getFormStyle();
    const isGrid = this.hasGridColumns();
    const formClass = style.floating ? 'form-nexa nexa-floating-form' : 'form-nexa';
    const hasFileInputs = Object.values(this.formData.form || {}).some((f) => f.type === 'file');
    const enctype       = hasFileInputs ? ' enctype="multipart/form-data"' : '';

    let formHTML = `<form id="${this.formId}" class="${formClass}"${enctype}>`;
    if (isGrid) formHTML += `<div class="nx-row">`;

    const orderedFields = this.getOrderedFields();
    for (const [fieldName, fieldConfig] of orderedFields) {
      if (fieldName === 'id' && this.options.mode === 'insert') continue;
      formHTML += this.generateField(fieldName, fieldConfig);
    }

    if (isGrid) formHTML += `</div>`;
    formHTML += `</form>`;
    return formHTML;
  }

  // ─── Style config ────────────────────────────────────────────────────────

  getFormStyle() {
    const defaultStyle = {
      floating:   true,
      size:       'form-control',
      layout:     'vertical',
      button:     'nx-btn-primary',
    };

    let style = { ...defaultStyle };

    if (this.formData.assets?.style) style = { ...style, ...this.formData.assets.style };

    const s = this.formData.settings;
    if (s) {
      if (s.floating    !== undefined) style.floating   = s.floating;
      if (s.validation  !== undefined) style.validation = s.validation;
      if (s.buttontype)                style.button     = s.buttontype;
      if (s.layout)                    style.layout     = s.layout;
      if (s.size)                      style.size       = s.size;
    }

    return style;
  }

  // ─── Grid detection ──────────────────────────────────────────────────────

  hasGridColumns() {
    if (!this.formData.form) return false;
    return Object.values(this.formData.form).some(
      (f) => f.columnWidth && (f.columnWidth.startsWith('col-') || f.columnWidth.startsWith('nx-col-')),
    );
  }

  // ─── Field ordering ──────────────────────────────────────────────────────

  getOrderedFields() {
    const formFields    = this.formData.form      || {};
    const variables     = this.formData.variables || [];
    const orderedFields = [];

    variables.forEach((fieldName) => {
      if (formFields[fieldName] && formFields[fieldName].condition === true) {
        orderedFields.push([fieldName, formFields[fieldName]]);
      }
    });

    Object.entries(formFields).forEach(([fieldName, fieldConfig]) => {
      if (!variables.includes(fieldName) && fieldConfig.condition === true) {
        orderedFields.push([fieldName, fieldConfig]);
      }
    });

    return orderedFields;
  }

  // ─── Field router ────────────────────────────────────────────────────────

  generateField(fieldName, fieldConfig) {
    if (fieldConfig.condition !== true) return '';

    if (fieldConfig.type === 'hidden') {
      return this.generateHiddenInput(
        fieldName,
        fieldConfig.placeholder || fieldConfig.label || fieldName,
        '',
        false,
      );
    }

    if (fieldConfig.type === 'slug') {
      return this.generateTextSlug(
        fieldName,
        fieldConfig.placeholder || fieldConfig.label || fieldName,
        '',
        false,
        this.value[fieldName] ?? '',
      );
    }

    const style        = this.getFormStyle();
    let placeholder = fieldConfig.placeholder || fieldConfig.label || fieldName;
    // Capitalize first letter and replace underscores with spaces
    if (typeof placeholder === 'string') {
      placeholder = placeholder.replace(/_/g, ' ');
      placeholder = placeholder.charAt(0).toUpperCase() + placeholder.slice(1);
    }
    const rawGridClass = fieldConfig.columnWidth;
    const gridClass    = rawGridClass ? rawGridClass.replace(/\bnx-col-/g, 'col-') : rawGridClass;
    const isFloating   = style.floating;
    const size         = style.size || 'form-control';
    const fieldType    = fieldConfig.type || 'text';
    const isHidden     = fieldConfig.hiddenForm === true;
    // Universal icon resolver
    const iconTypeRaw = String(fieldConfig?.iconType || 'material').toLowerCase().trim();
    const iconType = iconTypeRaw.replace(/^#?sym:/, '');
    const customIcon = typeof fieldConfig?.icons === 'string' ? fieldConfig.icons.trim() : '';
    const useDefaultIcon = customIcon.toLowerCase() === 'edit_note';
    const resolvedIcon = getIconByTypeUniversal(fieldType, iconType);
    const icon = isHidden ? null : ((!useDefaultIcon && customIcon) ? customIcon : resolvedIcon);
    const formSettings = this.formData.settings || {};

    const setValue = this.value[fieldName] ?? '';

    let fieldHTML = `<div class="${gridClass ? 'form-group ' + gridClass : 'form-group'}">`;

    // ── Standalone types (no icon/label wrapper) ──────────────────────────
    const standaloneTypes = ['checkbox', 'radio', 'switch', 'file', 'textarea'];
    if (standaloneTypes.includes(fieldType)) {
      let inputHTML = '';
      let labelIconHTML = '';
      if (icon) {
        if (iconType === 'octicon') {
          labelIconHTML = `<span class="${icon} form-label-icon" aria-hidden="true"></span> `;
        } else if (iconType === 'awesome') {
          labelIconHTML = `<i class="${icon} form-label-icon" aria-hidden="true"></i> `;
        } else {
          labelIconHTML = `<span class="material-symbols-outlined form-label-icon" aria-hidden="true">${icon}</span> `;
        }
      }
      switch (fieldType) {
        case 'checkbox': inputHTML = this.generateCheckboxInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue); break;
        case 'radio':    inputHTML = this.generateRadioInput(fieldName,    placeholder, size, isFloating, fieldConfig, setValue); break;
        case 'switch':   inputHTML = this.generateSwitchInput(fieldName,   placeholder, size, isFloating, fieldConfig, setValue); break;
        case 'file': {
          // Ensure file input has id=fieldName
          const fileInputId = fieldName;
          inputHTML = generateFileInput(fileInputId, iconType, this.formId, size, isFloating, fieldConfig, setValue);
          // Move error anchor inside label for inline error (like NexaWild.js)
          fieldHTML += `<div class="form-group-header"><label for="${fileInputId}">${labelIconHTML}${placeholder}</div>`;
          fieldHTML += inputHTML + `</div>`;
          return fieldHTML;
        }
        case 'textarea': inputHTML = this.generateTextareaInput(fieldName, placeholder, size, isFloating, setValue, fieldConfig); break;
      }
      // --- NEW: Use <dl><dt><label>... for checkbox/radio like form.js ---
      if (["checkbox", "radio"].includes(fieldType)) {
        fieldHTML += `<dl>`;
        fieldHTML += `<dt class="form-group-header"><label>${labelIconHTML}${placeholder} <small class="error" id="errors_${fieldName}" style="display:none;"></small></label></dt>`;
        fieldHTML += `<dd class="form-group-body">${inputHTML}</dd>`;
        fieldHTML += `</dl></div>`;
        return fieldHTML;
      }
      // --- END NEW ---
      // Switch: keep as before (with label above)
      if (fieldType === 'switch') {
        fieldHTML += `<div class="form-group-header"><label for="${fieldName}">${labelIconHTML}${placeholder} <small class="error" id="errors_${fieldName}" style="display:none;"></small></label></div>`;
      }
      // Tambahkan label dan error anchor untuk textarea
      if (fieldType === 'textarea') {
        fieldHTML += `<div class="form-group-header"><label for="${fieldName}">${labelIconHTML}${placeholder}</label></div>`;
      }
      fieldHTML += inputHTML + `</div>`;
      return fieldHTML;
    }

    // Special: tags field (Select2 multi tags) with label+icon+error+body
    if (fieldType === 'tags') {
      const tagIcon = (!useDefaultIcon && customIcon) || getIconByTypeUniversal('select2', iconType);
      let labelIconHTML = '';
      if (tagIcon) {
        if (iconType === 'octicon') {
          labelIconHTML = `<span class="${tagIcon} form-label-icon" aria-hidden="true"></span> `;
        } else if (iconType === 'awesome') {
          labelIconHTML = `<i class="${tagIcon} form-label-icon" aria-hidden="true"></i> `;
        } else {
          labelIconHTML = `<span class="material-symbols-outlined form-label-icon" aria-hidden="true">${tagIcon}</span> `;
        }
      }
      fieldHTML += `<div class="form-group-header"><label for="tags_${fieldName}">${labelIconHTML}${placeholder}</label><span class="error" id="errors_${fieldName}" style="display:none;"></span></div>`;
      fieldHTML += `<div class="form-group-body">`;
      fieldHTML += generateTagsInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue);
      fieldHTML += `</div></div>`;
      return fieldHTML;
    }

    // Special: instansi field (Select2 single select) with label+icon+error+body
      if (fieldType === 'instansi') {
        const instansiIcon = (!useDefaultIcon && customIcon) || getIconByTypeUniversal('instansi', iconType);
      let labelIconHTML = '';
      if (instansiIcon) {
        if (iconType === 'octicon') {
          labelIconHTML = `<span class="${instansiIcon} form-label-icon" aria-hidden="true"></span> `;
        } else if (iconType === 'awesome') {
          labelIconHTML = `<i class="${instansiIcon} form-label-icon" aria-hidden="true"></i> `;
        } else {
          labelIconHTML = `<span class="material-symbols-outlined form-label-icon" aria-hidden="true">${instansiIcon}</span> `;
        }
      }
      fieldHTML += `<div class="form-group-header"><label for="instansi_${fieldName}">${labelIconHTML}${placeholder}</label><span class="error" id="errors_${fieldName}" style="display:none;"></span></div>`;
      fieldHTML += `<div class="form-group-body">`;
      fieldHTML += generateInstansiInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue);
      fieldHTML += `</div></div>`;
      return fieldHTML;
    }

    // ── Hidden-form types (no label/icon) ─────────────────────────────────
    if (isHidden) {
      let inputHTML = '';
      switch (fieldType) {
        case 'text':           inputHTML = this.generateTextInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'instansi':       inputHTML = this.generateInstansiInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'email':          inputHTML = this.generateEmailInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'password':       inputHTML = this.generatePasswordInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'number':         inputHTML = this.generateNumberInput(fieldName,    placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'currency':       inputHTML = this.generateCurrencyInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'tel':            inputHTML = this.generateTelInput(fieldName,       placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'url':            inputHTML = this.generateUrlInput(fieldName,       placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'date':           inputHTML = this.generateDateInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'datetime-local': inputHTML = this.generateDateTimeInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'time':           inputHTML = this.generateTimeInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'month':          inputHTML = this.generateMonthInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'week':           inputHTML = this.generateWeekInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'color':          inputHTML = this.generateColorInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'range':          inputHTML = this.generateRangeInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
        case 'populate':       inputHTML = this.generatePopulateInput(fieldName,  placeholder, size, isFloating, fieldConfig, formSettings, setValue); break;
        default:               inputHTML = this.generateTextInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
      }
      return `<div class="${gridClass ? 'form-group ' + gridClass : 'form-group'}">${inputHTML}</div>`;
    }

    // ── Standard types with label + optional icon ─────────────────────────
    let inputHTML = '';
    switch (fieldType) {
      case 'text':           inputHTML = this.generateTextInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'instansi':       inputHTML = this.generateInstansiInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'email':          inputHTML = this.generateEmailInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'password':       inputHTML = this.generatePasswordInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'number':         inputHTML = this.generateNumberInput(fieldName,    placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'currency':       inputHTML = this.generateCurrencyInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'tel':            inputHTML = this.generateTelInput(fieldName,       placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'url':            inputHTML = this.generateUrlInput(fieldName,       placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'date':           inputHTML = this.generateDateInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'datetime-local': inputHTML = this.generateDateTimeInput(fieldName,  placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'time':           inputHTML = this.generateTimeInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'month':          inputHTML = this.generateMonthInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'week':           inputHTML = this.generateWeekInput(fieldName,      placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'color':          inputHTML = this.generateColorInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'range':          inputHTML = this.generateRangeInput(fieldName,     placeholder, size, isFloating, setValue, fieldConfig); break;
      case 'hidden':         inputHTML = this.generateHiddenInput(fieldName,    placeholder, size, isFloating); break;
      case 'select':         inputHTML = this.generateSelectInput(fieldName,    placeholder, size, isFloating, fieldConfig, setValue); break;
      case 'search':         inputHTML = this.generateSearchInput(fieldName,    placeholder, size, isFloating, fieldConfig, formSettings, setValue, this.formData); break;
      case 'maps':           inputHTML = this.generateMapsInput(fieldName,      placeholder, size, isFloating, fieldConfig, formSettings, setValue); break;
      case 'populate':       inputHTML = this.generatePopulateInput(fieldName,  placeholder, size, isFloating, fieldConfig, formSettings, setValue); break;
      case 'flag': {
        const random = generateUniqueName(fieldName);
        this.generateFlagInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue).then(
          (flagHTML) => {
            const fieldContainer = document.querySelector(`[data-field="${random}"]`);
            if (fieldContainer) {
              fieldContainer.innerHTML = flagHTML;
              setTimeout(() => { this.initializeFlagDropdowns(fieldContainer); }, 200);
            }
          },
        );
        inputHTML = `<div data-field="${random}">Loading flag input...</div>`;
        break;
      }
      default:
        inputHTML = this.generateTextInput(fieldName, placeholder, size, isFloating, setValue, fieldConfig);
    }

    // Label + error anchor with icon (selalu render label untuk semua tipe field kecuali file)
    let labelIconHTML = '';
    if (icon) {
      if (iconType === 'octicon') {
        labelIconHTML = `<span class="${icon} form-label-icon" aria-hidden="true"></span> `;
      } else if (iconType === 'awesome') {
        labelIconHTML = `<i class="${icon} form-label-icon" aria-hidden="true"></i> `;
      } else {
        labelIconHTML = `<span class="material-symbols-outlined form-label-icon" aria-hidden="true">${icon}</span> `;
      }
    }
    if (fieldType !== 'file') {
      fieldHTML += `<div class="form-group-header"><label for="${fieldName}">${labelIconHTML}${placeholder}</label><span class="error" id="errors_${fieldName}" style="display:none;"></span></div>`;
    }

    fieldHTML += `<div class="form-group-body">`;

    // Password input with toggle button (like wild/form.js)
    const noIconTypes = ['select', 'switch', 'radio', 'checkbox', 'color', 'range', 'flag'];
    if (fieldType === 'password') {
      const inputId = `${fieldName}_${this.formId}`;
      const iconShow = iconType === 'octicon'
        ? `<span class="${getIconByTypeUniversal('eye', iconType)} pwd-eye-on" aria-hidden="true"></span>`
        : iconType === 'awesome'
        ? `<i class="${getIconByTypeUniversal('eye', iconType)} pwd-eye-on" aria-hidden="true"></i>`
        : `<span class="material-symbols-outlined pwd-eye-on" aria-hidden="true">${getIconByTypeUniversal('eye', iconType)}</span>`;
      const iconHide = iconType === 'octicon'
        ? `<span class="${getIconByTypeUniversal('eye_off', iconType)} pwd-eye-off" aria-hidden="true" style="display:none"></span>`
        : iconType === 'awesome'
        ? `<i class="${getIconByTypeUniversal('eye_off', iconType)} pwd-eye-off" aria-hidden="true" style="display:none"></i>`
        : `<span class="material-symbols-outlined pwd-eye-off" aria-hidden="true" style="display:none">${getIconByTypeUniversal('eye_off', iconType)}</span>`;
      fieldHTML += `<div class="input-group">`;
      fieldHTML += `<input id="${inputId}" name="${fieldName}" type="password" class="${size}" placeholder="${placeholder}" value="${setValue}" />`;
      fieldHTML += `<div class="input-group-button"><button class="btn" type="button" data-target="${inputId}" data-password-visible="false" onclick="togglePasswordVisibility(this)" aria-label="Tampilkan password">${iconShow}${iconHide}</button></div>`;
      fieldHTML += `</div>`;
    } else if (icon && !noIconTypes.includes(fieldType)) {
      fieldHTML += `<div class="input-group">`;
      fieldHTML += inputHTML;
      fieldHTML += `<div class="input-group-button">`;
      // Render icon according to iconType
      if (iconType === 'octicon') {
        fieldHTML += `<button class="btn" type="button" aria-label="${placeholder}"><span class="${icon}"></span></button>`;
      } else if (iconType === 'awesome') {
        fieldHTML += `<button class="btn" type="button" aria-label="${placeholder}"><i class="${icon}"></i></button>`;
      } else {
        fieldHTML += `<button class="btn" type="button" aria-label="${placeholder}"><span class="material-symbols-outlined">${icon}</span></button>`;
      }
      fieldHTML += `</div>`;
      fieldHTML += `</div>`;
    } else {
      fieldHTML += inputHTML;
    }

    fieldHTML += `</div>`; // form-group-body
    fieldHTML += `</div>`; // form-group
    return fieldHTML;
  }

  // ─── Field accessor helpers ──────────────────────────────────────────────

  getFieldConfig(fieldName) {
    if (!this.formData.form || !this.formData.form[fieldName]) return {};
    return this.formData.form[fieldName];
  }

  getPlaceholder(fieldName) {
    const fieldConfig = this.getFieldConfig(fieldName);
    return fieldConfig.placeholder || fieldConfig.label || fieldName;
  }

  getIcon(fieldName) {
    return this.getFieldConfig(fieldName).icons || null;
  }

  getOptions(fieldName) {
    return this.getFieldConfig(fieldName).options || null;
  }

  getGridClass(fieldName) {
    return this.getFieldConfig(fieldName).columnWidth || null;
  }

  createFormElement(htmlString) {
    const tempDiv     = document.createElement('div');
    tempDiv.innerHTML = htmlString;
    return tempDiv.firstElementChild;
  }

  generateSubmitButton(data) {
    const style = this.getFormStyle();
    if (!this.options.footer) return '';
    const saveLabel   = this.formData.saveLabel   || this.settings.saveLabel   || 'Simpan';
    const cancelLabel = this.formData.cancelLabel || this.settings.cancelLabel || 'Batal';
    return `
      <div class="col-12 form-actions">
        <button type="button" class="${this.settings.buttontype || 'btn btn-primary'}" id="sendContent_${data.id}" data-original-text="${saveLabel}">${saveLabel}</button>
        <button type="button" class="btn btn-secondary">${cancelLabel}</button>
      </div>
    `;
  }
}

// ─── Apply all feature mixins to prototype ───────────────────────────────────

Object.assign(
  NexaFloating.prototype,
  TextFieldsMixin,
  DateFieldsMixin,
  MediaFieldsMixin,
  ChoiceFieldsMixin,
  SpecialFieldsMixin,
  SpecialPopulateMixin,
  EventsMixin,
  FloatingLabelsMixin,
  Select2Mixin,
  FormDataMixin,
  PublicApiMixin,
);
