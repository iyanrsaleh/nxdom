import React from 'react';
import {
  View,
  ActivityIndicator,
  StyleSheet,
  Text,
  Modal,
} from 'react-native';
import Colors from '../utils/Color';

/**
 * Spinner Component
 * 
 * @param {boolean} visible - Menampilkan/menyembunyikan spinner
 * @param {string} size - Ukuran spinner: 'small', 'large', atau number
 * @param {string} color - Warna spinner (default: primary500)
 * @param {string} text - Teks yang ditampilkan di bawah spinner
 * @param {boolean} overlay - Menampilkan overlay background (default: false)
 * @param {string} overlayColor - Warna overlay background (default: rgba dengan opacity)
 * @param {string} textColor - Warna teks (default: text.primary)
 * @param {object} style - Custom style untuk container
 * @param {object} textStyle - Custom style untuk teks
 */
const Spinner = ({
  visible = false,
  size = 'large',
  color = Colors.primary500,
  text = '',
  overlay = false,
  overlayColor = 'rgba(0, 0, 0, 0.5)',
  textColor = Colors.text.primary,
  style,
  textStyle,
}) => {
  if (!visible) return null;

  // Determine size value
  const getSize = () => {
    if (typeof size === 'number') return size;
    return size === 'small' ? 'small' : 'large';
  };

  const spinnerContent = (
    <View style={[styles.container, style]}>
      <ActivityIndicator size={getSize()} color={color} />
      {text ? (
        <Text style={[styles.text, { color: textColor }, textStyle]}>
          {text}
        </Text>
      ) : null}
    </View>
  );

  // Jika overlay aktif, wrap dengan Modal
  if (overlay) {
    return (
      <Modal
        transparent
        visible={visible}
        animationType="fade"
        statusBarTranslucent
      >
        <View style={[styles.overlay, { backgroundColor: overlayColor }]}>
          {spinnerContent}
        </View>
      </Modal>
    );
  }

  return spinnerContent;
};

const styles = StyleSheet.create({
  container: {
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  overlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  text: {
    marginTop: 12,
    fontSize: 14,
    textAlign: 'center',
  },
});

export default Spinner;

