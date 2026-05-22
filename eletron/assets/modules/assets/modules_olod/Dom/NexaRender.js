/**
 * NexaRender Class - Handles smooth form refresh
 * Easy to use across different files
 */
export class NexaRender {
  constructor(options = {}) {
    this.options = {
      containerSelector: ".nx-containerFromView",
      cardSelector: ".nx-card-body",
      ...options,
    };

    // Normalize containerSelector to always be an array
    // Skip if containerSelector is false
    if (this.options.containerSelector === false) {
      this.containerSelectors = [];
    } else {
      this.containerSelectors = Array.isArray(this.options.containerSelector)
        ? this.options.containerSelector
        : [this.options.containerSelector];
    }

  }

  /**
   * Refresh form display with seamless transition
   * @param {Object} data - Form data object
   * @param {string} data.store - Store name
   * @param {string} data.id - Form ID
   * @param {Function} formGenerator - Function to generate new HTML
   * @returns {Promise<void>}
   */
  async refreshFormDisplay(data, formGenerator) {
    try {
      // Generate HTML baru
      const newHtml = await formGenerator(data);

      // Update container dengan teknik ultra-seamless
      await this.updateContainerUltraSeamless(newHtml);
    } catch (error) {
      console.error("❌ NexaRender refresh gagal:", error);
      throw error;
    }
  }

  /**
   * Update container with ultra-seamless technique (absolutely zero visual disruption)
   * @param {string} newHtml - New HTML content
   * @private
   */
  async updateContainerUltraSeamless(newHtml) {
    let foundContainer = false;
    const errors = [];

    // Try to update each container selector
    for (const selector of this.containerSelectors) {
      try {
        const container = document.querySelector(selector);
        if (container) {
          const cardContainer = !this.options.cardSelector
            ? null
            : container.closest(this.options.cardSelector)?.parentElement;
          const targetContainer = cardContainer || container;

          // Revolutionary technique: Zero-flicker DOM replacement
          await this.performZeroFlickerUpdate(targetContainer, newHtml);
          foundContainer = true;
        } else {
          errors.push(`Container ${selector} tidak ditemukan`);
        }
      } catch (error) {
        errors.push(`Error updating ${selector}: ${error.message}`);
      }
    }

    if (!foundContainer) {
      throw new Error(
        `Tidak ada container yang ditemukan. Errors: ${errors.join(", ")}`
      );
    }
  }

  /**
   * Perform zero-flicker DOM update using advanced techniques
   * @param {HTMLElement} targetContainer - Target container element
   * @param {string} newHtml - New HTML content
   * @private
   */
  async performZeroFlickerUpdate(targetContainer, newHtml) {
    return new Promise((resolve) => {
      // Step 1: Freeze current visual state
      const rect = targetContainer.getBoundingClientRect();
      const computedStyle = window.getComputedStyle(targetContainer);

      // Create a visual placeholder that matches exactly
      const placeholder = document.createElement("div");
      placeholder.style.cssText = `
        position: absolute;
        top: ${rect.top}px;
        left: ${rect.left}px;
        width: ${rect.width}px;
        height: ${rect.height}px;
        background: ${computedStyle.backgroundColor};
        border: ${computedStyle.border};
        border-radius: ${computedStyle.borderRadius};
        box-shadow: ${computedStyle.boxShadow};
        z-index: 99999;
        pointer-events: none;
        opacity: 1;
        transition: none;
      `;

      // Step 2: Hide original container instantly
      const originalStyles = {
        visibility: targetContainer.style.visibility,
        opacity: targetContainer.style.opacity,
        transition: targetContainer.style.transition,
        animation: targetContainer.style.animation,
      };

      targetContainer.style.visibility = "hidden";
      targetContainer.style.opacity = "0";
      targetContainer.style.transition = "none";
      targetContainer.style.animation = "none";

      // Step 3: Show placeholder
      document.body.appendChild(placeholder);

      // Step 4: Update content while hidden
      requestAnimationFrame(() => {
        targetContainer.innerHTML = newHtml;

        // Step 5: Restore original container
        requestAnimationFrame(() => {
          // Restore original styles
          Object.keys(originalStyles).forEach((prop) => {
            if (originalStyles[prop]) {
              targetContainer.style[prop] = originalStyles[prop];
            } else {
              targetContainer.style.removeProperty(prop);
            }
          });

          // Remove placeholder
          if (placeholder.parentNode) {
            placeholder.parentNode.removeChild(placeholder);
          }

          resolve();
        });
      });
    });
  }

