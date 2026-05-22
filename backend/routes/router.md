# Dokumentasi Router - Nexa Dom Framework v2.0

Dokumentasi sistem routing yang menghubungkan file route (`web.php`, `api.php`) dengan controller utama (`AdminController`, `FrontendController`, `ApiController`).

---

## 0. Bootstrap

Alur dari `index.php`: `Nexa::getInstance()` → `Tatiye()` (memuat `web.php` dan `api.php` via `loadRoutes()`) → `run()` → `NexaRouter::handle()`.

## 1. Ringkasan Alur Routing

```
Request masuk
    │
    ├─► Session Routing (user login & first segment = username)
    │       └─► AdminController
    │
    ├─► Frontend Routing (public pages, unregistered routes)
    │       └─► FrontendController
    │
    └─► Regular Routing (web.php + api.php)
            └─► Handler yang terdaftar (Controller@action)
```

**Urutan prioritas** di `NexaRouter::handle()`:
1. **trySessionBasedRoutingOptimized** → AdminController (dashboard user)
2. **tryFrontendRouting** → FrontendController (halaman publik), dengan pengecualian: jika URI sudah cocok dengan route terdaftar di `web.php`, langkah ini mengembalikan false sehingga yang berjalan adalah langkah 3; khusus **`/`** ditangani **handleFrontendRoot** → `FrontendController@index` sebelum loop reguler
3. **Regular routing** → Cocokkan dengan route terdaftar di `web.php` & `api.php`

---

## 2. Load Route Files

Route dimuat di `system/Nexa.php` via `loadRoutes()`:

```php
// Urutan load: web.php dulu, lalu api.php
require $this->config['paths']['routes'] . 'web.php';
require $this->config['paths']['routes'] . 'api.php';
```

Variabel `$router` disediakan ke kedua file sehingga semua route masuk ke satu instance yang sama.

---

## 3. Web Routes (`web.php`)

### 3.1 Route yang Terdaftar

| Path | Handler | Keterangan |
|------|---------|------------|
| `/` | `FrontendController@index` | Home root |
| `/home` | `FrontendController@index` | Home |
| `/logout` | `OauthController@logout` | Logout |
| `/signin` | `OauthController@signin` | Login |
| `/signup` | `OauthController@signup` | Daftar |
| `/office` | `ExcelExampleController@index` | Excel example |
| `/drive/{params}` | `DriveController@index` | File drive (assets/drive/) |
| `/images/{params}` | `ImagesController@index` | Gambar (assets/images/) |
| `/{Y}/{params}` | `Frontend/BlogController@detail` | Blog detail (tahun/slug) |
| `/docs` | *(opsional — di `web.php` saat ini dikomentari)* | Bisa didaftarkan ke `DocsController@index` |
| `/docs/{params}` | *(opsional — dikomentari)* | Topik dokumentasi dinamis |
| `/debug` | `DebugController@index` | Debug |
| `/debug/{params}` | `DebugController@index` | Debug dengan params |
| `/eventload` | `FileController@eventload` | Event load |
| `/eventMarkdownload` | `FileController@eventMarkdownload` | Mark download |
| `/file/{params}` | `FileController@index` | File serving |
| `/app/{params}` | `JsController@index` | NexaJs app routes |

**Route template assets** (didaftarkan otomatis di `NexaRouter`, tidak di `web.php`):
- `/assets/{params}` → `TemplateController@assets` (auto-detect template via cookie/User-Agent)
- `/theme/`, `/mobile/`, `/tablet/`, `/dashboard/` → `TemplateController@index`

### 3.2 Penting

- Route spesifik harus didaftarkan sebelum route generic (mis. `/drive/{params}` sebelum catchall).
- Jika path cocok dengan route di `web.php`, **Frontend Routing tidak dipakai**—langsung ke regular routing.

---

## 4. API Routes (`api.php`)

### 4.1 Route yang Terdaftar

| Path | Handler | Method | Keterangan |
|------|---------|--------|------------|
| `/api/google-signup` | `Api/GoogleAuthController@signup` | POST | Google signup |
| `/api/google-signin` | `Api/GoogleAuthController@signin` | POST | Google signin |
| `/api/google-test` | `Api/GoogleAuthController@test` | GET | Test Google auth |
| `/api/` | `ApiController@index` | ANY | Generic API |
| `/api/{params}` | `ApiController@index` | ANY | Generic API dengan params |

### 4.2 Urutan Route

Route spesifik (Google Auth) harus di atas route generic (`/api/`, `/api/{params}`) agar cocok duluan.

---

## 5. AdminController

