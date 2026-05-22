// _choiceFields.js — Select / checkbox / radio / switch generators mixin for NexaFloating

export const ChoiceFieldsMixin = {

  // ─── Select ─────────────────────────────────────────────────────────────────

  generateSelectInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    const options  = fieldConfig.select.data || [];
    const disabled = fieldConfig?.disabled ? 'disabled' : '';
    const readonly = fieldConfig?.readonly ? 'readonly' : '';

    let selectHTML = `<select id="${fieldName}" name="${fieldName}" style="width:100%" ${disabled} ${readonly}>`;
    selectHTML += `<option value="">Pilihan</option>`;

    if (options && Array.isArray(options)) {
      options.forEach((option) => {
        const value    = option.value || option.id;
        const label    = option.text  || option.label || option.value;
        const selected = setValue && setValue == value ? 'selected' : '';
        selectHTML += `<option value="${value}" ${selected}>${label}</option>`;
      });
    }

    selectHTML += `</select>`;
    return selectHTML;
  },

  // ─── Checkbox ───────────────────────────────────────────────────────────────

  generateCheckboxInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    // Only generate options; group label and error anchor handled by NexaFloating.js
    let checkboxHTML = '';
    if (fieldConfig.select.data && Array.isArray(fieldConfig.select.data)) {
      fieldConfig.select.data.forEach((option, index) => {
        const checkboxId = `${fieldName}_${index}`;
        const checked    = setValue && setValue == option.value ? 'checked' : '';
        checkboxHTML += `<div class="form-checkbox"><label><input type="checkbox" id="${checkboxId}" name="${fieldName}" value="${option.value}" ${checked} /> ${option.label}</label></div>`;
      });
    }
    return checkboxHTML;
  },

  // ─── Radio ──────────────────────────────────────────────────────────────────

  generateRadioInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    // Only generate options; group label and error anchor handled by NexaFloating.js
    let radioHTML = '';
    if (fieldConfig.select.data) {
      fieldConfig.select.data.forEach((option, index) => {
        const radioId = `${fieldName}_${index}`;
        const checked = setValue && setValue == option.value ? 'checked' : '';
        radioHTML += `<div class="form-checkbox"><label><input type="radio" id="${radioId}" name="${fieldName}" value="${option.value}" ${checked} /> ${option.label}</label></div>`;
      });
    }
    return radioHTML;
  },

  // ─── Switch ─────────────────────────────────────────────────────────────────

  generateSwitchInput(fieldName, placeholder, size, isFloating, fieldConfig, setValue = '') {
    let switchHTML = '';
    switchHTML += `<div class="form-group mb-10px">`;
    switchHTML += `<div class="nx-switch-grid">`;

    if (fieldConfig.select.data && Array.isArray(fieldConfig.select.data)) {
      fieldConfig.select.data.forEach((option, index) => {
        const switchId  = `${fieldName}_${index}`;
        const isChecked = (setValue && setValue == option.value) || option.checked ? 'checked' : '';
        switchHTML += `<div class="nx-switch-item">`;
        switchHTML += `<input type="checkbox" id="${switchId}" name="${fieldName}" value="${option.value}" data-label="${option.value}" class="single-select-switch" ${isChecked} />`;
        switchHTML += `<label for="${switchId}">`;
        switchHTML += `<span class="nx-switch"></span>`;
        switchHTML += `<span class="nx-switch-text">${option.label}</span>`;
        switchHTML += `</label>`;
        switchHTML += `<small id="errors_${switchId}" class="error-message"></small>`;
        switchHTML += `</div>`;
      });
    }

    switchHTML += `</div>`; // nx-switch-grid
    switchHTML += `</div>`; // form-group
    return switchHTML;
  },
};
