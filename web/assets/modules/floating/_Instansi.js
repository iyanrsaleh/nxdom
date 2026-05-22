// Modular instansi input generator for NexaFloating
import { generateUniqueName } from './_helpers.js';

/**
 * generateInstansiInput
 * Output: Select2 single select (Level/Instansi) sesuai referensi form.js
 * @param {string} fieldName
 * @param {string} placeholder
 * @param {string} size
 * @param {boolean} isFloating
 * @param {object} fieldConfig
 * @param {object} formSettings
 * @param {string|undefined} setValue
 * @returns {string} HTML markup
 */
export function generateInstansiInput(fieldName, placeholder, size, isFloating, fieldConfig, formSettings, setValue) {
	console.log('fieldConfig:', fieldConfig);
	const fieldData = fieldConfig?.instansi?.data || [
		{ value: 'junior', label: 'Junior' },
		{ value: 'mid', label: 'Mid' },
		{ value: 'senior', label: 'Senior' },
		{ value: 'lead', label: 'Lead' }
	];
	const allowClear = 'true';
	const styleAttr = 'width:100%';
	let optionsHTML = '<option value=""></option>';
	if (Array.isArray(fieldData) && fieldData.length > 0) {
		optionsHTML += fieldData.map(item => {
			const val = item.value || item.label || '';
			const label = item.label || item.value || '';
			const selected = setValue && (Array.isArray(setValue) ? setValue.includes(val) : String(setValue).split(',').includes(val)) ? 'selected' : '';
			return `<option value="${val}" ${selected}>${label}</option>`;
		}).join('\n');
	}
	const placeholderText = placeholder || 'Pilih level';
	setTimeout(() => {
		const el = document.getElementById(`instansi_${fieldName}`);
		if (el) {
			const opts = {
				placeholder: placeholderText,
				allowClear: true,
				width: '100%'
			};
			if (globalThis.NXUI && typeof globalThis.NXUI.initSelect2 === 'function') {
				globalThis.NXUI.initSelect2(`#instansi_${fieldName}` , opts);
			} else if (typeof globalThis.$?.fn?.select2 === 'function') {
				globalThis.$(`#instansi_${fieldName}`).select2(opts);
			}
		}
	}, 150);
	return `
		<select id="instansi_${fieldName}" class="js-select2" name="${fieldName}" required data-placeholder="${placeholderText}" data-allow-clear="${allowClear}" style="${styleAttr}">
			${optionsHTML}
		</select>
	`;
}