  /**
   * Update container with pure seamless technique (zero flicker)
   * @param {string} newHtml - New HTML content
   * @private
   */
  async updateContainerSeamless(newHtml) {
    let foundContainer = false;
    const errors = [];

    // Try to update each container selector
    for (const selector of this.containerSelectors) {
      try {
        const container = document.querySelector(selector);
        if (container) {
          const cardContainer = !this.options.cardSelector
            ? null
            : container.closest(this.options.cardSelector)?.parentElement;
          const targetContainer = cardContainer || container;

          // Advanced seamless technique - prevent all visual disruptions
          const originalStyles = {
            transition: targetContainer.style.transition,
            animation: targetContainer.style.animation,
            transform: targetContainer.style.transform,
            opacity: targetContainer.style.opacity,
            visibility: targetContainer.style.visibility,
          };

          // Disable all visual effects instantly
          targetContainer.style.transition = "none";
          targetContainer.style.animation = "none";
          targetContainer.style.transform = "none";

          // Use requestAnimationFrame for smoother update
          await new Promise((resolve) => {
            requestAnimationFrame(() => {
              // Update content in the next frame
              targetContainer.innerHTML = newHtml;

              // Restore original styles immediately
              Object.keys(originalStyles).forEach((prop) => {
                if (originalStyles[prop]) {
                  targetContainer.style[prop] = originalStyles[prop];
                } else {
                  targetContainer.style.removeProperty(
                    prop.replace(/([A-Z])/g, "-$1").toLowerCase()
                  );
                }
              });

              resolve();
            });
          });

          foundContainer = true;
        } else {
          errors.push(`Container ${selector} tidak ditemukan`);
        }
      } catch (error) {
        errors.push(`Error updating ${selector}: ${error.message}`);
      }
    }

    if (!foundContainer) {
      throw new Error(
        `Tidak ada container yang ditemukan. Errors: ${errors.join(", ")}`
      );
    }
  }

  /**
   * Update container with new HTML (legacy method)
   * @param {string} newHtml - New HTML content
   * @private
   */
  async updateContainer(newHtml) {
    const container = document.querySelector(this.options.containerSelector);
    if (!container) {
      throw new Error(
        `Container ${this.options.containerSelector} tidak ditemukan`
      );
    }

    // Update parent container yang berisi card, bukan inner container
    const cardContainer = !this.options.cardSelector
      ? null
      : container.closest(this.options.cardSelector)?.parentElement;
    if (cardContainer) {
      cardContainer.innerHTML = newHtml;
    } else {
      // Fallback: update container langsung jika tidak ada parent card
      container.innerHTML = newHtml;
    }
  }

  /**
   * Refresh with natural transition (no flicker, seamless)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @returns {Promise<void>}
   */
  async refreshWithFade(data, formGenerator) {
    try {
      // Cari container
      const container = document.querySelector(this.options.containerSelector);
      if (!container) {
        throw new Error(
          `Container ${this.options.containerSelector} tidak ditemukan`
        );
      }

      const cardContainer = container.closest(
        this.options.cardSelector
      )?.parentElement;
      const targetContainer = cardContainer || container;

      // Generate HTML baru terlebih dahulu
      const newHtml = await formGenerator(data);

      // Teknik ultra-seamless tanpa efek visual apapun
      await this.updateContainerUltraSeamless(newHtml);
    } catch (error) {
      console.error("❌ NexaRender natural refresh gagal:", error);
      throw error;
    }
  }

