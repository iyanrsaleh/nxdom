/**
 * TabelRaw - ASCII Table Generator for Terminal
 * 
 * Komponen untuk membuat tabel dengan border karakter ASCII seperti terminal tradisional.
 * Mendukung berbagai konfigurasi seperti alignment, border style, index column, dan auto-width calculation.
 * 
 * @example
 * const data = [
 *     { id: 1, name: 'John', age: 30 },
 *     { id: 2, name: 'Jane', age: 25 }
 * ];
 * 
 * const table = new TabelRaw(data, {
 *     border: true,
 *     headerStyle: 'double',
 *     showIndex: true
 * });
 * 
 * console.log(table.render());
 * // Output:
 * // +----+-------+-----+
 * // | No | id    | name | age |
 * // +====+=======+======+=====+
 * // |  1 | 1     | John | 30  |
 * // |  2 | 2     | Jane | 25  |
 * // +----+-------+-----+
 * 
 * @class TabelRaw
 */
export class TabelRaw {
    /**
     * Creates an instance of TabelRaw
     * 
     * @param {Array<Object>} data - Array of objects to display in the table. Each object represents a row.
     * @param {Object} options - Configuration options for the table
     * @param {boolean} [options.border=true] - Show ASCII border characters
     * @param {string} [options.headerStyle='double'] - Header separator style: 'single', 'double', or 'bold'
     * @param {string} [options.align='left'] - Default text alignment: 'left', 'center', or 'right'
     * @param {Object} [options.columnAlign={}] - Per-column alignment override: { columnName: 'center' }
     * @param {number} [options.maxWidth=120] - Maximum table width in characters
     * @param {boolean} [options.truncate=true] - Truncate long text with '...'
     * @param {boolean} [options.showIndex=false] - Show row index number in first column
     * @param {string} [options.indexHeader='#'] - Header text for index column
     * 
     * @example
     * const table = new TabelRaw([
     *     { version: '1.0.3', status: 'development' }
     * ], {
     *     border: true,
     *     showIndex: true,
     *     columnAlign: { status: 'center' }
     * });
     */
    constructor(data = [], options = {}) {
        this.data = data;
        this.options = {
            border: true,           // Show border
            headerStyle: 'double',  // 'single', 'double', 'bold'
            align: 'left',          // Default alignment: 'left', 'center', 'right'
            columnAlign: {},        // Per-column alignment: { columnName: 'center' }
            maxWidth: 120,          // Maximum table width
            truncate: true,         // Truncate long text
            showIndex: false,       // Show row index
            indexHeader: '#',       // Header for index column
            ...options
        };
        
        this.columns = [];
        this.columnWidths = {};
        this.rows = [];
    }

    /**
     * Analyze data and calculate optimal column widths
     * 
     * This method:
     * - Extracts column names from the first data object
     * - Calculates minimum width needed for each column based on header and content
     * - Adds index column if showIndex is enabled
     * - Adjusts widths if total exceeds maxWidth
     * 
     * @private
     * @returns {void}
     */
    analyze() {
        if (!this.data || this.data.length === 0) {
            return;
        }

        // Get columns from first object
        const firstRow = this.data[0];
        this.columns = Object.keys(firstRow);

        // Add index column if needed
        if (this.options.showIndex) {
            this.columns.unshift(this.options.indexHeader);
        }

        // Calculate minimum width for each column
        this.columns.forEach(col => {
            let maxLength = col.length; // Header length

            this.data.forEach((row, index) => {
                let value = '';
                
                if (col === this.options.indexHeader && this.options.showIndex) {
                    value = String(index + 1);
                } else {
                    value = this.formatValue(row[col]);
                }
                
                maxLength = Math.max(maxLength, value.length);
            });

            // Add padding (2 spaces on each side)
            this.columnWidths[col] = maxLength + 2;
        });

        // Adjust if total width exceeds maxWidth
        this.adjustWidths();
    }

    /**
     * Adjust column widths proportionally if total width exceeds maxWidth
     * 
     * Scales down all columns proportionally while maintaining minimum width
     * to ensure headers are always readable.
     * 
     * @private
     * @returns {void}
     */
    adjustWidths() {
        const totalWidth = Object.values(this.columnWidths).reduce((a, b) => a + b, 0);
        const borderWidth = (this.columns.length + 1) * 1; // 1 char per border

        if (totalWidth + borderWidth > this.options.maxWidth) {
            const scale = (this.options.maxWidth - borderWidth) / totalWidth;
            this.columns.forEach(col => {
                this.columnWidths[col] = Math.max(
                    Math.floor(this.columnWidths[col] * scale),
                    col.length + 2 // Minimum width
                );
            });
        }
    }

    /**
     * Format any value type to string representation
     * 
     * Handles:
     * - null/undefined → empty string
     * - Objects → JSON.stringify
     * - Other types → String conversion
     * 
     * @param {*} value - Value to format
     * @returns {string} Formatted string value
     * 
     * @example
     * formatValue(null) // ''
     * formatValue({a: 1}) // '{"a":1}'
     * formatValue(123) // '123'
     */
    formatValue(value) {
        if (value === null || value === undefined) {
            return '';
        }
        if (typeof value === 'object') {
            return JSON.stringify(value);
        }
        return String(value);
    }

