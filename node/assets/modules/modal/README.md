# Dokumentasi modal Nexa (`NXUI`)

Dokumen ini menjelaskan **`NXUI.Modal`**: pemanggilan fungsi untuk membangun DOM (implementasi **`modalHTML`** di **`assets/modules/modal/NexaModalHtml.js`**), serta **`NXUI.Modal.open`** / **`NXUI.Modal.close`** untuk membuka dan menutup. Untuk minimize, restore, dan API lain pada instance, gunakan **`NXUI.nexaModal`**. Stylesheet dan perilaku inti di `assets/modules/modal/NexaModal.js` dan `modal.css` (lewat `assets/modules/Nexa.js`).

**Bukan paket npm:** Nexa tidak mendokumentasikan `import { NXUI } from '@nxui/core'` atau dependensi `@nxui/*` — `NXUI` tersedia lewat **`Nexa.js`** (biasanya **`window.NXUI`**). Alur resmi: **`await NXUI.Modal({ … })`** lalu **`await NXUI.Modal.open(elementById)`** (bukan pola `const m = NXUI.Modal(…); m.open();` kecuali persis sama di contoh resmi di bawah).

**Form di modal** bisa memakai HTML statis (`content`) atau **`floating`** (NexaFloating — sama seperti **`NXUI.Form`** di halaman biasa). Validasi footer bisa memakai **`setDataBy.form`** + **`onclick.validation`**; detail pola form global ada di **`docs/form.md`**.

## Daftar isi

