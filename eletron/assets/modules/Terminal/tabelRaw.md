# TabelRaw.js - Dokumentasi

## 📋 Deskripsi

`TabelRaw` adalah komponen untuk membuat tabel ASCII dengan border karakter seperti terminal tradisional. Komponen ini dirancang khusus untuk digunakan dalam terminal command line interface dengan output yang rapi dan mudah dibaca.

## 🚀 Instalasi & Import

```javascript
import { TabelRaw, createTable, createTableHTML } from './tabelRaw.js';
```

## 📖 API Reference

### Class: `TabelRaw`

#### Constructor

```javascript
new TabelRaw(data, options)
```

**Parameters:**
- `data` (Array): Array of objects yang akan ditampilkan dalam tabel
- `options` (Object): Konfigurasi tabel (opsional)

**Example:**
```javascript
const data = [
    { id: 1, name: 'John', age: 30 },
    { id: 2, name: 'Jane', age: 25 }
];

const table = new TabelRaw(data, {
    border: true,
    headerStyle: 'double',
    showIndex: true
});
```

#### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `border` | Boolean | `true` | Menampilkan border karakter ASCII |
| `headerStyle` | String | `'double'` | Style header: `'single'`, `'double'`, `'bold'` |
| `align` | String | `'left'` | Default alignment: `'left'`, `'center'`, `'right'` |
| `columnAlign` | Object | `{}` | Alignment per kolom: `{ columnName: 'center' }` |
| `maxWidth` | Number | `120` | Maximum lebar tabel (karakter) |
| `truncate` | Boolean | `true` | Memotong teks yang terlalu panjang |
| `showIndex` | Boolean | `false` | Menampilkan nomor index di kolom pertama |
| `indexHeader` | String | `'#'` | Header untuk kolom index |

#### Methods

##### `render()`

Mengembalikan string ASCII table yang sudah diformat.

**Returns:** `String`

**Example:**
```javascript
const table = new TabelRaw(data);
const asciiTable = table.render();
console.log(asciiTable);
```

**Output:**
```
+----+-------+-----+
| id | name  | age |
+====+=======+=====+
| 1  | John   | 30  |
| 2  | Jane  | 25  |
+----+-------+-----+
```

##### `renderHTML()`

Mengembalikan HTML string dengan `<pre>` tag yang sudah diformat untuk terminal.

**Returns:** `String`

**Example:**
```javascript
const table = new TabelRaw(data);
const htmlTable = table.renderHTML();
document.getElementById('output').innerHTML = htmlTable;
```

## 💡 Contoh Penggunaan

### Contoh 1: Tabel Sederhana

```javascript
const data = [
    { version: '1.0.3', status: 'development', description: 'New features' },
    { version: '1.0.2', status: 'production', description: 'Bug fixes' }
];

const table = new TabelRaw(data);
console.log(table.render());
```

**Output:**
```
+---------+-------------+---------------+
| version |   status    |  description  |
+=========+=============+===============+
| 1.0.3   | development | New features  |
| 1.0.2   | production  | Bug fixes     |
+---------+-------------+---------------+
```

### Contoh 2: Dengan Index dan Alignment

```javascript
const data = [
    { id: 1, name: 'Product A', price: 100 },
    { id: 2, name: 'Product B', price: 200 }
];

const table = new TabelRaw(data, {
    showIndex: true,
    indexHeader: 'No',
    columnAlign: {
        'id': 'center',
        'price': 'right'
    }
});

console.log(table.render());
```

**Output:**
```
+----+----+-----------+-------+
| No | id |   name    | price |
+====+====+===========+=======+
|  1 | 1  | Product A |   100 |
|  2 | 2  | Product B |   200 |
+----+----+-----------+-------+
```

### Contoh 3: Tanpa Border

```javascript
const table = new TabelRaw(data, {
    border: false
});
```

### Contoh 4: Header Single Line

```javascript
const table = new TabelRaw(data, {
    headerStyle: 'single'
});
```