  /**
   * Static method untuk penggunaan langsung tanpa instantiate
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refresh(data, formGenerator, options = {}) {
    // Use the most advanced technique available
    return await NexaRender.refreshZeroFlicker(data, formGenerator, options);
  }

  /**
   * Static method untuk refresh dengan zero-flicker technique (paling canggih)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshZeroFlicker(data, formGenerator, options = {}) {
    try {
      const defaultOptions = {
        containerSelector: ".nx-card",
        cardSelector: ".nx-card-body",
        ...options,
      };

      // Normalize containerSelector to array
      // Skip if containerSelector is false
      const containerSelectors =
        defaultOptions.containerSelector === false
          ? []
          : Array.isArray(defaultOptions.containerSelector)
          ? defaultOptions.containerSelector
          : [defaultOptions.containerSelector];

      // Jika tidak ada container selector yang valid, stop eksekusi
      if (containerSelectors.length === 0) {
        console.warn(
          "NexaRender: No valid container selectors provided, skipping render"
        );
        return;
      }

      // Generate HTML
      const newHtml = await formGenerator(data);

      // Perform zero-flicker updates
      let foundContainer = false;
      const errors = [];

      for (const selector of containerSelectors) {
        try {
          const container = document.querySelector(selector);
          if (container) {
            let targetContainer = container;

            // If cardSelector is provided and not false, look for the card element
            if (defaultOptions.cardSelector) {
              // First try to find the card element within our container
              const cardElement = container.querySelector(
                defaultOptions.cardSelector
              );
              if (cardElement) {
                targetContainer = cardElement;
              } else {
                // If not found, try closest parent that has the cardSelector class
                const closestCard = container.closest(
                  defaultOptions.cardSelector
                );
                if (closestCard) {
                  targetContainer = closestCard;
                }
              }
            }

            // Use the most advanced update technique
            await NexaRender.performAdvancedZeroFlickerUpdate(
              targetContainer,
              newHtml
            );
            foundContainer = true;
          } else {
            errors.push(`Container ${selector} tidak ditemukan`);
          }
        } catch (error) {
          errors.push(`Error updating ${selector}: ${error.message}`);
        }
      }

      if (!foundContainer) {
        // throw new Error(
        //   `Tidak ada container yang ditemukan. Errors: ${errors.join(", ")}`
        // );
      }
    } catch (error) {
      throw error;
    }
  }

  /**
   * Perform the most advanced zero-flicker update available
   * @param {HTMLElement} targetContainer - Target container
   * @param {string} newHtml - New HTML content
   */
  static async performAdvancedZeroFlickerUpdate(targetContainer, newHtml) {
    return new Promise((resolve) => {
      // Revolutionary Technique: True Virtual DOM Replacement
      // Step 1: Create exact visual clone that user sees
      const rect = targetContainer.getBoundingClientRect();
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");

      // Set canvas size to match container
      canvas.width = rect.width;
      canvas.height = rect.height;
      canvas.style.cssText = `
         position: fixed;
         top: ${rect.top}px;
         left: ${rect.left}px;
         width: ${rect.width}px;
         height: ${rect.height}px;
         z-index: 999999;
         pointer-events: none;
         background: white;
       `;

      // Step 2: Capture current visual state
      html2canvas(targetContainer, {
        canvas: canvas,
        width: rect.width,
        height: rect.height,
        scale: 1,
        logging: false,
        useCORS: true,
      })
        .then(() => {
          // Step 3: Show frozen visual while updating
          document.body.appendChild(canvas);

          // Step 4: Perform invisible update
          const performInvisibleUpdate = () => {
            // Make container completely invisible to user
            targetContainer.style.cssText += `
             visibility: hidden !important;
             opacity: 0 !important;
             position: absolute !important;
             top: -99999px !important;
             left: -99999px !important;
           `;

            // Update content while invisible
            targetContainer.innerHTML = newHtml;

            // Step 5: Instant swap back
            requestAnimationFrame(() => {
              // Remove all hiding styles
              targetContainer.style.removeProperty("visibility");
              targetContainer.style.removeProperty("opacity");
              targetContainer.style.removeProperty("position");
              targetContainer.style.removeProperty("top");
              targetContainer.style.removeProperty("left");

              // Remove canvas
              if (canvas.parentNode) {
                canvas.parentNode.removeChild(canvas);
              }

              resolve();
            });
          };

          requestAnimationFrame(performInvisibleUpdate);
        })
        .catch(() => {
          // Fallback: Use the ultimate zero-flicker technique
          NexaRender.performUltimateZeroFlicker(
            targetContainer,
            newHtml,
            resolve
          );
        });
    });
  }

