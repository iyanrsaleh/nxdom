/*!
 * NexaGrid.js - JavaScript Grid System for Ngorei NesxaUI
 * Copyright 2024-2025 The Ngorei NesxaUI Authors
 * Licensed under MIT
 */

class NexaGrid {
  constructor() {
    this.breakpoints = {
      xs: 0,
      sm: 576,
      md: 768,
      lg: 992,
      xl: 1200
    };
  }

  /**
   * Apply padding and margin styles to an element
   * @param {HTMLElement} element - Element to apply styles to
   * @param {Object} spacing - Spacing options
   * @param {string|number} spacing.padding - Padding shorthand (e.g., "1rem", "1rem 2rem", "1rem 2rem 3rem 4rem")
   * @param {string|number} spacing.paddingTop - Padding top
   * @param {string|number} spacing.paddingRight - Padding right
   * @param {string|number} spacing.paddingBottom - Padding bottom
   * @param {string|number} spacing.paddingLeft - Padding left
   * @param {string|number} spacing.margin - Margin shorthand (e.g., "1rem", "1rem 2rem", "1rem 2rem 3rem 4rem")
   * @param {string|number} spacing.marginTop - Margin top
   * @param {string|number} spacing.marginRight - Margin right
   * @param {string|number} spacing.marginBottom - Margin bottom
   * @param {string|number} spacing.marginLeft - Margin left
   * @returns {HTMLElement} Element with spacing applied
   */
  applySpacing(element, spacing = {}) {
    if (!element || !spacing) return element;
    
    // Apply padding
    if (spacing.padding !== undefined) {
      element.style.padding = spacing.padding;
    } else {
      if (spacing.paddingTop !== undefined) {
        element.style.paddingTop = typeof spacing.paddingTop === 'number' 
          ? `${spacing.paddingTop}px` 
          : spacing.paddingTop;
      }
      if (spacing.paddingRight !== undefined) {
        element.style.paddingRight = typeof spacing.paddingRight === 'number' 
          ? `${spacing.paddingRight}px` 
          : spacing.paddingRight;
      }
      if (spacing.paddingBottom !== undefined) {
        element.style.paddingBottom = typeof spacing.paddingBottom === 'number' 
          ? `${spacing.paddingBottom}px` 
          : spacing.paddingBottom;
      }
      if (spacing.paddingLeft !== undefined) {
        element.style.paddingLeft = typeof spacing.paddingLeft === 'number' 
          ? `${spacing.paddingLeft}px` 
          : spacing.paddingLeft;
      }
    }
    
    // Apply margin
    if (spacing.margin !== undefined) {
      element.style.margin = spacing.margin;
    } else {
      if (spacing.marginTop !== undefined) {
        element.style.marginTop = typeof spacing.marginTop === 'number' 
          ? `${spacing.marginTop}px` 
          : spacing.marginTop;
      }
      if (spacing.marginRight !== undefined) {
        element.style.marginRight = typeof spacing.marginRight === 'number' 
          ? `${spacing.marginRight}px` 
          : spacing.marginRight;
      }
      if (spacing.marginBottom !== undefined) {
        element.style.marginBottom = typeof spacing.marginBottom === 'number' 
          ? `${spacing.marginBottom}px` 
          : spacing.marginBottom;
      }
      if (spacing.marginLeft !== undefined) {
        element.style.marginLeft = typeof spacing.marginLeft === 'number' 
          ? `${spacing.marginLeft}px` 
          : spacing.marginLeft;
      }
    }
    
    return element;
  }

  /**
   * Create a container element
   * @param {Object} options - Container options
   * @param {boolean} options.nx - Use nx-container instead of container
   * @param {HTMLElement} options.parent - Parent element to append to
   * @param {string|number|Object} options.padding - Padding shorthand or object with paddingTop, paddingRight, paddingBottom, paddingLeft
   * @param {string|number} options.paddingTop - Padding top
   * @param {string|number} options.paddingRight - Padding right
   * @param {string|number} options.paddingBottom - Padding bottom
   * @param {string|number} options.paddingLeft - Padding left
   * @param {string|number|Object} options.margin - Margin shorthand or object with marginTop, marginRight, marginBottom, marginLeft
   * @param {string|number} options.marginTop - Margin top
   * @param {string|number} options.marginRight - Margin right
   * @param {string|number} options.marginBottom - Margin bottom
   * @param {string|number} options.marginLeft - Margin left
   * @returns {HTMLElement} Container element
   */
  createContainer(options = {}) {
    const container = document.createElement('div');
    container.className = options.nx ? 'nx-container' : 'container';
    
    // Apply spacing (padding and margin)
    this.applySpacing(container, {
      padding: options.padding,
      paddingTop: options.paddingTop,
      paddingRight: options.paddingRight,
      paddingBottom: options.paddingBottom,
      paddingLeft: options.paddingLeft,
      margin: options.margin,
      marginTop: options.marginTop,
      marginRight: options.marginRight,
      marginBottom: options.marginBottom,
      marginLeft: options.marginLeft
    });
    
    if (options.parent) {
      options.parent.appendChild(container);
    }
    
    return container;
  }

