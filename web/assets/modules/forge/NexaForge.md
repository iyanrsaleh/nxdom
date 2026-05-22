# NexaForge — Dokumentasi

Modul **`NexaForge.js`** menyediakan kelas **`NexaForge`** untuk merender **daftar data** dari template HTML di DOM (pagination, pencarian, filter, sort, CRUD ringkas, dan opsi **fetch API**). Kelas **`NexaDomextractor`** di file yang sama memproses string template dengan **`NexaFilter`** (placeholder, filter pipa, ternary/switch jika tersedia).

**API statis utama:** **`NexaForge.hydrate(root, lists)`** — mengikat data ke markup yang sudah ada; **`NexaForge.hydrateStorage(root, options)`** — mengisi data dari **tabel** (`NXUI.Storage().model`) lalu membuat instance; **`NexaForge.fetchStorageRows`** — hanya query batch (tanpa mount DOM).

**Impor:**

```javascript
import { NexaForge, NexaDomextractor } from "./assets/modules/Dom/NexaForge.js";
// atau lewat NXUI (jika terdaftar):
// NXUI.NexaForgeView === NexaForge
```

---

## Daftar isi

- [Ringkasan arsitektur](#ringkasan-arsitektur)
- [Atribut HTML & extractor](#atribut-html--extractor)
- [Metode statis](#metode-statis)
- [Sumber data: `storage` (NexaModels / SQL)](#sumber-data-storage-nexamodels--sql)
- [Referensi opsi: `fetchStorageRows` & `hydrateStorage`](#referensi-opsi-fetchstoragerows--hydratestorage)
- [Spinner (loading) pada `hydrateStorage`](#spinner-loading-pada-hydratestorage)
- [Legacy & alias (`NexaHtml.js`)](#legacy--alias-nexahtmljs)
- [Constructor: `new NexaForge(options)`](#constructor-new-nexaforgeoptions)
- [Properti instance](#properti-instance)
- [Template & rendering (`NexaDomextractor`)](#template--rendering-nexadomextractor)
- [Data: getter/setter dan bentuk input](#data-gettersetter-dan-bentuk-input)
- [Pagination, search, filter](#pagination-search-filter)
- [Metode manipulasi data](#metode-manipulasi-data)
- [Field sensitif](#field-sensitif)
- [Mode API (`endpoint`)](#mode-api-endpoint)
- [Event kustom](#event-kustom)
- [Utilitas & lifecycle](#utilitas--lifecycle)
- [Kelas `NexaDomextractor`](#kelas-nexadomextractor)
- [Contoh lengkap](#contoh-lengkap)
- [Integrasi `NXUI.html` + `hydrate`](#integrasi-nxuihtml--hydrate)
- [Troubleshooting (umum)](#troubleshooting-umum)

---

## Ringkasan arsitektur

1. **Elemen wajib di DOM** — sebuah node dengan **`id`** unik. Isinya adalah **satu baris template per item** (akan disalin ke `<script type="text/template">` dan diganti dengan **kontainer** hasil render).
2. **Extractor** — nama “kumpulan” data, dari atribut **`NexaForge` / `nexaforge` / `data-nexa-forge`** (atau legacy **`NexaHtml` / `nexahtml` / `data-nexa-html`**), atau jika tidak ada, sama dengan **`elementById`**.
3. **Data** — dinormalisasi ke bentuk `{ [extractor]: Array<item> }`. Setiap item mendapat **`no`** (nomor urut), **`encoded_id`** (dari `id` jika ada, base64), dan **`slug`** opsional dari `href`.
4. **Render** — internal memakai **`NexaDomextractor.render(template, data, templateElement)`** dengan **`NexaFilter`** untuk placeholder `{extractor.field}`, path bersarang, dan filter `|nama(...)`.

---

## Atribut HTML & extractor

Browser menormalisasi nama atribut ke huruf kecil. Nilai extractor dibaca dengan **`NexaForge.getNexaForgeAttr(el)`** dari salah satu:

- `NexaForge`, `nexaforge`, `data-nexa-forge` (disarankan)
- legacy: `NexaHtml`, `nexahtml`, `data-nexa-html`

**Contoh:**

```html
<div id="daftar-user" data-nexa-html="user">
  <div class="baris">
    <span>{user.nama}</span>
    <span>{user.email}</span>
  </div>
</div>
```

Key data saat inisialisasi harus **`user`** (sama dengan extractor):

```javascript
new NexaForge({
  elementById: "daftar-user",
  user: [
    { id: 1, nama: "A", email: "a@x.com" },
    { id: 2, nama: "B", email: "b@x.com" },
  ],
});
```

---

## Metode statis

Detail parameter **`fetchStorageRows`**, **`hydrateStorage`**, **`spinner`**, dan opsi yang tidak diteruskan ke constructor ada di [Referensi opsi](#referensi-opsi-fetchstoragerows--hydratestorage) dan [Spinner](#spinner-loading-pada-hydratestorage). Ringkasan tabel **`buildStorageQueryConfig`** / alur batch ada di [Sumber data: `storage`](#sumber-data-storage-nexamodels--sql).

### `NexaForge.SENSITIVE_FIELDS`

Array nama field yang dianggap sensitif (password, token, dll.). Bisa diubah lewat **`addSensitiveFields`** / **`removeSensitiveFields`**.

### `NexaForge.addSensitiveFields(fields)` / `removeSensitiveFields(fields)`

- **Parameter:** `string` atau `Array<string>`.
- Menambah atau menghapus entri (perbandingan **case-insensitive**).

### `NexaForge.getNexaForgeAttr(el)`

- **Parameter:** `Element`
- **Return:** `string | null` — nilai atribut extractor.

### `NexaForge.hydrate(root, lists = {})`

- **Parameter:**
  - `root` — `ParentNode` yang sudah berisi markup (mis. setelah `innerHTML`).
  - `lists` — `Record<string, Array>` — data per extractor, mis. `{ user: [...] }`.
- **Return:** `NexaForge[]` — satu instance per elemen yang punya atribut NexaForge **dan** **`id`**.
- Elemen tanpa `id` akan di-**warn** dan dilewati.

```javascript
const container = document.getElementById("main");
container.innerHTML = `...`; // fragmen HTML dengan blok list

const instances = NexaForge.hydrate(container, {
  user: [
    { id: 1, nama: "Rina", email: "rina@example.com" },
  ],
});
```

---

## Sumber data: `storage` (NexaModels / SQL)

Untuk mengisi daftar dari **tabel** lewat `NXUI.Storage().model(...)` — pola sama dengan **`NexaDom`** (`storage: { model, select?, query? }` di `docs/NexaDom.md`) — gunakan loader internal yang sama:

| Metode | Keterangan |
|--------|------------|
| **`NexaForge.buildStorageQueryConfig(storage)`** | Memetakan `{ model, select?, query? }` ke bentuk `queryConfig` untuk `storageModelStorageData`. |
| **`NexaForge.fetchStorageRows(options)`** | Satu panggilan async: `limit`/`offset`, `searchKeyword`, `sortBy`/`sortOrder`, `searchableFields`, dan opsi **`mapRow(row)`** untuk menyamakan kolom SQL dengan placeholder template. |
| **`NexaForge.hydrateStorage(root, options)`** | Memuat baris lewat **`fetchStorageRows`**, lalu membuat **`new NexaForge({ elementById, [extractor]: rows, ... })`** untuk setiap elemen bertanda NexaForge di **`root`**. |

**Catatan:** Muatan awal adalah **satu batch** SQL — default **`storageFetchLimit` / `fetchLimit` = 10000** (bukan `order`). **`order`** hanya mengatur **berapa baris per halaman** di UI; jika `order` ikut dipakai sebagai LIMIT SQL, hanya segelintir baris yang termuat dan pagination akan salah (mis. 49 data di DB tapi hanya 10 di memori). Pencarian dan pagination klien bekerja pada **data yang sudah di memori**. Untuk pagination server-side penuh, gunakan **`NexaDom`** dengan `storage`.

```javascript
// Setelah innerHTML fragmen HTML (mis. dari NXUI.html):
const { instances, totalCount, rows } = await NexaForge.hydrateStorage(container, {
  storage: {
    model: "demo",
    query: (q) => q.whereNotNull("id"),
  },
  order: 10,
  sortBy: "title",
  sortOrder: "ASC",
  searchableFields: ["title", "slug"],
  search: "exam-user-search",
  pagination: "exam-user-pagination",
  mapRow: (row) => ({
    id: row.id,
    nama: row.title ?? "",
    email: row.slug ?? "",
  }),
});
```

### `order` vs LIMIT SQL

| Opsi | Peran |
|------|--------|
| **`order`** | Hanya untuk **jumlah baris per halaman** di UI NexaForge (slice di klien). **Tidak** dipakai sebagai LIMIT query. |
| **`limit`** | LIMIT SQL eksplisit (override prioritas tertinggi). |
| **`storageFetchLimit`** atau **`fetchLimit`** | Ukuran batch yang diambil dari DB. Default **10000**. |
| *(tidak set)* | Fallback LIMIT = **10000** — cukup untuk pagination klien atas puluhan/ratusan ribu baris (terbatas memori). |

Jika Anda membutuhkan **satu halaman per request** dari server (offset berubah tanpa memuat semua baris ke browser), gunakan **`NexaDom`** + `storage` (`docs/NexaDom.md`), bukan `hydrateStorage`.

---

## Referensi opsi: `fetchStorageRows` & `hydrateStorage`

### `NexaForge.fetchStorageRows(options)`

| Opsi | Tipe | Keterangan |
|------|------|------------|
| **`storage`** | `{ model, select?, query? }` | **Wajib.** `model` = nama tabel; `query(builder)` = kondisi tetap (where/join); `select` = kolom (default `*`). |
| **`limit`** | `number` | SQL LIMIT; jika diisi, mengalahkan `storageFetchLimit` / default. |
| **`storageFetchLimit`** / **`fetchLimit`** | `number` | Batch SQL; default **10000**. |
| **`offset`** | `number` | SQL OFFSET (default `0`). |
| **`searchKeyword`** | `string` | Filter server-side (LIKE) jika `searchableFields` tidak kosong. |
| **`searchableFields`** / **`searchFields`** | `string[]` | Kolom untuk pencarian SQL. |
| **`filterValue`** / **`filterField`** | `string` | Filter persis `=` satu kolom (sesuai `storageModelStorageData`). |
| **`sortBy`** / **`sortOrder`** | `string` | Urutan SQL (default `id` / `DESC`). |
| **`mapRow`** | `(row) => object` | Memetakan satu baris DB ke bentuk yang dipakai template (mis. `title` → `nama`). |

**Return:** `{ totalCount, response, rows }` — `rows` sama isinya dengan `response` setelah `mapRow` (jika ada).

### `NexaForge.hydrateStorage(root, options)`

Selain opsi **`fetchStorageRows`** di atas, berikut opsi yang khas untuk **`hydrateStorage`**:

| Opsi | Keterangan |
|------|------------|
| **`extractor`** atau **`listsKey`** | Nama key data (mis. `user`). Jika tidak diisi, diambil dari atribut **`NexaForge`** pada elemen pertama, lalu fallback **`id`** elemen. |
| **`order`**, **`search`**, **`pagination`**, **`searchableFields`**, **`sortBy`**, **`sortOrder`**, **`filter`**, **`alwaysShowPagination`**, … | Diteruskan ke **`new NexaForge(...)`** (kecuali yang dihapus di bawah). Sama seperti inisialisasi manual daftar. |
| **`spinner`** | Lihat [Spinner (loading)](#spinner-loading-pada-hydratestorage). |

**Tidak** diteruskan ke constructor (hanya dipakai saat fetch / spinner): **`storage`**, **`extractor`**, **`listsKey`**, **`limit`**, **`storageFetchLimit`**, **`fetchLimit`**, **`offset`**, **`mapRow`**, **`searchKeyword`**, **`spinner`**.

**Return:** `{ instances: NexaForge[], totalCount, rows }`.

---

## Spinner (loading) pada `hydrateStorage`

Spinner memakai modul **`NexaSpinner.js`** (`nexaSpinner`) — perilaku diselaraskan dengan **`NexaDom`** (overlay layar penuh vs inline di container).

| Situasi | Perilaku |
|---------|----------|
| **`spinner` tidak ada** (`undefined`) | Tidak ada spinner (kompatibel dengan kode lama). |
| **`spinner: false`** | Spinner dinonaktifkan. |
| **`spinner: true`** | Spinner default (warna biru `#007bff`, overlay `body`, dll.). |
| **`spinner: { ... }`** | Menggabungkan dengan default: `enabled`, `centerScreen`, `type`, `size`, `color`, `position`, `message`. |

**Properti umum (objek `spinner`):**

| Properti | Keterangan |
|----------|------------|
| **`enabled`** | `false` mematikan spinner meski objek ada. |
| **`centerScreen`** | `true` (default): target **`body`**, tipe overlay. `false`: spinner **inline** pada **`root`** (`#id` container route). |
| **`type`** | `overlay` \| `inline` \| `button` — jika `centerScreen: true`, non-inline dipaksa ke overlay. |
| **`size`** | `small` \| `medium` \| `large` |
| **`color`** | Warna indikator (hex). |
| **`position`** | Untuk inline: `center` \| `top` \| `bottom` |
| **`message`** | Teks opsional di dekat spinner |

Spinner ditampilkan **sebelum** `fetchStorageRows` dan disembunyikan di **`finally`** (termasuk saat error).

```javascript
await NexaForge.hydrateStorage(container, {
  storage: { model: "demo", query: (q) => q.whereNotNull("id") },
  order: 10,
  spinner: {
    enabled: true,
    centerScreen: true,
    type: "overlay",
    size: "medium",
    color: "#CB2F2F",
    position: "center",
    message: "Memuat data…",
  },
});
```

---

## Legacy & alias (`NexaHtml.js`)

- Berkas **`assets/modules/Dom/NexaHtml.js`** mengekspor **`NexaForge` sebagai `NexaHtml`** dan **`NexaDomextractor`** — untuk kompatibilitas impor lama.
- Di **`NXUI`**, **`NexaForgeView`** adalah kelas **`NexaForge`**; alias deprecated **`NexaHtmlView`** mengacu ke kelas yang sama.
- Disarankan impor: **`import { NexaForge } from "./NexaForge.js"`**.

---

## Constructor: `new NexaForge(options)`

### Wajib

| Opsi | Keterangan |
|------|------------|
| **`elementById`** | `id` string — elemen template **harus ada** di `document`. |

### Data awal (salah satu pola)

| Pola | Contoh |
|------|--------|
| Objek dengan key = extractor | `{ elementById: "x", user: [{...}] }` |
| `{ data: [...] }` | Dinormalisasi ke array di bawah extractor |
| Array langsung | `[{...}, {...}]` → `{ [extractor]: [...] }` |
| Objek kosong / tanpa array | Menjadi `{ [extractor]: [] }` |

### Opsi umum

| Opsi | Keterangan |
|------|------------|
| **`order`** | Jumlah item per halaman (default implisit dipakai bersama pagination). |
| **`sortOrder`**, **`sortBy`** | Urutan awal: `ASC` / `DESC` + nama field. |
| **`search`** | `id` elemen `<input>` untuk pencarian. |
| **`searchableFields`** | Array nama field yang dicari (string). |
| **`pagination`** | `id` elemen kontainer pagination (biasanya `<ul class="pagination">`); `false` menonaktifkan. |
| **`alwaysShowPagination`** | `true`: bar pagination tetap ditampilkan jika ada data walau hanya **satu** halaman. Default: jika **≤1** halaman, bar disembunyikan (`display: none`). |
| **`filter`** / **`filterBy`** | Filter sederhana atau multi-select (lihat kode: `filterBy` bisa array id elemen). |
| **`hideSensitiveFields`** | Default `true` — samarkan field sensitif saat render. |
| **`endpoint`** | Konfigurasi HTTP untuk mode API (lihat [Mode API](#mode-api-endpoint)). |
| **`templateSelector`** | Default `'[data-template="list"]'` — selector template internal. |
| **`virtualScroll`** | Jika diset, ada jalur observer untuk muat lebih (IntersectionObserver). |

### Error

- `options` bukan object → throw.
- Elemen `#elementById` tidak ada → throw.
- **`endpoint`** invalid → **`_validateEndpointConfig`** throw (url wajib, method valid, dll.).

---

## Properti instance

| Anggota | Keterangan |
|---------|------------|
| **`data`** | Getter/setter — struktur `{ [extractor]: [...] }`. Setter memanggil normalisasi yang sama seperti konstruktor. |
| **`filter`** | Instance **`NexaFilter`**. |
| **`renderData`**, **`curPage`**, **`updatePaginationUI`** | Terikat ke instance — render halaman, potong data per halaman, refresh UI pagination. |
| **`_rowID`** | Nama extractor / key array utama. |
| **`_currentPage`**, **`_pageLimit`** | State pagination. |
| **`_activeFilters`** | Object filter multi-select (jika dipakai). |
| **`_filterState`** | Objek bantu: `active`, `history`, `add`, `remove`, `clear`, `get`, `getAll`. |
| **`_performance`** | `{ renderTime, filterTime, sortTime }` (array pelacakan). |

---

## Template & rendering (`NexaDomextractor`)

- Template disimpan sebagai **`<script type="text/template" data-template="list">`**; konten ter-render masuk ke **div** `#${elementById}_content_${instanceId}`.
- Placeholder utama: **`{extractor.field}`** atau path bersarang seperti **`{user.alamat.kota}`**.
- Prefix **`user.`** atau **`row.`** pada path juga didukung di mesin render (diselaraskan dengan key data).
- **Filter pipa:** `{user.nama|uppercase}` (sesuai kemampuan **`NexaFilter.parseFilters`** / **`Filter`**).
- **Ternary / switch** di template diproses jika **`filter.processTernary`** / **`processSwitch`** ada pada **`NexaFilter`**.
- Konteks global opsional di template (dari objek global **`NEXA`**): `base_url`, `page_home`, `page_index`, `projectAssets`, `projectDrive` — diisi **`NexaDomextractor`** saat render baris.

---

## Data: getter/setter dan bentuk input

Setter **`data`** menerima nilai yang dinormalisasi:

- Menambahkan **`no`**, **`encoded_id`** per item jika ada **`id`**.
- Struktur API `{ data: [...] }` diubah menjadi array di bawah extractor.

---

## Pagination, search, filter

- **Pagination** — membuat tombol First, Previous, nomor halaman (jendela ~5), Next, Last; memakai **`getFilteredData()`** agar konsisten dengan filter aktif.
- **Satu halaman** — jika total halaman ≤ `1` **dan** **`alwaysShowPagination`** bukan `true`, elemen pagination disembunyikan (kecuali tidak ada data sama sekali). Set **`alwaysShowPagination: true`** untuk demo atau agar kontrol tetap terlihat.
- **Search** — debounce + throttle pada input; memfilter **`_originalData`** lalu mengisi **`_data`** (pencarian di **memori**; bukan query ulang ke DB kecuali Anda memuat ulang data sendiri).
- **Filter multi (`filterBy`)** — tiap `<select id="...">` memakai atribut **`data-filter-type`**; kombinasi filter dengan **`_activeFilters`**.

Elemen **`#pageInfo`**, **`#currentPage`**, **`#totalPages`**, **`#totalItems`** diperbarui jika ada di DOM (id **global** — hindari dua instance NexaForge yang sama-sama mengisi id ini di satu halaman).

---

## Metode manipulasi data

| Metode | Keterangan |
|--------|------------|
| **`Element(callback)`** | Memanggil `callback` dengan salinan array item (dimaksudkan untuk akses baris halaman saat ini). *Catatan:* implementasi mengasumsikan struktur `data` yang konsisten dengan extractor. |
| **`addData(newData, resetPage = true)`** | Menyisipkan batch baru di depan data; memicu event **`dataReloaded`** / **`dataReloadError`**. |
| **`getCurrentData()`** | `{ all, current, pagination }`. |
| **`setData(newData)`** | Ganti penuh dengan **array**; reset halaman ke 1. |
| **`Reload(newData, options?)`** | Muat ulang: `append`, `preserveFilters`, `resetPage`. Mengembalikan object status atau throw di cabang tertentu. |
| **`setupRefreshButton(buttonId, options?)`** | Mengikat tombol: `onStart`, `onSuccess`, `onError`, `loadingText`, `data`, `reloadOptions`. |
| **`sort(order, sortBy)`** | **`ASC`** / **`DESC`** + field. |
| **`getID(id)`** | Cari di **`_originalData`**; kembalikan objek **tanpa** field sensitif. |
| **`updateById(id, updateData, reRender?)`** | Patch item; event **`dataUpdated`**. |
| **`updateData(condition, updateData, reRender?)`** | `condition` berupa **function(item, index)** atau **object** exact match; event **`bulkDataUpdated`**. |
| **`deleteById(id, reRender?)`** | Hapus baris; event **`dataDeleted`**. |
| **`filterKey(key, value)`** | Filter satu field; kembalikan ringkasan `{ filtered, total, data }`. |
| **`recover()`** | Pulihkan **`_data`** dari **`_originalData`**. |

---

## Field sensitif

- **Global:** `NexaForge.SENSITIVE_FIELDS`, `addSensitiveFields`, `removeSensitiveFields`.
- **Instance:** `setSensitiveFieldsVisibility(enabled)`, `getSensitiveFieldsVisibility()`, `getSensitiveFields()` (salinan array).
- Nilai string non-kosong ditampilkan sebagai **`••••••••`** jika nama field cocok (substring **case-insensitive**).

---

## Mode API (`endpoint`)

Constructor **`options.endpoint`** contohnya:

```javascript
endpoint: {
  url: "https://api.example.com/users",
  method: "GET",
  headers: {},
  timeout: 30000,
  retryAttempts: 3,
  retryDelay: 1000,
},
```

| Metode | Keterangan |
|--------|------------|
| **`setApi(requestData?, options?)`** | Fetch JSON; loading/error UI opsional; update DOM lewat **`_updateDomFromApi`**. |
| **`refreshApi(requestData?)`** | Perlu mode API aktif. |
| **`retry(requestData?)`** | Ulangi dengan data terakhir atau yang diberikan. |
| **`updateEndpoint(newEndpoint)`** | Merge ke konfigurasi. |
| **`getEndpoint()`** | Salinan aman atau `null`. |
| **`getApiState()`** | Termasuk `isApiMode` dan state storage. |
| **`getLastApiResponse()`** | Response sukses terakhir. |
| **`setRequestData(data)`** | Untuk retry / tracking. |
| **`setApiMode(enabled)`** / **`isApiMode()`** | |
| **`setLoadingMessage(msg)`** | Teks pada blok loading bawaan (Bootstrap-style). |
| **`setTimeout(ms)`** / **`setRetryConfig({ attempts, delay })`** | |

**Response JSON** didukung: `data` (array), array langsung, **`rows`**, atau **`items`**.

**Event dokumen:** `nexadom:dataLoaded`, `nexadom:dataError` (detail menyertakan `domId`, `elementId`, dll.).

---

## Event kustom

| Event | Kapan |
|-------|--------|
| **`dataReloaded`** / **`dataReloadError`** | Setelah **`addData`** |
| **`dataUpdated`** | Setelah **`updateById`** |
| **`bulkDataUpdated`** | Setelah **`updateData`** |
| **`dataDeleted`** | Setelah **`deleteById`** |
| **`nexadom:dataLoaded`** / **`nexadom:dataError`** | Mode API |

---

## Utilitas & lifecycle

| Metode | Keterangan |
|--------|------------|
| **`compileTemplate(template)`** | Mengubah `{{key}}` menjadi pola `\${data.key}` (untuk evaluasi string). |
| **`destroy()`** | Hapus listener, cache, elemen bertanda **`data-nexadom`**, observers lazy-load, dll. **Panggil saat komponen dibuang.** |

**Lazy load gambar:** `img[data-src]` memuat dari `data-src` saat masuk viewport.

---

## Kelas `NexaDomextractor`

- **Constructor:** `new NexaDomextractor(filter?, options?)` — jika bukan **`NexaFilter`**, dibuat filter baru.
- **`setPaginationInfo(currentPage, pageLimit)`** — memengaruhi nomor **`no`** per baris.
- **`render(template, data, element)`** — menghasilkan string HTML (dibungkus **`nx-row`** per key data).
- **`parse(template)`** → `DocumentFragment` via **DOMParser**.
- **`sanitize(str)`** — escape teks untuk HTML (cegah XSS pada string biasa).

---

## Contoh lengkap

### Inisialisasi manual

```html
<div id="list-wrap">
  <input type="search" id="cari-user" placeholder="Cari..." />
  <ul id="paginasi" class="pagination"></ul>
  <div id="info-halaman">
    Halaman <span id="currentPage">1</span> dari <span id="totalPages">1</span>
    — Total <span id="totalItems">0</span> data
  </div>

  <div id="users" data-nexa-html="user">
    <div class="user-row">
      <strong>{user.nama}</strong>
      <span>{user.email}</span>
      <small>#{user.no}</small>
    </div>
  </div>
</div>
```

```javascript
const nh = new NexaForge({
  elementById: "users",
  user: [
    { id: 10, nama: "Dewi", email: "dewi@example.com" },
    { id: 11, nama: "Eko", email: "eko@example.com" },
  ],
  order: 5,
  search: "cari-user",
  searchableFields: ["nama", "email"],
  pagination: "paginasi",
  sortOrder: "ASC",
  sortBy: "nama",
});

nh.getCurrentData();
nh.sort("DESC", "nama");
nh.addData({ user: [{ id: 12, nama: "Baru", email: "baru@example.com" }] });
nh.destroy();
```

### Mode API (fetch)

```javascript
const apiList = new NexaForge({
  elementById: "users",
  user: [],
  endpoint: {
    url: "https://api.example.com/v1/users",
    method: "GET",
  },
});

await apiList.setApi();
```

---

## Integrasi `NXUI.html` + `hydrate`

Alur umum: muat fragmen HTML statis → **`innerHTML`** → **`hydrate(container, { extractor: rows })`**.

Detail langkah dan contoh handler ada di **`docs/NexaDom.md`** — bagian *Route & fragmen HTML: `NXUI.html` + `hydrate` (NexaForge)* dan anchor **`#nexadom-route-html-hydrate`** / **`#nexadom-nexaforge-ref`**.

Ringkasnya:

1. Markup memakai **`id`** + atribut extractor (**`NexaForge`** / **`data-nexa-forge`**, atau legacy **`NexaHtml`**) dengan nilai sama dengan key di objek kedua **`hydrate`**.
2. Placeholder per item: **`{user.nama}`**, **`{user.email}`**, dll.
3. **`result.hydrate`** (dari **`NXUI.html`**) memanggil **`NexaForge.hydrate`** secara internal.

Untuk data dari tabel (bukan array statis), setelah **`innerHTML`** panggil **`NexaForge.hydrateStorage(container, { storage: { model, query? }, ... })`** (lihat [Sumber data: `storage`](#sumber-data-storage-nexamodels--sql) dan [Referensi opsi](#referensi-opsi-fetchstoragerows--hydratestorage)).

Contoh ringkas dengan **`NXUI.NexaForgeView`** (sama dengan **`NexaForge`**):

```javascript
const { instances, totalCount, rows } = await NXUI.NexaForgeView.hydrateStorage(container, {
  storage: { model: "demo", query: (q) => q.whereNotNull("id") },
  order: 10,
  storageFetchLimit: 10000,
  sortBy: "title",
  sortOrder: "ASC",
  searchableFields: ["nama", "email"],
  search: "exam-user-search",
  pagination: "exam-user-pagination",
  alwaysShowPagination: true,
  spinner: { enabled: true, centerScreen: true, color: "#CB2F2F", message: "" },
  mapRow: (row) => ({
    id: row.id,
    nama: String(row.title ?? ""),
    email: String(row.slug ?? ""),
  }),
});
```

---

## Troubleshooting (umum)

| Gejala | Penyebab umum | Tindakan |
|--------|----------------|----------|
| Pagination menunjukkan **1 halaman** dan **~10 item** padahal di DB banyak baris | LIMIT SQL terlalu kecil atau **`order`** pernah dipakai sebagai satu-satunya batas fetch. | Di **`hydrateStorage`**, pastikan batch memakai default **`storageFetchLimit`** (10000) atau set eksplisit; **`order`** hanya untuk ukuran halaman UI. |
| Bar pagination **tidak terlihat** | Hanya **satu** halaman data dan **`alwaysShowPagination`** tidak `true`. | Set **`alwaysShowPagination: true`** atau kurangi **`order`** agar ada banyak halaman. |
| **`#pageInfo` / `#currentPage`** tidak konsisten | Dua instance NexaForge memakai **id duplikat** di satu dokumen. | Satu set id unik per komponen, atau satu instance per halaman. |
| Spinner tidak muncul | **`spinner`** tidak diisi (`undefined`). | Beri **`spinner: { enabled: true, ... }`** atau **`spinner: true`**. |
| Data template kosong setelah **`hydrateStorage`** | Elemen list **tanpa `id`** atau extractor tidak cocok. | Pastikan **`id`** + atribut **`NexaForge="user"`** dan **`mapRow`** / kolom template selaras. |

---

## Referensi cepat — berkas sumber

| Topik | Lokasi di `NexaForge.js` (perkiraan) |
|-------|-------------------------------------|
| `hydrate`, `buildStorageQueryConfig`, `fetchStorageRows`, `hydrateStorage`, spinner | ~baris 1–340 |
| Constructor, pagination, search, filter, prototype methods | ~340–2500 |
| API `endpoint`, `_validateEndpointConfig`, `setApi` | ~2500–3000 |
| **`NexaDomextractor`** | ~3000–akhir |

---

*Dokumen ini mengacu pada perilaku kode di `assets/modules/Dom/NexaForge.js`. Untuk perilaku **`NXUI.html`** dan routing, lihat `docs/NexaDom.md` dan `assets/modules/Nexa.js`. Untuk query builder tabel, lihat `docs/NexaModels.md`.*
