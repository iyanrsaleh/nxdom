import React from 'react';
import { Modal, TouchableOpacity, View, Text, StyleSheet, Dimensions, Image, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

const { width: screenWidth, height: screenHeight } = Dimensions.get('window');

const ImageViewerModal = ({ visible, images, initialIndex = 0, onClose }) => {
  if (!visible || !images || images.length === 0) {
    return null;
  }

  const currentImage = images[initialIndex] || images[0];
  const imageUrl = typeof currentImage === 'string' ? currentImage : currentImage.url || currentImage;

  return (
    <Modal
      visible={visible}
      transparent={true}
      animationType="fade"
      onRequestClose={onClose}
    >
      <View style={styles.container}>
        {/* Close Button */}
        <TouchableOpacity style={styles.closeButton} onPress={onClose}>
          <Ionicons name="close" size={30} color="#fff" />
        </TouchableOpacity>

        {/* Image Container */}
        <ScrollView
          contentContainerStyle={styles.scrollContainer}
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          bounces={false}
        >
          <Image
            source={{ uri: imageUrl }}
            style={styles.image}
            resizeMode="contain"
          />
        </ScrollView>

        {/* Footer with title and description if available */}
        {currentImage?.props && (currentImage.props.title || currentImage.props.description) && (
          <View style={styles.footer}>
            {currentImage.props.title ? (
              <Text style={styles.footerTitle}>{currentImage.props.title}</Text>
            ) : null}
            {currentImage.props.description ? (
              <Text style={styles.footerDescription}>{currentImage.props.description}</Text>
            ) : null}
          </View>
        )}
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.95)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  closeButton: {
    position: 'absolute',
    top: 40,
    right: 20,
    zIndex: 1000,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    borderRadius: 20,
    padding: 8,
  },
  scrollContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    width: screenWidth,
    height: screenHeight,
  },
  image: {
    width: screenWidth,
    height: screenHeight,
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    paddingVertical: 16,
    paddingHorizontal: 16,
    zIndex: 1000,
  },
  footerTitle: {
    color: '#fff',
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 8,
    textAlign: 'center',
  },
  footerDescription: {
    color: '#fff',
    fontSize: 14,
    textAlign: 'center',
  },
});

export default ImageViewerModal;