  /**
   * Create a row element
   * @param {Object} options - Row options
   * @param {boolean} options.nx - Use nx-row instead of row (default: true, set to false to use 'row')
   * @param {string} options.spacing - Row spacing (xs, sm, default)
   * @param {string} options.justify - Justify content (center, start, end, between, around)
   * @param {string} options.align - Align items (center, start, end)
   * @param {HTMLElement} options.parent - Parent element to append to
   * @param {string|number|Object} options.padding - Padding shorthand or object with paddingTop, paddingRight, paddingBottom, paddingLeft
   * @param {string|number} options.paddingTop - Padding top
   * @param {string|number} options.paddingRight - Padding right
   * @param {string|number} options.paddingBottom - Padding bottom
   * @param {string|number} options.paddingLeft - Padding left
   * @param {string|number|Object} options.margin - Margin shorthand or object with marginTop, marginRight, marginBottom, marginLeft
   * @param {string|number} options.marginTop - Margin top
   * @param {string|number} options.marginRight - Margin right
   * @param {string|number} options.marginBottom - Margin bottom
   * @param {string|number} options.marginLeft - Margin left
   * @returns {HTMLElement} Row element
   */
  createRow(options = {}) {
    const row = document.createElement('div');
    const isNx = options.nx !== false; // Default to nx-row
    const rowClass = isNx ? 'nx-row' : 'row';
    
    let classes = [rowClass];
    
    // Add spacing classes
    if (options.spacing) {
      if (options.spacing === 'xs') classes.push('row-xs');
      if (options.spacing === 'sm') classes.push('row-sm');
    }
    
    // Add responsive spacing
    if (options.spacingSm) classes.push(`row-${options.spacingSm}--sm`);
    if (options.spacingMd) classes.push(`row-${options.spacingMd}--md`);
    if (options.spacingLg) classes.push(`row-${options.spacingLg}--lg`);
    if (options.spacingXl) classes.push(`row-${options.spacingXl}--xl`);
    
    // Add justify content
    if (options.justify && isNx) {
      classes.push(`nx-justify-${options.justify}`);
    }
    
    // Add align items
    if (options.align && isNx) {
      classes.push(`nx-align-${options.align}`);
    }
    
    row.className = classes.join(' ');
    
    // Apply spacing (padding and margin)
    this.applySpacing(row, {
      padding: options.padding,
      paddingTop: options.paddingTop,
      paddingRight: options.paddingRight,
      paddingBottom: options.paddingBottom,
      paddingLeft: options.paddingLeft,
      margin: options.margin,
      marginTop: options.marginTop,
      marginRight: options.marginRight,
      marginBottom: options.marginBottom,
      marginLeft: options.marginLeft
    });
    
    if (options.parent) {
      options.parent.appendChild(row);
    }
    
    return row;
  }

