import React from "react";
import { View, StyleSheet } from "react-native";
import QRCode from "react-native-qrcode-svg";

class QRCodeGenerator extends React.Component {
  render() {
    const {
      value = "",
      size = 200,
      color = "black",
      backgroundColor = "white",
      style,
    } = this.props;

    return (
      <View style={[styles.container, style]}>
        <QRCode
          value={value}
          size={size}
          color={color}
          backgroundColor={backgroundColor}
        />
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: {
    alignItems: "center",
    justifyContent: "center",
  },
});

export default QRCodeGenerator;
