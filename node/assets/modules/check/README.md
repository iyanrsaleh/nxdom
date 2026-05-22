# NexaCheckable (NXUI.Checkable)

**NexaCheckable** mengelola `**input[type="checkbox"]`** dan `**input[type="radio"]**` di halaman: mendengarkan peristiwa `change`, mengumpulkan nilai, dan mengekspos helper untuk membaca/mengatur keadaan tercentang.

**API yang disarankan:** `new NXUI.Checkable()` (objek `NXUI` disediakan setelah **Nexa.js** dimuat).

**Nama lama (deprecated, tetap berfungsi):** `NXUI.NexaCheckbox`, `NXUI.Checkbox`, `NexaCheckable` di `window`.

## Daftar isi

- [1. Prasyarat penting](#1-prasyarat-penting)
- [2. Cara memakai (minimal)](#2-cara-memakai-minimal)
- [3. Markup HTML (disarankan)](#3-markup-html-disarankan)
  - [Atribut `data-*` → properti `data`](#checkable-data-attrs)
  - [Switch / toggle UI](#checkable-switch-ui)
- [4. Callback `onSaveCallback`](#4-callback-onsavecallback)
- [5. Ringkasan metode](#5-ringkasan-metode)
  - [Pembacaan agregat](#checkable-metode-agregat)
  - [Radio](#checkable-metode-radio)
  - [Akses daftar & DOM](#checkable-metode-akses-dom)
  - [Penulisan keadaan](#checkable-metode-penulisan)
- [6. Perbedaan singkat dengan NexaField](#6-perbedaan-singkat-dengan-nexafield)

---

## 1. Prasyarat penting

Instance **memindai seluruh dokumen sekali** saat konstruktor dijalankan (`init()`):

- Semua checkbox dan radio yang ingin dikelola harus **sudah ada di DOM** sebelum `new NXUI.Checkable()`.
- Jika Anda menambah input baru secara dinamis setelah itu, instance **tidak** otomatis menempel listener pada elemen baru (perlu instance baru atau perluasan manual).

Contoh urutan yang benar:

```javascript
container.innerHTML = `... input checkbox / radio ...`;
const checkable = new NXUI.Checkable();
checkable.onSaveCallback((inputData) => { /* ... */ });
```

Demo interaktif: `templates/checkable.js` (route `/checkable` jika sudah diregistrasi di aplikasi Anda).

---

## 2. Cara memakai (minimal)

```javascript
const checkable = new NXUI.Checkable();

checkable.onSaveCallback((inputData) => {
  // Lihat bentuk payload di bagian "Callback".
});

// Opsional: baca snapshot
console.log(checkable.getAllValues());
```

Tidak ada langkah `init` terpisah; pemindaian dan `addEventListener("change", …)` terjadi di konstruktor.

---

## 3. Markup HTML (disarankan)


| Saran                                | Alasan                                                                                                                                                                            |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `**id` unik** pada setiap checkbox   | Kunci objek di `getAllValues` / `getCheckedValues` / dll. adalah `id || name || className`. Tanpa `id` yang unik, beberapa input bisa bertabrakan kunci (nilai terakhir menimpa). |
| `**name` konsisten** pada grup radio | Grup radio wajib memakai `name` yang sama; dipakai oleh `getSelectedRadio`, `getRadioGroups`.                                                                                     |
| Atribut `**data-*`**                 | Diurai ke objek `data` dengan **camelCase** (lihat di bawah).                                                                                                                     |


```html
<input type="checkbox" id="cb_notify" name="notify" value="yes" data-user-tier="pro" />
<input type="radio" id="plan_basic" name="plan" value="basic" checked />
<input type="radio" id="plan_pro" name="plan" value="pro" />
```

<a id="checkable-data-attrs"></a>

### Atribut `data-*` → properti `data`

`data-user-tier="pro"` menjadi `data.userTier === "pro"`. Pola: setelah `data-`, segmen dipisah `-`, segmen berikutnya diawali huruf besar (`kebab` → `camelCase`).

<a id="checkable-switch-ui"></a>

### Switch / toggle UI

**Switch** di kit Nexa adalah checkbox biasa dengan tampilan pill + thumb (mis. kelas `.nx-switch-item`, `.nx-switch` di `label`). **NexaCheckable** tidak memperlakukan switch secara khusus: tetap `type="checkbox"`, jadi `change`, `getCheckedValues`, dan `setValueById` bekerja seperti checkbox kotak. Gaya default ada di `assets/modules/Form/form.css`; halaman demo `templates/checkable.js` menyertakan salinan CSS scoped jika `form.css` tidak dimuat.

**Urutan DOM:** `input` harus **sebelum** `label` (saudara berurutan), agar selector seperti `input:checked ~ label .nx-switch` di stylesheet Nexa berfungsi.

```html
<div class="nx-switch-grid">
  <div class="nx-switch-item">
    <input
      type="checkbox"
      id="feature_cross"
      name="ui_cross"
      value="on"
      data-ui-kind="switch"
    />
    <label for="feature_cross">
      <span class="nx-switch"></span>
      Type Cross
    </label>
  </div>
</div>
```

```javascript
const checkable = new NXUI.Checkable();

checkable.onSaveCallback((inputData) => {
  if (inputData.id === "feature_cross") {
    console.log("switch:", inputData.checked, inputData.data?.uiKind); // data-ui-kind → uiKind
  }
});

// Programatik — sama seperti checkbox biasa
checkable.setValueById("feature_cross", true);
```

---

## 4. Callback `onSaveCallback`

```javascript
checkable.onSaveCallback((inputData) => { /* ... */ });
```

Objek `**inputData**` (ringkas):


| Properti                                          | Keterangan                                                                    |
| ------------------------------------------------- | ----------------------------------------------------------------------------- |
| `id`, `name`, `value`, `checked`, `type`, `class` | Dari elemen input.                                                            |
| `element`                                         | Node DOM asli (`HTMLInputElement`).                                           |
| `attributes`                                      | Objek semua atribut `name → value`.                                           |
| `data`                                            | Hanya atribut yang namanya diawali `data-`, sudah dinormalisasi ke camelCase. |


`setValueById` juga memicu callback yang sama (lewat `handleInputChange` internal).

---

## 5. Ringkasan metode

<a id="checkable-metode-agregat"></a>

### Pembacaan agregat


| Metode                              | Mengembalikan                                                                                                                                                                                                                       |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `getAllValues()`                    | Objek: kunci = `id || name || className`, nilai = snapshot `{ checked, value, name, id, type, class, attributes, data }` per input yang terdaftar saat konstruktor.                                                                 |
| `getCheckedValues()`                | Sama seperti di atas, hanya entri dengan `checked === true`.                                                                                                                                                                        |
| `getUncheckedValues()`              | Sama, hanya yang tidak tercentang.                                                                                                                                                                                                  |
| `getDataByAttribute(attributeName)` | Objek yang kunci-nya **nilai atribut** (string), hanya untuk input yang punya atribut tersebut. Contoh: `getDataByAttribute("data-slug")`. Nilai entri memuat `[attributeName]: attrValue` plus field umum (`checked`, `value`, …). |


<a id="checkable-metode-radio"></a>

### Radio


| Metode                        | Mengembalikan                                                                                                  |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `getSelectedRadio(groupName)` | Objek deskriptor untuk radio terpilih dalam `name="${groupName}"`, atau `null` jika tidak ada yang tercentang. |
| `getRadioGroups()`            | Objek `groupName → array` entri deskriptor untuk setiap radio di grup.                                         |


<a id="checkable-metode-akses-dom"></a>

### Akses daftar & DOM


| Metode                          | Keterangan                                                                                                 |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `Elements()`                    | Array gabungan checkbox + radio yang terdaftar saat init (NodeList di-spread ke array).                    |
| `getCheckboxes()`               | NodeList checkbox saat init.                                                                               |
| `getRadios()`                   | NodeList radio saat init.                                                                                  |
| `getElementById(id)`            | `document.getElementById(id)`.                                                                             |
| `getElementsByName(name)`       | `document.querySelectorAll('input[name="..."]')`.                                                          |
| `getElementsByClass(className)` | Setara `querySelectorAll("input." + className)` — hindari class dengan karakter khusus dalam selector CSS. |


<a id="checkable-metode-penulisan"></a>

### Penulisan keadaan


| Metode                      | Perilaku                                                                                                                                                                 |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `setValueById(id, checked)` | Set `element.checked`, lalu memicu callback seperti perubahan user.                                                                                                      |
| `reset()`                   | Set semua input terdaftar ke `checked = false`.                                                                                                                          |
| `checkAll()`                | Set **semua** checkbox dan radio terdaftar ke `checked = true`. Pada grup radio, perilaku browser/HTML bisa membingungkan (idealnya hanya untuk uji atau checkbox saja). |


---

## 6. Perbedaan singkat dengan NexaField


|                                | **NexaField**                         | **NexaCheckable**                  |
| ------------------------------ | ------------------------------------- | ---------------------------------- |
| Target                         | Inline edit (`span.editable`, dll.)   | `input` checkbox & radio           |
| Init                           | `initElements(selector?)` setelah DOM | Sekali di konstruktor, scan global |
| Checkbox/radio di field inline | Tidak dipakai untuk inline edit       | Gunakan Checkable                  |


Gunakan **Checkable** untuk form centang/pilihan tunggal; gunakan **Field** untuk teks/select/tags yang diedit inline.