import React from "react";
import { Alert, Platform } from "react-native";
import * as ImagePicker from "expo-image-picker";
import * as DocumentPicker from "expo-document-picker";

export const validateInput = (
  value,
  rules = {},
  type = "text",
  name = "",
  placeholder = ""
) => {
  const rule = rules[name];

  // Handle empty values first
  if (!value && type !== "file" && type !== "document" && type !== "video") {
    return `${placeholder} tidak boleh kosong`;
  }

  // Check length rules if they exist
  if (rule?.length && Array.isArray(rule.length)) {
    const [minLength, maxLength] = rule.length;

    // Skip length validation for phone at initial input
    if (type === "tel" && (!value || value.length < minLength)) {
      return null;
    }

    if (value?.length < minLength && type !== "tel") {
      if (type === "file" || type === "document" || type === "video") {
        return `${placeholder} minimal ${minLength}MB`;
      } else {
        return `${placeholder} minimal ${minLength} karakter`;
      }
    }

    if (maxLength && value?.length > maxLength && type !== "tel") {
      return `${placeholder} tidak boleh lebih dari ${maxLength} karakter`;
    }
  }

  // Type-specific validation
  switch (type) {
    case "email":
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) {
        return `${placeholder} harus berupa email yang valid`;
      }
      break;

    case "tel":
      const cleanNumber = value.replace(/[^\d]/g, "");
      const isStartWith08 = /^08\d{8,11}$/.test(cleanNumber);
      const isStartWith62 = /^62\d{9,12}$/.test(cleanNumber);
      const isStartWithArea = /^[2-3]\d{8,11}$/.test(cleanNumber);

      // Only validate format if there's input
      if (
        cleanNumber.length > 0 &&
        !isStartWith08 &&
        !isStartWith62 &&
        !isStartWithArea
      ) {
        return `${placeholder} tidak valid. Gunakan format: 08xx, +62xx, 02x, atau 03x`;
      }

      // Validate length only if number is complete
      if (
        cleanNumber.length > 0 &&
        (cleanNumber.length < 8 || cleanNumber.length > 13)
      ) {
        return `${placeholder} harus antara 8-13 digit`;
      }
      break;

    case "file":
      if (!value && placeholder) {
        return `${placeholder} tidak boleh kosong`;
      }
      break;

    case "document":
      if (!value && placeholder) {
        return `${placeholder} tidak boleh kosong`;
      }
      if (rule?.allowedTypes || rule?.maxSize) {
        return validateDocument(value, rule.allowedTypes, rule.maxSize);
      }
      break;

    case "video":
      if (!value && placeholder) {
        return `${placeholder} tidak boleh kosong`;
      }
      if (rule?.maxDuration || rule?.maxSize) {
        return validateVideo(value, rule.maxDuration, rule.maxSize);
      }
      break;
  }

  return null;
};

// Helper function untuk parse ukuran file
export const parseFileSize = (size) => {
  const units = {
    B: 1,
    KB: 1024,
    MB: 1024 * 1024,
    GB: 1024 * 1024 * 1024,
  };

  const match = size.match(/^(\d+)\s*(B|KB|MB|GB)$/i);
  if (match) {
    const [, value, unit] = match;
    return parseInt(value) * units[unit.toUpperCase()];
  }
  return parseInt(size);
};

// Helper function untuk format ukuran file
export const formatFileSize = (bytes) => {
  const sizes = ["B", "KB", "MB", "GB"];
  if (bytes === 0) return "0 B";
  const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
  return Math.round(bytes / Math.pow(1024, i), 2) + " " + sizes[i];
};

// Function untuk handle file picker
export const pickImage = async (options = {}) => {
  try {
    // Request permission first
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Sorry, we need camera roll permissions to make this work!"
      );
      return null;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 1,
      base64: true,
      allowsMultipleSelection: false,
      ...options,
    });

    if (!result.canceled && result.assets?.[0]) {
      const asset = result.assets[0];
      return {
        uri: asset.uri,
        name: asset.uri.split("/").pop(),
        type: `image/${asset.uri.split(".").pop()}`,
        size: asset.fileSize || 0,
        base64: asset.base64,
      };
    }

    return null;
  } catch (error) {
    console.error("Error picking image:", error);
    Alert.alert("Error", "Failed to pick image");
    return null;
  }
};