**File:** `controllers/AdminController.php`  
**Namespace node:** `App\Controllers\Admin\`

### 5.1 Kapan Dipanggil

Saat **Session Routing** cocok:

- User sudah login.
- First segment URL sama dengan `user_slug` (username) di session.

### 5.2 Pola URL

| Pattern | Method | Action | Keterangan |
|---------|--------|--------|------------|
| `/{username}` | GET | `index()` | Dashboard index |
| `/{username}/{page}` | GET, POST | `page()` | Halaman dashboard |
| `/{username}/{page}/{method}` | GET, POST | `page()` | Halaman + method |

### 5.3 Node Controllers (App\Controllers\Admin\)

AdminController mem-forward ke controller di namespace `Admin\` sesuai `{page}`:

- `IndexController` (page=index)
- `AccountController`, `ThemeController`, `BeasiswaController`, `AdminBeasiswaController`
- `HomeController`, `SearchController`, `UserController`, dll.

### 5.4 Halaman yang Diizinkan Tanpa Cek Ketat

- `account`, `beasiswa`, `adminbeasiswa`, `theme`, `home`

### 5.5 Fitur Utama

- **trackUserActivity()**: update `last_seen`, `is_online`, `last_ip`.
- **dataGlobal()**: data umum (user, role, URL, dark mode, dll).
- **nodeController()**: eksekusi controller child sesuai `page`.

---

## 6. FrontendController

**File:** `controllers/FrontendController.php`  
**Namespace node:** `App\Controllers\Frontend\`

### 6.1 Kapan Dipanggil

Saat **Frontend Routing** cocok:

- Bukan route API (`/api`...).
- Path tidak membuat `tryFrontendRouting` melepaskan ke regular routing (lihat pengecekan route terdaftar di dalam `tryFrontendRouting`).
- **User belum login** — jika sudah login, `FrontendController::page()` mengalihkan ke `/{username}/{page?}` (dashboard), sehingga halaman publik dinamis tidak dieksekusi untuk sesi login.

### 6.2 Pola URL

| Pattern | Method | Action | Keterangan |
|---------|--------|--------|------------|
| `/` | GET | `index()` | Home |
| `/{page}` | GET, POST | `page()` | Halaman publik |
| `/{page}/{method}` | GET, POST | `page()` | Halaman + method |

### 6.3 Routing Khusus

- **ORD-*** (contoh: `/ORD-12345`): redirect ke dashboard jika login, atau ke `/home` jika belum.
- **seller/{username_toko}**: cek `toko` di DB, lalu ke `SellerController` dan template `seller/toko`.

### 6.4 Node Controllers (App\Controllers\Frontend\)

- `IndexController`, `SearchController`, `BlogController`
- `AboutController`, `DownloadController`, `ExampleController`, `JSController`

### 6.5 Fitur Utama

- **divert()**: tentukan device type untuk template.
- ** controllerExists()**: cek controller di namespace `Frontend\`.
- **callController()**: jalankan method pada controller child.

---

## 7. ApiController

**File:** `controllers/ApiController.php`  
**Namespace node:** `App\Controllers\Api\`

### 7.1 Kapan Dipanggil

Saat **Regular Routing** cocok dengan `/api/` atau `/api/{params}` (bila tidak tertangkap route spesifik di `api.php`).

### 7.2 Pola URL

| Pattern | Method | Keterangan |
|---------|--------|------------|
| `/api/` | ANY | Root API |
| `/api/{page}` | ANY | Controller = page |
| `/api/{page}/{method}` | ANY | Controller + method |

Contoh:

- `/api/user` → `Api\UserController@index`
- `/api/user/profile` → `Api\UserController@profile`

### 7.3 Format Params

- `page` dari `params['page']` atau `slug[2]`.
- Jika `page` mengandung `/`, di-parse sebagai `controller/method`.

### 7.4 Node Controllers (App\Controllers\Api\)

- `IndexController`, `UserController`, `AuthController`, `OauthController`
- `GoogleAuthController`, `WhatsappController`, `SettingController`, dll.

### 7.5 Fitur Utama

- **CORS**: header `Access-Control-Allow-*` untuk semua origin.
- **OPTIONS**: preflight dihandle langsung, response 200.
- **formatApiResponse()**: format response standar sesuai HTTP method.
- **nodeApiControllerData()**: eksekusi controller API dan return data.

---

## 8. Diagram Relasi

```
┌─────────────────────────────────────────────────────────────────┐
│                        NexaRouter::handle()                       │
└─────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        ▼                           ▼                           ▼
┌───────────────┐         ┌───────────────────┐         ┌──────────────┐
│ Session-based │         │ Frontend Routing   │         │ Regular      │
│ Routing       │         │                    │         │ Routing      │
│ (user login)  │         │ (public, unreg.)   │         │ (web+api)    │
└───────┬───────┘         └─────────┬───────────┘         └──────┬───────┘
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────┐         ┌───────────────────┐         ┌──────────────┐
│AdminController│         │FrontendController  │         │ web.php      │
│ /{user}/...   │         │ /{page}/...       │         │ api.php      │
└───────┬───────┘         └─────────┬───────────┘         └──────┬───────┘
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────┐         ┌───────────────────┐         ┌──────────────┐
│ Admin\       │         │ Frontend\         │         │ ApiController │
│ *Controller  │         │ *Controller      │         │ Api\*Controller│
└───────────────┘         └───────────────────┘         └──────────────┘
```

---

## 9. Menambah Route Baru

### Web (halaman/aksi khusus)

Tambahkan di `web.php`:

```php
$router->add('/path-baru', 'ControllerName@action');
// atau dengan method tertentu:
$router->get('/path-get', 'ControllerName@action');
$router->post('/path-post', 'ControllerName@action');
```

### API

Tambahkan di `api.php` (route spesifik di atas route generic):

```php
$router->add('/api/endpoint-spesifik', 'Api/MyController@method', ['POST']);
```

### Tanpa daftar route

- **Admin**: buat controller di `Admin\`; akses lewat `/{username}/{page}`.
- **Frontend**: buat controller di `Frontend\`; akses lewat `/{page}` (path belum terdaftar di `web.php`).
- **API**: buat controller di `Api\`; akses lewat `/api/{controller}/{method}`.

---

## 10. Referensi File

| File | Fungsi |
|------|--------|
| `routes/web.php` | Route web (halaman, auth, assets, docs, dll.) |
| `routes/api.php` | Route API (Google Auth + generic) |
| `controllers/AdminController.php` | Entry point dashboard user |
| `controllers/FrontendController.php` | Entry point halaman publik |
| `controllers/ApiController.php` | Entry point API generic |
| `system/NexaRouter.php` | Implementasi dispatch & routing logic |
| `system/Nexa.php` | Load route files |
