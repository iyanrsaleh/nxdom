// Import required modules for Node.js environment
import { createCanvas, Image } from "canvas";

class ImageManager {
  constructor() {
    this.supportedFormats = ["jpg", "jpeg", "png", "gif", "webp"];
    // Determine if we're in browser or Node environment
    this.isBrowser = typeof window !== "undefined";
  }

  /**
   * Validates if the file is an image based on its extension
   * @param {string} fileName - Name of the file to validate
   * @returns {boolean} - True if file is a valid image
   */
  isValidImageFormat(fileName) {
    const extension = fileName.split(".").pop().toLowerCase();
    return this.supportedFormats.includes(extension);
  }

  /**
   * Get image dimensions
   * @param {File|Buffer} file - Image file or buffer
   * @returns {Promise<{width: number, height: number}>}
   */
  getImageDimensions(file) {
    return new Promise((resolve, reject) => {
      const img = new Image();

      img.onload = () => {
        resolve({
          width: img.width,
          height: img.height,
        });
      };

      img.onerror = () => reject(new Error("Failed to load image"));

      if (this.isBrowser) {
        img.src = URL.createObjectURL(file);
      } else {
        img.src = file;
      }
    });
  }

  /**
   * Convert file size to readable format
   * @param {number} bytes - File size in bytes
   * @returns {string} - Formatted file size
   */
  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  /**
   * Create a thumbnail from an image file
   * @param {File|Buffer} file - Image file or buffer
   * @param {number} maxWidth - Maximum width of thumbnail
   * @param {number} maxHeight - Maximum height of thumbnail
   * @returns {Promise<string>} - Base64 encoded thumbnail
   */
  createThumbnail(file, maxWidth = 200, maxHeight = 200) {
    return new Promise((resolve, reject) => {
      const img = new Image();

      img.onload = () => {
        const canvas = this.isBrowser
          ? document.createElement("canvas")
          : createCanvas(maxWidth, maxHeight);

        let width = img.width;
        let height = img.height;

        // Calculate new dimensions while maintaining aspect ratio
        if (width > height) {
          if (width > maxWidth) {
            height = height * (maxWidth / width);
            width = maxWidth;
          }
        } else {
          if (height > maxHeight) {
            width = width * (maxHeight / height);
            height = maxHeight;
          }
        }

        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, width, height);

        resolve(canvas.toDataURL("image/jpeg", 0.7));
      };

      img.onerror = () => reject(new Error("Failed to create thumbnail"));

      if (this.isBrowser) {
        img.src = URL.createObjectURL(file);
      } else {
        img.src = file;
      }
    });
  }

  /**
   * Compress an image file
   * @param {File|Buffer} file - Image file or buffer to compress
   * @param {number} quality - Compression quality (0-1)
   * @returns {Promise<Blob|Buffer>} - Compressed image blob or buffer
   */
  compressImage(file, quality = 0.7) {
    return new Promise((resolve, reject) => {
      const img = new Image();

      img.onload = () => {
        const canvas = this.isBrowser
          ? document.createElement("canvas")
          : createCanvas(img.width, img.height);

        canvas.width = img.width;
        canvas.height = img.height;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);

        if (this.isBrowser) {
          canvas.toBlob((blob) => resolve(blob), "image/jpeg", quality);
        } else {
          // For Node.js, return buffer
          const buffer = canvas.toBuffer("image/jpeg", { quality });
          resolve(buffer);
        }
      };

      img.onerror = () => reject(new Error("Failed to compress image"));

      if (this.isBrowser) {
        img.src = URL.createObjectURL(file);
      } else {
        img.src = file;
      }
    });
  }
}

export default ImageManager;
