// import { Jointabel } from "./Jointabel.js";

// Dynamic Sortable Class
export class NexaSortable {
  constructor(options = {}) {
    this.containerId = options.containerId || "sortable-list";
    this.itemClass = options.itemClass || "sortable-item";
    this.eventKey = options.eventKey || "sortableOrder";
    this.logPrefix = options.logPrefix || "Sortable";
    this.draggedElement = null;
    this.originalData = [];
    this.callback = null;
  }

  initSortable() {
    const sortableList = document.getElementById(this.containerId);
    if (!sortableList) return;

    // Get all draggable li elements
    const draggableItems = sortableList.querySelectorAll(
      'li[draggable="true"]'
    );

    // Store original data from DOM
    this.originalData = Array.from(draggableItems).map((item) => {
      const sortableItem = item.querySelector(`.${this.itemClass}`);
      return sortableItem
        ? sortableItem.getAttribute("value") || sortableItem.textContent.trim()
        : item.textContent.trim();
    });

    // Add event listeners to each li element
    draggableItems.forEach((item) => {
      item.addEventListener("dragstart", this.handleDragStart.bind(this));
      item.addEventListener("dragend", this.handleDragEnd.bind(this));
      item.addEventListener("dragover", this.handleDragOver.bind(this));
      item.addEventListener("drop", this.handleDrop.bind(this));
      item.addEventListener("dragenter", this.handleDragEnter.bind(this));
      item.addEventListener("dragleave", this.handleDragLeave.bind(this));
    });

    this.addStyles();
  }

  handleDragStart(e) {
    // Find the draggable li element (parent of the sortable-item span)
    this.draggedElement = e.target.closest('li[draggable="true"]') || e.target;
    this.draggedElement.classList.add("dragging");
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/html", this.draggedElement.outerHTML);
  }

  handleDragEnd(e) {
    if (this.draggedElement) {
      this.draggedElement.classList.remove("dragging");
    }
    this.draggedElement = null;
  }

  handleDragOver(e) {
    if (e.preventDefault) {
      e.preventDefault();
    }
    e.dataTransfer.dropEffect = "move";
    return false;
  }

  handleDragEnter(e) {
    // Find the container li element
    const containerLi =
      e.target.closest('li[draggable="true"]') ||
      e.target.closest(`.${this.itemClass}`);
    if (containerLi) {
      containerLi.classList.add("drag-over");
    }
  }

  handleDragLeave(e) {
    // Find the container li element
    const containerLi =
      e.target.closest('li[draggable="true"]') ||
      e.target.closest(`.${this.itemClass}`);
    if (containerLi) {
      containerLi.classList.remove("drag-over");
    }
  }

  handleDrop(e) {
    if (e.stopPropagation) {
      e.stopPropagation();
    }

    // Find the container li element for the drop target
    const dropTargetLi =
      e.target.closest('li[draggable="true"]') ||
      e.target.closest(`.${this.itemClass}`);

    if (
      this.draggedElement &&
      dropTargetLi &&
      this.draggedElement !== dropTargetLi
    ) {
      const parent = dropTargetLi.parentNode;
      if (
        parent &&
        parent.contains(this.draggedElement) &&
        parent.contains(dropTargetLi)
      ) {
        parent.insertBefore(this.draggedElement, dropTargetLi);
        this.updateDataIndexes();
      }
    }

    // Clean up drag-over class from all li elements
    const allLiElements = document.querySelectorAll('li[draggable="true"]');
    allLiElements.forEach((li) => li.classList.remove("drag-over"));

    return false;
  }

  updateDataIndexes() {
    const sortableList = document.getElementById(this.containerId);
    if (!sortableList) return;

    // Get all li elements instead of sortable-item spans
    const liItems = sortableList.querySelectorAll('li[draggable="true"]');
    liItems.forEach((item, index) => {
      item.setAttribute("data-index", index);
    });

    const newOrder = Array.from(liItems).map((item) => {
      const sortableItem = item.querySelector(`.${this.itemClass}`);
      return sortableItem
        ? sortableItem.getAttribute("value") || sortableItem.textContent.trim()
        : item.getAttribute("value") || item.textContent.trim();
    });
    this.saveNewOrder(newOrder);
  }

  saveNewOrder(newOrder) {
    console.log(`🔄 ${this.logPrefix} Order Updated:`);
    console.log(`📋 New Order:`, newOrder);
    console.log(
      `📊 Order Details:`,
      newOrder.map((item, index) => `${index + 1}. ${item}`)
    );

    const event = new CustomEvent(`${this.eventKey}Reordered`, {
      detail: { newOrder: newOrder },
    });
    document.dispatchEvent(event);

    // Call the callback if it exists
    if (this.callback) {
      this.callback(newOrder);
    }
  }

  getCurrentOrder() {
    const sortableList = document.getElementById(this.containerId);
    if (!sortableList) return [];

    const liItems = sortableList.querySelectorAll('li[draggable="true"]');
    const currentOrder = Array.from(liItems).map((item) => {
      const sortableItem = item.querySelector(`.${this.itemClass}`);
      return sortableItem
        ? sortableItem.getAttribute("value") || sortableItem.textContent.trim()
        : item.getAttribute("value") || item.textContent.trim();
    });
    console.log(`📋 Current ${this.logPrefix} Order:`, currentOrder);
    return currentOrder;
  }

  onCallback(callback) {
    this.callback = callback;
    return this;
  }

  addStyles() {
    if (document.querySelector(`#${this.containerId}-styles`)) return;

    const style = document.createElement("style");
    style.id = `${this.containerId}-styles`;
    style.textContent = `
      .${this.itemClass} {
        cursor: move;
      }
      
      li[draggable="true"] {
        cursor: move;
        transition: opacity 0.2s ease;
      }
      
      li[draggable="true"].dragging {
        opacity: 0.5;
      }
      
      li[draggable="true"].drag-over {
        opacity: 0.8;
        background-color: #e3f2fd;
        border: 2px dashed #2196f3;
      }
    `;

    document.head.appendChild(style);
  }
}
