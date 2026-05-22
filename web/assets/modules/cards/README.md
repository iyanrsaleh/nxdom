# Cards (NexaLayer)

Referensi cepat modul kartu/layer yang dipakai untuk layout panel drag-drop di Nexa.

**Path relatif:** `cards`  
**File utama:** `cards/NexaLayer.js`  
**Integrasi NXUI:** `assets/modules/Nexa.js` (`NXUI.NexaLayer` dan alias `NXUI.Layer`)

## Tujuan

`NexaLayer` dipakai untuk:
- render kartu dalam grid (`nx-col-*`)
- drag/sort antar kartu
- minimize/maximize/close per kartu
- simpan/restore state layout (posisi + ukuran kolom)

## API Utama

### 1) Constructor

```js
const layer = new NXUI.NexaLayer({
  container: "#form-layer",
  showHeader: true,
  showFooter: true,
  headerSize: "sm",
});
```

Opsi penting:
- `container` (default `.nx-row`)
- `item` (default `> div`)
- `dragClass`, `dropClass`
- `minimize` (default `true`)
- `showHeader` / `showFooter`
- `headerSize`: `"sm"` atau `"default"`

### 2) `Container(data)`

Membangun HTML kartu dari array `content`.

Struktur minimum item `content`:

```js
{
  id: "Propinsi",
  col: "nx-col-4",
  title: "Propinsi",
  header: "Propinsi",      // opsional; string kosong -> fallback title
  footer: "Info footer",   // opsional
  scroll: { type: "nx-scroll", height: "60vh" },
  html: "<div>Isi kartu</div>"
}
```

Contoh pakai:

```js
const html = layer.Container({
  content: [
    { id: "A", col: "nx-col-6", title: "Panel A", html: "<div>A</div>" },
    { id: "B", col: "nx-col-6", title: "Panel B", html: "<div>B</div>" },
  ],
});
document.querySelector("#form-layer").innerHTML = html;
```

### 3) `drop()`

Aktifkan sortable + restore state setelah HTML dimasukkan ke DOM.

```js
layer.drop();
```

### 4) `refreshContainer(byID, storage, methods)`

Refresh isi kartu tertentu tanpa render ulang semua container.

## Integrasi di `Nexa.js`

Di `assets/modules/Nexa.js`, modul ini diekspor sebagai:
- `NexaLayer`
- `Layer` (alias)

Artinya bisa dipakai:

```js
const layerA = new NXUI.NexaLayer({ container: "#x" });
const layerB = new NXUI.Layer({ container: "#x" }); // alias
```

## Pola Implementasi Disarankan

1. Buat instance `NexaLayer`.
2. Generate HTML via `Container({ content })`.
3. Inject ke `container`.
4. Panggil `drop()` setelah render.

Urutan ini penting agar state restore + sortable aktif dengan benar.
