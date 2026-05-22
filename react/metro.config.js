// Learn more https://docs.expo.io/guides/customizing-metro
const path = require('path');
const { getDefaultConfig } = require('expo/metro-config');

/** @type {import('expo/metro-config').MetroConfig} */
const config = getDefaultConfig(__dirname);

/** Paket lokal `file:./package` — mapping eksplisit agar Metro selalu menemukan NexaUI (npm start & Electron `[nexa-expo]`). */
const nexaUIPackageRoot = path.resolve(__dirname, 'package');

// Enable Fast Refresh
config.server = {
  ...config.server,
  enhanceMiddleware: (middleware) => {
    return middleware;
  },
};

// Watch options untuk memastikan perubahan file terdeteksi
config.watchFolders = [__dirname];

// Konfigurasi resolver
config.resolver = {
  ...config.resolver,
  extraNodeModules: {
    ...(config.resolver?.extraNodeModules || {}),
    NexaUI: nexaUIPackageRoot,
  },
  sourceExts: [...(config.resolver?.sourceExts || []), 'jsx', 'js', 'ts', 'tsx'],
  // Block expo-sqlite untuk web platform (mencegah WASM error)
  // expo-sqlite memerlukan WebAssembly yang tidak didukung di web bundler
  blockList: [
    // Block expo-sqlite WASM files untuk web
    /node_modules\/expo-sqlite\/web\/.*\.wasm$/,
    /node_modules\/expo-sqlite\/web\/worker\.ts$/,
    // Block wa-sqlite WASM files
    /node_modules\/.*wa-sqlite.*\.wasm$/,
  ],
  // Custom resolver untuk skip expo-sqlite di web
  // CATATAN: resolveRequest mungkin menyebabkan masalah di native, jadi kita gunakan blockList saja
  // resolveRequest: (context, moduleName, platform) => {
  //   // Skip expo-sqlite untuk web platform (mencegah WASM error)
  //   if (platform === 'web' && (moduleName === 'expo-sqlite' || moduleName.includes('expo-sqlite'))) {
  //     return { type: 'empty' };
  //   }
  //   if (platform === 'web' && (moduleName.includes('wa-sqlite') || moduleName.includes('.wasm'))) {
  //     return { type: 'empty' };
  //   }
  //   return null;
  // },
};

module.exports = config;

