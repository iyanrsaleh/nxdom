# NexaCmirror Documentation

## 📖 Pengenalan

**NexaCmirror** adalah class wrapper untuk CodeMirror editor yang memudahkan penggunaan dan pengembangan editor kode dalam sistem Nexa. Class ini menyediakan konfigurasi otomatis untuk berbagai mode bahasa pemrograman, autocomplete yang cerdas, dan theme yang disesuaikan secara otomatis.

## ✨ Fitur Utama

- ✅ **Multi-mode Support**: HTML, JavaScript, CSS, SQL, JSON
- ✅ **Auto Theme**: Theme otomatis disesuaikan berdasarkan mode
- ✅ **Smart Autocomplete**: Autocomplete cerdas untuk setiap mode
- ✅ **Auto-close Tags**: Auto-close HTML tags dan brackets
- ✅ **Keyboard Shortcuts**: Ctrl+Space, Tab, Enter untuk autocomplete
- ✅ **Event Handling**: Event listeners untuk cursor positioning dan autocomplete
- ✅ **Modular Design**: Mudah dikembangkan dan diintegrasikan

## 🚀 Instalasi & Setup

### Opsi 1: Dynamic Loading (Recommended)

Gunakan `NexaCmirror.loadDependencies()` untuk memuat semua dependencies secara dinamis menggunakan `NexaStylesheet` dan `NexaScript`:

```javascript
import { NexaCmirror } from "{dash/NexaCmirror.js}";

// Load dependencies terlebih dahulu
await NexaCmirror.loadDependencies();

// Setelah dependencies loaded, buat instance
const editor = new NexaCmirror('codeEditor', {
  mode: 'htmlmixed'
});
```

**Atau dengan auto-loading** (jika NXUI tersedia):

```javascript
import { NexaCmirror } from "{dash/NexaCmirror.js}";

// Jika NXUI tersedia, dependencies akan di-load otomatis
const editor = new NexaCmirror('codeEditor', {
  mode: 'htmlmixed'
});
```

### Opsi 2: Manual Loading (Traditional)

Tambahkan CDN links untuk CodeMirror di HTML:

```html
<!-- CSS -->
<link href='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/codemirror.css' rel='stylesheet'>
<link href='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/theme/monokai.css' rel='stylesheet'>
<link href='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/show-hint.css' rel='stylesheet'>

<!-- JavaScript Core -->
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/codemirror.js'></script>

<!-- Modes -->
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/xml/xml.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/css/css.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/javascript/javascript.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/htmlmixed/htmlmixed.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/sql/sql.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/mode/javascript/json.js'></script>

<!-- Addons -->
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/edit/closetag.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/edit/closebrackets.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/show-hint.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/xml-hint.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/html-hint.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/javascript-hint.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/css-hint.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.16.0/addon/hint/sql-hint.js'></script>
```

### Import NexaCmirror Class

```javascript
import { NexaCmirror } from "{dash/NexaCmirror.js}";
```

### 3. HTML Structure

```html
<div class="NexaCmirror-editorContainer">
  <div class="NexaCmirror-editorToolbar">
    <button id="btnCopy" class="NexaCmirror-btn-copy">📋 Copy</button>
    <button id="btnSave" class="NexaCmirror-btn-save">💾 Save</button>
  </div>
  <textarea id="codeEditor"><!-- Your code here --></textarea>
</div>
```

## 📝 Penggunaan Dasar

### Inisialisasi Sederhana

```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'htmlmixed' // Mode: 'htmlmixed', 'javascript', 'css', 'sql', 'json'
});
```

### Inisialisasi dengan Konfigurasi

```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'javascript',
  theme: 'monokai', // Optional: override auto theme
  lineNumbers: true,
  lineWrapping: true
});
```

## 🎨 Mode yang Didukung

### 1. HTML Mode (`htmlmixed`)

**Theme**: Monokai (gelap)

**Fitur**:
- Autocomplete HTML tags (div, span, img, dll)
- Autocomplete HTML attributes (class, id, style, dll)
- Auto-close tags saat Enter
- CSS autocomplete di dalam `<style>` tag
- CSS autocomplete di dalam atribut `style=""`
- Auto-insert default attributes (contoh: `<img src="">`)

