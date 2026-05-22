const FirebaseConfig = {
  apiKey: "AIzaSyA0XUCGzsK7hhg8NmxisslthTeOU93dORA",
  authDomain: "nexaui-86863.firebaseapp.com",
  databaseURL: "https://nexaui-86863-default-rtdb.firebaseio.com",
  projectId: "nexaui-86863",
  storageBucket: "nexaui-86863.firebasestorage.app",
  messagingSenderId: "1034885626532",
  appId: "1:1034885626532:web:64272a0e491f944dd04431",
  measurementId: "G-REZVBSZ6KR",
};

const hosts = "http://192.168.1.10";
const Server = {
  // Core endpoints (React Native + web compatibility)
  url: hosts,
  urlApi: hosts + "/api",
  API_URL: hosts + "/api",
  FILE_URL: hosts,

  // Optional endpoint variables - accessible as NEXA.<name>
  drive: hosts + "/assets/drive",
  rebit: "http://192.168.1.10/rebit",
  typicode: "https://jsonplaceholder.typicode.com/photos",
  firebaseConfig: FirebaseConfig,
};

// Export both configurations
export default Server;
export { FirebaseConfig };
