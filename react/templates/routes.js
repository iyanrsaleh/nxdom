// Import screens
import HomeScreen from "./Home";
import halaman from "./halaman";

/**
 * Helper function untuk menentukan statusBar style berdasarkan warna
 * @param {string} backgroundColor - Warna background
 * @returns {string} "light" atau "dark"
 */
const getStatusBarStyle = (backgroundColor) => {
  // Warna gelap/berwarna menggunakan light content (teks putih)
  const darkColors = [
    "#2196F3", "#4CAF50", "#FF9800", "#F44336", "#9C27B0", 
    "#00BCD4", "#24BCA9", "#24bca9", "#607D8B", "#3F51B5",
    "#009688", "#FF5722", "#795548", "#673AB7", "#6A1B9A"
  ];
  
  // Jika warna termasuk dark, gunakan light content
  if (darkColors.some(color => backgroundColor.toLowerCase().includes(color.toLowerCase()))) {
    return "light";
  }
  
  // Default untuk warna terang
  return "dark";
};

/**
 * Helper function untuk mengupdate routes dengan assetColor
 * @param {Array} routesArray - Array routes yang akan di-update
 * @param {Object} colors - Object berisi warna dari assetColor
 * @param {string} statusBarStyle - Style untuk statusBar (dari properti atau dihitung)
 * @returns {Array} Array routes yang sudah di-update
 */
const updateRoutesWithColor = (routesArray, colors, statusBarStyle) => {
  // statusBarStyle sudah di-pass sebagai parameter
  
  return routesArray.map(route => {
    const updatedRoute = { ...route };
    
    // Update options jika ada
    if (updatedRoute.options) {
      // Jika options adalah function, wrap dengan function baru
      if (typeof updatedRoute.options === 'function') {
        const originalOptions = updatedRoute.options; // Simpan function asli
        updatedRoute.options = (props) => {
          const baseOptions = originalOptions(props); // Panggil function asli
          return updateOptionsWithColor(baseOptions, colors, statusBarStyle);
        };
      } else {
        // Jika options adalah object, update langsung
        updatedRoute.options = updateOptionsWithColor(updatedRoute.options, colors, statusBarStyle);
      }
    }
    
    return updatedRoute;
  });
};

/**
 * Helper function untuk mengupdate options dengan warna
 * @param {Object} options - Options object
 * @param {Object} colors - Object berisi warna dari assetColor
 * @param {string} statusBarStyle - Style untuk statusBar
 * @returns {Object} Options yang sudah di-update
 */
const updateOptionsWithColor = (options, colors, statusBarStyle) => {
  const updatedOptions = { ...options };
  
  // Update headerStyle jika ada
  if (updatedOptions.headerStyle) {
    updatedOptions.headerStyle = {
      ...updatedOptions.headerStyle,
      backgroundColor: colors.backgroundColor || colors.buttonColor || updatedOptions.headerStyle.backgroundColor
    };
  }
  
  // Update headerTintColor jika ada
  if (updatedOptions.headerTintColor) {
    updatedOptions.headerTintColor = colors.buttonTextColor || updatedOptions.headerTintColor;
  }
  
  // Update statusBar jika ada
  if (updatedOptions.statusBar) {
    updatedOptions.statusBar = {
      ...updatedOptions.statusBar,
      style: statusBarStyle,
      backgroundColor: colors.backgroundColor || colors.buttonColor || updatedOptions.statusBar.backgroundColor
    };
  }
  
  return updatedOptions;
};