**Contoh**:
```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'htmlmixed'
});
```

### 2. JavaScript Mode (`javascript`)

**Theme**: Monokai (gelap)

**Fitur**:
- Autocomplete JavaScript keywords
- Autocomplete JavaScript functions
- Syntax highlighting

**Contoh**:
```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'javascript'
});
```

### 3. CSS Mode (`css`)

**Theme**: Monokai (gelap)

**Fitur**:
- Autocomplete CSS properties
- Autocomplete CSS values
- Syntax highlighting

**Contoh**:
```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'css'
});
```

### 4. SQL Mode (`sql`)

**Theme**: Default (putih)

**Fitur**:
- Autocomplete SQL keywords lengkap
- Syntax highlighting
- Support untuk DDL, DML, DCL, TCL

**Contoh**:
```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'sql'
});
```

### 5. JSON Mode (`json`)

**Theme**: Default (putih)

**Fitur**:
- Syntax highlighting JSON
- Autocomplete JSON
- Validasi format JSON

**Contoh**:
```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: { name: 'javascript', json: true }
});
```

## ⚙️ Konfigurasi

### Default Options

```javascript
{
  lineNumbers: true,           // Tampilkan nomor baris
  mode: 'htmlmixed',          // Mode default
  theme: 'monokai',           // Theme default (akan disesuaikan otomatis)
  indentUnit: 2,              // Ukuran indent
  lineWrapping: true,         // Wrap baris panjang
  autoCloseTags: true,        // Auto-close HTML tags
  autoCloseBrackets: true,     // Auto-close brackets
  tabSize: 2,                 // Ukuran tab
  indentWithTabs: false,      // Gunakan spaces bukan tabs
  smartIndent: true,          // Smart indentation
  matchBrackets: true,        // Highlight matching brackets
  autoFocus: false,           // Auto focus saat load
  readOnly: false,            // Read-only mode
  cursorBlinkRate: 530,       // Cursor blink rate
  lineWiseCopyCut: true,      // Copy/cut per baris
  pasteLinesPerSelection: true // Paste per selection
}
```

### Theme Auto-Configuration

Theme otomatis disesuaikan berdasarkan mode:

- **SQL & JSON** → `'default'` (putih)
- **HTML, JavaScript, CSS** → `'monokai'` (gelap)

Anda bisa override theme secara manual:

```javascript
const editor = new NexaCmirror('codeEditor', {
  mode: 'sql',
  theme: 'monokai' // Override: gunakan theme gelap untuk SQL
});
```

## 🔧 API Methods

### Content Management

#### `getValue()`
Mendapatkan konten editor.

```javascript
const content = editor.getValue();
console.log(content);
```

#### `setValue(content)`
Mengatur konten editor.

```javascript
editor.setValue('console.log("Hello World");');
```

### Mode Management

#### `setMode(mode)`
Mengubah mode editor. Theme akan otomatis disesuaikan.

```javascript
// Ubah ke JavaScript mode
editor.setMode('javascript');

// Ubah ke SQL mode
editor.setMode('sql');

// Ubah ke JSON mode
editor.setMode({ name: 'javascript', json: true });
```

### Theme Management

#### `setTheme(theme)`
Mengubah theme editor.

```javascript
// Ubah ke theme gelap
editor.setTheme('monokai');

// Ubah ke theme putih
editor.setTheme('default');
```

### Editor Control

#### `focus()`
Focus ke editor.

```javascript
editor.focus();
```

#### `refresh()`
Refresh editor (untuk resize atau perubahan layout).

```javascript
editor.refresh();
```

#### `setReadOnly(readOnly)`
Mengatur read-only mode.

```javascript
editor.setReadOnly(true);  // Read-only
editor.setReadOnly(false); // Editable
```

### Cursor Management

#### `getCursor()`
Mendapatkan posisi cursor.

