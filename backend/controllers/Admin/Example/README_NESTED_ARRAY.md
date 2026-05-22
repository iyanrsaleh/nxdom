# 📚 Tutorial: Array Bersarang (Nested Array) dengan Flatten Structure

## 🎯 Tujuan
Menampilkan data nested array (array di dalam array) di template NexaDom menggunakan teknik **Flatten Structure**.

---

## ❌ Masalah: Nested Array Tidak Bisa Langsung Digunakan

```php
// ❌ STRUKTUR INI TIDAK BISA LANGSUNG DITAMPILKAN
$produk = [
    [
        "nama" => "Laptop",
        "harga" => 7500000,
        "item" => [  // ← Array di dalam array (nested)
            ["kode" => "LP-01", "warna" => "Hitam"],
            ["kode" => "LP-02", "warna" => "Silver"]
        ]
    ],
    [
        "nama" => "Mouse",
        "harga" => 150000,
        "item" => [
            ["kode" => "MS-01", "warna" => "Hitam"]
        ]
    ]
];

// ❌ INI TIDAK AKAN BEKERJA:
$this->nexaBlock('produk', $produk);
```

**Kenapa?** NexaDom template system tidak support nested blocks secara native.

---

## ✅ Solusi: Flatten Structure

**Konsep:** "Ratakan" array bersarang menjadi satu array linear dengan **flag pembeda**.

### 📝 Langkah 1: Flatten di Controller

```php
// ✅ SOLUSI: FLATTEN STRUCTURE
$produkFlat = [];

foreach ($produk as $index => $parent) {
    // 1. TAMBAHKAN PARENT HEADER
    $produkFlat[] = [
        'is_parent_header' => true,  // ← FLAG pembeda
        'nama' => $parent['nama'],
        'harga' => number_format($parent['harga'], 0, ',', '.')
    ];
    
    // 2. TAMBAHKAN CHILD ITEMS
    if (!empty($parent['item'])) {
        foreach ($parent['item'] as $child) {
            $produkFlat[] = [
                'is_child_item' => true,  // ← FLAG pembeda
                'kode' => $child['kode'],
                'warna' => $child['warna']
            ];
        }
    } else {
        // 3. JIKA TIDAK ADA DATA
        $produkFlat[] = [
            'is_no_data' => true  // ← FLAG pembeda
        ];
    }
    
    // 4. TAMBAHKAN PARENT FOOTER
    $produkFlat[] = [
        'is_parent_footer' => true  // ← FLAG pembeda
    ];
}

// ✅ SEKARANG BISA DIGUNAKAN
$this->nexaBlock('produk', $produkFlat);
```

### 📊 Hasil Array Flat

```php
[
    0 => ['is_parent_header' => true, 'nama' => 'Laptop', ...],
    1 => ['is_child_item' => true, 'kode' => 'LP-01', ...],
    2 => ['is_child_item' => true, 'kode' => 'LP-02', ...],
    3 => ['is_parent_footer' => true],
    4 => ['is_parent_header' => true, 'nama' => 'Mouse', ...],
    5 => ['is_child_item' => true, 'kode' => 'MS-01', ...],
    6 => ['is_parent_footer' => true]
]
```

### 🎨 Langkah 2: Render di Template

```html
<!-- NEXA produk -->

{if is_parent_header}
<div class="card">
    <h3>{nama}</h3>
    <p>Rp {harga}</p>
    <table>
        <thead>
            <tr><th>Kode</th><th>Warna</th></tr>
        </thead>
        <tbody>
{endif}

{if is_child_item}
            <tr>
                <td>{kode}</td>
                <td>{warna}</td>
            </tr>
{endif}

{if is_no_data}
            <tr>
                <td colspan="2">Tidak ada item</td>
            </tr>
{endif}

{if is_parent_footer}
        </tbody>
    </table>
</div>
{endif}

<!-- END produk -->
```

---

## 🔥 Contoh Real: Order Tracking

### Controller (`OrderController.php`)

