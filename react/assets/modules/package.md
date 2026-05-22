# NexaReact Package Documentation

## Deskripsi

NexaReact adalah paket komponen React Native yang komprehensif dengan berbagai fitur dan utilitas untuk pengembangan aplikasi mobile yang efisien.

## Instalasi

```bash
npm install NexaUI
```

## Import

```javascript
import {
  React,
  useState,
  useEffect,
  View,
  Text,
  StyleSheet,
  Input,
  Buttons,
  Colors,
  NexaFirestore,
  // ... komponen lainnya
} from "NexaUI";
```

## Fitur Utama

### 🎯 React & React Native Core

- **React Hooks**: `useState`, `useEffect`, `useCallback`, `useMemo`
- **Navigation**: `useNavigation` dari React Navigation
- **React Native Components**: View, Text, StyleSheet, TouchableOpacity, TextInput, Image, ScrollView, FlatList, dan banyak lagi
- **Icons**: Feather Icons dan FontAwesome5

### 📱 UI Components

#### Form Components

- **Input**: Komponen input yang dapat dikustomisasi
- **Switch**: Toggle switch component
- **SelectList**: Dropdown/picker component
- **RichTextEditor**: Editor teks kaya dengan toolbar
- **Validation**: Sistem validasi form yang lengkap

#### Visual Components

- **Header**: Komponen header aplikasi
- **Buttons**: Berbagai jenis tombol (Action, BtnGroup, BtnTabs, CustomButton)
- **Avatar**: Komponen avatar pengguna
- **Images**: Manajemen gambar
- **Carousel**: Komponen slider/carousel
- **Modal**: Komponen modal yang dapat dikustomisasi
- **Loader**: Komponen loading indicator

#### Layout & Styling

- **Grid**: Sistem grid layout
- **Colors**: Palette warna standar
- **FontFamily**: Font Montserrat dan utilitas font
- **useMontserratFonts**: Hook untuk menggunakan font Montserrat
- **Typography**: Utilitas tipografi (`fs`)
- **assetsImage**: Manajemen aset gambar lokal

### 🛠️ Utilities

#### Media Handling

- **Image Picker**: `pickImage`, `pickCamera`, `pickMultipleImages`
- **Video Handling**: `pickVideo`, `recordVideo`, `validateVideo`
- **Document Picker**: `pickDocument`, `pickMultipleDocuments`
- **File Utilities**: `convertToBase64`, `parseFileSize`, `formatFileSize`

#### Storage & Data

- **AsyncStorage**: Penyimpanan lokal
- **NexaSync**: Sinkronisasi data dengan server
- **createNexaSync**: Factory untuk membuat instance NexaSync
- **Storage & Network**: Manajemen penyimpanan dan jaringan
- **Server**: Konfigurasi server

#### Firebase Integration

- **NexaFirestore**: Operasi CRUD Firebase
- **Firebase Data**: `firebaseData`, `nexaFirebase`
- **Firebase Config**: Konfigurasi Firebase
- **Cache Management**: `clearNexaFirebaseCache`, `getNexaFirebaseCacheInfo`

#### Special Features

- **QR Code**: Generator QR Code
- **Scanner**: Scanner QR Code (`NexaScanqr`)
- **Speech**: Text-to-Speech dengan ExpoSpeech
- **Local Images**: Manajemen aset gambar lokal
- **useModal**: Hook untuk manajemen modal
- **Icon**: Sistem ikon yang fleksibel

### 🌐 HTML-like Components

Komponen yang mirip dengan HTML untuk kemudahan penggunaan:

- **Div, P, Span**: Komponen layout dasar
- **H1, H2, H3**: Komponen heading
- **Section, Article**: Komponen struktur
- **HtmlButton**: Tombol dengan gaya HTML
- **createHTMLElement**: Factory untuk membuat komponen HTML-like
- **getNativeComponent**: Konverter ke komponen native

## Contoh Penggunaan

### Basic Component

