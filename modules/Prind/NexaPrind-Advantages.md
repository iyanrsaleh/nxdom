# Keunggulan NexaPrind.js

## 🎯 Ringkasan
NexaPrind.js adalah library JavaScript canggih untuk mencetak elemen HTML tertentu dengan kontrol penuh terhadap styling, layout, dan format kertas. Dibandingkan dengan solusi print biasa (`window.print()`), NexaPrind menawarkan banyak keunggulan.

---

## ✨ Keunggulan Utama

### 1. **Capture Dynamic Styles (Computed Styles)**
**Masalah dengan print biasa:**
- Style yang diterapkan secara dinamis (melalui JavaScript) sering hilang saat print
- Computed styles tidak ter-capture dengan baik

**Solusi NexaPrind:**
- ✅ Menangkap semua computed styles dari elemen secara otomatis
- ✅ Mempertahankan styling yang diterapkan secara dinamis
- ✅ Generate CSS otomatis dari computed styles dengan `!important`

```javascript
// NexaPrind otomatis menangkap semua computed styles
NexaPrind.printById('myElement', {
  captureDynamicStyles: true // Default: true
});
```

---

### 2. **Style Exclusion (styleNot) - Kontrol Granular**
**Keunggulan unik:**
- ✅ Bisa mengecualikan elemen tertentu dari style capture
- ✅ Mempertahankan styling asli untuk elemen yang dikecualikan
- ✅ Berguna untuk elemen yang sudah memiliki styling khusus

```javascript
// Exclude elemen tertentu dari style capture
NexaPrind.printById('content', {
  styleNot: ['.tcx-center', '.tcx-footer', '.custom-style']
});
```

---

### 3. **Table Preservation - Smart Table Handling**
**Masalah dengan print biasa:**
- Tabel sering rusak layoutnya saat print
- Border, spacing, dan alignment hilang
- Table width/height tidak terpertahankan

**Solusi NexaPrind:**
- ✅ **Otomatis mempertahankan semua styling tabel** (table, tr, td, th, dll)
- ✅ Tidak mengintervensi styling tabel sama sekali
- ✅ Table elements di-exclude dari style capture untuk preservasi maksimal
- ✅ Page-break handling khusus untuk tabel

```javascript
// Tabel akan tetap mempertahankan styling asli
// Tidak perlu konfigurasi khusus - otomatis!
```

---

### 4. **Heading Preservation - Natural Heading Sizes**
**Masalah dengan print biasa:**
- Font size global sering mengubah ukuran heading
- Heading menjadi terlalu kecil atau terlalu besar

**Solusi NexaPrind:**
- ✅ Heading (h1-h6) **otomatis mempertahankan ukuran natural** mereka
- ✅ Tidak terpengaruh oleh pengaturan `fontSize` global
- ✅ Bisa di-override untuk heading di dalam `styleNot`

```javascript
// Heading tetap natural, tidak terpengaruh fontSize global
NexaPrind.printById('content', {
  fontSize: '12pt', // Tidak mempengaruhi heading
  // Heading tetap menggunakan ukuran asli mereka
});
```

---

### 5. **Force Background Colors & Images**
**Masalah dengan print biasa:**
- Browser sering tidak mencetak background colors/images
- Harus manual enable "Background graphics" di print settings

**Solusi NexaPrind:**
- ✅ **Otomatis memaksa background colors dan images** saat print
- ✅ Menggunakan `print-color-adjust: exact` untuk semua browser
- ✅ Background images tetap tercetak

```javascript
NexaPrind.printById('content', {
  forceBackgrounds: true // Default: true
});
```

---

### 6. **Error Handling yang Komprehensif**
**Keunggulan:**
- ✅ Error handling di setiap tahap proses
- ✅ Skip broken resources (gambar 404, CSS yang tidak bisa diakses)
- ✅ Fallback mechanisms untuk berbagai skenario error
- ✅ Console warnings untuk debugging

```javascript
// Otomatis handle error tanpa crash
NexaPrind.printById('content', {
  skipBrokenResources: true // Default: true
});
```

---

