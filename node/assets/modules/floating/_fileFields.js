
// Fungsi untuk mendapatkan nama ikon dari ekstensi file
function getFileIconByExt(filename) {
	if (!filename) return 'default';
	const ext = filename.split('.').pop().toLowerCase();
	const supported = [
		'html','css','scss','sass','less','js','mjs','cjs','jsx','ts','tsx','vue','svelte','astro',
		'php','py','rb','java','kt','swift','go','rs','c','cpp','h','hpp','cs','dart','lua','r','scala','clj','ex','erl','hs','pl','nim','zig','d','elm','fs','ml','v','asm','wasm',
		'json','jsonc','xml','yaml','yml','toml','ini','env','sql','graphql','gql','proto','csv',
		'sh','bash','zsh','fish','bat','cmd','ps1',
		'md','mdx','txt','rtf','pdf','doc','docx','xls','xlsx','ppt','pptx','tex','latex',
		'jpg','jpeg','png','gif','webp','bmp','tiff','ico','svg',
		'zip','rar','tar','gz','7z','bz2','xz',
		'mp4','avi','mkv','mov','webm','mp3','wav','ogg','flac','aac',
		'ttf','otf','woff','woff2','eot',
		'dockerfile','dockerignore','makefile','cmake','gradle','nginx','htaccess','terraform','tf',
		'gitignore','gitattributes','npmignore','eslintrc','prettierrc','editorconfig','tsconfig','package','lock','license','log','exe','dll','jar','key','pem','cert','crt',
		'angular','nest','next','nuxt','prisma','storybook','jest','vitest','webpack','vite','rollup','firebase',
		'folder','folder-open','folder-src','folder-src-open','folder-dist','folder-dist-open','folder-node','folder-node-open','folder-assets','folder-images','folder-css','folder-js','folder-components','folder-config','folder-public','folder-test','folder-docs','folder-lib','folder-api','folder-database','folder-views','folder-routes','folder-controller','folder-template','folder-hooks','folder-middleware','folder-utils','folder-scripts','folder-docker','folder-git','folder-github','folder-vendor','folder-migrations','folder-fonts','folder-upload','folder-download','folder-log','folder-temp','folder-trash','folder-private'
	];
	return supported.includes(ext) ? ext : 'default';
}

// Fungsi global untuk update ikon file preview
window.updateFileIconPreview = function(input, iconSpanId, acceptList, warnId) {
	const file = input.files && input.files[0];
	const iconSpan = document.getElementById(iconSpanId);
	const warnSpan = warnId ? document.getElementById(warnId) : null;
	if (!iconSpan) return;
	let ext = 'default';
	let isValid = true;
	let warnMsg = '';
	// Ambil batas ukuran dari atribut data-max-size jika ada
	let maxSize = 0;
	if (input && input.hasAttribute('data-max-size')) {
		const maxStr = input.getAttribute('data-max-size');
		// Parsing "10MB", "2M", "500KB", "10000" (bytes)
		const match = maxStr.match(/(\d+(?:\.\d+)?)([a-zA-Z]*)/);
		if (match) {
			let val = parseFloat(match[1]);
			let unit = match[2].toUpperCase();
			if (unit === 'MB') maxSize = val * 1024 * 1024;
			else if (unit === 'M') maxSize = val * 1024 * 1024;
			else if (unit === 'KB') maxSize = val * 1024;
			else if (unit === 'B' || unit === '') maxSize = val;
			else if (unit === 'GB') maxSize = val * 1024 * 1024 * 1024;
		}
	}
	if (file && file.name) {
		ext = getFileIconByExt(file.name);
		if (acceptList && acceptList.length > 0) {
			const allowed = acceptList.map(e => e.replace('.', '').toLowerCase());
			const fileExt = file.name.split('.').pop().toLowerCase();
			isValid = allowed.includes(fileExt);
			if (!isValid) warnMsg = 'Ekstensi file tidak diizinkan!';
		}
		if (maxSize > 0 && file.size > maxSize) {
			isValid = false;
			warnMsg = 'Ukuran file melebihi batas (' + input.getAttribute('data-max-size') + ')!';
		}
	}
	iconSpan.className = 'icon icon-' + ext;
	if (warnSpan) {
        warnSpan.textContent = warnMsg;
        warnSpan.style.color = warnMsg ? 'red' : '';
        warnSpan.style.display = warnMsg ? 'inline' : 'none';
	}
}


