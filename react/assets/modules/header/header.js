import React from "react";
import {
  View,
  Text,
  TouchableOpacity,
  Image,
  StyleSheet,
  Dimensions,
} from "react-native";
import Icon from "react-native-vector-icons/Feather";

const { width } = Dimensions.get("window");

const Header = ({
  title = "",
  onBack,
  showBack = true,
  rightComponent,
  containerStyle,
}) => {
  return (
    <View style={[styles.container, containerStyle]}>
      <View style={styles.leftSection}>
        {showBack && (
          <TouchableOpacity onPress={onBack} style={styles.backButton}>
            <Icon name="arrow-left" size={24} color="#000" />
          </TouchableOpacity>
        )}
      </View>

      <View style={styles.centerSection}>
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
      </View>

      <View style={styles.rightSection}>{rightComponent}</View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    height: 56,
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#fff",
    elevation: 4,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 2,
  },
  leftSection: {
    width: 56,
    justifyContent: "center",
    alignItems: "center",
  },
  centerSection: {
    flex: 1,
    justifyContent: "center",
  },
  rightSection: {
    width: 56,
    justifyContent: "center",
    alignItems: "center",
  },
  backButton: {
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: "600",
    color: "#000",
  },
});

export default Header;
