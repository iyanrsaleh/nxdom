
// Class NexaCheckable — mengelola checkbox dan radio di DOM (event change + agregasi nilai)
export class NexaCheckable {
  constructor() {
    this.checkboxes = [];
    this.radios = [];
    this.allInputs = [];
    this.saveCallback = null;
    this.init();
  }

  init() {
    this.checkboxes = document.querySelectorAll('input[type="checkbox"]');
    this.radios = document.querySelectorAll('input[type="radio"]');
    this.allInputs = [...this.checkboxes, ...this.radios];
    this.bindEvents();
  }

  bindEvents() {
    this.allInputs.forEach((input) => {
      input.addEventListener("change", (e) => {
        this.handleInputChange(e);
      });
    });
  }

  handleInputChange(event) {
    const input = event.target;

    const inputData = {
      id: input.id,
      name: input.name,
      value: input.value,
      checked: input.checked,
      type: input.type,
      class: input.className,
      element: input,
    };

    const attributes = {};
    for (let attr of input.attributes) {
      attributes[attr.name] = attr.value;
    }
    inputData.attributes = attributes;

    inputData.data = this.getDataAttributes(input);

    if (this.saveCallback) {
      this.saveCallback(inputData);
    }
  }

  getAllValues() {
    const values = {};
    this.allInputs.forEach((input) => {
      const key = input.id || input.name || input.className;
      values[key] = {
        checked: input.checked,
        value: input.value,
        name: input.name,
        id: input.id,
        type: input.type,
        class: input.className,
        attributes: this.getElementAttributes(input),
        data: this.getDataAttributes(input),
      };
    });
    return values;
  }

  getElementAttributes(element) {
    const attrs = {};
    for (let attr of element.attributes) {
      attrs[attr.name] = attr.value;
    }
    return attrs;
  }

  getDataAttributes(element) {
    const data = {};
    for (let attr of element.attributes) {
      if (attr.name.startsWith("data-")) {
        const key = attr.name
          .substring(5)
          .split("-")
          .map((word, index) =>
            index === 0
              ? word
              : word.charAt(0).toUpperCase() + word.slice(1)
          )
          .join("");
        data[key] = attr.value;
      }
    }
    return data;
  }

  getCheckedValues() {
    const checkedValues = {};
    this.allInputs.forEach((input) => {
      if (input.checked) {
        const key = input.id || input.name || input.className;
        checkedValues[key] = {
          checked: input.checked,
          value: input.value,
          name: input.name,
          id: input.id,
          type: input.type,
          class: input.className,
          attributes: this.getElementAttributes(input),
          data: this.getDataAttributes(input),
        };
      }
    });
    return checkedValues;
  }

  getUncheckedValues() {
    const uncheckedValues = {};
    this.allInputs.forEach((input) => {
      if (!input.checked) {
        const key = input.id || input.name || input.className;
        uncheckedValues[key] = {
          checked: input.checked,
          value: input.value,
          name: input.name,
          id: input.id,
          type: input.type,
          class: input.className,
          attributes: this.getElementAttributes(input),
          data: this.getDataAttributes(input),
        };
      }
    });
    return uncheckedValues;
  }

  onSaveCallback(callback) {
    this.saveCallback = callback;
  }

  Elements() {
    return this.allInputs;
  }

  getCheckboxes() {
    return this.checkboxes;
  }

  getRadios() {
    return this.radios;
  }

  getElementById(id) {
    return document.getElementById(id);
  }

  getElementsByName(name) {
    return document.querySelectorAll(`input[name="${name}"]`);
  }

  getElementsByClass(className) {
    return document.querySelectorAll(`input.${className}`);
  }

  setValueById(id, checked) {
    const element = document.getElementById(id);
    if (element) {
      element.checked = checked;
      this.handleInputChange({ target: element });
    }
  }

  reset() {
    this.allInputs.forEach((input) => {
      input.checked = false;
    });
  }

  checkAll() {
    this.allInputs.forEach((input) => {
      input.checked = true;
    });
  }

  getSelectedRadio(groupName) {
    const radios = document.querySelectorAll(`input[name="${groupName}"]`);
    for (let radio of radios) {
      if (radio.checked) {
        return {
          id: radio.id,
          name: radio.name,
          value: radio.value,
          checked: radio.checked,
          type: radio.type,
          class: radio.className,
          attributes: this.getElementAttributes(radio),
          data: this.getDataAttributes(radio),
        };
      }
    }
    return null;
  }

  getRadioGroups() {
    const groups = {};
    this.radios.forEach((radio) => {
      if (!groups[radio.name]) {
        groups[radio.name] = [];
      }
      groups[radio.name].push({
        id: radio.id,
        name: radio.name,
        value: radio.value,
        checked: radio.checked,
        type: radio.type,
        class: radio.className,
        attributes: this.getElementAttributes(radio),
        data: this.getDataAttributes(radio),
      });
    });
    return groups;
  }

  getDataByAttribute(attributeName) {
    const data = {};
    this.allInputs.forEach((input) => {
      const attrValue = input.getAttribute(attributeName);
      if (attrValue) {
        data[attrValue] = {
          checked: input.checked,
          value: input.value,
          name: input.name,
          id: input.id,
          [attributeName]: attrValue,
          attributes: this.getElementAttributes(input),
          data: this.getDataAttributes(input),
        };
      }
    });
    return data;
  }
}

window.NexaCheckable = NexaCheckable;
export default NexaCheckable;