- [1. Peran modul](#1-peran-modul)
- [2. Prasyarat halaman](#2-prasyarat-halaman)
- [3. Alur standar (buka modal dinamis)](#3-alur-standar-buka-modal-dinamis)
- [4. Integrasi dengan route Nexa](#4-integrasi-dengan-route-nexa)
  - [4.1 Mendaftarkan halaman](#41-mendaftarkan-halaman)
  - [4.2 Handler di `nx` (Proxy Nexa)](#42-handler-di-nx-proxy-nexa)
  - [4.3 Handler submit footer](#43-handler-submit-footer)
- [5. API `NXUI` yang relevan](#5-api-nxui-yang-relevan)
- [6. Objek konfigurasi `NXUI.Modal(...)`](#6-objek-konfigurasi-nxuimodal)
  - [6.1 Properti utama](#61-properti-utama)
  - [6.2 Objek `onclick` (footer)](#62-objek-onclick-footer)
  - [6.3 Body modal: `content` atau `floating` (NexaFloating)](#63-body-modal-content-atau-floating-nexafloating)
  - [6.4 Validasi dari `setDataBy.form` / `storage.form`](#64-validasi-dari-setdatabyform--storageform)
- [7. Global helper dari `NexaModal.js` (window)](#7-global-helper-dari-nexamodaljs-window)
- [8. Fitur perilaku sistem modal](#8-fitur-perilaku-sistem-modal)
- [9. Checklist debugging](#9-checklist-debugging)
- [10. Contoh kode lengkap](#10-contoh-kode-lengkap)
  - [10.1 Layout halaman dengan `#nexa_main` (opsional)](#101-layout-halaman-dengan-nexa_main-opsional)
  - [10.2 Modal tanpa footer (hanya header tutup + konten)](#102-modal-tanpa-footer-hanya-header-tutup--konten)
  - [10.3 Modal dengan form: kumpulkan data lewat `getFormBy: ["id"]`](#103-modal-dengan-form-kumpulkan-data-lewat-getformby-id)
  - [10.4 Buka / tutup / minimize dari kode (tanpa HTML inline)](#104-buka--tutup--minimize-dari-kode-tanpa-html-inline)
  - [10.5 Import ESM dari modul lain (tanpa `window.NXUI`)](#105-import-esm-dari-modul-lain-tanpa-windownxui)
  - [10.6 Modal langsung minimized (taskbar) saat dibuat](#106-modal-langsung-minimized-taskbar-saat-dibuat)
  - [10.7 Modal dengan `floating` + NexaValidation (route `/modal`)](#107-modal-dengan-floating--nexavalidation-route-modal)
- [11. Referensi file](#11-referensi-file)

---

## 1. Peran modul

| Sumber | Fungsi |
|--------|--------|
| **`Nexa.js`** | Menggabungkan API ke `window.NXUI` ( **`NXUI.Modal`** termasuk `.open` / `.close`, **`NXUI.nexaModal`**, **`NXUI.ensureModalStylesheet`** ) dan ekspor ESM. |
| **`modal/NexaModalHtml.js`** | **`modalHTML(data)`**: membangun DOM modal (body dari **`content`** atau **`floating`**), footer + **`NexaValidation`**, `collectFormData` lokal. |
| **`modal/NexaModal.js`** | Instance `nexaModal`: buka/tutup, minimize/restore, taskbar minimized, IndexedDB untuk state minimasi, helper global `redModal`, `closeModal`, dll. |

---

## 2. Prasyarat halaman

- **`Nexa.js`** sudah dimuat sehingga `window.NXUI` (dan biasanya `window.nx`) tersedia.
- Untuk penempatan modal **sebelum** konten utama, sediakan **`#nexa_main`** di layout. Jika tidak ada, modal tetap disisipkan ke **`document.body`** (fallback implementasi).
- **ID modal harus unik** di seluruh dokumen. Misalnya gabungkan prefix tetap dengan id logis (contoh: `"setModal_" + id` → `setModal_modal1`) agar tidak bentrok dengan elemen lain.

---

## 3. Alur standar (buka modal dinamis)

1. Panggil **`await NXUI.Modal({ ... })`** dengan `elementById` = ID final elemen `.nx-modal`.
2. Panggil **`await NXUI.Modal.open(elementById)`** dengan **ID yang sama** seperti `elementById`.

Tanpa langkah (2), elemen modal sudah ada di DOM tetapi tidak dalam state “terbuka” yang dikelola sistem modal.

Contoh ringkas:

```javascript
const modalID = "setModal_modal1";

await NXUI.Modal({
  elementById: modalID,
  styleClass: "w-500px",
  minimize: true,
  label: "Modal",
  content: "<div><p>Konten HTML</p></div>",
  setDataBy: false,
  getFormBy: false,
  getValidationBy: false,
  onclick: {
    title: "Save Settings",
    cancel: "Cancel",
    send: "saveGroupByModal",
  },
});

await NXUI.Modal.open(modalID);
```

---

## 4. Integrasi dengan route Nexa

### 4.1 Mendaftarkan halaman

Pada modul route Nexa, fungsi ekspor (misalnya `export async function namaHalaman(page, route)`) memanggil `route.register(...)` dan mengisi `container.innerHTML`. Contoh resmi route modal: **`templates/modal.js`** (halaman **`/modal`**) — membuka modal dengan **`floating`** + **`setDataBy.form`** (selaras **`templates/form.js`**).

### 4.2 Handler di `nx` (Proxy Nexa)

Menetapkan fungsi di **`nx.*`** akan disinkronkan ke **`window.*`** untuk nama yang sama (lihat Proxy di **`Nexa.js`**). Di **`templates/modal.js`**: **`nx.openModalFloatingDemo`**, **`nx.saveModalFormSubmit`**, dan alias **`nx.redModal`** (= `openModalFloatingDemo`) agar pemanggilan lama seperti **`redModal('modal1')`** tetap jalan.

### 4.3 Handler submit footer

`onclick.send` berisi **nama fungsi string** (mis. `"saveModalFormSubmit"`). Saat tombol utama footer diklik (setelah validasi jika aktif), **`NexaModalHtml.js`** mencari fungsi dengan urutan yang sama seperti **`NexaForm`**:

1. `window[nama]`
2. `window.NXUI[nama]`
3. `window.nx[nama]`
4. `window.nx._global[nama]`

Contoh handler submit:

```javascript
nx.saveModalFormSubmit = async function (modalId, formData, setDataBy) {
  console.log({ modalId, formData, setDataBy });
  await NXUI.Modal.close(modalId);
};
```

Signature umum handler: **`(modalId, formData, setDataBy)`** — argumen ketiga dari **`setDataBy`** di objek konfigurasi **`NXUI.Modal({ ... })`**.

---

## 5. API `NXUI` yang relevan

| API | Deskripsi |
|-----|-----------|
| **`NXUI.Modal(data)`** | `async` — membuat/memperbarui DOM modal ber-ID `data.elementById`, memuat CSS bila perlu. |
| **`NXUI.Modal.open(id, data?)`** | `async` — membuka modal (delegasi ke instance internal; sama peran dengan pemanggilan `open` sebelumnya lewat `nexaModal`). |
| **`NXUI.Modal.close(id, force?)`** | `async` — menutup modal. |
| **`NXUI.nexaModal`** | Instance penuh `NexaModal` — `minimize`, `restore`, `toggleMinimize`, opsi lanjutan, dll. (`open`/`close` tersedia juga di **`NXUI.Modal`**). |
| **`NXUI.ensureModalStylesheet()`** | `async` — memastikan `modal.css` terpasang (biasanya dari alur `Modal`). |

Import ESM dari bundler yang mengarah ke `Nexa.js`:

```javascript
import { Modal, ensureModalStylesheet } from ".../Nexa.js";
```

---

## 6. Objek konfigurasi `NXUI.Modal(...)`

### 6.1 Properti utama

| Properti | Tipe | Deskripsi |
|----------|------|-----------|
| **`elementById`** | `string` | **Wajib disarankan.** Menjadi `id` elemen root `.nx-modal`. Default internal `"myModal"` jika tidak diisi. |
| **`content`** | `string` | HTML isi body modal. Dipakai jika **`floating`** tidak diisi. |
| **`floating`** | `object` | Skema **NexaFloating** (wajib **`form`**, **`variables`**, **`id`** / **`modalid`**). Body modal diisi dengan **`new NexaFloating(...).html()`** — selaras dengan **`NXUI.Form`**. Lihat [§6.3](#63-body-modal-content-atau-floating-nexafloating). |
| **`label`** | `string` | Teks judul header (`h5.nx-modal-title`). |
| **`styleClass`** | `string` | Kelas tambahan pada `.nx-modal-dialog` (mis. lebar: `w-500px`). |
| **`minimize`** | `boolean` | Jika `true`, tombol minimize di header ditampilkan; perilaku minimize terintegrasi dengan sistem modal (`nexaModal` untuk aksi lanjutan). |
| **`showMinimize`** | `boolean` | Hanya efektif jika **`minimize: true`**. Jika `true`, setelah modal dibuat modal langsung diperlakukan sebagai minimized (taskbar). Jika `minimize` false, nilai ini diabaikan (dengan peringatan di konsol). |
| **`minimizedBg`** | `string` | Warna latar taskbar item minimized (disimpan di atribut/data untuk gradien). |
| **`paddingTop`** | `string` | Padding atas dialog / atribut data untuk layout. |
| **`bodyPadding`** | `string` | Padding area body modal. |
| **`setDataBy`** | `any` | Diteruskan ke callback submit sebagai argumen ketiga (`setDataBy`). Untuk validasi terpadu dengan field **floating**, sertakan **`setDataBy.form`** (objek definisi field). Lihat [§6.4](#64-validasi-dari-setdatabyform--storageform). |
| **`getFormBy`** | `false` \| `string` \| `string[]` | Mengatur **`collectFormData`**: `false` menonaktifkan pola default; array seperti `["id"]`, `["name"]`, `["data-key"]`, `["data-order"]` untuk mengumpulkan field form. |
| **`getValidationBy`** | `false` \| … | Dipakai bersama **`NexaValidation`** bila validasi footer aktif. |
| **`footer`** | `string` | HTML opsional di sisi kiri footer (wadah `.nx-footer-custom`). |
| **`onclick`** | `object` \| tidak ada | Jika ada dan **`onclick.send`** terisi, footer dengan tombol Cancel + Save dibuat. |
| **`callback`** | `{ method?, data? }` | Disimpan sebagai atribut `data-callback-method` / `data-callback-data` pada elemen modal untuk integrasi lain. |

### 6.2 Objek `onclick` (footer)

| Field | Deskripsi |
|-------|-----------|
| **`cancel`** | Label tombol batal (memanggil `closeModal`). |
| **`title`** | Label tombol utama (save). |
| **`send`** | **Nama string** fungsi handler submit (bukan referensi fungsi). |
| **`validation`** | Objek aturan **`validasi`** (map key field → angka aturan, selaras **`NexaValidation`**). Digabung dengan aturan yang dibangun dari **`setDataBy.form`** / **`storage.form`** bila ada; nilai di **`onclick.validation`** menimpa key yang sama. Jika truthy atau **`getValidationBy`** diisi, alur **`NexaValidation`** dipakai. |

Tanpa `onclick` atau tanpa `onclick.send`, **footer default tidak dibuat** (modal tanpa tombol footer bawaan).

### 6.3 Body modal: `content` atau `floating` (NexaFloating)

Prioritas isi body di **`NexaModalHtml.js`** (sama seperti **`NexaForm`**):

1. Jika **`floating`** ada — `new NexaFloating(data.floating, { formById: data.floating.id || data.floating.modalid, mode: "insert" })`, lalu **`innerHTML = template.html()`**.
2. Jika tidak — **`content`** (string HTML) atau string kosong.

Struktur **`floating`** mengikuti **`NexaFloating.js`**: **`form`** (definisi field per key), **`variables`** (urutan field), **`id`** untuk `<form>` dalam, **`settings`** (mis. `floating: true`, `layout`), dll.

### 6.4 Validasi dari `setDataBy.form` / `storage.form`

**`NexaModalHtml.js`** membangun objek **`validasi`** untuk **`Validation()`** dengan menggabungkan:

- aturan dari **`storage.form`** atau **`setDataBy.form`** (hanya entri **`condition: true`**, mapping sama seperti **`NexaForm`** / **`getValidationBy`**), dan
- **`onclick.validation`** (menimpa key yang bentrok).

Dengan itu Anda bisa memakai **`floating`** + **`setDataBy.form`** + **`onclick.validation: {}`** agar aturan sepenuhnya datang dari definisi field (disarankan selaras **`templates/form.js`** dan **`templates/modal.js`**).

---

## 7. Global helper dari `NexaModal.js` (window)

Fungsi berikut berguna di HTML inline atau skrip non-modul:

| Global | Peran singkat |
|--------|----------------|
| **`redModal(modalId, data?)`** | Bisa di-hook dengan assignment `window.redModal = fn` (setter); default juga memanggil pembuka modal (setara `Modal.open`). |
| **`closeModal(modalId)`** | Menutup modal. |
| **`closeAllModals()`** | Menutup semua modal aktif. |
| **`minimizeModal` / `restoreModal` / `toggleMinimizeModal`** | Kontrol minimize. |
| **`window.nexaModal`** | Akses langsung ke instance yang sama dengan `NXUI.nexaModal`. |

Event kustom yang disebar di dokumen antara lain: `nexaModalOpened`, `nexaModalRestored`, dan event minimize terkait taskbar (lihat implementasi `NexaModal.js`).

---

## 8. Fitur perilaku sistem modal

- **Buka/tutup & animasi**: **`Modal.open`** / **`Modal.close`** (atau `nexaModal` untuk kontrol setara) mengatur tampilan, fokus, backdrop, z-index (multi-modal jika diizinkan opsi).
- **Minimize & taskbar**: Modal yang diminimize muncul sebagai item di container `#minimized-modals`; klik judul/area restore membuka kembali; tombol close pada item menutup paksa.
- **Persistensi minimasi**: Secara default, state minimized bisa dipersist ke **IndexedDB** (restore setelah refresh) sesuai opsi di `NexaModal`.
- **Validasi form**: Jika `onclick.validation` (truthy) atau `getValidationBy` mengaktifkan jalur validasi, **`NexaValidation`** dijalankan; aturan bisa berasal dari **`setDataBy.form`** + merge **`onclick.validation`**. Setelah sukses, data dikumpulkan dengan **`collectFormData`** lalu diteruskan ke handler **`send`** (bukan `result.response` seperti di **`NexaForm`** — perilaku tetap seperti implementasi modal saat ini).
- **Koleksi data tanpa validasi**: Jika tidak memakai blok validasi, tombol save memanggil `collectFormData(modalId, getFormBy)` dengan pola resolusi field di implementasi modal.
- **Duplikat ID**: Sebelum menambahkan modal baru, elemen lama dengan **ID sama** dihapus dari DOM agar tidak menumpuk.
- **Stylesheet**: `ensureModalStylesheet` menunggu `NXUI.NexaStylesheet.Dom` bila tersedia, lalu memuat `modal.css` dari modul.

---

## 9. Checklist debugging

- Error saat **`NXUI.Modal`** → tangkap di `try/catch` dan log; sering karena konten HTML tidak valid atau dependensi validasi.
- Modal tidak terlihat setelah **`NXUI.Modal({...})`** → pastikan memanggil **`NXUI.Modal.open(id)`** dan `id` **sama persis** dengan `elementById`.
- Tombol save tidak memanggil handler → pastikan nama di `onclick.send` ada di **`nx`**, **`NXUI`**, atau **`window`**, dan dieja sama.
- **`getFormBy: false`** mengubah perilaku koleksi default; sesuaikan dengan kebutuhan atau gunakan array metode koleksi eksplisit.

---

## 10. Contoh kode lengkap

### 10.1 Layout halaman dengan `#nexa_main` (opsional)

Jika konten route disuntikkan ke dalam shell yang punya anchor utama, hasil **`NXUI.Modal`** akan disisipkan **sebelum** elemen ini. Tanpa `#nexa_main`, modal tetap ditambahkan ke `body`.

```html
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <title>App</title>
  </head>
  <body>
    <header><!-- nav --></header>
    <main>
      <div id="nexa_main"></div>
    </main>
    <script type="module" src="/path/ke/Nexa.js"></script>
  </body>
</html>
```

### 10.2 Modal tanpa footer (hanya header tutup + konten)

Tidak menyertakan `onclick` / `onclick.send` sehingga tidak ada tombol Cancel/Save di bawah.

```javascript
async function openInfoModal() {
  const modalID = "setModal_info";
  await NXUI.Modal({
    elementById: modalID,
    styleClass: "w-400px",
    minimize: false,
    label: "Informasi",
    content: `
      <div>
        <p>Modal ini hanya punya tombol tutup (X) di header.</p>
      </div>
    `,
  });
  await NXUI.Modal.open(modalID);
}
```

### 10.3 Modal dengan form: kumpulkan data lewat `getFormBy: ["id"]`

Field memakai `id` yang unik di dalam modal. Tombol Save memanggil `saveContactModal` dengan objek `formData`.

```javascript
nx.openContactForm = async function () {
  const modalID = "setModal_contact";
  await NXUI.Modal({
    elementById: modalID,
    styleClass: "w-560px",
    minimize: true,
    label: "Edit kontak",
    setDataBy: null,
    getFormBy: ["id"],
    getValidationBy: false,
    onclick: {
      title: "Simpan",
      cancel: "Batal",
      send: "saveContactModal",
    },
    content: `
      <div class="nx-modal-form-stack">
        <label for="contact_name">Nama</label>
        <input type="text" id="contact_name" name="name" />
        <label for="contact_email">Email</label>
        <input type="email" id="contact_email" name="email" />
      </div>
    `,
  });
  await NXUI.Modal.open(modalID);
};

nx.saveContactModal = async function (modalId, formData, setDataBy) {
  console.log("Data:", formData);
  await NXUI.Modal.close(modalId);
};
```

### 10.4 Buka / tutup / minimize dari kode (tanpa HTML inline)

```javascript
// Buka (setelah NXUI.Modal pernah dipanggil untuk id yang sama)
await NXUI.Modal.open("setModal_modal1");

// Tutup
await NXUI.Modal.close("setModal_modal1");

// Minimize ke taskbar (perlu modal aktif / terdaftar di sistem)
window.minimizeModal("setModal_modal1");

// Kembalikan dari taskbar
window.restoreModal("setModal_modal1");
```

### 10.5 Import ESM dari modul lain (tanpa `window.NXUI`)

Pastikan path mengarah ke bundel yang mengekspor dari `Nexa.js`.

```javascript
import { Modal, nexaModal } from "/assets/modules/Nexa.js";

export async function showDemo() {
  const id = "setModal_esm";
  await Modal({
    elementById: id,
    styleClass: "w-480px",
    label: "Dari ESM",
    content: "<p>Halo dari import.</p>",
  });
  await Modal.open(id);
}
```

### 10.6 Modal langsung minimized (taskbar) saat dibuat

Perlu **`minimize: true`** dan **`showMinimize: true`**.

```javascript
await NXUI.Modal({
  elementById: "setModal_bg",
  styleClass: "w-400px",
  minimize: true,
  showMinimize: true,
  label: "Background task",
  minimizedBg: "#0d6efd",
  content: "<p>Modal ini langsung ke taskbar.</p>",
  onclick: {
    title: "OK",
    cancel: "Tutup",
    send: "ackBackgroundModal",
  },
});
await NXUI.Modal.open("setModal_bg");

nx.ackBackgroundModal = async function (modalId, formData) {
  await NXUI.Modal.close(modalId);
};
```

### 10.7 Modal dengan `floating` + NexaValidation (route `/modal`)

Contoh di repositori: **`templates/modal.js`**. Pola singkat:

- Satu objek **`form`** dipakai untuk **`floating.form`** dan **`setDataBy.form`**.
- **`getFormBy`**: `["name"]`, **`getValidationBy`**: `["name"]` (sesuai atribut yang dihasilkan NexaFloating).
- **`onclick`**: `send: "saveModalFormSubmit"`, **`validation: {}`** agar aturan utama dari **`setDataBy.form`**.
- Handler: **`nx.saveModalFormSubmit`** (atau nama lain yang cocok dengan **`onclick.send`**).

```javascript
const formDef = {
  nama: {
    condition: true,
    type: "text",
    label: "Nama",
    placeholder: "Nama lengkap",
    name: "nama",
    id: "nama",
    validation: 2,
  },
  email: {
    condition: true,
    type: "email",
    label: "Email",
    placeholder: "email@contoh.com",
    name: "email",
    id: "email",
    validation: 2,
  },
};

const floatingConfig = {
  id: "form_modal_modal1_nexa",
  label: "Kontak",
  variables: ["nama", "email"],
  form: formDef,
  settings: { floating: true, layout: "vertical" },
};

await NXUI.Modal({
  elementById: "setModal_modal1",
  styleClass: "w-500px",
  minimize: true,
  label: "Modal — data kontak",
  floating: floatingConfig,
  setDataBy: { form: formDef, source: "templates/modal.js", modalKey: "modal1" },
  getFormBy: ["name"],
  getValidationBy: ["name"],
  onclick: {
    title: "Simpan",
    cancel: "Batal",
    send: "saveModalFormSubmit",
    validation: {},
  },
});
await NXUI.Modal.open("setModal_modal1");
```

Penjelasan angka **`validation`** (2 = wajib; nilai lebih besar mengatur panjang minimum): **`docs/form.md`**.

---

## 11. Referensi file

- DOM modal + `floating` / validasi: **`assets/modules/modal/NexaModalHtml.js`**
- State & taskbar: `assets/modules/modal/NexaModal.js`
- Gaya tampilan: `assets/modules/modal/modal.css`
- Entry agregasi API: `assets/modules/Nexa.js`
- Demo route: **`templates/modal.js`** ( **`/modal`** )
- Form pola sama: **`docs/form.md`**, **`templates/form.js`**