**Output:**
```
+----+-------+
| id | name  |
+----+-------+
| 1  | John  |
+----+-------+
```

### Contoh 5: Truncate Long Text

```javascript
const data = [
    { name: 'Very long description that will be truncated', value: 100 }
];

const table = new TabelRaw(data, {
    maxWidth: 50,
    truncate: true
});
```

### Contoh 6: Menggunakan Helper Functions

```javascript
// Quick render
import { createTable, createTableHTML } from './tabelRaw.js';

// Render as ASCII string
const ascii = createTable(data, { border: true });

// Render as HTML
const html = createTableHTML(data, { border: true });
```

## 🎨 Styling

### HTML Output Styling

Output HTML menggunakan inline styles dengan:
- Font: `'Courier New', Consolas, Monaco, monospace`
- Color: `#00ff00` (green terminal color)
- Line height: `1.2`

Untuk custom styling, Anda bisa wrap output HTML dengan CSS:

```javascript
const html = table.renderHTML();
// Replace color atau tambahkan custom class
const customHtml = html.replace('color: #00ff00', 'color: #00ffff');
```

## 🔧 Advanced Usage

### Custom Column Width

Tabel secara otomatis menghitung lebar kolom berdasarkan konten. Jika total lebar melebihi `maxWidth`, kolom akan di-scale down secara proporsional.

### Handling Empty Data

```javascript
const table = new TabelRaw([]);
console.log(table.render()); // Output: "No data available"
```

### Handling Complex Data Types

```javascript
const data = [
    { 
        id: 1, 
        metadata: { key: 'value' },  // Object akan di-stringify
        tags: ['tag1', 'tag2'],       // Array akan di-stringify
        date: new Date()              // Date akan di-convert ke string
    }
];

const table = new TabelRaw(data);
```

## 📝 Best Practices

1. **Gunakan `maxWidth`** untuk membatasi lebar tabel agar tidak terlalu lebar di terminal
2. **Gunakan `truncate: true`** untuk data dengan teks panjang
3. **Gunakan `columnAlign`** untuk alignment yang konsisten (misalnya angka di-align right)
4. **Gunakan `showIndex`** untuk tabel dengan banyak baris agar mudah di-refer
5. **Gunakan `headerStyle: 'double'`** untuk membedakan header dengan data

## 🐛 Troubleshooting

### Tabel Terlalu Lebar

**Problem:** Tabel melebihi lebar terminal

**Solution:**
```javascript
const table = new TabelRaw(data, {
    maxWidth: 80,  // Sesuaikan dengan lebar terminal
    truncate: true
});
```

### Data Tidak Tampil

**Problem:** Tabel kosong atau "No data available"

**Solution:** Pastikan data adalah array dan tidak kosong:
```javascript
if (data && data.length > 0) {
    const table = new TabelRaw(data);
}
```

### Format Object Tidak Tepat

**Problem:** Object di dalam data tidak ter-format dengan baik

**Solution:** Object akan otomatis di-stringify. Untuk format custom, transform data terlebih dahulu:
```javascript
const formattedData = data.map(item => ({
    ...item,
    metadata: JSON.stringify(item.metadata)
}));
```

## 📚 Contoh Integrasi dengan Terminal

```javascript
// Dalam terminal command handler
cmd.addCommand("list", async function () {
    const data = await fetchData();
    
    const table = new TabelRaw(data, {
        border: true,
        headerStyle: 'double',
        showIndex: true,
        columnAlign: {
            'id': 'center',
            'status': 'center'
        }
    });
    
    const tableHTML = table.renderHTML();
    cmd.output(tableHTML);
    cmd.startNewCommand();
    return false;
});
```

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Support basic ASCII table rendering
- Support HTML output
- Support custom alignment
- Support index column
- Auto-width calculation

## 📄 License

MIT License

## 👤 Author

NexaUI Terminal Team

---

**Note:** Dokumentasi ini dibuat untuk versi 1.0.0 dari TabelRaw.js. Untuk update terbaru, silakan cek source code.