  /**
   * Create a column element
   * @param {Object} options - Column options
   * @param {number} options.cols - Column width (1-12)
   * @param {boolean} options.nx - Use nx-col instead of col-md
   * @param {Object} options.responsive - Responsive breakpoints {sm, md, lg, xl}
   * @param {number} options.offset - Column offset (0-6)
   * @param {Object} options.offsetResponsive - Responsive offsets {sm, md, lg, xl}
   * @param {string} options.textAlign - Text alignment (left, center, right)
   * @param {HTMLElement} options.parent - Parent element to append to
   * @param {string|HTMLElement} options.content - Content to add to column
   * @param {string|number|Object} options.padding - Padding shorthand or object with paddingTop, paddingRight, paddingBottom, paddingLeft
   * @param {string|number} options.paddingTop - Padding top
   * @param {string|number} options.paddingRight - Padding right
   * @param {string|number} options.paddingBottom - Padding bottom
   * @param {string|number} options.paddingLeft - Padding left
   * @param {string|number|Object} options.margin - Margin shorthand or object with marginTop, marginRight, marginBottom, marginLeft
   * @param {string|number} options.marginTop - Margin top
   * @param {string|number} options.marginRight - Margin right
   * @param {string|number} options.marginBottom - Margin bottom
   * @param {string|number} options.marginLeft - Margin left
   * @returns {HTMLElement} Column element
   */
  createCol(options = {}) {
    const col = document.createElement('div');
    const cols = options.cols || 12;
    const isNx = options.nx !== false; // Default to nx
    
    let classes = [];
    
    // Base column class
    if (isNx) {
      classes.push(`nx-col-${cols}`);
    } else {
      classes.push(`col-md-${cols}`);
    }
    
    // Responsive column classes
    if (options.responsive) {
      const { sm, md, lg, xl } = options.responsive;
      
      if (sm && isNx) classes.push(`nx-sm-${sm}`);
      if (md && isNx) classes.push(`nx-md-${md}`);
      if (lg && isNx) classes.push(`nx-lg-${lg}`);
      if (xl && isNx) classes.push(`nx-xl-${xl}`);
    }
    
    // Offset classes
    if (options.offset) {
      if (isNx) {
        classes.push(`nx-offset-${options.offset}`);
      }
    }
    
    // Responsive offset classes
    if (options.offsetResponsive) {
      const { sm, md, lg, xl } = options.offsetResponsive;
      
      if (sm && isNx) classes.push(`nx-sm-offset-${sm}`);
      if (md && isNx) classes.push(`nx-md-offset-${md}`);
      if (lg && isNx) classes.push(`nx-lg-offset-${lg}`);
      if (xl && isNx) classes.push(`nx-xl-offset-${xl}`);
    }
    
    // Text alignment
    if (options.textAlign) {
      const align = options.textAlign.toLowerCase();
      if (align === 'center') classes.push('tx-center');
      if (align === 'right') classes.push('tx-right');
      if (align === 'left') classes.push('tx-left');
    }
    
    col.className = classes.join(' ');
    
    // Apply spacing (padding and margin)
    this.applySpacing(col, {
      padding: options.padding,
      paddingTop: options.paddingTop,
      paddingRight: options.paddingRight,
      paddingBottom: options.paddingBottom,
      paddingLeft: options.paddingLeft,
      margin: options.margin,
      marginTop: options.marginTop,
      marginRight: options.marginRight,
      marginBottom: options.marginBottom,
      marginLeft: options.marginLeft
    });
    
    // Add content
    if (options.content) {
      if (typeof options.content === 'string') {
        col.innerHTML = options.content;
      } else if (options.content instanceof HTMLElement) {
        col.appendChild(options.content);
      }
    }
    
    if (options.parent) {
      options.parent.appendChild(col);
    }
    
    return col;
  }

  /**
   * Create multiple columns in a row
   * @param {Object} options - Options for row and columns
   * @param {Array} options.columns - Array of column configurations
   * @param {HTMLElement} options.parent - Parent element to append to
   * @param {string|number|Object} options.padding - Padding shorthand or object with paddingTop, paddingRight, paddingBottom, paddingLeft
   * @param {string|number} options.paddingTop - Padding top
   * @param {string|number} options.paddingRight - Padding right
   * @param {string|number} options.paddingBottom - Padding bottom
   * @param {string|number} options.paddingLeft - Padding left
   * @param {string|number|Object} options.margin - Margin shorthand or object with marginTop, marginRight, marginBottom, marginLeft
   * @param {string|number} options.marginTop - Margin top
   * @param {string|number} options.marginRight - Margin right
   * @param {string|number} options.marginBottom - Margin bottom
   * @param {string|number} options.marginLeft - Margin left
   * @returns {Object} Object with row and columns
   */
  createRowWithCols(options = {}) {
    const row = this.createRow({
      nx: options.nx,
      spacing: options.spacing,
      justify: options.justify,
      align: options.align,
      parent: options.parent,
      padding: options.padding,
      paddingTop: options.paddingTop,
      paddingRight: options.paddingRight,
      paddingBottom: options.paddingBottom,
      paddingLeft: options.paddingLeft,
      margin: options.margin,
      marginTop: options.marginTop,
      marginRight: options.marginRight,
      marginBottom: options.marginBottom,
      marginLeft: options.marginLeft
    });
    
    const columns = [];
    
    if (options.columns && Array.isArray(options.columns)) {
      options.columns.forEach(colConfig => {
        const col = this.createCol({
          ...colConfig,
          parent: row
        });
        columns.push(col);
      });
    }
    
    return { row, columns };
  }

