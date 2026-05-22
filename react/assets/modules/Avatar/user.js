import React from "react";
import { StyleSheet, View, Image, TouchableOpacity } from "react-native";
import FeatherIcon from "react-native-vector-icons/Feather";

const Avatar = ({
  size = 56,
  source,
  icon,
  iconColor = "#8E8D91",
  onIconPress,
  style,
  iconSize = 12,
  iconPadding = 4,
  iconBottom = -1,
  iconRight = -1,
  borderWidth = 2,
  borderColor = "#fff",
}) => {
  return (
    <View style={[styles.avatar, style]}>
      <Image
        alt=""
        source={typeof source === "string" ? { uri: source } : source}
        style={[
          styles.avatarImg,
          {
            width: size,
            height: size,
            borderRadius: size / 2,
          },
        ]}
      />
      {icon && (
        <TouchableOpacity
          style={[
            styles.avatarIcon,
            {
              backgroundColor: iconColor,
              padding: iconPadding,
              bottom: iconBottom,
              right: iconRight,
              borderWidth: borderWidth,
              borderColor: borderColor,
            },
          ]}
          onPress={onIconPress}
        >
          <FeatherIcon color="#fff" name={icon} size={iconSize} />
        </TouchableOpacity>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  avatar: {
    position: "relative",
  },
  avatarImg: {
    width: "100%",
    height: "100%",
  },
  avatarIcon: {
    alignItems: "center",
    justifyContent: "center",
    position: "absolute",
    borderRadius: 9999,
  },
});

export default Avatar;
