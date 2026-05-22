// _specialPopulate.js — Populate/search-specific field generators mixin for NexaFloating
// Handles: populate, search (content + modal)

import { generateUniqueName } from './_helpers.js';

function _getLookupConfig(config, type = 'search') {
  if (type === 'populate') {
    return config?.populate || config?.search || {};
  }
  return config?.search || {};
}

function _getSuggestionId(prefix, randomID) {
  return `${prefix}_${randomID}`;
}

function _setFieldValueByKey(fieldKey, value) {
  if (!fieldKey) return;
  const fieldElement =
    document.getElementById(fieldKey) ||
    document.querySelector(`[name="${fieldKey}"]`) ||
    document.querySelector(`input[data-field="${fieldKey}"]`);

  if (!fieldElement) return;

  if (fieldElement.tagName === 'INPUT' || fieldElement.tagName === 'TEXTAREA') {
    NXUI.id(fieldElement.id || fieldElement.name)?.val(value ?? '');
    fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
    fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
    fieldElement.dispatchEvent(new Event('blur', { bubbles: true }));
    return;
  }

  if (fieldElement.tagName === 'SELECT') {
    fieldElement.value = value ?? '';
    fieldElement.dispatchEvent(new Event('change', { bubbles: true }));
    fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
  }
}

function _applyPopulateTargets(lookupConfig, selectedRow) {
  const populateIndex = lookupConfig?.metadata?.index || {};
  const populateTarget = lookupConfig?.metadata?.targets || [];


  if (populateIndex?.key && populateIndex?.fieldSearch) {
    const indexValue = selectedRow?.[populateIndex.fieldSearch];
    _setFieldValueByKey(populateIndex.key, indexValue);
  }

  populateTarget.forEach((item) => {
    if (!item?.key || !item?.fields) return;
    const targetValue = selectedRow?.[item.fields];
    _setFieldValueByKey(item.key, targetValue);
  });
}

function _normalizePopulateRows(result, lookupConfig) {
  const responseRows = Array.isArray(result?.data?.response) ? result.data.response : [];
  const populateIndex = lookupConfig?.metadata?.index || {};
  const idKey = populateIndex?.fieldValue || populateIndex?.id || 'id';
  const searchKey = populateIndex?.fieldSearch || 'name';

  return responseRows.map((row, idx) => {
    const resolvedId = row?.id ?? row?.[idKey] ?? idx;
    const resolvedLabel = row?.label ?? row?.[searchKey] ?? row?.value ?? row?.data ?? '';
    const resolvedValue = row?.value ?? row?.[searchKey] ?? resolvedLabel;

    return {
      ...row,
      id: resolvedId,
      label: resolvedLabel,
      value: resolvedValue,
      title: row?.title ?? '',
    };
  });
}

// ─── Shared suggestion-dropdown position helper ──────────────────────────────