### 7. **Multiple Paper Sizes & Orientation**
**Keunggulan:**
- ✅ Support berbagai ukuran kertas: A4, A3, A5, Letter, Legal
- ✅ Custom paper size
- ✅ Portrait dan Landscape orientation
- ✅ Pengaturan via CSS `@page` rule

```javascript
NexaPrind.printById('content', {
  paperSize: 'A4',        // A4, A3, A5, Letter, Legal, Custom
  orientation: 'portrait', // portrait, landscape
  customSize: { width: '210mm', height: '297mm' } // Jika Custom
});
```

---

### 8. **Custom Margins - Per Sisi atau Global**
**Keunggulan:**
- ✅ Margin global atau per sisi (top, right, bottom, left)
- ✅ Support berbagai format: angka, string dengan unit, atau "none"
- ✅ Auto-format: jika hanya angka, otomatis tambahkan "mm"

```javascript
// Margin global
NexaPrind.printById('content', {
  margins: '20mm'
});

// Margin per sisi
NexaPrind.printById('content', {
  marginTop: '20mm',
  marginRight: '15mm',
  marginBottom: '20mm',
  marginLeft: '15mm'
});
```

---

### 9. **Font Customization**
**Keunggulan:**
- ✅ Custom font size, family, dan line height
- ✅ Heading tidak terpengaruh (preserved)
- ✅ Fallback fonts untuk kompatibilitas
- ✅ Table elements tidak terpengaruh

```javascript
NexaPrind.printById('content', {
  fontSize: '11pt',
  fontFamily: 'Times New Roman, serif',
  lineHeight: '1.5',
  useFallbackFonts: true // Default: true
});
```

---

### 10. **Performance Optimization**
**Keunggulan:**
- ✅ Limit jumlah elemen yang diproses (default: 100 elemen)
- ✅ Hanya capture critical CSS properties
- ✅ Bisa di-disable untuk full capture

```javascript
NexaPrind.printById('content', {
  optimizePerformance: true, // Default: true
  // Limit ke 100 elemen untuk performa optimal
});
```

---

### 11. **Dual Print Mode - Window Baru atau Current Window**
**Keunggulan:**
- ✅ Print di window baru (preview sebelum print)
- ✅ Print di window saat ini (langsung print)
- ✅ Auto-restore konten asli setelah print
- ✅ Auto-close window baru setelah print (opsional)

```javascript
// Window baru (preview)
NexaPrind.printById('content', {
  newWindow: true,
  removeAfterPrint: true // Tutup otomatis setelah print
});

// Window saat ini (langsung print)
NexaPrind.printById('content', {
  newWindow: false // Default
});
```

---

### 12. **Auto Content Restoration**
**Keunggulan:**
- ✅ Otomatis restore konten asli setelah print
- ✅ Restore title asli
- ✅ Event listener untuk `afterprint`
- ✅ Fallback timeout jika event tidak trigger

```javascript
// Otomatis restore - tidak perlu manual
// Konten asli akan dikembalikan setelah print selesai
```

---

### 13. **Smart Page Break Handling**
**Keunggulan:**
- ✅ Page break handling khusus untuk tabel
- ✅ Orphan dan widow line prevention
- ✅ Natural page breaks untuk konten panjang
- ✅ Table tidak terpotong di tengah halaman

```javascript
// Otomatis handle page breaks dengan baik
// Tabel tidak akan terpotong di tengah
```

---

### 14. **CSS Extraction dengan Error Recovery**
**Keunggulan:**
- ✅ Extract CSS dari stylesheets yang accessible
- ✅ Skip stylesheets yang broken atau tidak bisa diakses
- ✅ Extract dari `<style>` tags
- ✅ Validation untuk menghindari CSS error

```javascript
// Otomatis extract CSS yang bisa diakses
// Skip yang broken untuk menghindari error
```

---

### 15. **Multiple Selection Methods**
**Keunggulan:**
- ✅ Print by ID: `printById()`
- ✅ Print by Selector: `printBySelector()`
- ✅ Static methods - tidak perlu instantiate

