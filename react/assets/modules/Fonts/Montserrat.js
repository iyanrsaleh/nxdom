import React, { useState } from "react";
import { View, StyleSheet } from "react-native";
import {
  useFonts,
  Montserrat_100Thin,
  Montserrat_200ExtraLight,
  Montserrat_300Light,
  Montserrat_400Regular,
  Montserrat_500Medium,
  Montserrat_600SemiBold,
  Montserrat_700Bold,
  Montserrat_800ExtraBold,
  Montserrat_900Black,
  Montserrat_100Thin_Italic,
  Montserrat_200ExtraLight_Italic,
  Montserrat_300Light_Italic,
  Montserrat_400Regular_Italic,
  Montserrat_500Medium_Italic,
  Montserrat_600SemiBold_Italic,
  Montserrat_700Bold_Italic,
  Montserrat_800ExtraBold_Italic,
  Montserrat_900Black_Italic,
} from "@expo-google-fonts/montserrat";

export const FontFamily = {
  thin: "Montserrat_100Thin",
  extraLight: "Montserrat_200ExtraLight",
  light: "Montserrat_300Light",
  regular: "Montserrat_400Regular",
  medium: "Montserrat_500Medium",
  semiBold: "Montserrat_600SemiBold",
  bold: "Montserrat_700Bold",
  extraBold: "Montserrat_800ExtraBold",
  black: "Montserrat_900Black",
  thinItalic: "Montserrat_100Thin_Italic",
  extraLightItalic: "Montserrat_200ExtraLight_Italic",
  lightItalic: "Montserrat_300Light_Italic",
  regularItalic: "Montserrat_400Regular_Italic",
  mediumItalic: "Montserrat_500Medium_Italic",
  semiBoldItalic: "Montserrat_600SemiBold_Italic",
  boldItalic: "Montserrat_700Bold_Italic",
  extraBoldItalic: "Montserrat_800ExtraBold_Italic",
  blackItalic: "Montserrat_900Black_Italic",
};

export const useMontserratFonts = () => {
  const [fontsLoaded] = useFonts({
    Montserrat_100Thin,
    Montserrat_200ExtraLight,
    Montserrat_300Light,
    Montserrat_400Regular,
    Montserrat_500Medium,
    Montserrat_600SemiBold,
    Montserrat_700Bold,
    Montserrat_800ExtraBold,
    Montserrat_900Black,
    Montserrat_100Thin_Italic,
    Montserrat_200ExtraLight_Italic,
    Montserrat_300Light_Italic,
    Montserrat_400Regular_Italic,
    Montserrat_500Medium_Italic,
    Montserrat_600SemiBold_Italic,
    Montserrat_700Bold_Italic,
    Montserrat_800ExtraBold_Italic,
    Montserrat_900Black_Italic,
  });

  return fontsLoaded;
};
