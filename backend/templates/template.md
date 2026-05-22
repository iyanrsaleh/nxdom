# Dokumentasi Templates & Assets - NexaUI Framework

Panduan penggunaan template dan asset di NexaUI. Sistem ini mendukung **template-aware asset resolution** — CSS, JS, fonts, dan images otomatis diambil dari folder template yang aktif (theme, mobile, tablet, dashboard).

---

## 1. Struktur Folder Templates

```
templates/
├── theme/           # Desktop (default)
│   ├── header.html
│   ├── footer.html
│   ├── index.html
│   └── assets/
│       ├── css/
│       ├── js/
│       ├── fonts/
│       └── images/
├── mobile/          # Perangkat mobile
│   ├── header.html
│   ├── footer.html
│   └── assets/
├── tablet/          # Perangkat tablet
│   └── assets/
└── dashboard/       # Halaman dashboard (setelah login)
    └── assets/
```

### Folder Root Assets

```
assets/
├── drive/           # File upload (PDF, gambar, dll)
│   └── avatar/
│       └── 2026/01/
└── modules/         # Public modules (JS libraries, CSS frameworks)
    ├── Nexa.js
    ├── Nexa.css
    ├── Select2/
    ├── Modal/
    └── ...
```

---

## 2. Resolusi Asset Otomatis

Sistem mendeteksi template aktif via **cookie** (saat render) atau **User-Agent**. Tidak perlu menulis `theme/`, `mobile/`, atau `tablet/` di path.

| URL | Resolusi |
|-----|----------|
| `/assets/css/style.css` | 1. `templates/{active}/assets/css/style.css` → 2. `assets/css/style.css` |
| `/assets/js/app.js` | 1. `templates/{active}/assets/js/app.js` → 2. `assets/js/app.js` |
| `/assets/fonts/font.woff2` | 1. `templates/{active}/assets/fonts/` → 2. `assets/fonts/` |
| `/images/logo.png` | 1. `templates/{active}/assets/images/logo.png` → 2. `assets/images/logo.png` |
| `/drive/avatar/2026/01/file.png` | `assets/drive/avatar/2026/01/file.png` (tetap di root) |
| `/modules/Nexa.js` | `assets/modules/Nexa.js` (public modules, tetap di root) |

---

## 3. Sintaks di Template HTML

### 3.1 CSS

```html
<!-- Direkomendasikan: dengan variabel (support subdirectory) -->
<link rel="stylesheet" href="{assets/css/style.css}">

<!-- Atau path langsung (untuk install di root) -->
<link rel="stylesheet" href="/assets/css/style.css">
```

### 3.2 JavaScript

```html
<script src="{assets/js/app.js}"></script>
<!-- atau -->
<script src="/assets/js/app.js"></script>
```

### 3.3 Fonts (@font-face)

```html
<style>
@font-face {
  font-family: 'CustomFont';
  src: url('{assets/fonts/CustomFont.woff2}') format('woff2'),
       url('{assets/fonts/CustomFont.woff}') format('woff');
}
</style>
```

### 3.4 Images

```html
<!-- Gambar dari template assets -->
<img src="/images/logo.png" alt="Logo">
<img src="{img/logo.png}" alt="Logo">

<!-- Gambar upload (drive) -->
<img src="/drive/avatar/2026/01/pria_xxx.png" alt="Avatar">
<img src="{drive/avatar/2026/01/file.png}" alt="File">
```

### 3.5 Modules (Public Libraries)

```html
<!-- JavaScript modules dari assets/modules/ -->
<script src="/modules/Nexa.js"></script>
<script src="{modules/Nexa.js}"></script>

<!-- CSS modules -->
<link rel="stylesheet" href="/modules/Nexa.css">
<link rel="stylesheet" href="{modules/Nexa.css}">

<!-- Modules dengan subdirectory -->
<script src="/modules/Select2/select2.min.js"></script>
<link rel="stylesheet" href="{modules/Modal/modal.css}">
```

### 3.6 Format Ekstensi yang Didukung

| Tipe | Ekstensi |
|------|----------|
| CSS | `.css` |
| JS | `.js` |
| Fonts | `.woff`, `.woff2`, `.ttf`, `.eot`, `.otf` |
| Images | `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`, `.svg`, `.ico` |
| Lainnya | `.json`, `.map`, `.html`, `.txt`, `.md` |

