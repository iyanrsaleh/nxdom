import React, { useState, useEffect } from "react";
import {
  Text,
  View,
  StyleSheet,
  TouchableOpacity,
  Modal,
  Alert,
  Platform,
} from "react-native";
import FeatherIcon from "react-native-vector-icons/Feather";

// Dynamic import untuk expo-camera
let CameraView = null;
let Camera = null;
let useCameraPermissions = null;
let isCameraAvailable = false;

try {
  const ExpoCamera = require("expo-camera");
  // Expo Camera versi baru (SDK 50+)
  CameraView = ExpoCamera.CameraView;
  // Expo Camera versi lama sebagai fallback
  Camera = ExpoCamera.Camera;
  // Import useCameraPermissions hook
  useCameraPermissions = ExpoCamera.useCameraPermissions;
  // Import Camera object untuk permission methods
  const CameraModule = ExpoCamera.Camera || ExpoCamera;
  isCameraAvailable = true;
  // console.log("✅ expo-camera berhasil diimport");
} catch (error) {
  console.log("❌ expo-camera tidak tersedia:", error.message);
  isCameraAvailable = false;
}

const NexaScanqr = ({
  isVisible = false,
  onScanSuccess,
  onScanError,
  onClose,
  scannerTitle = "Scan QR Code",
}) => {
  const [scanned, setScanned] = useState(false);
  const [permission, setPermission] = useState(null);

  // Request camera permission saat komponen mount atau saat visible
  useEffect(() => {
    if (isVisible && isCameraAvailable) {
      // Set permission ke null dulu untuk menampilkan loading state
      setPermission(null);
      requestCameraPermission();
    }
  }, [isVisible]);

  const requestCameraPermission = async () => {
    try {
      if (!isCameraAvailable) {
        setPermission({ granted: false });
        return;
      }

      // Gunakan API permission dari expo-camera
      // Fungsi permission ada di object Camera
      const expoCamera = require("expo-camera");
      
      // Pastikan Camera object tersedia
      if (!expoCamera.Camera) {
        console.error("Camera object not available in expo-camera");
        setPermission({ granted: false });
        return;
      }
      
      // Cek permission status terlebih dahulu
      const permissionResponse = await expoCamera.Camera.getCameraPermissionsAsync();
      
      // Jika sudah granted, set permission
      if (permissionResponse.granted || permissionResponse.status === 'granted') {
        setPermission({ granted: true });
        return;
      }

      // Request permission jika belum granted
      const requestResponse = await expoCamera.Camera.requestCameraPermissionsAsync();
      
      // Set permission berdasarkan response
      if (requestResponse.granted || requestResponse.status === 'granted') {
        setPermission({ granted: true });
      } else {
        setPermission({ granted: false });
      }
    } catch (error) {
      console.error("Error requesting camera permission:", error);
      // Fallback jika error
      setPermission({ granted: false });
    }
  };

  const handleBarCodeScanned = ({ type, data }) => {
    if (scanned) return;

    setScanned(true);
    // Log removed for cleaner console output

    if (onScanSuccess) {
      onScanSuccess({
        type,
        data,
        timestamp: new Date().toISOString(),
      });
    } else {
      Alert.alert("QR Code Scanned!", data, [{ text: "OK", onPress: onClose }]);
    }
  };

  if (!isVisible) {
    return null;
  }

  // Jika camera tidak tersedia, tampilkan error
  if (!isCameraAvailable) {
    return (
      <Modal visible={isVisible} animationType="slide">
        <View style={styles.container}>
          <View style={styles.header}>
            <Text style={styles.title}>{scannerTitle}</Text>
            <TouchableOpacity style={styles.closeButton} onPress={onClose}>
              <FeatherIcon name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </View>
          <View style={styles.errorContainer}>
            <FeatherIcon name="camera-off" size={80} color="#666" />
            <Text style={styles.errorText}>Camera tidak tersedia</Text>
            <Text style={styles.errorSubText}>
              expo-camera atau react-native-camera belum terinstall dengan benar
            </Text>
            <TouchableOpacity style={styles.closeButtonAlt} onPress={onClose}>
              <Text style={styles.closeButtonText}>Tutup</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    );
  }

  // Jika permission belum dikasih
  if (permission === null) {
    return (
      <Modal visible={isVisible} animationType="slide">
        <View style={styles.container}>
          <View style={styles.header}>
            <Text style={styles.title}>{scannerTitle}</Text>
            <TouchableOpacity style={styles.closeButton} onPress={onClose}>
              <FeatherIcon name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </View>
          <View style={styles.errorContainer}>
            <Text style={styles.permissionText}>
              Meminta izin akses kamera...
            </Text>
          </View>
        </View>
      </Modal>
    );
  }

  // Jika permission ditolak
  if (permission && !permission.granted) {
    return (
      <Modal visible={isVisible} animationType="slide">
        <View style={styles.container}>
          <View style={styles.header}>
            <Text style={styles.title}>{scannerTitle}</Text>
            <TouchableOpacity style={styles.closeButton} onPress={onClose}>
              <FeatherIcon name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </View>
          <View style={styles.errorContainer}>
            <FeatherIcon name="camera-off" size={80} color="#666" />
            <Text style={styles.errorText}>Akses kamera ditolak</Text>
            <Text style={styles.errorSubText}>
              Berikan izin kamera untuk menggunakan scanner QR
            </Text>
            <TouchableOpacity
              style={styles.permissionButton}
              onPress={requestCameraPermission}
              activeOpacity={0.7}
            >
              <Text style={styles.permissionButtonText}>Berikan Izin</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    );
  }

  // RENDER CAMERA ASLI
  return (
    <Modal visible={isVisible} animationType="slide">
      <View style={styles.container}>
        {/* CAMERA COMPONENT ASLI */}
        <CameraView
          style={StyleSheet.absoluteFillObject}
          facing="back"
          onBarcodeScanned={scanned ? undefined : handleBarCodeScanned}
          barcodeScannerSettings={{
            barcodeTypes: ["qr"],
          }}
        />

        {/* Header Overlay */}
        <View style={styles.header}>
          <Text style={styles.title}>{scannerTitle}</Text>
          <TouchableOpacity style={styles.closeButton} onPress={onClose}>
            <FeatherIcon name="x" size={24} color="#fff" />
          </TouchableOpacity>
        </View>

        {/* Scan Area Overlay */}
        <View style={styles.scanOverlay}>
          <View style={styles.scanFrame}>
            <View style={[styles.corner, styles.topLeft]} />
            <View style={[styles.corner, styles.topRight]} />
            <View style={[styles.corner, styles.bottomLeft]} />
            <View style={[styles.corner, styles.bottomRight]} />
          </View>
          <Text style={styles.instruction}>Arahkan kamera ke QR code</Text>
        </View>

        {/* Scan Again Button */}
        {scanned && (
          <View style={styles.scanAgainContainer}>
            <TouchableOpacity
              style={styles.scanAgainButton}
              onPress={() => setScanned(false)}
            >
              <Text style={styles.scanAgainText}>📷 Scan Lagi</Text>
            </TouchableOpacity>
          </View>
        )}
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#000",
  },
  header: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingTop: 50,
    paddingHorizontal: 20,
    paddingBottom: 20,
    backgroundColor: "rgba(0,0,0,0.7)",
    zIndex: 2,
  },
  title: {
    color: "#fff",
    fontSize: 20,
    fontWeight: "600",
  },
  closeButton: {
    padding: 10,
    backgroundColor: "rgba(255,255,255,0.2)",
    borderRadius: 25,
  },
  scanOverlay: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: "center",
    alignItems: "center",
    zIndex: 1,
  },
  scanFrame: {
    width: 250,
    height: 250,
    position: "relative",
    marginBottom: 30,
  },
  corner: {
    position: "absolute",
    width: 40,
    height: 40,
    borderColor: "#00FF00",
    borderWidth: 4,
  },
  topLeft: {
    top: 0,
    left: 0,
    borderBottomWidth: 0,
    borderRightWidth: 0,
  },
  topRight: {
    top: 0,
    right: 0,
    borderBottomWidth: 0,
    borderLeftWidth: 0,
  },
  bottomLeft: {
    bottom: 0,
    left: 0,
    borderTopWidth: 0,
    borderRightWidth: 0,
  },
  bottomRight: {
    bottom: 0,
    right: 0,
    borderTopWidth: 0,
    borderLeftWidth: 0,
  },
  instruction: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "600",
    textAlign: "center",
    backgroundColor: "rgba(0,0,0,0.8)",
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 10,
  },
  scanAgainContainer: {
    position: "absolute",
    bottom: 100,
    left: 50,
    right: 50,
    zIndex: 2,
  },
  scanAgainButton: {
    backgroundColor: "#007AFF",
    paddingVertical: 15,
    paddingHorizontal: 30,
    borderRadius: 25,
    alignItems: "center",
  },
  scanAgainText: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "600",
  },
  errorContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    padding: 20,
  },
  errorText: {
    color: "#fff",
    fontSize: 20,
    fontWeight: "600",
    marginTop: 20,
    textAlign: "center",
  },
  errorSubText: {
    color: "#ccc",
    fontSize: 16,
    textAlign: "center",
    marginTop: 10,
    lineHeight: 22,
  },
  permissionText: {
    color: "#fff",
    fontSize: 18,
    textAlign: "center",
  },
  permissionButton: {
    backgroundColor: "#007AFF",
    paddingHorizontal: 30,
    paddingVertical: 15,
    borderRadius: 10,
    marginTop: 20,
  },
  permissionButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  closeButtonAlt: {
    backgroundColor: "#666",
    paddingHorizontal: 30,
    paddingVertical: 15,
    borderRadius: 10,
    marginTop: 20,
  },
  closeButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
});

export default NexaScanqr;
