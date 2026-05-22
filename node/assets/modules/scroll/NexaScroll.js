export class NexaScroll {
    constructor(options = {}) {
        this.options = {
            storageKey: 'nexaScrollPositions',
            debounceDelay: 100,
            autoInit: true,
            ...options
        };
        
        this.scrollClasses = [
            // Basic scroll classes
            '.nx-scroll',
            '.nx-scroll-x',
            
            // Custom scroll styles
            '.nx-scroll-rounded',
            '.nx-scroll-hidden',
            '.nx-scroll-autohide',
            
            // Table scroll classes
            '.nx-scroll-table',
            '.nx-scroll-smooth',
            '.nx-scroll-fade',
            '.nx-scroll-shadow',
            
            // Size variants
            '.nx-scroll-thin',
            '.nx-scroll-thick',
            
            // Color variants
            '.nx-scroll-primary'
        ];
        
        this.scrollElements = new Map();
        this.debounceTimers = new Map();
        
        if (this.options.autoInit) {
            this.init();
        }
    }
    
    /**
     * Initialize scroll position management
     */
    init() {
        this.findScrollElements();
        this.restoreScrollPositions();
        this.attachScrollListeners();
        
        // Listen for DOM changes to handle dynamically added elements
        this.observeDOM();
        
        return this;
    }
    
    /**
     * Find all elements with nx-scroll classes
     */
    findScrollElements() {
        this.scrollClasses.forEach(className => {
            const elements = document.querySelectorAll(className);
            elements.forEach(element => {
                const elementId = this.getElementId(element);
                this.scrollElements.set(elementId, {
                    element: element,
                    className: className,
                    lastPosition: { scrollTop: 0, scrollLeft: 0 }
                });
            });
        });
    }
    
    /**
     * Generate unique ID for element
     */
    getElementId(element) {
        // Use existing id if available
        if (element.id) {
            return element.id;
        }
        
        // Generate ID based on element position and classes
        const rect = element.getBoundingClientRect();
        const classes = Array.from(element.classList).join('-');
        return `nx-scroll-${classes}-${Math.round(rect.top)}-${Math.round(rect.left)}`;
    }
    
    /**
     * Attach scroll event listeners
     */
    attachScrollListeners() {
        this.scrollElements.forEach((data, elementId) => {
            const { element } = data;
            
            element.addEventListener('scroll', (event) => {
                this.handleScroll(elementId, event);
            });
        });
    }
    
    /**
     * Handle scroll events with debouncing
     */
    handleScroll(elementId, event) {
        const element = event.target;
        
        // Clear existing timer
        if (this.debounceTimers.has(elementId)) {
            clearTimeout(this.debounceTimers.get(elementId));
        }
        
        // Set new timer
        const timer = setTimeout(() => {
            this.saveScrollPosition(elementId, element);
        }, this.options.debounceDelay);
        
        this.debounceTimers.set(elementId, timer);
    }
    
    /**
     * Save scroll position to localStorage
     */
    saveScrollPosition(elementId, element) {
        const position = {
            scrollTop: element.scrollTop,
            scrollLeft: element.scrollLeft,
            timestamp: Date.now()
        };
        
        // Update in memory
        if (this.scrollElements.has(elementId)) {
            this.scrollElements.get(elementId).lastPosition = position;
        }
        
        // Save to localStorage
        const savedPositions = this.getSavedPositions();
        savedPositions[elementId] = position;
        
        try {
            localStorage.setItem(this.options.storageKey, JSON.stringify(savedPositions));
        } catch (error) {
            console.warn('NexaScroll: Could not save to localStorage:', error);
        }
    }
    
    /**
     * Get saved positions from localStorage
     */
    getSavedPositions() {
        try {
            const saved = localStorage.getItem(this.options.storageKey);
            return saved ? JSON.parse(saved) : {};
        } catch (error) {
            console.warn('NexaScroll: Could not read from localStorage:', error);
            return {};
        }
    }
    
    /**
     * Restore scroll positions from localStorage
     */
    restoreScrollPositions() {
        const savedPositions = this.getSavedPositions();
        
        this.scrollElements.forEach((data, elementId) => {
            const { element } = data;
            const savedPosition = savedPositions[elementId];
            
            if (savedPosition) {
                // Restore position after a short delay to ensure DOM is ready
                setTimeout(() => {
                    element.scrollTop = savedPosition.scrollTop || 0;
                    element.scrollLeft = savedPosition.scrollLeft || 0;
                }, 50);
            }
        });
    }
    
    /**
     * Observe DOM changes for dynamically added elements
     */
    observeDOM() {
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver((mutations) => {
                let shouldReinit = false;
                
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1) { // Element node
                                const hasScrollClass = this.scrollClasses.some(className => 
                                    node.matches && node.matches(className)
                                );
                                const containsScrollElements = this.scrollClasses.some(className =>
                                    node.querySelector && node.querySelector(className)
                                );
                                
                                if (hasScrollClass || containsScrollElements) {
                                    shouldReinit = true;
                                }
                            }
                        });
                    }
                });
                
                if (shouldReinit) {
                    setTimeout(() => {
                        this.findScrollElements();
                        this.restoreScrollPositions();
                        this.attachScrollListeners();
                    }, 100);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
    
    /**
     * Manually add element to scroll management
     */
    addElement(element, customId = null) {
        if (!element || typeof element.addEventListener !== 'function') {
            return this;
        }
        const elementId = customId || this.getElementId(element);
        
        this.scrollElements.set(elementId, {
            element: element,
            className: 'manual',
            lastPosition: { scrollTop: 0, scrollLeft: 0 }
        });
        
        element.addEventListener('scroll', (event) => {
            this.handleScroll(elementId, event);
        });
        
        // Restore position if exists
        const savedPositions = this.getSavedPositions();
        const savedPosition = savedPositions[elementId];
        
        if (savedPosition) {
            setTimeout(() => {
                element.scrollTop = savedPosition.scrollTop || 0;
                element.scrollLeft = savedPosition.scrollLeft || 0;
            }, 50);
        }
        
        return this;
    }
    
    /**
     * Remove element from scroll management
     */
    removeElement(elementId) {
        this.scrollElements.delete(elementId);
        
        if (this.debounceTimers.has(elementId)) {
            clearTimeout(this.debounceTimers.get(elementId));
            this.debounceTimers.delete(elementId);
        }
        
        // Remove from localStorage
        const savedPositions = this.getSavedPositions();
        delete savedPositions[elementId];
        
        try {
            localStorage.setItem(this.options.storageKey, JSON.stringify(savedPositions));
        } catch (error) {
            console.warn('NexaScroll: Could not update localStorage:', error);
        }
        
        return this;
    }
    
    /**
     * Clear all saved positions
     */
    clearSavedPositions() {
        try {
            localStorage.removeItem(this.options.storageKey);
        } catch (error) {
            console.warn('NexaScroll: Could not clear localStorage:', error);
        }
        
        return this;
    }
    
    /**
     * Get current scroll position for element
     */
    getScrollPosition(elementId) {
        if (this.scrollElements.has(elementId)) {
            const { element } = this.scrollElements.get(elementId);
            return {
                scrollTop: element.scrollTop,
                scrollLeft: element.scrollLeft
            };
        }
        return null;
    }
    
    /**
     * Set scroll position for element
     */
    setScrollPosition(elementId, position) {
        if (this.scrollElements.has(elementId)) {
            const { element } = this.scrollElements.get(elementId);
            
            if (position.scrollTop !== undefined) {
                element.scrollTop = position.scrollTop;
            }
            
            if (position.scrollLeft !== undefined) {
                element.scrollLeft = position.scrollLeft;
            }
            
            // Save the new position
            this.saveScrollPosition(elementId, element);
        }
        
        return this;
    }
    
    /**
     * Get all managed elements
     */
    getElements() {
        return Array.from(this.scrollElements.entries()).map(([id, data]) => ({
            id,
            element: data.element,
            className: data.className,
            position: data.lastPosition
        }));
    }
    
    /**
     * Destroy the instance and clean up
     */
    destroy() {
        // Clear all timers
        this.debounceTimers.forEach(timer => clearTimeout(timer));
        this.debounceTimers.clear();
        
        // Clear elements
        this.scrollElements.clear();
        
        return this;
    }
    
    /**
     * Enable/disable scroll position saving
     */
    on = true;
    
    enable() {
        this.on = true;
        return this;
    }
    
    disable() {
        this.on = false;
        return this;
    }
}
