# NexaForm — Dokumentasi Penggunaan

`NXUI.Form(data)` membangun form DOM secara dinamis dengan validasi, floating label, dan callback submit.

---

## Struktur Lengkap

```js
await NXUI.Form({
  elementById: "nexaFormDemo",   // ID elemen target di container.innerHTML
  label: "Judul Form",           // Header form (opsional)
  footer: "<p>Custom kiri</p>",  // HTML di kiri footer (opsional)

  floating: floatingConfig,      // NexaFloating config (opsional, lihat bawah)
  content: "<input ...>",        // HTML manual — dipakai jika floating tidak ada (opsional)

  onclick: {
    title: "Simpan",             // Teks tombol submit
    cancel: false,               // false = sembunyikan tombol cancel | true/"Batal" = tampilkan
    send: "namaFungsiCallback",  // Nama fungsi global yang dipanggil setelah submit/validasi sukses
    validation: {},              // Ada = aktifkan NexaValidation sebelum callback | tidak ada = submit langsung
  },

  setDataBy: {
    route: routeName,            // Nama route aktif (untuk referensi callback)
    source: "templates/form.js", // Path file sumber (referensi saja)
    form: demoForm,              // Object definisi field — WAJIB (dipakai validasi)
  },

  storage: {
    form: demoForm,              // Alternatif setDataBy.form — jika ada, ini yang dipakai
  },

  getFormBy: ["id"],             // Key pengambilan data form: "id" | "name" | "data-order"
  getValidationBy: ["name"],     // Key matching validasi: "name" | "id"
});
```

---

## `onclick`

| Opsi | Tipe | Default | Keterangan |
|------|------|---------|------------|
| `title` | `string` | `"Save"` | Teks tombol submit |
| `cancel` | `false \| true \| string` | `"Cancel"` | `false` = tombol cancel disembunyikan. `true` = tampil teks "Cancel". String = teks custom tombol cancel |
| `submitClass` | `string` | `"btn btn-primary"` | CSS class tombol submit — ganti untuk ubah warna/style |
| `cancelClass` | `string` | `"btn btn-secondary"` | CSS class tombol cancel — ganti untuk ubah warna/style |
| `send` | `string` | — | **Wajib.** Nama fungsi callback. Dicari di: `window` → `NXUI` → `nx` → `nx._global` |
| `validation` | `object \| undefined` | — | Jika ada (termasuk `{}`), submit melalui NexaValidation. Jika tidak ada, data langsung dikumpulkan dan callback dipanggil |

---

## `setDataBy.form` — Definisi Field

```js
const demoForm = {
  namaField: {
    condition: true,        // true = field aktif / ikut validasi
    type: "text",           // Tipe input HTML
    label: "Label Field",
    placeholder: "...",
    name: "namaField",      // Dipakai getValidationBy: ["name"]
    id: "namaField",        // Dipakai getFormBy: ["id"] dan getValidationBy: ["id"]
    validation: 2,          // Angka = min length | 0 = tidak divalidasi
  },
};
```

---

## `floating` — NexaFloating Config

```js
const floatingConfig = {
  id: "form_demo_nexa",       // ID unik form (dipakai NexaFloating internal)
  label: "Label form",
  variables: ["namaField"],   // Urutan tampil field
  form: demoForm,             // Definisi field (sama dengan setDataBy.form)
  settings: {
    floating: true,           // true = floating label style
    layout: "vertical",       // "vertical" | "horizontal"
  },
};
```

---

## Fungsi Callback (`onclick.send`)

Dipanggil setelah validasi sukses atau submit langsung.

```js
nx.namaFungsiCallback = async function (formId, formData, setDataBy) {
  console.log(formId);    // ID elemen form
  console.log(formData);  // Object data field { namaField: "nilai", ... }
  console.log(setDataBy); // Object setDataBy dari konfigurasi Form
};
```

> Daftarkan via `nx.nama = fn` agar tersedia di `window`, `NXUI`, dan `nx` sekaligus.

---

## `getFormBy`

Menentukan cara pengambilan key data dari elemen input:

| Nilai | Keterangan |
|-------|------------|
| `["id"]` | Pakai atribut `id` sebagai key (default) |
| `["name"]` | Pakai atribut `name` sebagai key |
| `["data-order"]` | Pakai atribut `data-order` sebagai key |

---

## `getValidationBy`

Menentukan cara matching rule validasi ke field:

| Nilai | Keterangan |
|-------|------------|
| `["name"]` | Match berdasarkan `name` field (default) |
| `["id"]` | Match berdasarkan `id` field |

---

## Contoh Minimal

```js
await NXUI.Form({
  elementById: "formContainer",
  label: "Login",
  floating: {
    id: "login_form",
    variables: ["credential"],
    form: {
      credential: {
        condition: true,
        type: "text",
        label: "Credential",
        placeholder: "Masukkan credential",
        name: "credential",
        id: "credential",
        validation: 2,
      },
    },
    settings: { floating: true, layout: "vertical" },
  },
  onclick: {
    title: "Masuk",
    cancel: false,
    send: "handleLogin",
    validation: {},
  },
  setDataBy: {
    route: routeName,
    form: { credential: { condition: true, name: "credential", id: "credential", validation: 2 } },
  },
  getFormBy: ["name"],
  getValidationBy: ["name"],
});

nx.handleLogin = async function (formId, formData, setDataBy) {
  const res = await NXUI.Storage().auth().login(formData);
  console.log(res);
};
```