function _positionSuggestionInModal(domElement, suggestionsDiv) {
  const modalBody = domElement.closest('.nx-modal')?.querySelector('.nx-modal-body');
  if (!modalBody) return false; // caller must do fixed positioning

  // Ensure search container is positioned relatively
  let searchContainer = domElement.closest('.nexa-search-container');
  if (!searchContainer) {
    searchContainer = document.createElement('div');
    searchContainer.className  = 'nexa-search-container';
    searchContainer.style.position = 'relative';
    domElement.parentNode.insertBefore(searchContainer, domElement);
    searchContainer.appendChild(domElement);
  }

  // Critical: dropdown must live in the same positioned container as the input
  // to avoid vertical offset/drift inside modal.
  if (suggestionsDiv.parentNode !== searchContainer) {
    searchContainer.appendChild(suggestionsDiv);
  }

  const dropdownHeight  = 250;
  const modalBodyRect   = modalBody.getBoundingClientRect();
  const inputRect       = domElement.getBoundingClientRect();
  const modalFooter     = domElement.closest('.nx-modal')?.querySelector('.nx-modal-footer');
  const modalContent    = domElement.closest('.nx-modal')?.querySelector('.nx-modal-content');

  let availableBelow = modalBodyRect.bottom - inputRect.bottom;
  let availableAbove = inputRect.top - modalBodyRect.top;

  if (modalFooter) {
    const footerTop = modalFooter.getBoundingClientRect().top;
    availableBelow  = Math.min(availableBelow, footerTop - inputRect.bottom);
  }
  if (modalContent) {
    const contentBottom = modalContent.getBoundingClientRect().bottom;
    availableBelow      = Math.min(availableBelow, contentBottom - inputRect.bottom);
  }

  const testBottom  = inputRect.bottom + dropdownHeight;
  const wouldCutOff = testBottom > modalBodyRect.bottom;
  const overflowAmt = testBottom - modalBodyRect.bottom;
  const allowOver   = overflowAmt < 30;
  const footerOverlap = modalFooter
    ? Math.max(0, inputRect.bottom + dropdownHeight - modalFooter.getBoundingClientRect().top)
    : 0;

  suggestionsDiv.style.position = 'absolute';
  suggestionsDiv.style.left     = '0px';
  suggestionsDiv.style.width    = domElement.offsetWidth + 'px';
  suggestionsDiv.style.zIndex   = '2147483647';
  suggestionsDiv.style.setProperty('z-index', '2147483647', 'important');
  suggestionsDiv.style.visibility = 'visible';
  suggestionsDiv.style.opacity    = '1';

  if ((wouldCutOff && !allowOver) || footerOverlap > 0) {
    if (availableAbove > 50) {
      const h = Math.min(dropdownHeight, availableAbove - 5);
      suggestionsDiv.style.top       = `-${h}px`;
      suggestionsDiv.style.maxHeight = h + 'px';
      suggestionsDiv.classList.add('nexa-dropdown-above');
    } else {
      const h = Math.max(150, availableBelow - 5);
      suggestionsDiv.style.top       = domElement.offsetHeight + 'px';
      suggestionsDiv.style.maxHeight = h + 'px';
      suggestionsDiv.classList.remove('nexa-dropdown-above');
      suggestionsDiv.style.display   = 'block';
    }
  } else {
    suggestionsDiv.style.top       = domElement.offsetHeight + 'px';
    suggestionsDiv.style.maxHeight = '250px';
    suggestionsDiv.classList.remove('nexa-dropdown-above');
  }
  return true;
}

function _positionPopulateSuggestionBelow(domElement, suggestionsDiv) {
  if (!domElement || !suggestionsDiv) return;

  const modalRoot = domElement.closest('.nx-modal');
  const modalBody = modalRoot?.querySelector('.nx-modal-body');
  const searchContainer = domElement.closest('.nexa-search-container');

  if (searchContainer && suggestionsDiv.parentNode !== searchContainer) {
    searchContainer.appendChild(suggestionsDiv);
  }

  if (searchContainer) searchContainer.style.position = 'relative';

  const inputRect = domElement.getBoundingClientRect();
  const modalBodyRect = modalBody?.getBoundingClientRect();
  const footerRect = modalRoot?.querySelector('.nx-modal-footer')?.getBoundingClientRect();

  const modalBottom = footerRect?.top ?? modalBodyRect?.bottom ?? window.innerHeight;
  const spaceBelow = Math.max(0, modalBottom - inputRect.bottom - 8);
  const maxHeight = Math.max(120, Math.min(250, spaceBelow || 250));

  suggestionsDiv.style.position = 'absolute';
  suggestionsDiv.style.left = '0px';
  suggestionsDiv.style.top = `${domElement.offsetHeight}px`;
  suggestionsDiv.style.width = `${domElement.offsetWidth}px`;
  suggestionsDiv.style.maxHeight = `${maxHeight}px`;
  suggestionsDiv.style.zIndex = '2147483647';
  suggestionsDiv.style.visibility = 'visible';
  suggestionsDiv.style.opacity = '1';
  suggestionsDiv.style.display = 'block';
  suggestionsDiv.classList.remove('nexa-dropdown-above');
}

