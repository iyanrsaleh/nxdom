import React from "react";
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  TouchableOpacity,
  Platform,
} from "react-native";
import MIcon from "../Icon";
import Icon from "react-native-vector-icons/MaterialCommunityIcons";
import { FontFamily } from "../Fonts/Montserrat";

const Input = ({
  label,
  iconName,
  Feather,
  Ion,
  Material,
  errors,
  password,
  type,
  txColor = "#222",
  colorIcon = "#566476",
  backgroundColor = "#fff",
  background,
  onFocus = () => {},
  onBlur = () => {},
  onSelectDocument = () => {},
  selectedFile = null,
  value,
  onChangeText,
  ...props
}) => {
  const [hidePassword, setHidePassword] = React.useState(password);
  const [isFocused, setIsFocused] = React.useState(false);

  const commonInputStyle = {
    color: "#000",
    flex: 1,
    height: 46,
    justifyContent: "center",
    textAlignVertical: "center",
    fontFamily: FontFamily.regular,
  };

  const containerStyle = {
    height: 48,
    flexDirection: "row",
    paddingHorizontal: 13,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: isFocused ? "#24bca9" : "#C9D3DB",
    borderStyle: "solid",
    backgroundColor: backgroundColor,
    alignItems: "center",
    justifyContent: "center",
  };

  const formatDateInput = (text) => {
    const cleaned = text.replace(/[^0-9]/g, "");
    let formatted = cleaned;
    if (cleaned.length >= 4) {
      formatted = `${cleaned.slice(0, 2)}-${cleaned.slice(
        2,
        4
      )}-${cleaned.slice(4)}`;
    } else if (cleaned.length >= 2) {
      formatted = `${cleaned.slice(0, 2)}-${cleaned.slice(2)}`;
    }
    return formatted;
  };

  const handleInputFocus = () => {
    setIsFocused(true);
    onFocus();
  };

  const handleInputBlur = () => {
    setIsFocused(false);
    onBlur();
  };

  const handleInputChange = (text) => {
    if (type === "Date") {
      onChangeText(formatDateInput(text));
    } else {
      onChangeText(text);
    }
  };

  const renderIcon = () => (
    <View style={styles.iconContainer}>
      {iconName && <MIcon Material={iconName} size={22} color={colorIcon} />}
      {Material && <MIcon Material={Material} size={22} color={colorIcon} />}
      {Feather && <MIcon Feather={Feather} size={22} color={colorIcon} />}
      {Ion && (
        <MIcon
          Ion={Ion}
          size={22}
          color={colorIcon}
          style={{ marginLeft: 5 }}
        />
      )}
    </View>
  );

  const renderInput = () => {
    switch (type) {
      case "Date":
        return (
          <TextInput
            {...props}
            autoCorrect={false}
            placeholderTextColor={txColor}
            onFocus={handleInputFocus}
            onBlur={handleInputBlur}
            style={commonInputStyle}
            value={value}
            onChangeText={handleInputChange}
            placeholder="DD-MM-YYYY"
            keyboardType="numeric"
            maxLength={10}
          />
        );
      case "Password":
        return (
          <>
            <TextInput
              {...props}
              autoCorrect={false}
              placeholderTextColor={txColor}
              onFocus={handleInputFocus}
              onBlur={handleInputBlur}
              secureTextEntry={hidePassword}
              style={commonInputStyle}
              value={value}
              onChangeText={handleInputChange}
            />
            <View style={styles.passwordIcon}>
              <Icon
                onPress={() => setHidePassword(!hidePassword)}
                name={hidePassword ? "eye-off-outline" : "eye-outline"}
                style={{ color: "#566476", fontSize: 22 }}
              />
            </View>
          </>
        );
      case "Email":
        return (
          <TextInput
            {...props}
            autoCorrect={false}
            placeholderTextColor={txColor}
            onFocus={handleInputFocus}
            onBlur={handleInputBlur}
            style={commonInputStyle}
            value={value}
            onChangeText={handleInputChange}
            keyboardType="email-address"
            autoCapitalize="none"
          />
        );
      case "document":
        return (
          <TouchableOpacity
            style={styles.documentButton}
            onPress={onSelectDocument}
          >
            <Text
              style={[
                styles.documentText,
                { color: selectedFile ? "#000" : txColor },
              ]}
              numberOfLines={1}
              ellipsizeMode="middle"
            >
              {selectedFile
                ? selectedFile.name
                : props.placeholder || "Select document"}
            </Text>
            <Icon
              name="file-document-outline"
              style={{ color: colorIcon, fontSize: 22, marginLeft: 10 }}
            />
          </TouchableOpacity>
        );
      default:
        return (
          <TextInput
            {...props}
            autoCorrect={false}
            placeholderTextColor={txColor}
            onFocus={handleInputFocus}
            onBlur={handleInputBlur}
            style={commonInputStyle}
            value={value}
            onChangeText={handleInputChange}
          />
        );
    }
  };

  return (
    <View style={styles.wrapper}>
      {label && <Text style={[styles.label, { color: txColor }]}>{label}</Text>}
      <View style={containerStyle}>
        {renderIcon()}
        {renderInput()}
      </View>
      <Text style={styles.errorText}>{errors}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    marginBottom: -18,
  },
  label: {
    marginVertical: 0,
    fontSize: 14,
    fontFamily: FontFamily.semiBold,
    marginBottom: 0,
    paddingLeft: 2,
  },
  iconContainer: {
    marginRight: 5,
    justifyContent: "center",
    height: "100%",
  },
  passwordIcon: {
    height: "100%",
    justifyContent: "center",
  },
  documentButton: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  documentText: {
    fontFamily: FontFamily.regular,
    flex: 1,
  },
  errorText: {
    marginVertical: 0,
    fontSize: 10,
    fontFamily: FontFamily.light,
    paddingLeft: 2,
    color: "red",
    marginBottom: 2,
    fontWeight: 'normal',
  },
});

export default Input;
