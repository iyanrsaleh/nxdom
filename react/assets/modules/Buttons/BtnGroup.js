import React from 'react';
import {
  StyleSheet,
  SafeAreaView,
  View,
  TouchableOpacity,
  Text,
} from 'react-native';
import FeatherIcon from 'react-native-vector-icons/Feather';
export default function isBtnGroup({ buttons }) {
  return (
    <View style={styles.contentActions}>
      {buttons.map((button, index) => (
        <TouchableOpacity
          key={index}
          onPress={button.onPress}
          style={styles.actionButton}>
          <View style={[
            styles.btnBase,
            button.primary ? styles.btnPrimary : styles.btnSecondary,
            { 
              borderColor: button.color || '#266EF1',
              backgroundColor: button.primary ? (button.color || '#266EF1') : 'transparent'
            }
          ]}>
            {button.icon && (
              <FeatherIcon
                name={button.icon}
                size={15}
                color={button.primary ? '#fff' : button.color}
                style={styles.btnIcon}
              />
            )}
            <Text style={[
              styles.btnTextBase,
              button.primary ? styles.btnPrimaryText : styles.btnSecondaryText,
              { color: button.primary ? '#fff' : (button.color || '#266EF1') }
            ]}>{button.text}
            </Text>

          </View>
        </TouchableOpacity>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({

  contentActions: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 18,
    marginBottom: 0,
  },
  actionButton: {
    flex: 1,
    paddingHorizontal:2,
  },
  btnBase: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderWidth: 2,
  },
  btnTextBase: {
    fontSize: 14,
    lineHeight: 20,
    fontWeight: '700',
  },
  btnPrimary: {
    backgroundColor: '#266EF1',
  },
  btnPrimaryText: {
    color: '#fff',
  },
  btnSecondary: {
    backgroundColor: 'transparent',
  },
  btnSecondaryText: {
    color: '#266EF1',
  },
  btnIcon: {
    marginRight: 8,
  },
});