function _applySelectedData(config, formData, fieldName, id, myData, randomID) {
  if (!config.target) return;
  const selectedData = myData.find((item) => item.id == id || item.id === parseInt(id));
  if (selectedData && formData?.target?.[fieldName]?.add) {
    const addMapping = formData.target[fieldName].add;
    Object.keys(addMapping).forEach((fieldCondition) => {
      const variableName = addMapping[fieldCondition];
      const conditionField =
        document.querySelector(`[name="${fieldCondition}"]`) ||
        document.getElementById(fieldCondition) ||
        document.querySelector(`input[data-field="${fieldCondition}"]`);
      if (conditionField && selectedData[variableName] !== undefined) {
        if (conditionField.tagName === 'INPUT' || conditionField.tagName === 'TEXTAREA') {
          NXUI.id(conditionField.id || conditionField.name)?.val(selectedData[variableName]);
          conditionField.dispatchEvent(new Event('input',  { bubbles: true }));
          conditionField.dispatchEvent(new Event('focus',  { bubbles: true }));
          conditionField.dispatchEvent(new Event('blur',   { bubbles: true }));
        } else if (conditionField.tagName === 'SELECT') {
          conditionField.value = selectedData[variableName];
          conditionField.dispatchEvent(new Event('change', { bubbles: true }));
          conditionField.dispatchEvent(new Event('input',  { bubbles: true }));
          conditionField.dispatchEvent(new Event('focus',  { bubbles: true }));
          conditionField.dispatchEvent(new Event('blur',   { bubbles: true }));
        }
      }
    });
  }
}

// ─── Mixin ───────────────────────────────────────────────────────────────────

