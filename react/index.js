import { registerRootComponent } from "expo";
import App from "./App";

// Filter out unwanted logs
const originalLog = console.log;
console.log = (...args) => {
  const message = args.join(' ');
  // Skip the AppRegistry "Running main" log
  if (message.includes('Running "main" with')) {
    return;
  }
  // Skip expo-file-system/legacy loaded log
  if (message.includes('expo-file-system/legacy loaded') || message.includes('expo-file-system loaded')) {
    return;
  }
  // Skip expo-sqlite loaded log
  if (message.includes('expo-sqlite loaded')) {
    return;
  }
  // Skip expo-camera import log
  if (message.includes('expo-camera berhasil diimport')) {
    return;
  }
  // Skip NexaDBLite database/table logs
  if (message.includes('[NexaDBLite] Database') && message.includes('opened successfully')) {
    return;
  }
  if (message.includes('[NexaDBLite] Table') && message.includes('ensured')) {
    return;
  }
  // Skip example import logs
  if (message.includes('[NexaDBLiteExample]') || message.includes('[NexaDbFirebaseExample]')) {
    return;
  }
  originalLog.apply(console, args);
};

// registerRootComponent calls AppRegistry.registerComponent('main', () => App);
// It also ensures that whether you load the app in Expo Go or in a native build,
// the environment is set up appropriately
registerRootComponent(App);
