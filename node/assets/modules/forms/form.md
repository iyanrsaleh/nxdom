# NexaUI Form & Grid Documentation

## Overview

NexaUI menyediakan sistem form yang powerful dan fleksibel yang terintegrasi dengan grid system yang responsif. Framework ini mendukung berbagai komponen form, floating labels, validation, dark mode, dan responsive design.

## Table of Contents

1. [Basic Form Structure](#basic-form-structure)
2. [Grid System](#grid-system)
3. [Form Components](#form-components)
   - [Text Inputs](#text-inputs)
   - [Textarea](#textarea)
   - [Select Dropdown](#select-dropdown)
   - [Checkbox & Radio](#checkbox--radio)
   - [Switch Toggle](#switch-toggle)
   - [Range Slider](#range-slider)
   - [Text Editor / Rich Text Editor](#text-editor--rich-text-editor)
   - [Search with Categories](#search-with-categories)
   - [Multi-Select Tags](#multi-select-tags)
4. [Button Components](#button-components)
5. [Floating Labels](#floating-labels)
6. [Input Groups](#input-groups)
7. [Form Validation](#form-validation)
8. [Responsive Design](#responsive-design)
9. [Dark Mode](#dark-mode)
10. [Advanced Components](#advanced-components)
11. [Examples](#examples)
    - [Complete Registration Form](#complete-registration-form)
    - [Profile Settings Form](#profile-settings-form)
    - [Button Showcase Form](#button-showcase-form)
    - [Range Slider Form Examples](#range-slider-form-examples)
    - [Dashboard Action Buttons Form](#dashboard-action-buttons-form)

---

## Basic Form Structure

### Form Container Classes

```html
<!-- Basic form container -->
<div class="form-nexa">
  <!-- form content -->
</div>

<!-- Form group for spacing -->
<div class="form-nexa-group">
  <label>Label Text</label>
  <input type="text" class="form-nexa-control" placeholder="Enter text" />
</div>
```

### Basic Input Classes

- `form-nexa-control` - Standard input styling
- `form-nexa-control-sm` - Small input
- `form-nexa-control-xs` - Extra small input
- `form-nexa-control-lg` - Large input

---

## Grid System

### Container & Row Structure

```html

  <div class="nx-row">
    <div class="nx-col-12">
      <!-- Full width content -->
    </div>
</div>
```

### Column Classes

#### Basic Columns

- `nx-col-1` to `nx-col-12` - Fixed width columns
- `nx-col` - Flexible width column

#### Responsive Columns

- `nx-md-1` to `nx-md-12` - Medium screens (≥768px)
- `nx-lg-1` to `nx-lg-12` - Large screens (≥992px)
- `nx-xl-1` to `nx-xl-12` - Extra large screens (≥1200px)

#### Form-specific Grid

- `form-nx-col-1` to `form-nx-col-12` - Form grid columns
- `form-nexa-row` - Form row container

### Grid Examples

```html
<!-- Two column form -->
<div class="nx-row">
  <div class="nx-col-6">
    <div class="form-nexa-group">
      <label>First Name</label>
      <input type="text" class="form-nexa-control" />
    </div>
  </div>
  <div class="nx-col-6">
    <div class="form-nexa-group">
      <label>Last Name</label>
      <input type="text" class="form-nexa-control" />
    </div>
  </div>
</div>
```

---

## Form Components

### Text Inputs

```html
<!-- Standard input -->
<div class="form-nexa-group">
  <label>Email</label>
  <input type="text" class="form-nexa-control" placeholder="Enter email" />
</div>

<!-- Input with icon -->
<div class="form-nexa-group form-nexa-icon">
  <input type="email" class="form-nexa-control" placeholder="Enter email" />
  <i class="material-symbols-outlined">email</i>
  <label>Email</label>
</div>
```

### Textarea

```html
<div class="form-nexa-group">
  <label>Message</label>
  <textarea
    class="form-nexa-control"
    rows="4"
    placeholder="Enter message"
  ></textarea>
</div>
```

### Select Dropdown

```html
<!-- Basic select -->
<div class="form-nexa-group">
  <label>Country</label>
  <select class="form-nexa-control">
    <option value="">Select country</option>
    <option value="id">Indonesia</option>
    <option value="us">United States</option>
  </select>
</div>

<!-- Select with icon -->
<div class="form-nexa-group form-nexa-select-icon">
  <select class="form-nexa-control">
    <option value="">Select country</option>
    <option value="id">Indonesia</option>
  </select>
  <i class="material-symbols-outlined">public</i>
  <label>Country</label>
</div>
```

### Checkbox & Radio

#### Modern Checkbox

```html
<div class="nx-checkbox-grid">
  <div class="nx-checkbox-item">
    <input type="checkbox" id="check1" />
    <label for="check1">
      <span class="nx-checkmark"></span>
      Option 1
    </label>
  </div>
</div>
```

#### Modern Radio

```html
<div class="nx-radio-grid">
  <div class="nx-radio-item">
    <input type="radio" id="radio1" name="option" />
    <label for="radio1">
      <span class="nx-radio-mark"></span>
      Option 1
    </label>
  </div>
</div>
```

#### Classic Checkbox/Radio

```html
<!-- Classic checkbox -->
<div class="form-nexa-check">
  <input type="checkbox" id="checkbox1" class="form-nexa-check-input" />
  <label for="checkbox1" class="form-nexa-check-label">Check this option</label>
</div>

<!-- Classic radio -->
<div class="form-nexa-radio">
  <input type="radio" id="radio1" name="group" class="form-nexa-radio-input" />
  <label for="radio1" class="form-nexa-radio-label">Radio option</label>
</div>
```

### Switch Toggle

```html
<!-- Modern switch -->
<div class="nx-switch-grid">
  <div class="nx-switch-item">
    <input type="checkbox" id="switch1" />
    <label for="switch1">
      <span class="nx-switch"></span>
      Enable notifications
    </label>
  </div>
</div>

<!-- Classic switch -->
<div class="form-nexa-switch-container">
  <div class="form-nexa-switch">
    <input type="checkbox" id="switch1" class="form-nexa-switch-input" />
    <span class="form-nexa-switch-slider"></span>
  </div>
  <label for="switch1" class="form-nexa-switch-label">Enable feature</label>
</div>
```

### Range Slider

```html
<!-- Basic range slider -->
<div class="form-nexa-group">
  <label>Volume</label>
  <input type="range" class="form-nexa-range" min="0" max="100" value="50" />
</div>

<!-- Range with value display -->
<div class="form-nexa-group">
  <label>Price Range: <span id="price-value">$500</span></label>
  <input
    type="range"
    class="form-nexa-range"
    min="0"
    max="1000"
    value="500"
    oninput="document.getElementById('price-value').textContent='$'+this.value"
  />
</div>

<!-- Multiple range sliders -->
<div class="form-nexa-group">
  <label>RGB Color Mixer</label>
  <div style="margin-bottom: 10px;">
    <label style="color: red;">Red: <span id="red-value">128</span></label>
    <input
      type="range"
      class="form-nexa-range"
      min="0"
      max="255"
      value="128"
      style="accent-color: red;"
    />
  </div>
  <div style="margin-bottom: 10px;">
    <label style="color: green;"
      >Green: <span id="green-value">128</span></label
    >
    <input
      type="range"
      class="form-nexa-range"
      min="0"
      max="255"
      value="128"
      style="accent-color: green;"
    />
  </div>
  <div>
    <label style="color: blue;">Blue: <span id="blue-value">128</span></label>
    <input
      type="range"
      class="form-nexa-range"
      min="0"
      max="255"
      value="128"
      style="accent-color: blue;"
    />
  </div>
</div>

<!-- Range with floating label -->
<div class="form-nexa-floating">
  <input
    type="range"
    class="form-nexa-range"
    min="0"
    max="100"
    value="75"
    placeholder=" "
  />
  <label>Brightness</label>
</div>
```

---

## Button Components

NexaUI menyediakan sistem button yang comprehensive dengan berbagai varian, size, state, dan efek visual.

### Basic Button Structure

```html
<!-- Base button class -->
<button class="nx-btn">Default Button</button>

<!-- Button with specific style -->
<button class="nx-btn-primary">Primary Button</button>
```

### Button Color Variants

#### Primary Buttons

```html
<!-- Standard primary with gradient -->
<button class="nx-btn-primary">Primary</button>

<!-- Success button with gradient -->
<button class="nx-btn-success">Success</button>

<!-- Danger button -->
<button class="nx-btn-danger">Delete</button>

<!-- Info button with gradient -->
<button class="nx-btn-info">Information</button>

<!-- Warning button -->
<button class="nx-btn-warning">Warning</button>

<!-- Link style button -->
<button class="nx-btn-link">Link Button</button>
```

#### Secondary Buttons

```html
<!-- Standard secondary -->
<button class="nx-btn-secondary">Secondary</button>

<!-- Secondary variants -->
<button class="nx-btn-secondary-light">Light Secondary</button>
<button class="nx-btn-secondary-dark">Dark Secondary</button>
<button class="nx-btn-secondary-outline">Outline Secondary</button>
```

#### Neutral Buttons

```html
<!-- Neutral color variants -->
<button class="nx-btn-white">White</button>
<button class="nx-btn-light">Light</button>
<button class="nx-btn-dark">Dark</button>
<button class="nx-btn-black">Black</button>
```

#### Special Button Types

```html
<!-- Ghost button with border -->
<button class="nx-btn-ghost">Ghost Button</button>

<!-- Text button with underline effect -->
<button class="nx-btn-text">Text Button</button>
```

### Button Sizes

#### Standard Sizes

```html
<!-- Size variations -->
<button class="nx-btn-primary is-small">Small</button>
<button class="nx-btn-primary">Normal (Default)</button>
<button class="nx-btn-primary is-normal">Normal</button>
<button class="nx-btn-primary is-medium">Medium</button>
<button class="nx-btn-primary is-large">Large</button>
```

#### Custom Sizes

```html
<!-- Custom size classes -->
<button class="nx-btn-primary custom-size-sm">Extra Small</button>
<button class="nx-btn-primary custom-size-xl">Extra Large</button>
```

### Button with Icons

#### Basic Icon Button

```html
<!-- Button with icon -->
<button class="nx-btn-success icon-button">
  <i class="material-symbols-outlined">check</i>
  <span>Save Changes</span>
</button>

<!-- Danger button with icon -->
<button class="nx-btn-danger icon-button">
  <i class="material-symbols-outlined">delete</i>
  <span>Delete Item</span>
</button>
```

#### Icon Only Button

```html
<!-- Circular icon-only button -->
<button class="nx-btn-primary nx-btn-icon-only">
  <i class="material-symbols-outlined">add</i>
</button>

<!-- Different color variants -->
<button class="nx-btn-danger nx-btn-icon-only">
  <i class="material-symbols-outlined">close</i>
</button>
```

### Button States

#### Loading State

```html
<!-- Loading button with spinner -->
<button class="nx-btn-primary loading">Loading...</button>

<!-- Loading with custom text -->
<button class="nx-btn-primary loading-text">
  <span class="spinner"></span>
  Processing...
</button>
```

#### Disabled State

```html
<!-- Disabled button -->
<button class="nx-btn-primary" disabled>Disabled Button</button>

<!-- Disabled with special animation -->
<button class="nx-btn-primary nx-btn-loading" disabled>Processing...</button>
```

### Button Variants

#### Outline Buttons

```html
<!-- Outline variants -->
<button class="nx-btn-primary outline">Primary Outline</button>
<button class="nx-btn-success outline">Success Outline</button>
<button class="nx-btn-danger outline">Danger Outline</button>
<button class="nx-btn-info outline">Info Outline</button>
```

#### Full Width Buttons

```html
<!-- Full width button -->
<button class="nx-btn-primary full-width">Full Width Button</button>

<!-- Full width with icon -->
<button class="nx-btn-success full-width icon-button">
  <i class="material-symbols-outlined">save</i>
  Save Changes
</button>
```

#### Rounded Buttons

```html
<!-- Rounded variants -->
<button class="nx-btn-primary rounded-xl">Extra Rounded</button>
<button class="nx-btn-primary rounded-full">Pill Button</button>
```

### Windows Style Buttons

#### Basic Windows Colors

```html
<!-- Windows system colors -->
<button class="nx-btn-win-blue">Windows Blue</button>
<button class="nx-btn-win-purple">Windows Purple</button>
<button class="nx-btn-win-teal">Windows Teal</button>
<button class="nx-btn-win-green">Windows Green</button>
```

#### Windows System Buttons

```html
<!-- System style buttons -->
<button class="nx-btn-win-system">System Button</button>
<button class="nx-btn-win-accent">Accent Button</button>
<button class="nx-btn-win-accent-light">Light Accent</button>
<button class="nx-btn-win-modern">Modern Style</button>
```

#### Windows Status Buttons

```html
<!-- Status buttons -->
<button class="nx-btn-win-error">Error</button>
<button class="nx-btn-win-warning">Warning</button>
<button class="nx-btn-win-success">Success</button>
```

### Custom Buttons

#### Variable-based Custom Buttons

```html
<!-- Custom button with CSS variables -->
<button class="nx-btn-custom" style="--button-color: #e74c3c;">
  Custom Color
</button>

<!-- Gradient custom button -->
<button class="nx-btn-custom gradient">Gradient Button</button>

<!-- Custom outline -->
<button class="nx-btn-custom outline" style="--button-color: #9b59b6;">
  Custom Outline
</button>
```

#### Custom Size Variants

```html
<!-- Custom size buttons -->
<button class="nx-btn-custom size-xs">Extra Small</button>
<button class="nx-btn-custom size-xl">Extra Large</button>
```

### Responsive Buttons

```html
<!-- Responsive button that becomes full width on mobile -->
<button class="nx-btn-primary responsive">Responsive Button</button>

<!-- Dark button with responsive behavior -->
<button class="nx-btn-dark responsive">Mobile-Friendly</button>
```

### Accessibility Features

```html
<!-- High contrast button for accessibility -->
<button class="nx-btn-dark high-contrast">High Contrast</button>

<!-- Button with proper ARIA attributes -->
<button
  class="nx-btn-primary"
  aria-label="Save document"
  aria-describedby="save-help"
>
  Save
</button>
<div id="save-help" class="sr-only">
  Saves the current document to your account
</div>
```

### Button Groups

```html
<!-- Button group example -->
<div class="example-buttons">
  <button class="nx-btn-primary">First</button>
  <button class="nx-btn-secondary">Second</button>
  <button class="nx-btn-danger">Third</button>
</div>
```

### Button Examples in Forms

#### Form Action Buttons

```html
<!-- Form action buttons -->
<div class="form-nexa-wizard-buttons">
  <button type="button" class="nx-btn-secondary">Cancel</button>
  <button type="submit" class="nx-btn-primary">Submit</button>
</div>

<!-- Form with loading state -->
<div class="form-nexa-wizard-buttons">
  <button type="button" class="nx-btn-secondary" disabled>Cancel</button>
  <button type="submit" class="nx-btn-primary loading">
    <span class="spinner"></span>
    Submitting...
  </button>
</div>
```

#### Input Buttons

```html
<!-- Input type buttons styled consistently -->
<input type="submit" class="nx-btn-primary" value="Submit Form" />
<input type="reset" class="nx-btn-secondary" value="Reset Form" />
```

### Advanced Button Features

#### Button with Ripple Effect

```html
<!-- Buttons have automatic ripple effect on click -->
<button class="nx-btn-primary">Click Me for Ripple Effect</button>
```

#### Button with Hover Animations

```html
<!-- Text button with underline animation -->
<button class="nx-btn-text">Hover for Animation</button>

<!-- Full width button with icon animation -->
<button class="nx-btn-success full-width icon-button">
  <i class="material-symbols-outlined">arrow_forward</i>
  Hover to See Icon Move
</button>
```

### Button Utility Classes

#### Spacing and Layout

```html
<!-- Buttons automatically have proper spacing in button groups -->
<div class="example-buttons">
  <button class="nx-btn-primary">Button 1</button>
  <button class="nx-btn-secondary">Button 2</button>
  <button class="nx-btn-info">Button 3</button>
</div>
```

#### Error State Button

```html
<!-- Button with error styling -->
<button class="nx-btn-primary nx-btn-error">Error State Button</button>
```

### JavaScript Integration Examples

#### Dynamic Button States

```javascript
// Toggle button loading state
function toggleButtonLoading(button, loading = true) {
  if (loading) {
    button.classList.add("loading");
    button.disabled = true;
    button.innerHTML = '<span class="spinner"></span> Loading...';
  } else {
    button.classList.remove("loading");
    button.disabled = false;
    button.innerHTML = "Submit";
  }
}

// Example usage
const submitButton = document.querySelector(".nx-btn-primary");
submitButton.addEventListener("click", () => {
  toggleButtonLoading(submitButton, true);

  // Simulate async operation
  setTimeout(() => {
    toggleButtonLoading(submitButton, false);
  }, 2000);
});
```

#### Button State Management

```javascript
// Button state manager
class ButtonStateManager {
  constructor(button) {
    this.button = button;
    this.originalText = button.textContent;
  }

  setLoading(text = "Loading...") {
    this.button.classList.add("loading");
    this.button.disabled = true;
    this.button.innerHTML = `<span class="spinner"></span> ${text}`;
  }

  setSuccess(text = "Success!") {
    this.button.classList.remove("loading");
    this.button.classList.add("nx-btn-success");
    this.button.textContent = text;

    setTimeout(() => this.reset(), 2000);
  }

  setError(text = "Error!") {
    this.button.classList.remove("loading");
    this.button.classList.add("nx-btn-error");
    this.button.textContent = text;

    setTimeout(() => this.reset(), 2000);
  }

  reset() {
    this.button.classList.remove("loading", "nx-btn-success", "nx-btn-error");
    this.button.disabled = false;
    this.button.textContent = this.originalText;
  }
}

// Usage example
const button = document.querySelector(".nx-btn-primary");
const buttonManager = new ButtonStateManager(button);

button.addEventListener("click", async () => {
  buttonManager.setLoading("Saving...");

  try {
    await saveData(); // Your async operation
    buttonManager.setSuccess("Saved!");
  } catch (error) {
    buttonManager.setError("Save Failed!");
  }
});
```

#### Icon Button Toggle

```javascript
// Toggle icon button functionality
function initToggleButtons() {
  const toggleButtons = document.querySelectorAll("[data-toggle]");

  toggleButtons.forEach((button) => {
    const iconElement = button.querySelector("i");
    const textElement = button.querySelector("span");

    button.addEventListener("click", () => {
      const isToggled = button.classList.contains("toggled");

      if (isToggled) {
        button.classList.remove("toggled");
        iconElement.textContent = button.dataset.iconOff || "visibility_off";
        textElement.textContent = button.dataset.textOff || "Hidden";
      } else {
        button.classList.add("toggled");
        iconElement.textContent = button.dataset.iconOn || "visibility";
        textElement.textContent = button.dataset.textOn || "Visible";
      }
    });
  });
}

// Initialize toggle buttons
document.addEventListener("DOMContentLoaded", initToggleButtons);
```

### Button Best Practices

#### 1. Accessibility

- Always use descriptive text or aria-labels
- Ensure sufficient color contrast
- Provide keyboard navigation support
- Use appropriate button types (button, submit, reset)

#### 2. Visual Hierarchy

- Use primary buttons for main actions
- Use secondary buttons for alternative actions
- Use danger buttons for destructive actions
- Limit the number of primary buttons per view

#### 3. Loading States

- Always provide feedback for async actions
- Disable buttons during processing
- Use clear loading text and animations

#### 4. Responsive Design

- Test buttons on various screen sizes
- Use full-width buttons on mobile when appropriate
- Ensure touch targets are at least 44px

#### 5. Icon Usage

- Use icons consistently across the application
- Ensure icons are meaningful and recognizable
- Provide text labels alongside icons when possible

### CSS Custom Properties

You can customize button colors using CSS custom properties:

```css
/* Custom button colors */
.custom-brand-button {
  --button-color: #your-brand-color;
}

/* Custom button with specific properties */
.special-button {
  --button-color: #ff6b6b;
  --button-hover-color: #ff5252;
  --button-text-color: white;
}
```

---

## Floating Labels

Floating labels provide a modern, space-efficient form design.

### Basic Floating Label

```html
<div class="form-nexa-floating">
  <input type="text" class="form-nexa-control" placeholder=" " />
  <label>Full Name</label>
</div>
```

### Floating Label with Icon

```html
<div class="form-nexa-floating form-nexa-icon">
  <input type="email" class="form-nexa-control" placeholder=" " />
  <i class="material-symbols-outlined">email</i>
  <label>Email Address</label>
</div>
```

### Floating Label Select

```html
<div class="form-nexa-floating form-nexa-select-icon">
  <select class="form-nexa-control">
    <option value="">Select option</option>
    <option value="1">Option 1</option>
  </select>
  <i class="material-symbols-outlined">arrow_drop_down</i>
  <label>Select Option</label>
</div>
```

---

## Input Groups

Input groups allow you to combine inputs with text, buttons, or other elements.

### Text Prefix/Suffix

```html
<!-- Currency input -->
<div class="form-nexa-input-group">
  <span class="form-nexa-input-group-text">Rp</span>
  <input type="number" class="form-nexa-control" placeholder="0" />
</div>

<!-- URL input -->
<div class="form-nexa-input-group">
  <input type="text" class="form-nexa-control" placeholder="yoursite" />
  <span class="form-nexa-input-group-text">.com</span>
</div>
```

### With Color Picker

```html
<div class="form-nexa-input-group">
  <span class="form-nexa-input-group-text">
    <input type="color" class="form-nexa-color-picker" value="#007bff" />
  </span>
  <input type="text" class="form-nexa-control" placeholder="Enter color" />
</div>
```

### Floating Label Input Group

```html
<div class="form-nexa-floating form-nexa-input-group-icon">
  <div class="form-nexa-input-group">
    <span class="form-nexa-input-group-text">Rp</span>
    <input type="number" class="form-nexa-control" placeholder=" " />
  </div>
  <label>Price</label>
</div>
```

---

## Form Validation

### Validation States

```html
<!-- Valid input -->
<div class="form-nexa-group">
  <input type="email" class="form-nexa-control is-valid" />
  <div class="valid-feedback">Looks good!</div>
</div>

<!-- Invalid input -->
<div class="form-nexa-group">
  <input type="email" class="form-nexa-control is-invalid" />
  <div class="invalid-feedback">Please enter a valid email.</div>
</div>

<!-- Error state with form-error class -->
<div class="form-nexa-group form-error">
  <input type="text" class="form-nexa-control" />
  <small class="error-message">This field is required</small>
</div>
```

### JavaScript Validation Example

```javascript
// Add validation to form
function validateForm() {
  const input = document.querySelector(".form-nexa-control");
  const container = input.closest(".form-nexa-group");

  if (!input.value.trim()) {
    container.classList.add("form-error");
    input.classList.add("is-invalid");

    let errorMsg = container.querySelector(".error-message");
    if (!errorMsg) {
      errorMsg = document.createElement("small");
      errorMsg.className = "error-message";
      container.appendChild(errorMsg);
    }
    errorMsg.textContent = "This field is required";
  } else {
    container.classList.remove("form-error");
    input.classList.remove("is-invalid");
    input.classList.add("is-valid");
  }
}
```

---

## Responsive Design

### Responsive Grid Layout

```html
<div class="nx-container">
  <div class="nx-row">
    <!-- Full width on mobile, half on tablet, third on desktop -->
    <div class="nx-col-12 nx-md-6 nx-lg-4">
      <div class="form-nexa-floating">
        <input type="text" class="form-nexa-control" placeholder=" " />
        <label>First Name</label>
      </div>
    </div>
    <div class="nx-col-12 nx-md-6 nx-lg-4">
      <div class="form-nexa-floating">
        <input type="text" class="form-nexa-control" placeholder=" " />
        <label>Last Name</label>
      </div>
    </div>
    <div class="nx-col-12 nx-lg-4">
      <div class="form-nexa-floating">
        <input type="email" class="form-nexa-control" placeholder=" " />
        <label>Email</label>
      </div>
    </div>
  </div>
</div>
```

### Mobile-Optimized Inputs

```html
<!-- Responsive input with proper mobile sizing -->
<div class="form-nexa-group">
  <input
    type="text"
    class="form-nexa-control form-nexa-control-responsive"
    placeholder="Mobile friendly"
  />
</div>
```

### Responsive Utilities

- `nx-hide-mobile` - Hide on mobile devices
- `nx-show-mobile` - Show only on mobile
- `form-nexa-hide-xs` - Hide on extra small screens
- `form-nexa-text-center-xs` - Center text on mobile

---

## Dark Mode

Dark mode is automatically applied based on user's system preference or can be manually triggered.

### Dark Mode Features

- Automatic detection via `@media (prefers-color-scheme: dark)`
- Dark backgrounds and appropriate text colors
- Proper contrast ratios for accessibility
- Dark mode support for all components

### Example CSS for Manual Dark Mode

```css
[data-theme="dark"] .form-nexa-control {
  background-color: #1e1e1e;
  border-color: #404040;
  color: #e0e0e0;
}

[data-theme="dark"] .form-nexa-floating label.active {
  background-color: #1e1e1e !important;
  color: #cccccc !important;
}
```

---

## Advanced Components

### File Upload

```html
<div class="form-nexa-file">
  <input type="file" class="form-nexa-file-input" multiple />
  <label class="form-nexa-file-label">
    <i class="material-symbols-outlined">cloud_upload</i>
    <div class="form-nexa-file-text">Choose files or drag here</div>
    <div class="form-nexa-file-button">Browse Files</div>
  </label>
  <div class="form-nexa-file-list">
    <!-- File list will be populated by JavaScript -->
  </div>
</div>
```

### Search with Dropdown

```html
<div class="form-nexa-search">
  <div class="form-nexa-search-container">
    <input type="text" class="form-nexa-control" placeholder="Search..." />
    <div class="form-nexa-search-dropdown">
      <div class="form-nexa-search-items">
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">search</i>
          Search result 1
        </div>
      </div>
    </div>
  </div>
</div>
```

### Date Picker with Icon

```html
<div class="form-nexa-floating form-nexa-date form-nexa-icon">
  <input type="date" class="form-nexa-control" placeholder=" " />
  <i class="material-symbols-outlined">calendar_today</i>
  <label>Select Date</label>
</div>
```

### Text Editor / Rich Text Editor

NexaUI mendukung text editor dengan styling khusus untuk Quill.js atau editor WYSIWYG lainnya.

```html
<!-- Basic Text Editor -->
<div class="form-nexa-editor">
  <label for="description">Product Description</label>
  <div id="description" class="form-nexa-editor-control">
    <!-- Quill.js editor akan di-render di sini -->
  </div>
</div>

<!-- Text Editor dengan Quill.js -->
<div class="form-nexa-editor">
  <label>Blog Post Content</label>
  <div id="quill-editor" class="form-nexa-editor-control">
    <p>Hello World!</p>
    <p>Some initial <strong>content</strong> here...</p>
  </div>
</div>

<!-- Text Editor untuk Form -->
<div class="nx-row">
  <div class="nx-col-12">
    <div class="form-nexa-editor">
      <label>Message</label>
      <div id="message-editor" class="form-nexa-editor-control"></div>
      <small class="form-text text-muted"
        >Use toolbar above to format your message</small
      >
    </div>
  </div>
</div>
```

### Search with Categories

Search dropdown dengan kategori untuk mengorganisir hasil pencarian.

```html
<!-- Search dengan kategori -->
<div class="form-nexa-search">
  <div class="form-nexa-search-container">
    <input
      type="text"
      class="form-nexa-control"
      placeholder="Search products, users, orders..."
    />
    <div class="form-nexa-search-dropdown">
      <!-- Kategori Products -->
      <div class="form-nexa-search-category">Products</div>
      <div class="form-nexa-search-items">
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">inventory_2</i>
          Laptop Gaming ASUS ROG
        </div>
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">phone_android</i>
          Smartphone Samsung Galaxy
        </div>
      </div>

      <!-- Kategori Users -->
      <div class="form-nexa-search-category">Users</div>
      <div class="form-nexa-search-items">
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">person</i>
          John Doe - Admin
        </div>
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">person</i>
          Jane Smith - Customer
        </div>
      </div>

      <!-- Kategori Orders -->
      <div class="form-nexa-search-category">Orders</div>
      <div class="form-nexa-search-items">
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">receipt</i>
          Order #12345 - Pending
        </div>
        <div class="form-nexa-search-item">
          <i class="material-symbols-outlined">receipt</i>
          Order #12346 - Completed
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Search dengan kategori dan icon -->
<div class="form-nexa-search">
  <div class="form-nexa-floating form-nexa-icon">
    <div class="form-nexa-search-container">
      <input type="text" class="form-nexa-control" placeholder=" " />
      <div class="form-nexa-search-dropdown">
        <div class="form-nexa-search-category">Recent Searches</div>
        <div class="form-nexa-search-items">
          <div class="form-nexa-search-item">
            <i class="material-symbols-outlined">history</i>
            "javascript tutorial"
          </div>
          <div class="form-nexa-search-item">
            <i class="material-symbols-outlined">history</i>
            "react components"
          </div>
        </div>

        <div class="form-nexa-search-category">Suggestions</div>
        <div class="form-nexa-search-items">
          <div class="form-nexa-search-item">
            <i class="material-symbols-outlined">lightbulb</i>
            "vue.js framework"
          </div>
          <div class="form-nexa-search-item">
            <i class="material-symbols-outlined">lightbulb</i>
            "css grid layout"
          </div>
        </div>
      </div>
    </div>
    <i class="material-symbols-outlined">search</i>
    <label>Search Documentation</label>
  </div>
</div>
```

### Multi-Select Tags

Multi-select dengan tags untuk memilih beberapa item sekaligus.

```html
<!-- Multi-select dengan tags -->
<div class="form-nexa-group">
  <label>Skills</label>
  <div class="form-nexa-search-tags">
    <!-- Selected tags -->
    <div class="form-nexa-search-tag">
      JavaScript
      <span class="form-nexa-search-tag-remove">×</span>
    </div>
    <div class="form-nexa-search-tag">
      React
      <span class="form-nexa-search-tag-remove">×</span>
    </div>
    <div class="form-nexa-search-tag">
      Node.js
      <span class="form-nexa-search-tag-remove">×</span>
    </div>

    <!-- Input untuk menambah tag baru -->
    <div class="form-nexa-search-input-container">
      <input
        type="text"
        class="form-nexa-search-input"
        placeholder="Add skill..."
      />
    </div>
  </div>

  <!-- Dropdown suggestions -->
  <div class="form-nexa-search-dropdown">
    <div class="form-nexa-search-category">Popular Skills</div>
    <div class="form-nexa-search-items">
      <div class="form-nexa-search-item">
        <i class="material-symbols-outlined">code</i>
        Python
      </div>
      <div class="form-nexa-search-item">
        <i class="material-symbols-outlined">code</i>
        TypeScript
      </div>
      <div class="form-nexa-search-item">
        <i class="material-symbols-outlined">code</i>
        Vue.js
      </div>
    </div>
  </div>
</div>

<!-- Multi-select countries -->
<div class="form-nexa-floating">
  <div class="form-nexa-search-tags">
    <div class="form-nexa-search-tag">
      🇮🇩 Indonesia
      <span class="form-nexa-search-tag-remove">×</span>
    </div>
    <div class="form-nexa-search-tag">
      🇺🇸 United States
      <span class="form-nexa-search-tag-remove">×</span>
    </div>
    <div class="form-nexa-search-input-container">
      <input type="text" class="form-nexa-search-input" placeholder=" " />
    </div>
  </div>
  <label>Select Countries</label>
</div>
```

### Form Wizard/Steps

```html
<div class="form-nexa-wizard">
  <div class="form-nexa-wizard-progress">
    <div class="form-nexa-wizard-progress-step active">
      <div class="step-number">1</div>
      <div class="step-text">Personal Info</div>
    </div>
    <div class="form-nexa-wizard-progress-step">
      <div class="step-number">2</div>
      <div class="step-text">Address</div>
    </div>
    <div class="form-nexa-wizard-progress-step">
      <div class="step-number">3</div>
      <div class="step-text">Review</div>
    </div>
  </div>

  <div class="form-nexa-wizard-step active">
    <!-- Step 1 content -->
  </div>

  <div class="form-nexa-wizard-buttons">
    <button class="form-nexa-btn secondary">Previous</button>
    <button class="form-nexa-btn primary">Next</button>
  </div>
</div>
```

---

## Examples

### Complete Registration Form

```html
<div class="nx-container">
  <div class="nx-row">
    <div class="nx-col-12 nx-md-8 nx-lg-6" style="margin: 0 auto;">
      <form class="form-nexa">
        <h2>Create Account</h2>

        <!-- Personal Information -->
        <div class="nx-row">
          <div class="nx-col-12">
            <label class="text-secondary">Personal Information</label>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating">
              <input
                type="text"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <label>First Name</label>
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating">
              <input
                type="text"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <label>Last Name</label>
            </div>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="nx-row">
          <div class="nx-col-12">
            <label class="text-secondary">Contact Information</label>
          </div>
          <div class="nx-col-12">
            <div class="form-nexa-floating form-nexa-icon">
              <input
                type="email"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <i class="material-symbols-outlined">email</i>
              <label>Email Address</label>
            </div>
          </div>
          <div class="nx-col-12">
            <div class="form-nexa-floating form-nexa-icon">
              <input
                type="tel"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <i class="material-symbols-outlined">phone</i>
              <label>Phone Number</label>
            </div>
          </div>
        </div>

        <!-- Password -->
        <div class="nx-row">
          <div class="nx-col-12">
            <label class="text-secondary">Security</label>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating form-nexa-icon">
              <input
                type="password"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <i class="material-symbols-outlined">lock</i>
              <label>Password</label>
              <i class="material-symbols-outlined password-toggle"
                >visibility</i
              >
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating form-nexa-icon">
              <input
                type="password"
                class="form-nexa-control"
                placeholder=" "
                required
              />
              <i class="material-symbols-outlined">lock</i>
              <label>Confirm Password</label>
            </div>
          </div>
        </div>

        <!-- Preferences -->
        <div class="nx-row">
          <div class="nx-col-12">
            <label class="text-secondary">Preferences</label>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating form-nexa-select-icon">
              <select class="form-nexa-control" required>
                <option value="">Select country</option>
                <option value="id">Indonesia</option>
                <option value="us">United States</option>
                <option value="uk">United Kingdom</option>
              </select>
              <i class="material-symbols-outlined">public</i>
              <label>Country</label>
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-floating">
              <input type="date" class="form-nexa-control" placeholder=" " />
              <label>Date of Birth</label>
            </div>
          </div>
        </div>

        <!-- Preferences -->
        <div class="nx-row">
          <div class="nx-col-12">
            <label class="text-secondary">Additional Preferences</label>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Experience Level: <span id="exp-value">5</span>/10</label>
              <input
                type="range"
                class="form-nexa-range"
                min="1"
                max="10"
                value="5"
                oninput="document.getElementById('exp-value').textContent=this.value"
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label
                >Notification Frequency:
                <span id="freq-value">Daily</span></label
              >
              <input
                type="range"
                class="form-nexa-range"
                min="1"
                max="4"
                value="1"
                oninput="
                  const frequencies = ['', 'Daily', 'Weekly', 'Monthly', 'Never'];
                  document.getElementById('freq-value').textContent=frequencies[this.value];
                "
              />
            </div>
          </div>
        </div>

        <!-- Terms and Newsletter -->
        <div class="nx-row">
          <div class="nx-col-12">
            <div class="nx-checkbox-grid">
              <div class="nx-checkbox-item">
                <input type="checkbox" id="terms" required />
                <label for="terms">
                  <span class="nx-checkmark"></span>
                  I agree to the Terms of Service and Privacy Policy
                </label>
              </div>
              <div class="nx-checkbox-item">
                <input type="checkbox" id="newsletter" />
                <label for="newsletter">
                  <span class="nx-checkmark"></span>
                  Subscribe to newsletter
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="nx-row">
          <div class="nx-col-12">
            <button type="submit" class="nx-btn-primary full-width icon-button">
              <i class="material-symbols-outlined">person_add</i>
              Create Account
            </button>
            <!-- Alternative buttons -->
            <div class="form-nexa-wizard-buttons" style="margin-top: 1rem;">
              <button type="button" class="nx-btn-secondary">
                <i class="material-symbols-outlined">arrow_back</i>
                Back to Login
              </button>
              <button type="reset" class="nx-btn-text">Reset Form</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
```

### Profile Settings Form

```html
<div class="nx-container">
  <div class="nx-row">
    <div class="nx-col-12 nx-lg-8">
      <!-- Profile Picture -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Profile Settings</h3>
        </div>
        <div class="nx-col-12 nx-md-4">
          <div class="form-nexa-file">
            <input type="file" class="form-nexa-file-input" accept="image/*" />
            <label class="form-nexa-file-label">
              <i class="material-symbols-outlined">photo_camera</i>
              <div class="form-nexa-file-text">Upload Profile Photo</div>
              <div class="form-nexa-file-button">Choose Photo</div>
            </label>
          </div>
        </div>
        <div class="nx-col-12 nx-md-8">
          <div class="form-nexa-floating">
            <input
              type="text"
              class="form-nexa-control"
              placeholder=" "
              value="John Doe"
            />
            <label>Display Name</label>
          </div>
          <div class="form-nexa-floating">
            <textarea class="form-nexa-control" placeholder=" " rows="3">
Software Developer passionate about creating amazing user experiences.</textarea
            >
            <label>Bio</label>
          </div>
        </div>
      </div>

      <!-- Notification Settings -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h4>Notifications</h4>
        </div>
        <div class="nx-col-12">
          <div class="nx-switch-grid">
            <div class="nx-switch-item">
              <input type="checkbox" id="email-notif" checked />
              <label for="email-notif">
                <span class="nx-switch"></span>
                Email Notifications
              </label>
            </div>
            <div class="nx-switch-item">
              <input type="checkbox" id="push-notif" />
              <label for="push-notif">
                <span class="nx-switch"></span>
                Push Notifications
              </label>
            </div>
            <div class="nx-switch-item">
              <input type="checkbox" id="sms-notif" />
              <label for="sms-notif">
                <span class="nx-switch"></span>
                SMS Notifications
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Theme Settings -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h4>Appearance</h4>
        </div>
        <div class="nx-col-12">
          <div class="nx-radio-grid">
            <div class="nx-radio-item">
              <input type="radio" id="theme-light" name="theme" checked />
              <label for="theme-light">
                <span class="nx-radio-mark"></span>
                Light Mode
              </label>
            </div>
            <div class="nx-radio-item">
              <input type="radio" id="theme-dark" name="theme" />
              <label for="theme-dark">
                <span class="nx-radio-mark"></span>
                Dark Mode
              </label>
            </div>
            <div class="nx-radio-item">
              <input type="radio" id="theme-auto" name="theme" />
              <label for="theme-auto">
                <span class="nx-radio-mark"></span>
                Auto (System)
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="nx-row">
        <div class="nx-col-12">
          <div class="form-nexa-wizard-buttons">
            <button class="nx-btn-secondary">
              <i class="material-symbols-outlined">close</i>
              Cancel
            </button>
            <button class="nx-btn-success icon-button">
              <i class="material-symbols-outlined">save</i>
              Save Changes
            </button>
            <button class="nx-btn-danger outline" style="margin-left: auto;">
              <i class="material-symbols-outlined">delete</i>
              Delete Profile
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Button Showcase Form

```html
<div class="nx-container">
  <div class="nx-row">
    <div class="nx-col-12 nx-lg-10" style="margin: 0 auto;">
      <h2>Button Components Showcase</h2>

      <!-- Color Variants Section -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Color Variants</h3>
          <div class="example-buttons">
            <button class="nx-btn-primary">Primary</button>
            <button class="nx-btn-secondary">Secondary</button>
            <button class="nx-btn-success">Success</button>
            <button class="nx-btn-danger">Danger</button>
            <button class="nx-btn-warning">Warning</button>
            <button class="nx-btn-info">Info</button>
            <button class="nx-btn-link">Link</button>
            <button class="nx-btn-dark">Dark</button>
          </div>
        </div>
      </div>

      <!-- Outline Variants -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Outline Variants</h3>
          <div class="example-buttons">
            <button class="nx-btn-primary outline">Primary Outline</button>
            <button class="nx-btn-success outline">Success Outline</button>
            <button class="nx-btn-danger outline">Danger Outline</button>
            <button class="nx-btn-info outline">Info Outline</button>
            <button class="nx-btn-secondary outline">Secondary Outline</button>
          </div>
        </div>
      </div>

      <!-- Size Variants -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Size Variants</h3>
          <div class="example-buttons">
            <button class="nx-btn-primary custom-size-sm">Extra Small</button>
            <button class="nx-btn-primary is-small">Small</button>
            <button class="nx-btn-primary">Normal</button>
            <button class="nx-btn-primary is-medium">Medium</button>
            <button class="nx-btn-primary is-large">Large</button>
            <button class="nx-btn-primary custom-size-xl">Extra Large</button>
          </div>
        </div>
      </div>

      <!-- Icon Buttons -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Icon Buttons</h3>
          <div class="example-buttons">
            <button class="nx-btn-success icon-button">
              <i class="material-symbols-outlined">check</i>
              <span>Save</span>
            </button>
            <button class="nx-btn-danger icon-button">
              <i class="material-symbols-outlined">delete</i>
              <span>Delete</span>
            </button>
            <button class="nx-btn-info icon-button">
              <i class="material-symbols-outlined">download</i>
              <span>Download</span>
            </button>
            <button class="nx-btn-primary icon-button">
              <i class="material-symbols-outlined">edit</i>
              <span>Edit</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Icon Only Buttons -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Icon Only Buttons</h3>
          <div class="example-buttons">
            <button
              class="nx-btn-primary nx-btn-icon-only"
              aria-label="Add new item"
            >
              <i class="material-symbols-outlined">add</i>
            </button>
            <button
              class="nx-btn-danger nx-btn-icon-only"
              aria-label="Delete item"
            >
              <i class="material-symbols-outlined">delete</i>
            </button>
            <button
              class="nx-btn-info nx-btn-icon-only"
              aria-label="Information"
            >
              <i class="material-symbols-outlined">info</i>
            </button>
            <button
              class="nx-btn-secondary nx-btn-icon-only"
              aria-label="Settings"
            >
              <i class="material-symbols-outlined">settings</i>
            </button>
          </div>
        </div>
      </div>

      <!-- Loading and Disabled States -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Button States</h3>
          <div class="example-buttons">
            <button class="nx-btn-primary loading">
              <span class="spinner"></span>
              Loading...
            </button>
            <button class="nx-btn-secondary" disabled>Disabled</button>
            <button class="nx-btn-success nx-btn-loading" disabled>
              Processing
            </button>
            <button class="nx-btn-primary nx-btn-error">Error State</button>
          </div>
        </div>
      </div>

      <!-- Windows Style Buttons -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Windows Style Buttons</h3>
          <div class="example-buttons">
            <button class="nx-btn-win-blue">Windows Blue</button>
            <button class="nx-btn-win-green">Windows Green</button>
            <button class="nx-btn-win-error">Windows Error</button>
            <button class="nx-btn-win-accent">Windows Accent</button>
            <button class="nx-btn-win-system">System Button</button>
          </div>
        </div>
      </div>

      <!-- Full Width Buttons -->
      <div class="nx-row">
        <div class="nx-col-12 nx-md-6">
          <h4>Full Width Buttons</h4>
          <button class="nx-btn-primary full-width">Full Width Primary</button>
          <button class="nx-btn-success full-width icon-button">
            <i class="material-symbols-outlined">cloud_upload</i>
            Upload Files
          </button>
          <button class="nx-btn-danger full-width outline">
            <i class="material-symbols-outlined">warning</i>
            Delete All Data
          </button>
        </div>
        <div class="nx-col-12 nx-md-6">
          <h4>Special Buttons</h4>
          <div class="example-buttons">
            <button class="nx-btn-ghost">Ghost Button</button>
            <button class="nx-btn-text">Text Button</button>
            <button class="nx-btn-primary rounded-full">Pill Button</button>
            <button class="nx-btn-custom gradient">Gradient</button>
          </div>
        </div>
      </div>

      <!-- Custom Color Buttons -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Custom Color Buttons</h3>
          <div class="example-buttons">
            <button class="nx-btn-custom" style="--button-color: #e74c3c;">
              Custom Red
            </button>
            <button class="nx-btn-custom" style="--button-color: #9b59b6;">
              Custom Purple
            </button>
            <button class="nx-btn-custom" style="--button-color: #1abc9c;">
              Custom Teal
            </button>
            <button
              class="nx-btn-custom outline"
              style="--button-color: #f39c12;"
            >
              Custom Orange Outline
            </button>
          </div>
        </div>
      </div>

      <!-- Form Action Example -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Form Action Buttons</h3>
          <form class="form-nexa">
            <div class="form-nexa-floating">
              <input type="text" class="form-nexa-control" placeholder=" " />
              <label>Sample Input</label>
            </div>

            <!-- Button combinations -->
            <div class="form-nexa-wizard-buttons">
              <button type="button" class="nx-btn-secondary">
                <i class="material-symbols-outlined">close</i>
                Cancel
              </button>
              <button type="reset" class="nx-btn-text">Reset</button>
              <button type="submit" class="nx-btn-primary icon-button">
                <i class="material-symbols-outlined">send</i>
                Submit
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Responsive Button Example -->
      <div class="nx-row">
        <div class="nx-col-12">
          <h3>Responsive Buttons (Try on mobile)</h3>
          <button class="nx-btn-primary responsive">Responsive Button</button>
          <button class="nx-btn-dark responsive">Mobile Adaptive</button>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Range Slider Form Examples

```html
<div class="nx-container">
  <div class="nx-row">
    <div class="nx-col-12 nx-lg-10" style="margin: 0 auto;">
      <div
        class="form-nexa"
        style="padding: 2rem; border: 1px solid #e0e0e0; border-radius: 8px;"
      >
        <h2>Range Slider Components</h2>

        <!-- Basic Range Sliders -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h3>Basic Range Sliders</h3>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Volume: <span id="volume-value">50</span>%</label>
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="100"
                value="50"
                oninput="document.getElementById('volume-value').textContent=this.value"
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Brightness: <span id="brightness-value">75</span>%</label>
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="100"
                value="75"
                oninput="document.getElementById('brightness-value').textContent=this.value"
              />
            </div>
          </div>
        </div>

        <!-- Price Range -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h3>Price Range Selector</h3>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Min Price: <span id="min-price">$100</span></label>
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="1000"
                value="100"
                step="50"
                oninput="document.getElementById('min-price').textContent='$'+this.value"
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Max Price: <span id="max-price">$500</span></label>
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="1000"
                value="500"
                step="50"
                oninput="document.getElementById('max-price').textContent='$'+this.value"
              />
            </div>
          </div>
        </div>

        <!-- RGB Color Mixer -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h3>RGB Color Mixer</h3>
          </div>
          <div class="nx-col-12 nx-md-8">
            <div class="form-nexa-group">
              <label style="color: red;"
                >Red: <span id="red-val">128</span></label
              >
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="255"
                value="128"
                style="accent-color: red;"
                oninput="updateColor('red', this.value)"
              />
            </div>
            <div class="form-nexa-group">
              <label style="color: green;"
                >Green: <span id="green-val">128</span></label
              >
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="255"
                value="128"
                style="accent-color: green;"
                oninput="updateColor('green', this.value)"
              />
            </div>
            <div class="form-nexa-group">
              <label style="color: blue;"
                >Blue: <span id="blue-val">128</span></label
              >
              <input
                type="range"
                class="form-nexa-range"
                min="0"
                max="255"
                value="128"
                style="accent-color: blue;"
                oninput="updateColor('blue', this.value)"
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-4">
            <div
              id="color-preview"
              style="
                width: 100%; 
                height: 150px; 
                background-color: rgb(128, 128, 128); 
                border: 2px solid #e0e0e0; 
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
              "
            >
              <span id="color-code">rgb(128, 128, 128)</span>
            </div>
          </div>
        </div>

        <!-- Temperature and Time -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h3>Specialized Ranges</h3>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label>Temperature: <span id="temp-value">20</span>°C</label>
              <input
                type="range"
                class="form-nexa-range"
                min="-10"
                max="40"
                value="20"
                oninput="document.getElementById('temp-value').textContent=this.value"
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-6">
            <div class="form-nexa-group">
              <label
                >Meeting Duration:
                <span id="duration-value">30</span> minutes</label
              >
              <input
                type="range"
                class="form-nexa-range"
                min="15"
                max="180"
                value="30"
                step="15"
                oninput="document.getElementById('duration-value').textContent=this.value"
              />
            </div>
          </div>
        </div>

        <!-- Quality Settings -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h3>Quality & Performance Settings</h3>
          </div>
          <div class="nx-col-12 nx-md-4">
            <div class="form-nexa-group">
              <label>Video Quality: <span id="quality-value">HD</span></label>
              <input
                type="range"
                class="form-nexa-range"
                min="1"
                max="4"
                value="2"
                oninput="
                  const qualities = ['', 'SD', 'HD', 'Full HD', '4K'];
                  document.getElementById('quality-value').textContent=qualities[this.value];
                "
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-4">
            <div class="form-nexa-group">
              <label>Performance: <span id="perf-value">Balanced</span></label>
              <input
                type="range"
                class="form-nexa-range"
                min="1"
                max="3"
                value="2"
                oninput="
                  const modes = ['', 'Power Save', 'Balanced', 'Performance'];
                  document.getElementById('perf-value').textContent=modes[this.value];
                "
              />
            </div>
          </div>
          <div class="nx-col-12 nx-md-4">
            <div class="form-nexa-group">
              <label>Storage Usage: <span id="storage-value">50</span>GB</label>
              <input
                type="range"
                class="form-nexa-range"
                min="10"
                max="100"
                value="50"
                step="10"
                oninput="document.getElementById('storage-value').textContent=this.value"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Color mixer function
  let rgbValues = { red: 128, green: 128, blue: 128 };

  function updateColor(component, value) {
    rgbValues[component] = parseInt(value);
    document.getElementById(component + "-val").textContent = value;

    const r = rgbValues.red;
    const g = rgbValues.green;
    const b = rgbValues.blue;

    const color = `rgb(${r}, ${g}, ${b})`;
    document.getElementById("color-preview").style.backgroundColor = color;
    document.getElementById("color-code").textContent = color;

    // Calculate text color based on brightness
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    document.getElementById("color-code").style.color =
      brightness > 125 ? "black" : "white";
  }
</script>
```

### Dashboard Action Buttons Form

```html
<div class="nx-container">
  <div class="nx-row">
    <div class="nx-col-12">
      <div
        class="form-nexa"
        style="padding: 2rem; border: 1px solid #e0e0e0; border-radius: 8px;"
      >
        <h3>Dashboard Actions</h3>

        <!-- Quick Actions -->
        <div class="nx-row">
          <div class="nx-col-12 nx-md-6 nx-lg-3">
            <button class="nx-btn-primary full-width icon-button">
              <i class="material-symbols-outlined">add</i>
              New Project
            </button>
          </div>
          <div class="nx-col-12 nx-md-6 nx-lg-3">
            <button class="nx-btn-info full-width icon-button">
              <i class="material-symbols-outlined">upload</i>
              Import Data
            </button>
          </div>
          <div class="nx-col-12 nx-md-6 nx-lg-3">
            <button class="nx-btn-success full-width icon-button">
              <i class="material-symbols-outlined">download</i>
              Export Report
            </button>
          </div>
          <div class="nx-col-12 nx-md-6 nx-lg-3">
            <button class="nx-btn-secondary full-width icon-button">
              <i class="material-symbols-outlined">settings</i>
              Settings
            </button>
          </div>
        </div>

        <!-- Table Actions -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h4>Table Actions</h4>
            <div class="example-buttons">
              <button class="nx-btn-secondary is-small table-template">
                <i class="material-symbols-outlined">filter_list</i>
                Filter
              </button>
              <button class="nx-btn-secondary is-small table-template">
                <i class="material-symbols-outlined">sort</i>
                Sort
              </button>
              <button class="nx-btn-info is-small icon-button">
                <i class="material-symbols-outlined">refresh</i>
                Refresh
              </button>
              <button class="nx-btn-success is-small icon-button">
                <i class="material-symbols-outlined">add</i>
                Add Row
              </button>
              <button class="nx-btn-danger is-small icon-button">
                <i class="material-symbols-outlined">delete</i>
                Delete Selected
              </button>
            </div>
          </div>
        </div>

        <!-- Bulk Actions -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h4>Bulk Operations</h4>
            <div class="example-buttons">
              <button class="nx-btn-primary outline">
                <i class="material-symbols-outlined">select_all</i>
                Select All
              </button>
              <button class="nx-btn-warning">
                <i class="material-symbols-outlined">edit</i>
                Bulk Edit
              </button>
              <button class="nx-btn-danger outline">
                <i class="material-symbols-outlined">delete_sweep</i>
                Bulk Delete
              </button>
            </div>
          </div>
        </div>

        <!-- Status Actions with Loading States -->
        <div class="nx-row">
          <div class="nx-col-12">
            <h4>Status Actions</h4>
            <div class="example-buttons">
              <button class="nx-btn-success" id="approve-btn">
                <i class="material-symbols-outlined">check_circle</i>
                Approve
              </button>
              <button class="nx-btn-warning" id="pending-btn">
                <i class="material-symbols-outlined">schedule</i>
                Mark Pending
              </button>
              <button class="nx-btn-danger" id="reject-btn">
                <i class="material-symbols-outlined">cancel</i>
                Reject
              </button>
              <button class="nx-btn-info loading" disabled>
                <span class="spinner"></span>
                Processing...
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

---

## JavaScript Integration

### Floating Label Activation

```javascript
// Auto-activate floating labels based on input values
function initFloatingLabels() {
  const floatingInputs = document.querySelectorAll(
    ".form-nexa-floating input, .form-nexa-floating select, .form-nexa-floating textarea"
  );

  floatingInputs.forEach((input) => {
    const label =
      input.nextElementSibling?.tagName === "LABEL"
        ? input.nextElementSibling
        : input.parentNode.querySelector("label");

    // Check if input has value on load
    if (input.value !== "" || input.type === "date" || input.type === "time") {
      label?.classList.add("active");
    }

    // Handle focus events
    input.addEventListener("focus", () => {
      label?.classList.add("active");
    });

    // Handle blur events
    input.addEventListener("blur", () => {
      if (
        input.value === "" &&
        input.type !== "date" &&
        input.type !== "time"
      ) {
        label?.classList.remove("active");
      }
    });

    // Handle input events
    input.addEventListener("input", () => {
      if (input.value !== "") {
        label?.classList.add("active");
      }
    });
  });
}

// Initialize on DOM content loaded
document.addEventListener("DOMContentLoaded", initFloatingLabels);
```

### Password Toggle

```javascript
// Password visibility toggle
function initPasswordToggle() {
  const toggleButtons = document.querySelectorAll(".password-toggle");

  toggleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const input = button.parentNode.querySelector(
        'input[type="password"], input[type="text"]'
      );

      if (input.type === "password") {
        input.type = "text";
        button.textContent = "visibility_off";
      } else {
        input.type = "password";
        button.textContent = "visibility";
      }
    });
  });
}

// Initialize password toggles
document.addEventListener("DOMContentLoaded", initPasswordToggle);
```

### Range Slider Interactions

```javascript
// Range slider with live value updates
function initRangeSliders() {
  const rangeInputs = document.querySelectorAll(".form-nexa-range");

  rangeInputs.forEach((slider) => {
    // Create value display if data-show-value exists
    if (slider.hasAttribute("data-show-value")) {
      const valueDisplay = document.createElement("span");
      valueDisplay.className = "range-value";
      valueDisplay.textContent = slider.value;
      slider.parentNode.appendChild(valueDisplay);

      slider.addEventListener("input", () => {
        valueDisplay.textContent = slider.value;
      });
    }

    // Custom formatter function
    if (slider.hasAttribute("data-formatter")) {
      const formatterName = slider.getAttribute("data-formatter");
      slider.addEventListener("input", () => {
        const value = formatRangeValue(slider.value, formatterName);
        const display = slider.parentNode.querySelector(
          ".range-value, [data-value-target]"
        );
        if (display) {
          display.textContent = value;
        }
      });
    }
  });
}

// Range value formatters
function formatRangeValue(value, type) {
  switch (type) {
    case "currency":
      return `$${value}`;
    case "percentage":
      return `${value}%`;
    case "temperature":
      return `${value}°C`;
    case "time":
      const hours = Math.floor(value / 60);
      const minutes = value % 60;
      return `${hours}:${minutes.toString().padStart(2, "0")}`;
    default:
      return value;
  }
}

// Dual range slider (min-max range)
function initDualRangeSlider(container) {
  const minSlider = container.querySelector('[data-range="min"]');
  const maxSlider = container.querySelector('[data-range="max"]');
  const minDisplay = container.querySelector('[data-display="min"]');
  const maxDisplay = container.querySelector('[data-display="max"]');

  function updateRange() {
    const minVal = parseInt(minSlider.value);
    const maxVal = parseInt(maxSlider.value);

    if (minVal >= maxVal) {
      minSlider.value = maxVal - 1;
    }

    if (maxVal <= minVal) {
      maxSlider.value = minVal + 1;
    }

    if (minDisplay) minDisplay.textContent = minSlider.value;
    if (maxDisplay) maxDisplay.textContent = maxSlider.value;
  }

  minSlider.addEventListener("input", updateRange);
  maxSlider.addEventListener("input", updateRange);

  // Initial update
  updateRange();
}

// Color mixer with RGB sliders
function initColorMixer(container) {
  const redSlider = container.querySelector('[data-color="red"]');
  const greenSlider = container.querySelector('[data-color="green"]');
  const blueSlider = container.querySelector('[data-color="blue"]');
  const preview = container.querySelector("[data-color-preview]");

  function updateColor() {
    const r = redSlider.value;
    const g = greenSlider.value;
    const b = blueSlider.value;

    const color = `rgb(${r}, ${g}, ${b})`;

    if (preview) {
      preview.style.backgroundColor = color;
    }

    // Update individual value displays
    const redDisplay = container.querySelector('[data-display="red"]');
    const greenDisplay = container.querySelector('[data-display="green"]');
    const blueDisplay = container.querySelector('[data-display="blue"]');

    if (redDisplay) redDisplay.textContent = r;
    if (greenDisplay) greenDisplay.textContent = g;
    if (blueDisplay) blueDisplay.textContent = b;

    // Trigger custom event
    container.dispatchEvent(
      new CustomEvent("colorchange", {
        detail: { r, g, b, color },
      })
    );
  }

  [redSlider, greenSlider, blueSlider].forEach((slider) => {
    slider.addEventListener("input", updateColor);
  });

  // Initial color update
  updateColor();
}

// Initialize all range components
document.addEventListener("DOMContentLoaded", () => {
  initRangeSliders();

  // Initialize dual range sliders
  const dualRangeContainers = document.querySelectorAll("[data-dual-range]");
  dualRangeContainers.forEach((container) => {
    initDualRangeSlider(container);
  });

  // Initialize color mixers
  const colorMixers = document.querySelectorAll("[data-color-mixer]");
  colorMixers.forEach((container) => {
    initColorMixer(container);
  });
});
```

### Text Editor Integration

```javascript
// Text Editor (Quill.js) Integration
function initTextEditors() {
  // Check if Quill is loaded
  if (typeof Quill === "undefined") {
    console.warn("Quill.js is required for text editors");
    return;
  }

  const editors = document.querySelectorAll(".form-nexa-editor-control");

  editors.forEach((editorElement) => {
    // Skip if already initialized
    if (editorElement.classList.contains("ql-container")) return;

    // Default toolbar options
    const defaultToolbar = [
      [{ header: [1, 2, 3, false] }],
      ["bold", "italic", "underline", "strike"],
      [{ color: [] }, { background: [] }],
      [{ list: "ordered" }, { list: "bullet" }],
      [{ align: [] }],
      ["link", "image"],
      ["clean"],
    ];

    // Get custom toolbar from data attribute
    const customToolbar = editorElement.getAttribute("data-toolbar");
    const toolbar = customToolbar ? JSON.parse(customToolbar) : defaultToolbar;

    // Initialize Quill editor
    const quill = new Quill(editorElement, {
      theme: "snow",
      placeholder:
        editorElement.getAttribute("data-placeholder") || "Start typing...",
      modules: {
        toolbar: toolbar,
      },
    });

    // Handle content changes
    quill.on("text-change", function (delta, oldDelta, source) {
      // Update hidden input if exists
      const hiddenInput = editorElement.parentNode.querySelector(
        'input[type="hidden"]'
      );
      if (hiddenInput) {
        hiddenInput.value = quill.root.innerHTML;
      }

      // Trigger custom event
      editorElement.dispatchEvent(
        new CustomEvent("editor-change", {
          detail: {
            content: quill.root.innerHTML,
            text: quill.getText(),
            delta: delta,
            source: source,
          },
        })
      );
    });

    // Store Quill instance for external access
    editorElement._quill = quill;
  });
}

// Text Editor utilities
function getEditorContent(editorElement) {
  return editorElement._quill ? editorElement._quill.root.innerHTML : "";
}

function setEditorContent(editorElement, content) {
  if (editorElement._quill) {
    editorElement._quill.root.innerHTML = content;
  }
}

// Search with Categories
function initSearchWithCategories() {
  const searchContainers = document.querySelectorAll(".form-nexa-search");

  searchContainers.forEach((container) => {
    const input = container.querySelector(
      ".form-nexa-control, .form-nexa-search-input"
    );
    const dropdown = container.querySelector(".form-nexa-search-dropdown");
    const items = container.querySelectorAll(".form-nexa-search-item");

    if (!input || !dropdown) return;

    // Show dropdown on focus
    input.addEventListener("focus", () => {
      container.classList.add("active");
      dropdown.style.display = "block";
    });

    // Hide dropdown on blur (with delay for item clicks)
    input.addEventListener("blur", () => {
      setTimeout(() => {
        container.classList.remove("active");
        dropdown.style.display = "none";
      }, 200);
    });

    // Filter items based on input
    input.addEventListener("input", () => {
      const query = input.value.toLowerCase();
      let hasVisibleItems = false;

      // Group items by category
      const categories = {};
      items.forEach((item) => {
        const category = item.parentNode.previousElementSibling;
        if (
          category &&
          category.classList.contains("form-nexa-search-category")
        ) {
          const categoryName = category.textContent;
          if (!categories[categoryName]) {
            categories[categoryName] = {
              element: category,
              items: [],
            };
          }
          categories[categoryName].items.push(item);
        }
      });

      // Filter and show/hide items
      Object.keys(categories).forEach((categoryName) => {
        const categoryData = categories[categoryName];
        let categoryHasVisible = false;

        categoryData.items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (query === "" || text.includes(query)) {
            item.style.display = "flex";
            categoryHasVisible = true;
            hasVisibleItems = true;
          } else {
            item.style.display = "none";
          }
        });

        // Show/hide category header
        categoryData.element.style.display = categoryHasVisible
          ? "block"
          : "none";
      });

      // Show "No results" message if needed
      let noResultsMsg = container.querySelector(
        ".form-nexa-search-no-results"
      );
      if (!hasVisibleItems && query !== "") {
        if (!noResultsMsg) {
          noResultsMsg = document.createElement("div");
          noResultsMsg.className = "form-nexa-search-no-results";
          noResultsMsg.textContent = "No results found";
          dropdown.appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = "block";
      } else if (noResultsMsg) {
        noResultsMsg.style.display = "none";
      }
    });

    // Handle item selection
    items.forEach((item) => {
      item.addEventListener("click", () => {
        const text = item.textContent.trim();
        input.value = text;

        // Trigger change event
        input.dispatchEvent(new Event("change"));

        // Hide dropdown
        container.classList.remove("active");
        dropdown.style.display = "none";

        // Custom selection event
        container.dispatchEvent(
          new CustomEvent("item-selected", {
            detail: {
              item: item,
              text: text,
              value: item.getAttribute("data-value") || text,
            },
          })
        );
      });
    });
  });
}

// Multi-Select Tags functionality
function initMultiSelectTags() {
  const tagContainers = document.querySelectorAll(".form-nexa-search-tags");

  tagContainers.forEach((container) => {
    const input = container.querySelector(".form-nexa-search-input");
    const dropdown = container.parentNode.querySelector(
      ".form-nexa-search-dropdown"
    );

    if (!input) return;

    // Handle tag removal
    const removeButtons = container.querySelectorAll(
      ".form-nexa-search-tag-remove"
    );
    removeButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const tag = button.parentNode;
        tag.remove();
        updateTagsValue(container);
      });
    });

    // Handle new tag input
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === ",") {
        e.preventDefault();
        addTag(container, input.value.trim());
        input.value = "";
      }

      if (e.key === "Backspace" && input.value === "") {
        // Remove last tag on backspace
        const tags = container.querySelectorAll(".form-nexa-search-tag");
        if (tags.length > 0) {
          tags[tags.length - 1].remove();
          updateTagsValue(container);
        }
      }
    });

    // Handle dropdown item selection for tags
    if (dropdown) {
      const items = dropdown.querySelectorAll(".form-nexa-search-item");
      items.forEach((item) => {
        item.addEventListener("click", () => {
          const text = item.textContent.trim();
          addTag(container, text);
          input.value = "";
          dropdown.style.display = "none";
        });
      });
    }
  });
}

function addTag(container, text) {
  if (!text) return;

  // Check if tag already exists
  const existingTags = Array.from(
    container.querySelectorAll(".form-nexa-search-tag")
  );
  if (
    existingTags.some(
      (tag) => tag.textContent.trim().replace("×", "").trim() === text
    )
  ) {
    return;
  }

  // Create new tag
  const tag = document.createElement("div");
  tag.className = "form-nexa-search-tag";
  tag.innerHTML = `
    ${text}
    <span class="form-nexa-search-tag-remove">×</span>
  `;

  // Add remove functionality
  const removeButton = tag.querySelector(".form-nexa-search-tag-remove");
  removeButton.addEventListener("click", () => {
    tag.remove();
    updateTagsValue(container);
  });

  // Insert before input container
  const inputContainer = container.querySelector(
    ".form-nexa-search-input-container"
  );
  container.insertBefore(tag, inputContainer);

  updateTagsValue(container);
}

function updateTagsValue(container) {
  const tags = Array.from(container.querySelectorAll(".form-nexa-search-tag"));
  const values = tags.map((tag) =>
    tag.textContent.trim().replace("×", "").trim()
  );

  // Update hidden input if exists
  const hiddenInput = container.parentNode.querySelector(
    'input[type="hidden"]'
  );
  if (hiddenInput) {
    hiddenInput.value = values.join(",");
  }

  // Trigger change event
  container.dispatchEvent(
    new CustomEvent("tags-changed", {
      detail: { tags: values },
    })
  );
}

// Initialize all advanced components
document.addEventListener("DOMContentLoaded", () => {
  // Initialize existing components
  initRangeSliders();

  // Initialize new components
  initTextEditors();
  initSearchWithCategories();
  initMultiSelectTags();

  // Initialize dual range sliders
  const dualRangeContainers = document.querySelectorAll("[data-dual-range]");
  dualRangeContainers.forEach((container) => {
    initDualRangeSlider(container);
  });

  // Initialize color mixers
  const colorMixers = document.querySelectorAll("[data-color-mixer]");
  colorMixers.forEach((container) => {
    initColorMixer(container);
  });
});
```

### Form Validation

```javascript
// Enhanced form validation
class NexaFormValidator {
  constructor(form) {
    this.form = form;
    this.init();
  }

  init() {
    this.form.addEventListener("submit", (e) => this.handleSubmit(e));

    // Real-time validation
    const inputs = this.form.querySelectorAll(".form-nexa-control");
    inputs.forEach((input) => {
      input.addEventListener("blur", () => this.validateField(input));
      input.addEventListener("input", () => this.clearErrors(input));
    });
  }

  handleSubmit(e) {
    e.preventDefault();

    if (this.validateForm()) {
      // Form is valid, proceed with submission
      console.log("Form is valid, submitting...");
    }
  }

  validateForm() {
    const inputs = this.form.querySelectorAll(".form-nexa-control[required]");
    let isValid = true;

    inputs.forEach((input) => {
      if (!this.validateField(input)) {
        isValid = false;
      }
    });

    return isValid;
  }

  validateField(input) {
    const container = input.closest(".form-nexa-group, .form-nexa-floating");
    let isValid = true;
    let message = "";

    // Required validation
    if (input.required && !input.value.trim()) {
      isValid = false;
      message = "This field is required";
    }

    // Email validation
    if (
      input.type === "email" &&
      input.value &&
      !this.isValidEmail(input.value)
    ) {
      isValid = false;
      message = "Please enter a valid email address";
    }

    // Phone validation
    if (
      input.type === "tel" &&
      input.value &&
      !this.isValidPhone(input.value)
    ) {
      isValid = false;
      message = "Please enter a valid phone number";
    }

    // Password validation
    if (input.type === "password" && input.value && input.value.length < 8) {
      isValid = false;
      message = "Password must be at least 8 characters long";
    }

    this.showValidationState(container, input, isValid, message);
    return isValid;
  }

  showValidationState(container, input, isValid, message) {
    // Remove existing states
    container.classList.remove("form-error");
    input.classList.remove("is-invalid", "is-valid");

    // Remove existing error message
    const existingError = container.querySelector(".error-message");
    if (existingError) {
      existingError.remove();
    }

    if (!isValid) {
      container.classList.add("form-error");
      input.classList.add("is-invalid");

      // Add error message
      const errorElement = document.createElement("small");
      errorElement.className = "error-message";
      errorElement.textContent = message;
      container.appendChild(errorElement);
    } else if (input.value) {
      input.classList.add("is-valid");
    }
  }

  clearErrors(input) {
    const container = input.closest(".form-nexa-group, .form-nexa-floating");
    container.classList.remove("form-error");
    input.classList.remove("is-invalid");

    const errorElement = container.querySelector(".error-message");
    if (errorElement) {
      errorElement.remove();
    }
  }

  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  isValidPhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ""));
  }
}

// Initialize form validators
document.addEventListener("DOMContentLoaded", () => {
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => new NexaFormValidator(form));
});
```

---

## Best Practices

### 1. Accessibility

- Always use proper labels for form elements
- Include `required` attributes for mandatory fields
- Use `aria-describedby` for error messages
- Ensure proper color contrast ratios

### 2. Mobile Optimization

- Use `form-nexa-control-responsive` for mobile-friendly inputs
- Test on various screen sizes
- Consider touch target sizes (minimum 44px)

### 3. Performance

- Initialize JavaScript components only when needed
- Use CSS transforms for smooth animations
- Minimize reflows and repaints

### 4. Validation

- Provide real-time feedback
- Use clear, helpful error messages
- Validate on both client and server side

### 5. Grid Usage

- Use semantic HTML structure
- Keep responsive breakpoints consistent
- Test grid layouts across different devices

### 6. Button Usage

- Use primary buttons sparingly (one per view/section)
- Provide loading states for async operations
- Use appropriate button types (button, submit, reset)
- Ensure proper color contrast for accessibility
- Use icons consistently and meaningfully
- Test touch targets on mobile devices (minimum 44px)
- Use outline variants for secondary actions
- Provide proper ARIA labels for icon-only buttons

---

## Browser Support

- Modern browsers (Chrome 60+, Firefox 55+, Safari 12+, Edge 79+)
- CSS Grid support required for advanced layouts
- CSS Custom Properties support recommended
- Flexbox support required

### Button-specific Support

- CSS Gradients support for button styling
- CSS Animations for loading states and hover effects
- CSS Transforms for button interactions
- CSS Custom Properties for custom button colors
- Flexbox for button layout and icon alignment

---

## Contributing

When contributing to the form and grid system:

1. Follow existing naming conventions
2. Ensure responsive behavior
3. Test in both light and dark modes
4. Include proper documentation
5. Test accessibility features

---

## Quick Class Reference

### Form Classes

#### Basic Form Classes

- `form-nexa` - Form container
- `form-nexa-group` - Form group container
- `form-nexa-control` - Standard input styling
- `form-nexa-control-sm` - Small input
- `form-nexa-control-xs` - Extra small input
- `form-nexa-control-lg` - Large input
- `form-nexa-control-responsive` - Mobile-optimized input

#### Form States

- `is-valid` - Valid input state
- `is-invalid` - Invalid input state
- `form-error` - Error state container
- `error-message` - Error message text

#### Floating Labels

- `form-nexa-floating` - Floating label container
- `form-nexa-icon` - Icon container for floating labels
- `form-nexa-select-icon` - Select with icon for floating labels

#### Input Groups

- `form-nexa-input-group` - Input group container
- `form-nexa-input-group-text` - Input group text/prefix/suffix
- `form-nexa-input-group-icon` - Input group with icon support
- `form-nexa-color-picker` - Color picker input

#### Form Components

- `form-nexa-check` - Checkbox container (classic)
- `form-nexa-check-input` - Checkbox input (classic)
- `form-nexa-check-label` - Checkbox label (classic)
- `form-nexa-radio` - Radio container (classic)
- `form-nexa-radio-input` - Radio input (classic)
- `form-nexa-radio-label` - Radio label (classic)

#### Modern Form Components

- `nx-checkbox-grid` - Modern checkbox grid
- `nx-checkbox-item` - Modern checkbox item
- `nx-checkmark` - Modern checkbox checkmark
- `nx-radio-grid` - Modern radio grid
- `nx-radio-item` - Modern radio item
- `nx-radio-mark` - Modern radio mark
- `nx-switch-grid` - Modern switch grid
- `nx-switch-item` - Modern switch item
- `nx-switch` - Modern switch toggle

#### Advanced Components

- `form-nexa-file` - File upload container
- `form-nexa-file-input` - File input
- `form-nexa-file-label` - File upload label
- `form-nexa-file-list` - File list container
- `form-nexa-range` - Range slider input
- `form-nexa-editor` - Text editor container
- `form-nexa-editor-control` - Text editor control element
- `form-nexa-search` - Search container
- `form-nexa-search-container` - Search input container
- `form-nexa-search-dropdown` - Search dropdown
- `form-nexa-search-category` - Search category header
- `form-nexa-search-items` - Search items container
- `form-nexa-search-item` - Individual search item
- `form-nexa-search-tags` - Multi-select tags container
- `form-nexa-search-tag` - Individual tag
- `form-nexa-search-tag-remove` - Tag remove button
- `form-nexa-search-input-container` - Tag input container
- `form-nexa-search-input` - Tag input field
- `form-nexa-search-no-results` - No results message
- `form-nexa-search-loading` - Loading state message
- `form-nexa-wizard` - Form wizard container
- `form-nexa-wizard-progress` - Wizard progress bar
- `form-nexa-wizard-step` - Wizard step
- `form-nexa-wizard-buttons` - Wizard button container

### Grid Classes

#### Containers and Layout

- `nx-container` - Main container
- `nx-row` - Row container
- `nx-col` - Flexible column
- `nx-col-1` to `nx-col-12` - Fixed width columns

#### Responsive Columns

- `nx-md-1` to `nx-md-12` - Medium screens (≥768px)
- `nx-lg-1` to `nx-lg-12` - Large screens (≥992px)
- `nx-xl-1` to `nx-xl-12` - Extra large screens (≥1200px)

#### Form-specific Grid

- `form-nx-col-1` to `form-nx-col-12` - Form grid columns
- `form-nexa-row` - Form row container

#### Grid Utilities

- `nx-center` - Center alignment
- `nx-justify-center` - Center justify
- `nx-justify-start` - Start justify
- `nx-justify-end` - End justify
- `nx-justify-between` - Space between
- `nx-justify-around` - Space around
- `nx-align-center` - Align center
- `nx-align-start` - Align start
- `nx-align-end` - Align end

#### Responsive Utilities

- `nx-hide-mobile` - Hide on mobile
- `nx-show-mobile` - Show only on mobile
- `form-nexa-hide-xs` - Hide on extra small screens
- `form-nexa-text-center-xs` - Center text on mobile

### Button Classes

#### Basic Button Classes

- `nx-btn` - Base button
- `nx-btn-primary` - Primary button with gradient
- `nx-btn-secondary` - Secondary button with gradient
- `nx-btn-success` - Success button with gradient
- `nx-btn-danger` - Danger button
- `nx-btn-warning` - Warning button
- `nx-btn-info` - Info button with gradient
- `nx-btn-link` - Link style button

#### Neutral Button Colors

- `nx-btn-white` - White button
- `nx-btn-light` - Light button
- `nx-btn-dark` - Dark button
- `nx-btn-black` - Black button

#### Special Button Types

- `nx-btn-ghost` - Ghost button with border
- `nx-btn-text` - Text button with underline effect

#### Secondary Button Variants

- `nx-btn-secondary-light` - Light secondary
- `nx-btn-secondary-dark` - Dark secondary
- `nx-btn-secondary-outline` - Outline secondary

#### Button Sizes

- `is-small` - Small button
- `is-normal` - Normal size (explicit)
- `is-medium` - Medium button
- `is-large` - Large button
- `custom-size-sm` - Extra small custom size
- `custom-size-xl` - Extra large custom size

#### Button States

- `loading` - Loading state
- `loading-text` - Loading with text
- `nx-btn-loading` - Loading with spinner
- `disabled` - Disabled state (HTML attribute)
- `nx-btn-error` - Error state

#### Button Variants

- `outline` - Outline variant
- `full-width` - Full width button
- `rounded-xl` - Extra rounded corners
- `rounded-full` - Pill-shaped button

#### Icon Button Classes

- `icon-button` - Button with icon and text
- `nx-btn-icon-only` - Icon-only circular button
- `with-icon` - Legacy icon button class

#### Windows Style Buttons

- `nx-btn-win-blue` - Windows blue
- `nx-btn-win-purple` - Windows purple
- `nx-btn-win-teal` - Windows teal
- `nx-btn-win-green` - Windows green
- `nx-btn-win-system` - Windows system style
- `nx-btn-win-accent` - Windows accent
- `nx-btn-win-accent-light` - Windows light accent
- `nx-btn-win-modern` - Windows modern style
- `nx-btn-win-error` - Windows error
- `nx-btn-win-warning` - Windows warning
- `nx-btn-win-success` - Windows success

#### Custom Button Classes

- `nx-btn-custom` - Custom button with CSS variables
- `gradient` - Gradient custom button
- `size-xs` - Extra small custom button
- `size-xl` - Extra large custom button

#### Responsive Button Classes

- `responsive` - Responsive button behavior
- `high-contrast` - High contrast accessibility

#### Button Utility Classes

- `example-buttons` - Button spacing container
- `table-template` - Table action button styling
- `spinner` - Loading spinner element

### Utility Classes

#### Sizing Utilities

- `w-10px` to `w-999px` - Width utilities (every pixel from 10-999)
- `h-10px` to `h-180px` - Height utilities

#### Text Alignment

- `tx-center` - Center text
- `tx-right` - Right align text
- `tx-left` - Left align text
- `tx-italic` - Italic text
- `tx-nowrap` - No wrap text

#### Spacing

- `mt-4` - Margin top 2rem

---

## License

NexaUI Form & Grid System is part of the NexaUI framework. Please refer to the main license file for terms and conditions.
