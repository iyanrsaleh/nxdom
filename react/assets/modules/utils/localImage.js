class assetsImage {
  static images = {};
  static navigationIcons = {};

  static initialize() {
    try {
      // Dynamically load all images from assets directory
      const requireContext = require.context(
        "../../images", // directory to search
        true, // search subdirectories
        /\.(png|jpg|jpeg|gif|ico|svg|webp)$/i // file pattern
      );

      // Get all possible image paths
      const imagePaths = requireContext.keys();

      // Load each image into the images object
      imagePaths.forEach((path) => {
        // Convert path to a reasonable key name
        // Example: './icons/home.png' becomes 'iconsHome'
        const key = path
          .replace(/^\.\//, "") // Remove './'
          .replace(/\.[^/.]+$/, "") // Remove extension
          .split("/") // Split by directory
          .map((part, index) =>
            index === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)
          )
          .join("");

        const image = requireContext(path);
        this.images[key] = image;

        // Special handling for navigation icons
        if (path.includes("navigation/")) {
          const navKey = path
            .replace(/^\.\/navigation\//, "")
            .replace(/\.[^/.]+$/, "")
            .toLowerCase();
          this.navigationIcons[navKey] = {
            focused: path.includes("-focused.") ? image : null,
            unfocused: !path.includes("-focused.") ? image : null,
          };
        }
      });

      // Merge focused and unfocused icons
      Object.keys(this.navigationIcons).forEach((key) => {
        const baseKey = key.replace("-focused", "");
        if (key.includes("-focused")) {
          if (this.navigationIcons[baseKey]) {
            this.navigationIcons[baseKey].focused =
              this.navigationIcons[key].focused;
          }
          delete this.navigationIcons[key];
        }
      });

      return true;
    } catch (error) {
      console.error("Failed to initialize images:", error);
      return false;
    }
  }

  static get(key) {
    if (this.images.hasOwnProperty(key)) {
      return this.images[key];
    }
    console.warn(`Image with key "${key}" not found in assetsImage.images`);
    return null;
  }

  static getSvgContent(key) {
    // Untuk SVG, kita perlu membaca file sebagai string
    // Ini akan digunakan dengan SvgXml
    const imagePath = this.images[key];
    if (!imagePath) {
      console.warn(`SVG with key "${key}" not found in assetsImage.images`);
      return null;
    }
    
    // Jika ini adalah SVG file, kita perlu membaca kontennya
    // Untuk sekarang, return path dan biarkan komponen yang handle
    return imagePath;
  }

  static getAll() {
    return Object.keys(this.images);
  }

  static getByDirectory(directory) {
    return Object.entries(this.images)
      .filter(([key]) => key.startsWith(directory))
      .reduce((acc, [key, value]) => {
        acc[key] = value;
        return acc;
      }, {});
  }

  static getNavigationIcon(routeName, isFocused = false) {
    const icon = this.navigationIcons[routeName.toLowerCase()];
    if (!icon) {
      console.warn(`Navigation icon for route "${routeName}" not found`);
      return null;
    }
    return isFocused ? icon.focused || icon.unfocused : icon.unfocused;
  }

  static getTabBarIcon(routeName) {
    return ({ focused }) => ({
      source: this.getNavigationIcon(routeName, focused),
      style: {
        width: 24,
        height: 24,
        tintColor: focused ? "#0066cc" : "#8e8e93",
      },
    });
  }
}

// Initialize images when the module is imported
assetsImage.initialize();

export default assetsImage;