    /**
     * Pad text to specified width with alignment
     * 
     * @param {string} text - Text to pad
     * @param {number} width - Target width in characters
     * @param {string} [align='left'] - Alignment: 'left', 'center', or 'right'
     * @returns {string} Padded text
     * 
     * @example
     * padText('Hello', 10, 'left')   // ' Hello     '
     * padText('Hello', 10, 'center')  // '  Hello   '
     * padText('Hello', 10, 'right')   // '     Hello '
     */
    padText(text, width, align = 'left') {
        text = String(text);
        
        // Truncate if needed
        if (this.options.truncate && text.length > width - 2) {
            text = text.substring(0, width - 5) + '...';
        }

        const padding = width - text.length;
        
        if (align === 'center') {
            const leftPad = Math.floor(padding / 2);
            const rightPad = padding - leftPad;
            return ' '.repeat(leftPad) + text + ' '.repeat(rightPad);
        } else if (align === 'right') {
            return ' '.repeat(padding - 1) + text + ' ';
        } else {
            return ' ' + text + ' '.repeat(padding - 1);
        }
    }

    /**
     * Create horizontal border line using ASCII characters
     * 
     * @param {string} [position='middle'] - Border position: 'top', 'header', 'middle', or 'bottom'
     * @returns {string} Border line string
     * 
     * @example
     * createBorder('top')    // '+----+----+'
     * createBorder('header')  // '+====+====+' (if headerStyle is 'double')
     */
    createBorder(position = 'middle') {
        let left = '+';
        let middle = '+';
        let right = '+';
        let line = '-';

        if (position === 'top') {
            left = '+';
            middle = '+';
            right = '+';
            line = '-';
        } else if (position === 'header') {
            left = '+';
            middle = '+';
            right = '+';
            line = this.options.headerStyle === 'double' ? '=' : '-';
        } else if (position === 'bottom') {
            left = '+';
            middle = '+';
            right = '+';
            line = '-';
        }

        const parts = this.columns.map(col => line.repeat(this.columnWidths[col]));
        return left + parts.join(middle) + right;
    }

    /**
     * Create a formatted table row string
     * 
     * @param {Object} data - Row data object
     * @param {boolean} [isHeader=false] - Whether this is a header row
     * @returns {string} Formatted row string with borders
     * 
     * @example
     * createRow({ id: 1, name: 'John' }, false) // '| 1 | John |'
     * createRow({}, true) // '| id | name |' (header row)
     */
    createRow(data, isHeader = false) {
        const parts = this.columns.map(col => {
            let value = '';
            let align = this.options.align;

            if (isHeader) {
                value = col;
                align = 'center';
            } else {
                if (col === this.options.indexHeader && this.options.showIndex) {
                    value = data._index || '';
                    align = 'right';
                } else {
                    value = this.formatValue(data[col]);
                    align = this.options.columnAlign[col] || this.options.align;
                }
            }

            return this.padText(value, this.columnWidths[col], align);
        });

        return '|' + parts.join('|') + '|';
    }

    /**
     * Generate complete ASCII table string
     * 
     * Processes data, calculates column widths, and generates formatted table
     * with borders, headers, and data rows.
     * 
     * @returns {string} Complete ASCII table as multi-line string
     * 
     * @example
     * const table = new TabelRaw([{id: 1, name: 'John'}]);
     * const output = table.render();
     * console.log(output);
     * // +----+------+
     * // | id | name |
     * // +====+======+
     * // | 1  | John |
     * // +----+------+
     */
    render() {
        if (!this.data || this.data.length === 0) {
            return 'No data available';
        }

        this.analyze();

        const lines = [];

        // Top border
        if (this.options.border) {
            lines.push(this.createBorder('top'));
        }

        // Header
        lines.push(this.createRow({}, true));

        // Header separator
        if (this.options.border) {
            lines.push(this.createBorder('header'));
        }

        // Data rows
        this.data.forEach((row, index) => {
            const rowData = { ...row };
            if (this.options.showIndex) {
                rowData._index = index + 1;
            }
            lines.push(this.createRow(rowData));
        });

        // Bottom border
        if (this.options.border) {
            lines.push(this.createBorder('bottom'));
        }

        return lines.join('\n');
    }

    /**
     * Render table as HTML with terminal styling
     * 
     * Wraps the ASCII table in a <pre> tag with monospace font and green color
     * suitable for terminal/console display.
     * 
     * @returns {string} HTML string with styled <pre> tag
     * 
     * @example
     * const table = new TabelRaw([{id: 1}]);
     * const html = table.renderHTML();
     * // '<pre style="font-family: 'Courier New'...">+----+...</pre>'
     */
    renderHTML() {
        const asciiTable = this.render();
        return `<pre style="font-family: 'Courier New', Consolas, Monaco, monospace; line-height: 1.2; margin: 0; color: #00ff00;">${asciiTable}</pre>`;
    }
}

/**
 * Helper function to quickly create and render ASCII table
 * 
 * Convenience function that creates a TabelRaw instance and immediately renders it.
 * 
 * @param {Array<Object>} data - Array of objects to display
 * @param {Object} [options={}] - Table configuration options (same as TabelRaw constructor)
 * @returns {string} Rendered ASCII table string
 * 
 * @example
 * const ascii = createTable([
 *     { version: '1.0.3', status: 'dev' }
 * ], { border: true });
 * console.log(ascii);
 */
export function createTable(data, options = {}) {
    const table = new TabelRaw(data, options);
    return table.render();
}

/**
 * Helper function to quickly create and render HTML table for terminal
 * 
 * Convenience function that creates a TabelRaw instance and immediately renders it as HTML.
 * 
 * @param {Array<Object>} data - Array of objects to display
 * @param {Object} [options={}] - Table configuration options (same as TabelRaw constructor)
 * @returns {string} Rendered HTML table string
 * 
 * @example
 * const html = createTableHTML([
 *     { version: '1.0.3', status: 'dev' }
 * ], { border: true });
 * document.getElementById('output').innerHTML = html;
 */
export function createTableHTML(data, options = {}) {
    const table = new TabelRaw(data, options);
    return table.renderHTML();
}

