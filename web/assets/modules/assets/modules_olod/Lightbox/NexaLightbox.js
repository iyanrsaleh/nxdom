export class NexaLightbox {
    constructor(selector) {
        this.selector = selector;
        this.element = document.querySelector(selector);
        this.targetId = this.element ? this.element.id : null;
        this.lightboxOverlay = null;
        this.currentZoom = 1;
        this.minZoom = 0.5;
        this.maxZoom = 3;
        this.zoomStep = 0.2;
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.translateX = 0;
        this.translateY = 0;
        this.init();
    }

    init() {
        if (!this.element) {
            console.error('NexaLightbox: Element not found');
            return;
        }

        // Create lightbox structure
        this.createLightboxOverlay();
        
        // Add click event to trigger element
        this.element.addEventListener('click', (e) => {
            e.preventDefault();
            this.show();
        });
    }

    createLightboxOverlay() {
        // Create lightbox container
        this.lightboxOverlay = document.createElement('div');
        this.lightboxOverlay.className = 'nexa-lightbox-overlay';
        this.lightboxOverlay.style.cssText = `
            display: none;
            position: fixed;
            z-index: 9998;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            opacity: 0;
            transition: opacity 0.3s ease;
        `;

        // Create lightbox content
        const lightboxContent = document.createElement('div');
        lightboxContent.className = 'nexa-lightbox-content';
        lightboxContent.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            max-width: 90%;
            max-height: 90%;
            transition: transform 0.3s ease;
            opacity: 0;
        `;

        // Create image element
        const img = document.createElement('img');
        img.className = 'nexa-lightbox-image';
        img.style.cssText = `
            width: 100%;
            height: auto;
            max-width: 100%;
            max-height: 100vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            cursor: grab;
            transition: transform 0.3s ease;
            transform-origin: center center;
        `;

        // Create close button (Font Awesome)
        const closeBtn = document.createElement('span');
        closeBtn.className = 'nexa-lightbox-close';
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 32px;
            font-weight: normal;
            cursor: pointer;
            z-index: 99999;
            transition: all 0.3s ease;
            user-select: none;
            padding: 8px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            box-sizing: border-box;
            border: 2px solid rgba(255, 255, 255, 0.5);
            pointer-events: auto;
        `;

        // Create zoom controls
        const zoomControls = document.createElement('div');
        zoomControls.className = 'nexa-lightbox-zoom-controls';
        zoomControls.style.cssText = `
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10001;
        `;

        const downloadBtn = document.createElement('button');
        downloadBtn.type = 'button';
        downloadBtn.className = 'nexa-zoom-btn';
        downloadBtn.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i>';
        downloadBtn.style.cssText = `
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        `;

        const zoomInBtn = document.createElement('button');
        zoomInBtn.type = 'button';
        zoomInBtn.className = 'nexa-zoom-btn';
        zoomInBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>';
        zoomInBtn.style.cssText = `
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        `;

        const zoomOutBtn = document.createElement('button');
        zoomOutBtn.type = 'button';
        zoomOutBtn.className = 'nexa-zoom-btn';
        zoomOutBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i>';
        zoomOutBtn.style.cssText = `
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        `;

        const resetZoomBtn = document.createElement('button');
        resetZoomBtn.type = 'button';
        resetZoomBtn.className = 'nexa-zoom-btn';
        resetZoomBtn.innerHTML = '<i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>';
        resetZoomBtn.style.cssText = `
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        `;

        zoomControls.appendChild(downloadBtn);
        zoomControls.appendChild(zoomInBtn);
        zoomControls.appendChild(zoomOutBtn);
        zoomControls.appendChild(resetZoomBtn);

        // Create loading spinner
        const spinner = document.createElement('div');
        spinner.className = 'nexa-lightbox-spinner';
        spinner.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        `;

        // Spinner + lightbox UI (ikon FA lewat nexa.css → all.min.css)
        if (!document.querySelector('#nexa-lightbox-styles')) {
            const style = document.createElement('style');
            style.id = 'nexa-lightbox-styles';
            style.textContent = `
                @keyframes spin {
                    0% { transform: translate(-50%, -50%) rotate(0deg); }
                    100% { transform: translate(-50%, -50%) rotate(360deg); }
                }
                .nexa-lightbox-close:hover {
                    color: #fff !important;
                    background: rgba(255, 255, 255, 0.2) !important;
                    transform: scale(1.1);
                }
                .nexa-lightbox-close:active {
                    transform: scale(0.95);
                }
                .nexa-lightbox-overlay {
                    backdrop-filter: blur(5px);
                }
                .nexa-zoom-btn:hover {
                    background: rgba(255, 255, 255, 0.2) !important;
                    transform: scale(1.1);
                }
                .nexa-zoom-btn:active {
                    transform: scale(0.95);
                }
                .nexa-lightbox-image.dragging {
                    cursor: grabbing !important;
                    transition: none !important;
                }
                .nexa-lightbox-close i,
                .nexa-zoom-btn i {
                    pointer-events: none;
                    line-height: 1;
                }
            `;
            document.head.appendChild(style);
        }

        // Assemble lightbox
        lightboxContent.appendChild(img);
        lightboxContent.appendChild(spinner);
        this.lightboxOverlay.appendChild(lightboxContent);
        this.lightboxOverlay.appendChild(closeBtn);
        this.lightboxOverlay.appendChild(zoomControls);
        document.body.appendChild(this.lightboxOverlay);

        // Event listeners
        this.closeHandler = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.hide();
        };
        
        this.overlayClickHandler = (e) => {
            if (e.target === this.lightboxOverlay) {
                e.preventDefault();
                e.stopPropagation();
                this.hide();
            }
        };
        
        closeBtn.addEventListener('click', this.closeHandler);
        this.lightboxOverlay.addEventListener('click', this.overlayClickHandler);

        // Keyboard events
        this.keydownHandler = (e) => {
            if (e.key === 'Escape' && this.lightboxOverlay.style.display === 'block') {
                e.preventDefault();
                e.stopPropagation();
                this.hide();
            }
        };
        document.addEventListener('keydown', this.keydownHandler);

        // Download event listener
        downloadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.downloadImage();
        });

        // Zoom and drag event listeners
        zoomInBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.zoomIn();
        });

        zoomOutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.zoomOut();
        });

        resetZoomBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.resetZoom();
        });

        // Mouse wheel zoom
        img.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -1 : 1;
            this.zoom(delta * this.zoomStep);
        });

        // Drag functionality
        img.addEventListener('mousedown', (e) => {
            if (this.currentZoom > 1) {
                this.isDragging = true;
                this.startX = e.clientX - this.translateX;
                this.startY = e.clientY - this.translateY;
                img.classList.add('dragging');
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (this.isDragging && this.currentZoom > 1) {
                this.translateX = e.clientX - this.startX;
                this.translateY = e.clientY - this.startY;
                this.updateImageTransform();
            }
        });

        document.addEventListener('mouseup', () => {
            if (this.isDragging) {
                this.isDragging = false;
                img.classList.remove('dragging');
            }
        });

        // Store references
        this.lightboxImage = img;
        this.lightboxContent = lightboxContent;
        this.spinner = spinner;
        this.zoomControls = zoomControls;
        this.closeBtn = closeBtn;
    }

    show() {
        if (!this.lightboxOverlay) return;

        // Get image source
        const imgSrc = this.element.src || this.element.getAttribute('data-src');
        const imgAlt = this.element.alt || 'Image';

        // Show lightbox
        this.lightboxOverlay.style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Show spinner
        this.spinner.style.display = 'block';
        this.lightboxImage.style.display = 'none';

        // Load image
        const tempImg = new Image();
        tempImg.onload = () => {
            this.lightboxImage.src = imgSrc;
            this.lightboxImage.alt = imgAlt;
            this.lightboxImage.style.display = 'block';
            this.spinner.style.display = 'none';

            // Animate in
            requestAnimationFrame(() => {
                this.lightboxOverlay.style.opacity = '1';
                this.lightboxContent.style.opacity = '1';
                this.lightboxContent.style.transform = 'translate(-50%, -50%) scale(1)';
            });
        };

        tempImg.onerror = () => {
            this.spinner.style.display = 'none';
            this.lightboxImage.style.display = 'block';
            this.lightboxImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIG5vdCBmb3VuZDwvdGV4dD48L3N2Zz4=';
            this.lightboxImage.alt = 'Image not found';
            
            requestAnimationFrame(() => {
                this.lightboxOverlay.style.opacity = '1';
                this.lightboxContent.style.opacity = '1';
                this.lightboxContent.style.transform = 'translate(-50%, -50%) scale(1)';
            });
        };

        tempImg.src = imgSrc;
    }

    hide() {
        if (!this.lightboxOverlay || this.lightboxOverlay.style.display === 'none') return;

        // Reset zoom when hiding
        this.resetZoom();

        // Animate out
        this.lightboxOverlay.style.opacity = '0';
        this.lightboxContent.style.opacity = '0';
        this.lightboxContent.style.transform = 'translate(-50%, -50%) scale(0.9)';

        setTimeout(() => {
            if (this.lightboxOverlay) {
                this.lightboxOverlay.style.display = 'none';
            }
            document.body.style.overflow = '';
        }, 300);
    }

    // Zoom methods
    zoomIn() {
        this.zoom(this.zoomStep);
    }

    zoomOut() {
        this.zoom(-this.zoomStep);
    }

    zoom(delta) {
        const newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, this.currentZoom + delta));
        if (newZoom !== this.currentZoom) {
            this.currentZoom = newZoom;
            this.updateImageTransform();
            
            // Reset position if zoomed out to 1 or less
            if (this.currentZoom <= 1) {
                this.translateX = 0;
                this.translateY = 0;
                this.updateImageTransform();
            }
        }
    }

    resetZoom() {
        this.currentZoom = 1;
        this.translateX = 0;
        this.translateY = 0;
        this.updateImageTransform();
    }

    updateImageTransform() {
        if (this.lightboxImage) {
            this.lightboxImage.style.transform = `scale(${this.currentZoom}) translate(${this.translateX / this.currentZoom}px, ${this.translateY / this.currentZoom}px)`;
            
            // Update cursor based on zoom level
            if (this.currentZoom > 1) {
                this.lightboxImage.style.cursor = this.isDragging ? 'grabbing' : 'grab';
            } else {
                this.lightboxImage.style.cursor = 'grab';
            }
        }
    }

    // Download image method
    downloadImage() {
        if (!this.lightboxImage || !this.lightboxImage.src) {
            console.error('No image to download');
            return;
        }

        // Get image source
        const imgSrc = this.lightboxImage.src;
        
        // Extract filename from URL or use target ID
        let filename = this.targetId || 'image';
        
        // Try to get filename from URL
        try {
            const url = new URL(imgSrc);
            const pathParts = url.pathname.split('/');
            const urlFilename = pathParts[pathParts.length - 1];
            if (urlFilename && urlFilename.includes('.')) {
                filename = urlFilename;
            } else {
                // Add extension based on image type or default to jpg
                filename += '.jpg';
            }
        } catch (e) {
            filename += '.jpg';
        }

        // Create download link
        const downloadLink = document.createElement('a');
        downloadLink.href = imgSrc;
        downloadLink.download = filename;
        downloadLink.style.display = 'none';
        
        // Add to DOM, click, and remove
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }

    destroy() {
        // Remove event listeners
        if (this.keydownHandler) {
            document.removeEventListener('keydown', this.keydownHandler);
            this.keydownHandler = null;
        }
        
        if (this.closeBtn && this.closeHandler) {
            this.closeBtn.removeEventListener('click', this.closeHandler);
            this.closeHandler = null;
        }
        
        if (this.lightboxOverlay && this.overlayClickHandler) {
            this.lightboxOverlay.removeEventListener('click', this.overlayClickHandler);
            this.overlayClickHandler = null;
        }
        
        // Remove DOM elements
        if (this.lightboxOverlay) {
            this.lightboxOverlay.remove();
            this.lightboxOverlay = null;
        }
        
        document.body.style.overflow = '';
    }
}

// Auto-initialize for elements with data-lightbox attribute or class
// NONAKTIF - karena menggunakan manual initialization di HTML
// document.addEventListener('DOMContentLoaded', () => {
//     const lightboxElements = document.querySelectorAll('[data-lightbox], .nexa-lightbox');
//     lightboxElements.forEach(element => {
//         // Check if already initialized
//         if (!element.hasAttribute('data-nexa-initialized')) {
//             element.setAttribute('data-nexa-initialized', 'true');
//             new NexaLightbox(`#${element.id}` || element);
//         }
//     });
// });

// Static method to initialize multiple elements at once
NexaLightbox.initAll = function(selector = 'img[data-lightbox], img.nexa-lightbox') {
    const elements = document.querySelectorAll(selector);
    const instances = [];
    
    elements.forEach((element, index) => {
        // Check if already initialized
        if (!element.hasAttribute('data-nexa-initialized')) {
            element.setAttribute('data-nexa-initialized', 'true');
            
            // Ensure element has ID
            if (!element.id) {
                element.id = 'nexa-lightbox-' + Math.random().toString(36).substr(2, 9);
            }
            
            // Create new instance with proper target ID
            const instance = new NexaLightbox(`#${element.id}`);
            instance.targetId = element.id; // Store target ID for reference
            instances.push(instance);
        }
    });
    
    return instances;
};
