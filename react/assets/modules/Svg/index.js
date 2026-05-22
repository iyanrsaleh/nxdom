import React from 'react';
import { SvgXml } from 'react-native-svg';
import { View, StyleSheet } from 'react-native';
import assetsImage from '../utils/localImage';
import svgContent from './svgContent';

/**
 * Komponen Svg untuk menampilkan SVG di React Native
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
 * @param {object} props - Props tambahan untuk SvgXml
 * 
 * @example
 * // Menggunakan XML langsung
 * <Svg 
 *   xml="<svg>...</svg>"
 *   width={120}
 *   height={120}
 * />
 * 
 * @example
 * // Menggunakan nama dari svgContent
 * <Svg 
 *   name="forgot"
 *   width={120}
 *   height={120}
 * />
 * 
 * @example
 * // Menggunakan fill untuk mengganti warna
 * <Svg 
 *   name="forgot"
 *   width={120}
 *   height={120}
 *   fill="#24BCA9"
 * />
 * 
 * @example
 * // Menggunakan assetsImage
 * <Svg 
 *   source="forgot"
 *   width={120}
 *   height={120}
 * />
 */
const Svg = ({ 
  xml, 
  source,
  name,
  width = 100, 
  height = 100, 
  style,
  svgStyle,
  color,
  fill,
  ...props 
}) => {
  let svgContentValue = xml;

  // Prioritas 1: Jika menggunakan name dari svgContent
  if (name && !svgContentValue) {
    if (svgContent[name]) {
      svgContentValue = svgContent[name];
    } else {
      console.warn(`SVG with name "${name}" not found in svgContent. Available: ${Object.keys(svgContent).join(', ')}`);
      return null;
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
        // Jika asset adalah require path, kita perlu load kontennya
        // Untuk sekarang, kita akan menggunakan xml langsung jika tersedia
        console.warn(`SVG source "${source}" is not a string. Please provide xml prop instead.`);
        return null;
      }
    } else {
      console.warn(`SVG source "${source}" not found in assetsImage.`);
      return null;
    }
  }

  // Jika tidak ada konten SVG
  if (!svgContentValue) {
    console.warn('Svg component requires either xml, name, or source prop.');
    return null;
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
      svgContentValue = svgContentValue.replace(/fill\s*=\s*"#17B8A6"/g, `fill="${fillColor}"`);
      
      // Method 2: Replace fill='#17B8A6' (single quotes)
      svgContentValue = svgContentValue.replace(/fill\s*=\s*'#17B8A6'/g, `fill='${fillColor}'`);
    } catch (error) {
      console.warn('Error replacing fill color:', error);
      // Jika ada error, gunakan SVG asli tanpa replace
    }
  }

  return (
    <View style={[styles.container, style]}>
      <SvgXml 
        xml={svgContentValue}
        width={width}
        height={height}
        style={svgStyle}
        {...props}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
  },
});

export default Svg;
export { Svg };
export { svgContent } from './svgContent';

