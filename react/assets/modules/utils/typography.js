import { Platform, StyleSheet } from "react-native";
import { FontFamily } from "../Fonts/Montserrat";

// Base text style that will be used as foundation
const baseTextStyle = {
  fontFamily: FontFamily.regular,
  color: "#000000",
};

// Create font styles with shorter names
export const fs = StyleSheet.create({
  base: baseTextStyle, // default text style
  xs: {
    ...baseTextStyle,
    fontSize: 12,
    lineHeight: 16,
  },
  sm: {
    ...baseTextStyle,
    fontSize: 14,
    lineHeight: 20,
  },
  md: {
    ...baseTextStyle,
    fontSize: 16,
    lineHeight: 24,
  },
  lg: {
    ...baseTextStyle,
    fontSize: 18,
    lineHeight: 28,
  },
  xl: {
    ...baseTextStyle,
    fontSize: 20,
    lineHeight: 28,
  },
  "2xl": {
    ...baseTextStyle,
    fontSize: 24,
    lineHeight: 32,
  },
  "3xl": {
    ...baseTextStyle,
    fontSize: 30,
    lineHeight: 36,
  },
  "4xl": {
    ...baseTextStyle,
    fontSize: 36,
    lineHeight: 40,
  },
  thin: {
    fontFamily: FontFamily.thin,
  },
  light: {
    fontFamily: FontFamily.light,
  },
  normal: {
    fontFamily: FontFamily.regular,
  },
  medium: {
    fontFamily: FontFamily.medium,
  },
  semibold: {
    fontFamily: FontFamily.semiBold,
  },
  bold: {
    fontFamily: FontFamily.bold,
  },
  extrabold: {
    fontFamily: FontFamily.extraBold,
  },
  black: {
    fontFamily: FontFamily.black,
  },
  center: {
    textAlign: "center",
  },
  left: {
    textAlign: "left",
  },
  right: {
    textAlign: "right",
  },
  justify: {
    textAlign: "justify",
  },
  italic: {
    fontStyle: "italic",
  },
  underline: {
    textDecorationLine: "underline",
  },
  "line-through": {
    textDecorationLine: "line-through",
  },
});
