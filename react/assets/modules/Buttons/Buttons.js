import React from "react";
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  TouchableOpacity,
} from "react-native";
import MIcon from "../Icon";
import Icon from "react-native-vector-icons/MaterialCommunityIcons";
import { Ionicons } from "@expo/vector-icons";
import FeatherIcon from "react-native-vector-icons/Feather";
import { useNavigation } from "@react-navigation/native";
import Loader from "./Loader";
const Buttons = ({
  label,
  iconName,
  border,
  txColor,
  vertical,
  horizontal,
  password,
  background,
  fontSize = 15,
  icon,
  borderColor,
  grid,
  navigasi = "",
  Feather,
  Material,
  Ion,
  index = 0,
  active = false,
  loading = "",
  color = "#000",
  onPress = "",
  padding = 10,
  ...props
}) => {
  const navigation = useNavigation();
  if (background) {
    var TxColor = color;
    var bg = background;
  } else {
    var TxColor = "#000";
    var bg = borderColor;
  }
  const [hidePassword, setHidePassword] = React.useState(password);
  const [isFocused, setIsFocused] = React.useState(false);

  var setFontSize = fontSize;

  // Set padding values
  const paddingV = vertical || padding;
  const paddingH = horizontal || padding;

  const renderMenu = (value) => {
    if (value == "FeatherIcon") {
      return <FeatherIcon name={iconName} size={setFontSize} />;
    } else if (value == "Ionicons") {
      return <Ionicons name={iconName} size={setFontSize} />;
    } else {
      return (
        <Icon
          name={iconName}
          style={{
            color: txColor,
            fontSize: setFontSize,
            marginRight: 10,
            marginTop: 7,
          }}
        />
      );
    }
  };

  if (grid) {
    if (navigasi) {
      return (
        <View style={{ flex: 1, paddingHorizontal: grid, marginBottom: 5 }}>
          <TouchableOpacity
            onPress={() => navigation.navigate(navigasi.name, navigasi.value)}
          >
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "center",
                borderRadius: border,
                paddingVertical: paddingV,
                paddingHorizontal: paddingH,
                borderWidth: 2,
                backgroundColor: background,
                borderColor: bg,
              }}
            >
              <Text
                style={{
                  fontSize: fontSize,
                  lineHeight: 26,
                  fontWeight: "600",
                  color: txColor,
                }}
              >
                {icon && renderMenu(icon)}
                {Material && (
                  <MIcon
                    Material={Material}
                    size={setFontSize}
                    color={txColor}
                  />
                )}
                {Feather && (
                  <MIcon Feather={Feather} size={setFontSize} color={txColor} />
                )}
                {Ion && <MIcon Ion={Ion} size={setFontSize} color={txColor} />}
                {label}
              </Text>
            </View>
          </TouchableOpacity>
        </View>
      );
    } else {
      if (onPress) {
        return (
          <View style={{ flex: 1, paddingHorizontal: grid, marginBottom: 5 }}>
            <TouchableOpacity onPress={onPress}>
              <View
                style={{
                  flexDirection: "column",
                  alignItems: "center",
                  justifyContent: "center",
                  borderRadius: border,
                  paddingVertical: paddingV,
                  paddingHorizontal: paddingH,
                  borderWidth: 2,
                  backgroundColor: background,
                  borderColor: bg,
                }}
              >
                <Text
                  style={{
                    fontSize: fontSize,
                    lineHeight: 26,
                    fontWeight: "900",
                    color: txColor,
                    textAlign: "center",
                  }}
                >
                  {icon && renderMenu(icon)}
                  {Material && (
                    <MIcon
                      Material={Material}
                      size={setFontSize}
                      color={txColor}
                    />
                  )}
                  {Feather && (
                    <MIcon
                      Feather={Feather}
                      size={setFontSize}
                      color={txColor}
                    />
                  )}
                  {Ion && (
                    <MIcon Ion={Ion} size={setFontSize} color={txColor} />
                  )}
                  {loading && <Loader visible={loading} color={txColor} />}
                </Text>
                {!loading && (
                  <Text
                    style={{
                      color: txColor,
                      fontSize: setFontSize,
                      marginTop: 5,
                    }}
                  >
                    {label}
                  </Text>
                )}
              </View>
            </TouchableOpacity>
          </View>
        );
      } else {
        return (
          <View style={{ flex: 1, paddingHorizontal: grid, marginBottom: 5 }}>
            <TouchableOpacity onPress={onPress}>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  justifyContent: "center",
                  borderRadius: border,
                  paddingVertical: paddingV,
                  paddingHorizontal: paddingH,
                  borderWidth: 2,
                  backgroundColor: background,
                  borderColor: bg,
                }}
              >
                <Text
                  style={{
                    fontSize: fontSize,
                    lineHeight: 26,
                    fontWeight: "900",
                    color: txColor,
                  }}
                >
                  {icon && renderMenu(icon)}
                  {Material && (
                    <Text>
                      <MIcon
                        Material={Material}
                        size={setFontSize}
                        color={txColor}
                      />
                      {label && <Text> </Text>}
                    </Text>
                  )}
                  {Feather && (
                    <Text>
                      <MIcon
                        Feather={Feather}
                        size={setFontSize}
                        color={txColor}
                      />
                      {label && <Text> </Text>}
                    </Text>
                  )}
                  {Ion && (
                    <Text>
                      <MIcon Ion={Ion} size={setFontSize} color={txColor} />
                      {label && <Text> </Text>}
                    </Text>
                  )}
                  {label}
                </Text>
              </View>
            </TouchableOpacity>
          </View>
        );
      }
    }
  } else {
    if (navigasi) {
      return (
        <View style={{ marginBottom: 5 }}>
          <TouchableOpacity
            onPress={() => navigation.navigate(navigasi.name, navigasi.value)}
          >
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "center",
                borderRadius: border,
                paddingVertical: paddingV,
                paddingHorizontal: paddingH,
                borderWidth: 2,
                backgroundColor: background,
                borderColor: bg,
              }}
            >
              <Text
                style={{
                  fontSize: fontSize,
                  lineHeight: 26,
                  fontWeight: "900",
                  color: txColor,
                }}
              >
                {icon && renderMenu(icon)}
                {Material && (
                  <Text>
                    <MIcon
                      Material={Material}
                      size={setFontSize}
                      color={txColor}
                    />
                    {label && <Text> </Text>}
                  </Text>
                )}
                {Feather && (
                  <Text>
                    <MIcon
                      Feather={Feather}
                      size={setFontSize}
                      color={txColor}
                    />
                    {label && <Text> </Text>}
                  </Text>
                )}
                {Ion && (
                  <Text>
                    <MIcon Ion={Ion} size={setFontSize} color={txColor} />
                    {label && <Text> </Text>}
                  </Text>
                )}
                {label}
              </Text>
            </View>
          </TouchableOpacity>
        </View>
      );
    } else {
      return (
        <View index={index} style={{ marginBottom: 1, marginTop: 5 }}>
          <TouchableOpacity onPress={onPress}>
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "center",
                borderRadius: border,
                paddingVertical: paddingV,
                paddingHorizontal: paddingH,
                borderWidth: 2,
                backgroundColor: background,
                borderColor: bg,
              }}
            >
              <Text
                style={{
                  fontSize: fontSize,
                  lineHeight: 26,
                  fontWeight: "900",
                  color: txColor,
                }}
              >
                {index !== active && (
                  <Text>
                    {icon && renderMenu(icon)}
                    {Material && (
                      <Text>
                        <MIcon
                          Material={Material}
                          size={setFontSize}
                          color={txColor}
                        />
                        {label && <Text> </Text>}
                      </Text>
                    )}
                    {Feather && (
                      <Text>
                        <MIcon
                          Feather={Feather}
                          size={setFontSize}
                          color={txColor}
                        />
                        {label && <Text> </Text>}
                      </Text>
                    )}
                    {Ion && (
                      <Text>
                        <MIcon Ion={Ion} size={setFontSize} color={txColor} />
                        {label && <Text> </Text>}
                      </Text>
                    )}
                  </Text>
                )}
                {loading && (
                  <View
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <Loader visible={loading} color={txColor} />
                    <Text
                      style={{
                        marginLeft: 3,
                        color: txColor,
                        fontSize: setFontSize,
                      }}
                    >
                      {label}
                    </Text>
                  </View>
                )}
                {!loading && (
                  <Text style={{ color: txColor, fontSize: setFontSize }}>
                    {label}
                  </Text>
                )}
              </Text>
            </View>
          </TouchableOpacity>
        </View>
      );
    }
  }
};
const style = StyleSheet.create({
  btn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 30,
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderWidth: 1,
  },
  label: {
    marginVertical: 5,
    fontSize: 14,
    fontWeight: "600",
    color: "#222",
    marginBottom: 1,
    paddingLeft: 2,
  },
});

export default Buttons;