export const SpecialPopulateMixin = {
  generatePopulateInput(fieldName, placeholder, size, isFloating, config, settings, setValue) {
    const randomID = generateUniqueName(fieldName);

    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const suggestionsId = _getSuggestionId('populate_suggestions', randomID);
    const validationBypassAttrs = setValue && setValue.trim() !== ''
      ? `data-has-preset-value="true" data-preset-value="${setValue.replace(/"/g, '&quot;')}"`
      : '';

    setTimeout(async () => {
      const inputElement = document.getElementById(randomID);
      const isInModalDom = Boolean(inputElement?.closest('.nx-modal'));
      const useModalMode = settings?.model === 'Modal' || isInModalDom;

      if (useModalMode) {
        await this.setModalPopulateInput(config, fieldName, randomID, this.formData);
      } else {
        await this.contentPopulateInput(config, fieldName, randomID, this.formData);
      }
    }, 0);

    return `
      <div class="form-nexa-group1 nexa-search-container nexa-populate-container">
        <input type="text" id="${randomID}" name="${fieldName}" class="${size}" ${valueAttr} ${placeholderAttr} ${validationBypassAttrs} data-lookup-type="populate" />
        <div id="${suggestionsId}" class="nexa-suggestions-dropdown nexa-populate-dropdown"></div>
      </div>
    `;
  },

  async contentPopulateInput(config, fieldName, randomID, formData) {
    try {

      const inputEl = NXUI.id(randomID);
      const lookupConfig = _getLookupConfig(config, 'populate');
      const suggestionsId = _getSuggestionId('populate_suggestions', randomID);
      this.addSuggestionsStyles();

      inputEl.on('keyup', async (e) => {
        const inputValue = e.target.value?.trim() || '';
        const suggestionsDiv = document.getElementById(suggestionsId);
        if (!suggestionsDiv) return;

        if (!inputValue) {
          suggestionsDiv.innerHTML = '';
          suggestionsDiv.style.display = 'none';
          suggestionsDiv.classList.remove('nexa-dropdown-above');
          suggestionsDiv.style.maxHeight = '';
          return;
        }

        const populateIndex = lookupConfig?.metadata?.index || {};

        const result = await NXUI.Storage().models('Office').searchPopulate({
          search: inputValue,
          ...populateIndex,
        });

        const myData = _normalizePopulateRows(result, lookupConfig);

        const domElement = document.getElementById(randomID);
        const inputRect = domElement.getBoundingClientRect();
        suggestionsDiv.style.left = inputRect.left + 'px';
        suggestionsDiv.style.top = inputRect.bottom + window.scrollY + 'px';
        suggestionsDiv.style.width = inputRect.width + 'px';

        if (myData.length > 0) {
          let html = '<ul class="nexa-suggestions-list">';
          myData.forEach((item) => {
            html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
              <h4 style="color: black; margin: 0;">${item.label || item.value || item.data}</h4>
              ${item.title ? `<span style="font-size:10px; color: black;">${item.title || item.label}</span>` : ''}
            </li>`;
          });
          html += '</ul>';
          suggestionsDiv.innerHTML = html;
          suggestionsDiv.style.display = 'block';

          suggestionsDiv.querySelectorAll('.nexa-suggestion-item').forEach((li) => {
            li.addEventListener('click', function () {
              const id = this.dataset.id || this.getAttribute('data-id');
              const value = this.dataset.value || this.getAttribute('data-value');
              const selectedRow = myData.find((item) => String(item.id) === String(id));

              if (lookupConfig?.hiddenFormvalue) {
                let hiddenInput = document.getElementById(`hidden${lookupConfig.hiddenFormvalue}`);
                if (!hiddenInput) {
                  hiddenInput = document.createElement('input');
                  hiddenInput.type = 'hidden';
                  hiddenInput.id = `hidden${lookupConfig.hiddenFormvalue}`;
                  hiddenInput.name = lookupConfig.hiddenFormvalue;
                  const searchContainer = document.getElementById(randomID)?.closest('.nexa-search-container');
                  if (searchContainer) searchContainer.appendChild(hiddenInput);
                }
                hiddenInput.value = id;
              }

              NXUI.id(randomID).val(value);
              _applyPopulateTargets(lookupConfig, selectedRow);
              _applySelectedData(config, formData, fieldName, id, myData, randomID);
              suggestionsDiv.innerHTML = '';
              suggestionsDiv.style.display = 'none';
              suggestionsDiv.classList.remove('nexa-dropdown-above');
            });
          });
        } else {
          suggestionsDiv.innerHTML = '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
          suggestionsDiv.style.display = 'block';
          NXUI.id(randomID).val('');
        }
      });
    } catch (_) {}
  },

  async setModalPopulateInput(config, fieldName, randomID, formData) {
    try {
      const inputEl = NXUI.id(randomID);
      const lookupConfig = _getLookupConfig(config, 'populate');
      const suggestionsId = _getSuggestionId('populate_suggestions', randomID);
      this.addSuggestionsStyles();

      inputEl.on('blur', function () {
        setTimeout(() => {
          const div = document.getElementById(suggestionsId);
          if (div) div.style.display = 'none';
        }, 150);
      });

      document.addEventListener('click', function (e) {
        const div = document.getElementById(suggestionsId);
        const inputElement = document.getElementById(randomID);
        if (div && inputElement && !div.contains(e.target) && !inputElement.contains(e.target)) {
          div.style.display = 'none';
        }
      });

      inputEl.on('keyup', async (e) => {
        const inputValue = e.target.value?.trim() || '';
        const suggestionsDiv = document.getElementById(suggestionsId);
        if (!suggestionsDiv) return;

        if (!inputValue) {
          suggestionsDiv.innerHTML = '';
          suggestionsDiv.style.display = 'none';
          suggestionsDiv.classList.remove('nexa-dropdown-above');
          ['maxHeight', 'zIndex', 'position', 'top', 'left', 'width', 'visibility', 'opacity'].forEach(
            (prop) => { suggestionsDiv.style[prop] = ''; },
          );
          return;
        }

        const populateIndex = lookupConfig?.metadata?.index || {};

        const result = await NXUI.Storage().models('Office').searchPopulate({
          search: inputValue,
          ...populateIndex,
        });

        const myData = _normalizePopulateRows(result, lookupConfig);
        const domElement = document.getElementById(randomID);

        _positionPopulateSuggestionBelow(domElement, suggestionsDiv);

        if (Array.isArray(myData) && myData.length > 0) {
          let html = '<ul class="nexa-suggestions-list">';
          myData.forEach((item) => {
            html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
              <h4 style="color: black; margin: 0;">${item.label || item.value || item.data}</h4>
              ${item.title ? `<span style="font-size:10px; color: black;">${item.title || item.label}</span>` : ''}
            </li>`;
          });
          html += '</ul>';
          suggestionsDiv.innerHTML = html;
          suggestionsDiv.style.display = 'block';

          suggestionsDiv.querySelectorAll('.nexa-suggestion-item').forEach((li) => {
            li.addEventListener('click', function () {
              const id = this.dataset.id || this.getAttribute('data-id');
              const value = this.dataset.value || this.getAttribute('data-value');
              const selectedRow = myData.find((item) => String(item.id) === String(id));

              if (lookupConfig?.hiddenFormvalue) {
                let hiddenInput = document.getElementById(`hidden${lookupConfig.hiddenFormvalue}`);
                if (!hiddenInput) {
                  hiddenInput = document.createElement('input');
                  hiddenInput.type = 'hidden';
                  hiddenInput.id = `hidden${lookupConfig.hiddenFormvalue}`;
                  hiddenInput.name = lookupConfig.hiddenFormvalue;
                  const searchContainer = document.getElementById(randomID)?.closest('.nexa-search-container');
                  if (searchContainer) searchContainer.appendChild(hiddenInput);
                }
                hiddenInput.value = id;
              }

              NXUI.id(randomID).val(value);
              _applyPopulateTargets(lookupConfig, selectedRow);
              _applySelectedData(config, formData, fieldName, id, myData, randomID);
              suggestionsDiv.innerHTML = '';
              suggestionsDiv.style.display = 'none';
              suggestionsDiv.classList.remove('nexa-dropdown-above');
            });
          });
        } else {
          suggestionsDiv.innerHTML = '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
          suggestionsDiv.style.display = 'block';
          NXUI.id(randomID).val('');
        }
      });
    } catch (_) {}
  },

  // ── Search (content mode) ─────────────────────────────────────────────────

  generateSearchInput(fieldName, placeholder, size, isFloating, config, settings, setValue, formData) {
    const randomID        = generateUniqueName(fieldName);
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr       = setValue ? `value="${setValue}"` : '';
    const suggestionsId = _getSuggestionId('suggestions', randomID);

    setTimeout(async () => {
      if (settings.model === 'Modal') {
        await this.setModalSearchInput(config, fieldName, randomID, formData);
      } else {
        await this.contentSearchInput(config, fieldName, randomID, formData);
      }
    }, 0);

    let hiddenvalue = '';
    if (config?.search?.hiddenFormvalue && this.options.mode !== 'update') {
      hiddenvalue = `<input type="hidden" id="hidden${config?.search?.hiddenFormvalue}" name="${config?.search?.hiddenFormvalue}"/>`;
    }

    const validationBypassAttrs = setValue && setValue.trim() !== ''
      ? `data-has-preset-value="true" data-preset-value="${setValue.replace(/"/g, '&quot;')}"`
      : '';

    return `
      <div class="form-nexa-group1 nexa-search-container">
        <input type="text" id="${randomID}" name="${fieldName}" class="${size}" ${valueAttr} ${placeholderAttr} ${validationBypassAttrs} />
        ${hiddenvalue}
      </div>
      <div id="${suggestionsId}" class="nexa-suggestions-dropdown"></div>
    `;
  },

  // ── Search inner — content page ───────────────────────────────────────────

  async contentSearchInput(config, fieldName, randomID, formData) {
    try {
      const inputEl  = NXUI.id(randomID);
      const configEl = config.search;
      const suggestionsId = _getSuggestionId('suggestions', randomID);
      this.addSuggestionsStyles();

      inputEl.on('keyup', async function (e) {
        if (!e.target.value || e.target.value.trim() === '') {
          const div = document.getElementById(suggestionsId);
          if (div) {
            div.innerHTML = '';
            div.style.display = 'none';
            div.classList.remove('nexa-dropdown-above');
            div.style.maxHeight = '';
          }
          return;
        }

        const searchParams = {
          access:   config.search?.access,
          metadata: Number(config.search?.tabelName),
          field:    config.search?.tabeltext,
          label:    config.search?.tabeltext,
          title:    config.search?.labelvalue || config.search?.tabeltext || null,
          value:    config.search?.tabelvalue,
          where:    { field: config.search?.wheretext, value: config.search?.wherevalue },
        };

        const result = await NXUI.Storage().models('Office').searchAt(searchParams, e.target.value);
        let myData = [];
        if (result && Array.isArray(result.data)) myData = result.data;

        const suggestionsDiv = document.getElementById(suggestionsId);
        if (!suggestionsDiv) return;

        const domElement = document.getElementById(randomID);
        const inputRect  = domElement.getBoundingClientRect();
        suggestionsDiv.style.left  = inputRect.left + 'px';
        suggestionsDiv.style.top   = inputRect.bottom + window.scrollY + 'px';
        suggestionsDiv.style.width = inputRect.width + 'px';

        if (result && Array.isArray(result.data) && result.data.length > 0) {
          let html = '<ul class="nexa-suggestions-list">';
          result.data.forEach((item) => {
            html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
              <h4 style="color: black; margin: 0;">${item.label || item.value || item.data}</h4>
              ${item.title ? `<span style="font-size:10px; color: black;">${item.title || item.label}</span>` : ''}
            </li>`;
          });
          html += '</ul>';
          suggestionsDiv.innerHTML    = html;
          suggestionsDiv.style.display = 'block';

          suggestionsDiv.querySelectorAll('.nexa-suggestion-item').forEach((li) => {
            li.addEventListener('click', function () {
              const id    = this.getAttribute('data-id');
              const value = this.getAttribute('data-value');

              if (configEl?.hiddenFormvalue) {
                let hiddenInput = document.getElementById('hidden' + config?.search?.hiddenFormvalue);
                if (!hiddenInput) {
                  hiddenInput      = document.createElement('input');
                  hiddenInput.type = 'hidden';
                  hiddenInput.id   = 'hidden' + config?.search?.hiddenFormvalue;
                  hiddenInput.name = config?.search?.hiddenFormvalue;
                  const sc = document.getElementById(randomID)?.closest('.nexa-search-container');
                  if (sc) sc.appendChild(hiddenInput);
                }
                hiddenInput.value = id;
              }
              NXUI.id(randomID).val(value);
              _applySelectedData(config, formData, fieldName, id, myData, randomID);
              suggestionsDiv.innerHTML    = '';
              suggestionsDiv.style.display = 'none';
              suggestionsDiv.classList.remove('nexa-dropdown-above');
            });
          });
        } else {
          suggestionsDiv.innerHTML    = '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
          suggestionsDiv.style.display = 'block';
          NXUI.id(randomID).val('');
        }
      });
    } catch (_) {}
  },

  // ── Search inner — modal ──────────────────────────────────────────────────

  async setModalSearchInput(config, fieldName, randomID, formData) {
    try {
      const inputEl  = NXUI.id(randomID);
      const configEl = config.search;
      const suggestionsId = _getSuggestionId('suggestions', randomID);
      this.addSuggestionsStyles();

      inputEl.on('blur', function () {
        setTimeout(() => {
          const div = document.getElementById(suggestionsId);
          if (div) div.style.display = 'none';
        }, 150);
      });

      document.addEventListener('click', function (e) {
        const div         = document.getElementById(suggestionsId);
        const inputElement = document.getElementById(randomID);
        if (div && inputElement && !div.contains(e.target) && !inputElement.contains(e.target)) {
          div.style.display = 'none';
        }
      });

      inputEl.on('keyup', async function (e) {
        if (!e.target.value || e.target.value.trim() === '') {
          const div = document.getElementById(suggestionsId);
          if (div) {
            div.innerHTML = '';
            div.style.display  = 'none';
            div.classList.remove('nexa-dropdown-above');
            ['maxHeight', 'zIndex', 'position', 'top', 'left', 'width', 'visibility', 'opacity'].forEach(
              (p) => { div.style[p] = ''; },
            );
          }
          return;
        }

        const searchParams = {
          access:   config.search?.access,
          metadata: Number(config.search?.tabelName),
          field:    config.search?.tabeltext,
          label:    config.search?.tabeltext,
          title:    config.search?.labelvalue || config.search?.tabeltext || null,
          value:    config.search?.tabelvalue,
          where:    { field: config.search?.wheretext, value: config.search?.wherevalue },
        };

        const result = await NXUI.Storage().models('Office').searchAt(searchParams, e.target.value);
        let myData   = result?.data ?? [];

        const suggestionsDiv = document.getElementById(suggestionsId);
        if (!suggestionsDiv) return;

        const domElement = document.getElementById(randomID);

        // Try modal-relative positioning first; fall back to fixed
        const usedModal = _positionSuggestionInModal(domElement, suggestionsDiv);
        if (!usedModal) {
          const vp          = window.innerHeight;
          const inputRect   = domElement.getBoundingClientRect();
          const spaceBelow  = vp - inputRect.bottom;
          const spaceAbove  = inputRect.top;
          suggestionsDiv.style.position = 'fixed';
          suggestionsDiv.style.left     = inputRect.left + 'px';
          suggestionsDiv.style.width    = inputRect.width + 'px';
          suggestionsDiv.style.zIndex   = '9999999';
          if (spaceBelow < 200 && spaceAbove > 200) {
            suggestionsDiv.style.top = (inputRect.top - 200) + 'px';
            suggestionsDiv.classList.add('nexa-dropdown-above');
          } else {
            suggestionsDiv.style.top   = inputRect.bottom + 'px';
            suggestionsDiv.style.display = 'none';
            suggestionsDiv.classList.remove('nexa-dropdown-above');
          }
        }

        if (result && Array.isArray(result.data) && result.data.length > 0) {
          let html = '<ul class="nexa-suggestions-list">';
          result.data.forEach((item) => {
            html += `<li class="nexa-suggestion-item" data-value="${item.value}" data-id="${item.id}">
              <h4 style="color: black; margin: 0;">${item.label || item.value || item.data}</h4>
              ${item.title ? `<span style="font-size:10px; color: black;">${item.title || item.label}</span>` : ''}
            </li>`;
          });
          html += '</ul>';
          suggestionsDiv.innerHTML    = html;
          suggestionsDiv.style.display = 'block';

          suggestionsDiv.querySelectorAll('.nexa-suggestion-item').forEach((li) => {
            li.addEventListener('click', function () {
              const id    = this.getAttribute('data-id');
              const value = this.getAttribute('data-value');

              if (configEl?.hiddenFormvalue) {
                let hiddenInput = document.getElementById('hidden' + config?.search?.hiddenFormvalue);
                if (!hiddenInput) {
                  hiddenInput      = document.createElement('input');
                  hiddenInput.type = 'hidden';
                  hiddenInput.id   = 'hidden' + config?.search?.hiddenFormvalue;
                  hiddenInput.name = config?.search?.hiddenFormvalue;
                  const sc = document.getElementById(randomID)?.closest('.nexa-search-container');
                  if (sc) sc.appendChild(hiddenInput);
                }
                hiddenInput.value = id;
              }
              NXUI.id(randomID).val(value);
              _applySelectedData(config, formData, fieldName, id, myData, randomID);
              suggestionsDiv.innerHTML    = '';
              suggestionsDiv.style.display = 'none';
              suggestionsDiv.classList.remove('nexa-dropdown-above');
            });
          });
        } else {
          suggestionsDiv.innerHTML    = '<div class="nexa-suggestion-empty">Tidak ada hasil ditemukan</div>';
          suggestionsDiv.style.display = 'block';
          NXUI.id(randomID).val('');
        }
      });
    } catch (_) {}
  },

  // ── Suggestions CSS (injected once) ──────────────────────────────────────

  addSuggestionsStyles() {
    if (document.getElementById('nexa-suggestions-styles')) return;
    const style    = document.createElement('style');
    style.id       = 'nexa-suggestions-styles';
    style.textContent = `
      .nexa-search-container { position: relative; }
      .nexa-suggestions-dropdown {
        position: fixed; background: white; border: 1px solid #ddd;
        border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,.15);
        z-index: 2147483647 !important; display: none;
        max-height: 250px; overflow-y: auto;
        visibility: visible !important; opacity: 1 !important;
      }
      .nx-modal .nexa-suggestions-dropdown { position: absolute; z-index: 2147483647 !important; visibility: visible !important; opacity: 1 !important; }
      .nx-modal .nexa-search-container { position: relative; }
      .nexa-suggestions-dropdown.nexa-dropdown-above { box-shadow: 0 -4px 12px rgba(0,0,0,.15); border-radius: 4px 4px 0 0; }
      .nexa-suggestions-dropdown.nexa-dropdown-above .nexa-suggestion-item:first-child { border-radius: 4px 4px 0 0; }
      .nexa-suggestions-dropdown.nexa-dropdown-above .nexa-suggestion-item:last-child  { border-radius: 0; border-bottom: 1px solid #f0f0f0; }
      .nexa-suggestions-dropdown[style*="max-height"] { border-bottom: 1px solid #ddd; }
      .nexa-suggestions-dropdown[style*="max-height"] .nexa-suggestion-item:last-child { border-bottom: none; }
      .nexa-suggestions-list { list-style: none; margin: 0; padding: 0; }
      .nexa-suggestion-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background-color .2s; color: black; }
      .nexa-suggestion-item h4 { color: black !important; margin: 0; }
      .nexa-suggestion-item span { color: black !important; }
      .nexa-suggestion-item:hover { background-color: #f5f5f5; }
      .nexa-suggestion-item:last-child { border-bottom: none; }
      .nexa-suggestion-empty { padding: 8px 12px; color: #666; font-style: italic; text-align: center; }
    `;
    document.head.appendChild(style);
  },
};