```php
$trackingData = [];

foreach ($orders as $orderIndex => $order) {
    $trackingItems = $this->notifikasi($order['order_number']);
    
    // 1. ORDER HEADER
    $trackingData[] = [
        'is_order_header' => true,
        'id' => $order['order_number'],
        'status' => $order['status'],
        'has_data' => !empty($trackingItems)
    ];
    
    // 2. TRACKING ITEMS
    if (!empty($trackingItems)) {
        foreach ($trackingItems as $item) {
            $trackingData[] = [
                'is_tracking_item' => true,
                'status' => $item['status'],
                'message' => $item['message'],
                'created_at' => $item['created_at']
            ];
        }
    } else {
        // 3. NO DATA
        $trackingData[] = [
            'is_no_data' => true
        ];
    }
    
    // 4. ORDER FOOTER
    $trackingData[] = [
        'is_order_footer' => true
    ];
}

$this->nexaBlock('tracking', $trackingData);
```

### Template (`order/index.html`)

```html
<!-- NEXA tracking -->

{if is_order_header}
<div class="ui accordion">
    <div class="title">
        <span>Order ID: {id}</span>
    </div>
    <div class="content">
        <table class="ui celled table">
            <tbody>
{endif}

{if is_tracking_item}
                <tr>
                    <td>{created_at}</td>
                </tr>
                <tr>
                    <td>{status}</td>
                    <td>{message}</td>
                </tr>
{endif}

{if is_no_data}
                <tr>
                    <td>Belum ada tracking</td>
                </tr>
{endif}

{if is_order_footer}
            </tbody>
        </table>
    </div>
</div>
{endif}

<!-- END tracking -->
```

---

## 📋 Ringkasan: 4 Langkah Mudah

1. **Buat array kosong** untuk hasil flatten
2. **Loop parent**, untuk setiap parent:
   - Tambahkan **header** (flag: `is_parent_header`)
   - Loop child, tambahkan **items** (flag: `is_child_item`)
   - Jika kosong, tambahkan **no_data** (flag: `is_no_data`)
   - Tambahkan **footer** (flag: `is_parent_footer`)
3. **Assign** hasil flatten: `$this->nexaBlock('nama', $dataFlat)`
4. **Di template**, gunakan `{if flag}` untuk membedakan jenis data

---

## ✨ Keuntungan Flatten Structure

| Keuntungan | Penjelasan |
|------------|------------|
| ✅ **Kompatibel** | Bekerja dengan NexaDom template system |
| ✅ **Sederhana** | Hanya butuh SATU `nexaBlock()` call |
| ✅ **Mudah Debug** | Array linear lebih mudah di-trace |
| ✅ **Fleksibel** | Bisa untuk berbagai jenis nested data |
| ✅ **Performa** | Tidak perlu nested loop di template |

---

## 💡 Tips & Best Practices

1. **Nama Flag Jelas**: Gunakan `is_header`, `is_item`, `is_footer`
2. **Tambahkan Index**: Gunakan `parent_index` untuk tracking relasi
3. **Handle Empty**: Selalu tambahkan `is_no_data` untuk UX yang baik
4. **Konsisten**: Gunakan pattern yang sama di seluruh project
5. **Dokumentasi**: Tambahkan komentar untuk memudahkan maintenance

---

## 📁 File Referensi

- **Dokumentasi Lengkap**: `controllers/Admin/Example/nested_array_flatten.php`
- **Template Contoh**: `templates/exasmpl/nested_array_flatten.html`
- **Implementasi Real**: 
  - Controller: `controllers/Admin/OrderController.php`
  - Template: `templates/dashboard/order/index.html`
- **Contoh Lain**: `controllers/Admin/CheckoutController.php` (line 62-142)

---

## 🎓 Latihan

Coba buat flatten structure untuk data berikut:

```php
$kategori = [
    [
        "nama" => "Elektronik",
        "produk" => [
            ["nama" => "TV", "harga" => 5000000],
            ["nama" => "Radio", "harga" => 500000]
        ]
    ],
    [
        "nama" => "Fashion",
        "produk" => [
            ["nama" => "Baju", "harga" => 150000]
        ]
    ]
];
```

**Hint**: Gunakan flag `is_category_header`, `is_product_item`, `is_category_footer`

---

**Happy Coding! 🚀**