```javascript
// Dua cara mudah untuk memilih elemen
NexaPrind.printById('myElement');
NexaPrind.printBySelector('.print-content');
```

---

### 16. **Print Color Adjust - Cross Browser**
**Keunggulan:**
- ✅ Support `-webkit-print-color-adjust: exact`
- ✅ Support `color-adjust: exact`
- ✅ Support `print-color-adjust: exact`
- ✅ Event listener untuk `beforeprint`

```javascript
// Otomatis set print color adjust untuk semua browser
// Background colors/images akan tercetak dengan baik
```

---

### 17. **No-Print Class Support**
**Keunggulan:**
- ✅ Otomatis hide elemen dengan class `.no-print`
- ✅ Berguna untuk tombol, navigasi, dll yang tidak perlu dicetak

```html
<div class="no-print">
  <!-- Ini tidak akan tercetak -->
</div>
```

---

### 18. **Comprehensive @page Rules**
**Keunggulan:**
- ✅ `@page` rule untuk semua halaman
- ✅ `@page :first` untuk halaman pertama
- ✅ `@page :left` dan `@page :right` untuk duplex printing
- ✅ Margin konsisten di semua halaman

---

## 📊 Perbandingan dengan Print Biasa

| Fitur | `window.print()` | NexaPrind.js |
|-------|------------------|--------------|
| Capture Dynamic Styles | ❌ Tidak | ✅ Ya |
| Style Exclusion | ❌ Tidak | ✅ Ya (styleNot) |
| Table Preservation | ❌ Sering rusak | ✅ Otomatis preserved |
| Heading Preservation | ❌ Terpengaruh global | ✅ Natural preserved |
| Background Colors | ❌ Perlu manual enable | ✅ Otomatis forced |
| Paper Size Control | ❌ Terbatas | ✅ Full control |
| Custom Margins | ❌ Terbatas | ✅ Per sisi atau global |
| Font Customization | ❌ Terbatas | ✅ Full control |
| Error Handling | ❌ Minimal | ✅ Comprehensive |
| Performance Optimization | ❌ Tidak | ✅ Optimized |
| Window Options | ❌ Satu cara | ✅ Window baru atau current |
| Auto Restore | ❌ Manual | ✅ Otomatis |
| Page Break Control | ❌ Terbatas | ✅ Smart handling |

---

## 🎯 Kapan Menggunakan NexaPrind?

### ✅ Gunakan NexaPrind jika:
- Perlu kontrol penuh terhadap styling saat print
- Ada elemen dengan dynamic styles yang harus dipertahankan
- Perlu print tabel dengan layout yang kompleks
- Perlu custom paper size dan margins
- Perlu background colors/images tercetak
- Perlu exclude elemen tertentu dari style capture
- Perlu preview sebelum print (window baru)

### ❌ Tidak perlu NexaPrind jika:
- Hanya perlu print sederhana tanpa styling khusus
- Tidak ada dynamic styles
- Tidak perlu kontrol paper size/margins
- Print seluruh halaman tanpa seleksi elemen

---

## 💡 Best Practices

1. **Gunakan `optimizePerformance: true`** untuk dokumen besar
2. **Gunakan `styleNot`** untuk elemen yang sudah memiliki styling khusus
3. **Gunakan `newWindow: true`** untuk preview sebelum print
4. **Set `removeAfterPrint: true`** jika tidak perlu window baru tetap terbuka
5. **Gunakan `skipBrokenResources: true`** untuk menghindari error dari resource yang broken
6. **Test dengan berbagai browser** untuk memastikan kompatibilitas

---

## 🚀 Kesimpulan

NexaPrind.js adalah solusi print yang **powerful, flexible, dan robust** dengan:
- ✅ Kontrol penuh terhadap styling dan layout
- ✅ Error handling yang komprehensif
- ✅ Performance optimization
- ✅ Cross-browser compatibility
- ✅ Easy to use API
- ✅ Smart preservation untuk tabel dan heading
- ✅ Comprehensive configuration options

**Ideal untuk aplikasi yang memerlukan print quality tinggi dengan kontrol penuh terhadap output.**

