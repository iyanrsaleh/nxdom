// _mediaFields.js — File / color / range input generators mixin for NexaFloating
// Handles: color, range, file (including the large fileUpload DOM logic)

export const MediaFieldsMixin = {

  // ─── Color ──────────────────────────────────────────────────────────────────

  generateColorInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'color';
    const valueAttr = setValue ? `value="${setValue}"` : '';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="${size}" ${valueAttr} ${disabled} ${readonly} />`;
  },

  // ─── Range ──────────────────────────────────────────────────────────────────

  generateRangeInput(fieldName, placeholder, size, isFloating, setValue = '', fieldConfig = {}) {
    const type     = fieldConfig?.hiddenForm ? 'hidden' : 'range';
    const disabled  = fieldConfig?.disabled ? 'disabled' : '';
    const readonly  = fieldConfig?.readonly ? 'readonly' : '';
    const options   = this.getOptions(fieldName);
    let attributes  = '';

    if (options) {
      if (options.min   !== undefined) attributes += ` min="${options.min}"`;
      if (options.max   !== undefined) attributes += ` max="${options.max}"`;
      if (options.step  !== undefined) attributes += ` step="${options.step}"`;
      if (setValue) {
        attributes += ` value="${setValue}"`;
      } else if (options.value !== undefined) {
        attributes += ` value="${options.value}"`;
      }
    } else if (setValue) {
      attributes += ` value="${setValue}"`;
    }

    return `<input type="${type}" id="${fieldName}" name="${fieldName}" class="form-nexa-range" ${disabled} ${readonly}${attributes} />`;
  },

  // ─── File ───────────────────────────────────────────────────────────────────

  generateFileInput(fieldName, placeholder, size, isFloating, setValue) {
    const randomID = fieldName + Math.floor(Math.random() * 1000);
    const fileUploadConfig = this.formData.form?.[fieldName] || {};

    let acceptAttribute = fileUploadConfig.fieldAccept || '';
    if (acceptAttribute) {
      acceptAttribute = acceptAttribute
        .split(',')
        .map((ext) => {
          ext = ext.trim().toLowerCase();
          return ext.startsWith('.') ? ext : '.' + ext;
        })
        .join(',');
    }

    const multipleAttribute = fileUploadConfig.fieldMultiple ? 'multiple' : '';

    const fileHTML = `
    <div class="nx-media nx-fileupload" id="fileUpload-${randomID}">
      <img
        style="height: 50px; width: 50px"
        src="${window.NEXA.url}/assets/images/500px.png"
        alt="preview"
        class="nx-media-img"
        id="preview-image-${randomID}"
      />
      <i
        id="fa-icon-${randomID}"
        class="fas fa-file"
        style="
          display: none;
          height: 50px;
          width: 50px;
          font-size: 32px;
          color: #666;
          align-items: center;
          justify-content: center;
        "
      ></i>
      <input
        type="file"
        id="${randomID}"
        name="${fieldName}"
        class="form-control"
        accept="${acceptAttribute}"
        ${multipleAttribute}
        data-max-size="${fileUploadConfig.fileUploadSize || '5MB'}"
        data-max-files="${fileUploadConfig.fieldMultiple ? '5' : '1'}"
      />
      <div class="nx-media-body">
        <h5>${fileUploadConfig.placeholder || placeholder}</h5>
        <p id="nx-file-type-${randomID}">Maksimal ${fileUploadConfig.fileUploadSize || '5MB'}, tipe: ${acceptAttribute || 'semua'}</p>
        <small id="file-name-${randomID}" style="color: #666"></small>
      </div>
    </div>`;

    setTimeout(() => {
      this.fileUpload(fileUploadConfig, fieldName, setValue, randomID);
    }, 0);

    return fileHTML;
  },

  fileUpload(data = {}, fieldName, setValue, randomID) {
    const forImages    = window.NEXA.url + '/assets/images/500px.png';
    const fileInput    = document.getElementById(randomID);
    const previewImage = document.getElementById(`preview-image-${randomID}`);
    const faIcon       = document.getElementById(`fa-icon-${randomID}`);
    const fileName     = document.getElementById(`file-name-${randomID}`);
    const fileTypeDisplay = document.getElementById(`nx-file-type-${randomID}`);
    const defaultSrc   = forImages;

    let originalSetValue  = setValue;
    let originalFileName  = '';
    let originalExtension = '';
    let originalIsImage   = false;

    // ── Handle existing file path from setValue ────────────────────────────
    if (setValue && typeof setValue === 'string' && setValue.trim() !== '') {
      const pathParts       = setValue.split('/');
      const fileNameFromPath = pathParts[pathParts.length - 1];
      const extension       = fileNameFromPath.split('.').pop().toLowerCase();

      originalFileName  = fileNameFromPath;
      originalExtension = extension;

      if (fileName) fileName.textContent = fileNameFromPath;

      const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
      originalIsImage = imageExtensions.includes(extension);

      if (originalIsImage) {
        if (previewImage) {
          previewImage.src = window.NEXA.url + '/' + setValue;
          previewImage.alt = fileNameFromPath;
          previewImage.style.display = 'block';
        }
        if (faIcon) faIcon.style.display = 'none';
      } else {
        const fileIcons = {
          pdf: 'fas fa-file-pdf', doc: 'fas fa-file-word', docx: 'fas fa-file-word',
          xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel',
          ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint',
          txt: 'fas fa-file-alt', rtf: 'fas fa-file-alt',
          zip: 'fas fa-file-archive', rar: 'fas fa-file-archive', '7z': 'fas fa-file-archive',
          mp3: 'fas fa-file-audio', wav: 'fas fa-file-audio',
          mp4: 'fas fa-file-video', avi: 'fas fa-file-video', mov: 'fas fa-file-video',
        };
        const fileColors = {
          pdf: '#F44336', doc: '#2196F3', docx: '#2196F3',
          xls: '#4CAF50', xlsx: '#4CAF50', ppt: '#FF9800', pptx: '#FF9800',
          txt: '#9E9E9E', rtf: '#9E9E9E', zip: '#795548', rar: '#795548',
          '7z': '#795548', mp3: '#E91E63', wav: '#E91E63',
          mp4: '#9C27B0', avi: '#9C27B0', mov: '#9C27B0',
        };
        if (previewImage) previewImage.style.display = 'none';
        if (faIcon) {
          faIcon.style.display = 'flex';
          faIcon.className     = fileIcons[extension] || 'fas fa-file';
          faIcon.style.color   = fileColors[extension] || '#666';
        }
      }
    } else {
      if (previewImage) { previewImage.src = forImages; previewImage.style.display = 'block'; }
      if (faIcon) faIcon.style.display = 'none';
    }

    // ── Accept attribute ───────────────────────────────────────────────────
    if (data && typeof data === 'object' && data.fieldAccept) {
      if (fileInput) {
        const normalizedAccept = data.fieldAccept
          .split(',')
          .map((ext) => { ext = ext.trim().toLowerCase(); return ext.startsWith('.') ? ext : '.' + ext; })
          .join(',');
        fileInput.setAttribute('accept', normalizedAccept);
      }
      if (fileTypeDisplay) {
        fileTypeDisplay.textContent = `Maksimal ${data.fileUploadSize || '5MB'}, tipe: ${data.fieldAccept}`;
      }
    } else {
      if (fileTypeDisplay) {
        fileTypeDisplay.textContent = `Maksimal ${data.fileUploadSize || '5MB'}, tipe: semua`;
      }
    }

    // ── File-type validator ────────────────────────────────────────────────
    function validateFileType(file) {
      if (!data || typeof data !== 'object' || !data.fieldAccept || typeof data.fieldAccept !== 'string') {
        return true;
      }
      try {
        const allowedExtensions = data.fieldAccept.split(',').map((ext) => ext.trim().toLowerCase());
        const fileExtension         = file.name.toLowerCase().split('.').pop();
        const fileExtensionWithDot  = '.' + fileExtension;
        return allowedExtensions.includes(fileExtension) || allowedExtensions.includes(fileExtensionWithDot);
      } catch (_) {
        return true;
      }
    }

    // ── Icon / color maps ──────────────────────────────────────────────────
    const fileIcons = {
      jpg: 'fas fa-file-image', jpeg: 'fas fa-file-image', png: 'fas fa-file-image',
      gif: 'fas fa-file-image', webp: 'fas fa-file-image', bmp: 'fas fa-file-image', svg: 'fas fa-file-image',
      pdf: 'fas fa-file-pdf', doc: 'fas fa-file-word', docx: 'fas fa-file-word',
      xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel',
      ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint',
      txt: 'fas fa-file-alt', rtf: 'fas fa-file-alt',
      xml: 'fas fa-file-code', yaml: 'fas fa-file-code', yml: 'fas fa-file-code',
      json: 'fas fa-file-code', csv: 'fas fa-file-csv',
      zip: 'fas fa-file-archive', rar: 'fas fa-file-archive', '7z': 'fas fa-file-archive',
      tar: 'fas fa-file-archive', gz: 'fas fa-file-archive',
      mp3: 'fas fa-file-audio', wav: 'fas fa-file-audio', flac: 'fas fa-file-audio',
      aac: 'fas fa-file-audio', ogg: 'fas fa-file-audio',
      mp4: 'fas fa-file-video', avi: 'fas fa-file-video', mov: 'fas fa-file-video',
      wmv: 'fas fa-file-video', flv: 'fas fa-file-video', mkv: 'fas fa-file-video', webm: 'fas fa-file-video',
    };

    const fileColors = {
      jpg: '#4CAF50', jpeg: '#4CAF50', png: '#4CAF50', gif: '#4CAF50',
      webp: '#4CAF50', bmp: '#4CAF50', svg: '#4CAF50',
      pdf: '#F44336', doc: '#2196F3', docx: '#2196F3',
      xls: '#4CAF50', xlsx: '#4CAF50', ppt: '#FF9800', pptx: '#FF9800',
      txt: '#9E9E9E', rtf: '#9E9E9E',
      xml: '#FF5722', yaml: '#FF5722', yml: '#FF5722', json: '#FFC107', csv: '#4CAF50',
      zip: '#795548', rar: '#795548', '7z': '#795548', tar: '#795548', gz: '#795548',
      mp3: '#E91E63', wav: '#E91E63', flac: '#E91E63', aac: '#E91E63', ogg: '#E91E63',
      mp4: '#9C27B0', avi: '#9C27B0', mov: '#9C27B0', wmv: '#9C27B0',
      flv: '#9C27B0', mkv: '#9C27B0', webm: '#9C27B0',
    };

    function showImage() {
      if (previewImage) previewImage.style.display = 'block';
      if (faIcon)       faIcon.style.display       = 'none';
    }

    function showIcon(iconClass, extension) {
      if (previewImage) previewImage.style.display = 'none';
      if (faIcon) {
        faIcon.style.display = 'flex';
        faIcon.className     = iconClass;
        faIcon.style.color   = fileColors[extension] || '#666';
      }
    }

    if (!fileInput) return;

    // ── Change event ───────────────────────────────────────────────────────
    fileInput.addEventListener('change', function (event) {
      const file = event.target.files[0];
      if (file) {
        if (!validateFileType(file)) {
          if (fileTypeDisplay) {
            const acceptTypes = data && data.fieldAccept ? data.fieldAccept : 'specified';
            fileTypeDisplay.textContent = `❌ File type not allowed. Only ${acceptTypes} files are permitted.`;
            fileTypeDisplay.style.color = '#F44336';
          }
          fileInput.value = '';
          if (fileName)     fileName.textContent = '';
          if (previewImage) { previewImage.src = defaultSrc; previewImage.alt = 'preview'; }
          showImage();
          setTimeout(() => {
            if (fileTypeDisplay) {
              const acceptTypes = data && data.fieldAccept ? data.fieldAccept : 'semua';
              fileTypeDisplay.textContent = `Maksimal ${data.fileUploadSize || '5MB'}, tipe: ${acceptTypes}`;
              fileTypeDisplay.style.color = '';
            }
          }, 3000);
          return;
        }

        if (fileName) fileName.textContent = file.name;
        const extension = file.name.split('.').pop().toLowerCase();

        if (file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function (e) {
            if (previewImage) { previewImage.src = e.target.result; previewImage.alt = file.name; }
            showImage();
          };
          reader.readAsDataURL(file);
        } else if (fileIcons[extension]) {
          showIcon(fileIcons[extension], extension);
        } else {
          showIcon('fas fa-file', 'default');
        }
      } else {
        // No file selected — restore original setValue preview or default
        if (originalSetValue && originalSetValue.trim() !== '') {
          if (fileName) fileName.textContent = originalFileName;
          if (originalIsImage) {
            if (previewImage) {
              previewImage.src = window.NEXA.url + '/' + originalSetValue;
              previewImage.alt = originalFileName;
              previewImage.style.display = 'block';
            }
            if (faIcon) faIcon.style.display = 'none';
          } else {
            const restoreIcons  = { pdf: 'fas fa-file-pdf', doc: 'fas fa-file-word', docx: 'fas fa-file-word', xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel', ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint', txt: 'fas fa-file-alt', rtf: 'fas fa-file-alt', zip: 'fas fa-file-archive', rar: 'fas fa-file-archive', '7z': 'fas fa-file-archive', mp3: 'fas fa-file-audio', wav: 'fas fa-file-audio', mp4: 'fas fa-file-video', avi: 'fas fa-file-video', mov: 'fas fa-file-video' };
            const restoreColors = { pdf: '#F44336', doc: '#2196F3', docx: '#2196F3', xls: '#4CAF50', xlsx: '#4CAF50', ppt: '#FF9800', pptx: '#FF9800', txt: '#9E9E9E', rtf: '#9E9E9E', zip: '#795548', rar: '#795548', '7z': '#795548', mp3: '#E91E63', wav: '#E91E63', mp4: '#9C27B0', avi: '#9C27B0', mov: '#9C27B0' };
            if (previewImage) previewImage.style.display = 'none';
            if (faIcon) {
              faIcon.style.display = 'flex';
              faIcon.className     = restoreIcons[originalExtension]  || 'fas fa-file';
              faIcon.style.color   = restoreColors[originalExtension] || '#666';
            }
          }
        } else {
          if (previewImage) { previewImage.src = defaultSrc; previewImage.alt = 'preview'; }
          if (fileName) fileName.textContent = '';
          showImage();
        }
      }
    });

    // Click on preview → open file picker
    if (previewImage) previewImage.addEventListener('click', () => { if (fileInput) fileInput.click(); });
    if (faIcon)       faIcon.addEventListener('click',       () => { if (fileInput) fileInput.click(); });
  },
};
