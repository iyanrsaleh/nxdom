// Basic React and React Native exports
import React, { useState, useEffect, useCallback, useMemo, useRef } from "react";
import { useNavigation, useFocusEffect } from "@react-navigation/native";
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Image,
  ScrollView,
  FlatList,
  Dimensions,
  Platform,
  ActivityIndicator,
  Modal,
  Alert,
  StatusBar,
  Animated,
  KeyboardAvoidingView,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import AsyncStorage from "@react-native-async-storage/async-storage";
import FeatherIcon from "react-native-vector-icons/Feather";
import FontAwesome from "react-native-vector-icons/FontAwesome5";
import { SvgXml } from "react-native-svg";
// Component imports
import Svg, { svgContent } from "./Svg";
import Input from "./Form/input";
import Switch from "./Form/Switch";
import RichTextEditor from "./Form/RichTextEditor";
import { FontFamily, useMontserratFonts } from "./Fonts/Montserrat";
import Colors from "./utils/Color";
import Icon, { SymbolsIcon } from "./Icon";
import Buttons from "./Buttons/Buttons";
import ButtonsAction from "./Buttons/Action";
import isBtnGroup from "./Buttons/BtnGroup";
import BtnTabs from "./Buttons/BtnTabs";
import CustomButton from "./Buttons/CustomButton";
import Loader from "./Buttons/Loader";
import Spinner from "./Spinner";
import Grid from "./utils/Grid";
import { fs } from "./utils/typography";
import Avatar from "./Avatar/user";
import useModal from "./Modal";
import Images from "./Avatar/Images";
import { IndexedDBManager } from "./Storage/IndexDB.js";
import { NexaDb } from "./Storage/NexaDb.js";
// Modul storage lainnya (belum dipindah ke ./Storage/) — sumber: StorageOld
import { NexaDBLite } from "./Storage/NexaDBLite.js";
import { NexaStores } from "./Storage/NexaStores.js";
import NexaModels from "./Storage/NexaModels.js";
import {
  NexaFirestore,
  firebaseData,
  nexaFirebase,
  NexaFirebase,
  initNexaFirebase,
  FirebaseConfig,
  clearNexaFirebaseCache,
  getNexaFirebaseCacheInfo,
} from "./Firebase/NexaFirebase";
import NEXA, { syncNexaFromConfig, NXUI, NX } from "./Nexa";
import Server from "../../config";
import QRCodeGenerator from "./utils/QRCode";
import { JsonView, formatJson } from "./utils/JsonView";
import SelectList from "./Form/SelectList";
import assetsImage from "./utils/localImage";
import Header from "./header/header";
import ExpoSpeech from "./utils/speech";
import ImgPicker from "./Avatar/pickImage";
import Carousel from "./Salid/carousel";
import NexaScanqr from "./Scanner/NexaScanqr";
import Toast, { ToastContainer, toastManager } from "./Toast";
import Properti, { properti } from "./Properti/Properti";

// HTML-like components import
import {
  Div,
  P,
  Span,
  Button as HtmlButton,
  H1,
  H2,
  H3,
  Section,
  Article,
  createHTMLElement,
  getNativeComponent,
} from "./utils/htmlToNative";

// Form validation imports
import {
  validateInput,
  useFormValidation,
  pickImage,
  pickCamera,
  pickVideo,
  recordVideo,
  pickMultipleImages,
  pickDocument,
  pickMultipleDocuments,
  validateVideo,
  convertToBase64,
  parseFileSize,
  formatFileSize,
} from "./Form/Validasi";

// Keep global NEXA in sync with root config on module load.
syncNexaFromConfig(Server);

// ============================================
// 🔄 AUTO-INITIALIZE NEXA DB
// ============================================
// Membuat instance NexaDb yang sudah siap digunakan
// User tidak perlu memanggil install() secara manual
const nexaDbInstance = new NexaDb();

let nexaDbReady = false;
let nexaDbInitPromise = null;

const initializeNexaDb = async () => {
  if (nexaDbReady) {
    return nexaDbInstance;
  }

  if (nexaDbInitPromise) {
    return nexaDbInitPromise;
  }

  nexaDbInitPromise = (async () => {
    try {
      await nexaDbInstance.install();
      nexaDbReady = true;
      return nexaDbInstance;
    } catch (error) {
      console.error("❌ Failed to initialize NexaDb:", error);
      nexaDbReady = false;
      nexaDbInitPromise = null;
      throw error;
    }
  })();

  return nexaDbInitPromise;
};

