// _dateFields.js — Date / time input generators mixin for NexaFloating
// Handles: date, datetime-local, time, month, week

export const DateFieldsMixin = {

  generateDateInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const placeholderAttr = `placeholder="${placeholder}"`;
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'date';
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" ${placeholderAttr} class="${size}" ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateDateTimeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'datetime-local';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateTimeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'time';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateMonthInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'month';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },

  generateWeekInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'week';
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${placeholderAttr} ${valueAttr} ${disabled} ${readonly} />`;
  },
};
