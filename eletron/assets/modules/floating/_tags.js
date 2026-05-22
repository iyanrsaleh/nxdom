// Modular tags input generator for NexaFloating
import { generateUniqueName } from './_helpers.js';

export function generateTagsInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue) {
	const fieldData = fieldConfig?.tags?.data;
	const allowClear = 'true';
	const styleAttr = 'width:100%';
	let optionsHTML = '';
	if (Array.isArray(fieldData) && fieldData.length > 0) {
		optionsHTML = fieldData.map(item => {
			const val = item.value || item.label || '';
			const label = item.label || item.value || '';
			const selected = setValue && (Array.isArray(setValue) ? setValue.includes(val) : String(setValue).split(',').includes(val)) ? 'selected' : '';
			return `<option value="${val}" ${selected}>${label}</option>`;
		}).join('\n');
	}
	if (setValue && typeof setValue === 'string' && (!optionsHTML || !optionsHTML.includes(`value="${setValue}"`))) {
		optionsHTML += `<option value="${setValue}" selected>${setValue}</option>`;
	}
	// Main select2 markup (no legacy NexaTags, no hidden input)
	const placeholderText = placeholder || 'Pilih tags';
	setTimeout(() => {
		const el = document.getElementById(`tags_${fieldName}`);
		if (el) {
			const opts = {
				placeholder: placeholderText,
				allowClear: true,
				width: '100%'
			};
			if (globalThis.NXUI && typeof globalThis.NXUI.initSelect2 === 'function') {
				globalThis.NXUI.initSelect2(`#tags_${fieldName}` , opts);
			} else if (typeof globalThis.$?.fn?.select2 === 'function') {
				globalThis.$(`#tags_${fieldName}`).select2(opts);
			}
		}
	}, 150);
	return `
		<select id="tags_${fieldName}" class="js-select2" name="${fieldName}" multiple required data-placeholder="${placeholderText}" data-allow-clear="${allowClear}" style="${styleAttr}">
			${optionsHTML}
		</select>
	`;
}
