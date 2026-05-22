# NX.Link — Navigasi tautan SPA (NexaLink)

Modul untuk **mencegah reload penuh** pada tautan same-origin di dalam suatu kontainer. Menggunakan **event delegation**, memperbarui URL lewat `history.pushState`, mengirim event navigasi, dan (lewat **`NexaLinkUI`**) dapat **fetch** halaman HTML lalu menyisipkan fragmen konten ke area yang ditentukan — opsional dengan **spinner** (`NexaSpinner.js`).

| Item | Nilai |
|------|--------|
| **Sumber** | `assets/modules/Link/NexaLink.js` |
| **Entry global** | `nxdom.js` → `window.NXUI` / `window.NX` |
| **Alias utama** | `NX.Link`, `NXUI.Link` (= kelas `NexaLinkUI`) |
| **Dependensi** | `../spinner/NexaSpinner.js` (hanya saat `load()` + opsi `spinner`) |

---

## Daftar isi

1. [Muat modul](#muat-modul)
2. [API global](#api-global)
3. [Arsitektur: tiga lapisan](#arsitektur-tiga-lapisan)
4. [Kelas `NexaLink`](#kelas-nexalink)
5. [`LinkDefault`](#linkdefault)
6. [Kelas `NexaLinkUI` (`NX.Link`)](#kelas-nexalinkui-nxlink)
7. [`onSkip`](#onskip)
8. [Metode `load()`](#metode-load)
9. [Spinner](#spinner)
10. [Event](#event)
11. [`NexaLinkNavigateDetail`](#nexalinknavigatedetail)
12. [Tautan yang diabaikan](#tautan-yang-diabaikan)
13. [Persyaratan HTML](#persyaratan-html)
14. [Contoh integrasi](#contoh-integrasi)
15. [Batasan & troubleshooting](#batasan--troubleshooting)
16. [Referensi file](#referensi-file)

---

## Muat modul

### ES module (langsung)

```javascript
import { NexaLink, LinkDefault, NexaLinkUI, onSkip } from "./Link/NexaLink.js";
```

### Lewat NXDOM (disarankan di aplikasi web)

```html
<script type="module" src="/modules/nxdom.js"></script>
<script src="/assets/js/app.js" defer></script>
```

Setelah `nxdom.js` selesai, `window.NXUI` dan alias `window.NX` tersedia:

```javascript
// Keduanya setara (NX = NXUI di nxdom.js)
const link = new NX.Link({ /* opsi */ });
const link2 = new NXUI.Link({ /* opsi */ });
```

Skrip inisialisasi (`app.js`, dll.) sebaiknya dijalankan **setelah** modul NXDOM — misalnya pada `DOMContentLoaded` atau di akhir `<body>` dengan atribut `defer`.

---

## API global

| Simbol | Tipe | Keterangan |
|--------|------|------------|
| `NXUI.Link` | `class NexaLinkUI` | API utama SPA + `load()` — **`new NXUI.Link(opts)`** |
| `NX.Link` | alias | Sama dengan `NXUI.Link` |
| `NXUI.NexaLink` | `class NexaLink` | Hanya intercept klik + `pushState` |
| `NXUI.LinkDefault` | `function` | Singkatan `new NexaLink(...).attach(...)` |
| `NXUI.onSkip` | `function` | Tautan di container tertentu → full page reload |
| `NXUI.spinner` | `function` | Dipakai internal `load()` jika opsi `spinner` diisi |

**Export dari `nxdom.js`:** `NexaLink`, `LinkDefault`, `NexaLinkUI`, `onSkip`.

---

## Arsitektur: tiga lapisan

```
┌─────────────────────────────────────────────────────────────┐
│  NexaLinkUI  (NX.Link)                                      │
│  · onClick / onDefault / load() / popstate                  │
│  · fetch HTML, swap konten, spinner, update <title>         │
└──────────────────────────┬──────────────────────────────────┘
                           │ memakai
┌──────────────────────────▼──────────────────────────────────┐
│  NexaLink                                                   │
│  · event delegation di root                                 │
│  · preventDefault + history.pushState (same-origin path)    │
│  · event link:navigate                                      │
└──────────────────────────┬──────────────────────────────────┘
                           │ opsional bypass
┌──────────────────────────▼──────────────────────────────────┐
│  onSkip(#container)                                         │
│  · capture phase → stopImmediatePropagation → location.href │
└─────────────────────────────────────────────────────────────┘
```

| Lapisan | Kapan dipakai |
|---------|----------------|
| **`NexaLink` saja** | Anda punya router sendiri; hanya butuh `pushState` tanpa fetch |
| **`NexaLinkUI` (`NX.Link`)** | Dokumentasi, dashboard, layout partial — fetch + ganti fragmen HTML |
| **`onSkip`** | Tautan yang **harus** full reload (eksternal, logout, unduhan halaman penuh) |

---

## Kelas `NexaLink`

Intercept klik `<a>` di dalam **root** lewat satu listener (bubbling). Elemen tautan yang ditambahkan dinamis tetap ter-cover.

### Konstruktor

```javascript
new NexaLink(selector, root?)
```

| Parameter | Tipe | Default | Keterangan |
|-----------|------|---------|------------|
| `selector` | `string` | — | Selector untuk `element.closest(selector)`, mis. `"a"`, `"summary a"` |
| `root` | `ParentNode \| string` | `document` | Induk delegation; string = `document.querySelector(root)` |

### Metode

| Metode | Return | Keterangan |
|--------|--------|------------|
| `getBase()` | `ParentNode \| null` | Menyelesaikan `root` ke node |
| `attach({ onNavigate? })` | `this` | Pasang listener; no-op jika root/selector tidak valid |

### Perilaku `attach()`

1. Memanggil `onNavigate(detail)` untuk setiap klik yang cocok (sebelum keputusan intercept).
2. Tautan **hanya hash** (`#bagian`) → **tidak** `preventDefault`; scroll native browser.
3. Tautan **same-origin** dengan path/query → `preventDefault`, `history.pushState`, dispatch `link:navigate`.
4. Tautan di dalam `#nx-skip-links` → diabaikan oleh `NexaLink` (ditangani `onSkip`).

### Contoh

```javascript
new NXUI.NexaLink("a", "#sidebar").attach({
  onNavigate(detail) {
    console.log(detail.href, detail.element);
  },
});
```

---

## `LinkDefault`

Singkatan:

```javascript
LinkDefault(selector, root?, options?)
// setara: new NexaLink(selector, root).attach(options)
```

```javascript
NXUI.LinkDefault("a", "#main-content", {
  onNavigate(info) {
    console.log("Navigasi:", info.href);
  },
});
```

---

## Kelas `NexaLinkUI` (`NX.Link`)

API berantai: `new NX.Link({ … }).onClick(fn).onDefault(root)`.

### Opsi konstruktor

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|------------|
| `loadContentSelectors` | `string[]` | `["#dokumentasi .nx-doc-content", ".nx-doc-content"]` | Dicoba berurutan saat `load()` pada HTML hasil fetch **dan** dokumen aktif |
| `loadContentSelector` | `string` | — | Satu selector; menggantikan array di atas |
| `spinner` | objek \| `false` \| `null` | `null` (mati) | Opsi `NXUI.spinner()`; lihat [Spinner](#spinner) |
| `enablePopstate` | `boolean` | `true` | Tombol Back/Forward memanggil `load(location.href, { forceFetch: true, skipSpinner: true })` |

### Metode

| Metode | Return | Keterangan |
|--------|--------|------------|
| `onClick(fn)` | `this` | Callback tiap klik tautan valid; daftarkan **sebelum** `onDefault()` |
| `onDefault(first, second?)` | `this` | Pasang `NexaLink` internal; lihat [Bentuk `onDefault`](#bentuk-ondefault) |
| `info()` | `NexaLinkNavigateDetail \| null` | Data klik terakhir |
| `load(href, options?)` | `Promise<void>` | Fetch & swap konten; lihat [Metode `load()`](#metode-load) |

### Bentuk `onDefault`

| Pemanggilan | Arti |
|-------------|------|
| `onDefault("#menu")` | Root `#menu`, selector tautan `"a"` |
| `onDefault("#menu", "summary a")` | Root lalu selector kustom |
| `onDefault("a", "#menu")` | Urutan `(selector, root)` seperti `LinkDefault` |
| `onDefault("#menu", { selector: "a.nav", onClick: fn })` | Opsi objek; `onClick` bisa inline |

**Penting:** Memanggil `onDefault()` berulang pada **instance yang sama** memasang listener baru di root baru; instance sebelumnya tidak otomatis dilepas dari root lama. Gunakan **satu root** yang mencakup semua menu (mis. `#main-content`) atau instance terpisah per area dengan sengaja.

### Pola minimal

```javascript
const link = new NX.Link();

link
  .onClick((info) => {
    if (info.href) link.load(info.href);
  })
  .onDefault("#nx-js-doc-menu");
```

### Pola lengkap (dokumentasi + pasca-load)

```javascript
const link = new NX.Link({
  loadContentSelectors: [".docs-article", "article.markdown-body.docs-article"],
  enablePopstate: true,
  spinner: {
    enabled: true,
    centerScreen: true,
    type: "overlay",
    size: "medium",
    color: "#CB2F2F",
    message: "Memuat…",
  },
});

link
  .onClick((info) => {
    if (!info.href) return;
    if (info.href.startsWith("#")) {
      syncMenuActive(info.element);
      return;
    }
    link
      .load(info.href)
      .then(() => bootArticleEnhancements())
      .catch((err) => console.error("Navigasi:", err));
  })
  .onDefault("#main-content");

NXUI.onSkip("#nx-skip-links", "a");
```

---

## `onSkip`

Tautan di dalam container yang dipilih melakukan **navigasi penuh** (`window.location.href`), bukan AJAX / `pushState`.

```javascript
NXUI.onSkip("#nx-skip-links", "a");
NXUI.onSkip(["#footer-external", "#nx-skip-links"], "a"); // array root
```

| Parameter | Default | Keterangan |
|-----------|---------|------------|
| `selector` | — | CSS selector container (atau array selector) |
| `linkSelector` | `"a"` | Selector tautan di dalam container |

**Urutan pendaftaran:** daftarkan `onSkip` **setelah** `onDefault` jika container `#nx-skip-links` bersarang di dalam root SPA (mis. di dalam `#main-content`), agar handler capture `onSkip` menang lebih dulu.

**HTML contoh:**

```html
<nav id="nx-skip-links">
  <a href="/logout">Keluar</a>
  <a href="https://example.com">Situs eksternal</a>
</nav>
```

---

## Metode `load()`

```javascript
await link.load(href, { forceFetch?: boolean, skipSpinner?: boolean });
```

### Alur

1. **`href` kosong atau diawali `#`** → no-op (return segera).
2. **Origin berbeda** → no-op.
3. **`pathname + search` sama** dengan halaman saat ini (hanya beda hash):
   - **Tidak** `fetch`.
   - Spinner **tidak** tampil.
   - Jika hash bermakna → `scrollIntoView({ behavior: "smooth" })` ke elemen `id`.
   - Jika tidak ada hash → scroll ke atas area konten.
4. **Path berbeda** → `fetch(pathname + search)` dengan `Accept: text/html`, `credentials: "same-origin"`.
5. Parse respons dengan `DOMParser`:
   - Jika ada `#dokumentasi` di respons **dan** di dokumen aktif → ganti `innerHTML` `#dokumentasi` secara penuh.
   - Jika tidak → cari node pertama yang cocok `loadContentSelectors` di respons dan salin `innerHTML` ke node yang sama di dokumen aktif.
6. Perbarui `document.title` dari `<title>` respons (jika ada).
7. Scroll ke hash atau ke atas konten.

### Opsi `load()` kedua argumen

| Opsi | Keterangan |
|------|------------|
| `forceFetch: true` | Paksa fetch meski path sama (dipakai handler `popstate`) |
| `skipSpinner: true` | Jangan tampilkan spinner (dipakai `popstate`) |

### `popstate` (tombol mundur / maju)

Bila `enablePopstate: true` (default), listener memanggil:

```javascript
link.load(window.location.href, { forceFetch: true, skipSpinner: true });
```

`NexaLink` sendiri **tidak** menangani `popstate`; hanya `NexaLinkUI` yang mengisi ulang konten sesuai URL di bilah alamat.

---

## Spinner

Opsi `spinner` dinormalisasi ke pemanggilan `NXUI.spinner()` / `nexaSpinner()`.

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|------------|
| `enabled` | `boolean` | `true` | `false` mematikan spinner |
| `centerScreen` | `boolean` | `true` | `true` → target `body`, tipe `overlay` |
| `target` | `string` | `#dokumentasi` | Container inline jika `centerScreen: false` |
| `type` | `'overlay' \| 'inline' \| 'button'` | `overlay` / `inline` | Sesuai `centerScreen` |
| `size` | `'small' \| 'medium' \| 'large'` | `medium` | Ukuran spinner |
| `color` | `string` | `#007bff` | Warna spinner |
| `position` | `'center' \| 'top' \| 'bottom'` | `center` | Posisi inline |
| `message` | `string` | `''` | Teks opsional |

```javascript
spinner: false                    // mati total
spinner: { enabled: false }       // sama
spinner: {
  centerScreen: false,
  target: ".docs-article",
  type: "inline",
  color: "#CB2F2F",
  message: "Memuat dokumentasi…",
}
```

**Catatan:** Spinner **inline** pada elemen yang `innerHTML`-nya diganti akan ikut hilang saat swap; `destroy()` tetap dipanggil di blok `finally` `load()`.

---

## Event

| Event | Tipe | Kapan | `detail` |
|-------|------|-------|----------|
| `nexa:link` | `CustomEvent` | Setelah klik lolos filter, dari `NexaLinkUI` | `NexaLinkNavigateDetail` |
| `link:navigate` | `Event` | Setelah `pushState` dari `NexaLink.attach` | — |

```javascript
window.addEventListener("nexa:link", (e) => {
  console.log(e.detail.href, e.detail.element);
});

window.addEventListener("link:navigate", () => {
  console.log("URL:", location.pathname + location.search + location.hash);
});
```

---

## `NexaLinkNavigateDetail`

Objek yang diteruskan ke `onNavigate`, `onClick`, dan `nexa:link` detail:

| Field | Tipe | Keterangan |
|-------|------|------------|
| `href` | `string` | Nilai atribut `href` (trim) |
| `element` | `HTMLAnchorElement` | Elemen `<a>` yang diklik |
| `text` | `string` | `textContent` tautan (trim) |
| `event` | `MouseEvent` | Event klik asli |

```javascript
link.onClick((info) => {
  console.log(link.info()); // sama dengan info terakhir setelah klik
});
```

---

## Tautan yang diabaikan

`NexaLink` / `NexaLinkUI` **tidak** mengintercept (atau tidak `preventDefault`) untuk:

| Kondisi | Perilaku |
|---------|----------|
| Hanya `#fragmen` | Navigasi hash native; `load("#…")` no-op |
| `target="_blank"` | Buka tab baru |
| Atribut `download` | Unduhan browser |
| Tomol mouse ≠ kiri (0) | — |
| `meta` / `ctrl` / `shift` / `alt` + klik | Buka tab baru / perilaku browser |
| `javascript:`, `mailto:`, `tel:`, `data:` | Diabaikan |
| Origin berbeda (domain/port) | Navigasi penuh normal |
| `href` kosong | Hanya `onNavigate` jika terpasang |
| Di dalam `#nx-skip-links` | Dilewati `NexaLink`; gunakan `onSkip` |
| `event.defaultPrevented` | Handler lain sudah menangani |

---

## Persyaratan HTML

### Layout dokumentasi (tema NXDOM)

```html
<main id="main-content">
  <nav class="subnav">…</nav>
  <div class="docs-layout">
    <aside id="sidebar">
      <div id="nx-js-doc-menu">
        <a class="filter-item" href="/native/entry">Entry</a>
        <a class="filter-item" href="#ringkasan">Pengenalan</a>
      </div>
    </aside>
    <article class="markdown-body docs-article">
      <!-- konten yang di-swap oleh load() -->
    </article>
  </div>
</main>
```

Bungkus menu sidebar dengan `id="nx-js-doc-menu"` agar mudah di-target terpisah dari `#main-content` bila diperlukan.

### Halaman respons server

Setiap URL yang di-`load()` harus mengembalikan HTML yang masih berisi **selector yang sama** di `loadContentSelectors`, misalnya:

```html
<article class="markdown-body docs-article">…</article>
```

Tanpa node cocok di respons **atau** di dokumen aktif, swap konten **dilewati** (halaman tidak berubah meski URL sudah `pushState`).

### Skrip setelah navigasi

Konten dari `innerHTML` **tidak** menjalankan ulang tag `<script>`. Inisialisasi (Highlight.js, Mermaid, komponen UI) harus dipanggil manual di `.then()` setelah `load()`:

```javascript
link.load(href).then(() => {
  window.nxBootDocArticle?.(document.querySelector(".docs-article"));
});
```

---

## Contoh integrasi

### 1. Tema dokumentasi (`templates/theme`)

| File | Peran |
|------|--------|
| `header.html` | `<script type="module" src="/modules/nxdom.js">` |
| `sidebar.html` | Menu dalam `#nx-js-doc-menu` |
| `footer.html` | `<script src="/assets/js/app.js" defer>` |
| `assets/js/app.js` | `new NX.Link({ loadContentSelectors: [".docs-article"], … })` |

Cuplikan `app.js` (produksi):

```javascript
const link = new NX.Link({
  loadContentSelectors: [".docs-article", "article.markdown-body.docs-article"],
  enablePopstate: true,
  spinner: { enabled: true, centerScreen: true, type: "overlay", color: "#CB2F2F" },
});

link
  .onClick((info) => {
    if (!info.href) return;
    if (info.href.startsWith("#")) { syncNavActive(info.element); return; }
    link.load(info.href).then(() => afterNavLoad(info.element));
  })
  .onDefault("#main-content");

NXUI.onSkip("#nx-skip-links", "a");
```

### 2. Hanya `pushState` (tanpa fetch)

```javascript
NXUI.LinkDefault("a", "#app-menu", {
  onNavigate({ href }) {
    myRouter.navigate(href);
  },
});
```

### 3. Hanya kelas dasar + router sendiri

```javascript
import { NexaLink } from "./Link/NexaLink.js";

const nav = new NexaLink(".nav-link", "#app");
nav.attach({
  onNavigate(d) {
    import("./pages/" + d.href + ".js").then((m) => m.render("#app"));
  },
});
```

### 4. Dengan `#dokumentasi` (layout legacy)

```javascript
const link = new NX.Link({
  loadContentSelector: "#dokumentasi .nx-doc-content",
});

link.onClick((i) => i.href && link.load(i.href)).onDefault("#dokumentasi");
```

Jika respons dan halaman aktif punya `#dokumentasi`, `load()` mengganti **seluruh** isi `#dokumentasi` terlebih dahulu, lalu mencari selector konten di dalamnya.

---

## Batasan & troubleshooting

| Gejala | Penyebab umum | Solusi |
|--------|----------------|--------|
| Klik tidak ada efek | `onDefault()` belum dipanggil | Tambahkan `.onDefault('#root')` setelah `.onClick()` |
| URL berubah, konten tidak | Selector tidak cocok di HTML respons | Samakan class/id di semua template; periksa `loadContentSelectors` |
| Sidebar ikut berubah | Selector salah (mis. `#sidebar`) | Targetkan area artikel, bukan sidebar |
| `NXUI.Link is not a constructor` | `nxdom.js` belum load | Muat modul dulu; init di `DOMContentLoaded` |
| Script di halaman baru tidak jalan | Batasan `innerHTML` | Panggil ulang init di `.then()` setelah `load()` |
| Back browser kosong / salah | `enablePopstate: false` atau server mengembalikan HTML berbeda | Aktifkan `enablePopstate`; pastikan respons konsisten |
| Link eksternal ter-intercept | Same-origin relatif salah | Pakai URL absolut atau `onSkip` |
| Dua handler bentrok | `onDefault` ganda pada root sama | Satu instance, satu root |
| Highlight / Mermaid hilang | Tidak re-init setelah swap | `bootArticleEnhancements()` di `then()` |

### Checklist deploy

- [ ] `nxdom.js` dimuat sebelum skrip yang memakai `NX.Link`
- [ ] Root `onDefault` ada di DOM (mis. `#main-content`)
- [ ] Setiap halaman server punya node target `loadContentSelectors`
- [ ] Tautan hash (`#section`) untuk TOC di halaman yang sama
- [ ] `onSkip` untuk logout / link yang harus full reload
- [ ] Re-init widget setelah `load()` selesai

---

## Referensi file

```
assets/modules/Link/
├── NexaLink.js    # NexaLink, LinkDefault, NexaLinkUI, onSkip
├── README.md      # Dokumen ini
├── index.html     # Demo lokal (3 bagian: NexaLink, NX.Link, onSkip)
└── page-b.html    # Halaman kedua untuk uji load()

assets/modules/nxdom.js          # Registrasi global NXUI.Link
assets/modules/spinner/        # NexaSpinner (dependensi load)
templates/theme/native/link.html # Halaman docs pengguna (tema)
templates/theme/assets/js/app.js # Contoh integrasi produksi
```

**Dokumentasi tema (HTML):** `/native/link` — ringkasan pengguna di situs docs.

---

## Ringkasan cepat

```javascript
// 1. Buat instance
const link = new NX.Link({ loadContentSelectors: [".docs-article"] });

// 2. Daftar callback sebelum attach
link.onClick((info) => {
  if (info.href && !info.href.startsWith("#")) link.load(info.href);
});

// 3. Pasang delegation
link.onDefault("#main-content");

// 4. (Opsional) Full reload untuk subset link
NXUI.onSkip("#nx-skip-links", "a");
```

**Ingat:** `NX.Link` = navigasi SPA + fetch fragmen; `NXUI.NexaLink` = intercept + `pushState` saja; `NXUI.onSkip` = paksa reload penuh.