```javascript
const cursor = editor.getCursor();
console.log(cursor.line);  // Nomor baris
console.log(cursor.ch);    // Posisi karakter
```

#### `setCursor(line, ch)`
Mengatur posisi cursor.

```javascript
editor.setCursor(5, 10); // Baris 5, karakter ke-10
```

### Selection Management

#### `getSelection()`
Mendapatkan text yang ter-select.

```javascript
const selected = editor.getSelection();
console.log(selected);
```

#### `replaceSelection(text)`
Mengganti text yang ter-select.

```javascript
editor.replaceSelection('new text');
```

### Line Management

#### `lineCount()`
Mendapatkan jumlah baris.

```javascript
const count = editor.lineCount();
console.log(`Total lines: ${count}`);
```

#### `getLine(line)`
Mendapatkan text di baris tertentu.

```javascript
const line5 = editor.getLine(5);
console.log(line5);
```

#### `setLine(line, text)`
Mengatur text di baris tertentu.

```javascript
editor.setLine(5, 'new line content');
```

#### `removeLine(line)`
Menghapus baris.

```javascript
editor.removeLine(5);
```

### Event Management

#### `on(event, callback)`
Menambahkan event listener.

```javascript
editor.on('change', (cm, change) => {
  console.log('Content changed:', change);
});

editor.on('focus', () => {
  console.log('Editor focused');
});
```

#### `off(event, callback)`
Menghapus event listener.

```javascript
const handler = () => console.log('changed');
editor.on('change', handler);
editor.off('change', handler);
```

### History Management

#### `undo()`
Undo perubahan.

```javascript
editor.undo();
```

#### `redo()`
Redo perubahan.

```javascript
editor.redo();
```

### Utility

#### `getEditor()`
Mendapatkan instance CodeMirror asli (untuk akses advanced).

```javascript
const cmEditor = editor.getEditor();
// Akses semua method CodeMirror
cmEditor.setOption('lineNumbers', false);
```

#### `save()`
Menyimpan konten ke textarea (jika menggunakan fromTextArea).

```javascript
editor.save();
```

#### `destroy()`
Menghancurkan instance editor.

```javascript
editor.destroy();
```

## ⌨️ Keyboard Shortcuts

### Autocomplete

- **Ctrl + Space**: Trigger autocomplete manual
- **Tab**: Trigger autocomplete atau indent selection

### Auto-close Tags (HTML Mode)

- **Enter**: Auto-close HTML tag dan insert closing tag dengan indent yang benar

**Contoh**:
```html
<div>|  <!-- Cursor di sini -->
```
Tekan Enter akan menghasilkan:
```html
<div>
  |  <!-- Cursor di sini dengan indent -->
</div>
```

## 🎯 Contoh Penggunaan Lengkap

### Contoh 1: HTML Editor dengan Save & Copy

```html
<!DOCTYPE html>
<html>
<head>
  <!-- Include CodeMirror CSS & JS -->
</head>
<body>
  <div class="NexaCmirror-editorContainer">
    <div class="NexaCmirror-editorToolbar">
      <button id="btnCopy" class="NexaCmirror-btn-copy">📋 Copy</button>
      <button id="btnSave" class="NexaCmirror-btn-save">💾 Save</button>
    </div>
    <textarea id="codeEditor"><div>Hello World</div></textarea>
  </div>

  <script type="module">
    import { NexaCmirror } from "{dash/NexaCmirror.js}";
    
    const editor = new NexaCmirror('codeEditor', {
      mode: 'htmlmixed'
    });
    
    // Setup Save button
    document.getElementById('btnSave').addEventListener('click', () => {
      const content = editor.getValue();
      localStorage.setItem('editorContent', content);
      alert('Saved!');
    });
    
    // Setup Copy button
    document.getElementById('btnCopy').addEventListener('click', () => {
      const content = editor.getValue();
      navigator.clipboard.writeText(content);
      alert('Copied!');
    });
  </script>
</body>
</html>
```

### Contoh 2: Dynamic Mode Switching

