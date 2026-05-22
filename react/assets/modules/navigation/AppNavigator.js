import React, { useState, useEffect, useCallback } from "react";
import {
  NavigationContainer,
  DefaultTheme,
  useNavigationContainerRef,
} from "@react-navigation/native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import { View, Text, Platform, StatusBar, AppState } from "react-native";
import { getRoutes } from "../../../templates/routes";
import { useMontserratFonts, FontFamily } from "../Fonts/Montserrat";
import { NexaDBLite, properti } from "../index";

const Stack = createNativeStackNavigator();

// Helper function untuk menentukan apakah warna termasuk gelap
const isColorDark = (hexColor) => {
  const darkColors = [
    "#211E1F",
    "#333333",
    "#2196F3",
    "#4CAF50",
    "#F44336",
    "#9C27B0",
  ];
  return darkColors.includes(hexColor);
};

export default function AppNavigator() {
  const fontsLoaded = useMontserratFonts();
  const navigationRef = useNavigationContainerRef();
  const [initialRoute, setInitialRoute] = useState("Home");
  const [isCheckingSession, setIsCheckingSession] = useState(true);
  const [assetColor, setAssetColor] = useState(null);
  const [propertiData, setPropertiData] = useState(null);
  const [routes, setRoutes] = useState([]);

  // Function untuk load dan update routes (menggunakan useCallback untuk stability)
  const loadAndUpdateRoutes = useCallback(async () => {
    try {
      // Ambil data lengkap dari properti (termasuk statusBar)
      const data = await properti.get();
      setPropertiData(data);
      const color = data?.assetColor || await properti.getAssetColor();
      setAssetColor(color);
      // Update routes dengan warna dan propertiData (termasuk statusBar)
      const updatedRoutes = getRoutes(color, data);
      setRoutes(updatedRoutes);
      
      // Update StatusBar untuk route saat ini jika ada
      if (navigationRef.current) {
        const currentRoute = navigationRef.current.getCurrentRoute();
        if (currentRoute) {
          const route = updatedRoutes.find((r) => r.name === currentRoute.name);
          if (route?.options?.statusBar) {
            const statusBarStyle = route.options.statusBar.style;
            const statusBarBg = route.options.statusBar.backgroundColor;
            
            // Update dengan delay untuk memastikan tidak di-override
            setTimeout(() => {
              StatusBar.setBackgroundColor(statusBarBg);
              const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
                ? "light-content"
                : "dark-content";
              StatusBar.setBarStyle(barStyle);
              
              // Log removed for cleaner console output
            }, 150);
          }
        }
      }
    } catch (error) {
      console.error('Error loading assetColor:', error);
      // Fallback ke routes default
      setRoutes(getRoutes());
    }
  }, [navigationRef]);

  // Load assetColor dan propertiData lengkap, lalu update routes saat mount
  useEffect(() => {
    loadAndUpdateRoutes();
  }, []);

  // Listen untuk perubahan properti dan update routes secara berkala
  useEffect(() => {
    if (!propertiData) return; // Skip jika propertiData belum di-load
    
    // Check perubahan properti setiap 3 detik
    const intervalId = setInterval(async () => {
      try {
        // Ambil data terbaru dari cache atau API
        const latestData = await properti.get();
        
        // Bandingkan updatedAt untuk detect perubahan
        if (propertiData?.updatedAt !== latestData?.updatedAt) {
          // Log removed for cleaner console output
          // Update routes dengan data terbaru
          await loadAndUpdateRoutes();
        }
      } catch (error) {
        console.error('Error checking properti changes:', error);
      }
    }, 3000); // Check setiap 3 detik

    return () => clearInterval(intervalId);
  }, [propertiData?.updatedAt, loadAndUpdateRoutes]);

  // Listen untuk app state change (foreground) dan refresh routes
  useEffect(() => {
    const subscription = AppState.addEventListener('change', async (nextAppState) => {
      if (nextAppState === 'active') {
        // App kembali ke foreground, refresh routes
        // Log removed for cleaner console output
        await loadAndUpdateRoutes();
      }
    });

    return () => subscription?.remove();
  }, [loadAndUpdateRoutes]);

  // Inisialisasi awal StatusBar
  useEffect(() => {
    if (routes.length === 0) return;
    
    // Mengambil konfigurasi default dari route awal (Home)
    const defaultRoute = routes.find((r) => r.name === "Home");
    if (defaultRoute?.options?.statusBar) {
      const statusBarStyle = defaultRoute.options.statusBar.style;
      const statusBarBg = defaultRoute.options.statusBar.backgroundColor;
      
      // Update dengan delay untuk memastikan tidak di-override
      setTimeout(() => {
        StatusBar.setBackgroundColor(statusBarBg);
        const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
          ? "light-content"
          : "dark-content";
        StatusBar.setBarStyle(barStyle);
        
        // Log removed for cleaner console output
      }, 100);
    }
  }, [routes]); // Update saat routes berubah

  // Check login status on app start
  useEffect(() => {
    const checkLoginStatus = async () => {
      // Set timeout untuk mencegah hang terlalu lama (10 detik untuk memberikan waktu untuk retry)
      const timeoutId = setTimeout(() => {
        console.warn("⚠️ [AppNavigator] Session check timeout, using default route");
        setInitialRoute("Home");
        setIsCheckingSession(false);
      }, 10000); // 10 detik timeout (memberikan waktu untuk 2 retry x 5 detik)

      try {
        setIsCheckingSession(true);
        
        // Get user session dari NexaDBLite - data.id digunakan sebagai key
        // Wrap dalam try-catch untuk mencegah error blocking UI
        let userSession = null;
        try {
          // Retry mechanism dengan timeout yang lebih panjang
          let retries = 2;
          let lastError = null;
          
          while (retries > 0) {
            try {
              // Add timeout untuk database call (5 detik untuk memberikan waktu lebih)
              const dbPromise = NexaDBLite.get("userSessions", "userSession");
              const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error("Database call timeout")), 5000)
              );
              
              userSession = await Promise.race([dbPromise, timeoutPromise]);
              // Jika berhasil, break dari loop
              break;
            } catch (retryError) {
              lastError = retryError;
              retries--;
              // Tunggu sebentar sebelum retry (hanya jika masih ada retry)
              if (retries > 0) {
                await new Promise(resolve => setTimeout(resolve, 300));
              }
            }
          }
          
          // Jika semua retry gagal, throw error terakhir
          if (!userSession && lastError) {
            throw lastError;
          }
        } catch (dbError) {
          // Jika error, log tapi jangan block UI
          console.warn("⚠️ [AppNavigator] Error getting user session:", dbError?.message || dbError);
          // Fallback: tetap gunakan route "Home"
          clearTimeout(timeoutId);
          setInitialRoute("Home");
          setIsCheckingSession(false);
          return;
        }
        
        clearTimeout(timeoutId);
        
        // Jika ada session dan valid, set route ke "User"
        if (userSession && userSession.isLoggedIn) {
          setInitialRoute("User");
          // Update StatusBar sesuai route User jika ada session
          // Gunakan routes yang sudah di-update dengan assetColor dan propertiData
          const currentRoutes = (assetColor || propertiData) ? getRoutes(assetColor, propertiData) : routes;
          const userRoute = currentRoutes.find((r) => r.name === "User");
          if (userRoute?.options?.statusBar) {
            const statusBarStyle = userRoute.options.statusBar.style;
            const statusBarBg = userRoute.options.statusBar.backgroundColor;
            
            // Update dengan delay untuk memastikan tidak di-override
            setTimeout(() => {
              StatusBar.setBackgroundColor(statusBarBg);
              const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
                ? "light-content"
                : "dark-content";
              StatusBar.setBarStyle(barStyle);
              
              // Log removed for cleaner console output
            }, 200);
          }
        } else {
          // Tidak ada session atau session tidak valid, tetap di "Home"
          setInitialRoute("Home");
        }
      } catch (error) {
        // Catch all errors dan pastikan UI tetap bisa render
        clearTimeout(timeoutId);
        console.error("❌ [AppNavigator] Error checking login status:", error);
        // Fallback: selalu set ke "Home" jika ada error
        setInitialRoute("Home");
      } finally {
        clearTimeout(timeoutId);
        setIsCheckingSession(false);
      }
    };
    checkLoginStatus();
  }, []);

  // Handle route change untuk update StatusBar
  useEffect(() => {
    if (navigationRef.current && routes.length > 0) {
      const unsubscribe = navigationRef.current.addListener("state", () => {
        const currentRoute = navigationRef.current.getCurrentRoute();
        if (currentRoute) {
          const route = routes.find((r) => r.name === currentRoute.name);
          if (route?.options?.statusBar) {
            const statusBarStyle = route.options.statusBar.style;
            const statusBarBg = route.options.statusBar.backgroundColor;
            
            // Update StatusBar dengan delay dan retry untuk memastikan tidak di-override
            const updateStatusBar = () => {
              StatusBar.setBackgroundColor(statusBarBg);
              const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
                ? "light-content"
                : "dark-content";
              StatusBar.setBarStyle(barStyle);
              
              // Log removed for cleaner console output
            };
            
            // Update segera
            updateStatusBar();
            
            // Retry setelah delay untuk memastikan tidak di-override
            setTimeout(updateStatusBar, 100);
            setTimeout(updateStatusBar, 300);
            setTimeout(updateStatusBar, 500);
          } else {
            // Jika route tidak punya statusBar config, gunakan dari properti untuk route User
            if (currentRoute.name === "User" && propertiData?.statusBar) {
              const statusBarStyle = propertiData.statusBar;
              const statusBarBg = assetColor?.backgroundColor || assetColor?.buttonColor || "#24BCA9";
              
              const updateStatusBar = () => {
                StatusBar.setBackgroundColor(statusBarBg);
                const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
                  ? "light-content"
                  : "dark-content";
                StatusBar.setBarStyle(barStyle);
                
                // Log removed for cleaner console output
              };
              
              // Update segera dan retry
              updateStatusBar();
              setTimeout(updateStatusBar, 100);
              setTimeout(updateStatusBar, 300);
              setTimeout(updateStatusBar, 500);
            }
          }
        }
      });
      return unsubscribe;
    }
  }, [navigationRef, routes, propertiData, assetColor]);

  // Interval untuk memastikan StatusBar tidak di-override (khusus untuk route User)
  useEffect(() => {
    if (!navigationRef.current || routes.length === 0) return;
    
    const intervalId = setInterval(() => {
      const currentRoute = navigationRef.current?.getCurrentRoute();
      if (currentRoute && currentRoute.name === "User") {
        const route = routes.find((r) => r.name === "User");
        if (route?.options?.statusBar) {
          const statusBarStyle = route.options.statusBar.style;
          const statusBarBg = route.options.statusBar.backgroundColor;
          
          StatusBar.setBackgroundColor(statusBarBg);
          const barStyle = (statusBarStyle === "light" || statusBarStyle === "light-content")
            ? "light-content"
            : "dark-content";
          StatusBar.setBarStyle(barStyle);
        }
      }
    }, 2000); // Check setiap 2 detik untuk route User

    return () => clearInterval(intervalId);
  }, [navigationRef, routes]);

  // Wait for fonts, session check, and routes to be ready
  if (!fontsLoaded || isCheckingSession || routes.length === 0) {
    return null; // Or return a loading screen if needed
  }

  return (
    <View style={{ flex: 1 }}>
      <NavigationContainer ref={navigationRef}>
        <Stack.Navigator initialRouteName={initialRoute}>
          {routes.map((route) => (
            <Stack.Screen
              key={route.name}
              name={route.name}
              component={route.component}
              options={(props) => {
                // Jika options adalah function, panggil dengan props
                // Jika options adalah object, gunakan langsung
                const baseOptions = typeof route.options === 'function' 
                  ? route.options(props) 
                  : route.options || {};
                
                return {
                  ...baseOptions,
                headerTitleStyle: {
                    ...(baseOptions?.headerTitleStyle || {}),
                  fontFamily: FontFamily.semiBold,
                },
                };
              }}
            />
          ))}
        </Stack.Navigator>
      </NavigationContainer>
    </View>
  );
}
