# Nexa Node (Express + NexaJS SPA)

Proyek ini menjalankan **frontend NexaJS** (SPA) dengan **Express**, dan mengarahkan **API PHP** serta **aset drive** lewat **reverse proxy** agar browser tetap **same-origin** dengan origin SPA (`http://localhost:5000`). Konfigurasi tidak diekspos publik lewat `GET /config.js`; endpoint disuntik ke HTML sebagai `window.__NEXA_ENDPOINT__`.

## Persyaratan

- **Node.js** (disarankan LTS 18+)
- **npm** atau **pnpm**
- Backend PHP (Nexa/API) dapat diakses dari mesin yang menjalankan Node (jaringan lokal atau `localhost`)

## Instalasi

```bash
cd Framework/Testing/node
npm install
```

`postinstall` menjalankan **patch-package** untuk patch kecil pada dependensi `http-proxy` (menghindari peringatan deprecasi `util._extend`).

## Menjalankan server

```bash
npm start
```

- Port dan host default diambil dari **`config.js`** (`config.url`).
- **Override port** lewat env: `PORT=5000` (Windows PowerShell: `$env:PORT=5000; npm start`). Jika `PORT` diset, `buildServerConfig()` mengikat ke `127.0.0.1` dan `baseUrl` berbentuk `http://127.0.0.1:<PORT>` — **buka aplikasi dengan host yang sama** dengan yang dipakai `sendSpaHtml` (header `Host`), atau akses lewat `localhost` agar injeksi `__NEXA_ENDPOINT__` selaras dengan origin browser.

Buka aplikasi di browser: `http://localhost:5000` (sesuaikan dengan `config.url`).

## File konfigurasi: `config.js`

| Properti | Peran |
|----------|--------|
| **`url`** | Origin SPA (Express). Harus sama port dengan proses yang benar-benar listen. |
| **`urlApi`** | URL **backend** API PHP (boleh host lain, mis. `http://192.168.1.5/api`). Digunakan server untuk **target proxy**; browser **tidak** memanggil URL ini langsung jika beda origin — lihat bagian proxy. |
| **`drive`** | URL basis aset media (mis. `http://…/assets/drive`). Dipakai untuk gambar dan path proxy `/assets/drive`. |
| **`typicode`**, **`firebaseConfig`** | Endpoint / flag tambahan untuk Nexa (opsional). |

**Catatan:** `config.js` tidak dilayani sebagai file statis publik; **GET** ke nama file sensitif (`config.js`, `server.js`, dll.) diblokir.

## Alur proxy (API & drive)

1. **Browser** memanggil same-origin, mis. `http://localhost:5000/api/...` dan `http://localhost:5000/assets/drive/...`.
2. **Express** mem-proxy ke host di `urlApi` / `drive` jika **origin backend ≠ origin listener** (SPA).
3. **`patchReqUrlToOriginal`** dijalankan sebelum proxy: `req.url = req.originalUrl` supaya path ke upstream tetap **lengkap** (`/api/outside` bukan `/outside` setelah mount Express memangkas prefix).
4. **`on.proxyRes`**: header **`Location`** / **`Content-Location`** dari backend yang mengarah ke host PHP (mis. `http://192.168.1.5/...`) ditulis ulang ke **origin yang dipakai browser** agar tidak terjadi redirect lintas origin + error CORS.

## Injeksi endpoint ke browser

- **`index.html`** (dan fallback SPA) disisipkan skrip:  
  `window.__NEXA_ENDPOINT__ = { ... }`  
  objek disusun dari `config` + `SERVER_CONFIG` + header `Host` (agar `localhost` vs `127.0.0.1` konsisten dengan tab browser).
- **`App.js`** membaca `globalThis.__NEXA_ENDPOINT__` — **bukan** `import` `config.js`. Membuka file HTML lokal (`file://`) tanpa server akan gagal.

## Aplikasi klien (NexaJS)

- **`App.js`**: `NXUI.Tatiye` dengan `endpoint: config` dari `__NEXA_ENDPOINT__`, route, `appRoot: 'templates'`, dll.
- **`NEXA.apiBase`**: basis API (biasanya `…/api`) — dipakai **NexaStorage** untuk `…/api/outside` (paket PHP).
- **Template route**: `templates/*.js` (mis. `blog.js`) memanggil `NXUI.Storage()` untuk data backend.

## Endpoint bantuan (Node, bukan PHP)

- **`GET /health`** — health check.
- **`GET /nexa-dev/status`**, **`/nexa-dev/info`** — status server; tidak memakai prefix `/api` agar tidak bentrok dengan proxy PHP.

## Struktur direktori (ringkas)

```
├── server.js          # Express, proxy, SPA inject
├── config.js          # Konfigurasi server + referensi upstream
├── App.js             # Entry NexaJS (module)
├── index.html
├── assets/            # Modul NexaJS, CSS, JS
├── templates/         # Route SPA (blog.js, …)
└── package.json
```

## Troubleshooting

| Gejala | Kemungkinan |
|--------|--------------|
| **CORS** setelah redirect | Backend mengembalikan `Location` absolut ke IP PHP; pastikan `on.proxyRes` aktif (v3: `options.on.proxyRes`, bukan `onProxyRes` di root). |
| Request ke **`…/outside`** bukan **`…/api/outside`** | Pastikan middleware **`patchReqUrlToOriginal`** dijalankan sebelum proxy; jangan mount proxy tanpa itu. |
| **`NEXA.apiBase` tidak selaras dengan tab** | Buka dengan host yang sama dengan `Host`/`config.url`; hindari campur `localhost` dan `127.0.0.1` untuk origin SPA. |
| **Port sudah dipakai** | Tutup proses lain di port tersebut atau ubah `config.url` + `PORT`. |

## Lisensi / versi

Lihat `package.json` (`express-nexa`, versi 1.0.0). Modul NexaJS di `assets/` mengikuti kebijakan proyek induk Framework.