// Fungsi untuk update label file input (hanya nama file/hint, tanpa warning)
window.fileUploadUpdate = function(input, labelId, dropId) {
	const label = document.getElementById(labelId);
	const drop = document.getElementById(dropId);
	if (!label) return;
	let fileName = '';
	if (input && input.files && input.files.length > 0) {
		fileName = input.files[0].name;
	}
	// Ambil hint dari atribut data-hint jika ada
	let hint = label.getAttribute('data-hint') || '';
	label.textContent = fileName ? fileName : hint;
	if (drop) {
		if (fileName) drop.classList.add('has-file');
		else drop.classList.remove('has-file');
	}
};

/** Kosongkan satu input file NexaFloating dan pulihkan label / ikon / kelas drop (setelah upload sukses). */
window.resetNexaFileUploadUI = function(input) {
	if (!input || input.type !== 'file') return;
	try {
		input.value = '';
	} catch (_) {}
	const baseId = input.id || input.name;
	if (!baseId) return;
	const labelId = `${baseId}-label`;
	const dropId = `${baseId}-drop`;
	const iconSpanId = `${baseId}-icon`;
	const warnId = `${baseId}-warn`;
	let acceptList = [];
	try {
		const raw = input.getAttribute('data-accept-list');
		if (raw) acceptList = JSON.parse(raw.replace(/&quot;/g, '"'));
	} catch (_) {}
	const drop = document.getElementById(dropId);
	if (drop) drop.classList.remove('has-file');
	if (typeof globalThis.fileUploadUpdate === 'function') {
		globalThis.fileUploadUpdate(input, labelId, dropId);
	}
	if (typeof globalThis.updateFileIconPreview === 'function') {
		globalThis.updateFileIconPreview(input, iconSpanId, acceptList, warnId);
	}
};

window.resetNexaFileUploadsInContainer = function(root) {
	if (!root || typeof root.querySelectorAll !== 'function') return;
	root.querySelectorAll('input[type="file"]').forEach((inp) => {
		window.resetNexaFileUploadUI(inp);
	});
};

export function generateFileInput(fieldName, iconType, formId, size, isFloating, fieldConfig, setValue) {

	// Use fieldName as id for accessibility and error anchor consistency
	const inputId = fieldName;
	const dropId = `${fieldName}-drop`;
	const labelId = `${fieldName}-label`;
	const iconSpanId = `${inputId}-icon`;
	const warnId = `${inputId}-warn`;
	// Siapkan daftar ekstensi dari fieldAccept
	let acceptList = (fieldConfig.fieldAccept || '').split(',').map(e => e.trim()).filter(Boolean);
	if (!Array.isArray(acceptList) || acceptList.length === 0) acceptList = [];
	// Escape JSON agar selalu valid di atribut HTML (pakai single quote di luar, double quote di dalam)
	const acceptListStr = JSON.stringify(acceptList).replace(/"/g, '&quot;');
	const maxSizeAttr = fieldConfig.fileUploadSize ? ` data-max-size='${fieldConfig.fileUploadSize}'` : '';
	// Simpan hint di atribut data-hint pada label
	const hintText = `${fieldConfig.fieldAccept ? fieldConfig.fieldAccept + ' ' : ''}${fieldConfig.fileUploadSize ? 'maksimal ' + fieldConfig.fileUploadSize : ''}`.trim();
	// Only add required if validation == 2 or '2'
	const requiredAttr = (fieldConfig.validation === 2 || fieldConfig.validation === '2') ? ' required' : '';
	return `
		<label for='${inputId}' class='file-upload-area' id='${dropId}'>
			<input id='${inputId}' name='${fieldName}' type='file' style='display:none' onchange='fileUploadUpdate(this, "${labelId}", "${dropId}"); updateFileIconPreview(this, "${iconSpanId}", JSON.parse(this.getAttribute("data-accept-list")), "${warnId}")' data-accept-list="${acceptListStr}"${maxSizeAttr}${requiredAttr}>
			<span class='file-upload-icon'>
				<span id='${iconSpanId}' class='icon icon-default' style='width:40px;height:40px;display:inline-block;'></span>
			</span>
			<span class='file-upload-text'><strong>Klik untuk memilih file</strong> atau seret &amp; lepas di sini</span>
			<span class='file-upload-hint' id='${labelId}' data-hint='${hintText}'>${hintText}</span>
			<span id='${warnId}' style='display:none;font-size:13px;margin-top:2px;'></span>
		</label>
	`;
}
