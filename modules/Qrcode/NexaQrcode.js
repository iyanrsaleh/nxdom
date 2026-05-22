class Qrcode {
  constructor(containerId, options = {}) {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);
    this.options = {
      width: options.width || 256,
      height: options.height || 256,
      text: options.text || "Hello World",
      colorDark: options.colorDark || "#000000",
      colorLight: options.colorLight || "#ffffff",
      correctLevel: options.correctLevel || "M",
      debug: options.debug || false,
      showControls: options.showControls || false, // Changed from downloadButton
      logo: options.logo || null,
      logoSize: options.logoSize || 0.2,
      logoMargin: options.logoMargin || 8,
      logoRadius: options.logoRadius || 8,
      ...options,
    };

    this.qrcode = null;
    this.qriousLoaded = false;

    if (!this.container) {
      this._initPromise = Promise.resolve();
      return;
    }

    this._initPromise = this.loadQRiousLibrary().then(() => {
      this.init();
    });
  }

  loadQRiousLibrary() {
    return new Promise((resolve) => {
      // Check if QRious is already loaded
      if (typeof QRious !== "undefined") {
        this.qriousLoaded = true;
        resolve();
        return;
      }

      // Check if script is already being loaded
      if (document.querySelector("script[data-qrious]")) {
        // Wait for existing script to load
        const checkLoaded = setInterval(() => {
          if (typeof QRious !== "undefined") {
            this.qriousLoaded = true;
            clearInterval(checkLoaded);
            resolve();
          }
        }, 100);
        return;
      }

      // Load QRious library dynamically
      const script = document.createElement("script");
      script.src = "https://unpkg.com/qrious@4.0.2/dist/qrious.min.js";
      script.setAttribute("data-qrious", "true");

      script.onload = () => {
        this.qriousLoaded = true;
        resolve();
      };

      script.onerror = () => {
        resolve(); // Resolve anyway to continue initialization
      };

      document.head.appendChild(script);
    });
  }

  init() {
    // Create QR code wrapper
    this.createWrapper();

    // Generate initial QR code
    this.generate(this.options.text);

    // Add control container if enabled
    if (this.options.showControls) {
      this.createControlsContainer();
    }
  }

  createWrapper() {
    // Clear container
    this.container.innerHTML = "";

    // Create QR code container
    this.qrContainer = document.createElement("div");
    this.qrContainer.className = "nexa-qrcode-container";
    this.qrContainer.style.cssText = `
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        `;

    this.container.appendChild(this.qrContainer);
  }

  createControlsContainer() {
    this.controlsContainer = document.createElement("div");
    this.controlsContainer.className = "nexa-qrcode-controls";
    this.controlsContainer.style.cssText = `
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        `;

    this.container.appendChild(this.controlsContainer);
  }

  createDownloadButton(text = "Download QR", className = "download-btn") {
    return this.createCustomButton(text, () => this.downloadQR(), {
      className: className,
      background: "#007bff",
      hoverBackground: "#0056b3",
    });
  }

  /**
   * Create custom button with flexible options
   * @param {string} text - Button text
   * @param {function} callback - Click handler
   * @param {object} options - Button styling and options
   * @returns {HTMLElement} Created button element
   */
  createCustomButton(text, callback, options = {}) {
    // Ensure controls container exists
    if (!this.controlsContainer) {
      this.createControlsContainer();
    }

    const btnOptions = {
      className: "custom-btn",
      background: "#6c757d",
      hoverBackground: "#5a6268",
      color: "white",
      padding: "8px 16px",
      fontSize: "14px",
      borderRadius: "4px",
      ...options,
    };

    const button = document.createElement("button");
    button.textContent = text;
    button.className = `nexa-qrcode-btn ${btnOptions.className}`;
    button.style.cssText = `
            padding: ${btnOptions.padding};
            background: ${btnOptions.background};
            color: ${btnOptions.color};
            border: none;
            border-radius: ${btnOptions.borderRadius};
            cursor: pointer;
            font-size: ${btnOptions.fontSize};
            transition: background 0.3s;
        `;

    button.addEventListener("click", callback);

    if (btnOptions.hoverBackground) {
      button.addEventListener("mouseover", () => {
        button.style.background = btnOptions.hoverBackground;
      });

      button.addEventListener("mouseout", () => {
        button.style.background = btnOptions.background;
      });
    }

    this.controlsContainer.appendChild(button);
    return button;
  }

  /**
   * Add multiple buttons at once
   * @param {Array} buttons - Array of button configurations
   */
  addButtons(buttons) {
    buttons.forEach((buttonConfig) => {
      if (buttonConfig.type === "download") {
        this.createDownloadButton(buttonConfig.text, buttonConfig.className);
      } else {
        this.createCustomButton(
          buttonConfig.text,
          buttonConfig.callback,
          buttonConfig.options || {}
        );
      }
    });
  }

  /**
   * Remove all buttons from controls
   */
  clearButtons() {
    if (this.controlsContainer) {
      this.controlsContainer.innerHTML = "";
    }
  }

  /**
   * Remove specific button by class name
   * @param {string} className - Class name of button to remove
   */
  removeButton(className) {
    if (this.controlsContainer) {
      const button = this.controlsContainer.querySelector(`.${className}`);
      if (button) {
        button.remove();
      }
    }
  }

  generate(text) {
    if (!text || text.trim() === "") {
      return false;
    }

    try {
      // Clear previous QR code
      this.qrContainer.innerHTML = "";

      // Check if QRious library is available
      if (typeof QRious === "undefined" || !this.qriousLoaded) {
        this.qrContainer.innerHTML =
          '<p style="color: orange;">Loading QR library...</p>';

        // Retry after library loads
        setTimeout(() => {
          if (typeof QRious !== "undefined") {
            this.generate(text);
          }
        }, 1000);

        return false;
      }

      // Create canvas element
      const canvas = document.createElement("canvas");

      // Generate QR code using QRious library
      const qr = new QRious({
        element: canvas,
        value: text,
        size: this.options.width,
        background: this.options.colorLight,
        foreground: this.options.colorDark,
        level: this.options.correctLevel,
      });

      // Add canvas to container
      this.qrContainer.appendChild(canvas);
      this.qrcode = canvas;

      // Prevent canvas from being removed accidentally
      canvas.style.display = "block";
      canvas.style.margin = "0 auto";

      // Add logo if specified
      if (this.options.logo) {
        this.addLogo(canvas);
      }

      return true;
    } catch (error) {
      this.qrContainer.innerHTML =
        '<p style="color: red;">Error generating QR code</p>';
      return false;
    }
  }

  addLogo(canvas) {
    const ctx = canvas.getContext("2d");
    const img = new Image();

    img.onload = () => {
      const canvasSize = canvas.width;
      const logoSize = canvasSize * this.options.logoSize;
      const x = (canvasSize - logoSize) / 2;
      const y = (canvasSize - logoSize) / 2;

      // Save current state
      ctx.save();

      // Create rounded rectangle for logo background
      const margin = this.options.logoMargin;
      const bgX = x - margin;
      const bgY = y - margin;
      const bgSize = logoSize + margin * 2;

      // Draw white background with rounded corners
      this.drawRoundedRect(
        ctx,
        bgX,
        bgY,
        bgSize,
        bgSize,
        this.options.logoRadius,
        "#ffffff"
      );

      // Create clipping path for rounded logo
      this.createRoundedClip(
        ctx,
        x,
        y,
        logoSize,
        logoSize,
        this.options.logoRadius - 2
      );

      // Draw the logo
      ctx.drawImage(img, x, y, logoSize, logoSize);

      // Restore state
      ctx.restore();
    };

    img.onerror = () => {
      // Failed to load logo image
    };

    // Support both URL and base64
    img.src = this.options.logo;
  }

  drawRoundedRect(ctx, x, y, width, height, radius, fillColor) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();

    if (fillColor) {
      ctx.fillStyle = fillColor;
      ctx.fill();
    }
  }

  createRoundedClip(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
    ctx.clip();
  }

  updateText(newText) {
    this.options.text = newText;
    return this.generate(newText);
  }

  updateOptions(newOptions) {
    this.options = { ...this.options, ...newOptions };
    return this.generate(this.options.text);
  }

  downloadQR() {
    try {
      const canvas = this.qrContainer.querySelector("canvas");
      if (!canvas) {
        return false;
      }

      // Create download link
      const link = document.createElement("a");
      link.download = `qrcode-${Date.now()}.png`;
      link.href = canvas.toDataURL("image/png");

      // Trigger download
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      return true;
    } catch (error) {
      return false;
    }
  }

  getDataURL() {
    try {
      const canvas = this.qrContainer.querySelector("canvas");
      if (!canvas) {
        return null;
      }

      return canvas.toDataURL("image/png");
    } catch (error) {
      return null;
    }
  }

  getBlob(callback) {
    try {
      const canvas = this.qrContainer.querySelector("canvas");
      if (!canvas) {
        return false;
      }

      canvas.toBlob(callback, "image/png");
      return true;
    } catch (error) {
      return false;
    }
  }

  clear() {
    if (this.qrContainer) {
      this.qrContainer.innerHTML = "";
    }
    this.qrcode = null;
  }

  destroy() {
    this.clear();

    if (this.container) {
      this.container.innerHTML = "";
    }

    this.qrcode = null;
    this.container = null;
    this.qrContainer = null;
    this.controlsContainer = null;
    this.downloadBtn = null;
  }

  // Static methods for quick QR code generation
  static generateToElement(elementId, text, options = {}) {
    const qr = new Qrcode(elementId, { ...options, text });
    return qr;
  }

  /**
   * Mengembalikan Promise<HTMLCanvasElement | null>.
   * Menunggu init async + kemungkinan retry generate() saat QRious belum siap.
   */
  static async generateToCanvas(text, options = {}) {
    const tempDiv = document.createElement("div");
    tempDiv.style.display = "none";
    document.body.appendChild(tempDiv);
    tempDiv.id = "temp-qr-" + Date.now();

    const qr = new Qrcode(tempDiv.id, {
      ...options,
      text,
      showControls: false,
    });

    await qr._initPromise;

    const maxWait = 10000;
    const step = 100;
    let waited = 0;
    let canvas = null;
    while (waited < maxWait) {
      canvas = qr.qrContainer?.querySelector("canvas") || null;
      if (canvas) break;
      await new Promise((r) => setTimeout(r, step));
      waited += step;
    }

    const clonedCanvas = canvas ? canvas.cloneNode(true) : null;

    if (tempDiv.parentNode) {
      document.body.removeChild(tempDiv);
    }
    qr.destroy();

    return clonedCanvas;
  }
}

export { Qrcode };
