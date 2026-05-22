export class NexaGlobal {
  constructor() {
    // Initialize any properties if needed

    // Sistem BENAR-BENAR dinamis menggunakan Proxy
    if (typeof window !== "undefined") {
      window.NexaGlobalInstance = this;

      // Proxy untuk auto-create global functions
      return new Proxy(this, {
        set(target, property, value) {
          // Set property ke instance
          target[property] = value;

          // Jika property adalah function, otomatis buat global function
          if (typeof value === "function" && property !== "constructor") {
            window[property] = async function (
              argument,
              secondParam,
              thirdParam
            ) {
              try {
                let result;

                // Cek apakah secondParam adalah element ID yang valid
                if (secondParam && typeof secondParam === "string") {
                  // Coba cari element dengan ID tersebut
                  const element = document.getElementById(secondParam);

                  if (element) {
                    // Jika element ditemukan, ambil data element dan gabungkan dengan argument
                    const elementData = target.getElementData(
                      secondParam,
                      true
                    );

                    // Automatic smart method calling untuk pattern umum
                    if (
                      target.isSmartMethodPattern(
                        property,
                        argument,
                        secondParam
                      )
                    ) {
                      result = await target.callSmartMethod(
                        property,
                        argument,
                        elementData,
                        secondParam
                      );
                    } else {
                      // Default behavior
                      result = target[property](
                        argument,
                        elementData,
                        thirdParam
                      );
                    }
                  } else {
                    // Jika element tidak ditemukan, treat sebagai parameter biasa
                    result = target[property](
                      argument,
                      secondParam,
                      thirdParam
                    );
                  }
                } else {
                  // Jika tidak ada secondParam atau bukan string, panggil dengan argument saja
                  result = target[property](argument, secondParam, thirdParam);
                }

                // ✅ Handle async results properly
                if (result && typeof result.then === "function") {
                  // Jika result adalah Promise, await dan return
                  return await result;
                } else {
                  // Jika result bukan Promise, return langsung
                  return result;
                }
              } catch (error) {
                console.error(`Error in async function '${property}':`, error);
                throw error; // Re-throw untuk debugging
              }
            };
            // Function created successfully
          }

          return true;
        },
      });
    }
  }

  // Class kosong - semua method didefinisikan dari luar secara dinamis

  // === ARRAY METHODS ===
  showArray(arr) {
    if (!Array.isArray(arr)) {
      console.error("Parameter bukan array!");
      return;
    }

    console.log("=== ARRAY DATA ===");
    console.log("Length:", arr.length);
    arr.forEach((item, index) => {
      console.log(`[${index}]:`, item);
    });
    return arr;
  }

  // === OBJECT METHODS ===
  showObject(obj) {
    if (typeof obj !== "object" || obj === null) {
      console.error("Parameter bukan object!");
      return;
    }

    console.log("=== OBJECT DATA ===");
    console.log("Keys:", Object.keys(obj));
    for (const [key, value] of Object.entries(obj)) {
      console.log(`${key}:`, value);
    }
    return obj;
  }

  // === ELEMENT DATA EXTRACTION METHODS ===
  getElementData(elementId, cleanData = true) {
    const element = document.getElementById(elementId);
    if (!element) {
      console.error(`Element with ID '${elementId}' not found`);
      return null;
    }

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

    // Return clean or raw data based on parameter
    return cleanData ? this.getCleanElementData(elementData) : elementData;
  }

  // Get clean data without duplicates (from NexaEventBind)
  getCleanElementData(rawData) {
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

  // Enhanced method to get element by selector (not just ID)
  getElementDataBySelector(selector, cleanData = true) {
    const element = document.querySelector(selector);
    if (!element) {
      console.error(`Element with selector '${selector}' not found`);
      return null;
    }

    // Temporarily set an ID if element doesn't have one
    const originalId = element.id;
    if (!element.id) {
      element.id = `temp_${Date.now()}`;
    }

    const data = this.getElementData(element.id, cleanData);

    // Restore original ID
    if (!originalId) {
      element.removeAttribute("id");
    }

    return data;
  }

  // Method to get form data
  getFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) {
      console.error(`Form with ID '${formId}' not found`);
      return null;
    }

    const formData = new FormData(form);
    const data = {};

    for (let [key, value] of formData.entries()) {
      data[key] = value;
    }

    return data;
  }

  // === SMART METHOD PATTERNS ===
  isSmartMethodPattern(methodName, argument, elementId) {
    // Detect common patterns yang bisa di-automate
    const smartPatterns = [
      "onPress",
      "handleClick",
      "processElement",
      "configureField",
      "setField",
    ];

    // Cek apakah method name cocok dengan pattern
    const isSmartMethod = smartPatterns.some(
      (pattern) =>
        methodName.includes(pattern) || methodName.startsWith(pattern)
    );

    // Cek apakah element ID menggunakan pattern btn_variable_index
    const hasButtonPattern = elementId && /^btn_.+_\d+$/.test(elementId);

    return isSmartMethod && hasButtonPattern;
  }

  async callSmartMethod(methodName, argument, elementData, elementId) {
    try {
      // Extract variable name dari element ID pattern (btn_variableName_index -> variableName)
      let actualData = argument; // default

      if (elementId && typeof elementId === "string") {
        const match = elementId.match(/^btn_(.+)_\d+$/);
        if (match) {
          actualData = match[1]; // variableName
        }
      }

      // Auto-route ke instance yang tepat berdasarkan method name
      const targetInstance = this.findTargetInstance(methodName);

      if (targetInstance && typeof targetInstance[argument] === "function") {
        // Panggil method di target instance dengan actualData dan elementData
        const result = targetInstance[argument](actualData, elementData);

        // Handle async results
        if (result && typeof result.then === "function") {
          return await result;
        } else {
          return result;
        }
      } else {
        // Fallback ke default behavior
        const result = this[methodName](argument, elementData, elementId);

        // Handle async fallback
        if (result && typeof result.then === "function") {
          return await result;
        } else {
          return result;
        }
      }
    } catch (error) {
      console.error(`Error in callSmartMethod '${methodName}':`, error);
      throw error;
    }
  }

  findTargetInstance(methodName) {
    // Smart instance detection berdasarkan method name atau context
    const instanceMap = {
      onPress: "variablesInstance",
      handleClick: "clickHandlerInstance",
      processElement: "elementProcessorInstance",
      configureField: "variablesInstance",
      setField: "variablesInstance",
    };

    // Cari instance yang cocok
    for (const [pattern, instanceName] of Object.entries(instanceMap)) {
      if (methodName.includes(pattern) || methodName.startsWith(pattern)) {
        const instance = window[instanceName];
        if (instance) {
          return instance;
        }
      }
    }

    // Fallback: cari instance apapun yang punya method tersebut
    const commonInstances = [
      "variablesInstance",
      "formInstance",
      "modalInstance",
      "componentInstance",
    ];

    for (const instanceName of commonInstances) {
      const instance = window[instanceName];
      if (instance && typeof instance[methodName] === "function") {
        return instance;
      }
    }

    return null;
  }

  // === UTILITY METHODS ===
}

// Make it available globally if needed
if (typeof window !== "undefined") {
  window.NexaGlobal = NexaGlobal;
}

// For Node.js environments
if (typeof module !== "undefined" && module.exports) {
  module.exports = NexaGlobal;
}