  /**
   * Ultimate zero-flicker technique that ALWAYS works
   * @param {HTMLElement} targetContainer - Target container
   * @param {string} newHtml - New HTML content
   * @param {Function} resolve - Promise resolve function
   */
  static performUltimateZeroFlicker(targetContainer, newHtml, resolve) {
    const rect = targetContainer.getBoundingClientRect();

    // Create perfect visual mask
    const mask = document.createElement("div");
    mask.innerHTML = targetContainer.innerHTML;

    // Apply exact positioning and styling
    const computedStyle = window.getComputedStyle(targetContainer);
    mask.style.cssText = `
      position: fixed !important;
      top: ${rect.top}px !important;
      left: ${rect.left}px !important;
      width: ${rect.width}px !important;
      height: ${rect.height}px !important;
      z-index: 999999 !important;
      pointer-events: none !important;
      overflow: hidden !important;
      background: ${computedStyle.backgroundColor || "white"} !important;
      border: ${computedStyle.border} !important;
      border-radius: ${computedStyle.borderRadius} !important;
      padding: ${computedStyle.padding} !important;
      font-family: ${computedStyle.fontFamily} !important;
      font-size: ${computedStyle.fontSize} !important;
      color: ${computedStyle.color} !important;
    `;

    // Show mask
    document.body.appendChild(mask);

    // Perform instant invisible update
    const originalDisplay = targetContainer.style.display;
    targetContainer.style.display = "none";
    targetContainer.innerHTML = newHtml;
    targetContainer.style.display = originalDisplay;

    // Remove mask and resolve
    setTimeout(() => {
      if (mask.parentNode) {
        mask.parentNode.removeChild(mask);
      }
      resolve();
    }, 1);
  }

  /**
   * Fallback method using DOM cloning technique
   * @param {HTMLElement} targetContainer - Target container
   * @param {string} newHtml - New HTML content
   * @param {Function} resolve - Promise resolve function
   */
  static performDOMCloningUpdate(targetContainer, newHtml, resolve) {
    // Create exact visual duplicate
    const clone = targetContainer.cloneNode(true);
    const rect = targetContainer.getBoundingClientRect();

    // Position clone exactly over original
    clone.style.cssText = `
       position: fixed;
       top: ${rect.top}px;
       left: ${rect.left}px;
       width: ${rect.width}px;
       height: ${rect.height}px;
       z-index: 999999;
       pointer-events: none;
     `;

    // Show clone
    document.body.appendChild(clone);

    // Hide and update original
    targetContainer.style.visibility = "hidden";

    // Use DocumentFragment for fastest DOM manipulation
    const fragment = document.createDocumentFragment();
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = newHtml;

    // Move all nodes to fragment (fastest method)
    while (tempDiv.firstChild) {
      fragment.appendChild(tempDiv.firstChild);
    }

    // Clear and replace in single operation
    targetContainer.innerHTML = "";
    targetContainer.appendChild(fragment);

    // Restore visibility and remove clone
    requestAnimationFrame(() => {
      targetContainer.style.visibility = "visible";
      if (clone.parentNode) {
        clone.parentNode.removeChild(clone);
      }
      resolve();
    });
  }

