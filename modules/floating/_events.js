// _events.js — DOM event attachment mixin for NexaFloating
// Handles: form submit, search, range, checkbox/radio, single-select behaviour

export const EventsMixin = {

  attachFloatingUploadSuccessListener() {
    if (this._nexaFloatingUploadSuccessBound) return;
    this._nexaFloatingUploadSuccessBound = (ev) => {
      const fid = ev.detail?.formElementId;
      if (fid == null || fid === '') return;
      if (!this.formElement) return;
      const fidStr = String(fid);
      const formIdStr = String(this.formId);
      const isDirectFormIdMatch = fidStr === formIdStr;
      let isContainerMatch = false;
      if (!isDirectFormIdMatch && typeof document !== 'undefined') {
        const targetContainer = document.getElementById(fidStr);
        if (targetContainer && typeof targetContainer.contains === 'function') {
          isContainerMatch = targetContainer.contains(this.formElement);
        }
      }
      if (!isDirectFormIdMatch && !isContainerMatch) return;
      if (typeof globalThis.resetNexaFileUploadsInContainer === 'function') {
        globalThis.resetNexaFileUploadsInContainer(this.formElement);
      }
    };
    document.addEventListener('nexaFloatingFileUploadSuccess', this._nexaFloatingUploadSuccessBound);
  },

  detachFloatingUploadSuccessListener() {
    if (!this._nexaFloatingUploadSuccessBound) return;
    document.removeEventListener('nexaFloatingFileUploadSuccess', this._nexaFloatingUploadSuccessBound);
    this._nexaFloatingUploadSuccessBound = null;
  },

  attachEventListeners() {
    if (!this.formElement) return;

    // Handle search inputs
    this.formElement.querySelectorAll('.form-nexa-search-input').forEach((input) => {
      this.attachSearchEvents(input);
    });

    // Form submit
    this.formElement.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleSubmit();
    });

    // Floating labels
    this.formElement
      .querySelectorAll(
        'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), select, textarea',
      )
      .forEach((input) => { this.handleFloatingLabel(input); });

    // Range inputs
    this.formElement.querySelectorAll('input[type="range"]').forEach((rangeInput) => {
      this.attachRangeEvents(rangeInput);
    });

    // Checkbox / radio
    this.formElement
      .querySelectorAll('input[type="checkbox"], input[type="radio"]')
      .forEach((input) => { this.attachCheckboxRadioEvents(input); });

    // Single-select checkboxes and switches
    this.attachSingleSelectBehavior();

    this.attachFloatingUploadSuccessListener();
  },

  attachSearchEvents(input) {
    const container = input.closest('.form-nexa-search-container');
    const items     = container?.querySelectorAll('.form-nexa-search-item') ?? [];

    input.addEventListener('focus', () => { container?.classList.add('active'); });
    input.addEventListener('blur',  () => {
      setTimeout(() => { container?.classList.remove('active'); }, 200);
    });

    input.addEventListener('input', (e) => {
      const value = e.target.value.toLowerCase();
      items.forEach((item) => {
        item.style.display = item.textContent.toLowerCase().includes(value) ? 'block' : 'none';
      });
    });

    items.forEach((item) => {
      item.addEventListener('click', () => {
        input.value = item.textContent;
        input.setAttribute('data-value', item.getAttribute('data-value'));
        container?.classList.remove('active');
        this.triggerFloatingLabel(input);
      });
    });
  },

  attachRangeEvents(rangeInput) {
    const valueDisplay      = document.createElement('div');
    valueDisplay.className  = 'form-nexa-range-value';
    valueDisplay.textContent = rangeInput.value;
    rangeInput.parentNode.insertBefore(valueDisplay, rangeInput.nextSibling);

    rangeInput.addEventListener('input',  (e) => { valueDisplay.textContent = e.target.value; });
    rangeInput.addEventListener('change', (e) => { valueDisplay.textContent = e.target.value; });
  },

  attachCheckboxRadioEvents(input) {
    input.addEventListener('change', (e) => {
      document.dispatchEvent(
        new CustomEvent('nexaInputChange', {
          detail: {
            name:  input.name,
            value: input.type === 'checkbox' ? input.checked : input.value,
            type:  input.type,
          },
        }),
      );
    });

    input.addEventListener('click', (e) => { e.stopPropagation(); });
  },

  attachSingleSelectBehavior() {
    if (!this.formElement) return;

    // Single-select checkboxes
    this.formElement.querySelectorAll('.single-select-checkbox').forEach((checkbox) => {
      checkbox.addEventListener('change', (e) => {
        if (e.target.checked) {
          this.formElement
            .querySelectorAll(`input[name="${e.target.name}"].single-select-checkbox`)
            .forEach((other) => { if (other !== e.target) other.checked = false; });
        }
      });
    });

    // Single-select switches
    this.formElement.querySelectorAll('.single-select-switch').forEach((switchEl) => {
      switchEl.addEventListener('change', (e) => {
        if (e.target.checked) {
          this.formElement
            .querySelectorAll(`input[name="${e.target.name}"].single-select-switch`)
            .forEach((other) => { if (other !== e.target) other.checked = false; });
        }
      });
    });
  },
};
