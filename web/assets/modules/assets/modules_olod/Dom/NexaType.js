export function fileType(filePath, targetSelector) {
  const idSelector = targetSelector.replace(/^#|#$/g, '');

  // Safety checks for input parameters
  if (!filePath || typeof filePath !== "string") {

    return null;
  }

  if (!targetSelector || typeof targetSelector !== "string") {
  
    return null;
  }

  // Get target element
  const targetElement = document.querySelector(targetSelector);
  if (!targetElement) {
 
    return null;
  }

  try {
    // Extract filename and extension from path
    const fileName = filePath.split("/").pop();
    const hasExtension = fileName.includes(".");
    const extension = hasExtension
      ? fileName.split(".").pop().toLowerCase()
      : "";


    // File type to FontAwesome icon mapping
    const fileIcons = {
      // Images
      jpg: "fas fa-file-image",
      jpeg: "fas fa-file-image",
      png: "fas fa-file-image",
      gif: "fas fa-file-image",
      webp: "fas fa-file-image",
      bmp: "fas fa-file-image",
      svg: "fas fa-file-image",

      // Documents
      pdf: "fas fa-file-pdf",
      doc: "fas fa-file-word",
      docx: "fas fa-file-word",
      xls: "fas fa-file-excel",
      xlsx: "fas fa-file-excel",
      ppt: "fas fa-file-powerpoint",
      pptx: "fas fa-file-powerpoint",
      txt: "fas fa-file-alt",
      rtf: "fas fa-file-alt",

      // Data & Config
      xml: "fas fa-file-code",
      yaml: "fas fa-file-code",
      yml: "fas fa-file-code",
      json: "fas fa-file-code",
      csv: "fas fa-file-csv",
      text: "fas fa-file",

      // Archives
      zip: "fas fa-file-archive",
      rar: "fas fa-file-archive",
      "7z": "fas fa-file-archive",
      tar: "fas fa-file-archive",
      gz: "fas fa-file-archive",

      // Audio
      mp3: "fas fa-file-audio",
      wav: "fas fa-file-audio",
      flac: "fas fa-file-audio",
      aac: "fas fa-file-audio",
      ogg: "fas fa-file-audio",

      // Video
      mp4: "fas fa-file-video",
      avi: "fas fa-file-video",
      mov: "fas fa-file-video",
      wmv: "fas fa-file-video",
      flv: "fas fa-file-video",
      mkv: "fas fa-file-video",
      webm: "fas fa-file-video",
    };

    // Color mapping for file types
    const fileColors = {
      // Images
      jpg: "#4CAF50",
      jpeg: "#4CAF50",
      png: "#4CAF50",
      gif: "#4CAF50",
      webp: "#4CAF50",
      bmp: "#4CAF50",
      svg: "#4CAF50",

      // Documents
      pdf: "#F44336",
      doc: "#2196F3",
      docx: "#2196F3",
      xls: "#4CAF50",
      xlsx: "#4CAF50",
      ppt: "#FF9800",
      pptx: "#FF9800",
      txt: "#9E9E9E",
      rtf: "#9E9E9E",

      // Data & Config
      xml: "#FF5722",
      yaml: "#FF5722",
      yml: "#FF5722",
      json: "#FFC107",
      csv: "#4CAF50",
      text: "#666",

      // Archives
      zip: "#795548",
      rar: "#795548",
      "7z": "#795548",
      tar: "#795548",
      gz: "#795548",

      // Audio
      mp3: "#E91E63",
      wav: "#E91E63",
      flac: "#E91E63",
      aac: "#E91E63",
      ogg: "#E91E63",

      // Video
      mp4: "#9C27B0",
      avi: "#9C27B0",
      mov: "#9C27B0",
      wmv: "#9C27B0",
      flv: "#9C27B0",
      mkv: "#9C27B0",
      webm: "#9C27B0",
    };

    // Get icon and color for this file type
    const iconClass = hasExtension
      ? fileIcons[extension] || "fas fa-file"
      : "fas fa-upload";
    const iconColor = hasExtension
      ? fileColors[extension] || "#666"
      : "#2196F3";

    // Check if it's an image file
    const imageExtensions = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"];
    const isImage = imageExtensions.includes(extension);
    const isPDF = extension === "pdf";

    // Generate simplified HTML structure like the example
    let htmlContent = "";
    if (!hasExtension) {
      // For text without extension, show upload icon (placeholder for file upload)
      htmlContent = `
      <img
        style="height: 50px; width: 50px; display: none;"
        src="${window.NEXA.url}/assets/images/500px.png"
        alt="File preview"
        class="nx-media-img"
        id="preview-image"
      />
    `;
    } else if (isImage) {

      // For images, show the actual image preview
      htmlContent = `
      <img
        id="box_${idSelector}"
        style="height: 30px; width: 30px; border-radius: 4px; object-fit: cover;cursor: pointer;"
        src="${window.NEXA.url}${filePath}"
        alt="${fileName}"
        class="nexa-lightbox"
      />
    `;
    } else {
      // For non-images, show icon
      // Special handling for PDF files - make them clickable to open modal preview
      if (isPDF) {
        const pdfPath = `${window.NEXA.url}${filePath}`;
        htmlContent = `
      <i
        id="fa-icon-pdf-${idSelector}"
        class="${iconClass}"
        style="
          display: flex;
          height: 30px;
          width: 30px;
          font-size: 30px;
          color: ${iconColor};
          align-items: center;
          justify-content: center;
          border-radius: 4px;
          background: #f8f9fa;
          cursor: pointer;
        "
      ></i>
    `;
      } else {
        htmlContent = `
      <i
        id="fa-icon"
        class="${iconClass}"
        style="
          display: flex;
          height: 30px;
          width: 30px;
          font-size: 30px;
          color: ${iconColor};
          align-items: center;
          justify-content: center;
          border-radius: 4px;
          background: #f8f9fa;
        "
      ></i>
    `;
      }
    }

    // Insert HTML into target element
    targetElement.innerHTML = htmlContent;
    
    // Add click event listener for PDF files
    if (isPDF) {
      const pdfIcon = document.getElementById(`fa-icon-pdf-${idSelector}`);
      if (pdfIcon) {
        const pdfPath = `${window.NEXA.url}${filePath}`;
        pdfIcon.addEventListener('click', () => {
          window.openModalPDF(idSelector, pdfPath);
        });
      }
    }
    
    const allLightboxes = NXUI.NexaLightbox.initAll('.nexa-lightbox');
    // Return info object for potential chaining
    return {
      fileName,
      extension,
      hasExtension,
      iconClass,
      iconColor,
      isImage,
      isPDF,
      isText: !hasExtension,
      targetElement,
    };
  } catch (error) {
    // Create fallback upload icon when everything fails
    targetElement.innerHTML = `
      <i class="fas fa-file" style="
        display: flex;
        height: 30px;
        width: 30px;
        font-size: 30px;
        color: #2196F3;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        background: #f8f9fa;
      "></i>
    `;

    return {
      fileName: filePath,
      extension: "",
      hasExtension: false,
      iconClass: "fas fa-upload",
      iconColor: "#2196F3",
      isImage: false,
      isText: true,
      targetElement,
    };
  }
}

window.openModalPDF = async function (idSelector, path) {
    const modalID="pdf"+idSelector;
      window.NXUI.modalHTML({
        elementById: modalID,
        styleClass: "w-700px",
        paddingTop:'90px',
        bodyPadding: "0px",
        minimize: true,
        label: `Preview`,
        onclick: false,
        content: `<iframe src="${path}" style="width:100%;height:600px;border:none;"></iframe>`,
       });
       
       window.NXUI.nexaModal.open(modalID);
         window.NXUI.id("body_"+modalID)
          .setStyle("padding", "0px")
          .setStyle("overflow-y", "hidden")
}