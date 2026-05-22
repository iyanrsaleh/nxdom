# React Index App - Alur Kerja

Dokumen ini mendokumentasikan **NexaJS untuk mobile — React Native (Expo)** di folder ini: alur dari titik masuk sampai rendering layar (`index.js`, `App.js`, modul terkait). Ini jalur **app**, bukan dokumentasi alur web browser.

## Daftar Isi

- [Arsitektur Singkat](#arsitektur-singkat)
- [Struktur proyek NexaJS React Native (Expo)](#struktur-proyek-nexajs-react-native-expo)
- [Alur Eksekusi Saat App Start](#alur-eksekusi-saat-app-start)
- [Detail Flow index.js](#detail-flow-indexjs)
- [Detail Flow App.js](#detail-flow-appjs)
- [Detail Flow AppNavigator](#detail-flow-appnavigator)
- [Konfigurasi `config.js`](#konfigurasi-configjs)
- [Folder `assets/modules` (NexaJS)](#folder-assetsmodules-nexajs)
- [NexaJS mobile dan referensi situs](#nexajs-mobile-dan-referensi-situs)
- [Memulai dengan impor `NexaJS`](#memulai-dengan-impor-nexajs)
- [Flow Route di templates/routes.js](#flow-route-di-templatesroutesjs)
- [Cara Membuat Route Baru](#cara-membuat-route-baru)
- [Hubungan navigate() dengan Route](#hubungan-navigate-dengan-route)
- [Catatan Operasional](#catatan-operasional)
- [Ringkasan](#ringkasan)

## Arsitektur Singkat

| Peran | Lokasi |
|--------|--------|
| Entry point Expo | `index.js` |
| Root React | `App.js` |
| Navigator + bootstrap app | `assets/modules/navigation/AppNavigator.js` |
| Peta route & warna dinamis | `templates/routes.js` |
| Host API, endpoint, Firebase | `config.js` (root proyek) |
| Paket runtime & UI NexaJS | `assets/modules/` (lihat [di bawah](#folder-assetsmodules-nexajs)) |

Struktur mengikuti pola modul NexaJS di `assets/modules`, dengan layar dan konfigurasi navigasi di `templates`.

## Struktur proyek NexaJS React Native (Expo)

Akar proyek ini adalah **workspace Expo**; kode aplikasi memisahkan **SDK NexaJS** (`assets/modules`), **konfigurasi server** (`config.js`), dan **layar + rute** (`templates/`).

```
Development/React/index/          # root (nama folder bisa berbeda di mesin Anda)
├── index.js                        # entry Expo: registerRootComponent
├── App.js                          # root React → AppNavigator
├── config.js                       # host API, Firebase — disinkron ke NEXA
├── app.json                        # nama app, slug, plugin Expo
├── babel.config.js
├── metro.config.js
├── package.json                    # termasuk "NexaJS": "file:./assets/modules"
├── assets/
│   ├── images/                     # aset gambar statis (opsional)
│   └── modules/                    # NexaJS: paket npm lokal (barrel index.js)
│       ├── package.json            # name: "NexaJS", main: index.js
│       ├── index.js
│       ├── Nexa.js                 # syncNexaFromConfig → global NEXA
│       ├── navigation/             # AppNavigator, dll.
│       ├── Storage/                # NexaDBLite, NexaModels, sync, …
│       ├── Form/, Fonts/, Properti/, …
│       └── …
├── templates/                      # layar aplikasi & definisi route
│   ├── routes.js                   # getRoutes / stack screens
│   ├── Home.js, Uid.js, halaman.js, …
│   ├── application/                # fitur aplikasi (tabs, helper)
│   ├── components/                 # grup layar komponen
│   ├── oauth/                      # alur masuk / daftar / profil OAuth
│   ├── exsampel/                   # contoh pemakaian NexaJS
│   ├── screens/                    # layar tambahan (mis. HomeScreen, DetailScreen)
│   └── …
└── node_modules/                   # dependensi npm (termasuk symlink NexaJS → assets/modules)
```

| Lokasi | Peran |
|--------|--------|
| Root (`index.js`, `App.js`) | Titik masuk Expo dan root React; tidak berisi logika bisnis besar. |
| `config.js` | Satu tempat mengatur URL backend & Firebase untuk modul yang memakai `NEXA`. |
| `assets/modules/` | **NexaJS**: komponen bersama, storage, Firebase, navigasi bootstrap, impor `from "NexaJS"`. |
| `templates/` | **Aplikasi Anda**: screen, route, contoh, modul oauth/application — impor NexaJS dari paket atau path relatif. |
| `app.json` / `metro.config.js` | Konfigurasi build & bundler Expo/Metro. |

Folder native **`android/`** / **`ios/`** muncul setelah **`expo prebuild`** atau `expo run:android` / `run:ios`; tidak wajib ada di setiap clone jika Anda hanya memakai Expo Go atau belum membangun proyek native.

## NexaJS mobile dan referensi situs

**Untuk pengguna Nexa:** yang dibahas di README ini adalah **NexaJS versi mobile (React Native / Expo)** — paket npm **`NexaJS`** → folder **`assets/modules`**, impor `import { … } from "NexaJS"` (lihat [Memulai dengan impor `NexaJS`](#memulai-dengan-impor-nexajs)). Entry app: **`index.js`** (Expo) dan **`App.js`**, bukan `index.html` atau pola SPA web.

NexaJS sebagai nama produk juga dipakai di jalur lain (web, desktop). Itu **bukan** isi dokumen ini. Jika Anda mencari panduan tambahan **selain** README ini untuk jalur yang sama, gunakan dokumentasi situs **`/docs/platform/react`**. Panduan **web** NexaJS ada di **`/docs/platform/javascript`** — stack dan entry berbeda dari app di folder ini; jangan dicampuradukkan dengan alur Expo di sini.

## Folder `assets/modules` (NexaJS)

**`assets/modules`** adalah **inti pustaka / SDK klien NexaJS** untuk app React Native (Expo) ini: satu area kode yang menggabungkan runtime, komponen, penyimpanan, dan integrasi backend — **bukan** konfigurasi bawaan Expo, melainkan **kode aplikasi** yang dipakai layar di `templates/` lewat import.

| Bagian | Fungsi singkat |
|--------|----------------|
| **`index.js`** (barrel) | Titik impor terpusat: mengekspor React/RN, form, tombol, storage (`NexaDBLite`, `NexaDb`, IndexedDB wrapper, Firebase, sync, …), **`NEXA`** / **`syncNexaFromConfig`**, **`properti`**, validasi, komponen mirip HTML, dll. Memanggil **`syncNexaFromConfig(Server)`** saat dimuat dan menginisialisasi **`NexaDb`** (IndexedDB) secara otomatis. |
| **`Nexa.js`** | Menyinkronkan `config.js` ke objek global **`NEXA`**. |
| **`navigation/AppNavigator.js`** | Navigator utama (React Navigation); di-mount dari `App.js`. |
| **`Storage/`**, **`Properti/`**, **`Form/`**, **`Fonts/`**, … | Implementasi konkret: SQLite ringan, sync, model API, tema dari server, UI, ikon, dll. |

**Cara yang disarankan untuk layar di `templates/`:** satu impor dari paket npm **`NexaJS`** (lihat [Memulai dengan impor `NexaJS`](#memulai-dengan-impor-nexajs)). Alternatif: path relatif **`../assets/modules`** atau subpath file (mis. navigator) jika perlu.

## Memulai dengan impor `NexaJS`

NexaJS membedakan workflow ini dari “impor React Native dan tiap komponen dari path panjang”: **satu nama paket** memuat seluruh barrel di `assets/modules/index.js`, sehingga Anda menggabungkan banyak simbol dalam **satu pernyataan `import`**.

### Hubungan npm ↔ folder

| File | Isi relevan |
|------|----------------|
| **`package.json`** (root proyek) | Dependensi `"NexaJS": "file:./assets/modules"` — Metro/npm mengarahkan paket **`NexaJS`** ke folder **`assets/modules`**. |
| **`assets/modules/package.json`** | `"name": "NexaJS"`, `"main": "index.js"` — entry resmi paket adalah **`assets/modules/index.js`**. |

Setelah `npm install`, impor `from "NexaJS"` selalu menyelesaikan ke **`assets/modules/index.js`**. Tidak perlu alias Babel tambahan untuk pola ini.

### Contoh (disarankan)

```js
import { View, StyleSheet, Text, Avatar, Buttons, NexaDBLite, properti } from "NexaJS";
```

Ini setara secara fungsi dengan mengimpor banyak modul terpisah, tetapi **lebih singkat** dan konsisten di seluruh proyek. Contoh nyata: `templates/exsampel/Avatar.js` memakai `import { View, StyleSheet, Avatar } from "NexaJS"`.

### Matriks kelompok ekspor (cuplikan)

Semua nama di bawah diekspor dari **`assets/modules/index.js`**; daftar penuh ada di file tersebut (bisa bertambah seiring pengembangan).

| Kelompok | Contoh nama yang bisa diimpor bersama |
|----------|----------------------------------------|
| React & React Native | `React`, `useState`, `useEffect`, `View`, `Text`, `StyleSheet`, `TouchableOpacity`, `ScrollView`, `FlatList`, `Platform`, `Modal`, `Alert`, … |
| Navigasi | `useNavigation`, `useFocusEffect` |
| Ikon & grafik | `FeatherIcon`, `FontAwesome`, `SvgXml`, `Svg`, `svgContent` |
| Form & UI Nexa | `Input`, `Switch`, `RichTextEditor`, `SelectList`, `Header`, `Buttons`, `ButtonsAction`, `BtnTabs`, `CustomButton`, `Loader`, `Spinner`, `Avatar`, `Images`, … |
| Layout & util | `Grid`, `Colors`, `fs`, `Icon`, `SymbolsIcon`, `QRCodeGenerator`, `Carousel`, `Toast`, `ToastContainer`, … |
| Penyimpanan & sync | `NexaDBLite`, `NexaDb`, `nexaDb`, `IndexedDBManager`, `AsyncStorage`, `NexaSync`, `Storage`, `NexaStores`, `NexaModels`, … |
| Firebase | `NexaFirestore`, `firebaseData`, `initNexaFirebase`, `FirebaseConfig`, … |
| Runtime & config | `NEXA`, `syncNexaFromConfig`, `Server`, `properti`, `Properti` |
| Komponen mirip HTML | `Div`, `P`, `Span`, `HtmlButton`, `H1`, `H2`, `Section`, … |
| Validasi & media | `validateInput`, `useFormValidation`, `pickImage`, `pickCamera`, `convertToBase64`, … |

Jika nama yang Anda butuhkan belum diekspor dari barrel, tambahkan ekspornya di **`assets/modules/index.js`** (satu tempat), lalu impor dari **`NexaJS`** seperti biasa.

### Kapan path relatif tetap dipakai

- **`App.js`** mengimpor navigator dengan path relatif ke file tunggal: `./assets/modules/navigation/AppNavigator` (boleh juga diseragamkan ke subpath paket jika nanti didukung).
- Kode **di dalam** `assets/modules/` sering memakai impor relatif antar file di folder yang sama — itu internal SDK, bukan pola layar aplikasi.

## Alur Eksekusi Saat App Start

1. **`index.js`**: mengganti `console.log` dengan wrapper yang menyaring log bising (Expo/AppRegistry, modul Expo, NexaDBLite contoh), lalu memanggil `registerRootComponent(App)` dari Expo.
2. **`App.js`**: dirender sebagai root; hook `useNexaPreviewReadySignal()` mengirim pesan ke parent window jika app berjalan di iframe (pratinjau Nexa/Electron).
3. **`App.js`**: merender satu child: `<AppNavigator />` (import dari `./assets/modules/navigation/AppNavigator`).
4. **`AppNavigator`**: memuat font, data properti, route dinamis, dan memeriksa sesi user; setelah siap, menampilkan `NavigationContainer` + stack screens.

Modul yang mengimpor `config.js` (misalnya `assets/modules/Nexa.js`) ikut ter-load saat bundle memuat dependensi tersebut — bukan dari `index.js` secara eksplisit, tetapi menjadi sumber URL untuk API dan integrasi Firebase. Lihat [Konfigurasi `config.js`](#konfigurasi-configjs).

## Detail Flow `index.js`

- Mengimpor `registerRootComponent` dari `expo` dan komponen default dari `./App`.
- **Filter log**: `console.log` diganti agar tidak mencetak pesan yang dianggap berisik, antara lain:
  - AppRegistry: teks mengandung `Running "main" with`
  - Expo: `expo-file-system` (legacy/loaded), `expo-sqlite loaded`, `expo-camera berhasil diimport`
  - NexaDBLite: baris pembuka database/tabel (`[NexaDBLite] Database` / `Table` … `opened` / `ensured`)
  - Contoh: `[NexaDBLiteExample]`, `[NexaDbFirebaseExample]`
- Log lain tetap diteruskan ke `console.log` asli.
- **`registerRootComponent(App)`**: mendaftarkan root component untuk Expo Go dan build native; ini setara dengan pola `AppRegistry.registerComponent('main', …)` yang dijelaskan di komentar internal Expo.

## Detail Flow `App.js`

- Mengimpor `AppNavigator` dari `./assets/modules/navigation/AppNavigator`.
- **`useNexaPreviewReadySignal()`** (di dalam `App`):
  - Hanya jalan di lingkungan ada `window`.
  - Setelah **dua** `requestAnimationFrame` berurutan, jika `window.parent !== window`, mengirim `postMessage({ type: "nexa-expo-preview-ready", v: 1 }, "*")` ke parent.
  - Tujuannya: memberi tahu jendela pratinjau (mis. Electron) bahwa UI React sudah siap, sehingga splash iframe bisa disembunyikan tepat setelah frame pertama, bukan hanya setelah event load iframe.
- Render: `return <AppNavigator />` — tidak ada provider tambahan di root selain itu.

## Detail Flow `AppNavigator`

File: `assets/modules/navigation/AppNavigator.js`. Ketergantungan utama: `getRoutes` dari `../../../templates/routes`, `useMontserratFonts` / `FontFamily` dari `../Fonts/Montserrat`, `NexaDBLite` dan `properti` dari `../index`.

### Bootstrap data & route

- **`loadAndUpdateRoutes`**: memanggil `properti.get()`, menyimpan `propertiData` dan warna (`assetColor` dari data atau `properti.getAssetColor()`), lalu `setRoutes(getRoutes(color, data))`. Jika error, fallback `getRoutes()` tanpa argumen.
- **Saat mount**: `useEffect` memanggil `loadAndUpdateRoutes()` sekali.
- **Polling properti**: setelah `propertiData` ada, interval **3 detik** membandingkan `updatedAt`; jika berubah, memanggil `loadAndUpdateRoutes` lagi.
- **App kembali aktif**: listener `AppState` saat state `active` memicu refresh route yang sama.

### Sesi login & route awal

- **`NexaDBLite.get("userSessions", "userSession")`** dengan retry **2×**, tiap percobaan dibatasi **5 detik** (`Promise.race` dengan timeout). Jeda antarretry **300 ms**.
- **Timeout keseluruhan pemeriksaan sesi: 10 detik** — jika lewat, route awal dipaksa `"Home"` dan pengecekan dianggap selesai.
- Jika sesi ada dan `userSession.isLoggedIn` truthy → `initialRoute` = `"User"`; jika tidak → `"Home"`. Error DB: fallback `"Home"`.
- State `isCheckingSession` dicegah mengunci UI tanpa batas (timeout + error path mengatur `false`).

### Status bar & navigasi

- Efek terpisah menyelaraskan `StatusBar` dengan opsi route (termasuk delay singkat untuk menghindari override).
- Listener **state** pada `navigationRef` memperbarui StatusBar saat route berganti; untuk route **User** ada juga penyegaran berinterval **2 detik** agar style tidak tertimpa.
- **`Stack.Navigator`**: `initialRouteName={initialRoute}`; `key={propertiData?.updatedAt || 'default'}` agar navigator ikut di-mount ulang saat properti berubah.
- Setiap **`Stack.Screen`** memakai `key` yang mengandung `propertiData?.updatedAt` untuk konsistensi re-render saat tema/properti berubah.
- **Opsi screen**: jika `route.options` berupa fungsi, dipanggil dengan props navigasi; judul header memakai `FontFamily.semiBold`.

### Kapan tidak ada UI

Komponen mengembalikan **`null`** (belum ada layar loading terpisah) selagi:

- font belum siap (`!fontsLoaded`), atau
- pemeriksaan sesi belum selesai (`isCheckingSession`), atau
- array `routes` masih kosong.

Setelah ketiga syarat terpenuhi, stack dirender.

## Konfigurasi `config.js`

File **`config.js`** di root proyek mengatur host backend Nexa/PHP, turunan URL API, dan kredensial Firebase untuk modul yang membutuhkannya.

### Isi utama

- **`hosts`**: string basis (misalnya `http://IP:port`) — disesuaikan dengan mesin yang menjalankan server Nexa/PHP di jaringan Anda.
- Objek **`Server`** (export default):
  - **`url`**: sama dengan `hosts` — origin aplikasi web/server.
  - **`urlApi`**, **`API_URL`**: `hosts + "/api"` — basis REST API.
  - **`API_Models`**: `hosts + "/api/models"`.
  - **`FILE_URL`**: sama dengan `hosts` — basis aset file.
  - **`drive`**: `hosts + "/assets/drive"`.
  - **`rebit`**, **`typicode`**: endpoint tambahan (contoh layanan lain / placeholder JSON).
  - **`firebaseConfig`**: objek konfigurasi Firebase Web (diisi dari **`FirebaseConfig`** di file yang sama).
- **`FirebaseConfig`**: objek standar Firebase (`apiKey`, `authDomain`, `databaseURL`, `projectId`, `storageBucket`, `messagingSenderId`, `appId`, `measurementId`).

### Ekspor

```js
export default Server;
export { FirebaseConfig };
```

### Hubungan ke runtime `NEXA`

**`assets/modules/Nexa.js`** mengimpor default dari `../../config` (`config.js`), lalu memanggil `syncNexaFromConfig(Server)`:

- Mengisi **`globalThis.NEXA`** (atau `global` / `window` sesuai lingkungan).
- Menggabungkan isi `Server` ke **`NEXA.endpoint`**, lalu menyalin setiap properti endpoint (kecuali kunci internal seperti `url`, `urlApi`, `apiBase`, `controllers`, `userId`) ke **properti top-level** `NEXA`, sehingga pola seperti `NEXA.drive` atau `NEXA.firebaseConfig` bisa dipakai di kode yang mengimpor `NEXA`.

Modul lain yang mengimpor langsung dari `config.js` antara lain:

- **`assets/modules/NexaFirebase.js`**: `{ FirebaseConfig }` untuk inisialisasi Firebase/Firestore.
- **`assets/modules/Storage/NexaModels.js`**: default `Server` untuk URL model/API.

### Praktik

- Ubah **`hosts`** (dan endpoint turunan jika struktur path server Anda berbeda) agar IP/port cocok dengan backend yang diuji (perangkat fisik, emulator, atau LAN).
- Untuk produksi atau repo publik, pertimbangkan memindahkan kunci sensitif ke variabel lingkungan atau file yang tidak di-commit; jangan mengandalkan nilai default di repositori tanpa meninjau kebijakan keamanan proyek Anda.

## Flow Route di `templates/routes.js`

- **`getRoutes(assetColor, propertiData)`**: jika `assetColor` atau `propertiData` diset, memanggil `getRoutesWithColor(...)`; jika tidak, mengembalikan export statis `routes` (default).
- **`getRoutesWithColor`** mendefinisikan screen inti:
  - `Home` → `HomeScreen`
  - `User` → `UID` (`Uid.js`)
  - `page` → `halaman`
- Route modul digabung dengan spread:
  - `PageOauth`, `PageComponents`, `PageApplication` lewat `updateRoutesWithColor(...)` (header/status bar mengikuti warna)
  - `MenuComponents` disisipkan apa adanya (tanpa helper warna yang sama)
- Helper seperti `getStatusBarStyle`, `updateRoutesWithColor`, dan `getDefaultHeaderStyle` mendukung tema konsisten.

## Cara Membuat Route Baru

### 1) Buat file screen

Contoh: `templates/MyScreen.js` dengan `export default` komponen layar.

### 2) Import screen ke `templates/routes.js`

```js
import MyScreen from "./MyScreen";
```

### 3) Tambahkan object route ke array di `getRoutesWithColor()`

```js
{
  name: "MyScreen",
  component: MyScreen,
  options: {
    title: "My Screen",
    headerShown: true,
    statusBar: {
      style: "dark",
      backgroundColor: "#FFFFFF",
    },
  },
},
```

- `name`: unik; dipakai di `navigation.navigate("MyScreen")`.
- `component`: komponen layar.
- `options`: header, `statusBar`, dll.

### 4) Route dari modul (banyak screen)

Ikuti pola spread yang sudah ada:

```js
...updateRoutesWithColor(PageComponents, colors, statusBarStyle)
```

### 5) Panggil dari screen lain

```js
navigation.navigate("MyScreen");
```

### 6) Validasi cepat

- Pastikan `name` unik.
- Pastikan path import benar.
- Restart bundler jika Metro masih cache path lama.

## Hubungan `navigate()` dengan Route

Di screen, contoh:

```js
navigation.navigate("Detail", {
  type: "settings",
  section: "notification",
  isEnabled: true,
});
```

- `"Detail"` harus ada sebagai `name` di `templates/routes.js`.
- Objek kedua menjadi `route.params` di layar tujuan.

Contoh definisi route:

```js
{
  name: "Detail",
  component: DetailScreen,
  options: {
    title: "Detail",
    headerShown: true,
  },
},
```

Tanpa route terdaftar, navigasi ke nama itu akan gagal.

## Catatan Operasional

- Import navigator: `./assets/modules/navigation/AppNavigator` (relatif dari `App.js`).
- Import route: di `AppNavigator`, `getRoutes` dari `../../../templates/routes` (ekivalen file `templates/routes.js`).
- **`config.js`** berada di root; modul di `assets/modules/` mengimpor dengan `../../config` atau `../../../config` sesuai kedalaman folder.
- Setelah refactor path besar, restart bundler untuk membersihkan cache resolver Metro/Expo.

## Ringkasan

Alur end-to-end:

`index.js` (filter log → `registerRootComponent`) → **`App.js`** (`useNexaPreviewReadySignal` + **`AppNavigator`**) → **`getRoutes(...)`** dari **`templates/routes.js`** → **`NavigationContainer`** + stack screens.

`config.js` menyediakan URL backend dan Firebase; **`assets/modules/Nexa.js`** menyinkronkannya ke objek global **`NEXA`** untuk pemanggilan di seluruh app.

Layar memakai **`import { … } from "NexaJS"`** (paket lokal `file:./assets/modules`) agar satu pernyataan mencakup RN, komponen Nexa, storage, dan `properti` — tanpa rantai impor file demi file.

`AppNavigator` mengorkestrasi font, properti (polling 3 detik), sesi (NexaDBLite + timeout/retry), pembaruan route saat app aktif kembali, dan StatusBar mengikuti route serta properti.