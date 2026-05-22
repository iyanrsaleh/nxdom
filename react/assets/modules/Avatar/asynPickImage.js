import * as ImagePicker from "expo-image-picker";
import AsyncStorage from "@react-native-async-storage/async-storage";

const AVATAR_STORAGE_KEY = "@user_avatar";
const DEFAULT_IMAGE =
  "https://images.unsplash.com/photo-1633332755192-727a05c4013d?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=facearea&facepad=2.5&w=256&h=256&q=80";

const ImgPicker = {
  requestPermission: async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    return status === "granted";
  },

  loadSavedImage: async () => {
    try {
      const savedImageRaw = await AsyncStorage.getItem(AVATAR_STORAGE_KEY);
      const savedImage = savedImageRaw ? JSON.parse(savedImageRaw) : null;
      return savedImage || DEFAULT_IMAGE;
    } catch (error) {
      console.error("Error loading saved image:", error);
      return DEFAULT_IMAGE;
    }
  },

  pickImage: async () => {
    try {
      const hasPermission = await ImgPicker.requestPermission();

      if (!hasPermission) {
        throw new Error("Gallery permission not granted");
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 1,
      });

      if (result.canceled) {
        const currentImage = await ImgPicker.loadSavedImage();
        return currentImage;
      }

      const imageUri = result.assets[0].uri;
      await AsyncStorage.setItem(AVATAR_STORAGE_KEY, JSON.stringify(imageUri));
      return imageUri;
    } catch (error) {
      console.error("Error in pickImage:", error);
      throw error;
    }
  },

  handleImageSelection: async (onSuccess, onError) => {
    try {
      const imageUri = await ImgPicker.pickImage();
      if (imageUri) {
        onSuccess(imageUri);
      }
    } catch (error) {
      onError?.(error);
      alert(error.message || "Error picking image");
    }
  },
};

export default ImgPicker;
