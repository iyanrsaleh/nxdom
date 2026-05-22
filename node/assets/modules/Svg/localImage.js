/**
 * Asset Image Manager untuk Electron
 * Adaptasi dari React Native ke Electron
 * 
 * Mengelola semua asset gambar dan SVG dalam aplikasi
 */

class AssetsImage {
  constructor() {
    this.images = {};
    this.navigationIcons = {};
    this.basePath = '/assets'; // Base path untuk assets di Electron
  }

  /**
   * Initialize asset manager
   * Di Electron, kita tidak bisa menggunakan require.context seperti di Webpack
   * Jadi kita akan menggunakan pendekatan manual atau dynamic import
   */
  initialize() {
    try {
      // Untuk Electron, kita akan menggunakan pendekatan manual
      // atau bisa menggunakan fs.readdir jika diperlukan
      
      // Contoh manual registration (bisa diperluas sesuai kebutuhan)
      this.registerDefaultAssets();
      
      return true;
    } catch (error) {
      console.error('Failed to initialize images:', error);
      return false;
    }
  }

  /**
   * Register default assets
   * Tambahkan asset default di sini
   */
  registerDefaultAssets() {
    // Contoh: register SVG dari svgContent
    // Ini akan di-populate dari svgContent.js
    
    // Register navigation icons jika ada
    // this.registerNavigationIcon('home', '/assets/icons/home.svg');
    // this.registerNavigationIcon('profile', '/assets/icons/profile.svg');
  }

  /**
   * Register single image
   * @param {string} key - Key untuk mengakses image
   * @param {string} path - Path ke image file
   */
  register(key, path) {
    this.images[key] = path;
  }

  /**
   * Register multiple images
   * @param {Object} images - Object dengan key-value pairs
   */
  registerMultiple(images) {
    Object.entries(images).forEach(([key, path]) => {
      this.register(key, path);
    });
  }

  /**
   * Get image by key
   * @param {string} key - Key untuk mengakses image
   * @returns {string|null} - Path ke image atau null jika tidak ditemukan
   */
  get(key) {
    if (this.images.hasOwnProperty(key)) {
      return this.images[key];
    }
    console.warn(`Image with key "${key}" not found in assetsImage.images`);
    return null;
  }

  /**
   * Get SVG content by key
   * Untuk SVG, kita bisa langsung return path atau fetch content
   * @param {string} key - Key untuk mengakses SVG
   * @returns {string|null} - Path atau content SVG
   */
  getSvgContent(key) {
    const imagePath = this.images[key];
    if (!imagePath) {
      console.warn(`SVG with key "${key}" not found in assetsImage.images`);
      return null;
    }
    
    return imagePath;
  }

  /**
   * Get all registered image keys
   * @returns {Array<string>} - Array of image keys
   */
  getAll() {
    return Object.keys(this.images);
  }

  /**
   * Get images by directory prefix
   * @param {string} directory - Directory prefix untuk filter
   * @returns {Object} - Object dengan filtered images
   */
  getByDirectory(directory) {
    return Object.entries(this.images)
      .filter(([key]) => key.startsWith(directory))
      .reduce((acc, [key, value]) => {
        acc[key] = value;
        return acc;
      }, {});
  }

  /**
   * Register navigation icon
   * @param {string} routeName - Nama route
   * @param {string} unfocusedPath - Path ke icon unfocused
   * @param {string} focusedPath - Path ke icon focused (optional)
   */
  registerNavigationIcon(routeName, unfocusedPath, focusedPath = null) {
    const key = routeName.toLowerCase();
    this.navigationIcons[key] = {
      unfocused: unfocusedPath,
      focused: focusedPath || unfocusedPath
    };
  }

  /**
   * Get navigation icon
   * @param {string} routeName - Nama route
   * @param {boolean} isFocused - Apakah icon dalam state focused
   * @returns {string|null} - Path ke icon
   */
  getNavigationIcon(routeName, isFocused = false) {
    const icon = this.navigationIcons[routeName.toLowerCase()];
    if (!icon) {
      console.warn(`Navigation icon for route "${routeName}" not found`);
      return null;
    }
    return isFocused ? icon.focused : icon.unfocused;
  }

  /**
   * Get tab bar icon configuration
   * @param {string} routeName - Nama route
   * @returns {Function} - Function yang return icon config
   */
  getTabBarIcon(routeName) {
    return ({ focused }) => {
      const source = this.getNavigationIcon(routeName, focused);
      return {
        source,
        style: {
          width: '24px',
          height: '24px',
          filter: focused ? 'none' : 'grayscale(100%)',
          opacity: focused ? 1 : 0.6
        }
      };
    };
  }

  /**
   * Load image from URL or path
   * @param {string} path - Path atau URL ke image
   * @returns {Promise<HTMLImageElement>} - Promise yang resolve ke image element
   */
  async loadImage(path) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = (error) => reject(error);
      img.src = path;
    });
  }

  /**
   * Preload multiple images
   * @param {Array<string>} paths - Array of image paths
   * @returns {Promise<Array<HTMLImageElement>>} - Promise yang resolve ke array of images
   */
  async preloadImages(paths) {
    const promises = paths.map(path => this.loadImage(path));
    return Promise.all(promises);
  }

  /**
   * Clear all registered images
   */
  clear() {
    this.images = {};
    this.navigationIcons = {};
  }
}

// Create singleton instance
const assetsImage = new AssetsImage();

// Initialize on module load
assetsImage.initialize();

// Export singleton instance
export default assetsImage;

// Export class untuk advanced usage
export { AssetsImage };