```javascript
import { View, Text, Buttons, Colors } from "NexaUI";

export default function App() {
  return (
    <View style={{ flex: 1, backgroundColor: Colors.primary }}>
      <Text>Hello NexaReact!</Text>
      <Buttons title="Click Me" onPress={() => alert("Clicked!")} />
    </View>
  );
}
```

### Form dengan Validasi

```javascript
import { Input, useFormValidation, Buttons } from "NexaUI";

export default function LoginForm() {
  const { values, errors, handleChange, validateForm } = useFormValidation({
    email: "",
    password: "",
  });

  return (
    <View>
      <Input
        placeholder="Email"
        value={values.email}
        onChangeText={(text) => handleChange("email", text)}
        error={errors.email}
      />
      <Input
        placeholder="Password"
        secureTextEntry
        value={values.password}
        onChangeText={(text) => handleChange("password", text)}
        error={errors.password}
      />
      <Buttons
        title="Login"
        onPress={() => validateForm() && console.log("Valid!")}
      />
    </View>
  );
}
```

### Image Picker

```javascript
import { ImgPicker, pickImage, Avatar } from "NexaUI";

export default function ProfilePicture() {
  const [imageUri, setImageUri] = useState(null);

  const selectImage = async () => {
    const result = await pickImage();
    if (result) {
      setImageUri(result.uri);
    }
  };

  return (
    <View>
      <Avatar source={{ uri: imageUri }} />
      <Buttons title="Select Image" onPress={selectImage} />
    </View>
  );
}
```

### Firebase Integration

```javascript
import { NexaFirestore, initNexaFirebase } from "NexaUI";

// Inisialisasi Firebase
initNexaFirebase({
  apiKey: "your-api-key",
  authDomain: "your-domain",
  // ... konfigurasi lainnya
});

// Menggunakan CRUD
const firebase = new NexaFirestore("users");

// Create
await firebase.add({ user1: { name: "John", email: "john@example.com" } });

// Read
const users = await firebase.getAll();

// Update
await firebase.update("user-id", { name: "John Updated" });

// Delete
await firebase.del("user-id");
```

### RichTextEditor

```javascript
import { RichTextEditor, View } from "NexaUI";

export default function TextEditor() {
  const richTextRef = useRef(null);

  const getContent = () => {
    const content = richTextRef.current?.getContentHtml();
    console.log("HTML Content:", content);
  };

  return (
    <View>
      <RichTextEditor
        ref={richTextRef}
        value="<p>Initial content</p>"
        placeholder="Start typing..."
      />
      <Button title="Get Content" onPress={getContent} />
    </View>
  );
}
```

### Button Variants

```javascript
import { Buttons, ButtonsAction, isBtnGroup, BtnTabs, Loader } from "NexaUI";

export default function ButtonExample() {
  const buttons = [
    { label: "Save", primary: true, onPress: () => console.log("Save") },
    { label: "Cancel", primary: false, onPress: () => console.log("Cancel") },
  ];

  return (
    <View>
      {/* Basic Button */}
      <Buttons title="Click Me" onPress={() => alert("Clicked!")} />

      {/* Action Button with Icon */}
      <ButtonsAction
        label="Upload"
        iconName="upload"
        Feather
        onPress={() => console.log("Upload")}
      />

      {/* Button Group */}
      <isBtnGroup buttons={buttons} />

      {/* Loading Button */}
      <ButtonsAction label="Saving..." loading={true} />

      {/* Standalone Loader */}
      <Loader />
    </View>
  );
}
```

### QR Code & Speech

```javascript
import { QRCodeGenerator, ExpoSpeech, View } from "NexaUI";

export default function QRSpeechExample() {
  const speakText = async () => {
    await ExpoSpeech.speak("Hello from NexaUI!");
  };

  return (
    <View>
      <QRCodeGenerator value="https://example.com" size={200} />
      <Button title="Speak Text" onPress={speakText} />
    </View>
  );
}
```

### Grid & Layout

