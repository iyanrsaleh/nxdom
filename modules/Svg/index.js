/**
 * Komponen Svg untuk Electron (Web-based)
 * Adaptasi dari React Native SVG ke Electron
 * 
 * @param {string} xml - Konten SVG dalam format XML string
 * @param {string} source - Key dari assetsImage untuk load SVG dari assets
 * @param {string} name - Nama SVG dari svgContent (misal: "forgot")
 * @param {number} width - Lebar SVG (default: 100)
 * @param {number} height - Tinggi SVG (default: 100)
 * @param {object} style - Style tambahan untuk container
 * @param {object} svgStyle - Style untuk SVG
 * @param {string} color - Warna untuk mengganti fill SVG (opsional, deprecated - gunakan fill)
 * @param {string} fill - Warna untuk mengganti fill SVG (opsional)
 * @param {object} props - Props tambahan untuk SVG element
 * 
 * @example
 * // Menggunakan XML langsung
 * const svg = Svg({ 
 *   xml: "<svg>...</svg>",
 *   width: 120,
 *   height: 120
 * });
 * document.body.appendChild(svg);
 * 
 * @example
 * // Menggunakan nama dari svgContent
 * const svg = Svg({ 
 *   name: "forgot",
 *   width: 120,
 *   height: 120
 * });
 * 
 * @example
 * // Menggunakan fill untuk mengganti warna
 * const svg = Svg({ 
 *   name: "forgot",
 *   width: 120,
 *   height: 120,
 *   fill: "#24BCA9"
 * });
 * 
 * @example
 * // Menggunakan assetsImage
 * const svg = Svg({ 
 *   source: "forgot",
 *   width: 120,
 *   height: 120
 * });
 */

import assetsImage from './localImage.js';
import svgContent from './svgContent.js';

/**
 * Fungsi untuk membuat elemen SVG dari XML string
 * @param {Object} options - Options untuk SVG
 * @returns {HTMLElement} - Container element dengan SVG
 */
export function Svg({ 
  xml, 
  source,
  name,
  width = 100, 
  height = 100, 
  style = {},
  svgStyle = {},
  color,
  fill,
  className = '',
  ...props 
} = {}) {
  let svgContentValue = xml;

  // Prioritas 1: Jika menggunakan name dari svgContent
  if (name && !svgContentValue) {
    if (svgContent[name]) {
      svgContentValue = svgContent[name];
    } else {
      console.warn(`SVG with name "${name}" not found in svgContent. Available: ${Object.keys(svgContent).join(', ')}`);
      return createErrorElement(`SVG "${name}" not found`);
    }
  }

  // Prioritas 2: Jika menggunakan source dari assetsImage
  if (source && !svgContentValue) {
    const asset = assetsImage.get(source);
    if (asset) {
      // Jika asset adalah string (SVG content), gunakan langsung
      if (typeof asset === 'string') {
        svgContentValue = asset;
      } else {
        console.warn(`SVG source "${source}" is not a string. Please provide xml prop instead.`);
        return createErrorElement(`SVG source "${source}" invalid`);
      }
    } else {
      console.warn(`SVG source "${source}" not found in assetsImage.`);
      return createErrorElement(`SVG source "${source}" not found`);
    }
  }

  // Jika tidak ada konten SVG
  if (!svgContentValue) {
    console.warn('Svg component requires either xml, name, or source prop.');
    return createErrorElement('No SVG content provided');
  }

  // Jika ada fill atau color prop, replace fill dengan warna tersebut
  // fill memiliki prioritas lebih tinggi dari color
  // Hanya mengganti fill="#17B8A6" (warna utama dari undraw.co) saja, bukan semua fill
  const fillColor = fill || color;
  if (fillColor && svgContentValue) {
    try {
      // Hanya replace fill="#17B8A6" (warna utama undraw.co yang bisa diubah) dengan warna baru
      // Gunakan regex yang lebih spesifik untuk memastikan hanya mengganti fill="#17B8A6"
      // dan tidak merusak struktur SVG atau mengganti warna lain
      
      // Method 1: Replace fill="#17B8A6" (double quotes) - warna utama undraw.co
      svgContentValue = svgContentValue.replace(/fill\s*=\s*"#17B8A6"/gi, `fill="${fillColor}"`);
      
      // Method 2: Replace fill='#17B8A6' (single quotes)
      svgContentValue = svgContentValue.replace(/fill\s*=\s*'#17B8A6'/gi, `fill='${fillColor}'`);
    } catch (error) {
      console.warn('Error replacing fill color:', error);
      // Jika ada error, gunakan SVG asli tanpa replace
    }
  }

  // Buat container element
  const container = document.createElement('div');
  container.className = `svg-container ${className}`.trim();
  
  // Apply container styles
  const containerStyles = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    ...style
  };
  
  Object.assign(container.style, containerStyles);

  // Parse SVG content dan inject ke container
  try {
    // Buat temporary div untuk parse SVG
    const temp = document.createElement('div');
    temp.innerHTML = svgContentValue.trim();
    
    // Ambil SVG element
    const svgElement = temp.querySelector('svg');
    
    if (!svgElement) {
      console.warn('Invalid SVG content: no <svg> element found');
      return createErrorElement('Invalid SVG content');
    }

    // Set width dan height
    svgElement.setAttribute('width', width);
    svgElement.setAttribute('height', height);
    
    // Apply SVG styles
    if (svgStyle && Object.keys(svgStyle).length > 0) {
      Object.assign(svgElement.style, svgStyle);
    }
    
    // Apply additional props to SVG element
    if (props && Object.keys(props).length > 0) {
      Object.entries(props).forEach(([key, value]) => {
        if (key.startsWith('data-') || key === 'id' || key === 'class') {
          svgElement.setAttribute(key, value);
        }
      });
    }
    
    // Append SVG ke container
    container.appendChild(svgElement);
    
  } catch (error) {
    console.error('Error parsing SVG:', error);
    return createErrorElement('Error parsing SVG');
  }

  return container;
}

/**
 * Fungsi helper untuk membuat error element
 */
function createErrorElement(message) {
  const errorDiv = document.createElement('div');
  errorDiv.className = 'svg-error';
  errorDiv.style.cssText = `
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fee;
    color: #c33;
    padding: 8px;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
  `;
  errorDiv.textContent = message;
  return errorDiv;
}

/**
 * Versi React-like untuk kompatibilitas dengan framework
 * Bisa digunakan dengan framework seperti React, Vue, dll
 */
export function SvgComponent(props) {
  return Svg(props);
}

// Export svgContent untuk kemudahan akses
export { default as svgContent } from './svgContent.js';
export { default as assetsImage } from './localImage.js';

// Default export
export default Svg;
