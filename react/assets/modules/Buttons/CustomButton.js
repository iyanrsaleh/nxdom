import React from 'react';
import { TouchableOpacity, Text, StyleSheet, View } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons'; // Anda bisa menggunakan set ikon lain jika diinginkan

const CustomButton = ({ 
  size = 'MD', 
  color = '#007AFF', 
  onPress, 
  title, 
  iconName, 
  fullWidth = false 
}) => {
  const buttonStyle = [
    styles[`btn${size}`],
    { backgroundColor: color },
    fullWidth && styles.fullWidth
  ];
  const textStyle = styles[`btn${size}Text`];
  const iconSize = size === 'XS' ? 16 : size === 'SM' ? 18 : size === 'MD' ? 20 : size === 'LG' ? 22 : 24;

  return (
    <TouchableOpacity onPress={onPress} style={buttonStyle}>
      <View style={styles.buttonContent}>
        {iconName && <Icon name={iconName} size={iconSize} color="#FFFFFF" style={styles.icon} />}
        <Text style={textStyle}>{title}</Text>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  btnXS: {
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 4,
  },
  btnXSText: {
    fontSize: 12,
    lineHeight: 16,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  btnSM: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 6,
  },
  btnSMText: {
    fontSize: 14,
    lineHeight: 18,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  btnMD: {
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  btnMDText: {
    fontSize: 16,
    lineHeight: 20,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  btnLG: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    backgroundColor: '#007AFF',
    borderRadius: 10,
  },
  btnLGText: {
    fontSize: 18,
    lineHeight: 22,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  btnXL: {
    paddingVertical: 12,
    paddingHorizontal: 24,
    backgroundColor: '#007AFF',
    borderRadius: 12,
  },
  btnXLText: {
    fontSize: 20,
    lineHeight: 24,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  buttonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  icon: {
    marginRight: 8,
  },
});

export default CustomButton;
