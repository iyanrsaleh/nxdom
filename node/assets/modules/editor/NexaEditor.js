export class NexaEditor {
  constructor(selector, options = {}) {
    this.element =
      typeof selector === "string"
        ? document.querySelector(selector)
        : selector;
    this.options = {
      height: "300px",
      toolbar: [
        "bold",
        "italic",
        "underline",
        "formatselect",
        "bullist",
        "numlist",
        "link",
        "image",
        "undo",
        "redo",
      ],
      formats: ["p", "h1", "h2", "h3", "h4", "h5", "h6"],
      placeholder: "Start typing...",
      input: null, // Target input element to sync content
      ...options,
    };

    this.init();
  }

  init() {
    if (!this.element) {
      console.error("NexaEditor: Element not found");
      return;
    }

    // Initialize target input if provided
    this.targetInput = null;
    if (this.options.input) {
      this.targetInput =
        typeof this.options.input === "string"
          ? document.querySelector(this.options.input)
          : this.options.input;

      if (!this.targetInput) {
        console.warn(
          "NexaEditor: Target input element not found:",
          this.options.input
        );
      }
    }

    this.createEditor();
    this.createToolbar();
    this.bindEvents();
  }

  createEditor() {
    // Create wrapper
    this.wrapper = document.createElement("div");
    this.wrapper.className = "nexa-editor-wrapper";

    // Create toolbar container
    this.toolbarContainer = document.createElement("div");
    this.toolbarContainer.className = "nexa-editor-toolbar";

    // Create editor container
    this.editorContainer = document.createElement("div");
    this.editorContainer.className = "nexa-editor-content";
    this.editorContainer.contentEditable = true;
    this.editorContainer.style.height = this.options.height;
    this.editorContainer.style.maxHeight = "none"; // Remove max-height limitation
    this.editorContainer.innerHTML =
      this.element.innerHTML || `<p>${this.options.placeholder}</p>`;

    // Replace original element
    this.element.parentNode.insertBefore(this.wrapper, this.element);
    this.wrapper.appendChild(this.toolbarContainer);
    this.wrapper.appendChild(this.editorContainer);
    this.element.style.display = "none";
  }

  createToolbar() {
    const toolbar = this.options.toolbar;

    toolbar.forEach((item) => {
      if (item === "|") {
        const separator = document.createElement("span");
        separator.className = "nexa-editor-separator";
        separator.innerHTML = "|";
        this.toolbarContainer.appendChild(separator);
      } else if (item === "formatselect") {
        this.createFormatSelect();
      } else {
        this.createToolbarButton(item);
      }
    });

    // Tidak perlu inisialisasi feather.replace() karena sudah pakai inline SVG
  }

  createToolbarButton(command) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "nexa-editor-btn";
    button.dataset.command = command;

    const icons = {
      bold: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 0 8H6zm0 8h9a4 4 0 0 1 0 8H6z"/></svg>',
      italic: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>',
      underline: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" y1="20" x2="20" y2="20"/></svg>',
      bullist: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
      numlist: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>',
      link: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 1 7 7l-1.5 1.5a5 5 0 0 1-7-7"/><path d="M14 11a5 5 0 0 0-7-7L5.5 3.5a5 5 0 0 0 7 7"/></svg>',
      image: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
      undo: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14H5v-4"/><path d="M20 20a9 9 0 0 0-15-7.5L5 10"/></svg>',
      redo: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14h4v-4"/><path d="M4 20a9 9 0 0 1 15-7.5L19 10"/></svg>',
    };

    button.innerHTML = icons[command] || command;
    button.title = command.charAt(0).toUpperCase() + command.slice(1);

    button.addEventListener("click", (e) => {
      e.preventDefault();
      this.execCommand(command);
    });

    this.toolbarContainer.appendChild(button);
  }

  createFormatSelect() {
    const select = document.createElement("select");
    select.className = "nexa-editor-format-select";

    // Add default option
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "Format";
    defaultOption.selected = true;
    select.appendChild(defaultOption);

    this.options.formats.forEach((format) => {
      const option = document.createElement("option");
      option.value = format;
      option.textContent = format.toUpperCase();
      select.appendChild(option);
    });

    select.addEventListener("change", (e) => {
      if (e.target.value && e.target.value !== "") {
        this.formatBlock(e.target.value);
      }
      // Reset to default option
      e.target.selectedIndex = 0;
    });

    this.toolbarContainer.appendChild(select);
  }

  execCommand(command) {
    this.editorContainer.focus();

    switch (command) {
      case "bold":
        document.execCommand("bold", false, null);
        break;
      case "italic":
        document.execCommand("italic", false, null);
        break;
      case "underline":
        document.execCommand("underline", false, null);
        break;
      case "bullist":
        document.execCommand("insertUnorderedList", false, null);
        break;
      case "numlist":
        document.execCommand("insertOrderedList", false, null);
        break;
      case "link":
        this.createLink();
        break;
      case "image":
        this.insertImage();
        break;
      case "undo":
        document.execCommand("undo", false, null);
        break;
      case "redo":
        document.execCommand("redo", false, null);
        break;
    }

    this.updateOriginalElement();
  }

  formatBlock(tag) {
    this.editorContainer.focus();
    document.execCommand("formatBlock", false, tag);
    this.updateOriginalElement();
  }

  createLink() {
    const url = prompt("Enter URL:");
    if (url) {
      document.execCommand("createLink", false, url);
      this.updateOriginalElement();
    }
  }

  insertImage() {
    const url = prompt("Enter image URL:");
    if (url) {
      document.execCommand("insertImage", false, url);
      this.updateOriginalElement();
    }
  }

  bindEvents() {
    // Update original element when content changes
    this.editorContainer.addEventListener("input", () => {
      this.updateOriginalElement();
    });

    // Handle paste events
    this.editorContainer.addEventListener("paste", (e) => {
      e.preventDefault();

      // Try to get HTML content first, fallback to plain text
      let content = e.clipboardData.getData("text/html");
      if (!content) {
        content = e.clipboardData.getData("text/plain");
      }

      // Check if content looks like HTML
      if (content.includes("<") && content.includes(">")) {
        // Insert HTML content directly
        document.execCommand("insertHTML", false, content);
      } else {
        // Insert as plain text
        document.execCommand("insertText", false, content);
      }
    });

    // Handle placeholder
    this.editorContainer.addEventListener("focus", () => {
      if (
        this.editorContainer.innerHTML === `<p>${this.options.placeholder}</p>`
      ) {
        this.editorContainer.innerHTML = "<p><br></p>";
      }
    });

    this.editorContainer.addEventListener("blur", () => {
      if (
        this.editorContainer.innerHTML === "<p><br></p>" ||
        this.editorContainer.innerHTML === ""
      ) {
        this.editorContainer.innerHTML = `<p>${this.options.placeholder}</p>`;
      }
    });
  }

  updateOriginalElement() {
    this.element.innerHTML = this.editorContainer.innerHTML;

    // Sync to target input if specified
    this.syncToInput();

    // Trigger change event
    const event = new Event("change", { bubbles: true });
    this.element.dispatchEvent(event);
  }

  // Method to sync editor content to target input
  syncToInput() {
    if (this.targetInput) {
      // Get HTML content (same as getContent())
      const textContent = this.getContent();

      // Update the target input value
      this.targetInput.value = textContent;

      // Trigger input and change events on target input
      const inputEvent = new Event("input", { bubbles: true });
      const changeEvent = new Event("change", { bubbles: true });
      this.targetInput.dispatchEvent(inputEvent);
      this.targetInput.dispatchEvent(changeEvent);
    }
  }

  getContent() {
    return this.editorContainer.innerHTML;
  }

  // New method to get only plain text without HTML tags
  getTextOnly() {
    // Create a temporary element to strip HTML
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = this.editorContainer.innerHTML;

    // Get text content and clean it up
    let textContent = tempDiv.textContent || tempDiv.innerText || "";

    // Remove placeholder text if present
    if (textContent.trim() === this.options.placeholder) {
      return "";
    }

    // Clean up extra whitespace and normalize line breaks
    return textContent
      .replace(/\s+/g, " ") // Replace multiple spaces with single space
      .trim(); // Remove leading/trailing whitespace
  }

  // Alternative method for getting text with preserved line breaks
  getFormattedText() {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = this.editorContainer.innerHTML;

    // Replace block elements with line breaks
    const blockElements = tempDiv.querySelectorAll(
      "div, p, h1, h2, h3, h4, h5, h6, li, br"
    );
    blockElements.forEach((el) => {
      if (el.tagName === "BR") {
        el.replaceWith("\n");
      } else {
        el.insertAdjacentText("afterend", "\n");
      }
    });

    let textContent = tempDiv.textContent || tempDiv.innerText || "";

    // Remove placeholder text if present
    if (textContent.trim() === this.options.placeholder) {
      return "";
    }

    // Clean up multiple line breaks but preserve intentional ones
    return textContent
      .replace(/\n\s*\n\s*\n/g, "\n\n") // Max 2 consecutive line breaks
      .trim();
  }

  setContent(content) {
    this.editorContainer.innerHTML = content;
    this.updateOriginalElement();
  }

  // Method to set editor height dynamically
  setHeight(height) {
    this.options.height = height;
    this.editorContainer.style.height = height;
    this.editorContainer.style.maxHeight = "none"; // Ensure no max-height limitation
  }

  // Method to insert HTML at current cursor position
  insertHTML(htmlContent) {
    this.editorContainer.focus();

    // Check if content looks like HTML
    if (htmlContent.includes("<") && htmlContent.includes(">")) {
      document.execCommand("insertHTML", false, htmlContent);
    } else {
      document.execCommand("insertText", false, htmlContent);
    }

    this.updateOriginalElement();
  }

  clear() {
    this.editorContainer.innerHTML = `<p>${this.options.placeholder}</p>`;
    this.updateOriginalElement();
  }

  isEmpty() {
    const content = this.editorContainer.innerHTML;
    return (
      content === `<p>${this.options.placeholder}</p>` ||
      content === "<p><br></p>" ||
      content === "" ||
      this.editorContainer.textContent.trim() === this.options.placeholder
    );
  }

  // Method untuk menambahkan event listener keyup pada elemen tertentu di dalam editor
  onElementKeyup(selector, callback) {
    this.editorContainer.addEventListener("keyup", (event) => {
      const targetElement = this.editorContainer.querySelector(selector);
      if (targetElement) {
        const elementContent = targetElement.textContent;
        callback(elementContent, targetElement, event);
      } else {
        // Jika selector tidak ditemukan, callback dengan seluruh isi editor
        const elementContent = this.editorContainer.innerHTML;
        callback(elementContent, this.editorContainer, event);
      }
    });
  }

  // Method untuk menambahkan event listener input pada elemen tertentu di dalam editor
  onElementInput(selector, callback) {
    this.editorContainer.addEventListener("input", (event) => {
      const targetElement = this.editorContainer.querySelector(selector);
      if (targetElement) {
        const elementContent = targetElement.textContent;
        callback(elementContent, targetElement, event);
      }
    });
  }

  destroy() {
    if (this.wrapper && this.wrapper.parentNode) {
      this.element.style.display = "";
      this.wrapper.parentNode.insertBefore(this.element, this.wrapper);
      this.wrapper.remove();
    }
  }
}
