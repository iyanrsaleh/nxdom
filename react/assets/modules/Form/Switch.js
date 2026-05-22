import React from "react";
import { Switch as RNSwitch, StyleSheet, Platform } from "react-native";

const Switch = ({ value, onValueChange, disabled, color }) => {
  return (
    <RNSwitch
      value={value}
      onValueChange={onValueChange}
      disabled={disabled}
      trackColor={{
        false: "#E9E9EA",
        true: color || "#24bca9",
      }}
      thumbColor="#FFFFFF"
      ios_backgroundColor="#E9E9EA"
      style={[
        styles.switch,
        Platform.select({
          ios: styles.iosSwitch,
          android: styles.androidSwitch,
        }),
      ]}
    />
  );
};

const styles = StyleSheet.create({
  switch: {
    width: Platform.OS === "ios" ? 52 : 48,
    height: Platform.OS === "ios" ? 32 : 28,
  },
  iosSwitch: {
    marginRight: 5,
  },
  androidSwitch: {
    marginRight: 5,
  },
});

export default Switch;