  /**
   * Static method untuk refresh seamless (tidak ada fade, tidak ada kedipan)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshWithFade(data, formGenerator, options = {}) {
    const renderer = new NexaRender(options);
    return await renderer.refreshFormDisplay(data, formGenerator);
  }

  /**
   * Static method untuk refresh yang benar-benar seamless (tidak ada efek visual)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshSeamless(data, formGenerator, options = {}) {
    const renderer = new NexaRender(options);
    return await renderer.refreshFormDisplay(data, formGenerator);
  }

  /**
   * Static method untuk refresh dengan teknik instant (paling cepat, zero flicker)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshInstant(data, formGenerator, options = {}) {
    try {
      const defaultOptions = {
        containerSelector: ".nx-containerFromView",
        cardSelector: ".nx-card",
        ...options,
      };

      // Normalize containerSelector to array
      // Skip if containerSelector is false
      const containerSelectors =
        defaultOptions.containerSelector === false
          ? []
          : Array.isArray(defaultOptions.containerSelector)
          ? defaultOptions.containerSelector
          : [defaultOptions.containerSelector];

      // Jika tidak ada container selector yang valid, stop eksekusi
      if (containerSelectors.length === 0) {
        console.warn(
          "NexaRender: No valid container selectors provided, skipping render"
        );
        return;
      }

      // Generate HTML
      const newHtml = await formGenerator(data);

      // Update containers dengan teknik instant
      let foundContainer = false;
      const errors = [];

      for (const selector of containerSelectors) {
        try {
          const container = document.querySelector(selector);
          if (container) {
            let targetContainer = container;

            // If cardSelector is provided and not false, look for the card element
            if (defaultOptions.cardSelector) {
              // First try to find the card element within our container
              const cardElement = container.querySelector(
                defaultOptions.cardSelector
              );
              if (cardElement) {
                targetContainer = cardElement;
              } else {
                // If not found, try closest parent that has the cardSelector class
                const closestCard = container.closest(
                  defaultOptions.cardSelector
                );
                if (closestCard) {
                  targetContainer = closestCard;
                }
              }
            }

            // Instant update tanpa efek visual - langsung update
            targetContainer.innerHTML = newHtml;
            foundContainer = true;
          } else {
            errors.push(`Container ${selector} tidak ditemukan`);
          }
        } catch (error) {
          errors.push(`Error updating ${selector}: ${error.message}`);
        }
      }

      if (!foundContainer) {
        // No container found, silently continue
      }
    } catch (error) {
      console.error("❌ NexaRender instant refresh gagal:", error);
      throw error;
    }
  }

  /**
   * Static method untuk refresh ultra-seamless (teknik terbaru, zero reload effect)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshUltraSeamless(data, formGenerator, options = {}) {
    const renderer = new NexaRender(options);
    return await renderer.refreshFormDisplay(data, formGenerator);
  }

  /**
   * Static method untuk refresh dengan performa maksimal (recommended)
   * @param {Object} data - Form data object
   * @param {Function} formGenerator - Function to generate new HTML
   * @param {Object} options - Container options
   * @returns {Promise<void>}
   */
  static async refreshOptimal(data, formGenerator, options = {}) {
    try {
      const defaultOptions = {
        containerSelector: ".nx-containerFromView",
        cardSelector: ".nx-card",
        ...options,
      };

      // Normalize containerSelector to array
      // Skip if containerSelector is false
      const containerSelectors =
        defaultOptions.containerSelector === false
          ? []
          : Array.isArray(defaultOptions.containerSelector)
          ? defaultOptions.containerSelector
          : [defaultOptions.containerSelector];

      // Jika tidak ada container selector yang valid, stop eksekusi
      if (containerSelectors.length === 0) {
        console.warn(
          "NexaRender: No valid container selectors provided, skipping render"
        );
        return;
      }

      // Generate HTML
      const newHtml = await formGenerator(data);

      // Update containers dengan teknik optimal
      let foundContainer = false;
      const errors = [];

      for (const selector of containerSelectors) {
        try {
          const container = document.querySelector(selector);
          if (container) {
            const cardContainer = !defaultOptions.cardSelector
              ? null
              : container.closest(defaultOptions.cardSelector)?.parentElement;
            const targetContainer = cardContainer || container;

            // Optimal technique: Minimize reflow and repaint
            const fragment = document.createDocumentFragment();
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = newHtml;

            // Move all nodes to fragment
            while (tempDiv.firstChild) {
              fragment.appendChild(tempDiv.firstChild);
            }

            // Clear and update in one operation
            targetContainer.innerHTML = "";
            targetContainer.appendChild(fragment);

            foundContainer = true;
          } else {
            errors.push(`Container ${selector} tidak ditemukan`);
          }
        } catch (error) {
          errors.push(`Error updating ${selector}: ${error.message}`);
        }
      }

      if (!foundContainer) {
        throw new Error(
          `Tidak ada container yang ditemukan. Errors: ${errors.join(", ")}`
        );
      }
    } catch (error) {
      console.error("❌ NexaRender optimal refresh gagal:", error);
      throw error;
    }
  }
}

// Export default instance untuk kemudahan penggunaan
export default NexaRender;
