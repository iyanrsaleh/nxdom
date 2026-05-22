import React from 'react';
import {
  StyleSheet,
  View,
  TouchableOpacity,
  Text,
  ScrollView,
  Dimensions,
} from 'react-native';
import FeatherIcon from 'react-native-vector-icons/Feather';

const { width: screenWidth } = Dimensions.get('window');

export const isBtnTabs = ({ tabs, activeIndex, onTabPress }) => {
  const minTabWidth = 100; // Lebar minimum untuk setiap tab
  const tabWidth = Math.max(screenWidth / tabs.length, minTabWidth);

  return (
    <ScrollView 
      horizontal 
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.scrollViewContent}
    >
      {tabs.map(({ name, icon }, index) => {
        const isActive = index === activeIndex;
        return (
          <TouchableOpacity
            key={name}
            onPress={() => onTabPress(index)}
            style={[
              styles.tab,
              { width: tabWidth },
              isActive && styles.activeTab,
            ]}>
            <View style={styles.iconContainer}>
              <FeatherIcon
                color={isActive ? '#6366f1' : '#6b7280'}
                name={icon}
                size={15} />
            </View>
            <Text
              style={[
                styles.tabText,
                isActive && styles.activeTabText,
              ]}>
              {name}
            </Text>
          </TouchableOpacity>
        );
      })}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  scrollViewContent: {
    flexGrow: 1,
    height:52
  },
  tab: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12, // Menambah padding vertikal
    paddingHorizontal:10,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
    height:45, // Menambah tinggi tetap untuk tab
  },
  activeTab: {
    borderBottomColor: '#6366f1',
  },
  iconContainer: {
    marginRight: 5,
  },
  tabText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
    marginBottom:1, // Menambah sedikit margin di bawah teks
  },
  activeTabText: {
    color: '#6366f1',
  },
});

export default isBtnTabs;