---

## 4. NexaAsset (PHP)

### 4.1 Template-aware (short form)

```php
// Otomatis resolve ke templates/{active}/assets/
NexaAsset::asset('css/style.css');   // → /assets/css/style.css
NexaAsset::asset('js/app.js');       // → /assets/js/app.js
NexaAsset::asset('fonts/font.woff2'); // → /assets/fonts/font.woff2
```

### 4.2 Images (template atau upload)

```php
// Resolusi: templates/{active}/assets/images/ → assets/images/
NexaAsset::img('logo.png');              // → /images/logo.png
NexaAsset::img('2026/01/photo.jpg');     // → /images/2026/01/photo.jpg

// Resize on-the-fly (via ImagesController)
NexaAsset::img('photo.jpg') . '?w=300&h=200';
```

### 4.3 Drive (file upload)

```php
NexaAsset::drive('avatar/2026/01/file.png');  // → /drive/avatar/2026/01/file.png
NexaAsset::driveBase();                       // → /drive
```

### 4.4 Modules (public libraries)

```php
NexaAsset::modules('Nexa.js');                // → /modules/Nexa.js
NexaAsset::modules('Select2/select2.min.js'); // → /modules/Select2/select2.min.js
NexaAsset::modules('Modal/modal.css');        // → /modules/Modal/modal.css
```

### 4.5 Per-template (explicit)

```php
NexaAsset::theme('assets/css/theme.css');     // → /theme/assets/css/theme.css
NexaAsset::mobile('assets/css/mobile.css');   // → /mobile/assets/css/mobile.css
NexaAsset::tablet('assets/css/tablet.css');   // → /tablet/assets/css/tablet.css
NexaAsset::dash('style.css');                // → Dashboard template
```

---

## 5. Device Detection

Template aktif ditentukan otomatis:

| Device | Template | Kapan |
|--------|----------|-------|
| Desktop | `theme` | User-Agent desktop, atau cookie `nexa_template` |
| Mobile | `mobile` | User-Agent mobile |
| Tablet | `tablet` | User-Agent tablet |
| Dashboard | `dashboard` | User login, halaman `/{username}/...` |

Cookie `nexa_template` di-set saat halaman di-render sehingga request asset berikutnya memakai template yang sama.

---

## 6. Contoh Lengkap

### theme/header.html

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contoh Aplikasi</title>

  <link rel="stylesheet" href="{assets/css/style.css}">
  <link rel="stylesheet" href="{modules/Nexa.css}">
  <link rel="preload" href="{assets/fonts/CustomFont.woff2}" as="font" crossorigin>
</head>
<body>
  <header>
    <img src="/images/logo.png" alt="Logo">
    <!-- ... -->
  </header>
```

### theme/footer.html

```html
  <script src="{modules/Nexa.js}"></script>
  <script src="{assets/js/app.js}"></script>
</body>
</html>
```

---

## 7. Ringkasan Route

| Path | Controller | Lokasi File |
|------|-------------|-------------|
| `/assets/*` | TemplateController@assets | `templates/{active}/assets/` atau `assets/` |
| `/theme/*` | TemplateController@index | `templates/theme/` |
| `/mobile/*` | TemplateController@index | `templates/mobile/` |
| `/tablet/*` | TemplateController@index | `templates/tablet/` |
| `/dashboard/*` | TemplateController@index | `templates/dashboard/` |
| `/images/*` | ImagesController | `templates/{active}/assets/images/` atau `assets/images/` |
| `/drive/*` | DriveController | `assets/drive/` |
| `/modules/*` | ModulesController | `assets/modules/` |

---

## 8. Tips

1. **Prioritas template:** Simpan asset di `templates/theme/assets/` (atau mobile/tablet/dashboard), sistem akan mengambil dari sana.
2. **Drive tetap di root:** File upload (avatar, dokumen) tetap di `assets/drive/` dan dilayani lewat `/drive/`.
3. **Modules untuk libraries:** Simpan JS/CSS libraries publik di `assets/modules/` dan akses via `/modules/`. Ini memisahkan library dari template-specific assets.
4. **Subdirectory:** Gunakan `{assets/...}`, `{img/...}`, atau `{modules/...}` agar base URL ikut subdirectory project.
5. **Resize gambar:** Tambahkan `?w=300&h=200` pada URL `/images/` untuk resize on-the-fly.
