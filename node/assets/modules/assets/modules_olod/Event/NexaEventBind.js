export class NexaEventBind {
  constructor(options = {}) {
    this.defaultEvent = options.defaultEvent || "click";
    this.cleanData = options.cleanData !== false; // Default to clean data (true), unless explicitly set to false
    this.listeners = new Map(); // Track added listeners to prevent duplicates

    // Return a Proxy to intercept method calls
    return new Proxy(this, {
      get(target, prop) {
        // If the property exists on the target, return it
        if (prop in target) {
          return target[prop];
        }

        // Otherwise, treat it as an element ID and return a function
        return function (callback, eventType = target.defaultEvent) {
          target.addListener(prop, callback, eventType);
        };
      },
    });
  }

  addListener(elementId, callback, eventType = "click") {
    const element = document.getElementById(elementId);
    if (!element) {
     
      return;
    }

    // Create a unique key for this listener
    const listenerKey = `${elementId}_${eventType}`;

    // Check if listener already exists
    if (this.listeners.has(listenerKey)) {
      console.warn(
        `Event listener for "${elementId}" (${eventType}) already exists`
      );
      return;
    }

    // Add the event listener dengan data attributes
    element.addEventListener(eventType, (event) => {
      // Extract attributes dari element, exclude href dan type
      const elementData = {};

      // Get all attributes
      Array.from(element.attributes).forEach((attr) => {
        // Skip href dan type attributes
        if (attr.name !== "href" && attr.name !== "type") {
          // Keep original attribute name with dashes
          elementData[attr.name] = attr.value;

          // Create nested object structure for data attributes
          if (attr.name.startsWith("data-")) {
            const path = attr.name.substring(5); // Remove 'data-' prefix
            const keys = path.split("-");

            // Smart JSON parsing with better detection
            let processedValue = attr.value;

            // Check if value looks like JSON and try to parse
            if (processedValue && typeof processedValue === "string") {
              const trimmed = processedValue.trim();
              if (
                (trimmed.startsWith("{") && trimmed.endsWith("}")) ||
                (trimmed.startsWith("[") && trimmed.endsWith("]"))
              ) {
                try {
                  processedValue = JSON.parse(trimmed);
                  console.log(
                    `✅ Successfully parsed JSON for ${attr.name}:`,
                    processedValue
                  );
                } catch (error) {
                  console.warn(
                    `⚠️ Failed to parse JSON for ${attr.name}:`,
                    error.message
                  );
                  // Keep original string value if parsing fails
                  processedValue = attr.value;
                }
              }
            }

            // Create nested structure with processed (potentially parsed) value
            let current = elementData;
            keys.forEach((key, index) => {
              if (index === keys.length - 1) {
                // Last key, set the processed value
                current[key] = processedValue;
              } else {
                // Create nested object if it doesn't exist
                if (!current[key] || typeof current[key] !== "object") {
                  current[key] = {};
                }
                current = current[key];
              }
            });

            // Also create camelCase version with parsed value
            const camelCaseName = attr.name.replace(
              /-([a-z])/g,
              (match, letter) => letter.toUpperCase()
            );
            elementData[camelCaseName] = processedValue;
          }

          // For non-data attributes, create camelCase version with original value
          if (attr.name.includes("-") && !attr.name.startsWith("data-")) {
            const camelCaseName = attr.name.replace(
              /-([a-z])/g,
              (match, letter) => letter.toUpperCase()
            );
            elementData[camelCaseName] = attr.value;
          }
        }
      });

      // Clean data if cleanData option is enabled
      let finalData = elementData;
      if (this.cleanData) {
        finalData = this.getCleanData(elementData);
      }

      // Call callback dengan element data
      callback(finalData, event, element);
    });

    // Mark as added to prevent duplicates
    this.listeners.set(listenerKey, true);

   
  }

  removeListener(elementId, eventType = "click") {
    const listenerKey = `${elementId}_${eventType}`;

    if (this.listeners.has(listenerKey)) {
      this.listeners.delete(listenerKey);
      console.log(`Event listener removed for "${elementId}" (${eventType})`);
    }
  }

  hasListener(elementId, eventType = "click") {
    const listenerKey = `${elementId}_${eventType}`;
    return this.listeners.has(listenerKey);
  }

  // Utility method untuk bind multiple elements sekaligus
  bindMultiple(bindings) {
    if (typeof bindings === "object") {
      Object.entries(bindings).forEach(([elementId, callback]) => {
        this[elementId](callback);
      });
    }
  }

  // Method untuk remove semua listeners
  removeAll() {
    this.listeners.clear();
    console.log("All event listeners removed");
  }

  // Get clean data without duplicates
  getCleanData(rawData) {
    const cleanData = {};

    // Add non-data attributes (excluding duplicates)
    Object.entries(rawData).forEach(([key, value]) => {
      if (!key.startsWith("data-") && !this.isCamelCaseDataAttribute(key)) {
        cleanData[key] = value;
      }
    });

    // Add nested data structure (the clean parsed versions)
    Object.entries(rawData).forEach(([key, value]) => {
      if (
        !key.includes("-") &&
        !key.startsWith("data") &&
        typeof value === "object" &&
        value !== null
      ) {
        cleanData[key] = value;
      }
    });

    // Add single-level parsed data attributes
    Object.entries(rawData).forEach(([key, value]) => {
      if (
        !key.includes("-") &&
        !key.startsWith("data") &&
        key !== "id" &&
        key !== "name" &&
        key !== "value" &&
        typeof value !== "object"
      ) {
        cleanData[key] = value;
      }
    });

    return cleanData;
  }

  // Check if a key is a camelCase data attribute
  isCamelCaseDataAttribute(key) {
    // Simple heuristic: starts with 'data' followed by uppercase letter
    return /^data[A-Z]/.test(key);
  }

  // Get statistics
  getStats() {
    return {
      totalListeners: this.listeners.size,
      registeredElements: Array.from(this.listeners.keys()).map(
        (key) => key.split("_")[0]
      ),
      cleanDataMode: this.cleanData,
    };
  }
}
