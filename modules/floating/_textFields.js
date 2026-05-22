// _textFields.js — Text-like input generators mixin for NexaFloating
// Handles: text, instansi, email, password, number, currency, tel, url,
//          hidden, slug

import { NexaSlug } from "../slug/NexaSlug.js";

export const TextFieldsMixin = {

  generateTextInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig) {
    const placeholderAttr = `placeholder="${placeholder}"`;
    const type     = fieldConfig?.hiddenForm ? 'hidden' : fieldConfig?.type;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateInstansiInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig) {
    console.log('fieldConfig:', fieldConfig);
    const placeholderAttr = `placeholder="${placeholder}"`;
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'text';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input value="${NEXA?.userData?.instansi || ''}" type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} disabled />`;
  },

  generateEmailInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'email';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generatePasswordInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'password';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateNumberInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'number';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    const options   = this.getOptions(fieldName);
    let attributes  = '';
    if (options) {
      if (options.min  !== undefined) attributes += ` min="${options.min}"`;
      if (options.max  !== undefined) attributes += ` max="${options.max}"`;
      if (options.step !== undefined) attributes += ` step="${options.step}"`;
    }
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly}${attributes} />`;
  },

  generateCurrencyInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'number';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    const options   = this.getOptions(fieldName);
    let attributes  = '';
    if (options) {
      if (options.min  !== undefined) attributes += ` min="${options.min}"`;
      if (options.max  !== undefined) attributes += ` max="${options.max}"`;
      if (options.step !== undefined) attributes += ` step="${options.step}"`;
    }
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly}${attributes} />`;
  },

  generateTelInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'tel';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateUrlInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'url';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateHiddenInput(fieldName, placeholder, size, isFloating) {
    const options = this.getOptions(fieldName);
    const value   = options?.value || '';
    return `<input type="hidden" id="${fieldName}" name="${fieldName}" data-hidden-no-error="true" value="${value}" />`;
  },

  generateTextSlug(fieldName, placeholder, size, isFloating, setValue = '') {
    const valueAttr     = setValue ? `value="${setValue}"` : '';
    const placeholderAttr = `placeholder="${placeholder}"`;
    // Initialize NexaSlug after DOM is ready
    setTimeout(() => {
      NexaSlug.fromFormData(this.form, { delay: 100 });
    }, 0);
    return `<input type="hidden" id="${fieldName}" name="${fieldName}" data-slug-field="true" ${placeholderAttr} ${valueAttr} />`;
  },
};
