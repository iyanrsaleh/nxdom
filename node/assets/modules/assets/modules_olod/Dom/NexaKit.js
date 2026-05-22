export class NexaKit {
  constructor() {
    // Constructor kosong, semua method akan static atau instance method
  }

  // Method untuk memilih element berdasarkan ID
  id(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih element berdasarkan class (mengambil element pertama)
  class(className) {
    const element = document.getElementsByClassName(className)[0];
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih element berdasarkan class (mengambil semua elements)
  classAll(className) {
    const elements = document.getElementsByClassName(className);
    if (elements.length === 0) {
      return [];
    }
    return Array.from(elements).map((el) => this.wrapElement(el));
  }

  // Method untuk memilih element berdasarkan CSS selector
  selector(cssSelector) {
    const element = document.querySelector(cssSelector);
    if (!element) {
      return this.createDummyElement();
    }
    return this.wrapElement(element);
  }

  // Method untuk memilih semua elements berdasarkan CSS selector
  selectorAll(cssSelector) {
    const elements = document.querySelectorAll(cssSelector);
    if (elements.length === 0) {
      return [];
    }
    return Array.from(elements).map((el) => this.wrapElement(el));
  }

  // Method untuk membuat element baru
  createElement(tagName, attributes = {}) {
    const element = document.createElement(tagName);

    // Set attributes jika ada
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === "id") {
        element.id = value;
      } else if (key === "class") {
        element.className = value;
      } else if (key.startsWith("data-")) {
        element.dataset[key.replace("data-", "")] = value;
      } else {
        element.setAttribute(key, value);
      }
    });

    return this.wrapElement(element);
  }

  // Method untuk membungkus element agar mendukung chaining
  wrapElement(element) {
    return {
      // Property innerHTML untuk get/set content
      get innerHTML() {
        return element.innerHTML;
      },
      set innerHTML(value) {
        element.innerHTML = value;
      },

      // Property textContent untuk get/set text
      get textContent() {
        return element.textContent;
      },
      set textContent(value) {
        element.textContent = value;
      },

      // Method html() sebagai alias untuk innerHTML (jQuery-like)
      html(value = null) {
        if (value === null) {
          return element.innerHTML;
        }
        element.innerHTML = value;
        return this;
      },

      // Property untuk mengakses element asli
      get element() {
        return element;
      },

      // Method untuk menambahkan class
      addClass(className) {
        element.classList.add(className);
        return this;
      },

      // Method untuk menghapus class
      removeClass(className) {
        element.classList.remove(className);
        return this;
      },

      // Method untuk toggle class
      toggleClass(className) {
        element.classList.toggle(className);
        return this;
      },

      // Method untuk set style
      setStyle(property, value) {
        element.style[property] = value;
        return this;
      },

      // Method untuk set multiple styles
      setStyles(styles = {}) {
        for (const [property, value] of Object.entries(styles)) {
          element.style[property] = value;
        }
        return this;
      },

      // Method untuk remove style property
      removeStyle(property) {
        element.style.removeProperty(property);
        return this;
      },

      // Method untuk menambahkan event listener
      on(event, handler) {
        element.addEventListener(event, handler);
        return this;
      },

      // Method untuk menghapus element
      remove() {
        element.remove();
        return this;
      },

      // Method untuk show element
      show() {
        element.style.display = "";
        return this;
      },

      // Method untuk hide element
      hide() {
        element.style.display = "none";
        return this;
      },

      // ===== ATTRIBUTE MANIPULATION =====

      // Method untuk set/get attribute
      attr(name, value = null) {
        if (value === null) {
          return element.getAttribute(name);
        }
        element.setAttribute(name, value);
        return this;
      },

      // Method untuk remove attribute
      removeAttr(name) {
        element.removeAttribute(name);
        return this;
      },

      // Method untuk check apakah attribute ada
      hasAttr(name) {
        return element.hasAttribute(name);
      },

      // Method untuk set/get data attribute
      data(key, value = null) {
        if (value === null) {
          return element.dataset[key];
        }
        element.dataset[key] = value;
        return this;
      },

      // Method untuk menambahkan ID ke existing ID
      addID(additionalId) {
        const currentId = element.id;
        const newId = currentId ? `${currentId} ${additionalId}` : additionalId;
        element.id = newId;
        return this;
      },

      // ===== VALUE MANIPULATION =====

      // Method untuk set/get value (untuk input, select, textarea)
      val(value = null) {
        if (value === null) {
          return element.value || "";
        }
        element.value = value;
        return this;
      },

      // Method untuk set/get placeholder
      placeholder(text = null) {
        if (text === null) {
          return element.placeholder || "";
        }
        element.placeholder = text;
        return this;
      },

      // ===== CLASS MANIPULATION ADVANCED =====

      // Method untuk check apakah class ada
      hasClass(className) {
        return element.classList.contains(className);
      },

      // Method untuk replace class
      replaceClass(oldClass, newClass) {
        element.classList.replace(oldClass, newClass);
        return this;
      },

      // Method untuk set multiple classes sekaligus
      setClasses(classNames) {
        element.className = classNames;
        return this;
      },

      // Method untuk get all classes
      getClasses() {
        return Array.from(element.classList);
      },

      // ===== CONTENT MANIPULATION ADVANCED =====

      // Method untuk append content
      append(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("beforeend", content);
        } else {
          element.appendChild(content);
        }
        return this;
      },

      // Method untuk prepend content
      prepend(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("afterbegin", content);
        } else {
          element.insertBefore(content, element.firstChild);
        }
        return this;
      },

      // Method untuk clear content
      clear() {
        element.innerHTML = "";
        return this;
      },

      // Method untuk insert before element
      before(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("beforebegin", content);
        } else {
          element.parentNode.insertBefore(content, element);
        }
        return this;
      },

      // Method untuk insert after element
      after(content) {
        if (typeof content === "string") {
          element.insertAdjacentHTML("afterend", content);
        } else {
          element.parentNode.insertBefore(content, element.nextSibling);
        }
        return this;
      },

      // Method untuk replace element dengan content baru
      replaceWith(content) {
        if (typeof content === "string") {
          element.outerHTML = content;
        } else {
          element.parentNode.replaceChild(content, element);
        }
        return this;
      },

      // Method untuk wrap element dengan wrapper
      wrap(wrapper) {
        if (typeof wrapper === "string") {
          const tempDiv = document.createElement("div");
          tempDiv.innerHTML = wrapper;
          const wrapperElement = tempDiv.firstChild;
          element.parentNode.insertBefore(wrapperElement, element);
          wrapperElement.appendChild(element);
        } else {
          element.parentNode.insertBefore(wrapper, element);
          wrapper.appendChild(element);
        }
        return this;
      },

      // Method untuk unwrap element (remove parent wrapper)
      unwrap() {
        const parent = element.parentNode;
        if (parent && parent !== document.body) {
          const grandParent = parent.parentNode;
          while (parent.firstChild) {
            grandParent.insertBefore(parent.firstChild, parent);
          }
          grandParent.removeChild(parent);
        }
        return this;
      },

      // Method untuk empty element (remove all children but keep element)
      empty() {
        while (element.firstChild) {
          element.removeChild(element.firstChild);
        }
        return this;
      },

      // Method untuk detach element (remove but keep in memory)
      detach() {
        if (element.parentNode) {
          element.parentNode.removeChild(element);
        }
        return this;
      },

      // ===== POSITIONING & DIMENSIONS =====

      // Method untuk get/set width
      width(value = null) {
        if (value === null) {
          return element.offsetWidth;
        }
        element.style.width = typeof value === "number" ? value + "px" : value;
        return this;
      },

      // Method untuk get/set height
      height(value = null) {
        if (value === null) {
          return element.offsetHeight;
        }
        element.style.height = typeof value === "number" ? value + "px" : value;
        return this;
      },

      // Method untuk get position
      position() {
        const rect = element.getBoundingClientRect();
        return {
          top: rect.top,
          left: rect.left,
          right: rect.right,
          bottom: rect.bottom,
          width: rect.width,
          height: rect.height,
        };
      },

      // ===== SCROLL MANIPULATION =====

      // Method untuk scroll to element
      scrollIntoView(options = { behavior: "smooth" }) {
        element.scrollIntoView(options);
        return this;
      },

      // Method untuk set scroll position
      scrollTo(top = 0, left = 0) {
        element.scrollTop = top;
        element.scrollLeft = left;
        return this;
      },

      // ===== FOCUS & SELECTION =====

      // Method untuk focus element
      focus() {
        element.focus();
        return this;
      },

      // Method untuk blur element
      blur() {
        element.blur();
        return this;
      },

      // Method untuk select text (untuk input/textarea)
      select() {
        if (element.select) {
          element.select();
        }
        return this;
      },

      // ===== ANIMATION & EFFECTS =====

      // Method untuk fade in
      fadeIn(duration = 300) {
        element.style.opacity = "0";
        element.style.display = "";
        element.style.transition = `opacity ${duration}ms ease`;

        setTimeout(() => {
          element.style.opacity = "1";
        }, 10);

        return this;
      },

      // Method untuk fade out
      fadeOut(duration = 300) {
        element.style.transition = `opacity ${duration}ms ease`;
        element.style.opacity = "0";

        setTimeout(() => {
          element.style.display = "none";
        }, duration);

        return this;
      },

      // Method untuk slide up
      slideUp(duration = 300) {
        element.style.transition = `height ${duration}ms ease`;
        element.style.height = element.offsetHeight + "px";
        element.style.overflow = "hidden";

        setTimeout(() => {
          element.style.height = "0";
        }, 10);

        setTimeout(() => {
          element.style.display = "none";
        }, duration);

        return this;
      },

      // Method untuk slide down
      slideDown(duration = 300) {
        element.style.display = "";
        const targetHeight = element.scrollHeight;
        element.style.height = "0";
        element.style.overflow = "hidden";
        element.style.transition = `height ${duration}ms ease`;

        setTimeout(() => {
          element.style.height = targetHeight + "px";
        }, 10);

        setTimeout(() => {
          element.style.height = "";
          element.style.overflow = "";
        }, duration);

        return this;
      },

      // ===== PARENT & CHILDREN MANIPULATION =====

      // Method untuk get parent element
      parent() {
        return element.parentElement
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              element.parentElement
            )
          : null;
      },

      // Method untuk get children elements
      children() {
        return Array.from(element.children).map((child) =>
          this.constructor.prototype.wrapElement.call(
            this.constructor.prototype,
            child
          )
        );
      },

      // Method untuk find element di dalam current element
      find(selector) {
        const found = element.querySelector(selector);
        return found
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              found
            )
          : null;
      },

      // Method untuk find all elements di dalam current element
      findAll(selector) {
        const found = element.querySelectorAll(selector);
        return Array.from(found).map((el) =>
          this.constructor.prototype.wrapElement.call(
            this.constructor.prototype,
            el
          )
        );
      },

      // ===== VALIDATION & UTILITIES =====

      // Method untuk check visibility
      isVisible() {
        return element.offsetWidth > 0 && element.offsetHeight > 0;
      },

      // Method untuk check apakah element ada di viewport
      isInViewport() {
        const rect = element.getBoundingClientRect();
        return (
          rect.top >= 0 &&
          rect.left >= 0 &&
          rect.bottom <=
            (window.innerHeight || document.documentElement.clientHeight) &&
          rect.right <=
            (window.innerWidth || document.documentElement.clientWidth)
        );
      },

      // Method untuk clone element
      clone(deep = true) {
        const cloned = element.cloneNode(deep);
        return this.constructor.prototype.wrapElement.call(
          this.constructor.prototype,
          cloned
        );
      },

      // ===== SIBLING MANIPULATION =====

      // Method untuk get next sibling
      next(selector = null) {
        let sibling = element.nextElementSibling;
        if (selector) {
          while (sibling && !sibling.matches(selector)) {
            sibling = sibling.nextElementSibling;
          }
        }
        return sibling
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              sibling
            )
          : null;
      },

      // Method untuk get previous sibling
      prev(selector = null) {
        let sibling = element.previousElementSibling;
        if (selector) {
          while (sibling && !sibling.matches(selector)) {
            sibling = sibling.previousElementSibling;
          }
        }
        return sibling
          ? this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              sibling
            )
          : null;
      },

      // Method untuk get all next siblings
      nextAll(selector = null) {
        const siblings = [];
        let sibling = element.nextElementSibling;
        while (sibling) {
          if (!selector || sibling.matches(selector)) {
            siblings.push(
              this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                sibling
              )
            );
          }
          sibling = sibling.nextElementSibling;
        }
        return siblings;
      },

      // Method untuk get all previous siblings
      prevAll(selector = null) {
        const siblings = [];
        let sibling = element.previousElementSibling;
        while (sibling) {
          if (!selector || sibling.matches(selector)) {
            siblings.unshift(
              this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                sibling
              )
            );
          }
          sibling = sibling.previousElementSibling;
        }
        return siblings;
      },

      // Method untuk get all siblings
      siblings(selector = null) {
        return [...this.prevAll(selector), ...this.nextAll(selector)];
      },

      // ===== FORM SPECIFIC METHODS =====

      // Method untuk check/uncheck checkbox/radio
      checked(value = null) {
        if (value === null) {
          return element.checked || false;
        }
        element.checked = !!value;
        return this;
      },

      // Method untuk disable/enable element
      disabled(value = null) {
        if (value === null) {
          return element.disabled || false;
        }
        element.disabled = !!value;
        return this;
      },

      // Method untuk submit form
      submit() {
        if (element.tagName.toLowerCase() === "form") {
          element.submit();
        }
        return this;
      },

      // Method untuk reset form
      reset() {
        if (element.tagName.toLowerCase() === "form") {
          element.reset();
        }
        return this;
      },

      // Method untuk serialize form data
      serialize() {
        if (element.tagName.toLowerCase() === "form") {
          const formData = new FormData(element);
          const result = {};
          for (let [key, value] of formData.entries()) {
            if (result[key]) {
              if (Array.isArray(result[key])) {
                result[key].push(value);
              } else {
                result[key] = [result[key], value];
              }
            } else {
              result[key] = value;
            }
          }
          return result;
        }
        return {};
      },

      // ===== ADDITIONAL UTILITIES =====

      // Method untuk trigger event
      trigger(eventType, eventData = {}) {
        const event = new CustomEvent(eventType, { detail: eventData });
        element.dispatchEvent(event);
        return this;
      },

      // Method untuk check if element matches selector
      is(selector) {
        return element.matches ? element.matches(selector) : false;
      },

      // Method untuk check if element has content
      hasContent() {
        return element.innerHTML.trim().length > 0;
      },

      // Method untuk get/set outer HTML
      outerHTML(value = null) {
        if (value === null) {
          return element.outerHTML;
        }
        element.outerHTML = value;
        return this;
      },

      // Method untuk get tag name
      tagName() {
        return element.tagName.toLowerCase();
      },

      // Method untuk get computed style
      css(property = null) {
        const computed = window.getComputedStyle(element);
        if (property) {
          return computed.getPropertyValue(property);
        }
        return computed;
      },

      // ===== ELEMENT INSPECTION METHODS =====

      // Method untuk get semua attributes element
      getAllAttrs() {
        const attrs = {};
        for (let i = 0; i < element.attributes.length; i++) {
          const attr = element.attributes[i];
          attrs[attr.name] = attr.value;
        }
        return attrs;
      },

      // Method untuk get semua data attributes
      getAllData() {
        const dataAttrs = {};
        for (let key in element.dataset) {
          dataAttrs[key] = element.dataset[key];
        }
        return dataAttrs;
      },

      // Method untuk get element properties (comprehensive info)
      getProperties() {
        return {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          dataAttributes: this.getAllData(),
          innerHTML: element.innerHTML,
          textContent: element.textContent,
          value: element.value || null,
          checked: element.checked || null,
          disabled: element.disabled || null,
          hidden: element.hidden || null,
          style: element.getAttribute("style") || null,
          position: this.position(),
          dimensions: {
            width: element.offsetWidth,
            height: element.offsetHeight,
            scrollWidth: element.scrollWidth,
            scrollHeight: element.scrollHeight,
          },
          visibility: {
            isVisible: this.isVisible(),
            isInViewport: this.isInViewport(),
            display: window.getComputedStyle(element).display,
            visibility: window.getComputedStyle(element).visibility,
            opacity: window.getComputedStyle(element).opacity,
          },
          hierarchy: {
            parent: element.parentElement
              ? element.parentElement.tagName.toLowerCase()
              : null,
            children: Array.from(element.children).map((child) =>
              child.tagName.toLowerCase()
            ),
            siblings: Array.from(element.parentElement?.children || [])
              .filter((sibling) => sibling !== element)
              .map((sibling) => sibling.tagName.toLowerCase()),
            nextSibling: element.nextElementSibling
              ? element.nextElementSibling.tagName.toLowerCase()
              : null,
            prevSibling: element.previousElementSibling
              ? element.previousElementSibling.tagName.toLowerCase()
              : null,
          },
        };
      },

      // Method untuk get full element info as JSON string
      inspect() {
        return JSON.stringify(this.getProperties(), null, 2);
      },

      // Method untuk print element info ke console
      debug() {
        // Debug logging disabled
        return this;
      },

      // Method untuk get attributes dengan filter
      getAttrs(filter = null) {
        const allAttrs = this.getAllAttrs();
        if (!filter) return allAttrs;

        const filtered = {};
        for (let [name, value] of Object.entries(allAttrs)) {
          if (
            name.includes(filter) ||
            (typeof filter === "function" && filter(name, value))
          ) {
            filtered[name] = value;
          }
        }
        return filtered;
      },

      // Method untuk check apakah element punya attribute tertentu
      hasAttrs(attributeNames) {
        if (typeof attributeNames === "string") {
          return element.hasAttribute(attributeNames);
        }
        if (Array.isArray(attributeNames)) {
          return attributeNames.every((attr) => element.hasAttribute(attr));
        }
        return false;
      },

      // Method untuk get list nama semua attributes
      getAttrNames() {
        return Array.from(element.attributes).map((attr) => attr.name);
      },

      // Method untuk get element structure tree
      getStructure(maxDepth = 3, currentDepth = 0) {
        if (currentDepth >= maxDepth) return null;

        return {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          children: Array.from(element.children)
            .map((child) => {
              const wrapped = this.constructor.prototype.wrapElement.call(
                this.constructor.prototype,
                child
              );
              return wrapped.getStructure(maxDepth, currentDepth + 1);
            })
            .filter(Boolean),
        };
      },

      // ===== JSON HANDLING METHODS =====

      // Method untuk convert element data ke JSON string
      toJSON(includeChildren = false) {
        const data = {
          tag: element.tagName.toLowerCase(),
          id: element.id || null,
          classes: Array.from(element.classList),
          attributes: this.getAllAttrs(),
          dataAttributes: this.getAllData(),
          content: {
            innerHTML: element.innerHTML,
            textContent: element.textContent,
          },
          style: element.getAttribute("style") || null,
          value: element.value || null,
        };

        if (includeChildren) {
          data.children = Array.from(element.children).map((child) => {
            const wrapped = this.constructor.prototype.wrapElement.call(
              this.constructor.prototype,
              child
            );
            return wrapped.toJSON(false); // Avoid deep recursion
          });
        }

        return JSON.stringify(data, null, 2);
      },

      // Method untuk load data dari JSON string
      fromJSON(jsonString, options = {}) {
        try {
          const data = JSON.parse(jsonString);

          // Set attributes
          if (data.attributes && options.loadAttributes !== false) {
            Object.entries(data.attributes).forEach(([name, value]) => {
              if (name !== "id" && name !== "class") {
                // Skip id and class
                element.setAttribute(name, value);
              }
            });
          }

          // Set data attributes
          if (data.dataAttributes && options.loadDataAttributes !== false) {
            Object.entries(data.dataAttributes).forEach(([key, value]) => {
              element.dataset[key] = value;
            });
          }

          // Set classes
          if (data.classes && options.loadClasses !== false) {
            element.className = data.classes.join(" ");
          }

          // Set content
          if (data.content && options.loadContent !== false) {
            if (data.content.innerHTML) {
              element.innerHTML = data.content.innerHTML;
            } else if (data.content.textContent) {
              element.textContent = data.content.textContent;
            }
          }

          // Set value for form elements
          if (data.value !== null && options.loadValue !== false) {
            element.value = data.value;
          }

          // Set inline styles
          if (data.style && options.loadStyle !== false) {
            element.setAttribute("style", data.style);
          }

          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk export form data sebagai JSON
      exportFormJSON() {
        if (element.tagName.toLowerCase() === "form") {
          const formData = this.serialize();
          return JSON.stringify(formData, null, 2);
        }
        return "{}";
      },

      // Method untuk import form data dari JSON
      importFormJSON(jsonString) {
        if (element.tagName.toLowerCase() !== "form") {
          return this;
        }

        try {
          const data = JSON.parse(jsonString);

          Object.entries(data).forEach(([name, value]) => {
            const input = element.querySelector(`[name="${name}"]`);
            if (input) {
              if (input.type === "checkbox" || input.type === "radio") {
                input.checked = !!value;
              } else {
                input.value = value;
              }
            }
          });

          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk save element state ke localStorage sebagai JSON
      saveToStorage(key, options = {}) {
        const data = {
          attributes:
            options.saveAttributes !== false ? this.getAllAttrs() : {},
          dataAttributes:
            options.saveDataAttributes !== false ? this.getAllData() : {},
          classes: options.saveClasses !== false ? this.getClasses() : [],
          content:
            options.saveContent !== false
              ? {
                  innerHTML: element.innerHTML,
                  textContent: element.textContent,
                }
              : {},
          value: options.saveValue !== false ? element.value || null : null,
          style:
            options.saveStyle !== false ? element.getAttribute("style") : null,
          timestamp: Date.now(),
        };

        try {
          localStorage.setItem(key, JSON.stringify(data));
          return this;
        } catch (error) {
          return this;
        }
      },

      // Method untuk load element state dari localStorage
      loadFromStorage(key, options = {}) {
        try {
          const data = JSON.parse(localStorage.getItem(key) || "{}");

          if (Object.keys(data).length === 0) {
            return this;
          }

          return this.fromJSON(JSON.stringify(data), options);
        } catch (error) {
          return this;
        }
      },

      // Method untuk convert element ke backup object (deep)
      createBackup() {
        return {
          element: {
            tag: element.tagName.toLowerCase(),
            attributes: this.getAllAttrs(),
            dataAttributes: this.getAllData(),
            classes: this.getClasses(),
            innerHTML: element.innerHTML,
            value: element.value || null,
          },
          timestamp: Date.now(),
          version: "1.0",
        };
      },

      // Method untuk restore dari backup object
      restoreFromBackup(backup) {
        try {
          if (!backup.element) {
            return this;
          }

          const data = backup.element;

          // Restore attributes
          Object.entries(data.attributes || {}).forEach(([name, value]) => {
            element.setAttribute(name, value);
          });

          // Restore data attributes
          Object.entries(data.dataAttributes || {}).forEach(([key, value]) => {
            element.dataset[key] = value;
          });

          // Restore classes
          if (data.classes) {
            element.className = data.classes.join(" ");
          }

          // Restore content
          if (data.innerHTML) {
            element.innerHTML = data.innerHTML;
          }

          // Restore value
          if (data.value !== null) {
            element.value = data.value;
          }

          return this;
        } catch (error) {
          return this;
        }
      },
    };
  }

  // Method untuk membuat dummy element jika element tidak ditemukan
  createDummyElement() {
    const dummy = () => this.createDummyElement();
    return {
      // Basic properties
      get innerHTML() {
        return "";
      },
      set innerHTML(value) {
        // Silent failure
      },
      get textContent() {
        return "";
      },
      set textContent(value) {
        // Silent failure
      },
      html: (value) => (value === null ? "" : dummy()),
      get element() {
        return null;
      },

      // Class manipulation
      addClass: dummy,
      removeClass: dummy,
      toggleClass: dummy,
      hasClass: () => false,
      replaceClass: dummy,
      setClasses: dummy,
      getClasses: () => [],

      // Style manipulation
      setStyle: dummy,
      setStyles: dummy,
      removeStyle: dummy,

      // Event handling
      on: dummy,

      // Visibility
      show: dummy,
      hide: dummy,
      remove: dummy,

      // Attributes
      attr: (name, value) => (value === null ? "" : dummy()),
      removeAttr: dummy,
      hasAttr: () => false,
      data: (key, value) => (value === null ? "" : dummy()),
      addID: dummy,

      // Values
      val: (value) => (value === null ? "" : dummy()),
      placeholder: (text) => (text === null ? "" : dummy()),

      // Content manipulation
      append: dummy,
      prepend: dummy,
      clear: dummy,
      before: dummy,
      after: dummy,
      replaceWith: dummy,
      wrap: dummy,
      unwrap: dummy,
      empty: dummy,
      detach: dummy,

      // Dimensions
      width: (value) => (value === null ? 0 : dummy()),
      height: (value) => (value === null ? 0 : dummy()),
      position: () => ({
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        width: 0,
        height: 0,
      }),

      // Scroll
      scrollIntoView: dummy,
      scrollTo: dummy,

      // Focus
      focus: dummy,
      blur: dummy,
      select: dummy,

      // Animation
      fadeIn: dummy,
      fadeOut: dummy,
      slideUp: dummy,
      slideDown: dummy,

      // Traversal
      parent: () => null,
      children: () => [],
      find: () => null,
      findAll: () => [],

      // Utilities
      isVisible: () => false,
      isInViewport: () => false,
      clone: dummy,

      // Sibling manipulation
      next: () => null,
      prev: () => null,
      nextAll: () => [],
      prevAll: () => [],
      siblings: () => [],

      // Form methods
      checked: (value) => (value === null ? false : dummy()),
      disabled: (value) => (value === null ? false : dummy()),
      submit: dummy,
      reset: dummy,
      serialize: () => ({}),

      // Additional utilities
      trigger: dummy,
      is: () => false,
      hasContent: () => false,
      outerHTML: (value) => (value === null ? "" : dummy()),
      tagName: () => "",
      css: () => "",

      // Element inspection
      getAllAttrs: () => ({}),
      getAllData: () => ({}),
      getProperties: () => ({
        tag: "",
        id: null,
        classes: [],
        attributes: {},
        dataAttributes: {},
        innerHTML: "",
        textContent: "",
        value: null,
        checked: null,
        disabled: null,
        hidden: null,
        style: null,
        position: { top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0 },
        dimensions: { width: 0, height: 0, scrollWidth: 0, scrollHeight: 0 },
        visibility: {
          isVisible: false,
          isInViewport: false,
          display: "none",
          visibility: "hidden",
          opacity: "0",
        },
        hierarchy: {
          parent: null,
          children: [],
          siblings: [],
          nextSibling: null,
          prevSibling: null,
        },
      }),
      inspect: () => "{}",
      debug: dummy,
      getAttrs: () => ({}),
      hasAttrs: () => false,
      getAttrNames: () => [],
      getStructure: () => ({
        tag: "",
        id: null,
        classes: [],
        attributes: {},
        children: [],
      }),
    };
  }
}