```javascript
import { Grid, View, Text, Colors } from "NexaUI";

export default function GridExample() {
  return (
    <View>
      <Grid container spacing={2}>
        <Grid item xs={6}>
          <View style={{ backgroundColor: Colors.primary, padding: 20 }}>
            <Text>Column 1</Text>
          </View>
        </Grid>
        <Grid item xs={6}>
          <View style={{ backgroundColor: Colors.secondary, padding: 20 }}>
            <Text>Column 2</Text>
          </View>
        </Grid>
      </Grid>
    </View>
  );
}
```

### Modal & Typography

```javascript
import { useModal, fs, FontFamily, View, Text, Button } from "NexaUI";

export default function ModalExample() {
  const { showModal, hideModal } = useModal();

  const openModal = () => {
    showModal(
      <View style={{ padding: 20 }}>
        <Text style={[fs.h1, { fontFamily: FontFamily.bold }]}>
          Modal Title
        </Text>
        <Text style={fs.body}>Modal content here</Text>
        <Button title="Close" onPress={hideModal} />
      </View>
    );
  };

  return (
    <View>
      <Button title="Open Modal" onPress={openModal} />
    </View>
  );
}
```

### Storage & Sync

```javascript
import { NexaSync, createNexaSync, AsyncStorage } from "NexaUI";

export default function StorageExample() {
  const sync = createNexaSync({
    endpoint: "https://api.example.com",
    apiKey: "your-api-key",
  });

  const saveData = async () => {
    // Save locally
    await AsyncStorage.setItem(
      "user_data",
      JSON.stringify({
        name: "John Doe",
        email: "john@example.com",
      })
    );

    // Sync with server
    await sync.upload("user_data");
  };

  const loadData = async () => {
    // Try to get from local storage first
    const localData = await AsyncStorage.getItem("user_data");

    if (!localData) {
      // Sync from server if not available locally
      await sync.download("user_data");
    }
  };

  return (
    <View>
      <Button title="Save Data" onPress={saveData} />
      <Button title="Load Data" onPress={loadData} />
    </View>
  );
}
```

## Komponen Lengkap yang Tersedia

### Semua Exports dari NexaUI:

```javascript
import {
  // React & React Native Core
  React,
  useState,
  useEffect,
  useCallback,
  useMemo,
  useNavigation,
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
  FeatherIcon,
  FontAwesome,

  // Form Components
  Input,
  Switch,
  SelectList,
  RichTextEditor,
  validateInput,
  useFormValidation,

  // Media Handling
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

  // UI Components
  Buttons,
  ButtonsAction,
  isBtnGroup,
  BtnTabs,
  CustomButton,
  Loader,
  Header,
  Avatar,
  Images,
  ImgPicker,
  Carousel,
  useModal,

  // Layout & Styling
  Grid,
  Colors,
  FontFamily,
  useMontserratFonts,
  fs,
  assetsImage,

  // Storage & Data
  AsyncStorage,
  NexaSync,
  createNexaSync,
  Storage,
  Network,
  Server,

  // Firebase Integration
  NexaFirestore,
  firebaseData,
  nexaFirebase,
  initNexaFirebase,
  FirebaseConfig,
  clearNexaFirebaseCache,
  getNexaFirebaseCacheInfo,

  // Special Features
  QRCodeGenerator,
  NexaScanqr,
  ExpoSpeech,
  Icon,

  // HTML-like Components
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
} from "NexaUI";
```

## Struktur Folder

```
package/
├── Avatar/          # Komponen avatar dan image handling
├── Buttons/         # Berbagai jenis tombol
├── Form/           # Komponen form dan validasi
├── Fonts/          # Font utilities
├── Icon/           # Komponen ikon
├── Modal/          # Komponen modal
├── Scanner/        # QR Scanner
├── Storage/        # Data storage dan sync
├── utils/          # Utilitas umum
├── header/         # Komponen header
└── Salid/          # Carousel component
```

## Dependencies

- React Native
- React Navigation
- React Native Vector Icons
- Expo (untuk beberapa fitur)
- Firebase (opsional)

## Platform Support

- ✅ iOS
- ✅ Android
- ✅ Expo

## Lisensi

MIT License

## Kontribusi

Silakan buka issue atau pull request untuk kontribusi.
