// _specialFields.js — Complex / async field generators mixin for NexaFloating
// Handles: textarea (rich editor), tags, maps, flag, search (content + modal)

import { setFlag }    from '../flag/NexaFlag.js';
import { generateUniqueName } from './_helpers.js';

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
    if (suggestionsDiv.parentNode !== domElement.parentNode) {
      domElement.parentNode.appendChild(suggestionsDiv);
    }
  }
  return true;
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

export const SpecialFieldsMixin = {

  // ── Textarea (rich editor) ─────────────────────────────────────────────────

  generateTextareaInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig) {
    const random        = generateUniqueName(fieldName);
    const limit         = Number(fieldConfig.limit);
    const hiddenInputId = `hidden_${random}`;
    const textareaInput = `textareaInput${random}_${fieldName}`;
    // Tambahkan required jika validation == 2
    const requiredAttr = (fieldConfig.validation === 2 || fieldConfig.validation === '2') ? ' required' : '';
    const hiddenInput   = `<textarea style="display:none;" id="${hiddenInputId}" name="${fieldName}"${requiredAttr}>${setValue || ''}</textarea>`;

    setTimeout(() => {
      try {
        const editor = new NXUI.Editor(`#${textareaInput}`, {
          height: `${fieldConfig.height || '150'}px`,
          placeholder: `Mulai mengetik  ${placeholder} di sini...`,
        });

        // Helper: Remove error if textarea is not empty
        function removeTextareaErrorIfFilled(content) {
          const plainText = content.replace(/<[^>]*>/g, '').trim();
          if (plainText.length > 0) {
            const errorMsg = document.getElementById(`errors_${fieldName}`);
            if (errorMsg) {
              errorMsg.style.display = 'none';
              errorMsg.textContent = '';
            }
            const hiddenInputEl = document.getElementById(hiddenInputId);
            if (hiddenInputEl) {
              hiddenInputEl.classList.remove('is-invalid');
              const formGroup = hiddenInputEl.closest('.form-group');
              if (formGroup) formGroup.classList.remove('error');
            }
          }
        }

        editor.onElementKeyup(`#${textareaInput}`, (content) => {
          const hiddenInputEl = document.getElementById(hiddenInputId);
          if (hiddenInputEl) {
            hiddenInputEl.value = content;
            hiddenInputEl.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenInputEl.dispatchEvent(new Event('change', { bubbles: true }));
            removeTextareaErrorIfFilled(content);
          }
        });

        const form = document.getElementById(hiddenInputId)?.closest('form');
        if (form) {
          form.addEventListener('submit', function(e) {
            const hiddenInputEl = document.getElementById(hiddenInputId);
            if (hiddenInputEl) {
              removeTextareaErrorIfFilled(hiddenInputEl.value);
            }
            // Tambahan: Reset editor dan textarea setelah submit sukses
            // (Pastikan ini hanya dijalankan jika submit benar-benar sukses, sesuaikan jika ada async/success callback)
            setTimeout(() => {
              if (editor && typeof editor.setValue === 'function') {
                editor.setValue(''); // Reset editor ke kosong
              }
              if (hiddenInputEl) hiddenInputEl.value = '';
            }, 200);
          }, true);
        }
      } catch (_) {}
    }, 100);

    return `
      ${hiddenInput}
      <div style="border: 1px solid #e0e0e0;" id="${textareaInput}"></div>
    `;
  },

  // ── Tags ──────────────────────────────────────────────────────────────────


  // ── Maps ──────────────────────────────────────────────────────────────────

  generateMapsInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue = '') {
    let valueString = '';
    const random    = generateUniqueName(fieldName);

    if (setValue) {
      if (typeof setValue === 'object') {
        if (setValue.lat !== undefined && setValue.lon !== undefined) {
          valueString = `${setValue.lat},${setValue.lon}`;
        } else if (setValue.latitude !== undefined && setValue.longitude !== undefined) {
          valueString = `${setValue.latitude},${setValue.longitude}`;
        } else if (setValue.coords) {
          const lat = setValue.coords.lat || setValue.coords.latitude;
          const lon = setValue.coords.lon || setValue.coords.longitude;
          if (lat !== undefined && lon !== undefined) valueString = `${lat},${lon}`;
        } else {
          valueString = String(setValue);
        }
      } else {
        valueString = String(setValue);
      }
    }

    const valueAttr = valueString ? `value="${valueString}"` : '';

    const getGeolocation = () => {
      if (!('geolocation' in navigator)) return;
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const coords = `${position.coords.latitude},${position.coords.longitude}`;
          const trySet = () => {
            const input = document.getElementById(random);
            if (input) input.value = coords;
            else setTimeout(trySet, 100);
          };
          trySet();
        },
        () => {},
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 },
      );
    };

    if (!valueString) {
      setTimeout(() => {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          getGeolocation();
        } else {
          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', getGeolocation);
          window.addEventListener('load', getGeolocation);
        }
      }, 100);
    }

    return `<input type="text" id="${random}" class="${size}" name="${fieldName}" ${valueAttr} />`;
  },

  // ── Flag ──────────────────────────────────────────────────────────────────

  async generateFlagInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue) {
    return await setFlag(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue);
  },

  // ── Search (content mode) ─────────────────────────────────────────────────

  generateSearchInput(fieldName, placeholder, size, isFloating, config, settings, setValue, formData) {
    const randomID        = generateUniqueName(fieldName);
    const placeholderAttr = `placeholder="${placeholder}"`;
    const valueAttr       = setValue ? `value="${setValue}"` : '';

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
      <div id="suggestions_${randomID}" class="nexa-suggestions-dropdown"></div>
    `;
  },

  // ── Search inner — content page ───────────────────────────────────────────

  async contentSearchInput(config, fieldName, randomID, formData) {
    try {
      const inputEl  = NXUI.id(randomID);
      const configEl = config.search;
      this.addSuggestionsStyles();

      inputEl.on('keyup', async function (e) {
        if (!e.target.value || e.target.value.trim() === '') {
          const div = document.getElementById(`suggestions_${randomID}`);
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

        const suggestionsDiv = document.getElementById(`suggestions_${randomID}`);
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
      console.log('formData:', formData);
      const inputEl  = NXUI.id(randomID);
      const configEl = config.search;
      this.addSuggestionsStyles();

      inputEl.on('blur', function () {
        setTimeout(() => {
          const div = document.getElementById(`suggestions_${randomID}`);
          if (div) div.style.display = 'none';
        }, 150);
      });

      document.addEventListener('click', function (e) {
        const div         = document.getElementById(`suggestions_${randomID}`);
        const inputElement = document.getElementById(randomID);
        if (div && inputElement && !div.contains(e.target) && !inputElement.contains(e.target)) {
          div.style.display = 'none';
        }
      });

      inputEl.on('keyup', async function (e) {
        if (!e.target.value || e.target.value.trim() === '') {
          const div = document.getElementById(`suggestions_${randomID}`);
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

        const suggestionsDiv = document.getElementById(`suggestions_${randomID}`);
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