/**
 * Konfigurasi Routes dan Navigation
 * --------------------------------
 *
 * Setiap route memiliki struktur berikut:
 * {
 *   name: string,           // Nama unik untuk route
 *   component: Component,   // Komponen React yang akan di-render
 *   options: {             // Opsi konfigurasi untuk route
 *     title: string,       // Judul yang ditampilkan di header
 *     headerShown: boolean,// Menampilkan/menyembunyikan header
 *     statusBar: {         // Konfigurasi StatusBar
 *       style: "light" | "dark",    // Warna teks StatusBar
 *       backgroundColor: string,     // Warna latar StatusBar
 *     }
 *   }
 * }
 *
 * Panduan Penggunaan StatusBar:
 * 1. Background Terang (mis: putih, abu-abu muda)
 *    - Gunakan style: "dark" untuk teks hitam
 *    Contoh:
 *    statusBar: {
 *      style: "dark",
 *      backgroundColor: "#FFFFFF"
 *    }
 *
 * 2. Background Gelap/Berwarna
 *    - Gunakan style: "light" untuk teks putih
 *    Contoh:
 *    statusBar: {
 *      style: "light",
 *      backgroundColor: "#24bca9"
 *    }
 *
 * Catatan Penting:
 * - headerShown: false akan menyembunyikan header navigasi
 * - Setiap route harus memiliki name yang unik
 * - Konfigurasi StatusBar akan berubah otomatis saat navigasi
 *
 * Warna Umum yang Digunakan di Android (Material Design):
 * --------------------------------------------------------
 * Primary Colors (Warna Utama):
 * - #2196F3 (Blue)        - Warna biru standar Material Design | statusBar: "light"
 * - #4CAF50 (Green)        - Warna hijau untuk success/positive actions | statusBar: "light"
 * - #FF9800 (Orange)       - Warna oranye untuk warning/attention | statusBar: "light"
 * - #F44336 (Red)          - Warna merah untuk error/danger | statusBar: "light"
 * - #9C27B0 (Purple)       - Warna ungu untuk premium features | statusBar: "light"
 * - #00BCD4 (Cyan)         - Warna cyan untuk secondary actions | statusBar: "light"
 * - #24BCA9 (Teal)         - Warna teal yang populer (contoh: line 72) | statusBar: "light"
 * - #607D8B (Blue Grey)    - Warna abu-abu biru untuk neutral | statusBar: "light"
 *
 * Neutral Colors (Warna Netral):
 * - #FFFFFF (White)        - Background terang (contoh: line 82) | statusBar: "dark"
 * - #F5F5F5 (Light Grey)   - Background abu-abu muda | statusBar: "dark"
 * - #9E9E9E (Grey)         - Teks sekunder | statusBar: "light"
 * - #424242 (Dark Grey)    - Teks utama pada background terang | statusBar: "light"
 * - #000000 (Black)        - Teks utama | statusBar: "light"
 * - #fff / #FFFFFF         - Teks putih untuk kontras (contoh: line 74) | statusBar: "dark"
 *
 * Accent Colors (Warna Aksen):
 * - #FFC107 (Amber)        - Warna kuning untuk highlight dan peringatan | statusBar: "dark"
 * - #E91E63 (Pink)         - Warna pink untuk special features dan romantis | statusBar: "light"
 * - #3F51B5 (Indigo)       - Warna indigo untuk professional apps | statusBar: "light"
 * - #009688 (Teal)         - Warna teal alternatif untuk modern UI | statusBar: "light"
 * - #FF5722 (Deep Orange)  - Warna oranye gelap untuk aksi penting | statusBar: "light"
 * - #795548 (Brown)        - Warna coklat untuk natural/organic theme | statusBar: "light"
 * - #CDDC39 (Lime)         - Warna lime untuk fresh dan energik | statusBar: "dark"
 * - #FFEB3B (Yellow)       - Warna kuning terang untuk perhatian | statusBar: "dark"
 * - #8BC34A (Light Green)  - Warna hijau muda untuk success ringan | statusBar: "dark"
 * - #03A9F4 (Light Blue)   - Warna biru muda untuk informasi | statusBar: "dark"
 * - #673AB7 (Deep Purple)  - Warna ungu gelap untuk premium | statusBar: "light"
 * - #FF4081 (Pink Accent)  - Warna pink accent untuk call-to-action | statusBar: "dark"
 * - #00E676 (Green Accent) - Warna hijau accent untuk success | statusBar: "dark"
 * - #18FFFF (Cyan Accent)  - Warna cyan accent untuk modern | statusBar: "dark"
 * - #FF6E40 (Deep Orange Accent) - Warna oranye accent untuk urgent | statusBar: "light"
 * - #651FFF (Deep Indigo)  - Warna indigo gelap untuk professional | statusBar: "light"
 * - #1DE9B6 (Teal Accent)  - Warna teal accent untuk fresh | statusBar: "dark"
 * - #FFD740 (Amber Accent) - Warna amber accent untuk highlight | statusBar: "dark"
 *
 * Catatan Penggunaan:
 * - Gunakan warna yang kontras untuk teks agar mudah dibaca
 * - Untuk background gelap (seperti #24BCA9), gunakan teks putih (#fff)
 * - Untuk background terang (#FFFFFF), gunakan teks hitam (#000000)
 * - StatusBar style "light" = teks putih, "dark" = teks hitam
 */

