/**
 * Preload: sampaikan IPC ke renderer tanpa expose ipcRenderer penuh.
 * context-menu-clicked dipancarkan dari main (electronShell.js) → renderer (App.js).
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  onContextMenuClick: (callback) => {
    ipcRenderer.on('context-menu-clicked', (_event, data) => {
      callback(data);
    });
  },
  removeContextMenuClickListener: () => {
    ipcRenderer.removeAllListeners('context-menu-clicked');
  },
  /**
   * Buka route SPA di jendela baru. String path saja, atau `{ routePath, layout?, handoffId? }`.
   */
  openRouteWindow: (payload) =>
    ipcRenderer.invoke('nexa-open-route-window', payload),
  /**
   * Kembalikan device ID unik (SHA-256 dari MAC + hostname).
   * Dipakai validasi lisensi agar license_key terikat ke satu perangkat.
   */
  getDeviceId: () => ipcRenderer.invoke('nexa-get-device-id'),
  /**
   * Kembalikan nama app dari package.json (mis. 'lisensi', 'sshftp').
   * Dipakai validasi lisensi agar license_key terikat ke satu aplikasi.
   */
  getAppId: () => ipcRenderer.invoke('nexa-get-app-id'),
  googleSignin: (clientId) => ipcRenderer.invoke('nexa-google-signin', clientId),
  getGoogleClientId: () => ipcRenderer.invoke('nexa-get-google-client-id'),
  /**
   * Paksa tema bawaan OS (title bar Windows, native dialog, scrollbar)
   * mengikuti preferensi dark-mode aplikasi.
   * @param {'system'|'light'|'dark'} source
   */
  setNativeTheme: (source) => ipcRenderer.invoke('nexa-set-native-theme', source),
  getNativeTheme: () => ipcRenderer.invoke('nexa-get-native-theme'),
  awaitDispatch: (packet) => ipcRenderer.invoke('nexa-await-dispatch', packet),
});
