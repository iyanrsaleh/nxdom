export class NexaDimensi {
    constructor(options = {}) {
        // Default options untuk kalkulasi dimensi
        this.options = {
            defaultUnit: 'px',
            precision: 2,
            fallbackStrategy: 'viewport', // viewport | document | zero
            clampMin: 0,
            ...options
        };
    }

    /**
     * Konversi pixel ke unit lain (vh, vw, %, em, rem)
     * @param {number} pixels - Nilai dalam pixel
     * @param {string} targetUnit - Unit target ('vh', 'vw', '%', 'em', 'rem', 'px')
     * @param {string} dimension - Dimensi ('height' atau 'width')
     * @param {Element} element - Element untuk referensi parent (untuk %)
     * @returns {number|string} - Nilai dalam unit target
     */
    convertPixelToUnit(pixels, targetUnit, dimension, element) {
        if (targetUnit === 'px') {
            return pixels;
        }

        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight
        };

        let result;
        
        switch (targetUnit.toLowerCase()) {
            case 'vh':
                result = (pixels / viewport.height) * 100;
                return parseFloat(result.toFixed(this.options.precision)) + 'vh';
                
            case 'vw':
                result = (pixels / viewport.width) * 100;
                return parseFloat(result.toFixed(this.options.precision)) + 'vw';
                
            case '%':
                // Untuk %, kita perlu parent element sebagai referensi
                const parent = element.parentElement;
                if (!parent) {
                    console.warn('Tidak dapat menghitung % tanpa parent element');
                    return pixels + 'px';
                }
                
                const parentDimension = dimension === 'height' ? 
                    parent.offsetHeight : parent.offsetWidth;
                    
                if (parentDimension === 0) {
                    console.warn('Parent element memiliki dimensi 0');
                    return '0%';
                }
                
                result = (pixels / parentDimension) * 100;
                return parseFloat(result.toFixed(this.options.precision)) + '%';
                
            case 'em':
                // em relatif terhadap font-size element itu sendiri
                const computedStyle = window.getComputedStyle(element);
                const fontSize = parseFloat(computedStyle.fontSize);
                result = pixels / fontSize;
                return parseFloat(result.toFixed(this.options.precision)) + 'em';
                
            case 'rem':
                // rem relatif terhadap font-size root element
                const rootFontSize = parseFloat(
                    window.getComputedStyle(document.documentElement).fontSize
                );
                result = pixels / rootFontSize;
                return parseFloat(result.toFixed(this.options.precision)) + 'rem';
                
            case 'vmin':
                const vmin = Math.min(viewport.width, viewport.height);
                result = (pixels / vmin) * 100;
                return parseFloat(result.toFixed(this.options.precision)) + 'vmin';
                
            case 'vmax':
                const vmax = Math.max(viewport.width, viewport.height);
                result = (pixels / vmax) * 100;
                return parseFloat(result.toFixed(this.options.precision)) + 'vmax';
                
            default:
                console.warn(`Unit "${targetUnit}" tidak didukung. Menggunakan px.`);
                return pixels + 'px';
        }
    }

    /**
     * Mendapatkan tinggi (height) dari elemen target
     * @param {string} selector - CSS selector untuk target elemen
     * @param {number} subtract - Nilai yang akan dikurangi dari tinggi (opsional)
     * @param {string} unit - Unit yang diinginkan: 'px', 'vh', '%', 'vw', 'em', 'rem' (opsional)
     * @returns {number|string} - Tinggi elemen dalam unit yang diminta (setelah dikurangi jika ada)
     */
    height(selector, subtract = 0, unit = 'px', options = {}) {
        const mergedOptions = {
            fallbackStrategy: this.options.fallbackStrategy,
            clampMin: this.options.clampMin,
            ...options
        };

        const element = document.querySelector(selector);
        let baseHeight = this.getElementDimension(element, 'height');

        if (baseHeight === null || baseHeight === undefined) {
            baseHeight = this.resolveFallbackDimension('height', mergedOptions.fallbackStrategy);
        }

        const resultPx = Math.max(
            mergedOptions.clampMin,
            (baseHeight || 0) - (subtract || 0)
        );

        // Konversi ke unit yang diminta
        const convertedResult = this.convertPixelToUnit(resultPx, unit, 'height', element || document.documentElement);

        return convertedResult;
    }

    /**
     * Mendapatkan lebar (width) dari elemen target
     * @param {string} selector - CSS selector untuk target elemen
     * @param {number} subtract - Nilai yang akan dikurangi dari lebar (opsional)
     * @param {string} unit - Unit yang diinginkan: 'px', 'vw', '%', 'vh', 'em', 'rem' (opsional)
     * @returns {number|string} - Lebar elemen dalam unit yang diminta (setelah dikurangi jika ada)
     */
    width(selector, subtract = 0, unit = 'px', options = {}) {
        const mergedOptions = {
            fallbackStrategy: this.options.fallbackStrategy,
            clampMin: this.options.clampMin,
            ...options
        };

        const element = document.querySelector(selector);
        let baseWidth = this.getElementDimension(element, 'width');

        if (baseWidth === null || baseWidth === undefined) {
            baseWidth = this.resolveFallbackDimension('width', mergedOptions.fallbackStrategy);
        }

        const resultPx = Math.max(
            mergedOptions.clampMin,
            (baseWidth || 0) - (subtract || 0)
        );

        // Konversi ke unit yang diminta
        const convertedResult = this.convertPixelToUnit(resultPx, unit, 'width', element || document.documentElement);

        return convertedResult;
    }

    /**
     * Mendapatkan dimensi lengkap (height dan width) dari elemen target
     * @param {string} selector - CSS selector untuk target elemen
     * @param {object} subtract - Object berisi nilai pengurangan {height: number, width: number} (opsional)
     * @param {object} units - Object berisi unit untuk height dan width {height: 'vh', width: 'vw'} (opsional)
     * @returns {object} - Object berisi height dan width dalam unit yang diminta (setelah dikurangi jika ada)
     */
    dimensions(selector, subtract = {}, units = {}) {
        const element = document.querySelector(selector);

        const originalHeight = this.getElementDimension(element, 'height');
        const originalWidth = this.getElementDimension(element, 'width');

        const subtractHeight = subtract.height || 0;
        const subtractWidth = subtract.width || 0;

        const heightPx = (originalHeight ?? this.resolveFallbackDimension('height')) - subtractHeight;
        const widthPx = (originalWidth ?? this.resolveFallbackDimension('width')) - subtractWidth;

        // Konversi ke unit yang diminta
        const heightUnit = units.height || this.options.defaultUnit;
        const widthUnit = units.width || this.options.defaultUnit;

        const result = {
            height: this.convertPixelToUnit(Math.max(this.options.clampMin, heightPx), heightUnit, 'height', element || document.documentElement),
            width: this.convertPixelToUnit(Math.max(this.options.clampMin, widthPx), widthUnit, 'width', element || document.documentElement)
        };


        return result;
    }

    /**
     * Mendapatkan posisi elemen (top, left, right, bottom)
     * @param {string} selector - CSS selector untuk target elemen
     * @returns {object} - Object berisi posisi elemen
     */
    position(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            console.warn(`Element dengan selector "${selector}" tidak ditemukan`);
            return { top: 0, left: 0, right: 0, bottom: 0 };
        }
        const rect = element.getBoundingClientRect();
        return {
            top: rect.top,
            left: rect.left,
            right: rect.right,
            bottom: rect.bottom
        };
    }

    /**
     * Mendapatkan data lengkap elemen termasuk unit pengukuran (px, %, vh, vw, em, rem, dll)
     * @param {string} selector - CSS selector untuk target elemen
     * @returns {object} - Object berisi semua data elemen dan unit pengukurannya
     */
    data(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            console.warn(`Element dengan selector "${selector}" tidak ditemukan`);
            return null;
        }

        const computedStyle = window.getComputedStyle(element);
        const inlineStyle = element.style;
        
        // Helper function untuk mengekstrak nilai dan unit
        const parseValueUnit = (value) => {
            if (!value || value === 'auto' || value === 'none') {
                return { value: null, unit: null, original: value };
            }
            
            const match = value.match(/^(-?\d*\.?\d+)(.*)$/);
            if (match) {
                return {
                    value: parseFloat(match[1]),
                    unit: match[2] || 'px',
                    original: value
                };
            }
            return { value: null, unit: null, original: value };
        };

        // Mendapatkan dimensi dengan unit
        const getDimensionData = (property) => {
            const inline = inlineStyle[property];
            const computed = computedStyle[property];
            
            return {
                inline: parseValueUnit(inline),
                computed: parseValueUnit(computed),
                pixels: parseFloat(computed) || 0
            };
        };

        // Deteksi viewport units
        const detectViewportUnits = () => {
            const viewportUnits = [];
            const properties = ['width', 'height', 'top', 'left', 'right', 'bottom', 'margin', 'padding'];
            
            properties.forEach(prop => {
                const inlineValue = inlineStyle[prop];
                if (inlineValue && (inlineValue.includes('vh') || inlineValue.includes('vw') || 
                                  inlineValue.includes('vmin') || inlineValue.includes('vmax'))) {
                    viewportUnits.push({ property: prop, value: inlineValue });
                }
            });
            
            return viewportUnits;
        };

        // Deteksi percentage units
        const detectPercentageUnits = () => {
            const percentageUnits = [];
            const properties = ['width', 'height', 'top', 'left', 'right', 'bottom'];
            
            properties.forEach(prop => {
                const inlineValue = inlineStyle[prop];
                if (inlineValue && inlineValue.includes('%')) {
                    percentageUnits.push({ property: prop, value: inlineValue });
                }
            });
            
            return percentageUnits;
        };

        // Deteksi relative units (em, rem)
        const detectRelativeUnits = () => {
            const relativeUnits = [];
            const properties = ['width', 'height', 'fontSize', 'margin', 'padding'];
            
            properties.forEach(prop => {
                const inlineValue = inlineStyle[prop];
                if (inlineValue && (inlineValue.includes('em') || inlineValue.includes('rem'))) {
                    relativeUnits.push({ property: prop, value: inlineValue });
                }
            });
            
            return relativeUnits;
        };

        // Analisis positioning
        const getPositioningData = () => {
            return {
                position: computedStyle.position,
                display: computedStyle.display,
                float: computedStyle.float,
                zIndex: computedStyle.zIndex,
                top: getDimensionData('top'),
                left: getDimensionData('left'),
                right: getDimensionData('right'),
                bottom: getDimensionData('bottom')
            };
        };

        // Box model data
        const getBoxModelData = () => {
            return {
                width: getDimensionData('width'),
                height: getDimensionData('height'),
                minWidth: getDimensionData('minWidth'),
                maxWidth: getDimensionData('maxWidth'),
                minHeight: getDimensionData('minHeight'),
                maxHeight: getDimensionData('maxHeight'),
                margin: {
                    top: getDimensionData('marginTop'),
                    right: getDimensionData('marginRight'),
                    bottom: getDimensionData('marginBottom'),
                    left: getDimensionData('marginLeft')
                },
                padding: {
                    top: getDimensionData('paddingTop'),
                    right: getDimensionData('paddingRight'),
                    bottom: getDimensionData('paddingBottom'),
                    left: getDimensionData('paddingLeft')
                },
                border: {
                    top: getDimensionData('borderTopWidth'),
                    right: getDimensionData('borderRightWidth'),
                    bottom: getDimensionData('borderBottomWidth'),
                    left: getDimensionData('borderLeftWidth')
                }
            };
        };

        // Responsive breakpoint detection
        const getResponsiveInfo = () => {
            const rect = element.getBoundingClientRect();
            const viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };
            
            return {
                viewport: viewport,
                elementRect: {
                    width: rect.width,
                    height: rect.height,
                    top: rect.top,
                    left: rect.left
                },
                percentageOfViewport: {
                    width: ((rect.width / viewport.width) * 100).toFixed(this.options.precision) + '%',
                    height: ((rect.height / viewport.height) * 100).toFixed(this.options.precision) + '%'
                }
            };
        };

        return {
            element: element,
            selector: selector,
            
            // Unit detection
            units: {
                viewport: detectViewportUnits(),
                percentage: detectPercentageUnits(),
                relative: detectRelativeUnits(),
                hasViewportUnits: detectViewportUnits().length > 0,
                hasPercentageUnits: detectPercentageUnits().length > 0,
                hasRelativeUnits: detectRelativeUnits().length > 0
            },
            
            // Dimensions and positioning
            boxModel: getBoxModelData(),
            positioning: getPositioningData(),
            responsive: getResponsiveInfo(),
            
            // Computed values
            computed: {
                width: parseFloat(computedStyle.width) || 0,
                height: parseFloat(computedStyle.height) || 0,
                fontSize: parseValueUnit(computedStyle.fontSize),
                lineHeight: parseValueUnit(computedStyle.lineHeight)
            },
            
            // CSS properties summary
            summary: {
                isResponsive: detectViewportUnits().length > 0 || detectPercentageUnits().length > 0,
                isFixed: computedStyle.position === 'fixed',
                isAbsolute: computedStyle.position === 'absolute',
                isRelative: computedStyle.position === 'relative',
                isSticky: computedStyle.position === 'sticky',
                hasOverflow: computedStyle.overflow !== 'visible',
                isScrollable: computedStyle.overflow === 'auto' || computedStyle.overflow === 'scroll' ||
                             computedStyle.overflowY === 'auto' || computedStyle.overflowY === 'scroll'
            }
        };
    }

    getElementDimension(element, dimension) {
        if (!element) {
            return null;
        }

        const rect = element.getBoundingClientRect();
        const computed = window.getComputedStyle(element);

        if (dimension === 'height') {
            const rectHeight = rect.height;
            if (rectHeight && rectHeight > 0) {
                return rectHeight;
            }
            return parseFloat(computed.height) || element.clientHeight || element.offsetHeight || null;
        }

        if (dimension === 'width') {
            const rectWidth = rect.width;
            if (rectWidth && rectWidth > 0) {
                return rectWidth;
            }
            return parseFloat(computed.width) || element.clientWidth || element.offsetWidth || null;
        }

        return null;
    }

    resolveFallbackDimension(dimension, strategy = this.options.fallbackStrategy) {
        if (strategy === 'viewport') {
            return dimension === 'height' ? window.innerHeight : window.innerWidth;
        }

        if (strategy === 'document') {
            const doc = document.documentElement;
            return dimension === 'height'
                ? doc.clientHeight || window.innerHeight
                : doc.clientWidth || window.innerWidth;
        }

        return 0;
    }
}