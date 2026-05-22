// _floatingLabels.js — Floating label logic mixin for NexaFloating
// Handles: label activation/deactivation, label discovery, script generation,
//          manual setup, and debug helpers

export const FloatingLabelsMixin = {

  // ─── Core per-input floating label handler ───────────────────────────────

  handleFloatingLabel(element) {
    const label = this.findLabelForElement(element);
    if (!label || label.tagName !== 'LABEL') return;

    const updateLabel = () => {
      if (element.value && element.value !== '') label.classList.add('active');
      else                                        label.classList.remove('active');
    };

    element.addEventListener('input', updateLabel);
    element.addEventListener('focus', () => { label.classList.add('active'); });
    element.addEventListener('blur',  updateLabel);
    updateLabel(); // initial state
  },

  // ─── Smart label discovery ───────────────────────────────────────────────

  findLabelForElement(element) {
    // Method 1: sibling label (or skip icon first)
    let label = element.nextElementSibling;
    if (label && label.tagName === 'I') label = label.nextElementSibling;
    if (label && label.tagName === 'LABEL') return label;

    // Method 2: search input wrapper
    if (element.closest('.form-nexa-search-container')) {
      const searchWrapper = element.closest('.form-nexa-search');
      if (searchWrapper?.nextElementSibling) {
        let next = searchWrapper.nextElementSibling;
        if (next && next.tagName === 'I') next = next.nextElementSibling;
        if (next && next.tagName === 'LABEL') return next;
      }
      const floatingContainer = element.closest('.form-nexa-floating');
      if (floatingContainer) {
        const l = floatingContainer.querySelector(`label[for="${element.name}"]`);
        if (l) return l;
      }
    }

    // Method 3: for attribute
    const root = element.closest('form') || document;
    return (
      root.querySelector(`label[for="${element.name}"]`) ||
      root.querySelector(`label[for="${element.id}"]`)
    );
  },

  triggerFloatingLabel(input) {
    const label = this.findLabelForElement(input);
    if (label && label.tagName === 'LABEL') {
      if (input.value && input.value !== '') label.classList.add('active');
      else                                   label.classList.remove('active');
    }
  },

  // ─── Post-insert initialisation ─────────────────────────────────────────

  initializeFloatingLabels(containerSelector) {
    const container =
      typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
    if (!container) return;

    this.formElement =
      container.querySelector(`#${this.formId}`) ||
      container.querySelector('form') ||
      container;

    if (!this.formElement) return;

    this.attachFloatingLabelEvents();
    this.attachEventListeners();
    this.attachSingleSelectBehavior();
    this.initializeSelect2();
    return this;
  },

  attachFloatingLabelEvents() {
    if (!this.formElement) return;
    this.formElement
      .querySelectorAll(
        'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea',
      )
      .forEach((input) => { this.handleFloatingLabel(input); });
  },

  // ─── Manual / emergency setup ────────────────────────────────────────────

  setupFloatingLabelsManual(containerSelector) {
    const container =
      typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
    if (!container) return;

    container
      .querySelectorAll(
        'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea',
      )
      .forEach((input) => {
        const handleFloating = () => {
          const label = this.findLabelForInput(input);
          if (label && label.tagName === 'LABEL') {
            if (input.value && input.value.trim() !== '') label.classList.add('active');
            else                                           label.classList.remove('active');
          }
        };
        input.addEventListener('input', handleFloating);
        input.addEventListener('focus', handleFloating);
        input.addEventListener('blur',  handleFloating);
        handleFloating();
      });

    this.initializeSelect2();
  },

  findLabelForInput(input) {
    const container = input.closest('form') || document;
    let label =
      container.querySelector(`label[for="${input.name}"]`) ||
      container.querySelector(`label[for="${input.id}"]`);
    if (label) return label;

    const floatingContainer = input.closest('.form-nexa-floating');
    if (floatingContainer) {
      label = floatingContainer.querySelector('label');
      if (label) return label;
    }

    label = input.nextElementSibling;
    if (label && label.tagName === 'I') label = label.nextElementSibling;
    if (label && label.tagName === 'LABEL') return label;

    const parent = input.parentElement;
    if (parent) {
      label = parent.querySelector('label');
      if (label) return label;
    }
    return null;
  },

  resetFloatingLabels() {
    this.formElement?.querySelectorAll('.form-nexa-floating label').forEach((l) => {
      l.classList.remove('active');
    });
  },

  forceActivateAllLabels() {
    this.formElement?.querySelectorAll('.form-nexa-floating label').forEach((l) => {
      l.classList.add('active');
    });
    setTimeout(() => {
      this.formElement?.querySelectorAll('.form-nexa-floating label').forEach((l) => {
        window.getComputedStyle(l); // consume computed styles
      });
    }, 200);
  },

  // ─── Inline script generator (html() mode) ──────────────────────────────

  generateFloatingLabelsScript() {
    const formId = this.formId;

    function initFloatingLabels() {
      const form =
        document.getElementById(formId) || document.querySelector('form.nexa-floating-form');
      if (!form) return;

      form
        .querySelectorAll(
          'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea',
        )
        .forEach(setupFloatingForInput);
    }

    function setupFloatingForInput(input) {
      function findLabel(input) {
        let label =
          document.querySelector(`label[for="${input.name}"]`) ||
          document.querySelector(`label[for="${input.id}"]`);
        if (label) return label;

        const container = input.closest('.form-nexa-floating');
        if (container) { label = container.querySelector('label'); if (label) return label; }

        label = input.nextElementSibling;
        if (label && label.tagName === 'I') label = label.nextElementSibling;
        if (label && label.tagName === 'LABEL') return label;

        const parent = input.parentElement;
        if (parent) { label = parent.querySelector('label'); if (label) return label; }
        return null;
      }

      const handleFloating = () => {
        const label = findLabel(input);
        if (label && label.tagName === 'LABEL') {
          if (input.value && input.value.trim() !== '') label.classList.add('active');
          else                                           label.classList.remove('active');
        }
      };

      input.addEventListener('input', handleFloating);
      input.addEventListener('focus', () => {
        const label = findLabel(input);
        if (label && label.tagName === 'LABEL') label.classList.add('active');
      });
      input.addEventListener('blur', handleFloating);
      handleFloating();
    }

    function initSingleSelectBehavior() {
      document.querySelectorAll('.single-select-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', function (e) {
          if (e.target.checked) {
            document
              .querySelectorAll(`input[name="${e.target.name}"].single-select-checkbox`)
              .forEach((o) => { if (o !== e.target) o.checked = false; });
          }
        });
      });

      document.querySelectorAll('.single-select-switch').forEach((switchEl) => {
        switchEl.addEventListener('change', function (e) {
          if (e.target.checked) {
            document
              .querySelectorAll(`input[name="${e.target.name}"].single-select-switch`)
              .forEach((o) => { if (o !== e.target) o.checked = false; });
          }
        });
      });
    }

    function initSelect2() {
      const form =
        document.getElementById(formId) || document.querySelector('form.nexa-floating-form');
      if (!form) return;
      if (form.closest('.nx-modal')) return;

      const selectElements = form.querySelectorAll(
        '.form-group-body select:not([data-select2-initialized])',
      );
      if (selectElements.length === 0) return;

      let attempts = 0;
      const tryInit = () => {
        attempts++;
        if (!(window.$ && window.$.fn && window.$.fn.select2)) {
          if (attempts < 10) setTimeout(tryInit, 500);
          return;
        }
        selectElements.forEach((selectElement) => {
          if (selectElement.hasAttribute('data-select2-initialized')) return;
          window.$(selectElement).select2({
            placeholder: 'Pilih opsi...',
            allowClear:  true,
            width:       '100%',
            language: {
              noResults: () => 'Tidak ada hasil ditemukan',
              searching: () => 'Mencari...',
            },
          });
          selectElement.setAttribute('data-select2-initialized', 'true');
        });
      };
      tryInit();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        initFloatingLabels();
        initSingleSelectBehavior();
        initSelect2();
      });
    } else {
      initFloatingLabels();
      initSingleSelectBehavior();
      initSelect2();
    }
  },
};
