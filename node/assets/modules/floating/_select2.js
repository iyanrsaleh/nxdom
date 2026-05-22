// _select2.js — Select2 lifecycle management mixin for NexaFloating
// Handles: init, re-init, destroy, wait-for-ready, flag-dropdown init

export const Select2Mixin = {

  initializeSelect2() {
    if (!this.formElement) return;
    if (!(window.$ && window.$.fn && window.$.fn.select2)) return;

    // Must be in the live DOM — detached forms would build a double-container.
    if (!this.formElement.isConnected) return;

    // NexaModal.open() handles Select2 after the modal is visible.
    if (this.formElement.closest('.nx-modal')) return;

    const selectElements = this.formElement.querySelectorAll(
      '.form-group-body select:not(.flag-kabupaten-select):not(.flag-kecamatan-select):not(.flag-desa-select):not([data-select2-initialized])',
    );
    if (selectElements.length === 0) return;

    selectElements.forEach((selectElement) => {
      try {
        if (selectElement.closest('[data-field]')) return;

        try {
          if (window.$(selectElement).data('select2')) window.$(selectElement).select2('destroy');
        } catch (_) {}

        const fieldName   = selectElement.name;
        let fieldConfig   = {};
        try { fieldConfig = this.getFieldConfig(fieldName); } catch (_) {}

        const placeholder              = fieldConfig.placeholder || fieldConfig.label || 'Pilih opsi...';
        const allowClear               = fieldConfig.validation !== '2' && fieldConfig.validation !== 2;
        const optionCount              = selectElement.options.length - 1;
        const minimumResultsForSearch  = optionCount <= 5 ? Infinity : 0;
        const modalContainer           = selectElement.closest('.nx-modal');

        window.$(selectElement).select2({
          width:  '100%',
          placeholder,
          allowClear,
          minimumResultsForSearch,
          ...(modalContainer ? { dropdownParent: window.$(modalContainer) } : {}),
          language: {
            noResults: () => 'Tidak ada hasil ditemukan',
            searching: () => 'Mencari...',
          },
        });

        selectElement.setAttribute('data-select2-initialized', 'true');
      } catch (_) {}
    });
  },

  reinitializeSelect2() {
    this.destroySelect2();
    this.initializeSelect2();
  },

  destroySelect2() {
    if (!this.formElement) return;
    this.formElement
      .querySelectorAll('.form-group-body select[data-select2-initialized]')
      .forEach((selectElement) => {
        try {
          if (window.$ && window.$.fn && window.$.fn.select2) {
            if (window.$(selectElement).data('select2')) window.$(selectElement).select2('destroy');
          }
          selectElement.removeAttribute('data-select2-initialized');
        } catch (_) {}
      });
  },

  isSelect2Ready() {
    return !!(window.$ && window.$.fn && window.$.fn.select2);
  },

  waitForSelect2AndInitialize(maxAttempts = 10, delay = 500) {
    let attempts = 0;
    const check  = () => {
      attempts++;
      if (this.isSelect2Ready()) { this.initializeSelect2(); return; }
      if (attempts < maxAttempts) setTimeout(check, delay);
    };
    check();
  },

  forceInitializeSelect2() {
    this.initializeSelect2();
  },

  initializeFlagDropdowns(container) {
    if (!container) return;
    try {
      container
        .querySelectorAll('.flag-kabupaten-select, .flag-kecamatan-select, .flag-desa-select')
        .forEach((select) => {
          if (!select.hasAttribute('data-flag-initialized')) {
            select.addEventListener('change', function (e) {
              e.stopPropagation();
              const val = (window.$ && window.$(this).data('select2'))
                ? window.$(this).val()
                : this.value;
              setTimeout(() => {
                if (this.classList.contains('flag-kabupaten-select')) {
                  if (typeof window.filterKecamatan === 'function') window.filterKecamatan(val);
                } else if (this.classList.contains('flag-kecamatan-select')) {
                  if (typeof window.filterKecamatanDesa === 'function') window.filterKecamatanDesa(val);
                }
              }, 50);
            });
            select.setAttribute('data-flag-initialized', 'true');
          }

          if (window.$ && window.$.fn && window.$.fn.select2 && !window.$(select).data('select2')) {
            try {
              const modalContainer = select.closest('.nx-modal');
              const optionCount    = select.options.length - 1;
              const minResults     = optionCount <= 5 ? Infinity : 0;
              const placeholderMap = {
                'flag-kabupaten-select': 'Select Kabupaten',
                'flag-kecamatan-select': 'Select Kecamatan',
                'flag-desa-select':      'Select Desa',
              };
              const placeholderText =
                Object.entries(placeholderMap).find(([cls]) => select.classList.contains(cls))?.[1] || 'Pilih...';

              window.$(select).select2({
                width:  '100%',
                placeholder: placeholderText,
                allowClear:  true,
                minimumResultsForSearch: minResults,
                ...(modalContainer ? { dropdownParent: window.$(modalContainer) } : {}),
                language: {
                  noResults: () => 'Tidak ada hasil ditemukan',
                  searching: () => 'Mencari...',
                },
              });
              select.setAttribute('data-select2-initialized', 'true');
            } catch (_) {}
          }
        });
    } catch (_) {}
  },
};