// Helper function to get file info
const getFileInfo = async (uri) => {
  const filename = uri.split("/").pop();
  const match = /\.(\w+)$/.exec(filename);
  const type = match ? `image/${match[1]}` : "image";

  return {
    name: filename,
    type,
    size: 0, // Size information might not be available
  };
};

// Function untuk handle multiple file picker
export const pickMultipleImages = async (options = {}) => {
  try {
    // Request permission first
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Sorry, we need camera roll permissions to make this work!"
      );
      return [];
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 1,
      base64: true,
      allowsMultipleSelection: true,
      ...options,
    });

    if (!result.canceled && result.assets) {
      return result.assets.map((asset) => ({
        uri: asset.uri,
        name: asset.uri.split("/").pop(),
        type: `image/${asset.uri.split(".").pop()}`,
        size: asset.fileSize || 0,
        base64: asset.base64,
      }));
    }

    return [];
  } catch (error) {
    console.error("Error picking multiple images:", error);
    Alert.alert("Error", "Failed to pick images");
    return [];
  }
};

// Function untuk handle document picker
export const pickDocument = async (options = {}) => {
  try {
    const result = await DocumentPicker.getDocumentAsync({
      type: options.type || "*/*",
      multiple: false,
      copyToCacheDirectory: true,
      ...options,
    });

    if (result.assets && result.assets[0]) {
      const document = result.assets[0];
      return {
        uri: document.uri,
        name: document.name,
        type: document.mimeType,
        size: document.size,
      };
    }

    return null;
  } catch (error) {
    console.error("Error picking document:", error);
    Alert.alert("Error", "Failed to pick document");
    return null;
  }
};

// Function untuk handle multiple document picker
export const pickMultipleDocuments = async (options = {}) => {
  try {
    const result = await DocumentPicker.getDocumentAsync({
      type: options.type || "*/*",
      multiple: true,
      copyToCacheDirectory: true,
      ...options,
    });

    if (result.assets) {
      return result.assets.map((document) => ({
        uri: document.uri,
        name: document.name,
        type: document.mimeType,
        size: document.size,
      }));
    }

    return [];
  } catch (error) {
    console.error("Error picking documents:", error);
    Alert.alert("Error", "Failed to pick documents");
    return [];
  }
};

// Tambahan validasi untuk tipe dokumen
export const validateDocument = (file, allowedTypes = [], maxSize = 0) => {
  if (!file) return "File tidak boleh kosong";

  // Validasi tipe file
  if (allowedTypes.length > 0) {
    const fileType = file.type?.toLowerCase() || "";
    const isValidType = allowedTypes.some((type) =>
      fileType.includes(type.toLowerCase().replace("*", ""))
    );

    if (!isValidType) {
      return `Tipe file tidak didukung. Tipe yang diizinkan: ${allowedTypes.join(", ")}`;
    }
  }

  // Validasi ukuran file
  if (maxSize > 0 && file.size > maxSize) {
    return `Ukuran file tidak boleh lebih dari ${formatFileSize(maxSize)}`;
  }

  return null;
};