initializeNexaDb().catch((error) => {
  console.warn("⚠️ NexaDb auto-initialization failed, will retry on first use:", error);
});

// ============================================
// WRAPPER NEXADB - HANYA UNTUK INDEXEDDBMANAGER
// ============================================
// PERHATIAN: Wrapper ini HANYA untuk IndexedDBManager (web/IndexedDB)
// Wrapper untuk interface yang mudah digunakan
const nexaDb = {
  instance: nexaDbInstance,

  async ready() {
    if (!nexaDbReady) {
      await initializeNexaDb();
    }
    return IndexedDBManager;
  },

  async set(storeName, data) {
    await this.ready();
    return IndexedDBManager.setAuto(storeName, data);
  },

  async get(storeName, key) {
    await this.ready();
    return IndexedDBManager.getAuto(storeName, key);
  },

  async getAll(storeName) {
    await this.ready();
    return IndexedDBManager.getAllAuto(storeName);
  },

  async delete(storeName, key) {
    await this.ready();
    return IndexedDBManager.deleteAuto(storeName, key);
  },

  async updateFields(storeName, id, fieldUpdates) {
    await this.ready();
    return IndexedDBManager.updateFieldsAuto(storeName, id, fieldUpdates);
  },

  async updateField(storeName, id, fieldName, fieldValue) {
    await this.ready();
    return IndexedDBManager.updateFieldAuto(storeName, id, fieldName, fieldValue);
  },

  async search(storeName, query, fields = []) {
    await this.ready();
    return IndexedDBManager.searchAuto(storeName, query, fields);
  },

  async getLatest(storeName, count = 1) {
    await this.ready();
    return IndexedDBManager.getLatestAuto(storeName, count);
  },

  async getOldest(storeName, count = 1) {
    await this.ready();
    return IndexedDBManager.getOldestAuto(storeName, count);
  },

  listStores() {
    return IndexedDBManager.listStores();
  },

  hasStore(storeName) {
    return IndexedDBManager.hasStore(storeName);
  },

  getInfo() {
    return IndexedDBManager.getInfo();
  },

  get db() {
    return IndexedDBManager;
  },
};

// Export all components and utilities
export {
  // React basics
  React,
  useState,
  useEffect,
  useCallback,
  useMemo,
  useRef,
  useNavigation,
  useFocusEffect,
  FeatherIcon,
  FontAwesome,
  SvgXml,
  // React Native basics
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Image,
  ScrollView,
  FlatList,
  Dimensions,
  Platform,
  ActivityIndicator,
  Modal,
  Alert,
  SafeAreaView,
  StatusBar,
  Animated,
  KeyboardAvoidingView,

  // Components
  Svg,
  svgContent,
  Input,
  Switch,
  RichTextEditor,
  Header,
  SelectList,
  QRCodeGenerator,
  JsonView,
  formatJson,
  AsyncStorage,
  IndexedDBManager,
  NexaDb,
  nexaDb,
  NexaDBLite,
  NexaStores,
  NexaModels,
  NexaFirestore,
  firebaseData,
  nexaFirebase,
  NexaFirebase,
  initNexaFirebase,
  clearNexaFirebaseCache,
  getNexaFirebaseCacheInfo,
  NEXA,
  NXUI,
  NX,
  syncNexaFromConfig,
  Server,
  FirebaseConfig,
  FontFamily,
  useMontserratFonts,
  Colors,
  Icon,
  SymbolsIcon,
  Buttons,
  ButtonsAction,
  isBtnGroup,
  BtnTabs,
  CustomButton,
  Loader,
  Spinner,
  Grid,
  fs,
  Images,
  Avatar,
  useModal,
  assetsImage,
  ExpoSpeech,
  ImgPicker,
  Carousel,
  NexaScanqr,
  Toast,
  ToastContainer,
  toastManager,

  // HTML-like components
  Div,
  P,
  Span,
  HtmlButton,
  H1,
  H2,
  H3,
  Section,
  Article,
  createHTMLElement,
  getNativeComponent,

  // Form validation exports
  validateInput,
  useFormValidation,
  pickImage,
  pickCamera,
  pickVideo,
  recordVideo,
  pickMultipleImages,
  pickDocument,
  pickMultipleDocuments,
  validateVideo,
  convertToBase64,
  parseFileSize,
  formatFileSize,

  // Properti management
  Properti,
  properti,
};
