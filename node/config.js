/** Hanya untuk Node (server.js). Browser dapat endpoint lewat window.__NEXA_ENDPOINT__ di HTML — GET /config.js diblokir. */
const config = {
  /** Origin SPA (Express); harus sama port dengan server yang dijalankan */
  url: "http://localhost:3107",
  /**
   * url jika menggunakan drive backend Nexa Dom Framework
   * URL API PHP/backend sebenarnya (boleh host/port lain).
   * server.js mem-proxy /api → host ini; browser disuntik same-origin …/api (bukan akses langsung ke URL ini).
   */
  urlApi: "http://localhost/api",
  /** Sama pola: path /assets/drive di origin SPA di-proxy ke URL ini jika beda host */
  drive: "http://localhost/assets/drive",


  // url jika menggunakan rebit backend sisipkan ke public di Framework anda folder rebit
  rebit: `http://localhost:8006/rebit`,


  // url typicode backend umum baca dokuentasi NXUI.Storage().api(row, body) 
  typicode: "https://jsonplaceholder.typicode.com/photos?_limit=5",
  // jika tidak menggunakan firebase backend maka isi dengan false
  firebaseConfig: false,
  // buat variable api backend anda di Framework anda dengan nama apiBackend dan ases megunakan NEXA.apiBackend
  // CONTOH:
  //apiBackend: "http://192.168.1.5/api",
};

export default config;
