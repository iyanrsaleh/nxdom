import React, { useEffect } from "react";
import AppNavigator from "./assets/modules/navigation/AppNavigator";

/**
 * Beri tahu jendela pratinjau Nexa (Electron) saat root React siap — splash bisa hilang
 * tepat setelah UI pertama (bukan sekadar event `load` iframe).
 * Hanya jika app dibuka di iframe (pratinjau); browser biasa tidak terpengaruh.
 */
function useNexaPreviewReadySignal() {
  useEffect(() => {
    if (typeof window === "undefined") return;
    var id = requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        try {
          if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: "nexa-expo-preview-ready", v: 1 }, "*");
          }
        } catch (_) {}
      });
    });
    return function () {
      cancelAnimationFrame(id);
    };
  }, []);
}

export default function App() {
  useNexaPreviewReadySignal();
  return <AppNavigator />;
}