```javascript
import { NexaCmirror } from "{dash/NexaCmirror.js}";

const editor = new NexaCmirror('codeEditor', {
  mode: 'htmlmixed'
});

// Switch mode dengan dropdown
document.getElementById('modeSelect').addEventListener('change', (e) => {
  const mode = e.target.value;
  
  if (mode === 'json') {
    editor.setMode({ name: 'javascript', json: true });
  } else {
    editor.setMode(mode);
  }
});
```

### Contoh 3: Custom Event Handling

```javascript
import { NexaCmirror } from "{dash/NexaCmirror.js}";

const editor = new NexaCmirror('codeEditor', {
  mode: 'javascript'
});

// Listen untuk perubahan
editor.on('change', (cm, change) => {
  console.log('Content changed:', change);
  
  // Auto-save setiap perubahan
  const content = editor.getValue();
  localStorage.setItem('autoSave', content);
});

// Listen untuk focus
editor.on('focus', () => {
  console.log('Editor focused');
});

// Listen untuk blur
editor.on('blur', () => {
  console.log('Editor blurred');
});
```

## 🎨 CSS Styling

Semua class menggunakan prefix `NexaCmirror-` untuk menghindari konflik:

```css
.NexaCmirror-editorContainer {
  /* Container styling */
}

.NexaCmirror-editorToolbar {
  /* Toolbar styling */
}

.NexaCmirror-btn-save,
.NexaCmirror-btn-copy {
  /* Button styling */
}

.NexaCmirror-save-notification,
.NexaCmirror-copy-notification {
  /* Notification styling */
}
```

## 🔍 Autocomplete Features

### HTML Mode

- **Tags**: Autocomplete untuk semua HTML tags
- **Attributes**: Autocomplete untuk HTML attributes dengan auto-insert `=""`
- **Default Attributes**: Tag seperti `<img>` otomatis insert `src=""`
- **Cursor Positioning**: Cursor otomatis dipindahkan ke dalam quotes setelah attribute completion

### JavaScript Mode

- **Keywords**: Autocomplete untuk JavaScript keywords
- **Functions**: Autocomplete untuk built-in functions

### CSS Mode

- **Properties**: Autocomplete untuk CSS properties
- **Values**: Autocomplete untuk CSS values
- **In HTML**: CSS autocomplete juga aktif di dalam `<style>` tag dan `style=""` attribute

### SQL Mode

- **Keywords**: Autocomplete lengkap untuk SQL keywords (DDL, DML, DCL, TCL)
- **Functions**: Autocomplete untuk SQL functions

### JSON Mode

- **Syntax Highlighting**: Warna untuk keys, values, strings, numbers
- **Autocomplete**: Autocomplete untuk JSON structure

## 🐛 Troubleshooting

### Syntax Highlighting Tidak Muncul

**Masalah**: JSON mode tidak menampilkan warna

**Solusi**: Pastikan menggunakan format object untuk JSON mode:
```javascript
mode: { name: 'javascript', json: true }
```

### Autocomplete Tidak Muncul

**Masalah**: Autocomplete tidak muncul saat mengetik

**Solusi**: 
1. Pastikan semua addon hint sudah di-load
2. Gunakan `Ctrl+Space` untuk trigger manual
3. Pastikan mode sudah benar

### Theme Tidak Sesuai

**Masalah**: Theme tidak sesuai dengan mode

**Solusi**: Theme otomatis disesuaikan, tapi bisa di-override:
```javascript
editor.setTheme('monokai'); // Force theme gelap
```

## 📚 Referensi

- [CodeMirror Documentation](https://codemirror.net/doc/manual.html)
- [CodeMirror Modes](https://codemirror.net/mode/)
- [CodeMirror Themes](https://codemirror.net/demo/theme.html)

## 📝 Changelog

### Version 1.0.0
- ✅ Initial release
- ✅ Support untuk HTML, JavaScript, CSS, SQL, JSON
- ✅ Auto theme configuration
- ✅ Smart autocomplete
- ✅ Auto-close tags
- ✅ Event handling

---

**Dibuat untuk sistem Nexa** - Memudahkan pengembangan dengan CodeMirror editor yang powerful dan mudah digunakan.