// Custom hook untuk form validation
export const useFormValidation = (initialValues = {}, validationRules = {}) => {
  const [values, setValues] = React.useState(initialValues);
  const [errors, setErrors] = React.useState({});
  const [touched, setTouched] = React.useState({});
  const [isValid, setIsValid] = React.useState(false);

  const validateForm = () => {
    const newErrors = {};
    let formIsValid = true;

    Object.keys(values).forEach((key) => {
      if (touched[key]) {
        // Pass the type from validationRules or default to "text"
        const type = validationRules[key]?.type || "text";
        const error = validateInput(
          values[key],
          validationRules,
          type,
          key,
          validationRules[key]?.placeholder || key
        );
        if (error) {
          newErrors[key] = error;
          formIsValid = false;
        }
      }
    });

    setErrors(newErrors);
    setIsValid(formIsValid);
  };

  React.useEffect(() => {
    validateForm();
  }, [values, touched]);

  const handleChange = (name, value) => {
    setValues((prev) => ({
      ...prev,
      [name]: value,
    }));
    if (!touched[name]) {
      setTouched((prev) => ({
        ...prev,
        [name]: true,
      }));
    }
  };

  const handleBlur = (name) => {
    if (!touched[name]) {
      setTouched((prev) => ({
        ...prev,
        [name]: true,
      }));
    }
  };

  const resetForm = () => {
    setValues(initialValues);
    setErrors({});
    setTouched({});
    setIsValid(false);
  };

  return {
    values,
    errors,
    touched,
    isValid,
    handleChange,
    handleBlur,
    resetForm,
    setValues,
  };
};

// Function untuk handle camera picker
export const pickCamera = async (options = {}) => {
  try {
    // Request camera permission
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Maaf, kami membutuhkan izin kamera untuk mengambil foto!"
      );
      return null;
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 1,
      base64: true,
      allowsEditing: true,
      ...options,
    });

    if (!result.canceled && result.assets?.[0]) {
      const asset = result.assets[0];
      return {
        uri: asset.uri,
        name: `camera_${new Date().getTime()}.jpg`,
        type: "image/jpeg",
        size: asset.fileSize || 0,
        base64: asset.base64,
      };
    }

    return null;
  } catch (error) {
    console.error("Error using camera:", error);
    Alert.alert("Error", "Gagal mengambil foto. Silakan coba lagi.");
    return null;
  }
};

// Function untuk handle video picker dari galeri
export const pickVideo = async (options = {}) => {
  try {
    // Request permission first
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Maaf, kami membutuhkan izin akses galeri untuk memilih video!"
      );
      return null;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Videos,
      quality: 1,
      videoQuality: "high",
      allowsEditing: true,
      videoMaxDuration: 60, // 60 detik default
      ...options,
    });

    if (!result.canceled && result.assets?.[0]) {
      const asset = result.assets[0];
      return {
        uri: asset.uri,
        name: `video_${new Date().getTime()}.mp4`,
        type: "video/mp4",
        size: asset.fileSize || 0,
        duration: asset.duration, // durasi dalam milidetik
      };
    }

    return null;
  } catch (error) {
    console.error("Error picking video:", error);
    Alert.alert("Error", "Gagal memilih video. Silakan coba lagi.");
    return null;
  }
};

// Function untuk merekam video dengan kamera
export const recordVideo = async (options = {}) => {
  try {
    // Request camera permission
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status !== "granted") {
      Alert.alert(
        "Permission needed",
        "Maaf, kami membutuhkan izin kamera untuk merekam video!"
      );
      return null;
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Videos,
      videoQuality: "high",
      allowsEditing: true,
      videoMaxDuration: 60, // 60 detik default
      ...options,
    });

    if (!result.canceled && result.assets?.[0]) {
      const asset = result.assets[0];
      return {
        uri: asset.uri,
        name: `recording_${new Date().getTime()}.mp4`,
        type: "video/mp4",
        size: asset.fileSize || 0,
        duration: asset.duration, // durasi dalam milidetik
      };
    }

    return null;
  } catch (error) {
    console.error("Error recording video:", error);
    Alert.alert("Error", "Gagal merekam video. Silakan coba lagi.");
    return null;
  }
};

// Tambahan validasi untuk video
export const validateVideo = (file, maxDuration = 60, maxSize = 0) => {
  if (!file) return "Video tidak boleh kosong";

  // Validasi durasi (dalam detik)
  if (maxDuration > 0 && file.duration > maxDuration * 1000) {
    return `Durasi video tidak boleh lebih dari ${maxDuration} detik`;
  }

  // Validasi ukuran file
  if (maxSize > 0 && file.size > maxSize) {
    return `Ukuran video tidak boleh lebih dari ${formatFileSize(maxSize)}`;
  }

  return null;
};