/**
 * Fungsi untuk mendapatkan routes dengan warna dinamis dari assetColor
 * @param {Object} assetColor - Object berisi warna dari properti
 * @param {Object} propertiData - Object berisi data properti lengkap (termasuk statusBar)
 * @returns {Array} Array routes dengan warna yang sudah di-update
 */
export const getRoutesWithColor = (assetColor = {}, propertiData = {}) => {
  const defaultColor = {
    backgroundColor: "#24BCA9",
    buttonColor: "#24BCA9",
    buttonTextColor: "#FFFFFF",
    color: "#009688"
  };
  
  const colors = { ...defaultColor, ...assetColor };
  // Gunakan statusBar dari properti jika ada, jika tidak hitung dari backgroundColor
  const statusBarFromProperti = propertiData?.statusBar;
  // Pastikan statusBar adalah string "light" atau "dark"
  const statusBarStyle = (statusBarFromProperti && typeof statusBarFromProperti === 'string') 
    ? statusBarFromProperti.toLowerCase() 
    : getStatusBarStyle(colors.backgroundColor);
  
  // Log removed for cleaner console output
  
  return [
  {
    name: "Home",
    component: HomeScreen,
    options: {
      title: "NexaJS",
      headerShown: false,
      statusBar: {
        style: "dark",
          backgroundColor: "#FFFFFF", // Tetap putih untuk Home
      },
    },
  },

  {
    name: "halaman",
    component: halaman,
    options: {
      title: "Halaman",
      headerShown: true,
      statusBar: {
        style: "dark",
        backgroundColor: "#FFFFFF",
      },
    },
  },
];
};

// Define routes configuration (default, akan di-override dengan warna dinamis)
export const routes = getRoutesWithColor();

/**
 * Helper function untuk mendapatkan semua routes
 * @param {Object} assetColor - Object berisi warna dari properti (optional)
 * @param {Object} propertiData - Object berisi data properti lengkap (optional)
 * @returns {Array} Array berisi semua konfigurasi route
 */
export const getRoutes = (assetColor, propertiData) => {
  if (assetColor || propertiData) {
    return getRoutesWithColor(assetColor, propertiData);
  }
  return routes;
};

/**
 * Default Style untuk Header dan StatusBar
 * Digunakan sebagai fallback ketika route tidak memiliki konfigurasi khusus
 *
 * Properti yang tersedia:
 * - headerStyle: Gaya untuk container header
 * - headerTintColor: Warna untuk teks dan icon di header
 * - headerTitleStyle: Gaya untuk teks judul header
 * - headerShown: Menampilkan/menyembunyikan header
 */
/**
 * Fungsi untuk mendapatkan default header style dengan warna dinamis
 * @param {Object} assetColor - Object berisi warna dari properti
 * @returns {Object} Default header style dengan warna yang sudah di-update
 */
export const getDefaultHeaderStyle = (assetColor = {}) => {
  const defaultColor = {
    backgroundColor: "#FFFFFF",
    buttonTextColor: "#000000"
  };
  
  const colors = { ...defaultColor, ...assetColor };
  
  return {
    headerStyle: {
      backgroundColor: colors.backgroundColor || "#FFFFFF",
  },
    headerTintColor: colors.buttonTextColor || "#000000",
  headerTitleStyle: {
    fontSize: 18,
    fontWeight: "600",
  },
  headerShown: true,
};
};

// Legacy export untuk kompatibilitas
const defaultHeaderStyle = getDefaultHeaderStyle();
