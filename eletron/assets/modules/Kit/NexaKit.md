# NexaKit — Fluent HTML Builder

**Version:** 1.0.3  
**File:** `assets/modules/Kit/NexaKit.js`  
**Global:** `NXUI.div()`, `NXUI.span()`, dll — tersedia via `window.NXUI`

---

## Daftar Isi

1. [Mengapa NexaKit?](#mengapa-nexakit)
2. [Konsep Dasar](#konsep-dasar)
3. [Builder Methods](#builder-methods)
4. [Attribute Methods](#attribute-methods)
5. [Style Shortcuts](#style-shortcuts)
6. [Container & Tree Building](#container--tree-building)
7. [Terminal Methods](#terminal-methods)
8. [.map() — Render Array](#map--render-array)
9. [.when() — Conditional Inline](#when--conditional-inline)
10. [.end() — Naik Level](#end--naik-level)
11. [.worker() — Web Worker](#worker--web-worker)
12. [.pre() — Source Block](#pre--source-block)
13. [Tag yang Tersedia](#tag-yang-tersedia)
14. [Form Shortcuts](#form-shortcuts)
15. [NXUI.Worker — Async Builder](#nxuiworker--async-builder)
16. [Contoh Lengkap](#contoh-lengkap)
17. [Changelog](#changelog)

---

## Mengapa NexaKit?

Menulis HTML secara manual di JavaScript selalu memiliki dua masalah besar: **panjang** dan **rawan typo**. NexaKit hadir untuk menyelesaikan keduanya sekaligus.

### Perbandingan Langsung

**Cara lama — string HTML manual:**
```javascript
container.innerHTML =
  '<div class="row">' +
    '<div class="col-8">' +
      '<h1 class="title">Hello World</h1>' +
      '<p class="desc">Deskripsi konten di sini.</p>' +
    '</div>' +
    '<div class="col-4">' +
      '<button onclick="openModal()" class="btn-primary">Buka Modal</button>' +
    '</div>' +
  '</div>';
```

**Cara NexaKit — fluent chain:**
```javascript
container.innerHTML =
  NXUI.div('row')
    .div('col-8')
      .h1('Hello World').class('title')
      .p('Deskripsi konten di sini.').class('desc')
    .end()
    .div('col-4')
      .btn('openModal()').class('btn-primary').view('Buka Modal')
    .end()
  .pre('code');
```

### Keuntungan NexaKit

| | HTML Manual | NexaKit |
|---|---|---|
| **Panjang kode** | Sangat panjang | 30–50% lebih singkat |
| **Typo tag** | Sering (`</div>` tertukar) | Tidak mungkin — method typed |
| **Dynamic content** | String concat berantakan | Langsung `${}` atau `.view(var)` |
| **Loop / array** | `.forEach` + `+=` | `.map(arr, fn)` inline |
| **Kondisional** | `if/else` terpisah | `.when(cond, fn)` di chain |
| **Preview + source** | Buat manual dua kali | `.pre('code')` otomatis |
| **Auto-generate** | Tidak ada | Converter HTML → NexaKit JS |

### Pola Umum yang Jadi Sangat Ringkas

```javascript
// ❌ Dulu — array jadi HTML
let html = '<ul>';
items.forEach(item => { html += `<li class="item">${item.label}</li>`; });
html += '</ul>';

// ✅ NexaKit
const html = NXUI.ul('list')
  .map(items, item => NXUI.li(item.label).class('item'))
  .end();
```

```javascript
// ❌ Dulu — conditional class
const cls = isActive ? 'btn-primary' : 'btn-secondary';
html = `<button class="${cls}">Simpan</button>`;

// ✅ NexaKit
const html = NXUI.button('Simpan')
  .when(isActive, b => b.class('btn-primary'))
  .when(!isActive, b => b.class('btn-secondary'));
```

```javascript
// ❌ Dulu — ingin tampil preview + source code
// Harus buat dua blok terpisah, escape HTML manual, setup highlight.js sendiri

// ✅ NexaKit — satu method, otomatis
const html = NXUI.div('card')
  .h1('Judul').class('heading')
  .p('Isi konten.').class('body')
  .end()
  .pre('code');  // ← preview kiri + NexaKit JS kanan, syntax highlight otomatis
```

### Auto-Generate dari HTML yang Sudah Ada

Punya HTML lama? Konversi otomatis ke NexaKit chain:
```bash
node NexaKit_convert.js input.html
# Output: kit_input.js — siap pakai
```

> NexaKit bukan pengganti HTML — ia adalah cara menulis HTML yang lebih cepat, lebih aman, dan lebih mudah di-maintain langsung dari JavaScript.

---

## Konsep Dasar

NexaKit menyediakan fluent API untuk membuat elemen HTML tanpa menulis string template secara manual. Ada dua mode utama:

| Mode | Cara pakai | Hasil |
|------|-----------|-------|
| **Standalone** | `NXUI.div().id('x').html('hi')` | String `outerHTML` element itu saja |
| **Tree / Chain** | `NXUI.div().container().p().view('hi')` | String `outerHTML` root keseluruhan |

**Aturan penting:**
- Setelah `.container()` → tag method berikutnya membuat **anak** (child)
- Setelah `.view()` → tag method berikutnya membuat **saudara** (sibling)
- `.end()` → naik satu level ke parent
- Hasil bisa langsung dipakai di template literal `${html}` atau `container.innerHTML = \`${html}\``

---

## Builder Methods

Entry point selalu dari `NXUI.<tag>()`:

```javascript
NXUI.div()       // → NexaElementBuilder
NXUI.span()
NXUI.p()
NXUI.h1()
// ... semua tag HTML tersedia
```

Method ini mengembalikan `NexaElementBuilder` yang bisa di-chain.

---

## Attribute Methods

Tersedia di `NexaElementBuilder`. Semua chainable kecuali disebutkan.

### `.id(value)`
Set atribut `id`.
```javascript
NXUI.div().id('wrapper')
// → <div id="wrapper"></div>
```

### `.class(value)`
Set `className` (mengganti semua class yang ada).
```javascript
NXUI.div().class('card active')
// → <div class="card active"></div>
```

### `.addClass(value)`
Tambahkan class tanpa menghapus class yang sudah ada.
```javascript
NXUI.div().class('card').addClass('active')
// → <div class="card active"></div>
```

### `.attr(name, value)`
Set atribut HTML sembarang.
```javascript
NXUI.a().attr('href', '/home').attr('target', '_blank')
// → <a href="/home" target="_blank"></a>
```

### `.src(value)` — shortcut src
Set atribut `src` langsung — setara `.attr('src', value)`.
```javascript
NXUI.img().src('http://localhost/assets/logo.png')
// → <img src="http://localhost/assets/logo.png">
```

### `.href(value)` — shortcut href
Set atribut `href` langsung — setara `.attr('href', value)`.
```javascript
NXUI.a().href('http://localhost/').view('Home')
// → <a href="http://localhost/">Home</a>
```

### `.a(href)` — tag shortcut
`.a()` menerima optional argument `href` langsung.
```javascript
NXUI.a('http://localhost/').view('Home')
// → <a href="http://localhost/">Home</a>

// Di dalam .map()
.map(items, (item) =>
  NXUI.a(item.url).view(item.label)
)
```

### `.img(src)` — tag shortcut
`.img()` menerima optional argument `src` langsung.
```javascript
NXUI.img('http://localhost/assets/logo.png')
// → <img src="http://localhost/assets/logo.png">
```

### `.btn(handler)` — button + onclick shortcut
Buat `<button>` dan set `onclick` sekaligus. Chainable.
```javascript
NXUI.div('c')
  .btn('openModal()').class('btn-primary').view('Buka Modal')
// → <div><button onclick="openModal()" class="btn-primary">Buka Modal</button></div>

// Sama dengan:
NXUI.div('c')
  .button().onclick('openModal()').class('btn-primary').view('Buka Modal')
```

### `.data(key, value)`
Set atribut `data-*`.
```javascript
NXUI.div().data('id', 42).data('type', 'card')
// → <div data-id="42" data-type="card"></div>
```

### `.on(event, handler)`
Pasang event handler — chainable.

- **String** → di-set sebagai atribut inline `on<event>="..."` → tetap di `outerHTML`
- **Function** → `addEventListener` (aktif jika elemen di-mount langsung via `.el()`)

```javascript
NXUI.button().on('click', "buatPackage('Buat')").view('Buat')
// → <button onclick="buatPackage('Buat')">Buat</button>

NXUI.input().on('change', "handleChange(this.value)").attr('type', 'text')
// → <input onchange="handleChange(this.value)" type="text">
```

### `.onclick(handler)`
Shortcut untuk `.on('click', handler)`.

```javascript
NXUI.p().onclick("buatPackage('Buat')").view(item.label)
// → <p onclick="buatPackage('Buat')">alerts</p>

// Di dalam .map()
.map(items, (item) =>
  NXUI.div().class('card').container()
    .p().onclick(`openDetail(${item.id})`).view(item.label)
    .span().view(item.version)
)
```

### `.style(obj)`
Set inline style dari object. Mendukung camelCase dan hyphenated.
```javascript
NXUI.span().style({
  width: '200px',
  'font-style': 'italic',
  backgroundColor: '#fff',
})
// → <span style="width: 200px; font-style: italic; background-color: #fff;"></span>
```

---

## Style Shortcuts

Shortcut style yang sering dipakai. Tersedia di `NexaElementBuilder` dan `NexaContainerBuilder`. Semua chainable.

### `.color(value)`
Set `style.color`.
```javascript
NXUI.span().color('#24292f').view('teks')
// → <span style="color: rgb(36, 41, 47);">teks</span>

NXUI.a('http://localhost/').color('red').view('link merah')
```

### `.fs(value)`
Set `style.fontSize`. Unit opsional — jika tidak ada unit maka default ke `px`.
```javascript
NXUI.p().fs('21px').view('besar')    // → font-size: 21px
NXUI.p().fs(21).view('besar')        // → font-size: 21px  (auto append px)
NXUI.p().fs('1.5rem').view('besar')  // → font-size: 1.5rem
NXUI.p().fs('2vh').view('besar')     // → font-size: 2vh
```

### `.wh(value)`
Set `style.width` dan `style.height` sekaligus dari string format `"WxH"`. Unit opsional, default `px`.
```javascript
NXUI.img('logo.png').wh('40x40')
// → style="width: 40px; height: 40px;"

NXUI.div('c').wh('100%x200px')
// → style="width: 100%; height: 200px;"

NXUI.div('c').wh('100vwx100vh')
// → style="width: 100vw; height: 100vh;"
```

> Format: `WxH` — pisahkan dengan `x` (case-insensitive). Contoh: `300x400`, `50%x100px`, `100vwx100vh`.

---

## Container & Tree Building

### `.container()`
Masuk ke mode child — tag method berikutnya membuat **anak** dari element ini.

```javascript
NXUI.div().id('wrap').container()
  .span().view('hello')
// → <div id="wrap"><span>hello</span></div>
```

Bisa nested berlapis:
```javascript
NXUI.div().id('outer').container()
  .div().id('inner').container()
    .p().view('dalam')
// → <div id="outer"><div id="inner"><p>dalam</p></div></div>
```

### `.div('c')` — shortcut container
Mengirim argumen `'c'` (atau truthy) ke tag method akan langsung masuk ke mode container — setara memanggil `.container()` setelah tag.

```javascript
// Sama hasilnya:
NXUI.div('c').span().view('hello')
NXUI.div().container().span().view('hello')
// → <div><span>hello</span></div>
```

Berlaku untuk semua tag method: `div('c')`, `section('c')`, `ul('c')`, dll.

```javascript
NXUI.div('c').id('wrap')
  .ul('c')
    .li().view('item 1')
    .li().view('item 2')
// → <div id="wrap"><ul><li>item 1</li><li>item 2</li></ul></div>
```

### `NexaContainerBuilder` — attribute methods
Setelah `.container()` atau `.div('c')`, builder berada di `NexaContainerBuilder`. Semua attribute method tetap tersedia pada container tersebut:

```javascript
NXUI.div('c').id('wrap').class('card').color('#333').fs('14px')
  .p().view('hello')
// → <div id="wrap" class="card" style="color: #333; font-size: 14px;"><p>hello</p></div>
```

Method yang tersedia di `NexaContainerBuilder`: `.id()`, `.class()`, `.addClass()`, `.attr()`, `.data()`, `.src()`, `.href()`, `.style()`, `.color()`, `.fs()`, `.wh()`, `.on()`, `.onclick()`.

---

## Terminal Methods

Method yang mengakhiri chain dan mengembalikan **string** atau **NexaViewResult**.

### `.view(content = '')`
Set `innerHTML` element — mengembalikan `NexaViewResult` (bukan string mentah).  
`NexaViewResult` bertindak sebagai string saat dipakai di template literal, tapi juga bisa dilanjutkan dengan tag method untuk membuat **sibling**.

```javascript
const html = NXUI.div().id('tes').container()
  .p().view('hello')    // NexaViewResult — chain bisa dilanjutkan
  .span().view('world') // sibling dari <p>

container.innerHTML = `${html}`;
// → <div id="tes"><p>hello</p><span>world</span></div>
```

### `.html(content)`
Set `innerHTML` — mengembalikan **string** `outerHTML` element **ini saja** (bukan root).  
Cocok untuk standalone, bukan untuk tree chain.

```javascript
const s = NXUI.span().class('badge').html('New');
// → '<span class="badge">New</span>'
```

### `.text(content)`
Set `textContent` (text di-escape otomatis) — mengembalikan string `outerHTML`.

```javascript
const s = NXUI.p().text('<script>xss</script>');
// → '<p>&lt;script&gt;xss&lt;/script&gt;</p>'
```

### `.append(child)`
Tambahkan child (string HTML, Element, atau NexaElementBuilder) — chainable.

```javascript
NXUI.div().append('<span>a</span>').append(NXUI.span().html('b'))
```

---

## .map() — Render Array

Iterasi array dan tambahkan setiap hasil sebagai anak/saudara di posisi saat ini di chain.  
Tersedia di: `NexaElementBuilder`, `NexaViewResult`, `NexaContainerBuilder`.

```javascript
.map(array, fn)
// fn: (item, index, array) → NexaElementBuilder | NexaViewResult | string | null
```

**Callback harus menggunakan `NXUI.xxx()` standalone** (bukan lanjutan chain yang sedang berjalan).

```javascript
const items = [
  { label: 'alerts', version: '1.0.0' },
  { label: 'modal',  version: '1.2.0' },
];

const html = NXUI.ul().container()
  .map(items, (item) =>
    NXUI.li('c')
      .span().view(item.label)
      .small().view(item.version)
  );

container.innerHTML = `${html}`;
// → <ul>
//     <li><span>alerts</span><small>1.0.0</small></li>
//     <li><span>modal</span><small>1.2.0</small></li>
//   </ul>
```

**Map di dalam tree bersarang:**

```javascript
const html = NXUI.div('c').id('app')
  .div('c').id('list')
    .map(items, (item) =>
      NXUI.div('c').class('card')
        .p().view(item.label)
        .span().view(item.version)
    )
  .end()                // ← kembali ke div#app
  .footer().view('end');

container.innerHTML = `${html}`;
```

### Conditional di dalam `.map()` — `if/else`

Gunakan **block function body** (kurung kurawal + `return`) untuk logika bercabang. Callback boleh mengembalikan `null` untuk melewati item.

#### Pola 1 — Persiapkan variabel dulu, pakai di tree

Paling umum: siapkan nilai dengan `if/else` atau ternary, lalu gunakan di dalam chain.

```javascript
.map(components, (item) => {
  // Persiapkan variabel dari kondisi
  const da         = item.id === 1 ? 'Active' : 'Not'
  const badgeClass = item.status   ? 'badge-success' : 'badge-danger'
  const iconColor  = item.status   ? 'green' : 'red'

  // Bangun tree pakai variabel di atas
  return NXUI.div('c')
    .h1().onclick(`buatPackage('${item.id}')`).view(item.label)
    .span().class(badgeClass).view(da)
    .btn('openModal()').class('btn-primary').view('Buka Modal')
    .icon('octicon octicon-mark-github-16').color(iconColor)
    .small().class('sss').view(item.description)
})
```

> **Catatan:** wajib pakai `{}` block body + `return` jika ada `const`/`let`/`if` di dalam callback.

#### Pola 2 — Skip item, kembalikan `null`

```javascript
.map(components, (item) => {
  if (!item.status) return null   // item tidak ditampilkan sama sekali

  return NXUI.div('c').p().view(item.label)
})
```

#### Pola 3 — Tree berbeda per kondisi

```javascript
.map(components, (item) => {
  if (item.type === 'premium') {
    return NXUI.div('c').class('card premium')
      .h1().onclick(`openDetail(${item.id})`).view(item.label)
      .icon('bi bi-star').color('#f59e0b')
  }

  return NXUI.div('c').class('card')
    .h1().onclick(`openDetail(${item.id})`).view(item.label)
    .span().view(item.version)
})
```

### Conditional inline dalam chain — `.when(condition, fn)`

`.when()` mengeksekusi `fn(builder)` jika condition truthy, tetap lanjut chain jika false. Tersedia di semua builder classes.

```javascript
.map(components, (item) =>
  NXUI.div('c')
    .h1().onclick(`buatPackage('${item.id}')`).view(item.label)
    .when(item.version, b => b.a('http://localhost/').fs('14px').view(item.version))
    .btn('openModal()').class('btn-primary').view('Buka Modal')
    .when(item.icon,    b => b.icon(item.icon).color('#24292f'))
    .small().class('sss').view(item.desc)
)
```

> **Aturan `.when()`:**
> - `fn` menerima posisi chain saat ini sebagai argumen
> - `fn` harus mengembalikan builder (hasil chain baru) agar chain berlanjut dari posisi baru
> - Jika condition false, `.when()` mengembalikan `this` — chain berlanjut dari posisi sebelumnya
> - Cocok untuk elemen opsional: icon, badge, label kondisional, dll

---

## .when() — Conditional Inline

Eksekusi `fn(builder)` jika condition **truthy**, tetap di posisi chain yang sama jika **falsy**.  
Tersedia di semua builder classes: `NexaElementBuilder`, `NexaViewResult`, `NexaContainerBuilder`, `NexaKit`.

```javascript
.when(condition, fn)
// condition : nilai apapun — truthy/falsy
// fn        : (builder) → builder  — harus mengembalikan posisi chain baru
```

### Contoh dasar

```javascript
// Tampilkan badge hanya jika item.isNew === true
NXUI.div('c').class('card')
  .span().view(item.label)
  .when(item.isNew, b => b.span().class('badge-new').view('NEW'))
  .small().view(item.version)
// Jika item.isNew truthy  → <div><span>label</span><span class="badge-new">NEW</span><small>1.0.0</small></div>
// Jika item.isNew falsy   → <div><span>label</span><small>1.0.0</small></div>
```

### Di dalam `.map()`

```javascript
.map(components, (item) =>
  NXUI.div('c')
    .h1().onclick(`buatPackage('${item.id}')`).view(item.label)
    .when(item.version, b => b.a('http://localhost/').fs('14px').view(item.version))
    .btn('openModal()').class('btn-primary').view('Buka Modal')
    .when(item.icon, b => b.icon(item.icon).color('#24292f'))
    .small().class('sss').view(item.desc)
)
```

### Multiple `.when()` berurutan

```javascript
NXUI.div('c').class('card')
  .h1().view(item.label)
  .when(item.isPremium,  b => b.icon('bi bi-star').color('#f59e0b'))
  .when(item.isVerified, b => b.icon('bi bi-check-circle').color('green'))
  .when(item.desc,       b => b.p().color('#666').view(item.desc))
  .small().view(item.version)
```

### Perbedaan `.when()` vs `if/else`

| | `.when()` | `if/else` block |
|---|---|---|
| Syntax | Inline dalam arrow expression | Block body `{}` + `return` |
| Cocok untuk | Elemen opsional dalam satu tree | Tree berbeda total per kondisi |
| Skip item | Tidak | `return null` untuk skip |

```javascript
// ✅ .when() — elemen opsional, tree sama
.map(items, (item) =>
  NXUI.div('c')
    .p().view(item.label)
    .when(item.badge, b => b.span().class('badge').view(item.badge))
)

// ✅ if/else — tree berbeda total
.map(items, (item) => {
  if (!item.status) return null
  if (item.type === 'premium') {
    return NXUI.div('c').class('premium').h1().view(item.label)
  }
  return NXUI.div('c').p().view(item.label)
})
```

---

## .end() — Naik Level

Kembali satu level ke parent di dalam tree.  
Aman dipanggil di root (tidak akan melempar error — tetap di root).

```javascript
NXUI.div().id('outer').container()
  .div().id('inner').container()
    .p().view('dalam')
  .end()                 // ← kembali ke div#outer level
  .span().view('luar')   // sibling dari div#inner

// → <div id="outer">
//     <div id="inner"><p>dalam</p></div>
//     <span>luar</span>
//   </div>
```

**`.end()` berlapis:**

```javascript
NXUI.div().container()
  .div().container()
    .div().container()
      .p().view('level 3')
    .end()   // → level 2
  .end()     // → level 1
  .span().view('level 1 sibling')
```

---

## .worker() — Web Worker

Serialize tree saat ini dan render ulang di **Web Worker thread** (non-blocking).  
Mengembalikan `Promise<string>`.

Fallback otomatis ke `Promise.resolve(outerHTML)` jika Worker belum siap.

```javascript
// Pakai async/await
const html = await NXUI.div().id('app').container()
  .h1().view('Hello')
  .p().view('World')
  .worker();

container.innerHTML = html;
```

**Gabung dengan .map() untuk array besar:**

```javascript
const html = await NXUI.ul().container()
  .map(bigArray, (item) =>
    NXUI.li().container()
      .span().view(item.name)
      .small().view(item.desc)
  )
  .worker();

container.innerHTML = html;
```

> **Catatan:** `.worker()` menggunakan `NexaBuilderWorker.js` di belakang layar.  
> Setup tidak diperlukan — `NXUI.Worker` terhubung otomatis saat Nexa.js boot.

---

## .pre() — Source Block

Dua perilaku berbeda tergantung argumen:

### `.pre()` — tanpa argumen
Buat elemen `<pre>` sebagai **sibling** (sama seperti tag method lain).

```javascript
NXUI.div().container()
  .p().view('hello')
  .pre()               // → sibling <pre>
```

### `.pre('code')` — dengan argumen (terminal)
**Terminal** — mengembalikan HTML element + blok `<pre><code>` berisi source yang sudah di-format dan di-escape.  
Berguna untuk halaman dokumentasi / preview komponen.

```javascript
const html = NXUI.div().id('demo').container()
  .h1().view('Title')
  .span().view('hello world 2').pre('code');

container.innerHTML = html;
// Menampilkan: elemen div#demo + blok source code terformat di bawahnya
```

---

## Tag yang Tersedia

Semua method berikut tersedia di `NXUI`, `NexaElementBuilder`, `NexaViewResult`, dan `NexaContainerBuilder`:

```
div  span  p  a(href)  button  btn(handler)  input  select  textarea
fieldset  legend  dl  dt  dd
ul  ol  li
h1  h2  h3  h4  h5  h6
img(src)
form  label
section  article  header  footer  nav  main  aside
table  thead  tbody  tr  td  th
strong  em  small  code  pre  blockquote
icon(classes)
```

**Tag dengan argumen opsional:**

| Tag | Argumen | Efek |
|-----|---------|------|
| `div('c')` | `'c'` atau truthy | Langsung masuk container mode |
| `a(href)` | string URL | Set `href` otomatis |
| `img(src)` | string URL | Set `src` otomatis |
| `btn(handler)` | string handler | Buat `<button onclick="...">` |
| `icon(classes)` | string class | Buat `<i class="...">` |
| `fieldset('c')` | `'c'` | Container dengan support `disabled` |
| `dl('c')` | `'c'` | Container definition list (pola form-group) |

### `.icon(classes)` — ikon shortcut
Buat elemen `<i>` sebagai sibling/child dengan class yang diberikan. Chainable.

```javascript
// Di dalam tree — sebagai sibling
NXUI.div('c')
  .span().view('label')
  .icon('octicon octicon-mark-github-16').color('#24292f')
// → <div><span>label</span><i class="octicon octicon-mark-github-16" style="color: #24292f;"></i></div>

// Di dalam .map()
.map(items, (item) =>
  NXUI.div('c')
    .p().view(item.label)
    .icon('bi bi-check-circle').color('green')
)
```

---

## Form Shortcuts

Method factory khusus di `NXUI` (NexaKit) untuk pattern form yang umum dipakai.
Setiap method langsung menghasilkan elemen dengan class CSS yang tepat.

| Method | HTML yang dihasilkan | Keterangan |
|--------|---------------------|------------|
| `.formGroup()` | `<div class="form-group">` | Container field form |
| `.formGroup('required')` | `<div class="form-group required">` | State: `required`\|`errored`\|`warn`\|`successful`\|`loading`\|`flattened` |
| `.inputGroup()` | `<div class="input-group">` | Input + tombol inline |
| `.inputGroup('inline')` | `<div class="input-group inline">` | Prefix icon/text di kiri |
| `.inputGroupBtn()` | `<div class="input-group-button">` | Wrapper tombol dalam input-group |
| `.hfields()` | `<div class="hfields">` | Horizontal fields (beberapa form-group sejajar) |
| `.formCheckbox()` | `<div class="form-checkbox">` | Checkbox / radio wrapper |
| `.radioGroup()` | `<div class="radio-group">` | Radio button group |
| `.formActions()` | `<div class="form-actions">` | Tombol submit/batal |
| `.formWarning()` | `<div class="form-warning">` | Kotak peringatan |
| `.formControl('text')` | `<input class="form-control" type="text">` | Input field; default type `text` |
| `.formSelect()` | `<select class="form-select">` | Select dropdown |
| `.note()` | `<p class="note">` | Helper text / catatan |

### Contoh — form-group lengkap

```javascript
const html =
  NXUI.formGroup('required')
    .dl('c')
      .dt('c').tag('label').attr('for', 'email').view('Email').end()
      .dd('c')
        .formControl('email').attr('id','email').attr('placeholder','email@contoh.com')
        .note().view('Format: nama@domain.com')
      .end()
    .end();
```

### Contoh — input-group (search bar)

```javascript
const html =
  NXUI.inputGroup()
    .formControl('text').attr('placeholder','Cari...')
    .inputGroupBtn()
      .btn('doSearch()').class('btn').view('Cari')
    .end();
```

### Contoh — form-group errored

```javascript
const html =
  NXUI.formGroup('errored')
    .dl('c')
      .dt('c').tag('label').view('Password').end()
      .dd('c')
        .formControl('password').attr('id','pwd')
        .div().class('error').view('Password minimal 8 karakter.')
      .end()
    .end();
```

### Contoh — fieldset disabled

```javascript
const html =
  NXUI.fieldset('c').attr('disabled','')
    .formGroup()
      .dl('c')
        .dt('c').tag('label').view('Input Disabled').end()
        .dd('c')
          .formControl().attr('value','Tidak bisa diedit')
          .formSelect()
            .tag('option').view('Pilihan disabled')
          .end()
        .end()
      .end()
    .end();
```

---

## NXUI.Worker — Async Builder

`NXUI.Worker` adalah API terpisah untuk menjalankan builder **murni string** di Web Worker.  
Tidak menggunakan DOM — cocok untuk rendering array besar tanpa memblok UI.

### `NXUI.Worker.build(spec)`
Build HTML dari spec tree object.

```javascript
const html = await NXUI.Worker.build({
  tag: 'ul',
  c: [
    { tag: 'li', t: 'Item 1' },
    { tag: 'li', t: 'Item 2' },
  ]
});
```

**Spec format:**
```javascript
{
  tag: 'div',                          // nama tag
  a: { id: 'x', class: 'card' },      // attributes
  s: { color: 'red' },                 // inline styles
  c: [ /* child specs */ ],            // children
  t: 'text content',                   // text (di-escape otomatis)
}
```

### `NXUI.Worker.buildMap(data, fn)`
Map array di worker thread. `fn` menerima `(item, index, NX)` di mana `NX` adalah builder worker (mirip `NXUI` tapi pure string).

```javascript
const html = await NXUI.Worker.buildMap(components, (item, i, NX) =>
  NX.div().class('card').container()
    .p().view(item.label)
    .span().view(item.version)
);

container.innerHTML = html;
```

> **Penting:** Di dalam callback `buildMap`, gunakan `NX.xxx()` bukan `NXUI.xxx()`.  
> `NXUI` tidak tersedia di worker context.

### `NXUI.Worker.pretty(html)`
Pretty-print HTML string di worker (async).

```javascript
const pretty = await NXUI.Worker.pretty(rawHtml);
console.log(pretty);
```

### `NXUI.Worker.ready`
Boolean — apakah Worker berhasil diinisialisasi.

```javascript
if (NXUI.Worker.ready) {
  // worker tersedia
}
```

---

## Contoh Lengkap

### 1. Elemen sederhana

```javascript
// Standalone — langsung jadi string
const badge = NXUI.span().class('badge').style({ color: 'red' }).html('NEW');
// → <span class="badge" style="color: red;">NEW</span>
```

### 2. Tree satu level

```javascript
const html = NXUI.div().id('tes').class('card').container()
  .h1().class('title').view('Judul')
  .p().view('Deskripsi singkat.');

container.innerHTML = `${html}`;
// → <div id="tes" class="card">
//     <h1 class="title">Judul</h1>
//     <p>Deskripsi singkat.</p>
//   </div>
```

### 3. Tree bersarang + .end()

```javascript
const html = NXUI.div().id('app').container()
  .nav().container()
    .a().attr('href', '/').view('Home')
    .a().attr('href', '/about').view('About')
  .end()                               // ← kembali ke div#app
  .main().container()
    .h1().view('Content')
  .end()
  .footer().view('© 2026');

container.innerHTML = `${html}`;
```

### 4. .map() dengan data array

```javascript
const components = [
  { id: 1, label: 'alerts',       version: '1.0.0' },
  { id: 2, label: 'autocomplete', version: '1.0.0' },
  { id: 3, label: 'avatars',      version: '1.0.0' },
];

const html = NXUI.div('c').id('tes').class('bold2')
  .div('c').id('level')
    .h1().class('bold').view('Komponen')
    .div('c')
      .map(components, (item) =>
        NXUI.div('c')
          .p().view(item.label)
          .span().view(item.version)
      )
    .end()
  .end()
  .a('http://localhost/').class('link').view('Lihat semua')
  .span().view('v1.0.2').pre('code');

container.innerHTML = `${html}`;
```

### 7. .map() dengan style shortcuts dan .icon()

```javascript
const components = [
  { id: 1, label: 'alerts', version: '1.0.0', icon: 'bi bi-bell' },
];

const html = NXUI.div('c').id('app')
  .div('c').id('list')
    .map(components, (item) =>
      NXUI.div('c')
        .h1().onclick(`openDetail(${item.id})`).view(item.label)
        .a(item.url).color('#bc4c00').fs('14px').view(item.version)
        .btn('openModal()').class('btn-primary').view('Buka')
        .icon(item.icon).color('#24292f')
        .small().view(item.label)
    )
  .end()
  .div('c').class('footer')
    .img('http://localhost/assets/logo.png').wh('40x40').end()
  .span().color('#888').fs('0.85rem').view('© 2026').pre('code');

container.innerHTML = `${html}`;
```

### 5. .worker() untuk array besar (non-blocking)

```javascript
const data = await fetch('/api/products').then(r => r.json());

const html = await NXUI.div().id('list').container()
  .map(data, (item) =>
    NXUI.div().class('product-card').container()
      .img().attr('src', item.image).attr('alt', item.name)
      .h3().view(item.name)
      .p().view(item.desc)
      .span().class('price').view(`Rp ${item.price}`)
  )
  .worker();

container.innerHTML = html;
```

### 6. NXUI.Worker.buildMap — map di worker

```javascript
const html = await NXUI.Worker.buildMap(data, (item, i, NX) =>
  NX.li().class('item').container()
    .span().class('name').view(item.name)
    .small().class('meta').view(item.created_at)
);

document.querySelector('#results').innerHTML = html;
```

---

## Changelog

### v1.0.4
- Tambah tag: `select`, `fieldset`, `legend`, `dl`, `dt`, `dd` di semua 4 class builder
- Tambah tag yang sudah ada di `NexaKit` factory tapi belum di builder: `form`, `label`, `header`, `main`, `table`
- Tambah **Form Shortcuts** di `NXUI` (NexaKit factory): `.formGroup()`, `.inputGroup()`, `.inputGroupBtn()`, `.hfields()`, `.formCheckbox()`, `.radioGroup()`, `.formActions()`, `.formWarning()`, `.formControl()`, `.formSelect()`, `.note()`
- `.pre('code')` otomatis highlight via `window.hljs.highlightElement()` setelah DOM render (`requestAnimationFrame`) jika `highlight.js` tersedia

### v1.0.3
- Tambah `.color(value)` — shortcut `style.color` di `NexaElementBuilder` dan `NexaContainerBuilder`
- Tambah `.fs(value)` — shortcut `style.fontSize`, auto-append `px` jika tanpa unit
- Tambah `.wh("WxH")` — shortcut set width & height sekaligus dari string format `WxH`
- Tambah `.src(value)` — shortcut `.attr('src', value)`
- Tambah `.href(value)` — shortcut `.attr('href', value)`
- Tambah `.btn(handler)` — shortcut buat `<button onclick>` sekaligus
- Tambah `.icon(classes)` — shortcut buat `<i class="...">` sebagai sibling/child
- Tag `.a(href)` dan `.img(src)` kini menerima argumen opsional untuk set atribut langsung
- Semua tag method kini menerima argumen `'c'`/truthy untuk langsung masuk container mode (`.div('c')` = `.div().container()`)
- `NexaContainerBuilder` kini memiliki attribute methods lengkap: `.id()`, `.class()`, `.addClass()`, `.attr()`, `.data()`, `.src()`, `.href()`, `.style()`, `.color()`, `.fs()`, `.wh()`, `.on()`, `.onclick()`
- Tambah `.when(condition, fn)` — conditional inline di semua builder classes; eksekusi `fn(builder)` jika truthy, tetap di chain jika falsy
- Fix: `_nxApplyMap` kini mendukung callback yang berakhir dengan `NexaElementBuilder` bertipe connected (chain berakhir di `.icon()`, `.color()`, dll — bukan `.view()`)

### v1.0.2
- Tambah `.worker()` terminal di `NexaElementBuilder`, `NexaViewResult`, `NexaContainerBuilder`
- Tambah `NXUI.Worker` — lazy-init singleton ke `NexaBuilderWorker.js`
- Tambah `NXUI.Worker.build(spec)`, `buildMap(data, fn)`, `pretty(html)`, `ready`
- `NexaBuilderWorker.js` — pure string builder untuk Web Worker (tanpa DOM)
- `NexaWorkerClient.js` — tambah `build()`, `buildMap()`, `pretty()`
- `_nxSetWorker()` — wiring otomatis dari `Nexa.js` saat boot
- `_nxDomToSpec()` — konversi DOM element ke plain spec object

### v1.0.1
- Tambah `.map(array, fn)` di semua builder classes
- Tambah `.end()` untuk navigasi level
- Tambah `.pre('code')` source block terminal
- `NexaViewResult` — chainable setelah `.view()`
- `NexaContainerBuilder` — tree building via `.container()`

### v1.0.0
- `NexaElementBuilder` — fluent builder standalone
- Method: `.id()`, `.class()`, `.addClass()`, `.attr()`, `.data()`, `.style()`
- Terminal: `.html()`, `.text()`, `.view()`
- Tag shortcuts di `NXUI`: `div`, `span`, `p`, `a`, `button`, `input`, `ul`, `ol`, `li`, `h1`–`h6`, `img`, `section`, `article`, `footer`, `nav`, `aside`, `thead`, `tbody`, `tr`, `td`, `th`, `strong`, `em`, `small`, `code`, `pre`, `blockquote`