  /**
   * Add utility classes to an element
   * @param {HTMLElement} element - Element to add classes to
   * @param {Object} utilities - Utility class options
   * @returns {HTMLElement} Element with utilities
   */
  addUtilities(element, utilities = {}) {
    const classes = [];
    
    // Text alignment
    if (utilities.textAlign) {
      const align = utilities.textAlign.toLowerCase();
      if (align === 'center') classes.push(utilities.textAlignForce ? 'tx-center-f' : 'tx-center');
      if (align === 'right') classes.push(utilities.textAlignForce ? 'tx-right-f' : 'tx-right');
      if (align === 'left') classes.push(utilities.textAlignForce ? 'tx-left-f' : 'tx-left');
    }
    
    // Text style
    if (utilities.textItalic) classes.push('tx-italic');
    if (utilities.textNormal) classes.push('tx-style-normal');
    if (utilities.textNowrap) classes.push('tx-nowrap');
    
    // Float utilities
    if (utilities.float) {
      const float = utilities.float.toLowerCase();
      classes.push(`pull-${float}`);
    }
    
    // Flex utilities
    if (utilities.displayFlex) classes.push('d-flex');
    if (utilities.justifyContent) {
      classes.push(`justify-${utilities.justifyContent}`);
    }
    
    // Alignment utilities
    if (utilities.align) {
      const align = utilities.align.toLowerCase();
      classes.push(`align-${align}`);
    }
    
    // Margin utilities
    if (utilities.marginLeftAuto) classes.push('ml-auto');
    if (utilities.marginRightAuto) classes.push('mr-auto');
    
    // Flex pull utilities
    if (utilities.flexPullRight) classes.push('flex-pull-right');
    if (utilities.flexPullLeft) classes.push('flex-pull-left');
    
    // Sticky
    if (utilities.sticky) classes.push('sticky');
    
    // Hide/show mobile
    if (utilities.hideMobile) classes.push('nx-hide-mobile');
    if (utilities.showMobile) classes.push('nx-show-mobile');
    
    // Add classes to element
    if (classes.length > 0) {
      element.className = (element.className + ' ' + classes.join(' ')).trim();
    }
    
    return element;
  }

  /**
   * Create a complete grid layout
   * @param {Object} config - Grid configuration
   * @param {HTMLElement} config.parent - Parent element
   * @param {boolean} config.useContainer - Wrap in container
   * @param {Object} config.container - Container options
   * @param {Array} config.rows - Array of row configurations
   * @returns {HTMLElement} Root element (container or first row)
   */
  createGrid(config = {}) {
    let root = config.parent || document.body;
    let container = null;
    
    // Create container if needed
    if (config.useContainer !== false) {
      container = this.createContainer({
        nx: config.container?.nx,
        parent: root
      });
      root = container;
    }
    
    // Create rows
    if (config.rows && Array.isArray(config.rows)) {
      config.rows.forEach((rowConfig) => {
        this.createRowWithCols({
          ...rowConfig,
          parent: root
        });
      });
    }
    
    const result = container || root;
    return result;
  }

  /**
   * Get current breakpoint based on window width
   * @returns {string} Current breakpoint name
   */
  getCurrentBreakpoint() {
    const width = window.innerWidth;
    
    if (width >= this.breakpoints.xl) return 'xl';
    if (width >= this.breakpoints.lg) return 'lg';
    if (width >= this.breakpoints.md) return 'md';
    if (width >= this.breakpoints.sm) return 'sm';
    return 'xs';
  }

  /**
   * Check if current viewport matches breakpoint
   * @param {string} breakpoint - Breakpoint to check (sm, md, lg, xl)
   * @returns {boolean} True if matches
   */
  isBreakpoint(breakpoint) {
    const width = window.innerWidth;
    const bp = this.breakpoints[breakpoint];
    return bp ? width >= bp : false;
  }

  /**
   * Responsive column helper - returns column width based on breakpoint
   * @param {Object} sizes - Column sizes for each breakpoint
   * @param {number} sizes.xs - Extra small
   * @param {number} sizes.sm - Small
   * @param {number} sizes.md - Medium
   * @param {number} sizes.lg - Large
   * @param {number} sizes.xl - Extra large
   * @returns {Object} Column configuration
   */
  responsiveCol(sizes = {}) {
    const responsive = {};
    
    if (sizes.sm) responsive.sm = sizes.sm;
    if (sizes.md) responsive.md = sizes.md;
    if (sizes.lg) responsive.lg = sizes.lg;
    if (sizes.xl) responsive.xl = sizes.xl;
    
    return {
      cols: sizes.xs || 12,
      responsive: responsive
    };
  }
}

// Export for ES6 modules
export { NexaGrid };
export default NexaGrid;

// Export for use in CommonJS modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = NexaGrid;
}

// Make available globally
if (typeof window !== 'undefined') {
  window.NexaGrid = NexaGrid;
}

